<?php
require_once "fungsi.php";
if (user_login_check()) { header("Location: index.php"); exit; }

$next = safe_back($_GET['next'] ?? '', 'index.php');
$error = "";
$username = trim($_POST['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $g = $_SESSION['gagal_login'] ?? ['n' => 0, 't' => 0];
    if ($g['n'] >= 5 && time() - $g['t'] < 60) {
        $error = "Terlalu banyak percobaan. Coba lagi dalam 1 menit.";
    } else {
        $pass = $_POST['password'] ?? '';
        $st = $conn->prepare("SELECT u.id_user, u.id_pelanggan, u.password, p.nama_pelanggan
                              FROM tbl_user u JOIN tbl_pelanggan p ON p.id_pelanggan = u.id_pelanggan
                              WHERE u.username = ? LIMIT 1");
        $st->bind_param("s", $username);
        $st->execute();
        $u = $st->get_result()->fetch_assoc();

        if ($u && password_verify($pass, $u['password'])) {
            unset($_SESSION['gagal_login']);
            session_regenerate_id(true);
            $_SESSION['user_id']        = $u['id_user'];
            $_SESSION['user_nama']      = $u['nama_pelanggan'];
            $_SESSION['user_pelanggan'] = $u['id_pelanggan'];
            flash('success', "Selamat datang kembali, {$u['nama_pelanggan']}!");
            header("Location: " . $next);
            exit;
        }
        $_SESSION['gagal_login'] = ['n' => ($g['t'] && time() - $g['t'] >= 60 ? 0 : $g['n']) + 1, 't' => time()];
        $error = "Username atau password salah.";
    }
}

$pageTitle = "Masuk";
require "header.php";
?>
<div class="row justify-content-center"><div class="col-md-6 col-lg-4">
  <div class="card border-0 shadow-sm"><div class="card-body p-4">
    <h4 class="mb-1">Masuk</h4>
    <p class="text-muted">Masuk untuk berbelanja dan melihat pesanan Anda.</p>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="masuk.php?next=<?= urlencode($next) ?>">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="<?= e($username) ?>" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-primary w-100">Masuk</button>
    </form>
    <p class="text-center small mt-3 mb-0">Belum punya akun? <a href="daftar.php?next=<?= urlencode($next) ?>">Daftar</a></p>
  </div></div>
</div></div>
<?php require "footer.php"; ?>
