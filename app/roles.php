<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/activity_log.php';
requireSuperAdmin();

$message = '';
$error = '';

// To add a new page/module to this matrix: build the page with
// requirePermission('key', 'view') + hasPermission('key', 'edit') gating
// its write actions, then add one line here, e.g. 'key' => 'Label'.
// It will automatically appear below for every role, no other changes
// needed. Remember to also add a matching line to the nav list in
// includes/layout_start.php so the page is reachable once permitted.
$modules = [
    'stock' => 'Stock',
    'purchase_orders' => 'Purchase Orders',
    'deliveries' => 'Delivery Schedule',
    'restock_orders' => 'Restock Orders',
    'job_cards' => 'Job Cards',
    'customers' => 'Customers',
    'suppliers' => 'Suppliers',
    'reports' => 'Reports',
    'activity_log' => 'Activity Log',
];
$accessLevels = ['none' => 'None', 'view' => 'View', 'edit' => 'Edit'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_role'])) {
        $name = trim($_POST['role_name'] ?? '');
        if ($name === '') {
            $error = 'Role name is required.';
        } else {
            $check = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
            $check->execute([$name]);
            if ($check->fetch()) {
                $error = 'A role with that name already exists.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO roles (name, is_system) VALUES (?, 0)');
                $stmt->execute([$name]);
                setFlashMessage("Role \"$name\" created. Set its permissions below.");
                logActivity('create_role', "Created role \"$name\".");
                header('Location: roles.php');
                exit;
            }
        }
    } elseif (isset($_POST['save_role_permissions'])) {
        $roleId = (int)$_POST['role_id'];
        $roleCheck = $pdo->prepare('SELECT name FROM roles WHERE id = ? AND is_system = 0');
        $roleCheck->execute([$roleId]);
        $roleName = $roleCheck->fetchColumn();
        if ($roleName === false) {
            $error = 'That role could not be found.';
        } else {
            $submitted = $_POST['permissions'] ?? [];
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO role_permissions (role_id, module_key, access_level) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE access_level = VALUES(access_level)'
                );
                foreach (array_keys($modules) as $moduleKey) {
                    $level = $submitted[$moduleKey] ?? 'none';
                    if (!array_key_exists($level, $accessLevels)) {
                        $level = 'none';
                    }
                    $stmt->execute([$roleId, $moduleKey, $level]);
                }
                $pdo->commit();
                setFlashMessage('Permissions saved.');
                logActivity('save_role_permissions', "Updated permissions for role \"$roleName\".");
                header('Location: roles.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Could not save permissions. Please try again.';
            }
        }
    }
}

$roles = $pdo->query('SELECT id, name FROM roles WHERE is_system = 0 ORDER BY id')->fetchAll();

$currentPermissions = [];
$permRows = $pdo->query('SELECT role_id, module_key, access_level FROM role_permissions')->fetchAll();
foreach ($permRows as $row) {
    $currentPermissions[$row['role_id']][$row['module_key']] = $row['access_level'];
}

$pageTitle = 'Roles & Permissions';
include __DIR__ . '/includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Add New Role</h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
            <input type="text" name="role_name" placeholder="e.g. Production Manager" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <button type="submit" name="create_role" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Add Role</button>
        </form>
        <p class="text-sm text-slate-500 mt-2">A new role starts with no access anywhere — set its permissions below once created.</p>
    </div>

    <p class="text-sm text-slate-500 mb-4">"Super Admin" always has full access everywhere and isn't shown here. Each role's permissions save independently — changing one role does not affect the others.</p>

    <?php foreach ($roles as $role): ?>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
            <h3 class="text-lg font-semibold text-brand-dark mb-3"><?= htmlspecialchars($role['name']) ?></h3>
            <form method="POST">
                <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-3">
                    <?php foreach ($modules as $moduleKey => $label): ?>
                        <?php $current = $currentPermissions[$role['id']][$moduleKey] ?? 'none'; ?>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1"><?= htmlspecialchars($label) ?></label>
                            <select name="permissions[<?= $moduleKey ?>]" class="w-full px-2 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                                <?php foreach ($accessLevels as $level => $levelLabel): ?>
                                    <option value="<?= $level ?>" <?= $current === $level ? 'selected' : '' ?>><?= $levelLabel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="save_role_permissions" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Save <?= htmlspecialchars($role['name']) ?> Permissions</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
