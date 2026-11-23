-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 23, 2026 at 04:18 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_campuspass`
--
CREATE DATABASE IF NOT EXISTS `db_campuspass` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_campuspass`;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `judul_event` varchar(150) NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `deskripsi` text NOT NULL,
  `tanggal_kegiatan` datetime NOT NULL,
  `lokasi` varchar(150) NOT NULL,
  `kuota_maksimal` int(10) UNSIGNED NOT NULL,
  `biaya_pendaftaran` decimal(12,2) NOT NULL DEFAULT 0.00,
  `narahubung` varchar(100) NOT NULL,
  `poster_file` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_events_user` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `judul_event`, `kategori`, `deskripsi`, `tanggal_kegiatan`, `lokasi`, `kuota_maksimal`, `biaya_pendaftaran`, `narahubung`, `poster_file`, `created_by`, `created_at`) VALUES
(1, 'Seminar Nasional AI dan Machine Learning 2026', 'Seminar', 'Seminar nasional membahas perkembangan Artificial Intelligence dan Machine Learning di dunia pendidikan dan industri.', '2026-11-25 09:00:00', 'Aula Utama Kampus', 50, 25000.00, 'Galang - 081234567890', '2ffdada774d7cd9e4f384df4e7198bd4.jpg', 1, '2026-11-20 08:47:39'),
(2, 'Workshop UI/UX Design untuk Pemula', 'Workshop', 'Workshop dasar perancangan UI/UX menggunakan prinsip desain modern dan berorientasi pada pengguna.', '2026-11-28 08:30:00', 'Lab Komputer FIK', 40, 15000.00, 'Kenzi - 081234567891', '85843af30fe446708749bc9b47bab424.jpg', 2, '2026-11-20 08:47:39'),
(3, 'Lomba Hackathon Nasional 2026', 'Lomba', 'Kompetisi pengembangan solusi digital untuk menyelesaikan masalah di lingkungan kampus.', '2026-12-05 08:00:00', 'Gedung Rektorat', 50, 0.00, 'Delia - 081234567892', '55a2ca437d7cb2feec5d5c2656fb976e.jpg', 3, '2026-11-20 08:47:39'),
(4, 'Piala Rektor 2026', 'Lomba', 'lomba caturan caturin', '2026-12-31 20:21:00', 'Selasar FIK', 67, 5000.00, 'Kenzi Syahkhalid Putra Tito', 'ec12cf194ffbc5cc4569347b85beab98.png', 1, '2026-11-23 13:23:08');

-- --------------------------------------------------------

--
-- Table structure for table `pendaftaran`
--

DROP TABLE IF EXISTS `pendaftaran`;
CREATE TABLE IF NOT EXISTS `pendaftaran` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(10) UNSIGNED NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_whatsapp` varchar(20) NOT NULL,
  `fakultas` varchar(100) NOT NULL,
  `jurusan` varchar(100) NOT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `ktm_file` varchar(255) DEFAULT NULL,
  `kode_tiket` varchar(50) NOT NULL,
  `status_bayar` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `status_kehadiran` enum('Belum Hadir','Hadir') NOT NULL DEFAULT 'Belum Hadir',
  `waktu_pendaftaran` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_tiket` (`kode_tiket`),
  UNIQUE KEY `unique_kode_tiket` (`kode_tiket`),
  KEY `fk_pendaftaran_event` (`event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pendaftaran`
--

INSERT INTO `pendaftaran` (`id`, `event_id`, `nama_lengkap`, `nim`, `email`, `no_whatsapp`, `fakultas`, `jurusan`, `bukti_pembayaran`, `ktm_file`, `kode_tiket`, `status_bayar`, `status_kehadiran`, `waktu_pendaftaran`) VALUES
(1, 1, 'Andi Pratama', '2510501001', 'andi@student.test', '081234560001', 'FIK', 'Sistem Informasi', 'bukti_andi.jpg', 'ktm_andi.jpg', 'CP26-AI001', 'Approved', 'Hadir', '2026-11-20 08:47:39'),
(2, 1, 'Budi Santoso', '2510501002', 'budi@student.test', '081234560002', 'FIK', 'Informatika', 'bukti_budi.jpg', 'ktm_budi.jpg', 'CP26-AI002', 'Approved', 'Belum Hadir', '2026-11-20 08:47:39'),
(3, 1, 'Citra Lestari', '2510501003', 'citra@student.test', '081234560003', 'FEB', 'Manajemen', 'bukti_citra.jpg', 'ktm_citra.jpg', 'CP26-AI003', 'Pending', 'Belum Hadir', '2026-11-20 08:47:39'),
(4, 2, 'Dimas Ramadhan', '2510501004', 'dimas@student.test', '081234560004', 'FIK', 'Sistem Informasi', 'bukti_dimas.jpg', 'ktm_dimas.jpg', 'CP26-UX001', 'Approved', 'Hadir', '2026-11-20 08:47:39'),
(5, 2, 'Eka Wulandari', '2510501005', 'eka@student.test', '081234560005', 'FISIP', 'Ilmu Komunikasi', 'bukti_eka.jpg', 'ktm_eka.jpg', 'CP26-UX002', 'Rejected', 'Belum Hadir', '2026-11-20 08:47:39'),
(6, 3, 'Fajar Nugroho', '2510501006', 'fajar@student.test', '081234560006', 'FT', 'Teknik Industri', NULL, 'ktm_fajar.jpg', 'CP26-HK001', 'Approved', 'Belum Hadir', '2026-11-20 08:47:39'),
(7, 3, 'Gita Permata', '2510501007', 'gita@student.test', '081234560007', 'FIK', 'Informatika', NULL, 'ktm_gita.jpg', 'CP26-HK002', 'Pending', 'Belum Hadir', '2026-11-20 08:47:39'),
(8, 3, 'Hendra Saputra', '2510501008', 'hendra@student.test', '081234560008', 'FH', 'Ilmu Hukum', NULL, 'ktm_hendra.jpg', 'CP26-HK003', 'Approved', 'Hadir', '2026-11-20 08:47:39'),
(9, 4, 'Kenzi Alputra Khalidsyah', '2510501067', 'kenzi@mainikan.discuss.id', '089893684', 'Ilmu Hitam', 'Santet Menengah Pertama', '99adad200139e1e33c32998b7090b0d8.jpg', '716673c6bb2dd0a82284d30be87a21d0.jpg', 'CP-EA774AECBB', 'Rejected', 'Belum Hadir', '2026-11-23 13:28:47'),
(10, 4, 'Galang Putra Pratama', '2510501047', 'galangputrapratamairawan@gmail.com', '089630205851', 'Fakultas Ilmu Komputer', 'D3 Sistem Informasi', 'e4ea90b265234d44002ff43632baf75a.jpg', 'e06a6b30f44e7a5ad0a7b2f4cad65a9d.jpg', 'CP-DD6ADBD882', 'Approved', 'Belum Hadir', '2026-11-23 13:41:54'),
(11, 2, 'Galang Putra Pratama', '2510501047', 'galangputrapratamairawan@gmail.com', '089630205851', 'Fakultas Ilmu Komputer', 'D3 Sistem Informasi', '4ec88103a8349764c39becd18dd0218f.jpg', '1def2c2e1bb9b67d0f7eeec10bf364fe.jpg', 'CP-72F3630059', 'Pending', 'Belum Hadir', '2026-11-23 13:43:07'),
(12, 4, 'Shelenia', '2510501099', 'selen@gmail.com', '089630205851', 'Fakultas Ilmu Komputer', 'D3 Sistem Informasi', 'f1a1002781d106e7a94c84c389b13d6a.jpg', 'ae36ebe38e14b25fe28d58bb28f966a7.jpg', 'CP-7DABF1C06B', 'Pending', 'Belum Hadir', '2026-11-23 13:47:47'),
(13, 4, 'Kenzo Alpukat', '2510501090', 'mainikandiscus@net.co.id.com.tv', '089630205851', 'Ilmu Hitam', 'Santet Menengah Pertama', '3301579fe9f8f6d36cccd20272df12d6.jpg', '6272028a281cacfbdba86f955ae38b16.jpg', 'CP-9A4D3B5B3A', 'Approved', 'Hadir', '2026-11-23 14:31:24');

-- --------------------------------------------------------

--
-- Table structure for table `presensi`
--

DROP TABLE IF EXISTS `presensi`;
CREATE TABLE IF NOT EXISTS `presensi` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pendaftaran_id` int(10) UNSIGNED NOT NULL,
  `waktu_checkin` timestamp NOT NULL DEFAULT current_timestamp(),
  `petugas_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pendaftaran_id` (`pendaftaran_id`),
  UNIQUE KEY `unique_pendaftaran_checkin` (`pendaftaran_id`),
  KEY `fk_presensi_petugas` (`petugas_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `presensi`
--

INSERT INTO `presensi` (`id`, `pendaftaran_id`, `waktu_checkin`, `petugas_id`) VALUES
(1, 1, '2026-11-25 01:42:00', 2),
(2, 4, '2026-11-28 01:15:00', 2),
(3, 8, '2026-12-05 01:30:00', 3),
(4, 13, '2026-11-23 14:31:57', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','panitia') NOT NULL DEFAULT 'panitia',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `unique_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama_lengkap`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Galang Putra Pratama', 'galang@campuspass.test', '$2y$10$ZxBY59rXDRyKELqnYADdBOsHRfsZAPD7eaiuPG9l5Tb8gk3oJXOAy', 'admin', '2026-11-20 05:35:00'),
(2, 'Kenzi Alkhalid Syahputra', 'kenzi@campuspass.test', 'kenzi123', 'panitia', '2026-11-20 08:47:39'),
(3, 'Delia Ayu Nandhita', 'delia@campuspass.test', 'dummy_password_delia', 'panitia', '2026-11-20 08:47:39');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `pendaftaran`
--
ALTER TABLE `pendaftaran`
  ADD CONSTRAINT `fk_pendaftaran_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `presensi`
--
ALTER TABLE `presensi`
  ADD CONSTRAINT `fk_presensi_pendaftaran` FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_presensi_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
