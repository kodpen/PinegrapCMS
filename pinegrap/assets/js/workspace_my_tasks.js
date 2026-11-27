/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the list of My Tasks: the open tasks gathered by date under
 * labels that fold, a task one line.
 *
 * assets/js/workspace.js asks this file for the list and lends it its own
 * helpers (api, t, el, icon, button, ...) and the parts of a task row it
 * keeps (the row's menu, the progress bar):
 *   PGWsMyTasks.list(container, tasks, grouped, helpers, parts, onChange)
 *
 * The server sends each task's group (due_group: overdue, today, week,
 * later, none) worked out on the reader's day (ws_task_due_group(),
 * includes/workspace/tasks.php); the list keeps the server's order inside
 * a group. The groups folded stay folded in this browser (localStorage
 * 'ws-tasks-folded'). A channel's Tasks tab and a record's window keep the
 * rows of taskRows() in workspace.js.
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

    var FOLD_KEY = 'ws-tasks-folded';

    // The groups in the order they stand, each with its label's text key.
    var GROUPS = [['overdue', 'mt_overdue'], ['today', 'today'], ['week', 'mt_week'], ['later', 'mt_later'], ['none', 'mt_no_date']];

    function readFolded() {
        try {
            var kept = JSON.parse(window.localStorage.getItem(FOLD_KEY) || '{}');

            return (kept && (typeof kept === 'object')) ? kept : {};
        } catch (error) {
            return {};
        }
    }

    function writeFolded(group, shut) {
        var kept = readFolded();

        if (shut) {
            kept[group] = 1;
        } else {
            delete kept[group];
        }

        try {
            window.localStorage.setItem(FOLD_KEY, JSON.stringify(kept));
        } catch (error) {
            // Not kept: the group opens again next time.
        }
    }

    // The Mark done circle at the start of a row. A task the reader may not
    // change, or a closed one, shows its state in its place instead.
    function doneMark(task, h, onChange) {
        if (task.can_edit && task.open) {
            var node = h.button('pg-icon-button pg-icon-button-sm ws-mt-done', '', 'bi-circle', h.t('mark_done'));

            node.appendChild(h.icon('bi-check-circle', 'ws-mt-done-tick'));
            node.addEventListener('click', function (event) {
                event.stopPropagation();
                node.disabled = true;
                h.api('ws_task_status', { task_id: task.id, status: 'done' }).then(function () {
                    h.toast(h.t('task_saved'), 'success');
                    onChange();
                }).catch(function (error) {
                    node.disabled = false;
                    h.fail(error);
                });
            });

            return node;
        }

        var mark = h.el('span', 'ws-mt-done is-still');

        mark.appendChild(h.icon(task.open ? 'bi-circle' : ((task.status === 'done') ? 'bi-check-circle-fill' : 'bi-dash-circle')));
        mark.title = task.status_label || '';

        return mark;
    }

    // One task: the circle, the number, the title, when it is due, the
    // priority (High and Urgent alone) and the people's initials; under it
    // only what the line cannot say, the agent channel, the application
    // that made it and the checklist's progress.
    function row(task, h, parts, onChange) {
        var node = h.el('div', 'pg-list-row ws-mt-row' + (task.open ? '' : ' is-read'));
        var main = h.el('div', 'pg-list-row-main');
        var head = h.el('div', 'ws-mt-head');
        var name = h.el('span', 'pg-list-row-title ws-mt-name');
        var meta = h.el('span', 'ws-mt-meta');
        // An open task past its due date on the reader's day (ws_tasks).
        var late = !!task.overdue;

        node.setAttribute('data-ws-task', String(task.id));
        node.appendChild(doneMark(task, h, onChange));

        name.appendChild(h.el('span', 'ws-mt-number', task.number));
        name.appendChild(document.createTextNode(task.title));
        head.appendChild(name);

        if (task.due_date) {
            meta.appendChild(h.el('span', 'ws-mt-due' + (late ? ' is-late' : ''), task.due_label));
        }

        if ((task.priority === 'urgent') || (task.priority === 'high')) {
            meta.appendChild(h.el('span', 'pg-pill ' + ((task.priority === 'urgent') ? 'pg-tone-rose' : 'pg-tone-amber'), (h.boot().priorities || {})[task.priority] || task.priority));
        }

        // Letters only: a photo is not drawn on this line.
        if ((task.assignees || []).length) {
            meta.appendChild(h.avatarStack('ws-mt-people', task.assignees.map(function (person) {
                return { name: person.name, initials: person.initials, tone: person.tone, avatar_kind: person.avatar_kind };
            }), 3));
        }

        head.appendChild(meta);
        main.appendChild(head);

        var sub = h.el('div', 'ws-mt-sub');

        if (task.agent_channel_name) {
            var channel = h.el('span', 'ws-mt-sub-part');

            channel.appendChild(h.icon('bi-robot'));
            channel.appendChild(document.createTextNode('#' + task.agent_channel_name));
            sub.appendChild(channel);
        }

        if (task.made_by_label) {
            var madeBy = h.el('span', 'ws-mt-sub-part');

            madeBy.appendChild(h.icon('bi-robot'));
            madeBy.appendChild(document.createTextNode(task.made_by_label));
            sub.appendChild(madeBy);
        }

        if (task.progress) {
            var share = h.el('span', 'ws-mt-sub-part ws-row-progress');

            share.appendChild(parts.progressBar(task.progress, true));
            share.appendChild(h.el('span', '', h.t('percent', task.progress.percent)));
            share.title = h.t('progress_text', task.progress.done, task.progress.total, task.progress.percent);
            sub.appendChild(share);
        }

        if (sub.firstChild) {
            main.appendChild(sub);
        }

        node.appendChild(main);

        function openTask() {
            h.openTask(task.id, onChange);
        }

        node.addEventListener('click', openTask);
        h.pressKeys(node, openTask);
        parts.menu(node, task, onChange);

        return node;
    }

    // A group: its label, a button that folds the rows under it, with the
    // count; the rows.
    function group(key, label, tasks, h, parts, onChange, shut) {
        var box = h.el('section', 'ws-mt-group');
        var heading = h.el('h2', 'ws-mt-group-head');
        var toggle = h.el('button', 'pg-group-label ws-mt-group-toggle');
        var chevron = h.icon(shut ? 'bi-chevron-right' : 'bi-chevron-down', 'ws-mt-group-chevron');
        var rows = h.el('div', 'ws-mt-rows');

        rows.id = h.nextId('ws-mt-rows-');
        rows.hidden = shut;
        box.setAttribute('data-ws-due-group', key);

        toggle.type = 'button';
        toggle.setAttribute('aria-controls', rows.id);
        toggle.appendChild(chevron);
        toggle.appendChild(document.createTextNode(label));
        toggle.appendChild(h.el('span', 'pg-group-label-count', String(tasks.length)));

        function set(folded) {
            shut = folded;
            rows.hidden = folded;
            toggle.setAttribute('aria-expanded', folded ? 'false' : 'true');
            toggle.title = folded ? h.t('side_expand') : h.t('side_collapse');
            chevron.classList.toggle('bi-chevron-right', folded);
            chevron.classList.toggle('bi-chevron-down', !folded);
        }

        toggle.addEventListener('click', function () {
            set(!shut);
            writeFolded(key, shut);
        });
        set(shut);

        tasks.forEach(function (task) {
            rows.appendChild(row(task, h, parts, onChange));
        });

        heading.appendChild(toggle);
        box.appendChild(heading);
        box.appendChild(rows);

        return box;
    }

    // The list: grouped by date (the open tasks without a search), else
    // one list in the server's order.
    function list(container, tasks, grouped, h, parts, onChange) {
        var box = h.el('div', 'ws-mt-list');

        h.clear(container);

        if (!grouped) {
            tasks.forEach(function (task) {
                box.appendChild(row(task, h, parts, onChange));
            });
            container.appendChild(box);

            return;
        }

        var folded = readFolded();
        var known = {};

        GROUPS.forEach(function (item) {
            known[item[0]] = true;
        });

        GROUPS.forEach(function (item) {
            var inGroup = tasks.filter(function (task) {
                // A task the server sent without a group stands with those
                // without a date rather than out of the list.
                return (known[task.due_group] ? task.due_group : 'none') === item[0];
            });

            if (inGroup.length) {
                box.appendChild(group(item[0], h.t(item[1]), inGroup, h, parts, onChange, !!folded[item[0]]));
            }
        });

        container.appendChild(box);
    }

    window.PGWsMyTasks = { list: list };
})();
