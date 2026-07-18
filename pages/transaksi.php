<?php
define('BASE_URL', '/KKP');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/whatsapp.php';
requireLogin();
$user       = getUser();
$role = $user['role'] ?? '';

if ($role !== 'admin') {
    header('Location: ' . BASE_URL . '/pages/laporan.php');
    exit;
}

$pageTitle  = 'Transaksi';
$activePage = 'transaksi';
$msg = $msg_type = '';

$allowedStatusBayar = ['belum', 'lunas'];
$allowedMetodeBayar = ['tunai', 'transfer', 'qris', 'debit', 'kredit'];

function genNoTransaksi($pdo) {
    $last = $pdo->query("SELECT no_transaksi FROM transaksi ORDER BY id DESC LIMIT 1")->fetchColumn();
    $num  = $last ? (int)substr($last, 4) + 1 : 1;
    return 'TRX' . date('ym') . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function fetchTransaksiWhatsAppDetail(PDO $pdo, int $transaksiId): ?array {
    $stmt = $pdo->prepare("SELECT t.no_transaksi,t.total,t.biaya_jasa,t.biaya_suku_cadang,t.status_bayar,t.metode_bayar,s.no_servis,p.nama AS nama_pelanggan,p.no_telepon FROM transaksi t JOIN servis s ON t.servis_id=s.id JOIN pelanggan p ON s.pelanggan_id=p.id WHERE t.id=?");
    $stmt->execute([$transaksiId]);

    $row = $stmt->fetch();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrExit();
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $servis_id  = (int)($_POST['servis_id'] ?? 0);
        $tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
        $biaya_jasa = (float)str_replace(',', '.', $_POST['biaya_jasa'] ?? 0);
        $biaya_sc   = (float)str_replace(',', '.', $_POST['biaya_suku_cadang'] ?? 0);
        $diskon     = 0;
        $total      = $biaya_jasa + $biaya_sc;
        $status     = $_POST['status_bayar'] ?? 'belum';
        $metode     = $_POST['metode_bayar'] ?? 'tunai';
        $catatan    = trim($_POST['catatan'] ?? '') ?: null;

        if (!$servis_id || !in_array($status, $allowedStatusBayar, true) || !in_array($metode, $allowedMetodeBayar, true)) {
            $msg = 'Pilih order servis terlebih dahulu.'; $msg_type = 'danger';
        } else {
            $cekServis = $pdo->prepare("SELECT status FROM servis WHERE id=?");
            $cekServis->execute([$servis_id]);
            $statusServis = $cekServis->fetchColumn();

            if (!$statusServis || !in_array($statusServis, ['selesai', 'diambil'], true)) {
                $msg = 'Servis belum selesai, transaksi tidak bisa dibuat.'; $msg_type = 'warning';
            } else {
                $cekDup = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE servis_id=?");
                $cekDup->execute([$servis_id]);

                if ((int)$cekDup->fetchColumn() > 0) {
                    $msg = 'Transaksi untuk order servis ini sudah ada.'; $msg_type = 'warning';
                } else {
                    $pdo->prepare("INSERT INTO transaksi (no_transaksi,servis_id,tanggal,biaya_jasa,biaya_suku_cadang,diskon,total,status_bayar,metode_bayar,catatan) VALUES (?,?,?,?,?,?,?,?,?,?)")
                        ->execute([genNoTransaksi($pdo), $servis_id, $tanggal, $biaya_jasa, $biaya_sc, $diskon, $total, $status, $metode, $catatan]);
                    $msg = 'Transaksi berhasil disimpan.'; $msg_type = 'success';
                }
            }
        }
    }
    if ($action === 'bayar') {
        $id = (int)$_POST['id'];
        $cekStatus = $pdo->prepare("SELECT status_bayar FROM transaksi WHERE id=?");
        $cekStatus->execute([$id]);
        $statusLama = $cekStatus->fetchColumn();

        $pdo->prepare("UPDATE transaksi SET status_bayar='lunas' WHERE id=?")->execute([$id]);

        $msg = 'Status diubah ke Lunas.'; $msg_type = 'success';
    }
    if ($action === 'hapus') {
        $pdo->prepare("DELETE FROM transaksi WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Transaksi dihapus.'; $msg_type = 'success';
    }
}

$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT t.*,p.nama AS nama_pelanggan,k.no_polisi,s.no_servis FROM transaksi t JOIN servis s ON t.servis_id=s.id JOIN pelanggan p ON s.pelanggan_id=p.id JOIN kendaraan k ON s.kendaraan_id=k.id WHERE t.no_transaksi LIKE ? OR p.nama LIKE ? OR s.no_servis LIKE ? ORDER BY t.id DESC");
    $stmt->execute(["%$search%","%$search%","%$search%"]);
} else {
    $stmt = $pdo->query("SELECT t.*,p.nama AS nama_pelanggan,k.no_polisi,s.no_servis FROM transaksi t JOIN servis s ON t.servis_id=s.id JOIN pelanggan p ON s.pelanggan_id=p.id JOIN kendaraan k ON s.kendaraan_id=k.id ORDER BY t.id DESC");
}
$data = $stmt->fetchAll();

