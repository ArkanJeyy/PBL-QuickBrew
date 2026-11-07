<?php
/**
 * PembayaranService — sentralisasi integrasi Midtrans Core API QRIS, verifikasi webhook,
 * sinkronisasi status cadangan, dan pelunasan pesanan QuickBrew.
 *
 * Memakai cURL native PHP (tanpa Composer). Kredensial dibaca lewat env() dari config/config.php:
 *   MIDTRANS_SERVER_KEY  server key Sandbox / Production
 *   MIDTRANS_PROD        'true' -> Production, selain itu -> Sandbox
 *
 * Kontrak utama:
 *   - buatQris($pesanan, $detail)                     -> charge QRIS Core API (POST /v2/charge)
 *   - sinkron($midtransOrderId)                       -> cek status Core API (GET /v2/{id}/status)
 *   - verifikasiSignature($notif)                     -> validasi SHA512 signature_key webhook
 *   - terapkanStatus($data)                           -> terapkan notifikasi/status ke DB
 *   - tandaiLunas($pemesananId, $metode, $penggunaId) -> pelunasan idempotent (QRIS maupun Tunai kasir)
 */
final class PembayaranService
{
    /** Masa berlaku transaksi QRIS dalam menit (satu sumber kebenaran bersama PemesananModel). */
    public const EXPIRY_MINUTES = 15;

    /** Batas waktu request HTTP ke Midtrans (detik) agar halaman tidak hang. */
    private const TIMEOUT         = 20;
    private const CONNECT_TIMEOUT = 10;

    /** Batas panjang field item_details di Midtrans. */
    private const MAX_ITEM_NAME = 50;
    private const MAX_ITEM_ID   = 50;

    private string $serverKey;
    private bool   $production;

    /**
     * Transport HTTP opsional untuk pengujian otomatis (tanpa memanggil jaringan Midtrans).
     * Signature: fn(string $method, string $url, ?array $body): array{httpCode:int, raw?:string|false, body?:array, errno?:int, error?:string}
     *
     * @var callable|null
     */
    private $httpTransport;

    public function __construct(?callable $httpTransport = null)
    {
        $this->serverKey     = (string) env('MIDTRANS_SERVER_KEY', '');
        $this->production    = env('MIDTRANS_PROD', 'false') === 'true';
        $this->httpTransport = $httpTransport;

        if ($this->serverKey === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diisi di config/.env.');
        }
    }

    // ------------------------------------------------------------------
    //  1. Membuat transaksi QRIS (Midtrans Core API: POST /v2/charge)
    // ------------------------------------------------------------------

