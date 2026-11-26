
<?php
$pageTitle = "Data Cabang Toko";
require "header.php";

// Tambah cabang
if (isset($_POST["tambah"])) {
    $nama = trim($_POST["nama_cabang"]);
    $lokasi = trim($_POST["lokasi"]);

    $stmt = $conn->prepare(
        "INSERT INTO tbl_cabang (nama_cabang, lokasi) VALUES (?, ?)"
    );
    $stmt->bind_param("ss", $nama, $lokasi);

    if ($stmt->execute()) {
        flash("Cabang berhasil ditambahkan.");
    } else {
        flash("Gagal menambahkan cabang.", "danger");
    }

    redirect("cabang.php");
}

// Hapus cabang
if (isset($_GET["hapus"])) {
    $id = (int) $_GET["hapus"];

    $stmt = $conn->prepare(
        "DELETE FROM tbl_cabang WHERE id_cabang = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();

    flash("Cabang berhasil dihapus.");
    redirect("cabang.php");
}

// Edit cabang
if (isset($_POST["edit"])) {
    $id = (int) $_POST["id_cabang"];
    $nama = trim($_POST["nama_cabang"]);
    $lokasi = trim($_POST["lokasi"]);

    $stmt = $conn->prepare(
        "UPDATE tbl_cabang
         SET nama_cabang = ?, lokasi = ?
         WHERE id_cabang = ?"
    );
    $stmt->bind_param("ssi", $nama, $lokasi, $id);
    $stmt->execute();

    flash("Cabang berhasil diperbarui.");
    redirect("cabang.php");
}

$edit = null;

if (isset($_GET["edit"])) {
    $id = (int) $_GET["edit"];
    $stmt = $conn->prepare(
        "SELECT * FROM tbl_cabang WHERE id_cabang = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
}

$data = $conn->query(
    "SELECT * FROM tbl_cabang ORDER BY id_cabang DESC"
);
?>

<div class="row g-3">

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <?= $edit ? "Edit Cabang" : "Tambah Cabang" ?>
            </div>

            <div class="card-body">
                <form method="POST">

                    <?php if ($edit): ?>
                        <input type="hidden" name="id_cabang"
                        value="<?= e($edit["id_cabang"]) ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Nama Cabang</label>
                        <input type="text"
                            name="nama_cabang"
                            class="form-control"
                            value="<?= e($edit["nama_cabang"] ?? "") ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Lokasi Cabang</label>
                        <textarea name="lokasi"
                            class="form-control"
                            required><?= e($edit["lokasi"] ?? "") ?></textarea>
                    </div>

                    <?php if ($edit): ?>
                        <button name="edit" class="btn btn-primary w-100">
                            Simpan Perubahan
                        </button>
                        <a href="cabang.php"
                           class="btn btn-secondary w-100 mt-2">
                            Batal
                        </a>
                    <?php else: ?>
                        <button name="tambah"
                            class="btn btn-primary w-100">
                            Tambah Cabang
                        </button>
                    <?php endif; ?>

                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                Daftar Cabang Toko
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Cabang</th>
                            <th>Lokasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php while ($row = $data->fetch_assoc()): ?>
                        <tr>
                            <td><?= e($row["id_cabang"]) ?></td>
                            <td><?= e($row["nama_cabang"]) ?></td>
                            <td><?= e($row["kota"]) ?></td>
                            <td>
                                <a href="cabang.php?edit=<?= $row["id_cabang"] ?>"
                                   class="btn btn-sm btn-warning">
                                    Edit
                                </a>

                                <a href="cabang.php?hapus=<?= $row["id_cabang"] ?>"
                                   class="btn btn-sm btn-danger"
                                   data-confirm="Yakin menghapus cabang ini?">
                                    Hapus
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require "footer.php"; ?>
