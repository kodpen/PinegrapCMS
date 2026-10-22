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
 *   options optional; for a file read for editing (each sheet of a workbook,
 *           or the document, carries `edit`, the file its `version`):
 *             editable  true lets the cells or the paragraphs the server
 *                       allows be changed
 *             onChange  called with the number of changes whenever it may
 *                       have moved: a value kept or undone, a key typed
 *             onSave    called on Ctrl+S / Cmd+S inside the view
 *
 * The view returned (for a workbook, and for a document read for editing)
 * tells the caller what was changed: view.kind ('xlsx' or 'docx'),
 * view.count(), view.edits() for the save request ([{sheet, name, cell,
 * value}] for a workbook, [{p, text}] for a document), view.version, and
 * view.reset() to drop every change. What is being typed counts already, so
 * a Save pressed before Enter sends it too. Nothing is saved from here; the
 * window that drew the view sends the edits.
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

    // The areas a sheet's cells are checked against (merges, array
    // formulas, tables, pivot tables), listed under each shown row they
    // cover, so a cell looks only at those of its own row. The server sends
    // them cut to the rows and columns shown; the rows come in order.
    function rangeIndex(sheet) {
        var index = {};
        var ranges = (sheet.edit && sheet.edit.ranges) || [];
        var shown = sheet.rows.map(function (row) { return row.n; });

        ranges.forEach(function (range) {
            var low = 0;
            var high = shown.length;

            while (low < high) {
                var middle = (low + high) >> 1;

                if (shown[middle] < range[0]) {
                    low = middle + 1;
                } else {
                    high = middle;
                }
            }

            for (var place = low; place < shown.length && shown[place] <= range[2]; place++) {
                (index[shown[place]] = index[shown[place]] || []).push(range);
            }
        });

        return index;
    }

    // Whether a cell of a protected sheet is unlocked: by its own style when
    // the file holds the cell (open, or closed when its row or column would
    // say otherwise), else by the style a new cell takes, the row's own
    // first, then the column's, else the default style (edit.other), as the
    // save gives it (pg_office_xlsx_new_cell_style()).
    function openOf(edit, address, row, column) {
        if (edit.open && own(edit.open, address)) {
            return true;
        }

        if (edit.closed && own(edit.closed, address)) {
            return false;
        }

        if (edit.rows && own(edit.rows, row)) {
            return !!edit.rows[row];
        }

        var columns = edit.columns || [];

        for (var place = 0; place < columns.length; place++) {
            if (column >= columns[place][0] && column <= columns[place][1]) {
                return true;
            }
        }

        var closedColumns = edit.closed_columns || [];

        for (var spot = 0; spot < closedColumns.length; spot++) {
            if (column >= closedColumns[spot][0] && column <= closedColumns[spot][1]) {
                return false;
            }
        }

        return !!edit.other;
    }

    // Why a cell of a sheet may not be changed, as the server sorted it
    // (includes/fn/office_edit.php); '' when it may. The server checks the
    // same again when the edit is saved.
    function lockOf(sheet, row, column, index) {
        var edit = sheet.edit;

        if (!edit || !edit.ok) {
            return 'sheet';
        }

        var address = columnName(column) + row;

        if (edit.locked && own(edit.locked, address)) {
            return edit.locked[address];
        }

        if (edit.protected && !openOf(edit, address, row, column)) {
            return 'protected';
        }

        var ranges = (index && index[row]) || [];

        for (var place = 0; place < ranges.length; place++) {
            var range = ranges[place];

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

        // A column opened by its style holds no cell in the file until one
        // is typed into it, so a protected sheet is drawn out to the last
        // such column (the server cuts the ranges to PG_OFFICE_MAX_COLUMNS).
        // Rows and the default style do not widen it.
        if (state && sheet.edit && sheet.edit.ok && sheet.edit.protected) {
            (sheet.edit.columns || []).forEach(function (range) {
                width = Math.max(width, Math.min(range[1] + 1, 200));
            });
        }

        var table = el('table', 'office-sheet');
        var index = state ? rangeIndex(sheet) : null;
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
                    var lock = lockOf(sheet, row.n, column, index);

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

        // The changes as they stand, with the value of the cell being typed
        // in: Save is offered, and sends it, before Enter.
        function changesNow() {
            var list = changes.map(function (sheetChanges) {
                var copy = {};

                Object.keys(sheetChanges).forEach(function (address) {
                    copy[address] = sheetChanges[address];
                });

                return copy;
            });

            var editor = shown.editor;

            if (editor) {
                var position = cellPosition(editor.td);
                var address = columnName(position.column) + position.row;
                var value = String(editor.input.value).replace(/\r\n?/g, '\n');

                if (value === original(office.sheets[shown.index], position.row, position.column)) {
                    delete list[shown.index][address];
                } else {
                    list[shown.index][address] = value;
                }
            }

            return list;
        }

        function count() {
            return changesNow().reduce(function (total, sheetChanges) {
                return total + Object.keys(sheetChanges).length;
            }, 0);
        }

        function notify() {
            drawStatus();

            if (options && typeof options.onChange === 'function') {
                options.onChange(count());
            }
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

            var list = changesNow();

            Array.prototype.forEach.call(tabs.querySelectorAll('.office-tab'), function (tab, index) {
                tab.classList.toggle('office-tab-edited', Object.keys(list[index]).length > 0);
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

            notify();
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
                notify();
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
                notify();
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

                // A key that finishes a word in an input method belongs to it.
                if (event.isComposing || event.keyCode === 229) {
                    return;
                }

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
            kind: 'xlsx',
            version: office.version || '',
            count: count,
            edits: function () {
                var list = [];

                changesNow().forEach(function (sheetChanges, index) {
                    Object.keys(sheetChanges).forEach(function (address) {
                        list.push({ sheet: index, name: office.sheets[index].name, cell: address, value: sheetChanges[address] });
                    });
                });

                return list;
            },
            reset: function () {
                shown.editor = null;
                changes = office.sheets.map(function () { return {}; });

                if (shown.index >= 0) {
                    show(shown.index);
                }

                notify();
            }
        };
    }

    // Why a paragraph of a document may not be changed, in words.
    function paragraphTitle(reason, strings) {
        if (reason === 'merged') {
            return strings.locked_merged || '';
        }

        return strings['para_' + reason] || strings.para_other || '';
    }

    // How many characters of a paragraph's text come before a point in its
    // drawing: text counts as it is, a tab (drawn as a space) and a line
    // break as one character each, the way they stand in the text the
    // server sent. -1 when the point is not in the paragraph.
    function textOffset(root, node, offset) {
        var count = 0;

        function length(current) {
            if (current.nodeType === 3) {
                return current.nodeValue.length;
            }

            if (current.nodeName === 'BR') {
                return 1;
            }

            var total = 0;

            for (var child = current.firstChild; child; child = child.nextSibling) {
                total += length(child);
            }

            return total;
        }

        function walk(current) {
            if (current === node) {
                if (current.nodeType === 3) {
                    count += offset;
                } else {
                    for (var index = 0; index < offset && index < current.childNodes.length; index++) {
                        count += length(current.childNodes[index]);
                    }
                }

                return true;
            }

            if (current.nodeType !== 1 || current.nodeName === 'BR') {
                count += length(current);
                return false;
            }

            for (var child = current.firstChild; child; child = child.nextSibling) {
                if (walk(child)) {
                    return true;
                }
            }

            return false;
        }

        return walk(root) ? count : -1;
    }

    // Where in a paragraph's text a click landed, as [start, end]: the words
    // the browser selected (the second click of a double click selects one),
    // or the point clicked. null when it cannot be told.
    function pointedText(node, event) {
        var selection = window.getSelection ? window.getSelection() : null;
        var start = -1;
        var end = -1;

        if (selection && selection.rangeCount) {
            var range = selection.getRangeAt(0);

            if (node.contains(range.startContainer) && node.contains(range.endContainer)) {
                start = textOffset(node, range.startContainer, range.startOffset);
                end = textOffset(node, range.endContainer, range.endOffset);
            }
        }

        if ((start < 0 || end < 0) && event) {
            var caret = null;

            if (document.caretPositionFromPoint) {
                var position = document.caretPositionFromPoint(event.clientX, event.clientY);
                caret = position ? { node: position.offsetNode, offset: position.offset } : null;
            } else if (document.caretRangeFromPoint) {
                var point = document.caretRangeFromPoint(event.clientX, event.clientY);
                caret = point ? { node: point.startContainer, offset: point.startOffset } : null;
            }

            if (caret && node.contains(caret.node)) {
                start = end = textOffset(node, caret.node, caret.offset);
            }
        }

        return (start < 0 || end < 0) ? null : [Math.min(start, end), Math.max(start, end)];
    }

    // A paragraph's own text drawn plain, tabs and line breaks the way the
    // server draws them; an empty one keeps its line.
    function drawText(node, text) {
        node.textContent = '';

        if (text === '') {
            node.appendChild(document.createTextNode(' '));
            return;
        }

        text.split('\n').forEach(function (line, index) {
            if (index > 0) {
                node.appendChild(document.createElement('br'));
            }

            line.split('\t').forEach(function (piece, position) {
                if (position > 0) {
                    node.appendChild(el('span', 'office-tabchar', ' '));
                }

                if (piece !== '') {
                    node.appendChild(document.createTextNode(piece));
                }
            });
        });
    }

    // A document as the safe HTML the server made. Read for editing, the
    // paragraphs it marked (data-p) may be given a new text, except those it
    // marked as holding more than text (data-lock); the server checks the
    // same again when the edit is saved. A paragraph is picked with a click
    // and opened with another (or Enter, or F2); what it is given shows plain
    // until the file is saved, when the server keeps the formatting of the
    // words that did not change.
    function renderDocument(container, office, strings, options) {
        var doc = el('div', 'office-doc');
        var status = el('div', 'office-note office-status');
        var edit = office.edit || null;
        var editable = !!(options && options.editable) && !!edit;
        var texts = (edit && edit.ok && edit.paragraphs) ? edit.paragraphs : {};

        // By position: the text typed, and the server's drawing of a
        // paragraph opened, to put back when its change is undone.
        var changes = {};
        var drawn = {};

        // The paragraphs marked, the one picked, the one pressed on while it
        // was already picked, the open editor, and what the status line adds.
        var nodes = [];
        var shown = { node: null, pressed: null, editor: null, note: '' };

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

        if (!editable) {
            return null;
        }

        function normal(value) {
            return String(value).replace(/\r\n?/g, '\n');
        }

        // The changes as they stand, with the text of the paragraph being
        // typed in: Save is offered, and sends it, before Enter.
        function changesNow() {
            var list = {};

            Object.keys(changes).forEach(function (position) {
                list[position] = changes[position];
            });

            if (shown.editor) {
                var position = shown.editor.position;
                var value = normal(shown.editor.input.value);

                if (value === texts[position]) {
                    delete list[position];
                } else {
                    list[position] = value;
                }
            }

            return list;
        }

        function count() {
            return Object.keys(changesNow()).length;
        }

        function drawStatus() {
            var parts = [];
            var total = count();

            if (!edit.ok) {
                parts.push(strings['doc_' + edit.why] || strings.doc_unusual || '');
            } else if (nodes.length) {
                parts.push(strings.doc_hint || '');
            }

            if (total > 0) {
                parts.push(fill(strings.doc_changed, total) + (strings.doc_format ? ' ' + strings.doc_format : ''));
            }

            if (shown.note) {
                parts.push(shown.note);
            }

            status.textContent = parts.filter(Boolean).join(' · ');
        }

        function notify() {
            drawStatus();

            if (options && typeof options.onChange === 'function') {
                options.onChange(count());
            }
        }

        function save() {
            if (options && typeof options.onSave === 'function') {
                options.onSave();
            }
        }

        function paragraphOf(target) {
            var node = (target && target.closest) ? target.closest('[data-p]') : null;

            return (node && doc.contains(node)) ? node : null;
        }

        function select(node, scroll) {
            if (!node) {
                return;
            }

            if (shown.node && shown.node !== node) {
                shown.node.classList.remove('office-selected');
                shown.node.tabIndex = -1;
            }

            shown.node = node;
            node.classList.add('office-selected');
            node.tabIndex = 0;
            node.focus({ preventScroll: true });

            if (scroll !== false && node.scrollIntoView) {
                node.scrollIntoView({ block: 'nearest' });
            }

            // Why a picked paragraph stays as it is, for screens without a
            // pointer to show its title.
            shown.note = node.classList.contains('office-locked') ? (node.title || '') : '';
            drawStatus();
        }

        // The marked paragraph before or after one, in the order they read.
        function beside(node, step) {
            var index = nodes.indexOf(node);

            return (index < 0) ? null : (nodes[index + step] || null);
        }

        // Draws a paragraph as it stands: its new text, or the server's
        // drawing when it has none.
        function redraw(node) {
            var position = node.getAttribute('data-p');

            if (own(changes, position)) {
                drawText(node, changes[position]);
            } else if (own(drawn, position)) {
                node.innerHTML = drawn[position];
            }

            node.classList.toggle('office-edited', own(changes, position));
        }

        // Remembers a paragraph's text as a change, unless it is the file's
        // own text again.
        function keep(node, value) {
            var position = node.getAttribute('data-p');

            value = normal(value);

            if (value === texts[position]) {
                delete changes[position];
            } else {
                changes[position] = value;
            }

            redraw(node);
            notify();
        }

        function closeEditor(save) {
            var editor = shown.editor;

            if (!editor) {
                return;
            }

            shown.editor = null;
            editor.node.classList.remove('office-editing');

            if (save) {
                keep(editor.node, editor.input.value);
            } else {
                redraw(editor.node);
                notify();
            }
        }

        // The box grows with its text, so the paragraph keeps its place.
        function fit(input) {
            input.style.height = 'auto';
            input.style.height = input.scrollHeight + 'px';
        }

        function openEditor(node, span) {
            if (!node || node.classList.contains('office-locked') || (shown.editor && shown.editor.node === node)) {
                return;
            }

            closeEditor(true);
            select(node, false);

            var position = node.getAttribute('data-p');
            var input = el('textarea', 'office-editor office-para-editor');

            if (!own(drawn, position)) {
                drawn[position] = node.innerHTML;
            }

            input.value = own(changes, position) ? changes[position] : texts[position];
            input.rows = 1;
            input.setAttribute('aria-label', strings.doc_editor || '');

            shown.editor = { node: node, position: position, input: input };
            node.classList.add('office-editing');
            node.textContent = '';
            node.appendChild(input);
            fit(input);
            input.focus({ preventScroll: true });

            var length = input.value.length;

            input.setSelectionRange(span ? Math.min(span[0], length) : length, span ? Math.min(span[1], length) : length);

            input.addEventListener('input', function () {
                fit(input);
                notify();
            });

            input.addEventListener('blur', function () {
                if (shown.editor && shown.editor.input === input) {
                    closeEditor(true);
                }
            });

            input.addEventListener('keydown', function (event) {
                // The keys end here: the paragraphs and the panel's own
                // shortcuts must not see them while a paragraph is typed in.
                event.stopPropagation();

                // A key that finishes a word in an input method belongs to it.
                if (event.isComposing || event.keyCode === 229) {
                    return;
                }

                var key = event.key;

                if ((event.ctrlKey || event.metaKey) && (key === 's' || key === 'S')) {
                    event.preventDefault();
                    closeEditor(true);
                    select(node, false);
                    save();
                    return;
                }

                // Shift+Enter is a line break inside the paragraph, as a
                // word processor takes it: the box types it itself.
                if (key === 'Enter' && event.shiftKey) {
                    return;
                }

                if (key === 'Enter' || key === 'Tab') {
                    event.preventDefault();
                    closeEditor(true);
                    select(beside(node, (key === 'Tab' && event.shiftKey) ? -1 : 1) || node);
                    return;
                }

                if (key === 'Escape') {
                    event.preventDefault();
                    closeEditor(false);
                    select(node, false);
                }
            });
        }

        Array.prototype.forEach.call(doc.querySelectorAll('[data-p]'), function (node) {
            var position = node.getAttribute('data-p');
            var lock = node.getAttribute('data-lock') || (own(texts, position) ? '' : 'other');

            node.tabIndex = -1;
            nodes.push(node);

            if (lock) {
                node.classList.add('office-locked');
                node.setAttribute('aria-readonly', 'true');
                node.title = paragraphTitle(lock, strings);
                return;
            }

            node.classList.add('office-para');

            Array.prototype.forEach.call(node.querySelectorAll('a[href]'), function (link) {
                link.title = strings.doc_link || '';
            });
        });

        // One stop for the Tab key; the arrows go on from there.
        if (nodes.length) {
            nodes[0].tabIndex = 0;
            doc.classList.add('office-doc-edit');
        }

        doc.addEventListener('mousedown', function (event) {
            if (shown.editor && shown.editor.node.contains(event.target)) {
                return;
            }

            var node = paragraphOf(event.target);

            shown.pressed = (node && node === shown.node) ? node : null;

            if (node) {
                select(node, false);
            }
        });

        doc.addEventListener('click', function (event) {
            if (shown.editor && shown.editor.node.contains(event.target)) {
                return;
            }

            var node = paragraphOf(event.target);
            var link = event.target.closest ? event.target.closest('a[href]') : null;
            var follow = !!link && (event.ctrlKey || event.metaKey);

            // A link in a paragraph that can be changed is followed with Ctrl
            // or Cmd held, as in a word processor; a plain click edits.
            if (link && node && !follow && !node.classList.contains('office-locked')) {
                event.preventDefault();
            }

            // A click on the paragraph already picked opens it, where the
            // click landed (with the word a double click selected).
            if (node && node === shown.pressed && !follow) {
                openEditor(node, pointedText(node, event));
            }

            shown.pressed = null;
        });

        doc.addEventListener('keydown', function (event) {
            if (shown.editor || !shown.node) {
                return;
            }

            var key = event.key;

            if ((event.ctrlKey || event.metaKey) && (key === 's' || key === 'S')) {
                event.preventDefault();
                event.stopPropagation();
                save();
                return;
            }

            if (event.target !== shown.node) {
                return;
            }

            if (key === 'ArrowDown' || key === 'ArrowUp') {
                event.preventDefault();
                event.stopPropagation();
                select(beside(shown.node, (key === 'ArrowDown') ? 1 : -1) || shown.node);
                return;
            }

            if (key === 'Enter' || key === 'F2') {
                event.preventDefault();
                event.stopPropagation();
                openEditor(shown.node, null);
            }
        });

        drawStatus();
        container.appendChild(status);

        return {
            kind: 'docx',
            version: office.version || '',
            count: count,
            edits: function () {
                var list = changesNow();

                return Object.keys(list).map(Number).sort(function (a, b) {
                    return a - b;
                }).map(function (position) {
                    return { p: position, text: list[position] };
                });
            },
            reset: function () {
                closeEditor(false);

                var positions = Object.keys(changes);

                changes = {};

                positions.forEach(function (position) {
                    var node = doc.querySelector('[data-p="' + position + '"]');

                    if (node) {
                        redraw(node);
                    }
                });

                notify();
            }
        };
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
                return renderDocument(container, office, strings, options || null);
            }

            return null;
        }
    };
})();
