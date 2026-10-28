<?php
// admin/kelola_pemesanan.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$page_title = "Kelola Pemesanan - Admin";

// Handle actions
$action = $_GET['action'] ?? 'list';
$booking_id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $id = $_POST['id'];
                $status = sanitize($_POST['status']);

                $query = "UPDATE pemesanan SET status_pemesanan = :status WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $id);

                if ($stmt->execute()) {
                    setAlert('success', 'Status pemesanan berhasil diupdate');
                } else {
                    setAlert('danger', 'Gagal mengupdate status pemesanan');
                }
                header('Location: kelola_pemesanan.php');
                exit();
                break;

            case 'update_payment_status':
                $id = $_POST['id'];
                $status = sanitize($_POST['payment_status']);

                $query = "UPDATE pemesanan SET status_pembayaran = :status WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $id);

                if ($stmt->execute()) {
                    setAlert('success', 'Status pembayaran berhasil diupdate');
                } else {
                    setAlert('danger', 'Gagal mengupdate status pembayaran');
                }
                header('Location: kelola_pemesanan.php');
                exit();
                break;
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT p.*, jt.nama_tiket, jt.harga, u.nama_lengkap, u.email, u.no_telepon
          FROM pemesanan p
          JOIN jenis_tiket jt ON p.jenis_tiket_id = jt.id
          JOIN users u ON p.user_id = u.id
          WHERE 1=1";
$params = [];

if ($status_filter) {
    $query .= " AND p.status_pemesanan = :status";
    $params['status'] = $status_filter;
}

if ($payment_filter) {
    $query .= " AND p.status_pembayaran = :payment";
    $params['payment'] = $payment_filter;
}

if ($date_filter) {
    $query .= " AND DATE(p.tanggal_kunjungan) = :date";
    $params['date'] = $date_filter;
}

if ($search) {
    $query .= " AND (p.kode_booking LIKE :search OR u.nama_lengkap LIKE :search OR u.email LIKE :search)";
    $params['search'] = "%$search%";
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindParam(":$key", $value);
}
$stmt->execute();
$bookings = $stmt->fetchAll();

