-- =====================================================================
--  Migrasi 003: Indeks Parsial Covering untuk Kueri Laporan Penjualan
--  (Aman dijalankan berulang kali / idempoten)
-- =====================================================================

BEGIN;

-- Indeks parsial + INCLUDE agar agregasi LaporanModel (ringkasanPeriode,
-- harian, menuTerlaris, perJam, perMetode, perKategori) pada baris paid
-- dapat dieksekusi lewat Index Only Scan tanpa menyentuh heap tabel pembayaran.
CREATE INDEX IF NOT EXISTS idx_pembayaran_paid_waktu
    ON pembayaran (waktu_pembayaran)
    INCLUDE (pemesanan_id, total_bayar, metode_pembayaran)
    WHERE status_pembayaran = 'paid';

COMMIT;
