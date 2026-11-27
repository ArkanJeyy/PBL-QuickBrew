<?php
if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
    }
}

$menu = $menu ?? [
    ['nama_menu' => 'Americano', 'nama_kategori' => 'Minuman', 'harga' => 20000, 'gambar' => 'americano.png', 'stok' => 12, 'aktif' => true, 'deskripsi' => 'Espresso dengan air panas yang kaya aroma dan cita rasa kopi autentik.'],
    ['nama_menu' => 'Caramel Frappe', 'nama_kategori' => 'Minuman', 'harga' => 24000, 'gambar' => 'caramel-frappe.png', 'stok' => 8, 'aktif' => true, 'deskripsi' => 'Kopi blended dengan susu segar, sirup karamel manis, dan saus karamel lezat.'],
    ['nama_menu' => 'Matcha Latte', 'nama_kategori' => 'Minuman', 'harga' => 24000, 'gambar' => 'matcha-latte.png', 'stok' => 0, 'aktif' => true, 'deskripsi' => 'Matcha khas Jepang berpadu dengan susu segar yang lembut dan creamy.'],
    ['nama_menu' => 'Peach Tea', 'nama_kategori' => 'Minuman', 'harga' => 19000, 'gambar' => 'peach-tea.png', 'stok' => 5, 'aktif' => true, 'deskripsi' => 'Teh segar pilihan dengan perisa buah persik manis dan potongan buah.'],
    ['nama_menu' => 'Chicken Croffle', 'nama_kategori' => 'Makanan', 'harga' => 20000, 'gambar' => 'chicken-croffle.png', 'stok' => 10, 'aktif' => true, 'deskripsi' => 'Croffle renyah disajikan dengan potongan daging ayam gurih dan saus spesial.'],
    ['nama_menu' => 'French Fries', 'nama_kategori' => 'Makanan', 'harga' => 26000, 'gambar' => 'french-fries.png', 'stok' => 0, 'aktif' => true, 'deskripsi' => 'Kentang goreng gurih keemasan dengan taburan bumbu rempah pilihan.'],
    ['nama_menu' => 'Spaghetti Carbonara', 'nama_kategori' => 'Makanan', 'harga' => 24000, 'gambar' => 'spaghetti-carbonara.png', 'stok' => 7, 'aktif' => true, 'deskripsi' => 'Pasta spaghetti dengan saus krim carbonara gurih, keju parmesan, dan daging asap.'],
    ['nama_menu' => 'Chicken Sandwich', 'nama_kategori' => 'Makanan', 'harga' => 19000, 'gambar' => 'chicken-sandwich.png', 'stok' => 4, 'aktif' => true, 'deskripsi' => 'Roti panggang lapis ayam fillet renyah, sayuran segar, dan saus mayones lezat.'],
    ['nama_menu' => 'Brownies', 'nama_kategori' => 'Dessert', 'harga' => 20000, 'gambar' => 'brownies.png', 'stok' => 6, 'aktif' => true, 'deskripsi' => 'Kue cokelat panggang fudgy lembut dengan lelehan cokelat pekat di dalamnya.'],
    ['nama_menu' => 'Cheesecake', 'nama_kategori' => 'Dessert', 'harga' => 19000, 'gambar' => 'cheesecake.png', 'stok' => 2, 'aktif' => true, 'deskripsi' => 'Kue keju lembut creamy dengan lapisan biskuit renyah di bagian dasar.'],
    ['nama_menu' => 'Banana Pancake', 'nama_kategori' => 'Dessert', 'harga' => 26000, 'gambar' => 'banana-pancake.png', 'stok' => 0, 'aktif' => true, 'deskripsi' => 'Pancake hangat empuk disajikan dengan potongan pisang segar dan sirup maple.'],
    ['nama_menu' => 'Choco Cake', 'nama_kategori' => 'Dessert', 'harga' => 24000, 'gambar' => 'choco-cake.png', 'stok' => 0, 'aktif' => false, 'deskripsi' => 'Kue bolu cokelat berlapis krim cokelat lembut dan taburan cokelat serut.'],
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
                    $urlGambar = $gambarTersedia ? asset('images/menu/' . rawurlencode($namaFileGambar)) : '';
                    $deskripsiMenu = trim((string) ($item['deskripsi'] ?? $item['description'] ?? ''));
                    if ($deskripsiMenu === '') {
                        $deskripsiMenu = 'Belum ada deskripsi.';
                    }
                    ?>
                    <article class="menu-card tw-relative tw-min-w-0 tw-rounded-2xl tw-border tw-border-border tw-bg-white tw-p-3.5 tw-shadow-sm" data-kategori="<?= e($kategoriSlug) ?>" data-nama="<?= e(strtolower($namaMenu)) ?>">
                        <div class="tw-mb-2.5 tw-flex tw-items-start tw-justify-between">
                            <div class="tw-h-[72px] tw-w-[72px] tw-shrink-0 tw-bg-transparent">
                                <?php if ($gambarTersedia): ?>
                                    <img src="<?= e($urlGambar) ?>" alt="<?= e($namaMenu) ?>" class="tw-h-full tw-w-full tw-object-contain">
                                <?php else: ?>
                                    <div class="tw-flex tw-h-full tw-w-full tw-items-center tw-justify-center tw-bg-transparent" role="img" aria-label="<?= e('Gambar ' . $namaMenu . ' tidak tersedia') ?>">
                                        <svg class="tw-h-8 tw-w-8 tw-text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                            <path d="m21 15-5-5L5 21"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <!-- Tombol Titik Tiga: Pop Up Detail Menu (Read-Only) -->
                            <button type="button" 
                                class="menu-detail-btn tw-rounded-full tw-border-0 tw-bg-transparent tw-p-1 tw-text-muted hover:tw-bg-brand-light hover:tw-text-brand tw-transition-colors" 
                                aria-label="Lihat detail <?= e($namaMenu) ?>" 
                                aria-haspopup="dialog"
                                data-nama="<?= e($namaMenu) ?>"
                                data-kategori="<?= e($kategoriLabel) ?>"
                                data-kategori-slug="<?= e($kategoriSlug) ?>"
                                data-harga="<?= e($hargaMenu) ?>"
                                data-gambar="<?= e($namaFileGambar) ?>"
                                data-gambar-url="<?= e($urlGambar) ?>"
                                data-deskripsi="<?= e($deskripsiMenu) ?>"
                                data-stok="<?= e($stokMenu) ?>"
                                data-aktif="<?= e($aktifMenu ? '1' : '0') ?>"
                                data-tersedia="<?= e($tersedia ? '1' : '0') ?>">
                                <svg class="tw-h-4 tw-w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <circle cx="12" cy="5" r="1.7"></circle>
                                    <circle cx="12" cy="12" r="1.7"></circle>
                                    <circle cx="12" cy="19" r="1.7"></circle>
                                </svg>
                            </button>
                        </div>
                        <div class="tw-flex tw-items-end tw-justify-between tw-gap-2">
                            <div class="tw-min-w-0">
                                <h2 class="tw-truncate tw-text-sm tw-font-semibold tw-text-main"><?= e($namaMenu) ?></h2>
                                <p class="tw-mt-1 tw-text-xs tw-text-muted">Rp <?= e(number_format($hargaMenu, 0, ',', '.')) ?></p>
                                <span class="tw-sr-only"><?= e($kategoriLabel) ?></span>
                            </div>
                            <span class="tw-shrink-0 tw-rounded-full tw-px-2 tw-py-0.5 tw-text-[9px] tw-font-medium <?= e($tersedia ? 'tw-bg-green-100 tw-text-green-600' : 'tw-bg-red-100 tw-text-red-500') ?>">
                                <?= e($tersedia ? 'Tersedia' : 'Habis') ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p id="menuEmptyState" class="tw-hidden tw-py-12 tw-text-center tw-text-sm tw-text-muted" role="status">Menu tidak ditemukan</p>
        </section>
    </div>
    </main>

    <!-- ==================== POP UP DETAIL MENU (READ-ONLY) ==================== -->
    <div id="menuDetailModal" class="tw-fixed tw-inset-0 tw-z-50 tw-hidden tw-items-center tw-justify-center tw-p-4 tw-overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="menuDetailModalTitle">
        <!-- Backdrop Overlay -->
        <div id="menuDetailModalBackdrop" class="tw-fixed tw-inset-0 tw-bg-black/50 tw-backdrop-blur-sm" aria-hidden="true"></div>

        <!-- Kartu Modal -->
        <div class="tw-relative tw-z-10 tw-w-full tw-max-w-lg tw-rounded-2xl tw-bg-white tw-shadow-2xl tw-border tw-border-[#EFE6DA] tw-flex tw-flex-col tw-max-h-[90vh] tw-overflow-hidden" style="border: 1px solid #EFE6DA;">
            <!-- Header Modal -->
            <div class="tw-flex tw-items-start tw-justify-between tw-border-b tw-border-[#EFE6DA] tw-px-6 tw-py-4 tw-shrink-0" style="border-bottom: 1px solid #EFE6DA;">
                <div>
                    <h2 id="menuDetailModalTitle" class="tw-text-lg sm:tw-text-xl tw-font-bold tw-text-main tw-m-0">Detail Menu</h2>
                    <p id="menuDetailModalSubtitle" class="tw-text-xs sm:tw-text-sm tw-text-muted tw-mt-0.5 tw-m-0">Informasi menu</p>
                </div>
                <button type="button" id="menuDetailModalCloseBtn" class="tw-rounded-full tw-p-1.5 tw-text-muted hover:tw-bg-brand-light hover:tw-text-brand tw-transition-colors tw-border-0 tw-bg-transparent focus:tw-outline-none" aria-label="Tutup detail menu">
                    <svg class="tw-h-5 tw-w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Body Modal (Scroll Internal - Susunan Satu Kolom) -->
            <div class="tw-overflow-y-auto tw-p-6 tw-space-y-4">
                <!-- 2. Banner Krem dengan Ikon Gembok di Paling Atas Isi -->
                <div class="tw-flex tw-items-center tw-gap-2.5 tw-rounded-xl tw-bg-[#F4ECE1] tw-border tw-border-[#EFE6DA] tw-px-3.5 tw-py-2.5 tw-text-xs tw-text-[#5A3E2B]" style="border: 1px solid #EFE6DA;">
                    <svg class="tw-h-4 tw-w-4 tw-shrink-0 tw-text-[#5A3E2B]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Perubahan menu hanya dapat dilakukan oleh Admin.</span>
                </div>

                <!-- 3. Foto Menu di Tengah (~120px, object-contain) -->
                <div class="tw-flex tw-justify-center tw-py-1">
                    <div class="tw-h-[120px] tw-w-[120px] tw-shrink-0 tw-flex tw-items-center tw-justify-center tw-bg-transparent">
                        <img id="modalMenuFoto" src="" alt="" class="tw-h-[120px] tw-w-[120px] tw-object-contain tw-bg-transparent tw-hidden">
                        <div id="modalMenuFotoPlaceholder" class="tw-flex tw-h-[120px] tw-w-[120px] tw-items-center tw-justify-center tw-rounded-2xl tw-bg-[#FBF8F3] tw-border tw-border-[#EFE6DA]" role="img" aria-label="Gambar menu tidak tersedia" style="border: 1px solid #EFE6DA;">
                            <svg class="tw-h-12 tw-w-12 tw-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <path d="m21 15-5-5L5 21"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- 4. Tiap Field Berupa Kotak Sendiri (Border tipis, BG krem muda, rounded-xl, padding 12px) -->
                <div class="tw-space-y-3">
                    <!-- Nama Menu -->
                    <div class="tw-rounded-xl tw-border tw-border-[#EFE6DA] tw-bg-[#FBF8F3]/60 tw-p-3" style="border: 1px solid #EFE6DA;">
                        <span class="tw-block tw-text-xs tw-font-medium tw-text-muted">Nama Menu</span>
                        <p id="modalMenuNama" class="tw-mt-1 tw-text-sm tw-font-semibold tw-text-[#2D231C] tw-m-0">-</p>
                    </div>

                    <!-- Kategori -->
                    <div class="tw-rounded-xl tw-border tw-border-[#EFE6DA] tw-bg-[#FBF8F3]/60 tw-p-3" style="border: 1px solid #EFE6DA;">
                        <span class="tw-block tw-text-xs tw-font-medium tw-text-muted">Kategori</span>
                        <p id="modalMenuKategori" class="tw-mt-1 tw-text-sm tw-font-semibold tw-text-[#2D231C] tw-m-0">-</p>
                    </div>

                    <!-- Harga Dasar -->
                    <div class="tw-rounded-xl tw-border tw-border-[#EFE6DA] tw-bg-[#FBF8F3]/60 tw-p-3" style="border: 1px solid #EFE6DA;">
                        <span class="tw-block tw-text-xs tw-font-medium tw-text-muted">Harga Dasar</span>
                        <p id="modalMenuHarga" class="tw-mt-1 tw-text-sm tw-font-semibold tw-text-[#2D231C] tw-m-0">Rp 0</p>
                    </div>

                    <!-- Deskripsi -->
                    <div class="tw-rounded-xl tw-border tw-border-[#EFE6DA] tw-bg-[#FBF8F3]/60 tw-p-3" style="border: 1px solid #EFE6DA;">
                        <span class="tw-block tw-text-xs tw-font-medium tw-text-muted">Deskripsi</span>
                        <p id="modalMenuDeskripsi" class="tw-mt-1 tw-text-xs sm:tw-text-sm tw-text-main tw-leading-relaxed tw-m-0">-</p>
                    </div>

                    <!-- Ketersediaan (Badge Tersedia/Habis + "N pcs") -->
                    <div class="tw-rounded-xl tw-border tw-border-[#EFE6DA] tw-bg-[#FBF8F3]/60 tw-p-3" style="border: 1px solid #EFE6DA;">
                        <span class="tw-block tw-text-xs tw-font-medium tw-text-muted">Ketersediaan</span>
                        <div class="tw-mt-1.5 tw-flex tw-items-center tw-gap-2.5">
                            <span id="modalMenuStatusBadge" class="tw-rounded-full tw-px-2.5 tw-py-0.5 tw-text-xs tw-font-medium tw-bg-green-100 tw-text-green-600">
                                Tersedia
                            </span>
                            <span id="modalMenuStok" class="tw-text-xs tw-font-medium tw-text-muted">0 pcs</span>
                        </div>
                    </div>

                    <!-- 5. Kotak Info Krem Khusus Minuman di Bawah Ketersediaan -->
                    <div id="modalMenuCustomizableNote" class="tw-hidden tw-w-full tw-items-start tw-gap-2.5 tw-rounded-xl tw-bg-[#F4ECE1] tw-border tw-border-[#EFE6DA] tw-p-3 tw-text-xs tw-text-[#5A3E2B]" style="width: 100%; border: 1px solid #EFE6DA;">
                        <svg class="tw-h-4 tw-w-4 tw-shrink-0 tw-text-[#5A3E2B] tw-mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <span class="tw-leading-normal">Menu ini mendukung kustomisasi ukuran, tipe, gula, es, dan topping saat dipesan pelanggan.</span>
                    </div>
                </div>
            </div>

            <!-- 6. Footer: Satu Tombol "Tutup" Cokelat Tua Rounded-Full di Kanan -->
            <div class="tw-border-t tw-border-[#EFE6DA] tw-px-6 tw-py-3.5 tw-flex tw-justify-end tw-bg-[#FBF8F3]/60 tw-shrink-0" style="border-top: 1px solid #EFE6DA;">
                <button type="button" id="menuDetailModalActionCloseBtn" class="tw-appearance-none tw-outline-none tw-rounded-full tw-border-0 tw-bg-brand tw-px-6 tw-py-2.5 tw-text-xs sm:tw-text-sm tw-font-medium tw-text-white tw-shadow-sm hover:tw-bg-brand-hover focus:tw-outline-none tw-transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <script src="<?= htmlspecialchars(asset('js/js-kasir/menu.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
