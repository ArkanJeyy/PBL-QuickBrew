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
});
