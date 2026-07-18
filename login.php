<?php

define('BASE_URL', '/KKP');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$maxAttempts = 5;
$lockSeconds = 300;

if (!isset($_SESSION['login_attempt'])) {
    $_SESSION['login_attempt'] = [
        'count' => 0,
        'locked_until' => 0,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

verifyCsrfOrExit();

$attempt = &$_SESSION['login_attempt'];
if (($attempt['locked_until'] ?? 0) > time()) {
    $remain = (int)(($attempt['locked_until'] - time()) / 60) + 1;
    $_SESSION['login_error'] = 'Terlalu banyak percobaan login. Coba lagi sekitar ' . $remain . ' menit.';
    header('Location: index.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $attempt['count'] = (int)($attempt['count'] ?? 0) + 1;
    $_SESSION['login_error'] = 'Username dan password tidak boleh kosong.';
    header('Location: index.php');
    exit();
}

if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    $attempt['count'] = (int)($attempt['count'] ?? 0) + 1;
    $_SESSION['login_error'] = 'Format username tidak valid.';
    header('Location: index.php');
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, username, password, nama_lengkap, role FROM users WHERE username = :username AND status = 'aktif' LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['login_attempt'] = ['count' => 0, 'locked_until' => 0];

        $_SESSION['user_id']      = $user['id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['user_role']    = strtolower($user['role']);

        $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $update->execute([':id' => $user['id']]);

        if ($user['role'] === 'owner') {
            header('Location: ' . BASE_URL . '/pages/laporan.php');
        } elseif ($user['role'] === 'mekanik') {
            header('Location: ' . BASE_URL . '/pages/servis.php');
        } else {
            header('Location: dashboard.php');
        }
        exit();
    } else {
        $attempt['count'] = (int)($attempt['count'] ?? 0) + 1;
        if ($attempt['count'] >= $maxAttempts) {
            $attempt['locked_until'] = time() + $lockSeconds;
            $attempt['count'] = 0;
            $_SESSION['login_error'] = 'Terlalu banyak percobaan login. Akun sementara dikunci 5 menit.';
        } else {
            $_SESSION['login_error'] = 'Username atau password salah. Silakan coba lagi.';
        }
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['login_error'] = 'Terjadi kesalahan sistem. Silakan coba beberapa saat lagi.';
    header('Location: index.php');
    exit();
}
