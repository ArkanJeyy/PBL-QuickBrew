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

/**
 * Kirim header keamanan HTTP standar (tanpa CSP agar tidak merusak skrip/gaya inline front-end).
 * Mengembalikan daftar header yang diterapkan.
 */
function kirim_header_keamanan(): array
{
    $headers = [
        'X-Frame-Options'        => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=()',
    ];
    if (!headers_sent()) {
        foreach ($headers as $nama => $nilai) {
            header($nama . ': ' . $nilai);
        }
    }
    return $headers;
}

/**
 * Ambil alamat IP klien.
 * Jika TRUSTED_PROXY=true (mis. di balik reverse proxy / ngrok), gunakan entri PALING KANAN
 * dari X-Forwarded-For yang ditambahkan oleh proxy tepercaya. Validasi dengan filter_var();
 * jika tidak valid, kembali ke REMOTE_ADDR.
 */
function ip_klien(): string
{
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
    if (filter_var($remote, FILTER_VALIDATE_IP) === false) {
        $remote = '127.0.0.1';
    }

    if (defined('TRUSTED_PROXY') && TRUSTED_PROXY) {
        $xff = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($xff !== '') {
            $bagian   = explode(',', $xff);
            $kandidat = trim((string) end($bagian));
            if (filter_var($kandidat, FILTER_VALIDATE_IP) !== false) {
                return $kandidat;
            }
        }
    }

    return $remote;
}

/** Hapus baris pembatasan_laju yang sudah lewat 1 jam secara probabilistik (~1%). */
function bersihkan_laju_lama_probabilistik(): void
{
    if (random_int(1, 100) === 1) {
        db()->exec("DELETE FROM pembatasan_laju WHERE waktu < CURRENT_TIMESTAMP - INTERVAL '1 hour'");
    }
}

/** Cek apakah jumlah hit untuk $kunci dalam $detik terakhir sudah mencapai/melebihi $maks (tanpa mencatat hit). */
function laju_terlampaui(string $kunci, int $maks, int $detik): bool
{
    $kunci = substr($kunci, 0, 120);
    $stmt  = db()->prepare(
        "SELECT COUNT(*) FROM pembatasan_laju
          WHERE kunci = :kunci
            AND waktu >= CURRENT_TIMESTAMP - (:detik * INTERVAL '1 second')"
    );
    $stmt->execute([
        ':kunci' => $kunci,
        ':detik' => max(1, $detik),
    ]);
    return ((int) $stmt->fetchColumn()) >= $maks;
}

/** Catat satu hit baru untuk $kunci pada tabel pembatasan_laju. */
function catat_laju(string $kunci, bool $jalankanGc = true): void
{
    if ($jalankanGc) {
        bersihkan_laju_lama_probabilistik();
    }
    $kunci = substr($kunci, 0, 120);
    $stmt  = db()->prepare('INSERT INTO pembatasan_laju (kunci) VALUES (?)');
    $stmt->execute([$kunci]);
}

/** Hapus seluruh catatan hit untuk $kunci (mis. setelah login berhasil untuk kunci username+IP). */
function reset_laju(string $kunci): void
{
    $kunci = substr($kunci, 0, 120);
    $stmt  = db()->prepare('DELETE FROM pembatasan_laju WHERE kunci = ?');
    $stmt->execute([$kunci]);
}

/**
 * Pembatas laju (rate limiter) berbasis tabel pembatasan_laju.
 * Mengembalikan true bila masih diizinkan (sekaligus mencatat hit), atau false bila batas terlampaui.
 */
function batasi_laju(string $kunci, int $maks, int $detik): bool
{
    bersihkan_laju_lama_probabilistik();
    if (laju_terlampaui($kunci, $maks, $detik)) {
        return false;
    }
    catat_laju($kunci, false);
    return true;
}

/**
 * Validasi path tujuan balik agar hanya menerima path internal relatif (anti Open Redirect).
 * Menolak URL eksternal (http/https), protocol-relative (//), backslash (\), maupun karakter kontrol.
 */
function tujuan_balik_aman(?string $target): ?string
{
    if ($target === null) {
        return null;
    }
    $target = trim($target);
    if ($target === '' || preg_match('/[\r\n\0\\\\]/', $target)) {
        return null;
    }
    if (str_starts_with($target, '//') || preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $target)) {
        return null;
    }
    $parsed = parse_url($target);
    if ($parsed === false || isset($parsed['scheme']) || isset($parsed['host']) || isset($parsed['user'])) {
        return null;
    }
    if (!str_starts_with($target, '/') && !str_starts_with($target, '?')) {
        return null;
    }
    return $target;
}

/**
 * Guard halaman/aksi staf (default role: ['kasir', 'admin']).
 * Role 'admin' otomatis memiliki seluruh hak akses 'kasir'.
 *
 * - Belum login -> redirect ke halaman login (menyertakan parameter redirect hanya bila path internal aman).
 * - Role tidak mencukupi -> HTTP 403 Forbidden.
 *
 * @param  string[]      $role       Daftar role yang diizinkan
 * @param  callable|null $redirector Callback redirect opsional untuk pengujian otomatis
 * @return array{id:int, nama:string, role:string}|array{} Data staf bila diizinkan
 */
function wajib_staf(array $role = ['kasir', 'admin'], ?callable $redirector = null): array
{
    $staf = $_SESSION['staf'] ?? null;
    if (!is_array($staf) || empty($staf['id']) || empty($staf['role'])) {
        $reqUri   = isset($_SERVER['REQUEST_URI']) ? tujuan_balik_aman((string) $_SERVER['REQUEST_URI']) : null;
        $loginUrl = $reqUri !== null ? url('login', ['redirect' => $reqUri]) : url('login');
        if ($redirector !== null) {
            ($redirector)($loginUrl);
            return [];
        }
        redirect($loginUrl);
    }

    $stafRole  = (string) $staf['role'];
    $diizinkan = in_array($stafRole, $role, true)
        || ($stafRole === 'admin' && in_array('kasir', $role, true));

    if (!$diizinkan) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>Akses ditolak: peran akun Anda tidak mencukupi.</p>';
        return [];
    }

    return [
        'id'   => (int) $staf['id'],
        'nama' => (string) ($staf['nama'] ?? ''),
        'role' => $stafRole,
    ];
}


