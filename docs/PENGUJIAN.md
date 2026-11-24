# Panduan Pengujian Otomatis (Lokal & CI) — QuickBrew

Dokumen ini menjelaskan cara menjalankan seluruh rangkaian pengujian (*test suite*) QuickBrew secara lokal maupun di **GitHub Actions CI**.

---

## 1. Prinsip Isolasi & Keamanan Pengujian

1. **Hanya berjalan di database `quickbrew_test`:** Seluruh berkas `tests/*_test.php` dan *runner* [tests/jalankan_semua.php](../tests/jalankan_semua.php) menolak berjalan apabila `DB_NAME` atau koneksi aktif `SELECT current_database()` bukan `quickbrew_test`. Database development (`QuickBrew`) tidak pernah disentuh.
2. **Tanpa jaringan keluar (*offline / stubbed*):** Seluruh panggilan ke Midtrans Core API dan Simulator Sandbox menggunakan *transport stub* lokal dan kredensial palsu (`DUMMY-...`), sehingga pengujian berjalan deterministik di lokal maupun CI.

---

## 2. Menjalankan Tes Secara Lokal

Pastikan PostgreSQL lokal sudah menyala dan database `quickbrew_test` telah tersedia (atau dibuat lewat *user* `postgres`).

### Menjalankan Seluruh Suite Sekaligus (Direkomendasikan)

- **Windows PowerShell:**
  ```powershell
  $env:DB_NAME = "quickbrew_test"
  php tests/jalankan_semua.php
  ```
- **Linux / macOS / Git Bash:**
  ```bash
  DB_NAME=quickbrew_test php tests/jalankan_semua.php
  ```

Jika `DB_NAME` tidak diset ke `quickbrew_test` (misalnya masih mengarah ke `QuickBrew`), skrip otomatis berhenti dengan *exit code* `1`.

### Menjalankan Satu Berkas Pengujian Spesifik

```bash
php tests/qr_svg_test.php
php tests/pembayaran_test.php
php tests/simulasi_pembayaran_test.php
php tests/hardening_keamanan_test.php
php tests/auth_staf_test.php
php tests/kasir_model_test.php
php tests/status_json_test.php
php tests/kedaluwarsa_test.php
php tests/cli_keamanan_test.php
```

---

## 3. Pengujian Otomatis di GitHub Actions (CI)

Konfigurasi alur CI berada di [.github/workflows/ci.yml](../.github/workflows/ci.yml) dan berjalan otomatis pada setiap `push` maupun `pull_request` ke branch `main`:

- **Matriks PHP:** `8.1` dan `8.3` *(ekstensi wajib: `pdo_pgsql`, `curl`, `mbstring`; fitur bahasa minimum memakai PHP 8.0/8.1+ seperti `str_contains`, `str_starts_with`)*.
- **Basis Data Service:** `postgres:16` dengan `healthcheck` (`pg_isready`) dan database `quickbrew_test`.
- **Variabel Lingkungan CI:** Menggunakan nilai palsu khusus CI (tanpa GitHub Secrets dan tanpa mengakses `config/.env`).
- **Tahapan CI:**
  1. Pemeriksaan sintaks (`php -l`) pada seluruh berkas `*.php`.
  2. Inisialisasi database `quickbrew_test` dan pemuatan skema `SQL/QuickBrew.sql`.
  3. Eksekusi `php tests/jalankan_semua.php` yang menjalankan setiap `tests/*_test.php` di subproses PHP terpisah.

Semua 9 berkas pengujian di `tests/*_test.php` berjalan penuh di CI tanpa ada yang di-*skip*.

---

## 4. Catatan Penyesuaian Tes pada `origin/main`

Untuk memastikan seluruh suite pengujian selaras dengan perubahan fitur pada `main` (tanpa mengubah kode aplikasi):
- `tests/auth_staf_test.php`: assertion redirect default staf (`kasir` & `admin`) disesuaikan dari `url('home')` menjadi `url('kasir')` mengikuti perilaku `AuthController::login()`.
- `tests/hardening_keamanan_test.php`: pemanggilan `helpers.php` di subproses diubah menjadi `require_once` (karena `config/config.php` sudah memuatnya), serta sesi `pesanan_saya` dibersihkan tiap iterasi uji *rate limit* & *idempotency* agar tidak terbentur validasi *"pesanan sebelumnya di sesi ini harus dibayar dulu"*.
- `tests/pembayaran_test.php`: sesi `pesanan_saya` dibersihkan antar skenario checkout independen (Tunai, dua pesanan identik, dan QRIS gagal).
