<?php
/**
 * Controller untuk Halaman & Pengelolaan Menu Kasir
 */
class KasirMenuController
{
    /**
     * Menampilkan Halaman Daftar Menu Kasir
     */
    public function index(): void
    {
        // Guard autentikasi: wajib login dengan role 'kasir' atau 'admin'
        $staf = wajib_staf(['kasir', 'admin']);
        if (empty($staf)) {
            return;
        }

        // Ambil seluruh menu dari database (dengan fallback bawaan jika DB offline)
        $menus = MenuModel::all();

        view('kasir/menu', [
            'user' => $staf,
            'menu' => $menus,
        ]);
    }
}

// Alias untuk fleksibilitas pemanggilan class jika diperlukan
if (!class_exists('MenuController', false)) {
    class_alias(KasirMenuController::class, 'MenuController');
}
