<?php
/**
 * simpel_cbt - Live Monitoring API
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.conn.php';
require_once __DIR__ . '/../../helper/cbt.helper.php';
require_once __DIR__ . '/../../helper/auth.helper.php';
require_api_login(['admin', 'pengawas']);
require_csrf();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'list_peserta':
            $id_jadwal = (int)($_GET['id_jadwal'] ?? 0);
            $status    = cbt_clean_input($conn, $_GET['status'] ?? '');

            $where = "WHERE 1=1";
            if ($id_jadwal > 0) {
                $where .= " AND s.id_jadwal = $id_jadwal";
            }
            if (!empty($status)) {
                $where .= " AND s.status_sesi = '$status'";
            }

            $q = "SELECT s.*, j.nama_ujian, j.durasi_menit, j.passing_grade,
                         h.nilai_akhir, h.status_kelulusan, h.total_soal, h.total_dijawab,
                         (SELECT COUNT(*) FROM cbt_jawaban jw WHERE jw.id_sesi = s.id_sesi AND jw.jawaban_dipilih IS NOT NULL AND jw.jawaban_dipilih != '') AS jawaban_terisi,
                         (SELECT COUNT(*) FROM cbt_jawaban jw WHERE jw.id_sesi = s.id_sesi) AS total_soal_sesi
                  FROM cbt_sesi s
                  JOIN cbt_jadwal j ON s.id_jadwal = j.id_jadwal
                  LEFT JOIN cbt_hasil h ON s.id_sesi = h.id_sesi
                  $where
                  ORDER BY s.id_sesi DESC";
            $res = mysqli_query($conn, $q);
            $data = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'force_finish':
            $id_sesi = (int)($_POST['id_sesi'] ?? 0);
            if ($id_sesi <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'ID Sesi tidak valid.']);
                exit;
            }

            $scoreResult = cbt_calculate_score($conn, $id_sesi);
            if ($scoreResult !== false) {
                echo json_encode(['status' => 'success', 'msg' => 'Sesi ujian berhasil diselesaikan secara paksa oleh Administrator.', 'data' => $scoreResult]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal menyelesaikan sesi atau sesi tidak ditemukan.']);
            }
            break;

        case 'reset_sesi':
            $id_sesi = (int)($_POST['id_sesi'] ?? 0);
            if ($id_sesi <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'ID Sesi tidak valid.']);
                exit;
            }

            // Hapus hasil rekapan nilai jika ada
            mysqli_query($conn, "DELETE FROM cbt_hasil WHERE id_sesi = $id_sesi");

            // Ambil durasi jadwal untuk reset timer
            $qJ = mysqli_query($conn, "SELECT j.durasi_menit FROM cbt_sesi s JOIN cbt_jadwal j ON s.id_jadwal = j.id_jadwal WHERE s.id_sesi = $id_sesi LIMIT 1");
            $rJ = mysqli_fetch_assoc($qJ);
            $durasiDetik = ($rJ['durasi_menit'] ?? 60) * 60;
            $now = date('Y-m-d H:i:s');

            // Reset status sesi menjadi sedang_mengerjakan dengan timer baru
            $sql = "UPDATE cbt_sesi SET 
                        status_sesi = 'sedang_mengerjakan', 
                        waktu_mulai = '$now', 
                        waktu_selesai = NULL, 
                        sisa_detik = $durasiDetik, 
                        sisa_detik_subtes = 0 
                    WHERE id_sesi = $id_sesi";

            if (mysqli_query($conn, $sql)) {
                echo json_encode(['status' => 'success', 'msg' => 'Sesi ujian peserta berhasil direset! Peserta dapat melanjutkan ujian kembali.']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal mereset sesi: ' . mysqli_error($conn)]);
            }
            break;

        case 'hapus_sesi':
            $id_sesi = (int)($_POST['id_sesi'] ?? 0);
            if ($id_sesi <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'ID Sesi tidak valid.']);
                exit;
            }

            mysqli_query($conn, "DELETE FROM cbt_hasil WHERE id_sesi = $id_sesi");
            mysqli_query($conn, "DELETE FROM cbt_jawaban WHERE id_sesi = $id_sesi");
            $sql = "DELETE FROM cbt_sesi WHERE id_sesi = $id_sesi";

            if (mysqli_query($conn, $sql)) {
                echo json_encode(['status' => 'success', 'msg' => 'Data sesi peserta berhasil dihapus bersih!']);
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
