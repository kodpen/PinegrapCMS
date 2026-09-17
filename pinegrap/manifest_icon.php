<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The icon of the installed panel, at the size the launcher asked for.
//
// A launcher wants exact squares - 192 and 512 - and the operator picked
// whatever image they had. Rather than storing four resized copies of a setting
// that changes twice a year, each size is drawn here once and kept in
// data/temp; the cache name carries the source and its modification time, so
// replacing the file in the Files screen replaces the icon with no button to
// press.
//
// The image is never stretched or cropped. It is scaled to fit and centred on a
// square of the background colour: a wide logo keeps its proportions and gains
// bars, which is the outcome an operator can predict. The maskable size adds a
// margin on top of that, because Android cuts the icon to whatever shape the
// device uses and anything in the outer fifth can be cut away.

// No session, on purpose.
//
// The browser fetches a manifest and its icons without credentials, so these
// requests arrive with no session cookie. Starting a session for them would
// write a session file for every icon a launcher asks for, and hand back a
// Set-Cookie for a session nobody asked for - which is the kind of thing that
// ends up replacing the cookie of the operator who is signed in in the same
// browser. Nothing here needs to know who is asking: the answer is the same
// for every visitor. $_SESSION is still an array so the shared code that looks
// into it finds nothing rather than warning.
define('PG_NO_SESSION', true);

$_SESSION = array();

include('init.php');

$size = isset($_GET['size']) ? (int) $_GET['size'] : 192;

// A whitelist, not a clamp: this endpoint is public, and an open size parameter
// is an invitation to ask for ten thousand different squares.
if (!in_array($size, array(96, 192, 512), true)) {
	$size = 192;
}

$maskable = (isset($_GET['purpose']) && ($_GET['purpose'] == 'maskable'));

$fallback = dirname(__FILE__) . '/assets/images/icon-' . (($size > 192) ? 512 : 192) . '.png';

$source = '';
$icon_name = (defined('APP_ICON')) ? trim((string) APP_ICON) : '';

if ($icon_name != '') {

	// The name is a row in the Files screen and nothing else. basename() is
	// what keeps a stored value that someone typed by hand from walking out of
	// the file directory.
	$candidate = FILE_DIRECTORY_PATH . '/' . basename($icon_name);

	if (is_file($candidate)) {
		$source = $candidate;
	}
}

if ($source == '') {
	pg_app_icon_send($fallback, 'image/png');
}

$cache_directory = dirname(__FILE__) . '/data/temp/app_icon';

$cache_file = $cache_directory . '/'
	. sha1(basename($icon_name) . '|' . (int) @filemtime($source) . '|' . $size . '|' . (($maskable) ? 'm' : 'a'))
	. '.png';

if (is_file($cache_file)) {
	pg_app_icon_send($cache_file, 'image/png');
}

if (!is_dir($cache_directory)) {
	@mkdir($cache_directory, 0755, true);
}

if (pg_app_icon_render($source, $cache_file, $size, $maskable)) {
	pg_app_icon_send($cache_file, 'image/png');
}

// Drawing was refused - no image library, or a file that is not really an
// image. The shipped icon is still an icon.
pg_app_icon_send($fallback, 'image/png');

// Streams a file and stops. Cached hard: the address changes when the source
// does, so a stale copy cannot outlive the setting.
function pg_app_icon_send($path, $type)
{
	if (!is_file($path)) {
		header('HTTP/1.1 404 Not Found');
		exit();
	}

	$tag = '"' . md5($path . '|' . filemtime($path)) . '"';

	header('Content-Type: ' . $type);
	header('Cache-Control: public, max-age=604800');
	header('ETag: ' . $tag);

	if ((isset($_SERVER['HTTP_IF_NONE_MATCH'])) && (trim($_SERVER['HTTP_IF_NONE_MATCH']) == $tag)) {
		header('HTTP/1.1 304 Not Modified');
		exit();
	}

	header('Content-Length: ' . filesize($path));
	readfile($path);
	exit();
}

