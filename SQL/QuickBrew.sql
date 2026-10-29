-- =====================================================================
--  QuickBrew - Skema Database (PostgreSQL 11+)
--  Sesuai ERD (7 tabel): kategori, menu, meja, pengguna,
--  pemesanan, detail_pemesanan, pembayaran
--
--  Alur status_pemesanan:
--    menunggu_pembayaran -> dibayar -> diproses -> siap -> selesai
--                        \-> batal
--  Alur status_pembayaran (mengikuti apply_payment di model/config.php):
--    pending -> paid | deny | cancel | expire | failure
-- =====================================================================

BEGIN;

-- Hapus objek lama agar script bisa dieksekusi berulang tanpa error
DROP VIEW     IF EXISTS v_rekap_harian, v_rekap_menu_harian;
DROP TABLE    IF EXISTS pembayaran, detail_pemesanan, pemesanan, pengguna, meja, menu, kategori CASCADE;
DROP FUNCTION IF EXISTS set_diperbarui_pada() CASCADE;

-- ---------------------------------------------------------------------
-- 1. kategori
-- ---------------------------------------------------------------------
CREATE TABLE kategori (
    id            SERIAL PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL UNIQUE
);

-- ---------------------------------------------------------------------
-- 2. menu
--    aktif = FALSE untuk menyembunyikan menu tanpa menghapusnya
--    (menu yang sudah pernah dipesan tidak bisa dihapus karena FK).
-- ---------------------------------------------------------------------
CREATE TABLE menu (
    id          SERIAL PRIMARY KEY,
    kategori_id INT          NOT NULL,
    nama_menu   VARCHAR(150) NOT NULL,
    harga       INT          NOT NULL CHECK (harga >= 0),
    gambar      VARCHAR(255),
    deskripsi   TEXT,
    stok        INT          NOT NULL DEFAULT 0 CHECK (stok >= 0),
    aktif       BOOLEAN      NOT NULL DEFAULT TRUE,
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON UPDATE CASCADE ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- 3. meja
--    kode_qr dipakai di URL QR (?meja=<kode_qr>). Untuk produksi,
--    sebaiknya gunakan string acak agar tidak mudah ditebak.
-- ---------------------------------------------------------------------
CREATE TABLE meja (
    id      SERIAL PRIMARY KEY,
    no_meja VARCHAR(20) NOT NULL UNIQUE,
    kode_qr VARCHAR(64) NOT NULL UNIQUE
);

-- ---------------------------------------------------------------------
-- 4. pengguna (staf: admin / kasir)
-- ---------------------------------------------------------------------
CREATE TABLE pengguna (
    id       SERIAL PRIMARY KEY,
    nama     VARCHAR(100) NOT NULL,
    username VARCHAR(50)  NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,              -- hash dari password_hash()
    role     VARCHAR(20)  NOT NULL DEFAULT 'kasir'
             CHECK (role IN ('admin', 'kasir'))
);

-- ---------------------------------------------------------------------
-- 5. pemesanan
--    pengguna_id = kasir yang memproses pesanan (NULL sebelum diproses)
--    diperbarui_pada otomatis di-update oleh trigger, dipakai untuk
--    polling real-time status di halaman pelanggan & dashboard kasir.
-- ---------------------------------------------------------------------
CREATE TABLE pemesanan (
    id               SERIAL PRIMARY KEY,
    meja_id          INT          NOT NULL,
    pengguna_id      INT          NULL,
    nama_pelanggan   VARCHAR(100) NOT NULL,
    email_pelanggan  VARCHAR(150) NOT NULL,
    total_harga      INT          NOT NULL CHECK (total_harga >= 0),
    status_pemesanan VARCHAR(30)  NOT NULL DEFAULT 'menunggu_pembayaran'
                     CHECK (status_pemesanan IN (
                         'menunggu_pembayaran', 'dibayar', 'diproses',
                         'siap', 'selesai', 'batal'
                     )),
    waktu_pemesanan  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meja_id)     REFERENCES meja(id)     ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (pengguna_id) REFERENCES pengguna(id) ON UPDATE CASCADE ON DELETE SET NULL
);

