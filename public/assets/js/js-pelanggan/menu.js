/* Halaman Menu: filter kategori, pencarian (modal), dan pop-up Customization. Vanilla JS. */
(function () {
  'use strict';

  var root = document.documentElement;
  var dataEl = document.getElementById('menu-data');
  var items = [];
  try {
    items = dataEl ? JSON.parse(dataEl.textContent) : [];
  } catch (err) {
    items = [];
  }
  var itemsById = {};
  items.forEach(function (item) { itemsById[item.id] = item; });

  var formatRupiah = function (amount, withDecimals) {
    var digits = String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return 'Rp ' + digits + (withDecimals ? ',00' : '');
  };

  /* Diisi oleh blok modal pencarian di bawah (dipanggil saat + ditekan dari hasil pencarian). */
  var closeSearch = null;

  /* ---------- Stepper jumlah di daftar menu ---------- */
  var cartApi = window.QuickBrewCart;
  var menuList = document.getElementById('menu-list');

  var syncSteppers = function () {
    if (!cartApi || !menuList) return;
    var counts = {};
    cartApi.items().forEach(function (entry) { counts[entry.id] = (counts[entry.id] || 0) + entry.quantity; });

    Array.prototype.forEach.call(menuList.querySelectorAll('.menu-item'), function (row) {
      var stepper = row.querySelector('[data-stepper]');
      var add = row.querySelector('.btn-add');
      if (!stepper || !add) return; // menu Habis tidak punya stepper
      var qty = counts[row.getAttribute('data-id')] || 0;
      stepper.hidden = qty < 1;
      add.hidden = qty >= 1;
      stepper.querySelector('[data-stepper-count]').textContent = String(qty);
    });
  };

  /* Fokus ke kontrol yang sedang terlihat untuk sebuah menu (stepper atau tombol +). */
  var focusItemControl = function (id) {
    if (!menuList) return;
    var row = menuList.querySelector('.menu-item[data-id="' + id + '"]');
    if (!row) return;
    var plus = row.querySelector('[data-stepper-plus]');
    var target = plus && !plus.closest('[data-stepper]').hidden ? plus : row.querySelector('.btn-add');
    if (target) target.focus();
  };

  document.addEventListener('cart:change', syncSteppers);
  window.addEventListener('storage', function (event) {
    if (cartApi && event.key === cartApi.key) syncSteppers();
  });
  window.addEventListener('pageshow', syncSteppers); // kembali dari halaman Keranjang (bfcache)
  syncSteppers();

  document.addEventListener('click', function (event) {
    var minus = event.target.closest('[data-stepper-minus]');
    if (!minus || !cartApi) return;
    var id = minus.getAttribute('data-menu-id');
    cartApi.decrementItem(id);
    focusItemControl(id);
  });

  /* Notifikasi "berhasil masuk keranjang" (terlihat + dibacakan pembaca layar lewat role=status). */
  var toastRegion = document.querySelector('[data-toast-region]');
  var TOAST_VISIBLE_MS = 2500;
  var TOAST_LEAVE_MS = 200;
  var toastTimer = null;
  var toastLeaveTimer = null;

  var notify = function (name) {
    if (!toastRegion) return;
    window.clearTimeout(toastTimer);
    window.clearTimeout(toastLeaveTimer);
    toastRegion.textContent = '';

    var toast = document.createElement('div');
    toast.className = 'toast';
    var icon = document.createElement('span');
    icon.className = 'toast__icon';
    icon.setAttribute('aria-hidden', 'true');
    var text = document.createElement('span');
    text.className = 'toast__text';
    text.textContent = name + ' berhasil ditambahkan ke keranjang';
    toast.appendChild(icon);
    toast.appendChild(text);
    toastRegion.appendChild(toast);

    toastTimer = window.setTimeout(function () {
      toast.classList.add('toast--leaving');
      toastLeaveTimer = window.setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, TOAST_LEAVE_MS);
    }, TOAST_VISIBLE_MS);
  };

  /* Menu tanpa kustomisasi (makanan, dessert): langsung masuk keranjang, jumlah 1. */
  var addDirect = function (item) {
    document.dispatchEvent(new CustomEvent('cart:add', {
      detail: {
        id: item.id,
        name: item.name,
        image: item.image,
        description: item.description,
        basePrice: item.priceValue,
        quantity: 1,
        size: '',
        type: '',
        sugar: '',
        ice: '',
        addons: [],
        unitPrice: item.priceValue,
        total: item.priceValue
      }
    }));
    notify(item.name);
  };

  /* ---------- Pop-up Customization ---------- */
  var openPopup = function () {};
  var popup = document.querySelector('[data-popup]');

  if (popup) {
    var popupImage = popup.querySelector('[data-popup-image]');
    var popupName = popup.querySelector('[data-popup-name]');
    var popupDesc = popup.querySelector('[data-popup-desc]');
    var popupPrice = popup.querySelector('[data-popup-price]');
    var qtyEl = popup.querySelector('[data-popup-qty]');
    var totalEl = popup.querySelector('[data-popup-total]');
    var scroller = popup.querySelector('.sheet__scroll');
    var state = { item: null, qty: 1, opener: null };

    var checkedAddons = function () {
      return Array.prototype.slice.call(popup.querySelectorAll('[data-addon]:checked'));
    };

    var unitPrice = function () {
      var sum = state.item ? state.item.priceValue : 0;
      // Tambahan harga dari pilihan (mis. ukuran M/L)
      Array.prototype.forEach.call(popup.querySelectorAll('input[type="radio"]:checked'), function (radio) {
        sum += Number(radio.getAttribute('data-price')) || 0;
      });
      checkedAddons().forEach(function (box) { sum += Number(box.getAttribute('data-price')); });
      return sum;
    };

    var refresh = function () {
      qtyEl.textContent = String(state.qty);
      totalEl.textContent = 'Total: ' + formatRupiah(unitPrice() * state.qty, false);
    };

    var selectedValue = function (name) {
      var input = popup.querySelector('input[name="' + name + '"]:checked');
      return input ? input.value : null;
    };

    openPopup = function (item, opener) {
      state.item = item;
      state.qty = 1;
      state.opener = opener || null;

      popupName.textContent = item.name;
      popupDesc.textContent = item.description;
      popupPrice.textContent = formatRupiah(item.priceValue, true);
      popupImage.src = item.image;

      Array.prototype.forEach.call(popup.querySelectorAll('input[type="radio"]'), function (radio) {
        radio.checked = radio.defaultChecked;
      });
      Array.prototype.forEach.call(popup.querySelectorAll('[data-addon]'), function (box) {
        box.checked = false;
      });
      refresh();

      popup.hidden = false;
      root.classList.add('is-locked', 'is-popup-open');
      scroller.scrollTop = 0;
      popup.querySelector('.sheet__back').focus();
    };

    var closePopup = function () {
      popup.hidden = true;
      root.classList.remove('is-locked', 'is-popup-open');
      if (state.opener && document.contains(state.opener) && state.opener.offsetParent !== null) {
        state.opener.focus();
      } else if (state.item) {
        focusItemControl(state.item.id); // tombol + sudah berganti menjadi stepper
      }
    };

    Array.prototype.forEach.call(popup.querySelectorAll('[data-popup-close]'), function (el) {
      el.addEventListener('click', closePopup);
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

    /* Keranjang belum diimplementasikan: kirim event agar bisa dipakai halaman Keranjang nanti. */
    popup.querySelector('[data-popup-submit]').addEventListener('click', function () {
      var detail = {
        id: state.item.id,
        name: state.item.name,
        image: state.item.image,
        description: state.item.description,
        basePrice: state.item.priceValue,
        quantity: state.qty,
        size: selectedValue('size'),
        type: selectedValue('type'),
        sugar: selectedValue('sugar'),
        ice: selectedValue('ice'),
        addons: checkedAddons().map(function (box) {
          return {
            id: box.value,
            label: box.getAttribute('data-label'),
            price: Number(box.getAttribute('data-price'))
          };
        }),
        unitPrice: unitPrice(),
        total: unitPrice() * state.qty
      };
      document.dispatchEvent(new CustomEvent('cart:add', { detail: detail }));
      notify(state.item.name);
      closePopup();
    });

    popup.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closePopup();
        return;
      }
      if (event.key !== 'Tab') return;
      var focusable = popup.querySelectorAll('button, input');
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

  /* Tombol + (daftar utama maupun hasil pencarian). Menu "Habis" tidak melakukan apa pun; makanan/dessert langsung ke keranjang. */
  document.addEventListener('click', function (event) {
    var btn = event.target.closest('.btn-add[data-menu-id], [data-stepper-plus][data-menu-id]');
    if (!btn || btn.getAttribute('aria-disabled') === 'true') return;
    var item = itemsById[btn.getAttribute('data-menu-id')];
    if (!item) return;

    if (!item.customizable) {
      addDirect(item);
      if (btn.closest('[data-search-modal]') && closeSearch) {
        closeSearch();
      } else {
        focusItemControl(item.id); // tombol + yang diklik bisa berganti menjadi stepper
      }
      return;
    }

    var opener = btn;
    if (btn.closest('[data-search-modal]') && closeSearch) {
      closeSearch(false);
      opener = document.querySelector('[data-search-open]');
    }
    openPopup(item, opener);
  });

  /* ---------- Dropdown kategori ---------- */
  var dropdown = document.querySelector('[data-dropdown]');
  var dropdownOverlay = document.querySelector('[data-dropdown-overlay]');
  var list = document.getElementById('menu-list');

  if (dropdown && list) {
    var trigger = dropdown.querySelector('.dropdown__trigger');
    var panel = dropdown.querySelector('.dropdown__panel');
    var label = dropdown.querySelector('[data-dropdown-label]');
    var options = Array.prototype.slice.call(dropdown.querySelectorAll('.dropdown__option'));

    var setDropdown = function (open) {
      panel.hidden = !open;
      if (dropdownOverlay) dropdownOverlay.hidden = !open;
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    /* Jarak antar section hanya antara item terlihat yang kategorinya berganti (bukan di item pertama). */
    var markSections = function () {
      var prev = null;
      Array.prototype.forEach.call(list.children, function (card) {
        if (card.hidden) return;
        var category = card.getAttribute('data-category');
        card.classList.toggle('menu-item--section-start', prev !== null && category !== prev);
        prev = category;
      });
    };

    var applyCategory = function (value) {
      Array.prototype.forEach.call(list.children, function (card) {
        card.hidden = value !== 'all' && card.getAttribute('data-category') !== value;
      });
      markSections();
    };

    trigger.addEventListener('click', function () {
      setDropdown(panel.hidden);
    });

    options.forEach(function (option) {
      option.addEventListener('click', function () {
        options.forEach(function (o) {
          o.setAttribute('aria-selected', o === option ? 'true' : 'false');
        });
        label.textContent = option.querySelector('.dropdown__option-label').textContent;
        applyCategory(option.getAttribute('data-value'));
        setDropdown(false);
        trigger.focus();
      });
    });

    if (dropdownOverlay) {
      dropdownOverlay.addEventListener('click', function () { setDropdown(false); });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) {
        setDropdown(false);
        trigger.focus();
      }
    });
  }

  /* ---------- Modal pencarian ---------- */
  var modal = document.querySelector('[data-search-modal]');
  var openBtn = document.querySelector('[data-search-open]');
  var template = document.getElementById('search-result-template');

  if (!modal || !openBtn || !template) return;

  var input = modal.querySelector('[data-search-input]');
  var results = modal.querySelector('[data-search-results]');
  var empty = modal.querySelector('[data-search-empty]');
  var emptyText = modal.querySelector('[data-search-empty-text]');
  var closers = modal.querySelectorAll('[data-search-close]');

  var showEmpty = function (text) {
    emptyText.textContent = text;
    empty.hidden = false;
    results.hidden = true;
    results.textContent = '';
  };

  var buildResult = function (item) {
    var node = template.content.firstElementChild.cloneNode(true);
    var img = node.querySelector('.search-result__media img');
    var chip = node.querySelector('.chip');
    var add = node.querySelector('.btn-add');
    var soldOut = item.status === 'soldout';

    img.src = item.image;
    node.querySelector('.search-result__name').textContent = item.name;
    node.querySelector('.search-result__desc').textContent = item.description;
    node.querySelector('.search-result__price').textContent = item.price;
    chip.textContent = soldOut ? 'Habis' : 'Tersedia';
    chip.classList.add(soldOut ? 'chip--soldout' : 'chip--available');
    add.setAttribute('data-menu-id', item.id);
    add.setAttribute('aria-label', 'Tambah ' + item.name);
    if (soldOut) {
      add.setAttribute('aria-disabled', 'true');
      node.classList.add('search-result--soldout');
    }
    return node;
  };

  var render = function (query) {
    var q = query.trim().toLowerCase();
    if (!q) {
      showEmpty('Ketik untuk mencari menu');
      return;
    }
    var matches = items.filter(function (item) {
      return item.name.toLowerCase().indexOf(q) !== -1;
    });
    if (!matches.length) {
      showEmpty('Menu tidak ditemukan');
      return;
    }
    results.textContent = '';
    matches.forEach(function (item) { results.appendChild(buildResult(item)); });
    empty.hidden = true;
    results.hidden = false;
  };

  var openModal = function () {
    modal.hidden = false;
    root.classList.add('is-locked');
    input.value = '';
    render('');
    input.focus();
  };

  var closeModal = function (restoreFocus) {
    modal.hidden = true;
    root.classList.remove('is-locked');
    if (restoreFocus !== false) openBtn.focus();
  };
  closeSearch = closeModal;

  openBtn.addEventListener('click', openModal);
  input.addEventListener('input', function () { render(input.value); });
  Array.prototype.forEach.call(closers, function (el) {
    el.addEventListener('click', function () { closeModal(); });
  });

  modal.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal();
      return;
    }
    if (event.key !== 'Tab') return;
    // Jaga fokus tetap di dalam modal
    var focusable = modal.querySelectorAll('button:not([hidden]), input');
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
})();
