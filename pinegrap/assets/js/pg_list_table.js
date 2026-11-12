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
 * localStorage (pg.list.<user id>.<list>). A filter button
 * (data-pg-list-filter, PGListTable's bindFilters()) narrows the list by one
 * column: a list printed whole keeps the rows whose cell carries that
 * data-pg-filter value, a server list sends it with its parameters. Below
 * 768 px each row is drawn
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
     * Ctrl+A in a list printed whole: the rows the search shows, by id (the
     * server has no condition to resolve for such a list).
     */
    Selection.prototype.selectIds = function (ids) {
        var self = this;

        this.clear();
        ids.forEach(function (id) { self.ids[String(id)] = true; });
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

    /**
     * Two sort values of a list printed whole: numbers as numbers, the rest
     * by the collator (the page's language, so Turkish letters sort in their
     * place); an empty value comes first, as NULL does in MySQL.
     */
    function compareSortValues(a, b, collator) {
        var number = /^-?\d+(\.\d+)?$/;

        a = String(a);
        b = String(b);

        if ((a === '') || (b === '')) {
            return (a === b) ? 0 : ((a === '') ? -1 : 1);
        }

        if (number.test(a) && number.test(b)) {
            return parseFloat(a) - parseFloat(b);
        }

        return collator.compare(a, b);
    }

    /**
     * The ids of rows ({id, values: key => sort value}) in the order of the
     * sort levels; rows that tie keep their order.
     */
    function sortRows(rows, levels, collator) {
        return rows.map(function (row, index) {
            return { row: row, index: index };
        }).sort(function (a, b) {
            for (var i = 0; i < levels.length; i++) {
                var result = compareSortValues(a.row.values[levels[i].sort] || '', b.row.values[levels[i].sort] || '', collator);

                if (result !== 0) {
                    return (levels[i].order === 'desc') ? -result : result;
                }
            }

            return a.index - b.index;
        }).map(function (item) {
            return String(item.row.id);
        });
    }

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

    /**
     * The levels after a sort menu action, without Shift: 'reverse' turns
     * level at, 'remove' drops it (never the last one), 'add' puts key as the
     * next level in order (a key already sorted by is left where it is).
     */
    function sortEdit(levels, action, at, key, order) {
        levels = levels.map(function (level) { return { sort: level.sort, order: level.order }; });

        if ((action === 'reverse') && levels[at]) {
            levels[at].order = (levels[at].order === 'desc') ? 'asc' : 'desc';
        } else if ((action === 'remove') && levels[at] && (levels.length > 1)) {
            levels.splice(at, 1);
        } else if ((action === 'add') && key && !levels.some(function (level) { return level.sort === key; })) {
            levels.push({ sort: key, order: (order === 'desc') ? 'desc' : 'asc' });
        }

        return levels;
    }

    /**
     * What a sort mark on a heading shows: for each sorted column its place
     * (1, 2, ...) and direction. The place is shown only when there is more
     * than one level; the lead's direction is its aria-sort.
     */
    function sortMarks(levels) {
        var marks = {};

        levels.forEach(function (level, index) {
            marks[level.sort] = { level: index + 1, order: level.order, numbered: levels.length > 1 };
        });

        return marks;
    }

    /**
     * Whether the select all button stands as "Deselect all": the whole
     * condition is selected, or every row a list printed whole shows (shown,
     * its ids; null for a server list, whose loaded rows are not all of it).
     */
    function allSelected(selection, shown) {
        if (selection.all) {
            return true;
        }

        return !!shown && (shown.length > 0) && shown.every(function (id) { return selection.has(id); });
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
    /**
     * The details panel's sections, one rule for every list: the first
     * column (the record's name) as the title, then the pictures, then a
     * label/value line per other column, then the row's buttons; a column
     * whose cell holds nothing is left out. columns: in the user's order;
     * hasValue(key): whether the row's cell shows anything.
     */
    function detailsSections(columns, hasValue) {
        var sections = { title: null, pictures: [], fields: [], actions: [] };

        columns.forEach(function (column, index) {
            if (index === 0) {
                sections.title = column.key;
            } else if (!hasValue(column.key)) {
                return;
            } else if (column.kind === 'image') {
                sections.pictures.push(column.key);
            } else if (column.kind === 'actions') {
                sections.actions.push(column.key);
            } else {
                sections.fields.push(column.key);
            }
        });

        return sections;
    }

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
        this.printed = root.getAttribute('data-pg-list-printed') === '1';
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
        this.filters = {};
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

            // The kept sort may name a column the server could not order
            // by: the browser sorts the printed rows once.
            if (this.levels.length) {
                this.sortHere();
            }
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
        this.more.className = 'pg-button pg-button-quiet pg-button-sm pg-list-table-more';
        this.more.textContent = this.texts.more || '';

        this.status = document.createElement('p');
        this.status.className = 'pg-list-table-status';
        this.status.setAttribute('role', 'status');

        this.sentinel = document.createElement('div');
        this.sentinel.className = 'pg-list-table-sentinel';

        this.foot.appendChild(this.status);
        this.foot.appendChild(this.more);
        this.foot.appendChild(this.sentinel);

        var clear = document.createElement('button');

        clear.type = 'button';
        clear.className = 'pg-icon-button pg-icon-button-sm pg-list-table-clear';
        clear.setAttribute('aria-label', this.texts.clear || '');
        clear.title = this.texts.clear || '';
        clear.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
        this.clearButton = clear;

        // Select all does what Ctrl+A does; it reads "Deselect all" while
        // everything is selected.
        this.allButton = document.createElement('button');
        this.allButton.type = 'button';
        this.allButton.className = 'pg-button pg-button-ghost pg-button-sm pg-list-table-all';
        this.allButton.setAttribute('aria-keyshortcuts', 'Control+A');

        // The undo of a replaced selection is a small offer of its own: the
        // count and the bulk actions go at once, it stays for 10 seconds.
        this.undoButton = document.createElement('button');
        this.undoButton.type = 'button';
        this.undoButton.className = 'pg-button pg-button-ghost pg-button-sm pg-list-table-undo';
        this.undoButton.innerHTML = '<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>';
        this.undoButton.appendChild(document.createTextNode(this.texts.undo || ''));
        this.undoButton.hidden = true;

        var tools = this.bar.querySelector('.pg-list-table-tools');

        this.bar.insertBefore(clear, this.bar.firstChild);
        this.bar.insertBefore(this.allButton, tools);
        this.bar.insertBefore(this.undoButton, tools);
        this.buildSortMenu(tools);
        this.markSort();

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

        this.allButton.addEventListener('click', function () {
            if (self.allSelected()) {
                self.replace(function () { this.clear(); });
            } else {
                self.selectAll();
            }
        });

        if (this.sortMenu) {
            this.sortMenu.addEventListener('click', function (event) {
                var target = event.target.closest('[data-pg-sort-action]');

                if (target) {
                    self.sortAction(target);
                }
            });
        }

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

        // The cell's own markup, escaped by the server where it was built;
        // a quick edit field shows what the cell holds.
        var cells = {};
        var headings = {};

        this.columns.forEach(function (column) {
            cells[column.key] = row.querySelector('td[data-pg-col="' + column.key + '"]');
            headings[column.key] = column.heading;
        });

        var markup = function (key) {
            return (typeof cells[key].pgQuickHtml === 'string') ? cells[key].pgQuickHtml : cells[key].innerHTML;
        };
        var sections = detailsSections(this.columns.filter(function (column) { return !!cells[column.key]; }), function (key) {
            return !!(cells[key].textContent.trim() || cells[key].querySelector('img, svg, i, input, select, button, a[href]'));
        });
        var block = function (className, keys) {
            if (!keys.length) {
                return;
            }

            var div = document.createElement('div');

            div.className = className;
            div.innerHTML = keys.map(markup).join('');
            body.appendChild(div);
        };

        if (sections.title) {
            var title = document.createElement('h3');

            title.className = 'pg-list-table-details-title';
            title.innerHTML = markup(sections.title);
            body.appendChild(title);
        }

        block('pg-list-table-details-pictures', sections.pictures);

        if (sections.fields.length) {
            var list = document.createElement('dl');

            sections.fields.forEach(function (key) {
                var term = document.createElement('dt');
                var value = document.createElement('dd');

                term.textContent = headings[key];
                value.innerHTML = markup(key);
                list.appendChild(term);
                list.appendChild(value);
            });

            body.appendChild(list);
        }

        block('pg-list-table-details-actions', sections.actions);
    };

    /**
     * Thumbnails (img[data-pg-thumb]) load as they come into sight, at most
     * six at a time, so a long list of pictures does not hold up the rows.
     * A picture a list's cell draws for the panel's lazy loader
     * (img.lazy[data-src], e.g. pg_product_image_cell_content()) is taken
     * over: that loader watches the window, and these rows scroll in their
     * own box, so it loaded the first screen only and never a later batch.
     */
    Driver.prototype.watchThumbs = function (scope) {
        var self = this;

        if (scope.querySelectorAll) {
            Array.prototype.forEach.call(scope.querySelectorAll('img.lazy[data-src]'), function (img) {
                img.setAttribute('data-pg-thumb', img.getAttribute('data-src'));
                img.removeAttribute('data-src');
                img.classList.remove('lazy');
            });
        }

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

            var done = function (event) {
                this.removeEventListener('load', done);
                this.removeEventListener('error', done);

                // A picture that cannot load shows the panel's broken
                // picture square (backend.src.js), not the loading mark.
                if ((event.type === 'error') && window.pgLazyFailedSrc && (this.src !== window.pgLazyFailedSrc)) {
                    this.src = window.pgLazyFailedSrc;
                    this.classList.add('pg-lazy-failed');
                }

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

    /** The ids of the rows on screen, in order; rows the search hid are not. */
    Driver.prototype.order = function () {
        return Array.prototype.filter.call(this.tbody.rows, function (row) {
            return !row.hidden;
        }).map(function (row) {
            return row.getAttribute('data-pg-id');
        });
    };

    /** The next or previous row the search did not hide. */
    function shownSibling(row, down) {
        do {
            row = down ? row.nextElementSibling : row.previousElementSibling;
        } while (row && row.hidden);

        return row;
    }

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

    /**
     * Ctrl+A or the select all button: the whole list by its condition (a
     * Ctrl+click then leaves a row out), or the rows a list printed whole
     * shows.
     */
    Driver.prototype.selectAll = function () {
        if (this.browser) {
            var shown = this.order();

            this.replace(function () { this.selectIds(shown); });
        } else {
            this.replace(function () { this.selectAll(Date.now() / 1000); });
        }
    };

    Driver.prototype.allSelected = function () {
        return allSelected(this.selection, this.browser ? this.order() : null);
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
            this.selectAll();
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
            var to = shownSibling(row, event.key === 'ArrowDown');

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

        var all = this.allSelected();

        this.bar.classList.toggle('is-empty', count === 0);
        this.count.textContent = (count === 0) ? '' : text(this.selection.all ? this.texts.all : this.texts.selected, count);
        this.clearButton.hidden = (count === 0);
        this.allButton.innerHTML = '<i class="bi ' + (all ? 'bi-x-square' : 'bi-check2-all') + '" aria-hidden="true"></i>';
        this.allButton.appendChild(document.createTextNode((all ? this.texts.deselectAll : this.texts.selectAll) || ''));
        this.allButton.hidden = this.browser ? (this.order().length === 0) : (this.total === 0);
        this.empty.hidden = this.browser ? (this.order().length > 0) : (this.tbody.rows.length > 0);
        this.more.hidden = !this.next || this.loading || !!this.failed;

        this.fillIds();

        this.root.dispatchEvent(new CustomEvent('pg-list-table:selection', {
            detail: { count: count, payload: this.selection.payload(this.condition()) }
        }));
    };

    /**
     * A printed list whose bulk endpoint reads a list of ids
     * (data-pg-list-ids-field, e.g. styles[]): one hidden field per selected
     * row, posted with the screen's form.
     */
    Driver.prototype.fillIds = function () {
        var name = this.root.getAttribute('data-pg-list-ids-field');
        var box = name ? this.root.querySelector('.pg-list-table-ids') : null;

        if (!box || this.selection.all) {
            return;
        }

        box.textContent = '';

        keys(this.selection.ids).forEach(function (id) {
            var input = document.createElement('input');

            input.type = 'hidden';
            input.name = name;
            input.value = id;
            box.appendChild(input);
        });
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
            button.className = 'pg-button pg-button-ghost pg-button-sm';
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
            var cls = ((cell.class || '') + ((column.align !== 'start') ? ' text-' + column.align : '')
                + ((column.kind === 'image') ? ' pg-list-col-image' : '')).trim();

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

        // Lazy pictures, then hints and menus of the new rows.
        rows.forEach(function (row) {
            self.watchThumbs(row);
        });

        if (rows.length && (typeof window.pgInitInjected === 'function') && window.jQuery) {
            window.pgInitInjected(window.jQuery(rows));
        }

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
        this.setLevels(sortAfterClick(this.levels, key, add));
    };

    /** New sort levels, from a heading or the sort menu. */
    Driver.prototype.setLevels = function (levels) {
        if (!levels.length) {
            return;
        }

        this.levels = levels;
        this.markSort();
        this.drawSortMenu();

        if (this.browser) {
            this.sortHere();
            this.paint();
            this.keepSort();
        } else {
            this.load(true);
        }
    };

    /**
     * The headings' sort marks: aria-sort (and its arrow) on the lead; with
     * more than one level each sorted heading shows its place, and a later
     * level its direction.
     */
    Driver.prototype.markSort = function () {
        var lead = this.levels[0] || null;
        var marks = sortMarks(this.levels);

        Array.prototype.forEach.call(this.table.tHead.rows[0].cells, function (th) {
            var key = th.getAttribute('data-pg-col');
            var button = th.querySelector('.pg-list-table-sort');
            var old = button ? button.querySelector('.pg-list-table-sort-mark') : null;
            var mark = has(marks, key) ? marks[key] : null;

            if (lead && (key === lead.sort)) {
                th.setAttribute('aria-sort', (lead.order === 'desc') ? 'descending' : 'ascending');
            } else {
                th.removeAttribute('aria-sort');
            }

            if (old) {
                old.parentNode.removeChild(old);
            }

            if (!button || !mark || (!mark.numbered && (mark.level === 1))) {
                return;
            }

            var span = document.createElement('span');

            span.className = 'pg-list-table-sort-mark fg-secondary text-nowrap';
            span.setAttribute('aria-hidden', 'true');
            // The place, then (for a later level) the direction: the lead's
            // arrow is drawn after the button by the stylesheet.
            span.innerHTML = ((mark.level <= 9) ? '<i class="bi bi-' + mark.level + '-circle-fill small ms-1"></i>' : '')
                + ((mark.level > 1) ? '<span class="ms-1">' + ((mark.order === 'desc') ? '\u2193' : '\u2191') + '</span>' : '');
            button.appendChild(span);
        });
    };

    /**
     * The sort menu: the screen's toolbar menu for this list
     * ([data-pg-list-sort-menu], pg_list_sort_menu()) taken over so it
     * follows the headings, else one in the bar. It adds, turns and removes
     * levels without Shift.
     */
    Driver.prototype.buildSortMenu = function (tools) {
        var name = String(this.name || '').replace(/["\\]/g, '');
        var wrap = document.querySelector('[data-pg-list-sort-menu="' + name + '"]');
        var self = this;

        this.sortable = this.model.filter(function (column) {
            return self.table.tHead.querySelector('th[data-pg-col="' + String(column.key).replace(/["\\]/g, '') + '"][data-pg-sortable="1"]');
        });

        if (!this.sortable.length) {
            return;
        }

        if (wrap) {
            this.sortDefault = sortParse(wrap.getAttribute('data-pg-sort-default'));
        } else if (tools) {
            wrap = document.createElement('span');
            wrap.className = 'pg-menu-wrap';
            wrap.innerHTML = '<button type="button" class="btn btn-sm btn-text theme-secondary pg-menu-caret" data-bs-toggle="menu" data-bs-placement="bottom-end" data-bs-auto-close="outside" aria-expanded="false"></button>'
                + '<ul class="menu pg-menu-end" style="min-width: min(20rem, 90vw);"></ul>';
            tools.insertBefore(wrap, tools.firstChild);
            this.sortDefault = [];
        } else {
            return;
        }

        this.sortButton = wrap.querySelector('[data-bs-toggle]');
        this.sortMenu = wrap.querySelector('.menu');
        this.drawSortMenu();
    };

    Driver.prototype.drawSortMenu = function () {
        if (!this.sortMenu) {
            return;
        }

        var texts = this.texts;
        var headings = {};
        var levels = this.levels;
        var html = '';

        function esc(value) {
            var div = document.createElement('div');

            div.textContent = String(value);

            return div.innerHTML.replace(/"/g, '&quot;');
        }

        this.model.forEach(function (column) {
            headings[column.key] = column.heading;
        });

        levels.forEach(function (level, index) {
            var asc = (level.order !== 'desc');

            html += '<li class="d-flex align-items-center gap-1 px-5 py-1">'
                + '<span class="fg-secondary small">' + (index + 1) + '.</span>'
                + '<span class="flex-grow-1 text-truncate">' + esc(has(headings, level.sort) ? headings[level.sort] : level.sort) + '</span>'
                + '<button type="button" class="btn btn-sm btn-text theme-secondary text-nowrap" data-pg-sort-action="reverse" data-pg-sort-at="' + index + '" title="' + esc(texts.sortReverse || '') + '">'
                + '<i class="bi bi-caret-' + (asc ? 'up' : 'down') + '-fill me-1" aria-hidden="true"></i>' + esc((asc ? texts.ascending : texts.descending) || '') + '</button>'
                + ((levels.length > 1) ? '<button type="button" class="btn btn-sm btn-text theme-secondary" data-pg-sort-action="remove" data-pg-sort-at="' + index + '" title="' + esc(texts.sortRemove || '')
                    + '" aria-label="' + esc(texts.sortRemove || '') + '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>' : '')
                + '</li>';
        });

        var options = '';

        this.sortable.forEach(function (column) {
            if (levels.some(function (level) { return level.sort === column.key; })) {
                return;
            }

            ['asc', 'desc'].forEach(function (order) {
                options += '<option value="' + esc(column.key + ':' + order) + '">'
                    + esc(String(((order === 'asc') ? texts.sortAsc : texts.sortDesc) || '%s').replace('%s', column.heading)) + '</option>';
            });
        });

        if (options !== '') {
            html += '<li class="d-flex gap-1 px-5 py-3">'
                + '<select class="form-control form-control-sm pg-list-table-sort-then" aria-label="' + esc(texts.sortThen || '') + '">'
                + '<option value="" selected>' + esc(texts.sortThen || '') + '</option>' + options + '</select>'
                + '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-pg-sort-action="add">' + esc(texts.sortAdd || '') + '</button></li>';
        }

        if (this.sortDefault.length && (sortValue(this.sortDefault) !== sortValue(levels))) {
            html += '<li><hr class="menu-divider"></li>'
                + '<li><button type="button" class="menu-item pg-link-body-emphasis" data-pg-sort-action="reset"><i class="bi bi-arrow-counterclockwise me-3" aria-hidden="true"></i>'
                + esc(texts.sortReset || '') + '</button></li>';
        }

        this.sortMenu.innerHTML = html;
        this.sortButton.innerHTML = '<i class="bi bi-sort-down me-1" aria-hidden="true"></i>' + esc(texts.sortBy || '')
            + ((levels.length > 1) ? ' <span class="badge rounded-pill theme-secondary">' + levels.length + '</span>' : '');
    };

    /** A sort menu button: reverse or remove a level, add one, or reset. */
    Driver.prototype.sortAction = function (target) {
        var action = target.getAttribute('data-pg-sort-action');
        var at = parseInt(target.getAttribute('data-pg-sort-at'), 10);
        var focus = null;

        if (action === 'reset') {
            this.setLevels(this.sortDefault);
        } else if (action === 'add') {
            var select = this.sortMenu.querySelector('.pg-list-table-sort-then');
            var pair = (select && select.value) ? select.value.split(':') : null;

            if (!pair) {
                if (select) {
                    select.focus();
                }

                return;
            }

            this.setLevels(sortEdit(this.levels, 'add', -1, pair[0], pair[1]));
            focus = '.pg-list-table-sort-then';
        } else {
            this.setLevels(sortEdit(this.levels, action, at));
            focus = '[data-pg-sort-action="' + action + '"][data-pg-sort-at="' + Math.min(at, this.levels.length - 1) + '"]';
        }

        // The menu was drawn again: the focus goes back into it.
        var again = focus ? this.sortMenu.querySelector(focus) : null;

        (again || this.sortButton).focus();
    };

    Driver.prototype.sortHere = function () {
        var collator = new Intl.Collator(document.documentElement.lang || undefined, { numeric: true, sensitivity: 'base' });
        var byId = {};
        var rows = Array.prototype.map.call(this.tbody.rows, function (row) {
            var values = {};

            Array.prototype.forEach.call(row.cells, function (cell) {
                var key = cell.getAttribute('data-pg-col');

                if (key) {
                    values[key] = cell.hasAttribute('data-pg-sort-value') ? cell.getAttribute('data-pg-sort-value') : cell.textContent.trim();
                }
            });

            byId[row.getAttribute('data-pg-id')] = row;

            return { id: row.getAttribute('data-pg-id'), values: values };
        });

        sortRows(rows, this.levels, collator).forEach(function (id) {
            this.tbody.appendChild(byId[id]);
        }, this);
    };

    /**
     * A printed list's sort, kept in the session for the screen's next
     * visit (the list_table_sort action). Nothing waits for the answer: the
     * rows are already sorted.
     */
    Driver.prototype.keepSort = function () {
        if (!this.printed || !window.fetch) {
            return;
        }

        window.fetch('api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'list_table_sort', token: window.software_token || '', list: this.name, sort: sortValue(this.levels) })
        }).catch(function () {
            // Offline: the sort lasts for this page.
        });
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
            this.showHere();
            return;
        }

        this.load(true);
    };

    /**
     * A filter of one column ('' for every value). A list printed whole
     * hides the rows whose cell of that column does not carry the value in
     * data-pg-filter; a server list sends it with its parameters and asks
     * again. Like new search words, it replaces a whole-list selection.
     */
    Driver.prototype.setFilter = function (key, value) {
        value = String(value || '');

        if ((this.filters[key] || '') === value) {
            return;
        }

        if (value === '') {
            delete this.filters[key];
        } else {
            this.filters[key] = value;
        }

        if (this.selection.all) {
            this.replace(function () { this.clear(); });
        }

        if (this.browser) {
            this.showHere();
            return;
        }

        if (value === '') {
            delete this.params[key];
        } else {
            this.params[key] = value;
        }

        this.load(true);
    };

    /**
     * Whether a cell's data-pg-filter holds a filter value: one value, or
     * several separated by spaces when a row belongs to more than one
     * group of the same buttons (an offer is Active, With code and
     * Incomplete at once).
     */
    function filterHas(values, value) {
        return (' ' + String(values || '') + ' ').indexOf(' ' + value + ' ') !== -1;
    }

    /** A list printed whole: the rows the search words and the filters keep. */
    Driver.prototype.showHere = function () {
        var value = this.search;
        var filters = this.filters;
        var match = window.pgListTurkishMatch || function (term, haystack) {
            return haystack.toLowerCase().indexOf(term.toLowerCase()) !== -1;
        };
        var shown = 0;

        Array.prototype.forEach.call(this.tbody.rows, function (row) {
            var hidden = (value !== '') && !match(value, row.textContent);

            keys(filters).forEach(function (key) {
                var cell = row.querySelector('td[data-pg-col="' + key + '"]');

                hidden = hidden || !cell || !filterHas(cell.getAttribute('data-pg-filter'), filters[key]);
            });

            row.hidden = hidden;
            shown += hidden ? 0 : 1;
        });

        this.total = shown;
        this.paint();
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

    // A filter button names its list and column:
    // <button data-pg-list-filter="list name" data-pg-filter-column="key"
    // data-pg-filter-value="value">; '' shows every value. The buttons of one
    // list and column are a group: the pressed one is marked active.
    function bindFilters() {
        document.addEventListener('click', function (event) {
            var button = event.target && event.target.closest && event.target.closest('[data-pg-list-filter]');

            if (!button) {
                return;
            }

            var name = button.getAttribute('data-pg-list-filter').replace(/["\\]/g, '');
            var key = button.getAttribute('data-pg-filter-column') || '';
            var root = document.querySelector('.pg-list-table[data-pg-list-table="' + name + '"]');

            event.preventDefault();

            Array.prototype.forEach.call(document.querySelectorAll('[data-pg-list-filter="' + name + '"]'), function (other) {
                if ((other.getAttribute('data-pg-filter-column') || '') === key) {
                    other.classList.toggle('active', other === button);
                    other.setAttribute('aria-pressed', (other === button) ? 'true' : 'false');
                }
            });

            if (root && root.pgListTable && key) {
                root.pgListTable.setFilter(key, button.getAttribute('data-pg-filter-value'));
            }
        });
    }

    window.PGListTable = {
        Selection: Selection,
        Driver: Driver,
        text: text,
        sortAfterClick: sortAfterClick,
        sortParse: sortParse,
        sortValue: sortValue,
        sortEdit: sortEdit,
        sortMarks: sortMarks,
        allSelected: allSelected,
        filterHas: filterHas,
        compareSortValues: compareSortValues,
        sortRows: sortRows,
        prefsKey: prefsKey,
        prefsClean: prefsClean,
        columnsView: columnsView,
        columnsToggle: columnsToggle,
        columnsMove: columnsMove,
        columnsReset: columnsReset,
        detailsSections: detailsSections,
        init: init
    };

    if (typeof document !== 'undefined' && document.addEventListener) {
        bindSearch();
        bindFilters();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { init(); });
        } else {
            init();
        }
    }
}());
