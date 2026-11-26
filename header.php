
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Memanggil file dari folder utama tokoku
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../functions.php";

// Proteksi login kasir
if (empty($_SESSION["kasir_id"])) {
    header("Location: ../login.php");
    exit;
}

$pageTitle = $pageTitle ?? "Dashboard Kasir";

$namaKasir = $_SESSION["kasir_nama"] ?? "Kasir";
$usernameKasir = $_SESSION["kasir_username"] ?? "kasir";
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= e($pageTitle) ?> - Tokoku Kasir</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<!-- CSS utama -->
<link href="../style.css" rel="stylesheet">

<!-- CSS tambahan kasir -->
<style>
:root {
    --primary: #268bd2;
    --primary-dark: #173b63;
    --background: #f0f8ff;
    --border: #dbeafe;
}

body {
    margin: 0;
    background: var(--background);
    font-family: system-ui, Arial, sans-serif;
}

.app-shell {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 250px;
    min-width: 250px;
    background: white;
    border-right: 1px solid var(--border);
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 23px;
    font-weight: 800;
    color: var(--primary-dark);
    margin-bottom: 35px;
}

.brand i {
    color: var(--primary);
    font-size: 28px;
}

.sidebar-caption {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 700;
    margin-bottom: 12px;
}

.sidebar .nav-link {
    color: #475569;
    padding: 12px 14px;
    border-radius: 9px;
    margin-bottom: 7px;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
}

.sidebar .nav-link:hover,
.sidebar .nav-link.active {
    background: #e0f2fe;
    color: var(--primary);
}

.sidebar-bottom {
    margin-top: auto;
    padding-top: 25px;
    font-size: 12px;
    color: #94a3b8;
}

.main-content {
    flex: 1;
    min-width: 0;
}

.topbar {
    background: white;
    min-height: 80px;
    padding: 15px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border);
    gap: 15px;
}

.user-chip {
    display: flex;
    align-items: center;
    gap: 9px;
    background: #f0f9ff;
    border: 1px solid var(--border);
    padding: 9px 14px;
    border-radius: 30px;
    font-size: 13px;
}

.content-area {
    padding: 25px;
}

@media(max-width: 768px) {
    .sidebar {
        width: 75px;
        min-width: 75px;
        padding: 15px 8px;
    }

    .brand span,
    .sidebar-caption,
    .sidebar .nav-link:not(i),
    .sidebar-bottom {
        display: none;
    }

    .sidebar .nav-link {
        justify-content: center;
        font-size: 19px;
    }

    .topbar {
        padding: 15px;
        flex-wrap: wrap;
    }

    .content-area {
        padding: 15px;
    }
}
</style>

</head>

<body>

<div class="app-shell">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="brand">
            <i class="bi bi-shop-window"></i>
            <span>Tokoku</span>
        </div>

        <div class="sidebar-caption">MENU KASIR</div>

        <a href="kasir.php" class="nav-link">
            <i class="bi bi-grid-1x2"></i>
            Dashboard
        </a>

        <a href="pelanggan.php" class="nav-link">
            <i class="bi bi-people"></i>
            Pelanggan
        </a>

        <a href="transaksi.php" class="nav-link">
            <i class="bi bi-receipt"></i>
            Transaksi
        </a>

        <a href="logout.php"
           class="nav-link text-danger mt-3"
           onclick="return confirm('Yakin ingin logout?')">

            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>

        <div class="sidebar-bottom">
            <i class="bi bi-shield-check"></i>
            PHP Native • MySQL
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">

            <div>
                <span class="text-muted small">
                    TOKOKU / SISTEM KASIR
                </span>

                <h5 class="mb-0">
                    <?= e($pageTitle) ?>
                </h5>
            </div>

            <div class="user-chip">

                <i class="bi bi-person-circle fs-5"></i>

                <span>
                    <?= e($namaKasir) ?>
                </span>

                <span class="badge bg-info text-dark">
                    Kasir
                </span>

                <a href="logout.php"
                   class="text-danger"
                   onclick="return confirm('Yakin ingin logout?')">

                    <i class="bi bi-box-arrow-right"></i>

                </a>

            </div>

        </header>

        <!-- CONTENT -->
        <div class="container-fluid content-area">

        <?php
        if (!empty($_SESSION["flash"])):

            $f = $_SESSION["flash"];

            unset($_SESSION["flash"]);
        ?>

            <div class="alert alert-<?= e($f["type"]) ?> alert-dismissible fade show">

                <?= e($f["message"]) ?>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>
