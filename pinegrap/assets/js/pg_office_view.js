/**
 * Pinegrap - Enterprise Website Platform
 *
 * Draws a Word or Excel file the server read for a preview
 * (includes/fn/office.php): a workbook as sheet tabs over a table, a
 * document as the safe HTML the server made. Loaded on first use by the file
 * manager and the Workspace; nothing here reads a file itself.
 *
 *   var view = window.pgOfficeView.render(container, office, strings, options)
 *
 *   office  what explorer_office_preview / ws_file_office answered
 *   strings the words of pg_office_view_strings(), already translated
 *   options optional; for a workbook read for editing (each sheet carries
 *           `edit`, the workbook its `version`):
 *             editable  true lets the cells the server allows be changed
 *             onChange  called when a cell's value is kept or undone
 *             onSave    called on Ctrl+S / Cmd+S inside the sheet
 *
 * The view returned (for a workbook) tells the caller what was changed:
 * view.count(), view.edits() as [{sheet, name, cell, value}] for the save
 * request, view.version, and view.reset() to drop every change. Nothing is
 * saved from here; the window that drew the view sends the edits.
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

    function own(object, key) {
        return Object.prototype.hasOwnProperty.call(object, key);
    }

    function fill(text, value) {
        return String(text || '').replace('{1}', value);
    }

    function isNumber(value) {
        return value !== '' && !isNaN(value) && !isNaN(parseFloat(value));
    }

    // Why a cell of a sheet may not be changed, as the server sorted it
    // (includes/fn/office_edit.php); '' when it may. The server checks the
    // same again when the edit is saved.
    function lockOf(sheet, row, column) {
        var edit = sheet.edit;

        if (!edit || !edit.ok) {
            return 'sheet';
        }

        var address = columnName(column) + row;

        if (edit.locked && own(edit.locked, address)) {
            return edit.locked[address];
        }

        if (edit.protected && !(edit.open && own(edit.open, address))) {
            return 'protected';
        }

        var ranges = edit.ranges || [];

        for (var index = 0; index < ranges.length; index++) {
            var range = ranges[index];

            if (row < range[0] || row > range[2] || column < range[1] || column > range[3]) {
                continue;
            }

            // The top left cell of a merge is the one that shows.
            if (range[4] === 'merged' && row === range[0] && column === range[1]) {
                continue;
            }

            return range[4];
        }

        return '';
    }

    function lockTitle(reason, strings, formula) {
        switch (reason) {
            case 'formula':
                return formula ? fill(strings.locked_formula_is, '=' + formula) : (strings.locked_formula || '');
            case 'value':
                return strings.locked_value || '';
            case 'merged':
                return strings.locked_merged || '';
            case 'table':
                return strings.locked_table || '';
            case 'pivot':
                return strings.locked_pivot || '';
            case 'array':
                return strings.locked_array || '';
            case 'protected':
                return strings.locked_protected || '';
        }

        return '';
    }

    // What a sheet that offers no cell says about itself.
    function sheetNote(edit, strings) {
        if (!edit) {
            return '';
        }

        if (!edit.ok) {
            return strings['sheet_' + edit.why] || strings.sheet_unusual || '';
        }

        return edit.protected ? (strings.sheet_protected || '') : '';
    }

    // One sheet as a table. With `state` (a sheet offered for editing) the
    // cells carry what the keyboard and the mouse need: the row number on
    // each row, and the column by the cell's place in it.
    function sheetTable(sheet, strings, state) {
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

            if (state) {
                tr.setAttribute('data-row', row.n);
            }

            for (var column = 0; column < width; column++) {
                var value = (column < row.cells.length) ? row.cells[column] : '';
                var td = el('td');

                if (state) {
                    var address = columnName(column) + row.n;
                    var lock = lockOf(sheet, row.n, column);

                    if (own(state.changes, address)) {
                        value = state.changes[address];
                        td.classList.add('office-edited');
                    }

                    if (lock) {
                        td.classList.add('office-locked');
                        td.setAttribute('aria-readonly', 'true');
                        td.title = lockTitle(lock, strings, (sheet.edit && sheet.edit.formulas) ? sheet.edit.formulas[address] : '');
                    }

                    td.tabIndex = -1;
                }

                td.textContent = value;

                // Numbers sit to the right, as the sheet shows them.
                if (isNumber(value)) {
                    td.classList.add('office-num');
                }

                // A formula whose result was not stored: worked out by the
                // spreadsheet program that opens the file.
                if (value === '' && row.calc && row.calc.indexOf(column) !== -1) {
                    td.classList.add('office-calc');

                    if (!td.title) {
                        td.title = strings.calc || '';
                    }
                }

                tr.appendChild(td);
            }

            body.appendChild(tr);
        });

        table.appendChild(body);

        if (state) {
            table.setAttribute('role', 'grid');
        }

        return table;
    }

    function renderBook(container, office, strings, options) {
        var book = el('div', 'office-book');
        var tabs = el('div', 'office-tabs');
        var pane = el('div', 'office-pane');
        var status = el('div', 'office-note office-status');
        var current = null;
        var editable = !!(options && options.editable);

        // Per sheet, by position: address -> the value typed.
        var changes = office.sheets.map(function () { return {}; });

        // The sheet on show, and in it the selected cell and the open editor.
        var shown = { index: -1, table: null, cell: null, editor: null, note: '' };

        function count() {
            return changes.reduce(function (total, sheetChanges) {
                return total + Object.keys(sheetChanges).length;
            }, 0);
        }

        function sheetEditable(index) {
            var edit = office.sheets[index].edit;

            return editable && !!edit && !!edit.ok;
        }

        // The value the file holds for a cell, as the server showed it.
        function original(sheet, row, column) {
            for (var index = 0; index < sheet.rows.length; index++) {
                if (sheet.rows[index].n === row) {
                    return (column < sheet.rows[index].cells.length) ? sheet.rows[index].cells[column] : '';
                }
            }

            return '';
        }

        function cellPosition(td) {
            var tr = td.parentNode;

            return { row: parseInt(tr.getAttribute('data-row'), 10), column: td.cellIndex - 1 };
        }

        function drawStatus() {
            if (!editable) {
                return;
            }

            var parts = [];
            var sheetEdit = (shown.index >= 0) ? office.sheets[shown.index].edit : null;
            var note = sheetNote(sheetEdit, strings);

            if (note) {
                parts.push(note);
            } else if (sheetEditable(shown.index)) {
                parts.push(strings.edit_hint || '');
            }

            if (count() > 0) {
                parts.push(fill(strings.changed, count()) + (strings.recalc ? ' ' + strings.recalc : ''));
            }

            if (shown.note) {
                parts.push(shown.note);
            }

            status.textContent = parts.filter(Boolean).join(' · ');
            status.classList.toggle('d-none', status.textContent === '');

            Array.prototype.forEach.call(tabs.querySelectorAll('.office-tab'), function (tab, index) {
                tab.classList.toggle('office-tab-edited', Object.keys(changes[index]).length > 0);
            });
        }

        function select(td) {
            if (!td || td.tagName !== 'TD') {
                return;
            }

            if (shown.cell && shown.cell !== td) {
                shown.cell.classList.remove('office-selected');
                shown.cell.setAttribute('aria-selected', 'false');
                shown.cell.tabIndex = -1;
            }

            shown.cell = td;
            td.classList.add('office-selected');
            td.setAttribute('aria-selected', 'true');
            td.tabIndex = 0;
            td.focus({ preventScroll: true });

            if (td.scrollIntoView) {
                td.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }
        }

        // The cell beside a cell: one row or one column over, if there is one.
        function neighbour(td, rows, columns) {
            var tr = td.parentNode;
            var target = tr;

            if (rows !== 0) {
                target = (rows > 0) ? tr.nextElementSibling : tr.previousElementSibling;
            }

            if (!target) {
                return null;
            }

            var index = td.cellIndex + columns;

            if (index < 1 || index >= target.cells.length) {
                return null;
            }

            return target.cells[index];
        }

        function shownValue(td) {
            var position = cellPosition(td);
            var address = columnName(position.column) + position.row;
            var sheetChanges = changes[shown.index];

            return own(sheetChanges, address) ? sheetChanges[address] : original(office.sheets[shown.index], position.row, position.column);
        }

        // Puts a value in a cell, remembers it as a change unless it is the
        // file's own value again, and redraws the cell.
        function keep(td, value) {
            var sheet = office.sheets[shown.index];
            var position = cellPosition(td);
            var address = columnName(position.column) + position.row;
            var was = original(sheet, position.row, position.column);

            value = String(value).replace(/\r\n?/g, '\n');

            if (value === was) {
                delete changes[shown.index][address];
            } else {
                changes[shown.index][address] = value;
            }

            td.textContent = value;
            td.classList.toggle('office-edited', own(changes[shown.index], address));
            td.classList.toggle('office-num', isNumber(value));
            td.classList.remove('office-calc');

            // A leading "=" stays text: no formula is worked out here.
            shown.note = (value.charAt(0) === '=') ? (strings.formula_text || '') : '';

            drawStatus();

            if (options && typeof options.onChange === 'function') {
                options.onChange(count());
            }
        }

        function closeEditor(save) {
            var editor = shown.editor;

            if (!editor) {
                return;
            }

            shown.editor = null;
            editor.td.classList.remove('office-editing');

            if (save) {
                keep(editor.td, editor.input.value);
            } else {
                editor.td.textContent = editor.before;
            }
        }

        function openEditor(td, replacement) {
            if (!td || !sheetEditable(shown.index) || td.classList.contains('office-locked')) {
                return;
            }

            closeEditor(true);
            select(td);

            var before = shownValue(td);
            var input = el('textarea', 'office-editor');

            input.value = (replacement !== undefined) ? replacement : before;
            input.rows = Math.min(8, input.value.split('\n').length);
            input.setAttribute('aria-label', columnName(cellPosition(td).column) + cellPosition(td).row);
            input.spellcheck = false;

            shown.editor = { td: td, input: input, before: before };
            td.textContent = '';
            td.classList.add('office-editing');
            td.appendChild(input);
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);

            input.addEventListener('input', function () {
                input.rows = Math.min(8, input.value.split('\n').length);
            });

            input.addEventListener('blur', function () {
                if (shown.editor && shown.editor.input === input) {
                    closeEditor(true);
                }
            });

            input.addEventListener('keydown', function (event) {
                // The keys end here: the grid and the panel's own shortcuts
                // must not see them while a cell is being typed in.
                event.stopPropagation();

                var key = event.key;

                if ((event.ctrlKey || event.metaKey) && (key === 's' || key === 'S')) {
                    event.preventDefault();
                    closeEditor(true);
                    select(td);

                    if (options && typeof options.onSave === 'function') {
                        options.onSave();
                    }
                    return;
                }

                if (key === 'Enter' && (event.altKey || event.shiftKey)) {
                    // A second line in the cell, as a spreadsheet takes it.
                    event.preventDefault();

                    var start = input.selectionStart;
                    var end = input.selectionEnd;

                    input.value = input.value.slice(0, start) + '\n' + input.value.slice(end);
                    input.setSelectionRange(start + 1, start + 1);
                    input.rows = Math.min(8, input.value.split('\n').length);
                    return;
                }

                if (key === 'Enter' || key === 'Tab') {
                    event.preventDefault();
                    closeEditor(true);

                    var next = (key === 'Enter') ? neighbour(td, event.shiftKey ? -1 : 1, 0) : neighbour(td, 0, event.shiftKey ? -1 : 1);
                    select(next || td);
                    return;
                }

                if (key === 'Escape') {
                    event.preventDefault();
                    closeEditor(false);
                    select(td);
                }
            });
        }

        function wireGrid(table) {
            table.addEventListener('mousedown', function (event) {
                var td = event.target.closest('td');

                if (td && !td.classList.contains('office-editing')) {
                    select(td);
                }
            });

            table.addEventListener('dblclick', function (event) {
                var td = event.target.closest('td');

                if (td) {
                    openEditor(td);
                }
            });

            table.addEventListener('keydown', function (event) {
                var td = shown.cell;

                if (!td || shown.editor) {
                    return;
                }

                var key = event.key;
                var moves = { ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1] };

                if ((event.ctrlKey || event.metaKey) && (key === 's' || key === 'S')) {
                    event.preventDefault();
                    event.stopPropagation();

                    if (options && typeof options.onSave === 'function') {
                        options.onSave();
                    }
                    return;
                }

                if (own(moves, key)) {
                    event.preventDefault();
                    select(neighbour(td, moves[key][0], moves[key][1]) || td);
                    return;
                }

                if (key === 'Tab') {
                    var beside = neighbour(td, 0, event.shiftKey ? -1 : 1);

                    if (beside) {
                        event.preventDefault();
                        select(beside);
                    }
                    return;
                }

                if (key === 'Enter' || key === 'F2') {
                    event.preventDefault();
                    openEditor(td);
                    return;
                }

                if ((key === 'Delete' || key === 'Backspace') && sheetEditable(shown.index) && !td.classList.contains('office-locked')) {
                    event.preventDefault();
                    keep(td, '');
                    return;
                }

                // Typing over a selected cell starts its new value, the way
                // a spreadsheet does.
                if (key && key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                    if (sheetEditable(shown.index) && !td.classList.contains('office-locked')) {
                        event.preventDefault();
                        openEditor(td, key);
                    }
                }
            });
        }

        function show(index) {
            closeEditor(true);

            var sheet = office.sheets[index];
            var offered = editable && !!sheet.edit;

            shown.index = index;
            shown.cell = null;
            shown.note = '';
            pane.innerHTML = '';

            if (!sheet.rows.length) {
                pane.appendChild(el('div', 'office-note', strings.empty || ''));
                shown.table = null;
            } else {
                shown.table = sheetTable(sheet, strings, offered ? { changes: changes[index] } : null);
                pane.appendChild(shown.table);

                if (offered) {
                    wireGrid(shown.table);
                }
            }

            if (sheet.truncated) {
                pane.appendChild(el('div', 'office-note', strings.truncated || ''));
            }

            drawStatus();
        }

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
                show(index);
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

        if (editable) {
            book.appendChild(status);
        }

        container.appendChild(book);

        if (current) {
            current.click();
        }

        return {
            version: office.version || '',
            count: count,
            edits: function () {
                var list = [];

                changes.forEach(function (sheetChanges, index) {
                    Object.keys(sheetChanges).forEach(function (address) {
                        list.push({ sheet: index, name: office.sheets[index].name, cell: address, value: sheetChanges[address] });
                    });
                });

                return list;
            },
            reset: function () {
                changes = office.sheets.map(function () { return {}; });

                if (shown.index >= 0) {
                    show(shown.index);
                }
            }
        };
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

        return null;
    }

    window.pgOfficeView = {
        render: function (container, office, strings, options) {
            strings = strings || {};
            container.innerHTML = '';
            container.classList.add('office-view');

            if (office && office.kind === 'xlsx') {
                return renderBook(container, office, strings, options || null);
            }

            if (office && office.kind === 'docx') {
                return renderDocument(container, office, strings);
            }

            return null;
        }
    };
})();
