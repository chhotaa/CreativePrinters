<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xlsx_writer.php';
requirePermission('reports', 'view');

$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-30 days'));
$from = $_GET['from'] ?? $defaultFrom;
$to = $_GET['to'] ?? $today;

if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    $report = $_GET['report'] ?? '';

    if ($report === 'sales') {
        $rows = $pdo->prepare(
            "SELECT COALESCE(c.name, po.customer_name) AS customer_name,
                    COUNT(DISTINCT po.po_number) AS po_count,
                    SUM(po.total_quantity) AS total_qty
             FROM purchase_orders po
             LEFT JOIN customers c ON c.id = po.customer_id
             WHERE po.po_date BETWEEN ? AND ?
             GROUP BY COALESCE(po.customer_id, 0), COALESCE(c.name, po.customer_name)
             ORDER BY total_qty DESC"
        );
        $rows->execute([$from, $to]);
        $data = array_map(fn($r) => [$r['customer_name'], (int)$r['po_count'], (int)$r['total_qty']], $rows->fetchAll());
        outputXlsx('sales_by_customer.xlsx', ['Customer', 'PO Count', 'Total Quantity'], $data);
        exit;
    } elseif ($report === 'production') {
        $rows = $pdo->prepare(
            "SELECT order_type, COUNT(*) AS card_count
             FROM job_cards WHERE job_date BETWEEN ? AND ?
             GROUP BY order_type ORDER BY card_count DESC"
        );
        $rows->execute([$from, $to]);
        $data = array_map(fn($r) => [$r['order_type'], (int)$r['card_count']], $rows->fetchAll());
        outputXlsx('production_volume.xlsx', ['Order Type', 'Job Card Count'], $data);
        exit;
    } elseif ($report === 'stock_requests') {
        $rows = $pdo->prepare(
            "SELECT ri.product_name,
                    COUNT(DISTINCT ri.request_id) AS request_count,
                    SUM(ri.quantity) AS total_requested,
                    SUM(CASE WHEN r.status = 'Approved' THEN ri.quantity ELSE 0 END) AS approved_qty,
                    SUM(CASE WHEN r.status = 'Rejected' THEN ri.quantity ELSE 0 END) AS rejected_qty,
                    SUM(CASE WHEN r.status = 'Pending' THEN ri.quantity ELSE 0 END) AS pending_qty,
                    SUM(CASE WHEN r.status = 'Deleted by User' THEN ri.quantity ELSE 0 END) AS deleted_qty
             FROM stock_request_items ri
             JOIN stock_requests r ON r.id = ri.request_id
             WHERE DATE(r.created_at) BETWEEN ? AND ?
             GROUP BY ri.product_name
             ORDER BY total_requested DESC"
        );
        $rows->execute([$from, $to]);
        $data = array_map(fn($r) => [
            $r['product_name'],
            (int)$r['request_count'],
            (int)$r['total_requested'],
            (int)$r['approved_qty'],
            (int)$r['rejected_qty'],
            (int)$r['pending_qty'],
            (int)$r['deleted_qty'],
        ], $rows->fetchAll());
        outputXlsx(
            'stock_requests_by_product.xlsx',
            ['Product', 'Request Count', 'Total Requested', 'Approved Qty', 'Rejected Qty', 'Pending Qty', 'Deleted Qty'],
            $data
        );
        exit;
    } elseif ($report === 'stock_request_details') {
        $rows = $pdo->prepare(
            "SELECT r.id, r.status, r.created_at, r.reviewed_at, r.review_notes, r.delete_reason,
                    u.username AS requester, ru.username AS reviewer,
                    (SELECT GROUP_CONCAT(CONCAT(product_name, ' x ', quantity) ORDER BY id SEPARATOR ', ')
                     FROM stock_request_items WHERE request_id = r.id) AS items_summary,
                    (SELECT SUM(quantity) FROM stock_request_items WHERE request_id = r.id) AS total_qty
             FROM stock_requests r
             JOIN users u ON u.id = r.requested_by_user_id
             LEFT JOIN users ru ON ru.id = r.reviewed_by_user_id
             WHERE DATE(r.created_at) BETWEEN ? AND ?
             ORDER BY r.created_at DESC"
        );
        $rows->execute([$from, $to]);
        $data = array_map(fn($r) => [
            (int)$r['id'],
            $r['created_at'],
            $r['requester'],
            $r['items_summary'] ?? '',
            (int)($r['total_qty'] ?? 0),
            $r['status'],
            $r['reviewer'] ?? '',
            $r['reviewed_at'] ?? '',
            $r['review_notes'] ?? $r['delete_reason'] ?? '',
        ], $rows->fetchAll());
        outputXlsx(
            'stock_request_details.xlsx',
            ['Request ID', 'Created', 'Requester', 'Items', 'Total Qty', 'Status', 'Reviewer', 'Reviewed At', 'Notes / Reason'],
            $data
        );
        exit;
    } elseif ($report === 'restock') {
        $rows = $pdo->prepare(
            "SELECT COALESCE(s.name, ro.supplier_name) AS supplier_name,
                    COUNT(*) AS order_count,
                    SUM(ro.received_quantity) AS total_received
             FROM restock_orders ro
             LEFT JOIN suppliers s ON s.id = ro.supplier_id
             WHERE ro.status = 'Confirmed' AND ro.created_at BETWEEN ? AND ?
             GROUP BY COALESCE(ro.supplier_id, 0), COALESCE(s.name, ro.supplier_name)
             ORDER BY total_received DESC"
        );
        $rows->execute([$from, $to . ' 23:59:59']);
        $data = array_map(fn($r) => [$r['supplier_name'], (int)$r['order_count'], (int)$r['total_received']], $rows->fetchAll());
        outputXlsx('restock_activity_by_supplier.xlsx', ['Supplier', 'Confirmed Orders', 'Total Quantity Received'], $data);
        exit;
    }
    http_response_code(400);
    die('Unknown report.');
}

