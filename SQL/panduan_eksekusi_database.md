# 🚀 Panduan Eksekusi Database QuickBrew (PostgreSQL)

Panduan praktis ini berisi alur lengkap pembuatan database `QuickBrew` di pgAdmin hingga eksekusi file script `QuickBrew.sql` melalui Terminal Laragon.

---

## 📋 Prasyarat

1. Aplikasi **pgAdmin 4** dan **Laragon** sudah terinstall.
2. *Service* PostgreSQL pada Laragon dalam kondisi **Active / Running**.
3. File **`QuickBrew.sql`** telah disimpan dalam folder proyek kamu.

---

## 🛠️ Alur Pelaksanaan

### Langkah 1: Buat Database di pgAdmin 4
1. Buka aplikasi **pgAdmin 4**.
2. Masukkan password akun **PostgreSQL** kamu jika diminta.
3. Pada panel navigasi sebelah kiri (**Object Explorer**):
   * Klik kanan pada **Databases**
   * Pilih **Create** ➔ **Database...**
4. Pada jendela dialog tab **General**:
   * Masukkan nama database: `QuickBrew`
5. Klik tombol **Save** untuk menyimpan database baru.

---

### Langkah 2: Buka Terminal Laragon
1. Buka aplikasi **Laragon**.
2. Pastikan layanan PostgreSQL sudah berjalan, lalu klik tombol **Terminal**.

---

### Langkah 3: Navigasi ke Folder Proyek
Pindahkan direktori terminal ke lokasi folder tempat kamu menyimpan file `QuickBrew.sql` menggunakan perintah `cd`:

```bash
cd C:\laragon\www\quickbrew
```
*(Sesuaikan path folder di atas dengan lokasi penyimpanan file kamu).*

---

### Langkah 4: Eksekusi File `QuickBrew.sql`
Jalankan perintah `psql` berikut untuk membuat seluruh tabel sekaligus mengisi data masukan awal:

```bash
psql -U postgres -d "QuickBrew" -f QuickBrew.sql
```

> 📌 **Catatan:**
> - Ganti `postgres` jika kamu menggunakan nama user PostgreSQL yang berbeda.
> - Jika diminta password, ketik password akun PostgreSQL kamu lalu tekan **Enter** *(karakter password sengaja tidak ditampilkan di layar saat diketik)*.

---

## 🔍 Langkah 5: Verifikasi Hasil Import

Kamu dapat memastikan tabel dan data berhasil masuk melalui **Terminal Laragon** atau GUI **pgAdmin**:

### A. Pengecekan via Terminal Laragon
1. Hubungkan terminal ke database:
   ```bash
   psql -U postgres -d "QuickBrew"
   ```
2. Tampilkan daftar tabel yang ada:
   ```sql
   \dt
   ```
3. Tampilkan data dari tabel menu:
   ```sql
   SELECT id, nama_menu, harga, stok FROM menu LIMIT 5;
   ```
4. Keluar dari terminal `psql`:
   ```sql
   \q
   ```

### B. Pengecekan via pgAdmin
1. Buka pgAdmin, lalu lakukan *Refresh* pada objek server.
2. Navigasi ke: **Databases** ➔ **QuickBrew** ➔ **Schemas** ➔ **public** ➔ **Tables**.
3. Pastikan ketujuh tabel (`kategori`, `meja`, `pengguna`, `menu`, `pemesanan`, `detail_pemesanan`, `pembayaran`) sudah terdaftar.
4. Klik kanan pada tabel `menu` ➔ **View/Edit Data** ➔ **First 100 Rows** untuk melihat isi data menu.

---

# 🚀 Panduan Eksekusi Database QuickBrew di DBeaver

Dokumen ini berisi panduan untuk membuat database PostgreSQL dan mengeksekusi script SQL (`QuickBrew.sql`) menggunakan fitur bawaan DBeaver.

---


## 🛠️ Langkah 1: Buat Database `QuickBrew`

1. Buka aplikasi **DBeaver**.
2. Pada panel **Database Navigator** di sebelah kiri, rentangkan koneksi PostgreSQL kamu.
3. Klik kanan pada folder **Databases**, lalu pilih **Create New Database**.
4. Pada kolom **Database name**, ketik: `QuickBrew`
5. Biarkan pengaturan lainnya secara *default*, lalu klik **OK**.

---

## 💻 Langkah 2: Jalankan Script SQL via Execute Script

Berbeda dengan cara manual (copy-paste), kita akan langsung memuat file SQL menggunakan fitur bawaan DBeaver:

1. Di panel **Database Navigator**, pastikan database `QuickBrew` sudah muncul.
2. Klik kanan tepat pada database **`QuickBrew`**.
3. Pilih menu **Tools** ➔ **Execute script**.
4. Pada jendela *Execute script* yang muncul, klik ikon folder/browse di sebelah kolom **Input file** (atau **Script**).
5. Cari dan pilih file `QuickBrew.sql` yang sudah kamu simpan sebelumnya, lalu klik **Open**.
6. Klik tombol **Start** di bagian bawah untuk memulai proses eksekusi.
7. Tunggu hingga proses selesai. Jika berhasil, kamu akan melihat status penyelesaian atau pop-up notifikasi berhasil tanpa *error*.

---

## 🔍 Langkah 3: Verifikasi Hasil Eksekusi

1. Di panel **Database Navigator**, rentangkan jalur berikut:
   `QuickBrew` ➔ `Schemas` ➔ `public` ➔ `Tables`
2. *(Catatan: Jika tabel belum muncul, klik kanan pada folder `Tables` dan pilih **Refresh** atau tekan `F5`).*
3. Pastikan tabel-tabel seperti `kategori`, `menu`, `meja`, `pemesanan`, dan lainnya sudah terdaftar.
4. Klik ganda pada salah satu tabel (misalnya `menu`).
5. Pilih tab **Data** di jendela tengah (di sebelah tab *Properties*) untuk memastikan seluruh baris data (termasuk menu minuman, makanan, dan dessert) telah berhasil disuntikkan.

✨ **Selesai!** Seluruh struktur skema dan data awal database `QuickBrew` kamu sudah siap digunakan.