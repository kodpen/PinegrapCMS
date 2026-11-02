<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's export settings (the EXPORT section of
 * assets/js/pg_image_editor_model.js, run under Node): a file's own format,
 * the format a save writes, the longest edge, the copy a format change
 * makes, and what a save request carries.
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
function image_editor_export_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieexport');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Formats, sizes, the copy rule and the request.
function test_image_editor_export_model()
{
	$r = image_editor_export_node(
		'var d = M.exportDefaults();' . "\n"
		. 'console.log(JSON.stringify({ own: [M.fileFormat("a/Kapak.JPG"), M.fileFormat("b.png?v=3"), M.fileFormat("c.webp"), M.fileFormat("d.bmp"), M.fileFormat("")],'
		. ' format: [M.exportFormat("keep", "jpeg"), M.exportFormat("webp", "jpeg"), M.exportFormat("bmp", "png")],'
		. ' size: [M.exportSize(2400, 1600, 1920), M.exportSize(1600, 2400, 1080), M.exportSize(800, 600, 1920), M.exportSize(800, 600, 0)],'
		. ' mode: [M.exportMode("replace", "keep", "jpeg"), M.exportMode("replace", "jpeg", "jpeg"), M.exportMode("replace", "webp", "jpeg"), M.exportMode("new", "webp", "png")],'
		. ' none: M.exportRequest(d, "jpeg"),'
		. ' webp: M.exportRequest({ format: "webp", quality: 82, edge: 1920, colors: true }, "jpeg"),'
		. ' png: M.exportRequest({ format: "keep", quality: 92, edge: 0, colors: true }, "png"),'
		. ' low: M.exportRequest({ format: "keep", quality: 3, edge: 0, colors: false }, "jpeg") }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('jpeg', 'png', 'webp', '', ''), $r['own'], 'the file\'s own format from its name');
	pg_assert_same(array('jpeg', 'webp', 'png'), $r['format'], 'keep is the own format; an unknown choice keeps it');
	pg_assert_same(array(array('w' => 1920, 'h' => 1280), array('w' => 720, 'h' => 1080), array('w' => 800, 'h' => 600), array('w' => 800, 'h' => 600)), $r['size'], 'the longest edge, never enlarged');
	pg_assert_same(array('replace', 'replace', 'copy', 'new'), $r['mode'], 'another format is a copy, the file keeps its own');
	pg_assert_same(null, $r['none'], 'the defaults change nothing');
	pg_assert_same(array('format' => 'webp', 'quality' => 82, 'edge' => 1920), $r['webp'], 'colour reduction is for PNG only');
	pg_assert_same(array('colors' => 256), $r['png'], 'a PNG down to 256 colours');
	pg_assert_same(array('quality' => 40), $r['low'], 'quality within 40-100');
}
