-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 28 Okt 2025 pada 22.27
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
-- Database: `emocare`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `journals`
--

CREATE TABLE `journals` (
  `journal_id` int(10) UNSIGNED NOT NULL,
  `pengguna_id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `judul` varchar(150) NOT NULL,
  `isi` mediumtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `moodtracker`
--

CREATE TABLE `moodtracker` (
  `mood_id` int(10) UNSIGNED NOT NULL,
  `pengguna_id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `mood_level` tinyint(3) UNSIGNED NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `moodtracker`
--

INSERT INTO `moodtracker` (`mood_id`, `pengguna_id`, `tanggal`, `mood_level`, `catatan`, `created_at`) VALUES
(52, 10, '2025-10-27', 1, 'karena abis mamam', '2025-10-27 10:42:27'),
(53, 10, '2025-10-27', 4, 'laptop mati', '2025-10-27 10:42:37'),
(54, 10, '2025-10-27', 5, 'batre 61%', '2025-10-27 10:42:49'),
(55, 10, '2025-10-27', 1, 'yipii', '2025-10-27 10:56:35');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengguna`
--

CREATE TABLE `pengguna` (
  `pengguna_id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `role` enum('user','admin') NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pengguna`
--

INSERT INTO `pengguna` (`pengguna_id`, `nama`, `email`, `password_hash`, `created_at`, `updated_at`, `role`) VALUES
(9, 'shailalove', 'shailalove@gmail.com', '$2y$10$OhgsvbvGVzZQf8TX.vdw6.gEzXuq1D2bGzioJ0T06GXZn2ZLKsN8O', '2025-10-15 10:19:58', '2025-10-20 08:58:53', 'user'),
(10, 'Admin EmoCare', 'admin@emocare.local', '$2y$10$EgazTxRYI9RCUKhWUmts9Og/8QNqFxuTUxlIuXaiQiQA/OczdXFca', '2025-10-18 16:49:51', '2025-10-20 07:56:19', 'admin'),
(11, 'shashasha', 'shalala@gmail.com', '$2y$10$5/cX/MjO7bCpxGwSjqbH2.ZrvoPwXHQRCPJPq10gr2izUid7Ft1ny', '2025-10-21 07:35:34', NULL, 'user'),
(12, 'zarahjaran', 'zarahjaran@gmail.com', '$2y$10$JWpyZgwzAtGN7Dd2iDxB6.xZxmz7g.5PfWLBjkaaFRN/cd690x0Sy', '2025-10-21 07:49:09', NULL, 'user');

-- --------------------------------------------------------

--
-- Struktur dari tabel `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `category` varchar(64) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `label` varchar(64) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `quiz_attempts`
--

INSERT INTO `quiz_attempts` (`id`, `pengguna_id`, `category`, `score`, `label`, `notes`, `created_at`) VALUES
(1, 10, 'self_esteem', 100.00, 'Mental Sehat', 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.', '2025-10-22 19:54:12'),
(2, 10, 'M NBV', 33.33, 'Depresi Berat', 'Pertimbangkan konselor/psikolog. Bila ada pikiran menyakiti diri, cari bantuan darurat.', '2025-10-22 20:20:25'),
(3, 10, 'M NBV', 66.67, 'Stres', 'Stres bermakna. Latih relaksasi/napas dalam, kurangi pemicu, minta dukungan.', '2025-10-23 00:19:40'),
(4, 10, 'M NBV', 33.33, 'Depresi Berat', 'Pertimbangkan konselor/psikolog. Bila ada pikiran menyakiti diri, cari bantuan darurat.', '2025-10-23 00:19:47'),
(5, 10, 'social_anxiety', 100.00, 'Mental Sehat', 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.', '2025-10-27 03:40:42'),
(6, 10, 'self_tes', 100.00, 'Mental Sehat', 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.', '2025-10-27 03:53:04'),
(7, 10, 'self_tes', 100.00, 'Mental Sehat', 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.', '2025-10-27 04:03:08'),
(8, 10, 'self_tes', 0.00, 'Depresi Berat', 'Pertimbangkan konselor/psikolog. Bila ada pikiran menyakiti diri, cari bantuan darurat.', '2025-10-27 04:03:59'),
(9, 10, 'self_tes', 100.00, 'Mental Sehat', 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.', '2025-10-27 04:04:11'),
(10, 10, 'self_tes', 100.00, 'Mental Sehat', 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.', '2025-10-28 16:03:29'),
(11, 10, 'self_tes', 100.00, 'Mental Sehat', 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.', '2025-10-28 16:12:57'),
(12, 10, 'self_tes', 100.00, 'Mental Sehat', 'Kondisi emosional stabil & adaptif. Pertahankan kebiasaan baik.', '2025-10-28 16:13:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `quiz_list`
--

CREATE TABLE `quiz_list` (
  `id` int(11) NOT NULL,
  `slug` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `icon` varchar(16) DEFAULT '?',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `quiz_list`
--

INSERT INTO `quiz_list` (`id`, `slug`, `name`, `description`, `icon`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'self_esteem', 'Self-Esteem', 'Penilaian kepercayaan diri & harga diri.', '✨', 1, 10, '2025-10-22 14:58:34'),
(9, 'self_tes', 'test', 'apayaa', '💗', 1, 1, '2025-10-27 04:02:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `quiz_options`
--

CREATE TABLE `quiz_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `option_order` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `quiz_options`
--

INSERT INTO `quiz_options` (`id`, `question_id`, `option_text`, `is_correct`, `option_order`) VALUES
(41, 6, 'Tidak Pernah', 0, 1),
(42, 6, 'Jarang', 1, 2),
(43, 6, 'Sering', 0, 3),
(44, 6, 'Sangat Sering', 0, 4),
(45, 7, 'Tidak Pernah', 1, 1),
(46, 7, 'Jarang', 0, 2),
(47, 7, 'Sering', 0, 3),
(48, 7, 'Sering Banget', 0, 4),
(57, 10, 'Tidak Pernah', 0, 1),
(58, 10, 'Jarang', 0, 2),
(59, 10, 'Sering', 0, 3),
(60, 10, 'Sangat Sering', 0, 4),
(61, 11, 'Tidak Pernah', 0, 1),
(62, 11, 'Jarang', 0, 2),
(63, 11, 'Sering', 0, 3),
(64, 11, 'Sangat Sering', 0, 4),
(65, 12, 'Tidak Pernah', 0, 1),
(66, 12, 'Jarang', 0, 2),
(67, 12, 'Sering', 0, 3),
(68, 12, 'Sangat Sering', 0, 4),
(69, 13, 'Tidak Pernah', 0, 1),
(70, 13, 'Jarang', 0, 2),
(71, 13, 'Sering', 0, 3),
(72, 13, 'Sangat Sering', 0, 4),
(129, 26, 'iya banget', 0, 1),
(130, 26, '-', 0, 2),
(131, 26, '-', 0, 3),
(132, 26, 'nga', 0, 4);

-- --------------------------------------------------------

--
-- Struktur dari tabel `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `question_text`, `image_path`, `is_active`, `created_at`, `category`) VALUES
(6, 'Saya merasa diri saya berharga, terlepas dari nilai raport atau prestasi.', NULL, 1, '2025-10-20 04:31:59', 'self_esteem'),
(7, 'Saya bisa menyebutkan minimal tiga hal yang saya sukai dari diri saya.', NULL, 1, '2025-10-20 05:02:27', 'self_esteem'),
(10, 'Saya cukup puas dengan penampilan saya saat ini.', NULL, 1, '2025-10-20 05:34:38', 'self_esteem'),
(11, 'Saya berani menyampaikan pendapat meskipun berbeda dengan teman.', NULL, 1, '2025-10-20 05:36:00', 'self_esteem'),
(12, 'Saya dapat menerima kegagalan sebagai bagian dari proses belajar.', NULL, 1, '2025-10-20 05:36:19', 'self_esteem'),
(13, 'Saya percaya bahwa saya mampu mempelajari hal baru jika berusaha.', NULL, 1, '2025-10-20 05:37:30', 'self_esteem'),
(26, 'Apalah kau merasa seperti gambar?', 'uploads/quiz/q_68feeef49bb2a8.34402560.jpg', 1, '2025-10-27 04:03:00', 'self_tes');

-- --------------------------------------------------------

--
-- Struktur dari tabel `quiz_result`
--

CREATE TABLE `quiz_result` (
  `id` int(11) NOT NULL,
  `pengguna_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `score` int(11) DEFAULT NULL,
  `total` int(11) DEFAULT NULL,
  `finished_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `quiz_result`
--

INSERT INTO `quiz_result` (`id`, `pengguna_id`, `category`, `score`, `total`, `finished_at`) VALUES
(1, 10, 'self_tes', NULL, NULL, '2025-10-28 23:24:28'),
(2, 10, 'self_tes', 0, 0, '2025-10-28 23:31:06'),
(3, 10, 'self_tes', 0, 0, '2025-10-28 23:38:14'),
(4, 10, 'self_esteem', 0, 0, '2025-10-28 23:38:38'),
(5, 10, 'self_esteem', 0, 0, '2025-10-29 00:15:15');

-- --------------------------------------------------------

--
-- Struktur dari tabel `reminders`
--

CREATE TABLE `reminders` (
  `reminder_id` int(10) UNSIGNED NOT NULL,
  `pengguna_id` int(10) UNSIGNED NOT NULL,
  `judul` varchar(150) NOT NULL,
  `reminder_time` time NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_user_stats`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_user_stats` (
`pengguna_id` int(10) unsigned
,`total_journals` bigint(21)
,`total_reminders_active` bigint(21)
,`total_moods` bigint(21)
);

-- --------------------------------------------------------

--
-- Struktur untuk view `v_user_stats`
--
DROP TABLE IF EXISTS `v_user_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_user_stats`  AS SELECT `p`.`pengguna_id` AS `pengguna_id`, (select count(0) from `journals` `j` where `j`.`pengguna_id` = `p`.`pengguna_id`) AS `total_journals`, (select count(0) from `reminders` `r` where `r`.`pengguna_id` = `p`.`pengguna_id` and `r`.`aktif` = 1) AS `total_reminders_active`, (select count(0) from `moodtracker` `m` where `m`.`pengguna_id` = `p`.`pengguna_id`) AS `total_moods` FROM `pengguna` AS `p` ;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `journals`
--
ALTER TABLE `journals`
  ADD PRIMARY KEY (`journal_id`),
  ADD KEY `ix_journal_user_date` (`pengguna_id`,`tanggal`,`journal_id`);

--
-- Indeks untuk tabel `moodtracker`
--
ALTER TABLE `moodtracker`
  ADD PRIMARY KEY (`mood_id`),
  ADD KEY `ix_mood_user_date` (`pengguna_id`,`tanggal`,`mood_id`);

--
-- Indeks untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`pengguna_id`),
  ADD UNIQUE KEY `ux_pengguna_email` (`email`);

--
-- Indeks untuk tabel `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`),
  ADD KEY `category` (`category`);

--
-- Indeks untuk tabel `quiz_list`
--
ALTER TABLE `quiz_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indeks untuk tabel `quiz_options`
--
ALTER TABLE `quiz_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indeks untuk tabel `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_qq_category` (`category`);

--
-- Indeks untuk tabel `quiz_result`
--
ALTER TABLE `quiz_result`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`),
  ADD KEY `category` (`category`),
  ADD KEY `finished_at` (`finished_at`);

--
-- Indeks untuk tabel `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `ix_rem_user_time` (`pengguna_id`,`reminder_time`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `journals`
--
ALTER TABLE `journals`
  MODIFY `journal_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `moodtracker`
--
ALTER TABLE `moodtracker`
  MODIFY `mood_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `pengguna_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `quiz_list`
--
ALTER TABLE `quiz_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `quiz_options`
--
ALTER TABLE `quiz_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT untuk tabel `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT untuk tabel `quiz_result`
--
ALTER TABLE `quiz_result`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `reminders`
--
ALTER TABLE `reminders`
  MODIFY `reminder_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `journals`
--
ALTER TABLE `journals`
  ADD CONSTRAINT `fk_journal_user` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`pengguna_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `moodtracker`
--
ALTER TABLE `moodtracker`
  ADD CONSTRAINT `fk_mood_user` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`pengguna_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `quiz_options`
--
ALTER TABLE `quiz_options`
  ADD CONSTRAINT `quiz_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `fk_rem_user` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`pengguna_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
