<?php
require_once __DIR__ . '/config/database.php';

$steps = [];
$errors = [];
$rebuild = isset($_GET['rebuild']) && $_GET['rebuild'] === '1';

$queries = [
    'Tabel users' => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        nama_lengkap VARCHAR(100) NOT NULL,
        role ENUM('admin', 'mekanik', 'owner') NOT NULL DEFAULT 'mekanik',
        email VARCHAR(100),
        telepon VARCHAR(20),
        foto VARCHAR(255) DEFAULT NULL,
        status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
        last_login DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    'Akun default admin' => "INSERT INTO users (username, password, nama_lengkap, role, email, telepon, status) VALUES
        ('admin', '\$2y\$10\$AkReX1D4wTYe4QjYLt7EwOGMpB2h7yWf/rLWcOfqLjb2skugcj3Su', 'Administrator', 'admin', 'admin@formulajaya.com', '08123456789', 'aktif')
        ON DUPLICATE KEY UPDATE
            password = VALUES(password),
            nama_lengkap = VALUES(nama_lengkap),
            role = VALUES(role),
            email = VALUES(email),
            telepon = VALUES(telepon),
            status = VALUES(status)",

    'Akun default owner' => "INSERT INTO users (username, password, nama_lengkap, role, email, telepon, status) VALUES
        ('owner', '\$2y\$10\$I/pi3gaOcl9.DIJOf0s8.uJFZit8RvSbBs/dv0Rc59pbKpQ9ELeam', 'Owner Utama', 'owner', 'owner@formulajaya.com', '08129876543', 'aktif')
        ON DUPLICATE KEY UPDATE
            password = VALUES(password),
            nama_lengkap = VALUES(nama_lengkap),
            role = VALUES(role),
            email = VALUES(email),
            telepon = VALUES(telepon),
            status = VALUES(status)",

    'Tabel pelanggan' => "CREATE TABLE IF NOT EXISTS pelanggan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode_pelanggan VARCHAR(20) NOT NULL UNIQUE,
        nama VARCHAR(100) NOT NULL,
        jenis_kelamin ENUM('L','P') DEFAULT NULL,
        no_telepon VARCHAR(20) NOT NULL,
        email VARCHAR(100) DEFAULT NULL,
        alamat TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    'Tabel kendaraan' => "CREATE TABLE IF NOT EXISTS kendaraan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pelanggan_id INT NOT NULL,
        no_polisi VARCHAR(20) NOT NULL UNIQUE,
        merk VARCHAR(50) NOT NULL,
        model VARCHAR(50) NOT NULL,
        tahun YEAR NOT NULL,
        warna VARCHAR(30) DEFAULT NULL,
        no_rangka VARCHAR(50) DEFAULT NULL,
        no_mesin VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    'Tabel stok' => "CREATE TABLE IF NOT EXISTS stok (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode_barang VARCHAR(20) NOT NULL UNIQUE,
        nama_barang VARCHAR(100) NOT NULL,
        satuan VARCHAR(20) NOT NULL DEFAULT 'pcs',
        stok INT NOT NULL DEFAULT 0,
        harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0,
        harga_jual DECIMAL(12,2) NOT NULL DEFAULT 0,
        keterangan TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    'Tabel servis' => "CREATE TABLE IF NOT EXISTS servis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_servis VARCHAR(20) NOT NULL UNIQUE,
        pelanggan_id INT NOT NULL,
        kendaraan_id INT NOT NULL,
        tanggal_masuk DATE NOT NULL,
        estimasi_selesai DATE DEFAULT NULL,
        tanggal_selesai DATE DEFAULT NULL,
        keluhan TEXT NOT NULL,
        diagnosis TEXT DEFAULT NULL,
        odometer INT DEFAULT NULL,
        status ENUM('masuk','proses','selesai','diambil') NOT NULL DEFAULT 'masuk',
        catatan TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id),
        FOREIGN KEY (kendaraan_id) REFERENCES kendaraan(id)
    ) ENGINE=InnoDB",

    'Tabel transaksi' => "CREATE TABLE IF NOT EXISTS transaksi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_transaksi VARCHAR(20) NOT NULL UNIQUE,
        servis_id INT NOT NULL,
        tanggal DATE NOT NULL,
        biaya_jasa DECIMAL(12,2) NOT NULL DEFAULT 0,
        biaya_suku_cadang DECIMAL(12,2) NOT NULL DEFAULT 0,
        diskon DECIMAL(12,2) NOT NULL DEFAULT 0,
        total DECIMAL(12,2) NOT NULL DEFAULT 0,
        status_bayar ENUM('belum','lunas') NOT NULL DEFAULT 'belum',
        metode_bayar ENUM('tunai','transfer','qris','debit','kredit') NOT NULL DEFAULT 'tunai',
        catatan TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (servis_id) REFERENCES servis(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    'Kolom metode_bayar (jika tabel lama)' => "ALTER TABLE transaksi ADD COLUMN IF NOT EXISTS metode_bayar ENUM('tunai','transfer','qris','debit','kredit') NOT NULL DEFAULT 'tunai' AFTER status_bayar",
];

if ($rebuild) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('DROP TABLE IF EXISTS transaksi');
        $pdo->exec('DROP TABLE IF EXISTS servis');
        $pdo->exec('DROP TABLE IF EXISTS kendaraan');
        $pdo->exec('DROP TABLE IF EXISTS stok');
        $pdo->exec('DROP TABLE IF EXISTS pelanggan');
        $pdo->exec('DROP TABLE IF EXISTS users');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $steps[] = 'Mode rebuild: tabel lama sudah dihapus';
    } catch (PDOException $e) {
        $errors[] = 'Mode rebuild gagal: ' . $e->getMessage();
    }
}

foreach ($queries as $label => $sql) {
    try {
        $pdo->exec($sql);
        $steps[] = $label;
    } catch (PDOException $e) {
        $errors[] = "$label: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Setup Database - Formula Jaya Per</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="card shadow border-0 p-4" style="max-width:480px;width:100%">
        <h5 class="fw-bold mb-4"><i class="fas fa-database me-2"></i>Setup Database</h5>
        <?php foreach ($steps as $s): ?>
            <div class="alert alert-success py-2 mb-2">✅ <?= htmlspecialchars($s) ?> berhasil dibuat</div>
        <?php endforeach; ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger py-2 mb-2">❌ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
        <?php if (empty($errors)): ?>
        <div class="alert alert-info py-2 mt-2">
            <?= $rebuild ? 'Schema berhasil dibangun ulang!' : 'Semua tabel berhasil disiapkan!' ?>
        </div>
        <a href="/KKP/pages/pelanggan.php" class="btn btn-primary w-100 mt-2">Buka Halaman Pelanggan</a>
        <?php endif; ?>
        <p class="text-danger small mt-3 mb-0">⚠️ Hapus file <strong>migrate.php</strong> ini setelah selesai.</p>
        <?php if (!$rebuild && empty($errors)): ?>
        <p class="text-muted small mt-2 mb-0">Jika dashboard masih error, buka halaman ini dengan parameter <strong>?rebuild=1</strong> untuk membangun ulang schema.</p>
        <?php endif; ?>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
