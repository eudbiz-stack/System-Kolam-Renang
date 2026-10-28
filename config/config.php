<?php
// config/config.php
session_start();

// Base URL
define('BASE_URL', 'http://localhost/kolam_renang_system/');

// Database connection
require_once 'database.php';
$database = new Database();
$db = $database->getConnection();

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Security functions
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize input
function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check user role
function hasRole($role)
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Redirect if not logged in
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit();
    }
}

// Redirect if wrong role
function requireRole($role)
{
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

// Generate unique booking code
function generateBookingCode()
{
    return 'KR' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Format currency
function formatCurrency($amount)
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Format date
function formatDate($date)
{
    return date('d/m/Y', strtotime($date));
}

// Format datetime
function formatDateTime($datetime)
{
    return date('d/m/Y H:i', strtotime($datetime));
}

// Days in Indonesian
function getDayInIndonesian($day)
{
    $days = [
        'monday' => 'senin',
        'tuesday' => 'selasa',
        'wednesday' => 'rabu',
        'thursday' => 'kamis',
        'friday' => 'jumat',
        'saturday' => 'sabtu',
        'sunday' => 'minggu'
    ];
    return $days[strtolower($day)] ?? $day;
}

// Alert functions
function setAlert($type, $message)
{
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getAlert()
{
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}
