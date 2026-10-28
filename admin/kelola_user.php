<?php
// admin/kelola_user.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$page_title = "Kelola User - Admin";

// Handle actions
$action = $_GET['action'] ?? 'list';
$user_id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $password = $_POST['password'];
                $nama_lengkap = sanitize($_POST['nama_lengkap']);
                $no_telepon = sanitize($_POST['no_telepon']);
                $alamat = sanitize($_POST['alamat']);
                $role = sanitize($_POST['role']);

                // Validation
                $error = '';
                if (empty($username) || empty($email) || empty($password) || empty($nama_lengkap)) {
                    $error = 'Field yang wajib harus diisi';
                } elseif (!isValidEmail($email)) {
                    $error = 'Format email tidak valid';
                } elseif (strlen($password) < 6) {
                    $error = 'Password minimal 6 karakter';
                } else {
                    // Check if username or email exists
                    $query = "SELECT id FROM users WHERE username = :username OR email = :email";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $email);
                    $stmt->execute();

                    if ($stmt->fetch()) {
                        $error = 'Username atau email sudah terdaftar';
                    }
                }

                if (empty($error)) {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    $query = "INSERT INTO users (username, email, password, nama_lengkap, no_telepon, alamat, role) 
                             VALUES (:username, :email, :password, :nama_lengkap, :no_telepon, :alamat, :role)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':nama_lengkap', $nama_lengkap);
                    $stmt->bindParam(':no_telepon', $no_telepon);
                    $stmt->bindParam(':alamat', $alamat);
                    $stmt->bindParam(':role', $role);

                    if ($stmt->execute()) {
                        setAlert('success', 'User berhasil ditambahkan');
                    } else {
                        setAlert('danger', 'Gagal menambahkan user');
                    }
                } else {
                    setAlert('danger', $error);
                }
                header('Location: kelola_user.php');
                exit();
                break;

            case 'edit':
                $id = $_POST['id'];
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $nama_lengkap = sanitize($_POST['nama_lengkap']);
                $no_telepon = sanitize($_POST['no_telepon']);
                $alamat = sanitize($_POST['alamat']);
                $role = sanitize($_POST['role']);

                $query = "UPDATE users SET username = :username, email = :email, nama_lengkap = :nama_lengkap, 
                         no_telepon = :no_telepon, alamat = :alamat, role = :role WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':nama_lengkap', $nama_lengkap);
                $stmt->bindParam(':no_telepon', $no_telepon);
                $stmt->bindParam(':alamat', $alamat);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':id', $id);

                if ($stmt->execute()) {
                    setAlert('success', 'User berhasil diupdate');
                } else {
                    setAlert('danger', 'Gagal mengupdate user');
                }
                header('Location: kelola_user.php');
                exit();
                break;

            case 'reset_password':
                $id = $_POST['id'];
                $new_password = password_hash('password', PASSWORD_BCRYPT);

                $query = "UPDATE users SET password = :password WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $new_password);
                $stmt->bindParam(':id', $id);

                if ($stmt->execute()) {
                    setAlert('success', 'Password berhasil direset ke "password"');
                } else {
                    setAlert('danger', 'Gagal mereset password');
                }
                header('Location: kelola_user.php');
                exit();
                break;

            case 'delete':
                $id = $_POST['id'];
                if ($id == $_SESSION['user_id']) {
                    setAlert('danger', 'Tidak dapat menghapus akun sendiri');
                } else {
                    $query = "DELETE FROM users WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $id);

                    if ($stmt->execute()) {
                        setAlert('success', 'User berhasil dihapus');
                    } else {
                        setAlert('danger', 'Gagal menghapus user');
                    }
                }
                header('Location: kelola_user.php');
                exit();
                break;
        }
    }
}

// Get user for edit
$user = null;
if ($action === 'edit' && $user_id) {
    $user = getUserById($user_id);
}

// Get all users with filters
$role_filter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($role_filter) {
    $query .= " AND role = :role";
    $params['role'] = $role_filter;
}

