<?php
/**
 * Suite pengujian otomatis integrasi pembayaran sisi pelanggan QuickBrew:
 *   - Menggunakan database khusus `quickbrew_test` (TIDAK menyentuh database development).
 *   - Transport HTTP Midtrans sepenuhnya di-stub (0 panggilan jaringan ke Midtrans).
 *   - Mencakup: Checkout QRIS, Checkout Tunai, kegagalan Midtrans, validasi pajak & manipulasi harga,
 *     seluruh skenario Webhook (400, 403, 200, 500 + rollback), stok kurang, sinkron 404, throttle,
 *     tandaiLunas() kasir, serta uji regresi QrSvg.
 */
declare(strict_types=1);

// 1. Isolasi environment SEBELUM memuat config/config.php (tanpa pernah membaca config/.env secara langsung)
ini_set('session.save_path', sys_get_temp_dir());

$logFile = sys_get_temp_dir() . '/quickbrew_pembayaran_test_' . getmypid() . '.log';
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
require_once APP_ROOT . '/app/Helpers/QrSvg.php';
require_once APP_ROOT . '/app/Models/MenuModel.php';
require_once APP_ROOT . '/app/Models/MejaModel.php';
require_once APP_ROOT . '/app/Models/PemesananModel.php';
require_once APP_ROOT . '/app/Services/PembayaranService.php';
require_once APP_ROOT . '/app/Controllers/PelangganController.php';
require_once APP_ROOT . '/app/Controllers/WebhookController.php';

// 2. Pastikan database `quickbrew_test` tersedia & terisolasi penuh
try {
    $adminPdo = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=postgres', env('DB_HOST', 'localhost'), env('DB_PORT', '5432')),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    if (!$adminPdo->query("SELECT 1 FROM pg_database WHERE datname = 'quickbrew_test'")->fetchColumn()) {
        $adminPdo->exec('CREATE DATABASE quickbrew_test');
    }
    unset($adminPdo);
} catch (PDOException $e) {
    fwrite(STDERR, 'Gagal menyiapkan database quickbrew_test: ' . $e->getMessage() . "\n");
    exit(1);
}

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

// Muat ulang skema bersih + jalankan migrasi 001 & 002 (uji idempotensi migrasi)
$pdo->exec((string) file_get_contents(APP_ROOT . '/SQL/QuickBrew.sql'));
$migrationSql = (string) file_get_contents(APP_ROOT . '/SQL/migrations/001_add_pembayaran_qris_data.sql');
$pdo->exec($migrationSql);
$pdo->exec($migrationSql);
$migration002Sql = (string) file_get_contents(APP_ROOT . '/SQL/migrations/002_pembatasan_laju.sql');
$pdo->exec($migration002Sql);
$pdo->exec($migration002Sql);

// -------------------------------------------------------------------------
// Helper assertion & utilitas uji
// -------------------------------------------------------------------------
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

function sign_webhook(string $orderId, string $statusCode, string $grossAmount, string $key = TEST_SERVER_KEY): string
{
    return hash('sha512', $orderId . $statusCode . $grossAmount . $key);
}

function send_webhook(WebhookController $ctrl, string $rawJson): array
{
    ob_start();
    $code = $ctrl->notify($rawJson);
    $body = (string) ob_get_clean();
    return ['code' => $code, 'body' => $body, 'json' => json_decode($body, true)];
}

function sample_qris_string(string $orderId, int $amount): string
{
    return '00020101021226610014COM.GO-JEK.WWW011993600914312345678900210G1234567890303UMI'
         . '51440014ID.CO.QRIS.WWW0215ID10260000123450303UMI520458145303360'
         . '54' . str_pad((string) strlen((string) $amount), 2, '0', STR_PAD_LEFT) . $amount
         . '5802ID5916QUICKBREW COFFEE6006MALANG610565141'
         . '62' . str_pad((string) (4 + strlen($orderId)), 2, '0', STR_PAD_LEFT)
         . '01' . str_pad((string) strlen($orderId), 2, '0', STR_PAD_LEFT) . $orderId
         . '6304A1B2';
}

echo "=== Pengujian Integrasi Pembayaran Pelanggan (DB: {$activeDb}) ===\n\n";

// Ambil ID menu contoh dari seed SQL/QuickBrew.sql
$americanoId = (int) $pdo->query("SELECT id FROM menu WHERE nama_menu = 'Americano'")->fetchColumn();
$croissantId = (int) $pdo->query("SELECT id FROM menu WHERE nama_menu = 'Croissant Butter'")->fetchColumn();
$kasirId     = (int) $pdo->query("SELECT id FROM pengguna WHERE role = 'kasir' LIMIT 1")->fetchColumn();

check($americanoId > 0 && $croissantId > 0 && $kasirId > 0, 'Data seed menu & pengguna tersedia di quickbrew_test');

