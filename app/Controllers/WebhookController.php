<?php
/**
 * WebhookController — menangani HTTP Notification (Webhook) dari Midtrans.
 *
 * Endpoint yang didukung:
 *   - POST /public/?page=webhook
 *   - POST /public/notify.php
 *
 * Alur kerja (sesuai spesifikasi panduan kerja):
 *   1. Tangkap payload JSON mentah dari php://input.
 *   2. Verifikasi signature_key memakai SHA512(order_id + status_code + gross_amount + server_key).
 *      Nilai gross_amount dipakai persis seperti string asli dari Midtrans (mis. "24000.00").
 *   3. Cari pesanan berdasarkan midtrans_order_id di dalam Database Transaction.
 *   4. Kunci baris pemesanan & pembayaran dengan SELECT ... FOR UPDATE (mencegah race condition).
 *      Jika status_pemesanan sudah 'dibayar' atau 'batal' (bukan 'menunggu_pembayaran'),
 *      langsung hentikan proses (idempotent) dan kembalikan 200 OK.
 *   5. Pemetaan status Midtrans:
 *      - settlement / capture (fraud_status: accept) -> pemesanan 'dibayar', pembayaran 'paid',
 *        kurangi stok menu dengan pengamanan terhadap CHECK (stok >= 0).
 *      - deny / cancel / expire / failure            -> pemesanan 'batal', pembayaran sesuai status.
 *      - pending                                     -> catat metode_pembayaran, status tetap menunggu.
 *   6. Catat kegagalan ke error_log tanpa pernah melog Server Key atau data sensitif.
 *   7. Selalu kembalikan HTTP 200 OK ke Midtrans agar tidak di-retry berulang.
 */
final class WebhookController
{
    /**
     * Action utama untuk menerima notifikasi webhook Midtrans.
     *
     * @param string|null $rawOverride Opsional untuk injeksi payload saat pengujian CLI/unit.
     */
    public function notify(?string $rawOverride = null): void
    {
        // 1. Tangkap payload request JSON dari Midtrans via php://input
        $rawBody = $rawOverride ?? (string) file_get_contents('php://input');
        if (trim($rawBody) === '') {
            $this->logError('Payload webhook kosong.');
            $this->respondOk('Empty payload ignored');
            return;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->logError('Payload webhook bukan JSON yang valid.');
            $this->respondOk('Invalid JSON ignored');
            return;
        }

        $orderId           = (string) ($payload['order_id'] ?? '');
        $statusCode        = (string) ($payload['status_code'] ?? '');
        // PENTING: gross_amount wajib menggunakan string asli apa adanya (mis. "24000.00"),
        // jangan di-cast ke int/float atau diformat ulang agar perhitungan SHA512 tidak meleset.
        $grossAmount       = isset($payload['gross_amount']) ? (string) $payload['gross_amount'] : '';
        $signatureKey      = (string) ($payload['signature_key'] ?? '');
        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus       = (string) ($payload['fraud_status'] ?? 'accept');
        $paymentType       = isset($payload['payment_type']) && $payload['payment_type'] !== ''
            ? (string) $payload['payment_type']
            : null;

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
            $this->logError("Field wajib webhook tidak lengkap (order_id={$orderId}).");
            $this->respondOk('Incomplete payload ignored');
            return;
        }

        // 2. Verifikasi signature_key menggunakan rumus SHA512(order_id + status_code + gross_amount + ServerKey)
        $serverKey = (string) env('MIDTRANS_SERVER_KEY', '');
        if ($serverKey === '') {
            // Jangan pernah melog isi key; cukup catat bahwa konfigurasi belum terisi
            $this->logError('Konfigurasi MIDTRANS_SERVER_KEY belum tersedia saat memproses webhook.');
            $this->respondOk('Configuration error logged');
            return;
        }

        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        if (!hash_equals($expectedSignature, $signatureKey)) {
            // JANGAN melog $serverKey ataupun $expectedSignature
            $this->logError("Signature tidak valid untuk midtrans_order_id={$orderId} (status={$transactionStatus}).");
            $this->respondOk('Invalid signature ignored');
            return;
        }

