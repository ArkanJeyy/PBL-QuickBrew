<?php
/**
 * Generator QR Code mandiri (Versi 1-4, Error Correction Level L, Byte Mode) -> SVG.
 * Dipakai untuk menghasilkan QR code meja secara offline tanpa library eksternal.
 */
final class QrSvg
{
    // [version => [size, totalCodewords, dataCodewords, ecCodewords]] (Level L, 1 block)
    private const VERSIONS = [
        1 => [21, 26,  19, 7],
        2 => [25, 44,  34, 10],
        3 => [29, 70,  55, 15],
        4 => [33, 100, 80, 20],
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

        $ver = 4;
        foreach (self::VERSIONS as $v => [, , $dataCap]) {
            if ($len + 2 <= $dataCap) {
                $ver = $v;
                break;
            }
        }
        [$size, , $dataCap, $ecLen] = self::VERSIONS[$ver];

        // Susun bit stream: mode 0100 (byte) + 8-bit length + data + terminator
        $bits = '0100' . str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
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

        $codewords = array_merge($data, self::reedSolomon($data, $ecLen));

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

        if ($ver >= 2) {
            $ap = 18 + ($ver - 2) * 4;
            for ($dr = -2; $dr <= 2; $dr++) {
                for ($dc = -2; $dc <= 2; $dc++) {
                    $m[$ap + $dr][$ap + $dc]   = max(abs($dr), abs($dc)) !== 1;
                    $res[$ap + $dr][$ap + $dc] = true;
                }
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

