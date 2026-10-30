<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_geo_country_label_stored() in includes/fn/geo.php: a country
 * stored on an order or an address (a code, or a name on older rows) named
 * in the current language, and its use in the order view's and the address
 * book's address blocks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A code in the countries map is named from its row, whatever its case.
function test_geo_country_label_stored_code_in_map()
{
	$map = array('TR' => 'Turkey', 'DE' => 'Germany');
	pg_assert_same(pg_geo_country_label('Turkey', 'TR'), pg_geo_country_label_stored('TR', $map));
	pg_assert_same(pg_geo_country_label('Turkey', 'TR'), pg_geo_country_label_stored(' tr ', $map));
	pg_assert_true(pg_geo_country_label_stored('DE', $map) !== 'DE', 'never the bare code');
}

// A code nobody knows stays the code; a stored name is named as a name.
function test_geo_country_label_stored_unknown_code_and_names()
{
	pg_assert_same('QQ', pg_geo_country_label_stored('QQ', array()));
	pg_assert_same(pg_geo_country_label('', 'FR') !== '' ? pg_geo_country_label('', 'FR') : 'FR', pg_geo_country_label_stored('FR', array()), 'a code outside the map');
	pg_assert_same('Nowhere Land', pg_geo_country_label_stored('Nowhere Land', array('TR' => 'Turkey')));
	pg_assert_same(pg_geo_country_label('Turkey'), pg_geo_country_label_stored('Turkey', array()));
	pg_assert_same('', pg_geo_country_label_stored('', array('TR' => 'Turkey')));
	pg_assert_same('', pg_geo_country_label_stored(null, array()));
}

// The order view names the country in its address blocks, keeping the
// country tokens as stored; the address book names its recipients' country.
function test_geo_country_label_stored_call_sites()
{
	$root = dirname(__DIR__) . '/pinegrap/includes/fn/';
	$widgets = file_get_contents($root . 'widgets.php');
	pg_assert_contains("pg_geo_country_label_site(\$order['billing_country'])", $widgets);
	pg_assert_contains("pg_geo_country_label_site(\$ship_to['country'] ?? '')", $widgets);
	pg_assert_contains("'__billing_country'    => h((string)\$order['billing_country'])", $widgets);
	pg_assert_contains("'__shipping_country'    => h((string)(\$ship_to['country']   ?? ''))", $widgets);
	$account = file_get_contents($root . 'widgets_account.php');
	pg_assert_contains("pg_geo_country_label(\$countries[(string)\$r['country']], (string)\$r['country'])", $account);
}
