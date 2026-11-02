<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for address autocomplete (includes/fn/address_autocomplete.php) and
 * its drivers (includes/geo/autocomplete/): each provider's answer mapped
 * from a fixture to the one address shape, the boxes a Turkish address
 * fills, the input clean-up and what the settings card decides. No provider
 * is called and no database is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

foreach (array('google_places', 'mapbox', 'photon') as $addr_test_driver) {
	require_once(PG_FUNCTIONS_DIR . '/includes/geo/autocomplete/' . $addr_test_driver . '.php');
}

function addr_test_fixture($name)
{
	return json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/address_autocomplete/' . $name . '.json'), true);
}

function addr_test_before()
{
	$apps = array();

	foreach (array_keys(pg_addr_providers()) as $provider) {
		$apps[$provider] = array('key' => '', 'key_stored' => false, 'host' => '', 'enabled' => false);
	}

	return $apps;
}

// Google: place predictions only, a malformed place id dropped.
function test_address_autocomplete_google_suggestions()
{
	$items = pg_addr_google_places_items(addr_test_fixture('google_autocomplete'));

	pg_assert_same(1, count($items));
	pg_assert_same('ChIJ7cv00DwsDogRAMDACa2m4K8', $items[0]['id']);
	pg_assert_same('Moda Caddesi 12, Kadıköy/İstanbul, Türkiye', $items[0]['label']);
	pg_assert_same(null, $items[0]['address'], 'Place Details fills it');
	pg_assert_same(array(), pg_addr_google_places_items(null));
}

// Google Turkey: the İlçe is administrative_area_level_2.
function test_address_autocomplete_google_details_tr()
{
	$address = pg_addr_google_places_address(addr_test_fixture('google_details_tr'));

	pg_assert_same('Moda Caddesi 12', $address['street']);
	pg_assert_same('Kadıköy', $address['district']);
	pg_assert_same('Kadıköy', $address['city'], 'no locality: the district');
	pg_assert_same('İstanbul', $address['state']);
	pg_assert_same('34710', $address['postcode']);
	pg_assert_same('TR', $address['country']);
}

// Google elsewhere: number first in the US, the subpremise as street 2.
function test_address_autocomplete_google_details_us()
{
	$address = pg_addr_google_places_address(addr_test_fixture('google_details_us'));

	pg_assert_same('1600 Amphitheatre Parkway', $address['street']);
	pg_assert_same('Suite 4', $address['street_2']);
	pg_assert_same('Mountain View', $address['city']);
	pg_assert_same('', $address['district']);
	pg_assert_same('California', $address['state']);
	pg_assert_same('CA', $address['state_code']);
	pg_assert_same('US', $address['country']);
}

// Mapbox: addresses with a street only, the place the İlçe in Turkey.
function test_address_autocomplete_mapbox_items()
{
	$items = pg_addr_mapbox_items(addr_test_fixture('mapbox_forward'));

	pg_assert_same(2, count($items), 'the place feature has no street');
	pg_assert_same('Hauptstraße 5, 10827 Berlin, Germany', $items[0]['label']);
	pg_assert_same('Hauptstraße 5', $items[0]['address']['street']);
	pg_assert_same('Berlin', $items[0]['address']['city']);
	pg_assert_same('Schöneberg', $items[0]['address']['district']);
	pg_assert_same('BE', $items[0]['address']['state_code']);
	pg_assert_same('DE', $items[0]['address']['country']);

	pg_assert_same('Bağdat Caddesi 100', $items[1]['address']['street']);
	pg_assert_same('Kadıköy', $items[1]['address']['district']);
	pg_assert_same('İstanbul', $items[1]['address']['state']);
}

