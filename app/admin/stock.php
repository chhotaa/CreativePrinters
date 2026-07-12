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
    <?php include __DIR__ . '/../includes/tailwind_head.php'; ?>
</head>
<body class="bg-slate-50 text-slate-800 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h2 class="text-2xl font-bold text-brand-dark">Stock</h2>
        <div class="flex flex-wrap items-center gap-1">
            <a href="index.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Dashboard</a>
            <a href="restock_orders.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Restock Orders</a>
            <a href="../logout.php" class="ml-2 px-3 py-1.5 rounded-md text-sm font-semibold bg-brand-green text-white hover:bg-brand-greendark transition-colors">Log Out</a>
        </div>
    </div>

    <?php if ($message): ?><div class="text-green-700 text-sm bg-green-50 border border-green-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="text-red-600 text-sm bg-red-50 border border-red-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Add / Update Stock</h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
            <input type="text" name="product_name" placeholder="Product name" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="number" name="quantity" placeholder="Quantity" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green w-36">
            <input type="number" name="reorder_level" placeholder="Reorder level" value="0" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green w-40">
            <button type="submit" name="save_stock" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Save</button>
        </form>
        <p class="text-sm text-slate-500 mt-2">Entering an existing product name updates its quantity/reorder level instead of duplicating it.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5 overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Product</th>
                    <th class="text-left px-3 py-2 font-semibold">Quantity</th>
                    <th class="text-left px-3 py-2 font-semibold">Reorder Level</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stockItems as $s): ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50 <?= $s['quantity'] <= $s['reorder_level'] ? 'bg-red-50' : '' ?>">
                    <td class="px-3 py-2"><?= htmlspecialchars($s['product_name']) ?></td>
                    <td class="px-3 py-2"><?= $s['quantity'] ?></td>
                    <td class="px-3 py-2"><?= $s['reorder_level'] ?></td>
                    <td class="px-3 py-2">
                        <form method="POST" onsubmit="return confirm('Delete this stock item?');" style="margin:0;">
                            <input type="hidden" name="stock_id" value="<?= $s['id'] ?>">
                            <button type="submit" name="delete_stock" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
