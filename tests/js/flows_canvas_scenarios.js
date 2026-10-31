/**
 * Pinegrap - Enterprise Website Platform
 *
 * The scenarios of the Flows canvas harness (tests/js/flows_canvas.js): what
 * a person does on the canvas, and what the screen and the requests it sends
 * must show for it. First what any box-and-arrow canvas does (drawing, view,
 * selection, moving, connecting, removing, keys, undo, data threads, the
 * outline, saving), then what the flows screen adds (palette, box forms,
 * problems, publish, read-only access, the ⋯ menu, runs).
 *
 * A scenario is { name, run(h), fixture, width, height, touch }: run gets
 * the driver of flows_canvas.js (h: open, click, drag, press, moveTo,
 * release, key, type, wheel, pinch, dropFrom, answer, leave, hide, blur,
 * waitFor, until, settle, probe, state, saves, store, t, and the checks ok,
 * same, near, need, eventually); fixture names the store's fixture
 * (flows_canvas_store.js, draft by default); width and height the window
 * (1440 x 900); touch makes it a touch screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

'use strict';

const GRID = 16;
const BOX_W = 208;
const BOX_H = 80;
const SAVE_DELAY = 1500;

const BOX = (id) => '#ws-root .wsf-node[data-node="' + id + '"]';
const PORT = (id, side) => BOX(id) + ' .wsf-port-' + side;
const ARROW = (index) => '#ws-root .wsf-edge[data-edge="' + index + '"] .wsf-edge-hit';
const VIEWPORT = '#ws-root [role="application"]';
const SIDE = '#wsf-side';
const BAR = '#ws-root .wsf-bar';
const PALETTE = '#ws-root .wsf-palette';
const OUTLINE = '#ws-root .wsf-outline';

const snap = (value) => Math.round(value / GRID) * GRID;

function nodeIn(graph, id) {
    return (graph && graph.nodes || []).filter((node) => node.id === id)[0] || null;
}

function arrows(graph) {
    return (graph && graph.edges || []).map((edge) => edge.from + '>' + edge.to + ':' + edge.on);
}

// A box clicked: selected, and its box has the focus.
async function pick(h, id) {
    await h.click(BOX(id));
    await h.waitFor(id + ' is selected', (harness, box) => {
        const state = harness.state();
        const focus = harness.focused();

        return state.selected && (state.selected.id === box) && focus && (focus.node === box);
    }, 3000, id);
}

// The save number count, arrived and answered.
async function saveNumber(h, count, label, timeout) {
    await h.until(label || ('save ' + count + ' is sent'), () => h.saves().length >= count, timeout || (SAVE_DELAY + 2500));
    await h.settle();

    return h.saves()[count - 1];
}

// What has the focus ({} for nothing but the page).
async function focus(h) {
    return (await h.probe('focused')) || {};
}

// The draft as the store keeps it now.
function draft(h, id) {
    return h.store.flows.get(id || 7).draft;
}

module.exports = [

    // ── Drawing and the view ───────────────────────────────────────────

    {
        name: 'opens the first own flow and draws its boxes and arrows',
        run: async (h) => {
            await h.open();

            const graph = draft(h);
            const view = await h.probe('view');
            const boxes = await h.probe('boxes');
            const edges = await h.probe('edges');
            const state = await h.state();

            h.same('the list asked, then the first own flow', h.store.calls.map((call) => call.action).filter((action) => action !== 'ws_tick'), ['ws_bootstrap', 'ws_flow_list', 'ws_flow_get']);
            h.same('flow 7 opened', h.store.callsOf('ws_flow_get')[0].data, { flow_id: 7 });
            h.same('the address names it', state.search, '?flow=7');
            h.same('the name to edit', state.name, 'Customer onboarding');
            h.same('every box drawn, in the order of the arrows', boxes.map((box) => box.id), ['manual_1', 'task_1', 'end_1', 'task_2']);

            graph.nodes.forEach((node) => {
                const box = boxes.filter((item) => item.id === node.id)[0];

                h.same(node.id + ' where the graph has it', box && [box.x, box.y], [node.x, node.y]);
                h.near(node.id + ' is ' + BOX_W + ' units wide', box && box.w, BOX_W * view.k, 1);
                h.near(node.id + ' is ' + BOX_H + ' units high', box && box.h, BOX_H * view.k, 1);
            });

            h.same('a task shows its title, a start and an end what they do', boxes.map((box) => box.title), [h.t('fl_manual_meta'), 'Call the customer', h.t('fl_end_meta'), 'Send the welcome pack']);
            h.ok('a box is named for screen readers by its kind and title', boxes[1].label.indexOf(h.t('fl_kind_task') + ': Call the customer.') === 0, boxes[1].label);
            h.same('an arrow for each', edges.map((edge) => edge.index), [0, 1]);

            graph.edges.forEach((edge, index) => {
                const from = boxes.filter((box) => box.id === edge.from)[0].screen;
                const to = boxes.filter((box) => box.id === edge.to)[0].screen;
                const drawn = edges[index];

                h.ok('arrow ' + index + ' leaves the right side of ' + edge.from, (Math.abs(drawn.from.x - from.right) <= 2) && (Math.abs(drawn.from.y - (from.top + from.bottom) / 2) <= 2), drawn.from);
                h.ok('arrow ' + index + ' ends at the left side of ' + edge.to, (Math.abs(drawn.to.x - to.left) <= 2) && (Math.abs(drawn.to.y - (to.top + to.bottom) / 2) <= 2), drawn.to);
            });

            h.same('the save state', state.save, h.t('fl_saved'));
            h.same('the server\'s one problem', state.problems && state.problems.text, h.t('fl_problems', 1));

            const lone = boxes.filter((box) => box.id === 'task_2')[0];

            h.ok('the box no arrow reaches is outlined', lone && lone.bad);
            h.same('its tooltip says why, in the server\'s words', lone && lone.tip, h.store.flows.get(7).problems.map((problem) => problem.text).join('\n'));
            h.same('the side panel shows the flow', state.side, h.t('fl_flow_settings'));
            h.same('the list', await h.page(() => Array.prototype.map.call(document.querySelectorAll('#ws-root .wsf-list-name'), (node) => node.textContent)), ['Customer onboarding', 'Weekly report', 'Stock check']);
            h.ok('the palette is shown to whoever may edit', state.palette);
        }
    },

    {
        name: 'the zoom buttons and keys zoom about the middle; 0 fits everything in sight',
        run: async (h) => {
            await h.open();

            const fitted = await h.probe('view');
            const boxes = await h.probe('boxes');

            h.ok('fit: every box inside the canvas', boxes.every((box) => box.inside), boxes.filter((box) => !box.inside).map((box) => box.id));
            h.ok('fit: not larger than 125%', fitted.k <= 1.25 + 1e-9, fitted.k);

            const rect = await h.probe('rect', VIEWPORT);
            const middle = { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
            const before = await h.probe('canvasPoint', middle.x, middle.y);

            await h.click({ label: h.t('fl_zoom_in') });

            const zoomed = await h.probe('view');
            const after = await h.probe('canvasPoint', middle.x, middle.y);

            h.near('Zoom in: 1.2 times', zoomed.k, Math.min(2, fitted.k * 1.2), 1e-6);
            h.ok('the middle stays where it was', (Math.abs(after.x - before.x) < 0.5) && (Math.abs(after.y - before.y) < 0.5), { before: before, after: after });
            h.same('the label says so', (await h.state()).zoom, Math.round(zoomed.k * 100) + '%');

            for (let i = 0; i < 12; i++) {
                await h.click({ label: h.t('fl_zoom_in') });
            }

            h.same('at most 200%', (await h.state()).zoom, '200%');

            for (let i = 0; i < 20; i++) {
                await h.click({ label: h.t('fl_zoom_out') });
            }

            h.same('at least 25%', (await h.state()).zoom, '25%');

            await pick(h, 'task_1');
            await h.key('+');
            h.near('+ zooms in', (await h.probe('view')).k, 0.3, 1e-6);
            await h.key('-');
            h.near('- zooms out', (await h.probe('view')).k, 0.25, 1e-6);
            await h.key('0');

            const again = await h.probe('view');

            h.ok('0 fits again', (Math.abs(again.k - fitted.k) < 1e-6) && (Math.abs(again.x - fitted.x) < 0.5) && (Math.abs(again.y - fitted.y) < 0.5), { fitted: fitted, again: again });
            await h.click({ label: h.t('fl_zoom_in') });
            await h.click({ label: h.t('fl_fit') });

            const button = await h.probe('view');

            h.ok('Fit to the screen fits again', (Math.abs(button.k - fitted.k) < 1e-6) && (Math.abs(button.x - fitted.x) < 0.5), { fitted: fitted, button: button });
            await h.sleep(SAVE_DELAY + 300);
            h.same('the view is never saved', h.saves().length, 0);
        }
    },

    {
        name: 'Ctrl+wheel and a pinch zoom about the pointer; wheel, drag, Space+drag and the middle button pan',
        run: async (h) => {
            await h.open();

            const spot = await h.probe('empty');

            h.need('an empty place on the canvas', spot);

            const under = await h.probe('canvasPoint', spot.x, spot.y);
            const k0 = (await h.probe('view')).k;

            await h.wheel(spot, 0, -100, 'Control');

            const k1 = (await h.probe('view')).k;
            const kept = await h.probe('canvasPoint', spot.x, spot.y);

            h.near('Ctrl+wheel zooms in', k1, Math.min(2, k0 * Math.exp(0.2)), 1e-6);
            h.ok('the point under the pointer stays', (Math.abs(kept.x - under.x) < 0.5) && (Math.abs(kept.y - under.y) < 0.5), { under: under, kept: kept });

            const v1 = await h.probe('view');

            await h.wheel(spot, 30, 50);

            const v2 = await h.probe('view');

            h.same('the wheel pans', [Math.round(v2.x - v1.x), Math.round(v2.y - v1.y), v2.k], [-30, -50, v1.k]);

            const boxesBefore = (await h.probe('boxes')).map((box) => [box.id, box.x, box.y]);
            const empty = await h.probe('empty');

            await h.drag(empty, { x: empty.x + 120, y: empty.y + 60 });

            const v3 = await h.probe('view');

            h.same('dragging the empty canvas pans', [Math.round(v3.x - v2.x), Math.round(v3.y - v2.y)], [120, 60]);
            h.same('the boxes keep their places', (await h.probe('boxes')).map((box) => [box.id, box.x, box.y]), boxesBefore);

            // Space held over the canvas: dragging a box pans.
            await pick(h, 'task_1');

            const v4 = await h.probe('view');

            await h.drag(BOX('task_2'), { x: (await h.probe('point', BOX('task_2'))).x - 80, y: (await h.probe('point', BOX('task_2'))).y - 40 }, { hold: ' ' });

            const v5 = await h.probe('view');

            h.same('Space+drag on a box pans', [Math.round(v5.x - v4.x), Math.round(v5.y - v4.y)], [-80, -40]);
            h.same('the box stays', [(await h.probe('box', 'task_2')).x, (await h.probe('box', 'task_2')).y], [320, 176]);

            await h.drag(BOX('end_1'), { x: (await h.probe('point', BOX('end_1'))).x + 40, y: (await h.probe('point', BOX('end_1'))).y + 24 }, { button: 'middle' });

            const v6 = await h.probe('view');

            h.same('the middle button pans', [Math.round(v6.x - v5.x), Math.round(v6.y - v5.y)], [40, 24]);
            h.same('end_1 stays', [(await h.probe('box', 'end_1')).x, (await h.probe('box', 'end_1')).y], [640, 0]);

            const centre = await h.probe('empty');
            const before = await h.probe('canvasPoint', centre.x, centre.y);

            await h.pinch(centre, 100, 150);

            const v7 = await h.probe('view');
            const after = await h.probe('canvasPoint', centre.x, centre.y);

            h.near('a pinch zooms by the fingers\' spread', v7.k, Math.min(2, v6.k * 1.5), 0.02);
            h.ok('the point between the fingers stays', (Math.abs(after.x - before.x) < 2) && (Math.abs(after.y - before.y) < 2), { before: before, after: after });
            await h.sleep(SAVE_DELAY + 300);
            h.same('panning and zooming save nothing', h.saves().length, 0);
        }
    },

    // ── Selecting, moving, saving ──────────────────────────────────────

    {
        name: 'a click selects a box; the arrow keys move it on the grid; the draft is saved 1.5 s after the last change',
        run: async (h) => {
            await h.open();
            await pick(h, 'task_1');

            let state = await h.state();

            h.same('its settings in the side panel', state.side, h.t('fl_kind_task'));
            h.same('the title field holds its title', await h.page((harness, label) => harness.find({ labelled: label, within: '#wsf-side' }).value, h.t('fl_title')), 'Call the customer');

            await h.key('ArrowRight');
            await h.key('ArrowRight');
            await h.key('ArrowDown', 'Shift');

            const typed = Date.now();
            const moved = await h.probe('box', 'task_1');

            h.same('16 units a key, 64 with Shift', [moved.x, moved.y], [352, 64]);

            const edges = await h.probe('edges');

            h.ok('the arrow into it follows', (Math.abs(edges[0].to.x - moved.screen.left) <= 2) && (Math.abs(edges[0].to.y - (moved.screen.top + moved.screen.bottom) / 2) <= 2), edges[0].to);
            h.ok('the arrow out of it follows', (Math.abs(edges[1].from.x - moved.screen.right) <= 2), edges[1].from);
            h.same('still the box that has the focus', (await focus(h)).node, 'task_1');
            await h.sleep(900);
            h.same('nothing saved while changes come', h.saves().length, 0);
            h.same('the save state says so', (await h.state()).save, h.t('fl_unsaved'));

            const first = await saveNumber(h, 1);

            h.ok('saved 1.5 s after the last change', (first.at - typed >= SAVE_DELAY - 100) && (first.at - typed <= SAVE_DELAY + 900), (first.at - typed) + ' ms');
            h.same('the save names the draft it was made on', first.data.draft_rev, 3);
            h.same('with the flow, its name and home', [first.data.flow_id, first.data.name, first.data.channel_id], [7, 'Customer onboarding', 0]);
            h.same('the box at its new place', [nodeIn(first.data.graph, 'task_1').x, nodeIn(first.data.graph, 'task_1').y], [352, 64]);
            h.same('nothing else moved', first.data.graph.nodes.filter((node) => node.id !== 'task_1').map((node) => [node.id, node.x, node.y]), [['manual_1', 0, 0], ['end_1', 640, 0], ['task_2', 320, 176]]);
            h.ok('not as a keepalive request', !first.keepalive);
            state = await h.state();
            h.same('saved', state.save, h.t('fl_saved'));

            await h.key('ArrowLeft');

            const second = await saveNumber(h, 2);

            h.same('the next save names the draft_rev the last one was answered with', second.data.draft_rev, 4);
            h.same('one save per pause', h.saves().length, 2);
        }
    },

    {
        name: 'Tab moves from box to box in the order of the arrows, and a box that takes the focus is selected',
        run: async (h) => {
            await h.open();
            await pick(h, 'task_1');
            await h.key('Tab', 'Shift');

            const order = [];

            for (let i = 0; i < 4; i++) {
                const focused = await focus(h);
                const state = await h.state();

                order.push(focused.node);
                h.same('the box with the focus is selected (' + focused.node + ')', state.selected && state.selected.id, focused.node);

                if (i < 3) {
                    await h.key('Tab');
                }
            }

            h.same('start, task, end, then the box no arrow reaches', order, ['manual_1', 'task_1', 'end_1', 'task_2']);
            await h.key('Escape');

            const state = await h.state();

            h.same('Esc lets the selection go', state.selected, null);
            h.same('the canvas keeps the focus', (await focus(h)).role, 'application');
        }
    },

    {
        name: 'dragging a box moves it on the grid and its arrows follow; Esc while dragging puts it back',
        run: async (h) => {
            await h.open();

            const k = (await h.probe('view')).k;
            const start = await h.probe('point', BOX('task_1'));

            await h.drag(BOX('task_1'), { x: start.x + 50, y: start.y + 37 });

            const moved = await h.probe('box', 'task_1');
            const expected = [snap(320 + 50 / k), snap(0 + 37 / k)];

            h.same('snapped to the 16-unit grid', [moved.x, moved.y], expected);
            h.ok('selected by the drag', moved.current);

            const edges = await h.probe('edges');

            h.ok('the arrows follow', (Math.abs(edges[0].to.x - moved.screen.left) <= 2) && (Math.abs(edges[1].from.x - moved.screen.right) <= 2), edges);

            const save = await saveNumber(h, 1);

            h.same('saved where it was dropped', [nodeIn(save.data.graph, 'task_1').x, nodeIn(save.data.graph, 'task_1').y], expected);

            const there = await h.probe('point', BOX('task_2'));

            await h.drag(BOX('task_2'), { x: there.x + 90, y: there.y + 60 }, { cancel: 'Escape' });

            h.same('Esc puts it back', [(await h.probe('box', 'task_2')).x, (await h.probe('box', 'task_2')).y], [320, 176]);
            h.same('and lets the selection go', (await h.state()).selected, null);
            await h.sleep(SAVE_DELAY + 400);
            h.same('nothing more to save', h.saves().length, 1);
        }
    },

    // ── Connecting and removing ────────────────────────────────────────

    {
        name: 'dragging from a box\'s port to another box draws an arrow on its first outcome; the server\'s problems follow',
        run: async (h) => {
            await h.open();
            await h.drag(PORT('task_1', 'out'), BOX('task_2'));

            const edges = await h.probe('edges');

            h.same('a third arrow', edges.length, 3);
            h.ok('from the port to the box', (Math.abs(edges[2].to.x - (await h.probe('box', 'task_2')).screen.left) <= 2), edges[2]);
            h.same('the box it leaves stays selected', (await h.state()).selected, { type: 'node', id: 'task_1' });
            await h.eventually('told to screen readers', (harness, text) => harness.live().indexOf(text) !== -1, 2000, h.t('fl_connected', 'Call the customer', 'Send the welcome pack'));

            const save = await saveNumber(h, 1);

            h.same('saved on the task\'s first outcome', arrows(save.data.graph), ['manual_1>task_1:next', 'task_1>end_1:done', 'task_1>task_2:done']);
            h.same('the server\'s answer clears the problem', (await h.state()).problems.text, h.t('fl_no_problems'));
            h.ok('and its outline', !(await h.probe('box', 'task_2')).bad);

            await h.drag(PORT('task_1', 'out'), BOX('end_1'));
            h.same('no second arrow on the same outcome', (await h.probe('edges')).length, 3);
            h.same('the box it leaves is selected', (await h.state()).selected, { type: 'node', id: 'task_1' });
            await h.drag(PORT('task_2', 'out'), BOX('task_2'));
            h.same('no arrow into itself', (await h.probe('edges')).length, 3);
            await h.sleep(SAVE_DELAY + 400);
            h.same('nothing more to save', h.saves().length, 1);
        }
    },

    {
        name: 'C asks which box to connect to: the list has the focus, Add draws the arrow, Cancel none; the box keeps the focus',
        run: async (h) => {
            await h.open();
            await pick(h, 'task_1');
            await h.key('c');

            const dialog = await h.waitFor('the question opens', (harness) => harness.dialog(), 4000);

            h.same('it asks about the box', dialog.text, h.t('fl_connect_title', 'Call the customer'));
            h.same('only the boxes an arrow may go to', dialog.options.map((option) => option.value), ['task_2']);
            h.same('named by kind and title', dialog.options[0].text, h.t('fl_kind_task') + ': Send the welcome pack');
            await h.waitFor('the list takes the focus', (harness) => harness.focused() && (harness.focused().tag === 'select') && harness.focused().dialog, 3000);
            await h.answer(h.t('fl_connect_add'));
            h.same('the arrow drawn', (await h.probe('edges')).length, 3);
            await h.eventually('the box has the focus again', (harness) => harness.focused() && (harness.focused().node === 'task_1') && (harness.focused().role === 'group'), 2000);
            await pick(h, 'task_1');
            await h.key('c');
            await h.waitFor('none left to connect to: a toast says so', (harness, text) => harness.toasts.some((toast) => toast.text === text), 3000, h.t('fl_connect_none'));
            h.same('no question then', await h.dialog(), null);

            await h.key('Tab', 'Shift');
            h.same('the start box', (await focus(h)).node, 'manual_1');
            await h.key('c');

            const second = await h.waitFor('the question opens', (harness) => harness.dialog(), 4000);

            h.same('the start may lead to the end and the second task', second.options.map((option) => option.value).sort(), ['end_1', 'task_2']);
            await h.answer(h.t('cancel'));
            h.same('Cancel draws nothing', (await h.probe('edges')).length, 3);
            await h.eventually('the start box has the focus again', (harness) => harness.focused() && (harness.focused().node === 'manual_1'), 2000);

            const save = await saveNumber(h, 1);

            h.same('one arrow saved', arrows(save.data.graph), ['manual_1>task_1:next', 'task_1>end_1:done', 'task_1>task_2:done']);
        }
    },

    {
        name: 'the side panel lists a box\'s arrows: Connect to... adds one, the cross takes one away, and its control keeps the focus',
        run: async (h) => {
            await h.open();
            await pick(h, 'task_1');

            const connect = SIDE + ' select.wsf-connect';

            h.same('Connect to... offers the boxes an arrow may go to', await h.page((harness, css) => Array.prototype.map.call(document.querySelector(css).options, (option) => option.value), connect), ['', 'task_2']);
            h.need('task_2 chosen', await h.probe('pick', connect, 'task_2'));
            await h.click({ text: h.t('fl_connect_add'), within: SIDE });
            await h.waitFor('the arrow drawn', (harness) => harness.edges().length === 3, 3000);
            await h.waitFor('the list has the focus again', (harness) => harness.focused() && (harness.focused().refocus === 'connect-task_1'), 3000);
            h.same('the side panel lists both arrows', await h.page(() => Array.prototype.map.call(document.querySelectorAll('#wsf-side .wsf-link [data-name-of]'), (node) => node.getAttribute('data-name-of'))), ['end_1', 'task_2']);
            await h.click({ label: h.t('fl_remove_arrow_to', h.t('fl_kind_end')), within: SIDE });
            await h.waitFor('the arrow to the end goes', (harness) => harness.edges().length === 2, 3000);
            await h.waitFor('Connect to... has the focus', (harness) => harness.focused() && (harness.focused().refocus === 'connect-task_1'), 3000);

            const save = await saveNumber(h, 1);

            h.same('saved', arrows(save.data.graph), ['manual_1>task_1:next', 'task_1>task_2:done']);
            h.same('one save for both', h.saves().length, 1);
        }
    },

    {
        name: 'an arrow\'s outcome is chosen in the side panel; an arrow taken on a cancelled task says so on itself',
        run: async (h) => {
            await h.open();
            await h.click(ARROW(1));
            h.same('the arrow selected', (await h.state()).selected, { type: 'edge', id: 1 });

            const outcome = { labelled: h.t('fl_on'), within: SIDE };

            h.same('a task\'s arrow may leave on its outcomes or always', await h.page((harness, spec) => Array.prototype.map.call(harness.find(spec).options, (option) => option.value), outcome), ['done', 'cancelled', 'timeout', 'always']);
            h.need('cancelled chosen', await h.probe('pick', outcome, 'cancelled'));
            await h.waitFor('the arrow says so', (harness, text) => harness.edges()[1] && (harness.edges()[1].label === text), 3000, h.t('fl_on_cancelled'));
            h.same('an arrow on done says nothing', (await h.probe('edges'))[0].label, '');
            await h.eventually('the outcome list keeps the focus', (harness) => harness.focused() && (harness.focused().refocus === 'on-task_1-end_1'), 2000);

            const save = await saveNumber(h, 1);

            h.same('saved on its outcome', arrows(save.data.graph), ['manual_1>task_1:next', 'task_1>end_1:cancelled']);
        }
    },

    {
        name: 'Delete removes the box selected with its arrows, or the arrow selected; the canvas keeps the focus',
        run: async (h) => {
            await h.open();
            await h.click(ARROW(1));

            let state = await h.state();

            h.same('a click on an arrow selects it', state.selected, { type: 'edge', id: 1 });
            h.same('its settings in the side panel', state.side, h.t('fl_arrow'));
            h.same('the canvas has the focus', (await focus(h)).role, 'application');
            await h.key('Delete');
            h.same('the arrow goes', (await h.probe('edges')).length, 1);
            await h.eventually('told', (harness, text) => harness.live().indexOf(text) !== -1, 2000, h.t('fl_removed', h.t('fl_arrow')));

            await pick(h, 'task_1');
            await h.key('Delete');
            state = await h.state();
            h.same('the box goes', (await h.probe('boxes')).map((box) => box.id), ['manual_1', 'end_1', 'task_2']);
            h.same('with its arrows', (await h.probe('edges')).length, 0);
            h.same('nothing selected', state.selected, null);
            h.same('the canvas has the focus', (await focus(h)).role, 'application');

            const save = await saveNumber(h, 1);

            h.same('saved without them', [save.data.graph.nodes.map((node) => node.id), arrows(save.data.graph)], [['manual_1', 'end_1', 'task_2'], []]);
        }
    },

    {
        name: 'Ctrl+Z undoes a change and Ctrl+Y does it again, each saved; the toolbar\'s buttons follow',
        run: async (h) => {
            await h.open();

            const undoDisabled = () => h.page((harness, label) => harness.find({ label: label }).disabled, h.t('fl_undo'));

            h.ok('nothing to undo yet', await undoDisabled());
            await pick(h, 'task_1');
            await h.key('ArrowRight');
            await h.key('ArrowRight');
            h.same('moved', (await h.probe('box', 'task_1')).x, 352);
            h.ok('Undo may be pressed', !(await undoDisabled()));
            await h.key('z', 'Control');
            h.same('Ctrl+Z: both moves made close together go back as one', (await h.probe('box', 'task_1')).x, 320);
            await h.eventually('told', (harness, text) => harness.live().indexOf(text) !== -1, 2000, h.t('fl_undone'));
            await h.key('y', 'Control');
            h.same('Ctrl+Y: done again', (await h.probe('box', 'task_1')).x, 352);

            await pick(h, 'task_2');
            await h.key('Delete');
            h.same('task_2 removed', (await h.probe('box', 'task_2')), null);
            await h.click({ label: h.t('fl_undo') });
            await h.waitFor('Undo brings it back', (harness) => !!harness.box('task_2'), 2000);
            h.same('where it was', [(await h.probe('box', 'task_2')).x, (await h.probe('box', 'task_2')).y], [320, 176]);

            const save = await saveNumber(h, 1);

            h.same('the last state is saved', [nodeIn(save.data.graph, 'task_1').x, !!nodeIn(save.data.graph, 'task_2')], [352, true]);
        }
    },

    {
        name: 'data threads: a value an earlier box gives hangs as a thread into the field that reads it; a value dragged onto a field is put in it; the toggle hides them and is kept',
        fixture: 'threads',
        run: async (h) => {
            await h.open();

            const threads = () => h.page(() => Array.prototype.map.call(document.querySelectorAll('#ws-root .wsf-edges .wsf-thread'), (path) => [path.getAttribute('data-from'), path.getAttribute('data-to'), path.getAttribute('data-field'), path.classList.contains('wsf-dim')]));

            h.same('one thread, from the task to the field reading it', await threads(), [['task_1', 'task_2', 'description', false]]);
            h.ok('the field shows as a chip under the box', !!(await h.probe('find', { css: BOX('task_2') + ' .wsf-data-field', text: h.t('fl_description') })));
            h.ok('the value as a dot under the box giving it', !!(await h.probe('find', { css: BOX('task_1') + ' .wsf-data-out' })));

            const ends = await h.page(() => {
                const path = document.querySelector('#ws-root .wsf-edges .wsf-thread');
                const matrix = path.getScreenCTM();
                const a = new DOMPoint(path.getPointAtLength(0).x, path.getPointAtLength(0).y).matrixTransform(matrix);
                const b = new DOMPoint(path.getPointAtLength(path.getTotalLength()).x, path.getPointAtLength(path.getTotalLength()).y).matrixTransform(matrix);
                const dot = document.querySelector('#ws-root .wsf-node[data-node="task_1"] .wsf-data-out .wsf-data-ring').getBoundingClientRect();
                const chip = document.querySelector('#ws-root .wsf-node[data-node="task_2"] .wsf-data-field').getBoundingClientRect();

                return { from: Math.hypot(a.x - (dot.left + dot.width / 2), a.y - dot.bottom), to: Math.hypot(b.x - (chip.left + chip.width / 2), b.y - chip.bottom) };
            });

            h.ok('it hangs from under the dot to under the chip', (ends.from < 3) && (ends.to < 3), ends);

            await pick(h, 'end_1');
            h.same('a box selected dims the threads that do not touch it', (await threads()).map((thread) => thread[3]), [true]);
            await pick(h, 'task_1');
            h.same('and not those that do', (await threads()).map((thread) => thread[3]), [false]);

            // Fitted, the first boxes are under the palette: the canvas is
            // moved clear of it first.
            const empty = await h.probe('empty');

            await h.drag(empty, { x: empty.x + 200, y: empty.y });
            await h.press(BOX('task_1') + ' .wsf-data-out .wsf-data-ring');
            await h.moveTo({ x: (await h.probe('point', BOX('task_1'))).x + 40, y: (await h.probe('point', BOX('task_1'))).y + 90 }, { steps: 4 });
            await h.waitFor('the fields that may take the value light up', (harness) => !!document.querySelector('#ws-root .wsf-data-target[data-bind="task_2 title"]'), 3000);
            h.same('only fields of the boxes after it, not one holding it already', await h.page(() => Array.prototype.map.call(document.querySelectorAll('#ws-root .wsf-data-target[data-bind]'), (chip) => chip.getAttribute('data-bind'))), ['task_2 title']);
            await h.moveTo('#ws-root .wsf-data-target[data-bind="task_2 title"]', { steps: 6 });
            await h.release();
            await h.waitFor('a second thread', (harness) => document.querySelectorAll('#ws-root .wsf-edges .wsf-thread').length === 2, 3000);

            const save = await saveNumber(h, 1);

            h.same('the value put at the end of the field', nodeIn(save.data.graph, 'task_2').config.title, 'Send the welcome pack {{node.task_1.task_id}}');
            h.same('the server finds nothing wrong with it', (await h.state()).problems.text, h.t('fl_no_problems'));

            await h.click({ text: h.t('fl_threads'), within: BAR });
            h.ok('the toggle hides them', await h.page(() => document.querySelector('#ws-root .wsf-stage').classList.contains('wsf-threads-off')));
            await h.open();
            h.ok('still hidden when the screen is opened again', await h.page(() => document.querySelector('#ws-root .wsf-stage').classList.contains('wsf-threads-off')));
            h.same('the toggle says so', await h.page((harness, text) => harness.find({ text: text, within: '#ws-root .wsf-bar' }).getAttribute('aria-pressed'), h.t('fl_threads')), 'false');
        }
    },

    // ── The outline ────────────────────────────────────────────────────

    {
        name: 'the outline view lists the boxes in the order of their arrows with their settings, and connects and adds without the canvas',
        run: async (h) => {
            await h.open();
            await h.click({ text: h.t('fl_outline'), within: BAR });

            let state = await h.state();

            h.ok('the outline in place of the canvas', state.outline && !state.canvas, state);
            h.same('pressed', await h.page((harness, text) => harness.find({ text: text, within: '#ws-root .wsf-bar' }).getAttribute('aria-pressed'), h.t('fl_outline')), 'true');
            h.same('the boxes in the order of their arrows', await h.page(() => Array.prototype.map.call(document.querySelectorAll('#ws-root .wsf-outline-item'), (item) => item.getAttribute('data-node'))), ['manual_1', 'task_1', 'end_1', 'task_2']);
            h.ok('each with its settings', await h.page(() => Array.prototype.every.call(document.querySelectorAll('#ws-root .wsf-outline-item'), (item) => !!item.querySelector('input, select'))));

            const connect = OUTLINE + ' .wsf-outline-item[data-node="task_1"] select.wsf-connect';

            h.need('task_2 chosen', await h.probe('pick', connect, 'task_2'));
            await h.click({ text: h.t('fl_connect_add'), within: OUTLINE + ' .wsf-outline-item[data-node="task_1"]' });
            await h.waitFor('the arrow listed', (harness) => document.querySelectorAll('#ws-root .wsf-outline-item[data-node="task_1"] .wsf-link').length === 2, 3000);
            await h.click({ text: h.t('fl_add_task'), within: OUTLINE });
            await h.waitFor('the new task\'s title has the focus', (harness) => harness.focused() && (harness.focused().refocus === 'title-task_3'), 3000);
            await h.type('Book the training');
            h.ok('its heading follows the title', (await h.page(() => document.querySelector('#ws-root .wsf-outline-item[data-node="task_3"] .wsf-outline-title').textContent)).indexOf('Book the training') !== -1);

            const save = await saveNumber(h, 1);
            const added = nodeIn(save.data.graph, 'task_3');

            h.same('saved: the arrow and the task', [arrows(save.data.graph).indexOf('task_1>task_2:done') !== -1, added && added.config.title], [true, 'Book the training']);
            h.same('placed right of the last box', added && [added.x, added.y], [640 + BOX_W + GRID * 5, 0]);

            await h.click({ text: h.t('fl_outline'), within: BAR });
            state = await h.state();
            h.ok('the canvas again', state.canvas && !state.outline, state);
            h.same('drawn with the new box', (await h.probe('boxes')).map((box) => box.id).sort(), ['end_1', 'manual_1', 'task_1', 'task_2', 'task_3']);
            h.ok('and fitted', (await h.probe('boxes')).every((box) => box.inside));
        }
    },

    {
        name: 'on a phone the outline is the canvas and nothing scrolls sideways',
        width: 390,
        height: 844,
        touch: true,
        run: async (h) => {
            await h.open();

            const state = await h.state();

            h.ok('the outline shown, the canvas not', state.outline && !state.canvas, state);
            h.ok('no sideways scroll', await h.page(() => document.documentElement.scrollWidth <= window.innerWidth), await h.page(() => [document.documentElement.scrollWidth, window.innerWidth]));
            await h.click({ text: h.t('fl_nav'), within: BAR });
            h.ok('the list opens from the toolbar', await h.page(() => document.querySelector('#ws-root .wsf-app').classList.contains('wsf-show-list')));
            await h.click({ css: '#ws-root .wsf-list-item', has: 'Weekly report' });
            await h.until('the flow opens', () => h.store.callsOf('ws_flow_get').length === 2, 3000);
            await h.settle();
            h.ok('the list closes again', await h.page(() => !document.querySelector('#ws-root .wsf-app').classList.contains('wsf-show-list')));
            h.same('the other flow', (await h.state()).name, 'Weekly report');
        }
    },

    // ── Saving against the server ──────────────────────────────────────

    {
        name: 'a draft saved meanwhile elsewhere is not written over: the save is refused, the screen offers to load it again',
        run: async (h) => {
            await h.open();

            const flow = h.store.flows.get(7);
            const elsewhere = JSON.parse(JSON.stringify(flow.draft));

            nodeIn(elsewhere, 'task_1').config.title = 'Call the customer again';
            nodeIn(elsewhere, 'task_1').label = 'Call the customer again';
            await h.store.keep(flow, elsewhere);
            flow.draft_rev = 4;

            await pick(h, 'task_1');
            await h.key('ArrowRight');

            const refused = await saveNumber(h, 1);

            h.same('the save names the draft it was made on', refused.data.draft_rev, 3);

            const state = await h.state();

            h.ok('the screen says so', state.conflict.indexOf(h.t('fl_conflict')) !== -1, state.conflict);
            h.ok('with a way to load it again', state.conflict.indexOf(h.t('fl_reload')) !== -1, state.conflict);
            h.same('the save state', state.save, h.t('fl_unsaved'));
            h.ok('Publish waits', !state.publish || state.publish.disabled, state.publish);
            await h.key('ArrowRight');
            await h.sleep(SAVE_DELAY + 500);
            h.same('nothing is sent over the newer draft', h.saves().length, 1);

            await h.click({ text: h.t('fl_reload'), within: '#ws-root' });
            await h.until('read again', () => h.store.callsOf('ws_flow_get').length === 2, 3000);
            await h.settle();

            const again = await h.state();

            h.same('the newer draft drawn', (await h.probe('box', 'task_1')).title, 'Call the customer again');
            h.same('where it was there', (await h.probe('box', 'task_1')).x, 320);
            h.same('the warning goes', again.conflict, '');
            h.same('saved', again.save, h.t('fl_saved'));
            h.same('the name takes the focus', (await focus(h)).refocus, 'name');
        }
    },

    {
        name: 'a change made just before the tab is hidden or the page is left goes out as a keepalive save',
        run: async (h) => {
            await h.open();
            await pick(h, 'task_1');
            await h.key('ArrowRight');
            await h.hide();

            const hidden = await saveNumber(h, 1, 'saved as the tab is hidden', 1000);

            h.ok('as a keepalive request', hidden.keepalive);
            h.same('with the change', nodeIn(hidden.data.graph, 'task_1').x, 336);
            await h.hide(false);
            await h.key('ArrowRight');

            const dialogs = await h.leave();

            await h.until('saved as the page is left', () => h.saves().length === 2, 2000);
            h.ok('as a keepalive request', h.saves()[1].keepalive);
            h.same('on the draft_rev the last save was answered with', h.saves()[1].data.draft_rev, 4);
            h.same('with the change', nodeIn(h.saves()[1].data.graph, 'task_1').x, 352);
            h.same('nobody is asked to stay', dialogs.map((dialog) => dialog.type), []);
        }
    },

    {
        name: 'a draft too large for a keepalive request: a hidden tab saves it as usual, leaving asks first',
        fixture: 'big',
        run: async (h) => {
            await h.open();
            h.ok('the draft is over 60 000 bytes', JSON.stringify(draft(h)).length > 60000, JSON.stringify(draft(h)).length);
            await pick(h, 'task_1');
            await h.key('ArrowRight');
            await h.hide();

            const hidden = await saveNumber(h, 1, 'saved as the tab is hidden', 1000);

            h.ok('not as a keepalive request', !hidden.keepalive);
            await h.hide(false);
            await h.key('ArrowRight');

            const dialogs = await h.leave();

            h.same('leaving asks first', dialogs.map((dialog) => dialog.type), ['beforeunload']);
            h.same('no keepalive save of it', h.saves().filter((save) => save.keepalive).length, 0);
        }
    },

    {
        name: 'Ctrl+S and leaving the window save at once; a failed save is said and tried again from its Retry',
        run: async (h) => {
            await h.open();
            await pick(h, 'task_1');
            await h.key('ArrowRight');

            const pressed = Date.now();

            await h.key('s', 'Control');

            const first = await saveNumber(h, 1, 'Ctrl+S saves', 800);

            h.ok('at once', first.at - pressed < 600, (first.at - pressed) + ' ms');
            await h.key('ArrowRight');
            await h.blur();
            await saveNumber(h, 2, 'the window losing the focus saves', 800);

            h.store.next('ws_flow_save', (request, store) => store.errorPage());
            await h.key('ArrowRight');
            await saveNumber(h, 3);

            let state = await h.state();

            h.same('the save state says it is not saved', state.save.indexOf(h.t('fl_unsaved')), 0);
            h.ok('with a Retry', !!(await h.probe('find', { text: h.t('fl_retry'), within: BAR })));
            await h.sleep(SAVE_DELAY + 500);
            h.same('not tried again on its own', h.saves().length, 3);
            await h.click({ text: h.t('fl_retry'), within: BAR });

            const retried = await saveNumber(h, 4, 'Retry saves', 1000);

            state = await h.state();
            h.same('saved', state.save, h.t('fl_saved'));
            h.same('the change it carried', nodeIn(retried.data.graph, 'task_1').x, 368);
        }
    },

    {
        name: 'opening another flow saves the change on its way first',
        run: async (h) => {
            await h.open();
            await pick(h, 'task_1');
            await h.key('ArrowRight');
            await h.click({ css: '#ws-root .wsf-list-item', has: 'Weekly report' });
            await h.until('the other flow asked', () => h.store.callsOf('ws_flow_get').length === 2, 3000);
            await h.settle();

            const order = h.store.calls.map((call) => call.action + ((call.data.flow_id !== undefined) ? ' ' + call.data.flow_id : '')).filter((call) => /^ws_flow_(save|get)/.test(call));

            h.same('the save of flow 7, then flow 8', order, ['ws_flow_get 7', 'ws_flow_save 7', 'ws_flow_get 8']);
            h.same('the other flow shown', (await h.state()).name, 'Weekly report');
            h.same('the moved box saved', nodeIn(draft(h, 7), 'task_1').x, 336);
        }
    },

    // ── What the flows screen adds ─────────────────────────────────────

    {
        name: 'the palette adds a box, selected, where there is room; dragged from the palette it lands where it is dropped',
        run: async (h) => {
            await h.open();
            h.ok('one start: the palette offers no second', await h.page((harness, text) => harness.find({ text: text, within: '#ws-root .wsf-palette' }).disabled, h.t('fl_add_manual')));
            await h.click({ text: h.t('fl_add_task'), within: PALETTE });
            await h.waitFor('a new box, selected', (harness) => harness.state().selected && (harness.state().selected.id === 'task_3'), 3000);
            h.same('it has the focus', (await focus(h)).node, 'task_3');
            await h.eventually('told', (harness, text) => harness.live().indexOf(text) !== -1, 2000, h.t('fl_added', h.t('fl_kind_task')));

            const placed = await h.probe('box', 'task_3');

            h.ok('on the grid', (placed.x % GRID === 0) && (placed.y % GRID === 0), placed);
            h.ok('clear of the other boxes', (await h.probe('boxes')).filter((box) => box.id !== 'task_3').every((box) => (Math.abs(box.x - placed.x) >= GRID * 4) || (Math.abs(box.y - placed.y) >= GRID * 3)), placed);
            h.same('an untitled task shows as one', placed.title, h.t('fl_untitled_box'));
            await h.click({ labelled: h.t('fl_title'), within: SIDE });
            await h.type('Book the training');
            h.same('the box takes the title as it is typed', (await h.probe('box', 'task_3')).title, 'Book the training');

            const spot = await h.probe('empty');
            const at = await h.probe('canvasPoint', spot.x, spot.y);

            await h.dropFrom({ text: h.t('fl_add_post'), within: PALETTE }, spot);
            await h.waitFor('the dropped box', (harness) => !!harness.box('post_1'), 3000);

            const dropped = await h.probe('box', 'post_1');

            h.same('centred where it was dropped, on the grid', [dropped.x, dropped.y], [snap(at.x - BOX_W / 2), snap(at.y - BOX_H / 2)]);

            const save = await saveNumber(h, 1);

            h.same('both saved', [nodeIn(save.data.graph, 'task_3') && nodeIn(save.data.graph, 'task_3').config.title, nodeIn(save.data.graph, 'post_1') && nodeIn(save.data.graph, 'post_1').kind], ['Book the training', 'post']);
            h.ok('the server finds what the post box lacks', (await h.state()).problems.text !== h.t('fl_no_problems'));
        }
    },

    {
        name: 'the problems the server finds are listed, each leading to its box; an arrow it names is outlined',
        fixture: 'problems',
        run: async (h) => {
            await h.open();

            let state = await h.state();

            h.same('three problems', state.problems.text, h.t('fl_problems', 3));
            h.ok('Publish waits', state.publish && state.publish.disabled, state.publish);

            const edges = await h.probe('edges');

            h.same('the arrow the server names is outlined, no other', edges.map((edge) => edge.bad), [false, false, true]);
            await h.click({ css: '#ws-root [aria-controls="wsf-problems"]' });
            state = await h.state();
            h.ok('the list opens', state.problems.expanded);
            h.same('the server\'s words', state.problemList, h.store.flows.get(7).problems.map((problem) => problem.text));
            await h.waitFor('the first problem has the focus', (harness) => harness.focused() && /wsf-problem/.test(document.activeElement.className), 3000);
            await h.key('Enter');
            await h.waitFor('its box selected and focused', (harness) => harness.state().selected && (harness.state().selected.id === 'task_2') && (harness.focused().node === 'task_2'), 3000);
            h.ok('in sight', (await h.probe('box', 'task_2')).inside);
            await h.click({ labelled: h.t('fl_title'), within: SIDE });
            await h.type('Send the welcome pack');
            await saveNumber(h, 1);
            state = await h.state();
            h.same('the title fixed, two problems left', state.problems.text, h.t('fl_problems', 2));
        }
    },

    {
        name: 'Publish waits for the problems, then asks and publishes the draft as the next version',
        run: async (h) => {
            await h.open();
            h.ok('Publish waits while there is a problem', (await h.state()).publish.disabled);
            await pick(h, 'task_1');
            h.need('task_2 chosen', await h.probe('pick', SIDE + ' select.wsf-connect', 'task_2'));
            await h.click({ text: h.t('fl_connect_add'), within: SIDE });
            await saveNumber(h, 1);
            h.ok('Publish may be pressed once none is left', !(await h.state()).publish.disabled);
            await h.click({ text: h.t('fl_publish'), within: BAR });

            const dialog = await h.answer(h.t('fl_publish_do', 1));

            h.same('it asks first, naming the version', dialog.text, h.t('fl_publish_ask', 1));
            await h.until('published', () => h.store.callsOf('ws_flow_publish').length === 1, 3000);
            await h.settle();
            h.same('on the draft the screen shows', h.store.callsOf('ws_flow_publish')[0].data, { flow_id: 7, draft_rev: 4 });
            await h.waitFor('the toolbar says so', (harness, text) => harness.state().status === text, 3000, h.t('fl_status_live', 1));
            await h.waitFor('a toast says so', (harness, text) => harness.toasts.some((toast) => toast.text === text), 3000, h.t('fl_published', 1));
            h.ok('nothing left to publish', (await h.state()).publish.disabled);
            await h.eventually('the status has the focus', (harness) => harness.focused() && (harness.focused().refocus === 'status'), 2000);
        }
    },

    {
        name: 'a flow the person may only run is drawn read-only: no palette, no moving, no removing',
        run: async (h) => {
            await h.open('?flow=9');

            const state = await h.state();

            h.ok('read-only', state.readOnly && !state.palette, state);
            h.ok('the toolbar says so', !!(await h.probe('find', { css: '#ws-root .wsf-bar span', text: h.t('fl_read_only') })));
            h.same('the name is not a field', await h.page(() => !!document.querySelector('#ws-root input.wsf-name')), false);
            h.ok('Run is offered', !!(await h.probe('find', { text: h.t('fl_run'), within: BAR })));

            const start = await h.probe('point', BOX('task_1'));

            await h.drag(BOX('task_1'), { x: start.x + 60, y: start.y + 40 });
            h.same('a box does not move', [(await h.probe('box', 'task_1')).x, (await h.probe('box', 'task_1')).y], [320, 0]);
            await h.drag(PORT('task_1', 'out'), BOX('manual_1'));
            h.same('no arrow drawn', (await h.probe('edges')).length, 2);
            await pick(h, 'task_1');

            const view = await h.probe('view');

            await h.key('ArrowRight');
            h.same('an arrow key moves the view instead', [(await h.probe('box', 'task_1')).x, Math.round((await h.probe('view')).x - view.x)], [320, -32]);
            await h.key('Delete');
            h.same('Delete removes nothing', (await h.probe('boxes')).length, 3);
            h.ok('its settings cannot be changed', await h.page(() => Array.prototype.every.call(document.querySelectorAll('#wsf-side input, #wsf-side select, #wsf-side textarea'), (control) => control.disabled)));
            await h.sleep(SAVE_DELAY + 300);
            h.same('nothing saved', h.saves().length, 0);
        }
    },

    {
        name: 'the ⋯ menu: Share lists who has the flow and gives the focus back; Delete asks, bins the flow and opens the next one',
        run: async (h) => {
            await h.open();
            await h.click({ label: h.t('fl_more'), within: BAR });
            await h.click({ text: h.t('fl_share'), within: BAR });
            await h.until('the shares read', () => h.store.callsOf('ws_flow_shares').length === 1, 3000);

            const dialog = await h.waitFor('the Share dialog opens', (harness) => harness.dialog(), 4000);

            h.same('named after the flow', await h.page(() => document.querySelector('dialog[open] .dialog-title').textContent), h.t('fl_share_title', 'Customer onboarding'));
            h.ok('the owner listed first', dialog.text.indexOf('Ayşe Demir') !== -1 && dialog.text.indexOf(h.t('fl_share_owner')) !== -1, dialog.text);
            h.ok('nobody else yet', dialog.text.indexOf(h.t('fl_share_none')) !== -1, dialog.text);
            await h.answer(h.t('fl_share_close'));
            await h.eventually('the ⋯ button has the focus again', (harness) => harness.focused() && (harness.focused().refocus === 'more'), 2000);

            await h.click({ label: h.t('fl_more'), within: BAR });
            await h.click({ text: h.t('fl_bin'), within: BAR });

            const asked = await h.answer(h.t('fl_bin'));

            h.same('it asks first', asked.text, h.t('fl_bin_ask', 'Customer onboarding'));
            await h.until('binned', () => h.store.callsOf('ws_flow_bin').length === 1, 3000);
            await h.until('the next flow opened', () => h.store.callsOf('ws_flow_get').length === 2, 3000);
            await h.settle();
            h.same('the next flow shown', (await h.state()).name, 'Weekly report');
            h.same('gone from the list', await h.page(() => Array.prototype.map.call(document.querySelectorAll('#ws-root .wsf-list-name'), (node) => node.textContent)), ['Weekly report', 'Stock check']);
            await h.eventually('a toast says so', (harness, text) => harness.toasts.some((toast) => toast.text === text), 3000, h.t('fl_binned'));
            await h.eventually('the next flow\'s name has the focus', (harness) => harness.focused() && (harness.focused().refocus === 'name'), 2000);
        }
    },

    {
        name: 'the ⋯ menu: Save as template keeps the draft; the gallery shows it as the site\'s, edit renames it, delete removes it',
        run: async (h) => {
            await h.open('?flow=7');
            await h.click({ label: h.t('fl_more'), within: BAR });
            await h.click({ text: h.t('fl_tpl_save'), within: BAR });

            const dialog = await h.waitFor('the template dialog opens', (harness) => harness.dialog(), 4000);

            h.same('it is the template dialog', dialog.id, 'wsf-tpl-dialog');
            h.same('the title is the flow\'s name, selected', await h.page(() => [document.activeElement.value, document.activeElement.selectionEnd]), ['Customer onboarding', 'Customer onboarding'.length]);
            await h.page(() => {
                const field = document.querySelector('dialog[open] [data-field="help"]');

                field.value = 'Welcome a new customer';
                document.querySelector('dialog[open] [data-field="category"]').value = 'sales';
            });
            await h.answer(h.t('fl_tpl_save_do'));
            h.same('saved through the panel action', h.store.callsOf('ws_flow_template_save').map((call) => [call.data.title, call.data.help, call.data.category]), [['Customer onboarding', 'Welcome a new customer', 'sales']]);
            await h.eventually('a toast says so', (harness, text) => harness.toasts.some((toast) => toast.text === text), 3000, h.t('fl_tpl_saved'));
            await h.eventually('the ⋯ button has the focus again', (harness) => harness.focused() && (harness.focused().refocus === 'more'), 2000);

            await h.click({ text: h.t('fl_new'), within: BAR });
            await h.waitFor('the gallery opens', () => !!document.querySelector('#ws-root .wsf-tpl-card'), 4000);
            h.same('the saved card, marked with its group', await h.page(() => Array.prototype.map.call(document.querySelectorAll('#ws-root .wsf-tpl-card'), (card) => [card.querySelector('.wsf-tpl-name').textContent, (card.querySelector('.badge') || {}).textContent || ''])), [['Customer onboarding', 'Sales']]);

            await h.click({ label: h.t('fl_tpl_edit') + ': Customer onboarding' });
            await h.waitFor('the template dialog opens', (harness) => harness.dialog(), 4000);
            await h.page(() => { document.querySelector('dialog[open] [data-field="title"]').value = 'Onboarding'; });
            await h.answer(h.t('fl_tpl_change_do'));
            h.same('changed through the panel action', h.store.callsOf('ws_flow_template_update').map((call) => [call.data.key, call.data.title]), [['site_1', 'Onboarding']]);
            await h.waitFor('the card renamed', () => !!document.querySelector('#ws-root .wsf-tpl-name') && document.querySelector('#ws-root .wsf-tpl-name').textContent === 'Onboarding', 4000);
            await h.eventually('its edit button has the focus', (harness) => harness.focused() && (harness.focused().refocus === 'tpl-edit-site_1'), 2000);

            await h.click({ label: h.t('fl_tpl_delete') + ': Onboarding' });

            const asked = await h.answer(h.t('fl_tpl_delete'));

            h.same('it asks first', asked.text, h.t('fl_tpl_delete_ask', 'Onboarding'));
            await h.until('binned', () => h.store.callsOf('ws_flow_template_bin').length === 1, 3000);
            await h.waitFor('the card gone', () => !document.querySelector('#ws-root .wsf-tpl-card'), 4000);
            await h.eventually('a toast says so', (harness, text) => harness.toasts.some((toast) => toast.text === text), 3000, h.t('fl_tpl_deleted'));
            await h.eventually('Empty canvas has the focus', (harness) => harness.focused() && (harness.focused().refocus === 'tpl-empty'), 2000);
        }
    },

    {
        name: 'with no flow yet the screen says how to start, and New flow is all it offers',
        fixture: 'empty',
        run: async (h) => {
            await h.open();

            const state = await h.state();

            h.same('it says how to start', state.empty, h.t('fl_none_build'));
            h.ok('no box to add, no flow tools', !state.name && !state.problems && (await h.page(() => !document.querySelector('#ws-root .wsf-palette button'))), state);
            h.ok('New flow offered', !!(await h.probe('find', { text: h.t('fl_new'), within: BAR })));
            h.same('the address names no flow', state.search, '');
            h.same('only the list asked', h.store.calls.map((call) => call.action).filter((action) => action !== 'ws_tick'), ['ws_bootstrap', 'ws_flow_list']);
        }
    },

    {
        name: 'New flow makes an empty draft and puts its name in the field',
        run: async (h) => {
            await h.open();
            await h.click({ text: h.t('fl_new'), within: BAR });
            await h.until('created', () => h.store.callsOf('ws_flow_create').length === 1, 3000);
            await h.settle();
            h.same('named as an untitled flow, no box', h.store.callsOf('ws_flow_create')[0].data, { name: h.t('fl_untitled'), graph: { v: 1, nodes: [], edges: [], notes: [] } });

            const state = await h.state();

            h.same('the new flow shown', [state.name, state.search], [h.t('fl_untitled'), '?flow=20']);
            h.same('the empty canvas says what to do', state.empty, h.t('fl_empty_canvas'));
            h.same('its name has the focus, selected', await h.page(() => [document.activeElement.classList.contains('wsf-name'), document.activeElement.selectionStart, document.activeElement.selectionEnd]), [true, 0, h.t('fl_untitled').length]);
            h.same('first in the list', await h.page(() => document.querySelector('#ws-root .wsf-list-name').textContent), h.t('fl_untitled'));
            h.same('the server says it has no start', state.problems.text, h.t('fl_problems', 1));
        }
    },

    {
        name: 'Run asks, then starts one run whatever the clicks, and shows it box by box; a lost answer is asked again with the same token',
        run: async (h) => {
            await h.open('?flow=8');
            h.store.hold('ws_flow_run_start');
            await h.click({ text: h.t('fl_run'), within: BAR });

            const dialog = await h.answer(h.t('fl_run_do'));

            h.same('it asks first', dialog.text, h.t('fl_run_ask', 'Weekly report', 2));
            await h.until('the start sent', () => h.store.callsOf('ws_flow_run_start').length === 1, 2000);
            await h.click({ text: h.t('fl_run'), within: BAR }, { force: true });
            await h.sleep(400);
            h.same('a click while it is sent starts nothing', [h.store.callsOf('ws_flow_run_start').length, await h.dialog()], [1, null]);

            const token = h.store.callsOf('ws_flow_run_start')[0].data.start_token;

            h.ok('a start token of its own, not the form token', /^canvas\.[0-9a-f]{24}$/.test(token), token);
            h.store.release('ws_flow_run_start');
            await h.waitFor('the run shown', (harness) => harness.state().runBar !== '', 3000);

            let state = await h.state();

            h.ok('the bar names the run and its version', state.runBar.indexOf(h.t('fl_run_shown', 42, 2)) !== -1, state.runBar);
            h.ok('read-only while it is shown', state.readOnly && !state.palette, state);
            h.same('each box with its state', (await h.probe('boxes')).map((box) => [box.id, box.run]), [['manual_1', 'done'], ['task_1', 'none'], ['end_1', 'none']]);
            h.ok('in words too', (await h.probe('box', 'manual_1')).label.indexOf(h.t('fl_step_done')) !== -1, (await h.probe('box', 'manual_1')).label);
            await h.waitFor('a toast says it started', (harness, text) => harness.toasts.some((toast) => toast.text === text), 3000, h.t('fl_run_started'));
            await h.click({ text: h.t('fl_run_close'), within: '#ws-root .wsf-run-bar' });
            state = await h.state();
            h.ok('Close brings the draft back to edit', !state.readOnly && state.palette && !state.runBar, state);

            // The start reached the server, its answer did not.
            h.store.next('ws_flow_run_start', async (request, store) => {
                await store.base('ws_flow_run_start', request);
                return store.drop();
            });
            await h.click({ text: h.t('fl_run'), within: BAR });
            await h.answer(h.t('fl_run_do'));
            await h.waitFor('the lost answer is told', (harness) => harness.toasts.some((toast) => toast.tone === 'danger'), 3000);
            await h.settle();
            await h.click({ text: h.t('fl_run'), within: BAR });
            await h.answer(h.t('fl_run_do'));
            await h.until('asked again', () => h.store.callsOf('ws_flow_run_start').length === 3, 3000);
            await h.settle();

            const calls = h.store.callsOf('ws_flow_run_start');

            h.ok('with the same token', calls[2].data.start_token === calls[1].data.start_token, calls.map((call) => call.data.start_token));
            h.ok('a new token for a new start', calls[1].data.start_token !== calls[0].data.start_token);
            h.same('one run for it', h.store.runs.size, 4);
            await h.waitFor('the toast says it was started already', (harness, text) => harness.toasts.some((toast) => toast.text === text), 3000, h.t('fl_run_again'));
            h.ok('that run shown', (await h.state()).runBar.indexOf(h.t('fl_run_shown', 43, 2)) !== -1, (await h.state()).runBar);
        }
    },

    {
        name: 'Flow runs lists the runs; a run opened is drawn as it went, read again until it ends, and the change is told',
        run: async (h) => {
            await h.open('?flow=8');
            await h.click({ text: h.t('fl_runs'), within: BAR });
            await h.until('the runs asked', () => h.store.callsOf('ws_flow_runs_list').length === 1, 3000);
            await h.settle();
            h.same('newest first', await h.page(() => Array.prototype.map.call(document.querySelectorAll('#wsf-side .wsf-run-item .fw-semibold'), (node) => node.textContent)), [h.t('fl_run_name', 41), h.t('fl_run_name', 40)]);
            await h.click({ has: h.t('fl_run_name', 41), css: '#wsf-side .wsf-run-item' });
            await h.waitFor('the run shown', (harness) => harness.state().runBar !== '', 3000);
            h.same('each box as it went', (await h.probe('boxes')).map((box) => [box.id, box.run]), [['manual_1', 'done'], ['task_1', 'waiting'], ['end_1', 'none']]);
            h.ok('the waiting box links its task', !!(await h.probe('find', { css: BOX('task_1') + ' a.wsf-run-task' })));
            h.same('the arrow its start took', (await h.probe('edges')).map((edge) => edge.taken), [true, false]);
            h.same('the run\'s title has the focus', (await focus(h)).refocus, 'run-title');

            const opened = Date.now();

            await h.until('read again on a timer', () => h.store.callsOf('ws_flow_run_get').length >= 2, 4000);
            h.ok('about 2 s later', h.store.callsOf('ws_flow_run_get')[1].at - opened >= 1500, (h.store.callsOf('ws_flow_run_get')[1].at - opened) + ' ms');

            const run = h.store.runs.get(41);

            run.steps[1].status = 'done';
            run.steps[1].outcome = 'done';
            run.steps[1].finished_at = '2026-10-29T10:00:00Z';
            run.steps[1].result = 'Done by Mehmet Kaya';
            run.steps.push({ node_id: 'end_1', status: 'done', outcome: null, task_id: null, started_at: '2026-10-29T10:00:00Z', finished_at: '2026-10-29T10:00:00Z', result: '' });
            run.status = 'done';
            run.finished_at = '2026-10-29T10:00:00Z';

            await h.waitFor('the change drawn', (harness) => harness.box('task_1').run === 'done', 8000);
            h.same('every box as it ended', (await h.probe('boxes')).map((box) => [box.id, box.run]), [['manual_1', 'done'], ['task_1', 'done'], ['end_1', 'done']]);
            await h.eventually('told to screen readers', (harness, text) => harness.live().indexOf(text) !== -1, 2000, h.t('fl_run_pair', 'Write the report', h.t('fl_step_done')));

            const reads = h.store.callsOf('ws_flow_run_get').length;

            await h.sleep(4000);
            h.same('not read again once it ended', h.store.callsOf('ws_flow_run_get').length, reads);
        }
    }
];
