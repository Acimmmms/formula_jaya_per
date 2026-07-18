<?php
define('BASE_URL', '/KKP');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
requireLogin();
$user = getUser();
$role = $user['role'] ?? '';

// mekanik hanya boleh akses servis
if ($role === 'mekanik') {
    header('Location: ' . BASE_URL . '/pages/servis.php');
    exit;
}

$totPelanggan = $pdo->query("SELECT COUNT(*) FROM pelanggan")->fetchColumn();
$totKendaraan = $pdo->query("SELECT COUNT(*) FROM kendaraan")->fetchColumn();
$totServis = $pdo->query("SELECT COUNT(*) FROM servis")->fetchColumn();
$servisHariIni = $pdo->query("SELECT COUNT(*) FROM servis WHERE DATE(tanggal_masuk)=CURDATE()")->fetchColumn();
$servisBerjalan = $pdo->query("SELECT COUNT(*) FROM servis WHERE status IN ('masuk','proses')")->fetchColumn();
$servisSelesai = $pdo->query("SELECT COUNT(*) FROM servis WHERE status IN ('selesai','diambil')")->fetchColumn();
$totStok = $pdo->query("SELECT COUNT(*) FROM stok")->fetchColumn();
$pendapatanBulan = $pdo->query("SELECT COALESCE(SUM(total),0) FROM transaksi WHERE DATE_FORMAT(tanggal,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m') AND status_bayar='lunas'")->fetchColumn();

$statusRows = $pdo->query("SELECT status, COUNT(*) AS total FROM servis GROUP BY status")->fetchAll();
$statusChart = ['masuk' => 0, 'proses' => 0, 'selesai' => 0, 'diambil' => 0];
foreach ($statusRows as $row) {
    $statusChart[$row['status']] = (int)$row['total'];
}

$monthlyRevenueRows = $pdo->query("SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode, COALESCE(SUM(total),0) AS total FROM transaksi WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) AND status_bayar='lunas' GROUP BY DATE_FORMAT(tanggal, '%Y-%m') ORDER BY periode")->fetchAll();
$monthlyRevenueLabels = [];
$monthlyRevenueData = [];
foreach ($monthlyRevenueRows as $row) {
    $monthlyRevenueLabels[] = date('M Y', strtotime($row['periode'] . '-01'));
    $monthlyRevenueData[] = (float)$row['total'];
}

$topPelanggan = $pdo->query("SELECT p.nama, COUNT(s.id) AS total_servis, COALESCE(SUM(t.total),0) AS total_transaksi FROM pelanggan p LEFT JOIN servis s ON s.pelanggan_id = p.id LEFT JOIN transaksi t ON t.servis_id = s.id GROUP BY p.id, p.nama ORDER BY total_servis DESC, total_transaksi DESC LIMIT 5")->fetchAll();
$avgNilaiTransaksi = $pdo->query("SELECT COALESCE(AVG(total),0) FROM transaksi WHERE status_bayar='lunas'")->fetchColumn();
$rasioServisSelesai = $totServis > 0 ? round(($servisSelesai / $totServis) * 100, 1) : 0;

$servisTerbaru = $pdo->query("SELECT s.*,p.nama,k.no_polisi,k.merk FROM servis s JOIN pelanggan p ON s.pelanggan_id=p.id JOIN kendaraan k ON s.kendaraan_id=k.id ORDER BY s.id DESC LIMIT 5")->fetchAll();
$stokHampirHabis = $pdo->query("SELECT * FROM stok WHERE stok <= 5 ORDER BY stok ASC LIMIT 5")->fetchAll();

