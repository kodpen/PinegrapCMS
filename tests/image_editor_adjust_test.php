<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's adjustments and filters: the server's colour
 * matrix, vignette and sharpening amounts are the preview's (the model,
 * assets/js/pg_image_editor_model.js, run under Node); the document check
 * takes the adjustments and filters the editor makes and nothing else; GD and
 * Imagick draw the vignette and the sharpening alike, Imagick the colour step
 * as the browser's colour layer has it; an edit writes the type it is asked
 * for.
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
function image_editor_adjust_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieadjust');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A document of a 300 x 200 picture with these adjustments and filter.
function image_editor_adjust_doc($adjust, $filter = 'none')
{
	return array(
		'v' => 1,
		'source' => array('fileId' => 3, 'width' => 300, 'height' => 200),
		'geometry' => array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => null, 'cropShape' => 'rect', 'output' => null),
		'adjust' => $adjust,
		'filter' => $filter,
		'layers' => array());
}

function image_editor_adjust_gd()
{
	return function_exists('imagecreatetruecolor') && function_exists('imageconvolution') && function_exists('imagescale');
}

// A w x h PNG of one colour at $path.
function image_editor_adjust_solid($path, $width, $height, $red, $green, $blue)
{
	$image = imagecreatetruecolor($width, $height);
	imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, $red, $green, $blue));
	imagepng($image, $path);
	pg_image_destroy($image);
}

// A pixel's red, green and blue; Imagick may write a one-colour picture as
// a palette PNG.
function image_editor_adjust_rgb($image, $x, $y)
{
	$c = imagecolorsforindex($image, imagecolorat($image, $x, $y));

	return array($c['red'], $c['green'], $c['blue']);
}

// The adjustments, the filters, the colour matrix, the vignette and the
// sharpening amount are the same numbers in the preview and on the server.
function test_image_editor_adjust_matches_the_model()
{
	$cases = array(
		array(array('brightness' => 20, 'contrast' => 25, 'temperature' => 40, 'gamma' => -20), 'vivid'),
		array(array('exposure' => -35, 'saturation' => 60), 'none'),
		array(array(), 'sepia'),
		array(array('contrast' => -100, 'saturation' => -100, 'temperature' => 100, 'gamma' => 100), 'noir'),
		array(array('brightness' => -100, 'exposure' => 100, 'gamma' => -100), 'chrome'),
		array(array('vignette' => 70, 'sharpen' => 50), 'none'));

	$node = image_editor_adjust_node('var cases = ' . json_encode($cases) . ";\n"
		. 'console.log(JSON.stringify({'
		. ' adjust: M.ADJUST, filters: M.FILTERS, names: M.FILTER_NAMES,'
		. ' color: cases.map(function (c) { var v = M.colorValues({ adjust: c[0], filter: c[1] }); return { v: v, c: M.colorMatrix(v), vig: M.vignetteStops(v.vignette), k: M.sharpenKernel(v.sharpen) }; })'
		. '}));');

	if ($node === null) {
		return;
	}

	pg_assert_false(isset($node['error']), isset($node['error']) ? $node['error'] : 'node ran');

	$ranges = array();

	foreach ($node['adjust'] as $d) {
		$ranges[$d[0]] = array($d[1], $d[2]);
	}

	pg_assert_same(pg_image_edit_adjust_ranges(), $ranges, 'the same adjustments and ranges');
	pg_assert_same(array_keys(pg_image_edit_filters()), $node['names'], 'the same filters in the same order');

	foreach (pg_image_edit_filters() as $name => $values) {
		pg_assert_same($values, $node['filters'][$name] ? $node['filters'][$name] : array(), 'filter ' . $name . ' has the same numbers');
	}

	foreach ($cases as $i => $case) {
		$check = pg_image_edit_validate(image_editor_adjust_doc($case[0], $case[1]));
		$edit = $check['edit'];
		$expected = $node['color'][$i];

		pg_assert_same('', $check['error'], 'case ' . $i . ' is taken');

		if ($expected['c'] === null) {
			pg_assert_same(null, $edit['color'], 'case ' . $i . ': no colour step');
		} else {
			foreach ($expected['c']['matrix'] as $n => $value) {
				pg_assert_true(abs($edit['color']['matrix'][$n] - $value) < 1e-5, 'case ' . $i . ' matrix ' . $n . ': ' . $edit['color']['matrix'][$n] . ' as the preview\'s ' . $value);
			}

			pg_assert_true(abs($edit['color']['gamma'] - $expected['c']['gamma']) < 1e-5, 'case ' . $i . ' gamma');
		}

		// The vignette's stops and the server's opacity curve.
		if ($expected['vig'] === null) {
			pg_assert_true(!$edit['vignette'], 'case ' . $i . ': no vignette');
		} else {
			foreach ($expected['vig'] as $stop) {
				pg_assert_true(abs(pg_image_edit_vignette_alpha($stop[0], $edit['vignette']) - $stop[1]) < 1e-3, 'case ' . $i . ' vignette at ' . $stop[0]);
			}
		}

		// The kernel GD runs and the preview's feConvolveMatrix are one.
		if ($expected['k'] === null) {
			pg_assert_true(!$edit['sharpen'], 'case ' . $i . ': no sharpening');
		} else {
			pg_assert_true(abs($expected['k'][4] - (1 + 0.75 * $edit['sharpen'])) < 1e-6, 'case ' . $i . ' kernel centre');
			pg_assert_true(abs($expected['k'][1] + $edit['sharpen'] / 8) < 1e-6, 'case ' . $i . ' kernel edge');
		}
	}
}

