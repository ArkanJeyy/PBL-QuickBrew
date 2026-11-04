/*
 * Halaman Checkout: tampilkan ringkasan dari keranjang (cart-store.js), hitung pajak & total untuk
 * tampilan, isi field tersembunyi "items" untuk dikirim ke server, tampilkan dialog "Konfirmasi Pesanan"
 * sebelum form dikirim, dan cegah kirim ganda.
 * Harga final tetap dihitung ulang server (PemesananModel::price). Vanilla JS.
 */
(function () {
  'use strict';

  var cart = window.QuickBrewCart;
  var form = document.querySelector('[data-checkout-form]');
  var template = document.getElementById('checkout-item-template');
  if (!cart || !form || !template) return;

  var list = form.querySelector('[data-checkout-list]');
  var countEl = form.querySelector('[data-checkout-count]');
  var emptyEl = form.querySelector('[data-checkout-empty]');
  var totalsEl = form.querySelector('[data-checkout-totals]');
  var taxEl = form.querySelector('[data-checkout-tax]');
  var totalEl = form.querySelector('[data-checkout-total]');
  var itemsInput = form.querySelector('[data-checkout-items]');
  var submitBtn = form.querySelector('[data-checkout-submit]');
  var submitText = form.querySelector('[data-checkout-submit-text]');
  var submitTotal = form.querySelector('[data-checkout-submit-total]');
  var taxRate = Number(form.getAttribute('data-tax-rate')) || 0;

  var formatRupiah = function (amount) {
    return 'Rp ' + String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  };

  var buildItem = function (item) {
    var node = template.content.firstElementChild.cloneNode(true);
    var img = node.querySelector('[data-image]');
    var variants = node.querySelector('[data-variants]');
    var addons = node.querySelector('[data-addons]');

    node.querySelector('[data-name]').textContent = item.name + (item.size ? ' (' + item.size + ')' : '');
    node.querySelector('[data-qty]').textContent = item.quantity + 'x';
    node.querySelector('[data-price]').textContent = formatRupiah(item.unitPrice * item.quantity);

    if (item.image) {
      img.src = item.image;
    } else {
      img.remove();
    }

    // Tipe | Level Gula | Level Ice (urutan seperti desain Checkout)
    var picks = [item.type, item.sugar, item.ice].filter(Boolean);
    if (picks.length) {
      variants.textContent = picks.join('  |  ');
    } else {
      variants.remove();
    }

    if (item.addons.length) {
      addons.textContent = 'Tambahan: ' + item.addons.map(function (a) {
        return a.label + ' (+' + formatRupiah(a.price) + ')';
      }).join(', ');
    } else {
      addons.remove();
    }
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

    var tax = Math.round(subtotal * taxRate);
    var total = subtotal + tax;

    countEl.textContent = 'Ringkasan Pesanan (' + quantity + ' Item)';
    emptyEl.hidden = items.length > 0;
    totalsEl.hidden = items.length === 0;
    taxEl.textContent = formatRupiah(tax);
    totalEl.textContent = formatRupiah(total);
    submitTotal.textContent = formatRupiah(total);
    submitBtn.disabled = items.length === 0;

    // Data yang dikirim ke server: hanya pilihan, bukan harga (harga dihitung ulang di server)
    itemsInput.value = JSON.stringify(items.map(function (item) {
      return {
        id: item.id,
        quantity: item.quantity,
        size: item.size,
        type: item.type,
        sugar: item.sugar,
        ice: item.ice,
        addons: item.addons.map(function (a) { return a.id; })
      };
    }));
  };

  /* ---------- Dialog "Konfirmasi Pesanan" ----------
     "Buat Pesanan" (setelah validasi bawaan browser lolos) membuka dialog; form baru benar-benar
     dikirim setelah tombol "Konfirmasi Pesanan" ditekan. */
  var root = document.documentElement;
  var dialog = form.querySelector('[data-confirm-order]');
  var confirmBtn = dialog ? dialog.querySelector('[data-confirm-submit]') : null;
  var confirmed = false; // tombol "Konfirmasi Pesanan" sudah ditekan
  var sent = false;      // form sudah terkirim sekali (cegah pesanan tersimpan dua kali)

  var fieldValue = function (name) {
    var input = form.querySelector('[name="' + name + '"]');
    return input ? input.value.trim() : '';
  };

  var fillDialog = function () {
    var metode = form.querySelector('input[name="metode"]:checked');
    var metodeLabel = metode ? metode.parentNode.querySelector('.pay-option__label') : null;

    dialog.querySelector('[data-confirm-nama]').textContent = fieldValue('nama');
    dialog.querySelector('[data-confirm-email]').textContent = fieldValue('email');
    dialog.querySelector('[data-confirm-metode]').textContent = metodeLabel ? metodeLabel.textContent : '';

    // Catatan (opsional): kosong -> "Tidak diisi" berwarna abu-abu
    var catatan = fieldValue('catatan');
    var catatanEl = dialog.querySelector('[data-confirm-catatan]');
    catatanEl.textContent = catatan || 'Tidak diisi';
    catatanEl.classList.toggle('confirm-order__value--empty', !catatan);

    // Ringkasan sama persis dengan yang tampil di Checkout
    var confirmList = dialog.querySelector('[data-confirm-list]');
    confirmList.textContent = '';
    Array.prototype.forEach.call(list.children, function (row) {
      confirmList.appendChild(row.cloneNode(true));
    });
    dialog.querySelector('[data-confirm-count]').textContent = countEl.textContent;
    dialog.querySelector('[data-confirm-tax]').textContent = taxEl.textContent;
    dialog.querySelector('[data-confirm-total]').textContent = totalEl.textContent;
  };

  var openDialog = function () {
    fillDialog();
    dialog.hidden = false;
    root.classList.add('is-locked');
    dialog.querySelector('.sheet__scroll').scrollTop = 0;
    confirmBtn.focus();
  };

  var closeDialog = function (restoreFocus) {
    dialog.hidden = true;
    root.classList.remove('is-locked');
    if (restoreFocus !== false) submitBtn.focus();
  };

  var setBusy = function (busy) {
    confirmed = busy;
    if (!busy) sent = false;
    if (confirmBtn) {
      confirmBtn.disabled = busy;
      confirmBtn.textContent = busy ? 'Memproses...' : 'Konfirmasi Pesanan';
    }
    if (busy) {
      submitBtn.setAttribute('aria-busy', 'true');
      submitText.textContent = 'Memproses...';
    } else {
      submitBtn.removeAttribute('aria-busy');
      submitText.textContent = 'Buat Pesanan';
    }
  };

  form.addEventListener('submit', function (event) {
    if (!cart.items().length) {
      event.preventDefault();
      return;
    }
    if (dialog && !confirmed) {
      event.preventDefault(); // tampilkan konfirmasi dulu
      openDialog();
      return;
    }
    if (sent) {
      event.preventDefault(); // sudah terkirim: jangan kirim lagi
      return;
    }
    sent = true;
  });

  if (dialog) {
    confirmBtn.addEventListener('click', function () {
      if (confirmed) return;
      setBusy(true);
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });

    Array.prototype.forEach.call(dialog.querySelectorAll('[data-confirm-edit]'), function (el) {
      el.addEventListener('click', function () { closeDialog(); });
    });

    dialog.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !confirmed) {
        closeDialog();
        return;
      }
      if (event.key !== 'Tab') return;
      // Jaga fokus di dalam dialog
      var focusable = Array.prototype.filter.call(dialog.querySelectorAll('button'), function (el) {
        return !el.disabled && el.offsetParent !== null;
      });
      if (!focusable.length) return;
      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  }

  // Kembali ke halaman ini dari riwayat browser (bfcache): tutup dialog & aktifkan lagi tombol
  window.addEventListener('pageshow', function () {
    setBusy(false);
    if (dialog && !dialog.hidden) closeDialog(false);
    render();
  });

  document.addEventListener('cart:change', render);
  window.addEventListener('storage', function (event) {
    if (event.key === cart.key) render();
  });

  render();
})();
