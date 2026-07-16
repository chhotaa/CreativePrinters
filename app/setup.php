<?php
// ============================================================
// RUN THIS ONCE by visiting yourdomain.com/app/setup.php in your browser.
// It creates the first admin login. DELETE THIS FILE straight after,
// since anyone could otherwise re-run it.
// ============================================================
require_once __DIR__ . '/includes/db.php';

$stmt = $pdo->query('SELECT COUNT(*) as cnt FROM users');
$count = $stmt->fetch()['cnt'];

if ($count > 0) {
    die('Setup already completed - users already exist. Please delete this file.');
}

$defaultUsername = 'admin';
$defaultPassword = 'ChangeMe123'; // change this immediately after logging in
$defaultEmail = 'youremail@example.com'; // change to your real email for reminders

$roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
$roleStmt->execute(['Super Admin']);
$superAdminRoleId = $roleStmt->fetchColumn();
if (!$superAdminRoleId) {
    die('Setup failed: the "Super Admin" role does not exist. Run the RBAC migration (app/migrations/2026-07-15_add_rbac.sql) first.');
}

$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role_id, email) VALUES (?, ?, ?, ?)');
$stmt->execute([$defaultUsername, $hash, $superAdminRoleId, $defaultEmail]);

echo "Setup complete.<br>";
echo "Username: <b>$defaultUsername</b><br>";
echo "Password: <b>$defaultPassword</b><br><br>";
echo "<strong>Please log in now, change this password, and then DELETE this setup.php file.</strong>";
