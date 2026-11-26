<?php
/**
 * Pengujian Paket 3: LaporanModel (hanya membaca database `quickbrew_test` dengan fixture deterministik).
 */
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());

const TEST_SERVER_KEY = 'DUMMY-Mid-server-UNITTEST-ONLY-000000';
const TEST_CLIENT_KEY = 'DUMMY-Mid-client-UNITTEST-ONLY-000000';
const TEST_APP_SECRET = 'dummy-app-secret-unittest-only-0123456789';

putenv('DB_NAME=quickbrew_test');
putenv('MIDTRANS_SERVER_KEY=' . TEST_SERVER_KEY);
putenv('MIDTRANS_CLIENT_KEY=' . TEST_CLIENT_KEY);
putenv('MIDTRANS_PROD=false');
putenv('APP_SECRET=' . TEST_APP_SECRET);

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Models/MenuModel.php';
require_once APP_ROOT . '/app/Models/MejaModel.php';
require_once APP_ROOT . '/app/Models/PemesananModel.php';
require_once APP_ROOT . '/app/Models/LaporanModel.php';

$pdo      = db();
$activeDb = (string) $pdo->query('SELECT current_database()')->fetchColumn();
if ($activeDb !== 'quickbrew_test') {
    fwrite(STDERR, "KEAMANAN: Koneksi aktif bukan quickbrew_test ({$activeDb}). Dibatalkan.\n");
    exit(1);
}

$pdo->exec((string) file_get_contents(APP_ROOT . '/SQL/QuickBrew.sql'));
$pdo->exec((string) file_get_contents(APP_ROOT . '/SQL/migrations/001_add_pembayaran_qris_data.sql'));
$pdo->exec((string) file_get_contents(APP_ROOT . '/SQL/migrations/002_pembatasan_laju.sql'));

$passed = 0;
$failed = 0;

