<?php
require_once APP_ROOT . '/app/Helpers/QrSvg.php';
require_once APP_ROOT . '/app/Services/PembayaranService.php';
require_once APP_ROOT . '/app/Services/CheckoutService.php';

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

    /** Daftar id pesanan pelanggan di session, terbaru di depan (lihat CheckoutService::pesananSayaIds()). */
    private function myOrderIds(): array
    {
        return CheckoutService::pesananSayaIds();
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

    /**
     * Data kartu "Pesanan Saya" (Beranda & Menu) untuk semua pesanan di session, terbaru di depan.
     * Tiap item: id, number (#A0017), url (Status), paymentUrl, statusLabel, unpaid (belum dibayar & belum batal).
     * Bila database tidak dapat diakses: kartu tetap tampil dari id saja (tanpa label status, unpaid = false).
     */
    private function myOrders(): array
    {
        $orders = [];
        foreach ($this->myOrderIds() as $id) {
            $item = [
                'id'          => $id,
                'number'      => PemesananModel::displayNumber($id),
                'url'         => $this->statusUrl($id),
                'paymentUrl'  => url('pembayaran', ['id' => $id, 't' => tok($id)]),
                'statusLabel' => '',
                'unpaid'      => false,
            ];
            try {
                $order = PemesananModel::find($id);
                if (!$order) continue; // pesanan sudah tidak ada di database
                $status = (string) $order['status_pemesanan'];
                $item['statusLabel'] = PemesananModel::STATUS_LABEL[$status] ?? '';
                $item['unpaid']      = !PemesananModel::isPaid($order) && $status !== 'batal';
            } catch (Throwable $e) {
                error_log(sprintf('[QuickBrew Pesanan Saya] id=%d gagal dibaca: %s', $id, $e->getMessage()));
            }
            $orders[] = $item;
        }
        return $orders;
    }

    public function home(): void
    {
        view('pelanggan/public/index', [
            'favorites'   => MenuModel::favorites(6),
            'tableNumber' => $this->tableNumber(),
            'orders'      => $this->myOrders(),
        ]);
    }

    /**
     * Halaman Menu: hanya kerangka halaman. Daftar menu & kategori dimuat menu.js dari API
     * GET /api/menu.php (MenuController). Opsi kustomisasi belum ada di API -> tetap dari file.
     */
    public function menu(): void
    {
        // Ikon kategori yang tersedia (icon-cat-<slug>.svg); kategori tanpa ikon memakai ikon "all"
        $categoryIcons = array_map(
            static fn (string $file): string => substr(basename($file, '.svg'), strlen('icon-cat-')),
            glob(APP_ROOT . '/public/assets/images/icons/icon-cat-*.svg') ?: []
        );

        $orders = $this->myOrders();

        view('pelanggan/public/menu', [
            'menuApiUrl'    => BASE_URL . '/api/pelanggan/menu.php',
            'categoryIcons' => $categoryIcons,
            'custOptions'   => require APP_ROOT . '/app/Models/customization-options.php',
            'tableNumber'   => $this->tableNumber(),
            'orders'        => $orders, // kartu "Pesanan Saya" setelah keranjang dikosongkan
            // popup "pesanan belum dibayar" saat menambah menu
            'unpaidOrders'  => array_values(array_filter($orders, static fn (array $o): bool => $o['unpaid'])),
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
     * Pesanan dikirim checkout.js lewat API POST /api/pelanggan/pemesanan.php (PemesananController::store).
     * POST ke halaman ini tetap didukung sebagai cadangan (tanpa JavaScript); logikanya sama persis
     * karena keduanya memakai CheckoutService::proses():
     *   - berhasil (atau token form sudah pernah berhasil): redirect ke halaman pembayaran.
     *   - gagal: tampilkan form lagi beserta pesan error, keranjang tidak dikosongkan.
     */
    public function checkout(): void
    {
        $old    = ['nama' => '', 'email' => '', 'metode' => 'qris', 'catatan' => ''];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->meja) {
            $hasil = (new CheckoutService($this->pembayaranService))->proses($_POST, $this->meja);
            if ($hasil['id'] !== null) {
                $this->redirectTo(url('pembayaran', ['id' => $hasil['id'], 't' => tok($hasil['id'])]));
                return;
            }
            if ($hasil['status'] === 429) {
                http_response_code(429);
            }
            $old    = $hasil['old'];
            $errors = $hasil['errors'];
        }

        view('pelanggan/public/checkout', [
            'tableNumber'     => $this->tableNumber(),
            'hasTable'        => $this->meja !== null,
            'old'             => $old,
            'errors'          => $errors,
            'metodeList'      => PemesananModel::METODE,
            'taxRate'         => PemesananModel::TAX_RATE,
            'noteMax'         => PemesananModel::NOTE_MAX,
            'csrfToken'       => csrf_token(),
            'pemesananApiUrl' => BASE_URL . '/api/pelanggan/pemesanan.php',
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
            CheckoutService::ingatPesanan($id);
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
            CheckoutService::ingatPesanan((int) $order['id']);
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
     * Endpoint JSON polling status pesanan: ?page=status-json&id=<id>&t=<tok(id)>
     *   - Header: Content-Type: application/json; charset=utf-8 & Cache-Control: no-store
     *   - Pembatasan laju: 30 permintaan / 60 detik per (IP + ID pesanan) -> HTTP 429 JSON
     *   - Validasi id + tok(id) -> HTTP 404 JSON bila tidak valid / tidak ditemukan
     *   - Untuk QRIS menunggu_pembayaran, panggil sinkron() dengan throttle sesi 5 detik di dalam try/catch
     */
    public function statusJson(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }

        $id = max(0, (int) ($_GET['id'] ?? 0));
        $kunciRate = 'status_json:' . ip_klien() . ':' . $id;

        if (!batasi_laju($kunciRate, 30, 60)) {
            http_response_code(429);
            echo json_encode([
                'ok'    => false,
                'error' => 'Terlalu banyak permintaan. Silakan coba lagi sebentar.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        $order = $this->orderFromRequest();
        if (!$order) {
            http_response_code(404);
            echo json_encode([
                'ok'    => false,
                'error' => 'Pesanan tidak ditemukan.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        $id = (int) $order['id'];

        // Sinkronisasi cadangan Midtrans untuk QRIS menunggu_pembayaran (throttle sesi 5 detik)
        if (
            (string) ($order['metode_pembayaran'] ?? '') === 'qris'
            && (string) $order['status_pemesanan'] === 'menunggu_pembayaran'
            && !empty($order['midtrans_order_id'])
        ) {
            $lastSync = (int) ($_SESSION['qris_sync_last_at'] ?? 0);
            $now      = time();

            if (($now - $lastSync) >= self::QRIS_SYNC_THROTTLE_SECONDS) {
                $_SESSION['qris_sync_last_at'] = $now;
                try {
                    ($this->pembayaranService ??= new PembayaranService())->sinkron((string) $order['midtrans_order_id']);
                    $order = PemesananModel::find($id) ?? $order;
                } catch (Throwable $e) {
                    error_log(sprintf(
                        '[QuickBrew StatusJSON] Sinkronisasi QRIS gagal untuk pemesanan_id=%d: %s',
                        $id,
                        $e->getMessage()
                    ));
                }
            }
        }

        $statusView = PemesananModel::statusView($order);
        http_response_code(200);
        echo json_encode([
            'ok'   => true,
            'data' => [
                'id'          => $id,
                'nomor'       => (string) $order['number'],
                'status'      => (string) $order['status_pemesanan'],
                'label'       => (string) $statusView['title'],
                'lunas'       => PemesananModel::isPaid($order),
                'metode'      => (string) ($order['metode_pembayaran'] ?? ''),
                'kedaluwarsa' => (string) ($order['deadline'] ?? ''),
                'diperbarui'  => (string) ($order['diperbarui_pada'] ?? $order['waktu_pemesanan'] ?? ''),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
