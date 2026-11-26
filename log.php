<?php
$pageTitle="Log Aktivitas"; require "header.php";
$rows=$conn->query("SELECT * FROM tbl_log ORDER BY id_log DESC LIMIT 200");
?>
<div class="card"><div class="card-header">Riwayat aktivitas Trigger</div><div class="table-responsive"><table class="table"><thead><tr><th>ID Log</th><th>Aktivitas</th><th>Keterangan</th><th>Waktu</th></tr></thead><tbody><?php if($rows && $rows->num_rows):while($r=$rows->fetch_assoc()):?><tr><td><?=e($r["id_log"])?></td><td><span class="badge badge-soft"><?=e($r["aktivitas"])?></span></td><td><?=e($r["keterangan"])?></td><td><?=e($r["waktu"])?></td></tr><?php endwhile;else:?><tr><td colspan="4" class="text-center p-4 text-muted">Belum ada aktivitas tercatat.</td></tr><?php endif;?></tbody></table></div></div>
<?php require "footer.php"; ?>
