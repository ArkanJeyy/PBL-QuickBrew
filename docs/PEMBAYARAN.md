# Dokumentasi Integrasi Pembayaran QuickBrew

Dokumen ini menjelaskan arsitektur pembayaran sisi pelanggan (**QRIS Midtrans Core API** dan **Tunai**), mekanisme **Webhook**, prosedur pengujian lokal dengan **ngrok** & **Simulator Sandbox Midtrans**, serta kontrak metode pada `PembayaranService` yang siap dipakai oleh pengembang **Dashboard Kasir**.

---

## 1. Alur Pembayaran Pelanggan

QuickBrew hanya menyediakan **dua metode pembayaran**: **QRIS** dan **Tunai**.

### 1.1. Metode QRIS (Midtrans Core API)
1. **Checkout (`POST ?page=checkout`)**
   - Pelanggan memindai QR meja (`?meja=MEJA01`), memilih menu, lalu mengirim formulir Checkout dengan metode `qris`.
   - `PemesananModel::price()` menghitung ulang seluruh harga satuan (termasuk ukuran & *add-on*), memeriksa ketersediaan stok dari database, dan menghitung **Pajak 10%**. Harga yang dikirim dari *browser* tidak pernah dipercayai.
   - `PemesananModel::create()` menyimpan baris ke `pemesanan` (`status_pemesanan = 'menunggu_pembayaran'`, `catatan` umum disimpan langsung di `pemesanan.catatan`), `detail_pemesanan`, dan `pembayaran` (`status_pembayaran = 'pending'`, `metode_pembayaran = 'qris'`) dalam satu transaksi database, lalu menutup transaksi tersebut (`COMMIT`).
   - **Di luar transaksi database**, `PelangganController::checkout()` memanggil `PembayaranService::buatQris()` yang menembak endpoint Midtrans Core API `POST /v2/charge` (`payment_type: "qris"`, masa berlaku **15 menit**, item `PAJAK` dikirim sebagai baris `item_details` tersendiri sehingga `SUM(item_details) == gross_amount`).
   - **Jika sukses:** `midtrans_order_id` (`TRX-{id}-{timestamp}`) dan `qris_data` (`{"qr_string":"...","qr_url":"..."}`) disimpan ke tabel `pembayaran`, penanda `$_SESSION['pesanan_baru']` diset agar keranjang dikosongkan satu kali, lalu pelanggan diarahkan ke halaman Pembayaran (`?page=pembayaran&id=<id>&t=<token>`).
   - **Jika gagal:** pesanan ditandai `status_pemesanan = 'batal'` dan `status_pembayaran = 'failure'`, kesalahan dicatat ke `error_log` tanpa data sensitif, pesan *"Pembayaran QRIS sedang bermasalah, coba lagi atau pilih Tunai"* ditampilkan pada formulir Checkout, dan **keranjang pelanggan tidak dikosongkan**.
2. **Halaman Pembayaran (`GET ?page=pembayaran&id=<id>&t=<token>`)**
   - Merender kode QR secara *offline* sebagai **SVG inline** dari `qr_string` melalui `QrSvg::svg()`, lengkap dengan tautan **Simpan QR** berbasis *data URI* (`.svg`). Apabila pembuatan SVG gagal, tampilan otomatis beralih ke elemen `<img class="pay-qr">` memakai `qr_url` cadangan dari `actions[]` Midtrans.
   - **Cadangan Webhook (*Fallback Sync*):** selama pesanan QRIS masih `menunggu_pembayaran`, membuka atau memuat ulang halaman Pembayaran (tombol *"Lihat Status Pembayaran"*) akan memanggil `PembayaranService::sinkron()` di dalam blok `try/catch` dengan **throttle maksimal 1 kali per 5 detik per sesi** (`$_SESSION['qris_sync_last_at']`).
