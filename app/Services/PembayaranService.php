<?php
/**
 * PembayaranService — semua logika integrasi Midtrans (Snap + Core API status) untuk QuickBrew.
 *
 * Memakai cURL native PHP (tanpa Composer). Kredensial dibaca lewat env() dari config/config.php:
 *   MIDTRANS_SERVER_KEY  server key Sandbox / Production
 *   MIDTRANS_PROD        'true' -> Production, selain itu -> Sandbox
 *
 * Contoh pemakaian di controller:
 *   $service = new PembayaranService();
 *   $hasil   = $service->buatTransaksi($pesanan, $detailPesanan);
 *   // simpan $hasil['snap_token'] & $hasil['midtrans_order_id'] ke tabel pembayaran
 *
 *   $status  = $service->sinkron($midtransOrderId); // cadangan bila webhook gagal
 */
final class PembayaranService
{
    /** Masa berlaku transaksi (menit). */
    private const EXPIRY_MINUTES = 15;

    /** Batas waktu request ke Midtrans (detik) agar halaman tidak hang. */
    private const TIMEOUT        = 20;
    private const CONNECT_TIMEOUT = 10;

    /** Batas panjang field dari Midtrans. */
    private const MAX_ITEM_NAME = 50;
    private const MAX_ITEM_ID   = 50;

    private string $serverKey;
    private bool   $production;

    public function __construct()
    {
        $this->serverKey  = (string) env('MIDTRANS_SERVER_KEY', '');
        $this->production = env('MIDTRANS_PROD', 'false') === 'true';

        if ($this->serverKey === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diisi di config/.env.');
        }
    }

    // ------------------------------------------------------------------
    //  1. Membuat transaksi Snap
    // ------------------------------------------------------------------

    /**
     * Buat transaksi Snap Midtrans untuk satu pesanan.
     *
     * @param array $pesanan       Baris tabel pemesanan. Wajib: id, total_harga.
     *                             Opsional: nama_pelanggan, email_pelanggan,
     *                             pajak (int, ditambahkan sebagai item tersendiri).
     * @param array $detailPesanan Baris detail_pemesanan (+ nama menu). Per baris wajib:
     *                             menu_id, harga (harga satuan SUDAH termasuk ukuran & add-on), jumlah.
     *                             Opsional: nama_menu, catatan.
     * @return array{snap_token: string, midtrans_order_id: string, redirect_url: ?string}
     * @throws InvalidArgumentException bila data pesanan tidak valid / total tidak cocok.
     * @throws RuntimeException         bila request ke Midtrans gagal.
     */
    public function buatTransaksi(array $pesanan, array $detailPesanan): array
    {
        $idPesanan = (int) ($pesanan['id'] ?? 0);
        if ($idPesanan <= 0) {
            throw new InvalidArgumentException('ID pesanan tidak valid.');
        }
        if (!$detailPesanan) {
            throw new InvalidArgumentException('Detail pesanan kosong.');
        }

        // order_id kustom: TRX-{id_pesanan}-{timestamp}.
        // Timestamp membuat order_id selalu unik, jadi pesanan yang sama bisa dibuatkan
        // transaksi baru (mis. setelah transaksi lama expire) tanpa ditolak Midtrans.
        $orderId = sprintf('TRX-%d-%d', $idPesanan, time());

        // --- Susun item_details ---------------------------------------
        // Midtrans mewajibkan: SUM(price * quantity) dari item_details == gross_amount.
        // Karena itu harga per item diambil dari detail_pemesanan.harga (snapshot harga satuan
        // yang sudah termasuk tambahan ukuran & add-on), bukan dari harga dasar menu.
        $items = [];
        foreach ($detailPesanan as $i => $d) {
            $harga  = (int) ($d['harga'] ?? -1);
            $jumlah = (int) ($d['jumlah'] ?? 0);
            if ($harga < 0 || $jumlah <= 0) {
                throw new InvalidArgumentException("Harga/jumlah pada detail pesanan ke-" . ($i + 1) . " tidak valid.");
            }

            $nama = (string) ($d['nama_menu'] ?? ('Menu #' . ($d['menu_id'] ?? '?')));
            if (!empty($d['catatan'])) {
                $nama .= ' (' . $d['catatan'] . ')'; // mis. "Americano (L, Ice, Extra Shot)"
            }

            $items[] = [
                // id dibuat unik per baris karena 1 menu bisa muncul beberapa kali dgn catatan berbeda
                'id'       => self::potong('MENU-' . ($d['menu_id'] ?? 0) . '-' . ($i + 1), self::MAX_ITEM_ID),
                'price'    => $harga,
                'quantity' => $jumlah,
                'name'     => self::potong($nama, self::MAX_ITEM_NAME),
            ];
        }

        // Pajak/biaya lain (jika total_harga pesanan memasukkannya) dikirim sebagai item tersendiri
        // agar penjumlahan item tetap sama dengan gross_amount.
        $pajak = (int) ($pesanan['pajak'] ?? 0);
        if ($pajak > 0) {
            $items[] = ['id' => 'PAJAK', 'price' => $pajak, 'quantity' => 1, 'name' => 'Pajak'];
        }

        $totalItem   = array_sum(array_map(static fn (array $it): int => $it['price'] * $it['quantity'], $items));
        $grossAmount = (int) ($pesanan['total_harga'] ?? $totalItem);

        // Validasi di sisi kita dulu supaya error-nya jelas (Midtrans hanya membalas 400).
        if ($totalItem !== $grossAmount) {
            throw new InvalidArgumentException(sprintf(
                'Total item (%d) tidak sama dengan total_harga pesanan (%d).', $totalItem, $grossAmount
            ));
        }
        if ($grossAmount <= 0) {
            throw new InvalidArgumentException('Total pembayaran harus lebih dari 0.');
        }

        // --- Payload Snap ---------------------------------------------
        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'item_details'     => $items,
            'customer_details' => array_filter([
                'first_name' => self::potong((string) ($pesanan['nama_pelanggan'] ?? ''), 50),
                'email'      => (string) ($pesanan['email_pelanggan'] ?? ''),
            ]),
            // Transaksi otomatis kedaluwarsa 15 menit setelah dibuat
            // (Midtrans lalu mengirim status 'expire').
            'expiry' => [
                'unit'     => 'minutes',
                'duration' => self::EXPIRY_MINUTES,
            ],
        ];

