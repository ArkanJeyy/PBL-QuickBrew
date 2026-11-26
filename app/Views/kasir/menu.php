<?php
if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
    }
}

$menu = $menu ?? [
    ['nama_menu' => 'Americano', 'nama_kategori' => 'Minuman', 'harga' => 20000, 'gambar' => 'americano.png', 'stok' => 12, 'aktif' => true],
    ['nama_menu' => 'Caramel Frappe', 'nama_kategori' => 'Minuman', 'harga' => 24000, 'gambar' => 'caramel-frappe.png', 'stok' => 8, 'aktif' => true],
    ['nama_menu' => 'Matcha Latte', 'nama_kategori' => 'Minuman', 'harga' => 24000, 'gambar' => 'matcha-latte.png', 'stok' => 0, 'aktif' => true],
    ['nama_menu' => 'Peach Tea', 'nama_kategori' => 'Minuman', 'harga' => 19000, 'gambar' => 'peach-tea.png', 'stok' => 5, 'aktif' => true],
    ['nama_menu' => 'Chicken Croffle', 'nama_kategori' => 'Makanan', 'harga' => 20000, 'gambar' => 'chicken-croffle.png', 'stok' => 10, 'aktif' => true],
    ['nama_menu' => 'French Fries', 'nama_kategori' => 'Makanan', 'harga' => 26000, 'gambar' => 'french-fries.png', 'stok' => 0, 'aktif' => true],
    ['nama_menu' => 'Spaghetti Carbonara', 'nama_kategori' => 'Makanan', 'harga' => 24000, 'gambar' => 'spaghetti-carbonara.png', 'stok' => 7, 'aktif' => true],
    ['nama_menu' => 'Chicken Sandwich', 'nama_kategori' => 'Makanan', 'harga' => 19000, 'gambar' => 'chicken-sandwich.png', 'stok' => 4, 'aktif' => true],
    ['nama_menu' => 'Brownies', 'nama_kategori' => 'Dessert', 'harga' => 20000, 'gambar' => 'brownies.png', 'stok' => 6, 'aktif' => true],
    ['nama_menu' => 'Cheesecake', 'nama_kategori' => 'Dessert', 'harga' => 19000, 'gambar' => 'cheesecake.png', 'stok' => 2, 'aktif' => true],
    ['nama_menu' => 'Banana Pancake', 'nama_kategori' => 'Dessert', 'harga' => 26000, 'gambar' => 'banana-pancake.png', 'stok' => 0, 'aktif' => true],
    ['nama_menu' => 'Choco Cake', 'nama_kategori' => 'Dessert', 'harga' => 24000, 'gambar' => 'choco-cake.png', 'stok' => 0, 'aktif' => false],
];

