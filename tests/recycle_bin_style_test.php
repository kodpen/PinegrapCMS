<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of sending a page style to the Recycle Bin
 * (view_folder_and_files_f.php): the id list a kept style records for its
 * folders and pages, and the trimming of a kept row to the columns its table
 * still has when the style is restored. Also the message the design list and
 * the editor show after a delete (pg_designer_delete_design_message() in
 * includes/fn/designer.php).
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

// Ids come back as positive integers, once each, in their first order.
function test_recycle_bin_style_id_list_cleans_ids()
{
	pg_assert_same(array(4, 7, 9), pg_recycle_id_list(array('4', 7, '7', 0, -3, 'abc', 9, '4')), 'mixed list');
	pg_assert_same(array(), pg_recycle_id_list(array()), 'empty list');
	pg_assert_same(array(), pg_recycle_id_list(null), 'no list');
	pg_assert_same(array(12), pg_recycle_id_list('12'), 'single value');
}

// The keys are 0, 1, 2 after duplicates are dropped, so json_encode() writes
// a list and not an object.
function test_recycle_bin_style_id_list_encodes_as_list()
{
	pg_assert_same('[5,3]', json_encode(pg_recycle_id_list(array(5, 5, 3))), 'json list');
}

// A column the table no longer has is left out; a null value is kept so the
// INSERT writes NULL rather than dropping the column.
function test_recycle_bin_style_row_for_columns()
{
	$row = array(
		'style_id' => '42',
		'style_name' => 'Home',
		'style_code' => null,
		'dropped_column' => 'x',
	);

	pg_assert_same(
		array('style_id' => '42', 'style_name' => 'Home', 'style_code' => null),
		pg_recycle_row_for_columns($row, array('style_id', 'style_name', 'style_code', 'added_column')),
		'trimmed row');

	pg_assert_same(array(), pg_recycle_row_for_columns($row, array()), 'no columns');
	pg_assert_same(array(), pg_recycle_row_for_columns(null, array('style_id')), 'no row');
}

// After a delete into the bin the message says where the design went, and the
// folder note says restoring brings the default back.
function test_recycle_bin_style_delete_message_recycled()
{
	$message = pg_designer_delete_design_message(array('recycled' => true, 'binned' => 2, 'home' => 0, 'folders' => 1));

	pg_assert_contains('2 page(s) were moved to the Recycle Bin', $message, 'pages');
	pg_assert_contains('restoring the design puts it back', $message, 'folders');

	pg_assert_same(
		'The design was moved to the Recycle Bin.',
		pg_designer_delete_design_message(array('recycled' => true, 'binned' => 0, 'home' => 0, 'folders' => 0)),
		'no pages');
}

// Without the bin column the old permanent-delete wording stays.
function test_recycle_bin_style_delete_message_permanent()
{
	pg_assert_same(
		'The style has been deleted.',
		pg_designer_delete_design_message(array('recycled' => false, 'binned' => 0, 'home' => 0, 'folders' => 0)),
		'plain delete');

	$message = pg_designer_delete_design_message(array('recycled' => false, 'binned' => 1, 'home' => 1, 'folders' => 2));

	pg_assert_contains('The design was deleted; 1 page(s)', $message, 'pages');
	pg_assert_contains('no home page', $message, 'home');
	pg_assert_contains('that setting was cleared.', $message, 'folders');
	pg_assert_false(strpos($message, 'restoring') !== false, 'no restore promise');
}
