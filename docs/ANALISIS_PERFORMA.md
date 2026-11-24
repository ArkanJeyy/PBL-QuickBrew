# Analisis Performa & Indeks PostgreSQL — QuickBrew

Dokumen ini mencatat hasil pengujian performa (`EXPLAIN (ANALYZE, BUFFERS)`) pada database pengujian **`quickbrew_test`** dengan populasi **5.000** dan **50.000** pesanan menggunakan [bin/seed_dummy.php](../bin/seed_dummy.php), serta evaluasi kandidat indeks sebelum dan sesudah optimasi.

---

## 1. Metodologi Pengukuran

- **Database:** PostgreSQL 14.5 lokal (`quickbrew_test`), di-*seed* deterministik melalui `bin/seed_dummy.php --jalankan --pesanan=5000 --hari=90 --seed=2026` lalu ditambah menjadi 50.000 pesanan (`--seed=2027`), disertai 5.000 baris pada `pembatasan_laju`.
- **Statistik & Cache:** `VACUUM ANALYZE` dijalankan setiap selesai seeding dan setiap penambahan indeks agar planner & *Visibility Map* akurat; setiap kueri dipanaskan satu kali (*warm cache*) sebelum dicatat `Execution Time`, `Shared Hit Blocks`, dan rencana *scan*-nya.
- **Catatan Ruang Lingkup:** Method `PemesananModel::analitikDashboard()` belum tersedia pada `origin/main` (`992fcd8`), sehingga analisis mencakup seluruh kueri yang memang ada di `origin/main`: `daftarMasuk`, `daftarAntrean` (semua & filter `'diproses'`), `hitungRingkas`, lookup `status-json` (`PemesananModel::find`), seluruh 6 kueri `LaporanModel`, dan lookup `pembatasan_laju`.

---

## 2. Pengukuran Baseline (Tanpa Indeks Tambahan, Skema `SQL/QuickBrew.sql`)

| Kueri | 5.000 Pesanan (ms) | Buffer Hit (5k) | 50.000 Pesanan (ms) | Buffer Hit (50k) | Rencana Scan Utama (50k) |
|---|---:|---:|---:|---:|---|
| `PemesananModel::daftarMasuk()` | 11,707 | 330 | 265,603 | 2.864 | `Bitmap Heap/Index Scan(pemesanan)`, `Seq Scan(pembayaran)`, `Index Scan(detail_pemesanan:idx_detail_pemesanan)` *(subquery agregasi)* |
| `PemesananModel::daftarAntrean(null, 50)` | 17,533 | 333 | 429,171 | 2.918 | `Seq Scan(pemesanan)`, `Seq Scan(pembayaran)`, `Subquery Scan` (`detail_pemesanan`) |
| `PemesananModel::daftarAntrean('diproses', 50)` | 11,136 | 333 | 262,733 | 2.862 | `Bitmap Heap/Index Scan(pemesanan)`, `Seq Scan(pembayaran)`, `Subquery Scan` |
| `PemesananModel::hitungRingkas()` | 2,697 | 129 | 60,235 | 1.335 | `Seq Scan(pemesanan)`, `Seq Scan(pembayaran)` |
| Lookup `status-json` (`find($id)`) | 0,029 | 7 | 0,049 | 7 | `Index Scan(pemesanan_pkey)`, `Index Scan(pembayaran_pemesanan_id_key)` |
| `LaporanModel::ringkasanPeriode()` *(7 hari)* | 1,335 | 220 | 29,279 | 2.134 | `Bitmap Heap/Index Scan(pembayaran:idx_pembayaran_status)`, `Seq Scan(detail_pemesanan)` |
| `LaporanModel::harian()` *(30 hari)* | 2,520 | 228 | 59,793 | 2.252 | `Bitmap Heap/Index Scan(pembayaran:idx_pembayaran_status)`, `Seq Scan(detail_pemesanan)` |
| `LaporanModel::menuTerlaris()` *(30 hari)* | 2,998 | 170 | 65,841 | 1.658 | `Bitmap Heap/Index Scan(pembayaran:idx_pembayaran_status)`, `Seq Scan(detail_pemesanan)` |
| `LaporanModel::perJam()` *(7 hari)* | 0,240 | 56 | 3,749 | 537 | `Bitmap Heap/Index Scan(pembayaran:idx_pembayaran_status)` |
| `LaporanModel::perMetode()` *(30 hari)* | 0,692 | 60 | 15,869 | 596 | `Bitmap Heap/Index Scan(pembayaran:idx_pembayaran_status)` |
| `LaporanModel::perKategori()` *(30 hari)* | 2,480 | 170 | 68,165 | 1.658 | `Bitmap Heap/Index Scan(pembayaran:idx_pembayaran_status)`, `Seq Scan(detail_pemesanan)` |
| Lookup `pembatasan_laju` *(5k baris)* | 0,024 | 3 | 0,085 | 3 | `Index Only Scan(idx_pembatasan_laju_kunci_waktu)` |

---

## 3. Evaluasi Kandidat Indeks (Pada 50.000 Pesanan)

Tiga kandidat indeks diuji secara langsung dengan `EXPLAIN (ANALYZE, BUFFERS)` + `VACUUM ANALYZE`:

