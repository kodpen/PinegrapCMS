<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipping method form shared by the create and the edit
 * screens (includes/forms/shipping_method_form.php): the defaults of a new
 * method, the stored record read back into the form, the amounts, variable
 * base rates and excluded dates a post is saved with, the zone picker, and
 * that both screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');

function test_shipping_method_form_new_values()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	$values = pg_shipping_method_form_new_values();

	pg_assert_true($values['status'] && $values['street_address'] && $values['po_box'], 'enabled, street address and PO box on');
	pg_assert_false($values['protected'] || $values['realtime_rate'] || $values['variable_base_rate'] || $values['adjust_transit'], 'the other switches off');
	pg_assert_same(null, $values['service'], 'the service picker keeps its default');

	foreach (array('mon', 'tue', 'wed', 'thu', 'fri') as $day) {
		pg_assert_true($values['handle_' . $day] && $values['ship_' . $day], $day . ' handled and shipped');
	}

	pg_assert_false($values['handle_sat'] || $values['handle_sun'] || $values['ship_sat'] || $values['ship_sun'], 'not on the weekend');

	foreach (pg_shipping_method_form_days() as $long) {
		pg_assert_true($values['available_on_' . $long], 'available on ' . $long);
		pg_assert_same('', $values['available_on_' . $long . '_cutoff_time'], $long . ' has no cut-off');
	}

	pg_assert_same(date('n/j/Y') . ' 12:00 AM', $values['start_time'], 'opens today');
	pg_assert_contains('2099 11:59 PM', $values['end_time'], 'closes at the end of 2099');
}

function test_shipping_method_amount_cents()
{
	pg_assert_same(0, pg_shipping_method_amount_cents(''), 'blank');
	pg_assert_same(0, pg_shipping_method_amount_cents('0'), 'zero');
	pg_assert_same(1250, pg_shipping_method_amount_cents('12.50'), 'decimal point');
	pg_assert_same(1, pg_shipping_method_amount_cents('0.005'), 'rounded, not truncated');
	pg_assert_same(0, pg_shipping_method_amount_cents(array('1')), 'an array is no amount');
}

function test_shipping_method_variable_base_rates()
{
	$off = pg_shipping_method_variable_base_rates(array('base_rate_2' => '5', 'base_rate_2_subtotal' => '50'));
	pg_assert_same(0, $off['variable_base_rate'], 'switch off: nothing saved');
	pg_assert_same(0, $off['base_rate_2_subtotal'], 'switch off: pairs are 0');

	$rates = pg_shipping_method_variable_base_rates(array(
		'variable_base_rate' => '1',
		'base_rate_2' => '3',
		'base_rate_2_subtotal' => '100',
		'base_rate_3' => '',
		'base_rate_3_subtotal' => '',
		'base_rate_4' => '5',
		'base_rate_4_subtotal' => '50',
	));

	pg_assert_same(
		array(
			'variable_base_rate' => 1,
			'base_rate_2' => 500,
			'base_rate_2_subtotal' => 5000,
			'base_rate_3' => 300,
			'base_rate_3_subtotal' => 10000,
			'base_rate_4' => 0,
			'base_rate_4_subtotal' => 0,
		),
		$rates,
		'valid pairs ordered by subtotal, from base_rate_2 on');

	$none = pg_shipping_method_variable_base_rates(array('variable_base_rate' => '1', 'base_rate_2' => '3', 'base_rate_2_subtotal' => '0'));
	pg_assert_same(0, $none['variable_base_rate'], 'no pair with a subtotal: off');
}

function test_shipping_method_excluded_dates()
{
	pg_assert_same(array(), pg_shipping_method_excluded_dates(''), 'empty box');
	pg_assert_same(
		array('2026-01-01', '2026-12-25'),
		pg_shipping_method_excluded_dates('2026-12-25, 2026-01-01,2026-12-25,2026-02-30,nonsense'),
		'valid dates only, once each, sorted');
}

