<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the File Manager's Bulk Rename preview: explorer_rename with
 * preview set answers each typed file name as prepare_file_name() would
 * store it and writes nothing, the page cache stays as it is, and the
 * drawer (view_folders.php) draws, compares and counts those stored names,
 * with Apply off until they have arrived.
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

// Each name comes back in its stored form, in the same order; a blank or a
// value that is not text stays blank, so the drawer can keep it unchanged.
function test_bulk_rename_preview_names_are_stored_forms()
{
	pg_assert_same(
		array('Urun_photo.jpg', 'a_b.jpg', 'Sirket_ozet.pdf', 'photo.jpg', '', '', 'shell_php'),
		pg_explorer_rename_preview_names(array('Ürün photo.jpg', 'a b.jpg', 'Şirket özet.pdf', ' photo.jpg. ', '', array('x'), 'shell.php'))
	);
	pg_assert_same(array(), pg_explorer_rename_preview_names('photo.jpg'), 'not a list');
	pg_assert_same(array(), pg_explorer_rename_preview_names(null), 'nothing sent');
	pg_assert_same(500, count(pg_explorer_rename_preview_names(array_fill(0, 600, 'a.jpg'))), 'at most 500 names');
}

// The preview answers before the id and name checks, so it never reaches a
// write; the rename itself is unchanged below it.
function test_bulk_rename_preview_answers_before_any_write()
{
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_items.php');
	$start = strpos($handler, 'function pg_explorer_handle_rename(');
	pg_assert_true($start !== false, 'handler found');
	$body = substr($handler, $start);

	$preview = strpos($body, "if (!empty(\$request['preview'])) {");
	pg_assert_true($preview !== false, 'preview branch');
	pg_assert_true($preview < strpos($body, '$item_id = '), 'before the id is read');
	pg_assert_true($preview < strpos($body, 'db('), 'before the first write');
	pg_assert_contains("'names' => pg_explorer_rename_preview_names(", $body);
}

// A preview is asked while the operator types: it must not empty the stored
// pages the way a rename does.
function test_bulk_rename_preview_leaves_the_page_cache()
{
	$entry = array('file' => 'explorer.php', 'write' => true);

	pg_assert_true(pg_page_cache_panel_dirties('file_explorer', $entry, array('type' => 'explorer_rename')), 'a rename');
	pg_assert_false(pg_page_cache_panel_dirties('file_explorer', $entry, array('type' => 'explorer_rename', 'preview' => 1)), 'a preview');
	pg_assert_true(pg_page_cache_panel_dirties('file_explorer', $entry, array('type' => 'explorer_move', 'preview' => 1)), 'only the rename has a preview');
}

function explorer_bulk_rename_preview_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	$start = strpos($source, "\n    function " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n    function ", $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

// The plan draws, compares and counts the stored name; a file outside the
// catalog waits for the server's answer before it can be applied.
function test_bulk_rename_plan_uses_stored_names()
{
	$plan = explorer_bulk_rename_preview_function('bulkRenamePlan');

	pg_assert_true($plan !== '', 'bulkRenamePlan() found');
	pg_assert_contains('bulkRenameStoredName(item, typed)', $plan);
	pg_assert_contains("var key = name.toLocaleLowerCase('tr');", $plan, 'the clash key keeps its Turkish fold, on the stored name');
	pg_assert_contains('(name === item.name)', $plan, 'unchanged compares the stored name');

	$stored = explorer_bulk_rename_preview_function('bulkRenameStoredName');
	pg_assert_contains("item.kind !== 'file'", $stored, 'only files are prepared');
	pg_assert_contains('insideCatalog()', $stored, 'catalog names stay as typed');

	$ask = explorer_bulk_rename_preview_function('bulkRenameAskStored');
	pg_assert_contains("type: 'explorer_rename', preview: 1, names: ", $ask);

	$refresh = explorer_bulk_rename_preview_function('bulkRenameRefresh');
	pg_assert_contains('apply.disabled = (doable.length === 0) || waiting;', $refresh, 'Apply waits for the answer');
}

// The rename sends what was typed, so the server keeps its refusals (a
// blocked extension) and counts only a name that changed.
function test_bulk_rename_run_counts_changed_names()
{
	$run = explorer_bulk_rename_preview_function('runBulkRename');

	pg_assert_contains('name: row.typed', $run);
	pg_assert_contains('(response.name !== row.item.name)', $run);
}
