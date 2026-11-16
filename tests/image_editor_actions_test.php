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

// The picture: Lock, Convert to a layer (where the editor can have the
// server draw the picture) and Delete. An area with Select area: the area part's
// commands in the panel's order, those the editor has, then Redact (off
// without the redaction part), Invert and Deselect.
function test_image_editor_actions_for_picture_and_area()
{
	$r = image_editor_actions_node(
		'console.log(JSON.stringify({'
		. ' picture: names({ layers: 0, free: 0, picture: true, sheet: true }),'
		. ' pictureLayer: names({ layers: 0, free: 0, picture: true, sheet: false, can: { pictureLayer: true } }),'
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
	pg_assert_same(array('lockPicture', 'pictureLayer', 'removePicture'), $r['pictureLayer'], 'Convert to a layer where the editor can draw the picture');
	pg_assert_same(array('options', 'erase', 'keep', 'copy', 'cutToLayer', 'adjust', 'redact', 'invert', 'deselect'), $r['area'], 'every area command');
	pg_assert_same(array('redact', 'invert', 'deselect'), $r['areaOpen'], 'only the commands the editor has');
	pg_assert_same(array('-redact', 'invert', 'deselect'), $r['noRedact'], 'Redact off without the redaction part');
}

// A corner keeps the proportions with a finger or a pen whatever the type;
// with the mouse a picture's does and a shape's does not, Shift doing the
// other. An unknown pointer counts as the mouse.
function test_image_editor_actions_keep_ratio()
{
	$r = image_editor_actions_node(
		'console.log(JSON.stringify(['
		. ' M.keepRatio("touch", false, false), M.keepRatio("touch", true, true), M.keepRatio("pen", false, false),'
		. ' M.keepRatio("mouse", false, false), M.keepRatio("mouse", true, false), M.keepRatio("mouse", false, true), M.keepRatio("mouse", true, true),'
		. ' M.keepRatio("", false, false), M.keepRatio(undefined, true, false)'
		. ']));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(array(true, true, true), array_slice($r, 0, 3), 'a finger or a pen keeps it');
	pg_assert_same(array(false, true, true, false), array_slice($r, 3, 4), 'the mouse: the type, Shift the other way');
	pg_assert_same(array(false, true), array_slice($r, 7, 2), 'an unknown pointer as the mouse');
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

// A chosen deformation pin puts Remove pin first, before Options; Delete
// still removes the layer.
function test_image_editor_actions_chosen_pin()
{
	$r = image_editor_actions_node(
		'console.log(JSON.stringify({ pin: names({ layers: 1, free: 1, pin: true, sheet: true }), open: names({ layers: 1, free: 1, pin: true, sheet: false }),'
		. ' noLayer: names({ layers: 0, free: 0, pin: true, sheet: true }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('removePin', 'options', 'duplicate', 'remove'), $r['pin'], 'Remove pin first');
	pg_assert_same(array('removePin', 'duplicate', 'remove'), $r['open'], 'with the sheet open');
	pg_assert_same(array(), $r['noLayer'], 'no layer, no bar');
}


// The bar over the object (where the panel is not a sheet): the type's own
// command, Motion in Animate, Duplicate, Delete, Lock, More. A locked layer:
// Unlock and More; several layers: no type command, no Motion; a redaction
// never gets Motion; the picture and an area: no bar (their commands stay
// in the panel). The phone's lists do not change (the tests above).
function test_image_editor_actions_over_the_object()
{
	$r = image_editor_actions_node(
		'var o = function (s) { s.place = "object"; return names(s); };' . "\n"
		. 'console.log(JSON.stringify({'
		. ' text: o({ layers: 1, free: 1, kind: "text" }),'
		. ' textAnimate: o({ layers: 1, free: 1, kind: "text", animate: true }),'
		. ' videoAnimate: o({ layers: 1, free: 1, kind: "video", animate: true, split: true }),'
		. ' video: o({ layers: 1, free: 1, kind: "video" }),'
		. ' several: o({ layers: 3, free: 3, kind: "text", animate: true }),'
		. ' someLocked: o({ layers: 2, free: 1 }),'
		. ' locked: o({ layers: 1, free: 0, kind: "text", animate: true }),'
		. ' redact: o({ layers: 1, free: 1, kind: "redact", animate: true }),'
		. ' picture: o({ layers: 0, free: 0, picture: true, sheet: false }),'
		. ' area: o({ layers: 0, area: true, sheet: false, can: all }),'
		. ' none: o({ layers: 0 }),'
		. ' phone: names({ layers: 1, free: 1, kind: "text", animate: true, split: true, sheet: true })'
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('editText', 'duplicate', 'remove', 'lock', 'more'), $r['text'], 'one text: Edit text first');
	pg_assert_same(array('editText', 'motion', 'duplicate', 'remove', 'lock', 'more'), $r['textAnimate'], 'in Animate: Motion after the type\'s command');
	pg_assert_same(array('split', 'motion', 'duplicate', 'remove', 'lock', 'more'), $r['videoAnimate'], 'one video in Animate: Split, Motion');
	pg_assert_same(array('duplicate', 'remove', 'lock', 'more'), $r['video'], 'a video outside Animate: no Split');
	pg_assert_same(array('duplicate', 'remove', 'lock', 'more'), $r['several'], 'several layers: Duplicate, Delete, Lock, More');
	pg_assert_same(array('duplicate', 'remove', 'lock', 'more'), $r['someLocked'], 'some locked: Lock locks the rest');
	pg_assert_same(array('unlock', 'more'), $r['locked'], 'a locked layer: Unlock and More');
	pg_assert_same(array('duplicate', 'remove', 'lock', 'more'), $r['redact'], 'a redaction never gets Motion');
	pg_assert_same(array(), $r['picture'], 'the picture: no bar over it');
	pg_assert_same(array(), $r['area'], 'an area: no bar over it');
	pg_assert_same(array(), $r['none'], 'nothing selected: no bar');
	pg_assert_same(array('options', 'split', 'duplicate', 'remove'), $r['phone'], 'the phone\'s list unchanged');
}

// The bar's place: centred above the box, clear of the turning handle;
// below it at the stage's top edge; kept inside the stage at the sides and
// above the play bar and the tracks (stage.bottom).
function test_image_editor_actions_bar_place()
{
	$r = image_editor_actions_node(
		'var bar = { w: 200, h: 40 }, stage = { w: 1000, h: 600, bottom: 0 }, p = M.actBarPlace;' . "\n"
		. 'console.log(JSON.stringify({'
		. ' middle: p({ x: 400, y: 300, w: 200, h: 100 }, bar, stage),'
		. ' top: p({ x: 400, y: 20, w: 200, h: 100 }, bar, stage),'
		. ' left: p({ x: -50, y: 300, w: 100, h: 100 }, bar, stage),'
		. ' right: p({ x: 950, y: 300, w: 100, h: 100 }, bar, stage),'
		. ' bottom: p({ x: 400, y: 20, w: 200, h: 560 }, bar, { w: 1000, h: 600, bottom: 120 }),'
		. ' tall: p({ x: 400, y: -100, w: 200, h: 900 }, bar, stage),'
		. ' narrow: p({ x: 0, y: 300, w: 50, h: 50 }, bar, { w: 150, h: 600 })'
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('x' => 400, 'y' => 221, 'below' => false), $r['middle'], 'above: 31 px of turning handle and 8 px clear');
	pg_assert_same(array('x' => 400, 'y' => 133, 'below' => true), $r['top'], 'no room above: 8 px below the box\'s handles');
	pg_assert_same(8, $r['left']['x'], 'kept inside on the left');
	pg_assert_same(792, $r['right']['x'], 'kept inside on the right');
	pg_assert_same(432, $r['bottom']['y'], 'above the play bar and the tracks');
	pg_assert_same(552, $r['tall']['y'], 'a box taller than the stage: inside the stage');
	pg_assert_same(8, $r['narrow']['x'], 'a stage narrower than the bar: its left edge');
}

// The stylesheet: the bar over the object shown where the panel is not a
// sheet (a class only the script sets there), placed by its two variables;
// the phone's rule kept.
function test_image_editor_actions_object_bar_rule()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_image_editor.src.css');

	pg_assert_true(preg_match('/\.pgie-actwrap\.pgie-actobj \{[^}]*top: var\(--pgie-act-y[^}]*left: var\(--pgie-act-x/', $css) === 1, 'placed by the script');
	pg_assert_true(preg_match('/\n\.pgie-actwrap\.pgie-actobj\.on \{\s*display: flex;/', $css) === 1, 'shown outside the small-screen rule');
	pg_assert_true(preg_match('/@media \(max-width: 767\.98px\), \(max-height: 499\.98px\) \{\s*\.pgie-actwrap\.on \{\s*display: flex;/', $css) === 1, 'the phone\'s rule kept');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_actions.js');
	pg_assert_contains("'(max-width: 767.98px), (max-height: 499.98px)'", $js, 'the script asks the same media as the stylesheet');
	pg_assert_contains('M.actBarPlace(', $js, 'placed by the pure function');
}
