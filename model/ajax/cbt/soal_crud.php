<?php
/**
 * simpel_cbt - Soal CRUD Handler
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
            $id_kategori = (int)($_GET['id_kategori'] ?? 0);
            $search      = cbt_clean_input($conn, $_GET['search'] ?? '');

            $where = "WHERE 1=1";
            if ($id_kategori > 0) {
                $where .= " AND s.id_kategori = $id_kategori";
            }
            if (!empty($search)) {
                $where .= " AND (s.pertanyaan LIKE '%$search%' OR k.nama_kategori LIKE '%$search%')";
            }

            $q = "SELECT s.*, k.nama_kategori, k.kode_kategori 
                  FROM cbt_soal s 
                  JOIN cbt_kategori k ON s.id_kategori = k.id_kategori 
                  $where 
                  ORDER BY s.id_soal DESC";
            $res = mysqli_query($conn, $q);
            $data = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $row['pertanyaan_preview'] = mb_strimwidth(strip_tags($row['pertanyaan']), 0, 100, '...');
                $data[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'detail':
            $id = (int)($_GET['id_soal'] ?? 0);
            $q = "SELECT s.*, k.nama_kategori 
                  FROM cbt_soal s 
                  JOIN cbt_kategori k ON s.id_kategori = k.id_kategori 
                  WHERE s.id_soal = $id LIMIT 1";
            $res = mysqli_query($conn, $q);
            $row = mysqli_fetch_assoc($res);
            if ($row) {
                echo json_encode(['status' => 'success', 'data' => $row]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Soal tidak ditemukan.']);
            }
            break;

        case 'save':
            $id           = (int)($_POST['id_soal'] ?? 0);
            $id_kategori  = (int)($_POST['id_kategori'] ?? 0);
            $pertanyaan   = trim($_POST['pertanyaan'] ?? '');
            $opsi_a       = trim($_POST['opsi_a'] ?? '');
            $opsi_b       = trim($_POST['opsi_b'] ?? '');
            $opsi_c       = trim($_POST['opsi_c'] ?? '');
            $opsi_d       = trim($_POST['opsi_d'] ?? '');
            $opsi_e       = trim($_POST['opsi_e'] ?? '');
            $kunci        = strtoupper(trim($_POST['kunci_jawaban'] ?? ''));
            $bobot        = (int)($_POST['bobot_nilai'] ?? 1);
            $pembahasan   = trim($_POST['pembahasan'] ?? '');
            $status       = isset($_POST['status']) ? (int)$_POST['status'] : 1;

            if ($id_kategori <= 0 || empty($pertanyaan) || empty($opsi_a) || empty($opsi_b) || empty($opsi_c) || empty($opsi_d) || empty($kunci)) {
                echo json_encode(['status' => 'error', 'msg' => 'Mohon lengkapi Kategori, Pertanyaan, Opsi A-D, dan Kunci Jawaban!']);
                exit;
            }

            // Handle upload gambar jika ada
            $gambarName = null;
            if (!empty($_FILES['gambar_soal']['name'])) {
                $file = $_FILES['gambar_soal'];
                $mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
                $mime = is_uploaded_file($file['tmp_name']) ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && ($file['size'] ?? 0) <= 5 * 1024 * 1024 && isset($mimeMap[$mime])) {
                    $ext = $mimeMap[$mime];
                    $uploadDir = __DIR__ . '/../../../uploads/cbt/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $newFilename = 'cbt_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFilename)) {
                        $gambarName = $newFilename;
                    }
                } else {
                    echo json_encode(['status' => 'error', 'msg' => 'Gambar harus JPG, PNG, WEBP, atau GIF dan maksimal 5 MB.']);
                    exit;
                }
            }

            // Escape input
            $pertanyaanEsc = mysqli_real_escape_string($conn, $pertanyaan);
            $opsiAEsc      = mysqli_real_escape_string($conn, $opsi_a);
            $opsiBEsc      = mysqli_real_escape_string($conn, $opsi_b);
            $opsiCEsc      = mysqli_real_escape_string($conn, $opsi_c);
            $opsiDEsc      = mysqli_real_escape_string($conn, $opsi_d);
            $opsiEEsc      = mysqli_real_escape_string($conn, $opsi_e);
            $kunciEsc      = mysqli_real_escape_string($conn, $kunci);
            $pembahasanEsc = mysqli_real_escape_string($conn, $pembahasan);

            if ($id > 0) {
                // Update
                $sqlGambar = $gambarName ? ", gambar = '$gambarName'" : "";
                if ($gambarName) {
                    $oldImageQuery = mysqli_query($conn, "SELECT gambar FROM cbt_soal WHERE id_soal = $id LIMIT 1");
                    $oldImage = $oldImageQuery ? mysqli_fetch_assoc($oldImageQuery) : null;
                    if (!empty($oldImage['gambar'])) {
                        @unlink(__DIR__ . '/../../../uploads/cbt/' . basename($oldImage['gambar']));
                    }
                }
                // Jika diminta hapus gambar
                if (!empty($_POST['hapus_gambar']) && $_POST['hapus_gambar'] == 1 && !$gambarName) {
                    $sqlGambar = ", gambar = NULL";
                }

                $sql = "UPDATE cbt_soal SET 
                            id_kategori = $id_kategori,
                            pertanyaan = '$pertanyaanEsc',
                            opsi_a = '$opsiAEsc',
                            opsi_b = '$opsiBEsc',
                            opsi_c = '$opsiCEsc',
                            opsi_d = '$opsiDEsc',
                            opsi_e = '$opsiEEsc',
                            kunci_jawaban = '$kunciEsc',
                            bobot_nilai = $bobot,
                            pembahasan = '$pembahasanEsc',
                            status = $status
                            $sqlGambar
                        WHERE id_soal = $id";
            } else {
                // Insert
                $gambarVal = $gambarName ? "'$gambarName'" : "NULL";
                $sql = "INSERT INTO cbt_soal 
                            (id_kategori, pertanyaan, gambar, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci_jawaban, bobot_nilai, pembahasan, status)
                        VALUES 
                            ($id_kategori, '$pertanyaanEsc', $gambarVal, '$opsiAEsc', '$opsiBEsc', '$opsiCEsc', '$opsiDEsc', '$opsiEEsc', '$kunciEsc', $bobot, '$pembahasanEsc', $status)";
            }

            if (mysqli_query($conn, $sql)) {
                echo json_encode(['status' => 'success', 'msg' => 'Soal berhasil disimpan!']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal menyimpan: ' . mysqli_error($conn)]);
            }
            break;

        case 'delete':
            $id = (int)($_POST['id_soal'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'ID Soal tidak valid.']);
                exit;
            }

            // Hapus file gambar jika ada
            $qG = mysqli_query($conn, "SELECT gambar FROM cbt_soal WHERE id_soal = $id LIMIT 1");
            if ($rG = mysqli_fetch_assoc($qG)) {
                if (!empty($rG['gambar'])) {
                    @unlink(__DIR__ . '/../../../uploads/cbt/' . basename($rG['gambar']));
                }
            }

            $sql = "DELETE FROM cbt_soal WHERE id_soal = $id";
            if (mysqli_query($conn, $sql)) {
                echo json_encode(['status' => 'success', 'msg' => 'Soal berhasil dihapus!']);
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
