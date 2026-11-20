<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The spacing box of the designer's Style › Spacing section
 * (assets/js/pg_spacing_box.js, window.PGSpacingBox): a side, a screen size
 * and a value mapped to the class today's rows write, both ways; Shift and
 * Alt; "auto"; a value read back from a class list saved by today's rows.
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

// Runs `$code` in Node with window.PGSpacingBox loaded as B; its `out` comes
// back decoded. Null when Node cannot run.
function spacing_box_test_node($code)
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
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_spacing_box.js') . "\n"
		. "var B = window.PGSpacingBox, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgspb');

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

// What today's row of a side writes at a size: its select's data-util prefix
// (mkSpRow(): 'mt-', 'mt-md-'…) and the value.
function spacing_box_test_today($kind, $side, $size, $value)
{
	$letter = array('t' => 't', 'b' => 'b', 's' => 's', 'e' => 'e', 'all' => '');
	return $kind . $letter[$side] . '-' . ($size === 'xs' ? '' : $size . '-') . $value;
}

// Every side of both boxes at every size and every value is written as the
// class today's row writes, and read back from it as set at that size.
function test_spacing_box_writes_todays_class_for_every_side_and_size()
{
	$cases = array();

	foreach (array('m', 'p') as $kind) {
		foreach (array('t', 'b', 's', 'e') as $side) {
			foreach (array('xs', 'sm', 'md', 'lg', 'xl', 'xxl') as $size) {
				$values = array('0', '1', '2', '3', '4', '5');

				if ($kind === 'm') {
					$values[] = 'auto';
				}

				foreach ($values as $value) {
					$cases[] = array($kind, $side, $size, $value);
				}
			}
		}
	}

	$res = spacing_box_test_node('var cases = ' . json_encode($cases) . ';'
		. 'out = cases.map(function (c) {'
		. ' var w = B.write("", c[0], c[2], [c[1]], c[3], false);'
		. ' var r = B.read(w, c[0], c[2])[c[1]];'
		. ' return [w, B.cls(c[0], c[1], c[2], c[3]), r.value, r.here, B.parse(w)]; });');

	if ($res === null) {
		return;
	}

	foreach ($cases as $i => $c) {
		$today = spacing_box_test_today($c[0], $c[1], $c[2], $c[3]);
		pg_assert_same($today, $res[$i][0], implode(' ', $c) . ' writes today\'s class');
		pg_assert_same($today, $res[$i][1], implode(' ', $c) . ': cls() names it');
		pg_assert_same($c[3], $res[$i][2], $today . ' reads back its value');
		pg_assert_true($res[$i][3], $today . ' reads as set at its size');
		pg_assert_same(array('kind' => $c[0], 'side' => $c[1], 'size' => $c[2], 'value' => $c[3]), $res[$i][4], $today . ' parses back');
	}
}

// Padding has no "auto" (today's padding rows offer 0 to 5); a typed value is
// 0 to 5, "auto" (or the drawing's "oto") on margins, empty for none.
function test_spacing_box_auto_only_on_margins()
{
	$res = spacing_box_test_node('out = ['
		. ' B.value("auto", "m"), B.value("Oto", "m"), B.value(" 3 ", "m"), B.value("", "m"), B.value("6", "m"), B.value("-1", "m"),'
		. ' B.value("auto", "p"), B.value("5", "p"), B.value("x", "p"),'
		. ' B.parse("p-auto"), B.parse("pt-md-auto"), B.parse("m-auto"), B.parse("ms-lg-auto"), B.parse("mt-6"), B.parse("mt-n2"), B.parse("mx-auto"), B.parse("my-md-0"),'
		. ' B.write("pt-2", "p", "xs", ["t"], "auto", false)'
		. '];');

	if ($res === null) {
		return;
	}

	pg_assert_same('auto', $res[0], '"auto" on a margin');
	pg_assert_same('auto', $res[1], 'the drawing\'s "oto" is auto');
	pg_assert_same('3', $res[2], 'a number is trimmed');
	pg_assert_same('', $res[3], 'empty is no value');
	pg_assert_same(null, $res[4], '6 is outside the shipped scale');
	pg_assert_same(null, $res[5], 'a negative value is not typed');
	pg_assert_same(null, $res[6], 'padding has no auto');
	pg_assert_same('5', $res[7], 'padding 5');
	pg_assert_same(null, $res[8], 'a word is no value');
	pg_assert_same(null, $res[9], 'p-auto is no spacing class of the box');
	pg_assert_same(null, $res[10], 'pt-md-auto is none either');
	pg_assert_same(array('kind' => 'm', 'side' => 'all', 'size' => 'xs', 'value' => 'auto'), $res[11], 'm-auto');
	pg_assert_same(array('kind' => 'm', 'side' => 's', 'size' => 'lg', 'value' => 'auto'), $res[12], 'ms-lg-auto');
	pg_assert_same(null, $res[13], 'mt-6 is not a Bootstrap class');
	pg_assert_same(array('kind' => 'm', 'side' => 't', 'size' => 'xs', 'value' => 'n2'), $res[14], 'a negative margin is read');
	pg_assert_same(array('kind' => 'm', 'side' => 'x', 'size' => 'xs', 'value' => 'auto'), $res[15], 'mx-auto is read');
	pg_assert_same(array('kind' => 'm', 'side' => 'y', 'size' => 'md', 'value' => '0'), $res[16], 'my-md-0 is read');
	pg_assert_same('pt-2', $res[17], 'auto is never written on padding');
}

