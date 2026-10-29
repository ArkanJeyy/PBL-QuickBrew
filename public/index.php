<?php
/**
 * Front controller QuickBrew.
 * Akses: http://localhost/PBL/PBL-QuickBrew/public/?page=menu
 * QR meja: http://localhost/PBL/PBL-QuickBrew/public/?meja=MEJA01
 */
require_once __DIR__ . '/../config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Models/MenuModel.php';
require_once APP_ROOT . '/app/Models/MejaModel.php';
require_once APP_ROOT . '/app/Controllers/PelangganController.php';

// page => [Controller, method]
$routes = [
    'home' => [PelangganController::class, 'home'],
    'menu' => [PelangganController::class, 'menu'],
    'cart' => [PelangganController::class, 'cart'],
    'qr'   => [PelangganController::class, 'qr'],
];

$page = (string) ($_GET['page'] ?? 'home');

if (!isset($routes[$page])) {
    http_response_code(404);
    echo '<h1>404</h1><p>Halaman tidak ditemukan. <a href="' . e(url()) . '">Kembali ke beranda</a></p>';
    exit;
}

try {
    ob_start(); // buang HTML setengah jadi bila query gagal di tengah render
    [$class, $method] = $routes[$page];
    (new $class())->$method();
    ob_end_flush();
} catch (PDOException $ex) {
    ob_end_clean();
    http_response_code(500);
    error_log('[QuickBrew] ' . $ex->getMessage());
    echo '<h1>Database tidak dapat diakses</h1><p>Periksa konfigurasi di <code>config/config.php</code> '
       . 'dan pastikan skema <code>SQL/QuickBrew.sql</code> sudah dijalankan.</p>';
}
