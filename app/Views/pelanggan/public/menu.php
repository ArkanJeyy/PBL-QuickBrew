<?php
/**
 * Halaman Menu pelanggan.
 * Variabel dari PelangganController::menu():
 *   $menuItems   array        Menu aktif dari database (MenuModel::all), sudah urut per kategori
 *   $categories  array        Opsi filter kategori ['all' => 'Semua Kategori', 'minuman' => 'Minuman', ...]
 *   $custOptions array        Isi app/Models/customization-options.php
 *   $tableNumber string|null  Nomor meja dari QR (?meja=<kode_qr>)
 */
require_once __DIR__ . '/../component/menu-item.php';
require_once __DIR__ . '/../component/customization-popup.php';

// Data untuk pencarian di sisi klien
$searchData = array_map(static fn (array $i): array => [
    'id'          => $i['id'],
    'name'        => $i['name'],
    'description' => $i['description'],
    'status'      => $i['status'],
    'price'       => format_rupiah($i['price'], false),
    'priceValue'  => $i['price'],
    'image'       => asset($i['image']),
    'dim'         => !empty($i['dim']),
    'customizable' => $i['customizable'],
], $menuItems);

$pageTitle       = 'Menu - ' . APP_NAME;
$pageStyles      = ['menu'];
$pageScripts     = ['cart-store', 'cart-badge', 'menu'];
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
          <span class="dropdown__label" data-dropdown-label><?= e($categories['all']) ?></span>
          <img class="dropdown__chevron" src="<?= e(asset('images/icons/icon-chevron-down.svg')) ?>" alt="">
        </button>
        <div class="dropdown__panel" id="category-panel" role="listbox" aria-label="Kategori menu" hidden>
<?php foreach ($categories as $value => $label):
    // Kategori baru yang belum punya ikon khusus memakai ikon "Semua Kategori"
    $icon = is_file(APP_ROOT . '/public/assets/images/icons/icon-cat-' . $value . '.svg') ? $value : 'all';
?>
          <button type="button" class="dropdown__option" role="option" data-value="<?= e($value) ?>" aria-selected="<?= $value === 'all' ? 'true' : 'false' ?>">
            <span class="dd-icon dd-icon--<?= e($icon) ?>"><img src="<?= e(asset('images/icons/icon-cat-' . $icon . '.svg')) ?>" alt=""></span>
            <span class="dropdown__option-label"><?= e($label) ?></span>
            <img class="dropdown__check" src="<?= e(asset('images/icons/icon-check.svg')) ?>" alt="">
          </button>
<?php endforeach; ?>
        </div>
      </div>
    </div>

    <ul class="menu-list" id="menu-list">
<?php
// Daftar sudah dikelompokkan per kategori oleh MenuModel::all() (ORDER BY kategori);
// item pertama tiap kategori (kecuali yang paling atas) diberi jarak ekstra.
$prevCategory = null;
foreach ($menuItems as $item) {
    render_menu_item($item, $prevCategory !== null && $prevCategory !== $item['category']);
    $prevCategory = $item['category'];
}
?>
    </ul>
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

  <script type="application/json" id="menu-data"><?= json_encode($searchData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