$statusLabel = ['masuk'=>'Masuk','proses'=>'Proses','selesai'=>'Selesai','diambil'=>'Diambil'];
$statusClass = ['masuk'=>'bg-info','proses'=>'bg-warning text-dark','selesai'=>'bg-success','diambil'=>'bg-secondary'];
$styleVer = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Formula Jaya Per</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        <a href="<?= BASE_URL ?>/dashboard.php" class="nav-item active">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <div class="nav-divider">Menu Utama</div>
        <?php $role = $user['role'] ?? ''; ?>
        <?php if ($role === 'admin'): ?>
        <a href="<?= BASE_URL ?>/pages/pelanggan.php" class="nav-item">
            <i class="fas fa-users"></i><span>Pelanggan</span>
        </a>
        <a href="<?= BASE_URL ?>/pages/kendaraan.php" class="nav-item">
            <i class="fas fa-car"></i><span>Kendaraan</span>
        </a>
        <?php endif; ?>
        <?php if (in_array($role, ['admin', 'mekanik'], true)): ?>
        <a href="<?= BASE_URL ?>/pages/servis.php" class="nav-item">
            <i class="fas fa-wrench"></i><span>Servis</span>
        </a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
        <a href="<?= BASE_URL ?>/pages/stok.php" class="nav-item">
            <i class="fas fa-box"></i><span>Stok Suku Cadang</span>
        </a>
        <a href="<?= BASE_URL ?>/pages/transaksi.php" class="nav-item">
            <i class="fas fa-receipt"></i><span>Transaksi</span>
        </a>
        <?php endif; ?>
        <div class="nav-divider">Laporan</div>
        <a href="<?= BASE_URL ?>/pages/laporan.php" class="nav-item">
            <i class="fas fa-chart-bar"></i><span>Laporan</span>
        </a>
        <?php if ($user['role'] === 'admin'): ?>
        <div class="nav-divider">Pengaturan</div>
        <a href="<?= BASE_URL ?>/pages/users.php" class="nav-item">
            <i class="fas fa-users-cog"></i><span>Manajemen User</span>
        </a>
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
                    <div class="user-avatar"><?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?></div>
                    <div class="text-start d-none d-md-block">
                        <div class="fw-semibold small"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                        <div class="text-muted" style="font-size:11px"><?= ucfirst($user['role']) ?></div>
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
        <div class="welcome-banner mb-4">
            <div>
                <h4 class="fw-bold mb-1">Selamat Datang, <?= htmlspecialchars($user['nama_lengkap']) ?>! 👋</h4>
                <p class="mb-0 text-white-50">Berikut ringkasan data bengkel hari ini, <?= date('d F Y') ?>.</p>
            </div>
            <i class="fas fa-car-side fa-3x text-white-50 d-none d-md-block"></i>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary-light"><i class="fas fa-users text-primary"></i></div>
                    <div class="stat-info">
                        <h3 class="stat-number"><?= $totPelanggan ?></h3>
                        <p class="stat-label">Total Pelanggan</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success-light"><i class="fas fa-wrench text-success"></i></div>
                    <div class="stat-info">
                        <h3 class="stat-number"><?= $servisHariIni ?></h3>
                        <p class="stat-label">Servis Masuk Hari Ini</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-warning-light"><i class="fas fa-box text-warning"></i></div>
                    <div class="stat-info">
                        <h3 class="stat-number"><?= $servisBerjalan ?></h3>
                        <p class="stat-label">Servis Berjalan</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-danger-light"><i class="fas fa-money-bill-wave text-danger"></i></div>
                    <div class="stat-info">
                        <h3 class="stat-number" style="font-size:1.1rem">Rp <?= number_format($pendapatanBulan, 0, ',', '.') ?></h3>
                        <p class="stat-label">Pendapatan Bulan Ini</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="metric-panel">
                    <div class="metric-panel__label">Total Kendaraan</div>
                    <div class="metric-panel__value"><?= $totKendaraan ?></div>
                    <div class="metric-panel__meta">Terkoneksi ke data pelanggan aktif</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-panel">
                    <div class="metric-panel__label">Rasio Servis Selesai</div>
                    <div class="metric-panel__value"><?= $rasioServisSelesai ?>%</div>
                    <div class="metric-panel__meta">Dari total <?= $totServis ?> order servis</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-panel">
                    <div class="metric-panel__label">Rata-rata Transaksi Lunas</div>
                    <div class="metric-panel__value">Rp <?= number_format($avgNilaiTransaksi, 0, ',', '.') ?></div>
                    <div class="metric-panel__meta">Nilai transaksi yang sudah dibayar</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100 analytics-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="fas fa-chart-line text-primary me-2"></i>Analitik Pendapatan 6 Bulan</span>
                        <span class="analytics-chip">Live overview</span>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap">
                            <canvas id="revenueChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100 analytics-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="fas fa-chart-pie text-primary me-2"></i>Status Servis</span>
                        <span class="analytics-chip analytics-chip--soft">Distribusi</span>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrap chart-wrap--compact">
                            <canvas id="statusChart" height="220"></canvas>
                        </div>
                        <div class="status-legend mt-3">
                            <div><span class="legend-dot bg-info"></span>Masuk: <?= $statusChart['masuk'] ?></div>
                            <div><span class="legend-dot bg-warning"></span>Proses: <?= $statusChart['proses'] ?></div>
                            <div><span class="legend-dot bg-success"></span>Selesai: <?= $statusChart['selesai'] ?></div>
                            <div><span class="legend-dot bg-secondary"></span>Diambil: <?= $statusChart['diambil'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Servis Terbaru -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="fas fa-wrench text-primary me-2"></i>Servis Terbaru</span>
                        <?php if (in_array($role, ['admin', 'mekanik'], true)): ?>
                        <a href="<?= BASE_URL ?>/pages/servis.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th class="ps-3">No. Servis</th><th>Pelanggan</th><th>Kendaraan</th><th class="text-center">Status</th></tr></thead>
                            <tbody>
                            <?php if (empty($servisTerbaru)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data servis</td></tr>
                            <?php else: foreach ($servisTerbaru as $s): ?>
                                <tr>
                                    <td class="ps-3"><span class="badge bg-primary-subtle text-primary border"><?= htmlspecialchars($s['no_servis']) ?></span></td>
                                    <td class="fw-semibold small"><?= htmlspecialchars($s['nama']) ?></td>
                                    <td class="small"><?= htmlspecialchars($s['no_polisi'].' '.$s['merk']) ?></td>
                                    <td class="text-center"><span class="badge <?= $statusClass[$s['status']] ?? 'bg-secondary' ?>"><?= $statusLabel[$s['status']] ?? $s['status'] ?></span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Stok Hampir Habis -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Stok Hampir Habis</span>
                        <?php if ($role === 'admin'): ?>
                        <a href="<?= BASE_URL ?>/pages/stok.php" class="btn btn-sm btn-outline-danger">Kelola Stok</a>
                        <?php endif; ?>

                    </div>
                    <div class="card-body p-0">
                    <?php if (empty($stokHampirHabis)): ?>
                        <div class="text-center text-muted py-4"><i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>Semua stok mencukupi</div>
                    <?php else: foreach ($stokHampirHabis as $b): ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <span class="small fw-semibold"><?= htmlspecialchars($b['nama_barang']) ?></span>
                            <span class="badge <?= $b['stok'] == 0 ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= $b['stok'] ?> <?= $b['satuan'] ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="fas fa-star text-warning me-2"></i>Top Pelanggan</span>
                        <span class="text-muted small">Berdasarkan total servis</span>
                    </div>
                    <div class="card-body p-0">
                    <?php if (empty($topPelanggan)): ?>
                        <div class="text-center text-muted py-4">Belum ada data pelanggan</div>
                    <?php else: foreach ($topPelanggan as $index => $pelanggan): ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-3 <?= $index < count($topPelanggan) - 1 ? 'border-bottom' : '' ?>">
                            <div>
                                <div class="fw-semibold small"><?= htmlspecialchars($pelanggan['nama']) ?></div>
                                <div class="text-muted" style="font-size: 12px"><?= (int)$pelanggan['total_servis'] ?> servis</div>
                            </div>
                            <div class="fw-semibold text-primary">Rp <?= number_format($pelanggan['total_transaksi'], 0, ',', '.') ?></div>
                        </div>
                    <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="fas fa-bolt text-primary me-2"></i>Insight Cepat</span>
                        <span class="text-muted small">Ringkas untuk monitoring</span>
                    </div>
                    <div class="card-body">
                        <div class="insight-grid">
                            <div class="insight-item">
                                <div class="insight-item__label">Kendaraan aktif</div>
                                <div class="insight-item__value"><?= $totKendaraan ?></div>
                            </div>
                            <div class="insight-item">
                                <div class="insight-item__label">Stok jenis barang</div>
                                <div class="insight-item__value"><?= $totStok ?></div>
                            </div>
                            <div class="insight-item">
                                <div class="insight-item__label">Servis selesai</div>
                                <div class="insight-item__value"><?= $servisSelesai ?></div>
                            </div>
                            <div class="insight-item">
                                <div class="insight-item__label">Pendapatan bulan ini</div>
                                <div class="insight-item__value">Rp <?= number_format($pendapatanBulan, 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const monthlyLabels = <?= json_encode($monthlyRevenueLabels, JSON_UNESCAPED_UNICODE) ?>;
const monthlyData = <?= json_encode($monthlyRevenueData, JSON_UNESCAPED_UNICODE) ?>;
const statusData = <?= json_encode(array_values($statusChart), JSON_UNESCAPED_UNICODE) ?>;

const revenueCtx = document.getElementById('revenueChart');
if (revenueCtx) {
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: monthlyLabels.length ? monthlyLabels : ['Belum ada data'],
            datasets: [{
                label: 'Pendapatan',
                data: monthlyData.length ? monthlyData : [0],
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.12)',
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => ' Rp ' + context.parsed.y.toLocaleString('id-ID')
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => 'Rp ' + Number(value).toLocaleString('id-ID')
                    }
                }
            }
        }
    });
}

const statusCtx = document.getElementById('statusChart');
if (statusCtx) {
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Masuk', 'Proses', 'Selesai', 'Diambil'],
            datasets: [{
                data: statusData,
                backgroundColor: ['#0ea5e9', '#f59e0b', '#16a34a', '#64748b'],
                borderWidth: 0,
                hoverOffset: 8,
            }]
        },
        options: {
            cutout: '68%',
            plugins: { legend: { display: false } }
        }
    });
}
</script>
</body>
</html>
