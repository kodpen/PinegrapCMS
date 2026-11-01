<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account menu's profile picture (pg_contact_photo_store()):
 * a picture whose pixels would not fit in memory is refused before GD
 * decodes it, so a small file with huge dimensions cannot end the request
 * with a fatal error.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * A PNG that declares the given size: a valid signature and IHDR chunk, then
 * an empty IDAT and IEND, enough for getimagesizefromstring().
 */
function contact_photo_store_png($width, $height)
{
	$chunk = function ($type, $data) {
		return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
	};

	return "\x89PNG\r\n\x1a\n"
		. $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
		. $chunk('IDAT', gzcompress(''))
		. $chunk('IEND', '');
}

function test_contact_photo_store_refuses_a_picture_too_large_to_decode()
{
	if (!function_exists('imagecreatefromstring')) {
		pg_assert_true(true, 'GD is not installed: no picture is decoded');
		return;
	}

	$limit = ini_get('memory_limit');
	ini_set('memory_limit', '128M');

	try {
		$data = 'data:image/png;base64,' . base64_encode(contact_photo_store_png(30000, 30000));
		$stored = pg_contact_photo_store(1, $data);
	} finally {
		ini_set('memory_limit', $limit);
	}

	pg_assert_false($stored['ok'], 'a 900 megapixel picture is refused');
	pg_assert_same(0, $stored['file_id'], 'no file is kept');
	pg_assert_same(lang('The picture is too large to process.'), $stored['error'], 'the refusal names the size');
}

function test_contact_photo_store_still_refuses_a_file_that_is_not_a_picture()
{
	$stored = pg_contact_photo_store(1, 'data:image/png;base64,' . base64_encode('not a picture'));

	pg_assert_false($stored['ok'], 'a file that is not a picture is refused');
	pg_assert_same(lang('The picture has to be a JPEG, PNG or WebP image.'), $stored['error'], 'the type refusal is kept');
}
