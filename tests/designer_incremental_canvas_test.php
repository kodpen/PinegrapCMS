<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the incremental canvas of the visual designer: a text, property
 * or style edit draws only the edited node's element again, in place, and
 * every other change (or an edit the node cannot be redrawn alone for) keeps
 * the full rebuild. The functions are cut out of
 * assets/js/style_designer.src.js and run under Node with the canvas on a
 * small DOM stand-in; on a machine without Node those tests are skipped.
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

// Runs $script after the incremental-canvas functions and returns the JSON it
// prints; null without Node. The canvas helpers they call are stubs that log
// their name into `calls`; buildNodeEl() returns a stand-in element named
// after the node. $script calls out(value) once.
function designer_incremental_canvas_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = _test_blocks_js();
	$from = strpos($source, '    // ── Incremental canvas');
	$to = strpos($source, '    // ── end of incremental canvas', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the incremental-canvas functions');

	$stubs = array('_syncToggleableShowStateFromDom', '_captureTabState', '_captureDetailsState', 'injectGoogleFonts',
		'_reExecuteCanvasScripts', '_sdThemeSwitchers', '_sdScrollers', '_sdFxCanvas', '_initCanvasTooltips',
		'_restoreTabState', '_restoreDetailsState', '_sdBootModuleWidgets', '_sdApplyImageStamps', '_sdTextInnerMark',
		'adjustCanvasToolbars', 'updateIconResizeHandle');
	$js = 'var calls = [];';
	foreach ($stubs as $name) {
		$js .= 'function ' . $name . '() { calls.push("' . $name . '"); }';
	}

	$js .= 'function requestAnimationFrame(fn) { fn(); }'
		. 'var _sdSharedOpen = { 5: true };'
		. 'var _shared = {};'
		. 'function _resolveSharedSid(n) { return _shared[n._id] || 0; }'
		. 'function _sdPartOf(n) { return !!(n.props && n.props.partOf); }'
		. 'function _sdFindNodeForEl() { return null; }'
		. 'var opened = null;'
		. 'function _sdCanvasOpenCapture(el) { return { from: el.name }; }'
		. 'function _sdCanvasOpenRestore(el, open) { opened = [el.name, open.from]; }'
		. 'function El(name) { this.name = name; this.parentNode = null; }'
		. 'function Parent(kids) { var self = this; this.kids = kids; kids.forEach(function (k) { k.parentNode = self; }); }'
		. 'Parent.prototype.replaceChild = function (n, o) { var i = this.kids.indexOf(o); this.kids[i] = n; n.parentNode = this; o.parentNode = null; };'
		. 'var built = [];'
		. 'function buildNodeEl(n) { built.push(n._id); return new El("new:" + n._id); }'
		. 'var wrapsFound = {};'
		. 'var canvasDoc = { querySelectorAll: function (sel) { var m = /data-sd-id="([^"]+)"/.exec(sel); return (m && wrapsFound[m[1]]) || []; } };'
		. 'var _id = 0; function N(type, props, kids) { return { _id: "n" + (++_id), type: type, props: props || {}, children: kids || [] }; }'
		. 'function out(v) { console.log(JSON.stringify(v)); }'
		. substr($source, $from, $to - $from)
		. $script;

	$file = tempnam(sys_get_temp_dir(), 'pgincr');
	file_put_contents($file, $js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A page node on the page the canvas shows is redrawn alone; the root, a node
// of another tree, a page left behind, a node in a shared component, a shared
// reference and a placed part (or a node inside one) are not.
function test_designer_incremental_canvas_scope_rules()
{
	$answer = designer_incremental_canvas_run(
		'var text = N("content", { contentType: "text", text: "Hi" });'
		. 'var inPart = N("content", { contentType: "text" });'
		. 'var part = N("semantic", { tag: "div", partOf: { sid: 3 } }, [inPart]);'
		. 'var ref = N("shared_ref", { sharedId: 4 });'
		. 'var inShared = N("content", { contentType: "text" });'
		. 'var root = N("root", {}, [N("section", {}, [text, part, ref])]);'
		. '_shared[inShared._id] = 4;'
		. 'var page = {}, other = {};'
		. 'out({'
		. ' text: _sdCanvasScopeOk(text, root, page, page),'
		. ' root: _sdCanvasScopeOk(root, root, page, page),'
		. ' otherTree: _sdCanvasScopeOk(N("content", {}), root, page, page),'
		. ' pageLeft: _sdCanvasScopeOk(text, root, other, page),'
		. ' notDrawn: _sdCanvasScopeOk(text, root, undefined, page),'
		. ' inShared: _sdCanvasScopeOk(inShared, root, page, page),'
		. ' ref: _sdCanvasScopeOk(ref, root, page, page),'
		. ' part: _sdCanvasScopeOk(part, root, page, page),'
		. ' inPart: _sdCanvasScopeOk(inPart, root, page, page),'
		. ' none: _sdCanvasScopeOk(null, root, page, page)'
		. '});'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'text' => true, 'root' => false, 'otherTree' => false, 'pageLeft' => false, 'notDrawn' => false,
		'inShared' => false, 'ref' => false, 'part' => false, 'inPart' => false, 'none' => false,
	), $answer, 'only a page node outside components and parts, on the page drawn, is redrawn alone');
}

// The scope lasts for the one call: renderCanvas() reads it once, it is gone
// after the call (also when the call throws), and a later render is never
// limited by it.
function test_designer_incremental_canvas_scope_is_one_call()
{
	$answer = designer_incremental_canvas_run(
		'var a = N("content", {});'
		. 'var seen = [];'
		. '_sdRenderNode(a, function () { seen.push(_sdCanvasScopeTake() === a); seen.push(_sdCanvasScopeTake()); });'
		. 'var after = _sdCanvasScope;'
		. 'try { _sdRenderNode(a, function () { throw new Error("x"); }); } catch (e) {}'
		. 'var afterThrow = _sdCanvasScope;'
		. '_sdRenderNode(null, function () { seen.push(_sdCanvasScopeTake()); });'
		. 'out({ seen: seen, after: after, afterThrow: afterThrow });'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('seen' => array(true, null, null), 'after' => null, 'afterThrow' => null), $answer,
		'the scope is read once and cleared after the call, thrown or not');
}

// The edited node's wrapper is replaced by buildNodeEl() of its subtree: its
// siblings keep their elements, a toggleable open inside it is carried over,
// the shared-open marks start clean, the canvas passes run again. With no
// wrapper, or two, nothing changes and the caller falls back to the rebuild.
function test_designer_incremental_canvas_redraws_in_place()
{
	$answer = designer_incremental_canvas_run(
		'var a = N("content", {}), b = N("content", {}), c = N("content", {});'
		. 'var ea = new El("a"), eb = new El("b"), ec = new El("c");'
		. 'var parent = new Parent([ea, eb, ec]);'
		. 'wrapsFound[b._id] = [eb];'
		. 'var ok = _sdCanvasRedrawNode(b);'
		. 'var names = parent.kids.map(function (k) { return k.name; });'
		. 'var kept = parent.kids[0] === ea && parent.kids[2] === ec;'
		. 'var shared = Object.keys(_sdSharedOpen).length;'
		. 'var callsOk = calls.slice();'
		. 'calls = []; built = [];'
		. 'var none = _sdCanvasRedrawNode(a);'
		. 'wrapsFound[c._id] = [ec, new El("c2")];'
		. 'var two = _sdCanvasRedrawNode(c);'
		. 'out({ ok: ok, names: names, kept: kept, opened: opened, shared: shared, none: none, two: two, builtAfter: built, callsAfter: calls,'
		. ' first: callsOk.slice(0, 4), passes: callsOk.slice(4) });'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(true, $answer['ok'], 'a single wrapper is redrawn');
	pg_assert_same(array('a', 'new:' . 'n2', 'c'), $answer['names'], 'the wrapper is replaced in place');
	pg_assert_same(true, $answer['kept'], 'the siblings keep their elements');
	pg_assert_same(array('new:n2', 'b'), $answer['opened'], 'what was open in the old element opens in the new one');
	pg_assert_same(0, $answer['shared'], 'the shared-open marks start clean');
	pg_assert_same(array('_syncToggleableShowStateFromDom', '_captureTabState', '_captureDetailsState', 'injectGoogleFonts'),
		$answer['first'], 'the open collapses, tabs and details are read before the swap');
	pg_assert_same(array('_reExecuteCanvasScripts', '_sdThemeSwitchers', '_sdScrollers', '_sdFxCanvas', '_initCanvasTooltips',
		'_restoreTabState', '_restoreDetailsState', '_sdBootModuleWidgets', '_sdApplyImageStamps', '_sdTextInnerMark',
		'adjustCanvasToolbars', 'updateIconResizeHandle'), $answer['passes'], 'the canvas passes run after the swap');
	pg_assert_same(false, $answer['none'], 'no wrapper: no redraw');
	pg_assert_same(false, $answer['two'], 'two wrappers: no redraw');
	pg_assert_same(array(), $answer['builtAfter'], 'nothing is built when the node cannot be redrawn alone');
	pg_assert_same(array(), $answer['callsAfter'], 'and the canvas is left alone');
}

// Props another node or the structure reads redraw the whole canvas.
function test_designer_incremental_canvas_full_props()
{
	$answer = designer_incremental_canvas_run(
		'out(["id", "regionType", "tag", "_locked", "text", "cssClass", "inlineStyle", "src"].map(_sdScopeFullProp));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, true, true, true, false, false, false, false), $answer, 'structure props rebuild, the others redraw alone');
}

// renderCanvas() asks for the scope before it empties the body, and only on
// the canvas document it drew, with nothing pending from a fresh document;
// the edit paths of the Properties, Styles and Attributes panels name their
// node, while undo keeps the full rebuild.
function test_designer_incremental_canvas_wiring()
{
	$js = _test_blocks_js();
	$body = _test_blocks_slice('    function renderCanvas() {', "\n    }\n");
	pg_assert_true($body !== '', 'renderCanvas() is found');
	$take = strpos($body, '_sdCanvasScopeTake()');
	$wipe = strpos($body, "canvasDoc.body.innerHTML = '';");
	pg_assert_true(($take !== false) && ($wipe !== false) && ($take < $wipe), 'the scope is read before the body is emptied');
	pg_assert_true(strpos($body, 'canvasDoc._sdDrawn && !_sdPendingScrollY && !_sdPendingOpen') !== false,
		'only a drawn canvas with nothing pending is redrawn in part');

	pg_assert_true(strpos($js, '_sdRenderNode(_scopeNode, render);') !== false, 'a Properties select or switch redraws its node');
	pg_assert_true(strpos($js, '_sdRenderNode(_scopeNode, renderCanvas);') !== false, 'a Properties text field redraws its node');
	pg_assert_same(3, substr_count($js, "syncInlineStyle(n);\n                _sdRenderNode(n, renderCanvas);"), 'the Styles rows redraw their node');

	$undo = _test_blocks_slice('    function undo() {', "\n    }\n");
	pg_assert_true(($undo !== '') && (strpos($undo, '_sdRenderNode') === false), 'undo keeps the full rebuild');
}
