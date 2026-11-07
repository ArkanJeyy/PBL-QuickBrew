<?php
/**
 * Komponen: daftar item pesanan + total (data dari PemesananModel::find()).
 * Dipakai halaman Pembayaran dan Status Pesanan. Gaya: checkout.css (.checkout-items, .checkout-total).
 *
 * @param array $order        Hasil PemesananModel::find()
 * @param bool  $withSubtotal Tampilkan baris Subtotal sebelum Pajak
 */
function render_order_summary(array $order, bool $withSubtotal = false): void
{
    ?>
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
<?php if ($withSubtotal): ?>
        <div class="checkout-total__row"><span>Subtotal</span><span><?= e(format_rupiah($order['subtotal'])) ?></span></div>
<?php endif; ?>
        <div class="checkout-total__row"><span>Pajak (10%)</span><span><?= e(format_rupiah($order['tax'])) ?></span></div>
        <div class="checkout-total__row checkout-total__row--grand"><span>Total</span><span><?= e(format_rupiah($order['total_harga'])) ?></span></div>
      </div>
<?php if (!empty($order['catatan'])): ?>
      <p class="pay-note"><strong>Catatan:</strong> <?= e($order['catatan']) ?></p>
<?php endif; ?>
<?php
}
