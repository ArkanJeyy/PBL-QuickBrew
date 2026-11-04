<?php
/**
 * Halaman konfirmasi pesanan setelah Checkout (data dari database, bukan dari keranjang).
 * Variabel dari PelangganController::pesanan():
 *   $order       array|null  Hasil PemesananModel::find(); null bila id/token tidak cocok
 *   $justOrdered bool        True tepat setelah pesanan dibuat -> keranjang dikosongkan (pesanan.js)
 *   $tableNumber string|null Nomor meja dari QR
 *   $metodeList  array       Label metode pembayaran
 *   $statusLabel array       Label status pesanan
 */
$pageTitle       = 'Pesanan - ' . APP_NAME;
$pageStyles      = ['cart', 'checkout'];
$pageScripts     = ['cart-store', 'pesanan'];
$pageClass       = 'page--checkout';
$withBrandHeader = false;
$withBottomBar   = false;

$headerTitle = 'Pesanan';
$backUrl     = url('menu');
$backLabel   = 'Kembali ke menu';

require __DIR__ . '/../../layouts/header.php';
require __DIR__ . '/../component/cart-header.php';
?>

  <main class="checkout-main checkout-main--done main-panel"<?= $justOrdered ? ' data-clear-cart' : '' ?>>
<?php if (!$order): ?>
    <section class="checkout-card checkout-notice">
      <h2 class="checkout-card__title">Pesanan tidak ditemukan</h2>
      <p class="checkout-notice__text">Tautan pesanan tidak valid atau sudah tidak berlaku.</p>
      <a class="btn btn--block" href="<?= e(url('menu')) ?>">Kembali ke Menu</a>
    </section>
<?php else: ?>
<?php
    $metode = (string) $order['metode_pembayaran'];
    $status = $statusLabel[$order['status_pemesanan']] ?? $order['status_pemesanan'];
?>
    <section class="checkout-card order-done" aria-labelledby="done-title">
      <p class="order-done__badge" aria-hidden="true">&#10003;</p>
      <h2 class="order-done__title" id="done-title">Pesanan berhasil dibuat</h2>
      <dl class="order-done__info">
        <div><dt>Nomor pesanan</dt><dd>#<?= e((string) $order['id']) ?></dd></div>
        <div><dt>Meja</dt><dd><?= e($order['no_meja']) ?></dd></div>
        <div><dt>Atas nama</dt><dd><?= e($order['nama_pelanggan']) ?></dd></div>
        <div><dt>Status</dt><dd><span class="order-status"><?= e($status) ?></span></dd></div>
      </dl>
    </section>

    <section class="checkout-card order-pay" aria-labelledby="pay-title">
      <h2 class="checkout-card__title checkout-card__title--ruled" id="pay-title">Pembayaran: <?= e($metodeList[$metode] ?? $metode) ?></h2>
<?php if ($metode === 'qris'): ?>
      <img class="order-pay__qris" src="<?= e(asset('images/qris-placeholder.svg')) ?>" alt="Kode QRIS pembayaran">
      <p class="checkout-notice__text">Scan QRIS di atas, bayar sebesar <strong><?= e(format_rupiah($order['total_harga'])) ?></strong>, lalu tunjukkan bukti pembayaran ke kasir beserta nomor pesanan Anda.</p>
<?php else: ?>
      <p class="checkout-notice__text">Silakan bayar sebesar <strong><?= e(format_rupiah($order['total_harga'])) ?></strong> di kasir dan sebutkan nomor pesanan <strong>#<?= e((string) $order['id']) ?></strong>.</p>
<?php endif; ?>
    </section>

    <section class="checkout-card" aria-labelledby="detail-title">
      <h2 class="checkout-card__title checkout-card__title--ruled" id="detail-title">Ringkasan Pesanan</h2>
      <ul class="checkout-items">
<?php foreach ($order['items'] as $item): ?>
        <li class="checkout-item">
          <div class="checkout-item__row">
            <div class="checkout-item__media"><img src="<?= e(asset($item['image'])) ?>" alt=""></div>
            <div class="checkout-item__info">
              <p class="checkout-item__name"><?= e($item['nama_menu']) ?></p>
              <p class="checkout-item__qty"><?= e((string) $item['jumlah']) ?>x</p>
            </div>
            <p class="checkout-item__price"><?= e(format_rupiah($item['subtotal'])) ?></p>
          </div>
<?php if (!empty($item['catatan'])): ?>
          <p class="checkout-item__meta"><?= e($item['catatan']) ?></p>
<?php endif; ?>
        </li>
<?php endforeach; ?>
      </ul>
      <div class="checkout-total">
        <div class="checkout-total__row"><span>Pajak (10%)</span><span><?= e(format_rupiah($order['tax'])) ?></span></div>
        <div class="checkout-total__row checkout-total__row--grand"><span>Total</span><span><?= e(format_rupiah($order['total_harga'])) ?></span></div>
      </div>
<?php if (!empty($order['catatan'])): ?>
      <p class="order-note"><strong>Catatan:</strong> <?= e($order['catatan']) ?></p>
<?php endif; ?>
    </section>

    <a class="btn btn--block btn--outline order-done__back" href="<?= e(url('menu')) ?>">Kembali ke Menu</a>
<?php endif; ?>
  </main>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
