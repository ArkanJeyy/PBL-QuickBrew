<?php
require_once APP_ROOT . '/app/Services/PembayaranService.php';

/**
 * Model pesanan pelanggan (tabel: pemesanan, detail_pemesanan, pembayaran).
 *
 * - price(): menghitung ulang harga di server dari isi keranjang yang dikirim browser
 *   (harga menu & stok dari database, tambahan harga dari customization-options.php).
 * - create(): menyimpan pesanan + detail + pembayaran (status pending) dalam satu transaksi.
 * - simpanQris() / tandaiGagalQris(): memperbarui hasil pembuatan QRIS Midtrans Core API.
 * - find(): data untuk halaman Pembayaran (nomor pesanan "#A0012", batas bayar, dan data QRIS).
 *
 * Stok TIDAK dikurangi saat pesanan dibuat; pengurangan stok dan perubahan status pesanan menjadi
 * 'diproses' dilakukan secara atomik di PembayaranService::tandaiLunas() saat pembayaran lunas.
 *
 * Catatan umum dari form Checkout disimpan langsung di kolom pemesanan.catatan, sedangkan
 * detail_pemesanan.catatan khusus menyimpan pilihan kustomisasi tiap item minuman.
 */
final class PemesananModel
{
    /** Panjang maksimal catatan umum dari form Checkout (karakter). */
    public const NOTE_MAX = 150;

    /** Pajak 10% (sama dengan TAX_RATE di cart.js). total_harga yang disimpan sudah termasuk pajak. */
    public const TAX_RATE = 0.1;

    /** Metode pembayaran yang tersedia di Checkout => label tampilan. */
    public const METODE = [
        'qris'  => 'QRIS',
        'tunai' => 'Tunai (bayar di kasir)',
    ];

    /** Label status pesanan untuk pelanggan. */
    public const STATUS_LABEL = [
        'menunggu_pembayaran' => 'Menunggu pembayaran',
        'dibayar'             => 'Sudah dibayar',
        'diproses'            => 'Sedang dibuat',
        'siap'                => 'Siap diambil',
        'selesai'             => 'Selesai',
        'batal'               => 'Dibatalkan',
    ];

    /** Label status pembayaran (tabel pembayaran) untuk badge di halaman Pembayaran. */
    public const PAYMENT_LABEL = [
        'pending' => 'Belum Dibayar',
        'paid'    => 'Sudah Dibayar',
        'deny'    => 'Ditolak',
        'cancel'  => 'Dibatalkan',
        'expire'  => 'Kedaluwarsa',
        'failure' => 'Gagal',
    ];

    /** Batas waktu pembayaran QRIS (menit), mengacu pada satu sumber di PembayaranService. */
    public const PAYMENT_MINUTES = PembayaranService::EXPIRY_MINUTES;

    /** Estimasi pesanan siap setelah dibayar (teks tetap di halaman Status Pesanan). */
    public const ESTIMATE_TEXT = '10 - 15 menit';

    private const MAX_LINES    = 50;
    private const MAX_QUANTITY = 99;

