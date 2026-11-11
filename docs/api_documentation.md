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
| `500` | Kesalahan server (mis. DB tidak terhubung) | ```json {"success":false,"message":"Terjadi kesalahan pada server.","debug":"<pesan error>","data":null}``` |

---
## 2. Endpoint Pemesanan

**Base URL endpoint ini**: `http://localhost:8000/public/api/pemesanan.php`

---

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

> **Simpan `id` dan `token`** dari respons ini — keduanya dibutuhkan untuk memanggil endpoint detail pesanan (2.3).

#### Respons Error
| Kode | Kondisi | Keterangan |
|------|---------|------------|
| `405` | Method bukan POST | Method tidak diizinkan |
| `422` | Validasi gagal | Field tidak lengkap / tidak valid — lihat field `errors` |
| `422` | Item tidak valid | Stok habis, menu nonaktif, pilihan kustomisasi salah |
| `404` | Kode meja tidak ditemukan | Pastikan `meja` sesuai seed DB (`MEJA01`–`MEJA05`) |
| `500` | Kesalahan server | Lihat field `debug` |

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

### 2.2 Daftar Semua Pesanan

#### URL
`GET /pemesanan.php`

#### Deskripsi
Mengembalikan daftar semua pesanan, diurutkan dari yang terbaru. Cocok untuk dashboard kasir.

#### Parameter Query (opsional)
| Parameter | Tipe | Keterangan |
|-----------|------|------------|
| `id`      | integer | Filter berdasarkan ID pesanan tertentu |
| `status`  | string | Filter status pesanan. Nilai valid: `menunggu_pembayaran`, `dibayar`, `diproses`, `siap`, `selesai`, `batal` |

#### Contoh Request
```bash
# Semua pesanan
GET http://localhost:8000/public/api/pemesanan.php

# Filter pesanan yang menunggu pembayaran
GET http://localhost:8000/public/api/pemesanan.php?status=menunggu_pembayaran

# Filter pesanan by ID pemesanan
GET http://localhost:8000/public/api/pemesanan.php?id=1
```

#### Respons Berhasil (200 OK)
```json
{
  "success": true,
  "message": "Data pemesanan berhasil diambil.",
  "data": [
    {
      "id"               : 1,
      "nomor"            : "#A0001",
      "nama_pelanggan"   : "Budi Santoso",
      "email_pelanggan"  : "budi@email.com",
      "no_meja"          : "1",
      "catatan"          : "Minta gula sedikit",
      "total_harga"      : 50600,
      "status_pemesanan" : "menunggu_pembayaran",
      "status_pembayaran": "pending",
      "metode_pembayaran": "tunai",
      "waktu_pemesanan"  : "2026-11-11 08:12:30",
      "waktu_pembayaran" : null,
      "diperbarui_pada"  : "2026-11-11 08:12:30"
    }
  ]
}
```

#### Respons Error
| Kode | Kondisi |
|------|---------|
| `405` | Method bukan GET |
| `500` | Kesalahan server |

---

### 2.3 Detail Satu Pesanan

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
| `405` | Method bukan GET |
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