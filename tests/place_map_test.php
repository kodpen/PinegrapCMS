<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the coordinates of a collection's places
 * (includes/fn/place_map.php, includes/geo/geocode/photon.php,
 * place_coordinates.php): which fields hold the address and the
 * coordinates, a found address filling both, an address not found, the
 * provider's "wait", the bulk bound and the count left, who may use it,
 * which providers may answer, and that nothing is asked without a press.
 * No provider is called and no database is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/geo/autocomplete/photon.php');
require_once(PG_FUNCTIONS_DIR . '/includes/geo/geocode/photon.php');

function place_map_test_fields()
{
	return array(
		array('id' => 11, 'name' => 'Name', 'type' => 'text box', 'kind' => ''),
		array('id' => 12, 'name' => 'Address', 'type' => 'text area', 'kind' => ''),
		array('id' => 13, 'name' => 'City', 'type' => 'text box', 'kind' => ''),
		array('id' => 14, 'name' => 'Latitude', 'type' => 'text box', 'kind' => 'number'),
		array('id' => 15, 'name' => 'Longitude', 'type' => 'text box', 'kind' => 'number'),
		array('id' => 16, 'name' => 'Phone', 'type' => 'text box', 'kind' => ''),
	);
}

function test_place_map_field_roles()
{
	$roles = pg_place_map_field_roles(place_map_test_fields());

	pg_assert_same(14, $roles['lat']);
	pg_assert_same(15, $roles['lng']);
	pg_assert_same(array(12, 13), $roles['address'], 'the address parts in form order');

	$turkish = pg_place_map_field_roles(array(
		array('id' => 1, 'name' => 'Adres', 'type' => 'text box', 'kind' => ''),
		array('id' => 2, 'name' => 'Enlem', 'type' => 'text box', 'kind' => 'number'),
		array('id' => 3, 'name' => 'Boylam', 'type' => 'text box', 'kind' => 'number'),
	));
	pg_assert_same(array('lat' => 2, 'lng' => 3, 'address' => array(1)), $turkish);
}

function test_place_map_field_roles_need_number_fields_and_an_address()
{
	$fields = place_map_test_fields();
	$fields[3]['kind'] = '';
	pg_assert_same(null, pg_place_map_field_roles($fields), 'a latitude that is plain text is not a coordinate');

	pg_assert_same(null, pg_place_map_field_roles(array_slice(place_map_test_fields(), 3, 2)), 'no address');
	pg_assert_same(null, pg_place_map_field_roles(array()));
}

// The Map of places block's chosen fields come first: fields the name rule
// does not know are taken, and a chosen field beats a name-rule field.
function test_place_map_field_roles_take_the_block_choice()
{
	$fields = array(
		array('id' => 21, 'name' => 'Location', 'type' => 'text area', 'kind' => ''),
		array('id' => 22, 'name' => 'Y Coord', 'type' => 'text box', 'kind' => 'number'),
		array('id' => 23, 'name' => 'X Coord', 'type' => 'text box', 'kind' => 'number'),
	);
	pg_assert_same(null, pg_place_map_field_roles($fields), 'no name matches');
	pg_assert_same(
		array('lat' => 22, 'lng' => 23, 'address' => array(21)),
		pg_place_map_field_roles($fields, array('address' => 'Location', 'lat' => 'Y Coord', 'lng' => 'X Coord'))
	);

	$both = array_merge(place_map_test_fields(), $fields);
	$roles = pg_place_map_field_roles($both, array('lat' => 'Y Coord', 'lng' => 'X Coord', 'address' => 'Location'));
	pg_assert_same(22, $roles['lat'], 'the chosen field, not Latitude');
	pg_assert_same(23, $roles['lng']);
	pg_assert_same(array(21), $roles['address'], 'a chosen address is the address alone');

	$roles = pg_place_map_field_roles($both, array('lat' => 'Y Coord'));
	pg_assert_same(array(22, 15, array(12, 13)), array($roles['lat'], $roles['lng'], $roles['address']), 'a part not chosen is found by name');

	pg_assert_same(pg_place_map_field_roles(place_map_test_fields()), pg_place_map_field_roles(place_map_test_fields(), array()), 'no block: the name rule');
}

// A chosen field no longer on the form, or one that does not fit the part,
// falls back to the name rule.
function test_place_map_field_roles_stale_choice_falls_back()
{
	$fields = place_map_test_fields();
	$roles = pg_place_map_field_roles($fields, array('lat' => 'Gone', 'lng' => 'Phone', 'address' => 'Removed'));
	pg_assert_same(array('lat' => 14, 'lng' => 15, 'address' => array(12, 13)), $roles);

	$fields[] = array('id' => 31, 'name' => 'Hidden lat', 'type' => 'text box', 'kind' => 'number', 'office' => 1);
	pg_assert_same(14, pg_place_map_field_roles($fields, array('lat' => 'Hidden lat'))['lat'], 'an office-use-only field is never chosen');
}