$listServis = $pdo->query("SELECT s.id,s.no_servis,p.nama,k.no_polisi FROM servis s JOIN pelanggan p ON s.pelanggan_id=p.id JOIN kendaraan k ON s.kendaraan_id=k.id LEFT JOIN transaksi t ON t.servis_id=s.id WHERE s.status IN ('selesai','diambil') AND t.id IS NULL ORDER BY s.id DESC")->fetchAll();

$totalLunas  = array_sum(array_map(fn($r) => $r['status_bayar'] === 'lunas' ? $r['total'] : 0, $data));
$totalBelum  = array_sum(array_map(fn($r) => $r['status_bayar'] === 'belum' ? $r['total'] : 0, $data));

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="page-head">
    <h5 class="fw-bold mb-0"><i class="fas fa-receipt me-2 text-primary"></i>Transaksi</h5>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stat -->
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm kpi-card text-center py-3"><div class="text-primary fw-bold fs-5"><?= count($data) ?></div><div class="text-muted small">Total Transaksi</div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm kpi-card text-center py-3"><div class="text-success fw-bold fs-6">Rp <?= number_format($totalLunas, 0, ',', '.') ?></div><div class="text-muted small">Total Lunas</div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm kpi-card text-center py-3"><div class="text-danger fw-bold fs-6">Rp <?= number_format($totalBelum, 0, ',', '.') ?></div><div class="text-muted small">Belum Dibayar</div></div></div>
</div>

