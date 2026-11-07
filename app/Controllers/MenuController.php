<?php

final class MenuController
{
    /**
     * GET /api/menu
     * Mengambil seluruh menu aktif dalam format JSON.
     */
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Pastikan endpoint hanya menerima GET.
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);

            echo json_encode([
                'success' => false,
                'message' => 'Method tidak diizinkan.',
                'data' => null,
            ]);

            return;
        }

        try {
            if (isset($_GET['id'])) {
                $id = (int) $_GET['id'];
                $menus = MenuModel::findByIds([$id]);
            } elseif (isset($_GET['category'])) {
                $categorySlug = slugify($_GET['category']);
                $allMenus = MenuModel::allForApi();
                $menus = array_values(array_filter($allMenus, fn($m) => $m['category'] === $categorySlug));
            } else {
                $menus = MenuModel::allForApi();
            }

            http_response_code(200);

            echo json_encode([
                'success' => true,
                'message' => 'Data menu berhasil diambil.',
                'data' => $menus,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (Throwable $e) {
            error_log('[QuickBrew API Menu] ' . $e->getMessage());

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'debug' => $e->getMessage(),
                'data' => null,
            ]);
        }
    }
}