<?php
/**
 * SiStok UMKM — Data Produk
 * Tambah / edit produk, tambah stok cepat, dan aktif / nonaktifkan produk.
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

// ---- Simpan produk (tambah / edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $id            = (int) ($_POST['id'] ?? 0);
    $kode_produk   = trim($_POST['kode_produk'] ?? '');
    $nama_produk   = trim($_POST['nama_produk'] ?? '');
    $kategori_id   = $_POST['kategori_id'] ?? '';
    $kategori_baru = trim($_POST['kategori_baru'] ?? '');
    $satuan        = formatSatuan($_POST['satuan'] ?? 'Pcs');
    $harga_beli    = parseHarga($_POST['harga_beli'] ?? 0);
    $harga_jual    = parseHarga($_POST['harga_jual'] ?? 0);
    $stok_minimum  = (int) ($_POST['stok_minimum'] ?? 0);
    $stok_awal     = (int) ($_POST['stok_awal'] ?? 0);

    // Validasi input
    if ($kode_produk === '' || $nama_produk === '') {
        $errors[] = 'Kode produk dan nama produk wajib diisi.';
    }
    if ($stok_awal < 0 || $stok_minimum < 0) {
        $errors[] = 'Stok tidak boleh negatif.';
    }
    if ($harga_jual <= 0) {
        $errors[] = 'Harga jual harus lebih dari 0.';
    }

    // Kategori baru (bila dipilih); nama yang sudah ada dipakai ulang agar tidak dobel
    if ($kategori_id === '__new__') {
        if ($kategori_baru === '') {
            $errors[] = 'Nama kategori baru wajib diisi.';
        } else {
            $kategori_baru = mb_convert_case($kategori_baru, MB_CASE_TITLE, 'UTF-8');
            $cek = mysqli_prepare($conn, "SELECT id FROM kategori WHERE LOWER(nama_kategori) = LOWER(?) LIMIT 1");
            mysqli_stmt_bind_param($cek, 's', $kategori_baru);
            mysqli_stmt_execute($cek);
            if ($ada = mysqli_fetch_assoc(mysqli_stmt_get_result($cek))) {
                $kategori_id = (int) $ada['id'];
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO kategori (nama_kategori) VALUES (?)");
                mysqli_stmt_bind_param($stmt, 's', $kategori_baru);
                mysqli_stmt_execute($stmt);
                $kategori_id = mysqli_insert_id($conn);
            }
        }
    }
    $kategori_id = $kategori_id !== '' ? (int) $kategori_id : null;

    // Simpan ke database
    if (empty($errors)) {
        try {
            if ($id > 0) {
                // Edit produk (stok tidak diubah di sini)
                $stmt = mysqli_prepare($conn, "UPDATE produk
                                               SET kode_produk=?, nama_produk=?, kategori_id=?, satuan=?, harga_beli=?, harga_jual=?, stok_minimum=?
                                               WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssisddii', $kode_produk, $nama_produk, $kategori_id, $satuan, $harga_beli, $harga_jual, $stok_minimum, $id);
                mysqli_stmt_execute($stmt);
                $_SESSION['flash_ok'] = 'Produk berhasil diperbarui.';
            } else {
                // Produk baru
                $stmt = mysqli_prepare($conn, "INSERT INTO produk (kode_produk, nama_produk, kategori_id, satuan, harga_beli, harga_jual, stok, stok_minimum)
                                               VALUES (?,?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($stmt, 'ssisddii', $kode_produk, $nama_produk, $kategori_id, $satuan, $harga_beli, $harga_jual, $stok_awal, $stok_minimum);
                mysqli_stmt_execute($stmt);
                $_SESSION['flash_ok'] = 'Produk baru berhasil ditambahkan.';
            }
            header('Location: produk.php');
            exit;
        } catch (mysqli_sql_exception $ex) {
            if ((int) $ex->getCode() === 1062) {
                $errors[] = "Kode produk '" . $kode_produk . "' sudah dipakai. Gunakan kode lain.";
            } else {
                error_log($ex->getMessage());
                $errors[] = 'Gagal menyimpan produk. Periksa kembali isian Anda.';
            }
        }
    }
}

// ---- Tambah stok cepat (dicatat sebagai Barang Masuk) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_stok') {
    $id     = (int) ($_POST['id'] ?? 0);
    $jumlah = (int) ($_POST['jumlah'] ?? 0);
    $ket    = trim($_POST['keterangan'] ?? '');
    if ($ket === '') {
        $ket = 'Tambah stok dari Data Produk';
    }

    if ($id <= 0 || $jumlah <= 0) {
        $errors[] = 'Jumlah tambahan stok harus lebih dari 0.';
    } else {
        try {
            // Ambil harga beli terakhir produk
            $cek = mysqli_prepare($conn, "SELECT harga_beli FROM produk WHERE id = ?");
            mysqli_stmt_bind_param($cek, 'i', $id);
            mysqli_stmt_execute($cek);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

            if ($row) {
                // Catat barang masuk (stok bertambah otomatis oleh trigger)
                $no  = noTransaksi('MSK');
                $tgl = date('Y-m-d');
                $hb  = (float) $row['harga_beli'];
                $sup = '';
                $stmt = mysqli_prepare($conn, "INSERT INTO barang_masuk (no_transaksi, produk_id, tanggal, jumlah, harga_beli, supplier, keterangan, user_id)
                                               VALUES (?,?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($stmt, 'sisidssi', $no, $id, $tgl, $jumlah, $hb, $sup, $ket, $user['id']);
                mysqli_stmt_execute($stmt);

                $_SESSION['flash_ok'] = "Stok berhasil ditambah $jumlah ($no).";
                header('Location: produk.php');
                exit;
            } else {
                $errors[] = 'Produk tidak ditemukan.';
            }
        } catch (mysqli_sql_exception $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Gagal menambah stok.';
        }
    }
}

// ---- Aktif / nonaktifkan produk ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'toggle') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "UPDATE produk SET is_active = 1 - is_active WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $_SESSION['flash_ok'] = 'Status produk berhasil diubah.';
    header('Location: produk.php');
    exit;
}

// ---- Daftar kategori untuk dropdown ----
$kategoriList = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// ---- Filter dan pencarian ----
$q         = trim($_GET['q'] ?? '');
$fKategori = $_GET['kategori'] ?? '';
$fStatus   = $_GET['status'] ?? '';

$where  = [];
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
if ($fStatus !== '') {
    $where[] = "p.is_active = ?";
    $params[] = (int) $fStatus;
    $types .= 'i';
}

// ---- Daftar produk (urut kode secara numerik) ----
$sql = "SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY LENGTH(p.kode_produk), p.kode_produk";
$stmt = mysqli_prepare($conn, $sql);
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$produkList = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Produk — SiStok UMKM</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/mobile.css">
</head>
<body>

<!-- ---- Sidebar ---- -->
<div class="sidebar"><?php renderNavbar('produk.php'); ?></div>

<div class="main">

    <!-- ---- Judul halaman ---- -->
    <div class="topbar">
        <div>
            <div class="page-title">Data Produk</div>
            <div class="page-sub">Kelola daftar produk, kategori, dan harga</div>
        </div>
        <button class="btn btn-primary" onclick="bukaModalTambah()" aria-label="Tambah Produk">
            <i class="fa-solid fa-plus"></i> <span class="btn-text">Tambah Produk</span>
        </button>
    </div>

    <!-- ---- Notifikasi ---- -->
    <?php if ($ok): ?><div class="notice-banner notice-ok"><?= e($ok) ?></div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <!-- ---- Filter dan pencarian ---- -->
    <form class="filter-bar" method="get">
        <input type="text" name="q" class="search-input" placeholder="Cari kode / nama produk..." value="<?= e($q) ?>">
        <select name="kategori" class="filter-select">
            <option value="">Semua Kategori</option>
            <?php mysqli_data_seek($kategoriList, 0); while ($k = mysqli_fetch_assoc($kategoriList)): ?>
                <option value="<?= (int) $k['id'] ?>" <?= $fKategori == $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
            <?php endwhile; ?>
        </select>
        <select name="status" class="filter-select">
            <option value="">Semua Status</option>
            <option value="1" <?= $fStatus === '1' ? 'selected' : '' ?>>Aktif</option>
            <option value="0" <?= $fStatus === '0' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <!-- ---- Tabel daftar produk ---- -->
    <div class="card">
        <div class="card-header"><div class="card-title">Daftar Produk</div></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th class="col-nama">Nama Produk</th>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th>Harga Beli</th>
                        <th>Harga Jual</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($produkList) === 0): ?>
                        <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:24px;">Belum ada produk.</td></tr>
                    <?php else: while ($p = mysqli_fetch_assoc($produkList)): ?>
                        <tr>
                            <td><?= e($p['kode_produk']) ?></td>
                            <td class="col-nama"><?= e($p['nama_produk']) ?></td>
                            <td><?= e($p['nama_kategori'] ?? '-') ?></td>
                            <td><?= e($p['satuan']) ?></td>
                            <td><?= formatRupiah($p['harga_beli']) ?></td>
                            <td><?= formatRupiah($p['harga_jual']) ?></td>
                            <td><?= number_format((int) $p['stok']) ?></td>
                            <td><?= $p['is_active'] ? '<span class="badge badge-ok">Aktif</span>' : '<span class="badge badge-off">Nonaktif</span>' ?></td>
                            <td>
                                <!-- Edit produk -->
                                <button class="btn btn-sm btn-outline" type="button" title="Edit Produk" aria-label="Edit Produk"
                                        onclick='bukaModalEdit(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'>
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <!-- Tambah stok -->
                                <button class="btn btn-sm btn-approve" type="button" title="Tambah Stok" aria-label="Tambah Stok"
                                        onclick='bukaModalStok(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'>
                                    <i class="fa-solid fa-circle-plus"></i>
                                </button>
                                <!-- Aktif / nonaktif -->
                                <form method="post" style="display:inline" onsubmit="return confirm('Ubah status produk ini?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="aksi" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button class="btn btn-sm <?= $p['is_active'] ? 'btn-danger' : 'btn-approve' ?>" type="submit"
                                            title="<?= $p['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>" aria-label="Ubah Status">
                                        <i class="fa-solid <?= $p['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ---- Modal tambah / edit produk ---- -->
<div class="modal-overlay" id="modalProduk">
    <div class="modal-box">
        <div class="modal-title" id="modalTitle">Tambah Produk</div>
        <div class="modal-sub">Lengkapi data produk di bawah ini.</div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" id="f_id" value="">

            <!-- Kode dan satuan -->
            <div class="form-row">
                <div class="form-group">
                    <label>Kode Produk</label>
                    <input type="text" name="kode_produk" id="f_kode" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Satuan</label>
                    <input type="text" name="satuan" id="f_satuan" class="form-control input-satuan" list="daftarSatuan" placeholder="Pilih / ketik satuan" autocomplete="off" required>
                    <datalist id="daftarSatuan">
                        <option value="Botol">
                        <option value="Bungkus">
                        <option value="Galon">
                        <option value="Karung">
                        <option value="Kg">
                        <option value="Pack">
                        <option value="Pcs">
                        <option value="Pouch">
                        <option value="Tabung">
                    </datalist>
                </div>
            </div>

            <!-- Nama produk -->
            <div class="form-group">
                <label>Nama Produk</label>
                <input type="text" name="nama_produk" id="f_nama" class="form-control" required>
            </div>

            <!-- Kategori -->
            <div class="form-group">
                <label>Kategori</label>
                <select name="kategori_id" id="f_kategori" class="form-control" onchange="toggleKategoriBaru(this.value)">
                    <option value="">- Tanpa Kategori -</option>
                    <?php mysqli_data_seek($kategoriList, 0); while ($k = mysqli_fetch_assoc($kategoriList)): ?>
                        <option value="<?= (int) $k['id'] ?>"><?= e($k['nama_kategori']) ?></option>
                    <?php endwhile; ?>
                    <option value="__new__">+ Kategori baru...</option>
                </select>
            </div>
            <div class="form-group" id="wrapKategoriBaru" style="display:none">
                <label>Nama Kategori Baru</label>
                <input type="text" name="kategori_baru" id="f_kategori_baru" class="form-control">
            </div>

            <!-- Harga -->
            <div class="form-row">
                <div class="form-group">
                    <label>Harga Beli</label>
                    <input type="text" inputmode="numeric" autocomplete="off" name="harga_beli" id="f_hbeli" class="form-control input-rupiah" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Harga Jual</label>
                    <input type="text" inputmode="numeric" autocomplete="off" name="harga_jual" id="f_hjual" class="form-control input-rupiah" placeholder="0">
                </div>
            </div>

            <!-- Stok -->
            <div class="form-row">
                <div class="form-group" id="wrapStokAwal">
                    <label>Stok Awal</label>
                    <input type="text" inputmode="numeric" autocomplete="off" name="stok_awal" id="f_stok_awal" class="form-control input-angka" value="0">
                </div>
                <div class="form-group">
                    <label>Stok Minimum</label>
                    <input type="text" inputmode="numeric" autocomplete="off" name="stok_minimum" id="f_stok_min" class="form-control input-angka" value="0">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="tutupModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ---- Modal tambah stok ---- -->
<div class="modal-overlay" id="modalStok">
    <div class="modal-box">
        <div class="modal-title">Tambah Stok</div>
        <div class="modal-sub" id="stokInfo"></div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="aksi" value="tambah_stok">
            <input type="hidden" name="id" id="s_id" value="">
            <div class="form-group">
                <label>Jumlah yang ditambahkan</label>
                <input type="number" name="jumlah" id="s_jumlah" class="form-control" min="1" inputmode="numeric" required>
            </div>
            <div class="form-group">
                <label>Keterangan (opsional)</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Belanja dari supplier">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="tutupModalStok()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ---- Script ---- -->
<script src="assets/harga.js"></script>
<script>
// ---- Modal tambah produk ----
function bukaModalTambah() {
    document.getElementById('modalTitle').textContent = 'Tambah Produk';
    document.getElementById('f_id').value = '';
    document.getElementById('f_kode').value = '';
    document.getElementById('f_nama').value = '';
    document.getElementById('f_satuan').value = '';
    document.getElementById('f_kategori').value = '';
    setRupiah(document.getElementById('f_hbeli'), 0);
    setRupiah(document.getElementById('f_hjual'), 0);
    document.getElementById('f_stok_awal').value = 0;
    document.getElementById('f_stok_min').value = 0;
    document.getElementById('wrapStokAwal').style.display = '';
    toggleKategoriBaru('');
    document.getElementById('modalProduk').classList.add('show');
}

// ---- Modal edit produk ----
function bukaModalEdit(p) {
    document.getElementById('modalTitle').textContent = 'Edit Produk';
    document.getElementById('f_id').value = p.id;
    document.getElementById('f_kode').value = p.kode_produk;
    document.getElementById('f_nama').value = p.nama_produk;
    document.getElementById('f_satuan').value = p.satuan;
    document.getElementById('f_kategori').value = p.kategori_id || '';
    setRupiah(document.getElementById('f_hbeli'), p.harga_beli);
    setRupiah(document.getElementById('f_hjual'), p.harga_jual);
    document.getElementById('f_stok_min').value = p.stok_minimum;
    document.getElementById('wrapStokAwal').style.display = 'none';
    toggleKategoriBaru('');
    document.getElementById('modalProduk').classList.add('show');
}

// ---- Modal tambah stok ----
function bukaModalStok(p) {
    document.getElementById('s_id').value = p.id;
    document.getElementById('stokInfo').textContent = p.nama_produk + ' — stok sekarang ' + p.stok + ' ' + p.satuan;
    document.getElementById('s_jumlah').value = '';
    document.getElementById('modalStok').classList.add('show');
    document.getElementById('s_jumlah').focus();
}

// ---- Tutup modal ----
function tutupModal() {
    document.getElementById('modalProduk').classList.remove('show');
}
function tutupModalStok() {
    document.getElementById('modalStok').classList.remove('show');
}

// ---- Kolom kategori baru ----
function toggleKategoriBaru(val) {
    document.getElementById('wrapKategoriBaru').style.display = (val === '__new__') ? '' : 'none';
}
</script>
</body>
</html>