$kasirUser = $user ?? (isset($_SESSION['staf']) ? $_SESSION['staf'] : []);
$namaKasir = (string) ($kasirUser['nama'] ?? 'Jihan Amanda');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Kasir - QuickBrew</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('css/css-kasir/dashboard.css'), ENT_QUOTES, 'UTF-8') ?>">

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
        #menuSearch {
            appearance: none;
            -webkit-appearance: none;
            outline: none;
            border: 1px solid #c9a98f !important;
            box-shadow: none !important;
        }
        #menuSearch:focus {
            outline: none !important;
            border-color: #5A3E2B !important;
            box-shadow: none !important;
        }
        #menuSearch::-webkit-search-decoration,
        #menuSearch::-webkit-search-cancel-button,
        #menuSearch::-webkit-search-results-button,
        #menuSearch::-webkit-search-results-decoration {
            -webkit-appearance: none;
        }
        .menu-filter {
            appearance: none;
            -webkit-appearance: none;
            outline: none;
            border-style: solid !important;
            border-width: 1px !important;
            box-shadow: none !important;
        }
        .menu-filter[aria-pressed="true"] {
            border-color: #5A3E2B !important;
            background-color: #5A3E2B !important;
            color: #ffffff !important;
        }
        .menu-filter[aria-pressed="false"] {
            border-color: #c9a98f !important;
            background-color: #ffffff !important;
            color: #5A3E2B !important;
        }
        .menu-filter[aria-pressed="false"]:hover {
            background-color: #F4ECE1 !important;
        }
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
            <a href="<?= e(url('kasir-pesanan')) ?>" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Pesanan</span>
            </a>
            <a href="<?= e(url('kasir-menu')) ?>" class="nav-link active" aria-current="page">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
                    <path d="M7 2v20"></path>
                    <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
                </svg>
                <span>Menu</span>
            </a>
            <a href="<?= e(url('kasir-stok')) ?>" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
                <span>Stok</span>
            </a>
        </nav>
    </aside>

    <main class="kasir-main tw-ml-0 tw-w-full tw-max-w-none !tw-p-0 md:tw-ml-[240px]">
        <!-- ===== Top Header Bar ===== -->
        <header class="top-header-divider tw-relative tw-z-30 tw-flex tw-h-16 tw-w-full tw-items-center tw-justify-end !tw-border-b !tw-border-solid !tw-border-[#D9C8B0] tw-bg-page tw-px-8 tw-overflow-visible" style="border-bottom: 1px solid #D9C8B0 !important;">
            <div class="header-top-row tw-flex tw-items-center tw-gap-4">
                <button type="button" class="btn-icon-bell" title="Notifikasi">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="bell-badge"></span>
                </button>

                <!-- Profil Kasir dengan Dropdown -->
                <div class="profile-dropdown-wrapper tw-relative" id="profileDropdownWrapper">
                    <button type="button" class="profile-card" id="profileDropdownBtn" aria-expanded="false" title="Menu Profil Kasir">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($user['nama'] ?? $namaKasir, 0, 1)) ?>
                        </div>
                        <div class="profile-info">
                            <span class="profile-name"><?= e($user['nama'] ?? $namaKasir) ?></span>
                            <span class="profile-role">Kasir</span>
                        </div>
                        <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8C7E74" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <!-- Menu Dropdown Kasir -->
                    <div class="profile-dropdown-menu" id="profileDropdownMenu">
                        <a href="<?= e(url('kasir-profil')) ?>" class="dropdown-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span>Profil Akun</span>
                        </a>
                        <form action="<?= e(url('logout')) ?>" method="POST" style="margin: 0; width: 100%;">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="dropdown-item logout-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

        <div class="tw-px-8 tw-pt-6 tw-pb-10">
            <section aria-labelledby="menu-heading">
                <div class="tw-mb-5">
                    <h1 id="menu-heading" class="tw-m-0 tw-text-2xl tw-font-bold tw-text-main">Menu</h1>
                    <p class="tw-mt-1 tw-text-sm tw-text-muted">Pantau menu dan ketersediaan di kafe.</p>
                </div>

            <div class="tw-mb-5 tw-flex tw-flex-wrap tw-items-center tw-gap-3">
                <label class="tw-relative tw-block tw-w-full sm:tw-w-64">
                    <span class="tw-sr-only">Cari menu</span>
                    <svg class="tw-pointer-events-none tw-absolute tw-left-3 tw-top-1/2 tw-h-4 tw-w-4 -tw-translate-y-1/2 tw-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input id="menuSearch" type="search" placeholder="Cari menu..." autocomplete="off" class="tw-w-full tw-appearance-none tw-outline-none tw-rounded-full tw-border tw-border-solid tw-border-[#c9a98f] tw-bg-white tw-py-1.5 tw-pl-9 tw-pr-4 tw-text-xs sm:tw-text-sm tw-text-main placeholder:tw-text-muted focus:tw-border-brand focus:tw-outline-none focus:tw-ring-0 appearance-none outline-none border border-solid border-[#c9a98f]">
                </label>

                <div class="tw-flex tw-flex-wrap tw-gap-2" role="group" aria-label="Filter kategori menu">
                    <button type="button" class="menu-filter tw-appearance-none tw-outline-none tw-rounded-full tw-border tw-border-solid tw-border-brand tw-bg-brand tw-px-4 tw-py-1.5 tw-text-xs sm:tw-text-sm tw-font-medium tw-text-white tw-transition-colors appearance-none outline-none border border-solid border-brand" data-kategori="semua" aria-pressed="true">Semua</button>
                    <button type="button" class="menu-filter tw-appearance-none tw-outline-none tw-rounded-full tw-border tw-border-solid tw-border-[#c9a98f] tw-bg-white tw-px-4 tw-py-1.5 tw-text-xs sm:tw-text-sm tw-font-medium tw-text-brand tw-transition-colors hover:tw-bg-brand-light appearance-none outline-none border border-solid border-[#c9a98f]" data-kategori="makanan" aria-pressed="false">Makanan</button>
                    <button type="button" class="menu-filter tw-appearance-none tw-outline-none tw-rounded-full tw-border tw-border-solid tw-border-[#c9a98f] tw-bg-white tw-px-4 tw-py-1.5 tw-text-xs sm:tw-text-sm tw-font-medium tw-text-brand tw-transition-colors hover:tw-bg-brand-light appearance-none outline-none border border-solid border-[#c9a98f]" data-kategori="minuman" aria-pressed="false">Minuman</button>
                    <button type="button" class="menu-filter tw-appearance-none tw-outline-none tw-rounded-full tw-border tw-border-solid tw-border-[#c9a98f] tw-bg-white tw-px-4 tw-py-1.5 tw-text-xs sm:tw-text-sm tw-font-medium tw-text-brand tw-transition-colors hover:tw-bg-brand-light appearance-none outline-none border border-solid border-[#c9a98f]" data-kategori="dessert" aria-pressed="false">Dessert</button>
                </div>
            </div>

            <div id="menuGrid" class="tw-grid tw-grid-cols-1 tw-gap-4 sm:tw-grid-cols-2 md:tw-grid-cols-2 lg:tw-grid-cols-4" aria-live="polite">
                <?php foreach ($menu as $item): ?>
                    <?php
                    $namaMenu = (string) ($item['nama_menu'] ?? $item['name'] ?? '');
                    $kategoriMenu = (string) ($item['nama_kategori'] ?? $item['category'] ?? '');
                    $kategoriSlug = strtolower(trim($kategoriMenu));
                    $kategoriLabel = ucfirst($kategoriMenu);
                    $hargaMenu = (float) ($item['harga'] ?? $item['price'] ?? 0);
                    $stokMenu = (int) ($item['stok'] ?? $item['stock'] ?? 0);
                    $aktifMenu = filter_var($item['aktif'] ?? $item['active'] ?? true, FILTER_VALIDATE_BOOLEAN);
                    $tersedia = $stokMenu > 0 && $aktifMenu;
                    $gambarMenu = (string) ($item['gambar'] ?? $item['image'] ?? '');
                    $namaFileGambar = basename($gambarMenu);
                    $pathGambar = APP_ROOT . '/public/assets/images/menu/' . $namaFileGambar;
                    $gambarTersedia = $namaFileGambar !== '' && is_file($pathGambar);
                    ?>
                    <article class="menu-card tw-relative tw-min-w-0 tw-rounded-2xl tw-border tw-border-border tw-bg-white tw-p-3.5 tw-shadow-sm" data-kategori="<?= htmlspecialchars($kategoriSlug, ENT_QUOTES, 'UTF-8') ?>" data-nama="<?= htmlspecialchars(strtolower($namaMenu), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="tw-mb-2.5 tw-flex tw-items-start tw-justify-between">
                            <div class="tw-h-[72px] tw-w-[72px] tw-shrink-0 tw-bg-transparent">
                                <?php if ($gambarTersedia): ?>
                                    <img src="<?= htmlspecialchars(asset('images/menu/' . rawurlencode($namaFileGambar)), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($namaMenu, ENT_QUOTES, 'UTF-8') ?>" class="tw-h-full tw-w-full tw-object-contain">
                                <?php else: ?>
                                    <div class="tw-flex tw-h-full tw-w-full tw-items-center tw-justify-center tw-bg-transparent" role="img" aria-label="<?= htmlspecialchars('Gambar ' . $namaMenu . ' tidak tersedia', ENT_QUOTES, 'UTF-8') ?>">
                                        <svg class="tw-h-8 tw-w-8 tw-text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                            <path d="m21 15-5-5L5 21"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <!-- TODO: Tambahkan aksi ubah/hapus menu. -->
                            <button type="button" class="tw-rounded-full tw-border-0 tw-bg-transparent tw-p-1 tw-text-muted hover:tw-bg-brand-light hover:tw-text-brand" aria-label="<?= htmlspecialchars('Aksi untuk ' . $namaMenu, ENT_QUOTES, 'UTF-8') ?>">
                                <svg class="tw-h-4 tw-w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <circle cx="12" cy="5" r="1.7"></circle>
                                    <circle cx="12" cy="12" r="1.7"></circle>
                                    <circle cx="12" cy="19" r="1.7"></circle>
                                </svg>
                            </button>
                        </div>
                        <div class="tw-flex tw-items-end tw-justify-between tw-gap-2">
                            <div class="tw-min-w-0">
                                <h2 class="tw-truncate tw-text-sm tw-font-semibold tw-text-main"><?= htmlspecialchars($namaMenu, ENT_QUOTES, 'UTF-8') ?></h2>
                                <p class="tw-mt-1 tw-text-xs tw-text-muted">Rp <?= htmlspecialchars(number_format($hargaMenu, 0, ',', '.'), ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="tw-sr-only"><?= htmlspecialchars($kategoriLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <span class="tw-shrink-0 tw-rounded-full tw-px-2 tw-py-0.5 tw-text-[9px] tw-font-medium <?= htmlspecialchars($tersedia ? 'tw-bg-green-100 tw-text-green-600' : 'tw-bg-red-100 tw-text-red-500', ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($tersedia ? 'Tersedia' : 'Habis', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p id="menuEmptyState" class="tw-hidden tw-py-12 tw-text-center tw-text-sm tw-text-muted" role="status">Menu tidak ditemukan</p>
        </section>
    </div>
    </main>

    <script src="<?= htmlspecialchars(asset('js/js-kasir/menu.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
