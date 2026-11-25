<?php
/**
 * Model laporan penjualan (khusus baca / read-only).
 *
 * Seluruh perhitungan hanya menyertakan pembayaran berstatus 'paid' dengan interval
 * waktu setengah terbuka pada kolom `pembayaran.waktu_pembayaran`:
 *   waktu_pembayaran >= :dari_ts AND waktu_pembayaran < :sampai_excl_ts
 * pada zona waktu Asia/Jakarta (WIB).
 *
 * Perbedaan dasar nominal:
 *   - `total_pendapatan` (ringkasanPeriode, harian, perJam, perMetode):
 *       SUM(pembayaran.total_bayar) -> sudah termasuk pajak 10%.
 *   - `pendapatan_menu` (menuTerlaris, perKategori):
 *       SUM(detail_pemesanan.subtotal) -> omzet kotor item sebelum pajak 10%.
 */
final class LaporanModel
{
    /** Maksimal rentang hari inklusif per kueri laporan. */
    public const MAKS_HARI = 366;

    /** Batas maksimal item pada menuTerlaris(). */
    public const MAKS_LIMIT_MENU = 100;

    /**
     * Ringkasan KPI penjualan pada periode terpilih beserta perbandingan
     * terhadap periode sebelumnya yang panjang harinya sama persis.
     *
     * @return array{
     *   periode: array{dari: string, sampai: string, jumlah_hari: int},
     *   total_pendapatan: int,
     *   jumlah_transaksi: int,
     *   item_terjual: int,
     *   rata_rata_nilai: float,
     *   periode_sebelumnya: array{
     *     dari: string,
     *     sampai: string,
     *     total_pendapatan: int,
     *     jumlah_transaksi: int,
     *     item_terjual: int,
     *     rata_rata_nilai: float
     *   },
     *   perbandingan: array{
     *     total_pendapatan: array{selisih: int, persen: ?float},
     *     jumlah_transaksi: array{selisih: int, persen: ?float},
     *     item_terjual: array{selisih: int, persen: ?float},
     *     rata_rata_nilai: array{selisih: float, persen: ?float}
     *   }
     * }
     * @throws InvalidArgumentException bila tanggal/rentang tidak valid.
     */
    public static function ringkasanPeriode(string $dari, string $sampai): array
    {
        $rentang = self::validasiRentang($dari, $sampai);

        $jumlahHari    = $rentang['jumlah_hari'];
        $sampaiPrevDt  = $rentang['dari_dt']->modify('-1 day');
        $dariPrevDt    = $sampaiPrevDt->modify('-' . ($jumlahHari - 1) . ' days');
        $dariPrev      = $dariPrevDt->format('Y-m-d');
        $sampaiPrev    = $sampaiPrevDt->format('Y-m-d');
        $dariPrevTs    = $dariPrev . ' 00:00:00';
        $sampaiPrevEx  = $rentang['dari_ts']; // eksklusif awal periode sekarang = akhir+1 periode lalu

        $sekarang   = self::agregatRingkasan($rentang['dari_ts'], $rentang['sampai_excl_ts']);
        $sebelumnya = self::agregatRingkasan($dariPrevTs, $sampaiPrevEx);

        return [
            'periode' => [
                'dari'        => $rentang['dari'],
                'sampai'      => $rentang['sampai'],
                'jumlah_hari' => $jumlahHari,
            ],
            'total_pendapatan'   => $sekarang['total_pendapatan'],
            'jumlah_transaksi'   => $sekarang['jumlah_transaksi'],
            'item_terjual'       => $sekarang['item_terjual'],
            'rata_rata_nilai'    => $sekarang['rata_rata_nilai'],
            'periode_sebelumnya' => [
                'dari'             => $dariPrev,
                'sampai'           => $sampaiPrev,
                'total_pendapatan' => $sebelumnya['total_pendapatan'],
                'jumlah_transaksi' => $sebelumnya['jumlah_transaksi'],
                'item_terjual'     => $sebelumnya['item_terjual'],
                'rata_rata_nilai'  => $sebelumnya['rata_rata_nilai'],
            ],
            'perbandingan' => [
                'total_pendapatan' => [
                    'selisih' => $sekarang['total_pendapatan'] - $sebelumnya['total_pendapatan'],
                    'persen'  => self::hitungPersenPerubahan($sekarang['total_pendapatan'], $sebelumnya['total_pendapatan']),
                ],
                'jumlah_transaksi' => [
                    'selisih' => $sekarang['jumlah_transaksi'] - $sebelumnya['jumlah_transaksi'],
                    'persen'  => self::hitungPersenPerubahan($sekarang['jumlah_transaksi'], $sebelumnya['jumlah_transaksi']),
                ],
                'item_terjual' => [
                    'selisih' => $sekarang['item_terjual'] - $sebelumnya['item_terjual'],
                    'persen'  => self::hitungPersenPerubahan($sekarang['item_terjual'], $sebelumnya['item_terjual']),
                ],
                'rata_rata_nilai' => [
                    'selisih' => round($sekarang['rata_rata_nilai'] - $sebelumnya['rata_rata_nilai'], 2),
                    'persen'  => self::hitungPersenPerubahan($sekarang['rata_rata_nilai'], $sebelumnya['rata_rata_nilai']),
                ],
            ],
        ];
    }

