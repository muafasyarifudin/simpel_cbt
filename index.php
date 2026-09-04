<?php
/**
 * simpel_cbt - Central Front Controller & Dispatcher
 * Arsitektur dan Pola Mengikuti Sistem SPMB v3
 */
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Muat konfigurasi database & helper dasar
require_once __DIR__ . '/model/config/config.conn.php';
require_once __DIR__ . '/model/helper/cbt.helper.php';
require_once __DIR__ . '/model/helper/auth.helper.php';

// Dispatching melalui controller/route/route.main.php
require_once __DIR__ . '/controller/route/route.main.php';