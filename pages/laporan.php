<?php
define('BASE_URL', '/KKP');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
$user       = getUser();
// Ambil role, bersihkan spasi kiri-kanan (trim), dan jadikan huruf kecil (strtolower)
$roleRaw    = $user['role'] ?? '';
$role       = strtolower(trim($roleRaw)); 
$user = getUser();
$role = strtolower(trim($user['role'] ?? ''));

if ($role === 'mekanik') {
    header('Location: ' . BASE_URL . '/pages/servis.php');
    exit;
}

// Hapus kata 'true' agar pengecekannya sedikit lebih longgar
if (!in_array($role, ['admin', 'owner'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}
$pageTitle  = 'Laporan';
$activePage = 'laporan';

$bulan = $_GET['bulan'] ?? date('Y-m');
[$tahun, $bln] = explode('-', $bulan . '-01');

// Statisik umum
$totPelanggan = $pdo->query("SELECT COUNT(*) FROM pelanggan")->fetchColumn();
$totKendaraan = $pdo->query("SELECT COUNT(*) FROM kendaraan")->fetchColumn();
$totServis    = $pdo->query("SELECT COUNT(*) FROM servis")->fetchColumn();
$totStok      = $pdo->query("SELECT COUNT(*) FROM stok")->fetchColumn();

// Servis bulan ini
$servisBulan = $pdo->prepare("SELECT COUNT(*) FROM servis WHERE DATE_FORMAT(tanggal_masuk,'%Y-%m')=?");
$servisBulan->execute([$bulan]);
$servisBulanCount = $servisBulan->fetchColumn();

// Transaksi bulan ini
$trxBulan = $pdo->prepare("SELECT COUNT(*),COALESCE(SUM(total),0) FROM transaksi WHERE DATE_FORMAT(tanggal,'%Y-%m')=?");
$trxBulan->execute([$bulan]);
[$trxCount, $trxTotal] = $trxBulan->fetch(PDO::FETCH_NUM);

// Pendapatan per bulan (12 bulan terakhir)
$pendapatan = $pdo->query("SELECT DATE_FORMAT(tanggal,'%Y-%m') AS bln, SUM(total) AS total FROM transaksi WHERE tanggal >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY bln ORDER BY bln")->fetchAll();

// Servis per status bulan ini
$servisStatus = $pdo->prepare("SELECT status, COUNT(*) AS jml FROM servis WHERE DATE_FORMAT(tanggal_masuk,'%Y-%m')=? GROUP BY status");
$servisStatus->execute([$bulan]);
$servisStatusData = $servisStatus->fetchAll(PDO::FETCH_KEY_PAIR);

// Stok hampir habis
$stokHampirHabis = $pdo->query("SELECT * FROM stok WHERE stok <= 5 ORDER BY stok ASC LIMIT 10")->fetchAll();

// Servis terbaru bulan ini
$servisTerbaru = $pdo->prepare("SELECT s.*,p.nama,k.no_polisi,k.merk,k.model FROM servis s JOIN pelanggan p ON s.pelanggan_id=p.id JOIN kendaraan k ON s.kendaraan_id=k.id WHERE DATE_FORMAT(s.tanggal_masuk,'%Y-%m')=? ORDER BY s.id DESC LIMIT 10");
$servisTerbaru->execute([$bulan]);
$servisListBulan = $servisTerbaru->fetchAll();

$export = $_GET['export'] ?? '';
if ($export === 'servis_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-servis-' . $bulan . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['No Servis', 'Tanggal Masuk', 'Pelanggan', 'No Polisi', 'Merk', 'Model', 'Keluhan', 'Status']);
    foreach ($servisListBulan as $s) {
        fputcsv($out, [
            $s['no_servis'],
            $s['tanggal_masuk'],
            $s['nama'],
            $s['no_polisi'],
            $s['merk'],
            $s['model'],
            $s['keluhan'],
            $statusLabel[$s['status']] ?? $s['status'],
        ]);
    }
    fclose($out);
    exit;
}

if ($export === 'pendapatan_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-pendapatan-' . $bulan . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Periode', 'Total Transaksi', 'Total Pendapatan']);
    fputcsv($out, [$bulan, (int)$trxCount, (float)$trxTotal]);
    fputcsv($out, []);
    fputcsv($out, ['Bulan', 'Pendapatan']);
    foreach ($pendapatan as $p) {
        fputcsv($out, [$p['bln'], (float)$p['total']]);
    }
    fclose($out);
    exit;
}

$statusLabel = ['masuk'=>'Masuk','proses'=>'Proses','selesai'=>'Selesai','diambil'=>'Diambil'];
$statusClass = ['masuk'=>'bg-info','proses'=>'bg-warning text-dark','selesai'=>'bg-success','diambil'=>'bg-secondary'];

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="page-head">
    <h5 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Laporan</h5>
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="text-muted small me-1 mb-0">Periode:</label>
        <input type="month" name="bulan" class="form-control form-control-sm" value="<?= htmlspecialchars($bulan) ?>" style="width:160px">
        <button class="btn btn-sm btn-primary">Tampilkan</button>
        <a class="btn btn-sm btn-outline-success" href="?bulan=<?= urlencode($bulan) ?>&export=servis_csv"><i class="fas fa-file-csv me-1"></i>Export Servis</a>
        <a class="btn btn-sm btn-outline-success" href="?bulan=<?= urlencode($bulan) ?>&export=pendapatan_csv"><i class="fas fa-file-csv me-1"></i>Export Pendapatan</a>
    </form>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm kpi-card text-center py-3"><div class="text-primary fw-bold fs-4"><?= $totPelanggan ?></div><div class="text-muted small">Total Pelanggan</div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm kpi-card text-center py-3"><div class="text-info fw-bold fs-4"><?= $totKendaraan ?></div><div class="text-muted small">Total Kendaraan</div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm kpi-card text-center py-3"><div class="text-warning fw-bold fs-4"><?= $totServis ?></div><div class="text-muted small">Total Order Servis</div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm kpi-card text-center py-3"><div class="text-success fw-bold fs-4"><?= $totStok ?></div><div class="text-muted small">Jenis Suku Cadang</div></div></div>
</div>

<!-- Bulan ini -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-calendar text-primary me-2"></i>Ringkasan <?= date('F Y', strtotime($bulan.'-01')) ?></div>
            <div class="card-body">
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted small">Order Servis</span>
                    <span class="fw-semibold"><?= $servisBulanCount ?></span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted small">Transaksi</span>
                    <span class="fw-semibold"><?= $trxCount ?></span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted small">Pendapatan</span>
                    <span class="fw-bold text-success">Rp <?= number_format($trxTotal, 0, ',', '.') ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-wrench text-warning me-2"></i>Servis per Status (Bulan Ini)</div>
            <div class="card-body">
                <?php foreach ($statusLabel as $key => $lbl): $jml = $servisStatusData[$key] ?? 0; ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <span><span class="badge <?= $statusClass[$key] ?> me-2"><?= $lbl ?></span></span>
                    <span class="fw-semibold"><?= $jml ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Stok Hampir Habis</div>
            <div class="card-body p-0">
                <?php if (empty($stokHampirHabis)): ?>
                    <div class="text-center text-muted py-4 small">Semua stok mencukupi</div>
                <?php else: foreach ($stokHampirHabis as $b): ?>
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <span class="small"><?= htmlspecialchars($b['nama_barang']) ?></span>
                        <span class="badge <?= $b['stok'] == 0 ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= $b['stok'] ?> <?= $b['satuan'] ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Laporan Pendapatan Per Bulan -->
<?php if (!empty($pendapatan)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="fas fa-chart-line text-success me-2"></i>Pendapatan 12 Bulan Terakhir</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Bulan</th><th class="text-end pe-3">Total Pendapatan</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pendapatan as $p): ?>
            <tr>
                <td class="ps-3"><?= date('F Y', strtotime($p['bln'].'-01')) ?></td>
                <td class="text-end pe-3 fw-semibold text-success">Rp <?= number_format($p['total'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Servis Bulan Ini -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="fas fa-list text-primary me-2"></i>Order Servis <?= date('F Y', strtotime($bulan.'-01')) ?></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">No. Servis</th><th>Tanggal</th><th>Pelanggan</th><th>Kendaraan</th><th>Keluhan</th><th class="text-center">Status</th></tr>
            </thead>
            <tbody>
            <?php if (empty($servisListBulan)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data servis bulan ini</td></tr>
            <?php else: foreach ($servisListBulan as $s): ?>
                <tr>
                    <td class="ps-3"><span class="badge bg-primary-subtle text-primary border"><?= htmlspecialchars($s['no_servis']) ?></span></td>
                    <td class="small"><?= date('d/m/Y', strtotime($s['tanggal_masuk'])) ?></td>
                    <td class="fw-semibold small"><?= htmlspecialchars($s['nama']) ?></td>
                    <td class="small"><?= htmlspecialchars($s['no_polisi'].' '.$s['merk'].' '.$s['model']) ?></td>
                    <td class="small text-muted" style="max-width:180px"><span class="d-block text-truncate"><?= htmlspecialchars($s['keluhan']) ?></span></td>
                    <td class="text-center"><span class="badge <?= $statusClass[$s['status']] ?? 'bg-secondary' ?>"><?= $statusLabel[$s['status']] ?? $s['status'] ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
