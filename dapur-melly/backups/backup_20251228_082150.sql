DROP TABLE IF EXISTS karyawan;
CREATE TABLE `karyawan` (
  `id_karyawan` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  `gaji_pokok` decimal(12,2) NOT NULL,
  `lama_bekerja` int(11) DEFAULT NULL COMMENT 'dalam bulan',
  `posisi` varchar(50) DEFAULT NULL,
  `tgl_masuk` date DEFAULT NULL,
  `tgl_gajian_rutin` tinyint(4) DEFAULT NULL COMMENT 'contoh: 25',
  `tgl_gajian` date DEFAULT NULL,
  PRIMARY KEY (`id_karyawan`),
  KEY `fk_karyawan_user` (`user_id`),
  CONSTRAINT `fk_karyawan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO karyawan VALUES("4","3","","081234567890","2000000.00","76","Baker","2025-12-21","26","");
INSERT INTO karyawan VALUES("5","23","","6285161869428","30000000.00","12","Baker","2025-12-22","5","");
INSERT INTO karyawan VALUES("6","24","","6285157997271","5000000.00","80","Dekor","2025-12-22","20","2025-12-28");
INSERT INTO karyawan VALUES("11","28","","6285161869428","5000000.00","0","Dekor","2025-12-25","25","");
INSERT INTO karyawan VALUES("12","27","","6285161869428","3000000.00","20","Baker","2025-12-25","25","");
INSERT INTO karyawan VALUES("13","29","","6285157997271","4000000.00","","Baker ","2025-12-25","1","");
INSERT INTO karyawan VALUES("14","2","","6281212170909","0.00","","Admin","","","");

DROP TABLE IF EXISTS laporan_bulanan;
CREATE TABLE `laporan_bulanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bulan` varchar(2) NOT NULL,
  `tahun` varchar(4) NOT NULL,
  `pendapatan` decimal(15,2) DEFAULT 0.00,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bulan_tahun` (`bulan`,`tahun`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO laporan_bulanan VALUES("1","12","2025","65000000.00","0","2025-12-26 16:34:47");

DROP TABLE IF EXISTS login_history;
CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_login` (`user_id`,`login_time`),
  CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS pendapatan_harian;
CREATE TABLE `pendapatan_harian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `bulan` varchar(2) NOT NULL,
  `tahun` varchar(4) NOT NULL,
  `offline` decimal(15,2) NOT NULL DEFAULT 0.00,
  `online` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_harian` decimal(15,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tanggal` (`tanggal`),
  KEY `idx_bulan_tahun` (`bulan`,`tahun`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pendapatan_harian VALUES("1","2025-12-20","12","2025","5000000.00","3000000.00","8000000.00","Hari ramai weekend","20","2025-12-26 16:28:31","2025-12-26 16:28:31");
INSERT INTO pendapatan_harian VALUES("2","2025-12-21","12","2025","4500000.00","2500000.00","7000000.00","Hari biasa","20","2025-12-26 16:28:31","2025-12-26 16:28:31");
INSERT INTO pendapatan_harian VALUES("3","2025-12-22","12","2025","6000000.00","4000000.00","10000000.00","Ada pesanan katering kecil","20","2025-12-26 16:28:31","2025-12-26 16:28:31");
INSERT INTO pendapatan_harian VALUES("4","2025-12-26","12","2025","10000000.00","30000000.00","40000000.00","diinput","0","2025-12-26 16:34:47","2025-12-26 16:34:47");

DROP TABLE IF EXISTS penggajian;
CREATE TABLE `penggajian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_karyawan` int(11) NOT NULL,
  `bulan` tinyint(4) NOT NULL,
  `tahun` year(4) NOT NULL,
  `tunjangan` decimal(12,2) DEFAULT 0.00,
  `potongan` decimal(12,2) DEFAULT 0.00,
  `gaji_bersih` decimal(12,2) NOT NULL,
  `status_bayar` enum('lunas','belum') DEFAULT 'belum',
  `tgl_bayar_aktual` date DEFAULT NULL,
  `status` enum('Belum','Lunas') DEFAULT 'Belum',
  PRIMARY KEY (`id`),
  KEY `fk_penggajian_karyawan` (`id_karyawan`),
  CONSTRAINT `fk_penggajian_karyawan` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id_karyawan`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO penggajian VALUES("4","4","12","2025","200000.00","100000.00","2100000.00","belum","","Belum");
INSERT INTO penggajian VALUES("5","5","12","2025","100000.00","200000.00","2400000.00","lunas","2025-12-22","Belum");
INSERT INTO penggajian VALUES("6","6","12","2025","200000.00","400000.00","4800000.00","lunas","2025-12-22","Belum");
INSERT INTO penggajian VALUES("8","11","12","2025","1000000.00","0.00","6000000.00","lunas","2025-12-25","Belum");
INSERT INTO penggajian VALUES("9","13","12","2025","200000.00","0.00","4200000.00","belum","","Belum");
INSERT INTO penggajian VALUES("10","12","12","2025","1200000.00","0.00","4200000.00","lunas","2025-12-25","Belum");

DROP TABLE IF EXISTS pesan_sistem;
CREATE TABLE `pesan_sistem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `sender_name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `tujuan_role` varchar(20) DEFAULT 'admin',
  `tujuan_user_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tujuan` (`tujuan_role`,`tujuan_user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS presensi;
CREATE TABLE `presensi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_karyawan` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `status` enum('masuk','izin','sakit','alpa') NOT NULL,
  `keterangan_lembur` text DEFAULT NULL,
  `jam_mulai_lembur` time DEFAULT NULL,
  `jam_selesai_lembur` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_presensi_karyawan` (`id_karyawan`),
  CONSTRAINT `fk_presensi_karyawan` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id_karyawan`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS produk_unggulan;
CREATE TABLE `produk_unggulan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_produk` varchar(100) NOT NULL,
  `jumlah_terjual` int(11) NOT NULL,
  `foto` varchar(255) NOT NULL,
  `posisi` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO produk_unggulan VALUES("1","Cheese Stik","120","1766149919_cheesestik.jpg","1");
INSERT INTO produk_unggulan VALUES("2","Chiffon Cheese","100","1766149937_chiffon.jpg","2");
INSERT INTO produk_unggulan VALUES("3","Asinan Kuah Lemon","80","1766149955_asinan.jpg","3");

DROP TABLE IF EXISTS target_pendapatan;
CREATE TABLE `target_pendapatan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bulan` varchar(2) NOT NULL,
  `tahun` varchar(4) NOT NULL,
  `target_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bulan_tahun` (`bulan`,`tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS users;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','owner','user') NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT 'default.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users VALUES("2","admin melly","$2y$10$MMx0uqu0Kt7RRXKlpq0NaeG4X7mOCyAU1uzOgXO1ewVNwx/1BzN9.","owner","Admin Dapur Melly","","","default.png","2025-12-25 10:52:27","2025-12-28 14:21:36");
INSERT INTO users VALUES("3","karyawan1","user123","user","Siti Aminah","","","default.png","2025-12-25 10:52:27","2025-12-25 10:52:27");
INSERT INTO users VALUES("20","admin","$2y$10$Hp1K9uInmiuIO7UoY351/OHUXn0H.0ByYz/Dtg2WToJEgdi8hXTxS","admin","Admin Dapur Melly","","","profile_20_1766406235.jpeg","2025-12-25 10:52:27","2025-12-25 13:53:07");
INSERT INTO users VALUES("23","putri","$2y$10$Wpxqf0b.vqmWmUVd4GF49OumpymPcHdqwx34L9iqToJk09Yc6Kexi","user","Putri ","","","default.png","2025-12-25 10:52:27","2025-12-25 10:52:27");
INSERT INTO users VALUES("24","rumi123","$2y$10$SwJmIRNvjpbEFY2z/g4jtu1HHTCnqr3/VaNo9y9QYncbpIcmUkgyS","user","Rumi","","","default.png","2025-12-25 10:52:27","2025-12-25 10:52:27");
INSERT INTO users VALUES("27","zoey","$2y$10$cUUCj1MOYVVWWz4/emTh1uHMuPgcLo2ZxvKAOKV9/wWE1jIyYmwue","user","zoey","","","default.png","2025-12-25 11:00:58","2025-12-25 11:00:58");
INSERT INTO users VALUES("28","Binbin","$2y$10$ETkxYaA4YzqCqOTG/U.Np.sTcT3.UcINyM.HY8duHof/Ua/U10BNy","user","Bintang","","","default.png","2025-12-25 11:09:38","2025-12-25 11:09:38");
INSERT INTO users VALUES("29","bb","$2y$10$ItpmzLny6apHyZ.URyRWseHqK39MhaJo4Bwt4cs3dL6NClGI.lqWm","user","Bulan","","","default.png","2025-12-25 11:26:15","2025-12-25 11:26:15");

