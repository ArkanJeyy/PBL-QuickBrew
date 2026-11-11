<?php
/**
 * Controller autentikasi staf (kasir & admin).
 * Menyediakan halaman/aksi login (GET/POST) dan logout (POST ber-CSRF).
 */
final class AuthController
{
    /** Batas gagal login per (username + IP): 5 percobaan / 15 menit. */
    public const BATAS_GAGAL_USER_IP = 5;

    /** Batas gagal login per IP: 20 percobaan / 15 menit. */
    public const BATAS_GAGAL_IP = 20;

    /** Jendela waktu pembatasan laju login (15 menit = 900 detik). */
    public const JENDELA_DETIK = 900;

    /** Hash bcrypt dummy untuk menjaga waktu verifikasi konstan saat username tidak ditemukan. */
    private const DUMMY_BCRYPT_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    /** @var callable|null */
    private $redirector;

    public function __construct(?callable $redirector = null)
    {
        $this->redirector = $redirector;
    }

    /**
     * Tampilkan form login staf (GET) atau proses kredensial login (POST).
     */
    public function login(): void
    {
        $method   = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $rawBack  = (string) ($method === 'POST' ? ($_POST['redirect'] ?? $_GET['redirect'] ?? '') : ($_GET['redirect'] ?? ''));
        $amanBack = tujuan_balik_aman($rawBack);

        // Jika staf sudah login pada GET, langsung arahkan ke tujuan balik aman atau beranda
        if ($method !== 'POST' && !empty($_SESSION['staf']['id'])) {
            $this->arahkan($amanBack ?? url('home'));
            return;
        }

        if ($method !== 'POST') {
            $this->renderForm(null, '', $amanBack ?? '');
            return;
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $this->renderForm('Sesi formulir telah berakhir, silakan muat ulang halaman dan coba lagi.', $username, $amanBack ?? '');
            return;
        }

        // Normalisasi username (trim, huruf kecil, maks 50 karakter) + IP klien -> SHA-256
        $normUser    = strtolower(text_cut($username, 50));
        $ip          = ip_klien();
        $kunciUserIp = 'login_u:' . hash('sha256', $normUser . '|' . $ip);
        $kunciIp     = 'login_ip:' . hash('sha256', $ip);

        // Cek kuota SEBELUM verifikasi password
        if (
            laju_terlampaui($kunciUserIp, self::BATAS_GAGAL_USER_IP, self::JENDELA_DETIK)
            || laju_terlampaui($kunciIp, self::BATAS_GAGAL_IP, self::JENDELA_DETIK)
        ) {
            http_response_code(429);
            $this->renderForm(
                'Terlalu banyak percobaan login yang gagal. Silakan coba lagi dalam 15 menit.',
                $username,
                $amanBack ?? ''
            );
            return;
        }

        $pengguna = $username !== '' ? PenggunaModel::cariUsername($username) : null;

        // Anti enumerasi & timing: selalu jalankan password_verify meskipun username tidak ditemukan
        $hashTarget = $pengguna !== null ? (string) $pengguna['password'] : self::DUMMY_BCRYPT_HASH;
        $cocok      = $password !== '' && password_verify($password, $hashTarget) && $pengguna !== null;

        if (!$cocok) {
            catat_laju($kunciUserIp);
            catat_laju($kunciIp);
            http_response_code(401);
            $this->renderForm('Username atau password salah.', $username, $amanBack ?? '');
            return;
        }

        // Blokir password literal default "password" pada lingkungan production
        $appEnv = (string) env('APP_ENV', defined('APP_ENV') ? APP_ENV : 'production');
        if ($appEnv === 'production' && $password === 'password') {
            http_response_code(403);
            $this->renderForm(
                'Password default harus diganti (lihat docs/DEPLOY_CHECKLIST.md / jalankan bin/ganti_password.php).',
                $username,
                $amanBack ?? ''
            );
            return;
        }

        // Perbarui hash otomatis bila algoritma/cost default PHP meningkat
        if (password_needs_rehash((string) $pengguna['password'], PASSWORD_DEFAULT)) {
            $hashBaru = password_hash($password, PASSWORD_DEFAULT);
            PenggunaModel::perbaruiPassword((int) $pengguna['id'], $hashBaru);
        }

        // Login sukses: reset kuota hanya untuk kunci (username+IP); kunci per-IP tidak direset
        reset_laju($kunciUserIp);

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        $_SESSION['staf'] = [
            'id'   => (int) $pengguna['id'],
            'nama' => (string) $pengguna['nama'],
            'role' => (string) $pengguna['role'],
        ];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $this->arahkan($amanBack ?? url('home'));
    }

    /**
     * Proses logout staf (wajib POST + token CSRF valid).
     * Menghancurkan sesi dan menghapus cookie sesi.
     */
    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!csrf_check($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?? '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => (bool) ($params['secure'] ?? false),
                    'httponly' => (bool) ($params['httponly'] ?? true),
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }

        $this->arahkan(url('login'));
    }

    private function renderForm(?string $error, string $username, string $redirect): void
    {
        view('auth/login', [
            'error'     => $error,
            'username'  => $username,
            'redirect'  => $redirect,
            'csrfToken' => csrf_token(),
        ]);
    }

    private function arahkan(string $targetUrl): void
    {
        if ($this->redirector !== null) {
            ($this->redirector)($targetUrl);
            return;
        }
        redirect($targetUrl);
    }
}