    /**
     * Rekap penjualan harian untuk setiap tanggal di dalam rentang [$dari, $sampai].
     * Tanggal tanpa transaksi lunas tetap dikembalikan dengan nilai 0 melalui generate_series().
     *
     * @return array<int, array{tanggal: string, jumlah_transaksi: int, item_terjual: int, total_pendapatan: int}>
     * @throws InvalidArgumentException bila tanggal/rentang tidak valid.
     */
    public static function harian(string $dari, string $sampai): array
    {
        $rentang = self::validasiRentang($dari, $sampai);

        $sql = "
            WITH bayar_harian AS (
                SELECT b.waktu_pembayaran::date AS tgl,
                       COUNT(*)::int AS jumlah_transaksi,
                       COALESCE(SUM(b.total_bayar), 0)::bigint AS total_pendapatan
                  FROM pembayaran b
                 WHERE b.status_pembayaran = 'paid'
                   AND b.waktu_pembayaran >= :dari_ts
                   AND b.waktu_pembayaran < :sampai_excl_ts
                 GROUP BY b.waktu_pembayaran::date
            ),
            item_harian AS (
                SELECT b.waktu_pembayaran::date AS tgl,
                       COALESCE(SUM(d.jumlah), 0)::int AS item_terjual
                  FROM pembayaran b
                  JOIN detail_pemesanan d ON d.pemesanan_id = b.pemesanan_id
                 WHERE b.status_pembayaran = 'paid'
                   AND b.waktu_pembayaran >= :dari_ts
                   AND b.waktu_pembayaran < :sampai_excl_ts
                 GROUP BY b.waktu_pembayaran::date
            )
            SELECT gs::date::text AS tanggal,
                   COALESCE(bh.jumlah_transaksi, 0)::int AS jumlah_transaksi,
                   COALESCE(ih.item_terjual, 0)::int AS item_terjual,
                   COALESCE(bh.total_pendapatan, 0)::bigint AS total_pendapatan
              FROM generate_series(:dari_tgl::date, :sampai_tgl::date, INTERVAL '1 day') AS gs
              LEFT JOIN bayar_harian bh ON bh.tgl = gs::date
              LEFT JOIN item_harian ih  ON ih.tgl = gs::date
             ORDER BY gs ASC
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':dari_ts'        => $rentang['dari_ts'],
            ':sampai_excl_ts' => $rentang['sampai_excl_ts'],
            ':dari_tgl'       => $rentang['dari'],
            ':sampai_tgl'     => $rentang['sampai'],
        ]);

        return array_map(static fn (array $r): array => [
            'tanggal'          => (string) $r['tanggal'],
            'jumlah_transaksi' => (int) $r['jumlah_transaksi'],
            'item_terjual'     => (int) $r['item_terjual'],
            'total_pendapatan' => (int) $r['total_pendapatan'],
        ], $stmt->fetchAll());
    }

    /**
     * Daftar menu terlaris berdasarkan total porsi terjual dan pendapatan menu (sebelum pajak).
     * Peringkat dihitung dengan window function RANK() OVER (ORDER BY total_terjual DESC, pendapatan_menu DESC).
     *
     * @return array<int, array{
     *   menu_id: int,
     *   nama_menu: string,
     *   kategori: string,
     *   total_terjual: int,
     *   pendapatan_menu: int,
     *   peringkat: int
     * }>
     * @throws InvalidArgumentException bila rentang tanggal atau parameter limit/kategoriId tidak valid.
     */
    public static function menuTerlaris(
        string $dari,
        string $sampai,
        int $limit = 10,
        ?int $kategoriId = null
    ): array {
        $rentang = self::validasiRentang($dari, $sampai);

        if ($limit < 1) {
            throw new InvalidArgumentException('Parameter limit minimal harus 1.');
        }
        $limit = min($limit, self::MAKS_LIMIT_MENU);

        if ($kategoriId !== null && $kategoriId <= 0) {
            throw new InvalidArgumentException('Parameter kategoriId harus berupa bilangan bulat positif.');
        }

        $filterKategori = $kategoriId !== null ? 'AND m.kategori_id = :kategori_id' : '';

        $sql = "
            WITH agregat AS (
                SELECT m.id AS menu_id,
                       m.nama_menu,
                       k.nama_kategori AS kategori,
                       SUM(d.jumlah)::int AS total_terjual,
                       SUM(d.subtotal)::bigint AS pendapatan_menu
                  FROM pembayaran b
                  JOIN detail_pemesanan d ON d.pemesanan_id = b.pemesanan_id
                  JOIN menu m             ON m.id = d.menu_id
                  JOIN kategori k         ON k.id = m.kategori_id
                 WHERE b.status_pembayaran = 'paid'
                   AND b.waktu_pembayaran >= :dari_ts
                   AND b.waktu_pembayaran < :sampai_excl_ts
                   {$filterKategori}
                 GROUP BY m.id, m.nama_menu, k.nama_kategori
            ),
            terperingkat AS (
                SELECT menu_id,
                       nama_menu,
                       kategori,
                       total_terjual,
                       pendapatan_menu,
                       RANK() OVER (ORDER BY total_terjual DESC, pendapatan_menu DESC)::int AS peringkat
                  FROM agregat
            )
            SELECT menu_id,
                   nama_menu,
                   kategori,
                   total_terjual,
                   pendapatan_menu,
                   peringkat
              FROM terperingkat
             ORDER BY peringkat ASC, nama_menu ASC
             LIMIT :limit
        ";

        $stmt = db()->prepare($sql);
        $stmt->bindValue(':dari_ts', $rentang['dari_ts'], PDO::PARAM_STR);
        $stmt->bindValue(':sampai_excl_ts', $rentang['sampai_excl_ts'], PDO::PARAM_STR);
        if ($kategoriId !== null) {
            $stmt->bindValue(':kategori_id', $kategoriId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn (array $r): array => [
            'menu_id'         => (int) $r['menu_id'],
            'nama_menu'       => (string) $r['nama_menu'],
            'kategori'        => (string) $r['kategori'],
            'total_terjual'   => (int) $r['total_terjual'],
            'pendapatan_menu' => (int) $r['pendapatan_menu'],
            'peringkat'       => (int) $r['peringkat'],
        ], $stmt->fetchAll());
    }

    /**
     * Distribusi transaksi dan total pendapatan (termasuk pajak) untuk 24 jam penuh (0..23).
     * Jam tanpa transaksi tetap muncul dengan nilai 0.
     *
     * @return array<int, array{jam: int, label: string, jumlah_transaksi: int, total_pendapatan: int}>
     * @throws InvalidArgumentException bila tanggal/rentang tidak valid.
     */
    public static function perJam(string $dari, string $sampai): array
    {
        $rentang = self::validasiRentang($dari, $sampai);

        $sql = "
            WITH bayar_jam AS (
                SELECT EXTRACT(HOUR FROM b.waktu_pembayaran)::int AS jam,
                       COUNT(*)::int AS jumlah_transaksi,
                       COALESCE(SUM(b.total_bayar), 0)::bigint AS total_pendapatan
                  FROM pembayaran b
                 WHERE b.status_pembayaran = 'paid'
                   AND b.waktu_pembayaran >= :dari_ts
                   AND b.waktu_pembayaran < :sampai_excl_ts
                 GROUP BY EXTRACT(HOUR FROM b.waktu_pembayaran)::int
            )
            SELECT gs AS jam,
                   COALESCE(bj.jumlah_transaksi, 0)::int AS jumlah_transaksi,
                   COALESCE(bj.total_pendapatan, 0)::bigint AS total_pendapatan
              FROM generate_series(0, 23) AS gs
              LEFT JOIN bayar_jam bj ON bj.jam = gs
             ORDER BY gs ASC
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':dari_ts'        => $rentang['dari_ts'],
            ':sampai_excl_ts' => $rentang['sampai_excl_ts'],
        ]);

        return array_map(static fn (array $r): array => [
            'jam'              => (int) $r['jam'],
            'label'            => sprintf('%02d:00', (int) $r['jam']),
            'jumlah_transaksi' => (int) $r['jumlah_transaksi'],
            'total_pendapatan' => (int) $r['total_pendapatan'],
        ], $stmt->fetchAll());
    }

    /**
     * Rekap jumlah transaksi dan total pendapatan (termasuk pajak) per metode pembayaran.
     *
     * @return array<int, array{
     *   metode: string,
     *   jumlah_transaksi: int,
     *   total_pendapatan: int,
     *   persen_transaksi: float,
     *   persen_pendapatan: float
     * }>
     * @throws InvalidArgumentException bila tanggal/rentang tidak valid.
     */
    public static function perMetode(string $dari, string $sampai): array
    {
        $rentang = self::validasiRentang($dari, $sampai);

        $sql = "
            SELECT COALESCE(NULLIF(LOWER(TRIM(b.metode_pembayaran)), ''), 'lainnya') AS metode,
                   COUNT(*)::int AS jumlah_transaksi,
                   COALESCE(SUM(b.total_bayar), 0)::bigint AS total_pendapatan
              FROM pembayaran b
             WHERE b.status_pembayaran = 'paid'
               AND b.waktu_pembayaran >= :dari_ts
               AND b.waktu_pembayaran < :sampai_excl_ts
             GROUP BY COALESCE(NULLIF(LOWER(TRIM(b.metode_pembayaran)), ''), 'lainnya')
             ORDER BY total_pendapatan DESC, metode ASC
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':dari_ts'        => $rentang['dari_ts'],
            ':sampai_excl_ts' => $rentang['sampai_excl_ts'],
        ]);
        $rows = $stmt->fetchAll();

        $totTx  = array_sum(array_map(static fn (array $r): int => (int) $r['jumlah_transaksi'], $rows));
        $totRev = array_sum(array_map(static fn (array $r): int => (int) $r['total_pendapatan'], $rows));

        return array_map(static fn (array $r): array => [
            'metode'            => (string) $r['metode'],
            'jumlah_transaksi'  => (int) $r['jumlah_transaksi'],
            'total_pendapatan'  => (int) $r['total_pendapatan'],
            'persen_transaksi'  => $totTx > 0 ? round(((int) $r['jumlah_transaksi'] / $totTx) * 100, 1) : 0.0,
            'persen_pendapatan' => $totRev > 0 ? round(((int) $r['total_pendapatan'] / $totRev) * 100, 1) : 0.0,
        ], $rows);
    }

    /**
     * Rekap item terjual dan pendapatan menu (sebelum pajak) untuk seluruh kategori menu.
     * Kategori yang belum memiliki penjualan tetap tampil dengan nilai 0.
     *
     * @return array<int, array{
     *   kategori_id: int,
     *   kategori: string,
     *   item_terjual: int,
     *   pendapatan_menu: int,
     *   persen_pendapatan: float
     * }>
     * @throws InvalidArgumentException bila tanggal/rentang tidak valid.
     */
    public static function perKategori(string $dari, string $sampai): array
    {
        $rentang = self::validasiRentang($dari, $sampai);

        $sql = "
            WITH kat_terjual AS (
                SELECT m.kategori_id,
                       COALESCE(SUM(d.jumlah), 0)::int AS item_terjual,
                       COALESCE(SUM(d.subtotal), 0)::bigint AS pendapatan_menu
                  FROM pembayaran b
                  JOIN detail_pemesanan d ON d.pemesanan_id = b.pemesanan_id
                  JOIN menu m             ON m.id = d.menu_id
                 WHERE b.status_pembayaran = 'paid'
                   AND b.waktu_pembayaran >= :dari_ts
                   AND b.waktu_pembayaran < :sampai_excl_ts
                 GROUP BY m.kategori_id
            )
            SELECT k.id AS kategori_id,
                   k.nama_kategori AS kategori,
                   COALESCE(kt.item_terjual, 0)::int AS item_terjual,
                   COALESCE(kt.pendapatan_menu, 0)::bigint AS pendapatan_menu
              FROM kategori k
              LEFT JOIN kat_terjual kt ON kt.kategori_id = k.id
             ORDER BY pendapatan_menu DESC, k.nama_kategori ASC
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':dari_ts'        => $rentang['dari_ts'],
            ':sampai_excl_ts' => $rentang['sampai_excl_ts'],
        ]);
        $rows = $stmt->fetchAll();

        $totMenuRev = array_sum(array_map(static fn (array $r): int => (int) $r['pendapatan_menu'], $rows));

        return array_map(static fn (array $r): array => [
            'kategori_id'       => (int) $r['kategori_id'],
            'kategori'          => (string) $r['kategori'],
            'item_terjual'      => (int) $r['item_terjual'],
            'pendapatan_menu'   => (int) $r['pendapatan_menu'],
            'persen_pendapatan' => $totMenuRev > 0 ? round(((int) $r['pendapatan_menu'] / $totMenuRev) * 100, 1) : 0.0,
        ], $rows);
    }

    /**
     * Validasi ketat format Y-m-d (WIB), urutan dari <= sampai, serta batas maksimal 366 hari.
     * Menghasilkan interval setengah terbuka [dari_ts, sampai_excl_ts).
     *
     * @return array{
     *   dari: string,
     *   sampai: string,
     *   dari_dt: DateTimeImmutable,
     *   sampai_dt: DateTimeImmutable,
     *   jumlah_hari: int,
     *   dari_ts: string,
     *   sampai_excl_ts: string
     * }
     * @throws InvalidArgumentException
     */
    private static function validasiRentang(string $dari, string $sampai): array
    {
        $tz = new DateTimeZone('Asia/Jakarta');

        $dariDt = DateTimeImmutable::createFromFormat('!Y-m-d', $dari, $tz);
        if (!$dariDt || $dariDt->format('Y-m-d') !== $dari) {
            throw new InvalidArgumentException("Tanggal mulai tidak valid ('{$dari}'); wajib berformat Y-m-d.");
        }

        $sampaiDt = DateTimeImmutable::createFromFormat('!Y-m-d', $sampai, $tz);
        if (!$sampaiDt || $sampaiDt->format('Y-m-d') !== $sampai) {
            throw new InvalidArgumentException("Tanggal akhir tidak valid ('{$sampai}'); wajib berformat Y-m-d.");
        }

        if ($dariDt > $sampaiDt) {
            throw new InvalidArgumentException("Tanggal mulai ({$dari}) tidak boleh lebih besar dari tanggal akhir ({$sampai}).");
        }

        $jumlahHari = (int) $dariDt->diff($sampaiDt)->days + 1;
        if ($jumlahHari > self::MAKS_HARI) {
            throw new InvalidArgumentException(sprintf(
                'Rentang tanggal terlalu panjang (%d hari); maksimal %d hari.',
                $jumlahHari,
                self::MAKS_HARI
            ));
        }

        return [
            'dari'           => $dari,
            'sampai'         => $sampai,
            'dari_dt'        => $dariDt,
            'sampai_dt'      => $sampaiDt,
            'jumlah_hari'    => $jumlahHari,
            'dari_ts'        => $dari . ' 00:00:00',
            'sampai_excl_ts' => $sampaiDt->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
        ];
    }

    /**
     * Hitung agregat KPI periode tanpa perkalian silang akibat JOIN ke detail_pemesanan.
     *
     * @return array{total_pendapatan: int, jumlah_transaksi: int, item_terjual: int, rata_rata_nilai: float}
     */
    private static function agregatRingkasan(string $dariTs, string $sampaiExclTs): array
    {
        $sql = "
            WITH bayar AS (
                SELECT COUNT(*)::int AS jumlah_transaksi,
                       COALESCE(SUM(b.total_bayar), 0)::bigint AS total_pendapatan
                  FROM pembayaran b
                 WHERE b.status_pembayaran = 'paid'
                   AND b.waktu_pembayaran >= :dari_ts
                   AND b.waktu_pembayaran < :sampai_excl_ts
            ),
            item AS (
                SELECT COALESCE(SUM(d.jumlah), 0)::int AS item_terjual
                  FROM pembayaran b
                  JOIN detail_pemesanan d ON d.pemesanan_id = b.pemesanan_id
                 WHERE b.status_pembayaran = 'paid'
                   AND b.waktu_pembayaran >= :dari_ts
                   AND b.waktu_pembayaran < :sampai_excl_ts
            )
            SELECT bayar.jumlah_transaksi,
                   bayar.total_pendapatan,
                   item.item_terjual
              FROM bayar CROSS JOIN item
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':dari_ts'        => $dariTs,
            ':sampai_excl_ts' => $sampaiExclTs,
        ]);
        $row = $stmt->fetch() ?: [];

        $tx         = (int) ($row['jumlah_transaksi'] ?? 0);
        $pendapatan = (int) ($row['total_pendapatan'] ?? 0);
        $item       = (int) ($row['item_terjual'] ?? 0);

        return [
            'total_pendapatan' => $pendapatan,
            'jumlah_transaksi' => $tx,
            'item_terjual'     => $item,
            'rata_rata_nilai'  => $tx > 0 ? round($pendapatan / $tx, 2) : 0.0,
        ];
    }

    /**
     * Hitung persen perubahan dari periode sebelumnya ke periode saat ini (1 desimal).
     * Aturan nol:
     *   - sebelumnya == 0 dan sekarang == 0 -> 0.0
     *   - sebelumnya == 0 dan sekarang > 0  -> null ("tidak terdefinisi")
     */
    private static function hitungPersenPerubahan(int|float $sekarang, int|float $sebelumnya): ?float
    {
        if ((float) $sebelumnya === 0.0) {
            return (float) $sekarang === 0.0 ? 0.0 : null;
        }
        return round((($sekarang - $sebelumnya) / $sebelumnya) * 100, 1);
    }
}
