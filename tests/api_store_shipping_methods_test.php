<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipping method writes: the external API's fields turned
 * into columns (api_store_shipping_method_columns()), the service list
 * against the column and the screen's picker, the route rows, the events,
 * and the two screens writing through includes/fn/shipping_methods.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function api_store_shipping_methods_test_columns($params, $row = null)
{
	return api_store_shipping_method_columns(($row === null) ? api_store_shipping_method_new_row() : $row, $params);
}

function test_api_shipping_method_new_row_matches_the_screen_defaults()
{
	list($columns, $error) = api_store_shipping_methods_test_columns(array('name' => 'Courier'));

	pg_assert_same(null, $error, 'a name is enough');
	pg_assert_same('enabled', $columns['status'], 'enabled');
	pg_assert_same('1', $columns['street_address'], 'street addresses allowed');
	pg_assert_same('1', $columns['handle_mon'], 'handled on Monday');
	pg_assert_same('0', $columns['ship_sun'], 'no transit on Sunday');
	pg_assert_same('1', $columns['available_on_sunday'], 'offered every day');
	pg_assert_same('1000-01-01 00:00:00', $columns['start_time'], 'no start');
	pg_assert_same('9999-12-31 23:59:59', $columns['end_time'], 'no end');
	pg_assert_false(isset($columns['id']) || isset($columns['user']) || isset($columns['timestamp']), 'no id, user or timestamp column');
}

function test_api_shipping_method_needs_a_name()
{
	list($columns, $error) = api_store_shipping_methods_test_columns(array('name' => '   '));

	pg_assert_same(null, $columns, 'nothing to save');
	pg_assert_same('name', $error[0], 'the name is named');
}

function test_api_shipping_method_base_rates_become_tiers()
{
	list($columns) = api_store_shipping_methods_test_columns(array('name' => 'A', 'base_rates' => array(
		array('from_subtotal' => 50000, 'rate' => 0),
		array('from_subtotal' => 0, 'rate' => 1500),
		array('from_subtotal' => 20000, 'rate' => 900),
	)));

	pg_assert_same('1500', $columns['base_rate'], 'the tier from 0 is the base rate');
	pg_assert_same('1', $columns['variable_base_rate'], 'more tiers switch the variable rate on');
	pg_assert_same('20000', $columns['base_rate_2_subtotal'], 'tiers in subtotal order');
	pg_assert_same('900', $columns['base_rate_2'], 'with their rate');
	pg_assert_same('50000', $columns['base_rate_3_subtotal'], 'the next tier');
	pg_assert_same('0', $columns['base_rate_4_subtotal'], 'an unused tier is 0');

	list($one) = api_store_shipping_methods_test_columns(array('name' => 'A', 'base_rates' => array(array('from_subtotal' => 0, 'rate' => 700))));
	pg_assert_same('0', $one['variable_base_rate'], 'one tier is a flat rate');

	list(, $error) = api_store_shipping_methods_test_columns(array('name' => 'A', 'base_rates' => array(array('from_subtotal' => 100, 'rate' => 700))));
	pg_assert_same('base_rates', $error[0], 'a list without a tier from 0 is refused');

	list(, $error) = api_store_shipping_methods_test_columns(array('name' => 'A', 'base_rates' => array(array('from_subtotal' => 0, 'rate' => 7.5))));
	pg_assert_same('base_rates', $error[0], 'a decimal amount is refused, not rounded');
}

function test_api_shipping_method_days_and_times()
{
	list($columns) = api_store_shipping_methods_test_columns(array(
		'name' => 'A',
		'offered_on' => array(array('day' => 'mon', 'until' => '14:30'), array('day' => 'sat', 'until' => null)),
		'handling_on' => array('tue'),
		'end_of_day' => '17:00',
	));

	pg_assert_same('1', $columns['available_on_monday'], 'offered on Monday');
	pg_assert_same('14:30:00', $columns['available_on_monday_cutoff_time'], 'until its cut-off');
	pg_assert_same('00:00:00', $columns['available_on_saturday_cutoff_time'], 'null is all day');
	pg_assert_same('0', $columns['available_on_tuesday'], 'a day left out is not offered');
	pg_assert_same('1', $columns['handle_tue'], 'handled on Tuesday');
	pg_assert_same('0', $columns['handle_mon'], 'and no longer on Monday');
	pg_assert_same('17:00:00', $columns['end_of_day'], 'end of day as a TIME');

	list(, $error) = api_store_shipping_methods_test_columns(array('name' => 'A', 'transit_on' => array('monday')));
	pg_assert_same('transit_on', $error[0], 'a long day name is refused');

	list(, $error) = api_store_shipping_methods_test_columns(array('name' => 'A', 'end_of_day' => '25:00'));
	pg_assert_same('end_of_day', $error[0], 'no such time');
}

