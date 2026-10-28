<?php
// admin/verifikasi_pembayaran.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$page_title = "Verifikasi Pembayaran - Admin";

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'verify':
                $payment_id = $_POST['payment_id'];
                $booking_id = $_POST['booking_id'];
                $status = sanitize($_POST['status']);
                $catatan = sanitize($_POST['catatan']);

                // Update payment status
                $query = "UPDATE pembayaran SET status = :status, verified_by = :admin_id, 
                         verified_at = NOW(), catatan = :catatan WHERE id = :payment_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $stmt->bindParam(':catatan', $catatan);
                $stmt->bindParam(':payment_id', $payment_id);

                if ($stmt->execute()) {
                    // Update booking payment status if verified
                    if ($status === 'verified') {
                        $query = "UPDATE pemesanan SET status_pembayaran = 'paid' WHERE id = :booking_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':booking_id', $booking_id);
                        $stmt->execute();
                    }

                    setAlert('success', 'Pembayaran berhasil diverifikasi');
                } else {
                    setAlert('danger', 'Gagal memverifikasi pembayaran');
                }
                break;
        }
    }
}

// Get pending payments
$query = "SELECT pb.*, p.kode_booking, p.total_harga, p.tanggal_kunjungan, p.jam_kunjungan,
                 jt.nama_tiket, u.nama_lengkap, u.email, u.no_telepon
          FROM pembayaran pb
          JOIN pemesanan p ON pb.pemesanan_id = p.id
          JOIN jenis_tiket jt ON p.jenis_tiket_id = jt.id
          JOIN users u ON p.user_id = u.id
          WHERE pb.status = 'pending'
          ORDER BY pb.tanggal_bayar DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_payments = $stmt->fetchAll();

// Get all payments for history
$query = "SELECT pb.*, p.kode_booking, p.total_harga, jt.nama_tiket, u.nama_lengkap,
                 admin.nama_lengkap as verified_by_name
          FROM pembayaran pb
          JOIN pemesanan p ON pb.pemesanan_id = p.id
          JOIN jenis_tiket jt ON p.jenis_tiket_id = jt.id
          JOIN users u ON p.user_id = u.id
          LEFT JOIN users admin ON pb.verified_by = admin.id
          WHERE pb.status != 'pending'
          ORDER BY pb.verified_at DESC
          LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute();
$payment_history = $stmt->fetchAll();

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
                        <a class="nav-link" href="kelola_pemesanan.php">
                            <i class="fas fa-clipboard-list me-2"></i>Kelola Pemesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="verifikasi_pembayaran.php">
                            <i class="fas fa-credit-card me-2"></i>Verifikasi Pembayaran
                            <?php if (count($pending_payments) > 0): ?>
                                <span class="badge bg-danger"><?= count($pending_payments) ?></span>
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
                <h1 class="h2">Verifikasi Pembayaran</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="badge bg-warning fs-6">
                        <?= count($pending_payments) ?> Pembayaran Pending
                    </span>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="card-custom mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>Pembayaran Menunggu Verifikasi
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_payments)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="text-muted">Semua pembayaran sudah diverifikasi</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($pending_payments as $payment): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning text-dark">
                                            <strong>Booking: <?= htmlspecialchars($payment['kode_booking']) ?></strong>
                                            <span class="float-end"><?= formatDateTime($payment['tanggal_bayar']) ?></span>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>Pelanggan:</strong><br><?= htmlspecialchars($payment['nama_lengkap']) ?></p>
                                                    <p><strong>Email:</strong><br><?= htmlspecialchars($payment['email']) ?></p>
                                                    <p><strong>Tiket:</strong><br><?= htmlspecialchars($payment['nama_tiket']) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Tanggal Kunjungan:</strong><br><?= formatDate($payment['tanggal_kunjungan']) ?> <?= date('H:i', strtotime($payment['jam_kunjungan'])) ?></p>
                                                    <p><strong>Total Bayar:</strong><br><?= formatCurrency($payment['jumlah_bayar']) ?></p>
                                                    <p><strong>Metode:</strong><br><?= ucfirst($payment['metode_pembayaran']) ?></p>
                                                </div>
                                            </div>

                                            <?php if ($payment['bukti_pembayaran']): ?>
                                                <div class="mb-3">
                                                    <strong>Bukti Pembayaran:</strong><br>
                                                    <img src="../uploads/bukti_pembayaran/<?= htmlspecialchars($payment['bukti_pembayaran']) ?>"
                                                        class="img-fluid mt-2" style="max-height: 200px; cursor: pointer;"
                                                        onclick="showImageModal(this.src)">
                                                </div>
                                            <?php endif; ?>

                                            <form method="POST" class="mt-3">
                                                <input type="hidden" name="action" value="verify">
                                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                <input type="hidden" name="booking_id" value="<?= $payment['pemesanan_id'] ?>">

                                                <div class="mb-3">
                                                    <label class="form-label">Status Verifikasi:</label>
                                                    <div class="btn-group w-100" role="group">
                                                        <input type="radio" class="btn-check" name="status" value="verified" id="verify_<?= $payment['id'] ?>" required>
                                                        <label class="btn btn-outline-success" for="verify_<?= $payment['id'] ?>">
                                                            <i class="fas fa-check me-1"></i>Terima
                                                        </label>

                                                        <input type="radio" class="btn-check" name="status" value="rejected" id="reject_<?= $payment['id'] ?>" required>
                                                        <label class="btn btn-outline-danger" for="reject_<?= $payment['id'] ?>">
                                                            <i class="fas fa-times me-1"></i>Tolak
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Catatan:</label>
                                                    <textarea class="form-control" name="catatan" rows="2" placeholder="Catatan verifikasi (opsional)"></textarea>
                                                </div>

                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="fas fa-check-circle me-2"></i>Verifikasi Pembayaran
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Riwayat Verifikasi Pembayaran
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($payment_history)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada riwayat verifikasi</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Booking</th>
                                        <th>Pelanggan</th>
                                        <th>Tiket</th>
                                        <th>Jumlah</th>
                                        <th>Status</th>
                                        <th>Diverifikasi</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_history as $payment): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($payment['kode_booking']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($payment['nama_lengkap']) ?></td>
                                            <td><?= htmlspecialchars($payment['nama_tiket']) ?></td>
                                            <td><?= formatCurrency($payment['jumlah_bayar']) ?></td>
                                            <td>
                                                <?php if ($payment['status'] === 'verified'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>Diterima
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times me-1"></i>Ditolak
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($payment['verified_by_name'] ?? 'System') ?>
                                            </td>
                                            <td><?= formatDateTime($payment['verified_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bukti Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<script>
    function showImageModal(src) {
        document.getElementById('modalImage').src = src;
        new bootstrap.Modal(document.getElementById('imageModal')).show();
    }
</script>

<?php include '../includes/footer.php'; ?>