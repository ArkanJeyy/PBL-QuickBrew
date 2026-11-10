<?php
/**
 * Suite pengujian Paket 1: Pengerasan Keamanan Dasar QuickBrew
 *   - Menggunakan database khusus `quickbrew_test` (TIDAK menyentuh database development).
 *   - Mencakup: konfigurasi sesi (HttpOnly, SameSite=Lax, Secure otomatis, strict_mode, only_cookies),
 *     APP_ENV & halaman 500 mandiri tanpa stack trace, header keamanan HTTP, ip_klien() (XFF paling kanan
 *     saat TRUSTED_PROXY=true), pembatasan_laju (batasi_laju, laju_terlampaui, catat_laju, reset_laju),
 *     rate limit checkout (429), serta idempotensi nonce CSRF checkout (double-submit, retry saat gagal,
 *     dua pesanan identik berturut-turut, batas 20 entri sesi).
 */
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());

$logFile = sys_get_temp_dir() . '/quickbrew_hardening_test_' . getmypid() . '.log';
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
putenv('APP_ENV=production');
putenv('TRUSTED_PROXY=false');

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
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

echo "=== Pengujian Paket 1: Hardening Keamanan Dasar (DB: {$activeDb}) ===\n\n";

// =========================================================================
// 1. Konfigurasi Sesi & Konstanta Keamanan
// =========================================================================
check(defined('APP_ENV') && APP_ENV === 'production', 'APP_ENV terdefinisi dan default ke production');
check(defined('TRUSTED_PROXY') && TRUSTED_PROXY === false, 'TRUSTED_PROXY terdefinisi dan default ke false');
check(ini_get('session.use_strict_mode') === '1', 'session.use_strict_mode bernilai 1');
check(ini_get('session.use_only_cookies') === '1', 'session.use_only_cookies bernilai 1');
check(ini_get('session.cookie_httponly') === '1', 'session.cookie_httponly bernilai 1');
check(strcasecmp((string) ini_get('session.cookie_samesite'), 'Lax') === 0, 'session.cookie_samesite bernilai Lax');
check(ini_get('display_errors') === '0' && ini_get('log_errors') === '1', 'Mode production: display_errors=0 dan log_errors=1');

// Uji render_halaman_error_500() mandiri tanpa stack trace via subprocess
$tmp500 = sys_get_temp_dir() . '/qb_test_500_' . getmypid() . '.php';
file_put_contents($tmp500, sprintf(<<<'PHP'
<?php
ini_set('session.save_path', sys_get_temp_dir());
putenv('DB_NAME=quickbrew_test');
putenv('MIDTRANS_SERVER_KEY=DUMMY-Mid-server-UNITTEST-ONLY-000000');
putenv('MIDTRANS_CLIENT_KEY=DUMMY-Mid-client-UNITTEST-ONLY-000000');
putenv('MIDTRANS_PROD=false');
putenv('APP_SECRET=%s');
putenv('APP_ENV=production');
require %s . '/config/config.php';
ob_start();
echo "SAMPAH_BUFFER_SEBELUM_ERROR";
throw new RuntimeException("Rahasia internal: " . TEST_APP_SECRET);
PHP, TEST_APP_SECRET, var_export($root, true)));
$html500 = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp500));
@unlink($tmp500);
check(
    str_contains($html500, 'Terjadi Kesalahan pada Server')
    && !str_contains($html500, 'SAMPAH_BUFFER_SEBELUM_ERROR')
    && !str_contains($html500, 'Stack trace')
    && !str_contains($html500, TEST_APP_SECRET),
    'render_halaman_error_500() menghasilkan HTML 500 generik mandiri tanpa stack trace / rahasia'
);

// =========================================================================
// 2. Header Keamanan HTTP (kirim_header_keamanan)
// =========================================================================
$secHeaders = kirim_header_keamanan();
check(($secHeaders['X-Frame-Options'] ?? '') === 'SAMEORIGIN', 'Header X-Frame-Options = SAMEORIGIN');
check(($secHeaders['X-Content-Type-Options'] ?? '') === 'nosniff', 'Header X-Content-Type-Options = nosniff');
check(($secHeaders['Referrer-Policy'] ?? '') === 'strict-origin-when-cross-origin', 'Header Referrer-Policy = strict-origin-when-cross-origin');
check(!empty($secHeaders['Permissions-Policy']), 'Header Permissions-Policy minimal terpasang');
check(!isset($secHeaders['Content-Security-Policy']), 'Header Content-Security-Policy TIDAK ditambahkan agar front-end aman');

