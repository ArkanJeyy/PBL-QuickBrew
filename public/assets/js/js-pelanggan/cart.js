/* Halaman Keranjang: render daftar, hapus item, dan hitung ringkasan. Vanilla JS. */
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
    return 'Rp ' + String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',00';
  };

  var buildItem = function (item) {
    var node = template.content.firstElementChild.cloneNode(true);
    var img = node.querySelector('[data-image]');
    var variants = node.querySelector('[data-variants]');
    var qty = node.querySelector('[data-qty]');
    var edit = node.querySelector('[data-cart-edit]');
    var del = node.querySelector('[data-cart-delete]');

    node.setAttribute('data-line-id', item.lineId);
    node.querySelector('[data-name]').textContent = item.name;
    if (item.quantity > 1) {
      qty.textContent = item.quantity + 'x';
      qty.hidden = false;
    }

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

    edit.setAttribute('aria-label', 'Ubah ' + item.name);
    del.setAttribute('aria-label', 'Hapus ' + item.name);
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

  /* ---------- Konfirmasi hapus: satu porsi per konfirmasi ---------- */
  var openConfirm = function (lineId, trigger) {
    var item = cart.items().filter(function (i) { return i.lineId === lineId; })[0];
    if (!item || !dialog) return;

    pendingLineId = lineId;
    pendingTrigger = trigger;
    dialogText.textContent = item.quantity > 1
      ? 'Satu porsi ' + item.name + ' akan dihapus dari keranjang. Sisa pesanan: ' + (item.quantity - 1) + 'x.'
      : item.name + ' akan dihapus dari keranjang.';

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

      cart.decrement(lineId); // memicu cart:change -> render ulang

      // Fokus: tombol hapus baris yang sama bila masih ada, kalau tidak ke item pertama / "Tambah Item"
      var again = list.querySelector('.cart-item[data-line-id="' + lineId + '"] [data-cart-delete]')
        || list.querySelector('[data-cart-delete]')
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

    if (event.target.closest('[data-cart-delete]')) {
      openConfirm(lineId, event.target.closest('[data-cart-delete]'));
      return;
    }

    /* Perilaku "ubah" belum ada di desain: kirim event agar bisa dihubungkan nanti. */
    if (event.target.closest('[data-cart-edit]')) {
      var item = cart.items().filter(function (i) { return i.lineId === lineId; })[0];
      if (item) document.dispatchEvent(new CustomEvent('cart:edit', { detail: item }));
    }
  });

  document.addEventListener('cart:change', render);
  window.addEventListener('storage', function (event) {
    if (event.key === cart.key) render();
  });

  render();
})();
