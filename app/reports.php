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

$salesStmt = $pdo->prepare(
    "SELECT customer_name, COUNT(DISTINCT po_number) AS po_count, SUM(total_quantity) AS total_qty
     FROM purchase_orders WHERE po_date BETWEEN ? AND ?
     GROUP BY customer_name ORDER BY total_qty DESC"
);
$salesStmt->execute([$from, $to]);
$salesRows = $salesStmt->fetchAll();

$productionStmt = $pdo->prepare(
    "SELECT order_type, COUNT(*) AS card_count
     FROM job_cards WHERE job_date BETWEEN ? AND ?
     GROUP BY order_type ORDER BY card_count DESC"
);
$productionStmt->execute([$from, $to]);
$productionRows = $productionStmt->fetchAll();

$restockStmt = $pdo->prepare(
    "SELECT supplier_name, COUNT(*) AS order_count, SUM(received_quantity) AS total_received
     FROM restock_orders WHERE status = 'Confirmed' AND created_at BETWEEN ? AND ?
     GROUP BY supplier_name ORDER BY total_received DESC"
);
$restockStmt->execute([$from, $to . ' 23:59:59']);
$restockRows = $restockStmt->fetchAll();

$pageTitle = 'Reports';
include __DIR__ . '/includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <form method="GET" class="flex flex-wrap gap-2 items-center">
            <label class="text-sm text-slate-600">From <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="ml-1 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green"></label>
            <label class="text-sm text-slate-600">To <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="ml-1 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green"></label>
            <button type="submit" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Apply</button>
        </form>
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
<?php include __DIR__ . '/includes/layout_end.php'; ?>