<!-- Form Tambah -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-plus text-primary me-2"></i>Buat Transaksi Baru</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-success-subtle text-success border" id="autosaveStatus">Autosave aktif</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearDraftBtn">Hapus draft</button>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" id="transaksiForm" autocomplete="off">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="tambah">
            <div class="alert alert-info py-2 mb-3">
                Isi form akan tersimpan otomatis di browser. Refresh halaman untuk melihat draft yang dipulihkan.
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Order Servis <span class="text-danger">*</span></label>
                    <select name="servis_id" class="form-select" required>
                        <option value="">-- Pilih Order Servis --</option>
                        <?php foreach ($listServis as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['no_servis'].' - '.$s['nama'].' ('.$s['no_polisi'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Hanya order dengan status Selesai/Diambil</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Biaya Jasa (Rp)</label>
                    <input type="number" name="biaya_jasa" class="form-control" value="0" min="0" id="biayaJasa">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Biaya Suku Cadang (Rp)</label>
                    <input type="number" name="biaya_suku_cadang" class="form-control" value="0" min="0" id="biayaSC">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total (Rp)</label>
                    <input type="text" class="form-control bg-light fw-bold" id="totalPreview" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status Bayar</label>
                    <select name="status_bayar" class="form-select">
                        <option value="belum">Belum Dibayar</option>
                        <option value="lunas">Lunas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Metode Pembayaran</label>
                    <select name="metode_bayar" class="form-select">
                        <option value="tunai">Tunai / Cash</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="qris">QRIS</option>
                        <option value="debit">Kartu Debit</option>
                        <option value="kredit">Kartu Kredit</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="catatan" class="form-control" placeholder="Catatan tambahan...">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabel -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Daftar Transaksi <span class="badge bg-primary"><?= count($data) ?></span></span>
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="No.transaksi / pelanggan..." value="<?= htmlspecialchars($search) ?>" style="width:240px">
            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
            <?php if ($search): ?><a href="transaksi.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">#</th>
                    <th>No. Transaksi</th>
                    <th>Tgl</th>
                    <th>No. Servis</th>
                    <th>Pelanggan</th>
                    <th>No. Polisi</th>
                    <th class="text-end">Jasa</th>
                    <th class="text-end">Suku Cadang</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Metode</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="11" class="text-center text-muted py-4">Belum ada data transaksi</td></tr>
            <?php else: foreach ($data as $i => $t): ?>
                <tr>
                    <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                    <td><span class="badge bg-primary-subtle text-primary border fw-semibold"><?= htmlspecialchars($t['no_transaksi']) ?></span></td>
                    <td class="small"><?= date('d/m/Y', strtotime($t['tanggal'])) ?></td>
                    <td class="small"><?= htmlspecialchars($t['no_servis']) ?></td>
                    <td class="fw-semibold small"><?= htmlspecialchars($t['nama_pelanggan']) ?></td>
                    <td class="small"><?= htmlspecialchars($t['no_polisi']) ?></td>
                    <td class="text-end small">Rp <?= number_format($t['biaya_jasa'], 0, ',', '.') ?></td>
                    <td class="text-end small">Rp <?= number_format($t['biaya_suku_cadang'], 0, ',', '.') ?></td>
                    <td class="text-end fw-semibold small">Rp <?= number_format($t['total'], 0, ',', '.') ?></td>
                    <td class="text-center">
                        <?php
                        $metodeBadge = ['tunai'=>['bg-success-subtle text-success','fa-money-bill-wave','Tunai'],'transfer'=>['bg-info-subtle text-info','fa-university','Transfer'],'qris'=>['bg-purple-subtle text-purple','fa-qrcode','QRIS'],'debit'=>['bg-primary-subtle text-primary','fa-credit-card','Debit'],'kredit'=>['bg-warning-subtle text-warning','fa-credit-card','Kredit']];
                        $mb = $metodeBadge[$t['metode_bayar'] ?? 'tunai'] ?? ['bg-secondary-subtle text-secondary','fa-money-bill','Tunai'];
                        ?>
                        <span class="badge <?= $mb[0] ?>"><i class="fas <?= $mb[1] ?> me-1"></i><?= $mb[2] ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($t['status_bayar'] === 'lunas'): ?>
                            <span class="badge bg-success">Lunas</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Belum Bayar</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <a href="nota.php?id=<?= $t['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary me-1" title="Cetak Nota"><i class="fas fa-print"></i></a>
                        <?php if ($t['status_bayar'] !== 'lunas'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Tandai sebagai Lunas?')">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="bayar">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button class="btn btn-sm btn-success me-1" title="Tandai Lunas"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus transaksi ini?')">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function hitungTotal() {
    const jasa = parseFloat(document.getElementById('biayaJasa').value) || 0;
    const sc   = parseFloat(document.getElementById('biayaSC').value) || 0;
    const tot  = jasa + sc;
    document.getElementById('totalPreview').value = 'Rp ' + tot.toLocaleString('id-ID');
}
['biayaJasa','biayaSC'].forEach(id => document.getElementById(id).addEventListener('input', hitungTotal));
hitungTotal();

(function () {
    const form = document.getElementById('transaksiForm');
    const statusBadge = document.getElementById('autosaveStatus');
    const clearDraftBtn = document.getElementById('clearDraftBtn');
    const storageKey = 'kkp_transaksi_draft';
    const fields = ['servis_id', 'tanggal', 'biaya_jasa', 'biaya_suku_cadang', 'status_bayar', 'metode_bayar', 'catatan'];

    if (!form) {
        return;
    }

    const setStatus = (text, tone = 'success') => {
        if (!statusBadge) return;
        statusBadge.textContent = text;
        statusBadge.className = 'badge border';
        if (tone === 'success') {
            statusBadge.classList.add('bg-success-subtle', 'text-success');
        } else if (tone === 'warning') {
            statusBadge.classList.add('bg-warning-subtle', 'text-warning');
        } else {
            statusBadge.classList.add('bg-secondary-subtle', 'text-secondary');
        }
    };

    const saveDraft = () => {
        const data = {};
        fields.forEach((name) => {
            const input = form.elements[name];
            if (input) {
                data[name] = input.value;
            }
        });
        localStorage.setItem(storageKey, JSON.stringify(data));
        setStatus('Draft tersimpan otomatis', 'success');
    };

    const restoreDraft = () => {
        const raw = localStorage.getItem(storageKey);
        if (!raw) {
            return;
        }

        try {
            const data = JSON.parse(raw);
            fields.forEach((name) => {
                const input = form.elements[name];
                if (input && Object.prototype.hasOwnProperty.call(data, name) && data[name] !== null) {
                    input.value = data[name];
                }
            });
            hitungTotal();
            setStatus('Draft dipulihkan', 'warning');
        } catch (error) {
            localStorage.removeItem(storageKey);
        }
    };

    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);
    form.addEventListener('submit', () => {
        localStorage.removeItem(storageKey);
    });

    if (clearDraftBtn) {
        clearDraftBtn.addEventListener('click', () => {
            localStorage.removeItem(storageKey);
            form.reset();
            hitungTotal();
            setStatus('Draft dihapus', 'secondary');
        });
    }

    restoreDraft();
})();
</script>

<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>
