<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/activity_log.php';
require_once __DIR__ . '/includes/stock_movements.php';
requirePermission('restock_orders', 'view');
$canEdit = hasPermission('restock_orders', 'edit');

$message = '';
$error = '';

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['create_restock'])) {
        $product = trim($_POST['product_name']);
        $qty = (int)$_POST['quantity'];
        $supplier = trim($_POST['supplier_name']);
        $notes = trim($_POST['notes'] ?? '');

        if ($product === '' || $supplier === '' || $qty <= 0) {
            $error = 'Product name, supplier, and a positive quantity are required.';
        } else {
            // Resolve or create the supplier master record (case-insensitive,
            // trim-tolerant) so this restock order links to the canonical row.
            $findSupplier = $pdo->prepare(
                'SELECT id, name FROM suppliers WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1'
            );
            $findSupplier->execute([$supplier]);
            $existing = $findSupplier->fetch();
            if ($existing) {
                $supplierId = (int)$existing['id'];
                $supplier = $existing['name'];
            } else {
                $createSupplier = $pdo->prepare('INSERT INTO suppliers (name) VALUES (?)');
                $createSupplier->execute([$supplier]);
                $supplierId = (int)$pdo->lastInsertId();
            }

            $stmt = $pdo->prepare(
                'INSERT INTO restock_orders (product_name, quantity, supplier_name, supplier_id, notes) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$product, $qty, $supplier, $supplierId, $notes !== '' ? $notes : null]);
            setFlashMessage('Restock order created.');
            logActivity('create_restock', "Created restock order for \"$product\" (qty: $qty, supplier: $supplier).");
            header('Location: restock_orders.php');
            exit;
        }
    } elseif (isset($_POST['mark_purchased'])) {
        $id = (int)$_POST['restock_id'];
        $productName = $pdo->prepare('SELECT product_name FROM restock_orders WHERE id = ?');
        $productName->execute([$id]);
        $product = $productName->fetchColumn();
        $stmt = $pdo->prepare("UPDATE restock_orders SET status = 'Purchased' WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 1) {
            setFlashMessage('Marked as purchased.');
            logActivity('mark_restock_purchased', "Marked restock order #$id (\"$product\") as Purchased.");
        } else {
            setFlashError('Order could not be marked purchased (already processed or not found).');
        }
        header('Location: restock_orders.php');
        exit;
    } elseif (isset($_POST['cancel_restock'])) {
        $id = (int)$_POST['restock_id'];
        $productName = $pdo->prepare('SELECT product_name FROM restock_orders WHERE id = ?');
        $productName->execute([$id]);
        $product = $productName->fetchColumn();
        $stmt = $pdo->prepare("UPDATE restock_orders SET status = 'Cancelled' WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 1) {
            setFlashMessage('Restock order cancelled.');
            logActivity('cancel_restock', "Cancelled restock order #$id (\"$product\").");
        } else {
            setFlashError('Order could not be cancelled (already processed or not found).');
        }
        header('Location: restock_orders.php');
        exit;
    } elseif (isset($_POST['reject_restock'])) {
        $id = (int)$_POST['restock_id'];
        $productName = $pdo->prepare('SELECT product_name FROM restock_orders WHERE id = ?');
        $productName->execute([$id]);
        $product = $productName->fetchColumn();
        $stmt = $pdo->prepare("UPDATE restock_orders SET status = 'Pending' WHERE id = ? AND status = 'Purchased'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 1) {
            setFlashMessage('Order rejected back to Pending.');
            logActivity('reject_restock', "Rejected restock order #$id (\"$product\") back to Pending.");
        } else {
            setFlashError('Order could not be rejected (not currently awaiting confirmation).');
        }
        header('Location: restock_orders.php');
        exit;
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

                // Fetch the resulting stock row so we can record the movement
                // with a concrete stock_id and post-change quantity.
                $stockRow = $pdo->prepare('SELECT id, quantity FROM stock WHERE product_name = ?');
                $stockRow->execute([$order['product_name']]);
                $stockAfter = $stockRow->fetch();
                if ($stockAfter && $receivedQty > 0) {
                    recordStockMovement(
                        $pdo,
                        (int)$stockAfter['id'],
                        $order['product_name'],
                        $receivedQty,
                        (int)$stockAfter['quantity'],
                        STOCK_MOVEMENT_RESTOCK_CONFIRM,
                        null,
                        'restock_order',
                        $id
                    );
                }

                $pdo->commit();
                setFlashMessage('Order confirmed and stock updated.');
                logActivity('confirm_restock', "Confirmed restock order #$id (\"{$order['product_name']}\"), received qty: $receivedQty.");
                header('Location: restock_orders.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

$existingProducts = $canEdit ? $pdo->query('SELECT DISTINCT product_name FROM stock ORDER BY product_name')->fetchAll() : [];
$existingSuppliers = $canEdit ? $pdo->query('SELECT name FROM suppliers ORDER BY name')->fetchAll() : [];

// ---- Pagination + search + status filter ----
require_once __DIR__ . '/includes/pagination.php';
$pageSizeOptions = [10, 20, 50, 100];
$page = max(1, (int)($_GET['page'] ?? 1));
$sizeIn = (int)($_GET['size'] ?? 20);
$size = in_array($sizeIn, $pageSizeOptions, true) ? $sizeIn : 20;
$q = trim($_GET['q'] ?? '');
$allowedStatusFilters = ['all', 'Pending', 'Purchased', 'Confirmed', 'Cancelled'];
$filterIn = $_GET['filter'] ?? 'all';
$filter = in_array($filterIn, $allowedStatusFilters, true) ? $filterIn : 'all';

$currentParams = ['page' => $page, 'size' => $size, 'q' => $q, 'filter' => $filter === 'all' ? '' : $filter];
$urlBuilder = fn(array $ov = []) => pageUrl('restock_orders.php', $currentParams, $ov);

$whereBits = [];
$whereParams = [];
if ($filter !== 'all') {
    $whereBits[] = 'ro.status = ?';
    $whereParams[] = $filter;
}
if ($q !== '') {
    $whereBits[] = '(ro.product_name LIKE ? OR ro.supplier_name LIKE ? OR s.name LIKE ? OR ro.notes LIKE ?)';
    $qLike = '%' . $q . '%';
    array_push($whereParams, $qLike, $qLike, $qLike, $qLike);
}
$whereSql = $whereBits ? 'WHERE ' . implode(' AND ', $whereBits) : '';

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM restock_orders ro LEFT JOIN suppliers s ON s.id = ro.supplier_id $whereSql"
);
$countStmt->execute($whereParams);
$totalRos = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRos / $size));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $size;