3. **Pelunasan Otomatis via Webhook (`POST /public/notify.php`)**
   - Saat pelanggan menyelesaikan pembayaran QRIS, Midtrans mengirim HTTP Notification ke `/public/notify.php`.
   - `WebhookController` memvalidasi input dan mendelegasikan verifikasi signature serta pembaruan database ke `PembayaranService`.
   - Pesanan yang lunas **langsung berstatus `diproses`** (bukan `dibayar`) agar segera masuk antrean pembuatan pesanan, status pembayaran menjadi `paid` beserta `waktu_pembayaran`, dan stok menu dikurangi secara atomik.

### 1.2. Metode Tunai (Bayar di Kasir)
1. Pelanggan memilih metode `tunai` saat Checkout.
2. Sistem **tidak memanggil Midtrans** sama sekali. Pesanan disimpan dengan `status_pemesanan = 'menunggu_pembayaran'` dan `pembayaran.status_pembayaran = 'pending'`, `metode_pembayaran = 'tunai'`.
3. Halaman Pembayaran menampilkan nomor pesanan terformat (mis. `#A0012` dari `PemesananModel::displayNumber()`) beserta total tagihan untuk ditunjukkan pelanggan kepada kasir.
4. Pesanan tetap berstatus `menunggu_pembayaran` hingga kasir menekan tombol konfirmasi bayar di Dashboard Kasir (yang memanggil `PembayaranService::tandaiLunas()`).

---

## 2. Pemetaan Status Midtrans ke Database

Pencarian pesanan hanya dilakukan melalui pencocokan eksak `pembayaran.midtrans_order_id`, disertai pencocokan numerik `gross_amount` terhadap `pembayaran.total_bayar`, serta hanya diproses bila `pemesanan.status_pemesanan` masih `'menunggu_pembayaran'` (*idempotent* dengan `SELECT ... FOR UPDATE`).

| `transaction_status` Midtrans | `fraud_status` | `pemesanan.status_pemesanan` | `pembayaran.status_pembayaran` | Efek ke `menu.stok` & Catatan |
|---|---|---|---|---|
| `settlement` | *(apa pun)* | **`diproses`** | **`paid`** (+ isi `waktu_pembayaran`) | Stok dikurangi per menu (`FOR UPDATE`); bila stok kurang, diset `0` + dicatat ke `error_log` |
| `capture` | `accept` | **`diproses`** | **`paid`** (+ isi `waktu_pembayaran`) | Sama seperti `settlement` (melalui `tandaiLunas()`) |
| `capture` | `challenge` | *(tetap `menunggu_pembayaran`)* | *(tetap `pending`)* | Tidak ada perubahan; dicatat ke `error_log` |
| `pending` | — | *(tetap `menunggu_pembayaran`)* | `pending` (catat `metode_pembayaran`) | Stok belum dikurangi |
| `deny` | — | **`batal`** | **`deny`** | Stok tidak berubah |
| `cancel` | — | **`batal`** | **`cancel`** | Stok tidak berubah |
| `expire` | — | **`batal`** | **`expire`** | Stok tidak berubah (otomatis setelah 15 menit) |
| `failure` | — | **`batal`** | **`failure`** | Stok tidak berubah |

> **Catatan skema:** Nilai `'dibayar'` tetap dipertahankan pada constraint `CHECK` tabel `pemesanan` demi kompatibilitas data lama, tetapi kode baru langsung mengubah pesanan lunas dari `'menunggu_pembayaran'` ke `'diproses'`.

---

## 3. Tabel Kode HTTP Endpoint Webhook (`/public/notify.php`)

Endpoint webhook resmi hanya satu: **`POST /public/notify.php`**.