if ($search) {
    $query .= " AND (username LIKE :search OR email LIKE :search OR nama_lengkap LIKE :search)";
    $params['search'] = "%$search%";
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindParam(":$key", $value);
}
$stmt->execute();
$users = $stmt->fetchAll();

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
                        <a class="nav-link active" href="kelola_user.php">
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
                <h1 class="h2">Kelola User</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Tambah User
                    </a>
                </div>
            </div>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <!-- Form Add/Edit -->
                <div class="card-custom mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-<?= $action === 'add' ? 'plus' : 'edit' ?> me-2"></i>
                            <?= $action === 'add' ? 'Tambah' : 'Edit' ?> User
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?= $action ?>">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username *</label>
                                        <input type="text" class="form-control" id="username" name="username"
                                            value="<?= $user ? htmlspecialchars($user['username']) : '' ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                            value="<?= $user ? htmlspecialchars($user['email']) : '' ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nama_lengkap" class="form-label">Nama Lengkap *</label>
                                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap"
                                            value="<?= $user ? htmlspecialchars($user['nama_lengkap']) : '' ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="no_telepon" class="form-label">No. Telepon</label>
                                        <input type="text" class="form-control" id="no_telepon" name="no_telepon"
                                            value="<?= $user ? htmlspecialchars($user['no_telepon']) : '' ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role *</label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="pelanggan" <?= ($user && $user['role'] === 'pelanggan') ? 'selected' : '' ?>>Pelanggan</option>
                                            <option value="admin" <?= ($user && $user['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                                            <option value="pemilik" <?= ($user && $user['role'] === 'pemilik') ? 'selected' : '' ?>>Pemilik</option>
                                        </select>
                                    </div>
                                </div>
                                <?php if ($action === 'add'): ?>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password" class="form-label">Password *</label>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                            <small class="text-muted">Minimal 6 karakter</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="alamat" class="form-label">Alamat</label>
                                <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= $user ? htmlspecialchars($user['alamat']) : '' ?></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i><?= $action === 'add' ? 'Tambah' : 'Update' ?>
                                </button>
                                <a href="kelola_user.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="card-custom mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="role" class="form-label">Filter Role</label>
                            <select class="form-select" name="role" id="role">
                                <option value="">Semua Role</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="pelanggan" <?= $role_filter === 'pelanggan' ? 'selected' : '' ?>>Pelanggan</option>
                                <option value="pemilik" <?= $role_filter === 'pemilik' ? 'selected' : '' ?>>Pemilik</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">Cari User</label>
                            <input type="text" class="form-control" name="search" id="search"
                                placeholder="Username, email, atau nama" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users List -->
            <div class="card-custom">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Daftar User (<?= count($users) ?>)
                    </h5>
                    <?php if ($role_filter || $search): ?>
                        <a href="kelola_user.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Reset Filter
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Tidak ada user ditemukan</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Nama Lengkap</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Terdaftar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user_item): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($user_item['username']) ?></strong>
                                                <?php if ($user_item['id'] == $_SESSION['user_id']): ?>
                                                    <span class="badge bg-info">Anda</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($user_item['nama_lengkap']) ?></td>
                                            <td><?= htmlspecialchars($user_item['email']) ?></td>
                                            <td>
                                                <?php
                                                $role_class = '';
                                                switch ($user_item['role']) {
                                                    case 'admin':
                                                        $role_class = 'danger';
                                                        break;
                                                    case 'pemilik':
                                                        $role_class = 'warning';
                                                        break;
                                                    case 'pelanggan':
                                                        $role_class = 'success';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge bg-<?= $role_class ?>"><?= ucfirst($user_item['role']) ?></span>
                                            </td>
                                            <td><?= formatDate($user_item['created_at']) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?action=edit&id=<?= $user_item['id'] ?>"
                                                        class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-warning"
                                                        onclick="resetPassword(<?= $user_item['id'] ?>)"
                                                        title="Reset Password">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <?php if ($user_item['id'] != $_SESSION['user_id']): ?>
                                                        <button type="button" class="btn btn-outline-danger"
                                                            onclick="deleteUser(<?= $user_item['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
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
        </main>
    </div>
</div>

<!-- Action Forms -->
<form id="resetPasswordForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="id" id="resetPasswordId">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
    function resetPassword(id) {
        if (confirm('Reset password user ini ke "password"?')) {
            document.getElementById('resetPasswordId').value = id;
            document.getElementById('resetPasswordForm').submit();
        }
    }

    function deleteUser(id) {
        if (confirm('Apakah Anda yakin ingin menghapus user ini? Semua data terkait akan ikut terhapus.')) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>