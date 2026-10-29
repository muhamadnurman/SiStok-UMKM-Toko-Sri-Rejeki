<?php
/**
 * SiStok UMKM — Barang Keluar
 * Mencatat barang keluar di luar penjualan (rusak, retur, dll).
 * Stok berkurang otomatis oleh trigger database.
 * Akses: admin, gudang
 */
require 'includes/function.php';
requireLogin(['admin', 'gudang']);

$user = currentUser();

// ---- Perlindungan CSRF (semua form POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
}

$errors = [];
$ok = $_SESSION['flash_ok'] ?? '';
unset($_SESSION['flash_ok']);

// ---- Simpan barang keluar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produk_id = (int) ($_POST['produk_id'] ?? 0);
    $tanggal   = $_POST['tanggal'] ?? '';
    $jumlah    = (int) ($_POST['jumlah'] ?? 0);
    $ket       = trim($_POST['keterangan'] ?? '');

    // Validasi input
    if ($produk_id <= 0) $errors[] = 'Pilih produk terlebih dahulu.';
    if ($jumlah <= 0)    $errors[] = 'Jumlah harus lebih dari 0.';
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$d || $d->format('Y-m-d') !== $tanggal) $errors[] = 'Tanggal tidak valid.';

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            // Cek stok (dikunci selama transaksi)
            $cek = mysqli_prepare($conn, "SELECT stok FROM produk WHERE id = ? AND is_active = 1 FOR UPDATE");
            mysqli_stmt_bind_param($cek, 'i', $produk_id);
            mysqli_stmt_execute($cek);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));
            if (!$row) {
                throw new RuntimeException('Produk tidak ditemukan atau sudah nonaktif.');
            }
            if ($jumlah > (int) $row['stok']) {
                throw new RuntimeException("Jumlah melebihi stok tersedia (stok saat ini: {$row['stok']}).");
            }

            // Simpan (stok berkurang otomatis oleh trigger)
            $no = noTransaksi('KLR');
            $stmt = mysqli_prepare($conn, "INSERT INTO barang_keluar (no_transaksi, produk_id, tanggal, jumlah, keterangan, user_id)
                                           VALUES (?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sisisi', $no, $produk_id, $tanggal, $jumlah, $ket, $user['id']);
            mysqli_stmt_execute($stmt);

            mysqli_commit($conn);
            $_SESSION['flash_ok'] = "Barang keluar berhasil dicatat ($no).";
            header('Location: keluar.php');
            exit;
        } catch (RuntimeException $ex) {
            mysqli_rollback($conn);
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            error_log($ex->getMessage());
            $errors[] = 'Gagal menyimpan data barang keluar. Silakan coba lagi.';
        }
    }
}

