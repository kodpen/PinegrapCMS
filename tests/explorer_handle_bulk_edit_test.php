<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's bulk page edit, bulk file edit and folder access
 * sub-action handlers (includes/explorer/handle_bulk_edit.php and
 * handle_folder_access.php): the handlers live there, behind the gate,
 * pg_explorer_handle() calls them, the bulk page answer and message follow
 * what was refused and skipped, and a rights row keeps only a real date for
 * view access.
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
// case in pg_explorer_handle().
function test_explorer_bulk_edit_and_folder_access_handlers_live_in_their_own_files()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	$files = array(
		'handle_bulk_edit.php' => array(
			'explorer_bulk_page_options' => 'pg_explorer_handle_bulk_page_options($type, $request, $user)',
			'explorer_pages_bulk_edit' => 'pg_explorer_handle_pages_bulk_edit($type, $request, $user)',
			'explorer_bulk_file_options' => 'pg_explorer_handle_bulk_file_options($type, $request, $user)',
			'explorer_files_bulk_edit' => 'pg_explorer_handle_files_bulk_edit($type, $request, $user)',
		),
		'handle_folder_access.php' => array(
			'explorer_folder_access_get' => 'pg_explorer_handle_folder_access_get($type, $request, $user)',
			'explorer_folder_access_set' => 'pg_explorer_handle_folder_access_set($type, $request, $user)',
		),
	);

	foreach ($files as $file => $calls) {
		$handler = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/' . $file);

		foreach ($calls as $case => $call) {
			$function = substr($call, 0, strpos($call, '('));

			pg_assert_contains('function ' . $call, $handler, $function);
			pg_assert_true(function_exists($function), $function . ' loaded');
			pg_assert_false(strpos($explorer, 'function ' . $function . '(') !== false, $function . ' not defined twice');
			pg_assert_contains("case '" . $case . "':\n            " . $call . ";\n            break;", $explorer, $case . ' calls it');
		}

		pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handler, $file . ' gate');
		pg_assert_false(strpos($handler, '__FILE__') !== false, $file . ' paths built from PG_FUNCTIONS_DIR');
		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/" . $file . "');", $explorer, $file . ' loaded by the explorer');
	}
}

// A full stop is added only when the text does not already end with one.
function test_explorer_sentence_end()
{
	pg_assert_same('3 pages were updated.', pg_explorer_sentence_end('3 pages were updated'), 'added');
	pg_assert_same('3 sayfa güncellendi.', pg_explorer_sentence_end('3 sayfa güncellendi.'), 'kept');
	pg_assert_same('.', pg_explorer_sentence_end(''), 'empty');
}

// Nothing refused and nothing skipped is success; a skipped style or a
// refusal next to an update is partial; refusals alone are an error.
function test_explorer_bulk_pages_status()
{
	pg_assert_same('success', pg_explorer_bulk_pages_status(3, array(), 0), 'all updated');
	pg_assert_same('partial', pg_explorer_bulk_pages_status(3, array(), 2), 'style skipped on some');
	pg_assert_same('partial', pg_explorer_bulk_pages_status(2, array('Access denied for a.'), 0), 'some refused');
	pg_assert_same('error', pg_explorer_bulk_pages_status(0, array('Access denied for a.'), 0), 'all refused');
}

// Only view access (1) keeps a date, and only a real Y-m-d one.
function test_explorer_folder_access_expiration_date()
{
	pg_assert_same('2026-12-31', pg_explorer_folder_access_expiration_date(1, '2026-12-31'), 'view access, real date');
	pg_assert_same('', pg_explorer_folder_access_expiration_date(2, '2026-12-31'), 'edit access never expires');
	pg_assert_same('', pg_explorer_folder_access_expiration_date(0, '2026-12-31'), 'no access');
	pg_assert_same('', pg_explorer_folder_access_expiration_date(1, ''), 'empty');
	pg_assert_same('', pg_explorer_folder_access_expiration_date(1, '2026-02-30'), 'no such day');
	pg_assert_same('', pg_explorer_folder_access_expiration_date(1, '31/12/2026'), 'other format');
	pg_assert_same('', pg_explorer_folder_access_expiration_date(1, 'a-b-c'), 'not numbers');
}
