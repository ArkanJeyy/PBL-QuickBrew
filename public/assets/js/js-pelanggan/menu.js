/* Halaman Menu: filter kategori, pencarian (modal), dan pembuka pop-up Customization
   (logika pop-up ada di customization-popup.js). Vanilla JS. */
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

  /* Tombol + (daftar utama maupun hasil pencarian) selalu membuka pop-up. Menu "Habis" tidak punya tombol +. */
  document.addEventListener('click', function (event) {
    var btn = event.target.closest('.btn-add[data-menu-id], [data-stepper-plus][data-menu-id]');
    if (!btn || btn.getAttribute('aria-disabled') === 'true') return;
    var item = itemsById[btn.getAttribute('data-menu-id')];
    if (!item || item.status === 'soldout') return;

    var opener = btn;
    if (btn.closest('[data-search-modal]') && closeSearch) {
      closeSearch(false);
      opener = document.querySelector('[data-search-open]');
    }
    if (!window.QuickBrewPopup) return;
    window.QuickBrewPopup.open(item, {
      opener: opener,
      onAdded: function (added) { notify(added.name); },
      onClose: function (closed) { focusItemControl(closed.id); } // tombol + bisa sudah berganti menjadi stepper
    });
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

    /* Semua menu selalu tampil. Memilih kategori = scroll ke kartu pertama kategori itu;
       label dropdown ikut berganti sesuai kategori yang sedang terlihat (scroll-spy). */
    var tools = dropdown.closest('.menu-tools');
    var activeValue = 'all';

    // Judul tiap kategori (dirender menu.php di atas kartu pertama kategori), urut sesuai tampilan.
    // Dipakai sebagai titik tujuan lompat kategori dan acuan scroll-spy.
    var firstCards = Array.prototype.map.call(list.querySelectorAll('[data-category-heading]'), function (heading) {
      return { category: heading.getAttribute('data-category-heading'), card: heading };
    });

    var setActive = function (value) {
      var match = options.filter(function (o) { return o.getAttribute('data-value') === value; })[0];
      if (!match) return;
      activeValue = value;
      options.forEach(function (o) {
        o.setAttribute('aria-selected', o === match ? 'true' : 'false');
      });
      label.textContent = match.querySelector('.dropdown__option-label').textContent;
    };

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var autoScrolling = false;
    var autoScrollTimer = null;

    var scrollToCategory = function (value) {
      var behavior = reduceMotion ? 'auto' : 'smooth';
      var target = value === 'all' ? null : firstCards.filter(function (f) { return f.category === value; })[0];
      if (value !== 'all' && !target) return;

      // Jeda scroll-spy selama scroll otomatis agar label tidak berkedip melewati kategori lain
      autoScrolling = true;
      window.clearTimeout(autoScrollTimer);
      autoScrollTimer = window.setTimeout(endAutoScroll, 700); // fallback bila 'scrollend' tidak didukung

      if (target) {
        target.card.scrollIntoView({ behavior: behavior, block: 'start' }); // jarak dari baris sticky: scroll-margin-top di menu.css
      } else {
        window.scrollTo({ top: 0, behavior: behavior });
      }
    };

    /* Kategori aktif = kategori terakhir yang judulnya sudah sampai di bawah baris sticky. */
    var currentCategory = function () {
      // Toleransi 20px: sedikit lebih besar dari jarak scroll-margin (lihat --menu-tools-offset di menu.css)
      var line = (tools ? tools.getBoundingClientRect().bottom : 0) + 20;
      var atBottom = window.innerHeight + window.pageYOffset >= document.documentElement.scrollHeight - 2;
      var value = 'all';
      firstCards.forEach(function (f) {
        var top = f.card.getBoundingClientRect().top;
        // Di dasar halaman, kategori terakhir mungkin tidak bisa naik sampai garis: cukup sudah terlihat
        if (top <= line || (atBottom && top < window.innerHeight)) value = f.category;
      });
      return value;
    };

    var spyFrame = null;
    var spy = function () {
      spyFrame = null;
      if (autoScrolling || !panel.hidden) return;
      var value = currentCategory();
      if (value !== activeValue) setActive(value);
    };

    var endAutoScroll = function () {
      window.clearTimeout(autoScrollTimer);
      autoScrolling = false;
      spy();
    };

    window.addEventListener('scroll', function () {
      if (spyFrame === null) spyFrame = window.requestAnimationFrame(spy);
    }, { passive: true });
    window.addEventListener('scrollend', function () {
      if (autoScrolling) endAutoScroll();
    });
    spy(); // posisi awal (mis. kembali ke halaman dengan scroll tersimpan)

    trigger.addEventListener('click', function () {
      setDropdown(panel.hidden);
    });

    options.forEach(function (option) {
      option.addEventListener('click', function () {
        var value = option.getAttribute('data-value');
        setActive(value);
        setDropdown(false);
        scrollToCategory(value);
        trigger.focus({ preventScroll: true });
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
    if (soldOut) {
      add.remove(); // menu habis: tanpa tombol +, foto hitam-putih
      node.classList.add('search-result--soldout');
    } else {
      add.setAttribute('data-menu-id', item.id);
      add.setAttribute('aria-label', 'Tambah ' + item.name);
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
