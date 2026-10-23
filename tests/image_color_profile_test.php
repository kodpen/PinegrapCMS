<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the colour profile checks of the image engine
 * (includes/image_engine.php): the ICC profile embedded in a JPEG, PNG or
 * WebP is found by its bytes, an sRGB one is told from a Display P3 or Adobe
 * RGB one, and the GD half, which writes no profile, leaves a picture with a
 * profile other than sRGB as it is instead of washing its colours out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function image_color_profile_test_folder()
{
	$folder = sys_get_temp_dir() . '/pg_image_color_profile_test_' . bin2hex(random_bytes(4));
	mkdir($folder . '/files', 0755, true);

	return $folder;
}

function image_color_profile_test_remove($path)
{
	if (is_dir($path) && !is_link($path)) {
		foreach (scandir($path) as $entry) {
			if (($entry !== '.') && ($entry !== '..')) {
				image_color_profile_test_remove($path . '/' . $entry);
			}
		}

		rmdir($path);

	} elseif (file_exists($path) || is_link($path)) {
		unlink($path);
	}
}

// A minimal ICC profile: the 128-byte header, a tag table with one 'desc'
// tag, and the description as a v2 text ('desc') or a v4 'mluc' record.
function image_color_profile_test_icc($description, $v4 = false, $space = 'RGB ')
{
	if ($v4) {
		$text = mb_convert_encoding($description, 'UTF-16BE', 'UTF-8');
		$tag = 'mluc' . "\x00\x00\x00\x00" . pack('N', 1) . pack('N', 12) . 'enUS' . pack('N', strlen($text)) . pack('N', 28) . $text;
	} else {
		$tag = 'desc' . "\x00\x00\x00\x00" . pack('N', strlen($description) + 1) . $description . "\x00" . str_repeat("\x00", 79);
	}

	$offset = 128 + 4 + 12;
	$size = $offset + strlen($tag);

	$header = pack('N', $size) . 'none' . ($v4 ? "\x04\x40\x00\x00" : "\x02\x10\x00\x00") . 'mntr' . $space . 'XYZ '
		. str_repeat("\x00", 12) . 'acsp' . str_repeat("\x00", 128 - 40);

	return $header . pack('N', 1) . 'desc' . pack('N', $offset) . pack('N', strlen($tag)) . $tag;
}

// APP2 segments carrying $icc in $parts chunks, in the order given.
function image_color_profile_test_app2($icc, $order)
{
	$parts = str_split($icc, (int) ceil(strlen($icc) / count($order)));
	$out = '';

	foreach ($order as $index) {
		$data = "ICC_PROFILE\x00" . chr($index + 1) . chr(count($order)) . $parts[$index];
		$out .= "\xFF\xE2" . pack('n', strlen($data) + 2) . $data;
	}

	return $out;
}

