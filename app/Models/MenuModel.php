<?php
/**
 * Model menu & kategori (tabel: menu, kategori, detail_pemesanan, pembayaran).
 *
 * Bentuk data yang dikembalikan disesuaikan dengan kebutuhan view pelanggan:
 *   id, name, price, description, status ('available' | 'soldout'),
 *   category (slug nama_kategori), image (path relatif ke public/assets),
 *   dim, customizable
 */
final class MenuModel
{
    /** Kategori yang memakai pop-up kustomisasi (ukuran, gula, es, add-on). */
    private const CUSTOMIZABLE_CATEGORIES = ['minuman'];

    private const SELECT = "SELECT m.id, m.nama_menu, m.harga, m.gambar, m.deskripsi, m.stok, k.nama_kategori
                              FROM menu m
                              JOIN kategori k ON k.id = m.kategori_id";

    /** Semua menu aktif, urut per kategori. */
    public static function all(): array
    {
        try {
            $rows = db()->query(self::SELECT . " WHERE m.aktif ORDER BY k.id, m.id")->fetchAll();
            return array_map([self::class, 'map'], $rows);
        } catch (Throwable) {
            return array_map([self::class, 'map'], self::seedFallback());
        }
    }

    /** Menu favorit: paling laris (pembayaran 'paid'), lalu yang stoknya tersedia. */
    public static function favorites(int $limit = 6): array
    {
        try {
            $s = db()->prepare(self::SELECT . "
                 LEFT JOIN (
                       SELECT d.menu_id, SUM(d.jumlah) AS terjual
                         FROM detail_pemesanan d
                         JOIN pembayaran p ON p.pemesanan_id = d.pemesanan_id AND p.status_pembayaran = 'paid'
                        GROUP BY d.menu_id
                 ) t ON t.menu_id = m.id
                 WHERE m.aktif
                 ORDER BY COALESCE(t.terjual, 0) DESC, (m.stok > 0) DESC, k.id, m.id
                 LIMIT ?");
            $s->bindValue(1, $limit, PDO::PARAM_INT);
            $s->execute();
            return array_map([self::class, 'map'], $s->fetchAll());
        } catch (Throwable) {
            return array_slice(self::all(), 0, $limit);
        }
    }

    /** Opsi filter kategori: ['all' => 'Semua Kategori', 'minuman' => 'Minuman', ...] */
    public static function categories(): array
    {
        $categories = ['all' => 'Semua Kategori'];
        try {
            foreach (db()->query("SELECT nama_kategori FROM kategori ORDER BY id")->fetchAll() as $row) {
                $categories[slugify($row['nama_kategori'])] = $row['nama_kategori'];
            }
        } catch (Throwable) {
            $categories += ['minuman' => 'Minuman', 'makanan' => 'Makanan', 'dessert' => 'Dessert'];
        }
        return $categories;
    }

    /** Data fallback sementara jika koneksi DB belum dikonfigurasi (sesuai QuickBrew.sql). */
    private static function seedFallback(): array
    {
        $seed = [
            [1, 'Minuman', 'Americano', 20000, 'americano.png', 'Espresso dengan air dan es, memiliki rasa kopi yang ringan dan menyegarkan.', 50],
            [2, 'Minuman', 'Hazelnut Latte', 26000, 'hazelnut-latte.png', 'Latte creamy dengan tambahan sirup hazelnut yang harum dan manis.', 50],
            [3, 'Minuman', 'Matcha Latte', 24000, 'matcha-latte.png', 'Matcha lembut yang dipadukan dengan susu creamy dan sedikit rasa earthy.', 50],
            [4, 'Minuman', 'Thai Tea Original', 19000, 'thai-tea-original.png', 'Teh Thailand dengan susu yang creamy, manis, dan aroma teh yang khas.', 50],
            [5, 'Minuman', 'Strawberry Milk', 26000, 'strawberry-milk.png', 'Susu creamy dengan perpaduan rasa strawberry yang manis dan fruity.', 50],
            [6, 'Minuman', 'Mocha Latte', 26000, 'mocha-latte.png', 'Perpaduan espresso, cokelat, dan susu dengan rasa manis yang seimbang.', 50],
            [7, 'Minuman', 'Choco Latte', 24000, 'choco-latte.png', 'Cokelat creamy dan susu dengan rasa manis yang cocok untuk segala suasana.', 50],
            [8, 'Minuman', 'Caramel Frappe', 24000, 'caramel-frappe.png', 'Minuman blended dingin perpaduan kopi, susu, dan saus karamel yang manis.', 50],
            [9, 'Minuman', 'Caramel Macchiato', 20000, 'caramel-macchiato.png', 'Espresso dan susu dengan sentuhan manis serta aroma karamel.', 50],
            [10, 'Minuman', 'Caffè Latte', 26000, 'caffe-latte.png', 'Espresso dengan susu steamed yang creamy dan lembut.', 50],
            [11, 'Minuman', 'Cappuccino', 24000, 'cappuccino.png', 'Perpaduan espresso, susu steamed, dan foam lembut dengan rasa creamy.', 50],
            [12, 'Minuman', 'Peach Tea', 19000, 'peach-tea.png', 'Teh dingin dengan perpaduan rasa peach yang manis dan menyegarkan.', 50],
            [13, 'Minuman', 'Lychee Tea', 20000, 'lychee-tea.png', 'Teh segar dengan rasa leci yang manis dan aroma buah yang menyegarkan.', 50],
            [14, 'Makanan', 'French Fries', 26000, 'french-fries.png', 'Kentang goreng renyah dengan rasa gurih, cocok sebagai teman minuman.', 50],
            [15, 'Makanan', 'Spaghetti Carbonara', 24000, 'spaghetti-carbonara.png', 'Spaghetti al dente dengan saus krim gurih dan taburan keju.', 50],
            [16, 'Makanan', 'Chicken Pasta', 19000, 'chicken-pasta.png', 'Pasta dengan potongan ayam panggang saus gurih kaya rasa.', 50],
            [17, 'Makanan', 'Chicken Croffle', 20000, 'chicken-croffle.png', 'Croffle renyah dengan potongan ayam gurih dan saus yang creamy.', 50],
            [18, 'Makanan', 'Beef Toast', 26000, 'beef-toast.png', 'Roti panggang dengan irisan daging sapi gurih dan saus khas.', 50],
            [19, 'Makanan', 'Chicken Sandwich', 19000, 'chicken-sandwich.png', 'Roti lembut berisi ayam gurih, sayuran segar, dan saus creamy.', 50],
            [20, 'Makanan', 'Croissant Butter', 24000, 'croissant-butter.png', 'Croissant renyah di luar dengan tekstur lembut dan buttery di dalam.', 50],
            [21, 'Dessert', 'Waffle Berry', 24000, 'waffle-berry.png', 'Waffle hangat dengan saus berry manis dan topping yang segar.', 50],
            [22, 'Dessert', 'Tiramisu', 19000, 'tiramisu.png', 'Dessert lembut dengan perpaduan mascarpone, kopi, dan cocoa.', 50],
            [23, 'Dessert', 'Brownies', 20000, 'brownies.png', 'Brownie cokelat dengan tekstur fudgy dan rasa cokelat yang pekat.', 50],
            [24, 'Dessert', 'Banana Pancake', 26000, 'banana-pancake.png', 'Pancake lembut dengan potongan pisang dan sirup manis.', 50],
            [25, 'Dessert', 'Cheesecake', 19000, 'cheesecake.png', 'Cheesecake lembut dengan rasa keju creamy dan tekstur yang ringan.', 50],
            [26, 'Dessert', 'Red Velvet Cake', 20000, 'red-velvet-cake.png', 'Cake red velvet lembut dengan cream cheese yang creamy dan sedikit tangy.', 50],
            [27, 'Dessert', 'Choco Waffle', 26000, 'choco-waffle.png', 'Waffle hangat dengan saus cokelat dan topping krim yang manis.', 50],
            [28, 'Dessert', 'Choco Cake', 24000, 'choco-cake.png', 'Cake cokelat lembut dengan rasa cokelat yang rich dan manis.', 50],
        ];
        return array_map(static fn (array $r): array => [
            'id'            => $r[0],
            'nama_kategori' => $r[1],
            'nama_menu'     => $r[2],
            'harga'         => $r[3],
            'gambar'        => $r[4],
            'deskripsi'     => $r[5],
            'stok'          => $r[6],
        ], $seed);
    }

    private static function map(array $row): array
    {
        $category = slugify($row['nama_kategori']);
        return [
            'id'           => (int) $row['id'],
            'name'         => $row['nama_menu'],
            'price'        => (int) $row['harga'],
            'description'  => (string) $row['deskripsi'],
            'status'       => (int) $row['stok'] > 0 ? 'available' : 'soldout',
            'stock'        => (int) $row['stok'],
            'category'     => $category,
            'image'        => self::image($row['gambar'], $row['nama_menu']),
            'dim'          => false,
            'customizable' => in_array($category, self::CUSTOMIZABLE_CATEGORIES, true),
        ];
    }

    /**
     * Path gambar relatif ke public/assets.
     * Urutan: kolom menu.gambar (nama file di assets/images/menu atau URL) -> slug nama menu -> logo.
     */
    private static function image(?string $gambar, string $nama): string
    {
        $gambar = trim((string) $gambar);
        if (preg_match('#^https?://#i', $gambar)) return $gambar;

        $dir   = APP_ROOT . '/public/assets/images/menu/';
        $slug  = slugify($nama);
        $files = array_filter([$gambar !== '' ? basename($gambar) : null, $slug . '.png', $slug . '.jpg']);
        foreach ($files as $file) {
            if (is_file($dir . $file)) return 'images/menu/' . $file;
        }
        return 'images/logo.png';
    }
}

