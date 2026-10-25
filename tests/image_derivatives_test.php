<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the responsive image derivatives (includes/image_engine.php):
 * what a query string asks for, which width and format it gets, the names a
 * derivative is kept and saved under, and - with GD, over pictures the test
 * draws itself - that a derivative is made once, found again, replaced when
 * its source changes, not made while another request holds the lock, and not
 * made for an animated GIF; and that a phone photo's EXIF orientation is read
 * without the exif extension too.
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

	$settings = pg_image_delivery_settings(array('image_quality_webp' => 70, 'image_quality_avif' => 40, 'image_resize_quality' => 90));

	pg_assert_same(40, pg_image_derivative_quality('avif', $settings), 'avif as set');
	pg_assert_same(70, pg_image_derivative_quality('webp', $settings), 'webp as set');
	pg_assert_same(90, pg_image_derivative_quality('png', $settings), 'the source format at the resize quality');
}

// The config row as get_file.php and pg_image_settings() read it.
function test_image_delivery_settings()
{
	$defaults = array(
		'formats' => array('webp' => true, 'avif' => true),
		'quality' => array('webp' => 80, 'avif' => 50, 'source' => 82));

	pg_assert_same($defaults, pg_image_delivery_settings(null), 'no row: what the software did before the settings');
	pg_assert_same($defaults, pg_image_delivery_settings(array('image_resize_quality' => '82')), 'a row without the new columns');

	$row = array('image_derivative_formats' => '1', 'image_quality_webp' => '75', 'image_quality_avif' => '45', 'image_resize_quality' => '85');
	pg_assert_same(array(
		'formats' => array('webp' => true, 'avif' => false),
		'quality' => array('webp' => 75, 'avif' => 45, 'source' => 85)), pg_image_delivery_settings($row), 'as stored');

	$formats = function ($bits) {
		$settings = pg_image_delivery_settings(array('image_derivative_formats' => $bits));
		return $settings['formats'];
	};

	pg_assert_same(array('webp' => false, 'avif' => false), $formats(0), 'both off');
	pg_assert_same(array('webp' => false, 'avif' => true), $formats(2), 'avif only');
	pg_assert_same(array('webp' => true, 'avif' => true), $formats(7), 'unknown bits ignored');

	$low = pg_image_delivery_settings(array('image_quality_webp' => 0, 'image_quality_avif' => 5, 'image_resize_quality' => 10));
	pg_assert_same(array('webp' => 40, 'avif' => 20, 'source' => 40), $low['quality'], 'raised to the floor of each scale');

	$high = pg_image_delivery_settings(array('image_quality_webp' => 255, 'image_quality_avif' => 120, 'image_resize_quality' => 101));
	pg_assert_same(array('webp' => 100, 'avif' => 100, 'source' => 100), $high['quality'], 'held at 100');

	$typed = pg_image_delivery_settings(array('image_quality_webp' => '', 'image_quality_avif' => 'abc'));
	pg_assert_same(array('webp' => 80, 'avif' => 50, 'source' => 82), $typed['quality'], 'an empty or non-numeric field keeps the default');
}

function test_image_derivative_offered()
{
	$writable = array('webp' => true, 'avif' => false);

	pg_assert_same(array('webp' => true, 'avif' => false), pg_image_derivative_offered($writable), 'what the server writes, by default');
	pg_assert_same(array('webp' => false, 'avif' => false), pg_image_derivative_offered($writable, pg_image_delivery_settings(array('image_derivative_formats' => 2))), 'avif on cannot make the server write it');
	pg_assert_same(array('webp' => false, 'avif' => false), pg_image_derivative_offered(array('webp' => true, 'avif' => true), pg_image_delivery_settings(array('image_derivative_formats' => 0))), 'both switched off');
	pg_assert_same(array('webp' => false, 'avif' => true), pg_image_derivative_offered(array('webp' => true, 'avif' => true), pg_image_delivery_settings(array('image_derivative_formats' => 2))), 'webp switched off');
}

