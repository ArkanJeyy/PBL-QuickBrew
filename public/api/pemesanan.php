<?php

require_once __DIR__ . '/../../config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Models/MenuModel.php';
require_once APP_ROOT . '/app/Models/MejaModel.php';
require_once APP_ROOT . '/app/Models/PemesananModel.php';
require_once APP_ROOT . '/app/Controllers/PemesananController.php';

$controller = new PemesananController();

// Routing berdasarkan sub-action dari query string ?action=...
// GET  /api/pemesanan              -> index()  (list semua pesanan, untuk kasir)
// GET  /api/pemesanan?action=show  -> show()   (detail satu pesanan by ?id=)
// POST /api/pemesanan              -> store()  (buat pesanan baru)
$action = $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'show') {
    $controller->show();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->store();
} else {
    $controller->index();
}