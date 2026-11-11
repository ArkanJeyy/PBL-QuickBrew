# Daftar Periksa Deployment Produksi (Deploy Checklist) — QuickBrew

Dokumen ini merangkum langkah-langkah konfigurasi, pengerasan keamanan, dan pemeliharaan yang **wajib** diperiksa sebelum menjalankan QuickBrew di server produksi maupun saat menyiapkan lingkungan demo Sandbox.

---

## 1. Variabel Lingkungan (`config/.env`)

Pastikan berkas `config/.env` telah dibuat dari `config/.env.example` dan disesuaikan:

| Variabel | Nilai Produksi | Nilai Demo Sandbox | Keterangan |
|---|---|---|---|
| `APP_ENV` | `production` | `development` | Di `production`: menyembunyikan detail galat PHP (`display_errors=0`), merender halaman 500 generik mandiri, dan **memblokir login staf yang masih memakai password bawaan `"password"`**. Untuk demo Sandbox yang masih memakai akun bawaan seed, gunakan `APP_ENV=development`. |
| `APP_SECRET` | String acak >= 32 byte | String acak >= 32 byte | Kunci rahasia HMAC untuk token akses pesanan (`tok()`). Hasilkan dengan `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. |
| `MIDTRANS_PROD` | `true` | `false` | Menentukan endpoint Core API Midtrans (`api.midtrans.com` vs `api.sandbox.midtrans.com`). |
| `MIDTRANS_SERVER_KEY` | `Mid-server-...` | `SB-Mid-server-...` | Server Key Midtrans. Jangan pernah ditulis di kode atau di-commit ke Git. |
| `MIDTRANS_CLIENT_KEY` | `Mid-client-...` | `SB-Mid-client-...` | Client Key Midtrans. |
| `ENABLE_PAYMENT_SIMULATION` | `false` | `true` *(opsional demo)* | **Wajib `false` di produksi.** Simulasi pembayaran juga otomatis nonaktif bila `MIDTRANS_PROD=true`. |
| `TRUSTED_PROXY` | `true` *(jika di balik reverse proxy)* / `false` | `true` *(jika lewat ngrok)* | Bila `true`, `ip_klien()` membaca IP **paling kanan** dari `X-Forwarded-For` dan deteksi HTTPS membaca `X-Forwarded-Proto`. **Penting saat demo lewat ngrok:** set `TRUSTED_PROXY=true` agar semua pengunjung tidak berbagi satu kuota pembatasan laju (`127.0.0.1`). |
| `TUNAI_EXPIRY_MINUTES` | `60` *(atau sesuai kebijakan toko)* | `60` | Batas waktu (menit) pesanan Tunai berstatus `menunggu_pembayaran` sebelum dibatalkan otomatis oleh `bin/kedaluwarsa.php`. |
| `CHECKOUT_RATE_MAX` | `10` | `10` | Batas maksimal percobaan checkout per IP dalam jendela 10 menit. |

---

## 2. Keamanan Akun Staf & Kode QR Meja

1. **Ganti Password Bawaan Seluruh Staf (`kasir` & `admin`):**
   Data awal (`SQL/QuickBrew.sql`) menyertakan password contoh `"password"`. Saat `APP_ENV=production`, sistem **menolak** login menggunakan password literal `"password"`.
   Jalankan skrip CLI berikut untuk setiap akun staf (password diketik tersembunyi, minimal 10 karakter):
   ```bash
   php bin/ganti_password.php admin
   php bin/ganti_password.php kasir
   ```
   > **Catatan Demo Sandbox:** Jika saat demo Sandbox Anda belum mengganti password bawaan seed, biarkan `APP_ENV=development` (blokir password default hanya berlaku saat `APP_ENV=production`).

2. **Acak Kode QR Meja (`meja.kode_qr`):**
   Kode bawaan seperti `MEJA01`, `MEJA02` mudah ditebak pengunjung dari luar kafe.
   - Tinjau rencana pengacakan (mode *dry-run*, tanpa mengubah database):
     ```bash
     php bin/acak_kode_qr.php
     ```
   - Terapkan kode QR acak 16 karakter hex (`bin2hex(random_bytes(8))`):
     ```bash
     php bin/acak_kode_qr.php --jalankan
     ```
   - **Peringatan:** Setelah `--jalankan` dieksekusi, kode QR cetak lama tidak berlaku lagi. Cetak ulang QR seluruh meja dari daftar URL baru atau halaman `?page=qr`.

---

## 3. Isolasi Web Root, `.env`, dan Aturan Server Web

1. **Arahkan `DocumentRoot` ke Direktori `public/`:**
   Konfigurasikan VirtualHost Apache atau blok `server` Nginx agar `DocumentRoot` / `root` langsung menunjuk ke folder `public/`. Dengan demikian folder `config/`, `app/`, `SQL/`, `bin/`, dan `tests/` berada **di luar web root** dan tidak dapat diakses langsung lewat HTTP.

2. **Aturan Nginx (Setara `.htaccess`):**
   Jika root proyek terpaksa berada di dalam folder yang terjangkau server web, blokir akses ke berkas sensitif di konfigurasi Nginx:
   ```nginx
   # Tolak akses ke seluruh dotfile (.env, .git, .htaccess)
   location ~ /\.(?!well-known).* {
       deny all;
   }

   # Tolak akses langsung ke direktori internal aplikasi
   location ~ ^/(config|app|SQL|bin|tests|docs)/ {
       deny all;
   }
   ```

---

## 4. HTTPS, Cookie Sesi `Secure`, dan Header Keamanan

1. **Wajib HTTPS di Produksi:**
   - Pasang sertifikat TLS (mis. Let's Encrypt / Cloudflare).
   - `config/config.php` secara otomatis mengaktifkan atribut cookie sesi `Secure`, `HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`, dan `use_only_cookies=1` ketika koneksi terdeteksi HTTPS (termasuk melalui `X-Forwarded-Proto: https` saat `TRUSTED_PROXY=true`).
2. **Header Keamanan HTTP:**
   - Fungsi `kirim_header_keamanan()` otomatis mengirim `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, dan `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
   - **Rekomendasi Tambahan (`Content-Security-Policy`):** Setelah seluruh aset front-end tidak lagi bergantung pada inline script/style bebas, tambahkan header `Content-Security-Policy` di level reverse proxy atau di `kirim_header_keamanan()`.

---

## 5. Penjadwalan Pembersih Pesanan Kedaluwarsa (`bin/kedaluwarsa.php`)

Pastikan skrip `bin/kedaluwarsa.php` dijadwalkan berjalan setiap **5 menit** agar pesanan QRIS (> 20 menit) dan Tunai (> `TUNAI_EXPIRY_MINUTES`) yang ditinggalkan pelanggan tidak menggantung selamanya:

- **Linux (Cron):**
  ```cron
  */5 * * * * /usr/bin/php /var/www/quickbrew/bin/kedaluwarsa.php >> /var/log/quickbrew-kedaluwarsa.log 2>&1
  ```
- **Windows (Task Scheduler):**
  ```bat
  schtasks /Create /SC MINUTE /MO 5 /TN "QuickBrew_Kedaluwarsa" /TR "\"C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe\" \"C:\laragon\www\PBL\PBL-QuickBrew\bin\kedaluwarsa.php\"" /F
  ```

---

## 6. Backup Database PostgreSQL (`pg_dump`)

Jadwalkan pencadangan harian database PostgreSQL dengan format *custom* terkompresi (`-Fc`) dan simpan di lokasi terpisah dari direktori publik web:

```bash
# Contoh backup harian PostgreSQL
pg_dump -U postgres -h localhost -p 5432 -Fc QuickBrew -f /var/backups/quickbrew/quickbrew_$(date +\%Y\%m\%d_\%H\%M).dump

# Contoh pemulihan (restore) bila diperlukan
pg_restore -U postgres -h localhost -p 5432 -d QuickBrew --clean /var/backups/quickbrew/quickbrew_YYYYMMDD_HHMM.dump
```
