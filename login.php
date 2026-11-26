
<?php
session_start();
require "koneksi.php";

// Jika sudah login, arahkan sesuai peran
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

if (isset($_SESSION['kasir_id'])) {
    header("Location: kasir/kasir.php");
    exit;
}

if (isset($_SESSION['user_id'])) {
    header("Location: user/index.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $g = $_SESSION['gagal_login'] ?? ['n' => 0, 't' => 0];

    if ($g['n'] >= 5 && time() - $g['t'] < 60) {

        $error = "Terlalu banyak percobaan. Coba lagi dalam 1 menit.";

    } elseif ($username === '' || $password === '') {

        $error = "Username dan password wajib diisi.";

    } else {

        // ==================================
        // 1. LOGIN ADMIN
        // ==================================

        $stmt = $conn->prepare("
            SELECT id_admin, password, nama_lengkap
            FROM tbl_admin
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password'])) {

            unset($_SESSION['gagal_login']);
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id_admin'];
            $_SESSION['admin_nama'] = $admin['nama_lengkap'];
            $_SESSION['role'] = 'admin';

            header("Location: index.php");
            exit;
        }

        // ==================================
        // 2. LOGIN KASIR DARI TBL_KASIR
        // ==================================

        $stmt = $conn->prepare("
            SELECT
                id_kasir,
                nama_kasir,
                username,
                password,
                status
            FROM tbl_kasir
            WHERE username = ?
            AND status = 'aktif'
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $kasir = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($kasir && password_verify($password, $kasir['password'])) {

            unset($_SESSION['gagal_login']);
            session_regenerate_id(true);

            $_SESSION['kasir_id'] = $kasir['id_kasir'];
            $_SESSION['kasir_nama'] = $kasir['nama_kasir'];
            $_SESSION['kasir_username'] = $kasir['username'];
            $_SESSION['role'] = 'kasir';

            header("Location: kasir/kasir.php");
            exit;
        }

        // ==================================
        // 3. LOGIN PELANGGAN DARI TBL_USER
        // ==================================

        $stmt = $conn->prepare("
            SELECT
                u.id_user,
                u.id_pelanggan,
                u.password,
                p.nama_pelanggan
            FROM tbl_user u
            JOIN tbl_pelanggan p
                ON p.id_pelanggan = u.id_pelanggan
            WHERE u.username = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {

            unset($_SESSION['gagal_login']);
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['user_nama'] = $user['nama_pelanggan'];
            $_SESSION['user_pelanggan'] = $user['id_pelanggan'];
            $_SESSION['role'] = 'pelanggan';

            header("Location: user/index.php");
            exit;
        }

        // ==================================
        // LOGIN GAGAL
        // ==================================

        $_SESSION['gagal_login'] = [
            'n' => ($g['t'] && time() - $g['t'] >= 60 ? 0 : $g['n']) + 1,
            't' => time()
        ];

        $error = "Username atau password salah.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Login - Toko Kita</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #dff3ff, #8ccfff);
    font-family: system-ui, Arial, sans-serif;
    padding: 20px;
}

.card {
    background: white;
    width: 100%;
    max-width: 400px;
    padding: 36px 32px;
    border-radius: 18px;
    box-shadow: 0 12px 35px rgba(30, 100, 160, .15);
}

.logo {
    width: 55px;
    height: 55px;
    background: #268bd2;
    color: white;
    border-radius: 15px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 25px;
    margin-bottom: 18px;
}

h1 {
    margin: 0 0 5px;
    font-size: 25px;
    color: #173b63;
}

.sub {
    margin: 0 0 25px;
    color: #8295aa;
    font-size: 14px;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 7px;
    color: #374151;
}

input {
    width: 100%;
    padding: 12px;
    border: 1px solid #dbe7f1;
    border-radius: 9px;
    font-size: 14px;
    margin-bottom: 17px;
}

input:focus {
    outline: 2px solid #8ccfff;
    border-color: #268bd2;
}

button {
    width: 100%;
    padding: 13px;
    background: #268bd2;
    color: white;
    border: none;
    border-radius: 9px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}

button:hover {
    background: #173b63;
}

.error {
    background: #fee2e2;
    color: #b91c1c;
    padding: 11px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 17px;
}

.links {
    margin-top: 22px;
    padding-top: 17px;
    border-top: 1px solid #e5e7eb;
    text-align: center;
    font-size: 13px;
    color: #64748b;
}

.links a {
    color: #268bd2;
    font-weight: 600;
    text-decoration: none;
}

.links p {
    margin: 8px 0;
}

.footer {
    text-align: center;
    font-size: 11px;
    color: #94a3b8;
    margin-top: 20px;
}
</style>
</head>

<body>

<div class="card">

    <div class="logo">🏪</div>

    <h1>Tokoku</h1>

    <p class="sub">
        Silakan masuk ke akun Anda
    </p>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <label for="username">Username</label>

        <input
            type="text"
            id="username"
            name="username"
            placeholder="Masukkan username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            required
            autofocus
        >

        <label for="password">Password</label>

        <input
            type="password"
            id="password"
            name="password"
            placeholder="Masukkan password"
            required
        >

        <button type="submit">
            Masuk ke Sistem
        </button>

    </form>

    <div class="links">

        <p>
            Belum punya akun?
            <a href="toko/daftar.php">Daftar pelanggan</a>
        </p>

        <p>
            <a href="toko/index.php">Lihat toko tanpa login</a>
        </p>

    </div>

    <div class="footer">
        © 2026 Toko Kita - Sistem Informasi Penjualan
    </div>

</div>

</body>
</html>
