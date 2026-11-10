<?php
/**
 * Pengujian Paket 3: Fondasi Model Pesanan & Pembayaran Kasir.
 *   - Hanya menggunakan database `quickbrew_test`.
 *   - Seluruh panggilan Midtrans di-stub.
 */
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());

$logFile = sys_get_temp_dir() . '/quickbrew_kasir_model_test_' . getmypid() . '.log';
@unlink($logFile);
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

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
require_once APP_ROOT . '/app/Services/PembayaranService.php';

$pdo = db();
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

echo "=== Pengujian Paket 3: Model Pesanan & Kasir (DB: {$activeDb}) ===\n\n";

$mejaId      = (int) $pdo->query('SELECT id FROM meja ORDER BY id LIMIT 1')->fetchColumn();
$americanoId = (int) $pdo->query("SELECT id FROM menu WHERE nama_menu = 'Americano'")->fetchColumn();
$croissantId = (int) $pdo->query("SELECT id FROM menu WHERE nama_menu = 'Croissant Butter'")->fetchColumn();
$kasirId     = (int) $pdo->query("SELECT id FROM pengguna WHERE role = 'kasir' LIMIT 1")->fetchColumn();

function buat_pesanan_uji(int $mejaId, array $rawItems, string $metode, string $nama = 'Pelanggan Uji', ?string $catatan = null): int
{
    $priced = PemesananModel::price($rawItems);
    if ($priced['error'] !== null) {
        throw new RuntimeException($priced['error']);
    }
    $id = PemesananModel::create([
        'meja_id' => $mejaId,
        'nama'    => $nama,
        'email'   => 'uji@example.com',
        'catatan' => $catatan,
        'metode'  => $metode,
        'total'   => $priced['total'],
        'lines'   => $priced['lines'],
    ]);
    if ($metode === 'qris') {
        $orderId = 'TRX-' . $id . '-' . time();
        PemesananModel::simpanQris($id, $orderId, (string) json_encode([
            'qr_string' => '00020101021226TESTQR' . $id,
            'qr_url'    => 'https://api.sandbox.midtrans.com/v2/qris/tx-' . $id . '/qr-code',
        ]));
    }
    return $id;
}

// =========================================================================
// 1. bayarTunai() Idempotent + Stok Berkurang Sekali + Siklus Transisi Valid/Tidak Valid
// =========================================================================
$stokAwalAmericano = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
$stokAwalCroissant = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$croissantId}")->fetchColumn();

$tunaiId = buat_pesanan_uji($mejaId, [
    ['id' => $americanoId, 'quantity' => 2, 'size' => 'M', 'type' => 'Ice', 'sugar' => 'Less Sugar', 'ice' => 'Normal Ice', 'addons' => ['extra-shot']],
    ['id' => $croissantId, 'quantity' => 1],
], 'tunai', 'Rina Tunai', 'Jangan terlalu manis');

// Transisi tidak valid saat masih menunggu_pembayaran -> siap atau selesai
check(PemesananModel::ubahStatus($tunaiId, 'siap', $kasirId) === 'transisi_tidak_valid', 'Transisi menunggu_pembayaran -> siap ditolak');
check(PemesananModel::ubahStatus($tunaiId, 'selesai', $kasirId) === 'transisi_tidak_valid', 'Transisi menunggu_pembayaran -> selesai ditolak');
check(PemesananModel::ubahStatus(999999, 'siap', $kasirId) === 'tidak_ditemukan', 'ubahStatus untuk ID tidak ada mengembalikan tidak_ditemukan');

// Konfirmasi bayarTunai pertama kali -> lunas, status menjadi diproses, stok berkurang
$resTunai1 = PemesananModel::bayarTunai($tunaiId, $kasirId);
$stokSetelah1 = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
check($resTunai1['ok'] === true && $resTunai1['kode'] === 'lunas', 'bayarTunai() pertama berhasil (kode = lunas)');
check($stokSetelah1 === $stokAwalAmericano - 2, 'Stok Americano berkurang tepat 2 setelah bayarTunai pertama');

// Panggilan kedua bayarTunai (idempotent) -> sudah_lunas, stok TIDAK berkurang lagi
$resTunai2 = PemesananModel::bayarTunai($tunaiId, $kasirId);
$stokSetelah2 = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
check($resTunai2['ok'] === true && $resTunai2['kode'] === 'sudah_lunas', 'bayarTunai() kedua bersifat idempotent (kode = sudah_lunas)');
check($stokSetelah2 === $stokSetelah1, 'Stok menu TIDAK berkurang dua kali pada pemanggilan ulang bayarTunai()');

// Pesanan yang sudah lunas (diproses) TIDAK BISA dibatalkan
check(PemesananModel::ubahStatus($tunaiId, 'batal', $kasirId) === 'transisi_tidak_valid', 'Pesanan yang sudah lunas (diproses) ditolak saat akan dibatalkan');

