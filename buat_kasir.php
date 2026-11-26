
<?php
require "koneksi.php";

$nama = "Kasir Toko";
$username = "kasir";
$password = password_hash("kasir123", PASSWORD_DEFAULT);
$status = "aktif";

$stmt = $conn->prepare("
    INSERT INTO tbl_kasir
    (nama_lengkap, username, password, status, nama_kasir)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssss",
    $nama,
    $username,
    $password,
    $status,
    $nama
);

if ($stmt->execute()) {
    echo "Akun kasir berhasil dibuat!";
    echo "<br>ID Kasir: " . $conn->insert_id;
    echo "<br>Username: kasir";
    echo "<br>Password: kasir123";
} else {
    echo "Gagal: " . $stmt->error;
}

$stmt->close();
?>
