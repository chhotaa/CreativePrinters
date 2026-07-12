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
$pageTitle = 'Restock Orders';
include __DIR__ . '/../includes/layout_start.php';
?>
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
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
