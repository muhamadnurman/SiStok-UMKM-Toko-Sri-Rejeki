<?php
/**
 * SiStok UMKM — Transaksi Penjualan (Kasir)
 * Memilih produk, mengisi keranjang, lalu menyimpan transaksi.
 * Harga dan stok selalu diambil dari database; stok berkurang oleh trigger.
 * Akses: admin, penjualan
 */
require 'includes/function.php';
requireLogin(['admin', 'penjualan']);

$user = currentUser();

// ---- Perlindungan CSRF (semua form POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
}

$errors = [];
$ok = '';

// ---- Simpan transaksi (checkout) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'checkout') {
    $items  = json_decode($_POST['items_json'] ?? '[]', true);
    $metode = $_POST['metode_bayar'] ?? 'tunai';
    $bayar  = (float) ($_POST['bayar'] ?? 0);

    if (!in_array($metode, ['tunai', 'transfer', 'qris'], true)) {
        $metode = 'tunai';
    }

    // Gabungkan item duplikat, buang data tidak valid
    $qtyMap = [];
    if (is_array($items)) {
        foreach ($items as $it) {
            $pid = (int) ($it['id'] ?? 0);
            $qty = (int) ($it['qty'] ?? 0);
            if ($pid > 0 && $qty > 0) {
                $qtyMap[$pid] = ($qtyMap[$pid] ?? 0) + $qty;
            }
        }
    }

    if (empty($qtyMap)) {
        $errors[] = 'Keranjang masih kosong.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $total = 0;
            $baris = [];

            // Harga dan stok diambil dari database, dikunci selama transaksi
            $stmtP = mysqli_prepare($conn, "SELECT nama_produk, harga_jual, stok FROM produk WHERE id = ? AND is_active = 1 FOR UPDATE");
            foreach ($qtyMap as $pid => $qty) {
                mysqli_stmt_bind_param($stmtP, 'i', $pid);
                mysqli_stmt_execute($stmtP);
                $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtP));

                if (!$p) {
                    throw new RuntimeException('Ada produk yang tidak ditemukan atau sudah nonaktif.');
                }
                if ($qty > (int) $p['stok']) {
                    throw new RuntimeException("Stok tidak mencukupi untuk produk: {$p['nama_produk']} (tersisa {$p['stok']}).");
                }
                $harga = (float) $p['harga_jual'];
                $sub   = $harga * $qty;
                $total += $sub;
                $baris[] = [$pid, $qty, $harga, $sub];
            }

            // Hitung pembayaran dan kembalian
            if ($metode !== 'tunai') {
                $bayar = $total;   // transfer / QRIS dianggap pas
            }
            if ($bayar < $total) {
                throw new RuntimeException('Uang diterima kurang dari total belanja.');
            }
            $kembalian = $bayar - $total;

            // Simpan header transaksi
            $no = noTransaksi('TRX');
            $stmt = mysqli_prepare($conn, "INSERT INTO transaksi (no_transaksi, total, bayar, kembalian, metode_bayar, user_id)
                                           VALUES (?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sdddsi', $no, $total, $bayar, $kembalian, $metode, $user['id']);
            mysqli_stmt_execute($stmt);
            $transaksiId = mysqli_insert_id($conn);

            // Simpan rincian (stok berkurang otomatis oleh trigger trg_transaksi_kurangi_stok)
            $stmt2 = mysqli_prepare($conn, "INSERT INTO transaksi_detail (transaksi_id, produk_id, jumlah, harga_satuan, subtotal)
                                            VALUES (?,?,?,?,?)");
            foreach ($baris as [$pid, $qty, $harga, $sub]) {
                mysqli_stmt_bind_param($stmt2, 'iiidd', $transaksiId, $pid, $qty, $harga, $sub);
                mysqli_stmt_execute($stmt2);
            }

            mysqli_commit($conn);
            header('Location: struk.php?id=' . $transaksiId);
            exit;
        } catch (RuntimeException $ex) {
            mysqli_rollback($conn);
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            error_log($ex->getMessage());
            $errors[] = 'Gagal menyimpan transaksi. Silakan coba lagi.';
        }
    }
}

