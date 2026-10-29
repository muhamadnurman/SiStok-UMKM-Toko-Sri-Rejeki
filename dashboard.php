<?php
/**
 * SiStok UMKM — Dashboard
 * Ringkasan stok, omzet hari ini, dan daftar produk yang perlu restok.
 * Akses: admin, gudang, penjualan
 */
require 'includes/function.php';
requireLogin(['admin', 'gudang', 'penjualan']);

$user = currentUser();

// ---- Sapaan dan tanggal ----
$jam = (int) date('H');
if ($jam < 11)      $salam = 'Selamat Pagi';
elseif ($jam < 15)  $salam = 'Selamat Siang';
elseif ($jam < 18)  $salam = 'Selamat Sore';
else                $salam = 'Selamat Malam';

$namaHari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tanggalIndo = $namaHari[(int) date('w')] . ', ' . date('j') . ' ' . $namaBulan[(int) date('n')] . ' ' . date('Y');

// ---- Ringkasan stok ----
$rs = mysqli_query($conn, "SELECT COUNT(*) AS total_produk,
                                  COALESCE(SUM(stok), 0) AS total_stok,
                                  COALESCE(SUM(stok * harga_beli), 0) AS nilai_stok
                           FROM produk WHERE is_active = 1");
$ringkasStok = mysqli_fetch_assoc($rs);

// ---- Transaksi hari ini ----
$rt = mysqli_query($conn, "SELECT COUNT(*) AS jumlah_transaksi, COALESCE(SUM(total), 0) AS omzet
                           FROM transaksi WHERE DATE(tanggal) = CURDATE()");
$transaksiHariIni = mysqli_fetch_assoc($rt);

// ---- Jumlah produk stok menipis (stok <= stok minimum) ----
$rc = mysqli_query($conn, "SELECT COUNT(*) AS n FROM produk WHERE is_active = 1 AND stok <= stok_minimum");
$jumlahMenipis = (int) mysqli_fetch_assoc($rc)['n'];

// ---- Daftar produk stok menipis ----
$rp = mysqli_query($conn, "SELECT id, kode_produk, nama_produk, satuan, stok, stok_minimum
                           FROM produk
                           WHERE is_active = 1 AND stok <= stok_minimum
                           ORDER BY stok ASC, nama_produk ASC
                           LIMIT 10");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — SiStok UMKM</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/mobile.css">
</head>
<body>

<!-- ---- Sidebar ---- -->
<div class="sidebar"><?php renderNavbar('dashboard.php'); ?></div>

<div class="main">

    <!-- ---- Notifikasi ---- -->
    <?php if (($_GET['notice'] ?? '') === 'akses_ditolak'): ?>
        <div class="alert-error">Anda tidak memiliki akses ke halaman tersebut.</div>
    <?php endif; ?>

    <!-- ---- Kartu sambutan ---- -->
    <div class="welcome-card">
        <div class="welcome-text">
            <h1><?= $salam ?>, <span><?= e($user['nama']) ?></span>!</h1>
            <h2>Selamat datang di Dashboard SiStok</h2>
            <p><?= $tanggalIndo ?> &middot; Anda masuk sebagai <?= e(roleLabel($user['role'])) ?></p>
        </div>
    </div>

    <!-- ---- Kartu ringkasan ---- -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-box"></i></div>
            <div>
                <div class="stat-val"><?= number_format((int) $ringkasStok['total_produk']) ?></div>
                <div class="stat-label">Total Produk Aktif</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-warehouse"></i></div>
            <div>
                <div class="stat-val"><?= number_format((int) $ringkasStok['total_stok']) ?></div>
                <div class="stat-label">Total Stok Barang</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber"><i class="fa-solid fa-sack-dollar"></i></div>
            <div>
                <div class="stat-val"><?= formatRupiah($ringkasStok['nilai_stok']) ?></div>
                <div class="stat-label">Nilai Stok (Harga Beli)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-cash-register"></i></div>
            <div>
                <div class="stat-val"><?= number_format((int) $transaksiHariIni['jumlah_transaksi']) ?></div>
                <div class="stat-label">Transaksi Hari Ini</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-chart-line"></i></div>
            <div>
                <div class="stat-val"><?= formatRupiah($transaksiHariIni['omzet']) ?></div>
                <div class="stat-label">Omzet Hari Ini</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div>
                <div class="stat-val"><?= $jumlahMenipis ?></div>
                <div class="stat-label">Produk Perlu Restok</div>
            </div>
        </div>
    </div>

    <!-- ---- Tabel produk stok menipis ---- -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Produk Stok Menipis</div>
            <a href="stok.php" class="btn btn-sm btn-primary">Lihat Semua Stok</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th class="col-nama">Nama Produk</th>
                        <th>Stok</th>
                        <th>Stok Minimum</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($rp) === 0): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px;">Semua stok produk aman.</td></tr>
                    <?php else: while ($p = mysqli_fetch_assoc($rp)): ?>
                        <tr>
                            <td><?= e($p['kode_produk']) ?></td>
                            <td class="col-nama"><?= e($p['nama_produk']) ?></td>
                            <td><?= number_format((int) $p['stok']) ?> <?= e($p['satuan']) ?></td>
                            <td><?= number_format((int) $p['stok_minimum']) ?> <?= e($p['satuan']) ?></td>
                            <td>
                                <?php if ((int) $p['stok'] <= 0): ?>
                                    <span class="badge badge-empty">Habis</span>
                                <?php else: ?>
                                    <span class="badge badge-low">Menipis</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>