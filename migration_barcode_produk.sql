-- Jalankan sekali pada database db_tokoku untuk menambahkan dukungan barcode.
USE db_tokoku;

ALTER TABLE tbl_produk
    ADD COLUMN barcode VARCHAR(64) NULL;

CREATE UNIQUE INDEX uq_tbl_produk_barcode
    ON tbl_produk (barcode);
