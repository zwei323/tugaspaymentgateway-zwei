# Tokoku --- Aplikasi Web Toko

Tokoku adalah aplikasi web toko berbasis PHP Native dan MySQL yang
dibuat untuk membantu mengelola data produk, pelanggan, transaksi
penjualan, serta laporan penjualan. Aplikasi ini dikembangkan dan
dijalankan menggunakan lingkungan lokal XAMPP.

## Fitur

-   **Dashboard Admin** --- halaman ringkasan pengelolaan toko.
-   **Manajemen Produk (CRUD)** --- tambah, lihat, ubah, dan hapus data
    produk; termasuk harga, stok, kategori, barcode, dan gambar produk
    (sesuai konfigurasi).
-   **Manajemen Pelanggan** --- pengelolaan data pelanggan.
-   **Transaksi Penjualan** --- input transaksi, memilih pelanggan dan
    produk, serta menentukan jumlah pembelian.
-   **Barcode Scanner** --- pemindaian barcode melalui scanner USB dan
    dukungan kamera browser jika tersedia.
-   **Riwayat Transaksi** --- melihat transaksi yang tersimpan, membuka
    detail, dan mencetak struk.
-   **Pembayaran QR** --- menampilkan QR pembayaran yang disediakan
    pemilik untuk pembayaran manual; bukan integrasi payment gateway
    otomatis.
-   **Laporan Penjualan** --- ringkasan dan daftar transaksi untuk
    membantu pemantauan penjualan.
-   **Filter Transaksi** --- filter kasir dan rentang tanggal, serta
    ringkasan penjualan per kasir (bergantung pada tersedianya
    relasi/kolom kasir pada database).

## Teknologi

-   PHP Native
-   MySQL / MariaDB
-   HTML
-   CSS
-   JavaScript
-   Bootstrap (UI)
-   Bootstrap Icons
-   XAMPP (Apache & MySQL/MariaDB)
-   Barcode Detection API pada browser yang mendukung, untuk pemindaian
    menggunakan kamera

## Persyaratan

-   Windows (panduan ini menggunakan Windows)
-   XAMPP
-   PHP versi yang kompatibel dengan kode proyek
-   MySQL atau MariaDB
-   Browser modern
-   Git (opsional, untuk pengelolaan repository)

## Instalasi Lokal

1.  Install dan jalankan XAMPP.

2.  Start **Apache** dan **MySQL** dari XAMPP Control Panel.

3.  Salin folder proyek ke direktori:

    ``` text
    D:\xampp\htdocs\tokoku
    ```

4.  Buka phpMyAdmin melalui:

    ``` text
    http://localhost/phpmyadmin
    ```

5.  Buat database dengan nama:

    ``` text
    db_tokoku
    ```

6.  Import file SQL proyek (jika tersedia) ke database `db_tokoku`.
    Pastikan tabel, view, trigger, dan stored procedure yang dibutuhkan
    sudah dibuat.

7.  Sesuaikan konfigurasi database di file `config.php`:

    ``` php
    <?php
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "db_tokoku";

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        die("Koneksi database gagal: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
    ```

    Sesuaikan host, username, password, dan nama database dengan
    konfigurasi lokal.

8.  Pastikan file `header.php`, `footer.php`, helper, dan file
    konfigurasi berada pada lokasi yang sesuai dengan struktur proyek.

9.  Buka aplikasi melalui:

    ``` text
    http://localhost/tokoku
    ```

> Nama file SQL untuk skema database dapat berbeda di setiap versi
> proyek. Import file SQL yang sesuai dengan proyek yang sedang
> digunakan.

## Struktur Direktori (Gambaran)

Struktur di bawah merupakan gambaran umum. Nama dan susunan file dapat
berbeda sesuai versi proyek.

``` text
tokoku/
├── config.php
├── functions.php
├── header.php
├── footer.php
├── index.php
├── produk.php
├── pelanggan.php
├── transaksi.php
├── laporan.php
├── detail.php
├── struk.php
├── kasir/
├── user/
├── assets/
└── uploads/
    └── produk/
```

## Alur Transaksi

1.  Admin/kasir membuka halaman transaksi.
2.  Pilih pelanggan.
3.  Pilih produk atau scan barcode.
4.  Masukkan jumlah barang.
5.  Simpan transaksi.
6.  Sistem memproses transaksi menggunakan prosedur `TambahTransaksi`
    (jika prosedur tersebut tersedia dan sudah terpasang di database).
7.  Transaksi ditampilkan pada riwayat.
8.  Pengguna dapat membuka detail transaksi atau mencetak struk.

## Barcode Scanner

Pemindaian barcode dapat dilakukan dengan:

-   **Scanner USB** --- scan kode produk; biasanya scanner berfungsi
    sebagai input keyboard dan mengirimkan Enter.
-   **Kamera browser** --- menggunakan API deteksi barcode browser,
    apabila browser/perangkat mendukung format yang digunakan dan izin
    kamera diberikan.

Jika barcode tidak dapat digunakan, pastikan kolom barcode produk
tersedia, data barcode sudah diisi, dan browser/perangkat mendukung
fitur kamera.

## Filter dan Ringkasan Transaksi

Halaman transaksi admin dapat menyediakan filter berdasarkan:

-   Kasir
-   Tanggal mulai
-   Tanggal akhir

Ringkasan dapat menampilkan jumlah transaksi, nilai penjualan, jumlah
produk terjual, dan rekap transaksi per kasir. Filter kasir membutuhkan
kolom ID kasir pada tabel transaksi dan relasi yang sesuai ke tabel
kasir. Jika struktur database belum memiliki relasi tersebut, filter
kasir perlu disesuaikan terlebih dahulu.

