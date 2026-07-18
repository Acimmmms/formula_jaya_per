<?php

define('BASE_URL', '/KKP');
require_once __DIR__ . '/includes/auth.php';

redirectIfLoggedIn();

$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
$styleVer = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Formula Jaya Per</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $styleVer ?>">
</head>
<body class="login-body">

    <div class="login-wrapper">
        <div class="login-left d-none d-lg-flex">
            <div class="login-left-content">
                <div class="brand-logo mb-4">
                    <i class="fas fa-car-side fa-4x text-white"></i>
                </div>
                <h1 class="text-white fw-bold">Formula Jaya Per</h1>
                <p class="text-white-50 fs-5">Sistem Manajemen Bengkel Mobil</p>
                <hr class="border-white-50 my-4">
                <ul class="list-unstyled text-white-50">
                    <li class="mb-2"><i class="fas fa-check-circle text-warning me-2"></i> Manajemen Pelanggan & Kendaraan</li>
                    <li class="mb-2"><i class="fas fa-check-circle text-warning me-2"></i> Manajemen Servis & Transaksi</li>
                    <li class="mb-2"><i class="fas fa-check-circle text-warning me-2"></i> Manajemen Stok Suku Cadang</li>
                    <li class="mb-2"><i class="fas fa-check-circle text-warning me-2"></i> Laporan & Analisis Data</li>
                </ul>
            </div>
        </div>

        <div class="login-right">
            <div class="login-form-container">
                <div class="text-center d-lg-none mb-4">
                    <i class="fas fa-car-side fa-3x text-primary"></i>
                    <h4 class="fw-bold mt-2 text-primary">Formula Jaya Per</h4>
                </div>

                <h3 class="fw-bold mb-1">Selamat Datang!</h3>
                <p class="text-muted mb-4">Masuk ke akun Anda untuk melanjutkan</p>

                <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['logout']) && $_GET['logout'] == '1'): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <div>Anda berhasil keluar dari sistem.</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>/login.php" method="POST" id="loginForm">
                    <?= csrfInput() ?>

                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input 
                                type="text" 
                                class="form-control border-start-0 ps-0" 
                                id="username" 
                                name="username" 
                                placeholder="Masukkan username"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                required
                                autocomplete="username"
                            >
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input 
                                type="password" 
                                class="form-control border-start-0 border-end-0 ps-0" 
                                id="password" 
                                name="password" 
                                placeholder="Masukkan password"
                                required
                                autocomplete="current-password"
                            >
                            <button class="btn btn-light border toggle-password" type="button" data-target="password">
                                <i class="fas fa-eye text-muted" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold fs-6" id="btnLogin">
                        <i class="fas fa-sign-in-alt me-2"></i> Masuk
                    </button>
                </form>

                <p class="text-center text-muted small mt-4 mb-0">
                    &copy; <?= date('Y') ?> Formula Jaya Per. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('.toggle-password').addEventListener('click', function () {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('btnLogin');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses...';
        });
    </script>
</body>
</html>
