<?php
define('BASE_URL', '/KKP');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
$user = getUser();
$role = $user['role'] ?? '';
if ($role !== 'admin') {
    if ($role === 'owner') {
        header('Location: ' . BASE_URL . '/pages/laporan.php');
    } elseif ($role === 'mekanik') {
        header('Location: ' . BASE_URL . '/pages/servis.php');
    } else {
        header('Location: ' . BASE_URL . '/dashboard.php');
    }
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/pages/transaksi.php'); exit; }

$stmt = $pdo->prepare("
    SELECT t.*,
           p.nama AS nama_pelanggan, p.no_telepon, p.alamat, p.kode_pelanggan,
           k.no_polisi, k.merk, k.model, k.tahun, k.warna,
           s.no_servis, s.keluhan, s.diagnosis, s.tanggal_masuk, s.tanggal_selesai, s.odometer, s.status AS status_servis,
           u.nama_lengkap AS petugas_nama
    FROM transaksi t
    JOIN servis s   ON t.servis_id   = s.id
    JOIN pelanggan p ON s.pelanggan_id = p.id
    JOIN kendaraan k ON s.kendaraan_id = k.id
    LEFT JOIN users u ON u.role = 'admin' AND u.status='aktif'
    WHERE t.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$trx = $stmt->fetch();
if (!$trx) { header('Location: ' . BASE_URL . '/pages/transaksi.php'); exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota <?= htmlspecialchars($trx['no_transaksi']) ?> - Formula Jaya Per</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f1f5f9; }
        .nota-wrapper { max-width: 800px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 40px 48px; }
        .nota-header { border-bottom: 3px solid #2563eb; padding-bottom: 20px; margin-bottom: 24px; }
        .bengkel-name { font-size: 1.6rem; font-weight: 700; color: #1e293b; }
        .bengkel-sub  { color: #64748b; font-size: 0.85rem; }
        .nota-title   { font-size: 1.1rem; font-weight: 700; letter-spacing: .05em; color: #2563eb; }
        .info-grid    { display: grid; grid-template-columns: 1fr 1fr; gap: 0 24px; }
        .info-row     { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #e2e8f0; font-size: .875rem; }
        .info-row .lbl { color: #64748b; }
        .info-row .val { font-weight: 600; text-align: right; }
        .section-title { font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: #94a3b8; margin: 20px 0 8px; }
        .biaya-table  { width: 100%; font-size: .875rem; border-collapse: collapse; }
        .biaya-table td { padding: 7px 10px; }
        .biaya-table tr.total-row td { font-weight: 700; font-size: 1rem; border-top: 2px solid #1e293b; }
        .biaya-table .lbl { color: #475569; }
        .biaya-table .val { text-align: right; }
        .status-badge { display: inline-block; padding: 4px 16px; border-radius: 999px; font-size: .8rem; font-weight: 700; letter-spacing: .04em; }
        .status-lunas  { background: #dcfce7; color: #166534; }
        .status-belum  { background: #fef9c3; color: #854d0e; }
        .footer-nota  { margin-top: 32px; border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: space-between; align-items: flex-end; font-size: .8rem; color: #64748b; }
        .ttd-box      { text-align: center; min-width: 160px; }
        .ttd-box .ttd-line { margin-top: 56px; border-top: 1px solid #334155; padding-top: 6px; font-weight: 600; color: #1e293b; }
        .action-bar   { max-width: 800px; margin: 0 auto 16px; display: flex; gap: 10px; }
        @media print {
            body { background: #fff; }
            .action-bar, .no-print { display: none !important; }
            .nota-wrapper { box-shadow: none; margin: 0; border-radius: 0; padding: 20px 28px; }
            @page { margin: 10mm 15mm; }
        }
    </style>
</head>
<body>

<div class="action-bar no-print pt-3">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print me-2"></i>Cetak Nota
    </button>
    <a href="<?= BASE_URL ?>/pages/transaksi.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Kembali
    </a>
</div>

<div class="nota-wrapper">

    <!-- Header Bengkel -->
    <div class="nota-header d-flex justify-content-between align-items-start">
        <div>
            <div class="bengkel-name"><i class="fas fa-car-side me-2" style="color:#2563eb"></i>Formula Jaya Per</div>
            <div class="bengkel-sub">Bengkel Mobil &amp; Perawatan Kendaraan</div>
            <div class="bengkel-sub mt-1"><i class="fas fa-map-marker-alt me-1"></i>Jl. Warung bambu - Cibarusah, Bekasi</div>
            <div class="bengkel-sub"><i class="fas fa-phone me-1"></i>0896-0611-4306</div>
        </div>
        <div class="text-end">
            <div class="nota-title">NOTA SERVIS</div>
            <div class="fw-bold mt-1" style="color:#1e293b;font-size:1.05rem"><?= htmlspecialchars($trx['no_transaksi']) ?></div>
            <div class="bengkel-sub mt-1">Tanggal: <?= date('d F Y', strtotime($trx['tanggal'])) ?></div>
            <div class="mt-2">
                <span class="status-badge <?= $trx['status_bayar'] === 'lunas' ? 'status-lunas' : 'status-belum' ?>">
                    <?= $trx['status_bayar'] === 'lunas' ? '✔ LUNAS' : '⏳ BELUM DIBAYAR' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Info Pelanggan & Kendaraan -->
    <div class="info-grid">
        <div>
            <div class="section-title">Data Pelanggan</div>
            <div class="info-row"><span class="lbl">Nama</span><span class="val"><?= htmlspecialchars($trx['nama_pelanggan']) ?></span></div>
            <div class="info-row"><span class="lbl">Kode</span><span class="val"><?= htmlspecialchars($trx['kode_pelanggan']) ?></span></div>
            <div class="info-row"><span class="lbl">Telepon</span><span class="val"><?= htmlspecialchars($trx['no_telepon'] ?? '-') ?></span></div>
            <div class="info-row"><span class="lbl">Alamat</span><span class="val" style="max-width:220px;word-break:break-word"><?= htmlspecialchars($trx['alamat'] ?? '-') ?></span></div>
        </div>
        <div>
            <div class="section-title">Data Kendaraan</div>
            <div class="info-row"><span class="lbl">No. Polisi</span><span class="val"><?= htmlspecialchars($trx['no_polisi']) ?></span></div>
            <div class="info-row"><span class="lbl">Merk / Model</span><span class="val"><?= htmlspecialchars($trx['merk'].' '.$trx['model']) ?></span></div>
            <div class="info-row"><span class="lbl">Tahun</span><span class="val"><?= htmlspecialchars($trx['tahun']) ?></span></div>
            <div class="info-row"><span class="lbl">Warna</span><span class="val"><?= htmlspecialchars($trx['warna'] ?? '-') ?></span></div>
        </div>
    </div>

    <!-- Info Servis -->
    <div class="section-title">Detail Servis</div>
    <div class="info-row"><span class="lbl">No. Servis</span><span class="val"><?= htmlspecialchars($trx['no_servis']) ?></span></div>
    <div class="info-row"><span class="lbl">Tgl Masuk</span><span class="val"><?= date('d F Y', strtotime($trx['tanggal_masuk'])) ?></span></div>
    <?php if (!empty($trx['odometer'])): ?>
    <div class="info-row"><span class="lbl">Kilometer</span><span class="val"><?= number_format((int)$trx['odometer'], 0, ',', '.') ?> km</span></div>
    <?php endif; ?>
    <?php if ($trx['tanggal_selesai']): ?>
    <div class="info-row"><span class="lbl">Tgl Selesai</span><span class="val"><?= date('d F Y', strtotime($trx['tanggal_selesai'])) ?></span></div>
    <?php endif; ?>
    <div class="info-row"><span class="lbl">Keluhan</span><span class="val"><?= htmlspecialchars($trx['keluhan']) ?></span></div>
    <?php if ($trx['diagnosis']): ?>
    <div class="info-row"><span class="lbl">Diagnosis / Pekerjaan</span><span class="val"><?= htmlspecialchars($trx['diagnosis']) ?></span></div>
    <?php endif; ?>

    <!-- Rincian Biaya -->
    <div class="section-title">Rincian Biaya</div>
    <table class="biaya-table">
        <tr>
            <td class="lbl">Biaya Jasa</td>
            <td class="val">Rp <?= number_format($trx['biaya_jasa'], 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td class="lbl">Biaya Suku Cadang</td>
            <td class="val">Rp <?= number_format($trx['biaya_suku_cadang'], 0, ',', '.') ?></td>
        </tr>
        <tr class="total-row">
            <td>TOTAL</td>
            <td class="val" style="color:#2563eb">Rp <?= number_format($trx['total'], 0, ',', '.') ?></td>
        </tr>
    </table>

    <?php
    $metodeLabel = ['tunai'=>'Tunai / Cash','transfer'=>'Transfer Bank','qris'=>'QRIS','debit'=>'Kartu Debit','kredit'=>'Kartu Kredit'];
    $metodeIcon  = ['tunai'=>'💵','transfer'=>'🏦','qris'=>'📱','debit'=>'💳','kredit'=>'💳'];
    $ml = $metodeLabel[$trx['metode_bayar'] ?? 'tunai'] ?? 'Tunai / Cash';
    $mi = $metodeIcon[$trx['metode_bayar'] ?? 'tunai'] ?? '💵';
    ?>
    <div class="info-row mt-3"><span class="lbl">Metode Pembayaran</span><span class="val"><?= $mi ?> <?= htmlspecialchars($ml) ?></span></div>

    <?php if ($trx['catatan']): ?>
    <div class="section-title">Catatan</div>
    <div style="font-size:.875rem;color:#475569;background:#f8fafc;padding:10px 14px;border-radius:8px;border-left:3px solid #2563eb">
        <?= htmlspecialchars($trx['catatan']) ?>
    </div>
    <?php endif; ?>

    <!-- Footer TTD -->
    <div class="footer-nota">
        <div>
            <div>Terima kasih telah mempercayakan kendaraan Anda</div>
            <div>kepada <strong>Formula Jaya Per</strong>.</div>
            <div class="mt-2" style="font-size:.75rem">Dokumen ini dicetak pada <?= date('d F Y H:i') ?></div>
        </div>
        <div class="ttd-box">
            <div>Hormat kami,</div>
            <div class="ttd-line">Petugas</div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
