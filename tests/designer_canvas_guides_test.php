<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The geometry of the designer's canvas guides
 * (assets/js/pg_canvas_guides.js, window.PGCanvasGuides): distances between
 * two boxes, a parent's inner distances, the spacing scale's nearest step,
 * the column under a pointer, equal gaps among siblings. Runs in Node;
 * skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGCanvasGuides loaded as G; its `out`
// comes back decoded. Null when Node cannot run.
function canvas_guides_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_canvas_guides.js') . "\n"
		. "var G = window.PGCanvasGuides, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgcg');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// A line as the module answers it, without `reach` / `between`.
function canvas_guides_test_line($axis, $from, $to, $at, $value)
{
	return array('axis' => $axis, 'from' => $from, 'to' => $to, 'at' => $at, 'value' => $value);
}

// A box is read from edges or from x / y / width / height; nothing else is
// a box.
function test_canvas_guides_box_reads_both_shapes()
{
	$res = canvas_guides_test_node('out = ['
		. ' G.box({ left: 10, top: 20, right: 110, bottom: 70 }),'
		. ' G.box({ x: 10, y: 20, width: 100, height: 50 }),'
		. ' G.box({ x: 110, y: 70, width: -100, height: -50 }),'
		. ' G.box(null), G.box({}), G.box({ left: "a", top: 0, right: 1, bottom: 1 })'
		. '];');

	if ($res === null) {
		return;
	}

	$b = array('left' => 10, 'top' => 20, 'right' => 110, 'bottom' => 70);
	pg_assert_same($b, $res[0], 'edges');
	pg_assert_same($b, $res[1], 'x, y, width, height');
	pg_assert_same($b, $res[2], 'a negative size is turned round');
	pg_assert_same(null, $res[3], 'null is no box');
	pg_assert_same(null, $res[4], 'an empty object is no box');
	pg_assert_same(null, $res[5], 'a word for an edge is no box');
}

// Two boxes side by side: one line across the gap at the middle of their
// shared height; stacked: one line down at the middle of their shared width.
function test_canvas_guides_distances_between_apart_boxes()
{
	$res = canvas_guides_test_node('var a = { left: 0, top: 0, right: 100, bottom: 100 };'
		. 'out = ['
		. ' G.distances(a, { left: 140, top: 20, right: 200, bottom: 160 }),'
		. ' G.distances({ left: 140, top: 20, right: 200, bottom: 160 }, a),'
		. ' G.distances(a, { left: 10, top: 130, right: 60, bottom: 150 }),'
		. ' G.distances(a, { left: 140, top: 20, right: 200, bottom: 160 }, 2),'
		. ' G.distances(a, { left: 100, top: 0, right: 200, bottom: 100 })'
		. '];');

	if ($res === null) {
		return;
	}

	$right = canvas_guides_test_line('x', 100, 140, 60, 40);
	$right['reach'] = null;
	pg_assert_same(array($right), $res[0], 'a box to the right: 40 px at the middle of the shared height');
	pg_assert_same(array($right), $res[1], 'the same line seen from the other box');

	$below = canvas_guides_test_line('y', 100, 130, 35, 30);
	$below['reach'] = null;
	pg_assert_same(array($below), $res[2], 'a box below: 30 px at the middle of the shared width');

	pg_assert_same(20, $res[3][0]['value'], 'at a zoom of 2 the 40 canvas pixels are 20 page pixels');
	pg_assert_same(100, $res[3][0]['from'], 'positions stay in canvas pixels');

	$touch = canvas_guides_test_line('x', 100, 100, 50, 0);
	$touch['reach'] = null;
	pg_assert_same(array($touch), $res[4], 'touching boxes read 0');
}

// A box away on both axes: one line on each, drawn at the selection's middle,
// with `reach` where the dashed guide meets the other box.
function test_canvas_guides_distances_diagonal()
{
	$res = canvas_guides_test_node('out = G.distances('
		. '{ left: 0, top: 0, right: 100, bottom: 100 },'
		. '{ left: 150, top: 180, right: 250, bottom: 260 });');

	if ($res === null) {
		return;
	}

	$x = canvas_guides_test_line('x', 100, 150, 50, 50);
	$x['reach'] = 180;
	$y = canvas_guides_test_line('y', 100, 180, 50, 80);
	$y['reach'] = 150;
	pg_assert_same(array($x, $y), $res, 'one line per axis, each reaching the other box\'s near edge');
}

