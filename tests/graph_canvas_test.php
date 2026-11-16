<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the graph canvas core (assets/js/pg_graph_canvas.js,
 * window.PGGraphCanvas): it is bundled and printed before the flow canvas
 * that uses it, the flow canvas keeps no copy of what moved into it, its
 * viewport pans and zooms as the flow canvas did, and create() routes the
 * pointer and the keys.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The core is a bundle of its own, and the flows screen prints it before
// the flow canvas: workspace_flow.js reads window.PGGraphCanvas as it runs.
function test_graph_canvas_bundled_and_printed_first()
{
	$bundles = pg_js_bundles();

	pg_assert_same(array('assets/js/pg_graph_canvas.js'), isset($bundles['assets/js/pg_graph_canvas.min.js']) ? $bundles['assets/js/pg_graph_canvas.min.js'] : null, 'the core has its bundle');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');
	$start = strpos($screen, 'function ws_screen_assets(');
	$body = ($start !== false) ? substr($screen, $start, strpos($screen, "\n}\n", $start) - $start) : '';
	$core = strpos($body, "pg_js_bundle_tags('assets/js/pg_graph_canvas.min.js')");
	$flow = strpos($body, "pg_js_bundle_tags('assets/js/workspace_flow.min.js')");

	pg_assert_true(($core !== false) && ($flow !== false) && ($core < $flow), 'the flows screen prints the core before the flow canvas');

	$harness = (string) file_get_contents(__DIR__ . '/js/flows_canvas.js');

	pg_assert_true(strpos($harness, "'assets/js/pg_graph_canvas.min.js', 'assets/js/workspace_flow.min.js'") !== false, 'the browser harness serves the core before the flow canvas');
}

// What moved into the core is not defined again in the flow canvas, which
// takes it from window.PGGraphCanvas.
function test_graph_canvas_flow_canvas_keeps_no_copy()
{
	$flow = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));

	pg_assert_contains('    var G = window.PGGraphCanvas;', $flow, 'the flow canvas reads the core');

	foreach (array('snap', 'clamp', 'svgEl', 'curve', 'freeId', 'typing', 'bytes', 'historyOf', 'historyRecord', 'historyStep', 'applyView', 'zoomAt', 'zoomBy', 'fit', 'canvasPoint', 'pointerDown', 'pointerMove', 'pointerUp', 'cancelDrag', 'port') as $name) {
		pg_assert_false((bool) preg_match('/^ *function ' . $name . '\(/m', $flow), 'no ' . $name . '() of its own');
	}

	foreach (array('ZOOM_MIN', 'ZOOM_MAX', 'SVG_NS', 'HISTORY_MAX', 'HISTORY_FOLD') as $name) {
		pg_assert_false((bool) preg_match('/^ *var ' . $name . ' = /m', $flow), 'no ' . $name . ' of its own');
	}
}

// The core's own class names are pgc-, each styled in the pgc- block of
// backend.src.css on --pgc-* tokens; the Workspace maps the tokens to its
// --ws-* scale, and the flow canvas keeps no wsf- name for what the core
// draws.
function test_graph_canvas_css_on_pgc_tokens()
{
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_graph_canvas.js');
	$css = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css'));
	$flow = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	pg_assert_true((bool) preg_match('/var CLASSES = \{(.*?)\};/s', $core, $block), 'the core names its classes');
	preg_match_all("/(\w+): '([\w-]+)'/", $block[1], $names, PREG_SET_ORDER);
	pg_assert_same(14, count($names), 'fourteen class names');

	foreach ($names as $name) {
		pg_assert_same(0, strpos($name[2], 'pgc-'), $name[1] . ' is a pgc- class');
		pg_assert_true((bool) preg_match('/\.' . preg_quote($name[2], '/') . '[ .:,{]/', $css), $name[2] . ' has a rule');
	}

	$start = strpos($css, '/* Graph canvas core (pg_graph_canvas.js)');
	$end = strpos($css, '/* The Flows canvas on the core:', (int) $start);
	pg_assert_true($start !== false && $end !== false, 'the core block is in backend.src.css');
	$block = substr($css, (int) $start, (int) $end - (int) $start);
	pg_assert_false((bool) preg_match('/var\(--(ws|wsf|bs)-/', $block), 'the core block reads no Workspace or Bootstrap variable');
	pg_assert_false(strpos($block, '.wsf-') !== false, 'and no Flows class');

	foreach (array('accent', 'soft', 'radius', 'radius-sm', 'shadow', 'shadow-accent') as $token) {
		pg_assert_contains('  --pgc-' . $token . ': var(--ws-' . $token . ');', $css, 'the Workspace maps --pgc-' . $token);
	}

	pg_assert_false(strpos($flow, "classes: {") !== false, 'the flow canvas takes the core\'s class names');
	pg_assert_false((bool) preg_match('/wsf-(node|edge|edges|port|stage|viewport|layer|boxes|selected|target)(?![\w-])/', $flow), 'and keeps no wsf- name for them');
}

// Runs the core in Node with a stand-in viewport 800 x 600 at (100, 50) and
// the script given; null when Node cannot run.
function graph_canvas_test_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$stage = <<<'JS'
var window = {};
function Box() { this.style = {}; this.textContent = ''; }
var ui = { viewport: new Box(), layer: new Box(), zoomLabel: new Box() };
ui.viewport.getBoundingClientRect = function () { return { left: 100, top: 50, width: 800, height: 600 }; };
var state = { view: { x: 40, y: 40, k: 1 }, nodes: [] };
JS;
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_graph_canvas.js');
	$host = <<<'JS'
var G = window.PGGraphCanvas;
var view = G.viewport({
    ui: ui,
    view: function (next) { if (next) { state.view = next; } return state.view; },
    items: function () { return state.nodes; }
});
JS;

	$file = @tempnam(sys_get_temp_dir(), 'pggraph');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $stage . "\n" . $core . "\n" . $host . "\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// The viewport: the transform, grid and label follow the view; zoom keeps
