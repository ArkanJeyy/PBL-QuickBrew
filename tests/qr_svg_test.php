<?php
/**
 * Pengujian otomatis app/Helpers/QrSvg.php:
 *   1. Uji regresi byte-per-byte terhadap hash SHA-256 keluaran asli V1-V4 (sebelum perubahan).
 *   2. Verifikasi struktur matriks ISO/IEC 18004 (Finder, Timing, Alignment, Format Info BCH,
 *      Version Info Golay Table D.1, jumlah modul data + remainder bits) untuk V1 s.d. V17.
 *   3. Decode balik dari SVG (<path d="M...">) -> unmask -> de-interleave multi-blok ->
 *      cek seluruh sindrom Reed-Solomon GF(256) == 0 -> decode bitstream Byte Mode ->
 *      pastikan string hasil decode identik byte-per-byte untuk sampel QRIS 150-500 karakter.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Helpers/QrSvg.php';

$passed = 0;
$failed = 0;

function assert_qr(bool $cond, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "[PASS] {$label}\n";
    } else {
        $failed++;
        echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

/** Hitung CRC-16/CCITT-FALSE (standar tag 6304 EMVCo QRIS). */
function qris_crc16(string $payload): string
{
    $crc = 0xFFFF;
    $len = strlen($payload);
    for ($i = 0; $i < $len; $i++) {
        $crc ^= (ord($payload[$i]) << 8);
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/** Bentuk sampel string berformat EMVCo QRIS dengan panjang target tertentu (150-500 karakter). */
function build_sample_qris(int $targetLen, int $amount = 26400, string $orderRef = 'TRX-12-1730800000'): string
{
    $tlv = static fn (string $tag, string $val): string => $tag . str_pad((string) strlen($val), 2, '0', STR_PAD_LEFT) . $val;

    $mai26 = $tlv('00', 'COM.GO-JEK.WWW')
           . $tlv('01', '9360091431234567890')
           . $tlv('02', 'G123456789')
           . $tlv('03', 'UMI');
    $mai51 = $tlv('00', 'ID.CO.QRIS.WWW')
           . $tlv('02', 'ID1026000012345')
           . $tlv('03', 'UMI');

    $base = $tlv('00', '01')
          . $tlv('01', '12')
          . $tlv('26', $mai26)
          . $tlv('51', $mai51)
          . $tlv('52', '5814')
          . $tlv('53', '360')
          . $tlv('54', (string) $amount)
          . $tlv('58', 'ID')
          . $tlv('59', 'QUICKBREW COFFEE')
          . $tlv('60', 'MALANG')
          . $tlv('61', '65141');

    // Tag 62 (Additional Data Field) diisi referensi & filler sampai tepat mencapai $targetLen
    $sub01    = $tlv('01', $orderRef);
    // Overhead tersisa: tag "62" (2) + len62 (2) + $sub01 + sub08 header ("08" + 2 digit len) + "6304" (4) + CRC (4) = 16 + strlen($sub01)
    $overhead = strlen($base) + 4 + strlen($sub01) + 4 + 8;
    $padLen   = max(4, $targetLen - $overhead);

    // Jika > 99 karakter, pecah ke beberapa sub-tag EMVCo (maks 99 per sub-tag)
    $extra = $sub01;
    $rem   = $targetLen - (strlen($base) + 4 + strlen($sub01) + 8);
    $tagNo = 2;
    while ($rem >= 5 && $tagNo <= 9) {
        $valLen = min(95, $rem - 4);
        if ($rem - 4 - $valLen > 0 && $rem - 4 - $valLen < 5) {
            $valLen = max(1, $rem - 9);
        }
        $filler = substr(str_repeat('MIDTRANSQRISQUICKBREW0123456789', 5), 0, $valLen);
        $extra .= $tlv(str_pad((string) $tagNo, 2, '0', STR_PAD_LEFT), $filler);
        $rem    = $targetLen - (strlen($base) + 4 + strlen($extra) + 8);
        $tagNo++;
    }
    if ($rem > 0) {
        // Sesuaikan nama merchant (tag 59) bila sisa 1-4 karakter
        $base = str_replace(
            $tlv('59', 'QUICKBREW COFFEE'),
            $tlv('59', 'QUICKBREW COFFEE' . str_repeat('X', $rem)),
            $base
        );
    }

    $withoutCrc = $base . $tlv('62', $extra) . '6304';
    return $withoutCrc . qris_crc16($withoutCrc);
}

// -------------------------------------------------------------------------
// 1. Uji regresi byte-per-byte V1-V4 (hash SHA-256 direkam sebelum perubahan)
// -------------------------------------------------------------------------
$goldenV1V4 = [
    'v1_short'      => ['MEJA01', '7215493aa2116518642a02d517ab0ddfe7b79872985a3a2c090e19da1e545f3e'],
    'v1_edge_17'    => [str_repeat('A', 17), '9c1357e8310e9999de3a6f28aa7ea9deda5ad5b5a92216c8ffa9e963662a2d2c'],
    'v2_edge_32'    => [str_repeat('B', 32), '4cdc94e1a3a8c7be799ff39ce8ac38766cc72a4fdc8fb143ccc48f91a5533684'],
    'v3_edge_53'    => [str_repeat('C', 53), 'c0854dc85eb74659476e7db719b146ec8267dedd33c4281d692902b0aed4864d'],
    'v4_meja_local' => ['http://localhost/PBL/PBL-QuickBrew/public/?page=menu&meja=MEJA01', 'e157dce2875a075a9b6551b8743717b26d6d9f55e1e396f69b1f3331dac20d9f'],
    'v4_url_77'     => ['https://quickbrew.example.com/PBL/PBL-QuickBrew/public/?page=menu&meja=MEJA05', 'd1a9fe30110d9b8fb05bb61a8f7c37fd3b5926c0adb0905aef00f68a3e081f5a'],
    'v4_edge_78'    => [str_repeat('D', 78), '13b92c40d3e7d7995ce2c000f0000489508023ce677bd18de263c7df0ea11c81'],
];

foreach ($goldenV1V4 as $name => [$input, $expectedHash]) {
    $svg    = QrSvg::svg($input, 2);
    $actual = hash('sha256', $svg);
    assert_qr($actual === $expectedHash, "Regresi V1-V4 byte-per-byte ({$name}, len=" . strlen($input) . ')', "got {$actual}");
}

// -------------------------------------------------------------------------
// 2. Decoder & validator independen dari SVG -> matriks -> RS syndrome -> string
// -------------------------------------------------------------------------
$isoTableL = [
    // ver => [size, totalCw, ecLen, g1Blocks, g1DataCw, g2Blocks, g2DataCw, alignCoords, remBits, verInfoHex]
    1  => [21,  26,  7, 1,  19, 0,   0, [],              0, 0],
    2  => [25,  44, 10, 1,  34, 0,   0, [6, 18],         7, 0],
    3  => [29,  70, 15, 1,  55, 0,   0, [6, 22],         7, 0],
    4  => [33, 100, 20, 1,  80, 0,   0, [6, 26],         7, 0],
    5  => [37, 134, 26, 1, 108, 0,   0, [6, 30],         7, 0],
    6  => [41, 172, 18, 2,  68, 0,   0, [6, 34],         7, 0],
    7  => [45, 196, 20, 2,  78, 0,   0, [6, 22, 38],     0, 0x07C94],
    8  => [49, 242, 24, 2,  97, 0,   0, [6, 24, 42],     0, 0x085BC],
    9  => [53, 292, 30, 2, 116, 0,   0, [6, 26, 46],     0, 0x09A99],
    10 => [57, 346, 18, 2,  68, 2,  69, [6, 28, 50],     0, 0x0A4D3],
    11 => [61, 404, 20, 4,  81, 0,   0, [6, 30, 54],     0, 0x0BBF6],
    12 => [65, 466, 24, 2,  92, 2,  93, [6, 32, 58],     0, 0x0C762],
    13 => [69, 532, 26, 4, 107, 0,   0, [6, 34, 62],     0, 0x0D847],
    14 => [73, 581, 30, 3, 115, 1, 116, [6, 26, 46, 66], 3, 0x0E60D],
    15 => [77, 655, 22, 5,  87, 1,  88, [6, 26, 48, 70], 3, 0x0F928],
    16 => [81, 733, 24, 5,  98, 1,  99, [6, 26, 50, 74], 3, 0x10B78],
    17 => [85, 815, 28, 1, 107, 5, 108, [6, 30, 54, 78], 3, 0x1145D],
];

/** Evaluasi sindrom Reed-Solomon GF(256) (polinom primitif 0x11D): semua sindrom wajib 0. */
function verify_rs_syndromes(array $codewordBlock, int $ecLen): bool
{
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x   = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) $x ^= 0x11D;
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }

    for ($j = 0; $j < $ecLen; $j++) {
        // Evaluasi polinom pesan pada alpha^j menggunakan skema Horner
        $syn = 0;
        foreach ($codewordBlock as $cw) {
            $syn = ($syn === 0 ? 0 : $exp[$log[$syn] + $j]) ^ $cw;
        }
        if ($syn !== 0) {
            return false;
        }
    }
    return true;
}

