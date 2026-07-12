<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_delivery'])) {
        $poId = (int)$_POST['po_id'];
        $dueDate = $_POST['due_date'];
        $qty = (int)$_POST['quantity'];

        if (!$poId || !$dueDate || $qty <= 0) {
            $error = 'PO, due date, and a positive quantity are required.';
        } else {
            $poStmt = $pdo->prepare('SELECT total_quantity FROM purchase_orders WHERE id = ?');
            $poStmt->execute([$poId]);
            $po = $poStmt->fetch();

            $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) AS total FROM deliveries WHERE po_id = ?');
            $sumStmt->execute([$poId]);
            $alreadyScheduled = (int)$sumStmt->fetch()['total'];

            if (!$po) {
                $error = 'Selected PO not found.';
            } elseif ($alreadyScheduled + $qty > (int)$po['total_quantity']) {
                $remaining = (int)$po['total_quantity'] - $alreadyScheduled;
                $error = "Cannot schedule $qty units — only $remaining of {$po['total_quantity']} remain unscheduled for this PO.";
            } else {
                $stmt = $pdo->prepare('INSERT INTO deliveries (po_id, due_date, quantity) VALUES (?, ?, ?)');
                $stmt->execute([$poId, $dueDate, $qty]);
                $message = 'Delivery date added.';
            }
        }
    } elseif (isset($_POST['update_status'])) {
        $id = (int)$_POST['delivery_id'];
        $status = $_POST['status'];
        $allowedStatuses = ['Pending', 'Shipped', 'Delivered'];
        if (!in_array($status, $allowedStatuses, true)) {
            $error = 'Invalid status value.';
        } else {
            $stmt = $pdo->prepare('UPDATE deliveries SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
            $message = 'Status updated.';
        }
    } elseif (isset($_POST['delete_delivery'])) {
        $id = (int)$_POST['delivery_id'];
        $stmt = $pdo->prepare('DELETE FROM deliveries WHERE id = ?');
        $stmt->execute([$id]);
        $message = 'Delivery entry deleted.';
    }
}

$pos = $pdo->query(
    "SELECT po.id, po.po_number, po.customer_name, po.item_code, po.total_quantity,
            po.total_quantity - COALESCE(SUM(d.quantity), 0) AS remaining_quantity
     FROM purchase_orders po
     LEFT JOIN deliveries d ON d.po_id = po.id
     GROUP BY po.id, po.po_number, po.customer_name, po.item_code, po.total_quantity
     ORDER BY po.po_number"
)->fetchAll();

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
    <title>Delivery Schedule - Creative Printers</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="topbar">
        <h2>Delivery Schedule</h2>
        <div class="nav-links"><a href="index.php">Dashboard</a><a href="purchase_orders.php">Purchase Orders</a><a href="../logout.php">Log Out</a></div>
    </div>

    <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <h3>Add Delivery Due Date</h3>
        <form method="POST">
            <select name="po_id" required>
                <option value="">Select PO Number</option>
                <?php foreach ($pos as $po): ?>
                    <option value="<?= $po['id'] ?>"><?= htmlspecialchars($po['po_number']) ?> - <?= htmlspecialchars($po['item_code']) ?> - <?= htmlspecialchars($po['customer_name']) ?> (<?= (int)$po['remaining_quantity'] ?> of <?= (int)$po['total_quantity'] ?> remaining)</option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="due_date" required>
            <input type="number" name="quantity" placeholder="Quantity for this date" required>
            <button type="submit" name="add_delivery" value="1">Add Delivery Date</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr><th>PO Number</th><th>Customer</th><th>Item</th><th>Due Date</th><th>Qty</th><th>Status</th><th>Reminder Sent</th><th></th></tr>
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
                    <td><?= $d['reminder_sent'] ?></td>
                    <td>
                        <form method="POST" style="display:inline-block; margin:0;">
                            <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                            <select name="status" onchange="this.form.submit()">
                                <option value="Pending" <?= $d['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Shipped" <?= $d['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="Delivered" <?= $d['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                            </select>
                            <input type="hidden" name="update_status" value="1">
                        </form>
                        <form method="POST" onsubmit="return confirm('Delete this delivery date?');" style="display:inline-block; margin:0;">
                            <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                            <button type="submit" name="delete_delivery" value="1" class="btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
