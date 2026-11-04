<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what an area selection does to pixels (E27b): the masks a save
 * sends are checked before any pixel work (pg_image_editor_take_masks()),
 * and the engine's mask step under GD and Imagick erases to see-through or
 * to a fill colour (white in a JPEG without one) and colours only inside
 * its mask; one pixel's colour step as the browser's applyColor() does it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A document of a 120 x 80 picture with no other change.
function image_editor_area_pixels_doc()
{
	return array(
		'v' => 1,
		'source' => array('fileId' => 3, 'width' => 120, 'height' => 80),
		'geometry' => array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => null, 'cropShape' => 'rect', 'output' => null),
		'adjust' => array(),
		'filter' => 'none',
		'layers' => array());
}

// A w x h PNG data: URL, opaque on its left half.
function image_editor_area_pixels_mask($width, $height)
{
	$image = imagecreatetruecolor($width, $height);
	imagesavealpha($image, true);
	imagealphablending($image, false);
	imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocatealpha($image, 0, 0, 0, 127));
	imagefilledrectangle($image, 0, 0, (int) ($width / 2) - 1, $height - 1, imagecolorallocatealpha($image, 255, 255, 255, 0));
	ob_start();
	imagepng($image);
	pg_image_destroy($image);

	return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
}

// A 120 x 80 picture of one grey (100) at $path, PNG or JPEG.
function image_editor_area_pixels_picture($path, $type)
{
	$image = imagecreatetruecolor(120, 80);
	imagefilledrectangle($image, 0, 0, 119, 79, imagecolorallocate($image, 100, 100, 100));

	if ($type === IMAGETYPE_JPEG) {
		imagejpeg($image, $path, 95);
	} else {
		imagepng($image, $path);
	}

	pg_image_destroy($image);
}

// The libraries this PHP has: GD always, Imagick when loaded.
function image_editor_area_pixels_libraries()
{
	return class_exists('Imagick') ? array('gd', 'imagick') : array('gd');
}

// The picture at $path with $masks through one library, read back.
function image_editor_area_pixels_run($library, $path, $type, $masks, $prefix)
{
	$edit = pg_image_edit_validate(image_editor_area_pixels_doc())['edit'];
	$edit['masks'] = pg_image_editor_take_masks($masks, $edit, $prefix);
	pg_assert_true(is_array($edit['masks']) && $edit['masks'], 'the masks are taken');
	$data = ($library === 'gd')
		? pg_process_image_with_gd($path, $type, false, 120, 80, 92, '', 0, $edit)
		: pg_process_image_with_imagick($path, $type, false, 120, 80, 92, '', 0, $edit);
	pg_image_editor_drop_redactions($edit['masks']);
	$image = imagecreatefromstring((string) $data);

	if ($image && !imageistruecolor($image)) {
		imagepalettetotruecolor($image);
	}

	return $image;
}

// The rgb and GD alpha (0 opaque, 127 clear) of a pixel.
function image_editor_area_pixels_at($image, $x, $y)
{
	$c = imagecolorsforindex($image, imagecolorat($image, $x, $y));

	return array($c['red'], $c['green'], $c['blue'], $c['alpha']);
}

