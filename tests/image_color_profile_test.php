<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the colour profile checks of the image engine
 * (includes/image_engine.php): the ICC profile embedded in a JPEG, PNG or
 * WebP is found by its bytes, an sRGB one is told from a Display P3 or Adobe
 * RGB one, and the GD half, which decodes without converting colours, writes
 * an RGB profile back into its JPEG, PNG or WebP output and leaves a picture
 * whose profile it cannot carry as it is instead of washing its colours out.
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

// The bytes of a picture drawn by GD and written as $output ('jpeg', 'png',
// 'webp', 'webp-lossless', 'webp-alpha').
function image_color_profile_test_gd_bytes($output, $width = 40, $height = 30)
{
	$image = imagecreatetruecolor($width, $height);
	imagefilledrectangle($image, 0, 0, (int) ($width / 2), (int) ($height / 2), imagecolorallocate($image, 200, 10, 10));

	if ($output === 'webp-alpha') {
		imagealphablending($image, false);
		imagesavealpha($image, true);
		imagefilledrectangle($image, (int) ($width / 2), 0, $width - 1, $height - 1, imagecolorallocatealpha($image, 0, 0, 255, 100));
	}

	ob_start();

	if ($output === 'jpeg') {
		imagejpeg($image, null, 80);
	} elseif ($output === 'png') {
		imagepng($image, null, 9);
	} elseif ($output === 'webp-lossless') {
		imagewebp($image, null, defined('IMG_WEBP_LOSSLESS') ? IMG_WEBP_LOSSLESS : 101);
	} else {
		imagewebp($image, null, 80);
	}

	return ob_get_clean();
}

// The JPEG markers before the scan, in order, as two hex digits each.
function image_color_profile_test_jpeg_markers($jpeg)
{
	$markers = array();
	$offset = 2;

	while ($offset + 4 <= strlen($jpeg)) {
		$marker = ord($jpeg[$offset + 1]);

		if ($marker === 0xDA) {
			break;
		}

		$markers[] = sprintf('%02X', $marker);
		$length = unpack('n', substr($jpeg, $offset + 2, 2));
		$offset += 2 + $length[1];
	}

	return $markers;
}

// The PNG chunk types in order; false when a CRC does not match.
function image_color_profile_test_png_types($png)
{
	$types = array();
	$offset = 8;

	while ($offset + 12 <= strlen($png)) {
		$length = unpack('N', substr($png, $offset, 4));
		$length = $length[1];
		$type = substr($png, $offset + 4, 4);

		if (pack('N', crc32($type . substr($png, $offset + 8, $length))) !== substr($png, $offset + 8 + $length, 4)) {
			return false;
		}

		$types[] = $type;
		$offset += 12 + $length;
	}

	return $types;
}

// The RIFF chunk types of a WebP in order.
function image_color_profile_test_webp_types($webp)
{
	$types = array();
	$offset = 12;

	while ($offset + 8 <= strlen($webp)) {
		$size = unpack('V', substr($webp, $offset + 4, 4));
		$types[] = substr($webp, $offset, 4);
		$offset += 8 + $size[1] + ($size[1] % 2);
	}

	return $types;
}

// The VP8X header of a WebP: flags, canvas width, canvas height.
function image_color_profile_test_vp8x($webp)
{
	$width = unpack('V', substr($webp, 24, 3) . "\x00");
	$height = unpack('V', substr($webp, 27, 3) . "\x00");

	return array(ord($webp[20]), $width[1] + 1, $height[1] + 1);
}

