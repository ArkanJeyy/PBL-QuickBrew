<?php

require_once __DIR__ . '/../../config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Models/PenggunaModel.php';
require_once APP_ROOT . '/app/Controllers/AuthController.php';

$controller = new AuthController();

$action = $_GET['action'] ?? 'login';

if ($action === 'logout') {
    $controller->logout();
} elseif ($action === 'me') {
    $controller->me();
} else {
    $controller->login();
}
