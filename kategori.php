<?php
$pageTitle = "Semua Kategori";
require "header.php";
$kategori = $conn->query("SELECT k.*, COUNT(p.id_produk) jumlah FROM tbl_kategori k
                          LEFT JOIN tbl_produk p ON p.id_kategori = k.id_kategori
                          GROUP BY k.id_kategori ORDER BY k.nama_kategori");
?>
<h4 class="mb-3">Semua Kategori</h4>
<div class="row g-3">
  <div class="col-6 col-md-4 col-lg-3">
    <a class="card kat-card h-100 text-center p-4" href="produk.php">
      <i class="bi bi-grid kat-icon"></i>
      <div class="fw-semibold mt-2">Semua Produk</div>
    </a>
  </div>
<?php while ($k = $kategori->fetch_assoc()): ?>
  <div class="col-6 col-md-4 col-lg-3">
    <a class="card kat-card h-100 text-center p-4" href="produk.php?kat=<?= (int)$k['id_kategori'] ?>">
      <i class="bi <?= ikon_kategori($k['id_kategori']) ?> kat-icon"></i>
      <div class="fw-semibold mt-2"><?= e($k['nama_kategori']) ?></div>
      <small class="text-muted"><?= (int)$k['jumlah'] ?> produk</small>
    </a>
  </div>
<?php endwhile; ?>
</div>
<?php require "footer.php"; ?>
