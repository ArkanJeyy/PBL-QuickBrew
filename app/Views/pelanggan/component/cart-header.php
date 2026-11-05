<?php
require_once __DIR__ . '/table-badge.php';

/**
 * Header halaman Keranjang / Checkout / Pesanan: tombol kembali, judul, dan label nomor meja.
 * Variabel:
 *   $tableNumber (string|null) - nomor meja dari QR (?meja=<kode_qr>), disimpan di session.
 *   $headerTitle (string)      - judul (default "Keranjang").
 *   $backUrl     (string)      - tujuan tombol kembali (default halaman Menu).
 *   $backLabel   (string)      - aria-label tombol kembali (default "Kembali ke menu").
 */
$headerTitle = $headerTitle ?? 'Keranjang';
$backUrl     = $backUrl ?? url('menu');
$backLabel   = $backLabel ?? 'Kembali ke menu';
?>
  <header class="cart-header">
    <a class="cart-header__back" href="<?= e($backUrl) ?>" aria-label="<?= e($backLabel) ?>">
      <img src="<?= e(asset('images/icons/icon-arrow-back.svg')) ?>" alt="">
    </a>
    <h1 class="cart-header__title"><?= e($headerTitle) ?></h1>
<?php render_table_badge($tableNumber ?? null); ?>
  </header>
