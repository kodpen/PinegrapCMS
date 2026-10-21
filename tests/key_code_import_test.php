<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reading an imported key code file (import_key_codes.php,
 * includes/fn/ecommerce.php): the header names, the separator, the yes/no
 * and date cells, and a row read into the columns it writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_key_code_import_column()
{
	pg_assert_same('code', pg_key_code_import_column('key_code'), 'export heading');
	pg_assert_same('code', pg_key_code_import_column("\xEF\xBB\xBFkey_code"), 'byte order mark on the first cell');
	pg_assert_same('code', pg_key_code_import_column(' Key Code '), 'case and spaces');
	pg_assert_same('offer_code', pg_key_code_import_column('Offer-Code'), 'dash');
	pg_assert_same('expiration_date', pg_key_code_import_column('expiration_date'), 'date');
	pg_assert_same('single_use', pg_key_code_import_column('SINGLE_USE'), 'flag');
	pg_assert_same('report', pg_key_code_import_column('report'), 'report');
	pg_assert_same('', pg_key_code_import_column('last_modified'), 'a column the import does not write');
	pg_assert_same('', pg_key_code_import_column('id'), 'no id');
}

function test_key_code_import_separator()
{
	pg_assert_same(',', pg_key_code_import_separator('"key_code","offer_code","enabled"'), 'the export');
	pg_assert_same(';', pg_key_code_import_separator('key_code;offer_code;enabled'), 'Excel in Turkish');
	pg_assert_same("\t", pg_key_code_import_separator("key_code\toffer_code"), 'tab');
	pg_assert_same(',', pg_key_code_import_separator('key_code'), 'one column');
}

function test_key_code_import_flag()
{
	pg_assert_same(1, pg_key_code_import_flag('1'), '1');
	pg_assert_same(0, pg_key_code_import_flag('0'), '0');
	pg_assert_same(1, pg_key_code_import_flag('TRUE'), 'Excel TRUE');
	pg_assert_same(0, pg_key_code_import_flag('FALSE'), 'Excel FALSE');
	pg_assert_same(1, pg_key_code_import_flag('DOĞRU'), 'Excel TRUE in Turkish');
	pg_assert_same(0, pg_key_code_import_flag('YANLIŞ'), 'Excel FALSE in Turkish');
	pg_assert_same(1, pg_key_code_import_flag(' Yes '), 'yes');
	pg_assert_same(0, pg_key_code_import_flag('Hayır'), 'hayır');
	pg_assert_same(0, pg_key_code_import_flag('HAYIR'), 'HAYIR');
	pg_assert_same(null, pg_key_code_import_flag(''), 'empty');
	pg_assert_same(false, pg_key_code_import_flag('2'), 'not a flag');
	pg_assert_same(false, pg_key_code_import_flag('maybe'), 'not a flag either');
}

function test_key_code_import_date()
{
	pg_assert_same('2026-12-31', pg_key_code_import_date('2026-12-31', 'day_month'), 'the export');
	pg_assert_same('2026-01-05', pg_key_code_import_date('2026-1-5', 'month_day'), 'ISO without zeros');
	pg_assert_same('0000-00-00', pg_key_code_import_date('', 'day_month'), 'empty: does not expire');
	pg_assert_same('0000-00-00', pg_key_code_import_date('0000-00-00', 'day_month'), 'zero date');
	pg_assert_same('2026-12-31', pg_key_code_import_date('31.12.2026', 'month_day'), 'dots are day first');
	pg_assert_same(false, pg_key_code_import_date('12.31.2026', 'month_day'), 'dots never month first');
	pg_assert_same('2026-06-05', pg_key_code_import_date('05/06/2026', 'day_month'), 'site order, day first');
	pg_assert_same('2026-05-06', pg_key_code_import_date('05/06/2026', 'month_day'), 'site order, month first');
	pg_assert_same('2026-12-31', pg_key_code_import_date('12/31/2026', 'day_month'), 'the other order when the site order is not a date');
	pg_assert_same('2026-12-31', pg_key_code_import_date('31-12-2026', 'month_day'), 'dashes, the other order');
	pg_assert_same(false, pg_key_code_import_date('2026-02-30', 'day_month'), 'no such day');
	pg_assert_same(false, pg_key_code_import_date('31/12/26', 'day_month'), 'two-digit year');
	pg_assert_same(false, pg_key_code_import_date('31.12.2026 00:00', 'day_month'), 'a time after the date');
	pg_assert_same(false, pg_key_code_import_date('soon', 'day_month'), 'not a date');
}

