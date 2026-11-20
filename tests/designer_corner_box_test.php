<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The corner box of the designer's Style › Border and corner section
 * (assets/js/pg_corner_box.js, window.PGCornerBox): a side or all four
 * corners and a value mapped to the radius class today's rows write, both
 * ways; each corner as the browser applies the classes; Shift and Alt.
 * Runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGCornerBox loaded as R; its `out` comes
// back decoded. Null when Node cannot run.
function corner_box_test_node($code)
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
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_corner_box.js') . "\n"
		. "var R = window.PGCornerBox, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgrdb');

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

// Every side and every value is written as the class today's row writes
// (rowCss(): 'rounded-top-' … 'rounded-start-', 0 to 5; Radius: 0 to 5,
// circle, pill) and read back from it as set (K1).
function test_corner_box_writes_todays_class_for_every_side()
{
	$cases = array();
	$prefix = array('t' => 'rounded-top-', 'e' => 'rounded-end-', 'b' => 'rounded-bottom-', 's' => 'rounded-start-', 'all' => 'rounded-');

	foreach ($prefix as $side => $pfx) {
		$values = array('0', '1', '2', '3', '4', '5');

		if ($side === 'all') {
			$values[] = 'circle';
			$values[] = 'pill';
		}

		foreach ($values as $value) {
			$cases[] = array($side, $value, $pfx . $value);
		}
	}

	$res = corner_box_test_node(
		'var cases = ' . json_encode($cases) . ";\n"
		. "out = cases.map(function (c) {\n"
		. "  var to = R.sides(c[0], false, false);\n"
		. "  var list = R.write('card shadow', to.sides, c[1], to.all);\n"
		. "  var r = R.read(list);\n"
		. "  return [list, r[c[0]].value, r[c[0]].here];\n"
		. '});'
	);

	if ($res === null) {
		return;
	}

	foreach ($cases as $i => $c) {
		pg_assert_same(array('card shadow ' . $c[2], $c[1], true), $res[$i], $c[0] . ' ' . $c[1]);
	}
}

// Each corner as the browser applies the classes: Bootstrap 5.3 prints
// rounded, then -top, -end, -bottom, -start, so a later one wins where two
// set the same corner.
function test_corner_box_reads_each_corner_as_the_browser()
{
	$res = corner_box_test_node(
		"out = [\n"
		. "  R.read('').corners,\n"
		. "  R.read('rounded-3').corners,\n"
		. "  R.read('rounded-3 rounded-top-0').corners,\n"
		. "  R.read('rounded-start-5 rounded-top-1 rounded-end-2 rounded-bottom-4').corners,\n"
		. "  R.read('rounded').corners,\n"
		. "  R.read('rounded-pill rounded-end-circle').corners,\n"
		. "  R.read('rounded-1 rounded-top-2'),\n"
		. ']'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('ts' => '', 'te' => '', 'be' => '', 'bs' => ''), $res[0], 'no class: every corner its default');
	pg_assert_same(array('ts' => '3', 'te' => '3', 'be' => '3', 'bs' => '3'), $res[1], 'rounded-3: all four');
	pg_assert_same(array('ts' => '0', 'te' => '0', 'be' => '3', 'bs' => '3'), $res[2], 'the top over all');
	pg_assert_same(array('ts' => '5', 'te' => '2', 'be' => '4', 'bs' => '5'), $res[3], 'start over top, end over top, bottom over end, start over bottom');
	pg_assert_same(array('ts' => '2', 'te' => '2', 'be' => '2', 'bs' => '2'), $res[4], 'the bare rounded is the scale\'s 2');
	pg_assert_same(array('ts' => 'pill', 'te' => 'circle', 'be' => 'circle', 'bs' => 'pill'), $res[5], 'circle and pill a page holds are read');

	pg_assert_same(array('value' => '1', 'here' => true), $res[6]['all'], 'the all value');
	pg_assert_same(array('value' => '2', 'here' => true), $res[6]['t'], 'a side set by its class');
	pg_assert_same(array('value' => '1', 'here' => false), $res[6]['e'], 'a side without its class shows all\'s value, grey');
}

