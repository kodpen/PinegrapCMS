<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the GD half of the image engine (includes/image_engine.php) over
 * a transparent single-frame GIF the test draws itself: the transparent
 * area stays transparent when the picture is turned, shrunk or re-encoded as
 * WebP, and a palette PNG keeps its alpha when turned.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function image_gd_gif_test_gd()
{
	return function_exists('imagecreate') && function_exists('imagegif') && function_exists('imagecreatefromgif') && function_exists('imagerotate');
}

// A 40x30 palette GIF: a transparent background (index 0, hiding a red that
// must never show) with an opaque dark 10x10 block in the top-left corner.
function image_gd_gif_test_file($path)
{
	$image      = imagecreate(40, 30);
	$background = imagecolorallocate($image, 252, 2, 4);
	$block      = imagecolorallocate($image, 4, 2, 4);

	imagecolortransparent($image, $background);
	imagefilledrectangle($image, 0, 0, 39, 29, $background);
	imagefilledrectangle($image, 0, 0, 9, 9, $block);
	imagegif($image, $path);
	imagedestroy($image);
}

// Whether the pixel at (x, y) of an encoded image is fully transparent.
function image_gd_gif_test_transparent($image, $x, $y)
{
	$index = imagecolorat($image, $x, $y);

	if (!imageistruecolor($image)) {
		return $index === imagecolortransparent($image);
	}

	$color = imagecolorsforindex($image, $index);

	return $color['alpha'] === 127;
}

function image_gd_gif_test_rgb($image, $x, $y)
{
	$color = imagecolorsforindex($image, imagecolorat($image, $x, $y));

	return array($color['red'], $color['green'], $color['blue']);
}

function test_image_gd_gif_rotate_keeps_transparency()
{
	if (!image_gd_gif_test_gd()) {
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_gif_test_');
	image_gd_gif_test_file($path);

	// Where the opaque corner block lands after each clockwise turn.
	$corners = array(90 => array(25, 5), 180 => array(35, 25), 270 => array(5, 35));

	foreach ($corners as $angle => $corner) {
		$data = pg_process_image_with_gd($path, IMAGETYPE_GIF, false, 0, 0, 92, '', $angle);
		pg_assert_true(is_string($data) && ($data !== ''), 'rotate ' . $angle . ' writes bytes');

		$image = imagecreatefromstring($data);
		pg_assert_same(IMAGETYPE_GIF, getimagesizefromstring($data)[2], 'rotate ' . $angle . ' is still a GIF');
		pg_assert_true(imagecolortransparent($image) < imagecolorstotal($image), 'rotate ' . $angle . ': the transparent index is inside the palette');
		pg_assert_true(image_gd_gif_test_transparent($image, 20, 20), 'rotate ' . $angle . ': the middle is transparent');
		pg_assert_false(image_gd_gif_test_transparent($image, $corner[0], $corner[1]), 'rotate ' . $angle . ': the block is opaque');
		pg_assert_same(array(4, 2, 4), image_gd_gif_test_rgb($image, $corner[0], $corner[1]), 'rotate ' . $angle . ': the block keeps its colour');
		imagedestroy($image);
	}

	unlink($path);
}

function test_image_gd_gif_resize_keeps_transparency()
{
	if (!image_gd_gif_test_gd()) {
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_gif_test_');
	image_gd_gif_test_file($path);

	$data = pg_process_image_with_gd($path, IMAGETYPE_GIF, true, 20, 15, 92);
	pg_assert_true(is_string($data) && ($data !== ''), 'resize writes bytes');

	$image = imagecreatefromstring($data);
	pg_assert_same(20, imagesx($image), 'resized width');
	pg_assert_true(imagecolortransparent($image) < imagecolorstotal($image), 'resize: the transparent index is inside the palette');
	pg_assert_true(image_gd_gif_test_transparent($image, 15, 10), 'resize: the middle is transparent');
	pg_assert_false(image_gd_gif_test_transparent($image, 2, 2), 'resize: the block is opaque');
	imagedestroy($image);

	$data = pg_process_image_with_gd($path, IMAGETYPE_GIF, true, 20, 15, 92, '', 90);
	$image = imagecreatefromstring($data);
	pg_assert_same(15, imagesx($image), 'resize and rotate: width');
	pg_assert_true(image_gd_gif_test_transparent($image, 7, 10), 'resize and rotate: the middle is transparent');
	pg_assert_false(image_gd_gif_test_transparent($image, 12, 2), 'resize and rotate: the block is opaque');
	imagedestroy($image);

	unlink($path);
}

function test_image_gd_gif_opaque_gif_gets_no_transparent_index()
{
	if (!image_gd_gif_test_gd()) {
		return;
	}

	$path  = tempnam(sys_get_temp_dir(), 'pg_gif_test_');
	$image = imagecreate(40, 30);
	imagecolorallocate($image, 252, 2, 4);
	imagefilledrectangle($image, 0, 0, 9, 9, imagecolorallocate($image, 4, 2, 4));
	imagegif($image, $path);
	imagedestroy($image);

	$data  = pg_process_image_with_gd($path, IMAGETYPE_GIF, false, 0, 0, 92, '', 90);
	$image = imagecreatefromstring($data);
	pg_assert_same(-1, imagecolortransparent($image), 'an opaque GIF stays without a transparent index');
	pg_assert_same(array(252, 2, 4), image_gd_gif_test_rgb($image, 15, 20), 'the background colour is kept');
	imagedestroy($image);

	unlink($path);
}

function test_image_gd_gif_rotate_to_webp_keeps_alpha()
{
	if (!image_gd_gif_test_gd() || !function_exists('imagewebp') || !function_exists('imagecreatefromwebp')) {
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_gif_test_');
	image_gd_gif_test_file($path);

	$data = pg_process_image_with_gd($path, IMAGETYPE_GIF, false, 0, 0, 92, 'webp', 90);
	pg_assert_true(is_string($data) && ($data !== ''), 'webp writes bytes');

	$image = imagecreatefromstring($data);
	pg_assert_true(image_gd_gif_test_transparent($image, 20, 20), 'webp: the middle is transparent');
	pg_assert_false(image_gd_gif_test_transparent($image, 25, 5), 'webp: the block is opaque');
	imagedestroy($image);

	unlink($path);
}

function test_image_gd_palette_png_rotate_keeps_alpha()
{
	if (!image_gd_gif_test_gd() || !function_exists('imagepng') || !function_exists('imagecreatefrompng')) {
		return;
	}

	$path       = tempnam(sys_get_temp_dir(), 'pg_gif_test_');
	$image      = imagecreate(40, 30);
	$background = imagecolorallocate($image, 252, 2, 4);
	imagecolortransparent($image, $background);
	imagefilledrectangle($image, 0, 0, 9, 9, imagecolorallocate($image, 4, 2, 4));
	imagepng($image, $path);
	imagedestroy($image);

	$data  = pg_process_image_with_gd($path, IMAGETYPE_PNG, false, 0, 0, 92, '', 90);
	$image = imagecreatefromstring($data);
	pg_assert_true(image_gd_gif_test_transparent($image, 20, 20), 'png: the middle is transparent');
	pg_assert_false(image_gd_gif_test_transparent($image, 25, 5), 'png: the block is opaque');
	imagedestroy($image);

	unlink($path);
}
