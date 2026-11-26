<?php
ob_start();
$pageTitle = "Transaksi Penjualan";
require "header.php";

/*
|--------------------------------------------------------------------------
| TOKOKU - TRANSAKSI KASIR + QR DANA
|--------------------------------------------------------------------------
| Menggunakan:
| - tbl_pelanggan
| - tbl_produk
| - tbl_transaksi
| - Stored Procedure TambahTransaksi(?,?,?)
|
| QRIS/DANA:
| - QR statis
| - Pembayaran diverifikasi manual oleh kasir
| - Metode pembayaran belum disimpan ke DB karena SP lama
|   hanya menerima id_pelanggan, id_produk, jumlah.
|--------------------------------------------------------------------------
*/

$dbMissing = false;
$barcodeEnabled = false;
$customers = [];
$products = [];
$recent = [];

/* ==========================================
   PROSES SIMPAN TRANSAKSI
========================================== */

if (isset($_POST["save"])) {

    $pel  = (int)($_POST["id_pelanggan"] ?? 0);
    $prod = (int)($_POST["id_produk"] ?? 0);
    $qty  = (int)($_POST["jumlah"] ?? 0);

    $metode = $_POST["metode_pembayaran"] ?? "Tunai";
    $konfirmasiQR = $_POST["konfirmasi_qris"] ?? "";

    // Validasi dasar
    if ($pel <= 0 || $prod <= 0 || $qty <= 0) {
        $_SESSION["flash"] = [
            "message" => "Pelanggan, produk, dan jumlah harus diisi.",
            "type" => "danger"
        ];

        header("Location: transaksi.php");
        exit;
    }

    // Validasi metode pembayaran
    if (!in_array($metode, ["Tunai", "QRIS"], true)) {
        $metode = "Tunai";
    }

    // QRIS wajib dikonfirmasi kasir
    if ($metode === "QRIS" && $konfirmasiQR !== "1") {
        $_SESSION["flash"] = [
            "message" => "Pembayaran QRIS belum dikonfirmasi.",
            "type" => "warning"
        ];

        header("Location: transaksi.php");
        exit;
    }

    try {

        // Simpan menggunakan stored procedure lama
        $stmt = $conn->prepare("CALL TambahTransaksi(?,?,?)");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("iii", $pel, $prod, $qty);
        $stmt->execute();
        $stmt->close();

        // Bersihkan hasil stored procedure
        while ($conn->more_results()) {
            $conn->next_result();

            $result = $conn->store_result();

            if ($result instanceof mysqli_result) {
                $result->free();
            }
        }

        $_SESSION["flash"] = [
            "message" => "Transaksi berhasil disimpan! Metode pembayaran: " . $metode,
            "type" => "success"
        ];

        header("Location: transaksi.php");
        exit;

    } catch (Throwable $error) {

        error_log("Transaksi Tokoku: " . $error->getMessage());

        $_SESSION["flash"] = [
            "message" => "Transaksi gagal disimpan: " . $error->getMessage(),
            "type" => "danger"
        ];

        header("Location: transaksi.php");
        exit;
    }
}

/* ==========================================
   DATA PELANGGAN
========================================== */

try {

    $q = $conn->query(
        "SELECT id_pelanggan, nama_pelanggan
         FROM tbl_pelanggan
         ORDER BY nama_pelanggan ASC"
    );

    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $customers[] = $row;
        }
    }

} catch (Throwable $error) {
    $dbMissing = true;
    error_log("Pelanggan: " . $error->getMessage());
}

/* ==========================================
   DATA PRODUK
========================================== */

try {

    // Mencoba mengambil produk dengan barcode
    $q = $conn->query(
        "SELECT id_produk, nama_produk, harga, barcode
         FROM tbl_produk
         ORDER BY nama_produk ASC"
    );

    $barcodeEnabled = true;

} catch (Throwable $error) {

    // Jika kolom barcode belum ada
    try {

        $q = $conn->query(
            "SELECT id_produk, nama_produk, harga
             FROM tbl_produk
             ORDER BY nama_produk ASC"
        );

        $barcodeEnabled = false;

    } catch (Throwable $error2) {

        $q = false;
        $dbMissing = true;

        error_log("Produk: " . $error2->getMessage());
    }
}

