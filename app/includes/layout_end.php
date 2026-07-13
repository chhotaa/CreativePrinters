        </main>
    </div>
    <script>
        function filterTable(input, tableId) {
            var filter = input.value.trim().toLowerCase();
            var rows = document.getElementById(tableId).querySelectorAll('tbody tr');
            rows.forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