        // 3 & 4. Cari pesanan berdasarkan midtrans_order_id di dalam Database Transaction
        //        dan kunci baris dengan SELECT ... FOR UPDATE untuk mencegah race condition.
        $db = db();
        try {
            $db->beginTransaction();

            // Kunci baris pemesanan (p) dan pembayaran (b) sekaligus
            $stmtOrder = $db->prepare("
                SELECT p.id                AS pemesanan_id,
                       p.status_pemesanan  AS status_pemesanan,
                       p.total_harga       AS total_harga,
                       b.id                AS pembayaran_id,
                       b.status_pembayaran AS status_pembayaran
                  FROM pembayaran b
                  JOIN pemesanan p ON p.id = b.pemesanan_id
                 WHERE b.midtrans_order_id = ?
                   FOR UPDATE OF p, b
            ");
            $stmtOrder->execute([$orderId]);
            $pesanan = $stmtOrder->fetch(PDO::FETCH_ASSOC);

            if (!$pesanan) {
                $db->rollBack();
                $this->logError("Pesanan dengan midtrans_order_id={$orderId} tidak ditemukan.");
                $this->respondOk('Order not found');
                return;
            }

            $pemesananId     = (int) $pesanan['pemesanan_id'];
            $statusPemesanan = (string) $pesanan['status_pemesanan'];

            // Pastikan hanya diproses sekali (idempotent):
            // Jika status di database sudah 'dibayar', 'batal', atau tahap setelahnya ('diproses', 'siap', 'selesai'),
            // langsung hentikan proses dan kembalikan 200 OK.
            if ($statusPemesanan !== 'menunggu_pembayaran') {
                $db->commit();
                $this->respondOk('Already processed');
                return;
            }

            // 5. Pemetaan status Midtrans ke database
            $isPaid = $transactionStatus === 'settlement'
                   || ($transactionStatus === 'capture' && $fraudStatus === 'accept');
            $isFail = in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true);

            if ($isPaid) {
                // a) Ambil kebutuhan stok per menu dari detail_pemesanan, lalu kunci baris menu
                //    (SELECT ... FOR UPDATE) agar pengecekan & pengurangan stok terhadap CHECK (stok >= 0)
                //    aman dari race condition. (Dipisah dari SUM/GROUP BY karena PostgreSQL tidak mengizinkan
                //    FOR UPDATE langsung pada query yang memiliki GROUP BY / fungsi agregat.)
                $stmtQty = $db->prepare("
                    SELECT menu_id, SUM(jumlah) AS jumlah_dipesan
                      FROM detail_pemesanan
                     WHERE pemesanan_id = ?
                     GROUP BY menu_id
                     ORDER BY menu_id
                ");
                $stmtQty->execute([$pemesananId]);
                $kebutuhanPerMenu = $stmtQty->fetchAll(PDO::FETCH_KEY_PAIR); // [menu_id => jumlah_dipesan]

                $stmtLockMenu   = $db->prepare("SELECT id, nama_menu, stok FROM menu WHERE id = ? FOR UPDATE");
                $stmtUpdateStok = $db->prepare("UPDATE menu SET stok = ? WHERE id = ?");

                foreach ($kebutuhanPerMenu as $menuId => $jumlahDipesan) {
                    $menuId        = (int) $menuId;
                    $jumlahDipesan = (int) $jumlahDipesan;

                    $stmtLockMenu->execute([$menuId]);
                    $menuRow = $stmtLockMenu->fetch(PDO::FETCH_ASSOC);
                    if (!$menuRow) {
                        continue;
                    }

                    $stokSaatIni = (int) $menuRow['stok'];

                    if ($stokSaatIni < $jumlahDipesan) {
                        // Penanganan jika stok tiba-tiba kurang saat pembayaran masuk:
                        // Catat ke error_log dan set stok ke 0 (tidak negatif agar tidak melanggar CHECK stok >= 0),
                        // sehingga transaksi yang uangnya sudah masuk (settled) tetap tercatat 'dibayar'.
                        $this->logError(sprintf(
                            'Stok menu #%d (%s) tidak mencukupi saat pelunasan pemesanan_id=%d (dipesan=%d, tersedia=%d). Stok diset menjadi 0.',
                            $menuId,
                            $menuRow['nama_menu'],
                            $pemesananId,
                            $jumlahDipesan,
                            $stokSaatIni
                        ));
                        $stokBaru = 0;
                    } else {
                        $stokBaru = $stokSaatIni - $jumlahDipesan;
                    }

                    $stmtUpdateStok->execute([$stokBaru, $menuId]);
                }

                // b) Ubah status pesanan menjadi 'dibayar'
                $stmtUpdatePesanan = $db->prepare("
                    UPDATE pemesanan
                       SET status_pemesanan = 'dibayar'
                     WHERE id = ?
                ");
                $stmtUpdatePesanan->execute([$pemesananId]);

                // c) Ubah status pembayaran menjadi 'paid' & isi waktu_pembayaran (wajib oleh CHECK constraint)
                $stmtUpdatePembayaran = $db->prepare("
                    UPDATE pembayaran
                       SET status_pembayaran = 'paid',
                           metode_pembayaran = COALESCE(?, metode_pembayaran),
                           waktu_pembayaran  = CURRENT_TIMESTAMP
                     WHERE pemesanan_id = ?
                ");
                $stmtUpdatePembayaran->execute([$paymentType, $pemesananId]);

            } elseif ($isFail) {
                // Status deny / cancel / expire / failure -> ubah status pesanan menjadi 'batal'
                $stmtUpdatePesanan = $db->prepare("
                    UPDATE pemesanan
                       SET status_pemesanan = 'batal'
                     WHERE id = ?
                ");
                $stmtUpdatePesanan->execute([$pemesananId]);

                $stmtUpdatePembayaran = $db->prepare("
                    UPDATE pembayaran
                       SET status_pembayaran = ?,
                           metode_pembayaran = COALESCE(?, metode_pembayaran)
                     WHERE pemesanan_id = ?
                ");
                $stmtUpdatePembayaran->execute([$transactionStatus, $paymentType, $pemesananId]);

            } elseif ($transactionStatus === 'pending') {
                // Status pending -> cukup catat metode pembayaran (jika ada), status pesanan tetap 'menunggu_pembayaran'
                if ($paymentType !== null) {
                    $stmtPending = $db->prepare("
                        UPDATE pembayaran
                           SET status_pembayaran = 'pending',
                               metode_pembayaran = ?
                         WHERE pemesanan_id = ?
                    ");
                    $stmtPending->execute([$paymentType, $pemesananId]);
                }
            } else {
                // Status lain (mis. capture dengan fraud_status challenge)
                $this->logError("Status transaksi belum final (status={$transactionStatus}, fraud={$fraudStatus}) untuk midtrans_order_id={$orderId}.");
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->logError("Kesalahan database pada midtrans_order_id={$orderId}: " . $e->getMessage());
        }

        // 7. Selalu kembalikan HTTP Response Code 200 OK kepada Midtrans
        $this->respondOk('Notification processed');
    }

    /**
     * Catat kegagalan webhook ke error_log tanpa pernah menyertakan Server Key / data sensitif.
     */
    private function logError(string $message): void
    {
        error_log('[QuickBrew Webhook] ' . $message);
    }

    /**
     * Kembalikan HTTP 200 OK berformat JSON kepada Midtrans.
     */
    private function respondOk(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['status' => 'ok', 'message' => $message], JSON_UNESCAPED_SLASHES);
    }
}
