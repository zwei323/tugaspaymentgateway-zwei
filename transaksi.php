<?php
ob_start();
$pageTitle = "Transaksi Penjualan";
require "header.php";

$dbMissing = false;
$barcodeEnabled = false;
$customers = $products = $list = false;

/* =========================
   PROSES SIMPAN TRANSAKSI
========================= */
if (isset($_POST["save"])) {
    $pel  = (int)($_POST["id_pelanggan"] ?? 0);
    $prod = (int)($_POST["id_produk"] ?? 0);
    $qty  = (int)($_POST["jumlah"] ?? 0);

    $metode = $_POST["metode_pembayaran"] ?? "Tunai";

    if (!in_array($metode, ["Tunai", "QRIS"], true)) {
        $metode = "Tunai";
    }

    if ($pel > 0 && $prod > 0 && $qty > 0) {
        try {
            // Tetap menggunakan prosedur transaksi yang lama
            $s = $conn->prepare("CALL TambahTransaksi(?,?,?)");
            $s->bind_param("iii", $pel, $prod, $qty);
            $s->execute();
            $s->close();

            // Bersihkan hasil stored procedure
            while ($conn->more_results() && $conn->next_result()) {
                if ($res = $conn->store_result()) {
                    $res->free();
                }
            }

            // Stored procedure inserts one transaction detail; use that row's
            // transaction ID to persist the cashier's selected payment method.
            $lastDetail = $conn->query(
                "SELECT id_transaksi FROM tbl_detail_transaksi
                 WHERE id_detail = LAST_INSERT_ID() LIMIT 1"
            )->fetch_assoc();
            $idTransaksi = (int)($lastDetail['id_transaksi'] ?? 0);
            if ($idTransaksi <= 0) {
                throw new Exception("Transaksi tersimpan, tetapi ID untuk metode pembayaran tidak ditemukan.");
            }

            $stmtMetode = $conn->prepare(
                "UPDATE tbl_transaksi SET pembayaran = ? WHERE id_transaksi = ?"
            );
            $stmtMetode->bind_param("si", $metode, $idTransaksi);
            $stmtMetode->execute();
            $stmtMetode->close();

            flash("Transaksi berhasil disimpan (" . $metode . ").");

        } catch (Throwable $error) {
            error_log("Kasir transaction error: " . $error->getMessage());
            flash("Transaksi gagal: " . $error->getMessage(), "danger");
        }
    } else {
        flash("Pilih pelanggan, produk, dan jumlah yang valid.", "warning");
    }

    redirect("transaksi.php");
}

/* =========================
   AMBIL DATA PELANGGAN
========================= */
try {
    $customers = $conn->query(
        "SELECT * FROM tbl_pelanggan ORDER BY nama_pelanggan"
    );

    if (!$customers) {
        $dbMissing = true;
    }
} catch (Throwable $error) {
    $dbMissing = true;
    error_log("Kasir customers error: " . $error->getMessage());
}

/* =========================
   CEK BARCODE & PRODUK
========================= */
try {
    $checkBarcode = $conn->query(
        "SHOW COLUMNS FROM tbl_produk LIKE 'barcode'"
    );

    $barcodeEnabled = $checkBarcode && $checkBarcode->num_rows > 0;

    if ($barcodeEnabled) {
        $products = $conn->query(
            "SELECT id_produk, nama_produk, harga, stok, barcode
             FROM tbl_produk
             ORDER BY nama_produk"
        );
    } else {
        $products = $conn->query(
            "SELECT id_produk, nama_produk, harga, stok
             FROM tbl_produk
             ORDER BY nama_produk"
        );
    }

    if (!$products) {
        $dbMissing = true;
    }
} catch (Throwable $error) {
    $dbMissing = true;
    error_log("Kasir products error: " . $error->getMessage());
}

/* =========================
   RIWAYAT TRANSAKSI
========================= */
try {
    $list = $conn->query(
        "SELECT t.*, p.nama_pelanggan
         FROM tbl_transaksi t
         LEFT JOIN tbl_pelanggan p
         ON p.id_pelanggan = t.id_pelanggan
         ORDER BY t.id_transaksi DESC
         LIMIT 100"
    );

    if (!$list) {
        $dbMissing = true;
    }
} catch (Throwable $error) {
    $dbMissing = true;
    error_log("Kasir transactions error: " . $error->getMessage());
}
?>

<?php if ($dbMissing): ?>
<div class="alert alert-warning">
    Database belum lengkap. Pastikan tabel
    tbl_produk, tbl_pelanggan, dan tbl_transaksi tersedia.
