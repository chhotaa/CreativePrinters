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
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="topbar">
        <h2>Creative Printers - Welcome, <?= htmlspecialchars($user['username']) ?></h2>
        <a class="btn" href="../logout.php">Log Out</a>
    </div>

    <div class="nav-links card">
        <a href="users.php">Users</a>
        <a href="stock.php">Stock</a>
        <a href="purchase_orders.php">Purchase Orders</a>
        <a href="deliveries.php">Delivery Schedule</a>
        <a href="restock_orders.php">Restock Orders</a>
    </div>

    <div class="card">
        <h3>Quick Overview</h3>
        <p>Products tracked: <b><?= $stockCount ?></b></p>
        <p>Purchase orders: <b><?= $poCount ?></b></p>
        <p>Deliveries due within 3 days (or overdue): <b><?= $dueSoonCount ?></b></p>
    </div>
</body>
</html>
