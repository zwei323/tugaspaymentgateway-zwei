
<?php
$pageTitle = "Laporan Penjualan";

// Panggil koneksi database
require_once __DIR__ . "/config.php";

// Panggil header tampilan

$pageTitle = "Laporan Penjualan";

// ------------------------------------------------------
// VALIDASI FILTER TANGGAL
// ------------------------------------------------------

$mulai = trim($_GET["mulai"] ?? "");
$akhir = trim($_GET["akhir"] ?? "");

$validDate = function ($date) {
    if ($date === "") {
        return true;
    }

    $d = DateTime::createFromFormat("Y-m-d", $date);
    return $d && $d->format("Y-m-d") === $date;
};

if (!$validDate($mulai)) {
    $mulai = "";
}

if (!$validDate($akhir)) {
    $akhir = "";
}

if ($mulai !== "" && $akhir !== "" && $mulai > $akhir) {
    $temp = $mulai;
    $mulai = $akhir;
    $akhir = $temp;
}

// ------------------------------------------------------
// HELPER QUERY FILTER TANGGAL
// ------------------------------------------------------

$runDateQuery = function ($conn, $sql, $mulai, $akhir) {

    $stmt = $conn->prepare($sql);

    if ($mulai !== "" && $akhir !== "") {
        $stmt->bind_param("ss", $mulai, $akhir);
    } elseif ($mulai !== "") {
        $stmt->bind_param("s", $mulai);
    } elseif ($akhir !== "") {
        $stmt->bind_param("s", $akhir);
    }

    $stmt->execute();

    return $stmt->get_result();
};

// ------------------------------------------------------
// KONDISI FILTER
// ------------------------------------------------------

$whereTanggal = "";

if ($mulai !== "" && $akhir !== "") {
    $whereTanggal = " WHERE tanggal BETWEEN ? AND ?";
} elseif ($mulai !== "") {
    $whereTanggal = " WHERE tanggal >= ?";
} elseif ($akhir !== "") {
    $whereTanggal = " WHERE tanggal <= ?";
}

// ------------------------------------------------------
// DATA LAPORAN JOIN
// ------------------------------------------------------

$sqlRows = "
    SELECT *
    FROM view_laporan_penjualan
    $whereTanggal
    ORDER BY tanggal DESC, id_transaksi DESC
";

$rows = $runDateQuery($conn, $sqlRows, $mulai, $akhir);

// ------------------------------------------------------
// TOTAL PENDAPATAN
// ------------------------------------------------------

$sqlSum = "
    SELECT COALESCE(SUM(total_belanja),0) AS total
    FROM tbl_transaksi
    " . str_replace("tanggal", "tanggal", $whereTanggal);

$sumData = $runDateQuery($conn, $sqlSum, $mulai, $akhir)
    ->fetch_assoc();

$sum = (float)($sumData["total"] ?? 0);

// ------------------------------------------------------
// JUMLAH TRANSAKSI
// ------------------------------------------------------

$sqlCount = "
    SELECT COUNT(*) AS total
    FROM tbl_transaksi
    $whereTanggal
";

$countData = $runDateQuery($conn, $sqlCount, $mulai, $akhir)
    ->fetch_assoc();

$totalTransaksi = (int)($countData["total"] ?? 0);

// ------------------------------------------------------
// TOTAL PRODUK TERJUAL
// ------------------------------------------------------

$sqlQty = "
    SELECT COALESCE(SUM(d.jumlah),0) AS total
    FROM tbl_detail_transaksi d
    INNER JOIN tbl_transaksi t
        ON t.id_transaksi = d.id_transaksi
";

if ($mulai !== "" && $akhir !== "") {
    $sqlQty .= " WHERE t.tanggal BETWEEN ? AND ?";
} elseif ($mulai !== "") {
    $sqlQty .= " WHERE t.tanggal >= ?";
} elseif ($akhir !== "") {
    $sqlQty .= " WHERE t.tanggal <= ?";
}

$stmtQty = $conn->prepare($sqlQty);

