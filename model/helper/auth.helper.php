<?php
/**
 * simpel_cbt - Auth Helper Functions with Multi-Role RBAC Support
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('is_admin_logged_in')) {
    function is_admin_logged_in() {
        return !empty($_SESSION['simpel_cbt_admin_id']);
    }
}

if (!function_exists('get_logged_admin')) {
    function get_logged_admin() {
        if (!is_admin_logged_in()) {
            return null;
        }
        return [
            'id'           => (int)($_SESSION['simpel_cbt_admin_id'] ?? 0),
            'username'     => $_SESSION['simpel_cbt_admin_user'] ?? '',
            'nama'         => $_SESSION['simpel_cbt_admin_nama'] ?? 'Petugas',
            'nama_lengkap' => $_SESSION['simpel_cbt_admin_nama'] ?? 'Petugas',
            'role'         => $_SESSION['simpel_cbt_admin_role'] ?? 'admin'
        ];
    }
}

if (!function_exists('check_admin_login')) {
    function check_admin_login() {
        if (!is_admin_logged_in()) {
            header("Location: index.php?m=login-pusat");
            exit;
        }
        // Baik admin maupun pengawas diizinkan masuk ke dashboard terpadu.
        // Tampilan menu sidebar otomatis menyesuaikan role di page.pusat.php.
        $user = get_logged_admin();
        if (!in_array($user['role'], ['admin', 'pengawas'])) {
            header("Location: index.php?m=login-pusat");
            exit;
        }
    }
}

if (!function_exists('require_api_login')) {
    function require_api_login(array $allowedRoles = ['admin', 'pengawas']) {
        $user = get_logged_admin();
        if (!$user || !in_array($user['role'], $allowedRoles, true)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'msg' => 'Akses ditolak.']);
            exit;
        }
        return $user;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['simpel_cbt_csrf'])) {
            $_SESSION['simpel_cbt_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['simpel_cbt_csrf'];
    }
}

if (!function_exists('require_csrf')) {
    function require_csrf() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
        if (empty($provided) || !hash_equals(csrf_token(), (string)$provided)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'msg' => 'Sesi keamanan tidak valid. Muat ulang halaman.']);
            exit;
        }
    }
}

if (!function_exists('require_exam_session_owner')) {
    function require_exam_session_owner($idSesi) {
        $owned = (int)($_SESSION['cbt_session_id'] ?? 0);
        if ($idSesi <= 0 || $owned !== (int)$idSesi) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'msg' => 'Sesi ujian tidak valid.']);
            exit;
        }
    }
}

if (!function_exists('exam_result_signature')) {
    function exam_result_signature($idSesi, $noPeserta) {
        return hash_hmac('sha256', (int)$idSesi . '|' . (string)$noPeserta, APP_SECRET);
    }
}

if (!function_exists('audit_log')) {
    function audit_log($conn, $aksi, $entitas = null, $idEntitas = null, array $detail = []) {
        $user = get_logged_admin();
        $id = (int)($user['id'] ?? 0);
        $username = mysqli_real_escape_string($conn, (string)($user['username'] ?? 'system'));
        $role = mysqli_real_escape_string($conn, (string)($user['role'] ?? 'system'));
        $aksiEsc = mysqli_real_escape_string($conn, (string)$aksi);
        $entitasEsc = mysqli_real_escape_string($conn, (string)$entitas);
        $idEsc = mysqli_real_escape_string($conn, (string)$idEntitas);
        $json = mysqli_real_escape_string($conn, json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $ip = mysqli_real_escape_string($conn, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        mysqli_query($conn, "INSERT INTO cbt_audit_log(id_admin,username,role,aksi,entitas,id_entitas,detail_json,ip_address)
            VALUES(" . ($id ?: 'NULL') . ",'$username','$role','$aksiEsc','$entitasEsc','$idEsc','$json','$ip')");
    }
}

if (!function_exists('check_pusat_login')) {
    function check_pusat_login() {
        check_admin_login();
    }
}

if (!function_exists('check_pengawas_login')) {
    function check_pengawas_login() {
        check_admin_login();
    }
}

if (!function_exists('admin_logout')) {
    function admin_logout($redirectUrl = 'index.php?m=login-pusat') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['simpel_cbt_admin_id']);
        unset($_SESSION['simpel_cbt_admin_user']);
        unset($_SESSION['simpel_cbt_admin_nama']);
        unset($_SESSION['simpel_cbt_admin_role']);
        unset($_SESSION['simpel_cbt_csrf']);
        // Bersihkan seluruh session simpel_cbt
        session_destroy();
        
        header("Location: " . $redirectUrl);
        exit;
    }
}
