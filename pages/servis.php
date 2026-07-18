<?php
define('BASE_URL', '/KKP');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/whatsapp.php';
requireLogin();
$user      = getUser();
$role = $user['role'] ?? '';

if ($role === 'owner') {
    header('Location: ' . BASE_URL . '/pages/laporan.php');
    exit;
}
if (!in_array($role, ['admin', 'mekanik'], true)) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pageTitle = 'Data Servis';
$activePage= 'servis';
$msg = $msg_type = '';

$isAdmin = hasRole('admin');
$isMechanic = hasRole('mekanik');
$canUpdateStatus = $isAdmin || $isMechanic;
$diagnosisLabel = $isMechanic ? 'Keluhan / Parts yang Diganti' : 'Diagnosis / Hasil Pemeriksaan';
$diagnosisPlaceholder = $isMechanic ? 'Daftarkan keluhan yang ditemu dan parts mana yang sudah diganti...' : 'Tuliskan diagnosis atau temuan teknis...';

$allowedStatus = ['masuk','proses','selesai','diambil'];

function genNoServis($pdo) {
    $last = $pdo->query("SELECT no_servis FROM servis ORDER BY id DESC LIMIT 1")->fetchColumn();
    $num  = $last ? (int)substr($last, 3) + 1 : 1;
    return 'SRV' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function fetchServisWhatsAppDetail(PDO $pdo, int $servisId): ?array {
    $stmt = $pdo->prepare("SELECT s.id,s.no_servis,s.tanggal_masuk,s.keluhan,s.diagnosis,s.status,s.odometer,p.nama AS nama_pelanggan,p.no_telepon,k.no_polisi,k.merk,k.model FROM servis s JOIN pelanggan p ON s.pelanggan_id=p.id JOIN kendaraan k ON s.kendaraan_id=k.id WHERE s.id=?");
    $stmt->execute([$servisId]);

    $row = $stmt->fetch();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrExit();
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        if (!$isAdmin) {
            $msg = 'Akses ditolak. Hanya admin yang dapat menambah order servis.'; $msg_type = 'danger';
        } else {
        $pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);
        $kendaraan_id = (int)($_POST['kendaraan_id'] ?? 0);
        $tgl_masuk    = $_POST['tanggal_masuk'] ?? date('Y-m-d');
        $kilometerRaw = trim($_POST['kilometer'] ?? '');
        $kilometer    = $kilometerRaw === '' ? null : (int) preg_replace('/\D+/', '', $kilometerRaw);
        $keluhan      = trim($_POST['keluhan'] ?? '');

        if (!$pelanggan_id || !$kendaraan_id || !$keluhan) {
            $msg = 'Pelanggan, Kendaraan, dan Keluhan wajib diisi.'; $msg_type = 'danger';
        } elseif ($kilometer !== null && $kilometer < 0) {
            $msg = 'Kilometer tidak valid.'; $msg_type = 'danger';
        } else {
            $cekRelasi = $pdo->prepare("SELECT COUNT(*) FROM kendaraan WHERE id=? AND pelanggan_id=?");
            $cekRelasi->execute([$kendaraan_id, $pelanggan_id]);

            if ((int)$cekRelasi->fetchColumn() === 0) {
                $msg = 'Kendaraan tidak sesuai dengan pelanggan yang dipilih.'; $msg_type = 'danger';
            } else {
                $cekOpen = $pdo->prepare("SELECT COUNT(*) FROM servis WHERE kendaraan_id=? AND status IN ('masuk','proses')");
                $cekOpen->execute([$kendaraan_id]);

                if ((int)$cekOpen->fetchColumn() > 0) {
                    $msg = 'Kendaraan ini masih memiliki order servis yang sedang berjalan.'; $msg_type = 'warning';
                } else {
                    $pdo->prepare("INSERT INTO servis (no_servis,pelanggan_id,kendaraan_id,tanggal_masuk,odometer,keluhan) VALUES (?,?,?,?,?,?)")
                        ->execute([genNoServis($pdo), $pelanggan_id, $kendaraan_id, $tgl_masuk, $kilometer, $keluhan]);
                    $servisId = (int) $pdo->lastInsertId();
                    $detail = fetchServisWhatsAppDetail($pdo, $servisId);
                    if ($detail) {
                        whatsappSendMessage($detail['no_telepon'], whatsappComposeServisMessage($detail, 'created'));
                    }
                    $msg = 'Order servis berhasil ditambahkan.'; $msg_type = 'success';
                }
            }
        }
        }
    }
    if ($action === 'update') {
        if (!$canUpdateStatus) {
            $msg = 'Akses ditolak. Anda tidak memiliki hak untuk memperbarui servis.'; $msg_type = 'danger';
        } else {
        $id        = (int)$_POST['id'];
        $status    = $_POST['status'] ?? 'masuk';
        $kilometerRaw = trim($_POST['kilometer'] ?? '');
        $kilometer = $kilometerRaw === '' ? null : (int) preg_replace('/\D+/', '', $kilometerRaw);
        $diagnosis = trim($_POST['diagnosis'] ?? '') ?: null;

        if (!$id || !in_array($status, $allowedStatus, true) || ($kilometer !== null && $kilometer < 0)) {
            $msg = 'Data update servis tidak valid.'; $msg_type = 'danger';
        } else {
            $cekStatus = $pdo->prepare("SELECT status FROM servis WHERE id=?");
            $cekStatus->execute([$id]);
            $statusLama = $cekStatus->fetchColumn();
            $tgl_selesai = in_array($status, ['selesai','diambil'], true) ? date('Y-m-d') : null;
            $pdo->prepare("UPDATE servis SET status=?,diagnosis=?,odometer=COALESCE(?,odometer),tanggal_selesai=COALESCE(tanggal_selesai,?) WHERE id=?")
                ->execute([$status, $diagnosis, $kilometer, $tgl_selesai, $id]);
            if ($statusLama !== false && $statusLama !== $status && $status === 'proses') {
                $detail = fetchServisWhatsAppDetail($pdo, $id);
                if ($detail) {
                    whatsappSendMessage($detail['no_telepon'], whatsappComposeServisMessage($detail, 'processing'));
                }
            }
            if ($statusLama !== false && $statusLama !== $status && $status === 'selesai') {
                $detail = fetchServisWhatsAppDetail($pdo, $id);
                if ($detail) {
                    whatsappSendMessage($detail['no_telepon'], whatsappComposeServisMessage($detail, 'completed'));
                }
            }
            $msg = 'Status servis diperbarui.'; $msg_type = 'success';
        }
        }
    }
    if ($action === 'hapus') {
        if (!$isAdmin) {
            $msg = 'Akses ditolak. Hanya admin yang dapat menghapus servis.'; $msg_type = 'danger';
        } else {
            $pdo->prepare("DELETE FROM servis WHERE id=?")->execute([(int)$_POST['id']]);
            $msg = 'Data dihapus.'; $msg_type = 'success';
        }
    }
    if ($action === 'ubah_status') {
        if (!$canUpdateStatus) {
            $msg = 'Akses ditolak. Anda tidak memiliki hak untuk mengubah status servis.'; $msg_type = 'danger';
        } else {
        $id         = (int)$_POST['id'];
        $nextStatus = $_POST['next_status'] ?? 'masuk';

        $cek = $pdo->prepare("SELECT status FROM servis WHERE id=?");
        $cek->execute([$id]);
        $currentStatus = $cek->fetchColumn();

        $nextMap = ['masuk' => 'proses', 'proses' => 'selesai', 'selesai' => 'diambil'];
        $allowedNext = $currentStatus ? ($nextMap[$currentStatus] ?? null) : null;

        if (!$id || !in_array($nextStatus, $allowedStatus, true) || $allowedNext !== $nextStatus) {
            $msg = 'Perubahan status tidak valid.'; $msg_type = 'danger';
        } else {
            $tgl_selesai = in_array($nextStatus, ['selesai','diambil'], true) ? date('Y-m-d') : null;
            $pdo->prepare("UPDATE servis SET status=?,tanggal_selesai=COALESCE(tanggal_selesai,?) WHERE id=?")
                ->execute([$nextStatus, $tgl_selesai, $id]);
            if ($currentStatus !== $nextStatus && $nextStatus === 'proses') {
                $detail = fetchServisWhatsAppDetail($pdo, $id);
                if ($detail) {
                    whatsappSendMessage($detail['no_telepon'], whatsappComposeServisMessage($detail, 'processing'));
                }
            }
            if ($currentStatus !== $nextStatus && $nextStatus === 'selesai') {
                $detail = fetchServisWhatsAppDetail($pdo, $id);
                if ($detail) {
                    whatsappSendMessage($detail['no_telepon'], whatsappComposeServisMessage($detail, 'completed'));
                }
            }
            $msg = 'Status servis berhasil diperbarui.'; $msg_type = 'success';
        }
        }
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM servis WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editData = $s->fetch();
}

