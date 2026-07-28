<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requirePermission('delivered_items', 'view');

// Every delivery that has been marked Delivered, joined with its PO
// so we can show PO context (customer, item, dates) alongside the
// DC / invoice details captured at the time of delivery.
$rows = $pdo->query(
    "SELECT d.id, d.quantity AS delivered_qty, d.due_date,
            d.dc_number, d.dc_date, d.invoice_number, d.bill_date,
            po.po_number, po.po_date, po.customer_name, po.item_code, po.description,
            po.total_quantity AS ordered_qty
     FROM deliveries d
     JOIN purchase_orders po ON po.id = d.po_id
     WHERE d.status = 'Delivered'
     ORDER BY COALESCE(d.dc_date, d.bill_date, d.due_date) DESC, d.id DESC"
)->fetchAll();

$pageTitle = 'Delivered Items';
include __DIR__ . '/includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h3 class="text-lg font-semibold text-brand-dark">All Delivered Items</h3>
            <input type="text" id="deliveredItemsSearch" placeholder="Search PO, customer, item, DC, invoice..." class="w-full sm:w-80 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
        </div>
        <div class="overflow-x-auto border border-slate-100 rounded-md">
            <table id="deliveredItems" class="w-full text-sm border-collapse">
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
                    <tr><td colspan="10" class="px-3 py-6 text-center text-slate-400">No delivered items yet.</td></tr>
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
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-xs text-slate-500">
            <div id="deliveredItemsInfo"></div>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5">
                    Show
                    <select id="deliveredItemsPageSize" class="px-2 py-1 border border-slate-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="all">All</option>
                    </select>
                    entries
                </label>
                <div class="flex gap-1">
                    <button type="button" id="deliveredItemsPrev" class="px-3 py-1 rounded-md border border-slate-300 font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                    <button type="button" id="deliveredItemsNext" class="px-3 py-1 rounded-md border border-slate-300 font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>
    </div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
