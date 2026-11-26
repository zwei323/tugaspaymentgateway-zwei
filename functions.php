<?php
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value) {
    return "Rp " . number_format((float)$value, 0, ',', '.');
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function flash($message, $type = "success") {
    $_SESSION["flash"] = ["message" => $message, "type" => $type];
}
?>