// =========================================================================
// 1. Checkout QRIS Sukses + Validasi SUM(item_details) == gross_amount + Pajak
// =========================================================================
$httpCalls = [];
$stubHandler = null;
$transport = static function (string $method, string $url, ?array $body) use (&$httpCalls, &$stubHandler, $pdo): array {
    $httpCalls[] = [
        'method'        => $method,
        'url'           => $url,
        'body'          => $body,
        'inTransaction' => $pdo->inTransaction(),
    ];
    return ($stubHandler)($method, $url, $body);
};

$service = new PembayaranService($transport);

$stubHandler = static function (string $method, string $url, ?array $body): array {
    $oid = (string) ($body['transaction_details']['order_id'] ?? 'TRX-0-0');
    $amt = (int) ($body['transaction_details']['gross_amount'] ?? 0);
    return [
        'httpCode' => 200,
        'body'     => [
            'status_code'        => '201',
            'status_message'     => 'QRIS transaction is created',
            'transaction_id'     => 'tx-qris-001',
            'order_id'           => $oid,
            'gross_amount'       => number_format($amt, 2, '.', ''),
            'currency'           => 'IDR',
            'payment_type'       => 'qris',
            'transaction_status' => 'pending',
            'fraud_status'       => 'accept',
            'qr_string'          => sample_qris_string($oid, $amt),
            'expiry_time'        => date('Y-m-d H:i:s', time() + 900),
            'actions'            => [
                [
                    'name'   => 'generate-qr-code',
                    'method' => 'GET',
                    'url'    => 'https://api.sandbox.midtrans.com/v2/qris/tx-qris-001/qr-code',
                ],
            ],
        ],
    ];
};

$_SESSION = [];
$_GET     = ['meja' => 'MEJA01'];
$csrf     = csrf_token();

$cartItems = [
    [
        'id'       => $americanoId,
        'quantity' => 2,
        'size'     => 'L',            // +5000 -> 25000
        'type'     => 'Ice',          // +0
        'sugar'    => 'Normal Sugar', // +0
        'ice'      => 'Normal Ice',   // +0
        'addons'   => ['extra-shot'], // +4000 -> 29000 x 2 = 58000
    ],
    [
        'id'       => $croissantId,   // 24000 x 1 = 24000
        'quantity' => 1,
    ],
];
// Subtotal = 82000, Pajak 10% = 8200, Total = 90200

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => $csrf,
    'nama'       => 'Budi Santoso',
    'email'      => 'budi@example.com',
    'metode'     => 'qris',
    'catatan'    => 'Tolong sedotan kertas',
    'items'      => json_encode($cartItems),
];

$redirectUrl = null;
$ctrl = new PelangganController($service, static function (string $to) use (&$redirectUrl): void {
    $redirectUrl = $to;
});
unset($_GET['meja']);

ob_start();
$ctrl->checkout();
$checkoutHtml = (string) ob_get_clean();

check($redirectUrl !== null && str_contains($redirectUrl, 'page=pembayaran'), 'Checkout QRIS sukses melakukan redirect ke halaman pembayaran', (string) $redirectUrl);
check(count($httpCalls) === 1, 'Checkout QRIS memanggil Midtrans tepat 1 kali');
check($httpCalls[0]['method'] === 'POST' && str_ends_with($httpCalls[0]['url'], '/v2/charge'), 'Checkout QRIS memanggil endpoint Core API POST /v2/charge');
check($httpCalls[0]['inTransaction'] === false, 'Panggilan Midtrans dilakukan DI LUAR transaksi database');

$sentPayload = $httpCalls[0]['body'];
$sumItems    = array_sum(array_map(static fn (array $i): int => $i['price'] * $i['quantity'], $sentPayload['item_details']));
$pajakItems  = array_values(array_filter($sentPayload['item_details'], static fn (array $i): bool => $i['id'] === 'PAJAK'));

check($sentPayload['payment_type'] === 'qris', 'Payload Midtrans memakai payment_type = qris');
check(($sentPayload['custom_expiry']['expiry_duration'] ?? null) === PembayaranService::EXPIRY_MINUTES, 'Payload Midtrans memakai custom_expiry dari EXPIRY_MINUTES (15)');
check(count($pajakItems) === 1 && $pajakItems[0]['price'] === 8200, 'Pajak 10% (Rp 8.200) dikirim sebagai item_details tersendiri');
check($sumItems === 90200 && $sentPayload['transaction_details']['gross_amount'] === 90200, 'SUM(item_details) sama persis dengan gross_amount (90200)');

$qrisOrderId = (int) ($_SESSION['pesanan_baru'] ?? 0);
$qrisOrder   = PemesananModel::find($qrisOrderId);
$qrisDecoded = json_decode((string) ($qrisOrder['qris_data'] ?? ''), true);

