/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the flow canvas (workspace_flows.php): the person's flows and
 * the live ones their channels may run in a list on the left, the flow drawn
 * in the middle, the settings of what is selected on the right.
 *
 * A flow is boxes and the arrows between them. A box is HTML on a layer
 * moved and scaled with one CSS transform; the arrows are cubic curves in an
 * SVG on the same layer, from a box's right port to the next box's left one.
 * An arrow is an "after" arrow: the box it leads to waits for the box it
 * leaves. Places are canvas units snapped to a 16 px grid, kept in the graph
 * the store keeps (includes/workspace/flows.php, ws_flow_graph_normalize());
 * the notes a graph may carry are kept as they are and not drawn yet.
 *
 * Every change is saved as the draft 1.5 s after the last one, and when the
 * window loses focus or the page is left; a save names the draft_rev it was
 * made on, so a draft changed on another screen is never written over (the
 * screen offers to load it again). The server answers what stops the draft
 * from being published; those boxes are outlined and the list of problems
 * leads to them. Publishing is a question the person answers.
 *
 * The outline view lists the boxes in the order of their arrows with all of
 * their settings and "Connect to..." controls: everything the canvas does,
 * without dragging, for phones (it is the default under 768 px) and for
 * screen readers. Somebody who may only run a flow sees it read-only.
 *
 * A live flow is run from the toolbar (a question first, then one start: the
 * request carries a token kept until an answer says what became of it, so a
 * double click or a request sent again starts one run). "Flow runs" lists the
 * runs in the side panel; a run opened there is drawn on the canvas as it
 * went: the graph of the version it follows, each box with its state as a
 * mark and a word, a waiting task box with a link to its task. Nothing can be
 * changed while a run is shown; the draft and the editing tools come back when
 * it is closed, and a change still on its way is saved meanwhile. An open run
 * is read again on a timer that slows while nothing changes and stops once the
 * run ends or the tab is hidden; what changed is told to screen readers.
 *
 * A flow is shared from the "⋯" menu (includes/workspace/flow_shares.php):
 * its owner sees whom it is shared with, changes a person's level (view, run,
 * edit), takes a share back, and adds people (the people picker the note
 * share uses) or a channel; everybody else who reaches the flow sees the same
 * list read-only. A flow shared with the person is listed under "Shared with
 * me" with what they may do with it. A person shared at edit edits the graph
 * as its owner does, but the home channel and deleting stay the owner's.
 *
 * assets/js/workspace.js starts it (window.PGWsFlow.start()) with the team
 * it has loaded and lends it its helpers through window.PGWsKit. Every text
 * comes from the #ws-config block (ws_flow_js_strings()); names and texts
 * the people wrote are written with textContent.
 *
 * Served minified as assets/js/workspace_flow.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var GRID = 16;
    var BOX_W = 208;
    var BOX_H = 80;
    var ZOOM_MIN = 0.25;
    var ZOOM_MAX = 2;
    var SAVE_DELAY = 1500;
    // Browsers refuse a keepalive request body over 64 KiB; the action and
    // the token ride on top of the draft.
    var KEEPALIVE_BYTES = 60000;
    var SVG_NS = 'http://www.w3.org/2000/svg';

    // An open run is read again after POLL_FIRST ms, then each look that
    // finds nothing new waits POLL_GROW times longer, up to POLL_MAX; a
    // look that got no answer waits twice as long each time, up to
    // POLL_FAIL_MAX, and one the server refused is not made again
    // (pollFailed()). The screen's ws_tick, which
    // carries the runs on, is sent at most once in TICK_GAP ms.
    var POLL_FIRST = 2000;
    var POLL_GROW = 1.5;
    var POLL_MAX = 30000;
    var POLL_FAIL_MAX = 60000;
    var TICK_GAP = 20000;

    // How a run and a box of it stand (ws_flow_run_statuses(),
    // ws_flow_step_statuses()): the word and the mark beside the colour.
    var RUN_STATES = {
        running: ['fl_run_running', 'bi-arrow-repeat'],
        waiting: ['fl_run_waiting', 'bi-hourglass-split'],
        done: ['fl_run_done', 'bi-check-circle-fill'],
        failed: ['fl_run_failed', 'bi-x-octagon-fill'],
        cancelled: ['fl_run_cancelled_state', 'bi-slash-circle']
    };
    var STEP_STATES = {
        ready: ['fl_step_ready', 'bi-hourglass'],
        running: ['fl_step_running', 'bi-arrow-repeat'],
        waiting: ['fl_step_waiting', 'bi-hourglass-split'],
        done: ['fl_step_done', 'bi-check-circle-fill'],
        failed: ['fl_step_failed', 'bi-x-octagon-fill'],
        skipped: ['fl_step_skipped', 'bi-skip-forward'],
        none: ['fl_step_none', 'bi-circle']
    };

    // What each kind of box is: its sign, its name, whether an arrow may
    // lead into it, and the outcomes an arrow may leave it on (the first is
    // the one a new arrow takes), as ws_flow_kinds() has them.
    var KINDS = {
        manual: { icon: 'bi-play-circle', name: 'fl_kind_manual', into: false, out: ['next'] },
        schedule: { icon: 'bi-clock', name: 'fl_kind_schedule', into: false, out: ['next'] },
        event: { icon: 'bi-lightning', name: 'fl_kind_event', into: false, out: ['next'] },
        join: { icon: 'bi-person-plus', name: 'fl_kind_join', into: false, out: ['next'] },
        task: { icon: 'bi-check2-square', name: 'fl_kind_task', into: true, out: ['done', 'cancelled'] },
        post: { icon: 'bi-chat-left-text', name: 'fl_kind_post', into: true, out: ['done', 'failed'] },
        email: { icon: 'bi-envelope', name: 'fl_kind_email', into: true, out: ['done', 'failed'] },
        notify: { icon: 'bi-bell', name: 'fl_kind_notify', into: true, out: ['done', 'failed'] },
        task_open: { icon: 'bi-plus-square', name: 'fl_kind_task_open', into: true, out: ['done', 'failed'] },
        webhook: { icon: 'bi-broadcast', name: 'fl_kind_webhook', into: true, out: ['done', 'failed'] },
        web_check: { icon: 'bi-globe2', name: 'fl_kind_web_check', into: true, out: ['done', 'failed'] },
        task_digest: { icon: 'bi-list-check', name: 'fl_kind_task_digest', into: true, out: ['done', 'failed'] },
        report: { icon: 'bi-bar-chart', name: 'fl_kind_report', into: true, out: ['done', 'failed'] },
        checkin: { icon: 'bi-person-check', name: 'fl_kind_checkin', into: true, out: ['done', 'failed'] },
        start_action: { icon: 'bi-alarm', name: 'fl_kind_start_action', into: true, out: ['done', 'failed'] },
        change: { icon: 'bi-pencil-square', name: 'fl_kind_change', into: true, out: ['done', 'failed'] },
        lead: { icon: 'bi-person-vcard', name: 'fl_kind_lead', into: true, out: ['done', 'failed'] },
        condition: { icon: 'bi-signpost-split', name: 'fl_kind_condition', into: true, out: ['yes', 'no'] },
        end: { icon: 'bi-flag', name: 'fl_kind_end', into: true, out: [] }
    };

    // What an arrow is taken on, as its box says it.
    var OUTCOMES = {
        done: 'fl_on_done',
        cancelled: 'fl_on_cancelled',
        failed: 'fl_on_failed',
        yes: 'fl_on_yes',
        no: 'fl_on_no'
    };

    // What a share lets its holder do (ws_flow_share_levels()), lowest first.
    var LEVELS = {
        view: 'fl_level_view',
        run: 'fl_level_run',
        edit: 'fl_level_edit'
    };

    // What the palette adds.
    var PALETTE = [
        ['manual', 'bi-play-circle', 'fl_add_manual'],
        ['schedule', 'bi-clock', 'fl_kind_schedule'],
        ['event', 'bi-lightning', 'fl_kind_event'],
        ['join', 'bi-person-plus', 'fl_kind_join'],
        ['task', 'bi-check2-square', 'fl_add_task'],
        ['condition', 'bi-signpost-split', 'fl_kind_condition'],
        ['post', 'bi-chat-left-text', 'fl_add_post'],
        ['email', 'bi-envelope', 'fl_add_email'],
        ['notify', 'bi-bell', 'fl_kind_notify'],
        ['task_open', 'bi-plus-square', 'fl_kind_task_open'],
        ['webhook', 'bi-broadcast', 'fl_kind_webhook'],
        ['web_check', 'bi-globe2', 'fl_kind_web_check'],
        ['task_digest', 'bi-list-check', 'fl_kind_task_digest'],
        ['report', 'bi-bar-chart', 'fl_kind_report'],
        ['checkin', 'bi-person-check', 'fl_kind_checkin'],
        ['start_action', 'bi-alarm', 'fl_kind_start_action'],
        ['change', 'bi-pencil-square', 'fl_kind_change'],
        ['lead', 'bi-person-vcard', 'fl_kind_lead'],
        ['end', 'bi-flag', 'fl_add_end']
    ];

    var K = null;
    var CFG = {};
    var FL = {};
    var BOOT = {};
    var t = null;
    var el = null;
    var uid = 0;

    var state = {
        flows: [],
        flow: null,         // the flow open: its brief, version, problems
        graph: null,        // what is drawn: the draft, or the live graph read-only
        edit: false,
        problems: [],
        selected: null,     // { type: node | edge, id }
        view: { x: 40, y: 40, k: 1 },
        outline: false,
        dirty: false,
        changes: 0,         // counts every change, to tell one made while a request ran
        saving: null,
        saveTimer: 0,
        saveError: '',
        conflict: '',
        refocus: '',
        space: false,
        drag: null,
        pointers: {},
        pinch: null,
        tap: null,
        panel: '',          // 'runs' while the side panel lists the runs
        section: '',        // 'scheduled' while staff's scheduled actions are shown
        runs: { flow: 0, items: [], next: null, loading: false, error: '' }, // error: why the last load failed
        run: null,          // the run shown on the canvas (ws_flow_run_get)
        stash: null,        // what the run view put aside: the draft, selection, view, edit
        starting: false,    // a start is being asked or sent
        startToken: null,   // { flow, value } kept until a start is answered
        opening: {},        // run ids whose ws_flow_run_get from the list is on its way
        openSeq: 0,         // the run asked for last; an older answer is not shown
        // busy: the id of the run a look is on its way for; note: why the
        // run shown is not being read ({ lost, text }), null while it is.
        poll: { timer: 0, delay: POLL_FIRST, busy: 0, note: null },
        tickAt: 0,
        share: null         // the Share dialog open: { flow, data (ws_flow_shares), busy }
    };

    var ui = {};

    // ── Small helpers ──────────────────────────────────────────────────

    function snap(value) {
        return Math.round(value / GRID) * GRID;
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function coord(value) {
        var limit = (FL.limits && FL.limits.coord) || 100000;

        return clamp(snap(value), -limit, limit);
    }

    function svgEl(tag, className) {
        var node = document.createElementNS(SVG_NS, tag);

        if (className) {
            node.setAttribute('class', className);
        }

        return node;
    }

    function nodeById(id) {
        return state.graph.nodes.filter(function (node) { return node.id === id; })[0] || null;
    }

    function kindOf(node) {
        return KINDS[node.kind] || { icon: 'bi-question-square', name: 'fl_box', into: true, out: [] };
    }

    function person(id) {
        return (BOOT.people || []).filter(function (member) { return member.id === id; })[0] || null;
    }

    function titleOf(node) {
        return ((node.kind === 'task') || (node.kind === 'task_open')) ? (node.config.title || node.label || '') : (node.label || '');
    }

    function nameOf(node) {
        return titleOf(node) || t(kindOf(node).name);
    }

    function freeId(prefix, list) {
        var n = 1;

        while (list.some(function (item) { return item.id === prefix + '_' + n; })) {
            n++;
        }

        return prefix + '_' + n;
    }

    function announce(text) {
        ui.live.textContent = '';
        window.setTimeout(function () { ui.live.textContent = text; }, 30);
    }

    function typing(target) {
        return !!(target && target.closest && target.closest('input, textarea, select, [contenteditable="true"]'));
    }

    function bytes(text) {
        return new Blob([text]).size;
    }

    // The flow is the person's to change, whether or not a run is shown
    // meanwhile: its draft keeps being saved.
    function drafting() {
        return !!(state.flow && (state.flow.access === 'edit'));
    }

    // The draft: what is drawn, or what the run view put aside.
    function draftOf() {
        return state.stash ? state.stash.graph : state.graph;
    }

    // A moment of a run (ISO 8601 in UTC) on the site's clock, like the
    // other times of the Workspace; a zone the browser does not know falls
    // back to its own.
    function when(iso) {
        var date = iso ? new Date(iso) : null;
        var shape = { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' };

        if (!date || isNaN(date.getTime())) {
            return '';
        }

        try {
            return date.toLocaleString(CFG.locale || undefined, Object.assign({ timeZone: CFG.timezone || undefined }, shape));
        } catch (error) {
            return date.toLocaleString(CFG.locale || undefined, shape);
        }
    }

    function personName(id) {
        return person(id) ? person(id).name : '#' + id;
    }

    // The graph as the editor keeps it: every part there, a box's settings
    // an object; the notes go back as they came.
    function graphOf(source) {
        var graph = { v: 1, nodes: [], edges: [], notes: [] };

        (source && source.nodes || []).forEach(function (node) {
            var config = (node.config && !Array.isArray(node.config)) ? JSON.parse(JSON.stringify(node.config)) : {};

            graph.nodes.push({ id: String(node.id || ''), kind: String(node.kind || ''), label: String(node.label || ''), x: +node.x || 0, y: +node.y || 0, config: config });
        });

        (source && source.edges || []).forEach(function (edge) {
            graph.edges.push({ from: String(edge.from || ''), to: String(edge.to || ''), on: String(edge.on || '') });
        });

        graph.notes = (source && Array.isArray(source.notes)) ? source.notes : [];

        return graph;
    }

    // The boxes in the order of their arrows: the start first, then each box
    // once every box before it is placed; ties and loops by their place.
    function orderedNodes() {
        var nodes = state.graph.nodes;
        var into = {};
        var next = {};
        var byPlace = function (a, b) {
            return ((a.kind === 'manual') ? 0 : 1) - ((b.kind === 'manual') ? 0 : 1) || (a.y - b.y) || (a.x - b.x);
        };

        nodes.forEach(function (node) {
            into[node.id] = 0;
            next[node.id] = [];
        });

        state.graph.edges.forEach(function (edge) {
            if ((into[edge.to] !== undefined) && next[edge.from] && (edge.from !== edge.to) && (next[edge.from].indexOf(edge.to) === -1)) {
                next[edge.from].push(edge.to);
                into[edge.to]++;
            }
        });

        var ready = nodes.filter(function (node) { return into[node.id] === 0; }).sort(byPlace);
        var out = [];

        while (ready.length) {
            var node = ready.shift();

            out.push(node);
            next[node.id].forEach(function (id) {
                if (--into[id] === 0) {
                    ready.push(nodeById(id));
                }
            });
            ready.sort(byPlace);
        }

        return out.concat(nodes.filter(function (node) { return out.indexOf(node) === -1; }).sort(byPlace));
    }

    // Who a task box goes to, in words.
    function ruleLabel(rule) {
        var match = /^(department|user):([0-9]+)$/.exec(rule || '');

        if (rule === 'creator') {
            return t('fl_assign_creator');
        }

        if (rule === 'department_lead') {
            return t('tpl_assign_lead');
        }

        if (match && (match[1] === 'department')) {
            var department = (BOOT.departments || []).filter(function (item) { return item.id === +match[2]; })[0];

            return t('tpl_assign_dept', department ? department.name : '#' + match[2]);
        }

        if (match) {
            return person(+match[2]) ? person(+match[2]).name : '#' + match[2];
        }

        return t('tpl_assign_none');
    }

    // What the step boxes choose from (ws_flow_js_config()).
    function stepLists() {
        var steps = FL.steps || {};

        return {
            metrics: steps.metrics || [],
            digest: steps.digest_only || {},
            targets: steps.targets || [],
            changes: steps.changes || {},
            records: steps.records || {},
            operators: steps.operators || {},
            counts: steps.counts || [],
            repeats: steps.repeats || {},
            events: steps.events || null
        };
    }

    // Up to two names, then how many more.
    function peopleText(ids) {
        var people = ids.map(function (id) { return person(id) ? person(id).name : '#' + id; });

        return (people.length > 2) ? t('fl_people_more', people.slice(0, 2).join(', '), people.length - 2) : people.join(', ');
    }

    function metaOf(node) {
        var config = node.config || {};

        if (node.kind === 'manual') {
            var subject = (FL.subjects || []).filter(function (item) { return item.key === config.subject; })[0];

            return subject ? t('fl_starts_on', subject.label) : t('fl_manual_meta');
        }

        if (node.kind === 'task') {
            var people = (config.assignees || []).map(function (id) { return person(id) ? person(id).name : '#' + id; });
            var who = people.length ? ((people.length > 2) ? t('fl_people_more', people.slice(0, 2).join(', '), people.length - 2) : people.join(', ')) : ruleLabel(config.assign);

            return ((config.due_in === '') || (config.due_in === undefined) || (config.due_in === null)) ? who : t('fl_due_meta', who, config.due_in);
        }

        if (node.kind === 'post') {
            var channel = (BOOT.channels || []).filter(function (item) { return item.id === config.channel_id; })[0];

            return channel ? t('fl_post_meta', channel.name) : t('fl_post_no_channel');
        }

        if (node.kind === 'email') {
            return config.to ? t('fl_email_meta', config.to) : t('fl_email_no_to');
        }

        if (node.kind === 'notify') {
            return (config.user_ids || []).length ? peopleText(config.user_ids) : t('fl_notify_nobody');
        }

        if (node.kind === 'task_open') {
            return (config.assignees || []).length ? peopleText(config.assignees) : t('fl_task_open_meta');
        }

        if ((node.kind === 'checkin') || (node.kind === 'report') || (node.kind === 'task_digest')) {
            var place = (BOOT.channels || []).filter(function (item) { return item.id === config.channel_id; })[0];

            if (node.kind === 'task_digest') {
                return (config.mode === 'channel') ? (place ? t('fl_post_meta', place.name) : t('fl_post_no_channel')) : t('fl_digest_mine');
            }

            if (node.kind === 'report') {
                return place ? t('fl_post_meta', place.name) : (config.to || t('fl_report_nowhere'));
            }

            return place ? t('fl_post_meta', place.name) : t('fl_post_no_channel');
        }

        if (node.kind === 'start_action') {
            var target = stepLists().targets.filter(function (item) { return item.id === config.target_id; })[0];

            return target ? target.name : t('fl_start_none');
        }

        if (node.kind === 'condition') {
            return conditionMeta(config.rule || {});
        }

        if (node.kind === 'schedule') {
            return config.date ? config.date + ' ' + (config.time || '') + ' · ' + (stepLists().repeats[config.repeat] || '') : t('fl_when_none');
        }

        if (node.kind === 'event') {
            var events = stepLists().events;
            var happening = (events && events.list[config.event]) ? events.list[config.event].label : config.event;

            return happening || t('fl_when_none');
        }

        if (node.kind === 'lead') {
            return config.private ? t('fl_lead_meta_private') : t('fl_lead_meta');
        }

        if (node.kind === 'join') {
            var joined = (BOOT.channels || []).filter(function (item) { return item.id === config.channel_id; })[0];

            return joined ? t('fl_post_meta', joined.name) : t('fl_join_any');
        }

        if (node.kind === 'change') {
            var kind = stepLists().changes[config.record];
            var what = kind ? kind.label : (config.record || t('fl_change_none'));

            return what + (config.id ? ' #' + config.id : '') + ((config.action === 'delete') ? ' · ' + t('fl_change_delete') : '');
        }

        if ((node.kind === 'webhook') || (node.kind === 'web_check')) {
            return config.url ? config.url.replace(/^https:\/\//i, '').split('/')[0] : t('fl_url_none');
        }

        return (node.kind === 'end') ? t('fl_end_meta') : '';
    }

    // ── Start ──────────────────────────────────────────────────────────

    function start(root, boot) {
        K = window.PGWsKit;

        if (!K) {
            return;
        }

        CFG = K.cfg || {};
        FL = CFG.flows || {};
        BOOT = boot || {};
        t = K.t;
        el = K.el;

        K.clear(root);

        if (!FL.ready) {
            root.appendChild(el('div', 'alert alert-warning', t('fl_not_ready')));
            return;
        }

        state.outline = !!(window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches);
        layout(root);
        bindCanvas();
        bindWindow();

        var asked = parseInt((/[?&]flow=([0-9]+)/.exec(window.location.search) || [])[1], 10) || 0;
        var section = FL.scheduled && /[?&]view=scheduled(&|$)/.test(window.location.search);

        K.api('ws_flow_list').then(function (data) {
            state.flows = data.flows || [];
            drawList();

            // The flow the address names opens, else the first of the
            // person's own (listed first); when the one asked for cannot be
            // opened (deleted, out of reach), that first one opens instead.
            var mine = state.flows.filter(function (flow) { return flow.mine; });
            var first = (mine[0] || state.flows[0] || {}).id || 0;

            if (section) {
                showScheduled();
            } else if (asked || first) {
                open(asked || first, first);
            } else {
                showNone();
            }
        }).catch(function (error) {
            K.fail(error);
            showNone();
        });
    }

    function layout(root) {
        ui.root = root;
        ui.bar = el('nav', 'pg-toolbar navigation mb-2 wsf-bar');
        ui.bar.setAttribute('aria-label', t('fl_toolbar'));
        ui.problemBox = el('div', 'wsf-problems d-none');
        ui.problemBox.id = 'wsf-problems';
        ui.conflictBox = el('div', 'alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 d-none');
        // While a run is shown: which, and the way back to the flow.
        ui.runBox = el('div', 'wsf-run-bar d-none');
        ui.app = el('div', 'wsf-app');
        ui.listCol = el('aside', 'wsf-list');
        ui.listCol.setAttribute('aria-label', t('fl_nav'));
        ui.main = el('div', 'wsf-main');
        ui.side = el('aside', 'wsf-side');
        ui.side.id = 'wsf-side';
        ui.side.setAttribute('aria-label', t('fl_settings'));
        ui.live = el('div', 'visually-hidden');
        ui.live.setAttribute('aria-live', 'polite');

        // The canvas: a viewport, the layer it moves and scales, and on the
        // layer the arrows under the boxes.
        ui.viewport = el('div', 'wsf-viewport');
        ui.viewport.tabIndex = -1;
        ui.viewport.setAttribute('role', 'application');
        ui.viewport.setAttribute('aria-roledescription', t('fl_canvas'));
        ui.viewport.setAttribute('aria-label', t('fl_canvas_help'));
        ui.layer = el('div', 'wsf-layer');
        ui.svg = svgEl('svg', 'wsf-edges');
        ui.svg.setAttribute('aria-hidden', 'true');
        ui.svg.setAttribute('width', '1');
        ui.svg.setAttribute('height', '1');
        ui.svg.innerHTML = '<defs>'
            + '<marker id="wsf-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path class="wsf-arrowhead" d="M0 0L10 5L0 10z"/></marker>'
            + '<marker id="wsf-arrow-on" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path class="wsf-arrowhead-on" d="M0 0L10 5L0 10z"/></marker>'
            + '<marker id="wsf-arrow-bad" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path class="wsf-arrowhead-bad" d="M0 0L10 5L0 10z"/></marker>'
            + '<marker id="wsf-arrow-taken" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path class="wsf-arrowhead-taken" d="M0 0L10 5L0 10z"/></marker>'
            + '</defs>';
        ui.temp = svgEl('path', 'wsf-edge-temp d-none');
        ui.svg.appendChild(ui.temp);
        ui.boxes = el('div', 'wsf-boxes');
        ui.layer.appendChild(ui.svg);
        ui.layer.appendChild(ui.boxes);
        ui.viewport.appendChild(ui.layer);

        ui.empty = el('div', 'wsf-empty d-none', t('fl_empty_canvas'));
        ui.palette = el('div', 'wsf-palette');
        ui.palette.setAttribute('role', 'toolbar');
        ui.palette.setAttribute('aria-label', t('fl_palette'));
        ui.zoom = el('div', 'wsf-zoom');
        ui.zoom.setAttribute('role', 'toolbar');
        ui.zoom.setAttribute('aria-label', t('fl_zoom'));
        ui.zoomLabel = el('span', 'wsf-zoom-label', '100%');

        [['bi-dash-lg', 'fl_zoom_out', function () { zoomBy(1 / 1.2); }], null, ['bi-plus-lg', 'fl_zoom_in', function () { zoomBy(1.2); }], ['bi-arrows-fullscreen', 'fl_fit', fit]].forEach(function (item) {
            if (!item) {
                ui.zoom.appendChild(ui.zoomLabel);
                return;
            }

            var control = K.button('btn btn-sm btn-ghost', '', item[0], t(item[1]));
            control.addEventListener('click', item[2]);
            ui.zoom.appendChild(control);
        });

        ui.stage = el('div', 'wsf-stage');
        ui.stage.appendChild(ui.viewport);
        ui.stage.appendChild(ui.empty);
        ui.stage.appendChild(ui.palette);
        ui.stage.appendChild(ui.zoom);
        ui.outlineBox = el('div', 'wsf-outline d-none');
        ui.outlineBox.setAttribute('role', 'region');
        ui.outlineBox.setAttribute('aria-label', t('fl_outline'));
        // Staff's scheduled actions, in place of the canvas.
        ui.scheduled = el('div', 'ws-pane ws-sa-view h-100 d-none');
        ui.scheduled.setAttribute('role', 'region');
        ui.scheduled.setAttribute('aria-label', t('sa_title'));
        ui.main.appendChild(ui.stage);
        ui.main.appendChild(ui.outlineBox);
        ui.main.appendChild(ui.scheduled);

        ui.app.appendChild(ui.listCol);
        ui.app.appendChild(ui.main);
        ui.app.appendChild(ui.side);

        root.appendChild(ui.bar);
        root.appendChild(ui.conflictBox);
        root.appendChild(ui.runBox);
        root.appendChild(ui.problemBox);
        root.appendChild(ui.app);
        root.appendChild(ui.live);

        applyView();
    }

    // ── The list ───────────────────────────────────────────────────────

    function drawList() {
        K.clear(ui.listCol);

        [[true, 'fl_list_mine'], [false, 'fl_list_shared']].forEach(function (part) {
            var flows = state.flows.filter(function (flow) { return flow.mine === part[0]; });

            if (!flows.length && !part[0]) {
                return;
            }

            ui.listCol.appendChild(el('div', 'wsf-list-title', t(part[1])));

            var list = el('ul', 'list-unstyled mb-3');

            if (!flows.length) {
                list.appendChild(el('li', 'small text-body-secondary px-2', t('fl_none')));
            }

            flows.forEach(function (flow) {
                var item = el('li');
                var link = el('button', 'wsf-list-item' + ((state.flow && state.flow.id === flow.id) ? ' active' : ''));
                var text = el('span', 'wsf-list-text');

                link.type = 'button';
                link.appendChild(K.icon('bi-diagram-3'));
                text.appendChild(el('span', 'wsf-list-name', flow.name));

                // Somebody else's flow says what the person may do with it.
                if (!flow.mine && LEVELS[flow.access]) {
                    text.appendChild(el('span', 'wsf-list-level', t(LEVELS[flow.access])));
                }

                link.appendChild(text);
                link.appendChild(statusBadge(flow, true));

                if (state.flow && state.flow.id === flow.id) {
                    link.setAttribute('aria-current', 'true');
                }

                // The list is drawn again once the flow is open, and the
                // focus comes back to its entry; on a phone the list closes,
                // and the flow's name takes it.
                link.setAttribute('data-refocus', 'flow-' + flow.id);
                link.addEventListener('click', function () {
                    ui.app.classList.remove('wsf-show-list');
                    state.refocus = 'flow-' + flow.id + ' name';
                    open(flow.id);
                });
                item.appendChild(link);
                list.appendChild(item);
            });

            ui.listCol.appendChild(list);
        });

        // Staff's scheduled actions: one entry, the section it opens.
        if (FL.scheduled) {
            var item = el('li');
            var link = el('button', 'wsf-list-item' + ((state.section === 'scheduled') ? ' active' : ''));
            var list = el('ul', 'list-unstyled mb-3 pt-2 border-top');

            link.type = 'button';
            link.appendChild(K.icon('bi-alarm'));
            link.appendChild(el('span', 'wsf-list-text', t('sa_title')));
            link.setAttribute('data-refocus', 'scheduled');

            if (state.section === 'scheduled') {
                link.setAttribute('aria-current', 'true');
            }

            link.addEventListener('click', function () {
                ui.app.classList.remove('wsf-show-list');
                state.refocus = 'scheduled';
                showScheduled();
            });
            item.appendChild(link);
            list.appendChild(item);
            ui.listCol.appendChild(list);
        }
    }

    function statusBadge(flow, small) {
        var text = t('fl_status_draft');
        var tone = 'secondary';

        if (flow.status === 'live') {
            text = t('fl_status_live', flow.version);
            tone = 'success';
        } else if (flow.status === 'paused') {
            text = t('fl_status_paused', flow.version);
            tone = 'warning';
        }

        return el('span', 'badge rounded-pill wsf-status text-bg-' + tone + (small ? ' wsf-status-sm' : ''), text);
    }

    function listEntry(flow) {
        var brief = { id: flow.id, name: flow.name, status: flow.status, access: flow.access, mine: flow.mine, channel_id: flow.channel_id, draft_rev: flow.draft_rev, version: flow.version };
        var found = false;

        state.flows = state.flows.map(function (item) {
            if (item.id === flow.id) {
                found = true;
                return brief;
            }

            return item;
        });

        if (!found) {
            state.flows.unshift(brief);
        }

        drawList();
    }

    function showNone() {
        endRun();
        state.panel = '';
        state.flow = null;
        state.graph = graphOf(null);
        K.clear(ui.bar);
        newFlowButton();
        listToggle();

        // The address no longer names a flow, so a reload does not ask for
        // one that is gone.
        try {
            window.history.replaceState(null, '', window.location.pathname);
        } catch (error) {
            // An address that cannot be changed changes nothing else.
        }

        state.section = '';
        ui.scheduled.classList.add('d-none');
        ui.problemBox.classList.add('d-none');
        ui.app.classList.add('wsf-nothing');
        K.clear(ui.side);
        K.clear(ui.boxes);
        drawEdges();
        K.clear(ui.palette);
        ui.empty.textContent = FL.build ? t('fl_none_build') : t('fl_none_run');
        ui.empty.classList.remove('d-none');
        ui.stage.classList.remove('d-none');
        ui.outlineBox.classList.add('d-none');
        refocus();
    }

    // ── Opening, creating ──────────────────────────────────────────────

    // Before another flow opens: what is still unsaved is saved. Changes
    // that cannot be saved (a save that failed, a draft changed on another
    // screen) are left behind only when the person says so.
    function leaveFlow() {
        return flush().then(function () {
            if (!state.flow || !drafting() || !(state.dirty || state.saveError || state.conflict)) {
                return true;
            }

            return K.ask(t('fl_leave_ask'), t('fl_leave'), true);
        });
    }

    // The Scheduled actions section: the scheduled screen's list, cards and
    // form (scheduledList() in workspace.js), New in the toolbar's first slot.
    function showScheduled() {
        leaveFlow().then(function (go) {
            if (!go) {
                refocus();
                return;
            }

            window.clearTimeout(state.saveTimer);
            showNone();
            state.section = 'scheduled';

            try {
                window.history.replaceState(null, '', window.location.pathname + '?view=scheduled');
            } catch (error) {
                // An address that cannot be changed leaves the section open all the same.
            }

            K.clear(ui.bar);
            K.clear(ui.scheduled);
            ui.empty.classList.add('d-none');
            ui.stage.classList.add('d-none');
            ui.scheduled.classList.remove('d-none');
            ui.scheduled.appendChild(el('h2', 'h5 mb-3', t('sa_title')));
            K.scheduledList(ui.scheduled, ui.bar);
            listToggle();
            drawList();
            refocus();
        }).catch(K.fail);
    }

    // ── A scheduled action on the canvas ───────────────────────────────

    // The boxes of a scheduled action (ws_scheduled_graph()) in the colours
    // of the flow boxes that will keep them: its "when" a start, its step a
    // task.
    var ACTION_KINDS = {
        when: 'wsf-node-manual',
        condition: 'wsf-node-sa-condition',
        step: 'wsf-node-task',
        follow: 'wsf-node-sa-follow'
    };

    // A staff member's scheduled action drawn read-only in the Scheduled
    // actions section, in place of its list: its "when", conditions, step
    // and follow-ups as boxes with the arrows between them, on a canvas
    // that scrolls; the actions that start it, those it starts and its
    // retry as chips above. Nothing is changed here: Edit (options.edit,
    // the card's form) changes it, Back shows the list again. The boxes
    // are a list in the order of the arrows for the keyboard and screen
    // readers, each with all of its text.
    function showAction(item, graph, options) {
        if (state.section !== 'scheduled') {
            return;
        }

        var nodes = (graph && graph.nodes) || [];
        var chips = (graph && graph.chips) || {};
        var pad = 24;
        var width = Math.max.apply(null, [0].concat(nodes.map(function (node) { return node.x + BOX_W; }))) + 2 * pad;
        var height = Math.max.apply(null, [0].concat(nodes.map(function (node) { return node.y + BOX_H; }))) + 2 * pad;
        var byId = {};

        options = options || {};
        K.clear(ui.bar);
        K.clear(ui.scheduled);

        var back = K.button('btn btn-sm btn-ghost', t('sa_canvas_back'), 'bi-arrow-left');
        back.setAttribute('data-refocus', 'sa-back');
        back.addEventListener('click', function () {
            state.refocus = 'scheduled';
            showScheduled();
        });
        ui.bar.appendChild(back);
        listToggle();
        ui.bar.appendChild(el('span', 'fw-semibold wsf-name-text', item.name));
        ui.bar.appendChild(el('span', 'ws-sa-state ws-sa-state-' + item.status, item.status_label));
        ui.bar.appendChild(el('span', 'pg-toolbar-grow'));

        if (options.edit) {
            var edit = K.button('btn btn-sm btn-outline-secondary', t('edit'), 'bi-pencil');
            edit.addEventListener('click', options.edit);
            ui.bar.appendChild(edit);
        }

        ui.scheduled.appendChild(el('h2', 'h5 mb-1', item.name));
        ui.scheduled.appendChild(el('p', 'small text-body-secondary', t('sa_canvas_help')));

        var chipRow = el('div', 'ws-sa-graph-chips');
        var chip = function (iconName, text) {
            var part = el('span', 'badge rounded-pill text-bg-light border ws-sa-graph-chip');

            part.appendChild(K.icon(iconName, 'me-1'));
            part.appendChild(document.createTextNode(text));
            chipRow.appendChild(part);
        };
        var names = function (list) {
            return list.map(function (other) { return other.name; }).join(', ');
        };

        if ((chips.starters || []).length) {
            chip('bi-box-arrow-in-right', t('sa_started_by') + ': ' + names(chips.starters));
        }

        if ((chips.started || []).length) {
            chip('bi-box-arrow-right', t('sa_canvas_starts') + ': ' + names(chips.started));
        }

        if ((chips.self_retry || []).length) {
            var outcomes = chips.self_retry.filter(function (label) { return label; });

            chip('bi-arrow-counterclockwise', outcomes.length ? t('sa_canvas_retry_on', outcomes.join(', ')) : t('sa_canvas_retry'));
        }

        if (chipRow.children.length) {
            ui.scheduled.appendChild(chipRow);
        }

        var stage = el('div', 'wsf-stage ws-sa-graph');
        var layer = el('div', 'ws-sa-graph-layer');
        var svg = svgEl('svg', 'wsf-edges');
        var boxes = el('div');

        stage.style.height = Math.min(height + 2, 560) + 'px';
        layer.style.width = width + 'px';
        layer.style.height = height + 'px';
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('width', String(width));
        svg.setAttribute('height', String(height));
        svg.innerHTML = '<defs><marker id="ws-sa-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path class="wsf-arrowhead" d="M0 0L10 5L0 10z"/></marker></defs>';
        boxes.setAttribute('role', 'list');
        boxes.setAttribute('aria-label', t('sa_canvas_label'));

        nodes.forEach(function (node) {
            var box = el('div', 'wsf-node ' + (ACTION_KINDS[node.kind] || ''));
            var head = el('div', 'wsf-node-head');
            var text = node.kind_label + ': ' + node.label + (node.meta ? '. ' + node.meta : '');

            byId[node.id] = node;
            box.tabIndex = 0;
            box.setAttribute('role', 'listitem');
            box.setAttribute('aria-label', text);
            box.title = node.label + (node.meta ? '\n' + node.meta : '');

            if (node.kind !== 'when') {
                box.appendChild(port('in'));
            }

            head.appendChild(K.icon(node.icon));
            head.appendChild(el('span', 'wsf-node-kind', node.kind_label));
            box.appendChild(head);
            box.appendChild(el('div', 'wsf-node-title', node.label));
            box.appendChild(el('div', 'wsf-node-meta', node.meta));

            if (node.kind !== 'follow') {
                box.appendChild(port('out'));
            }

            place(box, { x: node.x + pad, y: node.y + pad });
            boxes.appendChild(box);
        });

        ((graph && graph.edges) || []).forEach(function (edge) {
            var from = byId[edge.from];
            var to = byId[edge.to];

            if (!from || !to) {
                return;
            }

            var path = curve(from.x + pad + BOX_W, from.y + pad + BOX_H / 2, to.x + pad, to.y + pad + BOX_H / 2);
            var line = svgEl('path', 'wsf-edge-line');

            line.setAttribute('d', path.d);
            line.style.markerEnd = 'url(#ws-sa-arrow)';
            svg.appendChild(line);

            // A follow-up's arrow says on which outcome it is taken, beside
            // the box it leads to.
            if (edge.label) {
                var label = svgEl('text', 'wsf-edge-label');

                label.setAttribute('x', String(to.x + pad - 8));
                label.setAttribute('y', String(to.y + pad + BOX_H / 2 - 8));
                label.setAttribute('text-anchor', 'end');
                label.textContent = edge.label;
                svg.appendChild(label);
            }
        });

        layer.appendChild(svg);
        layer.appendChild(boxes);
        stage.appendChild(layer);
        ui.scheduled.appendChild(stage);
        back.focus();
    }

    // fallback: the flow opened instead when this one cannot be.
    function open(id, fallback) {
        return leaveFlow().then(function (go) {
            if (!go) {
                refocus();
                return false;
            }

            return K.api('ws_flow_get', { flow_id: id }).then(function (data) {
                show(data.flow);

                return true;
            });
        }).catch(function (error) {
            state.refocus = '';
            K.fail(error);

            if (!state.flow && fallback && (fallback !== id)) {
                return open(fallback);
            }

            if (!state.flow) {
                showNone();
            }

            return false;
        });
    }

    function show(flow) {
        var same = !!(state.flow && (state.flow.id === flow.id));

        window.clearTimeout(state.saveTimer);
        endRun();
        state.openSeq++;

        // The runs panel stays open while the same flow is shown again
        // (after Publish or Pause); another flow starts without it.
        if (!same) {
            state.panel = '';
            state.runs = { flow: flow.id, items: [], next: null, loading: false, error: '' };
        }

        state.flow = flow;
        state.edit = (flow.access === 'edit');

        // Somebody who only reaches the flow reads its live graph, or the
        // draft, read-only, of a flow never published.
        state.graph = graphOf(state.edit ? flow.draft : (flow.graph || flow.draft));
        state.problems = flow.problems || [];
        state.selected = null;
        state.dirty = false;
        state.saveError = '';
        state.conflict = '';
        state.section = '';
        ui.scheduled.classList.add('d-none');
        ui.app.classList.remove('wsf-nothing');
        ui.app.classList.toggle('wsf-readonly', !state.edit);
        ui.conflictBox.classList.add('d-none');

        try {
            window.history.replaceState(null, '', window.location.pathname + '?flow=' + flow.id);
        } catch (error) {
            // An address that cannot be changed leaves the flow open all the same.
        }

        listEntry(flow);
        drawBar();
        drawPalette();
        drawAll();
        fit();
    }

    function createFlow() {
        leaveFlow().then(function (go) {
            if (!go) {
                return null;
            }

            return K.api('ws_flow_create', { name: t('fl_untitled'), graph: graphOf(null) }).then(function (data) {
                show(data.flow);

                if (ui.name) {
                    ui.name.focus();
                    ui.name.select();
                }
            });
        }).catch(K.fail);
    }

    // ── The toolbar ────────────────────────────────────────────────────

    // "New flow", what the screen is for, in the toolbar's first slot.
    function newFlowButton() {
        if (!FL.build) {
            return;
        }

        var create = K.button('btn btn-sm btn-primary rounded-pill px-3', t('fl_new'), 'bi-plus-lg');

        create.setAttribute('data-refocus', 'new');
        create.addEventListener('click', createFlow);
        ui.bar.appendChild(create);
    }

    function listToggle() {
        var toggle = K.button('btn btn-sm btn-ghost d-md-none', t('fl_nav'), 'bi-list-ul');

        toggle.setAttribute('aria-expanded', ui.app.classList.contains('wsf-show-list') ? 'true' : 'false');
        toggle.addEventListener('click', function () {
            var shown = ui.app.classList.toggle('wsf-show-list');
            toggle.setAttribute('aria-expanded', shown ? 'true' : 'false');
        });
        ui.bar.appendChild(toggle);
    }

    // A flow that may be run now: live, published, and the person's to edit
    // or to run (ws_flow_run_start_check() decides the rest).
    function runnable() {
        var flow = state.flow;

        return !!(flow && (flow.status === 'live') && (flow.version > 0) && ((flow.access === 'edit') || (flow.access === 'run')));
    }

    // Run starts the live version; primary where "New flow" is not shown.
    function runButton(primary) {
        ui.runButton = null;

        if (!runnable()) {
            return;
        }

        var run = K.button(primary ? 'btn btn-sm btn-primary rounded-pill px-3' : 'btn btn-sm btn-outline-secondary', t('fl_run'), 'bi-play-fill');

        run.title = t('fl_run_help');
        run.setAttribute('data-refocus', 'run');
        run.disabled = (state.starting === 'send');
        run.addEventListener('click', startRun);
        ui.runButton = run;
        ui.bar.appendChild(run);
    }

    // The runs, or one of them, shown in the side panel. A box or an arrow
    // selected meanwhile takes the panel over (the outline aside) while
    // state.panel stays 'runs', so the list is back once nothing is selected.
    function runsShown() {
        return !!(state.run || ((state.panel === 'runs') && (!state.selected || state.outline)));
    }

    // "Flow runs" says by being pressed what the side panel shows; drawn
    // again with the side panel, which a selection redraws alone.
    function markRunsButton() {
        var runs = ui.bar ? ui.bar.querySelector('[data-refocus="runs"]') : null;

        if (runs) {
            runs.classList.toggle('active', runsShown());
            runs.setAttribute('aria-pressed', runsShown() ? 'true' : 'false');
        }
    }

    // "Flow runs": the runs in the side panel, pressed while they or one of
    // them is shown. A flow never published has none.
    function runsButton() {
        if (!state.flow || !(state.flow.version > 0)) {
            return;
        }

        var shown = runsShown();
        var runs = K.button('btn btn-sm btn-ghost' + (shown ? ' active' : ''), t('fl_runs'), 'bi-clock-history');

        runs.setAttribute('aria-pressed', shown ? 'true' : 'false');
        runs.setAttribute('aria-controls', ui.side.id);
        runs.setAttribute('data-refocus', 'runs');
        runs.addEventListener('click', toggleRuns);
        ui.bar.appendChild(runs);
    }

    function drawBar() {
        var flow = state.flow;

        K.clear(ui.bar);
        newFlowButton();

        // Without "New flow" (somebody who runs flows, not builds them) Run
        // is what the screen is for: the toolbar's one primary button.
        if (!FL.build) {
            runButton(true);
        }

        listToggle();

        if (state.edit) {
            ui.name = el('input', 'form-control form-control-sm wsf-name');
            ui.name.type = 'text';
            ui.name.maxLength = 160;
            ui.name.value = flow.name;
            ui.name.setAttribute('aria-label', t('fl_name'));
            ui.name.setAttribute('data-refocus', 'name');
            ui.name.addEventListener('input', function () {
                if (ui.name.value.trim() !== '') {
                    flow.name = ui.name.value.trim();
                    changed(false);
                }
            });
            ui.bar.appendChild(ui.name);
        } else {
            var nameText = el('span', 'fw-semibold wsf-name-text', flow.name);

            ui.name = null;
            nameText.tabIndex = -1;
            nameText.setAttribute('data-refocus', 'name');
            ui.bar.appendChild(nameText);
        }

        // Publish and Pause redraw the bar; the focus then goes to the
        // status they changed.
        ui.status = statusBadge(flow, false);
        ui.status.tabIndex = -1;
        ui.status.setAttribute('data-refocus', 'status');
        ui.bar.appendChild(ui.status);

        ui.unpublished = el('span', 'small text-body-secondary wsf-unpublished d-none', t('fl_unpublished'));
        ui.bar.appendChild(ui.unpublished);
        ui.save = el('span', 'small wsf-save');
        ui.save.setAttribute('role', 'status');
        ui.bar.appendChild(ui.save);
        ui.bar.appendChild(el('span', 'pg-toolbar-grow'));

        var outline = K.button('btn btn-sm btn-ghost' + (state.outline ? ' active' : ''), t('fl_outline'), 'bi-list-nested');
        outline.setAttribute('aria-pressed', state.outline ? 'true' : 'false');
        outline.setAttribute('data-refocus', 'outline');
        outline.addEventListener('click', function () {
            state.outline = !state.outline;
            state.refocus = 'outline';
            drawBar();
            drawAll();

            if (!state.outline) {
                fit();
            }
        });
        ui.bar.appendChild(outline);
        runsButton();

        if (FL.build) {
            runButton(false);
        }

        // A run shown says so in its own bar above the canvas.
        if (!state.edit) {
            if (!state.run) {
                ui.bar.appendChild(el('span', 'small text-body-secondary', t('fl_read_only')));
            }

            moreMenu();
            return;
        }

        ui.problemButton = K.button('btn btn-sm', '', '');
        ui.problemButton.setAttribute('aria-controls', 'wsf-problems');
        ui.problemButton.setAttribute('aria-expanded', ui.problemBox.classList.contains('d-none') ? 'false' : 'true');
        ui.problemButton.addEventListener('click', function () {
            var shown = ui.problemBox.classList.toggle('d-none') === false;

            ui.problemButton.setAttribute('aria-expanded', shown ? 'true' : 'false');

            if (shown && ui.problemBox.querySelector('button')) {
                ui.problemBox.querySelector('button').focus();
            }
        });
        ui.bar.appendChild(ui.problemButton);

        // Only the owner publishes: a published flow runs with the owner's
        // rights, so a colleague shared at edit changes the draft and the
        // server refuses them a publish (ws_flow_publish_check()).
        if (flow.manage) {
            ui.publish = K.button('btn btn-sm btn-outline-secondary', t('fl_publish'), 'bi-send');
            ui.publish.addEventListener('click', publish);
            ui.bar.appendChild(ui.publish);
        } else {
            ui.publish = null;
            ui.bar.appendChild(el('span', 'small text-body-secondary wsf-publish-owner', t('fl_publish_owner')));
        }

        if (flow.status === 'live') {
            var pause = K.button('btn btn-sm btn-outline-secondary', t('fl_pause'), 'bi-pause-circle');
            pause.addEventListener('click', function () { act('ws_flow_pause', pause, t('fl_paused')); });
            ui.bar.appendChild(pause);
        }

        moreMenu();
        drawState();
    }

    // "⋯": the flow's shares for everybody who reaches it (to change for its
    // owner, to read for the others), and deleting it for its owner alone.
    function moreMenu() {
        var flow = state.flow;
        var more = el('div', 'dropdown');
        var moreButton = K.button('btn btn-sm btn-ghost', '', 'bi-three-dots', t('fl_more'));
        var menu = el('ul', 'dropdown-menu dropdown-menu-end');
        var share = K.button('dropdown-item link-body-emphasis', flow.manage ? t('fl_share') : t('fl_shared_with'), flow.manage ? 'bi-share' : 'bi-people');

        moreButton.setAttribute('data-bs-toggle', 'dropdown');
        moreButton.setAttribute('aria-expanded', 'false');
        moreButton.setAttribute('data-refocus', 'more');
        share.setAttribute('data-wsf-share', '');
        share.addEventListener('click', openShares);
        menu.appendChild(el('li')).appendChild(share);

        if (flow.manage && state.edit) {
            var bin = K.button('dropdown-item link-body-emphasis', t('fl_bin'), 'bi-trash');

            bin.addEventListener('click', binFlow);
            menu.appendChild(el('li')).appendChild(bin);
        }

        more.appendChild(moreButton);
        more.appendChild(menu);
        ui.bar.appendChild(more);
    }

    // The save state, the problem count and whether Publish may be pressed.
    function drawState() {
        if (!state.edit || !ui.save) {
            return;
        }

        var count = state.problems.length;
        var text = t('fl_saved');

        // A live or paused flow whose draft holds what its version does not.
        ui.unpublished.classList.toggle('d-none', !((state.flow.status !== 'draft') && (state.flow.changed || state.dirty)));

        if (state.saving) {
            text = t('fl_saving');
        } else if (state.dirty || state.saveError || state.conflict) {
            text = t('fl_unsaved');
        }

        ui.save.textContent = text;
        ui.save.title = state.saveError || '';
        ui.save.classList.toggle('text-danger', !!(state.saveError || state.conflict));
        ui.save.classList.toggle('text-body-secondary', !(state.saveError || state.conflict));

        if (state.saveError && !state.saving) {
            var retry = K.button('btn btn-sm btn-link p-0 ms-1 align-baseline', t('fl_retry'));
            retry.addEventListener('click', function () {
                state.saveError = '';
                state.dirty = true;
                save();
            });
            ui.save.appendChild(retry);
        }

        K.clear(ui.problemButton);
        ui.problemButton.className = 'btn btn-sm ' + (count ? 'btn-outline-danger' : 'btn-ghost');
        ui.problemButton.appendChild(K.icon(count ? 'bi-exclamation-triangle' : 'bi-check2-circle', 'me-1'));
        ui.problemButton.appendChild(document.createTextNode(count ? t('fl_problems', count) : t('fl_no_problems')));
        ui.problemButton.disabled = !count;

        if (!ui.publish) {
            return;
        }

        var allowed = !count && !state.conflict && ((state.flow.status !== 'live') || state.flow.changed || state.dirty);

        ui.publish.disabled = !allowed;
        ui.publish.title = count ? t('fl_publish_blocked') : '';
    }

    // ── Drawing ────────────────────────────────────────────────────────

    function drawAll() {
        ui.stage.classList.toggle('d-none', state.outline);
        ui.outlineBox.classList.toggle('d-none', !state.outline);
        ui.app.classList.toggle('wsf-outlined', state.outline);
        drawCanvas();

        // The side panel before the outline: a focus asked for in the side
        // (the title of a run shown) is found there before the outline's
        // drawing gives it up.
        drawSettings();
        drawOutline();
        drawProblems();
        drawState();
    }

    function drawCanvas() {
        K.clear(ui.boxes);

        orderedNodes().forEach(function (node) {
            ui.boxes.appendChild(boxNode(node));
        });

        drawEdges();
        ui.empty.textContent = state.edit ? t('fl_empty_canvas') : '';
        ui.empty.classList.toggle('d-none', state.graph.nodes.length > 0);
        drawPalette();
        markSelected();
        markProblems();
    }

    function port(side) {
        var dot = el('span', 'wsf-port wsf-port-' + side);

        dot.setAttribute('aria-hidden', 'true');

        return dot;
    }

    function boxNode(node) {
        var kind = kindOf(node);
        var box = el('div', 'wsf-node wsf-node-' + (KINDS[node.kind] ? node.kind : 'unknown'));
        var head = el('div', 'wsf-node-head');

        box.tabIndex = 0;
        box.setAttribute('role', 'group');
        box.setAttribute('aria-roledescription', t('fl_box'));
        box.setAttribute('data-node', node.id);
        box.setAttribute('data-refocus', 'box-' + node.id);

        if (kind.into) {
            box.appendChild(port('in'));
        }

        head.appendChild(K.icon(kind.icon));
        head.appendChild(el('span', 'wsf-node-kind', t(kind.name)));
        head.appendChild(K.icon('bi-exclamation-triangle-fill', 'wsf-node-warn'));
        box.appendChild(head);
        box.appendChild(el('div', 'wsf-node-title'));
        box.appendChild(el('div', 'wsf-node-meta'));

        if (kind.out.length) {
            var out = port('out');

            if (state.edit) {
                out.title = t('fl_port_out');
            }

            box.appendChild(out);
        }

        fillBox(box, node);
        place(box, node);

        return box;
    }

    // A task needs a title, so an empty one shows as missing; a start or an
    // end without a name of its own shows what it does in its place.
    function fillBox(box, node) {
        var title = titleOf(node);
        var meta = metaOf(node);
        var missing = !title && ((node.kind === 'task') || (node.kind === 'task_open') || !KINDS[node.kind]);
        var titleNode = box.querySelector('.wsf-node-title');

        titleNode.textContent = title || (missing ? t('fl_untitled_box') : meta);
        titleNode.classList.toggle('wsf-placeholder', missing);
        box.querySelector('.wsf-node-meta').textContent = (title || missing) ? meta : '';
        box.setAttribute('aria-label', t(kindOf(node).name) + ': ' + (title || (missing ? t('fl_untitled_box') : '')) + '. ' + meta);
        paintRun(box, node);
    }

    // A box as the run shown left it: the tone, mark and word of its state,
    // its result in the line under the title, and in that line the link to
    // the task a waiting box waits for. Without a run, nothing of it.
    function paintRun(box, node) {
        var head = box.querySelector('.wsf-node-head');
        var meta = box.querySelector('.wsf-node-meta');
        var badge = head.querySelector('.wsf-run-badge');

        Object.keys(STEP_STATES).forEach(function (key) {
            box.classList.remove('wsf-run-' + key);
        });

        if (badge) {
            badge.remove();
        }

        if (!state.run) {
            return;
        }

        var step = stepOf(node.id);
        var status = step ? step.status : 'none';
        var result = (step && step.result) || '';

        box.classList.add('wsf-run-' + (STEP_STATES[status] ? status : 'none'));
        head.appendChild(runBadge(STEP_STATES, status));

        if (step && (step.status === 'waiting') && step.task_id) {
            K.clear(meta);
            meta.appendChild(taskLink(step, node, 'wsf-run-task', 'run-task-' + node.id));
        } else if (result) {
            meta.textContent = result;
        }

        box.title = result;
        box.setAttribute('aria-label', box.getAttribute('aria-label') + ' ' + stepWord(status) + '.' + (result ? ' ' + result : ''));
    }

    function place(box, item) {
        box.style.left = item.x + 'px';
        box.style.top = item.y + 'px';
    }

    function curve(x1, y1, x2, y2) {
        var dx = Math.max(48, Math.abs(x2 - x1) / 2);

        return { d: 'M' + x1 + ' ' + y1 + ' C' + (x1 + dx) + ' ' + y1 + ' ' + (x2 - dx) + ' ' + y2 + ' ' + x2 + ' ' + y2, mx: (x1 + x2) / 2, my: (y1 + y2) / 2 };
    }

    function drawEdges() {
        Array.prototype.slice.call(ui.svg.querySelectorAll('.wsf-edge')).forEach(function (node) {
            node.remove();
        });

        (state.graph ? state.graph.edges : []).forEach(function (edge, index) {
            var from = nodeById(edge.from);
            var to = nodeById(edge.to);

            if (!from || !to) {
                return;
            }

            var path = curve(from.x + BOX_W, from.y + BOX_H / 2, to.x, to.y + BOX_H / 2);
            var group = svgEl('g', 'wsf-edge');
            var line = svgEl('path', 'wsf-edge-line');
            var hit = svgEl('path', 'wsf-edge-hit');

            group.setAttribute('data-edge', String(index));

            // In a run, an arrow its box ended on was taken.
            var step = state.run ? stepOf(edge.from) : null;

            if (step && (step.status === 'done') && (step.outcome === edge.on)) {
                group.classList.add('wsf-edge-taken');
            }

            line.setAttribute('d', path.d);
            hit.setAttribute('d', path.d);
            group.appendChild(line);
            group.appendChild(hit);

            // An arrow taken when a task is cancelled, or a step failed, says
            // so on itself.
            if ((edge.on === 'cancelled') || (edge.on === 'failed')) {
                var label = svgEl('text', 'wsf-edge-label');

                label.setAttribute('x', String(path.mx));
                label.setAttribute('y', String(path.my - 6));
                label.setAttribute('text-anchor', 'middle');
                label.textContent = t(OUTCOMES[edge.on]);
                group.appendChild(label);
            }

            ui.svg.appendChild(group);
        });

        markSelected();
        markProblems();
    }

    // The buttons that add a box; on the canvas they may also be dragged to
    // where the box should go.
    function paletteButtons(className, draggable) {
        var started = state.graph.nodes.some(function (node) { return node.kind === 'manual'; });

        return PALETTE.map(function (item) {
            var add = K.button(className, t(item[2]), item[1]);

            // One box starts a flow; a second would be a problem to fix.
            if ((item[0] === 'manual') && started) {
                add.disabled = true;
                add.title = t('fl_one_start');
            }

            add.addEventListener('click', function () { addItem(item[0]); });

            // The outline's buttons take the focus when the last box there
            // is removed.
            if (!draggable) {
                add.setAttribute('data-refocus', 'outline-add-' + item[0]);
            }

            if (draggable) {
                add.draggable = true;
                add.addEventListener('dragstart', function (event) {
                    event.dataTransfer.setData('text/plain', 'wsf:' + item[0]);
                    event.dataTransfer.effectAllowed = 'copy';
                });
            }

            return add;
        });
    }

    function drawPalette() {
        K.clear(ui.palette);
        ui.palette.classList.toggle('d-none', !state.edit || !state.flow);

        if (state.edit) {
            paletteButtons('btn btn-sm btn-ghost wsf-palette-item', true).forEach(function (add) {
                ui.palette.appendChild(add);
            });
        }
    }

    function markSelected() {
        var selected = state.selected;

        Array.prototype.forEach.call(ui.layer.querySelectorAll('.wsf-selected'), function (node) {
            node.classList.remove('wsf-selected');
            node.removeAttribute('aria-current');
        });

        if (!selected) {
            return;
        }

        var target = ui.layer.querySelector('[data-' + selected.type + '="' + String(selected.id).replace(/"/g, '') + '"]');

        if (target) {
            target.classList.add('wsf-selected');
            target.setAttribute('aria-current', 'true');
        }
    }

    // The draft's problems on its boxes; a run's boxes are those of its
    // version, so none is marked there.
    function markProblems() {
        var byNode = {};
        var byEdge = {};

        (state.run ? [] : state.problems).forEach(function (problem) {
            if (problem.node) {
                (byNode[problem.node] = byNode[problem.node] || []).push(problem.text);
            }

            if ((problem.edge !== null) && (problem.edge !== undefined)) {
                byEdge[problem.edge] = true;
            }
        });

        Array.prototype.forEach.call(ui.root.querySelectorAll('[data-node]'), function (box) {
            var texts = byNode[box.getAttribute('data-node')];

            box.classList.toggle('wsf-bad', !!texts);

            if (!state.run) {
                box.title = texts ? texts.join('\n') : '';
            }
        });

        Array.prototype.forEach.call(ui.svg.querySelectorAll('.wsf-edge'), function (group) {
            group.classList.toggle('wsf-bad', !!byEdge[group.getAttribute('data-edge')]);
        });
    }

    function drawProblems() {
        K.clear(ui.problemBox);

        if (!state.edit || !state.problems.length) {
            ui.problemBox.classList.add('d-none');
            return;
        }

        ui.problemBox.appendChild(el('div', 'wsf-side-title', t('fl_problems', state.problems.length)));

        var list = el('ul', 'list-unstyled mb-0');

        state.problems.forEach(function (problem) {
            var row = K.button('wsf-problem', problem.text, 'bi-exclamation-triangle');

            if (problem.node && nodeById(problem.node)) {
                row.addEventListener('click', function () { select({ type: 'node', id: problem.node }, { reveal: true, focus: true }); });
            } else if ((problem.edge !== null) && (problem.edge !== undefined) && state.graph.edges[problem.edge]) {
                row.addEventListener('click', function () { select({ type: 'edge', id: problem.edge }, { reveal: true }); });
            } else {
                row.disabled = true;
            }

            list.appendChild(el('li')).appendChild(row);
        });

        ui.problemBox.appendChild(list);
    }

    // ── Settings ───────────────────────────────────────────────────────

    function field(label, control, help) {
        var wrap = el('div', 'mb-3');
        var id = 'wsf-f' + (++uid);
        var caption = el('label', 'form-label small fw-semibold mb-1', label);

        caption.htmlFor = id;
        control.id = id;
        control.disabled = control.disabled || !state.edit;
        wrap.appendChild(caption);
        wrap.appendChild(control);

        if (help) {
            var note = el('div', 'form-text', help);

            note.id = id + '-help';
            control.setAttribute('aria-describedby', note.id);
            wrap.appendChild(note);
        }

        return wrap;
    }

    function textInput(value, max, onInput, multi) {
        var input = el(multi ? 'textarea' : 'input', 'form-control form-control-sm');

        if (multi) {
            input.rows = 3;
        } else {
            input.type = 'text';
        }

        input.maxLength = max;
        input.value = value || '';
        input.addEventListener('input', function () { onInput(input.value); });

        return input;
    }

    function drawSettings() {
        var selected = state.selected;

        K.clear(ui.side);

        if (!state.flow) {
            return;
        }

        if (state.run) {
            runSide();
        } else if ((state.panel === 'runs') && (!selected || state.outline)) {
            runsSide();
        } else if (selected && (selected.type === 'node') && nodeById(selected.id) && !state.outline) {
            ui.side.appendChild(el('div', 'wsf-side-title', t(kindOf(nodeById(selected.id)).name)));
            ui.side.appendChild(nodeForm(nodeById(selected.id)));
        } else if (selected && (selected.type === 'edge') && state.graph.edges[selected.id] && !state.outline) {
            ui.side.appendChild(el('div', 'wsf-side-title', t('fl_arrow')));
            ui.side.appendChild(edgeForm(selected.id));
        } else {
            ui.side.appendChild(el('div', 'wsf-side-title', t('fl_flow_settings')));
            ui.side.appendChild(flowForm());
        }

        markRunsButton();
        refocus();
    }

    // A control drawn again takes the focus back: state.refocus names it by
    // its data-refocus, or names several, space-separated, tried in turn
    // (one hidden or disabled is passed over).
    function refocus() {
        if (!state.refocus) {
            return;
        }

        var keys = state.refocus.split(' ');

        state.refocus = '';

        for (var i = 0; i < keys.length; i++) {
            var target = keys[i] ? ui.root.querySelector('[data-refocus="' + keys[i] + '"]') : null;

            if (target && !target.disabled && target.getClientRects().length) {
                target.focus();
                return;
            }
        }
    }

    function flowForm() {
        var box = el('div');

        // "Only you" is the owner's to read; somebody the flow is shared with
        // reads that it has no home.
        var channels = [[0, state.flow.manage ? t('fl_home_none') : t('fl_home_none_other')]];

        (BOOT.channels || []).forEach(function (channel) {
            if (((channel.kind === 'public') || (channel.kind === 'private')) && !channel.archived) {
                channels.push([channel.id, '#' + channel.name]);
            }
        });

        // A home the person does not read (a flow shared with them) is named
        // as such rather than shown as no home at all.
        if (state.flow.channel_id && !channels.some(function (option) { return option[0] === state.flow.channel_id; })) {
            channels.push([state.flow.channel_id, t('fl_share_hidden')]);
        }

        var home = K.select(channels, state.flow.channel_id || 0);

        // The home decides who may run the flow: its owner moves it, a person
        // the flow is shared with at edit does not.
        home.disabled = !state.flow.manage;
        home.addEventListener('change', function () {
            state.flow.channel_id = parseInt(home.value, 10) || 0;
            changed(false);
        });
        box.appendChild(field(t('fl_home'), home, (state.edit && !state.flow.manage) ? t('fl_home_owner_only') : t('fl_home_help')));

        var keys = el('details', 'small wsf-keys');
        var list = el('ul', 'mb-0 ps-3 text-body-secondary');

        keys.appendChild(el('summary', 'fw-semibold', t('fl_keys')));
        ['tab', 'arrows', 'connect', 'delete', 'enter', 'zoom', 'pan', 'escape'].forEach(function (key) {
            list.appendChild(el('li', '', t('fl_key_' + key)));
        });
        keys.appendChild(list);
        box.appendChild(el('p', 'small text-body-secondary', state.edit ? t('fl_flow_help') : t('fl_read_only')));
        box.appendChild(keys);

        return box;
    }

    function nodeForm(node) {
        var box = el('div', 'wsf-form');
        var config = node.config;

        var titled = (node.kind === 'task') || (node.kind === 'task_open');
        var title = textInput(titleOf(node), titled ? 200 : 160, function (value) {
            if (titled) {
                config.title = value;
            }

            node.label = value.slice(0, 160);
            refreshNode(node);
        });

        title.setAttribute('data-refocus', 'title-' + node.id);
        box.appendChild(field(t('fl_title'), title));

        if (node.kind === 'manual') {
            var subject = K.select([['', t('fl_subject_none')]].concat((FL.subjects || []).map(function (item) { return [item.key, item.label]; })), config.subject || '');

            subject.addEventListener('change', function () {
                config.subject = subject.value;
                refreshNode(node);
            });
            box.appendChild(field(t('fl_subject'), subject, t('fl_subject_help')));
        }

        if (node.kind === 'task') {
            taskFields(box, node);
        }

        if (node.kind === 'post') {
            postFields(box, node);
        }

        if (node.kind === 'email') {
            emailFields(box, node);
        }

        if (node.kind === 'notify') {
            notifyFields(box, node);
        }

        if (node.kind === 'task_open') {
            taskOpenFields(box, node);
        }

        if ((node.kind === 'webhook') || (node.kind === 'web_check')) {
            urlFields(box, node);
        }

        if (node.kind === 'task_digest') {
            digestFields(box, node);
        }

        if (node.kind === 'report') {
            reportFields(box, node);
        }

        if (node.kind === 'checkin') {
            checkinFields(box, node);
        }

        if (node.kind === 'start_action') {
            startFields(box, node);
        }

        if (node.kind === 'change') {
            changeFields(box, node);
        }

        if (node.kind === 'lead') {
            leadFields(box, node);
        }

        if (node.kind === 'condition') {
            conditionFields(box, node);
        }

        if (node.kind === 'schedule') {
            scheduleFields(box, node);
        }

        if (node.kind === 'event') {
            eventFields(box, node);
        }

        if (node.kind === 'join') {
            channelField(box, node, t('fl_join_channel'), t('fl_join_any'));
        }

        if (state.edit) {
            // Only the owner: the box is looked at with their rights.
            if (state.flow && state.flow.manage) {
                tryField(box, node);
            }

            box.appendChild(connections(node));

            var remove = K.button('btn btn-sm btn-outline-warning', t('fl_remove_box'), 'bi-trash');
            remove.addEventListener('click', function () { removeSelected({ type: 'node', id: node.id }); });
            box.appendChild(remove);
        }

        return box;
    }

    // What the box would do, asked of the server (ws_flow_box_try) without
    // anything being sent or written: the draft is saved first, since the
    // server tries the box as the draft keeps it. The runs to read the
    // {{node.*}} values from are listed when the list is first opened.
    function tryField(box, node) {
        var flow = state.flow;
        var wrap = el('div', 'wsf-try border-top pt-3 mb-3');
        var runs = K.select([['0', t('fl_try_no_run')]], '0');
        var go = K.button('btn btn-sm btn-outline-secondary', t('fl_try'), 'bi-eye');
        var out = el('div', 'wsf-try-out small mt-2');
        var listed = false;

        go.title = t('fl_try_help');
        out.setAttribute('aria-live', 'polite');

        runs.addEventListener('focus', function () {
            if (listed) {
                return;
            }

            listed = true;
            K.api('ws_flow_runs_list', { flow_id: flow.id, before_id: 0 }).then(function (data) {
                (data.runs || []).forEach(function (run) {
                    var option = document.createElement('option');

                    option.value = String(run.id);
                    option.textContent = t('fl_try_run_item', run.id, runWord(run.status));
                    runs.appendChild(option);
                });
            }).catch(function () {
                listed = false;
            });
        });

        go.addEventListener('click', function () {
            go.disabled = true;
            out.textContent = '';

            flush().then(function () {
                return K.api('ws_flow_box_try', { flow_id: flow.id, node_id: node.id, run_id: Number(runs.value) || 0 });
            }).then(function (data) {
                drawTry(out, data.try || {});
            }).catch(function (error) {
                out.appendChild(el('div', 'text-danger', (error && error.message) || t('error_generic')));
            }).then(function () {
                go.disabled = false;
            });
        });

        wrap.appendChild(field(t('fl_try_run'), runs, t('fl_try_help')));
        wrap.appendChild(go);
        wrap.appendChild(out);
        box.appendChild(wrap);
    }

    // The answer of ws_flow_box_try in words: what the box would do, why it
    // would fail, its texts filled in, the values left empty, its next times
    // and the arrow it would leave on.
    function drawTry(out, tried) {
        if (tried.said) {
            out.appendChild(el('div', 'fw-semibold text-break', tried.said));
        }

        if (tried.refusal) {
            out.appendChild(el('div', 'text-danger', t('fl_try_fails', tried.refusal)));
        }

        (tried.texts || []).forEach(function (text) {
            var shown = el('div', 'border rounded p-2 mt-1 text-break', text.text);

            shown.style.whiteSpace = 'pre-wrap';
            out.appendChild(shown);
        });

        if ((tried.empty || []).length) {
            out.appendChild(el('div', 'text-body-secondary mt-1', t('fl_try_empty', tried.empty.join(', '))));
        }

        if ((tried.next || []).length) {
            out.appendChild(el('div', 'mt-1', t('fl_try_next', tried.next.join(' · '))));
        }

        if (tried.outcome && OUTCOMES[tried.outcome]) {
            out.appendChild(el('div', 'text-body-secondary mt-1', t('fl_try_outcome', t(OUTCOMES[tried.outcome]))));
        }
    }

    function taskFields(box, node) {
        var config = node.config;
        var options = [['people', t('fl_assign_people')], ['creator', t('fl_assign_creator')], ['department_lead', t('tpl_assign_lead')], ['none', t('tpl_assign_none')]];

        (BOOT.departments || []).forEach(function (department) {
            options.push(['department:' + department.id, t('tpl_assign_dept', department.name)]);
        });

        var current = (config.assignees && config.assignees.length) ? 'people' : (config.assign || 'none');

        if (!options.some(function (option) { return option[0] === current; })) {
            options.push([current, ruleLabel(current)]);
        }

        var who = K.select(options, current);
        var people = el('div', 'wsf-people mt-2');

        // The team's ticks are built once "The people I pick" is chosen: a
        // box given by a rule does not carry one tick per member in the DOM.
        var showPeople = function () {
            if (!people.firstChild) {
                peopleList(node, people);
            }
        };

        if (current === 'people') {
            showPeople();
        } else {
            people.classList.add('d-none');
        }

        who.addEventListener('change', function () {
            people.classList.toggle('d-none', who.value !== 'people');

            if (who.value === 'people') {
                config.assign = '';
                showPeople();
            } else {
                config.assign = who.value;
                config.assignees = [];
                Array.prototype.forEach.call(people.querySelectorAll('input[type="checkbox"]'), function (check) { check.checked = false; });
            }

            refreshNode(node);
        });

        var whoField = field(t('fl_assign'), who, t('fl_assign_help'));

        whoField.appendChild(people);
        box.appendChild(whoField);

        var due = el('input', 'form-control form-control-sm wsf-number');

        due.type = 'number';
        due.min = '0';
        due.max = '3650';
        due.step = '1';
        due.value = (config.due_in === '' || config.due_in === undefined || config.due_in === null) ? '' : String(config.due_in);
        due.addEventListener('input', function () {
            config.due_in = (due.value === '') ? '' : clamp(parseInt(due.value, 10) || 0, 0, 3650);
            refreshNode(node);
        });
        box.appendChild(field(t('fl_due_in'), due, t('fl_due_help')));

        var priorities = BOOT.priorities || {};
        var priority = K.select(Object.keys(priorities).map(function (key) { return [key, priorities[key]]; }), config.priority || 'normal');

        priority.addEventListener('change', function () {
            config.priority = priority.value;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_priority'), priority));

        box.appendChild(field(t('fl_description'), textInput(config.description, 8000, function (value) {
            config.description = value;
            refreshNode(node);
        }, true), t('fl_description_help')));
    }

    // A post box: the channel the owner writes in and the message. The store
    // checks the owner may write there.
    // The channels a step box may name, the first option for none, and one
    // the person does not read named as such.
    function channelPick(config, none) {
        var channels = [[0, none]];

        (BOOT.channels || []).forEach(function (channel) {
            if (((channel.kind === 'public') || (channel.kind === 'private')) && !channel.archived) {
                channels.push([channel.id, (channel.kind === 'private' ? '🔒 ' : '#') + channel.name]);
            }
        });

        if (config.channel_id && !channels.some(function (option) { return option[0] === config.channel_id; })) {
            channels.push([config.channel_id, t('fl_share_hidden')]);
        }

        return K.select(channels, config.channel_id || 0);
    }

    function postFields(box, node) {
        var config = node.config;
        var pick = channelPick(config, t('fl_post_pick'));

        pick.addEventListener('change', function () {
            config.channel_id = parseInt(pick.value, 10) || 0;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_post_channel'), pick));
        box.appendChild(field(t('fl_post_body'), textInput(config.body, 4000, function (value) {
            config.body = value;
            refreshNode(node);
        }, true), t('fl_values_help')));
    }

    // An e-mail box: addresses, subject and text, sent from the site.
    function emailFields(box, node) {
        var config = node.config;

        box.appendChild(field(t('fl_email_to'), textInput(config.to, 2000, function (value) {
            config.to = value;
            refreshNode(node);
        }), t('fl_email_to_help')));
        box.appendChild(field(t('fl_email_subject'), textInput(config.subject, 250, function (value) {
            config.subject = value;
            refreshNode(node);
        })));
        box.appendChild(field(t('fl_email_body'), textInput(config.body, 20000, function (value) {
            config.body = value;
            refreshNode(node);
        }, true), t('fl_values_help')));
    }

    // A notify box: the people told and the line they read.
    function notifyFields(box, node) {
        var config = node.config;

        box.appendChild(field(t('fl_notify_who'), peopleList(node, el('div', 'wsf-people'), 'user_ids', 50)));
        box.appendChild(field(t('fl_notify_text'), textInput(config.text, 500, function (value) {
            config.text = value;
            refreshNode(node);
        }), t('fl_values_help')));
    }

    // A task opened without waiting: its people, channel, due day, priority
    // and description; the title is the box's.
    function taskOpenFields(box, node) {
        var config = node.config;

        box.appendChild(field(t('fl_assign_people'), peopleList(node, el('div', 'wsf-people'), 'assignees', 20)));

        var pick = channelPick(config, t('fl_task_no_channel'));

        pick.addEventListener('change', function () {
            config.channel_id = parseInt(pick.value, 10) || 0;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_task_channel'), pick));

        var due = el('input', 'form-control form-control-sm wsf-number');

        due.type = 'number';
        due.min = '0';
        due.max = '3650';
        due.value = (config.due_in === '' || config.due_in === undefined || config.due_in === null) ? '' : String(config.due_in);
        due.addEventListener('input', function () {
            config.due_in = (due.value === '') ? '' : clamp(parseInt(due.value, 10) || 0, 0, 3650);
            refreshNode(node);
        });
        box.appendChild(field(t('fl_due_in'), due, t('fl_task_open_due_help')));

        var priorities = BOOT.priorities || {};
        var priority = K.select(Object.keys(priorities).map(function (key) { return [key, priorities[key]]; }), config.priority || 'normal');

        priority.addEventListener('change', function () {
            config.priority = priority.value;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_priority'), priority));
        box.appendChild(field(t('fl_description'), textInput(config.description, 8000, function (value) {
            config.description = value;
            refreshNode(node);
        }, true), t('fl_values_help')));
    }

    // A webhook or a web check: the https address, then what each needs.
    function urlFields(box, node) {
        var config = node.config;

        box.appendChild(field(t('fl_url'), textInput(config.url, 2000, function (value) {
            config.url = value.trim();
            refreshNode(node);
        }), t('fl_url_help')));

        if (node.kind === 'webhook') {
            var format = K.select([['generic', t('fl_hook_generic')], ['text', t('fl_hook_text')], ['discord', t('fl_hook_discord')]], config.format || 'generic');

            format.addEventListener('change', function () {
                config.format = format.value;
                refreshNode(node);
            });
            box.appendChild(field(t('fl_hook_format'), format, t('fl_hook_help')));
            box.appendChild(field(t('fl_post_body'), textInput(config.text, 4000, function (value) {
                config.text = value;
                refreshNode(node);
            }, true), t('fl_values_help')));

            return;
        }

        var mode = K.select([['status', t('fl_check_status')], ['ssl', t('fl_check_ssl')]], config.mode || 'status');
        var more = el('div');
        var draw = function () {
            K.clear(more);

            if (config.mode === 'ssl') {
                more.appendChild(field(t('fl_check_days'), numberInput(config.days, 1, 365, function (value) { config.days = value || 14; })));
                return;
            }

            more.appendChild(field(t('fl_check_contains'), textInput(config.contains, 200, function (value) {
                config.contains = value;
                refreshNode(node);
            })));
            more.appendChild(field(t('fl_check_max_ms'), numberInput(config.max_ms, 0, 60000, function (value) { config.max_ms = value; }), t('fl_check_max_ms_help')));

            var redirect = el('input', 'form-check-input');

            redirect.type = 'checkbox';
            redirect.checked = !!config.redirect;
            redirect.addEventListener('change', function () {
                config.redirect = redirect.checked;
                refreshNode(node);
            });
            more.appendChild(field(t('fl_check_redirect'), redirect));
        };

        mode.addEventListener('change', function () {
            config.mode = mode.value;
            draw();
            refreshNode(node);
        });
        box.appendChild(field(t('fl_check_mode'), mode));
        box.appendChild(more);
        draw();

        function numberInput(value, min, max, set) {
            var input = el('input', 'form-control form-control-sm wsf-number');

            input.type = 'number';
            input.min = String(min);
            input.max = String(max);
            input.value = String(value || '');
            input.addEventListener('input', function () {
                set(clamp(parseInt(input.value, 10) || 0, min, max));
                refreshNode(node);
            });

            return input;
        }
    }

    // A channel picker whose choice goes to config.channel_id.
    function channelField(box, node, label, none) {
        var pick = channelPick(node.config, none);

        pick.addEventListener('change', function () {
            node.config.channel_id = parseInt(pick.value, 10) || 0;
            refreshNode(node);
        });
        box.appendChild(field(label, pick));
    }

    // A tick whose state goes to config[key].
    function tickField(box, node, key, label, onChange) {
        var tick = el('input', 'form-check-input');

        tick.type = 'checkbox';
        tick.checked = !!node.config[key];
        tick.addEventListener('change', function () {
            node.config[key] = tick.checked;
            refreshNode(node);

            if (onChange) {
                onChange();
            }
        });
        box.appendChild(field(label, tick));

        return tick;
    }

    // The task e-mail: each person their own open tasks, or a channel's;
    // the filter, subject and opening lines; what they missed, and the
    // assistant's recap of it.
    function digestFields(box, node) {
        var config = node.config;
        var mode = K.select([['mine', t('fl_digest_mine')], ['channel', t('fl_digest_channel')]], config.mode || 'mine');
        var only = K.select(Object.keys(stepLists().digest).map(function (key) { return [key, stepLists().digest[key]]; }), config.only || 'open');

        mode.addEventListener('change', function () {
            config.mode = mode.value;
            refreshNode(node);
        });
        only.addEventListener('change', function () {
            config.only = only.value;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_digest_mode'), mode));
        channelField(box, node, t('fl_digest_channel_pick'), t('fl_task_no_channel'));
        box.appendChild(field(t('fl_digest_who'), peopleList(node, el('div', 'wsf-people'), 'user_ids', 50), t('fl_digest_who_help')));
        box.appendChild(field(t('fl_digest_only'), only));
        box.appendChild(field(t('fl_email_subject'), textInput(config.subject, 250, function (value) {
            config.subject = value;
            refreshNode(node);
        })));
        box.appendChild(field(t('fl_digest_intro'), textInput(config.intro, 2000, function (value) {
            config.intro = value;
            refreshNode(node);
        }, true), t('fl_values_help')));

        var ai = null;

        tickField(box, node, 'missed', t('fl_digest_missed'), function () {
            if (!config.missed) {
                config.ai = false;
            }

            if (ai) {
                ai.checked = !!config.ai;
                ai.disabled = !state.edit || !config.missed;
            }
        });
        ai = tickField(box, node, 'ai', t('fl_digest_ai'));
        ai.disabled = !state.edit || !config.missed;
    }

    // A summary of counts: which counts, and where it goes.
    function reportFields(box, node) {
        var config = node.config;
        var list = el('div', 'wsf-people-list');
        var chosen = (config.metrics || []).map(function (item) { return item.key; });

        list.setAttribute('role', 'group');
        list.setAttribute('aria-label', t('fl_report_counts'));
        stepLists().metrics.forEach(function (metric) {
            var row = el('label', 'wsf-person');
            var check = el('input', 'form-check-input m-0');

            check.type = 'checkbox';
            check.checked = chosen.indexOf(metric.key) !== -1;
            check.disabled = !state.edit;
            check.addEventListener('change', function () {
                var items = (config.metrics || []).filter(function (item) { return item.key !== metric.key; });

                if (check.checked) {
                    items.push({ key: metric.key, param: 0 });
                }

                config.metrics = items.slice(0, 20);
                refreshNode(node);
            });
            row.appendChild(check);
            row.appendChild(document.createTextNode(metric.label));
            list.appendChild(row);
        });

        box.appendChild(field(t('fl_report_title'), textInput(config.title, 160, function (value) {
            config.title = value;
            refreshNode(node);
        }), t('fl_values_help')));
        box.appendChild(field(t('fl_report_counts'), list));
        channelField(box, node, t('fl_post_channel'), t('fl_task_no_channel'));
        box.appendChild(field(t('fl_email_to'), textInput(config.to, 2000, function (value) {
            config.to = value;
            refreshNode(node);
        }), t('fl_report_where_help')));
    }

    // A check-in: the channel asked and the question.
    function checkinFields(box, node) {
        channelField(box, node, t('fl_post_channel'), t('fl_post_pick'));
        box.appendChild(field(t('fl_checkin_question'), textInput(node.config.question, 500, function (value) {
            node.config.question = value;
            refreshNode(node);
        }), t('fl_checkin_help')));
    }

    // A submitted form's customer brought into a channel: whether a channel
    // it opens is private, and who is put in it.
    function leadFields(box, node) {
        var config = node.config;
        var closed = el('input', 'form-check-input');

        closed.type = 'checkbox';
        closed.checked = !!config.private;
        closed.disabled = !state.edit;
        closed.addEventListener('change', function () {
            config.private = closed.checked;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_lead_private'), closed, t('fl_lead_help')));
        box.appendChild(field(t('fl_lead_people'), peopleList(node, el('div', 'wsf-people'), 'user_ids', 50)));
    }

    // Another scheduled action, started now or some minutes later.
    function startFields(box, node) {
        var config = node.config;
        var options = [[0, t('fl_start_pick')]].concat(stepLists().targets.map(function (item) { return [item.id, item.name]; }));

        if (config.target_id && !options.some(function (option) { return option[0] === config.target_id; })) {
            options.push([config.target_id, '#' + config.target_id]);
        }

        var pick = K.select(options, config.target_id || 0);
        var delay = el('input', 'form-control form-control-sm wsf-number');

        pick.addEventListener('change', function () {
            config.target_id = parseInt(pick.value, 10) || 0;
            refreshNode(node);
        });
        delay.type = 'number';
        delay.min = '0';
        delay.max = '10080';
        delay.value = String(config.delay || 0);
        delay.addEventListener('input', function () {
            config.delay = clamp(parseInt(delay.value, 10) || 0, 0, 10080);
            refreshNode(node);
        });
        box.appendChild(field(t('fl_start_target'), pick));
        box.appendChild(field(t('fl_start_delay'), delay, t('fl_start_delay_help')));
    }

    // A time the flow starts at: its first day and time, how it repeats, the
    // days of a weekly one and its last day, as a scheduled action's "when".
    function scheduleFields(box, node) {
        var config = node.config;
        var date = el('input', 'form-control form-control-sm');
        var clock = el('input', 'form-control form-control-sm');
        var repeats = stepLists().repeats;
        var repeat = K.select(Object.keys(repeats).map(function (key) { return [key, repeats[key]]; }), config.repeat || 'none');

        date.type = 'date';
        date.value = config.date || '';
        date.addEventListener('input', function () {
            config.date = date.value;
            refreshNode(node);
        });
        clock.type = 'time';
        clock.value = config.time || '';
        clock.addEventListener('input', function () {
            config.time = clock.value;
            refreshNode(node);
        });
        repeat.addEventListener('change', function () {
            config.repeat = repeat.value;
            refreshNode(node);
            drawSettings();
        });
        box.appendChild(field(t('fl_when_date'), date, t('fl_when_help')));
        box.appendChild(field(t('fl_when_time'), clock));
        box.appendChild(field(t('fl_when_repeat'), repeat));

        if (['none', 'workdays'].indexOf(config.repeat || 'none') === -1) {
            var every = el('input', 'form-control form-control-sm wsf-number');

            every.type = 'number';
            every.min = '1';
            every.max = '720';
            every.value = String(config.every || 1);
            every.addEventListener('input', function () {
                config.every = clamp(parseInt(every.value, 10) || 1, 1, 720);
                refreshNode(node);
            });
            box.appendChild(field(t('fl_when_every'), every));
        }

        if (config.repeat === 'weekly') {
            var days = el('div', 'wsf-people-list');

            days.setAttribute('role', 'group');
            days.setAttribute('aria-label', t('fl_cond_weekdays'));
            [1, 2, 3, 4, 5, 6, 7].forEach(function (day) {
                var row = el('label', 'wsf-person');
                var check = el('input', 'form-check-input m-0');

                check.type = 'checkbox';
                check.checked = (config.days || []).indexOf(day) !== -1;
                check.disabled = !state.edit;
                check.addEventListener('change', function () {
                    var chosen = (config.days || []).filter(function (item) { return item !== day; });

                    if (check.checked) {
                        chosen.push(day);
                    }

                    config.days = chosen.sort();
                    refreshNode(node);
                });
                row.appendChild(check);
                row.appendChild(document.createTextNode(t(DAYS[day])));
                days.appendChild(row);
            });
            box.appendChild(field(t('fl_cond_weekdays'), days));
        }

        if ((config.repeat || 'none') !== 'none') {
            var until = el('input', 'form-control form-control-sm');

            until.type = 'date';
            until.value = config.until || '';
            until.addEventListener('input', function () {
                config.until = until.value;
                refreshNode(node);
            });
            box.appendChild(field(t('fl_when_until'), until, t('fl_when_until_help')));
        }
    }

    // Something happening on the site: the event, narrowed to a form, the
    // status it moves to, or a channel, as the event rule takes it.
    function eventFields(box, node) {
        var config = node.config;
        var events = stepLists().events || { list: {}, forms: [], statuses: [] };
        var options = [['', t('fl_event_pick')]].concat(Object.keys(events.list).map(function (key) { return [key, events.list[key].label]; }));

        if (config.event && !events.list[config.event]) {
            options.push([config.event, config.event]);
        }

        var pick = K.select(options, config.event || '');
        var info = events.list[config.event] || null;

        pick.addEventListener('change', function () {
            config.event = pick.value;
            config.page_id = 0;
            config.status = '';
            config.channel_id = 0;
            refreshNode(node);
            drawSettings();
        });
        box.appendChild(field(t('fl_event'), pick, t('fl_event_help')));

        if (!info) {
            return;
        }

        if (info.filter === 'page') {
            var form = K.select([[0, t('fl_event_any_form')]].concat(events.forms || []), config.page_id || 0);

            form.addEventListener('change', function () {
                config.page_id = parseInt(form.value, 10) || 0;
                refreshNode(node);
            });
            box.appendChild(field(t('fl_event_form'), form));
        }

        if ((info.filter === 'status') || info.options) {
            var values = (info.filter === 'status') ? (events.statuses || []) : info.options;
            var status = K.select([['', info.any || t('fl_event_any_status')]].concat(values), config.status || '');

            status.addEventListener('change', function () {
                config.status = status.value;
                refreshNode(node);
            });
            box.appendChild(field(info.filter_label || t('fl_event_status'), status));
        }

        if (info.channel) {
            channelField(box, node, t('fl_event_channel'), t('fl_event_any_channel'));
        }
    }

    // The days of the week by number, 1 Monday - 7 Sunday.
    var DAYS = { 1: 'fl_day_mon', 2: 'fl_day_tue', 3: 'fl_day_wed', 4: 'fl_day_thu', 5: 'fl_day_fri', 6: 'fl_day_sat', 7: 'fl_day_sun' };

    // What a condition box's rule is, in a few words on the box.
    function conditionMeta(rule) {
        var lists = stepLists();

        if (rule.type === 'workday') {
            return t('fl_cond_workday');
        }

        if (rule.type === 'weekdays') {
            return (rule.days || []).map(function (day) { return t(DAYS[day]); }).join(', ') || t('fl_cond_none');
        }

        if (rule.type === 'hours') {
            return (rule.from || '?') + ' – ' + (rule.to || '?');
        }

        if (rule.type === 'metric') {
            var metric = lists.metrics.filter(function (item) { return item.key === rule.metric; })[0];

            return metric ? metric.label : (rule.metric || t('fl_cond_none'));
        }

        if (rule.type === 'record') {
            var record = lists.records[rule.record];
            var meta = (record && record.fields[rule.field]) ? record.fields[rule.field].label : rule.field;

            return (record ? record.label : (rule.record || '')) + (rule.id ? ' #' + rule.id : '') + (meta ? ' · ' + meta : '');
        }

        return t('fl_cond_none');
    }

    // A condition: its kind of rule, then that rule's settings, as the
    // scheduled form takes them. Somebody who may not change the flow reads
    // no record number and no value (ws_flow_condition_masked()).
    function conditionFields(box, node) {
        var config = node.config;
        var rule = config.rule = (config.rule && typeof config.rule === 'object') ? config.rule : { type: '' };
        var types = [['', t('fl_cond_pick')], ['workday', t('fl_cond_workday')], ['weekdays', t('fl_cond_weekdays')], ['hours', t('fl_cond_hours')], ['metric', t('fl_cond_metric')], ['record', t('fl_cond_record')]];
        var type = K.select(types, rule.type || '');
        var changed = function () {
            refreshNode(node);
        };

        type.addEventListener('change', function () {
            config.rule = { type: type.value };

            if (type.value === 'weekdays') {
                config.rule.days = [1, 2, 3, 4, 5];
            } else if (type.value === 'hours') {
                config.rule.from = '09:00';
                config.rule.to = '18:00';
            } else if ((type.value === 'metric') || (type.value === 'record')) {
                config.rule.op = (type.value === 'metric') ? 'gt' : 'eq';
                config.rule.value = '';
            }

            refreshNode(node);
            drawSettings();
        });
        box.appendChild(field(t('fl_cond_kind'), type, t('fl_cond_help')));

        if (rule.type === 'weekdays') {
            var days = el('div', 'wsf-people-list');

            days.setAttribute('role', 'group');
            days.setAttribute('aria-label', t('fl_cond_weekdays'));
            [1, 2, 3, 4, 5, 6, 7].forEach(function (day) {
                var row = el('label', 'wsf-person');
                var check = el('input', 'form-check-input m-0');

                check.type = 'checkbox';
                check.checked = (rule.days || []).indexOf(day) !== -1;
                check.disabled = !state.edit;
                check.addEventListener('change', function () {
                    var chosen = (rule.days || []).filter(function (item) { return item !== day; });

                    if (check.checked) {
                        chosen.push(day);
                    }

                    rule.days = chosen.sort();
                    changed();
                });
                row.appendChild(check);
                row.appendChild(document.createTextNode(t(DAYS[day])));
                days.appendChild(row);
            });
            box.appendChild(field(t('fl_cond_weekdays'), days));
        }

        if (rule.type === 'hours') {
            [['from', 'fl_cond_from'], ['to', 'fl_cond_to']].forEach(function (pair) {
                var key = pair[0];
                var clock = el('input', 'form-control form-control-sm');

                clock.type = 'time';
                clock.value = rule[key] || '';
                clock.addEventListener('input', function () {
                    rule[key] = clock.value;
                    changed();
                });
                box.appendChild(field(t(pair[1]), clock));
            });
        }

        if (rule.type === 'metric') {
            conditionMetric(box, node, rule);
        }

        if (rule.type === 'record') {
            conditionRecord(box, node, rule);
        }
    }

    // A count compared with a number; a count of a channel's tasks reads one
    // channel, or every one, and a count measured against a number (products
    // low in stock) takes that number.
    function conditionMetric(box, node, rule) {
        var lists = stepLists();
        var options = [['', t('fl_cond_metric_pick')]].concat(lists.metrics.map(function (item) { return [item.key, item.label]; }));

        if (rule.metric && !lists.metrics.some(function (item) { return item.key === rule.metric; })) {
            options.push([rule.metric, rule.metric]);
        }

        var metric = K.select(options, rule.metric || '');
        var info = lists.metrics.filter(function (item) { return item.key === rule.metric; })[0];

        metric.addEventListener('change', function () {
            var picked = lists.metrics.filter(function (item) { return item.key === metric.value; })[0];

            rule.metric = metric.value;
            // A number to measure against starts at 5, as on the scheduled
            // form (saMetricPicker() in workspace.js).
            rule.param = (picked && (picked.scope === 'threshold')) ? 5 : 0;
            refreshNode(node);
            drawSettings();
        });
        box.appendChild(field(t('fl_cond_metric'), metric));

        if (info && (info.scope === 'channel')) {
            var place = channelPick({ channel_id: rule.param || 0 }, t('fl_cond_any_channel'));

            place.addEventListener('change', function () {
                rule.param = parseInt(place.value, 10) || 0;
                refreshNode(node);
            });
            box.appendChild(field(t('fl_task_channel'), place));
        }

        if (info && (info.scope === 'threshold')) {
            var most = el('input', 'form-control form-control-sm wsf-number');

            most.type = 'number';
            most.min = '0';
            most.max = '1000000';
            most.value = String(rule.param || 0);
            most.addEventListener('input', function () {
                rule.param = Math.max(0, parseInt(most.value, 10) || 0);
                refreshNode(node);
            });
            box.appendChild(field(t('fl_cond_threshold'), most));
        }

        conditionCompare(box, node, rule, lists.counts.map(function (op) { return [op, lists.operators[op] || op]; }), null);
    }

    // A field of a record compared with a value.
    function conditionRecord(box, node, rule) {
        var lists = stepLists();
        var options = [['', t('fl_change_pick')]];

        Object.keys(lists.records).forEach(function (key) {
            options.push([key, lists.records[key].label]);
        });

        if (rule.record && !lists.records[rule.record]) {
            options.push([rule.record, rule.record]);
        }

        var kind = K.select(options, rule.record || '');
        var record = lists.records[rule.record] || null;
        var id = el('input', 'form-control form-control-sm wsf-number');

        kind.addEventListener('change', function () {
            rule.record = kind.value;
            rule.field = '';
            rule.value = '';
            refreshNode(node);
            drawSettings();
        });
        id.type = 'number';
        id.min = '1';
        id.value = rule.id ? String(rule.id) : '';
        id.addEventListener('input', function () {
            rule.id = Math.max(0, parseInt(id.value, 10) || 0);
            refreshNode(node);
        });
        box.appendChild(field(t('fl_change_kind'), kind));
        box.appendChild(field(t('fl_change_id'), id, state.edit ? t('fl_cond_id_help') : t('fl_cond_hidden')));

        var fields = record ? record.fields : {};
        var names = Object.keys(fields).filter(function (name) { return fields[name].column; });
        var pick = K.select([['', t('fl_cond_field_pick')]].concat(names.map(function (name) { return [name, fields[name].label]; })), rule.field || '');

        if (rule.field && (names.indexOf(rule.field) === -1)) {
            pick.appendChild(new Option(rule.field, rule.field, true, true));
        }

        pick.addEventListener('change', function () {
            rule.field = pick.value;
            rule.value = '';
            refreshNode(node);
            drawSettings();
        });
        box.appendChild(field(t('fl_cond_field'), pick));

        var meta = fields[rule.field] || null;
        var ops = Object.keys(lists.operators).filter(function (op) {
            return ((op !== 'within') && (op !== 'overdue')) || (meta && ((meta.kind === 'date') || (meta.kind === 'datetime')));
        });

        conditionCompare(box, node, rule, ops.map(function (op) { return [op, lists.operators[op]]; }), meta);
    }

    // How the value is compared, and the value: a field's choices where it
    // has them, else the value as the field holds it (conditionValue()). No
    // value for "is empty", its twin and "has gone by".
    function conditionCompare(box, node, rule, ops, meta) {
        var op = K.select(ops, rule.op || (ops[0] ? ops[0][0] : ''));

        op.addEventListener('change', function () {
            rule.op = op.value;
            refreshNode(node);
            drawSettings();
        });
        box.appendChild(field(t('fl_cond_op'), op));

        if ((rule.op === 'empty') || (rule.op === 'not_empty') || (rule.op === 'overdue')) {
            return;
        }

        var input;

        if (meta && (meta.kind === 'enum') && ((rule.op === 'eq') || (rule.op === 'neq'))) {
            input = K.select([['', t('fl_cond_value_pick')]].concat((meta.values || []).map(function (item) {
                return [item, (meta.labels && meta.labels[item]) ? meta.labels[item] : item];
            })), rule.value || '');
            input.addEventListener('change', function () {
                rule.value = input.value;
                refreshNode(node);
            });
        } else {
            // "Falls within the next days" takes a number of days.
            input = conditionValue((rule.op === 'within') ? null : meta, rule.value, function (value) {
                rule.value = value;
                refreshNode(node);
            });
        }

        box.appendChild(field(t('fl_cond_value'), input, (rule.op === 'within') ? t('fl_cond_within_help') : ''));
    }

    // A value a field is compared with, kept as the record keeps it and as
    // the scheduled form sends it (saValueInput() in workspace.js): an amount
    // shown as a decimal and kept in minor units (changeInput()), yes or no
    // as '1' or '0', a day as YYYY-MM-DD, a moment as YYYY-MM-DD HH:MM:00, a
    // decimal with a dot, anything else as typed. Always text: the store
    // would keep a true or false as '' (ws_flow_text()).
    function conditionValue(meta, value, onChange) {
        var kind = meta ? meta.kind : '';
        var input;

        if (kind === 'money') {
            return changeInput(meta, value, function (minor) {
                onChange(String(minor));
            });
        }

        if (kind === 'bool') {
            input = K.select([['1', t('fl_change_yes')], ['0', t('fl_change_no')]], (String(value) === '1') ? '1' : '0');
            input.addEventListener('change', function () {
                onChange(input.value);
            });

            return input;
        }

        input = textInput(value, 500, function (typed) {
            if (kind === 'datetime') {
                typed = (typed === '') ? '' : typed.replace('T', ' ').slice(0, 16) + ':00';
            }

            onChange((kind === 'decimal') ? typed.replace(',', '.') : typed);
        });

        if (kind === 'date') {
            input.type = 'date';
        } else if (kind === 'datetime') {
            input.type = 'datetime-local';
            input.value = String(value || '').replace(' ', 'T').slice(0, 16);
        }

        return input;
    }

    // A record changed: its kind, update or delete, its number, and for an
    // update the fields with their new values. Somebody who may not change
    // the flow reads the kind, the action and the names of the fields only
    // (ws_flow_graph_present()).
    function changeFields(box, node) {
        var config = node.config;
        var kinds = stepLists().changes;
        var options = [['', t('fl_change_pick')]];

        Object.keys(kinds).forEach(function (key) {
            options.push([key, kinds[key].label]);
        });

        if (config.record && !kinds[config.record]) {
            options.push([config.record, config.record]);
        }

        var kind = K.select(options, config.record || '');
        var info = kinds[config.record] || null;
        var actions = (info ? info.actions : ['update', 'delete']).map(function (action) {
            return [action, t((action === 'delete') ? 'fl_change_delete' : 'fl_change_update')];
        });
        var action = K.select(actions, config.action || 'update');
        var id = el('input', 'form-control form-control-sm wsf-number');

        config.fields = (config.fields && !Array.isArray(config.fields)) ? config.fields : {};
        kind.addEventListener('change', function () {
            config.record = kind.value;
            config.fields = {};
            config.action = 'update';
            refreshNode(node);
            drawSettings();
        });
        action.addEventListener('change', function () {
            config.action = action.value;

            if (config.action === 'delete') {
                config.fields = {};
            }

            refreshNode(node);
            drawSettings();
        });
        id.type = 'number';
        id.min = '1';
        id.value = config.id ? String(config.id) : '';
        id.addEventListener('input', function () {
            config.id = Math.max(0, parseInt(id.value, 10) || 0);
            refreshNode(node);
        });
        box.appendChild(field(t('fl_change_kind'), kind));
        box.appendChild(field(t('fl_change_action'), action));
        box.appendChild(field(t('fl_change_id'), id, state.edit ? t('fl_change_id_help') : t('fl_change_hidden')));

        if (config.action !== 'delete') {
            changeValues(box, node, info);
        }
    }

    // The fields of an update, one row each, and a list to add another from.
    function changeValues(box, node, info) {
        var config = node.config;
        var fields = info ? info.fields : {};
        var list = el('div', 'wsf-change-fields');

        Object.keys(config.fields).forEach(function (name) {
            var meta = fields[name] || { kind: 'text', label: name, values: [], labels: {} };
            var input = changeInput(meta, config.fields[name], function (value) {
                config.fields[name] = value;
                refreshNode(node);
            });
            var row = field(meta.label, input);

            if (state.edit) {
                var remove = K.button('btn btn-sm btn-link px-0', t('fl_change_remove'), 'bi-x');

                remove.addEventListener('click', function () {
                    delete config.fields[name];
                    refreshNode(node);
                    drawSettings();
                });
                row.appendChild(remove);
            }

            list.appendChild(row);
        });

        box.appendChild(list);

        var left = Object.keys(fields).filter(function (name) {
            return !(name in config.fields) && (fields[name].kind !== 'answers');
        });

        if (!state.edit || !left.length) {
            return;
        }

        var add = K.select([['', t('fl_change_add')]].concat(left.map(function (name) { return [name, fields[name].label]; })), '');

        add.addEventListener('change', function () {
            var meta = fields[add.value];

            if (!meta) {
                return;
            }

            config.fields[add.value] = (meta.kind === 'bool') ? false : ((meta.kind === 'enum') ? (meta.values[0] || '') : '');
            refreshNode(node);
            drawSettings();
        });
        box.appendChild(field(t('fl_change_fields'), add, t('fl_change_fields_help')));
    }

    // One field's new value as the change catalogue takes it: a choice, yes
    // or no, an amount in minor units shown as a decimal, or text.
    function changeInput(meta, value, onChange) {
        var input;

        if (meta.kind === 'enum') {
            input = K.select((meta.values || []).map(function (item) {
                return [item, (meta.labels && meta.labels[item]) ? meta.labels[item] : item];
            }), value);
            input.addEventListener('change', function () { onChange(input.value); });
        } else if (meta.kind === 'bool') {
            input = K.select([['1', t('fl_change_yes')], ['0', t('fl_change_no')]], (value === true || value === 1 || value === '1') ? '1' : '0');
            input.addEventListener('change', function () { onChange(input.value === '1'); });
        } else if (meta.kind === 'answers') {
            input = el('input', 'form-control form-control-sm');
            input.type = 'text';
            input.value = t('fl_change_answers');
            input.disabled = true;
        } else if (meta.kind === 'money') {
            input = el('input', 'form-control form-control-sm');
            input.type = 'number';
            input.step = '0.01';
            input.min = '0';
            input.value = (value === '' || value === null || value === undefined) ? '' : (parseInt(value, 10) / 100).toFixed(2);
            input.addEventListener('input', function () {
                onChange((input.value === '') ? '' : Math.round(parseFloat(input.value) * 100));
            });
        } else {
            input = textInput((value === null || value === undefined) ? '' : String(value), (meta.kind === 'long') ? 20000 : 4000, onChange, meta.kind === 'long');
        }

        return input;
    }

    // The team as ticks, with a filter once it is long, drawn into wrap: the
    // ids go into the box's settings under key (assignees by default), at
    // most max of them.
    function peopleList(node, wrap, key, max) {
        var config = node.config;
        var list = el('div', 'wsf-people-list');

        key = key || 'assignees';
        max = max || 20;

        var chosen = config[key] || [];
        var team = BOOT.people || [];

        list.setAttribute('role', 'group');
        list.setAttribute('aria-label', t('fl_assign_people'));

        if (team.length > 8) {
            var filter = el('input', 'form-control form-control-sm mb-1');

            filter.type = 'search';
            filter.placeholder = t('fl_people_filter');
            filter.setAttribute('aria-label', t('fl_people_filter'));
            filter.disabled = !state.edit;
            filter.addEventListener('input', function () {
                var query = filter.value.trim().toLocaleLowerCase();

                Array.prototype.forEach.call(list.children, function (row) {
                    row.classList.toggle('d-none', query !== '' && row.textContent.toLocaleLowerCase().indexOf(query) === -1);
                });
            });
            wrap.appendChild(filter);
        }

        team.forEach(function (member) {
            var row = el('label', 'wsf-person');
            var check = el('input', 'form-check-input m-0');

            check.type = 'checkbox';
            check.checked = chosen.indexOf(member.id) !== -1;
            check.disabled = !state.edit;
            check.addEventListener('change', function () {
                var ids = (config[key] || []).filter(function (id) { return id !== member.id; });

                if (check.checked) {
                    ids.push(member.id);
                }

                config[key] = ids.slice(0, max);

                if (node.kind === 'task') {
                    config.assign = '';
                }
                refreshNode(node);
            });
            row.appendChild(check);

            if (member.avatar) {
                row.appendChild(K.avatar(member, 'var(--ws-avatar-xs)'));
            }

            row.appendChild(document.createTextNode(member.name));
            list.appendChild(row);
        });

        wrap.appendChild(list);

        return wrap;
    }

    // The name of another box in a form, marked so that it follows a rename
    // (refreshNode()).
    function nameText(node, className) {
        var text = el('span', className || '', nameOf(node));

        text.setAttribute('data-name-of', node.id);

        return text;
    }

    // A label built from another box's name, marked the same way.
    function nameLabel(control, key, node) {
        control.setAttribute('aria-label', t(key, nameOf(node)));
        control.setAttribute('data-label-of', node.id);
        control.setAttribute('data-label-key', key);
    }

    // The arrows of a box, and the way to add one without dragging.
    function connections(node) {
        var box = el('div', 'wsf-links mb-3');
        var into = state.graph.edges.filter(function (edge) { return edge.to === node.id && nodeById(edge.from); });

        if (kindOf(node).into) {
            var after = el('div', 'small text-body-secondary mb-2', into.length ? '' : t('fl_after_none'));

            into.forEach(function (edge, index) {
                if (index) {
                    after.appendChild(document.createTextNode(', '));
                }

                after.appendChild(nameText(nodeById(edge.from)));
            });

            box.appendChild(el('div', 'form-label small fw-semibold mb-1', t('fl_after')));
            box.appendChild(after);
        }

        if (!kindOf(node).out.length) {
            return box;
        }

        box.appendChild(el('div', 'form-label small fw-semibold mb-1', t('fl_then')));

        var list = el('ul', 'list-unstyled mb-2');

        state.graph.edges.forEach(function (edge, index) {
            if ((edge.from !== node.id) || !nodeById(edge.to)) {
                return;
            }

            var row = el('li', 'wsf-link');

            row.appendChild(K.icon('bi-arrow-right'));
            row.appendChild(nameText(nodeById(edge.to), 'flex-grow-1 text-truncate'));

            if (kindOf(node).out.length > 1) {
                row.appendChild(outcomeSelect(edge));
            }

            var remove = K.button('btn btn-sm btn-ghost', '', 'bi-x-lg');

            nameLabel(remove, 'fl_remove_arrow_to', nodeById(edge.to));
            remove.title = remove.getAttribute('aria-label');
            remove.addEventListener('click', function () {
                state.refocus = 'connect-' + node.id;
                removeSelected({ type: 'edge', id: index });
            });
            row.appendChild(remove);
            list.appendChild(row);
        });

        if (!list.children.length) {
            list.appendChild(el('li', 'small text-body-secondary', t('fl_then_none')));
        }

        box.appendChild(list);

        var targets = candidates(node);
        var group = el('div', 'input-group input-group-sm');
        var pick = K.select([['', t('fl_connect_pick')]].concat(targets.map(function (target) { return [target.id, nameOf(target)]; })), '');
        var add = K.button('btn btn-outline-secondary', t('fl_connect_add'), 'bi-plus-lg');

        pick.classList.add('wsf-connect');
        nameLabel(pick, 'fl_connect_title', node);
        pick.setAttribute('data-refocus', 'connect-' + node.id);
        add.disabled = !targets.length;
        add.addEventListener('click', function () {
            if (pick.value) {
                state.refocus = 'connect-' + node.id;
                connect(node, nodeById(pick.value));
            }
        });
        group.appendChild(pick);
        group.appendChild(add);
        box.appendChild(group);

        return box;
    }

    function outcomeSelect(edge) {
        var from = nodeById(edge.from);
        var pick = K.select((from ? kindOf(from).out : [edge.on]).map(function (on) { return [on, t(OUTCOMES[on] || 'fl_on_done')]; }), edge.on);

        pick.className = 'form-select form-select-sm w-auto';
        pick.setAttribute('aria-label', onLabel(from));
        pick.setAttribute('data-refocus', 'on-' + edge.from + '-' + edge.to);
        pick.disabled = !state.edit;
        pick.addEventListener('change', function () {
            edge.on = pick.value;
            state.refocus = 'on-' + edge.from + '-' + edge.to;
            changed(true);
        });

        return pick;
    }

    // How the outcome picker of an arrow is called: a task is done or
    // cancelled, a step box done or failed.
    function onLabel(from) {
        return (from && (from.kind === 'task')) ? t('fl_on') : t('fl_on_box');
    }

    function edgeForm(index) {
        var edge = state.graph.edges[index];
        var box = el('div', 'wsf-form');

        box.appendChild(el('p', 'small mb-2', t('fl_arrow_text', nameOf(nodeById(edge.from)), nameOf(nodeById(edge.to)))));

        if (nodeById(edge.from) && (kindOf(nodeById(edge.from)).out.length > 1)) {
            box.appendChild(field(onLabel(nodeById(edge.from)), outcomeSelect(edge)));
        }

        if (state.edit) {
            var remove = K.button('btn btn-sm btn-outline-warning', t('fl_remove_arrow'), 'bi-trash');
            remove.addEventListener('click', function () { removeSelected({ type: 'edge', id: index }); });
            box.appendChild(remove);
        }

        return box;
    }

    // ── The outline ────────────────────────────────────────────────────

    // The heading after the kind: the box's title, if it has one.
    function outlineTitle(node) {
        var title = titleOf(node);

        return title ? ' · ' + title : ((node.kind === 'task') ? ' · ' + t('fl_untitled_box') : '');
    }

    function drawOutline() {
        K.clear(ui.outlineBox);

        if (!state.outline || !state.flow) {
            return;
        }

        // A run shown is read only: the bar over it says so, not this help.
        if (!state.run) {
            ui.outlineBox.appendChild(el('p', 'small text-body-secondary', t('fl_outline_help')));
        }

        var list = el('ol', 'wsf-outline-list');

        orderedNodes().forEach(function (node) {
            var item = el('li', 'wsf-outline-item wsf-node-' + (KINDS[node.kind] ? node.kind : 'unknown'));
            var head = el('h3', 'wsf-outline-head');

            item.setAttribute('data-node', node.id);
            head.appendChild(K.icon(kindOf(node).icon, 'me-1'));
            head.appendChild(el('span', 'wsf-outline-kind', t(kindOf(node).name)));
            head.appendChild(el('span', 'wsf-outline-title', outlineTitle(node)));
            item.appendChild(head);

            // A run shown: how each box went, in words, in place of its
            // settings. The heading takes the focus when a control of the
            // box goes with a redraw (keepFocus()), as a canvas box does.
            if (state.run) {
                var step = stepOf(node.id);

                head.tabIndex = -1;
                head.setAttribute('data-refocus', 'outline-box-' + node.id);
                item.classList.add('wsf-run-' + ((step && STEP_STATES[step.status]) ? step.status : 'none'));
                item.appendChild(outlineRun(node, step));
            } else {
                item.appendChild(nodeForm(node));
            }
            list.appendChild(item);
        });

        if (!list.children.length) {
            list.appendChild(el('li', 'small text-body-secondary', state.edit ? t('fl_empty_canvas') : ''));
        }

        ui.outlineBox.appendChild(list);

        if (state.edit) {
            var adds = el('div', 'd-flex flex-wrap gap-2 mb-3');

            adds.setAttribute('role', 'group');
            adds.setAttribute('aria-label', t('fl_palette'));
            paletteButtons('btn btn-sm btn-outline-secondary', false).forEach(function (add) {
                adds.appendChild(add);
            });
            ui.outlineBox.appendChild(adds);
        }

        refocus();
    }

    // ── Changing the graph ─────────────────────────────────────────────

    // The words of a box changed: its face, the outline's heading and the
    // other forms that name it (arrows into and out of it, "Connect to..."
    // lists and their labels) follow; nothing is drawn again (the field
    // being written in keeps its focus).
    function refreshNode(node) {
        var name = nameOf(node);

        Array.prototype.forEach.call(ui.root.querySelectorAll('.wsf-node[data-node="' + node.id + '"]'), function (box) {
            fillBox(box, node);
        });

        Array.prototype.forEach.call(ui.outlineBox.querySelectorAll('[data-node="' + node.id + '"] > .wsf-outline-head .wsf-outline-title'), function (title) {
            title.textContent = outlineTitle(node);
        });

        Array.prototype.forEach.call(ui.root.querySelectorAll('[data-name-of="' + node.id + '"], .wsf-connect option[value="' + node.id + '"]'), function (text) {
            text.textContent = name;
        });

        Array.prototype.forEach.call(ui.root.querySelectorAll('[data-label-of="' + node.id + '"]'), function (control) {
            var label = t(control.getAttribute('data-label-key'), name);

            control.setAttribute('aria-label', label);

            if (control.title) {
                control.title = label;
            }
        });

        changed(false);
    }

    // Something changed: drawn again when boxes or arrows came or went, and
    // saved a moment after the last change. The canvas hidden behind the
    // outline is drawn when it is shown again (drawAll()).
    function changed(structure) {
        if (!state.edit) {
            return;
        }

        state.dirty = true;
        state.changes++;

        if (structure) {
            if (!state.outline) {
                drawCanvas();
            }

            drawOutline();
            drawSettings();
        }

        drawState();
        window.clearTimeout(state.saveTimer);
        state.saveTimer = window.setTimeout(save, SAVE_DELAY);
    }

    // A free place near the middle of what is shown, below any box there.
    // With the canvas hidden (the outline), right of the last box instead,
    // so a flow built in the outline reads left to right on the canvas.
    function freePlace() {
        var rect = ui.viewport.getBoundingClientRect();
        var x = coord((rect.width / 2 - state.view.x) / state.view.k - BOX_W / 2);
        var y = coord((rect.height / 3 - state.view.y) / state.view.k - BOX_H / 2);

        if (!rect.width && state.graph.nodes.length) {
            var last = state.graph.nodes.reduce(function (a, b) { return (b.x > a.x) ? b : a; });

            x = coord(last.x + BOX_W + GRID * 5);
            y = last.y;
        }

        var taken = function () {
            return state.graph.nodes.some(function (node) {
                return (Math.abs(node.x - x) < GRID * 4) && (Math.abs(node.y - y) < GRID * 3);
            });
        };

        for (var tries = 0; taken() && (tries < 60); tries++) {
            y += BOX_H + GRID * 2;
        }

        return { x: x, y: y };
    }

    function addItem(kind, at) {
        if (!state.edit || !state.flow) {
            return;
        }

        var limits = FL.limits || {};

        if (state.graph.nodes.length >= (limits.nodes || 100)) {
            K.toast(t('fl_too_many_boxes', limits.nodes || 100), 'warning');
            return;
        }

        var place = at || freePlace();
        var config = {};

        if (kind === 'task') {
            config = { title: '', description: '', assignees: [], assign: 'creator', due_in: '', priority: 'normal' };
        } else if (kind === 'manual') {
            config = { subject: '' };
        } else if (kind === 'post') {
            config = { channel_id: 0, body: '' };
        } else if (kind === 'email') {
            config = { to: '', subject: '', body: '', page_id: 0 };
        } else if (kind === 'notify') {
            config = { user_ids: [], text: '' };
        } else if (kind === 'task_open') {
            config = { title: '', description: '', assignees: [], channel_id: 0, due_in: '', priority: 'normal' };
        } else if (kind === 'webhook') {
            config = { url: '', format: 'generic', text: '' };
        } else if (kind === 'web_check') {
            config = { url: '', mode: 'status', redirect: false, contains: '', max_ms: 0, days: 14 };
        } else if (kind === 'task_digest') {
            config = { mode: 'mine', channel_id: 0, user_ids: [], only: 'open', subject: '', intro: '', missed: false, ai: false };
        } else if (kind === 'report') {
            config = { title: '', metrics: [], channel_id: 0, to: '' };
        } else if (kind === 'checkin') {
            config = { channel_id: 0, question: '' };
        } else if (kind === 'start_action') {
            config = { target_id: 0, delay: 0 };
        } else if (kind === 'change') {
            config = { record: '', action: 'update', id: 0, fields: {} };
        } else if (kind === 'lead') {
            config = { private: false, user_ids: [] };
        } else if (kind === 'condition') {
            config = { rule: { type: 'workday' } };
        } else if (kind === 'schedule') {
            config = { date: new Date().toISOString().slice(0, 10), time: '09:00', repeat: 'none', every: 1, days: [], until: '' };
        } else if (kind === 'event') {
            config = { event: '', page_id: 0, status: '', channel_id: 0 };
        } else if (kind === 'join') {
            config = { channel_id: 0 };
        }

        var node = { id: freeId(kind, state.graph.nodes), kind: kind, label: '', x: place.x, y: place.y, config: config };

        state.graph.nodes.push(node);
        state.selected = { type: 'node', id: node.id };
        state.refocus = state.outline ? 'title-' + node.id : '';
        changed(true);
        focusSelected();
        announce(t('fl_added', t(KINDS[kind].name)));
    }

    // Whether an arrow may go from one box to another, as the store checks
    // it: not into itself, not into the start, not twice on one outcome.
    function canConnect(from, to) {
        var outcome = kindOf(from).out[0];

        return !!(from && to && outcome && (from.id !== to.id) && kindOf(to).into
            && !state.graph.edges.some(function (edge) { return edge.from === from.id && edge.to === to.id && edge.on === outcome; }));
    }

    function candidates(from) {
        return orderedNodes().filter(function (node) { return canConnect(from, node); });
    }

    function connect(from, to) {
        if (!canConnect(from, to)) {
            return false;
        }

        state.graph.edges.push({ from: from.id, to: to.id, on: kindOf(from).out[0] });
        changed(true);
        announce(t('fl_connected', nameOf(from), nameOf(to)));

        return true;
    }

    // "Connect to..." for the keyboard: the boxes an arrow may lead to.
    function askConnect(node) {
        if (!state.edit || !node || !kindOf(node).out.length) {
            return;
        }

        var targets = candidates(node);

        if (!targets.length) {
            K.toast(t('fl_connect_none'), 'secondary');
            return;
        }

        var pick = K.select(targets.map(function (target) { return [target.id, t(kindOf(target).name) + ': ' + nameOf(target)]; }), targets[0].id);

        pick.className = 'form-select';
        pick.setAttribute('aria-label', t('fl_connect_title', nameOf(node)));

        K.ask(t('fl_connect_title', nameOf(node)), t('fl_connect_add'), false, pick).then(function (yes) {
            if (yes) {
                connect(node, nodeById(pick.value));
            }

            state.selected = { type: 'node', id: node.id };
            markSelected();
            focusSelected();
        });

        // The dialog takes the focus once it is shown; the list then takes it
        // from the dialog, so the arrow keys choose at once.
        var dialog = document.getElementById('ws-ask');

        if (dialog) {
            pgUI.dialog.on(dialog, 'shown', function () {
                pick.focus();
            }, { once: true });
        }
    }

    function removeSelected(target) {
        var selected = target || state.selected;

        if (!state.edit || !selected) {
            return;
        }

        var selectedEdge = (state.selected && (state.selected.type === 'edge')) ? state.graph.edges[state.selected.id] : null;

        if (selected.type === 'node') {
            var node = nodeById(selected.id);

            if (!node) {
                return;
            }

            // In the outline the button that removed the box goes with it:
            // the next box's title takes the focus, or the buttons that add
            // one when no box is left.
            if (state.outline && !state.refocus) {
                var order = orderedNodes();
                var near = order[order.indexOf(node) + 1] || order[order.indexOf(node) - 1];

                state.refocus = (near ? 'title-' + near.id + ' ' : '') + 'outline-add-task';
            }

            state.graph.nodes = state.graph.nodes.filter(function (item) { return item !== node; });
            state.graph.edges = state.graph.edges.filter(function (edge) { return edge.from !== node.id && edge.to !== node.id; });
            announce(t('fl_removed', nameOf(node)));
        } else if (selected.type === 'edge') {
            state.graph.edges.splice(selected.id, 1);
            announce(t('fl_removed', t('fl_arrow')));
        }

        // The problems named the old places of the arrows; the next save
        // tells them again.
        state.problems = state.problems.filter(function (problem) { return (problem.edge === null) || (problem.edge === undefined); });

        if (!target || (state.selected && (state.selected.type === selected.type) && (state.selected.id === selected.id))) {
            state.selected = null;
        } else if (selectedEdge) {
            // An arrow is selected by its place in the list: the selection
            // follows the arrow it was, and goes when a removed box took it.
            var index = state.graph.edges.indexOf(selectedEdge);

            state.selected = (index === -1) ? null : { type: 'edge', id: index };
        }

        var keep = state.refocus;

        changed(true);

        // The control that removed it is gone: the canvas takes the focus.
        if (!keep && !state.outline) {
            ui.viewport.focus({ preventScroll: true });
        }
    }

    function moveSelected(dx, dy) {
        var selected = state.selected;
        var item = (selected && (selected.type === 'node')) ? nodeById(selected.id) : null;

        if (!item || !state.edit) {
            return;
        }

        item.x = coord(item.x + dx);
        item.y = coord(item.y + dy);
        place(ui.boxes.querySelector('[data-node="' + item.id + '"]'), item);
        drawEdges();
        reveal(selected);
        changed(false);
    }

    // ── Saving ─────────────────────────────────────────────────────────

    // The draft as saved, also while a run is shown over it.
    function payload() {
        var data = { flow_id: state.flow.id, draft_rev: state.flow.draft_rev, channel_id: state.flow.channel_id || 0, graph: draftOf() };

        if (String(state.flow.name || '').trim() !== '') {
            data.name = state.flow.name;
        }

        return data;
    }

    // options: { keepalive } for the save sent as the page is left.
    function save(options) {
        window.clearTimeout(state.saveTimer);

        if (!drafting() || !state.flow || state.conflict) {
            return Promise.resolve();
        }

        if (state.saving) {
            return state.saving.then(function () { return state.dirty ? save() : null; });
        }

        if (!state.dirty) {
            return Promise.resolve();
        }

        var flow = state.flow;

        state.dirty = false;
        state.saveError = '';
        state.saving = K.api('ws_flow_save', payload(), options).then(function (data) {
            flow.draft_rev = data.draft_rev;
            flow.changed = true;

            if (state.flow === flow) {
                state.problems = data.problems || [];

                // A run shown keeps the canvas; the draft's problems are
                // drawn once it is closed.
                if (!state.run) {
                    drawProblems();
                    markProblems();
                }
            }
        }).catch(function (error) {
            state.dirty = true;

            if (error.field === 'changed_meanwhile') {
                conflict(error.message);
            } else {
                state.saveError = error.message || t('fl_unsaved');
            }
        }).then(function () {
            state.saving = null;
            drawState();

            if (state.dirty && !state.conflict && !state.saveError) {
                state.saveTimer = window.setTimeout(save, SAVE_DELAY);
            }
        });

        drawState();

        return state.saving;
    }

    // What is still unsaved, saved before another flow is opened.
    function flush() {
        return state.dirty ? save() : (state.saving || Promise.resolve());
    }

    // The page is hidden or being left: the last changes go out on a
    // request the browser finishes even after the page is gone (keepalive).
    // It is an ordinary save otherwise: its answer is read, so a page that
    // lives on (a tab shown again) holds the draft_rev the server moved to,
    // and a refusal leaves the changes unsaved. Browsers refuse a keepalive
    // body over 64 KiB: a larger draft is saved the usual way while the page
    // is only hidden, and when it is left (leaving) it stays unsaved, which
    // beforeunload asks about.
    function saveOnLeave(leaving) {
        if (!drafting() || !state.flow || !state.dirty || state.saving || state.conflict) {
            return;
        }

        var keepalive = bytes(JSON.stringify(payload())) <= KEEPALIVE_BYTES;

        if (keepalive || !leaving) {
            save({ keepalive: keepalive });
        }
    }

    function conflict(message) {
        state.conflict = message || t('fl_conflict');
        K.clear(ui.conflictBox);
        ui.conflictBox.appendChild(K.icon('bi-exclamation-triangle'));
        ui.conflictBox.appendChild(el('span', 'flex-grow-1', state.conflict));

        var reload = K.button('btn btn-sm btn-outline-secondary', t('fl_reload'), 'bi-arrow-clockwise');

        reload.addEventListener('click', function () {
            state.dirty = false;
            state.saveError = '';
            state.conflict = '';
            state.refocus = 'name';
            open(state.flow.id);
        });
        ui.conflictBox.appendChild(reload);
        ui.conflictBox.classList.remove('d-none');
        drawState();
    }

    // ── Publish, pause, delete ─────────────────────────────────────────

    function publish() {
        save().then(function () {
            if (state.problems.length || state.dirty || state.conflict) {
                drawState();
                return;
            }

            var n = (state.flow.version || 0) + 1;

            K.ask(t('fl_publish_ask', n), t('fl_publish_do', n)).then(function (yes) {
                if (yes) {
                    act('ws_flow_publish', ui.publish, t('fl_published', n));
                }
            });
        });
    }

    // Publish and Pause. What is still unsaved is saved first, so the
    // request acts on the draft the screen shows; changes that could not be
    // saved stop it. A change made while the request runs stays on the
    // screen and is saved as usual: the answer then brings only the flow's
    // new status and version, not its draft.
    function act(action, control, done) {
        var flow = state.flow;

        control.disabled = true;

        flush().then(function () {
            if ((state.flow !== flow) || state.dirty || state.saveError || state.conflict) {
                control.disabled = false;
                drawState();

                if (state.flow === flow) {
                    K.toast(state.saveError || state.conflict || t('fl_unsaved'), 'danger');
                }

                return null;
            }

            var mark = state.changes;

            return K.api(action, { flow_id: flow.id, draft_rev: flow.draft_rev }).then(function (data) {
                if (state.flow !== flow) {
                    listEntry(data.flow);
                } else if ((state.changes !== mark) || state.dirty || state.saving) {
                    flow.status = data.flow.status;
                    flow.version = data.flow.version;
                    flow.graph = data.flow.graph;
                    flow.changed = true;
                    listEntry(flow);
                    redrawBar();
                } else {
                    var view = { x: state.view.x, y: state.view.y, k: state.view.k };

                    state.refocus = 'status';
                    show(data.flow);
                    state.view = view;
                    applyView();
                }

                K.toast(done, 'success');
            });
        }).catch(function (error) {
            control.disabled = false;

            if (error.field === 'changed_meanwhile') {
                conflict(error.message);
            } else {
                K.fail(error);
            }
        });
    }

    // The bar drawn again while the person may be at work elsewhere on the
    // screen: focus inside the bar stays on the same control (the name
    // being typed keeps its caret), a control that is gone, or a focus lost
    // to a closed dialog, hands it to the status; focus outside the bar is
    // left alone.
    function redrawBar() {
        var active = document.activeElement;
        var inBar = !!(active && ui.bar.contains(active));
        var lost = !active || (active === document.body);
        var key = inBar ? (active.getAttribute('data-refocus') || '') : '';
        var caret = (key === 'name') ? [active.selectionStart, active.selectionEnd] : null;

        drawBar();

        if (inBar || lost) {
            state.refocus = (key ? key + ' ' : '') + 'status';
            refocus();
        }

        if (caret && ui.name && (document.activeElement === ui.name)) {
            ui.name.setSelectionRange(caret[0], caret[1]);
        }
    }

    function binFlow() {
        var flow = state.flow;

        K.ask(t('fl_bin_ask', flow.name), t('fl_bin'), true).then(function (yes) {
            if (!yes) {
                return;
            }

            window.clearTimeout(state.saveTimer);
            state.dirty = false;

            K.api('ws_flow_bin', { flow_id: flow.id }).then(function () {
                state.flows = state.flows.filter(function (item) { return item.id !== flow.id; });
                state.flow = null;
                drawList();
                K.toast(t('fl_binned'), 'success');

                // The menu that deleted it is gone: the next flow's name, or
                // "New flow", takes the focus.
                state.refocus = 'name new';

                if (state.flows.length) {
                    open(state.flows[0].id);
                } else {
                    showNone();
                }
            }).catch(K.fail);
        });
    }


    // ── Shares ─────────────────────────────────────────────────────────

    // The Share dialog, made once: a title, the list and the controls drawn
    // into its body, and Close. The focus goes back to the menu that opened
    // it.
    function shareModal() {
        if (ui.shareModal) {
            return ui.shareModal;
        }

        var modal = el('div', 'modal fade wsf-share-modal');
        var dialog = el('div', 'modal-dialog modal-dialog-centered modal-dialog-scrollable');
        var content = el('div', 'modal-content');
        var head = el('div', 'modal-header');
        var title = el('h2', 'modal-title fs-6');
        var cross = el('button', 'btn-close');
        var foot = el('div', 'modal-footer');
        var close = K.button('btn btn-sm btn-ghost', t('fl_share_close'));

        modal.id = 'wsf-share';
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'wsf-share-title');
        title.id = 'wsf-share-title';
        cross.type = 'button';
        cross.setAttribute('aria-label', t('fl_share_close'));
        cross.setAttribute('data-bs-dismiss', 'modal');
        close.setAttribute('data-bs-dismiss', 'modal');
        head.appendChild(title);
        head.appendChild(cross);
        foot.appendChild(close);
        content.appendChild(head);
        content.appendChild(el('div', 'modal-body wsf-share-body'));
        content.appendChild(foot);
        dialog.appendChild(content);
        modal.appendChild(dialog);
        document.body.appendChild(modal);

        pgUI.dialog.on(modal, 'hidden', function () {
            state.share = null;
            state.refocus = 'more';
            refocus();
        });

        ui.shareModal = modal;

        return modal;
    }

    // The shares are read when the dialog opens, so a change made elsewhere
    // meanwhile is shown.
    function openShares() {
        var flow = state.flow;

        if (!flow) {
            return;
        }

        K.api('ws_flow_shares', { flow_id: flow.id }).then(function (data) {
            if (state.flow !== flow) {
                return;
            }

            var modal = shareModal();

            state.share = { flow: flow, data: data, busy: false };
            modal.querySelector('.modal-title').textContent = t('fl_share_title', flow.name);
            drawShares();
            pgUI.dialog.show(modal);
        }).catch(K.fail);
    }

    // A share sent, or taken back: the answer carries the list as it is now.
    // focus: the data-share-focus key of the control that takes the focus
    // once the list is drawn again.
    function shareRequest(action, data, focus) {
        var share = state.share;

        if (!share || share.busy) {
            return Promise.resolve(null);
        }

        share.busy = true;
        data.flow_id = share.flow.id;

        return K.api(action, data).then(function (answer) {
            if (state.share !== share) {
                return null;
            }

            share.data = answer;
            share.busy = false;
            drawShares(focus);

            return answer;
        }).catch(function (error) {
            share.busy = false;

            if (state.share === share) {
                drawShares(focus);
            }

            K.fail(error);

            return null;
        });
    }

    function shareName(share) {
        if (share.target_type === 'channel') {
            return share.name ? '#' + share.name : t('fl_share_hidden');
        }

        return share.name || ('#' + share.target_id);
    }

    // One row of the list: who, what they may do, and for the owner the
    // level to change and the way to take the share back.
    function shareRow(share, manage) {
        var row = el('li', 'wsf-share-row');
        var who = el('span', 'wsf-share-who');
        var name = shareName(share);
        var member = (share.target_type === 'user') ? person(share.target_id) : null;

        if (share.target_type === 'user') {
            who.appendChild(member ? K.avatar(member, '1.5rem') : K.icon('bi-person-circle'));
        } else {
            who.appendChild(K.icon('bi-hash'));
        }

        who.appendChild(el('span', 'text-truncate', name));
        row.appendChild(who);

        var level = el('span', 'wsf-share-level');

        if (manage && (share.target_type === 'user')) {
            var pick = K.select(Object.keys(LEVELS).map(function (key) { return [key, t(LEVELS[key])]; }), share.level);

            pick.setAttribute('aria-label', t('fl_share_level') + ': ' + name);
            pick.setAttribute('data-share-focus', 'level-' + share.id);
            pick.addEventListener('change', function () {
                var chosen = pick.value;

                shareRequest('ws_flow_share_set', { target_type: 'user', target_id: share.target_id, level: chosen }, 'level-' + share.id).then(function (answer) {
                    if (answer) {
                        announce(t('fl_share_changed', name, t(LEVELS[chosen])));
                    }
                });
            });
            level.appendChild(pick);
        } else {
            level.appendChild(el('span', 'small', t(LEVELS[share.level] || LEVELS.view)));
        }

        // What it gives now, when that is less than it was given.
        if (share.effective_level === '') {
            level.appendChild(el('span', 'form-text d-block m-0', t('fl_share_gone')));
        } else if ((share.target_type === 'user') && (share.effective_level !== share.level)) {
            level.appendChild(el('span', 'form-text d-block m-0', t('fl_share_as_run')));
        }

        row.appendChild(level);

        if (manage) {
            var remove = K.button('btn btn-sm btn-ghost wsf-share-remove', '', 'bi-x-lg', t('fl_share_remove', name));

            remove.setAttribute('data-share-focus', 'remove-' + share.id);
            remove.addEventListener('click', function () {
                // The focus goes to the row below, or the one above, or the
                // people picker once the row is gone.
                var rows = (state.share ? state.share.data.shares : []).map(function (item) { return item.id; });
                var at = rows.indexOf(share.id);
                var next = rows[at + 1] || rows[at - 1] || 0;

                shareRequest('ws_flow_share_remove', { share_id: share.id }, next ? 'remove-' + next : 'add-people').then(function (answer) {
                    if (answer) {
                        K.toast(t('fl_share_removed', name), 'success');
                        announce(t('fl_share_removed', name));
                    }
                });
            });
            row.appendChild(remove);
        }

        return row;
    }

    // The dialog's body: what sharing does, whom the flow is shared with
    // (its owner first), and for the owner the people and the channel to
    // share it with next.
    function drawShares(focus) {
        var share = state.share;
        var body = ui.shareModal ? ui.shareModal.querySelector('.modal-body') : null;

        if (!share || !body) {
            return;
        }

        var data = share.data || {};
        var manage = !!data.manage;
        var shares = data.shares || [];
        var owner = person(data.owner_id);

        K.clear(body);
        body.appendChild(el('p', 'small text-body-secondary', manage ? t('fl_share_help') : t('fl_share_read_help')));
        body.appendChild(el('div', 'wsf-side-title', t('fl_share_list')));

        var list = el('ul', 'list-unstyled wsf-share-list mb-3');
        var first = el('li', 'wsf-share-row');
        var who = el('span', 'wsf-share-who');

        who.appendChild(owner ? K.avatar(owner, '1.5rem') : K.icon('bi-person-circle'));
        who.appendChild(el('span', 'text-truncate', owner ? owner.name : '#' + data.owner_id));
        first.appendChild(who);
        first.appendChild(el('span', 'wsf-share-level small text-body-secondary', t('fl_share_owner')));
        list.appendChild(first);

        shares.forEach(function (item) {
            list.appendChild(shareRow(item, manage));
        });

        body.appendChild(list);

        if (!shares.length) {
            body.appendChild(el('p', 'small text-body-secondary', t('fl_share_none')));
        }

        if (manage) {
            body.appendChild(sharePeople(shares, data.owner_id));
            body.appendChild(shareChannel(shares));
        }

        if (focus) {
            var target = body.querySelector('[data-share-focus="' + focus + '"]');

            if (target) {
                target.focus();
            }
        }
    }

    // People to share the flow with: the note share's people picker, the
    // team but the owner and the people it is shared with already, and the
    // level they all get.
    function sharePeople(shares, ownerId) {
        var box = el('div', 'wsf-share-add mb-3');
        var taken = {};

        shares.forEach(function (item) {
            if (item.target_type === 'user') {
                taken[item.target_id] = true;
            }
        });

        var others = (BOOT.people || []).map(function (member) { return member.id; }).filter(function (id) {
            return (id !== ownerId) && !taken[id];
        });

        box.appendChild(el('div', 'wsf-side-title', t('fl_share_people')));

        if (!others.length || !K.peoplePicker) {
            return box;
        }

        var picker = K.peoplePicker([], others);
        var row = el('div', 'd-flex flex-wrap align-items-center gap-2 mt-2');
        var level = K.select(Object.keys(LEVELS).map(function (key) { return [key, t(LEVELS[key])]; }), 'run');
        var add = K.button('btn btn-sm btn-primary rounded-pill px-3', t('fl_share'), 'bi-share');

        picker.setAttribute('role', 'group');
        picker.setAttribute('aria-label', t('fl_share_people'));
        level.classList.add('w-auto');
        level.setAttribute('aria-label', t('fl_share_level'));
        add.setAttribute('data-share-focus', 'add-people');
        add.addEventListener('click', function () {
            var ids = picker.value();
            var chosen = level.value;
            var names = [];

            if (!ids.length) {
                K.toast(t('fl_share_pick'), 'warning');
                return;
            }

            // One share a person, one after another: the answer to the last
            // carries the list with all of them.
            ids.reduce(function (chain, id) {
                return chain.then(function (ok) {
                    if (!ok) {
                        return false;
                    }

                    return shareRequest('ws_flow_share_set', { target_type: 'user', target_id: id, level: chosen }, 'add-people').then(function (answer) {
                        if (answer) {
                            names.push(person(id) ? person(id).name : '#' + id);
                        }

                        return !!answer;
                    });
                });
            }, Promise.resolve(true)).then(function () {
                if (names.length) {
                    K.toast(t('fl_share_added', names.join(', ')), 'success');
                    announce(t('fl_share_added', names.join(', ')));
                }
            });
        });

        box.appendChild(picker);
        row.appendChild(level);
        row.appendChild(add);
        box.appendChild(row);

        return box;
    }

    // A channel to share the flow in, for its readers to look at: the public
    // and private channels the person is in or may read, as the note share
    // offers them, but the ones it is shared in already.
    function shareChannel(shares) {
        var box = el('div', 'wsf-share-add');
        var taken = {};

        shares.forEach(function (item) {
            if (item.target_type === 'channel') {
                taken[item.target_id] = true;
            }
        });

        var channels = (BOOT.channels || []).filter(function (channel) {
            return ((channel.kind === 'public') || (channel.kind === 'private')) && !channel.archived
                && (channel.joined || (channel.kind === 'public')) && !taken[channel.id];
        });

        box.appendChild(el('div', 'wsf-side-title', t('fl_share_channel')));

        if (!channels.length) {
            return box;
        }

        var row = el('div', 'd-flex flex-wrap align-items-center gap-2');
        var choice = K.select(channels.map(function (channel) {
            return [channel.id, (channel.kind === 'private' ? '🔒 ' : '#') + channel.name];
        }), channels[0].id);
        var add = K.button('btn btn-sm btn-outline-secondary', t('fl_share'), 'bi-hash');

        choice.classList.add('w-auto', 'mw-100');
        choice.setAttribute('aria-label', t('fl_share_channel'));
        row.appendChild(choice);
        row.appendChild(el('span', 'small text-body-secondary', t(LEVELS.view)));
        add.setAttribute('data-share-focus', 'add-channel');
        add.addEventListener('click', function () {
            var id = parseInt(choice.value, 10) || 0;
            var picked = channels.filter(function (channel) { return channel.id === id; })[0];

            if (!id) {
                return;
            }

            shareRequest('ws_flow_share_set', { target_type: 'channel', target_id: id, level: 'view' }, 'add-channel').then(function (answer) {
                if (answer) {
                    K.toast(t('fl_share_added', '#' + (picked ? picked.name : id)), 'success');
                    announce(t('fl_share_added', '#' + (picked ? picked.name : id)));
                }
            });
        });
        row.appendChild(add);
        box.appendChild(row);

        return box;
    }

    // ── Runs ───────────────────────────────────────────────────────────

    function runOpen(run) {
        return !!(run && ((run.status === 'running') || (run.status === 'waiting')));
    }

    // The box of the run shown; a later pass of a box stands for it.
    function stepOf(id) {
        return state.run ? ((state.run.steps || []).filter(function (step) { return step.node_id === id; }).pop() || null) : null;
    }

    function runWord(status) {
        return RUN_STATES[status] ? t(RUN_STATES[status][0]) : String(status || '');
    }

    function stepWord(status) {
        return STEP_STATES[status] ? t(STEP_STATES[status][0]) : String(status || '');
    }

    // A state as a mark and a word in its tone: never the colour alone.
    function runBadge(states, status) {
        var known = states[status];
        var badge = el('span', 'wsf-run-badge wsf-run-' + (known ? status : 'none'));

        badge.appendChild(K.icon(known ? known[1] : 'bi-question-circle'));
        badge.appendChild(document.createTextNode(known ? t(known[0]) : String(status || '')));

        return badge;
    }

    // The task a box opened: a link to it on the tasks screen, which opens
    // the Workspace's task drawer in place; a change made there reads the
    // run again at once (the task done moves the run on).
    function taskLink(step, node, className, key) {
        var link = el('a', className, t('fl_run_task'));

        link.href = ((CFG.urls && CFG.urls.tasks) || 'workspace_tasks.php') + '?task=' + step.task_id;
        link.setAttribute('aria-label', t('fl_run_task_of', nameOf(node)));
        link.setAttribute('data-refocus', key);
        link.addEventListener('click', function (event) {
            if (event.button || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || !K.openTask) {
                return;
            }

            event.preventDefault();
            K.openTask(step.task_id, null, function () {
                tick(true);
            });
        });

        return link;
    }

    function narrow() {
        return !!(window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches);
    }

    // Draws part of the screen again while the person may be at work in it:
    // a control that went with the drawing hands the focus to the one drawn
    // in its place (its data-refocus name), or to its box (on the canvas or
    // in the outline); in a run's side panel or outline, to the box shown or
    // the run's title, so a control the run no longer has (Cancel once it
    // ended, a task link once the box moved on) never drops the focus to the
    // page. A focus the caller asked for (state.refocus) comes first.
    function keepFocus(draw) {
        var active = document.activeElement;
        var wanted = state.refocus;
        var keys = [];

        if (active && (active !== document.body) && ui.root.contains(active)) {
            var box = active.closest('.wsf-node[data-node]');
            var item = active.closest('.wsf-outline-item[data-node]');

            if (active.getAttribute('data-refocus')) {
                keys.push(active.getAttribute('data-refocus'));
            }

            if (box) {
                keys.push('box-' + box.getAttribute('data-node'));
            }

            if (state.run && item) {
                keys.push('outline-box-' + item.getAttribute('data-node'));
            }

            if (state.run && (ui.side.contains(active) || ui.outlineBox.contains(active))) {
                if (active.closest('.wsf-run-step')) {
                    keys.push('step');
                }

                keys.push('run-title');
            }

            // A control of the bar over the canvas that went with the
            // drawing (Try again once the run is read again) hands the
            // focus to the bar's Close.
            if (state.run && ui.runBox.contains(active)) {
                keys.push('run-close');
            }
        }

        state.refocus = '';
        draw();

        var lost = !!active && (!document.activeElement || (document.activeElement === document.body) || !document.body.contains(active));

        state.refocus = wanted || (lost ? keys.join(' ') : '');
        refocus();
    }

    // "Flow runs" pressed: the list in the side panel, or back from it (and
    // from a run shown) to the flow. The list hidden by a selection is
    // shown again: one press always does what the button's state says.
    function toggleRuns() {
        state.openSeq++;

        if (state.run) {
            state.refocus = 'runs';
            closeRun('');
            return;
        }

        state.panel = runsShown() ? '' : 'runs';

        if (state.panel) {
            state.selected = null;
            markSelected();
            loadRuns(false);
        }

        // On a phone the side panel is under the canvas: its title takes the
        // focus, which brings the list into sight.
        state.refocus = (state.panel && narrow()) ? 'runs-title runs' : 'runs';
        drawBar();
        drawSettings();
    }

    // The runs of the flow, newest first, a page at a time; more: the next
    // page after those listed.
    function loadRuns(more) {
        var flow = state.flow;
        var list = state.runs;

        if (!flow || list.loading) {
            return;
        }

        if (list.flow !== flow.id) {
            state.runs = list = { flow: flow.id, items: [], next: null, loading: false, error: '' };
        }

        list.loading = true;
        list.error = '';

        K.api('ws_flow_runs_list', { flow_id: flow.id, before_id: more ? (list.next || 0) : 0 }).then(function (data) {
            var runs = data.runs || [];

            list.items = more ? list.items.concat(runs) : runs;
            list.next = data.next || null;

            // The older page's first run takes the focus from the button
            // that brought it, which may be gone.
            if (more && runs.length) {
                state.refocus = 'run-' + runs[0].id;
            }
        }).catch(function (error) {
            // Kept for the side panel, which says it in place of "has not
            // run yet" while nothing is listed (runsSide()).
            list.error = (error && error.message) || t('error_generic');
            K.fail(error);
        }).then(function () {
            list.loading = false;

            if ((state.runs === list) && (state.panel === 'runs') && !state.run) {
                keepFocus(drawSettings);
            }
        });
    }

    // A run read or started: its line in the list follows it.
    function rememberRun(run) {
        var list = state.runs;
        var brief = {};
        var found = false;

        if (!state.flow || (list.flow !== run.flow_id)) {
            return;
        }

        Object.keys(run).forEach(function (key) {
            brief[key] = ((key === 'steps') || (key === 'graph')) ? null : run[key];
        });

        list.items = list.items.map(function (item) {
            if (item.id === run.id) {
                found = true;
                return brief;
            }

            return item;
        });

        if (!found) {
            list.items.unshift(brief);
            list.items.sort(function (a, b) { return b.id - a.id; });
        }
    }

    function runsSide() {
        var list = state.runs;
        var head = el('div', 'wsf-side-title', t('fl_runs_title'));

        head.id = 'wsf-runs-title';
        head.tabIndex = -1;
        head.setAttribute('data-refocus', 'runs-title');
        ui.side.appendChild(head);

        // A load that failed is not "has not run yet": the reason, and a
        // way to load the list again. The redraw shows "Loading…" at once,
        // which loadRuns() itself does not draw.
        if (!list.items.length && list.error && !list.loading) {
            var failed = el('p', 'small text-danger', t('fl_runs_failed') + ' ' + list.error);
            var retry = K.button('btn btn-sm btn-link p-0 ms-1 align-baseline', t('fl_retry'));

            retry.setAttribute('data-refocus', 'runs-retry');
            retry.addEventListener('click', function () {
                loadRuns(false);
                state.refocus = 'runs-title';
                drawSettings();
            });
            failed.appendChild(retry);
            ui.side.appendChild(failed);
            return;
        }

        if (!list.items.length) {
            ui.side.appendChild(el('p', 'small text-body-secondary', list.loading ? t('loading') : t('fl_runs_none')));
            return;
        }

        var rows = el('ul', 'list-unstyled mb-2 wsf-run-list');

        rows.setAttribute('aria-labelledby', head.id);

        list.items.forEach(function (run) {
            var item = el('button', 'wsf-run-item');
            var top = el('span', 'wsf-run-item-top');

            item.type = 'button';
            item.disabled = !!state.opening[run.id];
            item.setAttribute('data-refocus', 'run-' + run.id);
            top.appendChild(runBadge(RUN_STATES, run.status));
            top.appendChild(el('span', 'fw-semibold', t('fl_run_name', run.id)));
            top.appendChild(el('span', 'ms-auto small text-body-secondary', t('fl_run_version', run.version)));
            item.appendChild(top);
            item.appendChild(el('span', 'wsf-run-item-line', t('fl_run_by', personName(run.started_by))));
            item.appendChild(el('span', 'wsf-run-item-line', t('fl_run_at', when(run.started_at))));
            item.appendChild(el('span', 'wsf-run-item-line', run.finished_at ? t('fl_run_ended', when(run.finished_at)) : t('fl_run_open')));
            item.addEventListener('click', function () {
                openRun(run.id);
            });
            rows.appendChild(el('li')).appendChild(item);
        });

        ui.side.appendChild(rows);

        if (list.next) {
            var more = K.button('btn btn-sm btn-ghost', t('fl_runs_more'), 'bi-chevron-down');

            more.setAttribute('data-refocus', 'runs-more');
            more.disabled = list.loading;
            more.addEventListener('click', function () {
                more.disabled = true;
                loadRuns(true);
            });
            ui.side.appendChild(more);
        }
    }

    // A run from the list, read with its boxes, then shown: only while it is
    // still the one asked for last and the list is still open. Its button
    // is disabled while the request runs, drawn so again if the list is
    // redrawn meanwhile (runsSide()).
    function openRun(id) {
        var seq = ++state.openSeq;
        var busy = function (on) {
            var item = ui.side.querySelector('.wsf-run-item[data-refocus="run-' + id + '"]');

            if (on) {
                state.opening[id] = true;
            } else {
                delete state.opening[id];
            }

            if (item) {
                item.disabled = on;
            }
        };

        busy(true);

        return K.api('ws_flow_run_get', { run_id: id }).then(function (data) {
            busy(false);

            if ((seq !== state.openSeq) || state.run || !runsShown()) {
                return;
            }

            state.refocus = 'run-title';
            showRun(data.run, !!data.due);
        }).catch(function (error) {
            busy(false);

            if (seq === state.openSeq) {
                state.refocus = 'run-' + id;
                refocus();
                K.fail(error);
            }
        });
    }

    // The run on the canvas: the graph of its version, read-only, with how
    // each box went. The draft, the selection and the view are put aside;
    // the draft goes on being saved while it waits (payload()).
    function showRun(run, due) {
        if (!run || !state.flow || (run.flow_id !== state.flow.id)) {
            return;
        }

        state.openSeq++;

        if (!state.stash) {
            state.stash = { graph: state.graph, selected: state.selected, view: { x: state.view.x, y: state.view.y, k: state.view.k }, edit: state.edit };
        }

        stopPoll();
        state.run = run;
        state.poll.note = null;
        state.edit = false;
        state.graph = graphOf(run.graph);
        state.selected = null;
        state.panel = 'runs';
        state.poll.delay = POLL_FIRST;
        rememberRun(run);
        ui.app.classList.add('wsf-run-view', 'wsf-readonly');
        cancelDrag();
        drawRunBar();
        drawBar();
        drawAll();
        fit();
        announce(t('fl_run_pair', t('fl_run_name', run.id), runWord(run.status)));

        if (due) {
            tick(false);
        }

        schedulePoll();
    }

    // The run view ends without drawing anything (another flow is shown).
    function endRun() {
        stopPoll();
        state.run = null;
        state.poll.note = null;
        state.stash = null;

        if (ui.app) {
            ui.app.classList.remove('wsf-run-view');
            ui.runBox.classList.add('d-none');
        }
    }

    // Back from a run to the flow: the draft, the selection, the view and the
    // editing tools as they were; panel: 'runs' keeps the list open.
    function closeRun(panel) {
        var stash = state.stash;

        state.openSeq++;
        endRun();

        // The list asked for shows in the side panel only with nothing
        // selected; back to the flow, the box selected before comes back.
        if (stash) {
            state.graph = stash.graph;
            state.selected = panel ? null : stash.selected;
            state.edit = stash.edit;
        }

        state.panel = panel;
        ui.app.classList.toggle('wsf-readonly', !state.edit);
        drawBar();
        drawAll();

        if (stash) {
            state.view = stash.view;
            applyView();
        } else {
            fit();
        }

        if (panel) {
            loadRuns(false);
        }
    }

    // The bar over the canvas while a run is shown.
    function drawRunBar() {
        var run = state.run;

        K.clear(ui.runBox);
        ui.runBox.classList.toggle('d-none', !run);

        if (!run) {
            return;
        }

        var close = K.button('btn btn-sm btn-outline-secondary', t('fl_run_close'), 'bi-x-lg');

        ui.runBox.appendChild(runBadge(RUN_STATES, run.status));
        ui.runBox.appendChild(el('span', 'flex-grow-1', t('fl_run_shown', run.id, run.version)));
        close.setAttribute('data-refocus', 'run-close');
        close.addEventListener('click', function () {
            state.refocus = 'runs';
            closeRun('');
        });
        ui.runBox.appendChild(close);

        // Why the run is not being read (pollFailed()), on a line of its own
        // under the bar: the server's refusal, after which what is shown is
        // the run as last read, or a look that got no answer, tried again
        // on its own or at once from here.
        var note = state.poll.note;

        if (!note) {
            return;
        }

        var line = el('div', 'w-100 small ' + (note.lost ? 'text-danger' : 'text-body-secondary'), note.lost ? (note.text + ' ' + t('fl_run_lost')) : note.text);

        if (!note.lost) {
            var retry = K.button('btn btn-sm btn-link p-0 ms-1 align-baseline', t('fl_retry'));

            retry.setAttribute('data-refocus', 'run-retry');
            retry.addEventListener('click', function () {
                state.poll.delay = POLL_FIRST;
                schedulePoll(0);
            });
            line.appendChild(retry);
        }

        ui.runBox.appendChild(line);
    }

    // The side panel of a run: the run, then the box selected or its boxes.
    function runSide() {
        var run = state.run;
        var head = el('div', 'wsf-side-title', t('fl_run_name', run.id));
        var facts = el('ul', 'list-unstyled small text-body-secondary my-2 wsf-run-facts');
        var tools = el('div', 'd-flex flex-wrap gap-2 mb-3');
        // A run the server no longer answers for (pollFailed()) is not said
        // to be still going: it shows as it was last read.
        var lost = !!(state.poll.note && state.poll.note.lost);

        head.tabIndex = -1;
        head.setAttribute('data-refocus', 'run-title');
        ui.side.appendChild(head);
        ui.side.appendChild(runBadge(RUN_STATES, run.status));

        [t('fl_run_version', run.version), t('fl_run_by', personName(run.started_by)), t('fl_run_at', when(run.started_at)),
            run.finished_at ? t('fl_run_ended', when(run.finished_at)) : (lost ? t('fl_run_lost') : t('fl_run_open'))].forEach(function (text) {
            facts.appendChild(el('li', '', text));
        });
        ui.side.appendChild(facts);

        if (run.can_cancel) {
            var cancel = K.button('btn btn-sm btn-outline-warning', t('fl_run_cancel'), 'bi-x-circle');

            cancel.setAttribute('data-refocus', 'run-cancel');
            cancel.addEventListener('click', function () { cancelRun(cancel); });
            tools.appendChild(cancel);
        }

        var all = K.button('btn btn-sm btn-ghost', t('fl_run_all'), 'bi-clock-history');

        all.setAttribute('data-refocus', 'run-all');
        all.addEventListener('click', function () {
            state.refocus = 'runs-title';
            closeRun('runs');
        });
        tools.appendChild(all);
        ui.side.appendChild(tools);

        // The outline beside it says how every box went already.
        if (state.outline) {
            return;
        }

        var selected = (state.selected && (state.selected.type === 'node')) ? nodeById(state.selected.id) : null;

        ui.side.appendChild(selected ? stepDetail(selected) : stepList());
    }

    // How one box went in the run.
    function stepDetail(node) {
        var step = stepOf(node.id);
        var wrap = el('div', 'wsf-run-step');
        var title = el('div', 'wsf-side-title', t(kindOf(node).name));
        var facts = el('ul', 'list-unstyled small text-body-secondary my-2 wsf-run-facts');

        title.tabIndex = -1;
        title.setAttribute('data-refocus', 'step');
        wrap.appendChild(title);
        wrap.appendChild(el('div', 'fw-semibold mb-1', nameOf(node)));
        wrap.appendChild(runBadge(STEP_STATES, step ? step.status : 'none'));

        [metaOf(node), (step && step.started_at) ? t('fl_run_at', when(step.started_at)) : '', (step && step.finished_at) ? t('fl_run_ended', when(step.finished_at)) : ''].forEach(function (text) {
            if (text) {
                facts.appendChild(el('li', '', text));
            }
        });
        wrap.appendChild(facts);

        if (step && step.result) {
            wrap.appendChild(el('div', 'form-label small fw-semibold mb-1', t('fl_run_result')));
            wrap.appendChild(el('p', 'small wsf-run-result', step.result));
        }

        if (step && step.task_id) {
            wrap.appendChild(taskLink(step, node, 'btn btn-sm btn-outline-secondary', 'step-task'));
        }

        // A failed box (one that stopped half way too) is tried again or
        // skipped by the flow's owner, in whose name it runs; a run done or
        // cancelled takes neither.
        if (state.flow && state.flow.manage && step && (step.status === 'failed') && (['done', 'cancelled'].indexOf(state.run.status) < 0)) {
            var mend = el('div', 'd-flex flex-wrap gap-2 mt-2');
            var retry = K.button('btn btn-sm btn-outline-primary', t('fl_step_retry'), 'bi-arrow-repeat');
            var skip = K.button('btn btn-sm btn-outline-secondary', t('fl_step_skip'), 'bi-skip-forward');

            retry.setAttribute('data-refocus', 'step-retry');
            skip.setAttribute('data-refocus', 'step-skip');
            retry.addEventListener('click', function () { mendStep(node, 'retry', [retry, skip]); });
            skip.addEventListener('click', function () { mendStep(node, 'skip', [retry, skip]); });
            mend.appendChild(retry);
            mend.appendChild(skip);
            wrap.appendChild(mend);
        }

        return wrap;
    }

    // Tries a failed box again or skips it, after asking: a box tried again
    // may post or send once more. Both buttons wait for the answer, and the
    // server changes the box only from failed, so a second click does
    // nothing twice.
    function mendStep(node, how, controls) {
        var run = state.run;

        K.ask(t(how === 'retry' ? 'fl_step_retry_ask' : 'fl_step_skip_ask', nameOf(node)), t(how === 'retry' ? 'fl_step_retry' : 'fl_step_skip'), how === 'skip').then(function (yes) {
            if (!yes || !state.run || (state.run.id !== run.id)) {
                state.refocus = 'step-' + how + ' step';
                refocus();
                return null;
            }

            controls.forEach(function (control) { control.disabled = true; });

            return K.api('ws_flow_step_mend', { run_id: run.id, node_id: node.id, how: how }).then(function (data) {
                K.toast(t(how === 'retry' ? 'fl_step_retried' : 'fl_step_skipped_by_hand'), 'success');
                state.refocus = 'step';
                applyRun(data.run);
                refocus();
                state.poll.delay = POLL_FIRST;
                schedulePoll();
            }).catch(function (error) {
                controls.forEach(function (control) { control.disabled = false; });
                K.fail(error);

                // Changed meanwhile, most likely: the run as it is now.
                state.poll.delay = POLL_FIRST;
                schedulePoll(0);
            });
        });
    }

    // The boxes of the run in the order of their arrows, each with its state;
    // one picked is selected on the canvas.
    function stepList() {
        var wrap = el('div');
        var list = el('ul', 'list-unstyled wsf-run-steps');

        wrap.appendChild(el('p', 'small text-body-secondary mb-2', t('fl_run_pick')));
        list.setAttribute('aria-label', t('fl_run_boxes'));

        orderedNodes().forEach(function (node) {
            var step = stepOf(node.id);
            var row = K.button('wsf-run-step-row', '', kindOf(node).icon);

            row.setAttribute('data-refocus', 'run-step-' + node.id);
            row.appendChild(el('span', 'wsf-run-step-name', nameOf(node)));
            row.appendChild(runBadge(STEP_STATES, step ? step.status : 'none'));
            row.addEventListener('click', function () {
                select({ type: 'node', id: node.id }, { reveal: true, focus: true });
            });
            list.appendChild(el('li')).appendChild(row);
        });

        wrap.appendChild(list);

        return wrap;
    }

    // A box of the run in the outline: its state, what it does, its result
    // and the link to a waiting box's task, all in words.
    function outlineRun(node, step) {
        var box = el('div', 'wsf-run-outline');
        var meta = metaOf(node);

        box.appendChild(el('p', 'mb-1')).appendChild(runBadge(STEP_STATES, step ? step.status : 'none'));

        if (meta) {
            box.appendChild(el('p', 'small text-body-secondary mb-1', meta));
        }

        if (step && step.result) {
            box.appendChild(el('p', 'small mb-1 wsf-run-result', step.result));
        }

        if (step && (step.status === 'waiting') && step.task_id) {
            box.appendChild(taskLink(step, node, 'btn btn-sm btn-outline-secondary', 'run-otask-' + node.id));
        }

        return box;
    }

    // The run read again drawn where it shows: the boxes in place, the
    // arrows, the outline, the side panel and the bar over the canvas.
    function drawRunViews() {
        Array.prototype.forEach.call(ui.boxes.querySelectorAll('.wsf-node[data-node]'), function (box) {
            var node = nodeById(box.getAttribute('data-node'));

            if (node) {
                fillBox(box, node);
            }
        });

        drawEdges();
        drawRunBar();
        drawOutline();
        drawSettings();
    }

    // A run read again: what changed is drawn, and told to screen readers
    // in a few words. Answers whether anything changed.
    function applyRun(run) {
        var before = state.run;
        var told = [];

        if (!before || !run || (before.id !== run.id)) {
            return false;
        }

        (run.steps || []).forEach(function (step) {
            var was = (before.steps || []).filter(function (item) { return item.node_id === step.node_id; }).pop();
            var node = nodeById(step.node_id);

            if (!was || (was.status !== step.status)) {
                told.push(t('fl_run_pair', node ? nameOf(node) : step.name, stepWord(step.status)));
            }
        });

        if (before.status !== run.status) {
            told.push(t('fl_run_pair', t('fl_run_name', run.id), runWord(run.status)));
        }

        var changed = !!told.length || (before.can_cancel !== run.can_cancel) || (JSON.stringify(before.steps) !== JSON.stringify(run.steps));

        state.run = run;
        rememberRun(run);

        if (changed) {
            keepFocus(drawRunViews);
        }

        if (told.length) {
            announce(told.join('. '));
        }

        if (!runOpen(run)) {
            stopPoll();
        }

        return changed;
    }

    function stopPoll() {
        window.clearTimeout(state.poll.timer);
        state.poll.timer = 0;
    }

    // The next look at the run shown, none once it ended, once the server
    // refused it (pollFailed()) or while the tab is hidden (it looks again
    // when shown, bindWindow()).
    function schedulePoll(delay) {
        stopPoll();

        if (!runOpen(state.run) || (state.poll.note && state.poll.note.lost) || (document.visibilityState === 'hidden')) {
            return;
        }

        state.poll.timer = window.setTimeout(pollRun, (delay === undefined) ? state.poll.delay : delay);
    }

    // A look at another run still on its way (the run shown was changed
    // meanwhile) holds nothing up: busy names the run its look is for, and
    // only a look at the same run waits for that one's answer, which
    // schedules the next.
    function pollRun() {
        var run = state.run;

        state.poll.timer = 0;

        if (!runOpen(run) || (state.poll.busy === run.id) || (document.visibilityState === 'hidden')) {
            return;
        }

        state.poll.busy = run.id;

        K.api('ws_flow_run_get', { run_id: run.id }).then(function (data) {
            pollDone(run);

            if (!state.run || (state.run.id !== run.id)) {
                pollResume();
                return;
            }

            // Read again after looks that got no answer: their word goes
            // from the bar, which applyRun() redraws only on a change.
            var noted = !!state.poll.note;

            state.poll.note = null;

            var changed = applyRun(data.run);

            if (noted && !changed) {
                keepFocus(drawRunBar);
            }

            state.poll.delay = changed ? POLL_FIRST : Math.min(POLL_MAX, Math.round(state.poll.delay * POLL_GROW));

            if (data.due) {
                tick(false);
            }

            schedulePoll();
        }).catch(function (error) {
            pollDone(run);

            if (!state.run || (state.run.id !== run.id)) {
                pollResume();
                return;
            }

            pollFailed(error);
        });
    }

    // A look at the run shown that failed. An error api() read from the
    // server's JSON answer carries a code ('' or one): the server refused
    // the run (it went with its binned flow, its channel is no longer read,
    // the session ended or its token changed), and asking again would only
    // be refused again. The run stays as it was last read, says why, offers
    // no Cancel and is not read again. A look that got no answer (the
    // network, a server error page that is not JSON) has no code: it is
    // tried again, waiting twice as long each time, and the bar says so
    // until a look succeeds; told to screen readers once.
    function pollFailed(error) {
        if (error && (error.code !== undefined)) {
            state.poll.note = { lost: true, text: error.message || t('error_generic') };
            state.run.can_cancel = false;
            keepFocus(drawRunViews);
            announce(state.poll.note.text + ' ' + t('fl_run_lost'));
            return;
        }

        var first = !state.poll.note;

        state.poll.note = { lost: false, text: t('fl_run_unreached') };
        state.poll.delay = Math.min(POLL_FAIL_MAX, state.poll.delay * 2);

        if (first) {
            keepFocus(drawRunBar);
            announce(state.poll.note.text);
        }

        schedulePoll();
    }

    // A look at run has come back: busy is cleared only while it still names
    // that run, so a look at another run sent meanwhile keeps its mark.
    function pollDone(run) {
        if (state.poll.busy === run.id) {
            state.poll.busy = 0;
        }
    }

    // A look answered for a run no longer shown: the run shown now keeps
    // being read, should its own next look have been passed over.
    function pollResume() {
        if (!state.poll.timer && (state.poll.busy !== (state.run && state.run.id))) {
            schedulePoll();
        }
    }

    // The screen's tick carries the runs on (ws_scheduled_run()); the run
    // shown is read again once it is back. force: a task of the run was just
    // changed in the drawer, so the gap between two ticks does not hold it.
    function tick(force) {
        var now = Date.now();

        if (!force && (now - state.tickAt < TICK_GAP)) {
            return;
        }

        state.tickAt = now;
        K.api('ws_tick').catch(function () {}).then(function () {
            if (runOpen(state.run)) {
                state.poll.delay = POLL_FIRST;
                schedulePoll(0);
            }
        });
    }

    // A token for one start (ws_flow_start_token_valid(): letters, digits
    // and . _ : -, at most 56).
    function startToken() {
        var random = new Uint8Array(12);

        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(random);
        } else {
            for (var i = 0; i < random.length; i++) {
                random[i] = Math.floor(Math.random() * 256);
            }
        }

        return 'canvas.' + Array.prototype.map.call(random, function (value) { return ('0' + value.toString(16)).slice(-2); }).join('');
    }

    // Run: asked first, then started once. A second click while it is asked
    // or sent does nothing; the token goes with the request and is kept
    // until the server answers it, so a start sent again after an answer was
    // lost gets the run the first one started instead of a second run.
    function startRun() {
        var flow = state.flow;

        if (state.starting || !runnable()) {
            return;
        }

        state.starting = 'ask';

        K.ask(t('fl_run_ask', flow.name, flow.version), t('fl_run_do')).then(function (yes) {
            if (!yes || (state.flow !== flow)) {
                state.starting = false;
                state.refocus = 'run';
                refocus();
                return null;
            }

            if (!state.startToken || (state.startToken.flow !== flow.id)) {
                state.startToken = { flow: flow.id, value: startToken() };
            }

            state.starting = 'send';

            if (ui.runButton) {
                ui.runButton.disabled = true;
            }

            // start_token, not token: K.api() sends the CSRF token as token,
            // and a data key of that name would replace it.
            return K.api('ws_flow_run_start', { flow_id: flow.id, start_token: state.startToken.value }).then(function (data) {
                state.startToken = null;
                state.starting = false;
                K.toast(data.started ? t('fl_run_started') : t('fl_run_again'), 'success');

                if (data.run && (state.flow === flow)) {
                    state.refocus = 'run-title';
                    showRun(data.run, false);
                } else if (ui.runButton) {
                    ui.runButton.disabled = false;
                }
            });
        }).catch(function (error) {
            // An answer of the server ends this start; a request that got
            // none (error.field unset) keeps its token for the next click.
            if (error && (error.field !== undefined)) {
                state.startToken = null;
            }

            state.starting = false;

            if (ui.runButton) {
                ui.runButton.disabled = false;
            }

            K.fail(error);
        });
    }

    function cancelRun(control) {
        var run = state.run;

        K.ask(t('fl_run_cancel_ask'), t('fl_run_cancel'), true).then(function (yes) {
            if (!yes || !state.run || (state.run.id !== run.id)) {
                state.refocus = 'run-cancel run-title';
                refocus();
                return null;
            }

            control.disabled = true;

            return K.api('ws_flow_run_cancel', { run_id: run.id }).then(function (data) {
                K.toast(t('fl_run_cancelled'), 'success');

                if (!data.run) {
                    state.refocus = 'runs-title runs';
                    closeRun('runs');
                    return;
                }

                state.refocus = 'run-title';
                applyRun(data.run);
                refocus();
            }).catch(function (error) {
                control.disabled = false;
                K.fail(error);

                // Ended meanwhile, most likely: the run as it is now.
                state.poll.delay = POLL_FIRST;
                schedulePoll(0);
            });
        });
    }

    // ── Selection and view ─────────────────────────────────────────────

    function select(selected, options) {
        state.selected = selected;
        markSelected();
        drawSettings();

        if (options && options.reveal) {
            if (state.outline) {
                var item = selected && ui.outlineBox.querySelector('[data-node="' + selected.id + '"] input');

                if (item) {
                    item.focus();
                }

                return;
            }

            reveal(selected);
        }

        if (options && options.focus) {
            focusSelected();
        }
    }

    function focusSelected() {
        var selected = state.selected;

        if (!selected || state.outline || (selected.type === 'edge')) {
            return;
        }

        var target = ui.boxes.querySelector('[data-node="' + selected.id + '"]');

        if (target && (document.activeElement !== target)) {
            target.focus({ preventScroll: true });
        }
    }

    // The selected box brought into sight when it is outside the viewport.
    function reveal(selected) {
        var item = null;

        if (selected && (selected.type === 'node')) {
            item = nodeById(selected.id);
        } else if (selected && (selected.type === 'edge') && state.graph.edges[selected.id]) {
            item = nodeById(state.graph.edges[selected.id].from);
        }

        if (!item) {
            return;
        }

        var rect = ui.viewport.getBoundingClientRect();
        var v = state.view;
        var left = item.x * v.k + v.x;
        var top = item.y * v.k + v.y;
        var margin = 32;

        if ((left < margin) || (top < margin) || (left + BOX_W * v.k > rect.width - margin) || (top + BOX_H * v.k > rect.height - margin)) {
            v.x = rect.width / 2 - (item.x + BOX_W / 2) * v.k;
            v.y = rect.height / 2 - (item.y + BOX_H / 2) * v.k;
            applyView();
        }
    }

    function applyView() {
        var v = state.view;
        var dots = GRID * v.k;

        // Zoomed far out, every fourth dot of the grid is drawn.
        while (dots < 8) {
            dots *= 4;
        }

        ui.layer.style.transform = 'translate(' + v.x + 'px, ' + v.y + 'px) scale(' + v.k + ')';
        ui.viewport.style.backgroundSize = dots + 'px ' + dots + 'px';
        ui.viewport.style.backgroundPosition = v.x + 'px ' + v.y + 'px';
        ui.zoomLabel.textContent = Math.round(v.k * 100) + '%';
    }

    function zoomAt(clientX, clientY, factor) {
        var rect = ui.viewport.getBoundingClientRect();
        var v = state.view;
        var k = clamp(v.k * factor, ZOOM_MIN, ZOOM_MAX);
        var px = clientX - rect.left;
        var py = clientY - rect.top;

        v.x = px - (px - v.x) * (k / v.k);
        v.y = py - (py - v.y) * (k / v.k);
        v.k = k;
        applyView();
    }

    function zoomBy(factor) {
        var rect = ui.viewport.getBoundingClientRect();

        zoomAt(rect.left + rect.width / 2, rect.top + rect.height / 2, factor);
    }

    // Everything on the canvas in sight, at most a little larger than life.
    function fit() {
        var items = state.graph ? state.graph.nodes : [];
        var rect = ui.viewport.getBoundingClientRect();

        if (!items.length || !rect.width) {
            state.view = { x: 40, y: 40, k: 1 };
            applyView();
            return;
        }

        var minX = Math.min.apply(null, items.map(function (item) { return item.x; }));
        var minY = Math.min.apply(null, items.map(function (item) { return item.y; }));
        var maxX = Math.max.apply(null, items.map(function (item) { return item.x + BOX_W; }));
        var maxY = Math.max.apply(null, items.map(function (item) { return item.y + BOX_H; }));
        var k = clamp(Math.min((rect.width - 96) / Math.max(1, maxX - minX), (rect.height - 96) / Math.max(1, maxY - minY)), ZOOM_MIN, 1.25);

        state.view = { x: (rect.width - (maxX - minX) * k) / 2 - minX * k, y: (rect.height - (maxY - minY) * k) / 2 - minY * k, k: k };
        applyView();
    }

    function canvasPoint(clientX, clientY) {
        var rect = ui.viewport.getBoundingClientRect();

        return { x: (clientX - rect.left - state.view.x) / state.view.k, y: (clientY - rect.top - state.view.y) / state.view.k };
    }

    // ── Pointer and keyboard ───────────────────────────────────────────

    function bindCanvas() {
        var viewport = ui.viewport;

        viewport.addEventListener('pointerdown', pointerDown);
        viewport.addEventListener('pointermove', pointerMove);
        viewport.addEventListener('pointerup', pointerUp);
        viewport.addEventListener('pointercancel', pointerUp);

        viewport.addEventListener('wheel', function (event) {
            event.preventDefault();

            if (event.ctrlKey || event.metaKey) {
                zoomAt(event.clientX, event.clientY, Math.exp(-event.deltaY * 0.002));
            } else {
                state.view.x -= event.deltaX;
                state.view.y -= event.deltaY;
                applyView();
            }
        }, { passive: false });

        viewport.addEventListener('dragover', function (event) {
            if (state.edit) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
            }
        });

        viewport.addEventListener('drop', function (event) {
            var data = event.dataTransfer.getData('text/plain') || '';

            if (!state.edit || (data.indexOf('wsf:') !== 0)) {
                return;
            }

            event.preventDefault();

            var kind = data.slice(4);
            var point = canvasPoint(event.clientX, event.clientY);

            if (KINDS[kind]) {
                addItem(kind, { x: coord(point.x - BOX_W / 2), y: coord(point.y - BOX_H / 2) });
            }
        });

        viewport.addEventListener('focusin', function (event) {
            var box = event.target.closest('[data-node]');

            if (box && !state.drag) {
                var selected = { type: 'node', id: box.getAttribute('data-node') };

                if (!state.selected || (state.selected.type !== selected.type) || (state.selected.id !== selected.id)) {
                    select(selected);
                }
            }
        });

        viewport.addEventListener('keydown', canvasKey);
    }

    function pointerDown(event) {
        var viewport = ui.viewport;

        state.pointers[event.pointerId] = { x: event.clientX, y: event.clientY };

        // A second finger: the drag so far becomes a pinch.
        if (Object.keys(state.pointers).length === 2) {
            var points = Object.keys(state.pointers).map(function (id) { return state.pointers[id]; });

            cancelDrag();
            state.pinch = {
                d: Math.max(1, Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y)),
                cx: (points[0].x + points[1].x) / 2,
                cy: (points[0].y + points[1].y) / 2,
                view: { x: state.view.x, y: state.view.y, k: state.view.k }
            };
            return;
        }

        if ((event.button !== undefined) && (event.button > 1)) {
            return;
        }

        // A link on a box (the task of a waiting box) keeps its own click:
        // the canvas captures no pointer for it.
        if (event.target.closest && event.target.closest('.wsf-node a[href]')) {
            delete state.pointers[event.pointerId];
            return;
        }

        var target = event.target;
        var box = target.closest('[data-node]');
        var edge = target.closest('.wsf-edge');
        var drag = { id: event.pointerId, sx: event.clientX, sy: event.clientY, moved: false, kind: 'pan', vx: state.view.x, vy: state.view.y };

        if (state.space || (event.button === 1)) {
            drag.kind = 'pan';
        } else if (state.edit && target.closest('.wsf-port-out') && box) {
            drag.kind = 'connect';
            drag.from = nodeById(box.getAttribute('data-node'));
            event.preventDefault();
        } else if (box) {
            drag.kind = state.edit ? 'move' : 'none';
            drag.item = nodeById(box.getAttribute('data-node'));
            drag.ix = drag.item.x;
            drag.iy = drag.item.y;
            drag.node = box;
            state.drag = drag;
            select({ type: 'node', id: drag.item.id });
            box.focus({ preventScroll: true });
        } else if (edge) {
            drag.kind = 'none';
            select({ type: 'edge', id: parseInt(edge.getAttribute('data-edge'), 10) });
            viewport.focus({ preventScroll: true });
        } else {
            drag.kind = 'pan';
            drag.empty = true;
        }

        state.drag = drag;

        try {
            viewport.setPointerCapture(event.pointerId);
        } catch (error) {
            // A pointer that cannot be captured still drags while it stays on the canvas.
        }

        if (drag.kind === 'pan') {
            viewport.classList.add('wsf-panning');
        }
    }

    function pointerMove(event) {
        if (state.pointers[event.pointerId]) {
            state.pointers[event.pointerId] = { x: event.clientX, y: event.clientY };
        }

        if (state.pinch) {
            var points = Object.keys(state.pointers).map(function (id) { return state.pointers[id]; });

            if (points.length < 2) {
                return;
            }

            var pinch = state.pinch;
            var k = clamp(pinch.view.k * Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y) / pinch.d, ZOOM_MIN, ZOOM_MAX);
            var rect = ui.viewport.getBoundingClientRect();
            var cx = (points[0].x + points[1].x) / 2 - rect.left;
            var cy = (points[0].y + points[1].y) / 2 - rect.top;
            var px = (pinch.cx - rect.left - pinch.view.x) / pinch.view.k;
            var py = (pinch.cy - rect.top - pinch.view.y) / pinch.view.k;

            state.view = { x: cx - px * k, y: cy - py * k, k: k };
            applyView();
            return;
        }

        var drag = state.drag;

        if (!drag || (drag.id !== event.pointerId)) {
            return;
        }

        var dx = event.clientX - drag.sx;
        var dy = event.clientY - drag.sy;

        if (!drag.moved && (Math.abs(dx) + Math.abs(dy) < 4)) {
            return;
        }

        drag.moved = true;

        if (drag.kind === 'pan') {
            state.view.x = drag.vx + dx;
            state.view.y = drag.vy + dy;
            applyView();
        } else if (drag.kind === 'move') {
            drag.item.x = coord(drag.ix + dx / state.view.k);
            drag.item.y = coord(drag.iy + dy / state.view.k);
            place(drag.node, drag.item);
            drawEdges();
        } else if (drag.kind === 'connect') {
            var point = canvasPoint(event.clientX, event.clientY);
            var under = document.elementFromPoint(event.clientX, event.clientY);
            var over = under && under.closest ? under.closest('[data-node]') : null;
            var to = over ? nodeById(over.getAttribute('data-node')) : null;

            ui.temp.setAttribute('d', curve(drag.from.x + BOX_W, drag.from.y + BOX_H / 2, point.x, point.y).d);
            ui.temp.classList.remove('d-none');
            Array.prototype.forEach.call(ui.boxes.querySelectorAll('.wsf-target'), function (node) { node.classList.remove('wsf-target'); });

            if (to && canConnect(drag.from, to)) {
                over.classList.add('wsf-target');
            }
        }
    }

    function pointerUp(event) {
        delete state.pointers[event.pointerId];

        if (state.pinch) {
            if (Object.keys(state.pointers).length < 2) {
                state.pinch = null;
            }

            return;
        }

        var drag = state.drag;

        if (!drag || (drag.id !== event.pointerId)) {
            return;
        }

        state.drag = null;
        ui.viewport.classList.remove('wsf-panning');

        if (drag.kind === 'connect') {
            var under = document.elementFromPoint(event.clientX, event.clientY);
            var over = under && under.closest ? under.closest('[data-node]') : null;

            ui.temp.classList.add('d-none');
            Array.prototype.forEach.call(ui.boxes.querySelectorAll('.wsf-target'), function (node) { node.classList.remove('wsf-target'); });

            // Released on a box: the box the arrow leaves is selected,
            // whether or not the arrow may go there (itself, the start, an
            // arrow already drawn), so its highlight and settings show what
            // the keys now act on.
            if (over && (event.type === 'pointerup')) {
                state.selected = { type: 'node', id: drag.from.id };

                if (!connect(drag.from, nodeById(over.getAttribute('data-node')))) {
                    markSelected();
                    drawSettings();
                }

                focusSelected();
            }
        } else if ((drag.kind === 'move') && drag.moved) {
            changed(false);
        } else if (drag.item && !drag.moved) {
            // A second tap on the same box opens its settings (the captured
            // pointer sends no dblclick to the box itself).
            var now = Date.now();

            if (state.tap && (state.tap.item === drag.item) && (now - state.tap.time < 450)) {
                state.tap = null;
                focusField();
            } else {
                state.tap = { item: drag.item, time: now };
            }
        } else if (drag.empty && !drag.moved && state.selected) {
            select(null);
            ui.viewport.focus({ preventScroll: true });
        }
    }

    // A drag cut short (Esc, a second finger): a box being moved goes back
    // to where it was, an arrow being drawn is dropped.
    function cancelDrag() {
        var drag = state.drag;

        state.drag = null;
        ui.temp.classList.add('d-none');
        ui.viewport.classList.remove('wsf-panning');
        Array.prototype.forEach.call(ui.boxes.querySelectorAll('.wsf-target'), function (node) { node.classList.remove('wsf-target'); });

        if (drag && (drag.kind === 'move') && drag.moved) {
            drag.item.x = drag.ix;
            drag.item.y = drag.iy;
            place(drag.node, drag.item);
            drawEdges();
        }
    }

    // Enter: the first field of what is selected; in a run, its task, or
    // else how it went.
    function focusField() {
        if (state.run) {
            state.refocus = 'step-task step';
            refocus();
            return;
        }

        var field = ui.side.querySelector('input, textarea, select');

        if (field && !state.outline) {
            field.focus();
        }
    }

    function canvasKey(event) {
        // A link on a box answers its own keys (Enter follows it).
        if (typing(event.target) || event.altKey || (event.target.closest && event.target.closest('.wsf-node a[href]'))) {
            return;
        }

        var key = event.key;
        var step = event.shiftKey ? GRID * 4 : GRID;
        var selected = state.selected;
        var handled = true;

        if ((key === 'ArrowLeft') || (key === 'ArrowRight') || (key === 'ArrowUp') || (key === 'ArrowDown')) {
            if (selected && (selected.type !== 'edge') && state.edit && !event.ctrlKey && !event.metaKey) {
                moveSelected((key === 'ArrowLeft') ? -step : (key === 'ArrowRight') ? step : 0, (key === 'ArrowUp') ? -step : (key === 'ArrowDown') ? step : 0);
            } else {
                state.view.x -= (key === 'ArrowLeft') ? -step * 2 : (key === 'ArrowRight') ? step * 2 : 0;
                state.view.y -= (key === 'ArrowUp') ? -step * 2 : (key === 'ArrowDown') ? step * 2 : 0;
                applyView();
            }
        } else if ((key === 'Delete') || (key === 'Backspace')) {
            removeSelected();
        } else if (((key === 'c') || (key === 'C')) && !event.ctrlKey && !event.metaKey && selected && (selected.type === 'node')) {
            askConnect(nodeById(selected.id));
        } else if ((key === 'Enter') && selected) {
            focusField();
        } else if ((key === '+') || (key === '=')) {
            zoomBy(1.2);
        } else if ((key === '-') || (key === '_')) {
            zoomBy(1 / 1.2);
        } else if (key === '0') {
            fit();
        } else if ((key === 'Escape') && (state.drag || selected)) {
            cancelDrag();
            select(null);
            ui.viewport.focus({ preventScroll: true });
        } else {
            handled = false;
        }

        if (handled) {
            event.preventDefault();
        }
    }

    function bindWindow() {
        // Space held down on the canvas: dragging anywhere on it moves it.
        // Elsewhere Space keeps its own work (a summary, a button).
        document.addEventListener('keydown', function (event) {
            if ((event.key === ' ') && !typing(event.target) && ui.viewport.contains(event.target)) {
                state.space = true;
                ui.viewport.classList.add('wsf-space');
                event.preventDefault();
            }

            if ((event.ctrlKey || event.metaKey) && ((event.key === 's') || (event.key === 'S')) && drafting()) {
                event.preventDefault();
                save();
            }
        });

        document.addEventListener('keyup', function (event) {
            if (event.key === ' ') {
                state.space = false;
                ui.viewport.classList.remove('wsf-space');
            }
        });

        window.addEventListener('blur', function () {
            if (state.dirty) {
                save();
            }
        });

        // A hidden tab saves what is on its way and stops looking at the run
        // shown; shown again, it looks at once.
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                saveOnLeave(false);
                stopPoll();
            } else if (runOpen(state.run)) {
                state.poll.delay = POLL_FIRST;
                schedulePoll(0);
            }
        });

        window.addEventListener('pagehide', function () {
            saveOnLeave(true);
        });

        window.addEventListener('beforeunload', function (event) {
            if (!drafting() || !state.flow) {
                return;
            }

            // After a failed save or against a newer draft the person is
            // asked first; pagehide sends the last changes if they leave.
            if (!state.saveError && !state.conflict) {
                saveOnLeave(true);
            }

            // Changes that are not on their way (a failed save, a newer
            // draft, a draft too large to send as the page closes) are not
            // lost silently.
            if (state.dirty || state.saveError || state.conflict) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    }

    window.PGWsFlow = { start: start, showAction: showAction };
})();
