<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/components/menu-item.php';
require_once __DIR__ . '/includes/components/customization-popup.php';

$menuItems   = require __DIR__ . '/includes/menu-data.php';
$custOptions = require __DIR__ . '/includes/customization-options.php';
$tableNumber = 5; // Sementara statis sesuai Figma. Nantinya dari QR/sesi meja.

// Opsi filter kategori (Figma: komponen Dropdown, node 251:66)
$categories = [
    'all'     => 'Semua Kategori',
    'minuman' => 'Minuman',
    'makanan' => 'Makanan',
    'dessert' => 'Dessert',
];

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

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/menu-header.php';
?>

  <main class="menu-main main-panel">
    <div class="menu-title">
      <a class="menu-title__back" href="index.php" aria-label="Kembali ke beranda">
        <img src="<?= e(asset('icons/icon-arrow-back.svg')) ?>" alt="">
      </a>
      <h1 class="menu-title__text">Menu</h1>
    </div>

    <div class="menu-tools">
      <button type="button" class="search-field" data-search-open aria-haspopup="dialog">
        <img class="search-field__icon" src="<?= e(asset('icons/icon-search.svg')) ?>" alt="">
        <span class="search-field__text">Cari menu..</span>
      </button>

      <div class="dropdown" data-dropdown>
        <button type="button" class="dropdown__trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="category-panel">
          <span class="dropdown__label" data-dropdown-label><?= e($categories['all']) ?></span>  
          <img class="dropdown__chevron" src="<?= e(asset('icons/icon-chevron-down.svg')) ?>" alt="">
        </button>
        <div class="dropdown__panel" id="category-panel" role="listbox" aria-label="Kategori menu" hidden>
<?php foreach ($categories as $value => $label): ?>
          <button type="button" class="dropdown__option" role="option" data-value="<?= e($value) ?>" aria-selected="<?= $value === 'all' ? 'true' : 'false' ?>">
            <span class="dd-icon dd-icon--<?= e($value) ?>"><img src="<?= e(asset('icons/icon-cat-' . $value . '.svg')) ?>" alt=""></span>
            <span class="dropdown__option-label"><?= e($label) ?></span>
            <img class="dropdown__check" src="<?= e(asset('icons/icon-check.svg')) ?>" alt="">
          </button>
<?php endforeach; ?>
        </div>
      </div>
    </div>

    <ul class="menu-list" id="menu-list">
<?php
// Daftar dikelompokkan per kategori agar tiap section punya jarak (urutan data asli Figma tidak mengelompokkan:
// dua menu makanan berada di akhir). Urutan di dalam kategori tetap sama seperti data.
$sectionOrder = ['minuman' => 0, 'makanan' => 1, 'dessert' => 2];
$listItems    = $menuItems;
usort($listItems, static fn (array $a, array $b): int => ($sectionOrder[$a['category']] ?? 99) <=> ($sectionOrder[$b['category']] ?? 99));
$prevCategory = null;
foreach ($listItems as $item) {
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
            <img src="<?= e(asset('icons/icon-close.svg')) ?>" alt="">
          </button>
        </div>
        <div class="search-modal__bar">
          <div class="search-modal__input-wrap">
            <img class="search-modal__input-icon" src="<?= e(asset('icons/icon-search.svg')) ?>" alt="">
            <input class="search-modal__input" type="search" placeholder="Cari menu.." autocomplete="off" aria-label="Cari menu" data-search-input>
          </div>
        </div>
        <div class="search-modal__body" data-search-body>
          <div class="search-empty" data-search-empty>
            <img class="search-empty__icon" src="<?= e(asset('icons/icon-search-empty.svg')) ?>" alt="">
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
        <img src="<?= e(asset('icons/icon-plus.svg')) ?>" alt="">
      </button>
    </li>
  </template>

  <script type="application/json" id="menu-data"><?= json_encode($searchData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
