<?php
define('BASE_URL', '/KKP');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
$user       = getUser();
$role = $user['role'] ?? '';

if ($role !== 'admin') {
    header('Location: ' . BASE_URL . '/pages/laporan.php');
    exit;
}

$pageTitle  = 'Stok Suku Cadang';
$activePage = 'stok';
$msg = $msg_type = '';

function genKodeBarang($pdo) {
    $last = $pdo->query("SELECT kode_barang FROM stok ORDER BY id DESC LIMIT 1")->fetchColumn();
    $num  = $last ? (int)substr($last, 3) + 1 : 1;
    return 'BRG' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrExit();
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nama    = trim($_POST['nama_barang'] ?? '');
        $satuan  = trim($_POST['satuan'] ?? 'pcs');
        $stok    = (int)($_POST['stok'] ?? 0);
        $h_beli  = (float)str_replace(',', '.', $_POST['harga_beli'] ?? 0);
        $h_jual  = (float)str_replace(',', '.', $_POST['harga_jual'] ?? 0);
        $ket     = trim($_POST['keterangan'] ?? '') ?: null;
        if (!$nama) {
            $msg = 'Nama barang wajib diisi.'; $msg_type = 'danger';
        } elseif ($stok < 0 || $h_beli < 0 || $h_jual < 0) {
            $msg = 'Stok dan harga tidak boleh bernilai negatif.'; $msg_type = 'danger';
        } else {
            $pdo->prepare("INSERT INTO stok (kode_barang,nama_barang,satuan,stok,harga_beli,harga_jual,keterangan) VALUES (?,?,?,?,?,?,?)")
                ->execute([genKodeBarang($pdo), $nama, $satuan, $stok, $h_beli, $h_jual, $ket]);
            $msg = 'Barang berhasil ditambahkan.'; $msg_type = 'success';
        }
    }
    if ($action === 'update') {
        $id     = (int)$_POST['id'];
        $nama   = trim($_POST['nama_barang'] ?? '');
        $satuan = trim($_POST['satuan'] ?? 'pcs');
        $stok   = (int)($_POST['stok'] ?? 0);
        $h_beli = (float)str_replace(',', '.', $_POST['harga_beli'] ?? 0);
        $h_jual = (float)str_replace(',', '.', $_POST['harga_jual'] ?? 0);
        $ket    = trim($_POST['keterangan'] ?? '') ?: null;
        if (!$nama) {
            $msg = 'Nama barang wajib diisi.'; $msg_type = 'danger';
        } elseif ($stok < 0 || $h_beli < 0 || $h_jual < 0) {
            $msg = 'Stok dan harga tidak boleh bernilai negatif.'; $msg_type = 'danger';
        } else {
            $pdo->prepare("UPDATE stok SET nama_barang=?,satuan=?,stok=?,harga_beli=?,harga_jual=?,keterangan=? WHERE id=?")
                ->execute([$nama, $satuan, $stok, $h_beli, $h_jual, $ket, $id]);
            $msg = 'Data berhasil diperbarui.'; $msg_type = 'success';
            header('Location: stok.php?msg=updated'); exit;
        }
    }
    if ($action === 'hapus') {
        $pdo->prepare("DELETE FROM stok WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Barang dihapus.'; $msg_type = 'success';
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM stok WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editData = $s->fetch();
}

$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM stok WHERE nama_barang LIKE ? OR kode_barang LIKE ? ORDER BY nama_barang");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM stok ORDER BY nama_barang");
}
$data = $stmt->fetchAll();

