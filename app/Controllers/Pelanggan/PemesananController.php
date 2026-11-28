<?php
require_once APP_ROOT . '/app/Services/CheckoutService.php';

/**
 * PemesananController - API untuk pemesanan (CRUD sederhana).
 *
 * Endpoint:
 *   POST /api/pelanggan/pemesanan.php                     → store() (buat pesanan baru, dipakai checkout.js)
 *   GET  /api/pelanggan/pemesanan.php?action=show&id=&t=  → show()  (detail satu pesanan, token dibutuhkan)
 *
 * Daftar semua pesanan dipindah ke endpoint kasir (kasir.php).
 */
final class PemesananController
{
    private ?PembayaranService $pembayaranService;

    public function __construct(?PembayaranService $pembayaranService = null)
    {
        $this->pembayaranService = $pembayaranService;
    }

    /**
     * GET /api/pemesanan?action=show&id=<id>&t=<tok(id)>
     * Mengembalikan detail satu pesanan beserta item-nya.
     * Token ?t= pakai fungsi tok() bawaan project (mencegah akses pesanan orang lain).
     */
    public function show(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method tidak diizinkan.',
                'data'    => null,
            ]);
            return;
        }

        try {
            $id    = (int)    ($_GET['id'] ?? 0);
            $token = (string) ($_GET['t']  ?? '');

            // Validasi token keamanan (sama seperti PelangganController)
            if ($id <= 0 || !hash_equals(tok($id), $token)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Akses ditolak. Token tidak valid.',
                    'data'    => null,
                ]);
                return;
            }

            $order = PemesananModel::find($id);

            if (!$order) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Pesanan tidak ditemukan.',
                    'data'    => null,
                ]);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Detail pesanan berhasil diambil.',
                'data'    => self::formatOrder($order),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            error_log('[QuickBrew API Pemesanan] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'data'    => null,
            ]);
        }
    }

    /**
     * POST /api/pelanggan/pemesanan.php
     * Membuat pesanan baru dari keranjang pelanggan. Dipakai halaman Checkout (checkout.js).
     * Logika sama dengan checkout form karena memakai CheckoutService::proses(): CSRF, pembatasan laju,
     * idempotensi token form, cek pesanan belum dibayar, hitung ulang harga di server, dan QRIS Midtrans.
     *
     * Meja diambil dari session hasil scan QR (?meja=...). Bila session belum punya meja,
     * kode QR boleh dikirim di body sebagai "meja".
     *
     * Body (JSON atau form-data):
     * {
     *   "csrf_token": "...",            // dari halaman Checkout
     *   "meja"    : "MEJA01",          // opsional, hanya dipakai bila session belum punya meja
     *   "nama"    : "Budi",
     *   "email"   : "budi@email.com",
     *   "metode"  : "tunai" | "qris",
     *   "catatan" : "...",             // opsional
     *   "items"   : [                  // JSON string atau array
     *     { "id": 1, "quantity": 2, "size": "M", "type": "Ice",
     *       "sugar": "Normal Sugar", "ice": "Normal Ice", "addons": [] }
     *   ]
     * }
     *
     * Respons berhasil: 201 (dibuat) atau 200 (token form sudah pernah berhasil -> pesanan yang sama),
     *   data: id, nomor, token, metode, total, status_pemesanan, redirect (URL halaman pembayaran).
     * Respons gagal: 400/403/409/422/429/502 dengan "errors" per field (nama, email, metode, catatan)
     *   dan/atau "form" untuk pesan umum.
     */
    public function store(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method tidak diizinkan.',
                'data'    => null,
            ]);
            return;
        }

        try {
            // Dukung JSON body (Content-Type: application/json) maupun form-data
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $body = json_decode((string) file_get_contents('php://input'), true);
                $body = is_array($body) ? $body : [];
            } else {
                $body = $_POST;
            }

            $meja = $_SESSION['meja'] ?? null;
            if ($meja === null && is_string($body['meja'] ?? null) && trim($body['meja']) !== '') {
                $meja = MejaModel::findByKode(trim($body['meja'])) ?: null;
            }

            $hasil = (new CheckoutService($this->pembayaranService))->proses($body, $meja);

            if ($hasil['id'] === null) {
                http_response_code($hasil['status']);
                echo json_encode([
                    'success' => false,
                    'message' => $hasil['errors']['form'] ?? 'Periksa kembali data pesanan.',
                    'errors'  => $hasil['errors'],
                    'data'    => null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }

            $id    = $hasil['id'];
            $order = PemesananModel::find($id);

            http_response_code($hasil['status']);
            echo json_encode([
                'success' => true,
                'message' => $hasil['status'] === 201 ? 'Pesanan berhasil dibuat.' : 'Pesanan sudah dibuat sebelumnya.',
                'data'    => [
                    'id'               => $id,
                    'nomor'            => PemesananModel::displayNumber($id),
                    'token'            => tok($id), // token untuk akses GET show
                    'metode'           => $order['metode_pembayaran'] ?? $hasil['old']['metode'],
                    'total'            => isset($order['total_harga']) ? (int) $order['total_harga'] : null,
                    'status_pemesanan' => $order['status_pemesanan'] ?? 'menunggu_pembayaran',
                    'redirect'         => self::halamanUrl('pembayaran', ['id' => $id, 't' => tok($id)]),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            error_log('[QuickBrew API Pemesanan] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server. Silakan coba lagi.',
                'errors'  => ['form' => 'Terjadi kesalahan pada server. Silakan coba lagi.'],
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * URL halaman front controller (public/index.php) dari dalam endpoint API.
     * Di endpoint public/api/<folder>/<file>.php, BASE_URL berisi ".../public/api/<folder>" (dihitung dari SCRIPT_NAME),
     * sehingga url() akan menunjuk folder API. Bagian "/api/..." dibuang agar kembali ke folder public/.
     */
    private static function halamanUrl(string $page, array $query = []): string
    {
        $publik = (string) preg_replace('#/api(/.*)?$#', '', BASE_URL);
        return $publik . '/?' . http_build_query(['page' => $page] + $query);
    }

    /**
     * Format data pesanan dari PemesananModel::find() agar rapi untuk respons API.
     */
    private static function formatOrder(array $order): array
    {
        return [
            'id'                => (int)    $order['id'],
            'nomor'             => $order['number'],
            'nama_pelanggan'    => $order['nama_pelanggan'],
            'email_pelanggan'   => $order['email_pelanggan'],
            'no_meja'           => $order['no_meja'],
            'catatan'           => $order['catatan'],
            'subtotal'          => (int) $order['subtotal'],
            'pajak'             => (int) $order['tax'],
            'total_harga'       => (int) $order['total_harga'],
            'status_pemesanan'  => $order['status_pemesanan'],
            'status_pembayaran' => $order['status_pembayaran'],
            'metode_pembayaran' => $order['metode_pembayaran'],
            'waktu_pemesanan'   => $order['waktu_pemesanan'],
            'waktu_pembayaran'  => $order['waktu_pembayaran'],
            'batas_bayar'       => PemesananModel::paymentDeadline((string) $order['waktu_pemesanan']),
            'items'             => array_map(static fn (array $item): array => [
                'nama_menu' => $item['nama_menu'],
                'gambar'    => $item['image'],
                'harga'     => (int) $item['harga'],
                'jumlah'    => (int) $item['jumlah'],
                'subtotal'  => (int) $item['subtotal'],
                'catatan'   => $item['catatan'],
                'options'   => $item['options'],
            ], $order['items']),
        ];
    }
}