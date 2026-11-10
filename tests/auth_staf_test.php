<?php
/**
 * Pengujian Paket 2: Autentikasi Staf, Guard wajib_staf(), & Throttling Login.
 *   - Hanya menggunakan database `quickbrew_test`.
 */
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());

$logFile = sys_get_temp_dir() . '/quickbrew_auth_test_' . getmypid() . '.log';
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

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Models/PenggunaModel.php';
require_once APP_ROOT . '/app/Controllers/AuthController.php';

$dbHost = (string) env('DB_HOST', 'localhost');
$dbPort = (string) env('DB_PORT', '5432');
$dbUser = (string) env('DB_USER', 'postgres');
$dbPass = (string) env('DB_PASS', '');

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

echo "=== Pengujian Paket 2: Autentikasi Staf (DB: {$activeDb}) ===\n\n";

// =========================================================================
// 1. PenggunaModel & Render Form Login (GET)
// =========================================================================
$kasir = PenggunaModel::cariUsername('kasir');
$admin = PenggunaModel::cariUsername('admin');
check($kasir !== null && $kasir['role'] === 'kasir', 'PenggunaModel::cariUsername(kasir) menemukan akun kasir');
check($admin !== null && $admin['role'] === 'admin', 'PenggunaModel::cariUsername(admin) menemukan akun admin');
check(PenggunaModel::cariUsername('tidak_ada') === null, 'PenggunaModel::cariUsername() mengembalikan null untuk user tidak dikenal');

$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR']    = '198.51.100.25';
$_GET = ['redirect' => '/PBL/PBL-QuickBrew/public/?page=kasir'];

$redirectTo = null;
$authCtrl = new AuthController(static function (string $to) use (&$redirectTo): void {
    $redirectTo = $to;
});

ob_start();
$authCtrl->login();
$loginHtml = (string) ob_get_clean();

check(
    str_contains($loginHtml, 'name="csrf_token"')
    && str_contains($loginHtml, 'name="username"')
    && str_contains($loginHtml, 'name="password"')
    && str_contains($loginHtml, 'name="redirect"'),
    'GET login merender form placeholder ber-CSRF dan menyimpan path redirect internal'
);

// =========================================================================
// 2. Blokir Password Default "password" di APP_ENV=production vs development
// =========================================================================
putenv('APP_ENV=production');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => csrf_token(),
    'username'   => 'kasir',
    'password'   => 'password',
];

$redirectTo = null;
ob_start();
$authCtrl->login();
$prodDefaultHtml = (string) ob_get_clean();

check(
    http_response_code() === 403
    && $redirectTo === null
    && empty($_SESSION['staf'])
    && str_contains($prodDefaultHtml, 'Password default belum diganti. Jalankan bin/ganti_password.php'),
    'APP_ENV=production menolak login dengan password literal "password"'
);

// Di APP_ENV=development (untuk demo Sandbox), password default tidak diblokir
putenv('APP_ENV=development');
$_POST = [
    'csrf_token' => csrf_token(),
    'username'   => 'kasir',
    'password'   => 'password',
    'redirect'   => '/PBL/PBL-QuickBrew/public/?page=menu',
];
$redirectTo = null;
ob_start();
$authCtrl->login();
ob_end_clean();

check(
    $redirectTo === '/PBL/PBL-QuickBrew/public/?page=menu'
    && ($_SESSION['staf']['username'] ?? $_SESSION['staf']['role'] ?? '') === 'kasir',
    'APP_ENV=development mengizinkan login demo dan mengarahkan ke path internal'
);

// Kembalikan ke production & ganti password kasir + admin ke password kuat
putenv('APP_ENV=production');
$strongPassKasir = 'KasirKuat#2026!';
$strongPassAdmin = 'AdminKuat#2026!';
PenggunaModel::perbaruiPassword((int) $kasir['id'], password_hash($strongPassKasir, PASSWORD_DEFAULT));
PenggunaModel::perbaruiPassword((int) $admin['id'], password_hash($strongPassAdmin, PASSWORD_DEFAULT));

// =========================================================================
// 3. Anti-Enumerasi, Password Salah, Rehash Otomatis, & Login Sukses
// =========================================================================
$_SESSION = [];
$pdo->exec('TRUNCATE TABLE pembatasan_laju');

// Username tidak dikenal -> pesan generik + catat hit tanpa menyimpan username mentah
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => csrf_token(),
    'username'   => 'UserRahasiaTidakAda',
    'password'   => 'SalahPassword123',
];
ob_start();
$authCtrl->login();
$errUnknownHtml = (string) ob_get_clean();