-- ---------------------------------------------------------------------
-- 6. detail_pemesanan
--    harga = snapshot harga menu saat dipesan (tidak ikut berubah
--    walau harga menu diubah kemudian).
--    catatan = kustomisasi pelanggan (mis. "Less sugar, Ice, Extra shot").
-- ---------------------------------------------------------------------
CREATE TABLE detail_pemesanan (
    id           SERIAL PRIMARY KEY,
    menu_id      INT NOT NULL,
    pemesanan_id INT NOT NULL,
    harga        INT NOT NULL CHECK (harga >= 0),
    jumlah       INT NOT NULL CHECK (jumlah > 0),
    subtotal     INT NOT NULL,
    catatan      VARCHAR(255),
    CHECK (subtotal = harga * jumlah),
    FOREIGN KEY (menu_id)      REFERENCES menu(id)      ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (pemesanan_id) REFERENCES pemesanan(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- 7. pembayaran (relasi 1:1 dengan pemesanan sesuai ERD)
--    snap_token disimpan agar pelanggan bisa membuka ulang popup
--    pembayaran Midtrans tanpa membuat transaksi baru.
-- ---------------------------------------------------------------------
CREATE TABLE pembayaran (
    id                SERIAL PRIMARY KEY,
    pemesanan_id      INT          NOT NULL UNIQUE,
    pengguna_id       INT          NULL,
    midtrans_order_id VARCHAR(64)  UNIQUE,
    snap_token        VARCHAR(255),
    waktu_pembayaran  TIMESTAMP    NULL,
    status_pembayaran VARCHAR(30)  NOT NULL DEFAULT 'pending'
                      CHECK (status_pembayaran IN (
                          'pending', 'paid', 'deny', 'cancel', 'expire', 'failure'
                      )),
    metode_pembayaran VARCHAR(50),
    total_bayar       INT          NOT NULL CHECK (total_bayar >= 0),
    -- Pembayaran yang lunas wajib punya waktu pembayaran
    CHECK (status_pembayaran <> 'paid' OR waktu_pembayaran IS NOT NULL),
    FOREIGN KEY (pemesanan_id) REFERENCES pemesanan(id) ON DELETE CASCADE,
    FOREIGN KEY (pengguna_id)  REFERENCES pengguna(id)  ON UPDATE CASCADE ON DELETE SET NULL
);

-- ---------------------------------------------------------------------
-- Index untuk query yang sering dipakai
-- ---------------------------------------------------------------------
CREATE INDEX idx_menu_kategori          ON menu (kategori_id);
CREATE INDEX idx_pemesanan_status_waktu ON pemesanan (status_pemesanan, waktu_pemesanan); -- dashboard kasir
CREATE INDEX idx_pemesanan_meja         ON pemesanan (meja_id);
CREATE INDEX idx_detail_pemesanan       ON detail_pemesanan (pemesanan_id);
CREATE INDEX idx_detail_menu            ON detail_pemesanan (menu_id);
CREATE INDEX idx_pembayaran_status      ON pembayaran (status_pembayaran, waktu_pembayaran); -- rekap harian

-- ---------------------------------------------------------------------
-- Trigger: isi otomatis pemesanan.diperbarui_pada setiap ada UPDATE
-- ---------------------------------------------------------------------
CREATE FUNCTION set_diperbarui_pada() RETURNS TRIGGER AS $$
BEGIN
    NEW.diperbarui_pada := CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_pemesanan_diperbarui
    BEFORE UPDATE ON pemesanan
    FOR EACH ROW EXECUTE FUNCTION set_diperbarui_pada();

-- ---------------------------------------------------------------------
-- View rekap penjualan (hanya pembayaran yang sudah 'paid')
-- ---------------------------------------------------------------------
-- Rekap total per hari
CREATE VIEW v_rekap_harian AS
SELECT
    p.waktu_pembayaran::date AS tanggal,
    COUNT(*)                 AS jumlah_transaksi,
    SUM(p.total_bayar)       AS total_pendapatan
FROM pembayaran p
WHERE p.status_pembayaran = 'paid'
GROUP BY p.waktu_pembayaran::date;

-- Rekap per menu per hari (menu terlaris)
CREATE VIEW v_rekap_menu_harian AS
SELECT
    p.waktu_pembayaran::date AS tanggal,
    m.id                     AS menu_id,
    m.nama_menu,
    SUM(d.jumlah)            AS total_terjual,
    SUM(d.subtotal)          AS total_pendapatan
FROM pembayaran p
JOIN detail_pemesanan d ON d.pemesanan_id = p.pemesanan_id
JOIN menu m             ON m.id = d.menu_id
WHERE p.status_pembayaran = 'paid'
GROUP BY p.waktu_pembayaran::date, m.id, m.nama_menu;

-- =====================================================================
--  DATA AWAL (SEED)
-- =====================================================================

-- 1. Insert Data Kategori
INSERT INTO kategori (nama_kategori) VALUES
('Minuman'),
('Makanan'),
('Dessert');

-- 2. Insert Data Menu
--    gambar = nama file di public/assets/images/menu/ (NULL -> otomatis memakai logo)
INSERT INTO menu (kategori_id, nama_menu, harga, gambar, deskripsi, stok) VALUES
-- --- KATEGORI MINUMAN ---
(1, 'Americano', 20000, 'americano.png', 'Espresso dengan air dan es, memiliki rasa kopi yang ringan dan menyegarkan.', 50),
(1, 'Hazelnut Latte', 26000, 'hazelnut-latte.png', 'Latte creamy dengan tambahan sirup hazelnut yang harum dan manis.', 50),
(1, 'Matcha Latte', 24000, 'matcha-latte.png', 'Matcha lembut yang dipadukan dengan susu creamy dan sedikit rasa earthy.', 50),
(1, 'Thai Tea Original', 19000, 'thai-tea-original.png', 'Teh Thailand dengan susu yang creamy, manis, dan aroma teh yang khas.', 50),
(1, 'Strawberry Milk', 26000, 'strawberry-milk.png', 'Susu creamy dengan perpaduan rasa strawberry yang manis dan fruity.', 50),
(1, 'Mocha Latte', 26000, 'mocha-latte.png', 'Perpaduan espresso, cokelat, dan susu dengan rasa manis yang seimbang.', 50),
(1, 'Choco Latte', 24000, 'choco-latte.png', 'Cokelat creamy dan susu dengan rasa manis yang cocok untuk segala suasana.', 50),
(1, 'Caramel Frappe', 24000, 'caramel-frappe.png', 'Minuman blended dingin perpaduan kopi, susu, dan saus karamel yang manis.', 50),
(1, 'Caramel Macchiato', 20000, 'caramel-macchiato.png', 'Espresso dan susu dengan sentuhan manis serta aroma karamel.', 50),
(1, 'Caffè Latte', 26000, 'caffe-latte.png', 'Espresso dengan susu steamed yang creamy dan lembut.', 50),
(1, 'Cappuccino', 24000, 'cappuccino.png', 'Perpaduan espresso, susu steamed, dan foam lembut dengan rasa creamy.', 50),
(1, 'Peach Tea', 19000, 'peach-tea.png', 'Teh dingin dengan perpaduan rasa peach yang manis dan menyegarkan.', 50),
(1, 'Lychee Tea', 20000, 'lychee-tea.png', 'Teh segar dengan rasa leci yang manis dan aroma buah yang menyegarkan.', 50),

-- --- KATEGORI MAKANAN ---
(2, 'French Fries', 26000, 'french-fries.png', 'Kentang goreng renyah dengan rasa gurih, cocok sebagai teman minuman.', 50),
(2, 'Spaghetti Carbonara', 24000, 'spaghetti-carbonara.png', 'Spaghetti al dente dengan saus krim gurih dan taburan keju.', 50),
(2, 'Chicken Pasta', 19000, 'chicken-pasta.png', 'Pasta dengan potongan ayam panggang saus gurih kaya rasa.', 50),
(2, 'Chicken Croffle', 20000, 'chicken-croffle.png', 'Croffle renyah dengan potongan ayam gurih dan saus yang creamy.', 50),
(2, 'Beef Toast', 26000, 'beef-toast.png', 'Roti panggang dengan irisan daging sapi gurih dan saus khas.', 50),
(2, 'Chicken Sandwich', 19000, 'chicken-sandwich.png', 'Roti lembut berisi ayam gurih, sayuran segar, dan saus creamy.', 50),
(2, 'Croissant Butter', 24000, 'croissant-butter.png', 'Croissant renyah di luar dengan tekstur lembut dan buttery di dalam.', 50),

-- --- KATEGORI DESSERT ---
(3, 'Waffle Berry', 24000, 'waffle-berry.png', 'Waffle hangat dengan saus berry manis dan topping yang segar.', 50),
(3, 'Tiramisu', 19000, 'tiramisu.png', 'Dessert lembut dengan perpaduan mascarpone, kopi, dan cocoa.', 50),
(3, 'Brownies', 20000, 'brownies.png', 'Brownie cokelat dengan tekstur fudgy dan rasa cokelat yang pekat.', 50),
(3, 'Banana Pancake', 26000, 'banana-pancake.png', 'Pancake lembut dengan potongan pisang dan sirup manis.', 50),
(3, 'Cheesecake', 19000, 'cheesecake.png', 'Cheesecake lembut dengan rasa keju creamy dan tekstur yang ringan.', 50),
(3, 'Red Velvet Cake', 20000, 'red-velvet-cake.png', 'Cake red velvet lembut dengan cream cheese yang creamy dan sedikit tangy.', 50),
(3, 'Choco Waffle', 26000, 'choco-waffle.png', 'Waffle hangat dengan saus cokelat dan topping krim yang manis.', 50),
(3, 'Choco Cake', 24000, 'choco-cake.png', 'Cake cokelat lembut dengan rasa cokelat yang rich dan manis.', 50);

-- 3. Insert Data Meja & Pengguna
INSERT INTO meja (no_meja, kode_qr) VALUES
('1', 'MEJA01'), ('2', 'MEJA02'), ('3', 'MEJA03'), ('4', 'MEJA04'), ('5', 'MEJA05');

-- Password default kedua akun: "password" (WAJIB diganti sebelum production)
INSERT INTO pengguna (nama, username, password, role) VALUES
('Admin',   'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Kasir 1', 'kasir', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'kasir');

COMMIT;