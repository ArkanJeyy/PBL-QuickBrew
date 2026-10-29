<?php
require_once __DIR__ . '/components/table-badge.php';

/**
 * Header halaman Keranjang: tombol kembali, judul, dan label nomor meja.
 * Variabel: $tableNumber (int) - sementara statis sesuai Figma ("Meja 5").
 */
?>
  <header class="cart-header">
    <a class="cart-header__back" href="menu.php" aria-label="Kembali ke menu">
      <img src="<?= e(asset('icons/icon-arrow-back.svg')) ?>" alt="">
    </a>
    <h1 class="cart-header__title">Keranjang</h1>
<?php render_table_badge((int) $tableNumber); ?>
  </header>