// Photon: the country filtered here, repeated street segments dropped, the
// county the İlçe in Turkey.
function test_address_autocomplete_photon_items()
{
	$items = pg_addr_photon_items(addr_test_fixture('photon'), 'TR');

	pg_assert_same(2, count($items));
	pg_assert_same('Moda Caddesi', $items[0]['address']['street']);
	pg_assert_same('Kadıköy', $items[0]['address']['district']);
	pg_assert_same('Moda Caddesi 12', $items[1]['address']['street']);
	pg_assert_same('Moda Caddesi 12, Kadıköy, İstanbul, 34710, TR', $items[1]['label']);

	$all = pg_addr_photon_items(addr_test_fixture('photon'), '');
	pg_assert_same(3, count($all));
	pg_assert_same('12 Rue de Moda', $all[2]['address']['street']);
	pg_assert_same('Paris', $all[2]['address']['city']);
}

function test_address_autocomplete_photon_host()
{
	pg_assert_same(PG_ADDR_PHOTON_HOST, pg_addr_photon_host(''));
	pg_assert_same(PG_ADDR_PHOTON_HOST, pg_addr_photon_host('http://photon.example.com'), 'https only');
	pg_assert_same(PG_ADDR_PHOTON_HOST, pg_addr_photon_host('https://a.example.com/?x=1'));
	pg_assert_same('https://geo.example.com:2322/photon', pg_addr_photon_host('https://geo.example.com:2322/photon/'));
}

// The labels-by-country rule: a Turkish pick writes the İlçe into the city box.
function test_address_autocomplete_boxes_tr()
{
	$boxes = pg_addr_boxes(pg_addr_google_places_address(addr_test_fixture('google_details_tr')));

	pg_assert_same('Moda Caddesi 12', $boxes['address_1']);
	pg_assert_same('Kadıköy', $boxes['city']);
	pg_assert_same('İstanbul', $boxes['state']);
	pg_assert_same('34710', $boxes['zip_code']);
	pg_assert_same('TR', $boxes['country']);

	$other = pg_addr_boxes(array('city' => 'Berlin', 'district' => 'Schöneberg', 'country' => 'de'));
	pg_assert_same('Berlin', $other['city'], 'elsewhere the city');
	pg_assert_same('DE', $other['country']);

	$tr_city_only = pg_addr_boxes(array('city' => 'Ankara', 'country' => 'TR'));
	pg_assert_same('Ankara', $tr_city_only['city'], 'no district: the city');
}

function test_address_autocomplete_street_line()
{
	pg_assert_same('Moda Caddesi 12', pg_addr_street_line('Moda Caddesi', '12', 'TR'));
	pg_assert_same('10 Downing Street', pg_addr_street_line('Downing Street', '10', 'gb'));
	pg_assert_same('Main Street', pg_addr_street_line('Main Street', '', 'US'));
	pg_assert_same('', pg_addr_street_line('', '5', 'US'), 'a number alone is no street');
}

function test_address_autocomplete_input()
{
	pg_assert_same('', pg_addr_text('ab'), 'too short');
	pg_assert_same('', pg_addr_text(array('Moda')));
	pg_assert_same('', pg_addr_text("Mod\xC3"), 'not UTF-8');
	pg_assert_same('Moda Cd 12', pg_addr_text("  Moda\tCd\n12 "));
	pg_assert_same(200, mb_strlen(pg_addr_text(str_repeat('ş', 300)), 'UTF-8'));

	pg_assert_same('TR', pg_addr_country(' tr '));
	pg_assert_same('', pg_addr_country('Turkey'));
	pg_assert_same('', pg_addr_country(null));

	pg_assert_same('tr', pg_addr_lang('tr-TR'));
	pg_assert_same('de', pg_addr_lang('DE'));
	pg_assert_same('en', pg_addr_lang('x"><'));
}

