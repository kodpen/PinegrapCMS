<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's redaction: the block and blur sizes the
 * server burns in are the preview's (the model, run under Node), the regions
 * a save sends are checked before any pixel work, GD and Imagick hide what
 * lies under a region and leave the rest, and a redacted save keeps no
 * metadata.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The block and blur sizes grow with the strength and the picture.
function test_image_editor_redact_sizes()
{
	$weak = pg_image_edit_redact_sizes(10, 2000, 1.0);
	$strong = pg_image_edit_redact_sizes(90, 2000, 1.0);

	pg_assert_true($strong['block'] > $weak['block'], 'a stronger pixelation has bigger blocks');
	pg_assert_true($strong['sigma'] > $weak['sigma'], 'a stronger blur is wider');
	pg_assert_same(70, pg_image_edit_redact_sizes(100, 2000, 1.0)['block'], 'blocks of 3.5 % of the longer side at full strength');
}

// A document of a 120 x 80 picture with no other change.
function image_editor_redact_doc()
{
	return array(
		'v' => 1,
		'source' => array('fileId' => 3, 'width' => 120, 'height' => 80),
		'geometry' => array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => null, 'cropShape' => 'rect', 'output' => null),
		'adjust' => array(),
		'filter' => 'none',
		'layers' => array());
}

// A w x h PNG data: URL, opaque on its left half when $half, else all over;
// $half 'disc' gives a round area in its box, as a brush dot leaves.
function image_editor_redact_mask($width, $height, $half)
{
	if ($half === 'disc') {
		$image = imagecreatetruecolor($width, $height);
		imagesavealpha($image, true);
		imagealphablending($image, false);
		imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocatealpha($image, 0, 0, 0, 127));
		imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), $width - 10, $height - 10, imagecolorallocatealpha($image, 255, 255, 255, 0));
		ob_start();
		imagepng($image);
		pg_image_destroy($image);

		return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
	}

	$image = imagecreatetruecolor($width, $height);
	imagesavealpha($image, true);
	imagealphablending($image, false);
	imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocatealpha($image, 0, 0, 0, 127));
	imagefilledrectangle($image, 0, 0, ($half ? (int) ($width / 2) : $width) - 1, $height - 1, imagecolorallocatealpha($image, 255, 255, 255, 0));
	ob_start();
	imagepng($image);
	pg_image_destroy($image);

	return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
}

// A 120 x 80 PNG of one-pixel black and white squares at $path.
function image_editor_redact_checker($path)
{
	$image = imagecreatetruecolor(120, 80);
	$white = imagecolorallocate($image, 255, 255, 255);

	for ($y = 0; $y < 80; $y++) {
		for ($x = ($y % 2); $x < 120; $x += 2) {
			imagesetpixel($image, $x, $y, $white);
		}
	}

	imagepng($image, $path);
	pg_image_destroy($image);
}

// The regions a save sends are checked before any pixel work.
function test_image_editor_redact_regions_are_checked()
{
	$edit = pg_image_edit_validate(image_editor_redact_doc())['edit'];
	$prefix = tempnam(sys_get_temp_dir(), 'pgieredact');
	$mask = image_editor_redact_mask(40, 30, false);

	try {
		pg_assert_same(array(), pg_image_editor_take_redactions(null, $edit, $prefix), 'none');

		$list = pg_image_editor_take_redactions(array(array('mode' => 'pixel', 'strength' => 50, 'x' => 10, 'y' => 20, 'mask' => $mask)), $edit, $prefix);
		pg_assert_same(1, count($list), 'one area');
		pg_assert_same(array(10, 20, 40, 30), array($list[0]['x'], $list[0]['y'], $list[0]['w'], $list[0]['h']), 'its box');
		pg_assert_same(pg_image_edit_redact_sizes(50, 120, 1.0)['block'], $list[0]['block'], 'its block from the strength');
		pg_assert_true(is_file($list[0]['mask']), 'its mask on the disk');
		pg_image_editor_drop_redactions($list);
		pg_assert_false(is_file($list[0]['mask']), 'and removed');

		$bad = array(
			'an unknown method' => array('mode' => 'swirl', 'strength' => 50, 'x' => 0, 'y' => 0, 'mask' => $mask),
			'a strength out of range' => array('mode' => 'blur', 'strength' => 0, 'x' => 0, 'y' => 0, 'mask' => $mask),
			'a string number' => array('mode' => 'blur', 'strength' => '50', 'x' => 0, 'y' => 0, 'mask' => $mask),
			'a box past the right edge' => array('mode' => 'solid', 'strength' => 50, 'x' => 90, 'y' => 0, 'mask' => $mask),
			'a fraction' => array('mode' => 'solid', 'strength' => 50, 'x' => 1.5, 'y' => 0, 'mask' => $mask),
			'a JPEG mask' => array('mode' => 'solid', 'strength' => 50, 'x' => 0, 'y' => 0, 'mask' => 'data:image/jpeg;base64,AAAA'));

		foreach ($bad as $label => $region) {
			pg_assert_same(false, pg_image_editor_take_redactions(array($region), $edit, $prefix), $label . ' is refused');
		}

		pg_assert_same(false, pg_image_editor_take_redactions(array_fill(0, 51, array('mode' => 'solid', 'strength' => 50, 'x' => 0, 'y' => 0, 'mask' => $mask)), $edit, $prefix), 'more than 50 are refused');
	} finally {
		@unlink($prefix);
	}
}