// Shift writes the opposite side too, Alt all four as the one class today's
// "Padding" / "Margin" row writes; clearing with Alt takes every class of
// that size away.
function test_spacing_box_shift_and_alt()
{
	$res = spacing_box_test_node('out = ['
		. ' B.sides("t", true, false), B.sides("s", true, false), B.sides("b", false, false), B.sides("e", false, true), B.sides("e", true, true),'
		. ' B.write("card mt-2", "m", "xs", B.sides("t", true, false).sides, "3", false),'
		. ' B.write("card ps-md-1", "p", "md", B.sides("s", true, false).sides, "4", false),'
		. ' B.write("card mt-2 mb-1 ms-auto mt-md-5", "m", "xs", B.sides("t", false, true).sides, "3", true),'
		. ' B.write("pt-1 pb-md-2 p-md-1 p-lg-2", "p", "md", B.sides("t", false, true).sides, "", true),'
		. ' B.write("m-2 px-1", "m", "xs", ["t", "b", "s", "e"], "auto", true)'
		. '];');

	if ($res === null) {
		return;
	}

	pg_assert_same(array('sides' => array('b'), 'all' => false), $res[2], 'a plain change is one side');
	pg_assert_same(array('sides' => array('t', 'b'), 'all' => false), $res[0], 'Shift on the top: top and bottom');
	pg_assert_same(array('sides' => array('s', 'e'), 'all' => false), $res[1], 'Shift on the start: start and end');
	pg_assert_same(array('sides' => array('t', 'e', 'b', 's'), 'all' => true), $res[3], 'Alt: all four');
	pg_assert_same(array('sides' => array('t', 'e', 'b', 's'), 'all' => true), $res[4], 'Alt wins over Shift');
	pg_assert_same('card mt-3 mb-3', $res[5], 'Shift writes both side classes');
	pg_assert_same('card ps-md-4 pe-md-4', $res[6], 'Shift at a size');
	pg_assert_same('card mt-md-5 m-3', $res[7], 'Alt writes m-3 in place of the side classes of its size, other sizes kept');
	pg_assert_same('pt-1 p-lg-2', $res[8], 'Alt clearing a size takes all its classes');
	pg_assert_same('px-1 m-auto', $res[9], 'Alt on margins leaves the padding');
}

// A value is read through the sizes as the browser applies the classes: a
// side class over an axis class over the all-sides class, a larger size over
// a smaller one; a value from a smaller size is inherited (grey), not set
// here (blue).
function test_spacing_box_reads_a_tree_saved_by_todays_rows()
{
	// A class list the ten rows of today write: Padding 3, Padding Top MD 5,
	// Margin Bottom 4, Margin Start auto, Margin End auto, and a theme class.
	$saved = 'container p-3 pt-md-5 mb-4 ms-auto me-auto shadow';
	$res = spacing_box_test_node('var c = ' . json_encode($saved) . ';'
		. 'out = [B.read(c, "p", "xs"), B.read(c, "p", "md"), B.read(c, "p", "xl"), B.read(c, "m", "xs"), B.read(c, "m", "lg"),'
		. ' B.read("mx-auto my-md-2 mt-lg-0", "m", "lg"), B.read("", "p", "xs").t, B.held(c), B.held("mx-auto py-xl-2 text-center")];');

	if ($res === null) {
		return;
	}

	$set = function ($v, $size) { return array('value' => $v, 'here' => true, 'size' => $size); };
	$inh = function ($v, $size) { return array('value' => $v, 'here' => false, 'size' => $size); };

	pg_assert_same(array('t' => $set('3', 'xs'), 'e' => $set('3', 'xs'), 'b' => $set('3', 'xs'), 's' => $set('3', 'xs')), $res[0], 'Padding 3 sets all four sides at XS');
	pg_assert_same(array('t' => $set('5', 'md'), 'e' => $inh('3', 'xs'), 'b' => $inh('3', 'xs'), 's' => $inh('3', 'xs')), $res[1], 'at MD the top is set, the rest inherited');
	pg_assert_same(array('t' => $inh('5', 'md'), 'e' => $inh('3', 'xs'), 'b' => $inh('3', 'xs'), 's' => $inh('3', 'xs')), $res[2], 'at XL everything is inherited');
	pg_assert_same(array('t' => $inh('', ''), 'e' => $set('auto', 'xs'), 'b' => $set('4', 'xs'), 's' => $set('auto', 'xs')), $res[3], 'margins at XS: none on top, auto at the ends');
	pg_assert_same(array('t' => $inh('', ''), 'e' => $inh('auto', 'xs'), 'b' => $inh('4', 'xs'), 's' => $inh('auto', 'xs')), $res[4], 'margins at LG inherited');
	pg_assert_same(array('t' => $set('0', 'lg'), 'e' => $inh('auto', 'xs'), 'b' => $inh('2', 'md'), 's' => $inh('auto', 'xs')), $res[5], 'axis classes count for their two sides');
	pg_assert_same($inh('', ''), $res[6], 'nothing set reads empty');
	pg_assert_same(array('xs', 'md'), $res[7], 'the sizes holding a value');
	pg_assert_same(array('xs', 'xl'), $res[8], 'axis classes hold their size too');
}

