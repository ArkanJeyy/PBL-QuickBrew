<?php
require_once APP_ROOT . '/app/Services/PembayaranService.php';

/**
 * WebhookController — menerima HTTP Notification (Webhook) resmi dari Midtrans
 * melalui endpoint tunggal POST /public/notify.php.
 *
 * Tugas controller ini hanya mem-parsing input JSON mentah, lalu mendelegasikan
 * verifikasi signature dan pembaruan status pesanan/stok ke PembayaranService:
 *   - 400 Bad Request           : body kosong, JSON rusak, atau field wajib kurang
 *   - 403 Forbidden             : signature_key tidak valid
 *   - 200 OK                    : sukses, sudah diproses, order tidak ditemukan, atau nominal tidak cocok
 *   - 500 Internal Server Error : exception tak terduga (setelah rollback & pencatatan log)
 */
final class WebhookController
{
    private ?PembayaranService $service;

    public function __construct(?PembayaranService $service = null)
    {
        $this->service = $service;
    }

    /**
     * Tangani request notifikasi webhook Midtrans.
     *
     * @param  string|null $rawOverride Payload JSON mentah opsional untuk pengujian otomatis CLI.
     * @return int Kode status HTTP yang dikirimkan.
     */
    public function notify(?string $rawOverride = null): int
    {
        $rawBody = $rawOverride ?? (string) file_get_contents('php://input');
        if (trim($rawBody) === '') {
            $this->logError('Payload webhook kosong.');
            return $this->respond(400, 'error', 'Payload kosong.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->logError('Payload webhook bukan JSON yang valid.');
            return $this->respond(400, 'error', 'Format JSON tidak valid.');
        }

        // Validasi kelengkapan field wajib; gross_amount dipertahankan apa adanya tanpa diformat ulang
        $orderId           = trim((string) ($payload['order_id'] ?? ''));
        $statusCode        = trim((string) ($payload['status_code'] ?? ''));
        $grossAmount       = isset($payload['gross_amount']) ? (string) $payload['gross_amount'] : '';
        $signatureKey      = trim((string) ($payload['signature_key'] ?? ''));
        $transactionStatus = trim((string) ($payload['transaction_status'] ?? ''));

        if (
            $orderId === ''
            || $statusCode === ''
            || trim($grossAmount) === ''
            || $signatureKey === ''
            || $transactionStatus === ''
        ) {
            $this->logError("Field wajib webhook tidak lengkap (order_id={$orderId}).");
            return $this->respond(400, 'error', 'Field wajib tidak lengkap.');
        }

        try {
            $service = $this->service ??= new PembayaranService();

            if (!$service->verifikasiSignature($payload)) {
                $this->logError("Signature tidak valid untuk midtrans_order_id={$orderId} (status={$transactionStatus}).");
                return $this->respond(403, 'error', 'Signature tidak valid.');
            }

            $hasil = $service->terapkanStatus($payload);
            return $this->respond(200, 'ok', $hasil);
        } catch (Throwable $e) {
            // Pastikan transaksi dibatalkan bila masih terbuka saat terjadi error tak terduga
            try {
                $db = db();
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
            } catch (Throwable $rollbackErr) {
                // Abaikan bila koneksi database sendiri sedang putus
            }

            $this->logError("Kesalahan tak terduga pada midtrans_order_id={$orderId}: " . $e->getMessage());
            return $this->respond(500, 'error', 'Terjadi kesalahan internal.');
        }
    }

    /** Catat kegagalan webhook ke error_log tanpa pernah menyertakan Server Key / data sensitif. */
    private function logError(string $message): void
    {
        error_log('[QuickBrew Webhook] ' . $message);
    }

    /** Kirim respons JSON beserta kode HTTP. */
    private function respond(int $httpCode, string $status, string $message): int
    {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
        } elseif (PHP_SAPI === 'cli') {
            @http_response_code($httpCode);
        }

        echo json_encode([
            'status'  => $status,
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $httpCode;
    }
}
