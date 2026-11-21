<?php
require_once __DIR__ . '/../../../config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';          // memuat wajib_kasir_api()
require_once APP_ROOT . '/app/Models/MenuModel.php'; 
require_once APP_ROOT . '/app/Models/PemesananModel.php';
require_once APP_ROOT . '/app/Controllers/Kasir/KasirPemesananController.php';

$controller = new KasirPemesananController();

$action = $_GET['action'] ?? null;

// Routing
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === null) {
    // Daftar semua pesanan
    $controller->index();
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'show') {
    $controller->show();
} elseif (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH']) && $action === 'update') {
    $controller->updateStatus();
} else {
    // Semua lainnya → 405
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan.',
        'data'    => null,
    ], JSON_UNESCAPED_UNICODE);
}
?>