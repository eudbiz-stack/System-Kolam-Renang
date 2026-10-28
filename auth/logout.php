<?php
// logout.php - Universal logout file
// This file can be placed in admin/, pelanggan/, pemilik/ folders

// Determine the correct path to config based on current directory
$config_path = '';
if (
    strpos(__DIR__, 'admin') !== false ||
    strpos(__DIR__, 'pelanggan') !== false ||
    strpos(__DIR__, 'pemilik') !== false
) {
    $config_path = '../config/config.php';
} else {
    $config_path = 'config/config.php';
}

require_once $config_path;

// Destroy session
session_destroy();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect to home page
header('Location: ' . BASE_URL . 'index.php');
exit();
