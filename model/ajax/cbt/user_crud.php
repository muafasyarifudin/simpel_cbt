<?php
/**
 * simpel_cbt - User Management CRUD API (Admin & Pengawas)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.conn.php';
require_once __DIR__ . '/../../helper/auth.helper.php';

require_api_login(['admin']);
require_csrf();

$currentAdmin = get_logged_admin();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    // 1. LIST USERS
    case 'list':
        $query = mysqli_query($conn, "SELECT id_admin, username, nama_lengkap, role, created_at FROM cbt_admin ORDER BY id_admin ASC");
        $users = [];
        $cntAdmin = 0;
        $cntPengawas = 0;

        while ($row = mysqli_fetch_assoc($query)) {
            $users[] = [
                'id_admin'     => (int)$row['id_admin'],
                'username'     => $row['username'],
                'nama_lengkap' => $row['nama_lengkap'],
                'role'         => $row['role'],
                'created_at'   => $row['created_at'],
                'is_current'   => ($row['id_admin'] == $currentAdmin['id'])
            ];
            if ($row['role'] === 'admin') $cntAdmin++;
            else $cntPengawas++;
        }

        echo json_encode([
            'status' => 'success',
            'data'   => $users,
            'summary' => [
                'total'          => count($users),
                'total_admin'    => $cntAdmin,
                'total_pengawas' => $cntPengawas
            ]
        ]);
        break;

    // 2. SAVE (CREATE / UPDATE)
    case 'save':
        $idAdmin     = (int)($_POST['id_admin'] ?? 0);
        $username    = strtolower(trim($_POST['username'] ?? ''));
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $role        = trim($_POST['role'] ?? 'pengawas');
        $password    = trim($_POST['password'] ?? '');

        if (empty($username) || empty($namaLengkap)) {
            echo json_encode(['status' => 'error', 'msg' => 'Username dan Nama Lengkap wajib diisi!']);
            exit;
        }

        // Sanitasi role
        if (!in_array($role, ['admin', 'pengawas'])) {
            $role = 'pengawas';
        }

        $uEsc = mysqli_real_escape_string($conn, $username);
        $nEsc = mysqli_real_escape_string($conn, $namaLengkap);
        $rEsc = mysqli_real_escape_string($conn, $role);

        // Cek username unik
        $checkQ = mysqli_query($conn, "SELECT id_admin FROM cbt_admin WHERE username = '$uEsc' AND id_admin != $idAdmin LIMIT 1");
        if (mysqli_num_rows($checkQ) > 0) {
            echo json_encode(['status' => 'error', 'msg' => "Username '{$username}' sudah digunakan pengguna lain!"]);
            exit;
        }

        if ($idAdmin > 0) {
            // Update
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    echo json_encode(['status' => 'error', 'msg' => 'Password minimal 8 karakter!']);
                    exit;
                }
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE cbt_admin SET username = '$uEsc', nama_lengkap = '$nEsc', role = '$rEsc', password = '$passHash' WHERE id_admin = $idAdmin";
            } else {
                $sql = "UPDATE cbt_admin SET username = '$uEsc', nama_lengkap = '$nEsc', role = '$rEsc' WHERE id_admin = $idAdmin";
            }
            $exec = mysqli_query($conn, $sql);
            if ($exec) {
                audit_log($conn, 'ubah_pengguna', 'admin', $idAdmin, ['username' => $username, 'role' => $role]);
                echo json_encode(['status' => 'success', 'msg' => "Data pengguna '{$namaLengkap}' berhasil diperbarui."]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal memperbarui pengguna: ' . mysqli_error($conn)]);
            }
        } else {
            // Create New
            if (empty($password)) {
                echo json_encode(['status' => 'error', 'msg' => 'Password wajib diisi untuk pengguna baru!']);
                exit;
            }
            if (strlen($password) < 8) {
                echo json_encode(['status' => 'error', 'msg' => 'Password minimal 8 karakter!']);
                exit;
            }
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO cbt_admin (username, password, nama_lengkap, role) VALUES ('$uEsc', '$passHash', '$nEsc', '$rEsc')";
            $exec = mysqli_query($conn, $sql);
            if ($exec) {
                audit_log($conn, 'buat_pengguna', 'admin', mysqli_insert_id($conn), ['username' => $username, 'role' => $role]);
                echo json_encode(['status' => 'success', 'msg' => "Pengguna baru '{$namaLengkap}' ({$role}) berhasil ditambahkan."]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Gagal menambahkan pengguna: ' . mysqli_error($conn)]);
            }
        }
        break;

    // 3. CHANGE PASSWORD
    case 'change_password':
        $idAdmin = (int)($_POST['id_admin'] ?? 0);
        $newPass = trim($_POST['new_password'] ?? '');

        if ($idAdmin <= 0 || empty($newPass)) {
            echo json_encode(['status' => 'error', 'msg' => 'ID Pengguna dan Password Baru wajib diisi!']);
            exit;
        }

        if (strlen($newPass) < 8) {
            echo json_encode(['status' => 'error', 'msg' => 'Password baru minimal 8 karakter!']);
            exit;
        }

        $passHash = password_hash($newPass, PASSWORD_DEFAULT);
        $exec = mysqli_query($conn, "UPDATE cbt_admin SET password = '$passHash' WHERE id_admin = $idAdmin");
        if ($exec) {
            audit_log($conn, 'ubah_password', 'admin', $idAdmin);
            echo json_encode(['status' => 'success', 'msg' => 'Kata sandi pengguna berhasil diperbarui.']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal memperbarui kata sandi: ' . mysqli_error($conn)]);
        }
        break;

    // 4. DELETE USER
    case 'delete':
        $idAdmin = (int)($_POST['id_admin'] ?? 0);
        if ($idAdmin <= 0) {
            echo json_encode(['status' => 'error', 'msg' => 'ID Pengguna tidak valid!']);
            exit;
        }

        // Proteksi: tidak boleh menghapus diri sendiri
        if ($idAdmin == $currentAdmin['id']) {
            echo json_encode(['status' => 'error', 'msg' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!']);
            exit;
        }

        // Proteksi: jangan hapus akun default id 1
        if ($idAdmin == 1) {
            echo json_encode(['status' => 'error', 'msg' => 'Akun Administrator Utama sistem tidak dapat dihapus!']);
            exit;
        }

        $exec = mysqli_query($conn, "DELETE FROM cbt_admin WHERE id_admin = $idAdmin");
        if ($exec) {
            audit_log($conn, 'hapus_pengguna', 'admin', $idAdmin);
            echo json_encode(['status' => 'success', 'msg' => 'Pengguna berhasil dihapus dari sistem.']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal menghapus pengguna: ' . mysqli_error($conn)]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'msg' => 'Aksi tidak dikenali.']);
        break;
}
