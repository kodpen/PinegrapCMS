/**
 * Pinegrap - Enterprise Website Platform
 *
 * The rows editor of a collection's rows field on Add and Edit Submitted
 * Form (pg_coll_rows_editor_markup() in includes/fn/collection_rows.php).
 * Each box marked data-pg-rows holds what to draw as JSON: the columns, the
 * rows, the refusals of the last save and the limit. The script draws a
 * plain table with an input per cell, and writes the whole list into the
 * one hidden field beside the box (data-pg-rows-value) on every change: the
 * cells carry no name, so PHP's max_input_vars never cuts a long list.
 *
 * A row is reordered by dragging its grip (SortableJS), by the arrow keys on
 * the grip, or by Move up and Move down.
 *
 * The same editor draws on a designed site form (pg_coll_rows_site_markup()):
 * its config says look "site", and the classes are then Bootstrap 5.3.8's
 * and a date or time is the browser's own input.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    function parse(text, fallback) {
        try {
            var value = JSON.parse(text || '');
            return (value && typeof value === 'object') ? value : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function icon(name) {
        var i = el('i', 'bi ' + name);
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    // The classes of each look: the panel's own parts, or a site's Bootstrap.
    var PANEL_LOOK = {
        icon: 'pg-icon-button pg-icon-button-sm', iconOff: 'opacity-50', actions: '',
        add: 'pg-button pg-button-quiet pg-button-sm', addLabel: 'pg-btn-label', muted: 'fg-secondary', number: 'fg-secondary',
        input: 'form-control form-control-sm', select: 'form-control form-control-sm',
        checkItem: 'd-inline-flex align-items-center gap-1 mb-0', check: 'check', checkLabel: '', nativeDates: false
    };
    /* @pg-site-output */
    var SITE_LOOK = {
        icon: 'btn btn-sm btn-outline-secondary', iconOff: '', actions: 'd-inline-flex gap-1',
        add: 'btn btn-sm btn-outline-primary', addLabel: 'ms-1', muted: 'text-body-secondary', number: 'text-body-secondary ms-1',
        input: 'form-control form-control-sm', select: 'form-select form-select-sm',
        checkItem: 'form-check form-check-inline mb-0', check: 'form-check-input', checkLabel: 'form-check-label', nativeDates: true
    };
    // The look of the box being drawn, set by its draw().
    var look = PANEL_LOOK;

    function iconButton(name, label, className) {
        var button = el('button', look.icon + ' ' + (className || ''));
        button.type = 'button';
        button.setAttribute('aria-label', label);
        button.title = label;
        button.appendChild(icon(name));
        return button;
    }

    // A header cell that keeps its column but shows its name to screen
    // readers only.
    function hiddenHead(text) {
        var th = el('th');
        th.scope = 'col';
        th.appendChild(el('span', 'visually-hidden', text));
        return th;
    }

    // The least width of a cell's input, so a table of several columns
    // scrolls sideways instead of squeezing what was typed.
    var MIN_WIDTH = { text: '11rem', textarea: '14rem', select: '8rem', multiselect: '9rem', date: '10rem', time: '8rem' };

    function setup(box) {
        if (box.getAttribute('data-pg-rows-ready')) return;
        box.setAttribute('data-pg-rows-ready', '1');

        var config = parse(box.getAttribute('data-pg-rows'), {});
        var boxLook = (config.look === 'site') ? SITE_LOOK : PANEL_LOOK;
        var words = parse(box.getAttribute('data-pg-rows-words'), {});
        var columns = Array.isArray(config.columns) ? config.columns : [];
        var max = parseInt(config.max, 10) || 100;
        var hidden = box.parentNode.querySelector('input[data-pg-rows-value]');

        // Each row keeps the refusals of the last save until it is drawn
        // again from a change of its own.
        var rows = (Array.isArray(config.rows) ? config.rows : []).map(function (row) {
            return { code: String(row.code || ''), values: row.values || {}, open: String(row.open || ''), errors: {}, rowError: '' };
        });
        (Array.isArray(config.errors) ? config.errors : []).forEach(function (error) {
            var row = (error.row === null || error.row === undefined) ? null : rows[error.row];
            if (!row) return;
            if (error.field === null || error.field === undefined) row.rowError = String(error.message || '');
            else row.errors[String(error.field)] = String(error.message || '');
        });

        function listColumn(column) {
            return column.input === 'checks' || column.input === 'multiselect';
        }

        function blankValues() {
            var values = {};
            columns.forEach(function (column) { values[String(column.id)] = listColumn(column) ? [] : ''; });
            return values;
        }

        function save() {
            if (!hidden) return;
            hidden.value = JSON.stringify(rows.map(function (row) {
                return { code: row.code, values: row.values };
            }));
        }

        function announce(text) {
            if (typeof window.pgAnnounce === 'function') window.pgAnnounce(text);
        }

        function moveRow(from, to, focusGrip) {
            if (to < 0 || to >= rows.length || from === to) return;
            var row = rows.splice(from, 1)[0];
            rows.splice(to, 0, row);
            draw();
            save();
            var grip = box.querySelectorAll('[data-pg-rows-grip]')[to];
            if (focusGrip && grip) grip.focus();
            announce(String(words.position || '').replace('{position}', to + 1).replace('{total}', rows.length));
        }

        function cellInput(row, column) {
            var key = String(column.id);
            var value = row.values[key];
            var wrap = el('div');
            var input;

            if (column.input === 'checks') {
                var checked = Array.isArray(value) ? value : (value ? [String(value)] : []);
                var group = el('div', 'd-flex flex-wrap gap-3');
                group.setAttribute('role', 'group');
                group.setAttribute('aria-label', column.label);
                column.options.forEach(function (option) {
                    var item = el('label', look.checkItem);
                    var check = el('input', look.check);
                    check.type = 'checkbox';
                    check.value = option.value;
                    check.checked = checked.indexOf(option.value) !== -1;
                    check.addEventListener('change', function () {
                        row.values[key] = Array.prototype.map.call(group.querySelectorAll('input:checked'), function (c) { return c.value; });
                        save();
                    });
                    item.appendChild(check);
                    item.appendChild(el('span', look.checkLabel, option.label));
                    group.appendChild(item);
                });
                wrap.appendChild(group);
                input = group;
            } else if (column.input === 'select' || column.input === 'multiselect') {
                input = el('select', look.select);
                if (column.input === 'multiselect') {
                    input.multiple = true;
                } else {
                    input.appendChild(el('option', '', words.choose || '')).value = '';
                }
                var chosen = Array.isArray(value) ? value : [String(value || '')];
                column.options.forEach(function (option) {
                    var opt = el('option', '', option.label);
                    opt.value = option.value;
                    opt.selected = chosen.indexOf(option.value) !== -1;
                    input.appendChild(opt);
                });
                input.addEventListener('change', function () {
                    row.values[key] = input.multiple
                        ? Array.prototype.map.call(input.selectedOptions, function (o) { return o.value; })
                        : input.value;
                    save();
                });
                wrap.appendChild(input);
            } else {
                input = el(column.input === 'textarea' ? 'textarea' : 'input', look.input);
                if (column.input === 'textarea') {
                    input.rows = 2;
                } else if (look.nativeDates && (column.input === 'date' || column.input === 'time')) {
                    // A site has no panel picker: the browser's own input,
                    // which posts an ISO date or a 24-hour time.
                    input.type = (column.input === 'date') ? 'date' : 'time';
                } else {
                    // A date or time is a text box with the panel's hook,
                    // drawn by pgFields as the record's own field is.
                    input.type = 'text';
                    if (column.hook) {
                        input.setAttribute(column.hook, '');
                        input.maxLength = (column.input === 'date') ? 10 : 11;
                    }
                }
                input.value = Array.isArray(value) ? String(value[0] || '') : String(value || '');
                var keep = function () {
                    row.values[key] = input.value;
                    save();
                };
                input.addEventListener('input', keep);
                input.addEventListener('change', keep);
                wrap.appendChild(input);
            }

            if (MIN_WIDTH[column.input]) input.style.minWidth = MIN_WIDTH[column.input];
            input.setAttribute('aria-label', column.label);
            if (column.required) input.setAttribute('aria-required', 'true');
            if (row.errors[key]) {
                input.classList.add('is-invalid');
                var feedback = el('div', 'invalid-feedback d-block', row.errors[key]);
                wrap.appendChild(feedback);
            }
            return wrap;
        }

        var holder = el('div');
        box.appendChild(holder);
        var sortable = null;

        function draw() {
            look = boxLook;
            if (sortable) { sortable.destroy(); sortable = null; }
            holder.innerHTML = '';

            if (!rows.length) {
                holder.appendChild(el('p', look.muted + ' mb-3', words.empty || ''));
            } else {
                var scroller = el('div', 'table-responsive');
                var table = el('table', 'table table-sm align-middle mb-3');
                var head = el('thead');
                var headRow = el('tr');
                headRow.appendChild(hiddenHead(words.row || ''));
                columns.forEach(function (column) {
                    var th = el('th', '', column.label + (column.required ? '*' : ''));
                    th.scope = 'col';
                    headRow.appendChild(th);
                });
                headRow.appendChild(hiddenHead(words.actions || ''));
                head.appendChild(headRow);
                table.appendChild(head);

                var body = el('tbody');
                rows.forEach(function (row, index) {
                    var tr = el('tr');
                    tr.setAttribute('data-pg-rows-index', String(index));

                    var gripCell = el('td', 'text-nowrap');
                    var grip = iconButton('bi-grip-vertical', words.drag || '', 'pg-coll-rows-grip');
                    grip.setAttribute('data-pg-rows-grip', '');
                    grip.addEventListener('keydown', function (event) {
                        if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') { event.preventDefault(); moveRow(index, index - 1, true); }
                        if (event.key === 'ArrowDown' || event.key === 'ArrowRight') { event.preventDefault(); moveRow(index, index + 1, true); }
                    });
                    gripCell.appendChild(grip);
                    gripCell.appendChild(el('span', look.number, String(index + 1)));
                    if (row.rowError) gripCell.appendChild(el('div', 'invalid-feedback d-block', row.rowError));
                    tr.appendChild(gripCell);

                    columns.forEach(function (column) {
                        var td = el('td');
                        td.appendChild(cellInput(row, column));
                        tr.appendChild(td);
                    });

                    var cell = el('td', 'text-nowrap text-end');
                    var actions = look.actions ? cell.appendChild(el('div', look.actions)) : cell;
                    var up = iconButton('bi-arrow-up', words.up || '');
                    up.disabled = index === 0;
                    // The icon button recipe has no disabled look of its own.
                    if (up.disabled && look.iconOff) up.classList.add(look.iconOff);
                    up.addEventListener('click', function () { moveRow(index, index - 1, false); });
                    var down = iconButton('bi-arrow-down', words.down || '');
                    down.disabled = index === rows.length - 1;
                    if (down.disabled && look.iconOff) down.classList.add(look.iconOff);
                    down.addEventListener('click', function () { moveRow(index, index + 1, false); });
                    actions.appendChild(up);
                    actions.appendChild(down);
                    if (row.open) {
                        var open = el('a', look.icon);
                        open.href = row.open;
                        open.target = '_blank';
                        open.rel = 'noopener';
                        open.title = words.open || '';
                        open.setAttribute('aria-label', words.open || '');
                        open.appendChild(icon('bi-box-arrow-up-right'));
                        actions.appendChild(open);
                    }
                    var remove = iconButton('bi-trash', words.remove || '');
                    remove.addEventListener('click', function () {
                        rows.splice(index, 1);
                        draw();
                        save();
                    });
                    actions.appendChild(remove);
                    tr.appendChild(cell);

                    body.appendChild(tr);
                });
                table.appendChild(body);
                scroller.appendChild(table);
                holder.appendChild(scroller);

                if (typeof window.Sortable !== 'undefined') {
                    sortable = window.Sortable.create(body, {
                        handle: '.pg-coll-rows-grip',
                        animation: 150,
                        onEnd: function (event) {
                            if (event.oldIndex === event.newIndex) return;
                            var row = rows.splice(event.oldIndex, 1)[0];
                            rows.splice(event.newIndex, 0, row);
                            draw();
                            save();
                        }
                    });
                }
            }

            if (Array.isArray(config.others) && config.others.length) {
                holder.appendChild(el('p', 'small ' + look.muted + ' mb-3', (words.others || '') + ' ' + config.others.join(', ')));
            }

            // The date and time cells just drawn get the panel's control.
            if (window.pgFields && typeof window.pgFields.init === 'function') window.pgFields.init(holder);

            var add = el('button', look.add);
            add.type = 'button';
            add.appendChild(icon('bi-plus-lg'));
            add.appendChild(el('span', look.addLabel, words.add || ''));
            if (rows.length >= max) {
                add.disabled = true;
                add.title = words.full || '';
            }
            add.addEventListener('click', function () {
                if (rows.length >= max) return;
                rows.push({ code: '', values: blankValues(), open: '', errors: {}, rowError: '' });
                draw();
                save();
                var inputs = holder.querySelectorAll('tbody tr:last-child input, tbody tr:last-child select, tbody tr:last-child textarea');
                if (inputs.length) inputs[0].focus();
            });
            holder.appendChild(add);
        }

        draw();
        save();
    }

    function setupAll() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-pg-rows]'), setup);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupAll);
    else setupAll();
})();