// Each library hides what lies under the opaque half of a mask and leaves
// the transparent half and the rest of the picture alone.
function test_image_editor_redact_burns_in()
{
	if (!function_exists('imagecreatetruecolor')) {
		pg_assert_true(true, 'GD is not loaded; skipped');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pgieredact') . '.png';
	$prefix = tempnam(sys_get_temp_dir(), 'pgieredact');
	$libraries = class_exists('Imagick') ? array('gd', 'imagick') : array('gd');
	image_editor_redact_checker($path);

	try {
		foreach (array('pixel', 'blur', 'solid') as $mode) {
			foreach ($libraries as $library) {
				$edit = pg_image_edit_validate(image_editor_redact_doc())['edit'];
				$edit['redact'] = pg_image_editor_take_redactions(array(array('mode' => $mode, 'strength' => 100, 'x' => 20, 'y' => 20, 'mask' => image_editor_redact_mask(40, 40, true))), $edit, $prefix);
				$data = ($library === 'gd')
					? pg_process_image_with_gd($path, IMAGETYPE_PNG, false, 120, 80, 92, '', 0, $edit)
					: pg_process_image_with_imagick($path, IMAGETYPE_PNG, false, 120, 80, 92, '', 0, $edit);
				pg_image_editor_drop_redactions($edit['redact']);
				$image = imagecreatefromstring((string) $data);
				// Imagick writes a picture of a few colours (the solid box over
				// black and white) as a palette PNG: read as colours, not indices.
				imagepalettetotruecolor($image);
				$label = $library . ' ' . $mode . ': ';

				$inside = array();

				for ($y = 24; $y < 56; $y++) {
					for ($x = 24; $x < 36; $x++) {
						$inside[] = imagecolorat($image, $x, $y) & 0xff;
					}
				}

				$spread = max($inside) - min($inside);
				pg_assert_true($spread < 60, $label . 'the checker is gone under the area (spread ' . $spread . ')');

				if ($mode === 'solid') {
					pg_assert_same(array(17, 19, 23), array_values(array_slice(imagecolorsforindex($image, imagecolorat($image, 30, 30)), 0, 3)), $label . 'the solid colour');
				}

				if ($mode === 'pixel') {
					pg_assert_true(abs((imagecolorat($image, 30, 30) & 0xff) - 128) < 40, $label . 'a block is the average grey');
				}

				pg_assert_same(array(0, 255), array(imagecolorat($image, 47, 30) & 0xff, imagecolorat($image, 48, 30) & 0xff), $label . 'the transparent half of the mask is left alone');
				pg_assert_same(array(255, 0), array(imagecolorat($image, 10, 10) & 0xff, imagecolorat($image, 11, 10) & 0xff), $label . 'outside the area is left alone');
				pg_image_destroy($image);
			}
		}

		// A brush-shaped solid area: its box is partly transparent, and the
		// corners of the box keep the picture.
		foreach ($libraries as $library) {
			$edit = pg_image_edit_validate(image_editor_redact_doc())['edit'];
			$edit['redact'] = pg_image_editor_take_redactions(array(array('mode' => 'solid', 'strength' => 50, 'x' => 20, 'y' => 20, 'mask' => image_editor_redact_mask(40, 40, 'disc'))), $edit, $prefix);
			$data = ($library === 'gd')
				? pg_process_image_with_gd($path, IMAGETYPE_PNG, false, 120, 80, 92, '', 0, $edit)
				: pg_process_image_with_imagick($path, IMAGETYPE_PNG, false, 120, 80, 92, '', 0, $edit);
			pg_image_editor_drop_redactions($edit['redact']);
			$image = imagecreatefromstring((string) $data);
			imagepalettetotruecolor($image);
			$label = $library . ' brush solid: ';

			pg_assert_same(array(17, 19, 23), array_values(array_slice(imagecolorsforindex($image, imagecolorat($image, 40, 40)), 0, 3)), $label . 'the middle is the solid colour');
			pg_assert_same(array(255, 0), array(imagecolorat($image, 22, 22) & 0xff, imagecolorat($image, 23, 22) & 0xff), $label . 'a corner of its box is left alone');
			pg_assert_same(array(255, 0), array(imagecolorat($image, 10, 10) & 0xff, imagecolorat($image, 11, 10) & 0xff), $label . 'outside the box is left alone');
			pg_image_destroy($image);
		}
	} finally {
		@unlink($path);
		@unlink($prefix);
	}
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_redact_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieredact');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The preview's sizes are the server's before the output scale; the methods
// are the same; an area's output box and a brush's points.
function test_image_editor_redact_model()
{
	$node = image_editor_redact_node('console.log(JSON.stringify({'
		. ' modes: M.REDACT_MODES,'
		. ' sizes: [[10, 400], [60, 2400], [100, 2000], [1, 50]].map(function (c) { return M.redactSizes(c[0], c[1]); }),'
		. ' box: M.redactRegion({ x: 10, y: 20, w: 30, h: 10, rot: 0 }, { x: 0, y: 0, w: 100, h: 50 }, 200, 100),'
		. ' turned: M.redactRegion({ x: 40, y: 20, w: 20, h: 10, rot: 90 }, { x: 0, y: 0, w: 100, h: 50 }, 100, 50),'
		. ' out: M.redactRegion({ x: 200, y: 20, w: 30, h: 10, rot: 0 }, { x: 0, y: 0, w: 100, h: 50 }, 100, 50),'
		. ' brush: M.brushLayer([[10, 10], [30, 20]], 6),'
		. ' pts: M.brushPoints(M.brushLayer([[10, 10], [30, 20]], 6)),'
		. ' brushBox: M.redactRegion(M.brushLayer([[10, 10], [30, 20]], 6), { x: 0, y: 0, w: 100, h: 50 }, 100, 50)'
		. '}));');

	if ($node === null) {
		return;
	}

	pg_assert_false(isset($node['error']), isset($node['error']) ? $node['error'] : 'node ran');
	pg_assert_same(pg_image_edit_redact_modes(), $node['modes'], 'the same methods');

	foreach (array(array(10, 400), array(60, 2400), array(100, 2000), array(1, 50)) as $i => $case) {
		$server = pg_image_edit_redact_sizes($case[0], $case[1], 1.0);
		pg_assert_same($server['block'], (int) $node['sizes'][$i]['block'], 'case ' . $i . ': the same block');
		pg_assert_true(abs($server['sigma'] - $node['sizes'][$i]['sigma']) <= 0.05, 'case ' . $i . ': the same blur');
	}

	pg_assert_same(array('x' => 20, 'y' => 40, 'w' => 60, 'h' => 20), $node['box'], 'a box at twice the size');
	pg_assert_same(array('x' => 45, 'y' => 15, 'w' => 10, 'h' => 20), $node['turned'], 'a quarter-turned box');
	pg_assert_same(null, $node['out'], 'outside the crop: nothing');
	pg_assert_same('brush', $node['brush']['shape'], 'a brush area');
	pg_assert_same(6, $node['brush']['size'], 'its width');
	pg_assert_same(array(array(10, 10), array(30, 20)), $node['pts'], 'its points come back');
	pg_assert_same(array('x' => 7, 'y' => 7, 'w' => 26, 'h' => 16), $node['brushBox'], 'its box grows by half the width');
}

// Every label of the tool is translated, the warning names the versions.
function test_image_editor_redact_labels()
{
	$strings = pg_image_editor_strings_redact();

	foreach (array('layer_redact', 'redactMethod', 'redactPixel', 'redactBlur', 'redactSolid', 'redactStrength', 'redactNote', 'hintRedact') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), 'label ' . $key);
	}
}

