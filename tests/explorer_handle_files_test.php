<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's paste, delete, design flag, upload folder choices
 * and folder settings sub-action handlers (includes/explorer/handle_files.php
 * and handle_folders.php): the handlers live there, behind the gate,
 * pg_explorer_handle() calls them, and the design and archived flags are set
 * only by an exact "1".
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
// case in pg_explorer_handle(); the folder choices get the folders the
// person may see.
function test_explorer_files_and_folders_handlers_live_in_their_own_files()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	$files = array(
		'handle_files.php' => array(
			'explorer_paste' => 'pg_explorer_handle_paste($type, $request, $user)',
			'explorer_delete_files' => 'pg_explorer_handle_delete_files($type, $request, $user)',
			'explorer_files_design' => 'pg_explorer_handle_files_design($type, $request, $user)',
		),
		'handle_folders.php' => array(
			'explorer_folder_options' => 'pg_explorer_handle_folder_options($type, $request, $user, $folders_that_user_has_access_to)',
			'explorer_folder_settings_get' => 'pg_explorer_handle_folder_settings_get($type, $request, $user)',
			'explorer_folder_settings_set' => 'pg_explorer_handle_folder_settings_set($type, $request, $user)',
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

	$folders = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_folders.php');
	pg_assert_contains('pg_explorer_child_folders($parent_id, $user, $folders_that_user_has_access_to)', $folders, 'the folder choices keep the folder filter');
}

// Only "1" turns the design flag on; anything else, missing included, is "0".
function test_explorer_files_design_flag()
{
	pg_assert_same('1', pg_explorer_files_design_flag(array('design' => '1')), 'string one');
	pg_assert_same('1', pg_explorer_files_design_flag(array('design' => 1)), 'integer one');
	pg_assert_same('0', pg_explorer_files_design_flag(array('design' => '0')), 'zero');
	pg_assert_same('0', pg_explorer_files_design_flag(array('design' => 'yes')), 'other text');
	pg_assert_same('0', pg_explorer_files_design_flag(array()), 'missing');
}

// Only "1" archives a folder; anything else, missing included, is empty.
function test_explorer_folder_archived_value()
{
	pg_assert_same('1', pg_explorer_folder_archived_value(array('archived' => '1')), 'string one');
	pg_assert_same('1', pg_explorer_folder_archived_value(array('archived' => 1)), 'integer one');
	pg_assert_same('', pg_explorer_folder_archived_value(array('archived' => '0')), 'zero');
	pg_assert_same('', pg_explorer_folder_archived_value(array('archived' => 'on')), 'other text');
	pg_assert_same('', pg_explorer_folder_archived_value(array()), 'missing');
}
