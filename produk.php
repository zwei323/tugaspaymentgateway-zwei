<?php
/* ==========================================
   DATA PRODUK (KASIR) - TOKO KITA
   Tambah, edit, hapus, dan upload gambar produk
========================================== */

// Cek session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek hak akses kasir
if (
    !isset($_SESSION['kasir_id']) ||
    ($_SESSION['role'] ?? '') !== 'kasir'
) {
    header("Location: ../login.php");
    exit;
}

// Buffer output agar redirect tetap bisa dilakukan setelah header.php dimuat
ob_start();

// Pengaturan halaman
$pageTitle = "Data Produk";

// Memanggil header
require_once "header.php";

// Fungsi keamanan
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// Fungsi format rupiah
if (!function_exists('rupiah')) {
    function rupiah($angka) {
        return "Rp " . number_format((float)$angka, 0, ',', '.');
    }
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Koneksi database (\$conn) tidak ditemukan. Pastikan header.php memuat file koneksi.");
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ==========================================
   PENGATURAN & FUNGSI BANTU
========================================== */

$dirUpload = __DIR__ . "/../uploads/produk";   // folder fisik (dipakai bersama admin & toko)
$urlUpload = "../uploads/produk";              // alamat untuk <img>
$maksBytes = 2 * 1024 * 1024;                  // 2 MB

function pk_csrf_field() {
    if (empty($_SESSION['_pkcsrf'])) {
        $_SESSION['_pkcsrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['_pkcsrf']) . '">';
}

function pk_csrf_check() {
    if (!hash_equals($_SESSION['_pkcsrf'] ?? '', $_POST['csrf'] ?? '')) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(419);
        die("Sesi tidak valid. Silakan kembali dan coba lagi.");
    }
}

function pk_flash($type, $msg) {
    $_SESSION['pk_flash'] = ['type' => $type, 'message' => $msg];
}

function pk_redirect($url) {
    while (ob_get_level()) ob_end_clean();
    header("Location: " . $url);
    exit;
}

// Simpan gambar upload -> [ok, namaFile | pesanError | null]
function pk_simpan_gambar(array $f, $dir, $maks) {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [true, null];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE)
        return [false, "Ukuran gambar melebihi batas server (maks 2 MB)."];
    if ($f['error'] !== UPLOAD_ERR_OK)
        return [false, "Upload gambar gagal (kode {$f['error']})."];
    if ($f['size'] > $maks)
        return [false, "Ukuran gambar maksimal 2 MB."];

    $info = @getimagesize($f['tmp_name']);
    $tipe = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info) return [false, "File yang dipilih bukan gambar yang valid."];
    if (!isset($tipe[$info[2]])) return [false, "Format gambar harus JPG, PNG, WEBP, atau GIF."];

    if (!is_dir($dir) && !@mkdir($dir, 0755, true))
        return [false, "Folder uploads/produk tidak bisa dibuat."];

    $nama = bin2hex(random_bytes(8)) . '.' . $tipe[$info[2]];
    if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $nama))
        return [false, "Gagal menyimpan gambar. Periksa izin tulis folder uploads/produk."];

    return [true, $nama];
}

function pk_hapus_file($dir, $nama) {
    if ($nama) {
        $p = $dir . '/' . basename($nama);
        if (is_file($p)) @unlink($p);
    }
}

function pk_url_gambar($nama, $dir, $url) {
    return ($nama && is_file($dir . '/' . basename($nama)))
        ? $url . '/' . rawurlencode(basename($nama))
        : null;
}

$adaGambar = $conn->query("SHOW COLUMNS FROM tbl_produk LIKE 'gambar'")->num_rows > 0;
$adaBarcode = $conn->query("SHOW COLUMNS FROM tbl_produk LIKE 'barcode'")->num_rows > 0;

/* ==========================================
   PROSES FORM
========================================== */