    /** Nomor pesanan untuk pelanggan & kasir: id 12 -> "#A0012". */
    public static function displayNumber(int $id): string
    {
        return '#A' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    /** Jam batas bayar QRIS ("18.20") dari waktu_pemesanan + PAYMENT_MINUTES. */
    public static function paymentDeadline(string $orderedAt): string
    {
        $time = strtotime($orderedAt);
        return $time ? date('H.i', $time + self::PAYMENT_MINUTES * 60) : '';
    }

    /**
     * Pecah detail_pemesanan.catatan minuman ("L, Ice, Normal Sugar, Normal Ice, Extra Shot") menjadi
     * ukuran, pilihan, dan add-on (dengan harga) untuk ditampilkan. Teks lain dikembalikan apa adanya.
     * @return array{size:string, variants:string[], addons:array, raw:?string}
     */
    private static function parseOptions(?string $catatan): array
    {
        $result = ['size' => '', 'variants' => [], 'addons' => [], 'raw' => null];
        if ($catatan === null || $catatan === '') return $result;

        $opts  = customization_options();
        $parts = array_map('trim', explode(',', $catatan));
        if (count($parts) < 4 || !in_array($parts[0], $opts['size']['options'], true)) {
            $result['raw'] = $catatan; // bukan format pilihan minuman
            return $result;
        }

        $addonsByLabel = [];
        foreach ($opts['addons']['items'] as $addon) $addonsByLabel[$addon['label']] = $addon;

        $result['size']     = $parts[0];
        $result['variants'] = array_slice($parts, 1, 3); // tipe, gula, es
        foreach (array_slice($parts, 4) as $label) {
            $result['addons'][] = ['label' => $label, 'price' => (int) ($addonsByLabel[$label]['price'] ?? 0)];
        }
        return $result;
    }

    /**
     * Hitung ulang isi keranjang di server.
     *
     * @param  mixed $rawItems Hasil json_decode field "items" dari form Checkout:
     *                         [{id, quantity, size, type, sugar, ice, addons:[id,...]}, ...]
     * @return array{lines:array, subtotal:int, tax:int, total:int, error:?string}
     */
    public static function price($rawItems): array
    {
        $fail = static fn (string $message): array => ['lines' => [], 'subtotal' => 0, 'tax' => 0, 'total' => 0, 'error' => $message];

        if (!is_array($rawItems) || !$rawItems) return $fail('Keranjang kosong. Tambahkan menu terlebih dahulu.');
        if (count($rawItems) > self::MAX_LINES) return $fail('Jumlah item di keranjang terlalu banyak.');

        $menus = MenuModel::findByIds(array_map(static fn ($i) => is_array($i) ? ($i['id'] ?? 0) : 0, $rawItems));
        $opts  = customization_options();
        $addonsById = [];
        foreach ($opts['addons']['items'] as $addon) $addonsById[$addon['id']] = $addon;

        $lines      = [];
        $qtyPerMenu = [];
        foreach ($rawItems as $raw) {
            if (!is_array($raw)) return $fail('Data keranjang tidak valid.');
            $menuId   = (int) ($raw['id'] ?? 0);
            $quantity = filter_var($raw['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_QUANTITY]]);
            $menu     = $menus[$menuId] ?? null;

            if (!$menu || !$menu['active']) return $fail('Ada menu di keranjang yang sudah tidak tersedia. Hapus dari keranjang lalu coba lagi.');
            if ($quantity === false) return $fail('Jumlah pesanan ' . $menu['name'] . ' tidak valid.');

            $unitPrice = $menu['price'];
            $notes     = [];
            if ($menu['customizable']) {
                // Pilihan minuman harus ada di customization-options.php; tambahan harga dihitung di sini, bukan dari browser
                foreach (['size', 'type', 'sugar', 'ice'] as $key) {
                    $value = (string) ($raw[$key] ?? '');
                    if (!in_array($value, $opts[$key]['options'], true)) {
                        return $fail('Pilihan ' . strtolower($opts[$key]['label']) . ' untuk ' . $menu['name'] . ' tidak valid.');
                    }
                    $unitPrice += (int) ($opts[$key]['prices'][$value] ?? 0);
                    $notes[]    = $value;
                }
                $addonIds = is_array($raw['addons'] ?? null) ? array_unique(array_map('strval', $raw['addons'])) : [];
                foreach ($addonIds as $addonId) {
                    if (!isset($addonsById[$addonId])) return $fail('Add-on untuk ' . $menu['name'] . ' tidak valid.');
                    $unitPrice += (int) $addonsById[$addonId]['price'];
                    $notes[]    = $addonsById[$addonId]['label'];
                }
            }

            $qtyPerMenu[$menuId] = ($qtyPerMenu[$menuId] ?? 0) + $quantity;
            if ($qtyPerMenu[$menuId] > $menu['stock']) {
                return $fail($menu['stock'] > 0
                    ? 'Stok ' . $menu['name'] . ' tinggal ' . $menu['stock'] . '. Kurangi jumlahnya di keranjang.'
                    : $menu['name'] . ' sedang habis. Hapus dari keranjang lalu coba lagi.');
            }

            $lines[] = [
                'menu_id'   => $menuId,
                'nama_menu' => $menu['name'],
                'harga'     => $unitPrice,
                'jumlah'    => $quantity,
                'subtotal'  => $unitPrice * $quantity,
                'catatan'   => $notes ? text_cut(implode(', ', $notes), 255) : null,
            ];
        }

        $subtotal = array_sum(array_column($lines, 'subtotal'));
        $tax      = (int) round($subtotal * self::TAX_RATE);
        return ['lines' => $lines, 'subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax, 'error' => null];
    }

    /**
     * Simpan pesanan baru. $order: meja_id, nama, email, catatan (?string), metode, total, lines (hasil price()).
     * @return int id pemesanan
     */
    public static function create(array $order): int
    {
        $lines   = $order['lines'];
        $catatan = isset($order['catatan']) && $order['catatan'] !== '' ? (string) $order['catatan'] : null;

        $db = db();
        $db->beginTransaction();
        try {
            $s = $db->prepare(
                "INSERT INTO pemesanan (meja_id, nama_pelanggan, email_pelanggan, catatan, total_harga)
                 VALUES (?, ?, ?, ?, ?) RETURNING id"
            );
            $s->execute([$order['meja_id'], $order['nama'], $order['email'], $catatan, $order['total']]);
            $id = (int) $s->fetchColumn();

            $detail = $db->prepare(
                "INSERT INTO detail_pemesanan (menu_id, pemesanan_id, harga, jumlah, subtotal, catatan)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($lines as $line) {
                $detail->execute([$line['menu_id'], $id, $line['harga'], $line['jumlah'], $line['subtotal'], $line['catatan']]);
            }

            $db->prepare(
                "INSERT INTO pembayaran (pemesanan_id, metode_pembayaran, total_bayar) VALUES (?, ?, ?)"
            )->execute([$id, $order['metode'], $order['total']]);

            $db->commit();
            return $id;
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $ex;
        }
    }

    /**
     * Simpan midtrans_order_id dan JSON qris_data setelah panggilan Core API QRIS berhasil.
     */
    public static function simpanQris(int $pemesananId, string $midtransOrderId, string $qrisDataJson): void
    {
        db()->prepare(
            "UPDATE pembayaran
                SET midtrans_order_id = ?,
                    qris_data         = ?,
                    metode_pembayaran = 'qris'
              WHERE pemesanan_id = ?"
        )->execute([$midtransOrderId, $qrisDataJson, $pemesananId]);
    }

    /**
     * Tandai pesanan 'batal' dan pembayaran 'failure' bila pembuatan QRIS ke Midtrans gagal.
     */
    public static function tandaiGagalQris(int $pemesananId): void
    {
        $db = db();
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE pemesanan SET status_pemesanan = 'batal' WHERE id = ?")
               ->execute([$pemesananId]);
            $db->prepare("UPDATE pembayaran SET status_pembayaran = 'failure' WHERE pemesanan_id = ?")
               ->execute([$pemesananId]);
            $db->commit();
        } catch (Throwable $ex) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $ex;
        }
    }

    /** Pesanan beserta meja, pembayaran (termasuk qris_data), dan detailnya; null bila tidak ada. */
    public static function find(int $id): ?array
    {
        $s = db()->prepare(
            "SELECT p.id, p.nama_pelanggan, p.email_pelanggan, p.catatan, p.total_harga,
                    p.status_pemesanan, p.waktu_pemesanan, p.diperbarui_pada, m.no_meja,
                    b.metode_pembayaran, b.status_pembayaran, b.midtrans_order_id, b.qris_data,
                    b.waktu_pembayaran
               FROM pemesanan p
               JOIN meja m ON m.id = p.meja_id
               LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
              WHERE p.id = ?"
        );
        $s->execute([$id]);
        $order = $s->fetch();
        if (!$order) return null;

        $s = db()->prepare(
            "SELECT d.jumlah, d.harga, d.subtotal, d.catatan, mn.nama_menu, mn.gambar
               FROM detail_pemesanan d
               JOIN menu mn ON mn.id = d.menu_id
              WHERE d.pemesanan_id = ?
              ORDER BY d.id"
        );
        $s->execute([$id]);
        $order['items'] = array_map(static fn (array $row): array => $row + [
            'image' => MenuModel::image($row['gambar'], $row['nama_menu']),
        ], $s->fetchAll());

        // Pilihan minuman untuk ringkasan: ukuran di nama, tipe | gula | es, dan add-on
        foreach ($order['items'] as &$item) {
            $item['options'] = self::parseOptions($item['catatan']);
        }
        unset($item);

        $subtotal          = array_sum(array_map('intval', array_column($order['items'], 'subtotal')));
        $order['subtotal'] = $subtotal;
        $order['tax']      = (int) $order['total_harga'] - $subtotal;
        $order['number']   = self::displayNumber((int) $order['id']);
        $order['deadline'] = self::paymentDeadline((string) $order['waktu_pemesanan']);
        return $order;
    }

    /** Sudah lewat tahap pembayaran? (lunas atau status pesanan sudah maju) */
    public static function isPaid(array $order): bool
    {
        return ($order['status_pembayaran'] ?? '') === 'paid'
            || in_array($order['status_pemesanan'], ['dibayar', 'diproses', 'siap', 'selesai'], true);
    }

    /**
     * Data halaman Status Pesanan: judul, teks, keadaan halaman, dan 4 langkah stepper.
     *
     * Database hanya menyimpan jam pesan (waktu_pemesanan), jam bayar (pembayaran.waktu_pembayaran),
     * dan jam perubahan status terakhir (diperbarui_pada, diisi trigger). Jadi jam "Diproses"/"Selesai"
     * hanya tampil pada langkah yang menjadi perubahan TERAKHIR; langkah lain tanpa jam.
     * Status 'siap' (tidak ada di desain 4 langkah) dianggap bagian dari "Diproses".
     *
     * @return array{state:string, title:string, desc:string, steps:array}
     *         state: unpaid | progress | done | cancel
     *         steps[]: label, icon, status (done|current|todo), time
     */
    public static function statusView(array $order): array
    {
        $time = static fn ($ts): string => ($ts && ($t = strtotime((string) $ts))) ? date('H.i', $t) : '';
        $status  = (string) $order['status_pemesanan'];
        $paid    = self::isPaid($order);
        $updated = $time($order['diperbarui_pada'] ?? null);
        $meja    = (string) $order['no_meja'];

        $steps = [
            ['label' => 'Pesanan Diterima', 'icon' => 'order', 'status' => 'done', 'time' => $time($order['waktu_pemesanan'])],
            ['label' => $paid ? 'Pembayaran Diterima' : 'Menunggu Pembayaran', 'icon' => 'pay',
             'status' => $paid ? 'done' : 'current', 'time' => $paid ? $time($order['waktu_pembayaran'] ?? null) : ''],
            ['label' => 'Diproses', 'icon' => 'process', 'status' => 'todo', 'time' => ''],
            ['label' => 'Selesai', 'icon' => 'done', 'status' => 'todo', 'time' => ''],
        ];

        switch ($status) {
            case 'batal':
                $steps[1] = ['label' => 'Dibatalkan', 'icon' => 'pay', 'status' => 'todo', 'time' => $updated];
                return ['state' => 'cancel', 'title' => 'Pesanan Dibatalkan',
                        'desc' => 'Pesanan ini dibatalkan. Silakan hubungi kasir bila ada pertanyaan.', 'steps' => $steps];
            case 'selesai':
                $steps[2]['status'] = 'done';
                $steps[3] = ['status' => 'done', 'time' => $updated] + $steps[3];
                return ['state' => 'done', 'title' => 'Selamat Menikmati',
                        'desc' => 'Pesananmu telah di antar ke Meja ' . $meja . '. Terima kasih sudah memilih ' . APP_NAME, 'steps' => $steps];
            case 'siap':
                $steps[2] = ['status' => 'done', 'time' => $updated] + $steps[2];
                $steps[3]['status'] = 'current';
                return ['state' => 'progress', 'title' => 'Pesanan Siap',
                        'desc' => 'Pesanan siap diantar ke Meja ' . $meja . '.', 'steps' => $steps];
            case 'diproses':
                $steps[2] = ['status' => 'current', 'time' => $updated] + $steps[2];
                return ['state' => 'progress', 'title' => 'Pesanan Diproses',
                        'desc' => 'Pesananmu sedang dibuat oleh barista.', 'steps' => $steps];
        }

        if ($paid) { // 'dibayar' (atau pembayaran sudah 'paid')
            $steps[2]['status'] = 'current';
            return ['state' => 'progress', 'title' => 'Pembayaran Diterima',
                    'desc' => 'Pesananmu akan segera diproses.', 'steps' => $steps];
        }

        $steps[1]['time'] = $updated;
        return ['state' => 'unpaid', 'title' => 'Menunggu Pembayaran',
                'desc' => 'Pesanan belum diproses. Silakan selesaikan pembayaran.', 'steps' => $steps];
    }

    // =====================================================================
    //  Fondasi Kasir: Transisi Status, Pembayaran Tunai/QRIS, & Query Kasir
    // =====================================================================

    /** Peta transisi status pesanan yang diizinkan untuk kasir. */
    public const PETA_TRANSISI = [
        'menunggu_pembayaran' => ['batal'],
        'diproses'            => ['siap'],
        'siap'                => ['selesai'],
    ];

    /**
     * Ubah status pesanan oleh kasir/staf sesuai peta transisi resmi:
     *   - diproses -> siap
     *   - siap -> selesai
     *   - menunggu_pembayaran -> batal (QRIS: batalkan di Midtrans dulu via PembayaranService::batalkan();
     *     Tunai: lokal saja, status_pembayaran = 'cancel'; pesanan yang sudah lunas tidak bisa dibatalkan).
     *
     * Mencatat audit ke error_log (id pesanan, status lama->baru, id staf; tanpa data pribadi).
     *
     * @return string 'ok' | 'tidak_ditemukan' | 'transisi_tidak_valid'
     */
    public static function ubahStatus(
        int $id,
        string $ke,
        ?int $penggunaId = null,
        ?PembayaranService $service = null
    ): string {
        if ($id <= 0) {
            return 'tidak_ditemukan';
        }

        $db = db();

        // Jika target adalah 'batal', periksa metode QRIS terlebih dahulu di luar lock DB
        // agar panggilan jaringan ke Midtrans tidak menahan transaksi database.
        if ($ke === 'batal') {
            $stmtAwal = $db->prepare(
                "SELECT p.status_pemesanan, b.metode_pembayaran, b.status_pembayaran, b.midtrans_order_id
                   FROM pemesanan p
                   LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
                  WHERE p.id = ?"
            );
            $stmtAwal->execute([$id]);
            $awal = $stmtAwal->fetch();
            if (!$awal) {
                return 'tidak_ditemukan';
            }

            if (
                (string) $awal['status_pemesanan'] !== 'menunggu_pembayaran'
                || (string) ($awal['status_pembayaran'] ?? '') === 'paid'
            ) {
                return 'transisi_tidak_valid';
            }

            $midtransOrderId = trim((string) ($awal['midtrans_order_id'] ?? ''));
            if ((string) ($awal['metode_pembayaran'] ?? '') === 'qris' && $midtransOrderId !== '') {
                try {
                    ($service ?? new PembayaranService())->batalkan($midtransOrderId);
                } catch (Throwable $e) {
                    error_log(sprintf(
                        '[QuickBrew Kasir] Gagal membatalkan QRIS di Midtrans untuk pemesanan_id=%d: %s',
                        $id,
                        $e->getMessage()
                    ));
                }
            }

            $db->beginTransaction();
            try {
                $stmtLock = $db->prepare(
                    "SELECT p.status_pemesanan, b.status_pembayaran
                       FROM pemesanan p
                       LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
                      WHERE p.id = ?
                        FOR UPDATE OF p"
                );
                $stmtLock->execute([$id]);
                $locked = $stmtLock->fetch();
                if (!$locked) {
                    $db->rollBack();
                    return 'tidak_ditemukan';
                }

                $statusSaatIni = (string) $locked['status_pemesanan'];
                $statusBayar   = (string) ($locked['status_pembayaran'] ?? '');

                // Bila saat sinkron/batalkan ternyata sudah lunas, tolak pembatalan
                if ($statusBayar === 'paid' || in_array($statusSaatIni, ['diproses', 'siap', 'selesai', 'dibayar'], true)) {
                    $db->rollBack();
                    return 'transisi_tidak_valid';
                }

                // Izinkan bila masih 'menunggu_pembayaran' atau baru saja menjadi 'batal' akibat terapkanStatus() di batalkan()
                if (!in_array($statusSaatIni, ['menunggu_pembayaran', 'batal'], true)) {
                    $db->rollBack();
                    return 'transisi_tidak_valid';
                }

                $db->prepare(
                    "UPDATE pemesanan
                        SET status_pemesanan = 'batal'
                      WHERE id = ?"
                )->execute([$id]);

                $db->prepare(
                    "UPDATE pembayaran
                        SET status_pembayaran = CASE WHEN status_pembayaran = 'pending' THEN 'cancel' ELSE status_pembayaran END,
                            pengguna_id       = COALESCE(?, pengguna_id)
                      WHERE pemesanan_id = ?"
                )->execute([$penggunaId, $id]);

                $db->commit();

                error_log(sprintf(
                    '[AUDIT STATUS] pemesanan_id=%d status=menunggu_pembayaran->batal pengguna_id=%s',
                    $id,
                    $penggunaId !== null ? (string) $penggunaId : '-'
                ));
                return 'ok';
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        }

        // Transisi selain 'batal' (diproses -> siap, siap -> selesai)
        $db->beginTransaction();
        try {
            $stmtLock = $db->prepare('SELECT status_pemesanan FROM pemesanan WHERE id = ? FOR UPDATE');
            $stmtLock->execute([$id]);
            $row = $stmtLock->fetch();
            if (!$row) {
                $db->rollBack();
                return 'tidak_ditemukan';
            }

            $statusLama = (string) $row['status_pemesanan'];
            $diizinkan  = self::PETA_TRANSISI[$statusLama] ?? [];
            if (!in_array($ke, $diizinkan, true)) {
                $db->rollBack();
                return 'transisi_tidak_valid';
            }

            $db->prepare('UPDATE pemesanan SET status_pemesanan = ? WHERE id = ?')
               ->execute([$ke, $id]);

            if ($penggunaId !== null) {
                $db->prepare('UPDATE pembayaran SET pengguna_id = COALESCE(?, pengguna_id) WHERE pemesanan_id = ?')
                   ->execute([$penggunaId, $id]);
            }

            $db->commit();

            error_log(sprintf(
                '[AUDIT STATUS] pemesanan_id=%d status=%s->%s pengguna_id=%s',
                $id,
                $statusLama,
                $ke,
                $penggunaId !== null ? (string) $penggunaId : '-'
            ));
            return 'ok';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Konfirmasi pembayaran tunai oleh kasir secara idempotent.
     * Hanya untuk pesanan bermetode 'tunai'. Memanggil PembayaranService::tandaiLunas($id, 'tunai', $kasirId)
     * sehingga stok berkurang tepat satu kali dan status berubah menjadi 'diproses'.
     *
     * @return array{ok:bool, kode:string, pesan:string}
     *         kode: 'lunas' | 'sudah_lunas' | 'tidak_ditemukan' | 'bukan_tunai' | 'status_tidak_valid'
     */
    public static function bayarTunai(int $id, int $kasirId, ?PembayaranService $service = null): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'kode' => 'tidak_ditemukan', 'pesan' => 'Pesanan tidak ditemukan.'];
        }

        $s = db()->prepare(
            "SELECT p.id, p.status_pemesanan, b.metode_pembayaran, b.status_pembayaran
               FROM pemesanan p
               LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
              WHERE p.id = ?"
        );
        $s->execute([$id]);
        $row = $s->fetch();
        if (!$row) {
            return ['ok' => false, 'kode' => 'tidak_ditemukan', 'pesan' => 'Pesanan tidak ditemukan.'];
        }

        if ((string) ($row['metode_pembayaran'] ?? '') !== 'tunai') {
            return ['ok' => false, 'kode' => 'bukan_tunai', 'pesan' => 'Pesanan ini tidak menggunakan metode pembayaran tunai.'];
        }

        $statusPesanan = (string) $row['status_pemesanan'];
        $statusBayar   = (string) ($row['status_pembayaran'] ?? '');

        if ($statusBayar === 'paid' || in_array($statusPesanan, ['diproses', 'siap', 'selesai', 'dibayar'], true)) {
            return ['ok' => true, 'kode' => 'sudah_lunas', 'pesan' => 'Pembayaran tunai sudah dikonfirmasi sebelumnya.'];
        }

        if ($statusPesanan !== 'menunggu_pembayaran') {
            return ['ok' => false, 'kode' => 'status_tidak_valid', 'pesan' => 'Pesanan tidak dalam status menunggu pembayaran.'];
        }

        $berhasil = ($service ?? new PembayaranService())->tandaiLunas($id, 'tunai', $kasirId);
        if ($berhasil) {
            error_log(sprintf('[AUDIT TUNAI] pemesanan_id=%d status=menunggu_pembayaran->diproses pengguna_id=%d', $id, $kasirId));
            return ['ok' => true, 'kode' => 'lunas', 'pesan' => 'Pembayaran tunai berhasil dikonfirmasi.'];
        }

        // Cek ulang bila terjadi balapan (race condition)
        $s->execute([$id]);
        $ulang = $s->fetch();
        if ($ulang && ((string) ($ulang['status_pembayaran'] ?? '') === 'paid')) {
            return ['ok' => true, 'kode' => 'sudah_lunas', 'pesan' => 'Pembayaran tunai sudah dikonfirmasi sebelumnya.'];
        }

        return ['ok' => false, 'kode' => 'status_tidak_valid', 'pesan' => 'Pesanan tidak dapat dilunasi.'];
    }

    /**
     * Pengecekan manual status QRIS oleh kasir: mencari midtrans_order_id dari tabel pembayaran,
     * lalu memanggil PembayaranService::sinkron($midtransOrderId).
     * Bila Midtrans mengembalikan null (404), dikembalikan sebagai 'belum_dibayar'.
     *
     * @return array{ok:bool, status:string, pesan:string}
     *         status: 'lunas' | 'belum_dibayar' | 'expire' | 'cancel' | 'deny' | 'failure' | 'tidak_ditemukan' | 'bukan_qris'
     */
    public static function cekQris(int $id, ?PembayaranService $service = null): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'status' => 'tidak_ditemukan', 'pesan' => 'Pesanan tidak ditemukan.'];
        }

