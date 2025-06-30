    </div>
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted"><?php echo APP_NAME; ?> &copy; <?php echo date('Y'); ?></span>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // Универсальная пагинация для всех таблиц
    
    $(document).ready(function() {
        var rowsPerPage = 10; // Можно изменить на нужное количество строк на странице
        $('table').each(function(tableIndex, table) {
            var $table = $(table);
            var $tbody = $table.find('tbody');
            var $rows = $tbody.find('tr');
            if ($rows.length <= rowsPerPage) return; // Не добавлять пагинацию, если строк мало

            var pageCount = Math.ceil($rows.length / rowsPerPage);
            var $pagination = $('<nav class="table-pagination"><ul class="pagination justify-content-center"></ul></nav>');
            for (var i = 0; i < pageCount; i++) {
                $pagination.find('ul').append('<li class="page-item"><a class="page-link" href="#">' + (i+1) + '</a></li>');
            }
            $table.after($pagination);

            function showPage(page) {
                $rows.hide();
                $rows.slice(page * rowsPerPage, (page + 1) * rowsPerPage).show();
                $pagination.find('li').removeClass('active');
                $pagination.find('li').eq(page).addClass('active');
            }
            showPage(0);

            $pagination.find('a').click(function(e) {
                e.preventDefault();
                var page = $(this).parent().index();
                showPage(page);
            });
        });
    });
    

    // Универсальная функция экспорта таблицы в Excel, совместимая с пагинацией и заменой input на значения
    function exportTableToExcel(tableID, filename = '') {
        const dataType = 'application/vnd.ms-excel';
        const table = document.getElementById(tableID);

        // 1. Сохраняем скрытые строки (если есть пагинация)
        let hiddenRows = [];
        $(table).find('tbody tr').each(function() {
            if ($(this).css('display') === 'none') {
                hiddenRows.push(this);
                $(this).show();
            }
        });

        // 2. Клонируем таблицу и заменяем input на значения
        let clonedTable = table.cloneNode(true);
        $(clonedTable).find('input').each(function() {
            var value = $(this).val();
            var textNode = document.createTextNode(value);
            $(this).replaceWith(textNode);
        });

        // 3. Экспортируем всю таблицу
        let tableHTML = clonedTable.outerHTML.replace(/ /g, '%20');
        filename = filename ? filename + '.xls' : 'excel_data.xls';

        const downloadLink = document.createElement("a");
        document.body.appendChild(downloadLink);

        if (navigator.msSaveOrOpenBlob) {
            const blob = new Blob(['\ufeff', tableHTML], { type: dataType });
            navigator.msSaveOrOpenBlob(blob, filename);
        } else {
            downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
            downloadLink.download = filename;
            downloadLink.click();
        }

        // 4. Возвращаем скрытие строк обратно
        hiddenRows.forEach(function(row) {
            $(row).hide();
        });
    }
    </script>
</body>
</html> 