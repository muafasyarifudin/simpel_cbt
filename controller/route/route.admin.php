<?php
/**
 * simpel_cbt - Controller Route Admin Pusat
 * Arsitektur Terinspirasi spmb_v3
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../model/helper/auth.helper.php';

// Pastikan sesi admin valid
check_admin_login();

// Route ke halaman pusat
include __DIR__ . '/../../view/public/content/page/page.pusat.php';