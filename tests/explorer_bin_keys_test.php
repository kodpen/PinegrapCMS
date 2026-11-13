<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The File Manager's Recycle Bin from the keyboard and on the server
 * (#8980): Ctrl+C, Ctrl+X and F2 do in the bin what its menu offers
 * (nothing, with a note), Enter opens a binned file as itself instead of in
 * the Edit File window, and the server refuses to copy, rename or save a
 * binned row. A row moved out of the bin by a drag gets its own name back
 * as Restore gives it, and only rows that really moved lose their bin
 * record. The browser script is pinned by its source; the PHP helper is
 * run.
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

// The body of one function in the screen's script, up to the next function
// declared at the same indent.
function explorer_bin_keys_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    function ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// One PHP function's source, up to the next top-level function.
function explorer_bin_keys_php_function($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// Copy, cut and rename say why nothing happens in the bin; the rename check
// itself says no there, and a file opens as itself.
function test_explorer_bin_keys_follow_the_menu()
{
	$clipboard = explorer_bin_keys_function('clipboardSet');
	$renamable = explorer_bin_keys_function('itemRenamable');
	$rename = explorer_bin_keys_function('beginRename');
	$activate = explorer_bin_keys_function('activateItem');

	pg_assert_contains('if (insideBin()) { toast(L.bin_restore_first, false); return; }', $clipboard, 'Ctrl+C and Ctrl+X');
	pg_assert_contains('if (insideBin()) { return false; }', $renamable, 'nothing in the bin is renamable');
	pg_assert_contains('if (item && insideBin() && !insideCatalog()) { toast(L.bin_restore_first, false); return; }', $rename, 'F2');
	pg_assert_contains('if (item.can_edit && !insideBin()) {', $activate, 'Enter and double click');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	pg_assert_contains("'bin_restore_first' => lang('Restore items from the Recycle Bin before you copy, cut or rename them.'),", $source);
}

// The server refuses what the keys no longer send: a paste of a binned file
// or page, a rename of anything in the bin, a save of the Edit File window.
function test_explorer_bin_server_refusals()
{
	$paste = explorer_bin_keys_php_function('includes/explorer/handle_files.php', 'pg_explorer_handle_paste');
	$rename = explorer_bin_keys_php_function('includes/explorer/handle_items.php', 'pg_explorer_handle_rename');
	$save = explorer_bin_keys_php_function('includes/explorer/handle_file_window.php', 'pg_explorer_handle_file_save');

	pg_assert_contains("if (pg_recycle_is_inside((int) \$file['folder'])) {\n                    \$errors[] = pg_recycle_binned_message(\$file['name'], \$file['id']);", $paste, 'paste of a file');
	pg_assert_contains("if (pg_recycle_is_inside((int) \$page['folder'])) {\n                    \$errors[] = pg_recycle_binned_message(\$page['name'], \$page['id']);", $paste, 'paste of a page');

	pg_assert_contains("if (pg_recycle_is_inside(\$item_id)) {\n                respond(array('status' => 'error', 'message' => pg_recycle_binned_message(\$folder['folder_name'], \$item_id)));", $rename, 'rename of a folder');
	pg_assert_contains("if (pg_recycle_is_inside((int) \$page['page_folder'])) {", $rename, 'rename of a page');
	pg_assert_contains("if (pg_recycle_is_inside((int) \$file['folder'])) {\n                respond(array('status' => 'error', 'message' => pg_recycle_binned_message(\$file['name'], \$item_id)));", $rename, 'rename of a file');

	pg_assert_contains("if (pg_recycle_is_inside((int) \$file['folder'])) {\n        respond(array('status' => 'error', 'message' => pg_recycle_binned_message(\$file['name'], \$file_id)));", $save, 'save of the file window');

	// Checked before the name or the bytes are touched.
	pg_assert_true(strpos($save, 'pg_recycle_binned_message') < strpos($save, 'prepare_file_name('), 'refused before the name is prepared');
	pg_assert_true(strpos($rename, "pg_recycle_binned_message(\$file['name']") < strpos($rename, '@rename('), 'refused before the disk rename');
}

// A move out of the bin releases the parked name of each row that really
// moved and had a bin record, and says when the name was taken; a row that
// failed keeps its record.
function test_explorer_move_out_of_the_bin_restores_the_name()
{
	$move = explorer_bin_keys_php_function('includes/explorer/handle_items.php', 'pg_explorer_handle_move');

	pg_assert_contains("\$moved_items[] = array('file', \$item_id);", $move);
	pg_assert_contains("\$moved_items[] = array('page', \$item_id);", $move);
	pg_assert_contains("\$moved_items[] = array('folder', \$item_id);", $move);
	pg_assert_contains('foreach ($moved_items as $moved_item) {', $move, 'only what moved');
	pg_assert_false(strpos($move, 'foreach ($items as $item) {' . "\n" . '                $item_kind') !== false, 'the old sweep over every requested item is gone');

	$release = strpos($move, '$released = pg_recycle_release_name($item_kind, $item_id, $user);');
	$forget = strpos($move, "db(\"DELETE FROM recycle_bin WHERE (item_type = '\" . e(\$item_kind) . \"') AND (item_id = '\" . e(\$item_id) . \"')\");");

	pg_assert_true($release !== false, 'the name is released');
	pg_assert_true(($forget !== false) && ($release < $forget), 'before the record goes');
	pg_assert_contains("pg_explorer_restore_notes(\$released['from'], '', \$released['to'])", $move, 'a taken name is said');
}

// The refusal names the row as the operator knows it.
function test_explorer_bin_refusal_names_the_unparked_name()
{
	pg_assert_contains('photo.jpg', pg_recycle_binned_message('pgbin_7383_photo.jpg', 7383), 'parked name');
	pg_assert_false(strpos(pg_recycle_binned_message('pgbin_7383_photo.jpg', 7383), 'pgbin_') !== false, 'no prefix');
	pg_assert_contains('pgbin_9_photo.jpg', pg_recycle_binned_message('pgbin_9_photo.jpg', 7383), 'another row\'s prefix is not trusted');
	pg_assert_contains('notes.txt', pg_recycle_binned_message('notes.txt', 12), 'a name that was never parked');
	pg_assert_contains('Recycle Bin', pg_recycle_binned_message('notes.txt', 12));
}