// Whose block wins for a form listed on several pages: the lowest page id
// whose first form list shows the form and whose block names a field.
function test_place_map_pick_choice()
{
	$pages = array(
		array('page_id' => 9, 'form' => 5, 'choice' => array('lat' => 'B')),
		array('page_id' => 4, 'form' => 7, 'choice' => array('lat' => 'Other form')),
		array('page_id' => 6, 'form' => 5, 'choice' => array('lat' => 'A')),
		array('page_id' => 2, 'form' => 5, 'choice' => array()),
	);
	pg_assert_same(array('lat' => 'A'), pg_place_map_pick_choice($pages, 5));
	pg_assert_same(array('lat' => 'Other form'), pg_place_map_pick_choice($pages, 7));
	pg_assert_same(array(), pg_place_map_pick_choice($pages, 8), 'no block lists it');
	pg_assert_same(array(), pg_place_map_pick_choice(array(), 5));
}

// A found address fills both fields, latitude second in GeoJSON.
function test_place_map_found_fills_both()
{
	$data = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/address_autocomplete/photon.json'), true);
	$result = pg_place_map_answer(array('ok' => true, 'status' => 200, 'data' => $data, 'error' => ''));

	pg_assert_same('found', $result['state']);
	pg_assert_same('40.98', $result['lat']);
	pg_assert_same('29.02', $result['lng']);
	pg_assert_same(array(40.98, 29.02), pg_geocode_photon_point($data));
}

function test_place_map_not_found_writes_nothing()
{
	$result = pg_place_map_answer(array('ok' => true, 'status' => 200, 'data' => array('type' => 'FeatureCollection', 'features' => array()), 'error' => ''));

	pg_assert_same('not_found', $result['state']);
	pg_assert_same('', $result['lat']);
	pg_assert_same('', $result['lng']);

	pg_assert_same(null, pg_geocode_photon_point(array('features' => array(array('geometry' => array('coordinates' => array(200, 95)))))), 'out of range');
	pg_assert_same(null, pg_geocode_photon_point(array('features' => array(array('geometry' => array('coordinates' => array('x', 'y')))))));
}

// The service's 429 and 503 are told as "wait", other failures as an error.
function test_place_map_wait_passed_on()
{
	pg_assert_same('wait', pg_place_map_answer(array('ok' => false, 'status' => 429, 'data' => null, 'error' => 'HTTP 429'))['state']);
	pg_assert_same('wait', pg_place_map_answer(array('ok' => false, 'status' => 503, 'data' => null, 'error' => 'HTTP 503'))['state'], 'the public server throttles with 503');
	pg_assert_same('error', pg_place_map_answer(array('ok' => false, 'status' => 502, 'data' => null, 'error' => 'HTTP 502'))['state']);
	pg_assert_same('error', pg_place_map_answer(array('ok' => false, 'status' => 0, 'data' => null, 'error' => 'No answer.'))['state']);
	pg_assert_same('error', pg_place_map_answer(array('ok' => false, 'status' => 500, 'data' => null, 'error' => 'HTTP 500'))['state']);
	pg_assert_contains('wait', pg_place_map_message(array('state' => 'wait', 'error' => '')));
}

// The bulk run pauses between requests to the public server only, never
// before the first.
function test_place_map_bulk_pause()
{
	pg_assert_same(0, pg_place_map_bulk_pause(PG_ADDR_PHOTON_HOST, 0), 'no pause before the first request');
	pg_assert_same(PG_PLACE_MAP_PUBLIC_PAUSE, pg_place_map_bulk_pause(PG_ADDR_PHOTON_HOST, 1));
	pg_assert_same(PG_PLACE_MAP_PUBLIC_PAUSE, pg_place_map_bulk_pause(PG_ADDR_PHOTON_HOST . '/', 5), 'a trailing slash is the same server');
	pg_assert_same(0, pg_place_map_bulk_pause('https://photon.example.com', 3), 'the site\'s own server is not paused for');
	pg_assert_true(PG_PLACE_MAP_PUBLIC_PAUSE * (PG_PLACE_MAP_BULK - 1) <= 10000000, 'the pauses of one press stay within ten seconds');
}

function test_place_map_bulk_bound_and_left()
{
	$plan = pg_place_map_bulk_plan(range(1, 25), PG_PLACE_MAP_BULK);

	pg_assert_same(range(1, 10), $plan['now']);
	pg_assert_same(15, $plan['left']);

	pg_assert_same(array('now' => array(7, 9), 'left' => 0), pg_place_map_bulk_plan(array(7, 9), PG_PLACE_MAP_BULK));
	pg_assert_same(10, PG_PLACE_MAP_BULK);

	$message = pg_place_map_bulk_message(array('found' => 8, 'not_found' => array(array('id' => 3), array('id' => 4)), 'left' => 15, 'state' => 'done', 'error' => ''));
	pg_assert_contains('8', $message);
	pg_assert_contains('15', $message);
}

