<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$user = currentUser();

$deliveries = $pdo->query(
    "SELECT d.*, po.po_number, po.customer_name, po.item_code, po.description
     FROM deliveries d
     JOIN purchase_orders po ON po.id = d.po_id
     ORDER BY d.due_date ASC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Due Dates - Creative Printers</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="topbar">
        <h2>Creative Printers - <?= htmlspecialchars($user['username']) ?></h2>
        <div class="nav-links"><a href="restock_orders.php">Restock Orders</a><a href="../logout.php">Log Out</a></div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr><th>PO Number</th><th>Customer</th><th>Item</th><th>Due Date</th><th>Qty</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($deliveries as $d):
                $due = new DateTime($d['due_date']);
                $today = new DateTime('today');
                $diff = (int)$today->diff($due)->format('%r%a');
                $rowClass = '';
                if ($d['status'] !== 'Delivered' && $diff < 0) $rowClass = 'overdue';
                elseif ($d['status'] !== 'Delivered' && $diff <= 3) $rowClass = 'due-soon';
            ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= htmlspecialchars($d['po_number']) ?></td>
                    <td><?= htmlspecialchars($d['customer_name']) ?></td>
                    <td><?= htmlspecialchars($d['item_code']) ?> - <?= htmlspecialchars($d['description']) ?></td>
                    <td><?= htmlspecialchars($d['due_date']) ?></td>
                    <td><?= $d['quantity'] ?></td>
                    <td class="status-<?= $d['status'] ?>"><?= $d['status'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