$_POST = [
    'csrf_token' => csrf_token(),
    'username'   => 'kasir',
    'password'   => 'SalahPassword123',
];
ob_start();
$authCtrl->login();
$errWrongPassHtml = (string) ob_get_clean();

check(
    str_contains($errUnknownHtml, 'Username atau password salah.')
    && str_contains($errWrongPassHtml, 'Username atau password salah.'),
    'Pesan error username tidak dikenal identik dengan password salah (anti-enumerasi)'
);

$rawKeyCount = (int) $pdo->query("SELECT COUNT(*) FROM pembatasan_laju WHERE kunci ILIKE '%UserRahasia%' OR kunci ILIKE '%kasir%'")->fetchColumn();
$maxKeyLen   = (int) $pdo->query("SELECT COALESCE(MAX(LENGTH(kunci)), 0) FROM pembatasan_laju")->fetchColumn();
check($rawKeyCount === 0 && $maxKeyLen > 0 && $maxKeyLen <= 120, 'Kunci pembatasan_laju memakai SHA-256 (<=120 char) dan tidak menyimpan username mentah');

// Uji password_needs_rehash (simpan hash cost=4 untuk kasir lalu login)
$lowCostHash = password_hash($strongPassKasir, PASSWORD_BCRYPT, ['cost' => 4]);
PenggunaModel::perbaruiPassword((int) $kasir['id'], $lowCostHash);

$_POST = [
    'csrf_token' => csrf_token(),
    'username'   => 'kasir',
    'password'   => $strongPassKasir,
    'redirect'   => 'https://evil.example/steal', // URL eksternal harus ditolak!
];
$redirectTo = null;
ob_start();
$authCtrl->login();
ob_end_clean();

$kasirAfter = PenggunaModel::cariUsername('kasir');
check(
    $kasirAfter !== null && $kasirAfter['password'] !== $lowCostHash && !password_needs_rehash($kasirAfter['password'], PASSWORD_DEFAULT),
    'password_needs_rehash memperbarui hash password secara otomatis saat login'
);
check(
    $redirectTo === url('home') && !str_contains((string) $redirectTo, 'evil.example'),
    'Login menolak URL redirect eksternal dan mengarahkan ke beranda internal'
);
check(
    isset($_SESSION['staf'])
    && $_SESSION['staf']['id'] === (int) $kasir['id']
    && $_SESSION['staf']['nama'] === $kasir['nama']
    && $_SESSION['staf']['role'] === 'kasir',
    '$_SESSION[staf] berisi [id, nama, role] setelah login sukses'
);

// =========================================================================
// 4. Guard wajib_staf() & Validasi Tujuan Balik
// =========================================================================
check(tujuan_balik_aman('/PBL/PBL-QuickBrew/public/?page=menu') === '/PBL/PBL-QuickBrew/public/?page=menu', 'tujuan_balik_aman menerima path internal');
check(tujuan_balik_aman('//evil.example/x') === null, 'tujuan_balik_aman menolak protocol-relative URL //evil.example');
check(tujuan_balik_aman('https://evil.example/x') === null, 'tujuan_balik_aman menolak URL absolut https://');
check(tujuan_balik_aman('/valid\\..\\evil') === null, 'tujuan_balik_aman menolak backslash');
check(tujuan_balik_aman("javascript:alert(1)") === null, 'tujuan_balik_aman menolak skema javascript:');

// Saat kasir login:
$stafKasir = wajib_staf(['kasir', 'admin'], static function (): void {});
check(($stafKasir['role'] ?? '') === 'kasir', 'wajib_staf([kasir, admin]) mengizinkan kasir');

ob_start();
$ditolakAdmin = wajib_staf(['admin'], static function (): void {});
$html403 = (string) ob_get_clean();
check($ditolakAdmin === [] && http_response_code() === 403 && str_contains($html403, '403'), 'wajib_staf([admin]) menolak kasir dengan HTTP 403');

// Admin boleh semua yang boleh kasir
$_SESSION['staf'] = ['id' => (int) $admin['id'], 'nama' => $admin['nama'], 'role' => 'admin'];
$stafAdminDiKasir = wajib_staf(['kasir'], static function (): void {});
check(($stafAdminDiKasir['role'] ?? '') === 'admin', 'wajib_staf([kasir]) tetap mengizinkan admin');

// Belum login -> redirect ke login
$_SESSION = [];
$_SERVER['REQUEST_URI'] = '/PBL/PBL-QuickBrew/public/?page=kasir';
$guardRedirect = null;
wajib_staf(['kasir', 'admin'], static function (string $to) use (&$guardRedirect): void {
    $guardRedirect = $to;
});
check($guardRedirect !== null && str_contains($guardRedirect, 'page=login') && str_contains($guardRedirect, 'redirect='), 'wajib_staf() saat belum login mengarahkan ke halaman login dengan parameter redirect internal');

