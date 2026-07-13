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
$pageTitle = 'Users';
include __DIR__ . '/../includes/layout_start.php';
?>
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

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <input type="text" placeholder="Search users..." oninput="filterTable(this, 'usersTable')" class="w-full sm:w-64 mb-3 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
        <div class="overflow-x-auto">
        <table id="usersTable" class="w-full text-sm border-collapse">
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
                <tr class="border-b border-slate-100 even:bg-slate-50 hover:bg-slate-100">
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
    </div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
