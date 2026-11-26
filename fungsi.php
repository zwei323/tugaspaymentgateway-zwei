<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Koneksi database pelanggan
require_once __DIR__ . "/koneksi.php";

// Fungsi umum dari folder utama Tokoku
require_once __DIR__ . "/../functions.php";

// =============================
// CSRF TOKEN
// =============================
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf" value="' .
            e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check() {
        if (!hash_equals(
            $_SESSION['csrf'] ?? '',
            $_POST['csrf'] ?? ''
        )) {
            http_response_code(419);
            die("Sesi tidak valid. Silakan kembali dan coba lagi.");
        }
    }
}

// =============================
// KERANJANG
// =============================
if (!function_exists('cart_count')) {
    function cart_count() {
        return array_sum($_SESSION['cart'] ?? []);
    }
}

// =============================
// URL KEMBALI
// =============================
if (!function_exists('back_url')) {
    function back_url() {
        $u = basename($_SERVER['PHP_SELF']);

        if (!empty($_SERVER['QUERY_STRING'])) {
            $u .= '?' . $_SERVER['QUERY_STRING'];
        }

        return $u;
    }
}

if (!function_exists('safe_back')) {
    function safe_back($b, $default = 'keranjang.php') {
        return preg_match(
            '/^[a-z_]+\.php(\?[\w=&%.+-]*)?$/i',
            (string)$b
        ) ? $b : $default;
    }
}

