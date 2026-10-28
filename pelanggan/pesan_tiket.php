<?php
// pelanggan/pesan_tiket.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('pelanggan');

$page_title = "Pesan Tiket - Kolam Renang Licia";

// Get available tickets
$tickets = getTicketTypes();

// Pre-select ticket if from URL
$selected_ticket_id = $_GET['ticket'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis_tiket_id = intval($_POST['jenis_tiket_id']);
    $tanggal_kunjungan = $_POST['tanggal_kunjungan'];
    $jam_kunjungan = $_POST['jam_kunjungan'];
    $jumlah_tiket = intval($_POST['jumlah_tiket']);
    $metode_pembayaran = sanitize($_POST['metode_pembayaran']);
    $catatan = sanitize($_POST['catatan']);

    // Validation
    $error = '';

    // Check if ticket exists
    $ticket = getTicketById($jenis_tiket_id);
    if (!$ticket || $ticket['status'] !== 'aktif') {
        $error = 'Tiket tidak tersedia';
    }

    // Check date is not in the past
    if (strtotime($tanggal_kunjungan) < strtotime(date('Y-m-d'))) {
        $error = 'Tanggal kunjungan tidak boleh di masa lalu';
    }

    // Check max booking days
    $max_days = intval(getSetting('max_booking_days') ?: 7);
    $max_date = date('Y-m-d', strtotime("+$max_days days"));
    if ($tanggal_kunjungan > $max_date) {
        $error = "Pemesanan maksimal $max_days hari ke depan";
    }

    // Check minimum booking hours
    $min_hours = intval(getSetting('min_booking_hours') ?: 2);
    $booking_datetime = strtotime("$tanggal_kunjungan $jam_kunjungan");
    $min_datetime = strtotime("+$min_hours hours");
    if ($booking_datetime < $min_datetime) {
        $error = "Pemesanan minimal $min_hours jam sebelum kunjungan";
    }

    // Check if time slot is available
    if (!$error && !isTimeSlotAvailable($tanggal_kunjungan, $jam_kunjungan)) {
        $error = 'Slot waktu tidak tersedia atau di luar jam operasional';
    }

    // Check quantity
    if ($jumlah_tiket < 1 || $jumlah_tiket > 10) {
        $error = 'Jumlah tiket minimal 1 dan maksimal 10';
    }

    if (empty($error)) {
        // Calculate total
        $total_harga = $ticket['harga'] * $jumlah_tiket;

        // Generate booking code
        $kode_booking = generateBookingCode();

        // Insert booking
        $query = "INSERT INTO pemesanan (user_id, jenis_tiket_id, tanggal_kunjungan, jam_kunjungan, 
                                        jumlah_tiket, total_harga, metode_pembayaran, kode_booking, catatan) 
                  VALUES (:user_id, :jenis_tiket_id, :tanggal_kunjungan, :jam_kunjungan, 
                          :jumlah_tiket, :total_harga, :metode_pembayaran, :kode_booking, :catatan)";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':jenis_tiket_id', $jenis_tiket_id);
        $stmt->bindParam(':tanggal_kunjungan', $tanggal_kunjungan);
        $stmt->bindParam(':jam_kunjungan', $jam_kunjungan);
        $stmt->bindParam(':jumlah_tiket', $jumlah_tiket);
        $stmt->bindParam(':total_harga', $total_harga);
        $stmt->bindParam(':metode_pembayaran', $metode_pembayaran);
        $stmt->bindParam(':kode_booking', $kode_booking);
        $stmt->bindParam(':catatan', $catatan);

        if ($stmt->execute()) {
            setAlert('success', 'Pemesanan berhasil dibuat dengan kode: ' . $kode_booking);
            header('Location: upload_pembayaran.php?booking=' . $kode_booking);
            exit();
        } else {
            $error = 'Gagal menyimpan pemesanan';
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
                        <a class="nav-link active" href="pesan_tiket.php">
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
                <h1 class="h2">Pesan Tiket</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Booking Form -->
                <div class="col-lg-8">
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-plus-circle me-2"></i>Form Pemesanan Tiket
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="bookingForm">
                                <!-- Ticket Selection -->
                                <div class="mb-4">
                                    <label for="jenis_tiket_id" class="form-label">Pilih Jenis Tiket *</label>
                                    <select class="form-select" id="jenis_tiket_id" name="jenis_tiket_id" required onchange="updatePrice()">
                                        <option value="">-- Pilih Tiket --</option>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <option value="<?= $ticket['id'] ?>"
                                                data-price="<?= $ticket['harga'] ?>"
                                                data-name="<?= htmlspecialchars($ticket['nama_tiket']) ?>"
                                                data-duration="<?= htmlspecialchars($ticket['durasi']) ?>"
                                                <?= ($selected_ticket_id == $ticket['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($ticket['nama_tiket']) ?> - <?= formatCurrency($ticket['harga']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="ticketInfo" class="mt-2 text-muted"></div>
                                </div>

                                <!-- Date and Time -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="tanggal_kunjungan" class="form-label">Tanggal Kunjungan *</label>
                                            <input type="date" class="form-control" id="tanggal_kunjungan" name="tanggal_kunjungan"
                                                min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                                max="<?= date('Y-m-d', strtotime('+' . (getSetting('max_booking_days') ?: 7) . ' days')) ?>"
                                                required onchange="checkOperatingHours()">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="jam_kunjungan" class="form-label">Jam Kunjungan *</label>
                                            <input type="time" class="form-control" id="jam_kunjungan" name="jam_kunjungan" required>
                                            <div id="operatingHours" class="mt-1 text-muted"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quantity -->
                                <div class="mb-3">
                                    <label for="jumlah_tiket" class="form-label">Jumlah Tiket *</label>
                                    <div class="input-group" style="max-width: 200px;">
                                        <button type="button" class="btn btn-outline-secondary" onclick="changeQuantity(-1)">-</button>
                                        <input type="number" class="form-control text-center" id="jumlah_tiket" name="jumlah_tiket"
                                            value="1" min="1" max="10" required onchange="updateTotal()">
                                        <button type="button" class="btn btn-outline-secondary" onclick="changeQuantity(1)">+</button>
                                    </div>
                                    <small class="text-muted">Maksimal 10 tiket per pemesanan</small>
                                </div>

                                <!-- Payment Method -->
                                <div class="mb-3">
                                    <label class="form-label">Metode Pembayaran *</label>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="metode_pembayaran"
                                                    value="transfer" id="transfer" required>
                                                <label class="form-check-label" for="transfer">
                                                    <i class="fas fa-university me-2"></i>Transfer Bank
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="metode_pembayaran"
                                                    value="e_wallet" id="e_wallet" required>
                                                <label class="form-check-label" for="e_wallet">
                                                    <i class="fas fa-mobile-alt me-2"></i>E-Wallet
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" name="metode_pembayaran"
                                                    value="cash" id="cash" required>
                                                <label class="form-check-label" for="cash">
                                                    <i class="fas fa-money-bill me-2"></i>Bayar di Tempat
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="mb-4">
                                    <label for="catatan" class="form-label">Catatan (Opsional)</label>
                                    <textarea class="form-control" id="catatan" name="catatan" rows="3"
                                        placeholder="Permintaan khusus atau catatan tambahan"></textarea>
                                </div>

                                <!-- Submit Button -->
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-shopping-cart me-2"></i>Buat Pemesanan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="card-custom" id="orderSummary" style="position: sticky; top: 20px;">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-receipt me-2"></i>Ringkasan Pemesanan
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="summaryContent">
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-ticket-alt fa-3x mb-3"></i>
                                    <p>Pilih tiket untuk melihat ringkasan pemesanan</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div class="card-custom mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Informasi Pembayaran
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Transfer Bank:</strong>
                                <p class="mb-1">Bank BCA: 1234567890</p>
                                <p class="mb-1">Bank Mandiri: 0987654321</p>
                                <p class="mb-0">a.n. Kolam Renang Licia</p>
                            </div>

                            <div class="mb-3">
                                <strong>E-Wallet:</strong>
                                <p class="mb-1">GoPay: 081234567890</p>
                                <p class="mb-1">OVO: 081234567890</p>
                                <p class="mb-0">DANA: 081234567890</p>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-lightbulb me-2"></i>
                                <small>Setelah pemesanan, Anda akan diarahkan untuk upload bukti pembayaran</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    let currentPrice = 0;

    function updatePrice() {
        const select = document.getElementById('jenis_tiket_id');
        const option = select.options[select.selectedIndex];

        if (option.value) {
            currentPrice = parseInt(option.dataset.price);
            const name = option.dataset.name;
            const duration = option.dataset.duration;

            document.getElementById('ticketInfo').innerHTML =
                `<i class="fas fa-info-circle me-1"></i>Durasi: ${duration}`;

            updateSummary();
        } else {
            currentPrice = 0;
            document.getElementById('ticketInfo').innerHTML = '';
            document.getElementById('summaryContent').innerHTML =
                '<div class="text-center text-muted py-4"><i class="fas fa-ticket-alt fa-3x mb-3"></i><p>Pilih tiket untuk melihat ringkasan pemesanan</p></div>';
        }
    }

    function changeQuantity(change) {
        const input = document.getElementById('jumlah_tiket');
        let value = parseInt(input.value) + change;
        if (value < 1) value = 1;
        if (value > 10) value = 10;
        input.value = value;
        updateTotal();
    }

    function updateTotal() {
        updateSummary();
    }

    function updateSummary() {
        const select = document.getElementById('jenis_tiket_id');
        const quantity = parseInt(document.getElementById('jumlah_tiket').value);
        const dateInput = document.getElementById('tanggal_kunjungan');
        const timeInput = document.getElementById('jam_kunjungan');

        if (select.value && currentPrice > 0) {
            const option = select.options[select.selectedIndex];
            const total = currentPrice * quantity;

            const summary = `
            <div class="mb-3">
                <strong>Tiket:</strong>
                <p class="mb-1">${option.dataset.name}</p>
                <p class="text-muted mb-0">Durasi: ${option.dataset.duration}</p>
            </div>
            
            ${dateInput.value ? `
            <div class="mb-3">
                <strong>Tanggal & Waktu:</strong>
                <p class="mb-1">${new Date(dateInput.value).toLocaleDateString('id-ID')}</p>
                ${timeInput.value ? `<p class="mb-0">${timeInput.value}</p>` : ''}
            </div>
            ` : ''}
            
            <div class="mb-3">
                <strong>Jumlah:</strong>
                <p class="mb-0">${quantity} tiket</p>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between">
                    <span>Harga per tiket:</span>
                    <span>${formatCurrency(currentPrice)}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Jumlah tiket:</span>
                    <span>${quantity}</span>
                </div>
            </div>
            
            <div class="d-flex justify-content-between fw-bold fs-5">
                <span>Total:</span>
                <span class="text-primary">${formatCurrency(total)}</span>
            </div>
        `;

            document.getElementById('summaryContent').innerHTML = summary;
        }
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(amount);
    }

    function checkOperatingHours() {
        const dateInput = document.getElementById('tanggal_kunjungan');
        if (dateInput.value) {
            const date = new Date(dateInput.value);
            const day = date.getDay(); // 0 = Sunday, 1 = Monday, etc.

            // This is a simplified version - in real implementation, 
            // you'd fetch operating hours from the server
            let hours = '';
            if (day >= 1 && day <= 5) { // Monday to Friday
                hours = 'Jam operasional: 06:00 - 21:00';
            } else { // Weekend
                hours = 'Jam operasional: 06:00 - 22:00';
            }

            document.getElementById('operatingHours').innerHTML =
                `<i class="fas fa-clock me-1"></i>${hours}`;
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        updatePrice();

        // Set minimum time based on minimum booking hours
        const now = new Date();
        const minHours = <?= getSetting('min_booking_hours') ?: 2 ?>;
        const minTime = new Date(now.getTime() + (minHours * 60 * 60 * 1000));

        document.getElementById('tanggal_kunjungan').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date().toDateString();

            if (selectedDate.toDateString() === today) {
                document.getElementById('jam_kunjungan').min =
                    minTime.toTimeString().substr(0, 5);
            } else {
                document.getElementById('jam_kunjungan').min = '';
            }

            updateSummary();
            checkOperatingHours();
        });

        document.getElementById('jam_kunjungan').addEventListener('change', updateSummary);
    });
</script>

<?php include '../includes/footer.php'; ?>