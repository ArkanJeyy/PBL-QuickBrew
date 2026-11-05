<?php
/**
 * Halaman uji lokal pratinjau SVG QR Code (Meja V4 + Sampel QRIS 150-500 karakter).
 * Jalankan via browser (Laragon: http://localhost/PBL/PBL-QuickBrew/tests/qr_preview.php)
 * atau `php -S localhost:8089 tests/qr_preview.php` untuk memindai langsung dengan kamera HP.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Helpers/QrSvg.php';

/** Hitung CRC-16/CCITT-FALSE (tag 6304 EMVCo QRIS). */
function preview_crc16(string $payload): string
{
    $crc = 0xFFFF;
    for ($i = 0, $n = strlen($payload); $i < $n; $i++) {
        $crc ^= (ord($payload[$i]) << 8);
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function preview_qris(string $orderId, int $amount, string $extraRef = ''): string
{
    $tlv   = static fn (string $t, string $v): string => $t . str_pad((string) strlen($v), 2, '0', STR_PAD_LEFT) . $v;
    $mai26 = $tlv('00', 'COM.GO-JEK.WWW') . $tlv('01', '9360091431234567890') . $tlv('02', 'G123456789') . $tlv('03', 'UMI');
    $mai51 = $tlv('00', 'ID.CO.QRIS.WWW') . $tlv('02', 'ID1026000012345') . $tlv('03', 'UMI');
    $tag62 = $tlv('01', $orderId) . ($extraRef !== '' ? $tlv('08', $extraRef) : '');
    $raw   = $tlv('00', '01')
           . $tlv('01', '12')
           . $tlv('26', $mai26)
           . $tlv('51', $mai51)
           . $tlv('52', '5814')
           . $tlv('53', '360')
           . $tlv('54', (string) $amount)
           . $tlv('58', 'ID')
           . $tlv('59', 'QUICKBREW COFFEE')
           . $tlv('60', 'MALANG')
           . $tlv('61', '65141')
           . $tlv('62', $tag62)
           . '6304';
    return $raw . preview_crc16($raw);
}

$samples = [
    'QR Meja 01 (Versi 4 — Regresi)' => 'http://localhost/PBL/PBL-QuickBrew/public/?page=menu&meja=MEJA01',
    'Sampel QRIS Pendek (~210 karakter)' => preview_qris('TRX-12-1730800012', 26400),
    'Sampel QRIS Standar Midtrans (~270 karakter)' => preview_qris('TRX-45-1730800045', 58300, 'SANDBOX-MIDTRANS-CORE-API-QRIS-GOPAY-SAMPLE-000123456789'),
    'Sampel QRIS Panjang (~360 karakter)' => preview_qris('TRX-99-1730800099', 125400, str_repeat('QUICKBREW-QRIS-LONG-REF-', 6)),
    'Sampel Payload Panjang (~480 karakter)' => '000201010212' . str_repeat('26400014COM.MIDTRANS.WWW0118936009143000012345', 9) . '5802ID5916QUICKBREW COFFEE6006MALANG6304ABCD',
];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pratinjau Uji QrSvg — QuickBrew</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 24px; background: #f8f5f2; color: #2e1f1a; }
    h1 { margin-bottom: 6px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
    .card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .qr-box { width: 220px; height: 220px; margin: 12px auto; }
    .qr-box svg { width: 100%; height: 100%; display: block; }
    code { display: block; font-size: 11px; word-break: break-all; background: #f3ede8; padding: 8px; border-radius: 6px; margin-top: 8px; }
    .meta { font-size: 13px; color: #6b574e; }
  </style>
</head>
<body>
  <h1>Pratinjau Uji QR SVG (Versi 1–17, Level L)</h1>
  <p class="meta">Pindai setiap QR di bawah memakai aplikasi kamera / pembaca QR ponsel untuk memastikan keterbacaan fisik.</p>
  <div class="grid">
<?php foreach ($samples as $title => $payload): ?>
    <section class="card">
      <strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></strong>
      <p class="meta">Panjang: <?= strlen($payload) ?> karakter</p>
      <div class="qr-box"><?= QrSvg::svg($payload, 2, '#000000', '#ffffff') ?></div>
      <code><?= htmlspecialchars($payload, ENT_QUOTES, 'UTF-8') ?></code>
    </section>
<?php endforeach; ?>
  </div>
</body>
</html>
