<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the upload size cap (includes/fn/image.php): how the setting is
 * read (0 is off, anything else is held between 200 and 20000 px), which
 * pictures it shrinks (only those whose longest edge is above it), and - with
 * GD, over pictures the test draws itself - that a large JPEG comes back at
 * the cap in its own format while a small one is left byte for byte.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_image_upload_cap_clamp_reads_off_and_range()
{
	pg_assert_same(0, pg_image_upload_cap_clamp(0), 'zero is off');
	pg_assert_same(0, pg_image_upload_cap_clamp(-5), 'a negative number is off');
	pg_assert_same(0, pg_image_upload_cap_clamp('abc'), 'not a number is off');
	pg_assert_same(2560, pg_image_upload_cap_clamp('2560'), 'a number as text');
	pg_assert_same(200, pg_image_upload_cap_clamp(50), 'below the floor is the floor');
	pg_assert_same(20000, pg_image_upload_cap_clamp(65535), 'above the ceiling is the ceiling');
}

function test_image_upload_cap_wanted_only_above_the_cap()
{
	pg_assert_false(pg_image_upload_cap_wanted(0, 6000, 4000), 'off');
	pg_assert_false(pg_image_upload_cap_wanted(2560, 2560, 1440), 'exactly the cap');
	pg_assert_false(pg_image_upload_cap_wanted(2560, 1200, 800), 'smaller');
	pg_assert_true(pg_image_upload_cap_wanted(2560, 4000, 3000), 'wider');
	pg_assert_true(pg_image_upload_cap_wanted(2560, 3000, 4000), 'taller');
	pg_assert_false(pg_image_upload_cap_wanted(2560, 0, 0), 'not a picture');
}

// A JPEG of the given size, drawn with a gradient so it compresses like a
// photo rather than to nothing; '' without GD.
function image_upload_cap_test_jpeg($width, $height)
{
	if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
		return '';
	}

	$image = imagecreatetruecolor($width, $height);

	for ($x = 0; $x < $width; $x += 4) {
		imagefilledrectangle($image, $x, 0, $x + 3, $height - 1, imagecolorallocate($image, $x % 256, ($x * 7) % 256, ($x * 13) % 256));
	}

	$path = tempnam(sys_get_temp_dir(), 'pg_cap_test_') . '.jpg';
	imagejpeg($image, $path, 95);
	pg_image_destroy($image);

	return $path;
}

function test_image_upload_cap_apply_shrinks_a_large_picture()
{
	$path = image_upload_cap_test_jpeg(900, 600);

	if ($path === '') {
		return;
	}

	$report = pg_image_upload_cap_apply($path, 300, 82);
	$size = getimagesize($path);
	@unlink($path);

	pg_assert_true(is_array($report), 'a report');
	pg_assert_true($report['resized'], 'resized');
	pg_assert_same(300, (int) $size[0], 'the long edge is the cap');
	pg_assert_same(200, (int) $size[1], 'the proportions kept');
	pg_assert_same(IMAGETYPE_JPEG, (int) $size[2], 'the same format');
}

function test_image_upload_cap_apply_leaves_a_small_picture()
{
	$path = image_upload_cap_test_jpeg(300, 200);

	if ($path === '') {
		return;
	}

	$before = md5_file($path);
	$report = pg_image_upload_cap_apply($path, 300, 82);
	$after = md5_file($path);
	@unlink($path);

	pg_assert_same(null, $report, 'no report: nothing done');
	pg_assert_same($before, $after, 'byte for byte');
}

function test_image_upload_cap_apply_leaves_what_is_not_a_picture()
{
	$path = tempnam(sys_get_temp_dir(), 'pg_cap_test_');
	file_put_contents($path, 'not a picture');

	$report = pg_image_upload_cap_apply($path, 300, 82);
	$after = file_get_contents($path);
	@unlink($path);

	pg_assert_same(null, $report, 'no report');
	pg_assert_same('not a picture', $after, 'untouched');
}
