<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $email = trim($_POST['email']);

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $check->execute([$username]);
            if ($check->fetch()) {
                $error = 'That username already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, email) VALUES (?, ?, ?, ?)');
                $stmt->execute([$username, $hash, $role, $email]);
                $message = 'User added.';
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $id = (int)$_POST['user_id'];
        if ($id === (int)$_SESSION['user_id']) {
            $error = "You can't delete your own account while logged in.";
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $message = 'User deleted.';
        }
    }
}

$users = $pdo->query('SELECT * FROM users ORDER BY username')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Creative Printers</title>
    <?php include __DIR__ . '/../includes/tailwind_head.php'; ?>
</head>
<body class="bg-slate-50 text-slate-800 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h2 class="text-2xl font-bold text-brand-dark">Users</h2>
        <div class="flex flex-wrap items-center gap-1">
            <a href="index.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Dashboard</a>
            <a href="restock_orders.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Restock Orders</a>
            <a href="../logout.php" class="ml-2 px-3 py-1.5 rounded-md text-sm font-semibold bg-brand-green text-white hover:bg-brand-greendark transition-colors">Log Out</a>
        </div>
    </div>

    <?php if ($message): ?><div class="text-green-700 text-sm bg-green-50 border border-green-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="text-red-600 text-sm bg-red-50 border border-red-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Add User</h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
            <input type="text" name="username" placeholder="Username" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="password" name="password" placeholder="Password" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <select name="role" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <option value="user">user (view due dates only)</option>
                <option value="admin">admin (full access)</option>
            </select>
            <input type="email" name="email" placeholder="Email" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <button type="submit" name="add_user" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Add User</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5 overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Username</th>
                    <th class="text-left px-3 py-2 font-semibold">Role</th>
                    <th class="text-left px-3 py-2 font-semibold">Email</th>
                    <th class="text-left px-3 py-2 font-semibold">Created</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2"><?= htmlspecialchars($u['username']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($u['role']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($u['email']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($u['created_at']) ?></td>
                    <td class="px-3 py-2">
                        <form method="POST" onsubmit="return confirm('Delete this user?');" style="margin:0;">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" name="delete_user" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
