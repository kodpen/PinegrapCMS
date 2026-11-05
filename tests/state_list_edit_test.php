<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All States list quick edit
 * (includes/fn/state_list_edit.php): every value the state screen changes
 * is a field, checked with the screen's own rules; a state whose country is
 * gone keeps its Country cell as text.
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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/state_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/state_list_edit.php');

foreach (array('DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $state_edit_test_name => $state_edit_test_value) {
	if (!defined($state_edit_test_name)) {
		define($state_edit_test_name, $state_edit_test_value);
	}
}

function pg_test_state_quick_row(array $over = array())
{
	return $over + array(
		'id' => 5, 'name' => 'Bavaria', 'code' => 'BY', 'country_id' => '9', 'country_name' => 'Germany', 'country_code' => 'DE',
		'country_label' => 'Germany', 'user' => 'admin', 'timestamp' => '1700000000',
	);
}

function pg_test_state_quick_fields()
{
	return pg_state_edit_columns(array('9' => 'Germany', '3' => 'France'));
}

function pg_test_state_quick_free($code, $country_id, $except_id)
{
	return false;
}

function pg_test_state_quick_taken($code, $country_id, $except_id)
{
	return true;
}

function pg_test_state_quick_country($country_id)
{
	return in_array((int) $country_id, array(3, 9), true);
}

// Every value the state screen changes is an editable column with a cell
// field; Last Modified stays text. A new column fails here until it is one
// or the other.
function test_state_quick_every_screen_value_is_a_field()
{
	$fields = pg_test_state_quick_fields();
	$cells = pg_state_list_cells(pg_test_state_quick_row(), $fields);

	foreach (pg_state_list_columns(true) as $key => $column) {
		if ($key === 'last_modified') {
			pg_assert_false(isset($column['editable']), $key . ' is text');
			pg_assert_false(isset($cells[$key]['attributes']['data-pg-edit']), $key . ' cell is text');
			continue;
		}

		pg_assert_true(isset($column['editable']), $key . ' is editable');
		pg_assert_true(isset($fields[$key]), $key . ' has a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell is a field');
	}

	pg_assert_same('9', $cells['country']['attributes']['data-pg-edit-value'], 'the country opens with its id');

	foreach (pg_state_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}
}

// A state whose country is gone: the picker does not offer it, so the cell
// stays text.
function test_state_quick_gone_country_is_text()
{
	$cells = pg_state_list_cells(pg_test_state_quick_row(array('country_id' => '44', 'country_name' => null, 'country_label' => '')), pg_test_state_quick_fields());

	pg_assert_false(isset($cells['country']['attributes']['data-pg-edit']), 'no field for a gone country');
	pg_assert_same('name', $cells['name']['attributes']['data-pg-edit'] ?? '', 'the name still changes');
}

// The checks are the screen's: name and code required and trimmed, a code
// another state of the country has refused (asked again on a country
// move), a country that exists.
function test_state_quick_change_checks()
{
	$row = pg_test_state_quick_row();
	$free = 'pg_test_state_quick_free';
	$taken = 'pg_test_state_quick_taken';
	$country = 'pg_test_state_quick_country';

	pg_assert_false(pg_state_quick_change($row, 'name', '', $free, $country)['ok'], 'empty name');
	pg_assert_same('Bayern', pg_state_quick_change($row, 'name', ' Bayern ', $free, $country)['values']['name'], 'trimmed name');
	pg_assert_true(pg_state_quick_change($row, 'name', 'Bayern', $taken, $country)['ok'], 'an old code clash does not block a name');

	pg_assert_false(pg_state_quick_change($row, 'code', '', $free, $country)['ok'], 'empty code');
	pg_assert_false(pg_state_quick_change($row, 'code', 'BW', $taken, $country)['ok'], 'a taken code');
	pg_assert_same('BW', pg_state_quick_change($row, 'code', 'BW', $free, $country)['values']['code']);

	pg_assert_false(pg_state_quick_change($row, 'country', '3', $taken, $country)['ok'], 'the code is taken in the new country');
	pg_assert_same(3, pg_state_quick_change($row, 'country', '3', $free, $country)['values']['country_id'], 'moved');
	pg_assert_false(pg_state_quick_change($row, 'country', '44', $free, $country)['ok'], 'a country that does not exist');

	pg_assert_false(pg_state_quick_change($row, 'last_modified', '1', $free, $country)['ok'], 'a computed column');
}

function test_state_quick_saved_answer()
{
	$cells = pg_state_list_cells(pg_test_state_quick_row(), pg_test_state_quick_fields());
	$answer = pg_state_quick_saved($cells, 'code');

	pg_assert_true($answer['ok']);
	pg_assert_same('BY', $answer['value']);
	pg_assert_same('9', $answer['cells']['country']['value']);
	pg_assert_false(isset($answer['cells']['last_modified']['value']), 'Last Modified is no field');
}
