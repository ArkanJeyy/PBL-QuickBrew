/*
 * Pop-up Customization (bottom sheet) - dipakai halaman Menu (tambah item) dan Keranjang (ubah item).
 * Markup: app/Views/pelanggan/component/customization-popup.php. Gaya: css-pelanggan/popup.css.
 *
 * Minuman: semua opsi + catatan. Makanan/dessert: mode ringkas (.popup--simple), hanya catatan & jumlah.
 *
 * API: window.QuickBrewPopup.open(item, options)
 *   item    { id, name, image, description, priceValue, customizable }
 *   options {
 *     opener:  elemen yang menerima fokus kembali saat pop-up ditutup,
 *     line:    baris keranjang yang diubah (mode edit; isian awal diambil dari baris ini),
 *     onAdded: function (item) dipanggil setelah item ditambahkan (mode tambah),
 *     onClose: function (item) dipanggil saat ditutup bila opener sudah tidak terlihat
 *   }
 */
(function () {
  'use strict';

  var popup = document.querySelector('[data-popup]');
  if (!popup) return;

  var root = document.documentElement;
  var popupImage = popup.querySelector('[data-popup-image]');
  var popupName = popup.querySelector('[data-popup-name]');
  var popupDesc = popup.querySelector('[data-popup-desc]');
  var popupPrice = popup.querySelector('[data-popup-price]');
  var qtyEl = popup.querySelector('[data-popup-qty]');
  var totalEl = popup.querySelector('[data-popup-total]');
  var noteEl = popup.querySelector('[data-popup-note]');
  var submitBtn = popup.querySelector('[data-popup-submit]');
  var scroller = popup.querySelector('.sheet__scroll');
  var SUBMIT_ADD = submitBtn.textContent;
  var SUBMIT_EDIT = 'Simpan Perubahan';
  var state = { item: null, qty: 1, opener: null, line: null, onAdded: null, onClose: null };

  var formatRupiah = function (amount) {
    return 'Rp ' + String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  };

  var radios = function () {
    return Array.prototype.slice.call(popup.querySelectorAll('input[type="radio"]'));
  };

  var addonBoxes = function () {
    return Array.prototype.slice.call(popup.querySelectorAll('[data-addon]'));
  };

  var checkedAddons = function () {
    return addonBoxes().filter(function (box) { return box.checked; });
  };

  var unitPrice = function () {
    var sum = state.item ? state.item.priceValue : 0;
    // Mode ringkas: opsi minuman tersembunyi (radio default tetap tercentang) tidak dihitung
    if (!state.item || !state.item.customizable) return sum;
    // Tambahan harga dari pilihan (mis. ukuran M/L)
    radios().forEach(function (radio) {
      if (radio.checked) sum += Number(radio.getAttribute('data-price')) || 0;
    });
    checkedAddons().forEach(function (box) { sum += Number(box.getAttribute('data-price')); });
    return sum;
  };

  var refresh = function () {
    qtyEl.textContent = String(state.qty);
    totalEl.textContent = 'Total: ' + formatRupiah(unitPrice() * state.qty);
  };

  var selectedValue = function (name) {
    var input = popup.querySelector('input[name="' + name + '"]:checked');
    return input ? input.value : null;
  };

  /* Isi pilihan: default (mode tambah) atau sesuai baris keranjang (mode edit). */
  var fillOptions = function (line) {
    radios().forEach(function (radio) { radio.checked = radio.defaultChecked; });
    addonBoxes().forEach(function (box) { box.checked = false; });
    if (noteEl) noteEl.value = line ? line.note || '' : '';
    if (!line) return;

    ['size', 'type', 'sugar', 'ice'].forEach(function (name) {
      // Nilai yang tidak lagi tersedia di opsi -> tetap pakai pilihan default
      radios().forEach(function (radio) {
        if (radio.name === name && radio.value === line[name]) radio.checked = true;
      });
    });
    var addonIds = (line.addons || []).map(function (a) { return a.id; });
    addonBoxes().forEach(function (box) { box.checked = addonIds.indexOf(box.value) !== -1; });
  };

  var open = function (item, options) {
    options = options || {};
    state.item = item;
    state.line = options.line || null;
    state.qty = state.line ? state.line.quantity : 1;
    state.opener = options.opener || null;
    state.onAdded = options.onAdded || null;
    state.onClose = options.onClose || null;

    popupName.textContent = item.name;
    popupDesc.textContent = item.description;
    popupPrice.textContent = formatRupiah(item.priceValue);
    popupImage.src = item.image;
    submitBtn.textContent = state.line ? SUBMIT_EDIT : SUBMIT_ADD;

    fillOptions(state.line);
    popup.classList.toggle('popup--simple', !item.customizable);
    refresh();

    popup.hidden = false;
    root.classList.add('is-locked', 'is-popup-open');
    scroller.scrollTop = 0;
    popup.querySelector('.sheet__back').focus();
  };

  var close = function () {
    popup.hidden = true;
    root.classList.remove('is-locked', 'is-popup-open');
    if (state.opener && document.contains(state.opener) && state.opener.offsetParent !== null) {
      state.opener.focus();
    } else if (state.onClose && state.item) {
      state.onClose(state.item); // mis. tombol + sudah berganti stepper, atau kartu keranjang dirender ulang
    }
  };

  Array.prototype.forEach.call(popup.querySelectorAll('[data-popup-close]'), function (el) {
    el.addEventListener('click', close);
  });

  popup.addEventListener('change', refresh);

  popup.querySelector('[data-popup-minus]').addEventListener('click', function () {
    if (state.qty > 1) {
      state.qty -= 1;
      refresh();
    }
  });

  popup.querySelector('[data-popup-plus]').addEventListener('click', function () {
    state.qty += 1;
    refresh();
  });

  submitBtn.addEventListener('click', function () {
    var item = state.item;
    var custom = item.customizable;
    var detail = {
      id: item.id,
      name: item.name,
      image: item.image,
      description: item.description,
      basePrice: item.priceValue,
      customizable: custom,
      quantity: state.qty,
      size: custom ? selectedValue('size') : '',
      type: custom ? selectedValue('type') : '',
      sugar: custom ? selectedValue('sugar') : '',
      ice: custom ? selectedValue('ice') : '',
      addons: !custom ? [] : checkedAddons().map(function (box) {
        return {
          id: box.value,
          label: box.getAttribute('data-label'),
          price: Number(box.getAttribute('data-price'))
        };
      }),
      note: noteEl ? noteEl.value.trim() : '',
      unitPrice: unitPrice(),
      total: unitPrice() * state.qty
    };

    if (state.line && window.QuickBrewCart) {
      window.QuickBrewCart.update(state.line.lineId, detail);
    } else {
      document.dispatchEvent(new CustomEvent('cart:add', { detail: detail }));
      if (state.onAdded) state.onAdded(item);
    }
    close();
  });

  popup.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    // Lewati input opsi minuman yang tersembunyi di mode ringkas
    var focusable = Array.prototype.filter.call(popup.querySelectorAll('button, input, textarea'), function (el) {
      return el.offsetParent !== null;
    });
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

  window.QuickBrewPopup = { open: open, close: close };
})();