function test_shipping_method_zone_options()
{
	$zones = array(
		array('id' => '1', 'name' => 'Europe'),
		array('id' => '2', 'name' => 'Turkey & <Cyprus>'),
		array('id' => '3', 'name' => 'World'),
	);

	pg_assert_same(
		'<option value="1">Europe</option><option value="2">Turkey &amp; &lt;Cyprus&gt;</option><option value="3">World</option>',
		pg_shipping_method_zone_options($zones, array()),
		'a new method: every zone, none selected');

	pg_assert_same(
		'<option selected="selected" value="3">World</option><option value="1">Europe</option><option value="2">Turkey &amp; &lt;Cyprus&gt;</option>',
		pg_shipping_method_zone_options($zones, array(3)),
		'allowed zones first and selected');
}

// The columns and values the screens wrote before the form was shared.
function test_shipping_method_form_columns()
{
	$columns = pg_shipping_method_form_columns(array(
		'name' => 'Courier',
		'service' => 'fedex_ground',
		'realtime_rate' => '1',
		'status' => '1',
		'base_rate' => '9.99',
		'item_rate' => '1',
		'item_rate_first_item_excluded' => '1',
		'handle_mon' => '1',
		'available_on_sunday' => '1',
		'protected' => array('1'),
	));

	pg_assert_same(
		array('name', 'description', 'code', 'carrier_title', 'carrier_vkn', 'status', 'start_time', 'end_time', 'service', 'realtime_rate',
			'base_rate', 'variable_base_rate', 'base_rate_2', 'base_rate_2_subtotal', 'base_rate_3', 'base_rate_3_subtotal', 'base_rate_4', 'base_rate_4_subtotal',
			'primary_weight_rate', 'primary_weight_rate_first_item_excluded', 'secondary_weight_rate', 'secondary_weight_rate_first_item_excluded', 'item_rate', 'item_rate_first_item_excluded',
			'base_transit_days', 'adjust_transit', 'street_address', 'po_box', 'handle_days',
			'handle_mon', 'handle_tue', 'handle_wed', 'handle_thu', 'handle_fri', 'handle_sat', 'handle_sun',
			'ship_mon', 'ship_tue', 'ship_wed', 'ship_thu', 'ship_fri', 'ship_sat', 'ship_sun',
			'end_of_day', 'transit_on_sunday', 'transit_on_saturday',
			'available_on_monday', 'available_on_monday_cutoff_time', 'available_on_tuesday', 'available_on_tuesday_cutoff_time',
			'available_on_wednesday', 'available_on_wednesday_cutoff_time', 'available_on_thursday', 'available_on_thursday_cutoff_time',
			'available_on_friday', 'available_on_friday_cutoff_time', 'available_on_saturday', 'available_on_saturday_cutoff_time',
			'available_on_sunday', 'available_on_sunday_cutoff_time', 'protected'),
		array_keys($columns),
		'every column the screens save');

	pg_assert_same('enabled', $columns['status'], 'ticked status');
	pg_assert_same('0', $columns['realtime_rate'], 'FedEx has no real-time rate');
	pg_assert_same('999', $columns['base_rate'], 'base rate in minor units');
	pg_assert_same('100', $columns['item_rate'], 'item rate in minor units');
	pg_assert_same('0', $columns['primary_weight_rate'], 'blank rate is 0');
	pg_assert_same('1', $columns['item_rate_first_item_excluded'], 'ticked box');
	pg_assert_same('', $columns['handle_tue'], 'unticked box is not posted');
	pg_assert_same('', $columns['protected'], 'an array is not saved as "Array"');
	pg_assert_same('', $columns['end_of_day'], 'blank time');

	foreach ($columns as $column => $value) {
		pg_assert_true(is_string($value), $column . ' is bound as a string, as before');
	}

	$disabled = pg_shipping_method_form_columns(array('service' => 'ups_ground', 'realtime_rate' => '1'));
	pg_assert_same('disabled', $disabled['status'], 'unticked status');
	pg_assert_same('1', $disabled['realtime_rate'], 'a service with real-time rates keeps the box');
}

function test_shipping_method_screens_use_shared_form()
{
	foreach (array('add_shipping_method.php', 'edit_shipping_method.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/shipping_method_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_shipping_method_form_cards(', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_shipping_method_form_columns($_POST)', $source, $file . ' saves the shared columns');
		pg_assert_contains('pg_shipping_method_save_links(', $source, $file . ' writes zones and excluded dates');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
	}
}
