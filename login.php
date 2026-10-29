<?php
/**
 * SiStok UMKM — Halaman Login
 * Memeriksa username dan password, lalu membuat sesi pengguna.
 */
require 'includes/function.php';

// ---- Sudah login: langsung ke dashboard ----
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error  = null;
$sukses = null;

// ---- Proses login ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, nama, password, role FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['role']    = $user['role'];
            $sukses = $user['nama'];
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — SiStok UMKM</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">

    <!-- ---- Hiasan gelombang ---- -->
    <svg class="login-wave" viewBox="0 0 1440 320" preserveAspectRatio="none">
        <path fill="#1e3a8a" d="M0,160L48,176C96,192,192,224,288,213.3C384,203,480,149,576,144C672,139,768,181,864,197.3C960,213,1056,203,1152,176C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        <path fill="#f97316" fill-opacity="0.9" d="M0,256L48,229.3C96,203,192,149,288,149.3C384,149,480,203,576,213.3C672,224,768,192,864,170.7C960,149,1056,139,1152,149.3C1248,160,1344,192,1392,208L1440,224L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
    </svg>

    <div class="login-card-wrap">
        <div class="login-box">

            <!-- ---- Logo dan judul ---- -->
            <div class="login-head">
                <div class="login-logo">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2 2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <h2 class="login-title">SiStok</h2>
                <p class="login-sub">Sistem Monitoring Stok dan Transaksi Toko Kelontong UMKM</p>
            </div>

            <!-- ---- Form login ---- -->
            <form method="post">
                <div class="login-field">
                    <label class="login-label" for="username">Username</label>
                    <div class="login-input-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        <input class="login-input" type="text" id="username" name="username" placeholder="Masukkan username" required autofocus>
                    </div>
                </div>

                <div class="login-field">
                    <label class="login-label" for="password">Password</label>
                    <div class="login-input-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input class="login-input" type="password" id="password" name="password" placeholder="Masukkan password Anda" required>
                    </div>
                </div>

                <button type="submit" class="login-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Masuk Sistem
                </button>
            </form>

        </div>
    </div>
</div>

<!-- ---- Script: pesan berhasil / gagal ---- -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if ($sukses): ?>
    Swal.fire({
        title: 'Login Berhasil!',
        text: <?= json_encode('Selamat datang, ' . $sukses . '!') ?>,
        icon: 'success',
        confirmButtonText: 'Lanjutkan',
        confirmButtonColor: '#f97316',
        allowOutsideClick: false
    }).then(() => { window.location.href = 'index.php'; });
<?php elseif ($error): ?>
    Swal.fire({
        title: 'Login Gagal!',
        text: <?= json_encode($error) ?>,
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#f97316'
    });
<?php endif; ?>
</script>
</body>
</html>