-- phpMyAdmin SQL Dump
-- version 6.0.0-dev+20260306.2f4a40d208
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 22, 2026 at 03:42 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventaris`
--

-- --------------------------------------------------------

--
-- Table structure for table `barang`
--

CREATE TABLE `barang` (
  `id_barang` int NOT NULL,
  `kode_barang` varchar(20) DEFAULT NULL,
  `nama_barang` varchar(100) NOT NULL,
  `id_ruangan` int NOT NULL,
  `jumlah` int NOT NULL,
  `jumlah_laik` int DEFAULT '0',
  `jumlah_tidak_laik` int DEFAULT '0',
  `deskripsi` text,
  `spesifikasi` text,
  `bisa_dipinjam` tinyint(1) DEFAULT '1',
  `pinjam_murid` tinyint(1) DEFAULT '1',
  `pinjam_guru` tinyint(1) DEFAULT '1',
  `pinjam_tendik` tinyint(1) DEFAULT '1',
  `foto` varchar(255) DEFAULT NULL,
  `sumber_dana` varchar(150) DEFAULT NULL,
  `tanggal_pembelian` date DEFAULT NULL,
  `durasi_murid` int DEFAULT NULL COMMENT 'Batas waktu peminjaman murid (menit). NULL = bebas.',
  `durasi_guru` int DEFAULT NULL COMMENT 'Batas waktu peminjaman guru (menit). NULL = bebas.',
  `durasi_tendik` int DEFAULT NULL COMMENT 'Batas waktu peminjaman tendik (menit). NULL = bebas.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `barang`
--

INSERT INTO `barang` (`id_barang`, `kode_barang`, `nama_barang`, `id_ruangan`, `jumlah`, `jumlah_laik`, `jumlah_tidak_laik`, `deskripsi`, `spesifikasi`, `bisa_dipinjam`, `pinjam_murid`, `pinjam_guru`, `pinjam_tendik`, `foto`, `sumber_dana`, `tanggal_pembelian`, `durasi_murid`, `durasi_guru`, `durasi_tendik`) VALUES
(1, 'MP-001', 'Meja Panjang', 1, 12, 12, 0, NULL, NULL, 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(2, NULL, 'Mic', 1, 15, 10, 5, NULL, 'Acer', 1, 1, 1, 1, NULL, 'BOS', '2026-05-13', NULL, NULL, NULL),
(3, NULL, 'Meja', 10, 6, 6, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(4, NULL, 'Lemari', 1, 3, 3, 0, NULL, 'Besar', 1, 1, 1, 1, NULL, 'BOSP', NULL, NULL, NULL, NULL),
(5, NULL, 'sajadah', 33, 19, 19, 0, NULL, NULL, 1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(6, NULL, 'Meja Staff TU', 11, 7, 7, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(7, NULL, 'Kursi Staff TU', 11, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(8, NULL, 'Kursi Lab.', 11, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(9, NULL, 'Lemari Besi', 11, 3, 3, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(10, NULL, 'Lemari Kayu Kecil', 11, 3, 3, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(11, NULL, 'Lemari Plastik', 11, 1, 1, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(12, NULL, 'Filling Kabinet', 11, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(13, NULL, 'Rak Besi', 11, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(14, NULL, 'Rak Kayu', 11, 2, 2, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(15, NULL, 'Komputer Lenovo', 11, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(16, NULL, 'Komputer Acer', 11, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(17, NULL, 'Komputer  Lenovo (Bel)', 11, 1, 1, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(18, NULL, 'Key Board Lenovo', 11, 1, 1, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(19, NULL, 'Key Board Logitech', 11, 1, 1, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(20, NULL, 'Key Board Acer', 11, 3, 3, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(21, NULL, 'Mouse Acer', 11, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(22, NULL, 'Mouse Logitech', 11, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(23, NULL, 'Mouse Lenovo', 11, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(24, NULL, 'Mouse Asic 9', 11, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(25, NULL, 'Lemari Kaca Besar', 15, 1, 1, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(26, NULL, 'Meja Kepsek', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(27, NULL, 'Kursi Kepsek', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(28, NULL, 'Meja Tamu Jati', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(29, NULL, 'Kursi Tamu Jati', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(30, NULL, 'Kursi Lab.', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(31, NULL, 'Keranjang Minuman', 15, 1, 1, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(32, NULL, 'Tempat Tisu', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(33, NULL, 'Komputer', 15, 1, 1, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(34, NULL, 'Key Board', 15, 1, 1, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(35, NULL, 'Mouse', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(36, NULL, 'Speaker Kecil', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(37, NULL, 'TV', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(38, NULL, 'DVR', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(39, NULL, 'Swicthub', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
(40, NULL, 'UPS', 15, 0, 0, 0, NULL, NULL, 0, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `detail_peminjaman`
--

CREATE TABLE `detail_peminjaman` (
  `id_detail` int NOT NULL,
  `id_peminjaman` int DEFAULT NULL,
  `id_barang` int DEFAULT NULL,
  `jumlah` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id_peminjaman` int NOT NULL,
  `id_pengguna` varchar(25) DEFAULT NULL,
  `tujuan` text,
  `kelas` varchar(50) DEFAULT NULL,
  `tanggal_pengajuan` date NOT NULL,
  `tanggal_pinjam` date DEFAULT NULL,
  `waktu_mulai` time DEFAULT NULL,
  `waktu_selesai` time DEFAULT NULL,
  `catatan` text,
  `tanggal_kembali` date DEFAULT NULL,
  `tgl_kembali_aktual` date DEFAULT NULL,
  `waktu_kembali` time DEFAULT NULL,
  `status` enum('menunggu','dipinjam','menunggu_kembali','dikembalikan') DEFAULT 'menunggu',
  `kondisi_kembali` enum('baik','rusak_ringan','rusak_berat') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengguna`
--

CREATE TABLE `pengguna` (
  `id_pengguna` varchar(25) NOT NULL,
  `nama` varchar(75) NOT NULL,
  `email` varchar(75) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','murid','guru','tendik') NOT NULL,
  `status` enum('aktif','pending','nonaktif') NOT NULL DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pengguna`
