<?php
require_once "fungsi.php";
if (user_login_check()) { header("Location: index.php"); exit; }

$errors = [];
$nama = trim($_POST['nama'] ?? '');
$kota = trim($_POST['kota'] ?? '');
$username = trim($_POST['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if ($nama === '' || mb_strlen($nama) > 100) $errors[] = "Nama lengkap wajib diisi (maksimal 100 karakter).";
    if ($kota === '' || mb_strlen($kota) > 50)  $errors[] = "Kota wajib diisi (maksimal 50 karakter).";
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) $errors[] = "Username 3-30 karakter, hanya huruf, angka, dan underscore.";
    if (strlen($pass) < 6) $errors[] = "Password minimal 6 karakter.";
    if ($pass !== $pass2)  $errors[] = "Konfirmasi password tidak sama.";

    if (!$errors) {
        $st = $conn->prepare("SELECT 1 FROM tbl_user WHERE username = ?");
        $st->bind_param("s", $username);
        $st->execute();
        if ($st->get_result()->fetch_row()) $errors[] = "Username sudah dipakai, pilih yang lain.";
    }

    if (!$errors) {
        try {
            $conn->begin_transaction();
            $st = $conn->prepare("INSERT INTO tbl_pelanggan (nama_pelanggan, kota) VALUES (?, ?)");
            $st->bind_param("ss", $nama, $kota);
            $st->execute();
            $idPelanggan = $conn->insert_id;

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st = $conn->prepare("INSERT INTO tbl_user (id_pelanggan, username, password) VALUES (?, ?, ?)");
            $st->bind_param("iss", $idPelanggan, $username, $hash);
            $st->execute();
            $idUser = $conn->insert_id;
            $conn->commit();

            session_regenerate_id(true);
            $_SESSION['user_id']        = $idUser;
            $_SESSION['user_nama']      = $nama;
            $_SESSION['user_pelanggan'] = $idPelanggan;
            flash('success', "Pendaftaran berhasil. Selamat datang, $nama!");
            header("Location: " . safe_back($_GET['next'] ?? '', 'index.php'));
            exit;
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            $errors[] = ($ex->getCode() == 1062)
                ? "Username sudah dipakai, pilih yang lain."
                : "Pendaftaran gagal. Pastikan tabel tbl_user sudah dibuat (tambah_user.sql).";
        }
    }
}

$pageTitle = "Daftar";
require "header.php";
$next = safe_back($_GET['next'] ?? '', 'index.php');
?>
<div class="row justify-content-center"><div class="col-md-7 col-lg-5">
  <div class="card border-0 shadow-sm"><div class="card-body p-4">
    <h4 class="mb-1">Buat Akun</h4>
    <p class="text-muted">Daftar untuk berbelanja dan memantau pesanan Anda.</p>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" action="daftar.php?next=<?= urlencode($next) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Nama lengkap</label>
        <input type="text" name="nama" class="form-control" maxlength="100" value="<?= e($nama) ?>" required></div>
      <div class="mb-3"><label class="form-label">Kota</label>
        <input type="text" name="kota" class="form-control" maxlength="50" value="<?= e($kota) ?>" required></div>
      <div class="mb-3"><label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" maxlength="30" value="<?= e($username) ?>" required></div>
      <div class="mb-3"><label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" minlength="6" required></div>
      <div class="mb-3"><label class="form-label">Ulangi password</label>
        <input type="password" name="password2" class="form-control" minlength="6" required></div>
      <button class="btn btn-primary w-100">Daftar</button>
    </form>
    <p class="text-center small mt-3 mb-0">Sudah punya akun? <a href="masuk.php?next=<?= urlencode($next) ?>">Masuk</a></p>
  </div></div>
</div></div>
<?php require "footer.php"; ?>
