<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the crop tool's output size (assets/js/pg_image_editor_crop.js,
 * run under Node on an editor without a page): the size typed in the panel
 * and the size a crop drag or a ratio carries along keep both sides within
 * the 8000 px a save accepts (pg_image_edit_validate()), at the crop box's
 * ratio.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and a bare editor from fake($w, $h) on a
// picture of that size (no page: the drawing calls do nothing); answers its
// JSON, null without Node.
function image_editor_crop_output_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiecrop');

	if ($file === false) {
		return null;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} };' . "\n"
		. 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . '); window.PgImageEditorModel = window.PgImageEditorModel || M;' . "\n"
		. 'require(' . json_encode($js . 'pg_image_editor.js') . '); require(' . json_encode($js . 'pg_image_editor_crop.js') . ');' . "\n"
		. 'var Editor = window.PgImageEditor.parts.Editor;' . "\n"
		. 'function fake(w, h) {' . "\n"
		. ' var e = Object.create(Editor.prototype), none = function () {};' . "\n"
		. ' e.doc = M.newDocument({ fileId: 1 }); e.doc.source.width = w; e.doc.source.height = h;' . "\n"
		. ' e.opening = M.clone(e.doc); e.history = new M.History(); e.dirty = false; e.cropRatio = 0;' . "\n"
		. ' e.undoBtn = {}; e.redoBtn = {}; e.render = none; e.renderView = none; e.renderMeta = none; e.keepSelection = none; e.setReady = none;' . "\n"
		. ' return e;' . "\n"
		. '}' . "\n";

	file_put_contents($file, $head . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A width typed for a tall crop (or a height for a wide one) that would make
// the other side longer than 8000 px is brought down, the ratio kept; a size
// within the limit stays as typed.
function test_image_editor_crop_output_typed_size_stays_within_8000()
{
	$r = image_editor_crop_output_node(
		'var a = fake(1000, 3000); a.setOutput("w", 4000); var tall = a.doc.geometry.output;' . "\n"
		. 'var b = fake(3000, 1000); b.setOutput("h", 4000); var wide = b.doc.geometry.output;' . "\n"
		. 'var c = fake(1000, 3000); c.setOutput("w", 2000); var within = c.doc.geometry.output;' . "\n"
		. 'console.log(JSON.stringify({ tall: tall, wide: wide, within: within }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), isset($r['error']) ? $r['error'] : '');
	pg_assert_same(array('w' => 2667, 'h' => 8000), $r['tall'], 'a width of 4000 on a 1:3 crop: the height stops at 8000');
	pg_assert_same(array('w' => 8000, 'h' => 2667), $r['wide'], 'a height of 4000 on a 3:1 crop: the width stops at 8000');
	pg_assert_same(array('w' => 2000, 'h' => 6000), $r['within'], 'a size within the limit stays as typed');
}

// A set output size follows the crop box's ratio when a ratio is picked or
// the box is dragged; it never grows past 8000 px on either side.
function test_image_editor_crop_output_follows_the_box_within_8000()
{
	$r = image_editor_crop_output_node(
		'var a = fake(4000, 4000); a.setOutput("w", 6000); a.setRatio(9 / 16); var ratio = a.doc.geometry.output;' . "\n"
		. 'var b = fake(4000, 4000); b.setOutput("w", 6000); b.setRatio(1 / 1); var square = b.doc.geometry.output;' . "\n"
		. 'console.log(JSON.stringify({ ratio: ratio, crop: a.doc.geometry.crop, square: square }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), isset($r['error']) ? $r['error'] : '');
	pg_assert_true(($r['ratio']['w'] <= 8000) && ($r['ratio']['h'] <= 8000), 'a 9:16 ratio after a width of 6000: both sides within 8000, got ' . json_encode($r['ratio']));
	pg_assert_same(8000, $r['ratio']['h'], 'the longer side is 8000');
	pg_assert_same((int) round(8000 * $r['crop']['w'] / $r['crop']['h']), $r['ratio']['w'], 'at the crop box\'s ratio');
	pg_assert_same(array('w' => 6000, 'h' => 6000), $r['square'], 'a size within the limit follows the box as before');
}