if ($mulai !== "" && $akhir !== "") {
    $stmtQty->bind_param("ss", $mulai, $akhir);
} elseif ($mulai !== "") {
    $stmtQty->bind_param("s", $mulai);
} elseif ($akhir !== "") {
    $stmtQty->bind_param("s", $akhir);
}

$stmtQty->execute();

$qtyData = $stmtQty->get_result()->fetch_assoc();
$totalProdukTerjual = (int)($qtyData["total"] ?? 0);

// ------------------------------------------------------
// ANALISIS SUBQUERY DI ATAS RATA-RATA
// ------------------------------------------------------

$sqlAbove = "
    SELECT id_transaksi, tanggal, total_belanja
    FROM tbl_transaksi
    $whereTanggal
    AND total_belanja > (
        SELECT AVG(total_belanja)
        FROM tbl_transaksi
        " . ($whereTanggal ? $whereTanggal : "") . "
    )
    ORDER BY total_belanja DESC
";

// Query subquery disesuaikan agar filter tanggal berlaku
if ($mulai !== "" && $akhir !== "") {

    $sqlAbove = "
        SELECT id_transaksi, tanggal, total_belanja
        FROM tbl_transaksi
        WHERE tanggal BETWEEN ? AND ?
        AND total_belanja > (
            SELECT AVG(total_belanja)
            FROM tbl_transaksi
            WHERE tanggal BETWEEN ? AND ?
        )
        ORDER BY total_belanja DESC
    ";

    $stmtAbove = $conn->prepare($sqlAbove);
    $stmtAbove->bind_param("ssss", $mulai, $akhir, $mulai, $akhir);

} elseif ($mulai !== "") {

    $sqlAbove = "
        SELECT id_transaksi, tanggal, total_belanja
        FROM tbl_transaksi
        WHERE tanggal >= ?
        AND total_belanja > (
            SELECT AVG(total_belanja)
            FROM tbl_transaksi
            WHERE tanggal >= ?
        )
        ORDER BY total_belanja DESC
    ";

    $stmtAbove = $conn->prepare($sqlAbove);
    $stmtAbove->bind_param("ss", $mulai, $mulai);

} elseif ($akhir !== "") {

    $sqlAbove = "
        SELECT id_transaksi, tanggal, total_belanja
        FROM tbl_transaksi
        WHERE tanggal <= ?
        AND total_belanja > (
            SELECT AVG(total_belanja)
            FROM tbl_transaksi
            WHERE tanggal <= ?
        )
        ORDER BY total_belanja DESC
    ";

    $stmtAbove = $conn->prepare($sqlAbove);
    $stmtAbove->bind_param("ss", $akhir, $akhir);

} else {

    $sqlAbove = "
        SELECT id_transaksi, tanggal, total_belanja
        FROM tbl_transaksi
        WHERE total_belanja > (
            SELECT AVG(total_belanja)
            FROM tbl_transaksi
        )
        ORDER BY total_belanja DESC
    ";

    $stmtAbove = $conn->prepare($sqlAbove);
}

$stmtAbove->execute();
$above = $stmtAbove->get_result();

// ------------------------------------------------------
// HEADER
// ------------------------------------------------------

require "header.php";
?>

<style>
.report-wrap {
    color: #24364b;
}

.report-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: #17446b;
}

.report-muted {
    color: #71869a;
    font-size: .85rem;
}

.report-card {
    background: #fff;
    border: 1px solid #e3edf5;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(40,110,160,.04);
    overflow: hidden;
}

.report-stat {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    height: 100%;
}

.report-stat .label {
    color: #71869a;
    font-size: .82rem;
}

.report-stat .value {
    font-size: 1.4rem;
    font-weight: 800;
    color: #17446b;
    margin-top: 4px;
}

.report-stat .icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.35rem;
    background: #e8f5ff;
    color: #2998df;
}

.report-head {
    padding: 16px 20px;
    border-bottom: 1px solid #e3edf5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.report-head h5 {
    margin: 0;
    font-weight: 800;
    color: #17446b;
}