$totalNilai = array_sum(array_column($data, 'stok') ? array_map(fn($r) => $r['stok'] * $r['harga_jual'], $data) : [0]);
$totalStok  = array_sum(array_column($data, 'stok'));
$stokHabis  = count(array_filter($data, fn($r) => $r['stok'] == 0));

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="page-head">
    <h5 class="fw-bold mb-0"><i class="fas fa-box me-2 text-primary"></i>Stok Suku Cadang</h5>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stat mini -->
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm kpi-card text-center py-3"><div class="text-primary fw-bold fs-4"><?= count($data) ?></div><div class="text-muted small">Total Jenis Barang</div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm kpi-card text-center py-3"><div class="text-success fw-bold fs-4"><?= number_format($totalStok) ?></div><div class="text-muted small">Total Unit Stok</div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm kpi-card text-center py-3"><div class="text-danger fw-bold fs-4"><?= $stokHabis ?></div><div class="text-muted small">Stok Habis</div></div></div>
</div>

<!-- Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <?php if ($editData): ?><i class="fas fa-edit text-warning me-2"></i>Edit Barang: <?= htmlspecialchars($editData['kode_barang']) ?>
        <?php else: ?><i class="fas fa-plus text-primary me-2"></i>Tambah Barang<?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="<?= $editData ? 'update' : 'tambah' ?>">
            <?php if ($editData): ?><input type="hidden" name="id" value="<?= $editData['id'] ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                    <input type="text" name="nama_barang" class="form-control" value="<?= htmlspecialchars($editData['nama_barang'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Satuan</label>
                    <select name="satuan" class="form-select">
                        <?php foreach (['pcs','set','liter','kg','meter','botol','kaleng'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($editData['satuan'] ?? 'pcs') === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stok</label>
                    <input type="number" name="stok" class="form-control" value="<?= $editData['stok'] ?? 0 ?>" min="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Harga Beli (Rp)</label>
                    <input type="number" name="harga_beli" class="form-control" value="<?= $editData['harga_beli'] ?? 0 ?>" min="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Harga Jual (Rp)</label>
                    <input type="number" name="harga_jual" class="form-control" value="<?= $editData['harga_jual'] ?? 0 ?>" min="0">
                </div>
                <div class="col-md-10">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="keterangan" class="form-control" value="<?= htmlspecialchars($editData['keterangan'] ?? '') ?>" placeholder="Keterangan tambahan...">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-<?= $editData ? 'warning' : 'primary' ?> w-100">
                        <i class="fas fa-save me-1"></i><?= $editData ? 'Perbarui' : 'Simpan' ?>
                    </button>
                </div>
                <?php if ($editData): ?>
                <div class="col-12"><a href="stok.php" class="btn btn-secondary btn-sm">Batal</a></div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabel -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Daftar Barang <span class="badge bg-primary"><?= count($data) ?></span></span>
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama / kode barang..." value="<?= htmlspecialchars($search) ?>" style="width:240px">
            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
            <?php if ($search): ?><a href="stok.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">#</th>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Satuan</th>
                    <th class="text-center">Stok</th>
                    <th class="text-end">Harga Beli</th>
                    <th class="text-end">Harga Jual</th>
                    <th>Keterangan</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data barang</td></tr>
            <?php else: foreach ($data as $i => $b): ?>
                <tr class="<?= $b['stok'] == 0 ? 'table-danger' : '' ?>">
                    <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                    <td><span class="badge bg-secondary-subtle text-secondary border"><?= htmlspecialchars($b['kode_barang']) ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($b['nama_barang']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($b['satuan']) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $b['stok'] == 0 ? 'bg-danger' : ($b['stok'] <= 5 ? 'bg-warning text-dark' : 'bg-success') ?>">
                            <?= number_format($b['stok']) ?>
                        </span>
                    </td>
                    <td class="text-end small">Rp <?= number_format($b['harga_beli'], 0, ',', '.') ?></td>
                    <td class="text-end small">Rp <?= number_format($b['harga_jual'], 0, ',', '.') ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($b['keterangan'] ?? '-') ?></td>
                    <td class="text-center">
                        <a href="?edit=<?= $b['id'] ?>" class="btn btn-sm btn-outline-warning me-1"><i class="fas fa-edit"></i></a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus barang ini?')">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
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
