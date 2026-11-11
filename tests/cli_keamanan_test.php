<?php
/**
 * Pengujian Paket 6: Skrip CLI Keamanan Produksi (bin/ganti_password.php & bin/acak_kode_qr.php).
 *   - Hanya menggunakan database `quickbrew_test`.
 */
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());

$logFile = sys_get_temp_dir() . '/quickbrew_cli_keamanan_test_' . getmypid() . '.log';
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
require_once APP_ROOT . '/app/Models/PenggunaModel.php';
require_once $root . '/bin/ganti_password.php';
require_once $root . '/bin/acak_kode_qr.php';

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

/**
 * Buat stream memori berisi baris input untuk simulasi STDIN di pengujian.
 *
 * @return resource
 */
function buat_stream_input(string $isi)
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $isi);
    rewind($stream);
    return $stream;
}

echo "=== Pengujian Paket 6: Skrip CLI Keamanan Produksi (DB: {$activeDb}) ===\n\n";

// =========================================================================
// 1. Pengujian bin/ganti_password.php
// =========================================================================

// 1a. Tanpa argumen -> gagal (exit code 1)
$codeTanpaArg = jalankan_cli_ganti_password(['bin/ganti_password.php']);
check($codeTanpaArg === 1, 'ganti_password.php menolak pemanggilan tanpa argumen <username>');

// 1b. Mengirim password lewat argumen kedua -> ditolak (exit code 1)
$codeArgLebih = jalankan_cli_ganti_password(['bin/ganti_password.php', 'kasir', 'PasswordRahasia123!']);
check($codeArgLebih === 1, 'ganti_password.php menolak jika password diberikan lewat argumen CLI');

// 1c. Username tidak ditemukan -> ditolak (exit code 1)
$sTidakAda = buat_stream_input("PasswordValid123!\nPasswordValid123!\n");
$codeTidakAda = jalankan_cli_ganti_password(['bin/ganti_password.php', 'akun_tidak_ada'], $sTidakAda);
fclose($sTidakAda);
check($codeTidakAda === 1, 'ganti_password.php menolak username yang tidak terdaftar');

// 1d. Password kurang dari 10 karakter -> ditolak (exit code 1)
$sPendek = buat_stream_input("Pendek12!\nPendek12!\n");
ob_start();
$codePendek = jalankan_cli_ganti_password(['bin/ganti_password.php', 'kasir'], $sPendek);
ob_end_clean();
fclose($sPendek);
check($codePendek === 1, 'ganti_password.php menolak password kurang dari 10 karakter');

// 1e. Konfirmasi password tidak cocok -> ditolak (exit code 1)
$sBeda = buat_stream_input("PasswordBaru123!\nPasswordLain999!\n");
ob_start();
$codeBeda = jalankan_cli_ganti_password(['bin/ganti_password.php', 'kasir'], $sBeda);
ob_end_clean();
fclose($sBeda);
check($codeBeda === 1, 'ganti_password.php menolak jika konfirmasi password tidak cocok');

// 1f. Password valid (>= 10 karakter) & konfirmasi cocok -> sukses (exit code 0) dan hash diperbarui
$hashLama = (string) PenggunaModel::cariUsername('kasir')['password'];
$passwordBaruUji = 'KopiMantap2026# Aman';
$sValid = buat_stream_input($passwordBaruUji . "\n" . $passwordBaruUji . "\n");
ob_start();
$codeValid = jalankan_cli_ganti_password(['bin/ganti_password.php', 'kasir'], $sValid);
ob_end_clean();
fclose($sValid);

$kasirSetelah = PenggunaModel::cariUsername('kasir');
$hashBaru     = (string) ($kasirSetelah['password'] ?? '');

check(
    $codeValid === 0
    && $hashBaru !== $hashLama
    && password_verify($passwordBaruUji, $hashBaru)
    && !password_verify('password', $hashBaru),
    'ganti_password.php berhasil memperbarui hash password_hash(..., PASSWORD_DEFAULT) di database'
);

// =========================================================================
// 2. Pengujian bin/acak_kode_qr.php
// =========================================================================

// 2a. Argumen tidak dikenal -> gagal (exit code 1)
$codeQrArgSalah = jalankan_cli_acak_kode_qr(['bin/acak_kode_qr.php', '--salah']);
check($codeQrArgSalah === 1, 'acak_kode_qr.php menolak argumen yang tidak dikenal');

// 2b. Tanpa argumen (mode dry-run) -> menampilkan rencana tanpa mengubah meja.kode_qr di database
$kodeSebelumDry = $pdo->query('SELECT id, kode_qr FROM meja ORDER BY id ASC')->fetchAll(PDO::FETCH_KEY_PAIR);

ob_start();
$codeDryRun = jalankan_cli_acak_kode_qr(['bin/acak_kode_qr.php']);
$outDryRun  = (string) ob_get_clean();

$kodeSesudahDry = $pdo->query('SELECT id, kode_qr FROM meja ORDER BY id ASC')->fetchAll(PDO::FETCH_KEY_PAIR);

check(
    $codeDryRun === 0
    && $kodeSebelumDry === $kodeSesudahDry
    && str_contains($outDryRun, 'MODE DRY-RUN')
    && str_contains($outDryRun, '?meja='),
    'acak_kode_qr.php tanpa argumen berjalan dalam mode dry-run dan tidak mengubah meja.kode_qr di database'
);

// 2c. Dengan --jalankan -> mengganti seluruh meja.kode_qr menjadi 16 karakter hex acak unik + cetak tabel URL & peringatan
ob_start();
$codeJalankan = jalankan_cli_acak_kode_qr(['bin/acak_kode_qr.php', '--jalankan']);
$outJalankan  = (string) ob_get_clean();

$kodeSesudahJalankan = $pdo->query('SELECT id, kode_qr FROM meja ORDER BY id ASC')->fetchAll(PDO::FETCH_KEY_PAIR);

$semuaHex16   = true;
$semuaBerubah = true;
foreach ($kodeSesudahJalankan as $idMeja => $kodeBaru) {
    if (preg_match('/^[0-9a-f]{16}$/', (string) $kodeBaru) !== 1) {
        $semuaHex16 = false;
    }
    if ((string) $kodeBaru === (string) ($kodeSebelumDry[$idMeja] ?? '')) {
        $semuaBerubah = false;
    }
}
$jumlahUnik = count(array_unique(array_values($kodeSesudahJalankan)));

check(
    $codeJalankan === 0
    && count($kodeSesudahJalankan) > 0
    && $semuaHex16
    && $semuaBerubah
    && $jumlahUnik === count($kodeSesudahJalankan),
    'acak_kode_qr.php --jalankan mengganti semua meja.kode_qr menjadi 16 karakter hex acak unik'
);

check(
    str_contains($outJalankan, '?meja=')
    && str_contains($outJalankan, 'PERINGATAN')
    && str_contains($outJalankan, 'tidak berlaku lagi'),
    'acak_kode_qr.php --jalankan mencetak tabel no_meja -> URL baru dan peringatan QR cetak lama tidak berlaku lagi'
);

echo "\nRingkasan Paket 6 (CLI Keamanan Produksi): {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
