<?php
/**
 * Runner CLI untuk menjalankan seluruh suite pengujian `tests/*_test.php`
 * sebagai proses PHP terpisah dan terisolasi.
 *
 * Keamanan:
 *   - Menolak berjalan apabila DB_NAME (environment/konfigurasi) atau database
 *     aktif pada koneksi PDO bukan `quickbrew_test`.
 *   - Setiap berkas tes dijalankan di subproses terpisah via proc_open().
 *
 * Penggunaan:
 *   DB_NAME=quickbrew_test php tests/jalankan_semua.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$root = dirname(__DIR__);

/**
 * Ambil nilai konfigurasi dari environment OS, lalu fallback ke config/.env jika ada.
 */
function baca_konfig_runner(string $root, string $kunci, string $default = ''): string
{
    $dariEnv = getenv($kunci);
    if ($dariEnv !== false) {
        return (string) $dariEnv;
    }

    static $envFileVars = null;
    if ($envFileVars === null) {
        $envFileVars = [];
        $envPath     = $root . '/config/.env';
        if (is_file($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $v = trim($v);
                if (strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                    $v = substr($v, 1, -1);
                }
                $envFileVars[trim($k)] = $v;
            }
        }
    }

    return $envFileVars[$kunci] ?? $default;
}

// 1. Pemeriksaan keamanan wajib: DB_NAME harus tepat `quickbrew_test`
$targetDb = baca_konfig_runner($root, 'DB_NAME', 'QuickBrew');
if ($targetDb !== 'quickbrew_test') {
    fwrite(
        STDERR,
        sprintf(
            "KEAMANAN: tests/jalankan_semua.php ditolak karena DB_NAME adalah '%s', wajib 'quickbrew_test'.\n"
            . "Jalankan dengan: DB_NAME=quickbrew_test php tests/jalankan_semua.php\n",
            $targetDb
        )
    );
    exit(1);
}

$dbHost = baca_konfig_runner($root, 'DB_HOST', 'localhost');
$dbPort = baca_konfig_runner($root, 'DB_PORT', '5432');
$dbUser = baca_konfig_runner($root, 'DB_USER', 'postgres');
$dbPass = baca_konfig_runner($root, 'DB_PASS', '');

try {
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbHost, $dbPort, $targetDb),
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $activeDb = (string) $pdo->query('SELECT current_database()')->fetchColumn();
} catch (Throwable $e) {
    fwrite(STDERR, "GALAT: Gagal terhubung ke database pengujian 'quickbrew_test': " . $e->getMessage() . "\n");
    exit(1);
}

if ($activeDb !== 'quickbrew_test') {
    fwrite(STDERR, "KEAMANAN: Koneksi aktif bukan 'quickbrew_test' (terdeteksi: '{$activeDb}'). Dibatalkan.\n");
    exit(1);
}

// 2. Kumpulkan seluruh file *_test.php secara alfabetis
$berkasTes = glob(__DIR__ . '/*_test.php') ?: [];
sort($berkasTes, SORT_STRING);

if ($berkasTes === []) {
    fwrite(STDERR, "Tidak ditemukan berkas tests/*_test.php.\n");
    exit(1);
}

echo sprintf(
    "=== QuickBrew Test Runner (DB: %s | PHP: %s | Total berkas: %d) ===\n\n",
    $activeDb,
    PHP_VERSION,
    count($berkasTes)
);

$jumlahLulus = 0;
$jumlahGagal = 0;
$hasilPerFile = [];
$waktuMulaiTotal = microtime(true);

foreach ($berkasTes as $path) {
    $namaFile = basename($path);
    $mulai    = microtime(true);

    $spec = [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proses = proc_open([PHP_BINARY, $path], $spec, $pipes, $root);
    if (!is_resource($proses)) {
        $jumlahGagal++;
        $hasilPerFile[] = [
            'file'      => $namaFile,
            'ok'        => false,
            'exit_code' => 1,
            'durasi'    => 0.0,
            'ringkasan' => 'Gagal memulai proses PHP',
        ];
        echo "[GAGAL] {$namaFile} — gagal memulai subproses\n";
        continue;
    }

    $stdout   = (string) stream_get_contents($pipes[1]);
    $stderr   = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($proses);
    $durasi   = microtime(true) - $mulai;
    $ok       = ($exitCode === 0);

    $barisOutput = array_values(array_filter(
        preg_split('/\r\n|\r|\n/', trim($stdout)) ?: [],
        static fn (string $b): bool => trim($b) !== ''
    ));
    $barisTerakhir = $barisOutput !== [] ? (string) end($barisOutput) : '(tanpa keluaran)';

    if ($ok) {
        $jumlahLulus++;
        echo sprintf("[LULUS] %-34s (%.2fd) | %s\n", $namaFile, $durasi, $barisTerakhir);
    } else {
        $jumlahGagal++;
        echo sprintf("[GAGAL] %-34s (%.2fd) | exit=%d | %s\n", $namaFile, $durasi, $exitCode, $barisTerakhir);
        echo "--- Keluaran {$namaFile} ---\n";
        if (trim($stdout) !== '') {
            echo rtrim($stdout) . "\n";
        }
        if (trim($stderr) !== '') {
            echo "[STDERR]\n" . rtrim($stderr) . "\n";
        }
        echo "--- Akhir keluaran {$namaFile} ---\n\n";
    }

    $hasilPerFile[] = [
        'file'      => $namaFile,
        'ok'        => $ok,
        'exit_code' => $exitCode,
        'durasi'    => $durasi,
        'ringkasan' => $barisTerakhir,
    ];
}

$durasiTotal = microtime(true) - $waktuMulaiTotal;

echo "\n=== Ringkasan Keseluruhan ===\n";
echo sprintf(
    "Total berkas: %d | Lulus: %d | Gagal: %d | Durasi: %.2f detik\n",
    count($hasilPerFile),
    $jumlahLulus,
    $jumlahGagal,
    $durasiTotal
);

exit($jumlahGagal === 0 ? 0 : 1);
