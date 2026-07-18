<?php
// API: ambil kendaraan berdasarkan pelanggan_id
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$pelanggan_id = (int)($_GET['pelanggan_id'] ?? 0);
if (!$pelanggan_id) {
    echo json_encode([]); exit();
}

$stmt = $pdo->prepare("SELECT id, no_polisi, merk, model, tahun FROM kendaraan WHERE pelanggan_id = ? ORDER BY no_polisi");
$stmt->execute([$pelanggan_id]);
echo json_encode($stmt->fetchAll());
