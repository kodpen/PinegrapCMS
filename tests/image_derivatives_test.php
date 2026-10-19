<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the responsive image derivatives (includes/image_engine.php):
 * what a query string asks for, which width and format it gets, the names a
 * derivative is kept and saved under, and - with GD, over pictures the test
 * draws itself - that a derivative is made once, found again, replaced when
 * its source changes, not made while another request holds the lock, and not
 * made for an animated GIF.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_image_derivative_request_reads_width_and_format()
{
	pg_assert_same(array('width' => 960, 'format' => ''), pg_image_derivative_request(array('w' => '960')), 'a width');
	pg_assert_same(array('width' => 0, 'format' => 'webp'), pg_image_derivative_request(array('f' => 'WebP')), 'a format, any case');
	pg_assert_same(array('width' => 480, 'format' => 'avif'), pg_image_derivative_request(array('w' => '480', 'f' => ' avif ')), 'both');
	pg_assert_same(array('width' => 0, 'format' => 'avif'), pg_image_derivative_request(array('w' => '96O', 'f' => 'avif')), 'a width that is not a number is dropped');
}

function test_image_derivative_request_ignores_the_rest()
{
	pg_assert_same(null, pg_image_derivative_request(array()), 'nothing asked');
	pg_assert_same(null, pg_image_derivative_request(array('name' => 'a.jpg', 't' => 'x')), 'other parameters');
	pg_assert_same(null, pg_image_derivative_request(array('w' => '0')), 'width zero');
	pg_assert_same(null, pg_image_derivative_request(array('w' => '-480')), 'a negative width');
	pg_assert_same(null, pg_image_derivative_request(array('w' => '123456')), 'more digits than any width');
	pg_assert_same(null, pg_image_derivative_request(array('w' => array('960'))), 'an array');
	pg_assert_same(null, pg_image_derivative_request(array('f' => 'png')), 'a format that is not made');
	pg_assert_same(null, pg_image_derivative_request(array('f' => array('webp'))), 'a format array');
	pg_assert_same(null, pg_image_derivative_request('w=960'), 'not an array');
}

function test_image_derivative_width_rounds_up_and_caps()
{
	pg_assert_same(480, pg_image_derivative_width(1, 4000), 'the narrowest');
	pg_assert_same(480, pg_image_derivative_width(480, 4000), 'exactly a step');
	pg_assert_same(960, pg_image_derivative_width(481, 4000), 'just past a step');
	pg_assert_same(960, pg_image_derivative_width(700, 4000), 'between steps');
	pg_assert_same(1920, pg_image_derivative_width(5000, 4000), 'above the widest');
	pg_assert_same(1200, pg_image_derivative_width(1440, 1200), 'never wider than the source');
	pg_assert_same(1200, pg_image_derivative_width(1920, 1200), 'every larger step lands on the same width');
	pg_assert_same(1200, pg_image_derivative_width(0, 1200), 'no width asked is the source width');
	pg_assert_same(0, pg_image_derivative_width(960, 0), 'a source with no width');
}

function test_image_derivative_long_edge_counts_width()
{
	pg_assert_same(960, pg_image_derivative_long_edge(960, 4000, 3000), 'landscape: the width is the long edge');
	pg_assert_same(960, pg_image_derivative_long_edge(960, 2000, 2000), 'square');
	pg_assert_same(1440, pg_image_derivative_long_edge(480, 1000, 3000), 'portrait: the height at that width');
	pg_assert_same(0, pg_image_derivative_long_edge(480, 0, 3000), 'no source');
	pg_assert_same(0, pg_image_derivative_long_edge(0, 1000, 3000), 'no width');
}

function test_image_derivative_format_falls_back()
{
	$all = array('webp' => true, 'avif' => true);
	$webp_only = array('webp' => true, 'avif' => false);
	$none = array('webp' => false, 'avif' => false);

	pg_assert_same('avif', pg_image_derivative_format('avif', 'jpg', $all), 'avif when it can be written');
	pg_assert_same('webp', pg_image_derivative_format('avif', 'jpg', $webp_only), 'webp in place of avif');
	pg_assert_same('', pg_image_derivative_format('avif', 'jpg', $none), 'the source format when neither can');
	pg_assert_same('webp', pg_image_derivative_format('webp', 'png', $all), 'webp');
	pg_assert_same('', pg_image_derivative_format('webp', 'png', $none), 'no webp writer');
	pg_assert_same('', pg_image_derivative_format('webp', 'webp', $all), 'webp from webp is no conversion');
	pg_assert_same('', pg_image_derivative_format('avif', 'webp', $webp_only), 'the webp fallback for a webp source');
	pg_assert_same('avif', pg_image_derivative_format('avif', 'webp', $all), 'a webp source to avif');
	pg_assert_same('', pg_image_derivative_format('', 'jpg', $all), 'no format asked');
}

