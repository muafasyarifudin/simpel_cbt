<?php
/**
 * simpel_cbt - Jadwal CRUD Handler
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.conn.php';
require_once __DIR__ . '/../../helper/cbt.helper.php';
require_once __DIR__ . '/../../helper/auth.helper.php';
require_api_login(['admin']);
require_csrf();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'list':
            $status = cbt_clean_input($conn, $_GET['status'] ?? '');
            $where = "WHERE 1=1";
            if (!empty($status)) {
                $where .= " AND j.status_ujian = '$status'";
            }

            $q = "SELECT j.*, k.nama_kategori,
                         (SELECT COUNT(*) FROM cbt_sesi s WHERE s.id_jadwal = j.id_jadwal) AS total_peserta,
                         (SELECT COUNT(*) FROM cbt_jadwal_subtes js WHERE js.id_jadwal = j.id_jadwal) AS total_subtes
                  FROM cbt_jadwal j
                  LEFT JOIN cbt_kategori k ON j.id_kategori = k.id_kategori
                  $where
                  ORDER BY j.id_jadwal DESC";
            $res = mysqli_query($conn, $q);
            $data = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'detail':
            $id = (int)($_GET['id_jadwal'] ?? 0);
            $q = "SELECT j.*, k.nama_kategori 
                  FROM cbt_jadwal j 
                  LEFT JOIN cbt_kategori k ON j.id_kategori = k.id_kategori 
                  WHERE j.id_jadwal = $id LIMIT 1";
            $res = mysqli_query($conn, $q);
            $row = mysqli_fetch_assoc($res);
            if ($row) {
                // Ambil daftar subtes jika ada
                $subtesList = cbt_get_jadwal_subtes($conn, $id);
                $row['subtes_list'] = $subtesList;
                echo json_encode(['status' => 'success', 'data' => $row]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Jadwal tidak ditemukan.']);
            }
            break;

        case 'generate_token':
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $token = '';
            for ($i = 0; $i < 6; $i++) {
                $token .= $chars[rand(0, strlen($chars) - 1)];
            }
            echo json_encode(['status' => 'success', 'token' => $token]);
            break;

        case 'save':
            $id           = (int)($_POST['id_jadwal'] ?? 0);
            $nama         = cbt_clean_input($conn, $_POST['nama_ujian'] ?? '');
            $kode         = strtoupper(cbt_clean_input($conn, $_POST['kode_ujian'] ?? ''));
            $tipe         = in_array($_POST['tipe_ujian'] ?? '', ['standar', 'multi_subtes']) ? $_POST['tipe_ujian'] : 'standar';
            $id_kategori  = !empty($_POST['id_kategori']) ? (int)$_POST['id_kategori'] : 'NULL';
            $durasi       = (int)($_POST['durasi_menit'] ?? 60);
            $tgl_mulai    = cbt_clean_input($conn, $_POST['tgl_mulai'] ?? '');
            $tgl_selesai  = cbt_clean_input($conn, $_POST['tgl_selesai'] ?? '');
            $acak_soal    = isset($_POST['acak_soal']) ? (int)$_POST['acak_soal'] : 1;
            $acak_opsi    = isset($_POST['acak_opsi']) ? (int)$_POST['acak_opsi'] : 0;
            $pass_grade   = (float)($_POST['passing_grade'] ?? 60.00);
            $token        = strtoupper(cbt_clean_input($conn, $_POST['token_ujian'] ?? ''));
            $target_jalur = cbt_clean_input($conn, $_POST['target_jalur'] ?? 'Semua Peserta');
            $status_ujian = in_array($_POST['status_ujian'] ?? '', ['draft', 'aktif', 'selesai', 'arsip']) ? $_POST['status_ujian'] : 'aktif';

            if (empty($nama) || empty($kode) || empty($token) || empty($tgl_mulai) || empty($tgl_selesai)) {
                echo json_encode(['status' => 'error', 'msg' => 'Nama Ujian, Kode, Token, dan Waktu Ujian wajib diisi!']);
                exit;
            }

            // Cek keunikan kode_ujian
            $checkKode = mysqli_query($conn, "SELECT id_jadwal FROM cbt_jadwal WHERE kode_ujian = '$kode' AND id_jadwal != $id LIMIT 1");
            if (mysqli_num_rows($checkKode) > 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Kode Ujian sudah digunakan!']);
                exit;
            }

            $idKatVal = ($tipe === 'standar' && $id_kategori !== 'NULL') ? $id_kategori : "NULL";

            if ($id > 0) {
                $sql = "UPDATE cbt_jadwal SET 
                            nama_ujian = '$nama',
                            kode_ujian = '$kode',
                            tipe_ujian = '$tipe',
                            id_kategori = $idKatVal,
                            durasi_menit = $durasi,
                            tgl_mulai = '$tgl_mulai',
                            tgl_selesai = '$tgl_selesai',
                            acak_soal = $acak_soal,
                            acak_opsi = $acak_opsi,
                            passing_grade = $pass_grade,
                            token_ujian = '$token',
                            target_jalur = '$target_jalur',
                            status_ujian = '$status_ujian'
                        WHERE id_jadwal = $id";
                $jadwalId = $id;
            } else {
                $sql = "INSERT INTO cbt_jadwal 
                            (nama_ujian, kode_ujian, tipe_ujian, id_kategori, durasi_menit, tgl_mulai, tgl_selesai, acak_soal, acak_opsi, passing_grade, token_ujian, target_jalur, status_ujian)
                        VALUES 
                            ('$nama', '$kode', '$tipe', $idKatVal, $durasi, '$tgl_mulai', '$tgl_selesai', $acak_soal, $acak_opsi, $pass_grade, '$token', '$target_jalur', '$status_ujian')";
            }

            if (mysqli_query($conn, $sql)) {
                if ($id <= 0) {
                    $jadwalId = mysqli_insert_id($conn);
                }

                // Handle subtes jika multi_subtes
                if ($tipe === 'multi_subtes' && isset($_POST['subtes']) && is_array($_POST['subtes'])) {
                    // Hapus subtes lama yang tidak ada di form baru
                    mysqli_query($conn, "DELETE FROM cbt_jadwal_subtes WHERE id_jadwal = $jadwalId");

                    $urutan = 1;
                    foreach ($_POST['subtes'] as $st) {
                        $stNama = cbt_clean_input($conn, $st['nama'] ?? 'Subtes ' . $urutan);
                        $stKat  = (int)($st['id_kategori'] ?? 0);
                        $stDur  = (int)($st['durasi'] ?? 30);
                        $stJml  = (int)($st['jumlah_soal'] ?? 0);
                        $stPass = (float)($st['passing_grade'] ?? 0.00);

                        if ($stKat > 0) {
                            mysqli_query($conn, "INSERT INTO cbt_jadwal_subtes 
                                (id_jadwal, nama_subtes, id_kategori, urutan, durasi_menit, jumlah_soal, passing_grade) 
                                VALUES 
                                ($jadwalId, '$stNama', $stKat, $urutan, $stDur, $stJml, $stPass)");
                            $urutan++;
                        }
                    }
                }

                echo json_encode(['status' => 'success', 'msg' => 'Jadwal ujian berhasil disimpan!']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal menyimpan: ' . mysqli_error($conn)]);
            }
            break;

        case 'toggle_status':
            $id = (int)($_POST['id_jadwal'] ?? 0);
            $newStatus = cbt_clean_input($conn, $_POST['status'] ?? 'aktif');
            if ($id <= 0 || !in_array($newStatus, ['aktif', 'draft', 'selesai', 'arsip'])) {
                echo json_encode(['status' => 'error', 'msg' => 'Parameter tidak valid.']);
                exit;
            }

            $sql = "UPDATE cbt_jadwal SET status_ujian = '$newStatus' WHERE id_jadwal = $id";
            if (mysqli_query($conn, $sql)) {
                echo json_encode(['status' => 'success', 'msg' => 'Status ujian berhasil diubah!']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal mengubah status: ' . mysqli_error($conn)]);
            }
            break;

        case 'delete':
            $id = (int)($_POST['id_jadwal'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'ID Jadwal tidak valid.']);
                exit;
            }

            // Cek apakah ada sesi peserta
            $checkSesi = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM cbt_sesi WHERE id_jadwal = $id");
            $rSesi = mysqli_fetch_assoc($checkSesi);
            if ($rSesi['cnt'] > 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Jadwal tidak dapat dihapus karena sudah memiliki data riwayat sesi pengerjaan peserta! Ubah status menjadi "arsip" jika sudah selesai.']);
                exit;
            }

            mysqli_query($conn, "DELETE FROM cbt_jadwal_subtes WHERE id_jadwal = $id");
            $sql = "DELETE FROM cbt_jadwal WHERE id_jadwal = $id";
            if (mysqli_query($conn, $sql)) {
                echo json_encode(['status' => 'success', 'msg' => 'Jadwal ujian berhasil dihapus!']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal menghapus: ' . mysqli_error($conn)]);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'msg' => 'Aksi tidak valid']);
            break;
    }
} catch (\Throwable $th) {
    echo json_encode(['status' => 'error', 'msg' => $th->getMessage()]);
}
