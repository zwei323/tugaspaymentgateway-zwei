<?php
require_once "fungsi.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);
    $cart = $_SESSION['cart'] ?? [];
    $back = 'keranjang.php';

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $stmt = $conn->prepare("SELECT nama_produk, stok FROM tbl_produk WHERE id_produk = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();

        if (!$p) {
            flash('danger', 'Produk tidak ditemukan.');
        } elseif ($aksi === 'tambah') {
            $back = safe_back($_POST['back'] ?? '');
            if ((int)$p['stok'] <= 0) {
                flash('danger', $p['nama_produk'] . ' sedang habis.');
            } else {
                $qty  = max(1, (int)($_POST['qty'] ?? 1));
                $baru = min(($cart[$id] ?? 0) + $qty, (int)$p['stok']);
                $cart[$id] = $baru;
                flash('success', $p['nama_produk'] . ' ditambahkan ke keranjang.');
            }
        } else { // ubah
            $qty = (int)($_POST['qty'] ?? 0);
            if ($qty <= 0) unset($cart[$id]);
            else $cart[$id] = min($qty, max(1, (int)$p['stok']));
            flash('success', 'Keranjang diperbarui.');
        }
    } elseif ($aksi === 'hapus') {
        unset($cart[$id]);
        flash('success', 'Produk dihapus dari keranjang.');
    } elseif ($aksi === 'kosongkan') {
        $cart = [];
        flash('success', 'Keranjang dikosongkan.');
    }
    $_SESSION['cart'] = $cart;
    header("Location: " . $back);
    exit;
}

$pageTitle = "Keranjang";
require "header.php";
[$items, $total] = cart_items($conn);
$ada_kurang = (bool)array_filter($items, fn($i) => $i['kurang']);
?>
<h4 class="mb-3"><i class="bi bi-cart3"></i> Keranjang Belanja</h4>

<?php if (!$items): ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-cart-x fs-1"></i>
    <p class="mt-2">Keranjang Anda masih kosong.</p>
    <a href="produk.php" class="btn btn-primary">Mulai Belanja</a>
  </div>
<?php else: ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm"><div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Produk</th><th>Harga</th><th style="width:150px">Jumlah</th><th>Subtotal</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
          <tr>
            <td><div class="fw-semibold"><?= e($i['nama_produk']) ?></div>
                <small class="text-muted"><?= e($i['nama_kategori'] ?? '') ?></small>
                <?php if ($i['kurang']): ?><div class="small text-danger">Stok tersisa <?= (int)$i['stok'] ?></div><?php endif; ?></td>
            <td><?= rupiah($i['harga']) ?></td>
            <td>
              <form method="post" class="input-group input-group-sm">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="ubah">
                <input type="hidden" name="id" value="<?= (int)$i['id_produk'] ?>">
                <input type="number" name="qty" class="form-control" min="0" max="<?= (int)$i['stok'] ?>" value="<?= (int)$i['qty'] ?>">
                <button class="btn btn-outline-secondary" title="Perbarui"><i class="bi bi-check2"></i></button>
              </form>
            </td>
            <td class="fw-semibold"><?= rupiah($i['subtotal']) ?></td>
            <td>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int)$i['id_produk'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
    <div class="mt-3 d-flex gap-2">
      <a href="produk.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Lanjut Belanja</a>
      <form method="post" onsubmit="return confirm('Kosongkan keranjang?')">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="kosongkan">
        <button class="btn btn-outline-danger">Kosongkan</button>
      </form>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <h6>Ringkasan</h6>
      <div class="d-flex justify-content-between"><span>Jumlah barang</span><span><?= cart_count() ?></span></div>
      <hr>
      <div class="d-flex justify-content-between fs-5 fw-bold"><span>Total</span><span class="text-primary"><?= rupiah($total) ?></span></div>
      <?php if ($ada_kurang): ?>
        <div class="alert alert-warning small mt-3 mb-0">Ada produk yang melebihi stok. Perbarui jumlahnya dulu.</div>
      <?php else: ?>
        <a href="checkout.php" class="btn btn-primary w-100 mt-3"><i class="bi bi-bag-check"></i> Proses Transaksi</a>
      <?php endif; ?>
    </div></div>
  </div>
</div>
<?php endif; ?>
<?php require "footer.php"; ?>
