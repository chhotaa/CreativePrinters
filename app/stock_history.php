<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/pagination.php';
requirePermission('stock', 'view');

$stockId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stock = null;
if ($stockId > 0) {
    $stmt = $pdo->prepare('SELECT id, product_name, quantity, reorder_level FROM stock WHERE id = ?');
    $stmt->execute([$stockId]);
    $stock = $stmt->fetch();
}

// ---- Pagination ----
$pageSizeOptions = [10, 20, 50, 100];
$page = max(1, (int)($_GET['page'] ?? 1));
$sizeIn = (int)($_GET['size'] ?? 20);
$size = in_array($sizeIn, $pageSizeOptions, true) ? $sizeIn : 20;

$currentParams = ['id' => $stockId, 'page' => $page, 'size' => $size];
$urlBuilder = fn(array $ov = []) => pageUrl('stock_history.php', $currentParams, $ov);

// Look up movements. Prefer the FK; fall back to product-name match so we
// still see history for a product that was deleted and later re-created.
$movements = [];
$totalMovements = 0;
$totalPages = 1;
$offset = 0;
if ($stock) {
    $whereSql = '(stock_id = ? OR (stock_id IS NULL AND product_name = ?))';
    $params = [$stockId, $stock['product_name']];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE $whereSql");
    $countStmt->execute($params);
    $totalMovements = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalMovements / $size));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $size;

    $stmt = $pdo->prepare(
        "SELECT * FROM stock_movements
         WHERE $whereSql
         ORDER BY created_at DESC, id DESC
         LIMIT $size OFFSET $offset"
    );
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
}

$reasonLabels = [
    'restock_confirm' => 'Restock confirmed',
    'manual_save'     => 'Manual update',
    'stock_deleted'   => 'Stock deleted',
];

$pageTitle = 'Stock History';
$pageHeading = $stock ? 'Stock History — ' . $stock['product_name'] : 'Stock History';
include __DIR__ . '/includes/layout_start.php';
?>
<div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
    <?php if (!$stock): ?>
        <p class="text-slate-600">
            That stock item was not found.
            <a href="stock.php" class="text-brand-green hover:underline">Back to Stock</a>.
        </p>
    <?php else: ?>
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
            <div class="text-sm text-slate-600">
                Current quantity: <span class="font-semibold text-brand-dark"><?= (int)$stock['quantity'] ?></span>
                &middot; Reorder level: <span class="font-semibold text-brand-dark"><?= (int)$stock['reorder_level'] ?></span>
            </div>
            <a href="stock.php" class="text-sm text-brand-green hover:underline">&larr; Back to Stock</a>
        </div>

        <?php if (empty($movements) && $totalMovements === 0): ?>
            <p class="text-sm text-slate-500">
                No recorded movements yet. New changes made via Save or a restock confirmation
                will start appearing here.
            </p>
        <?php else:
            $paginationArgs = [
                'total' => $totalMovements, 'offset' => $offset, 'size' => $size, 'pageRowCount' => count($movements),
                'page' => $page, 'totalPages' => $totalPages, 'sizeOptions' => $pageSizeOptions,
                'urlBuilder' => $urlBuilder, 'unit' => 'movement',
            ];
        ?>
            <div class="mb-3"><?php renderPaginationBar($paginationArgs); ?></div>
            <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-brand-dark text-white">
                        <th class="text-left px-3 py-2 font-semibold rounded-tl-md">When</th>
                        <th class="text-left px-3 py-2 font-semibold">Change</th>
                        <th class="text-left px-3 py-2 font-semibold">After</th>
                        <th class="text-left px-3 py-2 font-semibold">Reason</th>
                        <th class="text-left px-3 py-2 font-semibold">Details</th>
                        <th class="text-left px-3 py-2 font-semibold rounded-tr-md">By</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($movements as $m):
                    $delta = (int)$m['delta'];
                    $deltaClass = $delta > 0 ? 'text-green-700' : ($delta < 0 ? 'text-red-700' : 'text-slate-600');
                    $deltaSign  = $delta > 0 ? '+' : '';
                    $reasonLabel = $reasonLabels[$m['reason_code']] ?? $m['reason_code'];
                    $details = [];
                    if (!empty($m['reason_text'])) $details[] = $m['reason_text'];
                    if ($m['source_type'] === 'restock_order' && $m['source_id']) {
                        $details[] = 'Restock #' . (int)$m['source_id'];
                    }
                ?>
                    <tr class="border-b border-slate-100 even:bg-slate-50">
                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($m['created_at']) ?></td>
                        <td class="px-3 py-2 font-semibold <?= $deltaClass ?>"><?= $deltaSign . $delta ?></td>
                        <td class="px-3 py-2"><?= (int)$m['quantity_after'] ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($reasonLabel) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars(implode(' — ', $details)) ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($m['username'] ?? '(system)') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="mt-3"><?php renderPaginationBar($paginationArgs); ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
