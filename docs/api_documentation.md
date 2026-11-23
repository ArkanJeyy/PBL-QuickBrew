# Dokumentasi API QuickBrew (Bahasa Indonesia)

**Base URL**: `http://localhost:8000/public/api`

Dokumentasi ini menjelaskan semua endpoint API yang tersedia. 

---

## 1. Endpoint Menu

### URL
`GET /menu`

### Deskripsi
Mengembalikan data menu *aktif* dalam format JSON. Mendukung filter berdasarkan **ID** atau **kategori**.

### Parameter Query
| Parameter | Tipe | Wajib | Keterangan |
|-----------|------|-------|------------|
| `id` | integer | opsional (mutual exclusive dengan `category`) | ID unik menu yang ingin diambil. |
| `category` | string | opsional (mutual exclusive dengan `id`) | Slug kategori (lower‑case, tanpa spasi). Contoh: `minuman`, `makanan`, `dessert`. |

> Jika tidak ada parameter, akan mengembalikan seluruh menu.

### Contoh Request
```bash
# Semua menu
curl -X GET "http://localhost:8000/public/api/menu"

# Menu dengan ID = 12
curl -X GET "http://localhost:8000/public/api/menu?id=12"

# Menu kategori "minuman"
curl -X GET "http://localhost:8000/public/api/menu?category=minuman"
```

### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Data menu berhasil diambil.",
  "data": [
    {
      "id": 1,
      "name": "Americano",
      "price": 20000,
      "description": "Espresso dengan air dan es, memiliki rasa kopi yang ringan dan menyegarkan.",
      "status": "available",
      "stock": 50,
      "category": "minuman",
      "image": "images/menu/americano.png",
      "dim": false,
      "customizable": true
    }
    // … item lain
  ]
}
```

- Bila dipanggil dengan `id`, **array `data` hanya berisi satu objek** (atau kosong bila ID tidak ada).
- Bila dipanggil dengan `category`, `data` berisi semua menu yang cocok dengan slug kategori.

### Respons Error
| Kode | Kondisi | Contoh Body |
|------|----------|-------------|
| `405` | Metode selain GET | ```json {"success":false,"message":"Method tidak diizinkan.","data":null}``` |
| `500` | Kesalahan server (mis. DB tidak terhubung) | ```json {"success":false,"message":"Terjadi kesalahan pada server.","data":null}``` |

---
## 2. Endpoint Pemesanan

**Base URL endpoint ini**: `http://localhost:8000/public/api/pemesanan.php`

---

> **Catatan:** Endpoint ini khusus untuk **pelanggan** (tanpa login, diamankan dengan token `t`).
> `GET /pemesanan.php` tanpa `action=show` sudah tidak tersedia dan dibalas `405`.
> Daftar semua pesanan untuk kasir/admin ada di **bagian 6**.

### 2.1 Buat Pesanan Baru

#### URL
`POST /pemesanan.php`

#### Deskripsi
Membuat pesanan baru dari keranjang pelanggan. Server akan menghitung ulang harga & memvalidasi stok secara server-side (tidak percaya data harga dari client).

#### Request Body (JSON)
| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|------------|
| `meja` | string | ✅ | Kode QR meja. Contoh: `"MEJA01"` s/d `"MEJA05"` |
| `nama` | string | ✅ | Nama pelanggan (maks. 100 karakter) |
| `email` | string | ✅ | Email pelanggan yang valid (maks. 150 karakter) |
| `metode` | string | ✅ | Metode pembayaran: `"tunai"` atau `"qris"` |
| `catatan` | string | ❌ | Catatan umum pesanan (maks. 150 karakter) |
| `items` | array | ✅ | Array item pesanan (lihat tabel di bawah) |

#### Struktur tiap objek dalam `items`
| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|------------|
| `id` | integer | ✅ | ID menu (dari endpoint `/menu.php`) |
| `quantity` | integer | ✅ | Jumlah (1 – 99) |
| `size` | string | ✅ hanya minuman | Ukuran: `"S"`, `"M"` (+Rp 3.000), `"L"` (+Rp 5.000) |
| `type` | string | ✅ hanya minuman | Tipe: `"Hot"` atau `"Ice"` |
| `sugar` | string | ✅ hanya minuman | Level gula: `"Normal Sugar"`, `"Less Sugar"`, `"Extra Sugar"` |
| `ice` | string | ✅ hanya minuman | Level es: `"Normal Ice"`, `"Less Ice"`, `"Extra Ice"` |
| `addons` | array | ✅ hanya minuman | Add-on: `[]`, `["extra-shot"]` (+Rp 4.000), `["grass-jelly"]` (+Rp 3.000), `["boba"]` (+Rp 3.000) |

