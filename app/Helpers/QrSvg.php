<?php
/**
 * Generator QR Code mandiri (Versi 1-17, Error Correction Level L, Byte Mode) -> SVG.
 * Dipakai untuk menghasilkan QR code meja dan QRIS pembayaran secara offline tanpa library eksternal.
 */
final class QrSvg
{
    /**
     * Tabel parameter ISO/IEC 18004 untuk Error Correction Level L:
     * version => [size, totalCodewords, ecPerBlock, g1Blocks, g1DataCw, g2Blocks, g2DataCw, alignCoords]
     * Kapasitas maksimal V17-L (Byte Mode, header 20 bit) = 644 byte.
     */
    private const VERSIONS = [
        1  => [21,  26,  7, 1,  19, 0,   0, []],
        2  => [25,  44, 10, 1,  34, 0,   0, [6, 18]],
        3  => [29,  70, 15, 1,  55, 0,   0, [6, 22]],
        4  => [33, 100, 20, 1,  80, 0,   0, [6, 26]],
        5  => [37, 134, 26, 1, 108, 0,   0, [6, 30]],
        6  => [41, 172, 18, 2,  68, 0,   0, [6, 34]],
        7  => [45, 196, 20, 2,  78, 0,   0, [6, 22, 38]],
        8  => [49, 242, 24, 2,  97, 0,   0, [6, 24, 42]],
        9  => [53, 292, 30, 2, 116, 0,   0, [6, 26, 46]],
        10 => [57, 346, 18, 2,  68, 2,  69, [6, 28, 50]],
        11 => [61, 404, 20, 4,  81, 0,   0, [6, 30, 54]],
        12 => [65, 466, 24, 2,  92, 2,  93, [6, 32, 58]],
        13 => [69, 532, 26, 4, 107, 0,   0, [6, 34, 62]],
        14 => [73, 581, 30, 3, 115, 1, 116, [6, 26, 46, 66]],
        15 => [77, 655, 22, 5,  87, 1,  88, [6, 26, 48, 70]],
        16 => [81, 733, 24, 5,  98, 1,  99, [6, 26, 50, 74]],
        17 => [85, 815, 28, 1, 107, 5, 108, [6, 30, 54, 78]],
    ];

