<?php

require_once APP_ROOT . '/app/Helpers/EmailHelper.php';

final class InvoiceEmailService
{
    public static function kirim(int $pemesananId): bool
    {
        $db = db();

        try {
            // Ambil data pesanan, meja, dan pembayaran
            $stmt = $db->prepare("
                SELECT
                    p.id,
                    p.nama_pelanggan,
                    p.email_pelanggan,
                    p.total_harga,
                    m.no_meja,
                    b.status_pembayaran,
                    b.waktu_pembayaran,
                    p.invoice_email_sent_at
                FROM pemesanan p
                JOIN meja m ON m.id = p.meja_id
                JOIN pembayaran b ON b.pemesanan_id = p.id
                WHERE p.id = ?
            ");

            $stmt->execute([$pemesananId]);
            $pesanan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pesanan) {
                error_log("Invoice: pesanan #{$pemesananId} tidak ditemukan.");
                return false;
            }

            // Hanya kirim invoice untuk pembayaran lunas
            if ($pesanan['status_pembayaran'] !== 'paid') {
                return false;
            }

            // Jangan kirim ulang jika sudah tercatat terkirim
            if ($pesanan['invoice_email_sent_at'] !== null) {
                return true;
            }

            // Ambil detail menu pesanan
            $stmtDetail = $db->prepare("
                SELECT
                    d.harga,
                    d.jumlah,
                    d.subtotal,
                    d.catatan,
                    m.nama_menu
                FROM detail_pemesanan d
                JOIN menu m ON m.id = d.menu_id
                WHERE d.pemesanan_id = ?
                ORDER BY d.id
            ");

            $stmtDetail->execute([$pemesananId]);
            $items = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                error_log("Invoice: detail pesanan #{$pemesananId} kosong.");
                return false;
            }

            // Hitung subtotal dari snapshot detail pesanan
            $subtotal = array_sum(
                array_map(
                    static fn ($item) => (int) $item['subtotal'],
                    $items
                )
            );

            // Pajak merupakan selisih total harga dan subtotal
            $pajak = (int) $pesanan['total_harga'] - $subtotal;

            if ($pajak < 0) {
                error_log("Invoice: total pesanan #{$pemesananId} tidak valid.");
                return false;
            }

            $dataInvoice = [
                'id'              => $pemesananId,
                'nomor'            => 'QB-' . str_pad(
                    (string) $pemesananId,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
                'nama_pelanggan'   => $pesanan['nama_pelanggan'],
                'email_pelanggan'  => $pesanan['email_pelanggan'],
                'no_meja'          => $pesanan['no_meja'],
                'items'             => $items,
                'subtotal'          => $subtotal,
                'pajak'             => $pajak,
                'total_harga'       => (int) $pesanan['total_harga'],
            ];

            // Kirim email
            $berhasil = kirim_invoice_email($dataInvoice);

            if (!$berhasil) {
                error_log("Invoice: email pesanan #{$pemesananId} gagal dikirim.");
                return false;
            }

            // Catat waktu email berhasil dikirim
            $stmtUpdate = $db->prepare("
                UPDATE pemesanan
                SET invoice_email_sent_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND invoice_email_sent_at IS NULL
            ");

            $stmtUpdate->execute([$pemesananId]);

            return true;

        } catch (Throwable $e) {
            error_log(
                "[QuickBrew Invoice] Pesanan #{$pemesananId}: "
                . $e->getMessage()
            );

            return false;
        }
    }
}