<?php
if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
    }
}

$stok = $stok ?? [
    ['nama_menu' => 'Americano', 'harga' => 20000, 'gambar' => 'americano.png', 'stok' => 10],
    ['nama_menu' => 'Choco Waffle', 'harga' => 26000, 'gambar' => 'choco-waffle.png', 'stok' => 0],
    ['nama_menu' => 'Banana Pancake', 'harga' => 26000, 'gambar' => 'banana-pancake.png', 'stok' => 0],
    ['nama_menu' => 'Croissant Butter', 'harga' => 24000, 'gambar' => 'croissant-butter.png', 'stok' => 2],
    ['nama_menu' => 'Beef Toast', 'harga' => 26000, 'gambar' => 'beef-toast.png', 'stok' => 0],
    ['nama_menu' => 'French Fries', 'harga' => 26000, 'gambar' => 'french-fries.png', 'stok' => 0],
    ['nama_menu' => 'Brownies', 'harga' => 20000, 'gambar' => 'brownies.png', 'stok' => 12],
    ['nama_menu' => 'Hazelnut Latte', 'harga' => 26000, 'gambar' => 'hazelnut-latte.png', 'stok' => 0],
    ['nama_menu' => 'Caffe Latte', 'harga' => 26000, 'gambar' => 'caffe-latte.png', 'stok' => 0],
    ['nama_menu' => 'Lychee Tea', 'harga' => 20000, 'gambar' => 'lychee-tea.png', 'stok' => 12],
    ['nama_menu' => 'Cappuccino', 'harga' => 24000, 'gambar' => 'cappuccino.png', 'stok' => 12],
    ['nama_menu' => 'Matcha Latte', 'harga' => 24000, 'gambar' => 'matcha-latte.png', 'stok' => 4],
];

$kasirUser = $user ?? (isset($_SESSION['staf']) ? $_SESSION['staf'] : []);
$namaKasir = (string) ($kasirUser['nama'] ?? 'Jihan Amanda');

// Perhitungan Nilai Ringkasan Stok
$totalMenu = count($stok);
$totalTersedia = 0;
$totalMenipis = 0;
$totalHabis = 0;

foreach ($stok as $item) {
    $qty = (int) ($item['stok'] ?? $item['stock'] ?? 0);
    if ($qty === 0) {
        $totalHabis++;
    } elseif ($qty <= 5) {
        $totalMenipis++;
    } else {
        $totalTersedia++;
    }
}

// Format Tanggal Hari Ini dalam Bahasa Indonesia
$bulanIndo = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$tanggalHariIni = 'Hari ini, ' . date('d') . ' ' . ($bulanIndo[(int)date('n')] ?? date('M')) . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Kasir - QuickBrew</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/css-kasir/dashboard.css')) ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            /* Prefix 'tw-' supaya class Tailwind tidak bentrok dengan class CSS lama */
            prefix: 'tw-',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#5A3E2B',
                            hover:   '#452E1E',
                            light:   '#F4ECE1',
                        },
                        page:    '#FBF8F3',
                        card:    '#FFFFFF',
                        border:  '#EFE8DF',
                        muted:   '#8C7E74',
                        main:    '#2D231C',
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                    borderRadius: {
                        'xl2': '1rem',
                        'xl3': '1.5rem',
                    },
                },
            },
            /* Cegah Tailwind reset/preflight bertabrakan dgn CSS lama */
            corePlugins: {
                preflight: false,
            },
        }
    </script>
    <style>
        .top-header-divider {
            border-bottom: 1px solid #D9C8B0 !important;
        }
        header.top-header-divider {
            border-top: 0 !important;
            border-left: 0 !important;
            border-right: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;
        }
    </style>
