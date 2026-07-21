<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/activity_log.php';
requireSuperAdmin();

$message = '';
$error = '';

$superAdminRoleId = (int)$pdo->query("SELECT id FROM roles WHERE name = 'Super Admin'")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $roleId = (int)$_POST['role_id'];
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $check->execute([$username]);
            if ($check->fetch()) {
                $error = 'That username already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role_id, email, phone) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$username, $hash, $roleId, $email, $phone ?: null]);
                setFlashMessage('User added.');
                $roleNameStmt = $pdo->prepare('SELECT name FROM roles WHERE id = ?');
                $roleNameStmt->execute([$roleId]);
                logActivity('add_user', "Created user \"$username\" with role \"{$roleNameStmt->fetchColumn()}\".");
                header('Location: users.php');
                exit;
            }
        }
    } elseif (isset($_POST['update_user'])) {
        $id = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $roleId = (int)$_POST['role_id'];
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $newPassword = $_POST['password'] ?? '';

        if ($username === '') {
            $error = 'Username is required.';
        } elseif ($id === (int)$_SESSION['user_id'] && $roleId !== $superAdminRoleId) {
            $error = "You can't remove your own Super Admin access.";
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $check->execute([$username, $id]);
            if ($check->fetch()) {
                $error = 'That username already exists.';
            } else {
                if ($newPassword !== '') {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET username = ?, role_id = ?, email = ?, phone = ?, password_hash = ? WHERE id = ?');
                    $stmt->execute([$username, $roleId, $email, $phone ?: null, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET username = ?, role_id = ?, email = ?, phone = ? WHERE id = ?');
                    $stmt->execute([$username, $roleId, $email, $phone ?: null, $id]);
                }
                setFlashMessage('User updated.');
                $roleNameStmt = $pdo->prepare('SELECT name FROM roles WHERE id = ?');
                $roleNameStmt->execute([$roleId]);
                logActivity('update_user', "Updated user \"$username\" (role: \"{$roleNameStmt->fetchColumn()}\")." . ($newPassword !== '' ? ' Password was changed.' : ''));
                header('Location: users.php');
                exit;
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $id = (int)$_POST['user_id'];
        if ($id === (int)$_SESSION['user_id']) {
            $error = "You can't delete your own account while logged in.";
        } else {
            $nameStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $nameStmt->execute([$id]);
            $deletedUsername = $nameStmt->fetchColumn();
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            setFlashMessage('User deleted.');
            logActivity('delete_user', "Deleted user \"$deletedUsername\".");
            header('Location: users.php');
            exit;
        }
    }
}

$roles = $pdo->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $editStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $editStmt->execute([(int)$_GET['edit']]);
    $editUser = $editStmt->fetch();
}

$users = $pdo->query('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.username')->fetchAll();
$pageTitle = 'Users';
include __DIR__ . '/includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3"><?= $editUser ? 'Edit User' : 'Add User' ?></h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
                <?= csrfField() ?>
            <?php if ($editUser): ?>
                <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
            <?php endif; ?>
            <input type="text" name="username" placeholder="Username" required value="<?= $editUser ? htmlspecialchars($editUser['username']) : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="password" name="password" placeholder="<?= $editUser ? 'New password (leave blank to keep current)' : 'Password' ?>" <?= $editUser ? '' : 'required' ?> class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <select name="role_id" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $role['id'] ?>" <?= $editUser && (int)$editUser['role_id'] === (int)$role['id'] ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="email" name="email" placeholder="Email" value="<?= $editUser ? htmlspecialchars($editUser['email'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="phone" placeholder="Phone (for SMS/WhatsApp reminders)" value="<?= $editUser ? htmlspecialchars($editUser['phone'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
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
                    <th class="text-left px-3 py-2 font-semibold">Phone</th>
                    <th class="text-left px-3 py-2 font-semibold">Created</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr class="border-b border-slate-100 even:bg-slate-50 hover:bg-slate-100">
                    <td class="px-3 py-2"><?= htmlspecialchars($u['username']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($u['role_name']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($u['email']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($u['phone'] ?? '') ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($u['created_at']) ?></td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <a href="?edit=<?= $u['id'] ?>" class="px-3 py-1.5 rounded-md bg-brand-dark text-white text-xs font-semibold hover:bg-slate-700 transition-colors inline-block">Edit</a>
                        <form method="POST" onsubmit="return confirm('Delete this user?');" style="display:inline-block; margin:0;">
                <?= csrfField() ?>
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
<?php include __DIR__ . '/includes/layout_end.php'; ?>
