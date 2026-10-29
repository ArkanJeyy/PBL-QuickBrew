<?php
/**
 * Model meja (tabel: meja). kode_qr dipakai di URL QR: ?meja=<kode_qr>
 */
final class MejaModel
{
    /** Data meja fallback jika database belum siap (sesuai seed QuickBrew.sql). */
    private const DEFAULT_TABLES = [
        ['id' => 1, 'no_meja' => '1', 'kode_qr' => 'MEJA01'],
        ['id' => 2, 'no_meja' => '2', 'kode_qr' => 'MEJA02'],
        ['id' => 3, 'no_meja' => '3', 'kode_qr' => 'MEJA03'],
        ['id' => 4, 'no_meja' => '4', 'kode_qr' => 'MEJA04'],
        ['id' => 5, 'no_meja' => '5', 'kode_qr' => 'MEJA05'],
    ];

    /** Semua daftar meja (dari DB atau fallback jika DB belum terkoneksi). */
    public static function all(): array
    {
        try {
            $rows = db()->query("SELECT id, no_meja, kode_qr FROM meja ORDER BY id")->fetchAll();
            return $rows ?: self::DEFAULT_TABLES;
        } catch (Throwable) {
            return self::DEFAULT_TABLES;
        }
    }

    /**
     * Cari meja berdasarkan kode_qr (mis. MEJA01) atau no_meja (mis. 1).
     * Bila DB belum aktif, otomatis menggunakan daftar meja fallback untuk testing.
     */
    public static function findByKode(string $kode): ?array
    {
        $kode = trim($kode);
        if ($kode === '') return null;

        try {
            $s = db()->prepare("SELECT id, no_meja, kode_qr FROM meja WHERE UPPER(kode_qr) = UPPER(?) OR no_meja = ? LIMIT 1");
            $s->execute([$kode, $kode]);
            $row = $s->fetch();
            if ($row) return $row;
        } catch (Throwable) {
            // Gunakan fallback di bawah bila DB belum siap
        }

        foreach (self::DEFAULT_TABLES as $t) {
            if (strcasecmp($t['kode_qr'], $kode) === 0 || $t['no_meja'] === $kode) {
                return $t;
            }
        }
        return null;
    }
}


