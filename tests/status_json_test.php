<?php
/**
 * Pengujian Paket 4: Endpoint Polling Status Pesanan JSON (?page=status-json).
 *   - Hanya menggunakan database `quickbrew_test`.
 *   - Seluruh panggilan Midtrans di-stub.
 */
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());

$logFile = sys_get_temp_dir() . '/quickbrew_status_json_test_' . getmypid() . '.log';
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
require_once APP_ROOT . '/app/Controllers/PelangganController.php';

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

echo "=== Pengujian Paket 4: Endpoint Status Pesanan JSON (DB: {$activeDb}) ===\n\n";

$mejaId      = (int) $pdo->query('SELECT id FROM meja ORDER BY id LIMIT 1')->fetchColumn();
$croissantId = (int) $pdo->query("SELECT id FROM menu WHERE nama_menu = 'Croissant Butter'")->fetchColumn();

$priced = PemesananModel::price([['id' => $croissantId, 'quantity' => 1]]);
$qrisId = PemesananModel::create([
    'meja_id' => $mejaId,
    'nama'    => 'Pelanggan Polling',
    'email'   => 'polling@example.com',
    'catatan' => null,
    'metode'  => 'qris',
    'total'   => $priced['total'],
    'lines'   => $priced['lines'],
]);
$midtransOrderId = 'TRX-' . $qrisId . '-' . time();
PemesananModel::simpanQris($qrisId, $midtransOrderId, (string) json_encode([
    'qr_string' => '00020101021226TESTQR' . $qrisId,
    'qr_url'    => 'https://api.sandbox.midtrans.com/v2/qris/tx-' . $qrisId . '/qr-code',
]));

$tunaiId = PemesananModel::create([
    'meja_id' => $mejaId,
    'nama'    => 'Pelanggan Tunai',
    'email'   => 'tunai@example.com',
    'catatan' => null,
    'metode'  => 'tunai',
    'total'   => $priced['total'],
    'lines'   => $priced['lines'],
]);

// =========================================================================
// 1. Validasi ID & Token HMAC -> 404 JSON bila tidak valid
// =========================================================================
$_SERVER['REMOTE_ADDR'] = '198.51.100.40';
$ctrl = new PelangganController();

$_GET = ['id' => (string) $qrisId, 't' => 'token-palsu'];
ob_start();
$ctrl->statusJson();
$out404 = (string) ob_get_clean();
$json404 = json_decode($out404, true);

check(
    http_response_code() === 404
    && is_array($json404)
    && ($json404['ok'] ?? null) === false,
    'Token HMAC tidak valid mengembalikan HTTP 404 JSON {"ok":false,...}'
);

$_GET = ['id' => '999999', 't' => tok(999999)];
ob_start();
$ctrl->statusJson();
$outMissing = (string) ob_get_clean();
$jsonMissing = json_decode($outMissing, true);

check(
    http_response_code() === 404
    && is_array($jsonMissing)
    && ($jsonMissing['ok'] ?? null) === false,
    'ID pesanan tidak ditemukan mengembalikan HTTP 404 JSON'
);

// =========================================================================
// 2. QRIS menunggu_pembayaran: Throttle 5 Detik, Try/Catch Error, & Settlement
// =========================================================================
$syncCount = 0;
$modeStub  = 'error';

$service = new PembayaranService(static function () use (&$syncCount, &$modeStub, $midtransOrderId, $priced): array {
    $syncCount++;
    if ($modeStub === 'error') {
        return ['httpCode' => 503, 'raw' => 'Service Unavailable', 'errno' => 28, 'error' => 'Timeout'];
    }
    if ($modeStub === 'pending') {
        return [
            'httpCode' => 200,
            'body'     => [
                'status_code'        => '201',
                'order_id'           => $midtransOrderId,
                'transaction_status' => 'pending',
                'payment_type'       => 'qris',
                'gross_amount'       => (string) $priced['total'],
            ],
        ];
    }
    return [
        'httpCode' => 200,
        'body'     => [
            'status_code'        => '200',
            'order_id'           => $midtransOrderId,
            'transaction_status' => 'settlement',
            'fraud_status'       => 'accept',
            'payment_type'       => 'qris',
            'gross_amount'       => (string) $priced['total'],
        ],
    ];
});

$ctrl = new PelangganController($service);
$_SESSION['qris_sync_last_at'] = 0;
$_GET = ['id' => (string) $qrisId, 't' => tok($qrisId)];