if ($q) {
    while ($row = $q->fetch_assoc()) {
        $products[] = $row;
    }
}

/* ==========================================
   TRANSAKSI TERBARU
========================================== */

try {

    $q = $conn->query(
        "SELECT
            t.id_transaksi,
            t.tanggal,
            t.total_belanja,
            p.nama_pelanggan
         FROM tbl_transaksi t
         LEFT JOIN tbl_pelanggan p
            ON p.id_pelanggan = t.id_pelanggan
         ORDER BY t.id_transaksi DESC
         LIMIT 10"
    );

    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $recent[] = $row;
        }
    }

} catch (Throwable $error) {

    error_log("Riwayat transaksi: " . $error->getMessage());
}

/* ==========================================
   FUNGSI FORMAT
========================================== */

if (!function_exists("e")) {
    function e($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            "UTF-8"
        );
    }
}

if (!function_exists("rupiah")) {
    function rupiah($angka) {
        return "Rp " . number_format(
            (float)$angka,
            0,
            ",",
            "."
        );
    }
}
?>

<style>
.tx-wrap {
    max-width: 1500px;
    margin: auto;
    font-family: "Nunito", sans-serif;
    color: #24364b;
}

.tx-title {
    font-size: 1.55rem;
    font-weight: 800;
    color: #17446b;
}

.tx-sub {
    color: #71869a;
    font-size: .85rem;
}

.tx-card {
    background: #fff;
    border: 1px solid #e3edf5;
    border-radius: 15px;
    box-shadow: 0 4px 16px rgba(30,100,150,.05);
    overflow: hidden;
}

.tx-card-header {
    padding: 17px 20px;
    border-bottom: 1px solid #e5eef5;
    font-weight: 800;
    color: #17446b;
}

.tx-card-body {
    padding: 20px;
}

.tx-label {
    font-size: .83rem;
    font-weight: 700;
    color: #47627c;
    margin-bottom: 7px;
}

.tx-input {
    min-height: 45px;
    border-radius: 9px;
}

