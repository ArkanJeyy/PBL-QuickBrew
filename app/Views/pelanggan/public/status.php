<?php
/**
 * Halaman Status Pesanan (data dari database). Kartu status + stepper 4 langkah + ringkasan pesanan.
 * Variabel dari PelangganController::status():
 *   $order       array|null  Hasil PemesananModel::find(); null bila id/token tidak cocok
 *   $view        array|null  PemesananModel::statusView(): state (unpaid|progress|done|cancel), title, desc, steps
 *   $statusUrl   string      URL halaman ini (tombol "Lihat Status Pesanan" = muat ulang)
 *   $paymentUrl  string      URL halaman Pembayaran (tombol "Selesaikan Pembayaran")
 *   $estimate    string      Teks estimasi, mis. "10 - 15 menit"
 *   $tableNumber string|null Nomor meja dari QR
 */
require_once __DIR__ . '/../component/status-stepper.php';
require_once __DIR__ . '/../component/order-summary.php';

$pageTitle       = 'Status Pesanan - ' . APP_NAME;
$pageStyles      = ['cart', 'checkout', 'status'];
$pageScripts     = [];
$pageClass       = 'page--checkout page--status';
$withBrandHeader = false;
$withBottomBar   = false;

$headerTitle = 'Status Pesanan';
$backUrl     = url('menu');
$backLabel   = 'Kembali ke menu';

$state = $view['state'] ?? '';

require __DIR__ . '/../../layouts/header.php';
require __DIR__ . '/../component/cart-header.php';
?>

  <main class="checkout-main status-main main-panel">
<?php if (!$order): ?>
    <section class="checkout-card checkout-notice">
      <h2 class="checkout-card__title">Pesanan tidak ditemukan</h2>
      <p class="checkout-notice__text">Tautan pesanan tidak valid atau sudah tidak berlaku.</p>
      <a class="btn btn--block" href="<?= e(url('menu')) ?>">Kembali ke Menu</a>
    </section>
<?php else: ?>
    <section class="status-card status-card--<?= e($state) ?>" aria-labelledby="status-title">
      <span class="status-card__icon" aria-hidden="true"><?php render_status_icon('cup'); ?></span>
      <h2 class="status-card__title" id="status-title"><?= e($view['title']) ?></h2>
      <p class="status-card__desc" role="status"><?= e($view['desc']) ?></p>
<?php render_status_stepper($view['steps']); ?>
    </section>

    <section class="checkout-card" aria-labelledby="status-summary-title">
      <h3 class="checkout-card__title checkout-card__title--ruled" id="status-summary-title">Ringkasan Pesanan</h3>
<?php render_order_summary($order, true); ?>
    </section>

<?php if ($state === 'progress'): ?>
    <section class="checkout-card status-estimate" aria-label="Estimasi waktu">
      <span class="status-estimate__icon" aria-hidden="true"><?php render_status_icon('clock'); ?></span>
      <div>
        <p class="status-estimate__label">Estimasi setelah bayar</p>
        <p class="status-estimate__value"><?= e($estimate) ?></p>
      </div>
    </section>
<?php endif; ?>

    <div class="checkout-submit">
<?php if ($state === 'unpaid'): ?>
      <a class="btn btn--block checkout-submit__btn" href="<?= e($paymentUrl) ?>">Selesaikan Pembayaran</a>
<?php elseif ($state === 'progress'): ?>
      <a class="btn btn--block checkout-submit__btn" href="<?= e($statusUrl) ?>">Lihat Status Pesanan</a>
<?php else: ?>
      <a class="btn btn--block checkout-submit__btn" href="<?= e(url()) ?>">Kembali ke Beranda</a>
<?php endif; ?>
    </div>
<?php endif; ?>
  </main>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
