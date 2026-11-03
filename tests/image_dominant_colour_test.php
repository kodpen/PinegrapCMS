<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for get_dominant_area_color() (includes/fn/image.php), the accent
 * colour the edit file and order screens take from a picture: a palette
 * image (GIF, 8-bit PNG) gives its colour, not its palette index, and fully
 * transparent pixels do not count.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Writes $image with $write ('imagegif', 'imagepng') to a temporary file and
// returns the colour read back from it.
function image_dominant_colour_test_read($image, $write)
{
	$file = tempnam(sys_get_temp_dir(), 'pg_dominant_');

	try {
		call_user_func($write, $image, $file);
		return get_dominant_area_color($file);
	} finally {
		@unlink($file);
	}
}

function test_image_dominant_colour_palette_gif()
{
	if (!function_exists('imagegif')) {
		pg_assert_true(true, 'GD writes no GIF here; skipped');
		return;
	}

	$image = imagecreate(40, 40);
	imagecolorallocate($image, 0, 0, 0);
	$red = imagecolorallocate($image, 200, 0, 0);
	imagefill($image, 0, 0, $red);

	pg_assert_same('#c00000', image_dominant_colour_test_read($image, 'imagegif'), 'palette GIF gives its colour');
}

function test_image_dominant_colour_palette_png()
{
	if (!function_exists('imagepng')) {
		pg_assert_true(true, 'GD writes no PNG here; skipped');
		return;
	}

	$image = imagecreate(40, 40);
	imagecolorallocate($image, 0, 128, 0);

	pg_assert_same('#008000', image_dominant_colour_test_read($image, 'imagepng'), 'palette PNG gives its colour');
}

function test_image_dominant_colour_transparent_pixels_ignored()
{
	if (!function_exists('imagepng')) {
		pg_assert_true(true, 'GD writes no PNG here; skipped');
		return;
	}

	// A logo: a blue square in the middle of a mostly transparent picture.
	$image = imagecreatetruecolor(60, 60);
	imagealphablending($image, false);
	imagesavealpha($image, true);
	imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
	imagefilledrectangle($image, 20, 20, 39, 39, imagecolorallocate($image, 0, 0, 224));

	pg_assert_same('#0000e0', image_dominant_colour_test_read($image, 'imagepng'), 'transparent background is not the colour');

	$empty = imagecreatetruecolor(20, 20);
	imagealphablending($empty, false);
	imagesavealpha($empty, true);
	imagefill($empty, 0, 0, imagecolorallocatealpha($empty, 0, 0, 0, 127));

	pg_assert_same('#9696968e', image_dominant_colour_test_read($empty, 'imagepng'), 'fully transparent picture gives the fallback');
}

function test_image_dominant_colour_truecolor_jpeg()
{
	if (!function_exists('imagejpeg')) {
		pg_assert_true(true, 'GD writes no JPEG here; skipped');
		return;
	}

	$image = imagecreatetruecolor(40, 40);
	imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

	pg_assert_same('#ffffff', image_dominant_colour_test_read($image, 'imagejpeg'), 'white JPEG stays white');
}
