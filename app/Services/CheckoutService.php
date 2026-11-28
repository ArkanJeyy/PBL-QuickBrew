<?php
require_once APP_ROOT . '/app/Services/PembayaranService.php';

/**
 * Proses checkout pelanggan, dipakai bersama oleh:
 *   - API  POST /api/pelanggan/pemesanan.php  (PemesananController::store, dipanggil checkout.js lewat fetch)
 *   - Form POST ?page=checkout      (PelangganController::checkout, cadangan tanpa JavaScript & dipakai tests/)
 *
 * Urutan pemeriksaan: idempotensi token form -> pembatasan laju per IP -> CSRF & validasi field
 * -> pesanan sebelumnya harus sudah dibayar -> hitung ulang harga & stok di server -> simpan pesanan
 * -> QRIS: buat transaksi Midtrans di luar transaksi DB (gagal -> pesanan ditandai batal, token TIDAK dirotasi
 *    sehingga pelanggan bisa mencoba lagi).
 */
final class CheckoutService
{
    /** Jumlah maksimal pesanan yang diingat session (kartu "Pesanan Saya"). */
    public const MY_ORDERS_MAX = 5;

    /** Jumlah maksimal token form yang diingat untuk idempotensi. */
    private const IDEMPOTENSI_MAX = 20;

    private ?PembayaranService $pembayaranService;

    public function __construct(?PembayaranService $pembayaranService = null)
    {
        $this->pembayaranService = $pembayaranService;
    }

    /**
     * Daftar id pesanan pelanggan di session, terbaru di depan. Sesi lama yang baru punya
     * 'pesanan_terakhir' (sebelum daftar ini ada) ikut dimasukkan agar pesanannya tidak hilang.
     * @return int[]
     */
    public static function pesananSayaIds(): array
    {
        $ids  = array_map('intval', is_array($_SESSION['pesanan_saya'] ?? null) ? $_SESSION['pesanan_saya'] : []);
        $last = (int) ($_SESSION['pesanan_terakhir'] ?? 0);
        if ($last > 0 && !in_array($last, $ids, true)) array_unshift($ids, $last);
        return array_slice(array_values(array_filter($ids, static fn (int $id): bool => $id > 0)), 0, self::MY_ORDERS_MAX);
    }

    /**
     * Ingat pesanan di session agar bisa dibuka lagi dari kartu "Pesanan Saya" (Beranda & Menu).
     * Pesanan sebelumnya TIDAK tertimpa: disimpan sebagai daftar (maks. MY_ORDERS_MAX, terbaru di depan).
     */
    public static function ingatPesanan(int $id): void
    {
        $ids = array_values(array_diff(self::pesananSayaIds(), [$id]));
        array_unshift($ids, $id);
        $_SESSION['pesanan_saya']     = array_slice($ids, 0, self::MY_ORDERS_MAX);
        $_SESSION['pesanan_terakhir'] = $id;
    }

    /**
     * Nomor tampilan (#A0017) pesanan di session yang belum dibayar & belum batal.
     * Pesanan yang gagal dibaca dari database dianggap tidak menghalangi (sama seperti kartu "Pesanan Saya").
     * @return string[]
     */
    private static function pesananBelumDibayar(): array
    {
        $numbers = [];
        foreach (self::pesananSayaIds() as $id) {
            try {
                $order = PemesananModel::find($id);
            } catch (Throwable $e) {
                error_log(sprintf('[QuickBrew Checkout] pesanan id=%d gagal dibaca: %s', $id, $e->getMessage()));
                continue;
            }
            if ($order && !PemesananModel::isPaid($order) && (string) $order['status_pemesanan'] !== 'batal') {
                $numbers[] = PemesananModel::displayNumber($id);
            }
        }
        return $numbers;
    }

