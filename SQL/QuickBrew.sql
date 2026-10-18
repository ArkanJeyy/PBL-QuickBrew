-- Hapus tabel jika sudah ada agar bisa dieksekusi berulang tanpa error
DROP TABLE IF EXISTS pembayaran, detail_pemesanan, pemesanan, pengguna, meja, menu, kategori CASCADE;

CREATE TABLE kategori (
    id SERIAL PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL
);

CREATE TABLE menu (
    id SERIAL PRIMARY KEY,
    kategori_id INT NOT NULL,
    nama_menu VARCHAR(150) NOT NULL,
    harga INT NOT NULL,
    gambar VARCHAR(255),
    deskripsi TEXT,
    stok INT NOT NULL DEFAULT 0,
    FOREIGN KEY(kategori_id) REFERENCES kategori(id)
);

CREATE TABLE meja (
    id SERIAL PRIMARY KEY,
    no_meja VARCHAR(20) NOT NULL,
    kode_qr VARCHAR(64) NOT NULL UNIQUE
);

CREATE TABLE pengguna (
    id SERIAL PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'kasir'
);

CREATE TABLE pemesanan (
    id SERIAL PRIMARY KEY,
    meja_id INT NOT NULL,
    pengguna_id INT NULL,
    nama_pelanggan VARCHAR(100) NOT NULL,
    email_pelanggan VARCHAR(150) NOT NULL,
    total_harga INT NOT NULL,
    status_pemesanan VARCHAR(30) NOT NULL DEFAULT 'menunggu_pembayaran',
    waktu_pemesanan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(meja_id) REFERENCES meja(id),
    FOREIGN KEY(pengguna_id) REFERENCES pengguna(id)
);

CREATE TABLE detail_pemesanan (
    id SERIAL PRIMARY KEY,
    menu_id INT NOT NULL,
    pemesanan_id INT NOT NULL,
    harga INT NOT NULL,
    jumlah INT NOT NULL,
    subtotal INT NOT NULL,
    catatan VARCHAR(255),
    FOREIGN KEY(menu_id) REFERENCES menu(id),
    FOREIGN KEY(pemesanan_id) REFERENCES pemesanan(id) ON DELETE CASCADE
);

CREATE TABLE pembayaran (
    id SERIAL PRIMARY KEY,
    pemesanan_id INT NOT NULL,
    pengguna_id INT NULL,
    midtrans_order_id VARCHAR(64) UNIQUE,
    waktu_pembayaran TIMESTAMP NULL,
    status_pembayaran VARCHAR(30) NOT NULL DEFAULT 'pending',
    metode_pembayaran VARCHAR(50),
    total_bayar INT NOT NULL,
    FOREIGN KEY(pemesanan_id) REFERENCES pemesanan(id) ON DELETE CASCADE,
    FOREIGN KEY(pengguna_id) REFERENCES pengguna(id)
);

-- 1. Insert Data Kategori
INSERT INTO kategori (nama_kategori) VALUES
('Minuman'),
('Makanan'),
('Dessert');

-- 2. Insert Data Menu
INSERT INTO menu (kategori_id, nama_menu, harga, gambar, deskripsi, stok) VALUES
-- --- KATEGORI MINUMAN ---
(1, 'Americano', 20000, NULL, 'Espresso dengan air dan es, memiliki rasa kopi yang ringan dan menyegarkan.', 50),
(1, 'Espresso', 25000, NULL, 'Shot espresso dengan rasa kopi yang kuat, pekat, dan aroma yang khas.', 50),
(1, 'Cappuccino', 26000, NULL, 'Perpaduan espresso, susu steamed, dan foam lembut dengan rasa creamy.', 50),
(1, 'Caffè Latte', 28000, NULL, 'Espresso dengan susu steamed yang creamy dan lembut.', 50),
(1, 'Caramel Macchiato', 30000, NULL, 'Espresso dan susu dengan sentuhan manis serta aroma karamel.', 50),
(1, 'Mocha Latte', 29000, NULL, 'Perpaduan espresso, cokelat, dan susu dengan rasa manis yang seimbang.', 50),
(1, 'Hazelnut Cappuccino', 28000, NULL, 'Cappuccino creamy dengan tambahan aroma hazelnut yang harum dan manis.', 50),
(1, 'Matcha Latte', 27000, NULL, 'Matcha lembut yang dipadukan dengan susu creamy dan sedikit rasa earthy.', 50),
(1, 'Chocolate Latte', 26000, NULL, 'Cokelat creamy dan susu dengan rasa manis yang cocok untuk segala suasana.', 50),
(1, 'Thai Tea Original', 22000, NULL, 'Teh Thailand dengan susu yang creamy, manis, dan aroma teh yang khas.', 50),
(1, 'Lychee Tea', 21000, NULL, 'Teh segar dengan rasa leci yang manis dan aroma buah yang menyegarkan.', 50),
(1, 'Peach Tea', 21000, NULL, 'Teh dingin dengan perpaduan rasa peach yang manis dan menyegarkan.', 50),
(1, 'Strawberry Milk', 24000, NULL, 'Susu creamy dengan perpaduan rasa strawberry yang manis dan fruity.', 50),
(1, 'Cookies & Cream', 28000, NULL, 'Minuman creamy dengan rasa cookies dan crumble yang renyah.', 50),

-- --- KATEGORI MAKANAN ---
(2, 'Croissant Butter', 22000, NULL, 'Croissant renyah di luar dengan tekstur lembut dan buttery di dalam.', 50),
(2, 'Chicken Sandwich', 32000, NULL, 'Roti lembut berisi ayam gurih, sayuran segar, dan saus creamy.', 50),
(2, 'Beef Toast', 35000, NULL, 'Roti panggang dengan irisan daging sapi gurih dan saus khas.', 50),
(2, 'French Fries', 20000, NULL, 'Kentang goreng renyah dengan rasa gurih, cocok sebagai teman minuman.', 50),
(2, 'Chicken Croffle', 30000, NULL, 'Croffle renyah dengan potongan ayam gurih dan saus yang creamy.', 50),

-- --- KATEGORI DESSERT ---
(3, 'Choco Cake', 28000, NULL, 'Cake cokelat lembut dengan rasa cokelat yang rich dan manis.', 50),
(3, 'Red Velvet Cake', 30000, NULL, 'Cake red velvet lembut dengan cream cheese yang creamy dan sedikit tangy.', 50),
(3, 'Cheesecake', 32000, NULL, 'Cheesecake lembut dengan rasa keju creamy dan tekstur yang ringan.', 50),
(3, 'Tiramisu', 32000, NULL, 'Dessert lembut dengan perpaduan mascarpone, kopi, dan cocoa.', 50),
(3, 'Brownie', 25000, NULL, 'Brownie cokelat dengan tekstur fudgy dan rasa cokelat yang pekat.', 50),
(3, 'Waffle Berry', 28000, NULL, 'Waffle hangat dengan saus berry manis dan topping yang segar.', 50),
(3, 'Banana Pancake', 27000, NULL, 'Pancake lembut dengan potongan pisang dan sirup manis.', 50),
(3, 'Choco Croffle', 26000, NULL, 'Croffle renyah dengan saus cokelat yang manis dan creamy.', 50);

-- 3. Insert Data Meja & Pengguna
INSERT INTO meja(no_meja,kode_qr) VALUES('1','MEJA01'),('2','MEJA02'),('3','MEJA03');
INSERT INTO pengguna(nama,username,password,role) VALUES('Kasir 1','kasir','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','kasir');x