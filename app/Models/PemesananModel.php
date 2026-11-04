<?php
/**
 * Model pesanan pelanggan (tabel: pemesanan, detail_pemesanan, pembayaran).
 *
 * - price(): menghitung ulang harga di server dari isi keranjang yang dikirim browser
 *   (harga menu & stok dari database, tambahan harga dari customization-options.php).
 * - create(): menyimpan pesanan + detail + pembayaran (status pending) dalam satu transaksi.
 * - find(): data untuk halaman Pembayaran (dengan nomor pesanan "#A0012" & batas bayar).
 *
 * Stok TIDAK dikurangi di sini; pengurangan stok terjadi saat pembayaran lunas (apply_payment() di config.php).
 *
 * Catatan umum dari form Checkout: tabel pemesanan tidak punya kolom catatan (skema dikelola tim database),
 * jadi catatan disimpan di detail_pemesanan.catatan milik item PERTAMA, setelah penanda NOTE_MARKER.
 * find() memisahkannya lagi menjadi $order['catatan'].
 */
final class PemesananModel
{
    /** Pemisah catatan umum di detail_pemesanan.catatan item pertama: "L, Ice, ... | Catatan pesanan: ...". */
    public const NOTE_MARKER = ' | Catatan pesanan: ';

    /** Panjang maksimal catatan umum agar muat bersama pilihan minuman di VARCHAR(255). */
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

    /** Batas bayar QRIS yang DITAMPILKAN (menit setelah pesanan dibuat). Tidak ada pembatalan otomatis. */
    public const PAYMENT_MINUTES = 15;

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
                'menu_id'  => $menuId,
                'harga'    => $unitPrice,
                'jumlah'   => $quantity,
                'subtotal' => $unitPrice * $quantity,
                'catatan'  => $notes ? text_cut(implode(', ', $notes), 255) : null,
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
        // Catatan umum ikut disimpan di catatan item pertama (lihat penjelasan di atas class)
        $lines = $order['lines'];
        if (!empty($order['catatan']) && $lines) {
            $lines[0]['catatan'] = text_cut(
                ($lines[0]['catatan'] ?? '') . self::NOTE_MARKER . $order['catatan'], 255
            );
        }

        $db = db();
        $db->beginTransaction();
        try {
            $s = $db->prepare(
                "INSERT INTO pemesanan (meja_id, nama_pelanggan, email_pelanggan, total_harga)
                 VALUES (?, ?, ?, ?) RETURNING id"
            );
            $s->execute([$order['meja_id'], $order['nama'], $order['email'], $order['total']]);
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
            $db->rollBack();
            throw $ex;
        }
    }

    /** Pesanan beserta meja, pembayaran, dan detailnya; null bila tidak ada. */
    public static function find(int $id): ?array
    {
        $s = db()->prepare(
            "SELECT p.id, p.nama_pelanggan, p.email_pelanggan, p.total_harga, p.status_pemesanan,
                    p.waktu_pemesanan, m.no_meja, b.metode_pembayaran, b.status_pembayaran
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

        // Pisahkan catatan umum dari catatan item pertama
        $order['catatan'] = null;
        if ($order['items']) {
            $parts = explode(self::NOTE_MARKER, (string) $order['items'][0]['catatan'], 2);
            if (count($parts) === 2) {
                $order['items'][0]['catatan'] = $parts[0] !== '' ? $parts[0] : null;
                $order['catatan']             = $parts[1];
            }
        }

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
}
