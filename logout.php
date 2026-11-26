<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Kosongkan semua data session
$_SESSION = [];

// Hapus cookie session di browser
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Hancurkan session lama
session_destroy();

// Mulai session baru hanya untuk pesan flash
session_start();
session_regenerate_id(true);
$_SESSION["flash"] = [
    "type"    => "success",
    "message" => "Anda berhasil logout."
];

header("Location: login.php");
exit;