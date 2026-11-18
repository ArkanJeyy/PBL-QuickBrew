<?php
/**
 * Komponen: kartu "Pesanan Saya" ke halaman Status Pesanan, satu kartu per pesanan di session (terbaru di atas).
 * Dipakai di Beranda dan Menu agar pelanggan selalu bisa kembali ke pesanannya, termasuk pesanan lama
 * yang belum dibayar setelah ia membuat pesanan baru.
 * [Asumsi] tidak ada di desain; gaya mengikuti kartu & tombol yang ada (components.css).
 *
 * @param array $orders Hasil PelangganController::myOrders(): number, url, statusLabel; kosong = tidak ditampilkan
 */
function render_order_shortcut(array $orders): void
{
    if (!$orders) return;
    ?>
    <div class="order-shortcuts" id="pesanan-saya">
<?php foreach ($orders as $order): ?>
      <a class="order-shortcut" href="<?= e($order['url']) ?>">
        <span class="order-shortcut__text">
          <span class="order-shortcut__label">Pesanan Saya<?= $order['statusLabel'] !== '' ? ' · ' . e($order['statusLabel']) : '' ?></span>
          <span class="order-shortcut__number"><?= e($order['number']) ?></span>
        </span>
        <span class="order-shortcut__action">Lihat Status</span>
      </a>
<?php endforeach; ?>
    </div>
<?php
}
