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
    <?php include __DIR__ . '/../includes/tailwind_head.php'; ?>
</head>
<body class="bg-slate-50 text-slate-800 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h2 class="text-2xl font-bold text-brand-dark">Creative Printers - <?= htmlspecialchars($user['username']) ?></h2>
        <div class="flex flex-wrap items-center gap-1">
            <a href="dues.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Delivery Due Dates</a>
            <a href="../logout.php" class="ml-2 px-3 py-1.5 rounded-md text-sm font-semibold bg-brand-green text-white hover:bg-brand-greendark transition-colors">Log Out</a>
        </div>
    </div>

    <?php if ($message): ?><div class="text-green-700 text-sm bg-green-50 border border-green-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="text-red-600 text-sm bg-red-50 border border-red-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5 overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Product</th>
                    <th class="text-left px-3 py-2 font-semibold">Qty Ordered</th>
                    <th class="text-left px-3 py-2 font-semibold">Supplier</th>
                    <th class="text-left px-3 py-2 font-semibold">Notes</th>
                    <th class="text-left px-3 py-2 font-semibold">Status</th>
                    <th class="text-left px-3 py-2 font-semibold">Qty Received</th>
                    <th class="text-left px-3 py-2 font-semibold">Created</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md"></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $restockStatusBadge = [
                'Pending' => 'bg-amber-100 text-amber-800',
                'Purchased' => 'bg-blue-100 text-blue-800',
                'Confirmed' => 'bg-green-100 text-green-800',
                'Cancelled' => 'bg-slate-200 text-slate-600',
            ];
            foreach ($restockOrders as $r):
                $badgeClass = $restockStatusBadge[$r['status']] ?? 'bg-slate-100 text-slate-700';
            ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2"><?= htmlspecialchars($r['product_name']) ?></td>
                    <td class="px-3 py-2"><?= (int)$r['quantity'] ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($r['supplier_name']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($r['notes'] ?? '') ?></td>
                    <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeClass ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    <td class="px-3 py-2"><?= $r['received_quantity'] !== null ? (int)$r['received_quantity'] : '-' ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($r['created_at']) ?></td>
                    <td class="px-3 py-2">
                        <?php if ($r['status'] === 'Pending' && $user['role'] === 'user'): ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <button type="submit" name="mark_purchased" value="1" class="px-3 py-1.5 rounded-md bg-brand-green text-white text-xs font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Mark Purchased</button>
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
