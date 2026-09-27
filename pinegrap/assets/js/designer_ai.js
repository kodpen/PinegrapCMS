/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the assistant panel.
 *
 * A designer asks Claude or Pinegrap AI to change the element selected on the
 * canvas, or the whole page when nothing is selected. The request goes to the
 * site (api.php, designer/ai_ask) with the tab's tree as it is right now; the
 * answer is a proposal - operations against that tree - that this panel lists
 * under the request. Applying it asks the site to run the operations on the
 * tab's current tree and puts the result in place as one undo step, so Ctrl+Z
 * takes it back and nothing reaches the site before the page is published.
 *
 * Claude works in Anthropic's cloud and answers in minutes; Pinegrap AI is
 * carried on by this panel's own status calls, one model call at a time, so a
 * request to it only moves while an editor or a workspace screen is open (the
 * hourly job picks up the rest).
 *
 * The words come from the server (sdDesign.ai.text, through lang()), the
 * editor's state through StyleDesigner.aiBridge().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var cfg = (window.sdDesign && window.sdDesign.ai) ? window.sdDesign.ai : null;

    // StyleDesigner is a top-level const: a global binding, not a property
    // of window.
    var editor = (typeof StyleDesigner !== 'undefined') ? StyleDesigner : null;

    if (!cfg || !cfg.enabled || !editor || typeof editor.aiBridge !== 'function') {
        return;
    }

    var B = null;
    var TEXT = cfg.text || {};
    var AGENT_KEY = 'pg_sd_ai_agent';

    var state = {
        open: false,
        pageId: 0,
        loaded: false,
        loading: false,
        agents: [],
        agent: '',
        requests: [],
        wholePage: false,
        pollTimer: null,
        polling: false,
        sending: false,
        problem: '',
        syncTimer: null,
        lastTarget: ''
    };

    var el = {};

    function t(key, vars) {
        var s = (TEXT[key] !== undefined) ? String(TEXT[key]) : key;

        if (vars) {
            Object.keys(vars).forEach(function (name) {
                s = s.split('{' + name + '}').join(String(vars[name]));
            });
        }

        return s;
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function bridge() {
        if (!B) {
            B = editor.aiBridge();
        }

        return B;
    }

    function storeGet(key) {
        try { return window.localStorage.getItem(key) || ''; } catch (e) { return ''; }
    }

    function storeSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (e) { /* private window */ }
    }

    function toast(html, kind, ms) {
        var b = bridge();

        if (b && typeof b.toast === 'function') {
            b.toast(html, kind || 'info', ms || 5000);
        }
    }

    /* ------------------------------------------------------------------
       The panel
       ------------------------------------------------------------------ */

    function build() {
        if (el.panel) {
            return;
        }

        var panel = document.createElement('section');
        panel.className = 'sd-ai-panel d-none';
        panel.id = 'sd-ai-panel';
        panel.setAttribute('aria-labelledby', 'sd-ai-title');
        panel.innerHTML =
            '<header class="sd-ai-head">' +
                '<span class="bi bi-stars" aria-hidden="true"></span>' +
                '<h2 class="sd-ai-title" id="sd-ai-title">' + esc(t('title')) + '</h2>' +
                '<button type="button" class="sd-ai-close" title="' + esc(t('close')) + '" aria-label="' + esc(t('close')) + '"><span class="bi bi-x-lg" aria-hidden="true"></span></button>' +
            '</header>' +
            '<div class="sd-ai-list" aria-live="polite"></div>' +
            // Not a <form>: the panel's shortcut handler (Ctrl+S in
            // backend.js) submits the form around the focused field natively,
            // which would navigate the editor away with the tab unsaved.
            '<div class="sd-ai-compose" role="group" aria-label="' + esc(t('title')) + '">' +
                '<div class="sd-ai-target"></div>' +
                '<div class="sd-ai-agents" role="radiogroup" aria-label="' + esc(t('title')) + '"></div>' +
                '<label class="visually-hidden" for="sd-ai-input">' + esc(t('placeholder')) + '</label>' +
                '<textarea id="sd-ai-input" class="sd-ai-input" rows="3" maxlength="4000" placeholder="' + esc(t('placeholder')) + '"></textarea>' +
                '<div class="sd-ai-actions">' +
                    '<span class="sd-ai-hint">' + esc(t('agent_hint')) + ' · Ctrl+Enter</span>' +
                    '<button type="button" class="sd-ai-send"><span class="bi bi-send" aria-hidden="true"></span><span class="sd-ai-send-txt">' + esc(t('send')) + '</span></button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(panel);

        el.panel = panel;
        el.list = panel.querySelector('.sd-ai-list');
        el.form = panel.querySelector('.sd-ai-compose');
        el.target = panel.querySelector('.sd-ai-target');
        el.agents = panel.querySelector('.sd-ai-agents');
        el.input = panel.querySelector('.sd-ai-input');
        el.send = panel.querySelector('.sd-ai-send');

        panel.querySelector('.sd-ai-close').addEventListener('click', close);

        el.send.addEventListener('click', function (e) {
            e.preventDefault();
            send();
        });

        el.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                send();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                close();
            }
        });

        // "@claude ..." / "@ai ..." at the start picks who answers.
        el.input.addEventListener('input', function () {
            var m = /^\s*[@\/](claude|ai)\b/i.exec(el.input.value);

            if (m) {
                var agent = m[1].toLowerCase();

                if (agent !== state.agent && agentReady(agent)) {
                    state.agent = agent;
                    drawAgents();
                }
            }
        });

        el.input.addEventListener('focus', drawTarget);

        el.list.addEventListener('click', onListClick);
        el.target.addEventListener('click', onTargetClick);
        el.agents.addEventListener('click', onAgentClick);
    }

    function open(nodeId) {
        build();

        var b = bridge();

        if (nodeId && b) {
            b.select(nodeId);
            state.wholePage = false;
        }

        state.open = true;
        el.panel.classList.remove('d-none');
        setButton(true);
        drawTarget();

        // The tab may have changed since the panel was last open.
        var page = b ? b.page() : null;
        var pageId = page ? page.page_id : 0;

        if (!state.loaded || pageId !== state.pageId) {
            load();
        } else {
            draw();
        }

        clearInterval(state.syncTimer);
        // The target follows the canvas selection while the panel is open.
        state.syncTimer = setInterval(syncWithEditor, 700);

        setTimeout(function () { el.input.focus(); }, 30);
    }

    function close() {
        if (!el.panel) {
            return;
        }

        state.open = false;
        el.panel.classList.add('d-none');
        setButton(false);
        clearInterval(state.syncTimer);
    }

    function toggle() {
        if (state.open) {
            close();
        } else {
            open();
        }
    }

    function setButton(on) {
        var btn = document.getElementById('sd-ai-btn');

        if (btn) {
            btn.setAttribute('aria-expanded', on ? 'true' : 'false');
            btn.classList.toggle('active', !!on);
        }
    }

    function setBusyDot() {
        var btn = document.getElementById('sd-ai-btn');

        if (btn) {
            btn.classList.toggle('sd-ai-busy', waiting().length > 0);
        }
    }

    function syncWithEditor() {
        var b = bridge();
        var page = b ? b.page() : null;
        var pageId = page ? page.page_id : 0;

        if (pageId !== state.pageId) {
            load();
            return;
        }

        drawTarget();
    }

    /* ------------------------------------------------------------------
       Talking to the site
       ------------------------------------------------------------------ */

    function post(sub, extra) {
        var b = bridge();

        return b.post(sub, extra || {}).then(function (resp) {
            if (!resp || resp.status !== 'success') {
                throw new Error((resp && resp.message) ? resp.message : 'error');
            }

            return resp;
        });
    }

    // Claude and Pinegrap AI were both taken out of the Workspace while the
    // editor was open: the assistant leaves the editor once nothing is left
    // waiting.
    function retireIfUnset() {
        if (state.agents.length || waiting().length) {
            return false;
        }

        if (state.open) {
            close();
        }

        var btn = document.getElementById('sd-ai-btn');

        if (btn) {
            btn.hidden = true;
        }

        window.PgDesignerAI = null;

        return true;
    }

    function load() {
        var b = bridge();
        var page = b ? b.page() : null;

        state.pageId = page ? page.page_id : 0;
        state.loading = true;
        draw();

        post('ai_state', { page_id: state.pageId }).then(function (resp) {
            state.loaded = true;
            state.loading = false;
            state.agents = resp.agents || [];
            state.problem = resp.page_ok ? '' : (resp.problem || '');
            state.requests = resp.requests || [];

            if (retireIfUnset()) {
                return;
            }

            var saved = storeGet(AGENT_KEY);

            if (!agentReady(state.agent)) {
                state.agent = agentReady(saved) ? saved : '';
            }

            if (!state.agent) {
                state.agents.forEach(function (a) {
                    if (!state.agent && a.ready) {
                        state.agent = a.agent;
                    }
                });
            }

            draw();
            poll();
        }).catch(function (err) {
            state.loading = false;
            state.problem = err.message;
            draw();
        });
    }

    function agentReady(agent) {
        for (var i = 0; i < state.agents.length; i++) {
            if (state.agents[i].agent === agent) {
                return !!state.agents[i].ready;
            }
        }

        return false;
    }

    function agentLabel(agent) {
        for (var i = 0; i < state.agents.length; i++) {
            if (state.agents[i].agent === agent) {
                return state.agents[i].label;
            }
        }

        return agent === 'claude' ? 'Claude' : 'Pinegrap AI';
    }

    function target() {
        var b = bridge();
        var node = (!state.wholePage && b) ? b.selected() : null;

        return node ? { id: node._id, label: b.label(node) } : null;
    }

    function send() {
        if (state.sending) {
            return;
        }

        var prompt = el.input.value.trim();
        var m = /^\s*[@\/](claude|ai)\b/i.exec(prompt);

        if (m && agentReady(m[1].toLowerCase())) {
            state.agent = m[1].toLowerCase();
        }

        if (!prompt || !state.agent) {
            el.input.focus();
            return;
        }

        var b = bridge();
        var tgt = target();

        state.sending = true;
        drawSending();

        post('ai_ask', {
            page_id: state.pageId,
            node_id: tgt ? tgt.id : '',
            agent: state.agent,
            prompt: prompt,
            tree_json: b.treeJSON()
        }).then(function (resp) {
            state.sending = false;
            storeSet(AGENT_KEY, state.agent);
            el.input.value = '';
            state.requests.unshift(resp.request);
            draw();
            poll(true);
        }).catch(function (err) {
            state.sending = false;
            drawSending();
            toast(esc(err.message), 'error', 7000);
        });
    }

    function waiting() {
        return state.requests.filter(function (r) { return r && r.waiting; });
    }

    // One status call at a time. For Pinegrap AI the call is what carries
    // the request on, so the next one follows right after; Claude is only
    // looked in on.
    function poll(soon) {
        if (state.polling) {
            return;
        }

        clearTimeout(state.pollTimer);
        setBusyDot();

        var list = waiting();

        if (!list.length) {
            return;
        }

        var req = list[list.length - 1];
        var delay = soon ? 400 : (req.agent === 'ai' ? 1500 : 12000);

        state.pollTimer = setTimeout(function () {
            state.polling = true;

            post('ai_status', { request_id: req.id }).then(function (resp) {
                state.polling = false;
                replace(resp.request);
                draw();
                announce(req, resp.request);
                poll();
            }).catch(function () {
                state.polling = false;
                state.pollTimer = setTimeout(function () { poll(); }, 15000);
            });
        }, delay);
    }

    function replace(req) {
        for (var i = 0; i < state.requests.length; i++) {
            if (state.requests[i].id === req.id) {
                state.requests[i] = req;
                return;
            }
        }

        state.requests.unshift(req);
    }

    // A request that finished while the panel was closed says so.
    function announce(before, after) {
        if (!before.waiting || after.waiting || state.open) {
            return;
        }

        var kind = (after.status === 'answered') ? 'success' : 'warning';
        toast('<strong>' + esc(agentLabel(after.agent)) + '</strong> — ' + esc(after.status === 'answered' ? (after.answer || t('title')) : (after.error || t('failed'))), kind, 8000);
    }

    function findProposal(id) {
        for (var i = 0; i < state.requests.length; i++) {
            var list = state.requests[i].proposals || [];

            for (var j = 0; j < list.length; j++) {
                if (list[j].id === id) {
                    return list[j];
                }
            }
        }

        return null;
    }

    function apply(proposal, quiet) {
        var b = bridge();

        if (!b.mayEdit()) {
            toast(esc(t('view_only')), 'warning', 6000);
            return Promise.resolve(false);
        }

        var page = b.page();

        if (!page || page.page_id !== proposal.page_id) {
            toast(esc(t('other_page')), 'warning', 6000);
            return Promise.resolve(false);
        }

        return post('ai_apply', { proposal_id: proposal.id, tree_json: b.treeJSON() }).then(function (resp) {
            var tree = JSON.parse(resp.tree);

            b.apply(tree, proposal.node_id || null);
            proposal.status = 'applied';
            proposal.applied_to = 'editor';
            draw();

            var notes = [];

            if (resp.removed && resp.removed.length) {
                notes.push(t('removes', { list: resp.removed.join(', ') }));
            }

            if (resp.dropped && resp.dropped.length) {
                notes.push(t('dropped', { list: resp.dropped.join(', ') }));
            }

            if (!quiet) {
                toast(esc(t('applied')) + (notes.length ? '<br>' + esc(notes.join(' · ')) : ''), notes.length ? 'warning' : 'success', 7000);
            }

            return true;
        }).catch(function (err) {
            toast(esc(err.message), 'error', 8000);
            return false;
        });
    }

    function dismiss(proposal) {
        post('ai_dismiss', { proposal_id: proposal.id }).then(function (resp) {
            proposal.status = resp.proposal ? resp.proposal.status : 'dismissed';
            draw();
        }).catch(function (err) {
            toast(esc(err.message), 'error', 6000);
        });
    }

    function cancel(req) {
        post('ai_cancel', { request_id: req.id }).then(function () {
            req.status = 'cancelled';
            req.waiting = false;
            draw();
            setBusyDot();
        }).catch(function (err) {
            toast(esc(err.message), 'warning', 6000);
        });
    }

    /* ------------------------------------------------------------------
       Drawing
       ------------------------------------------------------------------ */

    function draw() {
        if (!el.panel) {
            return;
        }

        drawAgents();
        drawTarget();
        drawSending();
        drawList();
        setBusyDot();
    }

    function drawAgents() {
        if (!el.agents) {
            return;
        }

        var html = '';

        state.agents.forEach(function (a) {
            var on = (a.agent === state.agent);

            html += '<button type="button" role="radio" class="sd-ai-agent' + (on ? ' active' : '') + '" data-agent="' + esc(a.agent) + '"' +
                ' aria-checked="' + (on ? 'true' : 'false') + '"' +
                (a.ready ? '' : ' disabled title="' + esc(t('unavailable', { why: a.problem })) + '"') + '>' +
                '<span class="bi ' + (a.agent === 'claude' ? 'bi-cloud' : 'bi-cpu') + '" aria-hidden="true"></span>' + esc(a.label) + '</button>';
        });

        el.agents.innerHTML = html;
    }

    function drawTarget() {
        if (!el.target) {
            return;
        }

        var tgt = target();
        var key = (tgt ? tgt.id + '|' + tgt.label : '') + '|' + (state.wholePage ? 1 : 0);

        if (key === state.lastTarget && el.target.innerHTML !== '') {
            return;
        }

        state.lastTarget = key;

        if (tgt) {
            el.target.innerHTML = '<span class="sd-ai-chip"><span class="bi bi-bounding-box" aria-hidden="true"></span>' + esc(t('selected', { name: tgt.label })) + '</span>' +
                '<button type="button" class="sd-ai-link" data-act="page">' + esc(t('use_page')) + '</button>';
        } else {
            el.target.innerHTML = '<span class="sd-ai-chip sd-ai-chip-page"><span class="bi bi-file-earmark-richtext" aria-hidden="true"></span>' + esc(t('whole_page')) + '</span>' +
                '<span class="sd-ai-muted">' + esc(t('use_selection')) + '</span>';
        }
    }

    function drawSending() {
        if (!el.send) {
            return;
        }

        var blocked = state.sending || !state.agent || (state.problem !== '');

        el.send.disabled = blocked;
        el.input.disabled = (state.problem !== '');
        el.send.querySelector('.sd-ai-send-txt').textContent = state.sending ? t('sending') : t('send');
    }

    function drawList() {
        var html = '';

        if (state.loading && !state.loaded) {
            html = '<div class="sd-ai-empty"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span></div>';
        } else {
            if (state.problem) {
                html += '<div class="sd-ai-problem"><span class="bi bi-exclamation-triangle" aria-hidden="true"></span> ' + esc(state.problem) + '</div>';
            }

            var none = state.agents.length && !state.agents.some(function (a) { return a.ready; });

            if (none) {
                html += '<div class="sd-ai-problem">';

                state.agents.forEach(function (a) {
                    html += '<div><strong>' + esc(a.label) + ':</strong> ' + esc(a.problem) + '</div>';
                });

                html += '<a class="sd-ai-link" href="' + esc(cfg.settingsUrl || '#') + '" target="_blank" rel="noopener">' + esc(t('setup')) + '</a></div>';
            }

            if (!state.requests.length && !state.problem) {
                html += '<div class="sd-ai-empty">' + esc(t('empty')) + '</div>';
            }

            state.requests.forEach(function (req) {
                html += drawRequest(req);
            });
        }

        el.list.innerHTML = html;
    }

    function drawRequest(req) {
        var html = '<article class="sd-ai-item" data-request="' + req.id + '">';

        html += '<div class="sd-ai-q">' +
            '<span class="sd-ai-badge">' + esc(agentLabel(req.agent)) + '</span>' +
            (req.node_id ? '<span class="sd-ai-badge sd-ai-badge-muted" title="' + esc(req.node_id) + '"><span class="bi bi-bounding-box" aria-hidden="true"></span></span>' : '') +
            '<span class="sd-ai-time">' + esc(when(req.created_at)) + '</span>' +
            '</div>' +
            '<div class="sd-ai-prompt">' + esc(req.prompt) + '</div>';

        if (req.waiting) {
            html += '<div class="sd-ai-state"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ' +
                esc(req.status === 'queued' ? t('queued') + ' · ' + (req.agent === 'claude' ? t('wait_claude') : t('wait_ai')) : (req.agent === 'claude' ? t('wait_claude') : t('wait_ai'))) +
                (req.status === 'queued' ? ' <button type="button" class="sd-ai-link" data-act="cancel">' + esc(t('cancel')) + '</button>' : '') +
                '</div>';
        } else if (req.status === 'failed') {
            html += '<div class="sd-ai-state sd-ai-state-bad"><span class="bi bi-x-circle" aria-hidden="true"></span> ' + esc(t('failed')) + (req.error ? ': ' + esc(req.error) : '') + '</div>';
        } else if (req.status === 'cancelled') {
            html += '<div class="sd-ai-state"><span class="bi bi-slash-circle" aria-hidden="true"></span> ' + esc(t('cancelled')) + '</div>';
        }

        if (req.answer) {
            html += '<div class="sd-ai-answer">' + esc(req.answer).replace(/\n/g, '<br>') + '</div>';
        }

        (req.proposals || []).forEach(function (p) {
            html += drawProposal(p, req);
        });

        if (!req.waiting && req.status === 'answered' && !(req.proposals || []).length) {
            html += '<div class="sd-ai-muted">' + esc(t('no_proposal')) + '</div>';
        }

        return html + '</article>';
    }

    function drawProposal(p, req) {
        var html = '<div class="sd-ai-prop" data-proposal="' + p.id + '">';
        var facts = [t('changes', { n: p.ops_count })];

        if (p.whole_page) {
            facts.push(t('whole'));
        }

        // The answer above already says what the proposal does.
        if (p.summary && !(req && req.answer)) {
            html += '<div class="sd-ai-prop-sum">' + esc(p.summary) + '</div>';
        }

        html += '<div class="sd-ai-muted">' + esc(facts.join(' · ')) + '</div>';

        if (p.removed && p.removed.length) {
            html += '<div class="sd-ai-warn"><span class="bi bi-exclamation-triangle" aria-hidden="true"></span> ' + esc(t('removes', { list: p.removed.join(', ') })) + '</div>';
        }

        if (p.dropped && p.dropped.length) {
            html += '<div class="sd-ai-warn"><span class="bi bi-shield-exclamation" aria-hidden="true"></span> ' + esc(t('dropped', { list: p.dropped.join(', ') })) + '</div>';
        }

        if (p.status === 'pending') {
            html += '<div class="sd-ai-prop-btns">' +
                '<button type="button" class="sd-ai-btn-primary" data-act="apply"><span class="bi bi-magic" aria-hidden="true"></span> ' + esc(t('apply')) + '</button>' +
                '<button type="button" class="sd-ai-btn-ghost" data-act="dismiss">' + esc(t('dismiss')) + '</button>' +
                '</div>';
        } else if (p.status === 'applied') {
            html += '<div class="sd-ai-ok"><span class="bi bi-check2-circle" aria-hidden="true"></span> ' + esc(p.applied_to === 'page' ? t('applied_page') : t('applied')) + '</div>';

            if (p.applied_to === 'editor') {
                html += '<div class="sd-ai-prop-btns"><button type="button" class="sd-ai-btn-ghost" data-act="apply">' + esc(t('reapply')) + '</button></div>';
            }
        } else {
            html += '<div class="sd-ai-muted">' + esc(p.status === 'dismissed' ? t('dismissed') : (p.status === 'stale' ? t('stale') : (p.status === 'reverted' ? t('reverted') : p.status))) + '</div>';
        }

        return html + '</div>';
    }

    function when(unix) {
        if (!unix) {
            return '';
        }

        var d = new Date(unix * 1000);
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };

        return pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    /* ------------------------------------------------------------------
       Clicks
       ------------------------------------------------------------------ */

    function onListClick(e) {
        var btn = e.target.closest ? e.target.closest('[data-act]') : null;

        if (!btn) {
            return;
        }

        var act = btn.getAttribute('data-act');
        var propEl = btn.closest('[data-proposal]');
        var reqEl = btn.closest('[data-request]');

        if (propEl && (act === 'apply' || act === 'dismiss')) {
            var proposal = findProposal(parseInt(propEl.getAttribute('data-proposal'), 10));

            if (!proposal) {
                return;
            }

            if (act === 'apply') {
                btn.disabled = true;
                apply(proposal).then(function () { btn.disabled = false; });
            } else {
                dismiss(proposal);
            }

            return;
        }

        if (reqEl && act === 'cancel') {
            var id = parseInt(reqEl.getAttribute('data-request'), 10);

            state.requests.forEach(function (r) {
                if (r.id === id) {
                    cancel(r);
                }
            });
        }
    }

    function onTargetClick(e) {
        var btn = e.target.closest ? e.target.closest('[data-act="page"]') : null;

        if (btn) {
            state.wholePage = true;
            var b = bridge();

            if (b) {
                b.select(null);
            }

            drawTarget();
            el.input.focus();
        }
    }

    function onAgentClick(e) {
        var btn = e.target.closest ? e.target.closest('[data-agent]') : null;

        if (btn && !btn.disabled) {
            state.agent = btn.getAttribute('data-agent');
            storeSet(AGENT_KEY, state.agent);
            drawAgents();
            drawSending();
        }
    }

    /* ------------------------------------------------------------------
       Start
       ------------------------------------------------------------------ */

    // A new selection on the canvas is a new target: the "whole page"
    // choice only lasts until something is selected again.
    function watchSelection() {
        var last = '';

        setInterval(function () {
            if (!state.open) {
                return;
            }

            var b = bridge();
            var node = b ? b.selected() : null;
            var id = node ? node._id : '';

            if (id && id !== last) {
                state.wholePage = false;
            }

            last = id;
        }, 500);
    }

    // Opened from a workspace answer (…&ai_proposal=ID): the proposal goes on
    // the canvas once the page is there, as one undo step.
    function openProposalFromLink() {
        var id = parseInt(cfg.proposal, 10) || 0;

        if (!id) {
            return;
        }

        var tries = 0;
        var wait = setInterval(function () {
            var b = bridge();
            var ready = b && b.tree() && document.querySelector('#sd-canvas iframe');

            if (!ready && ++tries < 40) {
                return;
            }

            clearInterval(wait);
            open();

            post('ai_state', { page_id: b.page() ? b.page().page_id : 0 }).then(function (resp) {
                state.requests = resp.requests || state.requests;
                var found = null;

                (resp.requests || []).forEach(function (r) {
                    (r.proposals || []).forEach(function (p) { if (p.id === id) { found = p; } });
                });

                // A proposal made from a channel is not among the editor's
                // requests: it is applied by its id alone.
                var proposal = found || { id: id, page_id: b.page() ? b.page().page_id : 0, node_id: '', status: 'pending' };

                if (proposal.status === 'pending' || (proposal.status === 'applied' && proposal.applied_to === 'editor')) {
                    apply(proposal, true).then(function (ok) {
                        if (ok) {
                            toast(esc(t('from_workspace')), 'success', 9000);
                        }
                    });
                }

                draw();
            });
        }, 250);
    }

    function boot() {
        var btn = document.getElementById('sd-ai-btn');

        if (btn) {
            btn.addEventListener('click', toggle);
        }

        watchSelection();
        openProposalFromLink();

        // Requests still waiting from an earlier visit are carried on.
        setTimeout(function () {
            if (!state.loaded) {
                var b = bridge();
                var page = b ? b.page() : null;

                if (page && page.page_id > 0) {
                    state.pageId = page.page_id;
                    post('ai_state', { page_id: page.page_id }).then(function (resp) {
                        state.loaded = true;
                        state.agents = resp.agents || [];
                        state.requests = resp.requests || [];
                        state.problem = resp.page_ok ? '' : (resp.problem || '');

                        if (retireIfUnset()) {
                            return;
                        }

                        state.agents.forEach(function (a) { if (!state.agent && a.ready) { state.agent = a.agent; } });
                        poll();
                        setBusyDot();
                    }).catch(function () { /* the panel asks again when opened */ });
                }
            }
        }, 1500);
    }

    window.PgDesignerAI = { open: open, close: close, toggle: toggle };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(boot, 0); });
    } else {
        setTimeout(boot, 0);
    }
})();
