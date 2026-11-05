<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Shipping Arrival Dates list's quick edit
 * (includes/fn/arrival_date_list_edit.php): every value the arrival date
 * screen changes and the list shows is a field, a customer-chosen arrival
 * date stays text, the value each field opens with, what the screen's
 * check gets for a change, the cut-off guard and the answer's cells.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/arrival_date_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/arrival_date_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $arrival_date_edit_test_name => $arrival_date_edit_test_value) {
	if (!defined($arrival_date_edit_test_name)) {
		define($arrival_date_edit_test_name, $arrival_date_edit_test_value);
	}
}

function pg_test_arrival_date_quick_row(array $over = array())
{
	return array_merge(array(
		'id' => '5', 'name' => 'Xmas <eve>', 'description' => 'Before the holiday', 'code' => 'XMAS',
		'arrival_date' => '2026-12-24', 'status' => 'enabled', 'start_date' => '2026-11-01', 'end_date' => '2026-12-20',
		'default_selected' => '1', 'sort_order' => '1000', 'custom' => '0', 'custom_maximum_arrival_date' => '0000-00-00',
		'user' => 'admin', 'timestamp' => '1700000000',
	), $over);
}

// Every value the arrival date screen changes and the list shows is an
// editable column with a cell field; Last Modified stays text. A new
// column fails here until it is one or the other.
function test_arrival_date_quick_every_screen_value_is_a_field()
{
	$fields = pg_arrival_date_quick_edit_columns();
	$cells = pg_arrival_date_list_cells(pg_test_arrival_date_quick_row(), $fields, '2026-11-15');

	foreach (pg_arrival_date_list_columns(true) as $key => $column) {
		pg_assert_true(isset($cells[$key]), $key . ' has a cell');

		if ($key === 'last_modified') {
			pg_assert_false(isset($column['editable']), $key . ' is text');
			pg_assert_false(isset($cells[$key]['attributes']['data-pg-edit']), $key . ' cell is text');
			continue;
		}

		pg_assert_true(isset($column['editable']), $key . ' is editable');
		pg_assert_true(isset($fields[$key]), $key . ' has a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell is a field');
	}

	foreach (pg_arrival_date_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}

	pg_assert_same(50, $fields['name']['max'], 'the name fits the screen\'s box');
}

function test_arrival_date_quick_cells()
{
	$cells = pg_arrival_date_list_cells(pg_test_arrival_date_quick_row(), pg_arrival_date_quick_edit_columns(), '2026-11-15');

	pg_assert_contains('Xmas &lt;eve&gt;', $cells['name']['html'], 'the name is escaped');
	pg_assert_contains('status_enabled', $cells['name']['html'], 'offered today');
	pg_assert_same('Xmas <eve>', $cells['name']['attributes']['data-pg-edit-value'], 'the name opens as stored');
	pg_assert_same('enabled', $cells['status']['attributes']['data-pg-edit-value'], 'the status opens as stored');
	pg_assert_same(pg_arrival_date_form_date_text('2026-12-24'), $cells['arrival_date']['attributes']['data-pg-edit-value'], 'a date opens as the screen shows it');
	pg_assert_same('20261101', $cells['start_date']['attributes']['data-pg-sort-value'], 'a date sorts by its digits');

	$late = pg_arrival_date_list_cells(pg_test_arrival_date_quick_row(), pg_arrival_date_quick_edit_columns(), '2026-12-21');
	pg_assert_contains('status_disabled', $late['name']['html'], 'past its end date it is not offered');

	$custom = pg_arrival_date_list_cells(pg_test_arrival_date_quick_row(array('custom' => '1', 'arrival_date' => '0000-00-00')), pg_arrival_date_quick_edit_columns(), '2026-11-15');
	pg_assert_false(isset($custom['arrival_date']['attributes']['data-pg-edit']), 'a customer-chosen arrival date stays text');
	pg_assert_same(h(lang('Chosen by the customer')), $custom['arrival_date']['html'], 'and says who chooses it');
	pg_assert_same('start_date', $custom['start_date']['attributes']['data-pg-edit'], 'its other dates still change');
	pg_assert_false(pg_arrival_date_quick_cell_editable('arrival_date', array('custom' => '1')), 'the write refuses it too');
}

