/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - tasks that wait for other tasks, on the screens.
 *
 * assets/js/workspace.js asks this file for its parts and lends it its own
 * helpers (api, ask, t, el, ...):
 *   PGWsTaskLinks.drawer(task, helpers, onChange)  the Dependencies part of the drawer
 *   PGWsTaskLinks.rowTag(container, task, helpers) the lock on a task row
 *   PGWsTaskLinks.confirmStart(blockedBy, helpers) asks before a blocked task
 *                                                  is started; resolves true to go on
 *
 * The server keeps the links and refuses one that would make tasks wait for
 * each other (includes/workspace/task_links.php). A blocked task is never
 * stopped from starting here, only asked about.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    function numbers(blockedBy) {
        return (blockedBy || []).map(function (item) { return item.number; }).join(', ');
    }

    // ── The drawer ─────────────────────────────────────────────────────

    function drawer(task, h, onChange) {
        var wrap = h.el('div', 'ws-task-links');

        function changed(links) {
            task.links = links;
            draw();

            if (onChange) {
                onChange();
            }
        }

        function item(other, removable) {
            var row = h.el('div', 'ws-task-links-row' + (other.open ? '' : ' ws-task-links-done'));

            row.appendChild(h.icon(other.open ? 'bi-lock' : 'bi-check2-circle', other.open ? 'text-warning' : 'text-success'));

            var label = h.el(other.visible ? 'a' : 'span', 'ws-grow text-truncate', other.visible ? (other.number + ' · ' + other.title) : (other.number + ' · ' + h.t('tl_task_hidden')));

            if (other.visible) {
                label.href = '#';
                label.addEventListener('click', function (event) {
                    event.preventDefault();
                    h.openTask(other.id);
                });
            }

            row.appendChild(label);

            if (other.status_label) {
                row.appendChild(h.el('span', 'badge text-bg-light', other.status_label));
            }

            if (removable) {
                var remove = h.button('btn btn-sm btn-ghost py-0 px-1', '', 'bi-x-lg', h.t('tl_remove'));
                remove.addEventListener('click', function () {
                    remove.disabled = true;
                    h.api('ws_task_link_remove', { task_id: task.id, blocker_id: other.id }).then(function (data) {
                        changed(data.links);
                    }).catch(function (error) {
                        remove.disabled = false;
                        h.fail(error);
                    });
                });
                row.appendChild(remove);
            }

            return row;
        }

        // A task to wait for, found by its number or its title among the
        // ones the reader can see (the # picker's search).
        function finder() {
            var box = h.el('div', 'ws-task-links-find position-relative mt-1');
            var input = h.el('input', 'form-control form-control-sm');
            var list = h.el('div', 'ws-task-links-results list-group d-none');
            var serial = 0;

            input.type = 'search';
            input.placeholder = h.t('tl_add');
            input.setAttribute('aria-label', input.placeholder);

            var run = h.debounce(function () {
                var mine = ++serial;
                var query = input.value.trim();

                if (query === '') {
                    list.classList.add('d-none');
                    return;
                }

                h.api('ws_ref_search', { type: 'task', q: query }).then(function (data) {
                    if (mine !== serial) {
                        return;
                    }

                    h.clear(list);

                    data.items.filter(function (found) { return found.id !== task.id; }).forEach(function (found) {
                        var pick = h.button('list-group-item list-group-item-action small text-start', found.label);
                        pick.addEventListener('click', function () {
                            list.classList.add('d-none');
                            input.value = '';
                            h.api('ws_task_link_add', { task_id: task.id, blocker_id: found.id }).then(function (result) {
                                changed(result.links);
                            }).catch(h.fail);
                        });
                        list.appendChild(pick);
                    });

                    if (!list.firstChild) {
                        list.appendChild(h.el('div', 'list-group-item small text-body-secondary', h.t('nothing_found')));
                    }

                    list.classList.remove('d-none');
                }).catch(h.fail);
            }, 250);

            input.addEventListener('input', run);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    list.classList.add('d-none');
                }
            });

            box.appendChild(input);
            box.appendChild(list);

            return box;
        }

        function draw() {
            var links = task.links;

            h.clear(wrap);

            var head = h.el('div', 'ws-task-links-head');
            head.appendChild(h.icon('bi-diagram-2', 'me-1'));
            head.appendChild(h.el('span', 'fw-semibold', h.t('tl_title')));
            wrap.appendChild(head);

            wrap.appendChild(h.el('div', 'small text-body-secondary mt-1', h.t('tl_blocked_by')));

            if (!links.blocked_by.length) {
                wrap.appendChild(h.el('div', 'small text-body-secondary ms-3', h.t('tl_none')));
            }

            links.blocked_by.forEach(function (other) {
                wrap.appendChild(item(other, links.can_edit));
            });

            if (links.can_edit && (links.blocked_by.length < links.max)) {
                wrap.appendChild(finder());
            }

            if (links.blocks.length) {
                wrap.appendChild(h.el('div', 'small text-body-secondary mt-2', h.t('tl_blocks')));

                links.blocks.forEach(function (other) {
                    wrap.appendChild(item(other, false));
                });
            }
        }

        draw();

        return wrap;
    }

    // ── A task row ─────────────────────────────────────────────────────

    function rowTag(container, task, h) {
        if (!task.open || !task.blocked_by || !task.blocked_by.length) {
            return;
        }

        var tag = h.el('span', 'ws-task-links-tag ms-2');
        tag.appendChild(h.icon('bi-lock', 'me-1'));
        tag.appendChild(document.createTextNode(h.t('tl_waits', numbers(task.blocked_by))));
        container.appendChild(tag);
    }

    // ── Starting a blocked task ────────────────────────────────────────

    function confirmStart(blockedBy, h) {
        var list = (typeof blockedBy === 'string') ? blockedBy : numbers(blockedBy);

        if (!list) {
            return Promise.resolve(true);
        }

        return h.ask(h.t('tl_start_confirm', list), h.t('tl_start_anyway'), true);
    }

    window.PGWsTaskLinks = {
        drawer: drawer,
        rowTag: rowTag,
        confirmStart: confirmStart
    };
})();