// A box inside another (a selection and the element pointed at around it):
// the four inner distances; overlapping boxes: the starts and the ends.
function test_canvas_guides_distances_nested_and_overlapping()
{
	$res = canvas_guides_test_node('out = ['
		. ' G.distances({ left: 20, top: 10, right: 80, bottom: 50 }, { left: 0, top: 0, right: 100, bottom: 100 }),'
		. ' G.distances({ left: 0, top: 0, right: 100, bottom: 100 }, { left: 0, top: 0, right: 100, bottom: 60 }),'
		. ' G.distances({ left: 0, top: 0, right: 100, bottom: 100 }, { left: 50, top: 50, right: 150, bottom: 150 })'
		. '];');

	if ($res === null) {
		return;
	}

	$lines = array(
		canvas_guides_test_line('x', 0, 20, 30, 20),
		canvas_guides_test_line('x', 80, 100, 30, 20),
		canvas_guides_test_line('y', 0, 10, 50, 10),
		canvas_guides_test_line('y', 50, 100, 50, 50),
	);
	foreach ($lines as $i => $l) {
		$lines[$i]['reach'] = null;
	}
	pg_assert_same($lines, $res[0], 'inside: left, right, top, bottom');

	$bottom = canvas_guides_test_line('y', 60, 100, 50, 40);
	$bottom['reach'] = null;
	pg_assert_same(array($bottom), $res[1], 'edges in line give no line');

	pg_assert_same(4, count($res[2]), 'overlapping: two starts and two ends');
	pg_assert_same(50, $res[2][0]['value'], 'the left edges 50 apart');
	pg_assert_same(75, $res[2][0]['at'], 'at the middle of the shared height');
}

// Two boxes that face each other: how far their starts are apart, at the
// middle of the box pointed at, with a dashed guide along the selection's
// start (the drawing's card and picture: 36 px between the tops).
function test_canvas_guides_offset_between_facing_starts()
{
	$res = canvas_guides_test_node('var card = { left: 36, top: 84, right: 232, bottom: 244 };'
		. 'out = ['
		. ' G.offset(card, { left: 430, top: 48, right: 662, bottom: 198 }),'
		. ' G.offset({ left: 430, top: 48, right: 662, bottom: 198 }, card),'
		. ' G.offset(card, { left: 60, top: 300, right: 200, bottom: 360 }, 2),'
		. ' G.offset(card, { left: 430, top: 84, right: 662, bottom: 198 }),'
		. ' G.offset(card, { left: 430, top: 300, right: 662, bottom: 400 }),'
		. ' G.offset(card, { left: 0, top: 0, right: 300, bottom: 300 }),'
		. ' G.offset(card, null)'
		. '];');

	if ($res === null) {
		return;
	}

	$tops = canvas_guides_test_line('y', 48, 84, 546, 36);
	$tops['guide'] = array('axis' => 'x', 'from' => 232, 'to' => 662, 'at' => 84);
	pg_assert_same($tops, $res[0], 'side by side: the tops, at the middle of the picture, the guide from the card to the far edge');

	$back = canvas_guides_test_line('y', 48, 84, 134, 36);
	$back['guide'] = array('axis' => 'x', 'from' => 430, 'to' => 36, 'at' => 48);
	pg_assert_same($back, $res[1], 'seen from the picture: the guide runs back to the card\'s far edge');

	$lefts = canvas_guides_test_line('x', 36, 60, 330, 12);
	$lefts['guide'] = array('axis' => 'y', 'from' => 244, 'to' => 360, 'at' => 36);
	pg_assert_same($lefts, $res[2], 'stacked: the starts on the left, in page pixels at a zoom of 2');

	pg_assert_same(null, $res[3], 'tops level: no line');
	pg_assert_same(null, $res[4], 'off diagonally: no line');
	pg_assert_same(null, $res[5], 'one inside the other: no line');
	pg_assert_same(null, $res[6], 'no box');
}

