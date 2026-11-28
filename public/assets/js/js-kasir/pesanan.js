/**
 * QuickBrew - Kasir Pesanan JS
 * Handles:
 *  1. Dropdown profil kasir
 *  2. Filter tab & pencarian ID pesanan
 *  3. Modal Verifikasi Pembayaran Tunai (2 kolom + kalkulator kembalian real-time)
 *  4. Modal Verifikasi Pembayaran QRIS (2 kolom khusus non-tunai)
 *  5. Modal Siap Antar & Batalkan Pesanan
 */

document.addEventListener('DOMContentLoaded', function () {

    const ordersMap     = window.QUICKBREW_ORDERS || {};
    const detailBaseUrl = window.QUICKBREW_DETAIL_URL || '?page=kasir-pesanan-detail';
    const menuImgBase   = window.QUICKBREW_MENU_IMG_BASE || '';

    function formatRibuan(angka) {
        const bersih = Math.max(0, Math.floor(Number(angka) || 0));
        return bersih.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function formatRupiah(angka) {
        return 'Rp ' + formatRibuan(angka);
    }

    function parseAngka(str) {
        const digits = String(str || '').replace(/[^0-9]/g, '');
        return digits === '' ? 0 : parseInt(digits, 10);
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderModalItems(items) {
        if (!Array.isArray(items) || items.length === 0) {
            return '';
        }
        return items.map(function (it) {
            const imgSrc = menuImgBase + (it.gambar || 'americano.png');
            const varsHtml = Array.isArray(it.varian) && it.varian.length > 0
                ? '<div class="verify-item-vars">' + it.varian.map(function (v) {
                    return '<div>' + escapeHtml(v) + '</div>';
                }).join('') + '</div>'
                : '';

            return '<div class="verify-item-entry">'
                + '<div class="verify-item-head">'
                +   '<img src="' + escapeHtml(imgSrc) + '" alt="' + escapeHtml(it.nama) + '" class="verify-item-thumb">'
                +   '<div>'
                +     '<div class="verify-item-title">' + escapeHtml(it.nama) + '</div>'
                +     '<div class="verify-item-price">' + escapeHtml(it.harga) + '</div>'
                +   '</div>'
                + '</div>'
                + varsHtml
                + '</div>';
        }).join('');
    }

    // =========================================================
    // 1. DROPDOWN PROFIL KASIR
    // =========================================================
    const profileWrapper = document.getElementById('profileDropdownWrapper');
    const profileBtn     = document.getElementById('profileDropdownBtn');

    if (profileBtn && profileWrapper) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            profileWrapper.classList.toggle('active');
            const expanded = profileWrapper.classList.contains('active');
            profileBtn.setAttribute('aria-expanded', expanded.toString());
        });

        document.addEventListener('click', function (e) {
            if (!profileWrapper.contains(e.target)) {
                profileWrapper.classList.remove('active');
                profileBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // =========================================================
    // 2. FILTER TABS & SEARCH (Halaman Antrean)
    // =========================================================
    const filterTabs  = document.querySelectorAll('.filter-tab');
    const tableRows   = document.querySelectorAll('#bodyPesanan tr[data-status]');
    const emptyRow    = document.getElementById('emptyRow');
    const searchInput = document.getElementById('searchPesanan');

    function filterAndSearchTable() {
        if (!tableRows.length) return;
        const activeTab    = document.querySelector('.filter-tab.active');
        const activeFilter = activeTab ? activeTab.getAttribute('data-filter') : 'semua';
        const keyword      = searchInput ? searchInput.value.trim().toLowerCase() : '';
        let visibleCount   = 0;

        tableRows.forEach(function (row) {
            const status    = row.getAttribute('data-status');
            const statusOk  = (activeFilter === 'semua') || (status === activeFilter);
            const idText    = (row.querySelector('.order-code') || {}).textContent || '';
            const itemText  = (row.querySelector('.td-item') || {}).textContent || '';
            const keywordOk = keyword === '' || idText.toLowerCase().includes(keyword) || itemText.toLowerCase().includes(keyword);
            const show      = statusOk && keywordOk;
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        if (emptyRow) {
            emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
        }
    }

    filterTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            filterTabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            filterAndSearchTable();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', filterAndSearchTable);
    }

    // =========================================================
    // 3. MODAL VERIFIKASI PEMBAYARAN TUNAI (2 KOLOM + KEMBALIAN)
    // =========================================================
    const tunaiVerifyModal      = document.getElementById('tunaiVerifyModal');
    const btnCloseTunaiModal    = document.getElementById('btnCloseTunaiModal');
    const btnCancelTunaiModal   = document.getElementById('btnCancelTunaiModal');
    const inputUangDiterima     = document.getElementById('inputUangDiterima');
    const tvKembalian           = document.getElementById('tvKembalian');
    const cashErrorMsg          = document.getElementById('cashErrorMsg');
    const btnSubmitTunaiSuccess = document.getElementById('btnSubmitTunaiSuccess');
    const btnSubmitTunaiFail    = document.getElementById('btnSubmitTunaiFail');

    let activeTunaiId    = 'A1234';
    let activeTunaiTotal = 26400;

    function hitungKembalianTunai() {
        if (!inputUangDiterima) return;
        const diterima = parseAngka(inputUangDiterima.value);

        if (diterima < activeTunaiTotal) {
            const kurang = activeTunaiTotal - diterima;
            if (tvKembalian) {
                tvKembalian.textContent = 'Rp 0';
                tvKembalian.style.color = '#8C7E74';
            }
            if (cashErrorMsg) {
                cashErrorMsg.textContent = 'Uang diterima kurang ' + formatRupiah(kurang) + ' dari total pembayaran.';
                cashErrorMsg.style.display = 'block';
            }
            if (btnSubmitTunaiSuccess) btnSubmitTunaiSuccess.disabled = true;
        } else {
            const kembalian = diterima - activeTunaiTotal;
            if (tvKembalian) {
                tvKembalian.textContent = formatRupiah(kembalian);
                tvKembalian.style.color = '#15803D';
            }
            if (cashErrorMsg) {
                cashErrorMsg.style.display = 'none';
            }
            if (btnSubmitTunaiSuccess) btnSubmitTunaiSuccess.disabled = false;
        }
    }

    function openTunaiModal(orderId) {
        const cleanId = String(orderId || 'A1234').replace(/^#/, '');
        const data    = ordersMap[cleanId] || null;
        activeTunaiId = cleanId;

        if (data) {
            activeTunaiTotal = Number(data.total_num) || 26400;
            const setTxt = function (id, val) {
                const el = document.getElementById(id);
                if (el) el.textContent = val;
            };
            setTxt('tvOrderId', '#' + cleanId);
            setTxt('tvCustomer', (data.pelanggan || '-') + ' (' + (data.telepon || '-') + ')');
            setTxt('tvMeja', data.meja || '-');
            setTxt('tvItemSummary', (data.ringkasan_item || '-') + ' (' + (data.jumlah_item || 1) + ' item)');
            setTxt('tvDurasi', data.durasi || '12 menit');
            setTxt('tvCatatan', data.catatan || 'Tidak ada');
            setTxt('tvSubtotal', data.subtotal || formatRupiah(data.subtotal_num || 0));
            setTxt('tvPajak', data.pajak || formatRupiah(data.pajak_num || 0));
            setTxt('tvTotal', data.total || formatRupiah(activeTunaiTotal));

            const itemsContainer = document.getElementById('tvItemsList');
            if (itemsContainer) {
                itemsContainer.innerHTML = renderModalItems(data.items || []);
            }
        }

        // Default nominal uang diterima: 30.000 bila total <= 30.000, selain itu pembulatan 10.000 ke atas
        const defaultDiterima = activeTunaiTotal <= 30000
            ? 30000
            : Math.ceil(activeTunaiTotal / 10000) * 10000;

        if (inputUangDiterima) {
            inputUangDiterima.value = formatRibuan(defaultDiterima);
        }
        hitungKembalianTunai();

        if (tunaiVerifyModal) {
            tunaiVerifyModal.classList.add('active');
        }
    }

    function closeTunaiModal() {
        if (tunaiVerifyModal) tunaiVerifyModal.classList.remove('active');
    }

    if (inputUangDiterima) {
        inputUangDiterima.addEventListener('input', function () {
            const angka = parseAngka(inputUangDiterima.value);
            inputUangDiterima.value = angka > 0 ? formatRibuan(angka) : '';
            hitungKembalianTunai();
        });
    }

    document.querySelectorAll('.cash-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            const raw = chip.getAttribute('data-nominal');
            const nominal = raw === 'pas' ? activeTunaiTotal : parseInt(raw || '0', 10);
            if (inputUangDiterima && nominal > 0) {
                inputUangDiterima.value = formatRibuan(nominal);
                hitungKembalianTunai();
            }
        });
    });

    if (btnCloseTunaiModal)  btnCloseTunaiModal.addEventListener('click', closeTunaiModal);
    if (btnCancelTunaiModal) btnCancelTunaiModal.addEventListener('click', closeTunaiModal);
    if (tunaiVerifyModal) {
        tunaiVerifyModal.addEventListener('click', function (e) {
            if (e.target === tunaiVerifyModal) closeTunaiModal();
        });
    }

    if (btnSubmitTunaiSuccess) {
        btnSubmitTunaiSuccess.addEventListener('click', function () {
            const diterima = inputUangDiterima ? parseAngka(inputUangDiterima.value) : activeTunaiTotal;
            if (diterima < activeTunaiTotal) {
                hitungKembalianTunai();
                return;
            }
            const sep = detailBaseUrl.indexOf('?') !== -1 ? '&' : '?';
            window.location.href = detailBaseUrl + sep + 'id=' + encodeURIComponent(activeTunaiId)
                + '&state=tunai_berhasil&diterima=' + encodeURIComponent(String(diterima));
        });
    }

    if (btnSubmitTunaiFail) {
        btnSubmitTunaiFail.addEventListener('click', function () {
            const diterima = inputUangDiterima ? Math.max(activeTunaiTotal, parseAngka(inputUangDiterima.value)) : 30000;
            const sep = detailBaseUrl.indexOf('?') !== -1 ? '&' : '?';
            window.location.href = detailBaseUrl + sep + 'id=' + encodeURIComponent(activeTunaiId)
                + '&state=tunai_gagal&diterima=' + encodeURIComponent(String(diterima));
        });
    }

    // =========================================================
    // 4. MODAL VERIFIKASI PEMBAYARAN QRIS
    // =========================================================
    const qrisVerifyModal      = document.getElementById('qrisVerifyModal');
    const btnCloseQrisModal    = document.getElementById('btnCloseQrisModal');
    const btnCancelQrisModal   = document.getElementById('btnCancelQrisModal');
    const btnSubmitQrisSuccess = document.getElementById('btnSubmitQrisSuccess');
    const btnSubmitQrisPending = document.getElementById('btnSubmitQrisPending');
    const btnSubmitQrisExpire  = document.getElementById('btnSubmitQrisExpire');

    let activeQrisId = 'A1236';

    function openQrisModal(orderId) {
        const cleanId = String(orderId || 'A1236').replace(/^#/, '');
        const data    = ordersMap[cleanId] || null;
        activeQrisId  = cleanId;

        if (data) {
            const setTxt = function (id, val) {
                const el = document.getElementById(id);
                if (el) el.textContent = val;
            };
            setTxt('qvOrderId', '#' + cleanId);
            setTxt('qvCustomer', (data.pelanggan || '-') + ' (' + (data.telepon || '-') + ')');
            setTxt('qvMeja', data.meja || '-');
            setTxt('qvItemSummary', (data.ringkasan_item || '-') + ' (' + (data.jumlah_item || 1) + ' item)');
            setTxt('qvDurasi', data.durasi || '5 menit');
            setTxt('qvBatasBayar', data.batas_bayar || '11:42 WIB (15 menit)');
            setTxt('qvCatatan', data.catatan || 'Tidak ada');
            setTxt('qvRef', data.qris_ref || ('MID-QRIS-' + cleanId));
            setTxt('qvSubtotal', data.subtotal || formatRupiah(data.subtotal_num || 0));
            setTxt('qvPajak', data.pajak || formatRupiah(data.pajak_num || 0));
            setTxt('qvTotal', data.total || formatRupiah(data.total_num || 0));

            const itemsContainer = document.getElementById('qvItemsList');
            if (itemsContainer) {
                itemsContainer.innerHTML = renderModalItems(data.items || []);
            }
        }

        if (qrisVerifyModal) {
            qrisVerifyModal.classList.add('active');
        }
    }

    function closeQrisModal() {
        if (qrisVerifyModal) qrisVerifyModal.classList.remove('active');
    }

    if (btnCloseQrisModal)  btnCloseQrisModal.addEventListener('click', closeQrisModal);
    if (btnCancelQrisModal) btnCancelQrisModal.addEventListener('click', closeQrisModal);
    if (qrisVerifyModal) {
        qrisVerifyModal.addEventListener('click', function (e) {
            if (e.target === qrisVerifyModal) closeQrisModal();
        });
    }

    if (btnSubmitQrisSuccess) {
        btnSubmitQrisSuccess.addEventListener('click', function () {
            const sep = detailBaseUrl.indexOf('?') !== -1 ? '&' : '?';
            window.location.href = detailBaseUrl + sep + 'id=' + encodeURIComponent(activeQrisId) + '&state=qris_berhasil';
        });
    }

    if (btnSubmitQrisPending) {
        btnSubmitQrisPending.addEventListener('click', function () {
            const sep = detailBaseUrl.indexOf('?') !== -1 ? '&' : '?';
            window.location.href = detailBaseUrl + sep + 'id=' + encodeURIComponent(activeQrisId) + '&state=qris_pending';
        });
    }

    if (btnSubmitQrisExpire) {
        btnSubmitQrisExpire.addEventListener('click', function () {
            const sep = detailBaseUrl.indexOf('?') !== -1 ? '&' : '?';
            window.location.href = detailBaseUrl + sep + 'id=' + encodeURIComponent(activeQrisId) + '&state=qris_expire';
        });
    }

    // =========================================================
    // 5. DELEGASI TOMBOL SIAP ANTAR, TUNAI, QRIS & BATALKAN
    // =========================================================
    const confirmModal   = document.getElementById('confirmModal');
    const modalOrderId   = document.getElementById('modalOrderId');
    const btnCancel      = document.getElementById('btnCancelModal');
    const btnConfirm     = document.getElementById('btnConfirmModal');
    let currentAntarRow  = null;

    function closeAntarModal() {
        if (confirmModal) confirmModal.classList.remove('active');
    }

    document.addEventListener('click', function (e) {
        const tunaiBtn = e.target.closest('.btn-open-tunai-modal');
        if (tunaiBtn) {
            openTunaiModal(tunaiBtn.getAttribute('data-order-id') || 'A1234');
            return;
        }

        const qrisBtn = e.target.closest('.btn-open-qris-modal');
        if (qrisBtn) {
            openQrisModal(qrisBtn.getAttribute('data-order-id') || 'A1236');
            return;
        }

        const strukBtn = e.target.closest('.btn-open-struk-modal');
        if (strukBtn) {
            openStrukModal(strukBtn.getAttribute('data-order-id') || 'A1235');
            return;
        }

        const antarBtn = e.target.closest('.btn-open-modal');
        if (antarBtn) {
            currentAntarRow = antarBtn.closest('tr');
            if (modalOrderId) modalOrderId.textContent = antarBtn.getAttribute('data-order') || '';
            if (confirmModal) confirmModal.classList.add('active');
        }
    });

    if (btnCancel) btnCancel.addEventListener('click', closeAntarModal);
    if (confirmModal) {
        confirmModal.addEventListener('click', function (e) {
            if (e.target === confirmModal) closeAntarModal();
        });
    }
    if (btnConfirm) {
        btnConfirm.addEventListener('click', function () {
            if (currentAntarRow) {
                const statusBadge = currentAntarRow.querySelector('.td-status .badge-pill');
                if (statusBadge) {
                    statusBadge.textContent = 'Selesai';
                    statusBadge.className   = 'badge-pill badge-selesai';
                }
                currentAntarRow.setAttribute('data-status', 'selesai');
                const btn = currentAntarRow.querySelector('.btn-open-modal');
                const rawCode = String(btn ? (btn.getAttribute('data-order') || 'A1232') : 'A1232').replace(/^#/, '');
                if (btn) {
                    btn.outerHTML = '<button type="button" class="btn-action-outline btn-open-struk-modal" data-order-id="' + escapeHtml(rawCode) + '">Cetak Struk</button>';
                }
            }
            closeAntarModal();
        });
    }

    // Modal Batalkan Pesanan di halaman detail
    const btnOpenCancelModal    = document.getElementById('btnOpenCancelModal');
    const cancelOrderModal      = document.getElementById('cancelOrderModal');
    const btnDismissCancelOrder = document.getElementById('btnDismissCancelOrder');
    const btnConfirmCancelOrder = document.getElementById('btnConfirmCancelOrder');
    const paymentAlertBanner    = document.getElementById('paymentAlertBanner');
    const badgeStatus           = document.getElementById('badgeStatus');

    if (btnOpenCancelModal && cancelOrderModal) {
        btnOpenCancelModal.addEventListener('click', function () {
            cancelOrderModal.classList.add('active');
        });
    }
    if (btnDismissCancelOrder && cancelOrderModal) {
        btnDismissCancelOrder.addEventListener('click', function () {
            cancelOrderModal.classList.remove('active');
        });
        cancelOrderModal.addEventListener('click', function (e) {
            if (e.target === cancelOrderModal) cancelOrderModal.classList.remove('active');
        });
    }
    if (btnConfirmCancelOrder && cancelOrderModal) {
        btnConfirmCancelOrder.addEventListener('click', function () {
            cancelOrderModal.classList.remove('active');
            if (paymentAlertBanner) {
                paymentAlertBanner.textContent = 'Pesanan ini telah dibatalkan.';
                paymentAlertBanner.classList.remove('is-paid');
                paymentAlertBanner.classList.add('is-cancelled');
            }
            if (badgeStatus) {
                badgeStatus.textContent = 'Dibatalkan';
                badgeStatus.className   = 'badge-pill badge-belum-bayar';
            }
            document.querySelectorAll('.btn-open-tunai-modal, .btn-open-qris-modal').forEach(function (b) {
                b.disabled = true;
            });
            if (btnOpenCancelModal) {
                btnOpenCancelModal.textContent = 'Dibatalkan';
                btnOpenCancelModal.disabled    = true;
            }
        });
    }

    // =========================================================
    // 6. MODAL CETAK STRUK & NOTIFIKASI TOAST (TANPA PDF / PRINT)
    // =========================================================
    const strukModal           = document.getElementById('strukModal');
    const btnCloseStrukModal   = document.getElementById('btnCloseStrukModal');
    const btnCancelStrukModal  = document.getElementById('btnCancelStrukModal');
    const btnConfirmCetakStruk = document.getElementById('btnConfirmCetakStruk');
    const strukToast           = document.getElementById('strukToast');
    const strukToastText       = document.getElementById('strukToastText');
    let toastTimer             = null;

    function showStrukToast(pesan) {
        if (!strukToast) return;
        if (strukToastText) {
            strukToastText.textContent = pesan || 'Struk berhasil dicetak';
        }
        strukToast.classList.add('show');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            strukToast.classList.remove('show');
        }, 3500);
    }

    function openStrukModal(orderId) {
        const cleanId = String(orderId || 'A1235').replace(/^#/, '');
        const data    = ordersMap[cleanId] || null;

        if (data) {
            const setTxt = function (id, val) {
                const el = document.getElementById(id);
                if (el) el.textContent = val;
            };
            setTxt('smOrderId', '#' + cleanId);
            setTxt('smCustomer', (data.pelanggan || '-') + ' (' + (data.telepon || '-') + ')');
            setTxt('smMeja', data.meja || '-');
            setTxt('smItemSummary', (data.ringkasan_item || '-') + ' / ' + (data.jumlah_item || 1) + ' item');
            setTxt('smMetode', data.metode || '-');
            setTxt('smTotalLeft', data.total || formatRupiah(data.total_num || 0));
            setTxt('smCatatan', data.catatan || 'Tidak ada');
            setTxt('smSubtotal', data.subtotal || formatRupiah(data.subtotal_num || 0));
            setTxt('smPajak', data.pajak || formatRupiah(data.pajak_num || 0));
            setTxt('smTotalRight', data.total || formatRupiah(data.total_num || 0));

            const badgeStatus = document.getElementById('smBadgeStatus');
            if (badgeStatus) {
                const st = data.status_pesanan === 'Diproses' ? 'Diproses' : 'Selesai';
                badgeStatus.textContent = st;
                badgeStatus.className   = 'badge-pill ' + (st === 'Selesai' ? 'badge-selesai' : 'badge-diproses');
            }

            const itemsContainer = document.getElementById('smItemsList');
            if (itemsContainer) {
                itemsContainer.innerHTML = renderModalItems(data.items || []);
            }
        }

        if (strukModal) {
            strukModal.classList.add('active');
        }
    }

    function closeStrukModal() {
        if (strukModal) strukModal.classList.remove('active');
    }

    if (btnCloseStrukModal)  btnCloseStrukModal.addEventListener('click', closeStrukModal);
    if (btnCancelStrukModal) btnCancelStrukModal.addEventListener('click', closeStrukModal);
    if (strukModal) {
        strukModal.addEventListener('click', function (e) {
            if (e.target === strukModal) closeStrukModal();
        });
    }

    if (btnConfirmCetakStruk) {
        btnConfirmCetakStruk.addEventListener('click', function () {
            closeStrukModal();
            showStrukToast('Struk berhasil dicetak');
        });
    }

    // Tutup semua modal dengan tombol Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeTunaiModal();
            closeQrisModal();
            closeStrukModal();
            closeAntarModal();
            if (cancelOrderModal) cancelOrderModal.classList.remove('active');
        }
    });

});
