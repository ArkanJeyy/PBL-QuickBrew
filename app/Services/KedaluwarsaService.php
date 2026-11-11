<?php
require_once APP_ROOT . '/app/Services/PembayaranService.php';

/**
 * Layanan pembersihan pesanan menggantung (kedaluwarsa) untuk dijalankan secara berkala lewat CLI.
 *
 * Aturan kedaluwarsa:
 *   - QRIS menunggu_pembayaran lewat (PembayaranService::EXPIRY_MINUTES + 5) menit:
 *       1) Panggil sinkron() lebih dulu. Bila di Midtrans ternyata sudah settlement/capture,
 *          terapkanStatus() melunasi pesanan (diproses + stok berkurang) dan TIDAK dibatalkan.
 *       2) Bila masih menunggu, panggil batalkan() secara best-effort, kunci baris (FOR UPDATE),
 *          cek ulang status, lalu ubah pemesanan -> 'batal' dan pembayaran -> 'expire'.
 *   - Tunai menunggu_pembayaran lewat batas menit ($tunaiMenit, default TUNAI_EXPIRY_MINUTES = 60):
 *       Kunci baris (FOR UPDATE), cek ulang status, lalu ubah pemesanan -> 'batal' dan pembayaran -> 'cancel'.
 */
final class KedaluwarsaService
{
    /** Tambahan masa tenggang (grace period) di atas EXPIRY_MINUTES untuk pesanan QRIS (menit). */
    public const QRIS_GRACE_MINUTES = 5;

    /** Batas waktu default pesanan Tunai belum dibayar (menit). */
    public const DEFAULT_TUNAI_MINUTES = 60;

    private ?PembayaranService $pembayaranService;

    public function __construct(?PembayaranService $pembayaranService = null)
    {
        $this->pembayaranService = $pembayaranService;
    }