check($qrisOrder !== null && $qrisOrder['status_pemesanan'] === 'menunggu_pembayaran', 'Pesanan QRIS tersimpan dengan status menunggu_pembayaran');
check($qrisOrder['catatan'] === 'Tolong sedotan kertas', 'Catatan umum tersimpan langsung di pemesanan.catatan');
check($qrisOrder['items'][0]['catatan'] === 'L, Ice, Normal Sugar, Normal Ice, Extra Shot', 'detail_pemesanan.catatan bersih dari penanda NOTE_MARKER');
check(preg_match('/^TRX-' . $qrisOrderId . '-\d+$/', (string) $qrisOrder['midtrans_order_id']) === 1, 'midtrans_order_id berformat TRX-{id}-{timestamp}');
check(is_array($qrisDecoded) && !empty($qrisDecoded['qr_string']) && str_starts_with($qrisDecoded['qr_url'], 'https://'), 'pembayaran.qris_data menyimpan JSON {qr_string, qr_url}');

// Render halaman pembayaran untuk pesanan QRIS (uji SVG inline + data URI + throttle sinkron)
$stubHandler = static function (string $method, string $url): array {
    return [
        'httpCode' => 200,
        'body'     => [
            'status_code'        => '201',
            'order_id'           => basename(dirname($url)),
            'gross_amount'       => '90200.00',
            'payment_type'       => 'qris',
            'transaction_status' => 'pending',
        ],
    ];
};

$httpCalls = [];
$_SESSION['qris_sync_last_at'] = 0;
$_GET = ['id' => (string) $qrisOrderId, 't' => tok($qrisOrderId)];

ob_start();
$ctrl->pembayaran();
$payHtml1 = (string) ob_get_clean();

check(str_contains($payHtml1, '<svg class="pay-qr"'), 'Halaman pembayaran QRIS merender SVG inline dari qr_string');
check(str_contains($payHtml1, 'href="data:image/svg+xml;utf8,'), 'Tombol Simpan QR memakai data URI SVG');
check(str_contains($payHtml1, 'data-clear-cart'), 'Kunjungan pertama halaman pembayaran menyertakan atribut data-clear-cart');
check(count($httpCalls) === 1, 'pembayaran() memanggil sinkron() pertama kali saat throttle kosong');

// Panggil pembayaran() 2 kali lagi dalam < 5 detik -> tidak boleh memanggil Midtrans lagi (throttle)
ob_start();
$ctrl->pembayaran();
$payHtml2 = (string) ob_get_clean();

ob_start();
$ctrl->pembayaran();
ob_end_clean();

check(!str_contains($payHtml2, 'data-clear-cart'), 'Kunjungan ulang halaman pembayaran tidak lagi mengosongkan keranjang');
check(count($httpCalls) === 1, 'Throttle sesi menahan panggilan sinkron() berulang dalam < 5 detik');

// Uji fallback <img> memakai qr_url bila pembuatan SVG dari qr_string gagal (mis. string > 644 byte)
$pdo->prepare("UPDATE pembayaran SET qris_data = ? WHERE pemesanan_id = ?")->execute([
    json_encode([
        'qr_string' => str_repeat('9', 700),
        'qr_url'    => 'https://api.sandbox.midtrans.com/v2/qris/tx-qris-001/qr-code',
    ], JSON_UNESCAPED_SLASHES),
    $qrisOrderId,
]);
ob_start();
$ctrl->pembayaran();
$payFallbackHtml = (string) ob_get_clean();
check(
    str_contains($payFallbackHtml, '<img class="pay-qr" src="https://api.sandbox.midtrans.com/v2/qris/tx-qris-001/qr-code"'),
    'Halaman pembayaran jatuh ke cadangan <img src="qr_url"> bila QrSvg melempar error'
);
// Kembalikan qris_data asli
PemesananModel::simpanQris($qrisOrderId, (string) $qrisOrder['midtrans_order_id'], json_encode($qrisDecoded, JSON_UNESCAPED_SLASHES));

// =========================================================================
// 2. Checkout Tunai (Tanpa Panggilan Midtrans) + Idempotensi Nonce
// =========================================================================
$httpCalls   = [];
$redirectUrl = null;
$csrfTunai   = csrf_token(); // token baru setelah checkout pertama berhasil
$_POST = [
    'csrf_token' => $csrfTunai,
    'nama'       => 'Siti Aminah',
    'email'      => 'siti@example.com',
    'metode'     => 'tunai',
    'catatan'    => '',
    'items'      => json_encode([['id' => $croissantId, 'quantity' => 1]]),
];

ob_start();
$ctrl->checkout();
ob_end_clean();

$tunaiOrderId = (int) ($_SESSION['pesanan_baru'] ?? 0);
$tunaiOrder   = PemesananModel::find($tunaiOrderId);

check(count($httpCalls) === 0, 'Checkout Tunai TIDAK memanggil Midtrans sama sekali');
check($redirectUrl !== null && $tunaiOrder !== null, 'Checkout Tunai menyimpan pesanan dan redirect ke halaman pembayaran');
check(
    $tunaiOrder['status_pemesanan'] === 'menunggu_pembayaran'
    && $tunaiOrder['metode_pembayaran'] === 'tunai'
    && $tunaiOrder['midtrans_order_id'] === null,
    'Pesanan Tunai tetap menunggu_pembayaran tanpa midtrans_order_id'
);

