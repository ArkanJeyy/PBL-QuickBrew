<?php
/**
 * Komponen: kartu menu favorit di landing page (foto, chip status, nama, deskripsi, harga, tombol +).
 * Struktur data item: lihat app/Models/MenuModel.php.
 * Tombol + mengarah ke halaman Menu; menu "Habis" dinonaktifkan.
 */
function render_favorite_card(array $item): void
{
    $soldOut = $item['status'] === 'soldout';
    ?>
        <li class="fav-card<?= $soldOut ? ' fav-card--soldout' : '' ?>">
          <span class="chip <?= $soldOut ? 'chip--soldout' : 'chip--available' ?>"><?= $soldOut ? 'Habis' : 'Tersedia' ?></span>
          <div class="fav-card__media">
            <img src="<?= e(asset($item['image'])) ?>" alt="" loading="lazy">
          </div>
          <h3 class="fav-card__name"><?= e($item['name']) ?></h3>
          <p class="fav-card__desc"><?= e($item['description']) ?></p>
          <div class="fav-card__foot">
            <p class="fav-card__price"><?= e(format_rupiah($item['price'])) ?></p>
            <a class="btn-add" href="<?= e(url('menu')) ?>" aria-label="Pesan <?= e($item['name']) ?>"<?= $soldOut ? ' aria-disabled="true" tabindex="-1"' : '' ?>>
              <img src="<?= e(asset('images/icons/icon-plus.svg')) ?>" alt="">
            </a>
          </div>
        </li>
<?php
}
