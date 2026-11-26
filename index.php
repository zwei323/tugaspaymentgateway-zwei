<?php
$pageTitle = "Beranda";
require "header.php";

$kategori = $conn->query("SELECT k.*, COUNT(p.id_produk) jumlah FROM tbl_kategori k
                          LEFT JOIN tbl_produk p ON p.id_kategori = k.id_kategori
                          GROUP BY k.id_kategori ORDER BY k.nama_kategori");
$terbaru = $conn->query("SELECT p.*, k.nama_kategori FROM tbl_produk p
                         LEFT JOIN tbl_kategori k ON k.id_kategori = p.id_kategori
                         ORDER BY p.id_produk DESC LIMIT 8");
?>
<div class="hero p-4 p-md-5 mb-4">
  <h2 class="fw-bold">Belanja kebutuhan harian, mudah &amp; cepat</h2>
  <p class="mb-4">Cari produk, masukkan keranjang, dan lacak pesanan Anda kapan saja.</p>
  <form action="produk.php" method="get" class="row g-2" style="max-width:560px">
    <div class="col"><input type="search" name="q" class="form-control form-control-lg" placeholder="Cari produk..."></div>
    <div class="col-auto"><button class="btn btn-warning btn-lg"><i class="bi bi-search"></i> Cari</button></div>
  </form>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Kategori</h5><a href="kategori.php">Lihat semua</a>
</div>
<div class="row g-3 mb-4">
<?php while ($k = $kategori->fetch_assoc()): ?>
  <div class="col-6 col-md-4">
    <a class="card kat-card h-100 text-center p-3" href="produk.php?kat=<?= (int)$k['id_kategori'] ?>">
      <i class="bi <?= ikon_kategori($k['id_kategori']) ?> kat-icon"></i>
      <div class="fw-semibold mt-2"><?= e($k['nama_kategori']) ?></div>
      <small class="text-muted"><?= (int)$k['jumlah'] ?> produk</small>
    </a>
  </div>
<?php endwhile; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Produk Terbaru</h5><a href="produk.php?urut=terbaru">Lihat semua</a>
</div>
<div class="row g-3">
<?php while ($p = $terbaru->fetch_assoc()) echo kartu_produk($p); ?>
</div>
<?php require "footer.php"; ?>
