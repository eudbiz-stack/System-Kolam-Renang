<?php
// pelanggan/upload_pembayaran.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('pelanggan');

$page_title = "Upload Bukti Pembayaran - Kolam Renang Licia";

$booking_code = $_GET['booking'] ?? null;
if (!$booking_code) {
    header('Location: history_pemesanan.php');
    exit();
}

// Get booking details
$booking = getBookingByCode($booking_code);
if (!$booking || $booking['user_id'] != $_SESSION['user_id']) {
    setAlert('danger', 'Pemesanan tidak ditemukan');
    header('Location: history_pemesanan.php');
    exit();
}

// Check if payment already exists
$existing_payment = getPaymentByBookingId($booking['id']);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metode_pembayaran = sanitize($_POST['metode_pembayaran']);
    $catatan = sanitize($_POST['catatan']);

    $error = '';

    // Validate payment method
    if (!in_array($metode_pembayaran, ['transfer', 'e_wallet'])) {
        $error = 'Metode pembayaran tidak valid';
    }

    // Handle file upload
    $bukti_filename = null;
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/bukti_pembayaran';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $upload_result = uploadFile($_FILES['bukti_pembayaran'], $upload_dir, ['jpg', 'jpeg', 'png', 'pdf']);

        if ($upload_result['success']) {
            $bukti_filename = $upload_result['filename'];
        } else {
            $error = $upload_result['message'];
        }
    } else {
        $error = 'Bukti pembayaran harus diupload';
    }

    if (empty($error)) {
        if ($existing_payment) {
            // Update existing payment
            $query = "UPDATE pembayaran SET jumlah_bayar = :jumlah, metode_pembayaran = :metode, 
                     bukti_pembayaran = :bukti, catatan = :catatan, status = 'pending', 
                     tanggal_bayar = NOW() WHERE pemesanan_id = :booking_id";
        } else {
            // Insert new payment
            $query = "INSERT INTO pembayaran (pemesanan_id, jumlah_bayar, metode_pembayaran, 
                     bukti_pembayaran, catatan, status) 
                     VALUES (:booking_id, :jumlah, :metode, :bukti, :catatan, 'pending')";
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(':booking_id', $booking['id']);
        $stmt->bindParam(':jumlah', $booking['total_harga']);
        $stmt->bindParam(':metode', $metode_pembayaran);
        $stmt->bindParam(':bukti', $bukti_filename);
        $stmt->bindParam(':catatan', $catatan);

        if ($stmt->execute()) {
            setAlert('success', 'Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.');
            header('Location: history_pemesanan.php?view=' . $booking_code);
            exit();
        } else {
            $error = 'Gagal menyimpan bukti pembayaran';
        }
    }

    if ($error) {
        setAlert('danger', $error);
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
                    <h5 class="text-white">Dashboard Pelanggan</h5>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
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
                <h1 class="h2">Upload Bukti Pembayaran</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="history_pemesanan.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Booking Summary -->
                <div class="col-lg-4">
                    <div class="card-custom mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-ticket-alt me-2"></i>Detail Pemesanan
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <h4 class="text-primary"><?= htmlspecialchars($booking['kode_booking']) ?></h4>
                                <span class="badge bg-warning">Menunggu Pembayaran</span>
                            </div>

                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td><strong>Tiket:</strong></td>
                                    <td><?= htmlspecialchars($booking['nama_tiket']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Tanggal:</strong></td>
                                    <td><?= formatDate($booking['tanggal_kunjungan']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Jam:</strong></td>
                                    <td><?= date('H:i', strtotime($booking['jam_kunjungan'])) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Jumlah:</strong></td>
                                    <td><?= $booking['jumlah_tiket'] ?> tiket</td>
                                </tr>
                                <tr>
                                    <td><strong>Total:</strong></td>
                                    <td class="fw-bold text-primary"><?= formatCurrency($booking['total_harga']) ?></td>
                                </tr>
                            </table>

                            <?php if ($booking['catatan']): ?>
                                <div class="mt-3">
                                    <strong>Catatan:</strong>
                                    <p class="text-muted"><?= nl2br(htmlspecialchars($booking['catatan'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-university me-2"></i>Informasi Rekening
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Transfer Bank:</strong>
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded mb-2">
                                        <div>
                                            <strong>Bank BCA</strong><br>
                                            <small>1234567890</small><br>
                                            <small>a.n. Kolam Renang Licia</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('1234567890')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                        <div>
                                            <strong>Bank Mandiri</strong><br>
                                            <small>0987654321</small><br>
                                            <small>a.n. Kolam Renang Licia</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('0987654321')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <strong>E-Wallet:</strong>
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded mb-2">
                                        <div>
                                            <strong>GoPay</strong><br>
                                            <small>081234567890</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('081234567890')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded mb-2">
                                        <div>
                                            <strong>OVO</strong><br>
                                            <small>081234567890</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('081234567890')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                        <div>
                                            <strong>DANA</strong><br>
                                            <small>081234567890</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('081234567890')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Transfer sesuai dengan <strong>jumlah yang tepat</strong> untuk mempercepat verifikasi</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload Form -->
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-upload me-2"></i>Upload Bukti Pembayaran
                                <?php if ($existing_payment): ?>
                                    <span class="badge bg-warning ms-2">Update Bukti</span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($existing_payment): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Anda sudah pernah mengupload bukti pembayaran. Upload file baru akan mengganti bukti sebelumnya.
                                </div>
                            <?php endif; ?>

                            <form method="POST" enctype="multipart/form-data">
                                <!-- Payment Method -->
                                <div class="mb-4">
                                    <label class="form-label">Metode Pembayaran yang Digunakan *</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="metode_pembayaran"
                                                    value="transfer" id="method_transfer" required
                                                    <?= ($existing_payment && $existing_payment['metode_pembayaran'] === 'transfer') ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="method_transfer">
                                                    <i class="fas fa-university me-2"></i>Transfer Bank
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="metode_pembayaran"
                                                    value="e_wallet" id="method_ewallet" required
                                                    <?= ($existing_payment && $existing_payment['metode_pembayaran'] === 'e_wallet') ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="method_ewallet">
                                                    <i class="fas fa-mobile-alt me-2"></i>E-Wallet
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- File Upload -->
                                <div class="mb-4">
                                    <label for="bukti_pembayaran" class="form-label">Upload Bukti Pembayaran *</label>
                                    <div class="input-group">
                                        <input type="file" class="form-control" id="bukti_pembayaran" name="bukti_pembayaran"
                                            accept="image/*,.pdf" required onchange="previewFile()">
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearFile()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Format: JPG, PNG, atau PDF. Maksimal 5MB</small>

                                    <!-- Preview -->
                                    <div id="filePreview" class="mt-3" style="display: none;">
                                        <div class="border rounded p-3 bg-light">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file fa-2x me-3 text-primary"></i>
                                                <div>
                                                    <div id="fileName" class="fw-bold"></div>
                                                    <div id="fileSize" class="text-muted small"></div>
                                                </div>
                                            </div>
                                            <img id="imagePreview" class="mt-3 img-fluid" style="max-height: 200px; display: none;">
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="mb-4">
                                    <label for="catatan" class="form-label">Catatan Pembayaran (Opsional)</label>
                                    <textarea class="form-control" id="catatan" name="catatan" rows="3"
                                        placeholder="Contoh: Transfer dari rekening BCA atas nama John Doe"><?= $existing_payment ? htmlspecialchars($existing_payment['catatan']) : '' ?></textarea>
                                </div>

                                <!-- Instructions -->
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-lightbulb me-2"></i>Tips Upload Bukti Pembayaran:</h6>
                                    <ul class="mb-0">
                                        <li>Pastikan foto/screenshot jelas dan tidak blur</li>
                                        <li>Tampilkan informasi lengkap: jumlah transfer, tanggal, dan tujuan transfer</li>
                                        <li>Untuk transfer bank: sertakan nama pengirim dan bank asal</li>
                                        <li>Untuk e-wallet: pastikan status "Berhasil" terlihat jelas</li>
                                    </ul>
                                </div>

                                <!-- Submit -->
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-upload me-2"></i>
                                        <?= $existing_payment ? 'Update Bukti Pembayaran' : 'Upload Bukti Pembayaran' ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            KolamRenang.showToast('Nomor rekening disalin ke clipboard', 'success');
        }, function(err) {
            console.error('Could not copy text: ', err);
        });
    }

    function previewFile() {
        const input = document.getElementById('bukti_pembayaran');
        const preview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const imagePreview = document.getElementById('imagePreview');

        if (input.files && input.files[0]) {
            const file = input.files[0];

            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);

            preview.style.display = 'block';

            // Show image preview if it's an image
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.style.display = 'none';
            }
        }
    }

    function clearFile() {
        document.getElementById('bukti_pembayaran').value = '';
        document.getElementById('filePreview').style.display = 'none';
        document.getElementById('imagePreview').style.display = 'none';
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
</script>

<?php include '../includes/footer.php'; ?>