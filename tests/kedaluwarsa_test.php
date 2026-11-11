<?php
/**
 * Pengujian Paket 5: Pembersih Pesanan Menggantung CLI (KedaluwarsaService & bin/kedaluwarsa.php).
 *   - Hanya menggunakan database `quickbrew_test`.
 *   - Seluruh panggilan Midtrans di-stub.
 */
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());

$logFile = sys_get_temp_dir() . '/quickbrew_kedaluwarsa_test_' . getmypid() . '.log';
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
require_once APP_ROOT . '/app/Services/KedaluwarsaService.php';

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

echo "=== Pengujian Paket 5: Kedaluwarsa Pesanan CLI (DB: {$activeDb}) ===\n\n";

$mejaId      = (int) $pdo->query('SELECT id FROM meja ORDER BY id LIMIT 1')->fetchColumn();
$americanoId = (int) $pdo->query("SELECT id FROM menu WHERE nama_menu = 'Americano'")->fetchColumn();
$croissantId = (int) $pdo->query("SELECT id FROM menu WHERE nama_menu = 'Croissant Butter'")->fetchColumn();

function buat_pesanan_umur(int $mejaId, int $menuId, string $metode, int $menitLalu, string $nama = 'Pelanggan Uji'): array
{
    $priced = PemesananModel::price([['id' => $menuId, 'quantity' => 1]]);
    $id = PemesananModel::create([
        'meja_id' => $mejaId,
        'nama'    => $nama,
        'email'   => 'rahasia_' . $menitLalu . '@example.com',
        'catatan' => null,
        'metode'  => $metode,
        'total'   => $priced['total'],
        'lines'   => $priced['lines'],
    ]);
    $midtransOrderId = null;
    if ($metode === 'qris') {
        $midtransOrderId = 'TRX-' . $id . '-' . time();
        PemesananModel::simpanQris($id, $midtransOrderId, (string) json_encode([
            'qr_string' => '00020101021226TESTQR' . $id,
            'qr_url'    => 'https://api.sandbox.midtrans.com/v2/qris/tx-' . $id . '/qr-code',
        ]));
    }
    db()->prepare("UPDATE pemesanan SET waktu_pemesanan = CURRENT_TIMESTAMP - (:m * INTERVAL '1 minute') WHERE id = :id")
        ->execute([':m' => $menitLalu, ':id' => $id]);

    return ['id' => $id, 'midtrans_order_id' => $midtransOrderId, 'total' => $priced['total']];
}

// =========================================================================
// Siapkan 5 skenario pesanan:
//   1) QRIS baru 18 menit (< EXPIRY_MINUTES + 5 = 20 menit) -> TIDAK boleh disentuh
//   2) QRIS 25 menit (>= 20 menit) dan di Midtrans masih pending -> harus batal + expire (+ batalkan() dipanggil)
//   3) QRIS 25 menit (>= 20 menit) tetapi di Midtrans TERNYATA settlement -> harus jadi diproses + paid (TIDAK dibatalkan!)
//   4) Tunai baru 30 menit (< 60 menit) -> TIDAK boleh disentuh
//   5) Tunai 65 menit (>= 60 menit) -> harus batal + cancel
// =========================================================================
$qrisBaru       = buat_pesanan_umur($mejaId, $croissantId, 'qris', 18, 'Siti QRIS Baru');
$qrisKedaluwarsa= buat_pesanan_umur($mejaId, $croissantId, 'qris', 25, 'Budi QRIS Expire');
$qrisLunasTelat = buat_pesanan_umur($mejaId, $croissantId, 'qris', 25, 'Andi QRIS Lunas Telat');
$tunaiBaru      = buat_pesanan_umur($mejaId, $croissantId, 'tunai', 30, 'Dewi Tunai Baru');
$tunaiKedaluwarsa = buat_pesanan_umur($mejaId, $croissantId, 'tunai', 65, 'Eko Tunai Lama');