| Kondisi Request / Pemrosesan | Kode HTTP | Dicatat ke `error_log`? | Keterangan |
|---|---|---|---|
| Payload kosong, bukan JSON valid, atau field wajib kurang (`order_id`, `status_code`, `gross_amount`, `signature_key`, `transaction_status`) | **400 Bad Request** | Ya | Permintaan cacat ditolak lebih awal |
| `signature_key` tidak cocok dengan `SHA512(order_id + status_code + gross_amount + server_key)` | **403 Forbidden** | Ya (tanpa key/hash) | Mencegah pemalsuan notifikasi; `gross_amount` wajib string asli (mis. `"26400.00"`) |
| `midtrans_order_id` tidak ditemukan di tabel `pembayaran` | **200 OK** | Ya | Menghentikan retry berulang dari Midtrans untuk transaksi asing |
| Nilai numerik `gross_amount` berbeda dari `pembayaran.total_bayar` | **200 OK** | Ya | Tidak memproses pelunasan, tetap balas 200 agar tidak di-retry |
| Pesanan sudah diproses sebelumnya (`status_pemesanan != 'menunggu_pembayaran'`) | **200 OK** | Tidak | Idempotent; aman terhadap pengiriman webhook ganda / urutan terlambat |
| Pembaruan status (`settlement`, `pending`, `expire`, dst.) berhasil | **200 OK** | Tidak (kecuali stok kurang) | Alur normal selesai |
| Exception tak terduga (mis. gangguan koneksi / query database) | **500 Internal Server Error** | Ya (tanpa data sensitif) | Transaksi di-*rollback*; kode 500 meminta Midtrans menjadwalkan *retry* |

---

## 4. Konfigurasi Lokal dengan `ngrok` & Notification URL

Agar server Midtrans Sandbox dapat mengirim webhook ke lingkungan Laragon lokal:

1. Pastikan konfigurasi di `config/.env` sudah berisi kredensial Sandbox (lihat `config/.env.example`, `MIDTRANS_PROD=false`).
2. Jalankan Laragon (Apache/Nginx + PostgreSQL) dan pastikan proyek dapat diakses, misalnya di `http://localhost/PBL/PBL-QuickBrew/public/`.
3. Buka terminal baru dan jalankan `ngrok` mengarah ke port web server lokal (umumnya port `80`):
   ```bash
   ngrok http 80
   ```
4. Salin URL HTTPS dari `ngrok` (contoh: `https://a1b2-103-0-0-1.ngrok-free.app`).
5. Buka **Midtrans Dashboard (Sandbox)** -> **Settings** -> **Payment** -> **Notification URL**, lalu isi:
   ```text
   https://<subdomain-ngrok>/PBL/PBL-QuickBrew/public/notify.php
   ```
   *(Sesuaikan `/PBL/PBL-QuickBrew` dengan path folder proyek di dalam `www` Laragon Anda.)*

> **Tips SSL di Windows/Laragon:** `PembayaranService` selalu mengaktifkan `CURLOPT_SSL_VERIFYPEER = true`. Jika muncul `cURL error #60: SSL certificate problem`, unduh berkas `cacert.pem` resmi dari `curl.se/ca/cacert.pem` lalu arahkan `curl.cainfo = "C:/laragon/etc/ssl/cacert.pem"` pada `php.ini` Laragon.

> **Penting saat Demo via `ngrok` (`TRUSTED_PROXY`):** Karena seluruh trafik `ngrok` diteruskan ke web server lokal dari `127.0.0.1`, set `TRUSTED_PROXY=true` di `config/.env` saat demo menggunakan `ngrok`. Dengan `TRUSTED_PROXY=true`, fungsi `ip_klien()` akan membaca IP asli pengunjung dari entri **paling kanan** `X-Forwarded-For` (yang ditambahkan oleh proxy `ngrok`), sehingga seluruh pengunjung demo tidak berbagi satu kuota pembatasan laju (*rate limit*) yang sama. Batas percobaan checkout per IP (default `10` per 10 menit) juga dapat disesuaikan lewat variabel env `CHECKOUT_RATE_MAX`.

---

## 5. Cara Menguji dengan Simulator QRIS Sandbox Midtrans