// The browser gets ids and labels only; the session keeps the addresses.
function test_address_autocomplete_items_split()
{
	$split = pg_addr_items_split(array(
		array('id' => 'ChIJ7cv00DwsDogRAMDACa2m4K8', 'label' => 'A', 'address' => null),
		array('id' => '', 'label' => 'B', 'address' => array('street' => 'Moda Caddesi')),
	));

	pg_assert_same(2, count($split['items']));
	pg_assert_same(array('id', 'label'), array_keys($split['items'][1]));
	pg_assert_same(null, $split['store']['ChIJ7cv00DwsDogRAMDACa2m4K8']);
	pg_assert_same('Moda Caddesi', $split['store'][$split['items'][1]['id']]['street']);

	$many = array_fill(0, 9, array('id' => '', 'label' => 'x', 'address' => array()));
	pg_assert_same(PG_ADDR_SUGGESTIONS, count(pg_addr_items_split($many)['items']));
}

function test_address_autocomplete_http_answer()
{
	$ok = pg_addr_http_answer(array('ok' => true, 'status' => 200, 'body' => '{"features":[]}', 'error' => ''));
	pg_assert_true($ok['ok']);

	$google = pg_addr_http_answer(array('ok' => false, 'status' => 403, 'body' => '{"error":{"code":403,"message":"API key not valid."}}', 'error' => ''));
	pg_assert_false($google['ok']);
	pg_assert_true($google['refused']);
	pg_assert_same('API key not valid.', $google['error']);

	$mapbox = pg_addr_http_answer(array('ok' => false, 'status' => 401, 'body' => '{"message":"Not Authorized - Invalid Token"}', 'error' => ''));
	pg_assert_same('Not Authorized - Invalid Token', $mapbox['error']);

	$down = pg_addr_http_answer(array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'Operation timed out'));
	pg_assert_false($down['refused']);
	pg_assert_same('Operation timed out', $down['error']);
}

function test_address_autocomplete_settings_decide()
{
	$before = addr_test_before();

	pg_assert_same(null, pg_addr_settings_decide(array(), $before, '', ''), 'no card');
	pg_assert_same(null, pg_addr_settings_decide(array('addr_provider' => 'nope'), $before, '', ''));

	// On with no key anywhere: the key is marked.
	$d = pg_addr_settings_decide(array('addr_provider' => 'google_places', 'addr_enabled' => '1', 'addr_google_places_key' => ''), $before, '', '');
	pg_assert_true(isset($d['marks']['addr_google_places_key']));

	// A stored key is kept by an empty box.
	$stored = $before;
	$stored['google_places'] = array('key' => 'K', 'key_stored' => true, 'host' => '', 'enabled' => true);
	$d = pg_addr_settings_decide(array('addr_provider' => 'google_places', 'addr_enabled' => '1', 'addr_google_places_key' => ''), $stored, 'google_places', '');
	pg_assert_same(array(), $d['marks']);
	pg_assert_same('none', $d['action'], 'nothing changed');

	// Photon needs no key; a wrong host is marked.
	$d = pg_addr_settings_decide(array('addr_provider' => 'photon', 'addr_enabled' => '1', 'addr_photon_host' => ''), $before, '', '');
	pg_assert_same(array(), $d['marks']);
	pg_assert_same('save', $d['action']);

	$d = pg_addr_settings_decide(array('addr_provider' => 'photon', 'addr_enabled' => '1', 'addr_photon_host' => 'http://x'), $before, '', '');
	pg_assert_true(isset($d['marks']['addr_photon_host']));

	// A change that cannot be kept is refused on the switch.
	$d = pg_addr_settings_decide(array('addr_provider' => 'photon', 'addr_enabled' => '1'), $before, '', 'No key.');
	pg_assert_same('No key.', $d['marks']['addr_enabled']);

	// The test needs text and the switch on.
	$d = pg_addr_settings_decide(array('addr_provider' => 'photon', 'addr_enabled' => '1', 'addr_test' => '1', 'addr_test_text' => 'a'), $before, '', '');
	pg_assert_true(isset($d['marks']['addr_test_text']));

	$d = pg_addr_settings_decide(array('addr_provider' => 'photon', 'addr_test' => '1', 'addr_test_text' => 'Moda Caddesi'), $before, '', '');
	pg_assert_true(isset($d['marks']['addr_enabled']));
	pg_assert_same('Moda Caddesi', $d['test']);
}
