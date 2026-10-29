<?php
/**
 * SiStok UMKM — Struk Penjualan
 * Menampilkan struk satu transaksi (siap cetak, lebar 80mm).
 * Akses: admin, penjualan
 */
require 'includes/function.php';
requireLogin(['admin', 'penjualan']);

// ---- Identitas toko (ganti sesuai toko) ----
const NAMA_TOKO   = 'Toko Kelontong Sri Rejeki';
const ALAMAT_TOKO = 'Jl. Sukomade, Desa Sumberagung, Kabupaten Banyuwangi, Jawa Timur';

// ---- Data transaksi ----
$id = (int) ($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT t.*, u.nama AS kasir
                               FROM transaksi t JOIN users u ON u.id = t.user_id
                               WHERE t.id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$trx = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$trx) {
    http_response_code(404);
    die('Transaksi tidak ditemukan.');
}

// ---- Rincian barang ----
$stmt = mysqli_prepare($conn, "SELECT d.*, p.nama_produk, p.satuan
                               FROM transaksi_detail d JOIN produk p ON p.id = d.produk_id
                               WHERE d.transaksi_id = ? ORDER BY d.id");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$detail = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk <?= e($trx['no_transaksi']) ?> — SiStok UMKM</title>
    <style>
        /* ---- Tampilan layar ---- */
        body    { font-family: 'Courier New', monospace; font-size: 12px; margin: 0; background: #f3f4f6; }
        .struk  { width: 80mm; margin: 16px auto; background: #fff; padding: 10px 12px; }
        .c      { text-align: center; }
        .b      { font-weight: 700; }
        hr      { border: 0; border-top: 1px dashed #000; margin: 8px 0; }
        .r      { display: flex; justify-content: space-between; gap: 8px; }

        /* ---- Tombol aksi ---- */
        .aksi   { text-align: center; margin: 12px; }
        .aksi a,
        .aksi button {
            display: inline-block;
            padding: 8px 14px;
            border: 1px solid #f97316;
            background: #f97316;
            color: #fff;
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
        }
        .aksi a { background: #fff; color: #f97316; }

        /* ---- Tampilan cetak ---- */
        @media print {
            body    { background: #fff; }
            .aksi   { display: none; }
            .struk  { width: auto; margin: 0; padding: 0; }
            @page   { margin: 4mm; }
        }
    </style>
</head>
<body>

<!-- ---- Tombol aksi ---- -->
<div class="aksi">
    <button onclick="window.print()">Cetak Struk</button>
    <a href="transaksi.php">Transaksi Baru</a>
</div>

<div class="struk">

    <!-- ---- Kop toko ---- -->
    <div class="c b" style="font-size:14px"><?= e(NAMA_TOKO) ?></div>
    <div class="c"><?= e(ALAMAT_TOKO) ?></div>
    <hr>

    <!-- ---- Info transaksi ---- -->
    <div class="r"><span>No</span><span><?= e($trx['no_transaksi']) ?></span></div>
    <div class="r"><span>Tanggal</span><span><?= date('d/m/Y H:i', strtotime($trx['tanggal'])) ?></span></div>
    <div class="r"><span>Kasir</span><span><?= e($trx['kasir']) ?></span></div>
    <hr>

    <!-- ---- Rincian barang ---- -->
    <?php while ($d = mysqli_fetch_assoc($detail)): ?>
        <div><?= e($d['nama_produk']) ?></div>
        <div class="r">
            <span><?= (int) $d['jumlah'] ?> <?= e($d['satuan']) ?> × <?= formatRupiah($d['harga_satuan']) ?></span>
            <span><?= formatRupiah($d['subtotal']) ?></span>
        </div>
    <?php endwhile; ?>
    <hr>

    <!-- ---- Total dan pembayaran ---- -->
    <div class="r b"><span>TOTAL</span><span><?= formatRupiah($trx['total']) ?></span></div>
    <div class="r"><span>Bayar (<?= e(strtoupper($trx['metode_bayar'])) ?>)</span><span><?= formatRupiah($trx['bayar']) ?></span></div>
    <div class="r"><span>Kembali</span><span><?= formatRupiah($trx['kembalian']) ?></span></div>
    <hr>

    <!-- ---- Penutup ---- -->
    <div class="c">Terima kasih atas kunjungan Anda</div>

</div>
</body>
</html>