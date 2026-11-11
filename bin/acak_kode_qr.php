<?php
/**
 * Skrip CLI untuk mengacak kode QR meja (`meja.kode_qr`) dengan 16 karakter hex acak.
 *
 * Penggunaan:
 *   php bin/acak_kode_qr.php             # Mode dry-run (hanya menampilkan rencana tanpa mengubah DB)
 *   php bin/acak_kode_qr.php --jalankan  # Mengganti meja.kode_qr di database
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';

if (!function_exists('buat_kode_qr_unik')) {
    /**
     * Hasilkan 16 karakter hex acak (`bin2hex(random_bytes(8))`) yang belum ada di himpunan `$sudahDipakai`.
     *
     * @param array<string, true> $sudahDipakai Peta kode_qr yang sudah dipakai (diperbarui in-place).
     */
    function buat_kode_qr_unik(array &$sudahDipakai): string
    {
        do {
            $kandidat = bin2hex(random_bytes(8));
        } while (isset($sudahDipakai[$kandidat]));

        $sudahDipakai[$kandidat] = true;
        return $kandidat;
    }
}

if (!function_exists('jalankan_cli_acak_kode_qr')) {
    /**
     * Eksekutor CLI pengacak kode_qr meja (0 = sukses, 1 = gagal).
     *
     * @param string[] $args Daftar argumen CLI (termasuk $argv[0]).
     */
    function jalankan_cli_acak_kode_qr(array $args): int
    {
        $jalankan = false;

        foreach (array_slice($args, 1) as $arg) {
            if ($arg === '--jalankan') {
                $jalankan = true;
            } else {
                fwrite(STDERR, "Argumen tidak dikenal: {$arg}\n");
                fwrite(STDERR, "Penggunaan: php bin/acak_kode_qr.php [--jalankan]\n");
                return 1;
            }
        }

        try {
            $db   = db();
            $rows = $db->query('SELECT id, no_meja, kode_qr FROM meja ORDER BY no_meja ASC, id ASC')->fetchAll();

            if (!$rows) {
                echo "Tidak ada data meja di database.\n";
                return 0;
            }

            $sudahDipakai = [];
            foreach ($rows as $r) {
                $sudahDipakai[(string) $r['kode_qr']] = true;
            }

            $rencana = [];
            foreach ($rows as $r) {
                $kodeBaru  = buat_kode_qr_unik($sudahDipakai);
                $rencana[] = [
                    'id'        => (int) $r['id'],
                    'no_meja'   => (int) $r['no_meja'],
                    'kode_lama' => (string) $r['kode_qr'],
                    'kode_baru' => $kodeBaru,
                    'url_baru'  => url('home', ['meja' => $kodeBaru]),
                ];
            }

            if (!$jalankan) {
                echo "[MODE DRY-RUN] Rencana pengacakan kode_qr meja (database TIDAK diubah):\n";
                echo str_repeat('-', 78) . "\n";
                echo sprintf("%-8s | %-16s | %-16s | %s\n", 'No Meja', 'Kode Lama', 'Rencana Baru', 'URL Baru');
                echo str_repeat('-', 78) . "\n";
                foreach ($rencana as $item) {
                    echo sprintf(
                        "Meja %-3d | %-16s | %-16s | %s\n",
                        $item['no_meja'],
                        $item['kode_lama'],
                        $item['kode_baru'],
                        $item['url_baru']
                    );
                }
                echo str_repeat('-', 78) . "\n";
                echo "Jalankan dengan argumen --jalankan untuk menerapkan perubahan ke database.\n";
                return 0;
            }

            // Eksekusi pembaruan di dalam transaksi dengan retry jika terjadi bentrok UNIQUE
            $db->beginTransaction();
            try {
                $stmtUpdate = $db->prepare('UPDATE meja SET kode_qr = :kode WHERE id = :id');
                $stmtCek    = $db->prepare('SELECT COUNT(*) FROM meja WHERE kode_qr = :kode AND id <> :id');

                foreach ($rencana as &$item) {
                    while (true) {
                        $stmtCek->execute([':kode' => $item['kode_baru'], ':id' => $item['id']]);
                        if ((int) $stmtCek->fetchColumn() === 0) {
                            break;
                        }
                        $item['kode_baru'] = buat_kode_qr_unik($sudahDipakai);
                        $item['url_baru']  = url('home', ['meja' => $item['kode_baru']]);
                    }

                    $stmtUpdate->execute([
                        ':kode' => $item['kode_baru'],
                        ':id'   => $item['id'],
                    ]);
                }
                unset($item);

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            echo "[BERHASIL] Kode QR meja telah diperbarui menjadi 16 karakter hex acak:\n";
            echo str_repeat('-', 68) . "\n";
            echo sprintf("%-10s | %-18s | %s\n", 'No Meja', 'Kode QR Baru', 'URL Baru');
            echo str_repeat('-', 68) . "\n";
            foreach ($rencana as $item) {
                echo sprintf(
                    "Meja %-5d -> %-18s | %s\n",
                    $item['no_meja'],
                    $item['kode_baru'],
                    $item['url_baru']
                );
            }
            echo str_repeat('-', 68) . "\n";
            echo "PERINGATAN: Kode QR cetak lama tidak berlaku lagi! Segera cetak ulang QR seluruh meja.\n";
            return 0;
        } catch (Throwable $e) {
            error_log('[QuickBrew Acak QR Fatal] ' . $e->getMessage());
            fwrite(STDERR, "Gagal mengacak kode QR meja: " . $e->getMessage() . "\n");
            return 1;
        }
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(jalankan_cli_acak_kode_qr($argv ?? []));
}
