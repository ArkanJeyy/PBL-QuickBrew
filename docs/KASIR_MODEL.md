# Dokumentasi Kontrak Model & Layanan Kasir (`PemesananModel` & `PembayaranService`)

Dokumen ini menjadi acuan bagi pengembang **API Kasir** dan **Front-End Dashboard Kasir** QuickBrew untuk mengelola siklus hidup pesanan, konfirmasi pembayaran tunai, pengecekan/pembatalan QRIS, serta query daftar pesanan tanpa *N+1 query*.

---

## 1. Alur Status Pesanan & Peta Transisi (`PemesananModel::PETA_TRANSISI`)

```text
menunggu_pembayaran  --(bayarTunai / QRIS lunas)-->  diproses  -->  siap  -->  selesai
         |
         +--(ubahStatus ke 'batal')-------------->  batal
```

- **Status `dibayar` tidak digunakan pada kode baru** (pesanan lunas langsung berstatus `diproses` dan mengurangi stok menu secara atomik).
- **Pesanan yang sudah lunas (`diproses`, `siap`, `selesai`) tidak dapat dibatalkan.**

---

## 2. Transisi Status & Pembatalan Pesanan

### 2.1. `PemesananModel::ubahStatus(int $id, string $ke, ?int $penggunaId = null, ?PembayaranService $service = null): string`

Mengubah status pesanan menggunakan penguncian baris (`SELECT ... FOR UPDATE`) dan mencatat log audit ke `error_log` (`[AUDIT STATUS] pemesanan_id=.. status=..->.. pengguna_id=..`, tanpa data pribadi pelanggan).

- **Parameter:**
  - `$id` (`int`): ID pesanan (`pemesanan.id`).
  - `$ke` (`string`): Status tujuan (`'siap'`, `'selesai'`, atau `'batal'`).
  - `$penggunaId` (`?int`): ID staf kasir/admin dari `$_SESSION['staf']['id']`.
  - `$service` (`?PembayaranService`): Opsional (untuk injeksi stub saat pengujian).
- **Transisi yang Diizinkan:**
  - `'diproses' -> 'siap'`
  - `'siap' -> 'selesai'`
  - `'menunggu_pembayaran' -> 'batal'`
    - Jika metode pembayaran adalah **QRIS** dan memiliki `midtrans_order_id`, sistem otomatis memanggil `PembayaranService::batalkan($midtransOrderId)` terlebih dahulu. Apabila saat dicek ternyata transaksi di Midtrans sudah `settlement`, pembatalan ditolak (`'transisi_tidak_valid'`) dan status lunas diterapkan.
    - Jika metode pembayaran adalah **Tunai**, status diubah secara lokal (`pemesanan.status_pemesanan = 'batal'`, `pembayaran.status_pembayaran = 'cancel'`).
- **Nilai Kembali (`string`):**
  - `'ok'` — Status berhasil diperbarui.
  - `'tidak_ditemukan'` — ID pesanan tidak ditemukan.
  - `'transisi_tidak_valid'` — Transisi melanggar aturan (mis. membatalkan pesanan yang sudah lunas atau melompati tahap).

### 2.2. `PembayaranService::batalkan(string $midtransOrderId): ?array`

Membatalkan transaksi QRIS di Midtrans Core API (`POST /v2/{order_id}/cancel`) dan menerapkan hasilnya melalui `terapkanStatus()`.
- Mengembalikan `null` bila transaksi belum terdaftar di Midtrans (`404`).
- Menangani kondisi transaksi yang sudah berstatus final di Midtrans (`412`) dengan menerapkan status aktual atau memanggil `sinkron()`.

---

## 3. Aksi Pembayaran Kasir

### 3.1. `PemesananModel::bayarTunai(int $id, int $kasirId, ?PembayaranService $service = null): array`

Mengonfirmasi pelunasan pesanan bermetode **Tunai** secara atomik dan **idempotent** (memanggil `PembayaranService::tandaiLunas($id, 'tunai', $kasirId)`). Stok menu hanya dikurangi satu kali pada pelunasan pertama.

- **Bentuk Hasil (`array`):**
  ```php
  [
      'ok'    => true, // bool
      'kode'  => 'lunas', // 'lunas' | 'sudah_lunas' | 'tidak_ditemukan' | 'bukan_tunai' | 'status_tidak_valid'
      'pesan' => 'Pembayaran tunai berhasil dikonfirmasi.',
  ]
  ```