.report-table {
    min-width: 850px;
}

.report-table th {
    background: #f3f9fe;
    color: #71869a;
    font-size: .78rem;
    font-weight: 700;
    padding: 13px;
    white-space: nowrap;
}

.report-table td {
    font-size: .82rem;
    padding: 12px;
    vertical-align: middle;
}

.report-table tbody tr:hover {
    background: #f7fbff;
}

.filter-box {
    padding: 18px;
}

.print-header {
    display: none;
}

@media(max-width:576px) {
    .report-title {
        font-size: 1.25rem;
    }

    .report-stat .value {
        font-size: 1.15rem;
    }
}

/* ======================================
   TAMPILAN CETAK
====================================== */

@media print {

    @page {
        size: landscape;
        margin: 12mm;
    }

    body {
        background: #fff !important;
        color: #000 !important;
        font-size: 10pt;
    }

    .sidebar,
    .navbar,
    nav,
    header,
    footer,
    .page-actions,
    .no-print,
    .btn,
    form,
    .alert,
    .stat-icon {
        display: none !important;
    }

    .container,
    .container-fluid,
    .main-content,
    .content,
    .report-wrap {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .report-card,
    .card {
        border: 1px solid #ccc !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        break-inside: avoid;
        margin-bottom: 12px !important;
    }

    .report-head {
        padding: 8px 10px !important;
    }

    .report-table {
        min-width: 0 !important;
        width: 100% !important;
    }

    .report-table th,
    .report-table td {
        color: #000 !important;
        padding: 7px !important;
        font-size: 9pt !important;
        border: 1px solid #ccc !important;
    }

    .report-table th {
        background: #eee !important;
    }

    .report-stat {
        padding: 12px !important;
    }

    .report-stat .value {
        color: #000 !important;
        font-size: 15pt !important;
    }

    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
    }

    .print-header h2 {
        margin: 0;
        font-weight: bold;
    }

    .print-header p {
        margin: 4px 0;
    }

    .print-date {
        text-align: right;
        font-size: 9pt;
        margin-bottom: 10px;
    }

    a {
        text-decoration: none !important;
        color: #000 !important;
    }
}
</style>