> `size`, `type`, `sugar`, `ice`, `addons` **hanya berlaku untuk menu kategori Minuman** (`customizable: true`). Untuk Makanan & Dessert, field-field ini diabaikan — boleh tidak diisi.

#### Contoh Request

**Minuman (dengan kustomisasi):**
```json
{
  "meja"   : "MEJA01",
  "nama"   : "Budi Santoso",
  "email"  : "budi@email.com",
  "metode" : "tunai",
  "catatan": "Minta gula sedikit",
  "items"  : [
    {
      "id"      : 1,
      "quantity": 2,
      "size"    : "M",
      "type"    : "Ice",
      "sugar"   : "Normal Sugar",
      "ice"     : "Normal Ice",
      "addons"  : ["extra-shot"]
    }
  ]
}
```

**Makanan/Dessert (tanpa kustomisasi):**
```json
{
  "meja"   : "MEJA02",
  "nama"   : "Siti",
  "email"  : "siti@email.com",
  "metode" : "qris",
  "catatan": "",
  "items"  : [
    { "id": 14, "quantity": 1 }
  ]
}
```

#### Respons Berhasil (201 Created)
```json
{
  "success": true,
  "message": "Pesanan berhasil dibuat.",
  "data": {
    "id"      : 1,
    "nomor"   : "#A0001",
    "token"   : "abc123def456789a",
    "subtotal": 46000,
    "pajak"   : 4600,
    "total"   : 50600,
    "metode"  : "tunai",
    "status"  : "menunggu_pembayaran"
  }
}
```

> **Simpan `id` dan `token`** dari respons ini — keduanya dibutuhkan untuk memanggil endpoint detail pesanan (2.2).

#### Respons Error
| Kode | Kondisi | Keterangan |
|------|---------|------------|
| `405` | Method bukan POST | Method tidak diizinkan |
| `422` | Validasi gagal | Field tidak lengkap / tidak valid — lihat field `errors` |
| `422` | Item tidak valid | Stok habis, menu nonaktif, pilihan kustomisasi salah |
| `404` | Kode meja tidak ditemukan | Pastikan `meja` sesuai seed DB (`MEJA01`–`MEJA05`) |
| `500` | Kesalahan server | `{"success":false,"message":"Terjadi kesalahan pada server.","data":null}` |

**Contoh respons validasi gagal (422):**
```json
{
  "success": false,
  "message": "Validasi gagal.",
  "errors": {
    "nama" : "Nama wajib diisi (maks. 100 karakter).",
    "email": "Email tidak valid."
  },
  "data": null
}
```

---

### 2.2 Detail Satu Pesanan

#### URL
`GET /pemesanan.php?action=show&id={id}&t={token}`

#### Deskripsi
Mengembalikan detail lengkap satu pesanan beserta daftar item yang dipesan. Memerlukan **token** dari respons endpoint 2.1 untuk keamanan (mencegah akses pesanan orang lain).

#### Parameter Query
| Parameter | Tipe | Wajib | Keterangan |
|-----------|------|-------|------------|
| `action` | string | ✅ | Harus bernilai `show` |
| `id` | integer | ✅ | ID pesanan (dari field `data.id` respons POST) |
| `t` | string | ✅ | Token pesanan (dari field `data.token` respons POST) |

