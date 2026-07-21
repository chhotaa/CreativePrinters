    <?php if ($canEdit): ?>
    <?php
    $jc = $editJobCard;
    $val = function ($field, $default = '') use ($jc) {
        return $jc ? htmlspecialchars($jc[$field] ?? '') : $default;
    };
    ?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5">
        <h3 class="text-lg font-semibold text-brand-dark mb-3"><?= $jc ? 'Edit Job Card #' . str_pad((string)$jc['id'], 2, '0', STR_PAD_LEFT) : 'New Job Card' ?></h3>
        <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?= csrfField() ?>
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
    <?php endif; ?>

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
                        <a href="job_card_print.php?id=<?= $row['id'] ?>" target="_blank" class="px-3 py-1.5 rounded-md bg-brand-dark text-white text-xs font-semibold hover:bg-slate-700 transition-colors inline-block">Print</a>
                        <button type="button" onclick="openAttachmentsModal(<?= $row['id'] ?>)" title="Attachments" class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer align-middle relative">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M8.5 3a3.5 3.5 0 00-3.5 3.5v6a2.5 2.5 0 005 0v-5a1 1 0 10-2 0v5a.5.5 0 01-1 0v-6a1.5 1.5 0 013 0v6.5a3 3 0 006 0v-7a.75.75 0 00-1.5 0v7a1.5 1.5 0 01-3 0v-6.5A3.5 3.5 0 008.5 3z"/></svg>
                            <?php if (!empty($jobCardAttachments[$row['id']])): ?>
                                <span class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full bg-brand-green text-white text-[9px] leading-3.5 flex items-center justify-center"><?= count($jobCardAttachments[$row['id']]) ?></span>
                            <?php endif; ?>
                        </button>
                        <?php if ($canEdit): ?>
                            <a href="?edit=<?= $row['id'] ?>" class="px-3 py-1.5 rounded-md bg-slate-600 text-white text-xs font-semibold hover:bg-slate-700 transition-colors inline-block">Edit</a>
                            <form method="POST" onsubmit="return confirm('Delete this job card?');" style="display:inline-block; margin:0;">
                <?= csrfField() ?>
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

    <div id="attachmentsModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-5">
            <h3 class="text-lg font-semibold text-brand-dark mb-3">Attachments</h3>
            <div id="attachmentsList" class="space-y-2 mb-4"></div>
            <form method="POST" enctype="multipart/form-data" class="flex flex-wrap gap-2 items-center mb-3">
                <?= csrfField() ?>
                <input type="hidden" name="job_card_id" id="attachmentsJobCardId">
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required class="text-sm flex-1 min-w-[180px]">
                <button type="submit" name="upload_attachment" value="1" class="px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Upload</button>
            </form>
            <p class="text-xs text-slate-400 mb-3">JPG, PNG, GIF, WEBP, or PDF. Max 5MB.</p>
            <div class="flex justify-end">
                <button type="button" onclick="closeAttachmentsModal()" class="px-4 py-2 rounded-md border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">Close</button>
            </div>
        </div>
    </div>

    <script>
        var jobCardAttachmentsData = <?= json_encode(array_map(function ($list) {
            return array_map(function ($a) {
                return ['id' => $a['id'], 'name' => $a['original_filename']];
            }, $list);
        }, $jobCardAttachments), JSON_UNESCAPED_SLASHES) ?>;
        var jobCardAttachmentsCanEdit = <?= $canEdit ? 'true' : 'false' ?>;

        function openAttachmentsModal(jobCardId) {
            document.getElementById('attachmentsJobCardId').value = jobCardId;
            var list = document.getElementById('attachmentsList');
            var items = jobCardAttachmentsData[jobCardId] || [];
            if (items.length === 0) {
                list.innerHTML = '<p class="text-sm text-slate-400">No attachments yet.</p>';
            } else {
                list.innerHTML = items.map(function (a) {
                    var deleteBtn = jobCardAttachmentsCanEdit
                        ? '<form method="POST" onsubmit="return confirm(\'Delete this attachment?\');" style="display:inline;margin:0;"><?= csrfField() ?><input type="hidden" name="attachment_id" value="' + a.id + '"><input type="hidden" name="job_card_id" value="' + jobCardId + '"><button type="submit" name="delete_attachment" value="1" class="text-red-600 text-xs font-semibold hover:text-red-700 cursor-pointer">Delete</button></form>'
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
    </script>
