<?php
define('BASE_URL', '/KKP');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
$user      = getUser();
$role = $user['role'] ?? '';

if ($role !== 'admin') {
    header('Location: ' . BASE_URL . '/pages/laporan.php');
    exit;
}

$pageTitle = 'Data Kendaraan';
$activePage= 'kendaraan';
$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrExit();
    $action       = $_POST['action'] ?? '';
    $pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);
    $no_polisi    = strtoupper(trim($_POST['no_polisi'] ?? ''));
    $merk         = trim($_POST['merk'] ?? '');
    $model        = trim($_POST['model'] ?? '');
    $warna        = trim($_POST['warna'] ?? '') ?: null;

    if ($action === 'tambah') {
        if (!$pelanggan_id || !$no_polisi || !$merk || !$model) {
            $msg = 'Semua kolom bertanda * wajib diisi.'; $msg_type = 'danger';
        } else {
            $cekPel = $pdo->prepare("SELECT COUNT(*) FROM pelanggan WHERE id=?");
            $cekPel->execute([$pelanggan_id]);
            if ((int)$cekPel->fetchColumn() === 0) {
                $msg = 'Pelanggan tidak valid.'; $msg_type = 'danger';
            } else {
            $cek = $pdo->prepare("SELECT id FROM kendaraan WHERE no_polisi=?");
            $cek->execute([$no_polisi]);
            if ($cek->fetch()) { $msg = "No. Polisi $no_polisi sudah terdaftar."; $msg_type = 'warning'; }
            else {
                $tahun = (int)date('Y');
                $pdo->prepare("INSERT INTO kendaraan (pelanggan_id,no_polisi,merk,model,tahun,warna) VALUES (?,?,?,?,?,?)")
                    ->execute([$pelanggan_id, $no_polisi, $merk, $model, $tahun, $warna]);
                $msg = 'Kendaraan berhasil ditambahkan.'; $msg_type = 'success';
            }
            }
        }
    }
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        if (!$id || !$pelanggan_id || !$no_polisi || !$merk || !$model) {
            $msg = 'Data kendaraan tidak valid.'; $msg_type = 'danger';
        } else {
            $cekPel = $pdo->prepare("SELECT COUNT(*) FROM pelanggan WHERE id=?");
            $cekPel->execute([$pelanggan_id]);
            $cekNoPol = $pdo->prepare("SELECT COUNT(*) FROM kendaraan WHERE no_polisi=? AND id<>?");
            $cekNoPol->execute([$no_polisi, $id]);

            if ((int)$cekPel->fetchColumn() === 0) {
                $msg = 'Pelanggan tidak valid.'; $msg_type = 'danger';
            } elseif ((int)$cekNoPol->fetchColumn() > 0) {
                $msg = "No. Polisi $no_polisi sudah digunakan kendaraan lain."; $msg_type = 'warning';
            } else {
                $pdo->prepare("UPDATE kendaraan SET pelanggan_id=?,no_polisi=?,merk=?,model=?,warna=? WHERE id=?")
                    ->execute([$pelanggan_id, $no_polisi, $merk, $model, $warna, $id]);
                $msg = 'Data berhasil diperbarui.'; $msg_type = 'success';
            }
        }
    }
    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->beginTransaction();

            // Hapus data servis terkait dulu agar tidak melanggar FK kendaraan_id.
            // Data transaksi akan ikut terhapus otomatis (ON DELETE CASCADE pada servis_id).
            $pdo->prepare("DELETE FROM servis WHERE kendaraan_id=?")->execute([$id]);

            $del = $pdo->prepare("DELETE FROM kendaraan WHERE id=?");
            $del->execute([$id]);

            if ($del->rowCount() > 0) {
                $pdo->commit();
                $msg = 'Data kendaraan berhasil dihapus.'; $msg_type = 'success';
            } else {
                $pdo->rollBack();
                $msg = 'Data kendaraan tidak ditemukan.'; $msg_type = 'warning';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = 'Gagal menghapus kendaraan. Silakan coba lagi.'; $msg_type = 'danger';
        }
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM kendaraan WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editData = $s->fetch();
}

