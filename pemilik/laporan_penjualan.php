<?php
// pemilik/laporan_penjualan.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('pemilik');

$page_title = "Laporan Penjualan - Dashboard Pemilik";

// Date filters
$period = $_GET['period'] ?? 'month';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

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

// Get comprehensive sales data
function getSalesReport($db, $start_date, $end_date)
{
    $report = [];

    // Overall sales summary
    $query = "SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as gross_revenue,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN 1 ELSE 0 END) as paid_transactions,
                SUM(CASE WHEN status_pembayaran = 'unpaid' THEN 1 ELSE 0 END) as unpaid_transactions,
                SUM(CASE WHEN status_pembayaran = 'refunded' THEN total_harga ELSE 0 END) as refunded_amount,
                SUM(jumlah_tiket) as total_tickets,
                AVG(CASE WHEN status_pembayaran = 'paid' THEN total_harga END) as avg_transaction_value,
                COUNT(DISTINCT user_id) as unique_customers
              FROM pemesanan 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $report['summary'] = $stmt->fetch();

    // Net revenue (gross - refunds)
    $report['summary']['net_revenue'] = $report['summary']['gross_revenue'] - $report['summary']['refunded_amount'];

    // Sales by ticket type
    $query = "SELECT 
                jt.nama_tiket,
                jt.harga,
                COUNT(p.id) as orders_count,
                SUM(p.jumlah_tiket) as tickets_sold,
                SUM(CASE WHEN p.status_pembayaran = 'paid' THEN p.total_harga ELSE 0 END) as revenue,
                AVG(p.jumlah_tiket) as avg_tickets_per_order
              FROM jenis_tiket jt
              LEFT JOIN pemesanan p ON jt.id = p.jenis_tiket_id 
                AND DATE(p.created_at) BETWEEN :start_date AND :end_date
              GROUP BY jt.id
              ORDER BY revenue DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $report['by_ticket'] = $stmt->fetchAll();

    // Sales by payment method
    $query = "SELECT 
                metode_pembayaran,
                COUNT(*) as transactions,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as revenue,
                AVG(CASE WHEN status_pembayaran = 'paid' THEN total_harga END) as avg_value
              FROM pemesanan 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date
              GROUP BY metode_pembayaran
              ORDER BY revenue DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $report['by_payment'] = $stmt->fetchAll();

    // Daily sales breakdown
    $query = "SELECT 
                DATE(created_at) as sale_date,
                COUNT(*) as transactions,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as daily_revenue,
                SUM(jumlah_tiket) as tickets_sold,
                COUNT(DISTINCT user_id) as unique_customers
              FROM pemesanan 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date
              GROUP BY DATE(created_at)
              ORDER BY sale_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $report['daily'] = $stmt->fetchAll();

    // Top customers
    $query = "SELECT 
                u.nama_lengkap,
                u.email,
                COUNT(p.id) as total_orders,
                SUM(CASE WHEN p.status_pembayaran = 'paid' THEN p.total_harga ELSE 0 END) as total_spent,
                SUM(p.jumlah_tiket) as total_tickets
              FROM users u
              JOIN pemesanan p ON u.id = p.user_id
              WHERE DATE(p.created_at) BETWEEN :start_date AND :end_date
                AND p.status_pembayaran = 'paid'
              GROUP BY u.id
              HAVING total_spent > 0
              ORDER BY total_spent DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    $report['top_customers'] = $stmt->fetchAll();

    // Monthly comparison (last 12 months)
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as transactions,
                SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as revenue
              FROM pemesanan 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $report['monthly_trend'] = $stmt->fetchAll();

    return $report;
}

$sales_report = getSalesReport($db, $start_date, $end_date);

