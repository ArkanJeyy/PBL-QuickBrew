<?php
/**
 * Halaman Pembayaran setelah Checkout (data dari database, bukan dari keranjang).
 * Tampilan berbeda untuk metode QRIS (gambar QR + batas bayar + Simpan QR) dan Tunai (bayar di kasir).
 * Variabel dari PelangganController::pembayaran():
 *   $order        array|null  Hasil PemesananModel::find(); null bila id/token tidak cocok
 *   $justOrdered  bool        True tepat setelah pesanan dibuat -> keranjang dikosongkan (pembayaran.js)
 *   $statusUrl    string      URL halaman ini (tombol "Lihat Status Pembayaran" = muat ulang)
 *   $tableNumber  string|null Nomor meja dari QR
 *   $paymentLabel array       Label status pembayaran (pending => "Belum Dibayar", ...)
 */
$metode   = $order ? (string) $order['metode_pembayaran'] : '';
$isQris   = $metode === 'qris';
$payState = $order ? (string) ($order['status_pembayaran'] ?? 'pending') : 'pending';
$isPaid   = $payState === 'paid';
$isFailed = $order && ($order['status_pemesanan'] === 'batal' || in_array($payState, ['deny', 'cancel', 'expire', 'failure'], true));

$pageTitle       = ($order ? 'Pembayaran ' . ($isQris ? 'QRIS' : 'Tunai') : 'Pembayaran') . ' - ' . APP_NAME;
$pageStyles      = ['cart', 'checkout'];
$pageScripts     = ['cart-store', 'pembayaran'];
$pageClass       = 'page--checkout';
$withBrandHeader = false;
$withBottomBar   = false;

$headerTitle = $order ? 'Pembayaran ' . ($isQris ? 'QRIS' : 'Tunai') : 'Pembayaran';
$backUrl     = url('menu');
$backLabel   = 'Kembali ke menu';

// Teks kotak info & judul mengikuti status terbaru dari database
if ($isFailed) {
    [$headline, $subline, $notice] = ['Pesanan Dibatalkan', 'Pembayaran tidak diterima', 'Pesanan ini dibatalkan. Silakan hubungi kasir bila ada pertanyaan.'];
} elseif ($isPaid) {
    [$headline, $subline, $notice] = ['Pembayaran Diterima', 'Terima kasih, pesanan Anda sedang diproses', 'Pembayaran sudah dikonfirmasi kasir. Pesanan sedang diproses.'];
} else {
    [$headline, $subline, $notice] = ['Selesaikan Pembayaran', 'Pesanan telah dibuat, tetapi belum diproses', 'Belum ada pembayaran yang dikonfirmasi. Pesanan belum diproses.'];
}

require __DIR__ . '/../../layouts/header.php';
require __DIR__ . '/../component/cart-header.php';
?>

  <main class="checkout-main pay-main main-panel"<?= $justOrdered ? ' data-clear-cart' : '' ?>>
<?php if (!$order): ?>
    <section class="checkout-card checkout-notice">
      <h2 class="checkout-card__title">Pesanan tidak ditemukan</h2>
      <p class="checkout-notice__text">Tautan pesanan tidak valid atau sudah tidak berlaku.</p>
      <a class="btn btn--block" href="<?= e(url('menu')) ?>">Kembali ke Menu</a>
    </section>
<?php else: ?>
<?php $totalText = format_rupiah($order['total_harga']); ?>
    <header class="pay-head">
      <h2 class="pay-head__title"><?= e($headline) ?></h2>
      <p class="pay-head__desc"><?= e($subline) ?></p>
    </header>

    <section class="checkout-card" aria-labelledby="pay-info-title">
      <div class="pay-card-head">
        <h3 class="checkout-card__title pay-card-head__title" id="pay-info-title">Informasi Pesanan</h3>
        <span class="pay-badge pay-badge--<?= e($isPaid ? 'paid' : ($isFailed ? 'failed' : 'pending')) ?>"><?= e($paymentLabel[$payState] ?? $payState) ?></span>
      </div>
      <dl class="pay-info">
        <div><dt>Nomor Pesanan</dt><dd><?= e($order['number']) ?></dd></div>
        <div><dt>Metode Pembayaran</dt><dd><?= e($isQris ? 'QRIS' : 'Tunai') ?></dd></div>
      </dl>
    </section>

