<?php
/**
 * Controller untuk Halaman & Operasional Kasir
 */
class KasirController
{
    /**
     * Menampilkan Dashboard / Beranda Kasir
     */
    public function index(): void
    {
        // Guard autentikasi: wajib login dengan role 'kasir' atau 'admin'
        $staf = wajib_staf(['kasir', 'admin']);
        if (empty($staf)) {
            return;
        }

        view('kasir/dashboard', [
            'user' => $staf,
        ]);
    }
}
