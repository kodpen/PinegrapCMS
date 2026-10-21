<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the animation checks of the image engine
 * (includes/image_engine.php): an APNG and an animated WebP are told apart
 * from still pictures by their own bytes, and the engine, the derivatives
 * and the optimize button leave them as they are, the way they always left
 * an animated GIF.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function image_animation_test_folder()
{
	$folder = sys_get_temp_dir() . '/pg_image_animation_test_' . bin2hex(random_bytes(4));
	mkdir($folder . '/files', 0755, true);

	return $folder;
}

function image_animation_test_remove($path)
{
	if (is_dir($path) && !is_link($path)) {
		foreach (scandir($path) as $entry) {
			if (($entry !== '.') && ($entry !== '..')) {
				image_animation_test_remove($path . '/' . $entry);
			}
		}

		rmdir($path);

	} elseif (file_exists($path) || is_link($path)) {
		unlink($path);
	}
}

function image_animation_test_chunk($type, $data)
{
	return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
}

// The chunks of a PNG, signature left out: list of [type, data].
function image_animation_test_chunks($png)
{
	$chunks = array();
	$offset = 8;

	while ($offset + 8 <= strlen($png)) {
		$length = unpack('N', substr($png, $offset, 4));
		$chunks[] = array(substr($png, $offset + 4, 4), substr($png, $offset + 8, $length[1]));
		$offset += 12 + $length[1];
	}

	return $chunks;
}

// A still PNG drawn with GD: stripes, so it compresses but not to nothing.
function image_animation_test_png($width, $height)
{
	$image = imagecreatetruecolor($width, $height);

	for ($y = 0; $y < $height; $y += 4) {
		imagefilledrectangle($image, 0, $y, $width - 1, $y + 3, imagecolorallocate($image, ($y * 7) % 256, 90, 200 - ($y % 100)));
	}

	ob_start();
	imagepng($image, null, 0);
	$png = ob_get_clean();
	imagedestroy($image);

	return $png;
}

// The same picture as a two-frame APNG: acTL and an fcTL before the first
// IDAT, a second frame (fcTL + fdAT) after it, as the APNG specification
// lays one out. $extra goes between IHDR and acTL (a text chunk, say).
function image_animation_test_apng($width, $height, $extra = '')
{
	$png = image_animation_test_png($width, $height);
	$out = "\x89PNG\r\n\x1A\n";
	$frame = function ($sequence) use ($width, $height) {
		return image_animation_test_chunk('fcTL', pack('N', $sequence) . pack('N', $width) . pack('N', $height) . pack('N', 0) . pack('N', 0) . pack('n', 1) . pack('n', 10) . "\x00\x00");
	};
	$data = '';

	foreach (image_animation_test_chunks($png) as $chunk) {
		if ($chunk[0] === 'IHDR') {
			$out .= image_animation_test_chunk('IHDR', $chunk[1]) . $extra
				. image_animation_test_chunk('acTL', pack('N', 2) . pack('N', 0))
				. $frame(0);
		} elseif ($chunk[0] === 'IDAT') {
			$out .= image_animation_test_chunk('IDAT', $chunk[1]);
			$data .= $chunk[1];
		}
	}

	return $out . $frame(1) . image_animation_test_chunk('fdAT', pack('N', 2) . $data) . image_animation_test_chunk('IEND', '');
}

// An animated WebP header as libwebp writes one: RIFF, VP8X with the
// animation flag, ANIM, then one ANMF frame. Nothing here has to decode it.
function image_animation_test_webp($flags)
{
	$vp8x = 'VP8X' . pack('V', 10) . chr($flags) . "\x00\x00\x00" . substr(pack('V', 999), 0, 3) . substr(pack('V', 499), 0, 3);
	$anim = 'ANIM' . pack('V', 6) . "\x00\x00\x00\x00" . pack('v', 0);
	$frame = 'ANMF' . pack('V', 16) . str_repeat("\x00", 16);
	$body = 'WEBP' . $vp8x . $anim . $frame;

	return 'RIFF' . pack('V', strlen($body)) . $body;
}

