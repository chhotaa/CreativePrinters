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
$pageTitle = 'Restock Orders';
include __DIR__ . '/../includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Create Restock Order</h3>
        <p class="text-sm text-slate-500 mb-3">This is for buying stock for our own inventory (not a customer Purchase Order). Once created, a staff member marks it Purchased after buying it, then you confirm here to add it into Stock.</p>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
            <input type="text" name="product_name" list="stock-products" placeholder="Product name" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <datalist id="stock-products">
                <?php foreach ($existingProducts as $p): ?>
                    <option value="<?= htmlspecialchars($p['product_name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <input type="number" name="quantity" placeholder="Quantity to order" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green w-44">
            <input type="text" name="supplier_name" placeholder="Supplier name" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="notes" placeholder="Notes (optional)" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <button type="submit" name="create_restock" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Create Restock Order</button>
        </form>
    </div>

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
                    <td class="px-3 py-2 whitespace-nowrap">
                        <?php if ($r['status'] === 'Pending'): ?>
                            <form method="POST" onsubmit="return confirm('Cancel this restock order?');" style="display:inline-block; margin:0;">
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <button type="submit" name="cancel_restock" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Cancel</button>
                            </form>
                        <?php elseif ($r['status'] === 'Purchased'): ?>
                            <form method="POST" style="display:inline-block; margin:0;">
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <input type="number" name="received_quantity" value="<?= (int)$r['quantity'] ?>" min="0" class="w-20 px-2 py-1 border border-slate-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                                <button type="submit" name="confirm_restock" value="1" class="px-3 py-1.5 rounded-md bg-brand-green text-white text-xs font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Confirm</button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Reject this back to Pending?');" style="display:inline-block; margin:0;">
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <button type="submit" name="reject_restock" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Reject</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
