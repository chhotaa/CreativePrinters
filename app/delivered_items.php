<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pagination.php';
requirePermission('delivered_items', 'view');

// ---- URL params ----
$pageSizeOptions = [10, 20, 50, 100];
$page = max(1, (int)($_GET['page'] ?? 1));
$sizeIn = (int)($_GET['size'] ?? 20);
$size = in_array($sizeIn, $pageSizeOptions, true) ? $sizeIn : 20;
$q = trim($_GET['q'] ?? '');

$currentParams = ['page' => $page, 'size' => $size, 'q' => $q];
$urlBuilder = fn(array $ov = []) => pageUrl('delivered_items.php', $currentParams, $ov);

// ---- WHERE clause ----
$whereBits = ["d.status = 'Delivered'"];
$whereParams = [];
if ($q !== '') {
    $whereBits[] = '(po.po_number LIKE ? OR po.customer_name LIKE ? OR po.item_code LIKE ? OR po.description LIKE ? OR d.dc_number LIKE ? OR d.invoice_number LIKE ?)';
    $qLike = '%' . $q . '%';
    array_push($whereParams, $qLike, $qLike, $qLike, $qLike, $qLike, $qLike);
}
$whereSql = 'WHERE ' . implode(' AND ', $whereBits);

// ---- Count + fetch ----
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM deliveries d JOIN purchase_orders po ON po.id = d.po_id $whereSql"
);
$countStmt->execute($whereParams);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $size));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $size;

$rowsStmt = $pdo->prepare(
    "SELECT d.id, d.quantity AS delivered_qty, d.due_date,
            d.dc_number, d.dc_date, d.invoice_number, d.bill_date,
            po.po_number, po.po_date, po.customer_name, po.item_code, po.description,
            po.total_quantity AS ordered_qty
     FROM deliveries d JOIN purchase_orders po ON po.id = d.po_id
     $whereSql
     ORDER BY COALESCE(d.dc_date, d.bill_date, d.due_date) DESC, d.id DESC
     LIMIT $size OFFSET $offset"
);
$rowsStmt->execute($whereParams);
$rows = $rowsStmt->fetchAll();

$pageTitle = 'Delivered Items';
include __DIR__ . '/includes/layout_start.php';

$paginationArgs = [
    'total' => $totalRows, 'offset' => $offset, 'size' => $size, 'pageRowCount' => count($rows),
    'page' => $page, 'totalPages' => $totalPages, 'sizeOptions' => $pageSizeOptions,
    'urlBuilder' => $urlBuilder, 'unit' => 'row',
];
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h3 class="text-lg font-semibold text-brand-dark">All Delivered Items</h3>
            <form method="GET" action="delivered_items.php" class="flex items-center gap-2">
                <input type="hidden" name="size" value="<?= (int)$size ?>">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search PO, customer, item, DC, invoice..." class="w-full sm:w-80 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <button type="submit" class="px-3 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark">Search</button>
                <?php if ($q !== ''): ?><a href="<?= htmlspecialchars($urlBuilder(['q' => ''])) ?>" class="text-xs text-slate-500 underline">clear</a><?php endif; ?>
            </form>
        </div>

        <div class="mb-3"><?php renderPaginationBar($paginationArgs); ?></div>

        <div class="overflow-x-auto border border-slate-100 rounded-md">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-brand-dark text-white">
                        <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">PO Number</th>
                        <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">PO Date</th>
                        <th class="text-left px-3 py-2 font-semibold">Customer</th>
                        <th class="text-left px-3 py-2 font-semibold">Item</th>
                        <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">Ordered</th>
                        <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">Delivered</th>
                        <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">DC Number</th>
                        <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">DC Date</th>
                        <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">Invoice Number</th>
                        <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">Bill Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="10" class="px-3 py-6 text-center text-slate-400"><?= $q !== '' ? 'No delivered items match your search.' : 'No delivered items yet.' ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold text-brand-dark whitespace-nowrap"><?= htmlspecialchars($r['po_number']) ?></td>
                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($r['po_date'] ?? '') ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td class="px-3 py-2">
                            <div class="font-semibold text-slate-700"><?= htmlspecialchars($r['item_code']) ?></div>
                            <?php if (!empty($r['description'])): ?>
                                <div class="text-xs text-slate-500 truncate max-w-[240px]"><?= htmlspecialchars($r['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-right"><?= (int)$r['ordered_qty'] ?></td>
                        <td class="px-3 py-2 text-right font-semibold"><?= (int)$r['delivered_qty'] ?></td>
                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($r['dc_number'] ?? '') ?></td>
                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($r['dc_date'] ?? '') ?></td>
                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($r['invoice_number'] ?? '') ?></td>
                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($r['bill_date'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-3"><?php renderPaginationBar($paginationArgs); ?></div>
    </div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
