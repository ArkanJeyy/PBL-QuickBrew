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