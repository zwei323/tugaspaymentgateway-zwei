USE db_tokoku;

-- Jalankan satu kali sebelum mengaktifkan pembayaran Duitku.
ALTER TABLE tbl_transaksi
  ADD COLUMN email_pelanggan VARCHAR(255) NULL AFTER no_hp,
  ADD COLUMN status_pembayaran VARCHAR(20) NOT NULL DEFAULT 'Menunggu' AFTER pembayaran,
  ADD COLUMN duitku_reference VARCHAR(100) NULL AFTER status_pembayaran,
  ADD UNIQUE KEY uq_transaksi_duitku_reference (duitku_reference);
