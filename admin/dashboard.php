<?php
// admin/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$page_title = "Dashboard Admin - Kolam Renang Licia";
$stats = getDashboardStats();

// Get recent bookings
$query = "SELECT p.*, jt.nama_tiket, u.nama_lengkap 
          FROM pemesanan p
          JOIN jenis_tiket jt ON p.jenis_tiket_id = jt.id
          JOIN users u ON p.user_id = u.id
          ORDER BY p.created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_bookings = $stmt->fetchAll();

// Get pending payments
$query = "SELECT COUNT(*) as count FROM pembayaran WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_payments = $stmt->fetch()['count'];

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h5 class="text-white">Admin Panel</h5>
                    <small class="text-white-50">Selamat datang, <?= $_SESSION['nama_lengkap'] ?></small>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kelola_tiket.php">
                            <i class="fas fa-ticket-alt me-2"></i>Kelola Tiket
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kelola_user.php">
                            <i class="fas fa-users me-2"></i>Kelola User
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kelola_pemesanan.php">
                            <i class="fas fa-clipboard-list me-2"></i>Kelola Pemesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="verifikasi_pembayaran.php">
                            <i class="fas fa-credit-card me-2"></i>Verifikasi Pembayaran
                            <?php if ($pending_payments > 0): ?>
                                <span class="badge bg-danger"><?= $pending_payments ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pengaturan.php">
                            <i class="fas fa-cog me-2"></i>Pengaturan
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
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-calendar-alt me-1"></i><?= date('d F Y') ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card primary">
                        <div class="icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <h3><?= $stats['bookings_today'] ?></h3>
                        <p class="text-muted">Booking Hari Ini</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card success">
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3><?= formatCurrency($stats['revenue_month']) ?></h3>
                        <p class="text-muted">Pendapatan Bulan Ini</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card warning">
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?= $stats['pending_payments'] ?></h3>
                        <p class="text-muted">Pembayaran Pending</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card info">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?= $stats['total_customers'] ?></h3>
                        <p class="text-muted">Total Pelanggan</p>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="row">
                <div class="col-12">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Pemesanan Terbaru
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_bookings)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada pemesanan</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kode Booking</th>
                                                <th>Pelanggan</th>
                                                <th>Tiket</th>
                                                <th>Tanggal Kunjungan</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_bookings as $booking): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($booking['kode_booking']) ?></strong>
                                                    </td>
                                                    <td><?= htmlspecialchars($booking['nama_lengkap']) ?></td>
                                                    <td><?= htmlspecialchars($booking['nama_tiket']) ?></td>
                                                    <td>
                                                        <?= formatDate($booking['tanggal_kunjungan']) ?>
                                                        <br>
                                                        <small class="text-muted"><?= date('H:i', strtotime($booking['jam_kunjungan'])) ?></small>
                                                    </td>
                                                    <td><?= formatCurrency($booking['total_harga']) ?></td>
                                                    <td>
                                                        <?php
                                                        $status_class = '';
                                                        $status_text = '';
                                                        switch ($booking['status_pemesanan']) {
                                                            case 'pending':
                                                                $status_class = 'warning';
                                                                $status_text = 'Pending';
                                                                break;
                                                            case 'confirmed':
                                                                $status_class = 'success';
                                                                $status_text = 'Dikonfirmasi';
                                                                break;
                                                            case 'cancelled':
                                                                $status_class = 'danger';
                                                                $status_text = 'Dibatalkan';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="kelola_pemesanan.php?view=<?= $booking['id'] ?>"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center">
                                    <a href="kelola_pemesanan.php" class="btn btn-primary">
                                        <i class="fas fa-list me-2"></i>Lihat Semua Pemesanan
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-bolt me-2"></i>Aksi Cepat
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="kelola_tiket.php?action=add" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-plus-circle mb-2"></i><br>
                                        Tambah Tiket Baru
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="kelola_user.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-user-plus mb-2"></i><br>
                                        Kelola User
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="verifikasi_pembayaran.php" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-credit-card mb-2"></i><br>
                                        Verifikasi Pembayaran
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="pengaturan.php" class="btn btn-outline-info w-100">
                                        <i class="fas fa-cog mb-2"></i><br>
                                        Pengaturan Sistem
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>