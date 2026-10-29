<?php
/**
 * Controller halaman pelanggan: beranda, menu, keranjang.
 */
final class PelangganController
{
    /** Meja aktif (dari QR ?meja=<kode_qr>, disimpan di session). */
    private ?array $meja;

    public function __construct()
    {
        if (isset($_GET['meja'])) {
            $raw = trim((string) $_GET['meja']);
            if ($raw === '' || strcasecmp($raw, 'reset') === 0) {
                unset($_SESSION['meja']);
            } else {
                $meja = MejaModel::findByKode($raw);
                if ($meja) {
                    $_SESSION['meja'] = $meja;
                }
            }
        }
        $this->meja = $_SESSION['meja'] ?? null;
    }

    private function tableNumber(): ?string
    {
        return $this->meja['no_meja'] ?? null;
    }

    public function home(): void
    {
        view('pelanggan/public/index', [
            'favorites'   => MenuModel::favorites(6),
            'tableNumber' => $this->tableNumber(),
        ]);
    }

    public function menu(): void
    {
        view('pelanggan/public/menu', [
            'menuItems'   => MenuModel::all(),
            'categories'  => MenuModel::categories(),
            'custOptions' => require APP_ROOT . '/app/Models/customization-options.php',
            'tableNumber' => $this->tableNumber(),
        ]);
    }

    public function cart(): void
    {
        view('pelanggan/public/cart', [
            'tableNumber' => $this->tableNumber(),
        ]);
    }

    public function qr(): void
    {
        $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $originUrl = $scheme . '://' . $host . BASE_URL;

        view('pelanggan/public/qr', [
            'tables'      => MejaModel::all(),
            'tableNumber' => $this->tableNumber(),
            'originUrl'   => $originUrl,
        ]);
    }
}