// =========================================================================
// 5. Logout POST + CSRF
// =========================================================================
$_SESSION['staf'] = ['id' => (int) $kasir['id'], 'nama' => $kasir['nama'], 'role' => 'kasir'];
$tokenLogout = csrf_token();

// GET logout harus ditolak (405)
$_SERVER['REQUEST_METHOD'] = 'GET';
$authCtrl->logout();
check(http_response_code() === 405 && !empty($_SESSION['staf']), 'Logout via GET ditolak (HTTP 405) dan sesi tetap ada');

// POST logout tanpa CSRF valid harus ditolak (403)
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['csrf_token' => 'token-palsu'];
$authCtrl->logout();
check(http_response_code() === 403 && !empty($_SESSION['staf']), 'Logout dengan CSRF tidak valid ditolak (HTTP 403)');

// POST logout valid -> sesi bersih & redirect ke login
$_POST = ['csrf_token' => $tokenLogout];
$redirectTo = null;
$authCtrl->logout();
check($redirectTo === url('login') && empty($_SESSION['staf']), 'Logout POST ber-CSRF menghancurkan sesi staf dan redirect ke login');

// =========================================================================
// 6. Throttle Login: 5/15m per (username+IP) & 20/15m per IP
// =========================================================================
$pdo->exec('TRUNCATE TABLE pembatasan_laju');
$_SESSION = [];
$_SERVER['REMOTE_ADDR']    = '203.0.113.77';
$_SERVER['REQUEST_METHOD'] = 'POST';

// 5 percobaan gagal untuk username 'kasir' (dengan variasi huruf besar/spasi untuk menguji normalisasi)
for ($i = 1; $i <= 5; $i++) {
    $_POST = [
        'csrf_token' => csrf_token(),
        'username'   => ($i % 2 === 0) ? '  KASIR ' : 'kasir',
        'password'   => 'SalahTerus' . $i,
    ];
    ob_start();
    $authCtrl->login();
    ob_end_clean();
}
check(http_response_code() === 401, 'Percobaan gagal ke-5 masih mengembalikan 401');

// Percobaan ke-6: meskipun password BENAR, harus langsung ditolak 429 karena kuota dicek SEBELUM verifikasi
$redirectTo = null;
$_POST = [
    'csrf_token' => csrf_token(),
    'username'   => 'kasir',
    'password'   => $strongPassKasir,
];
ob_start();
$authCtrl->login();
$throttleUserHtml = (string) ob_get_clean();

check(
    http_response_code() === 429
    && $redirectTo === null
    && empty($_SESSION['staf'])
    && str_contains($throttleUserHtml, 'Terlalu banyak percobaan login'),
    'Percobaan ke-6 untuk (username + IP) yang sama ditolak HTTP 429 sebelum verifikasi password'
);

// Namun akun 'admin' dari IP yang sama masih bisa mencoba (karena kuota per-IP baru 5/20)
$_POST = [
    'csrf_token' => csrf_token(),
    'username'   => 'admin',
    'password'   => $strongPassAdmin,
];
$redirectTo = null;
ob_start();
$authCtrl->login();
ob_end_clean();
check($redirectTo !== null && ($_SESSION['staf']['role'] ?? '') === 'admin', 'Login admin dari IP yang sama masih diizinkan selama kuota per-IP (20) belum habis');

// Pastikan login sukses TIDAK mereset kuota per-IP:
// Saat ini IP 203.0.113.77 sudah punya 5 hit gagal. Tambah 15 hit gagal lagi dengan username berbeda-beda -> total 20 hit per IP.
$_SESSION = [];
for ($i = 1; $i <= 15; $i++) {
    $_POST = [
        'csrf_token' => csrf_token(),
        'username'   => 'user_acak_' . $i,
        'password'   => 'SalahPassword',
    ];
    ob_start();
    $authCtrl->login();
    ob_end_clean();
}

// Percobaan ke-21 dari IP yang sama (meskipun username baru & password admin benar) harus ditolak HTTP 429!
$redirectTo = null;
$_POST = [
    'csrf_token' => csrf_token(),
    'username'   => 'admin',
    'password'   => $strongPassAdmin,
];
ob_start();
$authCtrl->login();
$throttleIpHtml = (string) ob_get_clean();

check(
    http_response_code() === 429
    && $redirectTo === null
    && empty($_SESSION['staf'])
    && str_contains($throttleIpHtml, 'Terlalu banyak percobaan login'),
    'Setelah 20 gagal dari 1 IP (kuota per-IP tidak direset saat login sukses), percobaan ke-21 ditolak HTTP 429'
);

echo "\nRingkasan Paket 2 (Auth Staf): {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