// Transisi diproses -> selesai (melompati siap) harus ditolak
check(PemesananModel::ubahStatus($tunaiId, 'selesai', $kasirId) === 'transisi_tidak_valid', 'Transisi melompat diproses -> selesai ditolak');

// Transisi valid: diproses -> siap -> selesai
check(PemesananModel::ubahStatus($tunaiId, 'siap', $kasirId) === 'ok', 'Transisi diproses -> siap berhasil (ok)');
check(PemesananModel::ubahStatus($tunaiId, 'batal', $kasirId) === 'transisi_tidak_valid', 'Pesanan berstatus siap tidak bisa dibatalkan');
check(PemesananModel::ubahStatus($tunaiId, 'selesai', $kasirId) === 'ok', 'Transisi siap -> selesai berhasil (ok)');
check(PemesananModel::ubahStatus($tunaiId, 'siap', $kasirId) === 'transisi_tidak_valid', 'Transisi mundur selesai -> siap ditolak');

// Audit log tercatat tanpa membocorkan nama/email pelanggan
$isiLog = (string) @file_get_contents($logFile);
check(
    str_contains($isiLog, "[AUDIT STATUS] pemesanan_id={$tunaiId} status=diproses->siap pengguna_id={$kasirId}")
    && str_contains($isiLog, "[AUDIT STATUS] pemesanan_id={$tunaiId} status=siap->selesai pengguna_id={$kasirId}")
    && !str_contains($isiLog, 'Rina Tunai')
    && !str_contains($isiLog, 'uji@example.com'),
    'Audit log mencatat perubahan status beserta id staf tanpa data pribadi pelanggan'
);

// =========================================================================
// 2. Batal Pesanan Tunai & Batal Pesanan QRIS (PembayaranService::batalkan)
// =========================================================================
$tunaiBatalId = buat_pesanan_uji($mejaId, [['id' => $croissantId, 'quantity' => 1]], 'tunai', 'Batal Tunai');
check(PemesananModel::ubahStatus($tunaiBatalId, 'batal', $kasirId) === 'ok', 'Batal pesanan tunai menunggu_pembayaran berhasil (ok)');
$detailTunaiBatal = PemesananModel::detail($tunaiBatalId);
check(
    $detailTunaiBatal !== null
    && $detailTunaiBatal['status_pemesanan'] === 'batal'
    && $detailTunaiBatal['status_pembayaran'] === 'cancel',
    'Batal pesanan tunai mengubah status_pemesanan=batal dan status_pembayaran=cancel'
);

// Batal pesanan QRIS -> harus memanggil POST /v2/{order_id}/cancel
$qrisBatalId = buat_pesanan_uji($mejaId, [['id' => $croissantId, 'quantity' => 1]], 'qris', 'Batal QRIS');
$qrisBatalRow = PemesananModel::find($qrisBatalId);
$qrisMidtransId = (string) $qrisBatalRow['midtrans_order_id'];

$cancelCalls = [];
$stubCancelService = new PembayaranService(static function (string $method, string $url) use (&$cancelCalls, $qrisMidtransId, $qrisBatalRow): array {
    $cancelCalls[] = ['method' => $method, 'url' => $url];
    return [
        'httpCode' => 200,
        'body'     => [
            'status_code'        => '200',
            'status_message'     => 'Success, transaction is canceled',
            'order_id'           => $qrisMidtransId,
            'transaction_status' => 'cancel',
            'payment_type'       => 'qris',
            'gross_amount'       => (string) $qrisBatalRow['total_harga'],
        ],
    ];
});

$hasilBatalQris = PemesananModel::ubahStatus($qrisBatalId, 'batal', $kasirId, $stubCancelService);
$detailQrisBatal = PemesananModel::detail($qrisBatalId);
check(
    $hasilBatalQris === 'ok'
    && count($cancelCalls) === 1
    && $cancelCalls[0]['method'] === 'POST'
    && str_ends_with($cancelCalls[0]['url'], '/' . rawurlencode($qrisMidtransId) . '/cancel')
    && $detailQrisBatal['status_pemesanan'] === 'batal'
    && $detailQrisBatal['status_pembayaran'] === 'cancel',
    'Batal pesanan QRIS memanggil POST /v2/{order_id}/cancel dan menerapkan status batal/cancel'
);

// =========================================================================
// 3. cekQris(): 404 -> belum_dibayar, pending -> belum_dibayar, settlement -> lunas
// =========================================================================
$qrisCekId = buat_pesanan_uji($mejaId, [['id' => $croissantId, 'quantity' => 1]], 'qris', 'Cek QRIS');
$qrisCekRow = PemesananModel::find($qrisCekId);
$qrisCekMidtransId = (string) $qrisCekRow['midtrans_order_id'];

