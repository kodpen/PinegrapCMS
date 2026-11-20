<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the .csv and .tsv preview (includes/fn/office.php): the kind by
 * the name, the character set and the separator guessed from the bytes,
 * the cells as a spreadsheet program reads them (quoted separators, doubled
 * quotes and line breaks inside a quote, every line ending, blank lines),
 * the workbook preview's row, column and cell limits and the read's own byte
 * limit, the choice that reads the file again, and the doors that offer the
 * preview: view only, never offered for editing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The cells of each row of a text read with one separator.
function office_csv_test_cells($text, $delimiter, $whole = true)
{
	$rows = pg_office_csv_records($text, $delimiter, $whole, $cut);

	return array_map(function ($row) {
		return $row['cells'];
	}, $rows);
}

// A file on disk holding the bytes given.
function office_csv_test_file($bytes)
{
	$path = tempnam(sys_get_temp_dir(), 'pg_office_csv_');
	file_put_contents($path, $bytes);

	return $path;
}

function test_office_csv_kind_by_extension()
{
	pg_assert_same('csv', pg_office_preview_kind('Liste.CSV'), 'a .csv, whatever the case');
	pg_assert_same('csv', pg_office_preview_kind('veri.tsv'), 'a .tsv reads the same way');
	pg_assert_same('xlsx', pg_office_preview_kind('liste.xlsx'), 'a workbook as before');
	pg_assert_same('docx', pg_office_preview_kind('teklif.docx'), 'a document as before');
	pg_assert_same('', pg_office_preview_kind('notlar.txt'), 'plain text has no sheet');
	pg_assert_same('', pg_office_kind('liste.csv'), 'the kind the edits ask for leaves it out: view only');
	pg_assert_same('', pg_office_kind('veri.tsv'), 'nor a .tsv');
}

function test_office_csv_encoding_guess()
{
	pg_assert_same('utf-8', pg_office_csv_encoding("\xEF\xBB\xBFad;soyad"), 'a byte order mark says UTF-8');
	pg_assert_same('utf-8', pg_office_csv_encoding("ad;\xC5\x9Fehir\n"), 'valid UTF-8 without a mark');
	pg_assert_same('utf-8', pg_office_csv_encoding("a,b\n1,2\n"), 'plain ASCII reads as UTF-8');
	pg_assert_same('windows-1254', pg_office_csv_encoding("ad;\xDEehir\n\xC7i\xF0dem;\xFDl\xFDk\n"), 'Turkish letters as single bytes');
	pg_assert_same('utf-8', pg_office_csv_encoding(''), 'nothing reads as UTF-8');
}

function test_office_csv_text_in_utf8()
{
	pg_assert_same('ad;soyad', pg_office_csv_text("\xEF\xBB\xBFad;soyad", 'utf-8'), 'the mark is left out');
	pg_assert_same("\xC5\x9Eeker \xC4\x9Fi \xC4\xB0z \xC4\xB1 \xC5\x9F \xC4\x9E", pg_office_csv_text("\xDEeker \xF0i \xDDz \xFD \xFE \xD0", 'windows-1254'), 'the six Turkish letters of Windows-1254');
	pg_assert_same("\xE2\x82\xAC5", pg_office_csv_text("\x805", 'windows-1254'), 'the euro sign of the code page');
	pg_assert_same("a\xEF\xBF\xBDb", pg_office_csv_text("a\xFDb", 'utf-8'), 'a byte that is not UTF-8, read as UTF-8, becomes a replacement mark');
	pg_assert_same('ad', pg_office_csv_text("\xEF\xBB\xBFad", 'windows-1254'), 'the mark is never text, whichever set is chosen');
}

function test_office_csv_delimiter_guess()
{
	pg_assert_same('comma', pg_office_csv_delimiter("ad,soyad,yas\nAli,Kaya,30\nAyse,Demir,25\n", 'x.csv'), 'commas');
	pg_assert_same('semicolon', pg_office_csv_delimiter("urun;fiyat;adet\nelma;1,50;3\narmut;2,75;4\n", 'x.csv'), 'semicolons with decimal commas: Excel in Turkish');
	pg_assert_same('semicolon', pg_office_csv_delimiter("x;y\n1,5;2,5\n", 'x.csv'), 'a comma in every number does not win');
	pg_assert_same('tab', pg_office_csv_delimiter("ad\tsoyad\nAli\tKaya\n", 'x.csv'), 'tabs in a .csv');
	pg_assert_same('comma', pg_office_csv_delimiter("\"Kaya; Ali\",30\n\"Demir; Ayse\",25\n", 'x.csv'), 'a separator inside quotes is not counted');
	pg_assert_same('comma', pg_office_csv_delimiter("tek\nsutun\n", 'x.csv'), 'one column: the comma of a .csv');
	pg_assert_same('tab', pg_office_csv_delimiter("tek\nsutun\n", 'x.tsv'), 'one column: the tab of a .tsv');
	pg_assert_same('tab', pg_office_csv_delimiter("a,b\tc\nd,e\tf\n", 'x.tsv'), 'a tie goes to the kind the name says');
	pg_assert_same('comma', pg_office_csv_delimiter("a,b\tc\nd,e\tf\n", 'x.csv'), 'and to the comma for a .csv');
	pg_assert_same('comma', pg_office_csv_delimiter('', 'x.csv'), 'an empty file');
}

