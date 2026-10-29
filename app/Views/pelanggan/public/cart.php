<?php
/**
 * Halaman Keranjang pelanggan (isi keranjang disimpan di localStorage oleh cart-store.js).
 * Variabel dari PelangganController::cart():
 *   $tableNumber string|null  Nomor meja dari QR (?meja=<kode_qr>)
 */
$pageTitle       = 'Keranjang - ' . APP_NAME;
$pageStyles      = ['cart'];
$pageScripts     = ['cart-store', 'cart'];
$pageClass       = 'page--cart';
$withBrandHeader = false;
$withBottomBar   = false;

require __DIR__ . '/../../layouts/header.php';
require __DIR__ . '/../component/cart-header.php';
?>

  <main class="cart-main main-panel">
    <h2 class="cart-title" data-cart-count>Daftar Pesanan (0 Item)</h2>

    <ul class="cart-list" data-cart-list></ul>
    <p class="cart-empty" data-cart-empty hidden>Keranjang masih kosong</p>
  </main>

  <section class="cart-footer" aria-label="Ringkasan pembayaran">
    <div class="summary">
      <div class="summary__row">
        <span class="summary__label">Subtotal</span>
        <span class="summary__value" data-cart-subtotal>Rp 0,00</span>
      </div>
      <div class="summary__row summary__row--tax">
        <span class="summary__label">Tax (10%)</span>
        <span class="summary__value" data-cart-tax>Rp 0,00</span>
      </div>
      <hr class="summary__line">
      <div class="summary__row summary__row--total">
        <span class="summary__label">Total</span>
        <span class="summary__value" data-cart-total>Rp 0,00</span>
      </div>
    </div>

    <div class="cart-actions">
      <a class="btn btn--block btn--outline" href="<?= e(url('menu')) ?>">Tambah Item</a>
      <a class="btn btn--block" href="<?= e(url('checkout')) ?>" data-cart-checkout>Checkout</a>
    </div>
  </section>

  <div class="confirm" data-confirm hidden>
    <div class="confirm__backdrop" data-confirm-cancel></div>
    <div class="confirm__panel" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-text">
      <h2 class="confirm__title" id="confirm-title">Hapus pesanan?</h2>
      <p class="confirm__text" id="confirm-text" data-confirm-text></p>
      <div class="confirm__actions">
        <button type="button" class="btn btn--block btn--outline" data-confirm-cancel>Batal</button>
        <button type="button" class="btn btn--block" data-confirm-ok>Hapus</button>
      </div>
    </div>
  </div>

  <template id="cart-item-template">
    <li class="cart-item">
      <div class="cart-item__info">
        <h3 class="cart-item__name"><span data-name></span><span class="cart-item__qty" data-qty hidden></span></h3>
        <p class="cart-item__variants" data-variants></p>
        <p class="cart-item__addon" data-addon></p>
      </div>
      <div class="cart-item__media"><img src="" alt="" data-image></div>
      <p class="cart-item__price" data-price></p>
      <div class="cart-item__actions">
        <button type="button" class="cart-action" data-cart-edit>
          <img src="<?= e(asset('images/icons/icon-edit.svg')) ?>" alt="">
        </button>
        <button type="button" class="cart-action" data-cart-delete>
          <img src="<?= e(asset('images/icons/icon-trash.svg')) ?>" alt="">
        </button>
      </div>
    </li>
  </template>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
