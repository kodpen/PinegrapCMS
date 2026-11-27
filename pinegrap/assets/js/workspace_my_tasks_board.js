/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - My Tasks' layouts: the bar's control of two icons, List and
 * Board, kept per browser (localStorage 'ws-tasks-layout'), and the Board
 * layout: the tasks the bar chose as cards in a column a status.
 *
 * The board is the channel's board component over a source
 * (window.PGWsChannelBoard.mountSource(), workspace_board_channel.js), as a
 * Data table's board is. The columns come from the server with the list
 * (ws_tasks with layout=board, ws_tasks_status_board() in
 * includes/workspace/channel_board.php). A card dragged to another column,
 * or put there by its menu's "Move to…", writes the status through
 * ws_task_status, the action the task drawer's status control uses; a
 * refusal shows the store's sentence and the board is read again, so the
 * card goes back. On a phone the columns scroll sideways, one a screen.
 *
 * assets/js/workspace.js asks this file for both and lends it its helpers:
 *   PGWsMyTasksBoard.control(helpers, onPick) -> { node, value(), shown(on) }
 *   PGWsMyTasksBoard.mount(pane, helpers, {
 *       read() -> Promise of ws_tasks' board, openTask(id), menu(item, extra)
 *   })
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var LAYOUT_KEY = 'ws-tasks-layout';

    // The layouts: key, icon, text key.
    var LAYOUTS = [['list', 'bi-list-ul', 'mt_layout_list'], ['board', 'bi-kanban', 'mt_layout_board']];

    function storedLayout() {
        try {
            return (window.localStorage.getItem(LAYOUT_KEY) === 'board') ? 'board' : 'list';
        } catch (error) {
            return 'list';
        }
    }

    function storeLayout(layout) {
        try {
            window.localStorage.setItem(LAYOUT_KEY, layout);
        } catch (error) {
            // Private mode or a full store: the layout holds for this page.
        }
    }

    // The bar's control: a segmented control of two icons, each named for
    // the screen reader and the pointer.
    function control(h, onPick) {
        var node = h.el('div', 'pg-seg ws-mt-layout');
        var items = {};
        var current = storedLayout();

        node.setAttribute('role', 'group');
        node.setAttribute('aria-label', h.t('mt_layout'));

        function set(layout) {
            current = layout;

            Object.keys(items).forEach(function (key) {
                items[key].classList.toggle('is-active', key === layout);
                items[key].setAttribute('aria-pressed', (key === layout) ? 'true' : 'false');
            });
        }

        LAYOUTS.forEach(function (layout) {
            var item = h.el('button', 'pg-seg-item');
            var label = h.t(layout[2]);

            item.type = 'button';
            item.title = label;
            item.appendChild(h.icon(layout[1], ''));
            item.appendChild(h.el('span', 'visually-hidden', label));
            item.addEventListener('click', function () {
                if (current !== layout[0]) {
                    set(layout[0]);
                    storeLayout(layout[0]);
                    onPick(layout[0]);
                }
            });
            items[layout[0]] = item;
            node.appendChild(item);
        });

        set(current);

        return {
            node: node,
            value: function () { return current; },
            shown: function (on) { node.hidden = !on; }
        };
    }

    // "Move to…": the column asked in a dialog, the card's own left out.
    function askColumn(h, item, columns, moveTo) {
        var others = columns.filter(function (column) {
            return (column.drop !== null) && (column.drop !== item.status);
        });

        if (!others.length) {
            return;
        }

        var choice = h.form.select(others.map(function (column) { return [column.drop, column.title]; }), others[0].drop);

        h.ask(h.t('mt_move_q', item.number ? item.number + ' ' + item.title : item.title), h.t('mt_move'), false, h.form.field(h.t('mt_move_column'), choice)).then(function (yes) {
            if (yes) {
                moveTo(choice.value);
            }
        });
    }

    function mount(pane, h, options) {
        var columns = [];

        h.clear(pane);
        pane.className = 'ws-pane-board ws-mt-board';

        window.PGWsChannelBoard.mountSource(pane, {
            picker: false,
            slots: true,
            load: function () {
                return options.read().then(function (board) {
                    columns = (board && board.columns) || [];

                    return board;
                });
            },
            move: function (item, drop) {
                return h.api('ws_task_status', { task_id: item.id, status: drop }).then(function () {
                    return h.t('task_saved');
                });
            },
            open: function (item) {
                options.openTask(item.id);
            },
            menu: function (item, moveTo) {
                var extra = [];

                if (item.can_edit) {
                    extra.push('-');
                    extra.push({ icon: 'bi-arrows-move', label: h.t('mt_move_to'), action: function () { askColumn(h, item, columns, moveTo); } });
                }

                return options.menu(item, extra);
            }
        });
    }

    window.PGWsMyTasksBoard = {
        control: control,
        mount: mount
    };
})();