$httpCalls = [];
$stubPayment = new PembayaranService(static function (string $method, string $url) use (
    &$httpCalls,
    $qrisKedaluwarsa,
    $qrisLunasTelat
): array {
    $httpCalls[] = ['method' => $method, 'url' => $url];

    // Untuk $qrisLunasTelat: saat sinkron() dipanggil, kembalikan settlement!
    if (str_contains($url, (string) $qrisLunasTelat['midtrans_order_id']) && str_ends_with($url, '/status')) {
        return [
            'httpCode' => 200,
            'body'     => [
                'status_code'        => '200',
                'order_id'           => $qrisLunasTelat['midtrans_order_id'],
                'transaction_status' => 'settlement',
                'fraud_status'       => 'accept',
                'payment_type'       => 'qris',
                'gross_amount'       => (string) $qrisLunasTelat['total'],
            ],
        ];
    }

    // Untuk $qrisKedaluwarsa: saat sinkron() dipanggil masih pending, saat /cancel dipanggil sukses cancel
    if (str_contains($url, (string) $qrisKedaluwarsa['midtrans_order_id'])) {
        if (str_ends_with($url, '/status')) {
            return [
                'httpCode' => 200,
                'body'     => [
                    'status_code'        => '201',
                    'order_id'           => $qrisKedaluwarsa['midtrans_order_id'],
                    'transaction_status' => 'pending',
                    'payment_type'       => 'qris',
                    'gross_amount'       => (string) $qrisKedaluwarsa['total'],
                ],
            ];
        }
        if (str_ends_with($url, '/cancel')) {
            return [
                'httpCode' => 200,
                'body'     => [
                    'status_code'        => '200',
                    'order_id'           => $qrisKedaluwarsa['midtrans_order_id'],
                    'transaction_status' => 'cancel',
                    'payment_type'       => 'qris',
                    'gross_amount'       => (string) $qrisKedaluwarsa['total'],
                ],
            ];
        }
    }

    return ['httpCode' => 404, 'body' => ['status_code' => '404']];
});

$kedaluwarsaSvc = new KedaluwarsaService($stubPayment);

// =========================================================================
// 1. Uji --dry-run: mengidentifikasi kandidat tanpa mengubah DB atau memanggil Midtrans
// =========================================================================
$hasilDry = $kedaluwarsaSvc->jalankan(true, 60);
$masihMenungguCount = (int) $pdo->query("SELECT COUNT(*) FROM pemesanan WHERE status_pemesanan = 'menunggu_pembayaran'")->fetchColumn();

check(
    $hasilDry['dry_run'] === true
    && $hasilDry['kandidat_qris'] === 2
    && $hasilDry['kandidat_tunai'] === 1
    && $hasilDry['qris_kedaluwarsa'] === 0
    && $hasilDry['tunai_dibatalkan'] === 0
    && count($httpCalls) === 0
    && $masihMenungguCount === 5,
    'Mode dry-run mendeteksi 2 kandidat QRIS & 1 kandidat Tunai tanpa mengubah database atau memanggil Midtrans'
);

// =========================================================================
// 2. Eksekusi Sebenarnya: QRIS lunas terlambat diselamatkan, QRIS lewat batas -> expire, Tunai lewat batas -> cancel
// =========================================================================
$stokSebelum = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$croissantId}")->fetchColumn();
$hasilRun1   = $kedaluwarsaSvc->jalankan(false, 60);
$stokSesudah = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$croissantId}")->fetchColumn();

check(
    $hasilRun1['qris_kedaluwarsa'] === 1
    && $hasilRun1['qris_ternyata_lunas'] === 1
    && $hasilRun1['tunai_dibatalkan'] === 1,
    'Eksekusi memproses 1 QRIS expire, 1 QRIS ternyata lunas, dan 1 Tunai dibatalkan'
);

$rowQrisBaru        = PemesananModel::detail($qrisBaru['id']);
$rowQrisKedaluwarsa = PemesananModel::detail($qrisKedaluwarsa['id']);
$rowQrisLunasTelat  = PemesananModel::detail($qrisLunasTelat['id']);
$rowTunaiBaru       = PemesananModel::detail($tunaiBaru['id']);
$rowTunaiKedaluwarsa= PemesananModel::detail($tunaiKedaluwarsa['id']);

