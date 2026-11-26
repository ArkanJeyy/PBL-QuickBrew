<?php
/**
 * Skrip CLI untuk membuat atau membersihkan data pesanan dummy (khusus pengujian/demo performa).
 *
 * Penggunaan:
 *   php bin/seed_dummy.php [--jalankan] [--pesanan=5000] [--hari=90] [--seed=42]
 *   php bin/seed_dummy.php --bersihkan [--jalankan]
 *
 * Keamanan:
 *   - Default selalu mode dry-run (tidak mengubah database).
 *   - MENOLAK berjalan apabila nama database aktif tidak berakhiran `_test` atau `_demo`.
 *   - Data dummy ditandai dengan nama_pelanggan berawalan "Dummy " dan email berakhiran "@dummy.test".
 *   - Tidak pernah mengubah kolom `menu.stok`.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
require_once APP_ROOT . '/app/Helpers/helpers.php';
require_once APP_ROOT . '/app/Models/MenuModel.php';
require_once APP_ROOT . '/app/Models/PemesananModel.php';

if (!function_exists('database_aman_untuk_dummy')) {
    /**
     * Pastikan skrip dummy hanya diizinkan pada database berakhiran `_test` atau `_demo`.
     */
    function database_aman_untuk_dummy(string $namaDatabase): bool
    {
        return preg_match('/_(test|demo)$/i', trim($namaDatabase)) === 1;
    }
}

