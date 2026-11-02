<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Turkish place list (includes/geo/places/TR.json) and its
 * lookup (includes/fn/places.php): the file's shape, a district found in
 * its province however it is typed, the listed spelling written back, the
 * names the list does not hold, and the list the browser takes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// 81 provinces under their plate codes, named as the sales map names them,
// 973 districts with 51 "Merkez", none twice in one province.
function test_tr_place_list_file_shape()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

	$pack = pg_places_pack('TR');
	$regions = pg_sales_map_tr_regions();

	pg_assert_same(81, count($pack));
	pg_assert_same(array_keys($regions), array_keys($pack));

	$total = 0;
	$merkez = 0;

	foreach ($pack as $code => $place) {
		pg_assert_same($regions[$code], $place['name']);
		pg_assert_same(count($place['districts']), count(array_unique(array_map('pg_place_key', $place['districts']))));
		$total += count($place['districts']);
		$merkez += count(array_keys($place['districts'], 'Merkez', true));
	}

	pg_assert_same(973, $total);
	pg_assert_same(51, $merkez);
	pg_assert_same(array('TR'), pg_places_countries());
	pg_assert_same(array(), pg_places_pack('DE'));
}

// Content that is not a list reads as none; empty names are dropped.
function test_tr_place_list_parse()
{
	pg_assert_same(array(), pg_places_parse('nope'));
	pg_assert_same(array(), pg_places_parse('{"places": 3}'));
	pg_assert_same(
		array('TR-99' => array('name' => 'X', 'districts' => array('A'))),
		pg_places_parse('{"places": {"TR-99": {"name": " X ", "districts": ["A", "", 4]}, "TR-98": {"name": "Y"}}}'));
}

// The province of a state box: by plate number first, else by name with the
// older spellings.
function test_tr_place_list_province()
{
	pg_assert_same('TR-06', pg_place_tr_province('', '6'));
	pg_assert_same('TR-34', pg_place_tr_province('Ankara', '34'));
	pg_assert_same('TR-03', pg_place_tr_province('Afyon'));
	pg_assert_same('TR-33', pg_place_tr_province('İçel', '99'));
	pg_assert_same('', pg_place_tr_province('Alabama'));
}

// A district typed in any case or without Turkish letters is found in its
// province and given back in the listed spelling.
function test_tr_place_list_district_spelling()
{
	$pack = pg_places_pack('TR');

	pg_assert_same(array('known' => true, 'name' => 'Kadıköy', 'province' => 'TR-34'), pg_place_district($pack, 'TR-34', 'kadikoy'));
	pg_assert_same('Kadıköy', pg_place_district($pack, 'TR-34', 'KADIKÖY')['name']);
	pg_assert_same('Şişli', pg_place_district($pack, 'TR-34', ' sisli ')['name']);
	pg_assert_same('Eyüpsultan', pg_place_district($pack, 'TR-34', 'Eyüp Sultan')['name']);
	pg_assert_same('Iğdır', $pack['TR-76']['name']);
	pg_assert_same('Merkez', pg_place_district($pack, 'TR-76', 'MERKEZ')['name']);
	pg_assert_same('19 Mayıs', pg_place_district($pack, 'TR-55', '19 mayis')['name']);
}

// A district of another province, or a name no province has, is not listed
// in the province given.
function test_tr_place_list_district_not_listed()
{
	$pack = pg_places_pack('TR');

	pg_assert_same(array('known' => false, 'name' => '', 'province' => 'TR-34'), pg_place_district($pack, 'TR-34', 'Çankaya'));
	pg_assert_same(false, pg_place_district($pack, 'TR-34', 'Moda')['known']);
	pg_assert_same(false, pg_place_district($pack, 'TR-34', '')['known']);
	pg_assert_same(false, pg_place_district(array(), 'TR-34', 'Kadıköy')['known']);
	// Istanbul has no central district.
	pg_assert_same(false, pg_place_district($pack, 'TR-34', 'İstanbul')['known']);
}

// Without a province any province's district counts; a name of several
// provinces keeps no province.
function test_tr_place_list_district_without_province()
{
	$pack = pg_places_pack('TR');

	pg_assert_same(array('known' => true, 'name' => 'Kadıköy', 'province' => 'TR-34'), pg_place_district($pack, '', 'kadıkoy'));
	pg_assert_same(array('known' => true, 'name' => 'Gölbaşı', 'province' => ''), pg_place_district($pack, '', 'golbasi'));
	pg_assert_same(false, pg_place_district($pack, '', 'Atlantis')['known']);
}