</div>
<?php endif; ?>

<?php if (!$barcodeEnabled && !$dbMissing): ?>
<div class="alert alert-warning">
    Kolom barcode belum tersedia di tbl_produk.
</div>
<?php endif; ?>

<style>
.tx-page .card {
    height: 100%;
    border: 1px solid #e3edf5;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(40,110,160,.06);
    overflow: hidden;
}
.tx-page .card-header {
    background: #fff;
    border-bottom: 1px solid #eaf1f6;
    padding: 16px 20px;
    font-weight: 800;
    color: #17446b;
}
.tx-page .card-body {
    padding: 20px;
}
.tx-page .form-label {
    font-size: .84rem;
    font-weight: 700;
    color: #475569;
}
.tx-page .form-control,
.tx-page .form-select {
    min-height: 44px;
    border-color: #dbe5ee;
    border-radius: 9px;
}
.tx-page .form-control:focus,
.tx-page .form-select:focus {
    border-color: #2998df;
    box-shadow: 0 0 0 .2rem rgba(41,152,223,.13);
}
.tx-page .table {
    min-width: 620px;
    margin: 0;
}
.tx-page .table th {
    background: #f3f9fe;
    color: #71869a;
    font-size: .78rem;
    border: 0;
    padding: 13px;
}
.tx-page .table td {
    padding: 13px;
    vertical-align: middle;
    font-size: .84rem;
}
.tx-page .tx-submit {
    border: 0;
    border-radius: 9px;
    background: linear-gradient(135deg,#2998df,#1769aa);
    font-weight: 700;
    padding: 11px;
}
.tx-page .scan-tools {
    display: flex;
    gap: 8px;
}
.tx-page .scan-tools .form-control {
    flex: 1;
}
.tx-page .scan-video {
    display: none;
    width: 100%;
    max-height: 260px;
    object-fit: cover;
    border-radius: 10px;
    background: #0f172a;
    margin-top: 10px;
}
.tx-page .scan-status {
    min-height: 20px;
    font-size: .78rem;
}
.tx-page .scan-btn {
    white-space: nowrap;
}

/* METODE PEMBAYARAN */
.payment-option {
    display: flex;
    gap: 10px;
}
.payment-choice {
    flex: 1;
    cursor: pointer;
    position: relative;
}
.payment-choice input {
    position: absolute;
    opacity: 0;
}
.payment-choice .payment-box {
    border: 1px solid #dbe5ee;
    border-radius: 10px;
    padding: 13px 8px;
    text-align: center;
    transition: .2s;
    height: 100%;
    background: white;
}
.payment-choice input:checked + .payment-box {
    border: 2px solid #0d6efd;
    background: #f0f7ff;
    box-shadow: 0 0 0 3px rgba(13,110,253,.08);
}
.payment-choice .payment-box i {
    display: block;
    font-size: 1.4rem;
    margin-bottom: 4px;
}
.payment-choice .payment-box small {
    display: block;
    color: #71869a;
}

/* MODAL QRIS */
.qris-modal {
    display: none;
    position: fixed;
    z-index: 99999;
    inset: 0;
    background: rgba(15,23,42,.65);
    align-items: center;
    justify-content: center;
    padding: 15px;
}
.qris-modal.show {
    display: flex;
}
.qris-dialog {
    background: #fff;
    width: 100%;
    max-width: 430px;
    max-height: 95vh;
    overflow-y: auto;
    border-radius: 18px;
    padding: 22px;
    text-align: center;
    box-shadow: 0 15px 60px rgba(0,0,0,.2);
}
.qris-dialog h4 {
    color: #164a75;
    font-weight: 800;
}
.qris-image {
    width: 100%;
    max-width: 270px;
    aspect-ratio: 1 / 1;
    object-fit: contain;
    border: 1px solid #e5eaf0;
    border-radius: 12px;
    margin: 12px auto;
    padding: 8px;
    background: white;
}
.qris-total {
    font-size: 1.6rem;
    color: #0d6efd;
    font-weight: 800;
}
.qris-note {
    font-size: .85rem;
    color: #64748b;
}
.qris-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
}
.qris-actions button {
    flex: 1;
}
</style>

