/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the agents of a channel in its settings drawer: one compact
 * row an agent (picture, name, handle, role, state, and in a few words what
 * wakes it and what it may do by itself), which opens its settings, one
 * agent at a time: when it is woken, what it may do by itself (the tier)
 * and the loop guard; then the registered agents it can still take.
 * Whoever may post in the channel, in a private one only a member, adds,
 * changes and removes them; the tier is the channel manager's. The server
 * decides both (includes/workspace/channel_agents_panel.php).
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
     * @param {object}      [options] reveal: scroll to the section once drawn
     *                      (Manage in Channel info)
     */
    function mount(holder, channel, options) {
        var h = window.PGWsKit;

        if (!h || !holder || !channel) {
            return;
        }

        var t = h.t;
        var el = h.el;
        var root = el('div', 'ws-channel-agents mb-5 d-none');

        holder.appendChild(root);

        // The section's one open agent: opening another row closes it.
        var openId = 0;

        // The state's pill on a row. PF2's card state (working, waiting)
        // arrives in agent.state beside the stored ones drawn today:
        // paused, a poll agent not seen for ten minutes or a push door in
        // error (late), seen recently (on), a model agent (ready), a push
        // agent knocked as usual (off).
        var STATE_TONES = {
            working: 'pg-tone-blue',
            waiting: 'pg-tone-amber',
            late: 'pg-tone-amber',
            paused: 'pg-tone-gray',
            on: 'pg-tone-teal',
            ready: 'pg-tone-teal',
            off: 'pg-tone-gray'
        };

        function statePill(agent) {
            var state = STATE_TONES[agent.state] ? agent.state : (agent.active ? 'off' : 'paused');
            var words = {
                working: t('cha_state_working'),
                waiting: t('cha_state_waiting'),
                paused: t('cha_paused')
            };
            var pill = el('span', 'pg-pill ws-cha-state ' + STATE_TONES[state], words[state] || agent.seen_words || agent.seen || '');

            if (agent.seen) {
                pill.title = agent.seen;
            }

            return pill;
        }

        // The agent's own picture, else the robot in a quiet tile of its
        // colour, as Channel info draws it; never a face.
        function agentPicture(agent) {
            var face = h.agentFace({ tone: agent.agent_id, assistant: false });

            face.classList.add('ws-cha-face');

            if (agent.avatar) {
                var img = el('img', 'ws-cha-picture');

                img.src = agent.avatar;
                img.alt = '';
                img.loading = 'lazy';
                img.addEventListener('error', function () {
                    if (img.parentNode) {
                        img.parentNode.removeChild(img);
                    }
                });
                face.appendChild(img);
            }

            return face;
        }

        function headNode(agent, bodyId) {
            var head = el('button', 'ws-cha-head');
            var main = el('span', 'ws-cha-main');
            var top = el('span', 'ws-cha-name');

            head.type = 'button';
            head.setAttribute('aria-controls', bodyId);
            head.setAttribute('aria-expanded', 'false');

            top.appendChild(el('strong', 'pg-list-row-title', agent.name || ('@' + agent.handle)));
            top.appendChild(el('span', 'pg-text-meta', '@' + agent.handle));

            if (agent.role) {
                top.appendChild(el('span', 'pg-pill pg-tone-blue', agent.role));
            }

            main.appendChild(top);
            main.appendChild(el('span', 'pg-text-meta ws-cha-summary', [agent.wake_words, agent.tier_words].filter(Boolean).join(' · ')));

            head.appendChild(agentPicture(agent));
            head.appendChild(main);
            head.appendChild(statePill(agent));
            head.appendChild(h.icon('bi-chevron-down', 'ws-cha-chevron'));

            return head;
        }

        // A question with its choices as radio rows, each with its line
        // under it.
        function choices(name, question, items, value, disabled) {
            var set = el('fieldset', 'ws-cha-choices');

            set.appendChild(el('legend', 'pg-field-label', question));

            items.forEach(function (item) {
                var row = el('label', 'ws-cha-choice');
                var input = el('input', 'ws-cha-radio');
                var text = el('span', 'ws-cha-choice-text');

                input.type = 'radio';
                input.name = name;
                input.value = item[0];
                input.checked = (String(item[0]) === String(value));
                input.disabled = !!disabled;
                text.appendChild(el('span', 'pg-text-2 ws-cha-choice-title', item[1]));

                if (item[2]) {
                    text.appendChild(el('span', 'pg-text-meta', item[2]));
                }

                row.appendChild(input);
                row.appendChild(text);
                set.appendChild(row);
            });

            return set;
        }

        function chosen(set) {
            var on = set.querySelector('input:checked');

            return on ? on.value : '';
        }

        function bodyNode(agent, state, bodyId) {
            var body = el('div', 'ws-cha-body');
            var wakeName = nextId();
            var wakeHelp = {
                mention: t('cha_wake_mention_help', agent.handle),
                all: t('cha_wake_all_help'),
                prefix: t('cha_wake_prefix_help'),
                replies: t('cha_wake_replies_help')
            };

            body.id = bodyId;
            body.hidden = true;

            // When is it woken? A select, with a help line for the chosen
            // rule under it.
            var wakeRow = el('div', 'ws-cha-field');
            var wakeLabel = el('label', 'pg-field-label', t('cha_wake'));
            var wake = h.select([
                ['mention', t('cha_wake_mention')],
                ['all', t('cha_wake_all')],
                ['prefix', t('cha_wake_prefix')],
                ['replies', t('cha_wake_replies')]
            ], agent.wake_rule);
            var wakeLine = el('div', 'pg-field-help');

            wake.id = nextId();
            wake.name = wakeName;
            wakeLabel.htmlFor = wake.id;
            wakeRow.appendChild(wakeLabel);
            wakeRow.appendChild(wake);
            wakeRow.appendChild(wakeLine);
            body.appendChild(wakeRow);

            var prefixRow = el('div', 'ws-cha-field');
            var prefixLabel = el('label', 'pg-field-label', t('cha_prefix'));
            var prefix = el('input', 'form-control form-control-sm');

            prefix.type = 'text';
            prefix.maxLength = 32;
            prefix.id = nextId();
            prefix.value = agent.wake_prefix || '';
            prefix.placeholder = '[ci]';
            prefixLabel.htmlFor = prefix.id;
            prefixRow.appendChild(prefixLabel);
            prefixRow.appendChild(prefix);
            body.appendChild(prefixRow);

            function showWake() {
                wakeLine.textContent = wakeHelp[wake.value] || '';
                prefixRow.classList.toggle('d-none', wake.value !== 'prefix');
            }

            wake.addEventListener('change', showWake);
            showWake();

            // What may it do by itself? The three tiers as choices, each
            // with what it means; only the channel's manager changes it.
            var tier = choices(nextId(), t('cha_tier'), [
                ['1', t('cha_tier_1'), t('cha_tier_1_help')],
                ['2', t('cha_tier_2'), t('cha_tier_2_help')],
                ['3', t('cha_tier_3'), t('cha_tier_3_help')]
            ], String(agent.tier || 1), !state.can_tier || !state.can_edit);

            body.appendChild(tier);

            if (!state.can_tier) {
                body.appendChild(el('div', 'pg-field-help ws-cha-tier-note', t('cha_tier_help')));
            }

            // The loop guard: a switch at the row's end, its reason at the
            // left.
            var guardRow = el('div', 'ws-cha-switch-row');
            var guardText = el('div', 'ws-cha-switch-text');
            var guard = el('input', 'pg-switch');
            var guardTitle = el('label', 'pg-text-2 ws-cha-choice-title', t('cha_loop_guard'));

            guard.type = 'checkbox';
            guard.id = nextId();
            guard.setAttribute('role', 'switch');
            guard.checked = !!agent.loop_guard;
            guardTitle.htmlFor = guard.id;
            guardText.appendChild(guardTitle);
            guardText.appendChild(el('div', 'pg-text-meta', t('cha_loop_guard_help')));
            guardRow.appendChild(guardText);
            guardRow.appendChild(guard);
            body.appendChild(guardRow);

            var error = el('div', 'pg-field-help pg-field-error d-none');
            error.setAttribute('role', 'alert');
            body.appendChild(error);

            if (!state.can_edit) {
                [wake, prefix, guard].forEach(function (field) {
                    field.disabled = true;
                });

                return body;
            }

            var actions = el('div', 'ws-cha-actions');
            var remove = h.button('pg-button pg-button-ghost pg-button-sm ws-cha-remove', t('cha_remove'), 'bi-x-lg');
            var save = h.button('pg-button pg-button-quiet pg-button-sm', t('cha_save'), 'bi-check2');

            actions.appendChild(remove);
            actions.appendChild(save);
            body.appendChild(actions);

            remove.addEventListener('click', function () {
                h.ask(t('cha_remove_ask'), t('cha_remove'), true).then(function (yes) {
                    if (!yes) {
                        return;
                    }

                    remove.disabled = true;
                    h.api('ws_channel_agents_remove', { channel_id: channel.id, agent_id: agent.agent_id })
                        .then(function (next) {
                            openId = 0;
                            changed(next);
                        })
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

                // The tier goes only from the channel's manager: the server
                // refuses any other change of it.
                if (state.can_tier) {
                    data.tier = parseInt(chosen(tier), 10) || 1;
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

            return body;
        }

        // One agent: its compact row, and under it its settings, open for
        // one agent at a time.
        function rowNode(agent, state, list) {
            var item = el('div', 'ws-cha-item');
            var bodyId = nextId();
            var head = headNode(agent, bodyId);
            var body = bodyNode(agent, state, bodyId);

            item.appendChild(head);
            item.appendChild(body);

            function show(open) {
                item.classList.toggle('is-open', open);
                head.setAttribute('aria-expanded', open ? 'true' : 'false');
                body.hidden = !open;
            }

            head.addEventListener('click', function () {
                var open = head.getAttribute('aria-expanded') !== 'true';

                Array.prototype.forEach.call(list.querySelectorAll('.ws-cha-item.is-open'), function (other) {
                    if (other !== item && other.closeAgent) {
                        other.closeAgent();
                    }
                });
                show(open);
                openId = open ? agent.agent_id : 0;
            });

            item.closeAgent = function () {
                show(false);
            };

            show(openId === agent.agent_id);

            return item;
        }

        function addNode(state) {
            var wrap = el('div', 'ws-cha-add');
            var options = [['', t('cha_choose')]].concat(state.addable.map(function (agent) {
                return [String(agent.agent_id), (agent.name || agent.handle) + ' (@' + agent.handle + ')'];
            }));
            var pick = h.select(options, '');
            var add = h.button('pg-button pg-button-quiet pg-button-sm text-nowrap', t('cha_add'), 'bi-plus-lg');

            pick.setAttribute('aria-label', t('cha_choose'));
            add.disabled = true;
            pick.addEventListener('change', function () {
                add.disabled = (pick.value === '');
            });

            add.addEventListener('click', function () {
                add.disabled = true;
                h.api('ws_channel_agents_set', { channel_id: channel.id, agent_id: parseInt(pick.value, 10) })
                    .then(changed)
                    .catch(function (problem) {
                        add.disabled = false;
                        h.fail(problem);
                    });
            });

            wrap.appendChild(pick);
            wrap.appendChild(add);

            return wrap;
        }

        // The @ list of the open channel reads channel.apps, which the
        // screen took with the channel (ws_channel_app_picker()): after an
        // agent is added or removed it is read again, so the list offers
        // what the channel now holds without a reload.
        function refreshApps() {
            return h.api('ws_channel_get', { channel_id: channel.id }).then(function (data) {
                if (data && data.channel && (data.channel.id === channel.id)) {
                    channel.apps = data.channel.apps || [];
                }
            }).catch(function () {
                // The drawer stands as drawn; a reload reads the list.
            });
        }

        // Draws what an add or a removal answered, then reads the @ list
        // again.
        function changed(state) {
            draw(state);
            refreshApps();
        }

        function draw(state) {
            h.clear(root);

            if (!state || !state.ready || (!state.agents.length && !state.addable.length)) {
                root.classList.add('d-none');
                return;
            }

            root.classList.remove('d-none');
            // The drawer's other parts keep .form-label titles until the drawer is
            // redrawn; the section's title matches them.
            var title = root.appendChild(el('div', 'form-label', t('cha_title')));
            root.appendChild(el('div', 'pg-field-help ws-cha-intro', t('cha_help')));

            if (!state.agents.length) {
                root.appendChild(el('div', 'pg-text-meta', t('cha_empty')));
            } else {
                var list = el('div', 'pg-card-inner ws-cha-list');

                state.agents.forEach(function (agent) {
                    list.appendChild(rowNode(agent, state, list));
                });
                root.appendChild(list);
            }

            if (!state.can_edit) {
                root.appendChild(el('div', 'pg-field-help', t('cha_readonly')));
            } else if (state.addable.length) {
                root.appendChild(addNode(state));
            }

            // Opened from Channel info's Manage: the section is brought into
            // view and takes the focus, once.
            if (options && options.reveal) {
                options.reveal = false;
                title.tabIndex = -1;
                root.scrollIntoView({ block: 'start' });
                title.focus({ preventScroll: true });
            }
        }

        h.api('ws_channel_agents_list', { channel_id: channel.id }).then(draw).catch(h.fail);
    }

    window.PGWsChannelAgents = { mount: mount };
}());
