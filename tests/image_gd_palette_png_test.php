<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A palette PNG (PNG-8) through the GD engine (includes/image_engine.php,
 * #9118): a turn, a flip or a crop that adds no colour writes it back as a
 * palette PNG with exactly its pixels, instead of a truecolor RGBA file two
 * to three times its size; a picture with more than 256 colours stays
 * truecolor. pg_image_gd_exact_palette() keeps every pixel and its alpha.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function image_gd_palette_png_available()
{
	return function_exists('imagecreate') && function_exists('imagecreatetruecolor') && function_exists('imagepng') && function_exists('imagecreatefrompng') && function_exists('imagepalettetotruecolor');
}

// A PNG-8 of 300×200 with 40 colours in blocks and a transparent corner.
function image_gd_palette_png_source($path)
{
	$image = imagecreate(300, 200);
	$colors = array();

	for ($i = 0; $i < 40; $i++) {
		$colors[] = imagecolorallocate($image, ($i * 37) % 256, ($i * 91) % 256, ($i * 13) % 256);
	}

	$clear = imagecolorallocatealpha($image, 0, 0, 0, 127);
	imagecolortransparent($image, $clear);

	for ($y = 0; $y < 200; $y += 10) {
		for ($x = 0; $x < 300; $x += 15) {
			imagefilledrectangle($image, $x, $y, $x + 14, $y + 9, $colors[(($x / 15) * 7 + ($y / 10) * 3) % 40]);
		}
	}

	imagefilledrectangle($image, 0, 0, 29, 29, $clear);
	imagepng($image, $path, 9);
}

// The PNG colour type byte of the IHDR chunk: 3 palette, 6 RGBA.
function image_gd_palette_png_color_type($bytes)
{
	return (strlen($bytes) > 25) ? ord($bytes[25]) : -1;
}

// Every pixel of two GD pictures as truecolor values; a fully transparent
// pixel counts as one value whatever its colour.
function image_gd_palette_png_pixels($image)
{
	if (!imageistruecolor($image)) {
		imagepalettetotruecolor($image);
	}

	$pixels = array();

	for ($y = 0; $y < imagesy($image); $y++) {
		for ($x = 0; $x < imagesx($image); $x++) {
			$color = imagecolorat($image, $x, $y);
			$pixels[] = ((($color >> 24) & 0x7F) === 127) ? -1 : $color;
		}
	}

	return $pixels;
}

function test_image_gd_exact_palette_keeps_every_pixel()
{
	if (!image_gd_palette_png_available()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$image = imagecreatetruecolor(5, 3);
	imagealphablending($image, false);
	imagesavealpha($image, true);

	$values = array(array(255, 0, 0, 0), array(0, 128, 255, 0), array(10, 20, 30, 64), array(0, 0, 0, 127), array(255, 255, 255, 0));

	for ($y = 0; $y < 3; $y++) {
		for ($x = 0; $x < 5; $x++) {
			$v = $values[($x + $y) % 5];
			imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $v[0], $v[1], $v[2], $v[3]));
		}
	}

	$palette = pg_image_gd_exact_palette($image, 256);

	pg_assert_true($palette !== false, 'five colours fit');
	pg_assert_false(imageistruecolor($palette), 'a palette picture');
	pg_assert_same(5, imagecolorstotal($palette), 'one entry a colour');

	for ($y = 0; $y < 3; $y++) {
		for ($x = 0; $x < 5; $x++) {
			$entry = imagecolorsforindex($palette, imagecolorat($palette, $x, $y));
			$v = $values[($x + $y) % 5];
			pg_assert_same($v, array($entry['red'], $entry['green'], $entry['blue'], $entry['alpha']), 'pixel ' . $x . ',' . $y);
		}
	}

	pg_assert_false(pg_image_gd_exact_palette($image, 4), 'five colours do not fit four');
	pg_assert_false(pg_image_gd_exact_palette($palette, 256), 'not a truecolor picture');
}

function test_image_gd_engine_writes_a_palette_png_back_as_a_palette()
{
	if (!image_gd_palette_png_available()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pgpal');

	try {
		image_gd_palette_png_source($path);
		pg_assert_same(3, image_gd_palette_png_color_type((string) file_get_contents($path)), 'the source is PNG-8');

		$flip = pg_process_image_with_gd($path, IMAGETYPE_PNG, false, 0, 0, 92, '', 0, array('flip_x' => true));

		pg_assert_true(is_string($flip) && ($flip !== ''), 'flipped');
		pg_assert_same(3, image_gd_palette_png_color_type($flip), 'a flip stays a palette PNG');

		$expected = imagecreatefrompng($path);
		imagepalettetotruecolor($expected);
		imageflip($expected, IMG_FLIP_HORIZONTAL);

		pg_assert_true(image_gd_palette_png_pixels(imagecreatefromstring($flip)) === image_gd_palette_png_pixels($expected), 'pixel for pixel, the transparent corner included');

		$turn = pg_process_image_with_gd($path, IMAGETYPE_PNG, false, 0, 0, 92, '', 90);

		pg_assert_same(3, image_gd_palette_png_color_type($turn), 'a quarter turn stays a palette PNG');
		pg_assert_same(array(200, 300), array(imagesx(imagecreatefromstring($turn)), imagesy(imagecreatefromstring($turn))), 'turned');
	} finally {
		@unlink($path);
	}
}

function test_image_gd_engine_keeps_truecolor_when_colours_do_not_fit()
{
	if (!image_gd_palette_png_available()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$path = tempnam(sys_get_temp_dir(), 'pgpal');

	try {
		// A truecolor gradient source stays truecolor.
		$image = imagecreatetruecolor(64, 64);

		for ($y = 0; $y < 64; $y++) {
			for ($x = 0; $x < 64; $x++) {
				imagesetpixel($image, $x, $y, imagecolorallocate($image, $x * 4, $y * 4, 128));
			}
		}

		imagepng($image, $path, 9);
		pg_assert_same(6 & 2, image_gd_palette_png_color_type((string) file_get_contents($path)) & 2, 'the source is truecolor');

		$flip = pg_process_image_with_gd($path, IMAGETYPE_PNG, false, 0, 0, 92, '', 0, array('flip_x' => true));
		pg_assert_true(in_array(image_gd_palette_png_color_type($flip), array(2, 6), true), 'a truecolor source is not turned into a palette');

		// A palette source resized gains blended colours past 256: truecolor.
		image_gd_palette_png_source($path);
		$small = pg_process_image_with_gd($path, IMAGETYPE_PNG, true, 101, 67, 92);
		$colors = count(array_unique(image_gd_palette_png_pixels(imagecreatefromstring($small))));

		pg_assert_same(($colors > 256) ? 6 : 3, image_gd_palette_png_color_type($small), 'a palette only when the colours fit (' . $colors . ')');
	} finally {
		@unlink($path);
	}
}