$errors = [];
$form = [
    'id_produk'   => 0,
    'nama_produk' => '',
    'id_kategori' => 0,
    'harga'       => '',
    'stok'        => '',
    'barcode'     => '',
    'gambar'      => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    pk_csrf_check();

    $aksi = $_POST['aksi'] ?? 'simpan';
    $id   = (int)($_POST['id'] ?? 0);

    // ---------- HAPUS ----------
    if ($aksi === 'hapus') {

        $lama = null;

        if ($adaGambar) {
            $st = $conn->prepare("SELECT gambar FROM tbl_produk WHERE id_produk = ?");
            $st->bind_param("i", $id);
            $st->execute();
            $lama = ($st->get_result()->fetch_assoc())['gambar'] ?? null;
        }

        try {
            $st = $conn->prepare("DELETE FROM tbl_produk WHERE id_produk = ?");
            $st->bind_param("i", $id);
            $st->execute();

            pk_hapus_file($dirUpload, $lama);
            pk_flash('success', 'Produk berhasil dihapus.');
        } catch (mysqli_sql_exception $ex) {
            pk_flash('danger', 'Produk tidak bisa dihapus karena sudah tercatat di transaksi.');
        }

        pk_redirect("produk.php");
    }

    // ---------- SIMPAN (TAMBAH / EDIT) ----------
    $form['id_produk']   = $id;
    $form['nama_produk'] = trim($_POST['nama_produk'] ?? '');
    $form['id_kategori'] = (int)($_POST['id_kategori'] ?? 0);
    $form['harga']       = trim($_POST['harga'] ?? '');
    $form['stok']        = trim($_POST['stok'] ?? '');
    $form['barcode']     = trim($_POST['barcode'] ?? '');

    if (mb_strlen($form['barcode']) > 64) {
        $errors[] = "Barcode maksimal 64 karakter.";
    }
    if ($form['barcode'] === '') {
        $form['barcode'] = null;
    }
    if (!$adaBarcode && $form['barcode'] !== null) {
        $errors[] = "Kolom barcode belum tersedia. Jalankan migration_barcode_produk.sql terlebih dahulu.";
    }

    if ($form['nama_produk'] === '' || mb_strlen($form['nama_produk']) > 100) {
        $errors[] = "Nama produk wajib diisi (maksimal 100 karakter).";
    }

    $st = $conn->prepare("SELECT 1 FROM tbl_kategori WHERE id_kategori = ?");
    $st->bind_param("i", $form['id_kategori']);
    $st->execute();
    if (!$st->get_result()->fetch_row()) {
        $errors[] = "Pilih kategori yang valid.";
    }

    if (!is_numeric($form['harga']) || $form['harga'] < 0) {
        $errors[] = "Harga harus berupa angka (0 atau lebih).";
    }

    if (!ctype_digit($form['stok'])) {
        $errors[] = "Stok harus berupa bilangan bulat (0 atau lebih).";
    }

    // Data lama (untuk edit)
    $lama = null;
    if ($id > 0) {
        $cols = $adaGambar ? "id_produk, gambar" : "id_produk";
        $st = $conn->prepare("SELECT $cols FROM tbl_produk WHERE id_produk = ?");
        $st->bind_param("i", $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();

        if (!$row) {
            pk_flash('danger', 'Produk tidak ditemukan.');
            pk_redirect("produk.php");
        }

        $lama = $row['gambar'] ?? null;
        $form['gambar'] = $lama;
    }

    // Upload gambar baru
    $baru = null;
    if (!$errors && $adaGambar) {
        [$ok, $hasil] = pk_simpan_gambar(
            $_FILES['gambar'] ?? ['error' => UPLOAD_ERR_NO_FILE],
            $dirUpload,
            $maksBytes
        );

        if (!$ok) $errors[] = $hasil;
        else $baru = $hasil;
    }

    // Simpan ke database
    if (!$errors) {

        $harga = (float)$form['harga'];
        $stok  = (int)$form['stok'];

        $final = $lama;
        if ($baru) {
            $final = $baru;
        } elseif (!empty($_POST['hapus_gambar'])) {
            $final = null;
        }

        try {
            if ($id > 0) {
                if ($adaGambar && $adaBarcode) {
                    $st = $conn->prepare("UPDATE tbl_produk
                        SET nama_produk=?, id_kategori=?, harga=?, stok=?, gambar=?, barcode=?
                        WHERE id_produk=?");
                    $st->bind_param("sidissi", $form['nama_produk'], $form['id_kategori'], $harga, $stok, $final, $form['barcode'], $id);
                } elseif ($adaBarcode) {
                    $st = $conn->prepare("UPDATE tbl_produk
                        SET nama_produk=?, id_kategori=?, harga=?, stok=?, barcode=?
                        WHERE id_produk=?");
                    $st->bind_param("sidisi", $form['nama_produk'], $form['id_kategori'], $harga, $stok, $form['barcode'], $id);
                } elseif ($adaGambar) {
                    $st = $conn->prepare("UPDATE tbl_produk
                        SET nama_produk=?, id_kategori=?, harga=?, stok=?, gambar=?
                        WHERE id_produk=?");
                    $st->bind_param("sidisi", $form['nama_produk'], $form['id_kategori'], $harga, $stok, $final, $id);
                } else {
                    $st = $conn->prepare("UPDATE tbl_produk
                        SET nama_produk=?, id_kategori=?, harga=?, stok=?
                        WHERE id_produk=?");
                    $st->bind_param("sidii", $form['nama_produk'], $form['id_kategori'], $harga, $stok, $id);
                }
            } else {
                if ($adaGambar && $adaBarcode) {
                    $st = $conn->prepare("INSERT INTO tbl_produk
                        (nama_produk, id_kategori, harga, stok, gambar, barcode) VALUES (?, ?, ?, ?, ?, ?)");
                    $st->bind_param("sidiss", $form['nama_produk'], $form['id_kategori'], $harga, $stok, $final, $form['barcode']);
                } elseif ($adaBarcode) {
                    $st = $conn->prepare("INSERT INTO tbl_produk
                        (nama_produk, id_kategori, harga, stok, barcode) VALUES (?, ?, ?, ?, ?)");
                    $st->bind_param("sidis", $form['nama_produk'], $form['id_kategori'], $harga, $stok, $form['barcode']);
                } elseif ($adaGambar) {
                    $st = $conn->prepare("INSERT INTO tbl_produk
                        (nama_produk, id_kategori, harga, stok, gambar) VALUES (?, ?, ?, ?, ?)");
                    $st->bind_param("sidis", $form['nama_produk'], $form['id_kategori'], $harga, $stok, $final);
                } else {
                    $st = $conn->prepare("INSERT INTO tbl_produk
                        (nama_produk, id_kategori, harga, stok) VALUES (?, ?, ?, ?)");
                    $st->bind_param("sidi", $form['nama_produk'], $form['id_kategori'], $harga, $stok);
                }
            }

            $st->execute();

            // Buang gambar lama bila diganti / dihapus
            if ($id > 0 && $lama && $lama !== $final) {
                pk_hapus_file($dirUpload, $lama);
            }

            pk_flash('success', $id > 0 ? 'Produk berhasil diperbarui.' : 'Produk berhasil ditambahkan.');
            pk_redirect("produk.php");

        } catch (mysqli_sql_exception $ex) {
            pk_hapus_file($dirUpload, $baru);
            $errors[] = (int)$ex->getCode() === 1062
                ? "Barcode tersebut sudah digunakan oleh produk lain."
                : "Gagal menyimpan produk. Silakan coba lagi.";
        }
    }

} elseif (isset($_GET['edit'])) {

    $eid = (int)$_GET['edit'];
    $st = $conn->prepare("SELECT * FROM tbl_produk WHERE id_produk = ?");
    $st->bind_param("i", $eid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    if (!$row) {
        pk_flash('danger', 'Produk tidak ditemukan.');
        pk_redirect("produk.php");
    }

    $form = array_merge($form, $row);
}

/* ==========================================
   DATA TAMPILAN
========================================== */

$modeEdit = (int)$form['id_produk'] > 0;

$kategori = $conn->query(
    "SELECT id_kategori, nama_kategori FROM tbl_kategori ORDER BY nama_kategori"
);

$produk = $conn->query(
    "SELECT p.*, k.nama_kategori
     FROM tbl_produk p
     LEFT JOIN tbl_kategori k ON k.id_kategori = p.id_kategori
     ORDER BY p.id_produk DESC"
);
$totalProduk = $produk ? $produk->num_rows : 0;

$gambarForm = $adaGambar
    ? pk_url_gambar($form['gambar'] ?? null, $dirUpload, $urlUpload)
    : null;

$flash = $_SESSION['pk_flash'] ?? null;
unset($_SESSION['pk_flash']);
?>

<style>

/* ==========================================
   TEMA BIRU MUDA (sama dengan Dashboard Kasir)
========================================== */

:root {
    --rt-blue: #2998df;
    --rt-blue-dark: #1769aa;
    --rt-blue-light: #e8f5ff;
    --rt-navy: #17446b;
    --rt-border: #e3edf5;
    --rt-muted: #71869a;
}

.rt-wrap {
    font-family: "Nunito", "Inter", system-ui, sans-serif;
    color: #263b50;
}

.rt-title {
    font-size: 1.6rem;
    font-weight: 800;
    margin: 0;
    color: var(--rt-navy);
}

.rt-sub {
    font-size: .85rem;
    color: var(--rt-muted);
}

.rt-sub b {
    color: var(--rt-blue);
}

.rt-card {
    background: #fff;
    border: 1px solid var(--rt-border);
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(40, 110, 160, .04);
}

.rt-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--rt-border);
}

.rt-head h6 {
    margin: 0;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--rt-navy);
}

.rt-head .hi {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: .95rem;
    background: #e0f2fe;
    color: #0284c7;
}

.rt-btn {
    background: var(--rt-blue);
    color: #fff;
    border: 0;
    border-radius: 8px;
    padding: 9px 14px;
    font-size: .82rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.rt-btn:hover {
    background: var(--rt-blue-dark);
    color: #fff;
}

.rt-btn.light {
    background: #fff;
    color: var(--rt-navy);
    border: 1px solid var(--rt-border);
}

.rt-btn.light:hover {
    background: #f3f9fe;
    color: var(--rt-navy);
}

.rt-table th {
    font-size: .78rem;
    color: var(--rt-muted);
    font-weight: 700;
    background: #f3f9fe;
    border: 0;
    padding: 13px;
}

.rt-table td {
    font-size: .83rem;
    vertical-align: middle;
    padding: 12px 13px;
}

.rt-table tbody tr:hover {
    background: #f7fbff;
}

.rt-empty {
    text-align: center;
    padding: 30px 10px;
    color: var(--rt-muted);
}

.rt-empty i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
}

/* Form & gambar */

.rt-form .form-label {
    font-size: .82rem;
    font-weight: 700;
    color: var(--rt-navy);
}

.rt-form .form-control:focus,
.rt-form .form-select:focus {
    border-color: var(--rt-blue);
    box-shadow: 0 0 0 .2rem rgba(41, 152, 223, .15);
}

.pk-box {
    width: 100%;
    height: 175px;
    border: 2px dashed #bcd9ee;
    border-radius: 12px;
    background: var(--rt-blue-light);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    color: #8fbfe0;
}

.pk-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pk-thumb {
    width: 44px;
    height: 44px;
    border-radius: 9px;
    background: var(--rt-blue-light);
    color: #8fbfe0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-right: 12px;
    vertical-align: middle;
    flex-shrink: 0;
}

.pk-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pk-stok {
    font-size: .75rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    background: #e0f2fe;
    color: #0369a1;
}

.pk-stok.low {
    background: #fee2e2;
    color: #b91c1c;
}

.pk-search {
    position: relative;
}

.pk-search i {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--rt-muted);
}

.pk-search input {
    padding-left: 33px;
    min-width: 220px;
    border-radius: 9px;
    border: 1px solid var(--rt-border);
    font-size: .83rem;
}

</style>


<div class="rt-wrap">

    <!-- ==================================
         JUDUL HALAMAN
    =================================== -->

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">

        <div>
            <h1 class="rt-title">Data Produk</h1>

            <div class="rt-sub mt-1">
                Kelola produk, harga, stok, dan gambar.
                Total <b><?= (int)$totalProduk ?></b> produk.
            </div>
        </div>

        <a href="dashboard_kasir.php" class="rt-btn light">
            <i class="bi bi-arrow-left me-1"></i>
            Dashboard Kasir
        </a>

    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$adaGambar): ?>
        <div class="alert alert-warning">
            Fitur gambar belum aktif. Jalankan <code>tambah_gambar.sql</code>
            di phpMyAdmin (database <code>db_tokoku</code>).
        </div>
    <?php endif; ?>


    <div class="row g-3">

        <!-- ==================================
             FORM TAMBAH / EDIT
        =================================== -->

        <div class="col-12 col-lg-4">

            <div class="rt-card">

                <div class="rt-head">
                    <h6>
                        <span class="hi">
                            <i class="bi <?= $modeEdit ? 'bi-pencil-square' : 'bi-plus-circle' ?>"></i>
                        </span>
                        <?= $modeEdit ? 'Edit Produk #' . (int)$form['id_produk'] : 'Tambah Produk' ?>
                    </h6>
                </div>

                <div class="p-3 rt-form">

                    <?php foreach ($errors as $er): ?>
                        <div class="alert alert-danger py-2"><?= e($er) ?></div>
                    <?php endforeach; ?>

                    <form method="post" enctype="multipart/form-data">

                        <?= pk_csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$form['id_produk'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Nama Produk</label>
                            <input type="text" name="nama_produk" class="form-control"
                                   maxlength="100" value="<?= e($form['nama_produk']) ?>" required>
                        </div>

                        <?php if ($adaBarcode): ?>
                        <div class="mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" class="form-control"
                                   maxlength="64" value="<?= e($form['barcode'] ?? '') ?>"
                                   placeholder="Scan atau ketik kode barcode" autocomplete="off">
                            <div class="form-text">Boleh dikosongkan. Setiap barcode harus unik.</div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <select name="id_kategori" class="form-select" required>
                                <option value="">-- Pilih kategori --</option>
                                <?php while ($k = $kategori->fetch_assoc()): ?>
                                    <option value="<?= (int)$k['id_kategori'] ?>"
                                        <?= (int)$form['id_kategori'] === (int)$k['id_kategori'] ? 'selected' : '' ?>>
                                        <?= e($k['nama_kategori']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-7 mb-3">
                                <label class="form-label">Harga (Rp)</label>
                                <input type="number" name="harga" class="form-control"
                                       min="0" step="1" value="<?= e($form['harga']) ?>" required>
                            </div>
                            <div class="col-5 mb-3">
                                <label class="form-label">Stok</label>
                                <input type="number" name="stok" class="form-control"
                                       min="0" step="1" value="<?= e($form['stok']) ?>" required>
                            </div>
                        </div>

                        <?php if ($adaGambar): ?>

                        <div class="mb-3">

                            <label class="form-label">Gambar Produk</label>

                            <div class="pk-box mb-2">
                                <img id="pvGambar" alt=""
                                     src="<?= e($gambarForm ?? '') ?>"
                                     style="<?= $gambarForm ? '' : 'display:none' ?>">
                                <span id="pvKosong" style="<?= $gambarForm ? 'display:none' : '' ?>">
                                    <i class="bi bi-image" style="font-size:2.6rem"></i>
                                </span>
                            </div>

                            <input type="file" name="gambar" id="fileGambar" class="form-control"
                                   accept="image/jpeg,image/png,image/webp,image/gif">

                            <div class="form-text">
                                JPG, PNG, WEBP, atau GIF. Maksimal 2 MB.
                                <?= $modeEdit ? 'Kosongkan jika tidak ingin mengganti gambar.' : '' ?>
                            </div>

                            <?php if ($modeEdit && $gambarForm): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox"
                                           name="hapus_gambar" value="1" id="hapusGambar">
                                    <label class="form-check-label" for="hapusGambar">
                                        Hapus gambar saat ini
                                    </label>
                                </div>
                            <?php endif; ?>

                        </div>

                        <?php endif; ?>

                        <button class="rt-btn w-100 py-2">
                            <i class="bi bi-check2-circle me-1"></i>
                            <?= $modeEdit ? 'Update Produk' : 'Simpan Produk' ?>
                        </button>

                        <?php if ($modeEdit): ?>
                            <a href="produk.php" class="rt-btn light w-100 text-center mt-2">
                                Batal Edit
                            </a>
                        <?php endif; ?>

                    </form>

                </div>

            </div>

        </div>


        <!-- ==================================
             DAFTAR PRODUK
        =================================== -->

        <div class="col-12 col-lg-8">

            <div class="rt-card">

                <div class="rt-head">

                    <h6>
                        <span class="hi"><i class="bi bi-box-seam"></i></span>
                        Daftar Produk
                    </h6>

                    <div class="pk-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="cariProduk" class="form-control form-control-sm"
                               placeholder="Cari nama produk...">
                    </div>

                </div>

                <div class="table-responsive">

                    <table class="table rt-table mb-0">

                        <thead>
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Produk</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th class="pe-4">Aksi</th>
                            </tr>
                        </thead>

                        <tbody id="isiProduk">

                        <?php if ($produk && $produk->num_rows > 0): ?>

                            <?php while ($p = $produk->fetch_assoc()):
                                $img = $adaGambar
                                    ? pk_url_gambar($p['gambar'] ?? null, $dirUpload, $urlUpload)
                                    : null;
                                $sedangEdit = $modeEdit && (int)$form['id_produk'] === (int)$p['id_produk'];
                            ?>

                            <tr data-nama="<?= e(mb_strtolower($p['nama_produk'])) ?>"
                                style="<?= $sedangEdit ? 'background:#eef7fe' : '' ?>">

                                <td class="ps-4">
                                    <span class="fw-bold" style="color:#2998df">
                                        #<?= (int)$p['id_produk'] ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="pk-thumb">
                                            <?php if ($img): ?>
                                                <img src="<?= e($img) ?>" alt="">
                                            <?php else: ?>
                                                <i class="bi bi-image"></i>
                                            <?php endif; ?>
                                        </span>
                                        <?= e($p['nama_produk']) ?>
                                    </div>
                                </td>

                                <td><?= e($p['nama_kategori'] ?? '-') ?></td>

                                <td class="fw-bold"><?= e(rupiah($p['harga'])) ?></td>

                                <td>
                                    <span class="pk-stok <?= (int)$p['stok'] <= 5 ? 'low' : '' ?>">
                                        <?= (int)$p['stok'] ?>
                                    </span>
                                </td>

                                <td class="pe-4">
                                    <div class="d-flex gap-2">

                                        <a href="produk.php?edit=<?= (int)$p['id_produk'] ?>"
                                           class="btn btn-outline-warning btn-sm" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <form method="post"
                                              onsubmit="return confirm('Hapus produk <?= e(addslashes($p['nama_produk'])) ?>?')">
                                            <?= pk_csrf_field() ?>
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id" value="<?= (int)$p['id_produk'] ?>">
                                            <button class="btn btn-outline-danger btn-sm" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6">
                                    <div class="rt-empty">
                                        <i class="bi bi-box-seam"></i>
                                        Belum ada produk.
                                    </div>
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


<script>

// Preview gambar sebelum disimpan
(function () {

    const file = document.getElementById('fileGambar');
    if (!file) return;

    const img    = document.getElementById('pvGambar');
    const kosong = document.getElementById('pvKosong');
    const hapus  = document.getElementById('hapusGambar');
    const awal   = img.getAttribute('src');

    function tampil(src) {
        if (src) {
            img.src = src;
            img.style.display = '';
            kosong.style.display = 'none';
        } else {
            img.style.display = 'none';
            kosong.style.display = '';
        }
    }

    file.addEventListener('change', function () {
        const f = this.files[0];

        if (!f) { tampil(awal); return; }

        if (f.size > 2 * 1024 * 1024) {
            alert('Ukuran gambar maksimal 2 MB.');
            this.value = '';
            tampil(awal);
            return;
        }

        if (hapus) hapus.checked = false;
        tampil(URL.createObjectURL(f));
    });

    if (hapus) {
        hapus.addEventListener('change', function () {
            if (this.checked) { file.value = ''; tampil(''); }
            else { tampil(awal); }
        });
    }

})();

// Pencarian produk (di tabel)
(function () {

    const cari = document.getElementById('cariProduk');
    if (!cari) return;

    cari.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();

        document.querySelectorAll('#isiProduk tr[data-nama]').forEach(function (tr) {
            tr.style.display = tr.dataset.nama.includes(q) ? '' : 'none';
        });
    });

})();

</script>


<?php
// Memanggil footer
require_once "footer.php";

ob_end_flush();
?>
