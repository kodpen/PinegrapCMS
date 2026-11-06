<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's Visitor map card (includes/dashboard/v2/
 * cards/visitmap.php): the home country, the share from abroad, the bubbles
 * by province, and the countries drawn when no city can be placed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/visitmap.php');

function dashboard_v2_visitmap_list($visits, $rows)
{
	$list = array();

	foreach ($rows as $value => $n) {
		$list[] = array('value' => (string) $value, 'visits' => $n);
	}

	return array('totals' => array('visits' => $visits), 'rows' => $list);
}

function test_dashboard_v2_visitmap_is_built()
{
	$cards = pg_dashboard_v2_cards();

	pg_assert_true($cards['visitmap']['built'], 'built');
	pg_assert_same(2, $cards['visitmap']['period'], 'last 30 days by default');
}

// The home country is the one most visits came from; its visits are not
// abroad, visits without a country count in neither.
function test_dashboard_v2_visitmap_bubbles_by_province()
{
	$countries = dashboard_v2_visitmap_list(1300, array('TR' => 940, 'DE' => 40, 'tr' => 20, '' => 300));
	$cities = dashboard_v2_visitmap_list(1300, array('Istanbul' => 400, 'Ankara' => 200, 'Berlin' => 40, 'Kadikoy' => 10, '' => 650));
	$data = pg_dashboard_v2_visitmap_data($countries, $cities, 'Last 30 Days', '/m/', 'visitors.php?p=30d');

	pg_assert_same('1,300', $data['figure']);
	pg_assert_same('Last 30 Days · 1,300 visits · abroad 4%', $data['sub'], '40 of 1,000 placed visits');
	pg_assert_same('/m/tr-regions.svg', $data['map_url']);
	pg_assert_same(array('TR-34', 'TR-06'), array_column($data['bubbles'], 'code'), 'the places a province matches, most first');
	pg_assert_same(array(1.0, 0.5), array_map('floatval', array_column($data['bubbles'], 'share')));
	pg_assert_same('İstanbul: 400 visits', $data['bubbles'][0]['tip']);
	pg_assert_same('Cities', $data['rows_title']);
	pg_assert_same(array('Istanbul', 'Ankara'), array_column($data['rows'], 'text'));
	pg_assert_same('', $data['none']);
}

// No city matches a province (no Cloudflare): the countries are drawn.
function test_dashboard_v2_visitmap_countries_without_cities()
{
	$countries = dashboard_v2_visitmap_list(100, array('TR' => 60, 'DE' => 30, 'US' => 10));
	$cities = dashboard_v2_visitmap_list(100, array('' => 100));
	$data = pg_dashboard_v2_visitmap_data($countries, $cities, 'Today', '/m/', 'visitors.php?p=today');

	pg_assert_same('', $data['map_url']);
	pg_assert_same(array(), $data['bubbles']);
	pg_assert_same('Countries', $data['rows_title']);
	pg_assert_same(array(pg_geo_country_name('TR'), pg_geo_country_name('DE'), pg_geo_country_name('US')), array_column($data['rows'], 'text'), 'countries named in the panel language, as the rest of the panel names them');
	pg_assert_same('Today · 100 visits · abroad 40%', $data['sub']);
}

// A home country without a map of its own draws its countries.
function test_dashboard_v2_visitmap_home_without_a_map()
{
	$countries = dashboard_v2_visitmap_list(10, array('DE' => 9, 'TR' => 1));
	$cities = dashboard_v2_visitmap_list(10, array('Ankara' => 1, 'Berlin' => 9));
	$data = pg_dashboard_v2_visitmap_data($countries, $cities, 'Today', '/m/', '');

	pg_assert_same('', $data['map_url']);
	pg_assert_same('Countries', $data['rows_title']);
}

function test_dashboard_v2_visitmap_without_locations()
{
	$data = pg_dashboard_v2_visitmap_data(dashboard_v2_visitmap_list(5, array('' => 5)), dashboard_v2_visitmap_list(5, array('' => 5)), 'Today', '/m/', '');

	pg_assert_same('Today · 5 visits', $data['sub']);
	pg_assert_same(array(), $data['rows']);
	pg_assert_same('No visit in this period has a known location.', $data['none']);
}

function test_dashboard_v2_visitmap_empty()
{
	$data = pg_dashboard_v2_visitmap_data(dashboard_v2_visitmap_list(0, array()), dashboard_v2_visitmap_list(0, array()), 'Today', '/m/', '');

	pg_assert_same(array('empty' => 'No visits in this period yet.'), $data);
}
