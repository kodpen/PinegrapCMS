<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_optimizable_image_types() and pg_optimize_unsupported_message()
 * in includes/fn/image.php: the types the image optimizer accepts and the
 * answer for any other file.
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