if (!function_exists('jalankan_cli_seed_dummy')) {
    /**
     * Eksekutor CLI pembuat/pembersih data dummy (0 = sukses, 1 = gagal).
     *
     * @param string[] $args Daftar argumen CLI (termasuk $argv[0]).
     */
    function jalankan_cli_seed_dummy(array $args): int
    {
        $jalankan  = false;
        $bersihkan = false;
        $pesanan   = 5000;
        $hari      = 90;
        $seed      = 42;

        foreach (array_slice($args, 1) as $arg) {
            if ($arg === '--jalankan') {
                $jalankan = true;
            } elseif ($arg === '--bersihkan') {
                $bersihkan = true;
            } elseif (str_starts_with($arg, '--pesanan=')) {
                $val = substr($arg, strlen('--pesanan='));
                if (!ctype_digit($val) || (int) $val < 1 || (int) $val > 200000) {
                    fwrite(STDERR, "Galat: Nilai --pesanan harus antara 1 sampai 200000.\n");
                    return 1;
                }
                $pesanan = (int) $val;
            } elseif (str_starts_with($arg, '--hari=')) {
                $val = substr($arg, strlen('--hari='));
                if (!ctype_digit($val) || (int) $val < 1 || (int) $val > 3650) {
                    fwrite(STDERR, "Galat: Nilai --hari harus antara 1 sampai 3650.\n");
                    return 1;
                }
                $hari = (int) $val;
            } elseif (str_starts_with($arg, '--seed=')) {
                $val = substr($arg, strlen('--seed='));
                if (!ctype_digit($val)) {
                    fwrite(STDERR, "Galat: Nilai --seed harus bilangan bulat non-negatif.\n");
                    return 1;
                }
                $seed = (int) $val;
            } else {
                fwrite(STDERR, "Argumen tidak dikenal: {$arg}\n");
                fwrite(STDERR, "Penggunaan: php bin/seed_dummy.php [--jalankan] [--pesanan=5000] [--hari=90] [--seed=42] [--bersihkan]\n");
                return 1;
            }
        }

        try {
            $db       = db();
            $activeDb = (string) $db->query('SELECT current_database()')->fetchColumn();
        } catch (Throwable $e) {
            fwrite(STDERR, "Gagal terhubung ke database: " . $e->getMessage() . "\n");
            return 1;
        }

        if (!database_aman_untuk_dummy($activeDb)) {
            fwrite(
                STDERR,
                "KEAMANAN: bin/seed_dummy.php ditolak pada database '{$activeDb}'. "
                . "Hanya diizinkan pada database berakhiran '_test' atau '_demo'.\n"
            );
            return 1;
        }

        // Mode pembersihan data dummy saja
        if ($bersihkan) {
            $jumlahDummy = (int) $db->query(
                "SELECT COUNT(*) FROM pemesanan WHERE nama_pelanggan LIKE 'Dummy %' AND email_pelanggan LIKE '%@dummy.test'"
            )->fetchColumn();

            if (!$jalankan) {
                echo sprintf(
                    "[DRY-RUN BERSIHKAN] DB=%s | Ditemukan %d pesanan dummy yang akan dihapus (data non-dummy aman). "
                    . "Tambahkan --jalankan untuk menghapus.\n",
                    $activeDb,
                    $jumlahDummy
                );
                return 0;
            }

            $db->beginTransaction();
            try {
                $terhapus = $db->exec(
                    "DELETE FROM pemesanan WHERE nama_pelanggan LIKE 'Dummy %' AND email_pelanggan LIKE '%@dummy.test'"
                );
                $db->commit();
                $db->exec('ANALYZE pemesanan, detail_pemesanan, pembayaran');
                echo sprintf(
                    "[BERSIHKAN SELESAI] DB=%s | Berhasil menghapus %d pesanan dummy (detail & pembayaran ikut terhapus).\n",
                    $activeDb,
                    (int) $terhapus
                );
                return 0;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                fwrite(STDERR, "Gagal membersihkan data dummy: " . $e->getMessage() . "\n");
                return 1;
            }
        }

        // Mode dry-run seeding
        if (!$jalankan) {
            echo sprintf(
                "[DRY-RUN SEED] DB=%s | Rencana membuat %d pesanan dummy tersebar pada %d hari terakhir (seed=%d).\n"
                . "Distribusi: QRIS ~60%% / Tunai ~40%%, jam ramai pagi & sore, 1-4 item/pesanan, stok menu tidak diubah.\n"
                . "Tambahkan --jalankan untuk menulis ke database.\n",
                $activeDb,
                $pesanan,
                $hari,
                $seed
            );
            return 0;
        }

        // Ambil data referensi meja, menu aktif, dan pengguna kasir/admin yang sudah ada
        $mejaIds = $db->query('SELECT id FROM meja ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
        $menus   = $db->query("
            SELECT m.id, m.kategori_id, LOWER(k.nama_kategori) AS slug_kat, m.harga
              FROM menu m
              JOIN kategori k ON k.id = m.kategori_id
             WHERE m.aktif = TRUE
             ORDER BY m.id ASC
        ")->fetchAll();
        $stafIds = $db->query("SELECT id FROM pengguna WHERE role IN ('kasir', 'admin') ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

        if (!$mejaIds || !$menus) {
            fwrite(STDERR, "Galat: Data referensi meja atau menu belum tersedia di database '{$activeDb}'.\n");
            return 1;
        }

        $opts       = require APP_ROOT . '/app/Models/customization-options.php';
        $sizeOpts   = $opts['size']['options'] ?? ['S', 'M', 'L'];
        $sizePrices = $opts['size']['prices'] ?? ['M' => 3000, 'L' => 5000];
        $typeOpts   = $opts['type']['options'] ?? ['Hot', 'Ice'];
        $sugarOpts  = $opts['sugar']['options'] ?? ['Normal Sugar', 'Less Sugar', 'Extra Sugar'];
        $iceOpts    = $opts['ice']['options'] ?? ['Normal Ice', 'Less Ice', 'Extra Ice'];
        $addonItems = $opts['addons']['items'] ?? [];

        // Bobot distribusi jam operasional realistis (jam 07..22; puncak pagi 08-10 & sore 14-17)
        $jamKumulatif = [];
        $bobotJam     = [
            7  => 5,
            8  => 13,
            9  => 14,
            10 => 10,
            11 => 7,
            12 => 8,
            13 => 7,
            14 => 12,
            15 => 14,
            16 => 12,
            17 => 9,
            18 => 5,
            19 => 4,
            20 => 3,
            21 => 2,
        ];
        $acc = 0;
        foreach ($bobotJam as $jam => $w) {
            $acc += $w;
            $jamKumulatif[$jam] = $acc;
        }
        $totalBobotJam = $acc;

        mt_srand($seed);

        $sekarangTs = time();
        $runPrefix  = ((int) $db->query('SELECT COALESCE(MAX(id), 0) FROM pemesanan')->fetchColumn()) + 1;
        $batchSize  = 500;
        $countMeja  = count($mejaIds);
        $countMenu  = count($menus);
        $countStaf  = count($stafIds);
        $countSize  = count($sizeOpts);
        $countType  = count($typeOpts);
        $countSugar = count($sugarOpts);
        $countIce   = count($iceOpts);
        $countAddon = count($addonItems);

        $mulai = microtime(true);
        $db->beginTransaction();

        try {
            $stmtOrder = null;
            $batchData = [];

            $flushBatch = static function (array $itemsBatch) use ($db): void {
                if ($itemsBatch === []) {
                    return;
                }

                // 1. Multi-row INSERT INTO pemesanan ... RETURNING id
                $placeholdersP = [];
                $paramsP       = [];
                foreach ($itemsBatch as $row) {
                    $placeholdersP[] = '(?, ?, ?, ?, ?, ?)';
                    $paramsP[]       = $row['meja_id'];
                    $paramsP[]       = $row['nama'];
                    $paramsP[]       = $row['email'];
                    $paramsP[]       = $row['total_harga'];
                    $paramsP[]       = $row['status_pemesanan'];
                    $paramsP[]       = $row['waktu_pemesanan'];
                }

                $sqlP = 'INSERT INTO pemesanan (meja_id, nama_pelanggan, email_pelanggan, total_harga, status_pemesanan, waktu_pemesanan) VALUES '
                    . implode(', ', $placeholdersP)
                    . ' RETURNING id';
                $stmtP = $db->prepare($sqlP);
                $stmtP->execute($paramsP);
                $ids = $stmtP->fetchAll(PDO::FETCH_COLUMN);

                // 2. Multi-row INSERT INTO detail_pemesanan & pembayaran
                $placeholdersD = [];
                $paramsD       = [];
                $placeholdersB = [];
                $paramsB       = [];

                foreach ($itemsBatch as $idx => $row) {
                    $pid = (int) $ids[$idx];
                    foreach ($row['lines'] as $ln) {
                        $placeholdersD[] = '(?, ?, ?, ?, ?, ?)';
                        $paramsD[]       = $ln['menu_id'];
                        $paramsD[]       = $pid;
                        $paramsD[]       = $ln['harga'];
                        $paramsD[]       = $ln['jumlah'];
                        $paramsD[]       = $ln['subtotal'];
                        $paramsD[]       = $ln['catatan'];
                    }

                    $placeholdersB[] = '(?, ?, ?, ?, ?, ?, ?)';
                    $paramsB[]       = $pid;
                    $paramsB[]       = $row['pengguna_id'];
                    $paramsB[]       = $row['midtrans_order_id'];
                    $paramsB[]       = $row['waktu_pembayaran'];
                    $paramsB[]       = $row['status_pembayaran'];
                    $paramsB[]       = $row['metode_pembayaran'];
                    $paramsB[]       = $row['total_harga'];
                }

                $sqlD = 'INSERT INTO detail_pemesanan (menu_id, pemesanan_id, harga, jumlah, subtotal, catatan) VALUES '
                    . implode(', ', $placeholdersD);
                $db->prepare($sqlD)->execute($paramsD);

                $sqlB = 'INSERT INTO pembayaran (pemesanan_id, pengguna_id, midtrans_order_id, waktu_pembayaran, status_pembayaran, metode_pembayaran, total_bayar) VALUES '
                    . implode(', ', $placeholdersB);
                $db->prepare($sqlB)->execute($paramsB);
            };

            for ($i = 1; $i <= $pesanan; $i++) {
                // Pilih hari acak dalam [$hari - 1 .. 0] hari lalu dan jam berbobot realistis
                $hariOffset = mt_rand(0, $hari - 1);
                $tglDasar   = date('Y-m-d', $sekarangTs - ($hariOffset * 86400));

                $rollJam  = mt_rand(1, $totalBobotJam);
                $jamPilih = 9;
                foreach ($jamKumulatif as $j => $batas) {
                    if ($rollJam <= $batas) {
                        $jamPilih = $j;
                        break;
                    }
                }
                $menit = mt_rand(0, 59);
                $detik = mt_rand(0, 59);
                $waktuPesanTs = strtotime(sprintf('%s %02d:%02d:%02d', $tglDasar, $jamPilih, $menit, $detik));
                $waktuPesan   = date('Y-m-d H:i:s', $waktuPesanTs);

                // Susun 1-4 baris item dengan kustomisasi valid
                $jumlahBaris = mt_rand(1, 4);
                $lines       = [];
                $subtotalAll = 0;

                for ($l = 0; $l < $jumlahBaris; $l++) {
                    $menu       = $menus[mt_rand(0, $countMenu - 1)];
                    $hargaDasar = (int) $menu['harga'];
                    $qty        = mt_rand(1, 3);
                    $catatan    = null;
                    $tambahan   = 0;

                    if ((string) $menu['slug_kat'] === 'minuman') {
                        $sz      = $sizeOpts[mt_rand(0, $countSize - 1)];
                        $tp      = $typeOpts[mt_rand(0, $countType - 1)];
                        $sg      = $sugarOpts[mt_rand(0, $countSugar - 1)];
                        $ic      = $iceOpts[mt_rand(0, $countIce - 1)];
                        $tambahan += (int) ($sizePrices[$sz] ?? 0);

                        $bagian = [$sz, $tp, $sg, $ic];
                        if ($countAddon > 0 && mt_rand(1, 100) <= 30) {
                            $addon    = $addonItems[mt_rand(0, $countAddon - 1)];
                            $bagian[] = (string) $addon['label'];
                            $tambahan += (int) ($addon['price'] ?? 0);
                        }
                        $catatan = implode(', ', $bagian);
                    }

                    $hargaSatuan  = $hargaDasar + $tambahan;
                    $subBaris     = $hargaSatuan * $qty;
                    $subtotalAll += $subBaris;

                    $lines[] = [
                        'menu_id'  => (int) $menu['id'],
                        'harga'    => $hargaSatuan,
                        'jumlah'   => $qty,
                        'subtotal' => $subBaris,
                        'catatan'  => $catatan,
                    ];
                }

                $pajak      = (int) round($subtotalAll * PemesananModel::TAX_RATE);
                $totalHarga = $subtotalAll + $pajak;

                // Metode pembayaran: ~60% QRIS, ~40% Tunai
                $metode = (mt_rand(1, 100) <= 60) ? 'qris' : 'tunai';

                // Status pesanan realistis: 78% selesai, 4% diproses, 2% siap, 4% menunggu_pembayaran, 12% batal
                $rollStatus = mt_rand(1, 100);
                if ($rollStatus <= 78) {
                    $statusPesanan = 'selesai';
                    $statusBayar   = 'paid';
                } elseif ($rollStatus <= 82) {
                    $statusPesanan = 'diproses';
                    $statusBayar   = 'paid';
                } elseif ($rollStatus <= 84) {
                    $statusPesanan = 'siap';
                    $statusBayar   = 'paid';
                } elseif ($rollStatus <= 88) {
                    $statusPesanan = 'menunggu_pembayaran';
                    $statusBayar   = 'pending';
                } else {
                    $statusPesanan = 'batal';
                    $statusBayar   = $metode === 'qris' ? 'expire' : 'cancel';
                }

                $waktuBayar = null;
                $penggunaId = null;
                if ($statusBayar === 'paid') {
                    $waktuBayar = date('Y-m-d H:i:s', $waktuPesanTs + mt_rand(30, 420));
                    if ($metode === 'tunai' && $countStaf > 0) {
                        $penggunaId = (int) $stafIds[mt_rand(0, $countStaf - 1)];
                    }
                }

                $midtransOid = $metode === 'qris'
                    ? sprintf('TRX-DMY-%d-%d-%d', $seed, $runPrefix, $i)
                    : null;

                $batchData[] = [
                    'meja_id'           => (int) $mejaIds[mt_rand(0, $countMeja - 1)],
                    'nama'              => sprintf('Dummy Pelanggan %06d', $i),
                    'email'             => sprintf('dummy.%d.%d.%d@dummy.test', $seed, $runPrefix, $i),
                    'total_harga'       => $totalHarga,
                    'status_pemesanan'  => $statusPesanan,
                    'waktu_pemesanan'   => $waktuPesan,
                    'lines'             => $lines,
                    'pengguna_id'       => $penggunaId,
                    'midtrans_order_id' => $midtransOid,
                    'waktu_pembayaran'  => $waktuBayar,
                    'status_pembayaran' => $statusBayar,
                    'metode_pembayaran' => $metode,
                ];

                if (count($batchData) >= $batchSize) {
                    $flushBatch($batchData);
                    $batchData = [];
                }
            }

            if ($batchData !== []) {
                $flushBatch($batchData);
            }

            $db->commit();
            $db->exec('ANALYZE pemesanan, detail_pemesanan, pembayaran');

            $durasi = microtime(true) - $mulai;
            echo sprintf(
                "[SEED SELESAI] DB=%s | Berhasil membuat %d pesanan dummy (rentang %d hari, seed=%d) dalam %.2f detik.\n",
                $activeDb,
                $pesanan,
                $hari,
                $seed,
                $durasi
            );
            return 0;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            fwrite(STDERR, "Gagal membuat data dummy: " . $e->getMessage() . "\n");
            return 1;
        }
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(jalankan_cli_seed_dummy($argv ?? []));
}