function test_office_csv_records_quotes_and_lines()
{
	pg_assert_same(array(array('a', 'b;c', 'd'), array('1', '2', '3')), office_csv_test_cells("a;\"b;c\";d\n1;2;3\n", ';'), 'a separator inside quotes stays in the cell');
	pg_assert_same(array(array('say "hi"', 'x')), office_csv_test_cells("\"say \"\"hi\"\"\",x", ','), 'a doubled quote is one quote');
	pg_assert_same(array(array("two\nlines", 'b'), array('c', 'd')), office_csv_test_cells("\"two\nlines\",b\nc,d\n", ','), 'a line break inside quotes stays in the cell');
	pg_assert_same(array(array("two\r\nlines", 'b'), array('c', 'd')), office_csv_test_cells("\"two\r\nlines\",b\r\nc,d\r\n", ','), 'Windows line endings, inside and between');
	pg_assert_same(array(array('a', 'b'), array('c', 'd')), office_csv_test_cells("a,b\rc,d\r", ','), 'old Mac line endings');
	pg_assert_same(array(array('a', 'b', '')), office_csv_test_cells("a,b,", ','), 'a separator at the end leaves an empty cell; no line break at the end');
	pg_assert_same(array(array('a'), array(), array('b')), office_csv_test_cells("a\n\nb\n", ','), 'a blank line stays as an empty row');
	pg_assert_same(array(array('a', "open,to\nthe end")), office_csv_test_cells("a,\"open,to\nthe end", ','), 'a quote never closed runs to the end of a whole file');
	pg_assert_same(array(array('ab"c"d', 'e')), office_csv_test_cells("ab\"c\"d,e", ','), 'a quote inside an unquoted cell is a character');
	pg_assert_same(array(array('quoted tail', 'x')), office_csv_test_cells("\"quoted\" tail,x", ','), 'text after a closing quote is kept, as a spreadsheet program keeps it');
	pg_assert_same(array(array('a', 'b')), office_csv_test_cells("a\tb\n", "\t"), 'tabs');
	pg_assert_same(array(), office_csv_test_cells('', ','), 'an empty file has no rows');

	$rows = pg_office_csv_records("a\n\nb\n", ',', true, $cut);
	pg_assert_same(array(1, 2, 3), array_column($rows, 'n'), 'rows are numbered as the file counts its records');
	pg_assert_false($cut, 'nothing cut');

	$rows = pg_office_csv_records("a,b\nc,\"half", ',', false, $cut);
	pg_assert_same(array(array('a', 'b')), array_column($rows, 'cells'), 'the record the read stopped inside is left out');
	pg_assert_true($cut, 'and the sheet says it was cut');
}

function test_office_csv_records_limits()
{
	$text = str_repeat("x,y\n", PG_OFFICE_MAX_ROWS + 5);
	$rows = pg_office_csv_records($text, ',', true, $cut);
	pg_assert_same(PG_OFFICE_MAX_ROWS, count($rows), 'the rows a sheet shows');
	pg_assert_true($cut, 'more rows: cut');

	$rows = pg_office_csv_records(str_repeat("x,y\n", PG_OFFICE_MAX_ROWS), ',', true, $cut);
	pg_assert_same(PG_OFFICE_MAX_ROWS, count($rows), 'exactly the limit');
	pg_assert_false($cut, 'is not cut');

	$wide = implode(',', range(1, PG_OFFICE_MAX_COLUMNS + 10)) . "\n";
	$rows = pg_office_csv_records($wide, ',', true, $cut);
	pg_assert_same(PG_OFFICE_MAX_COLUMNS, count($rows[0]['cells']), 'the columns a sheet shows');
	pg_assert_same((string) PG_OFFICE_MAX_COLUMNS, $rows[0]['cells'][PG_OFFICE_MAX_COLUMNS - 1], 'the first ones');
	pg_assert_true($cut, 'more columns: cut');

	$full = implode(',', range(1, PG_OFFICE_MAX_COLUMNS)) . "\n";
	$rows = pg_office_csv_records(str_repeat($full, 700), ',', true, $cut);
	pg_assert_same((int) (PG_OFFICE_MAX_CELLS / PG_OFFICE_MAX_COLUMNS), count($rows), 'the cells a workbook shows');
	pg_assert_true($cut, 'more cells: cut');
}