// The province's own name typed as the district of a province with a
// "Merkez" counts as listed and is kept as typed.
function test_tr_place_list_province_name_as_district()
{
	$pack = pg_places_pack('TR');

	pg_assert_same(array('known' => true, 'name' => '', 'province' => 'TR-02'), pg_place_district($pack, 'TR-02', 'Adıyaman'));
	pg_assert_same(true, pg_place_district($pack, 'TR-03', 'Afyon')['known']);
}

// The save path: a Turkish city box in its listed spelling, any other
// address as typed; the note only for a Turkish district not listed.
function test_tr_place_list_city_fix_and_note()
{
	pg_assert_same('Kadıköy', pg_place_city_fix('TR', 'İstanbul', 'kadikoy'));
	pg_assert_same('Kadıköy', pg_place_city_fix('Türkiye', 'ISTANBUL', 'KADIKOY'));
	pg_assert_same('Moda', pg_place_city_fix('TR', 'İstanbul', 'Moda'));
	pg_assert_same('kadikoy', pg_place_city_fix('DE', 'Berlin', 'kadikoy'));
	pg_assert_same('', pg_place_city_fix('TR', 'İstanbul', ''));

	pg_assert_same('', pg_place_district_note('TR', 'İstanbul', 'Kadıköy'));
	pg_assert_same('', pg_place_district_note('US', 'NY', 'Moda'));
	pg_assert_same('', pg_place_district_note('TR', 'İstanbul', ''));
	pg_assert_true(strpos(pg_place_district_note('TR', 'İstanbul', 'Moda'), 'İstanbul') !== false);
	pg_assert_true(pg_place_district_note('TR', '', 'Atlantis') !== '');
}

// A posted form's city box is rewritten only when the spelling changes.
function test_tr_place_list_liveform_fix()
{
	$form = new class {
		public $values = array('shipping_country' => 'TR', 'shipping_state' => 'Ankara', 'shipping_city' => 'cankaya');
		public $assigned = 0;
		public function get_field_value($field) { return isset($this->values[$field]) ? $this->values[$field] : ''; }
		public function assign_field_value($field, $value) { $this->values[$field] = $value; $this->assigned++; }
	};

	pg_place_liveform_fix($form, 'shipping_');
	pg_assert_same('Çankaya', $form->values['shipping_city']);
	pg_place_liveform_fix($form, 'shipping_');
	pg_assert_same(1, $form->assigned);
}

// The browser's list: per plate number the province, the shop's states row
// (matched by plate code or name) and the districts joined by "|".
function test_tr_place_list_client_list()
{
	$list = pg_places_client_list(pg_places_pack('TR'), array(
		array('code' => 'İstanbul', 'name' => 'İstanbul', 'plate_code' => '34'),
		array('code' => 'AF', 'name' => 'Afyon', 'plate_code' => ''),
		array('code' => 'X', 'name' => 'Nowhere', 'plate_code' => '')));

	pg_assert_same(81, count($list));
	pg_assert_same(array('n' => 'İstanbul', 'c' => 'İstanbul', 's' => 'İstanbul'), array_diff_key($list['34'], array('d' => 1)));
	pg_assert_true(strpos($list['34']['d'], '|Kadıköy|') !== false);
	pg_assert_same('AF', $list['03']['c']);
	pg_assert_same('Afyon', $list['03']['s']);
	pg_assert_same('', $list['06']['c']);
}

// The suggester rides the district script, and the four save paths and the
// order screen call the lookup.
function test_tr_place_list_wiring()
{
	$read = function ($file) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	};

	pg_assert_true(strpos($read('includes/fn/shipping.php'), 'pg_places_suggest_script()') !== false);
	pg_assert_same(2, substr_count($read('express_order.php'), 'pg_place_liveform_fix('));
	pg_assert_true(strpos($read('billing_information.php'), "pg_place_liveform_fix(\$liveform, 'billing_')") !== false);
	pg_assert_true(strpos($read('shipping_address_and_arrival.php'), "pg_place_liveform_fix(\$liveform, '')") !== false);
	pg_assert_same(2, substr_count($read('view_order.php'), 'pg_place_district_note('));
}
