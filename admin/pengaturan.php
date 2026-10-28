<?php
// admin/pengaturan.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$page_title = "Pengaturan Sistem - Admin";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_settings':
                $settings = [
                    'site_name' => sanitize($_POST['site_name']),
                    'site_description' => sanitize($_POST['site_description']),
                    'contact_phone' => sanitize($_POST['contact_phone']),
                    'contact_email' => sanitize($_POST['contact_email']),
                    'address' => sanitize($_POST['address']),
                    'max_booking_days' => intval($_POST['max_booking_days']),
                    'min_booking_hours' => intval($_POST['min_booking_hours'])
                ];

                $success = true;
                foreach ($settings as $key => $value) {
                    if (!updateSetting($key, $value)) {
                        $success = false;
                        break;
                    }
                }

                if ($success) {
                    setAlert('success', 'Pengaturan berhasil disimpan');
                } else {
                    setAlert('danger', 'Gagal menyimpan pengaturan');
                }
                break;

            case 'update_operating_hours':
                $days = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
                $success = true;

                foreach ($days as $day) {
                    $jam_buka = $_POST['jam_buka_' . $day];
                    $jam_tutup = $_POST['jam_tutup_' . $day];
                    $status = isset($_POST['status_' . $day]) ? 'buka' : 'tutup';

                    $query = "UPDATE jam_operasional SET jam_buka = :jam_buka, jam_tutup = :jam_tutup, status = :status WHERE hari = :hari";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':jam_buka', $jam_buka);
                    $stmt->bindParam(':jam_tutup', $jam_tutup);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':hari', $day);

                    if (!$stmt->execute()) {
                        $success = false;
                        break;
                    }
                }

                if ($success) {
                    setAlert('success', 'Jam operasional berhasil diupdate');
                } else {
                    setAlert('danger', 'Gagal mengupdate jam operasional');
                }
                break;
        }
    }
}

// Get current settings
$current_settings = [];
$query = "SELECT setting_key, setting_value FROM settings";
$stmt = $db->prepare($query);
$stmt->execute();
$results = $stmt->fetchAll();

foreach ($results as $setting) {
    $current_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Get operating hours
$query = "SELECT * FROM jam_operasional ORDER BY FIELD(hari, 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu')";
$stmt = $db->prepare($query);
$stmt->execute();
$operating_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative array
$hours_by_day = [];
foreach ($operating_hours as $hour) {
    $hours_by_day[$hour['hari']] = $hour;
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
                        <a class="nav-link" href="kelola_pemesanan.php">
                            <i class="fas fa-clipboard-list me-2"></i>Kelola Pemesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="verifikasi_pembayaran.php">
                            <i class="fas fa-credit-card me-2"></i>Verifikasi Pembayaran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="pengaturan.php">
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
                <h1 class="h2">Pengaturan Sistem</h1>
            </div>

            <!-- General Settings -->
            <div class="card-custom mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-globe me-2"></i>Pengaturan Umum
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_settings">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="site_name" class="form-label">Nama Website</label>
                                    <input type="text" class="form-control" id="site_name" name="site_name"
                                        value="<?= htmlspecialchars($current_settings['site_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_email" class="form-label">Email Kontak</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email"
                                        value="<?= htmlspecialchars($current_settings['contact_email'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="site_description" class="form-label">Deskripsi Website</label>
                            <textarea class="form-control" id="site_description" name="site_description" rows="3"><?= htmlspecialchars($current_settings['site_description'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label">Nomor Telepon</label>
                                    <input type="text" class="form-control" id="contact_phone" name="contact_phone"
                                        value="<?= htmlspecialchars($current_settings['contact_phone'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="address" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($current_settings['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_booking_days" class="form-label">Maksimal Hari Booking ke Depan</label>
                                    <input type="number" class="form-control" id="max_booking_days" name="max_booking_days"
                                        value="<?= htmlspecialchars($current_settings['max_booking_days'] ?? '7') ?>" min="1" max="30" required>
                                    <small class="text-muted">Pelanggan dapat booking maksimal berapa hari ke depan</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="min_booking_hours" class="form-label">Minimal Jam Sebelum Kunjungan</label>
                                    <input type="number" class="form-control" id="min_booking_hours" name="min_booking_hours"
                                        value="<?= htmlspecialchars($current_settings['min_booking_hours'] ?? '2') ?>" min="1" max="24" required>
                                    <small class="text-muted">Booking harus dilakukan minimal berapa jam sebelum kunjungan</small>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan Pengaturan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Operating Hours -->
            <div class="card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>Jam Operasional
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_operating_hours">

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Hari</th>
                                        <th>Status</th>
                                        <th>Jam Buka</th>
                                        <th>Jam Tutup</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $days = [
                                        'senin' => 'Senin',
                                        'selasa' => 'Selasa',
                                        'rabu' => 'Rabu',
                                        'kamis' => 'Kamis',
                                        'jumat' => 'Jumat',
                                        'sabtu' => 'Sabtu',
                                        'minggu' => 'Minggu'
                                    ];

                                    foreach ($days as $day_key => $day_name):
                                        $day_data = $hours_by_day[$day_key] ?? null;
                                    ?>
                                        <tr>
                                            <td><strong><?= $day_name ?></strong></td>
                                            <td>
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input"
                                                        name="status_<?= $day_key ?>" id="status_<?= $day_key ?>"
                                                        <?= ($day_data && $day_data['status'] === 'buka') ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="status_<?= $day_key ?>">
                                                        Buka
                                                    </label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="time" class="form-control" name="jam_buka_<?= $day_key ?>"
                                                    value="<?= $day_data ? $day_data['jam_buka'] : '06:00' ?>" required>
                                            </td>
                                            <td>
                                                <input type="time" class="form-control" name="jam_tutup_<?= $day_key ?>"
                                                    value="<?= $day_data ? $day_data['jam_tutup'] : '21:00' ?>" required>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan Jam Operasional
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>