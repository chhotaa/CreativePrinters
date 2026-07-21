// Reusable autocomplete widget. Zero dependencies, self-styling.
// Usage:
//   attachAutocomplete(inputElement, ['ABC Traders', 'XYZ Ltd', ...]);
//
// Behavior:
//   - Substring match, case-insensitive.
//   - Arrow keys navigate, Enter selects, Escape closes.
//   - Click to select.
//   - When the typed text doesn't exactly match any item, a
//     "+ Create new: ..." hint appears so the user knows they're
//     about to add a new master record instead of picking an
//     existing one.
//   - If the user submits without picking, the typed text is
//     submitted as-is (the server-side auto-create handles it).

(function () {
    // Inject styles once.
    if (!document.getElementById('ac-styles')) {
        var style = document.createElement('style');
        style.id = 'ac-styles';
        style.textContent = [
            '.ac-wrap { position: relative; display: inline-block; }',
            '.ac-dropdown { position: absolute; top: 100%; left: 0; right: 0; min-width: 100%; z-index: 50;',
            '  background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; margin-top: 2px;',
            '  box-shadow: 0 4px 12px rgba(15,23,42,0.12); max-height: 240px; overflow-y: auto; }',
            '.ac-dropdown.hidden { display: none; }',
            '.ac-item { padding: 6px 10px; font-size: 13px; cursor: pointer; color: #1e293b; }',
            '.ac-item:hover, .ac-item.ac-active { background: #ecfdf5; color: #065f46; }',
            '.ac-new { padding: 6px 10px; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0;',
            '  background: #f8fafc; font-style: italic; }',
            '.ac-new b { font-style: normal; color: #0f172a; }'
        ].join('\n');
        document.head.appendChild(style);
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    window.attachAutocomplete = function (input, items) {
        if (!input || !Array.isArray(items)) return;

        var wrap = document.createElement('span');
        wrap.className = 'ac-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var list = document.createElement('div');
        list.className = 'ac-dropdown hidden';
        wrap.appendChild(list);

        var activeIndex = -1;
        var currentMatches = [];

        function render() {
            var q = input.value.trim().toLowerCase();
            if (q === '') {
                list.classList.add('hidden');
                currentMatches = [];
                return;
            }
            currentMatches = items.filter(function (n) {
                return n.toLowerCase().indexOf(q) !== -1;
            }).slice(0, 8);

            var exact = items.some(function (n) { return n.toLowerCase() === q; });
            var html = '';
            currentMatches.forEach(function (n, i) {
                html += '<div class="ac-item ' + (i === activeIndex ? 'ac-active' : '') +
                        '" data-i="' + i + '">' + escapeHtml(n) + '</div>';
            });
            if (!exact) {
                html += '<div class="ac-new">+ Create new: "<b>' +
                        escapeHtml(input.value.trim()) + '</b>"</div>';
            }
            list.innerHTML = html;
            list.classList.remove('hidden');
        }

        function select(i) {
            if (i < 0 || i >= currentMatches.length) return;
            input.value = currentMatches[i];
            list.classList.add('hidden');
            activeIndex = -1;
        }

        input.addEventListener('input', function () { activeIndex = -1; render(); });
        input.addEventListener('focus', render);
        // Delay hide so click-to-select on a dropdown item still fires.
        input.addEventListener('blur', function () {
            setTimeout(function () { list.classList.add('hidden'); }, 150);
        });

        input.addEventListener('keydown', function (e) {
            if (list.classList.contains('hidden') || currentMatches.length === 0) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, currentMatches.length - 1);
                render();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, -1);
                render();
            } else if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                select(activeIndex);
            } else if (e.key === 'Escape') {
                list.classList.add('hidden');
            }
        });

        list.addEventListener('mousedown', function (e) {
            var item = e.target.closest('.ac-item');
            if (item) {
                e.preventDefault();
                select(parseInt(item.dataset.i, 10));
            }
        });
    };
})();
