<?php
// pelanggan/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('pelanggan');

$page_title = "Dashboard Pelanggan - Kolam Renang Licia";

// Get user's recent bookings
$recent_bookings = getBookingsByUser($_SESSION['user_id'], 5);

// Get user's statistics
$query = "SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status_pemesanan = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as total_spent
          FROM pemesanan WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user_stats = $stmt->fetch();

// Get next visit
$query = "SELECT p.*, jt.nama_tiket FROM pemesanan p
          JOIN jenis_tiket jt ON p.jenis_tiket_id = jt.id
          WHERE p.user_id = :user_id 
          AND p.tanggal_kunjungan >= CURDATE()
          AND p.status_pemesanan = 'confirmed'
          ORDER BY p.tanggal_kunjungan ASC, p.jam_kunjungan ASC
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$next_visit = $stmt->fetch();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h5 class="text-white">Dashboard Pelanggan</h5>
                    <small class="text-white-50">Selamat datang, <?= $_SESSION['nama_lengkap'] ?></small>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pesan_tiket.php">
                            <i class="fas fa-ticket-alt me-2"></i>Pesan Tiket
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history_pemesanan.php">
                            <i class="fas fa-history me-2"></i>History Pemesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-2"></i>Profil Saya
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
                    <a href="pesan_tiket.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Pesan Tiket Baru
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card primary">
                        <div class="icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <h3><?= $user_stats['total_bookings'] ?></h3>
                        <p class="text-muted">Total Booking</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card success">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?= $user_stats['confirmed_bookings'] ?></h3>
                        <p class="text-muted">Booking Dikonfirmasi</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card info">
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3><?= formatCurrency($user_stats['total_spent']) ?></h3>
                        <p class="text-muted">Total Pengeluaran</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="dashboard-card warning">
                        <div class="icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3><?= $next_visit ? '1' : '0' ?></h3>
                        <p class="text-muted">Kunjungan Mendatang</p>
                    </div>
                </div>
            </div>

            <!-- Next Visit -->
            <?php if ($next_visit): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card-custom">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-check me-2"></i>Kunjungan Mendatang
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1"><?= htmlspecialchars($next_visit['nama_tiket']) ?></h6>
                                        <p class="mb-1">
                                            <i class="fas fa-calendar me-2"></i>
                                            <?= formatDate($next_visit['tanggal_kunjungan']) ?>
                                            <i class="fas fa-clock ms-3 me-2"></i>
                                            <?= date('H:i', strtotime($next_visit['jam_kunjungan'])) ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-barcode me-2"></i>
                                            Kode Booking: <strong><?= htmlspecialchars($next_visit['kode_booking']) ?></strong>
                                        </p>
                                        <p class="mb-0">
                                            <i class="fas fa-users me-2"></i>
                                            <?= $next_visit['jumlah_tiket'] ?> tiket - <?= formatCurrency($next_visit['total_harga']) ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <span class="badge bg-success fs-6">Dikonfirmasi</span>
                                        <br><br>
                                        <a href="history_pemesanan.php?view=<?= $next_visit['kode_booking'] ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-2"></i>Lihat Detail
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="row mb-4">
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
                                    <a href="pesan_tiket.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-plus-circle mb-2 fa-2x"></i>
                                        <span>Pesan Tiket Baru</span>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="history_pemesanan.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-history mb-2 fa-2x"></i>
                                        <span>Lihat Riwayat</span>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="profile.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-user-edit mb-2 fa-2x"></i>
                                        <span>Edit Profil</span>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="../index.php#contact" class="btn btn-outline-warning w-100 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-phone mb-2 fa-2x"></i>
                                        <span>Hubungi Kami</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="row">
                <div class="col-12">
                    <div class="card-custom">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Pemesanan Terbaru
                            </h5>
                            <a href="history_pemesanan.php" class="btn btn-sm btn-outline-primary">
                                Lihat Semua
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_bookings)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-ticket-alt fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum Ada Pemesanan</h5>
                                    <p class="text-muted">Mulai pesan tiket untuk menikmati fasilitas kolam renang kami</p>
                                    <a href="pesan_tiket.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Pesan Tiket Sekarang
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kode Booking</th>
                                                <th>Tiket</th>
                                                <th>Tanggal Kunjungan</th>
                                                <th>Total</th>
                                                <th>Status Pemesanan</th>
                                                <th>Status Pembayaran</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_bookings as $booking): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($booking['kode_booking']) ?></strong>
                                                        <br><small class="text-muted"><?= formatDateTime($booking['created_at']) ?></small>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($booking['nama_tiket']) ?>
                                                        <br><small class="text-muted"><?= $booking['jumlah_tiket'] ?> tiket</small>
                                                    </td>
                                                    <td>
                                                        <?= formatDate($booking['tanggal_kunjungan']) ?>
                                                        <br><small class="text-muted"><?= date('H:i', strtotime($booking['jam_kunjungan'])) ?></small>
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
                                                        <?php
                                                        $payment_class = '';
                                                        $payment_text = '';
                                                        switch ($booking['status_pembayaran']) {
                                                            case 'unpaid':
                                                                $payment_class = 'danger';
                                                                $payment_text = 'Belum Bayar';
                                                                break;
                                                            case 'paid':
                                                                $payment_class = 'success';
                                                                $payment_text = 'Sudah Bayar';
                                                                break;
                                                            case 'refunded':
                                                                $payment_class = 'info';
                                                                $payment_text = 'Refund';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?= $payment_class ?>"><?= $payment_text ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="history_pemesanan.php?view=<?= $booking['kode_booking'] ?>"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($booking['status_pembayaran'] === 'unpaid' && $booking['status_pemesanan'] !== 'cancelled'): ?>
                                                            <a href="upload_pembayaran.php?booking=<?= $booking['kode_booking'] ?>"
                                                                class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-upload"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
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

<?php include '../includes/footer.php'; ?>