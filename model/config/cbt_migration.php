<?php
/**
 * simpel_cbt - Database Schema Migration & Seeder
 */

if (!isset($conn)) {
    require_once __DIR__ . '/database.php';
}

if (!$conn) {
    die("Koneksi database tidak tersedia untuk migrasi.");
}

// 1. Tabel Admin
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `cbt_admin` (
    `id_admin` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `role` VARCHAR(30) NOT NULL DEFAULT 'admin',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. Tabel Kategori / Mata Pelajaran
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `cbt_kategori` (
    `id_kategori` INT(11) NOT NULL AUTO_INCREMENT,
    `nama_kategori` VARCHAR(120) NOT NULL,
    `kode_kategori` VARCHAR(30) NOT NULL UNIQUE,
    `deskripsi` TEXT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 3. Tabel Soal
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `cbt_soal` (
    `id_soal` INT(11) NOT NULL AUTO_INCREMENT,
    `id_kategori` INT(11) NOT NULL,
    `pertanyaan` TEXT NOT NULL,
    `gambar` VARCHAR(255) NULL,
    `opsi_a` TEXT NOT NULL,
    `opsi_b` TEXT NOT NULL,
    `opsi_c` TEXT NOT NULL,
    `opsi_d` TEXT NOT NULL,
    `opsi_e` TEXT NULL,
    `kunci_jawaban` ENUM('A','B','C','D','E') NOT NULL,
    `bobot_nilai` INT(11) NOT NULL DEFAULT 1,
    `pembahasan` TEXT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_soal`),
    INDEX (`id_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 4. Tabel Jadwal / Paket Ujian
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `cbt_jadwal` (
    `id_jadwal` INT(11) NOT NULL AUTO_INCREMENT,
    `nama_ujian` VARCHAR(150) NOT NULL,
    `kode_ujian` VARCHAR(50) NOT NULL UNIQUE,
    `tipe_ujian` ENUM('standar','multi_subtes') NOT NULL DEFAULT 'standar',
    `id_kategori` INT(11) NULL,
    `durasi_menit` INT(11) NOT NULL DEFAULT 60,
    `tgl_mulai` DATETIME NOT NULL,
    `tgl_selesai` DATETIME NOT NULL,
    `acak_soal` TINYINT(1) NOT NULL DEFAULT 1,
    `acak_opsi` TINYINT(1) NOT NULL DEFAULT 0,
    `passing_grade` DECIMAL(5,2) NOT NULL DEFAULT 60.00,
    `token_ujian` VARCHAR(20) NOT NULL,
    `target_jalur` VARCHAR(100) NULL,
    `status_ujian` ENUM('draft','aktif','selesai','arsip') NOT NULL DEFAULT 'aktif',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_jadwal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 5. Tabel Subtes Jadwal
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `cbt_jadwal_subtes` (
    `id_subtes` INT(11) NOT NULL AUTO_INCREMENT,
    `id_jadwal` INT(11) NOT NULL,
    `nama_subtes` VARCHAR(150) NOT NULL,
    `id_kategori` INT(11) NOT NULL,
    `urutan` INT(11) NOT NULL DEFAULT 1,
    `durasi_menit` INT(11) NOT NULL DEFAULT 30,
    `jumlah_soal` INT(11) NOT NULL DEFAULT 0,
    `passing_grade` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `bobot_subtes` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_subtes`),
    INDEX (`id_jadwal`),
    INDEX (`id_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 6. Tabel Sesi Peserta Ujian
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `cbt_sesi` (
    `id_sesi` INT(11) NOT NULL AUTO_INCREMENT,
    `id_jadwal` INT(11) NOT NULL,
    `id_subtes_aktif` INT(11) NULL,
    `subtes_ke` INT(11) NOT NULL DEFAULT 1,
    `no_pendaftaran` VARCHAR(50) NOT NULL,
    `nama_peserta` VARCHAR(150) NULL,
    `waktu_mulai` DATETIME NOT NULL,
    `waktu_mulai_subtes` DATETIME NULL,
    `waktu_selesai` DATETIME NULL,
    `sisa_detik` INT(11) NOT NULL DEFAULT 0,
    `sisa_detik_subtes` INT(11) NOT NULL DEFAULT 0,
    `status_sesi` ENUM('belum_mulai','sedang_mengerjakan','selesai','dibatalkan') NOT NULL DEFAULT 'sedang_mengerjakan',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_sesi`),
    INDEX (`id_jadwal`),
    INDEX (`no_pendaftaran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 7. Tabel Jawaban Peserta Per Butir
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `cbt_jawaban` (
    `id_jawaban` INT(11) NOT NULL AUTO_INCREMENT,
    `id_sesi` INT(11) NOT NULL,
    `id_subtes` INT(11) NULL,
    `id_soal` INT(11) NOT NULL,
    `urutan` INT(11) NOT NULL DEFAULT 1,
    `jawaban_dipilih` VARCHAR(5) NULL,
    `is_ragu` TINYINT(1) NOT NULL DEFAULT 0,
    `is_benar` TINYINT(1) NOT NULL DEFAULT 0,
    `skor_diperoleh` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `waktu_simpan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_jawaban`),
    UNIQUE KEY `sesi_soal` (`id_sesi`, `id_soal`),
    INDEX (`id_sesi`),
    INDEX (`id_subtes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 8. Tabel Hasil / Rekap Nilai Ujian
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `cbt_hasil` (
    `id_hasil` INT(11) NOT NULL AUTO_INCREMENT,
    `id_sesi` INT(11) NOT NULL UNIQUE,
    `id_jadwal` INT(11) NOT NULL,
    `no_pendaftaran` VARCHAR(50) NOT NULL,
    `nama_peserta` VARCHAR(150) NULL,
    `total_soal` INT(11) NOT NULL DEFAULT 0,
    `total_dijawab` INT(11) NOT NULL DEFAULT 0,
    `jumlah_benar` INT(11) NOT NULL DEFAULT 0,
    `jumlah_salah` INT(11) NOT NULL DEFAULT 0,
    `jumlah_kosong` INT(11) NOT NULL DEFAULT 0,
    `nilai_akhir` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `status_kelulusan` ENUM('LULUS','TIDAK_LULUS','PERTIMBANGAN') NOT NULL DEFAULT 'PERTIMBANGAN',
    `catatan` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_hasil`),
    INDEX (`id_jadwal`),
    INDEX (`no_pendaftaran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ==========================================
// SEEDER DATA CONTOH
// ==========================================

// 1. Akun Admin Default (admin / admin123)
$checkAdmin = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM cbt_admin");
$rowAdm = mysqli_fetch_assoc($checkAdmin);
if ($rowAdm['cnt'] == 0) {
    $passHash = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO `cbt_admin` (`username`, `password`, `nama_lengkap`, `role`) VALUES
        ('admin', '$passHash', 'Administrator CBT', 'admin')
    ");
}

// 2. Kategori Seeder
$checkKat = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM cbt_kategori");
$rowKat = mysqli_fetch_assoc($checkKat);
if ($rowKat['cnt'] == 0) {
    mysqli_query($conn, "INSERT INTO `cbt_kategori` (`id_kategori`, `nama_kategori`, `kode_kategori`, `deskripsi`, `status`) VALUES
        (1, 'Tes Potensi Skolastik (TPS)', 'TPS-01', 'Menguji penalaran umum, logika kuantitatif, dan deduksi analitik.', 1),
        (2, 'Kemampuan Bahasa Inggris', 'ENG-01', 'Menguji pemahaman tata bahasa, vocabulary, dan reading comprehension.', 1),
        (3, 'Pengetahuan & Wawasan Umum', 'UMUM-01', 'Menguji wawasan umum, kebangsaan, dan literasi digital.', 1)
    ");
}

// 3. Soal Seeder
$checkSoal = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM cbt_soal");
$rowSoal = mysqli_fetch_assoc($checkSoal);
if ($rowSoal['cnt'] == 0) {
    mysqli_query($conn, "INSERT INTO `cbt_soal` (`id_kategori`, `pertanyaan`, `opsi_a`, `opsi_b`, `opsi_c`, `opsi_d`, `opsi_e`, `kunci_jawaban`, `bobot_nilai`, `pembahasan`, `status`) VALUES
        (1, 'Jika semua peserta wajib membawa kartu ujian, dan sebagian peserta mengenakan pakaian putih, maka kesimpulan yang tepat adalah...', 'Semua peserta yang mengenakan pakaian putih tidak membawa kartu ujian.', 'Sebagian peserta yang mengenakan pakaian putih wajib membawa kartu ujian.', 'Semua yang membawa kartu ujian pasti berpakaian putih.', 'Hanya yang berpakaian putih yang membawa kartu ujian.', 'Peserta yang tidak berpakaian putih tidak boleh ikut ujian.', 'B', 10, 'Karena semua peserta wajib membawa kartu ujian, maka sebagian yang berpakaian putih juga termasuk dalam kewajiban membawa kartu ujian.', 1),
        (1, 'Deret angka: 4, 8, 16, 32, 64, ... Angka berikutnya pada pola barisan geometri ini adalah...', '96', '120', '128', '144', '256', 'C', 10, 'Setiap suku dikalikan 2. 64 x 2 = 128.', 1),
        (1, 'Buku : Halaman = Rumah : ...', 'Jalan', 'Pondasi', 'Atap', 'Ruangan', 'Tanah', 'D', 10, 'Buku terdiri dari halaman-halaman, rumah terdiri dari ruangan-ruangan.', 1),
        (2, 'Choose the most correct sentence for formal academic writing:', 'The committee have decided to postpone the exam.', 'The committee has decided to postpone the examination.', 'The committee are deciding for postponing.', 'The committee were decided to postpone.', 'The committee has decision postponing.', 'B', 10, 'Subjek kolektif tunggal dalam konteks resmi menggunakan has decided dan penulisan formal examination.', 1),
        (2, 'What is the closest synonym for the word \"INNOVATIVE\" in the phrase \"an innovative approach to problem solving\"?', 'Traditional', 'Creative & Pioneering', 'Outdated', 'Repetitive', 'Careless', 'B', 10, 'Innovative berarti kreatif, pionir, atau menghadirkan ide kebaruan.', 1),
        (3, 'Lambang sila ke-4 dalam Pancasila yang bermakna musyawarah untuk mufakat adalah...', 'Bintang Emas', 'Rantai Baja', 'Pohon Beringin', 'Kepala Banteng', 'Padi dan Kapas', 'D', 10, 'Sila ke-4 dilambangkan dengan Kepala Banteng.', 1)
    ");
}

// 4. Jadwal Seeder
$checkJadwal = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM cbt_jadwal");
$rowJadwal = mysqli_fetch_assoc($checkJadwal);
if ($rowJadwal['cnt'] == 0) {
    $tglMulai = date('Y-m-d 00:00:00');
    $tglSelesai = date('Y-m-d 23:59:59', strtotime('+60 days'));
    mysqli_query($conn, "INSERT INTO `cbt_jadwal` (`nama_ujian`, `kode_ujian`, `tipe_ujian`, `id_kategori`, `durasi_menit`, `tgl_mulai`, `tgl_selesai`, `acak_soal`, `acak_opsi`, `passing_grade`, `token_ujian`, `target_jalur`, `status_ujian`) VALUES
        ('Simulasi Ujian CBT Mandiri 2026', 'SIM-CBT-2026', 'standar', NULL, 30, '$tglMulai', '$tglSelesai', 1, 0, 60.00, 'CBT2026', 'Semua Peserta', 'aktif')
    ");
}