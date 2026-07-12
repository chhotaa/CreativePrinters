<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$user = currentUser();

$stockCount = $pdo->query('SELECT COUNT(*) c FROM stock')->fetch()['c'];
$poCount = $pdo->query('SELECT COUNT(*) c FROM purchase_orders')->fetch()['c'];
$dueSoonCount = $pdo->query("SELECT COUNT(*) c FROM deliveries WHERE status != 'Delivered' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)")->fetch()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Creative Printers</title>
    <?php include __DIR__ . '/../includes/tailwind_head.php'; ?>
</head>
<body class="bg-slate-50 text-slate-800 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h2 class="text-2xl font-bold text-brand-dark">Creative Printers - Welcome, <?= htmlspecialchars($user['username']) ?></h2>
        <a href="../logout.php" class="px-3 py-1.5 rounded-md text-sm font-semibold bg-brand-green text-white hover:bg-brand-greendark transition-colors">Log Out</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5 flex flex-wrap gap-1">
        <a href="users.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Users</a>
        <a href="stock.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Stock</a>
        <a href="purchase_orders.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Purchase Orders</a>
        <a href="deliveries.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Delivery Schedule</a>
        <a href="restock_orders.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Restock Orders</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Quick Overview</h3>
        <p class="text-sm text-slate-600 py-1">Products tracked: <b class="text-slate-900"><?= $stockCount ?></b></p>
        <p class="text-sm text-slate-600 py-1">Purchase orders: <b class="text-slate-900"><?= $poCount ?></b></p>
        <p class="text-sm text-slate-600 py-1">Deliveries due within 3 days (or overdue): <b class="text-slate-900"><?= $dueSoonCount ?></b></p>
    </div>
</body>
</html>
