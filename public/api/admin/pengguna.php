<?php

require_once __DIR__ . '/../../../config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Models/PenggunaModel.php';
require_once APP_ROOT . '/app/Controllers/Admin/PenggunaController.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$controller = new PenggunaController();

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if ($action === 'show') {
            $controller->show();
        } else {
            $controller->index();
        }
        break;

    case 'POST':
        $controller->store();
        break;

    case 'PUT':
        if ($action !== 'update') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Gunakan action=update.',
                'data' => null
            ]);
            break;
        }
        $controller->update();
        break;

    case 'DELETE':
        if ($action !== 'delete') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Gunakan action=delete.',
                'data' => null
            ]);
            break;
        }
        $controller->destroy();
        break;

    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method tidak diizinkan.',
            'data' => null
        ]);
        break;
}
