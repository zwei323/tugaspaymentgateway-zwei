<?php
require_once __DIR__ . '/koneksi.php';
$config = require __DIR__ . '/duitku_config.php';

function duitkuCallbackFail(int $code = 400): void {
    http_response_code($code);
    exit('Invalid callback');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$config['merchant_code'] || !$config['7a4e04b421b61daa8e3d650d5d1fb458']) {
    duitkuCallbackFail();
}

$merchantCode = (string)($_POST['merchantCode'] ?? '');
$amount = (string)($_POST['amount'] ?? '');
$orderId = (string)($_POST['merchantOrderId'] ?? '');
$reference = (string)($_POST['reference'] ?? '');
$signature = (string)($_POST['signature'] ?? '');
$resultCode = (string)($_POST['resultCode'] ?? '');

if ($merchantCode !== $config['merchant_code'] || !ctype_digit($amount) || !ctype_digit($orderId) || $reference === '' || $signature === '') {
    duitkuCallbackFail();
}

$expected = hash_hmac('sha256', $merchantCode . $amount . $orderId, $config['7a4e04b421b61daa8e3d650d5d1fb458']);
if (!hash_equals($expected, $signature)) {
    duitkuCallbackFail(403);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('SELECT total_belanja, duitku_reference, status_pembayaran FROM tbl_transaksi WHERE id_transaksi = ? FOR UPDATE');
    $id = (int)$orderId;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order || !hash_equals((string)$order['duitku_reference'], $reference) || (int)round((float)$order['total_belanja']) !== (int)$amount) {
        $conn->rollback();
        duitkuCallbackFail(404);
    }

    if ($resultCode === '00' && $order['status_pembayaran'] !== 'Lunas') {
        $stmt = $conn->prepare("UPDATE tbl_transaksi SET status_pembayaran = 'Lunas' WHERE id_transaksi = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($resultCode === '01' && $order['status_pembayaran'] === 'Menunggu') {
        $stmt = $conn->prepare("UPDATE tbl_transaksi SET status_pembayaran = 'Gagal' WHERE id_transaksi = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->commit();
    http_response_code(200);
    echo 'SUCCESS';
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Duitku callback error: ' . $e->getMessage());
    duitkuCallbackFail(500);
}
