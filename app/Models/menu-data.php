<?php
/**
 * Data menu sementara - diambil dari frame Figma "Katalog Menu - HiFi" (node 50:397).
 * Belum ada API/database; ganti isi file ini dengan query database nanti.
 *
 * Catatan:
 *  - Teks deskripsi persis seperti di Figma. Di desain deskripsi hanya 4 teks contoh
 *    yang diulang, sehingga tidak selalu cocok dengan nama menu (placeholder).
 *  - 'category' TIDAK ada di Figma (hanya nama opsi filter). Pengelompokan di bawah adalah asumsi.
 *  - 'status': 'available' (Tersedia) atau 'soldout' (Habis).
 *  - 'customizable' => false: langsung masuk keranjang (makanan, dessert), tanpa pop-up.
 *  - 'dim' => true: foto diberi overlay gelap 20% (hanya Beef Toast di Figma).
 */

$desc = [
    'A' => "Espresso shot premium dengan air\nmurni dan es batu segar.",
    'B' => "Teh khas Thailand wangi berpadu krimer\ndan susu kental manis.",
    'C' => "Pure Uji Matcha Jepang yang diaduk\nbersama susu segar hangat/dingin.",
    'D' => "Espresso, steamed milk, foam tebal, dan\nsentuhan sirup hazelnut.",
];

// [slug, nama, harga, deskripsi, status, kategori]
$rows = [
    ['americano',           'Americano',           20000, 'A', 'available', 'minuman'],
    ['hazelnut-latte',      'Hazelnut Latte',      26000, 'D', 'soldout',   'minuman'],
    ['matcha-latte',        'Matcha Latte',        24000, 'C', 'available', 'minuman'],
    ['thai-tea-original',   'Thai Tea Original',   19000, 'B', 'available', 'minuman'],
    ['strawberry-milk',     'Strawberry Milk',     20000, 'A', 'available', 'minuman'],
    ['mocha-latte',         'Mocha Latte',         26000, 'D', 'soldout',   'minuman'],
    ['choco-latte',         'Choco Latte',         24000, 'C', 'available', 'minuman'],
    ['caramel-frappe',      'Caramel Frappe',      19000, 'B', 'available', 'minuman'],
    ['caramel-macchiato',   'Caramel Macchiato',   20000, 'A', 'available', 'minuman'],
    ['caffe-latte',         'Caffe Latte',         26000, 'D', 'soldout',   'minuman'],
    ['cappuccino',          'Cappuccino',          24000, 'C', 'available', 'minuman'],
    ['peach-tea',           'Peach Tea',           19000, 'B', 'available', 'minuman'],
    ['lyche-tea',           'Lyche Tea',           20000, 'A', 'available', 'minuman'],
    ['french-fries',        'French Fries',        26000, 'D', 'soldout',   'makanan'],
    ['croissan-butter',     'Croissan Butter',     24000, 'C', 'available', 'makanan'],
    ['chicken-sandwich',    'Chicken Sandwich',    19000, 'B', 'available', 'makanan'],
    ['chicken-croffle',     'Chicken Croffle',     20000, 'A', 'available', 'makanan'],
    ['beef-toast',          'Beef Toast',          26000, 'D', 'soldout',   'makanan'],
    ['waffle-berry',        'Waffle Berry',        24000, 'C', 'available', 'dessert'],
    ['tiramisu',            'Tiramisu',            19000, 'B', 'available', 'dessert'],
    ['red-velvet-cake',     'Red Velvet Cake',     20000, 'A', 'available', 'dessert'],
    ['choco-waffle',        'Choco Waffle',        26000, 'D', 'soldout',   'dessert'],
    ['choco-cake',          'Choco Cake',          24000, 'C', 'available', 'dessert'],
    ['cheesecake',          'Cheesecake',          19000, 'B', 'available', 'dessert'],
    ['brownies',            'Brownies',            20000, 'A', 'available', 'dessert'],
    ['banana-pancake',      'Banana Pancake',      26000, 'D', 'soldout',   'dessert'],
    ['spaghetti-carbonara', 'Spaghetti Carbonara', 24000, 'C', 'available', 'makanan'],
    ['chicken-pasta',       'Chicken Pasta',       19000, 'B', 'available', 'makanan'],
];

// Kategori yang langsung masuk keranjang tanpa pop-up Customization (revisi).
$directCategories = ['makanan', 'dessert'];

$items = [];
foreach ($rows as [$slug, $name, $price, $descKey, $status, $category]) {
    $items[] = [
        'id'          => $slug,
        'name'        => $name,
        'price'       => $price,
        'description' => $desc[$descKey],
        'status'      => $status,
        'category'    => $category,
        'image'       => 'img/menu/' . $slug . '.png',
        'dim'         => $slug === 'beef-toast',
        'customizable' => !in_array($category, $directCategories, true),
    ];
}

return $items;