#### Contoh Request
```bash
GET http://localhost:8000/public/api/pemesanan.php?action=show&id=1&t=abc123def456789a
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Detail pesanan berhasil diambil.",
  "data": {
    "id"               : 1,
    "nomor"            : "#A0001",
    "nama_pelanggan"   : "Budi Santoso",
    "email_pelanggan"  : "budi@email.com",
    "no_meja"          : "1",
    "catatan"          : "Minta gula sedikit",
    "subtotal"         : 46000,
    "pajak"            : 4600,
    "total_harga"      : 50600,
    "status_pemesanan" : "menunggu_pembayaran",
    "status_pembayaran": "pending",
    "metode_pembayaran": "tunai",
    "waktu_pemesanan"  : "2026-11-11 08:12:30",
    "waktu_pembayaran" : null,
    "batas_bayar"      : "08.42",
    "items": [
      {
        "nama_menu": "Americano",
        "gambar"   : "images/menu/americano.png",
        "harga"    : 23000,
        "jumlah"   : 2,
        "subtotal" : 46000,
        "catatan"  : "M, Ice, Normal Sugar, Normal Ice, Extra Shot",
        "options"  : {
          "size"    : "M",
          "variants": ["Ice", "Normal Sugar", "Normal Ice"],
          "addons"  : [{ "label": "Extra Shot", "price": 4000 }],
          "raw"     : null
        }
      }
    ]
  }
}
```

#### Respons Error
| Kode | Kondisi |
|------|---------|
| `403` | Token tidak valid atau tidak cocok dengan ID |
| `404` | Pesanan dengan ID tersebut tidak ditemukan |
| `405` | Method bukan GET, atau `GET` tanpa `action=show` |
| `500` | Kesalahan server |

---

## 3. Endpoint Status Pesanan

**Base URL**: `http://localhost:8000/public/api/status.php`

### URL
`GET /status.php?id={id}&t={token}`

### Deskripsi
Mengembalikan status terkini pesanan beserta data tahapan/stepper (unpaid, progress, done, cancel). Dirancang ringan untuk dipanggil berkala (polling) oleh halaman pelanggan.

### Parameter Query
| Parameter | Tipe | Wajib | Keterangan |
|-----------|------|-------|------------|
| `id` | integer | ✅ | ID pesanan |
| `t` | string | ✅ | Token rahasia pesanan (didapat saat checkout/POST pemesanan) |

### Contoh Request
```bash
GET http://localhost:8000/public/api/status.php?id=1&t=091778a481317fdb
```

### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Status pesanan berhasil diambil.",
  "data": {
    "id": 1,
    "nomor": "#A0001",
    "no_meja": "1",
    "status_pemesanan": "menunggu_pembayaran",
    "status_pembayaran": "pending",
    "diperbarui_pada": "2026-11-11 08:13:55.889171",
    "view": {
      "state": "unpaid",
      "title": "Menunggu Pembayaran",
      "desc": "Pesanan belum diproses. Silakan selesaikan pembayaran.",
      "steps": [
        { "label": "Pesanan Diterima", "icon": "order", "status": "done", "time": "08.13" },
        { "label": "Menunggu Pembayaran", "icon": "pay", "status": "current", "time": "" },
        { "label": "Diproses", "icon": "process", "status": "todo", "time": "" },
        { "label": "Selesai", "icon": "done", "status": "todo", "time": "" }
      ]
    }
  }
}
```

---

## 4. Endpoint Autentikasi Staf (Kasir & Admin)

**Base URL**: `http://localhost:8000/public/api/auth.php`

Endpoint ini digunakan untuk autentikasi staf (kasir dan admin/owner) berbasis sesi (PHP Session).

---

### 4.1 Login Staf

#### URL
`POST /auth.php?action=login`  
*(atau tanpa query param: `POST /auth.php`)*

#### Deskripsi
Memverifikasi kredensial username dan password staf, lalu membuat sesi login.

#### Request Body (JSON)
| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `username` | string | ✅ | Username akun staf (misal: `"kasir"` atau `"admin"`) |
| `password` | string | ✅ | Password akun staf |

#### Contoh Request
```bash
curl -X POST "http://localhost:8000/public/api/auth.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "kasir",
    "password": "password"
  }'
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Login berhasil.",
  "data": {
    "id": 2,
    "nama": "Kasir 1",
    "username": "kasir",
    "role": "kasir"
  }
}
```

#### Respons Error
| Kode | Kondisi | Contoh Body |
|---|---|---|
| `405` | Method selain POST | `{"success": false, "message": "Method tidak diizinkan. Gunakan POST.", "data": null}` |
| `422` | Username atau password kosong | `{"success": false, "message": "Username dan password wajib diisi.", "data": null}` |
| `401` | Username atau password salah | `{"success": false, "message": "Username atau password salah.", "data": null}` |
| `429` | Terlalu banyak percobaan login gagal (rate limit) | `{"success": false, "message": "Terlalu banyak percobaan login yang gagal. Silakan coba lagi dalam 15 menit.", "data": null}` |
| `500` | Kesalahan server | `{"success": false, "message": "Terjadi kesalahan pada server.", "data": null}` |

