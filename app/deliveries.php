<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/activity_log.php';
requirePermission('deliveries', 'view');
$canEdit = hasPermission('deliveries', 'edit');

$message = '';
$error = '';

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['add_delivery'])) {
        $poId = (int)$_POST['po_id'];
        $dueDate = $_POST['due_date'];
        $qty = (int)$_POST['quantity'];

        if (!$poId || !$dueDate || $qty <= 0) {
            $error = 'PO, due date, and a positive quantity are required.';
        } else {
            $poStmt = $pdo->prepare('SELECT po_number, total_quantity FROM purchase_orders WHERE id = ?');
            $poStmt->execute([$poId]);
            $po = $poStmt->fetch();

            $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) AS total FROM deliveries WHERE po_id = ?');
            $sumStmt->execute([$poId]);
            $alreadyScheduled = (int)$sumStmt->fetch()['total'];

            if (!$po) {
                $error = 'Selected PO not found.';
            } elseif ($alreadyScheduled + $qty > (int)$po['total_quantity']) {
                $remaining = (int)$po['total_quantity'] - $alreadyScheduled;
                $error = "Cannot schedule $qty units — only $remaining of {$po['total_quantity']} remain unscheduled for this PO.";
            } else {
                $stmt = $pdo->prepare('INSERT INTO deliveries (po_id, due_date, quantity) VALUES (?, ?, ?)');
                $stmt->execute([$poId, $dueDate, $qty]);
                setFlashMessage('Delivery date added.');
                logActivity('add_delivery', "Added delivery due date $dueDate for PO {$po['po_number']} (qty: $qty).");
                header('Location: deliveries.php');
                exit;
            }
        }
    } elseif (isset($_POST['update_status'])) {
        $id = (int)$_POST['delivery_id'];
        $status = $_POST['status'];
        $allowedStatuses = ['Pending', 'Material Ready', 'Shipped', 'Delivered'];
        if (!in_array($status, $allowedStatuses, true)) {
            $error = 'Invalid status value.';
        } elseif ($status === 'Delivered') {
            $dcNumber = trim($_POST['dc_number'] ?? '');
            $invoiceNumber = trim($_POST['invoice_number'] ?? '');
            $dcDate = $_POST['dc_date'] ?? '';
            $billDate = $_POST['bill_date'] ?? '';

            $hasDcPair = $dcNumber !== '' && $dcDate !== '';
            $hasInvoicePair = $invoiceNumber !== '' && $billDate !== '';
            if (!$hasDcPair && !$hasInvoicePair) {
                $error = 'To mark as Delivered, provide either DC Number + DC Date or Invoice Number + Bill Date.';
            } else {
                $stmt = $pdo->prepare("UPDATE deliveries SET status = ?, dc_number = ?, invoice_number = ?, dc_date = ?, bill_date = ? WHERE id = ? AND status != 'Delivered'");
                $stmt->execute([
                    $status,
                    $dcNumber !== '' ? $dcNumber : null,
                    $invoiceNumber !== '' ? $invoiceNumber : null,
                    $dcDate !== '' ? $dcDate : null,
                    $billDate !== '' ? $billDate : null,
                    $id,
                ]);
                if ($stmt->rowCount() === 0) {
                    $error = 'This delivery is already marked Delivered and cannot be changed.';
                } else {
                    setFlashMessage('Status updated to Delivered.');
                    logActivity('update_delivery_status', "Marked delivery #$id as Delivered (DC: $dcNumber, Invoice: $invoiceNumber).");
                    header('Location: deliveries.php');
                    exit;
                }
            }
        } else {
            $stmt = $pdo->prepare("UPDATE deliveries SET status = ? WHERE id = ? AND status != 'Delivered'");
            $stmt->execute([$status, $id]);
            if ($stmt->rowCount() === 0) {
                $error = 'This delivery is already marked Delivered and cannot be changed.';
            } else {
                setFlashMessage('Status updated.');
                logActivity('update_delivery_status', "Updated delivery #$id status to $status.");
                header('Location: deliveries.php');
                exit;
            }
        }
    } elseif (isset($_POST['edit_delivery_details'])) {
        $id = (int)$_POST['delivery_id'];
        $dcNumber = trim($_POST['dc_number'] ?? '');
        $invoiceNumber = trim($_POST['invoice_number'] ?? '');
        $dcDate = $_POST['dc_date'] ?? '';
        $billDate = $_POST['bill_date'] ?? '';

        $hasDcPair = $dcNumber !== '' && $dcDate !== '';
        $hasInvoicePair = $invoiceNumber !== '' && $billDate !== '';
        if (!$hasDcPair && !$hasInvoicePair) {
            $error = 'At least one full pair (DC Number + DC Date, or Invoice Number + Bill Date) must remain filled.';
        } else {
            $stmt = $pdo->prepare("UPDATE deliveries SET dc_number = ?, invoice_number = ?, dc_date = ?, bill_date = ? WHERE id = ? AND status = 'Delivered'");
            $stmt->execute([
                $dcNumber !== '' ? $dcNumber : null,
                $invoiceNumber !== '' ? $invoiceNumber : null,
                $dcDate !== '' ? $dcDate : null,
                $billDate !== '' ? $billDate : null,
                $id,
            ]);
            if ($stmt->rowCount() === 0) {
                $error = 'That delivery could not be updated (it may not be marked Delivered).';
            } else {
                setFlashMessage('Delivery details updated.');
                logActivity('edit_delivery_details', "Updated details for delivery #$id (DC: $dcNumber, Invoice: $invoiceNumber).");
                header('Location: deliveries.php');
                exit;
            }
        }
    } elseif (isset($_POST['delete_delivery'])) {
        $id = (int)$_POST['delivery_id'];
        $poStmt = $pdo->prepare('SELECT po.po_number FROM deliveries d JOIN purchase_orders po ON po.id = d.po_id WHERE d.id = ?');
        $poStmt->execute([$id]);
        $poNumber = $poStmt->fetchColumn();
        $stmt = $pdo->prepare('DELETE FROM deliveries WHERE id = ?');
        $stmt->execute([$id]);
        setFlashMessage('Delivery entry deleted.');
        logActivity('delete_delivery', "Deleted delivery #$id for PO $poNumber.");
        header('Location: deliveries.php');
        exit;
    }

    // --------- BULK EDIT DETAILS (already-Delivered rows) ----------
    // Same shape as bulk_update_status but only touches DC / invoice
    // fields on rows that are ALREADY Delivered. Status is unchanged.
    elseif (isset($_POST['bulk_edit_details'])) {
        $ids = array_values(array_unique(array_map('intval', $_POST['delivery_ids'] ?? [])));
        $ids = array_filter($ids, fn($i) => $i > 0);
        if (empty($ids)) {
            $error = 'No deliveries selected.';
        } else {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $check = $pdo->prepare(
                "SELECT COUNT(DISTINCT po.po_number) AS po_count,
                        MIN(po.po_number) AS po_number,
                        SUM(CASE WHEN d.status = 'Delivered' THEN 1 ELSE 0 END) AS delivered_count
                 FROM deliveries d JOIN purchase_orders po ON po.id = d.po_id
                 WHERE d.id IN ($ph)"
            );
            $check->execute($ids);
            $info = $check->fetch();
            if ((int)$info['po_count'] !== 1) {
                $error = 'All selected deliveries must belong to the same PO.';
            } elseif ((int)$info['delivered_count'] !== count($ids)) {
                $error = 'Bulk edit only applies to already-Delivered rows.';
            } else {
                $dcNumber = trim($_POST['dc_number'] ?? '');
                $invoiceNumber = trim($_POST['invoice_number'] ?? '');
                $dcDate = $_POST['dc_date'] ?? '';
                $billDate = $_POST['bill_date'] ?? '';
                $hasDcPair = $dcNumber !== '' && $dcDate !== '';
                $hasInvoicePair = $invoiceNumber !== '' && $billDate !== '';
                if (!$hasDcPair && !$hasInvoicePair) {
                    $error = 'At least one full pair (DC Number + DC Date or Invoice Number + Bill Date) must be provided.';
                } else {
                    $upd = $pdo->prepare(
                        "UPDATE deliveries SET dc_number=?, invoice_number=?, dc_date=?, bill_date=?
                         WHERE id IN ($ph) AND status = 'Delivered'"
                    );
                    $params = [
                        $dcNumber !== '' ? $dcNumber : null,
                        $invoiceNumber !== '' ? $invoiceNumber : null,
                        $dcDate !== '' ? $dcDate : null,
                        $billDate !== '' ? $billDate : null,
                    ];
                    $upd->execute(array_merge($params, $ids));
                    setFlashMessage($upd->rowCount() . ' delivery row(s) had their DC/invoice details updated.');
                    logActivity('bulk_edit_delivery_details', 'Bulk-edited ' . $upd->rowCount() . " row(s) (PO {$info['po_number']}, DC: $dcNumber, Invoice: $invoiceNumber).");
                    header('Location: deliveries.php');
                    exit;
                }
            }
        }
    }

    // --------- BULK ACTIONS ----------
    // Selection scope is enforced server-side: every id in the batch
    // must belong to the same PO number. Prevents mixing customers.
    elseif (isset($_POST['bulk_update_status']) || isset($_POST['bulk_delete'])) {
        $ids = array_values(array_unique(array_map('intval', $_POST['delivery_ids'] ?? [])));
        $ids = array_filter($ids, fn($i) => $i > 0);
        if (empty($ids)) {
            $error = 'No deliveries selected.';
        } else {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $check = $pdo->prepare(
                "SELECT COUNT(DISTINCT po.po_number) AS po_count,
                        MIN(po.po_number) AS po_number,
                        SUM(CASE WHEN d.status = 'Delivered' THEN 1 ELSE 0 END) AS already_delivered
                 FROM deliveries d JOIN purchase_orders po ON po.id = d.po_id
                 WHERE d.id IN ($ph)"
            );
            $check->execute($ids);
            $info = $check->fetch();
            if ((int)$info['po_count'] !== 1) {
                $error = 'All selected deliveries must belong to the same PO.';
            } elseif (isset($_POST['bulk_delete'])) {
                if ((int)$info['already_delivered'] > 0) {
                    $error = 'Delivered rows cannot be deleted. Uncheck them and try again.';
                } else {
                    $del = $pdo->prepare("DELETE FROM deliveries WHERE id IN ($ph)");
                    $del->execute($ids);
                    setFlashMessage(count($ids) . ' delivery row(s) deleted.');
                    logActivity('bulk_delete_delivery', 'Bulk-deleted deliveries ' . implode(',', $ids) . " (PO {$info['po_number']}).");
                    header('Location: deliveries.php');
                    exit;
                }
            } else {
                // bulk_update_status
                $status = $_POST['bulk_status'] ?? '';
                $allowed = ['Pending', 'Material Ready', 'Shipped', 'Delivered'];
                if (!in_array($status, $allowed, true)) {
                    $error = 'Invalid status for bulk update.';
                } elseif ((int)$info['already_delivered'] > 0) {
                    $error = 'Selection includes already-Delivered rows. Uncheck them and try again.';
                } elseif ($status === 'Delivered') {
                    $dcNumber = trim($_POST['dc_number'] ?? '');
                    $invoiceNumber = trim($_POST['invoice_number'] ?? '');
                    $dcDate = $_POST['dc_date'] ?? '';
                    $billDate = $_POST['bill_date'] ?? '';
                    $hasDcPair = $dcNumber !== '' && $dcDate !== '';
                    $hasInvoicePair = $invoiceNumber !== '' && $billDate !== '';
                    if (!$hasDcPair && !$hasInvoicePair) {
                        $error = 'To mark as Delivered, provide either DC Number + DC Date or Invoice Number + Bill Date.';
                    } else {
                        // Same DC/invoice info applied to every selected row.
                        $upd = $pdo->prepare(
                            "UPDATE deliveries SET status='Delivered', dc_number=?, invoice_number=?, dc_date=?, bill_date=?
                             WHERE id IN ($ph) AND status != 'Delivered'"
                        );
                        $params = [
                            $dcNumber !== '' ? $dcNumber : null,
                            $invoiceNumber !== '' ? $invoiceNumber : null,
                            $dcDate !== '' ? $dcDate : null,
                            $billDate !== '' ? $billDate : null,
                        ];
                        $upd->execute(array_merge($params, $ids));
                        setFlashMessage($upd->rowCount() . ' delivery row(s) marked Delivered.');
                        logActivity('bulk_mark_delivered', 'Bulk-marked ' . $upd->rowCount() . " row(s) Delivered (PO {$info['po_number']}, DC: $dcNumber, Invoice: $invoiceNumber).");
                        header('Location: deliveries.php');
                        exit;
                    }
                } else {
                    $upd = $pdo->prepare("UPDATE deliveries SET status = ? WHERE id IN ($ph) AND status != 'Delivered'");
                    $upd->execute(array_merge([$status], $ids));
                    setFlashMessage($upd->rowCount() . " delivery row(s) updated to $status.");
                    logActivity('bulk_update_status', 'Bulk status ' . $status . ' on ' . $upd->rowCount() . " row(s) (PO {$info['po_number']}).");
                    header('Location: deliveries.php');
                    exit;
                }
            }
        }
    }
}

