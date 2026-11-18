/* Halaman Menu: daftar menu & kategori dimuat dari API GET /api/menu.php, lalu filter kategori,
   pencarian (modal), stepper, dan pembuka pop-up Customization (logika pop-up ada di
   customization-popup.js). Keranjang tetap di localStorage (cart-store.js). Vanilla JS. */
(function () {
  'use strict';

  var root = document.documentElement;
  var menuList = document.getElementById('menu-list');
  if (!menuList) return;

  var apiUrl = menuList.getAttribute('data-api-url');
  var assetBase = menuList.getAttribute('data-asset-base') || '';
  var categoryIcons = (menuList.getAttribute('data-category-icons') || '').split(',');
  var stateBox = document.querySelector('[data-menu-state]');
  var stateText = document.querySelector('[data-menu-state-text]');
  var retryBtn = document.querySelector('[data-menu-retry]');
  var categoryPanel = document.querySelector('[data-category-panel]');
  var cardTemplate = document.getElementById('menu-item-template');
  var sectionTemplate = document.getElementById('menu-section-template');
  var categoryTemplate = document.getElementById('menu-category-template');
  var started = false;

  var formatRupiah = function (amount) {
    return 'Rp ' + String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  };

  /* Path gambar dari API relatif ke public/assets (mis. "images/menu/americano.png"); URL absolut dibiarkan. */
  var assetUrl = function (path) {
    path = String(path || '');
    return /^https?:\/\//i.test(path) ? path : assetBase + path.replace(/^\/+/, '');
  };

  /* Slug kategori dari API ("minuman") -> label tampilan ("Minuman"). */
  var categoryLabel = function (slug) {
    return String(slug).split('-').map(function (word) {
      return word.charAt(0).toUpperCase() + word.slice(1);
    }).join(' ');
  };

  /* Data API (MenuModel::map) -> bentuk yang dipakai halaman (sama seperti data pencarian sebelumnya). */
  var toViewItem = function (raw) {
    var price = Number(raw.price) || 0;
    return {
      id: String(raw.id),
      name: String(raw.name || ''),
      description: String(raw.description || ''),
      status: raw.status === 'soldout' ? 'soldout' : 'available',
      price: formatRupiah(price),
      priceValue: price,
      image: assetUrl(raw.image),
      dim: !!raw.dim,
      customizable: !!raw.customizable,
      category: String(raw.category || '')
    };
  };

  var setState = function (text, canRetry) {
    stateBox.hidden = text === null;
    if (text !== null) stateText.textContent = text;
    retryBtn.hidden = !canRetry;
  };

  /* Satu kartu menu dari <template id="menu-item-template"> (component/menu-item.php). */
  var buildCard = function (item) {
    var node = cardTemplate.content.firstElementChild.cloneNode(true);
    var soldOut = item.status === 'soldout';
    var chip = node.querySelector('[data-chip]');

    node.setAttribute('data-id', item.id);
    node.setAttribute('data-category', item.category);
    if (soldOut) node.classList.add('menu-item--soldout');
    if (item.dim) node.querySelector('[data-media]').classList.add('menu-item__media--dim');
    node.querySelector('[data-image]').src = item.image;
    node.querySelector('[data-name]').textContent = item.name;
    node.querySelector('[data-desc]').textContent = item.description;
    node.querySelector('[data-price]').textContent = item.price;
    chip.textContent = soldOut ? 'Habis' : 'Tersedia';
    chip.classList.add(soldOut ? 'chip--soldout' : 'chip--available');

    var add = node.querySelector('[data-add]');
    var stepper = node.querySelector('[data-stepper]');
    if (soldOut) { // menu habis: tanpa tombol + dan stepper
      add.remove();
      stepper.remove();
      return node;
    }
    add.setAttribute('data-menu-id', item.id);
    add.setAttribute('aria-label', 'Tambah ' + item.name);
    stepper.setAttribute('aria-label', 'Jumlah ' + item.name);
    var minus = stepper.querySelector('[data-stepper-minus]');
    var plus = stepper.querySelector('[data-stepper-plus]');
    minus.setAttribute('data-menu-id', item.id);
    minus.setAttribute('aria-label', 'Kurangi ' + item.name);
    plus.setAttribute('data-menu-id', item.id);
    plus.setAttribute('aria-label', 'Tambah ' + item.name);
    return node;
  };

  /* Daftar menu dikelompokkan per kategori (urutan dari API: ORDER BY kategori), tiap kategori diawali judul. */
  var renderMenu = function (items) {
    var prev = null;
    menuList.textContent = '';
    items.forEach(function (item) {
      if (item.category !== prev) {
        var heading = sectionTemplate.content.firstElementChild.cloneNode(true);
        heading.setAttribute('data-category-heading', item.category);
        heading.querySelector('h2').textContent = categoryLabel(item.category);
        menuList.appendChild(heading);
        prev = item.category;
      }
      menuList.appendChild(buildCard(item));
    });
  };

  /* Pilihan dropdown kategori dari kategori yang muncul di data API (urut sesuai daftar). */
  var renderCategories = function (items) {
    var seen = {};
    items.forEach(function (item) {
      if (!item.category || seen[item.category]) return;
      seen[item.category] = true;
      var option = categoryTemplate.content.firstElementChild.cloneNode(true);
      var icon = categoryIcons.indexOf(item.category) !== -1 ? item.category : 'all';
      option.setAttribute('data-value', item.category);
      option.querySelector('.dd-icon').classList.add('dd-icon--' + icon);
      option.querySelector('.dd-icon img').src = assetUrl('images/icons/icon-cat-' + icon + '.svg');
      option.querySelector('.dropdown__option-label').textContent = categoryLabel(item.category);
      categoryPanel.appendChild(option);
    });
  };

  /* Ambil menu dari API. Respons: { success, message, data: [ {id, name, price, ...} ] } */
  var load = function () {
    setState('Memuat menu…', false);
    fetch(apiUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (response) {
        return response.json().then(function (body) {
          if (!response.ok || !body || body.success !== true) {
            throw new Error((body && body.message) || 'HTTP ' + response.status);
          }
          return body.data;
        });
      })
      .then(function (data) {
        // data bisa berupa array, atau objek berkunci id (mis. respons ?id=) -> jadikan array
        var raw = Array.isArray(data) ? data : Object.keys(data || {}).map(function (key) { return data[key]; });
        var items = raw.filter(function (item) { return item && item.id && item.active !== false; }).map(toViewItem);
        if (!items.length) {
          setState('Belum ada menu yang tersedia.', true);
          return;
        }
        renderMenu(items);
        if (!started) {
          renderCategories(items);
          started = true;
          setState(null, false);
          start(items);
        } else {
          setState(null, false);
        }
      })
      .catch(function () {
        setState('Menu gagal dimuat. Periksa koneksi lalu coba lagi.', true);
      });
  };

  retryBtn.addEventListener('click', load);

  /* ---------- Fitur halaman (dijalankan sekali setelah data API dimuat) ---------- */
  function start(items) {
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

    /* Menu tanpa kustomisasi (makanan, dessert): langsung masuk keranjang, jumlah 1. */
    var addDirect = function (item) {
      document.dispatchEvent(new CustomEvent('cart:add', {
        detail: {
          id: item.id,
          name: item.name,
          image: item.image,
          description: item.description,
          basePrice: item.priceValue,
          customizable: false,
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

    /* ---------- Blokir "pesanan sebelumnya belum dibayar" ----------
       Dialog hanya dirender menu.php bila ada pesanan di sesi yang belum dibayar. Selama itu, SETIAP tombol +
       membuka dialog "Selesaikan Pembayaran" dan menu TIDAK ditambahkan (server juga menolak checkout). */
    var unpaid = document.querySelector('[data-unpaid]');
    var unpaidOpener = null; // tombol yang membuka dialog (fokus dikembalikan ke sini)

    var closeUnpaid = function (restoreFocus) {
      unpaid.hidden = true;
      root.classList.remove('is-locked');
      var opener = unpaidOpener;
      unpaidOpener = null;
      if (restoreFocus && opener && document.contains(opener) && opener.offsetParent !== null) {
        opener.focus();
      }
    };

    var openUnpaid = function (btn) {
      var opener = btn;
      if (btn.closest('[data-search-modal]') && closeSearch) {
        closeSearch(false); // tutup modal pencarian dulu; fokus nanti kembali ke tombol "Cari menu"
        opener = document.querySelector('[data-search-open]');
      }
      unpaidOpener = opener;
      unpaid.hidden = false;
      root.classList.add('is-locked');
      unpaid.querySelector('.confirm__actions .btn').focus(); // tombol utama (Selesaikan Pembayaran)
    };

    if (unpaid) {
      Array.prototype.forEach.call(unpaid.querySelectorAll('[data-unpaid-close]'), function (el) {
        el.addEventListener('click', function () { closeUnpaid(true); });
      });
      // "Lihat Pesanan Saya" (lebih dari 1 pesanan belum dibayar): tutup dialog, gulir ke daftar kartu pesanan
      var unpaidList = unpaid.querySelector('[data-unpaid-list]');
      var orderList = document.getElementById('pesanan-saya');
      if (unpaidList && orderList) {
        unpaidList.addEventListener('click', function (event) {
          event.preventDefault();
          closeUnpaid(false);
          orderList.scrollIntoView({ behavior: 'smooth', block: 'start' });
          var firstCard = orderList.querySelector('a');
          if (firstCard) firstCard.focus({ preventScroll: true });
        });
      }
      unpaid.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          closeUnpaid(true);
          return;
        }
        if (event.key !== 'Tab') return;
        // Jaga fokus di dalam dialog
        var focusable = unpaid.querySelectorAll('.confirm__actions .btn');
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

    /* Tombol + (daftar utama maupun hasil pencarian). Minuman membuka pop-up Custom;
       makanan/dessert langsung ke keranjang. Menu "Habis" tidak punya tombol +. */
    document.addEventListener('click', function (event) {
      var btn = event.target.closest('.btn-add[data-menu-id], [data-stepper-plus][data-menu-id]');
      if (!btn || btn.getAttribute('aria-disabled') === 'true') return;
      var item = itemsById[btn.getAttribute('data-menu-id')];
      if (!item || item.status === 'soldout') return;

      if (unpaid) {
        openUnpaid(btn); // pesanan sebelumnya belum dibayar: tidak boleh menambah menu
        return;
      }
      doAdd(btn, item);
    });

    /* Aksi tombol + (dari hasil pencarian: modal pencarian ditutup lebih dulu). */
    function doAdd(btn, item) {
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
      if (!window.QuickBrewPopup) return;
      window.QuickBrewPopup.open(item, {
        opener: opener,
        onAdded: function (added) { notify(added.name); },
        onClose: function (closed) { focusItemControl(closed.id); } // tombol + bisa sudah berganti menjadi stepper
      });
    }

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
  }

  load();
})();
