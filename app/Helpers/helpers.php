<?php
/**
 * Helper umum untuk view & controller.
 * Bergantung pada konstanta di config/config.php (APP_ROOT, BASE_URL) dan fungsi e().
 */

/** URL file di public/assets. URL absolut (http/https) dikembalikan apa adanya. */
function asset(string $path): string
{
    if (preg_match('#^https?://#i', $path)) return $path;
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

/** URL halaman lewat front controller: url('menu') -> /public/?page=menu */
function url(string $page = 'home', array $query = []): string
{
    if ($page !== 'home') $query = ['page' => $page] + $query;
    return BASE_URL . '/' . ($query ? '?' . http_build_query($query) : '');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/** Format rupiah: format_rupiah(20000) -> "Rp 20.000"; format_rupiah(20000, true) -> "Rp 20.000,00" */
function format_rupiah($amount, bool $withDecimals = false): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.') . ($withDecimals ? ',00' : '');
}

/**
 * Panjang teks dalam karakter (UTF-8). Tidak mewajibkan ekstensi mbstring
 * (tidak selalu aktif, mis. saat menjalankan `php -S` tanpa php.ini Laragon).
 */
function text_length(string $text): int
{
    if (function_exists('mb_strlen')) return mb_strlen($text, 'UTF-8');
    $count = preg_match_all('/./us', $text);
    return $count === false ? strlen($text) : $count; // UTF-8 rusak: hitung byte
}

/** Potong teks menjadi maksimal $max karakter (UTF-8), tanpa mewajibkan mbstring. */
function text_cut(string $text, int $max): string
{
    if (function_exists('mb_substr')) return mb_substr($text, 0, $max, 'UTF-8');
    return preg_match('/^.{0,' . $max . '}/us', $text, $m) ? $m[0] : substr($text, 0, $max);
}

/** Ubah teks menjadi slug: "Caffè Latte" -> "caffe-latte" */
function slugify(string $text): string
{
    $text = strtr($text, ['à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u', '&' => 'and']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
}

/** Render view di app/Views. view('pelanggan/public/menu', [...]) */
function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_ROOT . '/app/Views/' . $name . '.php';
}

/** Opsi kustomisasi minuman (app/Models/customization-options.php), dimuat sekali per request. */
function customization_options(): array
{
    static $options = null;
    if ($options === null) {
        $options = require APP_ROOT . '/app/Models/customization-options.php';
    }
    return $options;
}

/** Token CSRF per sesi untuk form POST (mis. Checkout). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Cek token CSRF dari form; perbandingan aman terhadap timing attack. */
function csrf_check($token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

