<?php
/**
 * Opsi kustomisasi pesanan - diambil dari Figma "Customization Pop-up - HiFi" (node 52:294).
 * Belum ada API/database; ganti dengan data dari backend nanti.
 *
 * Catatan:
 *  - Opsi pertama pada tiap grup menjadi pilihan awal (asumsi; Figma hanya menampilkan satu contoh state).
 *  - 'prices' = harga tambahan per opsi (rupiah). Ukuran M +3.000, L +5.000 (permintaan revisi; tidak ada di Figma).
 *  - Desain hanya memiliki satu varian (minuman); belum ada varian untuk makanan/dessert.
 */
return [
    'size' => [
        'label'   => 'Ukuran',
        'layout'  => 'spread',   // S kiri, M tengah, L kanan
        'options' => ['S', 'M', 'L'],
        'prices'  => ['M' => 3000, 'L' => 5000],
    ],
    'type' => [
        'label'   => 'Tipe Minuman',
        'layout'  => 'spread',   // Hot kiri, Ice kanan
        'options' => ['Hot', 'Ice'],
    ],
    'sugar' => [
        'label'   => 'Level Gula',
        'layout'  => 'level',
        'options' => ['Normal Sugar', 'Less Sugar', 'Extra Sugar'],
    ],
    'ice' => [
        'label'   => 'Level Ice',
        'layout'  => 'level',
        'options' => ['Normal Ice', 'Less Ice', 'Extra Ice'],
    ],
    'addons' => [
        'label' => 'Add-on / Topping',
        'items' => [
            ['id' => 'extra-shot',  'label' => 'Extra Shot',  'price' => 4000],
            ['id' => 'grass-jelly', 'label' => 'Grass Jelly', 'price' => 3000],
            ['id' => 'boba',        'label' => 'Boba',        'price' => 3000],
        ],
    ],
];