// A parent's inner distances by side; the start is on the right in a
// right-to-left page; a child sticking out reads negative.
function test_canvas_guides_inner_distances()
{
	$res = canvas_guides_test_node('var p = { left: 0, top: 0, right: 300, bottom: 200 };'
		. 'var c = { left: 24, top: 16, right: 260, bottom: 152 };'
		. 'function v(r) { return [r.t.value, r.e.value, r.b.value, r.s.value]; }'
		. 'out = [ v(G.inner(c, p)), v(G.inner(c, p, 1, true)), v(G.inner(c, p, 2)),'
		. ' v(G.inner({ left: -10, top: 0, right: 300, bottom: 200 }, p)),'
		. ' G.inner(c, p).t.line, G.inner(c, p).s.line, G.inner(null, p) ];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(16, 40, 48, 24), $res[0], 'top, end, bottom, start');
	pg_assert_same(array(16, 24, 48, 40), $res[1], 'right to left: the start is the right side');
	pg_assert_same(array(8, 20, 24, 12), $res[2], 'at a zoom of 2');
	pg_assert_same(array(0, 0, 0, -10), $res[3], 'a child sticking out reads negative');
	pg_assert_same(canvas_guides_test_line('y', 0, 16, 142, 16), $res[4], 'the top line at the middle of the child');
	pg_assert_same(canvas_guides_test_line('x', 0, 24, 84, 24), $res[5], 'the start line at the middle of the child');
	pg_assert_same(null, $res[6], 'no child, no distances');
}

// The spacing scale is Bootstrap 5.3's (0, .25, .5, 1, 1.5, 3 rem); a value
// goes to its nearest step, a tie to the smaller.
function test_canvas_guides_nearest_step()
{
	$res = canvas_guides_test_node('function n(v, s) { var r = G.nearestStep(v, s); return r.step + ":" + r.px; }'
		. 'out = [ G.scale(), G.scale(20), G.scale(0),'
		. ' n(0), n(3), n(6), n(7), n(12), n(13), n(20), n(21), n(36), n(37), n(500), n(-8), n("x"),'
		. ' n(10, 20), n(10, [0, 10, 20]),'
		. ' G.dragStep("3", 9).step, G.dragStep("3", -5).step, G.dragStep("3", -100).step,'
		. ' G.dragStep("", 7).step, G.dragStep("auto", 30).step, G.dragStep("5", 40).step ];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(0, 4, 8, 16, 24, 48), $res[0], 'the scale at a 16 px root');
	pg_assert_same(array(0, 5, 10, 20, 30, 60), $res[1], 'the scale at a 20 px root');
	pg_assert_same(array(0, 4, 8, 16, 24, 48), $res[2], 'a root of 0 reads as 16 px');

	$expect = array('0:0', '1:4', '1:4', '2:8', '2:8', '3:16', '3:16', '4:24', '4:24', '5:48', '5:48', '0:0', '0:0', '2:10', '1:10');
	$names = array('0 px', '3 px', '6 px (tie, the smaller)', '7 px', '12 px (tie)', '13 px', '20 px (tie)', '21 px', '36 px (tie)', '37 px', 'past the scale', 'negative', 'not a number', 'a 20 px root', 'a given scale');
	foreach ($expect as $i => $e) {
		pg_assert_same($e, $res[3 + $i], $names[$i]);
	}

	pg_assert_same('4', $res[18], 'step 3 (16 px) dragged 9 px out lands on 4 (24 px)');
	pg_assert_same('2', $res[19], 'dragged 5 px in lands on 2');
	pg_assert_same('0', $res[20], 'never below 0');
	pg_assert_same('2', $res[21], 'no value starts at 0');
	pg_assert_same('4', $res[22], 'auto starts at 0 too');
	pg_assert_same('5', $res[23], 'never past 5');
}

// The twelve columns of a row, the column under a pointer and a dragged
// column's span, left to right and right to left.
function test_canvas_guides_columns()
{
	$res = canvas_guides_test_node('var row = { left: 0, top: 0, right: 1200, bottom: 80 };'
		. 'var cols = G.columns(row);'
		. 'out = [ cols.length, cols[0], cols[11].right, G.columns(row, { rtl: true })[0].left,'
		. ' G.columns(row, { gutter: 32, zoom: 0.5 })[0].content, G.columns(row, { count: 6 }).length,'
		. ' G.columnAt(0, row), G.columnAt(99, row), G.columnAt(100, row), G.columnAt(1199, row), G.columnAt(1500, row), G.columnAt(-30, row),'
		. ' G.columnAt(50, row, { rtl: true }), G.columnAt(10, { left: 0, top: 0, right: 0, bottom: 10 }),'
		. ' G.spanAt(0, 600, row), G.spanAt(0, 640, row), G.spanAt(0, 20, row), G.spanAt(3, 2000, row), G.spanAt(6, 300, row),'
		. ' G.spanAt(0, 300, row, { rtl: true }), G.spanAt(0, 600, row, { count: 6 }) ];');

	if ($res === null) {
		return;
	}

	pg_assert_same(12, $res[0], 'twelve columns');
	pg_assert_same(array('index' => 1, 'left' => 0, 'right' => 100, 'top' => 0, 'bottom' => 80,
		'content' => array('left' => 12, 'right' => 88, 'top' => 0, 'bottom' => 80)), $res[1], 'the first column, its content half a 24 px gutter in');
	pg_assert_same(1200, $res[2], 'the last column ends at the row\'s end');
	pg_assert_same(1100, $res[3], 'right to left: the first column is on the right');
	pg_assert_same(array('left' => 8, 'right' => 92, 'top' => 0, 'bottom' => 80), $res[4], 'a 32 px gutter at a zoom of 0.5 is 8 canvas pixels a side');
	pg_assert_same(6, $res[5], 'a count');

	pg_assert_same(array(1, 1, 2, 12, 12, 1), array_slice($res, 6, 6), 'the column under the pointer, outside the row the nearest end');
	pg_assert_same(12, $res[12], 'right to left: the left end is the last column');
	pg_assert_same(null, $res[13], 'a row with no width has no column');

	pg_assert_same(array('span' => 6, 'of' => 12), $res[14], 'the edge dragged to the middle: 6 of 12');
	pg_assert_same(array('span' => 6, 'of' => 12), $res[15], 'the nearest column line wins');
	pg_assert_same(array('span' => 1, 'of' => 12), $res[16], 'at least one column');
	pg_assert_same(array('span' => 9, 'of' => 12), $res[17], 'at most what is left of the row');
	pg_assert_same(array('span' => 1, 'of' => 12), $res[18], 'an edge dragged before its start keeps one column');
	pg_assert_same(array('span' => 9, 'of' => 12), $res[19], 'right to left');
	pg_assert_same(array('span' => 3, 'of' => 6), $res[20], 'of a six-column row');
}

// The gaps between neighbouring siblings, in either order given; the gaps of
// the same size as the one being set.
function test_canvas_guides_gaps_and_equal_gaps()
{
	$res = canvas_guides_test_node('var row = [ { left: 240, top: 0, right: 300, bottom: 50 }, { left: 0, top: 0, right: 100, bottom: 50 },'
		. ' { left: 124, top: 10, right: 216, bottom: 40 }, { left: 324, top: 0, right: 400, bottom: 50 } ];'
		. 'var stack = [ { left: 0, top: 0, right: 100, bottom: 40 }, { left: 0, top: 56, right: 100, bottom: 90 },'
		. ' { left: 0, top: 106, right: 100, bottom: 120 }, { left: 0, top: 110, right: 100, bottom: 130 } ];'
		. 'function v(g) { return g.map(function (x) { return [x.value, x.between]; }); }'
		. 'out = [ v(G.gaps(row, "x")), G.gaps(row, "x")[0], v(G.gaps(stack, "y")),'
		. ' v(G.equalGaps(row, 24, "x")), v(G.equalGaps(row, 24.4, "x")), v(G.equalGaps(row, 25, "x")),'
		. ' v(G.equalGaps(row, 12, "x", 2)), v(G.equalGaps(stack, 16, "y")), G.equalGaps(row, "x", "x"), G.gaps([], "x"),'
		. ' G.snap(23, [16, 24, 48]), G.snap(30, [16, 24, 48]), G.snap(20, [16, 24], 4), G.snap(5, []) ];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(array(24, array(1, 2)), array(24, array(2, 0)), array(24, array(0, 3))), $res[0], 'three gaps of 24, sorted by place, named by index');
	$first = canvas_guides_test_line('x', 100, 124, 25, 24);
	$first['between'] = array(1, 2);
	pg_assert_same($first, $res[1], 'a gap is a line at the middle of the shared height');
	pg_assert_same(array(array(16, array(0, 1)), array(16, array(1, 2))), $res[2], 'stacked; overlapping boxes give no gap');

	pg_assert_same(3, count($res[3]), 'every gap of 24');
	pg_assert_same(3, count($res[4]), 'within half a pixel');
	pg_assert_same(0, count($res[5]), 'a pixel off is not equal');
	pg_assert_same(array(array(12, array(1, 2)), array(12, array(2, 0)), array(12, array(0, 3))), $res[6], 'at a zoom of 2 the 24 canvas pixels are 12 page pixels');
	pg_assert_same(2, count($res[7]), 'the stacked gaps');
	pg_assert_same(array(), $res[8], 'no value, no gap');
	pg_assert_same(array(), $res[9], 'no siblings, no gap');

	pg_assert_same(24, $res[10], 'the handle holds at the nearest target within 4 px');
	pg_assert_same(null, $res[11], 'too far from any target');
	pg_assert_same(16, $res[12], 'a tie keeps the first');
	pg_assert_same(null, $res[13], 'no target');
}

// Layout lines: Bootstrap's container width for a canvas width, and the
// container's edges, its twelve columns and the gutters between them.
function test_canvas_guides_layout_lines()
{
	$res = canvas_guides_test_node('var page = { left: 0, top: 0, right: 1200, bottom: 900 };'
		. 'var l = G.layout(page);'
		. 'var found = G.layout(page, { container: { left: 100, right: 700, padLeft: 12, padRight: 12 } });'
		. 'var half = G.layout({ left: 0, top: 0, right: 600, bottom: 450 }, { zoom: 0.5 });'
		. 'out = [ [375, 576, 767, 768, 992, 1200, 1399, 1400, 1920].map(G.containerWidth),'
		. ' [l.left, l.right, l.top, l.bottom], l.columns.length, l.columns[0], l.columns[11], l.gutters.length, l.gutters[0],'
		. ' [found.left, found.right, found.columns[0].left, found.columns[11].right, found.gutters[0].right - found.gutters[0].left],'
		. ' [half.left, half.right, half.columns[0].left, half.gutters[0].right - half.gutters[0].left],'
		. ' G.layout({ left: 0, top: 0, right: 375, bottom: 600 }).left,'
		. ' G.layout({ left: 0, top: 0, right: 1200, bottom: 900 }, { count: 6 }).columns.length,'
		. ' G.layout(null), G.layout({ left: 0, top: 0, right: 0, bottom: 10 }) ];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array(375, 540, 540, 720, 960, 1140, 1140, 1320, 1320), $res[0], 'Bootstrap 5.3 container widths; below 576 the full width');
	pg_assert_same(array(30, 1170, 0, 900), $res[1], 'the 1140 container centred on a 1200 canvas, full height');
	pg_assert_same(12, $res[2], 'twelve columns');
	pg_assert_same(array('left' => 42, 'right' => 113), $res[3], 'the first column\'s content starts at the container\'s padding');
	pg_assert_same(array('left' => 1087, 'right' => 1158), $res[4], 'the last ends at the container\'s padding');
	pg_assert_same(11, $res[5], 'a gutter between each two columns');
	pg_assert_same(array('left' => 113, 'right' => 137), $res[6], 'a 24 px gutter');
	pg_assert_same(array(100, 700, 112, 688, 24), $res[7], 'the container found on the page and its paddings');
	pg_assert_same(array(15, 585, 21, 12), $res[8], 'at a zoom of 0.5 the 1200 page px canvas: the container and gutters halved');
	pg_assert_same(0, $res[9], 'a phone canvas: the container is the full width');
	pg_assert_same(6, $res[10], 'a count');
	pg_assert_same(null, $res[11], 'no canvas, no lines');
	pg_assert_same(null, $res[12], 'a canvas with no width');
}