// Export functionality
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];

    if ($export_type === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="laporan_penjualan_' . date('Y-m-d') . '.xls"');

        echo "Laporan Penjualan\n";
        echo "Periode: " . formatDate($start_date) . " - " . formatDate($end_date) . "\n\n";

        echo "RINGKASAN\n";
        echo "Total Transaksi\t" . $sales_report['summary']['total_transactions'] . "\n";
        echo "Revenue Kotor\t" . $sales_report['summary']['gross_revenue'] . "\n";
        echo "Revenue Bersih\t" . $sales_report['summary']['net_revenue'] . "\n";
        echo "Rata-rata Transaksi\t" . $sales_report['summary']['avg_transaction_value'] . "\n\n";

        echo "PENJUALAN HARIAN\n";
        echo "Tanggal\tTransaksi\tRevenue\tTiket Terjual\n";
        foreach ($sales_report['daily'] as $daily) {
            echo $daily['sale_date'] . "\t" . $daily['transactions'] . "\t" . $daily['daily_revenue'] . "\t" . $daily['tickets_sold'] . "\n";
        }

        exit();
    }
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
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="laporan_penjualan.php">
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
                <h1 class="h2">Laporan Penjualan</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="?export=excel&period=<?= $period ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>"
                            class="btn btn-outline-success">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </a>
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>
            </div>

            <!-- Period Filter -->
            <div class="card-custom mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Periode</label>
                            <select class="form-select" name="period" onchange="this.form.submit()">
                                <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Hari Ini</option>
                                <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Minggu Ini</option>
                                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Bulan Ini</option>
                                <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Tahun Ini</option>
                                <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Dari Tanggal</label>
                            <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sampai Tanggal</label>
                            <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card success">
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3><?= formatCurrency($sales_report['summary']['net_revenue']) ?></h3>
                        <p class="text-muted">Revenue Bersih</p>
                        <small class="text-muted">Kotor: <?= formatCurrency($sales_report['summary']['gross_revenue']) ?></small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card primary">
                        <div class="icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h3><?= $sales_report['summary']['total_transactions'] ?></h3>
                        <p class="text-muted">Total Transaksi</p>
                        <small class="text-muted"><?= $sales_report['summary']['paid_transactions'] ?> lunas</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card info">
                        <div class="icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <h3><?= $sales_report['summary']['total_tickets'] ?></h3>
                        <p class="text-muted">Tiket Terjual</p>
                        <small class="text-muted">Total periode</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card warning">
                        <div class="icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <h3><?= formatCurrency($sales_report['summary']['avg_transaction_value']) ?></h3>
                        <p class="text-muted">Rata-rata Transaksi</p>
                        <small class="text-muted">Per pembayaran</small>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- Revenue Trend -->
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>Tren Revenue Bulanan (12 Bulan Terakhir)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendChart" height="400"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card me-2"></i>Metode Pembayaran
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="paymentMethodChart" height="400"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Tables -->
            <div class="row mb-4">
                <!-- Sales by Ticket Type -->
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-ticket-alt me-2"></i>Penjualan per Jenis Tiket
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Jenis Tiket</th>
                                            <th>Harga</th>
                                            <th>Order</th>
                                            <th>Tiket Terjual</th>
                                            <th>Revenue</th>
                                            <th>Avg Tiket/Order</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sales_report['by_ticket'] as $ticket): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($ticket['nama_tiket']) ?></strong></td>
                                                <td><?= formatCurrency($ticket['harga']) ?></td>
                                                <td><?= $ticket['orders_count'] ?></td>
                                                <td><?= $ticket['tickets_sold'] ?></td>
                                                <td class="fw-bold"><?= formatCurrency($ticket['revenue']) ?></td>
                                                <td><?= number_format($ticket['avg_tickets_per_order'], 1) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Customers -->
                <div class="col-lg-4">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-crown me-2"></i>Top Customers
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($sales_report['top_customers'])): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada data customer</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($sales_report['top_customers'] as $index => $customer): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <span class="badge bg-primary rounded-pill"><?= $index + 1 ?></span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($customer['nama_lengkap']) ?></h6>
                                            <small class="text-muted"><?= $customer['total_orders'] ?> orders • <?= $customer['total_tickets'] ?> tiket</small>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-success"><?= formatCurrency($customer['total_spent']) ?></strong>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Sales Breakdown -->
            <div class="row">
                <div class="col-12">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>Breakdown Penjualan Harian
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($sales_report['daily'])): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Tidak ada data penjualan untuk periode ini</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Hari</th>
                                                <th>Transaksi</th>
                                                <th>Tiket Terjual</th>
                                                <th>Unique Customers</th>
                                                <th>Revenue</th>
                                                <th>Avg/Transaksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sales_report['daily'] as $daily): ?>
                                                <tr>
                                                    <td><?= formatDate($daily['sale_date']) ?></td>
                                                    <td>
                                                        <?php
                                                        $day_name = date('l', strtotime($daily['sale_date']));
                                                        $indonesian_days = [
                                                            'Monday' => 'Senin',
                                                            'Tuesday' => 'Selasa',
                                                            'Wednesday' => 'Rabu',
                                                            'Thursday' => 'Kamis',
                                                            'Friday' => 'Jumat',
                                                            'Saturday' => 'Sabtu',
                                                            'Sunday' => 'Minggu'
                                                        ];
                                                        echo $indonesian_days[$day_name];
                                                        ?>
                                                    </td>
                                                    <td><?= $daily['transactions'] ?></td>
                                                    <td><?= $daily['tickets_sold'] ?></td>
                                                    <td><?= $daily['unique_customers'] ?></td>
                                                    <td class="fw-bold"><?= formatCurrency($daily['daily_revenue']) ?></td>
                                                    <td><?= $daily['transactions'] > 0 ? formatCurrency($daily['daily_revenue'] / $daily['transactions']) : '-' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-dark">
                                                <th colspan="2">TOTAL</th>
                                                <th><?= array_sum(array_column($sales_report['daily'], 'transactions')) ?></th>
                                                <th><?= array_sum(array_column($sales_report['daily'], 'tickets_sold')) ?></th>
                                                <th>-</th>
                                                <th><?= formatCurrency(array_sum(array_column($sales_report['daily'], 'daily_revenue'))) ?></th>
                                                <th>-</th>
                                            </tr>
                                        </tfoot>
                                    </table>
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
    // Monthly Trend Chart
    const monthlyData = <?= json_encode($sales_report['monthly_trend']) ?>;
    const monthLabels = monthlyData.map(item => {
        const [year, month] = item.month.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('id-ID', {
            month: 'short',
            year: '2-digit'
        });
    }).reverse();
    const monthlyRevenue = monthlyData.map(item => item.revenue).reverse();

    const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Revenue (Rp)',
                data: monthlyRevenue,
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
            }
        }
    });

    // Payment Method Chart
    const paymentData = <?= json_encode($sales_report['by_payment']) ?>;
    const paymentLabels = paymentData.map(item => {
        const methods = {
            'transfer': 'Transfer Bank',
            'e_wallet': 'E-Wallet',
            'cash': 'Cash'
        };
        return methods[item.metode_pembayaran] || item.metode_pembayaran;
    });
    const paymentValues = paymentData.map(item => item.revenue);

    const paymentCtx = document.getElementById('paymentMethodChart').getContext('2d');
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: paymentLabels,
            datasets: [{
                data: paymentValues,
                backgroundColor: [
                    '#667eea',
                    '#764ba2',
                    '#f093fb'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>