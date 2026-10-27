<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's ERP document helpers (includes/explorer/erp.php):
 * the moved functions live there, behind the gate, and the explorer loads
 * them.
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

// The helpers live in their own file, loaded by the explorer's functions.
function test_explorer_erp_helpers_live_in_their_own_file()
{
	$erp = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/erp.php');
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	foreach (array('pg_explorer_file_id_actions', 'pg_explorer_request_file_ids', 'pg_explorer_erp_ready', 'pg_explorer_erp_tree', 'pg_explorer_erp_folder_item', 'pg_explorer_erp_file_item') as $function) {
		pg_assert_contains('function ' . $function . '(', $erp, $function);
		pg_assert_false(strpos($explorer, 'function ' . $function . '(') !== false, $function . ' not defined twice');
		pg_assert_true(function_exists($function), $function . ' loaded');
	}

	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $erp, 'gate');
	pg_assert_false(strpos($erp, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/erp.php');", $explorer, 'loaded by the explorer');
}

// The file-id sub-actions and the ids a request names are unchanged by the
// move: an item without a kind counts as a file only for files-only actions.
function test_explorer_request_file_ids_kinds()
{
	$request = array('items' => array(array('id' => 4), array('id' => 5, 'kind' => 'file'), array('id' => 6, 'kind' => 'page')));

	pg_assert_same(array(5), pg_explorer_request_file_ids('explorer_move', $request), 'kindless item is not a file for move');
	pg_assert_same(array(4, 5), pg_explorer_request_file_ids('explorer_optimize', $request), 'kindless item is a file for optimize');
	pg_assert_same(array(9), pg_explorer_request_file_ids('explorer_file_get', array('id' => '9')), 'id read for file_get');
	pg_assert_true(in_array('explorer_zip_extract', pg_explorer_file_id_actions(), true), 'zip extract listed');
}
