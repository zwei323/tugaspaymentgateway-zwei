<?php
$pageTitle="Tools Database"; require "header.php";
$explain=null;$testMessage=null;
if(isset($_POST["explain"])){
    $res=$conn->query("EXPLAIN SELECT t.id_transaksi,t.tanggal,p.nama_pelanggan,t.total_belanja FROM tbl_transaksi t JOIN tbl_pelanggan p ON p.id_pelanggan=t.id_pelanggan WHERE t.id_pelanggan=4");
    if($res)$explain=$res;
}
if(isset($_POST["commit_test"])){
    $conn->begin_transaction();
    $s=$conn->prepare("INSERT INTO tbl_kategori(nama_kategori) VALUES(?)");$name="Kategori TCL COMMIT";$s->bind_param("s",$name);
    if($s->execute()){$conn->commit();$testMessage="COMMIT berhasil: data kategori disimpan.";}else{$conn->rollback();$testMessage="Gagal melakukan COMMIT: ".$s->error;}
}
if(isset($_POST["rollback_test"])){
    $conn->begin_transaction();
    $s=$conn->prepare("INSERT INTO tbl_kategori(nama_kategori) VALUES(?)");$name="Kategori TCL ROLLBACK";$s->bind_param("s",$name);
    if($s->execute()){$conn->rollback();$testMessage="ROLLBACK berhasil: data percobaan dibatalkan.";}else{$conn->rollback();$testMessage="Gagal melakukan pengujian: ".$s->error;}
}
?>
<div class="row g-3"><div class="col-lg-6"><div class="card"><div class="card-header">Pengujian TCL</div><div class="card-body"><p class="text-muted">Gunakan tombol untuk melihat perbedaan COMMIT dan ROLLBACK.</p><form method="post" class="d-flex flex-wrap gap-2"><button name="commit_test" class="btn btn-success">Uji COMMIT</button><button name="rollback_test" class="btn btn-outline-warning">Uji ROLLBACK</button></form><?php if($testMessage):?><div class="alert alert-info mt-3 mb-0"><?=e($testMessage)?></div><?php endif;?></div></div></div><div class="col-lg-6"><div class="card"><div class="card-header">EXPLAIN Query</div><div class="card-body"><p class="text-muted">Analisis penggunaan index untuk pencarian transaksi berdasarkan pelanggan.</p><form method="post"><button name="explain" class="btn btn-primary">Jalankan EXPLAIN</button></form><?php if($explain):?><div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><?php foreach($explain->fetch_fields() as $f):?><th><?=e($f->name)?></th><?php endforeach;?></tr></thead><tbody><?php $explain->data_seek(0);while($r=$explain->fetch_assoc()):?><tr><?php foreach($r as $v):?><td><?=e($v)?></td><?php endforeach;?></tr><?php endwhile;?></tbody></table></div><?php endif;?></div></div></div></div>
<?php require "footer.php"; ?>
