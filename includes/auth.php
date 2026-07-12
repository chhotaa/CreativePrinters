<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /app/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die('Access denied. Admin login required.');
    }
}

function currentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
    ];
}

function redirectToDashboard($role) {
    header('Location: ' . ($role === 'admin' ? '/app/admin/index.php' : '/app/user/dues.php'));
    exit;
}
