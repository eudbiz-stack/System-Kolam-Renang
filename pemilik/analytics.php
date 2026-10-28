<?php
// pemilik/analytics.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('pemilik');

$page_title = "Analytics - Dashboard Pemilik";

// Get analytics data
function getAnalyticsData($db)
{
    $analytics = [];

    // Revenue trends (last 6 months)
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as revenue,
                COUNT(*) as bookings,
                SUM(jumlah_tiket) as tickets
              FROM pemesanan 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $analytics['revenue_trends'] = $stmt->fetchAll();

    // Customer acquisition and retention
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(DISTINCT user_id) as new_customers
              FROM pemesanan p1
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                AND NOT EXISTS (
                    SELECT 1 FROM pemesanan p2 
                    WHERE p2.user_id = p1.user_id 
                    AND p2.created_at < p1.created_at
                )
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $analytics['customer_acquisition'] = $stmt->fetchAll();

    // Ticket performance analysis
    $query = "SELECT 
                jt.nama_tiket,
                jt.harga,
                COUNT(p.id) as total_orders,
                SUM(p.jumlah_tiket) as total_tickets,
                SUM(CASE WHEN p.status_pembayaran = 'paid' THEN p.total_harga ELSE 0 END) as revenue,
                AVG(p.jumlah_tiket) as avg_tickets_per_order,
                (SUM(CASE WHEN p.status_pembayaran = 'paid' THEN p.total_harga ELSE 0 END) / 
                 (SELECT SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) FROM pemesanan) * 100) as revenue_share
              FROM jenis_tiket jt
              LEFT JOIN pemesanan p ON jt.id = p.jenis_tiket_id
              GROUP BY jt.id
              ORDER BY revenue DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $analytics['ticket_performance'] = $stmt->fetchAll();

    // Seasonal analysis (last 12 months by month)
    $query = "SELECT 
                MONTHNAME(tanggal_kunjungan) as month_name,
                MONTH(tanggal_kunjungan) as month_num,
                COUNT(*) as bookings,
                SUM(jumlah_tiket) as tickets,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as revenue
              FROM pemesanan 
              WHERE tanggal_kunjungan >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY MONTH(tanggal_kunjungan), MONTHNAME(tanggal_kunjungan)
              ORDER BY month_num";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $analytics['seasonal'] = $stmt->fetchAll();

    // Peak analysis by hour and day
    $query = "SELECT 
                HOUR(jam_kunjungan) as hour,
                DAYNAME(tanggal_kunjungan) as day_name,
                COUNT(*) as bookings,
                SUM(jumlah_tiket) as tickets
              FROM pemesanan 
              WHERE tanggal_kunjungan >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                AND status_pemesanan = 'confirmed'
              GROUP BY HOUR(jam_kunjungan), DAYNAME(tanggal_kunjungan)
              ORDER BY bookings DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $analytics['peak_analysis'] = $stmt->fetchAll();

    // Customer segments
    $query = "SELECT 
                CASE 
                    WHEN total_spent >= 500000 THEN 'VIP'
                    WHEN total_spent >= 200000 THEN 'Premium'
                    WHEN total_spent >= 50000 THEN 'Regular'
                    ELSE 'New'
                END as segment,
                COUNT(*) as customer_count,
                AVG(total_spent) as avg_spent,
                SUM(total_spent) as segment_revenue
              FROM (
                  SELECT 
                      u.id,
                      u.nama_lengkap,
                      SUM(CASE WHEN p.status_pembayaran = 'paid' THEN p.total_harga ELSE 0 END) as total_spent
                  FROM users u
                  LEFT JOIN pemesanan p ON u.id = p.user_id
                  WHERE u.role = 'pelanggan'
                  GROUP BY u.id
              ) customer_spending
              GROUP BY segment
              ORDER BY avg_spent DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $analytics['customer_segments'] = $stmt->fetchAll();

    // Conversion funnel
    $query = "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status_pemesanan = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN 1 ELSE 0 END) as paid_bookings
              FROM pemesanan 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $analytics['conversion_funnel'] = $stmt->fetch();

    // Average transaction value trends
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                AVG(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE NULL END) as avg_transaction_value,
                COUNT(CASE WHEN status_pembayaran = 'paid' THEN 1 ELSE NULL END) as paid_transactions
              FROM pemesanan 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $analytics['avg_transaction_trends'] = $stmt->fetchAll();

    // Customer lifetime value
    $query = "SELECT 
                u.nama_lengkap,
                COUNT(p.id) as total_bookings,
                SUM(CASE WHEN p.status_pembayaran = 'paid' THEN p.total_harga ELSE 0 END) as lifetime_value,
                MIN(p.created_at) as first_booking,
                MAX(p.created_at) as last_booking,
                DATEDIFF(MAX(p.created_at), MIN(p.created_at)) as customer_lifespan_days
              FROM users u
              JOIN pemesanan p ON u.id = p.user_id
              WHERE u.role = 'pelanggan'
              GROUP BY u.id
              HAVING lifetime_value > 0
              ORDER BY lifetime_value DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $analytics['top_clv'] = $stmt->fetchAll();

    return $analytics;
}

