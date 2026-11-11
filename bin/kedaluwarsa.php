<?php
/**
 * Skrip CLI pembersih pesanan menggantung (kedaluwarsa) QuickBrew.
 *
 * Penggunaan:
 *   php bin/kedaluwarsa.php [--dry-run] [--tunai-menit=60]
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once APP_ROOT . '/app/Services/KedaluwarsaService.php';

if (!function_exists('jalankan_cli_kedaluwarsa')) {
    /**
     * Eksekutor CLI kedaluwarsa (mengembalikan kode keluar 0 = sukses, 1 = gagal).
     *
     * @param string[] $args Daftar argumen CLI (termasuk $argv[0]).
     */
    function jalankan_cli_kedaluwarsa(array $args): int
    {
        $dryRun     = false;
        $tunaiMenit = null;

        foreach (array_slice($args, 1) as $arg) {
            if ($arg === '--dry-run') {
                $dryRun = true;
            } elseif (str_starts_with($arg, '--tunai-menit=')) {
                $nilai = substr($arg, strlen('--tunai-menit='));
                if (!ctype_digit($nilai) || (int) $nilai < 1) {
                    fwrite(STDERR, "Argumen --tunai-menit harus berupa bilangan bulat positif (>= 1).\n");
                    return 1;
                }
                $tunaiMenit = (int) $nilai;
            } else {
                fwrite(STDERR, "Argumen tidak dikenal: {$arg}\nPenggunaan: php bin/kedaluwarsa.php [--dry-run] [--tunai-menit=N]\n");
                return 1;
            }
        }

        try {
            $service   = new KedaluwarsaService();
            $ringkasan = $service->jalankan($dryRun, $tunaiMenit);

            $modeLabel = $ringkasan['dry_run'] ? 'DRY-RUN (tanpa perubahan DB)' : 'EKSEKUSI';
            echo sprintf(
                "[QuickBrew Kedaluwarsa] Mode=%s | Batas QRIS=%d menit | Batas Tunai=%d menit\n"
                . "  Kandidat QRIS=%d (kedaluwarsa=%d, ternyata_lunas=%d) | Kandidat Tunai=%d (dibatalkan=%d)\n",
                $modeLabel,
                $ringkasan['qris_batas_menit'],
                $ringkasan['tunai_batas_menit'],
                $ringkasan['kandidat_qris'],
                $ringkasan['qris_kedaluwarsa'],
                $ringkasan['qris_ternyata_lunas'],
                $ringkasan['kandidat_tunai'],
                $ringkasan['tunai_dibatalkan']
            );
            return 0;
        } catch (Throwable $e) {
            error_log('[QuickBrew Kedaluwarsa Fatal] ' . $e->getMessage());
            fwrite(STDERR, "Gagal menjalankan pembersihan kedaluwarsa: " . $e->getMessage() . "\n");
            return 1;
        }
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(jalankan_cli_kedaluwarsa($argv ?? []));
}
