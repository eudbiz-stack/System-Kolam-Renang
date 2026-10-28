<?php
// pelanggan/profile.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('pelanggan');

$page_title = "Profil Saya - Kolam Renang Licia";

// Get current user data
$user_data = getUserById($_SESSION['user_id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $nama_lengkap = sanitize($_POST['nama_lengkap']);
                $no_telepon = sanitize($_POST['no_telepon']);
                $alamat = sanitize($_POST['alamat']);

                $error = '';

                // Validation
                if (empty($username) || empty($email) || empty($nama_lengkap)) {
                    $error = 'Field yang wajib harus diisi';
                } elseif (!isValidEmail($email)) {
                    $error = 'Format email tidak valid';
                } elseif (!empty($no_telepon) && !isValidPhone($no_telepon)) {
                    $error = 'Format nomor telepon tidak valid';
                } else {
                    // Check if username or email already exists (except current user)
                    $query = "SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->execute();

                    if ($stmt->fetch()) {
                        $error = 'Username atau email sudah digunakan oleh user lain';
                    }
                }

                if (empty($error)) {
                    $query = "UPDATE users SET username = :username, email = :email, nama_lengkap = :nama_lengkap, 
                             no_telepon = :no_telepon, alamat = :alamat WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':nama_lengkap', $nama_lengkap);
                    $stmt->bindParam(':no_telepon', $no_telepon);
                    $stmt->bindParam(':alamat', $alamat);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);

                    if ($stmt->execute()) {
                        // Update session data
                        $_SESSION['username'] = $username;
                        $_SESSION['email'] = $email;
                        $_SESSION['nama_lengkap'] = $nama_lengkap;

                        setAlert('success', 'Profil berhasil diupdate');

                        // Refresh user data
                        $user_data = getUserById($_SESSION['user_id']);
                    } else {
                        setAlert('danger', 'Gagal mengupdate profil');
                    }
                } else {
                    setAlert('danger', $error);
                }
                break;

            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                $error = '';

                // Validation
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error = 'Semua field password harus diisi';
                } elseif (!password_verify($current_password, $user_data['password'])) {
                    $error = 'Password lama tidak benar';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Password baru minimal 6 karakter';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Konfirmasi password baru tidak cocok';
                }

                if (empty($error)) {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                    $query = "UPDATE users SET password = :password WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);

                    if ($stmt->execute()) {
                        setAlert('success', 'Password berhasil diubah');
                    } else {
                        setAlert('danger', 'Gagal mengubah password');
                    }
                } else {
                    setAlert('danger', $error);
                }
                break;
        }
    }
}

// Get user statistics
$query = "SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status_pemesanan = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN status_pembayaran = 'paid' THEN total_harga ELSE 0 END) as total_spent,
            MIN(created_at) as member_since
          FROM pemesanan WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user_stats = $stmt->fetch();

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
                        <a class="nav-link active" href="profile.php">
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
                <h1 class="h2">Profil Saya</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Profile Info -->
                <div class="col-lg-4">
                    <div class="card-custom mb-4">
                        <div class="card-header text-center bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user-circle me-2"></i>Informasi Profil
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-user-circle fa-5x text-primary mb-3"></i>
                                <h4><?= htmlspecialchars($user_data['nama_lengkap']) ?></h4>
                                <p class="text-muted mb-1">@<?= htmlspecialchars($user_data['username']) ?></p>
                                <p class="text-muted"><?= htmlspecialchars($user_data['email']) ?></p>
                            </div>

                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="border-end">
                                        <h5 class="text-primary"><?= $user_stats['total_bookings'] ?></h5>
                                        <small class="text-muted">Total Booking</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-end">
                                        <h5 class="text-success"><?= $user_stats['confirmed_bookings'] ?></h5>
                                        <small class="text-muted">Dikonfirmasi</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <h5 class="text-info"><?= formatCurrency($user_stats['total_spent']) ?></h5>
                                    <small class="text-muted">Total Spent</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light text-center text-muted">
                            <small>
                                <i class="fas fa-calendar me-1"></i>
                                Member sejak <?= $user_stats['member_since'] ? formatDate($user_stats['member_since']) : formatDate($user_data['created_at']) ?>
                            </small>
                        </div>
                    </div>

                    <!-- Quick Info -->
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Informasi Kontak
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong><i class="fas fa-envelope me-2"></i>Email:</strong>
                                <p class="mb-0"><?= htmlspecialchars($user_data['email']) ?></p>
                            </div>

                            <?php if ($user_data['no_telepon']): ?>
                                <div class="mb-3">
                                    <strong><i class="fas fa-phone me-2"></i>Telepon:</strong>
                                    <p class="mb-0"><?= htmlspecialchars($user_data['no_telepon']) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($user_data['alamat']): ?>
                                <div class="mb-0">
                                    <strong><i class="fas fa-map-marker-alt me-2"></i>Alamat:</strong>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($user_data['alamat'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Edit Forms -->
                <div class="col-lg-8">
                    <!-- Edit Profile -->
                    <div class="card-custom mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-edit me-2"></i>Edit Profil
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Username *</label>
                                            <input type="text" class="form-control" id="username" name="username"
                                                value="<?= htmlspecialchars($user_data['username']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="<?= htmlspecialchars($user_data['email']) ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="nama_lengkap" class="form-label">Nama Lengkap *</label>
                                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap"
                                        value="<?= htmlspecialchars($user_data['nama_lengkap']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="no_telepon" class="form-label">Nomor Telepon</label>
                                    <input type="text" class="form-control" id="no_telepon" name="no_telepon"
                                        placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($user_data['no_telepon']) ?>">
                                </div>

                                <div class="mb-4">
                                    <label for="alamat" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= htmlspecialchars($user_data['alamat']) ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Simpan Perubahan
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="card-custom">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-key me-2"></i>Ubah Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">

                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Password Lama *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('current_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Password Baru *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Minimal 6 karakter</small>
                                </div>

                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">Konfirmasi Password Baru *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Ubah Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const button = field.nextElementSibling.querySelector('i');

        if (field.type === 'password') {
            field.type = 'text';
            button.className = 'fas fa-eye-slash';
        } else {
            field.type = 'password';
            button.className = 'fas fa-eye';
        }
    }

    // Password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = this.value;

        if (newPassword !== confirmPassword) {
            this.setCustomValidity('Password tidak cocok');
        } else {
            this.setCustomValidity('');
        }
    });

    // Phone number formatting
    document.getElementById('no_telepon').addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value.startsWith('62')) {
            value = '0' + value.substring(2);
        }
        this.value = value;
    });
</script>

<?php include '../includes/footer.php'; ?>