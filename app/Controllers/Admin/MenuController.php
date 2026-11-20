<?php

/**
 * Admin MenuController - CRUD API for menu management (admin only).
 *
 * Endpoints (access via public/api/admin_menu.php):
 *   GET    /api/admin_menu                     -> index()   (list all menus)
 *   GET    /api/admin_menu?action=show&id=ID   -> show()    (single menu detail)
 *   POST   /api/admin_menu                     -> store()   (create new menu)
 *   PUT    /api/admin_menu?action=update&id=ID -> update() (update existing menu)
 *   DELETE /api/admin_menu?action=delete&id=ID -> destroy() (delete menu)
 *
 * All actions require admin role. Uses wajib_admin_api() guard defined in helpers.php.
 */
final class AdminMenuController
{
    /** List all menus (admin view). */
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        // Ensure admin access
        if (!wajib_admin_api()) {
            return; // guard already sent response
        }

        try {
            $menus = MenuModel::all();
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Daftar menu berhasil diambil.',
                'data'    => $menus,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            error_log('[QuickBrew API AdminMenu index] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'debug'   => $e->getMessage(),
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Show single menu detail. */
    public function show(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!wajib_admin_api()) {
            return;
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        if ($id === null || $id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parameter id tidak valid.',
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $menu = MenuModel::findByIds([$id]);
            $menu = $menu[$id] ?? null;
            if ($menu === null) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Menu tidak ditemukan.',
                    'data'    => null,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Detail menu berhasil diambil.',
                'data'    => $menu,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[QuickBrew API AdminMenu show] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'debug'   => $e->getMessage(),
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Create new menu entry. */
    public function store(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!wajib_admin_api()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method tidak diizinkan, gunakan POST.',
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Parse JSON body
        $input = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $required = ['name', 'price', 'category'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => "Field '{$field}' wajib diisi.",
                    'data'    => null,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        $name        = trim((string) $input['name']);
        $price       = (int) $input['price'];
        $category    = trim((string) $input['category']); // slug or name
        $description = trim((string) ($input['description'] ?? ''));
        $image       = trim((string) ($input['image'] ?? ''));
        $stock       = isset($input['stock']) ? (int) $input['stock'] : 0;
        $active      = $input['active'] ?? true;

        try {
            // Resolve category id using MenuModel::categories() (slug => name)
            $categoryMap = MenuModel::categories(); // slug => name
            $catSlug = slugify($category);
            $categoryId = null;
            foreach ($categoryMap as $slug => $name) {
                if ($slug === $catSlug) {
                    // Find id by name
                    $stmt = db()->prepare('SELECT id FROM kategori WHERE nama_kategori = ?');
                    $stmt->execute([$name]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $categoryId = (int) $row['id'];
                    }
                    break;
                }
            }
            if ($categoryId === null) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Kategori '{$category}' tidak ditemukan.",
                    'data'    => null,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $stmt = db()->prepare(
                'INSERT INTO menu (kategori_id, nama_menu, harga, gambar, deskripsi, stok, aktif) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id'
            );
            $stmt->execute([
                $categoryId,
                $name,
                $price,
                $image,
                $description,
                $stock,
                $active ? 'TRUE' : 'FALSE',
            ]);
            $newId = (int) $stmt->fetchColumn();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Menu berhasil dibuat.',
                'data'    => ['id' => $newId],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[QuickBrew API AdminMenu store] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'debug'   => $e->getMessage(),
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Update existing menu. */
    public function update(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!wajib_admin_api()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method tidak diizinkan, gunakan PUT/PATCH.',
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        if ($id === null || $id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parameter id tidak valid.',
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $input = json_decode((string) file_get_contents('php://input'), true) ?? [];
        if (empty($input)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Tidak ada data untuk diupdate.',
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Build dynamic SET clause
        $fields = [];
        $params = [];
        $allowed = ['name' => 'nama_menu', 'price' => 'harga', 'category' => 'kategori_id', 'description' => 'deskripsi', 'image' => 'gambar', 'stock' => 'stok', 'active' => 'aktif'];
        foreach ($allowed as $key => $col) {
            if (array_key_exists($key, $input)) {
                if ($key === 'category') {
                    // Resolve category id lewat PHP (bukan di dalam SQL)
                    $categoryMap = MenuModel::categories();
                    $catSlug     = slugify((string) $input[$key]);
                    $categoryId  = null;

                    foreach ($categoryMap as $slug => $catName) {
                        if ($slug === $catSlug) {
                            $stmtCat = db()->prepare('SELECT id FROM kategori WHERE nama_kategori = ?');
                            $stmtCat->execute([$catName]);
                            $catRow = $stmtCat->fetch();
                            if ($catRow) {
                                $categoryId = (int) $catRow['id'];
                            }
                            break;
                        }
                    }

                    if ($categoryId === null) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => "Kategori '{$catSlug}' tidak ditemukan.",
                            'data'    => null,
                        ], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                    $value = $categoryId;
                } elseif ($key === 'price' || $key === 'stock') {
                    $value = (int) $input[$key];
                } elseif ($key === 'active') {
                    $value = $input[$key] ? 'TRUE' : 'FALSE';
                } else {
                    $value = trim((string) $input[$key]);
                }
                $fields[] = "$col = ?";
                $params[] = $value;
            }
        }
        if (empty($fields)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Tidak ada field yang dapat diupdate.',
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        $params[] = $id; // WHERE id = ?

        $sql = 'UPDATE menu SET ' . implode(', ', $fields) . ' WHERE id = ?';
        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Menu berhasil diupdate.',
                'data'    => ['id' => $id],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[QuickBrew API AdminMenu update] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'debug'   => $e->getMessage(),
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /** Delete a menu entry. */
    public function destroy(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!wajib_admin_api()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method tidak diizinkan, gunakan DELETE.',
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        if ($id === null || $id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parameter id tidak valid.',
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $stmt = db()->prepare('DELETE FROM menu WHERE id = ?');
            $stmt->execute([$id]);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Menu berhasil dihapus.',
                'data'    => ['id' => $id],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[QuickBrew API AdminMenu destroy] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'debug'   => $e->getMessage(),
                'data'    => null,
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
