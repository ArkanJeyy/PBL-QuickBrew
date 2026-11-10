<?php
/**
 * Skrip CLI untuk mengganti password akun staf (kasir / admin) secara aman.
 *
 * Penggunaan:
 *   php bin/ganti_password.php <username>
 *
 * Aturan keamanan:
 *   - Hanya dapat dijalankan melalui CLI (PHP_SAPI === 'cli').
 *   - TIDAK menerima password melalui argumen baris perintah (agar tidak bocor di riwayat shell / daftar proses).
 *   - Panjang password baru minimal 10 karakter dan tidak boleh literal "password".
 *   - Menyimpan hash menggunakan password_hash(..., PASSWORD_DEFAULT).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Models/PenggunaModel.php';

if (!function_exists('baca_password_cli')) {
    /**
     * Baca satu baris password dari stream uji atau terminal interaktif (tersembunyi bila TTY).
     *
     * @param resource|null $inputStream Stream kustom untuk pengujian non-interaktif.
     */
    function baca_password_cli(string $prompt, $inputStream = null): string
    {
        if (is_resource($inputStream)) {
            echo $prompt;
            $line = fgets($inputStream);
            return $line === false ? '' : rtrim($line, "\r\n");
        }

        $isTty = function_exists('stream_isatty') && @stream_isatty(STDIN);
        if (!$isTty) {
            echo $prompt;
            $line = fgets(STDIN);
            return $line === false ? '' : rtrim($line, "\r\n");
        }

        // Terminal interaktif: sembunyikan echo karakter
        if (DIRECTORY_SEPARATOR === '\\') {
            fwrite(STDOUT, $prompt);
            $psCmd = 'powershell -NoProfile -NonInteractive -Command "$p = Read-Host -AsSecureString; '
                   . '$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($p); '
                   . '[Runtime.InteropServices.Marshal]::PtrToStringAuto($b)"';
            $out = @shell_exec($psCmd);
            fwrite(STDOUT, "\n");
            return $out === null ? '' : rtrim((string) $out, "\r\n");
        }

        fwrite(STDOUT, $prompt);
        @shell_exec('stty -echo 2>/dev/null');
        $line = fgets(STDIN);
        @shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
        return $line === false ? '' : rtrim($line, "\r\n");
    }
}

if (!function_exists('jalankan_cli_ganti_password')) {
    /**
     * Eksekutor CLI ganti password (mengembalikan kode keluar 0 = sukses, 1 = gagal).
     *
     * @param string[]      $args        Daftar argumen CLI (termasuk $argv[0]).
     * @param resource|null $inputStream Stream input opsional (untuk pengujian otomatis).
     */
    function jalankan_cli_ganti_password(array $args, $inputStream = null): int
    {
        if (count($args) !== 2) {
            if (count($args) > 2) {
                fwrite(STDERR, "Keamanan: Jangan masukkan password lewat argumen baris perintah.\n");
            }
            fwrite(STDERR, "Penggunaan: php bin/ganti_password.php <username>\n");
            return 1;
        }

        $username = trim((string) $args[1]);
        if ($username === '') {
            fwrite(STDERR, "Galat: Username tidak boleh kosong.\n");
            return 1;
        }

        $pengguna = PenggunaModel::cariUsername($username);
        if (!$pengguna) {
            fwrite(STDERR, "Galat: Pengguna dengan username '{$username}' tidak ditemukan.\n");
            return 1;
        }

        $passwordBaru = baca_password_cli("Masukkan password baru (minimal 10 karakter): ", $inputStream);
        if (text_length($passwordBaru) < 10) {
            fwrite(STDERR, "Galat: Password baru minimal harus 10 karakter.\n");
            return 1;
        }

        if (strtolower($passwordBaru) === 'password') {
            fwrite(STDERR, "Galat: Password tidak boleh menggunakan kata 'password'.\n");
            return 1;
        }

        $konfirmasi = baca_password_cli("Ulangi password baru: ", $inputStream);
        if (!hash_equals($passwordBaru, $konfirmasi)) {
            fwrite(STDERR, "Galat: Konfirmasi password tidak cocok.\n");
            return 1;
        }

        $hashBaru = password_hash($passwordBaru, PASSWORD_DEFAULT);
        PenggunaModel::perbaruiPassword((int) $pengguna['id'], $hashBaru);

        echo sprintf(
            "Berhasil: Password untuk pengguna '%s' (role: %s) telah diperbarui.\n",
            (string) $pengguna['username'],
            (string) $pengguna['role']
        );
        return 0;
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(jalankan_cli_ganti_password($argv ?? []));
}
