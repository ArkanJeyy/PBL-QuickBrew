<?php
$rawId         = ltrim((string) ($order['id'] ?? $_GET['id'] ?? 'A1234'), '#');
$orderId       = '#' . $rawId;
$meja          = (string) ($order['meja'] ?? '5');
$pelanggan     = (string) ($order['pelanggan'] ?? 'Arkan');
$telepon       = (string) ($order['telepon'] ?? '0857-8765-6748');
$metode        = (string) ($order['metode'] ?? 'Tunai');
$statusBayar   = (string) ($order['status_bayar'] ?? 'Belum Bayar');
$statusPesanan = (string) ($order['status_pesanan'] ?? 'Menunggu Pembayaran');
$subtotal      = (string) ($order['subtotal'] ?? 'Rp 24.000');
$pajak         = (string) ($order['pajak'] ?? 'Rp 2.400');
$total         = (string) ($order['total'] ?? 'Rp 26.400');
$totalNum      = (int) ($order['total_num'] ?? 26400);
$catatan       = (string) ($order['catatan'] ?? 'Tidak ada');
$qrisRef       = (string) ($order['qris_ref'] ?? ('MID-QRIS-' . $rawId));
$batasBayar    = (string) ($order['batas_bayar'] ?? '11:42 WIB (15 menit)');
$items         = is_array($order['items'] ?? null) ? $order['items'] : [];
$ordersList    = is_array($orders ?? null) ? $orders : [$rawId => $order];

$state         = (string) ($state ?? 'default');
$uangDiterima  = (int) ($uangDiterima ?? 30000);
$kembalianNum  = max(0, $uangDiterima - $totalNum);
$uangDiterimaRp= format_rupiah($uangDiterima);
$kembalianRp   = format_rupiah($kembalianNum);

$namaKasir     = (string) ($user['nama'] ?? 'Jihan Amanda');
$isQris        = strtoupper($metode) === 'QRIS';

// Override status berdasarkan state hasil validasi
$isExpired = ($state === 'qris_expire');
if (in_array($state, ['tunai_berhasil', 'qris_berhasil'], true)) {
    $isPaid        = true;
    $statusBayar   = 'Lunas';
    $statusPesanan = 'Diproses';
} elseif ($isExpired) {
    $isPaid        = false;
    $statusBayar   = 'Kedaluwarsa';
    $statusPesanan = 'Batal';
} elseif (in_array($state, ['tunai_gagal', 'qris_pending'], true)) {
    $isPaid        = false;
    $statusBayar   = 'Belum Bayar';
    $statusPesanan = 'Menunggu Pembayaran';
} else {
    $isPaid        = ($statusBayar === 'Lunas');
}

// Judul & subjudul halaman sesuai Figma
if ($state === 'tunai_berhasil') {
    $pageHeading    = 'Pembayaran Tunai Berhasil';
    $pageSubheading = 'Pesanan ' . $orderId . ' | Pembayaran Tunai';
    $leftCardTitle  = 'Rincian Pesanan';
} elseif ($state === 'tunai_gagal') {
    $pageHeading    = 'Konfirmasi Belum Tersimpan';
    $pageSubheading = 'Pesanan ' . $orderId . ' | Pembayaran Tunai';
    $leftCardTitle  = 'Rincian Pesanan';
} elseif ($state === 'qris_berhasil') {
    $pageHeading    = 'Pembayaran QRIS Berhasil';
    $pageSubheading = 'Pesanan ' . $orderId . ' | Pembayaran QRIS';
    $leftCardTitle  = 'Rincian Pesanan';
} elseif ($state === 'qris_pending') {
    $pageHeading    = 'Menunggu Pembayaran QRIS';
    $pageSubheading = 'Pesanan ' . $orderId . ' | Pembayaran QRIS';
    $leftCardTitle  = 'Rincian Pesanan';
} elseif ($state === 'qris_expire') {
    $pageHeading    = 'QRIS Kedaluwarsa';
    $pageSubheading = 'Pesanan ' . $orderId . ' | Dibatalkan Otomatis';
    $leftCardTitle  = 'Rincian Pesanan';
} else {
    $pageHeading    = 'Pesanan';
    $pageSubheading = 'Pengantaran ' . $orderId . ' | ' . date('d M Y') . ', 11:27';
    $leftCardTitle  = 'Detail Pesanan';
}

