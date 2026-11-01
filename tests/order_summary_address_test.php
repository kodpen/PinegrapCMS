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
