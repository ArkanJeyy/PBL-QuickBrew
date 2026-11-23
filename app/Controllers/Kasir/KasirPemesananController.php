<?php
/**
 * KasirPemesananController – API khusus kasir / admin.
 *
 * Endpoint (didefinisikan di public/api/kasir.php):
 *   GET       /api/kasir[?status=&limit=&offset=]     → index()        (daftar pesanan)
 *   GET       /api/kasir?action=show&id=              → show()         (detail satu pesanan)
 *   PUT|PATCH /api/kasir?action=update&id=            → updateStatus() (ubah status, body JSON)
 *
 * Semua endpoint mengharuskan login + role kasir atau admin (guard: wajib_role_api()).
 * Logika bisnis (peta transisi status, pembatalan QRIS, audit) ada di PemesananModel.
 */
final class KasirPemesananController
{
    /** Kirim respons JSON seragam. */
    private function kirim(int $kode, bool $sukses, string $pesan, $data = null): void
    {
        http_response_code($kode);
        echo json_encode(
            ['success' => $sukses, 'message' => $pesan, 'data' => $data],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    // -----------------------------------------------------------------
    // 1. Daftar pesanan (kasir / admin)
    // -----------------------------------------------------------------
    public function index(): void
    {
        // Guard: 401 bila belum login, 403 bila role tidak cukup (langsung exit).
        wajib_role_api(['kasir', 'admin']);

        $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
        if ($status === '') {
            $status = null;
        }
        if ($status !== null && !array_key_exists($status, PemesananModel::STATUS_LABEL)) {
            $this->kirim(422, false, 'Nilai status tidak valid.');
            return;
        }

        $limit  = (int) ($_GET['limit']  ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);

        try {
            $orders = PemesananModel::daftarAntrean($status, $limit, $offset);
            $this->kirim(200, true, 'Daftar pesanan berhasil diambil.', $orders);
        } catch (Throwable $e) {
            error_log('[QuickBrew API KasirPemesanan index] ' . $e->getMessage());
            $this->kirim(500, false, 'Terjadi kesalahan pada server.');
        }
    }

    // -----------------------------------------------------------------
    // 2. Detail satu pesanan (kasir / admin)
    // -----------------------------------------------------------------
    public function show(): void
    {
        wajib_role_api(['kasir', 'admin']);

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->kirim(400, false, 'Parameter id tidak valid.');
            return;
        }

        try {
            $order = PemesananModel::detail($id);
            if ($order === null) {
                $this->kirim(404, false, 'Pesanan tidak ditemukan.');
                return;
            }
            $this->kirim(200, true, 'Detail pesanan berhasil diambil.', $order);
        } catch (Throwable $e) {
            error_log('[QuickBrew API KasirPemesanan show] ' . $e->getMessage());
            $this->kirim(500, false, 'Terjadi kesalahan pada server.');
        }
    }

    // -----------------------------------------------------------------
    // 3. Ubah status pesanan (kasir / admin)
    //    - Content-Type: application/json wajib
    //    - Body: { "status": "siap" }
    //    - Transisi yang sah ditentukan PemesananModel::PETA_TRANSISI
    // -----------------------------------------------------------------
    public function updateStatus(): void
    {
        $staf = wajib_role_api(['kasir', 'admin']);   // data staf dari $_SESSION['staf']

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            $this->kirim(415, false, 'Content-Type harus application/json');
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->kirim(400, false, 'Parameter id tidak valid.');
            return;
        }

        $payload   = json_decode((string) file_get_contents('php://input'), true);
        $newStatus = is_array($payload) ? trim((string) ($payload['status'] ?? '')) : '';

        if ($newStatus === '') {
            $this->kirim(422, false, 'Field status wajib diisi.');
            return;
        }
        if (!array_key_exists($newStatus, PemesananModel::STATUS_LABEL)) {
            $this->kirim(422, false, 'Nilai status tidak valid.');
            return;
        }

        try {
            // ubahStatus() mengembalikan: 'ok' | 'tidak_ditemukan' | 'transisi_tidak_valid'
            $hasil = PemesananModel::ubahStatus($id, $newStatus, $staf['id']);
        } catch (Throwable $e) {
            error_log('[QuickBrew API KasirPemesanan update] ' . $e->getMessage());
            $this->kirim(500, false, 'Terjadi kesalahan pada server.');
            return;
        }

        switch ($hasil) {
            case 'ok':
                $this->kirim(200, true, 'Status pesanan berhasil diperbarui.', ['id' => $id, 'status' => $newStatus]);
                break;
            case 'tidak_ditemukan':
                $this->kirim(404, false, 'Pesanan tidak ditemukan.');
                break;
            case 'transisi_tidak_valid':
                $this->kirim(409, false, 'Perubahan status tidak diizinkan dari status saat ini.');
                break;
            default:
                $this->kirim(500, false, 'Terjadi kesalahan pada server.');
        }
    }
}