--

INSERT INTO `pengguna` (`id_pengguna`, `nama`, `email`, `username`, `password`, `role`, `status`) VALUES
('1', 'admin', '', 'admin', '$2y$10$yVmPd7YhIcEKlF0WdI9dM.BFEXMj7Xv8yA4la4pZZkf2c6xou/nuK', 'admin', 'aktif');

-- --------------------------------------------------------

--
-- Table structure for table `ruangan`
--

CREATE TABLE `ruangan` (
  `id_ruangan` int NOT NULL,
  `nama_ruangan` varchar(75) NOT NULL,
  `keterangan` text,
  `foto` varchar(255) DEFAULT NULL,
  `panjang` decimal(8,2) DEFAULT NULL,
  `lebar` decimal(8,2) DEFAULT NULL,
  `persentase_kerusakan` decimal(5,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ruangan`
--

INSERT INTO `ruangan` (`id_ruangan`, `nama_ruangan`, `keterangan`, `foto`, `panjang`, `lebar`, `persentase_kerusakan`) VALUES
(1, 'Lab Bahasa', NULL, 'room_1_1773036215.jpeg', 12.00, 9.00, 0.00),
(2, 'Perpustakaan', 'Gedung A Lantai 3', NULL, 16.00, 9.00, 0.00),
(4, 'Kantin', NULL, NULL, NULL, NULL, 0.00),
(7, 'Ruang Rapat', NULL, NULL, 9.00, 4.00, 0.00),
(8, 'Ruang UKS', NULL, NULL, 9.00, 4.00, 0.00),
(9, 'Lab Biologi', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(10, 'Ruang Waka', 'Gedung A Lantai 1', NULL, 9.00, 4.00, 0.00),
(11, 'RUANG TU', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(12, 'RUANG GUDANG TU', 'Gedung A', NULL, 4.00, 3.00, 0.00),
(13, 'RUANG PIKET GURU', 'Gedung A', NULL, 3.00, 2.50, 0.00),
(14, 'RUANG BK', 'Gedung A', NULL, 6.00, 4.00, 0.00),
(15, 'RUANG KEPALA SEKOLAH', 'Gedung A', NULL, 9.00, 4.00, 0.00),
(16, 'WC RUANG KEPALA SEKOLAH', 'Gedung A', NULL, 2.00, 1.50, 0.00),
(17, 'RUANG GURU A', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(18, 'RUANG GURU B', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(19, 'RUANG LAB. KOMPUTER A', 'Gedung A', NULL, 12.00, 9.00, 0.00),
(20, 'RUANG LAB. BAHASA', 'Gedung A', NULL, 12.00, 9.00, 0.00),
(21, 'RUANG GUDANG LAB. KOM. B', 'Gedung A', NULL, 4.50, 4.00, 0.00),
(22, 'RUANG LAB. KIMIA', 'Gedung A', NULL, 12.00, 9.00, 0.00),
(23, 'RUANG GUDANG LAB. KIMIA', 'Gedung A', NULL, 4.50, 4.00, 0.00),
(24, 'RUANG LAB. BIOLOGI', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(25, 'RUANG LAB. FISIKA', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(26, 'RUANG MUSIK', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(27, 'RUANG PERPUSTAKAAN', 'Gedung A', NULL, 16.00, 9.00, 0.00),
(28, 'RUANG KELAS AGAMA', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(29, 'RUANG KOMITE', 'Gedung A', NULL, 9.00, 4.00, 0.00),
(30, 'RUANG OSIS', 'Gedung A', NULL, 9.00, 4.00, 0.00),
(31, 'RUANG PODCAST', 'Gedung A', NULL, 3.00, 2.00, 0.00),
(32, 'RUANG JAGA SATPAM', 'Gedung A', NULL, 3.00, 2.50, 0.00),
(33, 'MUSHOLLA', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(34, 'KANTIN 1', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(35, 'KANTIN 2', 'Gedung A Lantai 2', NULL, 9.00, 8.00, 0.00),
(36, 'KANTIN 3', 'Gedung A', NULL, 9.00, 8.00, 0.00),
(37, 'PANTRY 1', 'Gedung A', NULL, 3.00, 2.00, 0.00),
(38, 'PANTRY 2', 'Gedung A', NULL, 3.00, 2.00, 0.00),
(39, 'PANTRY 3', 'Gedung A', NULL, 3.00, 2.00, 0.00),
(40, 'GUDANG', 'Gedung A', NULL, 3.00, 2.00, 0.00),
(41, 'PARKIR DEPAN', 'Gedung A', NULL, 48.00, 15.00, 0.00),
(42, 'PARKIR SAMPING', 'Gedung A', NULL, 45.00, 15.00, 0.00),
(43, 'PARKIR BELAKANG', 'Gedung A', NULL, 15.00, 10.00, 0.00),
(44, 'LAPANGAN / HALAMAN', 'Gedung A', NULL, 20.00, 10.00, 0.00),
(45, 'X E', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(46, 'X F', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(47, 'X G', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(48, 'X H', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(49, 'X I', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(50, 'X J', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(51, 'XI E', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(52, 'XI F', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(53, 'XI G', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(54, 'XI H', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(55, 'XI I', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(56, 'XI J', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(57, 'XII E', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(58, 'XII F', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(59, 'XII G', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(60, 'XII H', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(61, 'XII I', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(62, 'XII J', 'Gedung C', NULL, 9.00, 8.00, 0.00),
(63, 'RUANG KOPERASI', 'Gedung C', NULL, 9.00, 4.00, 0.00),
(64, 'RUANG GURU LANTAI 2', 'Gedung C Lantai 2', NULL, 9.00, 4.00, 0.00),
(65, 'RUANG GURU LANTAI 3', 'Gedung C Lantai 3', NULL, 9.00, 4.00, 0.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`id_barang`),
  ADD UNIQUE KEY `kode_barang` (`kode_barang`),
  ADD KEY `id_ruangan` (`id_ruangan`);

--
-- Indexes for table `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_peminjaman` (`id_peminjaman`),
  ADD KEY `id_barang` (`id_barang`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id_peminjaman`),
  ADD KEY `peminjaman_ibfk_1` (`id_pengguna`);

--
-- Indexes for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id_pengguna`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `ruangan`
--
ALTER TABLE `ruangan`
  ADD PRIMARY KEY (`id_ruangan`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barang`
--
ALTER TABLE `barang`
  MODIFY `id_barang` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  MODIFY `id_detail` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id_peminjaman` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `ruangan`
--
ALTER TABLE `ruangan`
  MODIFY `id_ruangan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barang`
--
ALTER TABLE `barang`
  ADD CONSTRAINT `barang_ibfk_1` FOREIGN KEY (`id_ruangan`) REFERENCES `ruangan` (`id_ruangan`);

--
-- Constraints for table `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  ADD CONSTRAINT `detail_peminjaman_ibfk_1` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id_peminjaman`),
  ADD CONSTRAINT `detail_peminjaman_ibfk_2` FOREIGN KEY (`id_barang`) REFERENCES `barang` (`id_barang`);

--
-- Constraints for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
