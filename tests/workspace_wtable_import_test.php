<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for bringing a file into a workflow table
 * (includes/workspace/wtable_import.php): header names, numbers, amounts,
 * dates and ticks as written in a file, the type guessed per column, the
 * columns matched by name, each value as the store's cell check takes it,
 * the CSV reader with its row ceiling, and a note's tables read as a file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/blocks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_import.php');

function test_workspace_wtable_import_names()
{
	$names = ws_wtable_import_names(array('Name', ' name ', '', 'Price', 'Name'));

	pg_assert_same('Name', $names[0]);
	pg_assert_same('name 2', $names[1], 'a name met before, case aside, gets a number');
	pg_assert_same(lang(array('string' => 'Column {var:1}', 'vars' => 3)), $names[2], 'an empty name');
	pg_assert_same('Price', $names[3]);
	pg_assert_same('Name 3', $names[4]);
	pg_assert_true(mb_strlen(ws_wtable_import_names(array(str_repeat('é', 300)))[0]) <= ws_wtable_limits()['name'], 'cut to the name length');
}

function test_workspace_wtable_import_number()
{
	pg_assert_same('1234.5', ws_wtable_import_number('1234.5'));
	pg_assert_same('12.5', ws_wtable_import_number('12,5'), 'a decimal comma');
	pg_assert_same('1234.56', ws_wtable_import_number('1.234,56'), 'dots separating thousands');
	pg_assert_same('1234.56', ws_wtable_import_number('1,234.56'), 'commas separating thousands');
	pg_assert_same('1234567', ws_wtable_import_number('1.234.567'), 'the same mark twice is thousands');
	pg_assert_same('-3', ws_wtable_import_number(' -3 '));
	pg_assert_same('1500', ws_wtable_import_number('1 500'), 'a space between thousands');
	pg_assert_same(null, ws_wtable_import_number('12a'));
	pg_assert_same(null, ws_wtable_import_number('.'));
	pg_assert_same(null, ws_wtable_import_number(''));
}

// Runs $test with the site writing decimals with a comma (Turkish) or a dot,
// then puts the site's own way back.
function ws_wtable_import_test_in($comma, $test)
{
	$own = ws_calc_decimal_comma();
	ws_calc_decimal_comma($comma);

	try {
		$test();
	} finally {
		ws_calc_decimal_comma($own);
	}
}

function test_workspace_wtable_import_number_site()
{
	ws_wtable_import_test_in(true, function () {
		pg_assert_same('1250', ws_wtable_import_number('1.250'), 'Turkish: a dot before three digits groups thousands');
		pg_assert_same('1.25', ws_wtable_import_number('1,25'));
		pg_assert_same('1.250', ws_wtable_import_number('1,250'), 'Turkish: a comma is the decimal, three digits or not');
		pg_assert_same('1.25', ws_wtable_import_number('1.25'), 'a dot not before three digits is the decimal');
		pg_assert_same('1250.50', ws_wtable_import_number('1.250,50'));
		pg_assert_same('1250.50', ws_wtable_import_number('1,250.50'));
		pg_assert_same('0.250', ws_wtable_import_number('0.250'), 'a lone nought is no thousands group');
		pg_assert_same('1234.567', ws_wtable_import_number('1234.567'), 'four digits before the mark are no group');
		pg_assert_same(array('amount' => '1250', 'currency' => 'TRY'), ws_wtable_import_money('1.250 ₺'));
		pg_assert_same(array('amount' => '1250', 'currency' => 'TRY'), ws_wtable_import_money('1.250 TL'));
		pg_assert_same(125000, ws_wtable_import_cell(array('type' => 'money', 'options' => array('decimals' => 2)), '₺1.250 '), 'money in minor units');
		pg_assert_same('1250', ws_wtable_import_cell(array('type' => 'number', 'options' => array()), '1.250'));
		pg_assert_same('1,250 kg', ws_wtable_import_cell(array('type' => 'number', 'options' => array()), '1,250 kg'), 'not a number: handed over as written');
		pg_assert_same(array('type' => 'number', 'options' => array('decimals' => 0)), ws_wtable_import_guess(array('1.250', '2.500', '3')), 'the guess is still a number');
		pg_assert_same(array('type' => 'money', 'options' => array('decimals' => 2, 'currency' => 'TRY')), ws_wtable_import_guess(array('1.250 ₺', '12,50 TL')));
		pg_assert_same('text', ws_wtable_import_guess(array('1.250', 'yok'))['type'], 'a cell that is no number keeps the column text');
	});

	ws_wtable_import_test_in(false, function () {
		pg_assert_same('1250', ws_wtable_import_number('1,250'), 'English: a comma before three digits groups thousands');
		pg_assert_same('1.25', ws_wtable_import_number('1.25'));
		pg_assert_same('1.250', ws_wtable_import_number('1.250'), 'English: a dot is the decimal, three digits or not');
		pg_assert_same('1.25', ws_wtable_import_number('1,25'), 'a comma not before three digits is the decimal');
		pg_assert_same('1250.50', ws_wtable_import_number('1,250.50'));
		pg_assert_same('1250.50', ws_wtable_import_number('1.250,50'));
		pg_assert_same(array('amount' => '1250', 'currency' => 'USD'), ws_wtable_import_money('$1,250'));
		pg_assert_same(array('amount' => '1250', 'currency' => 'EUR'), ws_wtable_import_money('EUR 1,250'));
		pg_assert_same(125000, ws_wtable_import_cell(array('type' => 'money', 'options' => array('decimals' => 2)), '1,250 USD'));
		pg_assert_same(array('type' => 'number', 'options' => array('decimals' => 0)), ws_wtable_import_guess(array('1,250', '2,500', '3')));
		pg_assert_same('text', ws_wtable_import_guess(array('1,250', 'n/a'))['type']);
	});
}

