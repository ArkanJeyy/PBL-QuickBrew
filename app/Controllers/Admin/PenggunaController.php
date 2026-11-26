<?php

final class PenggunaController
{
    private function json(int $status, string $message, $data = null): void
    {
        http_response_code($status);
        echo json_encode([
            'success' => $status >= 200 && $status < 300,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function input(): array
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        return is_array($input) ? $input : [];
    }

    private function validasiRole($role): bool
    {
        return in_array($role, ['admin', 'kasir'], true);
    }

    public function index(): void
    {
        if (!wajib_admin_api()) {
            return;
        }

        try {
            $data = PenggunaModel::semua();
            $this->json(200, 'Daftar pengguna berhasil diambil.', $data);
        } catch (Throwable $e) {
            error_log('[API Pengguna index] ' . $e->getMessage());
            $this->json(500, 'Terjadi kesalahan pada server.');
        }
    }

    public function show(): void
    {
        if (!wajib_admin_api()) {
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->json(400, 'Parameter id tidak valid.');
            return;
        }

        try {
            $data = PenggunaModel::cariId($id);

            if (!$data) {
                $this->json(404, 'Pengguna tidak ditemukan.');
                return;
            }

            $this->json(200, 'Detail pengguna berhasil diambil.', $data);
        } catch (Throwable $e) {
            error_log('[API Pengguna show] ' . $e->getMessage());
            $this->json(500, 'Terjadi kesalahan pada server.');
        }
    }

    public function store(): void
    {
        if (!wajib_admin_api()) {
            return;
        }

        $input = $this->input();

        $nama = trim((string) ($input['nama'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $role = $input['role'] ?? '';

        if ($nama === '' || $username === '' || $password === '' || $role === '') {
            $this->json(422, 'Nama, username, password, dan role wajib diisi.');
            return;
        }

        if (text_length($nama) > 100 || text_length($nama) < 2) {
            $this->json(422, 'Nama harus terdiri dari 2 sampai 100 karakter.');
            return;
        }

        if (strlen($username) > 50 || strlen($username) < 3) {
            $this->json(422, 'Username harus terdiri dari 3 sampai 50 karakter.');
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            $this->json(422, 'Username hanya boleh berisi huruf, angka, titik, underscore, atau tanda hubung.');
            return;
        }

        if (strlen($password) < 8) {
            $this->json(422, 'Password minimal 8 karakter.');
            return;
        }

        if (!$this->validasiRole($role)) {
            $this->json(422, 'Role harus admin atau kasir.');
            return;
        }

        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $id = PenggunaModel::tambah(
                $nama,
                $username,
                $hash,
                $role
            );

            $this->json(201, 'Pengguna berhasil dibuat.', ['id' => $id]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $this->json(409, 'Username sudah digunakan.');
                return;
            }

            error_log('[API Pengguna store] ' . $e->getMessage());
            $this->json(500, 'Terjadi kesalahan pada server.');
        } catch (Throwable $e) {
            error_log('[API Pengguna store] ' . $e->getMessage());
            $this->json(500, 'Terjadi kesalahan pada server.');
        }
    }

    public function update(): void
    {
        if (!wajib_admin_api()) {
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->json(400, 'Parameter id tidak valid.');
            return;
        }

        $input = $this->input();

        if (!$input) {
            $this->json(422, 'Data untuk update wajib diisi.');
            return;
        }

        $pengguna = PenggunaModel::cariId($id);

        if (!$pengguna) {
            $this->json(404, 'Pengguna tidak ditemukan.');
            return;
        }

        $nama = trim((string) ($input['nama'] ?? $pengguna['nama']));
        $username = trim((string) ($input['username'] ?? $pengguna['username']));
        $role = $input['role'] ?? $pengguna['role'];
        $password = $input['password'] ?? null;

        if ($nama === '' || $username === '') {
            $this->json(422, 'Nama dan username tidak boleh kosong.');
            return;
        }

        if (text_length($nama) < 2 || text_length($nama) > 100) {
            $this->json(422, 'Nama harus terdiri dari 2 sampai 100 karakter.');
            return;
        }

        if (strlen($username) < 3 || strlen($username) > 50) {
            $this->json(422, 'Username harus terdiri dari 3 sampai 50 karakter.');
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            $this->json(422, 'Format username tidak valid.');
            return;
        }

        if (!$this->validasiRole($role)) {
            $this->json(422, 'Role harus admin atau kasir.');
            return;
        }

        if ($password !== null && $password !== '') {
            if (strlen((string) $password) < 8) {
                $this->json(422, 'Password minimal 8 karakter.');
                return;
            }

            $passwordHash = password_hash((string) $password, PASSWORD_DEFAULT);
        } else {
            $passwordHash = null;
        }

        // Admin tidak dapat mengubah role dirinya sendiri menjadi kasir.
        $adminAktif = $_SESSION['staf'] ?? [];

        if (
            (int) ($adminAktif['id'] ?? 0) === $id &&
            $role !== 'admin'
        ) {
            $this->json(403, 'Admin tidak dapat mengubah role dirinya sendiri.');
            return;
        }

        try {
            PenggunaModel::ubah(
                $id,
                $nama,
                $username,
                $role,
                $passwordHash
            );

            $this->json(200, 'Pengguna berhasil diperbarui.', ['id' => $id]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $this->json(409, 'Username sudah digunakan.');
                return;
            }

            error_log('[API Pengguna update] ' . $e->getMessage());
            $this->json(500, 'Terjadi kesalahan pada server.');
        } catch (Throwable $e) {
            error_log('[API Pengguna update] ' . $e->getMessage());
            $this->json(500, 'Terjadi kesalahan pada server.');
        }
    }

    public function destroy(): void
    {
        if (!wajib_admin_api()) {
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->json(400, 'Parameter id tidak valid.');
            return;
        }

        $adminAktif = $_SESSION['staf'] ?? [];

        if ((int) ($adminAktif['id'] ?? 0) === $id) {
            $this->json(403, 'Admin tidak dapat menghapus akun dirinya sendiri.');
            return;
        }

        $pengguna = PenggunaModel::cariId($id);

        if (!$pengguna) {
            $this->json(404, 'Pengguna tidak ditemukan.');
            return;
        }

        try {
            // Jangan sampai sistem kehilangan seluruh akun admin.
            if ($pengguna['role'] === 'admin') {
                $stmt = db()->query(
                    "SELECT COUNT(*) FROM pengguna WHERE role = 'admin'"
                );

                if ((int) $stmt->fetchColumn() <= 1) {
                    $this->json(403, 'Admin terakhir tidak dapat dihapus.');
                    return;
                }
            }

            PenggunaModel::hapus($id);

            $this->json(200, 'Pengguna berhasil dihapus.', ['id' => $id]);
        } catch (Throwable $e) {
            error_log('[API Pengguna destroy] ' . $e->getMessage());
            $this->json(500, 'Terjadi kesalahan pada server.');
        }
    }
}