    /**
     * Proses seluruh pesanan menunggu_pembayaran yang telah melewati batas waktu.
     *
     * @return array{
     *   dry_run: bool,
     *   qris_batas_menit: int,
     *   tunai_batas_menit: int,
     *   kandidat_qris: int,
     *   kandidat_tunai: int,
     *   qris_kedaluwarsa: int,
     *   qris_ternyata_lunas: int,
     *   tunai_dibatalkan: int,
     *   id_diproses: int[]
     * }
     */
    public function jalankan(bool $dryRun = false, ?int $tunaiMenit = null): array
    {
        $qrisMenit = PembayaranService::EXPIRY_MINUTES + self::QRIS_GRACE_MINUTES;
        if ($tunaiMenit === null) {
            $envTunai   = (int) env('TUNAI_EXPIRY_MINUTES', (string) self::DEFAULT_TUNAI_MINUTES);
            $tunaiMenit = $envTunai > 0 ? $envTunai : self::DEFAULT_TUNAI_MINUTES;
        } else {
            $tunaiMenit = max(1, $tunaiMenit);
        }

        $db = db();
        $stmt = $db->prepare("
            SELECT p.id,
                   COALESCE(b.metode_pembayaran, 'tunai') AS metode_pembayaran,
                   COALESCE(b.status_pembayaran, 'pending') AS status_pembayaran,
                   b.midtrans_order_id
              FROM pemesanan p
              LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
             WHERE p.status_pemesanan = 'menunggu_pembayaran'
               AND (
                   (COALESCE(b.metode_pembayaran, 'tunai') = 'qris'
                    AND p.waktu_pemesanan <= CURRENT_TIMESTAMP - (:qris_menit * INTERVAL '1 minute'))
                   OR
                   (COALESCE(b.metode_pembayaran, 'tunai') = 'tunai'
                    AND p.waktu_pemesanan <= CURRENT_TIMESTAMP - (:tunai_menit * INTERVAL '1 minute'))
               )
             ORDER BY p.waktu_pemesanan ASC, p.id ASC
        ");
        $stmt->execute([
            ':qris_menit'  => $qrisMenit,
            ':tunai_menit' => $tunaiMenit,
        ]);
        $kandidat = $stmt->fetchAll();

        $ringkasan = [
            'dry_run'             => $dryRun,
            'qris_batas_menit'    => $qrisMenit,
            'tunai_batas_menit'   => $tunaiMenit,
            'kandidat_qris'       => 0,
            'kandidat_tunai'      => 0,
            'qris_kedaluwarsa'    => 0,
            'qris_ternyata_lunas' => 0,
            'tunai_dibatalkan'    => 0,
            'id_diproses'         => [],
        ];

        foreach ($kandidat as $row) {
            $id     = (int) $row['id'];
            $metode = (string) $row['metode_pembayaran'];

            if ($metode === 'qris') {
                $ringkasan['kandidat_qris']++;
                if ($dryRun) {
                    $ringkasan['id_diproses'][] = $id;
                    continue;
                }
                $hasil = $this->prosesQrisKedaluwarsa($id, trim((string) ($row['midtrans_order_id'] ?? '')));
                if ($hasil === 'lunas') {
                    $ringkasan['qris_ternyata_lunas']++;
                    $ringkasan['id_diproses'][] = $id;
                } elseif ($hasil === 'expire') {
                    $ringkasan['qris_kedaluwarsa']++;
                    $ringkasan['id_diproses'][] = $id;
                }
            } else {
                $ringkasan['kandidat_tunai']++;
                if ($dryRun) {
                    $ringkasan['id_diproses'][] = $id;
                    continue;
                }
                if ($this->prosesTunaiKedaluwarsa($id)) {
                    $ringkasan['tunai_dibatalkan']++;
                    $ringkasan['id_diproses'][] = $id;
                }
            }
        }

        return $ringkasan;
    }

    /**
     * @return string 'lunas' | 'expire' | 'dilewati'
     */
    private function prosesQrisKedaluwarsa(int $id, string $midtransOrderId): string
    {
        $db      = db();
        $service = $this->pembayaranService ??= new PembayaranService();

        // 1. Sinkronkan status ke Midtrans lebih dulu (jangan batalkan bila pelanggan sudah membayar)
        if ($midtransOrderId !== '') {
            try {
                $service->sinkron($midtransOrderId);
            } catch (Throwable $e) {
                error_log(sprintf('[Kedaluwarsa] Sinkron QRIS pemesanan_id=%d gagal: %s', $id, $e->getMessage()));
            }
        }

        // Cek status setelah sinkron()
        $stmtCek = $db->prepare(
            "SELECT p.status_pemesanan, b.status_pembayaran
               FROM pemesanan p
               LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
              WHERE p.id = ?"
        );
        $stmtCek->execute([$id]);
        $setelahSinkron = $stmtCek->fetch();
        if (!$setelahSinkron) {
            return 'dilewati';
        }

        $statusPesanan = (string) $setelahSinkron['status_pemesanan'];
        $statusBayar   = (string) ($setelahSinkron['status_pembayaran'] ?? '');

        if ($statusBayar === 'paid' || in_array($statusPesanan, ['diproses', 'siap', 'selesai', 'dibayar'], true)) {
            error_log(sprintf('[KEDALUWARSA QRIS] pemesanan_id=%d ternyata sudah lunas saat sinkron', $id));
            return 'lunas';
        }

        // Jika masih menunggu_pembayaran, lakukan pembatalan best-effort ke Midtrans di luar transaksi DB
        if ($statusPesanan === 'menunggu_pembayaran' && $midtransOrderId !== '') {
            try {
                $service->batalkan($midtransOrderId);
            } catch (Throwable $e) {
                error_log(sprintf('[Kedaluwarsa] Batalkan QRIS Midtrans pemesanan_id=%d gagal: %s', $id, $e->getMessage()));
            }
        }

        // 2. Kunci baris (FOR UPDATE) dan pastikan status akhir adalah batal + expire
        $db->beginTransaction();
        try {
            $stmtLock = $db->prepare(
                "SELECT p.status_pemesanan, b.status_pembayaran
                   FROM pemesanan p
                   LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
                  WHERE p.id = ?
                    FOR UPDATE OF p"
            );
            $stmtLock->execute([$id]);
            $locked = $stmtLock->fetch();
            if (!$locked) {
                $db->rollBack();
                return 'dilewati';
            }

            $statusSaatIni = (string) $locked['status_pemesanan'];
            $bayarSaatIni  = (string) ($locked['status_pembayaran'] ?? '');

            if ($bayarSaatIni === 'paid' || in_array($statusSaatIni, ['diproses', 'siap', 'selesai', 'dibayar'], true)) {
                $db->rollBack();
                return 'lunas';
            }

            if (!in_array($statusSaatIni, ['menunggu_pembayaran', 'batal'], true)) {
                $db->rollBack();
                return 'dilewati';
            }

            $db->prepare("UPDATE pemesanan SET status_pemesanan = 'batal' WHERE id = ?")
               ->execute([$id]);

            $db->prepare("UPDATE pembayaran SET status_pembayaran = 'expire' WHERE pemesanan_id = ? AND status_pembayaran <> 'paid'")
               ->execute([$id]);

            $db->commit();
            error_log(sprintf('[KEDALUWARSA QRIS] pemesanan_id=%d status=menunggu_pembayaran->batal pembayaran=expire', $id));
            return 'expire';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function prosesTunaiKedaluwarsa(int $id): bool
    {
        $db = db();
        $db->beginTransaction();
        try {
            $stmtLock = $db->prepare(
                "SELECT p.status_pemesanan, b.status_pembayaran
                   FROM pemesanan p
                   LEFT JOIN pembayaran b ON b.pemesanan_id = p.id
                  WHERE p.id = ?
                    FOR UPDATE OF p"
            );
            $stmtLock->execute([$id]);
            $locked = $stmtLock->fetch();
            if (!$locked) {
                $db->rollBack();
                return false;
            }

            if (
                (string) $locked['status_pemesanan'] !== 'menunggu_pembayaran'
                || (string) ($locked['status_pembayaran'] ?? '') === 'paid'
            ) {
                $db->rollBack();
                return false;
            }

            $db->prepare("UPDATE pemesanan SET status_pemesanan = 'batal' WHERE id = ?")
               ->execute([$id]);

            $db->prepare("UPDATE pembayaran SET status_pembayaran = 'cancel' WHERE pemesanan_id = ? AND status_pembayaran = 'pending'")
               ->execute([$id]);

            $db->commit();
            error_log(sprintf('[KEDALUWARSA TUNAI] pemesanan_id=%d status=menunggu_pembayaran->batal pembayaran=cancel', $id));
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
