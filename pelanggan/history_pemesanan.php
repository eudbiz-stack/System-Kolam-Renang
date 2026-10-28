<?php
// pelanggan/history_pemesanan.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('pelanggan');

$page_title = "History Pemesanan - Kolam Renang Licia";

// Get view action
$view_booking = $_GET['view'] ?? null;

// Get booking detail if viewing
$booking_detail = null;
$payment_info = null;
$user_info = null;
if ($view_booking) {
    $booking_detail = getBookingByCode($view_booking);
    if ($booking_detail && $booking_detail['user_id'] == $_SESSION['user_id']) {
        $payment_info = getPaymentByBookingId($booking_detail['id']);
        // Get user info for ticket
        $stmt = $db->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_info = $stmt->fetch();
    } else {
        $view_booking = null;
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$search = $_GET['search'] ?? '';

// Get user's bookings
$query = "SELECT p.*, jt.nama_tiket, jt.harga FROM pemesanan p
          JOIN jenis_tiket jt ON p.jenis_tiket_id = jt.id
          WHERE p.user_id = :user_id";
$params = ['user_id' => $_SESSION['user_id']];

if ($status_filter) {
    $query .= " AND p.status_pemesanan = :status";
    $params['status'] = $status_filter;
}

if ($payment_filter) {
    $query .= " AND p.status_pembayaran = :payment";
    $params['payment'] = $payment_filter;
}

if ($search) {
    $query .= " AND (p.kode_booking LIKE :search OR jt.nama_tiket LIKE :search)";
    $params['search'] = "%$search%";
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindParam(":$key", $value);
}
$stmt->execute();
$bookings = $stmt->fetchAll();

include '../includes/header.php';
?>

<style>
    /* Compact Print Styles */
    @media print {

        /* Hide everything except ticket */
        body * {
            visibility: hidden;
        }

        .print-ticket-area,
        .print-ticket-area * {
            visibility: visible;
        }

        .print-ticket-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        /* Hide non-essential elements */
        .sidebar,
        nav,
        .btn-toolbar,
        .card-header,
        .no-print {
            display: none !important;
        }

        /* Compact ticket styling */
        .compact-ticket {
            width: 18cm;
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border: 2px solid #333;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            page-break-inside: avoid;
        }

        .ticket-header-compact {
            background: #f8f9fa;
            padding: 8px 12px;
            text-align: center;
            border-bottom: 2px solid #333;
            font-weight: bold;
        }

        .pool-title {
            font-size: 16px;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .ticket-subtitle {
            font-size: 10px;
            margin: 2px 0 0 0;
            color: #666;
        }

        .ticket-content {
            display: flex;
            padding: 12px;
        }

        .ticket-info-table {
            flex: 1;
            margin-right: 15px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .info-table td {
            padding: 3px 8px;
            border-bottom: 1px dotted #ccc;
            vertical-align: top;
        }

        .info-table td:first-child {
            font-weight: bold;
            width: 35%;
            background: #f8f9fa;
        }

        .info-table td:last-child {
            width: 65%;
        }

        .qr-section {
            flex: 0 0 120px;
            text-align: center;
            border-left: 2px dashed #333;
            padding-left: 15px;
        }

        .qr-canvas {
            border: 1px solid #ddd;
            margin-bottom: 5px;
        }

        .qr-text {
            font-size: 9px;
            color: #666;
            line-height: 1.2;
        }

        .ticket-footer-compact {
            border-top: 2px solid #333;
            padding: 8px 12px;
            background: #f8f9fa;
        }

        .footer-rules {
            font-size: 9px;
            line-height: 1.3;
            color: #555;
            margin-bottom: 5px;
        }

        .footer-contact {
            font-size: 8px;
            color: #777;
            text-align: center;
            border-top: 1px dotted #ccc;
            padding-top: 5px;
            margin-top: 5px;
        }

        .status-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 3px;
            font-weight: bold;
        }

        .price-highlight {
            font-weight: bold;
            font-size: 13px;
        }

        .ticket-number {
            position: absolute;
            top: 5px;
            right: 10px;
            font-size: 8px;
            color: #999;
        }
    }

    /* Screen-only styles */
    @media screen {
        .print-ticket-area {
            display: none;
        }

        #qrcode-display {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 120px;
        }
    }
</style>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse no-print">
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
                        <a class="nav-link active" href="history_pemesanan.php">
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
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                <h1 class="h2">History Pemesanan</h1>
                <?php if ($view_booking): ?>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="history_pemesanan.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar
                        </a>
                    </div>
                <?php else: ?>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="pesan_tiket.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Pesan Tiket Baru
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($view_booking && $booking_detail): ?>
                <!-- Booking Detail View -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card-custom mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center no-print">
                                <h5 class="mb-0">
                                    <i class="fas fa-ticket-alt me-2"></i>Detail Pemesanan
                                </h5>
                                <div>
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
                                    <span class="badge bg-<?= $status_class ?> fs-6"><?= $status_text ?></span>
                                </div>
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
                                                <td><strong>Tiket:</strong></td>
                                                <td><?= htmlspecialchars($booking_detail['nama_tiket']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Tanggal Kunjungan:</strong></td>
                                                <td><?= formatDate($booking_detail['tanggal_kunjungan']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Jam Kunjungan:</strong></td>
                                                <td><?= date('H:i', strtotime($booking_detail['jam_kunjungan'])) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Jumlah Tiket:</strong></td>
                                                <td><?= $booking_detail['jumlah_tiket'] ?> tiket</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Harga:</strong></td>
                                                <td class="fw-bold text-primary"><?= formatCurrency($booking_detail['total_harga']) ?></td>
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

                        <!-- Payment Status -->
                        <div class="card-custom">
                            <div class="card-header no-print">
                                <h5 class="mb-0">
                                    <i class="fas fa-credit-card me-2"></i>Status Pembayaran
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $payment_class = '';
                                $payment_text = '';
                                $payment_icon = '';
                                switch ($booking_detail['status_pembayaran']) {
                                    case 'unpaid':
                                        $payment_class = 'danger';
                                        $payment_text = 'Belum Bayar';
                                        $payment_icon = 'fas fa-exclamation-triangle';
                                        break;
                                    case 'paid':
                                        $payment_class = 'success';
                                        $payment_text = 'Sudah Bayar';
                                        $payment_icon = 'fas fa-check-circle';
                                        break;
                                    case 'refunded':
                                        $payment_class = 'info';
                                        $payment_text = 'Refund';
                                        $payment_icon = 'fas fa-undo';
                                        break;
                                }
                                ?>

                                <div class="d-flex align-items-center mb-3">
                                    <i class="<?= $payment_icon ?> fa-2x text-<?= $payment_class ?> me-3"></i>
                                    <div>
                                        <h5 class="mb-1 text-<?= $payment_class ?>"><?= $payment_text ?></h5>
                                        <p class="mb-0 text-muted">
                                            <?php if ($booking_detail['status_pembayaran'] === 'unpaid'): ?>
                                                Silakan lakukan pembayaran dan upload bukti pembayaran
                                            <?php elseif ($booking_detail['status_pembayaran'] === 'paid'): ?>
                                                Pembayaran telah dikonfirmasi oleh admin
                                            <?php else: ?>
                                                Pembayaran telah di-refund
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <?php if ($payment_info): ?>
                                    <div class="alert alert-info">
                                        <strong>Info Pembayaran:</strong>
                                        <p class="mb-1">Metode: <?= ucfirst($payment_info['metode_pembayaran']) ?></p>
                                        <p class="mb-1">Jumlah: <?= formatCurrency($payment_info['jumlah_bayar']) ?></p>
                                        <p class="mb-1">Tanggal Upload: <?= formatDateTime($payment_info['tanggal_bayar']) ?></p>
                                        <p class="mb-0">Status:
                                            <?php
                                            switch ($payment_info['status']) {
                                                case 'pending':
                                                    echo '<span class="badge bg-warning">Menunggu Verifikasi</span>';
                                                    break;
                                                case 'verified':
                                                    echo '<span class="badge bg-success">Terverifikasi</span>';
                                                    break;
                                                case 'rejected':
                                                    echo '<span class="badge bg-danger">Ditolak</span>';
                                                    break;
                                            }
                                            ?>
                                        </p>
                                        <?php if ($payment_info['catatan']): ?>
                                            <p class="mb-0 mt-2"><strong>Catatan Admin:</strong> <?= htmlspecialchars($payment_info['catatan']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Action Buttons -->
                                <div class="d-flex gap-2 no-print">
                                    <?php if ($booking_detail['status_pembayaran'] === 'unpaid' && $booking_detail['status_pemesanan'] !== 'cancelled'): ?>
                                        <a href="upload_pembayaran.php?booking=<?= $booking_detail['kode_booking'] ?>"
                                            class="btn btn-primary">
                                            <i class="fas fa-upload me-2"></i>Upload Bukti Pembayaran
                                        </a>
                                    <?php elseif ($payment_info && $payment_info['status'] === 'rejected'): ?>
                                        <a href="upload_pembayaran.php?booking=<?= $booking_detail['kode_booking'] ?>"
                                            class="btn btn-warning">
                                            <i class="fas fa-upload me-2"></i>Upload Ulang Bukti
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($booking_detail['status_pemesanan'] === 'confirmed' && $booking_detail['status_pembayaran'] === 'paid'): ?>
                                        <button class="btn btn-success" onclick="printTicket()">
                                            <i class="fas fa-print me-2"></i>Cetak Tiket
                                        </button>
                                        <button class="btn btn-outline-primary" onclick="downloadPDF()">
                                            <i class="fas fa-download me-2"></i>Download PDF
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code & Actions -->
                    <div class="col-lg-4">
                        <?php if ($booking_detail['status_pemesanan'] === 'confirmed' && $booking_detail['status_pembayaran'] === 'paid'): ?>
                            <div class="card-custom mb-4">
                                <div class="card-header text-center bg-success text-white no-print">
                                    <h5 class="mb-0">
                                        <i class="fas fa-qrcode me-2"></i>E-Tiket
                                    </h5>
                                </div>
                                <div class="card-body text-center">
                                    <!-- QR Code Display -->
                                    <div id="qrcode-display" class="mb-3">
                                        <!-- QR Code will be generated here -->
                                    </div>

                                    <h6 class="text-success">Tiket Valid</h6>
                                    <p class="text-muted mb-0">Tunjukkan QR Code ini kepada petugas</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Booking Info Summary -->
                        <div class="card-custom">
                            <div class="card-header no-print">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Ringkasan
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Harga per tiket:</span>
                                    <span><?= formatCurrency($booking_detail['harga']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Jumlah tiket:</span>
                                    <span><?= $booking_detail['jumlah_tiket'] ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Total:</span>
                                    <span class="text-primary"><?= formatCurrency($booking_detail['total_harga']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COMPACT PRINT TICKET AREA - Only for confirmed and paid bookings -->
                <?php if ($booking_detail['status_pemesanan'] === 'confirmed' && $booking_detail['status_pembayaran'] === 'paid'): ?>
                    <div class="print-ticket-area">
                        <div class="compact-ticket">
                            <div class="ticket-number">No: <span id="print-ticket-number">001</span></div>

                            <!-- Compact Header -->
                            <div class="ticket-header-compact">
                                <h3 class="pool-title">🏊 KOLAM RENANG LICIA</h3>
                                <p class="ticket-subtitle">Swimming Pool Entry Ticket</p>
                                <span class="status-badge">✓ VALID TICKET</span>
                            </div>

                            <!-- Ticket Content -->
                            <div class="ticket-content">
                                <!-- Info Table -->
                                <div class="ticket-info-table">
                                    <table class="info-table">
                                        <tr>
                                            <td>Kode Booking</td>
                                            <td><?= htmlspecialchars($booking_detail['kode_booking']) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Nama Pelanggan</td>
                                            <td><?= htmlspecialchars($user_info['nama_lengkap']) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Jenis Tiket</td>
                                            <td><?= htmlspecialchars($booking_detail['nama_tiket']) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Tanggal Kunjungan</td>
                                            <td><?= formatDate($booking_detail['tanggal_kunjungan']) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Jam Masuk</td>
                                            <td><?= date('H:i', strtotime($booking_detail['jam_kunjungan'])) ?> WIB</td>
                                        </tr>
                                        <tr>
                                            <td>Jumlah Tiket</td>
                                            <td><?= $booking_detail['jumlah_tiket'] ?> orang</td>
                                        </tr>
                                        <tr>
                                            <td>Total Bayar</td>
                                            <td class="price-highlight"><?= formatCurrency($booking_detail['total_harga']) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Metode Bayar</td>
                                            <td><?= ucfirst($booking_detail['metode_pembayaran']) ?></td>
                                        </tr>
                                        <tr>
                                            <td>Waktu Cetak</td>
                                            <td id="print-timestamp">-</td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- QR Code Section -->
                                <div class="qr-section">
                                    <canvas id="qrcode-print" class="qr-canvas" width="90" height="90"></canvas>
                                    <div class="qr-text">
                                        <strong>SCAN TO VERIFY</strong><br>
                                        Tunjukkan ke petugas<br>
                                        <small><?= htmlspecialchars($booking_detail['kode_booking']) ?></small>
                                    </div>
                                </div>
                            </div>

                            <!-- Compact Footer -->
                            <div class="ticket-footer-compact">
                                <div class="footer-rules">
                                    <strong>PERATURAN:</strong>
                                    Wajib pakai pakaian renang • Anak <12th harus didampingi • Dilarang bawa makanan luar • Mandi sebelum masuk kolam • Patuhi protokol kesehatan • Tiket tidak dapat dipindahtangankan
                                        </div>
                                        <div class="footer-contact">
                                            📍 Jl. Raya Kemang No.123, Jakarta Selatan | 📞 021-7654321 | 📱 08123456789 | 📧 info@kolamrenanglicia.com<br>
                                            <em>Terima kasih telah memilih Kolam Renang Licia - Keep Swimming, Stay Healthy!</em>
                                        </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Bookings List (existing code remains the same) -->

                    <!-- Filter -->
                    <div class="card-custom mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status Pemesanan</label>
                                    <select class="form-select" name="status" id="status">
                                        <option value="">Semua Status</option>
                                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Dikonfirmasi</option>
                                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="payment" class="form-label">Status Pembayaran</label>
                                    <select class="form-select" name="payment" id="payment">
                                        <option value="">Semua Status</option>
                                        <option value="unpaid" <?= $payment_filter === 'unpaid' ? 'selected' : '' ?>>Belum Bayar</option>
                                        <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Sudah Bayar</option>
                                        <option value="refunded" <?= $payment_filter === 'refunded' ? 'selected' : '' ?>>Refund</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Cari</label>
                                    <input type="text" class="form-control" name="search" id="search"
                                        placeholder="Kode booking atau nama tiket" value="<?= htmlspecialchars($search) ?>">
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
                            <?php if ($status_filter || $payment_filter || $search): ?>
                                <a href="history_pemesanan.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($bookings)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-ticket-alt fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">Tidak Ada Pemesanan</h5>
                                    <p class="text-muted">Belum ada pemesanan yang sesuai dengan filter yang dipilih</p>
                                    <a href="pesan_tiket.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Pesan Tiket Sekarang
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($bookings as $booking): ?>
                                        <div class="col-lg-6 mb-4">
                                            <div class="card border-0 shadow-sm h-100">
                                                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($booking['kode_booking']) ?></h6>
                                                    <div>
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
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <h6 class="card-title"><?= htmlspecialchars($booking['nama_tiket']) ?></h6>
                                                    <div class="row mb-3">
                                                        <div class="col-6">
                                                            <small class="text-muted">Tanggal Kunjungan</small>
                                                            <p class="mb-1"><?= formatDate($booking['tanggal_kunjungan']) ?></p>
                                                            <small class="text-muted"><?= date('H:i', strtotime($booking['jam_kunjungan'])) ?></small>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Jumlah & Total</small>
                                                            <p class="mb-1"><?= $booking['jumlah_tiket'] ?> tiket</p>
                                                            <strong class="text-primary"><?= formatCurrency($booking['total_harga']) ?></strong>
                                                        </div>
                                                    </div>

                                                    <!-- Payment Status -->
                                                    <div class="mb-3">
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
                                                        <span class="badge bg-<?= $payment_class ?>">
                                                            <i class="fas fa-credit-card me-1"></i><?= $payment_text ?>
                                                        </span>
                                                    </div>

                                                    <small class="text-muted">Dipesan: <?= formatDateTime($booking['created_at']) ?></small>
                                                </div>
                                                <div class="card-footer bg-transparent">
                                                    <div class="d-flex gap-2">
                                                        <a href="?view=<?= $booking['kode_booking'] ?>"
                                                            class="btn btn-sm btn-outline-primary flex-fill">
                                                            <i class="fas fa-eye me-1"></i>Detail
                                                        </a>

                                                        <?php if ($booking['status_pembayaran'] === 'unpaid' && $booking['status_pemesanan'] !== 'cancelled'): ?>
                                                            <a href="upload_pembayaran.php?booking=<?= $booking['kode_booking'] ?>"
                                                                class="btn btn-sm btn-primary">
                                                                <i class="fas fa-upload me-1"></i>Bayar
                                                            </a>
                                                        <?php elseif ($booking['status_pemesanan'] === 'confirmed' && $booking['status_pembayaran'] === 'paid'): ?>
                                                            <a href="?view=<?= $booking['kode_booking'] ?>"
                                                                class="btn btn-sm btn-success">
                                                                <i class="fas fa-print"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
        </main>
    </div>
</div>

<?php if ($view_booking && $booking_detail && $booking_detail['status_pemesanan'] === 'confirmed' && $booking_detail['status_pembayaran'] === 'paid'): ?>
    <script>
        // Pass PHP data to JavaScript
        const ticketData = {
            bookingCode: <?= json_encode($booking_detail['kode_booking']) ?>,
            customerName: <?= json_encode($user_info['nama_lengkap']) ?>,
            ticketType: <?= json_encode($booking_detail['nama_tiket']) ?>,
            visitDate: <?= json_encode(formatDate($booking_detail['tanggal_kunjungan'])) ?>,
            visitTime: <?= json_encode(date('H:i', strtotime($booking_detail['jam_kunjungan']))) ?>,
            quantity: <?= json_encode($booking_detail['jumlah_tiket']) ?>,
            totalPrice: <?= json_encode(formatCurrency($booking_detail['total_harga'])) ?>,
            paymentMethod: <?= json_encode(ucfirst($booking_detail['metode_pembayaran'])) ?>
        };

        // Simple pattern-based QR code visual
        function createVisualQR(container, size = 120) {
            const canvas = document.createElement('canvas');
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');

            // Create a pseudo QR code pattern
            const moduleSize = size / 25; // 25x25 grid

            // Background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, size, size);

            // Create pattern based on booking code
            ctx.fillStyle = '#000000';
            const code = ticketData.bookingCode;

            // Position detection patterns (corners)
            function drawPositionPattern(x, y) {
                ctx.fillRect(x * moduleSize, y * moduleSize, 7 * moduleSize, 7 * moduleSize);
                ctx.fillStyle = '#ffffff';
                ctx.fillRect((x + 1) * moduleSize, (y + 1) * moduleSize, 5 * moduleSize, 5 * moduleSize);
                ctx.fillStyle = '#000000';
                ctx.fillRect((x + 2) * moduleSize, (y + 2) * moduleSize, 3 * moduleSize, 3 * moduleSize);
            }

            // Draw position patterns
            drawPositionPattern(0, 0); // Top-left
            drawPositionPattern(18, 0); // Top-right
            drawPositionPattern(0, 18); // Bottom-left

            // Create data pattern from booking code
            for (let i = 0; i < code.length; i++) {
                const charCode = code.charCodeAt(i);
                const x = 9 + (i % 7);
                const y = 9 + Math.floor(i / 7);

                if (charCode % 2 === 0) {
                    ctx.fillRect(x * moduleSize, y * moduleSize, moduleSize, moduleSize);
                }
            }

            // Add some random-looking but deterministic pattern
            for (let i = 0; i < 30; i++) {
                const seed = code.charCodeAt(i % code.length) + i;
                const x = (seed * 7) % 25;
                const y = (seed * 11) % 25;

                if (x > 8 && x < 17 && y > 8 && y < 17) {
                    if (seed % 3 === 0) {
                        ctx.fillRect(x * moduleSize, y * moduleSize, moduleSize, moduleSize);
                    }
                }
            }

            // Border
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 2;
            ctx.strokeRect(1, 1, size - 2, size - 2);

            container.innerHTML = '';
            container.appendChild(canvas);
        }

        // Create booking verification display
        function createBookingDisplay(container) {
            const display = document.createElement('div');
            display.className = 'booking-verification';
            display.style.cssText = `
        border: 3px solid #28a745;
        border-radius: 10px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 15px;
        text-align: center;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        max-width: 300px;
        margin: 0 auto;
    `;

            display.innerHTML = `
        <div style="background: #28a745; color: white; padding: 8px; border-radius: 5px; margin-bottom: 10px;">
            <strong>✓ TIKET VALID</strong>
        </div>
        <div style="font-size: 14px; font-weight: bold; color: #495057; margin-bottom: 8px;">
            KOLAM RENANG LICIA
        </div>
        <div style="font-size: 18px; font-weight: bold; color: #dc3545; background: #fff3cd; padding: 8px; border-radius: 5px; border: 2px dashed #ffc107; margin-bottom: 10px; letter-spacing: 1px;">
            ${ticketData.bookingCode}
        </div>
        <div style="font-size: 12px; color: #6c757d; line-height: 1.4;">
            <div><strong>Nama:</strong> ${ticketData.customerName}</div>
            <div><strong>Tanggal:</strong> ${ticketData.visitDate}</div>
            <div><strong>Jam:</strong> ${ticketData.visitTime}</div>
            <div><strong>Jumlah:</strong> ${ticketData.quantity} orang</div>
        </div>
        <div style="margin-top: 10px; padding: 5px; background: #d4edda; border-radius: 3px; font-size: 10px; color: #155724;">
            Tunjukkan kode ini kepada petugas
        </div>
    `;

            container.innerHTML = '';
            container.appendChild(display);
        }

        // Generate display QR code
        function generateQRCode() {
            const qrDisplay = document.getElementById('qrcode-display');
            if (!qrDisplay) return;

            // Create visual QR pattern
            const qrContainer = document.createElement('div');
            qrContainer.style.marginBottom = '10px';
            createVisualQR(qrContainer, 120);

            // Create booking info display
            const infoContainer = document.createElement('div');
            createBookingDisplay(infoContainer);

            qrDisplay.innerHTML = '';
            qrDisplay.appendChild(qrContainer);
            qrDisplay.appendChild(infoContainer);
        }

        // Generate print QR code
        function generatePrintQRCode() {
            return new Promise((resolve) => {
                const printCanvas = document.getElementById('qrcode-print');
                if (!printCanvas) {
                    resolve();
                    return;
                }

                const ctx = printCanvas.getContext('2d');
                const size = 90;

                // Clear canvas
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, size, size);

                // Create simple pattern
                ctx.fillStyle = '#000000';
                const moduleSize = size / 15;

                // Draw corners
                for (let corner of [
                        [1, 1],
                        [1, 11],
                        [11, 1]
                    ]) {
                    ctx.fillRect(corner[0] * moduleSize, corner[1] * moduleSize, 3 * moduleSize, 3 * moduleSize);
                }

                // Pattern from booking code
                const code = ticketData.bookingCode;
                for (let i = 0; i < code.length && i < 20; i++) {
                    const charCode = code.charCodeAt(i);
                    const x = 5 + (i % 5);
                    const y = 5 + Math.floor(i / 5);

                    if (charCode % 2 === 0) {
                        ctx.fillRect(x * moduleSize, y * moduleSize, moduleSize, moduleSize);
                    }
                }

                // Border
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = 1;
                ctx.strokeRect(0, 0, size, size);

                resolve();
            });
        }

        function updatePrintData() {
            try {
                const now = new Date();
                const options = {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    timeZone: 'Asia/Jakarta'
                };
                const printTime = now.toLocaleDateString('id-ID', options);
                const timestampEl = document.getElementById('print-timestamp');
                if (timestampEl) {
                    timestampEl.textContent = printTime;
                }

                const ticketNumber = Math.floor(Math.random() * 999) + 1;
                const ticketNumEl = document.getElementById('print-ticket-number');
                if (ticketNumEl) {
                    ticketNumEl.textContent = ticketNumber.toString().padStart(3, '0');
                }
            } catch (error) {
                console.error('Error updating print data:', error);
            }
        }

        function printTicket() {
            try {
                updatePrintData();

                generatePrintQRCode()
                    .then(() => {
                        setTimeout(() => {
                            window.print();
                        }, 500);
                    });
            } catch (error) {
                console.error('Error in printTicket:', error);
                setTimeout(() => {
                    window.print();
                }, 500);
            }
        }

        function downloadPDF() {
            alert('Untuk download PDF:\n1. Klik "Cetak Tiket"\n2. Pilih "Save as PDF" sebagai printer\n3. Klik "Save"');
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing ticket display...');
            generateQRCode();
            generatePrintQRCode();
            updatePrintData();
        });
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>