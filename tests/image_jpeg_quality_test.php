<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the quality a JPEG was saved at (pg_image_jpeg_quality()) and
 * the quality a save starts at (pg_image_jpeg_start_quality()): a turn of a
 * JPEG saved at about 70 stays near the original's size.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function image_jpeg_quality_available()
{
	return function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
}

// A 480×320 JPEG with gradients and blocks, as bytes.
function image_jpeg_quality_sample($quality)
{
	$image = imagecreatetruecolor(480, 320);

	for ($y = 0; $y < 320; $y += 4) {
		for ($x = 0; $x < 480; $x += 4) {
			$color = imagecolorallocate($image, ($x * 3 + $y) % 256, ($y * 5 + (intdiv($x, 16) % 2) * 60) % 256, ($x + $y * 2) % 256);
			imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $color);
		}
	}

	ob_start();
	imagejpeg($image, null, $quality);

	return ob_get_clean();
}

function test_image_jpeg_quality_reads_the_saved_quality()
{
	if (!image_jpeg_quality_available()) {
		return;
	}

	foreach (array(10, 35, 50, 70, 71, 85, 92, 95, 100) as $quality) {
		pg_assert_same($quality, pg_image_jpeg_quality(image_jpeg_quality_sample($quality)), 'a JPEG saved at ' . $quality);
	}
}

function test_image_jpeg_quality_refuses_what_is_not_a_jpeg()
{
	pg_assert_same(0, pg_image_jpeg_quality(''), 'no bytes');
	pg_assert_same(0, pg_image_jpeg_quality("\x89PNG\r\n\x1a\n" . str_repeat("\0", 40)), 'a PNG');
	pg_assert_same(0, pg_image_jpeg_quality("\xFF\xD8\xFF\xDB\x00\x43\x00" . str_repeat("\x01", 10)), 'a table cut short');
	pg_assert_same(0, pg_image_jpeg_quality("\xFF\xD8\xFF\xDA\x00\x02"), 'a scan before any table');
}

function test_image_jpeg_start_quality_is_the_lower_one()
{
	if (!image_jpeg_quality_available()) {
		return;
	}

	$dir = sys_get_temp_dir();
	$low = $dir . '/pg_jpeg_q70_' . getmypid() . '.jpg';
	$high = $dir . '/pg_jpeg_q98_' . getmypid() . '.jpg';
	$png = $dir . '/pg_jpeg_png_' . getmypid() . '.png';
	file_put_contents($low, image_jpeg_quality_sample(70));
	file_put_contents($high, image_jpeg_quality_sample(98));
	file_put_contents($png, "\x89PNG\r\n\x1a\n");

	pg_assert_same(70, pg_image_jpeg_start_quality($low), 'a JPEG saved at 70 starts at 70');
	pg_assert_same(92, pg_image_jpeg_start_quality($high), 'a JPEG saved at 98 starts at 92');
	pg_assert_same(92, pg_image_jpeg_start_quality($png), 'not a JPEG: 92');
	pg_assert_same(92, pg_image_jpeg_start_quality($dir . '/pg_missing_' . getmypid() . '.jpg'), 'no file: 92');

	// A turn of the JPEG saved at about 70, at its start quality, stays
	// within a few percent of the original (at 92 it grew 1.5-2.6 times).
	$turned = imagerotate(imagecreatefromjpeg($low), 90, 0);
	ob_start();
	imagejpeg($turned, null, pg_image_jpeg_start_quality($low));
	$after = strlen(ob_get_clean());
	$before = filesize($low);
	pg_assert_true(abs($after / $before - 1) < 0.1, 'turned: ' . $before . ' B before, ' . $after . ' B after');

	@unlink($low);
	@unlink($high);
	@unlink($png);
}