function test_office_csv_read_detects_and_rereads()
{
	$path = office_csv_test_file("\xDCr\xFCn;Fiyat\r\nElma;1,50\r\n\xDEeker;2,75\r\n");
	$book = pg_office_read($path, 'fiyatlar.csv');

	pg_assert_true($book['ok'], 'read');
	pg_assert_same('csv', $book['kind'], 'as a sheet');
	pg_assert_same(array('delimiter' => 'semicolon', 'encoding' => 'windows-1254'), $book['csv'], 'what was guessed');
	pg_assert_same(1, count($book['sheets']), 'one sheet');
	pg_assert_same('fiyatlar', $book['sheets'][0]['name'], 'named after the file, as a spreadsheet program names it');
	pg_assert_same(array("\xC3\x9Cr\xC3\xBCn", 'Fiyat'), $book['sheets'][0]['rows'][0]['cells'], 'the heading in UTF-8');
	pg_assert_same(array("\xC5\x9Eeker", '2,75'), $book['sheets'][0]['rows'][2]['cells'], 'a decimal comma stays in its cell');
	pg_assert_false($book['truncated'], 'nothing cut');

	$again = pg_office_read($path, 'fiyatlar.csv', false, array('delimiter' => 'comma', 'encoding' => 'utf-8'));
	pg_assert_same(array('delimiter' => 'comma', 'encoding' => 'utf-8'), $again['csv'], 'the choice is what is read');
	pg_assert_same(array("\xEF\xBF\xBDr\xEF\xBF\xBDn;Fiyat"), $again['sheets'][0]['rows'][0]['cells'], 'read the chosen way, however wrong');
	pg_assert_same(array('Elma;1', '50'), $again['sheets'][0]['rows'][1]['cells'], 'split at the comma');

	$odd = pg_office_read($path, 'fiyatlar.csv', false, array('delimiter' => '|', 'encoding' => 'latin-5'));
	pg_assert_same(array('delimiter' => 'semicolon', 'encoding' => 'windows-1254'), $odd['csv'], 'a choice not offered is guessed instead');

	$edit = pg_office_read($path, 'fiyatlar.csv', true);
	pg_assert_false(isset($edit['version']), 'asked for editing: no version to save against');
	pg_assert_false(isset($edit['sheets'][0]['edit']), 'and no cell is offered');

	@unlink($path);

	$path = office_csv_test_file("\xEF\xBB\xBF\"Ad\"\tSoyad\n\"Ay\xC5\x9Fe\"\tDemir\n");
	$book = pg_office_read($path, 'liste.tsv');
	pg_assert_same(array('delimiter' => 'tab', 'encoding' => 'utf-8'), $book['csv'], 'a .tsv with a mark');
	pg_assert_same(array('Ad', 'Soyad'), $book['sheets'][0]['rows'][0]['cells'], 'the mark is not in the first cell, the quotes are gone');
	@unlink($path);

	$missing = pg_office_read(sys_get_temp_dir() . '/pg_office_csv_missing_' . bin2hex(random_bytes(4)) . '.csv', 'yok.csv');
	pg_assert_false($missing['ok'], 'a file that is not there');
	pg_assert_same(lang('The file could not be read.'), $missing['error'], 'says so');
}

function test_office_csv_read_stops_at_its_byte_limit()
{
	$cell = str_repeat('a', 3 * 1024 * 1024);
	$path = office_csv_test_file($cell . ",1\n" . $cell . ",2\n" . $cell . ",3\n");
	$book = pg_office_read($path, 'buyuk.csv');
	@unlink($path);

	pg_assert_true($book['ok'], 'a file past the limit is still shown');
	pg_assert_same(2, count($book['sheets'][0]['rows']), 'the whole records within the limit');
	pg_assert_same('2', $book['sheets'][0]['rows'][1]['cells'][1], 'read to their end');
	pg_assert_true($book['truncated'], 'cut, and it says so');
	pg_assert_true($book['sheets'][0]['truncated'], 'on the sheet too');
}

function test_office_csv_doors_offer_the_preview()
{
	$media = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_files_media.php');
	$office = substr($media, strpos($media, 'function pg_explorer_handle_office_preview('));
	pg_assert_contains('pg_office_preview_kind($office_file[\'name\'])', $office, 'the file manager previews a .csv');
	pg_assert_contains('pg_office_csv_choice($request)', $office, 'and reads it again the chosen way');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$door = substr($actions, strpos($actions, "case 'ws_file_office':"), 2000);
	pg_assert_contains('pg_office_preview_kind($message[\'file_name\'])', $door, 'a channel previews a .csv');
	pg_assert_contains('pg_office_csv_choice($request)', $door, 'and reads it again the chosen way');

	$messages = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
	pg_assert_contains("'office'   => function_exists('pg_office_preview_kind') && (pg_office_preview_kind(\$row['file_name']) !== '')", $messages, 'its file card offers Preview');

	pg_assert_same(array('delimiter' => 'tab', 'encoding' => ''), pg_office_csv_choice(array('delimiter' => 'tab', 'edit' => 1)), 'the choice is read from the request');
	pg_assert_same(array('delimiter' => '', 'encoding' => ''), pg_office_csv_choice(array('delimiter' => array('x'))), 'never an array');

	$view = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');
	pg_assert_contains("office.kind === 'csv'", $view, 'the view draws a .csv');
	pg_assert_contains('options.reread(', $view, 'and asks its window to read it again');

	$folders = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
	pg_assert_contains("var OFFICE_SHEET_TYPES = ['csv', 'tsv'];", $folders, 'Quick Look lists the two kinds');
}
