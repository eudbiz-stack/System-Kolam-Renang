<?php
// pemilik/laporan_pemesanan.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('pemilik');

$page_title = "Laporan Pemesanan - Dashboard Pemilik";

// Date filters
$period = $_GET['period'] ?? 'month';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';

// Set dates based on period
switch ($period) {
    case 'today':
        $start_date = $end_date = date('Y-m-d');
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
}

function getBookingReport($db, $start_date, $end_date, $status_filter = '')
{
    $report = [];

    // Overall booking summary
    $query = "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status_pemesanan = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                SUM(CASE WHEN status_pemesanan = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN status_pemesanan = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN status_pembayaran = 'unpaid' THEN 1 ELSE 0 END) as unpaid_bookings,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN 1 ELSE 0 END) as paid_bookings,
                SUM(CASE WHEN status_pembayaran = 'refunded' THEN 1 ELSE 0 END) as refunded_bookings,
                SUM(jumlah_tiket) as total_tickets,
                SUM(total_harga) as total_revenue,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as paid_revenue,
                COUNT(DISTINCT user_id) as unique_customers
              FROM pemesanan 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date";

    if ($status_filter) {
        $query .= " AND status_pemesanan = :status";
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    if ($status_filter) {
        $stmt->bindParam(':status', $status_filter);
    }
    $stmt->execute();
    $report['summary'] = $stmt->fetch();

    // Booking status breakdown by date
    $query = "SELECT 
                DATE(created_at) as booking_date,
                status_pemesanan,
                COUNT(*) as count,
                SUM(total_harga) as revenue
              FROM pemesanan 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date";

    if ($status_filter) {
        $query .= " AND status_pemesanan = :status";
    }

    $query .= " GROUP BY DATE(created_at), status_pemesanan
                ORDER BY booking_date DESC, status_pemesanan";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    if ($status_filter) {
        $stmt->bindParam(':status', $status_filter);
    }
    $stmt->execute();
    $report['daily_status'] = $stmt->fetchAll();

    // Bookings by time slots
    $query = "SELECT 
                HOUR(jam_kunjungan) as hour,
                COUNT(*) as bookings,
                SUM(jumlah_tiket) as tickets,
                SUM(total_harga) as revenue,
                AVG(jumlah_tiket) as avg_tickets
              FROM pemesanan 
              WHERE DATE(tanggal_kunjungan) BETWEEN :start_date AND :end_date";

    if ($status_filter) {
        $query .= " AND status_pemesanan = :status";
    }

    $query .= " GROUP BY HOUR(jam_kunjungan)
                ORDER BY hour ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    if ($status_filter) {
        $stmt->bindParam(':status', $status_filter);
    }
    $stmt->execute();
    $report['by_time'] = $stmt->fetchAll();

    // Bookings by day of week
    $query = "SELECT 
                DAYNAME(tanggal_kunjungan) as day_name,
                DAYOFWEEK(tanggal_kunjungan) as day_num,
                COUNT(*) as bookings,
                SUM(jumlah_tiket) as tickets,
                SUM(total_harga) as revenue
              FROM pemesanan 
              WHERE DATE(tanggal_kunjungan) BETWEEN :start_date AND :end_date";

    if ($status_filter) {
        $query .= " AND status_pemesanan = :status";
    }

    $query .= " GROUP BY DAYOFWEEK(tanggal_kunjungan), DAYNAME(tanggal_kunjungan)
                ORDER BY day_num";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    if ($status_filter) {
        $stmt->bindParam(':status', $status_filter);
    }
    $stmt->execute();
    $report['by_day'] = $stmt->fetchAll();

    // Ticket type analysis
    $query = "SELECT 
                jt.nama_tiket,
                jt.harga,
                COUNT(p.id) as total_bookings,
                SUM(p.jumlah_tiket) as total_tickets,
                SUM(p.total_harga) as total_revenue,
                AVG(p.jumlah_tiket) as avg_tickets_per_booking
              FROM jenis_tiket jt
              LEFT JOIN pemesanan p ON jt.id = p.jenis_tiket_id 
                AND DATE(p.created_at) BETWEEN :start_date AND :end_date";

    if ($status_filter) {
        $query .= " AND p.status_pemesanan = :status";
    }

    $query .= " GROUP BY jt.id
                ORDER BY total_revenue DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    if ($status_filter) {
        $stmt->bindParam(':status', $status_filter);
    }
    $stmt->execute();
    $report['by_ticket_type'] = $stmt->fetchAll();

    // Customer behavior analysis
    $query = "SELECT 
                u.nama_lengkap,
                u.email,
                COUNT(p.id) as total_bookings,
                SUM(CASE WHEN p.status_pemesanan = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN p.status_pemesanan = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(p.jumlah_tiket) as total_tickets,
                SUM(p.total_harga) as total_spent,
                AVG(p.jumlah_tiket) as avg_tickets_per_booking,
                MIN(p.created_at) as first_booking,
                MAX(p.created_at) as last_booking
              FROM users u
              JOIN pemesanan p ON u.id = p.user_id
              WHERE DATE(p.created_at) BETWEEN :start_date AND :end_date";

    if ($status_filter) {
        $query .= " AND p.status_pemesanan = :status";
    }

    $query .= " GROUP BY u.id
                ORDER BY total_bookings DESC
                LIMIT 20";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    if ($status_filter) {
        $stmt->bindParam(':status', $status_filter);
    }
    $stmt->execute();
    $report['customer_behavior'] = $stmt->fetchAll();

    // Booking lead time analysis
    $query = "SELECT 
                CASE 
                    WHEN DATEDIFF(tanggal_kunjungan, DATE(created_at)) = 0 THEN 'Same Day'
                    WHEN DATEDIFF(tanggal_kunjungan, DATE(created_at)) = 1 THEN '1 Day'
                    WHEN DATEDIFF(tanggal_kunjungan, DATE(created_at)) BETWEEN 2 AND 7 THEN '2-7 Days'
                    WHEN DATEDIFF(tanggal_kunjungan, DATE(created_at)) BETWEEN 8 AND 14 THEN '1-2 Weeks'
                    WHEN DATEDIFF(tanggal_kunjungan, DATE(created_at)) BETWEEN 15 AND 30 THEN '2-4 Weeks'
                    ELSE 'More than 1 Month'
                END as lead_time_range,
                COUNT(*) as bookings,
                AVG(DATEDIFF(tanggal_kunjungan, DATE(created_at))) as avg_lead_days
              FROM pemesanan 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date";

    if ($status_filter) {
        $query .= " AND status_pemesanan = :status";
    }

    $query .= " GROUP BY lead_time_range
                ORDER BY avg_lead_days";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    if ($status_filter) {
        $stmt->bindParam(':status', $status_filter);
    }
    $stmt->execute();
    $report['lead_time'] = $stmt->fetchAll();

    // Recent bookings detail
    $query = "SELECT p.*, jt.nama_tiket, u.nama_lengkap, u.email
              FROM pemesanan p
              JOIN jenis_tiket jt ON p.jenis_tiket_id = jt.id
              JOIN users u ON p.user_id = u.id
              WHERE DATE(p.created_at) BETWEEN :start_date AND :end_date";

    if ($status_filter) {
        $query .= " AND p.status_pemesanan = :status";
    }

    $query .= " ORDER BY p.created_at DESC
                LIMIT 50";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    if ($status_filter) {
        $stmt->bindParam(':status', $status_filter);
    }
    $stmt->execute();
    $report['recent_bookings'] = $stmt->fetchAll();

    return $report;
}

