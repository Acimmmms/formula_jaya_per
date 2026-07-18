<?php
define('BASE_URL', '/KKP');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/dashboard.php'); exit;
}
$user       = getUser();
$pageTitle  = 'Manajemen User';
$activePage = 'users';
$msg = $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrExit();
    $action = $_POST['action'] ?? '';
    $allowedRole = ['admin', 'mekanik', 'owner'];
    $allowedStatus = ['aktif', 'nonaktif'];

    if ($action === 'tambah') {
        $username    = trim($_POST['username'] ?? '');
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $email       = trim($_POST['email'] ?? '') ?: null;
        $telepon     = trim($_POST['telepon'] ?? '') ?: null;
        $role        = $_POST['role'] ?? 'mekanik';
        $password    = $_POST['password'] ?? '';
        $status      = $_POST['status'] ?? 'aktif';

        if (!$username || !$namaLengkap || !$password) {
            $msg = 'Username, nama lengkap, dan password wajib diisi.'; $msg_type = 'danger';
        } elseif (!in_array($role, $allowedRole, true) || !in_array($status, $allowedStatus, true)) {
            $msg = 'Role atau status tidak valid.'; $msg_type = 'danger';
        } elseif (strlen($password) < 4) {
            $msg = 'Password minimal 4 karakter.'; $msg_type = 'danger';
        } else {
            $cek = $pdo->prepare("SELECT id FROM users WHERE username=?");
            $cek->execute([$username]);
            if ($cek->fetch()) {
                $msg = 'Username sudah digunakan.'; $msg_type = 'danger';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username,password,nama_lengkap,email,telepon,role,status) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$username, $hash, $namaLengkap, $email, $telepon, $role, $status]);
                $msg = 'User berhasil ditambahkan.'; $msg_type = 'success';
            }
        }
    }
    if ($action === 'update') {
        $id          = (int)$_POST['id'];
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $email       = trim($_POST['email'] ?? '') ?: null;
        $telepon     = trim($_POST['telepon'] ?? '') ?: null;
        $role        = $_POST['role'] ?? 'mekanik';
        $status      = $_POST['status'] ?? 'aktif';
        $newpass     = trim($_POST['password'] ?? '');
        if (!$namaLengkap) {
            $msg = 'Nama lengkap wajib diisi.'; $msg_type = 'danger';
        } elseif (!in_array($role, $allowedRole, true) || !in_array($status, $allowedStatus, true)) {
            $msg = 'Role atau status tidak valid.'; $msg_type = 'danger';
        } else {
            if ($newpass) {
                $pdo->prepare("UPDATE users SET nama_lengkap=?,email=?,telepon=?,role=?,status=?,password=? WHERE id=?")
                    ->execute([$namaLengkap, $email, $telepon, $role, $status, password_hash($newpass, PASSWORD_DEFAULT), $id]);
            } else {
                $pdo->prepare("UPDATE users SET nama_lengkap=?,email=?,telepon=?,role=?,status=? WHERE id=?")
                    ->execute([$namaLengkap, $email, $telepon, $role, $status, $id]);
            }
            $msg = 'User berhasil diperbarui.'; $msg_type = 'success';
            header('Location: users.php?msg=updated'); exit;
        }
    }
    if ($action === 'hapus') {
        $hapusId = (int)$_POST['id'];
        if ($hapusId === (int)($user['id'] ?? 0)) {
            $msg = 'Tidak bisa menghapus akun sendiri.'; $msg_type = 'danger';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$hapusId]);
            $msg = 'User dihapus.'; $msg_type = 'success';
        }
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editData = $s->fetch();
}

$data = $pdo->query("SELECT * FROM users ORDER BY role, nama_lengkap")->fetchAll();

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="page-head">
    <h5 class="fw-bold mb-0"><i class="fas fa-users-cog me-2 text-primary"></i>Manajemen User</h5>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <?php if ($editData): ?><i class="fas fa-edit text-warning me-2"></i>Edit User: <?= htmlspecialchars($editData['username']) ?>
        <?php else: ?><i class="fas fa-plus text-primary me-2"></i>Tambah User Baru<?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="<?= $editData ? 'update' : 'tambah' ?>">
            <?php if ($editData): ?><input type="hidden" name="id" value="<?= $editData['id'] ?>"><?php endif; ?>
            <div class="row g-3">
                <?php if (!$editData): ?>
                <div class="col-md-3">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required placeholder="username_login">
                </div>
                <?php endif; ?>
                <div class="col-md-<?= $editData ? '4' : '3' ?>">
                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($editData['nama_lengkap'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Password <?= $editData ? '<small class="text-muted">(kosong = tidak ganti)</small>' : '<span class="text-danger">*</span>' ?></label>
                    <input type="password" name="password" class="form-control" <?= $editData ? '' : 'required' ?> minlength="4">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="admin" <?= ($editData['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="mekanik" <?= ($editData['role'] ?? 'mekanik') === 'mekanik' ? 'selected' : '' ?>>Mekanik</option>
                        <option value="owner" <?= ($editData['role'] ?? '') === 'owner' ? 'selected' : '' ?>>Owner</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="aktif" <?= ($editData['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= ($editData['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editData['email'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Telepon</label>
                    <input type="text" name="telepon" class="form-control" value="<?= htmlspecialchars($editData['telepon'] ?? '') ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-<?= $editData ? 'warning' : 'primary' ?> w-100">
                        <i class="fas fa-save me-1"></i><?= $editData ? 'Perbarui' : 'Simpan' ?>
                    </button>
                </div>
                <?php if ($editData): ?>
                <div class="col-12"><a href="users.php" class="btn btn-secondary btn-sm">Batal</a></div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabel -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Daftar User <span class="badge bg-primary"><?= count($data) ?></span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">#</th>
                    <th>Username</th>
                    <th>Nama Lengkap</th>
                    <th>Email</th>
                    <th>Telepon</th>
                    <th class="text-center">Role</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data user</td></tr>
            <?php else: foreach ($data as $i => $u): ?>
                <tr>
                    <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($u['username']) ?> <?= (int)$u['id'] === (int)($user['id'] ?? 0) ? '<span class="badge bg-primary-subtle text-primary border ms-1">Anda</span>' : '' ?></td>
                    <td><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($u['telepon'] ?? '-') ?></td>
                    <td class="text-center">
                        <?php $roleBadge = ['admin'=>'bg-danger','mekanik'=>'bg-info','owner'=>'bg-secondary']; ?>
                        <span class="badge <?= $roleBadge[$u['role']] ?? 'bg-secondary' ?>"><?= ucfirst($u['role']) ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= $u['status'] === 'aktif' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($u['status']) ?></span>
                    </td>
                    <td class="text-center">
                        <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-warning me-1"><i class="fas fa-edit"></i></a>
                        <?php if ((int)$u['id'] !== (int)($user['id'] ?? 0)): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus user <?= htmlspecialchars($u['username']) ?>?')">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
