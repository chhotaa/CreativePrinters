<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$user = currentUser();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_purchased'])) {
    if ($user['role'] !== 'user') {
        $error = 'Only staff (user role) can mark an order as purchased.';
    } else {
        $id = (int)$_POST['restock_id'];
        $stmt = $pdo->prepare("UPDATE restock_orders SET status = 'Purchased' WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 1) {
            $message = 'Marked as purchased.';
        } else {
            $error = 'Order could not be marked purchased (already processed or not found).';
        }
    }
}

$restockOrders = $pdo->query('SELECT * FROM restock_orders ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restock Orders - Creative Printers</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="topbar">
        <h2>Creative Printers - <?= htmlspecialchars($user['username']) ?></h2>
        <div class="nav-links"><a href="dues.php">Delivery Due Dates</a><a href="../logout.php">Log Out</a></div>
    </div>

    <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <table>
            <thead>
                <tr><th>Product</th><th>Qty Ordered</th><th>Supplier</th><th>Notes</th><th>Status</th><th>Qty Received</th><th>Created</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($restockOrders as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['product_name']) ?></td>
                    <td><?= (int)$r['quantity'] ?></td>
                    <td><?= htmlspecialchars($r['supplier_name']) ?></td>
                    <td><?= htmlspecialchars($r['notes'] ?? '') ?></td>
                    <td class="status-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></td>
                    <td><?= $r['received_quantity'] !== null ? (int)$r['received_quantity'] : '-' ?></td>
                    <td><?= htmlspecialchars($r['created_at']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'Pending' && $user['role'] === 'user'): ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <button type="submit" name="mark_purchased" value="1">Mark Purchased</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
