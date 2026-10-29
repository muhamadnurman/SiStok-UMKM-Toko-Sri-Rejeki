<?php
/**
 * SiStok UMKM — Laporan
 * Tiga tab: penjualan, mutasi barang masuk/keluar, dan stok saat ini.
 * Laporan dapat dicetak (kop dan tanda tangan hanya muncul saat cetak).
 * Akses: admin
 */
require 'includes/function.php';
requireLogin(['admin']);

$user = currentUser();

// ---- Fungsi bantu: validasi tanggal ----
function tglValid($t, $default) {
    $d = DateTime::createFromFormat('Y-m-d', (string) $t);
    return ($d && $d->format('Y-m-d') === $t) ? $t : $default;
}

// ---- Tab dan rentang tanggal ----
$tab    = $_GET['tab'] ?? 'penjualan';
$dari   = tglValid($_GET['dari'] ?? '', date('Y-m-01'));
$sampai = tglValid($_GET['sampai'] ?? '', date('Y-m-d'));
if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

// ---- Judul dan periode laporan ----
$judulTab = [
    'penjualan' => 'Laporan Penjualan',
    'mutasi'    => 'Laporan Barang Masuk dan Keluar',
    'stok'      => 'Laporan Stok Saat Ini',
][$tab] ?? 'Laporan';

