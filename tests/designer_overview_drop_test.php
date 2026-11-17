<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the pure function behind the drop targets of the designer's Overview
 * tree (assets/js/style_designer.src.js, _sdTreeDropPick()): the gap between
 * two rows reaches 7 px each way, the middle 14 px of a 28 px row means
 * "inside", the pointer's horizontal place picks a level among the legal
 * parents only (12 px a level), and a refused target says why. Runs the
 * function under Node; skipped where there is no Node.
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
function designer_overview_drop_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs a Node script after the drop functions and returns its JSON output.
// Null without Node or when a function is missing.
function designer_overview_drop_run($script)
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

	foreach (array('_sdT', '_sdTreeDropWhy', '_sdTreeDropPick') as $name) {
		$fn = designer_overview_drop_fn($js, $name);

		if (!pg_assert_true($fn !== '', 'style_designer.js has ' . $name . '()')) {
			return null;
		}

		$src .= $fn . "\n";
	}

	$file = tempnam(sys_get_temp_dir(), 'pgovdrop');
	file_put_contents($file, $src
		. 'function t(o) { return { indent: o[0], ok: o[1], name: o[2], place: o[3], code: o[4] || "" }; }' . "\n"
		. $script . "\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : null;
}

// The gap between two rows is one target reaching 7 px into each of them.
function test_designer_overview_drop_gap_reaches_7px()
{
	$res = designer_overview_drop_run(
		'var inside = t([12, true, "Account menu", 3]);' . "\n"
		. 'var gap = [t([12, true, "Account menu", 2])];' . "\n"
		. 'var out = {};' . "\n"
		. '[0, 3, 6.9, 7, 14, 20.9, 21, 25, 27.9].forEach(function (y) { out[String(y)] = _sdTreeDropPick({ y: y, x: 40, h: 28, inside: inside, gap: gap }).zone; });' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	foreach (array('0', '3', '6.9') as $y) {
		pg_assert_same('before', $res[$y], 'y ' . $y . ' px of a row is the gap above it');
	}

	foreach (array('21', '25', '27.9') as $y) {
		pg_assert_same('after', $res[$y], 'y ' . $y . ' px of a row is the gap below it');
	}

	pg_assert_same('inside', $res['7'], 'the gap above ends at 7 px');
	pg_assert_same('inside', $res['20.9'], 'the gap below starts 7 px above the row\'s foot');
}

// The middle 14 px of a 28 px row mean "inside" and the label names the row.
function test_designer_overview_drop_middle_14px_is_inside()
{
	$res = designer_overview_drop_run(
		'var inside = t([12, true, "Account menu", 3]);' . "\n"
		. 'var out = [];' . "\n"
		. 'for (var y = 0; y < 28; y += 0.5) out.push(_sdTreeDropPick({ y: y, x: 40, h: 28, inside: inside, gap: [] }));' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	$in = 0;

	foreach ($res as $r) {
		if ($r['zone'] === 'inside') {
			$in++;
			pg_assert_true($r['ok'], 'inside a row that takes the element is a target');
			pg_assert_same('Into Account menu, place 3', $r['text'], 'the label says the target in words');
		}
	}

	pg_assert_same(28, $in, 'the middle 14 px (28 half-pixel steps) are "inside"');
}

// The pointer's horizontal place picks the level, 12 px a level, and only
// among parents that take the element.
function test_designer_overview_drop_level_among_legal_parents()
{
	$res = designer_overview_drop_run(
		'var all = [t([48, true, "Menu item", 2]), t([36, true, "Account menu", 4]), t([24, true, "Header", 3])];' . "\n"
		. 'var some = [t([48, true, "Menu item", 2]), t([36, false, "Account menu", 4, "list"]), t([24, true, "Header", 3])];' . "\n"
		. 'var out = { all: {}, some: {} };' . "\n"
		. '[0, 20, 31, 32, 37, 43, 44, 49, 50, 56, 120].forEach(function (x) {' . "\n"
		. '  out.all[x] = _sdTreeDropPick({ y: 25, x: x, h: 28, inside: null, gap: all });' . "\n"
		. '  out.some[x] = _sdTreeDropPick({ y: 25, x: x, h: 28, inside: null, gap: some });' . "\n"
		. '});' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	// Dots at 56, 44 and 32 px: the nearest one wins, the deeper on a tie.
	$want = array(0 => 2, 20 => 2, 31 => 2, 32 => 2, 37 => 2, 43 => 1, 44 => 1, 49 => 1, 50 => 0, 56 => 0, 120 => 0);

	foreach ($want as $x => $i) {
		pg_assert_same($i, $res['all'][$x]['index'], 'x ' . $x . ' px picks target ' . $i);
		pg_assert_true($res['all'][$x]['ok'], 'x ' . $x . ' px lands');
	}

	pg_assert_same(36, $res['all'][44]['indent'], 'the line starts at the chosen level\'s indent');
	pg_assert_same('Into Account menu, place 4', $res['all'][44]['text'], 'the label names the chosen parent');

	foreach ($want as $x => $i) {
		$got = $res['some'][$x]['index'];
		pg_assert_true($got !== 1, 'x ' . $x . ' px never picks the parent that refuses');
		pg_assert_true($res['some'][$x]['ok'], 'x ' . $x . ' px still lands on a legal parent');
	}

	pg_assert_same(0, $res['some'][44]['index'], 'over the refusing level the deeper legal one wins');
	pg_assert_same(2, $res['some'][37]['index'], 'nearer the shallower dot the shallower legal one wins');
}

// A row that takes nothing here is refused and the label says why.
function test_designer_overview_drop_refused_says_why()
{
	$res = designer_overview_drop_run(
		'var out = {};' . "\n"
		. 'out.empty = _sdTreeDropPick({ y: 14, x: 40, h: 28, inside: t([12, false, "Logo", 1, "empty"]), gap: [] });' . "\n"
		. 'out.self = _sdTreeDropPick({ y: 14, x: 40, h: 28, inside: t([12, false, "Menu", 1, "self"]), gap: [] });' . "\n"
		. 'out.gap = _sdTreeDropPick({ y: 2, x: 18, h: 28, inside: null, gap: [t([24, false, "List", 2, "list"]), t([12, false, "Header", 1, "locked"])] });' . "\n"
		. 'out.none = _sdTreeDropPick({ y: 14, x: 40, h: 28, inside: null, gap: [] });' . "\n"
		. 'out.rule = _sdTreeDropWhy("rule", "Table");' . "\n"
		. 'out.form = _sdTreeDropWhy("form", "Contact");' . "\n"
		. 'console.log(JSON.stringify(out));');

	if ($res === null) {
		return;
	}

	pg_assert_true(!$res['empty']['ok'], 'a row that holds nothing refuses "inside"');
	pg_assert_same('Logo holds no other elements', $res['empty']['text'], 'the label says why');
	pg_assert_same('An element cannot go inside itself', $res['self']['text'], 'a drop into itself says why');
	pg_assert_true(!$res['gap']['ok'], 'a gap with no legal parent is refused');
	pg_assert_same(1, $res['gap']['index'], 'the reason of the level under the pointer is given');
	pg_assert_same('Header is locked', $res['gap']['text'], 'a locked parent says so');
	pg_assert_true(!$res['none']['ok'], 'no target is no drop');
	pg_assert_same('Table does not take this element', $res['rule'], 'any other refusal names the parent');
	pg_assert_same('A form cannot go inside another form', $res['form'], 'a form in a form says so');
}
