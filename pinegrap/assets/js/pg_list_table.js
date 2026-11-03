/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's list table driver in the browser (window.PGListTable). A list
 * printed by pg_list_table_html() (includes/fn/list_table.php) comes alive
 * here: the next rows load as the list scrolls (or from "Show more"), a
 * heading click sorts without a reload, rows are selected as in the File
 * Manager (click, Ctrl/Cmd toggles, Shift a range, arrow keys), Ctrl+A
 * selects the whole list by its condition, and a selection that replaced
 * two or more rows can be undone for 10 seconds.
 *
 * The selection is kept by record id, never by the rows on screen, so
 * loading, sorting or searching does not drop it. PGListTable.Selection is
 * the pure model (no DOM; tests/list_table_js_test.php runs it in Node); its
 * payload() is what pg_list_table_selection_parse() reads.
 *
 * A screen listens to 'pg-list-table:selection' on the list's root element
 * (detail: count, payload) to enable its bulk actions, and may put them in
 * the bar's .pg-list-table-actions box. root.pgListTable is the driver.
 *
 * The bar's columns menu shows, hides and orders the columns, and its
 * details switch opens a panel with every column of the current row (a
 * side sheet below 1024 px); both choices are kept per user and list in
 * localStorage (pg.list.<user id>.<list>). Below 768 px each row is drawn
 * as a card, every cell under its column's name (data-pg-label). Pictures
 * named by img[data-pg-thumb] load as they come into sight, six at a time.
 *
 * Served minified as assets/js/pg_list_table.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var UNDO_MS = 10000;

    function has(object, key) {
        return Object.prototype.hasOwnProperty.call(object, key);
    }

    function keys(object) {
        var list = [];

        for (var key in object) {
            if (has(object, key)) {
                list.push(key);
            }
        }

        return list;
    }

    // ------------------------------------------------------------------
    // The selection model.
    // ------------------------------------------------------------------

    /**
     * Either a set of ids, or the whole list by its condition less the
     * exceptions, as of a moment (Unix seconds).
     */
    function Selection() {
        this.all = false;
        this.ids = {};
        this.except = {};
        this.asOf = 0;
        this.anchor = null;
    }

    Selection.prototype.has = function (id) {
        id = String(id);

        return this.all ? !has(this.except, id) : has(this.ids, id);
    };

    /** The rows selected, out of total rows of the condition. */
    Selection.prototype.count = function (total) {
        return this.all ? Math.max(0, total - keys(this.except).length) : keys(this.ids).length;
    };

    Selection.prototype.snapshot = function () {
        return {
            all: this.all,
            ids: keys(this.ids),
            except: keys(this.except),
            asOf: this.asOf,
            anchor: this.anchor
        };
    };

    Selection.prototype.restore = function (snapshot) {
        var self = this;

        this.all = !!snapshot.all;
        this.ids = {};
        this.except = {};
        this.asOf = snapshot.asOf || 0;
        this.anchor = (snapshot.anchor === undefined) ? null : snapshot.anchor;
        snapshot.ids.forEach(function (id) { self.ids[id] = true; });
        snapshot.except.forEach(function (id) { self.except[id] = true; });
    };

    /**
     * A change that replaces the selection: answers the selection before it
     * when that held two or more rows (it can be undone), else null.
     */
    Selection.prototype.replaced = function (total, change) {
        var before = (this.count(total) >= 2) ? this.snapshot() : null;

        change.call(this);

        return before;
    };

    Selection.prototype.clear = function () {
        this.all = false;
        this.ids = {};
        this.except = {};
        this.asOf = 0;
        this.anchor = null;
    };

    /** A plain click: this row alone. */
    Selection.prototype.only = function (id) {
        this.clear();
        this.ids[String(id)] = true;
        this.anchor = String(id);
    };

    /** Ctrl/Cmd click: this row in or out; under Ctrl+A an exception. */
    Selection.prototype.toggle = function (id) {
        id = String(id);

        if (this.all) {
            if (has(this.except, id)) {
                delete this.except[id];
            } else {
                this.except[id] = true;
            }
        } else if (has(this.ids, id)) {
            delete this.ids[id];
        } else {
            this.ids[id] = true;
        }

        this.anchor = id;
    };

    /**
     * Shift click: the rows from the anchor to this one, in the order on
     * screen. additive (Ctrl+Shift) keeps what was selected besides.
     */
    Selection.prototype.range = function (order, id, additive) {
        id = String(id);

        var from = (this.anchor === null) ? -1 : order.indexOf(this.anchor);
        var to = order.indexOf(id);
        var anchor = this.anchor;

        if (to < 0) {
            return;
        }

        if (from < 0) {
            from = to;
            anchor = id;
        }

        if (!additive) {
            this.clear();
        }

        for (var i = Math.min(from, to); i <= Math.max(from, to); i++) {
            if (this.all) {
                delete this.except[order[i]];
            } else {
                this.ids[order[i]] = true;
            }
        }

        this.anchor = anchor;
    };

    /** Ctrl+A: every row of the condition, as of now (Unix seconds). */
    Selection.prototype.selectAll = function (now) {
        this.clear();
        this.all = true;
        this.asOf = Math.floor(now);
    };

    /**
     * What a bulk action sends: the ids, or the condition with its
     * exceptions and moment (pg_list_table_selection_parse()).
     */
    Selection.prototype.payload = function (condition) {
        if (!this.all) {
            return { ids: keys(this.ids) };
        }

        return { condition: condition, except: keys(this.except), as_of: this.asOf };
    };

    // ------------------------------------------------------------------
    // Pure helpers.
    // ------------------------------------------------------------------

    /** A text with '%n' written as the number. */
    function text(template, number) {
        return String(template || '').replace('%n', String(number));
    }

    /**
     * The sort levels after a heading click: a plain click sorts by the
     * column alone, turning it when it already leads alone; Shift adds it
     * as the next level or turns it (pg_list_sort_toggle()'s rule).
     */
    function sortAfterClick(levels, key, add) {
        var i;

        if (!add) {
            if ((levels.length >= 1) && (levels[0].sort === key)) {
                return [{ sort: key, order: (levels[0].order === 'asc') ? 'desc' : 'asc' }];
            }

            return [{ sort: key, order: 'asc' }];
        }

        levels = levels.map(function (level) { return { sort: level.sort, order: level.order }; });

        for (i = 0; i < levels.length; i++) {
            if (levels[i].sort === key) {
                if (levels[i].order === 'asc') {
                    levels[i].order = 'desc';
                } else if (levels.length > 1) {
                    levels.splice(i, 1);
                } else {
                    levels[i].order = 'asc';
                }

                return levels;
            }
        }

        levels.push({ sort: key, order: 'asc' });

        return levels;
    }

    function sortParse(value) {
        return String(value || '').split(',').filter(Boolean).map(function (pair) {
            var parts = pair.split(':');

            return { sort: decodeURIComponent(parts[0]), order: (parts[1] === 'desc') ? 'desc' : 'asc' };
        });
    }

    function sortValue(levels) {
        return levels.map(function (level) { return encodeURIComponent(level.sort) + ':' + level.order; }).join(',');
    }

    // ------------------------------------------------------------------
    // Column preferences: order, shown or hidden, the details panel. Kept
    // in the browser per user and list (localStorage), never on the
    // server. Pure: a state object in, a new one out.
    // ------------------------------------------------------------------

    /** The localStorage key of a user's preferences for a list. */
    function prefsKey(user, list) {
        return 'pg.list.' + String(user || 0) + '.' + String(list || '');
    }

    /** A stored value read back as a state: order, hidden, details. */
    function prefsClean(value) {
        var state = { order: [], hidden: {}, details: false };

        if (!value || (typeof value !== 'object')) {
            return state;
        }

        if (Array.isArray(value.order)) {
            state.order = value.order.filter(function (key) { return typeof key === 'string'; });
        }

        if (value.hidden && (typeof value.hidden === 'object')) {
            keys(value.hidden).forEach(function (key) {
                if (typeof value.hidden[key] === 'boolean') {
                    state.hidden[key] = value.hidden[key];
                }
            });
        }

        state.details = (value.details === true);

        return state;
    }

    /**
     * The columns as the user sees them: the stored order for the keys the
     * list still has, then its new columns where the model puts them; each
     * hidden as stored, else as the model says. The first column (the
     * record's name) always leads and never hides, nor does a column that
     * is not hideable.
     */
    function columnsView(columns, state) {
        var byKey = {};
        var order = [];
        var first = columns.length ? columns[0].key : null;

        columns.forEach(function (column) {
            byKey[column.key] = column;
        });

        state.order.forEach(function (key) {
            if (has(byKey, key) && (key !== first) && (order.indexOf(key) < 0)) {
                order.push(key);
            }
        });

        columns.forEach(function (column, index) {
            if ((index > 0) && (order.indexOf(column.key) < 0)) {
                // A column the stored order does not know goes after the
                // column the model puts before it.
                var before = columns[index - 1].key;
                var at = order.indexOf(before);

                order.splice((at < 0) ? 0 : at + 1, 0, column.key);
            }
        });

        if (first !== null) {
            order.unshift(first);
        }

        return order.map(function (key) {
            var column = byKey[key];
            var copy = {};

            for (var name in column) {
                if (has(column, name)) {
                    copy[name] = column[name];
                }
            }

            copy.hidden = (key !== first) && (column.hideable !== false)
                && (has(state.hidden, key) ? state.hidden[key] : !!column.hidden);

            return copy;
        });
    }

    /** A column shown or hidden (one that cannot hide is left as it is). */
    function columnsToggle(columns, state, key) {
        var next = prefsClean(state);
        var view = columnsView(columns, next);

        view.forEach(function (column, index) {
            if ((column.key === key) && (index > 0) && (column.hideable !== false)) {
                next.hidden[key] = !column.hidden;
            }
        });

        return next;
    }

    /** A column moved by delta places; the first column stays first. */
    function columnsMove(columns, state, key, delta) {
        var next = prefsClean(state);
        var order = columnsView(columns, next).map(function (column) { return column.key; });
        var from = order.indexOf(key);
        var to = from + delta;

        if ((from > 0) && (to > 0) && (to < order.length)) {
            order.splice(from, 1);
            order.splice(to, 0, key);
        }

        next.order = order.slice(1);

        return next;
    }

    /** The model's columns again; the details panel keeps its state. */
    function columnsReset(state) {
        return { order: [], hidden: {}, details: prefsClean(state).details };
    }

    // ------------------------------------------------------------------
    // The driver.
    // ------------------------------------------------------------------

    function readJson(element, name, fallback) {
        try {
            var value = JSON.parse(element.getAttribute(name) || '');

            return (value === null) ? fallback : value;
        } catch (e) {
            return fallback;
        }
    }

    function Driver(root) {
        this.root = root;
        this.name = root.getAttribute('data-pg-list-table');
        this.browser = root.getAttribute('data-pg-list-mode') === 'browser';
        this.model = readJson(root, 'data-pg-list-columns', []);
        this.storeKey = prefsKey(root.getAttribute('data-pg-list-user'), root.getAttribute('data-pg-list-table'));
        this.prefs = prefsClean(this.readPrefs());
        this.columns = columnsView(this.model, this.prefs);
        this.current = null;
        this.params = readJson(root, 'data-pg-list-params', {});
        this.texts = readJson(root, 'data-pg-list-texts', {});
        this.levels = sortParse(root.getAttribute('data-pg-list-sort'));
        this.next = root.getAttribute('data-pg-list-next') || null;
        this.total = parseInt(root.getAttribute('data-pg-list-total'), 10) || 0;
        this.search = '';
        this.loading = false;
        this.request = 0;
        this.selection = new Selection();
        this.undo = null;
        this.undoTimer = 0;

        this.scroll = root.querySelector('.pg-list-table-scroll');
        this.table = root.querySelector('table');
        this.tbody = this.table.tBodies[0];
        this.empty = root.querySelector('.pg-list-table-empty');
        this.foot = root.querySelector('.pg-list-table-foot');
        this.bar = root.querySelector('.pg-list-table-bar');
        this.count = root.querySelector('.pg-list-table-count');
        this.live = root.querySelector('[data-pg-list-live]');
        this.menu = root.querySelector('.pg-list-table-columns-menu');
        this.details = root.querySelector('.pg-list-table-details');
        this.detailsBody = root.querySelector('.pg-list-table-details-body');
        this.detailsSwitch = root.querySelector('.pg-list-table-details-switch');
        this.thumbs = { waiting: [], running: 0 };

        if (this.browser) {
            this.total = this.tbody.rows.length;
            this.next = null;
        }

        this.build();
        this.bind();
        this.applyColumns();
        this.fit();
        this.paint();
        this.watch();
        this.watchThumbs(this.tbody);

        root.pgListTable = this;

        // Quick edit mode (pg_list_table_edit.js, bundled after this file).
        if (this.quickInit) {
            this.quickInit();
        }
    }

    Driver.prototype.build = function () {
        var self = this;

        // A sortable heading's text becomes a button.
        Array.prototype.forEach.call(this.table.tHead.rows[0].cells, function (th) {
            if (th.getAttribute('data-pg-sortable') !== '1') {
                return;
            }

            var button = document.createElement('button');

            button.type = 'button';
            button.className = 'pg-list-table-sort';
            button.title = self.texts.sort || '';
            button.textContent = th.textContent;
            th.textContent = '';
            th.appendChild(button);
        });

        this.more = document.createElement('button');
        this.more.type = 'button';
        this.more.className = 'btn btn-sm btn-outline theme-secondary pg-list-table-more';
        this.more.textContent = this.texts.more || '';

        this.status = document.createElement('p');
        this.status.className = 'pg-list-table-status fg-secondary';
        this.status.setAttribute('role', 'status');

        this.sentinel = document.createElement('div');
        this.sentinel.className = 'pg-list-table-sentinel';

        this.foot.appendChild(this.status);
        this.foot.appendChild(this.more);
        this.foot.appendChild(this.sentinel);

        var clear = document.createElement('button');

        clear.type = 'button';
        clear.className = 'btn btn-sm btn-text pg-list-table-clear';
        clear.setAttribute('aria-label', this.texts.clear || '');
        clear.title = this.texts.clear || '';
        clear.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
        this.clearButton = clear;

        this.undoButton = document.createElement('button');
        this.undoButton.type = 'button';
        this.undoButton.className = 'btn btn-sm btn-outline theme-secondary pg-list-table-undo';
        this.undoButton.textContent = this.texts.undo || '';
        this.undoButton.hidden = true;

        this.bar.insertBefore(clear, this.bar.firstChild);
        this.bar.insertBefore(this.undoButton, this.bar.querySelector('.pg-list-table-tools'));

        Array.prototype.forEach.call(this.tbody.rows, function (row) {
            row.tabIndex = -1;
        });
    };

    Driver.prototype.bind = function () {
        var self = this;

        this.table.tHead.addEventListener('click', function (event) {
            var button = event.target.closest('.pg-list-table-sort');

            if (button) {
                self.sortBy(button.parentNode.getAttribute('data-pg-col'), event.shiftKey);
            }
        });

        this.tbody.addEventListener('click', function (event) {
            var row = event.target.closest('tr[data-pg-id]');

            // A link, button or field inside a row does its own job.
            if (!row || event.target.closest('a, button, input, select, textarea, label')) {
                return;
            }

            self.pick(row, event);
        });

        this.tbody.addEventListener('dblclick', function (event) {
            var row = event.target.closest('tr[data-pg-id]');

            if (row && !event.target.closest('a, button, input, select, textarea')) {
                self.open(row);
            }
        });

        this.table.addEventListener('keydown', function (event) {
            self.key(event);
        });

        this.more.addEventListener('click', function () {
            self.load();
        });

        this.clearButton.addEventListener('click', function () {
            self.replace(function () { this.clear(); });
        });

        this.undoButton.addEventListener('click', function () {
            if (self.undo) {
                self.selection.restore(self.undo);
                self.dropUndo();
                self.paint();
            }
        });

        window.addEventListener('resize', function () {
            self.fit();
        });

        if (this.menu) {
            this.menu.addEventListener('click', function (event) {
                var target = event.target.closest('[data-pg-col-action]');

                if (target) {
                    self.columnAction(target.getAttribute('data-pg-col-action'), target.getAttribute('data-pg-col'));
                }
            });
        }

        if (this.detailsSwitch) {
            this.detailsSwitch.addEventListener('click', function () {
                self.setDetails(!self.prefs.details);
            });
            this.details.querySelector('.pg-list-table-details-close').addEventListener('click', function () {
                self.setDetails(false);
                self.detailsSwitch.focus();
            });
        }

        // The details follow the row the keyboard is on.
        this.tbody.addEventListener('focusin', function (event) {
            var row = event.target.closest('tr[data-pg-id]');

            if (row && (row.getAttribute('data-pg-id') !== self.current)) {
                self.current = row.getAttribute('data-pg-id');
                self.showDetails();
            }
        });
    };

    Driver.prototype.readPrefs = function () {
        try {
            return JSON.parse(window.localStorage.getItem(this.storeKey) || 'null');
        } catch (e) {
            return null;
        }
    };

    Driver.prototype.savePrefs = function () {
        try {
            window.localStorage.setItem(this.storeKey, JSON.stringify(this.prefs));
        } catch (e) {
            // Private mode or a full storage: the choice lasts for this page.
        }
    };

    /** A column menu item: show or hide, move, or reset. */
    Driver.prototype.columnAction = function (action, key) {
        if (action === 'toggle') {
            this.prefs = columnsToggle(this.model, this.prefs, key);
        } else if ((action === 'up') || (action === 'down')) {
            this.prefs = columnsMove(this.model, this.prefs, key, (action === 'up') ? -1 : 1);
        } else if (action === 'reset') {
            this.prefs = columnsReset(this.prefs);
        } else {
            return;
        }

        this.savePrefs();
        this.columns = columnsView(this.model, this.prefs);
        this.applyColumns();

        var again = this.menu.querySelector('[data-pg-col-action="' + action + '"][data-pg-col="' + (key || '') + '"]');

        if (again) {
            again.focus();
        }
    };

    /**
     * The columns' order and visibility on the headings and every row, a
     * phone card's label on each cell, and the menu drawn again.
     */
    Driver.prototype.applyColumns = function () {
        var self = this;
        var labels = {};

        this.model.forEach(function (column) {
            labels[column.key] = column.heading;
        });

        Array.prototype.forEach.call([this.table.tHead.rows[0]].concat(Array.prototype.slice.call(this.tbody.rows)), function (row) {
            self.arrange(row, labels);
        });

        this.drawMenu();
        this.showDetails();
    };

    Driver.prototype.arrange = function (row, labels) {
        var cells = {};

        Array.prototype.forEach.call(row.cells, function (cell) {
            cells[cell.getAttribute('data-pg-col')] = cell;
        });

        this.columns.forEach(function (column) {
            var cell = cells[column.key];

            if (cell) {
                cell.hidden = column.hidden;

                if (cell.tagName === 'TD') {
                    cell.setAttribute('data-pg-label', labels[column.key] || '');
                }

                row.appendChild(cell);
            }
        });
    };

    Driver.prototype.drawMenu = function () {
        if (!this.menu) {
            return;
        }

        var self = this;
        var last = this.columns.length - 1;
        var html = '';

        function esc(value) {
            var div = document.createElement('div');

            div.textContent = String(value);

            return div.innerHTML.replace(/"/g, '&quot;');
        }

        this.columns.forEach(function (column, index) {
            if (index === 0) {
                return;
            }

            var key = esc(column.key);
            var name = esc(column.heading);

            html += '<div class="pg-list-table-column">'
                + '<label class="pg-list-table-column-name"><input type="checkbox" class="check me-2" data-pg-col-action="toggle" data-pg-col="' + key + '"'
                + (column.hidden ? '' : ' checked') + ((column.hideable === false) ? ' disabled' : '') + '>' + name + '</label>'
                + '<button type="button" class="btn btn-sm btn-text theme-secondary" data-pg-col-action="up" data-pg-col="' + key + '"' + ((index <= 1) ? ' disabled' : '')
                + ' aria-label="' + esc(self.texts.up || '') + ': ' + name + '" title="' + esc(self.texts.up || '') + '"><i class="bi bi-arrow-up" aria-hidden="true"></i></button>'
                + '<button type="button" class="btn btn-sm btn-text theme-secondary" data-pg-col-action="down" data-pg-col="' + key + '"' + ((index >= last) ? ' disabled' : '')
                + ' aria-label="' + esc(self.texts.down || '') + ': ' + name + '" title="' + esc(self.texts.down || '') + '"><i class="bi bi-arrow-down" aria-hidden="true"></i></button>'
                + '</div>';
        });

        html += '<hr class="my-1"><button type="button" class="menu-item" data-pg-col-action="reset" data-pg-col=""><i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i>' + esc(this.texts.reset || '') + '</button>';

        this.menu.innerHTML = html;
    };

    /** The details panel opened or closed, the choice kept. */
    Driver.prototype.setDetails = function (open) {
        this.prefs.details = !!open;
        this.savePrefs();
        this.showDetails();
    };

    /**
     * The details panel: every column of the current row, hidden ones too,
     * in the order the user put them.
     */
    Driver.prototype.showDetails = function () {
        if (!this.details) {
            return;
        }

        var open = this.prefs.details;

        this.details.hidden = !open;
        this.root.classList.toggle('pg-list-table-with-details', open);
        this.detailsSwitch.setAttribute('aria-pressed', open ? 'true' : 'false');

        if (!open) {
            return;
        }

        var row = (this.current === null) ? null : this.tbody.querySelector('tr[data-pg-id="' + String(this.current).replace(/["\\]/g, '') + '"]');
        var body = this.detailsBody;

        body.textContent = '';

        if (!row) {
            var empty = document.createElement('p');

            empty.className = 'fg-secondary m-0';
            empty.textContent = this.texts.pick || '';
            body.appendChild(empty);
            return;
        }

        var list = document.createElement('dl');

        this.columns.forEach(function (column) {
            var cell = row.querySelector('td[data-pg-col="' + column.key + '"]');

            if (!cell || (column.kind === 'actions' && !cell.textContent.trim() && !cell.querySelector('a, button'))) {
                return;
            }

            var term = document.createElement('dt');
            var value = document.createElement('dd');

            term.textContent = column.heading;

            // The cell's own markup, escaped by the server where it was
            // built; a quick edit field shows what the cell holds.
            value.innerHTML = (typeof cell.pgQuickHtml === 'string') ? cell.pgQuickHtml : cell.innerHTML;
            list.appendChild(term);
            list.appendChild(value);
        });

        body.appendChild(list);
    };

    /**
     * Thumbnails (img[data-pg-thumb]) load as they come into sight, at most
     * six at a time, so a long list of pictures does not hold up the rows.
     */
    Driver.prototype.watchThumbs = function (scope) {
        var self = this;
        var images = scope.querySelectorAll ? scope.querySelectorAll('img[data-pg-thumb]') : [];

        if (!images.length) {
            return;
        }

        if (!this.thumbWatch) {
            if (!('IntersectionObserver' in window)) {
                Array.prototype.forEach.call(images, function (img) { self.queueThumb(img); });
                return;
            }

            this.thumbWatch = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        self.thumbWatch.unobserve(entry.target);
                        self.queueThumb(entry.target);
                    }
                });
            }, { root: this.scroll, rootMargin: '200px 0px' });
        }

        Array.prototype.forEach.call(images, function (img) {
            self.thumbWatch.observe(img);
        });
    };

    Driver.prototype.queueThumb = function (img) {
        if (!img.getAttribute('data-pg-thumb')) {
            return;
        }

        this.thumbs.waiting.push(img);
        this.pumpThumbs();
    };

    Driver.prototype.pumpThumbs = function () {
        var self = this;

        while ((this.thumbs.running < 6) && this.thumbs.waiting.length) {
            var img = this.thumbs.waiting.shift();
            var src = img.getAttribute('data-pg-thumb');

            img.removeAttribute('data-pg-thumb');
            this.thumbs.running++;

            var done = function () {
                this.removeEventListener('load', done);
                this.removeEventListener('error', done);
                self.thumbs.running--;
                self.pumpThumbs();
            };

            img.addEventListener('load', done);
            img.addEventListener('error', done);
            img.src = src;
        }
    };

    /** Rows load when the end of the list comes into sight. */
    Driver.prototype.watch = function () {
        var self = this;

        if (!('IntersectionObserver' in window)) {
            return;
        }

        new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting && self.next && !self.loading && !self.failed) {
                self.load();
            }
        }, { root: this.scroll, rootMargin: '0px 0px 400px 0px' }).observe(this.sentinel);
    };

    /** The list fills the window from where it starts. */
    Driver.prototype.fit = function () {
        var top = this.root.getBoundingClientRect().top + window.pageYOffset;

        this.root.style.setProperty('--pg-list-table-top', Math.max(0, Math.round(top)) + 'px');
    };

    Driver.prototype.order = function () {
        return Array.prototype.map.call(this.tbody.rows, function (row) {
            return row.getAttribute('data-pg-id');
        });
    };

    Driver.prototype.pick = function (row, event) {
        var id = row.getAttribute('data-pg-id');

        this.current = id;

        if (event.shiftKey) {
            this.selection.range(this.order(), id, event.ctrlKey || event.metaKey);
        } else if (event.ctrlKey || event.metaKey) {
            this.selection.toggle(id);
        } else {
            this.replace(function () { this.only(id); });
        }

        row.focus();
        this.paint();
        this.showDetails();
    };

    /** A change that replaces the selection, offering its undo. */
    Driver.prototype.replace = function (change) {
        var before = this.selection.replaced(this.total, change);

        this.dropUndo();

        if (before) {
            var self = this;

            this.undo = before;
            this.undoButton.hidden = false;
            this.undoTimer = window.setTimeout(function () {
                self.dropUndo();
                self.paint();
            }, UNDO_MS);
        }

        this.paint();
    };

    Driver.prototype.dropUndo = function () {
        window.clearTimeout(this.undoTimer);
        this.undo = null;
        this.undoButton.hidden = true;
    };

    /** Double-click or Enter: the row's own link, its name's first. */
    Driver.prototype.open = function (row) {
        var link = row.querySelector('a[data-pg-open]') || row.querySelector('td a[href]:not([href^="javascript"])');

        if (link) {
            link.click();
        }
    };

    Driver.prototype.key = function (event) {
        var row = event.target.closest ? event.target.closest('tr[data-pg-id]') : null;
        var mod = event.ctrlKey || event.metaKey;

        if (mod && ((event.key === 'a') || (event.key === 'A'))) {
            event.preventDefault();
            this.replace(function () { this.selectAll(Date.now() / 1000); });
            return;
        }

        if (event.key === 'Escape') {
            if (this.selection.count(this.total) > 0) {
                event.preventDefault();
                this.replace(function () { this.clear(); });
            }
            return;
        }

        if (!row) {
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            this.open(row);
        } else if (event.key === ' ') {
            event.preventDefault();
            this.selection.toggle(row.getAttribute('data-pg-id'));
            this.paint();
        } else if ((event.key === 'ArrowDown') || (event.key === 'ArrowUp')) {
            var to = (event.key === 'ArrowDown') ? row.nextElementSibling : row.previousElementSibling;

            if (to) {
                event.preventDefault();
                to.focus();

                if (event.shiftKey) {
                    this.selection.range(this.order(), to.getAttribute('data-pg-id'), false);
                    this.paint();
                }
            }
        }
    };

    /** The rows, the bar and the foot drawn from the state. */
    Driver.prototype.paint = function () {
        var self = this;
        var count = this.selection.count(this.total);

        Array.prototype.forEach.call(this.tbody.rows, function (row) {
            row.classList.toggle('pg-list-table-selected', self.selection.has(row.getAttribute('data-pg-id')));
        });

        this.bar.classList.toggle('is-empty', (count === 0) && !this.undo);
        this.count.textContent = (count === 0) ? '' : text(this.selection.all ? this.texts.all : this.texts.selected, count);
        this.clearButton.hidden = (count === 0);
        this.empty.hidden = this.tbody.rows.length > 0;
        this.more.hidden = !this.next || this.loading || !!this.failed;

        this.root.dispatchEvent(new CustomEvent('pg-list-table:selection', {
            detail: { count: count, payload: this.selection.payload(this.condition()) }
        }));
    };

    Driver.prototype.condition = function () {
        return { search: this.search, params: this.params };
    };

    Driver.prototype.say = function (message) {
        this.live.textContent = message;
    };

    Driver.prototype.setStatus = function (message, retry) {
        var self = this;

        this.status.textContent = message || '';

        if (retry) {
            var button = document.createElement('button');

            button.type = 'button';
            button.className = 'btn btn-sm btn-text';
            button.textContent = this.texts.retry || '';
            button.addEventListener('click', function () {
                self.failed = false;
                self.load(self.pendingReset);
            });
            this.status.appendChild(document.createTextNode(' '));
            this.status.appendChild(button);
        }
    };

    /** A row built from the rows answer. */
    Driver.prototype.row = function (data) {
        var tr = document.createElement('tr');

        tr.setAttribute('role', 'row');
        tr.setAttribute('data-pg-id', data.id);
        tr.tabIndex = -1;

        if (data.class) {
            tr.className = data.class;
        }

        this.columns.forEach(function (column) {
            var cell = (data.cells && data.cells[column.key]) || { html: '', class: '' };
            var label = column.heading;
            var td = document.createElement('td');
            var cls = ((cell.class || '') + ((column.align !== 'start') ? ' text-' + column.align : '')).trim();

            td.setAttribute('role', 'cell');
            td.setAttribute('data-pg-col', column.key);
            td.setAttribute('data-pg-label', label || '');

            if (cls) {
                td.className = cls;
            }

            for (var name in (cell.attrs || {})) {
                if (has(cell.attrs, name) && /^[a-z][a-z0-9-]*$/.test(name)) {
                    td.setAttribute(name, cell.attrs[name]);
                }
            }

            // The server escaped every cell where it built it.
            td.innerHTML = cell.html;
            td.hidden = !!column.hidden;
            tr.appendChild(td);
        });

        return tr;
    };

    /**
     * The next batch, or the first one again (reset) after a sort or a
     * search. A later request wins over an earlier one still running.
     */
    Driver.prototype.load = function (reset) {
        var self = this;
        var number = ++this.request;
        var body = {
            action: 'list_table_rows',
            token: window.software_token || '',
            list: this.name,
            params: this.params,
            search: this.search,
            sort: sortValue(this.levels)
        };

        if (!reset) {
            if (!this.next) {
                return;
            }

            body.after = this.next;
        }

        this.loading = true;
        this.failed = false;
        this.pendingReset = !!reset;
        this.root.setAttribute('aria-busy', 'true');
        this.setStatus(this.texts.loading, false);
        this.paint();

        window.fetch('api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().catch(function () { return null; });
        }).then(function (answer) {
            if (number !== self.request) {
                return;
            }

            if (!answer || (answer.status !== 'success')) {
                throw new Error((answer && answer.message) || '');
            }

            self.take(answer, reset || !!answer.restarted);
        }).catch(function (error) {
            if (number !== self.request) {
                return;
            }

            self.loading = false;
            self.failed = true;
            self.root.removeAttribute('aria-busy');
            self.setStatus((error && error.message) || self.texts.failed, true);
            self.paint();
        });
    };

    Driver.prototype.take = function (answer, reset) {
        var self = this;
        var fragment = document.createDocumentFragment();
        var rows = [];

        if (reset) {
            this.tbody.textContent = '';
            this.scroll.scrollTop = 0;
        }

        if (typeof answer.total === 'number') {
            this.total = answer.total;
        }

        answer.rows.forEach(function (data) {
            var tr = self.row(data);

            rows.push(tr);
            fragment.appendChild(tr);
        });

        this.tbody.appendChild(fragment);
        this.next = answer.next || null;
        this.loading = false;
        this.root.removeAttribute('aria-busy');
        this.setStatus('', false);

        // Hints, menus and lazy pictures of the new rows.
        if (rows.length && (typeof window.pgInitInjected === 'function') && window.jQuery) {
            window.pgInitInjected(window.jQuery(rows));
        }

        rows.forEach(function (row) {
            self.watchThumbs(row);
        });

        if (this.quickRows) {
            this.quickRows(rows);
        }

        this.say(text(this.texts.loaded, this.tbody.rows.length));
        this.paint();

        if (reset) {
            this.showDetails();
        }
    };

    /** A heading click: the server sorts, or the browser for a list printed whole. */
    Driver.prototype.sortBy = function (key, add) {
        this.levels = sortAfterClick(this.levels, key, add);
        this.markSort();

        if (this.browser) {
            this.sortHere();
            this.paint();
        } else {
            this.load(true);
        }
    };

    Driver.prototype.markSort = function () {
        var lead = this.levels[0] || null;

        Array.prototype.forEach.call(this.table.tHead.rows[0].cells, function (th) {
            if (lead && (th.getAttribute('data-pg-col') === lead.sort)) {
                th.setAttribute('aria-sort', (lead.order === 'desc') ? 'descending' : 'ascending');
            } else {
                th.removeAttribute('aria-sort');
            }
        });
    };

    Driver.prototype.sortHere = function () {
        var levels = this.levels;
        var collator = new Intl.Collator(document.documentElement.lang || undefined, { numeric: true, sensitivity: 'base' });
        var rows = Array.prototype.slice.call(this.tbody.rows);

        function cellText(row, key) {
            var cell = row.querySelector('td[data-pg-col="' + key + '"]');

            return cell ? (cell.getAttribute('data-pg-sort-value') || cell.textContent.trim()) : '';
        }

        rows.sort(function (a, b) {
            for (var i = 0; i < levels.length; i++) {
                var result = collator.compare(cellText(a, levels[i].sort), cellText(b, levels[i].sort));

                if (result !== 0) {
                    return (levels[i].order === 'desc') ? -result : result;
                }
            }

            return 0;
        });

        rows.forEach(function (row) {
            this.tbody.appendChild(row);
        }, this);
    };

    /**
     * The search box's words. A list printed whole hides the rows that do
     * not match (pgListTurkishMatch() of backend.js when it is there); a
     * server list asks again. A selection of the whole list belonged to the
     * old words, so it is replaced (with its undo).
     */
    Driver.prototype.setSearch = function (value) {
        value = String(value || '').trim();

        if (value === this.search) {
            return;
        }

        this.search = value;

        if (this.selection.all) {
            this.replace(function () { this.clear(); });
        }

        if (this.browser) {
            var match = window.pgListTurkishMatch || function (term, haystack) {
                return haystack.toLowerCase().indexOf(term.toLowerCase()) !== -1;
            };
            var shown = 0;

            Array.prototype.forEach.call(this.tbody.rows, function (row) {
                row.hidden = (value !== '') && !match(value, row.textContent);
                shown += row.hidden ? 0 : 1;
            });

            this.total = shown;
            this.paint();
            return;
        }

        this.load(true);
    };

    Driver.prototype.reload = function () {
        this.load(true);
    };

    function init(scope) {
        Array.prototype.forEach.call((scope || document).querySelectorAll('.pg-list-table[data-pg-list-table]'), function (root) {
            if (!root.pgListTable) {
                new Driver(root);
            }
        });
    }

    // A search box names its list: <input data-pg-list-search="list name">.
    function bindSearch() {
        var timer = 0;

        document.addEventListener('input', function (event) {
            var input = event.target;
            var name = input && input.getAttribute && input.getAttribute('data-pg-list-search');

            if (!name) {
                return;
            }

            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                var root = document.querySelector('.pg-list-table[data-pg-list-table="' + name.replace(/["\\]/g, '') + '"]');

                if (root && root.pgListTable) {
                    root.pgListTable.setSearch(input.value);
                }
            }, 400);
        });
    }

    window.PGListTable = {
        Selection: Selection,
        Driver: Driver,
        text: text,
        sortAfterClick: sortAfterClick,
        sortParse: sortParse,
        sortValue: sortValue,
        prefsKey: prefsKey,
        prefsClean: prefsClean,
        columnsView: columnsView,
        columnsToggle: columnsToggle,
        columnsMove: columnsMove,
        columnsReset: columnsReset,
        init: init
    };

    if (typeof document !== 'undefined' && document.addEventListener) {
        bindSearch();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { init(); });
        } else {
            init();
        }
    }
}());
