<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image pipeline's limits and measures
 * (includes/image_engine.php): the memory guard refuses a picture too large
 * to decode even where PHP's memory limit is off, and the size the width and
 * height cache is given is the displayed one, EXIF orientation applied,
 * whether or not the engine wrote the file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A memory limit of -1 (php-cli without a php.ini, a batch screen that turns
// it off) used to wave any picture through: a 20000 px square takes GD about
// 4.8 GB to resize.
function test_image_memory_guard_holds_without_a_limit()
{
	$before = ini_get('memory_limit');

	try {
		ini_set('memory_limit', '-1');

		pg_assert_same(false, pg_image_ensure_memory(20000, 20000), 'over the ceiling with no limit');
		pg_assert_same(true, pg_image_ensure_memory(1000, 1000), 'a small picture with no limit');
		pg_assert_same('-1', (string) ini_get('memory_limit'), 'the limit left off');

	} finally {
		ini_set('memory_limit', $before);
	}

	pg_assert_same(false, pg_image_ensure_memory(20000, 20000), 'over the ceiling with the limit back');
}

// A JPEG drawn by GD at a low quality, with an EXIF block that says
// "turn a quarter clockwise to display" (orientation 6), the way a phone
// stores a portrait photo.
function image_pipeline_test_rotated_jpeg($path, $width, $height, $quality)
{
	$image = imagecreatetruecolor($width, $height);

	for ($y = 0; $y < $height; $y += 4) {
		imagefilledrectangle($image, 0, $y, $width - 1, $y + 3, imagecolorallocate($image, (int) (255 * $y / $height), 90, 160));
	}

	imagejpeg($image, $path, $quality);
	pg_image_destroy($image);

	$tiff = 'II' . pack('v', 42) . pack('V', 8)
		. pack('v', 1)
		. pack('v', 0x0112) . pack('v', 3) . pack('V', 1) . pack('v', 6) . pack('v', 0)
		. pack('V', 0);

	$exif = "Exif\x00\x00" . $tiff;
	$bytes = file_get_contents($path);

	file_put_contents($path, substr($bytes, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($bytes, 2));
}

function test_image_display_size_applies_orientation()
{
	if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$path = sys_get_temp_dir() . '/pg_image_pipeline_' . bin2hex(random_bytes(4)) . '.jpg';

	try {
		image_pipeline_test_rotated_jpeg($path, 120, 60, 90);

		pg_assert_same(array(60, 120), pg_image_display_size($path), 'a phone portrait is taller than wide');

		// An Exif block whose orientation cannot be read: the stored size,
		// not nothing, so the cache still has a size. Some exif builds read
		// such a block as orientation 1; the answer is the same either way.
		$bytes = file_get_contents($path);
		file_put_contents($path, str_replace('II' . pack('v', 42), 'XX' . pack('v', 42), $bytes));

		pg_assert_same(array(120, 60), pg_image_display_size($path), 'the stored size');

		file_put_contents($path, 'not a picture');
		pg_assert_same(array(0, 0), pg_image_display_size($path), 'not a picture');

	} finally {
		@unlink($path);
	}
}

// Optimize finding nothing to gain writes the report's size into the cache
// (optimize_image.php): it must be the displayed size, the one a write
// would have reported, not the sideways stored one.
function test_image_engine_no_gain_reports_the_displayed_size()
{
	// The GD half: Imagick may find a gain where GD finds none.
	if (class_exists('Imagick') || !function_exists('imagecreatetruecolor') || !function_exists('imagejpeg') || !function_exists('imagerotate')) {
		pg_assert_true(true, 'GD alone is not available; skipped');
		return;
	}

	$path = sys_get_temp_dir() . '/pg_image_pipeline_' . bin2hex(random_bytes(4)) . '.jpg';

	try {
		// Saved at quality 20: the optimize ladder's 85 makes it heavier.
		image_pipeline_test_rotated_jpeg($path, 120, 60, 20);

		$report = pg_process_image_file($path, array());

		pg_assert_same('no_gain', $report['reason'], 'nothing gained');
		pg_assert_same(false, $report['changed'], 'left as it was');
		pg_assert_same(array(60, 120), array($report['width'], $report['height']), 'displayed size');

	} finally {
		@unlink($path);
	}
}
