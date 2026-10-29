<?php
date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrfField(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}
function csrfCheck(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        die('Sesi formulir tidak valid. Muat ulang halaman.');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';


function requireLogin(array $roles = []): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['role'], $roles, true)) {
        header('Location: dashboard.php?notice=akses_ditolak');
        exit;
    }
}

function currentUser(): array
{
    return [
        'id'    => $_SESSION['user_id']  ?? null,
        'nama'  => $_SESSION['nama']     ?? '-',
        'role'  => $_SESSION['role']     ?? '-',
    ];
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatRupiah($amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

function roleLabel(string $role): string
{
    $labels = [
        'admin'      => 'Admin / Pemilik',
        'gudang'     => 'Gudang',
        'penjualan'  => 'Penjualan / Kasir',
    ];
    return $labels[$role] ?? ucfirst($role);
}

/**
 * Nomor transaksi unik per jenis dokumen, mis. noTransaksi('MSK') -> MSK-20260924-AB12CD
 */
function noTransaksi(string $prefix): string
{
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Badge status stok, dipakai di stok.php & dashboard.php
 */
function stokBadge(int $stok, int $minimum): string
{
    if ($stok <= 0) {
        return '<span class="badge badge-empty">Habis</span>';
    }
    if ($stok <= $minimum) {
        return '<span class="badge badge-low">Menipis</span>';
    }
    return '<span class="badge badge-ok">Aman</span>';
}

/**
 * Sidebar navigasi yang dipakai di semua halaman. $active = nama halaman
 * yang sedang dibuka (dipakai untuk highlight menu).
 */
function renderNavbar(string $active = ''): void
{
    $user = currentUser();
    $menus = [
        ['label' => 'Dashboard',       'file' => 'dashboard.php',  'icon' => 'fa-gauge-high',      'roles' => ['admin', 'gudang', 'penjualan']],
        ['label' => 'Data Produk',     'file' => 'produk.php',     'icon' => 'fa-box',             'roles' => ['admin', 'gudang']],
        ['label' => 'Stok Barang',     'file' => 'stok.php',       'icon' => 'fa-warehouse',       'roles' => ['admin', 'gudang', 'penjualan']],
        ['label' => 'Barang Masuk',    'file' => 'masuk.php',      'icon' => 'fa-arrow-down',      'roles' => ['admin', 'gudang']],
        ['label' => 'Barang Keluar',   'file' => 'keluar.php',     'icon' => 'fa-arrow-up',        'roles' => ['admin', 'gudang']],
        ['label' => 'Transaksi',       'file' => 'transaksi.php',  'icon' => 'fa-cash-register',   'roles' => ['admin', 'penjualan']],
        ['label' => 'Laporan',         'file' => 'laporan.php',    'icon' => 'fa-chart-line',      'roles' => ['admin']],
    ];
    ?>
    <div class="sidebar-header">
        
        <div>
            <div class="brand-name">SiStok </div>
            <span class="dept-badge" style="background:rgba(255,255,255,.12);color:#fff;">UMKM</span>
        </div>
    </div>
    <div class="user-info">
        <div class="user-avatar"><?= e(mb_strtoupper(mb_substr($user['nama'], 0, 1))) ?></div>
        <div>
            <div class="user-nobed"><?= e($user['nama']) ?></div>
            <div class="user-dept"><?= e(roleLabel($user['role'])) ?></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menus as $m): ?>
            <?php if (!in_array($user['role'], $m['roles'], true)) continue; ?>
            <a class="nav-item <?= $active === $m['file'] ? 'active' : '' ?>" href="<?= e($m['file']) ?>" title="<?= e($m['label']) ?>">
                <i class="fa-solid <?= e($m['icon']) ?>"></i> <span><?= e($m['label']) ?></span>
            </a>
        <?php endforeach; ?>
        <a class="nav-item logout" href="logout.php" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i> <span>Keluar</span></a>
    </nav>
    <?php
}