$roStmt = $pdo->prepare(
    "SELECT ro.*, COALESCE(s.name, ro.supplier_name) AS supplier_display
     FROM restock_orders ro
     LEFT JOIN suppliers s ON s.id = ro.supplier_id
     $whereSql
     ORDER BY ro.created_at DESC
     LIMIT $size OFFSET $offset"
);
$roStmt->execute($whereParams);
$restockOrders = $roStmt->fetchAll();

$pageTitle = 'Restock Orders';
include __DIR__ . '/includes/layout_start.php';

$paginationArgs = [
    'total' => $totalRos, 'offset' => $offset, 'size' => $size, 'pageRowCount' => count($restockOrders),
    'page' => $page, 'totalPages' => $totalPages, 'sizeOptions' => $pageSizeOptions,
    'urlBuilder' => $urlBuilder, 'unit' => 'order',
];
?>
    <?php if ($canEdit): ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Create Restock Order</h3>
        <p class="text-sm text-slate-500 mb-3">This is for buying stock for our own inventory (not a customer Purchase Order). Once created, mark it Purchased after buying it, then confirm here to add it into Stock.</p>
        <form method="POST" class="flex flex-wrap gap-2 items-center">
                <?= csrfField() ?>
            <input type="text" name="product_name" list="stock-products" placeholder="Product name" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <datalist id="stock-products">
                <?php foreach ($existingProducts as $p): ?>
                    <option value="<?= htmlspecialchars($p['product_name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <input type="number" name="quantity" placeholder="Quantity to order" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green w-44">
            <input type="text" id="restock-supplier-input" name="supplier_name" placeholder="Supplier name" required autocomplete="off" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="text" name="notes" placeholder="Notes (optional)" class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <button type="submit" name="create_restock" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Create Restock Order</button>
        </form>
    </div>
    <script src="autocomplete.js"></script>
    <script>
        attachAutocomplete(
            document.getElementById('restock-supplier-input'),
            <?= json_encode(array_map(fn($s) => $s['name'], $existingSuppliers), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        );
    </script>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div class="inline-flex rounded-md border border-slate-300 overflow-hidden text-sm">
                <?php foreach ([['all','All'], ['Pending','Pending'], ['Purchased','Purchased'], ['Confirmed','Confirmed'], ['Cancelled','Cancelled']] as [$key, $label]):
                    $active = $filter === $key;
                ?>
                    <a href="<?= htmlspecialchars($urlBuilder(['filter' => $key === 'all' ? '' : $key])) ?>" class="px-3 py-1.5 <?= $active ? 'font-semibold text-white bg-brand-dark' : 'font-medium text-slate-600 hover:bg-slate-50 border-l border-slate-300' ?>"><?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
            </div>
            <form method="GET" action="restock_orders.php" class="flex items-center gap-2">
                <input type="hidden" name="size" value="<?= (int)$size ?>">
                <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search restock orders..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <button type="submit" class="px-3 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark">Search</button>
                <?php if ($q !== ''): ?><a href="<?= htmlspecialchars($urlBuilder(['q' => ''])) ?>" class="text-xs text-slate-500 underline">clear</a><?php endif; ?>
            </form>
        </div>
        <div class="mb-3"><?php renderPaginationBar($paginationArgs); ?></div>
        <div class="overflow-x-auto">
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
                    <?php if ($canEdit): ?><th class="text-left px-3 py-2 font-semibold rounded-tr-md"></th><?php endif; ?>
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
                <tr class="border-b border-slate-100 even:bg-slate-50 hover:bg-slate-100">
                    <td class="px-3 py-2"><?= htmlspecialchars($r['product_name']) ?></td>
                    <td class="px-3 py-2"><?= (int)$r['quantity'] ?></td>
                    <td class="px-3 py-2">
                        <?php if (!empty($r['supplier_id'])): ?>
                            <a href="supplier_detail.php?id=<?= (int)$r['supplier_id'] ?>" class="text-brand-green hover:underline"><?= htmlspecialchars($r['supplier_display']) ?></a>
                        <?php else: ?>
                            <?= htmlspecialchars($r['supplier_display']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2"><?= htmlspecialchars($r['notes'] ?? '') ?></td>
                    <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeClass ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    <td class="px-3 py-2"><?= $r['received_quantity'] !== null ? (int)$r['received_quantity'] : '-' ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($r['created_at']) ?></td>
                    <?php if ($canEdit): ?>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <?php if ($r['status'] === 'Pending'): ?>
                            <form method="POST" style="display:inline-block; margin:0;">
                <?= csrfField() ?>
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <button type="submit" name="mark_purchased" value="1" class="px-3 py-1.5 rounded-md bg-brand-green text-white text-xs font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Mark Purchased</button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Cancel this restock order?');" style="display:inline-block; margin:0;">
                <?= csrfField() ?>
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <button type="submit" name="cancel_restock" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Cancel</button>
                            </form>
                        <?php elseif ($r['status'] === 'Purchased'): ?>
                            <form method="POST" style="display:inline-block; margin:0;">
                <?= csrfField() ?>
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <input type="number" name="received_quantity" value="<?= (int)$r['quantity'] ?>" min="0" class="w-20 px-2 py-1 border border-slate-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                                <button type="submit" name="confirm_restock" value="1" class="px-3 py-1.5 rounded-md bg-brand-green text-white text-xs font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Confirm</button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Reject this back to Pending?');" style="display:inline-block; margin:0;">
                <?= csrfField() ?>
                                <input type="hidden" name="restock_id" value="<?= $r['id'] ?>">
                                <button type="submit" name="reject_restock" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Reject</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="mt-3"><?php renderPaginationBar($paginationArgs); ?></div>
    </div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
