<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's recursive delete pre-flight and recycle bin
 * sub-action handlers (includes/explorer/handle_recycle.php): the handlers
 * live there, behind the gate, pg_explorer_handle() calls them, a folder's
 * subtree comes deepest first, and a bin action's answer follows what was
 * done and refused.
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
function test_explorer_recycle_handlers_live_in_their_own_file()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$handler = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_recycle.php');

	$calls = array(
		'explorer_delete_check' => 'pg_explorer_handle_delete_check($type, $request, $user)',
		'explorer_recycle_delete' => 'pg_explorer_handle_recycle_delete($type, $request, $user)',
		'explorer_recycle_restore' => 'pg_explorer_handle_recycle_restore($type, $request, $user)',
		'explorer_hard_delete' => 'pg_explorer_handle_hard_delete($type, $request, $user)',
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
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_recycle.php');", $explorer, 'loaded by the explorer');
}

// Children come before their parent, the folder itself last, each with its
// name; folders outside the subtree are left out.
function test_explorer_subtree_bottom_up()
{
	$map = array(
		1 => array('folder_id' => 1, 'folder_parent' => 0, 'folder_name' => 'Root'),
		2 => array('folder_id' => 2, 'folder_parent' => 1, 'folder_name' => 'A'),
		3 => array('folder_id' => 3, 'folder_parent' => 2, 'folder_name' => 'A1'),
		4 => array('folder_id' => 4, 'folder_parent' => 2, 'folder_name' => 'A2'),
		5 => array('folder_id' => 5, 'folder_parent' => 3, 'folder_name' => 'A1x'),
		6 => array('folder_id' => 6, 'folder_parent' => 1, 'folder_name' => 'B'),
	);

	pg_assert_same(array(
		array('id' => 5, 'name' => 'A1x'),
		array('id' => 3, 'name' => 'A1'),
		array('id' => 4, 'name' => 'A2'),
		array('id' => 2, 'name' => 'A'),
	), pg_explorer_subtree_bottom_up($map, 2), 'subtree of A');

	pg_assert_same(array(array('id' => 6, 'name' => 'B')), pg_explorer_subtree_bottom_up($map, '6'), 'a folder without children');
}

// Nothing refused is success; refusals next to done items are partial;
// refusals alone are an error.
function test_explorer_bin_status()
{
	pg_assert_same('success', pg_explorer_bin_status(3, array()), 'all done');
	pg_assert_same('success', pg_explorer_bin_status(0, array()), 'nothing to do');
	pg_assert_same('partial', pg_explorer_bin_status(2, array('Access denied for a.')), 'some refused');
	pg_assert_same('error', pg_explorer_bin_status(0, array('Access denied for a.')), 'all refused');
}
