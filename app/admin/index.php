<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$user = currentUser();

$stockCount = $pdo->query('SELECT COUNT(*) c FROM stock')->fetch()['c'];
$poCount = $pdo->query('SELECT COUNT(*) c FROM purchase_orders')->fetch()['c'];
$dueSoonCount = $pdo->query("SELECT COUNT(*) c FROM deliveries WHERE status != 'Delivered' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)")->fetch()['c'];
$pageTitle = 'Dashboard';
$pageHeading = 'Welcome, ' . $user['username'];
include __DIR__ . '/../includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Quick Overview</h3>
        <p class="text-sm text-slate-600 py-1">Products tracked: <b class="text-slate-900"><?= $stockCount ?></b></p>
        <p class="text-sm text-slate-600 py-1">Purchase orders: <b class="text-slate-900"><?= $poCount ?></b></p>
        <p class="text-sm text-slate-600 py-1">Deliveries due within 3 days (or overdue): <b class="text-slate-900"><?= $dueSoonCount ?></b></p>
    </div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
