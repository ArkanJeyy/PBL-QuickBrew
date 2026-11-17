<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda Kasir - QuickBrew</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/css-kasir/dashboard.css')) ?>">

    <!-- Tailwind CDN ) -->
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
</head>
<body>

    <!-- ==================== SIDEBAR ==================== -->
    <aside class="kasir-sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
                    <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
                    <line x1="6" y1="1" x2="6" y2="4"></line>
                    <line x1="10" y1="1" x2="10" y2="4"></line>
                    <line x1="14" y1="1" x2="14" y2="4"></line>
                </svg>
            </div>
            <span class="brand-title">QuickBrew</span>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= e(url('kasir')) ?>" class="nav-link active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Pesanan</span>
            </a>
            <a href="#" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
                    <path d="M7 2v20"></path>
                    <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
                </svg>
                <span>Menu</span>
            </a>
            <a href="#" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
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
                <button type="submit" class="nav-link" style="color: #EF4444; background: none; border: none; width: 100%; text-align: left; cursor: pointer; font: inherit; display: flex; align-items: center; gap: 12px; padding: 12px 16px;">
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

        <!-- Top Header -->
        <header class="top-header">
            <div class="top-header-left">
                <h1>Selamat datang, Kasir!</h1>
                <p>Fokus pada pesanan yang perlu diproses hari ini.</p>
            </div>
            <div class="top-header-right">
                <button type="button" class="btn-icon-bell" title="Notifikasi Pesanan">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="bell-badge"></span>
                </button>

                <div class="date-badge">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Hari ini, <?= date('d M Y') ?></span>
                </div>

                <div class="profile-card">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($user['nama'] ?? 'Kasir', 0, 1)) ?>
                    </div>
                    <div class="profile-info">
                        <span class="profile-name"><?= e($user['nama'] ?? 'Jihan Amanda') ?></span>
                        <span class="profile-role">Kasir</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Metrics / Stat Cards -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon brown">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path>
                        <path d="M3 6h18"></path>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Total Pesanan</span>
                    <span class="stat-number">48</span>
                    <span class="stat-desc">48 pesanan lunas</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Diproses</span>
                    <span class="stat-number">12</span>
                    <span class="stat-desc">Pesanan aktif</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 8h1a4 4 0 1 1 0 8h-1"></path>
                        <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"></path>
                        <line x1="6" y1="2" x2="6" y2="4"></line>
                        <line x1="10" y1="2" x2="10" y2="4"></line>
                        <line x1="14" y1="2" x2="14" y2="4"></line>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Belum Bayar</span>
                    <span class="stat-number">6</span>
                    <span class="stat-desc">Menunggu Pembayaran</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon slate">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Selesai</span>
                    <span class="stat-number">30</span>
                    <span class="stat-desc">Pesanan hari ini</span>
                </div>
            </div>
        </section>

        <!-- Section: Pesanan Masuk -->
        <section class="section-card">
            <div class="section-header">
                <h2>Pesanan Masuk</h2>
                <div class="sub-badge">
                    <span class="highlight-unpaid">3 belum bayar</span> | 42 lunas
                </div>
            </div>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Pesanan</th>
                            <th>Meja / Menu / Total</th>
                            <th>Pembayaran</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="order-code">#A1232</div>
                                <div class="order-item-count">2 Item</div>
                            </td>
                            <td>
                                <div class="order-table-num">Meja 1</div>
                                <div class="order-items-summary">Matcha Latte L + Croissant Butter</div>
                                <div class="order-price">Rp 58.300</div>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill warning">Belum Bayar</span>
                                    <span class="badge-sub">Tunai</span>
                                </div>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill warning">Menunggu</span>
                                    <span class="badge-sub">12 menit</span>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action-outline">Bayar</button>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="order-code">#A1233</div>
                                <div class="order-item-count">3 Item</div>
                            </td>
                            <td>
                                <div class="order-table-num">Meja 5</div>
                                <div class="order-items-summary">Americano + Matcha Latte</div>
                                <div class="order-price">Rp 56.100</div>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill warning">Belum Bayar</span>
                                    <span class="badge-sub">QRIS</span>
                                </div>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill warning">Menunggu</span>
                                    <span class="badge-sub">18 menit</span>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action-outline">Cek QRIS</button>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="order-code">#A1237</div>
                                <div class="order-item-count">1 Item</div>
                            </td>
                            <td>
                                <div class="order-table-num">Meja 8</div>
                                <div class="order-items-summary">Strawberry Milk</div>
                                <div class="order-price">Rp 22.000</div>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill warning">Belum Bayar</span>
                                    <span class="badge-sub">Tunai</span>
                                </div>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill warning">Menunggu</span>
                                    <span class="badge-sub">3 menit</span>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action-outline">Bayar</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section: Antrean Pesanan | Semua Status -->
        <section class="section-card">
            <div class="section-header">
                <h2>Antrean Pesanan | Semua Status</h2>
                <div class="sub-badge">42 lunas | 6 belum bayar</div>
            </div>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Pesanan</th>
                            <th>Meja</th>
                            <th>Pembayaran</th>
                            <th>Status</th>
                            <th>Menu / Total</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="order-code">#A1231</div>
                                <div class="order-item-count">5 Item</div>
                            </td>
                            <td>
                                <span class="order-table-num">5</span>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill success">Lunas</span>
                                    <span class="badge-sub">QRIS</span>
                                </div>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill process">Diproses</span>
                                    <span class="badge-sub">20 menit</span>
                                </div>
                            </td>
                            <td>
                                <div class="order-items-summary">Americano, Matcha Latte L, Croissant Butter</div>
                                <div class="order-price">Rp 84.700</div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action-solid">Detail</button>
                                    <button type="button" class="btn-action-outline btn-open-modal" data-order="#A1231">Siap Antar</button>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="order-code">#A1234</div>
                                <div class="order-item-count">1 Item</div>
                            </td>
                            <td>
                                <span class="order-table-num">2</span>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill success">Lunas</span>
                                    <span class="badge-sub">Tunai</span>
                                </div>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill process">Diproses</span>
                                    <span class="badge-sub">15 menit</span>
                                </div>
                            </td>
                            <td>
                                <div class="order-items-summary">Americano</div>
                                <div class="order-price">Rp 22.000</div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action-solid">Detail</button>
                                    <button type="button" class="btn-action-outline btn-open-modal" data-order="#A1234">Siap Antar</button>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="order-code">#A1235</div>
                                <div class="order-item-count">2 Item</div>
                            </td>
                            <td>
                                <span class="order-table-num">3</span>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill success">Lunas</span>
                                    <span class="badge-sub">QRIS</span>
                                </div>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill process">Diproses</span>
                                    <span class="badge-sub">10 menit</span>
                                </div>
                            </td>
                            <td>
                                <div class="order-items-summary">Caramel Frappe</div>
                                <div class="order-price">Rp 30.800</div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action-solid">Detail</button>
                                    <button type="button" class="btn-action-outline btn-open-modal" data-order="#A1235">Siap Antar</button>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div class="order-code">#A1238</div>
                                <div class="order-item-count">1 Item</div>
                            </td>
                            <td>
                                <span class="order-table-num">7</span>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill success">Lunas</span>
                                    <span class="badge-sub">QRIS</span>
                                </div>
                            </td>
                            <td>
                                <div class="badge-status">
                                    <span class="badge-pill process">Diproses</span>
                                    <span class="badge-sub">4 menit</span>
                                </div>
                            </td>
                            <td>
                                <div class="order-items-summary">Waffle Berry</div>
                                <div class="order-price">Rp 26.400</div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action-solid">Detail</button>
                                    <button type="button" class="btn-action-outline btn-open-modal" data-order="#A1238">Siap Antar</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Bottom Row: Analytics & Quick Actions -->
        <div class="bottom-grid">
            <!-- Card 1: Chart Pesanan per Jam -->
            <div class="bottom-card">
                <h3>Pesanan per Jam</h3>
                <div style="width: 100%; height: 130px; display: flex; align-items: flex-end; justify-content: space-between; padding-top: 10px;">
                    <svg viewBox="0 0 280 100" style="width: 100%; height: 100%; overflow: visible;">
                        <!-- Grid Lines -->
                        <line x1="0" y1="20" x2="280" y2="20" stroke="#F1EAE2" stroke-width="1" stroke-dasharray="3 3"/>
                        <line x1="0" y1="50" x2="280" y2="50" stroke="#F1EAE2" stroke-width="1" stroke-dasharray="3 3"/>
                        <line x1="0" y1="80" x2="280" y2="80" stroke="#F1EAE2" stroke-width="1" stroke-dasharray="3 3"/>
                        
                        <!-- Smooth Trend Line -->
                        <path d="M 10 75 Q 50 78, 80 65 T 150 70 T 210 30 T 270 50" fill="none" stroke="#5A3E2B" stroke-width="2.5"/>
                        
                        <!-- Dots -->
                        <circle cx="10" cy="75" r="3.5" fill="#5A3E2B"/>
                        <circle cx="80" cy="65" r="3.5" fill="#5A3E2B"/>
                        <circle cx="150" cy="70" r="3.5" fill="#5A3E2B"/>
                        <circle cx="210" cy="30" r="3.5" fill="#5A3E2B"/>
                        <circle cx="270" cy="50" r="3.5" fill="#5A3E2B"/>
                    </svg>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 10px; color: #8C7E74; margin-top: 8px;">
                    <span>10:00</span>
                    <span>12:00</span>
                    <span>14:00</span>
                    <span>16:00</span>
                    <span>18:00</span>
                    <span>20:00</span>
                </div>
            </div>

            <!-- Card 2: Item Lunas per Kategori -->
            <div class="bottom-card">
                <h3>Item Lunas per Kategori</h3>
                <div class="donut-chart-container">
                    <div class="donut-visual">
                        <div class="donut-inner">
                            <span class="donut-value">116</span>
                            <span class="donut-label">Item Lunas</span>
                        </div>
                    </div>
                    <div class="chart-legends">
                        <div class="legend-item">
                            <span class="legend-dot coffee"></span>
                            <span>Minuman: <strong>99</strong> (85%)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot dessert"></span>
                            <span>Makanan: <strong>17</strong> (15%)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Quick Action List -->
            <div class="bottom-card">
                <h3>Aksi Cepat</h3>
                <div class="quick-action-list">
                    <a href="#" class="quick-action-item">
                        <div class="quick-action-left">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                                    <line x1="2" y1="10" x2="22" y2="10"></line>
                                </svg>
                            </div>
                            <div class="quick-action-text">
                                <h4>Konfirmasi Pembayaran</h4>
                                <p>Tunai: verifikasi uang, lalu konfirmasi</p>
                            </div>
                        </div>
                        <span class="quick-action-arrow">&rarr;</span>
                    </a>

                    <a href="#" class="quick-action-item">
                        <div class="quick-action-left">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                            </div>
                            <div class="quick-action-text">
                                <h4>Cek Pembayaran QRIS</h4>
                                <p>Belum terverifikasi: cek status</p>
                            </div>
                        </div>
                        <span class="quick-action-arrow">&rarr;</span>
                    </a>

                    <a href="#" class="quick-action-item">
                        <div class="quick-action-left">
                            <div class="quick-action-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                    <rect x="6" y="14" width="12" height="8"></rect>
                                </svg>
                            </div>
                            <div class="quick-action-text">
                                <h4>Cetak</h4>
                                <p>Karcis untuk pesanan selesai</p>
                            </div>
                        </div>
                        <span class="quick-action-arrow">&rarr;</span>
                    </a>
                </div>
            </div>
        </div>

    </main>

    <!-- ==================== MODAL KONFIRMASI PESANAN SELESAI ==================== -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-box">
            <div class="modal-icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="16 10 11 15 8 12"></polyline>
                </svg>
            </div>
            <h3 class="modal-title">Tandai siap diantar?</h3>
            <p class="modal-desc">Pastikan pesanan sudah lengkap dan siap diantar. Tandai selesai hanya setelah diterima pelanggan</p>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" id="btnCancelModal">Batal</button>
                <button type="button" class="btn-modal-confirm" id="btnConfirmModal">Siap diantar</button>
            </div>
        </div>
    </div>

    <!-- Script Interaktif Modal -->
    <script src="<?= e(asset('js/js-kasir/dashboard.js')) ?>"></script>

</body>
</html>
