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

function currentUser() {
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    if (!isset($_SESSION['user_id'])) {
        return $user = ['id' => null, 'username' => null, 'email' => null, 'role_id' => null, 'role_name' => null];
    }
    global $pdo;
    $stmt = $pdo->prepare('SELECT u.id, u.username, u.email, u.role_id, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    return $user = $row ?: ['id' => null, 'username' => null, 'email' => null, 'role_id' => null, 'role_name' => null];
}

function hasPermission($moduleKey, $level = 'view') {
    $user = currentUser();
    if ($user['role_name'] === 'Super Admin') {
        return true;
    }
    if (!$user['role_id']) {
        return false;
    }
    static $cache = [];
    $key = $user['role_id'] . ':' . $moduleKey;
    if (!array_key_exists($key, $cache)) {
        global $pdo;
        $stmt = $pdo->prepare('SELECT access_level FROM role_permissions WHERE role_id = ? AND module_key = ?');
        $stmt->execute([$user['role_id'], $moduleKey]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? $row['access_level'] : 'none';
    }
    $rank = ['none' => 0, 'view' => 1, 'edit' => 2];
    return ($rank[$cache[$key]] ?? 0) >= ($rank[$level] ?? 1);
}

function requirePermission($moduleKey, $level = 'view') {
    requireLogin();
    if (!hasPermission($moduleKey, $level)) {
        http_response_code(403);
        die('Access denied. You do not have permission to access this page.');
    }
}

function requireSuperAdmin() {
    requireLogin();
    if (currentUser()['role_name'] !== 'Super Admin') {
        http_response_code(403);
        die('Access denied. Super Admin login required.');
    }
}

function redirectToDashboard() {
    header('Location: /app/index.php');
    exit;
}