---

### 4.2 Cek Sesi Staf Aktif

#### URL
`GET /auth.php?action=me`

#### Deskripsi
Mengembalikan informasi akun staf yang sedang login saat ini (berdasarkan cookie session). Berguna untuk proteksi halaman / guard di frontend.

#### Contoh Request
```bash
curl -X GET "http://localhost:8000/public/api/auth.php?action=me"
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Sesi aktif.",
  "data": {
    "id": 2,
    "nama": "Kasir 1",
    "username": "kasir",
    "role": "kasir"
  }
}
```

#### Respons Error
| Kode | Kondisi | Contoh Body |
|---|---|---|
| `401` | Belum login atau sesi telah kedaluwarsa | `{"success": false, "message": "Belum login atau sesi telah berakhir.", "data": null}` |

---

### 4.3 Logout Staf

#### URL
`POST /auth.php?action=logout`

#### Deskripsi
Menghapus sesi login staf dan menghancurkan sesi di server.

#### Contoh Request
```bash
curl -X POST "http://localhost:8000/public/api/auth.php?action=logout"
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Logout berhasil. Sesi telah dihapus.",
  "data": null
}
```

#### Respons Error
| Kode | Kondisi | Contoh Body |
|---|---|---|
| `405` | Method selain POST | `{"success": false, "message": "Method tidak diizinkan. Gunakan POST.", "data": null}` |

---

## 5. Endpoint Manajemen Menu (Admin)

**Base URL**: `http://localhost:8000/public/api/admin_menu.php`

Endpoint ini digunakan untuk operasi CRUD manajemen data menu oleh **Admin**. Seluruh endpoint dilindungi oleh guard `wajib_admin_api()` yang mewajibkan sesi login staf aktif dengan role `admin` (melalui `POST /auth.php?action=login`) atau header `Authorization: Bearer <token>`.

> Jika diakses tanpa autentikasi atau oleh akun selain `admin` (misalnya `kasir`), server akan menolak permintaan dengan status **403 Forbidden**:
> ```json
> {
>   "success": false,
>   "message": "Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.",
>   "data": null
> }
> ```

---

### 5.1 Daftar Semua Menu

#### URL
`GET /admin_menu.php`

#### Deskripsi
Mengembalikan seluruh daftar menu yang tersimpan di database untuk keperluan pengelolaan admin.

#### Contoh Request
```bash
curl -X GET "http://localhost:8000/public/api/admin_menu.php" \
  -b "PHPSESSID=<session_id_admin>"
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Daftar menu berhasil diambil.",
  "data": [
    {
      "id": 1,
      "name": "Americano",
      "price": 20000,
      "description": "Espresso dengan air dan es, memiliki rasa kopi yang ringan dan menyegarkan.",
      "status": "available",
      "stock": 50,
      "active": true,
      "category": "minuman",
      "image": "images/menu/americano.png",
      "dim": false,
      "customizable": true
    }
  ]
}
```

#### Respons Error
| Kode | Kondisi | Contoh Body |
|---|---|---|
| `403` | Bukan role `admin` atau belum login | `{"success": false, "message": "Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.", "data": null}` |
| `500` | Kesalahan server | `{"success": false, "message": "Terjadi kesalahan pada server.", "data": null}` |

---

### 5.2 Detail Satu Menu

#### URL
`GET /admin_menu.php?action=show&id={id}`

#### Deskripsi
Mengembalikan detail satu item menu berdasarkan ID menu.

#### Parameter Query
| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `action` | string | ✅ | Harus bernilai `show` |
| `id` | integer | ✅ | ID menu yang ingin dilihat |

#### Contoh Request
```bash
curl -X GET "http://localhost:8000/public/api/admin_menu.php?action=show&id=1" \
  -b "PHPSESSID=<session_id_admin>"
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Detail menu berhasil diambil.",
  "data": {
    "id": 1,
    "name": "Americano",
    "price": 20000,
    "description": "Espresso dengan air dan es, memiliki rasa kopi yang ringan dan menyegarkan.",
    "status": "available",
    "stock": 50,
    "active": true,
    "category": "minuman",
    "image": "images/menu/americano.png",
    "dim": false,
    "customizable": true
  }
}
```

