<?php
/**
 * Admin Authentication Middleware
 */
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Global available admin info
$adminId = $_SESSION['admin_id'];
$adminUser = $_SESSION['admin_user'];
$adminRole = $_SESSION['admin_role'];