.tx-total {
    background: linear-gradient(135deg,#2499df,#1769aa);
    color: white;
    border-radius: 13px;
    padding: 20px;
}

.tx-total .amount {
    font-size: 1.8rem;
    font-weight: 900;
}

.tx-payment-option {
    border: 1px solid #dceaf5;
    border-radius: 11px;
    padding: 15px;
    cursor: pointer;
    transition: .2s;
    height: 100%;
}

.tx-payment-option:hover {
    background: #f4faff;
    border-color: #2998df;
}

.tx-payment-option:has(input:checked) {
    border: 2px solid #2998df;
    background: #edf8ff;
}

.tx-payment-option input {
    margin-right: 7px;
}

.tx-payment-option .payment-icon {
    font-size: 1.35rem;
    margin-right: 8px;
}

.tx-btn {
    border-radius: 9px;
    font-weight: 700;
    padding: 11px 16px;
}

.tx-table {
    min-width: 600px;
}

.tx-table th {
    background: #f2f8fd;
    color: #69839b;
    font-size: .77rem;
    padding: 13px;
    white-space: nowrap;
}

.tx-table td {
    font-size: .83rem;
    padding: 12px;
    vertical-align: middle;
}

.qr-image {
    width: 100%;
    max-width: 330px;
    border-radius: 12px;
    border: 1px solid #e5e5e5;
    padding: 6px;
    background: white;
}

.qr-note {
    font-size: .82rem;
    color: #71869a;
}

@media(max-width:576px) {
    .tx-title {
        font-size: 1.25rem;
    }

    .tx-card-body {
        padding: 15px;
    }

    .tx-total .amount {
        font-size: 1.45rem;
    }
}
</style>

<div class="tx-wrap">

    <!-- FLASH MESSAGE -->
    <?php if (!empty($_SESSION["flash"])): ?>
        <div class="alert alert-<?= e($_SESSION["flash"]["type"]) ?> alert-dismissible fade show">
            <?= e($_SESSION["flash"]["message"]) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION["flash"]); ?>
    <?php endif; ?>

    <?php if ($dbMissing): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Sebagian data database tidak berhasil dimuat.
            Periksa koneksi database dan tabel Tokoku.
        </div>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">

        <div>
            <h2 class="tx-title mb-1">
                <i class="bi bi-cart-check me-2"></i>
                Transaksi Penjualan
            </h2>

            <div class="tx-sub">
                Kelola transaksi penjualan dan pembayaran pelanggan.
            </div>
        </div>

        <div class="text-muted small">
            <i class="bi bi-calendar3 me-1"></i>
            <?= date("d F Y") ?>
        </div>

    </div>

    <!-- FORM TRANSAKSI -->
    <div class="tx-card mb-4">

        <div class="tx-card-header">
            <i class="bi bi-receipt me-2 text-primary"></i>
            Form Transaksi Baru
        </div>

        <div class="tx-card-body">

            <form method="POST"
                  id="formTransaksi"
                  action="transaksi.php">

                <input type="hidden" name="save" value="1">

                <input type="hidden"
                       name="konfirmasi_qris"
                       id="konfirmasi_qris"
                       value="0">

                <!-- PELANGGAN -->
                <div class="mb-3">

                    <label class="tx-label">
                        Pelanggan
                    </label>

                    <select name="id_pelanggan"
                            id="id_pelanggan"
                            class="form-select tx-input"
                            required>

                        <option value="">-- Pilih Pelanggan --</option>

                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c["id_pelanggan"] ?>">
                                <?= e($c["nama_pelanggan"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- BARCODE -->
                <div class="mb-3">

                    <label class="tx-label">
                        Barcode Produk
                    </label>

                    <div class="input-group">

                        <input type="text"
                               id="barcodeInput"
                               class="form-control tx-input"
                               placeholder="Scan barcode produk di sini"
                               autocomplete="off">

                        <button type="button"
                                class="btn btn-outline-primary"
                                id="btnCamera">

                            <i class="bi bi-camera me-1"></i>
                            Kamera

                        </button>

                    </div>

                    <div class="form-text">
                        Gunakan scanner barcode USB atau kamera.
                        Scanner USB biasanya otomatis menekan Enter.
                    </div>

                </div>

                <!-- CAMERA SCANNER -->
                <div id="cameraArea" class="d-none mb-3">

                    <div class="border rounded p-2 bg-light">

                        <div class="d-flex justify-content-between align-items-center mb-2">

                            <strong class="small">
                                Scan Barcode Kamera
                            </strong>

                            <button type="button"
                                    class="btn btn-sm btn-danger"
                                    id="btnStopCamera">
                                Tutup Kamera
                            </button>

                        </div>

                        <div id="reader"
                             style="width:100%;max-width:500px;margin:auto">
                        </div>

                    </div>

                </div>

                <!-- PRODUK -->
                <div class="mb-3">

                    <label class="tx-label">
                        Produk
                    </label>

                    <select name="id_produk"
                            id="id_produk"
                            class="form-select tx-input"
                            required>

                        <option value="">-- Pilih Produk --</option>

                        <?php foreach ($products as $p): ?>

                            <option
                                value="<?= (int)$p["id_produk"] ?>"
                                data-price="<?= (float)$p["harga"] ?>"
                                data-barcode="<?= e($p["barcode"] ?? "") ?>">

                                <?= e($p["nama_produk"]) ?>
                                — <?= e(rupiah($p["harga"])) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- JUMLAH -->
                <div class="mb-4">

                    <label class="tx-label">
                        Jumlah
                    </label>

                    <input type="number"
                           name="jumlah"
                           id="jumlah"
                           class="form-control tx-input"
                           min="1"
                           value="1"
                           required>

                </div>

                <!-- TOTAL -->
                <div class="tx-total mb-4">

                    <div class="small opacity-75">
                        Total Belanja
                    </div>

                    <div class="amount" id="totalBelanja">
                        Rp 0
                    </div>

                    <div class="small opacity-75 mt-1">
                        Harga produk × jumlah
                    </div>

                </div>

                <!-- METODE PEMBAYARAN -->
                <div class="mb-4">

                    <label class="tx-label mb-2">
                        Metode Pembayaran
                    </label>

                    <div class="row g-3">

                        <div class="col-6">

                            <label class="tx-payment-option d-block">

                                <input type="radio"
                                       name="metode_pembayaran"
                                       value="Tunai"
                                       checked>

                                <i class="bi bi-cash-stack payment-icon text-success"></i>

                                <strong>Tunai</strong>

                                <div class="small text-muted mt-1">
                                    Pembayaran langsung
                                </div>

                            </label>

                        </div>

                        <div class="col-6">

                            <label class="tx-payment-option d-block">

                                <input type="radio"
                                       name="metode_pembayaran"
                                       value="QRIS">

                                <i class="bi bi-qr-code payment-icon text-primary"></i>

                                <strong>QRIS / DANA</strong>

                                <div class="small text-muted mt-1">
                                    Scan QR pembayaran
                                </div>

                            </label>

                        </div>

                    </div>

                </div>

                <!-- BUTTON -->
                <div class="d-flex flex-wrap gap-2">

                    <button type="submit"
                            id="btnTunai"
                            class="btn btn-success tx-btn">

                        <i class="bi bi-check-circle me-1"></i>
                        Simpan Transaksi Tunai

                    </button>

                    <button type="button"
                            id="btnQR"
                            class="btn btn-primary tx-btn"
                            onclick="bukaQR()">

                        <i class="bi bi-qr-code me-1"></i>
                        Bayar dengan QR DANA

                    </button>

                    <button type="reset"
                            class="btn btn-light border tx-btn"
                            id="btnReset">

                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Reset

                    </button>

                </div>

            </form>

        </div>

    </div>

    <!-- RIWAYAT TRANSAKSI -->
    <div class="tx-card">

        <div class="tx-card-header d-flex justify-content-between align-items-center">

            <span>
                <i class="bi bi-clock-history me-2 text-primary"></i>
                Transaksi Terbaru
            </span>

            <span class="badge bg-primary">
                10 Terbaru
            </span>

        </div>

        <div class="table-responsive">

            <table class="table tx-table mb-0">

                <thead>
                    <tr>
                        <th>ID Transaksi</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($recent)): ?>

                        <?php foreach ($recent as $r): ?>

                            <tr>

                                <td class="fw-bold text-primary">
                                    #<?= e($r["id_transaksi"]) ?>
                                </td>

                                <td>
                                    <?= e($r["tanggal"]) ?>
                                </td>

                                <td>
                                    <i class="bi bi-person-circle text-info me-1"></i>
                                    <?= e($r["nama_pelanggan"] ?? "Umum") ?>
                                </td>

                                <td class="text-end fw-bold">
                                    <?= e(rupiah($r["total_belanja"])) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                Belum ada transaksi.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- ==========================================
     MODAL PEMBAYARAN QR DANA
========================================== -->

<div class="modal fade"
     id="modalQRDANA"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content border-0 shadow rounded-4">

            <div class="modal-header">

                <h5 class="modal-title fw-bold">
                    <i class="bi bi-qr-code text-primary me-2"></i>
                    Pembayaran QR DANA
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body text-center">

                <div class="small text-muted mb-2">
                    Scan QR berikut menggunakan DANA
                </div>

                <!-- QR DANA ASLI -->
                <img src="../assets/qris-dana.jpg"
                     alt="QR Profil DANA"
                     class="qr-image"
                     onerror="this.onerror=null;this.src='../assets/qris-dana.jpg';">

                <div class="mt-3">

                    <div class="text-muted small">
                        Total pembayaran
                    </div>

                    <div class="fs-3 fw-bold text-primary"
                         id="qrTotal">
                        Rp 0
                    </div>

                </div>

                <div class="alert alert-info text-start mt-3 mb-0 small">

                    <i class="bi bi-info-circle me-1"></i>

                    Scan QR menggunakan aplikasi DANA.
                    Pastikan nama penerima dan nominal sesuai.
                    Kasir harus mengecek uang masuk sebelum
                    mengonfirmasi transaksi.

                </div>

                <div class="qr-note mt-3">
                    QR ini bersifat statis, sehingga nominal
                    tidak terisi otomatis di aplikasi pembayaran.
                </div>

            </div>

            <div class="modal-footer d-flex flex-column">

                <button type="button"
                        class="btn btn-success tx-btn w-100"
                        onclick="konfirmasiDANA()">

                    <i class="bi bi-check-circle me-1"></i>
                    Pembayaran Sudah Diterima

                </button>

                <button type="button"
                        class="btn btn-light border tx-btn w-100"
                        data-bs-dismiss="modal">

                    Batalkan / Kembali

                </button>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================
     JAVASCRIPT BARCODE + PEMBAYARAN
========================================== -->

<script src="https://unpkg.com/@zxing/browser@latest"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {

    const form = document.getElementById("formTransaksi");

    const pelanggan = document.getElementById("id_pelanggan");
    const produk = document.getElementById("id_produk");
    const jumlah = document.getElementById("jumlah");

    const barcodeInput = document.getElementById("barcodeInput");

    const totalBelanja = document.getElementById("totalBelanja");
    const qrTotal = document.getElementById("qrTotal");

    const btnTunai = document.getElementById("btnTunai");
    const btnQR = document.getElementById("btnQR");

    const metodeInputs = document.querySelectorAll(
        'input[name="metode_pembayaran"]'
    );

    const konfirmasiQR = document.getElementById("konfirmasi_qris");

    /* ==================================
       FORMAT RUPIAH
    ================================== */

    function formatRupiah(angka) {
        return "Rp " + Number(angka || 0).toLocaleString("id-ID");
    }

    /* ==================================
       HITUNG TOTAL
    ================================== */

    function hitungTotal() {

        const selected = produk.options[produk.selectedIndex];

        const harga = selected
            ? Number(selected.dataset.price || 0)
            : 0;

        const qty = Number(jumlah.value || 0);

        const total = harga * qty;

        totalBelanja.textContent = formatRupiah(total);
        qrTotal.textContent = formatRupiah(total);
    }

    produk.addEventListener("change", hitungTotal);
    jumlah.addEventListener("input", hitungTotal);

    hitungTotal();

    /* ==================================
       METODE PEMBAYARAN
    ================================== */

    function updateMetode() {

        const metode = document.querySelector(
            'input[name="metode_pembayaran"]:checked'
        );

        if (!metode) return;

        if (metode.value === "Tunai") {

            btnTunai.classList.remove("d-none");
            btnQR.classList.add("d-none");

        } else {

            btnTunai.classList.add("d-none");
            btnQR.classList.remove("d-none");

        }
    }

    metodeInputs.forEach(input => {
        input.addEventListener("change", updateMetode);
    });

    updateMetode();

    /* ==================================
       BUKA MODAL QR
    ================================== */

    window.bukaQR = function() {

        if (!pelanggan.value) {
            alert("Silakan pilih pelanggan terlebih dahulu.");
            pelanggan.focus();
            return;
        }

        if (!produk.value) {
            alert("Silakan pilih produk terlebih dahulu.");
            produk.focus();
            return;
        }

        if (Number(jumlah.value) < 1) {
            alert("Jumlah produk minimal 1.");
            jumlah.focus();
            return;
        }

        hitungTotal();

        const modalElement = document.getElementById("modalQRDANA");

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

        modal.show();
    };

    /* ==================================
       KONFIRMASI DANA
    ================================== */

    window.konfirmasiDANA = function() {

        const selected = produk.options[produk.selectedIndex];

        if (!pelanggan.value || !produk.value || Number(jumlah.value) < 1) {
            alert("Data transaksi belum lengkap.");
            return;
        }

        const total = Number(selected.dataset.price || 0) *
                     Number(jumlah.value || 0);

        if (total <= 0) {
            alert("Total pembayaran tidak valid.");
            return;
        }

        const yakin = confirm(
            "Pastikan pembayaran DANA sebesar " +
            formatRupiah(total) +
            " sudah masuk.\n\n" +
            "Apakah pembayaran sudah diterima?"
        );

        if (!yakin) return;

        // Tandai bahwa kasir telah mengonfirmasi QR
        konfirmasiQR.value = "1";

        // Metode QRIS
        const qrisRadio = document.querySelector(
            'input[name="metode_pembayaran"][value="QRIS"]'
        );

        if (qrisRadio) {
            qrisRadio.checked = true;
        }

        // Tutup modal
        const modalElement = document.getElementById("modalQRDANA");

        const modal = bootstrap.Modal.getInstance(modalElement);

        if (modal) modal.hide();

        // Submit form transaksi
        form.requestSubmit();
    };

    /* ==================================
       RESET FORM
    ================================== */

    document.getElementById("btnReset").addEventListener("click", function() {

        setTimeout(function() {
            konfirmasiQR.value = "0";
            hitungTotal();
            updateMetode();
            barcodeInput.value = "";
        }, 10);

    });

    /* ==================================
       BARCODE USB
    ================================== */

    barcodeInput.addEventListener("keydown", function(event) {

        if (event.key !== "Enter") return;

        event.preventDefault();

        const barcode = barcodeInput.value.trim();

        if (!barcode) return;

        cariBarcode(barcode);

    });

    function cariBarcode(barcode) {

        let ditemukan = false;

        for (let i = 0; i < produk.options.length; i++) {

            const option = produk.options[i];

            const dataBarcode = (option.dataset.barcode || "").trim();

            if (dataBarcode === barcode) {

                produk.selectedIndex = i;

                // Jika produk sama telah dipilih, tambah jumlah
                if (jumlah.value && Number(jumlah.value) > 0) {
                    jumlah.value = Number(jumlah.value) + 1;
                } else {
                    jumlah.value = 1;
                }

                hitungTotal();

                ditemukan = true;

                break;
            }
        }

        if (!ditemukan) {
            alert("Barcode tidak ditemukan: " + barcode);
        }

        barcodeInput.value = "";
        barcodeInput.focus();
    }

    /* ==================================
       SCANNER KAMERA ZXING
    ================================== */

    const cameraArea = document.getElementById("cameraArea");
    const reader = document.getElementById("reader");

    let codeReader = null;
    let controls = null;

    document.getElementById("btnCamera").addEventListener("click", async function() {

        cameraArea.classList.remove("d-none");

        if (typeof ZXingBrowser === "undefined") {
            alert("Library barcode tidak berhasil dimuat. Periksa internet.");
            return;
        }

        try {

            codeReader = new ZXingBrowser.BrowserMultiFormatReader();

            const devices = await ZXingBrowser.BrowserCodeReader.listVideoInputDevices();

            if (!devices.length) {
                alert("Kamera tidak ditemukan.");
                return;
            }

            const selectedCamera = devices[devices.length - 1];

            controls = await codeReader.decodeFromVideoDevice(
                selectedCamera.deviceId,
                "video",
                function(result, error, control) {

                    if (result) {

                        const barcode = result.getText();

                        control.stop();

                        controls = null;

                        cameraArea.classList.add("d-none");

                        cariBarcode(barcode);
                    }
                }
            );

        } catch (error) {

            console.error(error);

            alert(
                "Kamera tidak dapat dibuka. " +
                "Pastikan izin kamera diberikan dan browser mendukung kamera."
            );
        }
    });

    document.getElementById("btnStopCamera").addEventListener("click", function() {

        if (controls) {
            controls.stop();
            controls = null;
        }

        cameraArea.classList.add("d-none");
    });

});
</script>

<?php
require "footer.php";
?>