// the point under the pointer; zoom stays within 0.25-2; fit brings every
// box into sight (an empty canvas goes back to 40, 40 at 100%); a point is
// read in canvas units; a box near the edge is brought into the middle; a
// pinch and the wheel move the view.
function test_graph_canvas_viewport()
{
	$result = graph_canvas_test_node(<<<'JS'
var out = {};
function copy(value) { return JSON.parse(JSON.stringify(value)); }
view.apply();
out.apply = [ui.layer.style.transform, ui.viewport.style.backgroundSize, ui.viewport.style.backgroundPosition, ui.zoomLabel.textContent];
var before = view.point(300, 250);
view.zoomAt(300, 250, 2);
var after = view.point(300, 250);
out.zoomKeepsPoint = [before, after, state.view.k];
view.zoomBy(10);
out.zoomMax = state.view.k;
view.zoomBy(0.001);
out.zoomMin = [state.view.k, ui.viewport.style.backgroundSize, ui.zoomLabel.textContent];
view.fit();
out.fitEmpty = copy(state.view);
state.nodes = [{ x: 0, y: 0 }, { x: 1000, y: 400 }];
view.fit();
out.fit = [Math.round(state.view.x * 100) / 100, Math.round(state.view.y * 100) / 100, Math.round(state.view.k * 1000) / 1000];
state.view = { x: 0, y: 0, k: 1 };
view.reveal({ x: 100, y: 100 });
out.revealInSight = copy(state.view);
view.reveal({ x: 2000, y: 100 });
out.revealOut = copy(state.view);
state.view = { x: 0, y: 0, k: 1 };
var pinch = view.pinchStart([{ x: 200, y: 150 }, { x: 300, y: 150 }]);
view.pinchMove(pinch, [{ x: 150, y: 150 }, { x: 350, y: 150 }]);
out.pinch = copy(state.view);
var prevented = false;
view.wheel({ preventDefault: function () { prevented = true; }, deltaX: 5, deltaY: 7, ctrlKey: false, metaKey: false });
out.wheel = [state.view.x, state.view.y, prevented];
out.history = (function () {
    var h = G.historyOf(1, 'a');
    G.historyRecord(h, 'b', false, 0);
    return [G.historyStep(h, true), G.historyStep(h, false)];
})();
out.helpers = [G.snap(23), G.snap(25), G.clamp(5, 0, 3), G.freeId('task', [{ id: 'task_1' }]), G.curve(0, 0, 10, 20).d];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('translate(40px, 40px) scale(1)', '16px 16px', '40px 40px', '100%'), $result['apply'], 'the layer, the grid and the label follow the view');
	pg_assert_same($result['zoomKeepsPoint'][0], $result['zoomKeepsPoint'][1], 'zoom keeps the point under the pointer');
	pg_assert_same(2, $result['zoomKeepsPoint'][2], 'zoomed twice as large');
	pg_assert_same(2, $result['zoomMax'], 'at most 200%');
	pg_assert_same(array(0.25, '16px 16px', '25%'), $result['zoomMin'], 'at least 25%, every fourth grid dot');
	pg_assert_same(array('x' => 40, 'y' => 40, 'k' => 1), $result['fitEmpty'], 'an empty canvas fits at 40, 40, 100%');
	pg_assert_same(array(48, 160.13, 0.583), $result['fit'], 'fit brings every box into sight');
	pg_assert_same(array('x' => 0, 'y' => 0, 'k' => 1), $result['revealInSight'], 'a box in sight is left where it is');
	pg_assert_same(array('x' => -1704, 'y' => 160, 'k' => 1), $result['revealOut'], 'a box out of sight comes to the middle');
	pg_assert_same(array('x' => -150, 'y' => -100, 'k' => 2), $result['pinch'], 'a pinch zooms about its middle');
	pg_assert_same(array(-155, -107, true), $result['wheel'], 'the wheel pans');
	pg_assert_same(array('a', 'b'), $result['history'], 'undo and redo');
	pg_assert_same(array(16, 32, 3, 'task_2', 'M0 0 C48 0 -38 20 10 20'), $result['helpers'], 'the geometry helpers');
}

