-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 29 Okt 2026 pada 00.50
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `umkm`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `barang_keluar`
--

CREATE TABLE `barang_keluar` (
  `id` int(10) UNSIGNED NOT NULL,
  `no_transaksi` varchar(30) NOT NULL,
  `produk_id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `jumlah` int(11) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Trigger `barang_keluar`
--
DELIMITER $$
CREATE TRIGGER `trg_keluar_kurangi_stok` AFTER INSERT ON `barang_keluar` FOR EACH ROW BEGIN
    UPDATE produk SET stok = stok - NEW.jumlah WHERE id = NEW.produk_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `barang_masuk`
--

CREATE TABLE `barang_masuk` (
  `id` int(10) UNSIGNED NOT NULL,
  `no_transaksi` varchar(30) NOT NULL,
  `produk_id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_beli` decimal(12,2) NOT NULL DEFAULT 0.00,
  `supplier` varchar(150) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Trigger `barang_masuk`
--
DELIMITER $$
CREATE TRIGGER `trg_masuk_tambah_stok` AFTER INSERT ON `barang_masuk` FOR EACH ROW BEGIN
    UPDATE produk SET stok = stok + NEW.jumlah WHERE id = NEW.produk_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`) VALUES
(3, 'Sembako'),
(4, 'Sayur Mayur'),
(7, 'Alat Mandi'),
(8, 'Snack Ringan'),
(9, 'Minuman Dingin'),
(10, 'Rokok'),
(11, 'Lainnya');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode_produk` varchar(30) NOT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `kategori_id` int(10) UNSIGNED DEFAULT NULL,
  `satuan` varchar(20) NOT NULL DEFAULT 'pcs',
  `harga_beli` decimal(12,2) NOT NULL DEFAULT 0.00,
  `harga_jual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stok` int(11) NOT NULL DEFAULT 0,
  `stok_minimum` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`id`, `kode_produk`, `nama_produk`, `kategori_id`, `satuan`, `harga_beli`, `harga_jual`, `stok`, `stok_minimum`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '001', 'Beras Pandan Wangi 5kg', 3, 'Karung', 72000.00, 78000.00, 10, 10, 1, '2026-09-29 14:20:02', '2026-10-20 11:28:48'),
(2, '002', 'Minyak Goreng Bimoli 2L', 3, 'Pouch', 34000.00, 38000.00, 10, 20, 1, '2026-09-29 14:49:27', '2026-10-20 11:28:48'),
(3, '003', 'Gula Pasir Gulaku 1kg', 3, 'Kg', 16000.00, 18000.00, 80, 10, 1, '2026-10-17 15:17:54', '2026-10-18 10:24:05'),
(4, '004', 'Tepung Terigu Segitiga Biru 1kg', 3, 'Kg', 11000.00, 13000.00, 50, 10, 1, '2026-10-17 15:19:06', '2026-10-17 15:28:18'),
(5, '005', 'Telur Ayam Ras (1kg)', 3, 'Kg', 26000.00, 29000.00, 40, 10, 1, '2026-10-17 15:20:25', '2026-10-18 10:24:05'),
(6, '006', 'Bawang Merah (250g)', 4, 'Pack', 8000.00, 10000.00, 30, 10, 1, '2026-10-19 15:02:08', '2026-10-19 15:02:08'),
(11, '007', 'Bawang Putih (250g)', 4, 'Pack', 9000.00, 11500.00, 25, 10, 1, '2026-10-19 15:13:47', '2026-10-19 15:13:47'),
(12, '008', 'Cabai Rawit Merah (100g)', 4, 'Pack', 4000.00, 6000.00, 35, 5, 1, '2026-10-19 15:15:41', '2026-10-20 10:32:18'),
(14, '009', 'Tomat Segar (500g)', 4, 'Pack', 5000.00, 7000.00, 20, 5, 1, '2026-10-20 11:01:20', '2026-10-20 11:01:20'),
(15, '010', 'Wortel Lokal (500g)', 4, 'Pack', 6000.00, 8000.00, 20, 5, 1, '2026-10-20 11:02:24', '2026-10-20 11:02:24'),
(16, '011', 'Sabun Mandi Lifebuoy 110g', 7, 'Pcs', 4000.00, 5000.00, 90, 10, 1, '2026-10-20 11:03:07', '2026-10-20 11:03:07'),
(17, '012', 'Shampo Pantene 160ml', 7, 'Botol', 21000.00, 25000.00, 30, 10, 1, '2026-10-20 11:04:02', '2026-10-20 11:04:02'),
(18, '013', 'Pasta Gigi Pepsodent 190g', 7, 'Pcs', 12500.00, 15000.00, 45, 10, 1, '2026-10-20 11:04:48', '2026-10-20 11:04:48'),
(19, '014', 'Sikat Gigi Formula', 7, 'Pcs', 4500.00, 6000.00, 40, 10, 1, '2026-10-20 11:05:36', '2026-10-20 11:05:36'),
(20, '015', 'Deterjen Rinso Anti Noda 770g', 7, 'Pcs', 18500.00, 22000.00, 35, 10, 1, '2026-10-20 11:06:26', '2026-10-20 11:06:26'),
(21, '016', 'Chitato Sapi Panggang 68g', 8, 'Bungkus', 9500.00, 11500.00, 50, 10, 1, '2026-10-20 11:07:29', '2026-10-20 11:07:29'),
(22, '017', 'Indomie Goreng Original', 3, 'Bungkus', 2800.00, 3200.00, 200, 20, 1, '2026-10-20 11:10:02', '2026-10-20 11:10:02'),
(23, '018', 'Oreo Vanilla 133g', 8, 'Bungkus', 8000.00, 10000.00, 40, 10, 1, '2026-10-20 11:12:39', '2026-10-20 11:12:39'),
(24, '019', 'Kacang Garuda Atom 100g', 8, 'Bungkus', 7000.00, 8500.00, 35, 10, 1, '2026-10-20 11:13:23', '2026-10-20 11:13:23'),
(25, '020', 'Tango Wafer Cokelat 110g', 8, 'Bungkus', 6500.00, 8000.00, 45, 10, 1, '2026-10-20 11:14:00', '2026-10-20 11:14:00'),
(26, '021', 'Teh Pucuk Harum 350ml', 9, 'Botol', 3000.00, 4000.00, 120, 20, 1, '2026-10-20 11:14:40', '2026-10-20 11:14:40'),
(27, '022', 'Aqua Air Mineral 600ml', 9, 'Botol', 2500.00, 3500.00, 150, 30, 1, '2026-10-20 11:15:14', '2026-10-20 11:15:14'),
(28, '023', 'Coca Cola 390ml', 9, 'Botol', 4500.00, 6000.00, 60, 10, 1, '2026-10-20 11:15:52', '2026-10-20 11:15:52'),
(29, '024', 'Pocari Sweat 500ml', 9, 'Botol', 7000.00, 8500.00, 50, 10, 1, '2026-10-20 11:16:44', '2026-10-20 11:16:44'),
(30, '025', 'Ultra Milk Cokelat 250ml', 9, 'Pcs', 5500.00, 7000.00, 70, 10, 1, '2026-10-20 11:17:26', '2026-10-20 11:17:26'),
(31, '026', 'Sampoerna A Mild 16', 10, 'Bungkus', 31000.00, 34000.00, 80, 20, 1, '2026-10-20 11:18:06', '2026-10-20 11:18:06'),
(32, '027', 'Gudang Garam Filter 12', 10, 'Bungkus', 23000.00, 25500.00, 75, 20, 1, '2026-10-20 11:18:50', '2026-10-20 11:18:50'),
(33, '028', 'Djarum Super 12', 10, 'Bungkus', 22500.00, 25000.00, 60, 20, 1, '2026-10-20 11:19:31', '2026-10-20 11:19:31'),
(34, '029', 'Gas LPG 3kg (Isi Ulang)', 11, 'Tabung', 18000.00, 21000.00, 25, 5, 1, '2026-10-20 11:20:35', '2026-10-20 11:20:35'),
(35, '030', 'Galon Aqua (Isi Ulang)', 11, 'Galon', 17000.00, 20000.00, 30, 10, 1, '2026-10-20 11:21:09', '2026-10-20 11:21:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(10) UNSIGNED NOT NULL,
  `no_transaksi` varchar(30) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `bayar` decimal(14,2) NOT NULL DEFAULT 0.00,
  `kembalian` decimal(14,2) NOT NULL DEFAULT 0.00,
  `metode_bayar` enum('tunai','transfer','qris') NOT NULL DEFAULT 'tunai',
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi_detail`
--

