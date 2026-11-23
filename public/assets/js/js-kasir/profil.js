// profil.js — QuickBrew Kasir
// Menangani: Dropdown Kasir, Modal Ubah Password, dan Interaksi Profil

document.addEventListener('DOMContentLoaded', function () {
    /* ===================== DROPDOWN PROFIL KASIR ===================== */
    const dropdownWrapper = document.getElementById('profileDropdownWrapper');
    const dropdownBtn     = document.getElementById('profileDropdownBtn');

    if (dropdownBtn && dropdownWrapper) {
        dropdownBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isActive = dropdownWrapper.classList.toggle('active');
            dropdownBtn.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        });

        document.addEventListener('click', function (e) {
            if (!dropdownWrapper.contains(e.target)) {
                dropdownWrapper.classList.remove('active');
                dropdownBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* ===================== MODAL UBAH PASSWORD ===================== */
    const modalOverlay = document.getElementById('passwordModalOverlay');
    const btnOpenModal = document.getElementById('btnOpenPasswordModal');
    const btnCancel    = document.getElementById('btnCancelPasswordModal');
    const btnSave      = document.getElementById('btnSavePassword');
    const formPassword = document.getElementById('formChangePassword');

    function openPasswordModal() {
        if (modalOverlay) {
            modalOverlay.classList.add('active');
            const currentPassInput = document.getElementById('currentPassword');
            if (currentPassInput) currentPassInput.focus();
        }
    }

    function closePasswordModal() {
        if (modalOverlay) {
            modalOverlay.classList.remove('active');
            if (formPassword) formPassword.reset();
        }
    }

    if (btnOpenModal) btnOpenModal.addEventListener('click', openPasswordModal);
    if (btnCancel)    btnCancel.addEventListener('click', closePasswordModal);

    if (btnSave) {
        btnSave.addEventListener('click', function () {
            const currentPassword = document.getElementById('currentPassword');
            const newPassword     = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');

            if (!currentPassword.value || !newPassword.value || !confirmPassword.value) {
                alert('Silakan lengkapi semua kolom password.');
                return;
            }

            if (newPassword.value.length < 8) {
                alert('Password baru minimal 8 karakter.');
                return;
            }

            if (newPassword.value !== confirmPassword.value) {
                alert('Konfirmasi password baru tidak cocok.');
                return;
            }

            alert('Password berhasil diperbarui (Simulasi tampilan).');
            closePasswordModal();
        });
    }

    // Tutup modal jika klik di luar area box
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function (e) {
            if (e.target === modalOverlay) closePasswordModal();
        });
    }

    // Tutup modal dengan tombol Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('active')) {
            closePasswordModal();
        }
    });

    /* ===================== SIMPAN PERUBAHAN PROFIL ===================== */
    const btnSaveProfile = document.querySelector('.btn-save-profile');
    if (btnSaveProfile) {
        btnSaveProfile.addEventListener('click', function () {
            const nama = document.getElementById('inputNama')?.value.trim();
            if (!nama) {
                alert('Nama lengkap tidak boleh kosong.');
                return;
            }
            alert('Perubahan profil berhasil disimpan (Simulasi tampilan).');
        });
    }

    /* ===================== UBAH FOTO PROFIL ===================== */
    const btnChangePhoto = document.querySelector('.btn-change-photo');
    if (btnChangePhoto) {
        btnChangePhoto.addEventListener('click', function () {
            alert('Fitur unggah foto profil (Simulasi tampilan).');
        });
    }
});