// 2b. Double-submit dengan token lama ($csrfTunai) -> TIDAK membuat pesanan baru, redirect ke pesanan yang sama
$countSebelumDup = (int) $pdo->query('SELECT COUNT(*) FROM pemesanan')->fetchColumn();
$redirectUrlDup  = null;
$ctrlDup = new PelangganController($service, static function (string $to) use (&$redirectUrlDup): void {
    $redirectUrlDup = $to;
});
ob_start();
$ctrlDup->checkout(); // $_POST masih memakai $csrfTunai yang sudah pernah berhasil
ob_end_clean();
$countSesudahDup = (int) $pdo->query('SELECT COUNT(*) FROM pemesanan')->fetchColumn();
check(
    $countSesudahDup === $countSebelumDup
    && $redirectUrlDup === url('pembayaran', ['id' => $tunaiOrderId, 't' => tok($tunaiOrderId)]),
    'Double-submit dengan token CSRF yang sama tidak membuat pesanan baru & redirect ke pesanan yang sama'
);

// 2c. Dua pesanan identik berturut-turut dengan csrf_token() baru -> menjadi dua pesanan berbeda
$csrfIdentik1 = csrf_token();
$_POST['csrf_token'] = $csrfIdentik1;
$redirectIdentik1 = null;
$ctrlIdentik1 = new PelangganController($service, static function (string $to) use (&$redirectIdentik1): void {
    $redirectIdentik1 = $to;
});
ob_start();
$ctrlIdentik1->checkout();
ob_end_clean();
$idIdentik1 = (int) ($_SESSION['pesanan_baru'] ?? 0);

$csrfIdentik2 = csrf_token();
$_POST['csrf_token'] = $csrfIdentik2;
$redirectIdentik2 = null;
$ctrlIdentik2 = new PelangganController($service, static function (string $to) use (&$redirectIdentik2): void {
    $redirectIdentik2 = $to;
});
ob_start();
$ctrlIdentik2->checkout();
ob_end_clean();
$idIdentik2 = (int) ($_SESSION['pesanan_baru'] ?? 0);

check(
    $csrfIdentik1 !== $csrfIdentik2
    && $idIdentik1 > $tunaiOrderId
    && $idIdentik2 > $idIdentik1
    && $redirectIdentik1 !== $redirectIdentik2,
    'Dua pesanan identik berturut-turut dengan token CSRF baru menghasilkan dua pesanan berbeda'
);

// =========================================================================
// 3. Checkout QRIS Gagal (Midtrans Error) -> Pesanan Batal + Keranjang Aman
// =========================================================================
$stubHandler = static function (): array {
    return [
        'httpCode' => 200,
        'body'     => [
            'status_code'    => '406',
            'status_message' => 'Duplicate order ID or channel unavailable',
        ],
    ];
};

unset($_SESSION['pesanan_baru']);
$redirectUrl = null;
$countBefore = (int) $pdo->query('SELECT COUNT(*) FROM pemesanan')->fetchColumn();
$csrf        = csrf_token(); // token baru setelah checkout sebelumnya berhasil

$_POST = [
    'csrf_token' => $csrf,
    'nama'       => 'Andi Gagal',
    'email'      => 'andi@example.com',
    'metode'     => 'qris',
    'catatan'    => '',
    'items'      => json_encode([['id' => $croissantId, 'quantity' => 1]]),
];

ob_start();
$ctrl->checkout();
$failedCheckoutHtml = (string) ob_get_clean();

