<?php
$ringkas      = is_array($ringkas ?? null) ? $ringkas : ['belum_bayar' => 0, 'lunas_hari_ini' => 0, 'pendapatan_hari_ini' => 0, 'per_status' => []];
$analitik     = is_array($analitik ?? null) ? $analitik : ['total_hari_ini' => 0, 'selesai_hari_ini' => 0, 'per_jam' => [], 'item_per_kategori' => [], 'total_item_lunas' => 0];
$paginasi     = is_array($paginasi ?? null) ? $paginasi : ['items' => [], 'total' => 0, 'halaman' => 1, 'per_halaman' => 10, 'total_halaman' => 1, 'dari' => 0, 'sampai' => 0];
$antrean      = is_array($antrean ?? null) ? $antrean : ($paginasi['items'] ?? []);
$flash        = is_array($flash ?? null) ? $flash : null;
$statusFilter      = is_string($statusFilter ?? null) ? $statusFilter : 'semua';
$cari              = is_string($cari ?? null) ? $cari : '';
$perHalaman        = max(5, (int) ($perHalaman ?? ($paginasi['per_halaman'] ?? 10)));
$perHalamanOptions = is_array($perHalamanOptions ?? null) && $perHalamanOptions !== [] ? array_map('intval', $perHalamanOptions) : [5, 10, 15, 20, 25, 50, 100];
if (!in_array($perHalaman, $perHalamanOptions, true)) {
    $perHalamanOptions[] = $perHalaman;
    sort($perHalamanOptions);
}

$namaStaf  = (string) ($user['nama'] ?? 'Kasir');
$roleStaf  = ucfirst((string) ($user['role'] ?? 'kasir'));
$inisial   = strtoupper(substr($namaStaf !== '' ? $namaStaf : 'K', 0, 1));

$totalHariIni   = (int) ($analitik['total_hari_ini'] ?? 0);
$belumBayarCnt  = (int) ($ringkas['belum_bayar'] ?? 0);
$diprosesCnt    = (int) (($ringkas['per_status']['diproses'] ?? 0) + ($ringkas['per_status']['siap'] ?? 0));
$selesaiHariIni = (int) ($analitik['selesai_hari_ini'] ?? 0);

// Hitungan global per tab dari ringkasan database agar tetap akurat saat pagination aktif
$perStatus = is_array($ringkas['per_status'] ?? null) ? $ringkas['per_status'] : [];
$tabCounts = [
    'menunggu' => (int) ($perStatus['menunggu_pembayaran'] ?? 0),
    'diproses' => (int) ($perStatus['diproses'] ?? 0),
    'siap'     => (int) ($perStatus['siap'] ?? 0),
    'selesai'  => (int) ($perStatus['selesai'] ?? 0),
    'batal'    => (int) ($perStatus['batal'] ?? 0),
];
$tabCounts['semua'] = array_sum($tabCounts);

$kasirTabUrl = static function (string $status, string $q = '', int $hal = 1, ?int $per = null) use ($perHalaman): string {
    $query  = [];
    $perVal = $per ?? $perHalaman;
    if ($status !== '' && $status !== 'semua') {
        $query['status'] = $status;
    }
    if ($q !== '') {
        $query['q'] = $q;
    }
    if ($perVal !== 10) {
        $query['per'] = $perVal;
    }
    if ($hal > 1) {
        $query['hal'] = $hal;
    }
    return url('kasir', $query) . '#antreanSection';
};

$halAktif     = max(1, (int) ($paginasi['halaman'] ?? 1));
$totalHalaman = max(1, (int) ($paginasi['total_halaman'] ?? 1));
$totalData    = max(0, (int) ($paginasi['total'] ?? 0));
$dariBaris    = max(0, (int) ($paginasi['dari'] ?? 0));
$sampaiBaris  = max(0, (int) ($paginasi['sampai'] ?? 0));

