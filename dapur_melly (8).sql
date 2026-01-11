-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 09, 2026 at 02:11 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dapur_melly`
--

-- --------------------------------------------------------

--
-- Table structure for table `karyawan`
--

CREATE TABLE `karyawan` (
  `id_karyawan` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  `gaji_pokok` decimal(12,2) NOT NULL,
  `lama_bekerja` int(11) DEFAULT NULL COMMENT 'dalam bulan',
  `posisi` varchar(50) DEFAULT NULL,
  `tgl_masuk` date DEFAULT NULL,
  `tgl_gajian_rutin` tinyint(4) DEFAULT NULL COMMENT 'contoh: 25',
  `tgl_gajian` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `karyawan`
--

INSERT INTO `karyawan` (`id_karyawan`, `user_id`, `alamat`, `no_telp`, `gaji_pokok`, `lama_bekerja`, `posisi`, `tgl_masuk`, `tgl_gajian_rutin`, `tgl_gajian`) VALUES
(4, 3, NULL, '081234567890', 2000000.00, 76, 'Baker', '2025-12-21', 26, NULL),
(5, 23, NULL, '6285161869428', 30000000.00, 12, 'Baker', '2025-12-22', 5, NULL),
(11, 28, NULL, '6285161869428', 5000000.00, 0, 'Dekor', '2025-12-25', 25, NULL),
(15, 20, NULL, '', 0.00, NULL, 'Admin', NULL, NULL, NULL),
(16, 30, '', '6285157997271', 2000000.00, NULL, 'Baker ', '2026-01-02', 7, NULL),
(17, 31, NULL, '6283806722867', 10000.00, NULL, 'Baker', '2026-01-08', 8, NULL),
(18, 32, NULL, '6285171014107', 1000000.00, NULL, 'Baker ', '2026-01-08', 8, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `laporan_bulanan`
--

CREATE TABLE `laporan_bulanan` (
  `id` int(11) NOT NULL,
  `bulan` varchar(2) NOT NULL,
  `tahun` varchar(4) NOT NULL,
  `pendapatan` decimal(15,2) DEFAULT 0.00,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `laporan_bulanan`
--

INSERT INTO `laporan_bulanan` (`id`, `bulan`, `tahun`, `pendapatan`, `updated_by`, `updated_at`) VALUES
(1, '12', '2025', 65000000.00, 0, '2025-12-26 09:34:47'),
(3, '01', '2026', 40000000.00, 0, '2026-01-02 06:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`id`, `user_id`, `login_time`, `ip_address`, `device_info`) VALUES
(1, 30, '2026-01-07 07:10:25', '::1', 'Absen Masuk via Dashboard - Posisi: Baker ');

-- --------------------------------------------------------

--
-- Table structure for table `pendapatan_harian`
--

CREATE TABLE `pendapatan_harian` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `bulan` varchar(2) NOT NULL,
  `tahun` varchar(4) NOT NULL,
  `offline` decimal(15,2) NOT NULL DEFAULT 0.00,
  `online` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_harian` decimal(15,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pendapatan_harian`
--

INSERT INTO `pendapatan_harian` (`id`, `tanggal`, `bulan`, `tahun`, `offline`, `online`, `total_harian`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2025-12-20', '12', '2025', 5000000.00, 3000000.00, 8000000.00, 'Hari ramai weekend', 20, '2025-12-26 09:28:31', '2025-12-26 09:28:31'),
(2, '2025-12-21', '12', '2025', 4500000.00, 2500000.00, 7000000.00, 'Hari biasa', 20, '2025-12-26 09:28:31', '2025-12-26 09:28:31'),
(3, '2025-12-22', '12', '2025', 6000000.00, 4000000.00, 10000000.00, 'Ada pesanan katering kecil', 20, '2025-12-26 09:28:31', '2025-12-26 09:28:31'),
(4, '2025-12-26', '12', '2025', 10000000.00, 30000000.00, 40000000.00, 'diinput', 0, '2025-12-26 09:34:47', '2025-12-26 09:34:47'),
(5, '2026-01-02', '01', '2026', 10000000.00, 30000000.00, 40000000.00, '', 0, '2026-01-02 06:29:07', '2026-01-02 06:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `penggajian`
--

CREATE TABLE `penggajian` (
  `id` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `bulan` tinyint(4) NOT NULL,
  `tahun` year(4) NOT NULL,
  `tunjangan` decimal(12,2) DEFAULT 0.00,
  `potongan` decimal(12,2) DEFAULT 0.00,
  `gaji_bersih` decimal(12,2) NOT NULL,
  `status_bayar` enum('lunas','belum') DEFAULT 'belum',
  `tgl_bayar_aktual` date DEFAULT NULL,
  `status` enum('Belum','Lunas') DEFAULT 'Belum'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `penggajian`
--

INSERT INTO `penggajian` (`id`, `id_karyawan`, `bulan`, `tahun`, `tunjangan`, `potongan`, `gaji_bersih`, `status_bayar`, `tgl_bayar_aktual`, `status`) VALUES
(4, 4, 12, '2025', 200000.00, 100000.00, 2100000.00, 'belum', NULL, 'Belum'),
(5, 5, 12, '2025', 100000.00, 200000.00, 2400000.00, 'lunas', '2025-12-22', 'Belum'),
(8, 11, 12, '2025', 1000000.00, 0.00, 6000000.00, 'lunas', '2025-12-25', 'Belum'),
(11, 16, 1, '2026', 0.00, 0.00, 2000000.00, 'lunas', '2026-01-07', 'Belum'),
(12, 11, 1, '2026', 0.00, 0.00, 5000000.00, 'lunas', '2026-01-07', 'Belum'),
(13, 5, 1, '2026', 0.00, 0.00, 30000000.00, 'lunas', '2026-01-07', 'Belum'),
(14, 4, 1, '2026', 0.00, 0.00, 2000000.00, 'lunas', '2026-01-07', 'Belum'),
(15, 17, 1, '2026', 100000.00, 110000.00, 0.00, 'lunas', '2026-01-08', 'Belum'),
(16, 18, 1, '2026', 1000000.00, 0.00, 2000000.00, 'lunas', '2026-01-08', 'Belum');

-- --------------------------------------------------------

--
-- Table structure for table `pesan_sistem`
--

CREATE TABLE `pesan_sistem` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `tujuan_role` varchar(20) DEFAULT 'admin',
  `tujuan_user_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pesan_sistem`
--

INSERT INTO `pesan_sistem` (`id`, `sender_id`, `sender_name`, `message`, `tujuan_role`, `tujuan_user_id`, `is_read`, `created_at`) VALUES
(1, 2, 'Admin Dapur Melly', 'tes', 'owner', NULL, 0, '2025-12-28 08:08:29'),
(2, 20, 'Admin Dapur Melly', 'halo owner', 'owner', NULL, 0, '2026-01-02 06:21:20');

-- --------------------------------------------------------

--
-- Table structure for table `presensi`
--

CREATE TABLE `presensi` (
  `presensi_id` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `status` enum('masuk','izin','sakit','alpa') NOT NULL DEFAULT 'masuk',
  `jam_masuk` datetime DEFAULT NULL,
  `keterangan_lembur` text DEFAULT NULL,
  `jam_mulai_lembur` time DEFAULT NULL,
  `jam_selesai_lembur` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `presensi`
--

INSERT INTO `presensi` (`presensi_id`, `id`, `id_karyawan`, `tanggal`, `status`, `jam_masuk`, `keterangan_lembur`, `jam_mulai_lembur`, `jam_selesai_lembur`) VALUES
(1, 0, 16, '2026-01-06', 'masuk', NULL, NULL, NULL, NULL),
(2, 0, 16, '2026-01-07', 'masuk', NULL, 'Terlambat 1 jam 10 menit', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `produk_unggulan`
--

CREATE TABLE `produk_unggulan` (
  `id` int(11) NOT NULL,
  `nama_produk` varchar(100) NOT NULL,
  `jumlah_terjual` int(11) NOT NULL,
  `foto` varchar(255) NOT NULL,
  `posisi` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `produk_unggulan`
--

INSERT INTO `produk_unggulan` (`id`, `nama_produk`, `jumlah_terjual`, `foto`, `posisi`) VALUES
(1, 'Cheese Stik', 120, '1766149919_cheesestik.jpg', 1),
(2, 'Chiffon Cheese', 100, '1766149937_chiffon.jpg', 2),
(3, 'Asinan Kuah Lemon', 80, '1766149955_asinan.jpg', 3);

-- --------------------------------------------------------

--
-- Table structure for table `system_parameters`
--

CREATE TABLE `system_parameters` (
  `id` int(11) NOT NULL,
  `param_key` varchar(50) NOT NULL,
  `param_value` text DEFAULT NULL,
  `param_label` varchar(100) DEFAULT NULL,
  `param_type` varchar(20) DEFAULT 'text',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_parameters`
--

INSERT INTO `system_parameters` (`id`, `param_key`, `param_value`, `param_label`, `param_type`, `created_at`, `updated_at`) VALUES
(1, 'uang_makan', '20000', 'Uang Makan per Hari', 'number', '2026-01-07 14:11:02', '2026-01-07 14:11:02'),
(2, 'potongan_terlambat', '10000', 'Potongan Terlambat per Kejadian', 'number', '2026-01-07 14:11:02', '2026-01-07 14:24:09'),
(3, 'jam_masuk_baker', '07:00', 'Jam Masuk Baker', 'time', '2026-01-07 14:11:02', '2026-01-07 14:11:02'),
(4, 'jam_masuk_umum', '08:00', 'Jam Masuk Umum', 'time', '2026-01-07 14:11:02', '2026-01-07 14:11:02'),
(5, 'target_warning_percent', '80', 'Persentase Warning Target', 'number', '2026-01-07 14:11:02', '2026-01-07 14:11:02');

-- --------------------------------------------------------

--
-- Table structure for table `target_pendapatan`
--

CREATE TABLE `target_pendapatan` (
  `id` int(11) NOT NULL,
  `bulan` varchar(2) NOT NULL,
  `tahun` varchar(4) NOT NULL,
  `target_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `target_pendapatan`
--

INSERT INTO `target_pendapatan` (`id`, `bulan`, `tahun`, `target_amount`, `created_at`, `updated_at`) VALUES
(1, '01', '2025', 10000000.00, '2025-12-28 08:08:01', '2025-12-28 08:08:01'),
(2, '01', '2026', 100000000.00, '2026-01-02 06:20:54', '2026-01-02 06:20:54');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','owner','user') NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT 'default.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `nama_lengkap`, `email`, `last_login`, `foto_profil`, `created_at`, `updated_at`) VALUES
(2, 'Owner Dapur Melly', '$2y$10$Gc7gLNh.b3nzoHRdGZdEeuL4zDREZxAsTboJJl83jQYJbut4v05s.', 'owner', 'Owner Dapur Melly', '', NULL, 'default.png', '2025-12-25 03:52:27', '2025-12-28 08:43:56'),
(3, 'karyawan1', 'user123', 'user', 'Siti Aminah', NULL, NULL, 'default.png', '2025-12-25 03:52:27', '2025-12-25 03:52:27'),
(20, 'admin', '$2y$10$quWmu7AfBh2jdwkupfQXVOpHrYx5MdEoAGz42fe0E83EGtng5kdkW', 'admin', 'Admin Dapur Melly', '', NULL, 'profile_20_1766406235.jpeg', '2025-12-25 03:52:27', '2026-01-02 06:19:24'),
(23, 'putri', '$2y$10$Wpxqf0b.vqmWmUVd4GF49OumpymPcHdqwx34L9iqToJk09Yc6Kexi', 'user', 'Putri ', NULL, NULL, 'default.png', '2025-12-25 03:52:27', '2025-12-25 03:52:27'),
(28, 'Binbin', '$2y$10$ETkxYaA4YzqCqOTG/U.Np.sTcT3.UcINyM.HY8duHof/Ua/U10BNy', 'user', 'Bintang', NULL, NULL, 'default.png', '2025-12-25 04:09:38', '2025-12-25 04:09:38'),
(30, 'Aloevera', '$2y$10$4XyHTebG3YzuXkftKGrAXetr/hHDiwGi6kzZO0CRckrTzgDkmzlyq', 'user', 'Aloevera', 'aloevera123@gmail.com', NULL, 'default.png', '2026-01-02 06:16:55', '2026-01-07 13:31:31'),
(31, 'arin123', '$2y$10$IZU7EcS4.RstG1316qU8cO3f4sG7Sf6atPDL66T3lo7B5x8LmsF9K', 'user', 'Arin', NULL, NULL, 'default.png', '2026-01-08 03:35:45', '2026-01-08 03:35:45'),
(32, 'haha123', '$2y$10$zhOZPrcknMqtx4OjEIKVT.Q0DSZ8iVWR2y3yZDaKyt92jN5/04Pwa', 'user', 'haha', NULL, NULL, 'default.png', '2026-01-08 03:37:21', '2026-01-08 03:37:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id_karyawan`),
  ADD KEY `fk_karyawan_user` (`user_id`);

--
-- Indexes for table `laporan_bulanan`
--
ALTER TABLE `laporan_bulanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bulan_tahun` (`bulan`,`tahun`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_login` (`user_id`,`login_time`);

--
-- Indexes for table `pendapatan_harian`
--
ALTER TABLE `pendapatan_harian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tanggal` (`tanggal`),
  ADD KEY `idx_bulan_tahun` (`bulan`,`tahun`);

--
-- Indexes for table `penggajian`
--
ALTER TABLE `penggajian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_penggajian_karyawan` (`id_karyawan`);

--
-- Indexes for table `pesan_sistem`
--
ALTER TABLE `pesan_sistem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tujuan` (`tujuan_role`,`tujuan_user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `presensi`
--
ALTER TABLE `presensi`
  ADD PRIMARY KEY (`presensi_id`);

--
-- Indexes for table `produk_unggulan`
--
ALTER TABLE `produk_unggulan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_parameters`
--
ALTER TABLE `system_parameters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `param_key` (`param_key`);

--
-- Indexes for table `target_pendapatan`
--
ALTER TABLE `target_pendapatan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bulan_tahun` (`bulan`,`tahun`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id_karyawan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `laporan_bulanan`
--
ALTER TABLE `laporan_bulanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pendapatan_harian`
--
ALTER TABLE `pendapatan_harian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `penggajian`
--
ALTER TABLE `penggajian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `pesan_sistem`
--
ALTER TABLE `pesan_sistem`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `presensi`
--
ALTER TABLE `presensi`
  MODIFY `presensi_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `produk_unggulan`
--
ALTER TABLE `produk_unggulan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_parameters`
--
ALTER TABLE `system_parameters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `target_pendapatan`
--
ALTER TABLE `target_pendapatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD CONSTRAINT `fk_karyawan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `penggajian`
--
ALTER TABLE `penggajian`
  ADD CONSTRAINT `fk_penggajian_karyawan` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id_karyawan`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