$analytics = getAnalyticsData($db);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h5 class="text-white">Dashboard Pemilik</h5>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
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
                        <a class="nav-link active" href="analytics.php">
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
                <h1 class="h2">Business Analytics</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="badge bg-info fs-6">Data Real-time</span>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card success">
                        <div class="icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <h3>
                            <?= $analytics['conversion_funnel']['total_bookings'] > 0 ?
                                round(($analytics['conversion_funnel']['confirmed_bookings'] / $analytics['conversion_funnel']['total_bookings']) * 100, 1) : 0 ?>%
                        </h3>
                        <p class="text-muted">Confirmation Rate</p>
                        <small class="text-muted">Bulan ini</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card primary">
                        <div class="icon">
                            <i class="fas fa-funnel-dollar"></i>
                        </div>
                        <h3>
                            <?= $analytics['conversion_funnel']['confirmed_bookings'] > 0 ?
                                round(($analytics['conversion_funnel']['paid_bookings'] / $analytics['conversion_funnel']['confirmed_bookings']) * 100, 1) : 0 ?>%
                        </h3>
                        <p class="text-muted">Payment Rate</p>
                        <small class="text-muted">Dari konfirmasi</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card warning">
                        <div class="icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <h3>
                            <?= !empty($analytics['avg_transaction_trends']) ?
                                formatCurrency(end($analytics['avg_transaction_trends'])['avg_transaction_value']) : formatCurrency(0) ?>
                        </h3>
                        <p class="text-muted">Avg Transaction</p>
                        <small class="text-muted">Bulan terakhir</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card info">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?= array_sum(array_column($analytics['customer_segments'], 'customer_count')) ?></h3>
                        <p class="text-muted">Total Customers</p>
                        <small class="text-muted">Semua segmen</small>
                    </div>
                </div>
            </div>

            <!-- Revenue & Customer Trends -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>Revenue & Customer Acquisition Trends (6 Bulan Terakhir)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueCustomerChart" height="400"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Customer Segments -->
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-users-cog me-2"></i>Customer Segments
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($analytics['customer_segments'] as $segment): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold"><?= $segment['segment'] ?></span>
                                        <span class="badge bg-primary"><?= $segment['customer_count'] ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted">Avg Spent: <?= formatCurrency($segment['avg_spent']) ?></small>
                                        <small class="text-muted">Total: <?= formatCurrency($segment['segment_revenue']) ?></small>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?= array_sum(array_column($analytics['customer_segments'], 'customer_count')) > 0 ? ($segment['customer_count'] / array_sum(array_column($analytics['customer_segments'], 'customer_count'))) * 100 : 0 ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seasonal Analysis & Peak Times -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>Seasonal Analysis (Revenue by Month)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="seasonalChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Average Transaction Value Trends -->
                <div class="col-lg-6">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-money-bill-trend-up me-2"></i>Average Transaction Value Trends
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="atvChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ticket Performance -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-ticket-alt me-2"></i>Ticket Performance Analysis
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Jenis Tiket</th>
                                            <th>Harga</th>
                                            <th>Total Orders</th>
                                            <th>Tiket Terjual</th>
                                            <th>Revenue</th>
                                            <th>Revenue Share</th>
                                            <th>Avg Tiket/Order</th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analytics['ticket_performance'] as $ticket): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($ticket['nama_tiket']) ?></strong></td>
                                                <td><?= formatCurrency($ticket['harga']) ?></td>
                                                <td><?= $ticket['total_orders'] ?></td>
                                                <td><?= $ticket['total_tickets'] ?></td>
                                                <td class="fw-bold"><?= formatCurrency($ticket['revenue']) ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress me-2" style="width: 100px; height: 20px;">
                                                            <div class="progress-bar" style="width: <?= $ticket['revenue_share'] ?>%"></div>
                                                        </div>
                                                        <span><?= number_format($ticket['revenue_share'], 1) ?>%</span>
                                                    </div>
                                                </td>
                                                <td><?= number_format($ticket['avg_tickets_per_order'], 1) ?></td>
                                                <td>
                                                    <?php
                                                    $performance = '';
                                                    if ($ticket['revenue_share'] >= 30) {
                                                        $performance = '<span class="badge bg-success">Excellent</span>';
                                                    } elseif ($ticket['revenue_share'] >= 20) {
                                                        $performance = '<span class="badge bg-primary">Good</span>';
                                                    } elseif ($ticket['revenue_share'] >= 10) {
                                                        $performance = '<span class="badge bg-warning">Average</span>';
                                                    } else {
                                                        $performance = '<span class="badge bg-danger">Needs Attention</span>';
                                                    }
                                                    echo $performance;
                                                    ?>
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

            <!-- Customer Lifetime Value -->
            <div class="row">
                <div class="col-12">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-crown me-2"></i>Top 10 Customer Lifetime Value
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($analytics['top_clv'])): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada data customer dengan transaksi</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($analytics['top_clv'] as $index => $customer): ?>
                                        <div class="col-lg-6 mb-3">
                                            <div class="d-flex align-items-center p-3 border rounded">
                                                <div class="me-3">
                                                    <span class="badge bg-<?= $index < 3 ? 'warning' : 'primary' ?> rounded-pill fs-6">
                                                        <?= $index + 1 ?>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?= htmlspecialchars($customer['nama_lengkap']) ?></h6>
                                                    <div class="row text-center">
                                                        <div class="col-4">
                                                            <small class="text-muted">CLV</small>
                                                            <div class="fw-bold text-success"><?= formatCurrency($customer['lifetime_value']) ?></div>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted">Bookings</small>
                                                            <div class="fw-bold"><?= $customer['total_bookings'] ?></div>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted">Days Active</small>
                                                            <div class="fw-bold"><?= $customer['customer_lifespan_days'] ?: 0 ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
    // Revenue & Customer Acquisition Chart
    const revenueData = <?= json_encode($analytics['revenue_trends']) ?>;
    const customerData = <?= json_encode($analytics['customer_acquisition']) ?>;

    // Merge data by month
    const monthlyData = {};
    revenueData.forEach(item => {
        monthlyData[item.month] = {
            ...item,
            new_customers: 0
        };
    });
    customerData.forEach(item => {
        if (monthlyData[item.month]) {
            monthlyData[item.month].new_customers = item.new_customers;
        }
    });

    const months = Object.keys(monthlyData).map(month => {
        const [year, monthNum] = month.split('-');
        const date = new Date(year, monthNum - 1);
        return date.toLocaleDateString('id-ID', {
            month: 'short',
            year: '2-digit'
        });
    });
    const revenues = Object.values(monthlyData).map(item => item.revenue);
    const newCustomers = Object.values(monthlyData).map(item => item.new_customers);

    const revenueCustomerCtx = document.getElementById('revenueCustomerChart').getContext('2d');
    new Chart(revenueCustomerCtx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Revenue (Rp)',
                data: revenues,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'New Customers',
                data: newCustomers,
                borderColor: '#764ba2',
                backgroundColor: 'rgba(118, 75, 162, 0.1)',
                borderWidth: 3,
                fill: false,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });

    // Seasonal Chart
    const seasonalData = <?= json_encode($analytics['seasonal']) ?>;
    const seasonalLabels = seasonalData.map(item => item.month_name);
    const seasonalRevenues = seasonalData.map(item => item.revenue);

    const seasonalCtx = document.getElementById('seasonalChart').getContext('2d');
    new Chart(seasonalCtx, {
        type: 'bar',
        data: {
            labels: seasonalLabels,
            datasets: [{
                label: 'Revenue',
                data: seasonalRevenues,
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
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });

    // Average Transaction Value Chart
    const atvData = <?= json_encode($analytics['avg_transaction_trends']) ?>;
    const atvLabels = atvData.map(item => {
        const [year, month] = item.month.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('id-ID', {
            month: 'short',
            year: '2-digit'
        });
    });
    const atvValues = atvData.map(item => item.avg_transaction_value);

    const atvCtx = document.getElementById('atvChart').getContext('2d');
    new Chart(atvCtx, {
        type: 'line',
        data: {
            labels: atvLabels,
            datasets: [{
                label: 'Average Transaction Value',
                data: atvValues,
                borderColor: '#764ba2',
                backgroundColor: 'rgba(118, 75, 162, 0.1)',
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
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>