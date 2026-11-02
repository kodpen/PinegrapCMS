<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's export settings: the EXPORT section of
 * assets/js/pg_image_editor_model.js under Node (a file's own format, the
 * format a save writes, the longest edge, the copy a format change makes,
 * what a save request carries, the result card), the server's check of the
 * settings, the copy's name and the recipe (includes/fn/image_editor.php),
 * and GD's longest edge and baseline JPEG (includes/image_engine.php).
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
		. ' low: M.exportRequest({ format: "keep", quality: 3, edge: 0, colors: false }, "jpeg"),'
		. ' flat: M.exportRequest({ format: "jpeg", quality: 92, edge: 0, colors: false, progressive: false }, "png"),'
		. ' flatPng: M.exportRequest({ format: "keep", quality: 92, edge: 0, colors: false, progressive: false }, "png"),'
		. ' camera: [M.exportRequest({ format: "keep", quality: 92, edge: 0, camera: true }, "jpeg"), M.exportRequest({ format: "keep", quality: 92, edge: 0, camera: true }, "png"), M.exportRequest({ format: "webp", quality: 92, edge: 0, camera: true }, "jpeg")],'
		. ' srgb: M.exportRequest({ format: "keep", quality: 92, edge: 0, srgb: true }, "png"),'
		. ' change: [M.exportChange(1000, 250), M.exportChange(1000, 1200), M.exportChange(1000, 10), M.exportChange(0, 10)],'
		. ' bytes: [M.exportBytes(900), M.exportBytes(250880), M.exportBytes(1468006, ","), M.exportBytes(-3)] }));'
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
	pg_assert_same(array('format' => 'jpeg', 'progressive' => false), $r['flat'], 'a baseline JPEG asked for');
	pg_assert_same(null, $r['flatPng'], 'progressive is for JPEG only');
	pg_assert_same(array(array('camera' => true), null, array('format' => 'webp')), $r['camera'], 'camera details from a JPEG into a JPEG only');
	pg_assert_same(array('srgb' => true), $r['srgb'], 'sRGB asked for');
	pg_assert_same(array(array('percent' => -75, 'bar' => 25), array('percent' => 20, 'bar' => 100), array('percent' => -99, 'bar' => 3), null), $r['change'], 'the result card\'s change and bar');
	pg_assert_same(array('900 B', '245 KB', '1,4 MB', '0 B'), $r['bytes'], 'sizes in B, KB and MB with the panel\'s separator');
}

// The settings a save sends, checked on the server.
function test_image_editor_export_options()
{
	$formats = array('jpeg', 'png', 'webp');
	$defaults = array('format' => '', 'quality' => 92, 'edge' => 0, 'colors' => 0, 'progressive' => true);

	pg_assert_same($defaults, pg_image_editor_export_options(null, 'jpeg', $formats), 'no settings keep everything');
	pg_assert_same($defaults, pg_image_editor_export_options(array('format' => 'jpeg'), 'jpeg', $formats), 'the own format is no change');
	pg_assert_same(array('format' => 'webp', 'quality' => 80, 'edge' => 1920, 'colors' => 0, 'progressive' => true),
		pg_image_editor_export_options(array('format' => 'webp', 'quality' => 80, 'edge' => 1920), 'jpeg', $formats), 'WebP at 80, 1920 px');
	pg_assert_same(256, pg_image_editor_export_options(array('colors' => 256), 'png', $formats)['colors'], 'a PNG down to 256 colours');
	pg_assert_false(pg_image_editor_export_options(array('format' => 'jpeg', 'progressive' => false), 'png', $formats)['progressive'], 'a baseline JPEG');

	$refused = array(
		'not an object'          => array('webp', 'jpeg'),
		'a format not written'   => array(array('format' => 'avif'), 'jpeg'),
		'a format not known'     => array(array('format' => 'gif'), 'jpeg'),
		'quality under 40'       => array(array('quality' => 20), 'jpeg'),
		'quality as text'        => array(array('quality' => '80'), 'jpeg'),
		'an edge not offered'    => array(array('edge' => 1000), 'jpeg'),
		'colours for a JPEG'     => array(array('colors' => 256), 'jpeg'),
		'colours not 256'        => array(array('colors' => 16), 'png'),
		'colours turned to WebP' => array(array('format' => 'webp', 'colors' => 256), 'png'),
		'progressive for a PNG'  => array(array('progressive' => false), 'png'),
		'progressive true'       => array(array('progressive' => true), 'jpeg'));

	foreach ($refused as $label => $case) {
		pg_assert_false(pg_image_editor_export_options($case[0], $case[1], $formats), 'refused: ' . $label);
	}
}