#### Respons Error
| Kode | Kondisi | Contoh Body |
|---|---|---|
| `400` | Parameter `id` tidak valid | `{"success": false, "message": "Parameter id tidak valid.", "data": null}` |
| `403` | Bukan role `admin` atau belum login | `{"success": false, "message": "Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.", "data": null}` |
| `404` | Menu tidak ditemukan | `{"success": false, "message": "Menu tidak ditemukan.", "data": null}` |
| `500` | Kesalahan server | `{"success": false, "message": "Terjadi kesalahan pada server.", "data": null}` |

---

### 5.3 Tambah Menu Baru

#### URL
`POST /admin_menu.php?action=store`

#### Deskripsi
Menambahkan item menu baru ke database.

#### Parameter Query
| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `action` | string | ✅ | Harus bernilai `store` |

#### Request Body (JSON)
| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `name` | string | ✅ | Nama menu |
| `price` | integer | ✅ | Harga menu dalam Rupiah |
| `category` | string | ✅ | Slug atau nama kategori (`minuman`, `makanan`, `dessert`) |
| `description` | string | ❌ | Deskripsi singkat menu |
| `image` | string | ❌ | Nama file gambar menu (misal: `"kopi-hitam.png"`) |
| `stock` | integer | ❌ | Jumlah stok awal (default: `0`) |
| `active` | boolean | ❌ | Status ketersediaan menu (default: `true`) |

#### Contoh Request
```bash
curl -X POST "http://localhost:8000/public/api/admin_menu.php?action=store" \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=<session_id_admin>" \
  -d '{
    "name": "Kopi Hitam",
    "price": 15000,
    "category": "minuman",
    "description": "Kopi hitam tanpa gula",
    "image": "kopi-hitam.png",
    "stock": 20,
    "active": true
  }'
```

#### Respons Berhasil (201 Created)
```json
{
  "success": true,
  "message": "Menu berhasil dibuat.",
  "data": {
    "id": 29
  }
}
```

#### Respons Error
| Kode | Kondisi | Contoh Body |
|---|---|---|
| `400` | Kategori tidak ditemukan | `{"success": false, "message": "Kategori 'snack' tidak ditemukan.", "data": null}` |
| `403` | Bukan role `admin` atau belum login | `{"success": false, "message": "Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.", "data": null}` |
| `405` | Method bukan POST | `{"success": false, "message": "Method tidak diizinkan, gunakan POST.", "data": null}` |
| `422` | Field wajib (`name`, `price`, `category`) kosong | `{"success": false, "message": "Field 'name' wajib diisi.", "data": null}` |
| `500` | Kesalahan server | `{"success": false, "message": "Terjadi kesalahan pada server.", "data": null}` |

---

### 5.4 Perbarui Data Menu

#### URL
`PUT /admin_menu.php?action=update&id={id}`  
*(atau `PATCH /admin_menu.php?action=update&id={id}`)*

#### Deskripsi
Memperbarui sebagian atau seluruh data menu yang sudah ada berdasarkan ID menu.

#### Parameter Query
| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `action` | string | ✅ | Harus bernilai `update` |
| `id` | integer | ✅ | ID menu yang akan diperbarui |

#### Request Body (JSON — Opsional/Parsial)
| Field | Tipe | Keterangan |
|---|---|---|
| `name` | string | Nama menu baru |
| `price` | integer | Harga menu baru |
| `category` | string | Slug kategori baru (`minuman`, `makanan`, `dessert`) |
| `description` | string | Deskripsi menu baru |
| `image` | string | Nama file gambar baru |
| `stock` | integer | Jumlah stok terbaru |
| `active` | boolean | Status aktif menu (`true` / `false`) |

#### Contoh Request
```bash
curl -X PUT "http://localhost:8000/public/api/admin_menu.php?action=update&id=29" \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=<session_id_admin>" \
  -d '{
    "price": 18000,
    "stock": 35
  }'
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Menu berhasil diupdate.",
  "data": {
    "id": 29
  }
}
```