$detailBaseUrl = url('kasir-pesanan-detail');
$menuImgBase   = asset('images/menu/');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageHeading) ?> - QuickBrew</title>
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
                <div class="detail-title-row">
                    <a href="<?= e(url('kasir-pesanan')) ?>" class="btn-back-header" title="Kembali ke Daftar Pesanan" aria-label="Kembali ke Daftar Pesanan">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                    </a>
                    <div>
                        <h1><?= e($pageHeading) ?></h1>
                        <p><?= e($pageSubheading) ?></p>
                    </div>
                </div>
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
                                <?= strtoupper(substr($namaKasir !== '' ? $namaKasir : 'J', 0, 1)) ?>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?= e($namaKasir) ?></span>
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

        <!-- ===== Banner Peringatan hanya pada Mode Detail Default ===== -->
        <?php if ($state === 'default'): ?>
            <div class="detail-alert-banner <?= $isPaid ? 'is-paid' : '' ?>" id="paymentAlertBanner">
                <?php if ($isPaid): ?>
                    Pesanan sudah lunas (<?= e($metode) ?>) dan sedang diproses.
                <?php elseif ($isQris): ?>
                    Menunggu pembayaran QRIS pelanggan. Kasir cukup memverifikasi status pembayaran dari sistem.
                <?php else: ?>
                    Menunggu pembayaran tunai di kasir. Pesanan diproses setelah pembayaran diterima.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ===== Layout 2 Kolom ===== -->
        <div class="detail-grid">

            <!-- ==================== KOLOM KIRI: RINCIAN / DETAIL PESANAN ==================== -->
            <section class="detail-card">
                <h2 class="detail-card-title"><?= e($leftCardTitle) ?></h2>

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
                    <?php if ($isQris && !$isPaid): ?>
                        <div class="detail-meta-row">
                            <span class="meta-label">Batas Bayar QRIS</span>
                            <span class="meta-value"><?= e($batasBayar) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                    if ($isPaid) {
                        $clsBayar  = 'badge-lunas';
                        $clsStatus = ($statusPesanan === 'Selesai') ? 'badge-selesai' : 'badge-diproses';
                    } elseif ($isExpired) {
                        $clsBayar  = 'badge-batal';
                        $clsStatus = 'badge-batal';
                    } else {
                        $clsBayar  = 'badge-belum-bayar';
                        $clsStatus = 'badge-menunggu';
                    }
                ?>
                <div class="detail-status-badges" id="detailStatusBadges">
                    <span class="badge-pill <?= e($clsBayar) ?>" id="badgePembayaran"><?= e($statusBayar) ?></span>
                    <span class="badge-pill <?= e($clsStatus) ?>" id="badgeStatus"><?= e($statusPesanan) ?></span>
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

            <!-- ==================== KOLOM KANAN (BERBEDA SESUAI STATE TUNAI / QRIS) ==================== -->
            <aside class="detail-side-col">

                <?php if ($state === 'tunai_berhasil'): ?>
                    <!-- ===== FRAME 2 FIGMA: TUNAI — PEMBAYARAN BERHASIL ===== -->
                    <div class="detail-card">
                        <h2 class="detail-card-title">Pembayaran Tercatat</h2>

                        <div class="status-callout callout-success">
                            <div class="callout-title"><?= e($total) ?> sudah Lunas</div>
                            <div class="callout-desc">Pesanan sekarang diproses.</div>
                        </div>

                        <div class="payment-result-rows">
                            <div class="payment-result-row">
                                <span>Uang diterima</span>
                                <span><?= e($uangDiterimaRp) ?></span>
                            </div>
                            <div class="payment-result-row is-highlight">
                                <span>Kembalian</span>
                                <strong><?= e($kembalianRp) ?></strong>
                            </div>
                        </div>

                        <p class="payment-helper-caption">Serahkan kembalian beserta struk ke pelanggan.</p>

                        <div class="payment-confirmed-by">
                            <span>Dikonfirmasi oleh</span>
                            <strong><?= e($namaKasir) ?> | Kasir</strong>
                        </div>

                        <div class="detail-side-actions">
                            <a href="<?= e(url('kasir-pesanan')) ?>" class="btn-detail-primary btn-link-center">Lihat Pesanan</a>
                            <button type="button" class="btn-detail-outline btn-open-struk-modal" data-order-id="<?= e($rawId) ?>">Cetak Struk</button>
                        </div>

                        <div class="detail-divider"></div>

                        <ul class="history-list history-detailed">
                            <li class="history-item">
                                <span class="history-time"><?= date('H:i') ?></span>
                                <span class="history-dot"></span>
                                <div class="history-content">
                                    <div class="history-main-text">Pembayaran diterima</div>
                                    <div class="history-sub-text">Konfirmasi tunai berhasil</div>
                                </div>
                            </li>
                            <li class="history-item">
                                <span class="history-time"><?= date('H:i') ?></span>
                                <span class="history-dot"></span>
                                <div class="history-content">
                                    <div class="history-main-text">Diproses</div>
                                    <div class="history-sub-text">Pesanan diteruskan ke dapur</div>
                                </div>
                            </li>
                        </ul>
                    </div>

                <?php elseif ($state === 'tunai_gagal'): ?>
                    <!-- ===== FRAME 3 FIGMA: TUNAI — GAGAL MENYIMPAN KONFIRMASI ===== -->
                    <div class="detail-card">
                        <h2 class="detail-card-title">Gangguan Koneksi</h2>

                        <div class="status-callout callout-danger">
                            <div class="callout-title">Konfirmasi pembayaran belum tercatat</div>
                            <div class="callout-desc">Koneksi terputus saat menyimpan. Jangan serahkan struk pembayaran lunas ke pelanggan terlebih dahulu.</div>
                        </div>

                        <div class="payment-result-rows">
                            <div class="payment-result-row">
                                <span>Uang diterima</span>
                                <span><?= e($uangDiterimaRp) ?></span>
                            </div>
                            <div class="payment-result-row">
                                <span>Kembalian saat dihitung</span>
                                <strong><?= e($kembalianRp) ?></strong>
                            </div>
                        </div>

                        <p class="payment-retry-guide">
                            Cek status pembayaran terlebih dahulu lalu kirim ulang konfirmasi untuk menghindari pencatatan ganda.
                        </p>
                        <div class="payment-attempt-note">Percobaan ke-1 belum tersimpan</div>

                        <div class="detail-side-actions">
                            <a href="<?= e($detailBaseUrl) ?>&id=<?= e($rawId) ?>&state=tunai_berhasil&diterima=<?= $uangDiterima ?>" class="btn-detail-primary btn-link-center">Cek Status &amp; Kirim Ulang</a>
                            <a href="<?= e($detailBaseUrl) ?>&id=<?= e($rawId) ?>" class="btn-detail-outline btn-link-center">Batal</a>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h2 class="detail-card-title">Ketentuan Pembayaran</h2>
                        <p class="ketentuan-text">Pesanan belum diproses sampai pembayaran dikonfirmasi dan tercatat di sistem.</p>
                    </div>

                <?php elseif ($state === 'qris_berhasil'): ?>
                    <!-- ===== QRIS — PEMBAYARAN TERVERIFIKASI LUNAS ===== -->
                    <div class="detail-card">
                        <h2 class="detail-card-title">Pembayaran QRIS Tercatat</h2>

                        <div class="status-callout callout-success">
                            <div class="callout-title"><?= e($total) ?> sudah Lunas via QRIS</div>
                            <div class="callout-desc">Dana terverifikasi otomatis dari Midtrans. Pesanan sekarang diproses.</div>
                        </div>

                        <div class="payment-result-rows">
                            <div class="payment-result-row">
                                <span>Metode Pembayaran</span>
                                <span>QRIS (Nominal Pas)</span>
                            </div>
                            <div class="payment-result-row is-highlight">
                                <span>Total Terbayar</span>
                                <strong><?= e($total) ?></strong>
                            </div>
                            <div class="payment-result-row">
                                <span>ID Referensi</span>
                                <span><?= e($qrisRef) ?></span>
                            </div>
                        </div>

                        <p class="payment-helper-caption">Tidak ada uang kembalian pada transaksi QRIS.</p>

                        <div class="payment-confirmed-by">
                            <span>Diverifikasi oleh</span>
                            <strong>Midtrans &bull; <?= e($namaKasir) ?></strong>
                        </div>

                        <div class="detail-side-actions">
                            <a href="<?= e(url('kasir-pesanan')) ?>" class="btn-detail-primary btn-link-center">Lihat Pesanan</a>
                            <button type="button" class="btn-detail-outline btn-open-struk-modal" data-order-id="<?= e($rawId) ?>">Cetak Struk</button>
                        </div>

                        <div class="detail-divider"></div>

                        <ul class="history-list history-detailed">
                            <li class="history-item">
                                <span class="history-time"><?= date('H:i') ?></span>
                                <span class="history-dot"></span>
                                <div class="history-content">
                                    <div class="history-main-text">Pembayaran QRIS terverifikasi</div>
                                    <div class="history-sub-text">Status Settlement dari Midtrans</div>
                                </div>
                            </li>
                            <li class="history-item">
                                <span class="history-time"><?= date('H:i') ?></span>
                                <span class="history-dot"></span>
                                <div class="history-content">
                                    <div class="history-main-text">Diproses</div>
                                    <div class="history-sub-text">Pesanan diteruskan ke dapur</div>
                                </div>
                            </li>
                        </ul>
                    </div>

                <?php elseif ($state === 'qris_pending'): ?>
                    <!-- ===== QRIS — PELANGGAN BELUM SCAN / MASIH PENDING ===== -->
                    <div class="detail-card">
                        <h2 class="detail-card-title">Status Pembayaran QRIS</h2>

                        <div class="status-callout callout-warning">
                            <div class="callout-title">Pembayaran QRIS belum diterima</div>
                            <div class="callout-desc">Pelanggan belum menyelesaikan scan QRIS di aplikasi e-wallet / m-banking.</div>
                        </div>

                        <div class="payment-result-rows">
                            <div class="payment-result-row">
                                <span>Tagihan QRIS</span>
                                <strong><?= e($total) ?></strong>
                            </div>
                            <div class="payment-result-row">
                                <span>Batas Bayar</span>
                                <span><?= e($batasBayar) ?></span>
                            </div>
                            <div class="payment-result-row">
                                <span>ID Referensi</span>
                                <span><?= e($qrisRef) ?></span>
                            </div>
                            <div class="payment-result-row">
                                <span>Status Gateway</span>
                                <span>Pending (Menunggu Scan)</span>
                            </div>
                        </div>

                        <p class="payment-retry-guide">
                            Apabila melewati batas 15 menit, transaksi QRIS otomatis kedaluwarsa (expire) dan pesanan dibatalkan oleh sistem.
                        </p>

                        <div class="detail-side-actions">
                            <a href="<?= e($detailBaseUrl) ?>&id=<?= e($rawId) ?>&state=qris_berhasil" class="btn-detail-primary btn-link-center">Cek Ulang Status QRIS</a>
                            <a href="<?= e(url('kasir-pesanan')) ?>" class="btn-detail-outline btn-link-center">Kembali ke Antrean</a>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h2 class="detail-card-title">Ketentuan Pembayaran QRIS</h2>
                        <p class="ketentuan-text">Kasir tidak menerima uang fisik untuk pesanan QRIS. Apabila pelanggan ingin berganti ke Tunai, batalkan pesanan ini lalu buat pesanan baru.</p>
                    </div>

                <?php elseif ($state === 'qris_expire'): ?>
                    <!-- ===== QRIS — KEDALUWARSA & DIBATALKAN OTOMATIS (LEWAT 15 MENIT) ===== -->
                    <div class="detail-card">
                        <h2 class="detail-card-title">Transaksi QRIS Kedaluwarsa</h2>

                        <div class="status-callout callout-danger">
                            <div class="callout-title">Waktu pembayaran QRIS (15 menit) telah habis</div>
                            <div class="callout-desc">Midtrans mengembalikan status expire. Pesanan ini otomatis dibatalkan dan stok menu telah dikembalikan.</div>
                        </div>

                        <div class="payment-result-rows">
                            <div class="payment-result-row">
                                <span>Tagihan QRIS</span>
                                <span><?= e($total) ?></span>
                            </div>
                            <div class="payment-result-row">
                                <span>ID Referensi</span>
                                <span><?= e($qrisRef) ?></span>
                            </div>
                            <div class="payment-result-row">
                                <span>Status Pembayaran</span>
                                <strong>Kedaluwarsa (expire)</strong>
                            </div>
                            <div class="payment-result-row">
                                <span>Status Pesanan</span>
                                <strong>Batal</strong>
                            </div>
                        </div>

                        <p class="payment-retry-guide">
                            Kode QR pada perangkat pelanggan sudah tidak berlaku. Arahkan pelanggan untuk membuat pesanan baru jika masih ingin memesan.
                        </p>

                        <div class="detail-side-actions">
                            <a href="<?= e(url('kasir-pesanan')) ?>" class="btn-detail-primary btn-link-center">Kembali ke Daftar Pesanan</a>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h2 class="detail-card-title">Riwayat Pesanan</h2>
                        <ul class="history-list history-detailed">
                            <li class="history-item">
                                <span class="history-time">11:27</span>
                                <span class="history-dot"></span>
                                <div class="history-content">
                                    <div class="history-main-text">Pesanan diterima (QRIS)</div>
                                    <div class="history-sub-text">Batas waktu pembayaran 15 menit</div>
                                </div>
                            </li>
                            <li class="history-item">
                                <span class="history-time">11:42</span>
                                <span class="history-dot" style="background-color: #DC2626;"></span>
                                <div class="history-content">
                                    <div class="history-main-text">Dibatalkan otomatis</div>
                                    <div class="history-sub-text">QRIS kedaluwarsa (expire) &bull; Stok dikembalikan</div>
                                </div>
                            </li>
                        </ul>
                    </div>

                <?php else: ?>
                    <!-- ===== DEFAULT DETAIL VIEW (SAAT KLIK TOMBOL DETAIL) ===== -->
                    <div class="detail-card">
                        <h2 class="detail-card-title">Tujuan Pengantaran</h2>

                        <div class="destination-table">Meja <?= e($meja) ?></div>
                        <div class="destination-customer"><?= e($pelanggan) ?></div>
                        <div class="destination-phone"><?= e($telepon) ?></div>

                        <div class="detail-divider"></div>

                        <div class="detail-side-actions">
                            <?php if (!$isPaid && !$isQris): ?>
                                <button type="button" class="btn-detail-primary btn-open-tunai-modal" data-order-id="<?= e($rawId) ?>">Bayar Tunai</button>
                                <button type="button" class="btn-detail-outline" id="btnOpenCancelModal">Batalkan Pesanan</button>
                            <?php elseif (!$isPaid && $isQris): ?>
                                <button type="button" class="btn-detail-primary btn-open-qris-modal" data-order-id="<?= e($rawId) ?>">Cek Status QRIS</button>
                                <button type="button" class="btn-detail-outline" id="btnOpenCancelModal">Batalkan Pesanan</button>
                            <?php else: ?>
                                <button type="button" class="btn-detail-primary btn-open-struk-modal" data-order-id="<?= e($rawId) ?>">Cetak Struk</button>
                                <a href="<?= e(url('kasir-pesanan')) ?>" class="btn-detail-outline btn-link-center">Kembali ke Pesanan</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h2 class="detail-card-title">Riwayat Pesanan</h2>
                        <ul class="history-list" id="orderHistoryList">
                            <li class="history-item">
                                <span class="history-time">11:27</span>
                                <span class="history-dot"></span>
                                <span class="history-text">Pesanan diterima (<?= e($metode) ?>)</span>
                            </li>
                            <?php if ($isPaid): ?>
                                <li class="history-item">
                                    <span class="history-time">11:28</span>
                                    <span class="history-dot"></span>
                                    <span class="history-text">Pembayaran lunas &bull; Diproses</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>

            </aside>

        </div><!-- /detail-grid -->

    </main>

    <!-- ==================== MODAL 1: KONFIRMASI PEMBAYARAN TUNAI (JIKA DIBUKA DARI DETAIL) ==================== -->
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
                <div class="verify-col-left">
                    <div class="verify-order-title">ID Pesanan <span id="tvOrderId"><?= e($orderId) ?></span></div>
                    <div class="verify-info-list">
                        <div class="verify-info-line" id="tvCustomer"><?= e($pelanggan) ?> (<?= e($telepon) ?>)</div>
                        <div class="verify-info-line">Nomor Meja: <span id="tvMeja"><?= e($meja) ?></span></div>
                        <div class="verify-info-line">Nama / Jumlah Item: <span id="tvItemSummary"><?= e($order['ringkasan_item'] ?? '') ?> (<?= (int) ($order['jumlah_item'] ?? 1) ?> item)</span></div>
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

                    <div class="verify-info-line verify-duration">Durasi Menunggu: <span id="tvDurasi"><?= e((string) ($order['durasi'] ?? '12 menit')) ?></span></div>

                    <div class="verify-divider"></div>

                    <div class="verify-items-wrap" id="tvItemsList"></div>
                </div>

                <div class="verify-col-right">
                    <h4 class="verify-section-heading">Ringkasan Pembayaran</h4>
                    <div class="verify-note-line">Catatan: <span id="tvCatatan"><?= e($catatan) ?></span></div>

                    <div class="verify-summary-rows">
                        <div class="verify-summary-row">
                            <span>Subtotal</span>
                            <span id="tvSubtotal"><?= e($subtotal) ?></span>
                        </div>
                        <div class="verify-summary-row">
                            <span>Pajak (10%)</span>
                            <span id="tvPajak"><?= e($pajak) ?></span>
                        </div>
                    </div>

                    <div class="verify-divider"></div>

                    <div class="verify-total-row">
                        <span>Total Pembayaran</span>
                        <span id="tvTotal"><?= e($total) ?></span>
                    </div>

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

    <!-- ==================== MODAL 2: VERIFIKASI PEMBAYARAN QRIS (JIKA DIBUKA DARI DETAIL) ==================== -->
    <div class="modal-overlay" id="qrisVerifyModal">
        <div class="verify-modal-box">
            <div class="verify-modal-header">
                <div>
                    <h3 class="verify-modal-title">Verifikasi Pembayaran QRIS</h3>
                    <p class="verify-modal-subtitle">Sinkronkan status pembayaran digital pelanggan dari Midtrans.</p>
                </div>
                <button type="button" class="verify-modal-close" id="btnCloseQrisModal" aria-label="Tutup">&times;</button>
            </div>

            <div class="verify-modal-grid">
                <div class="verify-col-left">
                    <div class="verify-order-title">ID Pesanan <span id="qvOrderId"><?= e($orderId) ?></span></div>
                    <div class="verify-info-list">
                        <div class="verify-info-line" id="qvCustomer"><?= e($pelanggan) ?> (<?= e($telepon) ?>)</div>
                        <div class="verify-info-line">Nomor Meja: <span id="qvMeja"><?= e($meja) ?></span></div>
                        <div class="verify-info-line">Nama / Jumlah Item: <span id="qvItemSummary"><?= e($order['ringkasan_item'] ?? '') ?> (<?= (int) ($order['jumlah_item'] ?? 1) ?> item)</span></div>
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

                    <div class="verify-info-line verify-duration">Durasi Menunggu: <span id="qvDurasi"><?= e((string) ($order['durasi'] ?? '5 menit')) ?></span></div>
                    <div class="verify-info-line verify-duration">Batas Waktu QRIS: <strong id="qvBatasBayar"><?= e($batasBayar) ?></strong></div>

                    <div class="verify-divider"></div>

                    <div class="verify-items-wrap" id="qvItemsList"></div>
                </div>

                <div class="verify-col-right">
                    <h4 class="verify-section-heading">Detail Tagihan QRIS</h4>
                    <div class="verify-note-line">Catatan: <span id="qvCatatan"><?= e($catatan) ?></span></div>

                    <div class="qris-info-box">
                        <div class="qris-info-title">Pembayaran Non-Tunai (Masa Aktif 15 Menit)</div>
                        <div class="qris-info-desc">Sistem mengecek status dana dari Midtrans. Jika melewati batas 15 menit tanpa pembayaran, status otomatis berubah menjadi Kedaluwarsa &amp; Batal.</div>
                        <div class="qris-ref-code">ID Referensi: <strong id="qvRef"><?= e($qrisRef) ?></strong></div>
                    </div>

                    <div class="verify-summary-rows">
                        <div class="verify-summary-row">
                            <span>Subtotal</span>
                            <span id="qvSubtotal"><?= e($subtotal) ?></span>
                        </div>
                        <div class="verify-summary-row">
                            <span>Pajak (10%)</span>
                            <span id="qvPajak"><?= e($pajak) ?></span>
                        </div>
                    </div>

                    <div class="verify-divider"></div>

                    <div class="verify-total-row">
                        <span>Total Tagihan QRIS</span>
                        <span id="qvTotal"><?= e($total) ?></span>
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

    <!-- ==================== MODAL CETAK STRUK (2 KOLOM SESUAI FIGMA, TANPA PDF/PRINT) ==================== -->
    <div class="modal-overlay" id="strukModal">
        <div class="verify-modal-box struk-modal-box">
            <div class="verify-modal-header">
                <div>
                    <h3 class="verify-modal-title">Cetak Struk</h3>
                </div>
                <button type="button" class="verify-modal-close" id="btnCloseStrukModal" aria-label="Tutup">&times;</button>
            </div>

            <div class="verify-modal-grid">
                <div class="verify-col-left">
                    <div class="verify-order-title">ID Pesanan <span id="smOrderId"><?= e($orderId) ?></span></div>
                    <div class="verify-info-list">
                        <div class="verify-info-line">Nama: <span id="smCustomer"><?= e($pelanggan) ?> (<?= e($telepon) ?>)</span></div>
                        <div class="verify-info-line">Nomor Meja: <span id="smMeja"><?= e($meja) ?></span></div>
                        <div class="verify-info-line">Menu / Jumlah Item: <span id="smItemSummary"><?= e($order['ringkasan_item'] ?? '') ?> / <?= (int) ($order['jumlah_item'] ?? 1) ?> item</span></div>
                        <div class="verify-info-line">Metode Pembayaran: <span id="smMetode"><?= e($metode) ?></span></div>
                        <div class="verify-info-line">Total: <span id="smTotalLeft"><?= e($total) ?></span></div>
                    </div>

                    <div class="verify-badge-rows">
                        <div class="verify-badge-row">
                            <span>Status Pembayaran</span>
                            <span class="badge-pill badge-lunas" id="smBadgeBayar">Lunas</span>
                        </div>
                        <div class="verify-badge-row">
                            <span>Status Pesanan</span>
                            <span class="badge-pill badge-selesai" id="smBadgeStatus"><?= e($statusPesanan) ?></span>
                        </div>
                    </div>

                    <div class="verify-divider"></div>

                    <div class="verify-items-wrap" id="smItemsList"></div>
                </div>

                <div class="verify-col-right">
                    <h4 class="verify-section-heading">Ringkasan Pembayaran</h4>
                    <div class="verify-note-line">Catatan: <span id="smCatatan"><?= e($catatan) ?></span></div>

                    <div class="verify-summary-rows">
                        <div class="verify-summary-row">
                            <span>Subtotal</span>
                            <span id="smSubtotal"><?= e($subtotal) ?></span>
                        </div>
                        <div class="verify-summary-row">
                            <span>Pajak (10%)</span>
                            <span id="smPajak"><?= e($pajak) ?></span>
                        </div>
                    </div>

                    <div class="verify-divider"></div>

                    <div class="verify-total-row" style="margin-bottom: 18px;">
                        <span>Total Pembayaran</span>
                        <span id="smTotalRight"><?= e($total) ?></span>
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

    <script>
        window.QUICKBREW_ORDERS = <?= json_encode($ordersList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.QUICKBREW_DETAIL_URL = <?= json_encode($detailBaseUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.QUICKBREW_MENU_IMG_BASE = <?= json_encode($menuImgBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="<?= e(asset('js/js-kasir/pesanan.js')) ?>"></script>

</body>
</html>