// Panggilan 1: sinkron() melempar RuntimeException -> ditangkap try/catch, respons JSON tetap 200 valid!
ob_start();
$ctrl->statusJson();
$outErrSync = (string) ob_get_clean();
$jsonErrSync = json_decode($outErrSync, true);

check(
    http_response_code() === 200
    && $syncCount === 1
    && is_array($jsonErrSync)
    && ($jsonErrSync['ok'] ?? false) === true
    && ($jsonErrSync['data']['id'] ?? 0) === $qrisId
    && ($jsonErrSync['data']['nomor'] ?? '') === PemesananModel::displayNumber($qrisId)
    && ($jsonErrSync['data']['status'] ?? '') === 'menunggu_pembayaran'
    && ($jsonErrSync['data']['label'] ?? '') === 'Menunggu Pembayaran'
    && ($jsonErrSync['data']['lunas'] ?? null) === false
    && ($jsonErrSync['data']['metode'] ?? '') === 'qris'
    && !empty($jsonErrSync['data']['kedaluwarsa'])
    && !empty($jsonErrSync['data']['diperbarui']),
    'Saat sinkron() error, statusJson() menangkap exception dan tetap mengembalikan JSON 200 lengkap'
);

// Panggilan 2 (< 5 detik kemudian): ditahan throttle sesi (syncCount tetap 1)
$modeStub = 'settlement';
ob_start();
$ctrl->statusJson();
$outThrottled = (string) ob_get_clean();
$jsonThrottled = json_decode($outThrottled, true);

check(
    $syncCount === 1 && ($jsonThrottled['data']['lunas'] ?? null) === false,
    'Panggilan statusJson() dalam < 5 detik ditahan oleh throttle sesi'
);

// Panggilan 3 (setelah >= 5 detik): sinkron() terpanggil dan melunasi pesanan
$_SESSION['qris_sync_last_at'] = time() - 6;
ob_start();
$ctrl->statusJson();
$outSettled = (string) ob_get_clean();
$jsonSettled = json_decode($outSettled, true);

check(
    $syncCount === 2
    && ($jsonSettled['ok'] ?? false) === true
    && ($jsonSettled['data']['status'] ?? '') === 'diproses'
    && ($jsonSettled['data']['label'] ?? '') === 'Pesanan Diproses'
    && ($jsonSettled['data']['lunas'] ?? false) === true,
    'Setelah jeda 5 detik, statusJson() menyinkronkan QRIS lunas menjadi status=diproses & lunas=true'
);

// =========================================================================
// 3. Pembatasan Laju: 30 Permintaan / 60 Detik per (IP + ID Pesanan) -> HTTP 429 JSON
// =========================================================================
$pdo->exec('TRUNCATE TABLE pembatasan_laju');
$_SERVER['REMOTE_ADDR'] = '203.0.113.88';
$_GET = ['id' => (string) $tunaiId, 't' => tok($tunaiId)];

for ($i = 1; $i <= 30; $i++) {
    ob_start();
    $ctrl->statusJson();
    ob_end_clean();
}
check(http_response_code() === 200, 'Permintaan ke-30 untuk (IP + ID pesanan) dalam 60 detik masih mengembalikan HTTP 200');

ob_start();
$ctrl->statusJson();
$out429 = (string) ob_get_clean();
$json429 = json_decode($out429, true);

check(
    http_response_code() === 429
    && is_array($json429)
    && ($json429['ok'] ?? null) === false
    && !empty($json429['error']),
    'Permintaan ke-31 untuk (IP + ID pesanan) yang sama mengembalikan HTTP 429 JSON'
);

// Permintaan dari IP yang sama tetapi untuk ID pesanan berbeda ($qrisId) masih diizinkan
$_GET = ['id' => (string) $qrisId, 't' => tok($qrisId)];
ob_start();
$ctrl->statusJson();
$outOtherOrder = (string) ob_get_clean();
$jsonOtherOrder = json_decode($outOtherOrder, true);

check(
    http_response_code() === 200 && ($jsonOtherOrder['ok'] ?? false) === true,
    'Kuota status-json terpisah per (IP + ID pesanan): pesanan lain dari IP yang sama tetap HTTP 200'
);

echo "\nRingkasan Paket 4 (Status Pesanan JSON): {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
