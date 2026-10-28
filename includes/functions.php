<?php
// includes/functions.php
require_once __DIR__ . '/../config/config.php';

// Get user data by ID
function getUserById($user_id)
{
    global $db;
    $query = "SELECT * FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    return $stmt->fetch();
}

// Get all ticket types
function getTicketTypes($status = 'aktif')
{
    global $db;
    $query = "SELECT * FROM jenis_tiket WHERE status = :status ORDER BY nama_tiket";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get ticket type by ID
function getTicketById($id)
{
    global $db;
    $query = "SELECT * FROM jenis_tiket WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    return $stmt->fetch();
}

// Get operating hours for a specific day
function getOperatingHours($day)
{
    global $db;
    $query = "SELECT * FROM jam_operasional WHERE hari = :hari";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':hari', $day);
    $stmt->execute();
    return $stmt->fetch();
}

// Check if date and time is available for booking
function isTimeSlotAvailable($date, $time, $exclude_booking_id = null)
{
    global $db;

    // Check operating hours
    $day = getDayInIndonesian(date('l', strtotime($date)));
    $operating = getOperatingHours($day);

    if (!$operating || $operating['status'] !== 'buka') {
        return false;
    }

    $booking_time = strtotime($time);
    $open_time = strtotime($operating['jam_buka']);
    $close_time = strtotime($operating['jam_tutup']);

    if ($booking_time < $open_time || $booking_time > $close_time) {
        return false;
    }

    // Check if slot is not overbooked (simple check)
    $query = "SELECT COUNT(*) as count FROM pemesanan 
              WHERE tanggal_kunjungan = :date 
              AND jam_kunjungan = :time 
              AND status_pemesanan != 'cancelled'";

    if ($exclude_booking_id) {
        $query .= " AND id != :exclude_id";
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':time', $time);

    if ($exclude_booking_id) {
        $stmt->bindParam(':exclude_id', $exclude_booking_id);
    }

    $stmt->execute();
    $result = $stmt->fetch();

    // Simple capacity check - max 50 bookings per time slot
    return $result['count'] < 50;
}

// Get booking by code
function getBookingByCode($code)
{
    global $db;
    $query = "SELECT p.*, jt.nama_tiket, jt.harga, u.nama_lengkap, u.email, u.no_telepon
              FROM pemesanan p
              JOIN jenis_tiket jt ON p.jenis_tiket_id = jt.id
              JOIN users u ON p.user_id = u.id
              WHERE p.kode_booking = :code";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':code', $code);
    $stmt->execute();
    return $stmt->fetch();
}

// Get bookings by user
function getBookingsByUser($user_id, $limit = null)
{
    global $db;
    $query = "SELECT p.*, jt.nama_tiket, jt.harga
              FROM pemesanan p
              JOIN jenis_tiket jt ON p.jenis_tiket_id = jt.id
              WHERE p.user_id = :user_id
              ORDER BY p.created_at DESC";

    if ($limit) {
        $query .= " LIMIT :limit";
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);

    if ($limit) {
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    }

    $stmt->execute();
    return $stmt->fetchAll();
}

// Get payment by booking ID
function getPaymentByBookingId($booking_id)
{
    global $db;
    $query = "SELECT * FROM pembayaran WHERE pemesanan_id = :booking_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':booking_id', $booking_id);
    $stmt->execute();
    return $stmt->fetch();
}

// Get statistics for dashboard
function getDashboardStats()
{
    global $db;

    $stats = [];

    // Total bookings today
    $query = "SELECT COUNT(*) as count FROM pemesanan WHERE DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['bookings_today'] = $stmt->fetch()['count'];

    // Total revenue this month
    $query = "SELECT SUM(total_harga) as total FROM pemesanan 
              WHERE MONTH(created_at) = MONTH(CURDATE()) 
              AND YEAR(created_at) = YEAR(CURDATE())
              AND status_pembayaran = 'paid'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['revenue_month'] = $stmt->fetch()['total'] ?? 0;

    // Pending payments
    $query = "SELECT COUNT(*) as count FROM pemesanan WHERE status_pembayaran = 'unpaid'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['pending_payments'] = $stmt->fetch()['count'];

    // Total customers
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'pelanggan'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_customers'] = $stmt->fetch()['count'];

    return $stats;
}

// Get setting value
function getSetting($key)
{
    global $db;
    $query = "SELECT setting_value FROM settings WHERE setting_key = :key";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':key', $key);
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : null;
}

// Update setting
function updateSetting($key, $value)
{
    global $db;
    $query = "UPDATE settings SET setting_value = :value WHERE setting_key = :key";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':value', $value);
    $stmt->bindParam(':key', $key);
    return $stmt->execute();
}

// Validate email
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number (Indonesian format)
function isValidPhone($phone)
{
    return preg_match('/^(\+62|62|0)8[1-9][0-9]{6,9}$/', $phone);
}

// Upload file handler
function uploadFile($file, $destination_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'])
{
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }

    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size too large'];
    }

    $filename = uniqid() . '.' . $file_extension;
    $destination = $destination_dir . '/' . $filename;

    if (!is_dir($destination_dir)) {
        mkdir($destination_dir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file'];
    }
}
