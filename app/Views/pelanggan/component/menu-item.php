<?php
/**
 * Komponen: template kartu menu (daftar utama halaman Menu).
 * Kartu dibuat menu.js dari data API GET /api/menu.php (lihat MenuController / MenuModel::map()):
 * template ini diklon per menu lalu diisi (nama, deskripsi, harga, foto, status Tersedia/Habis).
 * Menu "Habis": tombol + dan stepper dihapus, foto hitam-putih (.menu-item--soldout).
 * Tombol + berganti menjadi stepper [- n +] bila menu sudah ada di keranjang (diatur menu.js).
 */
function render_menu_item_template(): void
{
    ?>
  <template id="menu-item-template">
    <li class="menu-item" data-id="" data-category="">
      <div class="menu-item__media" data-media>
        <img src="" alt="" width="110" height="110" loading="lazy" data-image>
      </div>
      <div class="menu-item__info">
        <h2 class="menu-item__name" data-name></h2>
        <p class="menu-item__desc" data-desc></p>
        <span class="chip" data-chip></span>
        <p class="menu-item__price" data-price></p>
      </div>
      <button type="button" class="btn-add" data-menu-id="" data-add>
        <img src="<?= e(asset('images/icons/icon-plus.svg')) ?>" alt="">
      </button>
      <div class="qty-stepper" data-stepper role="group" hidden>
        <button type="button" class="qty-stepper__minus" data-stepper-minus data-menu-id="">
          <img src="<?= e(asset('images/icons/icon-qty-minus.svg')) ?>" alt="">
        </button>
        <span class="qty-stepper__count" data-stepper-count aria-live="polite">0</span>
        <button type="button" class="qty-stepper__plus" data-stepper-plus data-menu-id="">
          <img src="<?= e(asset('images/icons/icon-plus.svg')) ?>" alt="">
        </button>
      </div>
    </li>
  </template>

  <template id="menu-section-template">
    <li class="menu-section-title" data-category-heading=""><h2></h2></li>
  </template>

  <template id="menu-category-template">
    <button type="button" class="dropdown__option" role="option" data-value="" aria-selected="false">
      <span class="dd-icon"><img src="" alt=""></span>
      <span class="dropdown__option-label"></span>
      <img class="dropdown__check" src="<?= e(asset('images/icons/icon-check.svg')) ?>" alt="">
    </button>
  </template>
<?php
}