// Daftar nomor halaman ringkas dengan elipsis jika halaman > 7
$pageSlots = [];
if ($totalHalaman <= 7) {
    $pageSlots = range(1, $totalHalaman);
} else {
    $candidates = array_unique(array_filter([
        1,
        2,
        $halAktif - 1,
        $halAktif,
        $halAktif + 1,
        $totalHalaman - 1,
        $totalHalaman,
    ], static fn (int $p): bool => $p >= 1 && $p <= $totalHalaman));
    sort($candidates);
    $prevPage = 0;
    foreach ($candidates as $p) {
        if ($prevPage > 0 && ($p - $prevPage) > 1) {
            $pageSlots[] = '...';
        }
        $pageSlots[] = $p;
        $prevPage    = $p;
    }
}

// Persiapan skala SVG "Pesanan per Jam" (8 slot 3-jaman: 00.00 - 21.00)
$perJam = $analitik['per_jam'] ?? [];
if ($perJam === []) {
    for ($slot = 0; $slot < 8; $slot++) {
        $perJam[] = ['label' => sprintf('%02d.00', $slot * 3), 'jumlah' => 0];
    }
}
$maxPerJam = 0;
foreach ($perJam as $pj) {
    $maxPerJam = max($maxPerJam, (int) ($pj['jumlah'] ?? 0));
}
$yStep   = max(1, (int) ceil(max($maxPerJam, 7) / 7));
$yMax    = $yStep * 7;
$xStart  = 42.0;
$xEnd    = 310.0;
$xSpan   = $xEnd - $xStart;
$yTop    = 13.0;
$yBottom = 111.0;
$ySpan   = $yBottom - $yTop;

$chartPoints = [];
$slotTotal   = max(1, count($perJam) - 1);
foreach ($perJam as $idx => $pj) {
    $val = (int) ($pj['jumlah'] ?? 0);
    $x   = round($xStart + ($idx / $slotTotal) * $xSpan, 1);
    $y   = round($yBottom - ($yMax > 0 ? ($val / $yMax) * $ySpan : 0), 1);
    $chartPoints[] = [
        'x'     => $x,
        'y'     => $y,
        'val'   => $val,
        'label' => (string) ($pj['label'] ?? ''),
    ];
}
$polylineStr = implode(' ', array_map(
    static fn (array $pt): string => $pt['x'] . ',' . $pt['y'],
    $chartPoints
));
$xAxisIndices = [0, 2, 4, 6, 7];