<div class="tx-page">
<div class="row g-3 mb-4">

    <!-- FORM INPUT TRANSAKSI -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-upc-scan me-2 text-primary"></i>
                Input Transaksi
            </div>

            <div class="card-body">
                <form method="post" id="transactionForm">

                    <input type="hidden" name="save" value="1">

                    <!-- METODE PEMBAYARAN -->
                    <div class="mb-3">
                        <label class="form-label">Metode Pembayaran</label>

                        <div class="payment-option">
                            <label class="payment-choice">
                                <input
                                    type="radio"
                                    name="metode_pembayaran"
                                    value="Tunai"
                                    checked
                                >
                                <div class="payment-box">
                                    <i class="bi bi-cash text-success"></i>
                                    <strong>Tunai</strong>
                                    <small>Uang cash</small>
                                </div>
                            </label>

                            <label class="payment-choice">
                                <input
                                    type="radio"
                                    name="metode_pembayaran"
                                    value="QRIS"
                                >
                                <div class="payment-box">
                                    <i class="bi bi-qr-code text-primary"></i>
                                    <strong>QRIS</strong>
                                    <small>Scan pembayaran</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- SCAN BARCODE -->
                    <div class="mb-3">
                        <label class="form-label" for="scanBarcode">
                            Scan barcode produk
                        </label>

                        <div class="scan-tools">
                            <input
                                type="text"
                                class="form-control"
                                id="scanBarcode"
                                placeholder="Scan barcode lalu Enter"
                                autocomplete="off"
                                <?= !$barcodeEnabled ? 'disabled' : '' ?>
                            >

                            <button
                                type="button"
                                class="btn btn-outline-primary scan-btn"
                                id="cameraToggle"
                                <?= !$barcodeEnabled ? 'disabled' : '' ?>
                            >
                                <i class="bi bi-camera-video"></i>
                                Kamera
                            </button>
                        </div>

                        <video
                            class="scan-video"
                            id="barcodeVideo"
                            playsinline
                            muted
                        ></video>

                        <div class="scan-status text-muted mt-1" id="scanStatus">
                            Scan menggunakan scanner USB atau kamera.
                        </div>
                    </div>

                    <!-- PELANGGAN -->
                    <div class="mb-3">
                        <label class="form-label">Pelanggan</label>

                        <select
                            class="form-select"
                            name="id_pelanggan"
                            required
                        >
                            <option value="">-- Pilih pelanggan --</option>

                            <?php if ($customers):
                                while ($c = $customers->fetch_assoc()):
                            ?>
                            <option value="<?= (int)$c['id_pelanggan'] ?>">
                                <?= e($c['nama_pelanggan']) ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>

                    <!-- PRODUK -->
                    <div class="mb-3">
                        <label class="form-label">Produk</label>

                        <select
                            class="form-select"
                            id="productSelect"
                            name="id_produk"
                            required
                        >
                            <option value="">-- Pilih produk --</option>

                            <?php if ($products):
                                while ($p = $products->fetch_assoc()):
                            ?>
                            <option
                                value="<?= (int)$p['id_produk'] ?>"
                                data-barcode="<?= e($p['barcode'] ?? '') ?>"
                                data-price="<?= (float)$p['harga'] ?>"
                            >
                                <?= e($p['nama_produk']) ?>
                                — <?= rupiah($p['harga']) ?>
                                (stok <?= (int)$p['stok'] ?>)
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>

                    <!-- JUMLAH -->
                    <div class="mb-3">
                        <label class="form-label" for="productQty">
                            Jumlah
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            id="productQty"
                            name="jumlah"
                            min="1"
                            value="1"
                            required
                        >
                    </div>

                    <!-- TOTAL ESTIMASI -->
                    <div class="alert alert-primary py-2 mb-3">
                        <div class="small">Total pembayaran</div>
                        <div class="fs-4 fw-bold" id="transactionTotal">
                            Rp 0
                        </div>
                    </div>

                    <!-- SUBMIT -->
                    <button
                        type="submit"
                        class="btn btn-primary tx-submit w-100"
                        id="submitTransaction"
                    >
                        <i class="bi bi-cart-check me-1"></i>
                        Simpan Transaksi
                    </button>

                    <div class="small text-muted mt-2">
                        <i class="bi bi-shield-check me-1"></i>
                        Stok divalidasi saat transaksi disimpan.
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RIWAYAT TRANSAKSI -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                Riwayat Transaksi
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th>Total</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($list && $list->num_rows):
                        while ($r = $list->fetch_assoc()):
                    ?>
                        <tr>
                            <td>#<?= e($r['id_transaksi']) ?></td>
                            <td><?= e($r['tanggal']) ?></td>
                            <td><?= e($r['nama_pelanggan'] ?? '-') ?></td>
                            <td class="fw-semibold">
                                <?= rupiah($r['total_belanja']) ?>
                            </td>
                            <td class="text-nowrap">
                                <a
                                    href="../detail.php?id=<?= (int)$r['id_transaksi'] ?>"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <i class="bi bi-eye"></i> Detail
                                </a>
                                <a
                                    href="../struk.php?id=<?= (int)$r['id_transaksi'] ?>"
                                    class="btn btn-sm btn-outline-success"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <i class="bi bi-printer"></i> Cetak Struk
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="text-center p-4 text-muted">
                                Belum ada transaksi.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>

