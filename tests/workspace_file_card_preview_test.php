<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's file opened by its name (the message's file card
 * and the Files tab in assets/js/workspace.js): which files open on the
 * screen, Preview and Download in words, and "Save as data", the Data
 * tables' import of the sheet a preview window shows
 * (includes/workspace/wtable_import.php, workspace_wtables.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_file_card_preview_test_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// Runs fileShowWay() of workspace.js under node over each file; false when
// node is missing, null when the function is not found.
function workspace_file_card_preview_test_ways($files)
{
	if (!preg_match('/^    function fileShowWay\(file\) \{.*?^    \}$/ms', workspace_file_card_preview_test_js(), $match)) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwsway');

	if ($file === false) {
		return false;
	}

	file_put_contents($file, $match[0] . "\n" . 'console.log(JSON.stringify(' . json_encode($files) . '.map(fileShowWay)));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function test_workspace_file_card_preview_which_files_open_on_the_screen()
{
	$ways = workspace_file_card_preview_test_ways(array(
		array('kind' => 'pdf', 'office' => false, 'text' => false),
		array('kind' => 'file', 'office' => true, 'text' => false),
		array('kind' => 'file', 'office' => true, 'text' => true),
		array('kind' => 'file', 'office' => false, 'text' => true),
		array('kind' => 'file', 'office' => false, 'text' => false),
		array('kind' => 'image', 'image' => true),
		array('kind' => 'video'),
		array('kind' => 'audio'),
	));

	pg_assert_true($ways !== null, 'workspace.js defines fileShowWay()');

	if ($ways === false) {
		return;
	}

	pg_assert_same(array('window', 'window', 'window', 'text', '', '', '', ''), $ways,
		'a PDF and an Office file or sheet open in the preview window, another text file in the text window, the rest nowhere');
}

function test_workspace_file_card_preview_payload_marks_the_files_shown()
{
	foreach (array('Report.docx', 'Budget.xlsx', 'Old.xls', 'Book.ods', 'Deck.pptx', 'list.csv', 'list.tsv') as $name) {
		pg_assert_true(pg_office_preview_kind($name) !== '', $name . ' opens in the preview window');
	}

	foreach (array('notes.txt', 'read.md', 'data.json') as $name) {
		pg_assert_true(ws_file_is_text($name), $name . ' opens in the text window');
	}

	pg_assert_same('', pg_office_preview_kind('archive.zip'), 'a zip is shown nowhere');
	pg_assert_false(ws_file_is_text('archive.zip'), 'nor read as text');
}

function test_workspace_file_card_preview_sheets_are_offered_as_data()
{
	foreach (array('Budget.xlsx', 'OLD.XLS', 'book.ods', 'list.csv', 'list.tsv') as $name) {
		pg_assert_true(ws_wtable_import_sheet_name($name), $name . ' is a sheet');
	}

	foreach (array('Report.docx', 'Deck.pptx', 'Macro.xlsm', 'notes.txt', 'Report.pdf', 'xlsx', '') as $name) {
		pg_assert_false(ws_wtable_import_sheet_name($name), '"' . $name . '" is not');
	}
}

function test_workspace_file_card_preview_who_sees_save_as_data()
{
	pg_assert_true(ws_wtable_import_file_offered('Budget.xlsx', true, true), 'who may post and make a table there sees it');
	pg_assert_false(ws_wtable_import_file_offered('Budget.xlsx', false, true), 'a reader does not');
	pg_assert_false(ws_wtable_import_file_offered('Budget.xlsx', true, false), 'nor who may not make a table in the channel');
	pg_assert_false(ws_wtable_import_file_offered('Report.docx', true, true), 'a document is no sheet');

	$tables = array(
		array('id' => 4, 'name' => 'Stock', 'access' => 'owner'),
		array('id' => 5, 'name' => 'Shared', 'access' => 'view'),
		array('id' => 6, 'name' => 'Leads', 'access' => 'edit'),
		array('id' => 7, 'name' => 'Gone', 'access' => ''),
	);
	pg_assert_same(array(array('id' => 4, 'name' => 'Stock'), array('id' => 6, 'name' => 'Leads')), ws_wtable_import_file_targets($tables),
		'rows go only to a table the person may write');
	pg_assert_same(array(array('id' => 4, 'name' => 'Stock')), ws_wtable_import_file_targets($tables, 1), 'at most the limit');
}

function test_workspace_file_card_preview_reads_the_sheet_shown()
{
	$types = array('worksheet', 'chartsheet', 'worksheet', 'worksheet');
	pg_assert_same(0, ws_wtable_import_worksheet_at($types, 0), 'the first tab is the first worksheet');
	pg_assert_same(-1, ws_wtable_import_worksheet_at($types, 1), 'a chart sheet holds no cells');
	pg_assert_same(1, ws_wtable_import_worksheet_at($types, 2), 'a chart sheet before is not counted');
	pg_assert_same(2, ws_wtable_import_worksheet_at($types, 3), 'nor for the last');
	pg_assert_same(-1, ws_wtable_import_worksheet_at($types, 9), 'a tab past the end is none');
	pg_assert_same(3, ws_wtable_import_worksheet_at(array(), 3), 'an unknown list takes the tab as given');

	$path = tempnam(sys_get_temp_dir(), 'pgwstsv');
	file_put_contents($path, "Name\tCity\nAda\tLondon\nAlan\tWilmslow\n");
	$read = ws_wtable_import_file_table($path, 'people.tsv', 10);
	@unlink($path);

	pg_assert_true($read['ok'], 'a .tsv is read');
	pg_assert_same(array(array('Name', 'City'), array('Ada', 'London'), array('Alan', 'Wilmslow')), $read['table'], 'by its tabs');

	$import = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_import.php');
	pg_assert_contains('pg_products_import_to_csv($path, $file_name, $error, max(0, (int) $sheet))', $import, 'a workbook is read at the sheet asked');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_wtables.php');
	pg_assert_contains("ws_wtable_import_message_read(\$viewer, \$message_id, (int) \$field('sheet')", $screen, 'the import\'s own route reads a channel\'s file');
	pg_assert_contains("\$append ? 0 : \$read['channel_id']", $screen, 'a new table is kept for that channel');
	pg_assert_contains("ws_wtable_import_create(\$viewer, \$field('name'), \$plan, \$kept['rows'], \$kept['channel'])", $screen, 'and made there');
}

function test_workspace_file_card_preview_card_and_row_draw()
{
	$js = workspace_file_card_preview_test_js();

	pg_assert_contains("var name = el(way ? 'button' : 'a', 'ws-file-name');", $js, 'the card\'s name opens a file shown');
	pg_assert_contains("var link = el(way ? 'button' : 'a', 'ws-file-link');", $js, 'and the Files tab\'s');
	pg_assert_contains("if (!event.target.closest('.ws-file-tools')) {", $js, 'a click on the card opens it, its buttons aside');
	pg_assert_contains("if (!event.target.closest('a, audio, button:not(.ws-file-link)')) {", $js, 'and on the row');
	pg_assert_contains("var look = fileAction(t('preview'), 'bi-eye');", $js, 'Preview carries its word');
	pg_assert_contains("tools.appendChild(fileAction(t('download'), 'bi-download', file));", $js, 'Download on the card');
	pg_assert_contains("row.appendChild(fileAction(t('download'), 'bi-download', message.file));", $js, 'Download in the Files tab');
	pg_assert_contains("var node = el(file ? 'a' : 'button', 'pg-button pg-button-quiet pg-button-sm ws-file-act');", $js, 'both quiet and small');
	pg_assert_contains("node.appendChild(el('span', 'pg-btn-label', label));", $js, 'the word in its own span');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_true((bool) preg_match('/@media \(max-width: 575\.98px\) \{\s*\.ws-file-act \.pg-btn-label \{[^}]*clip-path: inset\(50%\);/', $css),
		'under 576 px the word is kept for a screen reader only');
}

function test_workspace_file_card_preview_save_as_data_button()
{
	$js = workspace_file_card_preview_test_js();

	pg_assert_contains("tableSlot.appendChild(previewTableButton(file, data.table_import, box));", $js, 'the window\'s head carries Save as data when offered');
	pg_assert_contains("lookButton('pg-button pg-button-quiet pg-button-sm', t('file_table_save'), 'bi-table')", $js, 'as a quiet button');
	pg_assert_contains("['wt_action', 'import_read'], ['wt_table', into.value], ['message', String(file.message_id)], ['sheet', String(previewSheetShown(box))]", $js, 'posting the import\'s own route');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	pg_assert_contains("ws_wtable_import_file_offered(\$message['file_name'], ws_can_post_channel(\$viewer, \$channel), ws_wtable_home_writable(\$viewer, (int) \$channel['id']) !== null)", $actions, 'the server offers it');

	$strings = ws_file_edit_js_strings();
	pg_assert_same('Save as data', $strings['file_table_save'] ?? '', 'the button is a screen text');
	pg_assert_same('Read the sheet', $strings['file_table_read'] ?? '', 'and its next step');
}