$failedOrderRow = $pdo->query("
    SELECT p.id, p.status_pemesanan, b.status_pembayaran
      FROM pemesanan p
      JOIN pembayaran b ON b.pemesanan_id = p.id
     ORDER BY p.id DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

check($redirectUrl === null, 'Midtrans gagal: TIDAK redirect ke halaman pembayaran');
check(!isset($_SESSION['pesanan_baru']), 'Midtrans gagal: $_SESSION[pesanan_baru] tidak diset sehingga keranjang aman');
check(
    str_contains($failedCheckoutHtml, 'Pembayaran QRIS sedang bermasalah, coba lagi atau pilih Tunai'),
    'Midtrans gagal: menampilkan pesan error yang sesuai di form checkout'
);
check(
    (int) $failedOrderRow['id'] > $tunaiOrderId
    && $failedOrderRow['status_pemesanan'] === 'batal'
    && $failedOrderRow['status_pembayaran'] === 'failure',
    'Midtrans gagal: status pesanan menjadi batal dan status pembayaran menjadi failure'
);

// =========================================================================
// 4. Manipulasi Harga & Opsi dari Browser
// =========================================================================
$tamperedPriced = PemesananModel::price([
    [
        'id'        => $americanoId,
        'quantity'  => 2,
        'price'     => 1,      // manipulasi harga dari klien
        'unitPrice' => 1,
        'subtotal'  => 2,
        'total'     => 2,
        'size'      => 'M',
        'type'      => 'Hot',
        'sugar'     => 'Less Sugar',
        'ice'       => 'Less Ice',
        'addons'    => [],
    ],
]);
// Americano (20000 + ukuran M 3000) x 2 = 46000 + Pajak 4600 = 50600
check(
    $tamperedPriced['error'] === null && $tamperedPriced['total'] === 50600 && $tamperedPriced['tax'] === 4600,
    'Harga yang dimanipulasi dari browser diabaikan; server menghitung ulang Rp 50.600'
);

$invalidOptPriced = PemesananModel::price([
    [
        'id'       => $americanoId,
        'quantity' => 1,
        'size'     => 'JUMBO-GRATIS',
        'type'     => 'Ice',
        'sugar'    => 'Normal Sugar',
        'ice'      => 'Normal Ice',
        'addons'   => ['addon-palsu'],
    ],
]);
check($invalidOptPriced['error'] !== null, 'Opsi kustomisasi palsu dari browser ditolak oleh PemesananModel::price()');

// =========================================================================
// 5. Pengujian Webhook (400, 403, 200, Status Mapping, Idempotency, Stok, 500)
// =========================================================================
$webhookCtrl     = new WebhookController($service);
$midtransOrderId = (string) $qrisOrder['midtrans_order_id'];

// 5a. Payload kosong / JSON rusak / field kurang -> 400
$rEmpty   = send_webhook($webhookCtrl, '   ');
$rBadJson = send_webhook($webhookCtrl, '{invalid-json');
$rMissing = send_webhook($webhookCtrl, json_encode(['order_id' => $midtransOrderId, 'status_code' => '200']));

check($rEmpty['code'] === 400, 'Webhook payload kosong -> HTTP 400');
check($rBadJson['code'] === 400, 'Webhook JSON rusak -> HTTP 400');
check($rMissing['code'] === 400, 'Webhook field wajib kurang -> HTTP 400');

// 5b. Signature salah -> 403 (termasuk bila signature dihitung tanpa desimal ".00")
$rBadSig = send_webhook($webhookCtrl, json_encode([
    'order_id'           => $midtransOrderId,
    'status_code'        => '200',
    'gross_amount'       => '90200.00',
    'transaction_status' => 'settlement',
    'signature_key'      => sign_webhook($midtransOrderId, '200', '90200'), // salah format gross_amount
]));
check($rBadSig['code'] === 403, 'Webhook signature salah -> HTTP 403');

// 5c. Order tidak ditemukan (termasuk percobaan menebak format TRX-{tunaiId}-{ts}) -> 200 & tidak mengubah pesanan tunai
$fakeTrxForTunai = "TRX-{$tunaiOrderId}-1730809999";
$rUnknown = send_webhook($webhookCtrl, json_encode([
    'order_id'           => $fakeTrxForTunai,
    'status_code'        => '200',
    'gross_amount'       => '26400.00',
    'transaction_status' => 'settlement',
    'signature_key'      => sign_webhook($fakeTrxForTunai, '200', '26400.00'),
]));
$tunaiAfterFake = PemesananModel::find($tunaiOrderId);
check(
    $rUnknown['code'] === 200 && $tunaiAfterFake['status_pemesanan'] === 'menunggu_pembayaran',
    'Webhook order_id tak terdaftar -> HTTP 200 dan tidak memakai fallback regex TRX-{id}'
);

// 5d. Nominal gross_amount tidak cocok dengan pembayaran.total_bayar -> 200, diabaikan + dicatat log
$rWrongAmount = send_webhook($webhookCtrl, json_encode([
    'order_id'           => $midtransOrderId,
    'status_code'        => '200',
    'gross_amount'       => '10000.00',
    'transaction_status' => 'settlement',
    'signature_key'      => sign_webhook($midtransOrderId, '200', '10000.00'),
]));
$afterWrongAmt = PemesananModel::find($qrisOrderId);
check(
    $rWrongAmount['code'] === 200 && $afterWrongAmt['status_pemesanan'] === 'menunggu_pembayaran',
    'Webhook gross_amount tidak cocok -> HTTP 200 dan pesanan tidak dilunasi'
);

// 5e. Webhook pending -> catat metode_pembayaran, status tetap menunggu_pembayaran
$rPending = send_webhook($webhookCtrl, json_encode([
    'order_id'           => $midtransOrderId,
    'status_code'        => '201',
    'gross_amount'       => '90200.00',
    'payment_type'       => 'qris',
    'transaction_status' => 'pending',
    'signature_key'      => sign_webhook($midtransOrderId, '201', '90200.00'),
]));
$afterPending = PemesananModel::find($qrisOrderId);
check(
    $rPending['code'] === 200
    && $afterPending['status_pemesanan'] === 'menunggu_pembayaran'
    && $afterPending['status_pembayaran'] === 'pending'
    && $afterPending['metode_pembayaran'] === 'qris',
    'Webhook pending mencatat metode_pembayaran dan membiarkan status menunggu_pembayaran'
);

// 5f. Webhook capture + fraud_status challenge -> diabaikan + log, tetap menunggu_pembayaran
$rChallenge = send_webhook($webhookCtrl, json_encode([
    'order_id'           => $midtransOrderId,
    'status_code'        => '200',
    'gross_amount'       => '90200.00',
    'payment_type'       => 'qris',
    'transaction_status' => 'capture',
    'fraud_status'       => 'challenge',
    'signature_key'      => sign_webhook($midtransOrderId, '200', '90200.00'),
]));
$afterChallenge = PemesananModel::find($qrisOrderId);
check(
    $rChallenge['code'] === 200 && $afterChallenge['status_pemesanan'] === 'menunggu_pembayaran',
    'Webhook capture + challenge tidak melunasi pesanan'
);

// 5g. Webhook settlement -> status_pemesanan = diproses (bukan dibayar), pembayaran = paid, stok berkurang
$stokAmBefore = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
$stokCrBefore = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$croissantId}")->fetchColumn();

