<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the open state of a collapse (an accordion item) in the visual
 * designer. The canvas copies a panel opened by a click into the tree, so the
 * save drops `show` from toggleable elements; a collapse keeps the state its
 * toggle is written with instead (aria-expanded="true" and no `collapsed`
 * means open), modal, offcanvas and dropdown-menu always close. The Expanded
 * and Collapsed switches write the panel and its toggles together, and the
 * accordions the palette builds (the Components tab's accordion, the FAQ
 * blocks) have working toggles whose state agrees with their panels and
 * survives a save. The functions are cut out of assets/js/style_designer.js
 * and run under Node; on a machine without Node the test is skipped. The
 * builders are found with the source scanner of designer_blocks_test.php.
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

// Runs $script after the designer's collapse-state functions (and its
// createNode() with `_sdT()` stubbed) and returns the JSON it prints; null
// without Node. $script builds trees with N() / sem() / con() and calls
// out(value) once.
function designer_collapse_state_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = _test_blocks_js();
	$from = strpos($source, '    // ── Toggleable open state');
	$to = strpos($source, '    // ========================= IDS', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the toggleable open-state functions');
	$node_from = strpos($source, '    function gid()');
	$node_to = strpos($source, '    function colProps(', (int) $node_from);
	pg_assert_true(($node_from !== false) && ($node_to !== false), 'the designer has createNode()');

	$js = 'var nodeIdCounter = 0;'
		. 'function _sdT(s) { var a = Array.prototype.slice.call(arguments, 1); return String(s).replace(/\\{var(?::\\d+)?\\}/g, function () { return a.length ? String(a.shift()) : ""; }); }'
		. substr($source, $from, $to - $from)
		. substr($source, $node_from, $node_to - $node_from)
		. 'var _id = 0; function N(type, props, kids) { return { _id: "n" + (++_id), type: type, props: props || {}, children: kids || [] }; }'
		. 'function sem(tag, cls, attrs, kids, extra) { var p = { tag: tag, cssClass: cls || "", _attrs: attrs || [] }; for (var k in (extra || {})) p[k] = extra[k]; return N("semantic", p, kids); }'
		. 'function con(tag, cls, attrs, extra) { var p = { contentType: "heading", tag: tag, text: "Q", cssClass: cls || "", _attrs: attrs || [] }; for (var k in (extra || {})) p[k] = extra[k]; return N("content", p); }'
		. 'function at(n, name) { var a = (n.props._attrs || []).filter(function (x) { return x.name === name; })[0]; return a ? a.value : null; }'
		. 'function cls(n) { return n.props.cssClass; }'
		. 'function out(v) { console.log(JSON.stringify(v)); }'
		. $script;

	$file = tempnam(sys_get_temp_dir(), 'pgcollapse');
	file_put_contents($file, $js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A toggle button for #$id: open or closed, the way the builders write it.
function designer_collapse_state_button($id, $open)
{
	return 'con("button", "accordion-button' . ($open ? '' : ' collapsed') . '", ['
		. '{ name: "type", value: "button" }, { name: "data-bs-toggle", value: "collapse" },'
		. ' { name: "data-bs-target", value: "#' . $id . '" }, { name: "aria-expanded", value: "' . ($open ? 'true' : 'false') . '" }])';
}

// An item open as written keeps `show` through a save, and gets it back when
// an older save dropped it; an item written closed loses a `show` the canvas
// left on it. Running the pass twice changes nothing more.
function test_designer_collapse_state_follows_the_toggle()
{
	$answer = designer_collapse_state_run(
		'var p1 = sem("div", "accordion-collapse collapse", [], [], { id: "c1" });'
		. 'var p2 = sem("div", "accordion-collapse collapse show", [], [], { id: "c2" });'
		. 'var p3 = sem("div", "accordion-collapse collapse show", [], [], { id: "c3" });'
		. 'var root = N("root", {}, [sem("div", "accordion", [], ['
		. ' sem("div", "accordion-item", [], [sem("h2", "accordion-header", [], [' . designer_collapse_state_button('c1', true) . ']), p1]),'
		. ' sem("div", "accordion-item", [], [sem("h2", "accordion-header", [], [' . designer_collapse_state_button('c2', false) . ']), p2]),'
		. ' sem("div", "accordion-item", [], [sem("h2", "accordion-header", [], [' . designer_collapse_state_button('c3', true) . ']), p3])'
		. '])]);'
		. '_normalizeToggleableShowState(root); var once = [cls(p1), cls(p2), cls(p3)];'
		. '_normalizeToggleableShowState(root);'
		. 'out([once, [cls(p1), cls(p2), cls(p3)]]);'
	);

	if ($answer === null) {
		return;
	}

	$expected = array('accordion-collapse collapse show', 'accordion-collapse collapse', 'accordion-collapse collapse show');
	pg_assert_same(array($expected, $expected), $answer, 'open toggle: show added or kept; closed toggle: show dropped; idempotent');
}

// What counts as an open toggle: aria-expanded="true" without `collapsed`; a
// link's href names the panel as well as data-bs-target; one open toggle of
// several is enough; a legacy `_attrs` id finds the panel; a class selector
// target or no toggle at all leaves the panel closed.
function test_designer_collapse_state_toggle_shapes()
{
	$answer = designer_collapse_state_run(
		'var half = sem("div", "collapse show", [], [], { id: "half" });'
		. 'var linked = sem("div", "collapse", [{ name: "id", value: "linked" }]);'
		. 'var two = sem("div", "collapse", [], [], { id: "two" });'
		. 'var bycls = sem("div", "collapse show multi", [], [], { id: "bycls" });'
		. 'var alone = sem("div", "collapse show", [], [], { id: "alone" });'
		. 'var root = N("root", {}, ['
		. ' con("button", "btn collapsed", [{ name: "data-bs-toggle", value: "collapse" }, { name: "data-bs-target", value: "#half" }, { name: "aria-expanded", value: "true" }]), half,'
		. ' sem("a", "btn", [{ name: "href", value: "#linked" }, { name: "data-bs-toggle", value: "collapse" }, { name: "aria-expanded", value: "true" }]), linked,'
		. ' ' . designer_collapse_state_button('two', false) . ', ' . designer_collapse_state_button('two', true) . ', two,'
		. ' con("button", "btn", [{ name: "data-bs-toggle", value: "collapse" }, { name: "data-bs-target", value: ".multi" }, { name: "aria-expanded", value: "true" }]), bycls,'
		. ' alone]);'
		. '_normalizeToggleableShowState(root);'
		. 'out([cls(half), cls(linked), cls(two), cls(bycls), cls(alone)]);'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('collapse', 'collapse show', 'collapse show', 'collapse multi', 'collapse'), $answer,
		'collapsed class wins over aria-expanded; href target; any open toggle; legacy id; class target and no toggle close');
}

// Modal, offcanvas and dropdown-menu have no authored open state: they close
// even when their trigger says aria-expanded="true".
function test_designer_collapse_state_other_toggleables_close()
{
	$answer = designer_collapse_state_run(
		'var modal = sem("div", "modal fade show", [], [], { id: "m" });'
		. 'var off = sem("div", "offcanvas offcanvas-start show", [], [], { id: "o" });'
		. 'var menu = sem("ul", "dropdown-menu show");'
		. 'var root = N("root", {}, ['
		. ' con("button", "btn", [{ name: "data-bs-toggle", value: "collapse" }, { name: "data-bs-target", value: "#m" }, { name: "aria-expanded", value: "true" }]), modal,'
		. ' con("button", "btn", [{ name: "data-bs-toggle", value: "offcanvas" }, { name: "data-bs-target", value: "#o" }, { name: "aria-expanded", value: "true" }]), off,'
		. ' sem("div", "dropdown", [], [con("button", "btn dropdown-toggle", [{ name: "data-bs-toggle", value: "dropdown" }, { name: "aria-expanded", value: "true" }]), menu])]);'
		. '_normalizeToggleableShowState(root);'
		. 'out([cls(modal), cls(off), cls(menu)]);'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('modal fade', 'offcanvas offcanvas-start', 'dropdown-menu'), $answer, 'show is dropped');
}

// The Expanded / Collapsed switches open or close a panel and flip every
// toggle with it; a toggle without aria-expanded gets one.
function test_designer_collapse_state_switch_writes_panel_and_toggles()
{
	$answer = designer_collapse_state_run(
		'var panel = sem("div", "accordion-collapse collapse", [], [], { id: "c1" });'
		. 'var b1 = ' . designer_collapse_state_button('c1', false) . ';'
		. 'var b2 = con("button", "btn", [{ name: "data-bs-toggle", value: "collapse" }, { name: "data-bs-target", value: "#c1" }]);'
		. 'var other = sem("div", "accordion-collapse collapse", [], [], { id: "c2" });'
		. 'var root = N("root", {}, [b1, b2, panel, other]);'
		. '_sdSetCollapseOpen(root, "c1", true);'
		. 'var opened = [cls(panel), cls(b1), at(b1, "aria-expanded"), cls(b2), at(b2, "aria-expanded"), cls(other)];'
		. '_normalizeToggleableShowState(root); var saved = cls(panel);'
		. '_sdSetCollapseOpen(root, "c1", false);'
		. 'out([opened, saved, [cls(panel), cls(b1), at(b1, "aria-expanded"), cls(b2), at(b2, "aria-expanded")]]);'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		array('accordion-collapse collapse show', 'accordion-button', 'true', 'btn', 'true', 'accordion-collapse collapse'),
		'accordion-collapse collapse show',
		array('accordion-collapse collapse', 'accordion-button collapsed', 'false', 'btn collapsed', 'false'),
	), $answer, 'open, kept by the save, then closed');
}

// Each accordion a palette builder writes: every button toggles a panel of
// the same tree (data-bs-toggle, data-bs-target, aria-controls), the panel
// names its accordion in data-bs-parent, button and panel agree on the open
// state, the first item is open, and a save keeps that state.
function test_designer_collapse_state_palette_accordions()
{
	$builders = array('accordion', 'faq-accordion', 'faq-categorized');
	$code = '';
	foreach ($builders as $ctype) {
		$at = _test_blocks_builder_at($ctype);
		$block = $at === false ? null : _test_blocks_block($at);
		pg_assert_true($block !== null, $ctype . ' has a builder');
		if ($block === null) {
			return;
		}
		$code .= 'builds.push(function (ctype) {' . substr(_test_blocks_js(), $at, $block[1] - $at + 1) . '}("' . $ctype . '"));';
	}

	$answer = designer_collapse_state_run(
		'var builds = [];' . $code
		. 'out(builds.map(function (root) {'
		. ' var nodes = []; (function w(n) { nodes.push(n); (n.children || []).forEach(w); })(root);'
		. ' var byId = {}; nodes.forEach(function (n) { var id = _sdNodeElementId(n); if (id) byId[id] = n; });'
		. ' var report = { items: 0, open: [], problems: [] };'
		. ' nodes.forEach(function (b) {'
		. '  if ((" " + cls(b) + " ").indexOf(" accordion-button ") === -1) return;'
		. '  report.items++;'
		. '  var id = _sdCollapseToggleTarget(b), panel = id ? byId[id] : null;'
		. '  if (!panel || (" " + cls(panel) + " ").indexOf(" accordion-collapse ") === -1) { report.problems.push("no panel: " + b.props.text); return; }'
		. '  if (at(b, "type") !== "button") report.problems.push("type: " + b.props.text);'
		. '  if (at(b, "aria-controls") !== id) report.problems.push("aria-controls: " + b.props.text);'
		. '  var parent = String(at(panel, "data-bs-parent") || "").replace(/^#/, "");'
		. '  if (!parent || !byId[parent] || (" " + cls(byId[parent]) + " ").indexOf(" accordion ") === -1) report.problems.push("data-bs-parent: " + b.props.text);'
		. '  var show = (" " + cls(panel) + " ").indexOf(" show ") !== -1;'
		. '  var collapsed = (" " + cls(b) + " ").indexOf(" collapsed ") !== -1;'
		. '  if (show !== (at(b, "aria-expanded") === "true") || show === collapsed) report.problems.push("state: " + b.props.text);'
		. '  report.open.push(show);'
		. ' });'
		. ' var before = JSON.stringify(root); _normalizeToggleableShowState(root);'
		. ' report.saveKeeps = JSON.stringify(root) === before;'
		. ' return report;'
		. '}));'
	);

	if ($answer === null) {
		return;
	}

	foreach ($builders as $i => $ctype) {
		$report = $answer[$i];
		pg_assert_true($report['items'] > 0, $ctype . ' writes accordion buttons');
		pg_assert_same(array(), $report['problems'], $ctype . ' buttons toggle their panels in step');
		pg_assert_true($report['open'][0], $ctype . ' first item is open');
		pg_assert_true($report['saveKeeps'], $ctype . ' a save keeps the built state');
	}
	pg_assert_same(array(true, false, false, false, false, false), $answer[1]['open'], 'faq-accordion: only the first item is open');
}