// The record screens' rule: Manager and above, or the forms right on the folder.
function test_place_map_user_without_forms_right_refused()
{
	pg_assert_false(pg_place_map_may(array('role' => 3, 'manage_forms' => 0), true), 'no forms right');
	pg_assert_false(pg_place_map_may(array('role' => 3, 'manage_forms' => 1), false), 'no access to the folder');
	pg_assert_true(pg_place_map_may(array('role' => 3, 'manage_forms' => 1), true));
	pg_assert_true(pg_place_map_may(array('role' => 2, 'manage_forms' => 0), false), 'a Manager');
	pg_assert_false(pg_place_map_may(null, true));
}

// Photon always: the site's Photon server while its account is on, else the
// public instance, also while Google Places or Mapbox gives the suggestions.
function test_place_map_photon_always()
{
	pg_assert_same(PG_ADDR_PHOTON_HOST, pg_place_map_source(array('host' => '', 'enabled' => false))['host'], 'no Photon account: the public instance');
	pg_assert_same(PG_ADDR_PHOTON_HOST, pg_place_map_source(array('host' => 'https://geo.example.com', 'enabled' => false))['host'], 'an account switched off is not used');
	pg_assert_same('https://geo.example.com', pg_place_map_source(array('host' => 'https://geo.example.com', 'enabled' => true))['host']);
	pg_assert_contains('photon.komoot.io', pg_place_map_help(PG_ADDR_PHOTON_HOST));
}

// With Google Places on, the lookup still goes to Photon's address only.
function test_place_map_google_on_asks_photon_only()
{
	$photon = array('key' => '', 'key_stored' => false, 'host' => '', 'enabled' => false);
	$url = pg_geocode_photon_url('Moda Caddesi 12, Kadıköy', pg_place_map_source($photon)['app']);

	pg_assert_same(0, strpos($url, PG_ADDR_PHOTON_HOST . '/api/?'), $url);

	foreach (array('includes/fn/place_map.php', 'includes/geo/geocode/photon.php', 'place_coordinates.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_false((bool) preg_match('/googleapis|api\.mapbox|pg_addr_(google_places|mapbox)_|pg_addr_provider\(/', $source), $file . ' asks no other provider and does not follow the suggestions provider');
	}
}

function test_place_map_address_text_and_number()
{
	pg_assert_same('Moda Caddesi 12, Kadıköy', pg_place_map_address_text(array(12 => " Moda  Caddesi 12 ", 13 => 'Kadıköy', 16 => 'x'), array(12, 13)));
	pg_assert_same('Kadıköy', pg_place_map_address_text(array(12 => 'Kadıköy', 13 => 'Kadıköy'), array(12, 13)), 'a repeated part once');
	pg_assert_same('', pg_place_map_address_text(array(12 => array('x')), array(12)));
	pg_assert_same(PG_PLACE_MAP_ADDRESS_MAX, mb_strlen(pg_place_map_address_text(array(1 => str_repeat('a', 400)), array(1)), 'UTF-8'));

	pg_assert_same('40.987654', pg_place_map_number(40.9876543));
	pg_assert_same('-3.5', pg_place_map_number(-3.5));
	pg_assert_same('0', pg_place_map_number(-0.0000001));
}

// No lookup without a press: only the endpoints call it, and the form's and
// the list's scripts ask the server inside their click handlers.
function test_place_map_no_call_without_a_press()
{
	$callers = array();

	foreach (array_merge(glob(PG_FUNCTIONS_DIR . '/*.php'), glob(PG_FUNCTIONS_DIR . '/includes/*/*.php')) as $file) {
		if (preg_match('/\bpg_place_map_(lookup|bulk)\(/', (string) file_get_contents($file))) {
			$callers[] = basename($file);
		}
	}

	sort($callers);
	pg_assert_same(array('place_coordinates.php', 'place_list.php', 'place_map.php'), $callers);

	// The page's typed-address lookup (place_list.php) asks only for a POST
	// of places.php, which the page sends on a press of Find.
	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/place_list.php');
	pg_assert_same(1, preg_match_all('/\bpg_place_map_lookup\(\$/', $list), 'one call, in pg_place_list_find()');
	$places = (string) file_get_contents(PG_FUNCTIONS_DIR . '/places.php');
	pg_assert_true(strpos($places, 'pg_place_list_find(') > strpos($places, "if (\$method === 'POST')"), 'only for a POST');

	foreach (array(pg_place_map_form_script(), pg_place_map_list_script()) as $script) {
		pg_assert_true(strpos($script, 'fetch(') > strpos($script, 'addEventListener("click"'), 'asked only on a click');
	}
}

// The bulk report is the list's notice: the counts, then each address not found on its own line, escaped.
function test_place_map_bulk_notice_lines()
{
	$notice = pg_place_map_bulk_notice(array('found' => 1, 'left' => 0, 'state' => 'done', 'error' => '', 'not_found' => array(
		array('id' => 3, 'reference' => 'BR0003', 'address' => 'Nowhere 1'),
		array('id' => 4, 'reference' => 'BR0004', 'address' => '<b>x</b>'),
	)));

	pg_assert_same(3, substr_count($notice, '<br>'));
	pg_assert_contains('<br>BR0003: Nowhere 1<br>BR0004: &lt;b&gt;x&lt;/b&gt;', $notice);
}