// Adjustments are numbers in range under known names, and the filter one
// of the list; the editor's identity is no colour step at all.
function test_image_editor_adjust_validate()
{
	$plain = pg_image_edit_validate(image_editor_adjust_doc(array('brightness' => 0)));

	pg_assert_same('', $plain['error'], 'zero is taken');
	pg_assert_same(array(null, 0.0, 0.0), array($plain['edit']['color'], (float) $plain['edit']['vignette'], (float) $plain['edit']['sharpen']), 'and changes nothing');

	$summed = pg_image_edit_validate(image_editor_adjust_doc(array('saturation' => -50), 'mono'));

	pg_assert_true(abs($summed['edit']['color']['matrix'][0] - 0.2126) < 1e-6, 'a filter and a slider add up within range (fully grey)');

	$cases = array(
		array('adjust', array('glow' => 10), 'none'),
		array('adjust', array('brightness' => 101), 'none'),
		array('adjust', array('vignette' => -1), 'none'),
		array('adjust', array('contrast' => '10'), 'none'),
		array('filter', array(), 'lomo'),
		array('filter', array(), 7));

	foreach ($cases as $case) {
		$check = pg_image_edit_validate(image_editor_adjust_doc($case[1], $case[2]));

		pg_assert_same($case[0], $check['error'], json_encode(array($case[1], $case[2])) . ' is refused');
	}

	$doc = image_editor_adjust_doc(array());
	$doc['adjust'] = 'bright';

	pg_assert_same('adjust', pg_image_edit_validate($doc)['error'], 'adjustments that are no object are refused');
}