function test_image_derivative_sources_and_quality()
{
	foreach (array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif') as $extension) {
		pg_assert_true(pg_image_derivative_source_ok($extension), $extension);
	}

	foreach (array('svg', 'avif', 'pdf', 'ico', '', 'JPG') as $extension) {
		pg_assert_false(pg_image_derivative_source_ok($extension), var_export($extension, true));
	}

	pg_assert_same(50, pg_image_derivative_quality('avif'), 'avif');
	pg_assert_same(80, pg_image_derivative_quality('WEBP'), 'webp');
	pg_assert_same(82, pg_image_derivative_quality('jpg'), 'the source format');
}

function test_image_derivative_names()
{
	$signature = pg_image_derivative_signature(1760000000, 123456, 80);

	pg_assert_true(preg_match('/^[0-9a-f]{12}$/', $signature) === 1, 'twelve hex characters');
	pg_assert_same($signature, pg_image_derivative_signature(1760000000, 123456, 80), 'the same bytes, the same signature');
	pg_assert_true($signature !== pg_image_derivative_signature(1760000001, 123456, 80), 'a new time');
	pg_assert_true($signature !== pg_image_derivative_signature(1760000000, 123457, 80), 'a new size');
	pg_assert_true($signature !== pg_image_derivative_signature(1760000000, 123456, 82), 'a new quality');

	pg_assert_same('960-' . $signature . '.webp', pg_image_derivative_file_name(960, $signature, 'webp'), 'file name');

	pg_assert_same('photo-960.webp', pg_image_derivative_download_name('photo.jpg', 960, 'webp'), 'saved under the new extension');
	pg_assert_same('a.b-480.avif', pg_image_derivative_download_name('a.b.png', 480, 'avif'), 'only the last extension goes');
	pg_assert_same('noext-480.jpg', pg_image_derivative_download_name('noext', 480, 'jpg'), 'no extension');
	pg_assert_same('.hidden-480.png', pg_image_derivative_download_name('.hidden', 480, 'png'), 'a leading dot is not an extension');
}

// -- with GD -------------------------------------------------------------------

function image_derivatives_test_gd()
{
	return function_exists('imagecreatetruecolor') && function_exists('imagejpeg') && function_exists('imagecreatefromjpeg');
}

function image_derivatives_test_folder()
{
	$folder = sys_get_temp_dir() . '/pg_image_derivatives_test_' . bin2hex(random_bytes(4));
	mkdir($folder . '/files', 0755, true);

	return $folder;
}

function image_derivatives_test_remove($path)
{
	if (is_dir($path) && !is_link($path)) {
		foreach (scandir($path) as $entry) {
			if (($entry !== '.') && ($entry !== '..')) {
				image_derivatives_test_remove($path . '/' . $entry);
			}
		}

		rmdir($path);

	} elseif (file_exists($path) || is_link($path)) {
		unlink($path);
	}
}

