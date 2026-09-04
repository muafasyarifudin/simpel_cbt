<?php
/**
 * simpel_cbt - Konfigurasi Database & Lingkungan Sistem
 */

// Matikan notifikasi error warning/notice ke output HTML
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        @header('X-Content-Type-Options: nosniff');
        @header('X-Frame-Options: SAMEORIGIN');
        @header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    session_start();
}

// Matikan notifikasi error internal agar output JSON & UI tetap bersih
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

// Kredensial Database
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'simpel_cbt');
define('APP_SECRET', getenv('APP_SECRET') ?: hash('sha256', DB_HOST . '|' . DB_NAME . '|SIMPEL_CBT_2026'));

// Tentukan Base URL secara dinamis
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseFolder = preg_replace('#/(config|api|admin|helpers).*$#', '', $scriptDir);
$baseFolder = rtrim($baseFolder, '/') . '/';
define('BASE_URL', $protocol . $host . $baseFolder);

// Koneksi ke Server MySQL
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);

if (!$conn) {
    die("<!DOCTYPE html>
    <html lang='id'>
    <head><meta charset='UTF-8'><title>Koneksi Database Gagal</title>
    <style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f8fafc;color:#1e293b;}</style>
    </head><body>
    <h2>Gagal Terhubung ke MySQL Database</h2>
    <p>Pastikan modul MySQL di XAMPP Control Panel sudah dalam keadaan <strong>Running (Start)</strong>.</p>
    </body></html>");
}

// Cek apakah database sudah ada, jika belum otomatis buat
$dbSelected = @mysqli_select_db($conn, DB_NAME);
if (!$dbSelected) {
    $createDbSql = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (mysqli_query($conn, $createDbSql)) {
        mysqli_select_db($conn, DB_NAME);
    } else {
        die("Gagal membuat database " . DB_NAME . ": " . mysqli_error($conn));
    }
}

// Set charset utf8mb4
mysqli_set_charset($conn, "utf8mb4");

// Cek apakah tabel utama sudah ada, jika belum otomatis jalankan migrasi
$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'cbt_jadwal'");
if ($checkTable && mysqli_num_rows($checkTable) === 0) {
    require_once __DIR__ . '/migration.php';
}