// What GD does with a picture's profile: nothing for none or sRGB, the
// profile carried into a JPEG, PNG or WebP when it describes RGB, and a
// refusal where it cannot be carried or would not describe the pixels.
function test_image_gd_icc_plan()
{
	$p3 = image_color_profile_test_icc('Display P3', true);
	$huge = $p3 . str_repeat("\x00", 255 * 65519);

	pg_assert_same('none', pg_image_gd_icc_plan('', 'jpeg'), 'no profile');
	pg_assert_same('none', pg_image_gd_icc_plan(image_color_profile_test_icc('sRGB IEC61966-2.1'), 'avif'), 'sRGB, any output');
	pg_assert_same('embed', pg_image_gd_icc_plan($p3, 'jpeg'), 'P3 into JPEG');
	pg_assert_same('embed', pg_image_gd_icc_plan($p3, 'jpg'), 'P3 into a .jpg');
	pg_assert_same('embed', pg_image_gd_icc_plan($p3, 'png', true), 'P3 into PNG');
	pg_assert_same('embed', pg_image_gd_icc_plan($p3, 'webp'), 'P3 into WebP');
	pg_assert_same('refuse', pg_image_gd_icc_plan($p3, 'png', false), 'PNG without zlib');
	pg_assert_same('refuse', pg_image_gd_icc_plan($p3, 'avif'), 'AVIF');
	pg_assert_same('refuse', pg_image_gd_icc_plan($p3, 'gif'), 'GIF');
	pg_assert_same('refuse', pg_image_gd_icc_plan($p3, 'bmp'), 'BMP');
	pg_assert_same('refuse', pg_image_gd_icc_plan($p3, ''), 'unknown output');
	pg_assert_same('refuse', pg_image_gd_icc_plan(false, 'jpeg'), 'unreadable profile');
	pg_assert_same('refuse', pg_image_gd_icc_plan(image_color_profile_test_icc('Generic Gray', false, 'GRAY'), 'jpeg'), 'grey profile: GD writes RGB');
	pg_assert_same('refuse', pg_image_gd_icc_plan(image_color_profile_test_icc('Coated FOGRA39', false, 'CMYK'), 'jpeg'), 'CMYK profile');
	pg_assert_same('refuse', pg_image_gd_icc_plan('not a profile', 'jpeg'), 'garbage');
	pg_assert_same('refuse', pg_image_gd_icc_plan($huge, 'jpeg'), 'too large for 255 JPEG segments');
	pg_assert_same('embed', pg_image_gd_icc_plan($huge, 'webp'), 'the same into WebP');
}

// The JPEG writer: the profile goes after the APPn segments and before GD's
// comment and tables, split into numbered segments, replacing an old one.
function test_image_jpeg_embed_icc()
{
	if (!function_exists('imagejpeg')) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_color_profile_test_folder();
	$big = image_color_profile_test_icc('Display P3', true) . str_repeat("\x01", 100000);
	$adobe = image_color_profile_test_icc('Adobe RGB (1998)');
	$jpeg = image_color_profile_test_gd_bytes('jpeg');

	try {
		pg_assert_same(array('E0', 'FE'), array_slice(image_color_profile_test_jpeg_markers($jpeg), 0, 2), 'what GD writes');

		$out = pg_image_jpeg_embed_icc($jpeg, $big);

		pg_assert_same(array('E0', 'E2', 'E2', 'FE'), array_slice(image_color_profile_test_jpeg_markers($out), 0, 4), 'after APP0, before the comment, in two segments');
		file_put_contents($folder . '/out.jpg', $out);
		pg_assert_same($big, pg_image_icc_profile($folder . '/out.jpg'), 'read back');
		pg_assert_true(@imagecreatefromstring($out) !== false, 'GD still decodes it');

		$again = pg_image_jpeg_embed_icc($out, $adobe);
		pg_assert_same(array('E0', 'E2', 'FE'), array_slice(image_color_profile_test_jpeg_markers($again), 0, 3), 'an old profile is replaced');
		file_put_contents($folder . '/again.jpg', $again);
		pg_assert_same($adobe, pg_image_icc_profile($folder . '/again.jpg'), 'the new one is read back');

		pg_assert_same(false, pg_image_jpeg_embed_icc('not a jpeg', $big), 'not a JPEG');
		pg_assert_same(false, pg_image_jpeg_embed_icc(substr($jpeg, 0, 10), $big), 'cut short');
		pg_assert_same(false, pg_image_jpeg_embed_icc($jpeg, ''), 'no profile');

	} finally {
		image_color_profile_test_remove($folder);
	}
}

