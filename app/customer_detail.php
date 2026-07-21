<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
requirePermission('customers', 'view');

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$customer = null;
if ($customerId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
}

$poRows = [];
$stats = ['po_count' => 0, 'ordered_qty' => 0, 'delivered_qty' => 0, 'overdue' => 0];

if ($customer) {
    // One row per (PO item, delivery). LEFT JOIN so PO items with no
    // deliveries yet still appear. Ordering is DESC on po_date so newest
    // POs come first; within a PO, item_code then due_date so the group
    // stays coherent when we walk the rows in PHP.
    $stmt = $pdo->prepare(
        'SELECT po.id AS po_row_id, po.po_number, po.po_date, po.item_code,
                po.description, po.total_quantity,
                d.id AS delivery_id, d.due_date, d.quantity AS delivered_qty,
                d.status, d.dc_number, d.invoice_number, d.dc_date, d.bill_date
         FROM purchase_orders po
         LEFT JOIN deliveries d ON d.po_id = po.id
         WHERE po.customer_id = ?
         ORDER BY po.po_date DESC, po.po_number DESC, po.item_code, d.due_date'
    );
    $stmt->execute([$customerId]);
    $poRows = $stmt->fetchAll();

    // Summary stats — computed separately so LEFT JOIN duplicates don't
    // double-count total_quantity.
    $s = $pdo->prepare(
        'SELECT
           COUNT(DISTINCT po.po_number) AS po_count,
           COALESCE(SUM(po.total_quantity), 0) AS ordered_qty
         FROM purchase_orders po
         WHERE po.customer_id = ?'
    );
    $s->execute([$customerId]);
    $agg = $s->fetch();
    $stats['po_count']    = (int)$agg['po_count'];
    $stats['ordered_qty'] = (int)$agg['ordered_qty'];

    $d = $pdo->prepare(
        "SELECT
           COALESCE(SUM(CASE WHEN d.status = 'Delivered' THEN d.quantity ELSE 0 END), 0) AS delivered_qty,
           SUM(CASE WHEN d.status != 'Delivered' AND d.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
         FROM deliveries d
         JOIN purchase_orders po ON po.id = d.po_id
         WHERE po.customer_id = ?"
    );
    $d->execute([$customerId]);
    $agg = $d->fetch();
    $stats['delivered_qty'] = (int)$agg['delivered_qty'];
    $stats['overdue']       = (int)$agg['overdue'];
}

// Group flat rows into: po_number -> item_row -> deliveries[]
$grouped = [];
foreach ($poRows as $r) {
    $po = $r['po_number'];
    if (!isset($grouped[$po])) {
        $grouped[$po] = ['po_number' => $po, 'po_date' => $r['po_date'], 'items' => []];
    }
    $itemKey = $r['po_row_id'];
    if (!isset($grouped[$po]['items'][$itemKey])) {
        $grouped[$po]['items'][$itemKey] = [
            'po_row_id'      => $r['po_row_id'],
            'item_code'      => $r['item_code'],
            'description'    => $r['description'],
            'total_quantity' => (int)$r['total_quantity'],
            'deliveries'     => [],
        ];
    }
    if ($r['delivery_id']) {
        $grouped[$po]['items'][$itemKey]['deliveries'][] = [
            'id'             => $r['delivery_id'],
            'due_date'       => $r['due_date'],
            'quantity'       => (int)$r['delivered_qty'],
            'status'         => $r['status'],
            'dc_number'      => $r['dc_number'],
            'invoice_number' => $r['invoice_number'],
            'dc_date'        => $r['dc_date'],
            'bill_date'      => $r['bill_date'],
        ];
    }
}

$statusBadge = [
    'Pending'   => 'bg-amber-100 text-amber-800',
    'Shipped'   => 'bg-blue-100 text-blue-800',
    'Delivered' => 'bg-green-100 text-green-800',
];

$pageTitle = 'Customer Detail';
$pageHeading = $customer ? $customer['name'] : 'Customer Detail';
include __DIR__ . '/includes/layout_start.php';
?>
<?php if (!$customer): ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <p class="text-slate-600">That customer was not found. <a href="customers.php" class="text-brand-green hover:underline">Back to Customers</a>.</p>
    </div>
<?php else: ?>
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
        <a href="customers.php" class="text-sm text-brand-green hover:underline">&larr; Back to Customers</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Contact</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
            <div><div class="text-xs text-slate-500">Contact Person</div><div><?= htmlspecialchars($customer['contact_person'] ?? '—') ?></div></div>
            <div><div class="text-xs text-slate-500">Phone</div><div><?= htmlspecialchars($customer['phone'] ?? '—') ?></div></div>
            <div><div class="text-xs text-slate-500">Email</div><div><?= htmlspecialchars($customer['email'] ?? '—') ?></div></div>
            <div><div class="text-xs text-slate-500">Address</div><div><?= htmlspecialchars($customer['address'] ?? '—') ?></div></div>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4">
            <div class="text-xs text-slate-500">Purchase Orders</div>
            <div class="text-2xl font-bold text-brand-dark"><?= $stats['po_count'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4">
            <div class="text-xs text-slate-500">Total Ordered Qty</div>
            <div class="text-2xl font-bold text-brand-dark"><?= $stats['ordered_qty'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4">
            <div class="text-xs text-slate-500">Delivered Qty</div>
            <div class="text-2xl font-bold text-green-700"><?= $stats['delivered_qty'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4 <?= $stats['overdue'] > 0 ? 'ring-red-300' : '' ?>">
            <div class="text-xs text-slate-500">Overdue Deliveries</div>
            <div class="text-2xl font-bold <?= $stats['overdue'] > 0 ? 'text-red-600' : 'text-slate-500' ?>"><?= $stats['overdue'] ?></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Purchase Orders</h3>
        <?php if (empty($grouped)): ?>
            <p class="text-sm text-slate-500">This customer has no purchase orders yet.</p>
        <?php else: ?>
            <div class="space-y-5">
            <?php foreach ($grouped as $po): ?>
                <div class="border border-slate-200 rounded-lg overflow-hidden">
                    <div class="bg-slate-50 px-4 py-2 flex flex-wrap items-baseline justify-between gap-2">
                        <div class="font-semibold text-brand-dark"><?= htmlspecialchars($po['po_number']) ?></div>
                        <div class="text-xs text-slate-500"><?= htmlspecialchars($po['po_date'] ?? '') ?></div>
                    </div>
                    <div class="divide-y divide-slate-100">
                    <?php foreach ($po['items'] as $item): ?>
                        <div class="px-4 py-3">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                                <div class="text-sm">
                                    <span class="font-medium text-slate-700"><?= htmlspecialchars($item['item_code'] ?: '(no item code)') ?></span>
                                    <?php if ($item['description']): ?>
                                        <span class="text-slate-500"> — <?= htmlspecialchars($item['description']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-slate-500">Ordered qty: <span class="font-semibold text-slate-700"><?= $item['total_quantity'] ?></span></div>
                            </div>
                            <?php if (empty($item['deliveries'])): ?>
                                <div class="text-xs text-slate-400 italic">No delivery scheduled yet.</div>
                            <?php else: ?>
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-slate-500">
                                            <th class="text-left pb-1 font-medium">Due Date</th>
                                            <th class="text-left pb-1 font-medium">Qty</th>
                                            <th class="text-left pb-1 font-medium">Status</th>
                                            <th class="text-left pb-1 font-medium">DC No.</th>
                                            <th class="text-left pb-1 font-medium">DC Date</th>
                                            <th class="text-left pb-1 font-medium">Invoice No.</th>
                                            <th class="text-left pb-1 font-medium">Bill Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($item['deliveries'] as $d): ?>
                                        <?php $badge = $statusBadge[$d['status']] ?? 'bg-slate-100 text-slate-700'; ?>
                                        <tr class="border-t border-slate-100">
                                            <td class="py-1.5"><?= htmlspecialchars($d['due_date']) ?></td>
                                            <td class="py-1.5"><?= $d['quantity'] ?></td>
                                            <td class="py-1.5"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $badge ?>"><?= htmlspecialchars($d['status']) ?></span></td>
                                            <td class="py-1.5"><?= htmlspecialchars($d['dc_number'] ?? '') ?></td>
                                            <td class="py-1.5"><?= htmlspecialchars($d['dc_date'] ?? '') ?></td>
                                            <td class="py-1.5"><?= htmlspecialchars($d['invoice_number'] ?? '') ?></td>
                                            <td class="py-1.5"><?= htmlspecialchars($d['bill_date'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
