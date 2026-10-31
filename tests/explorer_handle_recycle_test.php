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

// A restore goes to the folder it came from; to the root when that folder is
// gone or sits anywhere inside the bin, with the reason.
function test_explorer_restore_target()
{
	$map = array(
		1 => array('folder_id' => 1, 'folder_parent' => 0, 'folder_name' => 'Website'),
		7 => array('folder_id' => 7, 'folder_parent' => 1, 'folder_name' => 'Photos'),
		50 => array('folder_id' => 50, 'folder_parent' => 1, 'folder_name' => 'Recycle Bin'),
		8 => array('folder_id' => 8, 'folder_parent' => 50, 'folder_name' => 'pgbin_8_Docs'),
		9 => array('folder_id' => 9, 'folder_parent' => 8, 'folder_name' => 'Inner'));

	pg_assert_same(array('id' => 7, 'reason' => ''), pg_explorer_restore_target('7', $map, 50, 1), 'its own folder');
	pg_assert_same(array('id' => 1, 'reason' => 'gone'), pg_explorer_restore_target(99, $map, 50, 1), 'folder gone');
	pg_assert_same(array('id' => 1, 'reason' => 'gone'), pg_explorer_restore_target(0, $map, 50, 1), 'no folder recorded');
	pg_assert_same(array('id' => 1, 'reason' => 'binned'), pg_explorer_restore_target(8, $map, 50, 1), 'folder in the bin');
	pg_assert_same(array('id' => 1, 'reason' => 'binned'), pg_explorer_restore_target(9, $map, 50, 1), 'below a binned folder');
	pg_assert_same(array('id' => 7, 'reason' => ''), pg_explorer_restore_target(7, $map, 0, 1), 'no bin');
}

// A renamed item and one put in the root are each said; one that came back
// as it went says nothing.
function test_explorer_restore_notes()
{
	pg_assert_same(array(), pg_explorer_restore_notes('about', '', 'about'), 'unchanged');
	pg_assert_same(array(), pg_explorer_restore_notes('about', ''), 'not parked, own folder');
	pg_assert_same(array('about came back as about[1] because its name was taken.'), pg_explorer_restore_notes('about', '', 'about[1]'), 'renamed');
	pg_assert_same(array('logo.png went to the root folder because its folder is gone.'), pg_explorer_restore_notes('logo.png', 'gone'), 'gone');
	pg_assert_same(array('Docs went to the root folder because its folder is in the recycle bin.'), pg_explorer_restore_notes('Docs', 'binned', 'Docs'), 'binned');
	pg_assert_same(array(
		'Çay & Kahve came back as Çay & Kahve[1] because its name was taken.',
		'Çay & Kahve[1] went to the root folder because its folder is gone.'), pg_explorer_restore_notes('Çay & Kahve', 'gone', 'Çay & Kahve[1]'), 'both, plain text');
}

// The notes follow the count, or the refusals in a partial answer.
function test_explorer_restore_message()
{
	pg_assert_same('2 item(s) were restored from the recycle bin', pg_explorer_restore_message(2, array(), array()), 'no notes');
	pg_assert_same('2 item(s) were restored from the recycle bin. a went to the root folder because its folder is gone.',
		pg_explorer_restore_message(2, array(), array('a went to the root folder because its folder is gone.')), 'with a note');
	pg_assert_same('Access denied. a came back as a[1] because its name was taken.',
		pg_explorer_restore_message(1, array('Access denied'), array('a came back as a[1] because its name was taken.')), 'partial with a note');
}

// The handler collects a note per item that went to the root, was renamed,
// or is a page a manager restored while its style stays in the bin; the
// renamers say what they wrote.
function test_explorer_restore_reports_changes()
{
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_recycle.php');
	$names = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_names.php');
	$styles = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_pages_styles.php');

	pg_assert_contains("'message' => pg_explorer_restore_message(\$restored, \$errors, \$notes)", $handler, 'answer carries the notes');
	pg_assert_contains('$released = pg_recycle_release_name($item_kind, $item_id, $user);', $handler, 'release name result used');
	pg_assert_contains('$target[\'reason\']', $handler, 'root reason reported');
	pg_assert_contains('pg_explorer_restore_notes($restore_short_link[\'name\'], \'\', $restore_short_link_name)', $handler, 'short link rename');
	pg_assert_contains('pg_explorer_restore_notes($restored_style[\'original_name\'], \'\', $restored_style[\'name\'])', $handler, 'style rename');
	pg_assert_contains("which stays in the recycle bin until a designer restores it.", $handler, 'page without its style');
	pg_assert_same(3, substr_count($names, "return array('from' => \$original, 'to' => \$name);"), 'release name returns what it wrote');
	pg_assert_contains("\$out['original_name'] = \$name;", $styles, 'style restore returns its names');
}