1. Buka halaman pengujian QR meja di browser: `http://localhost/PBL/PBL-QuickBrew/public/?page=qr`, lalu klik **Simulasi Scan -> Menu** pada salah satu meja.
2. Tambahkan beberapa item menu ke keranjang, buka **Checkout**, isi nama & email, pilih metode **QRIS**, lalu konfirmasi pesanan.
3. Pada halaman **Pembayaran QRIS**, kode QR akan tampil dalam bentuk SVG. Salin URL gambar cadangan atau pindai / unggah kode QR ke halaman simulator resmi Midtrans:
   - Buka: `https://simulator.sandbox.midtrans.com/v2/qris/index`
   - Anda juga dapat menyalin `qr_string` dari kolom `pembayaran.qris_data` (atau memindai QR di layar) ke simulator QRIS/GoPay Sandbox, lalu klik **Inquire** -> **Pay**.
4. Jika `ngrok` aktif dan Notification URL sudah benar:
   - Midtrans akan memanggil `POST /public/notify.php` dengan `transaction_status: "settlement"`.
   - Klik tombol **Lihat Status Pembayaran** di halaman pelanggan -> badge berubah menjadi **Sudah Dibayar** (*Pembayaran Diterima*), `pemesanan.status_pemesanan` berubah menjadi `'diproses'`, dan `menu.stok` berkurang sesuai jumlah item.
5. Jika `ngrok` sedang tidak aktif:
   - Klik tombol **Lihat Status Pembayaran** setelah jeda minimal 5 detik -> mekanisme cadangan `PembayaranService::sinkron()` akan menarik status `settlement` langsung dari `GET /v2/{order_id}/status` dan melunasi pesanan secara identik.

### 5.1. Mode Demo: Tombol Simulasi Pembayaran Sandbox (`ENABLE_PAYMENT_SIMULATION`)

Untuk keperluan demonstrasi di lingkungan Sandbox, tersedia fitur simulasi pembayaran satu klik yang **secara default nonaktif** demi keamanan:

- **Syarat Aktif:** Fitur simulasi **hanya aktif** apabila di `config/.env` diset `ENABLE_PAYMENT_SIMULATION=true` (tanpa komentar di belakang nilai) **DAN** `MIDTRANS_PROD=false`. Jika salah satu tidak terpenuhi, banner dan tombol simulasi tidak ditampilkan, serta request `POST` simulasi ditolak (`HTTP 403`) tanpa mengubah status pesanan.
- **Tampilan Halaman Pembayaran:** Saat simulasi aktif, halaman pembayaran menampilkan banner `"MODE DEMO: simulasi pembayaran aktif"` beserta tombol **Simulasikan Pembayaran Berhasil (Sandbox)**.
- **Alur Eksekusi (`PembayaranService::simulasiQrisSandbox()`):**
  1. **Jalur Utama (Simulator Midtrans Asli):** Mengirim `qr_url` ke `https://simulator.sandbox.midtrans.com/v2/qris/payment` lalu menyelesaikan pembayaran via `/v2/qris/payment/gopay`, kemudian memanggil `sinkron()` agar alur status Midtrans ikut teruji. Mencatat log `[SIMULASI] pemesanan_id=<id> jalur=simulator`.
  2. **Simulator Terjangkau tetapi Tidak Melunasi:** Tidak melakukan fallback lokal dan mengembalikan `false` (status pesanan tetap `menunggu_pembayaran`).
  3. **Fallback Lokal (Hanya Bila Simulator Tidak Terjangkau):** Jika simulator Midtrans gagal dihubungi (error koneksi cURL / HTTP 5xx), pesanan dilunasi secara lokal melalui `tandaiLunas()` dengan catatan log `[SIMULASI LOKAL]` dan `[SIMULASI] pemesanan_id=<id> jalur=lokal`.

---

## 6. Kontrak `PembayaranService` untuk Pengembang Dashboard Kasir

File: `app/Services/PembayaranService.php`

