<?php
/**
 * Halaman Menu pelanggan. Daftar menu, judul kategori, dan pilihan kategori di dropdown dimuat
 * oleh menu.js dari API GET /api/menu.php (kartu dibuat dari <template> di component/menu-item.php).
 * Variabel dari PelangganController::menu():
 *   $menuApiUrl    string       URL API menu (mis. /public/api/menu.php)
 *   $categoryIcons string[]     Slug kategori yang punya ikon icon-cat-<slug>.svg
 *   $custOptions   array        Isi app/Models/customization-options.php (belum ada di API)
 *   $tableNumber   string|null  Nomor meja dari QR (?meja=<kode_qr>)
 *   $orders        array        Pesanan di sesi ini (kartu "Pesanan Saya"), terbaru di depan: number, url, statusLabel
 *   $unpaidOrders  array        Bagian $orders yang belum dibayar (popup peringatan saat menambah menu):
 *                               id, number, paymentUrl
 */
/** @var string   $menuApiUrl */
/** @var string[] $categoryIcons */
/** @var array    $custOptions */
/** @var ?string  $tableNumber */
/** @var array    $orders */
/** @var array    $unpaidOrders */

// Popup "belum dibayar" (memblokir tombol +): 1 pesanan -> langsung ke Pembayaran; lebih dari 1 -> ke daftar "Pesanan Saya"
$unpaidCount   = count($unpaidOrders);
$unpaidNumbers = implode(', ', array_column($unpaidOrders, 'number'));

require_once __DIR__ . '/../component/menu-item.php';
require_once __DIR__ . '/../component/order-shortcut.php';
require_once __DIR__ . '/../component/customization-popup.php';

$pageTitle       = 'Menu - ' . APP_NAME;
$pageStyles      = ['menu', 'popup'];
$pageScripts     = ['cart-store', 'cart-badge', 'customization-popup', 'menu'];
$pageClass       = 'page--menu';
$withBrandHeader = false;
$withBottomBar   = false;

require __DIR__ . '/../../layouts/header.php';
require __DIR__ . '/../component/menu-header.php';
?>

  <main class="menu-main main-panel">
    <div class="menu-title">
      <a class="menu-title__back" href="<?= e(url()) ?>" aria-label="Kembali ke beranda">
        <img src="<?= e(asset('images/icons/icon-arrow-back.svg')) ?>" alt="">
      </a>
      <h1 class="menu-title__text">Menu</h1>
    </div>

    <div class="menu-tools">
      <button type="button" class="search-field" data-search-open aria-haspopup="dialog">
        <img class="search-field__icon" src="<?= e(asset('images/icons/icon-search.svg')) ?>" alt="">
        <span class="search-field__text">Cari menu..</span>
      </button>

      <div class="dropdown" data-dropdown>
        <button type="button" class="dropdown__trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="category-panel">
          <span class="dropdown__label" data-dropdown-label>Semua Kategori</span>
          <img class="dropdown__chevron" src="<?= e(asset('images/icons/icon-chevron-down.svg')) ?>" alt="">
        </button>
        <div class="dropdown__panel" id="category-panel" role="listbox" aria-label="Kategori menu" hidden data-category-panel>
          <button type="button" class="dropdown__option" role="option" data-value="all" aria-selected="true">
            <span class="dd-icon dd-icon--all"><img src="<?= e(asset('images/icons/icon-cat-all.svg')) ?>" alt=""></span>
            <span class="dropdown__option-label">Semua Kategori</span>
            <img class="dropdown__check" src="<?= e(asset('images/icons/icon-check.svg')) ?>" alt="">
          </button>
          <!-- Kategori lain ditambahkan menu.js dari field "category" di API menu -->
        </div>
      </div>
    </div>

    <!-- Pintasan ke Status Pesanan terakhir (session), muncul setelah pelanggan membuat pesanan -->
