-- =====================================================================
-- Migrasi 001: Tambah kolom qris_data pada tabel pembayaran
-- Menyimpan JSON payload QRIS Midtrans Core API:
--   {"qr_string":"...","qr_url":"..."}
-- Aman dijalankan berulang (idempotent) pada PostgreSQL 11+.
-- =====================================================================

BEGIN;

ALTER TABLE pembayaran
    ADD COLUMN IF NOT EXISTS qris_data TEXT NULL;

COMMIT;