function test_workspace_wtable_import_money()
{
	pg_assert_same(array('amount' => '12.50', 'currency' => 'TRY'), ws_wtable_import_money('12,50 ₺'));
	pg_assert_same(array('amount' => '12.50', 'currency' => 'TRY'), ws_wtable_import_money('12,50 TL'));
	pg_assert_same(array('amount' => '9.99', 'currency' => 'USD'), ws_wtable_import_money('$9.99'));
	pg_assert_same(array('amount' => '1250.00', 'currency' => 'EUR'), ws_wtable_import_money('EUR 1.250,00'));
	pg_assert_same(array('amount' => '7', 'currency' => 'GBP'), ws_wtable_import_money('7 GBP'));
	pg_assert_same(array('amount' => '7', 'currency' => ''), ws_wtable_import_money('7'), 'a bare number is an amount without a currency');
	pg_assert_same(null, ws_wtable_import_money('ABC'));
	pg_assert_same(null, ws_wtable_import_money('twelve'));
}

function test_workspace_wtable_import_date()
{
	pg_assert_same(array('date' => '2026-10-29', 'time' => ''), ws_wtable_import_date('2026-10-29'));
	pg_assert_same(array('date' => '2026-10-29', 'time' => ''), ws_wtable_import_date('29.10.2026'), 'day first');
	pg_assert_same(array('date' => '2026-03-04', 'time' => ''), ws_wtable_import_date('4/3/2026'), 'day first with slashes');
	pg_assert_same(array('date' => '2026-10-29', 'time' => '09:05'), ws_wtable_import_date('2026-10-29 9:05:30'), 'a spreadsheet time');
	pg_assert_same(null, ws_wtable_import_date('31.02.2026'), 'no such day');
	pg_assert_same(null, ws_wtable_import_date('2026-10-29 25:00'));
	pg_assert_same(null, ws_wtable_import_date('2026'));
}

function test_workspace_wtable_import_checkbox()
{
	pg_assert_same(true, ws_wtable_import_checkbox('Evet'));
	pg_assert_same(false, ws_wtable_import_checkbox('HAYIR'), 'capital I lowers to a dotted i');
	pg_assert_same(false, ws_wtable_import_checkbox('hayır'));
	pg_assert_same(true, ws_wtable_import_checkbox('x'));
	pg_assert_same(true, ws_wtable_import_checkbox('1'));
	pg_assert_same(null, ws_wtable_import_checkbox('1', false), 'digits only when asked');
	pg_assert_same(null, ws_wtable_import_checkbox('maybe'));
}