$periodeTeks = $tab === 'stok'
    ? 'Per tanggal ' . date('d/m/Y')
    : 'Periode ' . date('d/m/Y', strtotime($dari)) . ' s/d ' . date('d/m/Y', strtotime($sampai));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan — SiStok UMKM</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/mobile.css">
    <style>
        /* ---- Elemen khusus cetak (disembunyikan di layar) ---- */
        .print-only { display: none; }

        /* ---- Tampilan cetak ---- */
        @media print {
            /* Ukuran kertas dan header/footer halaman */
            @page {
                size: A4;
                margin: 15mm;

                @top-left {
                    content: "<?= date('d/m/Y, H:i') ?>";
                    font-family: 'Roboto', Arial, sans-serif;
                    font-size: 10px;
                    color: #000;
                }
                @top-center {
                    content: "Laporan — SiStok";
                    font-family: 'Roboto', Arial, sans-serif;
                    font-size: 10px;
                    color: #000;
                }
                @bottom-right {
                    content: counter(page) "/" counter(pages);
                    font-family: 'Roboto', Arial, sans-serif;
                    font-size: 10px;
                    color: #000;
                }
            }

            /* Sembunyikan elemen yang tidak perlu dicetak */
            body { background: #fff !important; color: #000; font-size: 12px; }
            .sidebar, .filter-bar, .topbar, .no-print { display: none !important; }
            .main { margin: 0 !important; padding: 0 !important; width: 100% !important; }

            /* Kop laporan */
            .print-only { display: block !important; }
            .print-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
            .print-header h2 { margin: 0; font-size: 18px; }
            .print-header p  { margin: 2px 0 0; font-size: 12px; }

            /* Kartu dan tabel */
            .card, .stat-card { box-shadow: none !important; border: 1px solid #ccc !important; break-inside: avoid; }
            .stats { display: flex; gap: 12px; margin-bottom: 12px; }
            .table-wrap { overflow: visible !important; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #999 !important; padding: 4px 6px !important; }
            thead { display: table-header-group; }   /* header tabel diulang tiap halaman */
            tr { break-inside: avoid; }

            /* Badge dan tanda tangan */
            .badge { border: 1px solid #000; background: none !important; color: #000 !important; padding: 0 4px; }
            .print-sign { margin-top: 40px; display: flex; justify-content: flex-end; }
            .print-sign div { text-align: center; width: 200px; }
            .print-sign .space { height: 60px; }

            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<!-- ---- Sidebar ---- -->
<div class="sidebar"><?php renderNavbar('laporan.php'); ?></div>

<div class="main">

    <!-- ---- Judul halaman ---- -->
    <div class="topbar">
        <div>
            <div class="page-title">Laporan</div>
            <div class="page-sub">Ringkasan penjualan, stok, dan mutasi barang</div>
        </div>
        <button type="button" class="btn btn-primary no-print" onclick="window.print()" aria-label="Cetak">
            <i class="fa-solid fa-print"></i> <span class="btn-text">Cetak</span>
        </button>
    </div>

    <!-- ---- Kop laporan (hanya tampil saat dicetak) ---- -->
    <div class="print-only print-header">
        <h2>SiStok</h2>
        <p><strong><?= e($judulTab) ?></strong></p>
        <p><?= e($periodeTeks) ?></p>
        <p style="font-size:11px">Dicetak oleh <?= e($user['nama'] ?? '-') ?> pada <?= date('d/m/Y H:i') ?></p>
    </div>

    <!-- ---- Pilihan tab ---- -->
    <div class="filter-bar tab-bar" style="margin-bottom:8px">
        <a href="?tab=penjualan&dari=<?= e($dari) ?>&sampai=<?= e($sampai) ?>" class="btn btn-sm <?= $tab === 'penjualan' ? 'btn-primary' : 'btn-outline' ?>">Penjualan</a>
        <a href="?tab=mutasi&dari=<?= e($dari) ?>&sampai=<?= e($sampai) ?>" class="btn btn-sm <?= $tab === 'mutasi' ? 'btn-primary' : 'btn-outline' ?>">Barang Masuk &amp; Keluar</a>
        <a href="?tab=stok" class="btn btn-sm <?= $tab === 'stok' ? 'btn-primary' : 'btn-outline' ?>">Stok Saat Ini</a>
    </div>

    <!-- ---- Filter tanggal ---- -->
    <?php if ($tab !== 'stok'): ?>
    <form class="filter-bar" method="get">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="date" name="dari" class="form-control" style="max-width:170px" value="<?= e($dari) ?>">
        <input type="date" name="sampai" class="form-control" style="max-width:170px" value="<?= e($sampai) ?>">
        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-filter"></i> Terapkan</button>
    </form>
    <?php endif; ?>


    <?php if ($tab === 'penjualan'):
        // ---- Data: ringkasan penjualan ----
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS jumlah, COALESCE(SUM(total), 0) AS omzet
                                       FROM transaksi WHERE DATE(tanggal) BETWEEN ? AND ?");
        mysqli_stmt_bind_param($stmt, 'ss', $dari, $sampai);
        mysqli_stmt_execute($stmt);
        $ring = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        // ---- Data: produk terlaris ----
        $stmt2 = mysqli_prepare($conn, "SELECT p.nama_produk, SUM(td.jumlah) AS qty, SUM(td.subtotal) AS total
                                        FROM transaksi_detail td
                                        JOIN transaksi t ON t.id = td.transaksi_id
                                        JOIN produk p ON p.id = td.produk_id
                                        WHERE DATE(t.tanggal) BETWEEN ? AND ?
                                        GROUP BY td.produk_id, p.nama_produk
                                        ORDER BY qty DESC LIMIT 10");
        mysqli_stmt_bind_param($stmt2, 'ss', $dari, $sampai);
        mysqli_stmt_execute($stmt2);
        $terlaris = mysqli_stmt_get_result($stmt2);

        // ---- Data: daftar transaksi ----
        $stmt3 = mysqli_prepare($conn, "SELECT t.*, u.nama AS kasir
                                        FROM transaksi t JOIN users u ON u.id = t.user_id
                                        WHERE DATE(t.tanggal) BETWEEN ? AND ?
                                        ORDER BY t.tanggal DESC LIMIT 100");
        mysqli_stmt_bind_param($stmt3, 'ss', $dari, $sampai);
        mysqli_stmt_execute($stmt3);
        $daftarTrx = mysqli_stmt_get_result($stmt3);
    ?>

        <!-- ---- Tab penjualan: kartu ringkasan ---- -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-cash-register"></i></div>
                <div>
                    <div class="stat-val"><?= (int) $ring['jumlah'] ?></div>
                    <div class="stat-label">Jumlah Transaksi</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-sack-dollar"></i></div>
                <div>
                    <div class="stat-val"><?= formatRupiah($ring['omzet']) ?></div>
                    <div class="stat-label">Total Omzet</div>
                </div>
            </div>
        </div>

        <!-- ---- Tab penjualan: produk terlaris ---- -->
        <div class="card">
            <div class="card-header"><div class="card-title">Produk Terlaris</div></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th class="col-nama">Produk</th><th>Jumlah Terjual</th><th>Total Penjualan</th></tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($terlaris) === 0): ?>
                            <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:20px">Belum ada penjualan.</td></tr>
                        <?php else: while ($r = mysqli_fetch_assoc($terlaris)): ?>
                            <tr>
                                <td class="col-nama"><?= e($r['nama_produk']) ?></td>
                                <td><?= number_format((int) $r['qty']) ?></td>
                                <td><?= formatRupiah($r['total']) ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ---- Tab penjualan: daftar transaksi ---- -->
        <div class="card">
            <div class="card-header"><div class="card-title">Daftar Transaksi</div></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>No. Transaksi</th><th>Tanggal</th><th>Total</th><th>Metode</th><th>Kasir</th></tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($daftarTrx) === 0): ?>
                            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px">Tidak ada transaksi.</td></tr>
                        <?php else: while ($t = mysqli_fetch_assoc($daftarTrx)): ?>
                            <tr>
                                <td><?= e($t['no_transaksi']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($t['tanggal'])) ?></td>
                                <td><?= formatRupiah($t['total']) ?></td>
                                <td><?= strtoupper(e($t['metode_bayar'])) ?></td>
                                <td><?= e($t['kasir']) ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


    <?php elseif ($tab === 'mutasi'):
        // ---- Data: mutasi barang masuk dan keluar ----
        $stmtM = mysqli_prepare($conn, "SELECT bm.tanggal, bm.no_transaksi, p.nama_produk, bm.jumlah, 'Masuk' AS jenis
                                        FROM barang_masuk bm JOIN produk p ON p.id = bm.produk_id
                                        WHERE bm.tanggal BETWEEN ? AND ?
                                        UNION ALL
                                        SELECT bk.tanggal, bk.no_transaksi, p.nama_produk, bk.jumlah, 'Keluar' AS jenis
                                        FROM barang_keluar bk JOIN produk p ON p.id = bk.produk_id
                                        WHERE bk.tanggal BETWEEN ? AND ?
                                        ORDER BY tanggal DESC");
        mysqli_stmt_bind_param($stmtM, 'ssss', $dari, $sampai, $dari, $sampai);
        mysqli_stmt_execute($stmtM);
        $mutasi = mysqli_stmt_get_result($stmtM);
    ?>

        <!-- ---- Tab mutasi: tabel barang masuk dan keluar ---- -->
        <div class="card">
            <div class="card-header"><div class="card-title">Mutasi Barang Masuk &amp; Keluar</div></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Tanggal</th><th>No. Transaksi</th><th class="col-nama">Produk</th><th>Jenis</th><th>Jumlah</th></tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($mutasi) === 0): ?>
                            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px">Tidak ada mutasi pada rentang ini.</td></tr>
                        <?php else: while ($m = mysqli_fetch_assoc($mutasi)): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($m['tanggal'])) ?></td>
                                <td><?= e($m['no_transaksi']) ?></td>
                                <td class="col-nama"><?= e($m['nama_produk']) ?></td>
                                <td><?= $m['jenis'] === 'Masuk' ? '<span class="badge badge-ok">Masuk</span>' : '<span class="badge badge-empty">Keluar</span>' ?></td>
                                <td><?= ($m['jenis'] === 'Masuk' ? '+' : '-') . number_format((int) $m['jumlah']) ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


    <?php else:
        // ---- Data: stok saat ini ----
        $stok = mysqli_query($conn, "SELECT p.*, k.nama_kategori
                                     FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id
                                     WHERE p.is_active = 1
                                     ORDER BY LENGTH(p.kode_produk), p.kode_produk");
    ?>

        <!-- ---- Tab stok: tabel stok saat ini ---- -->
        <div class="card">
            <div class="card-header"><div class="card-title">Stok Saat Ini</div></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Kode</th><th class="col-nama">Produk</th><th>Kategori</th><th>Stok</th><th>Minimum</th><th>Nilai Stok</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($p = mysqli_fetch_assoc($stok)): ?>
                            <tr>
                                <td><?= e($p['kode_produk']) ?></td>
                                <td class="col-nama"><?= e($p['nama_produk']) ?></td>
                                <td><?= e($p['nama_kategori'] ?? '-') ?></td>
                                <td><?= number_format((int) $p['stok']) ?> <?= e($p['satuan']) ?></td>
                                <td><?= number_format((int) $p['stok_minimum']) ?></td>
                                <td><?= formatRupiah($p['stok'] * $p['harga_beli']) ?></td>
                                <td><?= stokBadge((int) $p['stok'], (int) $p['stok_minimum']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>
</body>
</html>