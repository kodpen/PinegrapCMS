<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Verified Shipping Addresses list's quick edit
 * (includes/fn/verified_shipping_address_list_edit.php): the address parts
 * are fields, the country, Created and Last Modified stay text, the state
 * opens only on a known state, the screen's checks on a change and the
 * answer's cells.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/verified_shipping_address_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/verified_shipping_address_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $verified_address_edit_test_name => $verified_address_edit_test_value) {
	if (!defined($verified_address_edit_test_name)) {
		define($verified_address_edit_test_name, $verified_address_edit_test_value);
	}
}

function pg_test_verified_address_quick_row(array $over = array())
{
	return array_merge(array(
		'id' => '7', 'company' => 'Acme <HQ>', 'address_1' => '1 Main St', 'address_2' => 'Suite 2',
		'city' => 'Springfield', 'state_id' => '12', 'state_name' => 'Illinois', 'zip_code' => '62701',
		'country_name' => '', 'country_code' => '',
		'created_timestamp' => '1700000000', 'created_username' => 'admin',
		'last_modified_timestamp' => '1700000500', 'last_modified_username' => 'admin',
	), $over);
}

function pg_test_verified_address_quick_fields()
{
	return pg_verified_address_quick_edit_columns(array('12' => 'Illinois', '13' => 'Indiana'));
}

// Every value the address screen changes is an editable column with a cell
// field; the country, Created and Last Modified stay text. A new column
// fails here until it is one or the other.
function test_verified_address_quick_every_screen_value_is_a_field()
{
	$text = array('country', 'created', 'last_modified');
	$fields = pg_test_verified_address_quick_fields();
	$cells = pg_verified_address_list_cells(pg_test_verified_address_quick_row(), $fields);

	foreach (pg_verified_address_list_columns(true) as $key => $column) {
		if (in_array($key, $text, true)) {
			pg_assert_false(isset($column['editable']), $key . ' is text');
			pg_assert_false(isset($cells[$key]['attributes']['data-pg-edit']), $key . ' cell is text');
			continue;
		}

		pg_assert_true(isset($column['editable']), $key . ' is editable');
		pg_assert_true(in_array($column['editable'], pg_list_table_edit_kinds(), true), $key . ' is a driver kind');
		pg_assert_true(isset($fields[$key]), $key . ' has a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell is a field');
	}

	foreach (pg_verified_address_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}

	foreach (array('company', 'address_1', 'address_2', 'city', 'zip_code') as $key) {
		pg_assert_same(50, $fields[$key]['max'], $key . ' is the screen\'s 50 characters');
	}

	foreach (array('address_1', 'city', 'zip_code') as $key) {
		pg_assert_true(!empty($fields[$key]['required']), $key . ' is required');
	}

	pg_assert_false(!empty($fields['company']['required']) || !empty($fields['address_2']['required']), 'company and address 2 are optional');
}

function test_verified_address_quick_cells()
{
	$fields = pg_test_verified_address_quick_fields();
	$cells = pg_verified_address_list_cells(pg_test_verified_address_quick_row(), $fields);

	pg_assert_contains('href="edit_verified_shipping_address.php?id=7" data-pg-open>Acme &lt;HQ&gt;</a>', $cells['company']['html'], 'company links to the screen, escaped');
	pg_assert_same('Acme <HQ>', $cells['company']['attributes']['data-pg-edit-value'], 'company opens as stored');
	pg_assert_same('12', $cells['state']['attributes']['data-pg-edit-value'], 'state opens on its id');
	pg_assert_same('Illinois', $cells['state']['html'], 'state shows its name');

	$blank = pg_verified_address_list_cells(pg_test_verified_address_quick_row(array('company' => '')), $fields);
	pg_assert_contains('>#7</a>', $blank['company']['html'], 'no company shows the number');
	pg_assert_same('', $blank['company']['attributes']['data-pg-edit-value'], 'and opens empty');

	$gone = pg_verified_address_list_cells(pg_test_verified_address_quick_row(array('state_id' => '99', 'state_name' => null)), $fields);
	pg_assert_false(isset($gone['state']['attributes']['data-pg-edit']), 'a state not among the choices stays text');

	$plain = pg_verified_address_list_cells(pg_test_verified_address_quick_row(), array());
	pg_assert_false(isset($plain['company']['attributes']['data-pg-edit']), 'no field without quick edit');
}

