<?php
require_once __DIR__ . '/fungsi.php';
require_once __DIR__ . '/duitku_helper.php';
wajib_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan.php');
    exit;
}
csrf_check();

$id = (int)($_POST['id_transaksi'] ?? 0);
$idPelanggan = (int)$_SESSION['user_pelanggan'];
$stmt = $conn->prepare("SELECT id_transaksi, total_belanja, pembayaran, duitku_reference FROM tbl_transaksi WHERE id_transaksi = ? AND id_pelanggan = ? AND pembayaran = 'Duitku' LIMIT 1");
$stmt->bind_param('ii', $id, $idPelanggan);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    flash('Pesanan Duitku tidak ditemukan.', 'warning');
    header('Location: pesanan.php');
    exit;
}

try {
    $configDuitku = require __DIR__ . '/duitku_config.php';
    $status = duitkuCheckStatusAndSync($conn, $order, $configDuitku);
    if ($status === 'Lunas') {
        flash('Pembayaran terverifikasi dan berstatus Lunas.', 'success');
    } elseif ($status === 'Gagal') {
        flash('Duitku menyatakan pembayaran gagal atau kedaluwarsa.', 'danger');
    } else {
        flash('Pembayaran masih menunggu konfirmasi Duitku.', 'info');
    }
} catch (Throwable $error) {
    error_log('Duitku manual status check error: ' . $error->getMessage());
    flash('Status belum dapat diperiksa. Coba lagi beberapa saat.', 'warning');
}

header('Location: pesanan.php');
exit;