function test_api_shipping_method_window_and_partial_update()
{
	$row = api_store_shipping_method_new_row();
	$row['name'] = 'Stored';
	$row['item_rate'] = '300';
	$row['service'] = 'ups_ground';
	$row['realtime_rate'] = '1';

	list($columns) = api_store_shipping_methods_test_columns(array('primary_weight_rate' => 250), $row);
	pg_assert_same('Stored', $columns['name'], 'an unsent name stays');
	pg_assert_same('300', $columns['item_rate'], 'an unsent rate stays');
	pg_assert_same('250', $columns['primary_weight_rate'], 'a sent rate changes');
	pg_assert_same('1', $columns['realtime_rate'], 'a live rate with UPS stays on');

	list($fedex) = api_store_shipping_methods_test_columns(array('realtime_service' => 'fedex_ground'), $row);
	pg_assert_same('0', $fedex['realtime_rate'], 'FedEx has no live rate, as on the screen');

	list(, $error) = api_store_shipping_methods_test_columns(array('available_from' => mktime(0, 0, 0, 6, 2, 2027), 'available_until' => mktime(0, 0, 0, 6, 1, 2027)), $row);
	pg_assert_same('available_until', $error[0], 'an end before the start is refused');

	list($always) = api_store_shipping_methods_test_columns(array('available_from' => ''), $row);
	pg_assert_same('1000-01-01 00:00:00', $always['start_time'], 'null is always');
}

function test_shipping_method_services_match_the_column_and_the_picker()
{
	$sql = file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/turkish_default/sql.sql');
	pg_assert_true((bool) preg_match("/`service` enum\\('',([^)]*)\\)/", $sql, $match), 'the column is found');

	$enum = explode(',', str_replace("'", '', $match[1]));
	$services = pg_shipping_method_services();
	sort($enum);
	sort($services);
	pg_assert_same($enum, $services, 'the list is the ENUM');

	require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');

	$choices = pg_shipping_method_service_choices(array('usps' => true, 'ups' => true, 'fedex' => true), null);
	$picked = call_user_func_array('array_merge', array_values($choices['groups']));
	sort($picked);
	pg_assert_same($services, $picked, 'the screen picker offers the same services');
}

function test_api_shipping_method_routes_and_events()
{
	$found = 0;

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'store.shipping_methods.') !== 0 || $route['method'] === 'GET') {
			continue;
		}

		$found++;
		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');
		pg_assert_same('store:write', $route['scope'], $route['id'] . ' scope');
		pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
	}

	pg_assert_same(4, $found, 'create, update, delete and its POST address');

	$events = api_webhook_events();

	foreach (array('shipping_method.created', 'shipping_method.updated', 'shipping_method.deleted') as $event) {
		pg_assert_true(isset($events[$event]), $event . ' is listed');
	}

	foreach (array('add_shipping_method.php' => 'pg_shipping_method_save(', 'edit_shipping_method.php' => 'pg_shipping_method_delete(') as $file => $call) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains($call, $source, $file . ' writes through the shared function');
		pg_assert_false(strpos($source, 'INSERT INTO shipping_methods') !== false || strpos($source, 'DELETE FROM shipping_methods') !== false, $file . ' writes no shipping method row itself');
	}
}

// The module answers a failed write with 0 or false (the API then answers
// 500 in JSON, the screens output_error()), so no write's result is dropped.
function test_shipping_method_module_checks_every_write()
{
	foreach (array('includes/fn/shipping_methods.php', 'includes/fn/store_zones.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(0, preg_match_all('/^\s*db_q\(/m', $source), $file . ' leaves no db_q() write unchecked');
		pg_assert_true(preg_match_all('/(if \(!db_q\(|\$saved = db_q\()/', $source) >= 6, $file . ' checks its writes');
	}
}

