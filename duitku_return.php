<?php
require_once __DIR__ . '/fungsi.php';
require_once __DIR__ . '/duitku_helper.php';
$configDuitku = require __DIR__ . '/duitku_config.php';
wajib_login();

// Never trust resultCode from the redirect. Confirm the status server-to-server.
$orderId = (int)($_GET['merchantOrderId'] ?? 0);
if ($orderId <= 0) {
    header('Location: pesanan.php');
    exit;
}

$idPelanggan = (int)$_SESSION['user_pelanggan'];
$stmt = $conn->prepare("SELECT id_transaksi, total_belanja, pembayaran, duitku_reference FROM tbl_transaksi WHERE id_transaksi = ? AND id_pelanggan = ? AND pembayaran = 'Duitku' LIMIT 1");
$stmt->bind_param('ii', $orderId, $idPelanggan);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    flash('Pesanan tidak ditemukan di akun ini.', 'warning');
    header('Location: pesanan.php');
    exit;
}

try {
    $statusPembayaran = duitkuCheckStatusAndSync($conn, $order, $configDuitku);
    if ($statusPembayaran === 'Lunas') {
        flash('Pembayaran terverifikasi dan berstatus Lunas.', 'success');
    } elseif ($statusPembayaran === 'Gagal') {
        flash('Pembayaran belum berhasil. Silakan cek metode pembayaran.', 'warning');
    } else {
        flash('Pembayaran masih diproses Duitku. Anda dapat cek kembali dari Pesanan Saya.', 'info');
    }
} catch (Throwable $error) {
    error_log('Duitku return status check error: ' . $error->getMessage());
    flash('Status belum dapat diverifikasi. Silakan cek kembali dari Pesanan Saya.', 'warning');
}

header('Location: struk.php?id=' . $orderId);
exit;