function test_workspace_wtable_import_guess()
{
	pg_assert_same(array('type' => 'text', 'options' => array()), ws_wtable_import_guess(array('', ' ')), 'an empty column is text');
	pg_assert_same('checkbox', ws_wtable_import_guess(array('yes', 'no', '', 'Yes'))['type']);
	pg_assert_same('number', ws_wtable_import_guess(array('1', '2', '3'))['type'], 'ones and noughts alone are numbers');
	pg_assert_same(array('type' => 'number', 'options' => array('decimals' => 2)), ws_wtable_import_guess(array('1', '2,5', '3.25')));
	pg_assert_same(array('type' => 'date', 'options' => array('time' => false)), ws_wtable_import_guess(array('2026-10-29', '30.10.2026')));
	pg_assert_same(array('time' => true), ws_wtable_import_guess(array('2026-10-29 10:30', '2026-10-30'))['options']);
	pg_assert_same(array('type' => 'money', 'options' => array('decimals' => 2, 'currency' => 'EUR')), ws_wtable_import_guess(array('€12', '13,50', 'EUR 4')));
	pg_assert_same('number', ws_wtable_import_guess(array('12', '13,50'))['type'], 'no currency named: a number');

	$status = ws_wtable_import_guess(array('Open', 'Done', 'open', 'Open', 'Done', ''));
	pg_assert_same('status', $status['type']);
	pg_assert_same(array('Open', 'Done'), array_column($status['options']['choices'], 'label'), 'each label once, case aside, in the order met');
	pg_assert_true(ws_wtable_column_options_check('status', $status['options'])['ok'], 'the store takes the choices');

	pg_assert_same('text', ws_wtable_import_guess(array('Ada', 'Bob', 'Cem', 'Dan'))['type'], 'labels all different are text');
	pg_assert_same('text', ws_wtable_import_guess(array('Open', 'Open', 'Done'))['type'], 'too few values for a status');
	pg_assert_same('text', ws_wtable_import_guess(array('12', 'twelve'))['type']);
}

function test_workspace_wtable_import_match()
{
	$columns = array(
		7 => array('name' => 'Name', 'type' => 'text'),
		8 => array('name' => 'Unit  price', 'type' => 'money'),
		9 => array('name' => 'Total', 'type' => 'formula'),
	);

	$match = ws_wtable_import_match(array(' NAME', 'unit price', 'Total', 'Notes', 'name'), $columns);

	pg_assert_same(array(0 => 7, 1 => 8), $match['map'], 'by name, case and spaces aside');
	pg_assert_same(array('Total', 'Notes', 'name'), $match['unmatched'], 'a formula column takes nothing, a column one file column');
}

function test_workspace_wtable_import_cell()
{
	$money = array('type' => 'money', 'options' => array('decimals' => 2, 'currency' => 'TRY'));
	$status = array('type' => 'status', 'options' => array('choices' => array(array('key' => 's1', 'label' => 'Open', 'color' => ''))));

	pg_assert_same(null, ws_wtable_import_cell(array('type' => 'text'), '  '), 'empty empties the cell');
	pg_assert_same('a, b', ws_wtable_import_cell(array('type' => 'text'), 'a, b'));
	pg_assert_same('1234.5', ws_wtable_import_cell(array('type' => 'number'), '1.234,5'));
	pg_assert_same('n/a', ws_wtable_import_cell(array('type' => 'number'), 'n/a'), 'unread values go as written');
	pg_assert_same(1250, ws_wtable_import_cell($money, '12,50 ₺'));
	pg_assert_same(29, ws_wtable_import_cell($money, '0.29'), 'rounded, not cut to 28');
	pg_assert_same('2026-10-29', ws_wtable_import_cell(array('type' => 'date', 'options' => array('time' => false)), '29.10.2026'));
	pg_assert_same('2026-10-29', ws_wtable_import_cell(array('type' => 'date', 'options' => array('time' => false)), '2026-10-29 00:00:00'), 'midnight is a date');
	pg_assert_same('2026-10-29 14:30', ws_wtable_import_cell(array('type' => 'date', 'options' => array('time' => true)), '29/10/2026 14:30'));
	pg_assert_same('2026-10-29 00:00', ws_wtable_import_cell(array('type' => 'date', 'options' => array('time' => true)), '2026-10-29'));
	pg_assert_same('s1', ws_wtable_import_cell($status, 'OPEN'), 'a status by its label');
	pg_assert_same('Closed', ws_wtable_import_cell($status, 'Closed'));
	pg_assert_same(true, ws_wtable_import_cell(array('type' => 'checkbox'), 'Evet'));
	pg_assert_same(5, ws_wtable_import_cell(array('type' => 'person'), 'Ada Lovelace', array('ada lovelace' => 5)));
	pg_assert_same(array('kind' => 'contact', 'id' => 12), ws_wtable_import_cell(array('type' => 'link', 'options' => array()), 'contact:12'));

	// Every converted value passes the store's own check.
	pg_assert_true(ws_wtable_cell_check($money, ws_wtable_import_cell($money, '€ 1.250,75'))['ok']);
	pg_assert_true(ws_wtable_cell_check($status, ws_wtable_import_cell($status, 'open'))['ok']);
}

