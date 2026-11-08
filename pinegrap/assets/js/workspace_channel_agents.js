/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agents of a channel in its settings drawer: the agents
 * added to it, each with what wakes it here (a mention, every message, a
 * prefix, replies to its own messages), its tier and the loop-guard switch,
 * and the registered agents it can still take. Whoever may post in the
 * channel, in a private one only a member, adds, changes and removes them;
 * the tier is the channel owner's. The server decides both
 * (includes/workspace/channel_agents_panel.php).
 *
 * assets/js/workspace.js mounts it through window.PGWsChannelAgents.mount()
 * and lends its helpers through window.PGWsKit. Every text comes from the
 * #ws-config block.
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

    var seq = 0;

    function nextId() {
        seq += 1;
        return 'ws-cha-' + seq;
    }

    /**
     * Draws a channel's agents into a node of the settings drawer. The
     * section stays hidden while the registry is not there, or when the
     * channel has no agent and no agent is registered.
     *
     * @param {HTMLElement} holder
     * @param {object}      channel the channel as the screen holds it (id)
     */
    function mount(holder, channel) {
        var h = window.PGWsKit;

        if (!h || !holder || !channel) {
            return;
        }

        var t = h.t;
        var el = h.el;
        var root = el('div', 'ws-channel-agents mb-5 d-none');

        holder.appendChild(root);

        function labelled(label, field, help) {
            var row = el('div', 'mb-3');
            var caption = el('label', 'form-label small', label);

            field.id = nextId();
            caption.setAttribute('for', field.id);
            row.appendChild(caption);
            row.appendChild(field);

            if (help) {
                row.appendChild(el('div', 'form-text', help));
            }

            return row;
        }

        function agentTitle(agent) {
            var head = el('div', 'd-flex align-items-center gap-2 flex-wrap');

            head.appendChild(el('span', 'fw-semibold', agent.name || agent.handle));
            head.appendChild(el('span', 'small fg-secondary', '@' + agent.handle));

            if (!agent.active) {
                head.appendChild(el('span', 'badge theme-warning', t('cha_paused')));
            }

            return head;
        }

        function rowNode(agent, state) {
            var item = el('div', 'list-group-item');
            var top = el('div', 'd-flex align-items-start gap-3 mb-3');
            var title = agentTitle(agent);

            title.classList.add('flex-grow-1', 'min-w-0');
            top.appendChild(title);
            item.appendChild(top);

            var wake = h.select([
                ['mention', t('cha_wake_mention')],
                ['all', t('cha_wake_all')],
                ['prefix', t('cha_wake_prefix')],
                ['replies', t('cha_wake_replies')]
            ], agent.wake_rule);
            item.appendChild(labelled(t('cha_wake'), wake));

            var prefix = el('input', 'form-control form-control-sm');
            prefix.type = 'text';
            prefix.maxLength = 32;
            prefix.value = agent.wake_prefix || '';
            var prefixRow = labelled(t('cha_prefix'), prefix, t('cha_prefix_help'));
            item.appendChild(prefixRow);

            function showPrefix() {
                prefixRow.classList.toggle('d-none', wake.value !== 'prefix');
            }

            wake.addEventListener('change', showPrefix);
            showPrefix();

            var tier = h.select([
                ['1', t('cha_tier_1')],
                ['2', t('cha_tier_2')],
                ['3', t('cha_tier_3')]
            ], String(agent.tier));
            tier.disabled = !state.can_tier;
            item.appendChild(labelled(t('cha_tier'), tier, state.can_tier ? '' : t('cha_tier_help')));

            var guardWrap = el('div', 'form-field mb-3');
            var guard = el('input', '');
            var guardLabel = el('label', '', t('cha_loop_guard'));
            guard.type = 'checkbox';
            guard.id = nextId();
            guard.setAttribute('role', 'switch');
            guard.checked = !!agent.loop_guard;
            guardLabel.htmlFor = guard.id;
            guardWrap.appendChild(el('div', 'switch')).appendChild(guard);
            guardWrap.appendChild(guardLabel);
            item.appendChild(guardWrap);

            var error = el('div', 'small fg-emphasis-danger d-none mb-3');
            error.setAttribute('role', 'alert');
            item.appendChild(error);

            [wake, prefix, tier, guard].forEach(function (field) {
                if (!state.can_edit) {
                    field.disabled = true;
                }
            });

            if (!state.can_edit) {
                return item;
            }

            var actions = el('div', 'd-flex gap-3 justify-content-end');
            var remove = h.button('btn btn-sm btn-text theme-danger', t('cha_remove'), 'bi-x-lg');
            var save = h.button('btn btn-sm btn-outline theme-primary rounded-pill px-5', t('cha_save'), 'bi-check2');

            actions.appendChild(remove);
            actions.appendChild(save);
            item.appendChild(actions);

            remove.addEventListener('click', function () {
                h.ask(t('cha_remove_ask'), t('cha_remove'), true).then(function (yes) {
                    if (!yes) {
                        return;
                    }

                    remove.disabled = true;
                    h.api('ws_channel_agents_remove', { channel_id: channel.id, agent_id: agent.agent_id })
                        .then(draw)
                        .catch(function (problem) {
                            remove.disabled = false;
                            h.fail(problem);
                        });
                });
            });

            save.addEventListener('click', function () {
                var data = {
                    channel_id: channel.id,
                    agent_id: agent.agent_id,
                    wake_rule: wake.value,
                    wake_prefix: prefix.value,
                    loop_guard: guard.checked ? 1 : 0
                };

                // The tier goes only from the channel owner: the server
                // refuses any other change of it.
                if (state.can_tier) {
                    data.tier = parseInt(tier.value, 10) || 1;
                }

                save.disabled = true;
                error.classList.add('d-none');

                h.api('ws_channel_agents_set', data).then(function (next) {
                    h.toast(t('cha_saved'));
                    draw(next);
                }).catch(function (problem) {
                    save.disabled = false;
                    error.textContent = (problem && problem.message) ? problem.message : String(problem || '');
                    error.classList.remove('d-none');
                });
            });

            return item;
        }

        function addNode(state) {
            var wrap = el('div', 'd-flex gap-3 align-items-center mt-3');
            var options = [['', t('cha_choose')]].concat(state.addable.map(function (agent) {
                return [String(agent.agent_id), (agent.name || agent.handle) + ' (@' + agent.handle + ')'];
            }));
            var pick = h.select(options, '');
            var add = h.button('btn btn-sm btn-outline theme-primary rounded-pill px-5 text-nowrap', t('cha_add'), 'bi-plus-lg');

            pick.setAttribute('aria-label', t('cha_choose'));
            add.disabled = true;
            pick.addEventListener('change', function () {
                add.disabled = (pick.value === '');
            });

            add.addEventListener('click', function () {
                add.disabled = true;
                h.api('ws_channel_agents_set', { channel_id: channel.id, agent_id: parseInt(pick.value, 10) })
                    .then(draw)
                    .catch(function (problem) {
                        add.disabled = false;
                        h.fail(problem);
                    });
            });

            wrap.appendChild(pick);
            wrap.appendChild(add);

            return wrap;
        }

        function draw(state) {
            h.clear(root);

            if (!state || !state.ready || (!state.agents.length && !state.addable.length)) {
                root.classList.add('d-none');
                return;
            }

            root.classList.remove('d-none');
            root.appendChild(el('div', 'form-label', t('cha_title')));
            root.appendChild(el('div', 'form-text mt-0 mb-3', t('cha_help')));

            if (!state.agents.length) {
                root.appendChild(el('div', 'small fg-secondary', t('cha_empty')));
            } else {
                var list = el('div', 'list-group');

                state.agents.forEach(function (agent) {
                    list.appendChild(rowNode(agent, state));
                });
                root.appendChild(list);
            }

            if (!state.can_edit) {
                root.appendChild(el('div', 'form-text', t('cha_readonly')));
            } else if (state.addable.length) {
                root.appendChild(addNode(state));
            }
        }

        h.api('ws_channel_agents_list', { channel_id: channel.id }).then(draw).catch(h.fail);
    }

    window.PGWsChannelAgents = { mount: mount };
}());
