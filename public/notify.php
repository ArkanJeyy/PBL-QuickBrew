<?php
/**
 * Endpoint resmi tunggal untuk HTTP Notification (Webhook) Midtrans.
 * Notification URL: https://<domain>/<path-proyek>/public/notify.php
 */
require_once __DIR__ . '/../config/config.php';
require_once APP_ROOT . '/app/Services/PembayaranService.php';
require_once APP_ROOT . '/app/Controllers/WebhookController.php';

(new WebhookController())->notify();