### 3.2. `PemesananModel::cekQris(int $id, ?PembayaranService $service = null): array`

Mencari `midtrans_order_id` dari tabel `pembayaran` untuk pesanan QRIS, lalu memanggil `PembayaranService::sinkron($midtransOrderId)`. Jika Midtrans mengembalikan `null` (`404`), status dipetakan menjadi `'belum_dibayar'`.

- **Bentuk Hasil (`array`):**
  ```php
  [
      'ok'     => true, // bool
      'status' => 'lunas', // 'lunas' | 'belum_dibayar' | 'expire' | 'cancel' | 'deny' | 'failure' | 'tidak_ditemukan' | 'bukan_qris'
      'pesan'  => 'Pembayaran QRIS berhasil diterima.',
  ]
  ```

---

## 4. Query Daftar & Detail Pesanan (Bebas N+1)

### 4.1. `PemesananModel::daftarMasuk(): array`

Mengambil seluruh pesanan berstatus `menunggu_pembayaran` (diurutkan dari yang paling awal masuk) dalam satu query dengan agregasi `STRING_AGG`:

```php
[
    [
        'id'                  => 12,
        'nomor'               => '#A0012',
        'no_meja'             => '1',
        'nama_pelanggan'      => 'Budi Santoso',
        'metode_pembayaran'   => 'tunai', // 'qris' | 'tunai'
        'status_pembayaran'   => 'pending',
        'ringkasan_item'      => 'Americano + Croissant Butter',
        'jumlah_item'         => 3,
        'total_harga'         => 90200,
        'waktu_pemesanan'     => '2026-11-10 10:15:00',
        'menit_sejak_dipesan' => 4,
    ],
]
```

### 4.2. `PemesananModel::daftarAntrean(?string $status = null, int $limit = 50, int $offset = 0): array`

Mengambil daftar pesanan untuk antrean kasir (diurutkan terbaru dulu `waktu_pemesanan DESC, id DESC`) dalam satu query tanpa N+1. Parameter `$status` bersifat opsional (mis. `'diproses'`, `'siap'`, `'selesai'`, `'batal'`, atau `null` untuk semua status).

- Setiap elemen memuat: `id`, `nomor`, `no_meja`, `nama_pelanggan`, `catatan`, `total_harga`, `status_pemesanan`, `label_status`, `metode_pembayaran`, `status_pembayaran`, `waktu_pembayaran`, `waktu_pemesanan`, `diperbarui_pada`, `pengguna_id`, `nama_kasir`, `ringkasan_item`, `jumlah_item`, `menit_sejak_dipesan`.

### 4.3. `PemesananModel::hitungRingkas(): array`

Menghitung metrik ringkasan dashboard kasir dalam satu query agregat (`COUNT(*) FILTER`):

```php
[
    'belum_bayar'         => 2,      // jumlah pesanan menunggu_pembayaran
    'lunas_hari_ini'      => 14,     // jumlah pembayaran 'paid' hari ini (WIB)
    'pendapatan_hari_ini' => 682000, // total nominal pembayaran 'paid' hari ini (WIB)
    'per_status'          => [
        'menunggu_pembayaran' => 2,
        'diproses'            => 3,
        'siap'                => 1,
        'selesai'             => 10,
        'batal'               => 1,
    ],
]
```

### 4.4. `PemesananModel::detail(int $id): ?array`

Mengambil detail lengkap satu pesanan (atau `null` bila tidak ditemukan):
- Memuat seluruh field `PemesananModel::find($id)` (`id`, `number`, `no_meja`, `nama_pelanggan`, `email_pelanggan`, `catatan`, `subtotal`, `tax`, `total_harga`, `status_pemesanan`, `waktu_pemesanan`, `diperbarui_pada`, `deadline`, `items` beserta `options` ukuran/varian/add-on) ditambah:
  - `label_status` (`string`)
  - `label_pembayaran` (`string`)
  - `lunas` (`bool`)
  - `pengguna_id` (`?int`), `nama_kasir` (`?string`)
  - `pembayaran_id` (`?int`), `total_bayar` (`int`), `kasir_pembayaran_id` (`?int`)