// On-page previews are capped at 5 rows -- the Excel export links use
// separate unlimited queries so downloads always contain everything.
$PREVIEW_LIMIT = 5;

$salesStmt = $pdo->prepare(
    "SELECT customer_name, COUNT(DISTINCT po_number) AS po_count, SUM(total_quantity) AS total_qty
     FROM purchase_orders WHERE po_date BETWEEN ? AND ?
     GROUP BY customer_name ORDER BY total_qty DESC LIMIT $PREVIEW_LIMIT"
);
$salesStmt->execute([$from, $to]);
$salesRows = $salesStmt->fetchAll();

$productionStmt = $pdo->prepare(
    "SELECT order_type, COUNT(*) AS card_count
     FROM job_cards WHERE job_date BETWEEN ? AND ?
     GROUP BY order_type ORDER BY card_count DESC LIMIT $PREVIEW_LIMIT"
);
$productionStmt->execute([$from, $to]);
$productionRows = $productionStmt->fetchAll();

$restockStmt = $pdo->prepare(
    "SELECT supplier_name, COUNT(*) AS order_count, SUM(received_quantity) AS total_received
     FROM restock_orders WHERE status = 'Confirmed' AND created_at BETWEEN ? AND ?
     GROUP BY supplier_name ORDER BY total_received DESC LIMIT $PREVIEW_LIMIT"
);
$restockStmt->execute([$from, $to . ' 23:59:59']);
$restockRows = $restockStmt->fetchAll();

$stockReqStmt = $pdo->prepare(
    "SELECT ri.product_name,
            COUNT(DISTINCT ri.request_id) AS request_count,
            SUM(ri.quantity) AS total_requested,
            SUM(CASE WHEN r.status = 'Approved' THEN ri.quantity ELSE 0 END) AS approved_qty,
            SUM(CASE WHEN r.status = 'Rejected' THEN ri.quantity ELSE 0 END) AS rejected_qty,
            SUM(CASE WHEN r.status = 'Pending' THEN ri.quantity ELSE 0 END) AS pending_qty,
            SUM(CASE WHEN r.status = 'Deleted by User' THEN ri.quantity ELSE 0 END) AS deleted_qty
     FROM stock_request_items ri
     JOIN stock_requests r ON r.id = ri.request_id
     WHERE DATE(r.created_at) BETWEEN ? AND ?
     GROUP BY ri.product_name
     ORDER BY total_requested DESC LIMIT $PREVIEW_LIMIT"
);
$stockReqStmt->execute([$from, $to]);
$stockReqRows = $stockReqStmt->fetchAll();

