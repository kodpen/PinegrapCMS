<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Currencies list quick edit
 * (includes/fn/currency_list_edit.php): every value the currency screen
 * changes is a field, checked with the screen's own rules; the base
 * currency's rate stays text, and a base move redraws the other rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/currency_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/currency_list_edit.php');

foreach (array('DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $currency_edit_test_name => $currency_edit_test_value) {
	if (!defined($currency_edit_test_name)) {
		define($currency_edit_test_name, $currency_edit_test_value);
	}
}

function pg_test_currency_quick_row(array $over = array())
{
	return $over + array(
		'id' => 4, 'name' => 'Euro', 'base' => '0', 'code' => 'EUR', 'symbol' => '&euro;', 'exchange_rate' => '0.92000',
		'created_timestamp' => '1700000000', 'created_username' => 'admin',
		'last_modified_timestamp' => '1700000500', 'last_modified_username' => 'admin',
	);
}

// Every value the currency screen changes is an editable column with a
// cell field; Created and Last Modified stay text. A new column fails here
// until it is one or the other.
function test_currency_quick_every_screen_value_is_a_field()
{
	$text = array('created', 'last_modified');
	$fields = pg_currency_edit_columns();
	$cells = pg_currency_list_cells(pg_test_currency_quick_row(), $fields, 'USD');

	foreach (pg_currency_list_columns(true) as $key => $column) {
		if (in_array($key, $text, true)) {
			pg_assert_false(isset($column['editable']), $key . ' is text');
			pg_assert_false(isset($cells[$key]['attributes']['data-pg-edit']), $key . ' cell is text');
			continue;
		}

		pg_assert_true(isset($column['editable']), $key . ' is editable');
		pg_assert_true(isset($fields[$key]), $key . ' has a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell is a field');
	}

	foreach (pg_currency_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}
}

// The fields open with the stored values: the symbol as its HTML, the rate
// with the screen's five places, the base as 1/0.
function test_currency_quick_values()
{
	$row = pg_test_currency_quick_row();

	pg_assert_same('Euro', pg_currency_quick_value($row, 'name'));
	pg_assert_same('&euro;', pg_currency_quick_value($row, 'symbol'));
	pg_assert_same('0', pg_currency_quick_value($row, 'base'));
	pg_assert_same(pg_format_input_amount('0.92000', 5), pg_currency_quick_value($row, 'exchange_rate'));
}

// The base currency's rate is always 1: its cell is text and a posted rate
// is refused.
function test_currency_quick_base_rate_locked()
{
	$base = pg_test_currency_quick_row(array('id' => 1, 'base' => '1', 'code' => 'USD', 'exchange_rate' => '1.00000'));
	$cells = pg_currency_list_cells($base, pg_currency_edit_columns(), 'USD');

	pg_assert_false(isset($cells['exchange_rate']['attributes']['data-pg-edit']), 'the base rate is text');
	pg_assert_same('base', $cells['base']['attributes']['data-pg-edit'] ?? '', 'the base switch is a field');
	pg_assert_false(pg_currency_quick_change($base, 'exchange_rate', '2')['ok'], 'a base rate is refused');
	pg_assert_false(strpos($cells['exchange_rate']['html'], 'fg-secondary') !== false, 'no reverse rate for the base');
}

// The checks are the screen's: name and code required, the code three
// Latin letters in capitals, the base not switched off, a rate above zero
// and within the column.
function test_currency_quick_change_checks()
{
	$row = pg_test_currency_quick_row();
	$base = pg_test_currency_quick_row(array('base' => '1'));

	pg_assert_false(pg_currency_quick_change($row, 'name', '')['ok'], 'empty name');
	pg_assert_same('Euro coin', pg_currency_quick_change($row, 'name', 'Euro coin')['values']['name']);

	pg_assert_false(pg_currency_quick_change($row, 'code', '')['ok'], 'empty code');
	pg_assert_false(pg_currency_quick_change($row, 'code', 'E1R')['ok'], 'digit in the code');
	pg_assert_false(pg_currency_quick_change($row, 'code', 'EU')['ok'], 'two letters');
	pg_assert_same('GBP', pg_currency_quick_change($row, 'code', ' gbp ')['values']['code'], 'trimmed and in capitals');

	pg_assert_same('', pg_currency_quick_change($row, 'symbol', '')['values']['symbol'], 'the symbol may be empty');

	pg_assert_false(pg_currency_quick_change($base, 'base', '0')['ok'], 'the base cannot be switched off');

	$made_base = pg_currency_quick_change($row, 'base', '1');
	pg_assert_true($made_base['ok'], 'another currency can become the base');
	pg_assert_same(1, $made_base['values']['base']);
	pg_assert_same('1', $made_base['values']['exchange_rate'], 'a new base gets the rate 1');

	pg_assert_same(0, pg_currency_quick_change($row, 'base', '0')['values']['base'], 'a non-base stays non-base');

	pg_assert_false(pg_currency_quick_change($row, 'exchange_rate', '0')['ok'], 'zero rate');
	pg_assert_false(pg_currency_quick_change($row, 'exchange_rate', '0.000001')['ok'], 'a rate that rounds to zero');
	pg_assert_false(pg_currency_quick_change($row, 'exchange_rate', '100000')['ok'], 'above the column');
	pg_assert_same('0.02000', pg_currency_quick_change($row, 'exchange_rate', '0.02')['values']['exchange_rate'], 'five places');

	pg_assert_false(pg_currency_quick_change($row, 'created', '1')['ok'], 'a computed column');
}

// The answer draws the edited cell, the rest of the row, and the other
// rows the change redrew under 'rows'.
function test_currency_quick_saved_answer()
{
	$fields = pg_currency_edit_columns();
	$cells = pg_currency_list_cells(pg_test_currency_quick_row(array('base' => '1', 'exchange_rate' => '1.00000')), $fields, 'EUR');
	$old = pg_currency_list_cells(pg_test_currency_quick_row(array('id' => 1, 'name' => 'US Dollar', 'code' => 'USD', 'exchange_rate' => '1.08000')), $fields, 'EUR');
	$answer = pg_currency_quick_saved($cells, 'base', array(1 => $old));

	pg_assert_true($answer['ok']);
	pg_assert_same('1', $answer['value']);
	pg_assert_false(isset($answer['cells']['exchange_rate']['value']), 'the new base rate is no field');
	pg_assert_same('0', $answer['rows']['1']['base']['value'], 'the old base loses its mark');
	pg_assert_contains('EUR', $answer['rows']['1']['exchange_rate']['html'], 'the reverse rate names the new base');
}