$settlePayload = json_encode([
    'order_id'           => $midtransOrderId,
    'status_code'        => '200',
    'gross_amount'       => '90200.00',
    'payment_type'       => 'qris',
    'transaction_status' => 'settlement',
    'fraud_status'       => 'accept',
    'signature_key'      => sign_webhook($midtransOrderId, '200', '90200.00'),
]);
$rSettle = send_webhook($webhookCtrl, $settlePayload);

$afterSettle = PemesananModel::find($qrisOrderId);
$stokAmAfter = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
$stokCrAfter = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$croissantId}")->fetchColumn();
$waktuBayar  = $pdo->query("SELECT waktu_pembayaran FROM pembayaran WHERE pemesanan_id = {$qrisOrderId}")->fetchColumn();

check(
    $rSettle['code'] === 200
    && $afterSettle['status_pemesanan'] === 'diproses'
    && $afterSettle['status_pembayaran'] === 'paid'
    && !empty($waktuBayar),
    'Webhook settlement mengubah pemesanan menjadi diproses dan pembayaran menjadi paid + waktu_pembayaran'
);
check(
    $stokAmAfter === ($stokAmBefore - 2) && $stokCrAfter === ($stokCrBefore - 1),
    'Webhook settlement mengurangi stok masing-masing menu sesuai jumlah pesanan'
);

// 5h. Settlement ganda -> stok TIDAK berkurang dua kali
$rSettleDup = send_webhook($webhookCtrl, $settlePayload);
$stokAmDup  = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
$stokCrDup  = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$croissantId}")->fetchColumn();
check(
    $rSettleDup['code'] === 200 && $stokAmDup === $stokAmAfter && $stokCrDup === $stokCrAfter,
    'Webhook settlement ganda bersifat idempotent (stok tidak dikurangi dua kali)'
);

// 5i. Pending datang terlambat SETELAH settlement -> diabaikan
$rLatePending = send_webhook($webhookCtrl, json_encode([
    'order_id'           => $midtransOrderId,
    'status_code'        => '201',
    'gross_amount'       => '90200.00',
    'payment_type'       => 'qris',
    'transaction_status' => 'pending',
    'signature_key'      => sign_webhook($midtransOrderId, '201', '90200.00'),
]));
$afterLatePending = PemesananModel::find($qrisOrderId);
check(
    $rLatePending['code'] === 200
    && $afterLatePending['status_pemesanan'] === 'diproses'
    && $afterLatePending['status_pembayaran'] === 'paid',
    'Webhook pending setelah settlement diabaikan'
);

// Helper pembuat pesanan uji cepat untuk skenario status lainnya
$buatPesananUji = static function (string $suffix, int $menuId, int $qty = 1) use ($pdo): array {
    $priced = PemesananModel::price([[
        'id'       => $menuId,
        'quantity' => $qty,
        'size'     => 'M',
        'type'     => 'Ice',
        'sugar'    => 'Normal Sugar',
        'ice'      => 'Normal Ice',
        'addons'   => [],
    ]]);
    $mejaId = (int) $pdo->query("SELECT id FROM meja WHERE kode_qr = 'MEJA01'")->fetchColumn();
    $id     = PemesananModel::create([
        'meja_id' => $mejaId,
        'nama'    => 'Pelanggan ' . $suffix,
        'email'   => strtolower($suffix) . '@example.com',
        'catatan' => null,
        'metode'  => 'qris',
        'total'   => $priced['total'],
        'lines'   => $priced['lines'],
    ]);
    $oid = "TRX-{$id}-173081" . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    PemesananModel::simpanQris($id, $oid, json_encode(['qr_string' => sample_qris_string($oid, $priced['total']), 'qr_url' => '']));
    return ['id' => $id, 'oid' => $oid, 'gross' => number_format($priced['total'], 2, '.', '')];
};

