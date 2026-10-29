/*
 * Penyimpanan keranjang (sisi klien) - dimuat di halaman Menu dan Keranjang.
 *
 * Belum ada backend/database, sehingga keranjang disimpan di localStorage browser.
 * Seluruh akses lewat window.QuickBrewCart agar mudah diganti ke API server nanti.
 * Event:
 *   'cart:add'    (dikirim pop-up Customization) -> item ditambahkan.
 *   'cart:change' (dikirim modul ini)            -> isi keranjang berubah.
 */
(function () {
  'use strict';

  var KEY = 'quickbrew.cart.v1';
  var memory = [];

  var sanitize = function (raw) {
    if (!raw || typeof raw !== 'object') return null;
    var quantity = Math.floor(Number(raw.quantity));
    var unitPrice = Number(raw.unitPrice);
    if (!raw.id || !raw.name || !isFinite(unitPrice) || !(quantity >= 1)) return null;
    return {
      lineId: String(raw.lineId || (Date.now().toString(36) + Math.random().toString(36).slice(2, 8))),
      id: String(raw.id),
      name: String(raw.name),
      image: raw.image ? String(raw.image) : '',
      description: raw.description ? String(raw.description) : '',
      basePrice: isFinite(Number(raw.basePrice)) ? Number(raw.basePrice) : unitPrice,
      quantity: quantity,
      size: raw.size ? String(raw.size) : '',
      type: raw.type ? String(raw.type) : '',
      sugar: raw.sugar ? String(raw.sugar) : '',
      ice: raw.ice ? String(raw.ice) : '',
      addons: Array.isArray(raw.addons) ? raw.addons.map(function (a) {
        return { id: String(a.id), label: String(a.label), price: Number(a.price) || 0 };
      }) : [],
      unitPrice: unitPrice
    };
  };

  var read = function () {
    try {
      var raw = window.localStorage.getItem(KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      if (Array.isArray(parsed)) {
        memory = parsed.map(sanitize).filter(Boolean);
      }
    } catch (err) {
      /* localStorage tidak tersedia: pakai memori sementara */
    }
    return memory.slice();
  };

  var write = function (items) {
    memory = items;
    try {
      window.localStorage.setItem(KEY, JSON.stringify(items));
    } catch (err) {
      /* abaikan: data tetap ada di memori selama halaman terbuka */
    }
    document.dispatchEvent(new CustomEvent('cart:change', { detail: { items: items.slice() } }));
  };

  /* Pesanan identik (menu + semua pilihan sama) digabung menjadi satu baris. */
  var signature = function (item) {
    return [
      item.id, item.size, item.type, item.sugar, item.ice,
      item.addons.map(function (a) { return a.id; }).sort().join('+')
    ].join('|');
  };

  var cart = {
    key: KEY,
    items: read,
    count: function () {
      return read().reduce(function (sum, item) { return sum + item.quantity; }, 0);
    },
    add: function (detail) {
      var item = sanitize(detail);
      if (!item) return;
      var items = read();
      var existing = items.filter(function (i) { return signature(i) === signature(item); })[0];
      if (existing) {
        existing.quantity += item.quantity;
      } else {
        items.push(item);
      }
      write(items);
    },
    remove: function (lineId) {
      write(read().filter(function (item) { return item.lineId !== lineId; }));
    },
    /* Hapus satu porsi: jumlah > 1 dikurangi 1; jumlah 1 menghapus barisnya. */
    decrement: function (lineId) {
      var next = [];
      read().forEach(function (item) {
        if (item.lineId !== lineId) {
          next.push(item);
        } else if (item.quantity > 1) {
          item.quantity -= 1;
          next.push(item);
        }
      });
      write(next);
    },
    /* Total jumlah satu menu di seluruh baris (varian kustomisasi berbeda dijumlahkan). */
    countFor: function (menuId) {
      return read().reduce(function (sum, item) { return item.id === menuId ? sum + item.quantity : sum; }, 0);
    },
    /* Kurangi satu porsi menu dari baris yang terakhir ditambahkan (dipakai stepper di daftar menu). */
    decrementItem: function (menuId) {
      var items = read();
      for (var i = items.length - 1; i >= 0; i--) {
        if (items[i].id === menuId) {
          cart.decrement(items[i].lineId);
          return;
        }
      }
    },
    clear: function () {
      write([]);
    }
  };

  window.QuickBrewCart = cart;

  document.addEventListener('cart:add', function (event) {
    cart.add(event.detail);
  });
})();
