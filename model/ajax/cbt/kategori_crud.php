<?php
/**
 * simpel_cbt - Kategori CRUD Handler
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
            $q = "SELECT k.*, COUNT(s.id_soal) AS total_soal 
                  FROM cbt_kategori k 
                  LEFT JOIN cbt_soal s ON k.id_kategori = s.id_kategori AND s.status = 1 
                  GROUP BY k.id_kategori 
                  ORDER BY k.id_kategori DESC";
            $res = mysqli_query($conn, $q);
            $data = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'detail':
            $id = (int)($_GET['id_kategori'] ?? 0);
            $q = "SELECT * FROM cbt_kategori WHERE id_kategori = $id LIMIT 1";
            $res = mysqli_query($conn, $q);
            $row = mysqli_fetch_assoc($res);
            if ($row) {
                echo json_encode(['status' => 'success', 'data' => $row]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Kategori tidak ditemukan.']);
            }
            break;

        case 'save':
            $id        = (int)($_POST['id_kategori'] ?? 0);
            $nama      = cbt_clean_input($conn, $_POST['nama_kategori'] ?? '');
            $kode      = strtoupper(cbt_clean_input($conn, $_POST['kode_kategori'] ?? ''));
            $deskripsi = cbt_clean_input($conn, $_POST['deskripsi'] ?? '');
            $status    = isset($_POST['status']) ? (int)$_POST['status'] : 1;

            if (empty($nama) || empty($kode)) {
                echo json_encode(['status' => 'error', 'msg' => 'Nama dan Kode Kategori wajib diisi!']);
                exit;
            }

            // Cek keunikan kode
            $checkKode = mysqli_query($conn, "SELECT id_kategori FROM cbt_kategori WHERE kode_kategori = '$kode' AND id_kategori != $id LIMIT 1");
            if (mysqli_num_rows($checkKode) > 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Kode kategori sudah digunakan oleh kategori lain!']);
                exit;
            }

            if ($id > 0) {
                $sql = "UPDATE cbt_kategori SET 
                            nama_kategori = '$nama', 
                            kode_kategori = '$kode', 
                            deskripsi = '$deskripsi', 
                            status = $status 
                        WHERE id_kategori = $id";
            } else {
                $sql = "INSERT INTO cbt_kategori (nama_kategori, kode_kategori, deskripsi, status) 
                        VALUES ('$nama', '$kode', '$deskripsi', $status)";
            }

            if (mysqli_query($conn, $sql)) {
                echo json_encode(['status' => 'success', 'msg' => 'Kategori berhasil disimpan!']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal menyimpan: ' . mysqli_error($conn)]);
            }
            break;

        case 'delete':
            $id = (int)($_POST['id_kategori'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['status' => 'error', 'msg' => 'ID Kategori tidak valid.']);
                exit;
            }

            // Cek apakah ada soal yang terkait
            $checkSoal = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM cbt_soal WHERE id_kategori = $id");
            $rSoal = mysqli_fetch_assoc($checkSoal);
            if ($rSoal['cnt'] > 0) {
                echo json_encode(['status' => 'error', 'msg' => 'Kategori tidak dapat dihapus karena masih memiliki ' . $rSoal['cnt'] . ' butir soal. Hapus atau pindahkan soal terlebih dahulu!']);
                exit;
            }

            $sql = "DELETE FROM cbt_kategori WHERE id_kategori = $id";
            if (mysqli_query($conn, $sql)) {
                echo json_encode(['status' => 'success', 'msg' => 'Kategori berhasil dihapus!']);
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
