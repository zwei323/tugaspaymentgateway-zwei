<?php
/** Verify a Duitku transaction directly with the official status API and sync it locally. */
function duitkuCheckStatusAndSync(mysqli $conn, array $order, array $config): string {
    $merchantCode = (string)($config['merchant_code'] ?? '');
    $apiKey = (string)($config['api_key'] ?? '');
    $orderId = (string)($order['id_transaksi'] ?? '');
    $reference = (string)($order['duitku_reference'] ?? '');

    if ($merchantCode === '' || $apiKey === '' || !ctype_digit($orderId) || $reference === '') {
        throw new RuntimeException('Konfigurasi Duitku atau referensi pembayaran belum lengkap.');
    }

    $payload = [
        'merchantCode' => $merchantCode,
        'merchantOrderId' => $orderId,
        'signature' => hash_hmac('sha256', $merchantCode . $orderId, $apiKey),
    ];
    $endpoint = !empty($config['sandbox'])
        ? 'https://sandbox.duitku.com/webapi/api/merchant/transactionStatus'
        : 'https://passport.duitku.com/webapi/api/merchant/transactionStatus';

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $response = is_string($body) ? json_decode($body, true) : null;
    if ($body === false || $httpCode < 200 || $httpCode >= 300 || !is_array($response)) {
        error_log('Duitku transactionStatus failed: HTTP ' . $httpCode . ' ' . $curlError);
        throw new RuntimeException('Status pembayaran belum dapat diperiksa ke Duitku.');
    }

    if ((string)($response['merchantOrderId'] ?? '') !== $orderId
        || (string)($response['reference'] ?? '') !== $reference
        || (int)($response['amount'] ?? -1) !== (int)round((float)$order['total_belanja'])) {
        throw new RuntimeException('Data status dari Duitku tidak cocok dengan pesanan.');
    }

    $result = (string)($response['statusCode'] ?? '');
    if ($result === '00') {
        $stmt = $conn->prepare("UPDATE tbl_transaksi SET status_pembayaran = 'Lunas' WHERE id_transaksi = ? AND pembayaran = 'Duitku'");
        $id = (int)$orderId;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        return 'Lunas';
    }

    if ($result === '02') {
        $stmt = $conn->prepare("UPDATE tbl_transaksi SET status_pembayaran = 'Gagal' WHERE id_transaksi = ? AND pembayaran = 'Duitku' AND status_pembayaran <> 'Lunas'");
        $id = (int)$orderId;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        return 'Gagal';
    }

    return 'Menunggu';
}
