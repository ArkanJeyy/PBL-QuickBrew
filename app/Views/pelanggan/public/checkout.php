<?php
/**
 * Halaman Checkout pelanggan. Ringkasan pesanan diisi checkout.js dari keranjang (localStorage);
 * harga final dihitung ulang server (PemesananModel::price) saat form dikirim.
 * Variabel dari PelangganController::checkout():
 *   $tableNumber string|null  Nomor meja dari QR
 *   $hasTable    bool         Sudah scan QR meja? (wajib untuk membuat pesanan)
 *   $old         array        Isian sebelumnya: nama, email, metode, catatan
 *   $errors      array        Pesan error: form, nama, email, metode, catatan
 *   $metodeList  array        ['qris' => 'QRIS', 'tunai' => ...]
 *   $taxRate     float        Tarif pajak (0.1)
 *   $noteMax     int          Panjang maksimal catatan (disimpan bersama catatan item pertama)
 *   $csrfToken   string       Token CSRF form
 */
$pageTitle       = 'Checkout - ' . APP_NAME;
$pageStyles      = ['cart', 'popup', 'checkout']; // popup.css: bottom sheet dialog Konfirmasi Pesanan
$pageScripts     = ['cart-store', 'checkout'];
$pageClass       = 'page--checkout';
$withBrandHeader = false;
$withBottomBar   = false;

$headerTitle = 'Checkout';
$backUrl     = url('cart');
$backLabel   = 'Kembali ke keranjang';

// Label metode di kartu pilihan (desain: "QRIS" / "Tunai")
$metodeShort = ['qris' => 'QRIS', 'tunai' => 'Tunai'];

$fieldError = static function (string $field) use ($errors): void {
    if (!empty($errors[$field])) {
        echo '<p class="field__error" id="error-' . e($field) . '">' . e($errors[$field]) . '</p>';
    }
};

require __DIR__ . '/../../layouts/header.php';
require __DIR__ . '/../component/cart-header.php';
?>

  <main class="checkout-main main-panel">
<?php if (!$hasTable): ?>
    <section class="checkout-card checkout-notice" aria-labelledby="notice-title">
      <h2 class="checkout-card__title" id="notice-title">Scan QR meja dulu</h2>
      <p class="checkout-notice__text">Pesanan diantar ke meja Anda, jadi nomor meja wajib diketahui. Silakan scan QR code yang ada di meja, lalu buka kembali keranjang.</p>
      <a class="btn btn--block" href="<?= e(url('qr')) ?>">Pilih / Scan Meja</a>
    </section>
<?php else: ?>
<?php if (!empty($errors['form'])): ?>
    <p class="checkout-alert" role="alert"><?= e($errors['form']) ?></p>
<?php endif; ?>

    <form class="checkout-form" method="post" action="<?= e(url('checkout')) ?>" data-checkout-form data-tax-rate="<?= e((string) $taxRate) ?>">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="items" value="" data-checkout-items>

      <section class="checkout-card" aria-labelledby="summary-title">
        <h2 class="checkout-card__title checkout-card__title--ruled" id="summary-title" data-checkout-count>Ringkasan Pesanan (0 Item)</h2>
        <ul class="checkout-items" data-checkout-list></ul>
        <div class="checkout-empty" data-checkout-empty hidden>
          <p>Keranjang masih kosong.</p>
          <a class="btn btn--block btn--outline" href="<?= e(url('menu')) ?>">Lihat Menu</a>
        </div>
        <div class="checkout-total" data-checkout-totals>
          <div class="checkout-total__row">
            <span>Pajak (<?= e((string) round($taxRate * 100)) ?>%)</span>
            <span data-checkout-tax>Rp 0</span>
          </div>
          <div class="checkout-total__row checkout-total__row--grand">
            <span>Total</span>
            <span data-checkout-total>Rp 0</span>
          </div>
        </div>
      </section>

      <section class="checkout-card" aria-labelledby="customer-title">
        <h2 class="checkout-card__title checkout-card__title--ruled" id="customer-title">Informasi Pemesan</h2>
        <div class="field">
          <label class="field__label" for="checkout-nama">Nama</label>
          <input class="field__input" type="text" id="checkout-nama" name="nama" value="<?= e($old['nama']) ?>"
                 placeholder="Masukkan nama" maxlength="100" autocomplete="name" required
                 <?= !empty($errors['nama']) ? 'aria-invalid="true" aria-describedby="error-nama"' : '' ?>>
<?php $fieldError('nama'); ?>
        </div>
        <div class="field">
          <label class="field__label" for="checkout-email">Email</label>
          <input class="field__input" type="email" id="checkout-email" name="email" value="<?= e($old['email']) ?>"
                 placeholder="Masukkan email" maxlength="150" autocomplete="email" inputmode="email" required
                 <?= !empty($errors['email']) ? 'aria-invalid="true" aria-describedby="error-email"' : '' ?>>
