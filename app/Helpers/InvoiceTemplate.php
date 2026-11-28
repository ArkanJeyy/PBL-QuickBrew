<?php

function buat_template_invoice(array $pesanan): string
{
    $nama = htmlspecialchars(
        $pesanan['nama_pelanggan'],
        ENT_QUOTES,
        'UTF-8'
    );

    $nomor = htmlspecialchars(
        $pesanan['nomor'],
        ENT_QUOTES,
        'UTF-8'
    );

    $meja = htmlspecialchars(
        (string) $pesanan['no_meja'],
        ENT_QUOTES,
        'UTF-8'
    );

    $itemsHtml = '';

    foreach ($pesanan['items'] as $item) {
        $namaMenu = htmlspecialchars(
            $item['nama_menu'],
            ENT_QUOTES,
            'UTF-8'
        );

        $jumlah = (int) $item['jumlah'];

        $harga = number_format(
            $item['harga'],
            0,
            ',',
            '.'
        );

        $subtotal = number_format(
            $item['subtotal'],
            0,
            ',',
            '.'
        );

        $catatan = htmlspecialchars(
            $item['catatan'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );

        $itemsHtml .= "
            <tr>
                <td style='padding:12px;border-bottom:1px solid #eee'>
                    <strong>{$namaMenu}</strong>
                    <br>
                    <small>{$catatan}</small>
                </td>
                <td style='padding:12px;text-align:center;border-bottom:1px solid #eee'>
                    {$jumlah}
                </td>
                <td style='padding:12px;text-align:right;border-bottom:1px solid #eee'>
                    Rp {$harga}
                </td>
                <td style='padding:12px;text-align:right;border-bottom:1px solid #eee'>
                    Rp {$subtotal}
                </td>
            </tr>
        ";
    }

    $subtotal = number_format(
        $pesanan['subtotal'],
        0,
        ',',
        '.'
    );

    $pajak = number_format(
        $pesanan['pajak'],
        0,
        ',',
        '.'
    );

    $total = number_format(
        $pesanan['total_harga'],
        0,
        ',',
        '.'
    );

    return "
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
    </head>
    <body style='margin:0;background:#f5f5f5;font-family:Arial,sans-serif'>
        <div style='max-width:650px;margin:30px auto;background:white;border-radius:10px;overflow:hidden'>

            <div style='background:#ffd91a;padding:25px;text-align:center'>
                <h1 style='margin:0;color:#222'>Quick Brew</h1>
                <p style='margin:8px 0 0'>Invoice Pemesanan</p>
            </div>

            <div style='padding:25px'>
                <h2>Terima kasih, {$nama}!</h2>

                <p>
                    Pembayaran pesanan Anda telah berhasil.
                    Berikut rincian transaksi Anda.
                </p>

                <div style='background:#f9f9f9;padding:15px;border-radius:8px'>
                    <p><strong>Nomor Pesanan:</strong> {$nomor}</p>
                    <p><strong>Nomor Meja:</strong> {$meja}</p>
                    <p><strong>Status Pembayaran:</strong>
                        <span style='color:green'>LUNAS</span>
                    </p>
                </div>

                <h3>Rincian Pesanan</h3>

                <table style='width:100%;border-collapse:collapse'>
                    <thead>
                        <tr style='background:#f5f5f5'>
                            <th style='padding:12px;text-align:left'>Menu</th>
                            <th style='padding:12px'>Qty</th>
                            <th style='padding:12px;text-align:right'>Harga</th>
                            <th style='padding:12px;text-align:right'>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                </table>

                <div style='margin-top:20px;text-align:right'>
                    <p>Subtotal: Rp {$subtotal}</p>
                    <p>Pajak: Rp {$pajak}</p>

                    <h2 style='color:#222'>
                        Total: Rp {$total}
                    </h2>
                </div>

                <div style='margin-top:25px;padding:15px;background:#fff9df;border-radius:8px'>
                    <p style='margin:0'>
                        Simpan email ini sebagai bukti pembayaran
                        pesanan Anda.
                    </p>
                </div>

                <p style='margin-top:30px'>
                    Terima kasih telah memesan di Quick Brew!
                </p>
            </div>

            <div style='background:#eee;padding:15px;text-align:center'>
                <small>Quick Brew — Scan, Order, Enjoy.</small>
            </div>
        </div>
    </body>
    </html>
    ";
}