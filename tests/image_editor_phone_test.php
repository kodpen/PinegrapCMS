<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's rows on a phone: when the sheet holds them
 * (M.phoneRows()), the folded sounds' line (M.phoneSoundLine()), Split in
 * the selection bar (M.actionList()), the part's place in the bundle, its
 * labels and its stylesheet. The model runs under Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_phone_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiephone');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var names = function (s) { return M.actionList(s).map(function (a) { return (a.off ? "-" : "") + a.name; }); };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The rows show once a shown clip is in the document; a hidden clip, other
// layers or nothing leave the panel's fields.
function test_image_editor_phone_rows()
{
	$r = image_editor_phone_node(
		'console.log(JSON.stringify(['
		. ' M.phoneRows([{ type: "text" }, { type: "video" }]),'
		. ' M.phoneRows([{ type: "video", hidden: true }]),'
		. ' M.phoneRows([{ type: "text" }, { type: "sticker" }]),'
		. ' M.phoneRows([]), M.phoneRows(null), M.phoneRows([null])'
		. ']));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(array(true, false, false, false, false, false), $r, 'only a shown clip brings the rows');
}

// "Sound · 1 row" for one, "Sound · {n} rows" otherwise, the count whole
// and never below 0.
function test_image_editor_phone_sound_line()
{
	$r = image_editor_phone_node('console.log(JSON.stringify([1, 2, 0, -3, 2.7, "x"].map(M.phoneSoundLine)));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(array('key' => 'phoneSoundsOne', 'n' => 1), $r[0], 'one row');
	pg_assert_same(array('key' => 'phoneSounds', 'n' => 2), $r[1], 'two rows');
	pg_assert_same(array(0, 0, 2, 0), array_map(function ($x) { return $x['n']; }, array_slice($r, 2)), 'a whole count from 0');
}

// One clip in the Animate tool: Split before Duplicate and Delete, off when
// the clip is locked; never for the picture or an area.
function test_image_editor_phone_split_in_bar()
{
	$r = image_editor_phone_node(
		'console.log(JSON.stringify({'
		. ' clip: names({ layers: 1, free: 1, split: true, sheet: false }),'
		. ' closed: names({ layers: 1, free: 1, split: true, sheet: true }),'
		. ' locked: names({ layers: 1, free: 0, split: true, sheet: false }),'
		. ' none: names({ layers: 1, free: 1, sheet: false }),'
		. ' picture: names({ layers: 0, picture: true, split: true, sheet: false })'
		. '}));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(array('split', 'duplicate', 'remove'), $r['clip'], 'Split joins Duplicate and Delete');
	pg_assert_same(array('options', 'split', 'duplicate', 'remove'), $r['closed'], 'after Options');
	pg_assert_same(array('-split', 'duplicate', '-remove'), $r['locked'], 'a locked clip is not split');
	pg_assert_same(array('duplicate', 'remove'), $r['none'], 'no Split for other layers');
	pg_assert_same(array('lockPicture', 'removePicture'), $r['picture'], 'nor for the picture');
}

// Bundled after the clip, sound and upload parts, with its labels; the bar draws Split through the clip
// part; the rows' rules are in the stylesheet.
function test_image_editor_phone_part()
{
	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];

	$at = array_search('assets/js/pg_image_editor_phone.js', $files, true);
	pg_assert_true(($at !== false) && ($files[$at - 1] === 'assets/js/pg_image_editor_upload.js'), 'bundled after the upload part');
	pg_assert_true(in_array('phone', pg_image_editor_parts(), true), 'a part with its strings');

	$own = pg_image_editor_strings_phone();
	pg_assert_true(isset($own['phoneSounds'], $own['phoneSoundsOne'], $own['actSplit']), 'its labels');
	pg_assert_true(strpos($own['phoneSounds'], '{n}') !== false, 'the count in the line');

	$acts = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_actions.js');
	pg_assert_true(strpos($acts, "split: ['scissors', 'actSplit', function () { this.clipSplitAt(") !== false, 'Split calls the clip part');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_phone.js');
	pg_assert_true(strpos($js, "var PHONE = '(max-width: 599.98px)';") !== false, 'a phone held upright, as the motion part');

	foreach (array('trackDown', 'clipDown', 'soundDown') as $down) {
		pg_assert_true(strpos($js, "'" . $down . "'") !== false, 'the sheet is kept under a press: ' . $down);
	}

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_image_editor.src.css');

	foreach (array('.pgie-phrows .pgie-trrow {', '.pgie-phrows .pgie-trgrip::after {', '.pgie .pgie-phfold {', '.pgie-phrows .pgie-trrow.pgie-cliprow .pgie-trlane {') as $rule) {
		pg_assert_true(strpos($css, $rule) !== false, 'the rule ' . $rule);
	}
}