        $s = db()->prepare(
            "SELECT p.id, p.status_pemesanan, b.metode_pembayaran, b.status_pembayaran, b.midtrans_order_id
               FROM pemesanan p
               LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
              WHERE p.id = ?"
        );
        $s->execute([$id]);
        $row = $s->fetch();
        if (!$row) {
            return ['ok' => false, 'status' => 'tidak_ditemukan', 'pesan' => 'Pesanan tidak ditemukan.'];
        }

        if ((string) ($row['metode_pembayaran'] ?? '') !== 'qris') {
            return ['ok' => false, 'status' => 'bukan_qris', 'pesan' => 'Pesanan bukan metode pembayaran QRIS.'];
        }

        $statusPesanan = (string) $row['status_pemesanan'];
        $statusBayar   = (string) ($row['status_pembayaran'] ?? '');

        if ($statusBayar === 'paid' || in_array($statusPesanan, ['diproses', 'siap', 'selesai', 'dibayar'], true)) {
            return ['ok' => true, 'status' => 'lunas', 'pesan' => 'Pembayaran QRIS sudah lunas.'];
        }

        if ($statusPesanan !== 'menunggu_pembayaran' || $statusBayar !== 'pending') {
            $kodeAkhir = $statusBayar !== '' && $statusBayar !== 'pending' ? $statusBayar : $statusPesanan;
            return ['ok' => true, 'status' => $kodeAkhir, 'pesan' => 'Transaksi QRIS sudah tidak dalam status menunggu pembayaran.'];
        }

