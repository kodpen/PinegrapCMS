<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for country and state writes over the external API
 * (includes/api/resources/store_regions.php) and the write path the four
 * country and state screens share with it (includes/fn/store_regions.php):
 * the route rows, partial updates laid over the stored row, the screens'
 * checks before the write, and the clean-up on delete.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/store_regions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function api_store_regions_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function api_store_regions_test_body($relative, $name)
{
	$source = api_store_regions_test_source($relative);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_api_store_regions_routes()
{
	$expected = array(
		'store.countries.get'         => array('GET', '/store/countries/{id}', 'orders:read'),
		'store.countries.create'      => array('POST', '/store/countries', 'store:write'),
		'store.countries.update'      => array('POST', '/store/countries/{id}', 'store:write'),
		'store.countries.delete'      => array('DELETE', '/store/countries/{id}', 'store:write'),
		'store.countries.delete_post' => array('POST', '/store/countries/{id}/delete', 'store:write'),
		'store.states.get'            => array('GET', '/store/states/{id}', 'orders:read'),
		'store.states.create'         => array('POST', '/store/states', 'store:write'),
		'store.states.update'         => array('POST', '/store/states/{id}', 'store:write'),
		'store.states.delete'         => array('DELETE', '/store/states/{id}', 'store:write'),
		'store.states.delete_post'    => array('POST', '/store/states/{id}/delete', 'store:write'),
	);

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

		foreach ($route['params'] as $param) {
			if (in_array($param['name'], array('name', 'code', 'country_code'), true)) {
				pg_assert_true($param['max_length'] <= 50, $route['id'] . ' ' . $param['name'] . ' fits varchar(50)');
			}
		}
	}

	pg_assert_same(count($expected), count($found), 'every route is declared');
}

function test_api_store_country_values_lay_the_sent_fields_over_the_row()
{
	$row = array('name' => 'Türkiye', 'code' => 'TR', 'zip_code_required' => '1', 'transit_adjustment_days' => '2', 'default_selected' => '1');

	pg_assert_same(
		array('name' => 'Türkiye', 'code' => 'TR', 'zip_code_required' => 1, 'transit_adjustment_days' => 2, 'default_selected' => 1),
		api_store_country_values($row, array()),
		'nothing sent keeps the row'
	);

	$values = api_store_country_values($row, array('name' => '  Turkey ', 'default' => false, 'transit_adjustment_days' => 0));

	pg_assert_same('Turkey', $values['name'], 'trimmed like the screen');
	pg_assert_same(0, $values['default_selected']);
	pg_assert_same(0, $values['transit_adjustment_days']);
	pg_assert_same('TR', $values['code']);

	$values = api_store_country_values(null, array('name' => 'Malta', 'code' => 'MT'));

	pg_assert_same(array('name' => 'Malta', 'code' => 'MT', 'zip_code_required' => 0, 'transit_adjustment_days' => 0, 'default_selected' => 0), $values, 'a create starts from the empty form');
	pg_assert_same(array(), pg_country_form_required_errors(array_merge($values, array('transit_adjustment_days' => (string) $values['transit_adjustment_days']))));
	pg_assert_true(isset(pg_country_form_required_errors(api_store_country_values(null, array('name' => 'Malta')))['code']), 'a create without a code is refused');
}

function test_api_store_state_values_take_the_resolved_country()
{
	$row = array('name' => 'İstanbul', 'code' => '34', 'country_id' => '7');

	pg_assert_same(array('name' => 'İstanbul', 'code' => '34', 'country_id' => 7), api_store_state_values($row, array(), 0));
	pg_assert_same(9, api_store_state_values($row, array('country_code' => 'CY'), 9)['country_id'], 'a sent country moves it');
	pg_assert_same('Ankara', api_store_state_values($row, array('name' => ' Ankara'), 0)['name']);
}

// Each handler checks with the screen's helpers, stops for X-Dry-Run, then
// writes through the shared function.
function test_api_store_regions_handlers_check_stop_then_write()
{
	$file = 'includes/api/resources/store_regions.php';

	foreach (array('country', 'state') as $kind) {
		$prepare = api_store_regions_test_body($file, 'api_store_' . $kind . '_prepare');

		pg_assert_contains('pg_' . $kind . '_form_required_errors(', $prepare);
		pg_assert_contains('pg_' . $kind . '_form_code_error(', $prepare);
		pg_assert_contains('pg_store_' . $kind . '_save(', api_store_regions_test_body($file, 'api_store_' . $kind . '_commit'));
	}

	foreach (array('api_store_countries_create', 'api_store_countries_update', 'api_store_states_create', 'api_store_states_update') as $handler) {
		$body = api_store_regions_test_body($file, $handler);
		$prepare = strpos($body, '_prepare(');
		$stop = strpos($body, 'api_dry_run_stop(');
		$commit = strpos($body, '_commit(');

		pg_assert_true(($prepare !== false) && ($stop > $prepare) && ($commit > $stop), $handler . ': check, stop, write');
	}

	foreach (array('api_store_countries_delete' => 'pg_store_country_delete(', 'api_store_states_delete' => 'pg_store_state_delete(') as $handler => $write) {
		$body = api_store_regions_test_body($file, $handler);

		pg_assert_true(strpos($body, 'api_dry_run_stop(') < strpos($body, $write), $handler . ': stop, then delete');
	}

	pg_assert_false(strpos(api_store_regions_test_source($file), 'INSERT INTO'), 'no SQL write of its own');
}

function test_store_region_screens_write_through_the_shared_functions()
{
	$calls = array(
		'add_country.php'  => array('pg_store_country_save('),
		'edit_country.php' => array('pg_store_country_save(', 'pg_store_country_delete('),
		'add_state.php'    => array('pg_store_state_save('),
		'edit_state.php'   => array('pg_store_state_save(', 'pg_store_state_delete('),
	);

	foreach ($calls as $file => $needles) {
		$source = api_store_regions_test_source($file);

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $file);
		}

		pg_assert_false((bool) preg_match('/(INSERT INTO|UPDATE|DELETE FROM) (countries|states)\b/', $source), $file . ' writes no country or state row itself');
	}
}

// A country's delete takes its states with their zone places and verified
// addresses, then its own zone places; a state's the same for one state.
function test_store_region_deletes_clean_up_as_the_screens_did()
{
	$file = 'includes/fn/store_regions.php';
	$unlink = api_store_regions_test_body($file, 'pg_store_state_unlink');

	foreach (array('zones_states_xref', 'tax_zones_states_xref', 'verified_shipping_addresses') as $table) {
		pg_assert_contains("'" . $table . "'", $unlink);
	}

	$country = api_store_regions_test_body($file, 'pg_store_country_delete');

	pg_assert_contains('pg_store_state_unlink(', $country);

	foreach (array('states', 'zones_countries_xref', 'tax_zones_countries_xref') as $table) {
		pg_assert_contains("'" . $table . "'", $country);
	}

	pg_assert_contains('DELETE FROM countries', $country);
	pg_assert_contains('pg_store_state_unlink(', api_store_regions_test_body($file, 'pg_store_state_delete'));

	$save = api_store_regions_test_body($file, 'pg_store_country_save');

	pg_assert_contains('UPDATE countries SET default_selected = 0 WHERE id != ?', $save, 'one default country');
}
