# ☕ Quick Brew: Sistem Quick Order Berbasis Scan QR Code

> Proyek *Project Based Learning* (PBL) — Kelompok 5, Kelas SIB 2E
> Program Studi D-IV Sistem Informasi Bisnis, Jurusan Teknologi Informasi, Politeknik Negeri Malang (September 2026)

**Quick Brew** adalah sistem pemesanan berbasis web (*quick order*) untuk meningkatkan efisiensi operasional coffee shop saat jam sibuk (*peak hours*). Pelanggan cukup memindai QR Code unik di meja, tanpa perlu mengunduh aplikasi atau registrasi, lalu memilih menu, menyesuaikan variasi minuman, dan melakukan *checkout* secara mandiri. Pesanan akan tampil secara *real-time* di dashboard kasir dan barista.

---

## 📑 Daftar Isi

- [Latar Belakang](#-latar-belakang)
- [Rumusan Masalah](#-rumusan-masalah)
- [Tujuan Proyek](#-tujuan-proyek)
- [Gambaran Solusi & Alur Sistem](#-gambaran-solusi--alur-sistem)
- [Peran Pengguna](#-peran-pengguna)
- [Fitur Utama](#-fitur-utama)
- [Ruang Lingkup](#-ruang-lingkup)
- [Asumsi dan Batasan](#-asumsi-dan-batasan)
- [Rancangan Basis Data](#-rancangan-basis-data)
- [Rancangan Antarmuka](#-rancangan-antarmuka-wireframe)
- [Teknologi](#-teknologi)
- [Instalasi](#-instalasi)
- [Rencana Kerja & Milestone](#-rencana-kerja--milestone)
- [Kriteria Keberhasilan](#-kriteria-keberhasilan)
- [Tim Pengembang](#-tim-pengembang)
- [Mata Kuliah Terintegrasi](#-mata-kuliah-terintegrasi-dan-dosen-pengampu)

---

## 📌 Latar Belakang

Pertumbuhan coffee shop di perkotaan dan kawasan kampus meningkatkan jumlah kunjungan, terutama pada jam sibuk, namun belum diimbangi efisiensi proses pemesanan. Berdasarkan pengalaman tim sebagai pelanggan UMKM coffee shop lokal di sekitar kampus, ditemukan:

- Antrean panjang di kasir hanya untuk melihat menu dan memesan.
- Menu fisik tidak selalu diperbarui saat stok atau harga berubah, dan jumlahnya terbatas saat kafe ramai.
- Pencatatan penyesuaian pesanan (*sugar level*, *ice level*, *add-ons*) masih manual sehingga rawan *human error*.

Quick Brew ditujukan bagi **UMKM coffee shop lokal di sekitar kampus** yang belum memakai sistem pemesanan digital karena keterbatasan biaya.

### Hasil Observasi / Wawancara Calon Pengguna

| Responden | Temuan Utama |
|---|---|
| Mahasiswa (Pelanggan) | Malas mengantre hanya untuk memilih menu; lebih suka pemesanan mandiri via web/QR tanpa mengunduh aplikasi, dengan opsi kustomisasi minuman. |
| Kasir / Barista | Pesanan tertukar saat diantar karena tidak ada sistem nomor meja atau nama; pesanan hanya diingat lewat wajah pelanggan. |
| Pemilik UMKM Meepo Coffee | Antrean panjang memperlambat *table turnover* dan berpotensi membatalkan pembelian; butuh sistem hemat biaya (tanpa langganan mahal), antarmuka jelas, dan pembayaran yang mudah. |

---

## ❓ Rumusan Masalah

1. Bagaimana merancang sistem pemesanan yang memungkinkan pelanggan melakukan order secara mandiri tanpa menunggu pelayan?
2. Bagaimana mengurangi kesalahan pencatatan pada sistem manual?
3. Bagaimana mempercepat alur informasi pesanan dari pelanggan ke dapur agar waktu penyajian lebih efisien?
4. Bagaimana menyediakan solusi berbasis web yang mudah diakses tanpa instalasi aplikasi khusus, baik oleh pelanggan maupun pemilik usaha?

---

## 🎯 Tujuan Proyek

1. Mengembangkan sistem pemesanan berbasis web yang dapat diakses melalui pemindaian QR Code pada setiap meja.
2. Mempercepat dan menyederhanakan proses pemesanan menu oleh pelanggan secara mandiri.
3. Mengurangi kesalahan pencatatan pesanan dan mempercepat alur informasi ke dapur.
4. Menyediakan dashboard manajemen bagi pemilik usaha untuk memantau pesanan dan menu secara *real-time*.

---

## 🔄 Gambaran Solusi & Alur Sistem

1. Pelanggan memindai **QR Code** di meja; tautan mengarah ke katalog digital yang terasosiasi dengan nomor meja.
2. Pelanggan memilih menu, menyesuaikan variasi (*sugar level*, *ice level*, *add-ons*), lalu melakukan *self-service checkout*.
3. Pesanan masuk ke **Dashboard Kasir**. Kasir melakukan konfirmasi/validasi pembayaran (QRIS statis atau bayar di kasir).
4. Setelah dikonfirmasi, detail pesanan muncul otomatis secara *real-time* di **Dashboard Barista** untuk dibuat.
5. Barista menandai pesanan **selesai** dengan satu klik.

Sistem memisahkan hak akses operasional harian (Kasir & Barista) dari fungsi pengawasan bisnis (Owner/Manajer), serta menyediakan fitur *Checking Stock* untuk pencatatan stok produk jadi saat pergantian shift.

---

## 👥 Peran Pengguna

| Peran | Hak Akses |
|---|---|
| **Pelanggan (Customer/Guest)** | Memindai QR Code meja, melihat katalog menu digital, menyesuaikan variasi pesanan, *checkout*, dan melihat status pesanan sendiri. |
| **Kasir / Barista** | Mengelola data dan ketersediaan stok menu, memantau antrean pesanan *real-time*, memperbarui status pesanan, dan mengonfirmasi pembayaran. |
| **Owner / Manajer** | Mengelola dan memantau pemasukan/laba kotor dari penjualan harian. |

---

## ✨ Fitur Utama

### Pelanggan
- [ ] Pindai QR Code meja untuk identifikasi lokasi pesan tanpa registrasi/login (entitas **Meja**)
- [ ] Jelajahi katalog menu digital (cari, filter kategori, lihat status ketersediaan) dan sesuaikan variasi pesanan (entitas **Menu**)
- [ ] *Self-service checkout*

### Kasir / Barista
- [ ] Pantau dan perbarui status antrean pesanan masuk secara *real-time* (entitas **Pemesanan**)
- [ ] Kelola data master menu dan ubah status ketersediaan stok — CRUD Menu (entitas **Menu**)
- [ ] Konfirmasi pembayaran (QRIS statis / bayar di kasir)

### Owner / Manajer
- [ ] Lihat pendapatan harian (pemasukan/laba kotor)
- [ ] Lihat dan rekapitulasi laporan penjualan serta omzet harian (entitas **Pemesanan** dan **Pembayaran**)

---

## 📦 Ruang Lingkup

**✅ Dikerjakan**
- Pemindaian QR Code meja tanpa login
- Katalog menu digital dengan kustomisasi variasi minuman (*sugar level*, *ice level*, *add-ons*)
- Pemesanan mandiri (*self-service checkout*)
- Dashboard antrean pesanan *real-time* untuk barista/kasir
- Manajemen status ketersediaan menu (CRUD sederhana)
- Laporan rekapitulasi penjualan harian

**❌ Tidak Dikerjakan**
- Integrasi *payment gateway* otomatis (pembayaran memakai QRIS statis atau bayar di kasir)
- Sistem akun/registrasi pelanggan (*membership*)
- Notifikasi WhatsApp/email otomatis
- Manajemen stok bahan baku (takaran gramasi/mililiter)
- Integrasi cetak resi ke printer thermal

> Alasan: kompleksitas integrasi dan keterbatasan kapasitas tim dalam 16 minggu.

---

## ⚠️ Asumsi dan Batasan

### Asumsi
- **Gawai & internet pengunjung:** pelanggan memiliki HP berkamera normal dan paket internet untuk memindai QR dan membuka menu web.
- **Kesiapan data kafe:** daftar menu, harga, kategori, dan nomor meja sudah ada dan siap dimasukkan ke sistem.
- **Kesiapan staf kafe:** kasir bersedia mengecek pesanan masuk lewat web secara langsung.
- **Pembayaran di tempat:** pelanggan tetap membayar langsung ke kasir (atau simulasi bayar) tanpa membatalkan pesanan yang sudah dibuat di web.

### Batasan
- **Tidak mengelola stok bahan mentah:** hanya stok produk jadi (mis. cup kopi atau porsi makanan), bukan biji kopi, sirup, atau susu bubuk.
- **Tidak ada catatan pengeluaran:** hanya mencatat uang masuk dari pesanan.
- **Tanpa payment gateway otomatis:** tidak memakai API Midtrans/QRIS otomatis; data dummy digunakan untuk pengujian.
- **Aplikasi *standalone*:** tidak terhubung ke mesin kasir POS lain maupun printer struk fisik.
- **Tanpa pelacakan GPS:** hanya membaca kode QR meja tanpa mengecek lokasi HP pelanggan.

---

## 🗄️ Rancangan Basis Data

### Relasi Antar Tabel

| Relasi | Kardinalitas | Keterangan |
|---|---|---|
| Kategori → Menu | 1 : N | Satu kategori memiliki banyak menu |
| Menu → Detail Pemesanan | 1 : N | Satu menu dapat muncul di banyak baris detail pesanan |
| Pemesanan → Detail Pemesanan | 1 : N | Satu transaksi dapat berisi beberapa item menu |
| Pengguna → Pemesanan | 1 : N | Satu staf dapat memproses banyak pesanan |
| Meja → Pemesanan | 1 : N | Satu meja dapat memiliki banyak pesanan |
| Pemesanan → Pembayaran | 1 : 1 | Pembayaran terkait pesanan tertentu |
| Pengguna → Pembayaran | 1 : N | Satu staf dapat memvalidasi banyak pembayaran |

> Relasi di atas mengikuti Entity Relationship Diagram pada proposal (Bab 6.1.6).

### Struktur Tabel

**`pengguna`**

| Kolom | Tipe Data | Constraint |
|---|---|---|
| id | SERIAL | Primary Key |
| nama | VARCHAR(100) | NOT NULL |
| username | VARCHAR(50) | UNIQUE, NOT NULL |
| password | VARCHAR(255) | NOT NULL |
| role | ENUM('kasir', 'barista', 'admin') | NOT NULL |

**`kategori`**

| Kolom | Tipe Data | Constraint |
|---|---|---|
| id | SERIAL | Primary Key |
| nama_kategori | VARCHAR(50) | NOT NULL |

**`menu`**

| Kolom | Tipe Data | Constraint |
|---|---|---|
| id | SERIAL | Primary Key |
| kategori_id | INT | Foreign Key → kategori, NOT NULL |
| nama_menu | VARCHAR(100) | NOT NULL |
| harga | DECIMAL(10,2) | NOT NULL |
| gambar | VARCHAR(255) | NULL |
| deskripsi | TEXT | NULL |
| stok | INT | DEFAULT 0 |

**`pemesanan`**

| Kolom | Tipe Data | Constraint |
|---|---|---|
| id | SERIAL | Primary Key |
| meja_id | VARCHAR(10) | Foreign Key → meja, NOT NULL |
| pengguna_id | INT | Foreign Key → pengguna (relasi memproses), NOT NULL |
| nama_pelanggan | VARCHAR(100) | NULL |
| email_pelanggan | VARCHAR(50) | — |
| total_harga | DECIMAL(12,2) | NOT NULL |
| waktu_pemesanan | DATETIME | DEFAULT CURRENT_TIMESTAMP |
| status_pemesanan | VARCHAR(50) | — |

**`meja`**

| Kolom | Tipe Data | Constraint |
|---|---|---|
| id | SERIAL | Primary Key |
| no_meja | VARCHAR | NOT NULL |
| kode_qr | VARCHAR | NOT NULL |

**`pembayaran`**

| Kolom | Tipe Data | Constraint |
|---|---|---|
| id | SERIAL | Primary Key |
| pemesanan_id | INT | Foreign Key → pemesanan, NOT NULL |
| pengguna_id | INT | Foreign Key → pengguna (relasi memvalidasi), NOT NULL |
| waktu_pembayaran | DATETIME | NOT NULL |
| status_pembayaran | VARCHAR | NOT NULL |
| metode_pembayaran | VARCHAR | NOT NULL |
| total_bayar | DECIMAL | NOT NULL |

**`detail_pemesanan`**

| Kolom | Tipe Data | Constraint |
|---|---|---|
| id | SERIAL | Primary Key |
| pemesanan_id | INT | Foreign Key → pemesanan, NOT NULL |
| menu_id | INT | Foreign Key → menu, NOT NULL |
| harga | DECIMAL(10,2) | NOT NULL (snapshot harga) |
| jumlah | INT | NOT NULL |
| subtotal | DECIMAL(12,2) | NOT NULL |
| catatan | TEXT | NULL (opsi variasi/add-ons) |

### Entity Relationship Diagram

<!-- Ganti path berikut dengan lokasi gambar ERD pada repository -->
![ERD Quick Brew](docs/erd.png)

---

## 🖼️ Rancangan Antarmuka (Wireframe)

Rancangan awal berupa sketsa *wireframe*; detail visual (warna, tipografi, ikonografi) akan disempurnakan pada tahap UI *high-fidelity*.

1. **Tampilan Pelanggan — Katalog Menu (Mobile):** cari dan filter menu, lihat status ketersediaan (Tersedia/HABIS), lalu tambahkan ke keranjang.
2. **Tampilan Pelanggan — Kustomisasi & Checkout:** modal untuk memilih tipe minuman (Ice/Hot), level ice, level sugar, add-on/topping (mis. Extra Shot, Grass Jelly, Boba), jumlah, lalu tambahkan ke keranjang dan konfirmasi pesanan.
3. **Barista & Kasir — Dashboard Antrean (Kanban):** daftar pesanan masuk per meja beserta rincian item dan variasinya secara *real-time*, dengan tombol **Selesai** satu klik.

<!-- Tambahkan gambar wireframe / desain Figma di sini -->
<!-- ![Wireframe Katalog](docs/wireframe-katalog.png) -->

---

## 🛠️ Teknologi

> Bagian ini mengikuti hal yang disebutkan di proposal. Lengkapi sesuai stack final yang digunakan tim.

| Komponen | Keterangan |
|---|---|
| Platform | Aplikasi web responsif (mobile-first untuk pelanggan) |
| Basis data | Relasional (skema pada bagian [Rancangan Basis Data](#-rancangan-basis-data)) |
| Email receipt | PHPMailer (pengiriman struk pesanan otomatis ke email pelanggan) |
| Akses meja | QR Code berbasis token unik per meja |
| Pembayaran | QRIS statis / bayar di kasir (tanpa payment gateway) |
| Backend / Framework | _TODO: isi sesuai stack tim_ |
| Frontend | _TODO: isi sesuai stack tim_ |
| Desain | _TODO: isi (mis. tautan Figma)_ |

---

## 🚀 Instalasi

> _TODO: sesuaikan langkah berikut dengan stack yang dipakai._

```bash
# 1. Clone repository
git clone https://github.com/<username>/<nama-repo>.git
cd <nama-repo>

# 2. Konfigurasi environment
#    (salin file contoh konfigurasi lalu isi kredensial database)

# 3. Import skema database

# 4. Jalankan aplikasi
```

### Akun Uji (Data Dummy)

| Role | Username | Password |
|---|---|---|
| _TODO_ | _TODO_ | _TODO_ |

---

## 📅 Rencana Kerja & Milestone

Durasi proyek: **16 minggu**.

| Minggu | Aktivitas | Milestone |
|---|---|---|
| 1 – 3 | Identifikasi masalah, wawancara pengguna | — |
| 4 | Finalisasi proposal | CP-1 Proposal |
| 5 – 8 | Implementasi | CP-2 Milestone 1 |
| 9 – 12 | Implementasi | CP-3 Milestone 2 |
| 13 – 16 | Pengujian, perbaikan, demo akhir | CP-4 Final |

### Rincian Pekerjaan Tim

| Bagian Besar | Sub-bagian | Tugas Rinci | Penanggung Jawab |
|---|---|---|---|
| **1. Desain UI/UX** | Wireframe & alur navigasi | Membuat wireframe seluruh halaman dan alur perpindahan antar halaman | Bilqis |
| | Desain visual & design system | Desain high-fidelity semua halaman beserta komponen header | Bilqis |
| | | Menentukan warna, tipografi, dan komponen reusable (button, card, badge status) | Bilqis |
| | | Memastikan desain responsif untuk tampilan mobile | Bilqis |
| **2. Frontend Development** | Implementasi halaman & komponen | Membangun seluruh halaman beserta header dan navigasi sesuai desain | Arkan |
| | | Membangun logika interaktif keranjang dan form input data pelanggan | Rafa |
| | Integrasi dengan backend | Menghubungkan tampilan menu, kategori, dan proses checkout ke API | Arkan |
| | | Menangani validasi input dan pesan error dari API | Rafa |
| **3. Backend Development** | Perancangan database | Merancang skema tabel beserta relasi dan constraint (ERD) | Isma |
| | Pengembangan API | Membangun endpoint untuk menu, kategori, dan proses checkout | Vesakha |
| | | Membangun fungsi pengiriman struk pesanan otomatis ke email pelanggan via PHPMailer | Isma |
| | Autentikasi & manajemen status | Membangun sistem login staff/admin dan endpoint update status pesanan | Vesakha |
| **4. Pengujian Sistem** | Pengujian fungsional | Menguji alur pemesanan | Arkan |
| | | Menguji pengiriman email receipt dan penyimpanan database | Isma |
| | | Menguji fitur admin (login, CRUD menu, update status) | Rafa |
| | Pengujian antarmuka & bug fixing | Menguji responsivitas tampilan mobile di berbagai browser | Arkan |
| | | Melakukan perbaikan bug dan validasi alur akhir | Vesakha |

---

## ✅ Kriteria Keberhasilan

- Aplikasi web berjalan penuh dengan entitas Pengguna, Menu, Kategori, Pemesanan, dan Detail Pemesanan yang saling berelasi.
- Peran Pelanggan (scan QR, pesan menu, pilih variasi) dan Barista/Kasir (kelola menu, pantau pesanan *real-time*) berfungsi penuh.
- Tersedia laporan rekapitulasi penjualan harian.
- Seluruh alur utama stabil dengan data uji terisi.
- Seluruh proses dikelola sesuai rencana proyek dan *logbook* mingguan.

---

## 👨‍💻 Tim Pengembang

**Kelompok 5 — SIB 2E**

| Nama | NIM | Peran Teknis | Peran Tim |
|---|---|---|---|
| Ananda Vesakhagotama S. | 254107060025 | Back-end | Ketua Tim & Pengembang Utama (back-end & basis data) |
| Arkan Farrasabyan | 254107060013 | Front-end | Analisis/Desainer (wireframe & front-end) |
| Aulia Bilqisty | 254107060103 | UI/UX | Sekretaris/Dokumentator (logbook, laporan, query laporan) |
| Isma Anbiya Fasha | 254107060061 | Back-end | Pengembang Utama (back-end & basis data) |
| Rafa Alrabbani | 254107060107 | Front-end | Pengembang Utama (front-end) |

---

## 🎓 Mata Kuliah Terintegrasi dan Dosen Pengampu

| Mata Kuliah | Dosen Pengampu | Kelas |
|---|---|---|
| Pemrograman Web | Moch. Zawaruddin Abdullah, S.ST., M.Kom. | SIB 2E |
| Basis Data Lanjut | Prof. Ir. Yan Watequlis Syaifudin, S.T., M.MT., Ph.D. | SIB 2E |
| Desain UI/UX | Anugrah Nur Rahmanto, S.Sn., M.Ds. | SIB 2E |

---

<p align="center">
  Program Studi D-IV Sistem Informasi Bisnis · Jurusan Teknologi Informasi · Politeknik Negeri Malang
</p>