// One row per request with items concatenated -- shows the WHO behind
// each request (requester + reviewer) alongside the by-product summary.
$stockReqDetailStmt = $pdo->prepare(
    "SELECT r.id, r.status, r.created_at, r.reviewed_at, r.review_notes, r.delete_reason,
            u.username AS requester, ru.username AS reviewer,
            (SELECT GROUP_CONCAT(CONCAT(product_name, ' x ', quantity) ORDER BY id SEPARATOR ', ')
             FROM stock_request_items WHERE request_id = r.id) AS items_summary,
            (SELECT SUM(quantity) FROM stock_request_items WHERE request_id = r.id) AS total_qty
     FROM stock_requests r
     JOIN users u ON u.id = r.requested_by_user_id
     LEFT JOIN users ru ON ru.id = r.reviewed_by_user_id
     WHERE DATE(r.created_at) BETWEEN ? AND ?
     ORDER BY r.created_at DESC LIMIT $PREVIEW_LIMIT"
);
$stockReqDetailStmt->execute([$from, $to]);
$stockReqDetailRows = $stockReqDetailStmt->fetchAll();

$pageTitle = 'Reports';
include __DIR__ . '/includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <form method="GET" class="flex flex-wrap gap-2 items-center">
            <label class="text-sm text-slate-600">From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="ml-1 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green"></label>
            <label class="text-sm text-slate-600">To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="ml-1 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green"></label>
            <button type="submit" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Apply</button>
        </form>
        <p class="mt-2 text-xs text-slate-500">Each table below previews the top 5 rows — click <strong>Export Excel</strong> for the complete data.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-brand-dark">Sales by Customer</h3>
            <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=xlsx&report=sales" class="text-sm font-semibold text-brand-green hover:text-brand-greendark">Export Excel</a>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Customer</th>
                    <th class="text-left px-3 py-2 font-semibold">PO Count</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md">Total Quantity</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($salesRows)): ?>
                <tr><td colspan="3" class="px-3 py-4 text-center text-slate-400">No purchase orders in this date range.</td></tr>
            <?php endif; ?>
            <?php foreach ($salesRows as $r): ?>
                <tr class="border-b border-slate-100 even:bg-slate-50">
                    <td class="px-3 py-2"><?= htmlspecialchars($r['customer_name']) ?></td>
                    <td class="px-3 py-2"><?= (int)$r['po_count'] ?></td>
                    <td class="px-3 py-2"><?= (int)$r['total_qty'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-brand-dark">Production Volume</h3>
            <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=xlsx&report=production" class="text-sm font-semibold text-brand-green hover:text-brand-greendark">Export Excel</a>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Order Type</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md">Job Card Count</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($productionRows)): ?>
                <tr><td colspan="2" class="px-3 py-4 text-center text-slate-400">No job cards in this date range.</td></tr>
            <?php endif; ?>
            <?php foreach ($productionRows as $r): ?>
                <tr class="border-b border-slate-100 even:bg-slate-50">
                    <td class="px-3 py-2"><?= htmlspecialchars($r['order_type']) ?></td>
                    <td class="px-3 py-2"><?= (int)$r['card_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-brand-dark">Restock Activity by Supplier</h3>
            <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=xlsx&report=restock" class="text-sm font-semibold text-brand-green hover:text-brand-greendark">Export Excel</a>
        </div>
        <p class="text-xs text-slate-400 mb-3">Shows confirmed restock order counts and quantities — not a dollar figure, since unit cost isn't tracked yet.</p>
        <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Supplier</th>
                    <th class="text-left px-3 py-2 font-semibold">Confirmed Orders</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md">Total Quantity Received</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($restockRows)): ?>
                <tr><td colspan="3" class="px-3 py-4 text-center text-slate-400">No confirmed restock orders in this date range.</td></tr>
            <?php endif; ?>
            <?php foreach ($restockRows as $r): ?>
                <tr class="border-b border-slate-100 even:bg-slate-50">
                    <td class="px-3 py-2"><?= htmlspecialchars($r['supplier_name']) ?></td>
                    <td class="px-3 py-2"><?= (int)$r['order_count'] ?></td>
                    <td class="px-3 py-2"><?= (int)$r['total_received'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-brand-dark">Stock Requests by Product</h3>
            <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=xlsx&report=stock_requests" class="text-sm font-semibold text-brand-green hover:text-brand-greendark">Export Excel</a>
        </div>
        <p class="text-xs text-slate-400 mb-3">Quantities requested per product, broken down by outcome. Approved = actually consumed from stock. Date range is by request creation date.</p>
        <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Product</th>
                    <th class="text-right px-3 py-2 font-semibold">Requests</th>
                    <th class="text-right px-3 py-2 font-semibold">Requested</th>
                    <th class="text-right px-3 py-2 font-semibold">Approved</th>
                    <th class="text-right px-3 py-2 font-semibold">Rejected</th>
                    <th class="text-right px-3 py-2 font-semibold">Pending</th>
                    <th class="text-right px-3 py-2 font-semibold rounded-tr-md">Deleted</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($stockReqRows)): ?>
                <tr><td colspan="7" class="px-3 py-4 text-center text-slate-400">No stock requests in this date range.</td></tr>
            <?php endif; ?>
            <?php foreach ($stockReqRows as $r): ?>
                <tr class="border-b border-slate-100 even:bg-slate-50">
                    <td class="px-3 py-2"><?= htmlspecialchars($r['product_name']) ?></td>
                    <td class="px-3 py-2 text-right"><?= (int)$r['request_count'] ?></td>
                    <td class="px-3 py-2 text-right font-semibold"><?= (int)$r['total_requested'] ?></td>
                    <td class="px-3 py-2 text-right text-green-700"><?= (int)$r['approved_qty'] ?></td>
                    <td class="px-3 py-2 text-right text-red-700"><?= (int)$r['rejected_qty'] ?></td>
                    <td class="px-3 py-2 text-right text-amber-700"><?= (int)$r['pending_qty'] ?></td>
                    <td class="px-3 py-2 text-right text-slate-500"><?= (int)$r['deleted_qty'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php
    $detailStatusBadge = [
        'Pending'         => 'bg-amber-100 text-amber-800',
        'Approved'        => 'bg-green-100 text-green-800',
        'Rejected'        => 'bg-red-100 text-red-800',
        'Deleted by User' => 'bg-slate-200 text-slate-700',
    ];
    ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-brand-dark">Stock Request Details</h3>
            <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=xlsx&report=stock_request_details" class="text-sm font-semibold text-brand-green hover:text-brand-greendark">Export Excel</a>
        </div>
        <p class="text-xs text-slate-400 mb-3">One row per request — shows who raised it, what was requested, current status, and who approved / rejected / marked it deleted.</p>
        <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">#</th>
                    <th class="text-left px-3 py-2 font-semibold">Created</th>
                    <th class="text-left px-3 py-2 font-semibold">Requested By</th>
                    <th class="text-left px-3 py-2 font-semibold">Items</th>
                    <th class="text-right px-3 py-2 font-semibold">Total Qty</th>
                    <th class="text-left px-3 py-2 font-semibold">Status</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md">Reviewed By</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($stockReqDetailRows)): ?>
                <tr><td colspan="7" class="px-3 py-4 text-center text-slate-400">No stock requests in this date range.</td></tr>
            <?php endif; ?>
            <?php foreach ($stockReqDetailRows as $r):
                $noteText = $r['review_notes'] ?? $r['delete_reason'] ?? '';
                $reviewerLabel = $r['reviewer'] ?? '';
                if ($r['status'] === 'Deleted by User' && $reviewerLabel === '') {
                    $reviewerLabel = $r['requester'];
                }
            ?>
                <tr class="border-b border-slate-100 even:bg-slate-50">
                    <td class="px-3 py-2 font-semibold text-brand-dark">#<?= (int)$r['id'] ?></td>
                    <td class="px-3 py-2 text-xs text-slate-600 whitespace-nowrap"><?= htmlspecialchars($r['created_at']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($r['requester']) ?></td>
                    <td class="px-3 py-2 text-xs"><?= htmlspecialchars($r['items_summary'] ?? '') ?></td>
                    <td class="px-3 py-2 text-right font-semibold"><?= (int)($r['total_qty'] ?? 0) ?></td>
                    <td class="px-3 py-2">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $detailStatusBadge[$r['status']] ?? 'bg-slate-100 text-slate-700' ?>"><?= htmlspecialchars($r['status']) ?></span>
                    </td>
                    <td class="px-3 py-2 text-xs">
                        <?php if ($reviewerLabel): ?>
                            <div class="text-slate-700"><?= htmlspecialchars($reviewerLabel) ?></div>
                            <?php if ($r['reviewed_at']): ?>
                                <div class="text-slate-400"><?= htmlspecialchars($r['reviewed_at']) ?></div>
                            <?php endif; ?>
                            <?php if ($noteText): ?>
                                <div class="text-slate-600 italic mt-1">"<?= htmlspecialchars($noteText) ?>"</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
