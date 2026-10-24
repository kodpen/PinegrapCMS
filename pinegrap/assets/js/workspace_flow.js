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
    var SVG_NS = 'http://www.w3.org/2000/svg';

    // What each kind of box is: its sign, its name, whether an arrow may
    // lead into it, and the outcomes an arrow may leave it on (the first is
    // the one a new arrow takes), as ws_flow_kinds() has them.
    var KINDS = {
        manual: { icon: 'bi-play-circle', name: 'fl_kind_manual', into: false, out: ['next'] },
        task: { icon: 'bi-check2-square', name: 'fl_kind_task', into: true, out: ['done', 'cancelled'] },
        end: { icon: 'bi-flag', name: 'fl_kind_end', into: true, out: [] }
    };

    // What the palette adds.
    var PALETTE = [
        ['manual', 'bi-play-circle', 'fl_add_manual'],
        ['task', 'bi-check2-square', 'fl_add_task'],
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
        saving: null,
        saveTimer: 0,
        saveError: '',
        conflict: '',
        refocus: '',
        space: false,
        drag: null,
        pointers: {},
        pinch: null,
        tap: null
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
        return (node.kind === 'task') ? (node.config.title || node.label || '') : (node.label || '');
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

        K.api('ws_flow_list').then(function (data) {
            state.flows = data.flows || [];
            drawList();

            // The person's own flows are listed first, and the first of
            // them opens.
            var mine = state.flows.filter(function (flow) { return flow.mine; });
            var first = asked || (mine[0] || state.flows[0] || {}).id || 0;

            if (first) {
                open(first);
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
        ui.app = el('div', 'wsf-app');
        ui.listCol = el('aside', 'wsf-list');
        ui.listCol.setAttribute('aria-label', t('fl_nav'));
        ui.main = el('div', 'wsf-main');
        ui.side = el('aside', 'wsf-side');
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
        ui.main.appendChild(ui.stage);
        ui.main.appendChild(ui.outlineBox);

        ui.app.appendChild(ui.listCol);
        ui.app.appendChild(ui.main);
        ui.app.appendChild(ui.side);

        root.appendChild(ui.bar);
        root.appendChild(ui.conflictBox);
        root.appendChild(ui.problemBox);
        root.appendChild(ui.app);
        root.appendChild(ui.live);

        applyView();
    }

    // ── The list ───────────────────────────────────────────────────────

    function drawList() {
        K.clear(ui.listCol);

        if (FL.build) {
            var create = K.button('btn btn-sm btn-primary rounded-pill w-100 mb-3', t('fl_new'), 'bi-plus-lg');
            create.addEventListener('click', createFlow);
            ui.listCol.appendChild(create);
        }

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

                link.type = 'button';
                link.appendChild(K.icon('bi-diagram-3'));
                link.appendChild(el('span', 'wsf-list-name', flow.name));
                link.appendChild(statusBadge(flow, true));

                if (state.flow && state.flow.id === flow.id) {
                    link.setAttribute('aria-current', 'true');
                }

                link.addEventListener('click', function () {
                    ui.app.classList.remove('wsf-show-list');
                    open(flow.id);
                });
                item.appendChild(link);
                list.appendChild(item);
            });

            ui.listCol.appendChild(list);
        });
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
        state.flow = null;
        state.graph = graphOf(null);
        K.clear(ui.bar);
        listToggle();
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
    }

    // ── Opening, creating ──────────────────────────────────────────────

    function open(id) {
        return flush().then(function () {
            return K.api('ws_flow_get', { flow_id: id });
        }).then(function (data) {
            show(data.flow);
        }).catch(function (error) {
            K.fail(error);

            if (!state.flow) {
                showNone();
            }
        });
    }

    function show(flow) {
        state.flow = flow;
        state.edit = (flow.access === 'edit');
        state.graph = graphOf(state.edit ? flow.draft : flow.graph);
        state.problems = flow.problems || [];
        state.selected = null;
        state.dirty = false;
        state.saveError = '';
        state.conflict = '';
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
        flush().then(function () {
            return K.api('ws_flow_create', { name: t('fl_untitled'), graph: graphOf(null) });
        }).then(function (data) {
            show(data.flow);

            if (ui.name) {
                ui.name.focus();
                ui.name.select();
            }
        }).catch(K.fail);
    }

    // ── The toolbar ────────────────────────────────────────────────────

    function listToggle() {
        var toggle = K.button('btn btn-sm btn-ghost d-md-none', t('fl_nav'), 'bi-list-ul');

        toggle.setAttribute('aria-expanded', ui.app.classList.contains('wsf-show-list') ? 'true' : 'false');
        toggle.addEventListener('click', function () {
            var shown = ui.app.classList.toggle('wsf-show-list');
            toggle.setAttribute('aria-expanded', shown ? 'true' : 'false');
        });
        ui.bar.appendChild(toggle);
    }

    function drawBar() {
        var flow = state.flow;

        K.clear(ui.bar);
        listToggle();

        if (state.edit) {
            ui.name = el('input', 'form-control form-control-sm wsf-name');
            ui.name.type = 'text';
            ui.name.maxLength = 160;
            ui.name.value = flow.name;
            ui.name.setAttribute('aria-label', t('fl_name'));
            ui.name.addEventListener('input', function () {
                if (ui.name.value.trim() !== '') {
                    flow.name = ui.name.value.trim();
                    changed(false);
                }
            });
            ui.bar.appendChild(ui.name);
        } else {
            ui.name = null;
            ui.bar.appendChild(el('span', 'fw-semibold wsf-name-text', flow.name));
        }

        ui.status = statusBadge(flow, false);
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

        if (!state.edit) {
            ui.bar.appendChild(el('span', 'small text-body-secondary', t('fl_read_only')));
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

        ui.publish = K.button('btn btn-sm btn-primary rounded-pill px-3', t('fl_publish'), 'bi-send');
        ui.publish.addEventListener('click', publish);
        ui.bar.appendChild(ui.publish);

        if (flow.status === 'live') {
            var pause = K.button('btn btn-sm btn-outline-secondary', t('fl_pause'), 'bi-pause-circle');
            pause.addEventListener('click', function () { act('ws_flow_pause', pause, t('fl_paused')); });
            ui.bar.appendChild(pause);
        }

        var more = el('div', 'dropdown');
        var moreButton = K.button('btn btn-sm btn-ghost', '', 'bi-three-dots', t('fl_more'));
        var menu = el('ul', 'dropdown-menu dropdown-menu-end');
        var bin = K.button('dropdown-item link-body-emphasis', t('fl_bin'), 'bi-trash');

        moreButton.setAttribute('data-bs-toggle', 'dropdown');
        moreButton.setAttribute('aria-expanded', 'false');
        bin.addEventListener('click', binFlow);
        menu.appendChild(el('li')).appendChild(bin);
        more.appendChild(moreButton);
        more.appendChild(menu);
        ui.bar.appendChild(more);

        drawState();
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
                save();
            });
            ui.save.appendChild(retry);
        }

        K.clear(ui.problemButton);
        ui.problemButton.className = 'btn btn-sm ' + (count ? 'btn-outline-danger' : 'btn-ghost');
        ui.problemButton.appendChild(K.icon(count ? 'bi-exclamation-triangle' : 'bi-check2-circle', 'me-1'));
        ui.problemButton.appendChild(document.createTextNode(count ? t('fl_problems', count) : t('fl_no_problems')));
        ui.problemButton.disabled = !count;

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
        drawOutline();
        drawSettings();
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
        var missing = !title && ((node.kind === 'task') || !KINDS[node.kind]);
        var titleNode = box.querySelector('.wsf-node-title');

        titleNode.textContent = title || (missing ? t('fl_untitled_box') : meta);
        titleNode.classList.toggle('wsf-placeholder', missing);
        box.querySelector('.wsf-node-meta').textContent = (title || missing) ? meta : '';
        box.setAttribute('aria-label', t(kindOf(node).name) + ': ' + (title || (missing ? t('fl_untitled_box') : '')) + '. ' + meta);
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
            line.setAttribute('d', path.d);
            hit.setAttribute('d', path.d);
            group.appendChild(line);
            group.appendChild(hit);

            // An arrow taken when the task is cancelled says so on itself.
            if (edge.on === 'cancelled') {
                var label = svgEl('text', 'wsf-edge-label');

                label.setAttribute('x', String(path.mx));
                label.setAttribute('y', String(path.my - 6));
                label.setAttribute('text-anchor', 'middle');
                label.textContent = t('fl_on_cancelled');
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

    function markProblems() {
        var byNode = {};
        var byEdge = {};

        state.problems.forEach(function (problem) {
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
            box.title = texts ? texts.join('\n') : '';
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

        if (selected && (selected.type === 'node') && nodeById(selected.id) && !state.outline) {
            ui.side.appendChild(el('div', 'wsf-side-title', t(kindOf(nodeById(selected.id)).name)));
            ui.side.appendChild(nodeForm(nodeById(selected.id)));
        } else if (selected && (selected.type === 'edge') && state.graph.edges[selected.id] && !state.outline) {
            ui.side.appendChild(el('div', 'wsf-side-title', t('fl_arrow')));
            ui.side.appendChild(edgeForm(selected.id));
        } else {
            ui.side.appendChild(el('div', 'wsf-side-title', t('fl_flow_settings')));
            ui.side.appendChild(flowForm());
        }

        refocus();
    }

    function refocus() {
        if (!state.refocus) {
            return;
        }

        var target = ui.root.querySelector('[data-refocus="' + state.refocus + '"]');

        state.refocus = '';

        if (target) {
            target.focus();
        }
    }

    function flowForm() {
        var box = el('div');
        var channels = [[0, t('fl_home_none')]];

        (BOOT.channels || []).forEach(function (channel) {
            if (((channel.kind === 'public') || (channel.kind === 'private')) && !channel.archived) {
                channels.push([channel.id, '#' + channel.name]);
            }
        });

        var home = K.select(channels, state.flow.channel_id || 0);

        home.addEventListener('change', function () {
            state.flow.channel_id = parseInt(home.value, 10) || 0;
            changed(false);
        });
        box.appendChild(field(t('fl_home'), home, t('fl_home_help')));

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

        var title = textInput(titleOf(node), (node.kind === 'task') ? 200 : 160, function (value) {
            if (node.kind === 'task') {
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

        if (state.edit) {
            box.appendChild(connections(node));

            var remove = K.button('btn btn-sm btn-outline-danger', t('fl_remove_box'), 'bi-trash');
            remove.addEventListener('click', function () { removeSelected({ type: 'node', id: node.id }); });
            box.appendChild(remove);
        }

        return box;
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
        var people = peopleList(node);

        people.classList.toggle('d-none', current !== 'people');
        who.addEventListener('change', function () {
            people.classList.toggle('d-none', who.value !== 'people');

            if (who.value === 'people') {
                config.assign = '';
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

    // The team as ticks, with a filter once it is long.
    function peopleList(node) {
        var config = node.config;
        var wrap = el('div', 'wsf-people mt-2');
        var list = el('div', 'wsf-people-list');
        var chosen = config.assignees || [];
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
                var ids = (config.assignees || []).filter(function (id) { return id !== member.id; });

                if (check.checked) {
                    ids.push(member.id);
                }

                config.assignees = ids.slice(0, 20);
                config.assign = '';
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

    // The arrows of a box, and the way to add one without dragging.
    function connections(node) {
        var box = el('div', 'wsf-links mb-3');
        var into = state.graph.edges.filter(function (edge) { return edge.to === node.id && nodeById(edge.from); });

        if (kindOf(node).into) {
            box.appendChild(el('div', 'form-label small fw-semibold mb-1', t('fl_after')));
            box.appendChild(el('div', 'small text-body-secondary mb-2', into.length ? into.map(function (edge) { return nameOf(nodeById(edge.from)); }).join(', ') : t('fl_after_none')));
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
            row.appendChild(el('span', 'flex-grow-1 text-truncate', nameOf(nodeById(edge.to))));

            if (kindOf(node).out.length > 1) {
                row.appendChild(outcomeSelect(edge));
            }

            var remove = K.button('btn btn-sm btn-ghost', '', 'bi-x-lg', t('fl_remove_arrow_to', nameOf(nodeById(edge.to))));

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

        pick.setAttribute('aria-label', t('fl_connect_title', nameOf(node)));
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
        var pick = K.select([['done', t('fl_on_done')], ['cancelled', t('fl_on_cancelled')]], edge.on);

        pick.className = 'form-select form-select-sm w-auto';
        pick.setAttribute('aria-label', t('fl_on'));
        pick.setAttribute('data-refocus', 'on-' + edge.from + '-' + edge.to);
        pick.disabled = !state.edit;
        pick.addEventListener('change', function () {
            edge.on = pick.value;
            state.refocus = 'on-' + edge.from + '-' + edge.to;
            changed(true);
        });

        return pick;
    }

    function edgeForm(index) {
        var edge = state.graph.edges[index];
        var box = el('div', 'wsf-form');

        box.appendChild(el('p', 'small mb-2', t('fl_arrow_text', nameOf(nodeById(edge.from)), nameOf(nodeById(edge.to)))));

        if (nodeById(edge.from) && (kindOf(nodeById(edge.from)).out.length > 1)) {
            box.appendChild(field(t('fl_on'), outcomeSelect(edge)));
        }

        if (state.edit) {
            var remove = K.button('btn btn-sm btn-outline-danger', t('fl_remove_arrow'), 'bi-trash');
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

        ui.outlineBox.appendChild(el('p', 'small text-body-secondary', t('fl_outline_help')));

        var list = el('ol', 'wsf-outline-list');

        orderedNodes().forEach(function (node) {
            var item = el('li', 'wsf-outline-item wsf-node-' + (KINDS[node.kind] ? node.kind : 'unknown'));
            var head = el('h3', 'wsf-outline-head');

            item.setAttribute('data-node', node.id);
            head.appendChild(K.icon(kindOf(node).icon, 'me-1'));
            head.appendChild(el('span', 'wsf-outline-kind', t(kindOf(node).name)));
            head.appendChild(el('span', 'wsf-outline-title', outlineTitle(node)));
            item.appendChild(head);
            item.appendChild(nodeForm(node));
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

    // The words of a box changed: its face and the outline's heading follow,
    // nothing is drawn again (the field being written in keeps its focus).
    function refreshNode(node) {
        Array.prototype.forEach.call(ui.root.querySelectorAll('.wsf-node[data-node="' + node.id + '"]'), function (box) {
            fillBox(box, node);
        });

        Array.prototype.forEach.call(ui.outlineBox.querySelectorAll('[data-node="' + node.id + '"] > .wsf-outline-head .wsf-outline-title'), function (title) {
            title.textContent = outlineTitle(node);
        });

        changed(false);
    }

    // Something changed: drawn again when boxes or arrows came or went, and
    // saved a moment after the last change.
    function changed(structure) {
        if (!state.edit) {
            return;
        }

        state.dirty = true;

        if (structure) {
            drawCanvas();
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
            dialog.addEventListener('shown.bs.modal', function shown() {
                dialog.removeEventListener('shown.bs.modal', shown);
                pick.focus();
            });
        }
    }

    function removeSelected(target) {
        var selected = target || state.selected;

        if (!state.edit || !selected) {
            return;
        }

        if (selected.type === 'node') {
            var node = nodeById(selected.id);

            if (!node) {
                return;
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

    function payload() {
        var data = { flow_id: state.flow.id, draft_rev: state.flow.draft_rev, channel_id: state.flow.channel_id || 0, graph: state.graph };

        if (String(state.flow.name || '').trim() !== '') {
            data.name = state.flow.name;
        }

        return data;
    }

    function save() {
        window.clearTimeout(state.saveTimer);

        if (!state.edit || !state.flow || state.conflict) {
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
        state.saving = K.api('ws_flow_save', payload()).then(function (data) {
            flow.draft_rev = data.draft_rev;
            flow.changed = true;

            if (state.flow === flow) {
                state.problems = data.problems || [];
                drawProblems();
                markProblems();
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

    // The page is being left: the last changes go out on a request the
    // browser finishes on its own.
    function saveOnLeave() {
        if (!state.edit || !state.flow || !state.dirty || state.saving || state.conflict) {
            return;
        }

        var body = payload();

        body.action = 'ws_flow_save';
        body.token = CFG.token;
        state.dirty = false;

        try {
            fetch(CFG.api_url, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        } catch (error) {
            state.dirty = true;
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
            state.conflict = '';
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

    function act(action, control, done) {
        control.disabled = true;

        K.api(action, { flow_id: state.flow.id, draft_rev: state.flow.draft_rev }).then(function (data) {
            var view = { x: state.view.x, y: state.view.y, k: state.view.k };

            show(data.flow);
            state.view = view;
            applyView();
            K.toast(done, 'success');
        }).catch(function (error) {
            control.disabled = false;

            if (error.field === 'changed_meanwhile') {
                conflict(error.message);
            } else {
                K.fail(error);
            }
        });
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

                if (state.flows.length) {
                    open(state.flows[0].id);
                } else {
                    showNone();
                }
            }).catch(K.fail);
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

            state.drag = null;
            ui.temp.classList.add('d-none');
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

            if (over && (event.type === 'pointerup')) {
                state.selected = { type: 'node', id: drag.from.id };
                connect(drag.from, nodeById(over.getAttribute('data-node')));
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

    // Enter: the first field of what is selected.
    function focusField() {
        var field = ui.side.querySelector('input, textarea, select');

        if (field && !state.outline) {
            field.focus();
        }
    }

    function canvasKey(event) {
        if (typing(event.target) || event.altKey) {
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
            if (state.drag && (state.drag.kind === 'connect')) {
                ui.temp.classList.add('d-none');
            }

            state.drag = null;
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
        // Space held down: dragging anywhere on the canvas moves it.
        document.addEventListener('keydown', function (event) {
            if ((event.key === ' ') && !typing(event.target) && ui.app.contains(event.target) && !event.target.closest('button')) {
                state.space = true;
                ui.viewport.classList.add('wsf-space');
                event.preventDefault();
            }

            if ((event.ctrlKey || event.metaKey) && ((event.key === 's') || (event.key === 'S')) && state.edit) {
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

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                saveOnLeave();
            }
        });

        window.addEventListener('pagehide', saveOnLeave);

        window.addEventListener('beforeunload', function (event) {
            saveOnLeave();

            // A save that failed or met a newer draft is not lost silently.
            if (state.edit && (state.saveError || state.conflict)) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    }

    window.PGWsFlow = { start: start };
})();
