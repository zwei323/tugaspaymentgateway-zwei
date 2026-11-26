<?php
require_once "fungsi.php";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // Hanya hapus data pelanggan; sesi admin (jika ada) tidak ikut terhapus
    unset($_SESSION['user_id'], $_SESSION['user_nama'], $_SESSION['user_pelanggan'], $_SESSION['pesanan_saya']);
    session_regenerate_id(true);
    flash('success', 'Anda berhasil keluar.');
}
header("Location: index.php");
exit;
