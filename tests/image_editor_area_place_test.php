<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's area selection kept while the picture moves
 * (assets/js/pg_image_editor_area.js, run under Node on an editor without a
 * page): the selection's outline is worked out again when an unlocked
 * picture is moved or resized on its canvas, so the marching ants and
 * Redact the selection follow the picture.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and a bare editor from fake() holding a
// 200 x 100 photo on an unlocked canvas; answers its JSON, null without Node.
function image_editor_area_place_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieplace');

	if ($file === false) {
		return null;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} };' . "\n"
		. 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . '); window.PgImageEditorModel = window.PgImageEditorModel || M;' . "\n"
		. 'require(' . json_encode($js . 'pg_image_editor.js') . '); require(' . json_encode($js . 'pg_image_editor_area.js') . ');' . "\n"
		. 'var Editor = window.PgImageEditor.parts.Editor;' . "\n"
		. 'function fake() {' . "\n"
		. ' var e = Object.create(Editor.prototype), doc = M.newDocument({ fileId: 1 });' . "\n"
		. ' doc.source.width = 200; doc.source.height = 100; e.doc = M.lockPicture(doc, false);' . "\n"
		. ' return e;' . "\n"
		. '}' . "\n"
		. 'var box = function (rings) { var l = M.areaLayer(rings); return [l.x, l.y, l.x + l.w, l.y + l.h].map(Math.round); };' . "\n";

	file_put_contents($file, $head . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// An inverted selection covers the picture: moving or resizing the unlocked
// picture moves its outline with it, not only after the document reopens.
function test_image_editor_area_follows_moved_picture()
{
	$r = image_editor_area_place_node(
		'var e = fake(); e.doc.selection = { ops: [], invert: true, feather: 0 };' . "\n"
		. 'var before = box(e.areaRings());' . "\n"
		. 'e.doc.canvas.picture.x = 50; var moved = box(e.areaRings());' . "\n"
		. 'e.doc.canvas.picture.k = 0.5; var resized = box(e.areaRings());' . "\n"
		. 'console.log(JSON.stringify({ before: before, moved: moved, resized: resized }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 0, 200, 100), $r['before'], 'the whole picture');
	pg_assert_same(array(50, 0, 250, 100), $r['moved'], 'the outline moved with the picture');
	pg_assert_same(array(50, 0, 150, 50), $r['resized'], 'the outline resized with the picture');
}
