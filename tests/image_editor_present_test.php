<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for presenting a canvas's scenes and their PDF
 * (pg_image_editor_present.js and the PRESENT section of the model, run
 * under Node): a scene letterboxed on the screen, the keys and gestures,
 * moving between scenes, a scene's moment with the transition it arrives
 * by; the PDF writer read back object by object; PDF in Save animation's
 * formats only with scenes; the part's labels.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and a canvas as board(); answers its
// JSON, null without Node.
function image_editor_present_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiepresent');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'function board() { return { v: 1, source: null, geometry: { rot: 0 }, artboard: { width: 1920, height: 1080, background: "#ffffff" }, layers: [{ id: "l0", type: "text" }], motion: { duration: 4000, loop: 0, base: "none" } }; }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A wide scene on a tall screen sits in a band across its middle; a tall
// one on a wide screen in a column.
function test_image_editor_present_fit()
{
	$r = image_editor_present_node('console.log(JSON.stringify([M.presentFit(1920, 1080, 390, 844), M.presentFit(1080, 1920, 1280, 800), M.presentFit(100, 100, 100, 100)]));');

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('x' => 0, 'y' => 313, 'w' => 390, 'h' => 219), $r[0], 'letterboxed');
	pg_assert_same(array('x' => 415, 'y' => 0, 'w' => 450, 'h' => 800), $r[1], 'pillarboxed');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 100, 'h' => 100), $r[2], 'the same shape fills it');
}

