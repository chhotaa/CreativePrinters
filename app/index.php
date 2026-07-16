<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$user = currentUser();

$canViewStock = hasPermission('stock', 'view');
$canViewPOs = hasPermission('purchase_orders', 'view');
$canViewDeliveries = hasPermission('deliveries', 'view');
$canViewRestock = hasPermission('restock_orders', 'view');

$stockCount = $canViewStock ? $pdo->query('SELECT COUNT(*) c FROM stock')->fetch()['c'] : 0;
$poCount = $canViewPOs ? $pdo->query('SELECT COUNT(*) c FROM purchase_orders')->fetch()['c'] : 0;
$dueSoonCount = $canViewDeliveries ? $pdo->query(
    "SELECT COUNT(*) c FROM deliveries WHERE status != 'Delivered' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)"
)->fetch()['c'] : 0;
$overdueCount = $canViewDeliveries ? $pdo->query(
    "SELECT COUNT(*) c FROM deliveries WHERE status != 'Delivered' AND due_date < CURDATE()"
)->fetch()['c'] : 0;
$pendingRestockCount = $canViewRestock ? $pdo->query(
    "SELECT COUNT(*) c FROM restock_orders WHERE status IN ('Pending', 'Purchased')"
)->fetch()['c'] : 0;

$lowStockItems = $canViewStock ? $pdo->query(
    'SELECT product_name, quantity, reorder_level FROM stock WHERE quantity <= reorder_level ORDER BY (reorder_level - quantity) DESC LIMIT 5'
)->fetchAll() : [];
$lowStockCount = $canViewStock ? $pdo->query('SELECT COUNT(*) c FROM stock WHERE quantity <= reorder_level')->fetch()['c'] : 0;

$overdueDeliveries = $canViewDeliveries ? $pdo->query(
    "SELECT d.due_date, d.quantity, po.po_number, po.customer_name,
            DATEDIFF(CURDATE(), d.due_date) AS days_overdue
     FROM deliveries d
     JOIN purchase_orders po ON po.id = d.po_id
     WHERE d.status != 'Delivered' AND d.due_date < CURDATE()
     ORDER BY d.due_date ASC LIMIT 5"
)->fetchAll() : [];

$pageTitle = 'Dashboard';
$pageHeading = 'Welcome, ' . $user['username'];
include __DIR__ . '/includes/layout_start.php';
?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-5">
        <?php if ($canViewStock): ?>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
            <p class="text-sm text-slate-500">Products tracked</p>
            <p class="text-3xl font-semibold text-slate-900 mt-1"><?= $stockCount ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
            <p class="text-sm text-slate-500">Low stock items</p>
            <p class="text-3xl font-semibold text-slate-900 mt-1"><?= $lowStockCount ?></p>
            <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-xs font-semibold <?= $lowStockCount > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' ?>">
                <?= $lowStockCount > 0 ? 'Needs reorder' : 'All stocked' ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if ($canViewPOs): ?>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
            <p class="text-sm text-slate-500">Purchase orders</p>
            <p class="text-3xl font-semibold text-slate-900 mt-1"><?= $poCount ?></p>
        </div>
        <?php endif; ?>
        <?php if ($canViewDeliveries): ?>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
            <p class="text-sm text-slate-500">Deliveries due within 3 days</p>
            <p class="text-3xl font-semibold text-slate-900 mt-1"><?= $dueSoonCount ?></p>
            <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-xs font-semibold <?= $dueSoonCount > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' ?>">
                <?= $dueSoonCount > 0 ? 'Coming up' : 'Nothing due' ?>
            </span>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
            <p class="text-sm text-slate-500">Overdue deliveries</p>
            <p class="text-3xl font-semibold text-slate-900 mt-1"><?= $overdueCount ?></p>
            <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-xs font-semibold <?= $overdueCount > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                <?= $overdueCount > 0 ? 'Overdue' : 'On track' ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if ($canViewRestock): ?>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
            <p class="text-sm text-slate-500">Restock orders pending</p>
            <p class="text-3xl font-semibold text-slate-900 mt-1"><?= $pendingRestockCount ?></p>
            <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-xs font-semibold <?= $pendingRestockCount > 0 ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                <?= $pendingRestockCount > 0 ? 'Awaiting action' : 'All clear' ?>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <?php if (($canViewStock && $lowStockCount > 0) || ($canViewDeliveries && $overdueCount > 0)): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <?php if ($canViewStock && $lowStockCount > 0): ?>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-brand-dark">Low stock</h3>
                <a href="stock.php" class="text-sm font-semibold text-brand-green hover:text-brand-greendark">View all &rarr;</a>
            </div>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($lowStockItems as $item): ?>
                    <li class="py-2 flex items-center justify-between text-sm">
                        <span class="text-slate-700"><?= htmlspecialchars($item['product_name']) ?></span>
                        <span class="text-slate-500"><?= (int)$item['quantity'] ?> / <?= (int)$item['reorder_level'] ?> reorder level</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($canViewDeliveries && $overdueCount > 0): ?>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-brand-dark">Overdue deliveries</h3>
                <a href="deliveries.php" class="text-sm font-semibold text-brand-green hover:text-brand-greendark">View all &rarr;</a>
            </div>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($overdueDeliveries as $d): ?>
                    <li class="py-2 flex items-center justify-between text-sm">
                        <span class="text-slate-700"><?= htmlspecialchars($d['po_number']) ?> - <?= htmlspecialchars($d['customer_name']) ?></span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800"><?= (int)$d['days_overdue'] ?> day<?= (int)$d['days_overdue'] === 1 ? '' : 's' ?> overdue</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 text-center">
        <p class="text-sm text-slate-500">Nothing needs attention right now &mdash; stock levels are healthy and no deliveries are overdue.</p>
    </div>
    <?php endif; ?>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