## Cetak Struk dan Laporan

-   Struk dapat dibuka dari riwayat transaksi berdasarkan ID transaksi.
-   Laporan dapat ditampilkan berdasarkan data transaksi yang tersedia.
-   Fitur cetak menggunakan fasilitas print browser.
-   Pastikan printer dan pengaturan halaman sudah sesuai sebelum
    mencetak.

## Catatan Pembayaran QR

QR pembayaran pada aplikasi ditujukan untuk menampilkan kode pembayaran
milik toko. Konfirmasi pembayaran dilakukan secara manual kecuali proyek
telah dikembangkan dengan integrasi payment gateway resmi. Jangan
menganggap pembayaran otomatis terverifikasi hanya karena QR
ditampilkan.

## Payment Gateway Duitku (Sandbox)

Checkout pelanggan menyediakan opsi Payment Gateway Duitku. Integrasi
menggunakan Duitku POP Create Invoice dan callback server untuk validasi
pembayaran. Halaman demo sukses Duitku hanya untuk simulasi sandbox;
aplikasi membuat invoice sendiri dan tidak memakai URL demo sebagai
endpoint API.

1. Import `migration_duitku.sql` satu kali ke database `db_tokoku`.
2. Atur environment variables pada konfigurasi Apache/XAMPP, lalu
   restart Apache:

   ```apache
   SetEnv DUITKU_MERCHANT_CODE "KODE_MERCHANT_SANDBOX"
   SetEnv DUITKU_API_KEY "API_KEY_SANDBOX"
   SetEnv DUITKU_SANDBOX "1"
   SetEnv DUITKU_CALLBACK_BASE "https://alamat-publik-anda/tokoku"
   SetEnv DUITKU_RETURN_BASE "http://localhost/tokoku"
   ```

   `DUITKU_CALLBACK_BASE` harus HTTPS dan dapat diakses dari server
   Duitku. Untuk pengembangan lokal, gunakan tunnel HTTPS yang mengarah
   ke XAMPP. Tanpa URL publik ini, callback pembayaran tidak dapat
   diterima.
   `DUITKU_RETURN_BASE` mengatur halaman toko yang dibuka kembali di
   browser pelanggan setelah checkout Duitku. Untuk situs production,
   isi dengan URL publik toko, misalnya `https://domain-anda/tokoku`.
3. Pastikan ekstensi PHP cURL aktif. Pada sandbox gunakan merchant code
   dan API key sandbox dari proyek Duitku.
4. Duitku akan memanggil
   `https://alamat-publik-anda/tokoku/user/duitku_callback.php`.
   Status pembayaran hanya berubah menjadi berhasil setelah callback
   dengan signature, jumlah, ID pesanan, dan reference yang valid.
5. Setelah pengujian selesai, untuk menerima pembayaran nyata buat/gunakan
   kredensial production dan set `DUITKU_SANDBOX` menjadi `0`. Jangan
   memakai kredensial sandbox untuk transaksi production.

## Troubleshooting

### Koneksi database gagal

-   Pastikan MySQL/MariaDB sudah berjalan di XAMPP.
-   Periksa konfigurasi host, username, password, dan nama database di
    `config.php`.
-   Pastikan database `db_tokoku` sudah dibuat dan tabel telah diimpor.

### `Undefined variable $conn` atau `prepare() on null`

-   Pastikan file konfigurasi koneksi dimuat sebelum query dijalankan.
-   Pastikan variabel koneksi bernama `$conn`.
-   Gunakan `require_once` untuk menghindari file konfigurasi/helper
    dimuat berulang.

### Stored Procedure `TambahTransaksi` tidak ditemukan

-   Pastikan stored procedure sudah diimpor/dibuat pada database.
-   Periksa nama dan parameter prosedur agar sesuai dengan pemanggilan
    dari PHP.

### Barcode kamera tidak berfungsi

-   Gunakan browser yang mendukung Barcode Detection API.
-   Izinkan akses kamera.
-   Gunakan scanner USB sebagai alternatif.
-   Pastikan barcode produk telah terdaftar di database.

### Gambar produk tidak tampil

-   Pastikan file gambar berada pada folder upload yang digunakan
    aplikasi.
-   Periksa path relatif gambar dan izin folder.
-   Pastikan nama file yang tersimpan di database sesuai dengan file
    aktual.

## Keamanan dan Pengembangan Lanjutan

-   Gunakan prepared statements untuk query dengan input pengguna.
-   Validasi data di server, jangan hanya mengandalkan validasi HTML.
-   Escape output HTML untuk mengurangi risiko XSS.
-   Jangan menyimpan password dalam bentuk teks biasa; gunakan
    `password_hash()` dan `password_verify()`.
-   Batasi akses halaman berdasarkan sesi dan peran pengguna.
-   Untuk deployment, jangan gunakan konfigurasi debug terbuka atau
    kredensial database default.
-   Buat backup database sebelum migrasi/perubahan skema.

## Pengembangan

Proyek ini masih dapat dikembangkan dengan penambahan fitur seperti:

-   Rekap penjualan berdasarkan periode.
-   Ekspor laporan.
-   Pengelolaan hak akses admin dan kasir yang lebih rinci.
-   Integrasi payment gateway.
-   Notifikasi transaksi.
-   Pengelolaan stok dan riwayat stok.
-   Penyempurnaan dashboard analitik.

## Lisensi

Tambahkan informasi lisensi sesuai ketentuan pemilik proyek sebelum
mendistribusikan atau menggunakan ulang kode.

------------------------------------------------------------------------

**Tokoku** --- aplikasi pengelolaan toko berbasis web untuk membantu
proses administrasi dan penjualan.
