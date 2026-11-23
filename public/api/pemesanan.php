<?php

require_once __DIR__ . '/../../config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Models/MenuModel.php';
require_once APP_ROOT . '/app/Models/MejaModel.php';
require_once APP_ROOT . '/app/Models/PemesananModel.php';
require_once APP_ROOT . '/app/Controllers/Pelanggan/PemesananController.php';

$controller = new PemesananController();

// Routing
$action = $_GET['action'] ?? null;

// Hanya izinkan:
//   - POST → store()
//   - GET dengan action=show → show()
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->store();
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'show') {
    $controller->show();
} else {
    // Semua method lain → 405 Method Not Allowed
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan.',
        'data'    => null,
    ], JSON_UNESCAPED_UNICODE);
}
?>