// The PNG writer: an iCCP chunk right after IHDR with a valid CRC; an sRGB or
// gAMA chunk, which would contradict it, is dropped.
function test_image_png_embed_icc()
{
	if (!function_exists('imagepng') || !function_exists('gzcompress')) {
		pg_assert_true(true, 'GD or zlib is not available; skipped');
		return;
	}

	$folder = image_color_profile_test_folder();
	$p3 = image_color_profile_test_icc('Display P3', true);
	$png = image_color_profile_test_gd_bytes('png');
	$tagged = substr($png, 0, 33) . image_color_profile_test_png_chunk('sRGB', "\x00") . image_color_profile_test_png_chunk('gAMA', pack('N', 45455)) . substr($png, 33);

	try {
		$out = pg_image_png_embed_icc($tagged, $p3);
		$types = image_color_profile_test_png_types($out);

		pg_assert_true(is_array($types), 'every CRC matches');
		pg_assert_same(array('IHDR', 'iCCP'), array_slice((array) $types, 0, 2), 'right after IHDR');
		pg_assert_false(in_array('sRGB', (array) $types, true) || in_array('gAMA', (array) $types, true), 'sRGB and gAMA are dropped');
		pg_assert_true(in_array('IDAT', (array) $types, true) && (end($types) === 'IEND'), 'the image data is kept');

		file_put_contents($folder . '/out.png', $out);
		pg_assert_same($p3, pg_image_icc_profile($folder . '/out.png'), 'read back');
		pg_assert_true(@imagecreatefromstring($out) !== false, 'GD still decodes it');

		$again = pg_image_png_embed_icc($out, image_color_profile_test_icc('Adobe RGB (1998)'));
		pg_assert_same(1, count(array_keys((array) image_color_profile_test_png_types($again), 'iCCP')), 'an old profile is replaced');

		pg_assert_same(false, pg_image_png_embed_icc('not a png', $p3), 'not a PNG');
		pg_assert_same(false, pg_image_png_embed_icc(substr($png, 0, 40), $p3), 'cut short before IDAT');

	} finally {
		image_color_profile_test_remove($folder);
	}
}

