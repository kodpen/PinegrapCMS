<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for File Manager fixes: Duplicate with Find & Replace reads its
 * switch, paste and Duplicate say the names copies took, the bin counts only
 * records whose item exists and the classic page deletes clear the record,
 * the bin's details panel, the backup details rows and the weekly backup's
 * week.
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
require_once(PG_FUNCTIONS_DIR . '/duplicate_folder_f.php');

function pg_test_fm_fixes_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The rows count only while the switch is on; an empty Find is skipped.
function test_duplicate_folder_keywords_follow_the_switch()
{
	$fields = array('find_1' => 'qa', 'replace_1' => 'qa2', 'find_2' => '', 'replace_2' => 'x', 'find_3' => 'old', 'replace_3' => '');

	pg_assert_same(array(), pg_duplicate_folder_keywords($fields), 'switch off');

	$fields['find_and_replace_check'] = '1';
	pg_assert_same(
		array(array('find' => 'qa', 'replace' => 'qa2'), array('find' => 'old', 'replace' => '')),
		pg_duplicate_folder_keywords($fields),
		'switch on');

	$fields['find_4'] = array('x');
	pg_assert_same(2, count(pg_duplicate_folder_keywords($fields)), 'an array value is no row');
	pg_assert_same(array(), pg_duplicate_folder_keywords('nope'), 'no fields');
}

// The screen posts the switch itself; no hidden field stands in for it.
function test_duplicate_folder_screen_reads_the_switch()
{
	$template = pg_test_fm_fixes_source('includes/templates/duplicate_folder.php');
	$screen = pg_test_fm_fixes_source('duplicate_folder.php');

	pg_assert_true(strpos($template, 'name="find_replace"') === false, 'no hidden find_replace field');
	pg_assert_contains('name="find_and_replace_check"', $template);
	pg_assert_contains("\$form->get('find_and_replace_check')", $screen);
	pg_assert_contains('pg_duplicate_folder_keywords(', $screen);
	pg_assert_true(strpos($screen, "get('find_replace') == 'true'") === false, 'the old check is gone');
	pg_assert_contains('Files and sub-folders were not copied.', $screen, 'the notice says what was not copied');
}

function test_paste_message_names_the_new_names()
{
	pg_assert_same('2 item(s) were pasted.', pg_explorer_paste_message(2, array(), false));
	pg_assert_same('1 item(s) were duplicated.', pg_explorer_paste_message(1, array(), true));
	pg_assert_same('Duplicated as a[1].txt.', pg_explorer_paste_message(1, array(array('a.txt', 'a[1].txt')), true));
	pg_assert_same(
		'2 item(s) were pasted. Copied under a new name: a.txt → a[2].txt, b → b[1].',
		pg_explorer_paste_message(2, array(array('a.txt', 'a[2].txt'), array('b', 'b[1]')), false));

	$view = pg_test_fm_fixes_source('view_folders.php');
	pg_assert_contains("items: [{ kind: item.kind, id: item.id }], duplicate: true }", $view, 'Duplicate says it is one');
	pg_assert_contains('fmt(L.renamed_as, [response.name])', $view, 'rename names the stored name');
	pg_assert_contains('renamedUploads.push(file.name', $view, 'upload collects changed names');
}

// The bin counts and keeps only records whose item exists; a page style
// lives in its record and is never an orphan.
function test_recycle_bin_skips_records_of_gone_items()
{
	$condition = pg_recycle_orphan_condition_sql();

	foreach (array('folder' => 'folder.folder_id', 'page' => 'page.page_id', 'file' => 'files.id', 'short_link' => 'short_links.id') as $type => $column) {
		pg_assert_contains("(recycle_bin.item_type = '" . $type . "') AND NOT EXISTS", $condition, $type);
		pg_assert_contains($column . ' = recycle_bin.item_id', $condition, $column);
	}
	pg_assert_true(strpos($condition, "'style'") === false, 'styles are not orphans');

	$list = pg_test_fm_fixes_source('includes/explorer/handle_list.php');
	pg_assert_true(strpos($list, 'SELECT COUNT(*) FROM recycle_bin') === false, 'no raw count left');
	pg_assert_contains('pg_recycle_drop_orphans();', $list);

	foreach (array('edit_page.php', 'edit_pages.php') as $screen) {
		pg_assert_contains("pg_recycle_forget('page', \$page_id);", pg_test_fm_fixes_source($screen), $screen);
	}
}

// In the bin the panel says when, who and where back, and offers the bin's
// two actions instead of the address, edit and visit.
function test_bin_details_panel()
{
	$view = pg_test_fm_fixes_source('view_folders.php');

	pg_assert_contains('function binRowsHtml(item) {', $view);
	pg_assert_contains('entry.deleted_by', $view);
	pg_assert_contains('quickTools = binActionsHtml();', $view);
	pg_assert_contains("item.url && !item.backup && !item.short_link && !binned", $view, 'no park address');
	pg_assert_contains('<span class="visually-hidden"> \' + esc(title)', $view, 'the badge reads its title');
	pg_assert_contains('fmt(L.counts_styles, [binStyles])', $view, 'styles counted apart');
	pg_assert_contains("deleted_by'] = pg_explorer_editor_name(", pg_test_fm_fixes_source('includes/explorer/handle_list.php'));
}

function test_backup_auto_week()
{
	pg_assert_same(array('week' => 44, 'start' => '2026-10-26', 'end' => '2026-11-01'), pg_explorer_backup_auto_week('auto_backup_2026-10-44.zip'));
	pg_assert_same(array('week' => 1, 'start' => '2025-12-29', 'end' => '2026-01-04'), pg_explorer_backup_auto_week('auto_backup_2026-01-01'));
	pg_assert_same(array('week' => 53, 'start' => '2026-12-28', 'end' => '2027-01-03'), pg_explorer_backup_auto_week('auto_backup_2026-12-53.zip'));
	// Names written by the run's calendar year: week 1 in December, week 53 in January.
	pg_assert_same(array('week' => 1, 'start' => '2025-12-29', 'end' => '2026-01-04'), pg_explorer_backup_auto_week('auto_backup_2025-12-01.zip'));
	pg_assert_same(array('week' => 53, 'start' => '2026-12-28', 'end' => '2027-01-03'), pg_explorer_backup_auto_week('auto_backup_2027-01-53'));
	pg_assert_same(null, pg_explorer_backup_auto_week('dev_pinegrap_com_2026-10-27_01-35'));
	pg_assert_same(null, pg_explorer_backup_auto_week('auto_backup_2026-10-60.zip'));

	$view = pg_test_fm_fixes_source('view_folders.php');
	pg_assert_contains("esc(L.location) + '</th>", $view);
	pg_assert_true(strpos($view, "esc(L.folder) + '</th><td class=\"text-break\">' + esc(item.path)") === false, 'no Folder row with the item path');
}

function test_quick_look_key_is_translated()
{
	$view = pg_test_fm_fixes_source('view_folders.php');
	pg_assert_true(strpos($view, "' (Space)'") === false, 'no fixed English key name');
	pg_assert_contains("L.quick_look + ' (' + L.space_key + ')'", $view);
}
