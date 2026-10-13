/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a task handed to an assistant, on the screens.
 *
 * assets/js/workspace.js asks this file for its parts and lends it its own
 * helpers (api, ask, t, el, ...):
 *   PGWsTaskDelegate.drawer(task, helpers, onChange) the Assistant part of the
 *                                                    drawer: { node, update(delegation) }
 *   PGWsTaskDelegate.rowTag(container, task, helpers) the assistant on a task row
 *
 * The server keeps the runs and decides who may start, stop or take back
 * one (includes/workspace/delegation.php). While a run is under way the
 * part reads it again every few seconds; a run of Pinegrap AI is carried on
 * by the screens waiting for it (ws_ai_kick), one call at a time and not
 * more often than every few seconds, as the channel screen does.
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

    var POLL_AI = 4000;
    var POLL_CLAUDE = 8000;

    // Server-rendered markup: escaped on the PHP side before it got here.
    function html(node, markup) {
        node.innerHTML = markup || '';
        return node;
    }

    function face(h, src, className) {
        var img = h.avatar({ avatar: src }, '');
        img.className = className;
        return img;
    }

    // ── The drawer ─────────────────────────────────────────────────────

    function drawer(task, h, onChange) {
        var wrap = h.el('div', 'ws-task-delegate');
        var state = task.delegation;
        var timer = null;
        var busy = false;

        // Read again only while the drawer that holds the part is open: a
        // drawer drawn again for another task leaves this part behind.
        function visible() {
            var panel = wrap.closest('.offcanvas');

            return document.body.contains(wrap) && (!panel || panel.classList.contains('show') || panel.classList.contains('showing'));
        }

        function schedule() {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }

            if (!state || !state.open) {
                return;
            }

            timer = setTimeout(function () {
                timer = null;

                if (!visible()) {
                    return;
                }

                var step = (state.open_agent === 'ai') ? h.api('ws_ai_kick').catch(function () {}) : Promise.resolve();

                step.then(function () {
                    return h.api('ws_task_delegation', { task_id: task.id });
                }).then(function (data) {
                    var was = state.open;

                    update(data.delegation);

                    if (was && !state.open && onChange) {
                        onChange();
                    }
                }).catch(function () {
                    schedule();
                });
            }, (state.open_agent === 'ai') ? POLL_AI : POLL_CLAUDE);
        }

        // What an action answers: the whole task, whose people may have
        // changed too (a task nobody was on gets the person who handed it
        // over), so the drawer is drawn again then.
        function done(data, message) {
            var before = (task.assignees || []).map(function (person) { return person.id; }).join(',');
            var after = (data.task.assignees || []).map(function (person) { return person.id; }).join(',');

            if (message) {
                h.toast(message, 'success');
            }

            if (onChange) {
                onChange();
            }

            if (before !== after) {
                h.openTask(task.id);
                return;
            }

            task.delegate = data.task.delegate;
            task.delegate_name = data.task.delegate_name;
            update(data.task.delegation);
        }

        function act(action, payload, control, message) {
            if (busy) {
                return Promise.resolve();
            }

            busy = true;

            if (control) {
                control.disabled = true;
            }

            payload.task_id = task.id;

            return h.api(action, payload).then(function (data) {
                busy = false;
                done(data, message);
            }).catch(function (error) {
                busy = false;

                if (control) {
                    control.disabled = false;
                }

                h.fail(error);
            });
        }

        function run(item) {
            var row = h.el('div', 'ws-task-note ws-task-delegate-run');
            var main = h.el('div', 'ws-task-note-main');
            var meta = h.el('div', 'ws-task-note-meta');

            row.appendChild(face(h, item.assistant.avatar, 'ws-task-note-avatar'));

            meta.appendChild(h.el('b', '', item.assistant.name));
            meta.appendChild(h.el('span', '', h.t('dg_asked', item.asked_by, item.asked_at)));
            main.appendChild(meta);

            var asked = h.el('div', 'ws-task-delegate-ask');

            if (item.instruction) {
                html(asked, item.instruction);
            } else {
                asked.textContent = h.t('dg_no_instruction');
                asked.classList.add('text-body-secondary');
            }

            main.appendChild(asked);

            if (item.answer) {
                main.appendChild(html(h.el('div', 'ws-msg-body ws-task-delegate-answer'), item.answer));
            }

            if (!item.answer || item.open) {
                var line = h.el('div', 'ws-claude-state ws-claude-' + item.status);
                line.appendChild(h.icon(item.icon));
                line.appendChild(h.el('span', '', item.label));
                main.appendChild(line);
            }

            row.appendChild(main);

            return row;
        }

        // A new run: the assistant to hand the task to, or the one it is
        // with, and what it should do.
        function composer() {
            var form = h.el('div', 'ws-task-delegate-form');
            var withKey = (state.delegate && state.assistants.some(function (item) { return item.key === state.delegate.key; })) ? state.delegate.key : '';
            var picker = null;

            if (withKey === '') {
                picker = h.select(state.assistants.map(function (item) {
                    return [item.key, item.ready ? item.name : (item.name + ' (' + h.t('dg_not_ready') + ')')];
                }), (state.assistants[0] || {}).key);
                picker.setAttribute('aria-label', h.t('dg_title'));
                form.appendChild(picker);
            }

            var text = h.el('textarea', 'form-control form-control-sm');
            text.rows = 2;
            text.maxLength = state.max;
            text.placeholder = (withKey === '') ? h.t('dg_instruction') : h.t('dg_follow_hint');
            text.setAttribute('aria-label', (withKey === '') ? h.t('dg_hand') : h.t('dg_follow'));
            form.appendChild(text);

            var send = h.button('btn btn-sm btn-outline-primary rounded-pill px-3', (withKey === '') ? h.t('dg_hand') : h.t('dg_send'), (withKey === '') ? 'bi-stars' : 'bi-send');

            send.addEventListener('click', function () {
                act('ws_task_delegate', { agent: withKey || picker.value, text: text.value }, send, (withKey === '') ? h.t('dg_started') : h.t('dg_sent'));
            });

            text.addEventListener('keydown', function (event) {
                if ((event.key === 'Enter') && (event.ctrlKey || event.metaKey)) {
                    event.preventDefault();
                    send.click();
                }
            });

            var actions = h.el('div', 'ws-task-delegate-actions');
            actions.appendChild(send);
            form.appendChild(actions);

            return form;
        }

        function draw() {
            h.clear(wrap);

            var head = h.el('div', 'ws-task-delegate-head');
            head.appendChild(h.icon('bi-stars', 'me-1'));
            head.appendChild(h.el('span', 'fw-semibold ws-grow', h.t('dg_title')));

            if (state.can_stop) {
                var stop = h.button('btn btn-sm btn-ghost py-0', h.t('dg_stop'), 'bi-stop-circle');
                stop.addEventListener('click', function () {
                    h.ask(h.t('dg_stop_confirm'), h.t('dg_stop'), true).then(function (yes) {
                        if (yes) {
                            act('ws_task_delegation_stop', { undelegate: 0 }, stop, '');
                        }
                    });
                });
                head.appendChild(stop);
            }

            wrap.appendChild(head);

            if (state.delegate) {
                var badge = h.el('div', 'ws-task-delegate-with');
                badge.appendChild(face(h, state.delegate.avatar, 'ws-task-delegate-face'));

                var who = h.el('div', 'ws-grow');
                who.appendChild(h.el('div', 'fw-semibold', h.t('dg_with', state.delegate.name)));
                who.appendChild(h.el('div', 'small text-body-secondary', h.t('dg_with_meta', state.delegate.by, state.delegate.at)));
                badge.appendChild(who);

                if (state.can_take) {
                    var back = h.button('btn btn-sm btn-ghost', h.t('dg_take_back'), 'bi-arrow-return-left');
                    back.addEventListener('click', function () {
                        h.ask(h.t('dg_take_confirm'), h.t('dg_take_back'), true).then(function (yes) {
                            if (yes) {
                                act('ws_task_delegation_stop', { undelegate: 1 }, back, '');
                            }
                        });
                    });
                    badge.appendChild(back);
                }

                wrap.appendChild(badge);
                wrap.appendChild(h.el('div', 'form-text mt-0 mb-2', h.t('dg_responsible')));
            }

            if (!state.runs.length) {
                wrap.appendChild(h.el('div', 'small text-body-secondary mb-2', h.t('dg_none')));
            } else {
                var list = h.el('div', 'ws-task-note-list');

                // Oldest first, as a conversation reads.
                state.runs.slice().reverse().forEach(function (item) {
                    list.appendChild(run(item));
                });

                wrap.appendChild(list);
            }

            if (state.can_hand && !state.open) {
                wrap.appendChild(composer());
            }
        }

        function update(delegation) {
            if (!delegation) {
                return;
            }

            state = delegation;
            task.delegation = delegation;
            draw();
            schedule();
        }

        draw();
        schedule();

        return { node: wrap, update: update };
    }

    // ── A task row ─────────────────────────────────────────────────────

    function rowTag(container, task, h) {
        if (!task.delegate_name) {
            return;
        }

        var tag = h.el('span', 'ws-task-delegate-tag ms-2');
        tag.appendChild(h.icon((task.delegate === 'ai') ? 'bi-cpu' : 'bi-stars', 'me-1'));
        tag.appendChild(document.createTextNode(task.delegate_name));
        tag.title = h.t('dg_with', task.delegate_name);
        container.appendChild(tag);
    }

    window.PGWsTaskDelegate = { drawer: drawer, rowTag: rowTag };
})();
