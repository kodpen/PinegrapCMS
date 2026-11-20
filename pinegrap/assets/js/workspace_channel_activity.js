/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the Activity tab of Channel info: in a channel with an agent
 * or a repository link, two tabs at the panel's top, Info (what the panel
 * holds) and Activity, which tells the channel's state in one place: the
 * summary of its agents and the project, a row each agent (what it works
 * on, its reported model, context, branch and when it reported), the
 * requests, what waits for a person, today's decisions, the repository's
 * numbers and the plan usage the agents reported. It only tells: links,
 * no setting and no action.
 *
 * It reads ws_channel_activity (includes/workspace/channel_activity.php)
 * when the tab opens, again when the channel's sync saw a message, a vote
 * or an answer change (at most once in 15 seconds) and every minute while
 * it is open, for the ages; nothing while it is closed. A part the answer
 * leaves null is left out.
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

    // The tones of an agent's state, as the agent card draws them.
    var STATE_TONES = { working: 'blue', waiting: 'amber', late: 'amber', paused: 'gray', idle: 'teal' };

    // The one tab drawn now: a redraw of Channel info replaces it.
    var current = null;
    var seq = 0;

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
    // whether that is 90% or more; null for none. Pure.
    function activityContext(context, t) {
        var used = context ? Number(context.used) : 0;
        var size = context ? Number(context.size) : 0;

        if (!context || !(size > 0) || !(used >= 0)) {
            return null;
        }

        var percent = Math.min(100, Math.round(used * 100 / size));

        return { words: t('act_context', activityTokens(used), activityTokens(size)), percent: percent, high: percent >= 90 };
    }

    // What an agent's row says in words: its state; while it works who
    // asked (only for a request of this channel), the message, since when
    // and the progress note; then its report's model, branch and when it
    // reported, a part it does not carry left out. Pure.
    function activityRowWords(agent, t) {
        var request = agent.request || null;
        var asked = (request && request.asked_by && request.asked_by.name) ? t('act_asked_by', request.asked_by.name) : null;

        return {
            state: agent.state_words || '',
            asked: asked,
            message_id: (request && request.message_id) ? request.message_id : null,
            request: request ? [request.since, request.note].filter(Boolean) : [],
            context: activityContext(agent.context, t),
            report: [agent.model, agent.branch, agent.reported].filter(Boolean),
            stale: !!agent.stale
        };
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

    // The summary line and the project.
    function drawSummary(h, pane, data) {
        var box = h.el('div', 'ws-act-summary');

        if (data.summary && data.summary.words) {
            box.appendChild(h.el('div', 'pg-title-status ws-act-summary-line', data.summary.words));
        }

        if (data.project && data.project.name) {
            var project = h.el('div', 'pg-text-meta ws-act-project');

            project.appendChild(h.icon(data.project.from === 'repository' ? 'bi-github' : 'bi-folder2'));
            project.appendChild(document.createTextNode(' ' + h.t('act_project', data.project.name)));
            box.appendChild(project);
        }

        if (box.firstChild) {
            pane.appendChild(box);
        }
    }

    // One agent's row: the face with its state dot, name and role, the
    // state's words; while it works who asked, since when and the note; then
    // its report. The name opens the agent card.
    function drawAgent(h, list, channel, agent) {
        var words = activityRowWords(agent, h.t);
        var row = h.el('div', 'ws-act-agent' + (words.stale ? ' is-stale' : ''));
        var face = h.el('span', 'ws-act-face');
        var main = h.el('div', 'ws-act-agent-main');
        var top = h.el('div', 'ws-act-agent-top');
        var name = h.el('span', 'ws-act-agent-name', agent.name || ('@' + agent.handle));

        face.appendChild(h.agentFace({ tone: agent.id, assistant: agent.kind !== 'app' }));
        // The agents' one state mark (.ws-agent-dot); ready draws none.
        if (['working', 'waiting', 'late', 'paused'].indexOf(agent.state) !== -1) {
            var dot = h.el('span', 'ws-agent-dot is-' + agent.state + ' ws-act-dot');

            dot.setAttribute('aria-hidden', 'true');
            face.appendChild(dot);
        }
        row.appendChild(face);

        top.appendChild(name);

        if (agent.role) {
            top.appendChild(h.el('span', 'pg-text-meta ws-act-agent-role', agent.role));
        }

        top.appendChild(h.el('span', 'pg-pill pg-tone-' + (STATE_TONES[agent.state] || 'gray') + ' ws-act-state', words.state));
        main.appendChild(top);

        if (h.agentCardHook) {
            h.agentCardHook(name, channel, { id: agent.id, handle: agent.handle, name: agent.name }, true);
        }

        if (words.asked || words.request.length) {
            var work = h.el('div', 'pg-text-meta ws-act-agent-line');

            if (words.asked) {
                work.appendChild(words.message_id ? link(h, channel.id, words.message_id, words.asked) : h.el('span', '', words.asked));
            }

            words.request.forEach(function (part) {
                if (work.firstChild) {
                    work.appendChild(document.createTextNode(' · '));
                }

                work.appendChild(h.el('span', '', part));
            });

            main.appendChild(work);
        }

        if (words.context) {
            var context = h.el('div', 'ws-act-context');

            context.appendChild(h.el('span', 'pg-text-meta pg-tabular', words.context.words));
            context.appendChild(bar(h, words.context.percent, words.context.words, words.context.high));
            main.appendChild(context);
        }

        if (words.report.length) {
            main.appendChild(h.el('div', 'pg-text-meta ws-act-agent-line ws-act-report', words.report.join(' · ')));
        }

        row.appendChild(main);
        list.appendChild(row);
    }

    function drawAgents(h, pane, channel, data) {
        if (!(data.agents || []).length) {
            return;
        }

        section(h, pane, h.t('act_agents'), data.agents.length);

        var list = h.el('div', 'ws-act-agents');

        data.agents.forEach(function (agent) {
            drawAgent(h, list, channel, agent);
        });

        pane.appendChild(list);
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

    // The repository's numbers of today and its last lines, only with a
    // link the reader may see (the server leaves it null otherwise).
    function drawRepository(h, pane, data) {
        var repo = data.repository;

        if (!repo) {
            return;
        }

        section(h, pane, h.t('act_repository'));

        if (repo.name) {
            pane.appendChild(h.el('div', 'pg-text-meta ws-act-project', repo.name));
        }

        var counts = repo.counts || {};
        var box = h.el('div', 'ws-act-facts');

        fact(h, box, h.t('act_pr_opened'), counts.opened || 0);
        fact(h, box, h.t('act_pr_merged'), counts.merged || 0);
        fact(h, box, h.t('act_pr_closed'), counts.closed || 0);
        fact(h, box, h.t('act_checks'), h.t('act_checks_value', counts.red || 0, counts.green || 0));
        pane.appendChild(box);

        var lines = repo.lines || [];

        if (lines.length) {
            var list = h.el('div', 'ws-act-lines');
            var icons = { pr: 'bi-git', ci: 'bi-check2-circle' };

            lines.forEach(function (item) {
                var row = h.el('div', 'ws-act-line');
                var text = (item.number ? '#' + item.number + ' ' : '') + (item.title || item.branch || '');

                row.appendChild(h.icon(icons[item.kind] || 'bi-github'));

                if (item.address) {
                    var go = h.el('a', 'ws-act-link ws-act-line-text', text);

                    go.href = item.address;
                    go.target = '_blank';
                    go.rel = 'noopener noreferrer';
                    row.appendChild(go);
                } else {
                    row.appendChild(h.el('span', 'ws-act-line-text', text));
                }

                if (item.state) {
                    row.appendChild(h.el('span', 'pg-text-meta ws-act-line-side', item.state));
                }

                list.appendChild(row);
            });

            pane.appendChild(list);
        }
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

    function draw(state, data) {
        var h = state.h;
        var pane = h.clear(state.pane);
        var scroll = state.pane.parentNode ? state.pane.parentNode.scrollTop : 0;

        drawSummary(h, pane, data);
        drawAgents(h, pane, state.channel, data);
        drawRequests(h, pane, state.channel, data);
        drawWaiting(h, pane, state.channel, data);
        drawDecisions(h, pane, state.channel, data);
        drawRepository(h, pane, data);
        drawUsage(h, pane, data);

        // A redraw for the ages keeps the reader's place.
        if (state.pane.parentNode) {
            state.pane.parentNode.scrollTop = scroll;
        }
    }

    // Reads the answer and draws it, while the tab is the one shown.
    function read(state) {
        var h = state.h;

        if (state.stopped || state.tab !== 'activity') {
            return;
        }

        if (!state.pane.isConnected) {
            stop(state);
            return;
        }

        state.readAt = Date.now();

        if (!state.pane.firstChild) {
            state.pane.appendChild(h.el('div', 'pg-text-meta ws-act-loading', h.t('loading')));
        }

        h.api('ws_channel_activity', { channel_id: state.channel.id }).then(function (data) {
            if (state.stopped || (state.tab !== 'activity') || !data || !data.activity) {
                return;
            }

            draw(state, data.activity);
        }).catch(function () {
            if (state.stopped || (state.tab !== 'activity')) {
                return;
            }

            h.clear(state.pane).appendChild(h.el('div', 'pg-text-meta ws-act-loading', h.t('act_failed')));
        });
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

        clearInterval(state.ticker);
        state.ticker = 0;

        if (tab === 'activity') {
            read(state);
            state.ticker = setInterval(function () { read(state); }, EVERY);
        }

        if (focus) {
            state.buttons[tab].focus();
        }
    }

    /**
     * Puts the Info and Activity tabs at the top of Channel info, where the
     * channel has an agent or a repository link; elsewhere it leaves the
     * panel as it is. The info's body stays the Info tab's pane.
     *
     * @param {HTMLElement} panel the info panel (aside.ws-info-panel)
     * @param {HTMLElement} body  its body, holding what Info shows
     * @param {object}      channel the channel as the screen holds it
     */
    function mount(panel, body, channel) {
        var h = window.PGWsKit;

        stop(current);
        current = null;

        if (!h || !panel || !body || !activityTabsShow(channel)) {
            return;
        }

        seq += 1;

        var el = h.el;
        var tabs = el('div', 'pg-seg ws-act-tabs');
        var pane = el('div', 'ws-act-pane');
        var holder = el('div', 'ws-panel-body ws-act-body');
        var state = { h: h, channel: channel, body: body, pane: pane, holder: holder, buttons: {}, tab: 'info', readAt: 0, braked: 0, ticker: 0, stopped: false };
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

        current = state;
        show(state, activityTabPick(storedTab(channel.id), true), false);
    }

    /**
     * The channel's sync saw a message, a vote or an answer change: the
     * open tab reads again, at most once in 15 seconds.
     *
     * @param {number} channelId
     */
    function changed(channelId) {
        var state = current;

        if (!state || state.stopped || (state.tab !== 'activity') || (state.channel.id !== channelId) || state.braked) {
            return;
        }

        var wait = activityBrake(state.readAt, Date.now());

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
        changed: changed
    };
})();