// APNG, animated WebP and animated GIF are told from still files by their
// bytes; a misleading name changes nothing.
function test_image_animation_detects_apng_and_animated_webp()
{
	if (!function_exists('imagepng')) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_animation_test_folder();

	try {
		$text = image_animation_test_chunk('tEXt', 'Comment' . "\x00" . str_repeat('x', 300000));

		file_put_contents($folder . '/apng.png', image_animation_test_apng(64, 32));
		file_put_contents($folder . '/apng-text.png', image_animation_test_apng(64, 32, $text));
		file_put_contents($folder . '/still.png', image_animation_test_png(64, 32));
		file_put_contents($folder . '/apng-named.jpg', image_animation_test_apng(64, 32));
		file_put_contents($folder . '/moving.webp', image_animation_test_webp(0x02));
		file_put_contents($folder . '/alpha.webp', image_animation_test_webp(0x10));
		file_put_contents($folder . '/short.png', "\x89PNG\r\n\x1A\n");
		file_put_contents($folder . '/empty.png', '');

		// acTL after the image data is not an APNG (the specification puts
		// it first); such a file is a still picture to every reader.
		$still = image_animation_test_png(64, 32);
		$late = substr($still, 0, -12) . image_animation_test_chunk('acTL', pack('N', 2) . pack('N', 0)) . substr($still, -12);
		file_put_contents($folder . '/late.png', $late);

		pg_assert_true(pg_image_is_animated_png($folder . '/apng.png'), 'APNG');
		pg_assert_true(pg_image_is_animated_png($folder . '/apng-text.png'), 'a large chunk before acTL is skipped, not read');
		pg_assert_false(pg_image_is_animated_png($folder . '/still.png'), 'still PNG');
		pg_assert_false(pg_image_is_animated_png($folder . '/late.png'), 'acTL after IDAT');
		pg_assert_false(pg_image_is_animated_png($folder . '/short.png'), 'signature only');
		pg_assert_false(pg_image_is_animated_png($folder . '/missing.png'), 'no such file');

		pg_assert_true(pg_image_is_animated_webp($folder . '/moving.webp'), 'animated WebP');
		pg_assert_false(pg_image_is_animated_webp($folder . '/alpha.webp'), 'VP8X without the animation flag');

		pg_assert_true(pg_image_is_animated($folder . '/apng.png'));
		pg_assert_true(pg_image_is_animated($folder . '/apng-named.jpg'), 'by its bytes, not its name');
		pg_assert_true(pg_image_is_animated($folder . '/moving.webp'));
		pg_assert_false(pg_image_is_animated($folder . '/still.png'));
		pg_assert_false(pg_image_is_animated($folder . '/alpha.webp'));
		pg_assert_false(pg_image_is_animated($folder . '/empty.png'), 'empty file');

		if (function_exists('imagewebp')) {
			$image = imagecreatetruecolor(16, 16);
			imagewebp($image, $folder . '/gd.webp', 80);
			imagedestroy($image);

			pg_assert_false(pg_image_is_animated($folder . '/gd.webp'), 'a plain VP8 WebP');
		}

		$frame = "\x21\xF9\x04\x00\x0A\x00\x00\x00"
			. "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x80\x00\x00\x00\xFF\xFF\xFF"
			. "\x02\x02\x44\x01\x00";

		file_put_contents($folder . '/moving.gif', 'GIF89a' . pack('v', 10) . pack('v', 10) . "\x00\x00\x00" . $frame . $frame . "\x3B");
		file_put_contents($folder . '/still.gif', 'GIF89a' . pack('v', 10) . pack('v', 10) . "\x00\x00\x00" . $frame . "\x3B");

		pg_assert_true(pg_image_is_animated($folder . '/moving.gif'), 'animated GIF as before');
		pg_assert_false(pg_image_is_animated($folder . '/still.gif'), 'still GIF');

	} finally {
		image_animation_test_remove($folder);
	}
}

// The engine leaves an APNG alone: optimize and the product upload do not
// write one frame over it, and the derivatives send the original.
function test_image_animation_engine_leaves_apng()
{
	if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_animation_test_folder();
	$source = $folder . '/files/moving.png';

	try {
		file_put_contents($source, image_animation_test_apng(1000, 600));
		$before = md5_file($source);

		$report = pg_process_image_file($source);

		pg_assert_same('skipped', $report['status'], 'optimize');
		pg_assert_same('animated', $report['reason']);
		pg_assert_false($report['changed']);
		pg_assert_same($before, md5_file($source), 'the file is not touched');

		$product = pg_process_image_file($source, array('max_dimension' => 480, 'min_dimension' => 100, 'quality' => 80));

		pg_assert_same('animated', $product['reason'], 'product upload profile');
		pg_assert_same($before, md5_file($source), 'still not touched');

		foreach (array(array('width' => 480, 'format' => ''), array('width' => 0, 'format' => 'webp'), array('width' => 480, 'format' => 'webp')) as $request) {
			$derivative = pg_image_derivative(11, $source, $request, $folder . '/cache');

			pg_assert_same('original', $derivative['status'], 'derivative ' . json_encode($request));
			pg_assert_same($source, $derivative['path']);
		}

		pg_assert_same(0, count(glob($folder . '/cache/11/*.png')) + count(glob($folder . '/cache/11/*.webp')), 'no still copy kept');

		// The same picture without its animation chunks is optimized as before.
		file_put_contents($source, image_animation_test_png(1000, 600));
		$still = pg_process_image_file($source, array('write' => false));

		pg_assert_same('success', $still['status'], 'a still PNG is still processed');

	} finally {
		image_animation_test_remove($folder);
	}
}

// An animated WebP is refused by name of the reason, not as a broken file.
function test_image_animation_engine_leaves_animated_webp()
{
	if (!defined('IMAGETYPE_WEBP')) {
		pg_assert_true(true, 'no WebP support; skipped');
		return;
	}

	$folder = image_animation_test_folder();
	$source = $folder . '/files/moving.webp';

	try {
		file_put_contents($source, image_animation_test_webp(0x02));

		$size = @getimagesize($source);

		if (!$size) {
			pg_assert_true(true, 'getimagesize() does not read VP8X here; skipped');
			return;
		}

		$report = pg_process_image_file($source);

		pg_assert_same('skipped', $report['status']);
		pg_assert_same('animated', $report['reason']);

	} finally {
		image_animation_test_remove($folder);
	}
}