// Persiapan SVG Donut "Item Lunas per Kategori"
$itemPerKategori = $analitik['item_per_kategori'] ?? [];
$totalItemLunas  = (int) ($analitik['total_item_lunas'] ?? 0);
$donutRadius     = 45.0;
$donutCircum     = 2 * M_PI * $donutRadius;
$warnaKategori   = [
    'Makanan' => '#5A3E2B',
    'Minuman' => '#C4A882',
    'Dessert' => '#E8D5BC',
];
$paletteCadangan = ['#5A3E2B', '#C4A882', '#E8D5BC', '#8C7E74', '#D97706'];
$donutSegments   = [];
$offsetAkum      = 0.0;
foreach ($itemPerKategori as $idx => $kat) {
    $namaKat = (string) ($kat['nama_kategori'] ?? '');
    $jumlah  = (int) ($kat['jumlah_item'] ?? 0);
    $persen  = (int) ($kat['persen'] ?? 0);
    $warna   = $warnaKategori[$namaKat] ?? $paletteCadangan[$idx % count($paletteCadangan)];

    $dashLen = ($totalItemLunas > 0 && $jumlah > 0) ? ($jumlah / $totalItemLunas) * $donutCircum : 0.0;
    $donutSegments[] = [
        'nama'       => $namaKat,
        'jumlah'     => $jumlah,
        'persen'     => $persen,
        'warna'      => $warna,
        'dash_len'   => $dashLen,
        'dash_gap'   => max(0.0, $donutCircum - $dashLen),
        'dashoffset' => $offsetAkum,
    ];
    $offsetAkum += $dashLen;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda Kasir - QuickBrew</title>
    <link rel="icon" href="<?= e(asset('images/logo.png')) ?>">
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
            <img src="<?= e(asset('images/logo.png')) ?>" alt="QuickBrew Logo" class="brand-logo-img">
            <span class="brand-title">QuickBrew</span>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= e(url('kasir')) ?>" class="nav-link active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="#antreanSection" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Pesanan</span>
            </a>
            <a href="<?= e(url('kasir-menu')) ?>" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
                    <path d="M7 2v20"></path>
                    <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
                </svg>
                <span>Menu</span>
            </a>
            <a href="<?= e(url('kasir-menu')) ?>" class="nav-link">
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
                <h1>Selamat datang, <?= e($namaStaf) ?>!</h1>
                <p>Fokus pada pesanan yang perlu diproses hari ini.</p>
            </div>
            <div class="top-header-right">
                <!-- Baris Atas: Notifikasi & Akun Kasir (Dropdown) -->
                <div class="header-top-row">
                    <a href="<?= e($kasirTabUrl('menunggu', '', 1)) ?>" class="btn-icon-bell" id="btnBellPending" title="<?= $belumBayarCnt > 0 ? e($belumBayarCnt . ' pesanan menunggu pembayaran') : 'Tidak ada pesanan menunggu pembayaran' ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <?php if ($belumBayarCnt > 0): ?>
                            <span class="bell-badge"></span>
                        <?php endif; ?>
                    </a>

                    <!-- Profil Kasir dengan Dropdown -->
                    <div class="profile-dropdown-wrapper" id="profileDropdownWrapper">
                        <button type="button" class="profile-card" id="profileDropdownBtn" aria-expanded="false" title="Menu Profil Kasir">
                            <div class="profile-avatar">
                                <?= e($inisial) ?>
                            </div>
                            <div class="profile-info">
                                <span class="profile-name"><?= e($namaStaf) ?></span>
                                <span class="profile-role"><?= e($roleStaf) ?></span>
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

                <!-- Baris Bawah: Tanggal -->
                <div class="date-badge">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Hari ini, <?= date('d M Y') ?></span>
                </div>
            </div>
        </header>

        <?php if ($flash !== null && !empty($flash['pesan'])): ?>
            <div class="kasir-flash kasir-flash--<?= e((string) ($flash['tipe'] ?? 'info')) ?>" role="status">
                <?= e((string) $flash['pesan']) ?>
            </div>
        <?php endif; ?>
        <div id="apiToastContainer" aria-live="polite"></div>

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
                    <span class="stat-number"><?= $totalHariIni ?></span>
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
                    <span class="stat-number"><?= $diprosesCnt ?></span>
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
                    <span class="stat-number"><?= $belumBayarCnt ?></span>
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
                    <span class="stat-number"><?= $selesaiHariIni ?></span>
                    <span class="stat-desc">Pesanan hari ini</span>
                </div>
            </div>
        </section>

        <!-- ===== Antrean Pesanan ===== -->
        <section class="section-card"
                 id="antreanSection"
                 data-api-url="<?= e(BASE_URL . '/api/Kasir/pemesanan.php') ?>"
                 data-status="<?= e($statusFilter) ?>"
                 data-q="<?= e($cari) ?>"
                 data-per="<?= $perHalaman ?>"
                 data-hal="<?= $halAktif ?>"
                 data-counts="<?= e(json_encode($tabCounts, JSON_UNESCAPED_UNICODE) ?: '{}') ?>">
            <!-- Section Header -->
            <div class="antrean-header">
                <div>
                    <h2 class="antrean-title">Antrean Pesanan</h2>
                    <p class="antrean-subtitle">Kelola seluruh pesanan dalam satu tempat.</p>
                </div>
                <form class="per-page-control" id="perPageForm" method="GET" action="<?= e(BASE_URL . '/') ?>#antreanSection">
                    <input type="hidden" name="page" value="kasir">
                    <?php if ($statusFilter !== 'semua'): ?>
                        <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                    <?php endif; ?>
                    <?php if ($cari !== ''): ?>
                        <input type="hidden" name="q" value="<?= e($cari) ?>">
                    <?php endif; ?>
                    <label for="perPageSelect">Tampilkan</label>
                    <select name="per" id="perPageSelect" class="per-page-select" aria-label="Jumlah transaksi per halaman">
                        <?php foreach ($perHalamanOptions as $opsi): ?>
                            <option value="<?= (int) $opsi ?>"<?= (int) $opsi === $perHalaman ? ' selected' : '' ?>><?= (int) $opsi ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>transaksi</span>
                </form>
            </div>

            <!-- Search & Filter Toolbar -->
            <div class="antrean-toolbar">
                <form class="search-wrapper" method="GET" action="<?= e(BASE_URL . '/') ?>#antreanSection" id="searchPesananForm">
                    <input type="hidden" name="page" value="kasir">
                    <?php if ($statusFilter !== 'semua'): ?>
                        <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                    <?php endif; ?>
                    <?php if ($perHalaman !== 10): ?>
                        <input type="hidden" name="per" value="<?= $perHalaman ?>">
                    <?php endif; ?>
                    <button type="submit" class="search-submit-btn" title="Cari pesanan" aria-label="Cari pesanan">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2D231C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                    <input type="text" id="searchPesanan" name="q" value="<?= e($cari) ?>" class="search-input" placeholder="Cari ID, meja, atau nama..">
                    <?php if ($cari !== ''): ?>
                        <a href="<?= e($kasirTabUrl($statusFilter, '', 1)) ?>" class="search-clear-btn" title="Hapus pencarian" aria-label="Hapus pencarian">&times;</a>
                    <?php endif; ?>
                </form>
                <div class="filter-tabs">
                    <a href="<?= e($kasirTabUrl('semua', $cari, 1)) ?>" class="filter-tab<?= $statusFilter === 'semua' ? ' active' : '' ?>" data-filter-key="semua">Semua (<?= $tabCounts['semua'] ?>)</a>
                    <a href="<?= e($kasirTabUrl('menunggu', $cari, 1)) ?>" class="filter-tab<?= $statusFilter === 'menunggu' ? ' active' : '' ?>" data-filter-key="menunggu">Menunggu (<?= $tabCounts['menunggu'] ?>)</a>
                    <a href="<?= e($kasirTabUrl('diproses', $cari, 1)) ?>" class="filter-tab<?= $statusFilter === 'diproses' ? ' active' : '' ?>" data-filter-key="diproses">Diproses (<?= $tabCounts['diproses'] ?>)</a>
                    <a href="<?= e($kasirTabUrl('siap', $cari, 1)) ?>" class="filter-tab<?= $statusFilter === 'siap' ? ' active' : '' ?>" data-filter-key="siap">Siap (<?= $tabCounts['siap'] ?>)</a>
                    <a href="<?= e($kasirTabUrl('selesai', $cari, 1)) ?>" class="filter-tab<?= $statusFilter === 'selesai' ? ' active' : '' ?>" data-filter-key="selesai">Selesai (<?= $tabCounts['selesai'] ?>)</a>
                    <?php if ($tabCounts['batal'] > 0 || $statusFilter === 'batal'): ?>
                        <a href="<?= e($kasirTabUrl('batal', $cari, 1)) ?>" class="filter-tab<?= $statusFilter === 'batal' ? ' active' : '' ?>" data-filter-key="batal">Batal (<?= $tabCounts['batal'] ?>)</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabel Antrean dalam Box Card Ber-border -->
            <div class="table-container-card">
                <div class="table-responsive">
                    <table class="custom-table">
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
                        <tbody id="antreanTbody">
                            <?php if ($antrean === []): ?>
                                <tr id="emptyServerRow">
                                    <td colspan="7" class="empty-table-cell">
                                        <?= ($statusFilter !== 'semua' || $cari !== '')
                                            ? 'Tidak ada pesanan yang cocok dengan filter atau pencarian ini.'
                                            : 'Belum ada pesanan yang masuk di basis data.' ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($antrean as $row): ?>
                                    <?php
                                    $pid          = (int) ($row['id'] ?? 0);
                                    $nomor        = (string) ($row['nomor'] ?? '');
                                    $noMeja       = (string) ($row['no_meja'] ?? '-');
                                    $namaPel      = (string) ($row['nama_pelanggan'] ?? '');
                                    $jumlahItem   = (int) ($row['jumlah_item'] ?? 0);
                                    $ringkasan    = (string) ($row['ringkasan_item'] ?? '');
                                    $totalHarga   = (int) ($row['total_harga'] ?? 0);
                                    $status       = (string) ($row['status_pemesanan'] ?? 'menunggu_pembayaran');
                                    $metode       = (string) ($row['metode_pembayaran'] ?? 'tunai');
                                    $statusBayar  = (string) ($row['status_pembayaran'] ?? 'pending');
                                    $isPaid       = $statusBayar === 'paid' || in_array($status, ['dibayar', 'diproses', 'siap', 'selesai'], true);

                                    if ($status === 'menunggu_pembayaran') {
                                        $dataFilter = 'menunggu';
                                        $badgeStatusClass = 'badge-menunggu';
                                        $badgeStatusText  = 'Menunggu';
                                    } elseif ($status === 'dibayar' || $status === 'diproses') {
                                        $dataFilter = 'diproses';
                                        $badgeStatusClass = 'badge-diproses';
                                        $badgeStatusText  = 'Diproses';
                                    } elseif ($status === 'siap') {
                                        $dataFilter = 'siap';
                                        $badgeStatusClass = 'badge-siap';
                                        $badgeStatusText  = 'Siap';
                                    } elseif ($status === 'selesai') {
                                        $dataFilter = 'selesai';
                                        $badgeStatusClass = 'badge-selesai';
                                        $badgeStatusText  = 'Selesai';
                                    } else {
                                        $dataFilter = 'batal';
                                        $badgeStatusClass = 'badge-batal';
                                        $badgeStatusText  = 'Batal';
                                    }

                                    if ($isPaid) {
                                        $badgeBayarClass = 'badge-lunas';
                                        $badgeBayarText  = 'Lunas';
                                    } elseif ($statusBayar === 'expire') {
                                        $badgeBayarClass = 'badge-batal';
                                        $badgeBayarText  = 'Kedaluwarsa';
                                    } elseif (in_array($statusBayar, ['cancel', 'deny', 'failure'], true)) {
                                        $badgeBayarClass = 'badge-batal';
                                        $badgeBayarText  = 'Batal';
                                    } else {
                                        $badgeBayarClass = 'badge-belum-bayar';
                                        $badgeBayarText  = 'Belum Bayar';
                                    }
                                    ?>
                                    <tr data-status="<?= e($dataFilter) ?>">
                                        <td class="td-id">
                                            <div class="order-code"><?= e($nomor) ?></div>
                                            <div class="order-item-count"><?= $jumlahItem ?> item</div>
                                        </td>
                                        <td class="td-meja"><span class="meja-num"><?= e($noMeja) ?></span></td>
                                        <td class="td-item">
                                            <div class="item-list"><?= e($ringkasan !== '' ? $ringkasan : '-') ?></div>
                                            <?php if ($namaPel !== ''): ?>
                                                <div class="order-item-count"><?= e($namaPel) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="td-total"><span class="order-total"><?= e(format_rupiah($totalHarga)) ?></span></td>
                                        <td class="td-bayar">
                                            <span class="badge-pill <?= e($badgeBayarClass) ?>"><?= e($badgeBayarText) ?></span>
                                            <div class="order-item-count"><?= e(strtoupper($metode)) ?></div>
                                        </td>
                                        <td class="td-status"><span class="badge-pill <?= e($badgeStatusClass) ?>"><?= e($badgeStatusText) ?></span></td>
                                        <td class="td-aksi">
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action-solid btn-detail-order" data-order-id="<?= $pid ?>">Detail</button>
                                                <?php if ($status === 'menunggu_pembayaran' && $metode === 'tunai'): ?>
                                                    <button type="button" class="btn-action-outline btn-open-modal" data-order-id="<?= $pid ?>" data-order-nomor="<?= e($nomor) ?>" data-aksi="bayar_tunai">Bayar</button>
                                                <?php elseif ($status === 'menunggu_pembayaran' && $metode === 'qris'): ?>
                                                    <button type="button" class="btn-action-outline btn-open-modal" data-order-id="<?= $pid ?>" data-order-nomor="<?= e($nomor) ?>" data-aksi="cek_qris">Cek QRIS</button>
                                                <?php elseif ($status === 'diproses'): ?>
                                                    <button type="button" class="btn-action-outline btn-open-modal" data-order-id="<?= $pid ?>" data-order-nomor="<?= e($nomor) ?>" data-aksi="siap">Siap Antar</button>
                                                <?php elseif ($status === 'siap'): ?>
                                                    <button type="button" class="btn-action-outline btn-open-modal" data-order-id="<?= $pid ?>" data-order-nomor="<?= e($nomor) ?>" data-aksi="selesai">Selesai</button>
                                                <?php elseif ($status === 'selesai'): ?>
                                                    <button type="button" class="btn-action-outline btn-print-order" data-order-id="<?= $pid ?>">Cetak Struk</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bar Pagination (kustom kelipatan 5 per halaman) -->
            <div class="pagination-bar" id="paginationBar">
                <div class="pagination-info" id="paginationInfo">
                    <?php if ($totalData > 0): ?>
                        Menampilkan <strong><?= $dariBaris ?>&ndash;<?= $sampaiBaris ?></strong> dari <strong><?= $totalData ?></strong> transaksi
                    <?php else: ?>
                        Menampilkan <strong>0</strong> dari <strong>0</strong> transaksi
                    <?php endif; ?>
                </div>
                <nav class="pagination-controls" id="paginationControls" aria-label="Navigasi halaman antrean pesanan"<?= $totalHalaman > 1 ? '' : ' style="display:none;"' ?>>
                    <?php if ($totalHalaman > 1): ?>
                        <?php if ($halAktif > 1): ?>
                            <a href="<?= e($kasirTabUrl($statusFilter, $cari, $halAktif - 1)) ?>" class="page-btn" data-page-target="<?= $halAktif - 1 ?>">&lsaquo; Sebelumnya</a>
                        <?php else: ?>
                            <span class="page-btn disabled" aria-disabled="true">&lsaquo; Sebelumnya</span>
                        <?php endif; ?>

                        <?php foreach ($pageSlots as $slot): ?>
                            <?php if ($slot === '...'): ?>
                                <span class="page-ellipsis" aria-hidden="true">&hellip;</span>
                            <?php elseif ((int) $slot === $halAktif): ?>
                                <span class="page-btn active" aria-current="page"><?= (int) $slot ?></span>
                            <?php else: ?>
                                <a href="<?= e($kasirTabUrl($statusFilter, $cari, (int) $slot)) ?>" class="page-btn" data-page-target="<?= (int) $slot ?>"><?= (int) $slot ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($halAktif < $totalHalaman): ?>
                            <a href="<?= e($kasirTabUrl($statusFilter, $cari, $halAktif + 1)) ?>" class="page-btn" data-page-target="<?= $halAktif + 1 ?>">Berikutnya &rsaquo;</a>
                        <?php else: ?>
                            <span class="page-btn disabled" aria-disabled="true">Berikutnya &rsaquo;</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </nav>
            </div>
        </section>

        <!-- ===== Bottom: Chart + Donut ===== -->
        <div class="bottom-grid">

            <!-- Card Kiri: Pesanan per Jam -->
            <div class="bottom-card">
                <h3 class="bottom-card-title">Pesanan per Jam</h3>
                <div class="chart-area">
                    <svg viewBox="0 0 330 135" style="width:100%;height:auto;display:block;overflow:visible;">
                        <!-- Y-axis labels & Horizontal Grid Lines (8 baris: 0 .. yMax) -->
                        <?php for ($g = 0; $g <= 7; $g++): ?>
                            <?php
                            $yGrid = 13 + $g * 14;
                            $yVal  = $yMax - ($g * $yStep);
                            ?>
                            <text x="5" y="<?= $yGrid + 3 ?>" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif"><?= $yVal ?></text>
                            <line x1="25" y1="<?= $yGrid ?>" x2="325" y2="<?= $yGrid ?>" stroke="#EFE8DF" stroke-width="1"/>
                        <?php endfor; ?>

                        <!-- Vertical Grid Lines & X-axis labels -->
                        <?php foreach ($xAxisIndices as $slotIdx): ?>
                            <?php if (isset($chartPoints[$slotIdx])): ?>
                                <?php $ptAxis = $chartPoints[$slotIdx]; ?>
                                <line x1="<?= $ptAxis['x'] ?>" y1="13" x2="<?= $ptAxis['x'] ?>" y2="111" stroke="#EFE8DF" stroke-width="1"/>
                                <text x="<?= $ptAxis['x'] ?>" y="126" text-anchor="middle" font-size="8.5" fill="#8C7E74" font-family="Poppins, sans-serif"><?= e($ptAxis['label']) ?></text>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <!-- Polyline solid -->
                        <polyline points="<?= e($polylineStr) ?>"
                                  fill="none"
                                  stroke="#5A3E2B"
                                  stroke-width="2"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"/>

                        <!-- Dots dan Label Angka Nilai di atas titik -->
                        <?php foreach ($chartPoints as $pt): ?>
                            <circle cx="<?= $pt['x'] ?>" cy="<?= $pt['y'] ?>" r="3.5" fill="#5A3E2B"/>
                            <text x="<?= $pt['x'] ?>" y="<?= max(8, $pt['y'] - 6) ?>" text-anchor="middle" font-size="7.5" font-weight="600" fill="#5A3E2B" font-family="Poppins, sans-serif"><?= (int) $pt['val'] ?></text>
                        <?php endforeach; ?>
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

                            <?php foreach ($donutSegments as $seg): ?>
                                <?php if ($seg['dash_len'] > 0): ?>
                                    <circle cx="60" cy="60" r="45"
                                            fill="none"
                                            stroke="<?= e($seg['warna']) ?>"
                                            stroke-width="20"
                                            stroke-linecap="butt"
                                            stroke-dasharray="<?= number_format($seg['dash_len'], 2, '.', '') ?> <?= number_format($seg['dash_gap'], 2, '.', '') ?>"
                                            stroke-dashoffset="-<?= number_format($seg['dashoffset'], 2, '.', '') ?>"
                                            transform="rotate(-90 60 60)"/>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <!-- Lubang tengah (white circle) -->
                            <circle cx="60" cy="60" r="33" fill="white"/>

                            <!-- Teks tengah -->
                            <text x="60" y="56"
                                  text-anchor="middle"
                                  font-size="16"
                                  font-weight="700"
                                  fill="#2D231C"
                                  font-family="Poppins, sans-serif"><?= $totalItemLunas ?></text>
                            <text x="60" y="68"
                                  text-anchor="middle"
                                  font-size="7.5"
                                  fill="#8C7E74"
                                  font-family="Poppins, sans-serif">Item Lunas</text>
                        </svg>
                    </div>
                    <div class="chart-legends">
                        <?php if ($donutSegments === []): ?>
                            <div class="legend-item">
                                <span class="legend-dot" style="background:#E8D5BC;"></span>
                                <div class="legend-text">
                                    <span class="legend-name">Belum ada data</span>
                                    <span class="legend-val">0 item | 0%</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($donutSegments as $seg): ?>
                                <div class="legend-item">
                                    <span class="legend-dot" style="background:<?= e($seg['warna']) ?>;"></span>
                                    <div class="legend-text">
                                        <span class="legend-name"><?= e($seg['nama']) ?></span>
                                        <span class="legend-val"><?= (int) $seg['jumlah'] ?> item | <?= (int) $seg['persen'] ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- /bottom-grid -->

    </main>

    <!-- ==================== MODAL KONFIRMASI AKSI PESANAN (POST + CSRF) ==================== -->
    <div class="modal-overlay" id="confirmModal">
        <form class="modal-box" id="confirmModalForm" method="POST" action="<?= e(url('kasir')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="pesanan_id" id="confirmOrderId" value="">
            <input type="hidden" name="aksi" id="confirmOrderAction" value="">
            <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
            <input type="hidden" name="return_q" value="<?= e($cari) ?>">
            <input type="hidden" name="return_hal" value="<?= $halAktif ?>">
            <input type="hidden" name="return_per" value="<?= $perHalaman ?>">
            <div class="modal-icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="16 10 11 15 8 12"></polyline>
                </svg>
            </div>
            <h3 class="modal-title" id="confirmModalTitle">Konfirmasi Aksi</h3>
            <p class="modal-desc" id="confirmModalDesc">Pastikan tindakan pada pesanan sudah benar.</p>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" id="btnCancelModal">Batal</button>
                <button type="submit" class="btn-modal-confirm" id="btnConfirmModal">Lanjutkan</button>
            </div>
        </form>
    </div>

    <!-- ==================== MODAL DETAIL & STRUK PESANAN ==================== -->
    <div class="modal-overlay" id="detailOrderModal">
        <div class="modal-box detail-modal-box">
            <div class="detail-modal-header">
                <div>
                    <h3 class="modal-title" id="detailOrderTitle" style="margin-bottom:2px;">Detail Pesanan</h3>
                    <p class="antrean-subtitle" id="detailOrderSubtitle"></p>
                </div>
                <button type="button" class="detail-modal-close" id="btnCloseDetailModal" aria-label="Tutup">&times;</button>
            </div>

            <div class="detail-meta-grid">
                <div>
                    <span class="detail-meta-label">Pelanggan</span>
                    <span class="detail-meta-value" id="detailPelanggan">-</span>
                </div>
                <div>
                    <span class="detail-meta-label">Meja</span>
                    <span class="detail-meta-value" id="detailMeja">-</span>
                </div>
                <div>
                    <span class="detail-meta-label">Pembayaran</span>
                    <span class="detail-meta-value" id="detailPembayaran">-</span>
                </div>
                <div>
                    <span class="detail-meta-label">Status</span>
                    <span class="detail-meta-value" id="detailStatus">-</span>
                </div>
                <div>
                    <span class="detail-meta-label">Kasir Pemroses</span>
                    <span class="detail-meta-value" id="detailKasir">-</span>
                </div>
            </div>

            <div class="detail-note-box" id="detailNoteBox" style="display:none;"></div>

            <div class="detail-items-list" id="detailItemsList"></div>

            <div class="detail-sub-row">
                <span>Subtotal</span>
                <span id="detailSubtotal">Rp 0</span>
            </div>
            <div class="detail-sub-row">
                <span>Pajak (10%)</span>
                <span id="detailPajak">Rp 0</span>
            </div>

            <div class="detail-total-row">
                <span>Total (termasuk pajak)</span>
                <span id="detailTotalHarga">Rp 0</span>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-modal-danger" id="btnDetailCancelOrder" style="display:none;">Batalkan Pesanan</button>
                <button type="button" class="btn-modal-cancel" id="btnCloseDetailFooter">Tutup</button>
                <button type="button" class="btn-modal-confirm" id="btnDetailPrint">Cetak Struk</button>
            </div>
        </div>
    </div>

    <!-- Script -->
    <script src="<?= e(asset('js/js-kasir/dashboard.js')) ?>"></script>

</body>
</html>
