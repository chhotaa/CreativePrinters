<?php
/**
 * Shared server-side pagination helpers.
 *
 * Every listing page follows the same pattern:
 *   1. Parse URL params (page, size, and page-specific filters).
 *   2. COUNT to figure out total pages.
 *   3. Fetch just the current page with LIMIT/OFFSET.
 *   4. Render a "Showing X-Y of Z | Show N | Prev | Next" bar
 *      both above AND below the table.
 *
 * These helpers factor out (1) + (4). Individual pages own their SQL.
 */

/**
 * Build a query-string URL preserving all current params, overriding
 * whichever keys the caller passes in. Any key set to '' is stripped
 * so the URL stays clean. `page` auto-resets to 1 when any *other*
 * param changes (typical UX: search resets pagination).
 *
 * @param string $script Filename to link to, e.g. 'deliveries.php'.
 * @param array  $current The current param values (from your $_GET parsing).
 * @param array  $overrides Keys to change.
 */
function pageUrl(string $script, array $current, array $overrides = []): string {
    $merged = array_merge($current, $overrides);
    // If the caller didn't explicitly set page, reset to 1 whenever any
    // OTHER key changed.
    if (!array_key_exists('page', $overrides)) {
        foreach ($overrides as $k => $v) {
            if ($k !== 'page' && (!isset($current[$k]) || $current[$k] !== $v)) {
                $merged['page'] = 1;
                break;
            }
        }
    }
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null && $v !== false);
    return $script . (empty($merged) ? '' : '?' . http_build_query($merged));
}

/**
 * Render the standard pagination bar. Uses <?= echo, not return.
 *
 * @param array $args {
 *   @var int    total          Total matching rows/records (not just this page).
 *   @var int    offset         (page-1)*size — starting index for "Showing X-Y".
 *   @var int    size           Rows per page.
 *   @var int    pageRowCount   Actual rows rendered on this page (may be <= size).
 *   @var int    page           1-based current page number.
 *   @var int    totalPages     Total page count.
 *   @var array  sizeOptions    Allowed page-size choices, e.g. [10, 20, 50, 100].
 *   @var callable urlBuilder   fn(array $overrides): string -- typically wraps pageUrl().
 *   @var string unit           'PO' | 'row' | 'entry' etc. Singular; 's' auto-added.
 * }
 */
function renderPaginationBar(array $args): void {
    $total = (int)$args['total'];
    $offset = (int)$args['offset'];
    $size = (int)$args['size'];
    $pageRowCount = (int)$args['pageRowCount'];
    $page = (int)$args['page'];
    $totalPages = (int)$args['totalPages'];
    $sizeOptions = $args['sizeOptions'];
    $urlBuilder = $args['urlBuilder'];
    $unit = $args['unit'] ?? 'row';
    $unitPlural = $unit . 's';
    ?>
    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
        <div>
            <?php if ($total > 0): ?>
                Showing <?= $unitPlural ?> <?= $offset + 1 ?>&ndash;<?= min($offset + $size, $total) ?> of <?= number_format($total) ?>
                <?php if ($pageRowCount !== $total): ?>
                    (<?= $pageRowCount ?> <?= $pageRowCount === 1 ? 'item' : 'items' ?> on this page)
                <?php endif; ?>
            <?php else: ?>
                No matches.
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-1.5">
                Show
                <select onchange="location.href=this.value" class="px-2 py-1 border border-slate-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <?php foreach ($sizeOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($urlBuilder(['size' => $opt])) ?>" <?= $size === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
                <?= htmlspecialchars($unitPlural) ?>
            </label>
            <div class="flex gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars($urlBuilder(['page' => $page - 1])) ?>" class="px-3 py-1 rounded-md border border-slate-300 font-medium hover:bg-slate-50">Previous</a>
                <?php else: ?>
                    <span class="px-3 py-1 rounded-md border border-slate-300 font-medium opacity-40 cursor-not-allowed">Previous</span>
                <?php endif; ?>
                <span class="px-3 py-1 text-slate-600">Page <?= $page ?> / <?= max(1, $totalPages) ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= htmlspecialchars($urlBuilder(['page' => $page + 1])) ?>" class="px-3 py-1 rounded-md border border-slate-300 font-medium hover:bg-slate-50">Next</a>
                <?php else: ?>
                    <span class="px-3 py-1 rounded-md border border-slate-300 font-medium opacity-40 cursor-not-allowed">Next</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