        try {
            $response = $this->request('POST', $this->snapUrl() . '/transactions', $payload);
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
    //  2. Sinkron status (cadangan bila webhook gagal)
    // ------------------------------------------------------------------

    /**
     * Ambil status transaksi langsung dari Midtrans: GET /v2/{order_id}/status,
     * lalu terapkan ke tabel pemesanan & pembayaran (logika yang sama dengan webhook).
     *
     * @return array Respons status Midtrans (transaction_status, payment_type, fraud_status, dst.).
     * @throws RuntimeException bila request gagal atau transaksi tidak ditemukan.
     */
    public function sinkron(string $midtrans_order_id): array
    {
        $midtrans_order_id = trim($midtrans_order_id);
        if ($midtrans_order_id === '') {
            throw new InvalidArgumentException('midtrans_order_id kosong.');
        }

        try {
            $status = $this->request('GET', $this->apiUrl() . '/' . rawurlencode($midtrans_order_id) . '/status');
        } catch (RuntimeException $e) {
            throw new RuntimeException('Gagal mengecek status Midtrans: ' . $e->getMessage(), 0, $e);
        }

        // Core API kadang membalas HTTP 200 tetapi status_code di body berisi "404"
        // (transaksi belum pernah dibayar / order_id tidak dikenal).
        $code = (string) ($status['status_code'] ?? '');
        if ($code !== '' && $code[0] !== '2') {
            throw new RuntimeException(sprintf(
                'Status Midtrans %s: %s', $code, $status['status_message'] ?? 'tidak diketahui'
            ));
        }

        $this->terapkanStatus($status);
        return $status;
    }

    // ------------------------------------------------------------------
    //  3. Menerapkan status ke database (dipakai sinkron() & webhook)
    // ------------------------------------------------------------------

    /**
     * Update pemesanan, pembayaran, dan stok menu berdasarkan data status Midtrans
     * (body notifikasi webhook atau respons GET /status).
     *
     *   settlement / capture+accept       -> pembayaran 'paid',  pemesanan 'dibayar', stok dikurangi
     *   deny / cancel / expire / failure  -> pembayaran = status, pemesanan 'batal'
     *   pending / lainnya                 -> tidak ada perubahan
     *
     * Hanya diproses bila pemesanan masih 'menunggu_pembayaran', sehingga aman dipanggil berulang
     * (webhook + sinkron untuk transaksi yang sama tidak mengurangi stok dua kali).
     */
    public function terapkanStatus(array $data): void
    {
        $orderId = (string) ($data['order_id'] ?? '');
        $pid     = $this->cariPemesananId($orderId);
        if (!$pid) return;

        $ts   = (string) ($data['transaction_status'] ?? '');
        $paid = $ts === 'settlement' || ($ts === 'capture' && ($data['fraud_status'] ?? 'accept') === 'accept');
        $fail = in_array($ts, ['deny', 'cancel', 'expire', 'failure'], true);
        if (!$paid && !$fail) return;

        $db = db();
        $db->beginTransaction();
        try {
            // Kunci baris pemesanan agar webhook & sinkron yang datang bersamaan tidak bentrok
            $s = $db->prepare("SELECT status_pemesanan FROM pemesanan WHERE id = ? FOR UPDATE");
            $s->execute([$pid]);

            if ($s->fetchColumn() === 'menunggu_pembayaran') {
                if ($paid) {
                    $db->prepare("UPDATE pemesanan SET status_pemesanan = 'dibayar' WHERE id = ?")
                       ->execute([$pid]);

                    // waktu_pembayaran wajib terisi bila status 'paid' (CHECK constraint)
                    $db->prepare("UPDATE pembayaran
                                     SET status_pembayaran = 'paid',
                                         metode_pembayaran = ?,
                                         waktu_pembayaran  = CURRENT_TIMESTAMP,
                                         midtrans_order_id = COALESCE(midtrans_order_id, ?)
                                   WHERE pemesanan_id = ?")
                       ->execute([$data['payment_type'] ?? null, $orderId, $pid]);

                    // Kurangi stok per menu (1 menu bisa muncul di beberapa baris detail)
                    $db->prepare("UPDATE menu
                                     SET stok = GREATEST(menu.stok - x.qty, 0)
                                    FROM (SELECT menu_id, SUM(jumlah) AS qty
                                            FROM detail_pemesanan
                                           WHERE pemesanan_id = ?
                                           GROUP BY menu_id) x
                                   WHERE x.menu_id = menu.id")
                       ->execute([$pid]);
                } else {
                    $db->prepare("UPDATE pemesanan SET status_pemesanan = 'batal' WHERE id = ?")
                       ->execute([$pid]);

                    $db->prepare("UPDATE pembayaran
                                     SET status_pembayaran = ?,
                                         metode_pembayaran = COALESCE(?, metode_pembayaran),
                                         midtrans_order_id = COALESCE(midtrans_order_id, ?)
                                   WHERE pemesanan_id = ?")
                       ->execute([$ts, $data['payment_type'] ?? null, $orderId, $pid]);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Verifikasi signature_key notifikasi webhook agar tidak bisa dipalsukan:
     * SHA512(order_id + status_code + gross_amount + server_key).
     * Wajib dipanggil di endpoint webhook sebelum terapkanStatus().
     */
    public function verifikasiSignature(array $notif): bool
    {
        $expected = hash('sha512',
            ($notif['order_id'] ?? '') . ($notif['status_code'] ?? '') . ($notif['gross_amount'] ?? '') . $this->serverKey
        );
        return hash_equals($expected, (string) ($notif['signature_key'] ?? ''));
    }

    // ------------------------------------------------------------------
    //  Helper internal
    // ------------------------------------------------------------------

    /** Base URL Snap API (membuat transaksi). */
    private function snapUrl(): string
    {
        return $this->production
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    /** Base URL Core API v2 (cek status). */
    private function apiUrl(): string
    {
        return $this->production
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    /**
     * Kirim request HTTP ke Midtrans memakai cURL native.
     *
     * @throws RuntimeException bila cURL gagal, respons bukan 2xx, atau body bukan JSON.
     */
    private function request(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Tidak dapat menginisialisasi cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,              // kembalikan body sebagai string, jangan di-echo
            CURLOPT_CUSTOMREQUEST  => $method,           // GET / POST
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT, // batas waktu membuka koneksi
            CURLOPT_TIMEOUT        => self::TIMEOUT,     // batas waktu total request (anti hang)
            // Autentikasi Midtrans: HTTP Basic, username = server key, password kosong.
            // cURL otomatis membentuk header "Authorization: Basic base64(server_key:)".
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $this->serverKey . ':',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,              // jangan matikan verifikasi SSL
        ]);

        if ($body !== null) {
            // Payload dikirim sebagai JSON (Midtrans tidak menerima form-urlencoded)
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        $raw      = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        // Gagal di level jaringan (DNS, timeout, SSL, dll.)
        if ($raw === false || $errno !== 0) {
            throw new RuntimeException(sprintf('cURL error #%d: %s', $errno, $error ?: 'tidak diketahui'));
        }

        $data = json_decode((string) $raw, true);

        // Respons HTTP bukan 2xx -> ambil pesan error dari Midtrans bila ada
        if ($httpCode < 200 || $httpCode >= 300) {
            $pesan = is_array($data)
                ? implode('; ', (array) ($data['error_messages'] ?? $data['status_message'] ?? 'Permintaan ditolak'))
                : substr((string) $raw, 0, 200);
            throw new RuntimeException(sprintf('HTTP %d dari Midtrans: %s', $httpCode, $pesan));
        }

        if (!is_array($data)) {
            throw new RuntimeException('Respons Midtrans bukan JSON yang valid.');
        }

        return $data;
    }

    /**
     * Cari pemesanan.id dari order_id Midtrans.
     * Prioritas kolom pembayaran.midtrans_order_id; fallback mengurai format TRX-{id}-{timestamp}.
     */
    private function cariPemesananId(string $orderId): int
    {
        if ($orderId === '') return 0;

        $s = db()->prepare("SELECT pemesanan_id FROM pembayaran WHERE midtrans_order_id = ?");
        $s->execute([$orderId]);
        $pid = (int) $s->fetchColumn();
        if ($pid) return $pid;

        if (!preg_match('/^TRX-(\d+)-\d+$/', $orderId, $m)) return 0;
        $s = db()->prepare("SELECT id FROM pemesanan WHERE id = ?");
        $s->execute([(int) $m[1]]);
        return (int) $s->fetchColumn();
    }

    /** Potong teks sesuai batas panjang field Midtrans (aman untuk UTF-8). */
    private static function potong(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 3) . '...' : $text;
    }
}

