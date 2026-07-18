<?php
$styleVer = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Formula Jaya Per') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= $styleVer ?>">
</head>
<body class="dashboard-body">

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-car-side fa-2x text-warning"></i>
        <div class="ms-2">
            <h6 class="mb-0 fw-bold text-white">Formula Jaya Per</h6>
            <small class="text-white-50">Bengkel Mobil</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php
        $role = $user['role'] ?? '';
        $isAdmin = $role === 'admin';
        $isMechanic = $role === 'mekanik';
        $isOwner = $role === 'owner';
        $showDashboard = $isAdmin;
        $showDataMaster = $isAdmin;
        $showServis = $isAdmin || $isMechanic;
        $showStok = $isAdmin;
        $showTransaksi = $isAdmin;
        $showLaporan = $isAdmin || $isOwner;
        $showMainSection = $showDataMaster || $showServis || $showStok || $showTransaksi;
        ?>


        <?php if ($showDashboard): ?>
        <a href="<?= BASE_URL ?>/dashboard.php" class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <?php endif; ?>
        <?php if ($showMainSection): ?>
        <div class="nav-divider">Menu Utama</div>
        <?php endif; ?>
        <?php if ($showDataMaster): ?>
        <a href="<?= BASE_URL ?>/pages/pelanggan.php" class="nav-item <?= ($activePage ?? '') === 'pelanggan' ? 'active' : '' ?>">
            <i class="fas fa-users"></i><span>Pelanggan</span>
        </a>
        <a href="<?= BASE_URL ?>/pages/kendaraan.php" class="nav-item <?= ($activePage ?? '') === 'kendaraan' ? 'active' : '' ?>">
            <i class="fas fa-car"></i><span>Kendaraan</span>
        </a>
        <?php endif; ?>
        <?php if ($showServis): ?>
        <a href="<?= BASE_URL ?>/pages/servis.php" class="nav-item <?= ($activePage ?? '') === 'servis' ? 'active' : '' ?>">
            <i class="fas fa-wrench"></i><span>Servis</span>
        </a>
        <?php endif; ?>
        <?php if ($showStok): ?>
        <a href="<?= BASE_URL ?>/pages/stok.php" class="nav-item <?= ($activePage ?? '') === 'stok' ? 'active' : '' ?>">
            <i class="fas fa-box"></i><span>Stok Suku Cadang</span>
        </a>
        <?php endif; ?>
        <?php if ($showTransaksi): ?>
        <a href="<?= BASE_URL ?>/pages/transaksi.php" class="nav-item <?= ($activePage ?? '') === 'transaksi' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i><span>Transaksi</span>
        </a>
        <?php endif; ?>
        <?php if ($showLaporan): ?>
        <div class="nav-divider">Laporan</div>
        <a href="<?= BASE_URL ?>/pages/laporan.php" class="nav-item <?= ($activePage ?? '') === 'laporan' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i><span>Laporan</span>
        </a>
        <?php endif; ?>
        <?php
        // Only show admin settings when user is admin. Hide WhatsApp link if integration disabled.
        $whEnabled = false;
        $wh_env = getenv('WHATSAPP_ENABLED');
        if ($wh_env !== false) {
            $whEnabled = filter_var($wh_env, FILTER_VALIDATE_BOOLEAN);
        }
        if ($isAdmin): ?>
        <div class="nav-divider">Pengaturan</div>
        <a href="<?= BASE_URL ?>/pages/users.php" class="nav-item <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i><span>Manajemen User</span>
        </a>
        <?php if ($whEnabled): ?>
        <a href="<?= BASE_URL ?>/pages/whatsapp.php" class="nav-item <?= ($activePage ?? '') === 'whatsapp' ? 'active' : '' ?>">
            <i class="fab fa-whatsapp"></i><span>WhatsApp Notifikasi</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </nav>
</div>

<div class="main-content" id="mainContent">
    <div class="topbar">
        <button class="btn btn-sm sidebar-toggle-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('mainContent').classList.toggle('expanded')">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <div class="topbar-right">
            <span class="text-muted small me-3 d-none d-md-inline">
                <i class="fas fa-calendar-alt me-1"></i><?= date('d F Y') ?>
            </span>
            <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                    <div class="user-avatar"><?= strtoupper(substr($user['nama_lengkap'] ?? 'U', 0, 1)) ?></div>
                    <div class="text-start d-none d-md-block">
                        <div class="fw-semibold small"><?= htmlspecialchars($user['nama_lengkap'] ?? '') ?></div>
                        <div class="text-muted" style="font-size:11px"><?= ucfirst($user['role'] ?? '') ?></div>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php" onclick="return confirm('Yakin ingin keluar?')">
                        <i class="fas fa-sign-out-alt me-2"></i>Keluar
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="page-content">
 