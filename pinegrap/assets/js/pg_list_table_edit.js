/**
 * Pinegrap - Enterprise Website Platform
 *
 * Quick edit mode of the panel's list table driver (pg_list_table.js). A
 * list whose source names a quick edit (pg_list_table_html() prints
 * data-pg-edit-url and data-pg-edit-columns, pg_list_edit_table()) gets a
 * "Quick edit" button in its bar; it and F2 turn the mode on and off.
 *
 * With the mode on, every cell of an editable column that carries its
 * data-pg-edit field is a field. A changed field is saved as soon as it is
 * left (or on Enter, which goes on to the same column of the next row;
 * in a several-line field Enter starts a new line and Ctrl+Enter goes on;
 * a several-choice field is a group of check boxes, saved when the focus
 * leaves the group); Escape puts the stored value back. The value goes to the screen's
 * pg_list_edit_request() endpoint, one cell per request, and the answer
 * draws the cell (and the other cells of the row it names) from what was
 * stored. A refused value stays in the field, marked, with the reason
 * under it; nothing is saved.
 *
 * The last save can be undone from the bar. A value saved on a selected
 * row can be applied to the other selected rows on screen ("Apply to N
 * selected rows"): one request per row; that group is then the last save.
 * With the mode off, a click selects and a double-click opens, as always.
 *
 * QuickEdits, applyTargets(), quickValue(), quickChanged() and
 * rowNameText() are pure (no browser; tests/list_table_quick_edit_js_test.php
 * runs them in Node).
 *
 * Bundled after pg_list_table.js into pg_list_table.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var T = window.PGListTable;

    if (!T || !T.Driver) {
        return;
    }

    // ------------------------------------------------------------------
    // Pure parts.
    // ------------------------------------------------------------------

    /**
     * The last save, for its undo: one change, or the group an "Apply to
     * the selected rows" wrote. A save that changed nothing keeps the one
     * before.
     */
    function QuickEdits() {
        this.last = null;
    }

    /** changes: list of {id, field, before, after}. */
    QuickEdits.prototype.record = function (changes) {
        var kept = (changes || []).filter(function (change) {
            return String(change.before) !== String(change.after);
        });

        if (kept.length) {
            this.last = kept;
        }
    };

    QuickEdits.prototype.undoable = function () {
        return !!this.last;
    };

    /** What to write back to undo the last save; it can be taken once. */
    QuickEdits.prototype.takeUndo = function () {
        var last = this.last || [];

        this.last = null;

        return last.map(function (change) {
            return { id: String(change.id), field: change.field, value: String(change.before) };
        });
    };

    QuickEdits.prototype.drop = function () {
        this.last = null;
    };

    /**
     * The rows a value saved on sourceId goes on to: the other selected
     * rows on screen whose cell can change, in screen order; none when the
     * saved row is not in the selection.
     *
     * @param rows      list of {id, editable}
     * @param selection a PGListTable.Selection
     * @param sourceId  the row the value was saved on
     */
    function applyTargets(rows, selection, sourceId) {
        sourceId = String(sourceId);

        if (!selection.has(sourceId)) {
            return [];
        }

        return rows.filter(function (row) {
            return row.editable && (String(row.id) !== sourceId) && selection.has(row.id);
        }).map(function (row) {
            return String(row.id);
        });
    }

    /**
     * The value a field sends: trimmed, a choice as it is; several lines
     * with their breaks as "\n" (a stored value may carry "\r\n").
     */
    function quickValue(type, raw) {
        if (type === 'select') {
            return String(raw);
        }

        if (type === 'textarea') {
            return String(raw).replace(/\r\n?/g, '\n').trim();
        }

        if (type === 'multiselect') {
            return quickChoices(raw).join(',');
        }

        return String(raw).trim();
    }

    /** A several-choice value ("3, 7,3") as its values, each once ([3, 7]). */
    function quickChoices(raw) {
        var seen = {};

        return String(raw).split(',').map(function (part) {
            return part.trim();
        }).filter(function (part) {
            if ((part === '') || seen[part]) {
                return false;
            }

            seen[part] = true;

            return true;
        });
    }

    /** Whether a field holds something else than the stored value. */
    function quickChanged(type, raw, stored) {
        if (type === 'multiselect') {
            return quickChoices(raw).sort().join(',') !== quickChoices(stored).sort().join(',');
        }

        return quickValue(type, raw) !== ((type === 'textarea') ? quickValue(type, stored) : String(stored));
    }

    /**
     * The text a cell names its row by, as shown: words of a block child
     * kept apart (textContent fused a gift card's code with the
     * "Expired" printed under it) and hidden text left out (unseen search
     * words, icon ligatures). Reads only childNodes, nodeType, nodeValue,
     * tagName, hidden and getAttribute, so it runs on any node tree.
     */
    /**
     * A select whose options carry classes wears the chosen option's class
     * (the Pages list's folders, coloured by their access type), dropping
     * the one it wore before.
     */
    function tintSelect(select) {
        var option = (select.selectedIndex >= 0) ? select.options[select.selectedIndex] : null;
        var tint = option ? String(option.className || '') : '';

        if (select.pgTint && (select.pgTint !== tint)) {
            select.classList.remove(select.pgTint);
        }

        if (tint !== '') {
            select.classList.add(tint);
        }

        select.pgTint = tint;
    }

    function rowNameText(node) {
        var walk = function (parent) {
            var text = '';

            Array.prototype.forEach.call(parent.childNodes || [], function (child) {
                if (child.nodeType === 3) {
                    text += child.nodeValue;
                } else if ((child.nodeType === 1) && !child.hidden && (child.getAttribute('aria-hidden') !== 'true')) {
                    var gap = /^(DIV|P|BR|LI|UL|OL|DL|DT|DD|H[1-6])$/.test(String(child.tagName).toUpperCase()) ? ' ' : '';

                    text += gap + walk(child) + gap;
                }
            });

            return text;
        };

        return node ? walk(node).replace(/\s+/g, ' ').trim() : '';
    }

    // ------------------------------------------------------------------
    // The mode on a driver.
    // ------------------------------------------------------------------

    var D = T.Driver.prototype;

    function quote(value) {
        return String(value).replace(/["\\]/g, '');
    }

    /** Started for each driver, once; nothing for a list with no quick edit. */
    D.quickInit = function () {
        var self = this;
        var root = this.root;

        if (this.quickReady) {
            return;
        }

        this.quickReady = true;
        this.editUrl = root.getAttribute('data-pg-edit-url') || '';
        this.button = root.querySelector('.pg-list-table-quick');

        try {
            this.editDefs = JSON.parse(root.getAttribute('data-pg-edit-columns') || '{}') || {};
        } catch (e) {
            this.editDefs = {};
        }

        if (!this.editUrl || !this.button) {
            return;
        }

        this.quickOn = false;
        this.edits = new QuickEdits();
        this.applyPending = null;

        this.quickUndoButton = this.barButton('pg-list-table-quick-undo', this.texts.undoChange);
        this.applyButton = this.barButton('pg-list-table-quick-apply', '');

        this.button.addEventListener('click', function () {
            self.setQuick(!self.quickOn);
        });

        this.quickUndoButton.addEventListener('click', function () {
            self.quickUndo();
        });

        this.applyButton.addEventListener('click', function () {
            self.quickApply();
        });

        root.addEventListener('pg-list-table:selection', function () {
            self.drawApply();
        });

        // The keyboard stays on the rows: a cell is reached through its row,
        // not as a stop of its own (pg_list_edit_cell() gives it one).
        Array.prototype.forEach.call(this.tbody.querySelectorAll('td[data-pg-edit]'), function (cell) {
            cell.removeAttribute('tabindex');
        });
    };

    D.barButton = function (name, label) {
        var button = document.createElement('button');

        button.type = 'button';
        button.className = 'btn btn-sm btn-outline theme-secondary ' + name;
        button.textContent = label || '';
        button.hidden = true;
        this.bar.insertBefore(button, this.bar.querySelector('.pg-list-table-tools'));

        return button;
    };

    /** The definition of a cell's field, when its column is editable. */
    D.editDef = function (cell) {
        var field = cell.getAttribute('data-pg-edit');
        var key = cell.getAttribute('data-pg-col');
        var column = null;

        this.model.forEach(function (item) {
            if (item.key === key) {
                column = item;
            }
        });

        if (!field || !column || !column.editable || !Object.prototype.hasOwnProperty.call(this.editDefs, field)) {
            return null;
        }

        return this.editDefs[field];
    };

    D.setQuick = function (on) {
        var self = this;

        if (!this.editUrl || (on === this.quickOn)) {
            return;
        }

        // A field being typed in is saved before it leaves the page.
        var active = document.activeElement;
        var activeCell = (active && active.closest) ? active.closest('td[data-pg-edit]') : null;

        if (!on && activeCell && activeCell.pgQuick && this.tbody.contains(activeCell)) {
            this.quickSave(activeCell);
        }

        this.quickOn = on;
        this.root.classList.toggle('is-quick-edit', on);
        this.button.setAttribute('aria-pressed', on ? 'true' : 'false');

        Array.prototype.forEach.call(this.tbody.rows, function (row) {
            self.quickRow(row);
        });

        if (!on) {
            this.applyPending = null;
            this.drawApply();

            if (activeCell) {
                var row = activeCell.closest('tr');

                if (row) {
                    row.focus();
                }
            }
        }

        this.say(on ? this.texts.quickOn : this.texts.quickOff);
    };

    /** Rows loaded while the mode is on get their fields too. */
    D.quickRows = function (rows) {
        var self = this;

        if (!this.editUrl) {
            return;
        }

        rows.forEach(function (row) {
            Array.prototype.forEach.call(row.querySelectorAll('td[data-pg-edit]'), function (cell) {
                cell.removeAttribute('tabindex');
            });
            self.quickRow(row);
        });
    };

    D.quickRow = function (row) {
        var self = this;

        Array.prototype.forEach.call(row.querySelectorAll('td[data-pg-edit]'), function (cell) {
            if (self.quickOn) {
                self.openField(cell);
            } else {
                self.closeField(cell);
            }
        });
    };

    /**
     * A several-choice field: a group of check boxes, one per option, that
     * reads and writes its value ("3,7", in the options' order) like an
     * input, and takes the focus on its first box.
     */
    function choiceGroup(def, value) {
        var group = document.createElement('div');
        var chosen = quickChoices(value);
        var boxes = [];

        group.className = 'pg-list-table-field pg-list-table-choices';
        group.setAttribute('role', 'group');
        // Safari, and Firefox on macOS, do not focus a clicked or tapped
        // check box: the group itself takes the focus then, so leaving it
        // still saves the boxes ticked.
        group.tabIndex = -1;

        (def.options || []).forEach(function (pair) {
            var label = document.createElement('label');
            var box = document.createElement('input');

            label.className = 'pg-list-table-choice';
            box.type = 'checkbox';
            box.className = 'check me-1';
            box.value = String(pair[0]);
            box.checked = (chosen.indexOf(box.value) !== -1);
            label.appendChild(box);
            label.appendChild(document.createTextNode(String(pair[1])));
            group.appendChild(label);
            boxes.push(box);
        });

        Object.defineProperty(group, 'value', {
            get: function () {
                return boxes.filter(function (box) {
                    return box.checked;
                }).map(function (box) {
                    return box.value;
                }).join(',');
            },
            set: function (next) {
                var values = quickChoices(next);

                boxes.forEach(function (box) {
                    box.checked = (values.indexOf(box.value) !== -1);
                });
            }
        });

        group.focus = function () {
            if (boxes.length) {
                boxes[0].focus();
            }
        };

        return group;
    }

    D.openField = function (cell) {
        var self = this;
        var def = this.editDef(cell);

        if (!def || cell.pgQuick) {
            return;
        }

        var value = cell.getAttribute('data-pg-edit-value') || '';
        var control;
        var row = cell.closest('tr');
        var name = row ? row.querySelector('td') : null;

        if (def.type === 'select') {
            control = document.createElement('select');
            control.className = 'form-control form-control-sm pg-list-table-field';

            (def.options || []).forEach(function (pair) {
                var option = document.createElement('option');

                option.value = String(pair[0]);
                option.textContent = String(pair[1]);
                option.selected = (option.value === value);

                if (pair[2]) {
                    option.className = String(pair[2]);
                }

                control.appendChild(option);
            });

            tintSelect(control);
        } else if (def.type === 'multiselect') {
            control = choiceGroup(def, value);
        } else if (def.type === 'textarea') {
            control = document.createElement('textarea');
            control.className = 'form-control form-control-sm pg-list-table-field pg-list-table-field-lines';
            control.rows = 2;
            control.value = quickValue('textarea', value);
            control.autocomplete = 'off';

            if (def.max) {
                control.maxLength = def.max;
            }
        } else {
            control = document.createElement('input');
            control.type = 'text';
            control.className = 'form-control form-control-sm pg-list-table-field' + ((def.type === 'text') ? '' : ' text-end');
            control.value = value;
            control.autocomplete = 'off';
            control.setAttribute('enterkeyhint', 'next');

            if (def.type === 'integer') {
                control.inputMode = 'numeric';
            } else if (def.type === 'money') {
                control.inputMode = 'decimal';
            } else if (def.max) {
                control.maxLength = def.max;
            }
        }

        // The row's name is its first cell's text. That cell may be an open
        // field itself (quick edit opens a row's fields in order), so its text
        // is the one kept when it opened, not the empty text it has now.
        var nameText = name ? (name.pgQuick ? (name.pgQuickName || '') : rowNameText(name)) : '';

        // No name: the field never travels with a form the list sits in.
        control.setAttribute('aria-label', String(def.label || '') + ((name && (name !== cell) && (nameText !== '')) ? ': ' + nameText : ''));

        var note = document.createElement('div');

        note.className = 'pg-list-table-field-note';
        note.id = 'pg-quick-' + Math.random().toString(36).slice(2);
        note.hidden = true;
        control.setAttribute('aria-describedby', note.id);

        cell.pgQuickHtml = cell.innerHTML;
        cell.pgQuickName = rowNameText(cell);
        cell.pgQuick = { control: control, note: note, def: def };
        cell.textContent = '';
        cell.appendChild(control);
        cell.appendChild(note);

        control.addEventListener('change', function () {
            if (control.tagName === 'SELECT') {
                tintSelect(control);
            }

            // A box ticked is not yet the choice: the group saves once the
            // focus leaves it.
            if (def.type !== 'multiselect') {
                self.quickSave(cell);
            }
        });

        if (def.type === 'multiselect') {
            control.addEventListener('focusout', function (event) {
                if (!event.relatedTarget || !control.contains(event.relatedTarget)) {
                    self.quickSave(cell);
                }
            });
        }

        control.addEventListener('keydown', function (event) {
            self.fieldKey(cell, event);
        });
    };

    D.closeField = function (cell) {
        if (!cell.pgQuick) {
            return;
        }

        cell.innerHTML = cell.pgQuickHtml;
        cell.classList.remove('is-invalid');
        delete cell.pgQuick;
        delete cell.pgQuickHtml;
    };

    /** The keys of a field; the rows' own keys (Space, arrows, Ctrl+A) stay out. */
    D.fieldKey = function (cell, event) {
        var quick = cell.pgQuick;

        event.stopPropagation();

        if ((event.key === 'Enter') && (quick.control.tagName === 'TEXTAREA') && !event.ctrlKey && !event.metaKey) {
            // A new line in the field; Ctrl+Enter goes on as Enter elsewhere.
            return;
        } else if (event.key === 'Enter') {
            event.preventDefault();
            this.quickSave(cell);
            this.fieldStep(cell, event.shiftKey ? -1 : 1);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            quick.control.value = (quick.control.tagName === 'TEXTAREA') ? quickValue('textarea', cell.getAttribute('data-pg-edit-value') || '') : (cell.getAttribute('data-pg-edit-value') || '');

            if (quick.control.tagName === 'SELECT') {
                tintSelect(quick.control);
            }

            this.fieldNote(cell, '');
        } else if (event.key === 'F2') {
            event.preventDefault();
            this.setQuick(false);
        } else if ((event.ctrlKey || event.metaKey) && ((event.key === 's') || (event.key === 'S'))) {
            event.preventDefault();
            this.quickSave(cell);
        }
    };

    /** The same column's field on the next (or the previous) row. */
    D.fieldStep = function (cell, direction) {
        var key = cell.getAttribute('data-pg-col');
        var row = cell.closest('tr');

        while (row) {
            row = (direction > 0) ? row.nextElementSibling : row.previousElementSibling;

            var next = row ? row.querySelector('td[data-pg-col="' + quote(key) + '"]') : null;

            if (next && next.pgQuick) {
                next.pgQuick.control.focus();

                if (next.pgQuick.control.select) {
                    next.pgQuick.control.select();
                }

                return;
            }
        }
    };

    /** The reason a value was refused under its field, or none. */
    D.fieldNote = function (cell, message) {
        var quick = cell.pgQuick;

        cell.classList.toggle('is-invalid', message !== '');

        if (!quick) {
            return;
        }

        quick.note.textContent = message;
        quick.note.hidden = (message === '');
        quick.control.classList.toggle('is-invalid', message !== '');

        if (message !== '') {
            quick.control.setAttribute('aria-invalid', 'true');
        } else {
            quick.control.removeAttribute('aria-invalid');
        }
    };

    D.token = function () {
        if ((typeof window.software_token === 'string') && (window.software_token !== '')) {
            return window.software_token;
        }

        var form = this.root.closest('form');
        var field = (form || document).querySelector('input[name="token"]');

        return field ? field.value : '';
    };

    /** One cell's value to the screen's endpoint; resolves its answer. */
    D.post = function (field, id, value) {
        var body = new FormData();
        var self = this;

        body.append('pg_list_edit', '1');
        body.append('token', this.token());
        body.append('field', field);
        body.append('id', id);
        body.append('value', value);

        return window.fetch(this.editUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json().catch(function () { return null; });
        }).then(function (data) {
            return (data && (typeof data === 'object')) ? data : { ok: false, message: self.texts.saveFailed };
        }, function () {
            return { ok: false, message: self.texts.saveFailed };
        });
    };

    /** A row's cell of a field, on screen. */
    D.fieldCell = function (id, field) {
        var row = this.tbody.querySelector('tr[data-pg-id="' + quote(id) + '"]');

        return row ? row.querySelector('td[data-pg-edit="' + quote(field) + '"]') : null;
    };

    /**
     * A cell drawn from an answer: its content, classes, the value its
     * field opens with. A cell in the mode keeps its field; what it shows
     * when the mode ends is the new content.
     */
    D.paintCell = function (cell, view) {
        var key = cell.getAttribute('data-pg-col');
        var align = '';

        this.model.forEach(function (column) {
            if ((column.key === key) && (column.align !== 'start')) {
                align = ' text-' + column.align;
            }
        });

        if ((view.value !== undefined) && (view.value !== null) && cell.hasAttribute('data-pg-edit')) {
            cell.setAttribute('data-pg-edit-value', String(view.value));

            if (cell.pgQuick) {
                cell.pgQuick.control.value = String(view.value);

                if (cell.pgQuick.control.tagName === 'SELECT') {
                    tintSelect(cell.pgQuick.control);
                }
            }
        }

        if (typeof view.html === 'string') {
            if (cell.pgQuick) {
                cell.pgQuickHtml = view.html;
            } else {
                cell.innerHTML = view.html;
            }
        }

        if (typeof view['class'] === 'string') {
            var invalid = cell.classList.contains('is-invalid');

            cell.className = (view['class'] + align).trim();
            cell.classList.toggle('is-invalid', invalid);
        }
    };

    /** The cells an answer names, drawn on a row; the edited cell left out. */
    D.paintRow = function (row, cells, skip) {
        if (!row || !cells || (typeof cells !== 'object')) {
            return;
        }

        for (var name in cells) {
            if (Object.prototype.hasOwnProperty.call(cells, name)) {
                var other = row.querySelector('[data-pg-cell="' + quote(name) + '"], td[data-pg-col="' + quote(name) + '"], td[data-pg-edit="' + quote(name) + '"]');

                if (other && (other !== skip)) {
                    this.paintCell(other, cells[name]);
                }
            }
        }
    };

    /**
     * A saved answer drawn on its row: the cell and the cells it names;
     * 'rows' (id => cells) redraws other rows on screen the save changed
     * too (a theme activated in place of another one).
     */
    D.takeSaved = function (cell, data) {
        var row = cell.closest('tr');

        this.paintCell(cell, data);
        this.paintRow(row, data.cells, cell);

        if (data.rows && (typeof data.rows === 'object')) {
            for (var id in data.rows) {
                if (Object.prototype.hasOwnProperty.call(data.rows, id)) {
                    var other = this.tbody.querySelector('tr[data-pg-id="' + quote(id) + '"]');

                    if (other && (other !== row)) {
                        this.paintRow(other, data.rows[id], null);
                    }
                }
            }
        }

        cell.classList.remove('pg-list-table-saved');
        void cell.offsetWidth;
        cell.classList.add('pg-list-table-saved');
    };

    /**
     * A field saved when it holds a new value. A save while the cell's last
     * one is still on its way waits for it.
     */
    D.quickSave = function (cell) {
        var self = this;
        var quick = cell.pgQuick;

        if (!quick) {
            return;
        }

        if (cell.pgQuickBusy) {
            cell.pgQuickAgain = true;
            return;
        }

        var stored = cell.getAttribute('data-pg-edit-value') || '';
        var value = quickValue(quick.def.type, quick.control.value);

        if (!quickChanged(quick.def.type, quick.control.value, stored)) {
            this.fieldNote(cell, '');
            return;
        }

        var field = cell.getAttribute('data-pg-edit');
        var id = cell.getAttribute('data-pg-edit-id') || '';
        var rowId = cell.closest('tr').getAttribute('data-pg-id');

        cell.pgQuickBusy = true;
        cell.setAttribute('aria-busy', 'true');

        this.post(field, id, value).then(function (data) {
            cell.pgQuickBusy = false;
            cell.removeAttribute('aria-busy');

            if (data.ok) {
                self.fieldNote(cell, '');
                self.takeSaved(cell, data);
                self.edits.record([{ id: id, field: field, before: stored, after: (data.value !== undefined) ? data.value : value }]);
                self.applyPending = { field: field, value: (data.value !== undefined) ? String(data.value) : value, row: rowId };
                self.say(self.texts.saved);

                // Something the save did beside the cell (a renamed page's
                // old address sending visitors on), said once.
                if (data.notice && (typeof window.pgToast === 'function')) {
                    window.pgToast({ message: String(data.notice), variant: 'info', delay: 8000 });
                }
            } else {
                self.fieldNote(cell, String(data.message || self.texts.saveFailed));
                self.say(String(data.message || self.texts.saveFailed));
            }

            self.drawApply();

            if (cell.pgQuickAgain) {
                cell.pgQuickAgain = false;
                self.quickSave(cell);
            }
        });
    };

    /** The rows on screen as applyTargets() takes them, for a field. */
    D.fieldRows = function (field) {
        return Array.prototype.map.call(this.tbody.rows, function (row) {
            var cell = row.hidden ? null : row.querySelector('td[data-pg-edit="' + quote(field) + '"]');

            return { id: row.getAttribute('data-pg-id'), editable: !!(cell && cell.pgQuick) };
        });
    };

    /** The undo and the apply buttons drawn from the state. */
    D.drawApply = function () {
        if (!this.applyButton) {
            return;
        }

        var pending = this.applyPending;
        var targets = (pending && this.quickOn) ? applyTargets(this.fieldRows(pending.field), this.selection, pending.row) : [];

        this.applyButton.hidden = (targets.length === 0) || !!this.applying;
        this.applyButton.textContent = T.text(this.texts.apply, targets.length);
        this.quickUndoButton.hidden = !this.edits.undoable() || !!this.applying;
        this.bar.classList.toggle('has-quick', !this.applyButton.hidden || !this.quickUndoButton.hidden);
    };

    /**
     * Writes one value per row, one request at a time; resolves the
     * changes written and the number refused.
     */
    D.writeAll = function (writes) {
        var self = this;
        var changes = [];
        var failed = 0;
        var chain = Promise.resolve();

        writes.forEach(function (write) {
            chain = chain.then(function () {
                var cell = self.fieldCell(write.row, write.field);
                var before = cell ? (cell.getAttribute('data-pg-edit-value') || '') : null;

                return self.post(write.field, write.id, write.value).then(function (data) {
                    if (!data.ok) {
                        failed++;

                        if (cell) {
                            self.fieldNote(cell, String(data.message || self.texts.saveFailed));
                        }

                        return;
                    }

                    if (cell) {
                        self.fieldNote(cell, '');
                        self.takeSaved(cell, data);
                    }

                    changes.push({ id: write.id, field: write.field, before: (before === null) ? write.before : before, after: (data.value !== undefined) ? data.value : write.value });
                });
            });
        });

        return chain.then(function () {
            return { changes: changes, failed: failed };
        });
    };

    D.finishWrite = function (result, message) {
        this.applying = false;

        var text = message;

        if (result.failed) {
            text = T.text(this.texts.applyFailed, result.failed);

            if (typeof window.pgToast === 'function') {
                window.pgToast({ message: text, variant: 'danger', delay: 6000 });
            }
        }

        this.say(text);
        this.drawApply();
    };

    /** "Apply to N selected rows": the last saved value on each. */
    D.quickApply = function () {
        var self = this;
        var pending = this.applyPending;

        if (!pending || this.applying) {
            return;
        }

        var writes = applyTargets(this.fieldRows(pending.field), this.selection, pending.row).map(function (row) {
            var cell = self.fieldCell(row, pending.field);

            return { row: row, id: cell.getAttribute('data-pg-edit-id') || row, field: pending.field, value: pending.value };
        });

        this.applying = true;
        this.applyPending = null;
        this.drawApply();

        this.writeAll(writes).then(function (result) {
            self.edits.record(result.changes);
            self.finishWrite(result, T.text(self.texts.applied, result.changes.length));
        });
    };

    /** The last save written back as it was before. */
    D.quickUndo = function () {
        var self = this;
        var tbody = this.tbody;

        if (this.applying || !this.edits.undoable()) {
            return;
        }

        var writes = this.edits.takeUndo().map(function (change) {
            var cell = tbody.querySelector('td[data-pg-edit="' + quote(change.field) + '"][data-pg-edit-id="' + quote(change.id) + '"]');
            var row = cell ? cell.closest('tr').getAttribute('data-pg-id') : '';

            return { row: row, id: change.id, field: change.field, value: change.value, before: '' };
        });

        this.applying = true;
        this.applyPending = null;
        this.drawApply();

        this.writeAll(writes).then(function (result) {
            self.finishWrite(result, self.texts.undone);
        });
    };

    // F2 turns the mode of the list on or off: the list the focus is in,
    // else the only list of the page that has quick edit.
    function bindF2() {
        document.addEventListener('keydown', function (event) {
            if ((event.key !== 'F2') || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) {
                return;
            }

            var target = event.target;
            var root = (target && target.closest) ? target.closest('.pg-list-table[data-pg-edit-url]') : null;

            if (!root) {
                if (target && target.closest && target.closest('input, select, textarea, [contenteditable]')) {
                    return;
                }

                var lists = document.querySelectorAll('.pg-list-table[data-pg-edit-url]');

                root = (lists.length === 1) ? lists[0] : null;
            }

            if (root && root.pgListTable && root.pgListTable.editUrl) {
                event.preventDefault();
                root.pgListTable.setQuick(!root.pgListTable.quickOn);
            }
        });
    }

    T.QuickEdits = QuickEdits;
    T.applyTargets = applyTargets;
    T.quickValue = quickValue;
    T.quickChanged = quickChanged;
    T.quickChoices = quickChoices;
    T.rowNameText = rowNameText;
    T.tintSelect = tintSelect;

    // Lists the driver started before this part loaded.
    if (typeof document !== 'undefined' && document.querySelectorAll) {
        bindF2();

        Array.prototype.forEach.call(document.querySelectorAll('.pg-list-table[data-pg-list-table]'), function (root) {
            if (root.pgListTable) {
                root.pgListTable.quickInit();
            }
        });
    }
}());
