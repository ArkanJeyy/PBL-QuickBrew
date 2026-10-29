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

/** Format rupiah: format_rupiah(20000) -> "Rp 20.000,00"; format_rupiah(20000, false) -> "Rp 20.000" */
function format_rupiah($amount, bool $withDecimals = true): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.') . ($withDecimals ? ',00' : '');
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

