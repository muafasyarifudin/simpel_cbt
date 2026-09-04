<?php
/**
 * simpel_cbt - CBT Exam Engine API with Universal Auth Bridge
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.conn.php';
require_once __DIR__ . '/../../helper/cbt.helper.php';
require_once __DIR__ . '/../../helper/auth_bridge.helper.php';
require_once __DIR__ . '/../../helper/auth.helper.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'lookup_peserta':
            $no_peserta = trim($_GET['no_peserta'] ?? ($_POST['no_peserta'] ?? ''));
            if (empty($no_peserta)) {
                echo json_encode(['status' => 'error', 'msg' => 'Nomor Peserta belum diisi.']);
                exit;
            }

            $bridgeConfig = get_auth_bridge_config();
            $verify = null;
            if (($bridgeConfig['mode'] ?? 'standalone') === 'standalone') {
                $noEsc = mysqli_real_escape_string($conn, $no_peserta);
                $pq = mysqli_query($conn, "SELECT no_peserta,nama_lengkap FROM cbt_peserta WHERE no_peserta='$noEsc' AND status=1 LIMIT 1");
                if ($pq && ($pr = mysqli_fetch_assoc($pq))) {
                    $verify = ['valid'=>true,'name'=>$pr['nama_lengkap'],'username'=>$pr['no_peserta'],'mode'=>'standalone_registered'];
                }
            }
            if ($verify === null) $verify = auth_bridge_verify_user($no_peserta);
            if ($verify['valid']) {
                echo json_encode([
                    'status'   => 'success',
                    'name'     => $verify['name'],
                    'username' => $verify['username'],
                    'mode'     => $verify['mode']
                ]);
            } else {
                echo json_encode([
                    'status' => 'not_found',
                    'msg'    => $verify['msg']
                ]);
            }
            break;

        case 'start':
            $id_jadwal    = (int)($_POST['id_jadwal'] ?? 0);
            $token_input  = strtoupper(trim($_POST['token_ujian'] ?? ''));
            $no_peserta   = strtoupper(cbt_clean_input($conn, $_POST['no_peserta'] ?? ''));
            $nama_input   = cbt_clean_input($conn, $_POST['nama_peserta'] ?? '');
            $password     = trim($_POST['auth_password'] ?? '');

            if (empty($no_peserta)) {
                echo json_encode(['status' => 'error', 'msg' => 'Nomor Peserta / User ID wajib diisi!']);
                exit;
            }

            // 1. VERIFIKASI VIA UNIVERSAL AUTH BRIDGE
            $verify = auth_bridge_verify_user($no_peserta, $password);
            if (!$verify['valid']) {
                echo json_encode(['status' => 'error', 'msg' => $verify['msg']]);
                exit;
            }

            // Gunakan data nama resmi hasil verifikasi dari database klien
            $nama_peserta = !empty($verify['name']) ? $verify['name'] : (!empty($nama_input) ? $nama_input : $no_peserta);
            $no_peserta   = $verify['username'];
            $no_peserta   = cbt_clean_input($conn, $no_peserta);
            $nama_peserta = cbt_clean_input($conn, $nama_peserta);

            if ($id_jadwal <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Pilih paket ujian terlebih dahulu!']);
                exit;
            }

            // Ambil jadwal
            $qJ = mysqli_query($conn, "SELECT * FROM cbt_jadwal WHERE id_jadwal = $id_jadwal LIMIT 1");
            $jadwal = mysqli_fetch_assoc($qJ);
            if (!$jadwal) {
                echo json_encode(['status' => 'error', 'msg' => 'Jadwal ujian tidak ditemukan!']);
                exit;
            }

            if ($jadwal['status_ujian'] !== 'aktif') {
                echo json_encode(['status' => 'error', 'msg' => 'Paket ujian ini sedang tidak berstatus aktif.']);
                exit;
            }

            if (!empty($jadwal['wajib_peserta_terdaftar'])) {
                $participantId = (int)($verify['raw']['id_peserta'] ?? 0);
                $assignment = mysqli_query($conn, "SELECT 1 FROM cbt_peserta_jadwal WHERE id_peserta=$participantId AND id_jadwal=$id_jadwal AND status='diizinkan' LIMIT 1");
                if ($participantId <= 0 || !$assignment || mysqli_num_rows($assignment) === 0) {
                    echo json_encode(['status'=>'error','msg'=>'Anda tidak terdaftar pada paket ujian ini.']); exit;
                }
            }

            // Validasi Token
            if (strtoupper($jadwal['token_ujian']) !== $token_input) {
                echo json_encode(['status' => 'error', 'msg' => 'Token Ujian salah! Silakan periksa kembali token dari panitia/pengawas.']);
                exit;
            }

            // Cek Rentang Waktu
            $now = date('Y-m-d H:i:s');
            if ($now < $jadwal['tgl_mulai']) {
                echo json_encode(['status' => 'error', 'msg' => 'Ujian belum dibuka. Waktu mulai: ' . tglIndoFormatted($jadwal['tgl_mulai'], true)]);
                exit;
            }
            if ($now > $jadwal['tgl_selesai']) {
                echo json_encode(['status' => 'error', 'msg' => 'Masa berlaku ujian ini telah berakhir pada ' . tglIndoFormatted($jadwal['tgl_selesai'], true)]);
                exit;
            }

            // Cek apakah peserta sudah memiliki sesi untuk jadwal ini
            $qCekSesi = mysqli_query($conn, "SELECT * FROM cbt_sesi WHERE id_jadwal = $id_jadwal AND no_pendaftaran = '$no_peserta' LIMIT 1");
            $existingSesi = mysqli_fetch_assoc($qCekSesi);

            if ($existingSesi) {
                if ($existingSesi['status_sesi'] === 'selesai') {
                    $qH = mysqli_query($conn, "SELECT * FROM cbt_hasil WHERE id_sesi = " . $existingSesi['id_sesi'] . " LIMIT 1");
                    $hasil = mysqli_fetch_assoc($qH);
                    echo json_encode([
                        'status'   => 'already_finished',
                        'msg'      => 'Anda telah menyelesaikan ujian ini sebelumnya.',
                        'id_sesi'  => (int)$existingSesi['id_sesi'],
                        'hasil'    => $hasil
                    ]);
                    exit;
                }

                // Sesi masih berjalan
                $durasiTotalDetik = ($jadwal['durasi_menit'] * 60) + (int)($existingSesi['tambahan_detik'] ?? 0);
                $waktuMulaiTs = strtotime($existingSesi['waktu_mulai']);
                $detikBerlalu = time() - $waktuMulaiTs;
                $sisaDetik = $durasiTotalDetik - $detikBerlalu;

                if ($sisaDetik <= 0) {
                    $resScore = cbt_calculate_score($conn, $existingSesi['id_sesi']);
                    echo json_encode([
                        'status'   => 'time_expired',
                        'msg'      => 'Waktu pengerjaan ujian telah habis.',
                        'id_sesi'  => (int)$existingSesi['id_sesi'],
                        'hasil'    => $resScore
                    ]);
                    exit;
                }

                mysqli_query($conn, "UPDATE cbt_sesi SET sisa_detik = $sisaDetik WHERE id_sesi = " . $existingSesi['id_sesi']);
                $_SESSION['cbt_session_id'] = (int)$existingSesi['id_sesi'];
                $_SESSION['cbt_no_peserta'] = $no_peserta;
                $_SESSION['cbt_nama_peserta'] = $nama_peserta;

                echo json_encode([
                    'status'     => 'success',
                    'msg'        => 'Melanjutkan sesi ujian yang sedang berlangsung.',
                    'id_sesi'    => (int)$existingSesi['id_sesi'],
                    'sisa_detik' => $sisaDetik,
                    'tipe_ujian' => $jadwal['tipe_ujian']
                ]);
                exit;
            }

            // BUAT SESI BARU
            $durasiDetik = $jadwal['durasi_menit'] * 60;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = cbt_clean_input($conn, $_SERVER['HTTP_USER_AGENT'] ?? '');

            $firstSubtesId = "NULL";
            if ($jadwal['tipe_ujian'] === 'multi_subtes') {
                $subList = cbt_get_jadwal_subtes($conn, $id_jadwal);
                if (!empty($subList)) {
                    $firstSubtesId = (int)$subList[0]['id_subtes'];
                    $durasiDetik = (int)$subList[0]['durasi_menit'] * 60;
                }
            }

            mysqli_begin_transaction($conn);
            $sqlInsSesi = "INSERT INTO cbt_sesi 
                            (id_jadwal, id_subtes_aktif, subtes_ke, no_pendaftaran, nama_peserta, waktu_mulai, waktu_mulai_subtes, sisa_detik, sisa_detik_subtes, status_sesi, ip_address, user_agent)
                           VALUES 
                            ($id_jadwal, $firstSubtesId, 1, '$no_peserta', '$nama_peserta', '$now', '$now', $durasiDetik, $durasiDetik, 'sedang_mengerjakan', '$ip', '$ua')";
            if (!mysqli_query($conn, $sqlInsSesi)) {
                mysqli_rollback($conn);
                echo json_encode(['status' => 'error', 'msg' => 'Gagal membuat sesi ujian.']);
                exit;
            }

            $id_sesi = mysqli_insert_id($conn);

            // ALOKASI SOAL
            if ($jadwal['tipe_ujian'] === 'standar') {
                $whereSoal = "WHERE status = 1";
                if (!empty($jadwal['id_kategori'])) {
                    $whereSoal .= " AND id_kategori = " . (int)$jadwal['id_kategori'];
                }
                $orderSoal = ($jadwal['acak_soal'] == 1) ? "ORDER BY RAND()" : "ORDER BY id_soal ASC";
                $qSoal = mysqli_query($conn, "SELECT id_soal FROM cbt_soal $whereSoal $orderSoal");
                $urutan = 1;
                while ($rS = mysqli_fetch_assoc($qSoal)) {
                    $sId = (int)$rS['id_soal'];
                    if (!mysqli_query($conn, "INSERT INTO cbt_jawaban (id_sesi, id_subtes, id_soal, urutan) VALUES ($id_sesi, NULL, $sId, $urutan)")) {
                        throw new RuntimeException('Gagal mengalokasikan soal.');
                    }
                    $urutan++;
                }
            } else {
                $subList = cbt_get_jadwal_subtes($conn, $id_jadwal);
                $urutan = 1;
                foreach ($subList as $st) {
                    $stId  = (int)$st['id_subtes'];
                    $stKat = (int)$st['id_kategori'];
                    $limit = ((int)$st['jumlah_soal'] > 0) ? "LIMIT " . (int)$st['jumlah_soal'] : "";
                    $orderSoal = ($jadwal['acak_soal'] == 1) ? "ORDER BY RAND()" : "ORDER BY id_soal ASC";
                    $qSoal = mysqli_query($conn, "SELECT id_soal FROM cbt_soal WHERE id_kategori = $stKat AND status = 1 $orderSoal $limit");
                    while ($rS = mysqli_fetch_assoc($qSoal)) {
                        $sId = (int)$rS['id_soal'];
                        if (!mysqli_query($conn, "INSERT INTO cbt_jawaban (id_sesi, id_subtes, id_soal, urutan) VALUES ($id_sesi, $stId, $sId, $urutan)")) {
                            throw new RuntimeException('Gagal mengalokasikan soal subtes.');
                        }
                        $urutan++;
                    }
                }
            }

            mysqli_commit($conn);
            session_regenerate_id(true);
            $_SESSION['cbt_session_id'] = $id_sesi;
            $_SESSION['cbt_no_peserta'] = $no_peserta;
            $_SESSION['cbt_nama_peserta'] = $nama_peserta;
            echo json_encode([
                'status'     => 'success',
                'msg'        => 'Sesi ujian berhasil dimulai!',
                'id_sesi'    => $id_sesi,
                'sisa_detik' => $durasiDetik,
                'tipe_ujian' => $jadwal['tipe_ujian']
            ]);
            break;

        case 'load_soal':
            $id_sesi = (int)($_GET['id_sesi'] ?? ($_POST['id_sesi'] ?? 0));
            require_exam_session_owner($id_sesi);
            if ($id_sesi <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'ID Sesi tidak valid.']);
                exit;
            }

            $qSesi = mysqli_query($conn, "SELECT s.*, j.nama_ujian, j.durasi_menit, j.acak_opsi, j.tipe_ujian 
                                          FROM cbt_sesi s 
                                          JOIN cbt_jadwal j ON s.id_jadwal = j.id_jadwal 
                                          WHERE s.id_sesi = $id_sesi LIMIT 1");
            $sesi = mysqli_fetch_assoc($qSesi);
            if (!$sesi) {
                echo json_encode(['status' => 'error', 'msg' => 'Sesi ujian tidak ditemukan.']);
                exit;
            }
            if ($sesi['status_sesi'] === 'ditangguhkan') {
                echo json_encode(['status'=>'suspended','msg'=>'Sesi ditangguhkan karena batas pelanggaran tercapai. Hubungi pengawas.']);exit;
            }

            $durasiTotalDetik = ($sesi['durasi_menit'] * 60) + (int)($sesi['tambahan_detik'] ?? 0);
            $detikBerlalu = time() - strtotime($sesi['waktu_mulai']);
            $sisaDetik = max(0, $durasiTotalDetik - $detikBerlalu);
            if ($sesi['tipe_ujian'] === 'multi_subtes' && !empty($sesi['id_subtes_aktif'])) {
                $sq=mysqli_query($conn,"SELECT durasi_menit FROM cbt_jadwal_subtes WHERE id_subtes=".(int)$sesi['id_subtes_aktif']." LIMIT 1");
                $sub=$sq?mysqli_fetch_assoc($sq):null;
                $subStart=strtotime($sesi['waktu_mulai_subtes'] ?: $sesi['waktu_mulai']);
                $sisaDetik=max(0,((int)($sub['durasi_menit']??0)*60)-(time()-$subStart));
            }

            if ($sesi['status_sesi'] === 'selesai' || $sisaDetik <= 0) {
                if ($sesi['status_sesi'] !== 'selesai') {
                    cbt_calculate_score($conn, $id_sesi);
                }
                echo json_encode(['status' => 'finished', 'msg' => 'Ujian telah selesai.', 'sisa_detik' => 0]);
                exit;
            }

            $whereSubtes = "";
            if ($sesi['tipe_ujian'] === 'multi_subtes' && !empty($sesi['id_subtes_aktif'])) {
                $whereSubtes = "AND jw.id_subtes = " . (int)$sesi['id_subtes_aktif'];
            }

            $qSoal = "SELECT jw.id_jawaban, jw.id_soal, jw.urutan, jw.jawaban_dipilih, jw.is_ragu, jw.id_subtes,
                             s.pertanyaan, s.gambar, s.opsi_a, s.opsi_b, s.opsi_c, s.opsi_d, s.opsi_e, s.bobot_nilai,
                             js.nama_subtes
                      FROM cbt_jawaban jw
                      JOIN cbt_soal s ON jw.id_soal = s.id_soal
                      LEFT JOIN cbt_jadwal_subtes js ON jw.id_subtes = js.id_subtes
                      WHERE jw.id_sesi = $id_sesi $whereSubtes
                      ORDER BY jw.urutan ASC";
            $resSoal = mysqli_query($conn, $qSoal);
            $listSoal = [];

            while ($r = mysqli_fetch_assoc($resSoal)) {
                $opsi = [
                    'A' => $r['opsi_a'],
                    'B' => $r['opsi_b'],
                    'C' => $r['opsi_c'],
                    'D' => $r['opsi_d']
                ];
                if (!empty($r['opsi_e'])) {
                    $opsi['E'] = $r['opsi_e'];
                }

                $listSoal[] = [
                    'id_jawaban'      => (int)$r['id_jawaban'],
                    'id_soal'         => (int)$r['id_soal'],
                    'urutan'          => (int)$r['urutan'],
                    'pertanyaan'      => $r['pertanyaan'],
                    'gambar'          => !empty($r['gambar']) ? 'uploads/cbt/' . $r['gambar'] : null,
                    'opsi'            => $opsi,
                    'jawaban_dipilih' => $r['jawaban_dipilih'] ?? '',
                    'is_ragu'         => (int)$r['is_ragu'],
                    'bobot'           => (int)$r['bobot_nilai'],
                    'id_subtes'       => $r['id_subtes'],
                    'nama_subtes'     => $r['nama_subtes'] ?? 'Umum'
                ];
            }

            echo json_encode([
                'status'       => 'success',
                'id_sesi'      => $id_sesi,
                'nama_ujian'   => $sesi['nama_ujian'],
                'no_peserta'   => $sesi['no_pendaftaran'],
                'nama_peserta' => $sesi['nama_peserta'],
                'tipe_ujian'   => $sesi['tipe_ujian'],
                'sisa_detik'   => $sisaDetik,
                'soal'         => $listSoal
            ]);
            break;

        case 'simpan_jawaban':
            $id_sesi = (int)($_POST['id_sesi'] ?? 0);
            require_exam_session_owner($id_sesi);
            $id_soal = (int)($_POST['id_soal'] ?? 0);
            $jawaban = strtoupper(trim($_POST['jawaban'] ?? ''));

            if ($id_sesi <= 0 || $id_soal <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Parameter tidak valid.']);
                exit;
            }

            $jawabanVal = in_array($jawaban, ['A', 'B', 'C', 'D', 'E']) ? "'$jawaban'" : "NULL";
            $activeQ=mysqli_query($conn,"SELECT ss.status_sesi,ss.waktu_mulai,ss.tambahan_detik,jj.durasi_menit,jj.tgl_mulai,jj.tgl_selesai FROM cbt_sesi ss JOIN cbt_jadwal jj ON jj.id_jadwal=ss.id_jadwal WHERE ss.id_sesi=$id_sesi LIMIT 1");$active=$activeQ?mysqli_fetch_assoc($activeQ):null;
            $serverNow=time();$allowedSave=$active&&$active['status_sesi']==='sedang_mengerjakan'&&$serverNow>=strtotime($active['tgl_mulai'])&&$serverNow<=strtotime($active['tgl_selesai'])&&($serverNow-strtotime($active['waktu_mulai']))<(((int)$active['durasi_menit']*60)+(int)$active['tambahan_detik']);
            if(!$allowedSave){http_response_code(409);echo json_encode(['status'=>'error','msg'=>'Jawaban ditolak karena sesi tidak aktif atau waktu telah habis.']);exit;}
            $oldQ = mysqli_query($conn, "SELECT jawaban_dipilih FROM cbt_jawaban WHERE id_sesi=$id_sesi AND id_soal=$id_soal LIMIT 1");
            $oldAnswer = $oldQ && ($oldRow=mysqli_fetch_assoc($oldQ)) ? $oldRow['jawaban_dipilih'] : null;
            $sql = "UPDATE cbt_jawaban SET jawaban_dipilih=$jawabanVal WHERE id_sesi=$id_sesi AND id_soal=$id_soal";
            mysqli_query($conn, $sql);
            if (mysqli_affected_rows($conn) > 0) {
                $oldEsc = $oldAnswer === null ? 'NULL' : "'" . mysqli_real_escape_string($conn,$oldAnswer) . "'";
                $newEsc = $jawabanVal;
                $ipEsc = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR'] ?? '');
                mysqli_query($conn, "INSERT INTO cbt_jawaban_log(id_sesi,id_soal,jawaban_lama,jawaban_baru,ip_address) VALUES($id_sesi,$id_soal,$oldEsc,$newEsc,'$ipEsc')");
            }

            echo json_encode(['status' => 'success', 'jawaban' => $jawaban, 'saved'=>true]);
            break;

        case 'set_ragu':
            $id_sesi = (int)($_POST['id_sesi'] ?? 0);
            require_exam_session_owner($id_sesi);
            $id_soal = (int)($_POST['id_soal'] ?? 0);
            $is_ragu = !empty($_POST['is_ragu']) ? 1 : 0;

            if ($id_sesi <= 0 || $id_soal <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Parameter tidak valid.']);
                exit;
            }

            $statusQ=mysqli_query($conn,"SELECT status_sesi FROM cbt_sesi WHERE id_sesi=$id_sesi LIMIT 1");$statusRow=$statusQ?mysqli_fetch_assoc($statusQ):null;
            if(!$statusRow||$statusRow['status_sesi']!=='sedang_mengerjakan'){http_response_code(409);echo json_encode(['status'=>'error','msg'=>'Sesi tidak aktif.']);exit;}
            $sql = "UPDATE cbt_jawaban SET is_ragu=$is_ragu WHERE id_sesi=$id_sesi AND id_soal=$id_soal";
            mysqli_query($conn, $sql);

            echo json_encode(['status' => 'success', 'is_ragu' => $is_ragu]);
            break;

        case 'finish':
            $id_sesi = (int)($_POST['id_sesi'] ?? 0);
            require_exam_session_owner($id_sesi);
            if ($id_sesi <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'ID Sesi tidak valid.']);
                exit;
            }

            $finishCheck=mysqli_query($conn,"SELECT s.id_jadwal,s.subtes_ke,j.tipe_ujian FROM cbt_sesi s JOIN cbt_jadwal j ON j.id_jadwal=s.id_jadwal WHERE s.id_sesi=$id_sesi LIMIT 1");
            $finishRow=$finishCheck?mysqli_fetch_assoc($finishCheck):null;
            if($finishRow&&$finishRow['tipe_ujian']==='multi_subtes'){
                $later=mysqli_query($conn,"SELECT 1 FROM cbt_jadwal_subtes WHERE id_jadwal=".(int)$finishRow['id_jadwal']." AND urutan>".(int)$finishRow['subtes_ke']." LIMIT 1");
                if($later&&mysqli_num_rows($later)){echo json_encode(['status'=>'error','msg'=>'Masih ada subtes berikutnya yang harus diselesaikan.']);exit;}
            }
            $result = cbt_calculate_score($conn, $id_sesi);
            if ($result !== false) {
                echo json_encode([
                    'status' => 'success',
                    'msg'    => 'Ujian berhasil diselesaikan!',
                    'data'   => $result
                ]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal menghitung nilai ujian.']);
            }
            break;

        case 'next_subtes':
            $id_sesi=(int)($_POST['id_sesi']??0); require_exam_session_owner($id_sesi);
            $sq=mysqli_query($conn,"SELECT s.*,j.tipe_ujian FROM cbt_sesi s JOIN cbt_jadwal j ON j.id_jadwal=s.id_jadwal WHERE s.id_sesi=$id_sesi LIMIT 1");
            $ss=$sq?mysqli_fetch_assoc($sq):null;
            if(!$ss||$ss['status_sesi']!=='sedang_mengerjakan'){echo json_encode(['status'=>'error','msg'=>'Sesi tidak aktif.']);exit;}
            $nextQ=mysqli_query($conn,"SELECT * FROM cbt_jadwal_subtes WHERE id_jadwal=".(int)$ss['id_jadwal']." AND urutan>".(int)$ss['subtes_ke']." ORDER BY urutan LIMIT 1");
            $next=$nextQ?mysqli_fetch_assoc($nextQ):null;
            if($next){$nid=(int)$next['id_subtes'];$order=(int)$next['urutan'];$seconds=(int)$next['durasi_menit']*60;$now=date('Y-m-d H:i:s');mysqli_query($conn,"UPDATE cbt_sesi SET id_subtes_aktif=$nid,subtes_ke=$order,waktu_mulai_subtes='$now',sisa_detik_subtes=$seconds WHERE id_sesi=$id_sesi");echo json_encode(['status'=>'next','msg'=>'Berpindah ke '.$next['nama_subtes'],'sisa_detik'=>$seconds]);}
            else {$result=cbt_calculate_score($conn,$id_sesi);echo json_encode(['status'=>'finished','data'=>$result]);}
            break;

        case 'ping':
            $id_sesi    = (int)($_POST['id_sesi'] ?? 0);
            require_exam_session_owner($id_sesi);
            $qTimer = mysqli_query($conn, "SELECT s.waktu_mulai,s.tambahan_detik,j.durasi_menit FROM cbt_sesi s JOIN cbt_jadwal j ON j.id_jadwal=s.id_jadwal WHERE s.id_sesi=$id_sesi LIMIT 1");
            $timer = mysqli_fetch_assoc($qTimer);
            $sisa_detik = $timer ? max(0, ((int)$timer['durasi_menit'] * 60) + (int)$timer['tambahan_detik'] - (time() - strtotime($timer['waktu_mulai']))) : 0;
            mysqli_query($conn, "UPDATE cbt_sesi SET sisa_detik = $sisa_detik WHERE id_sesi = $id_sesi");
            echo json_encode(['status' => 'pong', 'timestamp' => time()]);
            break;

        case 'log_violation':
            $id_sesi=(int)($_POST['id_sesi']??0);require_exam_session_owner($id_sesi);
            $liveQ=mysqli_query($conn,"SELECT status_sesi FROM cbt_sesi WHERE id_sesi=$id_sesi LIMIT 1");$live=$liveQ?mysqli_fetch_assoc($liveQ):null;if(!$live||$live['status_sesi']!=='sedang_mengerjakan'){echo json_encode(['status'=>'success','ignored'=>true]);exit;}
            $allowed=['tab_hidden','fullscreen_exit','copy','paste','context_menu','print_screen','camera_denied','camera_unavailable','screen_denied','screen_share_stopped'];
            $type=(string)($_POST['type']??'');if(!in_array($type,$allowed,true)){echo json_encode(['status'=>'error','msg'=>'Jenis pelanggaran tidak valid.']);exit;}
            $detail=cbt_clean_input($conn,mb_substr((string)($_POST['detail']??''),0,255));$ip=cbt_clean_input($conn,$_SERVER['REMOTE_ADDR']??'');
            $recent=mysqli_query($conn,"SELECT 1 FROM cbt_pelanggaran WHERE id_sesi=$id_sesi AND jenis='".cbt_clean_input($conn,$type)."' AND created_at>=DATE_SUB(NOW(),INTERVAL 3 SECOND) LIMIT 1");
            if(!$recent||mysqli_num_rows($recent)===0)mysqli_query($conn,"INSERT INTO cbt_pelanggaran(id_sesi,jenis,detail,ip_address) VALUES($id_sesi,'".cbt_clean_input($conn,$type)."','$detail','$ip')");
            $countQ=mysqli_query($conn,"SELECT COUNT(*) total FROM cbt_pelanggaran WHERE id_sesi=$id_sesi AND resolved_at IS NULL");$count=$countQ?(int)mysqli_fetch_assoc($countQ)['total']:0;
            $suspended=false;if($count>=4){mysqli_query($conn,"UPDATE cbt_sesi SET status_sesi='ditangguhkan',alasan_tindakan='Batas maksimal 4 pelanggaran tercapai' WHERE id_sesi=$id_sesi AND status_sesi='sedang_mengerjakan'");$suspended=mysqli_affected_rows($conn)>0;}
            echo json_encode(['status'=>'success','count'=>$count,'limit'=>4,'suspended'=>$suspended]);break;

        case 'upload_proctor_frame':
            $id_sesi=(int)($_POST['id_sesi']??0);require_exam_session_owner($id_sesi);
            $type=(string)($_POST['media_type']??'');$data=(string)($_POST['image_data']??'');
            if(!in_array($type,['camera','screen'],true)||!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#',$data,$match)){http_response_code(422);echo json_encode(['status'=>'error','msg'=>'Frame tidak valid.']);exit;}
            $binary=base64_decode($match[1],true);if($binary===false||strlen($binary)>1500000){http_response_code(422);echo json_encode(['status'=>'error','msg'=>'Frame terlalu besar.']);exit;}
            $relative='uploads/proctor/'.$id_sesi.'/'.$type.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.jpg';$absolute=__DIR__.'/../../../'.$relative;$dir=dirname($absolute);if(!is_dir($dir))mkdir($dir,0755,true);
            if(file_put_contents($absolute,$binary)===false){http_response_code(500);echo json_encode(['status'=>'error','msg'=>'Gagal menyimpan frame.']);exit;}
            $pathEsc=mysqli_real_escape_string($conn,$relative);mysqli_query($conn,"INSERT INTO cbt_proctor_media(id_sesi,media_type,file_path) VALUES($id_sesi,'$type','$pathEsc')");echo json_encode(['status'=>'success']);break;

        default:
            echo json_encode(['status' => 'error', 'msg' => 'Aksi tidak valid']);
            break;
    }
} catch (\Throwable $th) {
    @mysqli_rollback($conn);
    error_log('CBT exam API: ' . $th->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => 'Terjadi kesalahan internal.']);
}