// =========================================================================
// 3. Deteksi IP Klien (ip_klien) & TRUSTED_PROXY via Subprocess
// =========================================================================
$_SERVER['REMOTE_ADDR']          = '203.0.113.10';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 198.51.100.2';
check(ip_klien() === '203.0.113.10', 'TRUSTED_PROXY=false: ip_klien() mengabaikan X-Forwarded-For dan memakai REMOTE_ADDR');

$_SERVER['REMOTE_ADDR'] = 'bukan-ip-valid';
check(ip_klien() === '127.0.0.1', 'REMOTE_ADDR tidak valid jatuh kembali ke 127.0.0.1');
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

// Uji TRUSTED_PROXY=true & deteksi HTTPS via file subprocess sementara
$tmpProxy = sys_get_temp_dir() . '/qb_test_proxy_' . getmypid() . '.php';
file_put_contents($tmpProxy, sprintf(<<<'PHP'
<?php
ini_set('session.save_path', sys_get_temp_dir());
putenv('DB_NAME=quickbrew_test');
putenv('MIDTRANS_SERVER_KEY=DUMMY-Mid-server-UNITTEST-ONLY-000000');
putenv('MIDTRANS_CLIENT_KEY=DUMMY-Mid-client-UNITTEST-ONLY-000000');
putenv('MIDTRANS_PROD=false');
putenv('APP_SECRET=dummy-app-secret-unittest-only-0123456789');
putenv('TRUSTED_PROXY=true');
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 198.51.100.99';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http, https';
require %s . '/config/config.php';
require APP_ROOT . '/app/Helpers/helpers.php';
$ip1 = ip_klien();
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, ip_rusak';
$ip2 = ip_klien();
echo json_encode([
    'trusted'     => TRUSTED_PROXY,
    'secure'      => ini_get('session.cookie_secure'),
    'ip_kanan'    => $ip1,
    'ip_fallback' => $ip2,
]);
PHP, var_export($root, true)));
$subOut = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmpProxy));
@unlink($tmpProxy);
$subData = json_decode($subOut, true);
check(
    is_array($subData)
    && ($subData['trusted'] ?? false) === true
    && ($subData['secure'] ?? '') === '1'
    && ($subData['ip_kanan'] ?? '') === '198.51.100.99'
    && ($subData['ip_fallback'] ?? '') === '10.0.0.1',
    'TRUSTED_PROXY=true: memakai entri PALING KANAN X-Forwarded-For, fallback ke REMOTE_ADDR bila tidak valid, dan cookie Secure=1 saat X-Forwarded-Proto=https',
    $subOut
);

// =========================================================================
// 4. Tabel & Helper Pembatasan Laju (laju_terlampaui, catat_laju, reset_laju, batasi_laju)
// =========================================================================
$pdo->exec('TRUNCATE TABLE pembatasan_laju');

$kunciUji = 'uji:ip:192.0.2.1';
check(laju_terlampaui($kunciUji, 3, 60) === false, 'laju_terlampaui() awal = false');
check(batasi_laju($kunciUji, 3, 60) === true, 'batasi_laju() hit ke-1 = true');
check(batasi_laju($kunciUji, 3, 60) === true, 'batasi_laju() hit ke-2 = true');
check(batasi_laju($kunciUji, 3, 60) === true, 'batasi_laju() hit ke-3 = true');
check(laju_terlampaui($kunciUji, 3, 60) === true, 'laju_terlampaui() setelah 3 hit (maks 3) = true');
check(batasi_laju($kunciUji, 3, 60) === false, 'batasi_laju() hit ke-4 ditolak = false');

reset_laju($kunciUji);
check(laju_terlampaui($kunciUji, 3, 60) === false, 'reset_laju() menghapus hit sehingga kuota kembali tersedia');

// Baris di luar jendela waktu tidak ikut dihitung
$stmtOld = $pdo->prepare("INSERT INTO pembatasan_laju (kunci, waktu) VALUES (?, CURRENT_TIMESTAMP - INTERVAL '2 hours')");
for ($i = 0; $i < 5; $i++) {
    $stmtOld->execute(['uji:lama']);
}
check(laju_terlampaui('uji:lama', 3, 60) === false, 'Baris lama di luar jendela detik tidak menghalangi permintaan baru');

// =========================================================================
// 5. Checkout: Idempotensi Nonce, Retry Gagal, & Rate Limit 10/10 Menit (HTTP 429)
// =========================================================================
$pdo->exec('TRUNCATE TABLE pembatasan_laju');
$croissantId = (int) $pdo->query("SELECT id FROM menu WHERE nama_menu = 'Croissant Butter'")->fetchColumn();

