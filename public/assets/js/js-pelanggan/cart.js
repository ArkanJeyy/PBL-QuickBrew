/* Halaman Keranjang: render daftar, ubah jumlah lewat stepper [- n +], ubah minuman (pensil),
   dan hitung ringkasan. Tombol - pada jumlah 1 menghapus item lewat dialog konfirmasi. Vanilla JS. */
(function () {
  'use strict';

  var TAX_RATE = 0.1; // Figma: "Tax (10%)"

  var cart = window.QuickBrewCart;
  var list = document.querySelector('[data-cart-list]');
  var template = document.getElementById('cart-item-template');
  if (!cart || !list || !template) return;

  var countEl = document.querySelector('[data-cart-count]');
  var emptyEl = document.querySelector('[data-cart-empty]');
  var subtotalEl = document.querySelector('[data-cart-subtotal]');
  var taxEl = document.querySelector('[data-cart-tax]');
  var totalEl = document.querySelector('[data-cart-total]');
  var checkoutEl = document.querySelector('[data-cart-checkout]');

  var dialog = document.querySelector('[data-confirm]');
  var dialogText = dialog ? dialog.querySelector('[data-confirm-text]') : null;
  var dialogOk = dialog ? dialog.querySelector('[data-confirm-ok]') : null;
  var dialogCancel = dialog ? dialog.querySelector('button[data-confirm-cancel]') : null;
  var pendingLineId = null;
  var pendingTrigger = null;

  var formatRupiah = function (amount) {
    return 'Rp ' + String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  };

  var buildItem = function (item) {
    var node = template.content.firstElementChild.cloneNode(true);
    var img = node.querySelector('[data-image]');
    var variants = node.querySelector('[data-variants]');
    var edit = node.querySelector('[data-cart-edit]');

    node.setAttribute('data-line-id', item.lineId);
    node.querySelector('[data-name]').textContent = item.name;

    // Tipe | Level Ice | Level Gula (urutan seperti di Figma)
    [item.type, item.ice, item.sugar].filter(Boolean).forEach(function (text, index) {
      if (index > 0) {
        var sep = document.createElement('span');
        sep.setAttribute('aria-hidden', 'true');
        sep.textContent = '|';
        variants.appendChild(sep);
      }
      var span = document.createElement('span');
      span.textContent = text;
      variants.appendChild(span);
    });

    if (item.addons.length) {
      node.querySelector('[data-addon]').textContent = 'Add-on: ' + item.addons.map(function (a) {
        return a.label;
      }).join(', ');
    }

    node.querySelector('[data-price]').textContent = formatRupiah(item.unitPrice * item.quantity);

    if (item.image) {
      img.src = item.image;
    } else {
      img.remove();
    }

    // Hanya minuman yang bisa diubah (pop-up Custom); makanan/dessert tanpa tombol pensil
    if (item.customizable) {
      edit.setAttribute('aria-label', 'Ubah ' + item.name);
    } else {
      edit.remove();
    }

    // Stepper jumlah [- n +]
    node.querySelector('[data-cart-stepper]').setAttribute('aria-label', 'Jumlah ' + item.name);
    node.querySelector('[data-cart-qty]').textContent = String(item.quantity);
    node.querySelector('[data-cart-minus]').setAttribute('aria-label', 'Kurangi ' + item.name);
    node.querySelector('[data-cart-plus]').setAttribute('aria-label', 'Tambah ' + item.name);
    return node;
  };

  var render = function () {
    var items = cart.items();
    var quantity = 0;
    var subtotal = 0;

    list.textContent = '';
    items.forEach(function (item) {
      quantity += item.quantity;
      subtotal += item.unitPrice * item.quantity;
      list.appendChild(buildItem(item));
    });

    var tax = Math.round(subtotal * TAX_RATE);

    countEl.textContent = 'Daftar Pesanan (' + quantity + ' Item)';
    emptyEl.hidden = items.length > 0;
    subtotalEl.textContent = formatRupiah(subtotal);
    taxEl.textContent = formatRupiah(tax);
    totalEl.textContent = formatRupiah(subtotal + tax);

    if (items.length) {
      checkoutEl.removeAttribute('aria-disabled');
      checkoutEl.removeAttribute('tabindex');
    } else {
      checkoutEl.setAttribute('aria-disabled', 'true');
      checkoutEl.setAttribute('tabindex', '-1');
    }
  };

  /* Fokus ke tombol stepper ([data-cart-minus] / [data-cart-plus]) pada baris yang sama setelah render ulang. */
  var focusRowControl = function (lineId, selector) {
    var target = list.querySelector('.cart-item[data-line-id="' + lineId + '"] ' + selector);
    if (target) target.focus();
  };

  /* ---------- Konfirmasi hapus: dipakai saat tombol - ditekan pada jumlah 1 ---------- */
  var openConfirm = function (lineId, trigger) {
    var item = cart.items().filter(function (i) { return i.lineId === lineId; })[0];
    if (!item || !dialog) return;

    pendingLineId = lineId;
    pendingTrigger = trigger;
    dialogText.textContent = item.name + ' akan dihapus dari keranjang.';

    dialog.hidden = false;
    document.documentElement.classList.add('is-locked');
    dialogCancel.focus(); // fokus awal di "Batal" agar tidak terhapus tanpa sengaja
  };

  var closeConfirm = function (restoreFocus) {
    dialog.hidden = true;
    document.documentElement.classList.remove('is-locked');
    if (restoreFocus && pendingTrigger && document.contains(pendingTrigger)) pendingTrigger.focus();
    pendingLineId = null;
    pendingTrigger = null;
  };

  if (dialog) {
    Array.prototype.forEach.call(dialog.querySelectorAll('[data-confirm-cancel]'), function (el) {
      el.addEventListener('click', function () { closeConfirm(true); });
    });

    dialogOk.addEventListener('click', function () {
      var lineId = pendingLineId;
      closeConfirm(false);
      if (!lineId) return;

      cart.decrement(lineId); // jumlah 1 -> baris terhapus; memicu cart:change -> render ulang

      // Fokus: tombol - item pertama yang tersisa, atau "Tambah Item" bila keranjang kosong
      var again = list.querySelector('[data-cart-minus]')
        || document.querySelector('.cart-actions .btn');
      if (again) again.focus();
    });

    dialog.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeConfirm(true);
        return;
      }
      if (event.key !== 'Tab') return;
      var first = dialogCancel;
      var last = dialogOk;
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  }

  list.addEventListener('click', function (event) {
    var row = event.target.closest('.cart-item');
    if (!row) return;
    var lineId = row.getAttribute('data-line-id');

    /* Stepper: + menambah satu porsi; - mengurangi satu porsi, pada jumlah 1 minta konfirmasi hapus dulu. */
    if (event.target.closest('[data-cart-plus]')) {
      cart.increment(lineId); // memicu cart:change -> render ulang
      focusRowControl(lineId, '[data-cart-plus]');
      return;
    }

    var minusBtn = event.target.closest('[data-cart-minus]');
    if (minusBtn) {
      var line = cart.items().filter(function (i) { return i.lineId === lineId; })[0];
      if (!line) return;
      if (line.quantity > 1) {
        cart.decrement(lineId);
        focusRowControl(lineId, '[data-cart-minus]');
      } else {
        openConfirm(lineId, minusBtn);
      }
      return;
    }

    /* Pensil: buka pop-up Custom berisi pilihan baris ini; "Simpan Perubahan" memanggil cart.update(). */
    var editBtn = event.target.closest('[data-cart-edit]');
    if (editBtn && window.QuickBrewPopup) {
      var item = cart.items().filter(function (i) { return i.lineId === lineId; })[0];
      if (!item || !item.customizable) return;
      window.QuickBrewPopup.open({
        id: item.id,
        name: item.name,
        image: item.image,
        description: item.description,
        priceValue: item.basePrice,
        customizable: true
      }, {
        line: item,
        opener: editBtn,
        // Kartu dirender ulang setelah disimpan: fokus ke pensil baris yang sama, atau baris pertama yang setara
        onClose: function () {
          var target = list.querySelector('.cart-item[data-line-id="' + lineId + '"] [data-cart-edit]')
            || list.querySelector('.cart-item [data-cart-edit]');
          if (target) target.focus();
        }
      });
    }
  });

  document.addEventListener('cart:change', render);
  window.addEventListener('storage', function (event) {
    if (event.key === cart.key) render();
  });

  render();
})();
