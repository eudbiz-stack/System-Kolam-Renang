<?php
// pemilik/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('pemilik');

$page_title = "Dashboard Pemilik - Kolam Renang Licia";

// Date filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Get comprehensive statistics
function getOwnerStats($db, $start_date, $end_date)
{
    $stats = [];

    // Revenue statistics
    $query = "SELECT 
                SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as total_revenue,
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status_pemesanan = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN 1 ELSE 0 END) as paid_bookings,
                SUM(CASE WHEN status_pemesanan = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(jumlah_tiket) as total_tickets_sold
              FROM pemesanan 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $stats['overview'] = $stmt->fetch();

    // Today's stats
    $today = date('Y-m-d');
    $query = "SELECT 
                SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as today_revenue,
                COUNT(*) as today_bookings
              FROM pemesanan 
              WHERE DATE(created_at) = :today";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    $stats['today'] = $stmt->fetch();

    // Monthly comparison
    $current_month = date('Y-m');
    $last_month = date('Y-m', strtotime('-1 month'));

    $query = "SELECT 
                SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = :current_month AND status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as current_month_revenue,
                SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = :last_month AND status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as last_month_revenue,
                SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = :current_month THEN 1 ELSE 0 END) as current_month_bookings,
                SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = :last_month THEN 1 ELSE 0 END) as last_month_bookings
              FROM pemesanan";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':current_month', $current_month);
    $stmt->bindParam(':last_month', $last_month);
    $stmt->execute();
    $stats['monthly'] = $stmt->fetch();

    // Popular tickets
    $query = "SELECT jt.nama_tiket, jt.harga,
                COUNT(p.id) as total_orders,
                SUM(p.jumlah_tiket) as total_tickets,
                SUM(CASE WHEN p.status_pembayaran = 'paid' THEN p.total_harga ELSE 0 END) as revenue
              FROM jenis_tiket jt
              LEFT JOIN pemesanan p ON jt.id = p.jenis_tiket_id 
                AND DATE(p.created_at) BETWEEN :start_date AND :end_date
              GROUP BY jt.id
              ORDER BY revenue DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $stats['popular_tickets'] = $stmt->fetchAll();

    // Daily revenue for chart
    $query = "SELECT DATE(created_at) as date,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as revenue,
                COUNT(*) as bookings
              FROM pemesanan 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date
              GROUP BY DATE(created_at)
              ORDER BY date ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $stats['daily_revenue'] = $stmt->fetchAll();

    // Customer statistics
    $query = "SELECT 
                COUNT(DISTINCT user_id) as unique_customers,
                COUNT(DISTINCT CASE WHEN DATE(created_at) BETWEEN :start_date AND :end_date THEN user_id END) as period_customers
              FROM pemesanan";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $stats['customers'] = $stmt->fetch();

    // Peak hours analysis
    $query = "SELECT HOUR(jam_kunjungan) as hour,
                COUNT(*) as bookings,
                SUM(jumlah_tiket) as tickets
              FROM pemesanan 
              WHERE DATE(tanggal_kunjungan) BETWEEN :start_date AND :end_date
                AND status_pemesanan = 'confirmed'
              GROUP BY HOUR(jam_kunjungan)
              ORDER BY hour ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $stats['peak_hours'] = $stmt->fetchAll();

    return $stats;
}

$stats = getOwnerStats($db, $start_date, $end_date);

