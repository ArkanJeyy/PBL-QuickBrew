# Dokumentasi Kontrak `LaporanModel` (`app/Models/LaporanModel.php`)

Dokumen ini menjadi acuan bagi pengembang **API Laporan** dan **Front-End Dashboard Owner/Admin** untuk membaca ringkasan penjualan, tren harian/per jam, menu terlaris, serta komposisi metode pembayaran dan kategori.

---

## 1. Aturan Umum & Keputusan Desain

1. **Read-only & Khusus Pembayaran Lunas (`paid`):**
   Seluruh method hanya menghitung transaksi dengan `pembayaran.status_pembayaran = 'paid'`. Pembayaran berstatus `pending`, `cancel`, `expire`, `deny`, maupun `failure` tidak pernah dihitung.
2. **Validasi Rentang Tanggal (`Y-m-d` WIB):**
   - Parameter `$dari` dan `$sampai` wajib berformat ketat `Y-m-d` (zona waktu `Asia/Jakarta`).
   - `$dari <= $sampai`.
   - Rentang maksimal **366 hari** inklusif.
   - Pelanggaran melempar `InvalidArgumentException` dengan pesan kesalahan berbahasa Indonesia yang jelas.
3. **Interval Setengah Terbuka & Sargability (Tanpa `v_rekap_*`):**
   - Seluruh query menyaring `pembayaran.waktu_pembayaran >= :dari_ts AND pembayaran.waktu_pembayaran < :sampai_excl_ts` (di mana `:sampai_excl_ts` adalah `$sampai + 1 hari` pukul `00:00:00`).
   - **Mengapa tidak memakai view `v_rekap_harian` / `v_rekap_menu_harian`?** Kedua view tersebut melakukan *cast* `p.waktu_pembayaran::date` di kolom output, sehingga filter `WHERE tanggal BETWEEN ...` di atas view memaksa PostgreSQL mengeksekusi *cast* per baris dan **tidak dapat memanfaatkan indeks B-Tree secara langsung (non-*sargable*)**. Dengan filter rentang timestamp langsung pada tabel `pembayaran`, indeks pada `waktu_pembayaran` tetap dapat digunakan optimal.
4. **Dua Dasar Pendapatan (`total_pendapatan` vs `pendapatan_menu`) & Relasi Akuntansi:**
   - **`total_pendapatan`** (pada `ringkasanPeriode`, `harian`, `perJam`, `perMetode`): dihitung dari `SUM(pembayaran.total_bayar)` (**sudah termasuk pajak 10%**).
   - **`pendapatan_menu`** (pada `menuTerlaris`, `perKategori`): dihitung dari `SUM(detail_pemesanan.subtotal)` (**omzet item sebelum pajak 10%**).
   - **Relasi Akuntansi:** Untuk periode yang sama, penjumlahan `pendapatan_menu` seluruh menu/kategori ditambah total pajak (10%) sama persis dengan `total_pendapatan` pada `ringkasanPeriode()`:
     $$\sum \text{pendapatan\_menu} + \text{total\_pajak} = \text{total\_pendapatan}$$
5. **Tipe Data:** Seluruh nilai numerik dikembalikan sebagai `int` atau `float` PHP (bukan string dari PDO).

---

## 2. Kontrak Setiap Method

### 2.1. `LaporanModel::ringkasanPeriode(string $dari, string $sampai): array`

Mengembalikan KPI utama pada periode `[$dari, $sampai]` serta perbandingan otomatis terhadap periode sebelumnya yang panjang harinya sama persis.

- **Aturan Persentase Perbandingan (`perbandingan.*.persen`):**
  - Jika periode sebelumnya `0` dan periode sekarang `0` $\rightarrow$ `0.0`.
  - Jika periode sebelumnya `0` dan periode sekarang `> 0` $\rightarrow$ **`null` ("tidak terdefinisi")**.
  - Selain itu dibulatkan **1 angka di belakang koma** (`round((sekarang - sebelumnya) / sebelumnya * 100, 1)`).

```php
[
    'periode' => [
        'dari'        => '2026-05-10',
        'sampai'      => '2026-05-12',
        'jumlah_hari' => 3,
    ],
    'total_pendapatan' => 280500, // int (termasuk pajak)
    'jumlah_transaksi' => 3,      // int
    'item_terjual'     => 11,     // int
    'rata_rata_nilai'  => 93500.0,// float
    'periode_sebelumnya' => [
        'dari'             => '2026-05-07',
        'sampai'           => '2026-05-09',
        'total_pendapatan' => 26400,
        'jumlah_transaksi' => 1,
        'item_terjual'     => 1,
        'rata_rata_nilai'  => 26400.0,
    ],
    'perbandingan' => [
        'total_pendapatan' => ['selisih' => 254100, 'persen' => 962.5], // ?float (null = tidak terdefinisi)
        'jumlah_transaksi' => ['selisih' => 2,      'persen' => 200.0],
        'item_terjual'     => ['selisih' => 10,     'persen' => 1000.0],
        'rata_rata_nilai'  => ['selisih' => 67100.0,'persen' => 254.2],
    ],
]
```

