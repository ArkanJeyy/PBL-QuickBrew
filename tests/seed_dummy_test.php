<?php
/**
 * Pengujian Paket 4: Skrip CLI bin/seed_dummy.php.
 *   - Hanya menggunakan database `quickbrew_test`.
 *   - Menjalankan fungsi CLI secara in-process untuk menghindari spawning cmd.exe.
 */
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());

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
require_once APP_ROOT . '/app/Models/PemesananModel.php';
require_once $root . '/bin/seed_dummy.php';

$pdo      = db();
$activeDb = (string) $pdo->query('SELECT current_database()')->fetchColumn();
if ($activeDb !== 'quickbrew_test') {
    fwrite(STDERR, "KEAMANAN: Koneksi aktif bukan quickbrew_test ({$activeDb}). Dibatalkan.\n");
    exit(1);
}

$pdo->exec((string) file_get_contents(APP_ROOT . '/SQL/QuickBrew.sql'));
$pdo->exec((string) file_get_contents(APP_ROOT . '/SQL/migrations/001_add_pembayaran_qris_data.sql'));
$pdo->exec((string) file_get_contents(APP_ROOT . '/SQL/migrations/002_pembatasan_laju.sql'));
$migrasi003 = (string) file_get_contents(APP_ROOT . '/SQL/migrations/003_indeks_performa.sql');
$pdo->exec($migrasi003);
$pdo->exec($migrasi003);

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

echo "=== Pengujian Paket 4 (Seeding Dummy CLI): {$activeDb} ===\n\n";

// 1. Guard nama database: hanya akhiran _test atau _demo yang diizinkan
check(
    database_aman_untuk_dummy('quickbrew_test') === true
    && database_aman_untuk_dummy('quickbrew_demo') === true
    && database_aman_untuk_dummy('QuickBrew') === false
    && database_aman_untuk_dummy('quickbrew_prod') === false,
    'Guard database hanya mengizinkan nama database berakhiran _test atau _demo'
);

// 2. Sisipkan 1 pesanan non-dummy untuk memastikan tidak ikut terhapus oleh --bersihkan
$mejaId = (int) $pdo->query('SELECT id FROM meja ORDER BY id LIMIT 1')->fetchColumn();
$menuId = (int) $pdo->query('SELECT id FROM menu ORDER BY id LIMIT 1')->fetchColumn();
$priced = PemesananModel::price([['id' => $menuId, 'quantity' => 1]]);
$nonDummyId = PemesananModel::create([
    'meja_id' => $mejaId,
    'nama'    => 'Pelanggan Asli',
    'email'   => 'asli@example.com',
    'catatan' => null,
    'metode'  => 'tunai',
    'total'   => $priced['total'],
    'lines'   => $priced['lines'],
]);

// 3. Dry-run tidak mengubah apa pun di database
$countBeforeDry = (int) $pdo->query('SELECT COUNT(*) FROM pemesanan')->fetchColumn();
ob_start();
$codeDry = jalankan_cli_seed_dummy(['bin/seed_dummy.php', '--pesanan=25', '--hari=30', '--seed=7']);
$outDry  = (string) ob_get_clean();
$countAfterDry = (int) $pdo->query('SELECT COUNT(*) FROM pemesanan')->fetchColumn();

check(
    $codeDry === 0
    && $countAfterDry === $countBeforeDry
    && str_contains($outDry, 'DRY-RUN SEED'),
    'Mode default dry-run tidak mengubah jumlah baris di database'
);

// 4. Argumen tidak valid ditolak dengan kode 1
$codeBadArg = jalankan_cli_seed_dummy(['bin/seed_dummy.php', '--pesanan=999999']);
check($codeBadArg === 1, 'Batas --pesanan > 200000 ditolak dengan exit code 1');

// 5. Eksekusi --jalankan dengan 40 pesanan, cek konsistensi constraint & stok menu tidak berubah
$stokSebelum = $pdo->query('SELECT id, stok FROM menu ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);

ob_start();
$codeRun = jalankan_cli_seed_dummy(['bin/seed_dummy.php', '--jalankan', '--pesanan=40', '--hari=30', '--seed=7']);
$outRun  = (string) ob_get_clean();

$stokSesudah   = $pdo->query('SELECT id, stok FROM menu ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$countSetelah  = (int) $pdo->query('SELECT COUNT(*) FROM pemesanan')->fetchColumn();
$dummyCount    = (int) $pdo->query("SELECT COUNT(*) FROM pemesanan WHERE nama_pelanggan LIKE 'Dummy %' AND email_pelanggan LIKE '%@dummy.test'")->fetchColumn();
$paidNoTime    = (int) $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status_pembayaran = 'paid' AND waktu_pembayaran IS NULL")->fetchColumn();
$badSubtotal   = (int) $pdo->query("SELECT COUNT(*) FROM detail_pemesanan WHERE subtotal <> harga * jumlah")->fetchColumn();
$badTaxTotal   = (int) $pdo->query("
    SELECT COUNT(*)
      FROM pemesanan p
      JOIN (SELECT pemesanan_id, SUM(subtotal) AS sub FROM detail_pemesanan GROUP BY pemesanan_id) d ON d.pemesanan_id = p.id
     WHERE p.total_harga <> d.sub + ROUND(d.sub * 0.1)::int
")->fetchColumn();

check(
    $codeRun === 0
    && $dummyCount === 40
    && $countSetelah === 41
    && $stokSebelum === $stokSesudah,
    '--jalankan membuat tepat 40 pesanan dummy tanpa mengubah menu.stok'
);
check(
    $paidNoTime === 0 && $badSubtotal === 0 && $badTaxTotal === 0,
    'Seluruh data dummy mematuhi constraint (subtotal = harga * jumlah, pajak 10%, paid punya waktu_pembayaran)'
);

// 6. --bersihkan (dry-run lalu --jalankan) hanya menghapus data dummy dan menyisakan data non-dummy
ob_start();
$codeCleanDry = jalankan_cli_seed_dummy(['bin/seed_dummy.php', '--bersihkan']);
ob_end_clean();
$countAfterCleanDry = (int) $pdo->query('SELECT COUNT(*) FROM pemesanan')->fetchColumn();

ob_start();
$codeCleanRun = jalankan_cli_seed_dummy(['bin/seed_dummy.php', '--bersihkan', '--jalankan']);
ob_end_clean();

$dummyLeft    = (int) $pdo->query("SELECT COUNT(*) FROM pemesanan WHERE nama_pelanggan LIKE 'Dummy %'")->fetchColumn();
$nonDummyLeft = (int) $pdo->query("SELECT COUNT(*) FROM pemesanan WHERE id = {$nonDummyId}")->fetchColumn();

check(
    $codeCleanDry === 0
    && $countAfterCleanDry === 41
    && $codeCleanRun === 0
    && $dummyLeft === 0
    && $nonDummyLeft === 1,
    '--bersihkan menghapus HANYA data dummy dan mempertahankan pesanan asli non-dummy'
);

echo "\nRingkasan Paket 4 (Seed Dummy CLI): {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