// Clearing a side whose value comes from the all-sides or an axis class of
// that size writes the other sides as today's side classes, so only the side
// cleared changes; a class of another size or kind is never touched, and a
// list without a change is given back as it was.
function test_spacing_box_clear_and_keep()
{
	$res = spacing_box_test_node('out = ['
		. ' B.write("hero m-3", "m", "xs", ["t"], "", false),'
		. ' B.write("hero my-2 m-1", "m", "xs", ["b"], "", false),'
		. ' B.write("hero mx-auto m-1", "m", "xs", ["t"], "", false),'
		. ' B.write("hero p-3", "p", "xs", ["t", "b"], "", false),'
		. ' B.write("hero mt-2 mt-md-3 pt-2", "m", "xs", ["t"], "", false),'
		. ' B.write("hero mt-n2", "m", "xs", ["t"], "1", false),'
		. ' B.write("  hero   mt-2 ", "m", "md", ["t"], "", false),'
		. ' B.step("", 1, "m"), B.step("3", 1, "m"), B.step("5", 1, "m"), B.step("5", 1, "p"), B.step("auto", -1, "m"), B.step("0", -2, "p"), B.step("2", 2, "p")'
		. '];');

	if ($res === null) {
		return;
	}

	pg_assert_same('hero me-3 mb-3 ms-3', $res[0], 'm-3 without its top: the three other sides');
	pg_assert_same('hero mt-2 me-1 ms-1', $res[1], 'my-2 m-1 without the bottom: the top keeps my-2, the ends m-1');
	pg_assert_same('hero mx-auto mb-1', $res[2], 'mx-auto stays; the bottom keeps m-1\'s value');
	pg_assert_same('hero pe-3 ps-3', $res[3], 'Shift clearing p-3 leaves the ends');
	pg_assert_same('hero mt-md-3 pt-2', $res[4], 'clearing XS keeps the MD class and the padding');
	pg_assert_same('hero mt-1', $res[5], 'a negative margin of the side is replaced');
	pg_assert_same('  hero   mt-2 ', $res[6], 'nothing to clear: the list as it was');
	pg_assert_same(array('0', '4', 'auto', '5', '5', '0', '4'), array_slice($res, 7), 'the steps of a drag or an arrow key');
}

// The part draws the box from the module and is a part of its own, loaded
// when Style › Spacing is first shown; the core keeps today's rows (hidden)
// and hands the part a host.
function test_spacing_box_is_a_part()
{
	$parts = pg_designer_parts();
	$bundles = pg_js_bundles();
	pg_assert_same('assets/js/style_designer_spacing.min.js', isset($parts['spacing']) ? $parts['spacing'] : null, 'spacing is a part');
	pg_assert_same('assets/js/pg_spacing_box.js', $bundles['assets/js/style_designer_spacing.min.js'][0], 'the module first');
	pg_assert_same('assets/js/style_designer_spacing.js', end($bundles['assets/js/style_designer_spacing.min.js']), 'the part last');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_sdPartCall('spacing', 'draw', [host])", $core, 'the core draws the box through the part');
	pg_assert_contains('<div class="sd-spb-host sd-bm-wrap" data-sd-spbox></div>', $core, 'the section holds the box\'s place');
	pg_assert_contains('<div class="sd-sp-section sd-sp-rows" hidden>', $core, 'today\'s rows stay, hidden');
	pg_assert_contains('_sdSpBoxBind([selectedNode]);', $core, 'the single element\'s panel binds it');
	pg_assert_contains('_sdSpBoxBind(nodes);', $core, 'the several-element panel binds it');
}