// 5j. Webhook capture + fraud_status accept -> lunas ('diproses' + 'paid' + stok berkurang)
$ordCap        = $buatPesananUji('capture-accept', $americanoId, 1);
$stokBeforeCap = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
$rCapAccept    = send_webhook($webhookCtrl, json_encode([
    'order_id'           => $ordCap['oid'],
    'status_code'        => '200',
    'gross_amount'       => $ordCap['gross'],
    'payment_type'       => 'qris',
    'transaction_status' => 'capture',
    'fraud_status'       => 'accept',
    'signature_key'      => sign_webhook($ordCap['oid'], '200', $ordCap['gross']),
]));
$afterCap     = PemesananModel::find($ordCap['id']);
$stokAfterCap = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
check(
    $rCapAccept['code'] === 200
    && $afterCap['status_pemesanan'] === 'diproses'
    && $afterCap['status_pembayaran'] === 'paid'
    && $stokAfterCap === ($stokBeforeCap - 1),
    'Webhook capture + accept mengubah pesanan menjadi diproses dan mengurangi stok'
);

// 5k. Webhook deny, cancel, failure, expire -> masing-masing membatalkan pesanan & mencatat status pembayaran
foreach (['deny', 'cancel', 'failure', 'expire'] as $failStatus) {
    $ordFail   = $buatPesananUji($failStatus, $americanoId, 1);
    $stokAwal  = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
    $rFailStat = send_webhook($webhookCtrl, json_encode([
        'order_id'           => $ordFail['oid'],
        'status_code'        => '202',
        'gross_amount'       => $ordFail['gross'],
        'payment_type'       => 'qris',
        'transaction_status' => $failStatus,
        'signature_key'      => sign_webhook($ordFail['oid'], '202', $ordFail['gross']),
    ]));
    $afterFail = PemesananModel::find($ordFail['id']);
    $stokAkhir = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
    check(
        $rFailStat['code'] === 200
        && $afterFail['status_pemesanan'] === 'batal'
        && $afterFail['status_pembayaran'] === $failStatus
        && $stokAkhir === $stokAwal,
        "Webhook {$failStatus} -> pemesanan batal, pembayaran {$failStatus}, stok tetap"
    );
}

// 5l. Stok tiba-tiba kurang saat pelunasan -> stok diset 0 (tidak negatif), log tercatat, pesanan tetap diproses & paid
$pdo->exec("UPDATE menu SET stok = 3 WHERE id = {$americanoId}");
$ordShort = $buatPesananUji('stok-kurang', $americanoId, 3);
// Simulasikan pesanan lain menghabiskan 2 stok sebelum pembayaran ini masuk
$pdo->exec("UPDATE menu SET stok = 1 WHERE id = {$americanoId}");

$rShort = send_webhook($webhookCtrl, json_encode([
    'order_id'           => $ordShort['oid'],
    'status_code'        => '200',
    'gross_amount'       => $ordShort['gross'],
    'payment_type'       => 'qris',
    'transaction_status' => 'settlement',
    'signature_key'      => sign_webhook($ordShort['oid'], '200', $ordShort['gross']),
]));
$afterShort = PemesananModel::find($ordShort['id']);
$stokShort  = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
check(
    $rShort['code'] === 200
    && $stokShort === 0
    && $afterShort['status_pemesanan'] === 'diproses'
    && $afterShort['status_pembayaran'] === 'paid',
    'Stok kurang saat pelunasan: stok menjadi 0 (aman terhadap CHECK >= 0) dan pembayaran tetap tercatat lunas'
);
$pdo->exec("UPDATE menu SET stok = 40 WHERE id = {$americanoId}");

// 5m. Error database tak terduga saat webhook -> HTTP 500 + rollback penuh (stok tidak ikut berkurang)
$ordDbErr      = $buatPesananUji('db-error', $americanoId, 2);
$stokBeforeErr = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();

