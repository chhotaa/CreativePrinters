<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    redirectToDashboard($_SESSION['role']);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            redirectToDashboard($user['role']);
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Creative Printers</title>
    <?php include __DIR__ . '/includes/tailwind_head.php'; ?>
</head>
<body class="min-h-screen flex items-center justify-center bg-slate-50 p-5">
    <div class="w-full max-w-sm bg-white rounded-xl shadow-md ring-1 ring-slate-200 p-8">
        <h2 class="text-2xl font-bold text-brand-dark text-center mb-6">Creative Printers</h2>
        <?php if ($error): ?><div class="text-red-600 text-sm bg-red-50 border border-red-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" class="space-y-3">
            <input type="text" name="username" placeholder="Username" required autofocus class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="password" name="password" placeholder="Password" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <button type="submit" class="w-full px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Log In</button>
        </form>
    </div>
</body>
</html>