// A redacted save keeps no metadata: an EXIF block (its thumbnail could
// hold the unredacted picture) is not written back.
function test_image_editor_redact_drops_metadata()
{
	if (!function_exists('imagecreatetruecolor')) {
		pg_assert_true(true, 'GD is not loaded; skipped');
		return;
	}

	$image = imagecreatetruecolor(120, 80);
	ob_start();
	imagejpeg($image, null, 90);
	$jpeg = (string) ob_get_clean();
	pg_image_destroy($image);

	$exif = "Exif\0\0" . str_repeat('T', 64);
	$path = tempnam(sys_get_temp_dir(), 'pgieredact') . '.jpg';
	$prefix = tempnam(sys_get_temp_dir(), 'pgieredact');
	file_put_contents($path, substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));
	$libraries = class_exists('Imagick') ? array('gd', 'imagick') : array('gd');

	try {
		pg_assert_contains('Exif', (string) file_get_contents($path), 'the picture carries EXIF');

		foreach ($libraries as $library) {
			$edit = pg_image_edit_validate(image_editor_redact_doc())['edit'];
			$edit['redact'] = pg_image_editor_take_redactions(array(array('mode' => 'solid', 'strength' => 50, 'x' => 0, 'y' => 0, 'mask' => image_editor_redact_mask(20, 20, false))), $edit, $prefix);
			$data = ($library === 'gd')
				? pg_process_image_with_gd($path, IMAGETYPE_JPEG, false, 120, 80, 92, '', 0, $edit)
				: pg_process_image_with_imagick($path, IMAGETYPE_JPEG, false, 120, 80, 92, '', 0, $edit);
			pg_image_editor_drop_redactions($edit['redact']);

			pg_assert_true(is_string($data) && ($data !== ''), $library . ': written');
			pg_assert_false(strpos((string) $data, 'Exif') !== false, $library . ': no EXIF left');
		}
	} finally {
		@unlink($path);
		@unlink($prefix);
	}
}

