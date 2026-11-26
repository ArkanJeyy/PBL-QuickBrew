<?php
/**
 * Controller untuk Halaman & Operasional Kasir
 */
class KasirController
{
    /** @var callable|null */
    private $redirector;

    public function __construct(?callable $redirector = null)
    {
        $this->redirector = $redirector;
    }

    private function redirectTo(string $url): void
    {
        if ($this->redirector !== null) {
            ($this->redirector)($url);
            return;
        }
        redirect($url);
    }

    private const ALLOWED_FILTERS     = ['semua', 'menunggu', 'diproses', 'siap', 'selesai', 'batal'];
    private const PER_HALAMAN_DEFAULT = 10;
    private const PER_HALAMAN_OPTIONS = [5, 10, 15, 20, 25, 50, 100];

    private function normalisasiFilter(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, self::ALLOWED_FILTERS, true) ? $status : 'semua';
    }

    private function normalisasiPerHalaman(int $nilai): int
    {
        return ($nilai >= 5 && $nilai <= 100 && $nilai % 5 === 0)
            ? $nilai
            : self::PER_HALAMAN_DEFAULT;
    }

    private function buildKasirReturnUrl(
        string $statusFilter,
        string $cari,
        int $halaman,
        int $perHalaman = self::PER_HALAMAN_DEFAULT
    ): string {
        $query = [];
        if ($statusFilter !== 'semua') {
            $query['status'] = $statusFilter;
        }
        if ($cari !== '') {
            $query['q'] = $cari;
        }
        if ($perHalaman !== self::PER_HALAMAN_DEFAULT) {
            $query['per'] = $perHalaman;
        }
        if ($halaman > 1) {
            $query['hal'] = $halaman;
        }
        return url('kasir', $query) . '#antreanSection';
    }

    /**
     * Menampilkan Dashboard / Beranda Kasir serta menangani aksi status & pembayaran (POST -> Redirect -> GET).
     */
    public function index(): void
    {
        // Guard autentikasi: wajib login dengan role 'kasir' atau 'admin'
        $staf = wajib_staf(['kasir', 'admin']);
        if (empty($staf)) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $returnStatus = $this->normalisasiFilter((string) ($_POST['return_status'] ?? 'semua'));
            $returnCari   = text_cut(trim((string) ($_POST['return_q'] ?? '')), 100);
            $returnHal    = max(1, (int) ($_POST['return_hal'] ?? 1));
            $returnPer    = $this->normalisasiPerHalaman((int) ($_POST['return_per'] ?? self::PER_HALAMAN_DEFAULT));
            $returnUrl    = $this->buildKasirReturnUrl($returnStatus, $returnCari, $returnHal, $returnPer);

            $tokenForm = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
            if (!csrf_check($tokenForm)) {
                $_SESSION['kasir_flash'] = [
                    'tipe'  => 'galat',
                    'pesan' => 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.',
                ];
                $this->redirectTo($returnUrl);
                return;
            }

            $pesananId = (int) ($_POST['pesanan_id'] ?? 0);
            $aksi      = trim((string) ($_POST['aksi'] ?? ''));
            $stafId    = (int) ($staf['id'] ?? 0);

            try {
                $_SESSION['kasir_flash'] = $this->prosesAksiDashboard($pesananId, $aksi, $stafId);
            } catch (Throwable $e) {
                error_log(sprintf('[QuickBrew KasirDashboard] Aksi=%s pesanan_id=%d gagal: %s', $aksi, $pesananId, $e->getMessage()));
                $_SESSION['kasir_flash'] = [
                    'tipe'  => 'galat',
                    'pesan' => 'Terjadi kesalahan saat memproses pesanan. Silakan coba lagi.',
                ];
            }

            $this->redirectTo($returnUrl);
            return;
        }

        $flash = $_SESSION['kasir_flash'] ?? null;
        unset($_SESSION['kasir_flash']);

        $statusFilter = $this->normalisasiFilter((string) ($_GET['status'] ?? 'semua'));
        $cari         = text_cut(trim((string) ($_GET['q'] ?? '')), 100);
        $halaman      = max(1, (int) ($_GET['hal'] ?? 1));
        $perHalaman   = $this->normalisasiPerHalaman((int) ($_GET['per'] ?? self::PER_HALAMAN_DEFAULT));

        $ringkas   = PemesananModel::hitungRingkas();
        $analitik  = PemesananModel::analitikDashboard();
        $paginasi  = PemesananModel::daftarAntreanPaginasi($statusFilter, $cari, $halaman, $perHalaman);
        $antrean   = $paginasi['items'];

        view('kasir/dashboard', [
            'user'              => $staf,
            'flash'             => is_array($flash) ? $flash : null,
            'ringkas'           => $ringkas,
            'analitik'          => $analitik,
            'paginasi'          => $paginasi,
            'antrean'           => $antrean,
            'statusFilter'      => $statusFilter,
            'cari'              => $cari,
            'perHalaman'        => $perHalaman,
            'perHalamanOptions' => self::PER_HALAMAN_OPTIONS,
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
     * Eksekusi aksi status/pembayaran dari tombol di Dashboard Kasir.
     *
     * @return array{tipe:string, pesan:string}
     */
    private function prosesAksiDashboard(int $pesananId, string $aksi, int $stafId): array
    {
        if ($pesananId <= 0) {
            return ['tipe' => 'galat', 'pesan' => 'ID pesanan tidak valid.'];
        }

        $nomor = PemesananModel::displayNumber($pesananId);

        switch ($aksi) {
            case 'bayar_tunai':
                $res = PemesananModel::bayarTunai($pesananId, $stafId);
                return [
                    'tipe'  => $res['ok'] ? 'sukses' : 'galat',
                    'pesan' => $res['pesan'],
                ];

            case 'cek_qris':
                $res = PemesananModel::cekQris($pesananId);
                $tipe = !$res['ok'] ? 'galat' : ($res['status'] === 'lunas' ? 'sukses' : 'info');
                return [
                    'tipe'  => $tipe,
                    'pesan' => $nomor . ': ' . $res['pesan'],
                ];

            case 'siap':
            case 'selesai':
            case 'batal':
                $hasil = PemesananModel::ubahStatus($pesananId, $aksi, $stafId > 0 ? $stafId : null);
                if ($hasil === 'ok') {
                    $label = PemesananModel::STATUS_LABEL[$aksi] ?? $aksi;
                    return [
                        'tipe'  => 'sukses',
                        'pesan' => "Pesanan {$nomor} berhasil diperbarui menjadi '{$label}'.",
                    ];
                }
                if ($hasil === 'tidak_ditemukan') {
                    return ['tipe' => 'galat', 'pesan' => "Pesanan {$nomor} tidak ditemukan."];
                }
                return ['tipe' => 'galat', 'pesan' => "Perubahan status untuk pesanan {$nomor} tidak diizinkan."];

            default:
                return ['tipe' => 'galat', 'pesan' => 'Aksi tidak dikenal.'];
        }
    }

    /**
     * Menampilkan Halaman Detail & Pembayaran Pesanan Kasir
     */
    public function pesananDetail(): void
    {
        // Guard autentikasi: wajib login dengan role 'kasir' atau 'admin'
        $staf = wajib_staf(['kasir', 'admin']);
        if (empty($staf)) {
            return;
        }

        $orderId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['id'] ?? 'A1234')));
        if ($orderId === '') {
            $orderId = 'A1234';
        }

        $orders = [
            'A1232' => [
                'id'             => 'A1232',
                'meja'           => '5',
                'pelanggan'      => 'Rian Pratama',
                'telepon'        => '0812-3456-7890',
                'metode'         => 'QRIS',
                'status_bayar'   => 'Lunas',
                'status_pesanan' => 'Diproses',
                'subtotal'       => 'Rp 77.000',
                'pajak'          => 'Rp 7.700',
                'total'          => 'Rp 84.700',
                'catatan'        => 'Less sugar untuk Matcha Latte',
                'items'          => [
                    [
                        'nama'    => '1 × Americano (Dingin)',
                        'harga'   => 'Rp 22.000',
                        'gambar'  => 'americano.png',
                        'varian'  => ['Base Rp 22.000 | R (+Rp 0)', 'Normal Es | Tanpa Gula']
                    ],
                    [
                        'nama'    => '1 × Matcha Latte (Dingin)',
                        'harga'   => 'Rp 34.700',
                        'gambar'  => 'matcha-latte.png',
                        'varian'  => ['Size L (+Rp 6.000)', 'Sedikit Gula']
                    ],
                    [
                        'nama'    => '1 × Croissant Butter',
                        'harga'   => 'Rp 28.000',
                        'gambar'  => 'croissant-butter.png',
                        'varian'  => ['Hangatkan']
                    ]
                ]
            ],
            'A1234' => [
                'id'             => 'A1234',
                'meja'           => '5',
                'pelanggan'      => 'Arkan',
                'telepon'        => '0857-8765-6748',
                'metode'         => 'Tunai',
                'status_bayar'   => 'Belum Bayar',
                'status_pesanan' => 'Menunggu Pembayaran',
                'subtotal'       => 'Rp 24.000',
                'pajak'          => 'Rp 2.400',
                'total'          => 'Rp 26.400',
                'catatan'        => 'Tidak ada',
                'items'          => [
                    [
                        'nama'    => '1 × Americano (Dingin)',
                        'harga'   => 'Rp 24.000',
                        'gambar'  => 'americano.png',
                        'varian'  => [
                            'Base Rp 18.000 | R (+Rp 0)',
                            'Normal Es | Sedikit Gula',
                            'Tambahan / Topping: Ekstra Espresso (+Rp 4.000)'
                        ]
                    ]
                ]
            ],
            'A1235' => [
                'id'             => 'A1235',
                'meja'           => '3',
                'pelanggan'      => 'Siti Sarah',
                'telepon'        => '0821-9876-5432',
                'metode'         => 'QRIS',
                'status_bayar'   => 'Lunas',
                'status_pesanan' => 'Selesai',
                'subtotal'       => 'Rp 19.000',
                'pajak'          => 'Rp 1.900',
                'total'          => 'Rp 20.900',
                'catatan'        => 'Extra caramel drizzle',
                'items'          => [
                    [
                        'nama'    => '1 × Caramel Frappe',
                        'harga'   => 'Rp 20.900',
                        'gambar'  => 'caramel-frappe.png',
                        'varian'  => ['Normal Ice | Normal Sweet']
                    ]
                ]
            ],
            'A1238' => [
                'id'             => 'A1238',
                'meja'           => '7',
                'pelanggan'      => 'Budi Santoso',
                'telepon'        => '0813-1122-3344',
                'metode'         => 'Tunai',
                'status_bayar'   => 'Lunas',
                'status_pesanan' => 'Diproses',
                'subtotal'       => 'Rp 24.000',
                'pajak'          => 'Rp 2.400',
                'total'          => 'Rp 26.400',
                'catatan'        => 'Sajikan segera',
                'items'          => [
                    [
                        'nama'    => '1 × Waffle Berry',
                        'harga'   => 'Rp 26.400',
                        'gambar'  => 'waffle-berry.png',
                        'varian'  => ['Extra berry syrup']
                    ]
                ]
            ],
        ];

        $detail = $orders[$orderId] ?? $orders['A1234'];
        $detail['id'] = $orderId;

        view('kasir/pesanan-detail', [
            'user'  => $staf,
            'order' => $detail,
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

    /**
     * Menampilkan Halaman Stok Menu (hanya lihat).
     */
    public function stok(): void
    {
        // Guard autentikasi: wajib login dengan role 'kasir' atau 'admin'
        $staf = wajib_staf(['kasir', 'admin']);
        if (empty($staf)) {
            return;
        }

        // Samakan nama key dengan yang dibaca view stok.php
        $stok = array_map(static fn (array $m): array => [
            'nama_menu' => $m['name'],
            'harga'     => $m['price'],
            'stok'      => $m['stock'],
            'gambar'    => $m['image'],
        ], MenuModel::allForApi());

        view('kasir/stok', [
            'user' => $staf,
            'stok' => $stok,
        ]);
    }
}