// The canvas's pointer and keys (create()): a drag on the empty canvas pans
// once it passes 4 px; the adapter's own kind goes to its move and release;
// a press the adapter takes is forgotten; a second finger cancels the drag
// and pinches; arrows the adapter leaves pan, + zooms, 0 fits; Space held
// on the canvas is passed to the next press; destroy() removes every
// listener it added.
function test_graph_canvas_pointer_and_keys()
{
	$result = graph_canvas_test_node(<<<'JS'
function Target(name) { this.name = name; }
Target.prototype.closest = function (selector) { return (this.name === 'link') && (selector === 'a[href]') ? this : null; };
function listenable(node) {
    node.listeners = {};
    node.addEventListener = function (type, handler) { (node.listeners[type] = node.listeners[type] || []).push(handler); };
    node.removeEventListener = function (type, handler) { node.listeners[type] = (node.listeners[type] || []).filter(function (item) { return item !== handler; }); };
    node.fire = function (type, event) { (node.listeners[type] || []).slice().forEach(function (handler) { handler(event); }); };
    return node;
}
var document = listenable({});
listenable(ui.viewport);
ui.viewport.classes = {};
ui.viewport.classList = { add: function (name) { ui.viewport.classes[name] = true; }, remove: function (name) { delete ui.viewport.classes[name]; } };
ui.viewport.setPointerCapture = function () {};
ui.viewport.contains = function (target) { return target.name !== 'outside'; };
ui.temp = { classList: { add: function () {}, remove: function () {} } };
ui.boxes = { querySelectorAll: function () { return []; } };
var log = [];
var canvas = G.create({
    ui: ui,
    view: function (next) { if (next) { state.view = next; } return state.view; },
    items: function () { return state.nodes; },
    ownClicks: 'a[href]',
    editable: function () { return false; },
    item: function () { return null; },
    classes: { panning: 'is-panning', space: 'is-space', box: 'box', port: 'port', edge: 'edge', selected: 'on', target: 'aim', hidden: 'off' },
    press: function (event, drag, space) {
        log.push('press ' + event.target.name + (space ? ' space' : ''));
        if (event.target.name === 'field') { return false; }
        if (event.target.name === 'box') { drag.kind = 'thread'; }
        return true;
    },
    move: function (event, drag, dx, dy) { log.push('move ' + dx + ',' + dy); },
    release: function (event, drag) { log.push('release ' + drag.kind + ' ' + drag.moved); },
    cancel: function (drag) { log.push('cancel ' + (drag ? drag.kind : 'none')); },
    key: function (event) { log.push('key ' + event.key); return event.key === 'Delete'; }
});
canvas.bind();
function pointer(type, id, x, y, name) {
    ui.viewport.fire(type, { type: type, pointerId: id, clientX: x, clientY: y, button: 0, target: new Target(name || 'empty'), preventDefault: function () {} });
}
function key(name, target) {
    var event = { key: name, target: new Target(target || 'canvas'), shiftKey: false, altKey: false, prevented: false, preventDefault: function () { event.prevented = true; } };
    ui.viewport.fire('keydown', event);
    return event.prevented;
}
var out = {};
state.view = { x: 0, y: 0, k: 1 };
pointer('pointerdown', 1, 200, 200);
out.panning = !!ui.viewport.classes['is-panning'];
pointer('pointermove', 1, 202, 201);
out.small = [state.view.x, state.view.y];
pointer('pointermove', 1, 230, 210);
out.pan = [state.view.x, state.view.y];
pointer('pointerup', 1, 230, 210);
out.panEnd = [!!ui.viewport.classes['is-panning'], canvas.dragging()];
pointer('pointerdown', 2, 300, 300, 'box');
pointer('pointermove', 2, 310, 300, 'box');
pointer('pointerup', 2, 310, 300, 'box');
out.box = [state.view.x, canvas.dragging()];
pointer('pointerdown', 3, 300, 300, 'field');
out.taken = canvas.dragging();
pointer('pointerdown', 4, 300, 300, 'link');
pointer('pointerdown', 5, 300, 300, 'box');
pointer('pointerdown', 6, 400, 300);
pointer('pointermove', 6, 450, 300);
out.pinch = [canvas.dragging(), state.view.k];
pointer('pointerup', 6, 450, 300);
pointer('pointerup', 5, 300, 300);
state.view = { x: 0, y: 0, k: 1 };
out.keys = [key('Delete'), key('ArrowRight'), state.view.x, key('+'), state.view.k, key('x'), key('ArrowLeft', 'link')];
document.fire('keydown', { key: ' ', target: new Target('canvas'), preventDefault: function () {} });
out.space = !!ui.viewport.classes['is-space'];
pointer('pointerdown', 7, 10, 10, 'box');
pointer('pointerup', 7, 10, 10, 'box');
document.fire('keyup', { key: ' ' });
out.spaceUp = !!ui.viewport.classes['is-space'];
out.log = log;
canvas.destroy();
out.left = Object.keys(ui.viewport.listeners).concat(Object.keys(document.listeners)).filter(function (type) {
    return ((ui.viewport.listeners[type] || []).length + (document.listeners[type] || []).length) > 0;
});
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_true($result['panning'], 'the empty canvas pans');
	pg_assert_same(array(0, 0), $result['small'], 'nothing moves within 4 px');
	pg_assert_same(array(30, 10), $result['pan'], 'the view follows the pointer');
	pg_assert_same(array(false, null), $result['panEnd'], 'the pan ends with its pointer');
	pg_assert_same(array(30, null), $result['box'], 'the adapter\'s drag moves no view');
	pg_assert_same(null, $result['taken'], 'a press the adapter took is no drag');
	pg_assert_same(array(null, 1.5), $result['pinch'], 'a second finger cancels the drag and pinches');
	pg_assert_same(array(true, true, -32, true, 1.2, false, false), $result['keys'], 'the adapter\'s key, then arrows pan, + zooms; other keys and a link\'s keys are left');
	pg_assert_same(array(true, false), array($result['space'], $result['spaceUp']), 'Space held on the canvas');
	pg_assert_same(array(
		'press empty', 'release pan true',
		'press box', 'move 10,0', 'release thread true',
		'press field',
		'press box', 'cancel thread',
		'key Delete', 'key ArrowRight', 'key +', 'key x',
		'press box space', 'release thread false',
	), $result['log'], 'the adapter hears each press, drag, release, cancel and key');
	pg_assert_same(array(), $result['left'], 'destroy() removes every listener');
}

// The core's texts: every pgc_ key it reads is handed over by
// pg_graph_canvas_js_strings(), every key handed over is read, each text has
// its Turkish key, and the Workspace screen hands them to its script.
function test_graph_canvas_texts_are_handed_over_and_translated()
{
	$strings = pg_graph_canvas_js_strings();
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_graph_canvas.js');
	$keys = pg_test_language_map();

	preg_match_all("/'(pgc_[a-z_]+)'/", $core, $matches);

	pg_assert_true(count($matches[1]) > 0, 'the core reads its texts');

	foreach (array_unique($matches[1]) as $key) {
		pg_assert_true(isset($strings[$key]), 'handed over: ' . $key);
	}

	foreach (array_keys($strings) as $key) {
		pg_assert_contains("'" . $key . "'", $core, 'read by the core: ' . $key);
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/graph_canvas.php');

	preg_match_all("/lang\\(\\s*(?:array\\(\\s*'string'\\s*=>\\s*)?'((?:[^'\\\\]|\\\\.)*)'/", $source, $texts);

	pg_assert_same(count($strings), count($texts[1]), 'each text through lang()');

	foreach ($texts[1] as $text) {
		$text = str_replace("\\'", "'", $text);
		pg_assert_true(is_array($keys) && array_key_exists($text, $keys), 'a Turkish key: ' . $text);
	}

	pg_assert_contains('pg_graph_canvas_js_strings()', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php'), 'the Workspace screen hands them over');
}

// The canvas's boxes and arrows (create()): a box carries its focus, its
// name and its ports around the adapter's content; an arrow goes from the
// right edge of one box to the left edge of the next; the selection's mark
// moves; a box dragged moves on the grid and tells the adapter, Esc (cancel)
// puts it back; an arrow drawn from the out port lights the box it may go
// to and is handed to the adapter; a read-only box is only selected; an
// arrow pressed is selected; the live region is read out again.
function test_graph_canvas_boxes_and_arrows()
{
	$result = graph_canvas_test_node(graph_canvas_test_dom() . <<<'JS'
var layer = new El('div');
ui.layer = layer;
ui.boxes = layer.appendChild(new El('div'));
ui.svg = layer.appendChild(new El('svg'));
ui.temp = ui.svg.appendChild(new El('path'));
ui.temp.className = 'off';
ui.live = new El('div');
var viewportListeners = {};
ui.viewport.addEventListener = function (type, handler) { viewportListeners[type] = handler; };
ui.viewport.classList = { add: function () {}, remove: function () {} };
ui.viewport.setPointerCapture = function () {};
ui.viewport.focus = function () { focused = ui.viewport; };
var a = { id: 'a', x: 0, y: 0 };
var b = { id: 'b', x: 400, y: 0 };
var editable = true;
var log = [];
var canvas = G.create({
    ui: ui,
    view: function (next) { if (next) { state.view = next; } return state.view; },
    items: function () { return [a, b]; },
    text: function (key) { return 'T:' + key; },
    item: function (id) { return { a: a, b: b }[id] || null; },
    editable: function () { return editable; },
    select: function (selected) { log.push('select ' + selected.type + ' ' + selected.id); },
    placed: function () {},
    edges: function () { log.push('edges'); },
    moved: function (item) { log.push('moved ' + item.id); },
    canConnect: function (from, to) { return from !== to; },
    connected: function (from, to) { log.push('connected ' + from.id + ' ' + (to ? to.id : null)); },
    classes: { panning: 'is-panning', space: 'is-space', box: 'box', port: 'port', edge: 'edge', selected: 'on', target: 'aim', hidden: 'off' },
    press: function () { return true; },
    move: function () { log.push('adapter move'); },
    release: function (event, drag) { log.push('release ' + drag.kind + (drag.empty ? ' empty' : '')); },
    cancel: function (drag) { log.push('cancel ' + (drag ? drag.kind : 'none')); },
    key: function () { return false; }
});
canvas.bind();
state.view = { x: 0, y: 0, k: 1 };
function pointer(type, id, x, y, target) {
    viewportListeners[type]({ type: type, pointerId: id, clientX: x, clientY: y, button: 0, target: target, preventDefault: function () { log.push('prevented'); } });
}
var out = {};
var boxA = ui.boxes.appendChild(canvas.box(a, { className: 'kind', out: true, fill: function (box) { box.appendChild(new El('i')); } }));
var boxB = ui.boxes.appendChild(canvas.box(b, { into: true }));
var names = function (node) { return node.children.map(function (child) { return child.tag + '.' + child.className; }); };
out.boxA = [boxA.className, boxA.tabIndex, boxA.attrs.role, boxA.attrs['aria-roledescription'], boxA.attrs['data-node'], boxA.attrs['data-refocus'], names(boxA), boxA.children[1].title, boxA.children[1].attrs['aria-hidden'], boxA.style.left];
out.boxB = [names(boxB), boxB.style.left];
var drawn = canvas.edge(0, a, b);
out.edge = [names(ui.svg).slice(1), drawn.group.attrs['data-edge'], drawn.group.children[0].attrs.d === G.curve(208, 40, 400, 40).d, drawn.group.children[1].attrs.d === drawn.group.children[0].attrs.d, drawn.path.mx];
canvas.mark({ type: 'node', id: 'b' });
out.mark = [boxB.className, boxB.attrs['aria-current']];
canvas.mark({ type: 'edge', id: 0 });
out.markMoved = [boxB.className, boxB.attrs['aria-current'] || null, drawn.group.className];
canvas.mark(null);
out.markNone = drawn.group.className;
log = [];
pointer('pointerdown', 1, 200, 100, boxA.children[0]);
out.pressBox = [canvas.dragging().kind, focused === boxA];
pointer('pointermove', 1, 237, 120, boxA.children[0]);
out.moved = [a.x, a.y, boxA.style.left, boxA.style.top];
pointer('pointerup', 1, 237, 120, boxA.children[0]);
pointer('pointerdown', 2, 200, 100, boxA.children[0]);
pointer('pointermove', 2, 300, 100, boxA.children[0]);
canvas.cancel();
pointer('pointerup', 2, 300, 100, boxA.children[0]);
out.back = [a.x, a.y, boxA.style.left];
out.moveLog = log;
log = [];
under = boxB.children[0];
pointer('pointerdown', 3, 300, 100, boxA.children[1]);
pointer('pointermove', 3, 500, 100, boxA.children[1]);
out.drawing = [canvas.dragging().kind, ui.temp.className, ui.temp.attrs.d.indexOf('M240 56 C') === 0, boxB.className];
pointer('pointerup', 3, 500, 100, boxA.children[1]);
out.drawn = [ui.temp.className, boxB.className];
under = boxA;
pointer('pointerdown', 4, 300, 100, boxA.children[1]);
pointer('pointermove', 4, 500, 100, boxA.children[1]);
out.self = boxA.className;
pointer('pointerup', 4, 500, 100, boxA.children[1]);
out.connectLog = log;
log = [];
editable = false;
pointer('pointerdown', 5, 300, 100, boxA.children[1]);
pointer('pointermove', 5, 340, 100, boxA.children[1]);
pointer('pointerup', 5, 340, 100, boxA.children[1]);
out.readOnly = [a.x, log];
log = [];
pointer('pointerdown', 6, 300, 100, drawn.group.children[1]);
pointer('pointerup', 6, 300, 100, drawn.group.children[1]);
pointer('pointerdown', 7, 300, 100, layer);
pointer('pointerup', 7, 300, 100, layer);
out.others = [log, focused === ui.viewport];
canvas.announce('Arrow added');
out.live = ui.live.textContent;
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('box kind', 0, 'group', 'T:pgc_box', 'a', 'box-a', array('i.', 'span.port port-out'), 'T:pgc_port_out', 'true', '0px'), $result['boxA'], 'a box: focus, role, name, its content, the out port last with its title');
	pg_assert_same(array(array('span.port port-in'), '400px'), $result['boxB'], 'the in port first, at its place');
	pg_assert_same(array(array('g.edge'), '0', true, true, 304), $result['edge'], 'an arrow from one box\'s right edge to the other\'s left one');
	pg_assert_same(array('box on', 'true'), $result['mark'], 'the box selected is marked');
	pg_assert_same(array('box', null, 'edge on'), $result['markMoved'], 'the mark moves to the arrow selected');
	pg_assert_same('edge', $result['markNone'], 'nothing selected, nothing marked');
	pg_assert_same(array('move', true), $result['pressBox'], 'a box pressed is moved and focused');
	pg_assert_same(array(32, 16, '32px', '16px'), $result['moved'], 'it moves on the grid');
	pg_assert_same(array(32, 16, '32px'), $result['back'], 'cut short, it goes back');
	pg_assert_same(array('select node a', 'edges', 'moved a', 'select node a', 'edges', 'edges', 'cancel move'), $result['moveLog'], 'the adapter selects it, redraws the arrows, hears where it went');
	pg_assert_same(array('connect', '', true, 'box aim'), $result['drawing'], 'an arrow drawn from the out port lights the box it may go to');
	pg_assert_same(array('off', 'box'), $result['drawn'], 'released, the arrow is handed over');
	pg_assert_same('box kind', $result['self'], 'a box it may not go to stays dark');
	pg_assert_same(array('prevented', 'connected a b', 'prevented', 'connected a a'), $result['connectLog'], 'the adapter decides whether the arrow is added');
	pg_assert_same(array(32, array('select node a', 'release none')), $result['readOnly'], 'a read-only box is selected only');
	pg_assert_same(array(array('select edge 0', 'release none', 'release pan empty'), true), $result['others'], 'an arrow pressed is selected, the empty canvas pans');
	pg_assert_same('Arrow added', $result['live'], 'the live region reads the text');
}

// Slice 4 of the core, for a second user: a box the adapter sizes is drawn
// and fitted at its size; the zoom may go lower, never below 0.05; an arrow
// leaves a named port's row and ends spread over its target's left edge;
// its id is kept as given (a name, not only an index); a box moved without
// edges() draws only its own arrows again; an arrow may end on another
// target (an end chip); a box's picture is asked for once it is in sight.
function test_graph_canvas_sizes_ports_and_targets()
{
	$result = graph_canvas_test_node(graph_canvas_test_dom() . <<<'JS'
var out = {};
out.spread = G.spread([{ to: 'b', y: 50 }, { to: 'b', y: 10 }, { to: 'c', y: 0 }, { to: 'b', y: 10 }]);
var sized = { x: 0, y: 0, w: 1200, h: 2000 };
var far = G.viewport({ ui: ui, view: function (next) { if (next) { state.view = next; } return state.view; }, items: function () { return [sized]; }, size: function (item) { return { w: item.w, h: item.h }; }, zoomMin: 0.01 });
state.view = { x: 0, y: 0, k: 1 };
far.zoomBy(0.0001);
out.floor = state.view.k;
far.fit();
out.fit = Math.round(state.view.k * 1000) / 1000;
var layer = new El('div');
ui.layer = layer;
ui.boxes = layer.appendChild(new El('div'));
ui.svg = layer.appendChild(new El('svg'));
ui.temp = ui.svg.appendChild(new El('path'));
ui.temp.className = 'pgc-hidden';
ui.live = new El('div');
var listeners = {};
ui.viewport.addEventListener = function (type, handler) { listeners[type] = handler; };
ui.viewport.removeEventListener = function () {};
document.removeEventListener = function () {};
ui.viewport.classList = { add: function () {}, remove: function () {} };
ui.viewport.setPointerCapture = function () {};
ui.viewport.focus = function () {};
var a = { id: 'a', x: 0, y: 0, w: 320, h: 400 };
var b = { id: 'b', x: 640, y: 0, w: 320, h: 300 };
var chip = { id: 'mail', x: 640, y: 480, w: 160, h: 40 };
var log = [];
var thumbs = [];
var canvas = G.create({
    ui: ui,
    view: function (next) { if (next) { state.view = next; } return state.view; },
    items: function () { return [a, b]; },
    size: function (item) { return { w: item.w, h: item.h }; },
    zoomMin: 0.05,
    text: function (key) { return key; },
    item: function (id) { return { a: a, b: b }[id] || null; },
    editable: function () { return true; },
    select: function (selected) { log.push('select ' + selected.type + ' ' + JSON.stringify(selected.id)); },
    moved: function (item) { log.push('moved ' + item.id); },
    canConnect: function (from, to, port) { return port === 'nav-1'; },
    connected: function (from, to, port) { log.push('connected ' + from.id + ' ' + (to ? to.id : null) + ' ' + port); },
    targets: '[data-target]',
    target: function (element) { return element.getAttribute('data-target') === 'mail' ? chip : null; },
    thumb: function (item, holder) { thumbs.push(item.id + ' ' + holder.className); },
    press: function () { return true; },
    move: function () {},
    release: function (event, drag) { log.push('release ' + drag.kind); },
    cancel: function () {},
    key: function () { return false; }
});
canvas.bind();
state.view = { x: 0, y: 0, k: 1 };
var row;
var boxA = ui.boxes.appendChild(canvas.box(a, { out: true, thumb: true, fill: function (node) {
    var list = node.appendChild(new El('ul'));
    list.offsetTop = 200;
    list.offsetParent = node;
    row = list.appendChild(new El('li'));
    row.offsetTop = 32;
    row.offsetHeight = 24;
    row.offsetParent = list;
    row.appendChild(canvas.port('out', 'nav-1'));
} }));
var boxB = ui.boxes.appendChild(canvas.box(b, { into: true }));
var chipEl = ui.boxes.appendChild(new El('div'));
chipEl.setAttribute('data-target', 'mail');
out.box = [boxA.style.width, boxA.style.height, boxA.children[0].className, boxA.children[0].attrs['aria-hidden'], thumbs];
out.port = [row.children[0].className, row.children[0].attrs['data-port']];
var named = canvas.edge('nav-1>b', a, b, { port: 'nav-1', slot: 0, slots: 2 });
var plain = canvas.edge('a>b', a, b, { slot: 1, slots: 2 });
out.named = [named.group.attrs['data-edge'], named.group.children[0].attrs.d === G.curve(320, 244, 640, 100).d, plain.group.children[0].attrs.d === G.curve(320, 200, 640, 200).d];
log = [];
pointer('pointerdown', 1, 300, 100, named.group.children[1]);
pointer('pointerup', 1, 300, 100, named.group.children[1]);
var before = plain.group.children[0].attrs.d;
pointer('pointerdown', 2, 300, 100, boxB);
pointer('pointermove', 2, 332, 100, boxB);
pointer('pointerup', 2, 332, 100, boxB);
out.rerouted = [b.x, plain.group.children[0].attrs.d !== before, plain.group.children[0].attrs.d === G.curve(320, 200, 672, 200).d, plain.group.children[1].attrs.d === plain.group.children[0].attrs.d];
ui.svg.children = ui.svg.children.filter(function (child) { return child !== plain.group; });
plain.group.parentNode = null;
canvas.reroute(['a']);
out.forgotten = plain.group.children[0].attrs.d === G.curve(320, 200, 672, 200).d;
under = chipEl;
pointer('pointerdown', 3, 300, 100, row.children[0]);
pointer('pointermove', 3, 500, 300, row.children[0]);
out.aim = [ui.temp.attrs.d.indexOf('M320 244 C') === 0, chipEl.className];
pointer('pointerup', 3, 500, 300, row.children[0]);
under = boxB;
pointer('pointerdown', 4, 300, 100, boxA.children[2]);
pointer('pointermove', 4, 500, 300, boxA.children[2]);
out.plainAim = [ui.temp.attrs.d.indexOf('M320 200 C') === 0, boxB.className];
pointer('pointerup', 4, 500, 300, boxB.children[0]);
out.log = log;
var observed = [];
var disconnected = false;
window.IntersectionObserver = function (handler) { this.handler = handler; observed.push(this); };
window.IntersectionObserver.prototype.observe = function (node) { this.node = node; };
window.IntersectionObserver.prototype.unobserve = function () {};
window.IntersectionObserver.prototype.disconnect = function () { disconnected = true; };
thumbs = [];
var later = canvas.box(b, { thumb: true });
out.lazy = [thumbs.length];
observed[0].handler([{ isIntersecting: true, target: later.children[0] }]);
observed[0].handler([{ isIntersecting: true, target: later.children[0] }]);
out.lazy.push(thumbs);
canvas.destroy();
out.lazy.push(disconnected);
console.log(JSON.stringify(out));
function pointer(type, id, x, y, target) {
    listeners[type]({ type: type, pointerId: id, clientX: x, clientY: y, button: 0, target: target, preventDefault: function () {} });
}
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(array('slot' => 2, 'slots' => 3), array('slot' => 0, 'slots' => 3), array('slot' => 0, 'slots' => 1), array('slot' => 1, 'slots' => 3)), $result['spread'], 'arrows into one box ordered by height, ties in their order');
	pg_assert_same(0.05, $result['floor'], 'the zoom goes lower when asked, never below 0.05');
	pg_assert_same(0.252, $result['fit'], 'fit reads each box\'s size');
	pg_assert_same(array('320px', '400px', 'pgc-thumb', 'true', array('a pgc-thumb')), $result['box'], 'a sized box is drawn at its size, its picture holder first, filled at once without an observer');
	pg_assert_same(array('pgc-port pgc-port-out', 'nav-1'), $result['port'], 'a named port carries its name');
	pg_assert_same(array('nav-1>b', true, true), $result['named'], 'an arrow leaves its port\'s row, ends at its slot, its id kept');
	pg_assert_same(array(672, true, true, true), $result['rerouted'], 'a box moved without edges() draws its own arrows again');
	pg_assert_true($result['forgotten'], 'an arrow removed from the canvas is forgotten');
	pg_assert_same(array(true, 'pgc-target'), $result['aim'], 'an arrow from a named port lights another target it may go to');
	pg_assert_same(array(true, 'pgc-node'), $result['plainAim'], 'a box the adapter refuses stays dark');
	pg_assert_same(array('select edge "nav-1>b"', 'release none', 'select node "b"', 'moved b', 'connected a mail nav-1', 'connected a b null'), $result['log'], 'a named id is selected as given; the port goes with the arrow');
	pg_assert_same(array(0, array('b pgc-thumb'), true), $result['lazy'], 'a picture asked for once in sight, once; destroy() stops watching');
}

// The focus (adapter.focus): with no box in focus no arrow is drawn; a box
// in focus draws its arrows out and in, dims the boxes and targets at
// neither end and is announced with its counts; allEdges() draws the others
// faint, under them; a focus change touches only the arrows that change; a
// click on the empty canvas takes the focus away; groups() draws headings
// behind the boxes; without the field every arrow is drawn as before.
function test_graph_canvas_focus()
{
	$result = graph_canvas_test_node(graph_canvas_test_dom() . <<<'JS'
var out = {};
function setup(focusField) {
    var layer = new El('div');
    ui.layer = layer;
    ui.boxes = layer.appendChild(new El('div'));
    ui.svg = layer.appendChild(new El('svg'));
    ui.temp = ui.svg.appendChild(new El('path'));
    ui.temp.className = 'pgc-hidden';
    ui.live = new El('div');
    var listeners = {};
    ui.viewport.addEventListener = function (type, handler) { listeners[type] = handler; };
    ui.viewport.removeEventListener = function () {};
    document.removeEventListener = function () {};
    ui.viewport.classList = { add: function () {}, remove: function () {} };
    ui.viewport.setPointerCapture = function () {};
    ui.viewport.focus = function () {};
    var items = { a: { id: 'a', x: 0, y: 0 }, b: { id: 'b', x: 300, y: 0 }, c: { id: 'c', x: 600, y: 0 }, d: { id: 'd', x: 900, y: 0 } };
    var chip = { id: 'mail', x: 300, y: 200 };
    var adapter = {
        ui: ui,
        view: function (next) { if (next) { state.view = next; } return state.view; },
        items: function () { return Object.keys(items).map(function (k) { return items[k]; }); },
        text: function (key, a, b, c) { return key + '|' + a + '|' + b + '|' + c; },
        item: function (id) { return items[id] || null; },
        label: function (item) { return 'Box ' + item.id.toUpperCase(); },
        editable: function () { return false; },
        select: function () {},
        targets: '[data-target]',
        target: function (element) { return element.getAttribute('data-target') === 'mail' ? chip : null; },
        groups: function () { return [{ id: 'g1', label: 'Shop', x: 0, y: -40, w: 900, kind: 'group' }, { id: 'side', label: 'Outside', x: 1200, y: -40, w: 160, kind: 'side' }]; },
        press: function () { return true; },
        move: function () {}, release: function () {}, cancel: function () {}, key: function () { return false; }
    };
    if (focusField) { adapter.focus = true; }
    var canvas = G.create(adapter);
    canvas.bind();
    var els = {};
    Object.keys(items).forEach(function (k) { els[k] = ui.boxes.appendChild(canvas.box(items[k], { into: true })); });
    els.mail = ui.boxes.appendChild(new El('div'));
    els.mail.setAttribute('data-target', 'mail');
    var edges = {};
    [['ab', 'a', 'b'], ['ca', 'c', 'a'], ['bc', 'b', 'c'], ['cd', 'c', 'd'], ['am', 'a', 'mail']].forEach(function (e) {
        edges[e[0]] = canvas.edge(e[0], items[e[1]], e[2] === 'mail' ? chip : items[e[2]]).group;
    });
    return { canvas: canvas, els: els, edges: edges, listeners: listeners };
}
function classesOf(map) { var o = {}; Object.keys(map).forEach(function (k) { o[k] = map[k].className; }); return o; }
function order() { return ui.svg.children.filter(function (g) { return g.attrs['data-edge']; }).map(function (g) { return g.attrs['data-edge']; }); }

var plain = setup(false);
plain.canvas.focus('a');
out.plain = [classesOf(plain.edges), plain.canvas.focused(), classesOf(plain.els)];

var f = setup(true);
out.none = [classesOf(f.edges), f.canvas.focused()];
f.canvas.focus('a');
out.a = [classesOf(f.edges), classesOf(f.els), f.canvas.focused(), f.canvas.focused() === 'a', ui.live.textContent];
var touched = [];
Object.keys(f.edges).forEach(function (k) { var g = f.edges[k]; var add = g.classList.add; g.classList.add = function (n) { touched.push(k); add(n); }; });
f.canvas.allEdges(true);
out.faint = [classesOf(f.edges), order(), touched.slice().sort()];
touched = [];
f.canvas.focus('c');
out.c = [classesOf(f.edges), order().slice(-3), classesOf(f.els), touched.slice().sort(), ui.live.textContent];
touched = [];
ui.live.textContent = 'kept';
f.canvas.focus('c');
out.same = [touched.slice(), ui.live.textContent];
var late = f.canvas.edge('db', { id: 'd', x: 900, y: 0 }, { id: 'b', x: 300, y: 0 });
out.late = [late.group.className, order().indexOf('db') < Math.min(order().indexOf('ca'), order().indexOf('bc'), order().indexOf('cd'))];
var newBox = f.canvas.box({ id: 'e', x: 0, y: 300 }, {});
out.newBox = newBox.className;
f.canvas.allEdges(false);
f.listeners.pointerdown({ type: 'pointerdown', pointerId: 7, clientX: 10, clientY: 10, button: 0, target: f.els.b, preventDefault: function () {} });
f.listeners.pointerup({ type: 'pointerup', pointerId: 7, clientX: 10, clientY: 10, button: 0, target: f.els.b, preventDefault: function () {} });
out.pressed = [f.canvas.focused(), ui.live.textContent];
f.listeners.pointerdown({ type: 'pointerdown', pointerId: 8, clientX: 10, clientY: 10, button: 0, target: ui.viewport, preventDefault: function () {} });
f.listeners.pointermove({ type: 'pointermove', pointerId: 8, clientX: 60, clientY: 10, button: 0, target: ui.viewport, preventDefault: function () {} });
f.listeners.pointerup({ type: 'pointerup', pointerId: 8, clientX: 60, clientY: 10, button: 0, target: ui.viewport, preventDefault: function () {} });
out.panned = f.canvas.focused();
f.listeners.pointerdown({ type: 'pointerdown', pointerId: 9, clientX: 10, clientY: 10, button: 0, target: ui.viewport, preventDefault: function () {} });
f.listeners.pointerup({ type: 'pointerup', pointerId: 9, clientX: 10, clientY: 10, button: 0, target: ui.viewport, preventDefault: function () {} });
out.cleared = [f.canvas.focused(), classesOf(f.edges), f.els.d.className];
f.canvas.groups();
var holder = f.canvas.groups();
out.groups = [ui.layer.children[0] === holder, ui.layer.children.filter(function (c) { return c.className === 'pgc-groups'; }).length, ui.layer.children[0].children.map(function (h) { return [h.className, h.attrs['data-group'], h.style.left, h.style.top, h.style.width, h.children[0].className, h.children[0].textContent, h.children[1].className]; }), ui.layer.children[0].attrs['aria-hidden']];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	$all = array('ab' => 'pgc-edge', 'ca' => 'pgc-edge', 'bc' => 'pgc-edge', 'cd' => 'pgc-edge', 'am' => 'pgc-edge');
	pg_assert_same(array($all, null, array('a' => 'pgc-node', 'b' => 'pgc-node', 'c' => 'pgc-node', 'd' => 'pgc-node', 'mail' => '')), $result['plain'], 'without the field every arrow is drawn as before and focus() does nothing');
	pg_assert_same(array(array('ab' => 'pgc-edge pgc-hidden', 'ca' => 'pgc-edge pgc-hidden', 'bc' => 'pgc-edge pgc-hidden', 'cd' => 'pgc-edge pgc-hidden', 'am' => 'pgc-edge pgc-hidden'), null), $result['none'], 'with the field and no focus no arrow is drawn');
	pg_assert_same(array(
		array('ab' => 'pgc-edge pgc-edge-out', 'ca' => 'pgc-edge pgc-edge-in', 'bc' => 'pgc-edge pgc-hidden', 'cd' => 'pgc-edge pgc-hidden', 'am' => 'pgc-edge pgc-edge-out'),
		array('a' => 'pgc-node', 'b' => 'pgc-node', 'c' => 'pgc-node', 'd' => 'pgc-node pgc-dim', 'mail' => ''),
		'a', true, 'pgc_focus|Box A|2|1',
	), $result['a'], 'a box in focus: its arrows out and in, the boxes and targets at neither end dimmed, announced with its counts');
	pg_assert_same(array(
		array('ab' => 'pgc-edge pgc-edge-out', 'ca' => 'pgc-edge pgc-edge-in', 'bc' => 'pgc-edge pgc-edge-faint', 'cd' => 'pgc-edge pgc-edge-faint', 'am' => 'pgc-edge pgc-edge-out'),
		array('bc', 'cd', 'ab', 'ca', 'am'),
		array('bc', 'cd'),
	), $result['faint'], 'allEdges(true): the others faint, under those of the box in focus; only they are touched');
	pg_assert_same(array(
		array('ab' => 'pgc-edge pgc-edge-faint', 'ca' => 'pgc-edge pgc-edge-out', 'bc' => 'pgc-edge pgc-edge-in', 'cd' => 'pgc-edge pgc-edge-out', 'am' => 'pgc-edge pgc-edge-faint'),
		array('ca', 'bc', 'cd'),
		array('a' => 'pgc-node', 'b' => 'pgc-node', 'c' => 'pgc-node', 'd' => 'pgc-node', 'mail' => 'pgc-dim'),
		array('ab', 'am', 'bc', 'ca', 'cd'),
		'pgc_focus|Box C|2|1',
	), $result['c'], 'another focus: every arrow whose part changed drawn again, the target at neither end dimmed');
	pg_assert_same(array(array(), 'kept'), $result['same'], 'the same focus again touches no arrow and announces nothing');
	pg_assert_same(array('pgc-edge pgc-edge-faint', true), $result['late'], 'an arrow drawn later takes its state, a faint one under the rest');
	pg_assert_same('pgc-node pgc-dim', $result['newBox'], 'a box drawn later at neither end is dimmed');
	pg_assert_same(array('b', 'pgc_focus|Box B|1|2'), $result['pressed'], 'a press on a box puts it in focus, counted with the arrow drawn later');
	pg_assert_same('b', $result['panned'], 'a pan keeps the focus');
	pg_assert_same(array(null, array('ab' => 'pgc-edge pgc-hidden', 'ca' => 'pgc-edge pgc-hidden', 'bc' => 'pgc-edge pgc-hidden', 'cd' => 'pgc-edge pgc-hidden', 'am' => 'pgc-edge pgc-hidden'), 'pgc-node'), $result['cleared'], 'a click on the empty canvas takes the focus away: no arrow, nothing dimmed');
	pg_assert_same(array(true, 1, array(
		array('pgc-group pgc-group-group', 'g1', '0px', '-40px', '900px', 'pgc-group-label', 'Shop', 'pgc-group-rule'),
		array('pgc-group pgc-group-side', 'side', '1200px', '-40px', '160px', 'pgc-group-label', 'Outside', 'pgc-group-rule'),
	), 'true'), $result['groups'], 'groups(): headings with a label and a rule behind the boxes, drawn again in place');
}

// The autosave (autosave()): a change is sent once the changes pause; a
// change made while a save is on its way goes in a second one; a failure is
// kept and nothing is sent again by itself; a conflict holds every later
// save; a keepalive save whose answer is lost is not sent twice as the page
// goes; a graph too large for keepalive is saved as usual while the page is
// hidden and left unsaved when it is left.
function test_graph_canvas_autosave()
{
	$result = graph_canvas_test_node(<<<'JS'
var timers = [];
window.setTimeout = function (handler) { timers.push(handler); return timers.length; };
window.clearTimeout = function () {};
function flushTimers() { var due = timers; timers = []; due.forEach(function (handler) { handler(); }); }
var record = { dirty: false, changes: 0, saving: null, saveError: '', conflict: '', leaveSent: -1, saveTimer: 0 };
var sends = [];
var answers = [];
var size = 10;
var saver = G.autosave({
    record: record,
    delay: 1500,
    keepaliveBytes: 100,
    ready: function () { return true; },
    send: function (options) {
        sends.push(record.changes + (options && options.keepalive ? ' keepalive' : ''));
        var answer = answers.shift() || 'ok';
        return answer === 'ok' ? Promise.resolve({}) : Promise.reject(answer);
    },
    size: function () { return size; },
    isConflict: function (error) { return error.field === 'changed_meanwhile'; },
    conflict: function (message) { record.conflict = message; },
    failed: function () { return 'Not saved'; },
    drawn: function () {}
});
var out = {};
function tick() { return new Promise(function (done) { setImmediate(done); }); }
(async function () {
    saver.changed(); saver.later();
    out.waits = [sends.length, timers.length, record.dirty];
    flushTimers(); await tick();
    out.sent = [sends.slice(), record.dirty, record.saving];
    sends = [];
    saver.changed();
    var first = saver.save();
    saver.changed();
    var second = saver.save();
    await first; await second; await tick();
    out.during = [sends.slice(), record.dirty];
    sends = [];
    timers = [];
    answers = [{ field: '', message: 'Server down' }];
    saver.changed();
    await saver.save(); await tick();
    out.failed = [record.saveError, record.dirty, timers.length];
    timers = [];
    answers = [{ field: 'changed_meanwhile', message: 'Newer' }];
    record.saveError = '';
    await saver.save(); await tick();
    out.conflict = [record.conflict, record.dirty];
    sends = [];
    await saver.save();
    saver.leave(true);
    out.held = sends.length;
    record.conflict = '';
    answers = [{ message: 'gone' }];
    saver.changed();
    saver.leave(true); await tick(); await tick();
    out.leave = [sends.slice(), record.leaveSent === record.changes, record.dirty];
    sends = [];
    saver.leave(true);
    out.once = sends.length;
    saver.changed();
    size = 1000;
    saver.leave(true);
    out.bigLeft = sends.length;
    saver.leave(false); await tick();
    out.bigHidden = sends.slice();
    console.log(JSON.stringify(out));
})();
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(0, 1, true), $result['waits'], 'a change waits for the pause');
	pg_assert_same(array(array('1'), false, null), $result['sent'], 'then it is sent once');
	pg_assert_same(array(array('2', '3'), false), $result['during'], 'a change made while a save runs goes in the next one');
	pg_assert_same(array('Server down', true, 0), $result['failed'], 'a failure is kept, nothing is sent again by itself');
	pg_assert_same(array('Newer', true), $result['conflict'], 'a newer graph on the server is a conflict');
	pg_assert_same(0, $result['held'], 'a conflict holds every save');
	pg_assert_same(array(array('5 keepalive'), true, true), $result['leave'], 'a keepalive save whose answer is lost is taken as sent');
	pg_assert_same(0, $result['once'], 'and not sent again as the page goes');
	pg_assert_same(0, $result['bigLeft'], 'too large for keepalive: left unsaved when the page is left');
	pg_assert_same(array('6'), $result['bigHidden'], 'saved as usual while the page is only hidden');
}

// A small stand-in DOM for create(): elements with classes, attributes,
// children, closest() and querySelectorAll() on simple selectors; the
// element under the pointer is the variable under.
function graph_canvas_test_dom()
{
	return <<<'JS'
var focused = null;
function El(tag) {
    var self = this;
    this.tag = tag; this.children = []; this.attrs = {}; this.style = {}; this.className = ''; this.parent = null; this.textContent = '';
    this.classList = {
        add: function (name) { var list = self.names(); if (list.indexOf(name) < 0) { list.push(name); } self.className = list.join(' '); },
        remove: function (name) { self.className = self.names().filter(function (item) { return item !== name; }).join(' '); },
        contains: function (name) { return self.names().indexOf(name) >= 0; }
    };
}
El.prototype.names = function () { return this.className.split(' ').filter(Boolean); };
El.prototype.setAttribute = function (name, value) { if (name === 'class') { this.className = String(value); } else { this.attrs[name] = String(value); } };
El.prototype.getAttribute = function (name) { return (name === 'class') ? this.className : ((name in this.attrs) ? this.attrs[name] : null); };
El.prototype.removeAttribute = function (name) { delete this.attrs[name]; };
El.prototype.removeChild = function (child) { this.children = this.children.filter(function (item) { return item !== child; }); child.parent = null; child.parentNode = null; return child; };
El.prototype.appendChild = function (child) { if (child.parentNode) { child.parentNode.removeChild(child); } child.parent = this; child.parentNode = this; this.children.push(child); return child; };
El.prototype.insertBefore = function (child, before) { if (child.parentNode) { child.parentNode.removeChild(child); } var at = before ? this.children.indexOf(before) : -1; child.parent = this; child.parentNode = this; if (at < 0) { this.children.push(child); } else { this.children.splice(at, 0, child); } return child; };
Object.defineProperty(El.prototype, 'firstChild', { get: function () { return this.children[0] || null; } });
El.prototype.focus = function () { focused = this; };
El.prototype.matches = function (selector) {
    var self = this;
    return selector.split(',').some(function (one) {
        var ok = true;
        one.replace(/\.([\w-]+)|\[([\w-]+)(?:="([^"]*)")?\]/g, function (all, name, attr, value) {
            if (name) { ok = ok && self.classList.contains(name); }
            if (attr) { ok = ok && (self.getAttribute(attr) !== null) && ((value === undefined) || (self.getAttribute(attr) === value)); }
            return all;
        });
        return ok;
    });
};
El.prototype.closest = function (selector) { for (var node = this; node; node = node.parent) { if (node.matches(selector)) { return node; } } return null; };
El.prototype.querySelectorAll = function (selector) { var out = []; (function walk(node) { node.children.forEach(function (child) { if (child.matches(selector)) { out.push(child); } walk(child); }); })(this); return out; };
El.prototype.querySelector = function (selector) { return this.querySelectorAll(selector)[0] || null; };
El.prototype.addEventListener = function () {};
var under = null;
var document = {
    createElement: function (tag) { return new El(tag); },
    createElementNS: function (ns, tag) { return new El(tag); },
    elementFromPoint: function () { return under; },
    addEventListener: function () {}
};
window.setTimeout = function (handler) { handler(); };
JS;
}

// The pure graph logic: order() ranks boxes breadth-first from a start, the
// given order between equals, the unreached last with no rank; a loop, a
// self-arrow and an arrow to no box change nothing; linksOf() gives a box's
// arrows out and in as given.
function test_graph_canvas_order_and_links()
{
	$result = graph_canvas_test_node(<<<'JS'
var out = {};
var ids = ['a', 'b', 'c', 'd', 'e', 'f'];
var edges = [{ from: 'a', to: 'c' }, { from: 'a', to: 'b' }, { from: 'b', to: 'd' }, { from: 'c', to: 'd' }, { from: 'd', to: 'a' }, { from: 'e', to: 'f' }, { from: 'x', to: 'a' }, { from: 'b', to: 'b' }];
out.empty = G.order([], [], 'a');
out.order = G.order(ids, edges, 'a');
out.again = JSON.stringify(G.order(ids, edges, 'a')) === JSON.stringify(out.order);
out.noStart = G.order(ids, edges, 'zz');
out.island = G.order(ids, edges, 'e');
out.links = G.linksOf(edges, 'a');
out.none = G.linksOf(edges, 'zz');
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('ids' => array(), 'rank' => array()), $result['empty'], 'an empty graph');
	pg_assert_same(array('a', 'b', 'c', 'd', 'e', 'f'), $result['order']['ids'], 'by rank, the given order between equals, the unreached last');
	pg_assert_same(array('a' => 0, 'b' => 1, 'c' => 1, 'd' => 2, 'e' => null, 'f' => null), $result['order']['rank'], 'breadth-first ranks through a loop, none for an island');
	pg_assert_true($result['again'], 'the same answer for the same input');
	pg_assert_same(array('a', 'b', 'c', 'd', 'e', 'f'), $result['noStart']['ids'], 'a start that is no box keeps the given order');
	pg_assert_same(array(null, null, null, null, null, null), array_values($result['noStart']['rank']), 'and ranks nothing');
	pg_assert_same(array('e', 'f', 'a', 'b', 'c', 'd'), $result['island']['ids'], 'from an island its own boxes come first');
	pg_assert_same(array(array('from' => 'a', 'to' => 'c'), array('from' => 'a', 'to' => 'b')), $result['links']['out'], 'arrows out in the given order');
	pg_assert_same(array(array('from' => 'd', 'to' => 'a'), array('from' => 'x', 'to' => 'a')), $result['links']['in'], 'arrows in in the given order');
	pg_assert_same(array('out' => array(), 'in' => array()), $result['none'], 'a box with no arrows');
}

// arrange(): groups as bands of a wrapped grid, each with its heading's
// place; a group longer than a row wraps; an empty group is left out, one
// without a label gets no heading; a side column before the grid and one
// after it with sub-groups; a row as high as its tallest box; whole units;
// forty boxes in three groups never overlap; the same answer each time.
function test_graph_canvas_arrange()
{
	$result = graph_canvas_test_node(<<<'JS'
var out = {};
function range(prefix, n) { var list = []; for (var i = 1; i <= n; i++) { list.push(prefix + i); } return list; }
out.empty = G.arrange([], {});
out.small = G.arrange([
    { id: 'g1', label: 'A', items: range('a', 10) },
    { id: 'g2', label: null, items: ['b1'] },
    { id: 'e', label: 'E', items: [] }
], {
    cols: 4, w: 100, h: 50, gapX: 20, gapY: 10, head: 30,
    before: { id: 'shared', label: 'Shared', w: 80, h: 40, items: ['s1', 's2'] },
    after: { id: 'out', label: 'Out', w: 120, h: 20, groups: [{ id: 'o1', label: 'Mail', items: ['m1'] }, { id: 'o2', label: 'Web', items: ['w1', 'w2'] }] }
});
out.tall = G.arrange([{ id: 'g', items: ['p', 'q', 'r'] }], { cols: 2, size: function (id) { return id === 'p' ? { w: 208, h: 100 } : null; } });
out.whole = G.arrange([{ id: 'g', items: ['p', 'q'] }], { x: 0.4, y: 0.6, w: 100.5, gapX: 20.3 });
var groups = [{ id: 'x', label: 'X', items: range('x', 20) }, { id: 'y', label: 'Y', items: range('y', 12) }, { id: 'z', label: 'Pages', items: range('z', 8) }];
var big = G.arrange(groups, { w: 248, h: 320 });
var boxes = Object.keys(big.places).map(function (id) { return big.places[id]; });
var overlap = false;
boxes.forEach(function (a, i) { boxes.forEach(function (b, j) { if (i < j && a.x < b.x + 248 && b.x < a.x + 248 && a.y < b.y + 320 && b.y < a.y + 320) { overlap = true; } }); });
out.big = [boxes.length, overlap, big.headings.length, big.bounds, big.places.x9, big.places.y1, big.places.z8];
out.again = JSON.stringify(G.arrange(groups, { w: 248, h: 320 })) === JSON.stringify(big);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('places' => array(), 'headings' => array(), 'bounds' => array('x' => 0, 'y' => 0, 'w' => 0, 'h' => 0)), $result['empty'], 'nothing to place');

	$small = $result['small'];
	$p = function ($x, $y) {
		return array('x' => $x, 'y' => $y);
	};
	pg_assert_same(array(
		's1' => $p(0, 30), 's2' => $p(0, 80),
		'a1' => $p(120, 30), 'a2' => $p(240, 30), 'a3' => $p(360, 30), 'a4' => $p(480, 30),
		'a5' => $p(120, 90), 'a6' => $p(240, 90), 'a7' => $p(360, 90), 'a8' => $p(480, 90),
		'a9' => $p(120, 150), 'a10' => $p(240, 150),
		'b1' => $p(120, 210),
		'm1' => $p(620, 60), 'w1' => $p(620, 120), 'w2' => $p(620, 150),
	), $small['places'], 'the side columns and the bands of the wrapped grid');
	pg_assert_same(array(
		array('id' => 'shared', 'label' => 'Shared', 'x' => 0, 'y' => 0, 'w' => 80, 'kind' => 'side'),
		array('id' => 'g1', 'label' => 'A', 'x' => 120, 'y' => 0, 'w' => 460, 'kind' => 'group'),
		array('id' => 'out', 'label' => 'Out', 'x' => 620, 'y' => 0, 'w' => 120, 'kind' => 'side'),
		array('id' => 'o1', 'label' => 'Mail', 'x' => 620, 'y' => 30, 'w' => 120, 'kind' => 'sub'),
		array('id' => 'o2', 'label' => 'Web', 'x' => 620, 'y' => 90, 'w' => 120, 'kind' => 'sub'),
	), $small['headings'], 'a heading for each labelled group and column, none for the empty group');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 740, 'h' => 260), $small['bounds'], 'the bounds hold everything');

	pg_assert_same(array('p' => $p(0, 0), 'q' => $p(256, 0), 'r' => $p(0, 148)), $result['tall']['places'], 'a row as high as its tallest box');
	pg_assert_same(array('p' => $p(0, 1), 'q' => $p(121, 1)), $result['whole']['places'], 'whole units');

	pg_assert_same(40, $result['big'][0], 'forty boxes placed');
	pg_assert_false($result['big'][1], 'none overlaps another');
	pg_assert_same(3, $result['big'][2], 'three headings');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 2320, 'h' => 2280), $result['big'][3], 'eight a row, three bands');
	pg_assert_same(array($p(0, 408), $p(0, 1184), $p(2072, 1960)), array_slice($result['big'], 4, 3), 'a group longer than a row wraps; the next band starts below it');
	pg_assert_true($result['again'], 'the same answer for the same input');
}

