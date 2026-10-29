<?php
require_once __DIR__ . '/components/table-badge.php';

/**
 * Header halaman Menu: logo, ikon keranjang, dan label nomor meja.
 * Variabel: $tableNumber (int) - sementara statis sesuai Figma ("Meja 5").
 */
?>
  <header class="menu-header">
    <a class="menu-header__logo" href="index.php" aria-label="<?= e(APP_NAME) ?> - beranda">
      <img src="<?= e(asset('img/logo.png')) ?>" alt="">
    </a>
    <a class="menu-header__cart" href="cart.php" aria-label="Keranjang" data-cart-link>
      <img src="<?= e(asset('icons/icon-cart.svg')) ?>" alt="">
      <span class="cart-badge" data-cart-badge hidden></span>
    </a>
<?php render_table_badge((int) $tableNumber); ?>
  </header>
