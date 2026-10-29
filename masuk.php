<?php
/**
 * SiStok UMKM — Barang Masuk
 * Mencatat penerimaan barang dari supplier.
 * Stok bertambah otomatis oleh trigger database.
 * Akses: admin, gudang
 */
require 'includes/function.php';
require 'includes/harga.php';
requireLogin(['admin', 'gudang']);

$user = currentUser();

// ---- Perlindungan CSRF (semua form POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
}

$errors = [];
$ok = $_SESSION['flash_ok'] ?? '';
unset($_SESSION['flash_ok']);

// ---- Simpan barang masuk ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produk_id = (int) ($_POST['produk_id'] ?? 0);
    $tanggal   = $_POST['tanggal'] ?? '';
    $jumlah    = (int) ($_POST['jumlah'] ?? 0);
    $harga     = parseHarga($_POST['harga_beli'] ?? 0);
    $supplier  = trim($_POST['supplier'] ?? '');
    $ket       = trim($_POST['keterangan'] ?? '');

    // Validasi input
    if ($produk_id <= 0) $errors[] = 'Pilih produk terlebih dahulu.';
    if ($jumlah <= 0)    $errors[] = 'Jumlah harus lebih dari 0.';
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$d || $d->format('Y-m-d') !== $tanggal) $errors[] = 'Tanggal tidak valid.';

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            // Simpan (stok bertambah otomatis oleh trigger)
            $no = noTransaksi('MSK');
            $stmt = mysqli_prepare($conn, "INSERT INTO barang_masuk (no_transaksi, produk_id, tanggal, jumlah, harga_beli, supplier, keterangan, user_id)
                                           VALUES (?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sisidssi', $no, $produk_id, $tanggal, $jumlah, $harga, $supplier, $ket, $user['id']);
            mysqli_stmt_execute($stmt);

            // Harga beli produk hanya diperbarui bila harga diisi
            if ($harga > 0) {
                $up = mysqli_prepare($conn, "UPDATE produk SET harga_beli = ? WHERE id = ?");
                mysqli_stmt_bind_param($up, 'di', $harga, $produk_id);
                mysqli_stmt_execute($up);
            }

            mysqli_commit($conn);
            $_SESSION['flash_ok'] = "Barang masuk berhasil dicatat ($no).";
            header('Location: masuk.php');
            exit;
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            error_log($ex->getMessage());
            $errors[] = 'Gagal menyimpan data barang masuk. Silakan coba lagi.';
        }
    }
}

// ---- Daftar produk untuk dropdown ----
$produkList = mysqli_query($conn, "SELECT id, kode_produk, nama_produk, satuan, harga_beli
                                   FROM produk WHERE is_active = 1
                                   ORDER BY LENGTH(kode_produk), kode_produk");

// ---- Riwayat barang masuk (filter tanggal) ----
$dari   = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$stmt = mysqli_prepare($conn, "SELECT bm.*, p.nama_produk, p.kode_produk, p.satuan, u.nama AS nama_user
                               FROM barang_masuk bm
                               JOIN produk p ON p.id = bm.produk_id
                               JOIN users u ON u.id = bm.user_id
                               WHERE bm.tanggal BETWEEN ? AND ?
                               ORDER BY bm.created_at DESC LIMIT 100");
mysqli_stmt_bind_param($stmt, 'ss', $dari, $sampai);
mysqli_stmt_execute($stmt);
$riwayat = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barang Masuk — SiStok UMKM</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/mobile.css">
</head>
<body>

<!-- ---- Sidebar ---- -->
<div class="sidebar"><?php renderNavbar('masuk.php'); ?></div>

<div class="main">

    <!-- ---- Judul halaman ---- -->
    <div class="topbar">
        <div>
            <div class="page-title">Barang Masuk</div>
            <div class="page-sub">Catat penerimaan barang dari supplier untuk menambah stok</div>
        </div>
        <button class="btn btn-primary" onclick="bukaModal()" aria-label="Catat Barang Masuk">
            <i class="fa-solid fa-plus"></i> <span class="btn-text">Catat Barang Masuk</span>
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
        <div class="card-header"><div class="card-title">Riwayat Barang Masuk</div></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No. Transaksi</th>
                        <th>Tanggal</th>
                        <th class="col-nama">Produk</th>
                        <th>Jumlah</th>
                        <th>Harga Beli</th>
                        <th>Supplier</th>
                        <th>Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($riwayat) === 0): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px;">Belum ada data pada rentang tanggal ini.</td></tr>
                    <?php else: while ($r = mysqli_fetch_assoc($riwayat)): ?>
                        <tr>
                            <td><?= e($r['no_transaksi']) ?></td>
                            <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                            <td class="col-nama"><?= e($r['nama_produk']) ?> <span style="color:var(--muted)">(<?= e($r['kode_produk']) ?>)</span></td>
                            <td>+<?= number_format((int) $r['jumlah']) ?> <?= e($r['satuan']) ?></td>
                            <td><?= formatRupiah($r['harga_beli']) ?></td>
                            <td><?= e($r['supplier'] ?: '-') ?></td>
                            <td><?= e($r['nama_user']) ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ---- Modal catat barang masuk ---- -->
<div class="modal-overlay" id="modalMasuk">
    <div class="modal-box">
        <div class="modal-title">Catat Barang Masuk</div>
        <div class="modal-sub">Stok produk akan otomatis bertambah setelah disimpan.</div>
        <form method="post">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Produk</label>
                <select name="produk_id" id="pilihProduk" class="form-control" required onchange="isiHargaBeli()">
                    <option value="">- Pilih Produk -</option>
                    <?php mysqli_data_seek($produkList, 0); while ($p = mysqli_fetch_assoc($produkList)): ?>
                        <option value="<?= (int) $p['id'] ?>" data-harga="<?= (float) $p['harga_beli'] ?>">
                            <?= e($p['kode_produk']) ?> — <?= e($p['nama_produk']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
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
                <label>Harga Beli / Satuan</label>
                <input type="text" inputmode="numeric" autocomplete="off" name="harga_beli" id="hargaBeli" class="form-control input-rupiah" placeholder="0">
            </div>
            <div class="form-group">
                <label>Supplier</label>
                <input type="text" name="supplier" class="form-control">
            </div>
            <div class="form-group">
                <label>Keterangan</label>
                <input type="text" name="keterangan" class="form-control">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="tutupModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ---- Script ---- -->
<script src="assets/harga.js"></script>
<script>
// ---- Buka dan tutup modal ----
function bukaModal() {
    document.getElementById('modalMasuk').classList.add('show');
}
function tutupModal() {
    document.getElementById('modalMasuk').classList.remove('show');
}

// ---- Isi harga beli otomatis dari produk yang dipilih ----
function isiHargaBeli() {
    const sel = document.getElementById('pilihProduk');
    const opt = sel.options[sel.selectedIndex];
    setRupiah(document.getElementById('hargaBeli'), opt.dataset.harga || 0);
}
</script>
</body>
</html>