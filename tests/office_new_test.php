<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the empty workbook and document "New spreadsheet" and "New
 * document" make (includes/fn/office_new.php): their names, the packages
 * read back by the preview's readers as editable, and a first edit saved
 * into each and read back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function office_new_test_path($kind)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_new_test_');
	@unlink($path);

	return $path . '.' . $kind;
}

function test_office_new_names()
{
	pg_assert_same('New spreadsheet.xlsx', pg_office_new_name('xlsx'), 'a workbook');
	pg_assert_same('New document.docx', pg_office_new_name('docx'), 'a document');
	pg_assert_same('', pg_office_new_name('pptx'), 'no other kind');
	pg_assert_same('', pg_office_new_name('../xlsx'), 'nor anything else');
	pg_assert_same('', pg_office_new_bytes('csv'), 'no bytes for another kind');
}

function test_office_new_blank_cell()
{
	pg_assert_same('<c r="B3"/>', pg_xlsx_typed_cell('B3', array('t' => 'blank')), 'a blank cell holds no value');
	pg_assert_same('', pg_xlsx_typed_cell('B3', array('t' => 's', 'v' => '')), 'empty text still writes nothing');
}

function test_office_new_workbook_opens_editable()
{
	$path = office_new_test_path('xlsx');

	pg_assert_true(pg_office_new_write('xlsx', $path), 'written');
	pg_assert_same('xlsx', pg_office_kind(pg_office_new_name('xlsx')), 'edited in place by its name');

	$book = pg_office_xlsx_read($path, true);

	pg_assert_true($book['ok'], 'read by the preview');
	pg_assert_same(1, count($book['sheets']), 'one sheet');
	pg_assert_same('Sheet1', $book['sheets'][0]['name'], 'named as a spreadsheet names its first');
	pg_assert_same(PG_OFFICE_NEW_ROWS, count($book['sheets'][0]['rows']), 'a grid of empty rows to type into');
	pg_assert_same(array_fill(0, PG_OFFICE_NEW_COLUMNS, ''), $book['sheets'][0]['rows'][0]['cells'], 'each row of empty cells');
	pg_assert_same(PG_OFFICE_NEW_ROWS, $book['sheets'][0]['rows'][PG_OFFICE_NEW_ROWS - 1]['n'], 'numbered from 1');
	pg_assert_true($book['sheets'][0]['edit']['ok'], 'its cells may change');
	pg_assert_same(array(), $book['sheets'][0]['edit']['locked'], 'none locked');
	pg_assert_true(!empty($book['sheets_edit']['ok']), 'its sheets may change');

	$saved = pg_office_xlsx_save($path, array(
		array('sheet' => 0, 'name' => 'Sheet1', 'cell' => 'A1', 'value' => 'Ürün'),
		array('sheet' => 0, 'name' => 'Sheet1', 'cell' => 'B2', 'value' => '12,5'),
		array('sheet' => 0, 'name' => 'Sheet1', 'cell' => 'J30', 'value' => 'son'),
	), $book['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'a first edit saved: ' . json_encode($saved));

	$again = pg_office_xlsx_read($path);

	pg_assert_same('Ürün', $again['sheets'][0]['rows'][0]['cells'][0], 'text typed into A1');
	pg_assert_same('12.5', (string) $again['sheets'][0]['rows'][1]['cells'][1], 'a number typed into B2');
	pg_assert_same('son', $again['sheets'][0]['rows'][29]['cells'][9], 'the last cell of the grid');
	pg_assert_same('', $again['sheets'][0]['rows'][0]['cells'][1], 'the rest still empty');

	$rows = array();

	foreach (pg_sheet_rows($path, 'xlsx', $error) as $number => $cells) {
		$rows[$number] = $cells;
	}

	pg_assert_same('', $error, 'read by the importers\' reader too');
	pg_assert_same('Ürün', $rows[1][0]['v'], 'its typed text there');

	@unlink($path);
}

function test_office_new_document_opens_editable()
{
	$path = office_new_test_path('docx');

	pg_assert_true(pg_office_new_write('docx', $path), 'written');
	pg_assert_same('docx', pg_office_kind(pg_office_new_name('docx')), 'edited in place by its name');

	$zip = new ZipArchive();
	pg_assert_true($zip->open($path) === true, 'a zip');
	$names = array();

	for ($index = 0; $index < $zip->numFiles; $index++) {
		$names[] = $zip->getNameIndex($index);
	}

	sort($names);
	$document = $zip->getFromName('word/document.xml');
	$zip->close();

	pg_assert_same(array('[Content_Types].xml', '_rels/.rels', 'word/_rels/document.xml.rels', 'word/document.xml', 'word/settings.xml', 'word/styles.xml'), $names, 'the parts of a document');

	$dom = new DOMDocument();
	pg_assert_true($dom->loadXML($document), 'its main part is well formed');
	pg_assert_same(1, $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'p')->length, 'one empty paragraph');
	pg_assert_same(1, $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pgSz')->length, 'a page size');

	$read = pg_office_docx_read($path, true);

	pg_assert_true($read['ok'], 'read by the preview');
	pg_assert_true($read['edit']['ok'], 'its paragraphs may change');
	pg_assert_same(array('0' => ''), (array) $read['edit']['paragraphs'], 'its one paragraph, empty');
	pg_assert_true(strpos($read['html'], 'data-p="0"') !== false, 'drawn to be clicked');

	$saved = pg_office_docx_save($path, array(array('p' => 0, 'text' => 'Toplantı notları')), $read['version'], sys_get_temp_dir());

	pg_assert_true($saved['ok'], 'a first edit saved: ' . json_encode($saved));

	$again = pg_office_docx_read($path, true);

	pg_assert_same(array('0' => 'Toplantı notları'), (array) $again['edit']['paragraphs'], 'the typed text read back');

	@unlink($path);
}

function test_office_new_bytes()
{
	foreach (array('xlsx', 'docx') as $kind) {
		$bytes = pg_office_new_bytes($kind);

		pg_assert_same('PK', substr($bytes, 0, 2), $kind . ': a zip');
		pg_assert_true(strlen($bytes) < 4096, $kind . ': small');
	}
}

function test_office_new_doors()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_items.php');
	$folders = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/explorer.php');
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$workspace = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("case 'explorer_create_office':\n            pg_explorer_handle_create_office(\$type, \$request, \$user);\n            break;", $explorer, 'the File Manager makes one');
	pg_assert_contains("case 'explorer_create_office':", $panel, 'through the panel\'s explorer door');
	pg_assert_contains('check_edit_access($parent_id) == false) || pg_recycle_is_inside($parent_id)', $handler, 'where a new file may go');
	pg_assert_contains("type: 'explorer_create_office'", $folders, 'the New menu asks for it');
	pg_assert_contains('data-office-new="xlsx"', $folders, 'New spreadsheet');
	pg_assert_contains('data-office-new="docx"', $folders, 'New document');
	pg_assert_contains('openFileEditor(item || made)', $folders, 'opened in the file window');
	pg_assert_true(in_array('explorer_create_office', pg_page_cache_panel_sub_actions()['file_explorer']['dirty'], true), 'a new file marks the page cache dirty');

	$case = substr($actions, (int) strpos($actions, "case 'ws_file_office_new':"), 1500);

	pg_assert_contains("ws_action_channel(\$viewer, \$request, 'post')", $case, 'for who may post in the channel');
	pg_assert_contains('ws_message_send_file_bytes(', $case, 'posted with the upload checks');
	pg_assert_contains("api('ws_file_office_new'", $workspace, 'the attach menu asks for it');
	pg_assert_contains("previewFile(result.message.file", $workspace, 'opened in the preview window');
}
