<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/activity_log.php';
requirePermission('stock_requests', 'view');

$me = currentUser();
$canRaise = hasPermission('stock_requests', 'edit');
// Approver check is hardcoded to the roles that own the shop, not RBAC-driven.
$canApprove = in_array($me['role_name'], ['Owner', 'Super Admin'], true);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ---- Requester: create a new request ----
    if ($canRaise && isset($_POST['create_request'])) {
        $purpose = trim($_POST['purpose'] ?? '');
        $linkedPo = $_POST['linked_po_id'] ?? '';
        $linkedJob = $_POST['linked_job_card_id'] ?? '';
        $stockIds = $_POST['stock_id'] ?? [];
        $qtys = $_POST['quantity'] ?? [];

        // Build clean line-item list, drop rows with no stock or zero qty.
        $lines = [];
        foreach ($stockIds as $i => $sid) {
            $sid = (int)$sid;
            $q = (int)($qtys[$i] ?? 0);
            if ($sid > 0 && $q > 0) $lines[] = ['stock_id' => $sid, 'quantity' => $q];
        }
        if (empty($lines)) {
            $error = 'Please add at least one item with a positive quantity.';
        } else {
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO stock_requests (requested_by_user_id, purpose, linked_po_id, linked_job_card_id)
                     VALUES (?, ?, ?, ?)'
                );
                $ins->execute([
                    $me['id'],
                    $purpose !== '' ? $purpose : null,
                    $linkedPo !== '' ? (int)$linkedPo : null,
                    $linkedJob !== '' ? (int)$linkedJob : null,
                ]);
                $requestId = (int)$pdo->lastInsertId();
                $itemIns = $pdo->prepare(
                    'INSERT INTO stock_request_items (request_id, stock_id, product_name, quantity)
                     SELECT ?, id, product_name, ? FROM stock WHERE id = ?'
                );
                foreach ($lines as $ln) {
                    $itemIns->execute([$requestId, $ln['quantity'], $ln['stock_id']]);
                }
                $pdo->commit();
                setFlashMessage("Stock request #$requestId submitted for approval.");
                logActivity('create_stock_request', "Raised stock request #$requestId with " . count($lines) . ' line(s).');
                header('Location: stock_requests.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Could not create request: ' . $e->getMessage();
            }
        }
    }

    // ---- Requester: soft-delete own Pending request with a reason ----
    // We keep the row (so approvers/audit can still see the request happened)
    // and just flip its status. delete_reason is required.
    elseif (isset($_POST['delete_request'])) {
        $rid = (int)$_POST['request_id'];
        $reason = trim($_POST['delete_reason'] ?? '');
        $check = $pdo->prepare("SELECT requested_by_user_id, status FROM stock_requests WHERE id = ?");
        $check->execute([$rid]);
        $r = $check->fetch();
        if (!$r) {
            $error = 'Request not found.';
        } elseif ((int)$r['requested_by_user_id'] !== (int)$me['id']) {
            $error = 'You can only delete your own requests.';
        } elseif ($r['status'] !== 'Pending') {
            $error = 'Only Pending requests can be deleted.';
        } elseif ($reason === '') {
            $error = 'Please provide a reason for deleting the request.';
        } else {
            $pdo->prepare("UPDATE stock_requests SET status='Deleted by User', delete_reason=? WHERE id = ? AND status='Pending'")
                ->execute([$reason, $rid]);
            setFlashMessage("Stock request #$rid marked as Deleted by User.");
            logActivity('delete_stock_request', "Deleted own stock request #$rid. Reason: $reason");
            header('Location: stock_requests.php');
            exit;
        }
    }

    // ---- Approver: approve (atomically deduct stock) ----
    elseif ($canApprove && isset($_POST['approve_request'])) {
        $rid = (int)$_POST['request_id'];
        $pdo->beginTransaction();
        try {
            $check = $pdo->prepare("SELECT status FROM stock_requests WHERE id = ? FOR UPDATE");
            $check->execute([$rid]);
            $st = $check->fetchColumn();
            if ($st !== 'Pending') {
                throw new RuntimeException('This request is no longer Pending.');
            }

            $items = $pdo->prepare('SELECT id, stock_id, product_name, quantity FROM stock_request_items WHERE request_id = ?');
            $items->execute([$rid]);
            $lines = $items->fetchAll();

            $deduct = $pdo->prepare("UPDATE stock SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
            $movement = $pdo->prepare(
                "INSERT INTO stock_movements (stock_id, product_name, delta, quantity_after, reason_code, reason_text, source_type, source_id, user_id, username)
                 VALUES (?, ?, ?, ?, 'stock_request', ?, 'stock_request', ?, ?, ?)"
            );
            $qtyAfter = $pdo->prepare('SELECT quantity FROM stock WHERE id = ?');

            foreach ($lines as $ln) {
                $deduct->execute([$ln['quantity'], $ln['stock_id'], $ln['quantity']]);
                if ($deduct->rowCount() === 0) {
                    // Fetch what's actually on hand for the error message.
                    $onHand = $pdo->prepare('SELECT quantity FROM stock WHERE id = ?');
                    $onHand->execute([$ln['stock_id']]);
                    $have = (int)$onHand->fetchColumn();
                    throw new RuntimeException("Insufficient stock for {$ln['product_name']} — requested {$ln['quantity']}, on hand {$have}. Nothing was deducted.");
                }
                $qtyAfter->execute([$ln['stock_id']]);
                $movement->execute([
                    $ln['stock_id'], $ln['product_name'], -$ln['quantity'], (int)$qtyAfter->fetchColumn(),
                    "Approved stock request #$rid", $rid, $me['id'], $me['username'],
                ]);
            }

            $pdo->prepare("UPDATE stock_requests SET status='Approved', reviewed_by_user_id=?, reviewed_at=NOW() WHERE id = ? AND status='Pending'")
                ->execute([$me['id'], $rid]);
            $pdo->commit();
            setFlashMessage("Stock request #$rid approved and stock deducted.");
            logActivity('approve_stock_request', "Approved stock request #$rid (" . count($lines) . ' line(s)).');
            header('Location: stock_requests.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    // ---- Approver: reject with reason ----
    elseif ($canApprove && isset($_POST['reject_request'])) {
        $rid = (int)$_POST['request_id'];
        $reason = trim($_POST['review_notes'] ?? '');
        if ($reason === '') {
            $error = 'Please provide a reason when rejecting a request.';
        } else {
            $stmt = $pdo->prepare(
                "UPDATE stock_requests SET status='Rejected', reviewed_by_user_id=?, reviewed_at=NOW(), review_notes=?
                 WHERE id = ? AND status='Pending'"
            );
            $stmt->execute([$me['id'], $reason, $rid]);
            if ($stmt->rowCount() === 0) {
                $error = 'That request is no longer Pending — nothing changed.';
            } else {
                setFlashMessage("Stock request #$rid rejected.");
                logActivity('reject_stock_request', "Rejected stock request #$rid. Reason: $reason");
                header('Location: stock_requests.php');
                exit;
            }
        }
    }
}

// ---------------- QUERIES ----------------

$stockOptions = $canRaise ? $pdo->query(
    'SELECT id, product_name, quantity FROM stock ORDER BY product_name ASC'
)->fetchAll() : [];

$poOptions = $canRaise ? $pdo->query(
    'SELECT id, po_number, item_code, customer_name FROM purchase_orders ORDER BY po_number DESC LIMIT 200'
)->fetchAll() : [];

$jobCardOptions = $canRaise ? $pdo->query(
    'SELECT id, product_name, job_date FROM job_cards ORDER BY id DESC LIMIT 200'
)->fetchAll() : [];

// Pending requests visible to approver first, then history.
$pendingRequests = $canApprove ? $pdo->query(
    "SELECT r.id, r.created_at, r.purpose, r.linked_po_id, r.linked_job_card_id,
            u.username AS requester, po.po_number, jc.product_name AS job_name
     FROM stock_requests r
     JOIN users u ON u.id = r.requested_by_user_id
     LEFT JOIN purchase_orders po ON po.id = r.linked_po_id
     LEFT JOIN job_cards jc ON jc.id = r.linked_job_card_id
     WHERE r.status = 'Pending'
     ORDER BY r.created_at ASC"
)->fetchAll() : [];

// Requester sees their own requests. Approver sees everyone's.
if ($canApprove) {
    $historyStmt = $pdo->query(
        "SELECT r.id, r.status, r.created_at, r.reviewed_at, r.review_notes, r.delete_reason, r.purpose,
                u.username AS requester, ru.username AS reviewer,
                po.po_number, jc.product_name AS job_name
         FROM stock_requests r
         JOIN users u ON u.id = r.requested_by_user_id
         LEFT JOIN users ru ON ru.id = r.reviewed_by_user_id
         LEFT JOIN purchase_orders po ON po.id = r.linked_po_id
         LEFT JOIN job_cards jc ON jc.id = r.linked_job_card_id
         ORDER BY r.created_at DESC LIMIT 100"
    );
    $history = $historyStmt->fetchAll();
} else {
    $historyStmt = $pdo->prepare(
        "SELECT r.id, r.status, r.created_at, r.reviewed_at, r.review_notes, r.delete_reason, r.purpose,
                u.username AS requester, ru.username AS reviewer,
                po.po_number, jc.product_name AS job_name
         FROM stock_requests r
         JOIN users u ON u.id = r.requested_by_user_id
         LEFT JOIN users ru ON ru.id = r.reviewed_by_user_id
         LEFT JOIN purchase_orders po ON po.id = r.linked_po_id
         LEFT JOIN job_cards jc ON jc.id = r.linked_job_card_id
         WHERE r.requested_by_user_id = ?
         ORDER BY r.created_at DESC LIMIT 100"
    );
    $historyStmt->execute([$me['id']]);
    $history = $historyStmt->fetchAll();
}

// One query for all items belonging to any request we're rendering, then
// bucket them by request_id so the render loop doesn't hit the DB per row.
// array_values() reindexes so PDO's positional binding gets a clean
// 0..n-1 keyed array; array_unique keeps original keys which can leave
// gaps and break execute() with "parameter was not defined".
$requestIds = array_values(array_unique(array_merge(
    array_column($pendingRequests, 'id'),
    array_column($history, 'id')
)));
$itemsByRequest = [];
if (!empty($requestIds)) {
    $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
    $itemStmt = $pdo->prepare(
        "SELECT ri.request_id, ri.stock_id, ri.product_name, ri.quantity, s.quantity AS on_hand
         FROM stock_request_items ri
         LEFT JOIN stock s ON s.id = ri.stock_id
         WHERE ri.request_id IN ($placeholders)
         ORDER BY ri.id ASC"
    );
    $itemStmt->execute($requestIds);
    foreach ($itemStmt->fetchAll() as $row) {
        $itemsByRequest[(int)$row['request_id']][] = $row;
    }
}

$statusBadge = [
    'Pending'         => 'bg-amber-100 text-amber-800',
    'Approved'        => 'bg-green-100 text-green-800',
    'Rejected'        => 'bg-red-100 text-red-800',
    'Deleted by User' => 'bg-slate-200 text-slate-700',
];

$pageTitle = 'Stock Requests';
include __DIR__ . '/includes/layout_start.php';
?>

<?php if ($canRaise): ?>
<div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
    <h3 class="text-lg font-semibold text-brand-dark mb-3">Raise New Stock Request</h3>
    <form method="POST" id="newRequestForm" class="space-y-3">
        <?= csrfField() ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Purpose / Notes</label>
                <input type="text" name="purpose" placeholder="Why do you need this?" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Link to PO (optional)</label>
                <select name="linked_po_id" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="">— none —</option>
                    <?php foreach ($poOptions as $po): ?>
                        <option value="<?= $po['id'] ?>"><?= htmlspecialchars($po['po_number'] . ' · ' . $po['item_code'] . ' · ' . $po['customer_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Link to Job Card (optional)</label>
                <select name="linked_job_card_id" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="">— none —</option>
                    <?php foreach ($jobCardOptions as $jc): ?>
                        <option value="<?= $jc['id'] ?>">#<?= str_pad((string)$jc['id'], 2, '0', STR_PAD_LEFT) ?> · <?= htmlspecialchars($jc['product_name']) ?> · <?= htmlspecialchars($jc['job_date'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="text-xs font-semibold text-slate-500">Items</label>
                <button type="button" id="addItemRow" class="text-xs px-2 py-1 rounded-md border border-slate-300 hover:bg-slate-50">+ Add item</button>
            </div>
            <div id="itemRows" class="space-y-2">
                <!-- first row rendered below; more via JS clone -->
                <div class="item-row grid grid-cols-12 gap-2 items-center">
                    <select name="stock_id[]" required class="col-span-8 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                        <option value="">— select stock item —</option>
                        <?php foreach ($stockOptions as $s): ?>
                            <option value="<?= $s['id'] ?>" data-on-hand="<?= (int)$s['quantity'] ?>"><?= htmlspecialchars($s['product_name']) ?> (on hand: <?= (int)$s['quantity'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="quantity[]" min="1" placeholder="Qty" required class="col-span-3 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <button type="button" class="remove-row col-span-1 text-slate-400 hover:text-red-600 text-lg font-bold" title="Remove">×</button>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" name="create_request" value="1" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Submit Request</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($canApprove): ?>
<div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
    <h3 class="text-lg font-semibold text-brand-dark mb-3">Pending Approvals <span class="text-sm font-normal text-slate-500">(<?= count($pendingRequests) ?>)</span></h3>
    <?php if (empty($pendingRequests)): ?>
        <p class="text-sm text-slate-400 py-4 text-center">No pending requests. All caught up.</p>
    <?php endif; ?>
    <div class="space-y-3">
        <?php foreach ($pendingRequests as $r):
            $items = $itemsByRequest[(int)$r['id']] ?? [];
        ?>
            <div class="border border-slate-200 rounded-md p-3">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <div>
                        <span class="font-semibold text-brand-dark">#<?= (int)$r['id'] ?></span>
                        <span class="text-slate-500 text-sm">by <?= htmlspecialchars($r['requester']) ?></span>
                        <span class="text-slate-400 text-xs">· <?= htmlspecialchars($r['created_at']) ?></span>
                        <?php if ($r['po_number']): ?>
                            <span class="ml-2 text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700">PO: <?= htmlspecialchars($r['po_number']) ?></span>
                        <?php endif; ?>
                        <?php if ($r['job_name']): ?>
                            <span class="ml-1 text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700">Job: <?= htmlspecialchars($r['job_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($r['purpose'])): ?>
                    <div class="text-sm text-slate-600 italic mb-2">"<?= htmlspecialchars($r['purpose']) ?>"</div>
                <?php endif; ?>
                <table class="w-full text-sm mb-3">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-100">
                            <th class="py-1">Item</th>
                            <th class="py-1 text-right">Requested</th>
                            <th class="py-1 text-right">On hand</th>
                            <th class="py-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it):
                            $short = (int)$it['on_hand'] < (int)$it['quantity'];
                        ?>
                            <tr class="border-b border-slate-50 <?= $short ? 'bg-red-50' : '' ?>">
                                <td class="py-1"><?= htmlspecialchars($it['product_name']) ?></td>
                                <td class="py-1 text-right font-semibold"><?= (int)$it['quantity'] ?></td>
                                <td class="py-1 text-right"><?= $it['on_hand'] !== null ? (int)$it['on_hand'] : '—' ?></td>
                                <td class="py-1 text-xs <?= $short ? 'text-red-700 font-semibold' : 'text-slate-400' ?>">
                                    <?= $short ? 'Short by ' . ((int)$it['quantity'] - (int)$it['on_hand']) : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" onclick="openReviewModal(<?= (int)$r['id'] ?>, 'reject')" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors">Reject</button>
                    <button type="button" onclick="openReviewModal(<?= (int)$r['id'] ?>, 'approve')" class="px-3 py-1.5 rounded-md bg-brand-green text-white text-xs font-semibold hover:bg-brand-greendark transition-colors">Approve</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
    <h3 class="text-lg font-semibold text-brand-dark mb-3"><?= $canApprove ? 'All Requests' : 'My Requests' ?></h3>
    <?php if (empty($history)): ?>
        <p class="text-sm text-slate-400 py-4 text-center">No requests yet.</p>
    <?php endif; ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-brand-dark text-white text-left">
                    <th class="px-3 py-2 font-semibold">#</th>
                    <th class="px-3 py-2 font-semibold">Requester</th>
                    <th class="px-3 py-2 font-semibold">Items</th>
                    <th class="px-3 py-2 font-semibold">Link</th>
                    <th class="px-3 py-2 font-semibold">Status</th>
                    <th class="px-3 py-2 font-semibold">Reviewed</th>
                    <th class="px-3 py-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $r):
                    $items = $itemsByRequest[(int)$r['id']] ?? [];
                    $canDelete = $r['status'] === 'Pending' && $r['requester'] === $me['username'];
                ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-2 font-semibold text-brand-dark"><?= (int)$r['id'] ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($r['requester']) ?><div class="text-xs text-slate-400"><?= htmlspecialchars($r['created_at']) ?></div></td>
                        <td class="px-3 py-2">
                            <ul class="text-xs space-y-0.5">
                                <?php foreach ($items as $it): ?>
                                    <li><?= htmlspecialchars($it['product_name']) ?> × <?= (int)$it['quantity'] ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (!empty($r['purpose'])): ?>
                                <div class="text-xs text-slate-500 italic mt-1">"<?= htmlspecialchars($r['purpose']) ?>"</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-xs">
                            <?php if ($r['po_number']): ?><div>PO: <?= htmlspecialchars($r['po_number']) ?></div><?php endif; ?>
                            <?php if ($r['job_name']): ?><div>Job: <?= htmlspecialchars($r['job_name']) ?></div><?php endif; ?>
                        </td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusBadge[$r['status']] ?? 'bg-slate-100 text-slate-700' ?>"><?= htmlspecialchars($r['status']) ?></span>
                        </td>
                        <td class="px-3 py-2 text-xs">
                            <?php if ($r['status'] === 'Deleted by User' && !empty($r['delete_reason'])): ?>
                                <div class="text-slate-700">Deleted by <?= htmlspecialchars($r['requester']) ?></div>
                                <div class="text-slate-600 italic mt-1">"<?= htmlspecialchars($r['delete_reason']) ?>"</div>
                            <?php elseif ($r['reviewer']): ?>
                                <div class="text-slate-700"><?= htmlspecialchars($r['reviewer']) ?></div>
                                <div class="text-slate-400"><?= htmlspecialchars($r['reviewed_at']) ?></div>
                                <?php if ($r['review_notes']): ?>
                                    <div class="text-slate-600 italic mt-1">"<?= htmlspecialchars($r['review_notes']) ?>"</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2">
                            <?php if ($canDelete): ?>
                                <button type="button" onclick="openDeleteModal(<?= (int)$r['id'] ?>)" class="px-2 py-1 rounded-md text-xs bg-red-600 text-white hover:bg-red-700">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canApprove): ?>
<div id="reviewModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-5">
        <h3 id="reviewModalTitle" class="text-lg font-semibold text-brand-dark mb-1">Review Request</h3>
        <p id="reviewModalDesc" class="text-sm text-slate-500 mb-4"></p>
        <form method="POST" id="reviewForm">
            <?= csrfField() ?>
            <input type="hidden" name="request_id" id="reviewRequestId">
            <input type="hidden" name="approve_request" id="reviewApproveFlag" disabled>
            <input type="hidden" name="reject_request" id="reviewRejectFlag" disabled value="1">
            <div id="reviewReasonWrap" class="mb-4 hidden">
                <label class="block text-xs font-semibold text-slate-500 mb-1">Reason for rejection <span class="text-red-600">*</span></label>
                <textarea name="review_notes" id="reviewReasonInput" rows="3" placeholder="Tell the requester why this is being rejected..." class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green"></textarea>
                <div id="reviewReasonError" class="hidden text-red-600 text-xs mt-1">A reason is required.</div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeReviewModal()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                <button type="submit" id="reviewSubmitBtn" class="px-4 py-2 rounded-md text-white text-sm font-semibold cursor-pointer"></button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('reviewModal');
    var form = document.getElementById('reviewForm');
    var titleEl = document.getElementById('reviewModalTitle');
    var descEl = document.getElementById('reviewModalDesc');
    var idInput = document.getElementById('reviewRequestId');
    var approveFlag = document.getElementById('reviewApproveFlag');
    var rejectFlag = document.getElementById('reviewRejectFlag');
    var reasonWrap = document.getElementById('reviewReasonWrap');
    var reasonInput = document.getElementById('reviewReasonInput');
    var reasonError = document.getElementById('reviewReasonError');
    var submitBtn = document.getElementById('reviewSubmitBtn');

    window.openReviewModal = function (requestId, mode) {
        idInput.value = requestId;
        reasonInput.value = '';
        reasonError.classList.add('hidden');
        if (mode === 'approve') {
            titleEl.textContent = 'Approve Request #' + requestId;
            descEl.textContent = 'Stock will be deducted immediately. If any item is short, the whole approval rolls back.';
            approveFlag.disabled = false;
            approveFlag.value = '1';
            rejectFlag.disabled = true;
            reasonWrap.classList.add('hidden');
            submitBtn.textContent = 'Approve & Deduct Stock';
            submitBtn.className = 'px-4 py-2 rounded-md text-white text-sm font-semibold cursor-pointer bg-brand-green hover:bg-brand-greendark';
        } else {
            titleEl.textContent = 'Reject Request #' + requestId;
            descEl.textContent = 'The requester will see your reason on their request history.';
            approveFlag.disabled = true;
            rejectFlag.disabled = false;
            reasonWrap.classList.remove('hidden');
            submitBtn.textContent = 'Reject Request';
            submitBtn.className = 'px-4 py-2 rounded-md text-white text-sm font-semibold cursor-pointer bg-red-600 hover:bg-red-700';
            setTimeout(function () { reasonInput.focus(); }, 50);
        }
        modal.classList.remove('hidden');
    };
    window.closeReviewModal = function () { modal.classList.add('hidden'); };
    form.addEventListener('submit', function (e) {
        if (!rejectFlag.disabled && reasonInput.value.trim() === '') {
            e.preventDefault();
            reasonError.classList.remove('hidden');
            reasonInput.focus();
        }
    });
})();
</script>
<?php endif; ?>

<div id="deleteModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-2">Delete Request</h3>
        <p id="deleteModalDesc" class="text-sm text-slate-500 mb-4">The request will be marked as "Deleted by User" and your reason will be shown alongside it.</p>
        <form method="POST" id="deleteForm">
            <?= csrfField() ?>
            <input type="hidden" name="request_id" id="deleteRequestId">
            <input type="hidden" name="delete_request" value="1">
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-500 mb-1">Reason for deleting <span class="text-red-600">*</span></label>
                <textarea name="delete_reason" id="deleteReasonInput" rows="3" placeholder="Why are you cancelling this request?" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green"></textarea>
                <div id="deleteReasonError" class="hidden text-red-600 text-xs mt-1">A reason is required.</div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-md bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete Request</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('deleteModal');
    var idInput = document.getElementById('deleteRequestId');
    var reasonInput = document.getElementById('deleteReasonInput');
    var reasonError = document.getElementById('deleteReasonError');
    var form = document.getElementById('deleteForm');
    window.openDeleteModal = function (requestId) {
        idInput.value = requestId;
        reasonInput.value = '';
        reasonError.classList.add('hidden');
        modal.classList.remove('hidden');
        setTimeout(function () { reasonInput.focus(); }, 50);
    };
    window.closeDeleteModal = function () { modal.classList.add('hidden'); };
    form.addEventListener('submit', function (e) {
        if (reasonInput.value.trim() === '') {
            e.preventDefault();
            reasonError.classList.remove('hidden');
            reasonInput.focus();
        }
    });
})();
</script>

<?php if ($canRaise): ?>
<script>
(function () {
    var rowsWrap = document.getElementById('itemRows');
    var template = rowsWrap.querySelector('.item-row').cloneNode(true);

    function bindRemove(row) {
        row.querySelector('.remove-row').addEventListener('click', function () {
            if (rowsWrap.querySelectorAll('.item-row').length <= 1) return;
            row.remove();
        });
    }
    rowsWrap.querySelectorAll('.item-row').forEach(bindRemove);

    document.getElementById('addItemRow').addEventListener('click', function () {
        var fresh = template.cloneNode(true);
        fresh.querySelectorAll('input, select').forEach(function (el) {
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
        });
        rowsWrap.appendChild(fresh);
        bindRemove(fresh);
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_end.php'; ?>
