<?php

final class ApiAuthController
{
    /**
     * POST /api/auth.php?action=login
     * Login staf (kasir / admin).
     */
    public function login(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method tidak diizinkan. Gunakan POST.',
                'data'    => null,
            ]);
            return;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $input = $_POST;
        }

        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($username === '' || $password === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Username dan password wajib diisi.',
                'data'    => null,
            ]);
            return;
        }

        try {
            // Menggunakan method bawaan PenggunaModel.php
            $pengguna = PenggunaModel::cariUsername($username);

            if (!$pengguna || !password_verify($password, $pengguna['password'])) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Username atau password salah.',
                    'data'    => null,
                ]);
                return;
            }

            // Jika algoritma hash butuh rehash otomatis
            if (password_needs_rehash($pengguna['password'], PASSWORD_DEFAULT)) {
                $hashBaru = password_hash($password, PASSWORD_DEFAULT);
                PenggunaModel::perbaruiPassword((int) $pengguna['id'], $hashBaru);
            }

            // Simpan sesi staf
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);

            $_SESSION['staf'] = [
                'id'       => (int) $pengguna['id'],
                'nama'     => (string) $pengguna['nama'],
                'username' => (string) $pengguna['username'],
                'role'     => (string) $pengguna['role'],
            ];

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil.',
                'data'    => [
                    'id'       => (int) $pengguna['id'],
                    'nama'     => (string) $pengguna['nama'],
                    'username' => (string) $pengguna['username'],
                    'role'     => (string) $pengguna['role'],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (Throwable $e) {
            error_log('[QuickBrew API Auth] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'debug'   => $e->getMessage(),
                'data'    => null,
            ]);
        }
    }

    /**
     * POST /api/auth.php?action=logout
     * Logout staf dan hancurkan sesi.
     */
    public function logout(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method tidak diizinkan. Gunakan POST.',
                'data'    => null,
            ]);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION['staf']);
        session_destroy();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Logout berhasil. Sesi telah dihapus.',
            'data'    => null,
        ]);
    }

    /**
     * GET /api/auth.php?action=me
     * Cek staf yang sedang login saat ini.
     */
    public function me(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $staf = $_SESSION['staf'] ?? null;

        if (!$staf) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Belum login atau sesi telah berakhir.',
                'data'    => null,
            ]);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Sesi aktif.',
            'data'    => $staf,
        ]);
    }
}