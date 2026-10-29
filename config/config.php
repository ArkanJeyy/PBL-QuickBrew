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

function midtrans($method, $path, $body = null, $snap = false) {
    $base = $snap 
        ? (MIDTRANS_PROD ? 'https://app.midtrans.com/snap/v1' : 'https://app.sandbox.midtrans.com/snap/v1')
        : (MIDTRANS_PROD ? 'https://api.midtrans.com/v2'      : 'https://api.sandbox.midtrans.com/v2');
        
    $ch = curl_init($base . $path);
    $o  = [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_USERPWD        => MIDTRANS_SERVER_KEY . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json']
    ];
    
    if ($body) $o[CURLOPT_POSTFIELDS] = json_encode($body);
    
    curl_setopt_array($ch, $o);
    $r = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    
    return $r ?: [];
}

// Cari pemesanan_id dari order_id Midtrans.
// Prioritas: kolom pembayaran.midtrans_order_id (UNIQUE), fallback angka di order_id
// (misal "10" atau "QB-10"), lalu dipastikan ID tersebut benar-benar ada.
function find_pemesanan_id($orderId) {
    $orderId = (string) $orderId;
    if ($orderId === '') return 0;

    $s = db()->prepare("SELECT pemesanan_id FROM pembayaran WHERE midtrans_order_id = ?");
    $s->execute([$orderId]);
    $pid = (int) $s->fetchColumn();
    if ($pid) return $pid;

    if (!preg_match('/(\d+)/', $orderId, $m)) return 0;
    $s = db()->prepare("SELECT id FROM pemesanan WHERE id = ?");
    $s->execute([(int) $m[1]]);
    return (int) $s->fetchColumn();
}

// Dipakai webhook (notify.php) DAN pengecekan status langsung
// Status pembayaran mengikuti CHECK di tabel pembayaran:
//   pending -> paid | deny | cancel | expire | failure
// Status pemesanan: menunggu_pembayaran -> dibayar | batal
function apply_payment($d) {
    $rawOrderId = (string) ($d['order_id'] ?? '');
    $pid        = find_pemesanan_id($rawOrderId);
    if (!$pid) return;

    $ts   = $d['transaction_status'] ?? '';
    $paid = $ts === 'settlement' || ($ts === 'capture' && ($d['fraud_status'] ?? 'accept') === 'accept');
    $fail = in_array($ts, ['deny', 'cancel', 'expire', 'failure'], true);
    if (!$paid && !$fail) return; // 'pending' dsb. -> tidak ada perubahan

    $db = db();
    $db->beginTransaction();
    try {
        // Lock row pemesanan untuk transaksi atomic
        $s = $db->prepare("SELECT status_pemesanan FROM pemesanan WHERE id = ? FOR UPDATE");
        $s->execute([$pid]);
        $statusSaatIni = $s->fetchColumn();

        if ($statusSaatIni === 'menunggu_pembayaran') {
            if ($paid) {
                $db->prepare("UPDATE pemesanan SET status_pemesanan = 'dibayar' WHERE id = ?")
                   ->execute([$pid]);

                // waktu_pembayaran wajib terisi bila status 'paid' (CHECK constraint)
                $db->prepare("UPDATE pembayaran
                                 SET status_pembayaran = 'paid',
                                     metode_pembayaran = ?,
                                     waktu_pembayaran  = CURRENT_TIMESTAMP,
                                     midtrans_order_id = COALESCE(midtrans_order_id, ?)
                               WHERE pemesanan_id = ?")
                   ->execute([$d['payment_type'] ?? null, $rawOrderId, $pid]);

                // Kurangi stok menu (stok memiliki CHECK >= 0)
                $db->prepare("UPDATE menu
                                 SET stok = GREATEST(menu.stok - x.qty, 0)
                                FROM (SELECT menu_id, SUM(jumlah) AS qty
                                        FROM detail_pemesanan
                                       WHERE pemesanan_id = ?
                                       GROUP BY menu_id) x
                               WHERE x.menu_id = menu.id")
                   ->execute([$pid]);
            } else {
                $db->prepare("UPDATE pemesanan SET status_pemesanan = 'batal' WHERE id = ?")
                   ->execute([$pid]);

                $db->prepare("UPDATE pembayaran
                                 SET status_pembayaran = ?,
                                     metode_pembayaran = COALESCE(?, metode_pembayaran),
                                     midtrans_order_id = COALESCE(midtrans_order_id, ?)
                               WHERE pemesanan_id = ?")
                   ->execute([$ts, $d['payment_type'] ?? null, $rawOrderId, $pid]);
            }
        }

        $db->commit();
    } catch (Throwable $ex) {
        $db->rollBack();
        throw $ex;
    }
}