1. **DITERIMA — Indeks Parsial Covering `idx_pembayaran_paid_waktu`:**
   ```sql
   CREATE INDEX IF NOT EXISTS idx_pembayaran_paid_waktu
       ON pembayaran (waktu_pembayaran)
       INCLUDE (pemesanan_id, total_bayar, metode_pembayaran)
       WHERE status_pembayaran = 'paid';
   ```
   - **Manfaat Terukur:** Mengubah akses tabel `pembayaran` pada seluruh kueri `LaporanModel` dari `Bitmap Heap Scan` (`idx_pembayaran_status`) menjadi **`Index Only Scan` (`Heap Fetches: 0`)**, memangkas *shared buffer hit* sebesar **31,7% – 96,5%** dan mempercepat waktu eksekusi kueri laporan hingga **3,3× lebih cepat**.

2. **DITOLAK — `idx_pemesanan_waktu_desc ON pemesanan (waktu_pemesanan DESC, id DESC)`:**
   - **Alasan:** Pada `PemesananModel::daftarAntrean()`, subquery `LEFT JOIN (SELECT ... FROM detail_pemesanan GROUP BY pemesanan_id)` diagregasi terlebih dahulu untuk seluruh baris sebelum `LIMIT 50`. PostgreSQL tetap memilih `Seq Scan` + `HashAggregate` (424–435 ms, tanpa perubahan rencana maupun buffer), sehingga indeks ini tidak memberi manfaat nyata dan hanya menambah beban tulis saat `INSERT`.

3. **DITOLAK — `idx_pemesanan_menunggu_waktu ON pemesanan (waktu_pemesanan, id) WHERE status_pemesanan = 'menunggu_pembayaran'`:**
   - **Alasan:** Skema dasar sudah memiliki `idx_pemesanan_status_waktu (status_pemesanan, waktu_pemesanan)`. Pada `daftarMasuk()`, bottleneck utama (>95% waktu) ada pada `HashAggregate` atas `detail_pemesanan` (42 ms), bukan pada pencarian baris `menunggu_pembayaran` (~1 ms). Pemangkasan buffer hanya 7 blok (~1,4% selisih total) sehingga belum sebanding dengan indeks tambahan.

---

## 4. Perbandingan Sebelum vs Sesudah `idx_pembayaran_paid_waktu` (50.000 Pesanan)

| Kueri | Sebelum (ms) | Sesudah (ms) | Perubahan Waktu | Buffer Hit Sebelum $\rightarrow$ Sesudah | Perubahan Rencana Scan |
|---|---:|---:|---:|---:|---|
| `LaporanModel::perJam()` *(7 hari)* | 3,749 | **1,130** | **-69,9% (3,3× lebih cepat)** | 537 $\rightarrow$ **19** (**-96,5%**) | `Bitmap Heap Scan` $\rightarrow$ **`Index Only Scan`** |
| `LaporanModel::perMetode()` *(30 hari)* | 15,869 | **6,846** | **-56,9% (2,3× lebih cepat)** | 596 $\rightarrow$ **71** (**-88,1%**) | `Bitmap Heap Scan` $\rightarrow$ **`Index Only Scan`** |
| `LaporanModel::perKategori()` *(30 hari)* | 68,165 | **45,339** | **-33,5%** | 1.658 $\rightarrow$ **1.133** (**-31,7%**) | `Bitmap Heap Scan` $\rightarrow$ **`Index Only Scan`** (`pembayaran`) |
| `LaporanModel::harian()` *(30 hari)* | 59,793 | **40,188** | **-32,8%** | 2.252 $\rightarrow$ **1.202** (**-46,6%**) | `Bitmap Heap Scan` $\rightarrow$ **`Index Only Scan`** (`pembayaran`) |
| `LaporanModel::ringkasanPeriode()` *(7 hari)* | 29,279 | **20,363** | **-30,5%** | 2.134 $\rightarrow$ **1.098** (**-48,5%**) | `Bitmap Heap Scan` $\rightarrow$ **`Index Only Scan`** (`pembayaran`) |
| `LaporanModel::menuTerlaris()` *(30 hari)* | 65,841 | **60,774** | **-7,7%** | 1.658 $\rightarrow$ **1.133** (**-31,7%**) | `Bitmap Heap Scan` $\rightarrow$ **`Index Only Scan`** (`pembayaran`) |

---

## 5. Temuan Tambahan & Rekomendasi Performa

1. **Kueri `daftarMasuk()` & `daftarAntrean()` (265–435 ms pada 50k pesanan):**
   Kedua kueri melakukan subquery `GROUP BY d.pemesanan_id` atas seluruh tabel `detail_pemesanan` sebelum *join* dan `LIMIT 50`. Apabila jumlah pesanan melampaui puluhan ribu di masa depan, disarankan merefaktor kueri kasir tersebut dengan *CTE/LATERAL* yang menerapkan `WHERE` + `ORDER BY ... LIMIT 50` pada `pemesanan` terlebih dahulu sebelum mengambil agregasi item dari `detail_pemesanan`. *(Dipertahankan apa adanya pada tugas ini agar perilaku model tidak berubah).*
2. **`status-json` (`0,044 ms`) dan `pembatasan_laju` (`0,035 ms`):**
   Keduanya sudah menggunakan *primary key* / `Index Only Scan` sehingga tetap berada di bawah **0,1 ms** bahkan pada 50.000 pesanan.
3. **Pengaturan PostgreSQL:** Jalankan `VACUUM ANALYZE` secara berkala (otomatis via *autovacuum*) agar *Visibility Map* tetap ter-update sehingga `Index Only Scan` (`idx_pembayaran_paid_waktu`) menghasilkan `Heap Fetches: 0`.
