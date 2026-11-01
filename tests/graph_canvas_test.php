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
	pg_assert_same(8, count($names), 'eight class names');

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

	preg_match_all("/lang\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $source, $texts);

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
	$result = graph_canvas_test_node(<<<'JS'
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
El.prototype.appendChild = function (child) { child.parent = this; this.children.push(child); return child; };
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
