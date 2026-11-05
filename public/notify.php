<?php
/**
 * Endpoint langsung untuk HTTP Notification (Webhook) Midtrans.
 * URL: http(s)://<domain>/public/notify.php  (atau /public/?page=webhook)
 */
require_once __DIR__ . '/../config/config.php';
require_once APP_ROOT . '/app/Controllers/WebhookController.php';

(new WebhookController())->notify();