// The masks a save sends are checked before any pixel work.
function test_image_editor_area_masks_are_checked()
{
	$edit = pg_image_edit_validate(image_editor_area_pixels_doc())['edit'];
	$prefix = tempnam(sys_get_temp_dir(), 'pgiemask');
	$mask = image_editor_area_pixels_mask(40, 30);

	try {
		pg_assert_same(array(), pg_image_editor_take_masks(null, $edit, $prefix), 'none');

		$list = pg_image_editor_take_masks(array(
			array('op' => 'erase', 'x' => 10, 'y' => 20, 'mask' => $mask),
			array('op' => 'erase', 'x' => 0, 'y' => 0, 'mask' => $mask, 'fill' => '#ff8000'),
			array('op' => 'color', 'x' => 0, 'y' => 0, 'mask' => $mask, 'adjust' => array('brightness' => 40), 'filter' => 'none'),
			array('op' => 'color', 'x' => 0, 'y' => 0, 'mask' => $mask, 'adjust' => array(), 'filter' => 'none')), $edit, $prefix);
		pg_assert_same(3, count($list), 'three areas; the colour area without a change is left out');
		pg_assert_same(array('erase', 10, 20, 40, 30, null), array($list[0]['op'], $list[0]['x'], $list[0]['y'], $list[0]['w'], $list[0]['h'], $list[0]['fill']), 'the erase and its box');
		pg_assert_same(array(255, 128, 0), $list[1]['fill'], 'the fill colour');
		pg_assert_same(pg_image_editor_mask_color(array('brightness' => 40), 'none'), $list[2]['color'], 'the colour step of its adjustments');
		pg_assert_true(is_file($list[0]['mask']), 'its mask on the disk');
		pg_image_editor_drop_redactions($list);
		pg_assert_false(is_file($list[0]['mask']), 'and removed');

		$bad = array(
			'an unknown op' => array('op' => 'swirl', 'x' => 0, 'y' => 0, 'mask' => $mask),
			'a box past the right edge' => array('op' => 'erase', 'x' => 90, 'y' => 0, 'mask' => $mask),
			'a fraction' => array('op' => 'erase', 'x' => 1.5, 'y' => 0, 'mask' => $mask),
			'a string number' => array('op' => 'erase', 'x' => '1', 'y' => 0, 'mask' => $mask),
			'a fill that is no colour' => array('op' => 'erase', 'x' => 0, 'y' => 0, 'mask' => $mask, 'fill' => 'red'),
			'an adjustment out of range' => array('op' => 'color', 'x' => 0, 'y' => 0, 'mask' => $mask, 'adjust' => array('brightness' => 200)),
			'an unknown adjustment' => array('op' => 'color', 'x' => 0, 'y' => 0, 'mask' => $mask, 'adjust' => array('glow' => 10)),
			'an unknown filter' => array('op' => 'color', 'x' => 0, 'y' => 0, 'mask' => $mask, 'adjust' => array(), 'filter' => 'lomo'),
			'a JPEG mask' => array('op' => 'erase', 'x' => 0, 'y' => 0, 'mask' => 'data:image/jpeg;base64,AAAA'));

		foreach ($bad as $label => $area) {
			pg_assert_same(false, pg_image_editor_take_masks(array($area), $edit, $prefix), $label . ' is refused');
		}

		pg_assert_same(false, pg_image_editor_take_masks(array_fill(0, 51, array('op' => 'erase', 'x' => 0, 'y' => 0, 'mask' => $mask)), $edit, $prefix), 'more than 50 are refused');
	} finally {
		@unlink($prefix);
	}
}

// One pixel's colour step matches the browser's: no change is no change,
// full desaturation is grey, the matrix clamps before the gamma.
function test_image_editor_area_color_pixel()
{
	pg_assert_same(array(100, 150, 200), pg_image_edit_color_pixel(array('matrix' => array(1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0), 'gamma' => 1), 100, 150, 200), 'identity');

	$mono = pg_image_editor_mask_color(array(), 'mono');
	$grey = pg_image_edit_color_pixel($mono, 200, 40, 90);
	pg_assert_true(($grey[0] === $grey[1]) && ($grey[1] === $grey[2]), 'mono gives grey (' . implode(',', $grey) . ')');
	pg_assert_same(array(255, 0, 151), pg_image_edit_color_pixel(array('matrix' => array(3, 0, 0, 0, 0, 0, 1, 0, 0, -1, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0), 'gamma' => 0.5), 200, 40, 90), 'clamped, then the gamma');
}

