<?php
date_default_timezone_set('Asia/Jakarta');

// 0. Konfigurasi Aplikasi
const APP_NAME = 'QuickBrew';
// Root folder proyek (berisi app/, config/, public/)
define('APP_ROOT', dirname(__DIR__));
// URL folder public/ (lokasi front controller), terdeteksi otomatis.
// Contoh: http://localhost/PBL/PBL-QuickBrew/public -> '/PBL/PBL-QuickBrew/public'
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));

// 1. Loader .env — rahasia disimpan di config/.env (TIDAK di-commit ke Git).
//    Variabel environment dari server (jika ada) diutamakan di atas isi file .env.
function env($key, $default = null) {
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = __DIR__ . '/.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                [$k, $v] = explode('=', $line, 2);
                $v = trim($v);
                if (strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
                $vars[trim($k)] = $v;
            }
        }
    }
    $v = getenv($key);
    return $v !== false ? $v : ($vars[$key] ?? $default);
}

// 1b. Lingkungan Aplikasi & Proxy Tepercaya
define('APP_ENV', (string) env('APP_ENV', 'production'));
define('TRUSTED_PROXY', env('TRUSTED_PROXY', 'false') === 'true');

/** Render halaman HTML 500 generik mandiri (tanpa view & tanpa stack trace) untuk mode production. */
function render_halaman_error_500(): void {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo "<!DOCTYPE html>\n<html lang=\"id\">\n<head>\n<meta charset=\"utf-8\">\n"
       . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
       . "<title>500 Kesalahan Server — QuickBrew</title>\n</head>\n<body>\n"
       . "<h1>Terjadi Kesalahan pada Server</h1>\n"
       . "<p>Maaf, permintaan Anda tidak dapat diproses saat ini. Silakan coba beberapa saat lagi.</p>\n"
       . "</body>\n</html>";
}

error_reporting(E_ALL);
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

set_error_handler( static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    error_log(sprintf('[QuickBrew Error] (%d) %s in %s:%d', $severity, $message, $file, $line));
    if (APP_ENV === 'development') {
        return false;
    }
    return true;
});

set_exception_handler(static function (Throwable $ex): void {
    error_log(sprintf(
        '[QuickBrew Exception] %s: %s in %s:%d',
        get_class($ex),
        $ex->getMessage(),
        $ex->getFile(),
        $ex->getLine()
    ));
    if (APP_ENV === 'development') {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<h1>Unhandled Exception</h1><pre>' . htmlspecialchars((string) $ex, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }
    render_halaman_error_500();
});

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log(sprintf(
            '[QuickBrew Fatal] (%d) %s in %s:%d',
            $err['type'],
            $err['message'],
            $err['file'],
            $err['line']
        ));
        if (APP_ENV !== 'development') {
            render_halaman_error_500();
        }
    }
});

// 1c. Pengerasan Sesi (sebelum session_start)
if (session_status() === PHP_SESSION_NONE) {
    $httpsLangsung = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off' && (string) $_SERVER['HTTPS'] !== '0')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $httpsProxy = false;
    if (TRUSTED_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $protoParts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $httpsProxy = strtolower(trim((string) end($protoParts))) === 'https';
    }
    $cookieSecure = $httpsLangsung || $httpsProxy;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $cookieSecure ? '1' : '0');

    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $cookieSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}

// 2. Konfigurasi Database PostgreSQL (QuickBrew)
define('DB_DSN',  sprintf('pgsql:host=%s;port=%s;dbname=%s', env('DB_HOST', 'localhost'), env('DB_PORT', '5432'), env('DB_NAME', 'QuickBrew')));
define('DB_USER', env('DB_USER', 'postgres'));
define('DB_PASS', env('DB_PASS', ''));

// 3. Midtrans & App Secret (wajib diisi di config/.env)
define('MIDTRANS_SERVER_KEY', env('MIDTRANS_SERVER_KEY', ''));
define('MIDTRANS_CLIENT_KEY', env('MIDTRANS_CLIENT_KEY', ''));
define('MIDTRANS_PROD',       env('MIDTRANS_PROD', 'false') === 'true'); // true untuk production
define('ENABLE_PAYMENT_SIMULATION', env('ENABLE_PAYMENT_SIMULATION', 'false') === 'true'); // simulasi bayar demo; hanya berlaku bila MIDTRANS_PROD false
define('APP_SECRET',          env('APP_SECRET', ''));

foreach (['MIDTRANS_SERVER_KEY', 'MIDTRANS_CLIENT_KEY', 'APP_SECRET'] as $k) {
    if (!constant($k)) {
        http_response_code(500);
        exit("Konfigurasi $k belum diisi. Salin config/.env.example menjadi config/.env lalu isi nilainya.");
    }
}

function db() {
    static $p;
    if (!$p) {
        $p = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        // Kolom TIMESTAMP (tanpa zona) memakai zona sesi -> samakan dengan WIB
        // agar waktu_pemesanan / waktu_pembayaran & view rekap harian akurat.
        $p->exec("SET TIME ZONE 'Asia/Jakarta'");
    }
    return $p;
}

function e($s) { 
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); 
}

function rp($n) { 
    return 'Rp ' . number_format($n, 0, ',', '.'); 
}

function tok($id) { 
    return substr(hash_hmac('sha256', (string)$id, APP_SECRET), 0, 16); 
}

// Logika Midtrans (buat transaksi, cek status, update status pembayaran) ada di app/Services/PembayaranService.php
