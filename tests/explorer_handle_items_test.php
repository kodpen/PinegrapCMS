<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's tree, new folder, new file, rename and move
 * sub-action handlers (includes/explorer/handle_items.php): the handlers live
 * there, behind the gate, pg_explorer_handle() calls them, a bare new file
 * name gets a text extension and a move's status follows what moved and
 * what failed.
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
// case in pg_explorer_handle(); the tree gets the folders the person may see.
function test_explorer_items_handlers_live_in_their_own_file()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$handler = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_items.php');

	$calls = array(
		'explorer_tree' => 'pg_explorer_handle_tree($type, $request, $user, $folders_that_user_has_access_to)',
		'explorer_create_folder' => 'pg_explorer_handle_create_folder($type, $request, $user)',
		'explorer_create_file' => 'pg_explorer_handle_create_file($type, $request, $user)',
		'explorer_rename' => 'pg_explorer_handle_rename($type, $request, $user)',
		'explorer_move' => 'pg_explorer_handle_move($type, $request, $user)',
	);

	foreach ($calls as $case => $call) {
		$function = substr($call, 0, strpos($call, '('));

		pg_assert_contains('function ' . $call, $handler, $function);
		pg_assert_true(function_exists($function), $function . ' loaded');
		pg_assert_false(strpos($explorer, 'function ' . $function . '(') !== false, $function . ' not defined twice');
		pg_assert_contains("case '" . $case . "':\n            " . $call . ";\n            break;", $explorer, $case . ' calls it');
	}

	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handler, 'gate');
	pg_assert_false(strpos($handler, '__FILE__') !== false, 'paths built from PG_FUNCTIONS_DIR');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_items.php');", $explorer, 'loaded by the explorer');
	pg_assert_contains('pg_explorer_child_folders($node_id, $user, $folders_that_user_has_access_to)', $handler, 'the tree keeps the folder filter');
}

// A name without a dot gets ".txt"; a name with any dot is kept as typed.
function test_explorer_new_file_name()
{
	pg_assert_same('notes.txt', pg_explorer_new_file_name('notes'), 'bare name');
	pg_assert_same('notes.md', pg_explorer_new_file_name('notes.md'), 'own extension');
	pg_assert_same('.htaccess', pg_explorer_new_file_name('.htaccess'), 'leading dot');
	pg_assert_same('notes.', pg_explorer_new_file_name('notes.'), 'trailing dot');
	pg_assert_same('Yeni dosya.txt', pg_explorer_new_file_name('Yeni dosya'), 'multibyte bare name');
}

// Nothing failed is success, even when nothing moved (already in place);
// some moved and some failed is partial; none moved and some failed is error.
function test_explorer_move_status()
{
	pg_assert_same('success', pg_explorer_move_status(3, array()), 'all moved');
	pg_assert_same('success', pg_explorer_move_status(0, array()), 'already there');
	pg_assert_same('partial', pg_explorer_move_status(2, array('Access denied for a.')), 'some failed');
	pg_assert_same('error', pg_explorer_move_status(0, array('Access denied for a.')), 'all failed');
}