// Erase on a PNG: the opaque half of the mask becomes see-through, the rest
// stays; with a fill colour it takes the colour instead.
function test_image_editor_area_erase_png()
{
	if (!function_exists('imagecreatetruecolor')) {
		pg_assert_true(true, 'GD is not loaded; skipped');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pgiemask') . '.png';
	$prefix = tempnam(sys_get_temp_dir(), 'pgiemask');
	image_editor_area_pixels_picture($path, IMAGETYPE_PNG);

	try {
		foreach (image_editor_area_pixels_libraries() as $library) {
			$image = image_editor_area_pixels_run($library, $path, IMAGETYPE_PNG, array(array('op' => 'erase', 'x' => 20, 'y' => 20, 'mask' => image_editor_area_pixels_mask(40, 40))), $prefix);
			pg_assert_same(127, image_editor_area_pixels_at($image, 30, 30)[3], $library . ': the erased part is see-through');
			pg_assert_same(array(100, 100, 100, 0), image_editor_area_pixels_at($image, 50, 30), $library . ': the clear half of the mask is left alone');
			pg_assert_same(array(100, 100, 100, 0), image_editor_area_pixels_at($image, 5, 5), $library . ': outside the box is left alone');
			pg_image_destroy($image);

			$image = image_editor_area_pixels_run($library, $path, IMAGETYPE_PNG, array(array('op' => 'erase', 'x' => 20, 'y' => 20, 'mask' => image_editor_area_pixels_mask(40, 40), 'fill' => '#ff8000')), $prefix);
			pg_assert_same(array(255, 128, 0, 0), image_editor_area_pixels_at($image, 30, 30), $library . ': a fill colour covers the area');
			pg_image_destroy($image);
		}
	} finally {
		@unlink($path);
		@unlink($prefix);
	}
}

// Erase on a JPEG, which has no alpha: the fill colour, else white.
function test_image_editor_area_erase_jpeg_fill()
{
	if (!function_exists('imagecreatetruecolor')) {
		pg_assert_true(true, 'GD is not loaded; skipped');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pgiemask') . '.jpg';
	$prefix = tempnam(sys_get_temp_dir(), 'pgiemask');
	image_editor_area_pixels_picture($path, IMAGETYPE_JPEG);
	$near = function ($a, $b) {
		return (abs($a[0] - $b[0]) < 12) && (abs($a[1] - $b[1]) < 12) && (abs($a[2] - $b[2]) < 12);
	};

	try {
		foreach (image_editor_area_pixels_libraries() as $library) {
			$image = image_editor_area_pixels_run($library, $path, IMAGETYPE_JPEG, array(array('op' => 'erase', 'x' => 20, 'y' => 20, 'mask' => image_editor_area_pixels_mask(40, 40))), $prefix);
			pg_assert_true($near(image_editor_area_pixels_at($image, 30, 30), array(255, 255, 255)), $library . ': white without a fill');
			pg_assert_true($near(image_editor_area_pixels_at($image, 52, 30), array(100, 100, 100)), $library . ': the clear half keeps the picture');
			pg_image_destroy($image);

			$image = image_editor_area_pixels_run($library, $path, IMAGETYPE_JPEG, array(array('op' => 'erase', 'x' => 20, 'y' => 20, 'mask' => image_editor_area_pixels_mask(40, 40), 'fill' => '#2040c0')), $prefix);
			pg_assert_true($near(image_editor_area_pixels_at($image, 30, 30), array(32, 64, 192)), $library . ': the fill colour');
			pg_image_destroy($image);
		}
	} finally {
		@unlink($path);
		@unlink($prefix);
	}
}

// Adjust inside: the colour step changes the pixels under the mask only.
function test_image_editor_area_adjust_inside()
{
	if (!function_exists('imagecreatetruecolor')) {
		pg_assert_true(true, 'GD is not loaded; skipped');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pgiemask') . '.png';
	$prefix = tempnam(sys_get_temp_dir(), 'pgiemask');
	image_editor_area_pixels_picture($path, IMAGETYPE_PNG);
	$want = pg_image_edit_color_pixel(pg_image_editor_mask_color(array('brightness' => 60), 'none'), 100, 100, 100);

	try {
		foreach (image_editor_area_pixels_libraries() as $library) {
			$image = image_editor_area_pixels_run($library, $path, IMAGETYPE_PNG, array(array('op' => 'color', 'x' => 20, 'y' => 20, 'mask' => image_editor_area_pixels_mask(40, 40), 'adjust' => array('brightness' => 60))), $prefix);
			$inside = image_editor_area_pixels_at($image, 30, 30);
			pg_assert_true(abs($inside[0] - $want[0]) <= 2, $library . ': brighter inside (' . $inside[0] . ', want ' . $want[0] . ')');
			pg_assert_same(array(100, 100, 100, 0), image_editor_area_pixels_at($image, 50, 30), $library . ': the clear half of the mask is left alone');
			pg_assert_same(array(100, 100, 100, 0), image_editor_area_pixels_at($image, 5, 5), $library . ': outside the box is left alone');
			pg_image_destroy($image);
		}
	} finally {
		@unlink($path);
		@unlink($prefix);
	}
}

