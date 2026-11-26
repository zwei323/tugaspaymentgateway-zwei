
<?php
$pageTitle = "Edit Produk";
require "header.php";

/* ==========================================
   KONFIGURASI
========================================== */

$uploadDir = __DIR__ . "/uploads/produk/";
$uploadUrl = "uploads/produk/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

/* ==========================================
   AMBIL ID PRODUK
========================================== */

$id = (int)($_GET["id"] ?? $_POST["id_produk"] ?? 0);

if ($id <= 0) {
    flash("ID produk tidak valid.", "warning");
    redirect("produk.php");
}

/* ==========================================
   AMBIL DATA PRODUK
========================================== */

$stmt = $conn->prepare("SELECT * FROM tbl_produk WHERE id_produk = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$produk = $stmt->get_result()->fetch_assoc();

if (!$produk) {
    flash("Produk tidak ditemukan.", "danger");
    redirect("produk.php");
}

/* ==========================================
   PROSES UPDATE PRODUK
========================================== */

if (isset($_POST["update"])) {

    $nama = trim($_POST["nama_produk"] ?? "");
    $kat = (int)($_POST["id_kategori"] ?? 0);
    $harga = filter_var($_POST["harga"] ?? null, FILTER_VALIDATE_FLOAT);
    $stok = filter_var($_POST["stok"] ?? null, FILTER_VALIDATE_INT);
    $barcode = trim($_POST["barcode"] ?? "");

    if ($barcode === "") {
        $barcode = null;
    }

    // Gambar lama tetap digunakan jika tidak upload gambar baru
    $gambarLama = $produk["gambar"] ?? "";
    $gambarBaru = $gambarLama;
    $fileBaru = null;

    /* ------------------------------------------
       VALIDASI DATA
    ------------------------------------------ */

    if (
        $nama === "" ||
        mb_strlen($nama) > 100 ||
        $kat <= 0 ||
        $harga === false ||
        $harga < 0 ||
        $stok === false ||
        $stok < 0 ||
        ($barcode !== null && mb_strlen($barcode) > 64)
    ) {
        flash("Data produk tidak valid. Periksa kembali input.", "warning");
        redirect("edit_produk.php?id=" . $id);
    }

    /* ------------------------------------------
       VALIDASI KATEGORI
    ------------------------------------------ */

    $checkKategori = $conn->prepare(
        "SELECT id_kategori FROM tbl_kategori WHERE id_kategori = ?"
    );

    $checkKategori->bind_param("i", $kat);
    $checkKategori->execute();

    if (!$checkKategori->get_result()->fetch_assoc()) {
        flash("Kategori tidak ditemukan.", "warning");
        redirect("edit_produk.php?id=" . $id);
    }

    /* ------------------------------------------
       VALIDASI BARCODE DUPLIKAT
    ------------------------------------------ */

    if ($barcode !== null) {

        $checkBarcode = $conn->prepare(
            "SELECT id_produk
             FROM tbl_produk
             WHERE barcode = ? AND id_produk != ?
             LIMIT 1"
        );

        $checkBarcode->bind_param("si", $barcode, $id);
        $checkBarcode->execute();

        if ($checkBarcode->get_result()->fetch_assoc()) {
            flash("Barcode sudah digunakan produk lain.", "warning");
            redirect("edit_produk.php?id=" . $id);
        }
    }

    /* ------------------------------------------
       UPLOAD FOTO BARU (OPSIONAL)
    ------------------------------------------ */

    if (
        isset($_FILES["gambar"]) &&
        $_FILES["gambar"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        $file = $_FILES["gambar"];

        if ($file["error"] !== UPLOAD_ERR_OK) {
            flash("Upload gambar gagal. Silakan coba kembali.", "danger");
            redirect("edit_produk.php?id=" . $id);
        }

        // Maksimal 3 MB
        if ($file["size"] > 3 * 1024 * 1024) {
            flash("Ukuran gambar maksimal 3 MB.", "warning");
            redirect("edit_produk.php?id=" . $id);
        }

        // Periksa file gambar asli
        $imageInfo = getimagesize($file["tmp_name"]);

        if ($imageInfo === false) {
            flash("File yang diupload bukan gambar valid.", "warning");
            redirect("edit_produk.php?id=" . $id);
        }

        $mime = $imageInfo["mime"] ?? "";

        $allowed = [
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp"
        ];

        if (!isset($allowed[$mime])) {
            flash("Format gambar hanya JPG, PNG, atau WEBP.", "warning");
            redirect("edit_produk.php?id=" . $id);
        }

        // Nama file dibuat acak agar tidak bentrok
        $namaFile = "produk_" . bin2hex(random_bytes(12))
                  . "." . $allowed[$mime];

        $tujuan = $uploadDir . $namaFile;

        if (!move_uploaded_file($file["tmp_name"], $tujuan)) {
            flash("Gambar gagal disimpan ke folder upload.", "danger");
            redirect("edit_produk.php?id=" . $id);
        }

        $gambarBaru = $namaFile;
        $fileBaru = $tujuan;
    }

    /* ------------------------------------------
       UPDATE DATABASE
    ------------------------------------------ */

    try {

        if ($gambarBaru !== "") {

            $sql = "UPDATE tbl_produk
                    SET nama_produk = ?,
                        id_kategori = ?,
                        harga = ?,
                        stok = ?,
                        barcode = ?,
                        gambar = ?
                    WHERE id_produk = ?";

            $update = $conn->prepare($sql);

            $update->bind_param(
                "sidissi",
                $nama,
                $kat,
                $harga,
                $stok,
                $barcode,
                $gambarBaru,
                $id
            );

        } else {

            $sql = "UPDATE tbl_produk
                    SET nama_produk = ?,
                        id_kategori = ?,
                        harga = ?,
                        stok = ?,
                        barcode = ?,
                        gambar = NULL
                    WHERE id_produk = ?";

            $update = $conn->prepare($sql);

            $update->bind_param(
                "sidisi",
                $nama,
                $kat,
                $harga,
                $stok,
                $barcode,
                $id
            );
        }

        $update->execute();

        // Hapus foto lama jika berhasil diganti
        if (
            $fileBaru !== null &&
            !empty($gambarLama) &&
            $gambarLama !== $gambarBaru
        ) {
            $fileLama = $uploadDir . basename($gambarLama);

            if (is_file($fileLama)) {
                @unlink($fileLama);
            }
        }

        flash("Produk berhasil diperbarui.");
        redirect("produk.php");

    } catch (Throwable $error) {

        // Hapus foto baru jika update database gagal
        if ($fileBaru !== null && is_file($fileBaru)) {
            @unlink($fileBaru);
        }

        error_log("Edit produk error: " . $error->getMessage());

        flash("Produk gagal diperbarui. Periksa struktur database.", "danger");
        redirect("edit_produk.php?id=" . $id);
    }
}

/* ==========================================
   DATA KATEGORI
========================================== */

$cats = $conn->query(
    "SELECT * FROM tbl_kategori ORDER BY nama_kategori"
);

?>

<style>
.edit-produk-wrap {
    max-width: 1000px;
    margin: auto;
}

.edit-produk-card {
    border: 1px solid #e5edf5;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(30, 70, 110, .04);
}

.edit-produk-card .card-header {
    background: #fff;
    font-weight: 800;
    padding: 18px 22px;
    border-bottom: 1px solid #e8eef5;
}

.edit-produk-card .card-body {
    padding: 24px;
}

.edit-produk-wrap .form-label {
    font-size: .88rem;
    font-weight: 700;
    color: #304b66;
}

.edit-produk-wrap .form-control,
.edit-produk-wrap .form-select {
    min-height: 46px;
    border-radius: 9px;
}

.preview-box {
    background: #f5f9fd;
    border: 1px dashed #d4e2ef;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
}

.preview-img {
    width: 170px;
    height: 170px;
    object-fit: contain;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #e5edf5;
    padding: 5px;
}

.edit-produk-wrap .btn {
    border-radius: 9px;
    font-weight: 700;
    padding: 10px 16px;
}
</style>

<div class="edit-produk-wrap">

    <!-- HEADER -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">

        <div>
            <h4 class="fw-bold mb-1">Edit Produk</h4>
            <div class="text-muted small">
                Ubah informasi produk dan foto produk Tokoku.
            </div>
        </div>

        <a href="produk.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Kembali
        </a>

    </div>

    <div class="card edit-produk-card">

        <div class="card-header">
            <i class="bi bi-pencil-square text-primary me-2"></i>
            Form Edit Produk #<?= (int)$produk["id_produk"] ?>
        </div>

        <div class="card-body">

            <form method="post" enctype="multipart/form-data">

                <input type="hidden"
                       name="id_produk"
                       value="<?= (int)$produk["id_produk"] ?>">

                <div class="row g-4">

                    <!-- KOLOM FORM -->
                    <div class="col-md-7">

                        <!-- NAMA -->
                        <div class="mb-3">
                            <label class="form-label">Nama Produk</label>

                            <input type="text"
                                   name="nama_produk"
                                   class="form-control"
                                   maxlength="100"
                                   value="<?= e($produk["nama_produk"]) ?>"
                                   required>
                        </div>

                        <!-- KATEGORI -->
                        <div class="mb-3">
                            <label class="form-label">Kategori</label>

                            <select name="id_kategori"
                                    class="form-select"
                                    required>

                                <?php if ($cats): ?>
                                    <?php while ($c = $cats->fetch_assoc()): ?>

                                        <option
                                            value="<?= (int)$c["id_kategori"] ?>"
                                            <?= (int)$c["id_kategori"] === (int)$produk["id_kategori"] ? "selected" : "" ?>>

                                            <?= e($c["nama_kategori"]) ?>

                                        </option>

                                    <?php endwhile; ?>
                                <?php endif; ?>

                            </select>
                        </div>

                        <!-- BARCODE -->
                        <div class="mb-3">
                            <label class="form-label">Barcode</label>

                            <input type="text"
                                   name="barcode"
                                   class="form-control"
                                   maxlength="64"
                                   autocomplete="off"
                                   value="<?= e($produk["barcode"] ?? "") ?>"
                                   placeholder="Masukkan barcode produk">

                            <div class="form-text">
                                Barcode harus unik dan tidak boleh sama dengan produk lain.
                            </div>
                        </div>

                        <!-- HARGA -->
                        <div class="mb-3">
                            <label class="form-label">Harga (Rp)</label>

                            <input type="number"
                                   name="harga"
                                   class="form-control"
                                   min="0"
                                   step="0.01"
                                   value="<?= e($produk["harga"]) ?>"
                                   required>
                        </div>

                        <!-- STOK -->
                        <div class="mb-3">
                            <label class="form-label">Stok</label>

                            <input type="number"
                                   name="stok"
                                   class="form-control"
                                   min="0"
                                   value="<?= e($produk["stok"]) ?>"
                                   required>
                        </div>

                    </div>

                    <!-- KOLOM FOTO -->
                    <div class="col-md-5">

                        <label class="form-label">Foto Produk</label>

                        <div class="preview-box mb-3">

                            <?php if (!empty($produk["gambar"])): ?>

                                <img
                                    id="previewGambar"
                                    class="preview-img mb-2"
                                    src="<?= e($uploadUrl . $produk["gambar"]) ?>"
                                    alt="Foto produk">

                            <?php else: ?>

                                <img
                                    id="previewGambar"
                                    class="preview-img mb-2"
                                    src=""
                                    alt="Preview gambar"
                                    style="display:none">

                                <div id="placeholderGambar"
                                     class="text-muted py-4">

                                    <i class="bi bi-image fs-1 d-block mb-2"></i>
                                    Belum ada foto produk

                                </div>

                            <?php endif; ?>

                            <div class="small text-muted mt-2">
                                Preview foto produk
                            </div>

                        </div>

                        <input type="file"
                               name="gambar"
                               id="inputGambar"
                               class="form-control"
                               accept="image/jpeg,image/png,image/webp">

                        <div class="form-text">
                            JPG, PNG, WEBP. Maksimal 3 MB.
                            Kosongkan jika tidak ingin mengganti foto.
                        </div>

                    </div>

                </div>

                <!-- BUTTON -->
                <hr class="my-4">

                <div class="d-flex flex-wrap gap-2">

                    <button type="submit"
                            name="update"
                            class="btn btn-primary">

                        <i class="bi bi-save me-1"></i>
                        Simpan Perubahan

                    </button>

                    <a href="produk.php"
                       class="btn btn-outline-secondary">

                        <i class="bi bi-x-circle me-1"></i>
                        Batal

                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- ==========================================
     PREVIEW GAMBAR
========================================== -->

<script>
const inputGambar = document.getElementById("inputGambar");
const previewGambar = document.getElementById("previewGambar");
const placeholderGambar = document.getElementById("placeholderGambar");

inputGambar.addEventListener("change", function () {

    const file = this.files[0];

    if (!file) return;

    // Validasi ukuran di browser
    if (file.size > 3 * 1024 * 1024) {
        alert("Ukuran gambar maksimal 3 MB!");
        this.value = "";
        return;
    }

    // Validasi tipe file
    const allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    if (!allowedTypes.includes(file.type)) {
        alert("Format gambar harus JPG, PNG, atau WEBP!");
        this.value = "";
        return;
    }

    const reader = new FileReader();

    reader.onload = function (event) {

        previewGambar.src = event.target.result;
        previewGambar.style.display = "inline-block";

        if (placeholderGambar) {
            placeholderGambar.style.display = "none";
        }

    };

    reader.readAsDataURL(file);

});
</script>

<?php require "footer.php"; ?>
