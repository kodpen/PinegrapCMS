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

// The service's 429 is told as "wait", other failures as an error.
function test_place_map_wait_passed_on()
{
	pg_assert_same('wait', pg_place_map_answer(array('ok' => false, 'status' => 429, 'data' => null, 'error' => 'HTTP 429'))['state']);
	pg_assert_same('error', pg_place_map_answer(array('ok' => false, 'status' => 500, 'data' => null, 'error' => 'HTTP 500'))['state']);
	pg_assert_contains('wait', pg_place_map_message(array('state' => 'wait', 'error' => '')));
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

// Photon only: Google's and Mapbox's results may be neither kept nor drawn on another map.
function test_place_map_photon_only()
{
	pg_assert_true(pg_place_map_source('')['ok'], 'no provider on: the public Photon instance');

	foreach (array('google_places', 'mapbox') as $provider) {
		$source = pg_place_map_source($provider);
		pg_assert_false($source['ok'], $provider);
		pg_assert_true($source['reason'] !== '', $provider . ' says why');
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

// No lookup without a press: only the endpoint calls it, and the form's and
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
	pg_assert_same(array('place_coordinates.php', 'place_map.php'), $callers);

	foreach (array(pg_place_map_form_script(), pg_place_map_list_script()) as $script) {
		pg_assert_true(strpos($script, 'fetch(') > strpos($script, 'addEventListener("click"'), 'asked only on a click');
	}
}
