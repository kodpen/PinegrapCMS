/**
 * Pinegrap - Enterprise Website Platform
 *
 * Draws a Word or Excel file the server read for a preview
 * (includes/fn/office.php): a workbook as sheet tabs over a table, a
 * document as the safe HTML the server made. Loaded on first use by the file
 * manager and the Workspace; nothing here reads a file itself.
 *
 *   window.pgOfficeView.render(container, office, strings)
 *
 *   office  what explorer_office_preview / ws_file_office answered
 *   strings {hidden, truncated, images_lost, empty}, already translated
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }

        return node;
    }

    // 0 is A, 25 is Z, 26 is AA.
    function columnName(index) {
        var name = '';

        index += 1;

        while (index > 0) {
            var rest = (index - 1) % 26;
            name = String.fromCharCode(65 + rest) + name;
            index = Math.floor((index - 1) / 26);
        }

        return name;
    }

    function sheetTable(sheet) {
        var width = 0;

        sheet.rows.forEach(function (row) {
            width = Math.max(width, row.cells.length);
        });

        var table = el('table', 'office-sheet');
        var head = el('thead');
        var headRow = el('tr');

        headRow.appendChild(el('th', 'office-corner', ''));

        for (var column = 0; column < width; column++) {
            headRow.appendChild(el('th', '', columnName(column)));
        }

        head.appendChild(headRow);
        table.appendChild(head);

        var body = el('tbody');

        sheet.rows.forEach(function (row) {
            var tr = el('tr');
            tr.appendChild(el('th', '', row.n));

            for (var column = 0; column < width; column++) {
                var value = (column < row.cells.length) ? row.cells[column] : '';
                var td = el('td', '', value);

                // Numbers sit to the right, as the sheet shows them.
                if (value !== '' && !isNaN(value) && !isNaN(parseFloat(value))) {
                    td.className = 'office-num';
                }

                tr.appendChild(td);
            }

            body.appendChild(tr);
        });

        table.appendChild(body);

        return table;
    }

    function renderBook(container, office, strings) {
        var book = el('div', 'office-book');
        var tabs = el('div', 'office-tabs');
        var pane = el('div', 'office-pane');
        var current = null;

        office.sheets.forEach(function (sheet, index) {
            var tab = el('button', 'office-tab' + (sheet.hidden ? ' office-tab-hidden' : ''), sheet.name || ('#' + (index + 1)));
            tab.type = 'button';

            if (sheet.hidden) {
                tab.title = strings.hidden || '';
            }

            tab.addEventListener('click', function () {
                Array.prototype.forEach.call(tabs.querySelectorAll('.office-tab'), function (other) {
                    other.classList.remove('active');
                });
                tab.classList.add('active');
                pane.innerHTML = '';

                if (!sheet.rows.length) {
                    pane.appendChild(el('div', 'office-note', strings.empty || ''));
                } else {
                    pane.appendChild(sheetTable(sheet));
                }

                if (sheet.truncated) {
                    pane.appendChild(el('div', 'office-note', strings.truncated || ''));
                }
            });

            tabs.appendChild(tab);

            // The first sheet that is not hidden opens; failing that, the first.
            if (current === null && !sheet.hidden) {
                current = tab;
            }
        });

        if (current === null && tabs.firstChild) {
            current = tabs.firstChild;
        }

        book.appendChild(tabs);
        book.appendChild(pane);
        container.appendChild(book);

        if (current) {
            current.click();
        }
    }

    function renderDocument(container, office, strings) {
        var doc = el('div', 'office-doc');

        // The server made this markup itself from the file's text, escaped,
        // with its own small set of tags; nothing the file wrote reaches here
        // as markup.
        doc.innerHTML = office.html || '';

        if (!office.html) {
            doc.appendChild(el('div', 'office-note', strings.empty || ''));
        }

        container.appendChild(doc);

        if (office.images_lost > 0) {
            container.appendChild(el('div', 'office-note', (strings.images_lost || '').replace('{1}', office.images_lost)));
        }

        if (office.truncated) {
            container.appendChild(el('div', 'office-note', strings.truncated || ''));
        }
    }

    window.pgOfficeView = {
        render: function (container, office, strings) {
            strings = strings || {};
            container.innerHTML = '';
            container.classList.add('office-view');

            if (office && office.kind === 'xlsx') {
                renderBook(container, office, strings);
            } else if (office && office.kind === 'docx') {
                renderDocument(container, office, strings);
            }
        }
    };
})();
