<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan & Pembayaran - QuickBrew</title>
    <link rel="icon" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/css-kasir/dashboard.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/css-kasir/pesanan.css')) ?>">
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

        <?php
            $orderId       = '#' . ltrim((string) ($order['id'] ?? $_GET['id'] ?? 'A1234'), '#');
            $meja          = (string) ($order['meja'] ?? '5');
            $pelanggan     = (string) ($order['pelanggan'] ?? 'Arkan');
            $telepon       = (string) ($order['telepon'] ?? '0857-8765-6748');
            $metode        = (string) ($order['metode'] ?? 'Tunai');
            $statusBayar   = (string) ($order['status_bayar'] ?? 'Belum Bayar');
            $statusPesanan = (string) ($order['status_pesanan'] ?? 'Menunggu Pembayaran');
            $subtotal      = (string) ($order['subtotal'] ?? 'Rp 24.000');
            $pajak         = (string) ($order['pajak'] ?? 'Rp 2.400');
            $total         = (string) ($order['total'] ?? 'Rp 26.400');
            $catatan       = (string) ($order['catatan'] ?? 'Tidak ada');
            $items         = $order['items'] ?? [];
            $isPaid        = ($statusBayar === 'Lunas');
        ?>

        <!-- ===== Top Header ===== -->
        <header class="top-header">
            <div class="top-header-left">
                <h1>Pesanan</h1>
                <p>Pengantaran <?= e($orderId) ?> | <?= date('d M Y') ?>, 11:27</p>
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

        <!-- ===== Banner Peringatan Pembayaran ===== -->
        <div class="detail-alert-banner <?= $isPaid ? 'is-paid' : '' ?>" id="paymentAlertBanner">
            <?= $isPaid ? 'Pesanan sudah lunas dan sedang diproses oleh dapur/barista.' : 'Menunggu pembayaran. Pesanan baru diproses setelah pembayaran diterima.' ?>
        </div>

        <!-- ===== Layout 2 Kolom: Detail Pesanan & Tujuan Pengantaran ===== -->
        <div class="detail-grid">

            <!-- Kolom Kiri: Card Detail Pesanan -->
            <section class="detail-card">
                <h2 class="detail-card-title">Detail Pesanan</h2>

                <div class="detail-order-head">
                    <span class="detail-order-id"><?= e($orderId) ?></span>
                    <span class="badge-meja-solid">Meja <?= e($meja) ?></span>
                </div>

                <div class="detail-meta-list">
                    <div class="detail-meta-row">
                        <span class="meta-label">Pelanggan</span>
                        <span class="meta-value"><?= e($pelanggan) ?></span>
                    </div>
                    <div class="detail-meta-row">
                        <span class="meta-label">Telepon</span>
                        <span class="meta-value"><?= e($telepon) ?></span>
                    </div>
                    <div class="detail-meta-row">
                        <span class="meta-label">Metode</span>
                        <span class="meta-value"><?= e($metode) ?></span>
                    </div>
                </div>

                <div class="detail-status-badges" id="detailStatusBadges">
                    <span class="badge-pill <?= $isPaid ? 'badge-lunas' : 'badge-belum-bayar' ?>" id="badgePembayaran"><?= e($statusBayar) ?></span>
                    <span class="badge-pill <?= $isPaid ? ($statusPesanan === 'Selesai' ? 'badge-selesai' : 'badge-diproses') : 'badge-menunggu' ?>" id="badgeStatus"><?= e($statusPesanan) ?></span>
                </div>

                <!-- Item Pesanan -->
                <div class="detail-items-section">
                    <?php foreach ($items as $item): ?>
                        <div class="detail-item-card">
                            <div class="detail-item-top">
                                <img src="<?= e(asset('images/menu/' . ($item['gambar'] ?? 'americano.png'))) ?>" alt="<?= e($item['nama'] ?? 'Menu') ?>" class="detail-item-img">
                                <div class="detail-item-info">
                                    <div class="detail-item-name"><?= e($item['nama'] ?? '') ?></div>
                                    <div class="detail-item-price"><?= e($item['harga'] ?? '') ?></div>
                                </div>
                            </div>
                            <?php if (!empty($item['varian'])): ?>
                                <div class="detail-item-variants">
                                    <?php foreach ($item['varian'] as $var): ?>
                                        <div><?= e($var) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="detail-note">
                    Catatan: <?= e($catatan) ?>
                </div>

                <div class="detail-summary">
                    <div class="detail-summary-row">
                        <span>Subtotal</span>
                        <span><?= e($subtotal) ?></span>
                    </div>
                    <div class="detail-summary-row">
                        <span>Pajak (10%)</span>
                        <span><?= e($pajak) ?></span>
                    </div>
                    <div class="detail-summary-total">
                        <span>Total Pembayaran</span>
                        <span><?= e($total) ?></span>
                    </div>
                </div>
            </section>

            <!-- Kolom Kanan: Tujuan Pengantaran & Riwayat Pesanan -->
            <aside class="detail-side-col">

                <!-- Card Tujuan Pengantaran -->
                <div class="detail-card">
                    <h2 class="detail-card-title">Tujuan Pengantaran</h2>

                    <div class="destination-table">Meja <?= e($meja) ?></div>
                    <div class="destination-customer"><?= e($pelanggan) ?></div>
                    <div class="destination-phone"><?= e($telepon) ?></div>

                    <div class="detail-divider"></div>

                    <div class="detail-side-actions">
                        <?php if (!$isPaid): ?>
                            <button type="button" class="btn-detail-primary" id="btnOpenPayModal">Bayar</button>
                            <button type="button" class="btn-detail-outline" id="btnOpenCancelModal">Batalkan Pesanan</button>
                        <?php else: ?>
                            <button type="button" class="btn-detail-primary" onclick="window.print()">Cetak Struk</button>
                            <button type="button" class="btn-detail-outline" disabled style="opacity: 0.6; cursor: default;">Sudah Lunas</button>
                        <?php endif; ?>
                        <a href="<?= e(url('kasir-pesanan')) ?>" class="btn-detail-back">Kembali ke Daftar Pesanan</a>
                    </div>
                </div>

                <!-- Card Riwayat Pesanan -->
                <div class="detail-card">
                    <h2 class="detail-card-title">Riwayat Pesanan</h2>
                    <ul class="history-list" id="orderHistoryList">
                        <li class="history-item">
                            <span class="history-time">14:00</span>
                            <span class="history-dot"></span>
                            <span class="history-text">Pesanan diterima</span>
                        </li>
                    </ul>
                </div>

            </aside>

        </div><!-- /detail-grid -->

    </main>

    <!-- ==================== MODAL KONFIRMASI PEMBAYARAN ==================== -->
    <div class="modal-overlay" id="payConfirmModal">
        <div class="modal-box">
            <div class="modal-icon-wrapper" style="background: #DCFCE7; color: #15803D;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="16 10 11 15 8 12"></polyline>
                </svg>
            </div>
            <h3 class="modal-title">Konfirmasi Pembayaran?</h3>
            <p class="modal-desc">Pastikan pembayaran tunai sebesar <strong><?= e($total) ?></strong> untuk pesanan <strong><?= e($orderId) ?></strong> sudah diterima.</p>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" id="btnCancelPayModal">Batal</button>
                <button type="button" class="btn-modal-confirm" id="btnConfirmPayModal">Terima Pembayaran</button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL BATALKAN PESANAN ==================== -->
    <div class="modal-overlay" id="cancelOrderModal">
        <div class="modal-box">
            <div class="modal-icon-wrapper" style="background: #FEE2E2; color: #DC2626;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            </div>
            <h3 class="modal-title">Batalkan Pesanan?</h3>
            <p class="modal-desc">Pesanan <strong><?= e($orderId) ?></strong> akan dibatalkan dan tidak dapat dikembalikan.</p>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" id="btnDismissCancelOrder">Kembali</button>
                <button type="button" class="btn-modal-confirm" id="btnConfirmCancelOrder" style="background: #DC2626;">Ya, Batalkan</button>
            </div>
        </div>
    </div>

    <!-- Script -->
    <script src="<?= e(asset('js/js-kasir/pesanan.js')) ?>"></script>

</body>
</html>
