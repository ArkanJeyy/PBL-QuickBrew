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

## 2. Endpoint Status Pesanan JSON (Polling Pelanggan)

### URL
`GET /public/?page=status-json&id=<id>&t=<token_hmac>`

### Deskripsi
Mengembalikan status terkini satu pesanan dalam format JSON untuk kebutuhan *polling* otomatis halaman Pembayaran dan Status Pesanan di sisi front-end (disarankan interval **4–5 detik**).

- Mengirim header `Content-Type: application/json; charset=utf-8` dan `Cache-Control: no-store`.
- Bila metode pembayaran adalah **QRIS** dan status masih `menunggu_pembayaran`, endpoint ini otomatis memanggil sinkronisasi cadangan `PembayaranService::sinkron()` dengan *throttle* sesi **5 detik** di dalam blok `try/catch` (gangguan jaringan ke Midtrans dicatat di log dan tidak merusak respons JSON).
- Dibatasi **30 permintaan per 60 detik per (IP + ID pesanan)**.

### Parameter Query
| Parameter | Tipe | Wajib | Keterangan |
|-----------|------|-------|------------|
| `page` | string | ya | Nilai tetap: `status-json` |
| `id` | integer | ya | ID pesanan (`pemesanan.id`) |
| `t` | string | ya | Token HMAC 16 karakter dari `tok($id)` |

### Contoh Request
```bash
curl -X GET "http://localhost/PBL/PBL-QuickBrew/public/?page=status-json&id=12&t=a1b2c3d4e5f60718"
```

### Respons Berhasil (`200 OK`)
```json
{
  "ok": true,
  "data": {
    "id": 12,
    "nomor": "#A0012",
    "status": "diproses",
    "label": "Pesanan Diproses",
    "lunas": true,
    "metode": "qris",
    "kedaluwarsa": "18.20",
    "diperbarui": "2026-11-10 18:06:12"
  }
}
```

### Nilai `data.status` & `data.label`
| `status` | `lunas` | `label` |
|----------|---------|---------|
| `menunggu_pembayaran` | `false` | `Menunggu Pembayaran` |
| `diproses` | `true` | `Pesanan Diproses` |
| `siap` | `true` | `Pesanan Siap` |
| `selesai` | `true` | `Selamat Menikmati` |
| `batal` | `false` | `Pesanan Dibatalkan` |

### Respons Error
| Kode | Kondisi | Contoh Body |
|------|---------|-------------|
| `404` | Parameter `id` atau token `t` tidak valid / pesanan tidak ditemukan | `{"ok":false,"error":"Pesanan tidak ditemukan."}` |
| `429` | Melebihi batas 30 permintaan / 60 detik per (IP + ID pesanan) | `{"ok":false,"error":"Terlalu banyak permintaan. Silakan coba lagi sebentar."}` |

---