// A photo-like JPEG: coloured blocks over a gradient, saved at a high quality
// so a smaller derivative is lighter by a margin.
function image_derivatives_test_jpeg($path, $width, $height, $seed)
{
	$image = imagecreatetruecolor($width, $height);

	mt_srand($seed);

	for ($y = 0; $y < $height; $y += 8) {
		imagefilledrectangle($image, 0, $y, $width - 1, $y + 7, imagecolorallocate($image, (int) (255 * $y / $height), 120, 200));
	}

	for ($i = 0; $i < 3000; $i++) {
		$x = mt_rand(0, $width - 1);
		$y = mt_rand(0, $height - 1);
		imagefilledrectangle($image, $x, $y, $x + mt_rand(4, 40), $y + mt_rand(4, 40), imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
	}

	imagejpeg($image, $path, 95);
	imagedestroy($image);
}

// The same JPEG with an EXIF block that says "turn a quarter clockwise to
// display" (orientation 6), the way a phone stores a portrait photo.
function image_derivatives_test_rotated_jpeg($path, $width, $height)
{
	image_derivatives_test_jpeg($path, $width, $height, 7);

	// TIFF header (little endian), one IFD entry: 0x0112 Orientation, SHORT, 1, value 6.
	$tiff = 'II' . pack('v', 42) . pack('V', 8)
		. pack('v', 1)
		. pack('v', 0x0112) . pack('v', 3) . pack('V', 1) . pack('v', 6) . pack('v', 0)
		. pack('V', 0);

	$exif = "Exif\x00\x00" . $tiff;
	$segment = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;

	$bytes = file_get_contents($path);

	// Straight after the start-of-image marker.
	file_put_contents($path, substr($bytes, 0, 2) . $segment . substr($bytes, 2));
}

function test_image_derivative_made_once_and_found_again()
{
	if (!image_derivatives_test_gd()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_derivatives_test_folder();
	$source = $folder . '/files/photo.jpg';

	try {
		image_derivatives_test_jpeg($source, 2000, 1000, 1);

		$first = pg_image_derivative(12, $source, array('width' => 700, 'format' => ''), $folder . '/cache');

		pg_assert_same('derivative', $first['status'], 'made');
		pg_assert_same(960, $first['width'], 'at the next step');
		pg_assert_same('jpg', $first['extension'], 'in the source format');
		pg_assert_same($folder . '/cache/12', dirname($first['path']), 'in the file id folder');
		pg_assert_true(filesize($first['path']) < filesize($source), 'lighter than the original');

		$size = getimagesize($first['path']);
		pg_assert_same(array(960, 480), array($size[0], $size[1]), 'its dimensions');

		pg_assert_false(file_exists($first['path'] . '.lock'), 'the lock is gone');

		$written = filemtime($first['path']);
		$again = pg_image_derivative(12, $source, array('width' => 960, 'format' => ''), $folder . '/cache');

		pg_assert_same($first['path'], $again['path'], 'another width of the same step finds the same file');
		pg_assert_same($written, filemtime($again['path']), 'not made again');

		$small = $folder . '/files/small.jpg';
		image_derivatives_test_jpeg($small, 900, 600, 4);

		$original = pg_image_derivative(13, $small, array('width' => 960, 'format' => ''), $folder . '/cache');
		pg_assert_same('original', $original['status'], 'no derivative at the full size in the same format');
		pg_assert_same($small, $original['path'], 'the original is sent');
		pg_assert_false(is_dir($folder . '/cache/13'), 'and nothing is kept for it');

		if (function_exists('imagewebp') && (pg_image_format_writer('webp') !== '')) {
			$webp = pg_image_derivative(12, $source, array('width' => 480, 'format' => 'webp'), $folder . '/cache');

			pg_assert_same('derivative', $webp['status'], 'webp made');
			pg_assert_same('webp', $webp['extension'], 'webp extension');

			$size = getimagesize($webp['path']);
			pg_assert_same(array(480, 240, 'image/webp'), array($size[0], $size[1], $size['mime']), 'webp dimensions and type');
		}

		// The source is replaced: a new time and new bytes, a new derivative,
		// and the old one of the same width and format is removed.
		image_derivatives_test_jpeg($source, 2000, 1000, 2);
		touch($source, time() + 60);
		clearstatcache();

		$replaced = pg_image_derivative(12, $source, array('width' => 960, 'format' => ''), $folder . '/cache');

		pg_assert_same('derivative', $replaced['status'], 'made again');
		pg_assert_true($replaced['path'] !== $first['path'], 'under a new name');
		pg_assert_false(file_exists($first['path']), 'the old derivative is removed');

	} finally {
		image_derivatives_test_remove($folder);
	}
}

function test_image_derivative_follows_exif_orientation()
{
	if (!image_derivatives_test_gd() || !function_exists('exif_read_data') || !function_exists('imagerotate')) {
		pg_assert_true(true, 'GD with exif is not available; skipped');
		return;
	}

	$folder = image_derivatives_test_folder();
	$source = $folder . '/files/portrait.jpg';

	try {
		// Stored 2000 x 1000 on its side, displayed 1000 wide and 2000 tall.
		image_derivatives_test_rotated_jpeg($source, 2000, 1000);

		pg_assert_same(6, pg_image_jpeg_orientation($source), 'orientation read');
		pg_assert_same(array(1000, 2000), pg_image_oriented_size($source), 'displayed size');

		$derivative = pg_image_derivative(3, $source, array('width' => 480, 'format' => ''), $folder . '/cache');

		pg_assert_same('derivative', $derivative['status'], 'made');

		$size = getimagesize($derivative['path']);
		pg_assert_same(array(480, 960), array($size[0], $size[1]), 'upright, 480 wide');

	} finally {
		image_derivatives_test_remove($folder);
	}
}

function test_image_derivative_busy_while_locked()
{
	if (!image_derivatives_test_gd()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_derivatives_test_folder();
	$source = $folder . '/files/photo.jpg';

	try {
		image_derivatives_test_jpeg($source, 1600, 900, 3);

		// Where the derivative will go, worked out the way the function does.
		$quality = pg_image_derivative_quality('jpg');
		$name = pg_image_derivative_file_name(960, pg_image_derivative_signature(filemtime($source), filesize($source), $quality), 'jpg');

		mkdir($folder . '/cache/5', 0755, true);
		$lock = fopen($folder . '/cache/5/' . $name . '.lock', 'c');
		flock($lock, LOCK_EX);

		$busy = pg_image_derivative(5, $source, array('width' => 960, 'format' => ''), $folder . '/cache');

		pg_assert_same('busy', $busy['status'], 'another request is making it');
		pg_assert_same($source, $busy['path'], 'the original meanwhile');
		pg_assert_false(file_exists($folder . '/cache/5/' . $name), 'nothing made');

		flock($lock, LOCK_UN);
		fclose($lock);

		$made = pg_image_derivative(5, $source, array('width' => 960, 'format' => ''), $folder . '/cache');

		pg_assert_same('derivative', $made['status'], 'made once the lock is free');
		pg_assert_same($name, basename($made['path']), 'under that name');

	} finally {
		image_derivatives_test_remove($folder);
	}
}

function test_image_derivative_leaves_animated_gif()
{
	$folder = image_derivatives_test_folder();
	$source = $folder . '/files/moving.gif';

	// A GIF whose logical screen is 1000 x 500, with two frames.
	$frame = "\x21\xF9\x04\x00\x0A\x00\x00\x00"
		. "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x80\x00\x00\x00\xFF\xFF\xFF"
		. "\x02\x02\x44\x01\x00";

	file_put_contents($source, 'GIF89a' . pack('v', 1000) . pack('v', 500) . "\x00\x00\x00" . $frame . $frame . "\x3B");

	try {
		$first = pg_image_derivative(9, $source, array('width' => 480, 'format' => ''), $folder . '/cache');

		pg_assert_same('original', $first['status'], 'the animation is sent as it is');

		$markers = glob($folder . '/cache/9/480-*.gif.skip');
		pg_assert_same(1, count($markers), 'and that is remembered');

		$second = pg_image_derivative(9, $source, array('width' => 480, 'format' => ''), $folder . '/cache');
		pg_assert_same('original', $second['status'], 'the next request reads the marker');

	} finally {
		image_derivatives_test_remove($folder);
	}
}

// An attempt whose process died left its .try marker: the next request does
// not try again, it sends the original and remembers that.
function test_image_derivative_one_try_after_a_crash()
{
	if (!image_derivatives_test_gd()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_derivatives_test_folder();
	$source = $folder . '/files/photo.jpg';

	try {
		image_derivatives_test_jpeg($source, 1600, 900, 5);

		$name = pg_image_derivative_file_name(480, pg_image_derivative_signature(filemtime($source), filesize($source), 82), 'jpg');

		mkdir($folder . '/cache/8', 0755, true);
		touch($folder . '/cache/8/' . $name . '.try');

		$after = pg_image_derivative(8, $source, array('width' => 480, 'format' => ''), $folder . '/cache');

		pg_assert_same('original', $after['status'], 'not tried again');
		pg_assert_true(is_file($folder . '/cache/8/' . $name . '.skip'), 'remembered');
		pg_assert_false(file_exists($folder . '/cache/8/' . $name . '.try'), 'the marker is gone');
		pg_assert_false(file_exists($folder . '/cache/8/' . $name), 'nothing made');

		$ordinary = pg_image_derivative(8, $source, array('width' => 960, 'format' => ''), $folder . '/cache');

		pg_assert_same('derivative', $ordinary['status'], 'another width is made as usual');
		pg_assert_same(array($name . '.skip', basename($ordinary['path'])), array_values(array_diff(scandir($folder . '/cache/8'), array('.', '..'))), 'and leaves no marker behind');

	} finally {
		image_derivatives_test_remove($folder);
	}
}

function test_image_derivative_refuses_what_is_not_a_raster_image()
{
	$folder = image_derivatives_test_folder();

	try {
		file_put_contents($folder . '/files/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="900"></svg>');
		file_put_contents($folder . '/files/fake.jpg', 'not a picture at all');

		$svg = pg_image_derivative(1, $folder . '/files/logo.svg', array('width' => 480, 'format' => 'webp'), $folder . '/cache');
		pg_assert_same('original', $svg['status'], 'svg');

		$fake = pg_image_derivative(2, $folder . '/files/fake.jpg', array('width' => 480, 'format' => 'webp'), $folder . '/cache');
		pg_assert_same('original', $fake['status'], 'not an image');

		$no_id = pg_image_derivative(0, $folder . '/files/fake.jpg', array('width' => 480, 'format' => ''), $folder . '/cache');
		pg_assert_same('original', $no_id['status'], 'no file id');

		$no_root = pg_image_derivative(2, $folder . '/files/fake.jpg', array('width' => 480, 'format' => ''), '');
		pg_assert_same('original', $no_root['status'], 'no cache folder');

		pg_assert_false(is_dir($folder . '/cache'), 'nothing was written');

	} finally {
		image_derivatives_test_remove($folder);
	}
}
