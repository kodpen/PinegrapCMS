<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's upload, optimize and WebP sub-action handlers
 * (includes/explorer/handle_upload.php, includes/explorer/handle_files_media.php):
 * the handlers live there, behind the gate, pg_explorer_handle() calls each
 * one for its sub-action, and the pure parts (the image options an upload is
 * processed with, the name a WebP copy is built from, the status a batch
 * answers with) give the answers the case bodies gave.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// Each sub-action has its own handler, loaded by the explorer's functions and
// called from pg_explorer_handle().
function test_explorer_upload_and_image_handlers_live_in_their_own_files()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	$files = array(
		'handle_upload.php' => array(
			"case 'explorer_upload':" => 'pg_explorer_handle_upload'),
		'handle_files_media.php' => array(
			"case 'explorer_optimize':" => 'pg_explorer_handle_optimize',
			"case 'explorer_webp':" => 'pg_explorer_handle_webp'));

	foreach ($files as $file => $actions) {

		$handlers = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/' . $file);

		foreach ($actions as $case => $function) {
			pg_assert_contains('function ' . $function . '(', $handlers, $function);
			pg_assert_true(function_exists($function), $function . ' loaded');
			pg_assert_contains($case . "\n            " . $function . '($type, $request, $user);' . "\n            break;", $explorer, $function . ' is called by its case');
		}

		pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handlers, $file . ' gate');
		pg_assert_false(strpos($handlers, '__FILE__') !== false, $file . ' paths built from PG_FUNCTIONS_DIR');
		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/" . $file . "');", $explorer, $file . ' loaded by the explorer');
	}
}

// Optimize applies the site's picture profile; WebP alone changes only the
// format (and keeps the quality); a WebP file is not converted to itself.
function test_explorer_upload_image_options()
{
	$settings = array(
		'product_max_dimension' => 2000,
		'product_min_dimension' => 300,
		'resize_quality' => 82);

	pg_assert_same(
		array('max_dimension' => 2000, 'min_dimension' => 300, 'quality' => 82),
		pg_explorer_upload_image_options($settings, true, false, 'jpg'),
		'optimize');

	pg_assert_same(
		array('max_dimension' => 2000, 'min_dimension' => 300, 'quality' => 82, 'format' => 'webp'),
		pg_explorer_upload_image_options($settings, true, true, 'jpg'),
		'optimize and webp');

	pg_assert_same(
		array('quality' => 82, 'format' => 'webp'),
		pg_explorer_upload_image_options($settings, false, true, 'png'),
		'webp only: nothing scaled');

	pg_assert_same(
		array('quality' => 82),
		pg_explorer_upload_image_options($settings, false, true, 'webp'),
		'a webp file keeps its format');

	pg_assert_same(
		array('format' => 'webp'),
		pg_explorer_upload_image_options(array(), false, true, 'jpg'),
		'no quality setting');
}

// The WebP copy is named after everything before the last dot.
function test_explorer_webp_base_name()
{
	pg_assert_same('cat', pg_explorer_webp_base_name('cat.jpg'), 'one extension');
	pg_assert_same('cat.old', pg_explorer_webp_base_name('cat.old.png'), 'only the last one goes');
	pg_assert_same('cat', pg_explorer_webp_base_name('cat'), 'no extension');
	pg_assert_same('kedi-ğ', pg_explorer_webp_base_name('kedi-ğ.jpeg'), 'multibyte name');
}

// A batch with no errors succeeds; errors after some work are partial;
// errors with no work done are an error.
function test_explorer_batch_status()
{
	pg_assert_same('success', pg_explorer_batch_status(0, 3), 'all done');
	pg_assert_same('success', pg_explorer_batch_status(0, 0), 'nothing to do, nothing wrong');
	pg_assert_same('partial', pg_explorer_batch_status(2, 1), 'some done');
	pg_assert_same('error', pg_explorer_batch_status(1, 0), 'none done');
}
