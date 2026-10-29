<?php
/**
 * SiStok UMKM — Keluar (logout)
 * Menghapus sesi lalu kembali ke halaman login.
 */
require 'includes/function.php';

// ---- Hapus sesi ----
$_SESSION = [];
session_destroy();

// ---- Kembali ke login ----
header('Location: login.php');
exit;