-- Buat database MySQL untuk EmoCare
-- Jalankan:  CREATE DATABASE emocare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; USE emocare;

CREATE TABLE IF NOT EXISTS pengguna (
  pengguna_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  peran ENUM('user','admin') DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS moodtracker (
  mood_id INT AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT NOT NULL,
  tanggal DATE NOT NULL DEFAULT (CURRENT_DATE),
  mood_level TINYINT NOT NULL CHECK (mood_level BETWEEN 1 AND 5),
  catatan TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mood_user FOREIGN KEY (pengguna_id) REFERENCES pengguna(pengguna_id)
);

CREATE TABLE IF NOT EXISTS jurnal_harian (
  jurnal_id INT AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT NOT NULL,
  tanggal DATE NOT NULL DEFAULT (CURRENT_DATE),
  judul VARCHAR(100) NOT NULL,
  isi_jurnal TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_jurnal_user FOREIGN KEY (pengguna_id) REFERENCES pengguna(pengguna_id)
);

CREATE TABLE IF NOT EXISTS pengingat_selfcare (
  pengingat_id INT AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT NOT NULL,
  jenis_pengingat VARCHAR(100) NOT NULL,
  waktu_pengingat TIME NOT NULL,
  status BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pengingat_user FOREIGN KEY (pengguna_id) REFERENCES pengguna(pengguna_id)
);

CREATE TABLE IF NOT EXISTS kuis_psikologi (
  kuis_id INT AUTO_INCREMENT PRIMARY KEY,
  judul_kuis VARCHAR(200) NOT NULL,
  deskripsi TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pertanyaan_kuis (
  pertanyaan_id INT AUTO_INCREMENT PRIMARY KEY,
  kuis_id INT NOT NULL,
  pertanyaan VARCHAR(500) NOT NULL,
  opsi_a VARCHAR(255) NOT NULL,
  opsi_b VARCHAR(255) NOT NULL,
  opsi_c VARCHAR(255) NOT NULL,
  opsi_d VARCHAR(255) NOT NULL,
  jawaban_benar CHAR(1) NOT NULL CHECK (jawaban_benar IN ('A','B','C','D')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pertanyaan_kuis FOREIGN KEY (kuis_id) REFERENCES kuis_psikologi(kuis_id)
);

CREATE TABLE IF NOT EXISTS hasil_kuis (
  hasil_id INT AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT NOT NULL,
  kuis_id INT NOT NULL,
  skor INT NOT NULL,
  total_soal INT NOT NULL,
  jumlah_benar INT NOT NULL,
  tanggal_dikerjakan DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hasil_user FOREIGN KEY (pengguna_id) REFERENCES pengguna(pengguna_id),
  CONSTRAINT fk_hasil_kuis FOREIGN KEY (kuis_id) REFERENCES kuis_psikologi(kuis_id)
);
