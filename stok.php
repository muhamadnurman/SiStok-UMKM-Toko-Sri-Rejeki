<?php
/**
 * SiStok UMKM — Stok Barang
 * Memantau ketersediaan stok semua produk aktif (baca saja).
 * Akses: admin, gudang, penjualan
 */
require 'includes/function.php';
requireLogin(['admin', 'gudang', 'penjualan']);

$user = currentUser();

// ---- Filter dan pencarian ----
$q         = trim($_GET['q'] ?? '');
$fKategori = $_GET['kategori'] ?? '';
$fStatus   = $_GET['status'] ?? '';   // aman | menipis | habis

$where  = ["p.is_active = 1"];
$params = [];
$types  = '';
if ($q !== '') {
    $where[] = "(p.nama_produk LIKE ? OR p.kode_produk LIKE ?)";
    $like = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if ($fKategori !== '') {
    $where[] = "p.kategori_id = ?";
    $params[] = (int) $fKategori;
    $types .= 'i';
}
if ($fStatus === 'habis')    $where[] = "p.stok <= 0";
if ($fStatus === 'menipis')  $where[] = "p.stok > 0 AND p.stok <= p.stok_minimum";
if ($fStatus === 'aman')     $where[] = "p.stok > p.stok_minimum";

// ---- Daftar stok produk ----
$sql = "SELECT p.*, k.nama_kategori
        FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY LENGTH(p.kode_produk), p.kode_produk";
$stmt = mysqli_prepare($conn, $sql);
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt);

// ---- Daftar kategori untuk filter ----
$kategoriList = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// ---- Ringkasan stok ----
$rs = mysqli_query($conn, "SELECT COUNT(*) AS total,
                                  SUM(CASE WHEN stok <= 0 THEN 1 ELSE 0 END) AS habis,
                                  SUM(CASE WHEN stok > 0 AND stok <= stok_minimum THEN 1 ELSE 0 END) AS menipis
                           FROM produk WHERE is_active = 1");
$sum = mysqli_fetch_assoc($rs);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stok Barang — SiStok UMKM</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/mobile.css">
</head>
<body>

<!-- ---- Sidebar ---- -->
<div class="sidebar"><?php renderNavbar('stok.php'); ?></div>

<div class="main">

    <!-- ---- Judul halaman ---- -->
    <div class="topbar">
        <div>
            <div class="page-title">Stok Barang</div>
            <div class="page-sub">Pantau ketersediaan stok seluruh produk aktif</div>
        </div>
    </div>

    <!-- ---- Kartu ringkasan ---- -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-box"></i></div>
            <div>
                <div class="stat-val"><?= (int) $sum['total'] ?></div>
                <div class="stat-label">Total Produk</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div>
                <div class="stat-val"><?= (int) $sum['menipis'] ?></div>
                <div class="stat-label">Stok Menipis</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fa-solid fa-circle-xmark"></i></div>
            <div>
                <div class="stat-val"><?= (int) $sum['habis'] ?></div>
                <div class="stat-label">Stok Habis</div>
            </div>
        </div>
    </div>

    <!-- ---- Filter dan pencarian ---- -->
    <form class="filter-bar" method="get">
        <input type="text" name="q" class="search-input" placeholder="Cari kode / nama produk..." value="<?= e($q) ?>">
        <select name="kategori" class="filter-select">
            <option value="">Semua Kategori</option>
            <?php while ($k = mysqli_fetch_assoc($kategoriList)): ?>
                <option value="<?= (int) $k['id'] ?>" <?= $fKategori == $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
            <?php endwhile; ?>
        </select>
        <select name="status" class="filter-select">
            <option value="">Semua Status</option>
            <option value="aman"    <?= $fStatus === 'aman' ? 'selected' : '' ?>>Aman</option>
            <option value="menipis" <?= $fStatus === 'menipis' ? 'selected' : '' ?>>Menipis</option>
            <option value="habis"   <?= $fStatus === 'habis' ? 'selected' : '' ?>>Habis</option>
        </select>
        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <!-- ---- Tabel stok produk ---- -->
    <div class="card">
        <div class="card-header"><div class="card-title">Daftar Stok Produk</div></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th class="col-nama">Nama Produk</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Visual</th>
                        <th>Minimum</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($rows) === 0): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px;">Tidak ada data.</td></tr>
                    <?php else: while ($p = mysqli_fetch_assoc($rows)):
                        // Lebar batang stok dan warnanya
                        $skala  = max($p['stok_minimum'] * 2, $p['stok'], 1);
                        $persen = min(100, round(($p['stok'] / $skala) * 100));
                        $warna  = $p['stok'] <= 0 ? 'var(--danger)' : ($p['stok'] <= $p['stok_minimum'] ? '#f59e0b' : '#22c55e');
                    ?>
                        <tr>
                            <td><?= e($p['kode_produk']) ?></td>
                            <td class="col-nama"><?= e($p['nama_produk']) ?></td>
                            <td><?= e($p['nama_kategori'] ?? '-') ?></td>
                            <td><?= number_format((int) $p['stok']) ?> <?= e($p['satuan']) ?></td>
                            <td style="min-width:120px">
                                <div class="stok-bar"><div class="stok-fill" style="width:<?= $persen ?>%;background:<?= $warna ?>"></div></div>
                            </td>
                            <td><?= number_format((int) $p['stok_minimum']) ?></td>
                            <td><?= stokBadge((int) $p['stok'], (int) $p['stok_minimum']) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>