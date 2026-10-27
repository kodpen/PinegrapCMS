<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's file window, ERP documents and "Shared"
 * sub-action handlers (includes/explorer/handle_file_window.php,
 * handle_erp.php and handle_shared.php): the handlers live there, behind the
 * gate, pg_explorer_handle() calls them, the window keeps the design flag
 * for anyone below a designer, the ERP search is trimmed and capped, and a
 * zero expiration date means no end.
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

// Each handler is loaded by the explorer's functions and called from its
// case in pg_explorer_handle(); the shared list gets the folders the person
// may see.
function test_explorer_file_window_erp_shared_handlers_live_in_their_own_files()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	$files = array(
		'handle_file_window.php' => array(
			"case 'explorer_file_get':" => 'pg_explorer_handle_file_get($type, $request, $user)',
			"case 'explorer_file_save':" => 'pg_explorer_handle_file_save($type, $request, $user)',
		),
		'handle_erp.php' => array(
			"case 'explorer_erp_tree':\n        case 'explorer_erp_list':" => 'pg_explorer_handle_erp_tree_list($type, $request, $user)',
		),
		'handle_shared.php' => array(
			"case 'explorer_shared_list':" => 'pg_explorer_handle_shared_list($type, $request, $user, $folders_that_user_has_access_to)',
		),
	);

	foreach ($files as $file => $calls) {
		$handler = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/' . $file);

		foreach ($calls as $case => $call) {
			$function = substr($call, 0, strpos($call, '('));

			pg_assert_contains('function ' . $call, $handler, $function);
			pg_assert_true(function_exists($function), $function . ' loaded');
			pg_assert_false(strpos($explorer, 'function ' . $function . '(') !== false, $function . ' not defined twice');
			pg_assert_contains($case . "\n            " . $call . ";\n            break;", $explorer, $function . ' called from its case');
		}

		pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handler, $file . ' gate');
		pg_assert_false(strpos($handler, '__FILE__') !== false, $file . ' paths built from PG_FUNCTIONS_DIR');
		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/" . $file . "');", $explorer, $file . ' loaded by the explorer');
	}

	$shared = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_shared.php');
	pg_assert_contains('pg_explorer_folder_visible($row_folder_id, $folders_that_user_has_access_to)', $shared, 'the shared list keeps the folder filter');
}

// A designer sets the flag, only by an exact "1"; anyone else keeps the
// file's own.
function test_explorer_file_save_design()
{
	$designer = array('role' => 1);
	$manager = array('role' => 2);
	$plain = array('design' => 0);
	$design = array('design' => 1);

	pg_assert_same('1', pg_explorer_file_save_design(array('design' => '1'), $designer, $plain), 'designer turns it on');
	pg_assert_same('0', pg_explorer_file_save_design(array('design' => '0'), $designer, $design), 'designer turns it off');
	pg_assert_same('0', pg_explorer_file_save_design(array(), $designer, $design), 'designer sending nothing turns it off');
	pg_assert_same('1', pg_explorer_file_save_design(array('design' => '1'), array('role' => 0), $plain), 'administrator turns it on');
	pg_assert_same('0', pg_explorer_file_save_design(array('design' => '1'), $manager, $plain), 'manager cannot turn it on');
	pg_assert_same('1', pg_explorer_file_save_design(array('design' => '0'), $manager, $design), 'manager cannot turn it off');
}

// Trimmed, capped at 100 characters (multibyte counted as one), empty when
// missing.
function test_explorer_erp_search_term()
{
	pg_assert_same('fatura', pg_explorer_erp_search_term(array('search' => '  fatura ')), 'trimmed');
	pg_assert_same('', pg_explorer_erp_search_term(array()), 'missing');
	pg_assert_same(str_repeat('a', 100), pg_explorer_erp_search_term(array('search' => str_repeat('a', 150))), 'capped');
	pg_assert_same(str_repeat('ş', 100), pg_explorer_erp_search_term(array('search' => str_repeat('ş', 101))), 'multibyte capped by characters');
}

// A real date stays; the zero date and an empty value mean no end.
function test_explorer_shared_expiration_date()
{
	pg_assert_same('2026-12-31', pg_explorer_shared_expiration_date('2026-12-31'), 'real date');
	pg_assert_same('', pg_explorer_shared_expiration_date('0000-00-00'), 'zero date');
	pg_assert_same('', pg_explorer_shared_expiration_date(''), 'empty');
	pg_assert_same('', pg_explorer_shared_expiration_date(null), 'null');
}