<?php render_order_shortcut($orders); ?>

    <!-- Keadaan memuat / gagal memuat menu dari API -->
    <div class="menu-state" data-menu-state role="status" aria-live="polite">
      <p class="menu-state__text" data-menu-state-text>Memuat menu…</p>
      <button type="button" class="btn btn--outline menu-state__retry" data-menu-retry hidden>Coba lagi</button>
    </div>

    <!-- Diisi menu.js dari API: judul kategori + kartu menu, urut per kategori -->
    <ul class="menu-list" id="menu-list"
        data-api-url="<?= e($menuApiUrl) ?>"
        data-asset-base="<?= e(asset('')) ?>"
        data-category-icons="<?= e(implode(',', $categoryIcons)) ?>"></ul>
  </main>

  <div class="toast-region" role="status" aria-live="polite" data-toast-region></div>

  <div class="overlay" data-dropdown-overlay hidden></div>

  <div class="search-modal" data-search-modal hidden>
    <div class="search-modal__backdrop" data-search-close></div>
    <div class="search-modal__inner">
      <div class="search-modal__panel" role="dialog" aria-modal="true" aria-labelledby="search-modal-title">
        <div class="search-modal__head">
          <h2 class="search-modal__title" id="search-modal-title">Cari Menu</h2>
          <button type="button" class="search-modal__close" data-search-close aria-label="Tutup pencarian">
            <img src="<?= e(asset('images/icons/icon-close.svg')) ?>" alt="">
          </button>
        </div>
        <div class="search-modal__bar">
          <div class="search-modal__input-wrap">
            <img class="search-modal__input-icon" src="<?= e(asset('images/icons/icon-search.svg')) ?>" alt="">
            <input class="search-modal__input" type="search" placeholder="Cari menu.." autocomplete="off" aria-label="Cari menu" data-search-input>
          </div>
        </div>
        <div class="search-modal__body" data-search-body>
          <div class="search-empty" data-search-empty>
            <img class="search-empty__icon" src="<?= e(asset('images/icons/icon-search-empty.svg')) ?>" alt="">
            <p class="search-empty__text" data-search-empty-text>Ketik untuk mencari menu</p>
          </div>
          <ul class="search-results" data-search-results hidden></ul>
        </div>
      </div>
    </div>
  </div>

<?php if ($unpaidCount > 0): ?>
  <!-- Blokir: pesanan sebelumnya belum dibayar -> tombol + membuka dialog ini, menu tidak ditambahkan -->
  <div class="confirm" data-unpaid hidden>
    <div class="confirm__backdrop" data-unpaid-close></div>
    <div class="confirm__panel" role="alertdialog" aria-modal="true" aria-labelledby="unpaid-title" aria-describedby="unpaid-text">
      <h2 class="confirm__title" id="unpaid-title">Selesaikan pembayaran dulu</h2>
      <p class="confirm__text" id="unpaid-text">Pesanan <?= e($unpaidNumbers) ?> belum dibayar. Selesaikan pembayarannya terlebih dahulu sebelum menambah menu baru.</p>
      <div class="confirm__actions confirm__actions--stacked">
<?php if ($unpaidCount === 1): ?>
        <a class="btn btn--block" href="<?= e($unpaidOrders[0]['paymentUrl']) ?>">Selesaikan Pembayaran</a>
<?php else: ?>
        <a class="btn btn--block" href="#pesanan-saya" data-unpaid-list>Lihat Pesanan Saya</a>
<?php endif; ?>
        <button type="button" class="btn btn--block btn--outline" data-unpaid-close>Tutup</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php render_customization_popup($custOptions); ?>

  <template id="search-result-template">
    <li class="search-result">
      <div class="search-result__media"><img src="" alt=""></div>
      <div class="search-result__info">
        <h3 class="search-result__name"></h3>
        <p class="search-result__desc"></p>
        <span class="chip chip--small"></span>
        <p class="search-result__price"></p>
      </div>
      <button type="button" class="btn-add">
        <img src="<?= e(asset('images/icons/icon-plus.svg')) ?>" alt="">
      </button>
    </li>
  </template>

<?php render_menu_item_template(); ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
