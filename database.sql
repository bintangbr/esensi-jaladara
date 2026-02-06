-- ========================================
-- DATABASE ABSENSI SISTEM
-- ========================================

-- Create Database
CREATE DATABASE IF NOT EXISTS absensi_db;
USE absensi_db;

-- ========================================
-- TABLE 1: USERS
-- ========================================
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'hrd', 'karyawan') NOT NULL DEFAULT 'karyawan',
  gaji_harian DECIMAL(10, 2) DEFAULT 0,
  tarif_lembur_per_jam DECIMAL(10, 2) DEFAULT 0,
  status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLE 2: ABSENSI
-- ========================================
CREATE TABLE absensi (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  tanggal DATE NOT NULL,
  jam_masuk TIME,
  jam_pulang TIME,
  total_jam DECIMAL(5, 2),
  jam_lembur DECIMAL(5, 2) DEFAULT 0,
  selfie_masuk VARCHAR(255),
  selfie_pulang VARCHAR(255),
  lat_masuk DECIMAL(10, 8),
  lng_masuk DECIMAL(11, 8),
  lat_pulang DECIMAL(10, 8),
  lng_pulang DECIMAL(11, 8),
  status ENUM('belum_absen', 'absen_masuk', 'absen_pulang', 'selesai') DEFAULT 'belum_absen',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_tanggal (user_id, tanggal),
  INDEX idx_tanggal (tanggal),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLE 3: SETTINGS
-- ========================================
CREATE TABLE settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nama_setting VARCHAR(100) UNIQUE NOT NULL,
  value TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- INSERT DEFAULT SETTINGS
-- ========================================
INSERT INTO settings (nama_setting, value) VALUES
('jam_kerja_standar', '8'),
('gps_kantor_latitude', '-6.2088'),
('gps_kantor_longitude', '106.8456'),
('gps_kantor_radius', '100'),
('nama_perusahaan', 'PT. Aplikasi Absensi'),
('alamat_kantor', 'Jakarta, Indonesia');

-- ========================================
-- INSERT SAMPLE USERS
-- ========================================
-- Password: admin123 (hash dari password_hash('admin123', PASSWORD_BCRYPT))
INSERT INTO users (nama, email, password, role, gaji_harian, tarif_lembur_per_jam, status) VALUES
('Admin Sistem', 'admin@absensi.com', '$2y$10$YdQDpXMkLRpBHDN.jQxB1uVGjx/zGwXkxc5K5GzNbH3jkGzLe', 'admin', 0, 0, 'aktif'),
('HRD Manager', 'hrd@absensi.com', '$2y$10$YdQDpXMkLRpBHDN.jQxB1uVGjx/zGwXkxc5K5GzNbH3jkGzLe', 'hrd', 0, 0, 'aktif'),
('Budi Santoso', 'budi@absensi.com', '$2y$10$YdQDpXMkLRpBHDN.jQxB1uVGjx/zGwXkxc5K5GzNbH3jkGzLe', 'karyawan', 250000, 50000, 'aktif'),
('Siti Nurhaliza', 'siti@absensi.com', '$2y$10$YdQDpXMkLRpBHDN.jQxB1uVGjx/zGwXkxc5K5GzNbH3jkGzLe', 'karyawan', 250000, 50000, 'aktif'),
('Ahmad Hidayat', 'ahmad@absensi.com', '$2y$10$YdQDpXMkLRpBHDN.jQxB1uVGjx/zGwXkxc5K5GzNbH3jkGzLe', 'karyawan', 300000, 60000, 'aktif');

-- ========================================
-- SAMPLE ATTENDANCE DATA
-- ========================================
INSERT INTO absensi (user_id, tanggal, jam_masuk, jam_pulang, total_jam, jam_lembur, selfie_masuk, selfie_pulang, lat_masuk, lng_masuk, lat_pulang, lng_pulang, status) VALUES
(3, CURDATE(), '08:15:00', '17:30:00', 9.25, 1.25, 'selfie_3_20260205_masuk.jpg', 'selfie_3_20260205_pulang.jpg', -6.2088, 106.8456, -6.2088, 106.8456, 'selesai'),
(4, CURDATE(), '08:05:00', '16:45:00', 8.67, 0.67, 'selfie_4_20260205_masuk.jpg', 'selfie_4_20260205_pulang.jpg', -6.2088, 106.8456, -6.2088, 106.8456, 'selesai'),
(5, CURDATE(), '07:55:00', '17:15:00', 9.33, 1.33, 'selfie_5_20260205_masuk.jpg', 'selfie_5_20260205_pulang.jpg', -6.2088, 106.8456, -6.2088, 106.8456, 'selesai');
