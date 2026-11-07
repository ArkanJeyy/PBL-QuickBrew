<?php
/**
 * Pengujian pembatasan fitur simulasi pembayaran Sandbox QuickBrew.
 *   - Database khusus `quickbrew_test` (TIDAK menyentuh database development).
 *   - Transport Midtrans API & simulator Midtrans di-stub (0 panggilan jaringan).
 *   - Skenario: simulasi nonaktif, flag aktif + Production, flag aktif + Sandbox (jalur simulator),
 *     simulator terjangkau tetapi tidak melunasi (tanpa fallback), simulator tak terjangkau (fallback lokal),
 *     banner MODE DEMO, serta format log [SIMULASI].
 */
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());

$logFile = sys_get_temp_dir() . '/quickbrew_simulasi_test_' . getmypid() . '.log';
@unlink($logFile);
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

const TEST_SERVER_KEY = 'DUMMY-Mid-server-UNITTEST-ONLY-000000';

putenv('DB_NAME=quickbrew_test');
putenv('MIDTRANS_SERVER_KEY=' . TEST_SERVER_KEY);
putenv('MIDTRANS_CLIENT_KEY=DUMMY-Mid-client-UNITTEST-ONLY-000000');
putenv('MIDTRANS_PROD=false');
putenv('APP_SECRET=dummy-app-secret-unittest-only-0123456789');
// Konstanta ENABLE_PAYMENT_SIMULATION = true; variasi skenario lewat env saat membuat PembayaranService baru
putenv('ENABLE_PAYMENT_SIMULATION=true');

require_once dirname(__DIR__) . '/config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Helpers/QrSvg.php';
require_once APP_ROOT . '/app/Models/MenuModel.php';
require_once APP_ROOT . '/app/Models/MejaModel.php';
require_once APP_ROOT . '/app/Models/PemesananModel.php';
require_once APP_ROOT . '/app/Services/PembayaranService.php';
require_once APP_ROOT . '/app/Controllers/PelangganController.php';

try {
    $pdo = db();
} catch (PDOException $e) {
    fwrite(STDERR, 'Gagal membuka koneksi quickbrew_test: ' . $e->getMessage() . "\n");
    exit(1);
}
$activeDb = (string) $pdo->query('SELECT current_database()')->fetchColumn();
if ($activeDb !== 'quickbrew_test') {
    fwrite(STDERR, "FATAL: Pengujian harus berjalan di quickbrew_test, bukan {$activeDb}!\n");
    exit(1);
}
$pdo->exec((string) file_get_contents(APP_ROOT . '/SQL/QuickBrew.sql'));
$pdo->exec((string) file_get_contents(APP_ROOT . '/SQL/migrations/001_add_pembayaran_qris_data.sql'));

