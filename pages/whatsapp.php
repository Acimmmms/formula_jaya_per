<?php
define('BASE_URL', '/KKP');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/whatsapp.php';
requireLogin();

if (!hasRole('admin')) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$user = getUser();
$pageTitle = 'WhatsApp Notifikasi';
$activePage = 'whatsapp';
$msg = $msg_type = '';
$monthlyReminders = whatsappFetchMonthlyServiceReminders($pdo);
$monthlyReminderCount = count($monthlyReminders);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrExit();
    // Only support template test send from this simplified UI
    $action = $_POST['action'] ?? 'send_template';

    if ($action === 'send_monthly_reminders') {
        $reminders = whatsappFetchMonthlyServiceReminders($pdo);

        if (empty($reminders)) {
            $msg = 'Tidak ada pelanggan yang perlu diingatkan bulan ini.';
            $msg_type = 'info';
        } else {
            $sentCount = 0;
            $failedCount = 0;

            foreach ($reminders as $reminder) {
                $sent = whatsappSendMessage($reminder['no_telepon'], whatsappComposeMonthlyServisReminderMessage($reminder));
                if ($sent) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            }

            $msg = 'Pengingat servis bulanan diproses. Berhasil dikirim: ' . $sentCount . ', gagal: ' . $failedCount . '.';
            $msg_type = $failedCount > 0 ? 'warning' : 'success';
        }
    } elseif ($action === 'send_template') {
        $phone = trim($_POST['phone'] ?? '');
        $templateKey = trim($_POST['template_key'] ?? '');
        if ($phone === '' || $templateKey === '') {
            $msg = 'Nomor dan template harus dipilih.';
            $msg_type = 'danger';
        } else {
            $vars = [
                'nama' => trim((string) ($_POST['sample_nama'] ?? 'Pelanggan')),
                'no_servis' => trim((string) ($_POST['sample_no_servis'] ?? '-')),
                'kendaraan' => trim((string) ($_POST['sample_kendaraan'] ?? '-')),
                'kilometer' => trim((string) ($_POST['sample_kilometer'] ?? '0')),
                'total' => trim((string) ($_POST['sample_total'] ?? '-')),
            ];

            $message = renderWhatsappTemplate($templateKey, $vars);
            if ($message === null) {
                $msg = 'Template tidak ditemukan.';
                $msg_type = 'danger';
            } else {
                $sent = whatsappSendMessage($phone, $message);
                if ($sent) {
                    $msg = 'Pesan template berhasil dikirim.';
                    $msg_type = 'success';
                } else {
                    $msg = 'Pengiriman pesan gagal. Periksa konfigurasi.';
                    $msg_type = 'warning';
                }
            }
        }
    }
}

$apiUrl = getenv('WHATSAPP_API_URL');
$apiUrl = is_string($apiUrl) && trim($apiUrl) !== '' ? trim($apiUrl) : 'https://api.fonnte.com/send';
$apiKey = getenv('WHATSAPP_API_KEY');
$apiKey = is_string($apiKey) ? trim($apiKey) : '';
$enabled = whatsappEnabled();
$countryCode = whatsappCountryCode();

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="page-head">
    <h5 class="fw-bold mb-0"><i class="fab fa-whatsapp me-2 text-success"></i>WhatsApp Notifikasi</h5>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Metric panels removed for cleaner UI -->

<div class="row justify-content-center g-3">
    <div class="col-md-8 col-lg-6">
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="fas fa-paper-plane text-success me-2"></i>Kirim Template (Tes)
            </div>
            <div class="card-body">
                <?php $templates = loadWhatsappTemplates(); ?>
                <form method="POST" class="row g-3">
                    <?= csrfInput() ?>
                    <input type="hidden" name="action" value="send_template">
                    <div class="col-12">
                        <label class="form-label">Nomor</label>
                        <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Pilih Template</label>
                        <select name="template_key" class="form-select">
                            <?php foreach (array_keys($templates) as $key): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($key) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Nama (sample)</label>
                        <input type="text" name="sample_nama" class="form-control" value="Budi">
                    </div>
                    <div class="col-12 d-grid">
                        <button type="submit" class="btn btn-success">Kirim Template</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="fas fa-calendar-alt text-warning me-2"></i>Pengingat Servis Bulanan
            </div>
            <div class="card-body">
                <div class="alert alert-warning py-2 mb-3">
                    Ada <strong><?= $monthlyReminderCount ?></strong> pelanggan/kendaraan yang perlu diingatkan bulan ini.
                </div>

                <?php if (!empty($monthlyReminders)): ?>
                <div class="small text-muted mb-2">Preview 5 data teratas:</div>
                <ul class="small mb-3 ps-3">
                    <?php foreach (array_slice($monthlyReminders, 0, 5) as $reminder): ?>
                        <li><?= htmlspecialchars($reminder['nama_pelanggan'] . ' - ' . $reminder['no_polisi']) ?> <span class="text-muted">(<?= date('d/m/Y', strtotime($reminder['tanggal_servis_terakhir'])) ?>)</span></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <form method="POST" onsubmit="return confirm('Kirim pengingat servis bulanan ke semua pelanggan yang terdeteksi?');">
                    <?= csrfInput() ?>
                    <input type="hidden" name="action" value="send_monthly_reminders">
                    <button type="submit" class="btn btn-warning w-100" <?= $monthlyReminderCount === 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-bell me-1"></i>Kirim Pengingat Bulanan
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
<?php require_once __DIR__ . '/../includes/layout_end.php'; ?>