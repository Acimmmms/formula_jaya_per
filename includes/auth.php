<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (preg_match('#(/KKP)#i', $_SERVER['SCRIPT_NAME'], $m)) {
        define('BASE_URL', $m[1]);
    } else {
        define('BASE_URL', rtrim($scriptDir, '/'));
    }
}
function requireLogin() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }
}
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}
function hasRole($role) {
    if (!isLoggedIn()) return false;
    if (is_array($role)) {
        return in_array($_SESSION['user_role'], $role);
    }
    return $_SESSION['user_role'] === $role;
}
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        $redirectPath = '/dashboard.php';
        $currentRole = strtolower($_SESSION['user_role'] ?? '');
        if ($currentRole === 'owner') {
            $redirectPath = '/pages/laporan.php';
        } elseif ($currentRole === 'mekanik') {
            $redirectPath = '/pages/servis.php';
        }
        header('Location: ' . BASE_URL . $redirectPath);
        exit();
    }
}
function getUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'           => $_SESSION['user_id'],
        'username'     => $_SESSION['username'],
        'nama_lengkap' => $_SESSION['nama_lengkap'],
        'role'         => strtolower($_SESSION['user_role']),
    ];
}
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}
function verifyCsrfOrExit() {
    $token = $_POST['csrf_token'] ?? '';
    $valid = is_string($token) && hash_equals(csrfToken(), $token);

    if (!$valid) {
        http_response_code(419);
        echo 'CSRF token tidak valid. Silakan refresh halaman dan coba lagi.';
        exit();
    }
}