// =============================
// LOGIN PELANGGAN
// =============================
if (!function_exists('user_login_check')) {
    function user_login_check(): bool {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('wajib_login')) {
    function wajib_login() {
        if (!user_login_check()) {
            flash(
                "Silakan masuk atau daftar dulu untuk melanjutkan.",
                "warning"
            );

            header("Location: masuk.php?next=" . urlencode(back_url()));
            exit;
        }
    }
}

// =============================
// DATA KERANJANG
// =============================
if (!function_exists('cart_items')) {
    function cart_items(mysqli $conn): array {
        $cart = $_SESSION['cart'] ?? [];

        if (!$cart) {
            return [[], 0.0];
        }

        $ids = implode(',', array_map('intval', array_keys($cart)));

        $res = $conn->query("
            SELECT p.*, k.nama_kategori
            FROM tbl_produk p
            LEFT JOIN tbl_kategori k
                ON k.id_kategori = p.id_kategori
            WHERE p.id_produk IN ($ids)
        ");

        $items = [];
        $total = 0.0;

        while ($r = $res->fetch_assoc()) {
            $qty = (int)($cart[$r['id_produk']] ?? 0);

            $r['qty'] = $qty;
            $r['subtotal'] = (float)$r['harga'] * $qty;
            $r['kurang'] = $qty > (int)$r['stok'];

            $total += $r['subtotal'];
            $items[] = $r;
        }

        return [$items, $total];
    }
}

// =============================
// GAMBAR PRODUK
// =============================
if (!function_exists('gambar_produk')) {
    function gambar_produk(array $p): ?string {
        if (empty($p['gambar'])) {
            return null;
        }

        $f = basename($p['gambar']);

        $lokasi = __DIR__ . '/../uploads/produk/' . $f;

        if (is_file($lokasi)) {
            return '../uploads/produk/' . rawurlencode($f);
        }

        return null;
    }
}

// =============================
// ICON KATEGORI
// =============================
if (!function_exists('ikon_kategori')) {
    function ikon_kategori($id) {
        $ikon = [
            1 => 'bi-basket2',
            2 => 'bi-cup-straw',
            3 => 'bi-house-gear'
        ];

        return $ikon[(int)$id] ?? 'bi-box-seam';
    }
}

// =============================
// KARTU PRODUK
// =============================
if (!function_exists('kartu_produk')) {
    function kartu_produk(array $p): string {
        $habis = (int)$p['stok'] <= 0;

        ob_start();
        ?>

        <div class="col-6 col-md-4 col-lg-3">
            <div class="card h-100 produk-card">

                <div class="produk-img">
                    <?php if ($g = gambar_produk($p)): ?>

                        <img
                            src="<?= e($g) ?>"
                            alt="<?= e($p['nama_produk']) ?>"
                            loading="lazy"
                        >

                    <?php else: ?>

                        <i class="bi <?= e(ikon_kategori($p['id_kategori'])) ?>"></i>

                    <?php endif; ?>
                </div>

                <div class="card-body d-flex flex-column">

                    <span class="badge text-bg-light align-self-start mb-2">
                        <?= e($p['nama_kategori'] ?? 'Lainnya') ?>
                    </span>

                    <h6 class="card-title mb-1">
                        <?= e($p['nama_produk']) ?>
                    </h6>

                    <div class="fw-bold text-primary mb-1">
                        <?= rupiah($p['harga']) ?>
                    </div>

                    <div class="small mb-3 <?= $habis ? 'text-danger' : 'text-muted' ?>">
                        <?= $habis
                            ? 'Stok habis'
                            : ((int)$p['stok'] <= 10
                                ? 'Sisa ' . (int)$p['stok']
                                : 'Stok tersedia')
                        ?>
                    </div>

                    <form method="post" action="keranjang.php" class="mt-auto">

                        <?= csrf_field() ?>

                        <input type="hidden" name="aksi" value="tambah">
                        <input type="hidden" name="id" value="<?= (int)$p['id_produk'] ?>">
                        <input type="hidden" name="back" value="<?= e(back_url()) ?>">

                        <button
                            class="btn btn-primary btn-sm w-100"
                            <?= $habis ? 'disabled' : '' ?>
                        >
                            <i class="bi bi-cart-plus"></i>
                            Tambah ke Keranjang
                        </button>

                    </form>

                </div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }
}

// =============================
// OPSI PENGIRIMAN
// =============================
if (!function_exists('opsi_pengiriman')) {
    function opsi_pengiriman(): array {
        return [
            'ambil' => [
                'nama' => 'Ambil di toko',
                'ongkir' => 0,
                'alamat' => false
            ],

            'kurir' => [
                'nama' => 'Kurir toko',
                'ongkir' => 10000,
                'alamat' => true
            ],

            'ekspres' => [
                'nama' => 'Ekspres (same-day)',
                'ongkir' => 20000,
                'alamat' => true
            ]
        ];
    }
}

if (!function_exists('label_pengiriman')) {
    function label_pengiriman(array $o): string {
        return $o['nama'] .
            ($o['ongkir'] > 0
                ? ' (' . rupiah($o['ongkir']) . ')'
                : ' (gratis)');
    }
}

// =============================
// OPSI PEMBAYARAN
// =============================
if (!function_exists('opsi_pembayaran')) {
    function opsi_pembayaran(): array {
        return [
            'transfer' => 'Transfer bank',
            'cod' => 'Bayar di tempat (COD)',
            'ewallet' => 'E-wallet / QRIS'
        ];
    }
}

// =============================
// VOUCHER
// =============================
if (!function_exists('daftar_voucher')) {
    function daftar_voucher(): array {
        return [
            'HEMAT10' => [
                'persen' => 10,
                'min' => 0,
                'maks' => 0
            ]
        ];
    }
}

if (!function_exists('hitung_voucher')) {
    function hitung_voucher(string $kode, float $subtotal): array {
        $kode = strtoupper(trim($kode));

        $v = daftar_voucher()[$kode] ?? null;

        if (!$v) {
            return [
                'ok' => false,
                'diskon' => 0.0,
                'pesan' => 'Kode voucher tidak valid.'
            ];
        }

        if ($subtotal < $v['min']) {
            return [
                'ok' => false,
                'diskon' => 0.0,
                'pesan' => 'Minimal belanja ' .
                    rupiah($v['min']) .
                    ' untuk voucher ini.'
            ];
        }

        $d = $subtotal * $v['persen'] / 100;

        if ($v['maks'] > 0) {
            $d = min($d, $v['maks']);
        }

        $d = floor($d);

        return [
            'ok' => true,
            'diskon' => (float)$d,
            'pesan' => "Voucher $kode dipakai: potongan " .
                rupiah($d) . "."
        ];
    }
}