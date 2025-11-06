-- =========================
--  EmoCare Database Schema
-- =========================

-- 0) Buat Database
CREATE DATABASE IF NOT EXISTS emocare
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE emocare;

-- 1) Tabel pengguna (users)
DROP TABLE IF EXISTS pengguna;
CREATE TABLE pengguna (
  pengguna_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama         VARCHAR(100) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (pengguna_id),
  UNIQUE KEY ux_pengguna_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Tabel moodtracker
--    Dipakai oleh backend/moods.php:
--    - GET   : ambil items by pengguna_id (ORDER BY tanggal DESC, mood_id DESC)
--    - POST  : INSERT (tanggal = CURDATE(), mood_level, catatan)
DROP TABLE IF EXISTS moodtracker;
CREATE TABLE moodtracker (
  mood_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengguna_id  INT UNSIGNED NOT NULL,
  tanggal      DATE NOT NULL,          -- sesuai CURDATE() di backend
  mood_level   TINYINT UNSIGNED NOT NULL,  -- 1..5
  catatan      TEXT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (mood_id),
  KEY ix_mood_user_date (pengguna_id, tanggal, mood_id),
  CONSTRAINT fk_mood_user
    FOREIGN KEY (pengguna_id) REFERENCES pengguna(pengguna_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT ck_mood_level CHECK (mood_level BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Tabel journals (Jurnal Digital)
DROP TABLE IF EXISTS journals;
CREATE TABLE journals (
  journal_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengguna_id  INT UNSIGNED NOT NULL,
  tanggal      DATE NOT NULL,
  judul        VARCHAR(150) NOT NULL,
  isi          MEDIUMTEXT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (journal_id),
  KEY ix_journal_user_date (pengguna_id, tanggal, journal_id),
  CONSTRAINT fk_journal_user
    FOREIGN KEY (pengguna_id) REFERENCES pengguna(pengguna_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Tabel reminders (Self-Care Reminders)
DROP TABLE IF EXISTS reminders;
CREATE TABLE reminders (
  reminder_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengguna_id  INT UNSIGNED NOT NULL,
  judul        VARCHAR(150) NOT NULL,  -- dari preset atau custom
  reminder_time TIME NOT NULL,         -- "HH:MM:SS"
  aktif        TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (reminder_id),
  KEY ix_rem_user_time (pengguna_id, reminder_time),
  CONSTRAINT fk_rem_user
    FOREIGN KEY (pengguna_id) REFERENCES pengguna(pengguna_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) (Opsional) Tabel quiz_results
--    Simpan hasil kuis jika nanti diperlukan.
DROP TABLE IF EXISTS quiz_results;
CREATE TABLE quiz_results (
  quiz_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengguna_id  INT UNSIGNED NOT NULL,
  quiz_key     VARCHAR(80) NOT NULL,  -- identifier kuis (mis. 'stress_test')
  score        INT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (quiz_id),
  KEY ix_quiz_user (pengguna_id, quiz_key, created_at),
  CONSTRAINT fk_quiz_user
    FOREIGN KEY (pengguna_id) REFERENCES pengguna(pengguna_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) View statistik ringkas (opsional, dipakai stats.php kalau mau)
DROP VIEW IF EXISTS v_user_stats;
CREATE VIEW v_user_stats AS
SELECT 
  p.pengguna_id,
  (SELECT COUNT(*) FROM journals j WHERE j.pengguna_id = p.pengguna_id) AS total_journals,
  (SELECT COUNT(*) FROM reminders r WHERE r.pengguna_id = p.pengguna_id AND r.aktif=1) AS total_reminders_active,
  (SELECT COUNT(*) FROM moodtracker m WHERE m.pengguna_id = p.pengguna_id) AS total_moods
FROM pengguna p;

-- 7) Seed data (opsional)
--    a) Buat satu user contoh (gunakan BCRYPT hash contoh).
--       Kamu bisa juga daftar lewat halaman register (lebih aman).
INSERT INTO pengguna (nama, email, password_hash)
VALUES
('Pengguna Demo', 'demo@emocare.local',
 '$2y$10$E0NRs3Zp8Q2iO1m3VwWl8u9m5GJtLz8r0f3z6b5b3m5h2yq6b7fV6'); 
-- Password di atas: 'password123' (BCrypt 10 rounds). 
-- Ganti/hapus di produksi!

--    b) Contoh data mood (pakai user di atas)
INSERT INTO moodtracker (pengguna_id, tanggal, mood_level, catatan)
VALUES
(1, CURDATE(), 3, 'Sedikit cemas, tapi oke.'),
(1, CURDATE() - INTERVAL 1 DAY, 2, 'Biasa saja.'),
(1, CURDATE() - INTERVAL 2 DAY, 5, 'Senang sekali!');

--    c) Contoh jurnal & reminder
INSERT INTO journals (pengguna_id, tanggal, judul, isi)
VALUES
(1, CURDATE(), 'Mulai rutin pagi', 'Hari ini mencoba stretching 10 menit.');

INSERT INTO reminders (pengguna_id, judul, reminder_time, aktif)
VALUES 
(1, 'Minum air 8 gelas', '12:30:00', 1);
