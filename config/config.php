<?php
if (session_status() === PHP_SESSION_NONE) session_start();
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

// 2. Konfigurasi Database PostgreSQL (QuickBrew)
define('DB_DSN',  sprintf('pgsql:host=%s;port=%s;dbname=%s', env('DB_HOST', 'localhost'), env('DB_PORT', '5432'), env('DB_NAME', 'QuickBrew')));
define('DB_USER', env('DB_USER', 'postgres'));
define('DB_PASS', env('DB_PASS', ''));

// 3. Midtrans & App Secret (wajib diisi di config/.env)
define('MIDTRANS_SERVER_KEY', env('MIDTRANS_SERVER_KEY', ''));
define('MIDTRANS_CLIENT_KEY', env('MIDTRANS_CLIENT_KEY', ''));
define('MIDTRANS_PROD',       env('MIDTRANS_PROD', 'false') === 'true'); // true untuk production
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
