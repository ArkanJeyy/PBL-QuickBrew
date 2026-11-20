<?php
require_once __DIR__ . '/../../config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';   // <‑‑ pastikan ada
require_once APP_ROOT . '/app/Models/MenuModel.php';
require_once APP_ROOT . '/app/Controllers/Admin/MenuController.php';

// Allow cross‑origin requests (optional, untuk testing via Postman/JS)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST,PUT,PATCH,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$controller = new AdminMenuController();

$action = $_GET['action'] ?? null;
switch ($action) {
    case 'show':
        $controller->show();
        break;
    case 'update':
        $controller->update();
        break;
    case 'delete':
        $controller->destroy();
        break;
    case 'store':
        $controller->store();
        break;
    default:
        $controller->index();
}
?>