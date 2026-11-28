/**
 * Pinegrap - Enterprise Website Platform
 *
 * Simple spreadsheet formulas in the browser: the twin of
 * includes/fn/office_formula.php (read a formula into a tree, the cells it
 * reads, its text, its value) and of pg_office_xlsx_formula_results() in
 * includes/fn/office_edit.php (a sheet's formulas worked out in dependency
 * order, a circle #REF!). The workbook grid (pg_office_view.js) draws with it
 * at once what the save works out on the server; the server's result is the
 * one written. Both run the same test cases (tests/office_formula_twin_test.php).
 *
 *   var F = window.pgOfficeFormula;
 *   var tree = F.parse('=SUM(A1:A3)*2', 'tr');   // null: not a simple formula
 *   F.text(tree);                                // "SUM(A1:A3)*2"
 *   F.value(tree, { 1: { 0: 1 }, 2: { 0: 2 } }); // 6
 *
 * Cells are { row: { column: value } }, row 1-based, column 0-based; a value
 * is a number, a text, true/false, null or '' for empty, or { error: code }.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Excel's limits, as PG_OFFICE_FORMULA_MAX_LENGTH, _MAX_DEPTH and
    // _MAX_ARGUMENTS. The length is counted in UTF-8 bytes, as strlen().
    var MAX_LENGTH = 8192;
    var MAX_DEPTH = 64;
    var MAX_ARGUMENTS = 255;

    var ENGLISH = { SUM: 'SUM', AVERAGE: 'AVERAGE', MIN: 'MIN', MAX: 'MAX', COUNT: 'COUNT' };
    var LOCAL = {
        tr: { SUM: 'TOPLA', AVERAGE: 'ORTALAMA', MIN: 'MİN', MAX: 'MAK', COUNT: 'SAY' }
    };

    function own(object, key) {
        return Object.prototype.hasOwnProperty.call(object, key);
    }

    function bytes(text) {
        var count = 0;

        for (var index = 0; index < text.length; index++) {
            var code = text.charCodeAt(index);

            if (code < 0x80) {
                count += 1;
            } else if (code < 0x800) {
                count += 2;
            } else if (code >= 0xD800 && code <= 0xDBFF) {
                count += 4;
                index++;
            } else {
                count += 3;
            }
        }

        return count;
    }

    // English name => local name in a language; English when it has none.
    function names(language) {
        language = String(language || '').toLowerCase();

        return own(LOCAL, language) ? LOCAL[language] : ENGLISH;
    }

    // Upper case, the Turkish dotted and dotless i read as I.
    function nameKey(name) {
        return String(name).replace(/[ıİ]/g, 'I').replace(/[a-z]/g, function (letter) {
            return letter.toUpperCase();
        });
    }

    function functionName(name, language) {
        var key = nameKey(name);
        var english;

        for (english in ENGLISH) {
            if (own(ENGLISH, english) && key === english) {
                return english;
            }
        }

        var local = names(language);

        for (english in local) {
            if (own(local, english) && key === nameKey(local[english])) {
                return english;
            }
        }

        return null;
    }

    // [kind, text] tokens, or null for a character no simple formula holds.
    function tokens(text) {
        var list = [];
        var at = 0;
        var kinds = { '+': 'op', '-': 'op', '*': 'op', '/': 'op', '(': 'open', ')': 'close', ':': 'colon', ',': 'separator', ';': 'separator' };
        var number = /^(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/;
        var word = /^[A-Za-z_$\u0080-￿][A-Za-z0-9_.$\u0080-￿]*/;

        while (at < text.length) {
            var character = text.charAt(at);

            if (character === ' ' || character === '\t' || character === '\n' || character === '\r') {
                at++;
                continue;
            }

            var rest = text.slice(at);
            var match = number.exec(rest);

            if (match) {
                list.push(['number', match[0]]);
                at += match[0].length;
                continue;
            }

            match = word.exec(rest);

            if (match) {
                list.push(['word', match[0]]);
                at += match[0].length;
                continue;
            }

            if (!own(kinds, character)) {
                return null;
            }

            list.push([kinds[character], character]);
            at++;
        }

        return list;
    }

    function cellNode(word) {
        var match = /^(\$?)([A-Za-z]{1,3})(\$?)([1-9][0-9]{0,6})$/.exec(word);

        if (!match) {
            return null;
        }

        var letters = match[2].toUpperCase();
        var column = 0;

        for (var index = 0; index < letters.length; index++) {
            column = (column * 26) + (letters.charCodeAt(index) - 64);
        }

        var row = parseInt(match[4], 10);

        if (column > 16384 || row > 1048576) {
            return null;
        }

        return { type: 'cell', row: row, column: column - 1, absolute_column: match[1] === '$', absolute_row: match[3] === '$' };
    }

    function peek(state) {
        return (state.position < state.tokens.length) ? state.tokens[state.position] : [null, null];
    }

    function parseSum(state) {
        var left = parseProduct(state);

        while (left !== null) {
            var token = peek(state);

            if (token[0] !== 'op' || (token[1] !== '+' && token[1] !== '-')) {
                break;
            }

            state.position++;

            var right = parseProduct(state);

            if (right === null) {
                return null;
            }

            left = { type: 'binary', op: token[1], left: left, right: right };
        }

        return left;
    }

    function parseProduct(state) {
        var left = parseUnary(state);

        while (left !== null) {
            var token = peek(state);

            if (token[0] !== 'op' || (token[1] !== '*' && token[1] !== '/')) {
                break;
            }

            state.position++;

            var right = parseUnary(state);

            if (right === null) {
                return null;
            }

            left = { type: 'binary', op: token[1], left: left, right: right };
        }

        return left;
    }

    // A leading minus or plus binds closer than * and /, as in Excel.
    function parseUnary(state) {
        var token = peek(state);

        if (token[0] === 'op' && (token[1] === '-' || token[1] === '+')) {
            state.position++;

            if (++state.depth > MAX_DEPTH) {
                return null;
            }

            var operand = parseUnary(state);

            state.depth--;

            return (operand === null) ? null : { type: 'unary', op: token[1], operand: operand };
        }

        return parsePrimary(state);
    }

    function parsePrimary(state) {
        var token = peek(state);

        if (token[0] === 'number') {
            state.position++;

            var value = parseFloat(token[1]);

            return isFinite(value) ? { type: 'number', value: value } : null;
        }

        if (token[0] === 'open') {
            state.position++;

            if (++state.depth > MAX_DEPTH) {
                return null;
            }

            var inner = parseSum(state);

            state.depth--;

            if (inner === null || peek(state)[0] !== 'close') {
                return null;
            }

            state.position++;

            return { type: 'group', inner: inner };
        }

        if (token[0] !== 'word') {
            return null;
        }

        state.position++;

        var next = peek(state)[0];

        if (next === 'open') {
            return parseCall(state, token[1]);
        }

        var cell = cellNode(token[1]);

        if (cell === null) {
            return null;
        }

        if (next !== 'colon') {
            return cell;
        }

        state.position++;

        var end = peek(state);
        var to = (end[0] === 'word') ? cellNode(end[1]) : null;

        if (to === null) {
            return null;
        }

        state.position++;

        return { type: 'range', from: cell, to: to };
    }

    function parseCall(state, name) {
        var english = functionName(name, state.language);

        if (english === null || ++state.depth > MAX_DEPTH) {
            return null;
        }

        state.position++;

        var list = [];

        while (true) {
            var argument = parseSum(state);

            if (argument === null || list.length >= MAX_ARGUMENTS) {
                return null;
            }

            list.push(argument);

            var kind = peek(state)[0];

            state.position++;

            if (kind === 'close') {
                break;
            }

            if (kind !== 'separator') {
                return null;
            }
        }

        state.depth--;

        return { type: 'function', name: english, arguments: list };
    }

    // A range stands only as a function's argument, alone or in brackets.
    function rangesPlaced(node, argument) {
        switch (node.type) {
            case 'range':
                return argument;
            case 'group':
                return rangesPlaced(node.inner, argument);
            case 'unary':
                return rangesPlaced(node.operand, false);
            case 'binary':
                return rangesPlaced(node.left, false) && rangesPlaced(node.right, false);
            case 'function':
                for (var index = 0; index < node.arguments.length; index++) {
                    if (!rangesPlaced(node.arguments[index], true)) {
                        return false;
                    }
                }
        }

        return true;
    }

    // A formula's text as a tree, or null when it is not a simple formula.
    function parse(text, language) {
        text = String(text === null || text === undefined ? '' : text).replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');

        if (text.charAt(0) === '=') {
            text = text.slice(1);
        }

        if (text === '' || bytes(text) > MAX_LENGTH) {
            return null;
        }

        var list = tokens(text);

        if (!list || !list.length) {
            return null;
        }

        var state = { tokens: list, position: 0, depth: 0, language: String(language || 'en') };
        var tree = parseSum(state);

        if (tree === null || state.position !== list.length || !rangesPlaced(tree, false)) {
            return null;
        }

        return tree;
    }

    function area(node) {
        return [
            Math.min(node.from.row, node.to.row),
            Math.min(node.from.column, node.to.column),
            Math.max(node.from.row, node.to.row),
            Math.max(node.from.column, node.to.column)
        ];
    }

    // The areas a formula reads, in written order, each once.
    function cells(node) {
        var seen = {};
        var list = [];

        (function collect(part) {
            var found = null;

            switch (part.type) {
                case 'cell':
                    found = [part.row, part.column, part.row, part.column];
                    break;
                case 'range':
                    found = area(part);
                    break;
                case 'group':
                    collect(part.inner);
                    return;
                case 'unary':
                    collect(part.operand);
                    return;
                case 'binary':
                    collect(part.left);
                    collect(part.right);
                    return;
                case 'function':
                    part.arguments.forEach(collect);
                    return;
                default:
                    return;
            }

            var key = found.join(',');

            if (!own(seen, key)) {
                seen[key] = true;
                list.push(found);
            }
        })(node);

        return list;
    }

    function reads(node, row, column) {
        return cells(node).some(function (part) {
            return row >= part[0] && row <= part[2] && column >= part[1] && column <= part[3];
        });
    }

    function columnName(column) {
        var name = '';

        column += 1;

        while (column > 0) {
            var rest = (column - 1) % 26;

            name = String.fromCharCode(65 + rest) + name;
            column = Math.floor((column - 1) / 26);
        }

        return name;
    }

    function trimZeros(text) {
        return (text.indexOf('.') !== -1) ? text.replace(/0+$/, '').replace(/\.$/, '') : text;
    }

    // As pg_office_formula_number_text(): whole numbers below 1E+15 plain,
    // others with 15 significant digits as sprintf('%.15G') gives them, an
    // exponent of two digits at least.
    function numberText(value) {
        value = Number(value);

        if (value === Math.floor(value) && Math.abs(value) < 1e15) {
            return (value === 0) ? '0' : value.toFixed(0);
        }

        var exponential = value.toExponential(14);
        var exponent = parseInt(exponential.slice(exponential.indexOf('e') + 1), 10);

        if (exponent < -4 || exponent >= 15) {
            var mantissa = trimZeros(exponential.slice(0, exponential.indexOf('e')));
            var digits = String(Math.abs(exponent));

            return mantissa + 'E' + (exponent < 0 ? '-' : '+') + (digits.length < 2 ? '0' + digits : digits);
        }

        return trimZeros(value.toFixed(14 - exponent));
    }

    // A tree as text without "=": Excel's stored form, or with the
    // language's function names for showing.
    function text(node, language) {
        switch (node.type) {
            case 'number':
                return numberText(node.value);
            case 'cell':
                return (node.absolute_column ? '$' : '') + columnName(node.column) + (node.absolute_row ? '$' : '') + node.row;
            case 'range':
                return text(node.from, language) + ':' + text(node.to, language);
            case 'group':
                return '(' + text(node.inner, language) + ')';
            case 'unary':
                return node.op + text(node.operand, language);
            case 'binary':
                return text(node.left, language) + node.op + text(node.right, language);
            case 'function':
                return names(language)[node.name] + '(' + node.arguments.map(function (child) {
                    return text(child, language);
                }).join(',') + ')';
        }

        return '';
    }

    function error(code) {
        return { error: String(code) };
    }

    function isError(value) {
        return value !== null && typeof value === 'object' && own(value, 'error');
    }

    function ungroup(node) {
        while (node.type === 'group') {
            node = node.inner;
        }

        return node;
    }

    function cellValue(sheet, row, column) {
        if (!sheet || !own(sheet, row) || !sheet[row] || typeof sheet[row] !== 'object' || !own(sheet[row], column)) {
            return null;
        }

        var value = sheet[row][column];

        return (value === '' || value === undefined) ? null : value;
    }

    function finite(value) {
        if (!isFinite(value)) {
            return error('#NUM!');
        }

        // A negative zero is written as 0.
        return (value === 0) ? 0 : value;
    }

    // A value as a number for arithmetic, as Excel converts it.
    function number(value) {
        if (isError(value)) {
            return value;
        }

        if (value === null || value === undefined) {
            return 0;
        }

        if (typeof value === 'boolean') {
            return value ? 1 : 0;
        }

        if (typeof value === 'number') {
            return value;
        }

        var match = /^\s*([+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)\s*(%?)\s*$/.exec(String(value));

        if (match) {
            var found = parseFloat(match[1]);

            return finite(match[2] === '%' ? found / 100 : found);
        }

        return error('#VALUE!');
    }

    // The values given inside an area, row by row, left to right, empty
    // ones left out.
    function areaValues(sheet, part) {
        var list = [];
        var rows = Object.keys(sheet || {}).map(Number).filter(function (row) {
            return row === Math.floor(row) && row >= part[0] && row <= part[2] && sheet[row] && typeof sheet[row] === 'object';
        }).sort(function (a, b) { return a - b; });

        rows.forEach(function (row) {
            Object.keys(sheet[row]).map(Number).filter(function (column) {
                var value = sheet[row][column];

                return column === Math.floor(column) && column >= part[1] && column <= part[3] && value !== null && value !== '' && value !== undefined;
            }).sort(function (a, b) { return a - b; }).forEach(function (column) {
                list.push(sheet[row][column]);
            });
        });

        return list;
    }

    function call(node, sheet) {
        var counting = node.name === 'COUNT';
        var numbers = [];

        for (var index = 0; index < node.arguments.length; index++) {
            var argument = ungroup(node.arguments[index]);
            var value;

            if (argument.type === 'cell' || argument.type === 'range') {
                var part = (argument.type === 'cell') ? [argument.row, argument.column, argument.row, argument.column] : area(argument);
                var values = areaValues(sheet, part);

                for (var spot = 0; spot < values.length; spot++) {
                    value = values[spot];

                    if (isError(value)) {
                        if (!counting) {
                            return value;
                        }
                    } else if (typeof value === 'number') {
                        numbers.push(value);
                    }
                }

                continue;
            }

            value = evaluate(argument, sheet);

            if (isError(value)) {
                if (!counting) {
                    return value;
                }

                continue;
            }

            numbers.push(value);
        }

        var total = 0;

        numbers.forEach(function (one) { total += one; });

        switch (node.name) {
            case 'COUNT':
                return numbers.length;
            case 'SUM':
                return finite(total);
            case 'AVERAGE':
                return numbers.length ? finite(total / numbers.length) : error('#DIV/0!');
            case 'MIN':
                return numbers.length ? Math.min.apply(null, numbers) : 0;
            case 'MAX':
                return numbers.length ? Math.max.apply(null, numbers) : 0;
        }

        return error('#VALUE!');
    }

    function evaluate(node, sheet) {
        var left;
        var right;

        switch (node.type) {
            case 'number':
                return node.value;
            case 'cell':
                return number(cellValue(sheet, node.row, node.column));
            case 'group':
                return evaluate(node.inner, sheet);
            case 'unary':
                left = evaluate(node.operand, sheet);

                return (isError(left) || node.op === '+') ? left : finite(-left);
            case 'binary':
                left = evaluate(node.left, sheet);

                if (isError(left)) {
                    return left;
                }

                right = evaluate(node.right, sheet);

                if (isError(right)) {
                    return right;
                }

                switch (node.op) {
                    case '+':
                        return finite(left + right);
                    case '-':
                        return finite(left - right);
                    case '*':
                        return finite(left * right);
                }

                return (right === 0) ? error('#DIV/0!') : finite(left / right);
            case 'function':
                return call(node, sheet);
        }

        return error('#VALUE!');
    }

    // A formula's value; =A1 alone gives the cell as it is (empty is 0).
    function value(tree, sheet) {
        var reference = ungroup(tree);

        if (reference.type === 'cell') {
            var found = cellValue(sheet, reference.row, reference.column);

            if (found === null) {
                return 0;
            }

            return (typeof found === 'number') ? finite(found) : found;
        }

        return evaluate(tree, sheet);
    }

    // A sheet's formulas worked out, dependencies first (Tarjan's strongly
    // connected parts); a circle is #REF!, a formula reading an unknown cell
    // or an unknown result has none (null). `formulas` is ref => [row,
    // column, tree], `values` the other cells, `unknown` { row: { column:
    // true } }. Gives ref => value.
    function results(formulas, values, unknown) {
        var byRow = {};
        var refs = Object.keys(formulas);
        var readsOf = {};
        var blind = {};

        refs.forEach(function (ref) {
            var formula = formulas[ref];

            (byRow[formula[0]] = byRow[formula[0]] || {})[formula[1]] = ref;
        });

        refs.forEach(function (ref) {
            var found = {};
            var order = [];
            var dark = false;

            cells(formulas[ref][2]).forEach(function (part) {
                Object.keys(byRow).forEach(function (row) {
                    row = Number(row);

                    if (row < part[0] || row > part[2]) {
                        return;
                    }

                    Object.keys(byRow[row]).forEach(function (column) {
                        column = Number(column);

                        var other = byRow[row][column];

                        if (column >= part[1] && column <= part[3] && !own(found, other)) {
                            found[other] = true;
                            order.push(other);
                        }
                    });
                });

                Object.keys(unknown || {}).forEach(function (row) {
                    row = Number(row);

                    if (dark || row < part[0] || row > part[2]) {
                        return;
                    }

                    Object.keys(unknown[row]).forEach(function (column) {
                        column = Number(column);

                        if (column >= part[1] && column <= part[3]) {
                            dark = true;
                        }
                    });
                });
            });

            readsOf[ref] = order;
            blind[ref] = dark;
        });

        var index = {};
        var low = {};
        var stack = [];
        var onStack = {};
        var next = 0;
        var parts = [];

        function visit(ref) {
            index[ref] = low[ref] = next++;
            stack.push(ref);
            onStack[ref] = true;

            readsOf[ref].forEach(function (other) {
                if (!own(index, other)) {
                    visit(other);
                    low[ref] = Math.min(low[ref], low[other]);
                } else if (own(onStack, other)) {
                    low[ref] = Math.min(low[ref], index[other]);
                }
            });

            if (low[ref] !== index[ref]) {
                return;
            }

            var part = [];
            var member;

            do {
                member = stack.pop();
                delete onStack[member];
                part.push(member);
            } while (member !== ref);

            parts.push(part);
        }

        refs.forEach(function (ref) {
            if (!own(index, ref)) {
                visit(ref);
            }
        });

        var out = {};
        var sheet = {};

        Object.keys(values || {}).forEach(function (row) {
            sheet[row] = {};
            Object.keys(values[row]).forEach(function (column) {
                sheet[row][column] = values[row][column];
            });
        });

        parts.forEach(function (part) {
            var circle = part.length > 1 || readsOf[part[0]].indexOf(part[0]) !== -1;

            part.forEach(function (ref) {
                var found = null;

                if (circle) {
                    found = error('#REF!');
                } else if (!blind[ref] && readsOf[ref].every(function (other) { return out[other] !== null && out[other] !== undefined; })) {
                    found = value(formulas[ref][2], sheet);
                }

                out[ref] = found;

                if (found !== null) {
                    (sheet[formulas[ref][0]] = sheet[formulas[ref][0]] || {})[formulas[ref][1]] = found;
                }
            });
        });

        return out;
    }

    var api = {
        names: names,
        nameKey: nameKey,
        functionName: functionName,
        tokens: tokens,
        parse: parse,
        cells: cells,
        reads: reads,
        numberText: numberText,
        text: text,
        error: error,
        isError: isError,
        number: number,
        value: value,
        results: results
    };

    if (typeof window !== 'undefined') {
        window.pgOfficeFormula = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
