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
                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"></path>
                    <rect x="9" y="3" width="6" height="4" rx="2"></rect>
                    <line x1="9" y1="12" x2="15" y2="12"></line>
                    <line x1="9" y1="16" x2="13" y2="16"></line>
                </svg>
                <span>Pesanan</span>
            </a>
            <a href="<?= htmlspecialchars(url('kasir-menu'), ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
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
                <h1>Selamat datang, Kasir!</h1>
                <p>Fokus pada pesanan yang perlu diproses hari ini.</p>
            </div>
            <div class="top-header-right">
                <button type="button" class="btn-icon-bell" title="Notifikasi">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="bell-badge"></span>
                </button>

                <div class="date-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Hari ini, <?= date('d M Y') ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>

                <div class="profile-card">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($user['nama'] ?? 'K', 0, 1)) ?>
                    </div>
                    <div class="profile-info">
                        <span class="profile-name"><?= e($user['nama'] ?? 'Kasir') ?></span>
                        <span class="profile-role">Kasir</span>
                    </div>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8C7E74" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
            </div>
        </header>

        <!-- ===== Stats Cards ===== -->
        <section class="stats-grid">
            <!-- Total Pesanan -->
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
                    <span class="stat-desc">Hari ini</span>
                </div>
            </div>

            <!-- Diproses -->
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
                    <span class="stat-desc">Pesanan Aktif</span>
                </div>
            </div>

            <!-- Belum Bayar -->
            <div class="stat-card">
                <div class="stat-icon yellow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Belum Bayar</span>
                    <span class="stat-number">6</span>
                    <span class="stat-desc">Menunggu Pembayaran</span>
                </div>
            </div>

            <!-- Selesai -->
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

        <!-- ===== Antrean Pesanan ===== -->
        <section class="section-card">
            <!-- Section Header -->
            <div class="antrean-header">
                <div>
                    <h2 class="antrean-title">Antrean Pesanan</h2>
                    <p class="antrean-subtitle">Kelola seluruh pesanan dalam satu tempat.</p>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="antrean-toolbar">
                <div class="search-wrapper">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8C7E74" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="searchPesanan" class="search-input" placeholder="Cari ID pesanan...">
                </div>
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="semua">Semua <span class="tab-count">48</span></button>
                    <button class="filter-tab" data-filter="menunggu">Menunggu <span class="tab-count">6</span></button>
                    <button class="filter-tab" data-filter="diproses">Diproses <span class="tab-count">12</span></button>
                    <button class="filter-tab" data-filter="selesai">Selesai <span class="tab-count">30</span></button>
                </div>
            </div>

            <!-- Tabel Antrean -->
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>ID Pesanan</th>
                            <th>Meja</th>
                            <th>Item</th>
                            <th>Total</th>
                            <th>Pembayaran</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Row 1 -->
                        <tr data-status="diproses">
                            <td>
                                <div class="order-code">#A1232</div>
                                <div class="order-item-count">3 item</div>
                            </td>
                            <td><span class="meja-num">5</span></td>
                            <td>
                                <div class="item-list">Americana, Matcha Latte L,</div>
                                <div class="item-list">Croissant Butter</div>
                            </td>
                            <td><span class="order-total">Rp 84.700</span></td>
                            <td><span class="badge-pill success">Lunas</span></td>
                            <td><span class="badge-pill process">Diproses</span></td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action-solid">Detail</button>
                                    <button type="button" class="btn-action-outline btn-open-modal" data-order="#A1232">Siap Antar</button>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 2 -->
                        <tr data-status="menunggu">
                            <td>
                                <div class="order-code">#A1234</div>
                                <div class="order-item-count">1 item</div>
                            </td>
                            <td><span class="meja-num">2</span></td>
                            <td>
                                <div class="item-list">Americano</div>
                            </td>
                            <td><span class="order-total">Rp 22.000</span></td>
                            <td><span class="badge-pill warning">Belum Bayar</span></td>
                            <td><span class="badge-pill warning">Menunggu</span></td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action-solid">Detail</button>
                                    <button type="button" class="btn-action-outline">Bayar</button>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 3 -->
                        <tr data-status="selesai">
                            <td>
                                <div class="order-code">#A1235</div>
                                <div class="order-item-count">1 item</div>
                            </td>
                            <td><span class="meja-num">3</span></td>
                            <td>
                                <div class="item-list">Caramel Frappe</div>
                            </td>
                            <td><span class="order-total">Rp 20.900</span></td>
                            <td><span class="badge-pill success">Lunas</span></td>
                            <td><span class="badge-pill done">Selesai</span></td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn-action-solid">Detail</button>
                                    <button type="button" class="btn-action-outline">Cetak Struk</button>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 4 -->
                        <tr data-status="diproses">
                            <td>
                                <div class="order-code">#A1238</div>
                                <div class="order-item-count">1 item</div>
                            </td>
                            <td><span class="meja-num">7</span></td>
                            <td>
                                <div class="item-list">Waffle Berry</div>
                            </td>
                            <td><span class="order-total">Rp 25.400</span></td>
                            <td><span class="badge-pill success">Lunas</span></td>
                            <td><span class="badge-pill process">Diproses</span></td>
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

        <!-- ===== Bottom: Chart + Donut ===== -->
        <div class="bottom-grid">

            <!-- Card Kiri: Pesanan per Jam -->
            <div class="bottom-card">
                <h3 class="bottom-card-title">Pesanan per Jam</h3>
                <div class="chart-area">
                    <svg viewBox="0 0 330 135" style="width:100%;height:auto;display:block;overflow:visible;">
                        <!-- Y-axis labels -->
                        <text x="5" y="16" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">14</text>
                        <text x="5" y="30" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">12</text>
                        <text x="5" y="44" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">10</text>
                        <text x="5" y="58" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">8</text>
                        <text x="5" y="72" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">6</text>
                        <text x="5" y="86" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">4</text>
                        <text x="5" y="100" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">2</text>
                        <text x="5" y="114" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">0</text>

                        <!-- Horizontal Grid Lines (Solid) -->
                        <line x1="25" y1="13" x2="325" y2="13" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="25" y1="27" x2="325" y2="27" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="25" y1="41" x2="325" y2="41" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="25" y1="55" x2="325" y2="55" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="25" y1="69" x2="325" y2="69" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="25" y1="83" x2="325" y2="83" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="25" y1="97" x2="325" y2="97" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="25" y1="111" x2="325" y2="111" stroke="#EFE8DF" stroke-width="1"/>

                        <!-- Vertical Grid Lines (Solid, sesuai posisi jam) -->
                        <line x1="45" y1="13" x2="45" y2="111" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="110" y1="13" x2="110" y2="111" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="175" y1="13" x2="175" y2="111" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="245" y1="13" x2="245" y2="111" stroke="#EFE8DF" stroke-width="1"/>
                        <line x1="310" y1="13" x2="310" y2="111" stroke="#EFE8DF" stroke-width="1"/>

                        <!-- X-axis labels (Tepat di bawah garis vertikal) -->
                        <text x="45" y="126" text-anchor="middle" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">08.00</text>
                        <text x="110" y="126" text-anchor="middle" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">10.00</text>
                        <text x="175" y="126" text-anchor="middle" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">12.00</text>
                        <text x="245" y="126" text-anchor="middle" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">14.00</text>
                        <text x="310" y="126" text-anchor="middle" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif">16.00</text>

                        <!-- Polyline solid (Garis lurus presisi menyambung dari titik ke titik) -->
                        <polyline points="45,83 77,83 110,83 142,83 175,69 220,69 275,27 310,55"
                                  fill="none"
                                  stroke="#5A3E2B"
                                  stroke-width="2"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"/>

                        <!-- Dots dan Label Angka Nilai di atas titik -->
                        <!-- Titik 1 -->
                        <circle cx="45" cy="83" r="3.5" fill="#5A3E2B"/>
                        <text x="45" y="77" text-anchor="middle" font-size="7.5" font-weight="600" fill="#5A3E2B" font-family="Poppins, sans-serif">4</text>

                        <!-- Titik 2 -->
                        <circle cx="77" cy="83" r="3.5" fill="#5A3E2B"/>
                        <text x="77" y="77" text-anchor="middle" font-size="7.5" font-weight="600" fill="#5A3E2B" font-family="Poppins, sans-serif">4</text>

                        <!-- Titik 3 -->
                        <circle cx="110" cy="83" r="3.5" fill="#5A3E2B"/>
                        <text x="110" y="77" text-anchor="middle" font-size="7.5" font-weight="600" fill="#5A3E2B" font-family="Poppins, sans-serif">4</text>

                        <!-- Titik 4 -->
                        <circle cx="142" cy="83" r="3.5" fill="#5A3E2B"/>
                        <text x="142" y="77" text-anchor="middle" font-size="7.5" font-weight="600" fill="#5A3E2B" font-family="Poppins, sans-serif">4</text>

                        <!-- Titik 5 -->
                        <circle cx="175" cy="69" r="3.5" fill="#5A3E2B"/>
                        <text x="175" y="63" text-anchor="middle" font-size="7.5" font-weight="600" fill="#5A3E2B" font-family="Poppins, sans-serif">6</text>

                        <!-- Titik 6 -->
                        <circle cx="220" cy="69" r="3.5" fill="#5A3E2B"/>
                        <text x="220" y="63" text-anchor="middle" font-size="7.5" font-weight="600" fill="#5A3E2B" font-family="Poppins, sans-serif">6</text>

                        <!-- Titik 7 -->
                        <circle cx="275" cy="27" r="3.5" fill="#5A3E2B"/>
                        <text x="275" y="21" text-anchor="middle" font-size="7.5" font-weight="600" fill="#5A3E2B" font-family="Poppins, sans-serif">12</text>

                        <!-- Titik 8 -->
                        <circle cx="310" cy="55" r="3.5" fill="#5A3E2B"/>
                        <text x="310" y="49" text-anchor="middle" font-size="7.5" font-weight="600" fill="#5A3E2B" font-family="Poppins, sans-serif">8</text>
                    </svg>
                </div>
            </div>

            <!-- Card Kanan: Item Lunas per Kategori -->
            <div class="bottom-card">
                <h3 class="bottom-card-title">Item Lunas per Kategori</h3>
                <div class="donut-chart-container">
                    <div class="donut-visual">
                        <svg viewBox="0 0 120 120" width="150" height="150">
                            <!-- Background track (abu tipis) -->
                            <circle cx="60" cy="60" r="45"
                                    fill="none"
                                    stroke="#F0E8DF"
                                    stroke-width="20"/>

                            <!-- Minuman 69% — coklat muda, dimulai dari atas (-90°) -->
                            <circle cx="60" cy="60" r="45"
                                    fill="none"
                                    stroke="#C4A882"
                                    stroke-width="20"
                                    stroke-linecap="butt"
                                    stroke-dasharray="195.09 87.65"
                                    stroke-dashoffset="0"
                                    transform="rotate(-90 60 60)"/>

                            <!-- Dessert 16% — coklat sangat muda -->
                            <circle cx="60" cy="60" r="45"
                                    fill="none"
                                    stroke="#E8D5BC"
                                    stroke-width="20"
                                    stroke-linecap="butt"
                                    stroke-dasharray="45.24 237.50"
                                    stroke-dashoffset="-195.09"
                                    transform="rotate(-90 60 60)"/>

                            <!-- Makanan 15% — coklat tua brand -->
                            <circle cx="60" cy="60" r="45"
                                    fill="none"
                                    stroke="#5A3E2B"
                                    stroke-width="20"
                                    stroke-linecap="butt"
                                    stroke-dasharray="42.41 240.33"
                                    stroke-dashoffset="-240.33"
                                    transform="rotate(-90 60 60)"/>

                            <!-- Lubang tengah (white circle) -->
                            <circle cx="60" cy="60" r="33" fill="white"/>

                            <!-- Teks tengah -->
                            <text x="60" y="56"
                                  text-anchor="middle"
                                  font-size="16"
                                  font-weight="700"
                                  fill="#2D231C"
                                  font-family="Poppins, sans-serif">116</text>
                            <text x="60" y="68"
                                  text-anchor="middle"
                                  font-size="7.5"
                                  fill="#8C7E74"
                                  font-family="Poppins, sans-serif">Item Lunas</text>
                        </svg>
                    </div>
                    <div class="chart-legends">
                        <div class="legend-item">
                            <span class="legend-dot" style="background:#5A3E2B;"></span>
                            <div class="legend-text">
                                <span class="legend-name">Makanan</span>
                                <span class="legend-val">17 item | 15%</span>
                            </div>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot" style="background:#C4A882;"></span>
                            <div class="legend-text">
                                <span class="legend-name">Minuman</span>
                                <span class="legend-val">80 item | 69%</span>
                            </div>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot" style="background:#E8D5BC;"></span>
                            <div class="legend-text">
                                <span class="legend-name">Dessert</span>
                                <span class="legend-val">19 item | 16%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /bottom-grid -->

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
            <p class="modal-desc">Pastikan pesanan sudah lengkap dan siap diantar ke meja pelanggan.</p>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" id="btnCancelModal">Batal</button>
                <button type="button" class="btn-modal-confirm" id="btnConfirmModal">Siap Diantar</button>
            </div>
        </div>
    </div>

    <!-- Script -->
    <script src="<?= e(asset('js/js-kasir/dashboard.js')) ?>"></script>

</body>
</html>
