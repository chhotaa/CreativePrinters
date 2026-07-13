<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
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
                setFlashMessage('User added.');
                header('Location: users.php');
                exit;
            }
        }
    } elseif (isset($_POST['update_user'])) {
        $id = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $role = $_POST['role'];
        $email = trim($_POST['email']);
        $newPassword = $_POST['password'] ?? '';

        if ($username === '') {
            $error = 'Username is required.';
        } elseif ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
            $error = "You can't remove your own admin access.";
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $check->execute([$username, $id]);
            if ($check->fetch()) {
                $error = 'That username already exists.';
            } else {
                if ($newPassword !== '') {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET username = ?, role = ?, email = ?, password_hash = ? WHERE id = ?');
                    $stmt->execute([$username, $role, $email, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET username = ?, role = ?, email = ? WHERE id = ?');
                    $stmt->execute([$username, $role, $email, $id]);
                }
                if ($id === (int)$_SESSION['user_id']) {
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;
                }
                setFlashMessage('User updated.');
                header('Location: users.php');
                exit;
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $id = (int)$_POST['user_id'];
        if ($id === (int)$_SESSION['user_id']) {
            $error = "You can't delete your own account while logged in.";
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            setFlashMessage('User deleted.');
            header('Location: users.php');
            exit;
        }
    }
}

$editUser = null;
if (isset($_GET['edit'])) {
    $editStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $editStmt->execute([(int)$_GET['edit']]);
    $editUser = $editStmt->fetch();
}

$users = $pdo->query('SELECT * FROM users ORDER BY username')->fetchAll();
$pageTitle = 'Users';
include __DIR__ . '/../includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3"><?= $editUser ? 'Edit User' : 'Add User' ?></h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
            <?php if ($editUser): ?>
                <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
            <?php endif; ?>
            <input type="text" name="username" placeholder="Username" required value="<?= $editUser ? htmlspecialchars($editUser['username']) : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="password" name="password" placeholder="<?= $editUser ? 'New password (leave blank to keep current)' : 'Password' ?>" <?= $editUser ? '' : 'required' ?> class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <select name="role" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <option value="user" <?= $editUser && $editUser['role'] === 'user' ? 'selected' : '' ?>>user (view due dates only)</option>
                <option value="admin" <?= $editUser && $editUser['role'] === 'admin' ? 'selected' : '' ?>>admin (full access)</option>
            </select>
            <input type="email" name="email" placeholder="Email" value="<?= $editUser ? htmlspecialchars($editUser['email'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <button type="submit" name="<?= $editUser ? 'update_user' : 'add_user' ?>" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer"><?= $editUser ? 'Save Changes' : 'Add User' ?></button>
            <?php if ($editUser): ?>
                <a href="users.php" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <input type="text" id="usersTableSearch" placeholder="Search users..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                Show
                <select id="usersTablePageSize" class="px-2 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="all">All</option>
                </select>
                entries
            </label>
        </div>
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
                    <td class="px-3 py-2 whitespace-nowrap">
                        <a href="?edit=<?= $u['id'] ?>" class="px-3 py-1.5 rounded-md bg-brand-dark text-white text-xs font-semibold hover:bg-slate-700 transition-colors inline-block">Edit</a>
                        <form method="POST" onsubmit="return confirm('Delete this user?');" style="display:inline-block; margin:0;">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" name="delete_user" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-sm text-slate-600">
            <span id="usersTableInfo"></span>
            <div class="flex gap-2">
                <button type="button" id="usersTablePrev" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                <button type="button" id="usersTableNext" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
