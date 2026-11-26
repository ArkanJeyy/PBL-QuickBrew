/**
 * QuickBrew - Kasir Pesanan JS
 * Handles: profile dropdown, filter tabs, search, modal siap antar
 */

document.addEventListener('DOMContentLoaded', function () {

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
    // 2. FILTER TABS (Semua / Menunggu / Diproses / Selesai)
    // =========================================================
    const filterTabs = document.querySelectorAll('.filter-tab');
    const tableRows  = document.querySelectorAll('#bodyPesanan tr[data-status]');
    const emptyRow   = document.getElementById('emptyRow');

    function applyFilter(filter) {
        let visibleCount = 0;
        tableRows.forEach(function (row) {
            const status = row.getAttribute('data-status');
            const show = (filter === 'semua') || (status === filter);
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
            applyFilter(tab.getAttribute('data-filter'));
        });
    });

    // =========================================================
    // 3. SEARCH (filter berdasarkan ID pesanan)
    // =========================================================
    const searchInput = document.getElementById('searchPesanan');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const keyword = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            // Cari filter aktif saat ini
            const activeTab   = document.querySelector('.filter-tab.active');
            const activeFilter = activeTab ? activeTab.getAttribute('data-filter') : 'semua';

            tableRows.forEach(function (row) {
                const status = row.getAttribute('data-status');
                const statusOk = (activeFilter === 'semua') || (status === activeFilter);
                const idText   = (row.querySelector('.order-code') || {}).textContent || '';
                const keywordOk = keyword === '' || idText.toLowerCase().includes(keyword);
                const show = statusOk && keywordOk;
                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            if (emptyRow) {
                emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
            }
        });
    }

    // =========================================================
    // 4. MODAL KONFIRMASI "SIAP ANTAR" & "BAYAR" (ANTREAN TABEL)
    // =========================================================
    const confirmModal  = document.getElementById('confirmModal');
    const modalOrderId  = document.getElementById('modalOrderId');
    const btnCancel     = document.getElementById('btnCancelModal');
    const btnConfirm    = document.getElementById('btnConfirmModal');

    const payTableModal         = document.getElementById('payTableModal');
    const modalPayOrderId       = document.getElementById('modalPayOrderId');
    const modalPayTotal         = document.getElementById('modalPayTotal');
    const btnCancelPayTableModal= document.getElementById('btnCancelPayTableModal');
    const btnConfirmPayTableModal= document.getElementById('btnConfirmPayTableModal');
    let currentPayingRow        = null;

    function openModal(orderId) {
        if (modalOrderId) modalOrderId.textContent = orderId || '';
        if (confirmModal) confirmModal.classList.add('active');
    }

    function closeModal() {
        if (confirmModal) confirmModal.classList.remove('active');
        if (payTableModal) payTableModal.classList.remove('active');
    }

    // Tombol "Siap Antar" di tabel
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-open-modal');
        if (btn) {
            openModal(btn.getAttribute('data-order') || '');
        }

        const payBtn = e.target.closest('.btn-open-pay-modal');
        if (payBtn) {
            const orderId = payBtn.getAttribute('data-order') || '#A1234';
            const total   = payBtn.getAttribute('data-total') || 'Rp 22.000';
            currentPayingRow = payBtn.closest('tr');
            if (modalPayOrderId) modalPayOrderId.textContent = orderId;
            if (modalPayTotal) modalPayTotal.textContent = total;
            if (payTableModal) payTableModal.classList.add('active');
        }
    });

    if (btnCancel)  btnCancel.addEventListener('click', closeModal);
    if (confirmModal) {
        confirmModal.addEventListener('click', function (e) {
            if (e.target === confirmModal) closeModal();
        });
    }

    if (btnCancelPayTableModal) btnCancelPayTableModal.addEventListener('click', closeModal);
    if (payTableModal) {
        payTableModal.addEventListener('click', function (e) {
            if (e.target === payTableModal) closeModal();
        });
    }

    if (btnConfirmPayTableModal) {
        btnConfirmPayTableModal.addEventListener('click', function () {
            closeModal();
            if (currentPayingRow) {
                // Update badge pembayaran & status
                const bayarBadge = currentPayingRow.querySelector('.td-bayar .badge-pill');
                if (bayarBadge) {
                    bayarBadge.textContent = 'Lunas';
                    bayarBadge.className = 'badge-pill badge-lunas';
                }
                const statusBadge = currentPayingRow.querySelector('.td-status .badge-pill');
                if (statusBadge) {
                    statusBadge.textContent = 'Diproses';
                    statusBadge.className = 'badge-pill badge-diproses';
                }
                currentPayingRow.setAttribute('data-status', 'diproses');

                // Ubah tombol Bayar jadi tombol Siap Antar
                const aksiContainer = currentPayingRow.querySelector('.td-aksi .action-buttons');
                const orderCode = (currentPayingRow.querySelector('.order-code') || {}).textContent || '#A1234';
                if (aksiContainer) {
                    const payBtn = aksiContainer.querySelector('.btn-open-pay-modal');
                    if (payBtn) {
                        payBtn.outerHTML = '<button type="button" class="btn-action-outline btn-open-modal" data-order="' + orderCode + '">Siap Antar</button>';
                    }
                }
            }
        });
    }

    if (btnConfirm) {
        btnConfirm.addEventListener('click', function () {
            // TODO: kirim request ke backend untuk update status pesanan
            closeModal();
        });
    }

    // Tutup modal dengan Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal();
            if (payConfirmModal) payConfirmModal.classList.remove('active');
            if (cancelOrderModal) cancelOrderModal.classList.remove('active');
        }
    });

    // =========================================================
    // 5. HALAMAN DETAIL PESANAN (MODAL BAYAR & BATALKAN)
    // =========================================================
    const btnOpenPayModal     = document.getElementById('btnOpenPayModal');
    const payConfirmModal     = document.getElementById('payConfirmModal');
    const btnCancelPayModal   = document.getElementById('btnCancelPayModal');
    const btnConfirmPayModal  = document.getElementById('btnConfirmPayModal');

    const btnOpenCancelModal    = document.getElementById('btnOpenCancelModal');
    const cancelOrderModal      = document.getElementById('cancelOrderModal');
    const btnDismissCancelOrder = document.getElementById('btnDismissCancelOrder');
    const btnConfirmCancelOrder = document.getElementById('btnConfirmCancelOrder');

    const paymentAlertBanner = document.getElementById('paymentAlertBanner');
    const badgePembayaran    = document.getElementById('badgePembayaran');
    const badgeStatus        = document.getElementById('badgeStatus');
    const orderHistoryList   = document.getElementById('orderHistoryList');

    function appendHistory(text) {
        if (!orderHistoryList) return;
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        const li = document.createElement('li');
        li.className = 'history-item';
        li.innerHTML = '<span class="history-time">' + hh + ':' + mm + '</span>'
            + '<span class="history-dot"></span>'
            + '<span class="history-text">' + text + '</span>';
        orderHistoryList.appendChild(li);
    }

    if (btnOpenPayModal && payConfirmModal) {
        btnOpenPayModal.addEventListener('click', function () {
            payConfirmModal.classList.add('active');
        });
    }
    if (btnCancelPayModal && payConfirmModal) {
        btnCancelPayModal.addEventListener('click', function () {
            payConfirmModal.classList.remove('active');
        });
        payConfirmModal.addEventListener('click', function (e) {
            if (e.target === payConfirmModal) payConfirmModal.classList.remove('active');
        });
    }
    if (btnConfirmPayModal && payConfirmModal) {
        btnConfirmPayModal.addEventListener('click', function () {
            payConfirmModal.classList.remove('active');
            if (paymentAlertBanner) {
                paymentAlertBanner.textContent = 'Pembayaran diterima. Pesanan sedang diproses oleh dapur/barista.';
                paymentAlertBanner.classList.add('is-paid');
            }
            if (badgePembayaran) {
                badgePembayaran.textContent = 'Lunas';
                badgePembayaran.className = 'badge-pill badge-lunas';
            }
            if (badgeStatus) {
                badgeStatus.textContent = 'Diproses';
                badgeStatus.className = 'badge-pill badge-diproses';
            }
            if (btnOpenPayModal) {
                btnOpenPayModal.textContent = 'Sudah Dibayar';
                btnOpenPayModal.disabled = true;
            }
            if (btnOpenCancelModal) {
                btnOpenCancelModal.disabled = true;
            }
            appendHistory('Pembayaran diterima (Tunai)');
        });
    }

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
                badgeStatus.className = 'badge-pill badge-belum-bayar';
            }
            if (btnOpenPayModal) btnOpenPayModal.disabled = true;
            if (btnOpenCancelModal) {
                btnOpenCancelModal.textContent = 'Dibatalkan';
                btnOpenCancelModal.disabled = true;
            }
            appendHistory('Pesanan dibatalkan oleh kasir');
        });
    }

});
