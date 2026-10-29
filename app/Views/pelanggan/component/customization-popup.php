<?php
/**
 * Komponen: pop-up Customization (bottom sheet) saat tombol + pada menu ditekan.
 * Isi (nama, foto, deskripsi, harga) diisi oleh assets/js/menu.js saat dibuka.
 *
 * @param array $options Isi includes/customization-options.php
 */
require_once __DIR__ . '/../config.php';

/** Satu grup pilihan (radio) bergaya pill. */
function render_option_group(string $key, array $group): void
{
    $labelId = 'cust-label-' . $key;
    ?>
        <section class="cust-section">
          <p class="cust-section__label" id="<?= e($labelId) ?>"><?= e($group['label']) ?></p>
          <div class="option-row option-row--<?= e($group['layout']) ?> option-row--<?= e($key) ?>" role="radiogroup" aria-labelledby="<?= e($labelId) ?>">
<?php foreach ($group['options'] as $i => $label): ?>
            <label class="option">
              <input type="radio" name="<?= e($key) ?>" value="<?= e($label) ?>" data-price="<?= (int) ($group['prices'][$label] ?? 0) ?>"<?= $i === 0 ? ' checked' : '' ?>>
              <span class="option__face"><?= e($label) ?></span>
            </label>
<?php endforeach; ?>
          </div>
        </section>
<?php
}

function render_customization_popup(array $options): void
{
    ?>
  <div class="popup" data-popup hidden>
    <div class="popup__backdrop" data-popup-close></div>
    <section class="sheet" role="dialog" aria-modal="true" aria-labelledby="popup-title">
      <div class="sheet__scroll">
        <div class="sheet__head">
          <button type="button" class="sheet__back" data-popup-close aria-label="Kembali ke menu">
            <img src="<?= e(asset('icons/icon-arrow-back.svg')) ?>" alt="">
          </button>
          <h2 class="sheet__title" id="popup-title">Custom</h2>
        </div>

        <div class="product">
          <div class="product__media"><img src="" alt="" data-popup-image></div>
          <div class="product__info">
            <h3 class="product__name" data-popup-name></h3>
            <p class="product__desc" data-popup-desc></p>
            <p class="product__price" data-popup-price></p>
          </div>
        </div>

        <div class="options">
<?php
    foreach (['size', 'type', 'sugar', 'ice'] as $key) {
        render_option_group($key, $options[$key]);
    }
?>
          <section class="cust-section">
            <p class="cust-section__label" id="cust-label-addons"><?= e($options['addons']['label']) ?></p>
            <div class="addon-list" role="group" aria-labelledby="cust-label-addons">
<?php foreach ($options['addons']['items'] as $addon): ?>
              <label class="addon">
                <input type="checkbox" name="addon" value="<?= e($addon['id']) ?>" data-addon data-label="<?= e($addon['label']) ?>" data-price="<?= (int) $addon['price'] ?>">
                <span class="addon__mark"></span>
                <span class="addon__text"><?= e($addon['label']) ?> (+<?= e(format_rupiah($addon['price'], false)) ?>)</span>
              </label>
<?php endforeach; ?>
            </div>
          </section>
        </div>
      </div>

      <div class="sheet__footer">
        <div class="stepper" role="group" aria-label="Jumlah pesanan">
          <button type="button" data-popup-minus aria-label="Kurangi jumlah">
            <img src="<?= e(asset('icons/icon-qty-minus.svg')) ?>" alt="">
          </button>
          <span class="stepper__qty" data-popup-qty aria-live="polite">1</span>
          <button type="button" data-popup-plus aria-label="Tambah jumlah">
            <img src="<?= e(asset('icons/icon-qty-plus.svg')) ?>" alt="">
          </button>
        </div>
        <p class="sheet__total" data-popup-total>Total: Rp 0</p>
        <button type="button" class="btn btn--block" data-popup-submit>Tambah Keranjang</button>
      </div>
    </section>
  </div>
<?php
}