function test_arrival_date_quick_post_changes_one_value()
{
	$row = pg_test_arrival_date_quick_row();

	$post = pg_arrival_date_quick_post($row, 'status', 'disabled');
	pg_assert_same('', $post['status'], 'a switch turned off posts nothing');
	pg_assert_same('1', $post['default_selected'], 'the default switch as stored');
	pg_assert_same('1000', $post['sort_order'], 'the sort order as stored');
	pg_assert_same('XMAS', $post['code'], 'the code as stored');
	pg_assert_same('disabled', pg_arrival_date_form_status($post), 'the screen reads it as disabled');
	pg_assert_same('enabled', pg_arrival_date_form_status(pg_arrival_date_quick_post($row, 'name', 'x')), 'another change keeps it enabled');

	pg_assert_same(array(), pg_arrival_date_form_errors(pg_arrival_date_quick_post($row, 'name', 'New')), 'the stored record passes the screen\'s check');

	$values = pg_arrival_date_form_values(pg_arrival_date_quick_post($row, 'start_date', '2026-11-05'), 9);
	pg_assert_same('2026-11-05', $values[5], 'the new start date');
	pg_assert_same('2026-12-24', $values[3], 'the arrival date written back unchanged');
	pg_assert_same('2026-12-20', $values[6], 'the end date written back unchanged');
	pg_assert_same('9', $values[11], 'the user');

	pg_assert_true(isset(pg_arrival_date_form_errors(pg_arrival_date_quick_post($row, 'end_date', '2026-10-01'))['end_date']), 'an end before the start is refused');
	pg_assert_true(isset(pg_arrival_date_form_errors(pg_arrival_date_quick_post($row, 'start_date', 'soon'))['start_date']), 'a date that is not one is refused');
	pg_assert_true(isset(pg_arrival_date_form_errors(pg_arrival_date_quick_post($row, 'name', '  '))['name']), 'a blank name is refused');
}

function test_arrival_date_quick_cutoff_refusal()
{
	$post = pg_arrival_date_quick_post(pg_test_arrival_date_quick_row(), 'end_date', '2026-12-10');

	pg_assert_same('', pg_arrival_date_quick_cutoff_refusal(array('2026-12-10 18:00:00', '2026-11-01 00:00:00'), $post), 'cut-offs inside the dates');
	pg_assert_true(pg_arrival_date_quick_cutoff_refusal(array('2026-12-15 12:00:00'), $post) !== '', 'a cut-off after the new end');
	pg_assert_true(pg_arrival_date_quick_cutoff_refusal(array('2026-10-31 23:00:00'), $post) !== '', 'a cut-off before the start');
	pg_assert_same('', pg_arrival_date_quick_cutoff_refusal(array(), $post), 'no cut-offs');
}

function test_arrival_date_quick_saved_answer()
{
	$cells = pg_arrival_date_list_cells(pg_test_arrival_date_quick_row(), pg_arrival_date_quick_edit_columns(), '2026-11-15');
	$answer = pg_arrival_date_quick_saved($cells, 'status');

	pg_assert_true($answer['ok'], 'a success');
	pg_assert_same('enabled', $answer['value'], 'the value the field opens with next');
	pg_assert_same(array('name', 'arrival_date', 'start_date', 'end_date', 'last_modified'), array_keys($answer['cells']), 'the name follows the status, Last Modified every change');
}

function test_arrival_date_list_screen_uses_the_shared_code()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_arrival_dates.php');
	$request = strpos($source, 'pg_list_edit_requested()');

	pg_assert_true($request !== false, 'the list answers quick edits');
	pg_assert_true($request < strpos($source, 'validate_ecommerce_access($user)'), 'before the access check, so a refusal is JSON');
	pg_assert_contains("(\$user['role'] < 3) || !empty(\$user['manage_ecommerce'])", $source, 'the screen\'s rights');
	pg_assert_contains("'edit' => array('url' => 'view_arrival_dates.php'", $source, 'the table has quick edit');

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/arrival_date_list_edit.php');
	pg_assert_contains('pg_arrival_date_form_errors($post)', $list, 'the screen\'s check');
	pg_assert_contains('pg_store_arrival_date_save((int) $id, pg_arrival_date_form_values($post, $user[\'id\']))', $list, 'the screen\'s save');
}