    /**
     * Buat transaksi QRIS dinamis melalui Midtrans Core API (POST /v2/charge).
     * PENTING: Jangan memanggil metode ini di dalam transaksi database yang sedang terbuka.
     *
     * @param array $pesanan Baris pesanan. Wajib: id, total_harga.
     *                       Opsional: nama_pelanggan, email_pelanggan, pajak (int, item tersendiri).
     * @param array $detail  Baris detail pesanan. Per baris wajib: menu_id, harga (sudah termasuk
     *                       ukuran & add-on), jumlah. Opsional: nama_menu, catatan.
     * @return array{
     *     midtrans_order_id: string,
     *     transaction_id: string,
     *     qr_string: string,
     *     qr_url: string,
     *     expiry_time: string
     * }
     * @throws InvalidArgumentException bila data pesanan tidak valid atau SUM(item) != gross_amount.
     * @throws RuntimeException         bila panggilan ke Midtrans gagal atau respons ditolak.
     */
    public function buatQris(array $pesanan, array $detail): array
    {
        [$orderId, $items, $grossAmount] = $this->siapkanTransaksi($pesanan, $detail);

        // Payload Core API QRIS sesuai dokumentasi resmi Midtrans (POST /v2/charge)
        $payload = [
            'payment_type'        => 'qris',
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'item_details'     => $items,
            'customer_details' => array_filter([
                'first_name' => self::potong((string) ($pesanan['nama_pelanggan'] ?? ''), 50),
                'email'      => (string) ($pesanan['email_pelanggan'] ?? ''),
            ], static fn (string $v): bool => $v !== ''),
            'qris' => [
                'acquirer' => 'gopay',
            ],
            'custom_expiry' => [
                'expiry_duration' => self::EXPIRY_MINUTES,
                'unit'            => 'minute',
            ],
        ];

        try {
            ['data' => $response] = $this->request('POST', $this->apiUrl() . '/charge', $payload);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Gagal membuat pembayaran QRIS Midtrans: ' . $e->getMessage(), 0, $e);
        }

        // Core API dapat mengembalikan HTTP 200 dengan status_code 4xx/5xx di dalam body JSON.
        $statusCode = (string) ($response['status_code'] ?? '');
        $txStatus   = (string) ($response['transaction_status'] ?? '');
        $qrString   = trim((string) ($response['qr_string'] ?? ''));

        if ($statusCode === '' || $statusCode[0] !== '2' || $txStatus !== 'pending' || $qrString === '') {
            $pesan = (string) ($response['status_message'] ?? 'Respons QRIS Midtrans tidak valid.');
            if (!empty($response['validation_messages']) && is_array($response['validation_messages'])) {
                $pesan .= ' (' . implode('; ', array_map('strval', $response['validation_messages'])) . ')';
            }
            throw new RuntimeException(sprintf(
                'Midtrans menolak pembuatan QRIS (kode %s, status %s): %s',
                $statusCode !== '' ? $statusCode : '-',
                $txStatus !== '' ? $txStatus : '-',
                $pesan
            ));
        }

        // Ekstrak URL gambar QR cadangan dari actions[] yang namanya diawali "generate-qr-code"
        $qrUrl = '';
        foreach ((array) ($response['actions'] ?? []) as $action) {
            if (!is_array($action)) {
                continue;
            }
            $actName = (string) ($action['name'] ?? '');
            $actUrl  = trim((string) ($action['url'] ?? ''));
            if (str_starts_with($actName, 'generate-qr-code') && $actUrl !== '') {
                $qrUrl = $actUrl;
                break;
            }
        }

        return [
            'midtrans_order_id' => (string) ($response['order_id'] ?? $orderId),
            'transaction_id'    => (string) ($response['transaction_id'] ?? ''),
            'qr_string'         => $qrString,
            'qr_url'            => $qrUrl,
            'expiry_time'       => (string) ($response['expiry_time'] ?? ''),
        ];
    }

    /**
     * Buat transaksi Snap Midtrans untuk satu pesanan (warisan implementasi awal).
     *
     * @deprecated Tidak dipakai lagi sejak beralih ke Core API QRIS; gunakan {@see buatQris()}.
     * @return array{snap_token: string, midtrans_order_id: string, redirect_url: ?string}
     */
    public function buatTransaksi(array $pesanan, array $detailPesanan): array
    {
        [$orderId, $items, $grossAmount] = $this->siapkanTransaksi($pesanan, $detailPesanan);

        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'item_details'     => $items,
            'customer_details' => array_filter([
                'first_name' => self::potong((string) ($pesanan['nama_pelanggan'] ?? ''), 50),
                'email'      => (string) ($pesanan['email_pelanggan'] ?? ''),
            ], static fn (string $v): bool => $v !== ''),
            'expiry' => [
                'unit'     => 'minutes',
                'duration' => self::EXPIRY_MINUTES,
            ],
        ];

        try {
            ['data' => $response] = $this->request('POST', $this->snapUrl() . '/transactions', $payload);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Gagal membuat transaksi Midtrans: ' . $e->getMessage(), 0, $e);
        }

        if (empty($response['token'])) {
            throw new RuntimeException('Respons Midtrans tidak berisi snap token.');
        }

