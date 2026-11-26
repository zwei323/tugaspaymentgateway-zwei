
<?php
ob_start(); // Menahan output HTML agar redirect masih dapat dilakukan

$pageTitle = "Data Pelanggan";
require "header.php";

/* =====================================
   TAMBAH PELANGGAN + RFID
===================================== */
if (isset($_POST["save"])) {

    $nama  = trim($_POST["nama_pelanggan"] ?? "");
    $kota  = trim($_POST["kota"] ?? "");
    $rfid  = trim($_POST["rfid_uid"] ?? "");

    // RFID kosong diperbolehkan, pelanggan bisa mendaftar tanpa kartu
    if ($rfid === "") {
        $rfid = null;
    }

    if ($nama === "") {

        flash("Nama pelanggan wajib diisi.", "warning");

    } elseif (mb_strlen($nama) > 100 || mb_strlen($kota) > 50) {

        flash("Nama atau kota terlalu panjang.", "warning");

    } elseif ($rfid !== null && mb_strlen($rfid) > 50) {

        flash("UID RFID maksimal 50 karakter.", "warning");

    } else {

        try {

            $stmt = $conn->prepare("
                INSERT INTO tbl_pelanggan
                    (nama_pelanggan, kota, rfid_uid)
                VALUES (?, ?, ?)
            ");

            $stmt->bind_param("sss", $nama, $kota, $rfid);
            $stmt->execute();

            flash("Pelanggan berhasil ditambahkan.");

        } catch (Throwable $error) {

            error_log("Tambah pelanggan error: " . $error->getMessage());

            if (
                str_contains(
                    strtolower($error->getMessage()),
                    "duplicate"
                )
            ) {
                flash("UID RFID sudah digunakan pelanggan lain.", "danger");
            } else {
                flash("Gagal menyimpan pelanggan. Periksa database.", "danger");
            }
        }
    }

    redirect("pelanggan.php");
}

/* =====================================
   HAPUS PELANGGAN
===================================== */
if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    try {

        $stmt = $conn->prepare("
            DELETE FROM tbl_pelanggan
            WHERE id_pelanggan = ?
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        flash("Pelanggan berhasil dihapus.");

    } catch (Throwable $error) {

        error_log("Hapus pelanggan error: " . $error->getMessage());

        flash(
            "Pelanggan tidak dapat dihapus karena memiliki transaksi.",
            "danger"
        );
    }

    redirect("pelanggan.php");
}

/* =====================================
   DATA PELANGGAN
===================================== */
$list = $conn->query("
    SELECT *
    FROM tbl_pelanggan
    ORDER BY id_pelanggan DESC
");
?>

<style>
.member-rfid-box {
    background: linear-gradient(135deg, #eaf5ff, #f5fbff);
    border: 1px solid #cfe8fb;
    border-radius: 12px;
    padding: 15px;
}

.rfid-status {
    font-size: .8rem;
    margin-top: 8px;
}

.rfid-preview {
    border: 1px dashed #a7cde9;
    border-radius: 10px;
    background: #f8fcff;
    padding: 12px;
    color: #52738e;
    font-size: .82rem;
    overflow-wrap: anywhere;
}

.rfid-icon {
    width: 40px;
    height: 40px;
    background: #dff1ff;
    color: #238bd2;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 1.1rem;
}

.member-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border-radius: 6px;
    padding: 5px 8px;
    background: #e8f5ff;
    color: #1477bc;
    font-size: .75rem;
    font-weight: 700;
}

.rfid-input {
    border: 1px solid #9ecdf1;
}

.rfid-input:focus {
    border-color: #2998df;
    box-shadow: 0 0 0 .2rem rgba(41,152,223,.12);
}
</style>

<div class="page-actions mb-3">
    <div>
        <div class="text-muted">
            Kelola pelanggan dan registrasi kartu member RFID
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- ==================================
         FORM TAMBAH PELANGGAN
    =================================== -->

    <div class="col-lg-4">

        <div class="card">

            <div class="card-header">
                <i class="bi bi-person-plus me-2 text-primary"></i>
                Tambah Pelanggan / Member
            </div>

            <div class="card-body">

                <form method="post" id="formPelanggan">

                    <!-- NAMA -->
                    <div class="mb-3">

                        <label class="form-label">
                            Nama Pelanggan
                        </label>

                        <input
                            class="form-control"
                            name="nama_pelanggan"
                            maxlength="100"
                            placeholder="Masukkan nama pelanggan"
                            required
                        >

                    </div>

                    <!-- KOTA -->
                    <div class="mb-3">

                        <label class="form-label">
                            Kota
                        </label>

                        <input
                            class="form-control"
                            name="kota"
                            maxlength="50"
                            placeholder="Masukkan kota"
                        >

                    </div>

                    <!-- RFID -->
                    <div class="mb-3">

                        <div class="member-rfid-box">

                            <div class="d-flex align-items-center gap-2 mb-3">

                                <div class="rfid-icon">
                                    <i class="bi bi-credit-card-2-front"></i>
                                </div>

                                <div>
                                    <strong>Registrasi Kartu RFID</strong>

                                    <div class="text-muted small">
                                        Kartu member pelanggan
                                    </div>
                                </div>

                            </div>

                            <label class="form-label">
                                UID Kartu RFID
                            </label>

                            <input
                                type="text"
                                name="rfid_uid"
                                id="rfid_uid"
                                class="form-control rfid-input"
                                placeholder="Scan kartu RFID di sini"
                                maxlength="50"
                                autocomplete="off"
                            >

                            <div class="rfid-status text-primary" id="rfidStatus">
                                <i class="bi bi-wifi me-1"></i>
                                Siap membaca kartu RFID
                            </div>

                            <div class="rfid-preview mt-2">

                                <div class="small text-muted mb-1">
                                    UID yang terbaca:
                                </div>

                                <strong id="rfidPreview">
                                    Belum ada kartu yang dipindai
                                </strong>

                            </div>

                            <div class="form-text mt-2">
                                Tempel kartu ke reader. UID akan masuk otomatis
                                apabila RFID reader bertindak sebagai keyboard.
                            </div>

                        </div>

                    </div>

                    <!-- TOMBOL -->
                    <button
                        class="btn btn-primary w-100"
                        name="save"
                        value="1"
                        type="submit"
                    >
                        <i class="bi bi-person-check me-1"></i>
                        Simpan Pelanggan
                    </button>

                </form>

            </div>

        </div>

    </div>


    <!-- ==================================
         DAFTAR PELANGGAN
    =================================== -->

    <div class="col-lg-8">

        <div class="card">

            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

                <span>
                    <i class="bi bi-people me-2 text-primary"></i>
                    Daftar Pelanggan
                </span>

                <span class="badge bg-primary">
                    <?= $list ? $list->num_rows : 0 ?> Pelanggan
                </span>

            </div>

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Kota</th>
                            <th>Member RFID</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($list && $list->num_rows > 0): ?>

                        <?php while ($r = $list->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= e($r["id_pelanggan"]) ?>
                                </td>

                                <td>

                                    <div class="fw-semibold">
                                        <?= e($r["nama_pelanggan"]) ?>
                                    </div>

                                </td>

                                <td>
                                    <?= e($r["kota"] ?? "-") ?>
                                </td>

                                <td>

                                    <?php if (!empty($r["rfid_uid"])): ?>

                                        <span class="member-badge">
                                            <i class="bi bi-credit-card"></i>
                                            <?= e($r["rfid_uid"]) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="text-muted small">
                                            <i class="bi bi-dash-circle me-1"></i>
                                            Belum terdaftar
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <a
                                        class="btn btn-sm btn-outline-danger"
                                        data-confirm="Hapus pelanggan ini?"
                                        href="?delete=<?= (int)$r["id_pelanggan"] ?>"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="5" class="text-center text-muted p-4">

                                <i class="bi bi-people fs-3 d-block mb-2"></i>

                                Data pelanggan belum tersedia.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- ==================================
     RFID READER JAVASCRIPT
=================================== -->

<script>
document.addEventListener("DOMContentLoaded", function () {

    const rfidInput = document.getElementById("rfid_uid");
    const preview = document.getElementById("rfidPreview");
    const status = document.getElementById("rfidStatus");

    if (!rfidInput) return;

    // Ketika UID masuk dari reader
    rfidInput.addEventListener("input", function () {

        const uid = this.value.trim();

        if (uid !== "") {

            preview.textContent = uid;

            status.innerHTML =
                '<i class="bi bi-check-circle-fill me-1"></i>' +
                'Kartu RFID berhasil terbaca';

            status.className = "rfid-status text-success";

        } else {

            preview.textContent =
                "Belum ada kartu yang dipindai";

            status.innerHTML =
                '<i class="bi bi-wifi me-1"></i>' +
                'Siap membaca kartu RFID';

            status.className = "rfid-status text-primary";

        }

    });

    // RFID reader umumnya mengirim Enter setelah UID
    rfidInput.addEventListener("keydown", function (event) {

        if (event.key === "Enter") {

            event.preventDefault();

            const uid = this.value.trim();

            if (uid !== "") {

                preview.textContent = uid;

                status.innerHTML =
                    '<i class="bi bi-check-circle-fill me-1"></i>' +
                    'RFID berhasil dibaca. Silakan simpan pelanggan.';

                status.className = "rfid-status text-success";

            }

        }

    });

    // Fokus awal pada input RFID
    rfidInput.focus();

});
</script>

<?php require "footer.php"; ?>