// A photo-like JPEG drawn by GD with $segments after its start-of-image.
function image_color_profile_test_jpeg($path, $width, $height, $segments)
{
	$image = imagecreatetruecolor($width, $height);

	mt_srand(3);

	for ($i = 0; $i < 1500; $i++) {
		$x = mt_rand(0, $width - 1);
		$y = mt_rand(0, $height - 1);
		imagefilledrectangle($image, $x, $y, $x + mt_rand(4, 40), $y + mt_rand(4, 40), imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
	}

	ob_start();
	imagejpeg($image, null, 95);
	$bytes = ob_get_clean();

	file_put_contents($path, substr($bytes, 0, 2) . $segments . substr($bytes, 2));
}

function image_color_profile_test_png_chunk($type, $data)
{
	return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
}

// A 1x1 PNG with $chunks between IHDR and IDAT.
function image_color_profile_test_png($chunks)
{
	return "\x89PNG\r\n\x1A\n"
		. image_color_profile_test_png_chunk('IHDR', pack('N', 1) . pack('N', 1) . "\x08\x02\x00\x00\x00")
		. $chunks
		. image_color_profile_test_png_chunk('IDAT', gzcompress("\x00\xFF\x00\x00"))
		. image_color_profile_test_png_chunk('IEND', '');
}

// An extended WebP header: VP8X with $flags, then $chunks. Nothing here has
// to decode it.
function image_color_profile_test_webp($flags, $chunks)
{
	$body = 'WEBP' . 'VP8X' . pack('V', 10) . chr($flags) . "\x00\x00\x00" . "\x00\x00\x00\x00\x00\x00" . $chunks . 'VP8L' . pack('V', 4) . "\x2F\x00\x00\x00";

	return 'RIFF' . pack('V', strlen($body)) . $body;
}

function test_image_icc_profile_is_srgb()
{
	pg_assert_true(pg_image_icc_is_srgb(image_color_profile_test_icc('sRGB IEC61966-2.1')), 'v2 sRGB');
	pg_assert_true(pg_image_icc_is_srgb(image_color_profile_test_icc('sRGB', true)), 'v4 sRGB');
	pg_assert_false(pg_image_icc_is_srgb(image_color_profile_test_icc('Display P3', true)), 'Display P3');
	pg_assert_false(pg_image_icc_is_srgb(image_color_profile_test_icc('Adobe RGB (1998)')), 'Adobe RGB');
	pg_assert_false(pg_image_icc_is_srgb(image_color_profile_test_icc('sRGB', false, 'CMYK')), 'not an RGB profile');
	pg_assert_false(pg_image_icc_is_srgb(substr(image_color_profile_test_icc('sRGB'), 0, 140)), 'cut short');
	pg_assert_false(pg_image_icc_is_srgb('not a profile'), 'garbage');
}

// The profile is found in each format by its bytes; a JPEG's chunks are put
// back together in their sequence order.
function test_image_icc_profile_found_in_each_format()
{
	if (!function_exists('imagejpeg') || !function_exists('gzcompress')) {
		pg_assert_true(true, 'GD or zlib is not available; skipped');
		return;
	}

	$folder = image_color_profile_test_folder();
	$p3 = image_color_profile_test_icc('Display P3', true);

	try {
		image_color_profile_test_jpeg($folder . '/one.jpg', 32, 16, image_color_profile_test_app2($p3, array(0)));
		image_color_profile_test_jpeg($folder . '/split.jpg', 32, 16, image_color_profile_test_app2($p3, array(1, 0)));
		image_color_profile_test_jpeg($folder . '/none.jpg', 32, 16, '');

		pg_assert_same($p3, pg_image_icc_profile($folder . '/one.jpg'), 'JPEG, one segment');
		pg_assert_same($p3, pg_image_icc_profile($folder . '/split.jpg'), 'JPEG, two segments out of order');
		pg_assert_same('', pg_image_icc_profile($folder . '/none.jpg'), 'JPEG without a profile');

		$whole = image_color_profile_test_app2($p3, array(0));
		file_put_contents($folder . '/cut.jpg', "\xFF\xD8" . substr($whole, 0, 40));
		pg_assert_same(false, pg_image_icc_profile($folder . '/cut.jpg'), 'JPEG segment cut short');

		file_put_contents($folder . '/icc.png', image_color_profile_test_png(image_color_profile_test_png_chunk('iCCP', "ICC Profile\x00\x00" . gzcompress($p3))));
		file_put_contents($folder . '/srgb.png', image_color_profile_test_png(image_color_profile_test_png_chunk('sRGB', "\x00")));
		file_put_contents($folder . '/bad.png', image_color_profile_test_png(image_color_profile_test_png_chunk('iCCP', "ICC Profile\x00\x00not zlib")));
		file_put_contents($folder . '/plain.png', image_color_profile_test_png(''));

		pg_assert_same($p3, pg_image_icc_profile($folder . '/icc.png'), 'PNG iCCP');
		pg_assert_same('', pg_image_icc_profile($folder . '/srgb.png'), 'PNG with an sRGB chunk');
		pg_assert_same(false, pg_image_icc_profile($folder . '/bad.png'), 'PNG iCCP that does not inflate');

		// A few hundred bytes that inflate to 5 MB: past the cap the profile
		// counts as unreadable instead of being inflated into memory.
		file_put_contents($folder . '/bomb.png', image_color_profile_test_png(image_color_profile_test_png_chunk('iCCP', "ICC Profile\x00\x00" . gzcompress(str_repeat("\x00", 5 * 1024 * 1024), 9))));
		pg_assert_true(pg_image_icc_profile($folder . '/bomb.png') === false, 'PNG iCCP that inflates past the cap');
		pg_assert_true(pg_image_has_foreign_color_profile($folder . '/bomb.png'), 'and it counts as foreign');

		pg_assert_same('', pg_image_icc_profile($folder . '/plain.png'), 'PNG without a profile');

		// A chunk length near 2 GB would overflow the offset on 32-bit PHP.
		file_put_contents($folder . '/huge.png', image_color_profile_test_png(pack('N', 0x7FFFFFFD) . 'zTXt'));
		pg_assert_same('', pg_image_icc_profile($folder . '/huge.png'), 'PNG with a chunk length near 2 GB');

		file_put_contents($folder . '/icc.webp', image_color_profile_test_webp(0x20, 'ICCP' . pack('V', strlen($p3)) . $p3 . ((strlen($p3) % 2) ? "\x00" : '')));
		file_put_contents($folder . '/flag.webp', image_color_profile_test_webp(0x20, ''));
		file_put_contents($folder . '/plain.webp', image_color_profile_test_webp(0x10, ''));

		pg_assert_same($p3, pg_image_icc_profile($folder . '/icc.webp'), 'WebP ICCP');
		pg_assert_same(false, pg_image_icc_profile($folder . '/flag.webp'), 'WebP flag without the chunk');
		pg_assert_same('', pg_image_icc_profile($folder . '/plain.webp'), 'WebP without a profile');

		pg_assert_true(pg_image_has_foreign_color_profile($folder . '/one.jpg'), 'Display P3 is foreign');
		pg_assert_false(pg_image_has_foreign_color_profile($folder . '/none.jpg'), 'no profile is sRGB');
		pg_assert_true(pg_image_has_foreign_color_profile($folder . '/cut.jpg'), 'an unreadable profile counts as foreign');
		pg_assert_false(pg_image_has_foreign_color_profile($folder . '/missing.jpg'), 'no such file');

	} finally {
		image_color_profile_test_remove($folder);
	}
}

// GD writes no profile: a Display P3 photo is left as it is (optimize,
// derivatives), an sRGB-tagged one is processed as before.
function test_image_gd_leaves_foreign_color_profile()
{
	if (!function_exists('imagejpeg') || class_exists('Imagick')) {
		pg_assert_true(true, 'GD alone is not what runs here; skipped');
		return;
	}

	$folder = image_color_profile_test_folder();
	$source = $folder . '/files/p3.jpg';

	try {
		image_color_profile_test_jpeg($source, 1200, 800, image_color_profile_test_app2(image_color_profile_test_icc('Display P3', true), array(0)));
		$before = md5_file($source);

		$report = pg_process_image_file($source);

		pg_assert_same('skipped', $report['status'], 'optimize');
		pg_assert_same('color_profile', $report['reason']);
		pg_assert_same($before, md5_file($source), 'the file is not touched');

		$derivative = pg_image_derivative(21, $source, array('width' => 480, 'format' => ''), $folder . '/cache');

		pg_assert_same('original', $derivative['status'], 'the original is sent');
		pg_assert_same(0, count(glob($folder . '/cache/21/480-*.jpg.skip')), 'not remembered: it depends on the library');

		$srgb = $folder . '/files/srgb.jpg';
		image_color_profile_test_jpeg($srgb, 1200, 800, image_color_profile_test_app2(image_color_profile_test_icc('sRGB IEC61966-2.1'), array(0)));

		$made = pg_image_derivative(22, $srgb, array('width' => 480, 'format' => ''), $folder . '/cache');

		pg_assert_same('derivative', $made['status'], 'an sRGB profile changes nothing');

	} finally {
		image_color_profile_test_remove($folder);
	}
}

// For a picture with a foreign profile the markup offers only the formats
// Imagick writes; without Imagick it offers none at all.
function test_image_responsive_profile_formats()
{
	$all = array('webp' => true, 'avif' => true);
	$writers = array('webp' => 'imagick', 'avif' => 'gd');

	pg_assert_same($all, pg_img_profile_formats($all, false, false, array()), 'not foreign: unchanged without Imagick');
	pg_assert_same($all, pg_img_profile_formats($all, false, true, $writers), 'not foreign: unchanged with Imagick');
	pg_assert_same(null, pg_img_profile_formats($all, true, false, array('webp' => 'gd', 'avif' => 'gd')), 'foreign without Imagick: no responsive markup');
	pg_assert_same(array('webp' => true, 'avif' => false), pg_img_profile_formats($all, true, true, $writers), 'foreign: a format only GD writes is left out');
	pg_assert_same(array('webp' => false, 'avif' => false), pg_img_profile_formats(array('webp' => false, 'avif' => true), true, true, array('webp' => 'imagick', 'avif' => '')), 'a format switched off stays off');
	pg_assert_same($all, pg_img_profile_formats($all, true, true, array('webp' => 'imagick', 'avif' => 'imagick')), 'foreign: Imagick writes both');
}