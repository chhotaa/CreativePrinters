<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/activity_log.php';
requirePermission('suppliers', 'view');
$canEdit = hasPermission('suppliers', 'edit');
$isSuperAdmin = (currentUser()['role_name'] ?? '') === 'Super Admin';

$message = '';
$error = '';

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['add_supplier']) || isset($_POST['update_supplier'])) {
        $id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '') {
            $error = 'Supplier name is required.';
        } else {
            $check = $pdo->prepare('SELECT id FROM suppliers WHERE name = ? AND id != ?');
            $check->execute([$name, $id ?? 0]);
            if ($check->fetch()) {
                $error = 'A supplier with that name already exists.';
            } elseif ($id) {
                $stmt = $pdo->prepare('UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?');
                $stmt->execute([$name, $contactPerson ?: null, $phone ?: null, $email ?: null, $address ?: null, $id]);
                setFlashMessage('Supplier updated.');
                logActivity('update_supplier', "Updated supplier \"$name\".");
                header('Location: suppliers.php');
                exit;
            } else {
                $stmt = $pdo->prepare('INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $contactPerson ?: null, $phone ?: null, $email ?: null, $address ?: null]);
                setFlashMessage('Supplier added.');
                logActivity('add_supplier', "Added supplier \"$name\".");
                header('Location: suppliers.php');
                exit;
            }
        }
    } elseif (isset($_POST['sync_restock_links'])) {
        if (!$isSuperAdmin) {
            http_response_code(403);
            die('Only Super Admin can run the supplier link sync.');
        }
        // Re-run the same case-insensitive backfill the linking migration
        // did. Handles restock orders created before their supplier existed
        // in the master list.
        $stmt = $pdo->prepare(
            'UPDATE restock_orders ro
             JOIN suppliers s ON LOWER(TRIM(s.name)) = LOWER(TRIM(ro.supplier_name))
             SET ro.supplier_id = s.id
             WHERE ro.supplier_id IS NULL'
        );
        $stmt->execute();
        $linked = $stmt->rowCount();
        setFlashMessage($linked === 0
            ? 'No orphan restock orders matched an existing supplier.'
            : "Linked $linked orphan restock order" . ($linked === 1 ? '' : 's') . ' to existing suppliers.');
        logActivity('sync_supplier_restock_links', "Ran restock supplier-link sync. Linked $linked row" . ($linked === 1 ? '' : 's') . '.');
        header('Location: suppliers.php');
        exit;
    } elseif (isset($_POST['delete_supplier'])) {
        $id = (int)$_POST['supplier_id'];
        $nameStmt = $pdo->prepare('SELECT name FROM suppliers WHERE id = ?');
        $nameStmt->execute([$id]);
        $deletedName = $nameStmt->fetchColumn();
        $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);
        setFlashMessage('Supplier deleted.');
        logActivity('delete_supplier', "Deleted supplier \"$deletedName\".");
        header('Location: suppliers.php');
        exit;
    }
}

$editSupplier = null;
if ($canEdit && isset($_GET['edit'])) {
    $editStmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
    $editStmt->execute([(int)$_GET['edit']]);
    $editSupplier = $editStmt->fetch();
}

$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();

// Count restock orders that are unlinked AND match an existing supplier
// by name. If zero, the sync card stays hidden.
$orphanLinkable = (int)$pdo->query(
    'SELECT COUNT(*) FROM restock_orders ro
     JOIN suppliers s ON LOWER(TRIM(s.name)) = LOWER(TRIM(ro.supplier_name))
     WHERE ro.supplier_id IS NULL'
)->fetchColumn();

$pageTitle = 'Suppliers';
include __DIR__ . '/includes/layout_start.php';
?>
    <?php if ($isSuperAdmin && $orphanLinkable > 0): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-amber-900">
            <span class="font-semibold"><?= $orphanLinkable ?></span>
            unlinked restock order<?= $orphanLinkable === 1 ? '' : 's' ?>
            match an existing supplier by name. Sync to link them now — reports and lookups will start including them.
        </div>
        <form method="POST" style="margin:0;">
                <?= csrfField() ?>
            <button type="submit" name="sync_restock_links" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 transition-colors cursor-pointer">Sync links</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3"><?= $editSupplier ? 'Edit Supplier' : 'Add Supplier' ?></h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
                <?= csrfField() ?>
            <?php if ($editSupplier): ?>
                <input type="hidden" name="supplier_id" value="<?= $editSupplier['id'] ?>">
            <?php endif; ?>
            <input type="text" name="name" placeholder="Supplier name" required value="<?= $editSupplier ? htmlspecialchars($editSupplier['name']) : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="contact_person" placeholder="Contact person" value="<?= $editSupplier ? htmlspecialchars($editSupplier['contact_person'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="phone" placeholder="Phone" value="<?= $editSupplier ? htmlspecialchars($editSupplier['phone'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="email" name="email" placeholder="Email" value="<?= $editSupplier ? htmlspecialchars($editSupplier['email'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="address" placeholder="Address" value="<?= $editSupplier ? htmlspecialchars($editSupplier['address'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <button type="submit" name="<?= $editSupplier ? 'update_supplier' : 'add_supplier' ?>" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer"><?= $editSupplier ? 'Save Changes' : 'Add Supplier' ?></button>
            <?php if ($editSupplier): ?>
                <a href="suppliers.php" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <input type="text" id="suppliersTableSearch" placeholder="Search suppliers..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                Show
                <select id="suppliersTablePageSize" class="px-2 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="all">All</option>
                </select>
                entries
            </label>
        </div>
        <div class="overflow-x-auto">
        <table id="suppliersTable" class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Name</th>
                    <th class="text-left px-3 py-2 font-semibold">Contact Person</th>
                    <th class="text-left px-3 py-2 font-semibold">Phone</th>
                    <th class="text-left px-3 py-2 font-semibold">Email</th>
                    <th class="text-left px-3 py-2 font-semibold">Address</th>
                    <?php if ($canEdit): ?><th class="text-left px-3 py-2 font-semibold rounded-tr-md"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($suppliers as $s): ?>
                <tr class="border-b border-slate-100 even:bg-slate-50 hover:bg-slate-100">
                    <td class="px-3 py-2"><a href="supplier_detail.php?id=<?= (int)$s['id'] ?>" class="text-brand-green hover:underline font-medium"><?= htmlspecialchars($s['name']) ?></a></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($s['contact_person'] ?? '') ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($s['phone'] ?? '') ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($s['email'] ?? '') ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($s['address'] ?? '') ?></td>
                    <?php if ($canEdit): ?>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <a href="?edit=<?= $s['id'] ?>" class="px-3 py-1.5 rounded-md bg-brand-dark text-white text-xs font-semibold hover:bg-slate-700 transition-colors inline-block">Edit</a>
                        <form method="POST" onsubmit="return confirm('Delete this supplier?');" style="display:inline-block; margin:0;">
                <?= csrfField() ?>
                            <input type="hidden" name="supplier_id" value="<?= $s['id'] ?>">
                            <button type="submit" name="delete_supplier" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-sm text-slate-600">
            <span id="suppliersTableInfo"></span>
            <div class="flex gap-2">
                <button type="button" id="suppliersTablePrev" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                <button type="button" id="suppliersTableNext" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