    /**
     * Proses satu permintaan checkout.
     *
     * @param array      $input nama, email, metode, catatan, csrf_token, items (array atau JSON string)
     * @param array|null $meja  Baris meja tujuan (dari session hasil scan QR); null -> ditolak
     * @return array{status:int, id:?int, errors:array<string,string>, old:array<string,string>}
     *   status: 201 dibuat, 200 token form sudah pernah berhasil (id pesanan lama), 400 belum pilih meja,
     *           403 token CSRF tidak valid, 409 masih ada pesanan belum dibayar, 422 input/keranjang tidak valid,
     *           429 terlalu banyak percobaan, 502 Midtrans QRIS gagal.
     */
    public function proses(array $input, ?array $meja): array
    {
        $old = [
            'nama'    => trim(self::teks($input['nama'] ?? '')),
            'email'   => trim(self::teks($input['email'] ?? '')),
            'metode'  => self::teks($input['metode'] ?? ''),
            'catatan' => trim(self::teks($input['catatan'] ?? '')),
        ];
        $hasil = static fn (int $status, array $errors = [], ?int $id = null): array
            => ['status' => $status, 'id' => $id, 'errors' => $errors, 'old' => $old];

        if ($meja === null) {
            return $hasil(400, ['form' => 'Scan QR meja terlebih dahulu sebelum membuat pesanan.']);
        }

        $tokenForm = is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : '';

        // Idempotensi: jika token form sudah pernah berhasil membuat pesanan, kembalikan pesanan tersebut
        if ($tokenForm !== '' && isset($_SESSION['checkout_idempotensi'][$tokenForm])) {
            $idLama = (int) $_SESSION['checkout_idempotensi'][$tokenForm];
            if ($idLama > 0) {
                return $hasil(200, [], $idLama);
            }
        }

        // Pembatasan laju: default 10 percobaan per 10 menit (600 detik) per IP klien
        $maksCheckout = max(1, (int) env('CHECKOUT_RATE_MAX', '10'));
        if (!batasi_laju('checkout:' . ip_klien(), $maksCheckout, 600)) {
            return $hasil(429, ['form' => 'Terlalu banyak percobaan pemesanan. Silakan tunggu beberapa menit lalu coba lagi.']);
        }

        $errors = [];
        $csrfValid = csrf_check($tokenForm);
        if (!$csrfValid) {
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
        if ($errors) {
            return $hasil($csrfValid ? 422 : 403, $errors);
        }

        // Aturan: pesanan sebelumnya di sesi ini harus dibayar dulu sebelum membuat pesanan baru
        $unpaid = self::pesananBelumDibayar();
        if ($unpaid) {
            return $hasil(409, ['form' => 'Pesanan ' . implode(', ', $unpaid)
                . ' belum dibayar. Selesaikan pembayarannya terlebih dahulu sebelum membuat pesanan baru.']);
        }

        $rawItems = $input['items'] ?? null;
        if (is_string($rawItems)) {
            $rawItems = json_decode($rawItems, true);
        }
        $priced = PemesananModel::price($rawItems);
        if ($priced['error'] !== null) {
            return $hasil(422, ['form' => $priced['error']]);
        }

        $id = PemesananModel::create([
            'meja_id' => (int) $meja['id'],
            'nama'    => $old['nama'],
            'email'   => $old['email'],
            'catatan' => $old['catatan'] !== '' ? $old['catatan'] : null,
            'metode'  => $old['metode'],
            'total'   => $priced['total'],
            'lines'   => $priced['lines'],
        ]);
        self::ingatPesanan($id); // kartu "Pesanan Saya" di Beranda

        if ($old['metode'] === 'qris') {
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
            } catch (Throwable $e) {
                try {
                    PemesananModel::tandaiGagalQris($id);
                } catch (Throwable $dbErr) {
                    error_log(sprintf('[QuickBrew Checkout QRIS] Gagal menandai batal pemesanan_id=%d: %s', $id, $dbErr->getMessage()));
                }
                error_log(sprintf('[QuickBrew Checkout QRIS] pemesanan_id=%d gagal: %s', $id, $e->getMessage()));
                return $hasil(502, ['form' => 'Pembayaran QRIS sedang bermasalah, coba lagi atau pilih Tunai']);
            }
        }

        $this->catatBerhasil($tokenForm, $id);
        return $hasil(201, [], $id);
    }

    /** Catat token idempotensi (maks. IDEMPOTENSI_MAX entri terbaru) dan rotasi csrf_token. */
    private function catatBerhasil(string $tokenForm, int $id): void
    {
        $_SESSION['pesanan_baru'] = $id; // halaman Pembayaran mengosongkan keranjang satu kali
        if ($tokenForm !== '') {
            if (!isset($_SESSION['checkout_idempotensi']) || !is_array($_SESSION['checkout_idempotensi'])) {
                $_SESSION['checkout_idempotensi'] = [];
            }
            $_SESSION['checkout_idempotensi'][$tokenForm] = $id;
            if (count($_SESSION['checkout_idempotensi']) > self::IDEMPOTENSI_MAX) {
                $_SESSION['checkout_idempotensi'] = array_slice($_SESSION['checkout_idempotensi'], -self::IDEMPOTENSI_MAX, self::IDEMPOTENSI_MAX, true);
            }
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /** Nilai skalar -> string; array/objek (input tidak wajar) -> string kosong. */
    private static function teks($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