// ---- Daftar produk yang bisa dijual ----
$produkList = mysqli_query($conn, "SELECT id, kode_produk, nama_produk, satuan, harga_jual, stok
                                   FROM produk WHERE is_active = 1 AND stok > 0
                                   ORDER BY LENGTH(kode_produk), kode_produk");
$produkJson = [];
while ($p = mysqli_fetch_assoc($produkList)) {
    $produkJson[] = $p;
}

// ---- Transaksi terbaru ----
$riwayat = mysqli_query($conn, "SELECT t.*, u.nama AS kasir
                                FROM transaksi t JOIN users u ON u.id = t.user_id
                                ORDER BY t.created_at DESC LIMIT 15");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaksi — SiStok UMKM</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/mobile.css">
</head>
<body>

<!-- ---- Sidebar ---- -->
<div class="sidebar"><?php renderNavbar('transaksi.php'); ?></div>

<div class="main">

    <!-- ---- Judul halaman ---- -->
    <div class="topbar">
        <div>
            <div class="page-title">Transaksi Penjualan</div>
            <div class="page-sub">Kasir — pilih produk, jumlah, lalu simpan transaksi</div>
        </div>
    </div>

    <!-- ---- Notifikasi ---- -->
    <?php if ($ok): ?><div class="notice-banner notice-ok"><?= e($ok) ?></div><?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <div class="pos-layout">

        <!-- ---- Pilih produk ---- -->
        <div class="card">
            <div class="card-header"><div class="card-title">Pilih Produk</div></div>
            <div style="padding:16px 22px">
                <input type="text" id="cariProduk" class="search-input" style="width:100%" placeholder="Cari produk...">
                <div id="daftarProduk" style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;max-height:460px;overflow-y:auto"></div>
            </div>
        </div>

        <!-- ---- Keranjang dan pembayaran ---- -->
        <div class="card">
            <div class="card-header"><div class="card-title">Keranjang</div></div>
            <div style="padding:16px 22px">
                <div id="isiKeranjang"><p class="page-sub">Keranjang masih kosong.</p></div>
                <div class="total-line grand"><span>Total</span><span id="totalBelanja">Rp0</span></div>

                <div class="form-group" style="margin-top:14px">
                    <label>Metode Pembayaran</label>
                    <select id="metodeBayar" class="form-control">
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="qris">QRIS</option>
                    </select>
                </div>

                <div class="form-group" id="boxBayar">
                    <label>Uang Diterima</label>
                    <input type="number" id="uangDiterima" class="form-control" min="0" step="any" oninput="hitungKembalian()">
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px" id="quickCash"></div>
                </div>
                <div class="total-line"><span>Kembalian</span><span id="txtKembali">Rp0</span></div>

                <form method="post" id="formCheckout">
                    <?= csrfField() ?>
                    <input type="hidden" name="aksi" value="checkout">
                    <input type="hidden" name="bayar" id="bayarHidden">
                    <input type="hidden" name="items_json" id="itemsJson">
                    <input type="hidden" name="metode_bayar" id="metodeBayarHidden">
                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center" id="btnCheckout" disabled>
                        <i class="fa-solid fa-cash-register"></i> Simpan Transaksi
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ---- Tabel transaksi terbaru ---- -->
    <div class="card" style="margin-top:20px">
        <div class="card-header"><div class="card-title">Transaksi Terbaru</div></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>No. Transaksi</th><th>Tanggal</th><th>Total</th><th>Metode</th><th>Kasir</th><th>Struk</th></tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($riwayat) === 0): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px;">Belum ada transaksi.</td></tr>
                    <?php else: while ($t = mysqli_fetch_assoc($riwayat)): ?>
                        <tr>
                            <td><?= e($t['no_transaksi']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($t['tanggal'])) ?></td>
                            <td><?= formatRupiah($t['total']) ?></td>
                            <td><span class="badge badge-info"><?= strtoupper(e($t['metode_bayar'])) ?></span></td>
                            <td><?= e($t['kasir']) ?></td>
                            <td>
                                <a class="btn btn-outline btn-sm" href="struk.php?id=<?= (int) $t['id'] ?>" target="_blank" aria-label="Cetak struk">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ---- Script ---- -->
<script>
// ---- Data awal ----
const PRODUK = <?= json_encode($produkJson) ?>;
let keranjang = {};   // id -> {id, nama, harga, satuan, stok, qty}

// ---- Fungsi bantu ----
function esc(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
}
function formatRp(v) {
    return 'Rp' + Number(v).toLocaleString('id-ID');
}
function totalBelanja() {
    return Object.values(keranjang).reduce((t, it) => t + it.harga * it.qty, 0);
}

// ---- Daftar produk ----
function renderProduk(filter = '') {
    const wrap = document.getElementById('daftarProduk');
    wrap.innerHTML = '';
    const f = filter.toLowerCase();
    PRODUK.filter(p => p.nama_produk.toLowerCase().includes(f) || p.kode_produk.toLowerCase().includes(f))
        .forEach(p => {
            const div = document.createElement('div');
            div.className = 'card';
            div.style.cssText = 'padding:12px;cursor:pointer;margin-bottom:0';
            div.innerHTML = `<div style="font-weight:700;font-size:13px;color:var(--text)">${esc(p.nama_produk)}</div>
                <div style="font-size:12px;color:var(--muted);margin:4px 0">${formatRp(p.harga_jual)} / ${esc(p.satuan)}</div>
                <div style="font-size:11px;color:var(--muted)">Stok: ${p.stok}</div>`;
            div.onclick = () => tambahKeranjang(p);
            wrap.appendChild(div);
        });
}

// ---- Keranjang belanja ----
function tambahKeranjang(p) {
    if (!keranjang[p.id]) {
        if (p.stok < 1) return;
        keranjang[p.id] = { id: p.id, nama: p.nama_produk, harga: parseFloat(p.harga_jual), satuan: p.satuan, stok: p.stok, qty: 1 };
    } else if (keranjang[p.id].qty < p.stok) {
        keranjang[p.id].qty++;
    }
    renderKeranjang();
}

function ubahQty(id, delta) {
    const item = keranjang[id];
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) delete keranjang[id];
    else if (item.qty > item.stok) item.qty = item.stok;
    renderKeranjang();
}

