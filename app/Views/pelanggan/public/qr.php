<?php
/**
 * Halaman Testing QR Meja (?page=qr).
 * Menampilkan QR code tiap meja (dari tabel meja) + tombol simulasi scan langsung di browser.
 *
 * Variabel dari PelangganController::qr():
 *   $tables      array        Daftar meja: [['id' => ..., 'no_meja' => '1', 'kode_qr' => 'MEJA01'], ...]
 *   $tableNumber string|null  Nomor meja aktif saat ini (dari session)
 *   $originUrl   string       Base URL lengkap untuk membuat payload QR (mis. http://localhost/PBL/PBL-QuickBrew/public)
 */
require_once APP_ROOT . '/app/Helpers/QrSvg.php';
require_once __DIR__ . '/../component/site-footer.php';

$pageTitle       = 'Testing QR Meja - ' . APP_NAME;
$pageStyles      = ['landing', 'qr'];
$pageClass       = 'page--qr';
$withBottomBar   = false;
$brandTable      = $tableNumber;

require __DIR__ . '/../../layouts/header.php';
?>

  <main class="qr-main">
    <div class="qr-head">
      <a class="qr-head__back" href="<?= e(url('menu')) ?>" aria-label="Kembali ke menu">
        <img src="<?= e(asset('images/icons/icon-arrow-back.svg')) ?>" alt="">
      </a>
      <div>
        <h1 class="qr-head__title">Testing QR Code Meja</h1>
        <p class="qr-head__sub">Scan QR di bawah memakai kamera HP, atau klik <strong>Simulasi Scan</strong> untuk menguji perubahan tag meja di header.</p>
      </div>
    </div>

    <section class="qr-status" aria-live="polite">
      <div class="qr-status__info">
        <span class="qr-status__label">Status Meja di Header Saat Ini:</span>
<?php if ($tableNumber !== null && $tableNumber !== ''): ?>
        <strong class="qr-status__value">Meja <?= e($tableNumber) ?> (aktif di sesi)</strong>
<?php else: ?>
        <strong class="qr-status__value qr-status__value--empty">Belum ada meja dipilih</strong>
<?php endif; ?>
      </div>
<?php if ($tableNumber !== null && $tableNumber !== ''): ?>
      <a class="qr-reset" href="<?= e(url('qr', ['meja' => 'reset'])) ?>">Reset Meja</a>
<?php endif; ?>
    </section>

    <ul class="qr-grid">
<?php foreach ($tables as $t):
    $isActive = $tableNumber !== null && (string) $tableNumber === (string) $t['no_meja'];
    $scanUrl  = $originUrl . '/?page=menu&meja=' . rawurlencode($t['kode_qr']);
    $homeUrl  = url('home', ['meja' => $t['kode_qr']]);
    $menuUrl  = url('menu', ['meja' => $t['kode_qr']]);
?>
      <li class="qr-card<?= $isActive ? ' qr-card--active' : '' ?>">
        <div class="qr-card__top">
          <span class="qr-card__table">Meja <?= e($t['no_meja']) ?></span>
          <code class="qr-card__code"><?= e($t['kode_qr']) ?></code>
        </div>
        <div class="qr-card__box">
          <?= QrSvg::svg($scanUrl, 2) ?>
        </div>
        <p class="qr-card__url"><?= e($scanUrl) ?></p>
        <div class="qr-card__actions">
          <a class="btn btn--block" href="<?= e($menuUrl) ?>">Simulasi Scan &rarr; Menu</a>
          <a class="btn btn--block btn--outline" href="<?= e($homeUrl) ?>">Simulasi Scan &rarr; Beranda</a>
        </div>
      </li>
<?php endforeach; ?>
    </ul>
  </main>

<?php render_site_footer(); ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>

