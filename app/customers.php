<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/activity_log.php';
requirePermission('customers', 'view');
$canEdit = hasPermission('customers', 'edit');
$isSuperAdmin = (currentUser()['role_name'] ?? '') === 'Super Admin';

$message = '';
$error = '';

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['add_customer']) || isset($_POST['update_customer'])) {
        $id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '') {
            $error = 'Customer name is required.';
        } else {
            $check = $pdo->prepare('SELECT id FROM customers WHERE name = ? AND id != ?');
            $check->execute([$name, $id ?? 0]);
            if ($check->fetch()) {
                $error = 'A customer with that name already exists.';
            } elseif ($id) {
                $stmt = $pdo->prepare('UPDATE customers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?');
                $stmt->execute([$name, $contactPerson ?: null, $phone ?: null, $email ?: null, $address ?: null, $id]);
                setFlashMessage('Customer updated.');
                logActivity('update_customer', "Updated customer \"$name\".");
                header('Location: customers.php');
                exit;
            } else {
                $stmt = $pdo->prepare('INSERT INTO customers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $contactPerson ?: null, $phone ?: null, $email ?: null, $address ?: null]);
                setFlashMessage('Customer added.');
                logActivity('add_customer', "Added customer \"$name\".");
                header('Location: customers.php');
                exit;
            }
        }
    } elseif (isset($_POST['sync_po_links'])) {
        if (!$isSuperAdmin) {
            http_response_code(403);
            die('Only Super Admin can run the customer link sync.');
        }
        // Re-run the same case-insensitive backfill the linking migration
        // did originally. Useful when a customer has been added AFTER their
        // POs already existed as free-text.
        $stmt = $pdo->prepare(
            'UPDATE purchase_orders po
             JOIN customers c ON LOWER(TRIM(c.name)) = LOWER(TRIM(po.customer_name))
             SET po.customer_id = c.id
             WHERE po.customer_id IS NULL'
        );
        $stmt->execute();
        $linked = $stmt->rowCount();
        setFlashMessage($linked === 0
            ? 'No orphan POs matched an existing customer.'
            : "Linked $linked orphan PO row" . ($linked === 1 ? '' : 's') . ' to existing customers.');
        logActivity('sync_customer_po_links', "Ran PO customer-link sync. Linked $linked row" . ($linked === 1 ? '' : 's') . '.');
        header('Location: customers.php');
        exit;
    } elseif (isset($_POST['delete_customer'])) {
        $id = (int)$_POST['customer_id'];
        $nameStmt = $pdo->prepare('SELECT name FROM customers WHERE id = ?');
        $nameStmt->execute([$id]);
        $deletedName = $nameStmt->fetchColumn();
        $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        setFlashMessage('Customer deleted.');
        logActivity('delete_customer', "Deleted customer \"$deletedName\".");
        header('Location: customers.php');
        exit;
    }
}

$editCustomer = null;
if ($canEdit && isset($_GET['edit'])) {
    $editStmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $editStmt->execute([(int)$_GET['edit']]);
    $editCustomer = $editStmt->fetch();
}

$customers = $pdo->query('SELECT * FROM customers ORDER BY name')->fetchAll();

// How many POs remain unlinked AND have a customer_name that matches an
// existing customer master (case-insensitively). These are what the sync
// button would resolve. If zero, don't show the card at all.
$orphanLinkable = (int)$pdo->query(
    'SELECT COUNT(*) FROM purchase_orders po
     JOIN customers c ON LOWER(TRIM(c.name)) = LOWER(TRIM(po.customer_name))
     WHERE po.customer_id IS NULL'
)->fetchColumn();

$pageTitle = 'Customers';
include __DIR__ . '/includes/layout_start.php';
?>
    <?php if ($isSuperAdmin && $orphanLinkable > 0): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-amber-900">
            <span class="font-semibold"><?= $orphanLinkable ?></span>
            unlinked purchase order row<?= $orphanLinkable === 1 ? '' : 's' ?>
            match an existing customer by name. Sync to link them now — reports and lookups will start including them.
        </div>
        <form method="POST" style="margin:0;">
                <?= csrfField() ?>
            <button type="submit" name="sync_po_links" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-700 transition-colors cursor-pointer">Sync links</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3"><?= $editCustomer ? 'Edit Customer' : 'Add Customer' ?></h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
                <?= csrfField() ?>
            <?php if ($editCustomer): ?>
                <input type="hidden" name="customer_id" value="<?= $editCustomer['id'] ?>">
            <?php endif; ?>
            <input type="text" name="name" placeholder="Customer name" required value="<?= $editCustomer ? htmlspecialchars($editCustomer['name']) : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="contact_person" placeholder="Contact person" value="<?= $editCustomer ? htmlspecialchars($editCustomer['contact_person'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="phone" placeholder="Phone" value="<?= $editCustomer ? htmlspecialchars($editCustomer['phone'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="email" name="email" placeholder="Email" value="<?= $editCustomer ? htmlspecialchars($editCustomer['email'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="address" placeholder="Address" value="<?= $editCustomer ? htmlspecialchars($editCustomer['address'] ?? '') : '' ?>" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <button type="submit" name="<?= $editCustomer ? 'update_customer' : 'add_customer' ?>" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer"><?= $editCustomer ? 'Save Changes' : 'Add Customer' ?></button>
            <?php if ($editCustomer): ?>
                <a href="customers.php" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <input type="text" id="customersTableSearch" placeholder="Search customers..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                Show
                <select id="customersTablePageSize" class="px-2 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="all">All</option>
                </select>
                entries
            </label>
        </div>
        <div class="overflow-x-auto">
        <table id="customersTable" class="w-full text-sm border-collapse">
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
            <?php foreach ($customers as $c): ?>
                <tr class="border-b border-slate-100 even:bg-slate-50 hover:bg-slate-100">
                    <td class="px-3 py-2"><a href="customer_detail.php?id=<?= (int)$c['id'] ?>" class="text-brand-green hover:underline font-medium"><?= htmlspecialchars($c['name']) ?></a></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($c['contact_person'] ?? '') ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($c['email'] ?? '') ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($c['address'] ?? '') ?></td>
                    <?php if ($canEdit): ?>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <a href="?edit=<?= $c['id'] ?>" class="px-3 py-1.5 rounded-md bg-brand-dark text-white text-xs font-semibold hover:bg-slate-700 transition-colors inline-block">Edit</a>
                        <form method="POST" onsubmit="return confirm('Delete this customer?');" style="display:inline-block; margin:0;">
                <?= csrfField() ?>
                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                            <button type="submit" name="delete_customer" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-sm text-slate-600">
            <span id="customersTableInfo"></span>
            <div class="flex gap-2">
                <button type="button" id="customersTablePrev" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                <button type="button" id="customersTableNext" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
