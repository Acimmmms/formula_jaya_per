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

$pageTitle = 'Data Pelanggan';
$activePage= 'pelanggan';
$msg = $msg_type = '';

function genKode($pdo) {
    $last = $pdo->query("SELECT kode_pelanggan FROM pelanggan ORDER BY id DESC LIMIT 1")->fetchColumn();
    $num  = $last ? (int)substr($last, 3) + 1 : 1;
    return 'PLG' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrExit();
    $action = $_POST['action'] ?? '';
    $nama   = trim($_POST['nama'] ?? '');
    $telp   = trim($_POST['no_telepon'] ?? '');
    $email  = trim($_POST['email'] ?? '') ?: null;
    $alamat = trim($_POST['alamat'] ?? '') ?: null;

    if ($action === 'tambah') {
        if (!$nama || !$telp) { $msg = 'Nama dan Telepon wajib diisi.'; $msg_type = 'danger'; }
        else {
            $pdo->prepare("INSERT INTO pelanggan (kode_pelanggan,nama,no_telepon,email,alamat) VALUES (?,?,?,?,?)")
                ->execute([genKode($pdo), $nama, $telp, $email, $alamat]);
            $msg = 'Pelanggan berhasil ditambahkan.'; $msg_type = 'success';
        }
    }
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        if (!$nama || !$telp || !$id) { $msg = 'Data tidak valid.'; $msg_type = 'danger'; }
        else {
            $pdo->prepare("UPDATE pelanggan SET nama=?,no_telepon=?,email=?,alamat=? WHERE id=?")
                ->execute([$nama, $telp, $email, $alamat, $id]);
            $msg = 'Data berhasil diperbarui.'; $msg_type = 'success';
        }
    }
    if ($action === 'hapus') {
        $id  = (int)$_POST['id'];
        $cek = $pdo->prepare("SELECT COUNT(*) FROM kendaraan WHERE pelanggan_id=?");
        $cek->execute([$id]);
        if ($cek->fetchColumn() > 0) { $msg = 'Tidak bisa hapus - pelanggan masih punya kendaraan.'; $msg_type = 'warning'; }
        else { $pdo->prepare("DELETE FROM pelanggan WHERE id=?")->execute([$id]); $msg = 'Data dihapus.'; $msg_type = 'success'; }
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM pelanggan WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editData = $s->fetch();
}

$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE nama LIKE ? OR no_telepon LIKE ? OR kode_pelanggan LIKE ? ORDER BY id DESC");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM pelanggan ORDER BY id DESC");
}
$data = $stmt->fetchAll();

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="page-head">
    <h5 class="fw-bold mb-0"><i class="fas fa-users me-2 text-primary"></i>Data Pelanggan</h5>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <?= $editData ? '<i class="fas fa-edit text-warning me-2"></i>Edit Pelanggan' : '<i class="fas fa-plus text-primary me-2"></i>Tambah Pelanggan' ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="<?= $editData ? 'edit' : 'tambah' ?>">
            <?php if ($editData): ?>
            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nama <span class="text-danger">*</span></label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($editData['nama'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Telepon <span class="text-danger">*</span></label>
                    <input type="text" name="no_telepon" class="form-control" value="<?= htmlspecialchars($editData['no_telepon'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editData['email'] ?? '') ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-<?= $editData ? 'warning' : 'primary' ?> w-100">
                        <i class="fas fa-save me-1"></i><?= $editData ? 'Perbarui' : 'Simpan' ?>
                    </button>
                    <?php if ($editData): ?>
                    <a href="pelanggan.php" class="btn btn-secondary"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alamat</label>
                    <input type="text" name="alamat" class="form-control" value="<?= htmlspecialchars($editData['alamat'] ?? '') ?>">
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Daftar Pelanggan <span class="badge bg-primary"><?= count($data) ?></span></span>
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama / telepon..." value="<?= htmlspecialchars($search) ?>" style="width:220px">
            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
            <?php if ($search): ?><a href="pelanggan.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">#</th>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>No. Telepon</th>
                    <th>Email</th>
                    <th>Alamat</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data pelanggan</td></tr>
            <?php else: foreach ($data as $i => $p): ?>
                <tr>
                    <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['kode_pelanggan']) ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($p['nama']) ?></td>
                    <td><?= htmlspecialchars($p['no_telepon']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($p['email'] ?? '-') ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($p['alamat'] ?? '-') ?></td>
                    <td class="text-center">
                        <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning me-1"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus pelanggan ini?')">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
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