// The keys, the gestures and where they lead: no wrap at either end,
// Escape closes.
function test_image_editor_present_moves()
{
	$r = image_editor_present_node(
		'var keys = {}; [" ", "ArrowRight", "ArrowLeft", "PageUp", "Home", "End", "Escape", "a"].forEach(function (k) { keys[k] = M.presentKey(k); });' . "\n"
		. 'console.log(JSON.stringify({ keys: keys, gestures: [M.presentGesture(3, -4), M.presentGesture(-80, 10), M.presentGesture(90, -20), M.presentGesture(30, 80)],'
		. ' go: [M.presentGo(0, 3, "next"), M.presentGo(2, 3, "next"), M.presentGo(0, 3, "prev"), M.presentGo(1, 3, "last"), M.presentGo(2, 3, "first"), M.presentGo(1, 3, "close"), M.presentGo(1, 3, null)] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(' ' => 'next', 'ArrowRight' => 'next', 'ArrowLeft' => 'prev', 'PageUp' => 'prev', 'Home' => 'first', 'End' => 'last', 'Escape' => 'close', 'a' => null), $r['keys'], 'keys');
	pg_assert_same(array('tap', 'next', 'prev', null), $r['gestures'], 'a tap, a swipe left and right, a swipe down is none');
	pg_assert_same(array(1, 2, 0, 2, 0, -1, 1), $r['go'], 'moves stay inside the scenes');
}

// Arriving forward at scene 2 the fade of scene 1 plays at the whole
// animation's moment; going back, or past the fade, the scene alone,
// held at its end.
function test_image_editor_present_time()
{
	$r = image_editor_present_node(
		'var d = board(); M.sceneAdd(d, false); M.sceneSet(d, "s1", { transition: { kind: "fade", ms: 500 } });' . "\n"
		. 'console.log(JSON.stringify([M.presentTime(d, 1, 200, true), M.presentTime(d, 1, 200, false), M.presentTime(d, 1, 9000, true), M.presentTime(d, 0, 100, true)]));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('whole' => 4200, 'local' => 200), $r[0], 'over the fade: the whole moment');
	pg_assert_same(array('whole' => null, 'local' => 200), $r[1], 'backward: the scene alone');
	pg_assert_same(array('whole' => null, 'local' => 3000), $r[2], 'held at its end');
	pg_assert_same(array('whole' => null, 'local' => 100), $r[3], 'the first scene has no way in');
}

// The PDF: a page per picture at its size in points, each JPEG kept byte
// for byte; the cross-reference table points at every object.
function test_image_editor_present_pdf()
{
	if (!function_exists('imagejpeg')) {
		return;
	}

	$jpegs = array();

	foreach (array(array(64, 36), array(40, 40)) as $size) {
		$im = imagecreatetruecolor($size[0], $size[1]);
		imagefill($im, 0, 0, imagecolorallocate($im, 200, 40, 40));
		ob_start();
		imagejpeg($im, null, 80);
		$jpegs[] = ob_get_clean();
		imagedestroy($im);
	}

	$r = image_editor_present_node(
		'var j = ' . json_encode(array_map('base64_encode', $jpegs)) . ';' . "\n"
		. 'var b = M.pdfFile([{ jpeg: new Uint8Array(Buffer.from(j[0], "base64")), w: 64, h: 36 }, { jpeg: new Uint8Array(Buffer.from(j[1], "base64")), w: 40, h: 40 }]);' . "\n"
		. 'console.log(JSON.stringify({ pdf: Buffer.from(b).toString("base64") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	$pdf = base64_decode($r['pdf']);

	pg_assert_same('%PDF-1.4', substr($pdf, 0, 8), 'header');
	pg_assert_same("%%EOF\n", substr($pdf, -6), 'end');
	pg_assert_true(preg_match('~startxref\n(\d+)\n%%EOF\n$~', $pdf, $m) === 1, 'startxref');
	pg_assert_same('xref', substr($pdf, (int) $m[1], 4), 'startxref points at the table');
	pg_assert_true(preg_match('~xref\n0 (\d+)\n0000000000 65535 f \n((?:\d{10} 00000 n \n)+)trailer~', $pdf, $x) === 1, 'the table');
	pg_assert_same(9, (int) $x[1], 'two pages: catalog, pages and three objects each');

	foreach (explode("\n", trim($x[2])) as $i => $line) {
		$at = (int) substr($line, 0, 10);
		pg_assert_same(($i + 1) . ' 0 obj', substr($pdf, $at, strlen(($i + 1) . ' 0 obj')), 'object ' . ($i + 1) . ' where the table says');
	}

	pg_assert_contains('/Kids [3 0 R 6 0 R] /Count 2', $pdf, 'two pages');
	pg_assert_contains('/MediaBox [0 0 48 27]', $pdf, '64 x 36 pixels are 48 x 27 points');
	pg_assert_contains('/MediaBox [0 0 30 30]', $pdf, '40 x 40 pixels are 30 x 30 points');
	pg_assert_contains('/Width 64 /Height 36 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpegs[0]) . " >>\nstream\n" . $jpegs[0] . "\nendstream", $pdf, 'the first JPEG as it is');
	pg_assert_contains("stream\n" . $jpegs[1] . "\nendstream", $pdf, 'the second JPEG as it is');
	pg_assert_contains('q 30 0 0 30 0 0 cm /Im0 Do Q', $pdf, 'the picture fills its page');
	pg_assert_same('pdf', pg_image_editor_animation_type($pdf), 'the save tells it by its bytes');
}

// PDF joins Save animation's formats only with two scenes or more.
function test_image_editor_present_pdf_offered()
{
	$r = image_editor_present_node('console.log(JSON.stringify([M.animFormats({}), M.animFormats({ scenes: true }), M.animSwitch({ edge: 1280, fps: 15 }, "pdf")]));');

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_false(in_array('pdf', $r[0], true), 'not without scenes');
	pg_assert_true(in_array('pdf', $r[1], true), 'with scenes');
	pg_assert_same(array('edge' => 1280, 'fps' => 1, 'format' => 'pdf'), $r[2], 'one page a scene, the size kept');
}

// The part's labels.
function test_image_editor_present_labels()
{
	$labels = pg_image_editor_strings_present();

	foreach (array('present', 'presentTitle', 'presentPrev', 'presentNext', 'presentCount', 'presentLeave', 'animFormat_pdf', 'animPages') as $key) {
		pg_assert_true(isset($labels[$key]) && ($labels[$key] !== ''), $key);
	}

	pg_assert_contains('{n}', $labels['presentCount'], 'the scene\'s number');
	pg_assert_contains('{count}', $labels['presentCount'], 'of how many');
	pg_assert_true(in_array('present', pg_image_editor_parts(), true), 'a part of the editor');
}
