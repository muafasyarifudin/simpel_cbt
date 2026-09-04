<?php
/**
 * simpel_cbt - Admin Redirector (Standar spmb_v3)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['simpel_cbt_admin_id'])) {
    header("Location: ../index.php?m=pusat");
    exit();
} else {
    header("Location: ../index.php?m=login-pusat");
    exit();
}