$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT k.*,p.nama AS nama_pelanggan FROM kendaraan k JOIN pelanggan p ON k.pelanggan_id=p.id WHERE k.no_polisi LIKE ? OR k.merk LIKE ? OR p.nama LIKE ? ORDER BY k.id DESC");
    $stmt->execute(["%$search%","%$search%","%$search%"]);
} else {
    $stmt = $pdo->query("SELECT k.*,p.nama AS nama_pelanggan FROM kendaraan k JOIN pelanggan p ON k.pelanggan_id=p.id ORDER BY k.id DESC");
}
$data = $stmt->fetchAll();
$listPelanggan = $pdo->query("SELECT id,kode_pelanggan,nama FROM pelanggan ORDER BY nama")->fetchAll();
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="page-head">
    <h5 class="fw-bold mb-0"><i class="fas fa-car me-2 text-primary"></i>Data Kendaraan</h5>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (empty($listPelanggan)): ?>
<div class="alert alert-warning">Belum ada pelanggan. <a href="pelanggan.php">Tambah pelanggan dulu</a>.</div>
<?php else: ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <?= $editData ? '<i class="fas fa-edit text-warning me-2"></i>Edit Kendaraan' : '<i class="fas fa-plus text-primary me-2"></i>Tambah Kendaraan' ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="<?= $editData ? 'edit' : 'tambah' ?>">
            <?php if ($editData): ?><input type="hidden" name="id" value="<?= $editData['id'] ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Pemilik <span class="text-danger">*</span></label>
                    <select name="pelanggan_id" class="form-select" required>
                        <option value="">-- Pilih Pelanggan --</option>
                        <?php foreach ($listPelanggan as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($editData['pelanggan_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['kode_pelanggan'] . ' - ' . $p['nama']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">No. Polisi <span class="text-danger">*</span></label>
                    <input type="text" name="no_polisi" class="form-control text-uppercase" value="<?= htmlspecialchars($editData['no_polisi'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Merk <span class="text-danger">*</span></label>
                    <input type="text" name="merk" class="form-control" value="<?= htmlspecialchars($editData['merk'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Model <span class="text-danger">*</span></label>
                    <input type="text" name="model" class="form-control" value="<?= htmlspecialchars($editData['model'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Warna</label>
                    <input type="text" name="warna" class="form-control" value="<?= htmlspecialchars($editData['warna'] ?? '') ?>">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-<?= $editData ? 'warning' : 'primary' ?>">
                        <i class="fas fa-save me-1"></i><?= $editData ? 'Perbarui' : 'Simpan' ?>
                    </button>
                    <?php if ($editData): ?>
                    <a href="kendaraan.php" class="btn btn-secondary">Batal</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Daftar Kendaraan <span class="badge bg-primary"><?= count($data) ?></span></span>
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no.polisi / merk / pemilik..." value="<?= htmlspecialchars($search) ?>" style="width:240px">
            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
            <?php if ($search): ?><a href="kendaraan.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">#</th>
                    <th>No. Polisi</th>
                    <th>Merk / Model</th>
                    <th>Warna</th>
                    <th>Pemilik</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data kendaraan</td></tr>
            <?php else: foreach ($data as $i => $k): ?>
                <tr>
                    <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                    <td><span class="badge bg-dark fs-6"><?= htmlspecialchars($k['no_polisi']) ?></span></td>
                    <td><div class="fw-semibold"><?= htmlspecialchars($k['merk']) ?></div><small class="text-muted"><?= htmlspecialchars($k['model']) ?></small></td>
                    <td><?= htmlspecialchars($k['warna'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($k['nama_pelanggan']) ?></td>
                    <td class="text-center">
                        <a href="?edit=<?= $k['id'] ?>" class="btn btn-sm btn-outline-warning me-1"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus kendaraan ini?')">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $k['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
