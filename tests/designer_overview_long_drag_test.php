<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the pure functions behind a long drag in the designer's Overview
 * tree (assets/js/style_designer.src.js): the open state saved when a drag
 * starts and the tree folded to its sections (_sdTreeDragSave(),
 * _sdTreeDragFold()), given back as it was after Esc and after a drop, where
 * a branch opened under the pointer stays open when the drop went into it
 * (_sdTreeDragRestore(), _sdTreeDragKeep()); a closed branch opened after
 * 600 ms under the pointer (_sdTreeDragHover()); the scroll speed by the
 * pointer's distance from an edge (_sdTreeDragScroll()). Runs the functions
 * under Node; skipped where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function designer_overview_long_drag_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs a Node script after the long-drag functions and returns its JSON
// output. Null without Node or when a function is missing.
function designer_overview_long_drag_run($script)
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

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$src = '';

	foreach (array('_sdTreeDragSave', '_sdTreeDragFold', '_sdTreeDragRestore', '_sdTreeDragKeep', '_sdTreeDragHover', '_sdTreeDragScroll') as $name) {
		$fn = designer_overview_long_drag_fn($js, $name);

		if (!pg_assert_true($fn !== '', 'style_designer.js has ' . $name . '()')) {
			return null;
		}

		$src .= $fn . "\n";
	}

	// A page of three sections: Header (open, a closed menu in it), Main
	// (closed, an article in it) and Footer (never toggled), and a text
	// with nothing under it.
	$page = 'function page() {' . "\n"
		. '  var menu = { id: "menu", _expanded: false, children: [{ id: "link", children: [] }] };' . "\n"
		. '  var header = { id: "header", _expanded: true, children: [menu] };' . "\n"
		. '  var article = { id: "article", children: [{ id: "para", children: [] }] };' . "\n"
		. '  var main = { id: "main", _expanded: false, children: [article] };' . "\n"
		. '  var footer = { id: "footer", children: [{ id: "copy", children: [] }] };' . "\n"
		. '  var text = { id: "text", children: [] };' . "\n"
		. '  var root = { id: "root", children: [header, main, footer, text] };' . "\n"
		. '  var up = {};' . "\n"
		. '  (function walk(n) { (n.children || []).forEach(function (c) { up[c.id] = n; walk(c); }); })(root);' . "\n"
		. '  return { root: root, by: function (id) { var f = null; (function w(n) { if (n.id === id) f = n; (n.children || []).forEach(w); })(root); return f; },' . "\n"
		. '    parentOf: function (n) { return up[n.id] || null; } };' . "\n"
		. '}' . "\n"
		. 'function kids(n) { return n.children || []; }' . "\n"
		. 'function states(root) { var o = {}; (function w(n) { (n.children || []).forEach(function (c) { o[c.id] = c.hasOwnProperty("_expanded") ? c._expanded : "unset"; w(c); }); })(root); return o; }' . "\n";

	$file = tempnam(sys_get_temp_dir(), 'pgovlong');
	file_put_contents($file, $src . $page . $script . "\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

// When a drag starts the tree folds to its sections; Esc gives every branch
// back as it was, a never-toggled one included.
function test_designer_overview_long_drag_esc_gives_the_tree_back()
{
	$res = designer_overview_long_drag_run(
		'var p = page(), out = {};' . "\n"
		. 'out.before = states(p.root);' . "\n"
		. 'var saved = _sdTreeDragSave(p.root, kids);' . "\n"
		. '_sdTreeDragFold(p.root, kids);' . "\n"
		. 'out.folded = states(p.root);' . "\n"
		. '// Branches opened under the pointer during the drag.' . "\n"
		. 'p.by("main")._expanded = true; p.by("article")._expanded = true;' . "\n"
		. '_sdTreeDragRestore(saved, []);' . "\n"
		. 'out.after = states(p.root);' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	foreach (array('header', 'main', 'footer') as $id) {
		pg_assert_same(false, $res['folded'][$id], 'the drag folds the section ' . $id);
	}

	pg_assert_same('unset', $res['folded']['text'], 'a section with nothing under it is left alone');
	pg_assert_same(false, $res['folded']['menu'], 'a branch inside a section keeps its own state');
	pg_assert_same($res['before'], $res['after'], 'after Esc every branch is as it was');
}

// After a drop the tree is as it was, but a branch opened under the pointer
// that the drop went into stays open, with the branches over it; the other
// branches opened on the way close back.
function test_designer_overview_long_drag_drop_keeps_the_branch_it_went_into()
{
	$res = designer_overview_long_drag_run(
		'var p = page(), out = {};' . "\n"
		. 'out.before = states(p.root);' . "\n"
		. 'var saved = _sdTreeDragSave(p.root, kids);' . "\n"
		. '_sdTreeDragFold(p.root, kids);' . "\n"
		. 'var opened = [p.by("footer"), p.by("main"), p.by("article")];' . "\n"
		. 'opened.forEach(function (n) { n._expanded = true; });' . "\n"
		. 'var keep = _sdTreeDragKeep(opened, p.by("article"), p.parentOf);' . "\n"
		. 'out.keep = keep.map(function (n) { return n.id; });' . "\n"
		. '_sdTreeDragRestore(saved, keep);' . "\n"
		. 'out.after = states(p.root);' . "\n"
		. 'var q = page();' . "\n"
		. 'var saved2 = _sdTreeDragSave(q.root, kids);' . "\n"
		. '_sdTreeDragFold(q.root, kids);' . "\n"
		. 'var opened2 = [q.by("main")];' . "\n"
		. 'q.by("main")._expanded = true;' . "\n"
		. 'var keep2 = _sdTreeDragKeep(opened2, q.root, q.parentOf);' . "\n"
		. 'out.keep2 = keep2.length;' . "\n"
		. '_sdTreeDragRestore(saved2, keep2);' . "\n"
		. 'out.after2 = states(q.root);' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('main', 'article'), $res['keep'], 'the opened branches over the drop are kept, the others not');
	pg_assert_same(true, $res['after']['main'], 'the section opened on the way to the drop stays open');
	pg_assert_same(true, $res['after']['article'], 'the branch the drop went into stays open');
	pg_assert_same('unset', $res['after']['footer'], 'a branch opened on the way but not dropped into closes back');
	pg_assert_same(true, $res['after']['header'], 'a section open before the drag is open again');
	pg_assert_same(false, $res['after']['menu'], 'a branch closed before the drag is closed again');
	pg_assert_same(0, $res['keep2'], 'a drop on the page body keeps no opened branch');
	pg_assert_same($res['before'], $res['after2'], 'so the tree is as it was');
}

// A closed branch under the pointer opens after 600 ms; moving to another
// row starts the time again, leaving the rows stops it.
function test_designer_overview_long_drag_branch_opens_after_600ms()
{
	$res = designer_overview_long_drag_run(
		'var out = [], h = null;' . "\n"
		. '[["main", 0], ["main", 300], ["main", 599], ["main", 600], ["main", 900],' . "\n"
		. ' ["footer", 950], ["main", 1000], ["main", 1599], ["main", 1600], [null, 1700], [null, 2400]].forEach(function (s) {' . "\n"
		. '  h = _sdTreeDragHover(h, s[0], s[1]);' . "\n"
		. '  out.push([h.id, h.since, h.open]);' . "\n"
		. '});' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('main', 0, false), $res[0], 'the pointer arriving on a row starts the time');
	pg_assert_same(false, $res[2][2], 'not open at 599 ms');
	pg_assert_same(true, $res[3][2], 'open at 600 ms');
	pg_assert_same(array('footer', 950, false), $res[5], 'another row starts the time again');
	pg_assert_same(array('main', 1000, false), $res[6], 'and coming back starts it once more');
	pg_assert_same(false, $res[7][2], 'not open 599 ms after coming back');
	pg_assert_same(true, $res[8][2], 'open 600 ms after coming back');
	pg_assert_same(false, $res[10][2], 'no row under the pointer opens nothing');
}

// The top and bottom 48 px of the tree scroll it, from 6 px a frame at the
// zone's inner edge to 24 px a frame at the tree's edge; the rest does not.
function test_designer_overview_long_drag_scroll_speed_by_distance()
{
	$res = designer_overview_long_drag_run(
		'var out = {};' . "\n"
		. '[-20, 0, 12, 24, 36, 47, 48, 200, 352, 353, 364, 376, 388, 400, 430].forEach(function (y) { out[y] = _sdTreeDragScroll(y, 400); });' . "\n"
		. 'out.small = [_sdTreeDragScroll(30, 80), _sdTreeDragScroll(50, 80), _sdTreeDragScroll(40, 80)];' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	$want = array(
		'-20' => -24, '0' => -24, '12' => -20, '24' => -15, '36' => -11, '47' => -6, '48' => 0,
		'200' => 0, '352' => 0, '353' => 6, '364' => 11, '376' => 15, '388' => 20, '400' => 24, '430' => 24,
	);

	foreach ($want as $y => $v) {
		pg_assert_same($v, $res[$y], 'y ' . $y . ' px of a 400 px tree scrolls ' . $v . ' px a frame');
	}

	pg_assert_true($res['small'][0] < 0, 'in a short tree the nearer edge wins: up');
	pg_assert_true($res['small'][1] > 0, 'in a short tree the nearer edge wins: down');
	pg_assert_same(0, $res['small'][2], 'in a short tree the middle does not scroll');
}