---

### 2.2. `LaporanModel::harian(string $dari, string $sampai): array`

Mengembalikan satu baris per tanggal dari `$dari` hingga `$sampai` (menggunakan `generate_series` PostgreSQL sehingga hari tanpa transaksi tetap muncul bernilai `0`).

```php
[
    [
        'tanggal'          => '2026-05-10',
        'jumlah_transaksi' => 2,
        'item_terjual'     => 5,
        'total_pendapatan' => 122100,
    ],
    [
        'tanggal'          => '2026-05-11',
        'jumlah_transaksi' => 0,
        'item_terjual'     => 0,
        'total_pendapatan' => 0,
    ],
]
```

---

### 2.3. `LaporanModel::menuTerlaris(string $dari, string $sampai, int $limit = 10, ?int $kategoriId = null): array`

Mengembalikan daftar menu terlaris beserta peringkatnya.
- `$limit`: minimal `1`, maksimal `100` (`InvalidArgumentException` bila `< 1`).
- `$kategoriId`: opsional (`null` = semua kategori; `InvalidArgumentException` bila `<= 0`).
- **Peringkat (`RANK()`):** Menggunakan `RANK() OVER (ORDER BY total_terjual DESC, pendapatan_menu DESC)` dan urutan keluaran `peringkat ASC, nama_menu ASC`. Apabila dua menu memiliki `total_terjual` dan `pendapatan_menu` identik, keduanya mendapat peringkat sama dan peringkat berikutnya melompat (mis. `1, 1, 3`).

```php
[
    [
        'menu_id'         => 7,
        'nama_menu'       => 'Choco Latte',
        'kategori'        => 'Minuman',
        'total_terjual'   => 3,
        'pendapatan_menu' => 72000, // sebelum pajak
        'peringkat'       => 1,
    ],
    [
        'menu_id'         => 3,
        'nama_menu'       => 'Matcha Latte',
        'kategori'        => 'Minuman',
        'total_terjual'   => 3,
        'pendapatan_menu' => 72000,
        'peringkat'       => 1,
    ],
    [
        'menu_id'         => 14,
        'nama_menu'       => 'French Fries',
        'kategori'        => 'Makanan',
        'total_terjual'   => 2,
        'pendapatan_menu' => 52000,
        'peringkat'       => 3,
    ],
]
```

---

### 2.4. `LaporanModel::perJam(string $dari, string $sampai): array`

Selalu mengembalikan **24 baris** (`jam` `0` sampai `23`), termasuk jam yang tidak memiliki transaksi (`0`).

```php
[
    [
        'jam'              => 0,
        'label'            => '00:00',
        'jumlah_transaksi' => 1,
        'total_pendapatan' => 44000,
    ],
    // ... jam 1..23
]
```

---

### 2.5. `LaporanModel::perMetode(string $dari, string $sampai): array`

Mengelompokkan pembayaran `paid` berdasarkan `metode_pembayaran` (diurutkan `total_pendapatan DESC, metode ASC`).

```php
[
    [
        'metode'            => 'qris',
        'jumlah_transaksi'  => 2,
        'total_pendapatan'  => 202400,
        'persen_transaksi'  => 66.7,
        'persen_pendapatan' => 72.2,
    ],
    [
        'metode'            => 'tunai',
        'jumlah_transaksi'  => 1,
        'total_pendapatan'  => 78100,
        'persen_transaksi'  => 33.3,
        'persen_pendapatan' => 27.8,
    ],
]
```

---

### 2.6. `LaporanModel::perKategori(string $dari, string $sampai): array`

Mengembalikan seluruh kategori menu (`LEFT JOIN` dari `kategori`) beserta `item_terjual`, `pendapatan_menu` (sebelum pajak), dan persentasenya.

```php
[
    [
        'kategori_id'       => 1,
        'kategori'          => 'Minuman',
        'item_terjual'      => 8,
        'pendapatan_menu'   => 184000,
        'persen_pendapatan' => 72.2,
    ],
    [
        'kategori_id'       => 2,
        'kategori'          => 'Makanan',
        'item_terjual'      => 2,
        'pendapatan_menu'   => 52000,
        'persen_pendapatan' => 20.4,
    ],
    [
        'kategori_id'       => 3,
        'kategori'          => 'Dessert',
        'item_terjual'      => 1,
        'pendapatan_menu'   => 19000,
        'persen_pendapatan' => 7.5,
    ],
]
```