function renderKeranjang() {
    const wrap = document.getElementById('isiKeranjang');
    const ids  = Object.keys(keranjang);

    if (ids.length === 0) {
        wrap.innerHTML = '<p class="page-sub">Keranjang masih kosong.</p>';
        document.getElementById('btnCheckout').disabled = true;
        document.getElementById('totalBelanja').textContent = formatRp(0);
        hitungKembalian();
        return;
    }

    let total = 0;
    wrap.innerHTML = ids.map(id => {
        const it = keranjang[id];
        const subtotal = it.harga * it.qty;
        total += subtotal;
        return `<div class="cart-row">
            <div>
                <div class="cart-name">${esc(it.nama)}</div>
                <div class="cart-sub">${formatRp(it.harga)} x ${it.qty} = ${formatRp(subtotal)}</div>
            </div>
            <div class="qty-control">
                <button type="button" onclick="ubahQty(${id},-1)">-</button>
                <span>${it.qty}</span>
                <button type="button" onclick="ubahQty(${id},1)">+</button>
            </div>
        </div>`;
    }).join('');

    document.getElementById('totalBelanja').textContent = formatRp(total);
    hitungKembalian();
}

// ---- Pembayaran dan kembalian ----
function hitungKembalian() {
    const total = totalBelanja();
    const tunai = document.getElementById('metodeBayar').value === 'tunai';
    document.getElementById('boxBayar').style.display = tunai ? '' : 'none';

    const bayar   = tunai ? (parseFloat(document.getElementById('uangDiterima').value) || 0) : total;
    const kembali = bayar - total;
    const el = document.getElementById('txtKembali');
    el.textContent = kembali >= 0 ? formatRp(kembali) : 'Kurang ' + formatRp(-kembali);
    el.style.color = kembali >= 0 ? '' : '#dc2626';

    // Tombol uang cepat (uang pas + pecahan umum)
    const opsi = [total, 20000, 50000, 100000].filter((v, i) => v > 0 && (i === 0 || v >= total));
    document.getElementById('quickCash').innerHTML = (tunai && total > 0)
        ? opsi.map((v, i) => `<button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('uangDiterima').value=${v};hitungKembalian()">${i === 0 ? 'Uang pas' : formatRp(v)}</button>`).join('')
        : '';

    // Tombol simpan hanya aktif jika keranjang ada isi dan uang cukup
    document.getElementById('btnCheckout').disabled = total <= 0 || bayar < total;
}

// ---- Event ----
document.getElementById('cariProduk').addEventListener('input', (e) => renderProduk(e.target.value));
document.getElementById('metodeBayar').addEventListener('change', hitungKembalian);

document.getElementById('formCheckout').addEventListener('submit', function (e) {
    const items = Object.values(keranjang).map(it => ({ id: it.id, qty: it.qty }));
    if (items.length === 0) { e.preventDefault(); return; }

    const metode = document.getElementById('metodeBayar').value;
    document.getElementById('itemsJson').value = JSON.stringify(items);
    document.getElementById('metodeBayarHidden').value = metode;
    document.getElementById('bayarHidden').value = metode === 'tunai'
        ? (document.getElementById('uangDiterima').value || 0)
        : totalBelanja();

    // Cegah klik ganda (tombol dinonaktifkan setelah data terisi)
    setTimeout(() => { document.getElementById('btnCheckout').disabled = true; }, 0);
});

// ---- Tampilan awal ----
renderProduk();
hitungKembalian();
</script>
</body>
</html>