// Get booking detail if viewing
$booking_detail = null;
if ($action === 'view' && $booking_id) {
    $booking_detail = getBookingByCode($booking_id) ?: (function () use ($db, $booking_id) {
            $query = "SELECT p.*, jt.nama_tiket, jt.harga, u.nama_lengkap, u.email, u.no_telepon
                                  FROM pemesanan p
                                  JOIN jenis_tiket jt ON p.jenis_tiket_id = jt.id
                                  JOIN users u ON p.user_id = u.id
                                  WHERE p.id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $booking_id);
            $stmt->execute();
            return $stmt->fetch();
        })();

    // Get payment info
    $payment_info = null;
    if ($booking_detail) {
        $payment_info = getPaymentByBookingId($booking_detail['id']);
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
                    <h5 class="text-white">Admin Panel</h5>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
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
                        <a class="nav-link active" href="kelola_pemesanan.php">
                            <i class="fas fa-clipboard-list me-2"></i>Kelola Pemesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="verifikasi_pembayaran.php">
                            <i class="fas fa-credit-card me-2"></i>Verifikasi Pembayaran
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
                <h1 class="h2">Kelola Pemesanan</h1>
                <?php if ($action === 'view'): ?>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="kelola_pemesanan.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($action === 'view' && $booking_detail): ?>
                <!-- Booking Detail -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card-custom mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-ticket-alt me-2"></i>Detail Pemesanan
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Kode Booking:</strong></td>
                                                <td><?= htmlspecialchars($booking_detail['kode_booking']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Pelanggan:</strong></td>
                                                <td><?= htmlspecialchars($booking_detail['nama_lengkap']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td><?= htmlspecialchars($booking_detail['email']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>No. Telepon:</strong></td>
                                                <td><?= htmlspecialchars($booking_detail['no_telepon']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Tiket:</strong></td>
                                                <td><?= htmlspecialchars($booking_detail['nama_tiket']) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Tanggal Kunjungan:</strong></td>
                                                <td><?= formatDate($booking_detail['tanggal_kunjungan']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Jam Kunjungan:</strong></td>
                                                <td><?= date('H:i', strtotime($booking_detail['jam_kunjungan'])) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Jumlah Tiket:</strong></td>
                                                <td><?= $booking_detail['jumlah_tiket'] ?> tiket</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Harga:</strong></td>
                                                <td><?= formatCurrency($booking_detail['total_harga']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Metode Pembayaran:</strong></td>
                                                <td><?= ucfirst($booking_detail['metode_pembayaran']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Tanggal Pesan:</strong></td>
                                                <td><?= formatDateTime($booking_detail['created_at']) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <?php if ($booking_detail['catatan']): ?>
                                    <div class="mt-3">
                                        <strong>Catatan:</strong>
                                        <p class="mt-2"><?= nl2br(htmlspecialchars($booking_detail['catatan'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Status Management -->
                        <div class="card-custom mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cog me-2"></i>Kelola Status
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Booking Status -->
                                <form method="POST" class="mb-3">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?= $booking_detail['id'] ?>">
                                    <label class="form-label">Status Pemesanan:</label>
                                    <div class="input-group">
                                        <select class="form-select" name="status" required>
                                            <option value="pending" <?= $booking_detail['status_pemesanan'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="confirmed" <?= $booking_detail['status_pemesanan'] === 'confirmed' ? 'selected' : '' ?>>Dikonfirmasi</option>
                                            <option value="cancelled" <?= $booking_detail['status_pemesanan'] === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </div>
                                </form>

                                <!-- Payment Status -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_payment_status">
                                    <input type="hidden" name="id" value="<?= $booking_detail['id'] ?>">
                                    <label class="form-label">Status Pembayaran:</label>
                                    <div class="input-group">
                                        <select class="form-select" name="payment_status" required>
                                            <option value="unpaid" <?= $booking_detail['status_pembayaran'] === 'unpaid' ? 'selected' : '' ?>>Belum Bayar</option>
                                            <option value="paid" <?= $booking_detail['status_pembayaran'] === 'paid' ? 'selected' : '' ?>>Sudah Bayar</option>
                                            <option value="refunded" <?= $booking_detail['status_pembayaran'] === 'refunded' ? 'selected' : '' ?>>Refund</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Current Status -->
                        <div class="card-custom">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Status Saat Ini
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Pemesanan:</strong>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch ($booking_detail['status_pemesanan']) {
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
                                    <br><span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                </div>

                                <div>
                                    <strong>Pembayaran:</strong>
                                    <?php
                                    $payment_class = '';
                                    $payment_text = '';
                                    switch ($booking_detail['status_pembayaran']) {
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
                                    <br><span class="badge bg-<?= $payment_class ?>"><?= $payment_text ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Filter -->
                <div class="card-custom mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status Pemesanan</label>
                                <select class="form-select" name="status" id="status">
                                    <option value="">Semua</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Dikonfirmasi</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="payment" class="form-label">Status Pembayaran</label>
                                <select class="form-select" name="payment" id="payment">
                                    <option value="">Semua</option>
                                    <option value="unpaid" <?= $payment_filter === 'unpaid' ? 'selected' : '' ?>>Belum Bayar</option>
                                    <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Sudah Bayar</option>
                                    <option value="refunded" <?= $payment_filter === 'refunded' ? 'selected' : '' ?>>Refund</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Tanggal Kunjungan</label>
                                <input type="date" class="form-control" name="date" id="date" value="<?= htmlspecialchars($date_filter) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cari</label>
                                <input type="text" class="form-control" name="search" id="search"
                                    placeholder="Kode booking, nama, email" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bookings List -->
                <div class="card-custom">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Daftar Pemesanan (<?= count($bookings) ?>)
                        </h5>
                        <?php if ($status_filter || $payment_filter || $date_filter || $search): ?>
                            <a href="kelola_pemesanan.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Reset Filter
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bookings)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Tidak ada pemesanan ditemukan</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kode Booking</th>
                                            <th>Pelanggan</th>
                                            <th>Tiket</th>
                                            <th>Tanggal & Jam</th>
                                            <th>Total</th>
                                            <th>Status Pemesanan</th>
                                            <th>Status Pembayaran</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($booking['kode_booking']) ?></strong>
                                                    <br><small class="text-muted"><?= formatDateTime($booking['created_at']) ?></small>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($booking['nama_lengkap']) ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($booking['email']) ?></small>
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
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?action=view&id=<?= $booking['id'] ?>"
                                                            class="btn btn-outline-primary" title="Lihat Detail">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-success"
                                                            onclick="quickUpdateStatus(<?= $booking['id'] ?>, 'confirmed')"
                                                            title="Konfirmasi" <?= $booking['status_pemesanan'] === 'confirmed' ? 'disabled' : '' ?>>
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger"
                                                            onclick="quickUpdateStatus(<?= $booking['id'] ?>, 'cancelled')"
                                                            title="Batalkan" <?= $booking['status_pemesanan'] === 'cancelled' ? 'disabled' : '' ?>>
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Quick Update Form -->
<form id="quickUpdateForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="id" id="quickUpdateId">
    <input type="hidden" name="status" id="quickUpdateStatus">
</form>

<script>
    function quickUpdateStatus(id, status) {
        const statusText = status === 'confirmed' ? 'mengkonfirmasi' : 'membatalkan';
        if (confirm(`Apakah Anda yakin ingin ${statusText} pemesanan ini?`)) {
            document.getElementById('quickUpdateId').value = id;
            document.getElementById('quickUpdateStatus').value = status;
            document.getElementById('quickUpdateForm').submit();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>