<?php

/**
 * StatusController - API status pesanan untuk pelanggan.
 *
 * Endpoint:
 *   GET /api/status.php?id=<id>&t=<tok(id)>
 *
 * Dirancang untuk di-polling berkala oleh halaman pelanggan.
 * Hanya mengembalikan data status (ringan), bukan detail lengkap item pesanan.
 */
final class StatusController
{
    /**
     * GET /api/status.php?id=<id>&t=<tok(id)>
     * Mengembalikan status terkini sebuah pesanan beserta data stepper.
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

            // Validasi token (sama seperti PelangganController)
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

            $view = PemesananModel::statusView($order);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Status pesanan berhasil diambil.',
                'data'    => [
                    'id'               => (int) $order['id'],
                    'nomor'            => $order['number'],
                    'no_meja'          => $order['no_meja'],
                    'status_pemesanan' => $order['status_pemesanan'],
                    'status_pembayaran'=> $order['status_pembayaran'],
                    'diperbarui_pada'  => $order['diperbarui_pada'],
                    'view'             => [
                        'state' => $view['state'],
                        'title' => $view['title'],
                        'desc'  => $view['desc'],
                        'steps' => $view['steps'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (Throwable $e) {
            error_log('[QuickBrew API Status] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'debug'   => $e->getMessage(),
                'data'    => null,
            ]);
        }
    }
}