// A save to another endpoint (save.url, pg_image_editor_render()) burns the
// redaction in as a save does, refuses a bad area and leaves no mask behind.
function test_image_editor_redact_through_render()
{
	if (!function_exists('imagecreatetruecolor')) {
		pg_assert_true(true, 'GD is not loaded; skipped');
		return;
	}

	$folder = sys_get_temp_dir() . '/pgieredact' . uniqid();
	mkdir($folder);
	$path = $folder . '/picture.png';
	image_editor_redact_checker($path);

	try {
		$region = array('mode' => 'solid', 'strength' => 50, 'x' => 20, 'y' => 20, 'mask' => image_editor_redact_mask(40, 40, false));
		$picture = pg_image_editor_render($path, array('doc' => image_editor_redact_doc(), 'redact' => array($region)));

		pg_assert_same('', $picture['error'], 'rendered');
		$image = imagecreatefromstring($picture['data']);
		imagepalettetotruecolor($image);
		pg_assert_same(array(17, 19, 23), array_values(array_slice(imagecolorsforindex($image, imagecolorat($image, 30, 30)), 0, 3)), 'the area is the solid colour');
		pg_assert_same(array(255, 0), array(imagecolorat($image, 10, 10) & 0xff, imagecolorat($image, 11, 10) & 0xff), 'outside the area is left alone');
		pg_image_destroy($image);
		pg_assert_same(array('picture.png'), array_values(array_diff(scandir($folder), array('.', '..'))), 'no mask is left beside the picture');

		$region['x'] = 100;
		$refused = pg_image_editor_render($path, array('doc' => image_editor_redact_doc(), 'redact' => array($region)));
		pg_assert_true($refused['error'] !== '', 'an area past the edge is refused');
		pg_assert_same(array('picture.png'), array_values(array_diff(scandir($folder), array('.', '..'))), 'and leaves no file behind');
	} finally {
		foreach (glob($folder . '/*') as $file) {
			@unlink($file);
		}

		@rmdir($folder);
	}
}

// The redact part sends its masks for a save to another endpoint too.
function test_image_editor_redact_masks_for_url()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_redact.js');

	pg_assert_contains("(mode === 'url')", $source, 'the save hook covers save.url');
}
