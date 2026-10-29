<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for bringing a file into a workflow table
 * (includes/workspace/wtable_import.php): header names, numbers, amounts,
 * dates and ticks as written in a file, the type guessed per column, the
 * columns matched by name, each value as the store's cell check takes it,
 * and the CSV reader with its row ceiling.
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
