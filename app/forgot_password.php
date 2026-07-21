<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_log.php';

if (isset($_SESSION['user_id'])) {
    redirectToDashboard();
}

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $submitted = true;

    if ($username !== '') {
        $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && !empty($user['email'])) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
            $insert->execute([$user['id'], $tokenHash, $expiresAt]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $resetLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/app/reset_password.php?token=' . $token;

            $subject = 'Reset your Creative Printers password';
            $body = "Hi {$user['username']},\n\nSomeone requested a password reset for your account. If this was you, click the link below within 1 hour:\n\n$resetLink\n\nIf you didn't request this, you can ignore this email.";
            $headers = "From: no-reply@creativeprintingsolution.in\r\n";
            @mail($user['email'], $subject, $body, $headers);

            logActivity('password_reset_requested', "Password reset requested for \"{$user['username']}\".", $user['username']);
        }
        // Same message shown whether or not the username matched, and
        // whether or not it has an email on file — never reveal which.
    }
}

$pageTitle = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Creative Printers</title>
    <?php include __DIR__ . '/includes/tailwind_head.php'; ?>
</head>
<body class="app-bg min-h-screen flex items-center justify-center p-5 text-slate-800">
    <div class="w-full max-w-sm bg-white rounded-xl shadow-2xl ring-1 ring-white/20 p-8">
        <h2 class="text-2xl font-bold text-brand-dark text-center mb-1">Creative Printers</h2>
        <p class="text-center text-sm text-slate-500 mb-6">Print business, organized</p>
        <?php if ($submitted): ?>
            <div class="text-green-700 text-sm bg-green-50 border border-green-200 rounded-md px-3 py-2 mb-4">If an account with that username exists, we've emailed a password reset link. Check your inbox.</div>
            <a href="login.php" class="block text-center text-sm font-semibold text-brand-green hover:text-brand-greendark">Back to Log In</a>
        <?php else: ?>
            <p class="text-sm text-slate-500 mb-4 text-center">Enter your username and we'll email you a reset link.</p>
            <form method="POST" class="space-y-3">
                <input type="text" name="username" placeholder="Username" required autofocus class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <button type="submit" class="w-full px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Send Reset Link</button>
            </form>
            <a href="login.php" class="block text-center text-sm text-slate-500 hover:text-slate-700 mt-4">Back to Log In</a>
        <?php endif; ?>
    </div>
</body>
</html>
