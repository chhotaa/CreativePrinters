<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_restock'])) {
        $product = trim($_POST['product_name']);
        $qty = (int)$_POST['quantity'];
        $supplier = trim($_POST['supplier_name']);
        $notes = trim($_POST['notes'] ?? '');

        if ($product === '' || $supplier === '' || $qty <= 0) {
            $error = 'Product name, supplier, and a positive quantity are required.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO restock_orders (product_name, quantity, supplier_name, notes) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$product, $qty, $supplier, $notes !== '' ? $notes : null]);
            $message = 'Restock order created.';
        }
    } elseif (isset($_POST['cancel_restock'])) {
        $id = (int)$_POST['restock_id'];
        $stmt = $pdo->prepare("UPDATE restock_orders SET status = 'Cancelled' WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 1) {
            $message = 'Restock order cancelled.';
        } else {
            $error = 'Order could not be cancelled (already processed or not found).';
        }
    } elseif (isset($_POST['reject_restock'])) {
        $id = (int)$_POST['restock_id'];
        $stmt = $pdo->prepare("UPDATE restock_orders SET status = 'Pending' WHERE id = ? AND status = 'Purchased'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 1) {
            $message = 'Order rejected back to Pending.';
        } else {
            $error = 'Order could not be rejected (not currently awaiting confirmation).';
        }
    } elseif (isset($_POST['confirm_restock'])) {
        $id = (int)$_POST['restock_id'];
        $receivedQty = isset($_POST['received_quantity']) ? (int)$_POST['received_quantity'] : -1;

        if ($receivedQty < 0) {
            $error = 'Received quantity must be zero or a positive number.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT product_name, status FROM restock_orders WHERE id = ? FOR UPDATE');
                $stmt->execute([$id]);
                $order = $stmt->fetch();

                if (!$order) {
                    throw new RuntimeException('Restock order not found.');
                }
                if ($order['status'] !== 'Purchased') {
                    throw new RuntimeException('Order is not awaiting confirmation.');
                }

                $upd = $pdo->prepare(
                    "UPDATE restock_orders SET status = 'Confirmed', received_quantity = ? WHERE id = ? AND status = 'Purchased'"
                );
                $upd->execute([$receivedQty, $id]);
                if ($upd->rowCount() !== 1) {
                    throw new RuntimeException('Order status changed before it could be confirmed.');
                }

                $stockStmt = $pdo->prepare(
                    'INSERT INTO stock (product_name, quantity, reorder_level) VALUES (?, ?, 0)
                     ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
                );
                $stockStmt->execute([$order['product_name'], $receivedQty]);

                $pdo->commit();
                $message = 'Order confirmed and stock updated.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

$existingProducts = $pdo->query('SELECT DISTINCT product_name FROM stock ORDER BY product_name')->fetchAll();
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
        <h2>Restock Orders</h2>
        <div class="nav-links"><a href="index.php">Dashboard</a><a href="stock.php">Stock</a><a href="../logout.php">Log Out</a></div>
    </div>

    <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <h3>Create Restock Order</h3>
        <p style="font-size:13px;color:#666;">This is for buying stock for our own inventory (not a customer Purchase Order). Once created, a staff member marks it Purchased after buying it, then you confirm here to add it into Stock.</p>
        <form method="POST">
            <input type="text" name="product_name" list="stock-products" placeholder="Product name" required>
            <datalist id="stock-products">
                <?php foreach ($existingProducts as $p): ?>
                    <option value="<?= htmlspecialchars($p['product_name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <input type="number" name="quantity" placeholder="Quantity to order" required>
            <input type="text" name="supplier_name" placeholder="Supplier name" required>
            <input type="text" name="notes" placeholder="Notes (optional)">
            <button type="submit" name="create_restock" value="1">Create Restock Order</button>
        </form>
    </div>

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
                        <?php if ($r['status'] === 'Pending'): ?>
                            <form method="POST" onsubmit="return confirm('Cancel this restock order?');" style="display:inline-block; margin:0;">
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <button type="submit" name="cancel_restock" value="1" class="btn-danger">Cancel</button>
                            </form>
                        <?php elseif ($r['status'] === 'Purchased'): ?>
                            <form method="POST" style="display:inline-block; margin:0;">
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <input type="number" name="received_quantity" value="<?= (int)$r['quantity'] ?>" min="0" style="width:90px;display:inline-block;">
                                <button type="submit" name="confirm_restock" value="1">Confirm</button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Reject this back to Pending?');" style="display:inline-block; margin:0;">
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <button type="submit" name="reject_restock" value="1" class="btn-danger">Reject</button>
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
