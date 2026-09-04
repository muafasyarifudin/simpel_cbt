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

if ($action === 'proctor_media') {
    $id=(int)($_GET['id_media']??0);$q=mysqli_query($conn,"SELECT file_path FROM cbt_proctor_media WHERE id_media=$id LIMIT 1");$row=$q?mysqli_fetch_assoc($q):null;
    $root=realpath(__DIR__.'/../../../uploads/proctor');$file=$row?realpath(__DIR__.'/../../../'.$row['file_path']):false;
    if(!$root||!$file||strpos($file,$root)!==0||!is_file($file)){http_response_code(404);exit;}
    header('Content-Type: image/jpeg');header('Content-Length: '.filesize($file));header('Cache-Control: private, max-age=10');readfile($file);exit;
}

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

            $q = "SELECT s.*, j.nama_ujian, j.nama_sesi, j.durasi_menit, j.passing_grade,
                         h.nilai_akhir, h.status_kelulusan, h.total_soal, h.total_dijawab,
                         (SELECT COUNT(*) FROM cbt_jawaban jw WHERE jw.id_sesi = s.id_sesi AND jw.jawaban_dipilih IS NOT NULL AND jw.jawaban_dipilih != '') AS jawaban_terisi,
                         (SELECT COUNT(*) FROM cbt_jawaban jw WHERE jw.id_sesi = s.id_sesi) AS total_soal_sesi
                         ,(SELECT COUNT(*) FROM cbt_pelanggaran pl WHERE pl.id_sesi = s.id_sesi) AS total_pelanggaran
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

        case 'participant_detail':
            $id_sesi=(int)($_GET['id_sesi']??0);if($id_sesi<=0){echo json_encode(['status'=>'error','msg'=>'ID sesi tidak valid.']);exit;}
            $sessionQ=mysqli_query($conn,"SELECT s.*,j.nama_ujian,j.nama_sesi,j.kode_ujian FROM cbt_sesi s JOIN cbt_jadwal j ON j.id_jadwal=s.id_jadwal WHERE s.id_sesi=$id_sesi LIMIT 1");$session=$sessionQ?mysqli_fetch_assoc($sessionQ):null;
            if(!$session){echo json_encode(['status'=>'error','msg'=>'Sesi tidak ditemukan.']);exit;}
            $answers=[];$aq=mysqli_query($conn,"SELECT jw.urutan,jw.jawaban_dipilih,jw.is_ragu,jw.is_benar,jw.waktu_simpan,s.pertanyaan,s.kunci_jawaban FROM cbt_jawaban jw JOIN cbt_soal s ON s.id_soal=jw.id_soal WHERE jw.id_sesi=$id_sesi ORDER BY jw.urutan");while($aq&&$r=mysqli_fetch_assoc($aq)){$r['pertanyaan_preview']=mb_strimwidth(strip_tags($r['pertanyaan']),0,100,'...');unset($r['pertanyaan']);$answers[]=$r;}
            $violations=[];$vq=mysqli_query($conn,"SELECT jenis,detail,created_at,resolved_at FROM cbt_pelanggaran WHERE id_sesi=$id_sesi ORDER BY id_pelanggaran DESC");while($vq&&$r=mysqli_fetch_assoc($vq))$violations[]=$r;
            $media=[];$mq=mysqli_query($conn,"SELECT id_media,media_type,file_path,captured_at FROM cbt_proctor_media WHERE id_sesi=$id_sesi ORDER BY id_media DESC LIMIT 100");while($mq&&$r=mysqli_fetch_assoc($mq))$media[]=$r;
            echo json_encode(['status'=>'success','session'=>$session,'answers'=>$answers,'violations'=>$violations,'media'=>$media]);break;

        case 'force_finish':
            $id_sesi = (int)($_POST['id_sesi'] ?? 0);
            $alasan = cbt_clean_input($conn, $_POST['alasan'] ?? 'Diselesaikan oleh petugas');
            if ($id_sesi <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'ID Sesi tidak valid.']);
                exit;
            }

            $scoreResult = cbt_calculate_score($conn, $id_sesi);
            if ($scoreResult !== false) {
                mysqli_query($conn, "UPDATE cbt_sesi SET alasan_tindakan='$alasan' WHERE id_sesi=$id_sesi");
                audit_log($conn, 'force_finish', 'sesi', $id_sesi, ['alasan'=>$alasan]);
                echo json_encode(['status' => 'success', 'msg' => 'Sesi ujian berhasil diselesaikan secara paksa oleh Administrator.', 'data' => $scoreResult]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal menyelesaikan sesi atau sesi tidak ditemukan.']);
            }
            break;

        case 'reset_sesi':
            $id_sesi = (int)($_POST['id_sesi'] ?? 0);
            $alasan = cbt_clean_input($conn, $_POST['alasan'] ?? 'Reset oleh petugas');
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
                        , alasan_tindakan = '$alasan'
                    WHERE id_sesi = $id_sesi";

            if (mysqli_query($conn, $sql)) {
                mysqli_query($conn,"UPDATE cbt_pelanggaran SET resolved_at=NOW() WHERE id_sesi=$id_sesi AND resolved_at IS NULL");
                audit_log($conn, 'reset_sesi', 'sesi', $id_sesi, ['alasan'=>$alasan]);
                echo json_encode(['status' => 'success', 'msg' => 'Sesi ujian peserta berhasil direset! Peserta dapat melanjutkan ujian kembali.']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal mereset sesi: ' . mysqli_error($conn)]);
            }
            break;

        case 'tambah_waktu':
            $id_sesi=(int)($_POST['id_sesi']??0); $menit=max(1,min(180,(int)($_POST['menit']??0)));
            $alasan=cbt_clean_input($conn,$_POST['alasan']??'Tambahan waktu oleh petugas');
            if($id_sesi<=0){echo json_encode(['status'=>'error','msg'=>'ID sesi tidak valid.']);exit;}
            $detik=$menit*60;
            $ok=mysqli_query($conn,"UPDATE cbt_sesi SET tambahan_detik=tambahan_detik+$detik,sisa_detik=sisa_detik+$detik,alasan_tindakan='$alasan' WHERE id_sesi=$id_sesi AND status_sesi='sedang_mengerjakan'");
            if($ok&&mysqli_affected_rows($conn)>0){audit_log($conn,'tambah_waktu','sesi',$id_sesi,['menit'=>$menit,'alasan'=>$alasan]);echo json_encode(['status'=>'success','msg'=>"Tambahan waktu $menit menit diberikan."]);}else echo json_encode(['status'=>'error','msg'=>'Sesi tidak aktif atau tidak ditemukan.']);
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
                audit_log($conn, 'hapus_sesi', 'sesi', $id_sesi);
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
