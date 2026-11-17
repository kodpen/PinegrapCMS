<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the work the designer does on one option change: the
 * Attributes panel looks its class chips up in an index of the Options
 * panel read once (_sdClassControlIndex(), _sdClassControlFind()), render()
 * puts the Layers rows, status bar, Attributes panel and HTML tree off when
 * only one node's element was drawn again, and the issue badges are put on
 * that element alone. The lookup is cut out of
 * assets/js/style_designer.src.js and run under Node with DOM stand-ins; on
 * a machine without Node that test is skipped.
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

// Runs $script after the class control lookup and returns the JSON it
// prints; null without Node. `Panel(els)` is a stand-in Options panel whose
// elements are { tag, type, attrs }; `queries` counts its querySelectorAll
// and querySelector calls.
function designer_class_control_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = _test_blocks_js();
	$from = strpos($source, '    // ── Class control lookup');
	$to = strpos($source, '    // ── end of class control lookup', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the class control lookup');

	if (($from === false) || ($to === false)) {
		return null;
	}

	$js = 'var queries = 0;'
		. 'function El(tag, attrs, type) { this.tag = tag; this.attrs = attrs; this.type = type || ""; }'
		. 'El.prototype.getAttribute = function (a) { return Object.prototype.hasOwnProperty.call(this.attrs, a) ? this.attrs[a] : null; };'
		. 'function matches(el, sel) {'
		. ' var m = /^(\\w*)((?:\\[[^\\]]+\\])*)$/.exec(sel); if (!m) throw new Error("selector " + sel);'
		. ' if (m[1] && m[1] !== el.tag) return false;'
		. ' var parts = m[2].match(/\\[[^\\]]+\\]/g) || [];'
		. ' return parts.every(function (p) { var q = /^\\[([\\w-]+)(?:="([^"]*)")?\\]$/.exec(p);'
		. '  if (q[1] === "type") return el.type === q[2];'
		. '  var v = el.getAttribute(q[1]); return v !== null && (q[2] === undefined || v === q[2]); }); }'
		. 'function Panel(els) { this.els = els; }'
		. 'Panel.prototype.querySelectorAll = function (sel) { queries++; return this.els.filter(function (e) { return matches(e, sel); }); };'
		. 'Panel.prototype.querySelector = function (sel) { queries++; return this.els.filter(function (e) { return matches(e, sel); })[0] || null; };'
		. 'function findBuiltinClassBinding(cls, node) { return (node && node.type === "container" && cls === "container-fluid") ? { selector: "select[data-prop=\\"fluid\\"]" } : null; }'
		. 'function out(v) { console.log(JSON.stringify(v)); }'
		. 'function name(el) { return el ? el.attrs.name : null; }'
		. substr($source, $from, $to - $from)
		. $script;

	$file = tempnam(sys_get_temp_dir(), 'pgclsctl');
	file_put_contents($file, $js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// Every class finds the control the panel was searched for in its order:
// toggle button, toggle checkbox, utility prefix, style group, box model
// prefix, built-in binding; the first element in document order wins; the
// panel is read once however many classes are looked up.
function test_designer_class_control_lookup()
{
	$answer = designer_class_control_run(
		'var p = new Panel(['
		. ' new El("button", { "data-util-toggle": "border", name: "btn1" }),'
		. ' new El("button", { "data-util-toggle": "border", name: "btn2" }),'
		. ' new El("input", { "data-util-toggle": "text-truncate", name: "cb" }, "checkbox"),'
		. ' new El("input", { "data-util-toggle": "shadow", name: "textbox" }, "text"),'
		. ' new El("select", { "data-util": "overflow-", name: "ovf" }),'
		. ' new El("select", { "data-util": "", name: "empty" }),'
		. ' new El("select", { "data-stylegroup": "fw-bold,fw-light", name: "fw" }),'
		. ' new El("div", { "data-bm": "m-", name: "margin" }),'
		. ' new El("select", { "data-prop": "fluid", name: "fluid" })'
		. ']);'
		. 'var idx = _sdClassControlIndex(p, { type: "container" });'
		. 'var read = queries;'
		. 'var r = {};'
		. '["border", "text-truncate", "shadow", "overflow-hidden", "fw-light", "m-3", "container-fluid", "nothing-here", ""].forEach(function (c) { r[c] = name(_sdClassControlFind(idx, c)); });'
		. 'out({ r: r, read: read, after: queries, noPanel: _sdClassControlFind(_sdClassControlIndex(null, null), "border") });'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'border' => 'btn1',
		'text-truncate' => 'cb',
		'shadow' => null,
		'overflow-hidden' => 'ovf',
		'fw-light' => 'fw',
		'm-3' => 'margin',
		'container-fluid' => 'fluid',
		'nothing-here' => null,
		'' => null,
	), $answer['r'], 'each class finds the control the panel order gives it');
	pg_assert_same(5, $answer['read'], 'the panel is read once into the index');
	pg_assert_same(6, $answer['after'], 'a lookup queries the panel only for a built-in binding');
	pg_assert_same(null, $answer['noPanel'], 'no panel, no control');
}

// The Attributes panel builds the index once per draw; render() puts the
// other panels off after an in-place redraw and badges that element alone.
function test_designer_option_change_wiring()
{
	$attrs = _test_blocks_slice('    function renderAttrsPanel() {', "\n    }\n");
	pg_assert_same(1, substr_count($attrs, '_sdClassControlIndex('), 'the Attributes panel reads the Options panel once');
	pg_assert_same(0, substr_count($attrs, 'findControlForClass('), 'and never per chip');

	$render = _test_blocks_slice('    function render() {', "\n    }\n");
	pg_assert_true(strpos($render, "var _redrawn = _scope ? _sdCanvasRedrawnEl : null;") !== false, 'render() knows when the canvas kept its DOM');
	pg_assert_true(strpos($render, "if (_redrawn) {") !== false && strpos($render, '_sdPanelsSoon(_scope);') !== false, 'an in-place redraw puts the other panels off');
	pg_assert_true(strpos($render, 'applyIssueBadges(_redrawn);') !== false, 'and badges only the element drawn again');

	$redraw = _test_blocks_slice('    function _sdCanvasRedrawNode(node) {', "\n    }\n");
	pg_assert_true(strpos($redraw, '_sdCanvasRedrawnEl = el;') !== false, 'the in-place redraw names its element');
}
