<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requirePermission('activity_log', 'view');
// Log entries are immutable — there's nothing to write here, so "Edit"
// access to this module has no additional effect beyond "View".

$entries = $pdo->query('SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 500')->fetchAll();
$pageTitle = 'Activity Log';
include __DIR__ . '/includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <input type="text" id="activityLogTableSearch" placeholder="Search activity..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                Show
                <select id="activityLogTablePageSize" class="px-2 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="all">All</option>
                </select>
                entries
            </label>
        </div>
        <p class="text-xs text-slate-500 mb-3">Showing the most recent 500 actions.</p>
        <div class="overflow-x-auto">
        <table id="activityLogTable" class="w-full text-sm border-collapse">
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
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-sm text-slate-600">
            <span id="activityLogTableInfo"></span>
            <div class="flex gap-2">
                <button type="button" id="activityLogTablePrev" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                <button type="button" id="activityLogTableNext" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