#### Respons Error
| Kode | Kondisi | Contoh Body |
|---|---|---|
| `400` | Parameter `id` tidak valid / kategori tidak ditemukan | `{"success": false, "message": "Parameter id tidak valid.", "data": null}` |
| `403` | Bukan role `admin` atau belum login | `{"success": false, "message": "Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.", "data": null}` |
| `405` | Method bukan PUT/PATCH | `{"success": false, "message": "Method tidak diizinkan, gunakan PUT/PATCH.", "data": null}` |
| `422` | Body kosong atau tidak ada field valid | `{"success": false, "message": "Tidak ada data untuk diupdate.", "data": null}` |
| `500` | Kesalahan server | `{"success": false, "message": "Terjadi kesalahan pada server.", "data": null}` |

---

### 5.5 Hapus Menu

#### URL
`DELETE /admin_menu.php?action=delete&id={id}`

#### Deskripsi
Menghapus item menu dari database berdasarkan ID menu.

#### Parameter Query
| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `action` | string | ✅ | Harus bernilai `delete` |
| `id` | integer | ✅ | ID menu yang akan dihapus |

#### Contoh Request
```bash
curl -X DELETE "http://localhost:8000/public/api/admin_menu.php?action=delete&id=29" \
  -b "PHPSESSID=<session_id_admin>"
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Menu berhasil dihapus.",
  "data": {
    "id": 29
  }
}
```

#### Respons Error
| Kode | Kondisi | Contoh Body |
|---|---|---|
| `400` | Parameter `id` tidak valid | `{"success": false, "message": "Parameter id tidak valid.", "data": null}` |
| `403` | Bukan role `admin` atau belum login | `{"success": false, "message": "Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.", "data": null}` |
| `405` | Method bukan DELETE | `{"success": false, "message": "Method tidak diizinkan, gunakan DELETE.", "data": null}` |
| `500` | Kesalahan server (misal menu masih terikat relasi pesanan) | `{"success": false, "message": "Terjadi kesalahan pada server.", "data": null}` |
---

## 6. Endpoint Kasir / Staf (Pemesanan)

**Base URL endpoint ini**: `http://localhost:8000/public/api/kasir/pemesanan.php`

### Autentikasi

Endpoint ini **wajib login** dengan role `kasir` atau `admin`.

1. Login lewat endpoint **4.1** (`POST /auth.php`).
2. Kirim cookie sesi pada request berikutnya (Postman otomatis menyimpannya; pada `fetch` gunakan `credentials: 'same-origin'`).

Respons error umum untuk semua endpoint di bagian ini:

| Kode | Kondisi |
|------|---------|
| `401` | Belum login atau sesi berakhir |
| `403` | Sudah login, tetapi role bukan `kasir` / `admin` |
| `405` | Method atau `action` tidak diizinkan |
| `500` | Kesalahan server (`{"success":false,"message":"Terjadi kesalahan pada server.","data":null}`) |

---

### 6.1 Daftar Pesanan

#### URL
`GET /kasir/pemesanan.php`

#### Deskripsi
Mengembalikan daftar pesanan, diurutkan dari yang terbaru, lengkap dengan ringkasan item dan data pembayaran. Cocok untuk dashboard kasir.

#### Parameter Query (opsional)
| Parameter | Tipe | Default | Keterangan |
|-----------|------|---------|------------|
| `status` | string | semua | Filter status: `menunggu_pembayaran`, `diproses`, `siap`, `selesai`, `batal` |
| `limit` | integer | `50` | Jumlah data per halaman (maks. `200`) |
| `offset` | integer | `0` | Lewati sejumlah data awal (untuk halaman berikutnya) |

#### Contoh Request
```bash
# Semua pesanan (50 terbaru)
GET http://localhost:8000/public/api/kasir/pemesanan.php

# Pesanan yang sedang diproses
GET http://localhost:8000/public/api/kasir/pemesanan.php?status=diproses

# Halaman kedua (data ke-51 dst.)
GET http://localhost:8000/public/api/kasir/pemesanan.php?limit=50&offset=50
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Daftar pesanan berhasil diambil.",
  "data": [
    {
      "id": 1,
      "nomor": "#A0001",
      "no_meja": "1",
      "nama_pelanggan": "Budi Santoso",
      "catatan": "Minta gula sedikit",
      "total_harga": 50600,
      "status_pemesanan": "menunggu_pembayaran",
      "label_status": "Menunggu pembayaran",
      "metode_pembayaran": "tunai",
      "status_pembayaran": "pending",
      "waktu_pembayaran": null,
      "waktu_pemesanan": "2026-11-11 08:13:55.889171",
      "diperbarui_pada": "2026-11-11 08:13:55.889171",
      "pengguna_id": null,
      "nama_kasir": null,
      "ringkasan_item": "Americano",
      "jumlah_item": 2,
      "menit_sejak_dipesan": 15149
    }
  ]
}
```

