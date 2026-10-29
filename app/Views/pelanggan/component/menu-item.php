<?php
/**
 * Komponen: kartu menu (daftar utama halaman Menu).
 * Struktur data item: lihat includes/menu-data.php.
 * $sectionStart: item pertama sebuah section kategori (diberi jarak ekstra di atasnya).
 * Tombol + berganti menjadi stepper [- n +] bila menu sudah ada di keranjang (diatur menu.js).
 */
require_once __DIR__ . '/../config.php';

function render_menu_item(array $item, bool $sectionStart = false): void
{
    $soldOut = $item['status'] === 'soldout';
    ?>
        <li class="menu-item<?= $soldOut ? ' menu-item--soldout' : '' ?><?= $sectionStart ? ' menu-item--section-start' : '' ?>" data-id="<?= e($item['id']) ?>" data-category="<?= e($item['category']) ?>">
          <div class="menu-item__media<?= !empty($item['dim']) ? ' menu-item__media--dim' : '' ?>">
            <img src="<?= e(asset($item['image'])) ?>" alt="" width="110" height="110" loading="lazy">
          </div>
          <div class="menu-item__info">
            <h2 class="menu-item__name"><?= e($item['name']) ?></h2>
            <p class="menu-item__desc"><?= e($item['description']) ?></p>
            <span class="chip <?= $soldOut ? 'chip--soldout' : 'chip--available' ?>"><?= $soldOut ? 'Habis' : 'Tersedia' ?></span>
            <p class="menu-item__price"><?= e(format_rupiah($item['price'])) ?></p>
          </div>
          <button type="button" class="btn-add" data-menu-id="<?= e($item['id']) ?>" aria-label="Tambah <?= e($item['name']) ?>"<?= $soldOut ? ' aria-disabled="true"' : '' ?>>
            <img src="<?= e(asset('icons/icon-plus.svg')) ?>" alt="">
          </button>
<?php if (!$soldOut): ?>
          <div class="qty-stepper" data-stepper role="group" aria-label="Jumlah <?= e($item['name']) ?>" hidden>
            <button type="button" class="qty-stepper__minus" data-stepper-minus data-menu-id="<?= e($item['id']) ?>" aria-label="Kurangi <?= e($item['name']) ?>">
              <img src="<?= e(asset('icons/icon-qty-minus.svg')) ?>" alt="">
            </button>
            <span class="qty-stepper__count" data-stepper-count aria-live="polite">0</span>
            <button type="button" class="qty-stepper__plus" data-stepper-plus data-menu-id="<?= e($item['id']) ?>" aria-label="Tambah <?= e($item['name']) ?>">
              <img src="<?= e(asset('icons/icon-plus.svg')) ?>" alt="">
            </button>
          </div>
<?php endif; ?>
        </li>
<?php
}