$pos = $canEdit ? $pdo->query(
    "SELECT po.id, po.po_number, po.customer_name, po.item_code, po.total_quantity,
            po.total_quantity - COALESCE(SUM(d.quantity), 0) AS remaining_quantity
     FROM purchase_orders po
     LEFT JOIN deliveries d ON d.po_id = po.id
     GROUP BY po.id, po.po_number, po.customer_name, po.item_code, po.total_quantity
     HAVING remaining_quantity > 0
     ORDER BY po.po_number"
)->fetchAll() : [];

$deliveries = $pdo->query(
    "SELECT d.*, po.po_number, po.customer_name, po.item_code, po.description, po.total_quantity AS po_total_quantity
     FROM deliveries d
     JOIN purchase_orders po ON po.id = d.po_id
     ORDER BY d.due_date ASC, po.po_number ASC, po.item_code ASC"
)->fetchAll();

// Two-level grouping so the table renders nested: an outer group per
// PO Number (+ Customer), inside which one or more item-code sub-groups
// cluster the actual delivery rows. A single PO Number can span
// multiple purchase_orders rows if that PO covers multiple item codes.
$deliveryOuters = [];
foreach ($deliveries as $d) {
    $outerKey = $d['po_number'];
    if (!isset($deliveryOuters[$outerKey])) {
        $deliveryOuters[$outerKey] = [
            'po_number' => $d['po_number'],
            'customer_name' => $d['customer_name'],
            'scheduled_qty' => 0,
            'delivered_qty' => 0,
            'po_total_quantity' => 0,
            'inner_groups' => [],
        ];
    }
    $innerKey = $d['po_id'];
    if (!isset($deliveryOuters[$outerKey]['inner_groups'][$innerKey])) {
        $deliveryOuters[$outerKey]['inner_groups'][$innerKey] = [
            'po_id' => $d['po_id'],
            'item_code' => $d['item_code'],
            'description' => $d['description'],
            'po_total_quantity' => (int)$d['po_total_quantity'],
            'scheduled_qty' => 0,
            'delivered_qty' => 0,
            'rows' => [],
        ];
        $deliveryOuters[$outerKey]['po_total_quantity'] += (int)$d['po_total_quantity'];
    }
    $deliveryOuters[$outerKey]['inner_groups'][$innerKey]['scheduled_qty'] += (int)$d['quantity'];
    $deliveryOuters[$outerKey]['scheduled_qty'] += (int)$d['quantity'];
    if ($d['status'] === 'Delivered') {
        $deliveryOuters[$outerKey]['inner_groups'][$innerKey]['delivered_qty'] += (int)$d['quantity'];
        $deliveryOuters[$outerKey]['delivered_qty'] += (int)$d['quantity'];
    }
    $deliveryOuters[$outerKey]['inner_groups'][$innerKey]['rows'][] = $d;
}

// Sort outer groups (and their inner groups) by earliest non-delivered
// due date, so the Pending/Shipped filter views show closest-first.
// Delivered-only groups sink to the end, ordered by their own earliest date.
$earliestPending = static function (array $rows): ?string {
    $min = null;
    foreach ($rows as $r) {
        if ($r['status'] === 'Delivered') continue;
        if ($min === null || $r['due_date'] < $min) $min = $r['due_date'];
    }
    return $min;
};
$earliestAny = static function (array $rows): string {
    $min = $rows[0]['due_date'];
    foreach ($rows as $r) {
        if ($r['due_date'] < $min) $min = $r['due_date'];
    }
    return $min;
};
$sortKey = static function (array $rows) use ($earliestPending, $earliestAny): string {
    $p = $earliestPending($rows);
    return $p !== null ? '0_' . $p : '1_' . $earliestAny($rows);
};
foreach ($deliveryOuters as &$outer) {
    foreach ($outer['inner_groups'] as &$inner) {
        $inner['_sort'] = $sortKey($inner['rows']);
    }
    unset($inner);
    uasort($outer['inner_groups'], static fn($a, $b) => strcmp($a['_sort'], $b['_sort']));
    $allRows = [];
    foreach ($outer['inner_groups'] as $inner) {
        foreach ($inner['rows'] as $r) $allRows[] = $r;
    }
    $outer['_sort'] = $sortKey($allRows);
}
unset($outer);
uasort($deliveryOuters, static fn($a, $b) => strcmp($a['_sort'], $b['_sort']));

// Bucket deliveries into time windows (for the Cards view) and status
// columns (for the Kanban view). Rows still carry all their filter data
// so the same JS filter logic works across views.
$todayObj = new DateTime('today');
$cardBuckets = [
    'overdue' => ['label' => 'Overdue', 'accent' => 'red', 'rows' => []],
    'this_week' => ['label' => 'Due this week', 'accent' => 'amber', 'rows' => []],
    'next_two_weeks' => ['label' => 'Due in next 2 weeks', 'accent' => 'blue', 'rows' => []],
    'later' => ['label' => 'Later', 'accent' => 'slate', 'rows' => []],
    'delivered' => ['label' => 'Delivered', 'accent' => 'green', 'rows' => []],
];
$kanbanBuckets = [
    'Pending' => ['label' => 'Pending', 'accent' => 'amber', 'rows' => []],
    'Material Ready' => ['label' => 'Material Ready', 'accent' => 'purple', 'rows' => []],
    'Shipped' => ['label' => 'Shipped', 'accent' => 'blue', 'rows' => []],
    'Delivered' => ['label' => 'Delivered', 'accent' => 'green', 'rows' => []],
];
foreach ($deliveries as $d) {
    $due = new DateTime($d['due_date']);
    $daysUntil = (int)$todayObj->diff($due)->format('%r%a');
    if ($d['status'] === 'Delivered') {
        $cardBuckets['delivered']['rows'][] = $d;
    } elseif ($daysUntil < 0) {
        $cardBuckets['overdue']['rows'][] = $d;
    } elseif ($daysUntil <= 7) {
        $cardBuckets['this_week']['rows'][] = $d;
    } elseif ($daysUntil <= 14) {
        $cardBuckets['next_two_weeks']['rows'][] = $d;
    } else {
        $cardBuckets['later']['rows'][] = $d;
    }
    if (isset($kanbanBuckets[$d['status']])) {
        $kanbanBuckets[$d['status']]['rows'][] = $d;
    }
}