// check(): arrows whose end is no box, boxes no other box reaches (never
// the start, a self-arrow not counted), boxes with no way out; an empty
// graph has none.
function test_graph_canvas_check()
{
	$result = graph_canvas_test_node(<<<'JS'
var out = {};
out.empty = G.check([], [], null);
out.check = G.check(['a', 'b', 'c', 'd'], [{ from: 'a', to: 'b' }, { from: 'b', to: 'a' }, { from: 'c', to: 'c' }, { from: 'd', to: 'zz' }, { from: 'yy', to: 'd' }], 'a');
out.alone = G.check(['a'], [], 'a');
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('dangling' => array(), 'unreached' => array(), 'stuck' => array()), $result['empty'], 'an empty graph has no problem');
	pg_assert_same(array(array('from' => 'd', 'to' => 'zz'), array('from' => 'yy', 'to' => 'd')), $result['check']['dangling'], 'arrows whose end is no box');
	pg_assert_same(array('c', 'd'), $result['check']['unreached'], 'boxes no other box reaches');
	pg_assert_same(array('c'), $result['check']['stuck'], 'a box with no way out but itself');
	pg_assert_same(array('dangling' => array(), 'unreached' => array(), 'stuck' => array('a')), $result['alone'], 'the start is never unreached');
}