/** Urai SVG QrSvg::svg() kembali menjadi string asli & verifikasi seluruh aturan ISO/IEC 18004. */
function decode_and_verify_svg(string $svg, int $margin, array $isoTableL): array
{
    if (!preg_match('/viewBox="0 0 (\d+) \1"/', $svg, $vm)) {
        return ['ok' => false, 'reason' => 'viewBox SVG tidak valid'];
    }
    $dim  = (int) $vm[1];
    $size = $dim - 2 * $margin;
    $ver  = intdiv($size - 17, 4);
    if (!isset($isoTableL[$ver]) || $isoTableL[$ver][0] !== $size) {
        return ['ok' => false, 'reason' => "Ukuran matriks tidak dikenal: {$size}"];
    }

    $full = array_fill(0, $dim, array_fill(0, $dim, false));
    if (preg_match('/<path d="([^"]*)"/', $svg, $pm)) {
        preg_match_all('/M(\d+),(\d+)h1v1h-1z/', $pm[1], $pts, PREG_SET_ORDER);
        foreach ($pts as $pt) {
            $full[(int) $pt[2]][(int) $pt[1]] = true;
        }
    }

    // Pastikan quiet zone bersih
    for ($r = 0; $r < $dim; $r++) {
        for ($c = 0; $c < $dim; $c++) {
            if ($r < $margin || $r >= $dim - $margin || $c < $margin || $c >= $dim - $margin) {
                if ($full[$r][$c]) {
                    return ['ok' => false, 'reason' => "Quiet zone kotor pada ({$r},{$c})"];
                }
            }
        }
    }

    $m = array_fill(0, $size, array_fill(0, $size, false));
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            $m[$r][$c] = $full[$r + $margin][$c + $margin];
        }
    }

    [, $totalCw, $ecLen, $g1Blocks, $g1DataCw, $g2Blocks, $g2DataCw, $alignCoords, $remBits, $expectedVerInfo] = $isoTableL[$ver];

    // Bangun peta modul fungsi & verifikasi isinya
    $res = array_fill(0, $size, array_fill(0, $size, false));
    foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$r0, $c0]) {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $r = $r0 + $dr;
                $c = $c0 + $dc;
                if ($r < 0 || $c < 0 || $r >= $size || $c >= $size) continue;
                $in7 = $dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6;
                $exp = $in7 && ($dr === 0 || $dr === 6 || $dc === 0 || $dc === 6 || ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4));
                if ($m[$r][$c] !== $exp) {
                    return ['ok' => false, 'reason' => "Finder/separator salah di ({$r},{$c})"];
                }
                $res[$r][$c] = true;
            }
        }
    }

    for ($i = 8; $i < $size - 8; $i++) {
        $res[6][$i] = $res[$i][6] = true;
    }

    foreach ($alignCoords as $ar) {
        foreach ($alignCoords as $ac) {
            if (($ar === 6 && $ac === 6) || ($ar === 6 && $ac === $size - 7) || ($ar === $size - 7 && $ac === 6)) {
                continue;
            }
            for ($dr = -2; $dr <= 2; $dr++) {
                for ($dc = -2; $dc <= 2; $dc++) {
                    $exp = max(abs($dr), abs($dc)) !== 1;
                    if ($m[$ar + $dr][$ac + $dc] !== $exp) {
                        return ['ok' => false, 'reason' => "Alignment pattern salah di pusat ({$ar},{$ac})"];
                    }
                    $res[$ar + $dr][$ac + $dc] = true;
                }
            }
        }
    }

    // Verifikasi timing pattern di luar alignment
    for ($i = 8; $i < $size - 8; $i++) {
        if ($m[6][$i] !== ($i % 2 === 0) || $m[$i][6] !== ($i % 2 === 0)) {
            return ['ok' => false, 'reason' => "Timing pattern salah pada indeks {$i}"];
        }
    }

    // Verifikasi Version Info (V7+) terhadap ISO/IEC 18004 Annex D Table D.1
    if ($ver >= 7) {
        $v1 = 0;
        $v2 = 0;
        for ($i = 0; $i < 18; $i++) {
            $r1 = $size - 11 + ($i % 3);
            $c1 = intdiv($i, 3);
            $r2 = intdiv($i, 3);
            $c2 = $size - 11 + ($i % 3);
            if ($m[$r1][$c1]) $v1 |= (1 << $i);
            if ($m[$r2][$c2]) $v2 |= (1 << $i);
            $res[$r1][$c1] = $res[$r2][$c2] = true;
        }
        if ($v1 !== $expectedVerInfo || $v2 !== $expectedVerInfo) {
            return ['ok' => false, 'reason' => sprintf('Version info salah: v1=0x%05X v2=0x%05X exp=0x%05X', $v1, $v2, $expectedVerInfo)];
        }
    }

    // Dark module & Format Info
    if (!$m[$size - 8][8]) {
        return ['ok' => false, 'reason' => 'Dark module tidak bernilai gelap'];
    }
    $res[$size - 8][8] = true;
    for ($i = 0; $i < 9; $i++) {
        $res[8][$i] = $res[$i][8] = true;
    }
    for ($i = 0; $i < 8; $i++) {
        $res[8][$size - 1 - $i] = $res[$size - 1 - $i][8] = true;
    }

    $coordsA = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
    $fmtA = 0;
    $fmtB = 0;
    foreach ($coordsA as $i => [$r, $c]) {
        if ($m[$r][$c]) $fmtA |= (1 << (14 - $i));
    }
    for ($i = 0; $i < 7; $i++) {
        if ($m[$size - 1 - $i][8]) $fmtB |= (1 << (14 - $i));
    }
    for ($i = 7; $i < 15; $i++) {
        if ($m[8][$size - 15 + $i]) $fmtB |= (1 << (14 - $i));
    }
    if ($fmtA !== 0x77C4 || $fmtB !== 0x77C4) {
        return ['ok' => false, 'reason' => sprintf('Format info salah: A=0x%04X B=0x%04X', $fmtA, $fmtB)];
    }

    // Baca modul data secara zig-zag & buka Mask 0
    $rawBits = '';
    $up      = true;
    for ($col = $size - 1; $col > 0; $col -= 2) {
        if ($col === 6) $col--;
        for ($step = 0; $step < $size; $step++) {
            $r = $up ? ($size - 1 - $step) : $step;
            for ($dc = 0; $dc < 2; $dc++) {
                $c = $col - $dc;
                if (!$res[$r][$c]) {
                    $bit = $m[$r][$c];
                    if ((($r + $c) & 1) === 0) $bit = !$bit;
                    $rawBits .= $bit ? '1' : '0';
                }
            }
        }
        $up = !$up;
    }

    $expectedBits = $totalCw * 8 + $remBits;
    if (strlen($rawBits) !== $expectedBits) {
        return ['ok' => false, 'reason' => 'Jumlah modul data (' . strlen($rawBits) . ") != {$expectedBits}"];
    }
    if ($remBits > 0 && substr($rawBits, -$remBits) !== str_repeat('0', $remBits)) {
        return ['ok' => false, 'reason' => 'Remainder bits tidak bernilai 0'];
    }

    $interleaved = [];
    for ($i = 0; $i < $totalCw; $i++) {
        $interleaved[] = bindec(substr($rawBits, $i * 8, 8));
    }

    // De-interleave ke blok-blok Group 1 & Group 2
    $numBlocks  = $g1Blocks + $g2Blocks;
    $blockSizes = array_merge(
        array_fill(0, $g1Blocks, $g1DataCw),
        $g2Blocks > 0 ? array_fill(0, $g2Blocks, $g2DataCw) : []
    );
    $dataBlocks = array_fill(0, $numBlocks, []);
    $ecBlocks   = array_fill(0, $numBlocks, []);

    $idx        = 0;
    $maxDataLen = max($blockSizes);
    for ($col = 0; $col < $maxDataLen; $col++) {
        for ($b = 0; $b < $numBlocks; $b++) {
            if ($col < $blockSizes[$b]) {
                $dataBlocks[$b][] = $interleaved[$idx++];
            }
        }
    }
    for ($col = 0; $col < $ecLen; $col++) {
        for ($b = 0; $b < $numBlocks; $b++) {
            $ecBlocks[$b][] = $interleaved[$idx++];
        }
    }

    // Cek sindrom Reed-Solomon tiap blok
    for ($b = 0; $b < $numBlocks; $b++) {
        $fullBlock = array_merge($dataBlocks[$b], $ecBlocks[$b]);
        if (!verify_rs_syndromes($fullBlock, $ecLen)) {
            return ['ok' => false, 'reason' => "Sindrom Reed-Solomon gagal pada blok #{$b} (V{$ver})"];
        }
    }

    // Gabungkan kembali semua data codewords -> decode Byte Mode
    $dataCw = array_merge(...$dataBlocks);
    $dBits  = '';
    foreach ($dataCw as $cw) {
        $dBits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
    }

    $mode = substr($dBits, 0, 4);
    if ($mode !== '0100') {
        return ['ok' => false, 'reason' => "Mode bukan Byte (0100): {$mode}"];
    }
    $countBits = $ver <= 9 ? 8 : 16;
    $charCount = bindec(substr($dBits, 4, $countBits));
    $decoded   = '';
    $pos       = 4 + $countBits;
    for ($i = 0; $i < $charCount; $i++) {
        $decoded .= chr(bindec(substr($dBits, $pos + $i * 8, 8)));
    }

    return ['ok' => true, 'version' => $ver, 'text' => $decoded];
}

