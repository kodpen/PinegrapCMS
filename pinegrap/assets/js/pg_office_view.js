/**
 * Pinegrap - Enterprise Website Platform
 *
 * Draws a Word or Excel file the server read for a preview
 * (includes/fn/office.php): a workbook as sheet tabs over a table, a
 * document as the safe HTML the server made, a .csv or .tsv as a sheet under
 * the choice of its separator and character set, an .ods as a workbook only
 * looked at (kind 'ods', options not taken), an .xls the same way under the
 * button that saves a copy as .xlsx (kind 'xls'), a presentation as its
 * slides drawn by the image editor's painter under the tabs Slides and Text,
 * only looked at (kind 'pptx'). Loaded on first use by the file manager and
 * the Workspace; nothing here reads a file itself.
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
 *           for a .csv (kind 'csv', only looked at):
 *             reread    function (choice, done): reads the file again with
 *                       choice.delimiter and choice.encoding and calls
 *                       done(office) or done(null, message); without it
 *                       the choice is not drawn
 *           for a presentation (kind 'pptx', only looked at):
 *             slidePictures function (n, done): asks the window's door for
 *                       slide n's pictures and calls done(answer) with
 *                       {pictures: {layer id: data: address}} or done(null);
 *                       without it, or without the image editor on the
 *                       page, the text list alone is drawn
 *           for an .xls (kind 'xls', only looked at):
 *             saveCopy  function (done): saves a copy as .xlsx and calls
 *                       done(true, message) or done(false, message);
 *                       without it the button is not drawn
 *
 * The view returned (for a workbook, and for a document read for editing)
 * tells the caller what was changed: view.kind ('xlsx' or 'docx'),
 * view.count(), view.edits() for the save request (for a workbook one
 * list in the order the changes were made: the typed cells, {sheet, name,
 * cell, value}, the format ranges chosen from the bar above the grid, {op:
 * format, sheet, name, range, set}, the rows and columns inserted or
 * deleted from a head menu, {sheet, name, op, row} / {sheet, name, op,
 * column}, and the sheets added, renamed, deleted or moved from the tabs,
 * {op: sheet_add, to} / {op: sheet_rename, sheet, name, to} / {op:
 * sheet_delete, sheet, name} / {op: sheet_move, sheet, name, to}, each
 * position and name the workbook's at that point of the list; [{p, text}]
 * for a document),
 * view.version, and
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

    // The formula twin (assets/js/pg_office_formula.js) sits beside this
    // file and is fetched with the same stamp on first use, so every screen
    // that loads the view gets it without a tag of its own.
    var formulaScript = (typeof document !== 'undefined' && document.currentScript && document.currentScript.src)
        ? document.currentScript.src.replace(/pg_office_view\.js(?=$|[?#])/, 'pg_office_formula.js') : '';
    var formulaWaiting = [];

    // Calls done() once the twin is there, or could not be fetched.
    function withFormulas(done) {
        if (window.pgOfficeFormula || !formulaScript || typeof document === 'undefined') {
            done();
            return;
        }

        formulaWaiting.push(done);

        if (formulaWaiting.length > 1) {
            return;
        }

        var script = document.createElement('script');

        script.src = formulaScript;
        script.onload = script.onerror = function () {
            var list = formulaWaiting;

            formulaWaiting = [];
            list.forEach(function (call) { call(); });
        };
        document.head.appendChild(script);
    }

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

    // A sheet's last row and column count (Excel's grid, as the server's
    // PG_OFFICE_SHIFT_ROWS and PG_OFFICE_SHIFT_COLUMNS).
    var SHEET_ROWS = 1048576;
    var SHEET_COLUMNS = 16384;

    // The empty grid an added sheet is drawn as, the size of a new
    // spreadsheet's (PG_OFFICE_NEW_ROWS, PG_OFFICE_NEW_COLUMNS).
    var NEW_ROWS = 30;
    var NEW_COLUMNS = 10;

    // The most cells one format change takes (PG_OFFICE_EDIT_MAX_CHANGES).
    var FORMAT_CELLS = 5000;

    // The most characters a cell holds (Excel's limit, the save's).
    var CELL_CHARACTERS = 32767;

    // The colours the format bar offers, by the word that names each. They
    // are written into the file, so they are the same in either theme.
    var TEXT_COLOURS = [['000000', 'black'], ['6B7280', 'grey'], ['DC2626', 'red'], ['EA580C', 'orange'], ['CA8A04', 'yellow'],
        ['16A34A', 'green'], ['0D9488', 'teal'], ['2563EB', 'blue'], ['7C3AED', 'violet'], ['DB2777', 'pink']];
    var FILL_COLOURS = [['FFFF00', 'bright_yellow'], ['F3F4F6', 'light_grey'], ['FEE2E2', 'light_red'], ['FFEDD5', 'light_orange'], ['FEF9C3', 'light_yellow'],
        ['DCFCE7', 'light_green'], ['CCFBF1', 'light_teal'], ['DBEAFE', 'light_blue'], ['EDE9FE', 'light_violet'], ['FCE7F3', 'light_pink']];

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

    // The most values a column's filter menu lists.
    var FILTER_VALUES = 300;

    // The value a cell holds: a change typed into it (changes, by address)
    // first, else the file's.
    function cellValue(row, column, changes) {
        var address = columnName(column) + row.n;

        if (changes && own(changes, address)) {
            return String(changes[address]);
        }

        return (column < row.cells.length) ? String(row.cells[column]) : '';
    }

    // The text a cell shows, as the grid draws it: a change typed into it,
    // else its number format's text (row.show), else its value.
    function cellText(row, column, changes) {
        if (changes && own(changes, columnName(column) + row.n)) {
            return cellValue(row, column, changes);
        }

        if (row.show && own(row.show, column)) {
            return String(row.show[column]);
        }

        return cellValue(row, column, null);
    }

    function plainCompare(one, two) {
        return (one < two) ? -1 : ((one > two) ? 1 : 0);
    }

    // Two cell values in sort order: numbers first by size, then text by
    // `compare`; an empty cell is last either way, so `down` turns only the
    // rest around.
    function compareValues(one, two, down, compare) {
        if (one === '' || two === '') {
            return (one === '') ? ((two === '') ? 0 : 1) : -1;
        }

        var first = isNumber(one);
        var second = isNumber(two);
        var result;

        if (first && second) {
            result = parseFloat(one) - parseFloat(two);
        } else if (first !== second) {
            result = first ? -1 : 1;
        } else {
            result = (compare || plainCompare)(one, two);
        }

        return down ? -result : result;
    }

    // Whether a sheet's first row reads as its header: it holds text and no
    // number, and more rows follow. Sorting and filtering leave it on top.
    function headerRow(rows, changes) {
        if (rows.length < 2) {
            return false;
        }

        var filled = false;

        for (var column = 0; column < rows[0].cells.length; column++) {
            var value = cellValue(rows[0], column, changes);

            if (isNumber(value)) {
                return false;
            }

            filled = filled || value.trim() !== '';
        }

        return filled;
    }

    // The order a sheet's rows are shown in, as indexes into `rows`: those
    // `view.filters` leaves ({column: {text: true}}, the texts hidden),
    // sorted by `view.sort` ({column, down}) and kept in the file's order
    // for equal values. The header row stays first.
    function arrange(rows, view, changes, compare) {
        var order = rows.map(function (row, index) { return index; });
        var filters = (view && view.filters) || {};
        var columns = Object.keys(filters);
        var sort = view ? view.sort : null;

        if (!sort && !columns.length) {
            return order;
        }

        var head = headerRow(rows, changes) ? order.splice(0, 1) : [];

        order = order.filter(function (index) {
            return columns.every(function (column) {
                return !own(filters[column], cellText(rows[index], parseInt(column, 10), changes));
            });
        });

        if (sort) {
            var keys = {};

            order.forEach(function (index) {
                keys[index] = cellValue(rows[index], sort.column, changes);
            });

            order.sort(function (one, two) {
                return compareValues(keys[one], keys[two], !!sort.down, compare) || (one - two);
            });
        }

        return head.concat(order);
    }

    // The texts a column shows under its header, each once, in sort order;
    // `more` is true when there were more than FILTER_VALUES.
    function columnValues(rows, column, changes, compare) {
        var seen = {};
        var list = [];

        rows.forEach(function (row, index) {
            if (index === 0 && headerRow(rows, changes)) {
                return;
            }

            var text = cellText(row, column, changes);

            if (!own(seen, text)) {
                seen[text] = true;
                list.push({ text: text, value: cellValue(row, column, changes) });
            }
        });

        list.sort(function (one, two) {
            return compareValues(one.value, two.value, false, compare);
        });

        return {
            texts: list.slice(0, FILTER_VALUES).map(function (item) { return item.text; }),
            more: list.length > FILTER_VALUES
        };
    }

    function sheetWidth(sheet) {
        return sheet.rows.reduce(function (width, row) {
            return Math.max(width, row.cells.length);
        }, 0);
    }

    // Every cell of a workbook whose shown text holds `query`, whatever the
    // case: [{sheet, row (its number), column}], sheet by sheet in the order
    // its rows are shown (orders[sheet]), row by row, left to right.
    // `lower` lowers a text's case in the page's language.
    function findCells(sheets, query, orders, changes, lower) {
        lower = lower || function (text) { return text.toLowerCase(); };

        var needle = lower(String(query));
        var found = [];

        if (needle === '') {
            return found;
        }

        sheets.forEach(function (sheet, index) {
            var width = sheetWidth(sheet);
            var sheetChanges = changes ? changes[index] : null;

            (orders[index] || []).forEach(function (place) {
                var row = sheet.rows[place];

                for (var column = 0; column < width; column++) {
                    if (lower(cellText(row, column, sheetChanges)).indexOf(needle) !== -1) {
                        found.push({ sheet: index, row: row.n, column: column });
                    }
                }
            });
        });

        return found;
    }

    // One CSV field. A text a spreadsheet would take as a formula (=, +, -,
    // @, a tab or a return first) gets an apostrophe in front, unless it is
    // a number as shown (-12,50 or -₺1.234); a field holding the comma, a
    // quote or a line break is quoted.
    function csvField(text) {
        text = String(text);

        if (/^[=+\-@\t\r]/.test(text) && !/^[+-]?[^\d=+\-@|!()]{0,3}[\d.,\s\u00a0]*\d[\d.,\s\u00a0]*[^\d=+\-@|!()]{0,3}$/.test(text)) {
            text = '\'' + text;
        }

        return /[",\r\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    }

    // A sheet as CSV, as the grid shows it: the rows in `order`, each cell's
    // shown text, as wide as the last column holding a text; comma, CRLF,
    // and a byte order mark so that a spreadsheet reads it as UTF-8.
    function csvText(rows, order, changes) {
        var lines = order.map(function (place) {
            var row = rows[place];
            var width = row.cells.length;
            var cells = [];

            Object.keys(changes || {}).forEach(function (address) {
                var match = /^([A-Z]+)(\d+)$/.exec(address);

                if (match && parseInt(match[2], 10) === row.n) {
                    width = Math.max(width, columnIndex(match[1]) + 1);
                }
            });

            for (var column = 0; column < width; column++) {
                cells.push(cellText(row, column, changes));
            }

            return cells;
        });

        var width = lines.reduce(function (most, cells) {
            for (var column = cells.length; column > most; column--) {
                if (cells[column - 1] !== '') {
                    return column;
                }
            }

            return most;
        }, 0);

        return '\ufeff' + lines.map(function (cells) {
            var fields = [];

            for (var column = 0; column < width; column++) {
                fields.push(csvField((column < cells.length) ? cells[column] : ''));
            }

            return fields.join(',');
        }).join('\r\n') + '\r\n';
    }

    // A file name for a sheet's CSV: the sheet's name without the
    // characters a file name may not hold.
    function csvName(name) {
        var stem = String(name || '').replace(/[\\\/:*?"<>|\u0000-\u001f\u007f]+/g, '_').replace(/^[\s.]+|[\s.]+$/g, '');

        return (stem || 'sheet') + '.csv';
    }

    // A column's letters as its 0-based index: A is 0, AA is 26.
    function columnIndex(letters) {
        var index = 0;

        for (var at = 0; at < letters.length; at++) {
            index = index * 26 + (letters.charCodeAt(at) - 64);
        }

        return index - 1;
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

    // The formula the file holds in a cell the grid may change (a simple
    // one, which the save works out again), as stored: English names, no
    // "=". '' for a cell without one, or one locked.
    function fileFormulaOf(sheet, address) {
        var edit = sheet && sheet.edit;

        if (!edit || !edit.formulas || !own(edit.formulas, address) || (edit.locked && own(edit.locked, address))) {
            return '';
        }

        return String(edit.formulas[address]);
    }

    // A cell's text as a formula reads it, as the save stores it: a number
    // (a typed one with a dot or a lone comma, no leading zero, 15 digits
    // at most), TRUE/FALSE and an error code as the file shows them, else
    // text; null for empty.
    function sheetValue(text, typed) {
        text = String(text);

        if (text === '') {
            return null;
        }

        if (!typed && (text === 'TRUE' || text === 'FALSE')) {
            return text === 'TRUE';
        }

        if (!typed && /^#(?:DIV\/0!|VALUE!|REF!|NAME\?|N\/A|NUM!|NULL!)$/.test(text)) {
            return { error: text };
        }

        var plain = typed ? text.trim().replace(/^([+-]?\d*),(\d+)$/, '$1.$2') : text;

        if (!/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/.test(plain)) {
            return text;
        }

        if (typed && (/^[+-]?0\d/.test(plain) || plain.replace(/[eE].*$/, '').replace(/\D/g, '').length > 15)) {
            return text;
        }

        var number = parseFloat(plain);

        return isFinite(number) ? number : text;
    }

    // A worked-out value as the cell shows it.
    function resultText(value, formula) {
        if (value !== null && typeof value === 'object') {
            return String(value.error);
        }

        if (typeof value === 'boolean') {
            return value ? 'TRUE' : 'FALSE';
        }

        if (typeof value === 'number') {
            return formula ? formula.numberText(value) : String(value);
        }

        return String(value);
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

    // The font sizes the Size choice offers, in points (the format entry
    // takes whole points from 8 to 72).
    var FONT_SIZES = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 36, 48, 72];

    // The size the grid's own text stands for: a workbook's default font is
    // 11 points in Excel and in the product's new spreadsheets, and a look
    // carries a size only when it is not the default's.
    var BASE_SIZE = 11;

    // The width a wrapped cell wraps at in a column with no width of its
    // own, which is as wide as its widest text.
    var WRAP_WIDTH = '12rem';

    // A column's width in characters of the file's first font and a row's
    // height in points, drawn in pixels as a spreadsheet draws them at
    // 100 %: a character of the default font (Calibri 11) is 7 px wide and
    // a column adds 5 px of padding; a point is 4/3 px. A drawn size is
    // read back inside the limits the save takes (office_size.php).
    var CHAR_PX = 7;
    var CHAR_PAD = 5;
    var MAX_WIDTH = 255;
    var MAX_HEIGHT = 409;

    function widthPx(chars) {
        return Math.round(chars * CHAR_PX + CHAR_PAD);
    }

    function heightPx(points) {
        return Math.round(points * 4 / 3);
    }

    function sizeIn(value, low, high) {
        return Math.min(high, Math.max(low, Math.round(value * 100) / 100));
    }

    function widthOf(px) {
        return sizeIn((px - CHAR_PAD) / CHAR_PX, 0.5, MAX_WIDTH);
    }

    function heightOf(px) {
        return sizeIn(px * 3 / 4, 1, MAX_HEIGHT);
    }

    // The borders the Borders menu offers, in its order.
    var BORDERS = [['none', 'bi-border'], ['all', 'bi-border-all'], ['outline', 'bi-border-outer'], ['bottom', 'bi-border-bottom']];

    // The sides of one cell a border chosen for a range sets, as the server
    // writes them (pg_office_format_border_sides()): side => true for a
    // line, false for none; a side left out keeps what the cell has. "all"
    // lines every side of every cell, "none" takes every side off,
    // "outline" lines the range's outer edges, "bottom" the bottom edge of
    // its last row. `range` is [first row, first column, last row, last
    // column].
    function borderSides(border, range, row, column) {
        var sides = {};

        if (border === 'all' || border === 'none') {
            ['left', 'right', 'top', 'bottom'].forEach(function (side) {
                sides[side] = (border === 'all');
            });

            return sides;
        }

        if (border === 'outline') {
            if (column === range[1]) {
                sides.left = true;
            }

            if (column === range[3]) {
                sides.right = true;
            }

            if (row === range[0]) {
                sides.top = true;
            }
        }

        if ((border === 'outline' || border === 'bottom') && row === range[2]) {
            sides.bottom = true;
        }

        return sides;
    }

    // A range's format as one of its cells takes it: a border becomes the
    // sides it sets on that cell (`sides`), the rest stays.
    function cellSet(set, range, row, column) {
        if (!own(set, 'border')) {
            return set;
        }

        var out = {};

        Object.keys(set).forEach(function (key) {
            if (key !== 'border') {
                out[key] = set[key];
            }
        });

        out.sides = borderSides(set.border, range, row, column);

        return out;
    }

    // A cell format as the controls change it (bold, italic, underline,
    // strike, size, wrap, color, fill, align, number, and a cell's border
    // `sides`), laid over a look of the file (pg_office_xlsx_style_looks()):
    // `bd` holds the sides drawn as l, r, t and b in that order.
    function lookWith(look, set) {
        var out = {};
        var keys = { bold: 'b', italic: 'i', underline: 'u', strike: 'st', size: 'sz', wrap: 'w', color: 'c', fill: 'bg', align: 'a', number: 'n' };

        Object.keys(look || {}).forEach(function (key) {
            out[key] = look[key];
        });

        Object.keys(set || {}).forEach(function (key) {
            var value = set[key];

            if (key === 'sides') {
                var drawn = '';

                [['l', 'left'], ['r', 'right'], ['t', 'top'], ['b', 'bottom']].forEach(function (side) {
                    var on = own(value, side[1]) ? !!value[side[1]] : String(out.bd || '').indexOf(side[0]) !== -1;

                    drawn += on ? side[0] : '';
                });

                if (drawn) {
                    out.bd = drawn;
                } else {
                    delete out.bd;
                }

                return;
            }

            if (!own(keys, key)) {
                return;
            }

            if (value === false || value === '' || (key === 'number' && value === 'general')) {
                delete out[keys[key]];
            } else if (key === 'size') {
                out.sz = Number(value);
            } else {
                out[keys[key]] = (value === true) ? 1 : String(value).replace(/^#/, '');
            }
        });

        return out;
    }

    // Draws a cell's look. A colour of the file is drawn on paper, as a
    // spreadsheet draws it: a text colour with no fill on white, a fill with
    // no text colour under black, so that neither is lost in the dark theme.
    // A changed cell keeps the colours that mark it. A size is drawn against
    // the grid's text as the default's; the border's sides are drawn by the
    // stylesheet from the cell's office-bd-* classes, black on paper.
    function paint(td, look) {
        var style = td.style;
        var paper = !!(look && (look.c || look.bg)) && !td.classList.contains('office-edited');
        var lines = [];

        look = look || {};
        style.fontWeight = look.b ? 'bold' : '';
        style.fontStyle = look.i ? 'italic' : '';
        style.textDecorationLine = [look.u ? 'underline' : '', look.st ? 'line-through' : ''].join(' ').trim();
        style.fontSize = look.sz ? (Math.round(look.sz / BASE_SIZE * 1000) / 1000) + 'em' : '';
        style.whiteSpace = look.w ? 'pre-wrap' : '';

        // A column with no width of its own takes its widest text, so a
        // wrapped cell there sets a width to wrap at (WRAP_WIDTH); a column
        // with a width holds every cell to it (data-width, in px).
        var fixed = td.getAttribute('data-width');

        style.width = fixed ? fixed + 'px' : (look.w ? WRAP_WIDTH : '');
        style.minWidth = style.width;
        style.maxWidth = fixed ? fixed + 'px' : '';
        style.textAlign = look.a || '';
        style.color = paper ? (look.c ? '#' + look.c : 'black') : '';

        // A cell of a row frozen on top (data-pinned) is opaque, so that the
        // rows scrolled under it do not show through.
        style.backgroundColor = paper ? (look.bg ? '#' + look.bg : 'white') : (td.hasAttribute('data-pinned') ? (td.classList.contains('office-edited') ? 'var(--pg-warning-bg-subtle)' : 'var(--pg-bg-body)') : '');

        ['l', 'r', 't', 'b'].forEach(function (side) {
            var on = String(look.bd || '').indexOf(side) !== -1;

            td.classList.toggle('office-bd-' + side, on);

            if (on) {
                lines.push(side);
            }
        });

        td.classList.toggle('office-bd', lines.length > 0);
        td.classList.toggle('office-bd-paper', lines.length > 0 && paper);
    }

    // Whether a cell lies in a range [first row, first column, last row,
    // last column].
    function inRange(range, row, column) {
        return !!range && row >= range[0] && row <= range[2] && column >= range[1] && column <= range[3];
    }

    // A workbook's waiting changes are one list in the order they were
    // made, as the save sends them: a typed cell ({sheet, cell, value}), a
    // format range ({sheet, range, set}), a row or column inserted or
    // deleted ({op: insert|delete, sheet, axis, at}; `at` a row number or a
    // 0-based column, the line inserted before) or a sheet change ({op:
    // sheet_add|sheet_rename|sheet_delete|sheet_move, sheet, to}). Every
    // position and sheet place is the workbook's at that point of the list,
    // as the server applies each on the workbook the one before left; the
    // grid and the tabs are drawn from the list (listBook()).

    // Whether a change moves cells or sheets, so that an address before it
    // names another cell than after it.
    function listMoves(step) {
        return !step.cell && !step.range && !sizeOps[step.op];
    }

    // A column's width, a row's height and the frozen top row: {op: width|
    // height|freeze, sheet, at, value}, `at` a 0-based column, a row number
    // or 0, `value` characters, points (null takes it off) or on/off. They
    // move no cell.
    var sizeOps = { width: true, height: true, freeze: true };

    // Sets a size. The same size set again at once is one change (a drag
    // and its correction); any other is a change of its own.
    function listSize(list, sheet, op, at, value) {
        var last = list.length ? list[list.length - 1] : null;

        if (last && last.op === op && last.sheet === sheet && last.at === at) {
            last.value = value;
        } else {
            list.push({ op: op, sheet: sheet, at: at, value: value });
        }

        return list;
    }

    // A sheet's sizes as the reader sent them (pg_office_size_out()),
    // copied: widths by 0-based column, the default width and height, the
    // number of rows frozen on top.
    function sheetSizes(sheet) {
        var sizes = (sheet && sheet.sizes) || {};

        return {
            widths: copyOf(sizes.widths),
            width: (sizes.width === undefined) ? null : sizes.width,
            height: (sizes.height === undefined) ? null : sizes.height,
            frozen: sizes.frozen || 0
        };
    }

    // A sheet with a size of the list laid on it: a row's height on its
    // row (`h`, points), a width and the frozen rows in `sizes`.
    function sizeSheet(sheet, step) {
        var out = copyOf(sheet);
        var sizes = sheetSizes(sheet);

        if (step.op === 'width') {
            if (step.value === null) {
                delete sizes.widths[step.at];
            } else {
                sizes.widths[step.at] = step.value;
            }
        } else if (step.op === 'height') {
            out.rows = sheet.rows.map(function (row) {
                if (row.n !== step.at) {
                    return row;
                }

                var copy = copyOf(row);

                if (step.value === null) {
                    delete copy.h;
                } else {
                    copy.h = step.value;
                }

                return copy;
            });
        } else {
            sizes.frozen = step.value ? 1 : 0;
        }

        out.sizes = sizes;
        out.changed = true;

        return out;
    }

    // Takes a typed cell out of the changes made since the last row, column
    // or sheet change, then, unless `value` is null, puts it at the end with
    // its value. A cell typed again moves to the end, so it is written over
    // every format laid on it before; the caller leaves `value` null when
    // the cell holds its value without the change.
    function listCell(list, sheet, address, value) {
        for (var index = list.length - 1; index >= 0 && !listMoves(list[index]); index--) {
            if (list[index].cell && list[index].sheet === sheet && list[index].cell === address) {
                list.splice(index, 1);
                break;
            }
        }

        if (value !== null && value !== undefined) {
            list.push({ sheet: sheet, cell: address, value: String(value) });
        }

        return list;
    }

    // Sets one part of a range's format. The parts chosen one after the
    // other for the same range are one change; any other range, or the same
    // range after something else, is a change of its own.
    function listFormat(list, sheet, range, key, value) {
        var last = list.length ? list[list.length - 1] : null;

        if (!last || !last.range || last.sheet !== sheet || last.range.join(',') !== range.join(',')) {
            last = { sheet: sheet, range: range.slice(), set: {} };
            list.push(last);
        }

        last.set[key] = value;

        return list;
    }

    // Puts many typed cells of one sheet into the list as listCell() puts
    // one: `cells` is [{row, column, value}] in the order they are entered.
    // Each cell's entry made since the last row, column or sheet change is
    // taken out first; a cell is then put at the end unless the workbook
    // the list leaves holds its value already. The caller makes it one
    // change of Undo.
    function listValues(sheets, list, sheet, cells) {
        var last = list.length - 1;
        var wanted = {};

        while (last >= 0 && !listMoves(list[last])) {
            last--;
        }

        cells.forEach(function (cell) {
            wanted[columnName(cell.column) + cell.row] = true;
        });

        for (var index = list.length - 1; index > last; index--) {
            if (list[index].cell && list[index].sheet === sheet && own(wanted, list[index].cell)) {
                list.splice(index, 1);
            }
        }

        var under = listBook(sheets, list);
        var values = under.values[sheet] || {};
        var byRow = {};

        under.sheets[sheet].rows.forEach(function (row) {
            byRow[row.n] = row;
        });

        cells.forEach(function (cell) {
            var address = columnName(cell.column) + cell.row;
            var row = own(byRow, cell.row) ? byRow[cell.row] : null;
            var formula = own(values, address) ? '' : fileFormulaOf(under.sheets[sheet], address);
            var was = own(values, address) ? values[address] : (formula ? '=' + formula : ((row && cell.column < row.cells.length) ? row.cells[cell.column] : ''));
            var value = String(cell.value).replace(/\r\n?/g, '\n');

            if (value !== was) {
                list.push({ sheet: sheet, cell: address, value: value });
            }
        });

        return list;
    }

    // The clipboard's tab-separated text as a spreadsheet program writes it
    // (Excel, LibreOffice, a browser's sheets, this grid): rows end with a
    // line break, values end with a tab, and a value holding either, or a
    // quote, is quoted with its quotes doubled. A quote that does not close
    // a whole value is read as text. The last line break ends the block,
    // so it adds no row. Rows of arrays of strings; none for no text.
    function clipRows(text) {
        text = String(text || '');

        var rows = [];
        var row = [];
        var at = 0;

        if (text === '') {
            return rows;
        }

        while (at <= text.length) {
            var value = null;
            var end = at;

            if (text.charAt(at) === '"') {
                var spot = at + 1;
                var quoted = '';

                while (spot < text.length) {
                    if (text.charAt(spot) === '"') {
                        if (text.charAt(spot + 1) === '"') {
                            quoted += '"';
                            spot += 2;
                            continue;
                        }

                        break;
                    }

                    quoted += text.charAt(spot);
                    spot++;
                }

                var after = text.charAt(spot + 1);

                if (spot < text.length && (after === '' || after === '\t' || after === '\n' || after === '\r')) {
                    value = quoted;
                    end = spot + 1;
                }
            }

            if (value === null) {
                end = at;

                while (end < text.length && '\t\r\n'.indexOf(text.charAt(end)) === -1) {
                    end++;
                }

                value = text.slice(at, end);
            }

            row.push(value);

            var mark = text.charAt(end);

            if (mark === '\t') {
                at = end + 1;
                continue;
            }

            rows.push(row);
            row = [];

            if (mark === '') {
                break;
            }

            at = end + ((mark === '\r' && text.charAt(end + 1) === '\n') ? 2 : 1);

            // The line break after the last row ends the block.
            if (at >= text.length) {
                break;
            }
        }

        return rows;
    }

    // Rows of values as the clipboard's tab-separated text (clipRows()
    // reads it back), with a spreadsheet program's CRLF between rows.
    function clipText(rows) {
        return rows.map(function (row) {
            return row.map(function (value) {
                value = String(value);

                return /[\t\r\n"]/.test(value) ? '"' + value.replace(/"/g, '""') + '"' : value;
            }).join('\t');
        }).join('\r\n');
    }

    // Why a block of values may not be pasted from a cell with `rows` drawn
    // rows and `columns` drawn columns from it on: 'room' when it reaches
    // past them, 'many' over a save's cells (PG_OFFICE_EDIT_MAX_CHANGES),
    // 'long' when a value passes a cell's characters; '' when it may.
    function clipCheck(block, rows, columns) {
        var wide = 0;
        var cells = 0;
        var long = false;

        block.forEach(function (row) {
            wide = Math.max(wide, row.length);
            cells += row.length;
            long = long || row.some(function (value) { return value.length > CELL_CHARACTERS; });
        });

        if (block.length > rows || wide > columns) {
            return 'room';
        }

        return clipCells(cells) || (long ? 'long' : '');
    }

    // 'many' when more cells change at once than a save takes.
    function clipCells(count) {
        return (count > FORMAT_CELLS) ? 'many' : '';
    }

    function listCount(list) {
        return list.length;
    }

    // A cell's look with the format parts laid on it (listBook()'s `sets`
    // of the cell), in order.
    function listLook(look, sets) {
        (sets || []).forEach(function (set) {
            look = lookWith(look, set);
        });

        return look || {};
    }

    // Where a row number or a column lands after a line is inserted before
    // `at` or deleted at it; null for the line deleted.
    function shiftAt(number, op, at) {
        if (number < at) {
            return number;
        }

        if (op === 'insert') {
            return number + 1;
        }

        return (number === at) ? null : number - 1;
    }

    // An address-keyed map with its addresses moved by a row or column
    // change; the cells of a deleted line leave it.
    function shiftKeys(map, axis, op, at) {
        var out = {};

        Object.keys(map || {}).forEach(function (address) {
            var parts = /^([A-Z]+)(\d+)$/.exec(address);

            if (!parts) {
                return;
            }

            var column = columnIndex(parts[1]);
            var row = parseInt(parts[2], 10);
            var moved = shiftAt((axis === 'row') ? row : column, op, at);

            if (moved !== null) {
                out[(axis === 'row') ? columnName(column) + moved : columnName(moved) + row] = map[address];
            }
        });

        return out;
    }

    // A column-keyed map of a row (its styles, its shown texts) moved by a
    // column change.
    function shiftColumns(map, op, at) {
        var out = {};

        Object.keys(map || {}).forEach(function (key) {
            var moved = shiftAt(parseInt(key, 10), op, at);

            if (moved !== null) {
                out[moved] = map[key];
            }
        });

        return out;
    }

    // An area [first row, first column, last row, last column, …] moved as
    // the server moves it: an insert inside widens it, a delete inside
    // narrows it; null when nothing of it is left.
    function shiftRange(range, axis, op, at) {
        var out = range.slice();
        var low = (axis === 'row') ? 0 : 1;
        var high = low + 2;

        if (op === 'insert') {
            out[low] += (out[low] >= at) ? 1 : 0;
            out[high] += (out[high] >= at) ? 1 : 0;
        } else {
            out[low] -= (out[low] > at) ? 1 : 0;
            out[high] -= (out[high] >= at) ? 1 : 0;
        }

        return (out[high] < out[low]) ? null : out;
    }

    function copyOf(object, skip) {
        var out = {};

        Object.keys(object || {}).forEach(function (key) {
            if (key !== skip) {
                out[key] = object[key];
            }
        });

        return out;
    }

    // A sheet with a row or a column inserted or deleted: its rows, cell
    // values, styles, shown texts, formula marks, locked cells and areas
    // moved, an inserted row drawn empty, an inserted line marked added.
    function shiftSheet(sheet, axis, op, at) {
        var out = copyOf(sheet, 'byRow');
        var width = Math.max(sheet.wide || 0, sheetWidth(sheet));
        var edit = sheet.edit ? copyOf(sheet.edit) : null;
        var added = sheet.added || { row: {}, column: {} };

        if (axis === 'row') {
            var rows = [];
            var placed = (op !== 'insert');

            sheet.rows.forEach(function (row) {
                var number = shiftAt(row.n, op, at);

                if (!placed && row.n >= at) {
                    rows.push({ n: at, cells: [] });
                    placed = true;
                }

                if (number === row.n) {
                    rows.push(row);
                } else if (number !== null) {
                    var moved = copyOf(row);

                    moved.n = number;
                    rows.push(moved);
                }
            });

            if (!placed) {
                rows.push({ n: at, cells: [] });
            }

            out.rows = rows;
        } else {
            out.rows = sheet.rows.map(function (row) {
                var copy = copyOf(row);

                copy.cells = row.cells.slice();

                if (at < copy.cells.length) {
                    if (op === 'insert') {
                        copy.cells.splice(at, 0, '');
                    } else {
                        copy.cells.splice(at, 1);
                    }
                }

                copy.s = row.s ? shiftColumns(row.s, op, at) : row.s;
                copy.show = row.show ? shiftColumns(row.show, op, at) : row.show;
                copy.calc = row.calc ? row.calc.map(function (column) { return shiftAt(column, op, at); }).filter(function (column) { return column !== null; }) : row.calc;

                return copy;
            });

            out.wide = (op === 'insert') ? Math.min(Math.max(width, at) + 1, SHEET_COLUMNS) : Math.max(0, width - ((at < width) ? 1 : 0));

            // Widths move with their columns. The file keeps a run of
            // columns of one width as one entry, which an insert inside
            // widens (pg_office_shift_span()): a column inserted between
            // two of the same width takes it.
            if (sheet.sizes) {
                var sizes = sheetSizes(sheet);
                var widths = shiftColumns(sizes.widths, op, at);

                if (op === 'insert' && at > 0 && own(sizes.widths, at - 1) && own(sizes.widths, at) && sizes.widths[at - 1] === sizes.widths[at]) {
                    widths[at] = sizes.widths[at];
                }

                sizes.widths = widths;
                out.sizes = sizes;
            }
        }

        if (edit) {
            edit.locked = shiftKeys(edit.locked, axis, op, at);
            edit.formulas = shiftKeys(edit.formulas, axis, op, at);

            // The save moves the references inside the file's formulas;
            // the texts here are the file's, so after a move they stay
            // locked and are not worked out here.
            Object.keys(edit.formulas).forEach(function (address) {
                if (!own(edit.locked, address)) {
                    edit.locked[address] = 'formula';
                }
            });
            edit.shifted = true;
            edit.ranges = (edit.ranges || []).map(function (range) { return shiftRange(range, axis, op, at); }).filter(Boolean);
            out.edit = edit;
        }

        out.added = { row: added.row, column: added.column };
        out.added[axis] = shiftColumns(added[axis], op, at);

        if (op === 'insert') {
            out.added[axis][at] = true;
        }

        return out;
    }

    // An added sheet as the grid draws it: an empty grid of the rows and
    // columns of a new spreadsheet (PG_OFFICE_NEW_ROWS, PG_OFFICE_NEW_COLUMNS),
    // every cell open; the server makes the rows a cell is typed into.
    function newSheet(name, id) {
        var rows = [];

        for (var number = 1; number <= NEW_ROWS; number++) {
            rows.push({ n: number, cells: [] });
        }

        return { id: id, name: name, rows: rows, wide: NEW_COLUMNS, edit: { ok: true }, changed: true };
    }

    function moveItem(list, from, to) {
        list.splice(to, 0, list.splice(from, 1)[0]);
    }

    // The workbook as the list leaves it, from the sheets the server sent:
    // {sheets, values, sets}. Each sheet is a copy with `id` (its place as
    // opened, or "new" and a number), its rows, name and edit as the list
    // leaves them, `changed` when a change of the list touches it, `added`
    // rows and columns; `values` holds per sheet the cells typed (address
    // => value), `sets` the format parts laid on each cell (address =>
    // [set]). Nothing the server sent is changed.
    function listBook(sheets, list) {
        var book = {
            sheets: sheets.map(function (sheet, index) {
                var copy = copyOf(sheet, 'byRow');

                copy.id = index;

                return copy;
            }),
            values: sheets.map(function () { return {}; }),
            sets: sheets.map(function () { return {}; })
        };
        var added = 0;

        list.forEach(function (step) {
            var at = step.sheet;

            if (step.cell) {
                if (book.sheets[at]) {
                    book.values[at][step.cell] = step.value;
                    book.sheets[at].changed = true;
                }
            } else if (step.range) {
                if (book.sheets[at]) {
                    for (var row = step.range[0]; row <= step.range[2]; row++) {
                        for (var column = step.range[1]; column <= step.range[3]; column++) {
                            var address = columnName(column) + row;

                            (book.sets[at][address] = book.sets[at][address] || []).push(cellSet(step.set, step.range, row, column));
                        }
                    }

                    book.sheets[at].changed = true;
                }
            } else if (step.op === 'insert' || step.op === 'delete') {
                if (book.sheets[at]) {
                    book.sheets[at] = shiftSheet(book.sheets[at], step.axis, step.op, step.at);
                    book.sheets[at].changed = true;
                    book.values[at] = shiftKeys(book.values[at], step.axis, step.op, step.at);
                    book.sets[at] = shiftKeys(book.sets[at], step.axis, step.op, step.at);
                }
            } else if (sizeOps[step.op]) {
                if (book.sheets[at]) {
                    book.sheets[at] = sizeSheet(book.sheets[at], step);
                }
            } else if (step.op === 'sheet_add') {
                book.sheets.push(newSheet(step.to, 'new' + (++added)));
                book.values.push({});
                book.sets.push({});
            } else if (book.sheets[at] && step.op === 'sheet_rename') {
                book.sheets[at] = copyOf(book.sheets[at]);
                book.sheets[at].name = step.to;
                book.sheets[at].changed = true;
            } else if (book.sheets[at] && step.op === 'sheet_delete') {
                book.sheets.splice(at, 1);
                book.values.splice(at, 1);
                book.sets.splice(at, 1);
            } else if (book.sheets[at] && step.op === 'sheet_move' && book.sheets[step.to]) {
                book.sheets[at].changed = true;
                moveItem(book.sheets, at, step.to);
                moveItem(book.values, at, step.to);
                moveItem(book.sets, at, step.to);
            }
        });

        return book;
    }

    // The save list: each change in its order, named by its sheet's name
    // at that point of the list (`names` the names as opened); `rangeName`
    // writes a range. The server inserts before the line it is sent.
    function listEdits(list, names, rangeName) {
        var out = [];

        names = names.slice();

        list.forEach(function (step) {
            var name = names[step.sheet];

            if (step.cell) {
                out.push({ sheet: step.sheet, name: name, cell: step.cell, value: step.value });
            } else if (step.range) {
                out.push({ op: 'format', sheet: step.sheet, name: name, range: rangeName(step.range), set: copyOf(step.set) });
            } else if (step.op === 'insert' || step.op === 'delete') {
                var change = { sheet: step.sheet, name: name, op: step.op };

                change[step.axis] = (step.axis === 'row') ? step.at : columnName(step.at);
                out.push(change);
            } else if (sizeOps[step.op]) {
                var size = { op: step.op, sheet: step.sheet, name: name };

                if (step.op === 'width') {
                    size.column = columnName(step.at);
                    size.width = step.value;
                } else if (step.op === 'height') {
                    size.row = step.at;
                    size.height = step.value;
                } else {
                    size.on = !!step.value;
                }

                out.push(size);
            } else if (step.op === 'sheet_add') {
                out.push({ op: 'sheet_add', to: step.to });
                names.push(step.to);
            } else if (step.op === 'sheet_rename') {
                out.push({ op: 'sheet_rename', sheet: step.sheet, name: name, to: step.to });
                names[step.sheet] = step.to;
            } else if (step.op === 'sheet_delete') {
                out.push({ op: 'sheet_delete', sheet: step.sheet, name: name });
                names.splice(step.sheet, 1);
            } else if (step.op === 'sheet_move') {
                out.push({ op: 'sheet_move', sheet: step.sheet, name: name, to: step.to });
                moveItem(names, step.sheet, step.to);
            }
        });

        return out;
    }

    // A row's or a column's head. A column's, with `tools`, is a button
    // that opens its menu (sort, filter, and with `state.heads` insert and
    // delete) and marks the sort and filter on it; a row's is one with
    // `state.heads` (insert and delete).
    function headCell(axis, at, label, strings, state, tools) {
        var shifts = !!(state && state.heads);
        var menu = (axis === 'column') ? (!!tools || shifts) : shifts;
        var th = el('th', '', menu ? '' : label);

        // A row or column inserted by a change waiting is marked on its head.
        if (state && state.added && state.added[axis] && own(state.added[axis], at)) {
            th.classList.add('office-head-added');
        }

        if (menu) {
            var button = el('button', 'office-head', label);
            var name = (axis === 'row') ? strings.head_row : (shifts ? strings.head_column : strings.head_column_view);

            button.type = 'button';
            button.tabIndex = -1;
            button.setAttribute('data-axis', axis);
            button.setAttribute('data-at', at);
            button.setAttribute('aria-haspopup', 'menu');
            button.setAttribute('aria-label', fill(name, label));

            if (axis === 'column' && tools && tools.sort && tools.sort.column === at) {
                th.setAttribute('aria-sort', tools.sort.down ? 'descending' : 'ascending');
                button.appendChild(headMark(tools.sort.down ? 'bi-sort-alpha-up-alt' : 'bi-sort-alpha-down'));
            }

            if (axis === 'column' && tools && tools.filters && own(tools.filters, at)) {
                button.appendChild(headMark('bi-funnel-fill'));
                button.setAttribute('aria-label', fill(name, label) + ', ' + (strings.filtered_mark || ''));
            }

            th.appendChild(button);
        }

        return th;
    }

    function headMark(icon) {
        var mark = el('span', 'bi ' + icon + ' office-head-mark');

        mark.setAttribute('aria-hidden', 'true');

        return mark;
    }

    // Holds a column's cell (its head or a cell) to a width in px.
    function sizeCell(cell, px) {
        cell.setAttribute('data-width', px);
        cell.style.width = px + 'px';
        cell.style.minWidth = px + 'px';
        cell.style.maxWidth = px + 'px';
    }

    // Draws a row at a height in px. A table row is never lower than its
    // text, so its cells lose their padding above and below to come close.
    function sizeRow(tr, px) {
        tr.style.height = px + 'px';

        Array.prototype.forEach.call(tr.cells, function (cell) {
            cell.style.paddingTop = '0';
            cell.style.paddingBottom = '0';
            cell.style.lineHeight = '1.2';
        });
    }

    // Keeps the rows marked data-pinned (sheetTable()) under the column
    // heads while the grid scrolls; each sits below the ones before it, so
    // it is placed once the table is laid out.
    function pinRows(table) {
        var head = table.tHead;
        var top = head ? head.offsetHeight : 0;

        Array.prototype.forEach.call(table.querySelectorAll('tbody tr[data-pinned]'), function (tr) {
            Array.prototype.forEach.call(tr.cells, function (cell, place) {
                cell.style.position = 'sticky';
                cell.style.top = top + 'px';
                cell.style.zIndex = place ? '1' : '2';
            });

            top += tr.offsetHeight;
        });

        return !head || head.offsetHeight > 0;
    }

    // One sheet as a table. With `state` (a sheet offered for editing) the
    // cells carry what the keyboard and the mouse need: the row number on
    // each row, and the column by the cell's place in it. Each cell is drawn
    // in its look (`looks`, by style number) and a number in its number
    // format's text (row.show), with the format ranges waiting laid over
    // it (state.look). `tools` ({order, sort, filters}) gives the
    // rows' order as sorted and filtered, marked on the column heads.
    function sheetTable(sheet, strings, state, looks, tools) {
        var width = sheet.wide || 0;

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
        var sizes = sheetSizes(sheet);

        headRow.appendChild(el('th', 'office-corner', ''));

        for (var column = 0; column < width; column++) {
            var th = headCell('column', column, columnName(column), strings, state, tools);

            if (own(sizes.widths, column)) {
                sizeCell(th, widthPx(sizes.widths[column]));
            }

            headRow.appendChild(th);
        }

        head.appendChild(headRow);
        table.appendChild(head);

        var body = el('tbody');

        var order = tools ? tools.order : sheet.rows.map(function (row, place) { return place; });

        // The rows frozen on top stay in view while the grid scrolls: the
        // ones the sheet shows first, as far as they are among them.
        var pinning = sizes.frozen > 0;

        order.forEach(function (place) {
            var row = sheet.rows[place];
            var tr = el('tr');
            var pinned = pinning = pinning && row.n <= sizes.frozen;

            tr.appendChild(headCell('row', row.n, String(row.n), strings, state));
            tr.setAttribute('data-row', row.n);

            if (pinned) {
                tr.setAttribute('data-pinned', '');
            }

            for (var column = 0; column < width; column++) {
                var value = (column < row.cells.length) ? row.cells[column] : '';
                var td = el('td');
                var drawn = null;

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
                    } else if (state.formula) {
                        // An open formula: its text on the title, and the
                        // value worked out here when a change reaches it.
                        drawn = state.formula(row.n, column);

                        if (drawn) {
                            td.title = drawn.title;
                        }
                    }

                    td.tabIndex = -1;
                }

                var edited = td.classList.contains('office-edited');
                var live = !!drawn && drawn.text !== null;

                td.textContent = live ? drawn.text : ((!edited && row.show && own(row.show, column)) ? row.show[column] : value);

                var look = (looks && row.s && own(row.s, column)) ? looks[row.s[column]] : null;

                if (state && state.look) {
                    look = state.look(row.n, column, look);
                }

                if (own(sizes.widths, column)) {
                    td.setAttribute('data-width', widthPx(sizes.widths[column]));
                }

                if (pinned) {
                    td.setAttribute('data-pinned', '');
                }

                if (look || td.hasAttribute('data-width') || pinned) {
                    paint(td, look);
                }

                // Numbers sit to the right, as the sheet shows them.
                if (live ? drawn.num : isNumber(value)) {
                    td.classList.add('office-num');
                }

                // A formula whose result was not stored: worked out by the
                // spreadsheet program that opens the file.
                if (live ? drawn.calc : (value === '' && row.calc && row.calc.indexOf(column) !== -1)) {
                    td.classList.add('office-calc');

                    if (!td.title) {
                        td.title = strings.calc || '';
                    }
                }

                tr.appendChild(td);
            }

            if (row.h !== undefined && row.h !== null) {
                sizeRow(tr, heightPx(row.h));
            }

            body.appendChild(tr);
        });

        table.appendChild(body);

        if (state) {
            table.setAttribute('role', 'grid');
        }

        return table;
    }

    // Each rendered book gets its own pane id, so two previews on one page
    // never share the id their sheet tabs point to.
    var bookCount = 0;

    function renderBook(container, office, strings, options) {
        var book = el('div', 'office-book');
        var tabs = el('div', 'office-tabs');
        var pane = el('div', 'office-pane');
        var paneId = 'office_pane_' + (++bookCount);
        var status = el('div', 'office-note office-status');
        var current = null;
        var editable = !!(options && options.editable);

        // The sheet on show, and in it the selected cell and the open editor.
        var shown = { index: -1, table: null, cell: null, editor: null, note: '' };
        var menu = null;

        // The tabs offer sheet changes when the server allows the
        // workbook's sheets to change.
        var sheetsEditable = editable && !!office.sheets_edit && !!office.sheets_edit.ok;
        var strip = sheetsEditable ? el('div', 'office-tabs') : null;
        var addButton = null;

        // Every change waiting, in the order it was made (listCell(),
        // listFormat(), and the rows, columns and sheets the menus change).
        // A range is [first row, first column, last row, last column], its
        // `set` what the bar above the grid chose for the cells selected:
        // one, or the rectangle from shown.anchor to shown.cell picked with
        // Shift. `workbook` is the workbook as the list leaves it (listBook()):
        // the grid and the tabs are drawn from it, so a row or a sheet put
        // in is typed into before the save.
        var steps = [];
        var workbook = listBook(office.sheets, steps);
        var looks = office.looks || {};
        var bar = editable ? el('div', 'office-format') : null;
        var controls = {};

        // Undo and Redo of the list: each change made through change()
        // below is one press (listHistory()).
        var stepHistory = listHistory(100);

        // Per sheet, by its id in the book, how its rows are shown ({sort:
        // {column, down}, filters: {column: {text: true}}}); nothing of it
        // is written to the file. A sheet sorted or filtered is only looked
        // at, so that a selection, a range and a row's place on the grid
        // stay the file's. `finder` is the search through the workbook.
        var views = {};
        var tools = el('div', 'office-tools');
        var toolParts = {};
        var finder = { query: '', list: [], at: -1 };
        var compare = plainCompare;
        var lower = function (text) { return text.toLowerCase(); };

        // Text is sorted and found as the page's language orders and cases
        // it (a dotted and a dotless i in Turkish), numbers in text by size.
        try {
            var language = document.documentElement.lang || undefined;

            compare = new Intl.Collator(language, { numeric: true, sensitivity: 'base' }).compare;
            'I'.toLocaleLowerCase(language);
            lower = function (text) { return text.toLocaleLowerCase(language); };
        } catch (error) {
            // A language tag the browser does not know: the plain order.
        }

        function viewOf(index) {
            var id = workbook.sheets[index].id;

            return views[id] || (views[id] = { sort: null, filters: {} });
        }

        // The workbook drawn again from the list.
        function rebuild() {
            workbook = listBook(office.sheets, steps);
        }

        // Simple formulas (assets/js/pg_office_formula.js): typed with the
        // English names or those of the panel's language, worked out here
        // when a change reaches them; the save works them out again.
        var formulaLanguage = String(strings.formula_language || 'en');
        var liveMemo = { book: null, index: -1, out: {} };

        // The most formulas of a sheet worked out here, as the save's
        // PG_OFFICE_EDIT_MAX_FORMULAS.
        var LIVE_FORMULAS = 5000;

        function formulaApi() {
            return window.pgOfficeFormula || null;
        }

        // A typed text that starts with "=" but is not a simple formula.
        function refusedFormula(value) {
            var api = formulaApi();

            return !!api && String(value).charAt(0) === '=' && api.parse(value, formulaLanguage) === null;
        }

        function refusedNote() {
            var api = formulaApi();
            var names = api ? api.names(formulaLanguage) : {};

            return fill(strings.formula_refused, Object.keys(names).map(function (name) { return names[name]; }).join(', '));
        }

        // The tree of a cell's formula, typed or the file's; null for none.
        function formulaTree(index, address) {
            var api = formulaApi();
            var values = workbook.values[index] || {};

            if (!api) {
                return null;
            }

            if (own(values, address)) {
                return (String(values[address]).charAt(0) === '=') ? api.parse(values[address], formulaLanguage) : null;
            }

            var formula = fileFormulaOf(workbook.sheets[index], address);

            return formula ? api.parse(formula, 'en') : null;
        }

        // Whether a cell holds a formula, typed or the file's (locked too).
        function isFormulaCell(index, address) {
            var values = workbook.values[index] || {};
            var edit = workbook.sheets[index].edit || {};

            if (own(values, address)) {
                return formulaTree(index, address) !== null;
            }

            return !!(edit.formulas && own(edit.formulas, address));
        }

        // The formulas of a sheet that a waiting change reaches, worked out
        // from the cells as they stand: address => value, null when it is
        // not known here (a cell read is a date, another formula's
        // stripped result, past the rows shown, or the sheet moved lines).
        // A formula typed is always among them; others keep the file's
        // result as drawn.
        function liveResults(index) {
            var api = formulaApi();

            if (liveMemo.book === workbook && liveMemo.index === index) {
                return liveMemo.out;
            }

            var sheet = workbook.sheets[index];
            var typed = workbook.values[index] || {};
            var edit = sheet.edit || {};
            var formulas = {};
            var values = {};
            var unknown = {};
            var seeds = [];
            var out = {};
            var last = sheet.rows.length ? sheet.rows[sheet.rows.length - 1].n : 0;
            var width = sheet.wide || 0;

            function put(map, row, column, value) {
                (map[row] = map[row] || {})[column] = value;
            }

            liveMemo = { book: workbook, index: index, out: out };

            if (!api || !Object.keys(typed).length) {
                return out;
            }

            sheet.rows.forEach(function (row) {
                width = Math.max(width, row.cells.length);
                row.cells.forEach(function (value, column) {
                    var address = columnName(column) + row.n;

                    if (own(typed, address) || (edit.formulas && own(edit.formulas, address)) || (edit.locked && edit.locked[address] === 'formula')) {
                        return;
                    }

                    var look = lookAt(index, row.n, column);
                    var found = sheetValue(value, false);

                    if (look && look.n === 'date' && typeof found !== 'number' && found !== null) {
                        put(unknown, row.n, column, true);
                    } else if (found !== null) {
                        put(values, row.n, column, found);
                    }
                });
            });

            // The file's formulas: a simple one is worked out again, any
            // other loses its result on the save.
            Object.keys(edit.formulas || {}).concat(Object.keys(edit.locked || {})).forEach(function (address) {
                var parts = /^([A-Z]+)(\d+)$/.exec(address);

                if (!parts || own(typed, address) || own(formulas, address)) {
                    return;
                }

                var row = parseInt(parts[2], 10);
                var column = columnIndex(parts[1]);
                var formula = fileFormulaOf(sheet, address);
                var tree = formula ? api.parse(formula, 'en') : null;

                if (tree) {
                    formulas[address] = [row, column, tree];
                } else if (edit.locked && edit.locked[address] === 'formula') {
                    put(unknown, row, column, true);
                } else if (edit.formulas && own(edit.formulas, address)) {
                    put(unknown, row, column, true);
                }
            });

            Object.keys(typed).forEach(function (address) {
                var parts = /^([A-Z]+)(\d+)$/.exec(address);

                if (!parts) {
                    return;
                }

                var row = parseInt(parts[2], 10);
                var column = columnIndex(parts[1]);
                var value = String(typed[address]);
                var tree = (value.charAt(0) === '=') ? api.parse(value, formulaLanguage) : null;

                seeds.push([row, column]);

                if (tree) {
                    formulas[address] = [row, column, tree];
                    out[address] = null;
                } else if (value !== '') {
                    put(values, row, column, sheetValue(value, true));
                }
            });

            if (edit.shifted || Object.keys(formulas).length > LIVE_FORMULAS) {
                return out;
            }

            // A formula reading past the rows the preview holds is not
            // known here.
            Object.keys(formulas).forEach(function (address) {
                if (sheet.truncated && api.cells(formulas[address][2]).some(function (area) { return area[2] > last || area[3] >= width; })) {
                    put(unknown, formulas[address][0], formulas[address][1], true);
                    delete formulas[address];
                }
            });

            // The formulas a change reaches, through other formulas too:
            // each cell reached is looked for once in the formulas left.
            var reached = {};
            var waiting = Object.keys(formulas).filter(function (address) { return !own(out, address); }).map(function (address) {
                return { address: address, areas: api.cells(formulas[address][2]) };
            });

            Object.keys(out).forEach(function (address) { reached[address] = true; });

            for (var next = 0; next < seeds.length && waiting.length; next++) {
                var seed = seeds[next];

                waiting = waiting.filter(function (item) {
                    var hit = item.areas.some(function (area) {
                        return seed[0] >= area[0] && seed[0] <= area[2] && seed[1] >= area[1] && seed[1] <= area[3];
                    });

                    if (hit) {
                        reached[item.address] = true;
                        seeds.push([formulas[item.address][0], formulas[item.address][1]]);
                    }

                    return !hit;
                });
            }

            var results = api.results(formulas, values, unknown);

            Object.keys(reached).forEach(function (address) {
                out[address] = (own(results, address) && results[address] !== undefined) ? results[address] : null;
            });

            return out;
        }

        // How the grid draws an open formula cell: {title, text} with
        // text null for the file's result as drawn, else the value worked
        // out here ('' while unknown, calc), num for a number. null for a
        // cell without a formula.
        function formulaDraw(index, row, column) {
            var api = formulaApi();
            var address = columnName(column) + row;
            var values = workbook.values[index] || {};
            var typed = own(values, address);
            var formula = typed ? '' : fileFormulaOf(workbook.sheets[index], address);
            var tree = formulaTree(index, address);

            if (!api || (!tree && !formula)) {
                return null;
            }

            var title = fill(strings.locked_formula_is, '=' + (tree ? api.text(tree, formulaLanguage) : formula));
            var live = liveResults(index);

            if (!own(live, address)) {
                return { title: title, text: null };
            }

            var value = live[address];

            if (value === null) {
                return { title: title, text: '', num: false, calc: true };
            }

            return { title: title, text: resultText(value, api), num: typeof value === 'number', calc: false };
        }

        // The text a cell's editor opens with: the value typed, the file's
        // formula with "=" and the language's names, or the file's value.
        function editText(td) {
            var position = cellPosition(td);
            var address = columnName(position.column) + position.row;
            var values = workbook.values[shown.index];

            if (own(values, address)) {
                return values[address];
            }

            var formula = fileFormulaOf(workbook.sheets[shown.index], address);

            if (formula) {
                var api = formulaApi();
                var tree = api ? api.parse(formula, 'en') : null;

                return '=' + (tree ? api.text(tree, formulaLanguage) : formula);
            }

            return original(workbook.sheets[shown.index], position.row, position.column);
        }

        // Draws again every formula cell of the sheet on show, after a
        // change that may reach them.
        function drawResults() {
            if (!shown.table) {
                return;
            }

            var edit = workbook.sheets[shown.index].edit || {};
            var values = workbook.values[shown.index] || {};
            var byRow = {};

            Array.prototype.forEach.call(shown.table.tBodies[0] ? shown.table.tBodies[0].rows : [], function (tr) {
                byRow[tr.getAttribute('data-row')] = tr;
            });

            Object.keys(edit.formulas || {}).concat(Object.keys(values)).forEach(function (address) {
                var parts = /^([A-Z]+)(\d+)$/.exec(address);
                var tr = parts ? byRow[parts[2]] : null;
                var td = tr ? tr.cells[columnIndex(parts[1]) + 1] : null;

                if (td && !td.classList.contains('office-locked') && (!shown.editor || shown.editor.td !== td)) {
                    drawCell(td);
                }
            });
        }

        // Puts a value typed into a cell of the open sheet into the list:
        // the change made since the last row, column or sheet change is
        // taken out, and the new one put at the end unless the cell holds
        // that value without it.
        function listValue(list, position, value) {
            var address = columnName(position.column) + position.row;

            listCell(list, shown.index, address, null);

            var under = listBook(office.sheets, list);
            var values = under.values[shown.index];
            var formula = own(values, address) ? '' : fileFormulaOf(under.sheets[shown.index], address);
            var was = own(values, address) ? values[address] : original(under.sheets[shown.index], position.row, position.column);

            // A cell with the file's formula holds that formula: the same
            // formula typed again, in any language's names, is no change.
            if (formula) {
                var api = formulaApi();
                var tree = (api && value.charAt(0) === '=') ? api.parse(value, formulaLanguage) : null;
                var stored = api ? api.parse(formula, 'en') : null;

                was = (tree && stored && api.text(tree) === api.text(stored)) ? value : '=' + formula;
            }

            if (value !== was) {
                listCell(list, shown.index, address, value);
            }

            return list;
        }

        // The list as it stands, with the value of the cell being typed in:
        // Save is offered, and sends it, before Enter.
        function listNow() {
            var editor = shown.editor;

            if (!editor) {
                return steps;
            }

            var value = String(editor.input.value).replace(/\r\n?/g, '\n');

            if (value === editText(editor.td) || refusedFormula(value)) {
                return steps;
            }

            return listValue(steps.slice(), cellPosition(editor.td), value);
        }

        // The typed values of a sheet as they stand, the cell being typed in
        // with them.
        function valuesNow(index) {
            var values = copyOf(workbook.values[index]);
            var editor = shown.editor;

            if (editor && index === shown.index) {
                var position = cellPosition(editor.td);

                values[columnName(position.column) + position.row] = String(editor.input.value).replace(/\r\n?/g, '\n');
            }

            return values;
        }

        function count() {
            return listCount(listNow());
        }

        function notify() {
            drawStatus();
            drawSteps();

            if (options && typeof options.onChange === 'function') {
                options.onChange(count());
            }
        }

        function sheetEditable(index) {
            var edit = (index >= 0) ? workbook.sheets[index].edit : null;

            return editable && !!edit && !!edit.ok && !arranged(index);
        }

        // The format bar serves a sheet whose cells may change, unless it is
        // protected (the server refuses a sheet that does not allow it).
        function sheetFormattable(index) {
            var edit = (index >= 0) ? workbook.sheets[index].edit : null;

            return editable && !!edit && !!edit.ok && !edit.protected && !arranged(index);
        }

        // Rows and columns move on a sheet whose cells may change, unless it
        // is protected (the server says why for whatever else it refuses).
        function sheetShiftable(index) {
            var edit = (index >= 0) ? workbook.sheets[index].edit : null;

            return editable && !!edit && !!edit.ok && !edit.protected && !arranged(index);
        }

        // Whether a sheet is shown sorted or filtered.
        function arranged(index) {
            var view = (index >= 0) ? viewOf(index) : null;

            return !!view && (!!view.sort || Object.keys(view.filters).length > 0);
        }

        // The places of a sheet's rows in the order they are shown.
        function orderOf(index) {
            return arrange(workbook.sheets[index].rows, viewOf(index), workbook.values[index], compare);
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
            var sheetEdit = (shown.index >= 0) ? workbook.sheets[shown.index].edit : null;
            var note = sheetNote(sheetEdit, strings);

            if (note) {
                parts.push(note);
            } else if (arranged(shown.index) && sheetEdit && sheetEdit.ok) {
                parts.push(strings.view_paused || '');
            } else if (sheetEditable(shown.index)) {
                parts.push(strings.edit_hint || '');

                if (sheetShiftable(shown.index)) {
                    parts.push(strings.shift_hint || '');
                }

                if (sheetFormattable(shown.index)) {
                    parts.push(strings.format_hint || '');
                }
            }

            var list = listNow();

            // Formulas are worked out again after a cell or a line changes.
            if (list.length > 0) {
                parts.push(fill(strings.unsaved, list.length) + ((strings.recalc && list.some(function (step) { return !step.range && !sizeOps[step.op] && !/^sheet_/.test(step.op || ''); })) ? ' ' + strings.recalc : ''));
            } else if (sheetsEditable) {
                parts.push(strings.sheet_hint || '');
            }

            if (shown.note) {
                parts.push(shown.note);
            }

            status.textContent = parts.filter(Boolean).join(' · ');
            status.classList.toggle('d-none', status.textContent === '');

            Array.prototype.forEach.call(tabs.querySelectorAll('.office-tab'), function (tab, index) {
                var edited = !!workbook.sheets[index] && (!!workbook.sheets[index].changed || (index === shown.index && list !== steps));
                var mark = tab.querySelector('.office-tab-changed');

                tab.classList.toggle('office-tab-edited', edited);

                // The dot is drawn in CSS; a screen reader hears the word.
                if (mark) {
                    mark.hidden = !edited;
                }
            });
        }

        // The column-letter row and the row-number column stick over the
        // grid; the pane's scroll padding keeps a cell scrolled into view
        // beside them, not under them. Measured again each time, since the
        // row-number column widens with the sheet and a hidden pane reads 0.
        function padHeadings() {
            var corner = shown.table ? shown.table.querySelector('.office-corner') : null;
            var pinned = 0;

            // The rows frozen on top cover the cells scrolled under them too.
            if (corner) {
                Array.prototype.forEach.call(shown.table.querySelectorAll('tbody tr[data-pinned]'), function (tr) {
                    pinned += tr.offsetHeight;
                });
            }

            pane.style.scrollPaddingTop = corner ? (corner.offsetHeight + pinned) + 'px' : '';
            pane.style.scrollPaddingLeft = corner ? corner.offsetWidth + 'px' : '';
        }

        // Selects a cell; with `extend` (Shift) the range runs from the
        // cell first selected to this one.
        function select(td, extend) {
            if (!td || td.tagName !== 'TD') {
                return;
            }

            if (!extend || !shown.anchor || !shown.anchor.isConnected) {
                shown.anchor = td;
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
            padHeadings();

            if (td.scrollIntoView) {
                td.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }

            markRange();
            drawBar();
        }

        // The cells selected, [first row, first column, last row, last
        // column], or null with none.
        function selectionRange() {
            if (!shown.cell) {
                return null;
            }

            var from = cellPosition(shown.anchor || shown.cell);
            var to = cellPosition(shown.cell);

            return [Math.min(from.row, to.row), Math.min(from.column, to.column), Math.max(from.row, to.row), Math.max(from.column, to.column)];
        }

        function rangeName(range) {
            var first = columnName(range[1]) + range[0];
            var last = columnName(range[3]) + range[2];

            return (first === last) ? first : first + ':' + last;
        }

        // The drawn cells of a range, with their row and column.
        function rangeCells(range, each) {
            if (!shown.table || !range) {
                return;
            }

            Array.prototype.forEach.call(shown.table.querySelectorAll('tbody tr'), function (tr) {
                var row = parseInt(tr.getAttribute('data-row'), 10);

                if (row < range[0] || row > range[2]) {
                    return;
                }

                for (var column = range[1]; column <= range[3] && column + 1 < tr.cells.length; column++) {
                    each(tr.cells[column + 1], row, column);
                }
            });
        }

        function markRange() {
            if (!shown.table) {
                return;
            }

            Array.prototype.forEach.call(shown.table.querySelectorAll('td.office-in-range'), function (td) {
                td.classList.remove('office-in-range');
            });

            var range = selectionRange();

            if (range && (range[0] !== range[2] || range[1] !== range[3])) {
                rangeCells(range, function (td) {
                    td.classList.add('office-in-range');
                });
            }
        }

        // The file's row entry with this number (rows come in order).
        function rowOf(sheet, number) {
            if (!sheet.byRow) {
                sheet.byRow = {};
                sheet.rows.forEach(function (row) {
                    sheet.byRow[row.n] = row;
                });
            }

            return own(sheet.byRow, number) ? sheet.byRow[number] : null;
        }

        // A cell's look as it stands: the file's, under the waiting ranges.
        function lookAt(index, row, column) {
            var entry = rowOf(workbook.sheets[index], row);

            return listLook((entry && entry.s && own(entry.s, column)) ? looks[entry.s[column]] : null, workbook.sets[index][columnName(column) + row]);
        }

        function repaint(range) {
            rangeCells(range, function (td, row, column) {
                paint(td, lookAt(shown.index, row, column));
            });
        }

        // Sets one part of the selected cells' format, as a change waiting
        // for the save beside the cells typed and the other ranges.
        function setFormat(key, value) {
            var range = selectionRange();

            if (!range || !sheetFormattable(shown.index)) {
                return;
            }

            closeEditor(true);

            if ((range[2] - range[0] + 1) * (range[3] - range[1] + 1) > FORMAT_CELLS) {
                shown.note = strings.format_many || '';
            } else {
                change(function () { listFormat(steps, shown.index, range, key, value); });
                rebuild();
                shown.note = '';
                repaint(range);
                drawBar();
                notify();

                if (shown.cell) {
                    shown.cell.focus({ preventScroll: true });
                }
                return;
            }

            drawStatus();
        }

        // A button of the bar: look A's icon button, or an item of its
        // segmented control.
        function barButton(className, icon, label, text) {
            var button = el('button', className, text);

            button.type = 'button';
            button.setAttribute('aria-pressed', 'false');

            if (icon) {
                var glyph = el('span', 'bi ' + icon);

                glyph.setAttribute('aria-hidden', 'true');
                button.appendChild(glyph);
                button.setAttribute('aria-label', label);
                button.title = label;
            }

            return button;
        }

        function buildBar() {
            bar.setAttribute('role', 'toolbar');
            bar.setAttribute('aria-label', strings.format_bar || '');

            // Undo and Redo at the bar's start, on the whole list; shown on
            // every sheet, the format controls only where cells take one.
            [['undo', 'bi-arrow-counterclockwise', strings.doc_undo], ['redo', 'bi-arrow-clockwise', strings.doc_redo]].forEach(function (item) {
                var button = barButton('pg-icon-button pg-icon-button-sm', item[1], item[2] || '');

                button.removeAttribute('aria-pressed');
                button.disabled = true;

                // A press leaves the focus where it is: in the cell being
                // typed in, whose value is kept first and undone with it.
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    undoStep(item[0] === 'undo');
                });
                controls[item[0]] = button;
                bar.appendChild(button);
            });

            controls.bold = barButton('pg-icon-button pg-icon-button-sm', 'bi-type-bold', strings.format_bold || '');
            controls.bold.addEventListener('click', function () {
                setFormat('bold', !currentLook().b);
            });

            controls.italic = barButton('pg-icon-button pg-icon-button-sm', 'bi-type-italic', strings.format_italic || '');
            controls.italic.addEventListener('click', function () {
                setFormat('italic', !currentLook().i);
            });

            // Underline and Strikethrough beside them, as toggles of the
            // first selected cell's state.
            [['underline', 'bi-type-underline', 'u'], ['strike', 'bi-type-strikethrough', 'st']].forEach(function (item) {
                controls[item[0]] = barButton('pg-icon-button pg-icon-button-sm', item[1], strings['format_' + item[0]] || '');
                controls[item[0]].addEventListener('click', function () {
                    setFormat(item[0], !currentLook()[item[2]]);
                });
            });

            bar.appendChild(controls.bold);
            bar.appendChild(controls.italic);
            bar.appendChild(controls.underline);
            bar.appendChild(controls.strike);

            // The size in points. A cell with no size of its own shows
            // "Default"; one outside the list shows its own number. Neither
            // is offered as a choice.
            var sizeField = el('span', 'pg-input pg-input-sm office-size');
            var sizeChoice = el('select');
            var sizeDefault = el('option', '', strings.format_size_default || '');
            var sizeOther = el('option', '', '');

            sizeChoice.setAttribute('aria-label', strings.format_size || '');
            sizeChoice.title = strings.format_size || '';
            sizeDefault.value = '';
            sizeDefault.disabled = true;
            sizeChoice.appendChild(sizeDefault);

            FONT_SIZES.forEach(function (size) {
                var option = el('option', '', String(size));

                option.value = String(size);
                sizeChoice.appendChild(option);
            });

            sizeOther.value = 'other';
            sizeOther.disabled = true;
            sizeOther.hidden = true;
            sizeChoice.appendChild(sizeOther);

            sizeChoice.addEventListener('change', function () {
                var size = parseInt(sizeChoice.value, 10);

                if (size) {
                    setFormat('size', size);
                }
            });

            sizeField.appendChild(sizeChoice);
            controls.size = { field: sizeField, choice: sizeChoice, other: sizeOther };
            bar.appendChild(sizeField);

            // Text colour and fill: the glyph over a bar in the cell's
            // colour, opening the colours on offer ("None" first).
            [['color', 'bi-fonts', 'c', TEXT_COLOURS], ['fill', 'bi-paint-bucket', 'bg', FILL_COLOURS]].forEach(function (item) {
                var button = barButton('pg-icon-button pg-icon-button-sm office-colour-button', item[1], strings['format_' + item[0]] || '');
                var stripe = el('span', 'office-colour-bar');

                stripe.setAttribute('aria-hidden', 'true');
                button.removeAttribute('aria-pressed');
                button.setAttribute('aria-haspopup', 'menu');
                button.appendChild(stripe);
                button.addEventListener('click', function () {
                    var chosen = currentLook()[item[2]] || '';

                    openMenu(button, [{ label: strings.format_none || '', run: function () { setFormat(item[0], ''); } }], strings['format_' + item[0]] || '', {
                        colours: item[3],
                        chosen: chosen,
                        pick: function (colour) { setFormat(item[0], colour); }
                    });
                });
                controls[item[0]] = { button: button, stripe: stripe };
                bar.appendChild(button);
            });

            // Borders: the four choices of the format entry in the editor's
            // menu; the button is pressed while the first selected cell has
            // a line on any side.
            controls.border = barButton('pg-icon-button pg-icon-button-sm', 'bi-border-all', strings.format_borders || '');
            controls.border.setAttribute('aria-haspopup', 'menu');
            controls.border.addEventListener('click', function () {
                openMenu(controls.border, BORDERS.map(function (item) {
                    return {
                        label: strings['border_' + item[0]] || item[0],
                        icon: item[1],
                        run: function () { setFormat('border', item[0]); }
                    };
                }), strings.format_borders || '');
            });
            bar.appendChild(controls.border);

            // Choosing the alignment already chosen gives the cell back its
            // general alignment.
            var align = el('span', 'pg-seg');

            align.setAttribute('role', 'group');
            align.setAttribute('aria-label', strings.format_align || '');
            controls.align = {};

            [['left', 'bi-text-left'], ['center', 'bi-text-center'], ['right', 'bi-text-right']].forEach(function (item) {
                var button = barButton('pg-seg-item', item[1], strings['format_' + item[0]] || '');

                button.addEventListener('click', function () {
                    setFormat('align', (currentLook().a === item[0]) ? '' : item[0]);
                });
                controls.align[item[0]] = button;
                align.appendChild(button);
            });

            bar.appendChild(align);

            controls.wrap = barButton('pg-icon-button pg-icon-button-sm', 'bi-text-wrap', strings.format_wrap || '');
            controls.wrap.addEventListener('click', function () {
                setFormat('wrap', !currentLook().w);
            });
            bar.appendChild(controls.wrap);

            var number = el('span', 'pg-seg');

            number.setAttribute('role', 'group');
            number.setAttribute('aria-label', strings.format_number || '');
            controls.number = {};

            ['general', 'number', 'money', 'percent', 'date'].forEach(function (kind) {
                var button = barButton('pg-seg-item', '', '', strings['format_' + kind + '_kind'] || kind);

                button.addEventListener('click', function () {
                    setFormat('number', kind);
                });
                controls.number[kind] = button;
                number.appendChild(button);
            });

            bar.appendChild(number);
        }

        // The look of the first selected cell, as the bar shows it.
        function currentLook() {
            var range = selectionRange();

            return range ? lookAt(shown.index, range[0], range[1]) : {};
        }

        function press(button, on) {
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            button.classList.toggle('is-active', on);
        }

        function drawBar() {
            if (!bar) {
                return;
            }

            var offered = !!shown.table && sheetFormattable(shown.index);

            drawSteps();

            Array.prototype.forEach.call(bar.children, function (part) {
                if (part !== controls.undo && part !== controls.redo) {
                    part.classList.toggle('d-none', !offered);
                }
            });

            if (!offered) {
                return;
            }

            var look = currentLook();

            press(controls.bold, !!look.b);
            press(controls.italic, !!look.i);
            press(controls.underline, !!look.u);
            press(controls.strike, !!look.st);
            press(controls.border, !!look.bd);
            press(controls.wrap, !!look.w);

            // The size: one of the list, "Default", or the cell's own
            // number outside the list.
            var size = look.sz ? String(look.sz) : '';

            if (size && FONT_SIZES.indexOf(Number(size)) === -1) {
                controls.size.other.textContent = size;
                size = 'other';
            }

            controls.size.choice.value = size;

            // A colour from outside the list shows on the bar as it is.
            controls.color.stripe.style.backgroundColor = look.c ? '#' + look.c : '';
            controls.fill.stripe.style.backgroundColor = look.bg ? '#' + look.bg : '';

            Object.keys(controls.align).forEach(function (key) {
                press(controls.align[key], look.a === key);
            });

            Object.keys(controls.number).forEach(function (kind) {
                press(controls.number[kind], (look.n || 'general') === kind);
            });
        }

        // The pair greyed while there is nothing to undo or redo.
        function drawSteps() {
            if (controls.undo) {
                controls.undo.disabled = !stepHistory.canUndo();
                controls.redo.disabled = !stepHistory.canRedo();
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
            var values = workbook.values[shown.index];

            return own(values, address) ? values[address] : original(workbook.sheets[shown.index], position.row, position.column);
        }

        // Puts a value in a cell, remembers it as a change unless the cell
        // holds it already, and redraws the cell.
        function keep(td, value) {
            var position = cellPosition(td);
            var address = columnName(position.column) + position.row;

            value = String(value).replace(/\r\n?/g, '\n');

            // A leading "=" that is not a simple formula is refused: the
            // cell keeps what it held, the status line says what is allowed.
            var refused = value !== editText(td) && refusedFormula(value);

            if (!refused && value !== editText(td)) {
                change(function () { listValue(steps, position, value); });
                rebuild();
            }

            drawCell(td);
            drawResults();

            shown.note = refused ? refusedNote() : '';

            notify();
        }

        // Draws a cell again as the workbook holds it: its value (the
        // file's shown text when it was not typed), its marks and its look.
        function drawCell(td) {
            var position = cellPosition(td);
            var address = columnName(position.column) + position.row;
            var value = shownValue(td);
            var typed = own(workbook.values[shown.index], address);
            var entry = rowOf(workbook.sheets[shown.index], position.row);
            var drawn = formulaDraw(shown.index, position.row, position.column);
            var live = !!drawn && drawn.text !== null;
            var calc = live ? drawn.calc : (!typed && value === '' && !!entry && !!entry.calc && entry.calc.indexOf(position.column) !== -1);

            td.textContent = live ? drawn.text : ((!typed && entry && entry.show && own(entry.show, position.column)) ? entry.show[position.column] : value);
            td.classList.toggle('office-edited', typed);
            td.classList.toggle('office-num', live ? drawn.num : isNumber(value));
            td.classList.toggle('office-calc', calc);

            if (!td.classList.contains('office-locked')) {
                td.title = drawn ? drawn.title : (calc ? (strings.calc || '') : '');
            }

            paint(td, lookAt(shown.index, position.row, position.column));
        }

        // The drawn rows of the selection, each a list of its drawn cells,
        // left to right. A row number the sheet does not draw is no row.
        function selectionRows(range) {
            var rows = [];
            var last = null;

            rangeCells(range, function (td) {
                if (td.parentNode !== last) {
                    last = td.parentNode;
                    rows.push([]);
                }

                rows[rows.length - 1].push(td);
            });

            return rows;
        }

        // Says on the status line why the cells were not changed, or how
        // many locked cells were left as they were.
        function clipNote(reason, skipped) {
            if (reason === 'room') {
                shown.note = strings.clip_room || '';
            } else if (reason === 'many') {
                shown.note = fill(strings.clip_many, FORMAT_CELLS);
            } else if (reason === 'long') {
                shown.note = fill(strings.clip_long, CELL_CHARACTERS);
            } else {
                shown.note = skipped ? fill(strings.clip_skipped, skipped) : '';
            }

            notify();
        }

        // Writes many cells of the sheet on show at once, one change of
        // Undo: `cells` is [{td, value}]; a locked cell is left as it was and
        // counted with the `skipped` cells the caller left. Refused whole,
        // with its reason, past a save's cells.
        function writeCells(cells, skipped) {
            var open = cells.filter(function (cell) { return !cell.td.classList.contains('office-locked'); });
            var reason = clipCells(open.length);

            if (!reason && open.length) {
                var index = shown.index;

                change(function () {
                    listValues(office.sheets, steps, index, open.map(function (cell) {
                        var position = cellPosition(cell.td);

                        return { row: position.row, column: position.column, value: cell.value };
                    }));
                });
                rebuild();
                open.forEach(function (cell) { drawCell(cell.td); });
                drawResults();
            }

            clipNote(reason, reason ? 0 : cells.length - open.length + (skipped || 0));
        }

        // Ctrl+C: the selected cells as tab-separated text, each as its
        // editor opens it, so a paste into this grid gives the same values.
        function copyCells(event) {
            var range = selectionRange();

            if (!range || shown.editor || !event.clipboardData) {
                return;
            }

            event.preventDefault();
            // A formula is copied as the value it shows, as its text would
            // read other cells where it is pasted.
            event.clipboardData.setData('text/plain', clipText(selectionRows(range).map(function (row) {
                return row.map(function (td) {
                    var position = cellPosition(td);

                    return isFormulaCell(shown.index, columnName(position.column) + position.row) ? td.textContent : shownValue(td);
                });
            })));
        }

        // Ctrl+V: tab-separated text written from the selection's first
        // cell on, row by drawn row, as typed cells. A block reaching past
        // the drawn grid, too many cells or too long a value is refused
        // whole; the block pasted is selected.
        function pasteCells(event) {
            var range = selectionRange();

            if (!range || shown.editor || !event.clipboardData || !sheetEditable(shown.index)) {
                return;
            }

            var block = clipRows(event.clipboardData.getData('text/plain'));

            event.preventDefault();

            if (!block.length) {
                return;
            }

            var start = shown.table.querySelector('tbody tr[data-row="' + range[0] + '"]');
            var rows = [];

            // The drawn rows from the first selected one down.
            for (var tr = start; tr; tr = tr.nextElementSibling) {
                rows.push(tr);
            }

            var reason = clipCheck(block, rows.length, start ? start.cells.length - 1 - range[1] : 0);

            if (reason) {
                clipNote(reason, 0);
                return;
            }

            var cells = [];
            var corner = null;

            block.forEach(function (values, place) {
                values.forEach(function (value, offset) {
                    var td = rows[place].cells[range[1] + offset + 1];

                    cells.push({ td: td, value: value });
                    corner = td;
                });
            });

            writeCells(cells);
            select(rows[0].cells[range[1] + 1]);

            if (corner) {
                select(corner, true);
            }
        }

        // Ctrl+D: the selection's first drawn row written into the rows under
        // it; a selection of one row takes the drawn row above it. A formula
        // is not copied as its result: its column is left as it was.
        function fillDown() {
            var range = selectionRange();

            if (!range || !sheetEditable(shown.index)) {
                return;
            }

            var rows = selectionRows(range);
            var source = rows.shift();

            if (!rows.length && source && source.length) {
                var above = source[0].parentNode.previousElementSibling;

                rows = [source];
                source = above ? Array.prototype.slice.call(above.cells, range[1] + 1, range[1] + 1 + rows[0].length) : null;
            }

            if (!source || !rows.length) {
                return;
            }

            var cells = [];

            rows.forEach(function (row) {
                row.forEach(function (td, place) {
                    var from = source[place];
                    var position = from ? cellPosition(from) : null;

                    if (from && !isFormulaCell(shown.index, columnName(position.column) + position.row)) {
                        cells.push({ td: td, value: shownValue(from) });
                    } else {
                        cells.push({ td: td, value: null });
                    }
                });
            });

            // A cell under a formula counts as left as it was.
            var open = cells.filter(function (cell) { return cell.value !== null; });

            writeCells(open, cells.length - open.length);
        }

        // Delete and Backspace: the selected cells emptied.
        function clearCells() {
            var range = selectionRange();

            if (!range || !sheetEditable(shown.index)) {
                return;
            }

            var cells = [];

            selectionRows(range).forEach(function (row) {
                row.forEach(function (td) {
                    cells.push({ td: td, value: '' });
                });
            });

            writeCells(cells);
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
                // The value before the editor opened, kept again: drawn as it
                // was, in its number format's text and its look.
                keep(editor.td, editor.before);
            }
        }

        function openEditor(td, replacement) {
            if (!td || !sheetEditable(shown.index) || td.classList.contains('office-locked')) {
                return;
            }

            closeEditor(true);
            select(td);

            var before = editText(td);
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

                // Ctrl+Z and Ctrl+Y undo and redo the typing in the cell
                // first; with the value as it was, they go to the list.
                if (historyKey(event) && input.value === before) {
                    event.preventDefault();
                    undoStep(historyKey(event) === 'undo');
                    return;
                }

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

                    // Not a simple formula: the editor stays open on it.
                    if (input.value !== before && refusedFormula(input.value)) {
                        shown.note = refusedNote();
                        notify();
                        return;
                    }

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

        function closeMenu(focus) {
            if (!menu) {
                return;
            }

            var opener = menu.opener;

            // A sheet drawn again under the menu has a new head for it.
            if (opener && !opener.isConnected && menu.reopen) {
                opener = menu.reopen();
            }

            menu.node.remove();
            document.removeEventListener('mousedown', menu.outside, true);
            menu = null;

            if (focus && opener && opener.isConnected) {
                opener.focus({ preventScroll: true });
            }
        }

        // Puts a row's or a column's insert or delete at the end of the
        // list and draws the sheet as it leaves it. A line below or right of
        // the one chosen goes before the next one: the server inserts
        // before the line it is sent.
        function choose(axis, at, place) {
            closeEditor(true);
            change(function () { steps.push({ op: (place === 'delete') ? 'delete' : 'insert', sheet: shown.index, axis: axis, at: at + ((place === 'after') ? 1 : 0) }); });
            rebuild();
            refind(false);
            show(shown.index);
            notify();
        }

        // Changes the list as apply() does, kept as one step for Undo.
        function change(apply) {
            stepHistory.change(steps, apply);
        }

        // Undo (back) or Redo: one change of the list a press. A value
        // being typed is kept first, so it is the change undone. The sheet
        // on show stays open, scrolled and selected where it was.
        function undoStep(back) {
            closeEditor(true);

            var state = back ? stepHistory.undo(steps) : stepHistory.redo(steps);

            if (!state) {
                return;
            }

            var id = (shown.index >= 0) ? workbook.sheets[shown.index].id : null;
            var at = shown.cell ? cellPosition(shown.cell) : null;
            var scroll = [pane.scrollTop, pane.scrollLeft];
            var focused = book.contains(document.activeElement);

            steps = state;
            rebuild();
            refind(false);
            drawTabs();
            openTab(sheetPlace(id, shown.index));
            pane.scrollTop = scroll[0];
            pane.scrollLeft = scroll[1];

            var row = (at && shown.table) ? shown.table.querySelector('tr[data-row="' + at.row + '"]') : null;

            if (row && row.cells[at.column + 1]) {
                select(row.cells[at.column + 1]);
            }

            // The tabs are drawn again and a greyed button lets go of the
            // focus: it stays in the workbook, so the next key reaches it.
            if (focused && !book.contains(document.activeElement)) {
                var target = shown.cell || tabs.querySelector('.office-tab.active');

                if (target) {
                    target.focus({ preventScroll: true });
                }
            }

            notify();
        }

        // Where a sheet of the book stands now, by its id; failing that
        // (a sheet deleted), the place it had, or the last.
        function sheetPlace(id, place) {
            for (var index = 0; index < workbook.sheets.length; index++) {
                if (workbook.sheets[index].id === id) {
                    return index;
                }
            }

            return Math.max(0, Math.min(place, workbook.sheets.length - 1));
        }

        // What the menu offers for a row, a column or both (from a cell).
        function menuItems(lines) {
            var items = [];

            lines.forEach(function (line) {
                var name = (line.axis === 'row') ? line.at : columnName(line.at);
                var last = (line.axis === 'row') ? (line.at >= SHEET_ROWS) : (line.at >= SHEET_COLUMNS - 1);

                items.push({ label: strings[line.axis + '_before'], icon: 'bi-plus-lg', run: function () { choose(line.axis, line.at, 'before'); } });

                // Nothing fits past the sheet's last row or column.
                if (!last) {
                    items.push({ label: strings[line.axis + '_after'], icon: 'bi-plus-lg', run: function () { choose(line.axis, line.at, 'after'); } });
                }

                items.push({ label: fill(strings[line.axis + '_delete'], name), icon: 'bi-trash', run: function () { choose(line.axis, line.at, 'delete'); } });
            });

            return items;
        }

        // `swatches` (optional): {colours: [[RRGGBB, word]], chosen, pick}
        // drawn as a grid of colour buttons after the items. An item with
        // `divider` is a line between groups; one with `checked` is a
        // checkbox that keeps the menu open.
        function openMenu(anchor, items, label, swatches) {
            closeMenu(false);

            var node = el('div', 'menu show office-head-menu');
            var buttons = [];

            node.setAttribute('role', 'menu');
            node.setAttribute('aria-label', label || '');

            items.forEach(function (item) {
                if (item.divider) {
                    var line = el('hr', 'menu-divider');

                    line.setAttribute('role', 'separator');
                    node.appendChild(line);
                    return;
                }

                var button = el('button', 'menu-item pg-link-body-emphasis');
                button.type = 'button';
                button.setAttribute('role', 'menuitem');
                button.tabIndex = -1;

                if (item.checked !== undefined) {
                    var check = el('span', 'bi bi-check-lg office-menu-check');

                    check.setAttribute('aria-hidden', 'true');
                    button.setAttribute('role', 'menuitemcheckbox');
                    button.setAttribute('aria-checked', item.checked ? 'true' : 'false');
                    button.appendChild(check);
                    button.addEventListener('click', function () {
                        item.checked = !item.checked;
                        button.setAttribute('aria-checked', item.checked ? 'true' : 'false');
                        item.run(item.checked);
                    });
                    button.appendChild(el('span', '', item.label || ''));
                    buttons.push(button);
                    node.appendChild(button);
                    return;
                }

                if (item.icon) {
                    var icon = el('span', 'bi ' + item.icon);
                    icon.setAttribute('aria-hidden', 'true');
                    button.appendChild(icon);
                }

                button.appendChild(el('span', '', item.label || ''));

                if (!item.run) {
                    button.disabled = true;
                    button.setAttribute('aria-disabled', 'true');
                } else {
                    button.addEventListener('click', function () {
                        closeMenu(false);

                        // An item that put the focus somewhere itself says so.
                        if (!item.run() && shown.cell && shown.cell.isConnected) {
                            shown.cell.focus({ preventScroll: true });
                        }
                    });
                    buttons.push(button);
                }

                node.appendChild(button);
            });

            if (swatches) {
                var grid = el('div', 'office-swatches');

                swatches.colours.forEach(function (colour) {
                    var swatch = el('button', 'office-swatch');
                    var name = strings['colour_' + colour[1]] || colour[1];

                    swatch.type = 'button';
                    swatch.setAttribute('role', 'menuitemradio');
                    swatch.setAttribute('aria-checked', (swatches.chosen === colour[0]) ? 'true' : 'false');
                    swatch.setAttribute('aria-label', name);
                    swatch.title = name;
                    swatch.tabIndex = -1;
                    swatch.style.backgroundColor = '#' + colour[0];
                    swatch.classList.toggle('is-chosen', swatches.chosen === colour[0]);
                    swatch.addEventListener('click', function () {
                        closeMenu(false);
                        swatches.pick(colour[0]);
                    });
                    buttons.push(swatch);
                    grid.appendChild(swatch);
                });

                node.appendChild(grid);
            }

            node.addEventListener('keydown', function (event) {
                event.stopPropagation();

                var at = buttons.indexOf(document.activeElement);

                if (event.key === 'Escape' || event.key === 'Tab') {
                    event.preventDefault();
                    closeMenu(true);
                } else if ((event.key === 'ArrowDown' || event.key === 'ArrowUp' || (swatches && (event.key === 'ArrowRight' || event.key === 'ArrowLeft'))) && buttons.length) {
                    event.preventDefault();
                    buttons[(at + ((event.key === 'ArrowDown' || event.key === 'ArrowRight') ? 1 : -1) + buttons.length) % buttons.length].focus();
                }
            });

            var place = anchor.getBoundingClientRect();
            var box = book.getBoundingClientRect();

            node.style.left = Math.max(0, place.left - box.left) + 'px';
            node.style.top = (place.bottom - box.top) + 'px';
            book.appendChild(node);

            // A menu opened near the right edge (a narrow screen) stays in
            // the view.
            if (node.offsetLeft + node.offsetWidth > book.clientWidth) {
                node.style.left = Math.max(0, book.clientWidth - node.offsetWidth) + 'px';
            }

            menu = {
                node: node,
                opener: anchor,
                outside: function (event) {
                    if (menu && !menu.node.contains(event.target)) {
                        closeMenu(false);
                    }
                }
            };

            document.addEventListener('mousedown', menu.outside, true);
            (buttons[0] || node).focus({ preventScroll: true });
        }

        // Puts a sheet change at the end of the list and draws the tabs
        // and the sheet on show as it leaves them; the sheet at `open` (its
        // place after the change) opens.
        function changeSheets(sheetChange, open) {
            closeEditor(true);
            change(function () { steps.push(sheetChange); });
            rebuild();
            refind(false);
            shown.note = '';
            drawTabs();
            openTab(open);
            notify();
        }

        // The tabs drawn from the book: one a sheet, in its order, with its
        // name as the list leaves it.
        function drawTabs() {
            tabs.innerHTML = '';

            workbook.sheets.forEach(function (sheet, index) {
                tabs.appendChild(makeTab(sheet, index));
            });
        }

        function focusTab(index) {
            var tab = (index >= 0) ? tabList()[index] : null;

            (tab || addButton || tabs).focus({ preventScroll: true });

            return true;
        }

        // Why a name cannot be given to a sheet, as the server checks it:
        // 1 to 31 characters, none of \ / ? * [ ] :, no apostrophe at either
        // end, not History, and not another sheet's name in any case.
        function nameError(name, index) {
            var length = Array.from(name).length;

            if (!length || length > 31 || !name.trim() || /[\\\/?*\[\]:\u0000-\u001f\u007f]/.test(name)
                || name.charAt(0) === '\'' || name.charAt(name.length - 1) === '\'' || name.toLowerCase() === 'history') {
                return strings.sheet_bad || '';
            }

            for (var at = 0; at < workbook.sheets.length; at++) {
                if (at !== index && String(workbook.sheets[at].name).toLowerCase() === name.toLowerCase()) {
                    return fill(strings.sheet_taken, workbook.sheets[at].name);
                }
            }

            return '';
        }

        function newName() {
            var number = workbook.sheets.length + 1;

            while (nameError(fill(strings.sheet_new || 'Sheet{1}', number), -1)) {
                number++;
            }

            return fill(strings.sheet_new || 'Sheet{1}', number);
        }

        // A name typed in place of a tab (index) or of the add button (-1):
        // Enter keeps it, Esc leaves the sheet as it was.
        function askName(index) {
            closeMenu(false);

            var tab = (index >= 0) ? tabList()[index] : null;
            var anchor = tab || addButton;
            var input = el('input', 'form-control form-control-sm office-tab-input');
            var done = false;

            input.type = 'text';
            input.value = (index >= 0) ? workbook.sheets[index].name : newName();
            input.setAttribute('aria-label', strings.sheet_name || '');
            anchor.parentNode.insertBefore(input, anchor);

            if (tab) {
                tab.hidden = true;
            }

            function finish(keep, leaving) {
                if (done) {
                    return;
                }

                var name = input.value;
                var error = keep ? nameError(name, index) : '';

                if (error && !leaving) {
                    shown.note = error;
                    drawStatus();
                    input.focus();
                    return;
                }

                done = true;
                input.remove();

                if (tab) {
                    tab.hidden = false;
                }

                shown.note = '';

                var place = (index < 0) ? workbook.sheets.length : index;

                if (keep && !error && (index < 0 || name !== workbook.sheets[index].name)) {
                    changeSheets((index < 0) ? { op: 'sheet_add', to: name } : { op: 'sheet_rename', sheet: index, to: name }, place);
                } else {
                    drawStatus();
                    place = index;
                }

                if (!leaving) {
                    focusTab(place);
                }
            }

            input.addEventListener('keydown', function (event) {
                event.stopPropagation();

                if (event.key === 'Enter') {
                    event.preventDefault();
                    finish(true, false);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    finish(false, false);
                }
            });

            input.addEventListener('blur', function () {
                finish(true, true);
            });

            input.focus();
            input.select();
        }

        // What a sheet tab's menu offers; each change goes into the list
        // and the tabs are drawn as it leaves them.
        function sheetMenuItems(index) {
            var items = [{ label: strings.sheet_rename, icon: 'bi-pencil', run: function () { askName(index); return true; } }];

            if (index > 0) {
                items.push({ label: strings.sheet_left, icon: 'bi-arrow-left', run: function () { changeSheets({ op: 'sheet_move', sheet: index, to: index - 1 }, index - 1); return focusTab(index - 1); } });
            }

            if (index < workbook.sheets.length - 1) {
                items.push({ label: strings.sheet_right, icon: 'bi-arrow-right', run: function () { changeSheets({ op: 'sheet_move', sheet: index, to: index + 1 }, index + 1); return focusTab(index + 1); } });
            }

            // The last sheet shown stays (the server refuses to delete it).
            var others = workbook.sheets.some(function (sheet, at) { return at !== index && !sheet.hidden; });

            if (others) {
                items.push({ label: fill(strings.sheet_delete, workbook.sheets[index].name), icon: 'bi-trash', run: function () {
                    var open = (index < workbook.sheets.length - 1) ? index : index - 1;

                    changeSheets({ op: 'sheet_delete', sheet: index }, open);
                    return focusTab(open);
                } });
            }

            return items;
        }

        // The open sheet's head button of a column.
        function headButton(column) {
            return shown.table ? shown.table.querySelector('thead .office-head[data-at="' + column + '"]') : null;
        }

        // Draws the open sheet again in its new order, the find marks and
        // the tools' line with it; `keepMenu` leaves an open filter menu.
        function rearrange(keepMenu) {
            refind(false);
            show(shown.index, keepMenu);
        }

        function setSort(column, down) {
            viewOf(shown.index).sort = (column === null) ? null : { column: column, down: down };
            rearrange(false);
        }

        function clearView() {
            views[workbook.sheets[shown.index].id] = { sort: null, filters: {} };
            rearrange(false);
        }

        // What a column's menu offers for the view: sort either way, the
        // filter by its values, and the removal of either when set.
        function viewItems(column) {
            var view = viewOf(shown.index);
            var name = columnName(column);
            var items = [
                { label: strings.sort_up, icon: 'bi-sort-alpha-down', run: function () { setSort(column, false); } },
                { label: strings.sort_down, icon: 'bi-sort-alpha-up-alt', run: function () { setSort(column, true); } },
                { label: strings.filter_values, icon: 'bi-funnel', run: function () { return openFilter(column); } }
            ];

            if (view.sort && view.sort.column === column) {
                items.push({ label: strings.sort_off, icon: 'bi-x-lg', run: function () { setSort(null); } });
            }

            if (own(view.filters, column)) {
                items.push({ label: fill(strings.filter_off, name), icon: 'bi-x-lg', run: function () {
                    delete viewOf(shown.index).filters[column];
                    rearrange(false);
                } });
            }

            return items;
        }

        // A column's values as checkboxes: one cleared hides the rows
        // showing it, at once; the menu stays open.
        function openFilter(column) {
            var anchor = headButton(column);
            var values = columnValues(workbook.sheets[shown.index].rows, column, workbook.values[shown.index], compare);
            var hidden = viewOf(shown.index).filters[column] || {};
            var items = [{ label: strings.filter_all, icon: 'bi-eye', run: function () {
                delete viewOf(shown.index).filters[column];
                rearrange(false);
            } }, { divider: true }];

            values.texts.forEach(function (text) {
                items.push({ label: (text === '') ? strings.filter_empty : text, checked: !own(hidden, text), run: function (on) {
                    var filters = viewOf(shown.index).filters;

                    filters[column] = filters[column] || {};

                    if (on) {
                        delete filters[column][text];
                    } else {
                        filters[column][text] = true;
                    }

                    if (!Object.keys(filters[column]).length) {
                        delete filters[column];
                    }

                    rearrange(true);
                } });
            });

            if (values.more) {
                items.push({ label: fill(strings.filter_more, FILTER_VALUES) });
            }

            if (!anchor) {
                return false;
            }

            openMenu(anchor, items, fill(strings.filter_menu, columnName(column)));
            menu.reopen = function () { return headButton(column); };

            return true;
        }

        // Opens a column head's menu: the view's items, and inserting and
        // deleting where the sheet allows it.
        function headMenu(event) {
            var head = event.target.closest('.office-head');

            if (!head) {
                return false;
            }

            var axis = head.getAttribute('data-axis');
            var at = parseInt(head.getAttribute('data-at'), 10);
            var items = (axis === 'column') ? viewItems(at) : [];

            if (sheetShiftable(shown.index)) {
                items = items.concat(items.length ? [{ divider: true }] : [], menuItems([{ axis: axis, at: at }]), [{ divider: true }], sizeItems([{ axis: axis, at: at }], head));
            }

            if (!items.length) {
                return false;
            }

            event.preventDefault();
            openMenu(head, items, (axis === 'column') ? fill(strings.column_menu, columnName(at)) : strings.shift_menu);

            return true;
        }

        // The menu of a cell's row and column: the view's items for its
        // column, and inserting and deleting where the sheet allows it.
        function cellMenu(td) {
            var position = cellPosition(td);
            var items = viewItems(position.column);

            if (!sheetShiftable(shown.index)) {
                openMenu(td, items, fill(strings.column_menu, columnName(position.column)));
                return;
            }

            items = items.concat([{ divider: true }], menuItems([{ axis: 'row', at: position.row }, { axis: 'column', at: position.column }]),
                [{ divider: true }], sizeItems([{ axis: 'column', at: position.column }, { axis: 'row', at: position.row }], td));
            openMenu(td, items, strings.shift_menu);
        }

        // Places the rows frozen on top once the table is laid out: a book
        // drawn before it is on the page is placed at the next frame.
        function placePins(table) {
            if (!table.querySelector('tbody tr[data-pinned]') || pinRows(table)) {
                return;
            }

            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(function () {
                    if (table.isConnected) {
                        pinRows(table);
                        padHeadings();
                    }
                });
            }
        }

        // The open sheet drawn again as the list leaves it, scrolled and
        // selected where it was.
        function showAgain() {
            var at = shown.cell ? cellPosition(shown.cell) : null;
            var scroll = [pane.scrollTop, pane.scrollLeft];

            show(shown.index);
            pane.scrollTop = scroll[0];
            pane.scrollLeft = scroll[1];

            var row = (at && shown.table) ? shown.table.querySelector('tr[data-row="' + at.row + '"]') : null;

            if (row && row.cells[at.column + 1]) {
                select(row.cells[at.column + 1]);
            }
        }

        // Puts a column's width, a row's height or the frozen top row into
        // the list (listSize()) and draws the sheet as it leaves it.
        function setSize(op, at, value) {
            closeEditor(true);
            change(function () { listSize(steps, shown.index, op, at, value); });
            rebuild();
            showAgain();
            notify();
        }

        // The size a column or a row is drawn at, in the file's units: its
        // own, else as wide or high as the grid draws it.
        function sizeNow(axis, at) {
            var sheet = workbook.sheets[shown.index];

            if (axis === 'column') {
                var widths = sheetSizes(sheet).widths;
                var head = shown.table ? shown.table.tHead.rows[0].cells[at + 1] : null;

                return own(widths, at) ? widths[at] : (head ? widthOf(head.getBoundingClientRect().width) : null);
            }

            var row = rowOf(sheet, at);
            var tr = shown.table ? shown.table.querySelector('tr[data-row="' + at + '"]') : null;

            return (row && row.h !== undefined && row.h !== null) ? row.h : (tr ? heightOf(tr.getBoundingClientRect().height) : null);
        }

        // What a menu offers for sizes: each line's width or height, asked
        // as a number, and the frozen top row on or off.
        function sizeItems(lines, anchor) {
            var frozen = sheetSizes(workbook.sheets[shown.index]).frozen > 0;
            var items = lines.map(function (line) {
                return { label: strings[(line.axis === 'column') ? 'size_column' : 'size_row'], icon: (line.axis === 'column') ? 'bi-arrows' : 'bi-arrows-vertical', run: function () {
                    return askSize(line.axis, line.at, anchor);
                } };
            });

            items.push({ label: frozen ? strings.freeze_off : strings.freeze_on, icon: frozen ? 'bi-pin-angle-fill' : 'bi-pin-angle', run: function () {
                setSize('freeze', 0, !frozen);
            } });

            return items;
        }

        // Asks a column's width (characters) or a row's height (points) in
        // a field under the head or cell the menu opened from: Enter or
        // Apply keeps it, Escape leaves the sheet as it was.
        function askSize(axis, at, anchor) {
            closeMenu(false);

            if (!anchor || !anchor.isConnected) {
                return false;
            }

            var op = (axis === 'column') ? 'width' : 'height';
            var high = (axis === 'column') ? MAX_WIDTH : MAX_HEIGHT;
            var label = fill(strings[(axis === 'column') ? 'size_column_label' : 'size_row_label'], (axis === 'column') ? columnName(at) : at);
            var node = el('div', 'menu show office-head-menu');
            var title = el('div', 'menu-header', label);
            var line = el('div', 'menu-text d-flex align-items-center gap-3');
            var field = el('span', 'pg-input pg-input-sm');
            var input = el('input');
            var apply = el('button', 'pg-button pg-button-sm', strings.size_apply || '');
            var problem = el('div', 'menu-text small fg-danger');
            var now = sizeNow(axis, at);

            title.id = paneId + '_size';
            node.setAttribute('role', 'dialog');
            node.setAttribute('aria-labelledby', title.id);
            input.type = 'number';
            input.min = '0';
            input.max = String(high);
            input.step = 'any';
            input.inputMode = 'decimal';
            input.value = (now === null) ? '' : String(now);
            input.setAttribute('aria-labelledby', title.id);
            apply.type = 'button';
            problem.setAttribute('role', 'alert');
            problem.hidden = true;
            field.appendChild(input);
            line.appendChild(field);
            line.appendChild(apply);
            node.appendChild(title);
            node.appendChild(line);
            node.appendChild(problem);

            function keep() {
                var value = Number(String(input.value).replace(',', '.'));

                if (String(input.value).trim() === '' || !isFinite(value) || value <= 0 || value > high) {
                    problem.textContent = fill(strings.size_bad, high);
                    problem.hidden = false;
                    field.classList.add('is-invalid');
                    input.setAttribute('aria-invalid', 'true');
                    input.focus();
                    return;
                }

                closeMenu(false);
                setSize(op, at, Math.round(value * 100) / 100);

                if (!shown.cell && axis === 'column' && headButton(at)) {
                    headButton(at).focus({ preventScroll: true });
                }
            }

            apply.addEventListener('click', keep);
            node.addEventListener('keydown', function (event) {
                event.stopPropagation();

                if (event.key === 'Enter') {
                    event.preventDefault();
                    keep();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeMenu(true);
                }
            });

            var place = anchor.getBoundingClientRect();
            var box = book.getBoundingClientRect();

            node.style.left = Math.max(0, place.left - box.left) + 'px';
            node.style.top = (place.bottom - box.top) + 'px';
            book.appendChild(node);

            if (node.offsetLeft + node.offsetWidth > book.clientWidth) {
                node.style.left = Math.max(0, book.clientWidth - node.offsetWidth) + 'px';
            }

            menu = {
                node: node,
                opener: (shown.cell && shown.cell.isConnected) ? shown.cell : anchor,
                outside: function (event) {
                    if (menu && !menu.node.contains(event.target)) {
                        closeMenu(false);
                    }
                }
            };

            document.addEventListener('mousedown', menu.outside, true);
            input.focus({ preventScroll: true });
            input.select();

            return true;
        }

        // A drag on a column head's end edge or a row head's bottom edge
        // sets its width or height; the cursor shows where one starts. A
        // click that ends a drag opens no menu.
        function wireSizes(table) {
            var drag = null;
            var hovered = null;
            var swallow = false;

            function edgeOf(event) {
                var cell = (event.target && event.target.closest) ? event.target.closest('th') : null;

                if (!cell || !table.contains(cell) || cell.classList.contains('office-corner')) {
                    return null;
                }

                var box = cell.getBoundingClientRect();
                var reach = (event.pointerType === 'mouse') ? 4 : 8;

                if (cell.parentNode.parentNode === table.tHead) {
                    var rtl = window.getComputedStyle(cell).direction === 'rtl';

                    if (Math.abs(event.clientX - (rtl ? box.left : box.right)) > reach) {
                        return null;
                    }

                    return { axis: 'column', at: cell.cellIndex - 1, cell: cell, size: box.width, rtl: rtl };
                }

                if (Math.abs(event.clientY - box.bottom) > reach) {
                    return null;
                }

                return { axis: 'row', at: parseInt(cell.parentNode.getAttribute('data-row'), 10), cell: cell, size: box.height };
            }

            function hover(edge) {
                var cell = edge ? edge.cell : null;
                var cursor = edge ? ((edge.axis === 'column') ? 'col-resize' : 'row-resize') : '';

                [hovered, cell].forEach(function (th) {
                    if (th) {
                        var button = th.querySelector('.office-head');

                        th.style.cursor = (th === cell) ? cursor : '';

                        if (button) {
                            button.style.cursor = (th === cell) ? cursor : '';
                        }
                    }
                });

                hovered = cell;
            }

            // The line drawn at the size the pointer gives, before it is kept.
            function draw(px) {
                if (drag.axis === 'column') {
                    Array.prototype.forEach.call(table.rows, function (tr) {
                        if (tr.cells[drag.at + 1]) {
                            sizeCell(tr.cells[drag.at + 1], px);
                        }
                    });
                } else {
                    sizeRow(drag.cell.parentNode, px);
                }
            }

            table.addEventListener('pointermove', function (event) {
                if (!drag) {
                    hover(edgeOf(event));
                    return;
                }

                var moved = (drag.axis === 'column') ? (event.clientX - drag.x) * (drag.rtl ? -1 : 1) : event.clientY - drag.y;

                if (Math.abs(moved) > 2) {
                    drag.moved = true;
                }

                if (drag.moved) {
                    drag.px = Math.max(8, Math.round(drag.size + moved));
                    draw(drag.px);
                }
            });

            table.addEventListener('pointerleave', function () {
                if (!drag) {
                    hover(null);
                }
            });

            table.addEventListener('pointerdown', function (event) {
                swallow = false;

                var edge = (event.button === 0) ? edgeOf(event) : null;

                if (!edge) {
                    return;
                }

                event.preventDefault();
                closeEditor(true);
                closeMenu(false);
                drag = edge;
                drag.x = event.clientX;
                drag.y = event.clientY;
                drag.moved = false;

                if (table.setPointerCapture) {
                    table.setPointerCapture(event.pointerId);
                }
            });

            table.addEventListener('pointerup', function () {
                var done = drag;

                drag = null;

                if (!done || !done.moved) {
                    return;
                }

                swallow = true;
                hover(null);

                // Kept after the click this pointer makes, so the click
                // still lands on the table it began on.
                window.setTimeout(function () {
                    setSize((done.axis === 'column') ? 'width' : 'height', done.at, (done.axis === 'column') ? widthOf(done.px) : heightOf(done.px));
                }, 0);
            });

            // A drag the browser took over (a scroll on a touch screen)
            // leaves the sheet as the list has it.
            table.addEventListener('pointercancel', function () {
                var done = drag;

                drag = null;

                if (done && done.moved) {
                    showAgain();
                }
            });

            table.addEventListener('click', function (event) {
                if (swallow) {
                    swallow = false;
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            }, true);
        }

        // The heads' clicks; a sheet only looked at reaches its column heads
        // from the keyboard too: the first is a Tab stop, the arrows move.
        function wireHeads(table, roving) {
            table.addEventListener('click', headMenu);

            if (!roving) {
                return;
            }

            var heads = Array.prototype.slice.call(table.querySelectorAll('thead .office-head'));

            if (heads.length) {
                heads[0].tabIndex = 0;
            }

            table.querySelector('thead').addEventListener('keydown', function (event) {
                var at = heads.indexOf(event.target);
                var step = { ArrowLeft: -1, ArrowRight: 1 }[event.key];

                if (at < 0 || !step || !heads[at + step]) {
                    return;
                }

                event.preventDefault();
                heads[at].tabIndex = -1;
                heads[at + step].tabIndex = 0;
                heads[at + step].focus();
            });
        }

        // Searches the workbook again; `move` goes to the first match from
        // the open sheet on, else the one at the same place is kept.
        function refind(move) {
            var orders = workbook.sheets.map(function (sheet, index) { return orderOf(index); });

            finder.list = findCells(workbook.sheets, finder.query, orders, workbook.values, lower);

            if (!finder.list.length) {
                finder.at = -1;
            } else if (move || finder.at < 0) {
                finder.at = 0;

                for (var at = 0; at < finder.list.length; at++) {
                    if (finder.list[at].sheet >= shown.index) {
                        finder.at = at;
                        break;
                    }
                }
            } else {
                finder.at = Math.min(finder.at, finder.list.length - 1);
            }
        }

        function foundCell(match) {
            var tr = shown.table ? shown.table.querySelector('tbody tr[data-row="' + match.row + '"]') : null;

            return (tr && match.column + 1 < tr.cells.length) ? tr.cells[match.column + 1] : null;
        }

        // Marks the open sheet's matches, the current one apart.
        function markFound() {
            if (!shown.table) {
                return;
            }

            Array.prototype.forEach.call(shown.table.querySelectorAll('td.office-found'), function (td) {
                td.classList.remove('office-found', 'office-found-current');
            });

            finder.list.forEach(function (match, at) {
                var td = (match.sheet === shown.index) ? foundCell(match) : null;

                if (td) {
                    td.classList.add('office-found');
                    td.classList.toggle('office-found-current', at === finder.at);
                }
            });
        }

        // Shows the current match: its sheet opened, the cell scrolled into
        // view. The focus stays in the search field.
        function showFound() {
            var match = finder.list[finder.at];

            if (match && match.sheet !== shown.index) {
                openTab(match.sheet);
            } else {
                markFound();
                drawTools();
            }

            var td = match ? foundCell(match) : null;

            if (td && td.scrollIntoView) {
                padHeadings();
                td.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }
        }

        function stepFound(step) {
            if (!finder.list.length) {
                return;
            }

            finder.at = (finder.at + step + finder.list.length) % finder.list.length;
            showFound();
        }

        // The open sheet as a CSV file, as the grid shows it.
        function downloadCsv() {
            var index = shown.index;

            if (index < 0 || !shown.table) {
                return;
            }

            var sheet = workbook.sheets[index];
            var text = csvText(sheet.rows, orderOf(index), valuesNow(index));
            var link = el('a');

            link.href = URL.createObjectURL(new Blob([text], { type: 'text/csv;charset=utf-8' }));
            link.download = csvName(sheet.name);
            link.hidden = true;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(function () { URL.revokeObjectURL(link.href); }, 60000);

            toolParts.said = sheet.truncated ? (strings.csv_truncated || '') : '';
            drawTools();
        }

        // The line over the grid: find in the workbook, the open sheet's
        // sort and filter with a way to clear them, Download as CSV.
        function buildTools() {
            var search = el('div', 'office-find');
            var field = el('span', 'pg-input pg-input-sm office-find-field');
            var glass = el('span', 'bi bi-search');
            var input = el('input');
            var count = el('span', 'pg-input-end');

            search.setAttribute('role', 'search');
            glass.setAttribute('aria-hidden', 'true');
            input.type = 'search';
            input.placeholder = strings.find_label || '';
            input.setAttribute('aria-label', strings.find_label || '');
            input.spellcheck = false;
            count.setAttribute('aria-live', 'polite');
            field.appendChild(glass);
            field.appendChild(input);
            field.appendChild(count);
            search.appendChild(field);

            input.addEventListener('input', function () {
                finder.query = input.value;
                refind(true);
                showFound();
                drawTools();
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    stepFound(event.shiftKey ? -1 : 1);
                } else if (event.key === 'Escape' && input.value !== '') {
                    // The window's own Escape waits until the search is empty.
                    event.preventDefault();
                    event.stopPropagation();
                    input.value = '';
                    finder.query = '';
                    refind(false);
                    markFound();
                    drawTools();
                }
            });

            [['previous', 'bi-chevron-up', -1], ['next', 'bi-chevron-down', 1]].forEach(function (item) {
                var button = el('button', 'pg-icon-button pg-icon-button-sm');
                var icon = el('span', 'bi ' + item[1]);

                button.type = 'button';
                icon.setAttribute('aria-hidden', 'true');
                button.appendChild(icon);
                button.setAttribute('aria-label', strings['find_' + item[0]] || '');
                button.title = strings['find_' + item[0]] || '';
                button.addEventListener('click', function () {
                    stepFound(item[2]);
                });
                toolParts[item[0]] = button;
                search.appendChild(button);
            });

            var state = el('span', 'pg-text-meta office-view-state');
            var clear = el('button', 'pg-button pg-button-quiet pg-button-sm', strings.view_clear || '');
            var download = el('button', 'pg-button pg-button-quiet pg-button-sm office-csv');
            var csvIcon = el('span', 'bi bi-filetype-csv');

            state.setAttribute('aria-live', 'polite');
            clear.type = 'button';
            clear.addEventListener('click', clearView);
            download.type = 'button';
            download.title = strings.csv_help || '';
            csvIcon.setAttribute('aria-hidden', 'true');
            download.appendChild(csvIcon);
            download.appendChild(el('span', 'pg-btn-label', strings.csv_download || ''));
            download.addEventListener('click', downloadCsv);

            toolParts.count = count;
            toolParts.state = state;
            toolParts.clear = clear;
            toolParts.download = download;
            toolParts.said = '';

            tools.appendChild(search);
            tools.appendChild(state);
            tools.appendChild(clear);
            tools.appendChild(download);
        }

        // The tools' line as the open sheet and the search stand.
        function drawTools() {
            if (!toolParts.state) {
                return;
            }

            var count = '';

            if (finder.query !== '') {
                count = finder.list.length ? fill(strings.find_count, finder.at + 1).replace('{2}', finder.list.length) : (strings.find_none || '');
            }

            toolParts.count.textContent = count;
            toolParts.previous.disabled = toolParts.next.disabled = finder.list.length < 2;

            var parts = [];
            var index = shown.index;

            if (arranged(index)) {
                var view = viewOf(index);
                var rows = workbook.sheets[index].rows;
                var header = headerRow(rows, workbook.values[index]) ? 1 : 0;

                if (view.sort) {
                    parts.push(fill(strings[view.sort.down ? 'view_sorted_down' : 'view_sorted_up'], columnName(view.sort.column)));
                }

                if (Object.keys(view.filters).length) {
                    parts.push(fill(strings.view_filtered, orderOf(index).length - header).replace('{2}', rows.length - header));
                }

                if (header) {
                    parts.push(fill(strings.view_header, rows[0].n));
                }
            }

            if (toolParts.said) {
                parts.push(toolParts.said);
            }

            toolParts.state.textContent = parts.join(' ');
            toolParts.clear.classList.toggle('d-none', !arranged(index));
            toolParts.download.disabled = !shown.table;
        }

        function wireGrid(table) {
            table.addEventListener('copy', copyCells);
            table.addEventListener('paste', pasteCells);

            // A cell reached with Tab is selected as a click would select it,
            // so the arrows, Enter and typing work on it at once.
            table.addEventListener('focusin', function (event) {
                var td = event.target;

                if (td.tagName === 'TD' && td !== shown.cell && !shown.editor && !td.classList.contains('office-editing')) {
                    select(td);
                }
            });

            table.addEventListener('contextmenu', function (event) {
                if (headMenu(event)) {
                    return;
                }

                var td = event.target.closest('td');

                if (td && !td.classList.contains('office-editing')) {
                    event.preventDefault();
                    select(td);
                    cellMenu(td);
                }
            });

            table.addEventListener('mousedown', function (event) {
                var td = event.target.closest('td');

                if (td && !td.classList.contains('office-editing')) {
                    // Shift+click widens the selection, not the page's text
                    // selection.
                    if (event.shiftKey) {
                        event.preventDefault();
                    }

                    select(td, event.shiftKey);
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
                    select(neighbour(td, moves[key][0], moves[key][1]) || td, event.shiftKey);
                    return;
                }

                // Ctrl+B, Ctrl+I and Ctrl+U, as a spreadsheet takes them.
                var shortcuts = { b: ['bold', 'b'], i: ['italic', 'i'], u: ['underline', 'u'] };
                var shortcut = own(shortcuts, String(key).toLowerCase()) ? shortcuts[String(key).toLowerCase()] : null;

                if ((event.ctrlKey || event.metaKey) && !event.altKey && shortcut && sheetFormattable(shown.index)) {
                    event.preventDefault();
                    event.stopPropagation();
                    setFormat(shortcut[0], !currentLook()[shortcut[1]]);
                    return;
                }

                // The menu key or Shift+F10 offers the cell's column's sort
                // and filter, and its row and column.
                if (key === 'ContextMenu' || (key === 'F10' && event.shiftKey)) {
                    event.preventDefault();
                    cellMenu(td);
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

                // Ctrl+C and Ctrl+V are the browser's: its copy and paste
                // events below. The page's own (the File Manager's copy of
                // files) must not take them.
                var letter = String(key).toLowerCase();

                if ((event.ctrlKey || event.metaKey) && !event.altKey && (letter === 'c' || letter === 'v')) {
                    event.stopPropagation();
                    return;
                }

                // Ctrl+D fills the selection's first row down.
                if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && letter === 'd') {
                    event.preventDefault();
                    event.stopPropagation();
                    fillDown();
                    return;
                }

                if ((key === 'Delete' || key === 'Backspace') && sheetEditable(shown.index)) {
                    event.preventDefault();
                    clearCells();
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

        function show(index, keepMenu) {
            closeEditor(true);

            if (!keepMenu) {
                closeMenu(false);
            }

            var sheet = workbook.sheets[index];
            var offered = editable && !!sheet.edit;

            shown.index = index;
            shown.cell = null;
            shown.anchor = null;
            shown.note = '';
            toolParts.said = '';
            pane.innerHTML = '';

            if (!sheet.rows.length) {
                pane.appendChild(el('div', 'office-note', strings.empty || ''));
                shown.table = null;
            } else {
                shown.table = sheetTable(sheet, strings, offered ? {
                    changes: workbook.values[index],
                    heads: sheetShiftable(index),
                    added: sheet.added,
                    look: function (row, column, look) { return listLook(look, workbook.sets[index][columnName(column) + row]); },
                    formula: function (row, column) { return formulaDraw(index, row, column); }
                } : null, looks, { order: orderOf(index), sort: viewOf(index).sort, filters: viewOf(index).filters });
                pane.appendChild(shown.table);
                wireHeads(shown.table, !offered);

                if (offered && sheetShiftable(index)) {
                    wireSizes(shown.table);
                }

                placePins(shown.table);

                if (offered) {
                    wireGrid(shown.table);

                    // The grid's one Tab stop until a cell is selected.
                    var first = shown.table.querySelector('tbody td');

                    if (first) {
                        first.tabIndex = 0;
                    }
                }
            }

            // A sheet only read scrolls from the keyboard through its pane;
            // an editable grid is entered through its cells.
            if (offered && shown.table) {
                pane.removeAttribute('tabindex');
            } else {
                pane.tabIndex = 0;
            }

            if (sheet.truncated) {
                pane.appendChild(el('div', 'office-note', strings.truncated || ''));
            }

            padHeadings();
            markFound();
            drawBar();
            drawStatus();
            drawTools();
        }

        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', strings.sheets || '');
        pane.id = paneId;
        pane.setAttribute('role', 'tabpanel');

        function tabList() {
            return Array.prototype.slice.call(tabs.querySelectorAll('.office-tab'));
        }

        function openTab(index) {
            tabList().forEach(function (tab, at) {
                tab.classList.remove('active');
                tab.setAttribute('aria-selected', 'false');
                tab.tabIndex = -1;

                if (at === index) {
                    tab.classList.add('active');
                    tab.setAttribute('aria-selected', 'true');
                    tab.tabIndex = 0;
                    pane.setAttribute('aria-labelledby', tab.id);
                }
            });

            show(index);
        }

        // A sheet's tab, as the book has it at `index`.
        function makeTab(sheet, index) {
            var tab = el('button', 'office-tab' + (sheet.hidden ? ' office-tab-hidden' : ''));
            tab.type = 'button';
            tab.id = paneId + '_tab_' + index;
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-selected', 'false');
            tab.setAttribute('aria-controls', paneId);
            tab.tabIndex = -1;
            tab.appendChild(el('span', 'office-tab-name', sheet.name || ('#' + (index + 1))));

            // A hidden sheet shows only in italics and a changed one only by
            // a dot, so both are said in words too.
            if (sheet.hidden) {
                tab.title = strings.hidden || '';
                tab.appendChild(el('span', 'visually-hidden', ', ' + (strings.hidden || '')));
            }

            var changedMark = el('span', 'visually-hidden office-tab-changed', ', ' + (strings.changed_mark || ''));
            changedMark.hidden = true;
            tab.appendChild(changedMark);

            // The open sheet's tab, clicked again, offers the sheet's menu.
            tab.addEventListener('click', function () {
                if (sheetsEditable && tab.classList.contains('active')) {
                    openMenu(tab, sheetMenuItems(index), strings.sheet_menu);
                } else {
                    openTab(index);
                }
            });

            tab.addEventListener('contextmenu', function (event) {
                if (!sheetsEditable) {
                    return;
                }

                event.preventDefault();

                if (!tab.classList.contains('active')) {
                    openTab(index);
                }

                openMenu(tab, sheetMenuItems(index), strings.sheet_menu);
            });

            return tab;
        }

        drawTabs();

        // The first sheet that is not hidden opens; failing that, the first.
        workbook.sheets.forEach(function (sheet, index) {
            if (current === null && !sheet.hidden) {
                current = index;
            }
        });

        // Arrow keys, Home and End move between the sheet tabs and open the
        // one they land on; only the open tab sits in the Tab order. The menu
        // key or Shift+F10 offers the sheet's menu, F2 its new name.
        tabs.addEventListener('keydown', function (event) {
            var list = tabList();
            var at = list.indexOf(event.target);
            var next = -1;

            if (at < 0 || event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }

            if (sheetsEditable && (event.key === 'ContextMenu' || (event.key === 'F10' && event.shiftKey))) {
                event.preventDefault();
                openMenu(list[at], sheetMenuItems(at), strings.sheet_menu);
                return;
            }

            if (sheetsEditable && event.key === 'F2') {
                event.preventDefault();
                askName(at);
                return;
            }

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                next = (at + 1) % list.length;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                next = (at - 1 + list.length) % list.length;
            } else if (event.key === 'Home') {
                next = 0;
            } else if (event.key === 'End') {
                next = list.length - 1;
            }

            if (next >= 0) {
                event.preventDefault();
                list[next].focus();
                openTab(next);
            }
        });

        // A workbook whose sheets may change has the tabs, the sheet being
        // added and the add button in one strip; the tab list is laid out as
        // part of it.
        if (strip) {
            tabs.className = 'office-tablist';
            strip.appendChild(tabs);

            addButton = el('button', 'office-tab-add');
            addButton.type = 'button';
            addButton.title = strings.sheet_add || '';
            addButton.setAttribute('aria-label', strings.sheet_add || '');

            var plus = el('span', 'bi bi-plus-lg');
            plus.setAttribute('aria-hidden', 'true');
            addButton.appendChild(plus);

            addButton.addEventListener('click', function () {
                askName(-1);
            });

            strip.appendChild(addButton);
            book.appendChild(strip);
        } else {
            book.appendChild(tabs);
        }

        buildTools();
        book.appendChild(tools);

        if (bar) {
            buildBar();
            book.appendChild(bar);

            // Ctrl+Z, Ctrl+Y and Ctrl+Shift+Z on the grid, the tabs and the
            // bar; a field keeps its own (the cell's editor decides above).
            book.addEventListener('keydown', function (event) {
                var asked = historyKey(event);

                if (!asked || event.isComposing || /^(INPUT|TEXTAREA|SELECT)$/.test(event.target.tagName) || event.target.isContentEditable) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                undoStep(asked === 'undo');
            });
        }

        book.appendChild(pane);

        if (editable) {
            book.appendChild(status);
        }

        container.appendChild(book);

        if (current === null && workbook.sheets.length) {
            current = 0;
        }

        if (current !== null) {
            openTab(current);
        }

        // The formula twin, fetched on first use: the sheet is drawn again
        // with its formulas' titles once it is there, unless a cell was
        // chosen or changed in the meantime.
        if (editable && !window.pgOfficeFormula) {
            withFormulas(function () {
                if (window.pgOfficeFormula && book.isConnected && shown.index >= 0 && !shown.cell && !shown.editor && !steps.length) {
                    show(shown.index);
                }
            });
        }

        return {
            kind: 'xlsx',
            version: office.version || '',
            count: count,
            edits: function () {
                return listEdits(listNow(), office.sheets.map(function (sheet) { return sheet.name; }), rangeName);
            },
            reset: function () {
                shown.editor = null;
                steps = [];
                stepHistory.clear();
                rebuild();
                drawTabs();
                openTab(Math.min(Math.max(shown.index, 0), workbook.sheets.length - 1));
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

    // Whether an element is a tab as the server and drawText() draw it.
    function isTabChar(node) {
        return (' ' + (node.className || '') + ' ').indexOf(' office-tabchar ') !== -1;
    }

    // A paragraph's text read from its drawing as it is typed in: text as it
    // stands, a drawn tab a tab, a br a line break. The br a browser keeps in
    // an emptied paragraph, or after a line break typed at its end, only
    // holds the line open and is no text.
    function paragraphText(root) {
        var text = '';
        var lastBreak = false;

        (function walk(current) {
            for (var child = current.firstChild; child; child = child.nextSibling) {
                if (child.nodeType === 3) {
                    if (child.nodeValue !== '') {
                        text += child.nodeValue;
                        lastBreak = false;
                    }
                } else if (child.nodeType !== 1) {
                    continue;
                } else if (child.nodeName === 'BR') {
                    text += '\n';
                    lastBreak = true;
                } else if (isTabChar(child)) {
                    text += '\t';
                    lastBreak = false;
                } else {
                    walk(child);
                }
            }
        })(root);

        if (lastBreak && (text.length === 1 || text.charAt(text.length - 2) === '\n')) {
            text = text.slice(0, -1);
        }

        return text;
    }

    // The node and offset in a paragraph's drawing where a point of its text
    // stands, counted as textOffset() counts it: inside a text when it can
    // be, else beside a tab or a line break. A point past the text is its
    // end; an empty paragraph holds its own start.
    function textPoint(root, offset) {
        var left = Math.max(0, offset);
        var found = null;
        var last = null;

        function indexIn(parent, child) {
            return Array.prototype.indexOf.call(parent.childNodes, child);
        }

        (function walk(current) {
            for (var child = current.firstChild; child && !found; child = child.nextSibling) {
                if (child.nodeType === 3) {
                    if (left <= child.nodeValue.length) {
                        found = { node: child, offset: left };
                        return;
                    }

                    left -= child.nodeValue.length;
                    last = { node: child, offset: child.nodeValue.length };
                } else if (child.nodeType === 1 && (child.nodeName === 'BR' || isTabChar(child))) {
                    if (left === 0) {
                        found = { node: current, offset: indexIn(current, child) };
                        return;
                    }

                    left -= 1;
                    last = { node: current, offset: indexIn(current, child) + 1 };
                } else if (child.nodeType === 1) {
                    walk(child);
                }
            }
        })(root);

        return found || last || { node: root, offset: 0 };
    }

    // Text into a node, tabs and line breaks the way the server draws them.
    function appendText(node, text) {
        text.split('\n').forEach(function (line, index) {
            if (index > 0) {
                node.appendChild(document.createElement('br'));
            }

            line.split('\t').forEach(function (piece, position) {
                if (position > 0) {
                    var tab = el('span', 'office-tabchar', ' ');

                    // One character of the text: the caret passes it whole.
                    tab.setAttribute('contenteditable', 'false');
                    node.appendChild(tab);
                }

                if (piece !== '') {
                    node.appendChild(document.createTextNode(piece));
                }
            });
        });
    }

    // A paragraph's own text drawn plain, or with bold, italic and underline
    // (bits, one a character: 1, 2 and 4), colour, highlight and size as
    // the server draws them; an empty one keeps its line.
    function drawText(node, text, bits) {
        node.textContent = '';

        if (text === '') {
            node.appendChild(document.createTextNode(' '));
            return;
        }

        if (!bits) {
            appendText(node, text);
            return;
        }

        var start = 0;

        for (var index = 1; index <= text.length; index++) {
            if (index < text.length && (bits[index] || 0) === (bits[start] || 0)) {
                continue;
            }

            var look = bits[start] || 0;
            var outer = node;
            var style = lookStyle(lookParts(look));

            // A link is drawn as one, never followed: a click opens the
            // link window (renderDocument()).
            if (linkOf(look)) {
                outer = outer.appendChild(el('span', 'office-link'));
                outer.title = linkOf(look);
            }

            if (style) {
                outer = outer.appendChild(document.createElement('span'));
                outer.setAttribute('style', style);
            }

            [[4, 'u'], [2, 'em'], [1, 'strong']].forEach(function (pair) {
                if (look & pair[0]) {
                    outer = outer.appendChild(document.createElement(pair[1]));
                }
            });

            appendText(outer, text.slice(start, index));
            start = index;
        }
    }

    // The bits of bold (1), italic (2) and underline (4) a format sends.
    var DOC_FORMATS = [['bold', 1], ['italic', 2], ['underline', 4]];

    // The kinds a paragraph can be given, in the order the choice shows them.
    var DOC_KINDS = ['normal', 'heading1', 'heading2', 'heading3', 'bullet', 'number'];

    // The parts of a character's look beside the bits: what a save calls
    // each and the key the server's marks give it. A link's address rides
    // on the look as the colours do (includes/fn/office_docx_link.php).
    var DOC_LOOK_FORMATS = [['color', 'c'], ['highlight', 'h'], ['size', 's'], ['link', 'l']];

    // The colour a link is given when it is made, as a word processor's
    // link style draws it.
    var DOC_LINK_COLOUR = '0563C1';

    // Word's highlight colours, each with the colour it is drawn in
    // (pg_office_docx_highlight_names()); the bar offers them in the order
    // of Word's own menu, white left out as Word does.
    var DOC_HIGHLIGHTS = [['yellow', 'FFFF00'], ['green', '00FF00'], ['cyan', '00FFFF'], ['magenta', 'FF00FF'], ['blue', '0000FF'],
        ['red', 'FF0000'], ['darkBlue', '000080'], ['darkCyan', '008080'], ['darkGreen', '008000'], ['darkMagenta', '800080'],
        ['darkRed', '800000'], ['darkYellow', '808000'], ['darkGray', '808080'], ['lightGray', 'C0C0C0'], ['black', '000000']];
    var DOC_HIGHLIGHT_DRAWN = { white: 'FFFFFF' };

    DOC_HIGHLIGHTS.forEach(function (item) {
        DOC_HIGHLIGHT_DRAWN[item[0]] = item[1];
    });

    // A paragraph's alignments as a save names them, with the bar's icon.
    var DOC_ALIGNS = [['start', 'bi-text-left'], ['center', 'bi-text-center'], ['end', 'bi-text-right'], ['justify', 'bi-justify']];

    // Each set of colour, highlight and size a character has, kept once: a
    // character's look is its bits (DOC_FORMATS) plus eight times the
    // number of its set, so looks stay numbers compared with ===. Set 0
    // has none of them; set 1 is unknown, unlike every other (exactBase()).
    var DOC_LOOK_SETS = [{}, null];
    var DOC_LOOK_KEYS = { '': 0 };

    function lookSet(look) {
        var parts = {};
        var key = '';

        DOC_LOOK_FORMATS.forEach(function (format) {
            var value = look ? look[format[1]] : null;

            if (value !== undefined && value !== null && value !== '' && value !== false) {
                parts[format[1]] = value;
                key += format[1] + value + '\n';
            }
        });

        if (!own(DOC_LOOK_KEYS, key)) {
            DOC_LOOK_KEYS[key] = DOC_LOOK_SETS.length;
            DOC_LOOK_SETS.push(parts);
        }

        return DOC_LOOK_KEYS[key];
    }

    // The colour, highlight and size of a character's look ({c, h, s},
    // each only when it has it); null when unknown.
    function lookParts(value) {
        var set = DOC_LOOK_SETS[Math.floor((value || 0) / 8)];

        return set ? JSON.parse(JSON.stringify(set)) : null;
    }

    // A look with one part (c, h or s) set, or taken off with ''; the bits
    // and the other parts kept.
    function withLook(value, key, part) {
        var look = lookParts(value) || {};

        look[key] = part;

        return ((value || 0) & 7) + lookSet(look) * 8;
    }

    // The inline style a colour, highlight and size are drawn with, the
    // twin of pg_office_docx_look_style().
    function lookStyle(look) {
        var style = [];

        if (look && /^[0-9A-F]{6}$/.test(look.c || '')) {
            style.push('color:#' + look.c);
        }

        if (look && own(DOC_HIGHLIGHT_DRAWN, look.h || '')) {
            style.push('background-color:#' + DOC_HIGHLIGHT_DRAWN[look.h]);
        }

        if (look && typeof look.s === 'number' && look.s > 0) {
            style.push('font-size:' + look.s + 'pt');
        }

        return style.join(';');
    }

    // The address of the link a character's look stands in; '' for none.
    function linkOf(value) {
        var look = lookParts(value);

        return (look && typeof look.l === 'string') ? look.l : '';
    }

    // An address as the link window takes it, written the way a save sends
    // it: http and https with a host, mailto and tel as they are; an e-mail
    // address or a phone number given its scheme, a bare web address
    // https. null when it cannot be a link's (the server checks the same:
    // pg_office_docx_link_value()).
    function linkAddress(text) {
        var value = String(text || '').replace(/^\s+|\s+$/g, '');

        if (/^tel:/i.test(value) || (!/^[a-z][a-z0-9+.\-]*:/i.test(value) && /^\+?[0-9][0-9 ().\-]{3,}$/.test(value))) {
            value = 'tel:' + value.replace(/^tel:/i, '').replace(/\s+/g, '');
        } else if (!/^[a-z][a-z0-9+.\-]*:/i.test(value) && /^[^\s@\/:]+@[^\s@\/]+\.[^\s@\/]+$/.test(value)) {
            value = 'mailto:' + value;
        } else if (!/^[a-z][a-z0-9+.\-]*:/i.test(value) && /^[^\s\/:@#?]+\.[^\s\/:@#?]+/.test(value)) {
            value = 'https://' + value;
        }

        if (/^https?:/i.test(value)) {
            value = value.replace(/ /g, '%20');
        }

        if (value.length > 2048 || /[\u0000-\u0020\u007F-\u00A0\u2028\u2029]/.test(value)) {
            return null;
        }

        if (/^https?:\/\/([^\/?#@]*@)?[^\/?#@:]+(:[0-9]{1,5})?([\/?#]|$)/i.test(value) || /^mailto:[^@?]+@.+$/i.test(value)
            || /^tel:\+?[0-9A-Za-z().\-]+$/i.test(value)) {
            return value;
        }

        return null;
    }

    // A paragraph's alignment in the file, from the ones the server sent
    // (center, end, justify); every other paragraph is at its start.
    function alignOf(aligns, position) {
        var align = own(aligns, position) ? aligns[position] : '';

        return (align === 'center' || align === 'end' || align === 'justify') ? align : 'start';
    }

    // The CSS text-align an alignment is drawn with.
    function alignCss(align) {
        return align;
    }

    // The alignment a save sends for a paragraph: the one asked for when it
    // differs from the file's, else null.
    function alignSent(file, want) {
        return (want && want !== file) ? want : null;
    }

    // A paragraph's look, a number for each UTF-16 unit of its text, from
    // the stretches the server sent ([[characters, bits, {c, h, s}?], ...]),
    // which count characters: one outside the basic plane takes two units.
    function expandMarks(marks, text) {
        var bits = [];
        var points = Array.from(text);
        var at = 0;

        (marks || []).forEach(function (mark) {
            var look = mark[2] ? (mark[1] & 7) + lookSet(mark[2]) * 8 : mark[1];

            for (var count = 0; count < mark[0] && at < points.length; count++, at++) {
                for (var unit = 0; unit < points[at].length; unit++) {
                    bits.push(look);
                }
            }
        });

        while (bits.length < text.length) {
            bits.push(0);
        }

        return bits;
    }

    // A look carried over to a changed text: the characters kept at the
    // start and the end keep theirs, the ones typed between take the look
    // of the character before them (after them at the start), as the
    // server puts typed text into the run before it.
    function carryMarks(bits, before, after) {
        var head = 0;
        var tail = 0;

        while (head < before.length && head < after.length && before[head] === after[head]) {
            head++;
        }

        while (tail < before.length - head && tail < after.length - head && before[before.length - 1 - tail] === after[after.length - 1 - tail]) {
            tail++;
        }

        var middle = after.length - head - tail;
        var near = head > 0 ? bits[head - 1] : (tail > 0 ? bits[before.length - tail] : 0);
        var out = bits.slice(0, head);

        // Text typed right after a link goes on outside it, as the server
        // writes it into the run after the link.
        if (head > 0 && linkOf(near) && linkOf(near) !== (tail > 0 ? linkOf(bits[before.length - tail]) : '')) {
            near = withLook(near, 'l', '');
        }

        for (var count = 0; count < middle; count++) {
            out.push(near || 0);
        }

        return out.concat(bits.slice(before.length - tail));
    }

    // The stretches whose bold, italic, underline, colour, highlight or
    // size differ between the file's look and the one asked for, as a save
    // sends them: a part taken off is sent as false.
    function formatRanges(base, want) {
        var list = [];

        DOC_FORMATS.forEach(function (format) {
            var bit = format[1];
            var start = -1;

            for (var index = 0; index <= want.length; index++) {
                var differs = index < want.length && (want[index] & bit) !== (base[index] & bit);

                if (start >= 0 && (!differs || !!(want[index] & bit) !== !!(want[start] & bit))) {
                    var set = {};

                    set[format[0]] = !!(want[start] & bit);
                    list.push({ start: start, end: index, set: set });
                    start = -1;
                }

                if (differs && start < 0) {
                    start = index;
                }
            }
        });

        DOC_LOOK_FORMATS.forEach(function (format) {
            var start = -1;

            // A part of an unknown look is undefined, so it always differs.
            function partAt(looks, index) {
                var look = lookParts(looks[index]);

                return look ? (own(look, format[1]) ? look[format[1]] : false) : undefined;
            }

            for (var index = 0; index <= want.length; index++) {
                var differs = index < want.length && partAt(want, index) !== partAt(base, index);

                if (start >= 0 && (!differs || partAt(want, index) !== partAt(want, start))) {
                    var set = {};

                    set[format[0]] = partAt(want, start);
                    list.push({ start: start, end: index, set: set });
                    start = -1;
                }

                if (differs && start < 0) {
                    start = index;
                }
            }
        });

        return list;
    }

    // A paragraph cut where Enter is pressed: the text selected (start to
    // end, UTF-16 units) is taken out, the text before stays, the text
    // after goes on into a new paragraph; each character keeps its look.
    function cutLook(text, bits, start, end) {
        return {
            head: text.slice(0, start),
            tail: text.slice(end),
            headBits: bits.slice(0, start),
            tailBits: bits.slice(end, text.length)
        };
    }

    // A base look that differs from the one asked for in every bit and has
    // an unknown colour, highlight and size, so the save sends each of them
    // for each character: text moved into a new run whose own look is the
    // paragraph mark's, which the view does not know.
    function exactBase(want) {
        return want.map(function (bits) {
            return (((bits || 0) & 7) ^ 7) + 8;
        });
    }

    // A list of states to go back and forward through, for Undo and Redo:
    // record() keeps the state before a change, undo(now) gives the last
    // one kept and keeps now to redo, redo(now) the other way round. A new
    // change drops what could be redone; only the last limit states stay.
    function changeList(limit) {
        var past = [];
        var future = [];

        return {
            record: function (state) {
                past.push(state);
                future = [];

                if (past.length > limit) {
                    past.shift();
                }
            },
            undo: function (now) {
                if (!past.length) {
                    return null;
                }

                future.push(now);

                return past.pop();
            },
            redo: function (now) {
                if (!future.length) {
                    return null;
                }

                past.push(now);

                return future.pop();
            },
            canUndo: function () {
                return past.length > 0;
            },
            canRedo: function () {
                return future.length > 0;
            },
            clear: function () {
                past = [];
                future = [];
            }
        };
    }

    // Undo and Redo of a workbook's list of changes (renderBook()): a state
    // is a copy of the whole list. change(list, apply) runs apply(), which
    // changes the list in place, and keeps the list as it was when apply()
    // altered it (true), so a press puts back one change even where the
    // list merged it into an entry already there. undo(list) and
    // redo(list) give a copy of the list to put in its place, or null.
    function listHistory(limit) {
        var states = changeList(limit);
        var copy = function (list) { return JSON.parse(JSON.stringify(list)); };

        return {
            change: function (list, apply) {
                var before = JSON.stringify(list);

                apply();

                if (JSON.stringify(list) === before) {
                    return false;
                }

                states.record(JSON.parse(before));

                return true;
            },
            undo: function (list) {
                var state = states.undo(copy(list));

                return state ? copy(state) : null;
            },
            redo: function (list) {
                var state = states.redo(copy(list));

                return state ? copy(state) : null;
            },
            canUndo: states.canUndo,
            canRedo: states.canRedo,
            clear: states.clear
        };
    }

    // What a key asks of the list of changes: Ctrl+Z (Cmd on a Mac) undo,
    // Ctrl+Y and Ctrl+Shift+Z redo; '' for any other key.
    function historyKey(event) {
        if (!(event.ctrlKey || event.metaKey) || event.altKey) {
            return '';
        }

        var key = String(event.key).toLowerCase();

        if (key === 'z') {
            return event.shiftKey ? 'redo' : 'undo';
        }

        return key === 'y' ? 'redo' : '';
    }

    // A document as the safe HTML the server made, drawn on a white sheet.
    // Read for editing, the paragraphs it marked (data-p) may be given a new
    // text, except those it marked as holding more than text (data-lock);
    // the server checks the same again when the edit is saved. A paragraph
    // that may change is typed where it stands, the caret where the click
    // fell; what it is given shows plain until the file is saved, when the
    // server keeps the formatting of the words that did not change.
    // A new table's largest size and Word's widest table: the writer's
    // (includes/fn/office_docx_table.php).
    var DOC_TABLE_LIMITS = { rows: 20, columns: 10, widest: 63 };

    // A picture put into a document: one at most 2 MB, those of one save
    // at most 8 MB together, as the preview shows pictures (the writer's
    // PG_OFFICE_MAX_IMAGE and PG_OFFICE_MAX_IMAGES).
    var DOC_PICTURE_LIMITS = { one: 2 * 1024 * 1024, all: 8 * 1024 * 1024 };

    // A picture's kind by its first bytes, as the writer knows it: 'png',
    // 'jpeg' or 'gif'; '' for anything else.
    function pictureType(bytes) {
        var head = Array.prototype.slice.call(bytes || [], 0, 8);
        var starts = function (list) {
            return list.every(function (value, index) {
                return head[index] === value;
            });
        };

        if (starts([0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A])) {
            return 'png';
        }

        if (starts([0xFF, 0xD8, 0xFF])) {
            return 'jpeg';
        }

        return (starts([0x47, 0x49, 0x46, 0x38]) && (head[4] === 0x37 || head[4] === 0x39) && head[5] === 0x61) ? 'gif' : '';
    }

    // Why a picture of size bytes and kind type is not taken when those
    // put in already come to total bytes: a key of the strings, '' when it
    // is taken.
    function pictureRefusal(type, size, total) {
        if (!type) {
            return 'doc_picture_type';
        }

        if (size > DOC_PICTURE_LIMITS.one) {
            return 'doc_picture_large';
        }

        return (total + size > DOC_PICTURE_LIMITS.all) ? 'doc_picture_room' : '';
    }

    // How many rows the size grid shows once a size of that many rows is
    // pointed at: eight, and one more than the size, up to the largest.
    function pickRows(rows) {
        return Math.min(DOC_TABLE_LIMITS.rows, Math.max(8, rows + 1));
    }

    // The save entries of a table of the file from what the page shows now
    // (model: hidden when the whole table goes; rows, each fresh when put
    // in, hidden when deleted, anchor: a paragraph of the file in it; their
    // cells the same way, a fresh one with its paragraph entries, items).
    // A deleted column keeps a hidden cell in each new row, as the writer
    // puts rows in before any column goes. Columns put in are listed
    // first, their cells by the rows of the file (the table as it then
    // stands); then rows, their cells by every column; then what goes.
    // Several after one cell or row are listed in order; those before the
    // first go before it, left to right.
    function tableEntries(model) {
        var fileRows = model.rows.filter(function (row) {
            return !row.fresh;
        });
        var entries = [];

        if (!fileRows.length) {
            return entries;
        }

        if (model.hidden) {
            return [{ cell: fileRows[0].anchor, table: 'delete' }];
        }

        function cellsOf(list) {
            var cells = list.map(function (cell) {
                return cell.items || [];
            });

            return cells.some(function (items) { return items.length > 0; }) ? cells : null;
        }

        function add(anchor, action, cells) {
            var entry = { cell: anchor, table: action };

            if (cells) {
                entry.cells = cells;
            }

            entries.push(entry);
        }

        // Each run of new ones after one of the file, or before the first.
        function runs(list, put) {
            var last = null;
            var lead = [];

            list.forEach(function (item, index) {
                if (item.fresh) {
                    if (last) {
                        put(last, 'after', index);
                    } else {
                        lead.push(index);
                    }
                    return;
                }

                lead.forEach(function (at) {
                    put(item, 'before', at);
                });
                lead = [];
                last = item;
            });
        }

        var reference = fileRows[0].cells;

        runs(reference, function (cell, way, index) {
            add(cell.anchor, 'column_' + way, cellsOf(fileRows.map(function (row) {
                return row.cells[index];
            })));
        });

        runs(model.rows, function (row, way, index) {
            add(row.anchor, 'row_' + way, cellsOf(model.rows[index].cells));
        });

        fileRows.forEach(function (row) {
            if (row.hidden) {
                add(row.anchor, 'row_delete', null);
            }
        });

        reference.forEach(function (cell) {
            if (!cell.fresh && cell.hidden) {
                add(cell.anchor, 'column_delete', null);
            }
        });

        return entries;
    }

    function renderDocument(container, office, strings, options) {
        var doc = el('div', 'office-doc office-doc-paged');

        // The sheet is file content: white with dark text in both themes,
        // so the panel's light tokens are used inside it.
        var paper = el('div', 'office-paper');
        var status = el('div', 'office-note office-status');
        var edit = office.edit || null;
        var editable = !!(options && options.editable) && !!edit;
        var texts = (edit && edit.ok && edit.paragraphs) ? edit.paragraphs : {};
        var marks = (edit && edit.ok && edit.marks) ? edit.marks : {};

        // By position, each paragraph's kind in the file (normal, heading1
        // to heading3, bullet, number, other), and the one asked for when
        // it is to change (DOC_KINDS).
        var kinds = (edit && edit.ok && edit.kinds) ? edit.kinds : {};
        var wantKinds = {};
        var kindChoice = null;

        // By position, the alignment of a paragraph not at its start in the
        // file (center, end, justify), and the one asked for when it is to
        // change (DOC_ALIGNS); the file's inline alignment of a paragraph
        // drawn, to put back.
        var aligns = (edit && edit.ok && edit.aligns) ? edit.aligns : {};
        var wantAligns = {};
        var fileAligns = {};

        // By position, why a paragraph may not be deleted (picture, section,
        // cell, last, bookmark, locked) and those no paragraph may be added
        // after; the paragraphs to delete; how many were added. An added
        // paragraph is named n1, n2... and remembers the paragraph of the
        // file it follows (data-after), whose kind it takes.
        var keeps = (edit && edit.ok && edit.keeps) ? edit.keeps : {};
        var shut = (edit && edit.ok && edit.shut) ? edit.shut : {};
        var deletes = {};
        var added = 0;
        var blockControls = null;

        // By position: the text typed, and the server's drawing of a
        // paragraph opened, to put back when its change is undone.
        var changes = {};
        var drawn = {};

        // By position, the bold, italic and underline of a paragraph whose
        // text was selected for them: the text they are counted in, the
        // file's look and the one asked for, a number a character
        // (DOC_FORMATS). The bar above the document sets them.
        var looks = {};
        var bar = null;
        var controls = {};
        var barMenuOpen = null;

        // The paragraphs marked, the one picked, the one being typed in
        // (editor: its node, position, whether anything was typed, the
        // last selection in it), what was selected in it when it was left,
        // whether one was ever typed in, and what the status line adds.
        var nodes = [];
        var shown = { node: null, editor: null, last: null, opened: false, note: '' };

        // The document's own list of changes for Undo and Redo: the state
        // before each change, the last 100. Typing is one change while the
        // caret goes on where the last letter went (run: its paragraph,
        // whether it types or deletes, the caret's point after it).
        var steps = changeList(100);
        var run = null;
        var stepControls = null;

        doc.setAttribute('aria-label', strings.doc || '');
        paper.setAttribute('data-bs-theme', 'light');
        doc.appendChild(paper);

        // The server made this markup itself from the file's text, escaped,
        // with its own small set of tags; nothing the file wrote reaches here
        // as markup.
        paper.innerHTML = office.html || '';

        if (!office.html) {
            paper.appendChild(el('div', 'office-note', strings.empty || ''));
        }

        container.appendChild(doc);

        if (office.images_lost > 0) {
            container.appendChild(el('div', 'office-note', (strings.images_lost || '').replace('{1}', office.images_lost)));
        }

        if (office.truncated) {
            container.appendChild(el('div', 'office-note', strings.truncated || ''));
        }

        // A document only read scrolls from the keyboard through its own box.
        if (!editable) {
            doc.tabIndex = 0;
            return null;
        }

        function normal(value) {
            return String(value).replace(/\r\n?/g, '\n');
        }

        // How a paragraph is made typable: plain text only where the
        // browser can, so it never takes markup of its own.
        var typeMode = (function () {
            var probe = document.createElement('div');

            try {
                probe.contentEditable = 'plaintext-only';
            } catch (error) {
                return 'true';
            }

            return probe.contentEditable === 'plaintext-only' ? 'plaintext-only' : 'true';
        })();

        // Whether something was typed into a paragraph that is still being
        // typed in: its text is then read from its drawing.
        function typedIn(position) {
            return !!shown.editor && shown.editor.typed && shown.editor.position === position;
        }

        // The changes as they stand, with the text of the paragraph being
        // typed in: Save is offered, and sends it, before Enter.
        function changesNow() {
            var list = {};

            Object.keys(changes).forEach(function (position) {
                list[position] = changes[position];
            });

            if (shown.editor && shown.editor.typed) {
                var position = shown.editor.position;
                var value = paragraphText(shown.editor.node);

                if (value === texts[position]) {
                    delete list[position];
                } else {
                    list[position] = value;
                }
            }

            return list;
        }

        // The text a paragraph has now: as it is being typed, changed, or
        // the file's.
        function textNow(position) {
            if (typedIn(position)) {
                return paragraphText(shown.editor.node);
            }

            return own(changes, position) ? changes[position] : texts[position];
        }

        // A paragraph's look carried over to the text it has now; made from
        // the file's when asked to, else null when none was touched.
        function lookOf(position, make) {
            var text = textNow(position);
            var look = own(looks, position) ? looks[position] : null;

            if (!look) {
                if (!make) {
                    return null;
                }

                var bits = expandMarks(marks[position], texts[position]);

                look = looks[position] = { text: texts[position], base: bits, want: bits.slice() };
            }

            if (look.text !== text) {
                look.base = carryMarks(look.base, look.text, text);
                look.want = carryMarks(look.want, look.text, text);
                look.text = text;
            }

            return look;
        }

        function formatted(position) {
            var look = lookOf(position, false);

            return !!look && look.want.some(function (bits, index) {
                return bits !== look.base[index];
            });
        }

        function isNew(position) {
            return String(position).charAt(0) === 'n';
        }

        // The paragraphs changed: a new text, a format, a kind, or more.
        function positionsNow() {
            var list = Object.keys(changesNow());

            Object.keys(looks).forEach(function (position) {
                if (list.indexOf(position) < 0 && formatted(position)) {
                    list.push(position);
                }
            });

            Object.keys(wantKinds).concat(Object.keys(wantAligns)).forEach(function (position) {
                if (list.indexOf(position) < 0) {
                    list.push(position);
                }
            });

            return list;
        }

        // Whether a paragraph goes with a table, a row or a column deleted
        // (hidden, not deleted itself).
        function goneWith(position) {
            var node = doc.querySelector('[data-p="' + position + '"]');

            return !!node && node.hidden && !own(deletes, position);
        }

        // The paragraphs and tables added, each its own save entry: those
        // in a new cell go with their table's.
        function topAdded() {
            return Array.prototype.filter.call(doc.querySelectorAll('.office-added, .office-added-table, .office-added-picture'), function (node) {
                return !(node.parentNode && node.parentNode.closest && node.parentNode.closest('.office-new-cell'));
            });
        }

        // The save entries of the tables of the file (tableEntries()), a
        // new cell's paragraph entries given by itemsOf(cell). A table
        // inside a part deleted goes with it.
        function fileTableEntries(itemsOf) {
            var entries = [];

            Array.prototype.forEach.call(paper.querySelectorAll('table'), function (table) {
                if (freshTable(table) || (table.parentNode && table.parentNode.closest('.office-added-table, .office-new-cell, [hidden]'))) {
                    return;
                }

                entries = entries.concat(tableEntries({
                    hidden: table.hidden,
                    rows: Array.prototype.map.call(table.rows, function (row) {
                        return {
                            fresh: row.classList.contains('office-new-row'),
                            hidden: row.hidden,
                            anchor: rowAnchor(row),
                            cells: Array.prototype.map.call(row.cells, function (cell) {
                                var fresh = cell.classList.contains('office-new-cell');

                                return { fresh: fresh, hidden: cell.hidden, anchor: fresh ? null : cellAnchor(cell), items: fresh ? itemsOf(cell) : null };
                            })
                        };
                    })
                }));
            });

            return entries;
        }

        // Paragraphs of the file changed and deleted, paragraphs and tables
        // added, and rows, columns and tables of the file put in or deleted.
        function count() {
            var total = positionsNow().filter(function (position) {
                return !isNew(position) && !own(deletes, position) && !goneWith(position);
            }).length;

            return total + Object.keys(deletes).length + topAdded().length + fileTableEntries(function () {
                return [];
            }).length;
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

        // The selection inside a paragraph as [start, end] of its text, or
        // null when the selection is not all in it.
        function selectionIn(node) {
            var selection = window.getSelection ? window.getSelection() : null;

            if (!selection || !selection.rangeCount) {
                return null;
            }

            var range = selection.getRangeAt(0);

            if (!node.contains(range.startContainer) || !node.contains(range.endContainer)) {
                return null;
            }

            var start = textOffset(node, range.startContainer, range.startOffset);
            var end = textOffset(node, range.endContainer, range.endOffset);

            return (start < 0 || end < 0) ? null : [Math.min(start, end), Math.max(start, end)];
        }

        // Selects the text from a point of one paragraph to a point of the
        // same or a later one, in their drawings.
        function placeSelection(node, start, end, last) {
            var selection = window.getSelection ? window.getSelection() : null;

            if (!selection || !document.createRange) {
                return;
            }

            var from = textPoint(node, start);
            var to = textPoint(last || node, end);
            var range = document.createRange();

            range.setStart(from.node, from.offset);
            range.setEnd(to.node, to.offset);
            selection.removeAllRanges();
            selection.addRange(range);
        }

        // The sheet's selection as it stands, or null when it is not on the
        // sheet.
        function sheetRange() {
            var selection = window.getSelection ? window.getSelection() : null;

            if (!selection || !selection.rangeCount) {
                return null;
            }

            var range = selection.getRangeAt(0);

            return paper.contains(range.startContainer) && paper.contains(range.endContainer) ? range : null;
        }

        // The paragraphs a selection on the sheet reaches, in reading order,
        // each with the stretch of its text selected: [{node, position,
        // start, end}]. Paragraphs not typed in are left out.
        function partsOf(range) {
            var parts = [];

            nodes.forEach(function (node) {
                if (node.hidden || !node.classList.contains('office-typing') || !range.intersectsNode(node)) {
                    return;
                }

                var position = node.getAttribute('data-p');
                var length = textNow(position).length;
                var start = node.contains(range.startContainer) ? textOffset(node, range.startContainer, range.startOffset) : 0;
                var end = node.contains(range.endContainer) ? textOffset(node, range.endContainer, range.endOffset) : length;

                // A selection ending at a paragraph's very start does not
                // reach into it.
                if (start >= 0 && end >= 0 && !(parts.length && end === 0 && !range.collapsed)) {
                    parts.push({ node: node, position: position, start: Math.min(start, end), end: Math.max(start, end) });
                }
            });

            return parts;
        }

        // The text selected for the bar, counted in each paragraph's text as
        // it is now: on the sheet, or what was selected when the focus left
        // it while a paragraph of it stays picked. null when none.
        function selectedParts() {
            var range = sheetRange();
            var parts = null;

            if (range && !range.collapsed) {
                parts = partsOf(range);
            } else if (!range && shown.last && shown.last.some(function (part) { return part.node === shown.node; })) {
                parts = shown.last;
            } else if (!range && shown.editor && shown.editor.range) {
                parts = [{ node: shown.editor.node, position: shown.editor.position, start: shown.editor.range[0], end: shown.editor.range[1] }];
            }

            parts = (parts || []).filter(function (part) {
                return part.start < part.end;
            });

            return parts.length ? parts : null;
        }

        // Sets bold, italic or underline on the selected text, or takes it
        // off when all of it has it already, as a word processor does.
        function applyFormat(format) {
            var parts = selectedParts();
            var bit = format[1];
            var all = true;

            if (!parts) {
                shown.note = strings.doc_format_select || '';
                drawStatus();
                return;
            }

            var focused = document.activeElement === paper;
            var first = parts[0];
            var last = parts[parts.length - 1];

            parts.forEach(function (part) {
                var look = lookOf(part.position, true);

                for (var index = part.start; index < Math.min(part.end, look.want.length); index++) {
                    all = all && !!(look.want[index] & bit);
                }
            });

            parts.forEach(function (part) {
                var look = lookOf(part.position, true);

                for (var index = part.start; index < Math.min(part.end, look.want.length); index++) {
                    look.want[index] = all ? (look.want[index] & ~bit) : (look.want[index] | bit);
                }

                redraw(part.node);
            });

            shown.note = '';

            // The text stays selected, to format it again.
            if (focused) {
                placeSelection(first.node, first.start, last.end, last.node);
            } else if (shown.last) {
                shown.last = parts;
            }

            drawBar();
            notify();
        }

        // Ctrl+B, Ctrl+I and Ctrl+U (Cmd on a Mac) format the selection;
        // Ctrl+K opens the link window.
        function formatKey(event) {
            if (!bar || !(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey) {
                return false;
            }

            var index = ['b', 'i', 'u'].indexOf(String(event.key).toLowerCase());

            // Ctrl+K opens the link window.
            if (String(event.key).toLowerCase() === 'k' && controls.link) {
                event.preventDefault();
                openLinkWindow();
                return true;
            }

            if (index < 0) {
                return false;
            }

            event.preventDefault();
            oneChange(function () {
                applyFormat(DOC_FORMATS[index]);
            });

            return true;
        }

        // The paragraph the kind choice speaks for: the one open or picked,
        // when its kind may change; else null.
        function kindPosition() {
            var node = shown.editor ? shown.editor.node : shown.node;
            var position = node ? node.getAttribute('data-p') : null;

            return (position !== null && own(kinds, position) && !own(deletes, position) && !node.classList.contains('office-locked')) ? position : null;
        }

        // The paragraph Add and Delete act on: the one open or picked.
        function blockNode() {
            return shown.editor ? shown.editor.node : shown.node;
        }

        // The table cell a paragraph stands in, or null outside a table:
        // paragraphs are joined only inside one.
        function cellOf(node) {
            var cell = node.parentNode ? node.parentNode.closest('td, th') : null;

            return (cell && paper.contains(cell)) ? cell : null;
        }

        // Says why a key did nothing.
        function refuse(note) {
            shown.note = note || '';
            drawStatus();
        }

        // A new paragraph after one, holding text with its look (bits, a
        // number a character), of the same kind (a list item continues its
        // list) or the one asked for. The server writes it with the
        // paragraph properties of the file's paragraph it follows; its text
        // goes into a new run, so each format is sent for it.
        function addAfter(node, text, bits, kind, place) {
            var position = node.getAttribute('data-p');
            var after = isNew(position) ? node.getAttribute('data-after') : position;
            var tag = (place && place !== node) ? 'p' : (/^(P|H[1-6]|LI)$/.test(node.tagName) ? node.tagName.toLowerCase() : 'p');
            var key = 'n' + (++added);
            var fresh = el(tag, (tag === 'li' ? node.className.replace(/\boffice-\S+/g, function (name) {
                return /^office-lvl-/.test(name) ? name : '';
            }) + ' ' : '') + 'office-para office-added');
            var want = kind || (own(wantKinds, position) ? wantKinds[position] : kinds[position]);

            // A paragraph of a new cell is written as the cell's first one
            // (data-like: the paragraph of the file its properties come
            // from), and so is one added after it in the cell.
            var like = node.hasAttribute('data-like');
            var base = like ? position : after;

            fresh.setAttribute('data-p', key);
            fresh.setAttribute('data-after', after);

            if (like) {
                fresh.setAttribute('data-like', node.getAttribute('data-like'));
            }

            texts[key] = '';
            marks[key] = [];
            drawn[key] = '';
            kinds[key] = own(kinds, base) ? kinds[base] : 'other';

            if (want && want !== kinds[key] && own(kinds, base)) {
                wantKinds[key] = want;
            }

            if (text !== '') {
                changes[key] = text;
                looks[key] = { text: text, base: exactBase(bits), want: bits.slice() };
            }

            // It is written with the alignment of the file's paragraph it
            // follows; one asked for on the paragraph it was cut from goes on.
            var align = alignSent(alignOf(aligns, like ? node.getAttribute('data-like') : after), alignNow(position));

            if (align) {
                wantAligns[key] = align;
            }

            // Put after the node asked for (place): a new table, or the
            // paragraph itself.
            place = place || node;
            place.parentNode.insertBefore(fresh, place.nextSibling);

            if (place === node) {
                nodes.splice(nodes.indexOf(node) + 1, 0, fresh);
            } else {
                nodes = Array.prototype.slice.call(doc.querySelectorAll('[data-p]'));
            }

            redraw(fresh);

            return fresh;
        }

        // Enter: the paragraph is cut at the caret, the selection taken out
        // first; the text after it goes on, with its look, into a new
        // paragraph after it, where the caret goes. At the end of a heading
        // the new paragraph is normal text, as in a word processor.
        function splitParagraph() {
            var range = sheetRange();
            var parts = (range && !range.collapsed) ? partsOf(range) : [];

            if (parts.length > 1) {
                if (!removeAcross(parts)) {
                    return;
                }

                parts = [];
            }

            // A whole paragraph selected (three clicks) is typed in too.
            if (parts.length === 1 && (!shown.editor || shown.editor.node !== parts[0].node)) {
                select(parts[0].node, false);
                openEditor(parts[0].node);
            }

            var editor = shown.editor;

            if (!editor) {
                return;
            }

            var node = editor.node;
            var position = editor.position;
            var anchor = isNew(position) ? node.getAttribute('data-after') : position;

            if (own(shut, anchor)) {
                refuse(strings.doc_shut);
                return;
            }

            var text = textNow(position);
            var at = parts.length === 1 ? [parts[0].start, parts[0].end] : (selectionIn(node) || [text.length, text.length]);
            var look = lookOf(position, true);
            var cut = cutLook(text, look.want, at[0], at[1]);
            var base = cutLook(text, look.base, at[0], at[1]);
            var kind = own(wantKinds, position) ? wantKinds[position] : kinds[position];

            shown.editor = null;
            setText(position, cut.head);
            looks[position] = { text: cut.head, base: base.headBits, want: cut.headBits };
            redraw(node);

            var fresh = addAfter(node, cut.tail, cut.tailBits, (cut.tail === '' && /^heading/.test(kind || '')) ? 'normal' : '');

            shown.note = '';
            notify();
            enter(fresh, 0);
        }

        // A paragraph's text as a change, unless it is the file's again.
        function setText(position, value) {
            if (value === texts[position]) {
                delete changes[position];
            } else {
                changes[position] = value;
            }
        }

        // Why a paragraph may not go in a join, '' when it may.
        function goneReason(node) {
            var reason = isNew(node.getAttribute('data-p')) ? '' : keepReason(node);

            return reason ? (strings['keep_' + reason] || strings.keep_locked || '') : '';
        }

        // Takes a paragraph out of the document: an added one simply goes,
        // one of the file is deleted on save and hidden till then (its
        // text went into another).
        function takeOut(node) {
            var position = node.getAttribute('data-p');

            if (shown.node === node) {
                shown.node = null;
            }

            if (isNew(position)) {
                nodes.splice(nodes.indexOf(node), 1);
                node.parentNode.removeChild(node);
                [changes, looks, wantKinds, wantAligns, texts, marks, kinds, drawn].forEach(function (map) {
                    delete map[position];
                });
                return;
            }

            deletes[position] = true;
            node.classList.remove('office-selected');
            node.hidden = true;
            redraw(node);
        }

        // Backspace at a paragraph's start (back) or Delete at its end:
        // the later paragraph's text and look go to the end of the earlier
        // one and the later one is deleted; refused where that delete is,
        // with its reason. A cell's or the document's last paragraph is
        // kept, the earlier one going into it instead, when that one may go.
        function joinParagraphs(node, back) {
            var other = beside(node, back ? -1 : 1);

            if (!other) {
                return;
            }

            var first = back ? other : node;
            var second = back ? node : other;

            if (!first.classList.contains('office-typing') || !second.classList.contains('office-typing')) {
                var stuck = first.classList.contains('office-typing') ? second : first;

                refuse(own(deletes, stuck.getAttribute('data-p')) ? strings.doc_deleted : stuck.title);
                return;
            }

            if (cellOf(first) !== cellOf(second)) {
                refuse(strings.doc_cells);
                return;
            }

            var why = goneReason(second);
            var keepSecond = false;

            if (why) {
                if (goneReason(first) || !/^(cell|last)$/.test(keepReason(second))) {
                    refuse(why);
                    return;
                }

                keepSecond = true;
            }

            closeEditor(true);

            var one = first.getAttribute('data-p');
            var two = second.getAttribute('data-p');
            var head = textNow(one);
            var tail = textNow(two);
            var lookOne = lookOf(one, true);
            var lookTwo = lookOf(two, true);
            var into = keepSecond ? second : first;
            var position = keepSecond ? two : one;

            // The text moved goes into a run of the paragraph it joins:
            // each of its formats is sent.
            setText(position, head + tail);
            looks[position] = {
                text: head + tail,
                base: keepSecond ? exactBase(lookOne.want).concat(lookTwo.base) : lookOne.base.concat(exactBase(lookTwo.want)),
                want: lookOne.want.concat(lookTwo.want)
            };
            takeOut(keepSecond ? first : second);
            redraw(into);
            shown.note = '';
            notify();
            enter(into, head.length);
        }

        // A selection over several paragraphs taken out, as typing over it,
        // Enter, Backspace or Delete does: the first keeps its text before
        // the selection, the last's text after it joins it, the others go.
        // Refused, with the reason, when a paragraph between may not change
        // or go, or the selection reaches over table cells.
        function removeAcross(parts) {
            var first = parts[0];
            var last = parts[parts.length - 1];
            var from = nodes.indexOf(first.node);
            var to = nodes.indexOf(last.node);
            var index;

            for (index = from; index <= to; index++) {
                var node = nodes[index];

                if (node.hidden) {
                    continue;
                }

                if (!node.classList.contains('office-typing')) {
                    refuse(own(deletes, node.getAttribute('data-p')) ? strings.doc_deleted : node.title);
                    return false;
                }

                if (cellOf(node) !== cellOf(first.node)) {
                    refuse(strings.doc_cells);
                    return false;
                }

                if (index > from && goneReason(node)) {
                    refuse(goneReason(node));
                    return false;
                }
            }

            closeEditor(true);

            var head = textNow(first.position).slice(0, first.start);
            var tail = textNow(last.position).slice(last.end);
            var lookFirst = lookOf(first.position, true);
            var lookLast = lookOf(last.position, true);

            setText(first.position, head + tail);
            looks[first.position] = {
                text: head + tail,
                base: lookFirst.base.slice(0, first.start).concat(exactBase(lookLast.want.slice(last.end))),
                want: lookFirst.want.slice(0, first.start).concat(lookLast.want.slice(last.end))
            };

            nodes.slice(from + 1, to + 1).forEach(function (node) {
                if (!node.hidden) {
                    takeOut(node);
                }
            });

            redraw(first.node);
            shown.note = '';
            notify();
            enter(first.node, head.length);

            return true;
        }

        // Why the picked paragraph of the file may not be deleted, '' when
        // it may: a cell's or the document's last paragraph may go once a
        // paragraph is added after it.
        function keepReason(node) {
            var position = node.getAttribute('data-p');
            var keep = own(keeps, position) ? keeps[position] : '';
            var next = node.nextElementSibling;

            if ((keep === 'cell' || keep === 'last') && next && next.classList.contains('office-added')) {
                keep = '';
            }

            return keep;
        }

        // Deletes the picked paragraph, or keeps it again when it was to be
        // deleted; an added one simply goes.
        function deleteParagraph() {
            var node = blockNode();

            if (!node) {
                shown.note = strings.doc_pick || '';
                drawStatus();
                return;
            }

            var position = node.getAttribute('data-p');

            if (isNew(position)) {
                var holder = cellOf(node);

                // A new cell keeps one paragraph.
                if (holder && holder.classList.contains('office-new-cell') && cellParagraphs(holder).length < 2) {
                    refuse(strings.keep_cell);
                    return;
                }

                closeEditor(false);

                var next = beside(node, 1) || beside(node, -1);

                nodes.splice(nodes.indexOf(node), 1);
                node.parentNode.removeChild(node);
                [changes, looks, wantKinds, wantAligns, texts, marks, kinds, drawn].forEach(function (map) {
                    delete map[position];
                });
                shown.node = null;
                shown.last = null;
                notify();

                if (next) {
                    enter(next, 0);
                } else {
                    drawBar();
                }
                return;
            }

            if (own(deletes, position)) {
                delete deletes[position];
                shown.note = '';
            } else {
                var keep = keepReason(node);

                if (keep) {
                    shown.note = strings['keep_' + keep] || strings.keep_locked || '';
                    drawStatus();
                    return;
                }

                closeEditor(true);
                deletes[position] = true;
                shown.note = strings.doc_deleted || '';
            }

            redraw(node);

            // A paragraph to be deleted is picked; one kept again is typed
            // in, the caret at its end.
            if (own(deletes, position)) {
                select(node);
            } else {
                enter(node, 'end');
            }

            drawBar();
            notify();
        }

        // Asks for a kind for the picked paragraph; its file's kind again
        // takes the change back.
        function applyKind(kind) {
            var position = kindPosition();

            if (position === null || DOC_KINDS.indexOf(kind) < 0) {
                drawBar();
                return;
            }

            if (kind === kinds[position]) {
                delete wantKinds[position];
            } else {
                wantKinds[position] = kind;
            }

            var node = doc.querySelector('[data-p="' + position + '"]');

            if (node && !(shown.editor && shown.editor.node === node)) {
                redraw(node);
            }

            drawBar();
            notify();
        }

        // A paragraph's alignment in the file; an added paragraph is written
        // with the one of the file's paragraph it follows, a new cell's
        // with the one of the paragraph its cell is copied from.
        function fileAlign(position) {
            var node = isNew(position) ? doc.querySelector('[data-p="' + position + '"]') : null;

            if (node && node.hasAttribute('data-like')) {
                return alignOf(aligns, node.getAttribute('data-like'));
            }

            return alignOf(aligns, node ? node.getAttribute('data-after') : position);
        }

        function alignNow(position) {
            return own(wantAligns, position) ? wantAligns[position] : fileAlign(position);
        }

        // An alignment asked for is drawn on the paragraph; without one the
        // server's drawing of it comes back.
        function drawAlign(node) {
            var position = node.getAttribute('data-p');

            if (!own(fileAligns, position)) {
                fileAligns[position] = node.style.textAlign || '';
            }

            node.style.textAlign = own(wantAligns, position) ? alignCss(wantAligns[position]) : fileAligns[position];
        }

        // The paragraphs an alignment acts on: those the selection reaches,
        // else the one open or picked; locked and deleted ones left out.
        function alignPositions() {
            var range = sheetRange();
            var parts = (range && !range.collapsed) ? partsOf(range) : (selectedParts() || []);
            var list = parts.map(function (part) {
                return part.position;
            });

            if (!list.length && kindPosition() !== null) {
                list.push(kindPosition());
            }

            return list.filter(function (position) {
                var node = doc.querySelector('[data-p="' + position + '"]');

                return node && !own(deletes, position) && !node.classList.contains('office-locked');
            });
        }

        function applyAlign(align) {
            var positions = alignPositions();

            if (!positions.length) {
                refuse(strings.doc_pick || '');
                return;
            }

            positions.forEach(function (position) {
                if (align === fileAlign(position)) {
                    delete wantAligns[position];
                } else {
                    wantAligns[position] = align;
                }

                var node = doc.querySelector('[data-p="' + position + '"]');

                drawAlign(node);
                node.classList.toggle('office-edited', own(changes, position) || formatted(position) || own(wantKinds, position) || own(wantAligns, position) || isNew(position));
            });

            shown.note = '';
            drawBar();
            notify();
        }

        // Gives the selected text a colour (c), a highlight (h) or a size
        // (s), or takes it off with ''; the text stays selected.
        function applyLook(key, value, parts) {
            parts = parts || selectedParts();

            if (!parts) {
                shown.note = strings.doc_format_select || '';
                drawStatus();
                drawBar();
                return;
            }

            parts.forEach(function (part) {
                var look = lookOf(part.position, true);

                for (var index = part.start; index < Math.min(part.end, look.want.length); index++) {
                    look.want[index] = withLook(look.want[index], key, value);
                }

                redraw(part.node);
            });

            shown.note = '';

            if (document.activeElement === paper) {
                placeSelection(parts[0].node, parts[0].start, parts[parts.length - 1].end, parts[parts.length - 1].node);
            } else {
                shown.last = parts;
            }

            drawBar();
            notify();
        }

        // The text a link is put on, changed or taken off from: the text
        // selected, else the whole link the caret stands in. With the
        // address all of it has ('' none, null mixed); null when neither.
        function linkTarget() {
            var parts = selectedParts();

            if (!parts && shown.editor && shown.editor.range && shown.editor.range[0] === shown.editor.range[1]) {
                var position = shown.editor.position;
                var look = lookOf(position, true);
                var at = shown.editor.range[0];
                var address = linkOf(look.want[at]) || (at > 0 ? linkOf(look.want[at - 1]) : '');

                if (address) {
                    var start = linkOf(look.want[at]) === address ? at : at - 1;
                    var end = start;

                    while (start > 0 && linkOf(look.want[start - 1]) === address) {
                        start--;
                    }

                    while (end < look.want.length && linkOf(look.want[end]) === address) {
                        end++;
                    }

                    parts = [{ node: shown.editor.node, position: position, start: start, end: end }];
                }
            }

            if (!parts) {
                return null;
            }

            var found;

            parts.forEach(function (part) {
                var look = lookOf(part.position, true);

                for (var index = part.start; index < Math.min(part.end, look.want.length); index++) {
                    found = (found === undefined || found === linkOf(look.want[index])) ? linkOf(look.want[index]) : null;
                }
            });

            return { parts: parts, address: found === undefined ? '' : found };
        }

        // Puts the text into a link to an address, or takes it out of its
        // link with ''. Text newly linked takes a link's colour and
        // underline; text leaving a link loses them when it has exactly
        // those, as the server takes the link style off it.
        function applyLink(parts, address) {
            parts.forEach(function (part) {
                var look = lookOf(part.position, true);

                for (var index = part.start; index < Math.min(part.end, look.want.length); index++) {
                    var value = look.want[index];
                    var set = lookParts(value) || {};

                    if (address && !linkOf(value)) {
                        value = withLook(value, 'c', DOC_LINK_COLOUR) | 4;
                    } else if (!address && linkOf(value) && set.c === DOC_LINK_COLOUR && (value & 4)) {
                        value = withLook(value, 'c', '') & ~4;
                    }

                    look.want[index] = withLook(value, 'l', address);
                }

                redraw(part.node);
            });

            shown.note = '';
            paper.focus({ preventScroll: true });
            placeSelection(parts[0].node, parts[0].start, parts[parts.length - 1].end, parts[parts.length - 1].node);
            follow();
            drawBar();
            notify();
        }

        // The link window under the bar's Link button: the address, Apply,
        // Cancel and, on a link, Remove link. It acts on the text selected
        // when it opened, or the link the caret stands in.
        var linkWindow = null;

        function closeLinkWindow(back) {
            if (!linkWindow) {
                return;
            }

            var target = linkWindow.target;

            document.removeEventListener('mousedown', linkWindow.outside, true);
            linkWindow.node.remove();
            linkWindow = null;

            if (back && target) {
                paper.focus({ preventScroll: true });
                placeSelection(target.parts[0].node, target.parts[0].start, target.parts[target.parts.length - 1].end, target.parts[target.parts.length - 1].node);
                follow();
            }

            drawBar();
        }

        function openLinkWindow() {
            closeBarMenu(false);
            closeLinkWindow(false);
            closeTableWindow(false);

            var target = linkTarget();

            if (!target) {
                refuse(strings.doc_link_select || '');
                return;
            }

            var anchor = controls.link;
            var node = el('div', 'office-link-window pg-card');
            var id = 'office-link-' + (++linkWindows);
            var label = el('label', 'pg-field-label', strings.doc_link_address || '');
            var input = el('input', 'pg-input pg-input-sm');
            var help = el('div', 'pg-field-help', strings.doc_link_hint || '');
            var foot = el('div', 'office-link-foot');
            var apply = el('button', 'pg-button pg-button-fill pg-button-sm', strings.doc_link_apply || '');
            var cancel = el('button', 'pg-button pg-button-quiet pg-button-sm', strings.doc_link_cancel || '');

            node.setAttribute('role', 'dialog');
            node.setAttribute('aria-label', strings.doc_link || '');
            label.setAttribute('for', id);
            input.id = id;
            input.type = 'text';
            input.setAttribute('inputmode', 'url');
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('spellcheck', 'false');
            input.setAttribute('aria-describedby', id + '-help');
            input.value = target.address || '';
            help.id = id + '-help';
            apply.type = 'button';
            cancel.type = 'button';

            function done() {
                var address = linkAddress(input.value);

                if (address === null) {
                    input.setAttribute('aria-invalid', 'true');
                    help.className = 'pg-field-error';
                    help.textContent = strings.doc_link_bad || '';
                    input.focus();
                    return;
                }

                closeLinkWindow(false);
                oneChange(function () {
                    applyLink(target.parts, address);
                });
            }

            // A link the file holds, to a web address or a bookmark, can be
            // taken off.
            if (target.address) {
                var remove = el('button', 'pg-button pg-button-danger pg-button-sm', strings.doc_link_remove || '');

                remove.type = 'button';
                remove.addEventListener('click', function () {
                    closeLinkWindow(false);
                    oneChange(function () {
                        applyLink(target.parts, '');
                    });
                });
                foot.appendChild(remove);
            }

            foot.appendChild(el('span', 'office-link-gap'));
            foot.appendChild(cancel);
            foot.appendChild(apply);
            cancel.addEventListener('click', function () {
                closeLinkWindow(true);
            });
            apply.addEventListener('click', done);
            input.addEventListener('input', function () {
                input.removeAttribute('aria-invalid');
                help.className = 'pg-field-help';
                help.textContent = strings.doc_link_hint || '';
            });

            node.addEventListener('keydown', function (event) {
                event.stopPropagation();

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeLinkWindow(true);
                } else if (event.key === 'Enter' && event.target === input) {
                    event.preventDefault();
                    done();
                }
            });

            node.appendChild(label);
            node.appendChild(input);
            node.appendChild(help);
            node.appendChild(foot);
            bar.appendChild(node);
            node.style.left = Math.max(0, anchor.offsetLeft) + 'px';
            node.style.top = (anchor.offsetTop + anchor.offsetHeight + 4) + 'px';

            // Near the right edge (a narrow screen) the window stays in the
            // view.
            if (node.offsetLeft + node.offsetWidth > bar.clientWidth) {
                node.style.left = Math.max(0, bar.clientWidth - node.offsetWidth) + 'px';
            }

            linkWindow = {
                node: node,
                target: target,
                outside: function (event) {
                    if (linkWindow && !linkWindow.node.contains(event.target) && event.target !== anchor && !anchor.contains(event.target)) {
                        closeLinkWindow(false);
                    }
                }
            };

            document.addEventListener('mousedown', linkWindow.outside, true);
            anchor.setAttribute('aria-expanded', 'true');
            input.focus({ preventScroll: true });
            input.select();
        }

        var linkWindows = 0;

        // Tables. Each row and cell carries a name (data-r, data-c; a new
        // table data-t), so a state of the list tells them apart. A row,
        // column or table of the file that is deleted is hidden till the
        // save, with its paragraphs; one put in (.office-new-row,
        // .office-new-cell, .office-added-table) simply goes. A new cell
        // holds added paragraphs typed as any other, sent with its table.
        var tableKeys = 0;

        function nameOf(element, attribute, prefix) {
            element.setAttribute(attribute, prefix + (++tableKeys));
        }

        function freshTable(table) {
            return table.classList.contains('office-added-table');
        }

        // The paragraphs of a cell itself, not of a table inside it.
        function cellParagraphs(cell) {
            return Array.prototype.filter.call(cell.querySelectorAll('[data-p]'), function (node) {
                return cellOf(node) === cell && !node.hidden;
            });
        }

        function firstIn(cell, last) {
            var list = cell ? cellParagraphs(cell) : [];

            return list.length ? list[last ? list.length - 1 : 0] : null;
        }

        function shownOf(list) {
            return Array.prototype.filter.call(list, function (item) {
                return !item.hidden;
            });
        }

        // A paragraph of the file in a cell, or in a row: what a save names
        // its table, row or column by.
        function cellAnchor(cell) {
            var found = null;

            Array.prototype.some.call(cell.querySelectorAll('[data-p]'), function (node) {
                var position = node.getAttribute('data-p');

                if (cellOf(node) === cell && !isNew(position)) {
                    found = Number(position);
                }

                return found !== null;
            });

            return found;
        }

        function rowAnchor(row) {
            var found = null;

            Array.prototype.some.call(row.cells, function (cell) {
                found = cell.classList.contains('office-new-cell') ? null : cellAnchor(cell);

                return found !== null;
            });

            return found;
        }

        // The paragraph of the file a new cell copied from a cell is
        // written like: its first one, or the one that cell copies; null
        // for a plain one.
        function likeOf(cell) {
            var first = cell ? Array.prototype.filter.call(cell.querySelectorAll('[data-p]'), function (node) {
                return cellOf(node) === cell;
            })[0] : null;

            if (!first) {
                return null;
            }

            var position = first.getAttribute('data-p');

            if (!isNew(position)) {
                return position;
            }

            return first.getAttribute('data-like') || null;
        }

        // A new cell holding one empty paragraph, copied from a cell (its
        // kind, its alignment, hidden in a deleted column) or plain.
        function newCell(source) {
            var cell = el(source && source.tagName === 'TH' ? 'th' : 'td', 'office-new-cell');
            var like = likeOf(source);
            var key = 'n' + (++added);
            var fresh = el('p', 'office-para office-added');

            nameOf(cell, 'data-c', 'k');
            cell.hidden = !!(source && source.hidden);
            fresh.setAttribute('data-p', key);
            fresh.setAttribute('data-after', '');
            fresh.setAttribute('data-like', like === null ? '' : like);
            fresh.hidden = cell.hidden;
            texts[key] = '';
            marks[key] = [];
            drawn[key] = '';
            kinds[key] = (like !== null && own(kinds, like)) ? kinds[like] : 'normal';
            cell.appendChild(fresh);
            redraw(fresh);

            return cell;
        }

        function newRow() {
            var row = el('tr', 'office-new-row');

            nameOf(row, 'data-r', 'k');

            return row;
        }

        // The table the paragraph open or picked stands in (its nearest),
        // with its row and cell; null outside a table.
        function tablePlace() {
            var node = blockNode();
            var cell = (node && !node.hidden) ? cellOf(node) : null;
            var row = cell ? cell.parentNode : null;
            var table = (row && row.closest) ? row.closest('table') : null;

            return table ? { node: node, cell: cell, row: row, table: table, fresh: freshTable(table) } : null;
        }

        // A table whose rows are not the same cells end to end: merged
        // cells or rows of another length. Its rows and columns stay.
        function uneven(table) {
            var length = -1;

            return Array.prototype.some.call(table.rows, function (row) {
                var wide = Array.prototype.some.call(row.cells, function (cell) {
                    return cell.colSpan > 1;
                });

                length = (length < 0) ? row.cells.length : length;

                return wide || row.cells.length !== length;
            });
        }

        // Whether a part of the file may go: nothing in it that a save
        // refuses to delete (a picture, a field, a note, a control).
        function removable(part) {
            return !Array.prototype.some.call(part.querySelectorAll('[data-p]'), function (node) {
                var keep = own(keeps, node.getAttribute('data-p')) ? keeps[node.getAttribute('data-p')] : '';

                return node.classList.contains('office-locked') || keep === 'picture' || keep === 'section' || keep === 'locked';
            });
        }

        // An added paragraph's maps go with it.
        function dropKeys(node) {
            var key = node.getAttribute('data-p');

            if (key) {
                [changes, looks, wantKinds, wantAligns, texts, marks, kinds, drawn].forEach(function (map) {
                    delete map[key];
                });
            }
        }

        // A part put in on the page goes, with what was typed in it.
        function removePart(part) {
            Array.prototype.forEach.call(part.querySelectorAll('.office-added'), dropKeys);
            dropKeys(part);
            part.parentNode.removeChild(part);
        }

        // A part of the file is hidden till the save deletes it, with its
        // paragraphs; paragraphs and tables added in its cells go, and new
        // cells in it are hidden with it.
        function hidePart(part) {
            topAdded().forEach(function (node) {
                if (part.contains(node) && node.parentNode) {
                    removePart(node);
                }
            });

            part.hidden = true;

            Array.prototype.forEach.call(part.querySelectorAll('[data-p]'), function (node) {
                node.hidden = true;
                node.classList.remove('office-selected');
            });
        }

        // After a table changed: the paragraphs read again in order, the
        // caret in the paragraph asked.
        function tableChanged(target, end) {
            nodes = Array.prototype.slice.call(doc.querySelectorAll('[data-p]'));

            if (shown.node && (!doc.contains(shown.node) || shown.node.hidden)) {
                shown.node = null;
            }

            shown.last = null;
            shown.note = '';
            notify();

            if (target) {
                enter(target, end ? 'end' : 0);
            } else {
                drawBar();
            }
        }

        // Insert row: a row below the caret's, its cells copied from it;
        // the caret goes to its first cell.
        function insertRow() {
            var place = tablePlace();

            if (!place) {
                return;
            }

            if (uneven(place.table)) {
                refuse(strings.doc_table_merged);
                return;
            }

            if (place.fresh && shownOf(place.table.rows).length >= DOC_TABLE_LIMITS.rows) {
                refuse(fill(strings.doc_table_full, DOC_TABLE_LIMITS.rows).replace('{2}', DOC_TABLE_LIMITS.columns));
                return;
            }

            closeEditor(true);

            var row = newRow();

            Array.prototype.forEach.call(place.row.cells, function (cell) {
                row.appendChild(newCell(cell));
            });

            place.row.parentNode.insertBefore(row, place.row.nextSibling);
            tableChanged(firstIn(shownOf(row.cells)[0]));
        }

        // Insert column: a column right of the caret's, each cell copied
        // from the one beside it; the caret goes to its cell in its row.
        function insertColumn() {
            var place = tablePlace();

            if (!place) {
                return;
            }

            if (uneven(place.table)) {
                refuse(strings.doc_table_merged);
                return;
            }

            if (place.fresh && shownOf(place.row.cells).length >= DOC_TABLE_LIMITS.columns) {
                refuse(fill(strings.doc_table_full, DOC_TABLE_LIMITS.rows).replace('{2}', DOC_TABLE_LIMITS.columns));
                return;
            }

            // The columns a save puts in come before any goes.
            if (!place.fresh && place.row.cells.length >= DOC_TABLE_LIMITS.widest) {
                refuse(fill(strings.doc_table_wide, DOC_TABLE_LIMITS.widest));
                return;
            }

            closeEditor(true);

            var index = Array.prototype.indexOf.call(place.row.cells, place.cell);
            var target = null;

            Array.prototype.forEach.call(place.table.rows, function (row) {
                var source = row.cells[index];
                var cell = newCell(source);

                row.insertBefore(cell, source.nextSibling);

                if (row === place.row) {
                    target = cell;
                }
            });

            tableChanged(firstIn(target));
        }

        // Delete row: the caret's row goes; the caret to the row after it,
        // else the one before. The last row takes the table with it.
        function deleteRow() {
            var place = tablePlace();

            if (!place) {
                return;
            }

            var rows = shownOf(place.table.rows);

            if (rows.length < 2) {
                deleteTable();
                return;
            }

            var put = place.fresh || place.row.classList.contains('office-new-row');

            if (!place.fresh && uneven(place.table)) {
                refuse(strings.doc_table_merged);
                return;
            }

            if (!put && !removable(place.row)) {
                refuse(strings.doc_table_keep);
                return;
            }

            closeEditor(true);

            var at = rows.indexOf(place.row);
            var next = rows[at + 1] || rows[at - 1];
            var index = Array.prototype.indexOf.call(place.row.cells, place.cell);

            if (put) {
                removePart(place.row);
            } else {
                hidePart(place.row);
            }

            var cell = next.cells[index] && !next.cells[index].hidden ? next.cells[index] : shownOf(next.cells)[0];

            tableChanged(firstIn(cell));
        }

        // Delete column: the caret's column goes, in every row; the caret
        // to the cell after it, else the one before. The last column takes
        // the table with it.
        function deleteColumn() {
            var place = tablePlace();

            if (!place) {
                return;
            }

            var cells = shownOf(place.row.cells);

            if (cells.length < 2) {
                deleteTable();
                return;
            }

            if (uneven(place.table)) {
                refuse(strings.doc_table_merged);
                return;
            }

            var index = Array.prototype.indexOf.call(place.row.cells, place.cell);
            var reference = place.fresh ? null : Array.prototype.filter.call(place.table.rows, function (row) {
                return !row.classList.contains('office-new-row');
            })[0];

            // A column of the file is hidden in every row, new rows too: a
            // save puts those rows in before the column goes.
            var ofFile = !!reference && !reference.cells[index].classList.contains('office-new-cell');
            var column = Array.prototype.map.call(place.table.rows, function (row) {
                return row.cells[index];
            });

            if (ofFile && !column.every(function (cell) { return cell.classList.contains('office-new-cell') || removable(cell); })) {
                refuse(strings.doc_table_keep);
                return;
            }

            closeEditor(true);

            var at = cells.indexOf(place.cell);
            var next = cells[at + 1] || cells[at - 1];

            column.forEach(function (cell) {
                if (ofFile) {
                    hidePart(cell);
                } else {
                    removePart(cell);
                }
            });

            tableChanged(firstIn(next));
        }

        // Delete table: a new one goes, one of the file is hidden till the
        // save; the caret to the paragraph before it, else after it.
        function deleteTable() {
            var place = tablePlace();

            if (!place) {
                return;
            }

            if (!place.fresh && !removable(place.table)) {
                refuse(strings.doc_table_keep);
                return;
            }

            closeEditor(true);

            var table = place.table;
            var before = null;
            var after = null;

            nodes.forEach(function (node) {
                if (node.hidden || table.contains(node)) {
                    return;
                }

                if (table.compareDocumentPosition(node) & 2) {
                    before = node;
                } else if (!after) {
                    after = node;
                }
            });

            if (place.fresh) {
                removePart(table);
            } else {
                hidePart(table);
            }

            tableChanged(before || after, !!before);
        }

        // Tab in a table goes to the next cell (Shift+Tab the one before);
        // Tab in the last cell puts a row in after it. false when the
        // key goes on as outside a table (Shift+Tab in the first cell).
        function tabCell(node, back) {
            var cell = cellOf(node);
            var table = cell ? cell.parentNode.closest('table') : null;

            if (!table) {
                return false;
            }

            var cells = [];

            shownOf(table.rows).forEach(function (row) {
                cells = cells.concat(shownOf(row.cells));
            });

            var next = cells[cells.indexOf(cell) + (back ? -1 : 1)];

            if (next) {
                if (firstIn(next, back)) {
                    enter(firstIn(next, back), back ? 'end' : 0);
                }
                return true;
            }

            // Where no row can be put in, Tab leaves the table as it leaves
            // a paragraph.
            if (back || uneven(table) || (freshTable(table) && cells.length && shownOf(table.rows).length >= DOC_TABLE_LIMITS.rows)) {
                return false;
            }

            oneChange(insertRow);

            return true;
        }

        // The size grid under the bar's Table button: a new table of the
        // rows and columns pointed at goes after the paragraph open or
        // picked when the grid opened.
        var tableWindow = null;

        function closeTableWindow(back) {
            if (!tableWindow) {
                return;
            }

            var target = tableWindow.target;
            var at = tableWindow.at;

            document.removeEventListener('mousedown', tableWindow.outside, true);
            tableWindow.node.remove();
            tableWindow = null;

            if (back && target && doc.contains(target)) {
                enter(target, at);
            }

            drawBar();
        }

        // Why no table can go after a paragraph, '' when one can.
        function tableRefusal(node) {
            if (!node) {
                return strings.doc_pick || '';
            }

            var position = node.getAttribute('data-p');

            if (node.closest('.office-new-cell')) {
                return strings.doc_table_inside || '';
            }

            if (own(deletes, position)) {
                return strings.doc_deleted || '';
            }

            return own(shut, isNew(position) ? node.getAttribute('data-after') : position) ? (strings.doc_shut || '') : '';
        }

        function openTableWindow() {
            closeBarMenu(false);
            closeLinkWindow(false);
            closeTableWindow(false);

            var target = blockNode();
            var why = tableRefusal(target);

            if (why) {
                refuse(why);
                return;
            }

            var anchor = controls.table;
            var node = el('div', 'pg-card office-link-window office-table-window');
            var caption = el('div', 'office-table-size', strings.doc_table_choose || '');
            var grid = el('div', 'office-table-grid');
            var cells = [];
            var size = { rows: 1, columns: 1 };

            node.setAttribute('role', 'dialog');
            node.setAttribute('aria-label', strings.doc_table || '');
            caption.setAttribute('aria-live', 'polite');

            function label(rows, columns) {
                return fill(strings.doc_table_size, columns).replace('{2}', rows);
            }

            // The grid shows the rows pointed at and one more, at least
            // eight, every column.
            function shape(rows) {
                var want = pickRows(rows) * DOC_TABLE_LIMITS.columns;

                while (cells.length > want) {
                    cells.pop().remove();
                }

                while (cells.length < want) {
                    (function (index) {
                        var cell = el('button', 'office-table-cell');
                        var rowsAt = Math.floor(index / DOC_TABLE_LIMITS.columns) + 1;
                        var columnsAt = (index % DOC_TABLE_LIMITS.columns) + 1;

                        cell.type = 'button';
                        cell.tabIndex = -1;
                        cell.setAttribute('aria-label', label(rowsAt, columnsAt));
                        cell.addEventListener('mouseenter', function () {
                            mark(rowsAt, columnsAt, false);
                        });
                        cell.addEventListener('focus', function () {
                            mark(rowsAt, columnsAt, false);
                        });
                        cell.addEventListener('click', function () {
                            closeTableWindow(false);
                            oneChange(function () {
                                insertTable(target, rowsAt, columnsAt);
                            });
                        });
                        cells.push(cell);
                        grid.appendChild(cell);
                    })(cells.length);
                }
            }

            function mark(rows, columns, focus) {
                size = { rows: rows, columns: columns };
                shape(rows);

                cells.forEach(function (cell, index) {
                    var on = (Math.floor(index / DOC_TABLE_LIMITS.columns) < rows) && ((index % DOC_TABLE_LIMITS.columns) < columns);

                    cell.classList.toggle('is-on', on);
                    cell.tabIndex = (index === (rows - 1) * DOC_TABLE_LIMITS.columns + columns - 1) ? 0 : -1;
                });

                caption.textContent = label(rows, columns);

                if (focus) {
                    cells[(rows - 1) * DOC_TABLE_LIMITS.columns + columns - 1].focus({ preventScroll: true });
                }
            }

            node.addEventListener('keydown', function (event) {
                event.stopPropagation();

                var step = { ArrowRight: [0, 1], ArrowLeft: [0, -1], ArrowDown: [1, 0], ArrowUp: [-1, 0] }[event.key];

                if (event.key === 'Escape' || event.key === 'Tab') {
                    event.preventDefault();
                    closeTableWindow(true);
                } else if (step) {
                    event.preventDefault();
                    mark(Math.min(DOC_TABLE_LIMITS.rows, Math.max(1, size.rows + step[0])), Math.min(DOC_TABLE_LIMITS.columns, Math.max(1, size.columns + step[1])), true);
                }
            });

            node.appendChild(caption);
            node.appendChild(grid);
            bar.appendChild(node);
            shape(1);
            mark(1, 1, false);
            caption.textContent = strings.doc_table_choose || '';
            node.style.left = Math.max(0, anchor.offsetLeft) + 'px';
            node.style.top = (anchor.offsetTop + anchor.offsetHeight + 4) + 'px';

            // Near the right edge (a narrow screen) the grid stays in the
            // view.
            if (node.offsetLeft + node.offsetWidth > bar.clientWidth) {
                node.style.left = Math.max(0, bar.clientWidth - node.offsetWidth) + 'px';
            }

            tableWindow = {
                node: node,
                target: target,
                at: (shown.editor && shown.editor.node === target && shown.editor.range) ? shown.editor.range[0] : 'end',
                outside: function (event) {
                    if (tableWindow && !tableWindow.node.contains(event.target) && event.target !== anchor && !anchor.contains(event.target)) {
                        closeTableWindow(false);
                    }
                }
            };

            document.addEventListener('mousedown', tableWindow.outside, true);
            anchor.setAttribute('aria-expanded', 'true');
            cells[0].focus({ preventScroll: true });
        }

        // A new table of rows by columns after a paragraph (after its
        // list, for a list item), each cell one plain empty paragraph; a
        // paragraph follows it, as the writer puts one after a table none
        // follows. The caret goes to its first cell.
        function insertTable(node, rows, columns) {
            var why = tableRefusal(node);

            if (why || !doc.contains(node)) {
                refuse(why);
                return;
            }

            closeEditor(true);

            var position = node.getAttribute('data-p');
            var anchor = isNew(position) ? node.getAttribute('data-after') : position;
            var table = el('table', 'office-table office-added-table');
            var body = el('tbody');
            var place = (node.tagName === 'LI' && node.parentNode && /^(UL|OL)$/.test(node.parentNode.tagName)) ? node.parentNode : node;
            var row;
            var column;

            table.setAttribute('data-after', anchor);
            nameOf(table, 'data-t', 'k');

            for (row = 0; row < rows; row++) {
                var line = newRow();

                for (column = 0; column < columns; column++) {
                    line.appendChild(newCell(null));
                }

                body.appendChild(line);
            }

            table.appendChild(body);
            place.parentNode.insertBefore(table, place.nextSibling);

            var next = table.nextElementSibling;

            if (!next || next.tagName === 'TABLE' || !(next.hasAttribute('data-p') || next.querySelector('[data-p]'))) {
                addAfter(node, '', [], 'normal', table);
            }

            tableChanged(firstIn(table.rows[0].cells[0]));
        }

        // Pictures put in on the page: a .office-added-picture after a
        // paragraph (after its list, for a list item), named by data-t; its
        // bytes in base64 and its description wait here for the save.
        var pictures = {};
        var pictureWindow = null;

        // Why no picture can go after a paragraph, '' when one can: as a
        // table, never in a new table, row or column.
        function pictureSpot(node) {
            return (node && node.closest('.office-new-cell')) ? (strings.doc_picture_inside || '') : tableRefusal(node);
        }

        function closePictureWindow(back) {
            if (!pictureWindow) {
                return;
            }

            var target = pictureWindow.target;

            document.removeEventListener('mousedown', pictureWindow.outside, true);
            pictureWindow.node.remove();
            pictureWindow = null;
            controls.picture.setAttribute('aria-expanded', 'false');

            if (back && target && doc.contains(target)) {
                enter(target, 'end');
            }
        }

        // The bar's Picture button: the host's own picker when it has one
        // (the file window), else a window of the pictures it lists (a
        // channel's) with Upload. The picture goes after the paragraph open
        // or picked when it was pressed.
        function openPicture() {
            closeBarMenu(false);
            closeLinkWindow(false);
            closeTableWindow(false);

            if (pictureWindow) {
                closePictureWindow(true);
                return;
            }

            var target = blockNode();
            var why = pictureSpot(target);

            if (why) {
                refuse(why);
                return;
            }

            if (typeof (options || {}).pickPicture === 'function') {
                Promise.resolve(options.pickPicture()).then(function (picked) {
                    if (picked) {
                        fetchPicture(target, picked);
                    } else if (doc.contains(target)) {
                        enter(target, 'end');
                    }
                }, function () {
                    refuse(strings.doc_picture_failed || '');
                });
                return;
            }

            openPictureWindow(target);
        }

        function openPictureWindow(target) {
            var anchor = controls.picture;
            var node = el('div', 'pg-card office-link-window office-picture-window');
            var list = el('div', 'office-picture-list', strings.doc_picture_loading || '');
            var foot = el('div', 'office-link-foot');
            var upload = el('button', 'pg-button pg-button-sm');
            var cancel = el('button', 'pg-button pg-button-ghost pg-button-sm', strings.doc_link_cancel || '');
            var input = el('input', 'd-none');

            node.setAttribute('role', 'dialog');
            node.setAttribute('aria-label', strings.doc_picture || '');
            upload.type = 'button';
            cancel.type = 'button';
            input.type = 'file';
            input.accept = 'image/png,image/jpeg,image/gif';
            upload.appendChild(el('span', 'bi bi-upload'));
            upload.appendChild(el('span', 'pg-btn-label', strings.doc_picture_upload || ''));
            upload.firstChild.setAttribute('aria-hidden', 'true');
            upload.addEventListener('click', function () {
                input.click();
            });
            input.addEventListener('change', function () {
                var file = input.files && input.files[0];

                if (file) {
                    closePictureWindow(false);
                    takePicture(target, file, file.name);
                }
            });
            cancel.addEventListener('click', function () {
                closePictureWindow(true);
            });
            node.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    closePictureWindow(true);
                }
            });

            foot.appendChild(upload);
            foot.appendChild(el('span', 'office-link-gap'));
            foot.appendChild(cancel);
            node.appendChild(el('div', 'office-table-size', strings.doc_picture_choose || ''));
            node.appendChild(list);
            node.appendChild(foot);
            node.appendChild(input);
            bar.appendChild(node);
            node.style.left = Math.max(0, Math.min(anchor.offsetLeft, bar.clientWidth - node.offsetWidth)) + 'px';
            node.style.top = (anchor.offsetTop + anchor.offsetHeight + 4) + 'px';

            pictureWindow = {
                node: node,
                target: target,
                outside: function (event) {
                    if (pictureWindow && !pictureWindow.node.contains(event.target) && event.target !== anchor && !anchor.contains(event.target)) {
                        closePictureWindow(false);
                    }
                }
            };

            document.addEventListener('mousedown', pictureWindow.outside, true);
            anchor.setAttribute('aria-expanded', 'true');
            upload.focus({ preventScroll: true });

            Promise.resolve(typeof (options || {}).pictures === 'function' ? options.pictures() : []).then(function (items) {
                if (!pictureWindow || pictureWindow.node !== node) {
                    return;
                }

                list.textContent = (items && items.length) ? '' : (strings.doc_picture_none || '');

                (items || []).forEach(function (item) {
                    var tile = el('button', 'office-picture-tile');
                    var image = el('img');

                    tile.type = 'button';
                    tile.title = item.name || '';
                    tile.setAttribute('aria-label', item.name || '');
                    image.src = item.url;
                    image.alt = '';
                    image.loading = 'lazy';
                    tile.appendChild(image);
                    tile.addEventListener('click', function () {
                        closePictureWindow(false);
                        fetchPicture(target, item);
                    });
                    list.appendChild(tile);
                });
            }, function () {
                list.textContent = strings.doc_picture_failed || '';
            });
        }

        // A picture picked by its address ({url, name}) or given ({blob,
        // name}), read here: only the bytes go into the file.
        function fetchPicture(target, picked) {
            (picked.blob ? Promise.resolve(picked.blob) : fetch(picked.url, { credentials: 'same-origin' }).then(function (answer) {
                return answer.ok ? answer.blob() : Promise.reject(new Error(String(answer.status)));
            })).then(function (blob) {
                takePicture(target, blob, picked.name || '');
            }, function () {
                refuse(strings.doc_picture_failed || '');
            });
        }

        // The picture checked as the writer checks it and drawn after the
        // paragraph at once, one change of Undo.
        function takePicture(target, blob, name) {
            var reader = new FileReader();

            reader.onerror = function () {
                refuse(strings.doc_picture_failed || '');
            };
            reader.onload = function () {
                var bytes = new Uint8Array(reader.result);
                var type = pictureType(bytes);
                var total = Array.prototype.reduce.call(doc.querySelectorAll('.office-added-picture'), function (sum, node) {
                    return sum + pictures[node.getAttribute('data-t')].size;
                }, 0);
                var why = pictureRefusal(type, bytes.length, total) || (doc.contains(target) ? pictureSpot(target) : (strings.doc_pick || ''));

                if (why) {
                    refuse(fill(strings[why] || why, '2 MB').replace('{2}', '8 MB'));
                    return;
                }

                var text = '';

                for (var index = 0; index < bytes.length; index += 0x8000) {
                    text += String.fromCharCode.apply(null, bytes.subarray(index, index + 0x8000));
                }

                oneChange(function () {
                    insertPicture(target, type, window.btoa(text), String(name).replace(/\.[a-z0-9]+$/i, '').replace(/\s+/g, ' ').trim().slice(0, 255), bytes.length);
                });
            };
            reader.readAsArrayBuffer(blob);
        }

        function insertPicture(node, type, data, name, size) {
            closeEditor(true);

            var position = node.getAttribute('data-p');
            var holder = el('div', 'office-added-picture');
            var image = el('img', 'office-img');
            var place = (node.tagName === 'LI' && node.parentNode && /^(UL|OL)$/.test(node.parentNode.tagName)) ? node.parentNode : node;

            holder.setAttribute('data-after', isNew(position) ? node.getAttribute('data-after') : position);
            nameOf(holder, 'data-t', 'g');
            // It takes the alignment of the paragraph it follows.
            holder.style.textAlign = window.getComputedStyle(node).textAlign;
            image.src = 'data:image/' + type + ';base64,' + data;
            image.alt = name;
            holder.appendChild(image);
            pictures[holder.getAttribute('data-t')] = { data: data, name: name, size: size };
            place.parentNode.insertBefore(holder, place.nextSibling);
            tableChanged(node, true);
        }

        // The colour, highlight or size all the selected text has; '' when
        // it has none, null when it differs. With only a caret, the one of
        // the character before it, which typed text takes (carryMarks()).
        function selectionLook(key) {
            var parts = selectedParts() || [];
            var caret = !parts.length && !!shown.editor && !!shown.editor.range && document.activeElement === paper;
            var found;

            if (caret) {
                var at = shown.editor.range[0];

                parts = [{ position: shown.editor.position, start: Math.max(0, at - 1), end: Math.max(1, at) }];
            }

            parts.forEach(function (part) {
                var look = lookOf(part.position, true);

                for (var index = part.start; index < Math.min(part.end, look.want.length); index++) {
                    var set = lookParts(look.want[index]) || {};
                    var value = own(set, key) ? set[key] : '';

                    found = (found === undefined || found === value) ? value : null;
                }
            });

            return found === undefined ? (caret ? '' : null) : found;
        }

        // The bar's menu of colours: "None" first, then the swatches
        // ([[RRGGBB, word], ...], the chosen one ringed). A pick acts on the
        // text selected when the menu opened and puts the caret back on it.
        function closeBarMenu(focus) {
            if (!barMenuOpen) {
                return;
            }

            var opener = barMenuOpen.opener;

            document.removeEventListener('mousedown', barMenuOpen.outside, true);
            barMenuOpen.node.remove();
            barMenuOpen = null;

            if (focus) {
                opener.focus({ preventScroll: true });
            }
        }

        function barMenu(anchor, label, colours, chosen, pick) {
            closeBarMenu(false);
            closeTableWindow(false);

            var parts = selectedParts();
            var node = el('div', 'menu show office-head-menu');
            var buttons = [];
            var none = el('button', 'menu-item pg-link-body-emphasis');

            node.setAttribute('role', 'menu');
            node.setAttribute('aria-label', label || '');

            function choose(value) {
                closeBarMenu(false);
                oneChange(function () {
                    if (parts) {
                        paper.focus({ preventScroll: true });
                    }

                    pick(value, parts);
                });
            }

            none.type = 'button';
            none.tabIndex = -1;
            none.setAttribute('role', 'menuitem');
            none.appendChild(el('span', '', strings.format_none || ''));
            none.addEventListener('click', function () {
                choose('');
            });
            buttons.push(none);
            node.appendChild(none);

            var grid = el('div', 'office-swatches');

            colours.forEach(function (colour) {
                var swatch = el('button', 'office-swatch');
                var name = colour[2] || colour[1];

                swatch.type = 'button';
                swatch.tabIndex = -1;
                swatch.setAttribute('role', 'menuitemradio');
                swatch.setAttribute('aria-checked', (chosen === colour[1]) ? 'true' : 'false');
                swatch.setAttribute('aria-label', name);
                swatch.title = name;
                swatch.style.backgroundColor = '#' + colour[0];
                swatch.classList.toggle('is-chosen', chosen === colour[1]);
                swatch.addEventListener('click', function () {
                    choose(colour[1]);
                });
                buttons.push(swatch);
                grid.appendChild(swatch);
            });

            node.appendChild(grid);

            node.addEventListener('keydown', function (event) {
                event.stopPropagation();

                var at = buttons.indexOf(document.activeElement);

                if (event.key === 'Escape' || event.key === 'Tab') {
                    event.preventDefault();
                    closeBarMenu(true);
                } else if (/^Arrow(Down|Up|Right|Left)$/.test(event.key)) {
                    event.preventDefault();
                    buttons[(at + ((event.key === 'ArrowDown' || event.key === 'ArrowRight') ? 1 : -1) + buttons.length) % buttons.length].focus();
                }
            });

            bar.appendChild(node);
            node.style.left = Math.max(0, anchor.offsetLeft) + 'px';
            node.style.top = (anchor.offsetTop + anchor.offsetHeight) + 'px';

            // A menu opened near the right edge (a narrow screen) stays in
            // the view.
            if (node.offsetLeft + node.offsetWidth > bar.clientWidth) {
                node.style.left = Math.max(0, bar.clientWidth - node.offsetWidth) + 'px';
            }

            barMenuOpen = {
                node: node,
                opener: anchor,
                outside: function (event) {
                    if (barMenuOpen && !barMenuOpen.node.contains(event.target) && event.target !== anchor && !anchor.contains(event.target)) {
                        closeBarMenu(false);
                    }
                }
            };

            document.addEventListener('mousedown', barMenuOpen.outside, true);
            (buttons[0] || node).focus({ preventScroll: true });
        }

        // A copy of the document's changes as they stand, with what is being
        // typed, the paragraphs added (where each stands) and those hidden,
        // and the caret or the paragraph picked: what Undo puts back.
        function capture() {
            var state = {
                changes: changesNow(),
                looks: {},
                wantKinds: copyOf(wantKinds),
                wantAligns: copyOf(wantAligns),
                deletes: copyOf(deletes),
                added: [],
                hidden: [],
                tables: [],
                caret: null
            };
            var range = sheetRange();
            var parts = range ? partsOf(range) : [];

            Object.keys(looks).forEach(function (position) {
                state.looks[position] = lookCopy(looks[position]);
            });

            // Paragraphs, tables and pictures added, in reading order: a
            // table before the paragraphs of its cells.
            Array.prototype.forEach.call(doc.querySelectorAll('.office-added, .office-added-table, .office-added-picture'), function (node) {
                var key = node.getAttribute('data-p') || node.getAttribute('data-t');

                state.added.push({
                    node: node,
                    key: key,
                    parent: node.parentNode,
                    prev: node.previousElementSibling,
                    text: texts[key],
                    marks: marks[key],
                    kind: kinds[key],
                    drawn: drawn[key]
                });
            });

            nodes.forEach(function (node) {
                if (node.hidden) {
                    state.hidden.push(node);
                }
            });

            // Each table's rows and their cells as they stand, hidden or
            // not.
            Array.prototype.forEach.call(paper.querySelectorAll('table'), function (table) {
                state.tables.push({
                    table: table,
                    hidden: table.hidden,
                    rows: Array.prototype.map.call(table.rows, function (row) {
                        return {
                            row: row,
                            parent: row.parentNode,
                            hidden: row.hidden,
                            cells: Array.prototype.map.call(row.cells, function (cell) {
                                return { cell: cell, hidden: cell.hidden };
                            })
                        };
                    })
                });
            });

            if (parts.length) {
                state.caret = { node: parts[0].node, start: parts[0].start, last: parts[parts.length - 1].node, end: parts[parts.length - 1].end };
            } else if (shown.node) {
                state.caret = { node: shown.node };
            }

            return state;
        }

        function copyOf(map) {
            var copy = {};

            Object.keys(map).forEach(function (key) {
                copy[key] = map[key];
            });

            return copy;
        }

        function lookCopy(look) {
            return { text: look.text, base: look.base.slice(), want: look.want.slice() };
        }

        // What a state means for the file: two states that read the same
        // save the same. A look counts only where it changes a format.
        function meaning(state) {
            var shaped = {};

            Object.keys(state.looks).forEach(function (position) {
                var look = state.looks[position];
                var ranges = formatRanges(look.base, look.want);

                if (ranges.length) {
                    shaped[position] = [look.text, ranges];
                }
            });

            return JSON.stringify([state.changes, shaped, state.wantKinds, state.wantAligns, state.deletes, state.added.map(function (item) {
                return item.key;
            }), state.hidden.map(function (node) {
                return node.getAttribute('data-p');
            }), state.tables.map(function (item) {
                return (item.hidden ? '-' : '') + item.rows.map(function (line) {
                    return (line.hidden ? '-' : '') + line.row.getAttribute('data-r') + ':' + line.cells.map(function (cell) {
                        return (cell.hidden ? '-' : '') + cell.cell.getAttribute('data-c');
                    }).join(',');
                }).join(';');
            })]);
        }

        // Runs one change of the document: the state before it goes on the
        // list when it changed anything.
        function oneChange(change) {
            var before = capture();

            change();
            run = null;

            if (meaning(capture()) !== meaning(before)) {
                steps.record(before);
            }

            drawBar();
        }

        // Typing the browser does inside one paragraph: a new change unless
        // it goes on where the last letter went, the same way.
        function typedRun(node, type) {
            var kind = /^insert/.test(type) ? 'insert' : 'delete';
            var at = selectionIn(node);

            if (!run || run.node !== node || run.kind !== kind || !at || at[0] !== at[1] || run.at !== at[0]) {
                steps.record(capture());
                run = { node: node, kind: kind, at: -1 };
            }
        }

        // Puts the document back as a state of the list left it: the
        // paragraphs added then stand again where they stood, the others
        // go; each paragraph touched is drawn again; the caret goes back.
        function restore(state) {
            var touched = {};
            var caret = state.caret;

            if (shown.editor) {
                touched[shown.editor.position] = true;
            }

            shown.editor = null;
            shown.node = null;
            shown.last = null;
            shown.note = '';
            run = null;

            [changes, looks, wantKinds, wantAligns, deletes].forEach(function (map) {
                Object.keys(map).forEach(function (key) {
                    touched[key] = true;
                });
            });

            Array.prototype.forEach.call(doc.querySelectorAll('.office-added, .office-added-table, .office-added-picture'), function (node) {
                var key = node.getAttribute('data-p');

                node.parentNode.removeChild(node);
                [texts, marks, kinds, drawn].forEach(function (map) {
                    delete map[key];
                });
            });

            // In reading order, so the paragraph each follows stands already;
            // a table stands before the paragraphs of its cells go back in.
            state.added.forEach(function (item) {
                if (item.prev && item.prev.parentNode === item.parent) {
                    item.parent.insertBefore(item.node, item.prev.nextSibling);
                } else {
                    item.parent.insertBefore(item.node, item.parent.firstChild);
                }

                if (item.node.hasAttribute('data-p')) {
                    texts[item.key] = item.text;
                    marks[item.key] = item.marks;
                    kinds[item.key] = item.kind;
                    drawn[item.key] = item.drawn;
                }
            });

            // Each table's rows and cells as they stood: those put in since
            // go, those deleted since come back.
            state.tables.forEach(function (item) {
                Array.prototype.slice.call(item.table.rows).forEach(function (row) {
                    row.parentNode.removeChild(row);
                });

                item.rows.forEach(function (line) {
                    line.parent.appendChild(line.row);
                    line.row.hidden = line.hidden;

                    Array.prototype.slice.call(line.row.cells).forEach(function (cell) {
                        line.row.removeChild(cell);
                    });

                    line.cells.forEach(function (cell) {
                        cell.cell.hidden = cell.hidden;
                        line.row.appendChild(cell.cell);
                    });
                });

                item.table.hidden = item.hidden;
            });

            changes = copyOf(state.changes);
            wantKinds = copyOf(state.wantKinds);
            wantAligns = copyOf(state.wantAligns);
            deletes = copyOf(state.deletes);
            looks = {};

            Object.keys(state.looks).forEach(function (position) {
                looks[position] = lookCopy(state.looks[position]);
            });

            [changes, looks, wantKinds, wantAligns, deletes].forEach(function (map) {
                Object.keys(map).forEach(function (key) {
                    touched[key] = true;
                });
            });

            nodes = Array.prototype.slice.call(doc.querySelectorAll('[data-p]'));

            nodes.forEach(function (node) {
                var key = node.getAttribute('data-p');
                var hide = state.hidden.indexOf(node) >= 0;

                node.classList.remove('office-selected');

                if (node.hidden !== hide) {
                    node.hidden = hide;
                    touched[key] = true;
                }

                if (own(touched, key) || isNew(key)) {
                    redraw(node);
                }
            });

            if (caret && nodes.indexOf(caret.node) >= 0 && !caret.node.hidden) {
                if (own(caret, 'start') && caret.node.classList.contains('office-typing')) {
                    enter(caret.node, caret.start);

                    if (caret.last !== caret.node || caret.end !== caret.start) {
                        if (nodes.indexOf(caret.last) >= 0 && !caret.last.hidden) {
                            placeSelection(caret.node, caret.start, caret.end, caret.last);
                            follow();
                        }
                    }
                } else {
                    enter(caret.node, 'end');
                }
            }

            drawBar();
            notify();
        }

        // Undo (back) or Redo: one change of the list a press.
        function undoStep(back) {
            var now = capture();
            var state = back ? steps.undo(now) : steps.redo(now);

            if (state) {
                restore(state);
            }

            drawBar();
        }

        // The bar's buttons pressed when all of the selected text has theirs;
        // the kind choice on the picked paragraph's kind.
        function drawBar() {
            if (!bar) {
                return;
            }

            if (stepControls) {
                stepControls.undo.disabled = !steps.canUndo();
                stepControls.redo.disabled = !steps.canRedo();
            }

            if (kindChoice) {
                var position = kindPosition();
                var kind = position === null ? '' : (own(wantKinds, position) ? wantKinds[position] : kinds[position]);

                kindChoice.other.hidden = kind !== 'other';
                kindChoice.select.disabled = position === null;
                kindChoice.select.value = kind === '' ? 'normal' : kind;
            }

            if (blockControls) {
                var block = blockNode();
                var at = block ? block.getAttribute('data-p') : null;
                var gone = at !== null && own(deletes, at);
                var label = gone ? strings.doc_restore : strings.doc_delete;

                blockControls.remove.disabled = !block;
                blockControls.remove.setAttribute('aria-pressed', gone ? 'true' : 'false');
                blockControls.remove.classList.toggle('is-active', gone);
                blockControls.remove.setAttribute('aria-label', label || '');
                blockControls.remove.title = label || '';
            }

            var parts = selectedParts() || [];

            DOC_FORMATS.forEach(function (format) {
                var on = parts.length > 0;

                parts.forEach(function (part) {
                    var look = lookOf(part.position, true);

                    for (var index = part.start; on && index < Math.min(part.end, look.want.length); index++) {
                        on = !!(look.want[index] & format[1]);
                    }
                });

                controls[format[0]].setAttribute('aria-pressed', on ? 'true' : 'false');
                controls[format[0]].classList.toggle('is-active', on);
            });

            // The table controls show while the caret is in a table.
            if (controls.table) {
                controls.table.disabled = !blockNode();
                controls.table.setAttribute('aria-expanded', tableWindow ? 'true' : 'false');
                controls.tableTools.hidden = !tablePlace();
            }

            // Link pressed when the text selected, or the caret, is in one.
            if (controls.link) {
                var linked = linkTarget();
                var inLink = !!linked && !!linked.address;

                controls.link.setAttribute('aria-pressed', inLink ? 'true' : 'false');
                controls.link.classList.toggle('is-active', inLink);
                controls.link.setAttribute('aria-expanded', linkWindow ? 'true' : 'false');
            }

            // The colours as a bar under their glyph, the size as chosen; a
            // selection of mixed sizes shows none.
            if (controls.color) {
                var colour = selectionLook('c');
                var highlight = selectionLook('h');
                var size = selectionLook('s');

                controls.color.stripe.style.backgroundColor = colour ? '#' + colour : '';
                controls.highlight.stripe.style.backgroundColor = (highlight && own(DOC_HIGHLIGHT_DRAWN, highlight)) ? '#' + DOC_HIGHLIGHT_DRAWN[highlight] : '';

                if (size && FONT_SIZES.indexOf(Number(size)) < 0) {
                    controls.size.other.textContent = String(size);
                    controls.size.other.hidden = false;
                    controls.size.choice.value = 'other';
                } else {
                    controls.size.other.hidden = true;
                    controls.size.choice.value = (size === null) ? 'mixed' : String(size);
                }
            }

            if (controls.align) {
                var positions = alignPositions();
                var shownAlign = positions.length ? alignNow(positions[0]) : null;

                positions.forEach(function (position) {
                    shownAlign = (alignNow(position) === shownAlign) ? shownAlign : null;
                });

                Object.keys(controls.align).forEach(function (align) {
                    controls.align[align].disabled = !positions.length;
                    controls.align[align].setAttribute('aria-pressed', (align === shownAlign) ? 'true' : 'false');
                    controls.align[align].classList.toggle('is-active', align === shownAlign);
                });
            }
        }

        function paragraphOf(target) {
            var element = (target && target.nodeType === 3) ? target.parentNode : target;
            var node = (element && element.closest) ? element.closest('[data-p]') : null;

            return (node && doc.contains(node)) ? node : null;
        }

        function select(node, scroll) {
            if (!node) {
                return;
            }

            var typing = node.classList.contains('office-typing');

            if (shown.node && shown.node !== node) {
                shown.node.classList.remove('office-selected');

                if (!shown.node.classList.contains('office-typing')) {
                    shown.node.tabIndex = -1;
                }
            }

            if (shown.last && !shown.last.some(function (part) { return part.node === node; })) {
                shown.last = null;
            }

            shown.node = node;
            node.classList.add('office-selected');

            // A paragraph typed in holds the caret of the sheet; one only
            // picked takes the focus itself.
            if (!typing) {
                node.tabIndex = 0;

                if (document.activeElement !== node) {
                    node.focus({ preventScroll: true });
                }
            }

            if (scroll !== false && node.scrollIntoView) {
                node.scrollIntoView({ block: 'nearest' });
            }

            // Why a picked paragraph stays as it is, for screens without a
            // pointer to show its title.
            shown.note = own(deletes, node.getAttribute('data-p')) ? (strings.doc_deleted || '') : (node.classList.contains('office-locked') ? (node.title || '') : '');
            drawStatus();
            drawBar();
        }

        // The marked paragraph before or after one, in the order they read;
        // one joined into another and hidden till the save is passed.
        function beside(node, step) {
            var index = nodes.indexOf(node);

            if (index < 0) {
                return null;
            }

            index += step;

            while (nodes[index] && nodes[index].hidden) {
                index += step;
            }

            return nodes[index] || null;
        }

        // Whether a paragraph is typed in where it stands: one that may
        // change and is not to be deleted. The sheet is the one place typed
        // in, so a selection and the arrows cross paragraphs; a paragraph
        // that is not typed in is shut inside it and picked as a whole. A
        // drawn tab is one character the caret passes whole.
        function setTyping(node) {
            var on = node.classList.contains('office-para') && !own(deletes, node.getAttribute('data-p'));

            if (on) {
                node.removeAttribute('contenteditable');
                node.removeAttribute('tabindex');
            } else {
                node.contentEditable = 'false';
                node.tabIndex = (node === shown.node) ? 0 : -1;
            }

            node.classList.toggle('office-typing', on);

            Array.prototype.forEach.call(node.querySelectorAll('.office-tabchar'), function (tab) {
                tab.setAttribute('contenteditable', 'false');
            });
        }

        // Draws a paragraph as it stands: its new text, or the server's
        // drawing when it has none.
        function redraw(node) {
            var position = node.getAttribute('data-p');

            // The server's drawing, to put back when a change is undone.
            if (!own(drawn, position) && !typedIn(position)) {
                drawn[position] = node.innerHTML;
            }

            // A changed text keeps the bold, italic and underline it was
            // typed with, carried over as the server will write them.
            var look = lookOf(position, own(changes, position) || typedIn(position));
            var shaped = formatted(position);

            if (own(changes, position) || shaped || typedIn(position)) {
                drawText(node, textNow(position), look ? look.want : null);
            } else if (own(drawn, position)) {
                node.innerHTML = drawn[position];
            }

            setTyping(node);

            // A kind asked for shows as a look of the paragraph until the
            // save draws it again as what it has become.
            if (own(wantKinds, position)) {
                node.setAttribute('data-kind', wantKinds[position]);
            } else {
                node.removeAttribute('data-kind');
            }

            drawAlign(node);
            node.classList.toggle('office-edited', own(changes, position) || shaped || own(wantKinds, position) || own(wantAligns, position) || isNew(position));
            node.classList.toggle('office-deleted', own(deletes, position));
            node.setAttribute('aria-disabled', own(deletes, position) ? 'true' : 'false');
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

        // Leaves the paragraph being typed in: what was typed is kept, or
        // undone when save is false, and the paragraph drawn as it stands.
        function closeEditor(save) {
            var editor = shown.editor;

            if (!editor) {
                return;
            }

            shown.editor = null;

            if (save && editor.typed) {
                keep(editor.node, paragraphText(editor.node));
                return;
            }

            redraw(editor.node);
            notify();
        }

        // Starts typing in a paragraph that may change, where the caret
        // stands. An empty one is drawn holding a space to keep its line;
        // it is typed into holding only the line's end.
        function openEditor(node) {
            var position = node ? node.getAttribute('data-p') : null;

            if (!node || !node.classList.contains('office-typing') || (shown.editor && shown.editor.node === node)) {
                return;
            }

            closeEditor(true);

            if (!own(drawn, position)) {
                drawn[position] = node.innerHTML;
            }

            shown.editor = { node: node, position: position, typed: false, range: selectionIn(node) };
            shown.opened = true;

            if (textNow(position) === '' && !(node.childNodes.length === 1 && node.firstChild.nodeName === 'BR')) {
                node.textContent = '';
                node.appendChild(document.createElement('br'));

                if (document.activeElement === paper) {
                    placeSelection(node, 0, 0);
                }
            }

            drawBar();
        }

        // Which way the caret was last sent by an arrow key, so that a caret
        // the browser left between paragraphs goes on that way.
        var travel = 0;

        // Follows the caret of the sheet: the paragraph it stands in is the
        // one typed in. A caret left outside every paragraph typed in (on a
        // table's edge, a picture, a locked paragraph) goes on to the next
        // one that is, the way it was going. A selection over several
        // paragraphs leaves the one typed in as it is.
        var following = false;

        function follow() {
            var range = sheetRange();

            if (!range || document.activeElement !== paper || following) {
                return;
            }

            var from = paragraphOf(range.startContainer);
            var to = paragraphOf(range.endContainer);

            if (from && from === to && from.classList.contains('office-typing')) {
                if (!shown.editor || shown.editor.node !== from) {
                    select(from, false);
                    openEditor(from);
                }

                shown.editor.range = selectionIn(from);
                drawBar();
                return;
            }

            if (!range.collapsed) {
                drawBar();
                return;
            }

            var typable = nodes.filter(function (node) {
                return !node.hidden && node.classList.contains('office-typing');
            });
            var target = null;
            var index;

            if (travel < 0) {
                for (index = typable.length - 1; index >= 0 && !target; index--) {
                    target = range.comparePoint(typable[index], 0) < 0 ? typable[index] : null;
                }
            } else {
                for (index = 0; index < typable.length && !target; index++) {
                    target = range.comparePoint(typable[index], typable[index].childNodes.length) > 0 ? typable[index] : null;
                }
            }

            target = target || (travel < 0 ? typable[0] : typable[typable.length - 1]);

            if (target) {
                following = true;
                enter(target, range.comparePoint(target, 0) < 0 ? 'end' : 0);
                following = false;
            }
        }

        // Puts the caret into a paragraph at a point of its text ('end' for
        // its end); one that may not be typed in is only picked.
        function enter(node, at) {
            if (!node) {
                return;
            }

            if (!node.classList.contains('office-typing')) {
                closeEditor(true);
                select(node);
                return;
            }

            if (document.activeElement !== paper) {
                paper.focus({ preventScroll: true });
            }

            select(node, false);
            openEditor(node);

            var length = textNow(node.getAttribute('data-p')).length;
            var offset = (at === 'end') ? length : Math.min(at, length);

            placeSelection(node, offset, offset);
            travel = 0;
            follow();

            if (node.scrollIntoView) {
                node.scrollIntoView({ block: 'nearest' });
            }
        }

        // Esc: what was typed into the paragraph since the caret came into
        // it is undone; the caret stays, at its end.
        function undoTyping(node) {
            closeEditor(false);
            enter(node, 'end');
        }

        // Text typed where the caret stands, as the browser types it.
        function typeText(text) {
            if (text === '' || (document.execCommand && document.execCommand('insertText', false, text))) {
                return;
            }

            var range = sheetRange();

            if (!range || !shown.editor) {
                return;
            }

            var piece = document.createTextNode(text);
            var selection = window.getSelection();

            range.deleteContents();
            range.insertNode(piece);
            range.setStartAfter(piece);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
            shown.editor.typed = true;
            notify();
        }

        Array.prototype.forEach.call(doc.querySelectorAll('[data-p]'), function (node) {
            var position = node.getAttribute('data-p');
            var lock = node.getAttribute('data-lock') || (own(texts, position) ? '' : 'other');

            nodes.push(node);

            if (lock) {
                node.classList.add('office-locked');
                node.setAttribute('aria-readonly', 'true');
                node.title = paragraphTitle(lock, strings);
                setTyping(node);
                return;
            }

            node.classList.add('office-para');
            setTyping(node);

            // A link shows its address; a click opens the link window.
            Array.prototype.forEach.call(node.querySelectorAll('a[href]'), function (link) {
                link.title = link.getAttribute('href') || '';
            });
        });

        Array.prototype.forEach.call(paper.querySelectorAll('table'), function (table) {
            Array.prototype.forEach.call(table.rows, function (row) {
                nameOf(row, 'data-r', 'f');

                Array.prototype.forEach.call(row.cells, function (cell) {
                    nameOf(cell, 'data-c', 'f');
                });
            });
        });

        // The sheet is typed in when a paragraph of it may change; what is
        // not a paragraph (a picture, a note) is shut. It is the Tab key's
        // one stop; the arrows and Tab go on from there.
        if (nodes.some(function (node) { return node.classList.contains('office-typing'); })) {
            paper.contentEditable = typeMode;
            paper.classList.add('office-sheet-typing');
            paper.setAttribute('role', 'textbox');
            paper.setAttribute('aria-multiline', 'true');
            paper.setAttribute('aria-label', strings.doc || '');
            doc.classList.add('office-doc-edit');

            Array.prototype.forEach.call(paper.children, function (child) {
                if (!child.hasAttribute('data-p') && !child.querySelector('[data-p]')) {
                    child.contentEditable = 'false';
                }
            });
        } else if (nodes.length) {
            nodes[0].tabIndex = 0;
            doc.classList.add('office-doc-edit');
        } else {
            doc.tabIndex = 0;
        }

        // The sheet reached with Tab or a click follows its caret; a
        // paragraph that is only picked is picked.
        doc.addEventListener('focusin', function (event) {
            var node = event.target;

            if (node === paper) {
                follow();
                return;
            }

            if (!node.hasAttribute || !node.hasAttribute('data-p') || nodes.indexOf(node) === -1) {
                return;
            }

            closeEditor(true);

            if (node !== shown.node) {
                select(node, false);
            }
        });

        // Leaving the sheet keeps what was typed, and what was selected for
        // the bar; the window losing the focus does not leave it.
        doc.addEventListener('focusout', function (event) {
            if (event.target !== paper || (event.relatedTarget && paper.contains(event.relatedTarget))) {
                return;
            }

            if (!event.relatedTarget && document.hasFocus && !document.hasFocus()) {
                return;
            }

            var range = sheetRange();

            shown.last = (range && !range.collapsed) ? partsOf(range) : null;
            closeEditor(true);
        });

        // A click on the sheet below the last paragraph puts the caret at
        // the end of the last one that may change, as on a page.
        doc.addEventListener('mousedown', function (event) {
            if (event.target !== paper || event.button !== 0) {
                return;
            }

            var typable = nodes.filter(function (node) {
                return !node.hidden && node.classList.contains('office-typing');
            });
            var last = typable[typable.length - 1];

            if (last && event.clientY > last.getBoundingClientRect().bottom) {
                event.preventDefault();
                enter(last, 'end');
            }
        });

        // A click on a link in a paragraph that can be changed places the
        // caret and opens the link window on that link; the address is
        // never opened from the page.
        doc.addEventListener('click', function (event) {
            var node = paragraphOf(event.target);
            var link = event.target.closest ? event.target.closest('a[href], .office-link') : null;

            if (!link || !node || node.classList.contains('office-locked')) {
                return;
            }

            event.preventDefault();

            if (controls.link && node.classList.contains('office-typing') && !own(deletes, node.getAttribute('data-p'))) {
                follow();
                openLinkWindow();
            }
        });

        // Typed text is read as it changes.
        doc.addEventListener('input', function () {
            follow();

            if (run && shown.editor && run.node === shown.editor.node) {
                var at = selectionIn(run.node);

                run.at = (at && at[0] === at[1]) ? at[0] : -1;
            }

            if (shown.editor) {
                shown.editor.typed = true;
                notify();
                drawBar();
            }
        });

        // What the sheet takes: plain text typed inside one paragraph.
        // Formatting goes through the bar; a drop is not taken. Enter cuts
        // the paragraph, Backspace at its start and Delete at its end join
        // it to the one beside, and what is typed over a selection of
        // several paragraphs takes them out first: the editor does each, so
        // the browser never joins or splits a paragraph itself.
        doc.addEventListener('beforeinput', function (event) {
            var type = event.inputType || '';
            var range = sheetRange();

            // The browser's own undo and redo (its menu) work on the
            // document's list instead.
            if (/^history(Undo|Redo)$/.test(type) && stepControls && paper.contains(event.target)) {
                event.preventDefault();
                undoStep(type === 'historyUndo');
                return;
            }

            if (!range || /^history/.test(type)) {
                return;
            }

            if (/^format/.test(type) || type === 'insertFromDrop' || type === 'deleteByDrag') {
                event.preventDefault();
                return;
            }

            var parts = partsOf(range);
            var from = paragraphOf(range.startContainer);
            var to = paragraphOf(range.endContainer);
            var inside = !!from && from === to && from.classList.contains('office-typing');

            if (type === 'insertParagraph') {
                event.preventDefault();

                if (inside || parts.length > 1) {
                    oneChange(splitParagraph);
                }
                return;
            }

            if (!inside) {
                // Composed text cannot be stopped; anything else outside one
                // paragraph is done here or not at all.
                if (event.cancelable) {
                    event.preventDefault();
                }

                oneChange(function () {
                    across(parts, type, normal(event.data || (event.dataTransfer ? event.dataTransfer.getData('text/plain') : '') || ''));
                });
                return;
            }

            if (!shown.editor || shown.editor.node !== from) {
                follow();
            }

            var at = range.collapsed ? textOffset(from, range.startContainer, range.startOffset) : -1;

            if (/^delete.*Backward$/.test(type) && at === 0) {
                event.preventDefault();
                oneChange(function () {
                    joinParagraphs(from, true);
                });
            } else if (/^delete.*Forward$/.test(type) && at === textNow(from.getAttribute('data-p')).length) {
                event.preventDefault();
                oneChange(function () {
                    joinParagraphs(from, false);
                });
            } else {
                typedRun(from, type);
            }
        });

        // Typing over a selection that is not all inside one paragraph.
        function across(parts, type, typed) {
            if (parts.length === 1 && parts[0].start < parts[0].end) {
                // A whole paragraph selected (three clicks): the
                // selection is put inside it and typed over there.
                placeSelection(parts[0].node, parts[0].start, parts[0].end);
                follow();

                if (/^insert/.test(type) && typed !== '') {
                    typeText(typed);
                } else if (/^delete/.test(type) && document.execCommand) {
                    document.execCommand('delete');
                }
            } else if (parts.length > 1 && removeAcross(parts) && /^insert/.test(type)) {
                typeText(typed);
            }
        }

        // Paste is plain text, typed where the caret stands; over a
        // selection of several paragraphs it takes them out first.
        doc.addEventListener('paste', function (event) {
            var range = sheetRange();

            if (!range) {
                return;
            }

            var data = event.clipboardData || null;
            var text = data ? normal(data.getData('text/plain') || '') : '';
            var parts = partsOf(range);

            event.preventDefault();

            oneChange(function () {
                if (parts.length > 1 && !removeAcross(parts)) {
                    return;
                }

                if (shown.editor) {
                    typeText(text);
                }
            });
        });

        // The bar follows the selection on the sheet. Once the view has left
        // the page the listener goes.
        function followSelection() {
            if (!doc.isConnected) {
                if (shown.opened) {
                    document.removeEventListener('selectionchange', followSelection);
                }
                return;
            }

            follow();
        }

        document.addEventListener('selectionchange', followSelection);

        doc.addEventListener('keydown', function (event) {
            var onSheet = event.target === paper;

            // The caret may have moved since the last selection change was
            // seen.
            if (onSheet) {
                follow();
            }

            var editor = shown.editor;
            var node = onSheet ? (editor ? editor.node : null) : shown.node;

            if (!node || !(onSheet || node.contains(event.target))) {
                return;
            }

            var key = event.key;
            var plain = !(event.ctrlKey || event.metaKey || event.altKey);

            // The keys of the sheet end here: the panel's own shortcuts must
            // not see them. Esc with nothing typed is the window's.
            if (onSheet && !(key === 'Escape' && !editor.typed)) {
                event.stopPropagation();
            }

            // A key that finishes a word in an input method belongs to it.
            if (event.isComposing || event.keyCode === 229) {
                return;
            }

            if ((event.ctrlKey || event.metaKey) && (key === 's' || key === 'S')) {
                event.preventDefault();
                event.stopPropagation();
                save();
                return;
            }

            // Ctrl+Z undoes a change of the document; Ctrl+Y and
            // Ctrl+Shift+Z do it again. The browser's own undo is not used.
            var asked = stepControls ? historyKey(event) : '';

            if (asked) {
                event.preventDefault();
                event.stopPropagation();
                undoStep(asked === 'undo');
                return;
            }

            if ((onSheet || event.target === node) && formatKey(event)) {
                event.stopPropagation();
                return;
            }

            if (!onSheet && event.target !== node) {
                return;
            }

            if (onSheet) {
                var range = sheetRange();
                var length = textNow(editor.position).length;
                var at = (range && range.collapsed) ? selectionIn(node) : null;

                // Enter cuts the paragraph; Shift+Enter is a line break
                // inside it, typed by the browser.
                if (key === 'Enter' && plain && !event.shiftKey) {
                    event.preventDefault();
                    oneChange(splitParagraph);
                    return;
                }

                if (key === 'Escape' && editor.typed) {
                    event.preventDefault();
                    oneChange(function () {
                        undoTyping(node);
                    });
                    return;
                }

                // The arrows cross paragraphs: left at a paragraph's start
                // and right at its end go to the one beside; up and down are
                // the browser's, a caret it leaves between paragraphs going
                // on the same way.
                if (plain && !event.shiftKey && at && ((key === 'ArrowLeft' && at[0] === 0) || (key === 'ArrowRight' && at[0] === length))) {
                    var next = beside(node, key === 'ArrowLeft' ? -1 : 1);

                    while (next && !next.classList.contains('office-typing')) {
                        next = beside(next, key === 'ArrowLeft' ? -1 : 1);
                    }

                    event.preventDefault();

                    if (next) {
                        enter(next, key === 'ArrowLeft' ? 'end' : 0);
                    }
                    return;
                }

                if (/^Arrow(Up|Down|Left|Right)$/.test(key) || key === 'PageUp' || key === 'PageDown') {
                    travel = /Up|Left/.test(key) ? -1 : 1;
                }
            }

            // Tab and Shift+Tab go to the next paragraph and the one before;
            // at either end the focus leaves the document. In a table they
            // go from cell to cell, Tab in the last cell adding a row.
            if (key === 'Tab' && plain && bar && tabCell(node, event.shiftKey)) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            if (key === 'Tab' && plain) {
                var step = beside(node, event.shiftKey ? -1 : 1);

                if (step) {
                    event.preventDefault();
                    event.stopPropagation();
                    enter(step, event.shiftKey ? 'end' : 0);
                }
                return;
            }

            // A paragraph only picked (locked or to be deleted): the arrows
            // go on to the next and the one before.
            if (!onSheet && (key === 'ArrowDown' || key === 'ArrowUp')) {
                event.preventDefault();
                event.stopPropagation();
                enter(beside(node, (key === 'ArrowDown') ? 1 : -1) || node, 0);
            }
        });

        // The bar over a document with a paragraph that may change.
        if (edit.ok && nodes.some(function (node) { return !node.classList.contains('office-locked'); })) {
            bar = el('div', 'office-format office-doc-format');
            bar.setAttribute('role', 'toolbar');
            bar.setAttribute('aria-label', strings.doc_format_bar || '');

            // Undo and Redo at the bar's start, on the document's own list.
            stepControls = {};

            [['undo', 'bi-arrow-counterclockwise', strings.doc_undo], ['redo', 'bi-arrow-clockwise', strings.doc_redo]].forEach(function (item) {
                var button = el('button', 'pg-icon-button pg-icon-button-sm');
                var glyph = el('span', 'bi ' + item[1]);

                button.type = 'button';
                button.disabled = true;
                button.setAttribute('aria-label', item[2] || '');
                button.title = item[2] || '';
                glyph.setAttribute('aria-hidden', 'true');
                button.appendChild(glyph);

                // A press keeps the focus, and so the caret, on the sheet.
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    undoStep(item[0] === 'undo');
                });

                stepControls[item[0]] = button;
                bar.appendChild(button);
            });

            [['bi-type-bold', strings.format_bold], ['bi-type-italic', strings.format_italic], ['bi-type-underline', strings.doc_underline]].forEach(function (item, index) {
                var button = el('button', 'pg-icon-button pg-icon-button-sm');
                var glyph = el('span', 'bi ' + item[0]);

                button.type = 'button';
                button.setAttribute('aria-pressed', 'false');
                button.setAttribute('aria-label', item[1] || '');
                button.title = item[1] || '';
                glyph.setAttribute('aria-hidden', 'true');
                button.appendChild(glyph);

                // A press keeps the focus, and so the selection, in the
                // paragraph typed in.
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    oneChange(function () {
                        applyFormat(DOC_FORMATS[index]);
                    });
                });

                controls[DOC_FORMATS[index][0]] = button;
                bar.appendChild(button);
            });

            // The size in points, as the workbook bar's: "Default" takes the
            // text's own size off, so its style's holds; a size outside the
            // list shows its own number, a mixed selection none.
            var sizeField = el('span', 'pg-input pg-input-sm office-size');
            var sizeChoice = el('select');
            var sizeOther = el('option', '', '');
            var sizeMixed = el('option', '', '');

            sizeChoice.setAttribute('aria-label', strings.format_size || '');
            sizeChoice.title = strings.format_size || '';

            [''].concat(FONT_SIZES).forEach(function (size) {
                var option = el('option', '', size === '' ? (strings.format_size_default || '') : String(size));

                option.value = String(size);
                sizeChoice.appendChild(option);
            });

            [[sizeOther, 'other'], [sizeMixed, 'mixed']].forEach(function (item) {
                item[0].value = item[1];
                item[0].disabled = true;
                item[0].hidden = true;
                sizeChoice.appendChild(item[0]);
            });

            sizeChoice.addEventListener('change', function () {
                var size = sizeChoice.value;

                if (size === '' || Number(size) > 0) {
                    oneChange(function () {
                        applyLook('s', size === '' ? '' : Number(size));
                    });
                }
            });

            sizeField.appendChild(sizeChoice);
            bar.appendChild(sizeField);
            controls.size = { choice: sizeChoice, other: sizeOther };

            // Text colour and highlight: the glyph over a bar in the
            // selection's colour, opening the colours on offer ("None"
            // first). The text colours are the workbook bar's, the
            // highlights Word's own.
            [
                ['color', 'bi-fonts', strings.doc_color, 'c', TEXT_COLOURS.map(function (colour) {
                    return [colour[0], colour[0], strings['colour_' + colour[1]] || colour[1]];
                })],
                ['highlight', 'bi-highlighter', strings.doc_highlight, 'h', DOC_HIGHLIGHTS.map(function (colour) {
                    return [colour[1], colour[0], strings['highlight_' + colour[0]] || colour[0]];
                })]
            ].forEach(function (item) {
                var button = el('button', 'pg-icon-button pg-icon-button-sm office-colour-button');
                var glyph = el('span', 'bi ' + item[1]);
                var stripe = el('span', 'office-colour-bar');

                button.type = 'button';
                button.setAttribute('aria-label', item[2] || '');
                button.setAttribute('aria-haspopup', 'menu');
                button.title = item[2] || '';
                glyph.setAttribute('aria-hidden', 'true');
                stripe.setAttribute('aria-hidden', 'true');
                button.appendChild(glyph);
                button.appendChild(stripe);

                // A press keeps the selection on the sheet for the menu.
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    if (barMenuOpen && barMenuOpen.opener === button) {
                        closeBarMenu(false);
                        return;
                    }

                    barMenu(button, item[2], item[4], selectionLook(item[3]), function (value, parts) {
                        applyLook(item[3], value, parts);
                    });
                });

                controls[item[0]] = { button: button, stripe: stripe };
                bar.appendChild(button);
            });

            // A link on the selected text, or the one the caret stands in:
            // the link window (Ctrl+K).
            (function () {
                var button = el('button', 'pg-icon-button pg-icon-button-sm');
                var glyph = el('span', 'bi bi-link-45deg');
                var label = (strings.doc_link || '') + ' (Ctrl+K)';

                button.type = 'button';
                button.setAttribute('aria-pressed', 'false');
                button.setAttribute('aria-haspopup', 'dialog');
                button.setAttribute('aria-expanded', 'false');
                button.setAttribute('aria-label', strings.doc_link || '');
                button.setAttribute('aria-keyshortcuts', 'Control+K');
                button.title = label;
                glyph.setAttribute('aria-hidden', 'true');
                button.appendChild(glyph);

                // A press keeps the selection on the sheet for the window.
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    if (linkWindow) {
                        closeLinkWindow(true);
                        return;
                    }

                    openLinkWindow();
                });

                controls.link = button;
                bar.appendChild(button);
            })();

            // A new table after the paragraph open or picked: its size
            // chosen on a grid.
            (function () {
                var button = el('button', 'pg-icon-button pg-icon-button-sm');
                var glyph = el('span', 'bi bi-table');

                button.type = 'button';
                button.setAttribute('aria-haspopup', 'dialog');
                button.setAttribute('aria-expanded', 'false');
                button.setAttribute('aria-label', strings.doc_table || '');
                button.title = strings.doc_table || '';
                glyph.setAttribute('aria-hidden', 'true');
                button.appendChild(glyph);

                // A press keeps the caret on the sheet for the grid.
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    if (tableWindow) {
                        closeTableWindow(true);
                        return;
                    }

                    openTableWindow();
                });

                controls.table = button;
                bar.appendChild(button);
            })();

            // A picture after the paragraph open or picked, from the host's
            // picker or the pictures it lists.
            if (typeof (options || {}).pickPicture === 'function' || typeof (options || {}).pictures === 'function') {
                (function () {
                    var button = el('button', 'pg-icon-button pg-icon-button-sm');
                    var glyph = el('span', 'bi bi-image');

                    button.type = 'button';
                    button.setAttribute('aria-haspopup', 'dialog');
                    button.setAttribute('aria-expanded', 'false');
                    button.setAttribute('aria-label', strings.doc_picture || '');
                    button.title = strings.doc_picture || '';
                    glyph.setAttribute('aria-hidden', 'true');
                    button.appendChild(glyph);

                    // A press keeps the caret on the sheet for the picker.
                    button.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                    });
                    button.addEventListener('click', openPicture);

                    controls.picture = button;
                    bar.appendChild(button);
                })();
            }

            // The alignment of the paragraphs selected, or of the one open.
            var alignGroup = el('span', 'pg-seg');

            alignGroup.setAttribute('role', 'group');
            alignGroup.setAttribute('aria-label', strings.format_align || '');
            controls.align = {};

            DOC_ALIGNS.forEach(function (item) {
                var label = strings['doc_align_' + item[0]] || item[0];
                var button = el('button', 'pg-seg-item');
                var glyph = el('span', 'bi ' + item[1]);

                button.type = 'button';
                button.setAttribute('aria-pressed', 'false');
                button.setAttribute('aria-label', label);
                button.title = label;
                glyph.setAttribute('aria-hidden', 'true');
                button.appendChild(glyph);
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    oneChange(function () {
                        applyAlign(item[0]);
                    });
                });

                controls.align[item[0]] = button;
                alignGroup.appendChild(button);
            });

            bar.appendChild(alignGroup);

            // The picked paragraph's kind, when the server sent the kinds.
            if (Object.keys(kinds).length) {
                var field = el('span', 'pg-input pg-input-sm office-kind');
                var choice = el('select');
                var other = el('option', '', strings.kind_other || '');

                choice.setAttribute('aria-label', strings.doc_kind || '');
                choice.title = strings.doc_kind || '';

                DOC_KINDS.forEach(function (kind) {
                    var option = el('option', '', strings['kind_' + kind] || kind);

                    option.value = kind;
                    choice.appendChild(option);
                });

                other.value = 'other';
                other.disabled = true;
                other.hidden = true;
                choice.appendChild(other);

                choice.addEventListener('change', function () {
                    oneChange(function () {
                        applyKind(choice.value);
                    });
                });

                field.appendChild(choice);
                bar.appendChild(field);
                kindChoice = { select: choice, other: other };
            }

            // Delete the picked paragraph (pressed again, keep it); Enter
            // adds one.
            blockControls = {};

            [['remove', 'bi-trash3', strings.doc_delete, deleteParagraph]].forEach(function (item) {
                var button = el('button', 'pg-icon-button pg-icon-button-sm');
                var glyph = el('span', 'bi ' + item[1]);

                button.type = 'button';
                button.setAttribute('aria-label', item[2] || '');
                button.title = item[2] || '';
                glyph.setAttribute('aria-hidden', 'true');
                button.appendChild(glyph);

                // A press keeps the focus in the paragraph typed in.
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    oneChange(item[3]);
                });

                blockControls[item[0]] = button;
                bar.appendChild(button);
            });

            // With the caret in a table: its rows and columns put in or
            // deleted, the table deleted. At the bar's end, so the other
            // controls stay where they are as they come and go.
            var tableTools = el('span', 'office-table-tools');

            tableTools.setAttribute('role', 'group');
            tableTools.setAttribute('aria-label', strings.doc_table || '');
            tableTools.hidden = true;

            [
                ['bi-arrow-bar-down', strings.doc_table_row, insertRow],
                ['bi-arrow-bar-right', strings.doc_table_column, insertColumn],
                ['bi-arrows-collapse', strings.doc_table_row_delete, deleteRow],
                ['bi-arrows-collapse-vertical', strings.doc_table_column_delete, deleteColumn],
                ['bi-x-square', strings.doc_table_delete, deleteTable]
            ].forEach(function (item) {
                var button = el('button', 'pg-icon-button pg-icon-button-sm');
                var glyph = el('span', 'bi ' + item[0]);

                button.type = 'button';
                button.setAttribute('aria-label', item[1] || '');
                button.title = item[1] || '';
                glyph.setAttribute('aria-hidden', 'true');
                button.appendChild(glyph);

                // A press keeps the caret on the sheet.
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                button.addEventListener('click', function () {
                    oneChange(item[2]);
                });

                tableTools.appendChild(button);
            });

            controls.tableTools = tableTools;
            bar.appendChild(tableTools);

            container.insertBefore(bar, doc);
        }

        drawBar();
        drawStatus();
        container.appendChild(status);

        return {
            kind: 'docx',
            version: office.version || '',
            count: count,
            edits: function () {
                var list = changesNow();

                // Paragraphs of the file changed, then those deleted, then
                // those added, in the order they read after the same one.
                var items = positionsNow().filter(function (position) {
                    return !isNew(position) && !own(deletes, position) && !goneWith(position);
                }).map(Number).sort(function (a, b) {
                    return a - b;
                }).map(function (position) {
                    return entry(String(position), { p: position });
                });

                Object.keys(deletes).map(Number).sort(function (a, b) {
                    return a - b;
                }).forEach(function (position) {
                    items.push({ p: position, delete: true });
                });

                // Paragraphs, tables and pictures added, in reading order;
                // a new table with its rows of cells.
                topAdded().forEach(function (node) {
                    if (freshTable(node)) {
                        var rows = Array.prototype.map.call(node.rows, function (row) {
                            return Array.prototype.map.call(row.cells, cellItems);
                        });

                        items.push({ after: Number(node.getAttribute('data-after')), table: { rows: rows.length, columns: rows[0].length, cells: rows } });
                        return;
                    }

                    if (node.classList.contains('office-added-picture')) {
                        var picture = pictures[node.getAttribute('data-t')];

                        items.push({ after: Number(node.getAttribute('data-after')), picture: { data: picture.data, name: picture.name } });
                        return;
                    }

                    var key = node.getAttribute('data-p');
                    var item = entry(key, { after: Number(node.getAttribute('data-after')) });

                    if (!own(item, 'text')) {
                        item.text = textNow(key);
                    }

                    items.push(item);
                });

                return items.concat(fileTableEntries(cellItems));

                // A new cell's paragraphs as added ones; one empty paragraph
                // as it was put in is sent as none.
                function cellItems(cell) {
                    var list = cellParagraphs(cell).map(function (node) {
                        var key = node.getAttribute('data-p');
                        var item = entry(key, {});

                        if (!own(item, 'text')) {
                            item.text = textNow(key);
                        }

                        return item;
                    });

                    return (list.length === 1 && list[0].text === '' && Object.keys(list[0]).length === 1) ? [] : list;
                }

                // A paragraph's new text, kind and format beside its name.
                function entry(key, item) {
                    if (own(list, key)) {
                        item.text = list[key];
                    }

                    if (own(wantKinds, key)) {
                        item.kind = wantKinds[key];
                    }

                    var align = alignSent(fileAlign(key), own(wantAligns, key) ? wantAligns[key] : null);

                    if (align) {
                        item.align = align;
                    }

                    if (formatted(key)) {
                        var look = lookOf(key, false);

                        // The server counts characters, not the UTF-16
                        // units of a string here.
                        item.format = formatRanges(look.base, look.want).map(function (range) {
                            range.start = Array.from(look.text.slice(0, range.start)).length;
                            range.end = Array.from(look.text.slice(0, range.end)).length;

                            return range;
                        });
                    }

                    return item;
                }
            },
            reset: function () {
                closeEditor(false);
                closeTableWindow(false);
                closePictureWindow(false);

                // Added paragraphs, tables, rows and columns go, deleted
                // ones come back.
                Array.prototype.forEach.call(doc.querySelectorAll('.office-added, .office-added-table, .office-added-picture, .office-new-row, .office-new-cell'), function (node) {
                    var key = node.getAttribute('data-p');

                    if (node.parentNode) {
                        node.parentNode.removeChild(node);
                    }

                    if (key) {
                        [texts, marks, kinds, drawn].forEach(function (map) {
                            delete map[key];
                        });
                    }
                });

                Array.prototype.forEach.call(paper.querySelectorAll('table[hidden], tr[hidden], td[hidden], th[hidden]'), function (node) {
                    node.hidden = false;
                });

                nodes = Array.prototype.slice.call(doc.querySelectorAll('[data-p]'));

                if (shown.node && !doc.contains(shown.node)) {
                    shown.node = null;
                }

                var positions = Object.keys(changes).concat(Object.keys(looks), Object.keys(wantKinds), Object.keys(wantAligns), Object.keys(deletes)).filter(function (position) {
                    return !isNew(position);
                });

                changes = {};
                looks = {};
                wantKinds = {};
                wantAligns = {};
                deletes = {};
                shown.last = null;
                steps.clear();
                run = null;

                positions.forEach(function (position) {
                    var node = doc.querySelector('[data-p="' + position + '"]');

                    if (node) {
                        redraw(node);
                    }
                });

                // Paragraphs joined into others come back; the caret's
                // paragraph is typed in again.
                nodes.forEach(function (node) {
                    node.hidden = false;
                });
                follow();

                drawBar();
                notify();
            }
        };
    }

    // A .csv or .tsv: its sheet under a choice of the separator and the
    // character set it is read with, set to the ones the server guessed or
    // was last asked for. A change asks the window that drew the view to
    // read the file again (options.reread(choice, done)): done(office)
    // draws the answer in this one's place, done(null, message) says why
    // not and leaves the sheet as it was. Only looked at: nothing comes
    // back to save.
    function renderCsv(container, office, strings, options) {
        var reread = (options && typeof options.reread === 'function') ? options.reread : null;
        var used = office.csv || {};
        var bar = el('div', 'office-choice');
        var problem = el('div', 'pg-field-help pg-field-error office-choice-problem');
        var busy = false;

        function group(label, name, choices) {
            var wrap = el('div', 'office-choice-group');
            var seg = el('div', 'pg-seg');

            seg.setAttribute('role', 'group');
            seg.setAttribute('aria-label', label);

            choices.forEach(function (choice) {
                var item = el('button', 'pg-seg-item', choice[1]);

                item.type = 'button';
                item.setAttribute('data-csv-' + name, choice[0]);
                item.setAttribute('aria-pressed', (choice[0] === used[name]) ? 'true' : 'false');

                item.addEventListener('click', function () {
                    if (busy || choice[0] === used[name]) {
                        return;
                    }

                    var asked = { delimiter: used.delimiter || '', encoding: used.encoding || '' };

                    asked[name] = choice[0];
                    busy = true;
                    bar.setAttribute('aria-busy', 'true');
                    problem.textContent = '';

                    reread(asked, function (answer, message) {
                        busy = false;
                        bar.removeAttribute('aria-busy');

                        if (!container.isConnected) {
                            return;
                        }

                        if (answer && answer.kind === 'csv') {
                            window.pgOfficeView.render(container, answer, strings, options);

                            // The key or click that made the change keeps its place.
                            var again = container.querySelector('.office-choice [data-csv-' + name + '="' + choice[0] + '"]');

                            if (again) {
                                again.focus();
                            }

                            return;
                        }

                        problem.textContent = message || '';
                    });
                });

                seg.appendChild(item);
            });

            wrap.appendChild(el('span', 'pg-text-meta', label));
            wrap.appendChild(seg);

            return wrap;
        }

        if (reread) {
            bar.appendChild(group(strings.csv_delimiter || '', 'delimiter', [
                ['comma', strings.csv_comma || ''],
                ['semicolon', strings.csv_semicolon || ''],
                ['tab', strings.csv_tab || '']
            ]));
            bar.appendChild(group(strings.csv_encoding || '', 'encoding', [
                ['utf-8', strings.csv_utf8 || ''],
                ['windows-1254', strings.csv_1254 || '']
            ]));
            problem.setAttribute('role', 'status');
            bar.appendChild(problem);
            container.appendChild(bar);
        }

        renderBook(container, office, strings, null);

        return null;
    }

    // An .xls: its sheets drawn as a workbook's, only looked at, under the
    // button that has the window save a copy as .xlsx (options.saveCopy):
    // the copy is the file that is changed. The button keeps its label and
    // is disabled only while the copy is being written; the answer is said
    // under it.
    function renderXls(container, office, strings, options) {
        var saveCopy = (options && typeof options.saveCopy === 'function') ? options.saveCopy : null;

        if (saveCopy) {
            var bar = el('div', 'office-choice');
            var copy = el('button', 'pg-button pg-button-quiet pg-button-sm');
            var said = el('div', 'pg-field-help office-choice-problem');

            copy.type = 'button';
            copy.setAttribute('data-xls-copy', '');
            copy.appendChild(el('span', 'bi bi-filetype-xlsx'));
            copy.appendChild(el('span', 'pg-btn-label', strings.xls_copy || ''));

            copy.addEventListener('click', function () {
                if (copy.disabled) {
                    return;
                }

                copy.disabled = true;
                copy.setAttribute('aria-busy', 'true');
                said.textContent = '';
                said.classList.remove('pg-field-error');

                saveCopy(function (ok, message) {
                    copy.disabled = false;
                    copy.removeAttribute('aria-busy');
                    said.classList.toggle('pg-field-error', !ok);
                    said.textContent = message || '';
                });
            });

            said.setAttribute('role', 'status');
            bar.appendChild(copy);
            bar.appendChild(el('span', 'pg-text-meta', strings.xls_copy_help || ''));
            bar.appendChild(said);
            container.appendChild(bar);
        }

        renderBook(container, office, strings, null);

        return null;
    }

    // A presentation's text: its slides as a list, each with its number and
    // title, its text and pictures in reading order and its speaker notes;
    // only looked at. Every text is set as text; a picture's source is only
    // ever a data: URL of an image the server read from the file.
    function renderSlideList(container, office, strings) {
        var list = el('ol', 'office-doc office-slides');
        var slides = office.slides || [];

        list.setAttribute('aria-label', strings.slides || '');
        list.tabIndex = 0;

        slides.forEach(function (slide) {
            var item = el('li', 'office-slide');
            var head = el('h3', 'office-slide-title');
            var number = fill(strings.slide || '', slide.n);

            head.appendChild(el('span', 'office-slide-n pg-text-meta', number));

            if (slide.title) {
                head.appendChild(document.createTextNode(' ' + slide.title));
            }

            if (slide.hidden) {
                head.appendChild(el('span', 'office-slide-hidden', strings.slide_hidden || ''));
            }

            item.appendChild(head);

            (slide.items || []).forEach(function (part) {
                if (part.type === 'picture' && /^data:image\//.test(String(part.src || ''))) {
                    var img = el('img', 'office-img');

                    img.src = part.src;
                    img.alt = part.alt || '';
                    item.appendChild(img);
                    return;
                }

                if (part.type === 'picture' || part.type === 'lost') {
                    item.appendChild(el('div', 'office-lost', '[' + (strings.picture || '') + ']'));
                    return;
                }

                var line = el('p', 'office-slide-text' + (part.type === 'row' ? ' office-slide-row' : '') + (part.level > 0 ? ' office-lvl-' + Math.min(8, part.level) : ''));

                line.textContent = part.text || '';
                item.appendChild(line);
            });

            if (slide.notes && slide.notes.length) {
                var notes = el('div', 'office-slide-notes');

                notes.appendChild(el('div', 'pg-text-meta', strings.slide_notes || ''));
                slide.notes.forEach(function (text) {
                    notes.appendChild(el('p', 'office-slide-text', text));
                });
                item.appendChild(notes);
            }

            list.appendChild(item);
        });

        container.appendChild(list);

        if (!slides.length) {
            list.appendChild(el('li', 'office-note', strings.slide_none || ''));
        }

        if (office.images_lost > 0) {
            container.appendChild(el('div', 'office-note', fill(strings.images_lost || '', office.images_lost)));
        }

        if (office.truncated) {
            container.appendChild(el('div', 'office-note', strings.truncated || ''));
        }

        return null;
    }

    // The width of a thumb in the strip, in CSS pixels (the look's 148).
    var SLIDE_THUMB = 148;

    // A slide's document with its pictures pointed at the data: addresses
    // the preview's door answered (layer id => address); a picture layer
    // with no address is left out, so a picture never comes from elsewhere.
    function slideDoc(doc, pictures) {
        var copy = JSON.parse(JSON.stringify(doc || {}));

        copy.layers = (Array.isArray(copy.layers) ? copy.layers : []).filter(function (layer) {
            if (!layer || layer.type !== 'image') {
                return true;
            }

            var src = pictures ? pictures[layer.id] : null;

            if (typeof src !== 'string' || !/^data:image\/(png|jpeg|gif|webp);base64,/.test(src)) {
                return false;
            }

            layer.src = src;

            return true;
        });

        return copy;
    }

    // The size a slide is drawn at inside room (w x h), its ratio kept and
    // never larger than its own size.
    function slideFit(width, height, roomW, roomH) {
        var scale = Math.min(1, roomW / width, roomH > 0 ? roomH / height : Infinity);

        scale = (isFinite(scale) && scale > 0) ? scale : Math.min(1, roomW / width);

        return { w: Math.max(1, Math.floor(width * scale)), h: Math.max(1, Math.floor(height * scale)) };
    }

    // The slide a key moves to from index among count slides, or -1 when
    // the key is not one of the slides' keys.
    function slideKey(key, index, count) {
        if (key === 'ArrowLeft' || key === 'ArrowUp' || key === 'PageUp') {
            return Math.max(0, index - 1);
        }

        if (key === 'ArrowRight' || key === 'ArrowDown' || key === 'PageDown') {
            return Math.min(count - 1, index + 1);
        }

        if (key === 'Home') {
            return 0;
        }

        if (key === 'End') {
            return count - 1;
        }

        return -1;
    }

    // "Slide 3 of 24" with the slide's title after it, for a screen reader.
    function slideName(strings, n, count, title) {
        var name = String(strings.slide_of || '').replace('{1}', n).replace('{2}', count);

        return title ? name + ': ' + title : name;
    }

    // A presentation: the tabs Slides and Text. Slides draws each slide's
    // document (office.design, mapped on the server) with the image editor's
    // painter, its pictures asked for one slide at a time through
    // options.slidePictures(n, done) — the window's own door, read with its
    // view rights. Without the painter, the documents or that door, the text
    // list alone, as before.
    function renderSlides(container, office, strings, options) {
        var design = office && office.design;
        var pages = (design && Array.isArray(design.pages)) ? design.pages : [];
        var ask = options && (typeof options.slidePictures === 'function') ? options.slidePictures : null;

        if (!pages.length || !ask || !(design.width > 0) || !(design.height > 0) || typeof window === 'undefined' || !window.pgImageEditor || !window.pgImageEditor.load) {
            return renderSlideList(container, office, strings);
        }

        var uid = 'office-slides-' + Math.random().toString(36).slice(2, 9);
        var wrap = el('div', 'office-slides-view');
        var tabs = el('div', 'pg-seg office-slides-tabs');
        var slidesPane = el('div', 'office-slides-pane');
        var textPane = el('div', 'office-slides-pane');
        var textDrawn = false;
        var count = pages.length;
        var byNumber = {};
        var current = 0;

        (office.slides || []).forEach(function (slide) {
            byNumber[slide.n] = slide;
        });

        // The two tabs, Slides first; Text draws today's list on first use.
        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', strings.slides || '');

        var tabButtons = [['slides', strings.slides || '', slidesPane], ['text', strings.slides_text || '', textPane]].map(function (item, index) {
            var tab = el('button', 'pg-seg-item' + (index ? '' : ' active'), item[1]);

            tab.type = 'button';
            tab.id = uid + '-tab-' + item[0];
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-selected', index ? 'false' : 'true');
            tab.setAttribute('aria-controls', uid + '-pane-' + item[0]);
            tab.tabIndex = index ? -1 : 0;
            item[2].id = uid + '-pane-' + item[0];
            item[2].setAttribute('role', 'tabpanel');
            item[2].setAttribute('aria-labelledby', tab.id);
            item[2].hidden = index > 0;
            tabs.appendChild(tab);

            return tab;
        });

        function showTab(index, focus) {
            tabButtons.forEach(function (tab, i) {
                var on = (i === index);

                tab.classList.toggle('active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.tabIndex = on ? 0 : -1;
            });
            slidesPane.hidden = index !== 0;
            textPane.hidden = index !== 1;

            if (index === 1 && !textDrawn) {
                textDrawn = true;
                renderSlideList(textPane, office, strings);
            }

            if (focus) {
                tabButtons[index].focus();
            }

            if (index === 0) {
                drawMain();
            }
        }

        tabButtons.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                showTab(index, false);
            });
            tab.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight' || event.key === 'Home' || event.key === 'End') {
                    event.preventDefault();
                    showTab((event.key === 'Home') ? 0 : ((event.key === 'End') ? 1 : 1 - index), true);
                }
            });
        });

        wrap.appendChild(tabs);

        // What the drawing leaves out, in one quiet line.
        if (design.left) {
            slidesPane.appendChild(el('p', 'pg-text-meta office-slides-left', design.left));
        }

        var body = el('div', 'office-slides-body');
        var strip = el('ol', 'office-slides-strip');
        var main = el('div', 'office-slides-main');
        var stage = el('div', 'office-slides-stage');
        var nav = el('div', 'office-slides-nav');
        var said = el('div', 'visually-hidden');
        var position = el('span', 'pg-text-meta office-slides-position');
        var notesToggle = el('button', 'pg-button pg-button-quiet pg-button-sm office-slides-notes-toggle', strings.slide_notes || '');
        var notes = el('div', 'office-slides-notes');
        var thumbs = [];

        strip.setAttribute('aria-label', strings.slides || '');
        stage.tabIndex = 0;
        stage.setAttribute('role', 'img');
        said.setAttribute('aria-live', 'polite');
        notesToggle.type = 'button';
        notesToggle.setAttribute('aria-expanded', 'false');
        notesToggle.setAttribute('aria-controls', uid + '-notes');
        notes.id = uid + '-notes';
        notes.hidden = true;
        notesToggle.addEventListener('click', function () {
            var open = notes.hidden;

            notes.hidden = !open;
            notesToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        function navButton(icon, label, step) {
            var button = el('button', 'pg-icon-button pg-icon-button-sm');
            var glyph = el('span', 'bi ' + icon);

            button.type = 'button';
            glyph.setAttribute('aria-hidden', 'true');
            button.appendChild(glyph);
            button.setAttribute('aria-label', label);
            button.title = label;
            button.addEventListener('click', function () {
                choose(current + step, false);
            });

            return button;
        }

        var previous = navButton('bi-chevron-left', strings.slide_previous || '', -1);
        var next = navButton('bi-chevron-right', strings.slide_next || '', 1);

        nav.appendChild(previous);
        nav.appendChild(position);
        nav.appendChild(next);

        // The painter: the editor's own drawing code, loaded once the tab
        // shows; each slide's pictures asked for once.
        var editor = null;
        var asked = {};
        var queue = [];
        var busy = false;

        function loadEditor() {
            if (!editor) {
                editor = window.pgImageEditor.load().then(function (E) {
                    return (E && E.parts) ? E.parts : (window.PgImageEditor && window.PgImageEditor.parts) || null;
                });
            }

            return editor;
        }

        function picturesOf(page) {
            var n = page.n;

            if (!asked[n]) {
                asked[n] = (page.doc && (page.doc.layers || []).some(function (layer) { return layer && layer.type === 'image'; }))
                    ? new Promise(function (resolve) {
                        ask(n, function (answer) {
                            resolve((answer && answer.pictures) ? answer.pictures : {});
                        });
                    })
                    : Promise.resolve({});
            }

            return asked[n];
        }

        // A painter for a page, its layers ready: kept, so a slide is drawn
        // again at another size without loading anything.
        var painters = {};

        function painterOf(index) {
            if (!painters[index]) {
                var page = pages[index];

                painters[index] = Promise.all([loadEditor(), picturesOf(page)]).then(function (loaded) {
                    var parts = loaded[0];
                    var opened = parts ? parts.M.canvasOpenDoc(slideDoc(page.doc, loaded[1])) : null;

                    if (!opened) {
                        throw new Error('slide');
                    }

                    var painter = Object.create(parts.Editor.prototype);

                    painter.doc = opened;
                    painter.pictures = {};
                    painter.textFonts = {};
                    painter.dataReads = {};
                    painter.dataAsked = {};
                    painter.dataBareDraw = true;
                    painter.layersDirty = true;
                    painter.renderView = function () { /* no stage to draw */ };

                    return painter.layersReady().then(function () {
                        return painter;
                    });
                });
            }

            return painters[index];
        }

        // A slide drawn into box at w x h CSS pixels: a canvas in place of
        // the skeleton, or the sentence that it could not be drawn.
        function paint(index, box, w, h) {
            return painterOf(index).then(function (painter) {
                var art = painter.doc.artboard;
                var ratio = Math.min(2, Math.max(1, window.devicePixelRatio || 1));
                var canvas = document.createElement('canvas');
                var g;

                canvas.width = Math.max(1, Math.round(w * ratio));
                canvas.height = Math.max(1, Math.round(h * ratio));
                canvas.style.width = w + 'px';
                canvas.style.height = h + 'px';
                canvas.setAttribute('aria-hidden', 'true');
                g = canvas.getContext('2d');

                if (art.background && art.background !== 'transparent') {
                    g.fillStyle = art.background;
                    g.fillRect(0, 0, canvas.width, canvas.height);
                }

                painter.paintLayers(g, { x: 0, y: 0, w: art.width, h: art.height }, canvas.width, canvas.height);
                box.innerHTML = '';
                box.classList.remove('office-slides-skeleton');
                box.appendChild(canvas);
            }).catch(function () {
                box.innerHTML = '';
                box.classList.remove('office-slides-skeleton');
                box.classList.add('office-slides-failed');
                box.appendChild(el('span', 'pg-text-meta', strings.slide_failed || ''));
            });
        }

        // One slide drawn at a time, the chosen one first.
        function drawNext() {
            if (busy || !queue.length || !container.isConnected) {
                return;
            }

            var job = queue.shift();

            busy = true;
            job().then(function () {
                busy = false;
                drawNext();
            });
        }

        function later(job, first) {
            if (first) {
                queue.unshift(job);
            } else {
                queue.push(job);
            }

            drawNext();
        }

        function sizeOf(page) {
            var art = (page.doc && page.doc.artboard) || {};

            return { w: art.width || design.width, h: art.height || design.height };
        }

        function skeleton(box, w, h) {
            box.innerHTML = '';
            box.classList.remove('office-slides-failed');
            box.classList.add('office-slides-skeleton');
            box.style.width = w + 'px';
            box.style.height = h + 'px';
        }

        // The strip: a numbered thumb a slide in its own ratio, drawn when it
        // comes into view.
        var seen = typeof window.IntersectionObserver === 'function' ? new window.IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    seen.unobserve(entry.target);
                    drawThumb(thumbs.indexOf(entry.target.parentNode.parentNode));
                }
            });
        }, { root: null, rootMargin: '200px' }) : null;

        function drawThumb(index) {
            var thumb = thumbs[index];

            if (!thumb || thumb.drawn) {
                return;
            }

            thumb.drawn = true;
            later(function () {
                var size = sizeOf(pages[index]);
                var fit = slideFit(size.w, size.h, SLIDE_THUMB, 0);

                return paint(index, thumb.picture, fit.w, fit.h);
            }, false);
        }

        pages.forEach(function (page, index) {
            var text = byNumber[page.n] || {};
            var item = el('li', 'office-slides-thumb-item');
            var button = el('button', 'office-slides-thumb' + (page.hidden ? ' office-slides-thumb-hidden' : ''));
            var picture = el('span', 'office-slides-picture');
            var size = sizeOf(page);
            var fit = slideFit(size.w, size.h, SLIDE_THUMB, 0);
            var label = slideName(strings, page.n, count, text.title || '');

            button.type = 'button';
            button.tabIndex = index ? -1 : 0;
            button.setAttribute('aria-label', page.hidden ? label + ', ' + (strings.slide_hidden || '') : label);
            button.appendChild(el('span', 'pg-text-meta office-slides-n', page.n));
            skeleton(picture, fit.w, fit.h);
            button.appendChild(picture);

            if (page.hidden) {
                button.appendChild(el('span', 'pg-text-meta office-slides-hidden', strings.slide_hidden || ''));
            }

            button.addEventListener('click', function () {
                choose(index, false);
            });
            item.picture = picture;
            item.button = button;
            item.appendChild(button);
            strip.appendChild(item);
            thumbs.push(item);
        });

        // The chosen slide, fitted to the room beside the strip.
        var drawnAt = '';

        function drawMain() {
            if (slidesPane.hidden || !container.isConnected) {
                return;
            }

            var page = pages[current];
            var size = sizeOf(page);
            var roomW = Math.max(120, (stage.clientWidth || main.clientWidth || 624) - 24);
            var roomH = Math.max(120, Math.round(window.innerHeight * 0.6));
            var fit = slideFit(size.w, size.h, roomW, roomH);
            var key = current + ':' + fit.w + 'x' + fit.h;

            if (key === drawnAt) {
                return;
            }

            drawnAt = key;

            var box = el('div', 'office-slides-picture');

            skeleton(box, fit.w, fit.h);
            stage.innerHTML = '';
            stage.appendChild(box);
            later(function () {
                return (drawnAt === key) ? paint(current, box, fit.w, fit.h) : Promise.resolve();
            }, true);
        }

        function choose(index, focusThumb) {
            index = Math.max(0, Math.min(count - 1, index));

            var page = pages[index];
            var text = byNumber[page.n] || {};
            var name = slideName(strings, page.n, count, text.title || '');

            current = index;
            thumbs.forEach(function (thumb, i) {
                thumb.button.classList.toggle('is-active', i === index);
                thumb.button.setAttribute('aria-current', (i === index) ? 'true' : 'false');
                thumb.button.tabIndex = (i === index) ? 0 : -1;
            });
            position.textContent = page.n + ' / ' + count;
            previous.disabled = index === 0;
            next.disabled = index === count - 1;
            stage.setAttribute('aria-label', page.hidden ? name + ', ' + (strings.slide_hidden || '') : name);
            said.textContent = name;

            notes.innerHTML = '';
            (text.notes || []).forEach(function (line) {
                notes.appendChild(el('p', 'office-slide-text', line));
            });
            notesToggle.hidden = !(text.notes && text.notes.length);

            if (notesToggle.hidden) {
                notes.hidden = true;
                notesToggle.setAttribute('aria-expanded', 'false');
            }

            if (thumbs[index].button.scrollIntoView) {
                thumbs[index].button.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }

            if (focusThumb) {
                thumbs[index].button.focus();
            }

            drawMain();
            drawThumb(index);
        }

        // The arrow keys, Home and End while the focus is in the strip or on
        // the slide.
        function onKey(event) {
            var to = slideKey(event.key, current, count);

            if (to < 0 || event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }

            event.preventDefault();
            choose(to, strip.contains(document.activeElement));
        }

        strip.addEventListener('keydown', onKey);
        stage.addEventListener('keydown', onKey);

        main.appendChild(stage);
        main.appendChild(nav);
        main.appendChild(notesToggle);
        main.appendChild(notes);
        body.appendChild(strip);
        body.appendChild(main);
        slidesPane.appendChild(body);
        slidesPane.appendChild(said);

        if (office.truncated || design.truncated) {
            slidesPane.appendChild(el('div', 'office-note', strings.truncated || ''));
        }

        wrap.appendChild(slidesPane);
        wrap.appendChild(textPane);
        container.appendChild(wrap);

        thumbs.forEach(function (thumb, index) {
            if (seen) {
                seen.observe(thumb.picture);
            } else {
                drawThumb(index);
            }
        });

        // Drawn again at its new size when the window changes.
        if (typeof window.ResizeObserver === 'function') {
            var timer = null;
            var watch = new window.ResizeObserver(function () {
                if (!container.isConnected) {
                    watch.disconnect();
                    return;
                }

                clearTimeout(timer);
                timer = setTimeout(drawMain, 150);
            });

            watch.observe(main);
        }

        choose(0, false);

        return null;
    }

    window.pgOfficeView = {
        // The sheet tools' own logic, which reads no page: sort, filter,
        // find and CSV over the rows the server sent.
        sheetTools: {
            cellText: cellText,
            headerRow: headerRow,
            arrange: arrange,
            columnValues: columnValues,
            findCells: findCells,
            csvField: csvField,
            csvText: csvText,
            csvName: csvName
        },
        // A workbook's waiting changes as one ordered list, and the workbook
        // it leaves.
        sheetList: {
            cell: listCell,
            format: listFormat,
            count: listCount,
            look: listLook,
            sides: borderSides,
            book: listBook,
            edits: listEdits,
            history: listHistory,
            size: listSize,
            values: listValues
        },
        // The grid's clipboard: tab-separated text read and written, the
        // limits a paste, a fill and a clear are held to.
        sheetClip: {
            rows: clipRows,
            text: clipText,
            check: clipCheck,
            cells: clipCells
        },
        // The units a column's width and a row's height are drawn in.
        sheetSizes: {
            widthPx: widthPx,
            heightPx: heightPx,
            widthOf: widthOf,
            heightOf: heightOf
        },
        // A document's bold, italic and underline, which read no page: the
        // look the server sent, carried over a changed text, and the
        // stretches a save sends.
        docMarks: {
            expandMarks: expandMarks,
            carryMarks: carryMarks,
            formatRanges: formatRanges,
            cutLook: cutLook,
            exactBase: exactBase,
            lookOf: lookParts,
            withLook: withLook,
            linkOf: linkOf,
            linkAddress: linkAddress
        },
        // A paragraph's alignment: the file's, how it is drawn, what a save
        // sends.
        docAlign: {
            alignOf: alignOf,
            alignCss: alignCss,
            alignSent: alignSent
        },
        // The document's list of changes for Undo and Redo, and the keys
        // that ask for them.
        docHistory: {
            changeList: changeList,
            historyKey: historyKey
        },
        // A table's save entries from what the page shows, and the size
        // grid's rows.
        docTable: {
            tableEntries: tableEntries,
            pickRows: pickRows,
            limits: DOC_TABLE_LIMITS
        },
        // A picture's kind by its first bytes and why one is refused.
        // The Slides tab's own logic, which reads no page: a slide's
        // pictures pointed at the door's addresses, the size it is drawn
        // at, the slide a key moves to, the name a screen reader hears.
        slides: {
            doc: slideDoc,
            fit: slideFit,
            key: slideKey,
            name: slideName
        },
        docPicture: {
            pictureType: pictureType,
            pictureRefusal: pictureRefusal,
            limits: DOC_PICTURE_LIMITS
        },
        // A paragraph's text read from its drawing while it is typed in,
        // and a point of that text found in the drawing again.
        docText: {
            paragraphText: paragraphText,
            textOffset: textOffset,
            textPoint: textPoint
        },
        render: function (container, office, strings, options) {
            strings = strings || {};
            container.innerHTML = '';
            container.classList.add('office-view');

            if (office && office.kind === 'xlsx') {
                return renderBook(container, office, strings, options || null);
            }

            if (office && office.kind === 'csv') {
                return renderCsv(container, office, strings, options || null);
            }

            // An .ods: its sheets drawn as a workbook's, only looked at; the
            // editing options of a window that edits workbooks are not taken.
            if (office && office.kind === 'ods') {
                renderBook(container, office, strings, null);
                return null;
            }

            if (office && office.kind === 'xls') {
                return renderXls(container, office, strings, options || null);
            }

            if (office && office.kind === 'docx') {
                return renderDocument(container, office, strings, options || null);
            }

            // A presentation is only looked at; its options carry the door
            // its slides' pictures are asked for through (slidePictures).
            if (office && office.kind === 'pptx') {
                return renderSlides(container, office, strings, options || null);
            }

            return null;
        }
    };
})();
