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

    /**
     * Menampilkan Halaman Pesanan Kasir
     */
    public function pesanan(): void
    {
        // Guard autentikasi: wajib login dengan role 'kasir' atau 'admin'
        $staf = wajib_staf(['kasir', 'admin']);
        if (empty($staf)) {
            return;
        }

        view('kasir/pesanan', [
            'user' => $staf,
        ]);
    }

    /**
     * Menampilkan Halaman Profil Akun Kasir
     */
    public function profil(): void
    {
        // Guard autentikasi: wajib login dengan role 'kasir' atau 'admin'
        $staf = wajib_staf(['kasir', 'admin']);
        if (empty($staf)) {
            return;
        }

        view('kasir/profil', [
            'user' => $staf,
        ]);
    }
}