// The copy's name, the recipe and the engine's options.
function test_image_editor_export_name_and_recipe()
{
	pg_assert_same('photo.jpg', pg_image_editor_export_name('photo.png', 'jpeg'), 'JPEG is .jpg');
	pg_assert_same('a.b.webp', pg_image_editor_export_name('a.b.JPG', 'webp'), 'only the last extension');
	pg_assert_same('plain.avif', pg_image_editor_export_name('plain', 'avif'), 'a name without one gets one');
	pg_assert_same('png', pg_image_editor_export_format_of(IMAGETYPE_PNG), 'a PNG');
	pg_assert_same('jpeg', pg_image_editor_export_format_of(IMAGETYPE_JPEG), 'a JPEG');

	$base = array('format' => '', 'quality' => 92, 'edge' => 0, 'colors' => 0, 'progressive' => true);

	pg_assert_same(array(array('rot' => 0), array('quality' => 92)), pg_image_editor_export_edit(array('rot' => 0), $base), 'the defaults add nothing');
	pg_assert_same(array(array('edge' => 1280), array('quality' => 75, 'format' => 'webp')),
		pg_image_editor_export_edit(array(), array_merge($base, array('format' => 'webp', 'quality' => 75, 'edge' => 1280))), 'WebP through the engine\'s format');
	pg_assert_same(array(array('type' => IMAGETYPE_JPEG, 'progressive' => false), array('quality' => 92)),
		pg_image_editor_export_edit(array('type' => IMAGETYPE_PNG), array_merge($base, array('format' => 'jpeg', 'progressive' => false))), 'JPEG as the type the edit writes');
	pg_assert_same(array(array('type' => IMAGETYPE_PNG, 'colors' => 256), array('quality' => 92)),
		pg_image_editor_export_edit(array(), array_merge($base, array('format' => 'png', 'colors' => 256))), 'PNG with its colours');
}

// GD writes the longest edge, the other format and a baseline JPEG.
function test_image_editor_export_gd_encode()
{
	if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg') || !function_exists('imagepng')) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = sys_get_temp_dir() . '/pgieexport' . getmypid() . mt_rand(1000, 9999);
	@mkdir($folder, 0777, true);
	$source = $folder . '/wide.png';

	try {
		$image = imagecreatetruecolor(400, 200);
		imagefilledrectangle($image, 0, 0, 399, 199, imagecolorallocate($image, 30, 120, 200));
		imagepng($image, $source);
		pg_image_destroy($image);

		$check = pg_image_edit_validate(array(
			'v' => 1,
			'source' => array('fileId' => 7, 'width' => 400, 'height' => 200),
			'artboard' => null,
			'geometry' => array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => null, 'cropShape' => 'rect', 'output' => null),
			'adjust' => array(),
			'filter' => 'none',
			'frame' => null,
			'layers' => array()));

		pg_assert_same('', $check['error'], 'the document is valid');

		$options = array('format' => 'jpeg', 'quality' => 80, 'edge' => 1080, 'colors' => 0, 'progressive' => false);
		list($edit, $engine) = pg_image_editor_export_edit($check['edit'], $options);
		$kept = pg_image_edit_apply($source, $edit, $engine);

		pg_assert_same(array(400, 200), array($kept['width'], $kept['height']), 'a smaller picture is never enlarged');

		$edit['edge'] = 100;
		$report = pg_image_edit_apply($source, $edit, $engine);
		$size = getimagesizefromstring($report['data']);

		pg_assert_same('success', $report['status'], 'encoded');
		pg_assert_same(array(100, 50, IMAGETYPE_JPEG), array($size[0], $size[1], $size[2]), 'a JPEG within the longest edge');
		// A progressive JPEG has an SOF2 marker, a baseline one SOF0.
		pg_assert_false(strpos($report['data'], "\xFF\xC2") !== false, 'baseline when asked');

		unset($edit['progressive']);
		$progressive = pg_image_edit_apply($source, $edit, $engine);

		pg_assert_true(strpos($progressive['data'], "\xFF\xC2") !== false, 'progressive by default');
	} finally {
		@unlink($source);
		@rmdir($folder);
	}
}
