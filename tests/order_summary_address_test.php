<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the site's order documents: the Order Summary widget, its list
 * of recipients and the legal documents print the checkout's district before
 * the city and the country in the site's language; the checkout keeps the
 * recipient's requested arrival date; the Order Summary starter labels the
 * payment method and the requested arrival date.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_summary_city_line_prints_the_district_before_the_city()
{
	pg_assert_same('34710 Moda Kadıköy İstanbul', _pg_sw_list_order_city_line('34710', 'Kadıköy', 'İstanbul', 'Moda'), 'district');
	pg_assert_same('34710 Kadıköy İstanbul', _pg_sw_list_order_city_line('34710', 'Kadıköy', 'İstanbul', 'KADIKÖY'), 'district equal to the city');
	pg_assert_same('34710 Kadıköy İstanbul', _pg_sw_list_order_city_line('34710', 'Kadıköy', 'İstanbul', ''), 'no district');
	pg_assert_same('34710 Kadıköy İstanbul', _pg_sw_list_order_city_line('34710', 'Kadıköy', 'İstanbul'), 'no district argument');
}

function test_order_summary_recipient_row_prints_the_district()
{
	$row = pg_sw_list_order_shipment_row(array(
		'first_name' => 'Ada', 'last_name' => 'Kaya', 'address_1' => 'Sokak 1',
		'city' => 'Kadıköy', 'state' => 'İstanbul', 'zip_code' => '34710',
		'country' => 'Türkiye', 'district' => 'Moda',
	), null);

	pg_assert_same('Ada Kaya, Sokak 1, 34710 Moda Kadıköy İstanbul, Türkiye', $row['__shipping_full_address'], 'full address');
}

function test_order_summary_sources_read_the_district_and_the_site_country()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$widgets = (string) file_get_contents($root . 'includes/fn/widgets.php');
	$legal = (string) file_get_contents($root . 'includes/fn/legal_documents.php');

	pg_assert_true(substr_count($widgets, 'pg_ship_to_district_ready()') >= 2, 'widgets.php: both ship_tos queries read the district');
	pg_assert_true(strpos($legal, 'pg_ship_to_district_ready()') !== false, 'legal_documents.php: the district is read');
	pg_assert_true(strpos($legal, 'IFNULL(countries.name') === false, 'legal_documents.php: no English country name');
	pg_assert_true(strpos($legal, 'SELECT name FROM countries') === false, 'legal_documents.php: no English country name for the cart');
	pg_assert_true(strpos($legal, 'pg_geo_country_label_site(') !== false, 'legal_documents.php: site country label');
}

function test_checkout_custom_arrival_date_comes_from_the_ship_to_row()
{
	$row = array('arrival_date_id' => '2', 'arrival_date' => '2026-11-20');

	pg_assert_same('2026-11-20', _eo_custom_arrival_value('', $row, '2'), 'stored date of the chosen arrival choice');
	pg_assert_same('2026-11-25', _eo_custom_arrival_value('2026-11-25', $row, '2'), 'posted date wins');
	pg_assert_same('', _eo_custom_arrival_value('', $row, '3'), 'another arrival choice');
	pg_assert_same('', _eo_custom_arrival_value('', array('arrival_date_id' => '2', 'arrival_date' => '0000-00-00'), '2'), 'zero date');
	pg_assert_same('', _eo_custom_arrival_value('', array(), '2'), 'no stored row values');
}

function test_checkout_arrival_date_maximum_needs_a_real_date()
{
	pg_assert_same('2026-12-31', _eo_real_date('2026-12-31'), 'real date');
	pg_assert_same('', _eo_real_date('0000-00-00'), 'zero date');
	pg_assert_same('', _eo_real_date(''), 'empty');
	pg_assert_same('', _eo_real_date(null), 'null');
	pg_assert_same('', _eo_real_date('2026-02-30'), 'impossible date');
}

function test_checkout_arrival_date_error_is_translated_and_coded()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/shipping.php');

	pg_assert_true(strpos($source, "'message' => 'Sorry, the requested arrival date is not valid.'") === false, 'untranslated message');
	pg_assert_true(strpos($source, "'code' => 'arrival_date'") !== false, 'coded so the checkout keeps it out of the address notice');
	pg_assert_true(strpos($source, "\$properties['only_from_carrier'] ?? false") !== false, 'only_from_carrier read with a default');
	pg_assert_true(strpos($source, "\$properties['formatted'] ?? false") !== false, 'formatted read with a default');
}

function test_order_summary_starter_labels()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/style_designer.src.js');

	pg_assert_true(strpos($source, "text: _sdT('Method'), cssClass: 'text-muted small mb-1'") === false, 'payment card says Method');
	pg_assert_true(strpos($source, "text: _sdT('Estimated delivery'), cssClass: 'text-muted small mb-1'") === false, 'requested date labelled as an estimate');
	pg_assert_true(strpos($source, "customName: _sdT('Requested arrival date'),\n                                            _bindings: { eo_visible_if: 'has_arrival_date' }") !== false, 'arrival date row hidden without a date');
}