<!-- MODAL QRIS -->
<div class="qris-modal" id="qrisModal">
    <div class="qris-dialog">

        <div class="mb-2">
            <i class="bi bi-qr-code text-primary" style="font-size:2rem"></i>
        </div>

        <h4>Pembayaran QRIS</h4>
        <p class="qris-note">
            Silakan scan QRIS di bawah menggunakan aplikasi pembayaran pelanggan.
        </p>

        <div class="qris-total" id="qrisAmount">
            Rp 0
        </div>

        <img
            src="../assets/qris-tokoku.png"
            class="qris-image"
            id="qrisImage"
            alt="QRIS Tokoku"
        >

        <div id="qrisMissing" class="alert alert-warning small" style="display:none">
            Gambar QRIS belum ditemukan. Letakkan QRIS merchant di
            <strong>tokoku/assets/qris-tokoku.png</strong>.
        </div>

        <div class="alert alert-info text-start small mb-2">
            <i class="bi bi-info-circle me-1"></i>
            Pastikan pembayaran sudah masuk ke rekening atau aplikasi merchant
            sebelum menekan tombol konfirmasi.
        </div>

        <div class="qris-actions">
            <button
                type="button"
                class="btn btn-outline-secondary"
                id="cancelQris"
            >
                Batal
            </button>

            <button
                type="button"
                class="btn btn-success"
                id="confirmQris"
            >
                <i class="bi bi-check-circle me-1"></i>
                Pembayaran Diterima
            </button>
        </div>
    </div>
</div>

<!-- LIBRARY ZXING -->
<script src="https://unpkg.com/@zxing/browser@0.1.5/umd/index.min.js"></script>

