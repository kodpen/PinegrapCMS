/**
 * Pinegrap - Enterprise Website Platform
 *
 * Cells changed in the grid of one Data table (workspace_wtables.php?id=N),
 * for a person who may write the table. A double click, Enter or F2 on a
 * cell the server marked (td[data-ws-wt-edit]: text, number, money, date,
 * checkbox, status) turns it into its field; Enter or leaving the field
 * saves that one cell with the row's revision through the screen's
 * row_cell action, Escape gives it up, Tab and Shift+Tab save and go to the
 * next or the previous cell. The answer draws the row's cells as they are
 * now; a refusal stands under the field in the store's words, and a row
 * somebody changed meanwhile is drawn as it is now with that said under the
 * cell. Any other cell opens the row drawer, as a single click on a row
 * does; the arrow keys move between the cells.
 *
 * sameValue() and step() are pure (tests/workspace_wtable_grid_js_test.php
 * runs them in Node). Its own bundle, workspace_wtable_grid.min.js
 * (pg_js_bundles(), includes/fn/output.php), printed by this screen only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // ------------------------------------------------------------------
    // Pure parts.
    // ------------------------------------------------------------------

    /**
     * Is the value in the field the one the cell opened with? A box is
     * ticked or not; other values count without the spaces around them,
     * which the server drops.
     */
    function sameValue(kind, before, after) {
        if (kind === 'checkbox') {
            return (String(before) === '1') === (String(after) === '1');
        }

        return String(before == null ? '' : before).trim() === String(after == null ? '' : after).trim();
    }

    /**
     * The place of the cell Tab (1) or Shift+Tab (-1) goes to among count
     * cells from the one at index; -1 past either end.
     */
    function step(count, index, direction) {
        var next = index + (direction < 0 ? -1 : 1);

        return (index < 0 || next < 0 || next >= count) ? -1 : next;
    }

    var pure = { sameValue: sameValue, step: step };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = pure;
    }

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    window.PGWsWtableGrid = pure;

    // ------------------------------------------------------------------
    // The grid.
    // ------------------------------------------------------------------

    var node = document.getElementById('ws-wt-grid-config');
    var grid = document.querySelector('.ws-wt-grid');

    if (!node || !grid) {
        return;
    }

    var config = JSON.parse(node.textContent);
    var texts = config.texts || {};
    var editing = null;
    var pending = Promise.resolve(true);
    var openTimer = 0;

    // What a save says, read aloud; the keys of a field, named by it.
    var live = document.createElement('div');
    live.className = 'visually-hidden';
    live.setAttribute('role', 'status');
    live.setAttribute('aria-live', 'polite');
    grid.parentNode.insertBefore(live, grid.nextSibling);

    var keys = document.createElement('div');
    keys.className = 'visually-hidden';
    keys.id = 'ws-wt-grid-keys';
    keys.textContent = texts.keys || '';
    grid.parentNode.insertBefore(keys, live);

    function say(text) {
        live.textContent = '';
        window.setTimeout(function () { live.textContent = text || ''; }, 30);
    }

    function editableCells() {
        return Array.prototype.slice.call(grid.querySelectorAll('tbody td[data-ws-wt-edit]'));
    }

    function openRow(row) {
        window.clearTimeout(openTimer);

        // A save on its way first: a refused one stays on the screen in
        // its field.
        pending.then(function () {
            if (!editing && row.dataset.wsWtOpen) {
                window.location.href = row.dataset.wsWtOpen;
            }
        });
    }

    function makeField(cell) {
        var kind = cell.dataset.wsWtEdit;
        var column = (config.columns || {})[cell.dataset.wsWtCol] || {};
        var value = cell.dataset.wsWtValue || '';
        var field;

        if (kind === 'status') {
            field = document.createElement('select');
            field.className = 'pg-input pg-input-sm';
            field.appendChild(new Option(texts.none || '', ''));
            (column.choices || []).forEach(function (choice) {
                field.appendChild(new Option(choice.label, choice.key));
            });
            field.value = value;
        } else if (kind === 'checkbox') {
            field = document.createElement('input');
            field.type = 'checkbox';
            field.className = 'check';
            field.checked = (value === '1');
        } else {
            field = document.createElement('input');
            field.type = (kind === 'date') ? 'date' : ((kind === 'datetime') ? 'datetime-local' : 'text');
            field.className = 'pg-input pg-input-sm';
            field.autocomplete = 'off';
            field.value = value;

            if (kind === 'number' || kind === 'money') {
                field.inputMode = 'decimal';
            }
        }

        field.setAttribute('aria-label', column.currency ? column.name + ' (' + column.currency + ')' : (column.name || ''));
        field.setAttribute('aria-describedby', keys.id);

        return field;
    }

    function fieldValue(state) {
        return (state.kind === 'checkbox') ? (state.field.checked ? '1' : '0') : state.field.value;
    }

    function dropNotes(cell) {
        cell.querySelectorAll('.ws-wt-cell-note').forEach(function (note) { note.remove(); });
    }

    function startEdit(cell) {
        if (!cell || !cell.dataset.wsWtEdit || (editing && editing.cell === cell)) {
            return;
        }

        if (editing) {
            // Another cell open: it is saved first, and this one opens
            // once it is.
            if (!editing.busy) {
                finish(editing, 0);
            }

            if (editing) {
                pending.then(function () {
                    if (!editing) {
                        startEdit(cell);
                    }
                });

                return;
            }
        }

        window.clearTimeout(openTimer);
        dropNotes(cell);

        var state = { cell: cell, kind: cell.dataset.wsWtEdit, view: cell.innerHTML, busy: false };
        state.field = makeField(cell);
        cell.textContent = '';
        cell.classList.add('ws-wt-editing');
        cell.appendChild(state.field);
        editing = state;
        state.field.focus();

        if (state.field.type === 'text') {
            state.field.select();
        }
    }

    // The cell as it was drawn, the field gone.
    function close(state, focus) {
        if (editing === state) {
            editing = null;
        }

        state.cell.classList.remove('ws-wt-editing');
        state.cell.innerHTML = state.view;

        if (focus) {
            state.cell.focus();
        }
    }

    // The row's cells as the server drew them now, but the one whose field
    // still holds a refused value.
    function redraw(row, answer, keep) {
        if (typeof answer.revision === 'number') {
            row.dataset.wsWtRevision = String(answer.revision);
        }

        Object.keys(answer.cells || {}).forEach(function (id) {
            var old = row.querySelector('td[data-ws-wt-col="' + id + '"]');

            if (!old || old === keep) {
                return;
            }

            var holder = document.createElement('tbody');
            holder.innerHTML = '<tr>' + answer.cells[id] + '</tr>';

            var cell = holder.querySelector('td');

            if (cell) {
                old.replaceWith(cell);
            }
        });
    }

    function goTo(row, columnId, direction) {
        var cells = editableCells();
        var at = row ? row.querySelector('td[data-ws-wt-col="' + columnId + '"]') : null;
        var next = step(cells.length, cells.indexOf(at), direction);

        if (next >= 0) {
            startEdit(cells[next]);
        } else if (at) {
            at.focus();
        }
    }

    function post(row, state, value) {
        var body = new FormData();
        body.append('token', config.token);
        body.append('wt_action', 'row_cell');
        body.append('wt_table', config.table);
        body.append('wt_column', state.cell.dataset.wsWtCol);
        body.append('wt_row', row.dataset.wsWtRow);
        body.append('revision', row.dataset.wsWtRevision || '0');
        body.append('value', value);

        return fetch(config.url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .catch(function () { return { ok: false, error: '', message: texts.error || '' }; });
    }

    // Saves the field's cell when its value changed; direction 1 or -1
    // then opens the next or the previous cell, 0 leaves the focus on the
    // cell, null (the field left by the pointer) leaves it where it went.
    function finish(state, direction) {
        if (state.busy) {
            return;
        }

        // A refused value left again unchanged is not sent again.
        if (direction === null && state.refused === fieldValue(state)) {
            return;
        }

        var row = state.cell.closest('tr');
        var columnId = state.cell.dataset.wsWtCol;
        var value = fieldValue(state);

        if (sameValue(state.kind, state.cell.dataset.wsWtValue || '', value)) {
            close(state, direction === 0);

            if (direction) {
                goTo(row, columnId, direction);
            }

            return;
        }

        state.busy = true;
        state.cell.setAttribute('aria-busy', 'true');
        state.field.readOnly = true;
        say(texts.saving);

        pending = post(row, state, value).then(function (answer) {
            state.busy = false;
            state.cell.removeAttribute('aria-busy');
            state.field.readOnly = false;

            if (answer.ok || answer.error === 'changed_meanwhile') {
                // Saved, or somebody changed the row meanwhile: the row as
                // it is now, the field gone.
                if (editing === state) {
                    editing = null;
                }

                redraw(row, answer, null);

                var cell = row.querySelector('td[data-ws-wt-col="' + columnId + '"]');

                if (!answer.ok && cell) {
                    var note = document.createElement('div');
                    note.className = 'ws-wt-cell-note small fg-danger';
                    note.textContent = answer.message || '';
                    cell.appendChild(note);
                }

                say(answer.ok ? texts.saved : answer.message);

                if (answer.ok && direction) {
                    goTo(row, columnId, direction);
                } else if (cell && direction !== null) {
                    cell.focus();
                }

                return !!answer.ok;
            }

            // Refused: the typed value stays in its field, the reason
            // under it.
            state.refused = value;

            var message = state.cell.querySelector('.ws-wt-cell-note');

            if (!message) {
                message = document.createElement('div');
                message.className = 'ws-wt-cell-note invalid-feedback d-block';
                message.id = 'ws-wt-cell-note-' + row.dataset.wsWtRow + '-' + columnId;
                state.cell.appendChild(message);
            }

            message.textContent = answer.message || texts.error || '';
            state.field.classList.add('is-invalid');
            state.field.setAttribute('aria-invalid', 'true');
            state.field.setAttribute('aria-describedby', message.id + ' ' + keys.id);
            say(answer.message);

            if (editing !== state) {
                editing = state;
            }

            // Left by the pointer: the focus stays where it went.
            if (direction !== null) {
                state.field.focus();
            }

            return false;
        });
    }

    // The field's keys: Enter saves, Escape gives it up, Tab moves on.
    grid.addEventListener('keydown', function (e) {
        var state = editing;

        if (!state || e.target !== state.field) {
            return;
        }

        if (e.key === 'Enter') {
            e.preventDefault();
            finish(state, 0);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();

            if (!state.busy) {
                close(state, true);
            }
        } else if (e.key === 'Tab') {
            e.preventDefault();
            finish(state, e.shiftKey ? -1 : 1);
        }
    });

    // A field left for anything outside its cell is saved.
    grid.addEventListener('focusout', function (e) {
        var state = editing;

        if (!state || e.target !== state.field || (e.relatedTarget && state.cell.contains(e.relatedTarget))) {
            return;
        }

        finish(state, null);
    });

    // A cell's keys: Enter or F2 turns it into its field (or opens the row
    // drawer for a cell the grid does not change); the arrows move.
    grid.addEventListener('keydown', function (e) {
        var cell = e.target.closest ? e.target.closest('td[data-ws-wt-col]') : null;

        if (!cell || e.target !== cell) {
            return;
        }

        var row = cell.closest('tr');

        if (e.key === 'Enter' || e.key === 'F2') {
            e.preventDefault();

            if (cell.dataset.wsWtEdit) {
                startEdit(cell);
            } else {
                openRow(row);
            }

            return;
        }

        var target = null;

        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            var cells = Array.prototype.slice.call(row.querySelectorAll('td[data-ws-wt-col]'));
            target = cells[cells.indexOf(cell) + (e.key === 'ArrowLeft' ? -1 : 1)] || null;
        } else if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            var other = (e.key === 'ArrowUp') ? row.previousElementSibling : row.nextElementSibling;
            target = other ? other.querySelector('td[data-ws-wt-col="' + cell.dataset.wsWtCol + '"]') : null;
        }

        if (target) {
            e.preventDefault();
            target.focus();
        }
    });

    // From the grid's box or a row's number, an arrow goes into the cells.
    grid.addEventListener('keydown', function (e) {
        if (!/^Arrow(Down|Right)$/.test(e.key) || (e.target !== grid && !e.target.matches('a.ws-wt-open'))) {
            return;
        }

        var from = (e.target === grid) ? grid.querySelector('tbody tr') : e.target.closest('tr');
        var first = from ? from.querySelector('td[data-ws-wt-col]') : null;

        if (first) {
            e.preventDefault();
            first.focus();
        }
    });

    // A click on a row opens it in the row drawer. On a cell the grid
    // changes it waits for a second click, which turns the cell into its
    // field instead. A link or button of the row, a field, or a click that
    // ends a text selection keeps its own meaning.
    function editableTarget(e) {
        var cell = e.target.closest('td[data-ws-wt-edit]');

        return (cell && cell.closest('tr[data-ws-wt-open]') && !e.target.closest('a, button, input, select, textarea, label')) ? cell : null;
    }

    // A double click on such a cell selects no word of it.
    grid.addEventListener('mousedown', function (e) {
        if (e.detail >= 2 && editableTarget(e)) {
            e.preventDefault();
        }
    });

    grid.addEventListener('click', function (e) {
        var row = e.target.closest('tr[data-ws-wt-open]');

        if (!row || e.target.closest('a, button, input, select, textarea, label, .ws-wt-handle')) {
            return;
        }

        var cell = editableTarget(e);

        if (cell && e.detail >= 2) {
            window.clearTimeout(openTimer);
            startEdit(cell);

            return;
        }

        if (window.getSelection && String(window.getSelection()) !== '') {
            return;
        }

        if (cell) {
            window.clearTimeout(openTimer);
            cell.focus();
            openTimer = window.setTimeout(function () { openRow(row); }, 400);

            return;
        }

        openRow(row);
    });
})();
