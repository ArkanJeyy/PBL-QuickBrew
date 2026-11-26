document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('menuSearch');
    const filterButtons = Array.from(document.querySelectorAll('.menu-filter'));
    const menuCards = Array.from(document.querySelectorAll('.menu-card'));
    const emptyState = document.getElementById('menuEmptyState');
    let activeCategory = 'semua';

    const updateMenu = () => {
        const searchTerm = searchInput.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        menuCards.forEach((card) => {
            const matchesCategory = activeCategory === 'semua' || card.dataset.kategori === activeCategory;
            const matchesName = card.dataset.nama.includes(searchTerm);
            const visible = matchesCategory && matchesName;

            card.classList.toggle('tw-hidden', !visible);
            if (visible) visibleCount += 1;
        });

        emptyState.classList.toggle('tw-hidden', visibleCount > 0);
    };

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeCategory = button.dataset.kategori;

            filterButtons.forEach((filterButton) => {
                const active = filterButton === button;
                filterButton.setAttribute('aria-pressed', String(active));
                filterButton.classList.toggle('tw-bg-brand', active);
                filterButton.classList.toggle('tw-text-white', active);
                filterButton.classList.toggle('tw-bg-white', !active);
                filterButton.classList.toggle('tw-text-brand', !active);
            });

            updateMenu();
        });
    });

    searchInput.addEventListener('input', updateMenu);

    /* ===================== DROPDOWN PROFIL KASIR ===================== */
    const dropdownWrapper = document.getElementById('profileDropdownWrapper');
    const dropdownBtn     = document.getElementById('profileDropdownBtn');

    if (dropdownBtn && dropdownWrapper) {
        dropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isActive = dropdownWrapper.classList.toggle('active');
            dropdownBtn.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        });

        // Tutup dropdown jika pengguna klik di luar menu
        document.addEventListener('click', (e) => {
            if (!dropdownWrapper.contains(e.target)) {
                dropdownWrapper.classList.remove('active');
                dropdownBtn.setAttribute('aria-expanded', 'false');
            }
        });

        // Tutup dropdown dengan tombol Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' || e.key === 'Esc') {
                dropdownWrapper.classList.remove('active');
                dropdownBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* ===================== POP UP DETAIL MENU (READ-ONLY) ===================== */
    const detailModal               = document.getElementById('menuDetailModal');
    const detailModalBackdrop       = document.getElementById('menuDetailModalBackdrop');
    const detailModalCloseBtn       = document.getElementById('menuDetailModalCloseBtn');
    const detailModalActionCloseBtn = document.getElementById('menuDetailModalActionCloseBtn');
    const modalSubtitle             = document.getElementById('menuDetailModalSubtitle');
    const modalFoto                 = document.getElementById('modalMenuFoto');
    const modalFotoPlaceholder      = document.getElementById('modalMenuFotoPlaceholder');
    const modalNama                 = document.getElementById('modalMenuNama');
    const modalKategori             = document.getElementById('modalMenuKategori');
    const modalHarga                = document.getElementById('modalMenuHarga');
    const modalDeskripsi            = document.getElementById('modalMenuDeskripsi');
    const modalStatusBadge          = document.getElementById('modalMenuStatusBadge');
    const modalStok                 = document.getElementById('modalMenuStok');
    const modalCustomNote           = document.getElementById('modalMenuCustomizableNote');

    let lastActiveTrigger = null;

    const formatRupiah = (val) => {
        const num = Number(val) || 0;
        return 'Rp ' + num.toLocaleString('id-ID');
    };

    const openDetailModal = (btn) => {
        if (!detailModal) return;

        lastActiveTrigger = btn;

        const nama         = btn.dataset.nama || '-';
        const kategori     = btn.dataset.kategori || '-';
        const kategoriSlug = (btn.dataset.kategoriSlug || kategori).toLowerCase().trim();
        const harga        = btn.dataset.harga || '0';
        const gambarUrl    = btn.dataset.gambarUrl || '';
        const deskripsi    = btn.dataset.deskripsi || 'Belum ada deskripsi.';
        const stok         = parseInt(btn.dataset.stok, 10) || 0;
        const aktif        = btn.dataset.aktif === '1' || btn.dataset.aktif === 'true';
        const tersedia     = stok > 0 && aktif;

        // Header Subtitle: Informasi {nama menu}
        if (modalSubtitle) {
            modalSubtitle.textContent = 'Informasi ' + nama;
        }

        // Foto
        if (modalFoto && modalFotoPlaceholder) {
            if (gambarUrl) {
                modalFoto.src = gambarUrl;
                modalFoto.alt = nama;
                modalFoto.classList.remove('tw-hidden');
                modalFotoPlaceholder.classList.add('tw-hidden');
            } else {
                modalFoto.src = '';
                modalFoto.alt = '';
                modalFoto.classList.add('tw-hidden');
                modalFotoPlaceholder.classList.remove('tw-hidden');
            }
        }

        // Teks Informasi Menu
        if (modalNama) modalNama.textContent = nama;
        if (modalKategori) modalKategori.textContent = kategori;
        if (modalHarga) modalHarga.textContent = formatRupiah(harga);
        if (modalDeskripsi) modalDeskripsi.textContent = deskripsi;

        // Badge Status & Stok
        if (modalStatusBadge) {
            if (tersedia) {
                modalStatusBadge.textContent = 'Tersedia';
                modalStatusBadge.className = 'tw-rounded-full tw-px-2.5 tw-py-0.5 tw-text-xs tw-font-medium tw-bg-green-100 tw-text-green-600';
            } else {
                modalStatusBadge.textContent = 'Habis';
                modalStatusBadge.className = 'tw-rounded-full tw-px-2.5 tw-py-0.5 tw-text-xs tw-font-medium tw-bg-red-100 tw-text-red-500';
            }
        }
        if (modalStok) {
            modalStok.textContent = stok + ' pcs';
        }

        // Chip kustomisasi khusus minuman
        if (modalCustomNote) {
            const isMinuman = kategoriSlug === 'minuman';
            modalCustomNote.classList.toggle('tw-hidden', !isMinuman);
            modalCustomNote.classList.toggle('tw-inline-flex', isMinuman);
        }

        // Tampilkan modal & kunci scroll body
        detailModal.classList.remove('tw-hidden');
        detailModal.classList.add('tw-flex');
        document.body.style.overflow = 'hidden';

        // Fokus ke tombol X
        if (detailModalCloseBtn) {
            detailModalCloseBtn.focus();
        }
    };

    const closeDetailModal = () => {
        if (!detailModal || detailModal.classList.contains('tw-hidden')) return;

        detailModal.classList.add('tw-hidden');
        detailModal.classList.remove('tw-flex');
        document.body.style.overflow = '';

        // Kembalikan fokus ke tombol pemicu
        if (lastActiveTrigger) {
            lastActiveTrigger.focus();
            lastActiveTrigger = null;
        }
    };

    // Klik tombol titik tiga membuka modal tanpa memicu kartu
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.menu-detail-btn');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            openDetailModal(btn);
        }
    });

    // Tutup lewat tombol X
    if (detailModalCloseBtn) {
        detailModalCloseBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeDetailModal();
        });
    }

    // Tutup lewat tombol Tutup di footer
    if (detailModalActionCloseBtn) {
        detailModalActionCloseBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeDetailModal();
        });
    }

    // Tutup lewat klik overlay / backdrop
    if (detailModal) {
        detailModal.addEventListener('click', (e) => {
            if (e.target === detailModal || e.target === detailModalBackdrop) {
                closeDetailModal();
            }
        });
    }

    // Tutup lewat tombol Escape & trap fokus Tab di dalam modal
    document.addEventListener('keydown', (e) => {
        if (!detailModal || detailModal.classList.contains('tw-hidden')) return;

        if (e.key === 'Escape' || e.key === 'Esc') {
            e.preventDefault();
            closeDetailModal();
            return;
        }

        if (e.key === 'Tab') {
            const focusableElements = detailModal.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            const focusables = Array.from(focusableElements).filter((el) => el.offsetParent !== null);
            if (focusables.length === 0) return;

            const firstEl = focusables[0];
            const lastEl  = focusables[focusables.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === firstEl) {
                    e.preventDefault();
                    lastEl.focus();
                }
            } else {
                if (document.activeElement === lastEl) {
                    e.preventDefault();
                    firstEl.focus();
                }
            }
        }
    });
});
