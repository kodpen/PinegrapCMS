<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's rail groups (assets/js/pg_image_editor_model.js):
 * the photo tools and the vector tools as one rail item each, the mode a
 * group opens and the letters its modes keep. They run the model under Node
 * and are skipped where there is no Node.
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
function image_editor_rail_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgierail');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The photo rail: Select, Photo, Canvas, Draw, Text, Sticker, Vector,
// Animate; the canvas rail has one photo tool, so Frame stays its own item.
function test_image_editor_rail_profiles()
{
	$r = image_editor_rail_node(
		'function ids(p) { return M.railItems(M.resolveTools(p).list).map(function (i) { return i.id; }); }' . "\n"
		. 'var photo = M.railItems(M.resolveTools("photo").list);' . "\n"
		. 'console.log(JSON.stringify({ photo: ids("photo"), canvas: ids("canvas"),'
		. ' photoModes: photo[1].modes, vectorModes: photo[6].modes,'
		. ' frame: M.railItems(["select", "frame", "text"]) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('select', 'photo', 'artboard', 'draw', 'text', 'sticker', 'vectors', 'motion'), $r['photo'], 'the photo rail has one item per group');
	pg_assert_same(array('select', 'artboard', 'draw', 'text', 'sticker', 'vectors', 'frame', 'motion'), $r['canvas'], 'a group with one tool of the list is that tool');
	pg_assert_same(array('crop', 'adjust', 'redact', 'area', 'frame'), $r['photoModes'], 'Photo opens the crop, adjust, redact, area and frame modes');
	pg_assert_same(array('vector', 'scissors', 'builder', 'warp', 'puppet'), $r['vectorModes'], 'Vector opens the pen, scissors, shape builder, warp and free deformation in the group order');
	pg_assert_same(array(array('id' => 'select', 'modes' => array('select')), array('id' => 'frame', 'modes' => array('frame')), array('id' => 'text', 'modes' => array('text'))), $r['frame'], 'a single photo tool is its own item');
}

// A group opens its first mode the first time and the mode used last after;
// every tool finds its item, and the modes keep their letters.
function test_image_editor_rail_open_and_keys()
{
	$r = image_editor_rail_node(
		'var list = M.resolveTools("photo").list, items = M.railItems(list);' . "\n"
		. 'var photo = M.railItemOf(items, "adjust");' . "\n"
		. 'console.log(JSON.stringify({ first: M.railOpen(photo, {}), last: M.railOpen(photo, { photo: "redact" }),'
		. ' foreign: M.railOpen(photo, { photo: "warp" }), item: photo.id, none: M.railItemOf(items, "export"),'
		. ' group: M.railGroup("scissors"), single: M.railGroup("text"),'
		. ' crop: M.toolKey("crop", list), adjust: M.toolKey("adjust", list), keyCrop: M.toolForKey("c", list) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('crop', $r['first'], 'Photo opens Crop the first time');
	pg_assert_same('redact', $r['last'], 'a group opens the mode used last');
	pg_assert_same('crop', $r['foreign'], 'a remembered tool the group lacks is not opened');
	pg_assert_same('photo', $r['item'], 'a mode finds its group item');
	pg_assert_same(null, $r['none'], 'a tool not on the rail has no item');
	pg_assert_same('vectors', $r['group'], 'the scissors are a vector mode');
	pg_assert_same('', $r['single'], 'Text is in no group');
	pg_assert_same('C', $r['crop'], 'Crop keeps its letter in the group');
	pg_assert_true($r['adjust'] !== '', 'Adjust keeps a letter in the group');
	pg_assert_same('crop', $r['keyCrop'], 'the letter still picks the mode');
}