        $midtransOrderId = trim((string) ($row['midtrans_order_id'] ?? ''));
        if ($midtransOrderId === '') {
            return ['ok' => true, 'status' => 'belum_dibayar', 'pesan' => 'Transaksi QRIS belum memiliki ID Midtrans.'];
        }

        $hasilSinkron = ($service ?? new PembayaranService())->sinkron($midtransOrderId);
        if ($hasilSinkron === null) {
            return ['ok' => true, 'status' => 'belum_dibayar', 'pesan' => 'Transaksi QRIS belum dibayar (404 di Midtrans).'];
        }

        $s->execute([$id]);
        $setelah = $s->fetch();
        $bayarBaru   = (string) ($setelah['status_pembayaran'] ?? 'pending');
        $pesananBaru = (string) ($setelah['status_pemesanan'] ?? 'menunggu_pembayaran');

        if ($bayarBaru === 'paid' || in_array($pesananBaru, ['diproses', 'siap', 'selesai', 'dibayar'], true)) {
            return ['ok' => true, 'status' => 'lunas', 'pesan' => 'Pembayaran QRIS berhasil diterima.'];
        }

        if ($pesananBaru === 'batal' || in_array($bayarBaru, ['expire', 'cancel', 'deny', 'failure'], true)) {
            return ['ok' => true, 'status' => $bayarBaru !== 'pending' ? $bayarBaru : 'batal', 'pesan' => 'Transaksi QRIS tidak berhasil (' . $bayarBaru . ').'];
        }