<?php $fieldError('email'); ?>
        </div>
      </section>

      <fieldset class="checkout-card checkout-payment">
        <legend class="checkout-card__title">Metode Pembayaran</legend>
        <div class="pay-options">
<?php foreach ($metodeList as $value => $label): ?>
          <label class="pay-option">
            <input type="radio" name="metode" value="<?= e($value) ?>"<?= $old['metode'] === $value ? ' checked' : '' ?> required>
            <span class="pay-option__mark" aria-hidden="true"></span>
            <span class="pay-option__label"><?= e($metodeShort[$value] ?? $label) ?></span>
          </label>
<?php endforeach; ?>
        </div>
<?php $fieldError('metode'); ?>
      </fieldset>

      <section class="checkout-card" aria-labelledby="note-title">
        <h2 class="checkout-card__title checkout-card__title--ruled" id="note-title">
          <label for="checkout-catatan">Catatan</label>
        </h2>
        <textarea class="field__input field__input--area" id="checkout-catatan" name="catatan" rows="4" maxlength="<?= (int) $noteMax ?>"
                  placeholder="Tambahkan catatan..."
                  <?= !empty($errors['catatan']) ? 'aria-invalid="true" aria-describedby="error-catatan"' : '' ?>><?= e($old['catatan']) ?></textarea>
<?php $fieldError('catatan'); ?>
      </section>

      <div class="checkout-submit">
        <button type="submit" class="btn btn--block checkout-submit__btn" data-checkout-submit>
          <span data-checkout-submit-text>Buat Pesanan</span>
          <span aria-hidden="true">|</span>
          <span data-checkout-submit-total>Rp 0</span>
        </button>
      </div>

      <!-- Dialog "Konfirmasi Pesanan": dibuka checkout.js saat "Buat Pesanan" ditekan; pesanan baru dikirim setelah dikonfirmasi -->
      <div class="popup confirm-order" data-confirm-order hidden>
        <div class="popup__backdrop" data-confirm-edit></div>
        <section class="sheet" role="dialog" aria-modal="true" aria-labelledby="confirm-order-title" aria-describedby="confirm-order-desc">
          <div class="sheet__scroll">
            <header class="confirm-order__head">
              <img class="confirm-order__icon" src="<?= e(asset('images/icons/icon-cat-minuman.svg')) ?>" alt="">
              <h2 class="confirm-order__title" id="confirm-order-title">Konfirmasi Pesanan</h2>
              <p class="confirm-order__desc" id="confirm-order-desc">Pastikan detail pesanan sudah sesuai sebelum melanjutkan</p>
            </header>

            <section class="checkout-card confirm-order__card" aria-labelledby="confirm-customer-title">
              <h3 class="checkout-card__title checkout-card__title--ruled confirm-order__card-title" id="confirm-customer-title">Informasi Pemesan</h3>
              <dl class="confirm-order__info">
                <div><dt>Pemesan</dt><dd data-confirm-nama></dd></div>
                <div><dt>Email</dt><dd data-confirm-email></dd></div>
                <div><dt>Metode Pembayaran</dt><dd data-confirm-metode></dd></div>
                <div class="confirm-order__info-note"><dt>Catatan</dt><dd data-confirm-catatan></dd></div>
              </dl>
            </section>

            <section class="checkout-card confirm-order__card" aria-labelledby="confirm-summary-title">
              <h3 class="checkout-card__title checkout-card__title--ruled" id="confirm-summary-title" data-confirm-count>Ringkasan Pesanan (0 Item)</h3>
              <ul class="checkout-items" data-confirm-list></ul>
              <div class="checkout-total">
                <div class="checkout-total__row">
                  <span>Pajak (<?= e((string) round($taxRate * 100)) ?>%)</span>
                  <span data-confirm-tax>Rp 0</span>
                </div>
                <div class="checkout-total__row checkout-total__row--grand">
                  <span>Total</span>
                  <span data-confirm-total>Rp 0</span>
                </div>
              </div>
            </section>
          </div>

          <div class="sheet__footer confirm-order__footer">
            <button type="button" class="btn btn--block" data-confirm-submit>Konfirmasi Pesanan</button>
            <button type="button" class="btn btn--block btn--outline" data-confirm-edit>Edit</button>
          </div>
        </section>
      </div>
    </form>

    <template id="checkout-item-template">
      <li class="checkout-item">
        <div class="checkout-item__row">
          <div class="checkout-item__media"><img src="" alt="" data-image></div>
          <div class="checkout-item__info">
            <p class="checkout-item__name" data-name></p>
            <p class="checkout-item__qty" data-qty></p>
          </div>
          <p class="checkout-item__price" data-price></p>
        </div>
        <p class="checkout-item__meta" data-variants></p>
        <p class="checkout-item__meta" data-addons></p>
      </li>
    </template>
<?php endif; ?>
  </main>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
