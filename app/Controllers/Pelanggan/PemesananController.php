<?php

/**
 * PemesananController - API untuk pemesanan (CRUD sederhana).
 *
 * Endpoint:
 *   POST /api/pemesanan                → store()   (buat pesanan baru)
 *   GET  /api/pemesanan?action=show&id=&t= → show() (detail satu pesanan, token dibutuhkan)
 *
 * Daftar semua pesanan dipindah ke endpoint kasir (kasir.php).
 */
final class PemesananController
{
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
     * POST /api/pemesanan
     * Membuat pesanan baru dari keranjang pelanggan.
     *
     * Body (JSON atau form-data):
     * {
     *   "meja"    : "MEJA01",          // kode QR meja (kode_qr di tabel meja)
     *   "nama"    : "Budi",
     *   "email"   : "budi@email.com",
     *   "metode"  : "tunai" | "qris",
     *   "catatan" : "...",             // opsional
     *   "items"   : [                  // JSON string atau array
     *     {
     *       "id"       : 1,
     *       "quantity" : 2,
     *       "size"     : "M",
     *       "type"     : "Ice",
     *       "sugar"    : "Normal Sugar",
     *       "ice"      : "Normal Ice",
     *       "addons"   : []
     *     }
     *   ]
     * }
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
                $body = json_decode(file_get_contents('php://input'), true) ?? [];
            } else {
                $body = $_POST;
            }

            // --- Validasi input ---
            $errors = [];

            $kodeQr  = trim((string) ($body['meja']    ?? ''));
            $nama    = trim((string) ($body['nama']    ?? ''));
            $email   = trim((string) ($body['email']   ?? ''));
            $metode  = (string)      ($body['metode']  ?? '');
            $catatan = trim((string) ($body['catatan'] ?? ''));

            // items bisa berupa array langsung (JSON body) atau JSON string (form-data)
            $rawItems = $body['items'] ?? null;
            if (is_string($rawItems)) {
                $rawItems = json_decode($rawItems, true);
            }

            if ($kodeQr === '') {
                $errors['meja'] = 'Kode meja wajib diisi.';
            }
            if ($nama === '' || text_length($nama) > 100) {
                $errors['nama'] = 'Nama wajib diisi (maks. 100 karakter).';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 150) {
                $errors['email'] = 'Email tidak valid.';
            }
            if (!isset(PemesananModel::METODE[$metode])) {
                $errors['metode'] = 'Metode pembayaran tidak valid. Pilih: tunai atau qris.';
            }
            if (text_length($catatan) > PemesananModel::NOTE_MAX) {
                $errors['catatan'] = 'Catatan maksimal ' . PemesananModel::NOTE_MAX . ' karakter.';
            }

            if ($errors) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Validasi gagal.',
                    'errors'  => $errors,
                    'data'    => null,
                ]);
                return;
            }

            // --- Cek meja ---
            $meja = MejaModel::findByKode($kodeQr);
            if (!$meja) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Meja tidak ditemukan. Pastikan kode QR benar.',
                    'data'    => null,
                ]);
                return;
            }

            // --- Hitung ulang harga di server ---
            $priced = PemesananModel::price($rawItems);
            if ($priced['error'] !== null) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => $priced['error'],
                    'data'    => null,
                ]);
                return;
            }

            // --- Simpan pesanan ---
            $id = PemesananModel::create([
                'meja_id' => (int) $meja['id'],
                'nama'    => $nama,
                'email'   => $email,
                'catatan' => $catatan !== '' ? $catatan : null,
                'metode'  => $metode,
                'total'   => $priced['total'],
                'lines'   => $priced['lines'],
            ]);

            // --- Respons ---
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat.',
                'data'    => [
                    'id'          => $id,
                    'nomor'       => PemesananModel::displayNumber($id),
                    'token'       => tok($id),   // token untuk akses GET show
                    'subtotal'    => $priced['subtotal'],
                    'pajak'       => $priced['tax'],
                    'total'       => $priced['total'],
                    'metode'      => $metode,
                    'status'      => 'menunggu_pembayaran',
                ],
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