// ---- Daftar produk untuk dropdown ----
$produkList = mysqli_query($conn, "SELECT id, kode_produk, nama_produk, satuan, stok
                                   FROM produk WHERE is_active = 1
                                   ORDER BY LENGTH(kode_produk), kode_produk");

// ---- Riwayat barang keluar (filter tanggal) ----
$dari   = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$stmt = mysqli_prepare($conn, "SELECT bk.*, p.nama_produk, p.kode_produk, p.satuan, u.nama AS nama_user
                               FROM barang_keluar bk
                               JOIN produk p ON p.id = bk.produk_id
                               JOIN users u ON u.id = bk.user_id
                               WHERE bk.tanggal BETWEEN ? AND ?
                               ORDER BY bk.created_at DESC LIMIT 100");
mysqli_stmt_bind_param($stmt, 'ss', $dari, $sampai);
mysqli_stmt_execute($stmt);
$riwayat = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barang Keluar — SiStok UMKM</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/mobile.css">
</head>
<body>

<!-- ---- Sidebar ---- -->
<div class="sidebar"><?php renderNavbar('keluar.php'); ?></div>

<div class="main">

    <!-- ---- Judul halaman ---- -->
    <div class="topbar">
        <div>
            <div class="page-title">Barang Keluar</div>
            <div class="page-sub">Catat barang keluar di luar transaksi penjualan (rusak, retur, dll)</div>
        </div>
        <button class="btn btn-primary" onclick="bukaModal()" aria-label="Catat Barang Keluar">
            <i class="fa-solid fa-plus"></i> <span class="btn-text">Catat Barang Keluar</span>
        </button>
    </div>

    <!-- ---- Notifikasi ---- -->
    <?php if ($ok): ?><div class="notice-banner notice-ok"><?= e($ok) ?></div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <!-- ---- Filter tanggal ---- -->
    <form class="filter-bar" method="get">
        <div class="form-group" style="margin:0"><input type="date" name="dari" class="form-control" value="<?= e($dari) ?>"></div>
        <div class="form-group" style="margin:0"><input type="date" name="sampai" class="form-control" value="<?= e($sampai) ?>"></div>
        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-filter"></i> Filter Tanggal</button>
    </form>

    <!-- ---- Tabel riwayat ---- -->
    <div class="card">
        <div class="card-header"><div class="card-title">Riwayat Barang Keluar</div></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No. Transaksi</th>
                        <th>Tanggal</th>
                        <th class="col-nama">Produk</th>
                        <th>Jumlah</th>
                        <th>Keterangan</th>
                        <th>Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($riwayat) === 0): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px;">Belum ada data pada rentang tanggal ini.</td></tr>
                    <?php else: while ($r = mysqli_fetch_assoc($riwayat)): ?>
                        <tr>
                            <td><?= e($r['no_transaksi']) ?></td>
                            <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                            <td class="col-nama"><?= e($r['nama_produk']) ?> <span style="color:var(--muted)">(<?= e($r['kode_produk']) ?>)</span></td>
                            <td>-<?= number_format((int) $r['jumlah']) ?> <?= e($r['satuan']) ?></td>
                            <td><?= e($r['keterangan'] ?: '-') ?></td>
                            <td><?= e($r['nama_user']) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ---- Modal catat barang keluar ---- -->
<div class="modal-overlay" id="modalKeluar">
    <div class="modal-box">
        <div class="modal-title">Catat Barang Keluar</div>
        <div class="modal-sub">Stok produk akan otomatis berkurang setelah disimpan.</div>
        <form method="post">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Produk</label>
                <select name="produk_id" id="pilihProduk" class="form-control" required onchange="tampilStok()">
                    <option value="">- Pilih Produk -</option>
                    <?php mysqli_data_seek($produkList, 0); while ($p = mysqli_fetch_assoc($produkList)): ?>
                        <option value="<?= (int) $p['id'] ?>" data-stok="<?= (int) $p['stok'] ?>" data-satuan="<?= e($p['satuan']) ?>">
                            <?= e($p['kode_produk']) ?> — <?= e($p['nama_produk']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="page-sub" id="infoStok" style="margin-top:6px"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Jumlah</label>
                    <input type="number" name="jumlah" class="form-control" min="1" required>
                </div>
            </div>
            <div class="form-group">
                <label>Keterangan</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Rusak / Retur / dll">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="tutupModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ---- Script ---- -->
<script>
// ---- Buka dan tutup modal ----
function bukaModal() {
    document.getElementById('modalKeluar').classList.add('show');
}
function tutupModal() {
    document.getElementById('modalKeluar').classList.remove('show');
}

// ---- Tampilkan stok produk yang dipilih ----
function tampilStok() {
    const sel    = document.getElementById('pilihProduk');
    const opt    = sel.options[sel.selectedIndex];
    const stok   = opt.dataset.stok || 0;
    const satuan = opt.dataset.satuan || '';
    document.getElementById('infoStok').textContent = opt.value ? ('Stok tersedia: ' + stok + ' ' + satuan) : '';
}
</script>
</body>
</html>