$pdo->exec("
    CREATE OR REPLACE FUNCTION uji_gagal_update_pemesanan() RETURNS TRIGGER AS \$\$
    BEGIN
        RAISE EXCEPTION 'Simulasi gangguan database saat update pemesanan';
    END;
    \$\$ LANGUAGE plpgsql;
    CREATE TRIGGER trg_uji_gagal_pemesanan
        BEFORE UPDATE ON pemesanan
        FOR EACH ROW EXECUTE FUNCTION uji_gagal_update_pemesanan();
");

$rDbErr = send_webhook($webhookCtrl, json_encode([
    'order_id'           => $ordDbErr['oid'],
    'status_code'        => '200',
    'gross_amount'       => $ordDbErr['gross'],
    'payment_type'       => 'qris',
    'transaction_status' => 'settlement',
    'signature_key'      => sign_webhook($ordDbErr['oid'], '200', $ordDbErr['gross']),
]));

$pdo->exec("
    DROP TRIGGER IF EXISTS trg_uji_gagal_pemesanan ON pemesanan;
    DROP FUNCTION IF EXISTS uji_gagal_update_pemesanan();
");

$afterDbErr   = PemesananModel::find($ordDbErr['id']);
$stokAfterErr = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$americanoId}")->fetchColumn();
check(
    $rDbErr['code'] === 500
    && !$pdo->inTransaction()
    && $stokAfterErr === $stokBeforeErr
    && $afterDbErr['status_pemesanan'] === 'menunggu_pembayaran'
    && $afterDbErr['status_pembayaran'] === 'pending',
    'Error database tak terduga -> HTTP 500 dan seluruh perubahan dalam transaksi di-rollback'
);

// =========================================================================
// 6. Kontrak sinkron() 404 -> null & sinkronisasi pelunasan setelah throttle lewat
// =========================================================================
$stubHandler = static function (): array {
    return ['httpCode' => 404, 'body' => ['status_code' => '404', 'status_message' => 'Transaction doesn\'t exist.']];
};
check($service->sinkron('TRX-TIDAK-ADA-404') === null, 'sinkron() mengembalikan null saat HTTP 404');

$stubHandler = static function (): array {
    return ['httpCode' => 200, 'body' => ['status_code' => '404', 'status_message' => 'Transaction doesn\'t exist.']];
};
check($service->sinkron('TRX-TIDAK-ADA-BODY-404') === null, 'sinkron() mengembalikan null saat HTTP 200 tetapi status_code 404');

// Simulasikan jeda > 5 detik pada halaman pembayaran -> sinkron() mengambil settlement & memperbarui DB
$stubHandler = static function () use ($ordDbErr): array {
    return [
        'httpCode' => 200,
        'body'     => [
            'status_code'        => '200',
            'order_id'           => $ordDbErr['oid'],
            'gross_amount'       => $ordDbErr['gross'],
            'payment_type'       => 'qris',
            'transaction_status' => 'settlement',
            'fraud_status'       => 'accept',
        ],
    ];
};
$_SESSION['qris_sync_last_at'] = time() - 6;
$_GET = ['id' => (string) $ordDbErr['id'], 't' => tok($ordDbErr['id'])];
ob_start();
$ctrl->pembayaran();
$syncedHtml = (string) ob_get_clean();

$afterSync = PemesananModel::find($ordDbErr['id']);
check(
    $afterSync['status_pemesanan'] === 'diproses'
    && $afterSync['status_pembayaran'] === 'paid'
    && str_contains($syncedHtml, 'Pembayaran Diterima'),
    'Cadangan sinkron() di pembayaran() melunasi pesanan setelah interval throttle 5 detik lewat'
);

// =========================================================================
// 7. Kontrak tandaiLunas() untuk Pembayaran Tunai oleh Kasir (pengguna_id terisi)
// =========================================================================
$stokCrBeforeCash = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$croissantId}")->fetchColumn();
$lunas1 = $service->tandaiLunas($tunaiOrderId, 'tunai', $kasirId);
$lunas2 = $service->tandaiLunas($tunaiOrderId, 'tunai', $kasirId);

$tunaiPaidRow = $pdo->query("
    SELECT p.status_pemesanan, b.status_pembayaran, b.metode_pembayaran, b.pengguna_id, b.waktu_pembayaran
      FROM pemesanan p
      JOIN pembayaran b ON b.pemesanan_id = p.id
     WHERE p.id = {$tunaiOrderId}
")->fetch(PDO::FETCH_ASSOC);
$stokCrAfterCash = (int) $pdo->query("SELECT stok FROM menu WHERE id = {$croissantId}")->fetchColumn();

check(
    $lunas1 === true
    && $lunas2 === false
    && $tunaiPaidRow['status_pemesanan'] === 'diproses'
    && $tunaiPaidRow['status_pembayaran'] === 'paid'
    && $tunaiPaidRow['metode_pembayaran'] === 'tunai'
    && (int) $tunaiPaidRow['pengguna_id'] === $kasirId
    && !empty($tunaiPaidRow['waktu_pembayaran'])
    && $stokCrAfterCash === ($stokCrBeforeCash - 1),
    'tandaiLunas() melunasi pesanan tunai secara idempotent dan mengisi pembayaran.pengguna_id'
);

// =========================================================================
// 8. Verifikasi Keamanan Log (Tidak Ada Server Key / Secret di error_log)
// =========================================================================
$logContent = is_file($logFile) ? (string) file_get_contents($logFile) : '';
@unlink($logFile);

check(
    $logContent !== ''
    && !str_contains($logContent, TEST_SERVER_KEY)
    && !str_contains($logContent, TEST_CLIENT_KEY)
    && !str_contains($logContent, TEST_APP_SECRET),
    'error_log mencatat kegagalan tanpa pernah membocorkan Server Key / rahasia aplikasi'
);

echo "\nRingkasan Pembayaran & Webhook: {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
