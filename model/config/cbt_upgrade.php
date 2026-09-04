<?php
/** Upgrade skema idempoten untuk instalasi lama. Tidak menghapus data. */
if (!isset($conn) || !$conn) { return; }

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cbt_ruang (
 id_ruang INT AUTO_INCREMENT PRIMARY KEY, kode_ruang VARCHAR(30) NOT NULL UNIQUE,
 nama_ruang VARCHAR(100) NOT NULL, lokasi VARCHAR(150) NULL, kapasitas INT NOT NULL DEFAULT 0,
 status TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cbt_peserta (
 id_peserta INT AUTO_INCREMENT PRIMARY KEY, no_peserta VARCHAR(50) NOT NULL UNIQUE,
 nama_lengkap VARCHAR(150) NOT NULL, password VARCHAR(255) NULL, email VARCHAR(150) NULL,
 id_ruang INT NULL, kelompok VARCHAR(100) NULL, status TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
 INDEX(id_ruang), INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cbt_peserta_jadwal (
 id INT AUTO_INCREMENT PRIMARY KEY, id_peserta INT NOT NULL, id_jadwal INT NOT NULL,
 pin_ujian VARCHAR(255) NULL, status VARCHAR(30) NOT NULL DEFAULT 'diizinkan',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY peserta_jadwal(id_peserta,id_jadwal), INDEX(id_jadwal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cbt_audit_log (
 id_log BIGINT AUTO_INCREMENT PRIMARY KEY, id_admin INT NULL, username VARCHAR(50) NULL,
 role VARCHAR(30) NULL, aksi VARCHAR(80) NOT NULL, entitas VARCHAR(80) NULL,
 id_entitas VARCHAR(80) NULL, detail_json LONGTEXT NULL, ip_address VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(created_at), INDEX(aksi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cbt_pengumuman (
 id_pengumuman INT AUTO_INCREMENT PRIMARY KEY, judul VARCHAR(150) NOT NULL, isi TEXT NOT NULL,
 target VARCHAR(30) NOT NULL DEFAULT 'semua', aktif_mulai DATETIME NULL, aktif_selesai DATETIME NULL,
 status TINYINT(1) NOT NULL DEFAULT 1, id_admin INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cbt_jawaban_log (
 id_log BIGINT AUTO_INCREMENT PRIMARY KEY, id_sesi INT NOT NULL, id_soal INT NOT NULL,
 jawaban_lama VARCHAR(5) NULL, jawaban_baru VARCHAR(5) NULL, ip_address VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(id_sesi), INDEX(id_soal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cbt_pelanggaran (
 id_pelanggaran BIGINT AUTO_INCREMENT PRIMARY KEY, id_sesi INT NOT NULL,
 jenis VARCHAR(50) NOT NULL, detail VARCHAR(255) NULL, ip_address VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(id_sesi), INDEX(jenis), INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cbt_proctor_media (
 id_media BIGINT AUTO_INCREMENT PRIMARY KEY,id_sesi INT NOT NULL,
 media_type ENUM('camera','screen') NOT NULL,file_path VARCHAR(255) NOT NULL,
 captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX(id_sesi),INDEX(captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cbt_settings (
 setting_key VARCHAR(100) PRIMARY KEY, setting_value LONGTEXT NULL, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function cbt_add_column_if_missing($conn, $table, $column, $definition) {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $columnSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$tableSafe` LIKE '$columnSafe'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "ALTER TABLE `$tableSafe` ADD COLUMN `$columnSafe` $definition");
    }
}

cbt_add_column_if_missing($conn, 'cbt_jadwal', 'wajib_peserta_terdaftar', "TINYINT(1) NOT NULL DEFAULT 0");
cbt_add_column_if_missing($conn, 'cbt_jadwal', 'tampilkan_hasil', "TINYINT(1) NOT NULL DEFAULT 1");
cbt_add_column_if_missing($conn, 'cbt_jadwal', 'maks_perangkat', "INT NOT NULL DEFAULT 1");
cbt_add_column_if_missing($conn, 'cbt_jadwal', 'nama_sesi', "VARCHAR(100) NOT NULL DEFAULT 'Sesi Utama'");
cbt_add_column_if_missing($conn, 'cbt_sesi', 'tambahan_detik', "INT NOT NULL DEFAULT 0");
cbt_add_column_if_missing($conn, 'cbt_sesi', 'device_token', "VARCHAR(64) NULL");
cbt_add_column_if_missing($conn, 'cbt_sesi', 'alasan_tindakan', "VARCHAR(255) NULL");
cbt_add_column_if_missing($conn, 'cbt_soal', 'tingkat_kesulitan', "ENUM('mudah','sedang','sulit') NOT NULL DEFAULT 'sedang'");
cbt_add_column_if_missing($conn, 'cbt_soal', 'tag_kompetensi', "VARCHAR(150) NULL");
cbt_add_column_if_missing($conn, 'cbt_soal', 'versi', "INT NOT NULL DEFAULT 1");
cbt_add_column_if_missing($conn, 'cbt_pelanggaran', 'resolved_at', "DATETIME NULL");

$statusColumn=mysqli_query($conn,"SHOW COLUMNS FROM cbt_sesi LIKE 'status_sesi'");
$statusInfo=$statusColumn?mysqli_fetch_assoc($statusColumn):null;
if($statusInfo && stripos($statusInfo['Type']??'','ditangguhkan')===false){
    mysqli_query($conn,"ALTER TABLE cbt_sesi MODIFY status_sesi ENUM('belum_mulai','sedang_mengerjakan','ditangguhkan','selesai','dibatalkan') NOT NULL DEFAULT 'sedang_mengerjakan'");
}
