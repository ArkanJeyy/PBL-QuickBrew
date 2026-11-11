-- =====================================================================
-- Migrasi 002: Tabel pembatasan_laju (rate limiting)
-- Menyimpan hit percobaan per kunci (mis. checkout per IP, login, status-json)
-- beserta cap waktu untuk pembatasan laju permintaan.
-- Aman dijalankan berulang (idempotent) pada PostgreSQL 11+.
-- =====================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS pembatasan_laju (
    id    SERIAL PRIMARY KEY,
    kunci VARCHAR(120) NOT NULL,
    waktu TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_pembatasan_laju_kunci_waktu
    ON pembatasan_laju (kunci, waktu);

COMMIT;
