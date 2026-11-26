<?php
require_once "fungsi.php";
wajib_login();
$pageTitle = "Pesanan Saya";
require "header.php";

$idp = (int)$_SESSION['user_pelanggan'];
$st = $conn->prepare("SELECT id_transaksi, tanggal, total_belanja, status, pembayaran, status_pembayaran FROM tbl_transaksi
                      WHERE id_pelanggan = ? ORDER BY id_transaksi DESC");
$st->bind_param("i", $idp);
$st->execute();
$rows = $st->get_result();

$warna = ['Menunggu'=>'secondary','Diproses'=>'info','Dikirim'=>'primary','Selesai'=>'success','Dibatalkan'=>'danger'];
?>
<h4 class="mb-3"><i class="bi bi-receipt"></i> Pesanan Saya</h4>
<?php if ($rows->num_rows === 0): ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-bag fs-1"></i>
    <p class="mt-2">Anda belum punya pesanan.</p>
    <a href="produk.php" class="btn btn-primary">Mulai Belanja</a>
  </div>
<?php else: ?>
<div class="card border-0 shadow-sm"><div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead class="table-light"><tr><th>No. Pesanan</th><th>Tanggal</th><th>Total</th><th>Status Pesanan</th><th>Pembayaran</th><th></th></tr></thead>
    <tbody>
    <?php while ($r = $rows->fetch_assoc()): ?>
      <tr>
        <td>#<?= (int)$r['id_transaksi'] ?></td>
        <td><?= e(date('d M Y', strtotime($r['tanggal']))) ?></td>
        <td><?= rupiah($r['total_belanja']) ?></td>
        <td><span class="badge text-bg-<?= $warna[$r['status']] ?? 'secondary' ?>"><?= e($r['status']) ?></span></td>
        <td><?= e($r['pembayaran']) ?><?php if ($r['pembayaran'] === 'Duitku'): ?> <span class="badge text-bg-<?= $r['status_pembayaran'] === 'Lunas' ? 'success' : ($r['status_pembayaran'] === 'Gagal' ? 'danger' : 'warning') ?>"><?= e($r['status_pembayaran']) ?></span><?php endif; ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="struk.php?id=<?= (int)$r['id_transaksi'] ?>">Struk</a>
          <a class="btn btn-sm btn-outline-secondary" href="lacak.php?id=<?= (int)$r['id_transaksi'] ?>">Lacak</a>
          <?php if ($r['pembayaran'] === 'Duitku' && $r['status_pembayaran'] !== 'Lunas'): ?>
          <form method="post" action="duitku_cek.php" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id_transaksi" value="<?= (int)$r['id_transaksi'] ?>">
            <button class="btn btn-sm btn-outline-success" type="submit">Cek Status</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div></div>
<?php endif; ?>
<?php require "footer.php"; ?>
