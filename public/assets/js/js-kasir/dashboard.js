/**
 * QuickBrew - Script Dashboard Kasir
 * Menangani interaksi pada dashboard kasir (modal konfirmasi siap diantar, dsb.)
 */
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('confirmModal');
    const openButtons = document.querySelectorAll('.btn-open-modal');
    const btnCancel = document.getElementById('btnCancelModal');
    const btnConfirm = document.getElementById('btnConfirmModal');

    if (modal) {
        openButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                modal.classList.add('active');
            });
        });

        if (btnCancel) {
            btnCancel.addEventListener('click', () => {
                modal.classList.remove('active');
            });
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });

        if (btnConfirm) {
            btnConfirm.addEventListener('click', () => {
                modal.classList.remove('active');
                alert('Pesanan berhasil ditandai siap diantar!');
            });
        }
    }
});
