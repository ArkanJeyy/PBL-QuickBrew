<?php
/**
 * Halaman Beranda pelanggan.
 * Variabel dari PelangganController::home():
 *   $favorites   array        Menu favorit (MenuModel::favorites)
 *   $tableNumber string|null  Nomor meja dari QR (?meja=<kode_qr>)
 */
require_once __DIR__ . '/../component/favorite-card.php';
require_once __DIR__ . '/../component/feature-card.php';
require_once __DIR__ . '/../component/site-footer.php';

// Teks kartu fitur disusun ulang (di gambar referensi terlalu kecil untuk dibaca) - mohon dicek.
$features = [
    ['icon' => 'bolt',    'title' => 'Proses Cepat',        'description' => 'Pesan minuman praktis tanpa harus mengantre di kasir.'],
    ['icon' => 'sliders', 'title' => 'Kustomisasi Minuman', 'description' => 'Atur ukuran, tingkat gula, es, dan topping sesuai selera.'],
    ['icon' => 'cart',    'title' => 'Keranjang Praktis',   'description' => 'Periksa pesanan, ubah pilihan, lalu lanjut checkout.'],
    ['icon' => 'list',    'title' => 'Pesanan Teratur',     'description' => 'Bukti pemesanan dan status pesanan mudah dipantau.'],
];

$pageTitle     = APP_NAME . ' - Pesan minuman favoritmu';
$pageStyles    = ['landing'];
$pageClass     = 'page--landing';
$withBottomBar = false;
$brandTable    = $tableNumber;

require __DIR__ . '/../../layouts/header.php';
?>

  <main>
    <section class="hero" aria-labelledby="hero-title">
      <img class="hero__image" src="<?= e(asset('images/hero.png')) ?>" alt="">
      <h1 class="hero__title" id="hero-title">No More Waiting,<br>Just Brewing.</h1>
      <p class="hero__text">Pesan minuman favoritmu dengan<br>mudah, cepat, dan sesuai selera</p>
      <div class="hero__cta">
        <a class="btn btn--cta" href="<?= e(url('menu')) ?>">
          <span>Pesan Sekarang</span>
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M1.67 10h16.66M12.5 4.17 18.33 10l-5.83 5.83"/>
          </svg>
        </a>
      </div>
    </section>

    <section class="features" aria-labelledby="features-title">
      <h2 class="features__title" id="features-title">Kenapa pilih <?= e(APP_NAME) ?>?</h2>
      <p class="features__subtitle">Karena kami bukan sekadar kopi, tapi pengalaman</p>
      <ul class="features__list">
<?php foreach ($features as $feature) { render_feature_card($feature); } ?>
      </ul>
    </section>

    <section class="favorites" aria-labelledby="favorites-title">
      <div class="favorites__head">
        <h2 class="favorites__title" id="favorites-title">Menu Favorit</h2>
        <a class="favorites__all" href="<?= e(url('menu')) ?>">
          <span>Semua Kategori</span>
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M1.67 10h16.66M12.5 4.17 18.33 10l-5.83 5.83"/>
          </svg>
        </a>
      </div>

      <ul class="favorites__list" tabindex="0" role="region" aria-label="Menu favorit, geser ke samping untuk melihat lainnya">
<?php foreach ($favorites as $item) { render_favorite_card($item); } ?>
      </ul>
    </section>
  </main>

<?php render_site_footer(); ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