// The WebP writer, for the three shapes libgd writes: a simple lossy or
// lossless file gets a VP8X header (with the alpha flag when the lossless
// bitstream says so), an extended one gets the ICC flag; the RIFF size is
// written again.
function test_image_webp_embed_icc()
{
	$folder = image_color_profile_test_folder();
	$p3 = image_color_profile_test_icc('Display P3', true);
	$riff = function ($chunks) {
		return 'RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks;
	};

	// Synthetic bitstream headers: only the parts that carry the size.
	$vp8 = "\x10\x02\x00\x9D\x01\x2A" . pack('v', 640) . pack('v', 427);
	$vp8l = "\x2F" . pack('V', 639 | (426 << 14) | (1 << 28)) . "\x00";
	$extended = 'VP8X' . pack('V', 10) . "\x10\x00\x00\x00" . "\x7F\x02\x00\xAA\x01\x00"
		. 'ICCP' . pack('V', 4) . 'oldp'
		. 'ALPH' . pack('V', 3) . "abc\x00"
		. 'VP8 ' . pack('V', strlen($vp8)) . $vp8;

	$cases = array(
		'lossy'            => array($riff('VP8 ' . pack('V', strlen($vp8)) . $vp8), 0x20, array('VP8X', 'ICCP', 'VP8 ')),
		'lossless + alpha' => array($riff('VP8L' . pack('V', strlen($vp8l)) . $vp8l), 0x30, array('VP8X', 'ICCP', 'VP8L')),
		'extended + alpha' => array($riff($extended), 0x30, array('VP8X', 'ICCP', 'ALPH', 'VP8 ')));

	try {
		foreach ($cases as $name => $case) {
			$out = pg_image_webp_embed_icc($case[0], $p3);

			pg_assert_true(is_string($out), $name . ': written');
			pg_assert_same($case[2], image_color_profile_test_webp_types((string) $out), $name . ': chunks');
			pg_assert_same(array($case[1], 640, 427), image_color_profile_test_vp8x((string) $out), $name . ': flags and canvas');
			pg_assert_same(strlen((string) $out) - 8, current(unpack('V', substr((string) $out, 4, 4))), $name . ': RIFF size');

			file_put_contents($folder . '/out.webp', (string) $out);
			pg_assert_same($p3, pg_image_icc_profile($folder . '/out.webp'), $name . ': read back');
		}

		pg_assert_same(false, pg_image_webp_embed_icc($riff('VP8 ' . pack('V', 10) . "\x10\x02\x00\x00\x00\x00\x00\x00\x00\x00"), $p3), 'a lossy frame without its start code');
		pg_assert_same(false, pg_image_webp_embed_icc($riff('VP8X' . pack('V', 10) . "\x02\x00\x00\x00\x00\x00\x00\x00\x00\x00"), $p3), 'an animation');
		pg_assert_same(false, pg_image_webp_embed_icc($riff('VP8 ' . pack('V', 100) . $vp8), $p3), 'a chunk longer than the file');
		pg_assert_same(false, pg_image_webp_embed_icc('RIFF', $p3), 'not a WebP');

		// What this GD writes: decoded again with the profile in it.
		if (function_exists('imagewebp') && function_exists('imagecreatefromwebp')) {
			// GD writes lossless WebP from PHP 8.1 (IMG_WEBP_LOSSLESS); before
			// it a quality of 101 stops PHP with a fatal error.
			$outputs = defined('IMG_WEBP_LOSSLESS') ? array('webp', 'webp-lossless', 'webp-alpha') : array('webp', 'webp-alpha');

			foreach ($outputs as $output) {
				$out = pg_image_webp_embed_icc(image_color_profile_test_gd_bytes($output), $p3);

				pg_assert_true(is_string($out), $output . ' from GD: written');
				file_put_contents($folder . '/gd.webp', (string) $out);
				// imagecreatefromstring() reads WebP only from PHP 7.3.
				pg_assert_true(@imagecreatefromwebp($folder . '/gd.webp') !== false, $output . ' from GD: still decodes');
				pg_assert_same($p3, pg_image_icc_profile($folder . '/gd.webp'), $output . ' from GD: read back');
			}
		}

		pg_assert_same(false, pg_image_embed_icc('GIF89a', $p3), 'a GIF has no place for it');

	} finally {
		image_color_profile_test_remove($folder);
	}
}

