<?php
$ordersList = is_array($orders ?? null) ? $orders : [];
$detailBaseUrl = url('kasir-pesanan-detail');
$menuImgBase   = asset('images/menu/');
?>
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
            <a href="<?= e(url('kasir-stok')) ?>" class="nav-link">
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
                    <button class="filter-tab active" data-filter="semua">Semua (41)</button>
                    <button class="filter-tab" data-filter="menunggu">Menunggu (9)</button>
                    <button class="filter-tab" data-filter="diproses">Diproses (10)</button>
                    <button class="filter-tab" data-filter="selesai">Selesai (20)</button>
                </div>
            </div>

            <!-- Tabel Antrean -->
            <div class="table-container-card">
                <div class="table-responsive">
                    <table class="custom-table" id="tabelPesanan">
                        <thead>
                            <tr>
                                <th class="th-id">ID Pesanan</th>
                                <th class="th-meja">Meja</th>
                                <th class="th-item">Item</th>
                                <th class="th-total">Total</th>
                                <th class="th-metode">Metode</th>
                                <th class="th-bayar">Pembayaran</th>
                                <th class="th-status">Status</th>
                                <th class="th-aksi">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="bodyPesanan">
                            <!-- Row 1: QRIS Lunas / Diproses -->
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
                                <td class="td-metode"><span class="metode-badge metode-qris">QRIS</span></td>
                                <td class="td-bayar"><span class="badge-pill badge-lunas">Lunas</span></td>
                                <td class="td-status"><span class="badge-pill badge-diproses">Diproses</span></td>
                                <td class="td-aksi">
                                    <div class="action-buttons">
                                        <a href="<?= e($detailBaseUrl) ?>&id=A1232" class="btn-action-solid">Detail</a>
                                        <button type="button" class="btn-action-outline btn-open-modal" data-order="#A1232">Siap Antar</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Row 2: TUNAI Belum Bayar (Sesuai Figma) -->
                            <tr data-status="menunggu" id="row-A1234">
                                <td class="td-id">
                                    <div class="order-code">#A1234</div>
                                    <div class="order-item-count">1 item</div>
                                </td>
                                <td class="td-meja"><span class="meja-num">5</span></td>
                                <td class="td-item">
                                    <div class="item-list">Americano (Dingin)</div>
                                </td>
                                <td class="td-total"><span class="order-total">Rp 26.400</span></td>
                                <td class="td-metode"><span class="metode-badge metode-tunai">Tunai</span></td>
                                <td class="td-bayar"><span class="badge-pill badge-belum-bayar">Belum Bayar</span></td>
                                <td class="td-status"><span class="badge-pill badge-menunggu">Menunggu</span></td>
                                <td class="td-aksi">
                                    <div class="action-buttons">
                                        <a href="<?= e($detailBaseUrl) ?>&id=A1234" class="btn-action-solid">Detail</a>
                                        <button type="button" class="btn-action-outline btn-open-tunai-modal" data-order-id="A1234">Bayar</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Row 3: QRIS Belum Bayar (Pembanding dengan Tunai) -->
                            <tr data-status="menunggu" id="row-A1236">
                                <td class="td-id">
                                    <div class="order-code">#A1236</div>
                                    <div class="order-item-count">2 item</div>
                                </td>
                                <td class="td-meja"><span class="meja-num">4</span></td>
                                <td class="td-item">
                                    <div class="item-list">Matcha Latte L, Cheesecake</div>
                                </td>
                                <td class="td-total"><span class="order-total">Rp 55.000</span></td>
                                <td class="td-metode"><span class="metode-badge metode-qris">QRIS</span></td>
                                <td class="td-bayar"><span class="badge-pill badge-belum-bayar">Belum Bayar</span></td>
                                <td class="td-status"><span class="badge-pill badge-menunggu">Menunggu</span></td>
                                <td class="td-aksi">
                                    <div class="action-buttons">
                                        <a href="<?= e($detailBaseUrl) ?>&id=A1236" class="btn-action-solid">Detail</a>
                                        <button type="button" class="btn-action-outline btn-open-qris-modal" data-order-id="A1236">Cek QRIS</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Row 4: QRIS Lunas / Selesai -->
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
                                <td class="td-metode"><span class="metode-badge metode-qris">QRIS</span></td>
                                <td class="td-bayar"><span class="badge-pill badge-lunas">Lunas</span></td>
                                <td class="td-status"><span class="badge-pill badge-selesai">Selesai</span></td>
                                <td class="td-aksi">
                                    <div class="action-buttons">
                                        <a href="<?= e($detailBaseUrl) ?>&id=A1235" class="btn-action-solid">Detail</a>
                                        <button type="button" class="btn-action-outline btn-open-struk-modal" data-order-id="A1235">Cetak Struk</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Row 5: Tunai Lunas / Diproses -->
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
                                <td class="td-metode"><span class="metode-badge metode-tunai">Tunai</span></td>
                                <td class="td-bayar"><span class="badge-pill badge-lunas">Lunas</span></td>
                                <td class="td-status"><span class="badge-pill badge-diproses">Diproses</span></td>
                                <td class="td-aksi">
                                    <div class="action-buttons">
                                        <a href="<?= e($detailBaseUrl) ?>&id=A1238" class="btn-action-solid">Detail</a>
                                        <button type="button" class="btn-action-outline btn-open-modal" data-order="#A1238">Siap Antar</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty state -->
                            <tr id="emptyRow" style="display: none;">
                                <td colspan="8" class="empty-state-cell">
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

    <!-- ==================== MODAL 1: KONFIRMASI PEMBAYARAN TUNAI (SESUAI FIGMA) ==================== -->
    <div class="modal-overlay" id="tunaiVerifyModal">
        <div class="verify-modal-box">
            <div class="verify-modal-header">
                <div>
                    <h3 class="verify-modal-title">Konfirmasi Pembayaran Tunai</h3>
                    <p class="verify-modal-subtitle">Periksa ulang total sebelum konfirmasi.</p>
                </div>
                <button type="button" class="verify-modal-close" id="btnCloseTunaiModal" aria-label="Tutup">&times;</button>
            </div>

            <div class="verify-modal-grid">
                <!-- Kolom Kiri: Info Pesanan & Item -->
                <div class="verify-col-left">
                    <div class="verify-order-title">ID Pesanan <span id="tvOrderId">#A1234</span></div>
                    <div class="verify-info-list">
                        <div class="verify-info-line" id="tvCustomer">Arkan (0857-8765-6748)</div>
                        <div class="verify-info-line">Nomor Meja: <span id="tvMeja">5</span></div>
                        <div class="verify-info-line">Nama / Jumlah Item: <span id="tvItemSummary">Americano (Dingin) (1 item)</span></div>
                        <div class="verify-info-line">Metode Pembayaran: <strong>Tunai</strong></div>
                    </div>

                    <div class="verify-badge-rows">
                        <div class="verify-badge-row">
                            <span>Status Pembayaran</span>
                            <span class="badge-pill badge-belum-bayar">Belum Bayar</span>
                        </div>
                        <div class="verify-badge-row">
                            <span>Status Pesanan</span>
                            <span class="badge-pill badge-menunggu">Menunggu Pembayaran</span>
                        </div>
                    </div>

                    <div class="verify-info-line verify-duration">Durasi Menunggu: <span id="tvDurasi">12 menit</span></div>

                    <div class="verify-divider"></div>

                    <div class="verify-items-wrap" id="tvItemsList">
                        <!-- Diisi dinamis oleh JS -->
                    </div>
                </div>

                <!-- Kolom Kanan: Ringkasan Pembayaran & Input Uang Tunai -->
                <div class="verify-col-right">
                    <h4 class="verify-section-heading">Ringkasan Pembayaran</h4>
                    <div class="verify-note-line">Catatan: <span id="tvCatatan">Tidak ada</span></div>

                    <div class="verify-summary-rows">
                        <div class="verify-summary-row">
                            <span>Subtotal</span>
                            <span id="tvSubtotal">Rp 24.000</span>
                        </div>
                        <div class="verify-summary-row">
                            <span>Pajak (10%)</span>
                            <span id="tvPajak">Rp 2.400</span>
                        </div>
                    </div>

                    <div class="verify-divider"></div>

                    <div class="verify-total-row">
                        <span>Total Pembayaran</span>
                        <span id="tvTotal">Rp 26.400</span>
                    </div>

                    <!-- Input Uang Diterima -->
                    <div class="cash-input-group">
                        <div class="cash-input-row">
                            <label for="inputUangDiterima" class="cash-input-label">Uang Diterima</label>
                            <div class="cash-field-wrap">
                                <span class="cash-prefix">Rp</span>
                                <input type="text" id="inputUangDiterima" class="cash-field-input" value="30.000" inputmode="numeric" autocomplete="off">
                            </div>
                        </div>
                        <div class="cash-quick-chips">
                            <button type="button" class="cash-chip" data-nominal="pas">Uang Pas</button>
                            <button type="button" class="cash-chip" data-nominal="30000">Rp 30.000</button>
                            <button type="button" class="cash-chip" data-nominal="50000">Rp 50.000</button>
                            <button type="button" class="cash-chip" data-nominal="100000">Rp 100.000</button>
                        </div>
                        <div class="cash-error-msg" id="cashErrorMsg" style="display: none;">Nominal uang diterima kurang dari total pembayaran.</div>
                    </div>

                    <div class="verify-kembalian-row">
                        <span>Kembalian</span>
                        <strong id="tvKembalian">Rp 3.600</strong>
                    </div>

                    <div class="verify-modal-actions">
                        <button type="button" class="btn-verify-primary" id="btnSubmitTunaiSuccess">Konfirmasi Pembayaran</button>
                        <button type="button" class="btn-verify-outline" id="btnCancelTunaiModal">Batal</button>
                        <button type="button" class="btn-simulasi-link" id="btnSubmitTunaiFail">Simulasikan gagal simpan (gangguan koneksi)</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL 2: VERIFIKASI PEMBAYARAN QRIS ==================== -->
    <div class="modal-overlay" id="qrisVerifyModal">
        <div class="verify-modal-box">
            <div class="verify-modal-header">
                <div>
                    <h3 class="verify-modal-title">Verifikasi Pembayaran QRIS</h3>
                    <p class="verify-modal-subtitle">Sinkronkan status pembayaran digital pelanggan.</p>
                </div>
                <button type="button" class="verify-modal-close" id="btnCloseQrisModal" aria-label="Tutup">&times;</button>
            </div>

            <div class="verify-modal-grid">
                <!-- Kolom Kiri: Info Pesanan QRIS -->
                <div class="verify-col-left">
                    <div class="verify-order-title">ID Pesanan <span id="qvOrderId">#A1236</span></div>
                    <div class="verify-info-list">
                        <div class="verify-info-line" id="qvCustomer">Nadia Putri (0819-2233-4455)</div>
                        <div class="verify-info-line">Nomor Meja: <span id="qvMeja">4</span></div>
                        <div class="verify-info-line">Nama / Jumlah Item: <span id="qvItemSummary">Matcha Latte L, Cheesecake (2 item)</span></div>
                        <div class="verify-info-line">Metode Pembayaran: <strong>QRIS</strong></div>
                    </div>

                    <div class="verify-badge-rows">
                        <div class="verify-badge-row">
                            <span>Status Pembayaran</span>
                            <span class="badge-pill badge-belum-bayar">Belum Bayar</span>
                        </div>
                        <div class="verify-badge-row">
                            <span>Status Pesanan</span>
                            <span class="badge-pill badge-menunggu">Menunggu Pembayaran</span>
                        </div>
                    </div>

                    <div class="verify-info-line verify-duration">Durasi Menunggu: <span id="qvDurasi">5 menit</span></div>
                    <div class="verify-info-line verify-duration">Batas Waktu QRIS: <strong id="qvBatasBayar">11:42 WIB (15 menit)</strong></div>

                    <div class="verify-divider"></div>

                    <div class="verify-items-wrap" id="qvItemsList">
                        <!-- Diisi dinamis oleh JS -->
                    </div>
                </div>

                <!-- Kolom Kanan: Info Gateway QRIS (Tanpa Uang Diterima / Kembalian) -->
                <div class="verify-col-right">
                    <h4 class="verify-section-heading">Detail Tagihan QRIS</h4>
                    <div class="verify-note-line">Catatan: <span id="qvCatatan">Es dipisah untuk Matcha Latte</span></div>

                    <div class="qris-info-box">
                        <div class="qris-info-title">Pembayaran Non-Tunai (Masa Aktif 15 Menit)</div>
                        <div class="qris-info-desc">Sistem mengecek status dana. Jika melewati batas 15 menit tanpa pembayaran, status otomatis berubah menjadi Kedaluwarsa &amp; Batal.</div>
                        <div class="qris-ref-code">ID Referensi: <strong id="qvRef">MID-QRIS-A1236</strong></div>
                    </div>

                    <div class="verify-summary-rows">
                        <div class="verify-summary-row">
                            <span>Subtotal</span>
                            <span id="qvSubtotal">Rp 50.000</span>
                        </div>
                        <div class="verify-summary-row">
                            <span>Pajak (10%)</span>
                            <span id="qvPajak">Rp 5.000</span>
                        </div>
                    </div>

                    <div class="verify-divider"></div>

                    <div class="verify-total-row">
                        <span>Total Tagihan QRIS</span>
                        <span id="qvTotal">Rp 55.000</span>
                    </div>

                    <div class="verify-modal-actions" style="margin-top: 22px;">
                        <button type="button" class="btn-verify-primary" id="btnSubmitQrisSuccess">Cek Status QRIS</button>
                        <button type="button" class="btn-verify-outline" id="btnSubmitQrisPending">Simulasi Belum Dibayar</button>
                        <button type="button" class="btn-simulasi-link" id="btnSubmitQrisExpire">Simulasikan Lewat 15 Menit (Kedaluwarsa / Otomatis Batal)</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL 3: CETAK STRUK (2 KOLOM SESUAI FIGMA, TANPA PDF/PRINT) ==================== -->
    <div class="modal-overlay" id="strukModal">
        <div class="verify-modal-box struk-modal-box">
            <div class="verify-modal-header">
                <div>
                    <h3 class="verify-modal-title">Cetak Struk</h3>
                </div>
                <button type="button" class="verify-modal-close" id="btnCloseStrukModal" aria-label="Tutup">&times;</button>
            </div>

            <div class="verify-modal-grid">
                <!-- Kolom Kiri: Info Pesanan & Daftar Menu -->
                <div class="verify-col-left">
                    <div class="verify-order-title">ID Pesanan <span id="smOrderId">#A1235</span></div>
                    <div class="verify-info-list">
                        <div class="verify-info-line">Nama: <span id="smCustomer">Siti Sarah (0821-9876-5432)</span></div>
                        <div class="verify-info-line">Nomor Meja: <span id="smMeja">3</span></div>
                        <div class="verify-info-line">Menu / Jumlah Item: <span id="smItemSummary">Caramel Frappe / 1 item</span></div>
                        <div class="verify-info-line">Metode Pembayaran: <span id="smMetode">QRIS</span></div>
                        <div class="verify-info-line">Total: <span id="smTotalLeft">Rp 20.900</span></div>
                    </div>

                    <div class="verify-badge-rows">
                        <div class="verify-badge-row">
                            <span>Status Pembayaran</span>
                            <span class="badge-pill badge-lunas" id="smBadgeBayar">Lunas</span>
                        </div>
                        <div class="verify-badge-row">
                            <span>Status Pesanan</span>
                            <span class="badge-pill badge-selesai" id="smBadgeStatus">Selesai</span>
                        </div>
                    </div>

                    <div class="verify-divider"></div>

                    <div class="verify-items-wrap" id="smItemsList">
                        <!-- Diisi dinamis oleh JS -->
                    </div>
                </div>

                <!-- Kolom Kanan: Ringkasan Pembayaran & Tombol Cetak / Tutup -->
                <div class="verify-col-right">
                    <h4 class="verify-section-heading">Ringkasan Pembayaran</h4>
                    <div class="verify-note-line">Catatan: <span id="smCatatan">Tidak ada</span></div>

                    <div class="verify-summary-rows">
                        <div class="verify-summary-row">
                            <span>Subtotal</span>
                            <span id="smSubtotal">Rp 19.000</span>
                        </div>
                        <div class="verify-summary-row">
                            <span>Pajak (10%)</span>
                            <span id="smPajak">Rp 1.900</span>
                        </div>
                    </div>

                    <div class="verify-divider"></div>

                    <div class="verify-total-row" style="margin-bottom: 18px;">
                        <span>Total Pembayaran</span>
                        <span id="smTotalRight">Rp 20.900</span>
                    </div>

                    <div class="verify-modal-actions" id="strukActionsConfirm">
                        <button type="button" class="btn-verify-primary" id="btnConfirmCetakStruk">Cetak Struk</button>
                        <button type="button" class="btn-verify-outline" id="btnCancelStrukModal">Batal</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== NOTIFIKASI TOAST CETAK STRUK (KANAN ATAS SESUAI FIGMA) ==================== -->
    <div class="struk-toast" id="strukToast" role="status" aria-live="polite">
        <span class="struk-toast-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </span>
        <span class="struk-toast-text" id="strukToastText">Struk berhasil dicetak</span>
    </div>

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

    <!-- Data Pesanan untuk Modal Verifikasi -->
    <script>
        window.QUICKBREW_ORDERS = <?= json_encode($ordersList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.QUICKBREW_DETAIL_URL = <?= json_encode($detailBaseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.QUICKBREW_MENU_IMG_BASE = <?= json_encode($menuImgBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="<?= e(asset('js/js-kasir/pesanan.js')) ?>"></script>

</body>
</html>