<script>
(() => {
    const input = document.getElementById('scanBarcode');
    const select = document.getElementById('productSelect');
    const qty = document.getElementById('productQty');
    const statusEl = document.getElementById('scanStatus');
    const cameraButton = document.getElementById('cameraToggle');
    const video = document.getElementById('barcodeVideo');

    const form = document.getElementById('transactionForm');
    const qrisModal = document.getElementById('qrisModal');
    const qrisImage = document.getElementById('qrisImage');
    const qrisMissing = document.getElementById('qrisMissing');
    const qrisAmount = document.getElementById('qrisAmount');
    const cancelQris = document.getElementById('cancelQris');
    const confirmQris = document.getElementById('confirmQris');
    const totalEl = document.getElementById('transactionTotal');

    if (!input || !select || !qty || !statusEl || !video || !form) return;

    let cameraControls = null;
    let cameraActive = false;
    let qrisConfirmed = false;

    /* FORMAT RUPIAH */
    function formatRupiah(value) {
        return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
    }

    /* HITUNG TOTAL ESTIMASI */
    function calculateTotal() {
        const selectedOption = select.options[select.selectedIndex];
        const price = parseFloat(selectedOption?.dataset.price || 0);
        const amount = parseInt(qty.value || 0, 10);
        const total = price * amount;

        totalEl.textContent = formatRupiah(total);
        qrisAmount.textContent = formatRupiah(total);

        return total;
    }

    select.addEventListener('change', calculateTotal);
    qty.addEventListener('input', calculateTotal);

    calculateTotal();

    /* NORMALISASI BARCODE */
    function normalizeBarcode(raw) {
        let code = String(raw || '').trim();
        code = code.replace(/[\r\n\t]/g, '');

        const ean13 = code.match(/^(\d{13})/);
        if (ean13) return ean13[1];

        return code.replace(/\s/g, '');
    }

    function setStatus(message, type = 'muted') {
        statusEl.textContent = message;
        statusEl.className = 'scan-status mt-1 text-' + type;
    }

    /* PROSES BARCODE */
    function processBarcode(raw) {
        const code = normalizeBarcode(raw);
        if (!code) return false;

        const option = Array.from(select.options).find(opt => {
            return opt.value &&
                normalizeBarcode(opt.dataset.barcode || '') === code;
        });

        if (!option) {
            setStatus(
                'Barcode ' + code + ' belum terdaftar pada produk.',
                'danger'
            );
            input.value = '';
            input.focus();
            return false;
        }

        const sameProduct = select.value === option.value;
        select.value = option.value;

        qty.value = sameProduct
            ? Math.max(1, parseInt(qty.value || '1', 10)) + 1
            : 1;

        calculateTotal();

        setStatus(
            option.textContent.trim() +
            ' berhasil dipilih' +
            (sameProduct ? ' (jumlah +1)' : '') +
            '.',
            'success'
        );

        input.value = '';
        input.focus();

        return true;
    }

    /* SCANNER USB */
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            processBarcode(input.value);
        }
    });

    /* MATIKAN KAMERA */
    function stopCamera() {
        if (cameraControls) {
            try {
                cameraControls.stop();
            } catch (e) {}
            cameraControls = null;
        }

        cameraActive = false;

        if (video.srcObject) {
            video.srcObject.getTracks().forEach(track => track.stop());
        }

        video.pause();
        video.srcObject = null;
        video.style.display = 'none';

        cameraButton.innerHTML =
            '<i class="bi bi-camera-video"></i> Kamera';
    }

    /* MULAI KAMERA */
    async function startCamera() {
        if (!window.ZXingBrowser ||
            !window.ZXingBrowser.BrowserMultiFormatReader) {
            setStatus(
                'Library scanner gagal dimuat. Gunakan scanner USB.',
                'danger'
            );
            return;
        }

        if (cameraActive) return;

        try {
            const reader = new ZXingBrowser.BrowserMultiFormatReader();

            video.style.display = 'block';
            setStatus('Meminta izin kamera...', 'muted');
            cameraActive = true;

            cameraControls = await reader.decodeFromVideoDevice(
                undefined,
                video,
                (result, error, controls) => {
                    if (controls && !cameraControls) {
                        cameraControls = controls;
                    }

                    if (result && cameraActive) {
                        const success = processBarcode(result.getText());
                        if (success) stopCamera();
                    }
                }
            );

            cameraButton.innerHTML =
                '<i class="bi bi-stop-circle"></i> Stop';

            setStatus('Kamera aktif. Arahkan barcode ke kamera.', 'success');

        } catch (error) {
            stopCamera();

            setStatus(
                'Kamera gagal dibuka. Izinkan kamera browser.',
                'danger'
            );

            console.error('Barcode camera error:', error);
        }
    }

    cameraButton.addEventListener('click', () => {
        cameraActive ? stopCamera() : startCamera();
    });

    window.addEventListener('pagehide', stopCamera);

    /* =============================
       QRIS
    ============================== */

    function showQris() {
        calculateTotal();

        qrisModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function hideQris() {
        qrisModal.classList.remove('show');
        document.body.style.overflow = '';
    }

    qrisImage.addEventListener('error', () => {
        qrisImage.style.display = 'none';
        qrisMissing.style.display = 'block';
    });

    qrisImage.addEventListener('load', () => {
        qrisImage.style.display = 'block';
        qrisMissing.style.display = 'none';
    });

    cancelQris.addEventListener('click', () => {
        hideQris();
    });

    qrisModal.addEventListener('click', event => {
        if (event.target === qrisModal) {
            hideQris();
        }
    });

    /* FORM SUBMIT */
    form.addEventListener('submit', event => {
        const method = document.querySelector(
            'input[name="metode_pembayaran"]:checked'
        )?.value || 'Tunai';

        // QRIS harus dikonfirmasi dulu
        if (method === 'QRIS' && !qrisConfirmed) {
            event.preventDefault();
            showQris();
            return;
        }

        // Jika sudah konfirmasi QRIS, izinkan submit
        if (qrisConfirmed) {
            qrisConfirmed = false;
        }
    });

    /* KONFIRMASI QRIS */
    confirmQris.addEventListener('click', () => {
        const selectedMethod = document.querySelector(
            'input[name="metode_pembayaran"]:checked'
        );

        if (!selectedMethod || selectedMethod.value !== 'QRIS') {
            hideQris();
            return;
        }

        // Kasir bertanggung jawab memeriksa pembayaran masuk
        qrisConfirmed = true;
        hideQris();

        // Kirim form transaksi
        form.requestSubmit();
    });

})();
</script>

<?php require "footer.php"; ?>
