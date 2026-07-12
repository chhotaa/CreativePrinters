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
        $check = $pdo->prepare('SELECT id FROM purchase_orders WHERE po_number = ? AND item_code = ?');
        $check->execute([$poNumber, $itemCode]);
        if ($check->fetch()) {
            $error = 'That PO number with this item code already exists.';
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
$pageTitle = 'Purchase Orders';
include __DIR__ . '/../includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Add Purchase Order</h3>
        <p class="text-sm text-slate-500 mb-3">Add the PO header once here. Add its delivery due dates (one or many) on the Delivery Schedule page.</p>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
            <input type="text" name="po_number" placeholder="PO Number (e.g. HT64023370)" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="date" name="po_date" title="PO Date" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="customer_name" placeholder="Customer name" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="item_code" placeholder="Item code" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="description" placeholder="Description" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="number" name="total_quantity" placeholder="Total quantity" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green w-40">
            <button type="submit" name="add_po" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Add Purchase Order</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5 overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">PO Number</th>
                    <th class="text-left px-3 py-2 font-semibold">PO Date</th>
                    <th class="text-left px-3 py-2 font-semibold">Customer</th>
                    <th class="text-left px-3 py-2 font-semibold">Item Code</th>
                    <th class="text-left px-3 py-2 font-semibold">Description</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md">Total Qty</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pos as $po): ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2"><?= htmlspecialchars($po['po_number']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($po['po_date']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($po['customer_name']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($po['item_code']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($po['description']) ?></td>
                    <td class="px-3 py-2"><?= $po['total_quantity'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
