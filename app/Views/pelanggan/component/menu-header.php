<?php
require_once __DIR__ . '/table-badge.php';

/**
 * Header halaman Menu: logo, ikon keranjang, dan label nomor meja.
 * Variabel: $tableNumber (string|null) - nomor meja dari QR (?meja=<kode_qr>), disimpan di session.
 */
?>
  <header class="menu-header">
    <a class="menu-header__logo" href="<?= e(url()) ?>" aria-label="<?= e(APP_NAME) ?> - beranda">
      <img src="<?= e(asset('images/logo.png')) ?>" alt="">
    </a>
    <a class="menu-header__cart" href="<?= e(url('cart')) ?>" aria-label="Keranjang" data-cart-link>
      <img src="<?= e(asset('images/icons/icon-cart.svg')) ?>" alt="">
      <span class="cart-badge" data-cart-badge hidden></span>
    </a>
<?php render_table_badge($tableNumber ?? null); ?>
  </header>