// Draws the square. Imagick when the server has it, GD otherwise, and an honest
// false when it has neither - a host without an image library still gets an
// installable panel, wearing the shipped icon.
function pg_app_icon_render($source, $target, $size, $maskable)
{
	// A maskable icon is cut to the device's shape, and the specification only
	// promises the middle 80 per cent survives. Drawing into that circle is
	// what stops the corners of a logo being shaved off.
	$inner = ($maskable) ? (int) round($size * 0.8) : $size;

	$background = pg_app_icon_background();

	if (class_exists('Imagick')) {

		try {

			$image = new Imagick($source);
			$image->setImageColorspace(Imagick::COLORSPACE_SRGB);

			if ($image->getNumberImages() > 1) {
				$image = $image->coalesceImages();
				$image = $image->getImage();
			}

			$image->thumbnailImage($inner, $inner, true);

			$canvas = new Imagick();
			$canvas->newImage($size, $size, new ImagickPixel($background), 'png');

			$canvas->compositeImage(
				$image,
				Imagick::COMPOSITE_OVER,
				(int) round(($size - $image->getImageWidth()) / 2),
				(int) round(($size - $image->getImageHeight()) / 2)
			);

			$canvas->setImageFormat('png');
			$written = $canvas->writeImage($target);

			$image->clear();
			$canvas->clear();

			return ($written && is_file($target));

		} catch (Exception $error) {

			// Fall through to GD rather than giving up: a source Imagick
			// refuses is not necessarily one GD refuses.
		}
	}

	if (!function_exists('imagecreatetruecolor')) {
		return false;
	}

	$details = @getimagesize($source);

	if ((!$details) || (empty($details[0])) || (empty($details[1]))) {
		return false;
	}

	$loaders = array(
		IMAGETYPE_JPEG => 'imagecreatefromjpeg',
		IMAGETYPE_PNG  => 'imagecreatefrompng',
		IMAGETYPE_GIF  => 'imagecreatefromgif',
		IMAGETYPE_WEBP => 'imagecreatefromwebp',
		IMAGETYPE_BMP  => 'imagecreatefrombmp'
	);

	$type = isset($details[2]) ? (int) $details[2] : 0;

	if ((!isset($loaders[$type])) || (!function_exists($loaders[$type]))) {
		return false;
	}

	$loader = $loaders[$type];
	$image = @$loader($source);

	if (!$image) {
		return false;
	}

	$width = (int) $details[0];
	$height = (int) $details[1];
	$scale = min($inner / $width, $inner / $height);

	$scaled_width = max(1, (int) round($width * $scale));
	$scaled_height = max(1, (int) round($height * $scale));

	$canvas = imagecreatetruecolor($size, $size);

	// The background is painted first and the source composited over it, so a
	// transparent PNG lands on the colour rather than on black.
	$colour = pg_app_icon_gd_colour($canvas, $background);
	imagefilledrectangle($canvas, 0, 0, $size, $size, $colour);
	imagealphablending($canvas, true);

	imagecopyresampled(
		$canvas,
		$image,
		(int) round(($size - $scaled_width) / 2),
		(int) round(($size - $scaled_height) / 2),
		0,
		0,
		$scaled_width,
		$scaled_height,
		$width,
		$height
	);

	$written = @imagepng($canvas, $target, 6);

	imagedestroy($image);
	imagedestroy($canvas);

	return ($written && is_file($target));
}

// The colour behind the icon is the one the manifest already declares as the
// application's background, so the square matches the splash screen.
function pg_app_icon_background()
{
	return '#ffffff';
}

function pg_app_icon_gd_colour($canvas, $hex)
{
	$hex = ltrim($hex, '#');

	if (strlen($hex) == 3) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if (strlen($hex) != 6) {
		$hex = 'ffffff';
	}

	return imagecolorallocate(
		$canvas,
		hexdec(substr($hex, 0, 2)),
		hexdec(substr($hex, 2, 2)),
		hexdec(substr($hex, 4, 2))
	);
}
