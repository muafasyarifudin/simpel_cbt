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

            $verify = auth_bridge_verify_user($no_peserta);
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
                $durasiTotalDetik = $jadwal['durasi_menit'] * 60;
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
                            (id_jadwal, id_subtes_aktif, subtes_ke, no_pendaftaran, nama_peserta, waktu_mulai, sisa_detik, sisa_detik_subtes, status_sesi, ip_address, user_agent)
                           VALUES 
                            ($id_jadwal, $firstSubtesId, 1, '$no_peserta', '$nama_peserta', '$now', $durasiDetik, $durasiDetik, 'sedang_mengerjakan', '$ip', '$ua')";
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

            $durasiTotalDetik = $sesi['durasi_menit'] * 60;
            $detikBerlalu = time() - strtotime($sesi['waktu_mulai']);
            $sisaDetik = max(0, $durasiTotalDetik - $detikBerlalu);

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
            $sql = "UPDATE cbt_jawaban jw
                    JOIN cbt_sesi ss ON ss.id_sesi = jw.id_sesi
                    JOIN cbt_jadwal jj ON jj.id_jadwal = ss.id_jadwal
                    SET jw.jawaban_dipilih = $jawabanVal
                    WHERE jw.id_sesi = $id_sesi AND jw.id_soal = $id_soal
                      AND ss.status_sesi = 'sedang_mengerjakan'
                      AND NOW() BETWEEN jj.tgl_mulai AND jj.tgl_selesai
                      AND TIMESTAMPDIFF(SECOND, ss.waktu_mulai, NOW()) < jj.durasi_menit * 60";
            mysqli_query($conn, $sql);

            echo json_encode(['status' => 'success', 'jawaban' => $jawaban]);
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

            $sql = "UPDATE cbt_jawaban jw
                    JOIN cbt_sesi ss ON ss.id_sesi = jw.id_sesi
                    JOIN cbt_jadwal jj ON jj.id_jadwal = ss.id_jadwal
                    SET jw.is_ragu = $is_ragu
                    WHERE jw.id_sesi = $id_sesi AND jw.id_soal = $id_soal
                      AND ss.status_sesi = 'sedang_mengerjakan'
                      AND NOW() BETWEEN jj.tgl_mulai AND jj.tgl_selesai
                      AND TIMESTAMPDIFF(SECOND, ss.waktu_mulai, NOW()) < jj.durasi_menit * 60";
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

        case 'ping':
            $id_sesi    = (int)($_POST['id_sesi'] ?? 0);
            require_exam_session_owner($id_sesi);
            $qTimer = mysqli_query($conn, "SELECT s.waktu_mulai, j.durasi_menit FROM cbt_sesi s JOIN cbt_jadwal j ON j.id_jadwal=s.id_jadwal WHERE s.id_sesi=$id_sesi LIMIT 1");
            $timer = mysqli_fetch_assoc($qTimer);
            $sisa_detik = $timer ? max(0, ((int)$timer['durasi_menit'] * 60) - (time() - strtotime($timer['waktu_mulai']))) : 0;
            mysqli_query($conn, "UPDATE cbt_sesi SET sisa_detik = $sisa_detik WHERE id_sesi = $id_sesi");
            echo json_encode(['status' => 'pong', 'timestamp' => time()]);
            break;

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
