/**
 * Logic untuk halaman Stok Kasir
 * Menangani toggle dropdown profil kasir, tutup di luar area, dan tombol Escape
 */
document.addEventListener('DOMContentLoaded', () => {
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