// A format switched off is answered like one the server cannot write, and the
// quality is part of the name.
function test_image_derivative_follows_the_settings()
{
	if (!image_derivatives_test_gd() || !function_exists('imagewebp') || (pg_image_format_writer('webp') === '')) {
		pg_assert_true(true, 'GD with WebP is not available; skipped');
		return;
	}

	$folder = image_derivatives_test_folder();
	$source = $folder . '/files/photo.jpg';

	try {
		image_derivatives_test_jpeg($source, 2000, 1000, 3);

		$off = pg_image_delivery_settings(array('image_derivative_formats' => 0));
		$plain = pg_image_derivative(21, $source, array('width' => 960, 'format' => 'webp'), $folder . '/cache', $off);

		pg_assert_same('derivative', $plain['status'], 'still a smaller picture');
		pg_assert_same('jpg', $plain['extension'], 'in the source format when webp is off');

		$avif_off = pg_image_delivery_settings(array('image_derivative_formats' => 1));
		$webp = pg_image_derivative(21, $source, array('width' => 960, 'format' => 'avif'), $folder . '/cache', $avif_off);

		pg_assert_same('webp', $webp['extension'], 'avif off: webp instead');

		$first = pg_image_derivative(21, $source, array('width' => 480, 'format' => 'webp'), $folder . '/cache', pg_image_delivery_settings(array('image_quality_webp' => 80)));
		$second = pg_image_derivative(21, $source, array('width' => 480, 'format' => 'webp'), $folder . '/cache', pg_image_delivery_settings(array('image_quality_webp' => 60)));

		pg_assert_true($first['path'] !== $second['path'], 'another quality, another name');
		pg_assert_false(file_exists($first['path']), 'and the one of the old quality goes');
		pg_assert_true(is_file($second['path']), 'the new one is made');

	} finally {
		image_derivatives_test_remove($folder);
	}
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
	pg_image_destroy($image);
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

// A JPEG drawn by GD with $segments (whole marker segments) put straight
// after its start-of-image marker.
function image_derivatives_test_jpeg_with($path, $segments)
{
	image_derivatives_test_jpeg($path, 64, 32, 5);

	$bytes = file_get_contents($path);

	file_put_contents($path, substr($bytes, 0, 2) . $segments . substr($bytes, 2));
}

// An APP1 segment holding "Exif\0\0" and these TIFF bytes.
function image_derivatives_test_exif_segment($tiff)
{
	$exif = "Exif\x00\x00" . $tiff;

	return "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
}

// A TIFF block with one IFD0 entry, in either byte order.
function image_derivatives_test_tiff($big_endian, $tag, $type, $value)
{
	$short = $big_endian ? 'n' : 'v';
	$long = $big_endian ? 'N' : 'V';

	return ($big_endian ? 'MM' : 'II') . pack($short, 42) . pack($long, 8)
		. pack($short, 1)
		. pack($short, $tag) . pack($short, $type) . pack($long, 1)
		. (($type === 4) ? pack($long, $value) : (pack($short, $value) . pack($short, 0)))
		. pack($long, 0);
}

// The orientation read from the file's own bytes, for a server without the
// exif extension: 1 when there is nothing to read, 0 when there is an Exif
// block that cannot be read.
function test_image_jpeg_exif_orientation_read_by_hand()
{
	if (!image_derivatives_test_gd()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_derivatives_test_folder();
	$path = $folder . '/files/photo.jpg';

	$app0 = "\xFF\xE0" . pack('n', 16) . "JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
	$xmp_data = "http://ns.adobe.com/xap/1.0/\x00<x:xmpmeta/>";
	$xmp = "\xFF\xE1" . pack('n', strlen($xmp_data) + 2) . $xmp_data;

	$cases = array(
		'little endian, 6'                   => array(image_derivatives_test_exif_segment(image_derivatives_test_tiff(false, 0x0112, 3, 6)), 6),
		'big endian after JFIF and XMP, 8'   => array($app0 . $xmp . image_derivatives_test_exif_segment(image_derivatives_test_tiff(true, 0x0112, 3, 8)), 8),
		'as a LONG'                          => array(image_derivatives_test_exif_segment(image_derivatives_test_tiff(true, 0x0112, 4, 3)), 3),
		'no Exif block'                      => array('', 1),
		'Exif without an orientation'        => array(image_derivatives_test_exif_segment(image_derivatives_test_tiff(false, 0x010F, 3, 6)), 1),
		'a value outside 1-8 counts as none' => array(image_derivatives_test_exif_segment(image_derivatives_test_tiff(false, 0x0112, 3, 9)), 1),
		'unknown byte order'                 => array(image_derivatives_test_exif_segment('XX' . substr(image_derivatives_test_tiff(false, 0x0112, 3, 6), 2)), 0),
		'IFD0 outside the block'             => array(image_derivatives_test_exif_segment('II' . pack('v', 42) . pack('V', 4000) . str_repeat("\x00", 20)), 0),
		'entries running past the block'     => array(image_derivatives_test_exif_segment('II' . pack('v', 42) . pack('V', 8) . pack('v', 50) . str_repeat("\x00", 12)), 0),
		'an orientation of an unknown type'  => array(image_derivatives_test_exif_segment(image_derivatives_test_tiff(false, 0x0112, 7, 6)), 0),
		'an empty Exif block'                => array(image_derivatives_test_exif_segment(''), 1),
		'a TIFF header cut short'            => array(image_derivatives_test_exif_segment('II*'), 0),
	);

	// What pg_image_jpeg_orientation() gives with or without the exif
	// extension; Imagick reads a broken block its own way.
	$engine = array(
		'little endian, 6'    => 6,
		'no Exif block'       => 1,
		'an empty Exif block' => 1,
	);

	if (!class_exists('Imagick')) {
		$engine['unknown byte order'] = 0;
	}

	try {
		foreach ($cases as $label => $case) {
			image_derivatives_test_jpeg_with($path, $case[0]);

			pg_assert_same($case[1], pg_image_jpeg_exif_orientation($path), $label);

			if (isset($engine[$label])) {
				pg_assert_same($engine[$label], pg_image_jpeg_orientation($path), 'engine: ' . $label);
			}
		}

		// The Exif segment says it is longer than the file.
		file_put_contents($path, "\xFF\xD8" . substr(image_derivatives_test_exif_segment(image_derivatives_test_tiff(false, 0x0112, 3, 6)), 0, 20));
		pg_assert_same(0, pg_image_jpeg_exif_orientation($path), 'cut short');

		file_put_contents($path, "\x89PNG\r\n\x1A\n");
		pg_assert_same(1, pg_image_jpeg_exif_orientation($path), 'not a JPEG');

		pg_assert_same(1, pg_image_jpeg_exif_orientation($folder . '/files/missing.jpg'), 'no such file');

	} finally {
		image_derivatives_test_remove($folder);
	}
}

// An Exif block whose orientation cannot be read: which way up a browser
// shows the photo is not known, so no derivative or srcset width is made
// from it and the GD half does not write over it.
function test_image_unreadable_orientation_left_alone()
{
	if (!image_derivatives_test_gd() || class_exists('Imagick')) {
		pg_assert_true(true, 'GD alone is not what runs here; skipped');
		return;
	}

	$folder = image_derivatives_test_folder();
	$source = $folder . '/files/photo.jpg';

	try {
		image_derivatives_test_jpeg($source, 2000, 1000, 9);

		$bytes = file_get_contents($source);
		file_put_contents($source, substr($bytes, 0, 2) . image_derivatives_test_exif_segment('XX' . substr(image_derivatives_test_tiff(false, 0x0112, 3, 6), 2)) . substr($bytes, 2));

		$before = md5_file($source);

		pg_assert_same(0, pg_image_jpeg_orientation($source), 'not readable');
		pg_assert_same(array(0, 0), pg_image_oriented_size($source), 'no displayed size');

		$derivative = pg_image_derivative(4, $source, array('width' => 480, 'format' => ''), $folder . '/cache');

		pg_assert_same('original', $derivative['status'], 'the original is sent');
		pg_assert_false(is_dir($folder . '/cache/4'), 'nothing is made');

		$optimized = pg_process_image_file($source);

		pg_assert_same('skipped', $optimized['status'], 'optimize');
		pg_assert_same('orientation_unreadable', $optimized['reason']);
		pg_assert_false($optimized['changed']);

		$turned = pg_process_image_file($source, array('rotate' => 90));

		pg_assert_same('orientation_unreadable', $turned['reason'], 'rotate');
		pg_assert_same($before, md5_file($source), 'the file is not touched');

	} finally {
		image_derivatives_test_remove($folder);
	}
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

// Read with the exif extension where there is one, by hand where there is
// none: the result is the same upright picture either way.
function test_image_derivative_follows_exif_orientation()
{
	if (!image_derivatives_test_gd() || !function_exists('imagerotate')) {
		pg_assert_true(true, 'GD is not available; skipped');
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

		$processed = pg_process_image_file($source, array('write' => false));

		pg_assert_same('success', $processed['status'], 'optimized');
		pg_assert_same(array(1000, 2000), array($processed['width'], $processed['height']), 'optimized upright');

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

// The formats this server writes are tried once and kept; a stored answer
// under the same key and younger than a week is believed, an older one is
// tried again, and nothing is claimed that no library offers.
function test_image_derivative_formats_are_tried_and_kept()
{
	$folder = image_derivatives_test_folder();

	try {
		$claims = array(
			'webp' => (pg_image_format_writer('webp') !== ''),
			'avif' => (pg_image_format_writer('avif') !== ''));

		$tried = pg_image_derivative_formats($folder . '/a');

		pg_assert_same(array('webp', 'avif'), array_keys($tried), 'both formats answered');

		foreach ($tried as $format => $works) {
			pg_assert_true(!$works || $claims[$format], $format . ' only when a library claims it');
		}

		$stored = json_decode(file_get_contents($folder . '/a/formats.json'), true);
		pg_assert_same(pg_image_derivative_formats_key(), $stored['key'], 'kept under the key');
		pg_assert_same($tried, $stored['formats'], 'kept as answered');
		pg_assert_same(array('formats.json'), array_values(array_diff(scandir($folder . '/a'), array('.', '..'))), 'the probe file is gone');

		mkdir($folder . '/b');
		file_put_contents($folder . '/b/formats.json', json_encode(array('key' => pg_image_derivative_formats_key(), 'time' => time(), 'formats' => array('webp' => false, 'avif' => false))));
		pg_assert_same(array('webp' => false, 'avif' => false), pg_image_derivative_formats($folder . '/b'), 'a fresh stored answer is believed');

		mkdir($folder . '/c');
		file_put_contents($folder . '/c/formats.json', json_encode(array('key' => pg_image_derivative_formats_key(), 'time' => time() - 8 * 86400, 'formats' => array('webp' => false, 'avif' => false))));
		pg_assert_same($tried, pg_image_derivative_formats($folder . '/c'), 'a week-old answer is tried again');

		mkdir($folder . '/d');
		file_put_contents($folder . '/d/formats.json', json_encode(array('key' => 'another server', 'time' => time(), 'formats' => array('webp' => false, 'avif' => false))));
		pg_assert_same($tried, pg_image_derivative_formats($folder . '/d'), 'another key is tried again');

		pg_assert_same($claims, pg_image_derivative_formats(''), 'no folder: the claims as they are');

	} finally {
		image_derivatives_test_remove($folder);
	}
}

// Only where this server really writes AVIF.
function test_image_derivative_avif_where_it_can_be_written()
{
	$folder = image_derivatives_test_folder();

	try {
		$formats = pg_image_derivative_formats($folder . '/cache');

		if (!image_derivatives_test_gd() || empty($formats['avif'])) {
			pg_assert_true(true, 'AVIF cannot be written here; skipped');
			return;
		}

		$source = $folder . '/files/photo.jpg';
		image_derivatives_test_jpeg($source, 2000, 1000, 6);

		$avif = pg_image_derivative(4, $source, array('width' => 960, 'format' => 'avif'), $folder . '/cache');

		pg_assert_same('derivative', $avif['status'], 'made');
		pg_assert_same('avif', $avif['extension'], 'as avif');
		pg_assert_same('avif', substr(file_get_contents($avif['path'], false, null, 0, 12), 8, 4), 'avif bytes');

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