        return ['ok' => true, 'status' => 'belum_dibayar', 'pesan' => 'Pembayaran QRIS masih menunggu penyelesaian pelanggan.'];
    }

    /**
     * Daftar pesanan masuk yang masih berstatus 'menunggu_pembayaran' (tanpa N+1 query).
     * Mengembalikan: id, nomor, no_meja, nama_pelanggan, metode_pembayaran, status_pembayaran,
     * ringkasan_item ("Menu A + Menu B"), jumlah_item, total_harga, waktu_pemesanan, menit_sejak_dipesan.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function daftarMasuk(): array
    {
        $sql = "
            SELECT p.id,
                   m.no_meja,
                   p.nama_pelanggan,
                   p.total_harga,
                   p.waktu_pemesanan,
                   COALESCE(b.metode_pembayaran, 'tunai') AS metode_pembayaran,
                   COALESCE(b.status_pembayaran, 'pending') AS status_pembayaran,
                   COALESCE(agg.ringkasan_item, '') AS ringkasan_item,
                   COALESCE(agg.jumlah_item, 0) AS jumlah_item,
                   GREATEST(0, FLOOR(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - p.waktu_pemesanan)) / 60))::int AS menit_sejak_dipesan
              FROM pemesanan p
              JOIN meja m ON m.id = p.meja_id
              LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
              LEFT JOIN (
                   SELECT d.pemesanan_id,
                          STRING_AGG(mn.nama_menu, ' + ' ORDER BY d.id) AS ringkasan_item,
                          SUM(d.jumlah)::int AS jumlah_item
                     FROM detail_pemesanan d
                     JOIN menu mn ON mn.id = d.menu_id
                    GROUP BY d.pemesanan_id
              ) agg ON agg.pemesanan_id = p.id
             WHERE p.status_pemesanan = 'menunggu_pembayaran'
             ORDER BY p.waktu_pemesanan ASC, p.id ASC
        ";

        $rows = db()->query($sql)->fetchAll();
        return array_map(static fn (array $r): array => [
            'id'                  => (int) $r['id'],
            'nomor'               => self::displayNumber((int) $r['id']),
            'no_meja'             => (string) $r['no_meja'],
            'nama_pelanggan'      => (string) $r['nama_pelanggan'],
            'metode_pembayaran'   => (string) $r['metode_pembayaran'],
            'status_pembayaran'   => (string) $r['status_pembayaran'],
            'ringkasan_item'      => (string) $r['ringkasan_item'],
            'jumlah_item'         => (int) $r['jumlah_item'],
            'total_harga'         => (int) $r['total_harga'],
            'waktu_pemesanan'     => (string) $r['waktu_pemesanan'],
            'menit_sejak_dipesan' => (int) $r['menit_sejak_dipesan'],
        ], $rows);
    }

    /**
     * Daftar antrean pesanan untuk dashboard kasir (tanpa N+1 query), diurutkan terbaru dulu.
     * Bila $status diisi (mis. 'diproses', 'siap', 'selesai', 'batal', 'menunggu_pembayaran'),
     * hanya mengembalikan pesanan dengan status tersebut.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function daftarAntrean(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $limit  = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $status = $status !== null ? trim($status) : null;
        if ($status === '') {
            $status = null;
        }

        $where = $status !== null ? 'WHERE p.status_pemesanan = :status' : '';
        $sql = "
            SELECT p.id,
                   m.no_meja,
                   p.nama_pelanggan,
                   p.catatan,
                   p.total_harga,
                   p.status_pemesanan,
                   p.waktu_pemesanan,
                   p.diperbarui_pada,
                   b.pengguna_id,
                   u.nama AS nama_kasir,
                   COALESCE(b.metode_pembayaran, 'tunai') AS metode_pembayaran,
                   COALESCE(b.status_pembayaran, 'pending') AS status_pembayaran,
                   b.waktu_pembayaran,
                   COALESCE(agg.ringkasan_item, '') AS ringkasan_item,
                   COALESCE(agg.jumlah_item, 0) AS jumlah_item,
                   GREATEST(0, FLOOR(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - p.waktu_pemesanan)) / 60))::int AS menit_sejak_dipesan
              FROM pemesanan p
              JOIN meja m ON m.id = p.meja_id
              LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
              LEFT JOIN pengguna u ON u.id = b.pengguna_id
              LEFT JOIN (
                   SELECT d.pemesanan_id,
                          STRING_AGG(mn.nama_menu, ' + ' ORDER BY d.id) AS ringkasan_item,
                          SUM(d.jumlah)::int AS jumlah_item
                     FROM detail_pemesanan d
                     JOIN menu mn ON mn.id = d.menu_id
                    GROUP BY d.pemesanan_id
              ) agg ON agg.pemesanan_id = p.id
             {$where}
             ORDER BY p.waktu_pemesanan DESC, p.id DESC
             LIMIT :limit OFFSET :offset
        ";

        $stmt = db()->prepare($sql);
        if ($status !== null) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn (array $r): array => [
            'id'                  => (int) $r['id'],
            'nomor'               => self::displayNumber((int) $r['id']),
            'no_meja'             => (string) $r['no_meja'],
            'nama_pelanggan'      => (string) $r['nama_pelanggan'],
            'catatan'             => $r['catatan'],
            'total_harga'         => (int) $r['total_harga'],
            'status_pemesanan'    => (string) $r['status_pemesanan'],
            'label_status'        => self::STATUS_LABEL[(string) $r['status_pemesanan']] ?? (string) $r['status_pemesanan'],
            'metode_pembayaran'   => (string) $r['metode_pembayaran'],
            'status_pembayaran'   => (string) $r['status_pembayaran'],
            'waktu_pembayaran'    => $r['waktu_pembayaran'],
            'waktu_pemesanan'     => (string) $r['waktu_pemesanan'],
            'diperbarui_pada'     => (string) $r['diperbarui_pada'],
            'pengguna_id'         => $r['pengguna_id'] !== null ? (int) $r['pengguna_id'] : null,
            'nama_kasir'          => $r['nama_kasir'],
            'ringkasan_item'      => (string) $r['ringkasan_item'],
            'jumlah_item'         => (int) $r['jumlah_item'],
            'menit_sejak_dipesan' => (int) $r['menit_sejak_dipesan'],
        ], $stmt->fetchAll());
    }

    /**
     * Hitung ringkasan metrik pesanan untuk kartu statistik dashboard kasir dalam 1 query:
     *   - belum_bayar: jumlah pesanan berstatus menunggu_pembayaran
     *   - lunas_hari_ini: jumlah pembayaran 'paid' dengan waktu_pembayaran hari ini (WIB)
     *   - pendapatan_hari_ini: total nominal pembayaran 'paid' hari ini (WIB)
     *   - per_status: jumlah pesanan untuk masing-masing status_pemesanan
     *
     * @return array{belum_bayar:int, lunas_hari_ini:int, pendapatan_hari_ini:int, per_status:array<string,int>}
     */
    public static function hitungRingkas(): array
    {
        $row = db()->query("
            SELECT COUNT(*) FILTER (WHERE p.status_pemesanan = 'menunggu_pembayaran')::int AS belum_bayar,
                   COUNT(*) FILTER (WHERE p.status_pemesanan = 'diproses')::int            AS cnt_diproses,
                   COUNT(*) FILTER (WHERE p.status_pemesanan = 'siap')::int                AS cnt_siap,
                   COUNT(*) FILTER (WHERE p.status_pemesanan = 'selesai')::int             AS cnt_selesai,
                   COUNT(*) FILTER (WHERE p.status_pemesanan = 'batal')::int               AS cnt_batal,
                   COUNT(*) FILTER (
                       WHERE b.status_pembayaran = 'paid'
                         AND b.waktu_pembayaran::date = CURRENT_DATE
                   )::int AS lunas_hari_ini,
                   COALESCE(SUM(b.total_bayar) FILTER (
                       WHERE b.status_pembayaran = 'paid'
                         AND b.waktu_pembayaran::date = CURRENT_DATE
                   ), 0)::int AS pendapatan_hari_ini
              FROM pemesanan p
              LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
        ")->fetch() ?: [];

        $belumBayar = (int) ($row['belum_bayar'] ?? 0);
        return [
            'belum_bayar'         => $belumBayar,
            'lunas_hari_ini'      => (int) ($row['lunas_hari_ini'] ?? 0),
            'pendapatan_hari_ini' => (int) ($row['pendapatan_hari_ini'] ?? 0),
            'per_status'          => [
                'menunggu_pembayaran' => $belumBayar,
                'diproses'            => (int) ($row['cnt_diproses'] ?? 0),
                'siap'                => (int) ($row['cnt_siap'] ?? 0),
                'selesai'             => (int) ($row['cnt_selesai'] ?? 0),
                'batal'               => (int) ($row['cnt_batal'] ?? 0),
            ],
        ];
    }

    /**
     * Detail lengkap satu pesanan untuk tampilan kasir (mencakup item + kustomisasi terurai +
     * catatan umum + informasi pembayaran + kasir pemroses).
     */
    public static function detail(int $id): ?array
    {
        $order = self::find($id);
        if (!$order) {
            return null;
        }

        $s = db()->prepare(
            "SELECT b.pengguna_id,
                    u.nama AS nama_kasir,
                    b.id AS pembayaran_id,
                    b.total_bayar,
                    b.pengguna_id AS kasir_pembayaran_id
               FROM pemesanan p
               LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
               LEFT JOIN pengguna u ON u.id = b.pengguna_id
              WHERE p.id = ?"
        );
        $s->execute([$id]);
        $extra = $s->fetch() ?: [];

        $statusPesanan = (string) $order['status_pemesanan'];
        $statusBayar   = (string) ($order['status_pembayaran'] ?? 'pending');

        $order['label_status']        = self::STATUS_LABEL[$statusPesanan] ?? $statusPesanan;
        $order['label_pembayaran']    = self::PAYMENT_LABEL[$statusBayar] ?? $statusBayar;
        $order['lunas']               = self::isPaid($order);
        $order['pengguna_id']         = isset($extra['pengguna_id']) && $extra['pengguna_id'] !== null ? (int) $extra['pengguna_id'] : null;
        $order['nama_kasir']          = $extra['nama_kasir'] ?? null;
        $order['pembayaran_id']       = isset($extra['pembayaran_id']) && $extra['pembayaran_id'] !== null ? (int) $extra['pembayaran_id'] : null;
        $order['total_bayar']         = isset($extra['total_bayar']) ? (int) $extra['total_bayar'] : (int) $order['total_harga'];
        $order['kasir_pembayaran_id'] = isset($extra['kasir_pembayaran_id']) && $extra['kasir_pembayaran_id'] !== null ? (int) $extra['kasir_pembayaran_id'] : null;

        return $order;
    }
}
