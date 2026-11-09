<?php
require_once APP_ROOT . '/app/Helpers/QrSvg.php';
require_once APP_ROOT . '/app/Services/PembayaranService.php';

/**
 * Controller halaman pelanggan: beranda, menu, keranjang, checkout, pembayaran, status pesanan, QR meja.
 */
final class PelangganController
{
    /** Interval minimal sinkronisasi status QRIS ke Midtrans per sesi (detik). */
    private const QRIS_SYNC_THROTTLE_SECONDS = 5;

    /** Meja aktif (dari QR ?meja=<kode_qr>, disimpan di session). */
    private ?array $meja;

    private ?PembayaranService $pembayaranService;

    /** @var callable|null */
    private $redirector;

    public function __construct(?PembayaranService $pembayaranService = null, ?callable $redirector = null)
    {
        $this->pembayaranService = $pembayaranService;
        $this->redirector        = $redirector;

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

    /** Ambil pesanan dari ?id=&t= (token tok() mencegah membuka pesanan orang lain); null bila tidak cocok. */
    private function orderFromRequest(): ?array
    {
        $id    = (int) ($_GET['id'] ?? 0);
        $token = (string) ($_GET['t'] ?? '');
        return ($id > 0 && hash_equals(tok($id), $token)) ? PemesananModel::find($id) : null;
    }

    /** Simpan pesanan terakhir di session agar bisa dibuka lagi dari tombol "Pesanan Saya" di Beranda. */
    private function rememberOrder(int $id): void
    {
        $_SESSION['pesanan_terakhir'] = $id;
    }

    /** Tautan halaman Status Pesanan untuk sebuah pesanan. */
    private function statusUrl(int $id): string
    {
        return url('status', ['id' => $id, 't' => tok($id)]);
    }

    /** Redirect lewat $redirector (dipakai tests/pembayaran_test.php) atau redirect() biasa. */
    private function redirectTo(string $url): void
    {
        if ($this->redirector !== null) {
            ($this->redirector)($url);
            return;
        }
        redirect($url);
    }

    public function home(): void
    {
        $lastId = (int) ($_SESSION['pesanan_terakhir'] ?? 0);
        view('pelanggan/public/index', [
            'favorites'   => MenuModel::favorites(6),
            'tableNumber' => $this->tableNumber(),
            'lastOrder'   => $lastId > 0
                ? ['number' => PemesananModel::displayNumber($lastId), 'url' => $this->statusUrl($lastId)]
                : null,
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
     * POST: validasi, hitung ulang harga & stok di server, simpan pesanan, lalu:
     *   - Tunai: langsung redirect ke halaman pembayaran (tanpa memanggil Midtrans).
     *   - QRIS : panggil Core API Midtrans di luar transaksi DB; jika gagal -> tandai pesanan 'batal'
     *            dan pembayaran 'failure', tampilkan pesan error tanpa mengosongkan keranjang.
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
            if ($old['nama'] === '' || text_length($old['nama']) > 100) {
                $errors['nama'] = 'Nama wajib diisi (maksimal 100 karakter).';
            }
            if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || text_length($old['email']) > 150) {
                $errors['email'] = 'Masukkan alamat email yang valid.';
            }
            if (!isset(PemesananModel::METODE[$old['metode']])) {
                $errors['metode'] = 'Pilih metode pembayaran.';
            }
            if (text_length($old['catatan']) > PemesananModel::NOTE_MAX) {
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
                    $this->rememberOrder($id); // kartu "Pesanan Saya" di Beranda

                    if ($old['metode'] === 'tunai') {
                        $_SESSION['pesanan_baru'] = $id; // halaman Pembayaran mengosongkan keranjang satu kali
                        $targetUrl = url('pembayaran', ['id' => $id, 't' => tok($id)]);
                        if ($this->redirector !== null) {
                            ($this->redirector)($targetUrl);
                            return;
                        }
                        redirect($targetUrl);
                    }

                    // Metode QRIS: panggil Midtrans Core API di luar transaksi database
                    try {
                        $service = $this->pembayaranService ??= new PembayaranService();
                        $qris    = $service->buatQris([
                            'id'              => $id,
                            'nama_pelanggan'  => $old['nama'],
                            'email_pelanggan' => $old['email'],
                            'total_harga'     => $priced['total'],
                            'pajak'           => $priced['tax'],
                        ], $priced['lines']);

                        $qrisJson = (string) json_encode([
                            'qr_string' => $qris['qr_string'],
                            'qr_url'    => $qris['qr_url'] ?? '',
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                        PemesananModel::simpanQris($id, $qris['midtrans_order_id'], $qrisJson);

                        $_SESSION['pesanan_baru'] = $id;
                        $targetUrl = url('pembayaran', ['id' => $id, 't' => tok($id)]);
                        if ($this->redirector !== null) {
                            ($this->redirector)($targetUrl);
                            return;
                        }
                        redirect($targetUrl);
                    } catch (Throwable $e) {
                        try {
                            PemesananModel::tandaiGagalQris($id);
                        } catch (Throwable $dbErr) {
                            error_log(sprintf('[QuickBrew Checkout QRIS] Gagal menandai batal pemesanan_id=%d: %s', $id, $dbErr->getMessage()));
                        }
                        error_log(sprintf('[QuickBrew Checkout QRIS] pemesanan_id=%d gagal: %s', $id, $e->getMessage()));
                        $errors['form'] = 'Pembayaran QRIS sedang bermasalah, coba lagi atau pilih Tunai';
                    }
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

    /**
     * Halaman Pembayaran setelah Checkout: ?page=pembayaran&id=<id>&t=<tok(id)>
     * (token mencegah membuka pesanan orang lain). Tampilan berbeda untuk QRIS & Tunai.
     * Selama menunggu pembayaran QRIS, halaman ini menjalankan sinkron() ber-throttle sebagai
     * cadangan bila notifikasi webhook belum diterima. Tombol bawah membuka halaman Status Pesanan;
     * bila pesanan SUDAH dibayar/maju/batal saat halaman dibuka, langsung berpindah ke Status Pesanan
     * (pelunasan yang baru terjadi lewat sinkron() di request ini tetap menampilkan "Pembayaran Diterima").
     */
    public function pembayaran(): void
    {
        $order = $this->orderFromRequest();
        $id    = $order ? (int) $order['id'] : 0;

        if (!$order) {
            http_response_code(404);
        } else {
            $this->rememberOrder($id);
            if (PemesananModel::isPaid($order) || $order['status_pemesanan'] === 'batal') {
                unset($_SESSION['pesanan_baru']);
                $this->redirectTo($this->statusUrl($id));
                return;
            }
        }

        // Simulasi pembayaran demo: aktif HANYA bila ENABLE_PAYMENT_SIMULATION=true DAN MIDTRANS_PROD=false
        $simulasiAktif = $order !== null && $this->simulasiAktif();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['simulasi_bayar'])) {
            if (!$simulasiAktif) {
                // Tolak tanpa mengubah status pesanan apa pun
                http_response_code(403);
                error_log(sprintf('[SIMULASI DITOLAK] pemesanan_id=%d: simulasi pembayaran tidak aktif', $id));
            } elseif (
                (string) $order['metode_pembayaran'] === 'qris'
                && (string) $order['status_pemesanan'] === 'menunggu_pembayaran'
            ) {
                try {
                    $this->pembayaranService->simulasiQrisSandbox($id);
                } catch (Throwable $e) {
                    error_log(sprintf('[SIMULASI] pemesanan_id=%d gagal: %s', $id, $e->getMessage()));
                }

                $targetUrl = url('pembayaran', ['id' => $id, 't' => tok($id)]);
                if ($this->redirector !== null) {
                    ($this->redirector)($targetUrl);
                    return;
                }
                redirect($targetUrl);
            }
        }

        // Cadangan webhook: sinkronkan status QRIS maksimal sekali per 5 detik per sesi
        if (
            $order
            && (string) $order['metode_pembayaran'] === 'qris'
            && (string) $order['status_pemesanan'] === 'menunggu_pembayaran'
            && !empty($order['midtrans_order_id'])
        ) {
            $now    = time();
            $lastAt = (int) ($_SESSION['qris_sync_last_at'] ?? 0);
            if (($now - $lastAt) >= self::QRIS_SYNC_THROTTLE_SECONDS) {
                $_SESSION['qris_sync_last_at'] = $now;
                try {
                    $service    = $this->pembayaranService ??= new PembayaranService();
                    $syncStatus = $service->sinkron((string) $order['midtrans_order_id']);
                    if ($syncStatus !== null) {
                        $order = PemesananModel::find($id);
                    }
                } catch (Throwable $e) {
                    error_log(sprintf('[QuickBrew Sinkron QRIS] pemesanan_id=%d gagal: %s', $id, $e->getMessage()));
                }
            }
        }

        // Siapkan SVG inline & data URI dari qr_string; bila gagal, gunakan qr_url sebagai cadangan <img>
        $qrSvg          = null;
        $qrImgSrc       = null;
        $qrDownloadHref = null;
        $qrDownloadName = null;

        if ($order && (string) $order['metode_pembayaran'] === 'qris') {
            $rawQris  = trim((string) ($order['qris_data'] ?? ''));
            $qrString = '';
            $qrUrl    = '';

            if ($rawQris !== '') {
                $decoded = json_decode($rawQris, true);
                if (is_array($decoded)) {
                    $qrString = trim((string) ($decoded['qr_string'] ?? ''));
                    $qrUrl    = trim((string) ($decoded['qr_url'] ?? ''));
                } else {
                    $qrString = $rawQris;
                }
            }

            $nomorFile = ltrim((string) $order['number'], '#');

            if ($qrString !== '') {
                try {
                    $rawSvg  = QrSvg::svg($qrString);
                    $altText = e('Kode QRIS untuk membayar ' . format_rupiah($order['total_harga']));
                    $qrSvg   = preg_replace(
                        '/^<svg\b([^>]*)\baria-label="QR Code"/',
                        '<svg class="pay-qr"$1aria-label="' . $altText . '"',
                        $rawSvg,
                        1
                    );
                    $qrDownloadHref = QrSvg::dataUri($qrString);
                    $qrDownloadName = 'qris-quickbrew-' . $nomorFile . '.svg';
                } catch (Throwable $e) {
                    error_log(sprintf('[QuickBrew QR SVG] pemesanan_id=%d gagal merender SVG: %s', $id, $e->getMessage()));
                    $qrSvg = null;
                }
            }

            if ($qrSvg === null && $qrUrl !== '' && preg_match('#^https?://#i', $qrUrl)) {
                $qrImgSrc       = $qrUrl;
                $qrDownloadHref = $qrUrl;
                $qrDownloadName = 'qris-quickbrew-' . $nomorFile . '.png';
            }
        }

        $justOrdered = $order && ($_SESSION['pesanan_baru'] ?? null) === $id;
        unset($_SESSION['pesanan_baru']);

        view('pelanggan/public/pembayaran', [
            'order'          => $order,
            'justOrdered'    => $justOrdered,
            'statusUrl'      => $order ? $this->statusUrl($id) : url('menu'), // tombol bawah -> Status Pesanan
            'tableNumber'    => $this->tableNumber(),
            'paymentLabel'   => PemesananModel::PAYMENT_LABEL,
            'qrSvg'          => $qrSvg,
            'qrImgSrc'       => $qrImgSrc,
            'qrDownloadHref' => $qrDownloadHref,
            'qrDownloadName' => $qrDownloadName,
            'simulasiAktif'  => $simulasiAktif,
        ]);
    }

    /**
     * Halaman Status Pesanan: ?page=status&id=<id>&t=<tok(id)>
     * Stepper 4 langkah + ringkasan; tombol bawah sesuai keadaan (bayar / muat ulang / ke beranda).
     */
    public function status(): void
    {
        $order = $this->orderFromRequest();
        if (!$order) {
            http_response_code(404);
        } else {
            $this->rememberOrder((int) $order['id']);
        }
        $id = $order ? (int) $order['id'] : 0;

        view('pelanggan/public/status', [
            'order'        => $order,
            'view'         => $order ? PemesananModel::statusView($order) : null,
            'statusUrl'    => $order ? $this->statusUrl($id) : url('menu'),
            'paymentUrl'   => $order ? url('pembayaran', ['id' => $id, 't' => tok($id)]) : url('menu'),
            'estimate'     => PemesananModel::ESTIMATE_TEXT,
            'tableNumber'  => $this->tableNumber(),
        ]);
    }

    /**
     * Simulasi pembayaran demo diizinkan bila flag config aktif, bukan Production,
     * dan PembayaranService juga mengonfirmasi (pemeriksaan berlapis).
     */
    private function simulasiAktif(): bool
    {
        if (!ENABLE_PAYMENT_SIMULATION || MIDTRANS_PROD) {
            return false;
        }
        try {
            return ($this->pembayaranService ??= new PembayaranService())->simulasiAktif();
        } catch (Throwable $e) {
            error_log('[SIMULASI] Gagal memeriksa status simulasi: ' . $e->getMessage());
            return false;
        }
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
