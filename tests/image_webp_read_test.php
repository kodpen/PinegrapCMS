<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reading a WebP with GD: pg_image_webp_gd_readable()
 * (includes/image_engine.php) tells an animated WebP or one cut short from
 * bytes GD can take, and pg_image_from_string() (includes/fn/image.php) reads
 * a WebP on every PHP version. PHP's bundled GD before 8.2 ends the request
 * with a fatal error on such a WebP, and imagecreatefromstring() reads WebP
 * only from PHP 7.3.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function image_webp_read_test_folder()
{
	$folder = sys_get_temp_dir() . '/pg_image_webp_read_test_' . bin2hex(random_bytes(4));
	mkdir($folder, 0755, true);

	return $folder;
}

function image_webp_read_test_remove($folder)
{
	foreach (glob($folder . '/*') as $file) {
		unlink($file);
	}

	rmdir($folder);
}

// imagedestroy() frees nothing since PHP 8.0 and is deprecated in 8.5.
function image_webp_read_test_destroy($image)
{
	if (PHP_VERSION_ID < 80000) {
		imagedestroy($image);
	}
}

// A still WebP drawn with GD in one colour (200, 10, 10): a "VP8 " chunk, or
// VP8X, ALPH and "VP8 " when it is half transparent. '' without WebP support.
function image_webp_read_test_still($width, $height, $alpha = false)
{
	if (!function_exists('imagewebp')) {
		return '';
	}

	$image = imagecreatetruecolor($width, $height);

	if ($alpha) {
		imagealphablending($image, false);
		imagesavealpha($image, true);
		imagefill($image, 0, 0, imagecolorallocatealpha($image, 200, 10, 10, 60));
	} else {
		imagefill($image, 0, 0, imagecolorallocate($image, 200, 10, 10));
	}

	ob_start();
	imagewebp($image, null, 80);
	$webp = (string) ob_get_clean();
	image_webp_read_test_destroy($image);

	return $webp;
}

// The still's "VP8 " chunk as the one frame of an animated WebP: VP8X with
// the animation flag, ANIM, ANMF, laid out as libwebp writes them.
function image_webp_read_test_animated($still, $width, $height)
{
	$le24 = function ($value) {
		return substr(pack('V', $value), 0, 3);
	};

	$frame = $le24(0) . $le24(0) . $le24($width - 1) . $le24($height - 1) . $le24(100) . "\x00" . substr($still, 12);
	$body = 'WEBP'
		. 'VP8X' . pack('V', 10) . "\x02\x00\x00\x00" . $le24($width - 1) . $le24($height - 1)
		. 'ANIM' . pack('V', 6) . "\x00\x00\x00\x00" . pack('v', 0)
		. 'ANMF' . pack('V', strlen($frame)) . $frame . ((strlen($frame) % 2) ? "\x00" : '');

	return 'RIFF' . pack('V', strlen($body)) . $body;
}

// A 1x1 lossless WebP ("VP8L") as libwebp writes one; GD writes lossless only
// from PHP 8.1.
function image_webp_read_test_lossless()
{
	return base64_decode('UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==');
}

// Whole still pictures pass; cut short, animated, or not a WebP do not.
function test_image_webp_read_gd_readable_tells_whole_still_pictures()
{
	$lossless = image_webp_read_test_lossless();

	pg_assert_true(pg_image_webp_gd_readable($lossless), 'lossless');
	pg_assert_false(pg_image_webp_gd_readable(substr($lossless, 0, -2)), 'lossless cut short');
	pg_assert_false(pg_image_webp_gd_readable(''), 'empty');
	pg_assert_false(pg_image_webp_gd_readable('RIFF' . pack('V', 4) . 'WEBP'), 'container only');
	pg_assert_false(pg_image_webp_gd_readable("\x89PNG\r\n\x1A\n" . str_repeat("\x00", 40)), 'a PNG');

	$still = image_webp_read_test_still(300, 200);

	if ($still === '') {
		pg_assert_true(true, 'GD writes no WebP here; the rest skipped');
		return;
	}

	$alpha = image_webp_read_test_still(40, 30, true);

	pg_assert_true(pg_image_webp_gd_readable($still), 'lossy');
	pg_assert_true(pg_image_webp_gd_readable($alpha), 'lossy with alpha (VP8X, ALPH)');
	pg_assert_same('VP8X', substr($alpha, 12, 4), 'the alpha sample is an extended file');
	pg_assert_true(pg_image_webp_gd_readable($still . 'trailing bytes'), 'bytes after the container are ignored');

	pg_assert_false(pg_image_webp_gd_readable(substr($still, 0, -10)), 'the last bytes missing');
	pg_assert_false(pg_image_webp_gd_readable(substr($still, 0, (int) (strlen($still) / 2))), 'half of it');
	pg_assert_false(pg_image_webp_gd_readable(substr($still, 0, 30)), 'the headers alone');
	pg_assert_false(pg_image_webp_gd_readable(substr($alpha, 0, -4)), 'alpha cut short');
	pg_assert_false(pg_image_webp_gd_readable(image_webp_read_test_animated($still, 300, 200)), 'animated');

	// A frame tag that is not a key frame, or whose first partition is
	// longer than the chunk.
	$inter = $still;
	$inter[20] = chr(ord($inter[20]) | 0x01);
	pg_assert_false(pg_image_webp_gd_readable($inter), 'not a key frame');

	$chunk = unpack('V', substr($still, 16, 4));
	$tag = (ord($still[20]) | (ord($still[21]) << 8) | (ord($still[22]) << 16)) & 0x1F;
	$tag |= ($chunk[1] - 9) << 5;
	$long = substr_replace($still, substr(pack('V', $tag), 0, 3), 20, 3);
	pg_assert_false(pg_image_webp_gd_readable($long), 'first partition past the chunk');
}

