<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan - QuickBrew</title>
    <link rel="icon" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/css-kasir/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/css-kasir/pesanan.css')) ?>">

    <!-- Tailwind CDN dengan prefix tw- -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
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
                },
            },
            corePlugins: {
                preflight: false,
            },
        }
    </script>
</head>
<body>

    <!-- ==================== SIDEBAR ==================== -->
    <aside class="kasir-sidebar">
        <div class="sidebar-brand">
            <img src="<?= e(asset('images/logo.png')) ?>" alt="QuickBrew Logo" class="brand-logo-img">
            <span class="brand-title">QuickBrew</span>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= e(url('kasir')) ?>" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="<?= e(url('kasir-pesanan')) ?>" class="nav-link active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Pesanan</span>
            </a>
            <a href="<?= htmlspecialchars(url('kasir-menu'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
                    <path d="M7 2v20"></path>
                    <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
                </svg>
                <span>Menu</span>
            </a>
            <a href="#" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;">
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
    <main class="kasir-main">

        <!-- ===== Top Header ===== -->
        <header class="top-header">
            <div class="top-header-left">
                <h1>Pesanan</h1>
                <p>Kelola pembayaran dan pengantaran pesanan ke meja.</p>
            </div>
            <div class="top-header-right">
                <!-- Baris Atas: Notifikasi & Akun Kasir (Dropdown) -->
                <div class="header-top-row">
                    <button type="button" class="btn-icon-bell" title="Notifikasi">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span class="bell-badge"></span>
                    </button>

                    <!-- Profil Kasir dengan Dropdown -->
                    <div class="profile-dropdown-wrapper" id="profileDropdownWrapper">
                        <button type="button" class="profile-card" id="profileDropdownBtn" aria-expanded="false" title="Menu Profil Kasir">
                            <div class="profile-avatar">
                                <?= strtoupper(substr($user['nama'] ?? 'J', 0, 1)) ?>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?= e($user['nama'] ?? 'Jihan Amanda') ?></span>
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

                <!-- Baris Bawah: Filter Tanggal -->
                <div class="date-badge pesanan-date-filter" id="dateFilterBtn">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Hari Ini, <?= date('d M Y') ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
            </div>
        </header>

        <!-- ===== Card Antrean Pesanan ===== -->
        <section class="section-card">
            <!-- Section Header -->
            <div class="antrean-header">
                <div>
                    <h2 class="antrean-title">Antrean Pesanan</h2>
                    <p class="antrean-subtitle">Kelola seluruh pesanan dalam satu tempat.</p>
                </div>
            </div>

            <!-- Search & Filter Toolbar -->
            <div class="antrean-toolbar">
                <div class="search-wrapper">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8C7E74" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="searchPesanan" class="search-input" placeholder="Cari ID pesanan...">
                </div>
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="semua">Semua (40)</button>
                    <button class="filter-tab" data-filter="menunggu">Menunggu (8)</button>
                    <button class="filter-tab" data-filter="diproses">Diproses (10)</button>
                    <button class="filter-tab" data-filter="selesai">Selesai (20)</button>
                </div>
            </div>

            <!-- Tabel Antrean dalam Box Card Ber-border -->
            <div class="table-container-card">
                <div class="table-responsive">
                    <table class="custom-table" id="tabelPesanan">
                        <thead>
                            <tr>
                                <th class="th-id">ID Pesanan</th>
                                <th class="th-meja">Meja</th>
                                <th class="th-item">Item</th>
                                <th class="th-total">Total</th>
                                <th class="th-bayar">Pembayaran</th>
                                <th class="th-status">Status</th>
                                <th class="th-aksi">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="bodyPesanan">
                            <!-- Row 1 -->
                            <tr data-status="diproses">
                                <td class="td-id">
                                    <div class="order-code">#A1232</div>
                                    <div class="order-item-count">3 item</div>
                                </td>
                                <td class="td-meja"><span class="meja-num">5</span></td>
                                <td class="td-item">
                                    <div class="item-list">Americano, Matcha Latte L,</div>
                                    <div class="item-list">Croissant Butter</div>
                                </td>
                                <td class="td-total"><span class="order-total">Rp 84.700</span></td>
                                <td class="td-bayar"><span class="badge-pill badge-lunas">Lunas</span></td>
                                <td class="td-status"><span class="badge-pill badge-diproses">Diproses</span></td>
                                <td class="td-aksi">
                                    <div class="action-buttons">
                                        <button type="button" class="btn-action-solid">Detail</button>
                                        <button type="button" class="btn-action-outline btn-open-modal" data-order="#A1232">Siap Antar</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Row 2 -->
                            <tr data-status="menunggu">
                                <td class="td-id">
                                    <div class="order-code">#A1234</div>
                                    <div class="order-item-count">1 item</div>
                                </td>
                                <td class="td-meja"><span class="meja-num">2</span></td>
                                <td class="td-item">
                                    <div class="item-list">Americano</div>
                                </td>
                                <td class="td-total"><span class="order-total">Rp 22.000</span></td>
                                <td class="td-bayar"><span class="badge-pill badge-belum-bayar">Belum Bayar</span></td>
                                <td class="td-status"><span class="badge-pill badge-menunggu">Menunggu</span></td>
                                <td class="td-aksi">
                                    <div class="action-buttons">
                                        <button type="button" class="btn-action-solid">Detail</button>
                                        <button type="button" class="btn-action-outline">Bayar</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Row 3 -->
                            <tr data-status="selesai">
                                <td class="td-id">
                                    <div class="order-code">#A1235</div>
                                    <div class="order-item-count">1 item</div>
                                </td>
                                <td class="td-meja"><span class="meja-num">3</span></td>
                                <td class="td-item">
                                    <div class="item-list">Caramel Frappe</div>
                                </td>
                                <td class="td-total"><span class="order-total">Rp 20.900</span></td>
                                <td class="td-bayar"><span class="badge-pill badge-lunas">Lunas</span></td>
                                <td class="td-status"><span class="badge-pill badge-selesai">Selesai</span></td>
                                <td class="td-aksi">
                                    <div class="action-buttons">
                                        <button type="button" class="btn-action-solid">Detail</button>
                                        <button type="button" class="btn-action-outline">Cetak Struk</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Row 4 -->
                            <tr data-status="diproses">
                                <td class="td-id">
                                    <div class="order-code">#A1238</div>
                                    <div class="order-item-count">1 item</div>
                                </td>
                                <td class="td-meja"><span class="meja-num">7</span></td>
                                <td class="td-item">
                                    <div class="item-list">Waffle Berry</div>
                                </td>
                                <td class="td-total"><span class="order-total">Rp 26.400</span></td>
                                <td class="td-bayar"><span class="badge-pill badge-lunas">Lunas</span></td>
                                <td class="td-status"><span class="badge-pill badge-diproses">Diproses</span></td>
                                <td class="td-aksi">
                                    <div class="action-buttons">
                                        <button type="button" class="btn-action-solid">Detail</button>
                                        <button type="button" class="btn-action-outline btn-open-modal" data-order="#A1238">Siap Antar</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty state (tersembunyi secara default) -->
                            <tr id="emptyRow" style="display: none;">
                                <td colspan="7" class="empty-state-cell">
                                    <div class="empty-state">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#C4A882" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                        <p>Tidak ada pesanan ditemukan.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>

    <!-- ==================== MODAL KONFIRMASI SIAP ANTAR ==================== -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-box">
            <div class="modal-icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="16 10 11 15 8 12"></polyline>
                </svg>
            </div>
            <h3 class="modal-title">Tandai siap diantar?</h3>
            <p class="modal-desc">Pastikan pesanan <strong id="modalOrderId"></strong> sudah lengkap dan siap diantar ke meja pelanggan.</p>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" id="btnCancelModal">Batal</button>
                <button type="button" class="btn-modal-confirm" id="btnConfirmModal">Siap Diantar</button>
            </div>
        </div>
    </div>

    <!-- Script -->
    <script src="<?= e(asset('js/js-kasir/pesanan.js')) ?>"></script>

</body>
</html>
