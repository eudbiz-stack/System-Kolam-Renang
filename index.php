<?php
// index.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Get ticket types for display
$ticket_types = getTicketTypes();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kolam Renang Licia - Sistem Pemesanan Tiket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800"><rect fill="%234fc3f7" width="1200" height="800"/><circle fill="%2329b6f6" cx="200" cy="200" r="100"/><circle fill="%2303a9f4" cx="800" cy="400" r="150"/><circle fill="%2381d4fa" cx="1000" cy="600" r="80"/></svg>');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            color: white;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
        }

        .navbar-brand {
            font-weight: bold;
            color: #667eea !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .price-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-weight: bold;
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 1rem;
        }

        .section-title {
            position: relative;
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        footer {
            background: #2c3e50;
            color: white;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-swimming-pool me-2"></i>Kolam Renang Licia</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#tickets">Tiket</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#facilities">Fasilitas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Kontak</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?= $_SESSION['nama_lengkap'] ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= $_SESSION['role'] ?>/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                    </a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="<?= $_SESSION['role'] ?>/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/register.php">Daftar</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Selamat Datang di Kolam Renang Licia</h1>
                    <p class="lead mb-4">Nikmati pengalaman berenang yang menyegarkan dengan fasilitas terbaik dan harga terjangkau. Pesan tiket Anda sekarang!</p>
                    <div class="d-flex gap-3">
                        <?php if (isLoggedIn() && $_SESSION['role'] === 'pelanggan'): ?>
                            <a href="pelanggan/pesan_tiket.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-ticket-alt me-2"></i>Pesan Tiket
                            </a>
                        <?php else: ?>
                            <a href="auth/register.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                            </a>
                            <a href="auth/login.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-swimming-pool" style="font-size: 200px; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tickets Section -->
    <section id="tickets" class="py-5">
        <div class="container">
            <h2 class="section-title">Jenis Tiket</h2>
            <div class="row">
                <?php foreach ($ticket_types as $ticket): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-light text-center">
                                <h5 class="card-title mb-0"><?= htmlspecialchars($ticket['nama_tiket']) ?></h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="price-badge mb-3"><?= formatCurrency($ticket['harga']) ?></div>
                                <p class="card-text"><?= htmlspecialchars($ticket['deskripsi']) ?></p>
                                <p class="text-muted"><i class="fas fa-clock me-1"></i>Durasi: <?= htmlspecialchars($ticket['durasi']) ?></p>
                            </div>
                            <div class="card-footer bg-transparent text-center">
                                <?php if (isLoggedIn() && $_SESSION['role'] === 'pelanggan'): ?>
                                    <a href="pelanggan/pesan_tiket.php?ticket=<?= $ticket['id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-shopping-cart me-1"></i>Pesan
                                    </a>
                                <?php else: ?>
                                    <a href="auth/login.php" class="btn btn-outline-primary">
                                        <i class="fas fa-sign-in-alt me-1"></i>Login untuk Pesan
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Facilities Section -->
    <section id="facilities" class="py-5 bg-light">
        <div class="container">
            <h2 class="section-title">Fasilitas</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="feature-icon">
                            <i class="fas fa-swimming-pool"></i>
                        </div>
                        <h5>Kolam Renang Standar</h5>
                        <p>Kolam renang dengan panjang 25 meter, kedalaman bervariasi, cocok untuk semua usia.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="feature-icon">
                            <i class="fas fa-child"></i>
                        </div>
                        <h5>Kolam Anak</h5>
                        <p>Area khusus anak-anak dengan kedalaman yang aman dan mainan air yang menarik.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="feature-icon">
                            <i class="fas fa-shower"></i>
                        </div>
                        <h5>Kamar Mandi & Loker</h5>
                        <p>Fasilitas kamar mandi yang bersih dan loker untuk menyimpan barang bawaan.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="feature-icon">
                            <i class="fas fa-coffee"></i>
                        </div>
                        <h5>Kantin</h5>
                        <p>Tersedia kantin dengan berbagai makanan dan minuman segar.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="feature-icon">
                            <i class="fas fa-parking"></i>
                        </div>
                        <h5>Parkir Luas</h5>
                        <p>Area parkir yang luas dan aman untuk kendaraan bermotor dan mobil.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="feature-icon">
                            <i class="fas fa-life-ring"></i>
                        </div>
                        <h5>Keamanan 24/7</h5>
                        <p>Penjaga keamanan dan lifeguard yang berpengalaman selalu siap membantu.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5">
        <div class="container">
            <h2 class="section-title">Kontak Kami</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5><i class="fas fa-map-marker-alt me-2 text-primary"></i>Alamat</h5>
                            <p><?= getSetting('address') ?: 'Jl. Kolam Renang No. 123, Jakarta' ?></p>

                            <h5><i class="fas fa-phone me-2 text-primary"></i>Telepon</h5>
                            <p><?= getSetting('contact_phone') ?: '021-12345678' ?></p>

                            <h5><i class="fas fa-envelope me-2 text-primary"></i>Email</h5>
                            <p><?= getSetting('contact_email') ?: 'info@kolamrenang.com' ?></p>

                            <h5><i class="fas fa-clock me-2 text-primary"></i>Jam Operasional</h5>
                            <p>Senin - Jumat: 06:00 - 21:00<br>
                                Sabtu - Minggu: 06:00 - 22:00</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Kirim Pesan</h5>
                            <form>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nama</label>
                                    <input type="text" class="form-control" id="name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="message" class="form-label">Pesan</label>
                                    <textarea class="form-control" id="message" rows="4" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Kirim Pesan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 Kolam Renang Licia. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255,255,255,0.98)';
            } else {
                navbar.style.background = 'rgba(255,255,255,0.95)';
            }
        });
    </script>
</body>

</html>