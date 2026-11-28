<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Helpers/InvoiceTemplate.php';

function kirim_invoice_email(array $pesanan): bool
{
    $emailConfig = require APP_ROOT . '/config/email.php';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $emailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['username'];
        $mail->Password = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $emailConfig['port'];

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $emailConfig['from_email'],
            $emailConfig['from_name']
        );

        $mail->addAddress(
            $pesanan['email_pelanggan'],
            $pesanan['nama_pelanggan']
        );

        $mail->isHTML(true);
        $mail->Subject = 'Invoice Pemesanan Quick Brew';

        $mail->Body = buat_template_invoice($pesanan);

        $mail->AltBody = 'Invoice pemesanan Quick Brew. '
            . 'Nomor pesanan: ' . $pesanan['nomor']
            . '. Total pembayaran: Rp '
            . number_format($pesanan['total_harga'], 0, ',', '.')
            . '. Status pembayaran: Lunas.';

        $mail->send();

        return true;
    } catch (Exception $e) {
        error_log('Gagal mengirim invoice: ' . $mail->ErrorInfo);

        return false;
    }
}