// dashboard.js — QuickBrew Kasir
// Terintegrasi dengan public/api/Kasir/pemesanan.php (list, detail, PATCH status)
// serta fallback SSR/POST KasirController (pencarian ?q=, bayar_tunai, cek_qris)

(function () {
  var antreanSection = document.getElementById('antreanSection');
  var apiUrl         = antreanSection ? (antreanSection.getAttribute('data-api-url') || '') : '';
  var tbody          = document.getElementById('antreanTbody');
  var paginationInfo = document.getElementById('paginationInfo');
  var paginationCtrl = document.getElementById('paginationControls');
  var toastContainer = document.getElementById('apiToastContainer');

  var state = {
    status: antreanSection ? (antreanSection.getAttribute('data-status') || 'semua') : 'semua',
    q:      antreanSection ? (antreanSection.getAttribute('data-q') || '') : '',
    per:    antreanSection ? (parseInt(antreanSection.getAttribute('data-per') || '10', 10) || 10) : 10,
    hal:    antreanSection ? (parseInt(antreanSection.getAttribute('data-hal') || '1', 10) || 1) : 1
  };

  var tabCounts = {};
  try {
    tabCounts = JSON.parse((antreanSection && antreanSection.getAttribute('data-counts')) || '{}') || {};
  } catch (e) {
    tabCounts = {};
  }

  var filterToApiStatus = {
    semua: '',
    menunggu: 'menunggu_pembayaran',
    diproses: 'diproses',
    siap: 'siap',
    selesai: 'selesai',
    batal: 'batal'
  };

  function formatRupiah(angka) {
    var num = Number(angka) || 0;
    return 'Rp ' + num.toLocaleString('id-ID');
  }

  function showToast(tipe, pesan) {
    if (!toastContainer || !pesan) return;
    while (toastContainer.firstChild) {
      toastContainer.removeChild(toastContainer.firstChild);
    }
    var box = document.createElement('div');
    box.className = 'kasir-flash kasir-flash--' + (tipe || 'info');
    box.setAttribute('role', 'status');
    box.textContent = pesan;
    toastContainer.appendChild(box);
  }

  // Tampilkan pesan sukses dari aksi PATCH sebelumnya setelah reload sinkronisasi statistik & grafik
  try {
    var pendingToast = sessionStorage.getItem('qb_kasir_toast');
    if (pendingToast) {
      sessionStorage.removeItem('qb_kasir_toast');
      var parsedToast = JSON.parse(pendingToast);
      if (parsedToast && parsedToast.pesan) {
        showToast(parsedToast.tipe || 'sukses', parsedToast.pesan);
      }
    }
  } catch (e) {}

  function buildDashboardUrl(status, q, hal, per) {
    var url = new URL(window.location.href);
    url.search = '';
    url.searchParams.set('page', 'kasir');
    if (status && status !== 'semua') url.searchParams.set('status', status);
    if (q) url.searchParams.set('q', q);
    if (per && per !== 10) url.searchParams.set('per', String(per));
    if (hal && hal > 1) url.searchParams.set('hal', String(hal));
    url.hash = 'antreanSection';
    return url.pathname + url.search + url.hash;
  }

  function syncHiddenFormFields() {
    var confirmForm = document.getElementById('confirmModalForm');
    if (confirmForm) {
      var rs = confirmForm.querySelector('input[name="return_status"]');
      var rq = confirmForm.querySelector('input[name="return_q"]');
      var rh = confirmForm.querySelector('input[name="return_hal"]');
      var rp = confirmForm.querySelector('input[name="return_per"]');
      if (rs) rs.value = state.status;
      if (rq) rq.value = state.q;
      if (rh) rh.value = String(state.hal);
      if (rp) rp.value = String(state.per);
    }

    var searchForm = document.getElementById('searchPesananForm');
    if (searchForm) {
      var statusHidden = searchForm.querySelector('input[name="status"]');
      if (state.status !== 'semua') {
        if (!statusHidden) {
          statusHidden = document.createElement('input');
          statusHidden.type = 'hidden';
          statusHidden.name = 'status';
          searchForm.appendChild(statusHidden);
        }
        statusHidden.value = state.status;
      } else if (statusHidden) {
        statusHidden.remove();
      }

      var perHidden = searchForm.querySelector('input[name="per"]');
      if (state.per !== 10) {
        if (!perHidden) {
          perHidden = document.createElement('input');
          perHidden.type = 'hidden';
          perHidden.name = 'per';
          searchForm.appendChild(perHidden);
        }
        perHidden.value = String(state.per);
      } else if (perHidden) {
        perHidden.remove();
      }
    }

    var perPageForm = document.getElementById('perPageForm');
    if (perPageForm) {
      var ppStatus = perPageForm.querySelector('input[name="status"]');
      if (state.status !== 'semua') {
        if (!ppStatus) {
          ppStatus = document.createElement('input');
          ppStatus.type = 'hidden';
          ppStatus.name = 'status';
          perPageForm.appendChild(ppStatus);
        }
        ppStatus.value = state.status;
      } else if (ppStatus) {
        ppStatus.remove();
      }
    }
  }

  /* ===================== MODAL KONFIRMASI AKSI (PATCH API / POST PRG) ===================== */
  var overlay      = document.getElementById('confirmModal');
  var confirmForm  = document.getElementById('confirmModalForm');
  var btnCancel    = document.getElementById('btnCancelModal');
  var btnConfirm   = document.getElementById('btnConfirmModal');
  var inputOrderId = document.getElementById('confirmOrderId');
  var inputAction  = document.getElementById('confirmOrderAction');
  var modalTitle   = document.getElementById('confirmModalTitle');
  var modalDesc    = document.getElementById('confirmModalDesc');
  var defaultConfirmLabel = btnConfirm ? btnConfirm.textContent : 'Lanjutkan';

  var actionCopy = {
    bayar_tunai: {
      title: 'Konfirmasi pembayaran tunai?',
      desc: function (nomor) { return 'Pastikan uang tunai untuk pesanan ' + nomor + ' sudah diterima di kasir.'; },
      btn: 'Terima Pembayaran'
    },
    cek_qris: {
      title: 'Sinkronkan status QRIS?',
      desc: function (nomor) { return 'Sistem akan memeriksa status pembayaran Midtrans terbaru untuk pesanan ' + nomor + '.'; },
      btn: 'Cek Status QRIS'
    },
    siap: {
      title: 'Tandai siap diantar?',
      desc: function (nomor) { return 'Pastikan pesanan ' + nomor + ' sudah lengkap dan siap diantar ke meja pelanggan.'; },
      btn: 'Siap Diantar'
    },
    selesai: {
      title: 'Selesaikan pesanan?',
      desc: function (nomor) { return 'Tandai pesanan ' + nomor + ' sudah diterima pelanggan dan selesai.'; },
      btn: 'Tandai Selesai'
    },
    batal: {
      title: 'Batalkan pesanan?',
      desc: function (nomor) { return 'Pesanan ' + nomor + ' yang belum dibayar akan dibatalkan. Tindakan ini tidak dapat dibatalkan.'; },
      btn: 'Ya, Batalkan'
    }
  };

  function openConfirmModal(orderId, nomor, aksi) {
    if (!overlay || !inputOrderId || !inputAction) return;
    var cfg = actionCopy[aksi] || {
      title: 'Konfirmasi aksi?',
      desc: function (n) { return 'Lanjutkan pemrosesan pesanan ' + n + '?'; },
      btn: 'Lanjutkan'
    };
    inputOrderId.value = String(orderId || '');
    inputAction.value  = String(aksi || '');
    if (modalTitle) modalTitle.textContent = cfg.title;
    if (modalDesc)  modalDesc.textContent  = cfg.desc(nomor || ('#' + orderId));
    if (btnConfirm) {
      btnConfirm.disabled = false;
      btnConfirm.textContent = cfg.btn;
      defaultConfirmLabel = cfg.btn;
    }
    overlay.classList.add('active');
  }

  function closeConfirmModal() {
    if (overlay) overlay.classList.remove('active');
    if (btnConfirm) {
      btnConfirm.disabled = false;
      btnConfirm.textContent = defaultConfirmLabel;
    }
  }

  if (btnCancel) btnCancel.addEventListener('click', closeConfirmModal);
  if (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeConfirmModal();
    });
  }

  if (confirmForm) {
    confirmForm.addEventListener('submit', function (e) {
      var aksi    = inputAction ? inputAction.value : '';
      var orderId = inputOrderId ? inputOrderId.value : '';
      var apiStatusActions = ['siap', 'selesai', 'batal'];

      if (!apiUrl || apiStatusActions.indexOf(aksi) === -1) {
        return; // bayar_tunai & cek_qris diteruskan lewat POST form KasirController
      }

      e.preventDefault();
      if (btnConfirm) {
        btnConfirm.disabled = true;
        btnConfirm.textContent = 'Memproses...';
      }

      fetch(apiUrl + '?action=update&id=' + encodeURIComponent(orderId), {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ status: aksi })
      })
        .then(function (res) {
          return res.json().catch(function () { return null; }).then(function (json) {
            return { ok: res.ok, json: json };
          });
        })
        .then(function (result) {
          closeConfirmModal();
          if (result.ok && result.json && result.json.success) {
            try {
              sessionStorage.setItem('qb_kasir_toast', JSON.stringify({
                tipe: 'sukses',
                pesan: result.json.message || 'Status pesanan berhasil diperbarui.'
              }));
            } catch (err) {}
            window.location.href = buildDashboardUrl(state.status, state.q, state.hal, state.per);
            return;
          }
          showToast('galat', (result.json && result.json.message) || 'Gagal memperbarui status pesanan.');
        })
        .catch(function () {
          closeConfirmModal();
          showToast('galat', 'Koneksi ke server API gagal. Silakan coba lagi.');
        });
    });
  }

  /* ===================== MODAL DETAIL PESANAN (GET ?action=show&id=) ===================== */
  var detailOverlay      = document.getElementById('detailOrderModal');
  var btnCloseDetail     = document.getElementById('btnCloseDetailModal');
  var btnCloseDetailFoot = document.getElementById('btnCloseDetailFooter');
  var btnDetailPrint     = document.getElementById('btnDetailPrint');
  var btnDetailCancelOrd = document.getElementById('btnDetailCancelOrder');
  var currentDetailOrder = null;

  function setText(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  function renderOrderItems(items) {
    var container = document.getElementById('detailItemsList');
    if (!container) return;
    while (container.firstChild) {
      container.removeChild(container.firstChild);
    }
    if (!Array.isArray(items) || items.length === 0) {
      var empty = document.createElement('div');
      empty.className = 'detail-item-sub';
      empty.textContent = 'Tidak ada rincian item.';
      container.appendChild(empty);
      return;
    }

    items.forEach(function (item) {
      var row = document.createElement('div');
      row.className = 'detail-item-row';

      var left = document.createElement('div');
      var title = document.createElement('div');
      title.style.fontWeight = '600';
      title.textContent = String(item.jumlah || 0) + 'x ' + String(item.nama_menu || '-');
      left.appendChild(title);

      if (item.catatan) {
        var note = document.createElement('div');
        note.className = 'detail-item-sub';
        note.textContent = String(item.catatan);
        left.appendChild(note);
      }

      var right = document.createElement('div');
      right.style.fontWeight = '600';
      right.style.whiteSpace = 'nowrap';
      right.textContent = formatRupiah(item.subtotal || 0);

      row.appendChild(left);
      row.appendChild(right);
      container.appendChild(row);
    });
  }

  function closeDetailModal() {
    if (detailOverlay) detailOverlay.classList.remove('active');
    currentDetailOrder = null;
  }

  function fetchOrderDetail(orderId, triggerPrint) {
    if (!detailOverlay || !apiUrl) return;
    currentDetailOrder = null;
    setText('detailOrderTitle', 'Memuat rincian...');
    setText('detailOrderSubtitle', '');
    setText('detailPelanggan', '-');
    setText('detailMeja', '-');
    setText('detailPembayaran', '-');
    setText('detailStatus', '-');
    setText('detailKasir', '-');
    setText('detailSubtotal', 'Rp 0');
    setText('detailPajak', 'Rp 0');
    setText('detailTotalHarga', 'Rp 0');
    var noteBox = document.getElementById('detailNoteBox');
    if (noteBox) {
      noteBox.textContent = '';
      noteBox.style.display = 'none';
    }
    if (btnDetailCancelOrd) btnDetailCancelOrd.style.display = 'none';
    var itemsEl = document.getElementById('detailItemsList');
    if (itemsEl) {
      while (itemsEl.firstChild) itemsEl.removeChild(itemsEl.firstChild);
      var loadingItem = document.createElement('div');
      loadingItem.className = 'detail-item-sub';
      loadingItem.textContent = 'Mengambil data dari API...';
      itemsEl.appendChild(loadingItem);
    }

    detailOverlay.classList.add('active');

    fetch(apiUrl + '?action=show&id=' + encodeURIComponent(orderId), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) {
        return res.json().catch(function () { return null; }).then(function (json) {
          return { ok: res.ok, json: json };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.json || !result.json.success || !result.json.data) {
          closeDetailModal();
          showToast('galat', (result.json && result.json.message) || 'Gagal memuat detail pesanan.');
          return;
        }

        var order = result.json.data;
        currentDetailOrder = { id: order.id, nomor: order.number };

        setText('detailOrderTitle', 'Pesanan ' + (order.number || ''));
        setText('detailOrderSubtitle', order.waktu_pemesanan ? 'Dibuat pada ' + order.waktu_pemesanan : '');
        setText('detailPelanggan', order.nama_pelanggan || '-');
        setText('detailMeja', 'Meja ' + (order.no_meja || '-'));
        setText(
          'detailPembayaran',
          String(order.metode_pembayaran || 'tunai').toUpperCase() + ' (' + String(order.label_pembayaran || order.status_pembayaran || 'pending') + ')'
        );
        setText('detailStatus', order.label_status || order.status_pemesanan || '-');
        setText('detailKasir', order.nama_kasir || '-');
        setText('detailSubtotal', formatRupiah(order.subtotal || 0));
        setText('detailPajak', formatRupiah(order.tax || 0));
        setText('detailTotalHarga', formatRupiah(order.total_harga || 0));

        if (noteBox) {
          if (order.catatan) {
            noteBox.textContent = 'Catatan pelanggan: ' + order.catatan;
            noteBox.style.display = 'block';
          } else {
            noteBox.textContent = '';
            noteBox.style.display = 'none';
          }
        }

        if (btnDetailCancelOrd) {
          btnDetailCancelOrd.style.display = order.status_pemesanan === 'menunggu_pembayaran' ? 'inline-block' : 'none';
        }

        renderOrderItems(order.items || []);

        if (triggerPrint) {
          setTimeout(function () { window.print(); }, 150);
        }
      })
      .catch(function () {
        closeDetailModal();
        showToast('galat', 'Gagal menghubungi API detail pesanan.');
      });
  }

  if (btnCloseDetail)     btnCloseDetail.addEventListener('click', closeDetailModal);
  if (btnCloseDetailFoot) btnCloseDetailFoot.addEventListener('click', closeDetailModal);
  if (btnDetailPrint)     btnDetailPrint.addEventListener('click', function () { window.print(); });
  if (btnDetailCancelOrd) {
    btnDetailCancelOrd.addEventListener('click', function () {
      if (!currentDetailOrder) return;
      var target = currentDetailOrder;
      closeDetailModal();
      openConfirmModal(target.id, target.nomor, 'batal');
    });
  }
  if (detailOverlay) {
    detailOverlay.addEventListener('click', function (e) {
      if (e.target === detailOverlay) closeDetailModal();
    });
  }

  // Event delegation pada tbody untuk tombol Detail, Cetak Struk, dan modal aksi (bekerja untuk SSR & hasil API)
  if (tbody) {
    tbody.addEventListener('click', function (e) {
      var detailBtn = e.target.closest('.btn-detail-order');
      if (detailBtn) {
        fetchOrderDetail(detailBtn.getAttribute('data-order-id'), false);
        return;
      }

      var printBtn = e.target.closest('.btn-print-order');
      if (printBtn) {
        fetchOrderDetail(printBtn.getAttribute('data-order-id'), true);
        return;
      }

      var modalBtn = e.target.closest('.btn-open-modal');
      if (modalBtn) {
        var orderId = modalBtn.getAttribute('data-order-id');
        var nomor   = modalBtn.getAttribute('data-order-nomor') || modalBtn.getAttribute('data-order') || '';
        var aksi    = modalBtn.getAttribute('data-aksi') || 'siap';
        openConfirmModal(orderId, nomor, aksi);
      }
    });
  }

  /* ===================== PAGINATION & FILTER VIA API INDEX ===================== */
  function mapOrderPresentation(row) {
    var status = String(row.status_pemesanan || 'menunggu_pembayaran');
    var metode = String(row.metode_pembayaran || 'tunai');
    var statusBayar = String(row.status_pembayaran || 'pending');
    var isPaid = statusBayar === 'paid' || ['dibayar', 'diproses', 'siap', 'selesai'].indexOf(status) !== -1;

    var dataFilter = 'batal';
    var badgeStatusClass = 'badge-batal';
    var badgeStatusText = 'Batal';
    if (status === 'menunggu_pembayaran') {
      dataFilter = 'menunggu';
      badgeStatusClass = 'badge-menunggu';
      badgeStatusText = 'Menunggu';
    } else if (status === 'dibayar' || status === 'diproses') {
      dataFilter = 'diproses';
      badgeStatusClass = 'badge-diproses';
      badgeStatusText = 'Diproses';
    } else if (status === 'siap') {
      dataFilter = 'siap';
      badgeStatusClass = 'badge-siap';
      badgeStatusText = 'Siap';
    } else if (status === 'selesai') {
      dataFilter = 'selesai';
      badgeStatusClass = 'badge-selesai';
      badgeStatusText = 'Selesai';
    }

    var badgeBayarClass = 'badge-belum-bayar';
    var badgeBayarText = 'Belum Bayar';
    if (isPaid) {
      badgeBayarClass = 'badge-lunas';
      badgeBayarText = 'Lunas';
    } else if (statusBayar === 'expire') {
      badgeBayarClass = 'badge-batal';
      badgeBayarText = 'Kedaluwarsa';
    } else if (['cancel', 'deny', 'failure'].indexOf(statusBayar) !== -1) {
      badgeBayarClass = 'badge-batal';
      badgeBayarText = 'Batal';
    }

    return {
      status: status,
      metode: metode,
      dataFilter: dataFilter,
      badgeStatusClass: badgeStatusClass,
      badgeStatusText: badgeStatusText,
      badgeBayarClass: badgeBayarClass,
      badgeBayarText: badgeBayarText
    };
  }

  function renderOrderRow(row) {
    var pres = mapOrderPresentation(row);
    var tr = document.createElement('tr');
    tr.setAttribute('data-status', pres.dataFilter);

    // 1. ID & jumlah item
    var tdId = document.createElement('td');
    tdId.className = 'td-id';
    var codeDiv = document.createElement('div');
    codeDiv.className = 'order-code';
    codeDiv.textContent = String(row.nomor || '');
    var countDiv = document.createElement('div');
    countDiv.className = 'order-item-count';
    countDiv.textContent = String(row.jumlah_item || 0) + ' item';
    tdId.appendChild(codeDiv);
    tdId.appendChild(countDiv);
    tr.appendChild(tdId);

    // 2. Meja
    var tdMeja = document.createElement('td');
    tdMeja.className = 'td-meja';
    var mejaSpan = document.createElement('span');
    mejaSpan.className = 'meja-num';
    mejaSpan.textContent = String(row.no_meja || '-');
    tdMeja.appendChild(mejaSpan);
    tr.appendChild(tdMeja);

    // 3. Item & nama pelanggan
    var tdItem = document.createElement('td');
    tdItem.className = 'td-item';
    var itemDiv = document.createElement('div');
    itemDiv.className = 'item-list';
    itemDiv.textContent = row.ringkasan_item ? String(row.ringkasan_item) : '-';
    tdItem.appendChild(itemDiv);
    if (row.nama_pelanggan) {
      var pelDiv = document.createElement('div');
      pelDiv.className = 'order-item-count';
      pelDiv.textContent = String(row.nama_pelanggan);
      tdItem.appendChild(pelDiv);
    }
    tr.appendChild(tdItem);

    // 4. Total
    var tdTotal = document.createElement('td');
    tdTotal.className = 'td-total';
    var totalSpan = document.createElement('span');
    totalSpan.className = 'order-total';
    totalSpan.textContent = formatRupiah(row.total_harga || 0);
    tdTotal.appendChild(totalSpan);
    tr.appendChild(tdTotal);

    // 5. Pembayaran
    var tdBayar = document.createElement('td');
    tdBayar.className = 'td-bayar';
    var bayarSpan = document.createElement('span');
    bayarSpan.className = 'badge-pill ' + pres.badgeBayarClass;
    bayarSpan.textContent = pres.badgeBayarText;
    var metodeDiv = document.createElement('div');
    metodeDiv.className = 'order-item-count';
    metodeDiv.textContent = pres.metode.toUpperCase();
    tdBayar.appendChild(bayarSpan);
    tdBayar.appendChild(metodeDiv);
    tr.appendChild(tdBayar);

    // 6. Status
    var tdStatus = document.createElement('td');
    tdStatus.className = 'td-status';
    var statusSpan = document.createElement('span');
    statusSpan.className = 'badge-pill ' + pres.badgeStatusClass;
    statusSpan.textContent = pres.badgeStatusText;
    tdStatus.appendChild(statusSpan);
    tr.appendChild(tdStatus);

    // 7. Aksi
    var tdAksi = document.createElement('td');
    tdAksi.className = 'td-aksi';
    var actionsDiv = document.createElement('div');
    actionsDiv.className = 'action-buttons';

    var btnDetail = document.createElement('button');
    btnDetail.type = 'button';
    btnDetail.className = 'btn-action-solid btn-detail-order';
    btnDetail.setAttribute('data-order-id', String(row.id));
    btnDetail.textContent = 'Detail';
    actionsDiv.appendChild(btnDetail);

    if (pres.status === 'menunggu_pembayaran' && pres.metode === 'tunai') {
      actionsDiv.appendChild(makeModalButton(row.id, row.nomor, 'bayar_tunai', 'Bayar'));
    } else if (pres.status === 'menunggu_pembayaran' && pres.metode === 'qris') {
      actionsDiv.appendChild(makeModalButton(row.id, row.nomor, 'cek_qris', 'Cek QRIS'));
    } else if (pres.status === 'diproses') {
      actionsDiv.appendChild(makeModalButton(row.id, row.nomor, 'siap', 'Siap Antar'));
    } else if (pres.status === 'siap') {
      actionsDiv.appendChild(makeModalButton(row.id, row.nomor, 'selesai', 'Selesai'));
    } else if (pres.status === 'selesai') {
      var btnPrint = document.createElement('button');
      btnPrint.type = 'button';
      btnPrint.className = 'btn-action-outline btn-print-order';
      btnPrint.setAttribute('data-order-id', String(row.id));
      btnPrint.textContent = 'Cetak Struk';
      actionsDiv.appendChild(btnPrint);
    }

    tdAksi.appendChild(actionsDiv);
    tr.appendChild(tdAksi);
    return tr;
  }

  function makeModalButton(id, nomor, aksi, label) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-action-outline btn-open-modal';
    btn.setAttribute('data-order-id', String(id));
    btn.setAttribute('data-order-nomor', String(nomor || ''));
    btn.setAttribute('data-aksi', aksi);
    btn.textContent = label;
    return btn;
  }

  function renderPagination(statusKey, halAktif, perHalaman, totalData, jumlahDitampilkan) {
    var totalHalaman = Math.max(1, Math.ceil(totalData / perHalaman));
    var dari   = totalData > 0 && jumlahDitampilkan > 0 ? ((halAktif - 1) * perHalaman) + 1 : 0;
    var sampai = totalData > 0 && jumlahDitampilkan > 0 ? (dari + jumlahDitampilkan - 1) : 0;

    if (paginationInfo) {
      while (paginationInfo.firstChild) paginationInfo.removeChild(paginationInfo.firstChild);
      if (totalData > 0 && jumlahDitampilkan > 0) {
        paginationInfo.appendChild(document.createTextNode('Menampilkan '));
        var sRange = document.createElement('strong');
        sRange.textContent = dari + '\u2013' + sampai;
        paginationInfo.appendChild(sRange);
        paginationInfo.appendChild(document.createTextNode(' dari '));
        var sTotal = document.createElement('strong');
        sTotal.textContent = String(totalData);
        paginationInfo.appendChild(sTotal);
        paginationInfo.appendChild(document.createTextNode(' transaksi'));
      } else {
        paginationInfo.appendChild(document.createTextNode('Menampilkan '));
        var sZero1 = document.createElement('strong');
        sZero1.textContent = '0';
        paginationInfo.appendChild(sZero1);
        paginationInfo.appendChild(document.createTextNode(' dari '));
        var sZero2 = document.createElement('strong');
        sZero2.textContent = '0';
        paginationInfo.appendChild(sZero2);
        paginationInfo.appendChild(document.createTextNode(' transaksi'));
      }
    }

    if (!paginationCtrl) return;
    while (paginationCtrl.firstChild) paginationCtrl.removeChild(paginationCtrl.firstChild);

    if (totalHalaman <= 1) {
      paginationCtrl.style.display = 'none';
      return;
    }
    paginationCtrl.style.display = 'flex';

    // Sebelumnya
    if (halAktif > 1) {
      paginationCtrl.appendChild(makePageLink(statusKey, halAktif - 1, perHalaman, '\u2039 Sebelumnya'));
    } else {
      paginationCtrl.appendChild(makeDisabledPage('\u2039 Sebelumnya'));
    }

    var slots = [];
    if (totalHalaman <= 7) {
      for (var p = 1; p <= totalHalaman; p++) slots.push(p);
    } else {
      var candMap = {};
      [1, 2, halAktif - 1, halAktif, halAktif + 1, totalHalaman - 1, totalHalaman].forEach(function (n) {
        if (n >= 1 && n <= totalHalaman) candMap[n] = true;
      });
      var cands = Object.keys(candMap).map(Number).sort(function (a, b) { return a - b; });
      var prev = 0;
      cands.forEach(function (n) {
        if (prev > 0 && (n - prev) > 1) slots.push('...');
        slots.push(n);
        prev = n;
      });
    }

    slots.forEach(function (slot) {
      if (slot === '...') {
        var elip = document.createElement('span');
        elip.className = 'page-ellipsis';
        elip.setAttribute('aria-hidden', 'true');
        elip.textContent = '\u2026';
        paginationCtrl.appendChild(elip);
      } else if (slot === halAktif) {
        var cur = document.createElement('span');
        cur.className = 'page-btn active';
        cur.setAttribute('aria-current', 'page');
        cur.textContent = String(slot);
        paginationCtrl.appendChild(cur);
      } else {
        paginationCtrl.appendChild(makePageLink(statusKey, slot, perHalaman, String(slot)));
      }
    });

    // Berikutnya
    if (halAktif < totalHalaman) {
      paginationCtrl.appendChild(makePageLink(statusKey, halAktif + 1, perHalaman, 'Berikutnya \u203a'));
    } else {
      paginationCtrl.appendChild(makeDisabledPage('Berikutnya \u203a'));
    }
  }

  function makePageLink(statusKey, targetHal, perHalaman, label) {
    var a = document.createElement('a');
    a.href = buildDashboardUrl(statusKey, '', targetHal, perHalaman);
    a.className = 'page-btn';
    a.setAttribute('data-page-target', String(targetHal));
    a.textContent = label;
    return a;
  }

  function makeDisabledPage(label) {
    var span = document.createElement('span');
    span.className = 'page-btn disabled';
    span.setAttribute('aria-disabled', 'true');
    span.textContent = label;
    return span;
  }

  function loadQueueViaApi(statusKey, hal, per) {
    if (!apiUrl || !tbody) return;
    var totalData    = Number(tabCounts[statusKey] || 0);
    var totalHalaman = Math.max(1, Math.ceil(totalData / per));
    var halAman      = Math.min(Math.max(1, hal), totalHalaman);
    var offset       = (halAman - 1) * per;
    var apiStatus    = filterToApiStatus[statusKey] || '';

    var qs = new URLSearchParams();
    if (apiStatus !== '') qs.set('status', apiStatus);
    qs.set('limit', String(per));
    qs.set('offset', String(offset));

    tbody.classList.add('table-loading');

    fetch(apiUrl + '?' + qs.toString(), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) {
        return res.json().catch(function () { return null; }).then(function (json) {
          return { ok: res.ok, json: json };
        });
      })
      .then(function (result) {
        tbody.classList.remove('table-loading');
        if (!result.ok || !result.json || !result.json.success || !Array.isArray(result.json.data)) {
          showToast('galat', (result.json && result.json.message) || 'Gagal memuat antrean pesanan dari API.');
          return;
        }

        var rows = result.json.data;
        while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

        if (rows.length === 0) {
          var emptyTr = document.createElement('tr');
          emptyTr.id = 'emptyServerRow';
          var emptyTd = document.createElement('td');
          emptyTd.colSpan = 7;
          emptyTd.className = 'empty-table-cell';
          emptyTd.textContent = statusKey !== 'semua'
            ? 'Tidak ada pesanan yang cocok dengan filter ini.'
            : 'Belum ada pesanan yang masuk di basis data.';
          emptyTr.appendChild(emptyTd);
          tbody.appendChild(emptyTr);
        } else {
          rows.forEach(function (row) {
            tbody.appendChild(renderOrderRow(row));
          });
        }

        state.status = statusKey;
        state.hal    = halAman;
        state.per    = per;

        document.querySelectorAll('.filter-tab[data-filter-key]').forEach(function (tab) {
          tab.classList.toggle('active', tab.getAttribute('data-filter-key') === statusKey);
        });

        renderPagination(statusKey, halAman, per, totalData, rows.length);
        syncHiddenFormFields();

        if (window.history && typeof window.history.replaceState === 'function') {
          window.history.replaceState(null, '', buildDashboardUrl(statusKey, '', halAman, per));
        }
      })
      .catch(function () {
        tbody.classList.remove('table-loading');
        showToast('galat', 'Gagal menghubungi API antrean pesanan.');
      });
  }

  // Tab filter via API saat tidak sedang mencari teks (?q=)
  document.querySelectorAll('.filter-tab[data-filter-key]').forEach(function (tab) {
    tab.addEventListener('click', function (e) {
      if (state.q !== '' || !apiUrl) return;
      e.preventDefault();
      var key = tab.getAttribute('data-filter-key') || 'semua';
      loadQueueViaApi(key, 1, state.per);
    });
  });

  // Ikon lonceng ke filter Menunggu via API bila tidak sedang mencari teks
  var bellBtn = document.getElementById('btnBellPending');
  if (bellBtn) {
    bellBtn.addEventListener('click', function (e) {
      if (state.q !== '' || !apiUrl) return;
      e.preventDefault();
      loadQueueViaApi('menunggu', 1, state.per);
      if (antreanSection) antreanSection.scrollIntoView({ behavior: 'smooth' });
    });
  }

  // Pagination link via API saat tidak sedang mencari teks
  if (paginationCtrl) {
    paginationCtrl.addEventListener('click', function (e) {
      var link = e.target.closest('a.page-btn[data-page-target]');
      if (!link || state.q !== '' || !apiUrl) return;
      e.preventDefault();
      var targetHal = parseInt(link.getAttribute('data-page-target') || '1', 10) || 1;
      loadQueueViaApi(state.status, targetHal, state.per);
    });
  }

  /* ===================== JUMLAH BARIS PER HALAMAN ===================== */
  var perPageForm   = document.getElementById('perPageForm');
  var perPageSelect = document.getElementById('perPageSelect');
  if (perPageForm && perPageSelect) {
    perPageSelect.addEventListener('change', function () {
      var nextPer = parseInt(perPageSelect.value || '10', 10) || 10;
      if (state.q === '' && apiUrl) {
        loadQueueViaApi(state.status, 1, nextPer);
        return;
      }
      perPageForm.submit();
    });
  }

  /* ===================== DROPDOWN PROFIL KASIR ===================== */
  var dropdownWrapper = document.getElementById('profileDropdownWrapper');
  var dropdownBtn     = document.getElementById('profileDropdownBtn');

  if (dropdownBtn && dropdownWrapper) {
    dropdownBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isActive = dropdownWrapper.classList.toggle('active');
      dropdownBtn.setAttribute('aria-expanded', isActive ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
      if (!dropdownWrapper.contains(e.target)) {
        dropdownWrapper.classList.remove('active');
        dropdownBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();
