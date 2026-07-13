        </main>
    </div>
    <script>
        // Auto-wires up every <table id="..."> on the page with search +
        // "Show N entries" + Previous/Next, based on sibling controls named
        // by ID convention: {tableId}Search, {tableId}PageSize,
        // {tableId}Info, {tableId}Prev, {tableId}Next. Any control that
        // isn't present on a given page is simply skipped.
        document.querySelectorAll('table[id]').forEach(function (table) {
            initDataTable(table.id);
        });

        function initDataTable(tableId) {
            var table = document.getElementById(tableId);
            if (!table) return;
            var searchInput = document.getElementById(tableId + 'Search');
            var pageSizeSelect = document.getElementById(tableId + 'PageSize');
            var infoEl = document.getElementById(tableId + 'Info');
            var prevBtn = document.getElementById(tableId + 'Prev');
            var nextBtn = document.getElementById(tableId + 'Next');
            var allRows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
            var page = 1;

            function render() {
                var filter = searchInput ? searchInput.value.trim().toLowerCase() : '';
                var matches = allRows.filter(function (row) {
                    return row.textContent.toLowerCase().includes(filter);
                });

                var pageSizeValue = pageSizeSelect ? pageSizeSelect.value : 'all';
                var size = pageSizeValue === 'all' ? matches.length : parseInt(pageSizeValue, 10);
                var totalPages = size > 0 ? Math.max(1, Math.ceil(matches.length / size)) : 1;
                if (page > totalPages) page = totalPages;
                if (page < 1) page = 1;
                var start = size > 0 ? (page - 1) * size : 0;
                var end = size > 0 ? start + size : matches.length;

                allRows.forEach(function (row) { row.style.display = 'none'; });
                matches.slice(start, end).forEach(function (row) { row.style.display = ''; });

                if (infoEl) {
                    infoEl.textContent = matches.length === 0
                        ? 'No matching rows'
                        : 'Showing ' + (start + 1) + '–' + Math.min(end, matches.length) + ' of ' + matches.length;
                }
                if (prevBtn) prevBtn.disabled = page <= 1;
                if (nextBtn) nextBtn.disabled = page >= totalPages;
            }

            if (searchInput) searchInput.addEventListener('input', function () { page = 1; render(); });
            if (pageSizeSelect) pageSizeSelect.addEventListener('change', function () { page = 1; render(); });
            if (prevBtn) prevBtn.addEventListener('click', function () { page--; render(); });
            if (nextBtn) nextBtn.addEventListener('click', function () { page++; render(); });

            render();
        }
    </script>
</body>
</html>
