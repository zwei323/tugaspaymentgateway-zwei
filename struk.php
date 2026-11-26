<?php
require_once __DIR__ . '/fungsi.php';
wajib_login();

$id = (int)($_GET['id'] ?? 0);
$idPelanggan = (int)$_SESSION['user_pelanggan'];
$stmt = $conn->prepare(
    "SELECT t.id_transaksi, t.tanggal, t.total_belanja, t.status,
            t.nama_penerima, t.no_hp, t.alamat, t.pengiriman,
            t.ongkir, t.pembayaran, t.status_pembayaran, t.kode_voucher,
            t.diskon, p.nama_pelanggan
     FROM tbl_transaksi t
     LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
     WHERE t.id_transaksi = ? AND t.id_pelanggan = ?
     LIMIT 1"
);
$stmt->bind_param('ii', $id, $idPelanggan);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    $orderItems = [];
    $subtotal = 0;
} else {
    $stmt = $conn->prepare(
        "SELECT d.jumlah, d.harga, d.subtotal, p.nama_produk
         FROM tbl_detail_transaksi d
         LEFT JOIN tbl_produk p ON p.id_produk = d.id_produk
         WHERE d.id_transaksi = ?"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $orderItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $subtotal = array_sum(array_map(static fn($item) => (float)$item['subtotal'], $orderItems));
}

$qrPayload = $order
    ? 'TOKOKU|ID:' . $order['id_transaksi'] .
      '|TGL:' . $order['tanggal'] .
      '|SUB:' . number_format($subtotal, 0, ',', '.') .
      '|BAYAR:' . ($order['pembayaran'] ?: 'Belum tercatat') .
      '|TOTAL:' . number_format((float)$order['total_belanja'], 0, ',', '.')
    : '';

function rupiahStrukUser($value): string {
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk Pesanan</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:32px 16px;background:#f1f5f9;color:#1f2937;font:14px/1.5 Arial,sans-serif}
.receipt{max-width:520px;margin:0 auto;background:#fff;padding:30px 34px;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px #0f172a12}
.brand{text-align:center;margin-bottom:20px}.brand h1{margin:0;color:#1679bd;font-size:24px}.brand p{margin:4px 0;color:#64748b}
.meta{line-height:1.9}.rule{border:0;border-top:1px dashed #94a3b8;margin:18px 0}
table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:9px 4px;border-bottom:1px dashed #e2e8f0;text-align:left}th:last-child,td:last-child{text-align:right}.qty{white-space:nowrap}
.row{display:flex;justify-content:space-between;gap:16px;padding:5px 0}.total{font-size:18px;font-weight:700;color:#0f172a}
.qr{text-align:center;margin:18px 0 4px;font-size:12px;color:#475569}#receiptQr{display:flex;justify-content:center;margin:8px auto}#receiptQr img,#receiptQr canvas{width:112px!important;height:112px!important}
.back{display:block;width:max-content;margin:18px auto 0;padding:9px 16px;border-radius:7px;background:#2563eb;color:#fff;text-decoration:none}
.empty{text-align:center;padding:12px;color:#64748b}
@media print{body{display:none!important}}
</style>
</head>
<body>
<?php if (!$order): ?>
<main class="receipt"><div class="empty">Struk tidak ditemukan atau bukan milik akun Anda.</div></main>
<?php else: ?>
<main class="receipt">
  <header class="brand"><h1>TOKOKU</h1><p>Struk Pembelian</p></header>
  <hr class="rule">
  <section class="meta">
    <div><strong>No. Pesanan:</strong> #<?= (int)$order['id_transaksi'] ?></div>
    <div><strong>Tanggal:</strong> <?= e(date('d M Y', strtotime($order['tanggal']))) ?></div>
    <div><strong>Pelanggan:</strong> <?= e($order['nama_pelanggan'] ?? 'Pelanggan') ?></div>
    <div><strong>Status:</strong> <?= e($order['status']) ?></div>
  </section>
  <hr class="rule">
  <table>
    <thead><tr><th>Barang</th><th>Qty</th><th>Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($orderItems as $item): ?>
      <tr>
        <td><?= e($item['nama_produk'] ?? 'Produk') ?><br><small><?= rupiahStrukUser($item['harga']) ?> / item</small></td>
        <td class="qty"><?= (int)$item['jumlah'] ?></td>
        <td><?= rupiahStrukUser($item['subtotal']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <hr class="rule">
  <section>
    <div class="row"><span>Subtotal barang</span><span><?= rupiahStrukUser($subtotal) ?></span></div>
    <?php if ((float)$order['ongkir'] > 0): ?><div class="row"><span>Ongkir</span><span><?= rupiahStrukUser($order['ongkir']) ?></span></div><?php endif; ?>
    <?php if ((float)$order['diskon'] > 0): ?><div class="row"><span>Diskon</span><span>- <?= rupiahStrukUser($order['diskon']) ?></span></div><?php endif; ?>
    <div class="row"><span>Metode pembayaran</span><strong><?= e($order['pembayaran'] ?: 'Belum tercatat') ?></strong></div>
    <?php if ($order['pembayaran'] === 'Duitku'): ?><div class="row"><span>Status pembayaran</span><strong><?= e($order['status_pembayaran']) ?></strong></div><?php endif; ?>
    <hr class="rule">
    <div class="row total"><span>Total pembayaran</span><span><?= rupiahStrukUser($order['total_belanja']) ?></span></div>
  </section>
  <section class="qr"><strong>QR Ringkasan Transaksi</strong><div id="receiptQr"></div><span>QR ini berisi ringkasan pesanan, bukan QR pembayaran.</span></section>
</main>
<?php endif; ?>
<a class="back" href="pesanan.php">Kembali ke Pesanan Saya</a>
<?php if ($order): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
if (typeof QRCode !== 'undefined') {
    new QRCode(document.getElementById('receiptQr'), {
        text: <?= json_encode($qrPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        width: 112,
        height: 112,
        correctLevel: QRCode.CorrectLevel.M
    });
}
</script>
<?php endif; ?>
</body>
</html>