        return [
            'snap_token'        => (string) $response['token'],
            'midtrans_order_id' => $orderId,
            'redirect_url'      => $response['redirect_url'] ?? null,
        ];
    }

    // ------------------------------------------------------------------
    //  2. Sinkronisasi status cadangan (GET /v2/{order_id}/status)
    // ------------------------------------------------------------------

    /**
     * Ambil status transaksi langsung dari Midtrans (GET /v2/{order_id}/status) sebagai
     * cadangan bila webhook belum sampai, lalu terapkan ke database.
     *
     * @return array|null Data status Midtrans, atau null bila Midtrans membalas 404 (transaksi belum ada).
     * @throws InvalidArgumentException bila midtrans_order_id kosong.
     * @throws RuntimeException         bila request gagal atau status_code selain 2xx/404.
     */
    public function sinkron(string $midtrans_order_id): ?array
    {
        $midtrans_order_id = trim($midtrans_order_id);
        if ($midtrans_order_id === '') {
            throw new InvalidArgumentException('midtrans_order_id kosong.');
        }

        try {
            $res = $this->request(
                'GET',
                $this->apiUrl() . '/' . rawurlencode($midtrans_order_id) . '/status',
                null,
                true // izinkan HTTP 404 ditangani sebagai null di bawah
            );
        } catch (RuntimeException $e) {
            throw new RuntimeException('Gagal mengecek status Midtrans: ' . $e->getMessage(), 0, $e);
        }

        $httpCode = $res['httpCode'];
        $status   = $res['data'];
        $code     = (string) ($status['status_code'] ?? '');

        // Transaksi belum dikenal di Midtrans (HTTP 404 atau HTTP 200 dengan status_code "404")
        if ($httpCode === 404 || $code === '404') {
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $pesan = (string) ($status['status_message'] ?? 'Permintaan cek status ditolak.');
            throw new RuntimeException(sprintf('HTTP %d dari Midtrans: %s', $httpCode, $pesan));
        }

        if ($code !== '' && $code[0] !== '2') {
            throw new RuntimeException(sprintf(
                'Status Midtrans %s: %s',
                $code,
                $status['status_message'] ?? 'tidak diketahui'
            ));
        }

        $this->terapkanStatus($status);
        return $status;
    }

    /**
     * Khusus lingkungan Sandbox (MIDTRANS_PROD === false):
     * Simulasikan pembayaran QRIS sukses langsung ke simulator Midtrans Sandbox,
     * lalu sinkronkan statusnya. Bila simulator Midtrans tidak dapat dijangkau,
     * gunakan fallback pelunasan lokal agar proses demo tidak pernah gagal.
     */
    public function simulasiQrisSandbox(int $pemesananId, ?string $rawQrisData = null): bool
    {
        if (MIDTRANS_PROD) {
            throw new RuntimeException('Simulasi pembayaran hanya diizinkan pada lingkungan Sandbox.');
        }

        $qrUrl = '';
        if ($rawQrisData !== null && $rawQrisData !== '') {
            $decoded = json_decode($rawQrisData, true);
            if (is_array($decoded)) {
                $qrUrl = trim((string) ($decoded['qr_url'] ?? ''));
            }
        }

        if ($qrUrl === '') {
            $p = db()->prepare('SELECT qris_data, midtrans_order_id FROM pembayaran WHERE pemesanan_id = ?');
            $p->execute([$pemesananId]);
            $row = $p->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $decoded = json_decode((string) ($row['qris_data'] ?? ''), true);
                if (is_array($decoded)) {
                    $qrUrl = trim((string) ($decoded['qr_url'] ?? ''));
                }
            }
        }

        $simulasiBerhasil = false;
        if ($qrUrl !== '') {
            try {
                // Tahap 1: Pindai QR di simulator Midtrans untuk mendapatkan form payload
                $ch = curl_init('https://simulator.sandbox.midtrans.com/v2/qris/payment');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query(['qrCodeUrl' => $qrUrl]),
                    CURLOPT_TIMEOUT        => 5,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $resp1 = (string) curl_exec($ch);
                curl_close($ch);

                // Ekstrak referenceId dan exploreData
                if (
                    preg_match('/name="referenceId"\s+type="hidden"\s+value="([^"]+)"/', $resp1, $mRef)
                    && preg_match('/name="exploreData"\s+type="hidden"\s+value="([^"]+)"/', $resp1, $mExp)
                ) {
                    $refId   = html_entity_decode($mRef[1], ENT_QUOTES, 'UTF-8');
                    $expData = html_entity_decode($mExp[1], ENT_QUOTES, 'UTF-8');

                    // Tahap 2: Kirim instruksi Pay ke simulator GoPay
                    $ch2 = curl_init('https://simulator.sandbox.midtrans.com/v2/qris/payment/gopay');
                    curl_setopt_array($ch2, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => http_build_query([
                            'referenceId' => $refId,
                            'exploreData' => $expData,
                            'issuer'      => 'gopay',
                        ]),
                        CURLOPT_TIMEOUT        => 5,
                        CURLOPT_SSL_VERIFYPEER => true,
                    ]);
                    $resp2 = (string) curl_exec($ch2);
                    curl_close($ch2);

                    if (stripos($resp2, 'PAID') !== false || stripos($resp2, 'Success') !== false) {
                        $simulasiBerhasil = true;
                    }
                }
            } catch (Throwable $e) {
                error_log('[QuickBrew Sandbox Simulator] ' . $e->getMessage());
            }
        }

        // Ambil midtrans_order_id untuk sinkronisasi
        $stmt = db()->prepare('SELECT midtrans_order_id FROM pembayaran WHERE pemesanan_id = ?');
        $stmt->execute([$pemesananId]);
        $orderId = (string) $stmt->fetchColumn();

        if ($simulasiBerhasil && $orderId !== '') {
            try {
                $this->sinkron($orderId);
                return true;
            } catch (Throwable $e) {
                error_log('[QuickBrew Sandbox Simulator Sinkron Gagal] ' . $e->getMessage());
            }
        }

        // Fallback tangguh: pastikan pesanan selalu lunas di DB lokal
        return $this->tandaiLunas($pemesananId, 'qris');
    }

    // ------------------------------------------------------------------
    //  3. Satu fungsi pelunasan terpusat (QRIS webhook/sinkron & Tunai kasir)
    // ------------------------------------------------------------------

    /**
     * Tandai pesanan sebagai lunas secara atomik dan idempotent:
     *   - Kunci baris pemesanan & pembayaran (SELECT ... FOR UPDATE).
     *   - Hanya memproses bila pemesanan.status_pemesanan === 'menunggu_pembayaran'.
     *   - Kurangi stok per menu (kunci baris menu FOR UPDATE; bila stok kurang -> set 0 + log,
     *     pembayaran tetap diselesaikan agar tidak melanggar CHECK stok >= 0).
     *   - Ubah pemesanan.status_pemesanan menjadi 'diproses' (bukan 'dibayar').
     *   - Ubah pembayaran.status_pembayaran menjadi 'paid', isi waktu_pembayaran, metode_pembayaran,
     *     dan pembayaran.pengguna_id (jika diberikan oleh kasir).
     *
     * Dapat dipanggil mandiri (membuka transaksi sendiri) maupun di dalam transaksi aktif.
     *
     * @return bool True bila pelunasan baru saja diterapkan; false bila sudah pernah diproses / tidak ditemukan.
     */
    public function tandaiLunas(int $pemesananId, string $metode, ?int $penggunaId = null): bool
    {
        if ($pemesananId <= 0) {
            throw new InvalidArgumentException('ID pemesanan tidak valid.');
        }

        $db    = db();
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }

        try {
            // Kunci baris pemesanan dan pembayaran sekaligus untuk mencegah race condition
            $stmtLock = $db->prepare("
                SELECT p.id, p.status_pemesanan, b.id AS pembayaran_id
                  FROM pemesanan p
                  JOIN pembayaran b ON b.pemesanan_id = p.id
                 WHERE p.id = ?
                   FOR UPDATE OF p, b
            ");
            $stmtLock->execute([$pemesananId]);
            $row = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if (!$row || (string) $row['status_pemesanan'] !== 'menunggu_pembayaran') {
                if ($ownTx) {
                    $db->commit();
                }
                return false;
            }

            // Hitung total jumlah per menu (dipisah dari FOR UPDATE karena PostgreSQL tidak
            // mengizinkan FOR UPDATE pada query GROUP BY), diurutkan berdasarkan menu_id untuk mencegah deadlock.
            $stmtQty = $db->prepare("
                SELECT menu_id, SUM(jumlah) AS jumlah_dipesan
                  FROM detail_pemesanan
                 WHERE pemesanan_id = ?
                 GROUP BY menu_id
                 ORDER BY menu_id
            ");
            $stmtQty->execute([$pemesananId]);
            $kebutuhanPerMenu = $stmtQty->fetchAll(PDO::FETCH_KEY_PAIR);

            $stmtLockMenu   = $db->prepare("SELECT id, nama_menu, stok FROM menu WHERE id = ? FOR UPDATE");
            $stmtUpdateStok = $db->prepare("UPDATE menu SET stok = ? WHERE id = ?");

            foreach ($kebutuhanPerMenu as $menuId => $jumlahDipesan) {
                $menuId        = (int) $menuId;
                $jumlahDipesan = (int) $jumlahDipesan;

                $stmtLockMenu->execute([$menuId]);
                $menuRow = $stmtLockMenu->fetch(PDO::FETCH_ASSOC);
                if (!$menuRow) {
                    continue;
                }

                $stokSaatIni = (int) $menuRow['stok'];
                if ($stokSaatIni < $jumlahDipesan) {
                    error_log(sprintf(
                        '[QuickBrew Pembayaran] Stok menu #%d (%s) kurang saat pelunasan pemesanan_id=%d (dipesan=%d, tersedia=%d). Stok diset menjadi 0.',
                        $menuId,
                        $menuRow['nama_menu'],
                        $pemesananId,
                        $jumlahDipesan,
                        $stokSaatIni
                    ));
                    $stokBaru = 0;
                } else {
                    $stokBaru = $stokSaatIni - $jumlahDipesan;
                }

                $stmtUpdateStok->execute([$stokBaru, $menuId]);
            }

            // Pesanan yang sudah lunas langsung berstatus 'diproses'
            $db->prepare("UPDATE pemesanan SET status_pemesanan = 'diproses' WHERE id = ?")
               ->execute([$pemesananId]);

            $metodeBersih = trim($metode);
            $db->prepare("
                UPDATE pembayaran
                   SET status_pembayaran = 'paid',
                       waktu_pembayaran  = CURRENT_TIMESTAMP,
                       metode_pembayaran = COALESCE(NULLIF(?, ''), metode_pembayaran),
                       pengguna_id       = COALESCE(?, pengguna_id)
                 WHERE pemesanan_id = ?
            ")->execute([$metodeBersih, $penggunaId, $pemesananId]);

            if ($ownTx) {
                $db->commit();
            }
            return true;
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    //  4. Verifikasi signature & pemetaan status Midtrans -> Database
    // ------------------------------------------------------------------

    /**
     * Verifikasi signature_key notifikasi webhook Midtrans:
     * SHA512(order_id + status_code + gross_amount + server_key).
     * Nilai gross_amount dipakai persis apa adanya (mis. "24000.00") tanpa diformat ulang.
     */
    public function verifikasiSignature(array $notif): bool
    {
        $orderId      = (string) ($notif['order_id'] ?? '');
        $statusCode   = (string) ($notif['status_code'] ?? '');
        $grossAmount  = isset($notif['gross_amount']) ? (string) $notif['gross_amount'] : '';
        $signatureKey = (string) ($notif['signature_key'] ?? '');

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
            return false;
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
        return hash_equals($expected, $signatureKey);
    }

    /**
     * Terapkan payload status Midtrans (dari webhook atau sinkron()) ke database.
     *
     * Aturan:
     *   - Pencarian pesanan HANYA melalui pembayaran.midtrans_order_id (tanpa fallback regex).
     *   - gross_amount dicocokkan secara numerik terhadap pembayaran.total_bayar.
     *   - Idempotent: hanya memproses bila pemesanan.status_pemesanan === 'menunggu_pembayaran'.
     *   - settlement / capture+accept      -> tandaiLunas() (pemesanan 'diproses', pembayaran 'paid', stok berkurang).
     *   - deny / cancel / expire / failure -> pemesanan 'batal', pembayaran = status terkait.
     *   - pending                          -> catat metode_pembayaran saja.
     *   - lainnya (mis. capture+challenge) -> catat ke log, tanpa perubahan status.
     *
     * @return string Kode hasil pemrosesan: 'paid'|'failed'|'pending'|'already_processed'|'not_found'|'amount_mismatch'|'ignored'
     */
    public function terapkanStatus(array $data): string
    {
        $orderId           = trim((string) ($data['order_id'] ?? ''));
        $transactionStatus = trim((string) ($data['transaction_status'] ?? ''));
        $fraudStatus       = isset($data['fraud_status']) && trim((string) $data['fraud_status']) !== ''
            ? trim((string) $data['fraud_status'])
            : 'accept';
        $paymentType       = isset($data['payment_type']) && trim((string) $data['payment_type']) !== ''
            ? trim((string) $data['payment_type'])
            : null;
        $grossAmountRaw    = isset($data['gross_amount']) ? trim((string) $data['gross_amount']) : '';

        if ($orderId === '') {
            error_log('[QuickBrew Pembayaran] Payload status tidak memiliki order_id.');
            return 'ignored';
        }

        $db = db();
        $db->beginTransaction();
        try {
            // Cari pesanan HANYA berdasarkan midtrans_order_id dan kunci kedua baris
            $stmtOrder = $db->prepare("
                SELECT p.id                AS pemesanan_id,
                       p.status_pemesanan  AS status_pemesanan,
                       b.id                AS pembayaran_id,
                       b.status_pembayaran AS status_pembayaran,
                       b.total_bayar       AS total_bayar
                  FROM pembayaran b
                  JOIN pemesanan p ON p.id = b.pemesanan_id
                 WHERE b.midtrans_order_id = ?
                   FOR UPDATE OF p, b
            ");
            $stmtOrder->execute([$orderId]);
            $pesanan = $stmtOrder->fetch(PDO::FETCH_ASSOC);

            if (!$pesanan) {
                $db->rollBack();
                error_log("[QuickBrew Pembayaran] Pesanan dengan midtrans_order_id={$orderId} tidak ditemukan.");
                return 'not_found';
            }

            // Verifikasi kecocokan nominal secara numerik (mis. "26400.00" vs 26400)
            $totalBayar = (int) $pesanan['total_bayar'];
            if ($grossAmountRaw === '' || !is_numeric($grossAmountRaw) || abs((float) $grossAmountRaw - (float) $totalBayar) >= 0.01) {
                $db->rollBack();
                error_log(sprintf(
                    '[QuickBrew Pembayaran] Nominal gross_amount (%s) tidak cocok dengan total_bayar (%d) pada midtrans_order_id=%s.',
                    $grossAmountRaw !== '' ? $grossAmountRaw : '-',
                    $totalBayar,
                    $orderId
                ));
                return 'amount_mismatch';
            }

            $pemesananId     = (int) $pesanan['pemesanan_id'];
            $statusPemesanan = (string) $pesanan['status_pemesanan'];

            // Idempotent: abaikan jika pesanan sudah pernah dilunasi atau dibatalkan
            if ($statusPemesanan !== 'menunggu_pembayaran') {
                $db->commit();
                return 'already_processed';
            }

            $isPaid = $transactionStatus === 'settlement'
                   || ($transactionStatus === 'capture' && $fraudStatus === 'accept');
            $isFail = in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true);

            if ($isPaid) {
                $this->tandaiLunas($pemesananId, $paymentType ?? 'qris');
                $db->commit();
                return 'paid';
            }

            if ($isFail) {
                $db->prepare("UPDATE pemesanan SET status_pemesanan = 'batal' WHERE id = ?")
                   ->execute([$pemesananId]);

                $db->prepare("
                    UPDATE pembayaran
                       SET status_pembayaran = ?,
                           metode_pembayaran = COALESCE(?, metode_pembayaran)
                     WHERE pemesanan_id = ?
                ")->execute([$transactionStatus, $paymentType, $pemesananId]);

                $db->commit();
                return 'failed';
            }

            if ($transactionStatus === 'pending') {
                if ($paymentType !== null) {
                    $db->prepare("
                        UPDATE pembayaran
                           SET metode_pembayaran = ?
                         WHERE pemesanan_id = ?
                    ")->execute([$paymentType, $pemesananId]);
                }
                $db->commit();
                return 'pending';
            }

            // Status lain (mis. capture dengan fraud_status = challenge)
            error_log("[QuickBrew Pembayaran] Status transaksi belum final (status={$transactionStatus}, fraud={$fraudStatus}) untuk midtrans_order_id={$orderId}.");
            $db->commit();
            return 'ignored';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    //  Helper internal
    // ------------------------------------------------------------------

    /**
     * Validasi pesanan, susun order_id kustom TRX-{id}-{timestamp}, dan bentuk item_details
     * termasuk item Pajak tersendiri sehingga SUM(price * quantity) == gross_amount.
     *
     * @return array{0: string, 1: array<int, array{id:string, price:int, quantity:int, name:string}>, 2: int}
     */
    private function siapkanTransaksi(array $pesanan, array $detailPesanan): array
    {
        $idPesanan = (int) ($pesanan['id'] ?? 0);
        if ($idPesanan <= 0) {
            throw new InvalidArgumentException('ID pesanan tidak valid.');
        }
        if (!$detailPesanan) {
            throw new InvalidArgumentException('Detail pesanan kosong.');
        }

        $orderId = sprintf('TRX-%d-%d', $idPesanan, time());

        $items = [];
        foreach ($detailPesanan as $i => $d) {
            $harga  = (int) ($d['harga'] ?? -1);
            $jumlah = (int) ($d['jumlah'] ?? 0);
            if ($harga < 0 || $jumlah <= 0) {
                throw new InvalidArgumentException('Harga/jumlah pada detail pesanan ke-' . ($i + 1) . ' tidak valid.');
            }

            $nama = (string) ($d['nama_menu'] ?? ('Menu #' . ($d['menu_id'] ?? '?')));
            if (!empty($d['catatan'])) {
                $nama .= ' (' . $d['catatan'] . ')';
            }

            $items[] = [
                'id'       => self::potong('MENU-' . ($d['menu_id'] ?? 0) . '-' . ($i + 1), self::MAX_ITEM_ID),
                'price'    => $harga,
                'quantity' => $jumlah,
                'name'     => self::potong($nama, self::MAX_ITEM_NAME),
            ];
        }

        // Pajak 10% dikirim sebagai item tersendiri agar SUM(item_details) == gross_amount
        $pajak = (int) ($pesanan['pajak'] ?? 0);
        if ($pajak > 0) {
            $items[] = [
                'id'       => 'PAJAK',
                'price'    => $pajak,
                'quantity' => 1,
                'name'     => 'Pajak',
            ];
        }

        $totalItem   = array_sum(array_map(static fn (array $it): int => $it['price'] * $it['quantity'], $items));
        $grossAmount = array_key_exists('total_harga', $pesanan) ? (int) $pesanan['total_harga'] : $totalItem;

        if ($totalItem !== $grossAmount) {
            throw new InvalidArgumentException(sprintf(
                'Total item (%d) tidak sama dengan total_harga pesanan (%d).',
                $totalItem,
                $grossAmount
            ));
        }
        if ($grossAmount <= 0) {
            throw new InvalidArgumentException('Total pembayaran harus lebih dari 0.');
        }

        return [$orderId, $items, $grossAmount];
    }

    /** Base URL Snap API (untuk method @deprecated buatTransaksi). */
    private function snapUrl(): string
    {
        return $this->production
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    /** Base URL Core API v2 (charge QRIS & cek status transaksi). */
    private function apiUrl(): string
    {
        return $this->production
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    /**
     * Kirim request HTTP ke Midtrans memakai transport terinjeksi (pengujian) atau cURL native.
     *
     * @return array{httpCode: int, data: array}
     * @throws RuntimeException bila koneksi gagal, respons bukan JSON, atau HTTP bukan 2xx (kecuali 404 saat $allowNotFound).
     */
    private function request(string $method, string $url, ?array $body = null, bool $allowNotFound = false): array
    {
        if ($this->httpTransport !== null) {
            $res      = ($this->httpTransport)($method, $url, $body);
            $httpCode = (int) ($res['httpCode'] ?? 200);
            $errno    = (int) ($res['errno'] ?? 0);
            $error    = (string) ($res['error'] ?? '');
            $raw      = array_key_exists('body', $res) && is_array($res['body'])
                ? json_encode($res['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : ($res['raw'] ?? '');
        } else {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Tidak dapat menginisialisasi cURL.');
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
                CURLOPT_USERPWD        => $this->serverKey . ':',
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }

            $raw      = curl_exec($ch);
            $errno    = curl_errno($ch);
            $error    = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        }

        if ($raw === false || $errno !== 0) {
            throw new RuntimeException(sprintf('cURL error #%d: %s', $errno, $error !== '' ? $error : 'tidak diketahui'));
        }

        $data = json_decode((string) $raw, true);

        if ($allowNotFound && $httpCode === 404) {
            return ['httpCode' => 404, 'data' => is_array($data) ? $data : ['status_code' => '404']];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $pesan = is_array($data)
                ? implode('; ', (array) ($data['error_messages'] ?? $data['validation_messages'] ?? $data['status_message'] ?? 'Permintaan ditolak'))
                : substr((string) $raw, 0, 200);
            throw new RuntimeException(sprintf('HTTP %d dari Midtrans: %s', $httpCode, $pesan));
        }

        if (!is_array($data)) {
            throw new RuntimeException('Respons Midtrans bukan JSON yang valid.');
        }

        return ['httpCode' => $httpCode, 'data' => $data];
    }

    /** Potong teks sesuai batas panjang field Midtrans (aman tanpa mewajibkan ekstensi mbstring). */
    private static function potong(string $text, int $max): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max
                ? mb_substr($text, 0, $max - 3, 'UTF-8') . '...'
                : $text;
        }
        $len = preg_match_all('/./us', $text);
        $len = $len === false ? strlen($text) : $len;
        if ($len <= $max) {
            return $text;
        }
        $cut = preg_match('/^.{0,' . ($max - 3) . '}/us', $text, $m) ? $m[0] : substr($text, 0, $max - 3);
        return $cut . '...';
    }
}