check(
    $rowQrisBaru['status_pemesanan'] === 'menunggu_pembayaran'
    && $rowQrisBaru['status_pembayaran'] === 'pending',
    'Pesanan QRIS berusia 18 menit (< 20 menit) tetap menunggu_pembayaran'
);
check(
    $rowQrisKedaluwarsa['status_pemesanan'] === 'batal'
    && $rowQrisKedaluwarsa['status_pembayaran'] === 'expire',
    'Pesanan QRIS lewat batas diubah menjadi status_pemesanan=batal dan status_pembayaran=expire'
);
check(
    $rowQrisLunasTelat['status_pemesanan'] === 'diproses'
    && $rowQrisLunasTelat['status_pembayaran'] === 'paid'
    && $stokSesudah === $stokSebelum - 1,
    'Pesanan QRIS yang ternyata sudah lunas saat sinkron() diubah menjadi diproses/paid (TIDAK dibatalkan) dan mengurangi stok'
);
check(
    $rowTunaiBaru['status_pemesanan'] === 'menunggu_pembayaran'
    && $rowTunaiBaru['status_pembayaran'] === 'pending',
    'Pesanan Tunai berusia 30 menit (< 60 menit) tetap menunggu_pembayaran'
);
check(
    $rowTunaiKedaluwarsa['status_pemesanan'] === 'batal'
    && $rowTunaiKedaluwarsa['status_pembayaran'] === 'cancel',
    'Pesanan Tunai lewat batas diubah menjadi status_pemesanan=batal dan status_pembayaran=cancel'
);

// Pastikan batalkan() hanya dipanggil untuk QRIS yang masih pending, BUKAN untuk QRIS yang sudah lunas
$cancelUrls = array_column(
    array_filter($httpCalls, static fn (array $c): bool => $c['method'] === 'POST' && str_ends_with($c['url'], '/cancel')),
    'url'
);
check(
    count($cancelUrls) === 1 && str_contains($cancelUrls[0], (string) $qrisKedaluwarsa['midtrans_order_id']),
    'Endpoint /cancel hanya dipanggil untuk QRIS yang masih pending, tidak untuk QRIS yang lunas'
);

// =========================================================================
// 3. Idempoten: Eksekusi ulang tidak mengubah apa pun lagi
// =========================================================================
$httpCalls = [];
$hasilRun2 = $kedaluwarsaSvc->jalankan(false, 60);
check(
    $hasilRun2['kandidat_qris'] === 0
    && $hasilRun2['kandidat_tunai'] === 0
    && $hasilRun2['qris_kedaluwarsa'] === 0
    && $hasilRun2['tunai_dibatalkan'] === 0
    && count($httpCalls) === 0,
    'Eksekusi kedua bersifat idempotent (0 kandidat tersisa, 0 perubahan)'
);

// =========================================================================
// 4. Uji Opsi --tunai-menit=25 lewat Skrip CLI bin/kedaluwarsa.php (Exit Code & Tanpa PII)
// =========================================================================
require_once $root . '/bin/kedaluwarsa.php';
ob_start();
$cliCode = jalankan_cli_kedaluwarsa(['bin/kedaluwarsa.php', '--tunai-menit=25']);
$cliText = (string) ob_get_clean();

$rowTunaiBaruSetelahCli = PemesananModel::detail($tunaiBaru['id']);
check(
    $cliCode === 0
    && str_contains($cliText, 'Batas Tunai=25 menit')
    && str_contains($cliText, 'dibatalkan=1')
    && $rowTunaiBaruSetelahCli['status_pemesanan'] === 'batal'
    && $rowTunaiBaruSetelahCli['status_pembayaran'] === 'cancel',
    'CLI bin/kedaluwarsa.php --tunai-menit=25 keluar dengan kode 0 dan membatalkan pesanan tunai berusia 30 menit'
);
check(
    !str_contains($cliText, 'Dewi Tunai Baru')
    && !str_contains($cliText, 'rahasia_'),
    'Output stdout CLI bin/kedaluwarsa.php bersih dari data pribadi (nama/email pelanggan)'
);

echo "\nRingkasan Paket 5 (Kedaluwarsa Pesanan CLI): {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