function test_verified_address_state_options_keep_the_filter_labels()
{
	$states = array(
		array('id' => '13', 'name' => 'Indiana', 'country_name' => '', 'country_code' => ''),
		array('id' => '12', 'name' => 'Illinois', 'country_name' => '', 'country_code' => ''),
	);

	$options = pg_verified_address_state_options($states, false);
	pg_assert_same(array('13' => 'Indiana', '12' => 'Illinois'), $options, 'id => name, in the given order');
}

function test_verified_address_quick_change_uses_the_screen_checks()
{
	$stored = array('company' => 'Acme', 'address_1' => '1 Main St', 'address_2' => '', 'city' => 'Springfield', 'state_id' => '12', 'zip_code' => '62701');

	$change = pg_verified_address_quick_change($stored, 'city', '  Chicago ');
	pg_assert_true($change['ok'], 'city change');
	pg_assert_same('Chicago', $change['values']['city'], 'city trimmed');
	pg_assert_same('1 Main St', $change['values']['address_1'], 'other values kept');
	pg_assert_same(array('company', 'address_1', 'address_2', 'city', 'state_id', 'zip_code'), array_keys($change['values']), 'the screen\'s save order');

	$change = pg_verified_address_quick_change($stored, 'state', '13');
	pg_assert_true($change['ok'], 'state change');
	pg_assert_same('13', $change['values']['state_id'], 'state written to state_id');

	pg_assert_true(pg_verified_address_quick_change($stored, 'company', '')['ok'], 'company may be emptied');
	pg_assert_true(pg_verified_address_quick_change($stored, 'address_2', '')['ok'], 'address 2 may be emptied');

	foreach (array('address_1', 'city', 'zip_code') as $field) {
		pg_assert_false(pg_verified_address_quick_change($stored, $field, ' ')['ok'], $field . ' blank refused');
	}

	pg_assert_false(pg_verified_address_quick_change($stored, 'state', '0')['ok'], 'no state refused');
	pg_assert_false(pg_verified_address_quick_change($stored, 'created', 'x')['ok'], 'a computed column refused');
}

function test_verified_address_quick_saved_redraws_the_row()
{
	$cells = pg_verified_address_list_cells(pg_test_verified_address_quick_row(), pg_test_verified_address_quick_fields());
	$answer = pg_verified_address_quick_saved($cells, 'state');

	pg_assert_true($answer['ok'], 'ok');
	pg_assert_same('12', $answer['value'], 'state value');
	pg_assert_false(isset($answer['cells']['state']), 'the edited cell is the answer itself');
	pg_assert_true(isset($answer['cells']['country']) && isset($answer['cells']['last_modified']) && isset($answer['cells']['company']), 'country, Last Modified and the company link redrawn');
	pg_assert_same('Acme <HQ>', $answer['cells']['company']['value'], 'company keeps its value');
}

function test_verified_address_list_answers_quick_edit_before_its_access_check()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_verified_shipping_addresses.php');

	$request = strpos($source, 'pg_list_edit_request($quick_edit');
	$gate = strpos($source, 'validate_ecommerce_access($user);');
	pg_assert_true(($request !== false) && ($gate !== false) && ($request < $gate), 'JSON answer before the screen gate');
	pg_assert_contains("return (\$user['role'] < 3) || !empty(\$user['manage_ecommerce']);", $source, 'the screen\'s commerce rights');
	pg_assert_contains("'edit' => array('url' => 'view_verified_shipping_addresses.php', 'columns' => \$quick_edit)", $source, 'the list offers quick edit');
}