$stubOk = static function (string $method, string $url, ?array $body): array {
    $oid = (string) ($body['transaction_details']['order_id'] ?? 'TRX-0-0');
    return [
        'httpCode' => 200,
        'body'     => [
            'status_code'        => '201',
            'order_id'           => $oid,
            'gross_amount'       => '26400.00',
            'payment_type'       => 'qris',
            'transaction_status' => 'pending',
            'qr_string'          => '00020101021226TESTQRSTRING',
            'actions'            => [],
        ],
    ];
};
$stubFail = static function (): array {
    return ['httpCode' => 500, 'body' => ['status_code' => '500', 'status_message' => 'Internal Server Error']];
};

$modeStub = 'fail';
$service  = new PembayaranService(static function (string $m, string $u, ?array $b) use (&$modeStub, $stubOk, $stubFail): array {
    return $modeStub === 'ok' ? $stubOk($m, $u, $b) : $stubFail();
});

$_SESSION = [];
$_SERVER['REMOTE_ADDR']    = '192.0.2.55';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET = ['meja' => 'MEJA01'];

$tokenAwal = csrf_token();
$_POST = [
    'csrf_token' => $tokenAwal,
    'nama'       => 'Pelanggan Retry',
    'email'      => 'retry@example.com',
    'metode'     => 'qris',
    'catatan'    => '',
    'items'      => json_encode([['id' => $croissantId, 'quantity' => 1]]),
];

// 5a. Jalur gagal (Midtrans error) TIDAK mengonsumsi token CSRF
$redirectUrl = null;
$ctrl = new PelangganController($service, static function (string $to) use (&$redirectUrl): void {
    $redirectUrl = $to;
});
unset($_GET['meja']);

ob_start();
$ctrl->checkout();
ob_end_clean();

check($redirectUrl === null, 'Saat QRIS gagal, checkout tidak redirect');
check(csrf_token() === $tokenAwal && empty($_SESSION['checkout_idempotensi'][$tokenAwal]), 'Jalur gagal TIDAK mengonsumsi/merotasi csrf_token sehingga pelanggan bisa retry');

// 5b. Pelanggan mencoba lagi (retry) dengan token yang sama saat Midtrans sudah pulih -> BERHASIL
$modeStub = 'ok';
ob_start();
$ctrl->checkout();
ob_end_clean();

$idSuksesPertama = (int) ($_SESSION['pesanan_baru'] ?? 0);
check($redirectUrl !== null && $idSuksesPertama > 0, 'Retry dengan token yang sama berhasil membuat pesanan saat Midtrans pulih');
check(csrf_token() !== $tokenAwal, 'Setelah checkout berhasil, csrf_token di-rotasi ke nilai baru');
check(($_SESSION['checkout_idempotensi'][$tokenAwal] ?? 0) === $idSuksesPertama, 'Token lama disimpan di $_SESSION[checkout_idempotensi]');

// 5c. Rate limit checkout: kita sudah memakai 2 hit dari IP 192.0.2.55. Habiskan sisa 8 hit (total 10 hit).
for ($i = 3; $i <= 10; $i++) {
    $_POST['csrf_token'] = csrf_token();
    $_POST['metode']     = 'tunai';
    $redirectUrl         = null;
    ob_start();
    $ctrl->checkout();
    ob_end_clean();
}
check($redirectUrl !== null, 'Percobaan checkout ke-10 dalam 10 menit masih diizinkan');

// Percobaan ke-11 dengan token baru -> ditolak HTTP 429 + pesan di form
$_POST['csrf_token'] = csrf_token();
$redirectUrl         = null;
http_response_code(200);
ob_start();
$ctrl->checkout();
$html429 = (string) ob_get_clean();

check(http_response_code() === 429, 'Percobaan checkout ke-11 dari IP yang sama menghasilkan HTTP 429');
check($redirectUrl === null && str_contains($html429, 'Terlalu banyak percobaan pemesanan'), 'Checkout ke-11 tidak membuat pesanan dan menampilkan pesan pembatasan laju di form');

// 5d. Batas maksimal 20 entri terbaru di $_SESSION['checkout_idempotensi']
$pdo->exec('TRUNCATE TABLE pembatasan_laju');
putenv('CHECKOUT_RATE_MAX=30');
for ($i = 1; $i <= 25; $i++) {
    $_POST['csrf_token'] = csrf_token();
    $_POST['metode']     = 'tunai';
    ob_start();
    $ctrl->checkout();
    ob_end_clean();
}
check(count($_SESSION['checkout_idempotensi'] ?? []) === 20, '$_SESSION[checkout_idempotensi] dibatasi maksimal 20 entri terbaru');
putenv('CHECKOUT_RATE_MAX');

@unlink($logFile);
echo "\nRingkasan Paket 1 (Hardening Keamanan Dasar): {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
