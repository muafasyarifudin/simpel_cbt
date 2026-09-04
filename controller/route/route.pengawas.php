<?php
/**
 * simpel_cbt - Controller Route Pengawas Ruangan
 * Kini Menggunakan Layout Terpadu Sidebar Standar dengan Pembatasan Hak Akses
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../model/helper/auth.helper.php';

// Pastikan sesi pengawas/admin valid
check_pengawas_login();

// Route ke halaman template terpadu dengan sidebar
include __DIR__ . '/../../view/public/content/page/page.pusat.php';