<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_optimizable_image_types(), pg_optimize_unsupported_message()
 * and pg_optimize_failure_message() in includes/fn/image.php: the types the
 * image optimizer accepts, the answer for any other file, and the answer
 * for a picture it left alone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// WebP is optimized as well; 'tif' is not (optimize_image() never took it).
function test_optimizable_image_types()
{
	pg_assert_same(array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp'), pg_optimizable_image_types(), 'types');
}

// The sentence names the file and its type apart and lists every accepted
// type, webp included.
function test_optimize_unsupported_message()
{
	$message = pg_optimize_unsupported_message('example-doc.pdf', 'PDF');

	pg_assert_true(strpos($message, 'example-doc.pdf') !== false, 'name');
	pg_assert_true(strpos($message, '(pdf)') !== false, 'type');
	pg_assert_true(strpos($message, 'jpg, jpeg, png, gif, bmp, tiff, webp') !== false, 'list');
}

// A file that is not a readable picture is said to be one, not a download
// from a service (#9022); every reason names the file, and a reason with no
// sentence of its own gets the neutral one.
function test_optimize_failure_message()
{
	$unreadable = pg_optimize_failure_message('unsupported', 'test.jpg');

	pg_assert_contains('test.jpg', $unreadable, 'names the file');
	pg_assert_contains('could not be read as a picture', $unreadable);

	foreach (array('unsupported', 'animated', 'orientation_unreadable', 'color_profile', 'too_large_to_process', 'missing', 'failed', 'write_failed', 'no_webp', 'no_color', '') as $reason) {
		$message = pg_optimize_failure_message($reason, 'photo.png');

		pg_assert_contains('photo.png', $message, $reason . ' names the file');
		pg_assert_true(stripos($message, 'service') === false, $reason . ' blames no service');
	}

	pg_assert_same(pg_optimize_failure_message('', 'photo.png'), pg_optimize_failure_message('no_webp', 'photo.png'), 'one neutral sentence');
	pg_assert_contains('could not be optimized', pg_optimize_failure_message('no_color', 'photo.png'));
	pg_assert_contains('Imagick or GD', pg_optimize_failure_message('no_library', 'photo.png'));
}

// optimize_image() answers through the helper; the catch-all sentence about
// an image optimization service is gone.
function test_optimize_image_answers_through_the_helper()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/optimize_image.php');

	pg_assert_contains("pg_optimize_failure_message(\$report['reason'], \$file['name'])", $source);
	pg_assert_true(strpos($source, 'image optimization service') === false, 'no service sentence');
}
