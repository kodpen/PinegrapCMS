<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's selection bar: which commands M.actionList()
 * (the ACTIONS section of assets/js/pg_image_editor_model.js, run under
 * Node) offers for what is selected, the part's place in the bundle and its
 * labels, and the stylesheet that shows the bar only where the panel is a
 * sheet.
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
function image_editor_actions_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieacts');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		// The names in order, a disabled one marked with a minus.
		. 'var names = function (s) { return M.actionList(s).map(function (a) { return (a.off ? "-" : "") + a.name; }); };' . "\n"
		. 'var all = { erase: true, keep: true, copy: true, cutToLayer: true, adjust: true, redact: true };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Layers first: Options while the sheet is closed, Duplicate, Delete (off
// when every selected layer is locked). Nothing selected: no bar.
function test_image_editor_actions_for_layers()
{
	$r = image_editor_actions_node(
		'console.log(JSON.stringify({'
		. ' none: names({ layers: 0, free: 0, picture: false, area: false, sheet: true, can: all }),'
		. ' closed: names({ layers: 2, free: 2, sheet: true, can: all }),'
		. ' open: names({ layers: 1, free: 1, sheet: false, can: all }),'
		. ' locked: names({ layers: 1, free: 0, sheet: true }),'
		. ' withPicture: names({ layers: 1, free: 1, picture: true, area: true, sheet: false })'
		. '}));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(array(), $r['none'], 'nothing selected: no commands');
	pg_assert_same(array('options', 'duplicate', 'remove'), $r['closed'], 'the sheet closed: Options first');
	pg_assert_same(array('duplicate', 'remove'), $r['open'], 'the sheet open: no Options');
	pg_assert_same(array('options', 'duplicate', '-remove'), $r['locked'], 'locked layers cannot be deleted');
	pg_assert_same(array('duplicate', 'remove'), $r['withPicture'], 'layers before the picture and an area');
}

// The picture: Lock and Delete. An area with Select area: the area part's
// commands in the panel's order, those the editor has, then Redact (off
// without the redaction part), Invert and Deselect.
function test_image_editor_actions_for_picture_and_area()
{
	$r = image_editor_actions_node(
		'console.log(JSON.stringify({'
		. ' picture: names({ layers: 0, free: 0, picture: true, sheet: true }),'
		. ' area: names({ layers: 0, picture: false, area: true, sheet: true, can: all }),'
		. ' areaOpen: names({ area: true, sheet: false, can: { redact: true } }),'
		. ' noRedact: names({ area: true, sheet: false, can: {} })'
		. '}));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(array('options', 'lockPicture', 'removePicture'), $r['picture'], 'the picture');
	pg_assert_same(array('options', 'erase', 'keep', 'copy', 'cutToLayer', 'adjust', 'redact', 'invert', 'deselect'), $r['area'], 'every area command');
	pg_assert_same(array('redact', 'invert', 'deselect'), $r['areaOpen'], 'only the commands the editor has');
	pg_assert_same(array('-redact', 'invert', 'deselect'), $r['noRedact'], 'Redact off without the redaction part');
}

// The part is bundled, has its labels, and each command it draws has a
// label (most are the owning parts').
function test_image_editor_actions_part()
{
	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];

	pg_assert_true(in_array('assets/js/pg_image_editor_actions.js', $files, true), 'in the editor\'s bundle');
	pg_assert_true(in_array('actions', pg_image_editor_parts(), true), 'a part with its strings');

	$own = pg_image_editor_strings_actions();
	pg_assert_true(isset($own['actBar'], $own['actOptions']), 'the bar\'s own labels');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_actions.js');
	preg_match_all("#^\s*([a-zA-Z]+): \['[a-zA-Z]+', '([a-zA-Z]+)'#m", $js, $m, PREG_SET_ORDER);
	pg_assert_true(count($m) >= 13, 'the commands are listed');

	$strings = pg_image_editor_strings();

	foreach ($m as $command) {
		pg_assert_true(isset($strings[$command[2]]), $command[1] . ' has its label ' . $command[2]);
	}
}

// Hidden by default, shown only in the small-screen layout, where the panel
// is a sheet; on the desktop the panel shows the same buttons.
function test_image_editor_actions_only_on_small_screens()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_image_editor.src.css');

	pg_assert_true(preg_match('/\.pgie-actwrap \{[^}]*display: none;/', $css) === 1, 'hidden by default');
	pg_assert_true(preg_match('/@media \(max-width: 767\.98px\), \(max-height: 499\.98px\) \{\s*\.pgie-actwrap\.on \{\s*display: flex;/', $css) === 1, 'shown where the panel is a sheet');
}
