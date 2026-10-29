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
    // The label of each kind of template setting (ws_flow_template_setting_types()).
    var SETTINGS = { channel: 'fl_tpl_set_channel', person: 'fl_tpl_set_person', time: 'fl_tpl_set_time', url: 'fl_tpl_set_url', address: 'fl_tpl_set_address' };
    var ZOOM_MIN = 0.25;
    var ZOOM_MAX = 2;
    var SAVE_DELAY = 1500;
    // Browsers refuse a keepalive request body over 64 KiB; the action and
    // the token ride on top of the draft.
    var KEEPALIVE_BYTES = 60000;
    var SVG_NS = 'http://www.w3.org/2000/svg';
    // Undo goes back at most HISTORY_MAX steps; a change that is not
    // structural made within HISTORY_FOLD ms of the one before it (typing,
    // an arrow-key move) folds into that step.
    var HISTORY_MAX = 50;
    var HISTORY_FOLD = 1000;

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

    // What each kind of box is (its line icon is FLOW_ICONS[kind] of
    // workspace.js, K.flowIcon()): its name, its group (start,
    // values, logic, people, message or system, which gives its colour:
    // --wsf-kind of .wsf-group-<group> in backend.src.css), whether an arrow
    // may lead into it, and the outcomes an arrow may leave it on (the first
    // is the one a new arrow takes), as ws_flow_kinds() has them.
    var KINDS = {
        manual: { name: 'fl_kind_manual', group: 'start', into: false, out: ['next'] },
        schedule: { name: 'fl_kind_schedule', group: 'start', into: false, out: ['next'] },
        event: { name: 'fl_kind_event', group: 'start', into: false, out: ['next'] },
        join: { name: 'fl_kind_join', group: 'start', into: false, out: ['next'] },
        row: { name: 'fl_kind_row', group: 'start', into: false, out: ['next'] },
        task: { name: 'fl_kind_task', group: 'people', into: true, out: ['done', 'cancelled', 'timeout'] },
        post: { name: 'fl_kind_post', group: 'message', into: true, out: ['done', 'failed'] },
        email: { name: 'fl_kind_email', group: 'message', into: true, out: ['done', 'failed'] },
        notify: { name: 'fl_kind_notify', group: 'message', into: true, out: ['done', 'failed'] },
        task_open: { name: 'fl_kind_task_open', group: 'people', into: true, out: ['done', 'failed'] },
        webhook: { name: 'fl_kind_webhook', group: 'system', into: true, out: ['done', 'failed'] },
        web_check: { name: 'fl_kind_web_check', group: 'system', into: true, out: ['done', 'failed'] },
        task_digest: { name: 'fl_kind_task_digest', group: 'people', into: true, out: ['done', 'failed'] },
        report: { name: 'fl_kind_report', group: 'message', into: true, out: ['done', 'failed'] },
        checkin: { name: 'fl_kind_checkin', group: 'people', into: true, out: ['done', 'failed'] },
        start_action: { name: 'fl_kind_start_action', group: 'system', into: true, out: ['done', 'failed'] },
        start_flow: { name: 'fl_kind_start_flow', group: 'system', into: true, out: ['done', 'failed'] },
        change: { name: 'fl_kind_change', group: 'system', into: true, out: ['done', 'failed'] },
        lead: { name: 'fl_kind_lead', group: 'people', into: true, out: ['done', 'failed'] },
        mcp: { name: 'fl_kind_mcp', group: 'system', into: true, out: ['done', 'failed'] },
        approval: { name: 'fl_kind_approval', group: 'people', into: true, out: ['approved', 'rejected', 'expired', 'withdrawn', 'failed'] },
        condition: { name: 'fl_kind_condition', group: 'logic', into: true, out: ['yes', 'no'] },
        delay: { name: 'fl_kind_delay', group: 'logic', into: true, out: ['done'] },
        end: { name: 'fl_kind_end', group: 'logic', into: true, out: [] },
        // A value box: no arrows, its values reach the boxes through threads.
        constants: { name: 'fl_kind_constants', group: 'values', into: false, out: [], pure: true },
        value: { name: 'fl_kind_value', group: 'values', into: false, out: [], pure: true }
    };

    // What an arrow is taken on, as its box says it.
    var OUTCOMES = {
        done: 'fl_on_done',
        cancelled: 'fl_on_cancelled',
        failed: 'fl_on_failed',
        yes: 'fl_on_yes',
        no: 'fl_on_no',
        approved: 'fl_on_approved',
        rejected: 'fl_on_rejected',
        expired: 'fl_on_expired',
        withdrawn: 'fl_on_withdrawn',
        timeout: 'fl_on_timeout',
        always: 'fl_on_always'
    };

    // What a share lets its holder do (ws_flow_share_levels()), lowest first.
    var LEVELS = {
        view: 'fl_level_view',
        run: 'fl_level_run',
        edit: 'fl_level_edit'
    };

    // What the palette adds.
    var PALETTE = [
        ['manual', 'fl_add_manual'],
        ['schedule', 'fl_kind_schedule'],
        ['event', 'fl_kind_event'],
        ['join', 'fl_kind_join'],
        ['row', 'fl_kind_row'],
        ['task', 'fl_add_task'],
        ['condition', 'fl_kind_condition'],
        ['delay', 'fl_kind_delay'],
        ['post', 'fl_add_post'],
        ['email', 'fl_add_email'],
        ['notify', 'fl_kind_notify'],
        ['task_open', 'fl_kind_task_open'],
        ['webhook', 'fl_kind_webhook'],
        ['web_check', 'fl_kind_web_check'],
        ['task_digest', 'fl_kind_task_digest'],
        ['report', 'fl_kind_report'],
        ['checkin', 'fl_kind_checkin'],
        ['start_action', 'fl_kind_start_action'],
        ['start_flow', 'fl_kind_start_flow'],
        ['change', 'fl_kind_change'],
        ['lead', 'fl_kind_lead'],
        ['mcp', 'fl_kind_mcp'],
        ['approval', 'fl_kind_approval'],
        ['constants', 'fl_kind_constants'],
        ['value', 'fl_kind_value'],
        ['end', 'fl_add_end']
    ];

    // The flow list's filter chips: every flow, the four ways to start,
    // and the drafts.
    var CHIPS = [
        ['', 'fl_chip_all'],
        ['time', 'fl_chip_time'],
        ['manual', 'fl_chip_manual'],
        ['event', 'fl_chip_event'],
        ['join', 'fl_chip_join'],
        ['draft', 'fl_chip_draft']
    ];

    // The palette's groups in their order, with their headings.
    var PALETTE_GROUPS = [
        ['start', 'fl_group_start'],
        ['values', 'fl_group_values'],
        ['logic', 'fl_group_logic'],
        ['people', 'fl_group_people'],
        ['message', 'fl_group_message'],
        ['system', 'fl_group_system']
    ];

    // The box whose line icon stands for a way to start in the flow list.
    var START_BOXES = { time: 'schedule', manual: 'manual', event: 'event', join: 'join' };

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
        warnings: [],       // what may be published but a reader should know
        selected: null,     // { type: node | edge, id }
        view: { x: 40, y: 40, k: 1 },
        outline: false,
        threads: true,      // the data threads shown (the "Data threads" toggle)
        binding: null,      // a thread on its way: { from, token } until a field takes it
        dotTap: null,       // the last tap on a value dot, for the second
        dirty: false,
        changes: 0,         // counts every change, to tell one made while a request ran
        history: null,      // the open flow's steps for undo and redo (historyOf())
        saving: null,
        saveTimer: 0,
        arriving: 0,        // the timer that opens a placed template's setup panel
        saveError: '',
        conflict: '',
        refocus: '',
        listing: false,     // the flow list shown full width, no flow open
        chip: '',           // the flow list's chip: a way to start, 'draft', '' for all
        query: '',          // what the flow list is searched for
        paletteQuery: '',   // what the palette is searched for
        space: false,
        drag: null,
        pointers: {},
        pinch: null,
        tap: null,
        panel: '',          // 'runs' while the side panel lists the runs
        section: '',        // 'action' while a scheduled action is drawn read-only, 'templates' in the gallery
        galleryInto: null,  // the flow the gallery was opened to fill, null for a new one
        scheduled: null,    // the scheduled actions the list ends with: converted (items), refused (count)
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
        share: null,        // the Share dialog open: { flow, data (ws_flow_shares), busy }
        // A patch of Pinegrap AI opened onto the flow (ws_flow_ai_marks()):
        // { change, boxes: { id: 'added' | 'changed' }, order: [id] }, null
        // when none or the viewer hid its marks.
        marks: null,
        // A flow Pinegrap AI just drafted from a sentence (sketchCard()):
        // { flow, notes }, null once hidden or another flow is shown.
        sketch: null
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
        return KINDS[node.kind] || { name: 'fl_box', into: true, out: [] };
    }

    // The classes of a box (and of its outline entry) that give it its
    // kind's look and its group's colour; an unknown kind has no group.
    function kindClasses(kind) {
        return KINDS[kind] ? 'wsf-node-' + kind + ' wsf-group-' + KINDS[kind].group : 'wsf-node-unknown';
    }

    function person(id) {
        return (BOOT.people || []).filter(function (member) { return member.id === id; })[0] || null;
    }

    function titleOf(node) {
        return ((node.kind === 'task') || (node.kind === 'task_open') || (node.kind === 'approval')) ? (node.config.title || node.label || '') : (node.label || '');
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

    // ── Undo and redo ──────────────────────────────────────────────────

    // The steps of one flow as JSON text of its graph: before each change
    // (past), those undone (future), and the graph as it is (now).
    function historyOf(flow, graph) {
        return { flow: flow, past: [], future: [], now: graph, at: 0, fold: false };
    }

    // The graph after a change; false when it is the same (the flow's name,
    // its home). A change undone cannot be redone once another one is made.
    function historyRecord(history, graph, fold, at) {
        if (graph === history.now) {
            return false;
        }

        if (!(fold && history.fold && ((at - history.at) < HISTORY_FOLD))) {
            history.past.push(history.now);

            if (history.past.length > HISTORY_MAX) {
                history.past.shift();
            }
        }

        history.future = [];
        history.now = graph;
        history.at = at;
        history.fold = fold;

        return true;
    }

    // One step back (or forward again): the graph to show, null when there
    // is none. The change after it starts a step of its own.
    function historyStep(history, back) {
        var from = back ? history.past : history.future;

        if (!from.length) {
            return null;
        }

        (back ? history.future : history.past).push(history.now);
        history.now = from.pop();
        history.fold = false;

        return history.now;
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
            events: steps.events || null,
            tables: steps.tables || [],
            mcp: steps.mcp || []
        };
    }

    // Up to two names, then how many more.
    function peopleText(ids) {
        var people = ids.map(function (id) { return person(id) ? person(id).name : '#' + id; });

        return (people.length > 2) ? t('fl_people_more', people.slice(0, 2).join(', '), people.length - 2) : people.join(', ');
    }

    function metaOf(node) {
        var config = node.config || {};

        if (node.kind === 'constants') {
            var names = (config.rows || []).map(function (row) { return row.key; }).filter(Boolean);

            return names.length ? names.join(', ') : t('fl_constants_none');
        }

        if (node.kind === 'value') {
            return derivedRule(config.rule).label;
        }

        if (node.kind === 'manual') {
            var subject = (FL.subjects || []).filter(function (item) { return item.key === config.subject; })[0];

            return subject ? t('fl_starts_on', subject.label) : t('fl_manual_meta');
        }

        if (node.kind === 'task') {
            var people = (config.assignees || []).map(function (id) { return person(id) ? person(id).name : '#' + id; });
            var who = people.length ? ((people.length > 2) ? t('fl_people_more', people.slice(0, 2).join(', '), people.length - 2) : people.join(', ')) : ruleLabel(config.assign);

            var meta = ((config.due_in === '') || (config.due_in === undefined) || (config.due_in === null)) ? who : t('fl_due_meta', who, config.due_in);

            return (config.timeout > 0) ? t('fl_timeout_meta', meta, config.timeout) : meta;
        }

        if (node.kind === 'post') {
            if (config.channel_id === -1) {
                return t('fl_post_meta_joined');
            }

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

        if (node.kind === 'start_flow') {
            var chained = chainTargets().filter(function (item) { return item.id === config.flow_id; })[0];

            return chained ? chained.name : (config.flow_id ? '#' + config.flow_id : t('fl_chain_none'));
        }

        if (node.kind === 'start_action') {
            var target = stepLists().targets.filter(function (item) { return item.id === config.target_id; })[0];

            return target ? target.name : t('fl_start_none');
        }

        if (node.kind === 'condition') {
            return conditionMeta(config.rule || {}, node);
        }

        if (node.kind === 'delay') {
            return delayText(config);
        }

        if (node.kind === 'approval') {
            var asked = (BOOT.channels || []).filter(function (item) { return item.id === config.channel_id; })[0];

            if (!asked) {
                return t('fl_approval_no_channel');
            }

            return (config.approvers || []).length ? t('fl_post_meta', asked.name) + ' · ' + peopleText(config.approvers) : t('fl_post_meta', asked.name);
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

        if (node.kind === 'row') {
            return rowMeta(config);
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

        if (node.kind === 'mcp') {
            var called = mcpServer(config.server_id);

            if (!config.server_id) {
                return t('fl_mcp_none');
            }

            return (called ? '@' + called.alias : '#' + config.server_id) + (config.tool ? ' · ' + config.tool : '');
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
            root.appendChild(el('div', 'alert theme-warning', t('fl_not_ready')));
            return;
        }

        state.outline = !!(window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches);
        state.threads = threadsShown();
        layout(root);
        bindCanvas();
        bindWindow();

        var asked = parseInt((/[?&]flow=([0-9]+)/.exec(window.location.search) || [])[1], 10) || 0;
        // A box to open with the flow (a draft card's "Edit on the canvas").
        var askedBox = (/[?&]box=([a-z0-9_]+)(&|$)/.exec(window.location.search) || [])[1] || '';
        // A run to open with the flow (a run's card in a channel).
        var askedRun = parseInt((/[?&]run=([0-9]+)/.exec(window.location.search) || [])[1], 10) || 0;

        K.api('ws_flow_list').then(function (data) {
            state.flows = data.flows || [];
            state.scheduled = data.scheduled || null;
            drawList();

            // The flow the address names opens, else the flow list (an old
            // ?view=scheduled address too: the list ends with the scheduled
            // actions); when the one asked for cannot be opened (deleted,
            // out of reach), the list shows instead.
            if (asked) {
                open(asked).then(function () {
                    if (askedBox && state.flow && (state.flow.id === asked) && nodeById(askedBox)) {
                        select({ type: 'node', id: askedBox }, { reveal: true, focus: true });
                    }

                    if (askedRun && state.flow && (state.flow.id === asked)) {
                        openLastRun({ id: askedRun, flow_id: asked });
                    }
                });
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
        ui.bar = el('nav', 'pg-toolbar navigation mb-3 wsf-bar');
        ui.bar.setAttribute('aria-label', t('fl_toolbar'));
        ui.problemBox = el('div', 'wsf-problems d-none');
        ui.problemBox.id = 'wsf-problems';
        // The boxes a patch of Pinegrap AI added or changed, each leading to
        // its box.
        ui.marksBox = el('div', 'wsf-ai-marks d-none');
        ui.marksBox.setAttribute('role', 'region');
        ui.marksBox.setAttribute('aria-label', t('fl_ai_title'));
        // A flow Pinegrap AI drafted from a sentence: check it before
        // publishing, and the model's notes.
        ui.sketchBox = el('div', 'wsf-ai-marks wsf-ai-sketch d-none');
        ui.sketchBox.setAttribute('role', 'region');
        ui.sketchBox.setAttribute('aria-label', t('fl_ai_sketch_region'));
        ui.conflictBox = el('div', 'alert theme-warning d-flex flex-wrap align-items-center gap-3 py-3 d-none');
        // While a run is shown: which, and the way back to the flow.
        ui.runBox = el('div', 'wsf-run-bar d-none');
        ui.app = el('div', 'wsf-app');
        // The palette: the left column of the editor.
        ui.palette = el('aside', 'wsf-palette');
        ui.palette.setAttribute('aria-label', t('fl_palette'));
        ui.main = el('div', 'wsf-main');
        ui.side = el('aside', 'wsf-side');
        ui.side.id = 'wsf-side';
        ui.side.setAttribute('aria-label', t('fl_settings'));
        ui.live = el('div', 'visually-hidden');
        ui.live.setAttribute('aria-live', 'polite');
        // The flow list, full width in place of the editor while no flow
        // is open: a search field, the chips and the table.
        ui.flows = el('section', 'wsf-flows d-none');
        ui.flows.setAttribute('aria-label', t('fl_nav'));

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
        ui.stage.appendChild(ui.zoom);
        ui.outlineBox = el('div', 'wsf-outline d-none');
        ui.outlineBox.setAttribute('role', 'region');
        ui.outlineBox.setAttribute('aria-label', t('fl_outline'));
        // A scheduled action drawn read-only (showAction()), in place of
        // the canvas.
        ui.scheduled = el('div', 'ws-pane ws-sa-view h-100 d-none');
        ui.scheduled.setAttribute('role', 'region');
        ui.scheduled.setAttribute('aria-label', t('sa_title'));
        // The setup panel of a template just placed, over the canvas.
        ui.setup = el('section', 'wsf-setup d-none');
        ui.setup.setAttribute('aria-labelledby', 'wsf-setup-title');
        ui.stage.appendChild(ui.setup);
        ui.main.appendChild(ui.stage);
        ui.main.appendChild(ui.outlineBox);
        ui.main.appendChild(ui.scheduled);
        // The ways to start a flow and the template gallery, in place of
        // the canvas.
        ui.gallery = el('div', 'wsf-gallery d-none');
        ui.gallery.setAttribute('role', 'region');
        ui.gallery.setAttribute('aria-label', t('fl_tpl_title'));
        ui.main.appendChild(ui.gallery);

        ui.app.appendChild(ui.palette);
        ui.app.appendChild(ui.main);
        ui.app.appendChild(ui.side);

        root.appendChild(ui.bar);
        root.appendChild(ui.conflictBox);
        root.appendChild(ui.runBox);
        root.appendChild(ui.problemBox);
        root.appendChild(ui.marksBox);
        root.appendChild(ui.sketchBox);
        root.appendChild(ui.flows);
        root.appendChild(ui.app);
        root.appendChild(ui.live);

        applyView();
    }

    // ── The list ───────────────────────────────────────────────────────

    function drawList() {
        if (state.listing) {
            drawRows();
        }
    }

    // How many flows each chip holds among those the search finds.
    function listCounts(flows, query) {
        var counts = {};

        CHIPS.forEach(function (chip) {
            counts[chip[0]] = flows.filter(function (flow) { return listMatches(flow, chip[0], query); }).length;
        });

        return counts;
    }

    // Whether a flow shows under a chip and a search: its name, its start
    // sentence or its home channel holds every word searched for.
    function listMatches(flow, chip, query) {
        if (chip === 'draft' ? (flow.status !== 'draft') : (chip && (flow.start !== chip))) {
            return false;
        }

        var text = [flow.name, flow.start_text, flow.channel_name].join(' ').toLowerCase();

        return String(query || '').toLowerCase().split(/\s+/).every(function (word) {
            return !word || (text.indexOf(word) !== -1);
        });
    }

    // How a flow starts in the list: its sentence, else its kind's name.
    function startSentence(flow) {
        if (flow.start_text) {
            return flow.start_text;
        }

        return START_BOXES[flow.start] ? t(KINDS[START_BOXES[flow.start]].name) : t('fl_list_no_start');
    }

    // The flow list in place of the editor: a search field, the chips (a
    // toolbar of toggle buttons, each count in its name) and the table.
    function drawFlows() {
        K.clear(ui.flows);

        var tools = el('div', 'wsf-flows-tools');
        var search = el('input', 'form-control form-control-sm wsf-flows-search');

        search.type = 'search';
        search.value = state.query;
        search.setAttribute('aria-label', t('fl_list_search'));
        search.placeholder = t('fl_list_search');
        search.setAttribute('data-refocus', 'flows-search');
        search.addEventListener('input', function () {
            state.query = search.value;
            drawRows();
        });
        tools.appendChild(search);
        ui.chips = el('div', 'wsf-chips');
        ui.chips.setAttribute('role', 'toolbar');
        ui.chips.setAttribute('aria-label', t('fl_list_filter'));
        tools.appendChild(ui.chips);
        ui.flows.appendChild(tools);

        var table = el('table', 'table wsf-flow-table');
        var head = el('tr');

        table.appendChild(el('caption', 'visually-hidden', t('fl_nav')));
        ['fl_col_flow', 'fl_col_start', 'fl_col_state', 'fl_flag_last', 'fl_col_channel'].forEach(function (key) {
            var cell = el('th', '', t(key));

            cell.setAttribute('scope', 'col');
            head.appendChild(cell);
        });
        table.appendChild(el('thead')).appendChild(head);
        ui.rows = el('tbody');
        table.appendChild(ui.rows);
        ui.flows.appendChild(table);
        ui.flowsNone = el('p', 'small fg-secondary wsf-flows-none d-none');
        ui.flows.appendChild(ui.flowsNone);

        // Staff's scheduled actions: the converted ones, then the ones
        // the conversion refused.
        ui.scheduledGroups = el('div', 'wsf-sched-groups');
        ui.flows.appendChild(ui.scheduledGroups);
        drawRows();
        drawScheduled();
    }

    // The groups the flow list ends with (ws_flows_scheduled_rows()):
    // "Converted from scheduled actions", each row with its when and step,
    // the converted mark, Open flow, Run now and History; then "Not
    // converted", the cards of the scheduled actions (scheduledList() in
    // workspace.js, which lists only those rows here). A group with no
    // rows is not shown.
    function drawScheduled() {
        var groups = state.scheduled;

        K.clear(ui.scheduledGroups);

        if (!groups) {
            return;
        }

        if ((groups.converted || []).length) {
            var converted = el('section', 'wsf-sched-group');
            var list = el('ul', 'list-unstyled wsf-sched-list');

            converted.setAttribute('aria-labelledby', 'wsf-sched-converted');
            converted.appendChild(el('h2', 'h6 wsf-sched-title', t('fl_conv_title', groups.converted.length))).id = 'wsf-sched-converted';
            groups.converted.forEach(function (item) {
                list.appendChild(convertedRow(item));
            });
            converted.appendChild(list);
            ui.scheduledGroups.appendChild(converted);
        }

        if (groups.refused > 0) {
            var refused = el('section', 'wsf-sched-group wsf-sched-refused');
            var cards = el('div');

            refused.setAttribute('aria-labelledby', 'wsf-sched-refused');
            var title = el('h2', 'h6 wsf-sched-title', t('fl_refused_title', groups.refused));

            // Back from an action drawn on the canvas, the group's heading
            // takes the focus.
            title.id = 'wsf-sched-refused';
            title.tabIndex = -1;
            title.setAttribute('data-refocus', 'sched-refused');
            refused.appendChild(title);
            refused.appendChild(el('p', 'small fg-secondary', t('fl_refused_help')));
            refused.appendChild(cards);
            ui.scheduledGroups.appendChild(refused);
            K.scheduledList(cards);
        }
    }

    // One converted scheduled action: its name, state and converted mark;
    // its when (and next time) and its step; Open flow, Run now (the only
    // hand start of a flow started by time or event) and History, the
    // action's runs from before and after the conversion.
    function convertedRow(item) {
        var row = el('li', 'wsf-sched-row');
        var head = el('div', 'wsf-sched-head');
        var tools = el('div', 'wsf-sched-tools');
        var historyBox = el('div', 'ws-sa-history');
        var open = K.button('btn btn-sm btn-outline theme-secondary', t('fl_open_flow'), 'bi-diagram-3');
        var history = K.button('btn btn-sm btn-text theme-secondary', t('sa_history'), 'bi-clock-history');

        head.appendChild(el('b', 'wsf-sched-name', item.name));
        head.appendChild(el('span', 'ws-sa-state ws-sa-state-' + item.status, item.status_label));

        var mark = el('span', 'badge rounded-pill badge-subtle theme-secondary border wsf-sched-mark');

        mark.appendChild(K.icon('bi-arrow-left-right', 'me-1'));
        mark.appendChild(document.createTextNode(t('fl_conv_mark')));
        head.appendChild(mark);
        row.appendChild(head);
        row.appendChild(el('div', 'ws-sa-line', item.when + (item.next ? ' · ' + t('sa_next') + ': ' + item.next : '')));
        row.appendChild(el('div', 'ws-sa-line ws-sa-do', item.action_text || ''));

        if (item.last) {
            row.appendChild(el('div', 'ws-sa-line ws-sa-last ws-sa-last-' + item.last.status, t('sa_last') + ': ' + item.last.label + ' · ' + item.last.time + (item.last.text ? ' · ' + item.last.text : '')));
        }

        // Without the flow store's read, the row has no flow to open.
        if (item.flow) {
            open.setAttribute('data-refocus', 'sched-open-' + item.id);
            open.setAttribute('aria-label', t('fl_open_flow') + ': ' + item.name);
            open.addEventListener('click', function () {
                openFromList({ id: item.flow.id });
            });
            tools.appendChild(open);
        }

        if (item.can_edit) {
            var run = K.button('btn btn-sm btn-text theme-secondary', t('sa_run_now'), 'bi-lightning');

            run.setAttribute('data-refocus', 'sched-run-' + item.id);
            run.addEventListener('click', function () {
                K.ask(t('sa_run_now_confirm'), t('sa_run_now')).then(function (yes) {
                    if (!yes) {
                        return;
                    }

                    run.disabled = true;
                    K.api('ws_scheduled_run_now', { id: item.id }).then(function (data) {
                        convertedChanged(data.item, 'sched-run-' + item.id);
                    }).catch(function (error) {
                        run.disabled = false;
                        K.fail(error);
                    });
                });
            });
            tools.appendChild(run);
        }

        history.setAttribute('aria-expanded', 'false');
        history.addEventListener('click', function () {
            if (!historyBox.hidden) {
                historyBox.hidden = true;
                history.setAttribute('aria-expanded', 'false');
                return;
            }

            K.api('ws_scheduled_get', { id: item.id }).then(function (data) {
                K.clear(historyBox);
                historyBox.hidden = false;
                history.setAttribute('aria-expanded', 'true');

                if (!(data.item.history || []).length) {
                    historyBox.appendChild(el('div', 'small fg-secondary', t('sa_no_history')));
                }

                (data.item.history || []).forEach(function (run) {
                    var line = el('div', 'ws-sa-history-row ws-sa-last-' + run.status);

                    line.appendChild(el('b', '', run.label));
                    line.appendChild(el('span', 'fg-secondary', run.time + (run.by_hand ? ' · ' + t('sa_by_hand') : '') + (run.source ? ' · ' + t('sa_started_by') + ' ' + run.source : '')));
                    line.appendChild(el('span', '', run.text));
                    historyBox.appendChild(line);
                });
            }).catch(K.fail);
        });
        tools.appendChild(history);
        historyBox.hidden = true;
        row.appendChild(tools);
        row.appendChild(historyBox);

        return row;
    }

    // A converted row after Run now: drawn again with what it says now.
    function convertedChanged(item, focus) {
        if (!item || !state.scheduled) {
            return;
        }

        state.scheduled.converted = state.scheduled.converted.map(function (other) {
            return (other.id === item.id) ? item : other;
        });
        state.refocus = focus;
        drawScheduled();
        refocus();
    }

    // The chips and the rows, drawn again as the search or a chip changes;
    // the search field keeps the focus.
    function drawRows() {
        var counts = listCounts(state.flows, state.query);
        var flows = state.flows.filter(function (flow) { return listMatches(flow, state.chip, state.query); });

        K.clear(ui.chips);
        CHIPS.forEach(function (chip) {
            var button = K.button('btn btn-sm rounded-pill wsf-chip' + ((state.chip === chip[0]) ? ' active' : ''), t(chip[1]) + ' ');

            button.appendChild(el('span', 'wsf-chip-count', String(counts[chip[0]])));
            button.setAttribute('aria-pressed', (state.chip === chip[0]) ? 'true' : 'false');
            button.setAttribute('data-refocus', 'chip-' + (chip[0] || 'all'));
            button.addEventListener('click', function () {
                state.chip = chip[0];
                state.refocus = 'chip-' + (chip[0] || 'all');
                drawRows();
                refocus();
            });
            ui.chips.appendChild(button);
        });

        K.clear(ui.rows);
        flows.forEach(function (flow) {
            ui.rows.appendChild(flowRow(flow));
        });

        ui.flowsNone.textContent = state.flows.length ? t('fl_list_none_match') : (FL.build ? t('fl_none_build') : t('fl_none_run'));
        ui.flowsNone.classList.toggle('d-none', flows.length > 0);
    }

    // One flow of the table: its name (the row's header, a button that
    // opens it), how it starts, its state, its last run and its home
    // channel. A click anywhere on the row opens it too.
    function flowRow(flow) {
        var row = el('tr', 'wsf-flow-row');
        var name = el('th', 'wsf-flow-name');
        var link = el('button', 'wsf-flow-open', flow.name);
        var starts = el('td', 'wsf-flow-start');
        var stateCell = el('td');
        var last = el('td', 'wsf-flow-last');
        var channel = el('td', '', flow.channel_name ? '#' + flow.channel_name : '—');

        link.type = 'button';
        link.setAttribute('data-refocus', 'row-' + flow.id);
        link.addEventListener('click', function () {
            openFromList(flow);
        });
        name.setAttribute('scope', 'row');
        name.appendChild(link);

        // Somebody else's flow says what the person may do with it.
        if (!flow.mine && LEVELS[flow.access]) {
            name.appendChild(el('span', 'wsf-list-level', t(LEVELS[flow.access])));
        }

        if (START_BOXES[flow.start]) {
            starts.appendChild(K.flowIcon(START_BOXES[flow.start], 'wsf-group-start'));
        }

        starts.appendChild(el('span', '', startSentence(flow)));
        stateCell.appendChild(statusBadge(flow, true));

        if (flow.last_run) {
            last.appendChild(runFlag(flow.last_run, {
                key: 'table-flag-' + flow.id,
                label: t('fl_flag_last') + ': ' + flow.name,
                open: function (run) {
                    openFromList(flow).then(function (opened) {
                        if (opened) {
                            openLastRun(run);
                        }
                    });
                }
            }));
        } else {
            last.appendChild(el('span', 'fg-secondary', t('fl_list_never')));
        }

        [[starts, 'fl_col_start'], [stateCell, 'fl_col_state'], [last, 'fl_flag_last'], [channel, 'fl_col_channel']].forEach(function (cell) {
            cell[0].setAttribute('data-label', t(cell[1]));
        });

        [name, starts, stateCell, last, channel].forEach(function (cell) {
            row.appendChild(cell);
        });

        row.addEventListener('click', function (event) {
            if (!(event.target && event.target.closest && event.target.closest('button, a, input'))) {
                openFromList(flow);
            }
        });

        return row;
    }

    // A flow opened from the list; the flow's name takes the focus.
    function openFromList(flow) {
        state.refocus = 'name';

        return open(flow.id);
    }

    // The list shown full width, or the editor in its place.
    function listing(on) {
        state.listing = on;
        ui.flows.classList.toggle('d-none', !on);
        ui.app.classList.toggle('d-none', on);
    }

    // Back to the flow list from a flow: what is unsaved is saved first,
    // and the flow's row takes the focus.
    function showList() {
        var id = state.flow ? state.flow.id : 0;

        leaveFlow().then(function (go) {
            if (!go) {
                refocus();
                return;
            }

            window.clearTimeout(state.saveTimer);
            state.refocus = 'row-' + id + ' flows-search';
            showNone();
        }).catch(K.fail);
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
        var brief = { id: flow.id, name: flow.name, status: flow.status, access: flow.access, mine: flow.mine, channel_id: flow.channel_id, draft_rev: flow.draft_rev, version: flow.version, start: flow.start, last_run: null };
        var found = false;

        state.flows = state.flows.map(function (item) {
            if (item.id === flow.id) {
                found = true;
                // The flow's own answer carries no runs: its last one stays.
                brief.last_run = item.last_run || null;
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
        closeSetup();
        endRun();
        state.panel = '';
        state.flow = null;
        state.graph = graphOf(null);
        K.clear(ui.bar);
        newFlowButton();

        // The address no longer names a flow, so a reload does not ask for
        // one that is gone.
        try {
            window.history.replaceState(null, '', window.location.pathname);
        } catch (error) {
            // An address that cannot be changed changes nothing else.
        }

        state.section = '';
        ui.scheduled.classList.add('d-none');
        ui.gallery.classList.add('d-none');
        ui.problemBox.classList.add('d-none');
        ui.sketchBox.classList.add('d-none');
        ui.app.classList.add('wsf-nothing');
        ui.app.classList.add('wsf-no-palette');
        K.clear(ui.side);
        K.clear(ui.boxes);
        drawEdges();
        K.clear(ui.palette);
        ui.empty.textContent = FL.build ? t('fl_none_build') : t('fl_none_run');
        ui.empty.classList.remove('d-none');
        ui.stage.classList.remove('d-none');
        ui.outlineBox.classList.add('d-none');
        listing(true);
        drawFlows();
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

    // ── Starting a flow, the template gallery ──────────────────────────

    // In place of the canvas: the ways to start a new flow (an empty canvas
    // or a template), or, with into (a flow with no boxes yet), the
    // templates to place on that flow. The templates come grouped by their
    // category (ws_flow_templates()); a card shows the signs of its boxes,
    // its title and line, how it starts, how many boxes and data threads it
    // has, and Use.
    function showGallery(into) {
        leaveFlow().then(function (go) {
            if (!go) {
                refocus();
                return;
            }

            // The focus asked for is the gallery's: showNone() would spend
            // it on the flow list it draws on the way.
            var focus = state.refocus;

            window.clearTimeout(state.saveTimer);
            state.refocus = '';
            showNone();
            listing(false);
            flowsButton();
            state.refocus = focus;
            state.section = 'templates';
            state.galleryInto = into;
            K.clear(ui.gallery);
            ui.empty.classList.add('d-none');
            ui.stage.classList.add('d-none');
            ui.gallery.classList.remove('d-none');

            if (into) {
                var back = K.button('btn btn-sm btn-ghost', t('fl_tpl_back'), 'bi-arrow-left');

                back.setAttribute('data-refocus', 'tpl-back');
                back.addEventListener('click', function () {
                    open(into);
                });
                ui.bar.insertBefore(back, ui.bar.firstChild);
            } else {
                ui.gallery.appendChild(el('h2', 'h5 mb-5', t('fl_tpl_start')));

                var starts = el('div', 'wsf-starts mb-7');
                var empty = el('button', 'wsf-start-card');

                empty.type = 'button';
                empty.setAttribute('data-refocus', 'tpl-empty');
                empty.appendChild(K.icon('bi-square'));
                empty.appendChild(el('span', 'wsf-start-title', t('fl_tpl_empty')));
                empty.appendChild(el('span', 'wsf-start-help', t('fl_tpl_empty_help')));
                empty.addEventListener('click', createFlow);
                starts.appendChild(empty);

                if (FL.ai_sketch) {
                    starts.appendChild(sketchCard());
                }

                ui.gallery.appendChild(starts);
            }

            ui.gallery.appendChild(el('h2', 'h5 mb-5', t('fl_tpl_title')));

            Object.keys(FL.template_categories || {}).forEach(function (category) {
                var items = (FL.templates || []).filter(function (template) { return template.category === category; });

                if (!items.length) {
                    return;
                }

                var grid = el('div', 'wsf-tpl-grid mb-7');

                ui.gallery.appendChild(el('h3', 'wsf-tpl-category', FL.template_categories[category]));
                items.forEach(function (template) {
                    grid.appendChild(templateCard(template, into));
                });
                ui.gallery.appendChild(grid);
            });

            refocus();
        }).catch(K.fail);
    }

    // "Describe it to AI": one sentence, and Pinegrap AI drafts a new flow
    // of the person (ws_flow_ai_sketch) that opens on the canvas with what
    // is left to fix and the model's notes. Never published here: that
    // stays the person's, on the canvas.
    function sketchCard() {
        var card = el('form', 'wsf-start-card wsf-start-ai');
        var id = 'wsf-f' + (++uid);
        var label = el('label', 'wsf-start-title', t('fl_ai_sketch'));
        var help = el('span', 'wsf-start-help', t('fl_ai_sketch_help'));
        var input = el('input', 'form-control form-control-sm');
        var go = K.button('btn btn-sm btn-solid theme-primary rounded-pill px-5 align-self-start', t('fl_ai_sketch_go'), 'bi-stars');
        var status = el('div', 'wsf-start-help');

        card.noValidate = true;
        card.appendChild(K.icon('bi-stars'));
        label.htmlFor = id;
        input.id = id;
        input.type = 'text';
        input.maxLength = FL.ai_sketch_max || 600;
        input.placeholder = t('fl_ai_sketch_example');
        input.autocomplete = 'off';
        input.setAttribute('data-refocus', 'tpl-ai');
        help.id = id + '-help';
        status.id = id + '-status';
        status.setAttribute('role', 'status');
        input.setAttribute('aria-describedby', help.id + ' ' + status.id);
        go.type = 'submit';

        var say = function (text, wrong) {
            status.className = 'wsf-start-help' + (wrong ? ' fg-danger' : '');
            status.textContent = text;

            if (wrong) {
                input.setAttribute('aria-invalid', 'true');
            } else {
                input.removeAttribute('aria-invalid');
            }
        };

        card.addEventListener('submit', function (event) {
            event.preventDefault();

            var sentence = input.value.trim();

            if (go.disabled) {
                return;
            }

            if (!sentence) {
                say(t('fl_ai_sketch_empty'), true);
                input.focus();
                return;
            }

            go.disabled = true;
            input.readOnly = true;
            card.setAttribute('aria-busy', 'true');
            say(t('fl_ai_sketch_working'), false);

            K.api('ws_flow_ai_sketch', { sentence: sentence }).then(function (data) {
                state.sketch = { flow: data.flow.id, notes: data.notes || [] };
                state.refocus = 'ai-sketch';
                show(data.flow);

                // What is left to fix is in sight at once.
                if (state.problems.length || state.warnings.length) {
                    ui.problemBox.classList.remove('d-none');
                    ui.problemButton.setAttribute('aria-expanded', 'true');
                }

                refocus();
            }).catch(function (error) {
                go.disabled = false;
                input.readOnly = false;
                card.removeAttribute('aria-busy');
                say((error && error.message) || t('error_generic'), true);
                input.focus();
            });
        });

        card.appendChild(label);
        card.appendChild(help);
        card.appendChild(input);
        card.appendChild(go);
        card.appendChild(status);

        return card;
    }

    function templateCard(template, into) {
        var card = el('div', 'wsf-tpl-card');
        var icons = el('div', 'wsf-tpl-icons');
        var start = KINDS[template.start];
        var meta = el('div', 'wsf-tpl-meta');
        var use = K.button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('fl_tpl_use'), 'bi-plus-lg');

        icons.setAttribute('aria-hidden', 'true');
        template.graph.nodes.forEach(function (node) {
            icons.appendChild(K.flowIcon(node.kind));
        });

        card.appendChild(icons);

        if (template.saved) {
            card.appendChild(savedMark(template));
        }

        card.appendChild(el('h4', 'wsf-tpl-name', template.title));
        card.appendChild(el('p', 'wsf-tpl-help', template.help));
        meta.appendChild(el('span', 'wsf-tpl-start', start ? t(start.name) : ''));
        meta.appendChild(el('span', '', t('fl_tpl_boxes', template.boxes)));

        if (template.threads) {
            meta.appendChild(el('span', '', t('fl_tpl_threads', template.threads)));
        }

        card.appendChild(meta);
        use.setAttribute('data-refocus', 'tpl-' + template.key);
        use.setAttribute('aria-label', t('fl_tpl_use') + ': ' + template.title);
        use.addEventListener('click', function () {
            use.disabled = true;
            useTemplate(template, into).then(function () {
                use.disabled = false;
            });
        });

        if (!template.saved) {
            card.appendChild(use);

            return card;
        }

        // A template saved on the site: edit and delete for its saver or a
        // Manager and above (saved.edit).
        var foot = el('div', 'd-flex flex-wrap align-items-center gap-2 mt-auto');

        foot.appendChild(use);

        if (template.saved.edit) {
            var edit = K.button('btn btn-sm btn-ghost', '', 'bi-pencil', t('fl_tpl_edit') + ': ' + template.title);
            var bin = K.button('btn btn-sm btn-ghost', '', 'bi-trash', t('fl_tpl_delete') + ': ' + template.title);

            edit.setAttribute('data-refocus', 'tpl-edit-' + template.key);
            edit.addEventListener('click', function () { templateDialog(template); });
            bin.setAttribute('data-refocus', 'tpl-bin-' + template.key);
            bin.addEventListener('click', function () { binTemplate(template, bin); });
            foot.appendChild(edit);
            foot.appendChild(bin);
        }

        card.appendChild(foot);

        return card;
    }

    // The mark of a template saved on the site: its own group (team, site,
    // sales), as the gallery files all of them under "Saved on this site".
    function savedMark(template) {
        var badge = el('span', 'badge rounded-pill badge-subtle theme-secondary border align-self-start');

        badge.title = t('fl_tpl_site_mark');
        badge.appendChild(K.icon('bi-bookmark-star', 'me-1'));
        badge.appendChild(el('span', '', (FL.template_categories || {})[template.saved.category] || ''));

        return badge;
    }

    // ── Saving a flow as a template, changing a saved one ──────────────

    // The dialog of a template's title, line and group: for the flow open
    // in the editor (no template) or for a saved template of the gallery.
    // Built once; each opening fills it.
    function templateModal() {
        if (ui.templateModal) {
            return ui.templateModal;
        }

        var modal = el('dialog', 'dialog wsf-tpl-modal');
        var form = el('form');
        var head = el('div', 'dialog-header');
        var title = el('h2', 'dialog-title fs-md');
        var cross = el('button', 'btn-close');
        var body = el('div', 'dialog-body');
        var foot = el('div', 'dialog-footer');
        var cancel = K.button('btn btn-sm btn-ghost', t('fl_tpl_cancel'));
        var submit = K.button('btn btn-sm btn-solid theme-primary', '');
        var error = el('div', 'alert theme-danger small py-2 mb-0 d-none');
        var categories = Object.keys(FL.template_categories || {}).filter(function (key) { return key !== 'saved'; });
        var name = el('input', 'form-control form-control-sm');
        var line = el('input', 'form-control form-control-sm');
        var group = K.select(categories.map(function (key) { return [key, FL.template_categories[key]]; }), categories[0] || '');

        modal.id = 'wsf-tpl-dialog';
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'wsf-tpl-dialog-title');
        title.id = 'wsf-tpl-dialog-title';
        cross.type = 'button';
        cross.setAttribute('aria-label', t('fl_tpl_cancel'));
        cross.setAttribute('data-bs-dismiss', 'dialog');
        cancel.setAttribute('data-bs-dismiss', 'dialog');
        submit.type = 'submit';
        error.setAttribute('role', 'alert');
        name.type = line.type = 'text';
        name.maxLength = 160;
        name.required = true;
        line.maxLength = 255;
        name.setAttribute('data-field', 'title');
        line.setAttribute('data-field', 'help');
        group.setAttribute('data-field', 'category');
        head.appendChild(title);
        head.appendChild(cross);
        body.appendChild(el('p', 'small fg-secondary wsf-tpl-modal-help'));
        body.appendChild(field(t('fl_tpl_name'), name));
        body.appendChild(field(t('fl_tpl_line'), line));
        body.appendChild(field(t('fl_tpl_group'), group));
        body.appendChild(error);
        foot.appendChild(cancel);
        foot.appendChild(submit);
        form.appendChild(head);
        form.appendChild(body);
        form.appendChild(foot);
        modal.appendChild(form);
        document.body.appendChild(modal);

        // The fields are the person's to fill whether or not a flow is open
        // for editing (field() follows state.edit).
        [name, line, group].forEach(function (control) { control.disabled = false; });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (ui.templateSend) {
                ui.templateSend();
            }
        });

        // What follows a change (the gallery drawn again) waits for the
        // dialog to be closed: while it is open the page behind it takes no
        // focus.
        pgUI.dialog.on(modal, 'hidden', function () {
            var after = ui.templateAfter;

            ui.templateSend = ui.templateAfter = null;

            if (after) {
                whenDialogsClosed(after);
            } else {
                refocus();
            }
        });

        ui.templateModal = modal;
        ui.templateParts = { title: title, help: body.querySelector('.wsf-tpl-modal-help'), name: name, line: line, group: group, submit: submit, error: error };

        return modal;
    }

    // Opens the dialog: with a template, to change that saved template;
    // without, to keep the open flow's draft as a template of the site
    // (its unsaved changes are saved first, as the server reads the draft).
    function templateDialog(template) {
        var flow = template ? null : state.flow;
        var modal = templateModal();
        var parts = ui.templateParts;
        var busy = false;

        if (!template && !flow) {
            return;
        }

        parts.title.textContent = template ? t('fl_tpl_edit') : t('fl_tpl_save');
        parts.help.textContent = t('fl_tpl_save_help');
        parts.help.classList.toggle('d-none', !!template);
        parts.submit.textContent = template ? t('fl_tpl_change_do') : t('fl_tpl_save_do');
        parts.name.value = template ? template.title : flow.name;
        parts.line.value = template ? template.help : '';
        parts.group.value = template ? template.saved.category : (parts.group.options[0] ? parts.group.options[0].value : '');
        parts.error.classList.add('d-none');
        parts.submit.disabled = false;
        [parts.name, parts.line, parts.group].forEach(function (control) { control.classList.remove('is-invalid'); });
        state.refocus = template ? 'tpl-edit-' + template.key : 'more';

        ui.templateSend = function () {
            if (busy) {
                return;
            }

            var data = { title: parts.name.value, help: parts.line.value, category: parts.group.value };
            var request = template
                ? K.api('ws_flow_template_update', Object.assign({ key: template.key }, data))
                : flush().then(function () {
                    if ((state.flow !== flow) || state.dirty || state.saveError || state.conflict) {
                        throw new Error(state.saveError || state.conflict || t('fl_unsaved'));
                    }

                    return K.api('ws_flow_template_save', Object.assign({ flow_id: flow.id }, data));
                });

            busy = true;
            parts.submit.disabled = true;
            parts.error.classList.add('d-none');
            [parts.name, parts.line, parts.group].forEach(function (control) { control.classList.remove('is-invalid'); });

            request.then(function (answer) {
                busy = false;
                parts.submit.disabled = false;
                FL.templates = templatesKept(FL.templates, answer.template, template);

                if (template && (state.section === 'templates')) {
                    ui.templateAfter = function () {
                        state.refocus = 'tpl-edit-' + answer.template.key;
                        showGallery(state.galleryInto);
                    };
                }

                pgUI.dialog.hide(modal);
                K.toast(template ? t('fl_tpl_changed') : t('fl_tpl_saved'), 'success');
            }).catch(function (failure) {
                busy = false;
                parts.submit.disabled = false;

                var bad = failure.field ? modal.querySelector('[data-field="' + failure.field + '"]') : null;

                parts.error.textContent = failure.message || t('fl_unsaved');
                parts.error.classList.remove('d-none');

                if (bad) {
                    bad.classList.add('is-invalid');
                }

                (bad || parts.submit).focus();
            });
        };

        pgUI.dialog.show(modal);
        parts.name.focus();
        parts.name.select();
    }

    // fn once no dialog is open: the question asked or the template dialog
    // fades out before it closes, and until then the page behind it takes
    // no focus.
    function whenDialogsClosed(fn) {
        var open = document.querySelector('dialog[open]');

        if (!open) {
            fn();
            return;
        }

        open.addEventListener('close', function () { whenDialogsClosed(fn); }, { once: true });
    }

    // The gallery's list as the server now has it: a saved or changed
    // template first (the latest changed come first), a deleted one (old
    // without entry) gone. A new list; the one given stays as it was.
    function templatesKept(list, entry, old) {
        var kept = (list || []).filter(function (item) {
            return !(old && (item.key === old.key)) && !(entry && (item.key === entry.key));
        });

        return entry ? [entry].concat(kept) : kept;
    }

    function binTemplate(template, control) {
        K.ask(t('fl_tpl_delete_ask', template.title), t('fl_tpl_delete'), true).then(function (yes) {
            if (!yes) {
                control.focus();
                return null;
            }

            control.disabled = true;

            return K.api('ws_flow_template_bin', { key: template.key }).then(function () {
                FL.templates = templatesKept(FL.templates, null, template);
                K.toast(t('fl_tpl_deleted'), 'success');
                whenDialogsClosed(function () {
                    state.refocus = 'tpl-' + ((FL.templates || []).filter(function (item) { return item.saved; })[0] || { key: '' }).key + ' tpl-empty tpl-back';
                    showGallery(state.galleryInto);
                });
            });
        }).catch(function (error) {
            control.disabled = false;
            K.fail(error);
        });
    }

    // The template's boxes and arrows on the canvas: on the flow it was
    // asked for, else on a new flow named after it. Saved as any change.
    function useTemplate(template, into) {
        var place = function () {
            state.graph = graphOf(template.graph);
            state.selected = null;
            state.refocus = 'box-' + template.graph.nodes[0].id;
            drawPalette();
            drawAll();
            fit();
            changed(true);
            refocus();
            arrive(template);
        };

        var create = function () {
            return K.api('ws_flow_create', { name: template.title, graph: graphOf(template.graph) }).then(function (data) {
                state.refocus = 'box-' + template.graph.nodes[0].id;
                show(data.flow);
                refocus();
                arrive(template);
            }).catch(K.fail);
        };

        // A flow that got boxes meanwhile (another screen) is not written
        // over: the template becomes a flow of its own.
        if (into) {
            return open(into).then(function (opened) {
                if (!opened) {
                    return null;
                }

                if (state.edit && !state.graph.nodes.length) {
                    place();
                    return null;
                }

                return create();
            });
        }

        return create();
    }

    // ── Placing a template, its setup panel ────────────────────────────

    function stillMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    // A line drawn from its start to its end in ms, after at.
    function grow(path, at, ms) {
        var length = path.getTotalLength ? path.getTotalLength() : 0;

        if (!length) {
            return;
        }

        path.style.strokeDasharray = length + ' ' + length;
        path.style.strokeDashoffset = String(length);
        path.getBoundingClientRect();
        path.style.transition = 'stroke-dashoffset ' + ms + 'ms ease-out ' + at + 'ms';
        path.style.strokeDashoffset = '0';
        window.setTimeout(function () {
            path.style.transition = '';
            path.style.strokeDasharray = '';
            path.style.strokeDashoffset = '';
        }, at + ms + 50);
    }

    // The data threads of a template are the ones its fields hold
    // (ws_flow_template_threads(): from, to, the setting), drawn with the
    // canvas (drawData()): each gives the lines drawn for it.
    function drawThreads(threads) {
        return threads.map(function (thread) {
            var at = '[data-from="' + String(thread.from).replace(/"/g, '') + '"][data-to="' + String(thread.to).replace(/"/g, '')
                + '"][data-field="' + String(thread.key).replace(/"/g, '') + '"]';

            return Array.prototype.slice.call(ui.svg.querySelectorAll('.wsf-thread' + at));
        });
    }

    // The template's boxes, arrows and threads appear at the times of
    // template.motion (ws_flow_template_motion()), all at once with reduced
    // motion; then the setup panel slides in.
    function arrive(template) {
        var motion = template.motion || { boxes: [], arrows: [], threads: [], panel: 0 };
        var still = stillMotion();
        var flow = state.flow;
        var paths = drawThreads(motion.threads || []);

        if (!flow) {
            return;
        }

        if (!still) {
            motion.boxes.forEach(function (item) {
                var box = ui.boxes.querySelector('.wsf-node[data-node="' + item.id + '"]');

                if (box) {
                    box.style.animationDelay = item.at + 'ms';
                    box.style.animationDuration = item.ms + 'ms';
                    box.classList.add('wsf-arrive');
                    box.addEventListener('animationend', function () {
                        box.classList.remove('wsf-arrive');
                        box.style.animationDelay = '';
                        box.style.animationDuration = '';
                    }, { once: true });
                }
            });

            motion.arrows.forEach(function (item) {
                var line = ui.svg.querySelector('.wsf-edge[data-edge="' + item.edge + '"] .wsf-edge-line');

                if (line) {
                    line.classList.add('wsf-arrow-hold');
                    window.setTimeout(function () { line.classList.remove('wsf-arrow-hold'); }, item.at + item.ms);
                    grow(line, item.at, item.ms);
                }
            });

            motion.threads.forEach(function (item, index) {
                (paths[index] || []).forEach(function (path) {
                    grow(path, item.at, item.ms);
                });
            });
        }

        if (!(template.settings || []).length) {
            return;
        }

        state.arriving = window.setTimeout(function () {
            if (state.flow === flow) {
                setupPanel(template);
            }
        }, still ? 0 : motion.panel);
    }

    function closeSetup() {
        window.clearTimeout(state.arriving);

        if (ui.setup) {
            K.clear(ui.setup);
            ui.setup.classList.add('d-none');
        }
    }

    // What a setting holds now: the value of its first box on the canvas.
    function settingValue(setting) {
        var target = setting.targets[0];
        var node = nodeById(target[0]);

        return node ? node.config[target[1]] : null;
    }

    function setupControl(setting, answers) {
        var type = setting.type;
        var value = settingValue(setting);
        var control;

        answers[type] = value;

        if (type === 'channel') {
            control = channelPick({ channel_id: value || 0 }, t('fl_post_pick'));
            control.addEventListener('change', function () { answers.channel = parseInt(control.value, 10) || 0; });
        } else if (type === 'person') {
            control = el('div', 'wsf-people-list');
            control.setAttribute('role', 'group');
            answers.person = (value || []).slice();
            (BOOT.people || []).forEach(function (member) {
                var row = el('label', 'wsf-person');
                var check = el('input', 'check m-0');

                check.type = 'checkbox';
                check.checked = answers.person.indexOf(member.id) !== -1;
                check.addEventListener('change', function () {
                    answers.person = answers.person.filter(function (id) { return id !== member.id; });

                    if (check.checked) {
                        answers.person.push(member.id);
                    }
                });
                row.appendChild(check);
                row.appendChild(document.createTextNode(member.name));
                control.appendChild(row);
            });
        } else {
            control = el('input', 'form-control form-control-sm');
            control.type = { time: 'time', url: 'url' }[type] || 'text';
            control.value = value || '';
            control.addEventListener('input', function () { answers[type] = control.value; });
        }

        control.setAttribute('data-setting', type);

        return field(t(SETTINGS[type] || type), control, (type === 'address') ? t('fl_email_to_help') : '');
    }

    // The template's few settings, then Open on canvas (the answers into the
    // draft) or Publish now (into the draft, then published). The server
    // checks the answers again (ws_flow_template_setup()).
    function setupPanel(template) {
        var answers = {};
        var flow = state.flow;
        var error = el('div', 'wsf-setup-error d-none');
        var actions = el('div', 'wsf-setup-actions');
        var openIt = K.button('btn btn-sm btn-outline theme-secondary rounded-pill px-5', t('fl_tpl_open'), 'bi-bounding-box');
        var publishIt = K.button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('fl_tpl_publish'), 'bi-send');
        var close = K.button('btn btn-sm btn-ghost wsf-setup-close', '', 'bi-x-lg');
        var title = el('h2', 'wsf-setup-title', t('fl_tpl_setup'));

        closeSetup();
        title.id = 'wsf-setup-title';
        close.setAttribute('aria-label', t('fl_tpl_close'));
        close.addEventListener('click', closeSetup);
        ui.setup.appendChild(close);
        ui.setup.appendChild(title);
        ui.setup.appendChild(el('p', 'wsf-setup-help', t('fl_tpl_setup_help', template.title)));
        template.settings.forEach(function (setting) {
            ui.setup.appendChild(setupControl(setting, answers));
        });
        error.setAttribute('role', 'alert');
        ui.setup.appendChild(error);

        var send = function (publishing) {
            var control = publishing ? publishIt : openIt;

            openIt.disabled = publishIt.disabled = true;
            error.classList.add('d-none');
            Array.prototype.forEach.call(ui.setup.querySelectorAll('.is-invalid'), function (item) { item.classList.remove('is-invalid'); });

            flush().then(function () {
                if ((state.flow !== flow) || state.dirty || state.saveError || state.conflict) {
                    throw new Error(state.saveError || state.conflict || t('fl_unsaved'));
                }

                return K.api('ws_flow_template_setup', { flow_id: flow.id, draft_rev: flow.draft_rev, key: template.key, answers: answers, publish: publishing });
            }).then(function (data) {
                var view = { x: state.view.x, y: state.view.y, k: state.view.k };

                state.refocus = publishing ? 'status' : 'box-' + template.graph.nodes[0].id;
                show(data.flow);
                state.view = view;
                applyView();

                if (data.publish_error) {
                    K.toast(data.publish_error, 'danger');
                } else if (data.published) {
                    K.toast(t('fl_tpl_published'), 'success');
                }
            }).catch(function (failure) {
                openIt.disabled = publishIt.disabled = false;

                if (failure.field === 'changed_meanwhile') {
                    closeSetup();
                    conflict(failure.message);
                    return;
                }

                var bad = failure.field ? ui.setup.querySelector('[data-setting="' + failure.field + '"]') : null;

                error.textContent = failure.message || t('fl_unsaved');
                error.classList.remove('d-none');

                if (bad) {
                    bad.classList.add('is-invalid');
                }

                (bad || control).focus();
            });
        };

        openIt.addEventListener('click', function () { send(false); });
        publishIt.addEventListener('click', function () { send(true); });
        actions.appendChild(openIt);
        actions.appendChild(publishIt);
        ui.setup.appendChild(actions);
        ui.setup.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.stopPropagation();
                closeSetup();
                ui.viewport.focus();
            }
        });
        ui.setup.classList.remove('d-none');

        var first = ui.setup.querySelector('[data-setting]');

        (first && first.matches('input, select') ? first : (ui.setup.querySelector('input') || openIt)).focus();
    }

    // ── A scheduled action on the canvas ───────────────────────────────

    // The boxes of a scheduled action (ws_scheduled_graph()) in the group
    // colours of the flow boxes that will keep them: its "when" a start, a
    // condition logic, its step a task, a follow-up the start of another
    // action.
    var ACTION_KINDS = {
        when: 'wsf-group-start',
        condition: 'wsf-group-logic',
        step: 'wsf-group-people',
        follow: 'wsf-group-system'
    };

    // A staff member's scheduled action drawn read-only in place of the
    // flow list (Show on canvas on a card of its "Not converted" group): its "when", conditions, step
    // and follow-ups as boxes with the arrows between them, on a canvas
    // that scrolls; the actions that start it, those it starts and its
    // retry as chips above. Nothing is changed here: Edit (options.edit)
    // opens it as a flow on the canvas, Back shows the flow list again,
    // its "Not converted" group in view. The boxes
    // are a list in the order of the arrows for the keyboard and screen
    // readers, each with all of its text.
    function showAction(item, graph, options) {
        // An answer that comes once the person has left the list is not shown.
        if (!state.listing) {
            return;
        }

        var nodes = (graph && graph.nodes) || [];
        var chips = (graph && graph.chips) || {};
        var pad = 24;
        var width = Math.max.apply(null, [0].concat(nodes.map(function (node) { return node.x + BOX_W; }))) + 2 * pad;
        var height = Math.max.apply(null, [0].concat(nodes.map(function (node) { return node.y + BOX_H; }))) + 2 * pad;
        var byId = {};

        options = options || {};
        listing(false);
        state.section = 'action';
        K.clear(ui.bar);
        K.clear(ui.scheduled);
        ui.empty.classList.add('d-none');
        ui.stage.classList.add('d-none');
        ui.scheduled.classList.remove('d-none');

        var back = K.button('btn btn-sm btn-ghost', t('sa_canvas_back'), 'bi-arrow-left');
        back.setAttribute('data-refocus', 'sa-back');
        back.addEventListener('click', function () {
            state.refocus = 'sched-refused flows-search';
            showNone();
        });
        ui.bar.appendChild(back);
        ui.bar.appendChild(el('span', 'fw-semibold wsf-name-text', item.name));
        ui.bar.appendChild(el('span', 'ws-sa-state ws-sa-state-' + item.status, item.status_label));
        ui.bar.appendChild(el('span', 'pg-toolbar-grow'));

        if (options.edit) {
            var edit = K.button('btn btn-sm btn-outline theme-secondary', t('edit'), 'bi-pencil');
            edit.addEventListener('click', options.edit);
            ui.bar.appendChild(edit);
        }

        ui.scheduled.appendChild(el('h2', 'h5 mb-1', item.name));
        ui.scheduled.appendChild(el('p', 'small fg-secondary', t('sa_canvas_help')));

        var chipRow = el('div', 'ws-sa-graph-chips');
        var chip = function (iconName, text) {
            var part = el('span', 'badge rounded-pill badge-subtle theme-secondary border ws-sa-graph-chip');

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
        closeSetup();
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

        // The notes of a drafted flow go with that flow only.
        if (state.sketch && (state.sketch.flow !== flow.id)) {
            state.sketch = null;
        }

        // Somebody who only reaches the flow reads its live graph, or the
        // draft, read-only, of a flow never published.
        state.graph = graphOf(state.edit ? flow.draft : (flow.graph || flow.draft));
        state.problems = flow.problems || [];
        state.warnings = flow.warnings || [];
        state.marks = marksOf(flow);
        state.selected = null;
        state.binding = null;

        // Shown again after Publish or Pause, the flow keeps its steps;
        // another flow starts with none.
        if (!same || !state.history) {
            state.history = historyOf(flow.id, JSON.stringify(state.graph));
        } else {
            state.history.now = JSON.stringify(state.graph);
        }

        state.dirty = false;
        state.saveError = '';
        state.conflict = '';
        state.section = '';
        ui.scheduled.classList.add('d-none');
        ui.gallery.classList.add('d-none');
        listing(false);
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

        var create = K.button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('fl_new'), 'bi-plus-lg');

        create.setAttribute('data-refocus', 'new');
        create.addEventListener('click', function () {
            // With templates or Pinegrap AI to offer, the ways to start
            // come first.
            if ((FL.templates || []).length || FL.ai_sketch) {
                showGallery(null);
            } else {
                createFlow();
            }
        });
        ui.bar.appendChild(create);
    }

    // "Flows ›": back to the flow list.
    function flowsButton() {
        var back = K.button('btn btn-sm btn-ghost wsf-back', t('fl_nav'));

        back.appendChild(el('span', 'ms-1', '›')).setAttribute('aria-hidden', 'true');
        back.setAttribute('aria-label', t('fl_list_back'));
        back.setAttribute('data-refocus', 'flows');
        back.addEventListener('click', showList);
        ui.bar.appendChild(back);
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

        var run = K.button(primary ? 'btn btn-sm btn-solid theme-primary rounded-pill px-5' : 'btn btn-sm btn-outline theme-secondary', t('fl_run'), 'bi-play-fill');

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

        flowsButton();

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

        ui.unpublished = el('span', 'small fg-secondary wsf-unpublished d-none', t('fl_unpublished'));
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

        if (!state.outline) {
            ui.bar.appendChild(threadsButton());
        }

        runsButton();

        if (FL.build) {
            runButton(false);
        }

        // A run shown says so in its own bar above the canvas.
        if (!state.edit) {
            if (!state.run) {
                ui.bar.appendChild(el('span', 'small fg-secondary', t('fl_read_only')));
            }

            moreMenu();
            return;
        }

        ui.undo = K.button('btn btn-sm btn-ghost', '', 'bi-arrow-counterclockwise', t('fl_undo'));
        ui.undo.setAttribute('data-refocus', 'undo redo');
        ui.undo.addEventListener('click', function () { undo(true); });
        ui.bar.appendChild(ui.undo);
        ui.redo = K.button('btn btn-sm btn-ghost', '', 'bi-arrow-clockwise', t('fl_redo'));
        ui.redo.setAttribute('data-refocus', 'redo undo');
        ui.redo.addEventListener('click', function () { undo(false); });
        ui.bar.appendChild(ui.redo);

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
            ui.publish = K.button('btn btn-sm btn-outline theme-secondary', t('fl_publish'), 'bi-send');
            ui.publish.addEventListener('click', publish);
            ui.bar.appendChild(ui.publish);
        } else {
            ui.publish = null;
            ui.bar.appendChild(el('span', 'small fg-secondary wsf-publish-owner', t('fl_publish_owner')));
        }

        if (flow.status === 'live') {
            var pause = K.button('btn btn-sm btn-outline theme-secondary', t('fl_pause'), 'bi-pause-circle');
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
        var more = el('div', 'pg-menu-wrap');
        var moreButton = K.button('btn btn-sm btn-ghost', '', 'bi-three-dots', t('fl_more'));
        var menu = el('ul', 'menu pg-menu-end');
        var share = K.button('menu-item pg-link-body-emphasis', flow.manage ? t('fl_share') : t('fl_shared_with'), flow.manage ? 'bi-share' : 'bi-people');

        moreButton.setAttribute('data-bs-toggle', 'menu');
        moreButton.setAttribute('data-bs-placement', 'bottom-end');
        moreButton.setAttribute('aria-expanded', 'false');
        moreButton.setAttribute('data-refocus', 'more');
        share.setAttribute('data-wsf-share', '');
        share.addEventListener('click', openShares);
        menu.appendChild(el('li')).appendChild(share);

        // Keeping the draft as a template of the site: for those who build
        // flows, on a flow they edit (ws_flow_template_save() refuses one
        // without boxes, said in the dialog).
        if (FL.template_save && state.edit) {
            var keep = K.button('menu-item pg-link-body-emphasis', t('fl_tpl_save'), 'bi-bookmark-plus');

            keep.addEventListener('click', function () { templateDialog(null); });
            menu.appendChild(el('li')).appendChild(keep);
        }

        if (flow.manage && state.edit) {
            var bin = K.button('menu-item pg-link-body-emphasis', t('fl_bin'), 'bi-trash');

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

        ui.undo.disabled = !undoable(true);
        ui.redo.disabled = !undoable(false);

        // A live or paused flow whose draft holds what its version does not.
        ui.unpublished.classList.toggle('d-none', !((state.flow.status !== 'draft') && (state.flow.changed || state.dirty)));

        if (state.saving) {
            text = t('fl_saving');
        } else if (state.dirty || state.saveError || state.conflict) {
            text = t('fl_unsaved');
        }

        ui.save.textContent = text;
        ui.save.title = state.saveError || '';
        ui.save.classList.toggle('fg-danger', !!(state.saveError || state.conflict));
        ui.save.classList.toggle('fg-secondary', !(state.saveError || state.conflict));

        if (state.saveError && !state.saving) {
            var retry = K.button('btn btn-sm btn-link p-0 ms-1 align-baseline', t('fl_retry'));
            retry.addEventListener('click', function () {
                state.saveError = '';
                state.dirty = true;
                save();
            });
            ui.save.appendChild(retry);
        }

        // Warnings alone open the list too, but leave Publish as it is.
        var warned = state.warnings.length;

        K.clear(ui.problemButton);
        ui.problemButton.className = 'btn btn-sm ' + (count ? 'btn-outline theme-danger' : (warned ? 'btn-outline theme-warning' : 'btn-ghost'));
        ui.problemButton.appendChild(K.icon(count ? 'bi-exclamation-triangle' : (warned ? 'bi-exclamation-circle' : 'bi-check2-circle'), 'me-1'));
        ui.problemButton.appendChild(document.createTextNode(count ? t('fl_problems', count) : (warned ? t('fl_warnings', warned) : t('fl_no_problems'))));
        ui.problemButton.disabled = !count && !warned;

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
        ui.stage.classList.toggle('wsf-threads-off', !state.threads);
        ui.outlineBox.classList.toggle('d-none', !state.outline);
        ui.app.classList.toggle('wsf-outlined', state.outline);
        drawCanvas();

        // The side panel before the outline: a focus asked for in the side
        // (the title of a run shown) is found there before the outline's
        // drawing gives it up.
        drawSettings();
        drawOutline();
        drawProblems();
        drawMarks();
        drawSketch();
        drawState();
    }

    function drawCanvas() {
        K.clear(ui.boxes);

        orderedNodes().forEach(function (node) {
            ui.boxes.appendChild(boxNode(node));
        });

        drawEdges();
        drawFlag();
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
        var box = el('div', 'wsf-node ' + kindClasses(node.kind));
        var head = el('div', 'wsf-node-head');

        box.tabIndex = 0;
        box.setAttribute('role', 'group');
        box.setAttribute('aria-roledescription', t('fl_box'));
        box.setAttribute('data-node', node.id);
        box.setAttribute('data-refocus', 'box-' + node.id);

        if (kind.into) {
            box.appendChild(port('in'));
        }

        head.appendChild(K.flowIcon(node.kind));
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
        paintMark(box, box.querySelector('.wsf-node-head'), node);
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

        // A box moved may change which box is the last.
        if (ui.flag && (box !== ui.flag)) {
            placeFlag();
        }
    }

    function curve(x1, y1, x2, y2) {
        var dx = Math.max(48, Math.abs(x2 - x1) / 2);

        return { d: 'M' + x1 + ' ' + y1 + ' C' + (x1 + dx) + ' ' + y1 + ' ' + (x2 - dx) + ' ' + y2 + ' ' + x2 + ' ' + y2, mx: (x1 + x2) / 2, my: (y1 + y2) / 2 };
    }

    function drawEdges() {
        Array.prototype.slice.call(ui.svg.querySelectorAll('.wsf-edge, .wsf-thread')).forEach(function (node) {
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

            if (step && (step.status === 'done') && step.outcome && ((step.outcome === edge.on) || (edge.on === 'always'))) {
                group.classList.add('wsf-edge-taken');
            }

            line.setAttribute('d', path.d);
            hit.setAttribute('d', path.d);
            group.appendChild(line);
            group.appendChild(hit);

            // An arrow taken when a task is cancelled, or a step failed, says
            // so on itself.
            if ((edge.on === 'cancelled') || (edge.on === 'failed') || (edge.on === 'always')) {
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

    // ── Data threads ───────────────────────────────────────────────────

    // The fields of a box a run fills with values, as ws_flow_node_texts()
    // reads them, with the words the side panel names them by and the most
    // they hold. A condition reads the one value its rule compares
    // (fieldTexts()). The fields that decide where something goes (addresses,
    // recipients) are not among them, so a value never goes there.
    var VALUE_FIELDS = {
        task: [['title', 'fl_title', 200], ['description', 'fl_description', 8000]],
        post: [['body', 'fl_post_body', 4000]],
        email: [['subject', 'fl_email_subject', 250], ['body', 'fl_email_body', 20000]],
        notify: [['text', 'fl_notify_text', 500]],
        task_open: [['title', 'fl_title', 200], ['description', 'fl_description', 8000]],
        webhook: [['text', 'fl_post_body', 4000]],
        mcp: [['arguments', 'fl_mcp_arguments', 20000]],
        task_digest: [['subject', 'fl_email_subject', 250], ['intro', 'fl_digest_intro', 2000]],
        report: [['title', 'fl_report_title', 160]],
        checkin: [['question', 'fl_checkin_question', 500]],
        approval: [['title', 'fl_title', 200], ['text', 'fl_description', 4000]],
        value: [['a', 'fl_derived_a', 500], ['b', 'fl_derived_b', 500]]
    };

    // How many tones a type's threads take turns in (.wsf-tone-<type>-<n>).
    var THREAD_TONES = { number: 3, text: 3 };

    // The row under a box: a field chip's and a value dot's full width, the
    // gap between them, where the row starts and ends inside the box, and
    // the ring's size; all 18 px high, half under the box's bottom edge. A
    // thread bends at most `deep` px further down to pass under a box.
    var DATA_ROW = { field: 76, out: 84, gap: 4, left: 8, half: 9, ring: 18, deep: 160 };

    // Where the chips (fields first) and the dots of a box's row go: at full
    // width while they fit within the box, else all as wide as the box's
    // width shared out allows (a dot never narrower than its ring, the
    // label then cut, its full name on hover), so the row is never wider
    // than its box. Each slot is { left, width } from the box's left edge.
    function dataRowSlots(fields, outs) {
        var room = BOX_W - 2 * DATA_ROW.left;
        var count = fields + outs;
        var full = fields * DATA_ROW.field + outs * DATA_ROW.out + Math.max(0, count - 1) * DATA_ROW.gap;
        var slots = { field: [], out: [] };
        var at = DATA_ROW.left;
        var width = 0;
        var step = 0;
        var i;

        if (full <= room) {
            for (i = 0; i < count; i++) {
                width = (i < fields) ? DATA_ROW.field : DATA_ROW.out;
                slots[(i < fields) ? 'field' : 'out'].push({ left: at, width: width });
                at += width + DATA_ROW.gap;
            }

            return slots;
        }

        width = Math.max(DATA_ROW.ring, Math.floor((room - (count - 1) * DATA_ROW.gap) / count));
        step = (count > 1) ? Math.min(width + DATA_ROW.gap, (room - width) / (count - 1)) : 0;

        for (i = 0; i < count; i++) {
            slots[(i < fields) ? 'field' : 'out'].push({ left: DATA_ROW.left + Math.round(i * step), width: width });
        }

        return slots;
    }

    // A thread hanging from (x1, y1) to (x2, y2), sag px below the lower
    // end. When its curve would pass under one of the boxes given ({ x, y }
    // of their top left corner, the two it joins left out), it hangs deeper,
    // 16 px at a time, until it passes below them all, at most DATA_ROW.deep
    // px more; past that (a box far below) it keeps its own sag. Gives the
    // path and the sag taken.
    function threadPath(x1, y1, x2, y2, sag, boxes) {
        var low = Math.max(y1, y2);
        var crosses = function (depth) {
            var cx = (x1 + x2) / 2;
            var cy = low + depth;

            return boxes.some(function (box) {
                var left = box.x - 4;
                var right = box.x + BOX_W + 4;
                var top = box.y - 4;
                var bottom = box.y + BOX_H + DATA_ROW.ring;

                for (var n = 1; n < 24; n++) {
                    var u = n / 24;
                    var x = (1 - u) * (1 - u) * x1 + 2 * u * (1 - u) * cx + u * u * x2;
                    var y = (1 - u) * (1 - u) * y1 + 2 * u * (1 - u) * cy + u * u * y2;

                    if ((x > left) && (x < right) && (y > top) && (y < bottom)) {
                        return true;
                    }
                }

                return false;
            });
        };
        var depth = sag;

        while (crosses(depth) && (depth < sag + DATA_ROW.deep)) {
            depth += 16;
        }

        if (crosses(depth)) {
            depth = sag;
        }

        return { d: 'M' + x1 + ' ' + y1 + ' Q' + ((x1 + x2) / 2) + ' ' + (low + depth) + ' ' + x2 + ' ' + y2, sag: depth };
    }

    function fieldTexts(node) {
        var config = node.config || {};

        if (node.kind === 'condition') {
            var rule = config.rule || {};

            return ((rule.type === 'value') && (typeof rule.ref === 'string') && rule.ref) ? [{ key: 'rule', label: t('fl_cond_value_ref'), text: rule.ref }] : [];
        }

        return (VALUE_FIELDS[node.kind] || []).filter(function (item) {
            return typeof config[item[0]] === 'string';
        }).map(function (item) {
            return { key: item[0], label: t(item[1]), text: config[item[0]] };
        });
    }

    // The fields of a box one more value may go into: every one of its
    // kind, a condition's only while it compares no value yet.
    function openFields(node) {
        var config = node.config || {};

        if (node.kind === 'condition') {
            var rule = config.rule || {};

            return ((rule.type === 'value') && !rule.ref) ? [{ key: 'rule', label: t('fl_cond_value_ref'), text: '', max: 0 }] : [];
        }

        return (VALUE_FIELDS[node.kind] || []).filter(function (item) {
            return (node.kind !== 'value') || (item[0] !== 'b') || derivedRule(config.rule).two;
        }).map(function (item) {
            return { key: item[0], label: t(item[1]), text: (typeof config[item[0]] === 'string') ? config[item[0]] : '', max: item[2] };
        });
    }

    // Where a value of a box may go: the open fields of every box that may
    // read it from that box (valueSources(), so the boxes after it, and any
    // box for what started the run), except those already holding it.
    function bindTargets(from, token) {
        var out = [];

        state.graph.nodes.forEach(function (node) {
            var source = valueSources(node)[token];

            if (!source || (source.node.id !== from)) {
                return;
            }

            openFields(node).forEach(function (item) {
                if (item.text.indexOf(token) === -1) {
                    out.push({ node: node, key: item.key, label: item.label, text: item.text, max: item.max });
                }
            });
        });

        return out;
    }

    // A value put into a field at its end, after a space, as "Add value"
    // writes it as a chip; a condition compares it, its comparison back to
    // "is" when that does not fit the value's type.
    function bindValue(from, token, to, key) {
        var target = bindTargets(from, token).filter(function (item) { return (item.node.id === to) && (item.key === key); })[0];

        if (!target) {
            return false;
        }

        var node = target.node;
        var source = valueSources(node)[token];

        if (key === 'rule') {
            node.config.rule.ref = token;

            if (VALUE_OPS[(source.value.type === 'number') ? 'number' : 'text'].indexOf(node.config.rule.op) === -1) {
                node.config.rule.op = 'eq';
            }
        } else {
            var text = target.text + ((target.text && !/\s$/.test(target.text)) ? ' ' : '') + token;

            if (text.length > target.max) {
                K.toast(t('fl_thread_full'), 'secondary');
                return false;
            }

            node.config[key] = text;
        }

        refreshNode(node);
        drawSettings();
        announce(t('fl_thread_bound', nameOf(source.node) + ' › ' + source.value.label, nameOf(node) + ' › ' + target.label));

        return true;
    }

    // One thread for each value a field of a box holds, read from the
    // references kept in the fields: nothing else stores them, so a removed
    // chip takes its thread with it. A value no box before gives has no
    // source (from ''). Threads of one type take turns in its tones.
    function dataThreads() {
        var threads = [];
        var tones = {};

        if (!state.graph) {
            return threads;
        }

        orderedNodes().forEach(function (node) {
            var sources = null;

            fieldTexts(node).forEach(function (item) {
                var seen = {};
                var match;

                if (item.text.indexOf('{{') === -1) {
                    return;
                }

                sources = sources || valueSources(node);
                VALUE_TOKEN.lastIndex = 0;

                while ((match = VALUE_TOKEN.exec(item.text)) !== null) {
                    if (seen[match[0]]) {
                        continue;
                    }

                    seen[match[0]] = true;

                    var source = sources[match[0]];
                    var type = (source && THREAD_TONES[source.value.type]) ? source.value.type : 'text';

                    tones[type] = (tones[type] || 0) + 1;
                    threads.push({
                        token: match[0],
                        from: source ? source.node.id : '',
                        to: node.id,
                        field: item.key,
                        fieldLabel: item.label,
                        label: source ? nameOf(source.node) + ' › ' + source.value.label : match[0].slice(2, -2),
                        value: source ? source.value.label : '',
                        type: type,
                        tone: (tones[type] - 1) % THREAD_TONES[type],
                        skipped: !!(source && source.skipped)
                    });
                }
            });
        });

        return threads;
    }

    function toneClass(thread) {
        return 'wsf-tone-' + thread.type + '-' + thread.tone;
    }

    // The threads on the canvas: under each box a row of the fields it fills
    // (chips, first) and of the values it gives (dots), never wider than the
    // box (dataRowSlots()); chips in the order of the boxes they read from,
    // dots in the order of the boxes they reach, left to right, so threads
    // cross less. From each dot a 1 px line to the chip of each field it
    // fills, hanging below both by 10 px + 0.06 of its span, deeper where it
    // would pass under another box (threadPath()). The threads of one dot
    // leave it a few px apart, the one going farthest on the left (it sags
    // more, so they do not cross near the dot); threads between the same two
    // boxes hang 4 px apart. The row is hidden from screen readers: the
    // outline says the same in words.
    function drawData() {
        Array.prototype.slice.call(ui.layer.querySelectorAll('.wsf-thread, .wsf-data')).forEach(function (node) {
            node.remove();
        });

        var threads = dataThreads();
        var binding = state.binding;
        var rows = {};
        var row = function (id) {
            return (rows[id] = rows[id] || { fields: [], outs: [], ends: {}, labels: {}, values: {}, open: {} });
        };
        var slot = function (id, part, key) {
            var item = rows[id];

            item.slots = item.slots || dataRowSlots(item.fields.length, item.outs.length);

            return item.slots[part][(part === 'out') ? item.outs.indexOf(key) : item.fields.indexOf(key)];
        };
        // Where the boxes at the other ends of a chip or a dot stand, on
        // average: the row is laid out in that order.
        var reach = function (id, key) {
            var others = rows[id].ends[key].slice(1).map(nodeById).filter(Boolean);

            return others.length ? others.reduce(function (sum, node) { return sum + node.x; }, 0) / others.length : Infinity;
        };
        var add = function (map, key, thread) {
            (map[key] = map[key] || []).push(thread);
        };
        var leaving = {};
        var reaching = {};
        var pairs = {};

        threads.forEach(function (thread) {
            var into = row(thread.to);

            if (into.fields.indexOf(thread.field) === -1) {
                into.fields.push(thread.field);
            }

            into.ends[thread.field] = (into.ends[thread.field] || [thread.to]).concat(thread.from ? [thread.from] : []);

            if (thread.from) {
                var from = row(thread.from);

                if (from.outs.indexOf(thread.token) === -1) {
                    from.outs.push(thread.token);
                }

                from.ends[thread.token] = (from.ends[thread.token] || [thread.from]).concat([thread.to]);
                add(leaving, thread.from + ' ' + thread.token, thread);
                add(reaching, thread.to + ' ' + thread.field, thread);
                add(pairs, thread.from + ' ' + thread.to, thread);
                from.values[thread.token] = from.values[thread.token] || thread;
            }

            into.labels[thread.field] = into.labels[thread.field] || thread;
        });

        // In a draft a thread may start from any value of the box selected,
        // and from every dot already drawn.
        var live = (state.edit && !state.run && state.selected && (state.selected.type === 'node')) ? nodeById(state.selected.id) : null;

        if (live) {
            boxValues(live).forEach(function (value) {
                var item = row(live.id);

                if (item.outs.indexOf(value.token) === -1) {
                    item.outs.push(value.token);
                    item.ends[value.token] = [live.id];
                    item.values[value.token] = { value: value.label, type: THREAD_TONES[value.type] ? value.type : 'text', tone: 0, unused: true };
                }
            });
        }

        // A thread being drawn: every field it may go into lights up.
        var targets = binding ? bindTargets(binding.from, binding.token) : [];

        targets.forEach(function (target) {
            var item = row(target.node.id);

            if (item.fields.indexOf(target.key) === -1) {
                item.fields.push(target.key);
                item.ends[target.key] = [target.node.id];
                item.labels[target.key] = { fieldLabel: target.label, type: 'text', tone: 0 };
            }

            item.ends[target.key].push(binding.from);
            item.open[target.key] = true;
        });

        Object.keys(rows).forEach(function (id) {
            ['fields', 'outs'].forEach(function (part) {
                var order = rows[id][part].slice();

                rows[id][part].sort(function (a, b) {
                    return (reach(id, a) - reach(id, b)) || (order.indexOf(a) - order.indexOf(b));
                });
            });
        });

        Object.keys(rows).forEach(function (id) {
            var node = nodeById(id);
            var box = ui.boxes.querySelector('.wsf-node[data-node="' + id + '"]');

            if (!node || !box) {
                return;
            }

            rows[id].fields.forEach(function (key) {
                var bad = threads.some(function (thread) { return (thread.to === id) && (thread.field === key) && !thread.from; });
                var label = rows[id].labels[key];
                var chip = el('span', 'wsf-data wsf-data-field ' + toneClass(label) + (bad ? ' wsf-data-bad' : '') + (rows[id].open[key] ? ' wsf-data-target' : ''), label.fieldLabel);
                var at = slot(id, 'field', key);

                chip.style.left = at.left + 'px';
                chip.style.setProperty('--wsf-w', at.width + 'px');
                chip.title = label.fieldLabel;
                chip.setAttribute('data-ends', rows[id].ends[key].join(' '));
                chip.setAttribute('aria-hidden', 'true');

                if (rows[id].open[key]) {
                    chip.setAttribute('data-bind', id + ' ' + key);
                }

                box.appendChild(chip);
            });

            rows[id].outs.forEach(function (token) {
                var value = rows[id].values[token];
                var dot = el('span', 'wsf-data wsf-data-out ' + toneClass(value) + (value.unused ? ' wsf-data-unused' : ''));
                var at = slot(id, 'out', token);

                dot.appendChild(el('span', 'wsf-data-ring', (value.type === 'number') ? '#' : 'T'));
                dot.appendChild(el('span', 'wsf-data-label', value.value));
                dot.style.left = at.left + 'px';
                dot.style.setProperty('--wsf-w', at.width + 'px');
                dot.title = value.value;
                dot.setAttribute('data-ends', rows[id].ends[token].join(' '));
                dot.setAttribute('aria-hidden', 'true');

                if (state.edit && !state.run) {
                    liveDot(dot, node, token);
                }

                box.appendChild(dot);
            });
        });

        var start = {};
        var end = {};
        var span = function (thread) {
            return Math.abs(nodeById(thread.to).x - nodeById(thread.from).x);
        };

        Object.keys(leaving).forEach(function (key) {
            var list = leaving[key].sort(function (a, b) { return span(b) - span(a); });

            list.forEach(function (thread, index) {
                start[threads.indexOf(thread)] = (index - (list.length - 1) / 2) * 4;
            });
        });

        Object.keys(reaching).forEach(function (key) {
            var list = reaching[key].sort(function (a, b) { return nodeById(a.from).x - nodeById(b.from).x; });

            list.forEach(function (thread, index) {
                end[threads.indexOf(thread)] = (index - (list.length - 1) / 2) * 6;
            });
        });

        threads.forEach(function (thread, index) {
            var from = thread.from ? nodeById(thread.from) : null;
            var to = nodeById(thread.to);

            if (!from || !to || !rows[from.id] || !rows[to.id]) {
                return;
            }

            var field = slot(to.id, 'field', thread.field);
            var x1 = from.x + slot(from.id, 'out', thread.token).left + DATA_ROW.half + start[index];
            var x2 = to.x + field.left + field.width / 2 + end[index];
            var y1 = from.y + BOX_H + DATA_ROW.half;
            var y2 = to.y + BOX_H + DATA_ROW.half;
            var sag = 10 + 0.06 * Math.abs(x2 - x1) + 4 * pairs[from.id + ' ' + to.id].indexOf(thread);
            var others = state.graph.nodes.filter(function (node) { return (node !== from) && (node !== to); });
            var path = svgEl('path', 'wsf-thread ' + toneClass(thread) + (thread.skipped ? ' wsf-thread-skipped' : ''));

            path.setAttribute('d', threadPath(x1, y1, x2, y2, sag, others).d);
            path.setAttribute('data-from', from.id);
            path.setAttribute('data-to', to.id);
            path.setAttribute('data-field', thread.field);
            path.setAttribute('data-ends', from.id + ' ' + to.id);
            ui.svg.appendChild(path);
        });

        markThreads();

        return targets.length;
    }

    // A dot a thread may be drawn from: dragged to a field chip, tapped twice
    // (or its menu's Fork, a long press on a touch screen) to choose the
    // field with the next tap; the menu's "Pass to a field…" asks for it.
    function liveDot(dot, node, token) {
        dot.classList.add('wsf-data-live');
        dot.setAttribute('data-node', node.id);
        dot.setAttribute('data-token', token);
        dot.title = (dot.title ? dot.title + '\n' : '') + t('fl_thread_dot');

        if (state.binding && (state.binding.from === node.id) && (state.binding.token === token)) {
            dot.classList.add('wsf-data-hot');
        }

        if (K.onContext) {
            K.onContext(dot, function () {
                return [
                    { label: t('fl_thread_fork'), icon: 'bi-bezier2', action: function () { startBinding(node.id, token); } },
                    { label: t('fl_thread_pass'), icon: 'bi-keyboard', action: function () { askBind(node, token); } }
                ];
            });
        }
    }

    // A thread on its way from a value: the fields it may go into light up
    // until one is chosen, the thread is let go, or Esc.
    function startBinding(from, token, quiet) {
        state.binding = { from: from, token: token };
        ui.viewport.classList.add('wsf-binding');

        if (!drawData()) {
            K.toast(t('fl_thread_none'), 'secondary');
            endBinding();
            return false;
        }

        if (!quiet) {
            announce(t('fl_thread_pick'));
        }

        return true;
    }

    function endBinding() {
        if (!state.binding) {
            return;
        }

        state.binding = null;
        ui.viewport.classList.remove('wsf-binding');
        drawData();
    }

    // A thread followed by the pointer: from the dot, hanging as a drawn one.
    function hangingPath(x1, y1, x2, y2) {
        return 'M' + x1 + ' ' + y1 + ' Q' + ((x1 + x2) / 2) + ' ' + (Math.max(y1, y2) + 10 + 0.06 * Math.abs(x2 - x1)) + ' ' + x2 + ' ' + y2;
    }

    // "Pass a value to a field" for the keyboard (V) and a dot's menu: one of
    // the box's values into a field it may go into.
    function askBind(node, token) {
        if (!state.edit || state.run || !node) {
            return;
        }

        var values = boxValues(node);
        var body = el('div');
        var value = K.select(values.map(function (item) { return [item.token, item.label]; }), token || (values[0] ? values[0].token : ''));
        var into = K.select([], '');
        var fill = function () {
            K.clear(into);
            bindTargets(node.id, value.value).forEach(function (target) {
                var option = el('option', '', nameOf(target.node) + ' › ' + target.label);

                option.value = target.node.id + ' ' + target.key;
                into.appendChild(option);
            });
        };

        if (values.length) {
            fill();
        }

        if (!into.options.length) {
            K.toast(t('fl_thread_none'), 'secondary');
            return;
        }

        value.className = into.className = 'form-control';
        value.addEventListener('change', fill);
        body.appendChild(field(t('fl_cond_value_ref'), value));
        body.appendChild(field(t('fl_thread_field'), into));

        K.ask(t('fl_thread_title', nameOf(node)), t('fl_thread_add'), false, body).then(function (yes) {
            if (yes && into.value) {
                var parts = into.value.split(' ');

                bindValue(node.id, value.value, parts[0], parts[1]);
            }

            state.selected = { type: 'node', id: node.id };
            markSelected();
            focusSelected();
        });
    }

    // With a box selected its threads are drawn at full colour, the others
    // dimmed; with none (or an arrow) all of them.
    function markThreads() {
        var selected = state.selected;
        var id = (selected && (selected.type === 'node')) ? String(selected.id) : '';

        Array.prototype.forEach.call(ui.layer.querySelectorAll('.wsf-thread, .wsf-data'), function (item) {
            item.classList.toggle('wsf-dim', !!id && ((item.getAttribute('data-ends') || '').split(' ').indexOf(id) === -1));
        });
    }

    // The "Data threads" toggle: shown unless the viewer hid them, kept in
    // this browser.
    function threadsShown() {
        try {
            return window.localStorage.getItem('pg_wsf_threads') !== 'off';
        } catch (error) {
            return true;
        }
    }

    function threadsButton() {
        var button = K.button('btn btn-sm btn-ghost' + (state.threads ? ' active' : ''), t('fl_threads'), 'bi-bezier2');

        button.setAttribute('aria-pressed', state.threads ? 'true' : 'false');
        button.addEventListener('click', function () {
            state.threads = !state.threads;
            button.classList.toggle('active', state.threads);
            button.setAttribute('aria-pressed', state.threads ? 'true' : 'false');
            ui.stage.classList.toggle('wsf-threads-off', !state.threads);

            try {
                window.localStorage.setItem('pg_wsf_threads', state.threads ? 'on' : 'off');
            } catch (error) {
                // Not kept: shown again next time.
            }
        });

        return button;
    }

    // What each box reads, in words under its heading in the outline: the
    // canvas's threads for a screen reader and a phone.
    function drawReads(threads) {
        Array.prototype.forEach.call(ui.outlineBox.querySelectorAll('.wsf-outline-item[data-node]'), function (item) {
            var id = item.getAttribute('data-node');
            var reads = threads.filter(function (thread) { return thread.to === id; });
            var old = item.querySelector('.wsf-outline-reads');

            if (old) {
                old.remove();
            }

            if (!reads.length) {
                return;
            }

            var list = el('ul', 'wsf-outline-reads list-unstyled small');

            list.setAttribute('aria-label', t('fl_threads'));
            reads.forEach(function (thread) {
                var line = el('li', toneClass(thread) + (thread.from ? '' : ' wsf-data-bad'));
                var text = t('fl_thread_line', thread.fieldLabel, thread.label);

                // A run shown: the value the thread brought in it.
                if (!thread.from) {
                    text += ' ' + t('fl_values_missing');
                } else if (state.run) {
                    var value = runValue(thread);

                    text = t('fl_run_pair', text, value.note || value.text);
                } else if (thread.skipped) {
                    text += ' (' + t('fl_values_skipped') + ')';
                }

                line.appendChild(K.icon(thread.from ? 'bi-arrow-return-right' : 'bi-exclamation-triangle', 'wsf-outline-read-icon'));
                line.appendChild(el('span', '', text));
                list.appendChild(line);
            });

            item.insertBefore(list, item.querySelector('.wsf-outline-head').nextSibling);
        });
    }

    // What the palette offers: an MCP box only while there is a server the
    // person may call with a tool that reads (ws_step_mcp_form_servers()), a
    // row box only while there is a table they read (ws_flow_row_tables()).
    function paletteItems() {
        var mcp = stepLists().mcp.length > 0;
        var tables = stepLists().tables.length > 0;

        return PALETTE.filter(function (item) {
            return ((item[0] !== 'mcp') || mcp) && ((item[0] !== 'row') || tables);
        });
    }

    // The buttons that add a box; in the palette they may also be dragged
    // to where the box should go.
    function paletteButtons(className, draggable) {
        return paletteItems().map(function (item) {
            return paletteButton(item, className, draggable);
        });
    }

    function paletteButton(item, className, draggable) {
        var add = K.button(className, t(item[1]));

        add.insertBefore(K.flowIcon(item[0], 'me-1'), add.firstChild);

        // One box starts a flow; a second would be a problem to fix.
        if ((item[0] === 'manual') && startNode()) {
            add.disabled = true;
            add.title = t('fl_one_start');
        }

        add.addEventListener('click', function () { addItem(item[0]); });
        // The outline's buttons take the focus when the last box there is
        // removed.
        add.setAttribute('data-refocus', (draggable ? 'palette-add-' : 'outline-add-') + item[0]);

        if (draggable) {
            add.draggable = true;
            add.addEventListener('dragstart', function (event) {
                event.dataTransfer.setData('text/plain', 'wsf:' + item[0]);
                event.dataTransfer.effectAllowed = 'copy';
            });
        }

        return add;
    }

    // The box that starts the flow, null before it has one.
    function startNode() {
        return state.graph.nodes.filter(function (node) { return KINDS[node.kind] && (KINDS[node.kind].group === 'start'); })[0] || null;
    }

    // The palette's groups in their order, each with the items whose name
    // holds every word searched for; a group with none is left out.
    function paletteGroups(items, query) {
        var words = String(query || '').toLowerCase().split(/\s+/).filter(Boolean);

        return PALETTE_GROUPS.map(function (group) {
            return {
                group: group[0],
                items: items.filter(function (item) {
                    var name = t(item[1]).toLowerCase();

                    return KINDS[item[0]] && (KINDS[item[0]].group === group[0]) && words.every(function (word) { return name.indexOf(word) !== -1; });
                })
            };
        }).filter(function (group) { return group.items.length > 0; });
    }

    // A group's heading; the start group names the flow's start once it
    // has one.
    function paletteHeading(group) {
        var start = (group === 'start') ? startNode() : null;

        return start ? t('fl_palette_this_flow', t(kindOf(start).name)) : t(PALETTE_GROUPS.filter(function (item) { return item[0] === group; })[0][1]);
    }

    // The editor's left column: Start from a template (while the flow is
    // empty), a search field and the boxes to add in their groups. Only
    // whoever edits the flow has it.
    function drawPalette() {
        var shown = !!(state.edit && state.flow);

        K.clear(ui.palette);
        ui.app.classList.toggle('wsf-no-palette', !shown);

        if (!shown) {
            return;
        }

        templateButton(ui.palette);

        var search = el('input', 'form-control form-control-sm wsf-palette-search');

        search.type = 'search';
        search.value = state.paletteQuery;
        search.placeholder = t('fl_palette_search');
        search.setAttribute('aria-label', t('fl_palette_search'));
        search.setAttribute('data-refocus', 'palette-search');
        search.addEventListener('input', function () {
            state.paletteQuery = search.value;
            drawPaletteGroups();
        });
        ui.palette.appendChild(search);
        ui.paletteGroups = el('div', 'wsf-palette-groups');
        ui.palette.appendChild(ui.paletteGroups);
        drawPaletteGroups();
    }

    function drawPaletteGroups() {
        var groups = paletteGroups(paletteItems(), state.paletteQuery);

        K.clear(ui.paletteGroups);
        ui.paletteStart = null;

        groups.forEach(function (group) {
            var wrap = el('div', 'wsf-palette-group wsf-group-' + group.group);
            var head = el('h3', 'wsf-palette-head', paletteHeading(group.group));

            head.id = 'wsf-palette-' + group.group;
            wrap.setAttribute('role', 'group');
            wrap.setAttribute('aria-labelledby', head.id);
            wrap.appendChild(head);

            if (group.group === 'start') {
                ui.paletteStart = head;
            }

            group.items.forEach(function (item) {
                wrap.appendChild(paletteButton(item, 'btn btn-sm btn-ghost wsf-palette-item', true));
            });
            ui.paletteGroups.appendChild(wrap);
        });

        if (!groups.length) {
            ui.paletteGroups.appendChild(el('p', 'small fg-secondary', t('fl_palette_none')));
        }
    }

    // "Start from a template" above the boxes, while the flow has none: the
    // template is placed on this flow's canvas.
    function templateOffered() {
        return !!((FL.templates || []).length && !state.graph.nodes.length && !state.run);
    }

    function templateButton(parent) {
        if (!templateOffered()) {
            return;
        }

        var pick = K.button('btn btn-sm btn-ghost wsf-palette-item wsf-palette-template', t('fl_tpl_from_template'), 'bi-grid-1x2');
        var into = state.flow.id;

        pick.setAttribute('data-refocus', 'palette-template');
        pick.addEventListener('click', function () {
            showGallery(into);
        });
        parent.appendChild(pick);
    }

    // ── Marks of a patch from Pinegrap AI ──────────────────────────────

    // The marks of the patch the flow carries, unless the viewer hid that
    // patch's marks in this browser.
    function marksOf(flow) {
        var given = flow.ai_marks;

        if (!state.edit || !given || !given.boxes || !given.boxes.length || marksHidden(given.change_id)) {
            return null;
        }

        var marks = { change: given.change_id, boxes: {}, order: [] };

        given.boxes.forEach(function (box) {
            marks.boxes[box.id] = (box.mark === 'added') ? 'added' : 'changed';
            marks.order.push(box.id);
        });

        return marks;
    }

    function marksHidden(change) {
        try {
            return window.localStorage.getItem('pg_wsf_marks_hidden') === String(change);
        } catch (error) {
            return false;
        }
    }

    function hideMarks() {
        try {
            window.localStorage.setItem('pg_wsf_marks_hidden', String(state.marks.change));
        } catch (error) {
            // Not kept: the marks come back when the flow is opened again.
        }

        state.marks = null;
        drawAll();
        announce(t('fl_ai_hidden'));
        ui.viewport.focus({ preventScroll: true });
    }

    function markOf(id) {
        return (state.marks && !state.run && state.marks.boxes[id]) || '';
    }

    // A box (on the canvas or in the outline) a patch added or changed: its
    // outline, a badge in its heading and the word in its name.
    function paintMark(box, head, node) {
        var mark = markOf(node.id);
        var badge = head.querySelector('.wsf-ai-badge');

        box.classList.toggle('wsf-ai-added', mark === 'added');
        box.classList.toggle('wsf-ai-changed', mark === 'changed');

        if (badge) {
            badge.remove();
        }

        if (!mark) {
            return;
        }

        badge = el('span', 'wsf-ai-badge', t(mark === 'added' ? 'fl_ai_added' : 'fl_ai_changed'));
        badge.insertBefore(K.icon('bi-stars'), badge.firstChild);
        badge.title = t(mark === 'added' ? 'fl_ai_added_long' : 'fl_ai_changed_long');
        head.appendChild(badge);

        if (box.hasAttribute('aria-label')) {
            box.setAttribute('aria-label', box.getAttribute('aria-label') + ' ' + badge.title + '.');
        }
    }

    // Over the canvas: what the patch added or changed, each box a button
    // that selects it, and the way to hide the marks.
    function drawMarks() {
        K.clear(ui.marksBox);

        var ids = state.marks ? state.marks.order.filter(function (id) { return !!markOf(id) && !!nodeById(id); }) : [];

        ui.marksBox.classList.toggle('d-none', !ids.length);

        if (!ids.length) {
            return;
        }

        var text = el('span', 'wsf-ai-marks-text');

        text.appendChild(K.icon('bi-stars', 'me-1'));
        text.appendChild(document.createTextNode(t('fl_ai_marked')));
        ui.marksBox.appendChild(text);

        ids.forEach(function (id) {
            var node = nodeById(id);
            var go = K.button('btn btn-sm btn-ghost wsf-ai-go', '', state.marks.boxes[id] === 'added' ? 'bi-plus-circle' : 'bi-pencil');

            go.appendChild(nameText(node));
            go.title = t(state.marks.boxes[id] === 'added' ? 'fl_ai_added_long' : 'fl_ai_changed_long');
            go.addEventListener('click', function () {
                if (nodeById(id)) {
                    select({ type: 'node', id: id }, { reveal: true, focus: true });
                }
            });
            ui.marksBox.appendChild(go);
        });

        var hide = K.button('btn btn-sm btn-link ms-auto', t('fl_ai_hide'));

        hide.addEventListener('click', hideMarks);
        ui.marksBox.appendChild(hide);
    }

    function drawSketch() {
        K.clear(ui.sketchBox);

        var shown = !!(state.sketch && state.flow && state.edit && (state.sketch.flow === state.flow.id));

        ui.sketchBox.classList.toggle('d-none', !shown);

        if (!shown) {
            return;
        }

        var text = el('p', 'wsf-ai-marks-text mb-0');
        var hide = K.button('btn btn-sm btn-link ms-auto', t('fl_ai_sketch_hide'));

        text.tabIndex = -1;
        text.setAttribute('data-refocus', 'ai-sketch');
        text.appendChild(K.icon('bi-stars', 'me-1'));
        text.appendChild(document.createTextNode(t('fl_ai_sketch_drafted')));
        ui.sketchBox.appendChild(text);
        hide.addEventListener('click', function () {
            state.sketch = null;
            drawSketch();

            if (ui.name) {
                ui.name.focus();
            }
        });
        ui.sketchBox.appendChild(hide);

        if (state.sketch.notes.length) {
            var notes = el('ul', 'wsf-ai-sketch-notes mb-0');

            state.sketch.notes.forEach(function (note) {
                notes.appendChild(el('li', '', note));
            });
            ui.sketchBox.appendChild(notes);
        }
    }

    function markSelected() {
        var selected = state.selected;

        drawData();

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

        var warnings = {};

        (state.run ? [] : state.problems).forEach(function (problem) {
            if (problem.node) {
                (byNode[problem.node] = byNode[problem.node] || []).push(problem.text);
            }

            if ((problem.edge !== null) && (problem.edge !== undefined)) {
                byEdge[problem.edge] = true;
            }
        });

        // A warning is told on its box, which is not outlined for it.
        (state.run ? [] : state.warnings).forEach(function (warning) {
            if (warning.node) {
                (warnings[warning.node] = warnings[warning.node] || []).push(warning.text);
            }
        });

        Array.prototype.forEach.call(ui.root.querySelectorAll('[data-node]'), function (box) {
            var texts = byNode[box.getAttribute('data-node')];
            var told = (texts || []).concat(warnings[box.getAttribute('data-node')] || []);

            box.classList.toggle('wsf-bad', !!texts);

            if (!state.run) {
                box.title = told.join('\n');
            }
        });

        Array.prototype.forEach.call(ui.svg.querySelectorAll('.wsf-edge'), function (group) {
            group.classList.toggle('wsf-bad', !!byEdge[group.getAttribute('data-edge')]);
        });
    }

    function drawProblems() {
        K.clear(ui.problemBox);

        if (!state.edit || (!state.problems.length && !state.warnings.length)) {
            ui.problemBox.classList.add('d-none');
            return;
        }

        ui.problemBox.classList.toggle('wsf-warned', !state.problems.length);

        if (state.problems.length) {
            ui.problemBox.appendChild(el('div', 'wsf-side-title', t('fl_problems', state.problems.length)));
            ui.problemBox.appendChild(problemList(state.problems, 'wsf-problem', 'bi-exclamation-triangle'));
        }

        if (state.warnings.length) {
            ui.problemBox.appendChild(el('div', 'wsf-side-title', t('fl_warnings', state.warnings.length)));
            ui.problemBox.appendChild(problemList(state.warnings, 'wsf-problem wsf-warning', 'bi-exclamation-circle'));
        }
    }

    // A list of problems or warnings, each leading to its box or arrow.
    function problemList(problems, className, icon) {
        var list = el('ul', 'list-unstyled mb-0');

        problems.forEach(function (problem) {
            var row = K.button(className, problem.text, icon);

            if (problem.node && nodeById(problem.node)) {
                row.addEventListener('click', function () { select({ type: 'node', id: problem.node }, { reveal: true, focus: true }); });
            } else if ((problem.edge !== null) && (problem.edge !== undefined) && state.graph.edges[problem.edge]) {
                row.addEventListener('click', function () { select({ type: 'edge', id: problem.edge }, { reveal: true }); });
            } else {
                row.disabled = true;
            }

            list.appendChild(el('li')).appendChild(row);
        });

        return list;
    }

    // ── Settings ───────────────────────────────────────────────────────

    function field(label, control, help) {
        var wrap = el('div', 'mb-5');
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

    // ── Values a box writes in ({{node.<id>.<field>}}, {{trigger.<field>}}) ──

    // What the server reads as a value (WS_STEP_VALUE_PATTERN, the
    // reference check): a box's field, or one of what started the run.
    var VALUE_TOKEN = /\{\{(?:node\.([a-z][a-z0-9_]{0,31})\.([a-z_][a-z0-9_]{0,31})|trigger\.([a-z_][a-z0-9_]{0,31}))\}\}/g;

    // The arrows of the graph once each, as ws_flow_graph_check() reads
    // them: id => ids an arrow leads into, and id => id => true for an arrow
    // taken whenever its box is done (on always, or on its only outcome).
    function arrowMaps() {
        var next = {};
        var always = {};

        state.graph.nodes.forEach(function (node) {
            next[node.id] = [];
            always[node.id] = {};
        });

        state.graph.edges.forEach(function (edge) {
            if (!next[edge.from] || !next[edge.to] || (edge.from === edge.to)) {
                return;
            }

            var out = kindOf(nodeById(edge.from)).out;

            if ((edge.on === 'always') || ((out.length === 1) && (edge.on === out[0]))) {
                always[edge.from][edge.to] = true;
            }

            if (next[edge.from].indexOf(edge.to) === -1) {
                next[edge.from].push(edge.to);
            }
        });

        return { next: next, always: always };
    }

    // The boxes some arrows lead from to a box (ws_flow_boxes_before()).
    function boxesBefore(into, id) {
        var before = {};
        var queue = [id];

        while (queue.length) {
            (into[queue.shift()] || []).forEach(function (from) {
                if (!before[from]) {
                    before[from] = true;
                    queue.push(from);
                }
            });
        }

        return before;
    }

    // The boxes that have surely run, done, whenever a box runs, as
    // ws_flow_sure_before() decides it: a value of any other box before it
    // is empty when that box was skipped. A box on a loop is left out.
    function sureBefore(maps) {
        var into = {};
        var left = {};
        var queue = [];
        var order = [];
        var sure = {};

        Object.keys(maps.next).forEach(function (id) {
            into[id] = [];
        });
        Object.keys(maps.next).forEach(function (from) {
            maps.next[from].forEach(function (to) {
                into[to].push(from);
            });
        });
        Object.keys(into).forEach(function (id) {
            left[id] = into[id].length;

            if (!left[id]) {
                queue.push(id);
            }
        });

        while (queue.length) {
            var id = queue.shift();

            order.push(id);
            maps.next[id].forEach(function (to) {
                if (--left[to] === 0) {
                    queue.push(to);
                }
            });
        }

        order.forEach(function (id) {
            var before = boxesBefore(into, id);
            var set = null;
            var grew = true;

            into[id].forEach(function (from) {
                var both = {};

                Object.keys(sure[from]).forEach(function (key) {
                    if (!set || set[key]) {
                        both[key] = true;
                    }
                });
                set = both;
            });
            set = set || {};
            set[id] = true;

            while (grew) {
                grew = false;
                Object.keys(before).forEach(function (other) {
                    if (set[other]) {
                        return;
                    }

                    if (into[other].some(function (from) { return set[from] && maps.always[from][other]; })) {
                        Object.keys(sure[other]).forEach(function (key) {
                            set[key] = true;
                        });
                        grew = true;
                    }
                });
            }

            sure[id] = set;
        });

        return sure;
    }

    // What a box gives the boxes after it, as the server declares it
    // (ws_flow_trigger_outputs(), ws_flow_node_fields()): a start box what
    // started the run, any other its text, outcome, status and outputs, a
    // web check's by its mode, a report's counts by the counts it sends.
    function boxValues(node) {
        var outputs = FL.trigger_outputs || {};
        var list = [];
        var keys = {};
        var add = function (token, item) {
            if (!keys[item.key]) {
                keys[item.key] = true;
                list.push({ token: '{{' + token + item.key + '}}', label: item.label, type: item.type || 'text' });
            }
        };

        // A derived value box gives one value of its rule's type
        // (ws_flow_derived_outputs()).
        if (node.kind === 'value') {
            add('node.' + node.id + '.', { key: 'value', label: t('fl_derived_value'), type: derivedRule(node.config.rule).type });

            return list;
        }

        // A constants box gives its rows (ws_flow_constants_outputs()).
        if (kindOf(node).pure) {
            (node.config.rows || []).forEach(function (row) {
                if (/^[a-z_][a-z0-9_]{0,31}$/.test(row.key || '')) {
                    add('node.' + node.id + '.', { key: row.key, label: row.key, type: (row.type === 'number') ? 'number' : 'text' });
                }
            });

            return list;
        }

        if (!kindOf(node).into) {
            ((outputs.kinds || {})[node.kind] || []).forEach(function (item) { add('trigger.', item); });

            if (node.kind === 'event') {
                ((outputs.events || {})[node.config.event] || []).forEach(function (item) { add('trigger.', item); });
            }

            if (node.kind === 'row') {
                rowCellValues(node.config).forEach(function (item) { add('trigger.', item); });
            }

            return list;
        }

        var prefix = 'node.' + node.id + '.';
        var mode = (node.config.mode === 'ssl') ? 'ssl' : 'status';

        add(prefix, { key: 'text', label: t('fl_values_text') });
        add(prefix, { key: 'outcome', label: t('fl_values_outcome') });
        add(prefix, { key: 'status', label: t('fl_values_status') });
        ((FL.outputs || {})[node.kind] || []).forEach(function (item) {
            if (!item.mode || (item.mode === mode)) {
                add(prefix, item);
            }
        });

        if (node.kind === 'report') {
            (node.config.metrics || []).forEach(function (item) {
                var key = String(item.key || '') + (((parseInt(item.param, 10) || 0) > 0) ? '_' + parseInt(item.param, 10) : '');
                var metric = stepLists().metrics.filter(function (one) { return one.key === item.key; })[0];

                if (/^[a-z_][a-z0-9_]{0,31}$/.test(key)) {
                    add(prefix, { key: key, label: metric ? metric.label : item.key });
                }
            });
        }

        return list;
    }

    // The values a box may write in: what started the run, then each box
    // before it in arrow order, one that may not have run marked (its
    // values are then empty).
    function valueGroups(node) {
        var maps = arrowMaps();
        var into = {};
        var sure = null;
        var groups = [];

        Object.keys(maps.next).forEach(function (id) {
            into[id] = [];
        });
        Object.keys(maps.next).forEach(function (from) {
            maps.next[from].forEach(function (to) {
                into[to].push(from);
            });
        });

        var before = boxesBefore(into, node.id);
        // A derived value box has no place among the arrows: it may read any
        // box, checked at the boxes that read it.
        var reader = node.kind === 'value';

        orderedNodes().forEach(function (other) {
            // A value box gives to every box, read whenever it is.
            var pure = !!kindOf(other).pure;
            var start = !kindOf(other).into && !pure;

            if ((other.id === node.id) || (!start && !pure && !reader && !before[other.id])) {
                return;
            }

            var values = boxValues(other);

            if (!values.length) {
                return;
            }

            sure = sure || sureBefore(maps);
            groups.push({ node: other, values: values, skipped: !start && !pure && !reader && !!sure[node.id] && !sure[node.id][other.id] });
        });

        return groups;
    }

    // token => the box that gives it, the value and whether that box may be
    // skipped.
    function valueSources(node) {
        var sources = {};

        valueGroups(node).forEach(function (group) {
            group.values.forEach(function (value) {
                sources[value.token] = { node: group.node, value: value, skipped: group.skipped };
            });
        });

        return sources;
    }

    // token => what its chip reads and whether the box may be skipped.
    function valueNames(node) {
        var names = {};
        var sources = valueSources(node);

        Object.keys(sources).forEach(function (token) {
            names[token] = { label: nameOf(sources[token].node) + ' › ' + sources[token].value.label, skipped: sources[token].skipped };
        });

        return names;
    }

    // A value as the builder reads it; one no box before gives keeps its
    // token, marked (the reference check names it a problem).
    function valueChip(token, names) {
        var name = names[token];
        var chip = el('span', 'wsf-chip' + (name ? (name.skipped ? ' wsf-chip-skipped' : '') : ' wsf-chip-bad'), name ? name.label : token.slice(2, -2));

        chip.contentEditable = 'false';
        chip.setAttribute('data-token', token);
        chip.title = name ? (name.skipped ? t('fl_values_skipped') : token) : t('fl_values_missing');

        return chip;
    }

    function drawValues(input, text, names) {
        var at = 0;
        var match;
        var lines = function (part) {
            part.split('\n').forEach(function (line, index) {
                if (index) {
                    input.appendChild(el('br'));
                }

                if (line !== '') {
                    input.appendChild(document.createTextNode(line));
                }
            });
        };

        K.clear(input);
        VALUE_TOKEN.lastIndex = 0;

        while ((match = VALUE_TOKEN.exec(text)) !== null) {
            lines(text.slice(at, match.index));
            input.appendChild(valueChip(match[0], names));
            at = match.index + match[0].length;
        }

        lines(text.slice(at));

        // A line break at the end shows only with one more after it, which
        // valuesText() does not read.
        if (text.slice(-1) === '\n') {
            input.appendChild(el('br'));
        }
    }

    // The text with its tokens, as saved: a chip gives its token, a line
    // break "\n" (none on a single line), the break that ends a block or
    // the box nothing.
    function valuesText(input, multi) {
        var out = '';
        var walk = function (parent) {
            Array.prototype.forEach.call(parent.childNodes, function (child, index) {
                if (child.nodeType === 3) {
                    out += child.nodeValue.replace(/ /g, ' ');
                } else if (child.nodeType !== 1) {
                    return;
                } else if (child.hasAttribute('data-token')) {
                    out += child.getAttribute('data-token');
                } else if (child.nodeName === 'BR') {
                    out += Array.prototype.slice.call(parent.childNodes, index + 1).some(function (after) {
                        return (after.nodeType === 1) || ((after.nodeType === 3) && (after.nodeValue !== ''));
                    }) ? '\n' : '';
                } else {
                    if (/^(DIV|P|LI)$/.test(child.nodeName) && (out !== '') && (out.slice(-1) !== '\n')) {
                        out += '\n';
                    }

                    walk(child);
                }
            });
        };

        walk(input);

        return multi ? out : out.replace(/[\r\n]+/g, ' ');
    }

    // A text a box writes in, with its values as chips: the builder never
    // types an id. Pasted or saved tokens become chips too.
    function valueInput(node, value, max, onInput, multi) {
        var input = el('div', 'form-control form-control-sm wsf-values' + (multi ? ' wsf-values-multi' : ''));
        var names = valueNames(node);
        var last = value || '';
        var range = null;
        var keep = function () {
            var selection = window.getSelection();

            if (selection && selection.rangeCount && input.contains(selection.getRangeAt(0).startContainer)) {
                range = selection.getRangeAt(0).cloneRange();
            }
        };
        var caret = function (place) {
            var selection = window.getSelection();

            input.focus();
            selection.removeAllRanges();
            selection.addRange(place);
            range = place.cloneRange();
        };
        var atEnd = function () {
            var place = document.createRange();

            place.selectNodeContents(input);
            place.collapse(false);

            if (input.lastChild && (input.lastChild.nodeName === 'BR')) {
                place.setStartBefore(input.lastChild);
                place.collapse(true);
            }

            return place;
        };
        // The chip right before (or after) a collapsed caret, empty text
        // between skipped: the browser does not always delete one.
        var chipBeside = function (back) {
            var selection = window.getSelection();

            if (!selection || !selection.rangeCount || !selection.isCollapsed || !input.contains(selection.anchorNode)) {
                return null;
            }

            var at = selection.anchorNode;
            var offset = selection.anchorOffset;
            var near;

            if (at.nodeType === 3) {
                if (back ? (offset > 0) : (offset < at.nodeValue.length)) {
                    return null;
                }

                near = back ? at.previousSibling : at.nextSibling;
            } else {
                near = at.childNodes[back ? offset - 1 : offset] || null;
            }

            while (near && (near.nodeType === 3) && (near.nodeValue === '')) {
                near = back ? near.previousSibling : near.nextSibling;
            }

            return (near && (near.nodeType === 1) && near.hasAttribute('data-token')) ? near : null;
        };
        var redraw = function (text) {
            drawValues(input, text, names);
            caret(atEnd());
        };
        var changed = function () {
            var text = valuesText(input, multi);

            if (text.length > max) {
                redraw(last);
                return;
            }

            last = text;
            onInput(text);
        };

        input.setAttribute('role', 'textbox');
        input.setAttribute('aria-multiline', multi ? 'true' : 'false');
        input.contentEditable = state.edit ? 'true' : 'false';

        if (!state.edit) {
            input.setAttribute('aria-readonly', 'true');
        }

        drawValues(input, last, names);

        input.addEventListener('keydown', function (event) {
            var chip = ((event.key === 'Backspace') || (event.key === 'Delete')) ? chipBeside(event.key === 'Backspace') : null;

            if (chip) {
                var place = document.createRange();

                event.preventDefault();
                place.setStartBefore(chip);
                place.collapse(true);
                chip.parentNode.removeChild(chip);
                caret(place);
                changed();
            } else if (event.key === 'Enter') {
                event.preventDefault();

                if (multi) {
                    document.execCommand('insertLineBreak');
                }
            } else if ((event.ctrlKey || event.metaKey) && /^[biu]$/i.test(event.key)) {
                event.preventDefault();
            }
        });
        input.addEventListener('paste', function (event) {
            var text = ((event.clipboardData || window.clipboardData).getData('text') || '').replace(/\r\n?/g, '\n');

            event.preventDefault();
            document.execCommand('insertText', false, multi ? text : text.replace(/\n+/g, ' '));
            VALUE_TOKEN.lastIndex = 0;

            if (VALUE_TOKEN.test(text)) {
                redraw(valuesText(input, multi));
                changed();
            }
        });
        input.addEventListener('drop', function (event) { event.preventDefault(); });
        input.addEventListener('input', changed);
        input.addEventListener('keyup', keep);
        input.addEventListener('mouseup', keep);
        input.addEventListener('blur', keep);

        // Puts a value where the caret was, else at the end.
        input.wsfInsert = function (token) {
            var place = (range && input.contains(range.startContainer)) ? range : atEnd();
            var chip = valueChip(token, names);
            var after = document.createTextNode('');

            place.deleteContents();
            place.insertNode(chip);
            chip.parentNode.insertBefore(after, chip.nextSibling);
            place = document.createRange();
            place.setStart(after, 0);
            place.collapse(true);
            caret(place);
            changed();
        };

        return input;
    }

    // A field of a box's text with "Add value": the list of what started the
    // run and the boxes before it, each value a chip put in the text.
    function valueField(node, label, value, max, onInput, multi, help) {
        var input = valueInput(node, value, max, onInput, multi);
        var wrap = field(label, input, help ? help + ' ' + t('fl_values_hint') : t('fl_values_hint'));
        var caption = wrap.querySelector('label');

        // A label names a form control only; the text box is a div.
        caption.id = input.id + '-label';
        caption.removeAttribute('for');
        caption.addEventListener('click', function () { input.focus(); });
        input.setAttribute('aria-labelledby', caption.id);

        if (!state.edit) {
            return wrap;
        }

        var add = K.button('btn btn-sm btn-outline theme-secondary mt-2', t('fl_values_add'), 'bi-plus-lg');
        var list = el('div', 'wsf-values-list d-none');
        var close = function (focus) {
            list.classList.add('d-none');
            add.setAttribute('aria-expanded', 'false');

            if (focus) {
                add.focus();
            }
        };

        list.id = input.id + '-values';
        list.setAttribute('role', 'group');
        list.setAttribute('aria-label', t('fl_values_add'));
        add.setAttribute('aria-controls', list.id);
        add.setAttribute('aria-expanded', 'false');
        add.addEventListener('mousedown', function (event) {
            // The caret stays where the value goes.
            event.preventDefault();
        });
        add.addEventListener('click', function () {
            if (!list.classList.contains('d-none')) {
                close(false);
                return;
            }

            var groups = valueGroups(node);

            K.clear(list);

            if (!groups.length) {
                list.appendChild(el('div', 'small fg-secondary', t('fl_values_none')));
            }

            groups.forEach(function (group) {
                var head = el('div', 'wsf-values-group', nameOf(group.node));

                if (group.skipped) {
                    head.appendChild(el('span', 'badge badge-subtle theme-warning ms-2', t('fl_values_skipped')));
                }

                list.appendChild(head);
                group.values.forEach(function (value) {
                    var pick = K.button('wsf-values-pick', value.label);

                    pick.addEventListener('mousedown', function (event) { event.preventDefault(); });
                    pick.addEventListener('click', function () {
                        close(false);
                        input.wsfInsert(value.token);
                    });
                    list.appendChild(pick);
                });
            });

            list.classList.remove('d-none');
            add.setAttribute('aria-expanded', 'true');

            var first = list.querySelector('button');

            if (first) {
                first.focus();
            }
        });
        list.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close(true);
            }
        });

        input.parentNode.insertBefore(list, input.nextSibling);
        input.parentNode.insertBefore(add, list);

        return wrap;
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
        var list = el('ul', 'mb-0 ps-5 fg-secondary');

        keys.appendChild(el('summary', 'fw-semibold', t('fl_keys')));
        ['tab', 'arrows', 'connect', 'thread', 'delete', 'undo', 'enter', 'zoom', 'pan', 'escape'].forEach(function (key) {
            list.appendChild(el('li', '', t('fl_key_' + key)));
        });
        keys.appendChild(list);
        box.appendChild(el('p', 'small fg-secondary', state.edit ? t('fl_flow_help') : t('fl_read_only')));
        box.appendChild(keys);

        return box;
    }

    function nodeForm(node) {
        var box = el('div', 'wsf-form');
        var config = node.config;

        var titled = (node.kind === 'task') || (node.kind === 'task_open') || (node.kind === 'approval');
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

        if (node.kind === 'mcp') {
            mcpFields(box, node);
        }

        if (node.kind === 'condition') {
            conditionFields(box, node);
        }

        if (node.kind === 'delay') {
            delayFields(box, node);
        }

        if (node.kind === 'constants') {
            constantsFields(box, node);
        }

        if (node.kind === 'value') {
            derivedFields(box, node);
        }

        if (node.kind === 'start_flow') {
            chainFields(box, node);
        }

        if (node.kind === 'approval') {
            approvalFields(box, node);
        }

        retryFields(box, node);

        if (node.kind === 'schedule') {
            scheduleFields(box, node);
        }

        if (node.kind === 'event') {
            eventFields(box, node);
        }

        if (node.kind === 'join') {
            channelField(box, node, t('fl_join_channel'), t('fl_join_any'));
        }

        if (node.kind === 'row') {
            rowFields(box, node);
        }

        if (state.edit) {
            // Only the owner: the box is looked at with their rights.
            if (state.flow && state.flow.manage) {
                tryField(box, node);
            }

            box.appendChild(connections(node));

            var remove = K.button('btn btn-sm btn-outline theme-warning', t('fl_remove_box'), 'bi-trash');
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
        var wrap = el('div', 'wsf-try border-top pt-5 mb-5');
        var runs = K.select([['0', t('fl_try_no_run')]], '0');
        var go = K.button('btn btn-sm btn-outline theme-secondary', t('fl_try'), 'bi-eye');
        var out = el('div', 'wsf-try-out small mt-3');
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
                out.appendChild(el('div', 'fg-danger', (error && error.message) || t('error_generic')));
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
            out.appendChild(el('div', 'fg-danger', t('fl_try_fails', tried.refusal)));
        }

        (tried.texts || []).forEach(function (text) {
            var shown = el('div', 'border rounded-4 p-3 mt-1 text-break', text.text);

            shown.style.whiteSpace = 'pre-wrap';
            out.appendChild(shown);
        });

        if ((tried.empty || []).length) {
            out.appendChild(el('div', 'fg-secondary mt-1', t('fl_try_empty', tried.empty.join(', '))));
        }

        if ((tried.next || []).length) {
            out.appendChild(el('div', 'mt-1', t('fl_try_next', tried.next.join(' · '))));
        }

        if (tried.outcome && OUTCOMES[tried.outcome]) {
            out.appendChild(el('div', 'fg-secondary mt-1', t('fl_try_outcome', t(OUTCOMES[tried.outcome]))));
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
        var people = el('div', 'wsf-people mt-3');

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

        // Hours the box waits for its task before it leaves on timeout
        // (ws_flow_timeout_hours()); the task itself stays open.
        var timeout = el('input', 'form-control form-control-sm wsf-number');

        timeout.type = 'number';
        timeout.min = '1';
        timeout.max = '720';
        timeout.step = '1';
        timeout.value = (config.timeout === '' || config.timeout === undefined || config.timeout === null) ? '' : String(config.timeout);
        timeout.addEventListener('input', function () {
            config.timeout = (timeout.value === '') ? '' : clamp(parseInt(timeout.value, 10) || 1, 1, 720);
            refreshNode(node);
        });
        box.appendChild(field(t('fl_timeout'), timeout, t('fl_timeout_help')));

        var priorities = BOOT.priorities || {};
        var priority = K.select(Object.keys(priorities).map(function (key) { return [key, priorities[key]]; }), config.priority || 'normal');

        priority.addEventListener('change', function () {
            config.priority = priority.value;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_priority'), priority));

        box.appendChild(valueField(node, t('fl_description'), config.description, 8000, function (value) {
            config.description = value;
            refreshNode(node);
        }, true, t('fl_description_help')));
    }

    // A post box: the channel the owner writes in and the message. The store
    // checks the owner may write there.
    // The channels a step box may name, the first option for none, then the
    // options given first ([value, label] pairs: the channel joined), and
    // one the person does not read named as such.
    function channelPick(config, none, first) {
        var channels = [[0, none]].concat(first || []);

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

    // Whether the flow starts when somebody joins a channel: its post boxes
    // may then write in the channel joined (-1), as the server allows
    // (ws_flow_starts_on_join()).
    function startsOnJoin() {
        return state.graph.nodes.some(function (item) { return item.kind === 'join'; });
    }

    function postFields(box, node) {
        var config = node.config;
        var pick = channelPick(config, t('fl_post_pick'), (startsOnJoin() || (config.channel_id === -1)) ? [[-1, t('fl_post_joined')]] : []);

        pick.addEventListener('change', function () {
            config.channel_id = parseInt(pick.value, 10) || 0;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_post_channel'), pick));
        box.appendChild(valueField(node, t('fl_post_body'), config.body, 4000, function (value) {
            config.body = value;
            refreshNode(node);
        }, true));
    }

    // An e-mail box: addresses, subject and text, sent from the site.
    function emailFields(box, node) {
        var config = node.config;

        box.appendChild(field(t('fl_email_to'), textInput(config.to, 2000, function (value) {
            config.to = value;
            refreshNode(node);
        }), t('fl_email_to_help')));
        box.appendChild(valueField(node, t('fl_email_subject'), config.subject, 250, function (value) {
            config.subject = value;
            refreshNode(node);
        }, false));
        box.appendChild(valueField(node, t('fl_email_body'), config.body, 20000, function (value) {
            config.body = value;
            refreshNode(node);
        }, true));
    }

    // A notify box: the people told and the line they read.
    function notifyFields(box, node) {
        var config = node.config;

        box.appendChild(field(t('fl_notify_who'), peopleList(node, el('div', 'wsf-people'), 'user_ids', 50)));
        box.appendChild(valueField(node, t('fl_notify_text'), config.text, 500, function (value) {
            config.text = value;
            refreshNode(node);
        }, false));
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
        box.appendChild(valueField(node, t('fl_description'), config.description, 8000, function (value) {
            config.description = value;
            refreshNode(node);
        }, true));
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
            box.appendChild(valueField(node, t('fl_post_body'), config.text, 4000, function (value) {
                config.text = value;
                refreshNode(node);
            }, true));

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

            var redirect = el('input', 'check');

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
        var tick = el('input', 'check');

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
        box.appendChild(valueField(node, t('fl_email_subject'), config.subject, 250, function (value) {
            config.subject = value;
            refreshNode(node);
        }, false));
        box.appendChild(valueField(node, t('fl_digest_intro'), config.intro, 2000, function (value) {
            config.intro = value;
            refreshNode(node);
        }, true));

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
            var check = el('input', 'check m-0');

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

        box.appendChild(valueField(node, t('fl_report_title'), config.title, 160, function (value) {
            config.title = value;
            refreshNode(node);
        }, false));
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
        box.appendChild(valueField(node, t('fl_checkin_question'), node.config.question, 500, function (value) {
            node.config.question = value;
            refreshNode(node);
        }, false, t('fl_checkin_help')));
    }

    // A submitted form's customer brought into a channel: whether a channel
    // it opens is private, and who is put in it.
    function leadFields(box, node) {
        var config = node.config;
        var closed = el('input', 'check');

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

    // ── The MCP box ────────────────────────────────────────────────────

    // A server an MCP box may call, as the form offers it, null when the
    // person may not call it (off, no tool that reads, no account).
    function mcpServer(id) {
        return stepLists().mcp.filter(function (item) { return item.id === id; })[0] || null;
    }

    // Why the box's tool may not run, '' when it may or none is chosen yet:
    // the refusals of ws_step_mcp_tool_problem() and ws_step_mcp_check().
    function mcpToolProblem(config) {
        if (!config.server_id) {
            return '';
        }

        var server = mcpServer(config.server_id);

        if (!server) {
            return t('fl_mcp_server_out');
        }

        if (!config.tool) {
            return '';
        }

        if (server.asks.indexOf(config.tool) !== -1) {
            return t('fl_mcp_tool_asks', config.tool, server.alias);
        }

        return server.tools.some(function (tool) { return tool.name === config.tool; }) ? '' : t('fl_mcp_tool_off', config.tool, server.alias);
    }

    // Why the arguments are no JSON object, '' when they are one or empty:
    // read with each value empty, as ws_step_mcp_arguments() reads them, so
    // a value written outside quotes leaves no object.
    function mcpArgumentsProblem(text) {
        var bare = String(text || '').replace(VALUE_TOKEN, '').trim();
        var parsed = null;

        if (bare === '') {
            return '';
        }

        try {
            parsed = (bare.charAt(0) === '{') ? JSON.parse(bare) : null;
        } catch (error) {
            parsed = null;
        }

        return (parsed && (typeof parsed === 'object') && !Array.isArray(parsed)) ? '' : t('fl_mcp_arguments_bad');
    }

    // A tool of a connected MCP server that reads: the server, the tool and
    // its arguments as a JSON object, values of the run put inside quotes.
    function mcpFields(box, node) {
        var config = node.config;
        var servers = stepLists().mcp;
        var options = [[0, t('fl_mcp_server_pick')]].concat(servers.map(function (item) {
            return [item.id, '@' + item.alias + (item.name ? ' · ' + item.name : '')];
        }));

        if (config.server_id && !mcpServer(config.server_id)) {
            options.push([config.server_id, '#' + config.server_id]);
        }

        var pick = K.select(options, config.server_id || 0);
        var toolWrap = el('div');
        var about = el('div', 'form-text');
        var note = el('div', 'small fg-danger mb-5');
        var argNote = el('div', 'small fg-danger mb-5');
        var tell = function () {
            var server = mcpServer(config.server_id);
            var tool = server ? server.tools.filter(function (item) { return item.name === config.tool; })[0] : null;

            about.textContent = tool ? tool.description : '';
            note.textContent = mcpToolProblem(config);
            argNote.textContent = mcpArgumentsProblem(config.arguments);
        };
        var drawTools = function () {
            var server = mcpServer(config.server_id);
            var list = server ? server.tools : [];
            var choices = [['', t('fl_mcp_tool_pick')]].concat(list.map(function (item) { return [item.name, item.name]; }));

            if (config.tool && !list.some(function (item) { return item.name === config.tool; })) {
                choices.push([config.tool, config.tool]);
            }

            var tools = K.select(choices, config.tool || '');

            tools.setAttribute('data-refocus', 'mcp-tool-' + node.id);
            tools.addEventListener('change', function () {
                config.tool = tools.value;
                tell();
                refreshNode(node);
            });
            K.clear(toolWrap);
            toolWrap.appendChild(field(t('fl_mcp_tool'), tools, t('fl_mcp_tool_help')));
            toolWrap.appendChild(about);
        };

        pick.setAttribute('data-refocus', 'mcp-server-' + node.id);
        pick.addEventListener('change', function () {
            var server = mcpServer(parseInt(pick.value, 10) || 0);

            config.server_id = parseInt(pick.value, 10) || 0;

            // A tool of the server before stays only where this one has it.
            if (!server || !server.tools.some(function (item) { return item.name === config.tool; })) {
                config.tool = '';
            }

            drawTools();
            tell();
            refreshNode(node);
        });
        note.setAttribute('role', 'status');
        argNote.setAttribute('role', 'status');
        box.appendChild(field(t('fl_mcp_server'), pick, servers.length ? '' : t('fl_mcp_no_server')));
        box.appendChild(toolWrap);
        box.appendChild(note);
        box.appendChild(valueField(node, t('fl_mcp_arguments'), config.arguments, 20000, function (value) {
            config.arguments = value;
            argNote.textContent = mcpArgumentsProblem(value);
            refreshNode(node);
        }, true, t('fl_mcp_arguments_help')));
        box.appendChild(argNote);
        drawTools();
        tell();
    }

    // Another scheduled action, started now or some minutes later.
    // The flows a box may start: those the person runs or changes, other
    // than the one open; the server takes only a live one started by hand
    // (ws_flow_chain_check()).
    function chainTargets() {
        var open = state.flow ? state.flow.id : 0;

        return state.flows.filter(function (item) {
            return (item.id !== open) && ((item.access === 'run') || (item.access === 'edit'));
        });
    }

    // A box that starts another flow and goes on without waiting for it.
    function chainFields(box, node) {
        var config = node.config;
        var options = [[0, t('fl_chain_pick')]].concat(chainTargets().map(function (item) { return [item.id, item.name]; }));

        if (config.flow_id && !options.some(function (option) { return option[0] === config.flow_id; })) {
            options.push([config.flow_id, '#' + config.flow_id]);
        }

        var pick = K.select(options, config.flow_id || 0);

        pick.addEventListener('change', function () {
            config.flow_id = parseInt(pick.value, 10) || 0;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_chain_flow'), pick, t('fl_chain_help')));
    }

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

    // How long a delay box waits, in words: "2 hours", or that none is set.
    function delayText(config) {
        var amount = parseInt(config.amount, 10);
        var words = {
            minutes: ['fl_delay_minute', 'fl_delay_minutes'],
            hours: ['fl_delay_hour', 'fl_delay_hours'],
            days: ['fl_delay_day', 'fl_delay_days']
        }[config.unit] || ['fl_delay_minute', 'fl_delay_minutes'];

        if (!(amount > 0)) {
            return t('fl_delay_none');
        }

        return (amount === 1) ? t(words[0]) : t(words[1], amount);
    }

    // A delay box: how long it waits and the unit counted in, at most a
    // week (WS_FLOW_DELAY_MAX minutes), as a scheduled action's start delay.
    function delayFields(box, node) {
        var config = node.config;
        var amount = el('input', 'form-control form-control-sm wsf-number');
        var unit = K.select([
            ['minutes', t('fl_delay_unit_minutes')],
            ['hours', t('fl_delay_unit_hours')],
            ['days', t('fl_delay_unit_days')]
        ], config.unit || 'minutes');

        amount.type = 'number';
        amount.min = '1';
        amount.max = '10080';
        amount.value = (config.amount === '' || config.amount === undefined) ? '' : String(config.amount);
        amount.addEventListener('input', function () {
            config.amount = (amount.value === '') ? '' : clamp(parseInt(amount.value, 10) || 0, 0, 99999);
            refreshNode(node);
        });
        unit.addEventListener('change', function () {
            config.unit = unit.value;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_delay_amount'), amount, t('fl_delay_help')));
        box.appendChild(field(t('fl_delay_unit'), unit));
    }

    // How many times a step box tries again on its own after a failure,
    // and the minutes before each try (ws_flow_retry_normalize()).
    // A constants box's rows: a name (the key a thread reads), a type and a
    // value each; a name keeps to small letters, digits and _ as it is typed.
    function constantsFields(box, node) {
        var config = node.config;
        var list = el('div', 'wsf-constants');
        var redraw = function () {
            if (state.outline) {
                drawOutline();
            } else {
                drawSettings();
            }
        };

        config.rows = Array.isArray(config.rows) ? config.rows : [];
        list.setAttribute('role', 'group');
        list.setAttribute('aria-label', t('fl_constants_rows'));

        config.rows.forEach(function (row, index) {
            var line = el('div', 'wsf-constant');
            var name = textInput(row.key, 32, function (value) {
                var clean = value.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '').slice(0, 32);

                row.key = clean;

                if (name.value !== clean) {
                    name.value = clean;
                }

                refreshNode(node);
            });
            var type = K.select([['text', t('fl_constants_text')], ['number', t('fl_constants_number')]], (row.type === 'number') ? 'number' : 'text');
            var value = textInput(row.value, 500, function (text) {
                row.value = text;
                refreshNode(node);
            });
            var remove = K.button('btn btn-sm btn-ghost', '', 'bi-x-lg', t('fl_constants_remove', row.key || String(index + 1)));

            name.placeholder = t('fl_constants_name');
            name.setAttribute('aria-label', t('fl_constants_name'));
            name.setAttribute('data-refocus', 'constant-' + node.id + '-' + index);
            type.setAttribute('aria-label', t('fl_constants_type'));
            type.addEventListener('change', function () {
                row.type = type.value;
                refreshNode(node);
            });
            value.placeholder = t('fl_constants_value');
            value.setAttribute('aria-label', t('fl_constants_value'));
            value.inputMode = (row.type === 'number') ? 'decimal' : 'text';
            remove.addEventListener('click', function () {
                config.rows.splice(index, 1);
                state.refocus = 'constants-add-' + node.id;
                refreshNode(node);
                redraw();
            });

            line.appendChild(name);
            line.appendChild(type);
            line.appendChild(value);
            line.appendChild(remove);
            list.appendChild(line);
        });

        box.appendChild(el('div', 'form-label small fw-semibold', t('fl_constants_rows')));
        box.appendChild(list);

        var add = K.button('btn btn-sm btn-outline theme-secondary mb-5', t('fl_constants_add'), 'bi-plus-lg');

        add.setAttribute('data-refocus', 'constants-add-' + node.id);
        add.disabled = config.rows.length >= ((FL.limits && FL.limits.constants) || 20);
        add.addEventListener('click', function () {
            config.rows.push({ key: '', type: 'text', value: '' });
            state.refocus = 'constant-' + node.id + '-' + (config.rows.length - 1);
            refreshNode(node);
            redraw();
        });
        box.appendChild(add);
        box.appendChild(el('p', 'small fg-secondary', t('fl_constants_help')));
    }

    // A derived value box's rule (FL.derived, ws_flow_derived_rules()): its
    // label, the type of the value it gives and whether it reads a second
    // value; an unknown one as join, as the server keeps it.
    function derivedRule(key) {
        var rules = (FL.derived || {}).rules || [];
        var rule = rules.filter(function (item) { return item.key === key; })[0] || rules.filter(function (item) { return item.key === 'join'; })[0];

        return rule || { key: 'join', label: key || '', type: 'text', two: true };
    }

    // A derived value box: its rule, the values it works from (with value
    // chips) and what the rule takes beside them.
    function derivedFields(box, node) {
        var config = node.config;
        var lists = FL.derived || {};
        var operators = stepLists().operators;
        var rule = derivedRule(config.rule);
        var pick = K.select((lists.rules || []).map(function (item) { return [item.key, item.label]; }), rule.key);

        pick.addEventListener('change', function () {
            config.rule = pick.value;
            refreshNode(node);
            drawSettings();
        });
        box.appendChild(field(t('fl_derived_rule'), pick));
        box.appendChild(valueField(node, t('fl_derived_a'), config.a || '', 500, function (value) {
            config.a = value;
            refreshNode(node);
        }, false));

        if (rule.key === 'compare') {
            var op = K.select((lists.operators || []).map(function (key) { return [key, operators[key] || key]; }), config.op || 'eq');

            op.addEventListener('change', function () {
                config.op = op.value;
                refreshNode(node);
            });
            box.appendChild(field(t('fl_derived_op'), op));
        }

        if (rule.two) {
            box.appendChild(valueField(node, t((rule.key === 'add_days') ? 'fl_derived_days' : 'fl_derived_b'), config.b || '', 500, function (value) {
                config.b = value;
                refreshNode(node);
            }, false));
        }

        if (rule.key === 'round') {
            var places = K.select([0, 1, 2, 3, 4, 5, 6].map(function (n) { return [String(n), String(n)]; }), String(config.places || 0));

            places.addEventListener('change', function () {
                config.places = parseInt(places.value, 10) || 0;
                refreshNode(node);
            });
            box.appendChild(field(t('fl_derived_places'), places));
        }

        if (rule.key === 'date_format') {
            var format = K.select((lists.formats || []).map(function (item) { return [item.key, item.label]; }), config.format || 'Y-m-d');

            format.addEventListener('change', function () {
                config.format = format.value;
                refreshNode(node);
            });
            box.appendChild(field(t('fl_derived_format'), format));
        }

        box.appendChild(el('p', 'small fg-secondary', t('fl_derived_help')));
    }

    function retryFields(box, node) {
        var retry = (FL.steps || {}).retry || {};

        if ((retry.kinds || []).indexOf(node.kind) < 0) {
            return;
        }

        var config = node.config;
        var max = retry.max || 7;
        var afterMax = retry.after_max || 10080;
        var times = el('input', 'form-control form-control-sm wsf-number');
        var after = el('input', 'form-control form-control-sm wsf-number');

        times.type = 'number';
        times.min = '0';
        times.max = String(max);
        times.step = '1';
        times.value = String(config.retry || 0);
        times.addEventListener('input', function () {
            config.retry = clamp(parseInt(times.value, 10) || 0, 0, max);
            refreshNode(node);
        });
        after.type = 'number';
        after.min = '1';
        after.max = String(afterMax);
        after.step = '1';
        after.value = (config.retry_after === '' || config.retry_after === undefined || config.retry_after === null) ? '' : String(config.retry_after);
        after.addEventListener('input', function () {
            config.retry_after = (after.value === '') ? '' : clamp(parseInt(after.value, 10) || 1, 1, afterMax);
            refreshNode(node);
        });
        box.appendChild(field(t('fl_self_retry'), times, t('fl_self_retry_help')));
        box.appendChild(field(t('fl_self_retry_after'), after, t('fl_self_retry_after_help')));
    }

    // An approval box: the channel it asks in, the people asked, the text,
    // whether one approval or all of them settle it, and the hours it gives
    // them (WS_STEP_APPROVAL_CLOSES_MAX); the title is the box's.
    function approvalFields(box, node) {
        var config = node.config;
        var rule = K.select([['any', t('fl_approval_any')], ['all', t('fl_approval_all')]], config.rule || 'any');
        var closes = el('input', 'form-control form-control-sm wsf-number');

        channelField(box, node, t('fl_approval_channel'), t('fl_post_pick'));
        box.appendChild(field(t('fl_approval_who'), peopleList(node, el('div', 'wsf-people'), 'approvers', 50), t('fl_approval_who_help')));
        box.appendChild(valueField(node, t('fl_description'), config.text, 4000, function (value) {
            config.text = value;
            refreshNode(node);
        }, true));
        rule.addEventListener('change', function () {
            config.rule = rule.value;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_approval_rule'), rule));

        closes.type = 'number';
        closes.min = '1';
        closes.max = '720';
        closes.value = (config.closes_in === '' || config.closes_in === undefined || config.closes_in === null) ? '' : String(config.closes_in);
        closes.addEventListener('input', function () {
            config.closes_in = (closes.value === '') ? '' : clamp(parseInt(closes.value, 10) || 1, 1, 720);
            refreshNode(node);
        });
        box.appendChild(field(t('fl_approval_closes'), closes, t('fl_approval_closes_help')));
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
                var check = el('input', 'check m-0');

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

    // What happens to a row that starts a row box's flow (ws_flow_row_whats()).
    var ROW_WHATS = { created: 'fl_row_created', updated: 'fl_row_updated', deleted: 'fl_row_deleted' };

    // The table a row box waits on, of those the person reads; null for none.
    function rowTable(config) {
        return stepLists().tables.filter(function (table) { return table.id === config.table_id; })[0] || null;
    }

    // A row box in a few words: what happens, the table, the column a change
    // has to write.
    function rowMeta(config) {
        var table = rowTable(config);

        if (!config.table_id) {
            return t('fl_row_none');
        }

        var column = table ? table.columns.filter(function (item) { return item.id === config.column_id; })[0] : null;

        return t(ROW_WHATS[config.what] || 'fl_row_created') + ' · ' + (table ? table.name : '#' + config.table_id) + (column ? ' · ' + column.name : '');
    }

    // The values of a row's cells a row box gives beside the server's own
    // (ws_flow_row_outputs()): one per column of its table, by the column's name.
    function rowCellValues(config) {
        var table = rowTable(config);

        return table ? table.columns.map(function (column) {
            return { key: 'cell_' + column.id, label: column.name, type: ((column.type === 'number') || (column.type === 'money')) ? 'number' : 'text' };
        }) : [];
    }

    // A row box's table, what happens to the row, and for a change the
    // column it has to write.
    function rowFields(box, node) {
        var config = node.config;
        var tables = stepLists().tables;
        var options = [[0, t('fl_row_table_pick')]].concat(tables.map(function (table) { return [table.id, table.name]; }));

        if (config.table_id && !rowTable(config)) {
            options.push([config.table_id, '#' + config.table_id]);
        }

        var pick = K.select(options, config.table_id || 0);
        var what = K.select(Object.keys(ROW_WHATS).map(function (key) { return [key, t(ROW_WHATS[key])]; }), config.what || 'created');

        pick.addEventListener('change', function () {
            config.table_id = parseInt(pick.value, 10) || 0;
            config.column_id = 0;
            refreshNode(node);
            drawSettings();
        });
        what.addEventListener('change', function () {
            config.what = what.value;
            config.column_id = 0;
            refreshNode(node);
            drawSettings();
        });
        box.appendChild(field(t('fl_row_table'), pick, t('fl_row_table_help')));
        box.appendChild(field(t('fl_row_what'), what));

        var table = rowTable(config);

        if ((config.what !== 'updated') || !table) {
            return;
        }

        var column = K.select([[0, t('fl_row_any_column')]].concat(table.columns.map(function (item) { return [item.id, item.name]; })), config.column_id || 0);

        column.addEventListener('change', function () {
            config.column_id = parseInt(column.value, 10) || 0;
            refreshNode(node);
        });
        box.appendChild(field(t('fl_row_column'), column, t('fl_row_column_help')));
    }

    // The days of the week by number, 1 Monday - 7 Sunday.
    var DAYS = { 1: 'fl_day_mon', 2: 'fl_day_tue', 3: 'fl_day_wed', 4: 'fl_day_thu', 5: 'fl_day_fri', 6: 'fl_day_sat', 7: 'fl_day_sun' };

    // What a condition box's rule is, in a few words on the box.
    function conditionMeta(rule, node) {
        var lists = stepLists();

        if (rule.type === 'value') {
            var named = rule.ref ? valueNames(node)[rule.ref] : null;

            return named ? named.label : (rule.ref ? rule.ref.slice(2, -2) : t('fl_cond_none'));
        }

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
        var types = [['', t('fl_cond_pick')], ['workday', t('fl_cond_workday')], ['weekdays', t('fl_cond_weekdays')], ['hours', t('fl_cond_hours')], ['metric', t('fl_cond_metric')], ['record', t('fl_cond_record')], ['value', t('fl_cond_value_rule')]];
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
            } else if ((type.value === 'metric') || (type.value === 'record') || (type.value === 'value')) {
                config.rule.op = (type.value === 'metric') ? 'gt' : 'eq';
                config.rule.value = '';
            }

            if (type.value === 'value') {
                config.rule.ref = '';
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
                var check = el('input', 'check m-0');

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

        if (rule.type === 'value') {
            conditionValueRule(box, node, rule);
        }
    }

    // The comparisons a value takes by its type (ws_flow_condition_value_operators()).
    var VALUE_OPS = { number: ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'empty', 'not_empty'], text: ['eq', 'neq', 'contains', 'empty', 'not_empty'] };

    // A value of the run compared: what started it or a box before this one
    // (the values "Add value" offers), the comparison its type takes, and
    // what it is compared with; a number's a number.
    function conditionValueRule(box, node, rule) {
        var options = [['', t('fl_cond_value_ref_pick')]];
        var types = {};

        valueGroups(node).forEach(function (group) {
            group.values.forEach(function (value) {
                types[value.token] = value.type;
                options.push([value.token, nameOf(group.node) + ' › ' + value.label + (group.skipped ? ' (' + t('fl_values_skipped') + ')' : '')]);
            });
        });

        if (rule.ref && !types[rule.ref]) {
            options.push([rule.ref, rule.ref.slice(2, -2)]);
        }

        var pick = K.select(options, rule.ref || '');
        var type = (types[rule.ref] === 'number') ? 'number' : 'text';

        pick.addEventListener('change', function () {
            rule.ref = pick.value;

            if (VALUE_OPS[(types[rule.ref] === 'number') ? 'number' : 'text'].indexOf(rule.op) === -1) {
                rule.op = 'eq';
            }

            refreshNode(node);
            drawSettings();
        });
        box.appendChild(field(t('fl_cond_value_ref'), pick, (options.length > 1) ? '' : t('fl_values_none')));

        var operators = stepLists().operators;

        conditionCompare(box, node, rule, VALUE_OPS[type].map(function (key) { return [key, operators[key] || key]; }), (type === 'number') ? { kind: 'decimal' } : null);
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
            // A number to measure against starts at 5, the threshold a
            // converted scheduled action carried.
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
            var check = el('input', 'check m-0');

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
        var box = el('div', 'wsf-links mb-5');
        var into = state.graph.edges.filter(function (edge) { return edge.to === node.id && nodeById(edge.from); });

        if (kindOf(node).into) {
            var after = el('div', 'small fg-secondary mb-3', into.length ? '' : t('fl_after_none'));

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

        var list = el('ul', 'list-unstyled mb-3');

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
            list.appendChild(el('li', 'small fg-secondary', t('fl_then_none')));
        }

        box.appendChild(list);

        var targets = candidates(node);
        var group = el('div', 'input-group input-group-sm');
        var pick = K.select([['', t('fl_connect_pick')]].concat(targets.map(function (target) { return [target.id, nameOf(target)]; })), '');
        var add = K.button('btn btn-outline theme-secondary', t('fl_connect_add'), 'bi-plus-lg');

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

    // The outcomes an arrow may leave a box on: its own, and always for a
    // box that is not a start (ws_flow_arrow_outcomes()).
    function arrowOutcomes(node) {
        var kind = kindOf(node);

        return (kind.into && kind.out.length) ? kind.out.concat(['always']) : kind.out;
    }

    function outcomeSelect(edge) {
        var from = nodeById(edge.from);
        var pick = K.select((from ? arrowOutcomes(from) : [edge.on]).map(function (on) { return [on, t(OUTCOMES[on] || 'fl_on_done')]; }), edge.on);

        pick.className = 'form-control form-control-sm w-auto';
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

        box.appendChild(el('p', 'small mb-3', t('fl_arrow_text', nameOf(nodeById(edge.from)), nameOf(nodeById(edge.to)))));

        if (nodeById(edge.from) && (kindOf(nodeById(edge.from)).out.length > 1)) {
            box.appendChild(field(onLabel(nodeById(edge.from)), outcomeSelect(edge)));
        }

        if (state.edit) {
            var remove = K.button('btn btn-sm btn-outline theme-warning', t('fl_remove_arrow'), 'bi-trash');
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
            ui.outlineBox.appendChild(el('p', 'small fg-secondary', t('fl_outline_help')));
        }

        var list = el('ol', 'wsf-outline-list');

        orderedNodes().forEach(function (node) {
            var item = el('li', 'wsf-outline-item ' + kindClasses(node.kind));
            var head = el('h3', 'wsf-outline-head');

            item.setAttribute('data-node', node.id);
            head.appendChild(K.flowIcon(node.kind, 'me-1'));
            head.appendChild(el('span', 'wsf-outline-kind', t(kindOf(node).name)));
            head.appendChild(el('span', 'wsf-outline-title', outlineTitle(node)));
            paintMark(item, head, node);
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
            list.appendChild(el('li', 'small fg-secondary', state.edit ? t('fl_empty_canvas') : ''));
        }

        ui.outlineBox.appendChild(list);
        drawReads(dataThreads());

        if (state.edit) {
            var adds = el('div', 'd-flex flex-wrap gap-3 mb-5');

            adds.setAttribute('role', 'group');
            adds.setAttribute('aria-label', t('fl_palette'));
            paletteButtons('btn btn-sm btn-outline theme-secondary', false).forEach(function (add) {
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

        // The values it reads may have changed, and its name is in the
        // outline's lines of the boxes it gives to.
        drawData();
        drawReads(dataThreads());

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
    // outline is drawn when it is shown again (drawAll()). A change to the
    // graph is a step to undo; one neither structural nor its own step (a
    // box dropped) folds into a close one before it.
    function changed(structure, step) {
        if (!state.edit) {
            return;
        }

        state.dirty = true;
        state.changes++;

        if (state.history) {
            historyRecord(state.history, JSON.stringify(state.graph), !structure && !step, Date.now());
        }

        if (structure) {
            // The first box placed, or the last one removed, takes the
            // template entry away or brings it back; a start box placed or
            // removed renames the start group.
            if ((!!ui.palette.querySelector('.wsf-palette-template') !== templateOffered())
                || (ui.paletteStart && (ui.paletteStart.textContent !== paletteHeading('start')))) {
                drawPalette();
            }

            if (!state.outline) {
                drawCanvas();
            }

            drawOutline();
            drawSettings();
            drawMarks();
        }

        drawState();
        window.clearTimeout(state.saveTimer);
        state.saveTimer = window.setTimeout(save, SAVE_DELAY);
    }

    // Undo (back) or redo may be pressed: the draft open for editing, no
    // run shown, no box on its way and a step to go to.
    function undoable(back) {
        var history = state.history;

        return !!(state.edit && state.flow && !state.run && !state.drag && history && (history.flow === state.flow.id) && (back ? history.past : history.future).length);
    }

    // The graph one step back or forward, saved as any change; a box
    // selected stays selected while it is there, an arrow (selected by its
    // place in the list) does not.
    function undo(back) {
        if (!undoable(back)) {
            return;
        }

        var graph = graphOf(JSON.parse(historyStep(state.history, back)));
        var selected = state.selected;
        var pressed = (document.activeElement === ui.undo) || (document.activeElement === ui.redo);

        state.graph = graph;
        state.binding = null;
        state.selected = (selected && (selected.type === 'node') && nodeById(selected.id)) ? selected : null;
        changed(true);

        // The button that went grey gives the focus to the other one.
        if (pressed && document.activeElement && document.activeElement.disabled) {
            (back ? ui.redo : ui.undo).focus();
        }

        announce(t(back ? 'fl_undone' : 'fl_redone'));
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
            config = { title: '', description: '', assignees: [], assign: 'creator', due_in: '', priority: 'normal', timeout: '' };
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
        } else if (kind === 'constants') {
            config = { rows: [{ key: '', type: 'text', value: '' }] };
        } else if (kind === 'value') {
            config = { rule: 'join', a: '', b: '', op: 'eq', places: 0, format: 'Y-m-d' };
        } else if (kind === 'lead') {
            config = { private: false, user_ids: [] };
        } else if (kind === 'mcp') {
            // The one server there is is taken; of several, none yet.
            var servers = stepLists().mcp;

            config = { server_id: (servers.length === 1) ? servers[0].id : 0, tool: '', arguments: '' };
        } else if (kind === 'condition') {
            config = { rule: { type: 'workday' } };
        } else if (kind === 'delay') {
            config = { amount: 1, unit: 'hours' };
        } else if (kind === 'start_flow') {
            config = { flow_id: 0 };
        } else if (kind === 'approval') {
            config = { channel_id: 0, title: '', text: '', approvers: [], rule: 'any', closes_in: '' };
        } else if (kind === 'schedule') {
            config = { date: new Date().toISOString().slice(0, 10), time: '09:00', repeat: 'none', every: 1, days: [], until: '' };
        } else if (kind === 'event') {
            config = { event: '', page_id: 0, status: '', channel_id: 0 };
        } else if (kind === 'join') {
            config = { channel_id: 0 };
        } else if (kind === 'row') {
            // The one table there is is taken; of several, none yet.
            var tables = stepLists().tables;

            config = { table_id: (tables.length === 1) ? tables[0].id : 0, what: 'created', column_id: 0 };
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

        pick.className = 'form-control';
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
                state.warnings = data.warnings || [];

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

        var reload = K.button('btn btn-sm btn-outline theme-secondary', t('fl_reload'), 'bi-arrow-clockwise');

        reload.addEventListener('click', function () {
            state.dirty = false;
            state.saveError = '';
            state.conflict = '';
            state.refocus = 'name';
            // The draft read again is the first step: undo never brings
            // back what was written over on the other screen.
            state.history = null;
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

                // The menu that deleted it is gone: the list's search, or
                // "New flow", takes the focus.
                state.refocus = 'flows-search new';
                showNone();
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

        var modal = el('dialog', 'dialog dialog-scrollable wsf-share-modal');
        var content = modal;
        var head = el('div', 'dialog-header');
        var title = el('h2', 'dialog-title fs-md');
        var cross = el('button', 'btn-close');
        var foot = el('div', 'dialog-footer');
        var close = K.button('btn btn-sm btn-ghost', t('fl_share_close'));

        modal.id = 'wsf-share';
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'wsf-share-title');
        title.id = 'wsf-share-title';
        cross.type = 'button';
        cross.setAttribute('aria-label', t('fl_share_close'));
        cross.setAttribute('data-bs-dismiss', 'dialog');
        close.setAttribute('data-bs-dismiss', 'dialog');
        head.appendChild(title);
        head.appendChild(cross);
        foot.appendChild(close);
        content.appendChild(head);
        content.appendChild(el('div', 'dialog-body wsf-share-body'));
        content.appendChild(foot);
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
            modal.querySelector('.dialog-title').textContent = t('fl_share_title', flow.name);
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
        var body = ui.shareModal ? ui.shareModal.querySelector('.dialog-body') : null;

        if (!share || !body) {
            return;
        }

        var data = share.data || {};
        var manage = !!data.manage;
        var shares = data.shares || [];
        var owner = person(data.owner_id);

        K.clear(body);
        body.appendChild(el('p', 'small fg-secondary', manage ? t('fl_share_help') : t('fl_share_read_help')));
        body.appendChild(el('div', 'wsf-side-title', t('fl_share_list')));

        var list = el('ul', 'list-unstyled wsf-share-list mb-5');
        var first = el('li', 'wsf-share-row');
        var who = el('span', 'wsf-share-who');

        who.appendChild(owner ? K.avatar(owner, '1.5rem') : K.icon('bi-person-circle'));
        who.appendChild(el('span', 'text-truncate', owner ? owner.name : '#' + data.owner_id));
        first.appendChild(who);
        first.appendChild(el('span', 'wsf-share-level small fg-secondary', t('fl_share_owner')));
        list.appendChild(first);

        shares.forEach(function (item) {
            list.appendChild(shareRow(item, manage));
        });

        body.appendChild(list);

        if (!shares.length) {
            body.appendChild(el('p', 'small fg-secondary', t('fl_share_none')));
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
        var box = el('div', 'wsf-share-add mb-5');
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
        var row = el('div', 'd-flex flex-wrap align-items-center gap-3 mt-3');
        var level = K.select(Object.keys(LEVELS).map(function (key) { return [key, t(LEVELS[key])]; }), 'run');
        var add = K.button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('fl_share'), 'bi-share');

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

        var row = el('div', 'd-flex flex-wrap align-items-center gap-3');
        var choice = K.select(channels.map(function (channel) {
            return [channel.id, (channel.kind === 'private' ? '🔒 ' : '#') + channel.name];
        }), channels[0].id);
        var add = K.button('btn btn-sm btn-outline theme-secondary', t('fl_share'), 'bi-hash');

        choice.classList.add('w-auto', 'max-w-100');
        choice.setAttribute('aria-label', t('fl_share_channel'));
        row.appendChild(choice);
        row.appendChild(el('span', 'small fg-secondary', t(LEVELS.view)));
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

    // A run's flag (assets/js/workspace_flow_flag.js, in the workspace
    // bundle so the run's card in a channel draws the same one).
    function runFlag(run, options) {
        return window.PGWsFlowFlag.runFlag(run, options);
    }

    // The last run of a flow in the list, null for none.
    function lastRunOf(flowId) {
        var entry = state.flows.filter(function (item) { return item.id === flowId; })[0];

        return (entry && entry.last_run) || null;
    }

    // A run read, started or cancelled is the last one of its flow when no
    // later one is known: its flag in the list and on the canvas follows it.
    function noteLastRun(run) {
        var changed = false;

        if (!run || !run.id) {
            return;
        }

        state.flows.forEach(function (entry) {
            if ((entry.id !== run.flow_id) || (entry.last_run && (entry.last_run.id > run.id))) {
                return;
            }

            var brief = {};

            Object.keys(run).forEach(function (key) {
                brief[key] = ((key === 'steps') || (key === 'graph')) ? null : run[key];
            });

            if (JSON.stringify(entry.last_run) !== JSON.stringify(brief)) {
                entry.last_run = brief;
                changed = true;
            }
        });

        if (changed) {
            keepFocus(function () {
                drawList();
                drawFlag();
            });
        }
    }

    // The box the canvas flag stands above: the end box farthest right, else
    // the box farthest right (lowest of those) that is not a value box.
    function lastBox() {
        var best = null;

        state.graph.nodes.forEach(function (node) {
            if (kindOf(node).pure) {
                return;
            }

            var rank = [(node.kind === 'end') ? 1 : 0, node.x, node.y];

            if (!best || (rank[0] > best.rank[0]) || ((rank[0] === best.rank[0]) && ((rank[1] > best.rank[1]) || ((rank[1] === best.rank[1]) && (rank[2] > best.rank[2]))))) {
                best = { node: node, rank: rank };
            }
        });

        return best ? best.node : null;
    }

    // The flow's last run as a flag above its last box, while no run is
    // shown on the canvas (that one marks every box itself).
    function drawFlag() {
        if (ui.flag) {
            ui.flag.remove();
            ui.flag = null;
        }

        var run = (state.flow && !state.run) ? lastRunOf(state.flow.id) : null;
        var node = run ? lastBox() : null;

        if (!node) {
            return;
        }

        ui.flag = runFlag(run, {
            key: 'canvas-flag',
            open: openLastRun,
            stop: stopLastRun
        });
        ui.flag.classList.add('wsf-flag-canvas');
        placeFlag();
        ui.boxes.appendChild(ui.flag);
    }

    function placeFlag() {
        var node = ui.flag ? lastBox() : null;

        if (node) {
            place(ui.flag, node);
        }
    }

    // The link of a flag: the run on the canvas, with the list of runs
    // open behind it (openRun() shows a run only while it is).
    function openLastRun(run) {
        if (!state.flow || (state.flow.id !== run.flow_id)) {
            return;
        }

        if (state.run) {
            closeRun('runs');
        } else if (!runsShown()) {
            state.panel = 'runs';
            state.selected = null;
            markSelected();
            loadRuns(false);
            drawBar();
            drawSettings();
        }

        openRun(run.id);
    }

    // Stop on a running run's flag: asked first, as Cancel the run is.
    function stopLastRun(run, control) {
        K.ask(t('fl_run_cancel_ask'), t('fl_run_cancel'), true).then(function (yes) {
            if (!yes) {
                refocus();
                return null;
            }

            control.disabled = true;

            return K.api('ws_flow_run_cancel', { run_id: run.id }).then(function (data) {
                K.toast(t('fl_run_cancelled'), 'success');
                noteLastRun(data.run);
            });
        }).catch(function (error) {
            control.disabled = false;
            K.fail(error);
        });
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

        noteLastRun(run);

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
            var failed = el('p', 'small fg-danger', t('fl_runs_failed') + ' ' + list.error);
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
            ui.side.appendChild(el('p', 'small fg-secondary', list.loading ? t('loading') : t('fl_runs_none')));
            return;
        }

        var rows = el('ul', 'list-unstyled mb-3 wsf-run-list');

        rows.setAttribute('aria-labelledby', head.id);

        list.items.forEach(function (run) {
            var item = el('button', 'wsf-run-item');
            var top = el('span', 'wsf-run-item-top');

            item.type = 'button';
            item.disabled = !!state.opening[run.id];
            item.setAttribute('data-refocus', 'run-' + run.id);
            top.appendChild(runBadge(RUN_STATES, run.status));
            top.appendChild(el('span', 'fw-semibold', t('fl_run_name', run.id)));
            top.appendChild(el('span', 'ms-auto small fg-secondary', t('fl_run_version', run.version)));
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

        var close = K.button('btn btn-sm btn-outline theme-secondary', t('fl_run_close'), 'bi-x-lg');

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

        var line = el('div', 'w-100 small ' + (note.lost ? 'fg-danger' : 'fg-secondary'), note.lost ? (note.text + ' ' + t('fl_run_lost')) : note.text);

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
        var facts = el('ul', 'list-unstyled small fg-secondary my-3 wsf-run-facts');
        var tools = el('div', 'd-flex flex-wrap gap-3 mb-5');
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
            var cancel = K.button('btn btn-sm btn-outline theme-warning', t('fl_run_cancel'), 'bi-x-circle');

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
        var facts = el('ul', 'list-unstyled small fg-secondary my-3 wsf-run-facts');

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

        var reads = runReads(node);

        if (reads) {
            wrap.appendChild(reads);
        }

        if (step && step.task_id) {
            wrap.appendChild(taskLink(step, node, 'btn btn-sm btn-outline theme-secondary', 'step-task'));
        }

        // A failed box (one that stopped half way too) is tried again or
        // skipped by the flow's owner, in whose name it runs; a run done or
        // cancelled takes neither.
        if (state.flow && state.flow.manage && step && (step.status === 'failed') && (['done', 'cancelled'].indexOf(state.run.status) < 0)) {
            var mend = el('div', 'd-flex flex-wrap gap-3 mt-3');
            var retry = K.button('btn btn-sm btn-outline theme-primary', t('fl_step_retry'), 'bi-arrow-repeat');
            var skip = K.button('btn btn-sm btn-outline theme-secondary', t('fl_step_skip'), 'bi-skip-forward');

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

        wrap.appendChild(el('p', 'small fg-secondary mb-3', t('fl_run_pick')));
        list.setAttribute('aria-label', t('fl_run_boxes'));

        orderedNodes().forEach(function (node) {
            var step = stepOf(node.id);
            var row = K.button('wsf-run-step-row', '');

            row.appendChild(K.flowIcon(node.kind));

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
            box.appendChild(el('p', 'small fg-secondary mb-1', meta));
        }

        if (step && step.result) {
            box.appendChild(el('p', 'small mb-1 wsf-run-result', step.result));
        }

        if (step && (step.status === 'waiting') && step.task_id) {
            box.appendChild(taskLink(step, node, 'btn btn-sm btn-outline theme-secondary', 'run-otask-' + node.id));
        }

        return box;
    }

    // The value a thread brought in the run shown, as the server fills it
    // (ws_step_node_values()): the box's sentence, outcome and status, else
    // the value it kept (steps[].outputs); a constants box's row; a derived
    // value named, not worked out again. A box not
    // reached or skipped gives nothing. What started the run is not in the
    // run's answer: unknown, named rather than given.
    function runValue(thread) {
        var match = /^\{\{node\.([a-z0-9_]+)\.([a-z0-9_]+)\}\}$/.exec(thread.token);

        if (!thread.from) {
            return { text: '', note: t('fl_values_missing') };
        }

        if (!match) {
            return { text: '', note: t('fl_run_value_trigger'), unknown: true };
        }

        var node = nodeById(match[1]);
        var key = match[2];

        if (node && (node.kind === 'constants')) {
            var row = ((node.config || {}).rows || []).filter(function (item) { return item.key === key; })[0];

            return row ? { text: String(row.value) } : { text: '', note: t('fl_run_value_empty') };
        }

        // A derived value is worked out as a box reads it; the run's answer
        // does not hold it.
        if (node && (node.kind === 'value')) {
            return { text: '', note: t('fl_run_value_derived'), unknown: true };
        }

        var step = stepOf(match[1]);

        if (!step) {
            return { text: '', note: t('fl_run_value_unreached') };
        }

        if (step.status === 'skipped') {
            return { text: '', note: t('fl_run_value_skipped') };
        }

        var value = (key === 'text') ? step.result : (((key === 'outcome') || (key === 'status')) ? step[key] : (step.outputs || {})[key]);

        if ((value === null) || (value === undefined) || (value === '')) {
            return { text: '', note: t((['done', 'failed'].indexOf(step.status) < 0) ? 'fl_run_value_pending' : 'fl_run_value_empty') };
        }

        return { text: String(value) };
    }

    // A field as the run filled it: each value in place of its reference,
    // nothing for one the run gave none, a value of what started the run
    // named in brackets.
    function runFilled(text, threads) {
        return text.replace(VALUE_TOKEN, function (token) {
            var thread = threads.filter(function (item) { return item.token === token; })[0];
            var value = thread ? runValue(thread) : { text: '' };

            return value.unknown ? '[' + thread.label + ']' : value.text;
        });
    }

    // What a box of the run received, field by field: each thread into it
    // with its value, the field as filled once the box ran, and the field
    // as written (its template) on request. Null for a box that reads
    // nothing.
    function runReads(node) {
        var threads = dataThreads().filter(function (thread) { return thread.to === node.id; });
        var step = stepOf(node.id);
        var ran = !!step && (['running', 'waiting', 'done', 'failed'].indexOf(step.status) >= 0);
        var shown = state.runTemplates = state.runTemplates || {};

        if (!threads.length) {
            return null;
        }

        var wrap = el('div', 'wsf-run-reads mt-3');

        wrap.appendChild(el('div', 'form-label small fw-semibold mb-1', t('fl_run_values')));

        fieldTexts(node).forEach(function (item) {
            var reads = threads.filter(function (thread) { return thread.field === item.key; });
            var id = node.id + '.' + item.key;

            if (!reads.length) {
                return;
            }

            var list = el('ul', 'wsf-outline-reads list-unstyled small');

            wrap.appendChild(el('div', 'small fw-semibold', item.label));
            list.setAttribute('aria-label', item.label);
            reads.forEach(function (thread) {
                var value = runValue(thread);
                var line = el('li', toneClass(thread) + (thread.from ? '' : ' wsf-data-bad'));

                line.appendChild(K.icon(thread.from ? 'bi-arrow-return-right' : 'bi-exclamation-triangle', 'wsf-outline-read-icon'));
                line.appendChild(el('span', '', thread.label + ': '));
                line.appendChild(el('span', value.note ? 'fst-italic' : 'fg-body wsf-run-result', value.note || value.text));
                list.appendChild(line);
            });
            wrap.appendChild(list);

            if (ran) {
                wrap.appendChild(el('div', 'small fg-secondary', t('fl_run_filled')));
                wrap.appendChild(el('p', 'small mb-1 wsf-run-result', runFilled(item.text, reads)));
            }

            var toggle = K.button('btn btn-sm btn-link p-0 mb-2', t(shown[id] ? 'fl_run_template_hide' : 'fl_run_template_show'));

            toggle.setAttribute('aria-expanded', shown[id] ? 'true' : 'false');
            toggle.setAttribute('data-refocus', 'run-template-' + id);
            toggle.addEventListener('click', function () {
                shown[id] = !shown[id];
                state.refocus = 'run-template-' + id;
                drawSettings();
                refocus();
            });
            wrap.appendChild(toggle);

            if (shown[id]) {
                wrap.appendChild(el('p', 'small fg-secondary font-monospace mb-2 wsf-run-result', item.text));
            }
        });

        return wrap;
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

    // The optional customer of a run, asked under Run's question: a search
    // among the contacts the person reads (ws_ref_search), one picked at a
    // time. The server checks the pick again (ws_flow_run_contact_check()).
    function customerPicker() {
        var picked = { id: 0 };
        var wrap = el('div', 'wsf-run-customer');
        var id = 'wsf-run-customer-' + Date.now();
        var label = el('label', 'form-label small fw-semibold mb-1', t('fl_run_customer'));
        var input = el('input', 'form-control form-control-sm');
        var help = el('div', 'form-text', t('fl_run_customer_help'));
        var list = el('div', 'list-group mt-1 d-none');
        var chip = el('div', 'd-flex align-items-center gap-2 mt-1 d-none');
        var serial = 0;

        label.htmlFor = id;
        input.id = id;
        input.type = 'search';
        input.autocomplete = 'off';
        help.id = id + '-help';
        input.setAttribute('aria-describedby', help.id);

        function pick(found) {
            picked.id = found ? found.id : 0;
            K.clear(chip);
            list.classList.add('d-none');
            input.value = '';
            input.classList.toggle('d-none', !!found);
            chip.classList.toggle('d-none', !found);

            if (!found) {
                input.focus();
                return;
            }

            var clear = K.button('btn btn-sm btn-ghost', '', 'bi-x-lg');

            clear.setAttribute('aria-label', t('fl_run_customer_clear'));
            clear.title = t('fl_run_customer_clear');
            clear.addEventListener('click', function () {
                pick(null);
            });
            chip.appendChild(el('span', 'bi bi-person-vcard'));
            chip.appendChild(el('span', 'fw-semibold', found.label));
            chip.appendChild(clear);
            clear.focus();
        }

        input.addEventListener('input', K.debounce(function () {
            var mine = ++serial;
            var query = input.value.trim();

            if (query === '') {
                list.classList.add('d-none');
                return;
            }

            K.api('ws_ref_search', { type: 'contact', q: query }).then(function (data) {
                if (mine !== serial) {
                    return;
                }

                K.clear(list);
                (data.items || []).forEach(function (found) {
                    var item = K.button('list-group-item list-group-item-action small text-start', found.label + (found.meta ? ' · ' + found.meta : ''));

                    item.addEventListener('click', function () {
                        pick(found);
                    });
                    list.appendChild(item);
                });

                if (!list.firstChild) {
                    list.appendChild(el('div', 'list-group-item small fg-secondary', t('fl_run_customer_none')));
                }

                list.classList.remove('d-none');
            }).catch(K.fail);
        }, 250));
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                list.classList.add('d-none');
            }
        });

        [label, input, chip, list, help].forEach(function (node) {
            wrap.appendChild(node);
        });

        return { node: wrap, picked: picked };
    }

    // Run: asked first, then started once. A second click while it is asked
    // or sent does nothing; the token goes with the request and is kept
    // until the server answers it, so a start sent again after an answer was
    // lost gets the run the first one started instead of a second run. A
    // customer picked makes the run about them: a run for them still open
    // is answered instead of a second one.
    function startRun() {
        var flow = state.flow;

        if (state.starting || !runnable()) {
            return;
        }

        state.starting = 'ask';

        var customer = FL.run_customer ? customerPicker() : null;

        K.ask(t('fl_run_ask', flow.name, flow.version), t('fl_run_do'), false, customer ? customer.node : null).then(function (yes) {
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
            // contact_id 0: a run about nobody.
            return K.api('ws_flow_run_start', { flow_id: flow.id, start_token: state.startToken.value, contact_id: customer ? customer.picked.id : 0 }).then(function (data) {
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

        // A link on a box (the task of a waiting box) and the last run's
        // flag keep their own clicks: the canvas captures no pointer for them.
        if (event.target.closest && event.target.closest('.wsf-node a[href], .wsf-flag-canvas')) {
            delete state.pointers[event.pointerId];
            return;
        }

        var target = event.target;
        var box = target.closest('.wsf-node[data-node]');
        var edge = target.closest('.wsf-edge');
        var dot = target.closest('.wsf-data-live');
        var bind = target.closest('[data-bind]');
        var ring = dot ? dot.querySelector('.wsf-data-ring').getBoundingClientRect() : null;
        var drag = { id: event.pointerId, sx: event.clientX, sy: event.clientY, moved: false, kind: 'pan', vx: state.view.x, vy: state.view.y };

        // A thread waiting for its field: a lit field takes it, anything
        // else lets it go.
        if (state.binding && bind && !state.space && (event.button !== 1)) {
            var place = bind.getAttribute('data-bind').split(' ');

            bindValue(state.binding.from, state.binding.token, place[0], place[1]);
            endBinding();
            delete state.pointers[event.pointerId];
            event.preventDefault();
            return;
        }

        if (state.binding && !(dot && (dot.getAttribute('data-node') === state.binding.from) && (dot.getAttribute('data-token') === state.binding.token))) {
            endBinding();
        }

        if (state.space || (event.button === 1)) {
            drag.kind = 'pan';
        } else if (dot) {
            drag.kind = 'thread';
            drag.from = nodeById(dot.getAttribute('data-node'));
            drag.token = dot.getAttribute('data-token');
            drag.start = canvasPoint(ring.left + ring.width / 2, ring.bottom);
            event.preventDefault();

            if (!state.selected || (state.selected.type !== 'node') || (state.selected.id !== drag.from.id)) {
                select({ type: 'node', id: drag.from.id });
            }

            if (box) {
                box.focus({ preventScroll: true });
            }
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
        } else if (drag.kind === 'thread') {
            if (!state.binding && !startBinding(drag.from.id, drag.token, true)) {
                state.drag = null;
                return;
            }

            var end = canvasPoint(event.clientX, event.clientY);
            var hit = document.elementFromPoint(event.clientX, event.clientY);
            var hot = hit && hit.closest ? hit.closest('[data-bind]') : null;

            ui.temp.setAttribute('d', hangingPath(drag.start.x, drag.start.y, end.x, end.y));
            ui.temp.classList.add('wsf-temp-thread');
            ui.temp.classList.remove('d-none');
            Array.prototype.forEach.call(ui.boxes.querySelectorAll('.wsf-data-target.wsf-data-hot'), function (node) { node.classList.remove('wsf-data-hot'); });

            if (hot) {
                hot.classList.add('wsf-data-hot');
            }
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

        if (drag.kind === 'thread') {
            ui.temp.classList.add('d-none');
            ui.temp.classList.remove('wsf-temp-thread');

            if (drag.moved) {
                var hit = document.elementFromPoint(event.clientX, event.clientY);
                var into = hit && hit.closest ? hit.closest('[data-bind]') : null;

                if (into && state.binding && (event.type === 'pointerup')) {
                    var place = into.getAttribute('data-bind').split(' ');

                    bindValue(drag.from.id, drag.token, place[0], place[1]);
                }

                endBinding();
            } else {
                // The second tap on a dot (the captured pointer sends no
                // dblclick) starts the thread; the next tap on a field ends it.
                var at = Date.now();

                if (state.dotTap && (state.dotTap.token === drag.token) && (state.dotTap.from === drag.from.id) && (at - state.dotTap.time < 450)) {
                    state.dotTap = null;
                    startBinding(drag.from.id, drag.token);
                } else {
                    state.dotTap = { from: drag.from.id, token: drag.token, time: at };
                }
            }

            return;
        }

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
            changed(false, true);
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
        ui.temp.classList.remove('wsf-temp-thread');
        endBinding();
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
        // A link on a box and the last run's flag answer their own keys
        // (Enter follows the link, opens the fold).
        if (typing(event.target) || event.altKey || (event.target.closest && event.target.closest('.wsf-node a[href], .wsf-flag-canvas'))) {
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
        } else if (((key === 'v') || (key === 'V')) && !event.ctrlKey && !event.metaKey && selected && (selected.type === 'node')) {
            askBind(nodeById(selected.id));
        } else if ((key === 'Escape') && state.binding) {
            endBinding();
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

            // Ctrl+Z, Ctrl+Y and Ctrl+Shift+Z on the screen; a text field
            // keeps the browser's own undo, a dialog over it its keys.
            var redo = (event.key === 'y') || (event.key === 'Y') || (((event.key === 'z') || (event.key === 'Z')) && event.shiftKey);

            if ((event.ctrlKey || event.metaKey) && !event.altKey && (redo || (event.key === 'z') || (event.key === 'Z')) && !typing(event.target)
                && ((event.target === document.body) || ui.root.contains(event.target)) && !state.section && ui.gallery.classList.contains('d-none') && undoable(!redo)) {
                event.preventDefault();
                undo(!redo);
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

    window.PGWsFlow = { start: start, showAction: showAction, runFlag: runFlag };
})();
