<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the store lookup lists over the external API (arrival dates,
 * ship date adjustments, verified shipping addresses, referral sources)
 * and the write path the screens of the four lookup lists (arrival dates,
 * ship date adjustments, verified shipping addresses, referral sources)
 * share with it (includes/fn/store_lookups.php): the route rows and their
 * scopes, partial updates laid over the stored row, the screens' checks
 * before the write, the shipping cut-off rule.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/store_regions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/store_lookups.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/arrival_date_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ship_date_adjustment_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function api_store_lookups_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function api_store_lookups_test_body($relative, $name)
{
	$source = api_store_lookups_test_source($relative);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_api_store_lookups_routes()
{
	$expected = array();

	foreach (array('arrival-dates' => 'arrival_dates', 'ship-date-adjustments' => 'ship_date_adjustments', 'verified-addresses' => 'verified_addresses', 'referral-sources' => 'referral_sources') as $path => $key) {
		$expected['store.' . $key . '.list']        = array('GET', '/store/' . $path, 'orders:read');
		$expected['store.' . $key . '.get']         = array('GET', '/store/' . $path . '/{id}', 'orders:read');
		$expected['store.' . $key . '.create']      = array('POST', '/store/' . $path, 'store:write');
		$expected['store.' . $key . '.update']      = array('POST', '/store/' . $path . '/{id}', 'store:write');
		$expected['store.' . $key . '.delete']      = array('DELETE', '/store/' . $path . '/{id}', 'store:write');
		$expected['store.' . $key . '.delete_post'] = array('POST', '/store/' . $path . '/{id}/delete', 'store:write');
	}

	$found = array();

	foreach (api_schema() as $route) {
		if (!isset($expected[$route['id']])) {
			continue;
		}

		$found[] = $route['id'];
		$row = $expected[$route['id']];

		pg_assert_same($row[0], $route['method'], $route['id'] . ' method');
		pg_assert_same($row[1], $route['path'], $route['id'] . ' path');
		pg_assert_same($row[2], $route['scope'], $route['id'] . ' scope');
		pg_assert_true(function_exists($route['handler']), $route['handler'] . '() exists');

		if ($route['method'] !== 'GET') {
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		}
	}

	pg_assert_same(count($expected), count($found), 'every lookup route is declared');
}

// A text field's max_length fits its column (the non-strict session cuts
// the rest in silence).
function test_api_store_lookups_text_limits()
{
	$columns = array(
		'store.arrival_dates' => array('name' => 50, 'description' => 255, 'code' => 50),
		'store.ship_date_adjustments' => array('zip_code_prefix' => 3),
		'store.verified_addresses' => array('company' => 50, 'address_1' => 50, 'address_2' => 50, 'city' => 50, 'zip_code' => 50),
		'store.referral_sources' => array('name' => 50, 'code' => 50),
	);

	foreach (api_schema() as $route) {
		foreach ($columns as $prefix => $limits) {
			if (!in_array($route['id'], array($prefix . '.create', $prefix . '.update'), true)) {
				continue;
			}

			foreach ($route['params'] as $param) {
				if (isset($limits[$param['name']])) {
					pg_assert_same($limits[$param['name']], $param['max_length'] ?? null, $route['id'] . ' ' . $param['name'] . ' fits its column');
				}
			}
		}
	}
}

function test_api_store_arrival_date_cutoff_rule()
{
	$start = '2026-12-01 00:00:00';
	$end = '2026-12-20 23:59:59';

	pg_assert_same(array(2, '2026-12-20 17:30'), pg_arrival_date_form_cutoff('2', '2026-12-20T17:30', $start, $end, array()), 'a cut-off inside the dates');
	pg_assert_same(null, pg_arrival_date_form_cutoff('2', '2026-12-21T09:00', $start, $end, array()), 'after the end');
	pg_assert_same(null, pg_arrival_date_form_cutoff('2', '2026-12-10T09:00', $start, $end, array(2)), 'a method used twice');
	pg_assert_same(null, pg_arrival_date_form_cutoff('', '2026-12-10T09:00', $start, $end, array()), 'no method');
	pg_assert_same(null, pg_arrival_date_form_cutoff('2', 'soon', $start, $end, array()), 'not a date and time');
	pg_assert_same(null, pg_arrival_date_form_cutoff(array('2'), '2026-12-10T09:00', $start, $end, array()), 'a list as the method');
}

function test_api_store_referral_source_values()
{
	$source = api_store_referral_source_values(array('name' => 'Radio', 'code' => 'RAD', 'sort_order' => '3'), array('code' => ' FM '));
	pg_assert_same(array('name' => 'Radio', 'code' => 'FM', 'sort_order' => '3'), $source, 'only the code changes');
	pg_assert_same('', pg_referral_source_form_error($source, array()), 'the screen accepts it');
	pg_assert_true(pg_referral_source_form_error(api_store_referral_source_values(null, array('name' => 'X')), array()) !== '', 'a new source needs a code');
}

function test_api_store_arrival_date_post()
{
	$row = array('name' => 'Valentine', 'description' => '', 'code' => 'V', 'arrival_date' => '2027-02-14', 'status' => 'enabled', 'start_date' => '2027-02-01', 'end_date' => '2027-02-12', 'default_selected' => '1', 'sort_order' => '4', 'custom' => '0', 'custom_maximum_arrival_date' => '0000-00-00');

	$post = api_store_arrival_date_post($row, array('enabled' => false, 'end_date' => ' 2027-02-13 '));
	pg_assert_same('0', $post['status'], 'enabled false disables it');
	pg_assert_same('2027-02-13', $post['end_date'], 'only the end date changes');
	pg_assert_same('2027-02-01', $post['start_date'], 'the start date stays');
	pg_assert_same('1', $post['default_selected'], 'the default stays');
	pg_assert_same('', $post['custom_maximum_arrival_date'], 'the zero date reads as blank');
	pg_assert_same(array(), pg_arrival_date_form_errors($post), 'the screen accepts it');

	$new = api_store_arrival_date_post(null, array('name' => 'X', 'start_date' => '2027-03-01', 'end_date' => '2027-03-02', 'arrival_date' => '2027-03-05'));
	pg_assert_same('1', $new['status'], 'a new arrival date is enabled');
	pg_assert_same(array(), pg_arrival_date_form_errors($new), 'a minimal new arrival date passes');
	pg_assert_true(isset(pg_arrival_date_form_errors(api_store_arrival_date_post(null, array('name' => 'X')))['start_date']), 'a new arrival date needs its dates');

	$out = api_store_arrival_date_present($row + array('id' => '9'), array(array('shipping_method_id' => '2', 'date_and_time' => '2027-02-10 17:30:00')));
	pg_assert_same(null, $out['custom_maximum_arrival_date'], 'the zero date answers null');
	pg_assert_same(array(array('shipping_method_id' => 2, 'date_and_time' => '2027-02-10T17:30')), $out['shipping_cutoffs'], 'a cut-off answers in the form it is sent');
	pg_assert_true($out['enabled'] && $out['default'] && !$out['custom'], 'the switches answer as booleans');
}

function test_api_store_arrival_date_plain_text()
{
	pg_assert_true(api_store_arrival_date_plain_text('Arrives by Friday & on time'), 'plain text passes');
	pg_assert_false(api_store_arrival_date_plain_text('<img src=x onerror=alert(1)>'), 'markup is refused');
	pg_assert_false(api_store_arrival_date_plain_text('a > b'), 'a bare > is refused');
}

// A cut-off as the API sends it, from a stored one, is kept by the same
// rule: an update that moves the dates past a stored cut-off is caught.
function test_api_store_arrival_date_stored_cutoff_items()
{
	$items = api_store_arrival_date_stored_cutoff_items(array(array('shipping_method_id' => '2', 'date_and_time' => '2027-02-10 17:30:00')));
	pg_assert_same(array(array('shipping_method_id' => 2, 'date_and_time' => '2027-02-10T17:30')), $items, 'stored rows read back as sent items');
	pg_assert_same(array(2, '2027-02-10 17:30'), pg_arrival_date_form_cutoff($items[0]['shipping_method_id'], $items[0]['date_and_time'], '2027-02-01 00:00:00', '2027-02-12 23:59:59', array()), 'inside the dates');
	pg_assert_same(null, pg_arrival_date_form_cutoff($items[0]['shipping_method_id'], $items[0]['date_and_time'], '2027-02-11 00:00:00', '2027-02-12 23:59:59', array()), 'a start moved past it');
}

function test_api_store_ship_date_adjustment_values()
{
	$row = array('zip_code_prefix' => '341', 'shipping_method_id' => '4', 'adjustment' => '-2');

	pg_assert_same(array('zip_code_prefix' => '341', 'shipping_method_id' => 4, 'days' => '2', 'direction' => 'earlier'), api_store_ship_date_adjustment_values($row, array()), 'a stored negative adjustment reads as earlier');
	pg_assert_same(array('zip_code_prefix' => '341', 'shipping_method_id' => 4, 'days' => '5', 'direction' => 'earlier'), api_store_ship_date_adjustment_values($row, array('days' => 5)), 'only the days change, the direction stays');
	pg_assert_same(array('zip_code_prefix' => '060', 'shipping_method_id' => 0, 'days' => '', 'direction' => 'later'), api_store_ship_date_adjustment_values(null, array('zip_code_prefix' => ' 060 ')), 'a new adjustment moves later by default');
	pg_assert_same(-5, (int) pg_ship_date_adjustment_form_adjustment(5, 'earlier'), 'earlier is stored negative');
	pg_assert_same(array('id' => 7, 'zip_code_prefix' => '341', 'shipping_method_id' => 4, 'days' => 2, 'direction' => 'earlier', 'adjustment' => -2), api_store_ship_date_adjustment_present(array('id' => '7') + $row), 'the answer splits the signed adjustment');
}

function test_api_store_verified_address_values()
{
	$row = array('company' => 'Acme', 'address_1' => 'Main St 1', 'address_2' => '', 'city' => 'Kadıköy', 'state_id' => '34', 'zip_code' => '34710');

	$values = api_store_verified_address_values($row, array('city' => ' Üsküdar ', 'zip_code' => '34660'));
	pg_assert_same(array('company' => 'Acme', 'address_1' => 'Main St 1', 'address_2' => '', 'city' => 'Üsküdar', 'state_id' => '34', 'zip_code' => '34660'), $values, 'only the fields sent change');
	pg_assert_same('', api_store_verified_address_values(null, array('state_id' => 0))['state_id'], 'state 0 counts as no state');
	pg_assert_same(null, api_store_verified_address_present($row + array('id' => '3'))['state_code'], 'no state code without its join');
}

// Each write handler checks, stops for X-Dry-Run, then writes.
function test_api_store_lookups_check_stop_write()
{
	$file = 'includes/api/resources/store_lookups.php';

	foreach (array('arrival_dates' => 'arrival_date', 'ship_date_adjustments' => 'ship_date_adjustment', 'verified_addresses' => 'verified_address', 'referral_sources' => 'referral_source') as $plural => $one) {
		foreach (array('create', 'update') as $action) {
			$body = api_store_lookups_test_body($file, 'api_store_' . $plural . '_' . $action);
			$prepare = strpos($body, 'api_store_' . $one . '_prepare(');
			$stop = strpos($body, 'api_dry_run_stop(');
			$commit = strpos($body, 'api_store_' . $one . '_commit(');
			pg_assert_true(($prepare !== false) && ($prepare < $stop) && ($stop < $commit), $plural . ' ' . $action . ': check, stop, write');
		}

		$body = api_store_lookups_test_body($file, 'api_store_' . $plural . '_delete');
		pg_assert_true(strpos($body, 'api_dry_run_stop(') < strpos($body, 'pg_store_' . $one . '_delete('), $plural . ' delete: stop, then delete');
	}
}

// The screens write through the shared functions and keep no SQL of their
// own for the row.
function test_api_store_lookups_screens_share_the_write_path()
{
	$screens = array(
		'add_arrival_date.php' => array('pg_store_arrival_date_save(0, ', 'arrival_dates'),
		'edit_arrival_date.php' => array('pg_store_arrival_date_delete($id)', 'arrival_dates'),
		'add_ship_date_adjustment.php' => array('pg_store_ship_date_adjustment_save(0, ', 'ship_date_adjustments'),
		'edit_ship_date_adjustment.php' => array('pg_store_ship_date_adjustment_delete(', 'ship_date_adjustments'),
		'add_verified_shipping_address.php' => array('pg_store_verified_address_save(0, ', 'verified_shipping_addresses'),
		'edit_verified_shipping_address.php' => array('pg_store_verified_address_delete(', 'verified_shipping_addresses'),
		'add_referral_source.php' => array('pg_store_referral_source_save(0, ', 'referral_sources'),
		'edit_referral_source.php' => array('pg_store_referral_source_delete($id)', 'referral_sources'),
	);

	foreach ($screens as $file => $rule) {
		$source = api_store_lookups_test_source($file);
		pg_assert_contains($rule[0], $source, $file . ' calls the shared write');

		foreach (array('INSERT INTO ' . $rule[1], 'UPDATE ' . $rule[1], 'DELETE FROM ' . $rule[1]) as $sql) {
			pg_assert_false(strpos($source, $sql) !== false, $file . ' keeps no ' . $sql);
		}
	}

	// The edit screens write the row the page checked, not a posted id: a
	// post without one saved a new row.
	foreach (array('edit_ship_date_adjustment.php' => array('pg_store_ship_date_adjustment_save($id, ', 'pg_store_ship_date_adjustment_delete($id)'), 'edit_verified_shipping_address.php' => array('pg_store_verified_address_save($id, ', 'pg_store_verified_address_delete($id)')) as $file => $calls) {
		$source = api_store_lookups_test_source($file);

		foreach ($calls as $call) {
			pg_assert_contains($call, $source, $file . ' writes the checked id');
		}

		pg_assert_false(strpos($source, "get_field_value('id')") !== false, $file . ' never reads the posted id');
	}

	// Checkout prints the referral sources as options: name and code escaped.
	foreach (array('get_billing_information.php' => 2, 'get_express_order.php' => 2) as $file => $count) {
		pg_assert_same($count, preg_match_all('/\$referral_source_options\[h\(\$\w+\[\'name\'\]\)\] = h\(\$\w+\[\'code\'\]\);/', api_store_lookups_test_source($file)), $file . ' escapes the referral sources');
	}

	// The shipping page hands verified addresses to an inline script: every
	// text the API can write goes through escape_javascript().
	$shipping = api_store_lookups_test_source('get_shipping_address_and_arrival.php');

	foreach (array('company', 'address_1', 'address_2', 'city', 'state_code', 'zip_code', 'country_code') as $column) {
		pg_assert_contains('"' . $column . '": "\' . escape_javascript($verified_shipping_address[\'' . $column . '\']) . \'"', $shipping, 'the shipping page escapes ' . $column);
	}

	$edit = api_store_lookups_test_source('edit_arrival_date.php');
	pg_assert_contains('pg_store_arrival_date_save($id, ', $edit, 'the arrival date edit saves through the shared write');
	pg_assert_contains('pg_store_arrival_date_cutoffs_save(', api_store_lookups_test_body('includes/forms/arrival_date_form.php', 'pg_arrival_date_form_save_cutoffs'), 'the screen\'s cut-offs go through the shared write');
}

// Every query of the write path binds one value per placeholder.
function test_api_store_lookups_write_path_placeholders()
{
	$file = 'includes/fn/store_lookups.php';
	$counts = array(
		'pg_store_arrival_date_save' => array('UPDATE arrival_dates' => 13, 'INSERT INTO arrival_dates' => 12),
		'pg_store_ship_date_adjustment_save' => array('UPDATE ship_date_adjustments' => 5, 'INSERT INTO ship_date_adjustments' => 5),
		'pg_store_verified_address_save' => array('UPDATE verified_shipping_addresses' => 8, 'INSERT INTO verified_shipping_addresses' => 8),
		'pg_store_referral_source_save' => array('UPDATE referral_sources' => 5, 'INSERT INTO referral_sources' => 4),
	);

	foreach ($counts as $function => $queries) {
		$body = api_store_lookups_test_body($file, $function);

		foreach ($queries as $sql => $count) {
			$query = substr($body, strpos($body, $sql));
			$query = substr($query, 0, strpos($query, '", '));
			pg_assert_same($count, substr_count($query, '?'), $function . ': ' . $sql . ' placeholders');
		}
	}

	pg_assert_contains('DELETE FROM shipping_cutoffs', api_store_lookups_test_body($file, 'pg_store_arrival_date_delete'), 'an arrival date goes with its cut-offs');
	pg_assert_contains('default_selected = 0 WHERE id != ?', api_store_lookups_test_body($file, 'pg_store_arrival_date_save'), 'one default arrival date');
}
