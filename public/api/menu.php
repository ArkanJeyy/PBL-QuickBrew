<?php

require_once __DIR__ . '/../../config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Models/MenuModel.php';
require_once APP_ROOT . '/app/Controllers/MenuController.php';

$controller = new MenuController();
$controller->index();