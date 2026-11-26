USE db_tokoku;

-- Pastikan tbl_pelanggan dan tbl_transaksi dari BAB sebelumnya sudah ada.
CREATE TABLE IF NOT EXISTS tbl_kategori (
  id_kategori INT AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS tbl_produk (
  id_produk INT AUTO_INCREMENT PRIMARY KEY,
  nama_produk VARCHAR(100) NOT NULL,
  id_kategori INT,
  harga DECIMAL(12,2) NOT NULL,
  stok INT NOT NULL DEFAULT 0,
  barcode VARCHAR(64) NULL UNIQUE,
  CONSTRAINT fk_produk_kategori FOREIGN KEY (id_kategori) REFERENCES tbl_kategori(id_kategori)
);

CREATE TABLE IF NOT EXISTS tbl_detail_transaksi (
  id_detail INT AUTO_INCREMENT PRIMARY KEY,
  id_transaksi INT NOT NULL,
  id_produk INT NOT NULL,
  jumlah INT NOT NULL,
  harga DECIMAL(12,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_detail_transaksi FOREIGN KEY (id_transaksi) REFERENCES tbl_transaksi(id_transaksi),
  CONSTRAINT fk_detail_produk FOREIGN KEY (id_produk) REFERENCES tbl_produk(id_produk)
);

CREATE TABLE IF NOT EXISTS tbl_log (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  aktivitas VARCHAR(100),
  keterangan TEXT,
  waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Data kategori/produk contoh (jalankan hanya sekali jika data belum tersedia)
INSERT INTO tbl_kategori(nama_kategori)
SELECT 'Makanan' WHERE NOT EXISTS (SELECT 1 FROM tbl_kategori WHERE nama_kategori='Makanan');
INSERT INTO tbl_kategori(nama_kategori)
SELECT 'Minuman' WHERE NOT EXISTS (SELECT 1 FROM tbl_kategori WHERE nama_kategori='Minuman');
INSERT INTO tbl_kategori(nama_kategori)
SELECT 'Peralatan Rumah' WHERE NOT EXISTS (SELECT 1 FROM tbl_kategori WHERE nama_kategori='Peralatan Rumah');

INSERT INTO tbl_produk(nama_produk,id_kategori,harga,stok)
SELECT 'Indomie Goreng', (SELECT id_kategori FROM tbl_kategori WHERE nama_kategori='Makanan' LIMIT 1),3500,100
WHERE NOT EXISTS (SELECT 1 FROM tbl_produk WHERE nama_produk='Indomie Goreng');
INSERT INTO tbl_produk(nama_produk,id_kategori,harga,stok)
SELECT 'Beras 5 KG', (SELECT id_kategori FROM tbl_kategori WHERE nama_kategori='Makanan' LIMIT 1),75000,50
WHERE NOT EXISTS (SELECT 1 FROM tbl_produk WHERE nama_produk='Beras 5 KG');
INSERT INTO tbl_produk(nama_produk,id_kategori,harga,stok)
SELECT 'Teh Botol', (SELECT id_kategori FROM tbl_kategori WHERE nama_kategori='Minuman' LIMIT 1),5000,80
WHERE NOT EXISTS (SELECT 1 FROM tbl_produk WHERE nama_produk='Teh Botol');
INSERT INTO tbl_produk(nama_produk,id_kategori,harga,stok)
SELECT 'Aqua 600 ML', (SELECT id_kategori FROM tbl_kategori WHERE nama_kategori='Minuman' LIMIT 1),3000,100
WHERE NOT EXISTS (SELECT 1 FROM tbl_produk WHERE nama_produk='Aqua 600 ML');
INSERT INTO tbl_produk(nama_produk,id_kategori,harga,stok)
SELECT 'Sabun Cuci', (SELECT id_kategori FROM tbl_kategori WHERE nama_kategori='Peralatan Rumah' LIMIT 1),12000,40
WHERE NOT EXISTS (SELECT 1 FROM tbl_produk WHERE nama_produk='Sabun Cuci');

-- Buat ulang view laporan
CREATE OR REPLACE VIEW view_laporan_penjualan AS
SELECT t.id_transaksi,t.tanggal,p.nama_pelanggan,pr.nama_produk,k.nama_kategori,
       d.jumlah,d.harga,d.subtotal,t.total_belanja
FROM tbl_transaksi t
JOIN tbl_pelanggan p ON p.id_pelanggan=t.id_pelanggan
JOIN tbl_detail_transaksi d ON d.id_transaksi=t.id_transaksi
JOIN tbl_produk pr ON pr.id_produk=d.id_produk
JOIN tbl_kategori k ON k.id_kategori=pr.id_kategori;

-- Trigger audit transaksi
DROP TRIGGER IF EXISTS after_insert_transaksi_audit;
DELIMITER //
CREATE TRIGGER after_insert_transaksi_audit
AFTER INSERT ON tbl_transaksi
FOR EACH ROW
BEGIN
  INSERT INTO tbl_log(aktivitas,keterangan)
  VALUES('INSERT TRANSAKSI', CONCAT('Transaksi ID: ', NEW.id_transaksi));
END //
DELIMITER ;

-- Stored Procedure proses tambah transaksi (menggunakan transaksi TCL dan validasi stok)
DROP PROCEDURE IF EXISTS TambahTransaksi;
DELIMITER //
CREATE PROCEDURE TambahTransaksi(IN p_id_pelanggan INT, IN p_id_produk INT, IN p_jumlah INT)
BEGIN
  DECLARE v_harga DECIMAL(12,2);
  DECLARE v_stok INT;
  DECLARE v_subtotal DECIMAL(12,2);
  DECLARE v_id_transaksi INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF p_jumlah <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Jumlah produk harus lebih dari 0';
  END IF;

  START TRANSACTION;
  SELECT harga,stok INTO v_harga,v_stok FROM tbl_produk WHERE id_produk=p_id_produk FOR UPDATE;
  IF v_stok IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Produk tidak ditemukan';
  END IF;
  IF v_stok < p_jumlah THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Stok produk tidak mencukupi';
  END IF;

  SET v_subtotal=v_harga*p_jumlah;
  INSERT INTO tbl_transaksi(tanggal,id_pelanggan,total_belanja)
  VALUES(CURDATE(),p_id_pelanggan,v_subtotal);
  SET v_id_transaksi=LAST_INSERT_ID();

  INSERT INTO tbl_detail_transaksi(id_transaksi,id_produk,jumlah,harga,subtotal)
  VALUES(v_id_transaksi,p_id_produk,p_jumlah,v_harga,v_subtotal);

  UPDATE tbl_produk SET stok=stok-p_jumlah WHERE id_produk=p_id_produk;
  COMMIT;
END //
DELIMITER ;

-- Stored Functions
DROP FUNCTION IF EXISTS HitungSubtotal;
DELIMITER //
CREATE FUNCTION HitungSubtotal(p_harga DECIMAL(12,2), p_jumlah INT)
RETURNS DECIMAL(12,2) DETERMINISTIC NO SQL
BEGIN
  RETURN p_harga*p_jumlah;
END //
DELIMITER ;

DROP FUNCTION IF EXISTS HitungDiskon;
DELIMITER //
CREATE FUNCTION HitungDiskon(p_total DECIMAL(12,2))
RETURNS DECIMAL(12,2) DETERMINISTIC NO SQL
BEGIN
  RETURN p_total-(p_total*10/100);
END //
DELIMITER ;

-- Index untuk optimasi
CREATE INDEX idx_transaksi_pelanggan ON tbl_transaksi(id_pelanggan);
CREATE INDEX idx_transaksi_tanggal ON tbl_transaksi(tanggal);
CREATE INDEX idx_detail_produk ON tbl_detail_transaksi(id_produk);


-- Trigger BAB 5: catat pelanggan INSERT
CREATE TABLE IF NOT EXISTS log_pelanggan (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  aktivitas VARCHAR(50),
  id_pelanggan INT,
  waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
DROP TRIGGER IF EXISTS after_insert_pelanggan;
DELIMITER //
CREATE TRIGGER after_insert_pelanggan
AFTER INSERT ON tbl_pelanggan
FOR EACH ROW
BEGIN
  INSERT INTO log_pelanggan(aktivitas,id_pelanggan)
  VALUES('INSERT PELANGGAN',NEW.id_pelanggan);
END //
DELIMITER ;

-- Trigger BAB 5: audit perubahan nama pelanggan
CREATE TABLE IF NOT EXISTS log_perubahan_pelanggan (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  id_pelanggan INT,
  nama_lama VARCHAR(100),
  nama_baru VARCHAR(100),
  waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
DROP TRIGGER IF EXISTS after_update_pelanggan;
DELIMITER //
CREATE TRIGGER after_update_pelanggan
AFTER UPDATE ON tbl_pelanggan
FOR EACH ROW
BEGIN
  INSERT INTO log_perubahan_pelanggan(id_pelanggan,nama_lama,nama_baru)
  VALUES(OLD.id_pelanggan,OLD.nama_pelanggan,NEW.nama_pelanggan);
END //
DELIMITER ;

-- Trigger BAB 5: validasi total transaksi tidak boleh negatif
DROP TRIGGER IF EXISTS before_insert_transaksi;
DELIMITER //
CREATE TRIGGER before_insert_transaksi
BEFORE INSERT ON tbl_transaksi
FOR EACH ROW
BEGIN
  IF NEW.total_belanja < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Total belanja tidak boleh negatif';
  END IF;
END //
DELIMITER ;

-- Trigger BAB 5: catat penghapusan pelanggan (jika pelanggan tidak terikat transaksi)
CREATE TABLE IF NOT EXISTS log_hapus_pelanggan (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  id_pelanggan INT,
  nama_pelanggan VARCHAR(100),
  waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
DROP TRIGGER IF EXISTS after_delete_pelanggan;
DELIMITER //
CREATE TRIGGER after_delete_pelanggan
AFTER DELETE ON tbl_pelanggan
FOR EACH ROW
BEGIN
  INSERT INTO log_hapus_pelanggan(id_pelanggan,nama_pelanggan)
  VALUES(OLD.id_pelanggan,OLD.nama_pelanggan);
END //
DELIMITER ;