function test_key_code_import_row()
{
	$columns = array(0 => 'code', 1 => 'offer_code', 2 => 'enabled', 3 => 'expiration_date', 4 => 'notes', 5 => 'single_use', 6 => 'report');

	$read = pg_key_code_import_row(array(' ABC ', 'OFFER', '0', '', '', '1', 'offer_code'), $columns, 'day_month');
	pg_assert_same('', $read['error'], 'an exported row reads');
	pg_assert_same(array('code' => 'ABC', 'offer_code' => 'OFFER', 'enabled' => 0, 'expiration_date' => '0000-00-00', 'notes' => '', 'single_use' => 1, 'report' => 'offer_code'), $read['values'], 'every column of the export');

	// A file of codes alone writes the code only: a code already there keeps
	// its offer code, its enabled flag and the rest.
	$read = pg_key_code_import_row(array('ABC'), array(0 => 'code'), 'day_month');
	pg_assert_same(array('code' => 'ABC'), $read['values'], 'codes alone');

	// Empty flags, offer code and report are left out; a short row is read
	// as empty cells.
	$read = pg_key_code_import_row(array('ABC', '', ''), $columns, 'day_month');
	pg_assert_same('', $read['error'], 'a short row');
	pg_assert_same(array('code' => 'ABC', 'expiration_date' => '0000-00-00', 'notes' => ''), $read['values'], 'empty cells');

	$read = pg_key_code_import_row(array('ABC', 'OFFER', 'TRUE', '31.12.2026', 'n', 'FALSE', 'Key Code'), $columns, 'day_month');
	pg_assert_same('', $read['error'], 'Excel wrote it back');
	pg_assert_same(array('code' => 'ABC', 'offer_code' => 'OFFER', 'enabled' => 1, 'expiration_date' => '2026-12-31', 'notes' => 'n', 'single_use' => 0, 'report' => 'key_code'), $read['values'], 'Excel cells');

	$read = pg_key_code_import_row(array('ABC', 'OFFER', '1', '31/13/2026'), $columns, 'day_month');
	pg_assert_contains('31/13/2026', $read['error'], 'a date that is not one names the cell');

	$read = pg_key_code_import_row(array('ABC', 'OFFER', 'sometimes'), $columns, 'day_month');
	pg_assert_contains('sometimes', $read['error'], 'a flag that is not one names the cell');

	$read = pg_key_code_import_row(array('ABC', 'OFFER', '1', '', '', '1', 'both'), $columns, 'day_month');
	pg_assert_contains('both', $read['error'], 'an unknown report');

	$read = pg_key_code_import_row(array(str_repeat('K', 51), 'OFFER'), $columns, 'day_month');
	pg_assert_contains('50', $read['error'], 'a code longer than the column');
	pg_assert_same(str_repeat('K', 51), $read['values']['code'], 'a code longer than the column is kept, so the row is reported, not taken for a blank line');

	$read = pg_key_code_import_row(array('ABC', str_repeat('O', 51)), $columns, 'day_month');
	pg_assert_contains('50', $read['error'], 'an offer code longer than the column');
	pg_assert_same(false, isset($read['values']['offer_code']), 'an offer code too long to store is left out');

	$read = pg_key_code_import_row(array(str_repeat('ğ', 50), 'OFFER'), $columns, 'day_month');
	pg_assert_same('', $read['error'], '50 letters fit, whatever their bytes');
}
