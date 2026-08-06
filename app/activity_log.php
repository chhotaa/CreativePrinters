<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pagination.php';
requirePermission('activity_log', 'view');
// Log entries are immutable — there's nothing to write here, so "Edit"
// access to this module has no additional effect beyond "View".

// ---- URL params ----
$pageSizeOptions = [25, 50, 100, 200];
$page = max(1, (int)($_GET['page'] ?? 1));
$sizeIn = (int)($_GET['size'] ?? 50);
$size = in_array($sizeIn, $pageSizeOptions, true) ? $sizeIn : 50;
$q = trim($_GET['q'] ?? '');

$currentParams = ['page' => $page, 'size' => $size, 'q' => $q];
$urlBuilder = fn(array $ov = []) => pageUrl('activity_log.php', $currentParams, $ov);

$whereBits = [];
$whereParams = [];
if ($q !== '') {
    $whereBits[] = '(username LIKE ? OR role_name LIKE ? OR action LIKE ? OR description LIKE ? OR ip_address LIKE ?)';
    $qLike = '%' . $q . '%';
    array_push($whereParams, $qLike, $qLike, $qLike, $qLike, $qLike);
}
$whereSql = $whereBits ? 'WHERE ' . implode(' AND ', $whereBits) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log $whereSql");
$countStmt->execute($whereParams);
$totalEntries = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalEntries / $size));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $size;

$entriesStmt = $pdo->prepare(
    "SELECT * FROM activity_log $whereSql ORDER BY created_at DESC, id DESC LIMIT $size OFFSET $offset"
);
$entriesStmt->execute($whereParams);
$entries = $entriesStmt->fetchAll();

$pageTitle = 'Activity Log';
include __DIR__ . '/includes/layout_start.php';

$paginationArgs = [
    'total' => $totalEntries, 'offset' => $offset, 'size' => $size, 'pageRowCount' => count($entries),
    'page' => $page, 'totalPages' => $totalPages, 'sizeOptions' => $pageSizeOptions,
    'urlBuilder' => $urlBuilder, 'unit' => 'entry',
];
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <form method="GET" action="activity_log.php" class="flex items-center gap-2">
                <input type="hidden" name="size" value="<?= (int)$size ?>">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search activity..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                <button type="submit" class="px-3 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark">Search</button>
                <?php if ($q !== ''): ?><a href="<?= htmlspecialchars($urlBuilder(['q' => ''])) ?>" class="text-xs text-slate-500 underline">clear</a><?php endif; ?>
            </form>
        </div>
        <div class="mb-3"><?php renderPaginationBar($paginationArgs); ?></div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Date/Time</th>
                    <th class="text-left px-3 py-2 font-semibold">Username</th>
                    <th class="text-left px-3 py-2 font-semibold">Role</th>
                    <th class="text-left px-3 py-2 font-semibold">Action</th>
                    <th class="text-left px-3 py-2 font-semibold">Description</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md">IP Address</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($entries)): ?>
                <tr><td colspan="6" class="px-3 py-6 text-center text-slate-400"><?= $q !== '' ? 'No entries match your search.' : 'No activity yet.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($entries as $e): ?>
                <tr class="border-b border-slate-100 even:bg-slate-50 hover:bg-slate-100">
                    <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars($e['created_at']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($e['username'] ?? '-') ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($e['role_name'] ?? '-') ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($e['action']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($e['description']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($e['ip_address'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="mt-3"><?php renderPaginationBar($paginationArgs); ?></div>
    </div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