// imagecreatefromstring() reads WebP from PHP 7.3; before it the bytes go
// through imagecreatefromwebp(). An animated WebP or one cut short gives
// false: before PHP 8.2 GD would end the run with a fatal error on it.
function test_image_webp_read_from_string_on_every_php_version()
{
	if (!function_exists('imagecreatefromstring')) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	pg_assert_false(pg_image_from_string(''), 'empty');
	pg_assert_false(pg_image_from_string('not a picture'), 'not a picture');

	$image = imagecreatetruecolor(4, 3);
	ob_start();
	imagepng($image);
	$png = (string) ob_get_clean();
	image_webp_read_test_destroy($image);

	$image = pg_image_from_string($png);
	pg_assert_true($image !== false, 'a PNG as before');
	pg_assert_same(4, ($image !== false) ? imagesx($image) : 0, 'PNG width');

	$still = image_webp_read_test_still(20, 10);

	if (($still === '') || !function_exists('imagecreatefromwebp')) {
		pg_assert_true(true, 'GD reads no WebP here; the rest skipped');
		return;
	}

	$folder = image_webp_read_test_folder();

	// Before PHP 7.3 the bytes alone go through a temporary copy.
	$copies = count((array) glob(sys_get_temp_dir() . '/pg_webp_*'));

	try {
		file_put_contents($folder . '/red.webp', $still);

		foreach (array('bytes' => '', 'file' => $folder . '/red.webp') as $label => $path) {
			$image = pg_image_from_string($still, $path);

			pg_assert_true($image !== false, 'WebP from ' . $label);

			if ($image !== false) {
				$rgb = imagecolorat($image, 5, 5);

				pg_assert_same(array(20, 10), array(imagesx($image), imagesy($image)), 'size from ' . $label);
				pg_assert_true(abs((($rgb >> 16) & 0xFF) - 200) <= 8, 'red from ' . $label);
				image_webp_read_test_destroy($image);
			}
		}

		$image = pg_image_from_string(image_webp_read_test_lossless());
		pg_assert_same(1, ($image !== false) ? imagesx($image) : 0, 'lossless');

		pg_assert_false(pg_image_from_string(substr($still, 0, -6)), 'cut short');
		pg_assert_false(pg_image_from_string(image_webp_read_test_animated($still, 20, 10)), 'animated');
		pg_assert_same($copies, count((array) glob(sys_get_temp_dir() . '/pg_webp_*')), 'no copy left behind');

	} finally {
		image_webp_read_test_remove($folder);
	}
}

// The edit file screen and the order screens take an accent colour from the
// picture: a WebP gives its own colour on every version, and an animated one
// or one cut short the grey fallback instead of a blank page.
function test_image_webp_read_dominant_colour()
{
	$still = image_webp_read_test_still(60, 40);

	if (($still === '') || !function_exists('imagecreatefromwebp')) {
		pg_assert_true(true, 'GD reads no WebP here; skipped');
		return;
	}

	$folder = image_webp_read_test_folder();

	try {
		file_put_contents($folder . '/red.webp', $still);
		file_put_contents($folder . '/moving.webp', image_webp_read_test_animated($still, 60, 40));
		file_put_contents($folder . '/short.webp', substr($still, 0, -6));

		pg_assert_same('#c00000', get_dominant_area_color($folder . '/red.webp'), 'still');
		pg_assert_same('#9696968e', get_dominant_area_color($folder . '/moving.webp'), 'animated');
		pg_assert_same('#9696968e', get_dominant_area_color($folder . '/short.webp'), 'cut short');

	} finally {
		image_webp_read_test_remove($folder);
	}
}

// The engine's GD half reads a whole WebP and refuses one cut short; the
// engine itself turns that into a failed report, not a fatal error.
function test_image_webp_read_engine_gd_read()
{
	$still = image_webp_read_test_still(20, 10);

	if (($still === '') || !function_exists('imagecreatefromwebp')) {
		pg_assert_true(true, 'GD reads no WebP here; skipped');
		return;
	}

	$folder = image_webp_read_test_folder();

	try {
		file_put_contents($folder . '/red.webp', $still);
		file_put_contents($folder . '/short.webp', substr($still, 0, -6));

		$image = pg_image_gd_read($folder . '/red.webp', IMAGETYPE_WEBP);
		pg_assert_true($image !== false, 'whole');
		pg_assert_false(pg_image_gd_read($folder . '/short.webp', IMAGETYPE_WEBP), 'cut short');

		if (!class_exists('Imagick')) {
			$report = pg_process_image_file($folder . '/short.webp', array('write' => false));

			pg_assert_same(array('error', 'failed'), array($report['status'], $report['reason']), 'the engine reports it');
		}

	} finally {
		image_webp_read_test_remove($folder);
	}
}

// The doors that hand GD a picture's bytes go through the check: the account
// menu's profile picture, the accent colour and the panel icon.
function test_image_webp_read_doors()
{
	$lines = array(
		array('includes/fn/contacts.php', '$source = pg_image_from_string($binary);', 1),
		array('includes/fn/image.php', '$image = pg_image_from_string($image_data, $file_path);', 1),
		array('manifest_icon.php', "(\$type === IMAGETYPE_WEBP) && (PHP_VERSION_ID < 80200) && !pg_image_webp_gd_readable(", 1),
		array('includes/image_engine.php', '(PHP_VERSION_ID < 80200) && !pg_image_webp_gd_readable(', 1),
	);

	foreach ($lines as $line) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $line[0]);

		pg_assert_same($line[2], substr_count($source, $line[1]), $line[0] . ': ' . $line[1]);
	}
}
