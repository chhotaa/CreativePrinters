<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_po'])) {
    $poNumber = trim($_POST['po_number']);
    $poDate = $_POST['po_date'] ?: null;
    $customer = trim($_POST['customer_name']);
    $itemCode = trim($_POST['item_code']);
    $description = trim($_POST['description']);
    $totalQty = (int)$_POST['total_quantity'];

    if ($poNumber === '' || $customer === '') {
        $error = 'PO number and customer name are required.';
    } else {
        $check = $pdo->prepare('SELECT id FROM purchase_orders WHERE po_number = ?');
        $check->execute([$poNumber]);
        if ($check->fetch()) {
            $error = 'That PO number already exists.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO purchase_orders (po_number, po_date, customer_name, item_code, description, total_quantity)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$poNumber, $poDate, $customer, $itemCode, $description, $totalQty]);
            $message = 'Purchase order added. Now add its delivery due dates on the Delivery Schedule page.';
        }
    }
}

$pos = $pdo->query('SELECT * FROM purchase_orders ORDER BY po_date DESC, id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders - Creative Printers</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="topbar">
        <h2>Purchase Orders</h2>
        <div class="nav-links"><a href="index.php">Dashboard</a><a href="deliveries.php">Delivery Schedule</a><a href="../logout.php">Log Out</a></div>
    </div>

    <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <h3>Add Purchase Order</h3>
        <p style="font-size:13px;color:#666;">Add the PO header once here. Add its delivery due dates (one or many) on the Delivery Schedule page.</p>
        <form method="POST">
            <input type="text" name="po_number" placeholder="PO Number (e.g. HT64023370)" required>
            <input type="date" name="po_date" title="PO Date">
            <input type="text" name="customer_name" placeholder="Customer name" required>
            <input type="text" name="item_code" placeholder="Item code">
            <input type="text" name="description" placeholder="Description">
            <input type="number" name="total_quantity" placeholder="Total quantity">
            <button type="submit" name="add_po" value="1">Add Purchase Order</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>PO Number</th><th>PO Date</th><th>Customer</th><th>Item Code</th><th>Description</th><th>Total Qty</th></tr></thead>
            <tbody>
            <?php foreach ($pos as $po): ?>
                <tr>
                    <td><?= htmlspecialchars($po['po_number']) ?></td>
                    <td><?= htmlspecialchars($po['po_date']) ?></td>
                    <td><?= htmlspecialchars($po['customer_name']) ?></td>
                    <td><?= htmlspecialchars($po['item_code']) ?></td>
                    <td><?= htmlspecialchars($po['description']) ?></td>
                    <td><?= $po['total_quantity'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
