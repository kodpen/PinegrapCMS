/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the Board tab of a channel: the channel's tasks as cards in
 * columns (ws_channel_board, includes/workspace/channel_board.php), grouped
 * by status, by the people on them or by when they are due.
 *
 * A task card is moved to another column by dragging it, or from the
 * keyboard: Space picks it up, the left and right arrows choose the column,
 * Enter puts it down and Esc leaves it where it was. What a move does is
 * what the task's own actions do: another status (ws_task_status), other
 * people (ws_task_save, with the clash check and its question), another due
 * date (ws_task_move, with the planning board's question for the newest copy
 * of a repeating task). The planning board's helpers for that are lent by
 * assets/js/workspace.js through window.PGWsKit (moveTask, askSeries), as are
 * the task drawer, the menus and the dialogs.
 *
 * The channel screen mounts the board on its tab
 * (window.PGWsChannelBoard.mount()) and hands it the stamp its regular look
 * (ws_sync) brings back while the tab is open; when the stamp moves, the
 * board is read again. Every text comes from the #ws-config block.
 *
 * The tab has two views under one toolbar: Board, the columns, and List,
 * the channel's task cards the Tasks tab showed, drawn by workspace.js
 * (hooks.listInto()). The toolbar holds the Segmented control of the two,
 * the grouping (Board) or the open, closed or all tasks (List), the search,
 * "Only mine", "Filter" (urgency, person, the cancelled ones) and the one
 * filled "New task". The view chosen is kept in the browser.
 *
 * The workflow tables screen (workspace_wtables.php) mounts the same board
 * over a table's rows by a status column (window.PGWsChannelBoard.mountSource()):
 * that screen does not load workspace.js, so the board brings its own small
 * kit, reads and writes through the source it is handed, and draws no
 * toolbar of its own.
 *
 * Served minified as assets/js/workspace_board_channel.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var GROUPS = ['status', 'person', 'date'];

    // A grouping the board no longer offers (the former "By kind") is read
    // as the first one.
    function knownGroup(group) {
        return (GROUPS.indexOf(group) === -1) ? GROUPS[0] : group;
    }

    // The view chosen last, Board or List, kept per browser.
    var VIEW_KEY = 'ws-board-view';

    function storedView() {
        try {
            return (window.localStorage.getItem(VIEW_KEY) === 'list') ? 'list' : 'board';
        } catch (error) {
            return 'board';
        }
    }

    function storeView(view) {
        try {
            window.localStorage.setItem(VIEW_KEY, view);
        } catch (error) {
            // Private mode or a full store: the view holds for this page.
        }
    }

    // How the reader last grouped and filtered, for the next channel opened
    // on this page.
    var prefs = { view: storedView(), group: 'status', status: 'open', priority: '', mine: false, cancelled: false };

    // The board on the screen; one at a time.
    var board = null;

    function kit() {
        return (board && board.kit) || window.PGWsKit || null;
    }

    // What a board over a source needs of the kit when workspace.js is not
    // on the page: elements, a select, toasts through the panel's pgToast,
    // and the source's own words.
    function smallKit(texts) {
        function el(tag, className, text) {
            var node = document.createElement(tag);

            if (className) {
                node.className = className;
            }

            if ((text !== undefined) && (text !== null) && (text !== '')) {
                node.textContent = String(text);
            }

            return node;
        }

        function icon(name, extra) {
            var node = el('i', 'bi ' + name + (extra ? ' ' + extra : ''));
            node.setAttribute('aria-hidden', 'true');
            return node;
        }

        function toast(message, kind) {
            if (typeof window.pgToast === 'function') {
                window.pgToast({ message: message, variant: kind || 'secondary' });
            }
        }

        return {
            t: function (key) {
                var text = Object.prototype.hasOwnProperty.call(texts, key) ? texts[key] : key;

                Array.prototype.slice.call(arguments, 1).forEach(function (value, index) {
                    text = text.split('{' + (index + 1) + '}').join(String(value));
                });

                return text;
            },
            el: el,
            icon: icon,
            button: function (className, label, iconName, title) {
                var node = el('button', className);
                node.type = 'button';

                if (iconName) {
                    node.appendChild(icon(iconName, label ? 'me-1' : ''));
                }

                if (label) {
                    node.appendChild(document.createTextNode(label));
                }

                if (title) {
                    node.title = title;
                    node.setAttribute('aria-label', title);
                }

                return node;
            },
            select: function (options, value) {
                var node = el('select', 'pg-input pg-input-sm');

                options.forEach(function (option) {
                    var item = el('option', '', option[1]);
                    item.value = option[0];
                    item.selected = (String(option[0]) === String(value));
                    node.appendChild(item);
                });

                return node;
            },
            toast: toast,
            fail: function (error) {
                toast((error && error.message) ? error.message : (texts.error_generic || ''), 'danger');
            }
        };
    }

    function t() {
        var k = kit();

        return k ? k.t.apply(null, arguments) : String(arguments[0]);
    }

    function clear(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }

        return node;
    }

    // ── Reading ──

    function load(target) {
        var k = kit();
        var self = target || board;

        if (!k || !self) {
            return;
        }

        var mine = ++self.serial;

        if (self.filter) {
            self.filter.refresh();
        }

        // The List view: the Tasks pane's cards, narrowed by the toolbar.
        if (!self.source && (prefs.view === 'list')) {
            clear(self.body);
            self.hooks.listInto(self.body, {
                status: prefs.status,
                q: self.q,
                priority: prefs.priority,
                person: self.person,
                mine: prefs.mine,
                onChange: function () {
                    if (self === board) {
                        reload();
                    }
                }
            });
            return;
        }

        prefs.group = knownGroup(prefs.group);

        var reading = self.source ? self.source.load({ all: self.doneAll }) : k.api('ws_channel_board', {
            channel_id: self.channel.id,
            group_by: prefs.group,
            priority: prefs.priority,
            person: self.person,
            mine: prefs.mine ? 1 : 0,
            q: self.q,
            cancelled: prefs.cancelled ? 1 : 0,
            done_all: self.doneAll ? 1 : 0
        });

        reading.then(function (data) {
            if ((mine !== self.serial) || (self !== board)) {
                return;
            }

            self.data = data;
            self.stamp = data.stamp;
            draw(self);
        }).catch(function (error) {
            if (self === board) {
                k.fail(error);
            }
        });
    }

    function reload() {
        if (board) {
            load(board);

            if (board.hooks.refresh) {
                board.hooks.refresh();
            }
        }
    }

    // ── Drawing ──

    // The one toolbar of both views, drawn once a mount: the Segmented
    // control, the grouping or the task status, the search, "Only mine",
    // "Filter" and "New task"; what is set behind "Filter" is a tag under
    // it (self.filter.tags).
    function toolbar(self) {
        var k = kit();
        var bar = k.el('nav', 'pg-toolbar ws-bar ws-cb-bar');

        var views = k.form.seg(t('cb_view'), [['board', t('cb_view_board')], ['list', t('cb_view_list')]], function (view) {
            setView(self, view);
        }, 'ws-cb-views');

        Object.keys(views.items).forEach(function (view) {
            views.items[view].insertBefore(k.icon((view === 'list') ? 'bi-list-ul' : 'bi-kanban', ''), views.items[view].firstChild);
        });
        self.views = views;
        bar.appendChild(views.node);

        // The grouping, in the Board view: chips, the pressed one filled.
        var groups = k.el('div', 'ws-cb-chips');
        groups.setAttribute('role', 'group');
        groups.setAttribute('aria-label', t('cb_group'));

        GROUPS.forEach(function (key) {
            var item = k.button('ws-cb-chip', t('cb_by_' + key));

            item.setAttribute('data-ws-cb-group', key);
            item.addEventListener('click', function () {
                if (prefs.group !== key) {
                    prefs.group = key;
                    self.current = 0;
                    self.data = null;
                    showView(self);
                    draw(self);
                    load(self);
                }
            });
            groups.appendChild(item);
        });

        self.groups = groups;
        bar.appendChild(groups);

        // Open, closed or all tasks, in the List view, as the Tasks tab had.
        var status = k.form.select([['open', t('open_tasks')], ['closed', t('closed_tasks')], ['all', t('all_tasks')]], prefs.status, true);

        status.classList.add('w-auto');
        status.setAttribute('aria-label', t('task_status_filter'));
        status.addEventListener('change', function () {
            prefs.status = status.value;
            load(self);
        });
        self.status = status;
        bar.appendChild(status);

        bar.appendChild(k.el('div', 'pg-toolbar-grow'));

        var search = k.bar.search(t('cb_search'), function (value) {
            self.q = String(value).trim();
            load(self);
        });

        search.input.value = self.q;
        bar.appendChild(search);
        bar.appendChild(toggle(self, 'ws-cb-mine', t('cb_mine'), function () { return prefs.mine; }, function (on) { prefs.mine = on; }));

        // The same urgency choice as the planning board and the task lists.
        var urgency = k.priorityFilter(prefs.priority, function (value) {
            prefs.priority = value;
            load(self);
        });

        var people = [[0, t('cb_all_people')], [-1, t('cb_nobody')]];

        (self.channel.members || []).forEach(function (member) {
            people.push([member.id, member.name]);
        });

        var who = k.select(people, self.person);

        who.classList.add('w-auto');
        who.setAttribute('aria-label', t('who'));
        who.addEventListener('change', function () {
            self.person = parseInt(who.value, 10) || 0;
            load(self);
        });

        var cancelled = k.form.switchRow(t('cb_cancelled'), prefs.cancelled);

        cancelled.input.addEventListener('change', function () {
            prefs.cancelled = cancelled.input.checked;
            load(self);
        });

        self.cancelledField = {
            label: t('cb_cancelled'),
            row: cancelled.row,
            tag: function () { return prefs.cancelled ? t('cb_cancelled') : ''; },
            clear: function () { prefs.cancelled = false; cancelled.input.checked = false; }
        };
        self.filter = k.bar.filter({
            fields: [
                { label: t('priority'), control: urgency, tag: function () { return prefs.priority ? t('priority') + ': ' + k.bar.selectedText(urgency) : ''; }, clear: function () { prefs.priority = ''; urgency.value = ''; } },
                { label: t('who'), control: who, tag: function () { return self.person ? t('who') + ': ' + k.bar.selectedText(who) : ''; }, clear: function () { self.person = 0; who.value = '0'; } },
                self.cancelledField
            ],
            onChange: function () { load(self); }
        });
        bar.appendChild(self.filter.slot);

        var add = k.button('pg-button pg-button-fill pg-button-sm ws-cb-new', t('new_task'), 'bi-plus-lg');

        add.addEventListener('click', function () {
            k.openTask(0, self.hooks.taskDefaults ? self.hooks.taskDefaults() : {}, reload);
        });
        bar.appendChild(add);

        return bar;
    }

    // The toolbar's parts for the view and grouping chosen: the pressed
    // segment and chip, the grouping or the task status, and "Show the
    // cancelled ones" only while the board is by status.
    function showView(self) {
        var list = prefs.view === 'list';

        Object.keys(self.views.items).forEach(function (view) {
            self.views.items[view].setAttribute('aria-pressed', (view === prefs.view) ? 'true' : 'false');
        });
        Array.prototype.forEach.call(self.groups.children, function (item) {
            var on = item.getAttribute('data-ws-cb-group') === prefs.group;

            item.classList.toggle('active', on);
            item.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        self.groups.hidden = list;
        self.status.hidden = !list;
        self.cancelledField.off = list || (prefs.group !== 'status');
        self.filter.refresh();
    }

    function setView(self, view) {
        if (prefs.view === view) {
            return;
        }

        prefs.view = view;
        storeView(view);
        showView(self);
        draw(self);
        load(self);

        if (self.hooks.viewChanged) {
            self.hooks.viewChanged();
        }
    }

    // A switch of the toolbar as a chip: a button that says whether it is
    // on; on a phone its icon alone, its word kept for screen readers.
    function toggle(self, id, label, isOn, set) {
        var k = kit();
        var chip = k.bar.label(k.button('ws-cb-chip', label, 'bi-person'));

        chip.id = id;

        function show() {
            chip.classList.toggle('active', isOn());
            chip.setAttribute('aria-pressed', isOn() ? 'true' : 'false');
        }

        show();
        chip.addEventListener('click', function () {
            set(!isOn());
            show();
            load(self);
        });

        return chip;
    }

    function draw(self) {
        var k = kit();
        var pane = self.body;
        var hadFocus = self.focusKey;

        clear(pane);

        // The List view draws itself once its tasks are read (load()).
        if (!self.source && (prefs.view === 'list')) {
            return;
        }

        if (!self.data) {
            pane.appendChild(k.el('div', 'small fg-secondary', t('loading')));
            return;
        }

        var columns = self.data.columns || [];

        if (self.current >= columns.length) {
            self.current = 0;
        }

        // A phone shows one column at a time, picked here.
        var picker = k.select(columns.map(function (column, index) {
            return [index, column.title + ' (' + column.items.length + ')'];
        }), self.current);
        picker.classList.add('w-auto', 'ws-cb-picker', 'mb-3');
        picker.setAttribute('aria-label', t('cb_column'));
        picker.addEventListener('change', function () {
            self.current = parseInt(picker.value, 10) || 0;
            Array.prototype.forEach.call(self.colsNode.children, function (node, index) {
                node.classList.toggle('ws-cb-col-current', index === self.current);
            });
        });
        pane.appendChild(picker);

        var cols = k.el('div', 'ws-cb-cols ws-cb-by-' + self.data.group_by);
        self.colsNode = cols;
        self.columnNodes = [];

        columns.forEach(function (column, index) {
            cols.appendChild(columnNode(self, column, index));
        });

        pane.appendChild(cols);

        self.live = k.el('div', 'visually-hidden');
        self.live.setAttribute('aria-live', 'assertive');
        pane.appendChild(self.live);

        // The card that had the keyboard keeps it after a new reading.
        if (hadFocus) {
            var again = cols.querySelector('[data-ws-cb-card="' + hadFocus + '"]');

            if (again) {
                again.focus();
            }
        }
    }

    function columnNode(self, column, index) {
        var k = kit();
        var node = k.el('section', 'ws-cb-col' + (index === self.current ? ' ws-cb-col-current' : '') + ((column.drop === null) ? ' ws-cb-nodrop' : ''));
        var head = k.el('header', 'ws-cb-col-head');

        node.setAttribute('aria-label', column.title);

        if (column.person) {
            var face = k.avatar(column.person, '1.4rem');
            face.classList.add('ws-cb-face');
            head.appendChild(face);
        } else {
            var mark = k.icon(column.icon || 'bi-columns', 'ws-cb-col-icon');

            // A table's status choice keeps its own colour.
            if (column.color) {
                mark.style.color = column.color;
            }

            head.appendChild(mark);
        }

        head.appendChild(k.el('span', 'ws-cb-col-title', column.title));
        head.appendChild(k.el('span', 'ws-cb-col-count', column.items.length));

        // A new task with the column's value: its status, its person, its day.
        if (column.add && self.data.can_post) {
            var add = k.button('pg-icon-button pg-icon-button-sm ws-cb-add', '', 'bi-plus-lg', t('cb_add'));
            add.addEventListener('click', function () { newTask(self, column); });
            head.appendChild(add);
        }

        node.appendChild(head);

        var list = k.el('div', 'ws-cb-list');
        list.setAttribute('role', 'list');

        if (!column.items.length) {
            list.appendChild(k.el('div', 'ws-cb-empty', t('cb_empty')));
        }

        column.items.forEach(function (item) {
            list.appendChild(cardNode(self, item, column, index));
        });

        node.appendChild(list);

        if (column.more) {
            var more = k.button('pg-button pg-button-ghost pg-button-sm ws-cb-more', t('cb_more_done', column.more));
            more.addEventListener('click', function () {
                self.doneAll = true;
                load(self);
            });
            node.appendChild(more);
        }

        // Dropped here with the mouse.
        node.addEventListener('dragover', function (event) {
            if (self.drag && (column.drop !== null) && (self.drag.column !== index)) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                node.classList.add('ws-cb-drop');
            }
        });
        node.addEventListener('dragleave', function (event) {
            if (!node.contains(event.relatedTarget)) {
                node.classList.remove('ws-cb-drop');
            }
        });
        node.addEventListener('drop', function (event) {
            event.preventDefault();
            node.classList.remove('ws-cb-drop');

            if (self.drag) {
                var drag = self.drag;

                self.drag = null;
                move(self, drag.item, drag.column, index);
            }
        });

        self.columnNodes.push(node);

        return node;
    }

    function cardNode(self, item, column, columnIndex) {
        var k = kit();
        var card = k.el('div', 'ws-cb-card ws-cb-' + item.kind + (item.priority ? ' ws-cb-pri-' + item.priority : '') + ((item.kind === 'task' && !item.open) ? ' ws-cb-closed' : ''));
        var key = item.kind + ':' + item.id;

        card.tabIndex = 0;
        card.setAttribute('role', 'listitem');
        card.setAttribute('data-ws-cb-card', key);
        card.setAttribute('aria-label', (item.number ? item.number + ' ' : '') + item.title);

        if (item.kind === 'task') {
            taskCard(card, item);
        } else {
            rowCard(card, item);
        }

        var movable = !!item.can_edit;

        if (movable) {
            card.draggable = true;
            card.addEventListener('dragstart', function (event) {
                self.drag = { item: item, column: columnIndex };
                card.classList.add('ws-cb-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(item.id));
            });
            card.addEventListener('dragend', function () {
                card.classList.remove('ws-cb-dragging');
                self.drag = null;
                self.columnNodes.forEach(function (node) { node.classList.remove('ws-cb-drop'); });
            });
        }

        card.addEventListener('click', function () { openCard(self, item); });
        card.addEventListener('focus', function () { self.focusKey = key; });
        card.addEventListener('keydown', function (event) { cardKey(self, event, card, item, columnIndex, movable); });

        if (item.kind === 'task') {
            k.onContext(card, function () {
                return k.taskMenu({ id: item.id, number: item.number, title: item.title, status: item.status, open: item.open, due_date: item.due_date || '' }, reload);
            });
        }

        return card;
    }

    function taskCard(card, item) {
        var k = kit();
        var top = k.el('div', 'ws-cb-card-top');

        top.appendChild(k.el('span', 'ws-cb-number', item.number));

        if (item.priority === 'urgent' || item.priority === 'high') {
            top.appendChild(k.el('span', 'badge ws-cb-priority', item.priority_label));
        }

        if (item.recurring) {
            var repeat = k.icon('bi-arrow-repeat', 'ws-cb-flag');
            repeat.title = t('cb_repeating');
            top.appendChild(repeat);
        }

        if (item.reminder) {
            var bell = k.icon('bi-bell', 'ws-cb-flag');
            bell.title = t('cb_reminder');
            top.appendChild(bell);
        }

        // On the status board the column says the status; elsewhere the card
        // does.
        if (!board || board.data.group_by !== 'status') {
            top.appendChild(k.el('span', 'ws-cb-status ws-s-' + item.status, item.status_label));
        }

        card.appendChild(top);
        card.appendChild(k.el('div', 'ws-cb-title', item.title));

        var meta = k.el('div', 'ws-cb-meta');

        if (item.due_date) {
            var due = k.el('span', 'ws-cb-due' + (item.overdue ? ' fg-danger' : ''));
            due.appendChild(k.icon('bi-calendar-event', 'me-1'));
            due.appendChild(document.createTextNode(item.due_label));
            meta.appendChild(due);
        }

        if (item.progress) {
            var share = k.el('span', 'ws-cb-progress');
            var bar = k.el('span', 'progress ws-cb-progress-bar');
            var fill = k.el('span', 'progress-bar');
            var shareText = t('progress_text', item.progress.done, item.progress.total, item.progress.percent);

            // The bar is named with the sentence; the title only serves the mouse.
            bar.setAttribute('role', 'progressbar');
            bar.setAttribute('aria-label', shareText);
            bar.setAttribute('aria-valuemin', '0');
            bar.setAttribute('aria-valuemax', '100');
            bar.setAttribute('aria-valuenow', String(item.progress.percent));
            fill.style.width = item.progress.percent + '%';
            bar.appendChild(fill);
            share.appendChild(bar);
            share.appendChild(document.createTextNode(item.progress.done + '/' + item.progress.total));
            share.title = shareText;
            meta.appendChild(share);
        }

        if (item.notes_count) {
            var notes = k.el('span', 'ws-cb-notes');
            notes.appendChild(k.icon('bi-journal-text', 'me-1'));
            notes.appendChild(document.createTextNode(String(item.notes_count)));
            notes.title = t('cb_notes', item.notes_count);
            meta.appendChild(notes);
        }

        // The assistant or agent holding the task, as a task row shows it
        // (workspace_task_delegate.js; not on a table's board).
        if (item.delegate_name && window.PGWsTaskDelegate && k.avatar) {
            window.PGWsTaskDelegate.rowTag(meta, item, k);
        }

        if (item.assignees.length) {
            var faces = k.avatarStack('ws-cb-faces', item.assignees, 4);

            // The stack is row-reverse: the count goes in first to sit last.
            if (item.assignees.length > 4) {
                faces.insertBefore(k.el('span', 'ws-cb-more-faces', '+' + (item.assignees.length - 4)), faces.firstChild);
            }

            meta.appendChild(faces);
        }

        if (meta.childNodes.length) {
            card.appendChild(meta);
        }
    }

    // A table row: its title and a few of its other cells.
    function rowCard(card, item) {
        var k = kit();
        var top = k.el('div', 'ws-cb-card-top');

        top.appendChild(k.el('span', 'ws-cb-number', item.number));
        card.appendChild(top);
        card.appendChild(k.el('div', 'ws-cb-title', item.title));

        (item.lines || []).forEach(function (line) {
            var sub = k.el('div', 'ws-cb-sub');

            sub.appendChild(k.el('span', 'fg-secondary', line.label + ': '));
            sub.appendChild(document.createTextNode(line.text));
            card.appendChild(sub);
        });
    }

    // ── Acting ──

    function openCard(self, item) {
        var k = kit();

        if (self.source) {
            self.source.open(item);
        } else {
            k.openTask(item.id, null, reload);
        }
    }

    function newTask(self, column) {
        var k = kit();
        var defaults = self.hooks.taskDefaults();
        var add = column.add || {};

        Object.keys(add).forEach(function (key) {
            if (key !== 'status') {
                defaults[key] = add[key];
            }
        });

        // A new task starts as "to do"; one made in another status column
        // goes there once it is saved.
        k.openTask(0, defaults, function (taskId) {
            if (add.status && (add.status !== 'todo') && taskId) {
                k.api('ws_task_status', { task_id: taskId, status: add.status }).then(reload).catch(k.fail);
                return;
            }

            reload();
        });
    }

    // A card put in another column: what that column stands for is written
    // on the task, by the action that changes that part of it.
    function move(self, item, fromIndex, toIndex) {
        var k = kit();
        var columns = self.data.columns;
        var from = columns[fromIndex];
        var to = columns[toIndex];

        if (!from || !to || (fromIndex === toIndex)) {
            return;
        }

        if ((to.drop === null) || (to.drop === undefined)) {
            k.toast(t('cb_no_drop'), 'warning');
            return;
        }

        // A source writes the move itself; the board is read again either
        // way, so a refused move shows where the card really is.
        if (self.source) {
            self.source.move(item, to.drop).then(function (message) {
                if (message) {
                    k.toast(message, 'success');
                }

                reload();
            }).catch(function (error) {
                k.fail(error);
                reload();
            });
            return;
        }

        switch (self.data.group_by) {
            case 'status':
                k.api('ws_task_status', { task_id: item.id, status: to.drop }).then(function () {
                    k.toast(t('task_saved'), 'success');
                    reload();
                }).catch(k.fail);
                break;

            case 'person':
                var people = item.assignees.map(function (person) { return person.id; }).filter(function (id) {
                    return id !== from.drop;
                });

                if ((to.drop > 0) && (people.indexOf(to.drop) === -1)) {
                    people.push(to.drop);
                }

                // The clash check and its question, as the planning board
                // asks them when work is handed over.
                k.moveTask({ task_id: item.id, assignees: people }, false, reload, 'ws_task_save');
                break;

            case 'date':
                if (to.drop === '') {
                    if (item.recurring) {
                        k.toast(t('cb_no_date_repeat'), 'warning');
                        return;
                    }

                    k.moveTask({ task_id: item.id, due_date: '' }, false, reload, 'ws_task_save');
                    return;
                }

                var day = { task_id: item.id, date: to.drop };

                if (!item.series_latest || (item.due_date === to.drop)) {
                    k.moveTask(day, false, reload);
                    return;
                }

                // The newest copy of a repeating task: that copy, or the
                // ones after it too - the planning board's question.
                k.askSeries().then(function (series) {
                    if (series) {
                        day.series = series;
                        k.moveTask(day, false, reload);
                    }
                });
                break;
        }
    }

    // The keyboard: Enter opens a card; Space picks it up, the arrows choose
    // a column, Enter puts it down, Esc leaves it. Up and down go from card
    // to card, left and right from column to column.
    function cardKey(self, event, card, item, columnIndex, movable) {
        var lifted = self.lifted;
        var columns = self.data.columns;

        if (lifted && (lifted.item === item)) {
            if ((event.key === 'ArrowRight') || (event.key === 'ArrowLeft')) {
                event.preventDefault();

                var next = lifted.target + ((event.key === 'ArrowRight') ? 1 : -1);

                if ((next >= 0) && (next < columns.length)) {
                    lifted.target = next;
                    markTarget(self, next);
                    say(self, t('cb_target', columns[next].title));
                }
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                self.lifted = null;
                card.classList.remove('ws-cb-lifted');
                markTarget(self, -1);

                if (lifted.target !== columnIndex) {
                    say(self, t('cb_dropped', columns[lifted.target].title));
                    move(self, item, columnIndex, lifted.target);
                } else {
                    say(self, t('cb_cancel_move'));
                }
            } else if (event.key === 'Escape' || event.key === 'Tab') {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                }

                self.lifted = null;
                card.classList.remove('ws-cb-lifted');
                markTarget(self, -1);
                say(self, t('cb_cancel_move'));
            }

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            openCard(self, item);
        } else if ((event.key === ' ') && movable) {
            event.preventDefault();
            self.lifted = { item: item, target: columnIndex };
            card.classList.add('ws-cb-lifted');
            say(self, t('cb_lifted', item.title));
        } else if ((event.key === 'ArrowDown') || (event.key === 'ArrowUp')) {
            event.preventDefault();

            var sibling = (event.key === 'ArrowDown') ? card.nextElementSibling : card.previousElementSibling;

            if (sibling && sibling.classList.contains('ws-cb-card')) {
                sibling.focus();
            }
        } else if ((event.key === 'ArrowRight') || (event.key === 'ArrowLeft')) {
            event.preventDefault();

            var step = (event.key === 'ArrowRight') ? 1 : -1;

            for (var i = columnIndex + step; (i >= 0) && (i < self.columnNodes.length); i += step) {
                var first = self.columnNodes[i].querySelector('.ws-cb-card');

                if (first) {
                    first.focus();
                    break;
                }
            }
        }
    }

    function markTarget(self, index) {
        self.columnNodes.forEach(function (node, i) {
            node.classList.toggle('ws-cb-drop', i === index);
        });

        // On a phone the column being chosen is the one shown.
        if ((index >= 0) && self.colsNode) {
            self.columnNodes.forEach(function (node, i) {
                node.classList.toggle('ws-cb-col-current', i === index);
            });
        } else if (self.colsNode) {
            self.columnNodes.forEach(function (node, i) {
                node.classList.toggle('ws-cb-col-current', i === self.current);
            });
        }
    }

    function say(self, text) {
        if (self.live) {
            self.live.textContent = text;
        }
    }

    // ── What the channel screen calls ──

    function state(pane, channel, hooks) {
        return {
            pane: pane,
            channel: channel,
            hooks: hooks || {},
            data: null,
            stamp: '',
            serial: 0,
            person: 0,
            q: '',
            doneAll: false,
            current: 0,
            body: pane,
            drag: null,
            lifted: null,
            focusKey: '',
            columnNodes: []
        };
    }

    // hooks: openMessage(id), openSummary(), taskDefaults(), refresh(),
    // listInto(body, filters) draws the List view, viewChanged(), view
    // ('board', 'list' or '' for the one kept) the view to open on.
    function mount(pane, channel, hooks) {
        board = state(pane, channel, hooks);

        if (board.hooks.view) {
            prefs.view = (board.hooks.view === 'list') ? 'list' : 'board';
        }

        var frame = kit().el('div', 'ws-bar-frame');

        frame.appendChild(toolbar(board));
        pane.appendChild(frame);
        pane.appendChild(board.filter.tags);
        board.body = kit().el('div', 'ws-cb-body');
        pane.appendChild(board.body);
        showView(board);
        draw(board);
        load(board);
    }

    // A board over another source of cards (a workflow table's rows).
    // source: texts, load({all}) -> Promise of the board's data,
    // move(item, drop) -> Promise of the sentence to show, open(item).
    function mountSource(pane, source) {
        board = state(pane, { id: 0 }, {});
        board.source = source;
        board.kit = window.PGWsKit || smallKit(source.texts || {});
        draw(board);
        load(board);
    }

    // The channel screen's regular look brought a new stamp: the board is
    // read again when something on it changed, unless a card is in the
    // reader's hand.
    function stamp(value) {
        if (!board || !value || (value === board.stamp) || board.drag || board.lifted || !document.body.contains(board.pane)) {
            return;
        }

        board.stamp = value;
        load(board);
    }

    window.PGWsChannelBoard = {
        mount: mount,
        mountSource: mountSource,
        stamp: stamp,
        // The view the tab shows, for the screen's address.
        view: function () { return prefs.view; },
        isOpen: function () { return !!(board && document.body.contains(board.pane)); }
    };
})();