// Calculate growth percentages
$revenue_growth = 0;
$booking_growth = 0;
if ($stats['monthly']['last_month_revenue'] > 0) {
    $revenue_growth = (($stats['monthly']['current_month_revenue'] - $stats['monthly']['last_month_revenue']) / $stats['monthly']['last_month_revenue']) * 100;
}
if ($stats['monthly']['last_month_bookings'] > 0) {
    $booking_growth = (($stats['monthly']['current_month_bookings'] - $stats['monthly']['last_month_bookings']) / $stats['monthly']['last_month_bookings']) * 100;
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h5 class="text-white">Dashboard Pemilik</h5>
                    <small class="text-white-50">Selamat datang, <?= $_SESSION['nama_lengkap'] ?></small>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="laporan_penjualan.php">
                            <i class="fas fa-chart-line me-2"></i>Laporan Penjualan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="laporan_pemesanan.php">
                            <i class="fas fa-clipboard-list me-2"></i>Laporan Pemesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php">
                            <i class="fas fa-chart-pie me-2"></i>Analytics
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-home me-2"></i>Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard Overview</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <form method="GET" class="d-flex gap-2">
                        <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                        <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-filter"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Key Metrics Row 1 -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card success">
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3><?= formatCurrency($stats['overview']['total_revenue']) ?></h3>
                        <p class="text-muted">Total Revenue</p>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-arrow-<?= $revenue_growth >= 0 ? 'up text-success' : 'down text-danger' ?> me-1"></i>
                            <small class="text-<?= $revenue_growth >= 0 ? 'success' : 'danger' ?>">
                                <?= number_format(abs($revenue_growth), 1) ?>% vs bulan lalu
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card primary">
                        <div class="icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <h3><?= $stats['overview']['total_bookings'] ?></h3>
                        <p class="text-muted">Total Bookings</p>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-arrow-<?= $booking_growth >= 0 ? 'up text-success' : 'down text-danger' ?> me-1"></i>
                            <small class="text-<?= $booking_growth >= 0 ? 'success' : 'danger' ?>">
                                <?= number_format(abs($booking_growth), 1) ?>% vs bulan lalu
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card info">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?= $stats['customers']['period_customers'] ?></h3>
                        <p class="text-muted">Active Customers</p>
                        <small class="text-muted">
                            Total: <?= $stats['customers']['unique_customers'] ?> customers
                        </small>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card warning">
                        <div class="icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <h3><?= $stats['overview']['total_bookings'] > 0 ? round(($stats['overview']['confirmed_bookings'] / $stats['overview']['total_bookings']) * 100, 1) : 0 ?>%</h3>
                        <p class="text-muted">Confirmation Rate</p>
                        <small class="text-muted">
                            <?= $stats['overview']['confirmed_bookings'] ?>/<?= $stats['overview']['total_bookings'] ?> confirmed
                        </small>
                    </div>
                </div>
            </div>

            <!-- Key Metrics Row 2 -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card-custom">
                        <div class="card-body text-center">
                            <div class="icon bg-success text-white rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <h4><?= formatCurrency($stats['today']['today_revenue']) ?></h4>
                            <p class="text-muted mb-0">Hari Ini</p>
                            <small class="text-muted"><?= $stats['today']['today_bookings'] ?> bookings</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card-custom">
                        <div class="card-body text-center">
                            <div class="icon bg-primary text-white rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h4><?= $stats['overview']['total_tickets_sold'] ?></h4>
                            <p class="text-muted mb-0">Tiket Terjual</p>
                            <small class="text-muted">Total periode</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card-custom">
                        <div class="card-body text-center">
                            <div class="icon bg-info text-white rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4><?= $stats['overview']['paid_bookings'] ?></h4>
                            <p class="text-muted mb-0">Pembayaran Lunas</p>
                            <small class="text-muted">
                                <?= $stats['overview']['total_bookings'] > 0 ? round(($stats['overview']['paid_bookings'] / $stats['overview']['total_bookings']) * 100, 1) : 0 ?>% dari total
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card-custom">
                        <div class="card-body text-center">
                            <div class="icon bg-warning text-white rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <h4><?= $stats['overview']['paid_bookings'] > 0 ? formatCurrency($stats['overview']['total_revenue'] / $stats['overview']['paid_bookings']) : formatCurrency(0) ?></h4>
                            <p class="text-muted mb-0">Rata-rata Transaksi</p>
                            <small class="text-muted">Per pembayaran</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- Revenue Chart -->
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>Tren Revenue Harian
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="400"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Peak Hours Chart -->
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clock me-2"></i>Jam Sibuk
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="peakHoursChart" height="400"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popular Tickets -->
            <div class="row">
                <div class="col-12">
                    <div class="card-custom">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-star me-2"></i>Performa Jenis Tiket
                            </h5>
                            <span class="text-muted">Periode: <?= formatDate($start_date) ?> - <?= formatDate($end_date) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Jenis Tiket</th>
                                            <th>Harga</th>
                                            <th>Total Order</th>
                                            <th>Tiket Terjual</th>
                                            <th>Revenue</th>
                                            <th>% dari Total Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['popular_tickets'] as $ticket): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($ticket['nama_tiket']) ?></strong>
                                                </td>
                                                <td><?= formatCurrency($ticket['harga']) ?></td>
                                                <td><?= $ticket['total_orders'] ?></td>
                                                <td><?= $ticket['total_tickets'] ?></td>
                                                <td class="fw-bold"><?= formatCurrency($ticket['revenue']) ?></td>
                                                <td>
                                                    <?php
                                                    $percentage = $stats['overview']['total_revenue'] > 0 ?
                                                        ($ticket['revenue'] / $stats['overview']['total_revenue']) * 100 : 0;
                                                    ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress me-2" style="width: 100px; height: 20px;">
                                                            <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                                        </div>
                                                        <span><?= number_format($percentage, 1) ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Revenue Chart Data
    const revenueData = <?= json_encode($stats['daily_revenue']) ?>;
    const revenueLabels = revenueData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short'
        });
    });
    const revenueValues = revenueData.map(item => item.revenue);

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: revenueLabels,
            datasets: [{
                label: 'Revenue (Rp)',
                data: revenueValues,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            },
            elements: {
                point: {
                    radius: 6,
                    hoverRadius: 8
                }
            }
        }
    });

    // Peak Hours Chart Data
    const peakHoursData = <?= json_encode($stats['peak_hours']) ?>;
    const hourLabels = peakHoursData.map(item => item.hour + ':00');
    const hourValues = peakHoursData.map(item => item.bookings);

    // Peak Hours Chart
    const peakCtx = document.getElementById('peakHoursChart').getContext('2d');
    new Chart(peakCtx, {
        type: 'bar',
        data: {
            labels: hourLabels,
            datasets: [{
                label: 'Bookings',
                data: hourValues,
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                borderColor: '#667eea',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>