// The vignette darkens the corners and leaves the middle as it was, the
// sharpening leaves a flat picture as it was, in both libraries.
function test_image_editor_adjust_vignette_and_sharpen()
{
	if (!image_editor_adjust_gd()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pgieadj') . '.png';
	image_editor_adjust_solid($path, 300, 200, 128, 128, 128);
	$edit = pg_image_edit_validate(image_editor_adjust_doc(array('vignette' => 100, 'sharpen' => 60)))['edit'];
	$libraries = class_exists('Imagick') ? array('gd', 'imagick') : array('gd');

	try {
		foreach ($libraries as $library) {
			$data = ($library === 'gd')
				? pg_process_image_with_gd($path, IMAGETYPE_PNG, false, 300, 200, 92, '', 0, $edit)
				: pg_process_image_with_imagick($path, IMAGETYPE_PNG, false, 300, 200, 92, '', 0, $edit);
			$image = imagecreatefromstring((string) $data);
			$corner = image_editor_adjust_rgb($image, 0, 0)[0];
			$r = sqrt(((1 / 300 - 1) * (1 / 300 - 1) + (1 / 200 - 1) * (1 / 200 - 1)) / 2);
			$expected = 128 * (1 - pg_image_edit_vignette_alpha($r, $edit['vignette']));

			pg_assert_same(array(128, 128, 128), image_editor_adjust_rgb($image, 150, 100), $library . ': the middle is as it was');
			pg_assert_same(array(128, 128, 128), image_editor_adjust_rgb($image, 120, 90), $library . ': the sharpening leaves a flat area alone');
			pg_assert_true(abs($corner - $expected) <= 2, $library . ': the corner is ' . $corner . ', the curve says ' . round($expected));
			pg_image_destroy($image);
		}
	} finally {
		@unlink($path);
	}
}

// Imagick draws the colour step as the browser's colour layer has it.
function test_image_editor_adjust_imagick_color()
{
	if (!class_exists('Imagick') || !image_editor_adjust_gd()) {
		pg_assert_true(true, 'Imagick is not loaded; skipped');
		return;
	}

	$colors = array(array(200, 120, 40), array(30, 90, 210), array(128, 128, 128));
	$doc = image_editor_adjust_doc(array('brightness' => 15, 'contrast' => 30, 'temperature' => -45, 'gamma' => 25), 'warm');
	$node = image_editor_adjust_node('var color = M.colorMatrix(M.colorValues(' . json_encode($doc) . '));' . "\n"
		. 'var colors = ' . json_encode($colors) . ';' . "\n"
		. 'console.log(JSON.stringify(colors.map(function (c) { return Array.prototype.slice.call(M.applyColor(new Uint8ClampedArray([c[0], c[1], c[2], 255]), color), 0, 3); })));');

	if ($node === null) {
		return;
	}

	pg_assert_false(isset($node['error']), isset($node['error']) ? $node['error'] : 'node ran');

	$edit = pg_image_edit_validate($doc)['edit'];
	$path = tempnam(sys_get_temp_dir(), 'pgieadj') . '.png';

	try {
		foreach ($colors as $i => $color) {
			image_editor_adjust_solid($path, 20, 10, $color[0], $color[1], $color[2]);
			$image = imagecreatefromstring((string) pg_process_image_with_imagick($path, IMAGETYPE_PNG, false, 20, 10, 92, '', 0, $edit));
			$found = image_editor_adjust_rgb($image, 10, 5);

			foreach (array(0, 1, 2) as $c) {
				pg_assert_true(abs($found[$c] - $node[$i][$c]) <= 2, 'colour ' . $i . ' channel ' . $c . ': ' . $found[$c] . ' as the browser\'s ' . $node[$i][$c]);
			}

			pg_image_destroy($image);
		}
	} finally {
		@unlink($path);
	}
}

// GD has no colour matrix and says so; an edit writes the type it is asked
// for (a colour layer saved as the original's JPEG).
function test_image_editor_adjust_gd_color_and_type()
{
	if (!image_editor_adjust_gd()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pgieadj') . '.png';
	image_editor_adjust_solid($path, 40, 30, 10, 200, 90);

	try {
		$colored = pg_image_edit_validate(image_editor_adjust_doc(array('saturation' => 20)));

		if (!class_exists('Imagick')) {
			pg_assert_same('no_color', pg_image_edit_apply($path, $colored['edit'])['reason'], 'GD refuses a colour step by name');
		}

		$edit = pg_image_edit_validate(array_merge(image_editor_adjust_doc(array()), array('source' => array('width' => 40, 'height' => 30))))['edit'];
		$edit['type'] = IMAGETYPE_JPEG;
		$data = pg_process_image_with_gd($path, IMAGETYPE_PNG, false, 40, 30, 92, '', 0, $edit);
		$size = getimagesizefromstring((string) $data);

		pg_assert_same(IMAGETYPE_JPEG, $size[2], 'a PNG written as the JPEG asked for');
		pg_assert_same(array(40, 30), array($size[0], $size[1]), 'at its size');
	} finally {
		@unlink($path);
	}
}