// GD carries an RGB profile it cannot convert into what it writes: a Display
// P3 photo is optimized, scaled and turned into WebP with its profile. A
// profile GD cannot carry (into AVIF, a grey one, an unreadable one) still
// leaves the file as it is.
function test_image_gd_keeps_foreign_color_profile()
{
	if (!function_exists('imagejpeg') || class_exists('Imagick')) {
		pg_assert_true(true, 'GD alone is not what runs here; skipped');
		return;
	}

	$folder = image_color_profile_test_folder();
	$p3 = image_color_profile_test_icc('Display P3', true);
	$source = $folder . '/files/p3.jpg';

	try {
		image_color_profile_test_jpeg($source, 1200, 800, image_color_profile_test_app2($p3, array(0)));
		$copy = $folder . '/files/p3-copy.jpg';
		copy($source, $copy);

		$report = pg_process_image_file($source);

		pg_assert_same('success', $report['status'], 'optimize');
		pg_assert_true($report['changed'], 'the file is rewritten');
		pg_assert_same($p3, pg_image_icc_profile($source), 'with its profile');
		pg_assert_true(@imagecreatefromjpeg($source) !== false, 'and it decodes');

		$derivative = pg_image_derivative(21, $copy, array('width' => 480, 'format' => ''), $folder . '/cache');

		pg_assert_same('derivative', $derivative['status'], 'a derivative is made');
		pg_assert_same($p3, pg_image_icc_profile($derivative['path']), 'with the profile');
		pg_assert_same(480, (int) current(getimagesize($derivative['path'])), 'at the asked width');

		if (pg_image_format_writer('webp') === 'gd') {
			$webp = pg_process_image_file($copy, array('format' => 'webp', 'write' => false));

			pg_assert_same('success', $webp['status'], 'WebP conversion');
			file_put_contents($folder . '/out.webp', $webp['data']);
			pg_assert_same($p3, pg_image_icc_profile($folder . '/out.webp'), 'WebP keeps the profile');
			pg_assert_true(@imagecreatefromwebp($folder . '/out.webp') !== false, 'WebP decodes');
		}

		if (pg_image_format_writer('avif') === 'gd') {
			$avif = pg_process_image_file($copy, array('format' => 'avif', 'write' => false));
			pg_assert_same('color_profile', $avif['reason'], 'AVIF has no place for it here: refused');
		}

		$gray = $folder . '/files/gray.jpg';
		image_color_profile_test_jpeg($gray, 1200, 800, image_color_profile_test_app2(image_color_profile_test_icc('Generic Gray', false, 'GRAY'), array(0)));
		$before = md5_file($gray);
		$report = pg_process_image_file($gray);

		pg_assert_same('color_profile', $report['reason'], 'a grey profile would not describe RGB pixels');
		pg_assert_same($before, md5_file($gray), 'the file is not touched');

		$refused = pg_image_derivative(23, $gray, array('width' => 480, 'format' => ''), $folder . '/cache');

		pg_assert_same('original', $refused['status'], 'the original is sent');
		pg_assert_same(0, count(glob($folder . '/cache/23/480-*.jpg.skip')), 'not remembered: it depends on the library');

		$bad = $folder . '/files/bad.png';
		file_put_contents($bad, image_color_profile_test_png(image_color_profile_test_png_chunk('iCCP', "ICC Profile\x00\x00not zlib")));
		pg_assert_same('color_profile', pg_process_image_file($bad)['reason'], 'an unreadable profile is refused');

		$srgb = $folder . '/files/srgb.jpg';
		image_color_profile_test_jpeg($srgb, 1200, 800, image_color_profile_test_app2(image_color_profile_test_icc('sRGB IEC61966-2.1'), array(0)));

		$made = pg_image_derivative(22, $srgb, array('width' => 480, 'format' => ''), $folder . '/cache');

		pg_assert_same('derivative', $made['status'], 'an sRGB profile changes nothing');
		pg_assert_same('', pg_image_icc_profile($made['path']), 'and it is not written');

	} finally {
		image_color_profile_test_remove($folder);
	}
}

// For a picture with a profile GD cannot carry, the markup offers only the
// formats it can still get; without Imagick and no original derivative it
// offers none at all.
function test_image_responsive_profile_formats()
{
	$all = array('webp' => true, 'avif' => true);
	$writers = array('webp' => 'imagick', 'avif' => 'gd');
	$none = array('original' => false, 'webp' => false, 'avif' => false);
	$avif = array('original' => false, 'webp' => false, 'avif' => true);
	$every = array('original' => true, 'webp' => true, 'avif' => true);

	pg_assert_same($all, pg_img_profile_formats($all, $none, false, array()), 'nothing refused: unchanged without Imagick');
	pg_assert_same($all, pg_img_profile_formats($all, $none, true, $writers), 'nothing refused: unchanged with Imagick');
	pg_assert_same(null, pg_img_profile_formats($all, $every, false, array('webp' => '', 'avif' => '')), 'the original refused without Imagick: no responsive markup');
	pg_assert_same(array('webp' => true, 'avif' => false), pg_img_profile_formats($all, $avif, false, array('webp' => '', 'avif' => '')), 'an RGB profile without Imagick: WebP kept, AVIF left out');
	pg_assert_same(array('webp' => true, 'avif' => false), pg_img_profile_formats($all, $every, true, $writers), 'a format only GD writes is left out');
	pg_assert_same(array('webp' => true, 'avif' => true), pg_img_profile_formats($all, array('original' => true, 'webp' => true, 'avif' => false), true, $writers), 'GD carries it into AVIF here: kept');
	pg_assert_same(array('webp' => false, 'avif' => false), pg_img_profile_formats(array('webp' => false, 'avif' => true), $every, true, array('webp' => 'imagick', 'avif' => '')), 'a format switched off stays off');
	pg_assert_same($all, pg_img_profile_formats($all, $every, true, array('webp' => 'imagick', 'avif' => 'imagick')), 'Imagick writes both');
}