// Uji sampel QRIS panjang 150-500 karakter + batas tiap versi V5-V17
$shortQrisRaw = '00020101021226590014COM.GO-JEK.WWW01189360091431234567890210G1234567890303UMI5204581453033605405220005802ID5916QUICKBREW COFFEE6006MALANG62190115TRX-1-1730800016304';
$qrisSamples = [
    'qris_168' => $shortQrisRaw . qris_crc16($shortQrisRaw),
    'qris_228' => build_sample_qris(228, 26400, 'TRX-12-1730800012'),
    'qris_275' => build_sample_qris(275, 58300, 'TRX-45-1730800045'),
    'qris_340' => build_sample_qris(340, 99000, 'TRX-88-1730800088'),
    'qris_430' => build_sample_qris(430, 125400, 'TRX-109-1730800109'),
    'qris_500' => build_sample_qris(500, 240900, 'TRX-512-1730800512'),
    'max_644'  => str_repeat('0123456789ABCDEF', 40) . 'WXYZ', // tepat 644 byte (batas V17-L)
];

foreach ($qrisSamples as $label => $sample) {
    $svg = QrSvg::svg($sample, 2);
    $res = decode_and_verify_svg($svg, 2, $isoTableL);
    assert_qr(
        $res['ok'] && $res['text'] === $sample,
        "Verifikasi matriks + sindrom RS + round-trip decode {$label} (len=" . strlen($sample) . ', V' . ($res['version'] ?? '?') . ')',
        $res['reason'] ?? 'teks tidak cocok'
    );
}