function check(bool $cond, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "[PASS] {$label}\n";
    } else {
        $failed++;
        echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function assert_throws_invalid(callable $fn, string $label): void
{
    try {
        $fn();
        check(false, $label, 'Tidak melempar InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        check($e->getMessage() !== '', $label);
    } catch (Throwable $e) {
        check(false, $label, 'Melempar tipe lain: ' . get_class($e));
    }
}

/**
 * Buat fixture pesanan + detail + pembayaran deterministik di quickbrew_test.
 *
 * @param array<int, array{menu_id: int, harga: int, jumlah: int}> $items
 */
function buat_fixture_pembayaran(
    int $mejaId,
    array $items,
    string $metode,
    string $statusBayar,
    string $statusPemesanan,
    string $waktuPemesanan,
    ?string $waktuPembayaran = null
): int {
    $db    = db();
    $lines = [];
    $sub   = 0;
    foreach ($items as $it) {
        $s = $it['harga'] * $it['jumlah'];
        $sub += $s;
        $lines[] = [
            'menu_id'  => $it['menu_id'],
            'harga'    => $it['harga'],
            'jumlah'   => $it['jumlah'],
            'subtotal' => $s,
        ];
    }
    $tax   = (int) round($sub * PemesananModel::TAX_RATE);
    $total = $sub + $tax;

    $stmtP = $db->prepare("
        INSERT INTO pemesanan (meja_id, nama_pelanggan, email_pelanggan, total_harga, status_pemesanan, waktu_pemesanan)
        VALUES (?, 'Pelanggan Laporan', 'laporan@example.com', ?, ?, ?)
        RETURNING id
    ");
    $stmtP->execute([$mejaId, $total, $statusPemesanan, $waktuPemesanan]);
    $pid = (int) $stmtP->fetchColumn();

    $stmtD = $db->prepare("
        INSERT INTO detail_pemesanan (menu_id, pemesanan_id, harga, jumlah, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($lines as $ln) {
        $stmtD->execute([$ln['menu_id'], $pid, $ln['harga'], $ln['jumlah'], $ln['subtotal']]);
    }

    $stmtB = $db->prepare("
        INSERT INTO pembayaran (pemesanan_id, midtrans_order_id, waktu_pembayaran, status_pembayaran, metode_pembayaran, total_bayar)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $oid = $metode === 'qris' ? ('TRX-LAP-' . $pid) : null;
    $stmtB->execute([$pid, $oid, $waktuPembayaran, $statusBayar, $metode, $total]);

    return $total;
}

echo "=== Pengujian Paket 3: LaporanModel (DB: {$activeDb}) ===\n\n";

$mejaId = (int) $pdo->query('SELECT id FROM meja ORDER BY id LIMIT 1')->fetchColumn();

// Ambil 4 menu lintas 3 kategori (Minuman, Makanan, Dessert)
$menuAmericano = $pdo->query("SELECT id, harga, kategori_id FROM menu WHERE nama_menu = 'Americano'")->fetch();
$menuMatcha    = $pdo->query("SELECT id, harga, kategori_id FROM menu WHERE nama_menu = 'Matcha Latte'")->fetch();
$menuFries     = $pdo->query("SELECT id, harga, kategori_id FROM menu WHERE nama_menu = 'French Fries'")->fetch();
$menuTiramisu  = $pdo->query("SELECT id, harga, kategori_id FROM menu WHERE nama_menu = 'Tiramisu'")->fetch();

$katMinumanId = (int) $menuAmericano['kategori_id'];
$katMakananId = (int) $menuFries['kategori_id'];

// =========================================================================
// 1. Validasi Rentang Tanggal & Parameter InvalidArgumentException
// =========================================================================
assert_throws_invalid(static fn () => LaporanModel::ringkasanPeriode('2026-02-30', '2026-03-01'), 'Menolak tanggal kalender tidak valid (2026-02-30)');
assert_throws_invalid(static fn () => LaporanModel::harian('10-05-2026', '2026-05-12'), 'Menolak format selain Y-m-d');
assert_throws_invalid(static fn () => LaporanModel::perJam('2026-05-10', '2026-05-05'), 'Menolak dari > sampai');
assert_throws_invalid(static fn () => LaporanModel::perMetode('2025-01-01', '2026-01-02'), 'Menolak rentang > 366 hari (367 hari)');
assert_throws_invalid(static fn () => LaporanModel::menuTerlaris('2026-05-01', '2026-05-03', 0), 'Menolak limit <= 0 pada menuTerlaris()');
assert_throws_invalid(static fn () => LaporanModel::menuTerlaris('2026-05-01', '2026-05-03', 5, 0), 'Menolak kategoriId <= 0 pada menuTerlaris()');

// =========================================================================
// 2. Periode Kosong (Belum Ada Transaksi Sama Sekali)
// =========================================================================
$kosongRingkas = LaporanModel::ringkasanPeriode('2026-01-01', '2026-01-03');
$kosongHarian  = LaporanModel::harian('2026-01-01', '2026-01-03');
$kosongJam     = LaporanModel::perJam('2026-01-01', '2026-01-03');
$kosongMetode  = LaporanModel::perMetode('2026-01-01', '2026-01-03');
$kosongKat     = LaporanModel::perKategori('2026-01-01', '2026-01-03');
$kosongMenu    = LaporanModel::menuTerlaris('2026-01-01', '2026-01-03');

check(
    $kosongRingkas['total_pendapatan'] === 0
    && $kosongRingkas['jumlah_transaksi'] === 0
    && $kosongRingkas['item_terjual'] === 0
    && $kosongRingkas['rata_rata_nilai'] === 0.0
    && $kosongRingkas['perbandingan']['total_pendapatan']['selisih'] === 0
    && $kosongRingkas['perbandingan']['total_pendapatan']['persen'] === 0.0,
    'Periode kosong: ringkasanPeriode mengembalikan nol dan persen perbandingan 0 -> 0 adalah 0.0'
);
check(
    count($kosongHarian) === 3
    && $kosongHarian[0]['tanggal'] === '2026-01-01'
    && $kosongHarian[1]['total_pendapatan'] === 0
    && $kosongHarian[2]['tanggal'] === '2026-01-03',
    'Periode kosong: harian() mengisi seluruh tanggal dengan 0'
);
check(
    count($kosongJam) === 24
    && $kosongJam[0]['jam'] === 0
    && $kosongJam[23]['jam'] === 23
    && $kosongJam[12]['total_pendapatan'] === 0,
    'Periode kosong: perJam() selalu mengembalikan 24 baris (jam 0..23)'
);
check($kosongMetode === [] && $kosongMenu === [] && count($kosongKat) === 3, 'Periode kosong: perMetode & menuTerlaris kosong, perKategori tetap memuat 3 kategori');

// =========================================================================
// 3. Siapkan Fixture Deterministik Lintas Periode & Batas Tengah Malam
//    - Periode Sebelumnya (3 hari): 2026-05-07 .. 2026-05-09 -> 1 paid
//    - Periode Uji Utama  (3 hari): 2026-05-10 .. 2026-05-12 (tgl 11 sengaja kosong!)
//    - Luar batas: 2026-05-13 00:00:00 (tidak boleh masuk periode 10..12!)
// =========================================================================

// A. Periode Sebelumnya (2026-05-08 09:30:00): 1x Matcha Latte (24.000) -> sub=24.000, tax=2.400, total=26.400
$prevTotal1 = buat_fixture_pembayaran(
    $mejaId,
    [['menu_id' => (int) $menuMatcha['id'], 'harga' => 24000, 'jumlah' => 1]],
    'qris',
    'paid',
    'selesai',
    '2026-05-08 09:28:00',
    '2026-05-08 09:30:00'
);

// B. Periode Uji: Hari 1 awal persis tengah malam (2026-05-10 00:00:00)
//    2x Americano (20.000) = 40.000 -> tax 4.000 -> total 44.000 (QRIS, paid)
$mainTotal1 = buat_fixture_pembayaran(
    $mejaId,
    [['menu_id' => (int) $menuAmericano['id'], 'harga' => 20000, 'jumlah' => 2]],
    'qris',
    'paid',
    'selesai',
    '2026-05-09 23:58:00', // waktu_pemesanan kemarin, tapi dibayar 10 Mei 00:00:00 -> wajib masuk 10 Mei!
    '2026-05-10 00:00:00'
);

// C. Periode Uji: Hari 1 siang (2026-05-10 14:15:00)
//    2x French Fries (26.000) = 52.000 + 1x Tiramisu (19.000) = 19.000 -> sub=71.000, tax=7.100, total=78.100 (Tunai, paid)
$mainTotal2 = buat_fixture_pembayaran(
    $mejaId,
    [
        ['menu_id' => (int) $menuFries['id'], 'harga' => 26000, 'jumlah' => 2],
        ['menu_id' => (int) $menuTiramisu['id'], 'harga' => 19000, 'jumlah' => 1],
    ],
    'tunai',
    'paid',
    'diproses',
    '2026-05-10 14:10:00',
    '2026-05-10 14:15:00'
);

// D. Periode Uji: Hari 3 akhir persis sebelum tengah malam (2026-05-12 23:59:59)
//    1x Tiramisu (19.000) -> sub=19.000, tax=1.900, total=20.900 (QRIS, paid)
//    Catatan tie: Americano (qty 2, rev 40.000), French Fries (qty 2, rev 52.000), Tiramisu (qty 2, rev 38.000)
//    Tambah 1 lagi transaksi 1x Americano (20.000) & 1x Tiramisu? Tidak: ubah agar Americano dan Matcha punya total_terjual=2 & pendapatan_menu=48.000 sama persis?
//    Kita tambahkan di jam 23:59:59: 1x Matcha Latte (24.000) -> tunggu, agar nilai tie sama persis (qty=2, pendapatan_menu=52.000):
//    French Fries sudah qty=2, rev=52.000. Jika kita beri pesanan lagi 2x Thai Tea/Matcha?
//    Lebih bersih: ubah item D menjadi 1x Tiramisu (19.000) + 1x Matcha (26.000 -> snapshot harga berbeda? Bisa, atau 2x Matcha 24k = 48k & 2x Choco Latte 24k = 48k).
$menuChoco = $pdo->query("SELECT id, harga FROM menu WHERE nama_menu = 'Choco Latte'")->fetch();
// Tambah 2x Matcha (24.000 -> 48.000) + 2x Choco Latte (24.000 -> 48.000) di 2026-05-12 23:59:59!
// Maka French Fries = qty 2, rev 52.000 (rank 1), Choco Latte = qty 2, rev 48.000 (rank 2), Matcha Latte = qty 2, rev 48.000 (rank 2), Americano = qty 2, rev 40.000 (rank 4), Tiramisu = qty 1, rev 19.000 (rank 5) -> tie 2, 2, 4 (atau mari buat rank 1 tie: 3x Matcha 24k = 72k & 3x Choco 24k = 72k -> rank 1, 1, 3!).
$mainTotal3 = buat_fixture_pembayaran(
    $mejaId,
    [
        ['menu_id' => (int) $menuMatcha['id'], 'harga' => 24000, 'jumlah' => 3],
        ['menu_id' => (int) $menuChoco['id'],  'harga' => 24000, 'jumlah' => 3],
    ],
    'qris',
    'paid',
    'selesai',
    '2026-05-12 23:58:00',
    '2026-05-12 23:59:59'
);

// E. Pembayaran NON-PAID di dalam rentang (TIDAK boleh dihitung!): pending, cancel, expire, deny, failure
foreach (['pending', 'cancel', 'expire', 'deny', 'failure'] as $stNonPaid) {
    buat_fixture_pembayaran(
        $mejaId,
        [['menu_id' => (int) $menuAmericano['id'], 'harga' => 20000, 'jumlah' => 5]],
        'qris',
        $stNonPaid,
        $stNonPaid === 'pending' ? 'menunggu_pembayaran' : 'batal',
        '2026-05-11 10:00:00',
        $stNonPaid === 'pending' ? null : '2026-05-11 10:05:00'
    );
}

// F. Transaksi tepat 2026-05-13 00:00:00 (luar batas setengah terbuka < 2026-05-13 00:00:00 -> TIDAK boleh masuk periode 10..12)
buat_fixture_pembayaran(
    $mejaId,
    [['menu_id' => (int) $menuAmericano['id'], 'harga' => 20000, 'jumlah' => 4]],
    'tunai',
    'paid',
    'selesai',
    '2026-05-13 00:00:00',
    '2026-05-13 00:00:00'
);

// Hitung ekspektasi periode 2026-05-10 .. 2026-05-12
$expectedRev = $mainTotal1 + $mainTotal2 + $mainTotal3; // 44000 + 78100 + 158400 = 280500
$expectedTx  = 3;
$expectedQty = 2 + 3 + 6; // 11
$expectedAvg = round($expectedRev / $expectedTx, 2); // 93500.0

$ringkas = LaporanModel::ringkasanPeriode('2026-05-10', '2026-05-12');

check(
    $ringkas['total_pendapatan'] === $expectedRev
    && $ringkas['jumlah_transaksi'] === $expectedTx
    && $ringkas['item_terjual'] === $expectedQty
    && $ringkas['rata_rata_nilai'] === $expectedAvg
    && is_int($ringkas['total_pendapatan'])
    && is_int($ringkas['jumlah_transaksi'])
    && is_int($ringkas['item_terjual'])
    && is_float($ringkas['rata_rata_nilai']),
    'ringkasanPeriode hanya menghitung paid dalam batas tengah malam tepat dan mengembalikan tipe int/float PHP'
);

$expectedPersenRev = round((($expectedRev - $prevTotal1) / $prevTotal1) * 100, 1);
check(
    $ringkas['periode_sebelumnya']['dari'] === '2026-05-07'
    && $ringkas['periode_sebelumnya']['sampai'] === '2026-05-09'
    && $ringkas['periode_sebelumnya']['total_pendapatan'] === $prevTotal1
    && $ringkas['perbandingan']['total_pendapatan']['selisih'] === ($expectedRev - $prevTotal1)
    && $ringkas['perbandingan']['total_pendapatan']['persen'] === $expectedPersenRev
    && $ringkas['perbandingan']['jumlah_transaksi']['selisih'] === 2
    && $ringkas['perbandingan']['jumlah_transaksi']['persen'] === 200.0,
    'ringkasanPeriode membandingkan periode sebelumnya dengan panjang hari yang sama secara akurat'
);

// Kasus periode sebelumnya 0 namun periode sekarang > 0 -> persen === null
$ringkasDariNol = LaporanModel::ringkasanPeriode('2026-05-08', '2026-05-08');
check(
    $ringkasDariNol['periode_sebelumnya']['total_pendapatan'] === 0
    && $ringkasDariNol['total_pendapatan'] === $prevTotal1
    && $ringkasDariNol['perbandingan']['total_pendapatan']['selisih'] === $prevTotal1
    && $ringkasDariNol['perbandingan']['total_pendapatan']['persen'] === null,
    'Perbandingan dengan periode sebelumnya 0 -> >0 mengembalikan persen = null (tidak terdefinisi)'
);

// Uji harian() mengisi tanggal 2026-05-11 (yang hanya berisi non-paid) sebagai 0
$harian = LaporanModel::harian('2026-05-10', '2026-05-12');
check(
    count($harian) === 3
    && $harian[0]['tanggal'] === '2026-05-10' && $harian[0]['total_pendapatan'] === ($mainTotal1 + $mainTotal2) && $harian[0]['item_terjual'] === 5
    && $harian[1]['tanggal'] === '2026-05-11' && $harian[1]['total_pendapatan'] === 0 && $harian[1]['jumlah_transaksi'] === 0 && $harian[1]['item_terjual'] === 0
    && $harian[2]['tanggal'] === '2026-05-12' && $harian[2]['total_pendapatan'] === $mainTotal3 && $harian[2]['item_terjual'] === 6,
    'harian() mengisi tanggal kosong/non-paid (2026-05-11) dengan nol dan menghormati batas 00:00:00 & 23:59:59'
);

// Uji menuTerlaris() peringkat tie menggunakan RANK() (1, 1, 3) & urutan deterministik nama_menu ASC
$topMenus = LaporanModel::menuTerlaris('2026-05-10', '2026-05-12', 10);
check(
    count($topMenus) === 5
    && $topMenus[0]['nama_menu'] === 'Choco Latte'  && $topMenus[0]['peringkat'] === 1 && $topMenus[0]['total_terjual'] === 3 && $topMenus[0]['pendapatan_menu'] === 72000
    && $topMenus[1]['nama_menu'] === 'Matcha Latte' && $topMenus[1]['peringkat'] === 1 && $topMenus[1]['total_terjual'] === 3 && $topMenus[1]['pendapatan_menu'] === 72000
    && $topMenus[2]['nama_menu'] === 'French Fries' && $topMenus[2]['peringkat'] === 3 && $topMenus[2]['total_terjual'] === 2 && $topMenus[2]['pendapatan_menu'] === 52000
    && $topMenus[3]['nama_menu'] === 'Americano'    && $topMenus[3]['peringkat'] === 4 && $topMenus[3]['total_terjual'] === 2 && $topMenus[3]['pendapatan_menu'] === 40000
    && $topMenus[4]['nama_menu'] === 'Tiramisu'     && $topMenus[4]['peringkat'] === 5 && $topMenus[4]['total_terjual'] === 1 && $topMenus[4]['pendapatan_menu'] === 19000,
    'menuTerlaris() menghitung RANK() tie (1, 1, 3) dan mengurutkan deterministik dengan nama_menu ASC'
);

// Filter kategoriId di menuTerlaris()
$topMakanan = LaporanModel::menuTerlaris('2026-05-10', '2026-05-12', 10, $katMakananId);
check(
    count($topMakanan) === 1
    && $topMakanan[0]['nama_menu'] === 'French Fries'
    && $topMakanan[0]['peringkat'] === 1,
    'menuTerlaris() mendukung filter kategoriId'
);

// Uji perJam()
$perJam = LaporanModel::perJam('2026-05-10', '2026-05-12');
check(
    count($perJam) === 24
    && $perJam[0]['jumlah_transaksi'] === 1 && $perJam[0]['total_pendapatan'] === $mainTotal1
    && $perJam[14]['jumlah_transaksi'] === 1 && $perJam[14]['total_pendapatan'] === $mainTotal2
    && $perJam[23]['jumlah_transaksi'] === 1 && $perJam[23]['total_pendapatan'] === $mainTotal3
    && $perJam[10]['jumlah_transaksi'] === 0 && $perJam[10]['total_pendapatan'] === 0,
    'perJam() selalu mengembalikan 24 baris dengan jam batas 00:00, 14:00, dan 23:00 akurat'
);

// Uji perMetode()
$perMetode = LaporanModel::perMetode('2026-05-10', '2026-05-12');
check(
    count($perMetode) === 2
    && $perMetode[0]['metode'] === 'qris'  && $perMetode[0]['jumlah_transaksi'] === 2 && $perMetode[0]['total_pendapatan'] === ($mainTotal1 + $mainTotal3)
    && $perMetode[1]['metode'] === 'tunai' && $perMetode[1]['jumlah_transaksi'] === 1 && $perMetode[1]['total_pendapatan'] === $mainTotal2,
    'perMetode() mengelompokkan qris vs tunai secara akurat'
);

// Uji perKategori() & Relasi Akuntansi: SUM(pendapatan_menu) + total_pajak === total_pendapatan
$perKategori    = LaporanModel::perKategori('2026-05-10', '2026-05-12');
$sumMenuAll     = array_sum(array_column($topMenus, 'pendapatan_menu'));
$sumKategoriAll = array_sum(array_column($perKategori, 'pendapatan_menu'));
$totalPajak     = (int) round(40000 * 0.1) + (int) round(71000 * 0.1) + (int) round(144000 * 0.1);

check(
    count($perKategori) === 3
    && $sumMenuAll === $sumKategoriAll
    && ($sumMenuAll + $totalPajak) === $ringkas['total_pendapatan'],
    'Relasi akuntansi terbukti: SUM(pendapatan_menu seluruh menu) + pajak (10%) === ringkasanPeriode[total_pendapatan]'
);

echo "\nRingkasan Paket 3 (LaporanModel): {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
