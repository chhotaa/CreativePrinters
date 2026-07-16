<?php
function logActivity($action, $description, $usernameOverride = null) {
    global $pdo;
    $userId = $_SESSION['user_id'] ?? null;
    $username = $usernameOverride;
    $roleName = null;
    if ($userId) {
        $user = currentUser();
        $username = $user['username'];
        $roleName = $user['role_name'];
    }
    $stmt = $pdo->prepare(
        'INSERT INTO activity_log (user_id, username, role_name, action, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $username, $roleName, $action, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
}
