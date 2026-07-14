<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_po'])) {
    $poNumber = trim($_POST['po_number'] ?? '');
    $poDate = $_POST['po_date'] ?: null;
    $customer = trim($_POST['customer_name'] ?? '');
    $itemCodes = $_POST['item_code'] ?? [];
    $descriptions = $_POST['description'] ?? [];
    $totalQuantities = $_POST['total_quantity'] ?? [];

    if ($poNumber === '' || $customer === '') {
        $error = 'PO number and customer name are required.';
    } else {
        // Build the item rows, skipping any row left completely blank
        // (e.g. an extra "+ Add another item" row nobody filled in).
        $items = [];
        $rowCount = max(count($itemCodes), count($descriptions), count($totalQuantities));
        for ($i = 0; $i < $rowCount; $i++) {
            $itemCode = trim($itemCodes[$i] ?? '');
            $description = trim($descriptions[$i] ?? '');
            $qty = isset($totalQuantities[$i]) && $totalQuantities[$i] !== '' ? (int)$totalQuantities[$i] : 0;
            if ($itemCode === '' && $description === '' && $qty === 0) {
                continue;
            }
            $items[] = ['item_code' => $itemCode, 'description' => $description, 'total_quantity' => $qty];
        }

        if (empty($items)) {
            $error = 'Add at least one item.';
        } else {
            $seenInBatch = [];
            $duplicateInBatch = null;
            foreach ($items as $item) {
                if (isset($seenInBatch[$item['item_code']])) {
                    $duplicateInBatch = $item['item_code'];
                    break;
                }
                $seenInBatch[$item['item_code']] = true;
            }

            if ($duplicateInBatch !== null) {
                $label = $duplicateInBatch === '' ? '(blank)' : $duplicateInBatch;
                $error = "Item code \"$label\" was entered more than once in this submission.";
            } else {
                $existsCheck = $pdo->prepare('SELECT id FROM purchase_orders WHERE po_number = ? AND item_code = ?');
                $conflictItem = null;
                foreach ($items as $item) {
                    $existsCheck->execute([$poNumber, $item['item_code']]);
                    if ($existsCheck->fetch()) {
                        $conflictItem = $item['item_code'] === '' ? '(blank)' : $item['item_code'];
                        break;
                    }
                }

                if ($conflictItem !== null) {
                    $error = "That PO number already has an item code \"$conflictItem\".";
                } else {
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare(
                            'INSERT INTO purchase_orders (po_number, po_date, customer_name, item_code, description, total_quantity)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        foreach ($items as $item) {
                            $stmt->execute([$poNumber, $poDate, $customer, $item['item_code'], $item['description'], $item['total_quantity']]);
                        }
                        $pdo->commit();
                        $count = count($items);
                        setFlashMessage("Purchase order saved with $count item" . ($count === 1 ? '' : 's') . '. Now add delivery due dates on the Delivery Schedule page.');
                        header('Location: purchase_orders.php');
                        exit;
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Could not save purchase order — no items were added.';
                    }
                }
            }
        }
    }
}

$pos = $pdo->query('SELECT * FROM purchase_orders ORDER BY po_date DESC, id DESC')->fetchAll();
$pageTitle = 'Purchase Orders';
include __DIR__ . '/../includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Add Purchase Order</h3>
        <p class="text-sm text-slate-500 mb-3">Enter the PO number and customer once, then add as many item rows as this PO covers in a single save.</p>
        <form method="POST" id="poForm">
            <div class="flex flex-wrap gap-2 items-center mb-4">
                <input type="text" name="po_number" placeholder="PO Number (e.g. HT64023370)" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <input type="date" name="po_date" title="PO Date" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <input type="text" name="customer_name" placeholder="Customer name" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>

            <div class="text-xs font-semibold text-slate-500 mb-2">Items</div>
            <div id="poItemRows" class="space-y-2 mb-3">
                <div class="po-item-row flex flex-wrap gap-2 items-center">
                    <input type="text" name="item_code[]" placeholder="Item code" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <input type="text" name="description[]" placeholder="Description" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <input type="number" name="total_quantity[]" placeholder="Total quantity" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green w-40">
                    <button type="button" onclick="removePoItemRow(this)" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Remove</button>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="addPoItemRow()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">+ Add another item</button>
                <button type="submit" name="add_po" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Save Purchase Order</button>
            </div>
        </form>
    </div>
    <script>
        function addPoItemRow() {
            var rows = document.getElementById('poItemRows');
            var newRow = rows.querySelector('.po-item-row').cloneNode(true);
            newRow.querySelectorAll('input').forEach(function (input) { input.value = ''; });
            rows.appendChild(newRow);
        }
        function removePoItemRow(button) {
            var rows = document.getElementById('poItemRows');
            if (rows.querySelectorAll('.po-item-row').length > 1) {
                button.closest('.po-item-row').remove();
            }
        }
    </script>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <input type="text" id="poTableSearch" placeholder="Search purchase orders..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                Show
                <select id="poTablePageSize" class="px-2 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="all">All</option>
                </select>
                entries
            </label>
        </div>
        <div class="overflow-x-auto">
        <table id="poTable" class="w-full text-sm border-collapse">
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
                <tr class="border-b border-slate-100 even:bg-slate-50 hover:bg-slate-100">
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
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-sm text-slate-600">
            <span id="poTableInfo"></span>
            <div class="flex gap-2">
                <button type="button" id="poTablePrev" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                <button type="button" id="poTableNext" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
