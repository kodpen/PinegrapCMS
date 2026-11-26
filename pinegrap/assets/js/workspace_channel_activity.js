/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - Channel info's agents block and its Activity tab. In a
 * channel with an agent or a repository link two tabs stand at the panel's
 * top, Info (what the panel holds) and Activity, which tells the channel's
 * state in one place: the project, the requests, what waits for a person,
 * today's decisions, the repository's numbers and the plan usage the agents
 * reported. It only tells: links, no setting and no action.
 *
 * Over both tabs stands one block "Agents": a calm two-line row each agent
 * (its state's word and the one thing worth knowing now), which opens in
 * place for its facts. It is one node: changing the tab moves it to the top
 * of the other pane, so open rows and "n more" keep their state; both are
 * kept across a redraw of the panel and dropped when it closes. Without the
 * tabs (assistants only) drawInfo() places it after People.
 *
 * It reads ws_channel_activity (includes/workspace/channel_activity.php)
 * while the panel is open in a channel with a registry agent, else when the
 * Activity tab opens; again when the channel's sync saw a message, a vote
 * or an answer change (at most once in 15 seconds) and every minute, for
 * the ages. A part the answer leaves null is left out.
 *
 * assets/js/workspace.js mounts it from drawInfo() through
 * window.PGWsChannelActivity.mount() and lends its helpers through
 * window.PGWsKit. Every text comes from the #ws-config block
 * (ws_channel_activity_js_strings()).
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

    // The least time between two reads asked by the channel's sync, and the
    // time between two reads while the tab is open.
    var BRAKE = 15000;
    var EVERY = 60000;

    // The rows the agents block shows before "n more", and the order of the
    // states (ws_channel_activity_order()); the assistants come last.
    var SHOWN = 6;
    var STATE_RANK = { working: 0, waiting: 1, late: 2, idle: 3, paused: 4 };

    // The ring's radius in its 18 px box, and its whole length.
    var RING_R = 7;
    var RING_C = 2 * Math.PI * RING_R;

    // The one panel drawn now: a redraw of Channel info replaces it.
    var current = null;
    var seq = 0;

    // What the block keeps while the panel stays open in a channel: the rows
    // opened (by the agent's key), "n more" pressed, the last answer and
    // when it was read, and each agent's state as last told.
    var kept = keptFresh(0);

    function keptFresh(channelId) {
        return { channelId: channelId, open: {}, more: false, data: null, readAt: 0, states: null };
    }

    // Whether Channel info gets its two tabs: a team reader's channel with
    // an agent or a repository link. Pure
    // (tests/workspace_channel_activity_js_test.php).
    function activityTabsShow(channel) {
        if (!channel || channel.guest) {
            return false;
        }

        return ((channel.agents || []).length > 0) || !!channel.repository_linked;
    }

    // The tab a channel's info opens on: the one last open there, kept in
    // the browser; Info for anything else. Pure.
    function activityTabPick(stored, shows) {
        return (shows && (stored === 'activity')) ? 'activity' : 'info';
    }

    // The browser key that keeps a channel's last tab. Pure.
    function activityTabKey(channelId) {
        return 'pg-ws-info-tab-' + channelId;
    }

    // A count of tokens in short words: 950, 12k, 412k, 1M, 1.5M. Pure.
    function activityTokens(count) {
        var value = Math.max(0, Math.round(Number(count) || 0));

        if (value >= 1000000) {
            return (Math.round(value / 100000) / 10) + 'M';
        }

        if (value >= 1000) {
            return Math.round(value / 1000) + 'k';
        }

        return String(value);
    }

    // The reported context, "412k of 1M tokens", its share of the window and
    // whether that is 90% or more; with no size (none typed on the agent's
    // form, none reported) the used tokens alone with a null percent and no
    // mark; used above the size counts as full; null for none. Pure.
    function activityContext(context, t) {
        var used = context ? Number(context.used) : 0;
        var size = context ? Number(context.size) : 0;

        if (!context || !(used >= 0)) {
            return null;
        }

        if (!(size > 0)) {
            return { words: t('act_context_used', activityTokens(used)), percent: null, high: false };
        }

        var percent = Math.min(100, Math.round(used * 100 / size));

        return { words: t('act_context', activityTokens(used), activityTokens(size)), percent: percent, high: percent >= 90 };
    }

    // The block's rows: the agents the channel holds (channelAgents() of
    // workspace.js: the assistants, then the registry agents), each joined
    // by id with the read's agent, the read's row winning; once the read
    // has landed, its agents replace the registry agents the page kept;
    // then in the
    // order working, waiting, not seen, ready, paused, the assistants last,
    // the order they came in within each. Pure.
    function agentsRows(base, read) {
        var byId = {};

        (read || []).forEach(function (agent) {
            if (agent && agent.id) {
                byId[agent.id] = agent;
            }
        });

        // Once the read has landed it decides the registry agents: one it
        // no longer returns loses its row, one the page did not know yet
        // (added while the page was open) gets one. The assistants, which
        // the read does not carry, stay.
        var list = (base || []).filter(function (agent) {
            return !read || !agent.id || !!byId[agent.id];
        });
        var known = {};

        list.forEach(function (agent) {
            if (agent.id) {
                known[agent.id] = true;
            }
        });

        (read || []).forEach(function (agent) {
            if (agent && agent.id && !known[agent.id]) {
                known[agent.id] = true;
                list.push({ key: 'agent-' + agent.id, id: agent.id, tone: agent.id, name: agent.name || ('@' + (agent.handle || '')) });
            }
        });

        var rows = list.map(function (agent, index) {
            var row = Object.assign({}, agent);
            var fresh = agent.id ? byId[agent.id] : null;

            if (fresh) {
                Object.assign(row, fresh);
                row.name = fresh.name || agent.name;
                row.key = agent.key;
                row.tone = agent.tone;
                row.read = true;
            }

            row.assistant = !!agent.assistant || (row.kind === 'model');
            row.state = row.assistant ? 'idle' : (row.state || (row.active === false ? 'paused' : 'idle'));
            row.at = index;

            return row;
        });

        rows.sort(function (a, b) {
            var ra = a.assistant ? 5 : (STATE_RANK[a.state] !== undefined ? STATE_RANK[a.state] : 3);
            var rb = b.assistant ? 5 : (STATE_RANK[b.state] !== undefined ? STATE_RANK[b.state] : 3);

            return (ra !== rb) ? ra - rb : a.at - b.at;
        });

        return rows;
    }

    // The rows shown: past six the first six and how many "n more" holds,
    // all of them once it was pressed. Pure.
    function agentsShown(rows, more) {
        var list = rows || [];

        if (more || (list.length <= SHOWN)) {
            return { rows: list, rest: 0 };
        }

        return { rows: list.slice(0, SHOWN), rest: list.length - SHOWN };
    }

    // A row's second line at rest: the state's word, then the one thing
    // worth knowing (rest) and an amber part (warn); tone colours the word.
    // Before the read lands a working or a ready row shows its word alone.
    // Pure.
    function agentsLine(agent, t) {
        if (agent.assistant) {
            return { word: t('cm_assistant'), rest: t('agents_always'), warn: '', tone: '' };
        }

        var request = agent.request || null;
        var out = { word: agent.state_words || '', rest: '', warn: '', tone: '' };
        var waiting = Number(agent.waiting) || 0;

        switch (agent.state) {
            case 'working':
                out.tone = 'blue';

                if (agent.read && request) {
                    out.rest = request.note || ((request.asked_by && request.asked_by.name) ? t('agents_for', request.asked_by.name, request.since || '') : (request.since || ''));
                }
                break;
            case 'waiting':
                out.tone = 'amber';
                out.rest = (waiting === 1) ? t('agents_queue_one') : ((waiting > 1) ? t('agents_queue', waiting) : '');
                break;
            case 'late':
                out.tone = 'amber';
                out.rest = agent.seen_rest || '';
                break;
            case 'paused':
                break;
            default:
                if (agent.read) {
                    if ((agent.kind === 'app') && !agent.reported_at) {
                        out.rest = t('agents_no_report');
                    } else {
                        out.rest = agent.model || '';
                        out.warn = agent.stale ? (agent.reported || '') : '';
                    }
                }
        }

        return out;
    }

    // The facts of an opened row, in their order, a fact with nothing to
    // say left out: Doing, Asked by, Queue, Handle, Wakes, Model, Context,
    // Branch, Seen, Reported; an assistant has Handle, Wakes and Seen. Each
    // is key, label, text, warn and muted; Asked by carries the asker's
    // name, the message and since; Context its percent and high. Pure.
    function agentsFacts(agent, t) {
        var facts = [];
        var request = agent.request || null;

        function add(key, text, extra) {
            if ((text === undefined) || (text === null) || (text === '')) {
                return;
            }

            facts.push(Object.assign({ key: key, label: t('agents_f_' + key), text: String(text), warn: false, muted: false }, extra || {}));
        }

        if (!agent.assistant) {
            add('doing', request ? request.note : '');

            if (request && request.asked_by && request.asked_by.name) {
                add('asked', request.asked_by.name, { message_id: request.message_id || null, since: request.since || '' });
            }

            var waiting = Number(agent.waiting) || 0;

            add('queue', (waiting === 1) ? t('agents_queue_one') : ((waiting > 1) ? t('agents_queue', waiting) : ''));
        }

        add('handle', agent.handle ? '@' + agent.handle : '');
        add('wakes', agent.wake_fact || t('agents_mentioned'));

        if (!agent.assistant) {
            var context = activityContext(agent.context, t);

            add('model', agent.model);

            if (context && (context.percent === null)) {
                add('context', context.words, { percent: null, high: false });
            } else if (context) {
                add('context', t('agents_context', activityTokens(agent.context.used), activityTokens(agent.context.size), context.percent), { percent: context.percent, high: context.high });
            }

            add('branch', agent.branch);
        }

        add('seen', agent.seen, { warn: agent.seen_state === 'late' });

        if (!agent.assistant) {
            if (agent.reported_at) {
                add('reported', agent.reported_ago, { warn: !!agent.stale });
            } else if (agent.read && (agent.kind === 'app')) {
                add('reported', t('agents_not_yet'), { muted: true });
            }
        }

        return facts;
    }

    // The context ring at a row's end: the arc's length over the whole
    // circle, amber from 90%; null for no context. Pure.
    function agentsRing(context) {
        var used = context ? Number(context.used) : 0;
        var size = context ? Number(context.size) : 0;

        if (!context || !(size > 0) || !(used >= 0)) {
            return null;
        }

        var percent = Math.min(100, Math.round(used * 100 / size));

        return { percent: percent, high: percent >= 90, dash: (percent * RING_C / 100).toFixed(2), whole: RING_C.toFixed(2) };
    }

    // How long a read asked by the sync waits after the last one: none
    // once 15 seconds have passed, else the rest of them. Pure.
    function activityBrake(lastAt, now) {
        if (!lastAt) {
            return 0;
        }

        var passed = now - lastAt;

        if (passed < 0) {
            return BRAKE;
        }

        return (passed >= BRAKE) ? 0 : BRAKE - passed;
    }

    function storedTab(channelId) {
        try {
            return window.localStorage.getItem(activityTabKey(channelId));
        } catch (error) {
            return null;
        }
    }

    function keepTab(channelId, tab) {
        try {
            window.localStorage.setItem(activityTabKey(channelId), tab);
        } catch (error) {
            // Without the browser's storage the tab is not kept.
        }
    }

    function stop(state) {
        if (!state) {
            return;
        }

        clearTimeout(state.braked);
        clearInterval(state.ticker);
        state.braked = 0;
        state.ticker = 0;
        state.stopped = true;
    }

    // ── Drawing ──

    function messageHref(h, channelId, messageId) {
        return h.cfg.urls.workspace + '?channel=' + channelId + '&message=' + messageId;
    }

    function link(h, channelId, messageId, text, extra) {
        var node = h.el('a', 'ws-act-link' + (extra ? ' ' + extra : ''), text);

        node.href = messageHref(h, channelId, messageId);

        return node;
    }

    function section(h, pane, label, count) {
        var head = h.el('h3', 'ws-panel-kicker', label);

        if ((count !== undefined) && (count !== null)) {
            head.appendChild(h.el('span', 'pg-count ws-act-count', String(count)));
        }

        pane.appendChild(head);
    }

    function bar(h, percent, label, high) {
        var node = h.el('div', 'ws-act-bar' + (high ? ' is-high' : ''));
        var fill = h.el('span', '');

        fill.style.width = Math.max(0, Math.min(100, percent)) + '%';
        node.appendChild(fill);
        node.setAttribute('role', 'meter');
        node.setAttribute('aria-valuemin', '0');
        node.setAttribute('aria-valuemax', '100');
        node.setAttribute('aria-valuenow', String(percent));
        node.setAttribute('aria-label', label);

        return node;
    }

    // The project, the first line under the block in the Activity pane.
    function drawProject(h, pane, data) {
        if (!data.project || !data.project.name) {
            return;
        }

        var project = h.el('div', 'pg-text-meta ws-act-project');

        project.appendChild(h.icon(data.project.from === 'repository' ? 'bi-github' : 'bi-folder2'));
        project.appendChild(document.createTextNode(' ' + h.t('act_project', data.project.name)));
        pane.appendChild(project);
    }

    // ── The agents block ──

    function svgNode(tag, attrs) {
        var node = document.createElementNS('http://www.w3.org/2000/svg', tag);

        Object.keys(attrs).forEach(function (name) {
            node.setAttribute(name, attrs[name]);
        });

        return node;
    }

    // The context ring, named by its tip's words.
    function ringNode(h, agent) {
        var ring = agentsRing(agent.context);

        if (!ring) {
            return null;
        }

        var context = activityContext(agent.context, h.t);
        var words = h.t('agents_ring', context ? context.words : '', ring.percent);
        var svg = svgNode('svg', { 'class': 'ws-agents-ring' + (ring.high ? ' is-high' : ''), viewBox: '0 0 18 18', width: '18', height: '18', role: 'img', 'aria-label': words });
        var title = svgNode('title', {});

        title.textContent = words;
        svg.appendChild(title);
        svg.appendChild(svgNode('circle', { cx: '9', cy: '9', r: String(RING_R), 'class': 'ws-agents-ring-track' }));
        svg.appendChild(svgNode('circle', { cx: '9', cy: '9', r: String(RING_R), 'class': 'ws-agents-ring-arc', 'stroke-dasharray': ring.dash + ' ' + ring.whole, transform: 'rotate(-90 9 9)' }));

        return svg;
    }

    // The facts of an opened row: labels at the left, values at the right.
    function factsNode(h, channel, agent, id) {
        var list = h.el('dl', 'ws-agents-facts');

        list.id = id;

        agentsFacts(agent, h.t).forEach(function (fact) {
            var value = h.el('dd', 'ws-agents-fact-' + fact.key + (fact.warn ? ' ws-agents-warn' : '') + (fact.muted ? ' pg-text-meta' : ''));

            list.appendChild(h.el('dt', '', fact.label));

            if (fact.key === 'asked') {
                value.appendChild(fact.message_id ? link(h, channel.id, fact.message_id, fact.text) : h.el('span', '', fact.text));

                if (fact.since) {
                    value.appendChild(document.createTextNode(' · ' + fact.since));
                }
            } else if (fact.key === 'context') {
                // No size, no bar: the used tokens alone.
                if (fact.percent !== null) {
                    value.appendChild(bar(h, fact.percent, fact.text, fact.high));
                }

                value.appendChild(h.el('span', 'pg-tabular', fact.text));
            } else if (fact.key === 'branch') {
                var cut = h.el('span', 'ws-agents-cut', fact.text);

                cut.title = fact.text;
                value.appendChild(cut);
            } else {
                value.textContent = fact.text;
            }

            list.appendChild(value);
        });

        return list;
    }

    // One row: a button with the face, two lines, the ring and the
    // chevron, which opens the facts under it.
    function rowNode(state, agent) {
        var h = state.h;
        var open = !!kept.open[agent.key];
        var line = agentsLine(agent, h.t);
        var row = h.el('div', 'ws-agents-row is-' + agent.state + (agent.assistant ? ' is-assistant' : '') + (open ? ' is-open' : ''));
        var head = h.el('button', 'ws-agents-rowhead');
        var face = h.el('span', 'ws-agents-face');
        var text = h.el('span', 'ws-agents-text');
        var first = h.el('span', 'ws-agents-l1');
        var second = h.el('span', 'ws-agents-l2');
        var factsId = 'ws-agents-facts-' + state.seq + '-' + agent.key;

        head.type = 'button';
        head.setAttribute('aria-expanded', open ? 'true' : 'false');
        head.setAttribute('aria-controls', factsId);
        head.setAttribute('data-info-key', 'agents-row-' + agent.key);

        face.appendChild(h.agentFace({ tone: agent.tone, assistant: agent.assistant }));

        // The agents' one state mark (.ws-agent-dot); ready draws none.
        if (!agent.assistant && ['working', 'waiting', 'late', 'paused'].indexOf(agent.state) !== -1) {
            var dot = h.el('span', 'ws-agent-dot is-' + agent.state);

            dot.setAttribute('aria-hidden', 'true');
            face.appendChild(dot);
        }

        head.appendChild(face);

        first.appendChild(h.el('span', 'ws-agents-name', agent.name));

        if (agent.role) {
            first.appendChild(h.el('span', 'ws-agents-role', agent.role));
        }

        second.appendChild(h.el('em', 'ws-agents-word' + (line.tone ? ' is-' + line.tone : ''), line.word));

        if (line.rest || line.warn) {
            var rest = h.el('span', 'ws-agents-rest', ' · ' + (line.rest || ''));

            if (line.warn) {
                if (line.rest) {
                    rest.appendChild(document.createTextNode(' · '));
                }

                rest.appendChild(h.el('span', 'ws-agents-warn', line.warn));
            }

            second.appendChild(rest);
        }

        text.appendChild(first);
        text.appendChild(second);
        head.appendChild(text);

        var end = h.el('span', 'ws-agents-end');
        var ring = agent.assistant ? null : ringNode(h, agent);

        if (ring) {
            end.appendChild(ring);
        }

        end.appendChild(h.icon(open ? 'bi-chevron-up' : 'bi-chevron-down', 'ws-agents-chev'));
        head.appendChild(end);
        row.appendChild(head);

        var facts = factsNode(h, state.channel, agent, factsId);

        facts.hidden = !open;
        row.appendChild(facts);

        head.addEventListener('click', function () {
            agentsToggle(row, head, facts, agent.key, !kept.open[agent.key]);
        });

        // Esc closes the open row that holds the focus; the next one the panel.
        row.addEventListener('keydown', function (event) {
            if ((event.key === 'Escape') && kept.open[agent.key]) {
                event.stopPropagation();
                agentsToggle(row, head, facts, agent.key, false);
                head.focus();
            }
        });

        return row;
    }

    function agentsToggle(row, head, facts, key, open) {
        var chevron = head.querySelector('.ws-agents-chev');

        if (open) {
            kept.open[key] = true;
        } else {
            delete kept.open[key];
        }

        row.classList.toggle('is-open', open);
        head.setAttribute('aria-expanded', open ? 'true' : 'false');
        facts.hidden = !open;

        if (chevron) {
            chevron.classList.toggle('bi-chevron-down', !open);
            chevron.classList.toggle('bi-chevron-up', open);
        }
    }

    // The block's rows, the count and the summary drawn anew from the
    // channel and the last answer; the focus stays on the row that held it.
    function drawRows(state) {
        var h = state.h;
        var block = state.block;

        if (!block) {
            return;
        }

        var rows = agentsRows(h.channelAgents ? h.channelAgents(state.channel) : [], kept.data ? kept.data.agents : null);
        var shown = agentsShown(rows, kept.more);
        var active = block.contains(document.activeElement) ? document.activeElement.getAttribute('data-info-key') : null;
        var list = h.clear(block.querySelector('.ws-agents-rows'));

        block.querySelector('.ws-agents-count').textContent = String(rows.length);
        block.querySelector('.ws-agents-sum').textContent = (kept.data && kept.data.summary && kept.data.summary.words) || '';

        shown.rows.forEach(function (agent) {
            list.appendChild(rowNode(state, agent));
        });

        if (shown.rest) {
            var more = h.el('button', 'ws-agents-more', h.t('act_more', shown.rest));

            more.type = 'button';
            more.setAttribute('data-info-key', 'agents-more');
            more.appendChild(h.icon('bi-chevron-down', ''));
            more.addEventListener('click', function () {
                kept.more = true;
                drawRows(state);

                var next = list.querySelectorAll('.ws-agents-rowhead')[SHOWN];

                if (next) {
                    next.focus();
                }
            });
            list.appendChild(more);
        }

        if (active) {
            var target = block.querySelector('[data-info-key="' + active + '"]');

            if (target) {
                target.focus({ preventScroll: true });
            }
        }

        agentsTell(state, rows);
    }

    // A state that changed while the panel is open is said once, politely:
    // "Mimar: Working".
    function agentsTell(state, rows) {
        if (!kept.data) {
            return;
        }

        var before = kept.states;
        var said = [];

        kept.states = {};

        rows.forEach(function (agent) {
            if (agent.assistant || !agent.read) {
                return;
            }

            kept.states[agent.key] = agent.state;

            if (before && before[agent.key] && (before[agent.key] !== agent.state)) {
                said.push(agent.name + ': ' + (agent.state_words || ''));
            }
        });

        if (said.length && state.live) {
            state.live.textContent = said.join('. ');
        }
    }

    // The block: its head (the count, the states that ask for a look, the
    // manager's way to the agents' settings) and its rows; null for a
    // channel with no agent at all.
    function blockNode(state) {
        var h = state.h;
        var channel = state.channel;

        if (!h.channelAgents || !h.channelAgents(channel).length) {
            return null;
        }

        var block = h.el('section', 'ws-agents');
        var head = h.el('div', 'ws-agents-head');
        var title = h.el('h3', 'ws-panel-kicker', h.t('agents_title'));

        title.id = 'ws-agents-title-' + state.seq;
        block.setAttribute('aria-labelledby', title.id);
        head.appendChild(title);
        head.appendChild(h.el('span', 'ws-agents-count'));
        head.appendChild(h.el('span', 'ws-agents-sum'));

        if (channel.can_manage && h.channelSettings) {
            var manage = h.el('button', 'pg-icon-button pg-icon-button-sm ws-agents-manage');

            manage.type = 'button';
            manage.title = h.t('agents_manage');
            manage.setAttribute('aria-label', h.t('agents_manage'));
            manage.setAttribute('data-info-key', 'agents-manage');
            manage.appendChild(h.icon('bi-sliders', ''));
            manage.addEventListener('click', function () {
                h.channelSettings(channel, 'people');
            });
            head.appendChild(manage);
        }

        block.appendChild(head);
        block.appendChild(h.el('div', 'ws-agents-rows'));

        state.live = h.el('div', 'visually-hidden');
        state.live.setAttribute('aria-live', 'polite');
        block.appendChild(state.live);

        return block;
    }

    // A label and its value on one line.
    function fact(h, box, label, value) {
        var row = h.el('div', 'ws-act-fact');

        row.appendChild(h.el('span', 'ws-act-fact-key', label));

        if (value && value.nodeType) {
            row.appendChild(value);
        } else {
            row.appendChild(h.el('span', 'pg-tabular ws-act-fact-value', String(value)));
        }

        box.appendChild(row);
    }

    function drawRequests(h, pane, channel, data) {
        var requests = data.requests;

        if (!requests) {
            return;
        }

        section(h, pane, h.t('act_requests'));

        var box = h.el('div', 'ws-act-facts');
        var waiting = h.el('span', 'pg-tabular ws-act-fact-value', String(requests.waiting || 0));

        if (requests.waiting && requests.oldest_age) {
            var oldest = h.t('act_oldest', requests.oldest_age);

            waiting.appendChild(document.createTextNode(' · '));
            waiting.appendChild(requests.oldest_message_id ? link(h, channel.id, requests.oldest_message_id, oldest) : h.el('span', '', oldest));
        }

        fact(h, box, h.t('act_req_waiting'), waiting);
        fact(h, box, h.t('act_req_working'), requests.working || 0);
        fact(h, box, h.t('act_req_answered'), requests.answered || 0);
        fact(h, box, h.t('act_req_failed'), requests.failed || 0);
        pane.appendChild(box);
    }

    // A list of lines, each a link to its message with the age at its right.
    function line(h, list, channel, messageId, text, side, icon) {
        var row = h.el('div', 'ws-act-line');

        if (icon) {
            row.appendChild(h.icon(icon));
        }

        row.appendChild(messageId ? link(h, channel.id, messageId, text, 'ws-act-line-text') : h.el('span', 'ws-act-line-text', text));

        if (side) {
            row.appendChild(h.el('span', 'pg-text-meta pg-tabular ws-act-line-side', side));
        }

        list.appendChild(row);
    }

    function drawWaiting(h, pane, channel, data) {
        var waiting = data.waiting;

        if (!waiting) {
            return;
        }

        var items = waiting.items || [];

        section(h, pane, h.t('act_waiting'), items.length + (waiting.more || 0));

        var list = h.el('div', 'ws-act-lines');
        var icons = { poll: 'bi-bar-chart', question: 'bi-question-circle', approval: 'bi-check2-square' };

        if (!items.length) {
            list.appendChild(h.el('div', 'pg-text-meta', h.t('act_waiting_none')));
        }

        items.forEach(function (item) {
            line(h, list, channel, item.message_id, item.text || h.t('act_kind_' + item.kind), item.age, icons[item.kind] || 'bi-dot');
        });

        if (waiting.more) {
            list.appendChild(h.el('div', 'pg-text-meta ws-act-more', h.t('act_more', waiting.more)));
        }

        pane.appendChild(list);
    }

    function drawDecisions(h, pane, channel, data) {
        var decisions = data.decisions;

        if (!decisions) {
            return;
        }

        section(h, pane, h.t('act_decisions'), decisions.count || 0);

        var list = h.el('div', 'ws-act-lines');

        if (!(decisions.items || []).length) {
            list.appendChild(h.el('div', 'pg-text-meta', h.t('act_decisions_none')));
        }

        (decisions.items || []).forEach(function (item) {
            line(h, list, channel, item.message_id, item.text, '', 'bi-clock-history');
        });

        // The channel's Decisions tab, beside Messages.
        var all = h.el('button', 'pg-button pg-button-ghost pg-button-sm ws-act-all', h.t('act_all_decisions'));

        all.type = 'button';
        all.addEventListener('click', function () {
            var tab = document.querySelector('.ws-tabs [data-ws-tab="decisions"]');

            if (tab) {
                tab.click();
            }
        });
        list.appendChild(all);
        pane.appendChild(list);
    }

    // The repository's numbers of today and its last lines
    // (channel_activity_repo.php), only with a link the reader may see: the
    // server leaves it null otherwise. A line's state is said in words for
    // a pull request and a check; a label line shows its label.
    function drawRepository(h, pane, data) {
        var repo = data.repository;

        if (!repo) {
            return;
        }

        section(h, pane, h.t('act_repository'));

        if (repo.name) {
            var name = h.el('div', 'pg-text-meta ws-act-project');

            name.appendChild(h.icon('bi-github'));
            name.appendChild(document.createTextNode(' '));
            name.appendChild(repo.url ? outLink(h, repo.url, repo.name, '') : h.el('span', '', repo.name));
            pane.appendChild(name);
        }

        var pulls = repo.pull_requests || {};
        var checks = repo.checks || {};
        var box = h.el('div', 'ws-act-facts');

        fact(h, box, h.t('act_pr_opened'), pulls.opened || 0);
        fact(h, box, h.t('act_pr_merged'), pulls.merged || 0);
        fact(h, box, h.t('act_pr_closed'), pulls.closed || 0);
        fact(h, box, h.t('act_checks'), h.t('act_checks_value', checks.red || 0, checks.green || 0));
        pane.appendChild(box);

        var lines = repo.lines || [];

        if (!lines.length) {
            return;
        }

        var list = h.el('div', 'ws-act-lines');
        var icons = { pr: 'bi-git', ci: 'bi-check2-circle', label: 'bi-tag' };
        var states = { opened: 1, reopened: 1, merged: 1, closed: 1, green: 1, red: 1 };

        lines.forEach(function (item) {
            var row = h.el('div', 'ws-act-line');
            var text = (item.number ? '#' + item.number + ' ' : '') + (item.title || item.branch || '');
            var state = (item.kind === 'label') ? item.state : (states[item.state] ? h.t('act_state_' + item.state) : '');

            row.appendChild(h.icon(icons[item.kind] || 'bi-github'));
            row.appendChild(item.url ? outLink(h, item.url, text, 'ws-act-line-text') : h.el('span', 'ws-act-line-text', text));

            if (state) {
                row.appendChild(h.el('span', 'pg-text-meta ws-act-line-side', state));
            }

            list.appendChild(row);
        });

        pane.appendChild(list);
    }

    // A link out of the site (GitHub), in a new tab.
    function outLink(h, href, text, extra) {
        var node = h.el('a', 'ws-act-link' + (extra ? ' ' + extra : ''), text);

        node.href = /^https:\/\//.test(href) ? href : '#';
        node.target = '_blank';
        node.rel = 'noopener noreferrer';

        return node;
    }

    // Each reported plan window once, with the agents that reported it.
    function drawUsage(h, pane, data) {
        var usage = data.usage || [];

        if (!usage.length) {
            return;
        }

        section(h, pane, h.t('act_usage'));

        var list = h.el('div', 'ws-act-usage');

        usage.forEach(function (window) {
            var item = h.el('div', 'ws-act-window' + (window.high ? ' is-high' : ''));

            item.appendChild(h.el('div', 'pg-tabular ws-act-window-words', window.words));
            item.appendChild(bar(h, window.percent, window.label || window.words, window.high));
            item.appendChild(h.el('div', 'pg-text-meta ws-act-window-who', (window.agents || []).map(function (agent) {
                return agent.name || ('@' + agent.handle);
            }).join(', ')));
            list.appendChild(item);
        });

        pane.appendChild(list);

        if (data.usage_tip) {
            pane.appendChild(h.el('p', 'pg-text-meta ws-act-tip', data.usage_tip));
        }
    }

    // The Activity pane under the block.
    function draw(state, data) {
        var h = state.h;
        var pane = h.clear(state.pane);
        var scroll = state.holder.scrollTop;

        drawProject(h, pane, data);
        drawRequests(h, pane, state.channel, data);
        drawWaiting(h, pane, state.channel, data);
        drawDecisions(h, pane, state.channel, data);
        drawRepository(h, pane, data);
        drawUsage(h, pane, data);
        state.drawn = true;

        // A redraw for the ages keeps the reader's place.
        state.holder.scrollTop = scroll;
    }

    // Whether the panel reads now: on either tab in a channel with a
    // registry agent (for the block), else only while Activity is shown.
    function wantsRead(state) {
        return !!state.tabs && ((state.tab === 'activity') || ((state.channel.agents || []).length > 0));
    }

    // Reads the answer and draws it: the block's rows and the Activity pane.
    // When the read fails the block stays drawn from what the channel holds.
    function read(state) {
        var h = state.h;

        if (state.stopped || !wantsRead(state)) {
            return;
        }

        if (!state.holder.isConnected) {
            stop(state);
            return;
        }

        kept.readAt = Date.now();

        if (!state.drawn && !state.pane.firstChild) {
            state.pane.appendChild(h.el('div', 'pg-text-meta ws-act-loading', h.t('loading')));
        }

        h.api('ws_channel_activity', { channel_id: state.channel.id }).then(function (data) {
            if (state.stopped || !data || !data.activity || (kept.channelId !== state.channel.id)) {
                return;
            }

            kept.data = data.activity;
            drawRows(state);
            draw(state, data.activity);
        }).catch(function () {
            if (state.stopped || state.drawn) {
                return;
            }

            h.clear(state.pane).appendChild(h.el('div', 'pg-text-meta ws-act-loading', h.t('act_failed')));
        });
    }

    function tick(state) {
        clearInterval(state.ticker);
        state.ticker = 0;

        if (wantsRead(state)) {
            state.ticker = setInterval(function () { read(state); }, EVERY);
        }
    }

    function show(state, tab, focus) {
        state.tab = tab;
        keepTab(state.channel.id, tab);

        ['info', 'activity'].forEach(function (key) {
            var on = (key === tab);

            state.buttons[key].classList.toggle('is-active', on);
            state.buttons[key].setAttribute('aria-selected', on ? 'true' : 'false');
            state.buttons[key].tabIndex = on ? 0 : -1;
        });

        state.body.hidden = (tab !== 'info');
        state.holder.hidden = (tab !== 'activity');

        // The block moves to the top of the pane shown; it is not drawn
        // again, so open rows stay open.
        if (state.block) {
            if (tab === 'info') {
                state.body.insertBefore(state.block, state.body.firstChild);
            } else {
                state.holder.insertBefore(state.block, state.pane);
            }
        }

        // Activity drawn from the last answer; read only when it has none.
        if ((tab === 'activity') && !state.drawn) {
            if (kept.data) {
                draw(state, kept.data);
            } else {
                read(state);
            }
        }

        tick(state);

        if (focus) {
            state.buttons[tab].focus();
        }
    }

    /**
     * Puts the agents block and, where the channel has an agent or a
     * repository link, the Info and Activity tabs at the top of Channel
     * info; the info's body stays the Info tab's pane. Without the tabs the
     * block waits for place().
     *
     * @param {HTMLElement} panel the info panel (aside.ws-info-panel)
     * @param {HTMLElement} body  its body, holding what Info shows
     * @param {object}      channel the channel as the screen holds it
     */
    function mount(panel, body, channel) {
        var h = window.PGWsKit;

        stop(current);
        current = null;

        if (!h || !panel || !body || !channel) {
            return;
        }

        if (kept.channelId !== channel.id) {
            kept = keptFresh(channel.id);
        }

        seq += 1;

        var el = h.el;
        var pane = el('div', 'ws-act-pane');
        var holder = el('div', 'ws-panel-body ws-act-body');
        var state = { h: h, seq: seq, channel: channel, body: body, pane: pane, holder: holder, buttons: {}, tab: 'info', tabs: activityTabsShow(channel), block: null, live: null, drawn: false, braked: 0, ticker: 0, stopped: false };

        state.block = blockNode(state);
        current = state;

        if (!state.tabs) {
            drawRows(state);
            return;
        }

        var tabs = el('div', 'pg-seg ws-act-tabs');
        var keys = ['info', 'activity'];

        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', h.t('channel_info'));

        keys.forEach(function (key, index) {
            var tab = el('button', 'pg-seg-item', h.t('act_tab_' + key));

            tab.type = 'button';
            tab.id = 'ws-act-tab-' + seq + '-' + key;
            tab.setAttribute('role', 'tab');
            tab.addEventListener('click', function () {
                if (state.tab !== key) {
                    show(state, key, false);
                }
            });
            tab.addEventListener('keydown', function (event) {
                var moves = { ArrowLeft: -1, ArrowRight: 1, Home: -index, End: keys.length - 1 - index };

                if (moves[event.key] !== undefined) {
                    event.preventDefault();
                    show(state, keys[(index + moves[event.key] + keys.length) % keys.length], true);
                }
            });
            state.buttons[key] = tab;
            tabs.appendChild(tab);
        });

        body.id = body.id || ('ws-act-info-' + seq);
        body.setAttribute('role', 'tabpanel');
        body.setAttribute('aria-labelledby', state.buttons.info.id);
        holder.id = 'ws-act-pane-' + seq;
        holder.setAttribute('role', 'tabpanel');
        holder.setAttribute('aria-labelledby', state.buttons.activity.id);
        state.buttons.activity.setAttribute('aria-controls', holder.id);
        holder.tabIndex = 0;
        holder.appendChild(pane);
        state.buttons.info.setAttribute('aria-controls', body.id);

        var head = el('div', 'ws-act-tabs-row');

        head.appendChild(tabs);
        panel.insertBefore(head, body);
        panel.insertBefore(holder, body.nextSibling);

        drawRows(state);
        show(state, activityTabPick(storedTab(channel.id), true), false);

        // A redraw of the panel reads at once only when the last answer is
        // older than the brake; else the minute's ticker brings the next.
        if (wantsRead(state) && (!kept.readAt || !activityBrake(kept.readAt, Date.now()))) {
            read(state);
        }
    }

    /**
     * Places the block where Channel info's agents stood, after People, in
     * a channel with no tabs (assistants only); with the tabs mount() has
     * placed it already.
     *
     * @param {HTMLElement} body the panel's body
     */
    function place(body) {
        if (current && !current.tabs && current.block && body) {
            body.appendChild(current.block);
        }
    }

    /**
     * The panel closed: open rows, "n more" and the last answer go with it.
     */
    function forget() {
        stop(current);
        current = null;
        kept = keptFresh(0);
    }

    /**
     * The channel's sync saw a message, a vote or an answer change: the
     * open tab reads again, at most once in 15 seconds.
     *
     * @param {number} channelId
     */
    function changed(channelId) {
        var state = current;

        if (!state || state.stopped || !wantsRead(state) || (state.channel.id !== channelId) || state.braked) {
            return;
        }

        var wait = activityBrake(kept.readAt, Date.now());

        if (!wait) {
            read(state);
            return;
        }

        state.braked = setTimeout(function () {
            state.braked = 0;
            read(state);
        }, wait);
    }

    window.PGWsChannelActivity = {
        mount: mount,
        place: place,
        forget: forget,
        changed: changed
    };
})();