### 6.1. `tandaiLunas(int $pemesananId, string $metode, ?int $penggunaId = null): bool`
**Fungsi utama yang wajib dipanggil saat kasir menekan tombol "Bayar / Konfirmasi Tunai"** pada pesanan berstatus `menunggu_pembayaran`.

- **Parameter:**
  - `$pemesananId` (`int`): `pemesanan.id` yang akan dilunasi.
  - `$metode` (`string`): metode pembayaran, isi `'tunai'` untuk konfirmasi pembayaran tunai oleh kasir (atau `'qris'` dari alur Midtrans).
  - `$penggunaId` (`?int`): `pengguna.id` milik akun kasir yang sedang login; otomatis disimpan ke kolom `pembayaran.pengguna_id`. Boleh `null` untuk pelunasan otomatis sistem/webhook.
- **Perilaku dalam 1 transaksi atomik:**
  1. Mengunci baris `pemesanan` dan `pembayaran` dengan `SELECT ... FOR UPDATE`.
  2. Jika pesanan tidak ada atau `status_pemesanan !== 'menunggu_pembayaran'`, mengembalikan `false` tanpa mengubah data apa pun (*idempotent*, aman dari klik ganda).
  3. Mengunci baris `menu` terkait (`FOR UPDATE` berurutan menurut `menu_id`) dan mengurangi `stok`. Bila stok kurang dari jumlah pesanan, `stok` diset ke `0` dan dicatat ke `error_log` agar tidak melanggar `CHECK (stok >= 0)`.
  4. Mengubah `pemesanan.status_pemesanan = 'diproses'`.
  5. Mengubah `pembayaran.status_pembayaran = 'paid'`, mengisi `waktu_pembayaran = CURRENT_TIMESTAMP`, `metode_pembayaran`, dan `pengguna_id`.
- **Nilai kembali (`bool`):** `true` jika pelunasan baru saja berhasil diterapkan; `false` jika pesanan sudah diproses/dibatalkan sebelumnya.
- **Contoh pemakaian di Controller Kasir:**
  ```php
  require_once APP_ROOT . '/app/Services/PembayaranService.php';

  $service  = new PembayaranService();
  $berhasil = $service->tandaiLunas($pemesananId, 'tunai', (int) $_SESSION['pengguna']['id']);
  ```

### 6.2. `sinkron(string $midtrans_order_id): ?array`
Dapat dipakai tombol *"Cek Status Midtrans"* di sisi kasir apabila kasir ingin memaksa pengecekan manual untuk pesanan QRIS yang masih menggantung.
- Mengembalikan `array` respons status Midtrans dan otomatis menerapkan perubahan ke database lewat `terapkanStatus()` / `tandaiLunas()`.
- Mengembalikan `null` bila transaksi belum terdaftar di Midtrans (`404`).
- Melempar `RuntimeException` bila terjadi gangguan jaringan/API.

### 6.3. `buatQris(array $pesanan, array $detail): array`
Digunakan saat pembuatan transaksi QRIS pelanggan (`POST /v2/charge`).
- **Input `$pesanan`:** `['id' => int, 'total_harga' => int, 'pajak' => int, 'nama_pelanggan' => string, 'email_pelanggan' => string]`.
- **Input `$detail`:** daftar baris `['menu_id' => int, 'nama_menu' => string, 'harga' => int, 'jumlah' => int, 'catatan' => ?string]`.
- **Output:** `['midtrans_order_id' => string, 'transaction_id' => string, 'qr_string' => string, 'qr_url' => string, 'expiry_time' => string]`.

---

## 7. Menjalankan Pengujian Otomatis

Seluruh pengujian otomatis berjalan melalui CLI menggunakan database khusus **`quickbrew_test`** dan *stub* transport HTTP (tanpa menyentuh database development `QuickBrew` dan tanpa memanggil API Midtrans sungguhan):

```bash
php tests/qr_svg_test.php
php tests/pembayaran_test.php
php tests/simulasi_pembayaran_test.php
```

