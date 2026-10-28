<?php
// pemilik/logout.php
require_once __DIR__ . '/../config/config.php';

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