$passed = 0;
$failed = 0;
function check(bool $cond, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) { $passed++; echo "[PASS] {$label}\n"; }
    else { $failed++; echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

echo "=== Pengujian Pembatasan Simulasi Pembayaran (DB: {$activeDb}) ===\n\n";

$mejaId      = (int) $pdo->query('SELECT id FROM meja ORDER BY id LIMIT 1')->fetchColumn();
$americanoId = (int) $pdo->query("SELECT id FROM menu WHERE nama_menu = 'Americano'")->fetchColumn();

/** Buat pesanan QRIS menunggu_pembayaran lengkap dengan midtrans_order_id & qris_data. */
function buat_pesanan_qris(int $mejaId, int $menuId): array
{
    $priced = PemesananModel::price([['id' => $menuId, 'quantity' => 1, 'size' => 'S', 'type' => 'Hot', 'sugar' => 'Normal Sugar', 'ice' => 'Normal Ice', 'addons' => []]]);
    if ($priced['error'] !== null) {
        throw new RuntimeException('Gagal menghitung harga uji: ' . $priced['error']);
    }
    $id = PemesananModel::create([
        'meja_id' => $mejaId, 'nama' => 'Uji Simulasi', 'email' => 'uji@example.com', 'catatan' => null,
        'metode'  => 'qris', 'total' => $priced['total'], 'lines' => $priced['lines'],
    ]);
    $orderId = 'TRX-' . $id . '-' . time();
    PemesananModel::simpanQris($id, $orderId, (string) json_encode([
        'qr_string' => '00020101021226TESTQRSTRING' . $id,
        'qr_url'    => 'https://api.sandbox.midtrans.com/v2/qris/tx-sim-' . $id . '/qr-code',
    ], JSON_UNESCAPED_SLASHES));
    return ['id' => $id, 'order_id' => $orderId, 'total' => $priced['total']];
}

function status_db(int $id): array
{
    $s = db()->prepare('SELECT p.status_pemesanan, b.status_pembayaran FROM pemesanan p JOIN pembayaran b ON b.pemesanan_id = p.id WHERE p.id = ?');
    $s->execute([$id]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: [];
}

function baca_log(string $file): string
{
    clearstatcache();
    return is_file($file) ? (string) file_get_contents($file) : '';
}

// --- Stub transport Midtrans API (sinkron -> settlement) ---------------------
$apiCalls = [];
$statusTotals = []; // midtrans_order_id => total
$apiTransport = static function (string $method, string $url, ?array $body) use (&$apiCalls, &$statusTotals): array {
    $apiCalls[] = ['method' => $method, 'url' => $url];
    if ($method === 'GET' && preg_match('#/v2/([^/]+)/status$#', $url, $m)) {
        $orderId = rawurldecode($m[1]);
        return ['httpCode' => 200, 'body' => [
            'status_code' => '200', 'transaction_status' => 'settlement', 'fraud_status' => 'accept',
            'order_id' => $orderId, 'payment_type' => 'qris',
            'gross_amount' => number_format((float) ($statusTotals[$orderId] ?? 0), 2, '.', ''),
        ]];
    }
    return ['httpCode' => 500, 'body' => ['status_message' => 'tidak diharapkan']];
};

// --- Stub simulator Midtrans: mode 'sukses' | 'tidak_lunas' | 'tak_terjangkau' | 'http500' ---
$simCalls = [];
$simMode  = 'sukses';
$simTransport = static function (string $url, array $form) use (&$simCalls, &$simMode): array {
    $simCalls[] = ['url' => $url, 'form' => $form];
    if ($simMode === 'tak_terjangkau') {
        return ['httpCode' => 0, 'raw' => false, 'errno' => 6, 'error' => 'Could not resolve host'];
    }
    if ($simMode === 'http500') {
        return ['httpCode' => 503, 'raw' => 'Service Unavailable'];
    }
    if (str_ends_with($url, '/v2/qris/payment')) {
        return ['httpCode' => 200, 'raw' => '<form action="payment/gopay"><input name="referenceId" type="hidden" value="REF-UJI"><input name="exploreData" type="hidden" value="{&quot;a&quot;:1}"></form>'];
    }
    if ($simMode === 'tidak_lunas') {
        return ['httpCode' => 200, 'raw' => '<p>Transaction is unsuccessful.</p>'];
    }
    return ['httpCode' => 200, 'raw' => '<input name="orderId" type="hidden" value="REF-UJI"><input name="status" type="hidden" value="PAID">'];
};

/** Jalankan PelangganController::pembayaran() untuk pesanan $id dengan env tertentu. */
function jalankan_halaman(int $id, string $method, PembayaranService $service, ?string &$redirect = null): array
{
    $_SESSION['qris_sync_last_at'] = time(); // matikan sinkron cadangan agar tidak mengaburkan hasil
    $_GET    = ['id' => (string) $id, 't' => tok($id)];
    $_POST   = $method === 'POST' ? ['simulasi_bayar' => '1'] : [];
    $_SERVER['REQUEST_METHOD'] = $method;
    $redirect = null;
    http_response_code(200);
    $ctrl = new PelangganController($service, static function (string $to) use (&$redirect): void { $redirect = $to; });
    ob_start();
    $ctrl->pembayaran();
    $html = (string) ob_get_clean();
    return ['html' => $html, 'code' => http_response_code(), 'redirect' => $redirect];
}

check(defined('ENABLE_PAYMENT_SIMULATION'), 'Konstanta ENABLE_PAYMENT_SIMULATION terdefinisi di config.php');

// =========================================================================
// 1. Simulasi NONAKTIF (ENABLE_PAYMENT_SIMULATION=false) -> POST ditolak, status tetap
// =========================================================================
putenv('ENABLE_PAYMENT_SIMULATION=false');
putenv('MIDTRANS_PROD=false');
$svcOff = new PembayaranService($apiTransport, $simTransport);
$o1 = buat_pesanan_qris($mejaId, $americanoId);
$simCalls = []; $apiCalls = [];
$r = jalankan_halaman($o1['id'], 'POST', $svcOff);
check($svcOff->simulasiAktif() === false, 'Nonaktif: PembayaranService::simulasiAktif() = false');
check($r['code'] === 403, 'Nonaktif: POST simulasi ditolak dengan HTTP 403', 'kode=' . $r['code']);
check($r['redirect'] === null, 'Nonaktif: tidak ada redirect setelah POST ditolak');
check(status_db($o1['id']) === ['status_pemesanan' => 'menunggu_pembayaran', 'status_pembayaran' => 'pending'], 'Nonaktif: status pesanan & pembayaran tidak berubah');
check($simCalls === [] && $apiCalls === [], 'Nonaktif: simulator & API Midtrans tidak dipanggil');
check(!str_contains($r['html'], 'simulasi_bayar') && !str_contains($r['html'], 'MODE DEMO'), 'Nonaktif: tombol simulasi & banner MODE DEMO tidak dirender');
check($svcOff->simulasiQrisSandbox($o1['id']) === false && status_db($o1['id'])['status_pemesanan'] === 'menunggu_pembayaran', 'Nonaktif: simulasiQrisSandbox() langsung menolak tanpa melunasi');

// =========================================================================
// 2. Flag TRUE tetapi MIDTRANS_PROD=true -> ditolak
// =========================================================================
putenv('ENABLE_PAYMENT_SIMULATION=true');
putenv('MIDTRANS_PROD=true');
$svcProd = new PembayaranService($apiTransport, $simTransport);
$o2 = buat_pesanan_qris($mejaId, $americanoId);
$simCalls = []; $apiCalls = [];
$r = jalankan_halaman($o2['id'], 'POST', $svcProd);
check($svcProd->simulasiAktif() === false, 'Production: simulasiAktif() = false walau flag true');
check($r['code'] === 403, 'Production: POST simulasi ditolak dengan HTTP 403', 'kode=' . $r['code']);
check(status_db($o2['id'])['status_pemesanan'] === 'menunggu_pembayaran', 'Production: status pesanan tidak berubah');
check($svcProd->simulasiQrisSandbox($o2['id']) === false && $simCalls === [], 'Production: simulasiQrisSandbox() menolak & simulator tidak dipanggil');
check(!str_contains($r['html'], 'MODE DEMO'), 'Production: banner MODE DEMO tidak dirender');

// =========================================================================
// 3. Flag TRUE + Sandbox -> jalur simulator Midtrans, lalu sinkron() melunasi
// =========================================================================
putenv('MIDTRANS_PROD=false');
$svcOn = new PembayaranService($apiTransport, $simTransport);
check($svcOn->simulasiAktif() === true, 'Sandbox + flag: simulasiAktif() = true');

$o3 = buat_pesanan_qris($mejaId, $americanoId);
$statusTotals[$o3['order_id']] = $o3['total'];
$r = jalankan_halaman($o3['id'], 'GET', $svcOn);
check(str_contains($r['html'], 'MODE DEMO: simulasi pembayaran aktif'), 'Sandbox + flag: banner "MODE DEMO: simulasi pembayaran aktif" dirender');
check(str_contains($r['html'], 'name="simulasi_bayar"'), 'Sandbox + flag: tombol simulasi dirender');

$simMode = 'sukses'; $simCalls = []; $apiCalls = [];
$logSebelum = strlen(baca_log($logFile));
$r = jalankan_halaman($o3['id'], 'POST', $svcOn);
$logBaru = substr(baca_log($logFile), $logSebelum);
check($r['redirect'] !== null && str_contains((string) $r['redirect'], 'page=pembayaran'), 'Sandbox + flag: POST simulasi berjalan lalu redirect ke halaman pembayaran');
check(count($simCalls) === 2 && str_ends_with($simCalls[1]['url'], '/v2/qris/payment/gopay'), 'Sandbox + flag: simulator Midtrans dipanggil (scan QR + bayar GoPay)');
check(($simCalls[1]['form']['referenceId'] ?? '') === 'REF-UJI' && ($simCalls[1]['form']['exploreData'] ?? '') === '{"a":1}', 'Sandbox + flag: referenceId & exploreData diteruskan (HTML entity didekode)');
check(count(array_filter($apiCalls, static fn ($c) => str_ends_with($c['url'], '/status'))) === 1, 'Sandbox + flag: status diterapkan via sinkron() (GET /v2/{id}/status)');
check(status_db($o3['id']) === ['status_pemesanan' => 'diproses', 'status_pembayaran' => 'paid'], 'Sandbox + flag: pesanan diproses & pembayaran paid');
check(str_contains($logBaru, "[SIMULASI] pemesanan_id={$o3['id']} jalur=simulator"), 'Log: "[SIMULASI] pemesanan_id=.. jalur=simulator" tercatat');
check(!str_contains($logBaru, '[SIMULASI LOKAL]'), 'Jalur simulator: TIDAK memakai fallback lokal');

// =========================================================================
// 4. Simulator terjangkau tetapi TIDAK melunasi -> tanpa fallback, false
// =========================================================================
$o4 = buat_pesanan_qris($mejaId, $americanoId);
$statusTotals[$o4['order_id']] = $o4['total'];
$simMode = 'tidak_lunas'; $simCalls = []; $apiCalls = [];
$logSebelum = strlen(baca_log($logFile));
$hasil = $svcOn->simulasiQrisSandbox($o4['id']);
$logBaru = substr(baca_log($logFile), $logSebelum);
check($hasil === false, 'Simulator terjangkau tapi tidak lunas: simulasiQrisSandbox() = false');
check(status_db($o4['id'])['status_pemesanan'] === 'menunggu_pembayaran', 'Simulator terjangkau tapi tidak lunas: status tetap menunggu_pembayaran');
check(!str_contains($logBaru, '[SIMULASI LOKAL]') && $apiCalls === [], 'Simulator terjangkau tapi tidak lunas: TIDAK fallback lokal & tidak sinkron');

// =========================================================================
// 5. Simulator TIDAK terjangkau -> fallback lokal tandaiLunas() dengan log jelas
// =========================================================================
foreach (['tak_terjangkau' => 'error koneksi cURL', 'http500' => 'HTTP 503'] as $mode => $label) {
    $o5 = buat_pesanan_qris($mejaId, $americanoId);
    $simMode = $mode; $simCalls = []; $apiCalls = [];
    $logSebelum = strlen(baca_log($logFile));
    $hasil = $svcOn->simulasiQrisSandbox($o5['id']);
    $logBaru = substr(baca_log($logFile), $logSebelum);
    check($hasil === true && status_db($o5['id']) === ['status_pemesanan' => 'diproses', 'status_pembayaran' => 'paid'], "Simulator tak terjangkau ({$label}): fallback lokal melunasi pesanan");
    check(str_contains($logBaru, "[SIMULASI LOKAL] pemesanan_id={$o5['id']}") && str_contains($logBaru, "[SIMULASI] pemesanan_id={$o5['id']} jalur=lokal"), "Simulator tak terjangkau ({$label}): log [SIMULASI LOKAL] & jalur=lokal tercatat");
}

// =========================================================================
// 6. Log tidak membocorkan Server Key
// =========================================================================
check(!str_contains(baca_log($logFile), TEST_SERVER_KEY), 'error_log tidak memuat Server Key');

echo "\nRingkasan Simulasi Pembayaran: {$passed} lulus, {$failed} gagal.\n";
@unlink($logFile);
exit($failed === 0 ? 0 : 1);

