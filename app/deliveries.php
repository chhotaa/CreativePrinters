<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/activity_log.php';
require_once __DIR__ . '/includes/attachments.php';
requirePermission('deliveries', 'view');
$canEdit = hasPermission('deliveries', 'edit');

$message = '';
$error = '';

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $allowedStatuses = ['Pending', 'Shipped', 'Delivered'];
        if (!in_array($status, $allowedStatuses, true)) {
            $error = 'Invalid status value.';
        } elseif ($status === 'Delivered') {
            $dcNumber = trim($_POST['dc_number'] ?? '');
            $invoiceNumber = trim($_POST['invoice_number'] ?? '');
            $dcDate = $_POST['dc_date'] ?? '';
            $billDate = $_POST['bill_date'] ?? '';

            if ($dcNumber === '' || $invoiceNumber === '' || $dcDate === '' || $billDate === '') {
                $error = 'DC Number, Invoice Number, DC Date, and Bill Date are all required to mark a delivery as Delivered.';
            } else {
                $stmt = $pdo->prepare("UPDATE deliveries SET status = ?, dc_number = ?, invoice_number = ?, dc_date = ?, bill_date = ? WHERE id = ? AND status != 'Delivered'");
                $stmt->execute([$status, $dcNumber, $invoiceNumber, $dcDate, $billDate, $id]);
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
    } elseif (isset($_POST['upload_attachment'])) {
        $id = (int)$_POST['delivery_id'];
        $uploadError = saveAttachment('delivery', $id, $_FILES['attachment'] ?? []);
        if ($uploadError) {
            $error = $uploadError;
        } else {
            setFlashMessage('Attachment uploaded.');
            logActivity('upload_attachment', "Uploaded attachment \"{$_FILES['attachment']['name']}\" to delivery #$id.");
            header('Location: deliveries.php');
            exit;
        }
    } elseif (isset($_POST['delete_attachment'])) {
        $attachmentId = (int)$_POST['attachment_id'];
        if (deleteAttachment($attachmentId)) {
            setFlashMessage('Attachment deleted.');
            logActivity('delete_attachment', "Deleted attachment #$attachmentId from a delivery.");
        }
        header('Location: deliveries.php');
        exit;
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
    "SELECT d.*, po.po_number, po.customer_name, po.item_code, po.description
     FROM deliveries d
     JOIN purchase_orders po ON po.id = d.po_id
     ORDER BY d.due_date ASC"
)->fetchAll();

$deliveryAttachments = [];
$allAttachments = $pdo->query("SELECT * FROM attachments WHERE record_type = 'delivery' ORDER BY uploaded_at DESC")->fetchAll();
foreach ($allAttachments as $a) {
    $deliveryAttachments[$a['record_id']][] = $a;
}

$pageTitle = 'Delivery Schedule';
include __DIR__ . '/includes/layout_start.php';
?>
    <?php if ($canEdit): ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3">Add Delivery Due Date</h3>
        <form method="POST" class="flex flex-wrap gap-2 items-center" id="addDeliveryForm">
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
            <input type="text" id="deliveriesTableSearch" placeholder="Search deliveries..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                Show
                <select id="deliveriesTablePageSize" class="px-2 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="all">All</option>
                </select>
                entries
            </label>
        </div>
        <div class="overflow-x-auto">
        <table id="deliveriesTable" class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">PO Number</th>
                    <th class="text-left px-3 py-2 font-semibold">Customer</th>
                    <th class="text-left px-3 py-2 font-semibold">Item</th>
                    <th class="text-left px-3 py-2 font-semibold">Due Date</th>
                    <th class="text-left px-3 py-2 font-semibold">Qty</th>
                    <th class="text-left px-3 py-2 font-semibold">Status</th>
                    <?php if ($canEdit): ?><th class="text-left px-3 py-2 font-semibold">Reminder Sent</th><?php endif; ?>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md"></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $deliveryStatusBadge = [
                'Pending' => 'bg-amber-100 text-amber-800',
                'Shipped' => 'bg-blue-100 text-blue-800',
                'Delivered' => 'bg-green-100 text-green-800',
            ];
            foreach ($deliveries as $d):
                $due = new DateTime($d['due_date']);
                $today = new DateTime('today');
                $diff = (int)$today->diff($due)->format('%r%a');
                $rowClass = '';
                if ($d['status'] !== 'Delivered' && $diff < 0) $rowClass = 'bg-red-50';
                elseif ($d['status'] !== 'Delivered' && $diff <= 3) $rowClass = 'bg-amber-50';
                $badgeClass = $deliveryStatusBadge[$d['status']] ?? 'bg-slate-100 text-slate-700';
            ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50 <?= $rowClass ?>">
                    <td class="px-3 py-2"><?= htmlspecialchars($d['po_number']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($d['customer_name']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($d['item_code']) ?> - <?= htmlspecialchars($d['description']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($d['due_date']) ?></td>
                    <td class="px-3 py-2"><?= $d['quantity'] ?></td>
                    <?php if ($canEdit): ?>
                    <td class="px-3 py-2">
                        <form method="POST" style="display:inline-block; margin:0;">
                            <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                            <select name="status" onchange="handleStatusChange(this)" data-delivery-id="<?= $d['id'] ?>" data-current-status="<?= htmlspecialchars($d['status']) ?>" <?= $d['status'] === 'Delivered' ? 'disabled title="Delivered deliveries cannot be changed"' : '' ?> class="px-2 py-1 border border-slate-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed">
                                <option value="Pending" <?= $d['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Shipped" <?= $d['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="Delivered" <?= $d['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                            </select>
                            <input type="hidden" name="update_status" value="1">
                        </form>
                    </td>
                    <?php else: ?>
                    <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeClass ?>"><?= htmlspecialchars($d['status']) ?></span></td>
                    <?php endif; ?>
                    <?php if ($canEdit): ?><td class="px-3 py-2"><?= htmlspecialchars($d['reminder_sent']) ?></td><?php endif; ?>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <?php if ($d['status'] === 'Delivered' && $d['dc_number']): ?>
                            <button type="button" onclick="viewDeliveryDetails(<?= $d['id'] ?>)" title="View delivery details" class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer align-middle">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M10 3.5c-4.14 0-7.5 3.5-8.5 6.5 1 3 4.36 6.5 8.5 6.5s7.5-3.5 8.5-6.5c-1-3-4.36-6.5-8.5-6.5zm0 11a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/><circle cx="10" cy="10" r="2"/></svg>
                            </button>
                        <?php endif; ?>
                        <button type="button" onclick="openAttachmentsModal(<?= $d['id'] ?>)" title="Attachments" class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer align-middle relative">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M8.5 3a3.5 3.5 0 00-3.5 3.5v6a2.5 2.5 0 005 0v-5a1 1 0 10-2 0v5a.5.5 0 01-1 0v-6a1.5 1.5 0 013 0v6.5a3 3 0 006 0v-7a.75.75 0 00-1.5 0v7a1.5 1.5 0 01-3 0v-6.5A3.5 3.5 0 008.5 3z"/></svg>
                            <?php if (!empty($deliveryAttachments[$d['id']])): ?>
                                <span class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full bg-brand-green text-white text-[9px] leading-3.5 flex items-center justify-center"><?= count($deliveryAttachments[$d['id']]) ?></span>
                            <?php endif; ?>
                        </button>
                        <?php if ($canEdit): ?>
                        <form method="POST" onsubmit="return confirm('Delete this delivery date?');" style="display:inline-block; margin:0;">
                            <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                            <button type="submit" name="delete_delivery" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-sm text-slate-600">
            <span id="deliveriesTableInfo"></span>
            <div class="flex gap-2">
                <button type="button" id="deliveriesTablePrev" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                <button type="button" id="deliveriesTableNext" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>

    <?php if ($canEdit): ?>
    <div id="deliveryDetailsModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-5">
            <h3 class="text-lg font-semibold text-brand-dark mb-3">Delivery Details</h3>
            <p class="text-sm text-slate-500 mb-3">Enter these details to mark the delivery as Delivered.</p>
            <form method="POST" id="deliveryDetailsForm">
                <input type="hidden" name="delivery_id" id="deliveryDetailsId">
                <input type="hidden" name="status" value="Delivered">
                <input type="hidden" name="update_status" value="1">
                <div class="space-y-3 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">DC Number</label>
                        <input type="text" name="dc_number" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Invoice Number</label>
                        <input type="text" name="invoice_number" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">DC Date</label>
                        <input type="date" name="dc_date" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Bill Date</label>
                        <input type="date" name="bill_date" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    </div>
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
            <dl class="space-y-2 text-sm mb-4">
                <div class="flex justify-between border-b border-slate-100 pb-2"><dt class="text-slate-500">DC Number</dt><dd id="viewDcNumber" class="font-semibold text-brand-dark"></dd></div>
                <div class="flex justify-between border-b border-slate-100 pb-2"><dt class="text-slate-500">Invoice Number</dt><dd id="viewInvoiceNumber" class="font-semibold text-brand-dark"></dd></div>
                <div class="flex justify-between border-b border-slate-100 pb-2"><dt class="text-slate-500">DC Date</dt><dd id="viewDcDate" class="font-semibold text-brand-dark"></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Bill Date</dt><dd id="viewBillDate" class="font-semibold text-brand-dark"></dd></div>
            </dl>
            <div class="flex justify-end">
                <button type="button" onclick="closeViewDetailsModal()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">Close</button>
            </div>
        </div>
    </div>

    <div id="attachmentsModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-5">
            <h3 class="text-lg font-semibold text-brand-dark mb-3">Attachments</h3>
            <div id="attachmentsList" class="space-y-2 mb-4"></div>
            <?php if ($canEdit): ?>
            <form method="POST" enctype="multipart/form-data" class="flex flex-wrap gap-2 items-center mb-3">
                <input type="hidden" name="delivery_id" id="attachmentsDeliveryId">
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required class="text-sm flex-1 min-w-[180px]">
                <button type="submit" name="upload_attachment" value="1" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Upload</button>
            </form>
            <p class="text-xs text-slate-400 mb-3">JPG, PNG, GIF, WEBP, or PDF. Max 5MB.</p>
            <?php endif; ?>
            <div class="flex justify-end">
                <button type="button" onclick="closeAttachmentsModal()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">Close</button>
            </div>
        </div>
    </div>

    <script>
        var deliveryAttachmentsData = <?= json_encode(array_map(function ($list) {
            return array_map(function ($a) {
                return ['id' => $a['id'], 'name' => $a['original_filename']];
            }, $list);
        }, $deliveryAttachments), JSON_UNESCAPED_SLASHES) ?>;
        var attachmentsCanEdit = <?= $canEdit ? 'true' : 'false' ?>;

        function openAttachmentsModal(deliveryId) {
            document.getElementById('attachmentsDeliveryId').value = deliveryId;
            var list = document.getElementById('attachmentsList');
            var items = deliveryAttachmentsData[deliveryId] || [];
            if (items.length === 0) {
                list.innerHTML = '<p class="text-sm text-slate-400">No attachments yet.</p>';
            } else {
                list.innerHTML = items.map(function (a) {
                    var deleteBtn = attachmentsCanEdit
                        ? '<form method="POST" onsubmit="return confirm(\'Delete this attachment?\');" style="display:inline;margin:0;"><input type="hidden" name="attachment_id" value="' + a.id + '"><input type="hidden" name="delivery_id" value="' + deliveryId + '"><button type="submit" name="delete_attachment" value="1" class="text-red-600 text-xs font-semibold hover:text-red-700 cursor-pointer">Delete</button></form>'
                        : '';
                    return '<div class="flex items-center justify-between gap-2 border border-slate-200 rounded-md px-3 py-2 text-sm">' +
                        '<a href="download_attachment.php?id=' + a.id + '" target="_blank" class="text-brand-dark hover:text-brand-green truncate">' + a.name.replace(/</g, '&lt;') + '</a>' +
                        deleteBtn + '</div>';
                }).join('');
            }
            document.getElementById('attachmentsModal').classList.remove('hidden');
        }

        function closeAttachmentsModal() {
            document.getElementById('attachmentsModal').classList.add('hidden');
        }

        var deliveryDetailsData = <?= json_encode(array_reduce($deliveries, function ($carry, $d) {
            if ($d['status'] === 'Delivered' && $d['dc_number']) {
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
            if (reset && activeStatusSelect) {
                activeStatusSelect.value = activeStatusSelect.dataset.currentStatus;
            }
            activeStatusSelect = null;
        }
        <?php endif; ?>

        function viewDeliveryDetails(id) {
            var data = deliveryDetailsData[id];
            if (!data) return;
            document.getElementById('viewDcNumber').textContent = data.dc_number;
            document.getElementById('viewInvoiceNumber').textContent = data.invoice_number;
            document.getElementById('viewDcDate').textContent = data.dc_date;
            document.getElementById('viewBillDate').textContent = data.bill_date;
            document.getElementById('viewDetailsModal').classList.remove('hidden');
        }

        function closeViewDetailsModal() {
            document.getElementById('viewDetailsModal').classList.add('hidden');
        }
    </script>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