CREATE TABLE `transaksi_detail` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaksi_id` int(10) UNSIGNED NOT NULL,
  `produk_id` int(10) UNSIGNED NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` decimal(12,2) NOT NULL,
  `subtotal` decimal(14,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Trigger `transaksi_detail`
--
DELIMITER $$
CREATE TRIGGER `trg_transaksi_kurangi_stok` AFTER INSERT ON `transaksi_detail` FOR EACH ROW BEGIN
    UPDATE produk SET stok = stok - NEW.jumlah WHERE id = NEW.produk_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `role` enum('admin','gudang','penjualan') NOT NULL DEFAULT 'penjualan',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama`, `role`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2b$12$EH/AAy3E.bxQm6oysSBop.iN.w6gjClNqJ2RH58yZjpU9Erb/yaei', 'Administrator', 'admin', 1, '2026-09-23 14:10:49');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `barang_keluar`
--
ALTER TABLE `barang_keluar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_transaksi` (`no_transaksi`),
  ADD KEY `fk_keluar_produk` (`produk_id`),
  ADD KEY `fk_keluar_user` (`user_id`);

--
-- Indeks untuk tabel `barang_masuk`
--
ALTER TABLE `barang_masuk`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_transaksi` (`no_transaksi`),
  ADD KEY `fk_masuk_produk` (`produk_id`),
  ADD KEY `fk_masuk_user` (`user_id`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_produk` (`kode_produk`),
  ADD KEY `fk_produk_kategori` (`kategori_id`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_transaksi` (`no_transaksi`),
  ADD KEY `fk_transaksi_user` (`user_id`);

--
-- Indeks untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_detail_transaksi` (`transaksi_id`),
  ADD KEY `fk_detail_produk` (`produk_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `barang_keluar`
--
ALTER TABLE `barang_keluar`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `barang_masuk`
--
ALTER TABLE `barang_masuk`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `barang_keluar`
--
ALTER TABLE `barang_keluar`
  ADD CONSTRAINT `fk_keluar_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_keluar_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `barang_masuk`
--
ALTER TABLE `barang_masuk`
  ADD CONSTRAINT `fk_masuk_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_masuk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD CONSTRAINT `fk_detail_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detail_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
