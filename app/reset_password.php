<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/activity_log.php';

if (isset($_SESSION['user_id'])) {
    redirectToDashboard();
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$tokenValid = false;
$resetUser = null;

if ($token !== '') {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT pr.id AS reset_id, pr.used_at, u.id AS user_id, u.username,
                (pr.expires_at < NOW()) AS is_expired
         FROM password_resets pr JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?'
    );
    $stmt->execute([$tokenHash]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $error = 'This reset link is invalid. Please request a new one.';
    } elseif ($reset['used_at'] !== null) {
        $error = 'This reset link has already been used. Please request a new one.';
    } elseif ((int)$reset['is_expired'] === 1) {
        $error = 'This reset link has expired. Please request a new one.';
    } else {
        $tokenValid = true;
        $resetUser = $reset;
    }
} else {
    $error = 'No reset token provided.';
}

if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$hash, $resetUser['user_id']]);

        $markUsed = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $markUsed->execute([$resetUser['reset_id']]);

        logActivity('password_reset_completed', "Password reset completed for \"{$resetUser['username']}\".", $resetUser['username']);
        setFlashMessage('Your password has been reset. Please log in.');
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Creative Printers</title>
    <?php include __DIR__ . '/includes/tailwind_head.php'; ?>
</head>
<body class="min-h-screen flex items-center justify-center bg-slate-50 p-5">
    <div class="w-full max-w-sm bg-white rounded-xl shadow-md ring-1 ring-slate-200 p-8">
        <h2 class="text-2xl font-bold text-brand-dark text-center mb-6">Creative Printers</h2>
        <?php if ($error): ?><div class="text-red-600 text-sm bg-red-50 border border-red-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($tokenValid): ?>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="password" name="new_password" placeholder="New password (min. 6 characters)" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <input type="password" name="confirm_password" placeholder="Confirm new password" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <button type="submit" name="reset_password" value="1" class="w-full px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Set New Password</button>
            </form>
        <?php else: ?>
            <a href="forgot_password.php" class="block text-center text-sm font-semibold text-brand-green hover:text-brand-greendark">Request a new reset link</a>
        <?php endif; ?>
    </div>
</body>
</html>
