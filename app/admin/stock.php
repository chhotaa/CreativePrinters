<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_stock'])) {
        $product = trim($_POST['product_name']);
        $qty = (int)$_POST['quantity'];
        $reorder = (int)$_POST['reorder_level'];

        if ($product === '') {
            $error = 'Product name is required.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO stock (product_name, quantity, reorder_level) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), reorder_level = VALUES(reorder_level)'
            );
            $stmt->execute([$product, $qty, $reorder]);
            $message = 'Stock saved.';
        }
    } elseif (isset($_POST['delete_stock'])) {
        $id = (int)$_POST['stock_id'];
        $stmt = $pdo->prepare('DELETE FROM stock WHERE id = ?');
        $stmt->execute([$id]);
        $message = 'Stock item deleted.';
    }
}

$stockItems = $pdo->query('SELECT * FROM stock ORDER BY product_name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stock - Creative Printers</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="topbar">
        <h2>Stock</h2>
        <div class="nav-links"><a href="index.php">Dashboard</a><a href="restock_orders.php">Restock Orders</a><a href="../logout.php">Log Out</a></div>
    </div>

    <?php if ($message): ?><div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <h3>Add / Update Stock</h3>
        <form method="POST">
            <input type="text" name="product_name" placeholder="Product name" required>
            <input type="number" name="quantity" placeholder="Quantity" required>
            <input type="number" name="reorder_level" placeholder="Reorder level" value="0">
            <button type="submit" name="save_stock" value="1">Save</button>
        </form>
        <p style="font-size:13px;color:#666;">Entering an existing product name updates its quantity/reorder level instead of duplicating it.</p>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Product</th><th>Quantity</th><th>Reorder Level</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($stockItems as $s): ?>
                <tr <?= $s['quantity'] <= $s['reorder_level'] ? 'class="overdue"' : '' ?>>
                    <td><?= htmlspecialchars($s['product_name']) ?></td>
                    <td><?= $s['quantity'] ?></td>
                    <td><?= $s['reorder_level'] ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Delete this stock item?');" style="margin:0;">
                            <input type="hidden" name="stock_id" value="<?= $s['id'] ?>">
                            <button type="submit" name="delete_stock" value="1" class="btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
