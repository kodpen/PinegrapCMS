/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the Board tab of a channel: the channel's tasks, decisions and
 * files as cards in columns (ws_channel_board,
 * includes/workspace/channel_board.php), grouped by status, by kind, by the
 * people on them or by when they are due.
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
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var GROUPS = ['status', 'type', 'person', 'date'];

    // How the reader last grouped and filtered, for the next channel opened
    // on this page.
    var prefs = { group: 'status', priority: '', mine: false, cancelled: false };

    // The board on the screen; one at a time.
    var board = null;

    function kit() {
        return window.PGWsKit || null;
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

        k.api('ws_channel_board', {
            channel_id: self.channel.id,
            group_by: prefs.group,
            priority: prefs.priority,
            person: self.person,
            mine: prefs.mine ? 1 : 0,
            q: self.q,
            cancelled: prefs.cancelled ? 1 : 0,
            done_all: self.doneAll ? 1 : 0
        }).then(function (data) {
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

    function toolbar(self) {
        var k = kit();
        var bar = k.el('div', 'pg-toolbar ws-cb-bar');

        var groups = k.el('div', 'btn-group btn-group-sm');
        groups.setAttribute('role', 'group');
        groups.setAttribute('aria-label', t('cb_group'));

        GROUPS.forEach(function (key) {
            var item = k.button('btn btn-ghost' + (prefs.group === key ? ' active' : ''), t('cb_by_' + key));
            item.setAttribute('aria-pressed', prefs.group === key ? 'true' : 'false');
            item.addEventListener('click', function () {
                if (prefs.group !== key) {
                    prefs.group = key;
                    self.current = 0;
                    draw(self);
                    load(self);
                }
            });
            groups.appendChild(item);
        });

        bar.appendChild(groups);

        // The same urgency choice as the planning board and the task lists.
        bar.appendChild(k.priorityFilter(prefs.priority, function (value) {
            prefs.priority = value;
            load(self);
        }));

        var people = [[0, t('cb_all_people')], [-1, t('cb_nobody')]];

        ((self.data && self.data.members) || []).forEach(function (member) {
            people.push([member.id, member.name]);
        });

        var who = k.select(people, self.person);
        who.classList.add('w-auto');
        who.setAttribute('aria-label', t('who'));
        who.addEventListener('change', function () {
            self.person = parseInt(who.value, 10) || 0;
            load(self);
        });
        bar.appendChild(who);

        bar.appendChild(toggle(self, 'ws-cb-mine', t('cb_mine'), prefs.mine, function (on) { prefs.mine = on; }));

        if (prefs.group === 'status') {
            bar.appendChild(toggle(self, 'ws-cb-cancelled', t('cb_cancelled'), prefs.cancelled, function (on) { prefs.cancelled = on; }));
        }

        bar.appendChild(k.el('div', 'pg-toolbar-grow'));

        var searchGroup = k.el('div', 'input-group input-group-sm rounded-pill pg-toolbar-search');
        var search = k.el('input', 'form-control');
        search.type = 'search';
        search.value = self.q;
        search.placeholder = t('cb_search');
        search.setAttribute('aria-label', search.placeholder);
        search.addEventListener('input', k.debounce(function () {
            self.q = search.value.trim();
            load(self);
        }, 300));
        searchGroup.appendChild(search);
        bar.appendChild(searchGroup);

        return bar;
    }

    function toggle(self, id, label, on, set) {
        var k = kit();
        var wrap = k.el('div', 'form-check form-switch mb-0');
        var input = k.el('input', 'form-check-input');
        input.type = 'checkbox';
        input.id = id;
        input.checked = !!on;

        var text = k.el('label', 'form-check-label small', label);
        text.htmlFor = id;

        input.addEventListener('change', function () {
            set(input.checked);
            load(self);
        });

        wrap.appendChild(input);
        wrap.appendChild(text);

        return wrap;
    }

    function draw(self) {
        var k = kit();
        var pane = self.pane;
        var hadFocus = self.focusKey;

        clear(pane);
        pane.appendChild(toolbar(self));

        if (!self.data) {
            pane.appendChild(k.el('div', 'small text-body-secondary', t('loading')));
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
        picker.classList.add('w-auto', 'ws-cb-picker', 'mb-2');
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
            head.appendChild(k.icon(column.icon || 'bi-columns', 'ws-cb-col-icon'));
        }

        head.appendChild(k.el('span', 'ws-cb-col-title', column.title));
        head.appendChild(k.el('span', 'ws-cb-col-count', column.items.length));

        // A new task with the column's value: its status, its person, its day.
        if (column.add && self.data.can_post) {
            var add = k.button('btn btn-sm btn-ghost ws-cb-add', '', 'bi-plus-lg', t('cb_add'));
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
            var more = k.button('btn btn-sm btn-link ws-cb-more', t('cb_more_done', column.more));
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
            otherCard(card, item);
        }

        var movable = (item.kind === 'task') && item.can_edit && (self.data.group_by !== 'type');

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
            var due = k.el('span', 'ws-cb-due' + (item.overdue ? ' text-danger' : ''));
            due.appendChild(k.icon('bi-calendar-event', 'me-1'));
            due.appendChild(document.createTextNode(item.due_label));
            meta.appendChild(due);
        }

        if (item.progress) {
            var share = k.el('span', 'ws-cb-progress');
            var bar = k.el('span', 'ws-cb-progress-bar');
            var fill = k.el('span');

            fill.style.width = item.progress.percent + '%';
            bar.appendChild(fill);
            share.appendChild(bar);
            share.appendChild(document.createTextNode(item.progress.done + '/' + item.progress.total));
            share.title = t('progress_text', item.progress.done, item.progress.total, item.progress.percent);
            meta.appendChild(share);
        }

        if (item.notes_count) {
            var notes = k.el('span', 'ws-cb-notes');
            notes.appendChild(k.icon('bi-journal-text', 'me-1'));
            notes.appendChild(document.createTextNode(String(item.notes_count)));
            notes.title = t('cb_notes', item.notes_count);
            meta.appendChild(notes);
        }

        if (item.assignees.length) {
            var faces = k.el('span', 'ws-avatars ws-cb-faces');

            item.assignees.slice(0, 4).forEach(function (person) {
                var face = k.avatar(person);
                face.title = person.name;
                faces.appendChild(face);
            });

            if (item.assignees.length > 4) {
                faces.appendChild(k.el('span', 'ws-cb-more-faces', '+' + (item.assignees.length - 4)));
            }

            meta.appendChild(faces);
        }

        if (meta.childNodes.length) {
            card.appendChild(meta);
        }
    }

    function otherCard(card, item) {
        var k = kit();
        var icons = { decision: 'bi-patch-check', file: 'bi-file-earmark', pin: 'bi-pin-angle', summary: 'bi-journal-text' };
        var top = k.el('div', 'ws-cb-card-top');

        if ((item.kind === 'file') && item.thumb) {
            var picture = k.el('img', 'ws-cb-thumb');
            picture.src = item.thumb;
            picture.alt = '';
            picture.loading = 'lazy';
            card.appendChild(picture);
        }

        top.appendChild(k.icon(icons[item.kind] || 'bi-dot', 'ws-cb-kind-icon'));

        if ((item.kind === 'file') && item.ext) {
            top.appendChild(k.el('span', 'ws-file-ext', item.ext));
        }

        card.appendChild(top);
        card.appendChild(k.el('div', 'ws-cb-title', item.title));

        if (item.sub) {
            card.appendChild(k.el('div', 'ws-cb-sub', item.sub));
        }

        card.title = (item.kind === 'file') ? t('cb_open_file') : ((item.kind === 'summary') ? t('cb_open_summary') : t('cb_open_message'));
    }

    // ── Acting ──

    function openCard(self, item) {
        var k = kit();

        if (item.kind === 'task') {
            k.openTask(item.id, null, reload);
        } else if (item.kind === 'file') {
            window.open(item.url, '_blank', 'noopener');
        } else if (item.kind === 'summary') {
            self.hooks.openSummary();
        } else if (item.message_id) {
            self.hooks.openMessage(item.message_id);
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

    // hooks: openMessage(id), openSummary(), taskDefaults(), refresh()
    function mount(pane, channel, hooks) {
        board = {
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
            drag: null,
            lifted: null,
            focusKey: '',
            columnNodes: []
        };

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
        stamp: stamp,
        isOpen: function () { return !!(board && document.body.contains(board.pane)); }
    };
})();