function test_workspace_wtable_import_row_cells()
{
	$columns = array(3 => array('type' => 'text'), 4 => array('type' => 'number', 'options' => array('decimals' => 0)));

	pg_assert_same(array(3 => 'Ada', 4 => '7'), ws_wtable_import_row_cells(array('x', 'Ada', '7'), array(1 => 3, 2 => 4), $columns));
	pg_assert_same(array(3 => null), ws_wtable_import_row_cells(array('x'), array(1 => 3, 5 => 99), $columns), 'a short row and a gone column');
}

function test_workspace_wtable_import_file_table()
{
	$path = tempnam(sys_get_temp_dir(), 'pg_test_');
	file_put_contents($path, "\xEF\xBB\xBFName;Price\n\nAda;12,5\n\"B;ob\";3\nCem;4\n");

	$read = ws_wtable_import_file_table($path, 'list.csv', 3);

	pg_assert_true($read['ok']);
	pg_assert_same(array(array('Name', 'Price'), array('Ada', '12,5'), array('B;ob', '3')), $read['table'], 'separator from the first line, BOM and blank lines dropped');
	pg_assert_true($read['more'], 'a longer file is told');
	pg_assert_true(!ws_wtable_import_file_table($path, 'list.csv', 10)['more']);
	pg_assert_true(!ws_wtable_import_file_table($path, 'list.txt', 10)['ok'], 'only CSV and spreadsheets');

	file_put_contents($path, "\n\n");
	pg_assert_true(!ws_wtable_import_file_table($path, 'list.csv', 10)['ok'], 'an empty file');

	unlink($path);
}

function test_workspace_wtable_import_note_tables()
{
	$body = "Intro line\n::: Leads\n| Name | Stage |\n|---|:-:|\n| Ada | Lead |\n| Can | Won | extra |\n\n```\n| In | Code |\n|---|---|\n| x | y |\n```\n| A |\n|---|---|\n| 1 |\nnot a row\n| 2 |";
	$tables = ws_wtable_import_note_tables($body);

	pg_assert_same(2, count($tables), 'a table inside a code fence is code');
	pg_assert_same('Leads', $tables[0]['title'], 'the ::: line above names it');
	pg_assert_same(2, $tables[0]['line']);
	pg_assert_same(array(array('Name', 'Stage'), array('Ada', 'Lead'), array('Can', 'Won')), $tables[0]['table'], 'cut to the drawn column count');
	pg_assert_same('', $tables[1]['title']);
	pg_assert_same(array(array('A', ''), array('1', '')), $tables[1]['table'], 'padded to the row of dashes; ends at a line that is no row');

	$cut = ws_wtable_import_note_tables("| N |\n|---|\n| 1 |\n| 2 |\n| 3 |", 3);
	pg_assert_same(3, count($cut[0]['table']));
	pg_assert_true($cut[0]['more'], 'a longer table is told');
	pg_assert_same(array(), ws_wtable_import_note_tables("| only | a row |\nno dashes"), 'no row of dashes, no table');
}

