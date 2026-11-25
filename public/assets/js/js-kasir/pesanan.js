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
    // 4. MODAL KONFIRMASI "SIAP ANTAR"
    // =========================================================
    const confirmModal  = document.getElementById('confirmModal');
    const modalOrderId  = document.getElementById('modalOrderId');
    const btnCancel     = document.getElementById('btnCancelModal');
    const btnConfirm    = document.getElementById('btnConfirmModal');

    function openModal(orderId) {
        if (modalOrderId) modalOrderId.textContent = orderId || '';
        if (confirmModal) confirmModal.classList.add('active');
    }

    function closeModal() {
        if (confirmModal) confirmModal.classList.remove('active');
    }

    // Tombol "Siap Antar" di tabel
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-open-modal');
        if (btn) {
            openModal(btn.getAttribute('data-order') || '');
        }
    });

    if (btnCancel)  btnCancel.addEventListener('click', closeModal);
    if (confirmModal) {
        confirmModal.addEventListener('click', function (e) {
            if (e.target === confirmModal) closeModal();
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
        if (e.key === 'Escape') closeModal();
    });

});