#### Respons Error (tambahan)
| Kode | Kondisi |
|------|---------|
| `422` | Nilai `status` tidak valid |

---

### 6.2 Detail Satu Pesanan

#### URL
`GET /kasir/pemesanan.php?action=show&id={id}`

#### Deskripsi
Mengembalikan detail lengkap satu pesanan: data pelanggan, rincian item beserta kustomisasinya, informasi pembayaran, dan kasir yang memproses. Tidak memerlukan token `t` karena diamankan dengan login staf.

#### Parameter Query
| Parameter | Tipe | Wajib | Keterangan |
|-----------|------|-------|------------|
| `action` | string | ✅ | Harus bernilai `show` |
| `id` | integer | ✅ | ID pesanan |

#### Contoh Request
```bash
GET http://localhost:8000/public/api/kasir/pemesanan.php?action=show&id=1
```

#### Isi `data` (respons 200 OK)
Nama field mengikuti `PemesananModel::detail()`:

| Field | Keterangan |
|-------|------------|
| `id`, `number`, `no_meja` | Identitas pesanan dan nomor meja |
| `nama_pelanggan`, `email_pelanggan`, `catatan` | Data pelanggan dan catatan pesanan |
| `subtotal`, `tax`, `total_harga` | Rincian biaya |
| `status_pemesanan`, `label_status` | Status pesanan dan labelnya |
| `label_pembayaran`, `lunas` | Status pembayaran (label dan boolean) |
| `waktu_pemesanan`, `diperbarui_pada`, `deadline` | Waktu terkait pesanan |
| `items[]` | Daftar item: nama menu, harga, jumlah, subtotal, catatan, `options` (ukuran, varian, add-on) |
| `pengguna_id`, `nama_kasir` | Kasir yang memproses (dapat `null`) |
| `pembayaran_id`, `total_bayar`, `kasir_pembayaran_id` | Data pembayaran |

#### Respons Error (tambahan)
| Kode | Kondisi |
|------|---------|
| `400` | Parameter `id` tidak valid |
| `404` | Pesanan tidak ditemukan |

---

### 6.3 Ubah Status Pesanan

#### URL
`PATCH /kasir/pemesanan.php?action=update&id={id}`  (`PUT` juga diterima)

#### Deskripsi
Mengubah status pesanan sesuai alur resmi. Aturan transisi ditentukan di `PemesananModel::PETA_TRANSISI`; siapa yang mengubah (id staf dari sesi) dicatat di log audit.

#### Header
| Header | Nilai |
|--------|-------|
| `Content-Type` | `application/json` (wajib) |

#### Body (JSON)
```json
{ "status": "siap" }
```

#### Transisi status yang diizinkan
| Dari | Ke |
|------|----|
| `menunggu_pembayaran` | `batal` |
| `diproses` | `siap` |
| `siap` | `selesai` |

Pesanan yang sudah lunas (`diproses`, `siap`, `selesai`) tidak dapat dibatalkan. Perpindahan dari `menunggu_pembayaran` ke `diproses` terjadi saat pembayaran lunas (QRIS otomatis, tunai dikonfirmasi kasir), bukan lewat endpoint ini.

#### Contoh Request
```bash
PATCH http://localhost:8000/public/api/kasir/pemesanan.php?action=update&id=1
Content-Type: application/json

{ "status": "siap" }
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Status pesanan berhasil diperbarui.",
  "data": { "id": 1, "status": "siap" }
}
```

#### Respons Error (tambahan)
| Kode | Kondisi | Contoh Pesan |
|------|---------|--------------|
| `400` | Parameter `id` tidak valid | `Parameter id tidak valid.` |
| `404` | Pesanan tidak ditemukan | `Pesanan tidak ditemukan.` |
| `409` | Transisi tidak diizinkan dari status saat ini | `Perubahan status tidak diizinkan dari status saat ini.` |
| `415` | `Content-Type` bukan JSON | `Content-Type harus application/json` |
| `422` | Field `status` kosong atau nilainya tidak valid | `Nilai status tidak valid.` |