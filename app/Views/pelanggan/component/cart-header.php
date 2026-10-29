<?php
require_once __DIR__ . '/table-badge.php';

/**
 * Header halaman Keranjang: tombol kembali, judul, dan label nomor meja.
 * Variabel: $tableNumber (string|null) - nomor meja dari QR (?meja=<kode_qr>), disimpan di session.
 */
?>
  <header class="cart-header">
    <a class="cart-header__back" href="<?= e(url('menu')) ?>" aria-label="Kembali ke menu">
      <img src="<?= e(asset('images/icons/icon-arrow-back.svg')) ?>" alt="">
    </a>
    <h1 class="cart-header__title">Keranjang</h1>
<?php render_table_badge($tableNumber ?? null); ?>
  </header>
