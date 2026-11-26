<?php
$pageTitle = "Lacak Pesanan";
require "header.php";

$id    = (int)($_REQUEST['id'] ?? 0);
$nama  = trim($_REQUEST['nama'] ?? '');
$order = null; $items = []; $err = '';

if ($id > 0) {
    $stmt = $conn->prepare("SELECT t.*, p.nama_pelanggan, p.kota FROM tbl_transaksi t
                            JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
                            WHERE t.id_transaksi = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $milikSaya = in_array($id, $_SESSION['pesanan_saya'] ?? [], true);
    if ($row && isset($_SESSION['user_pelanggan']) && (int)$row['id_pelanggan'] === (int)$_SESSION['user_pelanggan']) $milikSaya = true;
    $namaCocok = $row && $nama !== '' && mb_strtolower($nama) === mb_strtolower($row['nama_pelanggan']);

    if ($row && ($milikSaya || $namaCocok)) {
        $order = $row;
        $stmt = $conn->prepare("SELECT d.*, pr.nama_produk FROM tbl_detail_transaksi d
                                JOIN tbl_produk pr ON pr.id_produk = d.id_produk
                                WHERE d.id_transaksi = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || $nama !== '') {
        $err = "Pesanan tidak ditemukan atau nama tidak cocok.";
    }
}

$langkah = ['Menunggu' => 'Pesanan Diterima', 'Diproses' => 'Diproses', 'Dikirim' => 'Dikirim', 'Selesai' => 'Selesai'];
$ikon    = ['Menunggu' => 'bi-receipt', 'Diproses' => 'bi-box-seam', 'Dikirim' => 'bi-truck', 'Selesai' => 'bi-check-lg'];
?>
<h4 class="mb-3"><i class="bi bi-truck"></i> Lacak Pesanan</h4>

<div class="card border-0 shadow-sm mb-4"><div class="card-body">
  <form method="post" class="row g-2">
    <div class="col-md-3"><input type="number" name="id" class="form-control" placeholder="Nomor pesanan" min="1" value="<?= $id ?: '' ?>" required></div>
    <div class="col-md-6"><input type="text" name="nama" class="form-control" placeholder="Nama pemesan" value="<?= e($nama) ?>"></div>
    <div class="col-md-3 d-grid"><button class="btn btn-primary"><i class="bi bi-search"></i> Lacak</button></div>
  </form>
  <?php if ($err): ?><div class="alert alert-danger mt-3 mb-0"><?= e($err) ?></div><?php endif; ?>
</div></div>

<?php if ($order): ?>
<div class="card border-0 shadow-sm"><div class="card-body">
  <div class="d-flex flex-wrap justify-content-between">
    <div><h5 class="mb-0">Pesanan #<?= (int)$order['id_transaksi'] ?></h5>
         <small class="text-muted"><?= e(date('d M Y', strtotime($order['tanggal']))) ?> &middot; <?= e($order['nama_pelanggan']) ?>, <?= e($order['kota']) ?></small></div>
    <div class="fs-5 fw-bold text-primary"><?= rupiah($order['total_belanja']) ?></div>
  </div>

  <?php if ($order['status'] === 'Dibatalkan'): ?>
    <div class="alert alert-danger mt-3"><i class="bi bi-x-circle"></i> Pesanan ini dibatalkan.</div>
  <?php else:
    $keys = array_keys($langkah);
    $idx  = array_search($order['status'], $keys, true);
    if ($idx === false) $idx = 0; ?>
    <div class="steps">
      <?php foreach ($keys as $n => $k): ?>
        <div class="step <?= $n <= $idx ? 'done' : '' ?> <?= $n === $idx ? 'now' : '' ?>">
          <div class="dot"><i class="bi <?= $ikon[$k] ?>"></i></div><?= e($langkah[$k]) ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h6 class="mt-3">Rincian</h6>
  <?php if ($items): ?>
  <div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Produk</th><th>Harga</th><th>Jumlah</th><th class="text-end">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr><td><?= e($it['nama_produk']) ?></td><td><?= rupiah($it['harga']) ?></td><td><?= (int)$it['jumlah'] ?></td><td class="text-end"><?= rupiah($it['subtotal']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><p class="text-muted small mb-0">Rincian barang tidak tersedia untuk pesanan ini.</p><?php endif; ?>

  <?php if (!empty($order['pengiriman'])): ?>
  <hr>
  <div class="row g-3 small">
    <div class="col-md-6">
      <div class="d-flex justify-content-between"><span>Subtotal barang</span><span><?= rupiah($order['total_belanja'] - $order['ongkir'] + $order['diskon']) ?></span></div>
      <div class="d-flex justify-content-between"><span>Ongkir</span><span><?= rupiah($order['ongkir']) ?></span></div>
      <?php if ($order['diskon'] > 0): ?>
      <div class="d-flex justify-content-between text-success"><span>Diskon (<?= e($order['kode_voucher']) ?>)</span><span>- <?= rupiah($order['diskon']) ?></span></div>
      <?php endif; ?>
      <div class="d-flex justify-content-between fw-bold"><span>Total</span><span><?= rupiah($order['total_belanja']) ?></span></div>
    </div>
    <div class="col-md-6">
      <div><span class="text-muted">Penerima:</span> <?= e($order['nama_penerima']) ?> (<?= e($order['no_hp']) ?>)</div>
      <div><span class="text-muted">Pengiriman:</span> <?= e($order['pengiriman']) ?></div>
      <?php if (!empty($order['alamat'])): ?><div><span class="text-muted">Alamat:</span> <?= nl2br(e($order['alamat'])) ?></div><?php endif; ?>
      <div><span class="text-muted">Pembayaran:</span> <?= e($order['pembayaran']) ?></div>
    </div>
  </div>
  <?php endif; ?>
</div></div>
<?php endif; ?>
<?php require "footer.php"; ?>