// Uji setiap versi V1 s.d. V17 minimal 1 sampel tepat di kapasitas versi tersebut
$versionEdgeLengths = [
    1 => 17, 2 => 32, 3 => 53, 4 => 78, 5 => 106, 6 => 134, 7 => 154, 8 => 192, 9 => 230,
    10 => 271, 11 => 321, 12 => 367, 13 => 425, 14 => 458, 15 => 520, 16 => 586, 17 => 644,
];
foreach ($versionEdgeLengths as $v => $maxBytes) {
    $payload = substr(str_repeat("V{$v}-QRIS-", (int) ceil($maxBytes / 6) + 2), 0, $maxBytes);
    $svg     = QrSvg::svg($payload, 2);
    $res     = decode_and_verify_svg($svg, 2, $isoTableL);
    assert_qr(
        $res['ok'] && $res['version'] === $v && $res['text'] === $payload,
        "Batas kapasitas penuh V{$v}-L ({$maxBytes} byte)",
        $res['reason'] ?? ('got V' . ($res['version'] ?? '?'))
    );
}

// Uji input terlalu panjang (> 644 byte) melempar InvalidArgumentException
$threwOverflow = false;
try {
    QrSvg::svg(str_repeat('X', 645));
} catch (InvalidArgumentException $e) {
    $threwOverflow = true;
}
assert_qr($threwOverflow, 'Input > 644 byte melempar InvalidArgumentException (tidak memotong diam-diam)');

// Uji dataUri menghasilkan awalan yang benar
$uri = QrSvg::dataUri($qrisSamples['qris_275']);
assert_qr(str_starts_with($uri, 'data:image/svg+xml;utf8,%3Csvg'), 'QrSvg::dataUri() menghasilkan data URI SVG valid');

echo "\nRingkasan QrSvg: {$passed} lulus, {$failed} gagal.\n";
exit($failed === 0 ? 0 : 1);
