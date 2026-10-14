-- ========================================================
-- SKEMA DATABASE RESTORAN QUICKBREW (PostgreSQL)
-- ========================================================

-- 1. Tabel Kategori (Tabel Master)
CREATE TABLE kategori (
id SERIAL PRIMARY KEY,
nama_kategori VARCHAR(20) NOT NULL
);

-- 2. Tabel Meja (Tabel Master)
CREATE TABLE meja (
id SERIAL PRIMARY KEY,
no_meja VARCHAR(10) NOT NULL,
kode_qr VARCHAR(255)
);

-- 3. Tabel Pengguna (Tabel Master)
CREATE TABLE pengguna (
id SERIAL PRIMARY KEY,
nama VARCHAR(100) NOT NULL,
username VARCHAR(50) UNIQUE NOT NULL,
password VARCHAR(255) NOT NULL,
role VARCHAR(255) NOT NULL
);

-- 4. Tabel Menu (Bergantung pada Kategori)
CREATE TABLE menu (
id SERIAL PRIMARY KEY,
kategori_id INT REFERENCES kategori(id),
nama_menu VARCHAR(100) NOT NULL,
harga DECIMAL(19, 0) NOT NULL,
gambar VARCHAR(255),
deskripsi VARCHAR(150),
stok INT NOT NULL
);

-- 5. Tabel Pemesanan (Bergantung pada Meja & Pengguna)
CREATE TABLE pemesanan (
id SERIAL PRIMARY KEY,
meja_id INT REFERENCES meja(id),
pengguna_id INT REFERENCES pengguna(id),
nama_pelanggan VARCHAR(100),
email_pelanggan VARCHAR(100),
total_harga DECIMAL(19, 0),
status_pemesanan VARCHAR(50),
waktu_pemesanan TIMESTAMP
);

-- 6. Tabel Detail Pemesanan (Bergantung pada Menu & Pemesanan)
CREATE TABLE detail_pemesanan (
id SERIAL PRIMARY KEY,
menu_id INT REFERENCES menu(id),
pemesanan_id INT REFERENCES pemesanan(id) ON DELETE CASCADE,
harga DECIMAL(19, 0) NOT NULL,
jumlah INT NOT NULL,
subtotal DECIMAL(19, 0) NOT NULL,
catatan VARCHAR(255)
);

-- 7. Tabel Pembayaran (Bergantung pada Pemesanan & Pengguna)
CREATE TABLE pembayaran (
id SERIAL PRIMARY KEY,
pemesanan_id INT REFERENCES pemesanan(id),
pengguna_id INT REFERENCES pengguna(id),
waktu_pembayaran TIMESTAMP,
status_pembayaran VARCHAR(100),
metode_pembayaran VARCHAR(100),
total_bayar DECIMAL(19, 0) NOT NULL
);

-- ========================================================
-- INSERT DATA INITIAL
-- ========================================================

-- Insert Data Kategori
INSERT INTO kategori (nama_kategori) VALUES
('Minuman'),
('Makanan'),
('Dessert');

-- Insert Data Menu
INSERT INTO menu (kategori_id, nama_menu, harga, gambar, deskripsi, stok) VALUES
-- KATEGORI MINUMAN (kategori_id = 1)
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

-- KATEGORI MAKANAN (kategori_id = 2)
(2, 'Croissant Butter', 22000, NULL, 'Croissant renyah di luar dengan tekstur lembut dan buttery di dalam.', 50),
(2, 'Chicken Sandwich', 32000, NULL, 'Roti lembut berisi ayam gurih, sayuran segar, dan saus creamy.', 50),
(2, 'Beef Toast', 35000, NULL, 'Roti panggang dengan irisan daging sapi gurih dan saus khas.', 50),
(2, 'French Fries', 20000, NULL, 'Kentang goreng renyah dengan rasa gurih, cocok sebagai teman minuman.', 50),
(2, 'Chicken Croffle', 30000, NULL, 'Croffle renyah dengan potongan ayam gurih dan saus yang creamy.', 50);