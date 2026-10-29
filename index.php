<?php
/**
 * SiStok UMKM — Pintu masuk aplikasi
 * Mengarahkan ke dashboard (halaman lain akan meminta login bila belum masuk).
 */
header('Location: dashboard.php');
exit;