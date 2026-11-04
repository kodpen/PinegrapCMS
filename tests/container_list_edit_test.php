<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Containers list's quick edit
 * (includes/fn/container_list_edit.php): every value the container screen
 * changes is a field, the value each field opens with, and the form values
 * the container screen's check gets for a change.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/container_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/container_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'BASE_CURRENCY_SYMBOL' => '$', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $container_edit_test_name => $container_edit_test_value) {
	if (!defined($container_edit_test_name)) {
		define($container_edit_test_name, $container_edit_test_value);
	}
}

function pg_test_container_quick_row(array $over = array())
{
	return array_merge(array(
		'id' => '5', 'name' => 'Small box', 'enabled' => '1', 'length' => '12.5000', 'width' => '8.0000',
		'height' => '4.2500', 'weight' => '0.0000', 'cost' => '1250', 'created_username' => 'admin',
		'created_timestamp' => '1700000000', 'last_modified_username' => 'admin', 'last_modified_timestamp' => '1700000000',
	), $over);
}

function pg_test_container_decimal($text)
{
	$separators = pg_number_separators();

	return str_replace('.', $separators['decimal'], $text);
}

// Every value the container screen changes is an editable column of the
// list with a cell field; the rest are computed. A new column fails here
// until it is one or the other.
function test_container_quick_every_screen_value_is_a_field()
{
	$computed = array('created', 'last_modified');
	$fields = pg_container_quick_edit_columns();
	$cells = pg_container_list_cells(pg_test_container_quick_row(), $fields);

	foreach (pg_container_list_columns(true) as $key => $column) {
		if (in_array($key, $computed, true)) {
			pg_assert_false(isset($column['editable']), $key . ' is computed');
			continue;
		}

		pg_assert_true(isset($column['editable'], $fields[$key]), $key . ' is a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell carries its field');
	}

	foreach (array_keys($fields) as $field) {
		pg_assert_true(isset(pg_container_list_columns(true)[$field]), $field . ' has a column');
	}

	foreach (pg_container_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}
}

function test_container_quick_cells_without_edit_have_no_field()
{
	$cells = pg_container_list_cells(pg_test_container_quick_row(), array());

	foreach ($cells as $key => $cell) {
		pg_assert_false(isset($cell['attributes']['data-pg-edit']), $key . ' is text');
	}

	// The name's colour still follows Enabled.
	pg_assert_contains('status_disabled', pg_container_list_cells(pg_test_container_quick_row(array('enabled' => '0')), array())['name']['class']);
}

function test_container_quick_value_is_what_the_screen_shows()
{
	$row = pg_test_container_quick_row();

	pg_assert_same('Small box', pg_container_quick_value('name', $row));
	pg_assert_same('1', pg_container_quick_value('enabled', $row));
	pg_assert_same('0', pg_container_quick_value('enabled', pg_test_container_quick_row(array('enabled' => '0'))));
	pg_assert_same(pg_test_container_decimal('12.5'), pg_container_quick_value('length', $row));
	pg_assert_same('8', pg_container_quick_value('width', $row));
	pg_assert_same(pg_test_container_decimal('4.25'), pg_container_quick_value('height', $row));

	// No thousands separator: the screen's check reads one as a decimal mark.
	pg_assert_same('12345', pg_container_quick_value('length', pg_test_container_quick_row(array('length' => '12345.0000'))));

	// A blank weight or cost is none, as on the screen.
	pg_assert_same('', pg_container_quick_value('weight', $row));
	pg_assert_same(pg_test_container_decimal('2.5'), pg_container_quick_value('weight', pg_test_container_quick_row(array('weight' => '2.5000'))));
	pg_assert_same('', pg_container_quick_value('cost', pg_test_container_quick_row(array('cost' => '0'))));
	pg_assert_same(pg_format_input_amount(12.5), pg_container_quick_value('cost', $row));
}

function test_container_quick_form_values_change_one_field()
{
	$row = pg_test_container_quick_row();
	$values = pg_container_quick_form_values($row, 'width', '9,5');

	pg_assert_same('9,5', $values['width']);
	pg_assert_same('Small box', $values['name']);
	pg_assert_same('1', $values['enabled']);
	pg_assert_same(pg_test_container_decimal('12.5'), $values['length']);
	pg_assert_same('', $values['weight']);

	// The screen posts no enabled field when the switch is off.
	pg_assert_same('', pg_container_quick_form_values($row, 'enabled', '0')['enabled']);
	pg_assert_same('1', pg_container_quick_form_values(pg_test_container_quick_row(array('enabled' => '0')), 'enabled', '1')['enabled']);

	// An emptied weight or cost reaches the check empty.
	pg_assert_same('', pg_container_quick_form_values($row, 'cost', '')['cost']);

	// The read values pass the screen's own figure check.
	foreach (array('length', 'width', 'height') as $field) {
		pg_assert_same('', pg_container_number_check($values[$field], pg_container_dimension_max(), true), $field);
	}

	pg_assert_same('', pg_container_number_check(pg_container_quick_value('cost', $row), pg_container_cost_max(), false));
}

function test_container_quick_saved_draws_the_whole_row()
{
	$fields = pg_container_quick_edit_columns();
	$answer = pg_container_quick_saved(pg_container_list_cells(pg_test_container_quick_row(array('enabled' => '0')), $fields), 'enabled');

	pg_assert_true($answer['ok']);
	pg_assert_same('0', $answer['value']);
	pg_assert_contains('status_disabled', $answer['cells']['name']['class']);
	pg_assert_true(isset($answer['cells']['last_modified']));
	pg_assert_false(isset($answer['cells']['enabled']));
}
