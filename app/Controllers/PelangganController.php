<?php
/**
 * Controller halaman pelanggan: beranda, menu, keranjang, checkout, konfirmasi pesanan, QR meja.
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
            'custOptions' => require APP_ROOT . '/app/Models/customization-options.php', // pop-up ubah minuman
            'tableNumber' => $this->tableNumber(),
        ]);
    }

    /**
     * Checkout. GET: form (ringkasan diisi checkout.js dari keranjang).
     * POST: validasi, hitung ulang harga & stok di server, simpan, lalu redirect ke konfirmasi (Post/Redirect/Get).
     * Wajib sudah scan QR meja (meja_id di tabel pemesanan NOT NULL).
     */
    public function checkout(): void
    {
        $old    = ['nama' => '', 'email' => '', 'metode' => 'qris', 'catatan' => ''];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->meja) {
            $old = [
                'nama'    => trim((string) ($_POST['nama'] ?? '')),
                'email'   => trim((string) ($_POST['email'] ?? '')),
                'metode'  => (string) ($_POST['metode'] ?? ''),
                'catatan' => trim((string) ($_POST['catatan'] ?? '')),
            ];

            if (!csrf_check($_POST['csrf_token'] ?? null)) {
                $errors['form'] = 'Sesi formulir sudah kedaluwarsa. Silakan coba lagi.';
            }
            if ($old['nama'] === '' || mb_strlen($old['nama']) > 100) {
                $errors['nama'] = 'Nama wajib diisi (maksimal 100 karakter).';
            }
            if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
                $errors['email'] = 'Masukkan alamat email yang valid.';
            }
            if (!isset(PemesananModel::METODE[$old['metode']])) {
                $errors['metode'] = 'Pilih metode pembayaran.';
            }
            if (mb_strlen($old['catatan']) > PemesananModel::NOTE_MAX) {
                $errors['catatan'] = 'Catatan maksimal ' . PemesananModel::NOTE_MAX . ' karakter.';
            }

            if (!$errors) {
                $priced = PemesananModel::price(json_decode((string) ($_POST['items'] ?? ''), true));
                if ($priced['error'] !== null) {
                    $errors['form'] = $priced['error'];
                } else {
                    $id = PemesananModel::create([
                        'meja_id' => (int) $this->meja['id'],
                        'nama'    => $old['nama'],
                        'email'   => $old['email'],
                        'catatan' => $old['catatan'] !== '' ? $old['catatan'] : null,
                        'metode'  => $old['metode'],
                        'total'   => $priced['total'],
                        'lines'   => $priced['lines'],
                    ]);
                    $_SESSION['pesanan_baru'] = $id; // halaman konfirmasi mengosongkan keranjang satu kali
                    redirect(url('pesanan', ['id' => $id, 't' => tok($id)]));
                }
            }
        }

        view('pelanggan/public/checkout', [
            'tableNumber' => $this->tableNumber(),
            'hasTable'    => $this->meja !== null,
            'old'         => $old,
            'errors'      => $errors,
            'metodeList'  => PemesananModel::METODE,
            'taxRate'     => PemesananModel::TAX_RATE,
            'noteMax'     => PemesananModel::NOTE_MAX,
            'csrfToken'   => csrf_token(),
        ]);
    }

    /** Konfirmasi pesanan: ?page=pesanan&id=<id>&t=<tok(id)> (token mencegah membuka pesanan orang lain). */
    public function pesanan(): void
    {
        $id    = (int) ($_GET['id'] ?? 0);
        $token = (string) ($_GET['t'] ?? '');
        $order = ($id > 0 && hash_equals(tok($id), $token)) ? PemesananModel::find($id) : null;

        if (!$order) {
            http_response_code(404);
        }

        $justOrdered = $order && ($_SESSION['pesanan_baru'] ?? null) === $id;
        unset($_SESSION['pesanan_baru']);

        view('pelanggan/public/pesanan', [
            'order'       => $order,
            'justOrdered' => $justOrdered,
            'tableNumber' => $this->tableNumber(),
            'metodeList'  => PemesananModel::METODE,
            'statusLabel' => PemesananModel::STATUS_LABEL,
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