$pageTitle = 'Delivery Schedule';
include __DIR__ . '/includes/layout_start.php';
?>
    <?php if ($canEdit): ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Add Delivery Due Date</h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center" id="addDeliveryForm">
                <?= csrfField() ?>
            <div class="relative po-search-wrap min-w-[260px]">
                <input type="text" id="poSearchInput" placeholder="Select PO Number" autocomplete="off" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <input type="hidden" name="po_id" id="poIdInput">
                <div id="poSearchResults" class="hidden absolute z-10 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-slate-300 rounded-md shadow-lg"></div>
            </div>
            <input type="date" name="due_date" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="number" name="quantity" placeholder="Quantity for this date" required class="px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green w-48">
            <button type="submit" name="add_delivery" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Add Delivery Date</button>
        </form>
    </div>
    <script>
        (function () {
            var poOptions = <?= json_encode(array_map(function ($po) {
                return [
                    'id' => $po['id'],
                    'label' => $po['po_number'] . ' - ' . $po['item_code'] . ' - ' . $po['customer_name'],
                ];
            }, $pos), JSON_UNESCAPED_SLASHES) ?>;

            var input = document.getElementById('poSearchInput');
            var hidden = document.getElementById('poIdInput');
            var results = document.getElementById('poSearchResults');

            function renderResults(filter) {
                var matches = poOptions.filter(function (po) {
                    return po.label.toLowerCase().indexOf(filter.toLowerCase()) !== -1;
                });
                results.innerHTML = '';
                if (matches.length === 0) {
                    results.classList.add('hidden');
                    return;
                }
                matches.forEach(function (po) {
                    var item = document.createElement('div');
                    item.textContent = po.label;
                    item.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-slate-100';
                    item.addEventListener('click', function () {
                        input.value = po.label;
                        hidden.value = po.id;
                        results.classList.add('hidden');
                    });
                    results.appendChild(item);
                });
                results.classList.remove('hidden');
            }

            input.addEventListener('focus', function () { renderResults(input.value); });
            input.addEventListener('input', function () {
                hidden.value = '';
                renderResults(input.value);
            });
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.po-search-wrap')) {
                    results.classList.add('hidden');
                }
            });
            document.getElementById('addDeliveryForm').addEventListener('submit', function (e) {
                if (!hidden.value) {
                    e.preventDefault();
                    input.focus();
                    results.classList.remove('hidden');
                    renderResults(input.value);
                }
            });
        })();
    </script>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div id="viewModeSwitcher" class="inline-flex rounded-md border border-slate-300 overflow-hidden text-sm">
                <button type="button" data-view="table" class="view-tab active px-3 py-1.5 font-semibold text-white bg-brand-green">Table</button>
                <button type="button" data-view="cards" class="view-tab px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50 border-l border-slate-300">Cards</button>
                <button type="button" data-view="kanban" class="view-tab px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50 border-l border-slate-300">Kanban</button>
            </div>
            <input type="text" id="deliverySearch" placeholder="Search PO, customer, item..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div class="flex flex-wrap items-center gap-2">
                <div id="deliveryFilterTabs" class="inline-flex rounded-md border border-slate-300 overflow-hidden text-sm">
                    <button type="button" data-filter="all" class="filter-tab active px-3 py-1.5 font-semibold text-white bg-brand-dark">All</button>
                    <button type="button" data-filter="Pending" class="filter-tab px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50 border-l border-slate-300">Pending</button>
                    <button type="button" data-filter="Material Ready" class="filter-tab px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50 border-l border-slate-300">Material Ready</button>
                    <button type="button" data-filter="Shipped" class="filter-tab px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50 border-l border-slate-300">Shipped</button>
                    <button type="button" data-filter="Delivered" class="filter-tab px-3 py-1.5 font-medium text-slate-600 hover:bg-slate-50 border-l border-slate-300">Delivered</button>
                </div>
                <label id="archiveToggleWrap" class="hidden flex items-center gap-1.5 text-xs text-slate-600 ml-2">
                    <input type="checkbox" id="includeArchive" class="rounded border-slate-300 text-brand-green focus:ring-brand-green">
                    Include archive (delivered 30+ days ago)
                </label>
            </div>
        </div>
        <div id="tableView">
        <div class="overflow-x-auto max-h-[65vh] overflow-y-auto border border-slate-100 rounded-md">
        <table class="w-full text-sm border-collapse">
            <?php
            // colspan for group-header rows: +1 for the leading checkbox column when the viewer can edit.
            $tableColCount = $canEdit ? 5 : 4;
            ?>
            <thead class="sticky top-0 z-10">
                <tr class="bg-brand-dark text-white">
                    <?php if ($canEdit): ?><th class="w-8 text-center px-2 py-2"></th><?php endif; ?>
                    <th class="text-left px-3 py-2 font-semibold">Due Date</th>
                    <th class="text-left px-3 py-2 font-semibold">Qty</th>
                    <th class="text-left px-3 py-2 font-semibold">Status</th>
                    <th class="text-left px-3 py-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody id="deliveryGroups">
            <?php
            $deliveryStatusBadge = [
                'Pending' => 'bg-amber-100 text-amber-800',
                'Material Ready' => 'bg-purple-100 text-purple-800',
                'Shipped' => 'bg-blue-100 text-blue-800',
                'Delivered' => 'bg-green-100 text-green-800',
            ];
            if (empty($deliveryOuters)):
            ?>
                <tr><td colspan="<?= $tableColCount ?>" class="px-3 py-6 text-center text-slate-400">No deliveries scheduled yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($deliveryOuters as $outer):
                $outerKeyAttr = htmlspecialchars($outer['po_number']);
                $outerItemCount = count($outer['inner_groups']);
            ?>
                <tr class="po-outer-header bg-brand-dark text-white border-t-2 border-brand-dark cursor-pointer" data-outer-key="<?= $outerKeyAttr ?>" onclick="toggleOuterGroup('<?= $outerKeyAttr ?>')">
                    <td colspan="<?= $tableColCount ?>" class="px-3 py-2">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="outer-chevron transition-transform inline-block w-3">▾</span>
                            <span class="font-semibold"><?= htmlspecialchars($outer['po_number']) ?></span>
                            <span class="text-white/60">·</span>
                            <span><?= htmlspecialchars($outer['customer_name']) ?></span>
                            <span class="ml-auto text-xs text-white/70">
                                <?= $outerItemCount ?> item<?= $outerItemCount === 1 ? '' : 's' ?> ·
                                <?= $outer['delivered_qty'] ?> delivered of <?= $outer['scheduled_qty'] ?> scheduled ·
                                PO total <?= $outer['po_total_quantity'] ?>
                            </span>
                        </div>
                    </td>
                </tr>
                <?php foreach ($outer['inner_groups'] as $group): ?>
                <tr class="po-group-header bg-slate-100 border-t border-b border-slate-300 cursor-pointer" data-group-id="<?= $group['po_id'] ?>" data-outer-key="<?= $outerKeyAttr ?>" onclick="togglePoGroup(<?= $group['po_id'] ?>)">
                    <td colspan="<?= $tableColCount ?>" class="px-3 py-2 pl-6">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="chevron transition-transform inline-block w-3">▾</span>
                            <span class="font-semibold text-brand-dark"><?= htmlspecialchars($group['item_code']) ?></span>
                            <span class="text-slate-400">—</span>
                            <span class="text-slate-700"><?= htmlspecialchars($group['description']) ?></span>
                            <span class="ml-auto text-xs text-slate-500">
                                <?= $group['delivered_qty'] ?> delivered of <?= $group['scheduled_qty'] ?> scheduled · PO total <?= $group['po_total_quantity'] ?>
                            </span>
                        </div>
                    </td>
                </tr>
                <?php foreach ($group['rows'] as $d):
                    $due = new DateTime($d['due_date']);
                    $today = new DateTime('today');
                    $diff = (int)$today->diff($due)->format('%r%a');
                    $rowClass = '';
                    if ($d['status'] !== 'Delivered' && $diff < 0) $rowClass = 'bg-red-50';
                    elseif ($d['status'] !== 'Delivered' && $diff <= 3) $rowClass = 'bg-amber-50';
                    $badgeClass = $deliveryStatusBadge[$d['status']] ?? 'bg-slate-100 text-slate-700';
                    $daysSinceDelivered = null;
                    if ($d['status'] === 'Delivered' && !empty($d['bill_date'])) {
                        $bill = new DateTime($d['bill_date']);
                        $daysSinceDelivered = (int)$bill->diff($today)->format('%r%a');
                    }
                ?>
                <tr class="delivery-row border-b border-slate-100 hover:bg-slate-50 <?= $rowClass ?>" data-group-id="<?= $group['po_id'] ?>" data-outer-key="<?= $outerKeyAttr ?>" data-status="<?= htmlspecialchars($d['status']) ?>" data-search="<?= htmlspecialchars(strtolower($d['po_number'] . ' ' . $d['customer_name'] . ' ' . $d['item_code'] . ' ' . $d['description'])) ?>" data-days-since-delivered="<?= $daysSinceDelivered ?? '' ?>" data-delivery-id="<?= (int)$d['id'] ?>">
                    <?php if ($canEdit): ?>
                    <td class="w-8 text-center px-2 py-2">
                        <input type="checkbox" class="bulk-select-checkbox rounded border-slate-300 text-brand-green focus:ring-brand-green" data-delivery-id="<?= (int)$d['id'] ?>" data-outer-key="<?= $outerKeyAttr ?>" data-status="<?= htmlspecialchars($d['status']) ?>">
                    </td>
                    <?php endif; ?>
                    <td class="px-3 py-2 pl-12"><?= htmlspecialchars($d['due_date']) ?></td>
                    <td class="px-3 py-2"><?= $d['quantity'] ?></td>
                    <?php if ($canEdit): ?>
                    <td class="px-3 py-2">
                        <form method="POST" style="display:inline-block; margin:0;">
                <?= csrfField() ?>
                            <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                            <select name="status" onchange="handleStatusChange(this)" data-delivery-id="<?= $d['id'] ?>" data-current-status="<?= htmlspecialchars($d['status']) ?>" <?= $d['status'] === 'Delivered' ? 'disabled title="Delivered deliveries cannot be changed"' : '' ?> class="px-2 py-1 border border-slate-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed">
                                <option value="Pending" <?= $d['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Material Ready" <?= $d['status'] === 'Material Ready' ? 'selected' : '' ?>>Material Ready</option>
                                <option value="Shipped" <?= $d['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="Delivered" <?= $d['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                            </select>
                            <input type="hidden" name="update_status" value="1">
                        </form>
                    </td>
                    <?php else: ?>
                    <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeClass ?>"><?= htmlspecialchars($d['status']) ?></span></td>
                    <?php endif; ?>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <?php if ($d['status'] === 'Delivered' && ($d['dc_number'] || $d['invoice_number'])): ?>
                            <button type="button" onclick="viewDeliveryDetails(<?= $d['id'] ?>)" title="View delivery details" class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer align-middle">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M10 3.5c-4.14 0-7.5 3.5-8.5 6.5 1 3 4.36 6.5 8.5 6.5s7.5-3.5 8.5-6.5c-1-3-4.36-6.5-8.5-6.5zm0 11a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/><circle cx="10" cy="10" r="2"/></svg>
                            </button>
                        <?php endif; ?>
                        <?php if ($canEdit): ?>
                        <form method="POST" onsubmit="return confirm('Delete this delivery date?');" style="display:inline-block; margin:0;">
                <?= csrfField() ?>
                            <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                            <button type="submit" name="delete_delivery" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-xs text-slate-500">
            <div id="deliveryFilterInfo"></div>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5">
                    Show
                    <select id="deliveryPageSize" class="px-2 py-1 border border-slate-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="all">All</option>
                    </select>
                    POs
                </label>
                <div class="flex gap-1">
                    <button type="button" id="deliveryPrev" class="px-3 py-1 rounded-md border border-slate-300 font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                    <button type="button" id="deliveryNext" class="px-3 py-1 rounded-md border border-slate-300 font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>
        </div>

        <?php
        // Shared renderer for a delivery card used by both the Cards view
        // (grouped by time window) and the Kanban view (grouped by status).
        $renderDeliveryCard = function ($d, $accent) use ($canEdit, $deliveryStatusBadge, $todayObj) {
            $due = new DateTime($d['due_date']);
            $diff = (int)$todayObj->diff($due)->format('%r%a');
            $accentBorder = 'border-l-4 border-l-' . $accent . '-400';
            $badgeClass = $deliveryStatusBadge[$d['status']] ?? 'bg-slate-100 text-slate-700';
            $searchable = htmlspecialchars(strtolower($d['po_number'] . ' ' . $d['customer_name'] . ' ' . $d['item_code'] . ' ' . $d['description']));
            $daysSinceDelivered = '';
            if ($d['status'] === 'Delivered' && !empty($d['bill_date'])) {
                $bill = new DateTime($d['bill_date']);
                $daysSinceDelivered = (int)$bill->diff($todayObj)->format('%r%a');
            }
            ob_start(); ?>
            <div class="delivery-card bg-white ring-1 ring-slate-200 <?= $accentBorder ?> rounded-md p-3 mb-2 hover:shadow-sm transition-shadow" data-status="<?= htmlspecialchars($d['status']) ?>" data-search="<?= $searchable ?>" data-days-since-delivered="<?= $daysSinceDelivered ?>">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <div class="text-sm font-semibold text-brand-dark truncate"><?= htmlspecialchars($d['po_number']) ?></div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0 <?= $badgeClass ?>"><?= htmlspecialchars($d['status']) ?></span>
                </div>
                <div class="text-xs text-slate-600 mb-1 truncate"><?= htmlspecialchars($d['customer_name']) ?></div>
                <div class="text-xs text-slate-500 mb-2 truncate"><?= htmlspecialchars($d['item_code']) ?> — <?= htmlspecialchars($d['description']) ?></div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-700"><span class="text-slate-400">Due</span> <?= htmlspecialchars($d['due_date']) ?></span>
                    <span class="text-slate-700"><span class="text-slate-400">Qty</span> <?= $d['quantity'] ?></span>
                </div>
                <?php if ($canEdit || ($d['status'] === 'Delivered' && ($d['dc_number'] || $d['invoice_number']))): ?>
                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-100">
                    <?php if ($canEdit): ?>
                    <form method="POST" style="margin:0;" class="flex-1">
                <?= csrfField() ?>
                        <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                        <select name="status" onchange="handleStatusChange(this)" data-delivery-id="<?= $d['id'] ?>" data-current-status="<?= htmlspecialchars($d['status']) ?>" <?= $d['status'] === 'Delivered' ? 'disabled title="Delivered deliveries cannot be changed"' : '' ?> class="w-full px-3 py-2.5 md:px-2 md:py-1 border border-slate-300 rounded-md text-sm md:text-xs focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed">
                            <option value="Pending" <?= $d['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Material Ready" <?= $d['status'] === 'Material Ready' ? 'selected' : '' ?>>Material Ready</option>
                            <option value="Shipped" <?= $d['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="Delivered" <?= $d['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                        </select>
                        <input type="hidden" name="update_status" value="1">
                    </form>
                    <?php endif; ?>
                    <?php if ($d['status'] === 'Delivered' && ($d['dc_number'] || $d['invoice_number'])): ?>
                        <button type="button" onclick="viewDeliveryDetails(<?= $d['id'] ?>)" title="View delivery details" class="inline-flex items-center justify-center w-10 h-10 md:w-7 md:h-7 rounded-md border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M10 3.5c-4.14 0-7.5 3.5-8.5 6.5 1 3 4.36 6.5 8.5 6.5s7.5-3.5 8.5-6.5c-1-3-4.36-6.5-8.5-6.5zm0 11a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/><circle cx="10" cy="10" r="2"/></svg>
                        </button>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                    <form method="POST" onsubmit="return confirm('Delete this delivery date?');" style="margin:0;">
                <?= csrfField() ?>
                        <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                        <button type="submit" name="delete_delivery" value="1" title="Delete" class="inline-flex items-center justify-center w-10 h-10 md:w-7 md:h-7 rounded-md bg-red-600 text-white hover:bg-red-700 transition-colors cursor-pointer shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5"><path d="M6 2a1 1 0 00-1 1v1H3a1 1 0 000 2h14a1 1 0 100-2h-2V3a1 1 0 00-1-1H6zm2 6a1 1 0 011 1v6a1 1 0 11-2 0V9a1 1 0 011-1zm4 0a1 1 0 011 1v6a1 1 0 11-2 0V9a1 1 0 011-1z"/></svg>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php return ob_get_clean();
        };
        ?>

        <div id="cardsView" class="hidden">
            <?php foreach ($cardBuckets as $bucketKey => $bucket): ?>
                <div class="delivery-bucket mb-5" data-bucket="<?= $bucketKey ?>">
                    <h3 class="text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2 cursor-pointer select-none" onclick="toggleBucket('<?= $bucketKey ?>')">
                        <span class="bucket-chevron transition-transform inline-block w-3 text-slate-500">▾</span>
                        <span class="inline-block w-2 h-2 rounded-full bg-<?= $bucket['accent'] ?>-500"></span>
                        <?= htmlspecialchars($bucket['label']) ?>
                        <span class="text-xs font-normal text-slate-400 bucket-count"></span>
                    </h3>
                    <div class="bucket-body">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <?php foreach ($bucket['rows'] as $d): echo $renderDeliveryCard($d, $bucket['accent']); endforeach; ?>
                        </div>
                        <div class="empty-bucket-msg hidden text-xs text-slate-400 italic mt-2">No deliveries in this bucket match your filters.</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="kanbanView" class="hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <?php foreach ($kanbanBuckets as $status => $bucket): ?>
                    <div class="kanban-column bg-slate-50 rounded-lg p-3" data-status-column="<?= $status ?>">
                        <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-<?= $bucket['accent'] ?>-500"></span>
                                <?= htmlspecialchars($bucket['label']) ?>
                            </span>
                            <span class="text-xs font-normal text-slate-400 column-count"></span>
                        </h3>
                        <div class="max-h-[65vh] overflow-y-auto">
                            <?php foreach ($bucket['rows'] as $d): echo $renderDeliveryCard($d, $bucket['accent']); endforeach; ?>
                        </div>
                        <div class="empty-column-msg hidden text-xs text-slate-400 italic mt-2 text-center">No cards match your filters.</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($canEdit): ?>
    <!-- Sticky bulk action bar: appears when any delivery row is selected.
         Selection is scoped to a single PO; picking a row from a different PO
         auto-clears the earlier selection (see JS below). -->
    <div id="bulkActionBar" class="hidden fixed bottom-0 left-0 right-0 md:left-60 z-40 bg-brand-dark text-white shadow-2xl px-4 py-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm">
                <span id="bulkCount" class="font-bold">0</span> selected in PO
                <span id="bulkPoLabel" class="font-semibold text-brand-green"></span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <!-- Actions for non-Delivered selections -->
                <span id="bulkPendingActions" class="hidden flex-wrap items-center gap-2 md:flex">
                    <select id="bulkStatusSelect" class="px-3 py-1.5 rounded-md text-slate-800 text-sm">
                        <option value="">Change status to...</option>
                        <option value="Pending">Pending</option>
                        <option value="Material Ready">Material Ready</option>
                        <option value="Shipped">Shipped</option>
                    </select>
                    <button type="button" id="bulkApplyStatusBtn" class="px-3 py-1.5 rounded-md bg-white text-brand-dark text-sm font-semibold hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed" disabled>Apply</button>
                    <button type="button" id="bulkMarkDeliveredBtn" class="px-3 py-1.5 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark">Mark Delivered</button>
                    <button type="button" id="bulkDeleteBtn" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-sm font-semibold hover:bg-red-700">Delete</button>
                </span>
                <!-- Actions for all-Delivered selections -->
                <span id="bulkDeliveredActions" class="hidden flex-wrap items-center gap-2 md:flex">
                    <button type="button" id="bulkEditDetailsBtn" class="px-3 py-1.5 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark">Edit DC / Invoice</button>
                </span>
                <!-- Shown when selection is mixed -->
                <span id="bulkMixedNotice" class="hidden text-xs text-amber-200 italic">Mixed selection — pick either all Delivered or all non-Delivered.</span>
                <button type="button" id="bulkClearBtn" class="px-3 py-1.5 rounded-md border border-white/40 text-sm font-medium hover:bg-white/10">Clear</button>
            </div>
        </div>
    </div>

    <!-- Bulk Delivered modal: same DC/invoice inputs, one shared value set
         applied to every selected row on submit. -->
    <div id="bulkDeliveredModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-5">
            <h3 class="text-lg font-semibold text-brand-dark mb-1">Mark <span id="bulkDeliveredCount">0</span> deliveries as Delivered</h3>
            <p class="text-sm text-slate-500 mb-3">Same DC / invoice info will be applied to every selected row. Provide either <strong>DC Number + DC Date</strong> or <strong>Invoice Number + Bill Date</strong> (or both).</p>
            <form method="POST" id="bulkDeliveredForm" onsubmit="return validateBulkDelivered()">
                <?= csrfField() ?>
                <input type="hidden" name="bulk_update_status" value="1">
                <input type="hidden" name="bulk_status" value="Delivered">
                <div id="bulkDeliveredIdInputs"></div>
                <div class="space-y-3 mb-4">
                    <div class="rounded-md border border-slate-200 p-3">
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">DC Info</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">DC Number</label>
                                <input type="text" name="dc_number" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">DC Date</label>
                                <input type="date" name="dc_date" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                        </div>
                    </div>
                    <div class="rounded-md border border-slate-200 p-3">
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Invoice / Bill Info</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Invoice Number</label>
                                <input type="text" name="invoice_number" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Bill Date</label>
                                <input type="date" name="bill_date" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                        </div>
                    </div>
                    <div id="bulkDeliveredError" class="hidden text-red-600 text-xs bg-red-50 border border-red-200 rounded-md px-3 py-2"></div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeBulkDeliveredModal()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Save &amp; Mark Delivered</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk status change confirmation modal: fires from the Apply button
         instead of a browser confirm() (which is often suppressed). -->
    <div id="bulkStatusConfirmModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-5">
            <h3 class="text-lg font-semibold text-brand-dark mb-2">Change status</h3>
            <p id="bulkStatusConfirmMsg" class="text-sm text-slate-600 mb-4"></p>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeBulkStatusConfirm()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="button" id="bulkStatusConfirmYes" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark">Change Status</button>
            </div>
        </div>
    </div>

    <!-- Bulk delete confirmation modal: same reason as above -- inline
         confirms are unreliable so we render our own. -->
    <div id="bulkDeleteConfirmModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-5">
            <h3 class="text-lg font-semibold text-brand-dark mb-2">Delete selected deliveries</h3>
            <p id="bulkDeleteConfirmMsg" class="text-sm text-slate-600 mb-4"></p>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeBulkDeleteConfirm()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="button" id="bulkDeleteConfirmYes" class="px-4 py-2 rounded-md bg-red-600 text-white text-sm font-semibold hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>

    <!-- Bulk edit DC/Invoice modal: only for selections where every row
         is already-Delivered. Same fields as the mark-delivered modal;
         status stays Delivered, only the detail fields get updated. -->
    <div id="bulkEditDetailsModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-5">
            <h3 class="text-lg font-semibold text-brand-dark mb-1">Edit DC / Invoice for <span id="bulkEditCount">0</span> deliveries</h3>
            <p class="text-sm text-slate-500 mb-3">These rows stay Delivered. Same values will be applied to all selected. At least one full pair (<strong>DC No + DC Date</strong> or <strong>Invoice No + Bill Date</strong>) must be filled.</p>
            <form method="POST" id="bulkEditDetailsForm" onsubmit="return validateBulkEdit()">
                <?= csrfField() ?>
                <input type="hidden" name="bulk_edit_details" value="1">
                <div id="bulkEditIdInputs"></div>
                <div class="space-y-3 mb-4">
                    <div class="rounded-md border border-slate-200 p-3">
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">DC Info</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">DC Number</label>
                                <input type="text" name="dc_number" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">DC Date</label>
                                <input type="date" name="dc_date" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                        </div>
                    </div>
                    <div class="rounded-md border border-slate-200 p-3">
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Invoice / Bill Info</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Invoice Number</label>
                                <input type="text" name="invoice_number" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Bill Date</label>
                                <input type="date" name="bill_date" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                        </div>
                    </div>
                    <div id="bulkEditError" class="hidden text-red-600 text-xs bg-red-50 border border-red-200 rounded-md px-3 py-2"></div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeBulkEditModal()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden form for bulk delete — submitted programmatically after
         confirming to avoid an inline form nested in the table. -->
    <form method="POST" id="bulkDeleteForm" class="hidden">
        <?= csrfField() ?>
        <input type="hidden" name="bulk_delete" value="1">
        <div id="bulkDeleteIdInputs"></div>
    </form>

    <div id="deliveryDetailsModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-5">
            <h3 class="text-lg font-semibold text-brand-dark mb-3">Delivery Details</h3>
            <p class="text-sm text-slate-500 mb-3">Provide either <strong>DC Number + DC Date</strong> or <strong>Invoice Number + Bill Date</strong> (or both) to mark this delivery as Delivered.</p>
            <form method="POST" id="deliveryDetailsForm" onsubmit="return validateDeliveryDetails()">
                <?= csrfField() ?>
                <input type="hidden" name="delivery_id" id="deliveryDetailsId">
                <input type="hidden" name="status" value="Delivered">
                <input type="hidden" name="update_status" value="1">
                <div class="space-y-3 mb-4">
                    <div class="rounded-md border border-slate-200 p-3">
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">DC Info</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">DC Number</label>
                                <input type="text" name="dc_number" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">DC Date</label>
                                <input type="date" name="dc_date" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                        </div>
                    </div>
                    <div class="rounded-md border border-slate-200 p-3">
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Invoice / Bill Info</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Invoice Number</label>
                                <input type="text" name="invoice_number" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Bill Date</label>
                                <input type="date" name="bill_date" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                            </div>
                        </div>
                    </div>
                    <div id="deliveryDetailsError" class="hidden text-red-600 text-xs bg-red-50 border border-red-200 rounded-md px-3 py-2"></div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeDeliveryDetailsModal(true)" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Save &amp; Mark Delivered</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div id="viewDetailsModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-5">
            <h3 class="text-lg font-semibold text-brand-dark mb-3">Delivery Details</h3>

            <div id="viewDetailsViewMode">
                <dl class="space-y-2 text-sm mb-4">
                    <div id="viewDcNumberRow" class="flex justify-between border-b border-slate-100 pb-2"><dt class="text-slate-500">DC Number</dt><dd id="viewDcNumber" class="font-semibold text-brand-dark"></dd></div>
                    <div id="viewDcDateRow" class="flex justify-between border-b border-slate-100 pb-2"><dt class="text-slate-500">DC Date</dt><dd id="viewDcDate" class="font-semibold text-brand-dark"></dd></div>
                    <div id="viewInvoiceNumberRow" class="flex justify-between border-b border-slate-100 pb-2"><dt class="text-slate-500">Invoice Number</dt><dd id="viewInvoiceNumber" class="font-semibold text-brand-dark"></dd></div>
                    <div id="viewBillDateRow" class="flex justify-between"><dt class="text-slate-500">Bill Date</dt><dd id="viewBillDate" class="font-semibold text-brand-dark"></dd></div>
                </dl>
                <div class="flex justify-end gap-2">
                    <?php if ($canEdit): ?>
                    <button type="button" onclick="switchToEditDetailsMode()" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Edit</button>
                    <?php endif; ?>
                    <button type="button" onclick="closeViewDetailsModal()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">Close</button>
                </div>
            </div>

            <?php if ($canEdit): ?>
            <div id="viewDetailsEditMode" class="hidden">
                <p class="text-sm text-slate-500 mb-3">Update DC or invoice info. At least one full pair (<strong>DC Number + DC Date</strong> or <strong>Invoice Number + Bill Date</strong>) must remain filled.</p>
                <form method="POST" id="editDetailsForm" onsubmit="return validateEditDetails()">
                    <?= csrfField() ?>
                    <input type="hidden" name="delivery_id" id="editDetailsId">
                    <input type="hidden" name="edit_delivery_details" value="1">
                    <div class="space-y-3 mb-4">
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">DC Info</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">DC Number</label>
                                    <input type="text" name="dc_number" id="editDcNumberInput" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">DC Date</label>
                                    <input type="date" name="dc_date" id="editDcDateInput" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                                </div>
                            </div>
                        </div>
                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Invoice / Bill Info</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">Invoice Number</label>
                                    <input type="text" name="invoice_number" id="editInvoiceNumberInput" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">Bill Date</label>
                                    <input type="date" name="bill_date" id="editBillDateInput" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                                </div>
                            </div>
                        </div>
                        <div id="editDetailsError" class="hidden text-red-600 text-xs bg-red-50 border border-red-200 rounded-md px-3 py-2"></div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="switchToViewDetailsMode()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Save Changes</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        var deliveryDetailsData = <?= json_encode(array_reduce($deliveries, function ($carry, $d) {
            if ($d['status'] === 'Delivered' && ($d['dc_number'] || $d['invoice_number'])) {
                $carry[$d['id']] = [
                    'dc_number' => $d['dc_number'],
                    'invoice_number' => $d['invoice_number'],
                    'dc_date' => $d['dc_date'],
                    'bill_date' => $d['bill_date'],
                ];
            }
            return $carry;
        }, []), JSON_UNESCAPED_SLASHES) ?>;

        <?php if ($canEdit): ?>
        var activeStatusSelect = null;

        function handleStatusChange(select) {
            if (select.value === 'Delivered') {
                activeStatusSelect = select;
                document.getElementById('deliveryDetailsId').value = select.dataset.deliveryId;
                document.getElementById('deliveryDetailsModal').classList.remove('hidden');
            } else {
                select.form.submit();
            }
        }

        function closeDeliveryDetailsModal(reset) {
            document.getElementById('deliveryDetailsModal').classList.add('hidden');
            document.getElementById('deliveryDetailsForm').reset();
            document.getElementById('deliveryDetailsError').classList.add('hidden');
            if (reset && activeStatusSelect) {
                activeStatusSelect.value = activeStatusSelect.dataset.currentStatus;
            }
            activeStatusSelect = null;
        }

        function validateDeliveryDetails() {
            var form = document.getElementById('deliveryDetailsForm');
            var dcNo = form.dc_number.value.trim();
            var dcDate = form.dc_date.value.trim();
            var invNo = form.invoice_number.value.trim();
            var billDate = form.bill_date.value.trim();
            var hasDc = dcNo !== '' && dcDate !== '';
            var hasInv = invNo !== '' && billDate !== '';
            var errEl = document.getElementById('deliveryDetailsError');
            if (!hasDc && !hasInv) {
                errEl.textContent = 'Please fill either DC Number + DC Date or Invoice Number + Bill Date.';
                errEl.classList.remove('hidden');
                return false;
            }
            errEl.classList.add('hidden');
            return true;
        }
        <?php endif; ?>

        var currentViewingDeliveryId = null;

        function viewDeliveryDetails(id) {
            var data = deliveryDetailsData[id];
            if (!data) return;
            currentViewingDeliveryId = id;
            var fields = [
                ['viewDcNumber', 'viewDcNumberRow', data.dc_number],
                ['viewDcDate', 'viewDcDateRow', data.dc_date],
                ['viewInvoiceNumber', 'viewInvoiceNumberRow', data.invoice_number],
                ['viewBillDate', 'viewBillDateRow', data.bill_date],
            ];
            fields.forEach(function (f) {
                var valEl = document.getElementById(f[0]);
                var rowEl = document.getElementById(f[1]);
                var val = f[2];
                if (val === null || val === undefined || val === '') {
                    rowEl.classList.add('hidden');
                    valEl.textContent = '';
                } else {
                    rowEl.classList.remove('hidden');
                    valEl.textContent = val;
                }
            });
            switchToViewDetailsMode();
            document.getElementById('viewDetailsModal').classList.remove('hidden');
        }

        function closeViewDetailsModal() {
            document.getElementById('viewDetailsModal').classList.add('hidden');
            switchToViewDetailsMode();
            currentViewingDeliveryId = null;
        }

        function switchToViewDetailsMode() {
            var viewSection = document.getElementById('viewDetailsViewMode');
            var editSection = document.getElementById('viewDetailsEditMode');
            if (viewSection) viewSection.classList.remove('hidden');
            if (editSection) {
                editSection.classList.add('hidden');
                var errEl = document.getElementById('editDetailsError');
                if (errEl) errEl.classList.add('hidden');
            }
        }

        function switchToEditDetailsMode() {
            if (currentViewingDeliveryId === null) return;
            var data = deliveryDetailsData[currentViewingDeliveryId];
            if (!data) return;
            document.getElementById('editDetailsId').value = currentViewingDeliveryId;
            document.getElementById('editDcNumberInput').value = data.dc_number || '';
            document.getElementById('editDcDateInput').value = data.dc_date || '';
            document.getElementById('editInvoiceNumberInput').value = data.invoice_number || '';
            document.getElementById('editBillDateInput').value = data.bill_date || '';
            document.getElementById('viewDetailsViewMode').classList.add('hidden');
            document.getElementById('viewDetailsEditMode').classList.remove('hidden');
            document.getElementById('editDetailsError').classList.add('hidden');
        }

        function validateEditDetails() {
            var form = document.getElementById('editDetailsForm');
            var dcNo = form.dc_number.value.trim();
            var dcDate = form.dc_date.value.trim();
            var invNo = form.invoice_number.value.trim();
            var billDate = form.bill_date.value.trim();
            var hasDc = dcNo !== '' && dcDate !== '';
            var hasInv = invNo !== '' && billDate !== '';
            var errEl = document.getElementById('editDetailsError');
            if (!hasDc && !hasInv) {
                errEl.textContent = 'At least one full pair (DC Number + DC Date or Invoice Number + Bill Date) must remain filled.';
                errEl.classList.remove('hidden');
                return false;
            }
            errEl.classList.add('hidden');
            return true;
        }

        // Filter tabs + search + archive toggle + collapsible PO groups.
        (function () {
            var currentFilter = 'all';
            var currentView = 'table';
            var searchInput = document.getElementById('deliverySearch');
            var archiveToggle = document.getElementById('includeArchive');
            var archiveToggleWrap = document.getElementById('archiveToggleWrap');
            var infoEl = document.getElementById('deliveryFilterInfo');
            var tabs = document.querySelectorAll('.filter-tab');
            var viewTabs = document.querySelectorAll('.view-tab');
            var pageSizeSelect = document.getElementById('deliveryPageSize');
            var prevBtn = document.getElementById('deliveryPrev');
            var nextBtn = document.getElementById('deliveryNext');
            var filterTabsWrap = document.getElementById('deliveryFilterTabs');
            var tableViewEl = document.getElementById('tableView');
            var cardsViewEl = document.getElementById('cardsView');
            var kanbanViewEl = document.getElementById('kanbanView');
            var collapsedGroups = {};
            var collapsedOuters = {};
            var currentPage = 1;

            // Shared filter predicate: whether a delivery card/row passes the
            // current status tab + search + archive toggle. Used by all three
            // views. For Kanban view, currentFilter is ignored — the columns
            // themselves are the status split.
            function rowPasses(el, ignoreStatusFilter) {
                var searchText = searchInput.value.trim().toLowerCase();
                var showArchive = archiveToggle.checked;
                var status = el.dataset.status;
                var searchable = el.dataset.search;
                var daysSinceDelivered = el.dataset.daysSinceDelivered;
                var passStatus = ignoreStatusFilter || currentFilter === 'all' || status === currentFilter;
                var passSearch = !searchText || searchable.indexOf(searchText) !== -1;
                var passArchive = true;
                if (!showArchive && status === 'Delivered' && daysSinceDelivered !== '' && parseInt(daysSinceDelivered, 10) >= 30) {
                    passArchive = false;
                }
                return passStatus && passSearch && passArchive;
            }

            function applyCardsFilter() {
                document.querySelectorAll('#cardsView .delivery-bucket').forEach(function (bucket) {
                    var visibleCount = 0;
                    bucket.querySelectorAll('.delivery-card').forEach(function (card) {
                        var passes = rowPasses(card, false);
                        card.style.display = passes ? '' : 'none';
                        if (passes) visibleCount++;
                    });
                    var countEl = bucket.querySelector('.bucket-count');
                    if (countEl) countEl.textContent = '(' + visibleCount + ')';
                    var emptyMsg = bucket.querySelector('.empty-bucket-msg');
                    if (emptyMsg) emptyMsg.classList.toggle('hidden', visibleCount > 0);
                });
            }

            function applyKanbanFilter() {
                document.querySelectorAll('#kanbanView .kanban-column').forEach(function (col) {
                    var visibleCount = 0;
                    // In Kanban, the column IS the status filter — ignore the tab
                    col.querySelectorAll('.delivery-card').forEach(function (card) {
                        var passes = rowPasses(card, true);
                        card.style.display = passes ? '' : 'none';
                        if (passes) visibleCount++;
                    });
                    var countEl = col.querySelector('.column-count');
                    if (countEl) countEl.textContent = visibleCount + ' item' + (visibleCount === 1 ? '' : 's');
                    var emptyMsg = col.querySelector('.empty-column-msg');
                    if (emptyMsg) emptyMsg.classList.toggle('hidden', visibleCount > 0);
                });
            }

            function applyFilters() {
                // Cards and Kanban always update their internal filter (cheap;
                // they're just show/hide of static DOM). Table view runs the
                // full pagination pass.
                applyCardsFilter();
                applyKanbanFilter();
                if (currentView === 'table') applyTableFilter();
                archiveToggleWrap.classList.toggle('hidden', currentFilter === 'Pending' || currentFilter === 'Material Ready' || currentFilter === 'Shipped');
            }

            function applyTableFilter() {
                var searchText = searchInput.value.trim().toLowerCase();
                var showArchive = archiveToggle.checked;
                var visibleByInner = {};   // po_id -> count of passing rows
                var visibleByOuter = {};   // po_number -> count of passing rows

                // Pass 1: which rows pass filters?
                var passesFilterByRow = new Map();
                document.querySelectorAll('.delivery-row').forEach(function (row) {
                    var status = row.dataset.status;
                    var searchable = row.dataset.search;
                    var innerKey = row.dataset.groupId;
                    var outerKey = row.dataset.outerKey;
                    var daysSinceDelivered = row.dataset.daysSinceDelivered;
                    var passStatus = currentFilter === 'all' || status === currentFilter;
                    var passSearch = !searchText || searchable.indexOf(searchText) !== -1;
                    var passArchive = true;
                    if (!showArchive && status === 'Delivered' && daysSinceDelivered !== '' && parseInt(daysSinceDelivered, 10) >= 30) {
                        passArchive = false;
                    }
                    var passes = passStatus && passSearch && passArchive;
                    passesFilterByRow.set(row, passes);
                    if (passes) {
                        visibleByInner[innerKey] = (visibleByInner[innerKey] || 0) + 1;
                        visibleByOuter[outerKey] = (visibleByOuter[outerKey] || 0) + 1;
                    }
                });

                // Pass 2: paginate by outer group (PO Number).
                var eligibleOuterKeys = [];
                document.querySelectorAll('.po-outer-header').forEach(function (header) {
                    var key = header.dataset.outerKey;
                    if ((visibleByOuter[key] || 0) > 0) eligibleOuterKeys.push(key);
                });

                var pageSizeVal = pageSizeSelect.value;
                var pageSize = pageSizeVal === 'all' ? eligibleOuterKeys.length : parseInt(pageSizeVal, 10);
                var totalPages = pageSize > 0 ? Math.max(1, Math.ceil(eligibleOuterKeys.length / pageSize)) : 1;
                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;
                var startIdx = pageSize > 0 ? (currentPage - 1) * pageSize : 0;
                var endIdx = pageSize > 0 ? startIdx + pageSize : eligibleOuterKeys.length;
                var pageOuterKeys = new Set(eligibleOuterKeys.slice(startIdx, endIdx));

                // Pass 3: apply visibility across three levels (outer, inner, row).
                var totalVisibleRows = 0;
                document.querySelectorAll('.po-outer-header').forEach(function (header) {
                    var key = header.dataset.outerKey;
                    header.style.display = pageOuterKeys.has(key) ? '' : 'none';
                    if (pageOuterKeys.has(key)) totalVisibleRows += visibleByOuter[key] || 0;
                });
                document.querySelectorAll('.po-group-header').forEach(function (header) {
                    var innerKey = header.dataset.groupId;
                    var outerKey = header.dataset.outerKey;
                    var onPage = pageOuterKeys.has(outerKey);
                    var outerCollapsed = !!collapsedOuters[outerKey];
                    var hasContent = (visibleByInner[innerKey] || 0) > 0;
                    header.style.display = (onPage && !outerCollapsed && hasContent) ? '' : 'none';
                });
                passesFilterByRow.forEach(function (passes, row) {
                    var innerKey = row.dataset.groupId;
                    var outerKey = row.dataset.outerKey;
                    var onPage = pageOuterKeys.has(outerKey);
                    var outerCollapsed = !!collapsedOuters[outerKey];
                    var innerCollapsed = !!collapsedGroups[innerKey];
                    row.style.display = (passes && onPage && !outerCollapsed && !innerCollapsed) ? '' : 'none';
                });

                var pageInfo = eligibleOuterKeys.length === 0
                    ? 'No deliveries match this view.'
                    : 'Showing POs ' + (startIdx + 1) + '–' + Math.min(endIdx, eligibleOuterKeys.length)
                        + ' of ' + eligibleOuterKeys.length
                        + ' (' + totalVisibleRows + ' deliverie' + (totalVisibleRows === 1 ? '' : 's') + ' on this page)';
                infoEl.textContent = pageInfo;

                prevBtn.disabled = currentPage <= 1;
                nextBtn.disabled = currentPage >= totalPages;
            }

            function setActiveView(view) {
                currentView = view;
                tableViewEl.classList.toggle('hidden', view !== 'table');
                cardsViewEl.classList.toggle('hidden', view !== 'cards');
                kanbanViewEl.classList.toggle('hidden', view !== 'kanban');
                // Filter tabs make sense for Table + Cards but not Kanban
                // (columns ARE the status split). Hide the tabs on Kanban.
                filterTabsWrap.classList.toggle('hidden', view === 'kanban');
                viewTabs.forEach(function (t) {
                    var isActive = t.dataset.view === view;
                    t.classList.toggle('active', isActive);
                    t.classList.toggle('bg-brand-green', isActive);
                    t.classList.toggle('text-white', isActive);
                    t.classList.toggle('font-semibold', isActive);
                    t.classList.toggle('text-slate-600', !isActive);
                    t.classList.toggle('font-medium', !isActive);
                });
                applyFilters();
            }

            viewTabs.forEach(function (tab) {
                tab.addEventListener('click', function () { setActiveView(tab.dataset.view); });
            });

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabs.forEach(function (t) {
                        t.classList.remove('active', 'bg-brand-dark', 'text-white', 'font-semibold');
                        t.classList.add('text-slate-600', 'font-medium');
                    });
                    tab.classList.add('active', 'bg-brand-dark', 'text-white', 'font-semibold');
                    tab.classList.remove('text-slate-600', 'font-medium');
                    currentFilter = tab.dataset.filter;
                    currentPage = 1;
                    applyFilters();
                });
            });

            searchInput.addEventListener('input', function () { currentPage = 1; applyFilters(); });
            archiveToggle.addEventListener('change', function () { currentPage = 1; applyFilters(); });
            pageSizeSelect.addEventListener('change', function () { currentPage = 1; applyFilters(); });
            prevBtn.addEventListener('click', function () { currentPage--; applyFilters(); });
            nextBtn.addEventListener('click', function () { currentPage++; applyFilters(); });

            window.togglePoGroup = function (groupId) {
                collapsedGroups[groupId] = !collapsedGroups[groupId];
                var chevron = document.querySelector('.po-group-header[data-group-id="' + groupId + '"] .chevron');
                if (chevron) chevron.style.transform = collapsedGroups[groupId] ? 'rotate(-90deg)' : '';
                applyFilters();
            };

            window.toggleOuterGroup = function (outerKey) {
                collapsedOuters[outerKey] = !collapsedOuters[outerKey];
                var attr = outerKey.replace(/"/g, '\\"');
                var chevron = document.querySelector('.po-outer-header[data-outer-key="' + attr + '"] .outer-chevron');
                if (chevron) chevron.style.transform = collapsedOuters[outerKey] ? 'rotate(-90deg)' : '';
                applyFilters();
            };

            var collapsedBuckets = {};
            window.toggleBucket = function (bucketKey) {
                collapsedBuckets[bucketKey] = !collapsedBuckets[bucketKey];
                var bucket = document.querySelector('.delivery-bucket[data-bucket="' + bucketKey + '"]');
                if (!bucket) return;
                var body = bucket.querySelector('.bucket-body');
                var chev = bucket.querySelector('.bucket-chevron');
                if (body) body.classList.toggle('hidden', collapsedBuckets[bucketKey]);
                if (chev) chev.style.transform = collapsedBuckets[bucketKey] ? 'rotate(-90deg)' : '';
            };

            // On mobile (< md breakpoint) the Table view is unusable due to
            // 4 columns of nested rows and small tap targets. Default to
            // Cards view on small screens. Users can still switch manually;
            // the table view isn't hidden, just not the initial choice.
            if (window.matchMedia('(max-width: 767px)').matches) {
                setActiveView('cards');
            }

            applyFilters();
        })();
    </script>

    <?php if ($canEdit): ?>
    <script>
    // Bulk-actions module. Selection is scoped to ONE PO -- picking a row
    // from a different PO auto-clears the previous selection so the
    // shared DC/invoice info always applies to a single customer.
    (function () {
        // Map: id -> status. Lets the bar figure out if the selection is all
        // Delivered / all non-Delivered / mixed and swap actions accordingly.
        var selected = new Map();
        var currentOuterKey = null;

        var bar = document.getElementById('bulkActionBar');
        var countEl = document.getElementById('bulkCount');
        var poLabel = document.getElementById('bulkPoLabel');
        var statusSelect = document.getElementById('bulkStatusSelect');
        var applyBtn = document.getElementById('bulkApplyStatusBtn');
        var deliveredBtn = document.getElementById('bulkMarkDeliveredBtn');
        var deleteBtn = document.getElementById('bulkDeleteBtn');
        var editBtn = document.getElementById('bulkEditDetailsBtn');
        var clearBtn = document.getElementById('bulkClearBtn');
        var pendingActions = document.getElementById('bulkPendingActions');
        var deliveredActions = document.getElementById('bulkDeliveredActions');
        var mixedNotice = document.getElementById('bulkMixedNotice');

        function selectionKind() {
            var deliv = 0, other = 0;
            selected.forEach(function (status) {
                if (status === 'Delivered') deliv++; else other++;
            });
            if (deliv > 0 && other > 0) return 'mixed';
            if (deliv > 0) return 'delivered';
            return 'pending';
        }

        function refreshBar() {
            countEl.textContent = selected.size;
            poLabel.textContent = currentOuterKey || '';
            bar.classList.toggle('hidden', selected.size === 0);
            var kind = selectionKind();
            pendingActions.classList.toggle('hidden', kind !== 'pending');
            pendingActions.classList.toggle('flex', kind === 'pending');
            deliveredActions.classList.toggle('hidden', kind !== 'delivered');
            deliveredActions.classList.toggle('flex', kind === 'delivered');
            mixedNotice.classList.toggle('hidden', kind !== 'mixed');
        }

        function clearSelection() {
            selected.clear();
            currentOuterKey = null;
            document.querySelectorAll('.bulk-select-checkbox:checked').forEach(function (cb) {
                cb.checked = false;
                setRowControlsDisabled(cb.closest('tr'), false);
            });
            statusSelect.value = '';
            applyBtn.disabled = true;
            refreshBar();
        }

        // Toggle inline row controls (status dropdown, delete button) so
        // they can't fire while a checkbox in that row is selected. Prevents
        // accidental collision between bulk action and per-row action.
        function setRowControlsDisabled(row, disabled) {
            if (!row) return;
            row.classList.toggle('bg-brand-green/10', disabled);
            row.querySelectorAll('select[name="status"], button[name="delete_delivery"]').forEach(function (el) {
                el.disabled = disabled;
                if (disabled) el.setAttribute('data-was-editable', '1');
            });
        }

        document.addEventListener('change', function (e) {
            var cb = e.target;
            if (!cb.classList || !cb.classList.contains('bulk-select-checkbox')) return;
            var id = parseInt(cb.dataset.deliveryId, 10);
            var outerKey = cb.dataset.outerKey;
            var status = cb.dataset.status;
            var row = cb.closest('tr');
            if (cb.checked) {
                if (currentOuterKey && currentOuterKey !== outerKey) {
                    clearSelection();
                    cb.checked = true;
                }
                currentOuterKey = outerKey;
                selected.set(id, status);
                setRowControlsDisabled(row, true);
            } else {
                selected.delete(id);
                if (selected.size === 0) currentOuterKey = null;
                setRowControlsDisabled(row, false);
            }
            refreshBar();
        });

        statusSelect.addEventListener('change', function () {
            applyBtn.disabled = statusSelect.value === '';
        });

        clearBtn.addEventListener('click', clearSelection);

        function buildAndSubmitStatusForm() {
            var f = document.createElement('form');
            f.method = 'POST';
            f.style.display = 'none';
            var csrf = document.querySelector('input[name="_csrf"]');
            f.appendChild(csrf.cloneNode(true));
            var flag = document.createElement('input');
            flag.type = 'hidden'; flag.name = 'bulk_update_status'; flag.value = '1';
            f.appendChild(flag);
            var st = document.createElement('input');
            st.type = 'hidden'; st.name = 'bulk_status'; st.value = statusSelect.value;
            f.appendChild(st);
            selected.forEach(function (_status, id) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = 'delivery_ids[]'; i.value = id;
                f.appendChild(i);
            });
            document.body.appendChild(f);
            f.submit();
        }

        var statusConfirm = document.getElementById('bulkStatusConfirmModal');
        var statusConfirmMsg = document.getElementById('bulkStatusConfirmMsg');
        var statusConfirmYes = document.getElementById('bulkStatusConfirmYes');
        window.closeBulkStatusConfirm = function () { statusConfirm.classList.add('hidden'); };
        statusConfirmYes.addEventListener('click', function () {
            closeBulkStatusConfirm();
            buildAndSubmitStatusForm();
        });
        applyBtn.addEventListener('click', function () {
            if (selected.size === 0 || statusSelect.value === '') return;
            statusConfirmMsg.textContent = 'Change ' + selected.size + ' delivery row(s) to "' + statusSelect.value + '"?';
            statusConfirm.classList.remove('hidden');
        });

        deliveredBtn.addEventListener('click', function () {
            if (selected.size === 0) return;
            var wrap = document.getElementById('bulkDeliveredIdInputs');
            wrap.innerHTML = '';
            selected.forEach(function (_status, id) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = 'delivery_ids[]'; i.value = id;
                wrap.appendChild(i);
            });
            document.getElementById('bulkDeliveredCount').textContent = selected.size;
            document.getElementById('bulkDeliveredError').classList.add('hidden');
            document.getElementById('bulkDeliveredForm').reset();
            // re-populate ids (reset() wiped nothing here since ids are in a separate div, but be safe)
            selected.forEach(function (id) {
                if (!wrap.querySelector('input[value="' + id + '"]')) {
                    var i = document.createElement('input');
                    i.type = 'hidden'; i.name = 'delivery_ids[]'; i.value = id;
                    wrap.appendChild(i);
                }
            });
            document.getElementById('bulkDeliveredModal').classList.remove('hidden');
        });

        editBtn.addEventListener('click', function () {
            if (selected.size === 0) return;
            var wrap = document.getElementById('bulkEditIdInputs');
            wrap.innerHTML = '';
            selected.forEach(function (_status, id) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = 'delivery_ids[]'; i.value = id;
                wrap.appendChild(i);
            });
            document.getElementById('bulkEditCount').textContent = selected.size;
            document.getElementById('bulkEditError').classList.add('hidden');
            document.getElementById('bulkEditDetailsForm').reset();
            selected.forEach(function (_status, id) {
                if (!wrap.querySelector('input[value="' + id + '"]')) {
                    var i = document.createElement('input');
                    i.type = 'hidden'; i.name = 'delivery_ids[]'; i.value = id;
                    wrap.appendChild(i);
                }
            });
            document.getElementById('bulkEditDetailsModal').classList.remove('hidden');
        });

        window.closeBulkEditModal = function () {
            document.getElementById('bulkEditDetailsModal').classList.add('hidden');
        };
        window.validateBulkEdit = function () {
            var f = document.getElementById('bulkEditDetailsForm');
            var hasDc = f.dc_number.value.trim() !== '' && f.dc_date.value.trim() !== '';
            var hasInv = f.invoice_number.value.trim() !== '' && f.bill_date.value.trim() !== '';
            var err = document.getElementById('bulkEditError');
            if (!hasDc && !hasInv) {
                err.textContent = 'Please fill either DC Number + DC Date or Invoice Number + Bill Date.';
                err.classList.remove('hidden');
                return false;
            }
            err.classList.add('hidden');
            return true;
        };

        var delConfirm = document.getElementById('bulkDeleteConfirmModal');
        var delConfirmMsg = document.getElementById('bulkDeleteConfirmMsg');
        var delConfirmYes = document.getElementById('bulkDeleteConfirmYes');
        window.closeBulkDeleteConfirm = function () { delConfirm.classList.add('hidden'); };
        delConfirmYes.addEventListener('click', function () {
            closeBulkDeleteConfirm();
            var wrap = document.getElementById('bulkDeleteIdInputs');
            wrap.innerHTML = '';
            selected.forEach(function (_status, id) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = 'delivery_ids[]'; i.value = id;
                wrap.appendChild(i);
            });
            document.getElementById('bulkDeleteForm').submit();
        });
        deleteBtn.addEventListener('click', function () {
            if (selected.size === 0) return;
            delConfirmMsg.textContent = 'Delete ' + selected.size + ' delivery row(s)? This cannot be undone.';
            delConfirm.classList.remove('hidden');
        });

        window.closeBulkDeliveredModal = function () {
            document.getElementById('bulkDeliveredModal').classList.add('hidden');
        };
        window.validateBulkDelivered = function () {
            var f = document.getElementById('bulkDeliveredForm');
            var hasDc = f.dc_number.value.trim() !== '' && f.dc_date.value.trim() !== '';
            var hasInv = f.invoice_number.value.trim() !== '' && f.bill_date.value.trim() !== '';
            var err = document.getElementById('bulkDeliveredError');
            if (!hasDc && !hasInv) {
                err.textContent = 'Please fill either DC Number + DC Date or Invoice Number + Bill Date.';
                err.classList.remove('hidden');
                return false;
            }
            err.classList.add('hidden');
            return true;
        };
    })();
    </script>
    <?php endif; ?>

<?php include __DIR__ . '/includes/layout_end.php'; ?>
