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
}