    public static function svg(string $text, int $margin = 2, string $fg = '#2e1f1a', string $bg = '#ffffff'): string
    {
        $matrix = self::encode($text);
        $size   = count($matrix);
        $dim    = $size + $margin * 2;
        $paths  = '';
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c]) {
                    $paths .= 'M' . ($c + $margin) . ',' . ($r + $margin) . 'h1v1h-1z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges" role="img" aria-label="QR Code">'
             . '<rect width="' . $dim . '" height="' . $dim . '" fill="' . htmlspecialchars($bg, ENT_QUOTES) . '" rx="1.5"/>'
             . '<path d="' . $paths . '" fill="' . htmlspecialchars($fg, ENT_QUOTES) . '"/>'
             . '</svg>';
    }

    public static function dataUri(string $text, int $margin = 2): string
    {
        return 'data:image/svg+xml;utf8,' . rawurlencode(self::svg($text, $margin));
    }

    private static function encode(string $text): array
    {
        $bytes = array_values(unpack('C*', $text));
        $len   = count($bytes);

        $ver = null;
        foreach (self::VERSIONS as $v => [, , , $g1Blocks, $g1DataCw, $g2Blocks, $g2DataCw]) {
            $dataCap  = $g1Blocks * $g1DataCw + $g2Blocks * $g2DataCw;
            // Byte mode (4 bit) + character count (8 bit utk V1-9, 16 bit utk V10-26)
            $overhead = $v <= 9 ? 2 : 3;
            if ($len + $overhead <= $dataCap) {
                $ver = $v;
                break;
            }
        }
        if ($ver === null) {
            throw new InvalidArgumentException('Panjang data QR melebihi kapasitas maksimal (644 byte).');
        }

        [$size, , $ecLen, $g1Blocks, $g1DataCw, $g2Blocks, $g2DataCw, $alignCoords] = self::VERSIONS[$ver];
        $dataCap   = $g1Blocks * $g1DataCw + $g2Blocks * $g2DataCw;
        $countBits = $ver <= 9 ? 8 : 16;

        // Susun bit stream: mode 0100 (byte) + panjang karakter (8/16 bit) + data + terminator
        $bits = '0100' . str_pad(decbin($len), $countBits, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $dataCap * 8 - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        $data = [];
        for ($i = 0, $n = strlen($bits); $i < $n; $i += 8) {
            $data[] = bindec(substr($bits, $i, 8));
        }
        $pads = [0xEC, 0x11];
        for ($p = 0; count($data) < $dataCap; $p++) {
            $data[] = $pads[$p & 1];
        }

        $codewords = self::interleaveBlocks($data, $ecLen, $g1Blocks, $g1DataCw, $g2Blocks, $g2DataCw);

        // Inisialisasi matriks & area fungsi
        $m   = array_fill(0, $size, array_fill(0, $size, false));
        $res = array_fill(0, $size, array_fill(0, $size, false));

        self::finder($m, $res, 0, 0);
        self::finder($m, $res, 0, $size - 7);
        self::finder($m, $res, $size - 7, 0);

        for ($i = 8; $i < $size - 8; $i++) {
            $m[6][$i]   = $m[$i][6]   = ($i % 2 === 0);
            $res[6][$i] = $res[$i][6] = true;
        }

        // Alignment patterns (lewati 3 sudut yang bertumpukan dengan finder pattern)
        foreach ($alignCoords as $ar) {
            foreach ($alignCoords as $ac) {
                if (($ar === 6 && $ac === 6)
                    || ($ar === 6 && $ac === $size - 7)
                    || ($ar === $size - 7 && $ac === 6)
                ) {
                    continue;
                }
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $m[$ar + $dr][$ac + $dc]   = max(abs($dr), abs($dc)) !== 1;
                        $res[$ar + $dr][$ac + $dc] = true;
                    }
                }
            }
        }

        // Version information 18-bit (Golay code 0x1F25) wajib untuk Versi >= 7
        if ($ver >= 7) {
            $verInfo = self::versionInfoBits($ver);
            for ($i = 0; $i < 18; $i++) {
                $bit = (($verInfo >> $i) & 1) === 1;
                $r1  = $size - 11 + ($i % 3);
                $c1  = intdiv($i, 3);
                $r2  = intdiv($i, 3);
                $c2  = $size - 11 + ($i % 3);

                $m[$r1][$c1]   = $m[$r2][$c2]   = $bit;
                $res[$r1][$c1] = $res[$r2][$c2] = true;
            }
        }

        // Dark module & reservasi format info
        $m[$size - 8][8]   = true;
        $res[$size - 8][8] = true;
        for ($i = 0; $i < 9; $i++) {
            $res[8][$i] = $res[$i][8] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            $res[8][$size - 1 - $i] = $res[$size - 1 - $i][8] = true;
        }

        // Isi bit data secara zig-zag dari kanan bawah
        $allBits = '';
        foreach ($codewords as $cw) {
            $allBits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }
        $bitIdx = 0;
        $bitLen = strlen($allBits);
        $up     = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col--;
            for ($step = 0; $step < $size; $step++) {
                $r = $up ? ($size - 1 - $step) : $step;
                for ($dc = 0; $dc < 2; $dc++) {
                    $c = $col - $dc;
                    if (!$res[$r][$c]) {
                        $bit = $bitIdx < $bitLen && $allBits[$bitIdx++] === '1';
                        // Mask 0: (r + c) % 2 === 0
                        if ((($r + $c) & 1) === 0) $bit = !$bit;
                        $m[$r][$c] = $bit;
                    }
                }
            }
            $up = !$up;
        }

        // Format info untuk Level L (01) + Mask 0 (000) -> 0x77C4 (15 bit)
        $fmt = 0x77C4;
        $fBits = [];
        for ($i = 0; $i < 15; $i++) {
            $fBits[$i] = (($fmt >> $i) & 1) === 1;
        }
        $coordsA = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
        foreach ($coordsA as $i => [$r, $c]) {
            $m[$r][$c] = $fBits[14 - $i];
        }
        for ($i = 0; $i < 7; $i++) {
            $m[$size - 1 - $i][8] = $fBits[14 - $i];
        }
        for ($i = 7; $i < 15; $i++) {
            $m[8][$size - 15 + $i] = $fBits[14 - $i];
        }

        return $m;
    }

    /**
     * Bagi data ke blok-blok Reed-Solomon (Group 1 & Group 2), hitung EC tiap blok,
     * lalu susun secara interleaved sesuai ISO/IEC 18004 Section 6.6.
     */
    private static function interleaveBlocks(
        array $data,
        int $ecLen,
        int $g1Blocks,
        int $g1DataCw,
        int $g2Blocks,
        int $g2DataCw
    ): array {
        $dataBlocks = [];
        $ecBlocks   = [];
        $offset     = 0;

        for ($b = 0; $b < $g1Blocks; $b++) {
            $block        = array_slice($data, $offset, $g1DataCw);
            $offset      += $g1DataCw;
            $dataBlocks[] = $block;
            $ecBlocks[]   = self::reedSolomon($block, $ecLen);
        }
        for ($b = 0; $b < $g2Blocks; $b++) {
            $block        = array_slice($data, $offset, $g2DataCw);
            $offset      += $g2DataCw;
            $dataBlocks[] = $block;
            $ecBlocks[]   = self::reedSolomon($block, $ecLen);
        }

        $codewords  = [];
        $maxDataLen = max($g1DataCw, $g2DataCw);
        for ($col = 0; $col < $maxDataLen; $col++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$col])) {
                    $codewords[] = $block[$col];
                }
            }
        }
        for ($col = 0; $col < $ecLen; $col++) {
            foreach ($ecBlocks as $ec) {
                $codewords[] = $ec[$col];
            }
        }

        return $codewords;
    }

    /** Hitung 18-bit Version Information memakai polinom Golay 0x1F25 (ISO/IEC 18004 Annex D). */
    private static function versionInfoBits(int $ver): int
    {
        $rem = $ver << 12;
        for ($bit = 17; $bit >= 12; $bit--) {
            if (($rem >> $bit) & 1) {
                $rem ^= (0x1F25 << ($bit - 12));
            }
        }
        return ($ver << 12) | $rem;
    }

    private static function finder(array &$m, array &$res, int $r0, int $c0): void
    {
        $size = count($m);
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $r = $r0 + $dr;
                $c = $c0 + $dc;
                if ($r < 0 || $c < 0 || $r >= $size || $c >= $size) continue;
                $in7 = $dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6;
                $onBorder = $dr === 0 || $dr === 6 || $dc === 0 || $dc === 6;
                $inCore   = $dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4;
                $m[$r][$c]   = $in7 && ($onBorder || $inCore);
                $res[$r][$c] = true;
            }
        }
    }

    private static function reedSolomon(array $data, int $ecLen): array
    {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        $mul = static fn (int $a, int $b): int => ($a && $b) ? $exp[$log[$a] + $log[$b]] : 0;

        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            for ($j = 0, $n = count($gen); $j < $n; $j++) {
                $next[$j]     ^= $gen[$j];
                $next[$j + 1] ^= $mul($gen[$j], $exp[$i]);
            }
            $gen = $next;
        }

        $rem = array_fill(0, $ecLen, 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($rem);
            $rem[]  = 0;
            for ($j = 0; $j < $ecLen; $j++) {
                $rem[$j] ^= $mul($gen[$j + 1], $factor);
            }
        }
        return $rem;
    }
}
