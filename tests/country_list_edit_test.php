<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Countries list quick edit
 * (includes/fn/country_list_edit.php): every value the country screen
 * changes is a field, checked with the screen's own rules; a new default
 * redraws the country that lost the switch.
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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/country_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/country_list_edit.php');

foreach (array('DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $country_edit_test_name => $country_edit_test_value) {
	if (!defined($country_edit_test_name)) {
		define($country_edit_test_name, $country_edit_test_value);
	}
}

function pg_test_country_quick_row(array $over = array())
{
	return $over + array(
		'id' => 9, 'name' => 'Germany', 'label' => 'Germany', 'code' => 'DE', 'zip_code_required' => '1',
		'transit_adjustment_days' => '2', 'default_selected' => '0', 'user' => 'admin', 'timestamp' => '1700000000',
	);
}

function pg_test_country_quick_free($code, $except_id)
{
	return false;
}

function pg_test_country_quick_taken($code, $except_id)
{
	return true;
}

// Every value the country screen changes is an editable column with a cell
// field; Last Modified stays text. A new column fails here until it is one
// or the other.
function test_country_quick_every_screen_value_is_a_field()
{
	$fields = pg_country_edit_columns();
	$cells = pg_country_list_cells(pg_test_country_quick_row(), $fields);

	foreach (pg_country_list_columns(true) as $key => $column) {
		if ($key === 'last_modified') {
			pg_assert_false(isset($column['editable']), $key . ' is text');
			pg_assert_false(isset($cells[$key]['attributes']['data-pg-edit']), $key . ' cell is text');
			continue;
		}

		pg_assert_true(isset($column['editable']), $key . ' is editable');
		pg_assert_true(isset($fields[$key]), $key . ' has a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell is a field');
	}

	foreach (pg_country_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}
}

function test_country_quick_values()
{
	$row = pg_test_country_quick_row();

	pg_assert_same('Germany', pg_country_quick_value($row, 'name'));
	pg_assert_same('DE', pg_country_quick_value($row, 'code'));
	pg_assert_same('1', pg_country_quick_value($row, 'zip_code_required'));
	pg_assert_same('2', pg_country_quick_value($row, 'transit_adjustment'));
	pg_assert_same('0', pg_country_quick_value($row, 'default'));
}

// The checks are the screen's: name and code required and trimmed, a code
// another country has refused, whole days of zero or more.
function test_country_quick_change_checks()
{
	$row = pg_test_country_quick_row();
	$free = 'pg_test_country_quick_free';

	pg_assert_false(pg_country_quick_change($row, 'name', '', $free)['ok'], 'empty name');
	pg_assert_same('Deutschland', pg_country_quick_change($row, 'name', ' Deutschland ', $free)['values']['name'], 'trimmed name');

	pg_assert_false(pg_country_quick_change($row, 'code', '', $free)['ok'], 'empty code');
	pg_assert_same('DD', pg_country_quick_change($row, 'code', 'DD', $free)['values']['code']);
	pg_assert_false(pg_country_quick_change($row, 'code', 'FR', 'pg_test_country_quick_taken')['ok'], 'a taken code');
	pg_assert_true(pg_country_quick_change($row, 'code', 'DE', 'pg_test_country_quick_taken')['ok'], 'its own code again is no clash');

	pg_assert_same(0, pg_country_quick_change($row, 'transit_adjustment', '', $free)['values']['transit_adjustment_days'], 'empty days are 0');
	pg_assert_same(5, pg_country_quick_change($row, 'transit_adjustment', 5, $free)['values']['transit_adjustment_days']);

	$values = pg_country_quick_change($row, 'default', '1', $free)['values'];
	pg_assert_same(1, $values['default_selected'], 'made the default');
	pg_assert_same(1, $values['zip_code_required'], 'the other switch is kept');
	pg_assert_same(0, pg_country_quick_change($row, 'zip_code_required', '0', $free)['values']['zip_code_required']);

	pg_assert_false(pg_country_quick_change($row, 'last_modified', '1', $free)['ok'], 'a computed column');
}

// The answer draws the edited cell, the rest of the row, and the country
// that lost the default under 'rows'.
function test_country_quick_saved_answer()
{
	$fields = pg_country_edit_columns();
	$cells = pg_country_list_cells(pg_test_country_quick_row(array('default_selected' => '1')), $fields);
	$old = pg_country_list_cells(pg_test_country_quick_row(array('id' => 3, 'name' => 'France', 'label' => 'France', 'code' => 'FR')), $fields);
	$answer = pg_country_quick_saved($cells, 'default', array(3 => $old));

	pg_assert_true($answer['ok']);
	pg_assert_same('1', $answer['value']);
	pg_assert_same('2', $answer['cells']['transit_adjustment']['value']);
	pg_assert_same('0', $answer['rows']['3']['default']['value'], 'the old default loses its switch');
}
