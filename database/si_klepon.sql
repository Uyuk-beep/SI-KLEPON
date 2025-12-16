-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 16 Des 2025 pada 06.11
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
-- Database: `si_klepon`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `nip` varchar(20) DEFAULT NULL,
  `no_telepon` varchar(15) DEFAULT NULL,
  `jabatan` varchar(50) DEFAULT NULL,
  `role` enum('super_admin','admin') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`admin_id`, `username`, `password`, `nama`, `nip`, `no_telepon`, `jabatan`, `role`) VALUES
(1, 'superadmin', '$2y$10$ObvHdot1MzO4yjP1tGibqOKmVQrKg9VQLZbnE/MhVn4J209390uWa', 'Super Admin Utama', '111111111111111111', '081100001111', 'Kepala', 'super_admin'),
(2, 'adminkasi', '$2y$10$ooX8QYxqOh6es/utX8SiJ.ZlZSmxhjUgaDC/KTjHjaldyKRM4LHRO', 'Admin Kasi', '222222222222222222', '082200002222', 'Kasi Pemerintahan', 'admin');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_surat`
--

CREATE TABLE `jenis_surat` (
  `id_jenis_surat` int(11) NOT NULL,
  `nama_surat` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jenis_surat`
--

INSERT INTO `jenis_surat` (`id_jenis_surat`, `nama_surat`, `is_active`) VALUES
(1, 'Surat Keterangan Usaha (SKU)', 1),
(2, 'Surat Keterangan Tidak Mampu (SKTM)', 1),
(3, 'Surat Keterangan Kematian', 1),
(4, 'Surat Keterangan Kelahiran', 1),
(5, 'Surat Keterangan Belum Menikah', 1),
(6, 'Surat Keterangan untuk Menikah', 1),
(7, 'Pengajuan PBB Baru', 1),
(8, 'Surat Keterangan Ahli Waris', 1),
(9, 'Surat Keterangan Berkelakuan Baik', 1),
(10, 'Surat Keterangan Domisili', 1),
(11, 'Surat Keterangan Pindah', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `kegiatan`
--

CREATE TABLE `kegiatan` (
  `kegiatan_id` int(11) NOT NULL,
  `judul` varchar(255) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengajuan_surat`
--

CREATE TABLE `pengajuan_surat` (
  `pengajuan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_jenis_surat` int(11) DEFAULT NULL,
  `nama_pengaju` varchar(255) NOT NULL,
  `email_pengaju` varchar(100) NOT NULL,
  `no_telepon` varchar(15) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `tgl_pengajuan` date NOT NULL,
  `status` enum('Menunggu','Diajukan User','Verifikasi Kasi','Ditolak','Selesai') NOT NULL,
  `alasan_penolakan` text DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `foto_ktp` varchar(255) DEFAULT NULL,
  `foto_kk` varchar(255) DEFAULT NULL,
  `foto_formulir` varchar(255) DEFAULT NULL,
  `foto_lainnya` varchar(255) DEFAULT NULL,
  `file_surat` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengumuman`
--

CREATE TABLE `pengumuman` (
  `pengumuman_id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `statistik`
--

CREATE TABLE `statistik` (
  `id` int(11) NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `tahun` int(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `struktur`
--

CREATE TABLE `struktur` (
  `struktur_id` int(11) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `posisi` varchar(100) DEFAULT NULL,
  `urutan` int(11) NOT NULL DEFAULT 10,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user`
--

INSERT INTO `user` (`user_id`, `nama`, `nik`, `username`, `no_telepon`, `email`, `password`) VALUES
(1, 'Budi Santoso', '3333333333333333', 'budisantoso', '081234567890', 'budi.santoso@example.com', '$2y$10$Ec9G0/atP2CRyWrUMc/TCeXXoHpvBjXnGicASsh.0Nq/uZ4GrIRrW'),
(2, 'Siti Aminah', '4444444444444444', 'sitiaminah', '089876543210', 'siti.aminah@example.com', '$2y$10$ZOnggL3vCjzft9aD3sWdY.Irap/njOJOtFyVMTjhi3fXAx.uQDdEm');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `jenis_surat`
--
ALTER TABLE `jenis_surat`
  ADD PRIMARY KEY (`id_jenis_surat`),
  ADD UNIQUE KEY `nama_surat` (`nama_surat`);

--
-- Indeks untuk tabel `kegiatan`
--
ALTER TABLE `kegiatan`
  ADD PRIMARY KEY (`kegiatan_id`);

--
-- Indeks untuk tabel `pengajuan_surat`
--
ALTER TABLE `pengajuan_surat`
  ADD PRIMARY KEY (`pengajuan_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_jenis_surat` (`id_jenis_surat`);

--
-- Indeks untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD PRIMARY KEY (`pengumuman_id`);

--
-- Indeks untuk tabel `statistik`
--
ALTER TABLE `statistik`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `struktur`
--
ALTER TABLE `struktur`
  ADD PRIMARY KEY (`struktur_id`);

--
-- Indeks untuk tabel `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `jenis_surat`
--
ALTER TABLE `jenis_surat`
  MODIFY `id_jenis_surat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `kegiatan`
--
ALTER TABLE `kegiatan`
  MODIFY `kegiatan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengajuan_surat`
--
ALTER TABLE `pengajuan_surat`
  MODIFY `pengajuan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  MODIFY `pengumuman_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `statistik`
--
ALTER TABLE `statistik`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `struktur`
--
ALTER TABLE `struktur`
  MODIFY `struktur_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `pengajuan_surat`
--
ALTER TABLE `pengajuan_surat`
  ADD CONSTRAINT `fk_jenis_surat` FOREIGN KEY (`id_jenis_surat`) REFERENCES `jenis_surat` (`id_jenis_surat`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
