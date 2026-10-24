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
 * one (includes/workspace/delegation.php). What a run proposes waits under
 * its answer, drawn as the channel draws a change, for the person who asked
 * to apply or set aside (includes/workspace/changes.php). While a run is
 * under way the part reads it again every few seconds; a run of Pinegrap AI
 * is carried on by the screens waiting for it (ws_ai_kick), one call at a
 * time and not more often than every few seconds, as the channel screen does.
 *
 * An MCP server's alias is one of the assistants (key mcp:<server id>), run
 * by Pinegrap AI. A call of its tools that writes waits on the task as a
 * card of type mcp_call that only the person who asked can run, with their
 * own account; the card and the picker offer to connect that account, and
 * the service's sign-in comes back to the task. A server that is an agent
 * itself runs the task on its own (agent mcp): it is carried on the same way
 * as Pinegrap AI, by the screens waiting for it (ws_mcp_agent_kick). When
 * its tool changes things on the service, the run waits first as a card of
 * the same type, and once the person who asked applies it, the agent's
 * answer comes into the run.
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

    // The call that carries a run on, by its assistant; Claude's routine
    // runs on its own.
    function kickFor(agent) {
        return (agent === 'ai') ? 'ws_ai_kick' : ((agent === 'mcp') ? 'ws_mcp_agent_kick' : '');
    }

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

    // ── A change a run proposed ─────────────────────────────────────────

    // The card of one proposed change, as the channel draws it
    // (changesNode in assets/js/workspace.js), with the decision taken on
    // the task instead of in a channel. done(change) runs after it is
    // applied or set aside.
    function changeCard(change, agent, h, done, taskId) {
        var card = h.el('div', 'ws-draft ws-change ws-change-' + change.status + ' ws-change-do-' + change.action);
        var head = h.el('div', 'ws-draft-head');
        var icons = { create: 'bi-plus-square', 'delete': 'bi-trash3', add: 'bi-box-arrow-in-right', remove: 'bi-box-arrow-right' };

        head.appendChild(h.icon(change.status === 'applied' ? 'bi-check2-circle' : (icons[change.action] || 'bi-pencil-square')));

        var what = h.el('span', 'ws-change-record');

        if (change.record_html) {
            html(what, change.record_html);
        } else if (change.record_label) {
            what.appendChild(h.el('b', '', change.record_label));
        }

        head.appendChild(what);

        if (change.action_label) {
            head.appendChild(h.el('span', 'ws-change-action', change.action_label));
        }

        head.appendChild(h.el('span', 'ws-change-type', change.type_label));
        card.appendChild(head);

        if (change.hidden) {
            card.appendChild(h.el('div', 'ws-draft-meta', h.t('change_hidden', change.count)));
        } else if (change.fields.length) {
            var list = h.el('dl', 'ws-change-fields');

            change.fields.forEach(function (field) {
                var value = h.el('dd');

                list.appendChild(h.el('dt', '', field.label));

                if (field.html) {
                    value.appendChild(html(h.el('span', 'ws-change-to'), field.html));
                } else if ((field.to === '') && (field.from !== '')) {
                    value.appendChild(h.el('span', 'ws-change-held', field.from));
                } else {
                    if (field.from !== '') {
                        value.appendChild(h.el('span', 'ws-change-from', field.from));
                        value.appendChild(h.icon('bi-arrow-right', 'ws-change-arrow'));
                    }

                    value.appendChild(h.el('span', 'ws-change-to', field.to));
                }

                list.appendChild(value);
            });

            card.appendChild(list);
        }

        if (change.reason) {
            card.appendChild(h.el('div', 'ws-draft-text', change.reason));
        }

        if (change.warning && (change.status === 'pending')) {
            var warning = h.el('div', 'ws-change-warning');
            warning.appendChild(h.icon('bi-exclamation-triangle', 'me-1'));
            warning.appendChild(document.createTextNode(change.warning));
            card.appendChild(warning);
        }

        var actions = h.el('div', 'ws-draft-actions');
        var buttons = [];

        function decide(action) {
            buttons.forEach(function (other) { other.disabled = true; });

            h.api(action, { change_id: change.id }).then(function () {
                if (action === 'ws_ai_change_apply') {
                    h.toast(h.t(change.agent ? 'mcp_agent_sent' : 'dg_change_done'), 'success');
                }

                done(change);
            }).catch(function (error) {
                h.fail(error);
                done(change);
            });
        }

        if (change.can_apply) {
            var apply = h.button('btn btn-sm btn-primary rounded-pill px-3', h.t('change_apply'), 'bi-check2');

            apply.addEventListener('click', function () {
                var go = change.warning ? h.ask(change.warning, h.t('change_apply'), true) : Promise.resolve(true);

                go.then(function (yes) {
                    if (yes) {
                        decide('ws_ai_change_apply');
                    }
                });
            });

            buttons.push(apply);
            actions.appendChild(apply);
        }

        if (change.can_dismiss) {
            var dismiss = h.button('btn btn-sm btn-ghost', h.t('change_dismiss'), 'bi-x-lg');

            dismiss.addEventListener('click', function () {
                decide('ws_ai_change_dismiss');
            });

            buttons.push(dismiss);
            actions.appendChild(dismiss);
        }

        // A call of an MCP server's tool runs with the asker's own account,
        // connected from here; the sign-in comes back to this task.
        if (change.connect) {
            var connect = h.button('btn btn-sm btn-outline-primary rounded-pill px-3', h.t(change.connect.again ? 'mcp_connect_again' : 'mcp_connect_me'), 'bi-box-arrow-in-right');

            connect.addEventListener('click', function () {
                connect.disabled = true;
                h.mcpConnect(change.connect.server_id, 0, function () {
                    connect.disabled = false;
                }, taskId);
            });

            actions.appendChild(connect);
        }

        if (actions.childNodes.length) {
            card.appendChild(actions);
        }

        if ((change.status === 'pending') && !change.can_apply) {
            card.appendChild(h.el('div', 'ws-draft-done', change.blocked || (change.no_right ? h.t('change_no_right') : h.t('change_waiting', change.asker))));
        } else if ((change.status === 'pending') && change.account) {
            var account = h.el('div', 'ws-draft-done', h.t('mcp_account', change.account.alias) + ' ');
            var off = h.el('a', '', h.t('mcp_disconnect_me'));

            off.href = '#';
            off.addEventListener('click', function (event) {
                event.preventDefault();

                h.ask(h.t('mcp_disconnect_ask', change.account.alias), h.t('mcp_disconnect_me'), true).then(function (yes) {
                    if (!yes) {
                        return;
                    }

                    h.api('ws_mcp_disconnect_me', { server_id: change.account.server_id }).then(function () {
                        h.toast(h.t('mcp_disconnected'), 'success');
                        done(change);
                    }).catch(h.fail);
                });
            });

            account.appendChild(off);
            card.appendChild(account);
        } else if (change.status === 'applied') {
            card.appendChild(h.el('div', 'ws-draft-done', h.t('change_applied', change.decided_by, change.decided)));
        } else if (change.status === 'dismissed') {
            card.appendChild(h.el('div', 'ws-draft-done', h.t('change_dismissed', change.decided_by)));
        } else if (change.status === 'stale') {
            card.appendChild(h.el('div', 'ws-draft-done text-warning-emphasis', change.proposer ? h.t('proposal_change_stale', change.proposer) : h.t((agent === 'ai') ? 'ai_change_stale' : 'change_stale')));
        } else if (change.status === 'failed') {
            card.appendChild(h.el('div', 'ws-draft-done text-danger-emphasis', h.t('change_failed', change.error)));
        }

        return card;
    }

    function changesBox(item, h, done, taskId) {
        var box = h.el('div', 'ws-drafts ws-changes ws-task-delegate-changes');

        // A run of an MCP server's own agent waits here for the approval
        // its tool asks for before it is sent (mcp_agent.php). Changes an
        // application proposed on the task (proposals.php) are headed by
        // its name.
        var proposer = (item.changes[0] && item.changes[0].proposer) || '';

        box.appendChild(h.el('div', 'ws-drafts-title', proposer ? h.t('proposal_changes', proposer) : h.t((item.agent === 'mcp') ? 'mcp_agent_changes' : ((item.agent === 'ai') ? 'ai_changes' : 'claude_changes'))));

        item.changes.forEach(function (change) {
            box.appendChild(changeCard(change, item.agent, h, done, taskId));
        });

        return box;
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

                var kick = kickFor(state.open_agent);
                var step = kick ? h.api(kick).catch(function () {}) : Promise.resolve();

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
            }, kickFor(state.open_agent) ? POLL_AI : POLL_CLAUDE);
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

        // A proposal applied or set aside: the task drawn again when it was
        // the task or its notes that changed (a call of an MCP server's tool
        // leaves its decision as a note), the runs read again otherwise.
        function decided(change) {
            if (onChange) {
                onChange();
            }

            if ((change.type === 'task') || (change.type === 'task_note') || (change.type === 'mcp_call')) {
                h.openTask(task.id);
                return;
            }

            h.api('ws_task_delegation', { task_id: task.id }).then(function (data) {
                update(data.delegation);
            }).catch(function () {});
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

            if (item.changes && item.changes.length) {
                main.appendChild(changesBox(item, h, decided, task.id));
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
                var ready = state.assistants.filter(function (item) { return item.ready; });
                var options = state.assistants.map(function (item) {
                    var name = item.title ? (item.name + ' · ' + item.title) : item.name;

                    return [item.key, (item.ready || item.connect) ? name : (name + ' (' + h.t('dg_not_ready') + ')')];
                });

                // An assistant that is not set up stays in the list, so the
                // reader sees it exists, but cannot be chosen: the server
                // would only refuse it. An MCP server's alias that only
                // waits for the reader's own account can be chosen, and
                // offers to connect it. With none ready the picker opens on
                // nothing rather than on one that cannot work.
                if (!ready.length) {
                    options.unshift(['', h.t('dg_not_chosen')]);
                }

                picker = h.select(options, ready.length ? ready[0].key : '');

                Array.prototype.forEach.call(picker.options, function (option) {
                    option.disabled = (option.value !== '') && !state.assistants.some(function (item) {
                        return (item.key === option.value) && (item.ready || item.connect);
                    });
                });

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
            var connect = h.button('btn btn-sm btn-outline-primary rounded-pill px-3 d-none', h.t('mcp_connect_me'), 'bi-box-arrow-in-right');
            var hint = h.el('div', 'form-text mt-0 d-none');

            function chosen() {
                var key = withKey || (picker ? picker.value : '');

                return state.assistants.filter(function (item) { return item.key === key; })[0] || null;
            }

            // An alias whose sign-in waits for the reader's own account:
            // connect it first, then hand the task over.
            function refresh() {
                var item = chosen();
                var waits = !!(item && !item.ready && item.connect);

                send.classList.toggle('d-none', waits);
                connect.classList.toggle('d-none', !waits);
                hint.classList.toggle('d-none', !waits);
                hint.textContent = waits ? h.t('dg_connect_first', item.connect.alias) : '';
                send.disabled = !item;
            }

            send.addEventListener('click', function () {
                var agent = withKey || picker.value;

                if (agent === '') {
                    return;
                }

                act('ws_task_delegate', { agent: agent, text: text.value }, send, (withKey === '') ? h.t('dg_started') : h.t('dg_sent'));
            });

            connect.addEventListener('click', function () {
                var item = chosen();

                if (!item || !item.connect) {
                    return;
                }

                connect.disabled = true;
                h.mcpConnect(item.connect.server_id, 0, function () {
                    connect.disabled = false;
                }, task.id);
            });

            if (picker) {
                picker.addEventListener('change', refresh);
            }

            text.addEventListener('keydown', function (event) {
                if ((event.key === 'Enter') && (event.ctrlKey || event.metaKey)) {
                    event.preventDefault();
                    send.click();
                }
            });

            var actions = h.el('div', 'ws-task-delegate-actions');
            actions.appendChild(send);
            actions.appendChild(connect);
            form.appendChild(hint);
            form.appendChild(actions);
            refresh();

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

            if (state.waiting) {
                var waiting = h.el('div', 'ws-task-delegate-waiting');
                waiting.appendChild(h.icon('bi-hourglass-split', 'me-1'));
                waiting.appendChild(document.createTextNode(h.t('dg_waiting', state.waiting)));
                wrap.appendChild(waiting);
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
        if (task.delegate_name) {
            var tag = h.el('span', 'ws-task-delegate-tag ms-2');
            tag.appendChild(h.icon((task.delegate === 'ai') ? 'bi-cpu' : ((String(task.delegate).indexOf('mcp:') === 0) ? 'bi-plug' : 'bi-stars'), 'me-1'));
            tag.appendChild(document.createTextNode(task.delegate_name));
            tag.title = h.t('dg_with', task.delegate_name);
            container.appendChild(tag);
        }

        waitingTag(container, task, h);
    }

    // What the assistants proposed on a task and nobody has decided yet,
    // also on a task that was only asked about in a note.
    function waitingTag(container, task, h) {
        if (!task.waiting_changes) {
            return;
        }

        var tag = h.el('span', 'ws-task-delegate-tag ws-task-delegate-tag-waiting ms-2');
        tag.appendChild(h.icon('bi-hourglass-split', 'me-1'));
        tag.appendChild(document.createTextNode(h.t('dg_waiting_short', task.waiting_changes)));
        tag.title = h.t('dg_waiting', task.waiting_changes);
        container.appendChild(tag);
    }

    window.PGWsTaskDelegate = { drawer: drawer, rowTag: rowTag };
})();