<?php if (!$isPaid && !$isFailed): ?>
    <section class="checkout-card pay-amount" aria-labelledby="pay-amount-title">
      <p class="pay-amount__label" id="pay-amount-title">Total yang perlu dibayar</p>
      <p class="pay-amount__value"><?= e($totalText) ?></p>
<?php if ($isQris): ?>
<?php if (!empty($qrSvg)): ?>
      <?= $qrSvg ?>
<?php else: ?>
      <img class="pay-qr" src="<?= e($qrImgSrc ?? asset('images/qris-placeholder.svg')) ?>" alt="Kode QRIS untuk membayar <?= e($totalText) ?>">
<?php endif; ?>
<?php if ($order['deadline'] !== ''): ?>
      <p class="pay-amount__deadline">Batas bayar pukul <?= e($order['deadline']) ?></p>
<?php endif; ?>
      <a class="btn btn--block btn--outline pay-amount__save" href="<?= e($qrDownloadHref ?? asset('images/qris-placeholder.svg')) ?>"
         download="<?= e($qrDownloadName ?? ('qris-quickbrew-' . ltrim($order['number'], '#') . '.svg')) ?>">Simpan QR</a>
<?php if (!MIDTRANS_PROD): ?>
      <form method="post" style="margin-top: 10px;">
        <input type="hidden" name="simulasi_bayar" value="1">
        <button type="submit" class="btn btn--block" style="background: #2e7d32; color: #fff; font-size: 13px; font-weight: 600; padding: 10px; border: none; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
          Simulasikan Pembayaran Berhasil (Sandbox)
        </button>
      </form>
<?php endif; ?>
<?php else: ?>
      <div class="pay-cashier">
        <p class="pay-cashier__title">Sebutkan <?= e($order['number']) ?> di kasir</p>
        <p class="pay-cashier__text">Tunjukkan nomor pesanan ini dan lakukan pembayaran <?= e($totalText) ?> kepada kasir</p>
      </div>
<?php endif; ?>
    </section>
<?php endif; ?>

    <p class="pay-notice<?= $isPaid ? ' pay-notice--paid' : '' ?>" role="status"><?= e($notice) ?></p>

    <section class="checkout-card" aria-labelledby="pay-summary-title">
      <h3 class="checkout-card__title checkout-card__title--ruled" id="pay-summary-title">Ringkasan Pesanan</h3>
      <ul class="checkout-items">
<?php foreach ($order['items'] as $item):
    $opt = $item['options'];
?>
        <li class="checkout-item">
          <div class="checkout-item__row">
            <div class="checkout-item__media"><img src="<?= e(asset($item['image'])) ?>" alt=""></div>
            <div class="checkout-item__info">
              <p class="checkout-item__name"><?= e($item['nama_menu'] . ($opt['size'] !== '' ? ' (' . $opt['size'] . ')' : '')) ?></p>
              <p class="checkout-item__qty"><?= e((string) $item['jumlah']) ?>x</p>
            </div>
            <p class="checkout-item__price"><?= e(format_rupiah($item['subtotal'])) ?></p>
          </div>
<?php if ($opt['variants']): ?>
          <p class="checkout-item__meta"><?= e(implode('  |  ', $opt['variants'])) ?></p>
<?php endif; ?>
<?php if ($opt['addons']): ?>
          <p class="checkout-item__meta">Tambahan: <?= e(implode(', ', array_map(
              static fn (array $a): string => $a['label'] . ($a['price'] ? ' (+' . format_rupiah($a['price']) . ')' : ''),
              $opt['addons']
          ))) ?></p>
<?php endif; ?>
<?php if ($opt['raw'] !== null): ?>
          <p class="checkout-item__meta"><?= e($opt['raw']) ?></p>
<?php endif; ?>
        </li>
<?php endforeach; ?>
      </ul>
      <div class="checkout-total">
        <div class="checkout-total__row"><span>Pajak (10%)</span><span><?= e(format_rupiah($order['tax'])) ?></span></div>
        <div class="checkout-total__row checkout-total__row--grand"><span>Total</span><span><?= e($totalText) ?></span></div>
      </div>
<?php if (!empty($order['catatan'])): ?>
      <p class="pay-note"><strong>Catatan:</strong> <?= e($order['catatan']) ?></p>
<?php endif; ?>
    </section>

    <div class="checkout-submit">
      <a class="btn btn--block checkout-submit__btn" href="<?= e($statusUrl) ?>">Lihat Status Pembayaran</a>
    </div>
<?php endif; ?>
  </main>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
