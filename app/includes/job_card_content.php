    <?php
    $jc = $editJobCard;
    $val = function ($field, $default = '') use ($jc) {
        return $jc ? htmlspecialchars($jc[$field] ?? '') : $default;
    };
    ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3"><?= $jc ? 'Edit Job Card #' . str_pad((string)$jc['id'], 2, '0', STR_PAD_LEFT) : 'New Job Card' ?></h3>
        <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php if ($jc): ?>
                <input type="hidden" name="job_card_id" value="<?= $jc['id'] ?>">
            <?php endif; ?>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Date</label>
                <input type="date" name="job_date" value="<?= $jc ? htmlspecialchars($jc['job_date']) : date('Y-m-d') ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Name</label>
                <input type="text" name="product_name" placeholder="e.g. Green Printers Plate" required value="<?= $val('product_name') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Design Name</label>
                <input type="text" name="design_name" placeholder="e.g. Oeko Tex Hang Tag" value="<?= $val('design_name') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Board Name / GSM</label>
                <input type="text" name="board_name_gsm" placeholder="e.g. 210 GSM Artboard" value="<?= $val('board_name_gsm') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Board Size</label>
                <input type="text" name="board_size" placeholder='e.g. 25&quot;x36&quot;(1x3)' value="<?= $val('board_size') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Cutting Size</label>
                <input type="text" name="cutting_size" placeholder='e.g. 25&quot;x12&quot;' value="<?= $val('cutting_size') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Board Quantity</label>
                <input type="text" name="board_quantity" placeholder="e.g. 370 sheet" value="<?= $val('board_quantity') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Copies</label>
                <input type="text" name="copies" placeholder="e.g. 1000+100" value="<?= $val('copies') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Colour</label>
                <input type="text" name="colour" placeholder="e.g. Front - 2 Color, Green + Black" value="<?= $val('colour') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Lamination / Varnish</label>
                <input type="text" name="lamination_varnish" value="<?= $val('lamination_varnish') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Order</label>
                <select name="order_type" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="Sample" <?= $jc && $jc['order_type'] === 'Sample' ? 'selected' : '' ?>>Sample</option>
                    <option value="Bulk Production" <?= !$jc || $jc['order_type'] === 'Bulk Production' ? 'selected' : '' ?>>Bulk Production</option>
                    <option value="Repeat Order" <?= $jc && $jc['order_type'] === 'Repeat Order' ? 'selected' : '' ?>>Repeat order</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Plate</label>
                <select name="plate_type" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="New" <?= $jc && $jc['plate_type'] === 'New' ? 'selected' : '' ?>>New</option>
                    <option value="Old" <?= !$jc || $jc['plate_type'] === 'Old' ? 'selected' : '' ?>>Old</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Die Punching</label>
                <select name="die_punching" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="" <?= $jc && empty($jc['die_punching']) ? 'selected' : '' ?>>N/A</option>
                    <option value="New" <?= $jc && $jc['die_punching'] === 'New' ? 'selected' : '' ?>>New</option>
                    <option value="Old" <?= $jc && $jc['die_punching'] === 'Old' ? 'selected' : '' ?>>Old</option>
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="block text-xs font-semibold text-slate-500 mb-1">Details</label>
                <textarea name="details" rows="3" placeholder="Any additional notes for this job card" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green"><?= $val('details') ?></textarea>
            </div>
            <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-5">
                <span class="text-xs font-semibold text-slate-500">Pasting:</span>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="pasting_perforation" value="1" <?= $jc && $jc['pasting_perforation'] ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-green focus:ring-brand-green">
                    Perforation
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="pasting_double_board" value="1" <?= $jc && $jc['pasting_double_board'] ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-green focus:ring-brand-green">
                    Double Board
                </label>
            </div>
            <div class="sm:col-span-2 lg:col-span-3 flex items-center gap-2">
                <button type="submit" name="<?= $jc ? 'update_job_card' : 'add_job_card' ?>" value="1" class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer"><?= $jc ? 'Save Changes' : 'Save Job Card' ?></button>
                <?php if ($jc): ?>
                    <a href="job_cards.php" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <input type="text" id="jobCardsTableSearch" placeholder="Search job cards..." class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                Show
                <select id="jobCardsTablePageSize" class="px-2 py-1.5 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="all">All</option>
                </select>
                entries
            </label>
        </div>
        <div class="overflow-x-auto">
        <table id="jobCardsTable" class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-brand-dark text-white">
                    <th class="text-left px-3 py-2 font-semibold rounded-tl-md">Sl.No</th>
                    <th class="text-left px-3 py-2 font-semibold">Date</th>
                    <th class="text-left px-3 py-2 font-semibold">Name</th>
                    <th class="text-left px-3 py-2 font-semibold">Design Name</th>
                    <th class="text-left px-3 py-2 font-semibold">Order Type</th>
                    <th class="text-left px-3 py-2 font-semibold rounded-tr-md"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($jobCards as $row): ?>
                <tr class="border-b border-slate-100 even:bg-slate-50 hover:bg-slate-100">
                    <td class="px-3 py-2"><?= str_pad((string)$row['id'], 2, '0', STR_PAD_LEFT) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($row['job_date']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($row['product_name']) ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($row['design_name'] ?? '') ?></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($row['order_type']) ?></td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        <a href="../job_card_print.php?id=<?= $row['id'] ?>" target="_blank" class="px-3 py-1.5 rounded-md bg-brand-dark text-white text-xs font-semibold hover:bg-slate-700 transition-colors inline-block">Print</a>
                        <?php if ($isAdmin): ?>
                            <a href="?edit=<?= $row['id'] ?>" class="px-3 py-1.5 rounded-md bg-slate-600 text-white text-xs font-semibold hover:bg-slate-700 transition-colors inline-block">Edit</a>
                            <form method="POST" onsubmit="return confirm('Delete this job card?');" style="display:inline-block; margin:0;">
                                <input type="hidden" name="job_card_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="delete_job_card" value="1" class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700 transition-colors cursor-pointer">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3 text-sm text-slate-600">
            <span id="jobCardsTableInfo"></span>
            <div class="flex gap-2">
                <button type="button" id="jobCardsTablePrev" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                <button type="button" id="jobCardsTableNext" class="px-3 py-1.5 rounded-md border border-slate-300 text-sm font-medium hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>
