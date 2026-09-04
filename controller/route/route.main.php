<?php
/**
 * simpel_cbt - Controller Route Utama (Dispatcher)
 * Arsitektur Terinspirasi spmb_v3
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$module = $_GET['m'] ?? 'home';

switch ($module) {
    case 'admin':
    case 'pusat':
        include __DIR__ . '/route.admin.php';
        break;

    case 'pengawas':
        include __DIR__ . '/route.admin.php';
        break;
    case 'ignore_pengawas':
        include __DIR__ . '/route.pengawas.php';
        break;

    case 'login':
    case 'home':
        include __DIR__ . '/../../view/private/page/page.login.peserta.php';
        break;

    case 'login-pusat':
        include __DIR__ . '/../../view/private/page/page.login.pusat.php';
        break;

    case 'login-pengawas':
        header('Location: index.php?m=login-pusat');
        exit;
    case 'ignore_login_pengawas':
        include __DIR__ . '/../../view/private/page/page.login.pengawas.php';
        break;

    case 'exam':
    case 'cbtExam':
        include __DIR__ . '/../../view/private/page/page.cbt.exam.php';
        break;

    case 'print':
    case 'cbtPrint':
        include __DIR__ . '/../../view/private/page/page.cbt.print.php';
        break;

    case 'logout':
        require_once __DIR__ . '/../../model/helper/auth.helper.php';
        admin_logout('index.php?m=login-pusat');
        break;

    case 'logout-pengawas':
        require_once __DIR__ . '/../../model/helper/auth.helper.php';
        admin_logout('index.php?m=login-pengawas');
        break;

    default:
        http_response_code(404);
        echo "<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>404 - Halaman Modul Tidak Ditemukan</h2><p><a href='index.php'>Kembali ke Beranda</a></p></div>";
        break;
}