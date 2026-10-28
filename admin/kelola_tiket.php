<?php
// admin/kelola_tiket.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$page_title = "Kelola Tiket - Admin";

// Handle actions
$action = $_GET['action'] ?? 'list';
$ticket_id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $nama_tiket = sanitize($_POST['nama_tiket']);
                $harga = floatval($_POST['harga']);
                $deskripsi = sanitize($_POST['deskripsi']);
                $durasi = sanitize($_POST['durasi']);
                $status = sanitize($_POST['status']);

                $query = "INSERT INTO jenis_tiket (nama_tiket, harga, deskripsi, durasi, status) 
                         VALUES (:nama_tiket, :harga, :deskripsi, :durasi, :status)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':nama_tiket', $nama_tiket);
                $stmt->bindParam(':harga', $harga);
                $stmt->bindParam(':deskripsi', $deskripsi);
                $stmt->bindParam(':durasi', $durasi);
                $stmt->bindParam(':status', $status);

                if ($stmt->execute()) {
                    setAlert('success', 'Tiket berhasil ditambahkan');
                } else {
                    setAlert('danger', 'Gagal menambahkan tiket');
                }
                header('Location: kelola_tiket.php');
                exit();
                break;

            case 'edit':
                $id = $_POST['id'];
                $nama_tiket = sanitize($_POST['nama_tiket']);
                $harga = floatval($_POST['harga']);
                $deskripsi = sanitize($_POST['deskripsi']);
                $durasi = sanitize($_POST['durasi']);
                $status = sanitize($_POST['status']);

                $query = "UPDATE jenis_tiket SET nama_tiket = :nama_tiket, harga = :harga, 
                         deskripsi = :deskripsi, durasi = :durasi, status = :status 
                         WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':nama_tiket', $nama_tiket);
                $stmt->bindParam(':harga', $harga);
                $stmt->bindParam(':deskripsi', $deskripsi);
                $stmt->bindParam(':durasi', $durasi);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $id);

                if ($stmt->execute()) {
                    setAlert('success', 'Tiket berhasil diupdate');
                } else {
                    setAlert('danger', 'Gagal mengupdate tiket');
                }
                header('Location: kelola_tiket.php');
                exit();
                break;

            case 'delete':
                $id = $_POST['id'];
                $query = "DELETE FROM jenis_tiket WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id);

                if ($stmt->execute()) {
                    setAlert('success', 'Tiket berhasil dihapus');
                } else {
                    setAlert('danger', 'Gagal menghapus tiket');
                }
                header('Location: kelola_tiket.php');
                exit();
                break;
        }
    }
}

// Get ticket for edit
$ticket = null;
if ($action === 'edit' && $ticket_id) {
    $ticket = getTicketById($ticket_id);
}

// Get all tickets
$query = "SELECT * FROM jenis_tiket ORDER BY nama_tiket";
$stmt = $db->prepare($query);
$stmt->execute();
$tickets = $stmt->fetchAll();

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
                        <a class="nav-link active" href="kelola_tiket.php">
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
                <h1 class="h2">Kelola Tiket</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Tambah Tiket
                    </a>
                </div>
            </div>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <!-- Form Add/Edit -->
                <div class="card-custom mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-<?= $action === 'add' ? 'plus' : 'edit' ?> me-2"></i>
                            <?= $action === 'add' ? 'Tambah' : 'Edit' ?> Tiket
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?= $action ?>">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="id" value="<?= $ticket['id'] ?>">
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nama_tiket" class="form-label">Nama Tiket *</label>
                                        <input type="text" class="form-control" id="nama_tiket" name="nama_tiket"
                                            value="<?= $ticket ? htmlspecialchars($ticket['nama_tiket']) : '' ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="harga" class="form-label">Harga *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" id="harga" name="harga"
                                                value="<?= $ticket ? $ticket['harga'] : '' ?>" min="0" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="durasi" class="form-label">Durasi</label>
                                        <input type="text" class="form-control" id="durasi" name="durasi"
                                            placeholder="Contoh: 1 hari, 3 jam"
                                            value="<?= $ticket ? htmlspecialchars($ticket['durasi']) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="aktif" <?= ($ticket && $ticket['status'] === 'aktif') ? 'selected' : '' ?>>Aktif</option>
                                            <option value="nonaktif" <?= ($ticket && $ticket['status'] === 'nonaktif') ? 'selected' : '' ?>>Non-aktif</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="deskripsi" class="form-label">Deskripsi</label>
                                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?= $ticket ? htmlspecialchars($ticket['deskripsi']) : '' ?></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i><?= $action === 'add' ? 'Tambah' : 'Update' ?>
                                </button>
                                <a href="kelola_tiket.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tickets List -->
            <div class="card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Daftar Tiket
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tickets)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada tiket</p>
                            <a href="?action=add" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Tambah Tiket Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Tiket</th>
                                        <th>Harga</th>
                                        <th>Durasi</th>
                                        <th>Status</th>
                                        <th>Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($ticket['nama_tiket']) ?></strong>
                                                <?php if ($ticket['deskripsi']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($ticket['deskripsi']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= formatCurrency($ticket['harga']) ?></td>
                                            <td><?= htmlspecialchars($ticket['durasi']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $ticket['status'] === 'aktif' ? 'success' : 'secondary' ?>">
                                                    <?= ucfirst($ticket['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= formatDate($ticket['created_at']) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?action=edit&id=<?= $ticket['id'] ?>"
                                                        class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger btn-delete"
                                                        onclick="deleteTicket(<?= $ticket['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
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
        </main>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
    function deleteTicket(id) {
        if (confirm('Apakah Anda yakin ingin menghapus tiket ini?')) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>