// Skenario 404 di Midtrans
$svc404 = new PembayaranService(static fn (): array => [
    'httpCode' => 404,
    'body'     => ['status_code' => '404', 'status_message' => 'Transaction doesn\'t exist.'],
]);
$cek1 = PemesananModel::cekQris($qrisCekId, $svc404);
check($cek1['ok'] === true && $cek1['status'] === 'belum_dibayar', 'cekQris() saat Midtrans 404 mengembalikan status = belum_dibayar');

// Skenario settlement di Midtrans
$svcSettle = new PembayaranService(static fn (): array => [
    'httpCode' => 200,
    'body'     => [
        'status_code'        => '200',
        'order_id'           => $qrisCekMidtransId,
        'transaction_status' => 'settlement',
        'fraud_status'       => 'accept',
        'payment_type'       => 'qris',
        'gross_amount'       => (string) $qrisCekRow['total_harga'],
    ],
]);
$cek2 = PemesananModel::cekQris($qrisCekId, $svcSettle);
$afterCek = PemesananModel::detail($qrisCekId);
check(
    $cek2['ok'] === true
    && $cek2['status'] === 'lunas'
    && $afterCek['status_pemesanan'] === 'diproses'
    && $afterCek['status_pembayaran'] === 'paid',
    'cekQris() melunasi pesanan dan mengubah status ke diproses saat Midtrans settlement'
);

// bayarTunai pada pesanan QRIS harus ditolak (bukan_tunai)
$tolakTunaiPadaQris = PemesananModel::bayarTunai($qrisCekId, $kasirId);
check($tolakTunaiPadaQris['ok'] === false && $tolakTunaiPadaQris['kode'] === 'bukan_tunai', 'bayarTunai() menolak pesanan bermetode QRIS');

// =========================================================================
// 4. Query Tanpa N+1: daftarMasuk(), daftarAntrean(), hitungRingkas(), detail()
// =========================================================================
$masukId = buat_pesanan_uji($mejaId, [
    ['id' => $americanoId, 'quantity' => 1, 'size' => 'S', 'type' => 'Hot', 'sugar' => 'Normal Sugar', 'ice' => 'Normal Ice', 'addons' => []],
    ['id' => $croissantId, 'quantity' => 2],
], 'tunai', 'Doni Masuk', 'Minta tisu tambahan');

$daftarMasuk = PemesananModel::daftarMasuk();
$itemMasuk   = array_values(array_filter($daftarMasuk, static fn (array $r): bool => $r['id'] === $masukId));
check(
    count($itemMasuk) === 1
    && $itemMasuk[0]['nomor'] === PemesananModel::displayNumber($masukId)
    && $itemMasuk[0]['ringkasan_item'] === 'Americano + Croissant Butter'
    && $itemMasuk[0]['jumlah_item'] === 3
    && is_int($itemMasuk[0]['menit_sejak_dipesan']),
    'daftarMasuk() mengembalikan pesanan menunggu_pembayaran dengan ringkasan item "A + B"'
);

$antreanDiproses = PemesananModel::daftarAntrean('diproses', 10, 0);
check(
    count($antreanDiproses) === 1 && $antreanDiproses[0]['id'] === $qrisCekId,
    'daftarAntrean(diproses) memfilter status dengan benar'
);

$antreanSemua = PemesananModel::daftarAntrean(null, 50, 0);
check(
    count($antreanSemua) >= 5 && $antreanSemua[0]['id'] === $masukId,
    'daftarAntrean() mengurutkan pesanan terbaru lebih dulu'
);

$ringkas = PemesananModel::hitungRingkas();
check(
    $ringkas['belum_bayar'] === 1
    && $ringkas['lunas_hari_ini'] === 2
    && $ringkas['per_status']['menunggu_pembayaran'] === 1
    && $ringkas['per_status']['diproses'] === 1
    && $ringkas['per_status']['selesai'] === 1
    && $ringkas['per_status']['batal'] === 2,
    'hitungRingkas() menghitung belum_bayar, lunas_hari_ini, dan per_status secara akurat'
);

$detailLengkap = PemesananModel::detail($tunaiId);
check(
    $detailLengkap !== null
    && $detailLengkap['number'] === PemesananModel::displayNumber($tunaiId)
    && $detailLengkap['lunas'] === true
    && $detailLengkap['pengguna_id'] === $kasirId
    && count($detailLengkap['items']) === 2
    && $detailLengkap['items'][0]['options']['size'] === 'M',
    'detail() mengembalikan item, kustomisasi terurai, catatan, kasir, dan informasi pembayaran lengkap'
);

echo "\nRingkasan Paket 3 (Kasir Model Pesanan): {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
