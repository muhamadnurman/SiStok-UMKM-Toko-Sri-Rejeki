<?php

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'umkm';

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$conn) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');

date_default_timezone_set('Asia/Jakarta');