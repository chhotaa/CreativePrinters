<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/activity_log.php';
require_once __DIR__ . '/includes/stock_movements.php';
requirePermission('stock', 'view');
$canEdit = hasPermission('stock', 'edit');

$message = '';
$error = '';

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['save_stock'])) {
        $product = trim($_POST['product_name']);
        $qty = (int)$_POST['quantity'];
        $reorder = (int)$_POST['reorder_level'];
        $reasonText = trim($_POST['reason'] ?? '');

        if ($product === '') {
            $error = 'Product name is required.';
        } else {
            $pdo->beginTransaction();
            try {
                // Snapshot the previous quantity (if any) so we can log the delta.
                $prev = $pdo->prepare('SELECT id, quantity FROM stock WHERE product_name = ?');
                $prev->execute([$product]);
                $prevRow = $prev->fetch();
                $prevQty = $prevRow ? (int)$prevRow['quantity'] : 0;

                $stmt = $pdo->prepare(
                    'INSERT INTO stock (product_name, quantity, reorder_level) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), reorder_level = VALUES(reorder_level)'
                );
                $stmt->execute([$product, $qty, $reorder]);

                // Look the row up again to get id (whether newly inserted or updated).
                $after = $pdo->prepare('SELECT id FROM stock WHERE product_name = ?');
                $after->execute([$product]);
                $stockId = (int)$after->fetchColumn();

                $delta = $qty - $prevQty;
                if ($delta !== 0) {
                    recordStockMovement(
                        $pdo, $stockId, $product, $delta, $qty,
                        STOCK_MOVEMENT_MANUAL_SAVE, $reasonText
                    );
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Could not save stock.';
            }
            if ($error === '') {
                setFlashMessage('Stock saved.');
                logActivity('save_stock', "Saved stock for \"$product\" (qty: $qty, reorder level: $reorder).");
                header('Location: stock.php');
                exit;
            }
        }
    } elseif (isset($_POST['delete_stock'])) {
        $id = (int)$_POST['stock_id'];
        $pdo->beginTransaction();
        try {
            $row = $pdo->prepare('SELECT product_name, quantity FROM stock WHERE id = ?');
            $row->execute([$id]);
            $rowData = $row->fetch();
            if ($rowData) {
                $productName = $rowData['product_name'];
                $prevQty = (int)$rowData['quantity'];
                // Log the drawdown BEFORE the DELETE so stock_id is still valid;
                // the FK is ON DELETE SET NULL so the history survives either way.
                if ($prevQty !== 0) {
                    recordStockMovement(
                        $pdo, $id, $productName, -$prevQty, 0,
                        STOCK_MOVEMENT_STOCK_DELETED
                    );
                }
                $del = $pdo->prepare('DELETE FROM stock WHERE id = ?');
                $del->execute([$id]);
                $pdo->commit();
                setFlashMessage('Stock item deleted.');
                logActivity('delete_stock', "Deleted stock item \"$productName\".");
            } else {
                $pdo->commit();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Could not delete stock item.';
        }
        if ($error === '') {
            header('Location: stock.php');
            exit;
        }
    }
}

$stockItems = $pdo->query('SELECT * FROM stock ORDER BY product_name')->fetchAll();
$pageTitle = 'Stock';
include __DIR__ . '/includes/layout_start.php';
?>
    <?php if ($canEdit): ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Add / Update Stock</h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
                <?= csrfField() ?>
            <input type="text" name="product_name" placeholder="Product name" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="number" name="quantity" placeholder="Quantity" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green w-36">
            <input type="number" name="reorder_level" placeholder="Reorder level" value="0" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green w-40">
            <input type="text" name="reason" placeholder="Reason (optional, e.g. damaged, recount)" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green flex-1 min-w-60">
            <button type="submit" name="save_stock" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Save</button>
        </form>
        <p class="text-sm text-slate-500 mt-2">Entering an existing product name updates its quantity/reorder level instead of duplicating it. The Reason field is saved to the movement history.</p>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <input type="text" id="stockTableSearch" placeholder="Search stock..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                Show
                <select id="stockTablePageSize" class="px-2 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="all">All</option>
                </select>
                entries
            </label>
        </div>
        <div class="overflow-x-auto">
        <table id="stockTable" class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Product</th>
                    <th class="text-left px-3 py-2 font-semibold">Quantity</th>
                    <th class="text-left px-3 py-2 font-semibold">Reorder Level</th>
                    <th class="text-left px-3 py-2 font-semibold <?= $canEdit ? '' : 'rounded-tr-md' ?>">History</th>
                    <?php if ($canEdit): ?><th class="text-left px-3 py-2 font-semibold rounded-tr-md"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stockItems as $s): ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50 <?= $s['quantity'] <= $s['reorder_level'] ? 'bg-red-50' : '' ?>">
                    <td class="px-3 py-2"><?= htmlspecialchars($s['product_name']) ?></td>
                    <td class="px-3 py-2"><?= $s['quantity'] ?></td>
                    <td class="px-3 py-2"><?= $s['reorder_level'] ?></td>
                    <td class="px-3 py-2">
                        <a href="stock_history.php?id=<?= (int)$s['id'] ?>" class="text-brand-green hover:underline font-medium text-xs">View history</a>
                    </td>
                    <?php if ($canEdit): ?>
                    <td class="px-3 py-2">
                        <form method="POST" onsubmit="return confirm('Delete this stock item?');" style="margin:0;">
                <?= csrfField() ?>
                            <input type="hidden" name="stock_id" value="<?= $s['id'] ?>">
                            <button type="submit" name="delete_stock" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-sm text-slate-600">
            <span id="stockTableInfo"></span>
            <div class="flex gap-2">
                <button type="button" id="stockTablePrev" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                <button type="button" id="stockTableNext" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