</head>
<body class="tw-font-sans">
    <!-- ==================== SIDEBAR ==================== -->
    <aside class="kasir-sidebar tw-hidden md:tw-flex" aria-label="Navigasi kasir">
        <div class="sidebar-brand">
            <img src="<?= e(asset('images/logo.png')) ?>" alt="QuickBrew Logo" class="brand-logo-img">
            <span class="brand-title">QuickBrew</span>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= e(url('kasir')) ?>" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Pesanan</span>
            </a>
            <a href="<?= e(url('kasir-menu')) ?>" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
                    <path d="M7 2v20"></path>
                    <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
                </svg>
                <span>Menu</span>
            </a>
            <a href="<?= e(url('kasir-stok')) ?>" class="nav-link active" aria-current="page">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
                <span>Stok</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <form action="<?= e(url('logout')) ?>" method="POST" style="margin: 0; width: 100%;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="nav-link logout-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT ==================== -->
    <main class="kasir-main tw-ml-0 tw-w-full tw-max-w-none !tw-p-0 md:tw-ml-[240px]">
        <!-- ===== Top Header Bar ===== -->
        <header class="top-header-divider tw-relative tw-z-30 tw-flex tw-h-16 tw-w-full tw-items-center tw-justify-end !tw-border-b !tw-border-solid !tw-border-[#D9C8B0] tw-bg-page tw-px-8 tw-overflow-visible" style="border-bottom: 1px solid #D9C8B0 !important;">
            <div class="header-top-row tw-flex tw-items-center tw-gap-4">
                <button type="button" class="btn-icon-bell" title="Notifikasi" aria-label="Notifikasi">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="bell-badge"></span>
                </button>

                <!-- Profil Kasir dengan Dropdown -->
                <div class="profile-dropdown-wrapper tw-relative" id="profileDropdownWrapper">
                    <button type="button" class="profile-card" id="profileDropdownBtn" aria-expanded="false" title="Menu Profil Kasir">
                        <div class="profile-avatar" aria-hidden="true">
                            <?= e(strtoupper(substr($user['nama'] ?? $namaKasir, 0, 1))) ?>
                        </div>
                        <div class="profile-info">
                            <span class="profile-name"><?= e($user['nama'] ?? $namaKasir) ?></span>
                            <span class="profile-role">Kasir</span>
                        </div>
                        <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8C7E74" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <!-- Menu Dropdown Kasir -->
                    <div class="profile-dropdown-menu" id="profileDropdownMenu">
                        <a href="<?= e(url('kasir-profil')) ?>" class="dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span>Profil Akun</span>
                        </a>
                        <form action="<?= e(url('logout')) ?>" method="POST" style="margin: 0; width: 100%;">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="dropdown-item logout-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <line x1="19" y1="12" x2="5" y2="12"></line>
                                    <polyline points="12 19 5 12 12 5"></polyline>
                                </svg>
                                <span>Keluar</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- ===== Area Konten Halaman ===== -->
        <div class="tw-px-8 tw-pt-6 tw-pb-10">
            <!-- Header Halaman: Judul, Subjudul, Badge Tanggal -->
            <div class="tw-mb-6 tw-flex tw-flex-col tw-gap-3 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between">
                <div>
                    <h1 id="stok-heading" class="tw-m-0 tw-text-2xl tw-font-bold tw-text-main">Stok</h1>
                    <p class="tw-mt-1 tw-text-sm tw-text-muted">Stok unit jual per menu</p>
                </div>
                <div class="tw-self-start sm:tw-self-auto">
                    <div class="date-badge">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span><?= e($tanggalHariIni) ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Empat Kartu Ringkasan -->
            <section class="tw-grid tw-grid-cols-1 sm:tw-grid-cols-2 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-4 tw-mb-6" aria-label="Ringkasan Stok">
                <!-- Total Menu -->
                <article class="tw-rounded-2xl tw-border tw-border-border tw-bg-white tw-p-4 tw-shadow-sm">
                    <div class="tw-flex tw-items-center tw-gap-2.5">
                        <div class="tw-flex tw-h-8 tw-w-8 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-full tw-bg-brand tw-text-white" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <span class="tw-text-xs tw-font-semibold tw-text-[#5A3E2B]">Total Menu</span>
                    </div>
                    <div class="tw-mt-3 tw-text-center tw-text-3xl tw-font-bold tw-text-main"><?= e($totalMenu) ?></div>
                </article>

                <!-- Tersedia -->
                <article class="tw-rounded-2xl tw-border tw-border-border tw-bg-white tw-p-4 tw-shadow-sm">
                    <div class="tw-flex tw-items-center tw-gap-2.5">
                        <div class="tw-flex tw-h-8 tw-w-8 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-full tw-bg-brand tw-text-white" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <span class="tw-text-xs tw-font-semibold tw-text-[#5A3E2B]">Tersedia</span>
                    </div>
                    <div class="tw-mt-3 tw-text-center tw-text-3xl tw-font-bold tw-text-main"><?= e($totalTersedia) ?></div>
                </article>

                <!-- Stok Menipis -->
                <article class="tw-rounded-2xl tw-border tw-border-border tw-bg-white tw-p-4 tw-shadow-sm">
                    <div class="tw-flex tw-items-center tw-gap-2.5">
                        <div class="tw-flex tw-h-8 tw-w-8 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-full tw-bg-brand tw-text-white" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <span class="tw-text-xs tw-font-semibold tw-text-[#5A3E2B]">Stok Menipis</span>
                    </div>
                    <div class="tw-mt-3 tw-text-center tw-text-3xl tw-font-bold tw-text-main"><?= e($totalMenipis) ?></div>
                </article>

                <!-- Stok Habis -->
                <article class="tw-rounded-2xl tw-border tw-border-border tw-bg-white tw-p-4 tw-shadow-sm">
                    <div class="tw-flex tw-items-center tw-gap-2.5">
                        <div class="tw-flex tw-h-8 tw-w-8 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-full tw-bg-brand tw-text-white" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <span class="tw-text-xs tw-font-semibold tw-text-[#5A3E2B]">Stok Habis</span>
                    </div>
                    <div class="tw-mt-3 tw-text-center tw-text-3xl tw-font-bold tw-text-main"><?= e($totalHabis) ?></div>
                </article>
            </section>

            <!-- Kartu Putih Ketersediaan Menu -->
            <section class="tw-rounded-2xl tw-border tw-border-border tw-bg-white tw-p-5 sm:tw-p-6 tw-shadow-sm" aria-labelledby="ketersediaan-heading">
                <div class="tw-mb-5">
                    <h2 id="ketersediaan-heading" class="tw-m-0 tw-text-base sm:tw-text-lg tw-font-bold tw-text-main">Ketersediaan Menu</h2>
                </div>

                <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3 sm:tw-gap-4">
                    <?php foreach ($stok as $item): ?>
                        <?php
                        $namaMenu = (string) ($item['nama_menu'] ?? $item['name'] ?? '');
                        $hargaMenu = (float) ($item['harga'] ?? $item['price'] ?? 0);
                        $stokMenu = (int) ($item['stok'] ?? $item['stock'] ?? 0);
                        $gambarMenu = (string) ($item['gambar'] ?? $item['image'] ?? '');
                        $namaFileGambar = basename($gambarMenu);
                        $pathGambar = APP_ROOT . '/public/assets/images/menu/' . $namaFileGambar;
                        $gambarTersedia = $namaFileGambar !== '' && is_file($pathGambar);

                        // Status stok: 0 Habis, 1-5 Menipis, selain itu Tersedia
                        if ($stokMenu === 0) {
                            $statusLabel = 'Habis';
                            $statusClass = 'tw-bg-red-100 tw-text-red-500';
                        } elseif ($stokMenu <= 5) {
                            $statusLabel = 'Menipis';
                            $statusClass = 'tw-bg-amber-100 tw-text-amber-700';
                        } else {
                            $statusLabel = 'Tersedia';
                            $statusClass = 'tw-bg-green-100 tw-text-green-600';
                        }
                        ?>
                        <article class="tw-relative tw-flex tw-items-center tw-gap-3.5 tw-rounded-xl tw-border tw-border-border/80 tw-bg-white tw-p-3.5 tw-shadow-sm tw-transition-colors hover:tw-border-brand/40">
                            <!-- Foto Menu (56px object-contain) -->
                            <div class="tw-h-[56px] tw-w-[56px] tw-shrink-0 tw-flex tw-items-center tw-justify-center">
                                <?php if ($gambarTersedia): ?>
                                    <img src="<?= e(asset('images/menu/' . rawurlencode($namaFileGambar))) ?>" alt="<?= e($namaMenu) ?>" class="tw-h-[56px] tw-w-[56px] tw-object-contain">
                                <?php else: ?>
                                    <div class="tw-flex tw-h-[56px] tw-w-[56px] tw-items-center tw-justify-center tw-rounded-lg tw-bg-page/60" role="img" aria-label="<?= e('Gambar ' . $namaMenu . ' tidak tersedia') ?>">
                                        <svg class="tw-h-6 tw-w-6 tw-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                            <path d="m21 15-5-5L5 21"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Detail: Nama, Harga, Jumlah Pcs -->
                            <div class="tw-min-w-0 tw-flex-1 tw-pr-14">
                                <h3 class="tw-truncate tw-text-sm tw-font-semibold tw-text-main"><?= e($namaMenu) ?></h3>
                                <p class="tw-mt-0.5 tw-text-xs tw-text-muted">Rp <?= e(number_format($hargaMenu, 0, ',', '.')) ?></p>
                                <p class="tw-text-xs tw-text-muted"><?= e($stokMenu) ?> pcs</p>
                            </div>

                            <!-- Badge Status (Kanan Atas) -->
                            <span class="tw-absolute tw-right-3 tw-top-3 tw-shrink-0 tw-rounded-full tw-px-2 tw-py-0.5 tw-text-[9px] tw-font-medium <?= e($statusClass) ?>">
                                <?= e($statusLabel) ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>

    <script src="<?= e(asset('js/js-kasir/stok.js')) ?>"></script>
</body>
</html>