// Get the report data
$report = getBookingReport($db, $start_date, $end_date, $status_filter);


// Function to get status badge class
function getStatusBadgeClass($status)
{
    switch ($status) {
        case 'pending':
            return 'badge-warning';
        case 'confirmed':
            return 'badge-success';
        case 'cancelled':
            return 'badge-danger';
        case 'paid':
            return 'badge-success';
        case 'unpaid':
            return 'badge-warning';
        case 'refunded':
            return 'badge-info';
        default:
            return 'badge-secondary';
    }
}

// Function to translate status
function translateStatus($status)
{
    $translations = [
        'pending' => 'Menunggu',
        'confirmed' => 'Dikonfirmasi',
        'cancelled' => 'Dibatalkan',
        'paid' => 'Lunas',
        'unpaid' => 'Belum Bayar',
        'refunded' => 'Refund'
    ];
    return $translations[$status] ?? $status;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-lg-flex">
                        <div>
                            <h5 class="mb-0">Laporan Pemesanan</h5>
                            <p class="text-sm mb-0">
                                Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>
                            </p>
                        </div>
                        <div class="ms-auto my-auto mt-lg-0 mt-4">
                            <div class="ms-auto my-auto">
                                <button class="btn btn-outline-primary btn-sm mb-0" onclick="window.print()">
                                    <i class="fas fa-print"></i> Cetak
                                </button>
                                <button class="btn btn-outline-success btn-sm mb-0" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel"></i> Export Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Periode</label>
                            <select name="period" class="form-select" onchange="this.form.submit()">
                                <option value="today" <?= $period == 'today' ? 'selected' : '' ?>>Hari Ini</option>
                                <option value="week" <?= $period == 'week' ? 'selected' : '' ?>>Minggu Ini</option>
                                <option value="month" <?= $period == 'month' ? 'selected' : '' ?>>Bulan Ini</option>
                                <option value="year" <?= $period == 'year' ? 'selected' : '' ?>>Tahun Ini</option>
                                <option value="custom" <?= $period == 'custom' ? 'selected' : '' ?>>Custom</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>"
                                <?= $period != 'custom' ? 'readonly' : '' ?>>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Tanggal Akhir</label>
                            <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>"
                                <?= $period != 'custom' ? 'readonly' : '' ?>>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Menunggu</option>
                                <option value="confirmed" <?= $status_filter == 'confirmed' ? 'selected' : '' ?>>Dikonfirmasi</option>
                                <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mt-4">
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Total Pemesanan</p>
                                <h5 class="font-weight-bolder mb-0"><?= number_format($report['summary']['total_bookings']) ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                <i class="ni ni-calendar-grid-58 text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Total Tiket</p>
                                <h5 class="font-weight-bolder mb-0"><?= number_format($report['summary']['total_tickets']) ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                                <i class="ni ni-tag text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Total Pendapatan</p>
                                <h5 class="font-weight-bolder mb-0"><?= formatCurrency($report['summary']['total_revenue']) ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                                <i class="ni ni-money-coins text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Pelanggan Unik</p>
                                <h5 class="font-weight-bolder mb-0"><?= number_format($report['summary']['unique_customers']) ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                                <i class="ni ni-single-02 text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Breakdown -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Status Pemesanan</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span>Menunggu:</span>
                                <span class="badge <?= getStatusBadgeClass('pending') ?>">
                                    <?= $report['summary']['pending_bookings'] ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span>Dikonfirmasi:</span>
                                <span class="badge <?= getStatusBadgeClass('confirmed') ?>">
                                    <?= $report['summary']['confirmed_bookings'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span>Dibatalkan:</span>
                                <span class="badge <?= getStatusBadgeClass('cancelled') ?>">
                                    <?= $report['summary']['cancelled_bookings'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Status Pembayaran</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span>Belum Bayar:</span>
                                <span class="badge <?= getStatusBadgeClass('unpaid') ?>">
                                    <?= $report['summary']['unpaid_bookings'] ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span>Lunas:</span>
                                <span class="badge <?= getStatusBadgeClass('paid') ?>">
                                    <?= $report['summary']['paid_bookings'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span>Refund:</span>
                                <span class="badge <?= getStatusBadgeClass('refunded') ?>">
                                    <?= $report['summary']['refunded_bookings'] ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span>Pendapatan Bersih:</span>
                                <span class="text-success fw-bold">
                                    <?= formatCurrency($report['summary']['paid_revenue']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analysis Charts -->
    <div class="row mt-4">
        <!-- Time Slot Analysis -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Analisis Jam Kunjungan</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Jam</th>
                                    <th>Pemesanan</th>
                                    <th>Tiket</th>
                                    <th>Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($report['by_time'])): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($report['by_time'] as $time_data): ?>
                                        <tr>
                                            <td><?= sprintf('%02d:00', $time_data['hour']) ?></td>
                                            <td><?= $time_data['bookings'] ?></td>
                                            <td><?= $time_data['tickets'] ?></td>
                                            <td><?= formatCurrency($time_data['revenue']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Day of Week Analysis -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Analisis Hari Kunjungan</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Hari</th>
                                    <th>Pemesanan</th>
                                    <th>Tiket</th>
                                    <th>Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($report['by_day'])): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($report['by_day'] as $day_data): ?>
                                        <tr>
                                            <td><?= $day_data['day_name'] ?></td>
                                            <td><?= $day_data['bookings'] ?></td>
                                            <td><?= $day_data['tickets'] ?></td>
                                            <td><?= formatCurrency($day_data['revenue']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ticket Type Analysis -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Analisis Jenis Tiket</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Jenis Tiket</th>
                                    <th>Harga</th>
                                    <th>Pemesanan</th>
                                    <th>Total Tiket</th>
                                    <th>Pendapatan</th>
                                    <th>Rata-rata Tiket/Pemesanan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($report['by_ticket_type'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($report['by_ticket_type'] as $ticket_data): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ticket_data['nama_tiket']) ?></td>
                                            <td><?= formatCurrency($ticket_data['harga']) ?></td>
                                            <td><?= $ticket_data['total_bookings'] ?></td>
                                            <td><?= $ticket_data['total_tickets'] ?></td>
                                            <td><?= formatCurrency($ticket_data['total_revenue']) ?></td>
                                            <td><?= number_format($ticket_data['avg_tickets_per_booking'], 1) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lead Time Analysis -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Analisis Waktu Pemesanan</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Rentang Waktu</th>
                                    <th>Jumlah Pemesanan</th>
                                    <th>Persentase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($report['lead_time'])): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    $total_lead_bookings = array_sum(array_column($report['lead_time'], 'bookings'));
                                    foreach ($report['lead_time'] as $lead_data):
                                        $percentage = $total_lead_bookings > 0 ? ($lead_data['bookings'] / $total_lead_bookings * 100) : 0;
                                    ?>
                                        <tr>
                                            <td><?= $lead_data['lead_time_range'] ?></td>
                                            <td><?= $lead_data['bookings'] ?></td>
                                            <td><?= number_format($percentage, 1) ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Customers -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Pelanggan Teratas</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Pemesanan</th>
                                    <th>Total Tiket</th>
                                    <th>Total Belanja</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($report['customer_behavior'])): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($report['customer_behavior'], 0, 10) as $customer): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($customer['nama_lengkap']) ?></td>
                                            <td><?= $customer['total_bookings'] ?></td>
                                            <td><?= $customer['total_tickets'] ?></td>
                                            <td><?= formatCurrency($customer['total_spent']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>Pemesanan Terbaru</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Kode Booking</th>
                                    <th>Pelanggan</th>
                                    <th>Jenis Tiket</th>
                                    <th>Tanggal Kunjungan</th>
                                    <th>Jam</th>
                                    <th>Jumlah Tiket</th>
                                    <th>Total Harga</th>
                                    <th>Status Pemesanan</th>
                                    <th>Status Pembayaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($report['recent_bookings'])): ?>
                                    <tr>
                                        <td colspan="9" class="text-center">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($report['recent_bookings'] as $booking): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($booking['kode_booking']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($booking['nama_lengkap']) ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($booking['email']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($booking['nama_tiket']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($booking['tanggal_kunjungan'])) ?></td>
                                            <td><?= date('H:i', strtotime($booking['jam_kunjungan'])) ?></td>
                                            <td><?= $booking['jumlah_tiket'] ?></td>
                                            <td><?= formatCurrency($booking['total_harga']) ?></td>
                                            <td>
                                                <span class="badge <?= getStatusBadgeClass($booking['status_pemesanan']) ?>">
                                                    <?= translateStatus($booking['status_pemesanan']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?= getStatusBadgeClass($booking['status_pembayaran']) ?>">
                                                    <?= translateStatus($booking['status_pembayaran']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle period change
    document.querySelector('select[name="period"]').addEventListener('change', function() {
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');

        if (this.value === 'custom') {
            startDateInput.removeAttribute('readonly');
            endDateInput.removeAttribute('readonly');
        } else {
            startDateInput.setAttribute('readonly', 'readonly');
            endDateInput.setAttribute('readonly', 'readonly');
        }
    });

    // Export to Excel function
    function exportToExcel() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'excel');
        window.location.href = window.location.pathname + '?' + params.toString();
    }

    // Print styles
    const printCSS = `
    @media print {
        .btn, .form-control, .form-select, form { display: none !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .table { font-size: 12px; }
        h5, h6 { font-size: 14px; }
        .badge { border: 1px solid #333; }
    }
`;

    // Add print styles
    const style = document.createElement('style');
    style.textContent = printCSS;
    document.head.appendChild(style);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>