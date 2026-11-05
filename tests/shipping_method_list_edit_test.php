<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Shipping Methods list quick edit
 * (includes/fn/shipping_method_list_edit.php): every value of the list the
 * shipping method screen changes is a field, checked with the screen's own
 * rules; a pickup method and a variable base rate keep the cells the screen
 * decides for them as text.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipping_method_list_edit.php');

foreach (array('BASE_CURRENCY_SYMBOL' => '$', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $shipping_edit_test_name => $shipping_edit_test_value) {
	if (!defined($shipping_edit_test_name)) {
		define($shipping_edit_test_name, $shipping_edit_test_value);
	}
}

function pg_test_shipping_quick_method(array $over = array())
{
	return $over + array(
		'id' => 7, 'name' => 'Courier', 'description' => 'Two days', 'code' => 'C1',
		'status' => 'enabled', 'start_time' => '2020-01-01 00:00:00', 'end_time' => '2099-01-01 00:00:00',
		'service' => '', 'realtime_rate' => 0,
		'base_rate' => 500, 'variable_base_rate' => 0,
		'base_rate_2' => 0, 'base_rate_2_subtotal' => 0, 'base_rate_3' => 0, 'base_rate_3_subtotal' => 0, 'base_rate_4' => 0, 'base_rate_4_subtotal' => 0,
		'primary_weight_rate' => 0, 'secondary_weight_rate' => 0, 'item_rate' => 125,
		'handle_days' => 0, 'base_transit_days' => 2, 'street_address' => 1, 'po_box' => 0, 'protected' => 0,
		'stock_location_id' => 0, 'user' => '<b>ayse</b>', 'timestamp' => time(),
	);
}

function test_shipping_quick_edit_every_column_but_zones_and_last_modified_is_a_field()
{
	$columns = pg_shipping_method_edit_columns(array('ups_ground'));
	$fields = array_keys($columns);
	sort($fields);

	$text = array_diff(array_keys(pg_shipping_method_list_columns(true)), $fields);
	sort($text);

	pg_assert_same(array('last_modified', 'zones'), array_values($text), 'only the zones (several choices) and the last change stay text');
	$named = array_keys(pg_shipping_method_quick_column_names());
	sort($named);
	pg_assert_same($fields, $named, 'each field names its column');
	pg_assert_same(array('' => '–', 'ups_ground' => get_shipping_service_name('ups_ground')), $columns['service']['options'], 'no service, or one offered');
	pg_assert_same(50, $columns['name']['max'], 'the screen\'s name length');
	pg_assert_same(65535, $columns['handling']['max'], 'handling is SMALLINT UNSIGNED');
}

function test_shipping_quick_services_are_the_ready_carriers_then_the_stored_ones()
{
	$services = pg_shipping_method_quick_services(array('usps' => false, 'ups' => true, 'fedex' => false), array('fedex_ground', 'ups_ground', ''));

	pg_assert_true(in_array('ups_next_day_air', $services, true), 'a service of a carrier that is set up');
	pg_assert_false(in_array('usps_priority', $services, true), 'none of a carrier that is not');
	pg_assert_same('fedex_ground', end($services), 'a stored service is kept, after the offered ones');
	pg_assert_same(count(array_unique($services)), count($services), 'each once');
}

function test_shipping_quick_change_takes_the_screen_checks()
{
	$method = pg_test_shipping_quick_method();
	$ready = array('usps' => false, 'ups' => true, 'fedex' => true);

	pg_assert_same(array('name' => 'Express'), pg_shipping_method_quick_change($method, 'name', 'Express', $ready)['columns'], 'a name');
	pg_assert_false(pg_shipping_method_quick_change($method, 'name', '  ', $ready)['ok'], 'no blank name');
	pg_assert_same(array('description' => ''), pg_shipping_method_quick_change($method, 'message', '', $ready)['columns'], 'the message may be blank');
	pg_assert_same(array('primary_weight_rate' => '750'), pg_shipping_method_quick_change($method, 'primary_weight_rate', '7,50', $ready)['columns'], 'an amount in cents');
	pg_assert_same(array('item_rate' => '0'), pg_shipping_method_quick_change($method, 'item_rate', '', $ready)['columns'], 'a blank amount is no charge');
	pg_assert_false(pg_shipping_method_quick_change($method, 'item_rate', '99999999999', $ready)['ok'], 'beyond INT UNSIGNED');
	pg_assert_same(array('handle_days' => '0'), pg_shipping_method_quick_change($method, 'handling', '', $ready)['columns'], 'a blank handling time is none');
	pg_assert_false(pg_shipping_method_quick_change($method, 'handling', '70000', $ready)['ok'], 'beyond SMALLINT UNSIGNED');
	pg_assert_same(array('base_transit_days' => '3'), pg_shipping_method_quick_change($method, 'transit', '3', $ready)['columns'], 'a transit time');
	pg_assert_same(array('po_box' => '1'), pg_shipping_method_quick_change($method, 'po_box', '1', $ready)['columns'], 'a switch');
	pg_assert_same(array('protected' => '0'), pg_shipping_method_quick_change($method, 'protected', '0', $ready)['columns'], 'a switch off');
	pg_assert_false(pg_shipping_method_quick_change($method, 'zones', '1', $ready)['ok'], 'the zones are not a field');
}

function test_shipping_quick_change_ties_the_real_time_rate_to_the_service()
{
	$ready = array('usps' => false, 'ups' => true, 'fedex' => true);
	$method = pg_test_shipping_quick_method(array('service' => 'ups_ground', 'realtime_rate' => 1));

	pg_assert_same(array('service' => 'fedex_ground', 'realtime_rate' => '0'), pg_shipping_method_quick_change($method, 'service', 'fedex_ground', $ready)['columns'], 'FedEx has no real-time rate: it turns off');
	pg_assert_same(array('service' => '', 'realtime_rate' => '0'), pg_shipping_method_quick_change($method, 'service', '', $ready)['columns'], 'no service, no real-time rate');
	pg_assert_same(array('service' => 'ups_2nd_day_air'), pg_shipping_method_quick_change($method, 'service', 'ups_2nd_day_air', $ready)['columns'], 'a UPS service keeps it');
	pg_assert_false(pg_shipping_method_quick_change($method, 'service', 'usps_priority', $ready)['ok'], 'a carrier that is not set up');

	$kept = pg_test_shipping_quick_method(array('service' => 'usps_priority'));
	pg_assert_true(pg_shipping_method_quick_change($kept, 'service', 'usps_priority', $ready)['ok'], 'the stored service of a carrier no longer set up stays');

	pg_assert_false(pg_shipping_method_quick_change(pg_test_shipping_quick_method(), 'realtime_rate', '1', $ready)['ok'], 'no real-time rate without a service');
	pg_assert_false(pg_shipping_method_quick_change(pg_test_shipping_quick_method(array('service' => 'fedex_ground')), 'realtime_rate', '1', $ready)['ok'], 'nor with FedEx');
	pg_assert_same(array('realtime_rate' => '1'), pg_shipping_method_quick_change($kept, 'realtime_rate', '1', $ready)['columns'], 'a USPS service takes one');
}

function test_shipping_quick_pickup_and_variable_base_rate_cells_stay_text()
{
	$ready = array('usps' => true, 'ups' => true, 'fedex' => true);
	$columns = pg_shipping_method_edit_columns(array('ups_ground'));
	$pickup = pg_test_shipping_quick_method(array('stock_location_id' => 3));

	foreach (array('service', 'realtime_rate', 'base_rate', 'street', 'po_box') as $field) {
		pg_assert_same('locked', pg_shipping_method_quick_change($pickup, $field, '1', $ready)['code'], $field . ': refused on a pickup method');
		pg_assert_false(isset(pg_shipping_method_list_cells($pickup, array(), $columns)[$field]['attributes']['data-pg-edit']), $field . ': text on a pickup method');
	}

	pg_assert_true(pg_shipping_method_quick_change($pickup, 'item_rate', '1', $ready)['ok'], 'a pickup method keeps its item rate');

	$variable = pg_test_shipping_quick_method(array('variable_base_rate' => 1, 'base_rate_2' => 300, 'base_rate_2_subtotal' => 10000));
	pg_assert_same('locked', pg_shipping_method_quick_change($variable, 'base_rate', '5', $ready)['code'], 'a variable base rate is refused');
	pg_assert_false(isset(pg_shipping_method_list_cells($variable, array(), $columns)['base_rate']['attributes']['data-pg-edit']), 'and drawn as text');
}

function test_shipping_quick_cells_carry_the_values_their_fields_open_with()
{
	$columns = pg_shipping_method_edit_columns();
	$cells = pg_shipping_method_list_cells(pg_test_shipping_quick_method(), array('<Zone>'), $columns);

	pg_assert_same(array_keys(pg_shipping_method_list_columns(true)), array_keys($cells), 'a cell for every column, in order');
	pg_assert_same('Courier', $cells['name']['attributes']['data-pg-edit-value'], 'the name');
	pg_assert_same(pg_format_input_amount(1.25), $cells['item_rate']['attributes']['data-pg-edit-value'], 'an amount in the site\'s decimal style');
	pg_assert_same('', $cells['handling']['attributes']['data-pg-edit-value'], 'no handling time opens blank');
	pg_assert_same('1', $cells['street']['attributes']['data-pg-edit-value'], 'a switch on');
	pg_assert_same('0', $cells['po_box']['attributes']['data-pg-edit-value'], 'a switch off');
	pg_assert_same('&lt;Zone&gt;', $cells['zones']['html'], 'the zones escaped');
	pg_assert_false(isset($cells['zones']['attributes']['data-pg-edit']), 'and not a field');
	pg_assert_contains('&lt;b&gt;ayse&lt;/b&gt;', $cells['last_modified']['html'], 'the username escaped');

	$plain = pg_shipping_method_list_cells(pg_test_shipping_quick_method(), array(), array());
	pg_assert_false(isset($plain['name']['attributes']['data-pg-edit']), 'no fields without quick edit');

	$variable = pg_shipping_method_list_cells(pg_test_shipping_quick_method(array('variable_base_rate' => 1, 'base_rate_2' => 300, 'base_rate_2_subtotal' => 10000, 'base_rate_3_subtotal' => 20000)), array(), $columns);
	pg_assert_same(2, substr_count($variable['base_rate']['html'], '<br>'), 'each step with a subtotal');
	pg_assert_contains(' (@' . pg_format_money(100, BASE_CURRENCY_SYMBOL) . ')', $variable['base_rate']['html'], 'a step shows the subtotal it starts at');

	$answer = pg_shipping_method_quick_saved($cells, 'service');
	pg_assert_true($answer['ok'], 'a saved answer');
	pg_assert_same('0', $answer['cells']['realtime_rate']['value'], 'the other cells carry their values, the real-time rate after a service');
	pg_assert_false(isset($answer['cells']['service']), 'the edited cell is the answer itself');
}
