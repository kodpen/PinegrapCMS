<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's panels on demand: a hidden panel (Layers tree,
 * HTML tree, Attributes, Styles) is not drawn but marked and drawn when it
 * shows; typing in a field draws the other panels once the typing pauses;
 * an edit of one node redraws only that node's rows in the Layers tree. The
 * functions are cut out of assets/js/style_designer.src.js and run under
 * Node with small DOM stand-ins; on a machine without Node those tests are
 * skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/designer_blocks_test.php';

// Runs $script after the panels-on-demand functions and returns the JSON it
// prints; null without Node. `hosts` maps a host id to a stand-in element
// (Host(visible, folded)); the panels' drawing functions log their name into
// `painted`; timers run when `tick()` is called.
function designer_panels_on_demand_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = _test_blocks_js();
	$from = strpos($source, '    // ── Panels on demand');
	$to = strpos($source, '    // ── end of panels on demand', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the panels-on-demand functions');

	$js = 'var painted = [];'
		. 'function renderTree() { painted.push("tree"); }'
		. 'function renderHtmlTree() { painted.push("html"); }'
		. 'function renderAttrsPanel() { painted.push("attrs"); }'
		. 'function renderStylesPanel() { painted.push("styles"); }'
		. 'function renderStatusBar() { painted.push("status"); }'
		. 'function _sdLivePreviewSchedule() { painted.push("preview"); }'
		. 'var observed = 0;'
		. 'function ResizeObserver(fn) { this.fn = fn; } ResizeObserver.prototype.observe = function () { observed++; };'
		. 'var timers = [], timerId = 0;'
		. 'function setTimeout(fn) { timers.push({ id: ++timerId, fn: fn }); return timerId; }'
		. 'function clearTimeout(id) { timers = timers.filter(function (t) { return t.id !== id; }); }'
		. 'function tick() { var t = timers; timers = []; t.forEach(function (x) { x.fn(); }); }'
		. 'function Host(visible, folded) { this.visible = visible; this.folded = folded; }'
		. 'Host.prototype.getClientRects = function () { return this.visible ? [1] : []; };'
		. 'Host.prototype.closest = function () { return this.folded ? {} : null; };'
		. 'var hosts = {};'
		. 'var document = { getElementById: function (id) { return hosts[id] || null; }, createElement: function () { return new Ul(); } };'
		. 'function Row(name, depth, cls) { this.name = name; this.depth = depth; this.cls = cls || ""; this.parentNode = null;'
		. ' var self = this; this.style = { getPropertyValue: function () { return String(self.depth); } };'
		. ' this.classList = { contains: function (c) { return self.cls === c; } }; }'
		. 'function Ul() { this.kids = []; }'
		. 'Object.defineProperty(Ul.prototype, "firstChild", { get: function () { return this.kids[0] || null; } });'
		. 'Ul.prototype.appendChild = function (k) { k.parentNode = this; this.kids.push(k); };'
		. 'Ul.prototype.insertBefore = function (k, ref) { var from = k.parentNode; if (from) from.kids.splice(from.kids.indexOf(k), 1); k.parentNode = this; this.kids.splice(this.kids.indexOf(ref), 0, k); };'
		. 'Ul.prototype.removeChild = function (k) { this.kids.splice(this.kids.indexOf(k), 1); k.parentNode = null; };'
		. 'Object.defineProperty(Row.prototype, "nextElementSibling", { get: function () { var k = this.parentNode.kids; return k[k.indexOf(this) + 1] || null; } });'
		. 'var built = [];'
		. 'function buildTreeItems(ul, node, depth) { built.push([node._id, depth]); ul.appendChild(new Row("new:" + node._id, depth)); ul.appendChild(new Row("newkids:" + node._id, depth, "sd-tree-children")); }'
		. 'var rows = {};'
		. 'function _sdTreeRowOf(list, node) { return rows[node._id] || null; }'
		. 'var _shared = {};'
		. 'function _resolveSharedSid(n) { return _shared[n._id] || 0; }'
		. 'var _sdSharedOpen = { 7: true };'
		. 'var _sdEditEpoch = 5;'
		. 'var tree = { _id: "root", children: [] };'
		. 'function out(v) { console.log(JSON.stringify(v)); }'
		. substr($source, $from, $to - $from)
		. $script;

	$file = tempnam(sys_get_temp_dir(), 'pgpanels');
	file_put_contents($file, $js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A panel that can be seen draws; one that is not laid out, or sits in the
// folded bottom panel, is marked and its host observed once; drawn again
// when shown, it is no longer marked. No host: the panel decides itself.
function test_designer_panels_on_demand_defer()
{
	$answer = designer_panels_on_demand_run(
		'hosts["sd-overview-tree-wrap"] = new Host(true, false);'
		. 'hosts["sd-bp-attrs"] = new Host(true, true);'
		. 'hosts["sd-html-tree"] = new Host(false, false);'
		. 'var r = { tree: _sdPanelDefer("tree"), attrs: _sdPanelDefer("attrs"), html: _sdPanelDefer("html"), styles: _sdPanelDefer("styles") };'
		. '_sdPanelDefer("html");'
		. 'var stale = Object.keys(_sdPanelStale).sort();'
		. 'hosts["sd-html-tree"].visible = true;'
		. 'var again = _sdPanelDefer("html");'
		. 'out({ r: r, stale: stale, observed: observed, again: again, staleAfter: Object.keys(_sdPanelStale).sort() });'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('tree' => false, 'attrs' => true, 'html' => true, 'styles' => false), $answer['r'], 'visible draws; folded or not laid out waits; no host draws');
	pg_assert_same(array('attrs', 'html'), $answer['stale'], 'the hidden panels are marked');
	pg_assert_same(2, $answer['observed'], 'each hidden host is observed once');
	pg_assert_same(false, $answer['again'], 'shown again, the panel draws');
	pg_assert_same(array('attrs'), $answer['staleAfter'], 'and is no longer marked');
}

// The flush draws the marked panels that can be seen now, and only those.
function test_designer_panels_on_demand_flush()
{
	$answer = designer_panels_on_demand_run(
		'hosts["sd-overview-tree-wrap"] = new Host(true, false);'
		. 'hosts["sd-bp-attrs"] = new Host(true, true);'
		. 'hosts["sd-bp-styles"] = new Host(true, false);'
		. '_sdPanelStale = { tree: true, attrs: true };'
		. '_sdPanelsFlush();'
		. 'out(painted);'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('tree'), $answer, 'only a marked panel that can be seen is drawn');
}

// Keys typed into one node draw the panels once, after the pause, with that
// node's rows; keys into two nodes draw the whole tree; render() cancels.
function test_designer_panels_on_demand_typing()
{
	$answer = designer_panels_on_demand_run(
		'var redrawn = [];'
		. '_sdTreeRowsRedraw = function (n) { redrawn.push(n._id); return true; };'
		. 'var a = { _id: "a" }, b = { _id: "b" };'
		. '_sdPanelsSoon(a); _sdPanelsSoon(a); _sdPanelsSoon(a);'
		. 'var before = painted.slice(), pending = timers.length;'
		. 'tick();'
		. 'var one = painted.slice(); painted = [];'
		. '_sdPanelsSoon(a); _sdPanelsSoon(b); tick();'
		. 'var mixed = painted.slice(); painted = [];'
		. '_sdPanelsSoon(a); _sdPanelsCancel(); tick();'
		. 'out({ before: before, pending: pending, one: one, redrawn: redrawn, mixed: mixed, cancelled: painted });'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(), $answer['before'], 'nothing is drawn while typing');
	pg_assert_same(1, $answer['pending'], 'one timer for the run of keys');
	pg_assert_same(array('status', 'attrs', 'html', 'preview'), $answer['one'], 'the panels after the pause, the tree by its rows');
	pg_assert_same(array('a'), $answer['redrawn'], 'the typed node\'s rows');
	pg_assert_same(array('tree', 'status', 'attrs', 'html', 'preview'), $answer['mixed'], 'two nodes: the whole tree');
	pg_assert_same(array(), $answer['cancelled'], 'a cancelled run draws nothing');
}

// The node's row and the list of its children are replaced in place, the
// rows around them stay; the epoch is taken; a shared node, the root, a
// stale or other tree and a missing row fall back to the whole tree.
function test_designer_panels_on_demand_tree_rows()
{
	$answer = designer_panels_on_demand_run(
		'hosts["sd-overview-tree-wrap"] = new Host(true, false);'
		. 'var list = new Ul(); list._sdTreeOf = tree; list._sdTreeEpoch = 1; hosts["sd-tree-list"] = list;'
		. 'var x = new Row("x", 1), n = new Row("n", 2), nk = new Row("nkids", 2, "sd-tree-children"), y = new Row("y", 2);'
		. '[x, n, nk, y].forEach(function (r) { list.appendChild(r); });'
		. 'var node = { _id: "n" }; rows.n = n;'
		. 'var ok = _sdTreeRowsRedraw(node);'
		. 'var names = list.kids.map(function (r) { return r.name; });'
		. 'var res = { ok: ok, names: names, built: built, epoch: list._sdTreeEpoch, shared: Object.keys(_sdSharedOpen).length };'
		. 'var s = { _id: "s" }; rows.s = new Row("s", 1); list.appendChild(rows.s); _shared.s = 3;'
		. 'res.sharedNode = _sdTreeRowsRedraw(s);'
		. 'res.root = _sdTreeRowsRedraw(tree);'
		. 'res.noRow = _sdTreeRowsRedraw({ _id: "zz" });'
		. '_sdPanelStale.tree = true; res.stale = _sdTreeRowsRedraw(node); delete _sdPanelStale.tree;'
		. 'list._sdTreeOf = {}; res.otherTree = _sdTreeRowsRedraw(node); list._sdTreeOf = tree;'
		. 'hosts["sd-overview-tree-wrap"].visible = false; built = []; res.hidden = _sdTreeRowsRedraw(node); res.hiddenBuilt = built; res.hiddenStale = !!_sdPanelStale.tree;'
		. 'out(res);'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(true, $answer['ok'], 'the rows are redrawn');
	pg_assert_same(array('x', 'new:n', 'newkids:n', 'y'), $answer['names'], 'the row and its children list are replaced in place');
	pg_assert_same(array(array('n', 2)), $answer['built'], 'built once at the row\'s depth');
	pg_assert_same(5, $answer['epoch'], 'the list matches the tree as it is now');
	pg_assert_same(0, $answer['shared'], 'the shared-open marks start clean');
	pg_assert_same(false, $answer['sharedNode'], 'a shared component node draws the whole tree');
	pg_assert_same(false, $answer['root'], 'the root draws the whole tree');
	pg_assert_same(false, $answer['noRow'], 'no row: the whole tree');
	pg_assert_same(false, $answer['stale'], 'a stale tree is drawn whole');
	pg_assert_same(false, $answer['otherTree'], 'a tree drawn from another page is drawn whole');
	pg_assert_same(true, $answer['hidden'], 'a hidden tree is not drawn');
	pg_assert_same(array(), $answer['hiddenBuilt'], 'nothing is built while hidden');
	pg_assert_same(true, $answer['hiddenStale'], 'and it is marked for when it shows');
}

// The drawing functions ask first; render() keeps the scope for the tree;
// the Properties text field and the folds use the new paths.
function test_designer_panels_on_demand_wiring()
{
	$js = _test_blocks_js();
	$render = _test_blocks_slice('    function render() {', "\n    }\n");
	$scope = strpos($render, 'var _scope = _sdCanvasScope;');
	$canvas = strpos($render, 'renderCanvas();');
	pg_assert_true(($scope !== false) && ($canvas !== false) && ($scope < $canvas), 'render() reads the scope before renderCanvas() takes it');
	pg_assert_true(strpos($render, '_sdTreeRefresh(_scope);') !== false, 'render() redraws the tree by the scope');
	pg_assert_true(strpos($render, '_sdPanelsCancel();') !== false, 'render() cancels the typing run');

	pg_assert_true(strpos(_test_blocks_slice('    function renderTree() {', "\n    }\n"), "_sdPanelDefer('tree')") !== false, 'renderTree() asks first');
	pg_assert_true(strpos(_test_blocks_slice('    function renderHtmlTree() {', "\n    }\n"), "_sdPanelDefer('html')") !== false, 'renderHtmlTree() asks first');
	pg_assert_true(strpos(_test_blocks_slice('    function renderAttrsPanel() {', "\n    }\n"), "_sdPanelDefer('attrs')") !== false, 'renderAttrsPanel() asks first');
	pg_assert_same(2, substr_count($js, "_sdPanelDefer('styles')"), 'both Styles panel functions ask first');

	pg_assert_true(strpos($js, "_sdRenderNode(_scopeNode, renderCanvas);\n                    // The other panels once the typing pauses; render()\n                    // does not run on a keystroke here.\n                    _sdPanelsSoon(_scopeNode);") !== false,
		'a Properties text field draws the canvas now, the panels after the pause');
	pg_assert_true(strpos($js, "if (!isNowCollapsed) _sdPanelsFlush();") !== false, 'unfolding the bottom panel draws what it missed');
	pg_assert_true(strpos($js, "if (sub === 'tree') _sdPanelsFlush();") !== false, 'the Tree tab draws what it missed');
}