// All four at once: Alt from any side, or the centre, writes the one
// all-corners class; Alt also takes away the side classes. Shift writes the
// opposite side too.
function test_corner_box_shift_and_alt()
{
	$res = corner_box_test_node(
		"var w = function (list, side, val, shift, alt) { var to = R.sides(side, shift, alt); return R.write(list, to.sides, val, to.all); };\n"
		. "out = [\n"
		. "  w('card', 't', '3', true, false),\n"
		. "  w('card', 'e', '2', true, false),\n"
		. "  w('card rounded-top-1 rounded-end-2 rounded-1', 'b', '4', false, true),\n"
		. "  w('card rounded-top-1 rounded-1', 'all', '4', false, false),\n"
		. "  w('card rounded-top-1 rounded-1', 'all', 'pill', false, true),\n"
		. "  w('card rounded rounded-top', 't', '3', false, false),\n"
		. "  w('card rounded', 'all', '', false, false),\n"
		. "  w('card rounded-3 rounded-top-1', 't', '', false, false),\n"
		. "  w('card rounded-3', 't', '', false, false),\n"
		. "  w('card', 't', 'pill', false, false),\n"
		. "  w(' card  rounded-2 ', 'all', '2', false, false),\n"
		. "  [R.value('3', 't'), R.value('pill', 't'), R.value('Pill', 'all'), R.value('daire', 'all'), R.value('', 't'), R.value('7', 'all')],\n"
		. "  [R.step('', 1), R.step('5', 1), R.step('3', -1), R.step('0', -1), R.step('pill', -1), R.step('circle', 1)],\n"
		. ']'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same('card rounded-top-3 rounded-bottom-3', $res[0], 'Shift: the top and the bottom');
	pg_assert_same('card rounded-end-2 rounded-start-2', $res[1], 'Shift: the end and the start');
	pg_assert_same('card rounded-4', $res[2], 'Alt: one all-corners class in place of every radius class');
	pg_assert_same('card rounded-top-1 rounded-4', $res[3], 'the centre changes only the all-corners class');
	pg_assert_same('card rounded-pill', $res[4], 'Alt on the centre: pill on all four');
	pg_assert_same('card rounded rounded-top-3', $res[5], 'the bare side class is replaced, the bare all class stays');
	pg_assert_same('card', $res[6], 'clearing all takes the bare rounded too');
	pg_assert_same('card rounded-3', $res[7], 'clearing a side takes its class only');
	pg_assert_same('card rounded-3', $res[8], 'a side shown from all has nothing of its own to clear');
	pg_assert_same('card', $res[9], 'a side takes no pill: nothing is written');
	pg_assert_same(' card  rounded-2 ', $res[10], 'nothing changed: the list as it was (K1)');
	pg_assert_same(array('3', null, 'pill', 'circle', '', null), $res[11], 'typed values');
	pg_assert_same(array('0', '5', '2', '0', '5', 'circle'), $res[12], 'the steps of a drag or an arrow key');
}

// The corners are drawn by the spacing part, from their own module, in
// Style › Border and corner; today's radius rows stay there, hidden.
function test_corner_box_is_in_the_spacing_part()
{
	$bundles = pg_js_bundles();
	pg_assert_same(array('assets/js/pg_spacing_box.js', 'assets/js/pg_corner_box.js', 'assets/js/style_designer_spacing.js'), $bundles['assets/js/style_designer_spacing.min.js'], 'both modules, then the part');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_sdPartCall('spacing', 'corners', [host])", $core, 'the core draws the corners through the part');
	pg_assert_contains('<div class="sd-spb-host sd-rdb-host sd-bm-wrap" data-sd-rdbox></div>', $core, 'the section holds the drawing\'s place');
	pg_assert_contains('<div class="sd-rd-rows" hidden>', $core, 'today\'s radius rows stay, hidden');

	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_spacing.js');
	pg_assert_contains('corners: corners', $part, 'the part draws the corners');
}
