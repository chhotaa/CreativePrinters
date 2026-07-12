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
    <?php include __DIR__ . '/../includes/tailwind_head.php'; ?>
</head>
<body class="bg-slate-50 text-slate-800 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h2 class="text-2xl font-bold text-brand-dark">Delivery Schedule</h2>
        <div class="flex flex-wrap items-center gap-1">
            <a href="index.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Dashboard</a>
            <a href="purchase_orders.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Purchase Orders</a>
            <a href="restock_orders.php" class="px-3 py-1.5 rounded-md text-sm font-semibold text-brand-dark hover:bg-brand-dark hover:text-white transition-colors">Restock Orders</a>
            <a href="../logout.php" class="ml-2 px-3 py-1.5 rounded-md text-sm font-semibold bg-brand-green text-white hover:bg-brand-greendark transition-colors">Log Out</a>
        </div>
    </div>

    <?php if ($message): ?><div class="text-green-700 text-sm bg-green-50 border border-green-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="text-red-600 text-sm bg-red-50 border border-red-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Add Delivery Due Date</h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
            <select name="po_id" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green min-w-[220px]">
                <option value="">Select PO Number</option>
                <?php foreach ($pos as $po): ?>
                    <option value="<?= $po['id'] ?>"><?= htmlspecialchars($po['po_number']) ?> - <?= htmlspecialchars($po['item_code']) ?> - <?= htmlspecialchars($po['customer_name']) ?> (<?= (int)$po['remaining_quantity'] ?> of <?= (int)$po['total_quantity'] ?> remaining)</option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="due_date" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="number" name="quantity" placeholder="Quantity for this date" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green w-48">
            <button type="submit" name="add_delivery" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Add Delivery Date</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5 overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">PO Number</th>
                    <th class="text-left px-3 py-2 font-semibold">Customer</th>
                    <th class="text-left px-3 py-2 font-semibold">Item</th>
                    <th class="text-left px-3 py-2 font-semibold">Due Date</th>
                    <th class="text-left px-3 py-2 font-semibold">Qty</th>
                    <th class="text-left px-3 py-2 font-semibold">Status</th>
                    <th class="text-left px-3 py-2 font-semibold">Reminder Sent</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md"></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $deliveryStatusBadge = [
                'Pending' => 'bg-amber-100 text-amber-800',
                'Shipped' => 'bg-blue-100 text-blue-800',
                'Delivered' => 'bg-green-100 text-green-800',
            ];
            foreach ($deliveries as $d):
                $due = new DateTime($d['due_date']);
                $today = new DateTime('today');
                $diff = (int)$today->diff($due)->format('%r%a');
                $rowClass = '';
                if ($d['status'] !== 'Delivered' && $diff < 0) $rowClass = 'bg-red-50';
                elseif ($d['status'] !== 'Delivered' && $diff <= 3) $rowClass = 'bg-amber-50';
                $badgeClass = $deliveryStatusBadge[$d['status']] ?? 'bg-slate-100 text-slate-700';
            ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50 <?= $rowClass ?>">
                    <td class="px-3 py-2"><?= htmlspecialchars($d['po_number']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($d['customer_name']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($d['item_code']) ?> - <?= htmlspecialchars($d['description']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($d['due_date']) ?></td>
                    <td class="px-3 py-2"><?= $d['quantity'] ?></td>
                    <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeClass ?>"><?= htmlspecialchars($d['status']) ?></span></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($d['reminder_sent']) ?></td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <form method="POST" style="display:inline-block; margin:0;">
                            <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                            <select name="status" onchange="this.form.submit()" class="px-2 py-1 border border-slate-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                                <option value="Pending" <?= $d['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Shipped" <?= $d['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="Delivered" <?= $d['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                            </select>
                            <input type="hidden" name="update_status" value="1">
                        </form>
                        <form method="POST" onsubmit="return confirm('Delete this delivery date?');" style="display:inline-block; margin:0;">
                            <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                            <button type="submit" name="delete_delivery" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
