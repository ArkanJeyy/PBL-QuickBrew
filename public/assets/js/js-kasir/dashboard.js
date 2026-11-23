// dashboard.js — QuickBrew Kasir
// Handles: modal siap antar, filter tabs, search pesanan

(function () {
  /* ===================== MODAL SIAP ANTAR ===================== */
  const overlay    = document.getElementById('confirmModal');
  const btnCancel  = document.getElementById('btnCancelModal');
  const btnConfirm = document.getElementById('btnConfirmModal');

  function openModal() {
    overlay.classList.add('active');
  }
  function closeModal() {
    overlay.classList.remove('active');
  }

  document.querySelectorAll('.btn-open-modal').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal();
    });
  });

  if (btnCancel)  btnCancel.addEventListener('click', closeModal);
  if (btnConfirm) btnConfirm.addEventListener('click', function () {
    closeModal();
    // TODO: kirim AJAX / redirect ke endpoint ubah status pesanan
  });

  if (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeModal();
    });
  }

  /* ===================== FILTER TABS ===================== */
  const filterBtns = document.querySelectorAll('.filter-tab');
  const tableRows  = document.querySelectorAll('table tbody tr[data-status]');

  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      // Set active tab
      filterBtns.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');

      var filter = btn.getAttribute('data-filter');

      tableRows.forEach(function (row) {
        if (filter === 'semua') {
          row.style.display = '';
        } else {
          row.style.display = row.getAttribute('data-status') === filter ? '' : 'none';
        }
      });
    });
  });

  /* ===================== SEARCH PESANAN ===================== */
  var searchInput = document.getElementById('searchPesanan');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var q = this.value.toLowerCase().trim();
      tableRows.forEach(function (row) {
        var id = row.querySelector('.order-code');
        if (!id) return;
        row.style.display = id.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  /* ===================== DROPDOWN PROFIL KASIR ===================== */
  const dropdownWrapper = document.getElementById('profileDropdownWrapper');
  const dropdownBtn     = document.getElementById('profileDropdownBtn');

  if (dropdownBtn && dropdownWrapper) {
    dropdownBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isActive = dropdownWrapper.classList.toggle('active');
      dropdownBtn.setAttribute('aria-expanded', isActive ? 'true' : 'false');
    });

    // Tutup dropdown jika pengguna klik di luar menu
    document.addEventListener('click', function (e) {
      if (!dropdownWrapper.contains(e.target)) {
        dropdownWrapper.classList.remove('active');
        dropdownBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();
