<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
requirePermission('suppliers', 'view');

$supplierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$supplier = null;
if ($supplierId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch();
}

$restocks = [];
$stats = ['total' => 0, 'confirmed' => 0, 'open' => 0, 'received_qty' => 0];

if ($supplier) {
    $stmt = $pdo->prepare(
        'SELECT * FROM restock_orders
         WHERE supplier_id = ?
         ORDER BY created_at DESC'
    );
    $stmt->execute([$supplierId]);
    $restocks = $stmt->fetchAll();

    $s = $pdo->prepare(
        "SELECT
           COUNT(*) AS total,
           SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed,
           SUM(CASE WHEN status IN ('Pending','Purchased') THEN 1 ELSE 0 END) AS open_count,
           COALESCE(SUM(CASE WHEN status = 'Confirmed' THEN received_quantity ELSE 0 END), 0) AS received_qty
         FROM restock_orders
         WHERE supplier_id = ?"
    );
    $s->execute([$supplierId]);
    $agg = $s->fetch();
    $stats['total']        = (int)$agg['total'];
    $stats['confirmed']    = (int)$agg['confirmed'];
    $stats['open']         = (int)$agg['open_count'];
    $stats['received_qty'] = (int)$agg['received_qty'];
}

$statusBadge = [
    'Pending'   => 'bg-amber-100 text-amber-800',
    'Purchased' => 'bg-blue-100 text-blue-800',
    'Confirmed' => 'bg-green-100 text-green-800',
    'Cancelled' => 'bg-slate-200 text-slate-600',
];

$pageTitle = 'Supplier Detail';
$pageHeading = $supplier ? $supplier['name'] : 'Supplier Detail';
include __DIR__ . '/includes/layout_start.php';
?>
<?php if (!$supplier): ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <p class="text-slate-600">That supplier was not found. <a href="suppliers.php" class="text-brand-green hover:underline">Back to Suppliers</a>.</p>
    </div>
<?php else: ?>
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
        <a href="suppliers.php" class="text-sm text-brand-green hover:underline">&larr; Back to Suppliers</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Contact</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
            <div><div class="text-xs text-slate-500">Contact Person</div><div><?= htmlspecialchars($supplier['contact_person'] ?? '—') ?></div></div>
            <div><div class="text-xs text-slate-500">Phone</div><div><?= htmlspecialchars($supplier['phone'] ?? '—') ?></div></div>
            <div><div class="text-xs text-slate-500">Email</div><div><?= htmlspecialchars($supplier['email'] ?? '—') ?></div></div>
            <div><div class="text-xs text-slate-500">Address</div><div><?= htmlspecialchars($supplier['address'] ?? '—') ?></div></div>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4">
            <div class="text-xs text-slate-500">Restock Orders</div>
            <div class="text-2xl font-bold text-brand-dark"><?= $stats['total'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4">
            <div class="text-xs text-slate-500">Confirmed</div>
            <div class="text-2xl font-bold text-green-700"><?= $stats['confirmed'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4 <?= $stats['open'] > 0 ? 'ring-amber-300' : '' ?>">
            <div class="text-xs text-slate-500">Open (Pending / Purchased)</div>
            <div class="text-2xl font-bold <?= $stats['open'] > 0 ? 'text-amber-700' : 'text-slate-500' ?>"><?= $stats['open'] ?></div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-4">
            <div class="text-xs text-slate-500">Total Qty Received</div>
            <div class="text-2xl font-bold text-brand-dark"><?= $stats['received_qty'] ?></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Restock Orders</h3>
        <?php if (empty($restocks)): ?>
            <p class="text-sm text-slate-500">This supplier has no restock orders yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-brand-dark text-white">
                        <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Created</th>
                        <th class="text-left px-3 py-2 font-semibold">Product</th>
                        <th class="text-left px-3 py-2 font-semibold">Qty Ordered</th>
                        <th class="text-left px-3 py-2 font-semibold">Status</th>
                        <th class="text-left px-3 py-2 font-semibold">Qty Received</th>
                        <th class="text-left px-3 py-2 font-semibold rounded-tr-md">Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($restocks as $r):
                    $badge = $statusBadge[$r['status']] ?? 'bg-slate-100 text-slate-700';
                ?>
                    <tr class="border-b border-slate-100 even:bg-slate-50">
                        <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($r['created_at']) ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['product_name']) ?></td>
                        <td class="px-3 py-2"><?= (int)$r['quantity'] ?></td>
                        <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $badge ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                        <td class="px-3 py-2"><?= $r['received_quantity'] !== null ? (int)$r['received_quantity'] : '—' ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($r['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