function test_workspace_wtable_import_note_cell()
{
	pg_assert_same('Ada Yılmaz', ws_wtable_import_note_cell('<@user:12>', array(12 => 'Ada Yılmaz')), 'a person by name');
	pg_assert_same('13', ws_wtable_import_note_cell('<@user:13>', array()), 'an unknown person by id');
	pg_assert_same('', ws_wtable_import_note_cell('<@dept:4>'), 'a department is no person');
	pg_assert_same('order:1045', ws_wtable_import_note_cell('<#order:1045>'), 'a record as kind:id');
	pg_assert_same('Site', ws_wtable_import_note_cell('[Site](https://example.com)'));
	pg_assert_same('Done', ws_wtable_import_note_cell(' **_Done_** '), 'marks around the whole cell');
	pg_assert_same('a **b** c', ws_wtable_import_note_cell('a **b** c'), 'marks inside stay');
	pg_assert_same('12,50', ws_wtable_import_note_cell('`12,50`'));
}

function test_workspace_wtable_import_note_rows()
{
	$rows = ws_wtable_import_note_rows(array(array('**Item**', 'Price'), array('Cup', '=2*3'), array('<@user:5>', '=1/0')), array(5 => 'Can'));

	pg_assert_same(array('Item', 'Price'), $rows[0]);
	pg_assert_same(array('Cup', '6'), $rows[1], 'a formula as its value');
	pg_assert_same(array('Can', ''), $rows[2], 'a formula error is empty');
}

function test_workspace_wtable_import_note_choices_from()
{
	// Rows as ws_notes_list() gives them: an excerpt, no body.
	$notes = array(
		array('id' => 7, 'title' => 'Fruit', 'name' => 'Fruit', 'excerpt' => 'Ürün Adet Fiyat', 'access' => 'owner'),
		array('id' => 8, 'title' => 'Plain', 'name' => 'Plain', 'excerpt' => 'No table here', 'access' => 'owner'),
		array('id' => 9, 'title' => 'Shared', 'name' => 'Shared', 'excerpt' => '…', 'access' => 'view'),
	);
	$bodies = array(
		7 => "| Ürün | Adet | Fiyat |\n|---|---|---|\n| Elma | 3 | 12,50 |\n| Armut | 1 | 20 |",
		8 => 'a | b without a row of dashes',
	);

	$choices = ws_wtable_import_note_choices_from($notes, $bodies);

	pg_assert_same(array(array('note' => 7, 'block' => 0, 'label' => 'Fruit — Ürün, Adet, Fiyat')), $choices, 'a table from the body; a note without one or without a body is left out');
	pg_assert_same(array(), ws_wtable_import_note_choices_from($notes, array()), 'the excerpt is never read');
}

function test_workspace_wtable_import_temp_name()
{
	pg_assert_true(ws_wtable_import_temp_name('/tmp/pg_wtimport_Ab12Cd'), 'the whole prefix');
	pg_assert_true(ws_wtable_import_temp_name('C:\\Windows\\Temp\\pg_1A2B.tmp'), 'Windows: three prefix characters, backslashes');
	pg_assert_true(ws_wtable_import_temp_name('C:\\Windows\\Temp\\pg_F.TMP'));
	pg_assert_true(!ws_wtable_import_temp_name('C:\\Windows\\Temp\\other.tmp'), 'another file');
	pg_assert_true(!ws_wtable_import_temp_name('C:\\pg_wtimport_x\\secret.txt'), 'the prefix in a folder name only');
	pg_assert_true(!ws_wtable_import_temp_name('/tmp/pg_session.json'));
	pg_assert_true(!ws_wtable_import_temp_name(''));
}

function test_workspace_wtable_import_kept_path()
{
	$path = tempnam(sys_get_temp_dir(), 'pg_wtimport_');
	$real = realpath($path);

	pg_assert_same($real, ws_wtable_import_kept_path($path), 'a file tempnam() wrote in the temp folder');
	pg_assert_same('', ws_wtable_import_kept_path(__FILE__), 'a file elsewhere');
	@unlink($path);
	pg_assert_same('', ws_wtable_import_kept_path($path), 'a file no longer there');
}