// Stat per status
$statCount = [];
foreach (['masuk','proses','selesai','diambil'] as $st) {
    $r = $pdo->prepare("SELECT COUNT(*) FROM servis WHERE status=?");
    $r->execute([$st]);
    $statCount[$st] = $r->fetchColumn();
}

$search       = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';

$where = [];
$params = [];
if ($search) {
    $where[]  = "(s.no_servis LIKE ? OR p.nama LIKE ? OR k.no_polisi LIKE ?)";
    $params   = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
if ($filterStatus) {
    $where[]  = "s.status=?";
    $params[] = $filterStatus;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT s.*,p.nama AS nama_pelanggan,k.no_polisi,k.merk,k.model FROM servis s JOIN pelanggan p ON s.pelanggan_id=p.id JOIN kendaraan k ON s.kendaraan_id=k.id $whereSql ORDER BY s.id DESC");
$stmt->execute($params);
$data = $stmt->fetchAll();

$listPelanggan = $pdo->query("SELECT id,kode_pelanggan,nama FROM pelanggan ORDER BY nama")->fetchAll();
$listKendaraan = $pdo->query("SELECT k.id,k.no_polisi,k.merk,k.model,p.nama AS nama_pelanggan FROM kendaraan k JOIN pelanggan p ON k.pelanggan_id=p.id ORDER BY k.no_polisi")->fetchAll();

$statusLabel = ['masuk'=>'Masuk','proses'=>'Proses','selesai'=>'Selesai','diambil'=>'Diambil'];
$statusClass = ['masuk'=>'bg-info','proses'=>'bg-warning text-dark','selesai'=>'bg-success','diambil'=>'bg-secondary'];

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="page-head">
    <h5 class="fw-bold mb-0"><i class="fas fa-wrench me-2 text-primary"></i>Data Servis</h5>
</div>

<!-- Stat Cards Status -->
<div class="row g-3 mb-4">
    <?php
    $statDef = [
        'masuk'   => ['label'=>'Masuk',   'icon'=>'fas fa-sign-in-alt',  'color'=>'info'],
        'proses'  => ['label'=>'Proses',  'icon'=>'fas fa-cogs',          'color'=>'warning'],
        'selesai' => ['label'=>'Selesai', 'icon'=>'fas fa-check-circle',  'color'=>'success'],
        'diambil' => ['label'=>'Diambil', 'icon'=>'fas fa-car',           'color'=>'secondary'],
    ];
    foreach ($statDef as $key => $def): ?>
    <div class="col-6 col-md-3">
        <a href="?status=<?= $key ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 <?= $filterStatus === $key ? 'border border-'.$def['color'].' border-2' : '' ?>">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-<?= $def['color'] ?>-subtle" style="width:48px;height:48px;flex-shrink:0">
                    <i class="<?= $def['icon'] ?> text-<?= $def['color'] ?> fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4 lh-1"><?= $statCount[$key] ?></div>
                    <div class="text-muted small"><?= $def['label'] ?></div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Form Tambah -->
<?php if (!$editData && $isAdmin): ?>
<div class="card border-0 shadow-sm mb-4">

    <div class="card-header bg-white fw-semibold"><i class="fas fa-plus text-primary me-2"></i>Tambah Order Servis</div>
    <div class="card-body">
        <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="tambah">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
                    <select name="pelanggan_id" class="form-select" required>
                        <option value="">-- Pilih Pelanggan --</option>
                        <?php foreach ($listPelanggan as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['kode_pelanggan'].' - '.$p['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kendaraan <span class="text-danger">*</span></label>
                    <select name="kendaraan_id" class="form-select" required>
                        <option value="">-- Pilih Kendaraan --</option>
                        <?php foreach ($listKendaraan as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['no_polisi'].' - '.$k['merk'].' '.$k['model'].' ('.$k['nama_pelanggan'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tanggal Masuk</label>
                    <input type="date" name="tanggal_masuk" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kilometer</label>
                    <input type="number" name="kilometer" class="form-control" min="0" placeholder="Contoh: 45210">
                </div>
                <div class="col-md-7">
                    <label class="form-label">Keluhan <span class="text-danger">*</span></label>
                    <input type="text" name="keluhan" class="form-control" placeholder="Deskripsikan keluhan kendaraan..." required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Form Update Status -->
<?php if ($editData): 
    // mekanik hanya update status, jadi elemen selain status tidak boleh muncul.
?>
<div class="card border-0 shadow-sm mb-4 border-warning">
    <div class="card-header bg-warning fw-semibold"><i class="fas fa-edit me-2"></i>Update Status Servis: <?= htmlspecialchars($editData['no_servis']) ?></div>
    <div class="card-body">
        <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($statusLabel as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $editData['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kilometer</label>
                    <input type="number" name="kilometer" class="form-control" min="0" value="<?= htmlspecialchars((string)($editData['odometer'] ?? '')) ?>" placeholder="Contoh: 45210">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= htmlspecialchars($diagnosisLabel) ?></label>
                    <input type="text" name="diagnosis" class="form-control" value="<?= htmlspecialchars($editData['diagnosis'] ?? '') ?>" placeholder="<?= htmlspecialchars($diagnosisPlaceholder) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-warning w-100"><i class="fas fa-save me-1"></i>Perbarui</button>
                </div>
                <div class="col-12"><a href="servis.php" class="btn btn-secondary btn-sm">Batal</a></div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Tabel -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-semibold">Daftar Servis <span class="badge bg-primary"><?= count($data) ?></span></span>
            <?php if ($filterStatus): ?>
            <span class="badge bg-<?= ['masuk'=>'info','proses'=>'warning','selesai'=>'success','diambil'=>'secondary'][$filterStatus] ?? 'secondary' ?> text-capitalize">
                Filter: <?= $statusLabel[$filterStatus] ?? $filterStatus ?>
            </span>
            <a href="servis.php<?= $search ? '?search='.urlencode($search) : '' ?>" class="btn btn-sm btn-outline-secondary py-0">✕ Hapus Filter</a>
            <?php endif; ?>
        </div>
        <form method="GET" class="d-flex gap-2">
            <?php if ($filterStatus): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>"><?php endif; ?>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="No.servis / pelanggan / no.polisi..." value="<?= htmlspecialchars($search) ?>" style="width:260px">
            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
            <?php if ($search): ?><a href="servis.php<?= $filterStatus ? '?status='.urlencode($filterStatus) : '' ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">#</th>
                    <th>No. Servis</th>
                    <th>Tgl Masuk</th>
                    <th>Pelanggan</th>
                    <th>Kendaraan</th>
                    <th>Keluhan</th>
                    <th>Kilometer</th>
                    <th>Diagnosis</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data servis</td></tr>
            <?php else: foreach ($data as $i => $s): ?>
                <tr>
                    <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                    <td><span class="badge bg-primary-subtle text-primary border fw-semibold"><?= htmlspecialchars($s['no_servis']) ?></span></td>
                    <td class="small"><?= date('d/m/Y', strtotime($s['tanggal_masuk'])) ?></td>
                    <td class="fw-semibold small"><?= htmlspecialchars($s['nama_pelanggan']) ?></td>
                    <td class="small"><?= htmlspecialchars($s['no_polisi'].' '.$s['merk'].' '.$s['model']) ?></td>
                    <td class="small text-muted" style="max-width:150px"><span class="d-block text-truncate" title="<?= htmlspecialchars($s['keluhan']) ?>"><?= htmlspecialchars($s['keluhan']) ?></span></td>
                    <td class="small text-muted"><?= $s['odometer'] !== null ? number_format((int)$s['odometer'], 0, ',', '.') . ' km' : '-' ?></td>
                    <td class="small text-muted" style="max-width:150px"><span class="d-block text-truncate" title="<?= htmlspecialchars($s['diagnosis'] ?? '') ?>"><?= htmlspecialchars($s['diagnosis'] ?? '-') ?></span></td>
                    <td class="text-center"><span class="badge <?= $statusClass[$s['status']] ?? 'bg-secondary' ?>"><?= $statusLabel[$s['status']] ?? $s['status'] ?></span></td>
                    <td class="text-center">
                        <?php
                        $nextMap = ['masuk'=>'proses','proses'=>'selesai','selesai'=>'diambil'];
                        $nextSt  = $nextMap[$s['status']] ?? null;
                        $nextLbl = ['proses'=>'<i class="fas fa-cogs"></i> Proses','selesai'=>'<i class="fas fa-check"></i> Selesai','diambil'=>'<i class="fas fa-car"></i> Diambil'];
                        ?>
                        <?php if ($nextSt): ?>
                        <form method="POST" class="d-inline">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="ubah_status">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <input type="hidden" name="next_status" value="<?= $nextSt ?>">
                            <button class="btn btn-sm btn-outline-success me-1" title="Tandai <?= $statusLabel[$nextSt] ?>"><?= $nextLbl[$nextSt] ?></button>
                        </form>
                        <?php endif; ?>
<?php if (in_array($s['status'], ['selesai','diambil']) && $isAdmin): ?>
                        <a href="<?= BASE_URL ?>/pages/transaksi.php" class="btn btn-sm btn-primary me-1" title="Buat Transaksi"><i class="fas fa-receipt"></i> Transaksi</a>
                        <?php endif; ?>
                        <?php if ($isAdmin): ?>
                        <a href="?edit=<?= $s['id'] ?><?= $filterStatus ? '&status='.urlencode($filterStatus) : '' ?>" class="btn btn-sm btn-outline-warning me-1" title="Update Status"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus order servis ini?')">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
<?php else: ?>
                        <!-- mekanik hanya boleh mengubah status melalui tombol "Tandai" (ubah_status) -->
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