<div class="report-wrap">

    <!-- HEADER LAPORAN CETAK -->
    <div class="print-header">
        <h2>TOKOKU</h2>
        <p>Laporan Penjualan</p>

        <p>
            Periode:
            <?= $mulai ? e($mulai) : "Semua tanggal" ?>
            s/d
            <?= $akhir ? e($akhir) : "Semua tanggal" ?>
        </p>

        <div class="print-date">
            Dicetak: <?=date("d-m-Y H:i")?>
        </div>
    </div>

    <!-- HEADER HALAMAN -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 no-print">

        <div>
            <h3 class="report-title mb-1">
                <i class="bi bi-bar-chart-line text-primary"></i>
                Laporan Penjualan
            </h3>

            <div class="report-muted">
                Analisis dan rekap data transaksi Tokoku
            </div>
        </div>

        <button type="button"
                class="btn btn-primary"
                onclick="window.print()">
            <i class="bi bi-printer me-1"></i>
            Cetak Laporan
        </button>

    </div>

    <!-- FILTER TANGGAL -->
    <div class="report-card mb-3 no-print">

        <div class="report-head">
            <h5>
                <i class="bi bi-funnel text-primary"></i>
                Filter Laporan
            </h5>
        </div>

        <div class="filter-box">

            <form method="GET" class="row g-3 align-items-end">

                <div class="col-12 col-md-4">
                    <label class="form-label">Tanggal Mulai</label>

                    <input type="date"
                           name="mulai"
                           class="form-control"
                           value="<?=e($mulai)?>">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Tanggal Akhir</label>

                    <input type="date"
                           name="akhir"
                           class="form-control"
                           value="<?=e($akhir)?>">
                </div>

                <div class="col-12 col-md-4 d-flex gap-2">

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                        Terapkan
                    </button>

                    <a href="laporan.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Reset
                    </a>

                </div>

            </form>

        </div>

    </div>

    <!-- RINGKASAN -->
    <div class="row g-3 mb-3">

        <div class="col-12 col-md-4">

            <div class="report-card report-stat">

                <div>
                    <div class="label">Total Pendapatan</div>

                    <div class="value">
                        <?=rupiah($sum)?>
                    </div>
                </div>

                <div class="icon">
                    <i class="bi bi-cash-stack"></i>
                </div>

            </div>

        </div>

        <div class="col-12 col-md-4">

            <div class="report-card report-stat">

                <div>
                    <div class="label">Total Transaksi</div>

                    <div class="value">
                        <?=number_format($totalTransaksi,0,",",".")?>
                    </div>
                </div>

                <div class="icon">
                    <i class="bi bi-receipt"></i>
                </div>

            </div>

        </div>

        <div class="col-12 col-md-4">

            <div class="report-card report-stat">

                <div>
                    <div class="label">Produk Terjual</div>

                    <div class="value">
                        <?=number_format($totalProdukTerjual,0,",",".")?>
                    </div>
                </div>

                <div class="icon">
                    <i class="bi bi-box-seam"></i>
                </div>

            </div>

        </div>

    </div>

    <!-- TABEL LAPORAN JOIN -->
    <div class="report-card mb-4">

        <div class="report-head">

            <h5>
                <i class="bi bi-table text-primary"></i>
                Detail Laporan Penjualan (JOIN)
            </h5>

            <span class="report-muted">
                <?= $rows->num_rows ?> baris data
            </span>

        </div>

        <div class="table-responsive">

            <table class="table report-table mb-0">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Produk</th>
                        <th>Kategori</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                        <th>Total Transaksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if($rows && $rows->num_rows): ?>

                    <?php while($r = $rows->fetch_assoc()): ?>

                        <tr>

                            <td>
                                #<?=e($r["id_transaksi"])?>
                            </td>

                            <td>
                                <?=e($r["tanggal"])?>
                            </td>

                            <td>
                                <?=e($r["nama_pelanggan"] ?? "Umum")?>
                            </td>

                            <td>
                                <?=e($r["nama_produk"])?>
                            </td>

                            <td>
                                <?=e($r["nama_kategori"] ?? "-")?>
                            </td>

                            <td>
                                <?=e($r["jumlah"])?>
                            </td>

                            <td>
                                <?=rupiah($r["subtotal"])?>
                            </td>

                            <td class="fw-bold">
                                <?=rupiah($r["total_belanja"])?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="text-center p-4 text-muted">
                            Tidak ada data transaksi pada periode ini.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- ANALISIS SUBQUERY -->
    <div class="report-card mb-4">

        <div class="report-head">

            <h5>
                <i class="bi bi-graph-up-arrow text-primary"></i>
                Analisis Subquery: Transaksi Di Atas Rata-rata
            </h5>

        </div>

        <div class="table-responsive">

            <table class="table report-table mb-0">

                <thead>
                    <tr>
                        <th>ID Transaksi</th>
                        <th>Tanggal</th>
                        <th>Total Transaksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if($above && $above->num_rows): ?>

                    <?php while($r = $above->fetch_assoc()): ?>

                        <tr>

                            <td>
                                #<?=e($r["id_transaksi"])?>
                            </td>

                            <td>
                                <?=e($r["tanggal"])?>
                            </td>

                            <td class="fw-bold text-primary">
                                <?=rupiah($r["total_belanja"])?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="3" class="text-center p-4 text-muted">
                            Tidak ada transaksi di atas rata-rata pada periode ini.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- TANDA TANGAN CETAK -->
    <div class="d-none d-print-block mt-4">

        <div class="row">

            <div class="col-8"></div>

            <div class="col-4 text-center">

                <p>
                    Dicetak pada <?=date("d M Y")?>
                </p>

                <br><br><br>

                <p>
                    <strong>Penanggung Jawab</strong>
                </p>

            </div>

        </div>

    </div>

</div>

<?php require "footer.php"; ?>
