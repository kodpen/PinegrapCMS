<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the packing slip's addresses: the recipient's district
 * before the city, the country's name rather than its stored code, and no
 * ", " before an empty state for the recipient and the shop's own address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function packing_slip_address_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/print_packing_slip.php');
}

function test_packing_slip_address_district_read_behind_guard()
{
	$source = packing_slip_address_source();

	pg_assert_contains('(pg_ship_to_district_ready() ? "ship_tos.district," : "")', $source);
	pg_assert_contains("\$ship_to['district']", $source);
}

function test_packing_slip_address_country_named()
{
	$source = packing_slip_address_source();

	pg_assert_contains("h(pg_order_view_country(\$ship_to['country']))", $source);
	pg_assert_contains('h(pg_order_view_country(ORGANIZATION_COUNTRY))', $source);
	pg_assert_true(strpos($source, "h(\$ship_to['country'])") === false, 'ship-to country printed as its code');
}

function test_packing_slip_address_no_comma_before_empty_state()
{
	$source = packing_slip_address_source();

	pg_assert_true(strpos($source, "h(\$ship_to['city']) . ', ' . h(\$ship_to['state'])") === false, 'recipient: comma before an empty state');
	pg_assert_true(strpos($source, "h(ORGANIZATION_CITY) . ', ' . h(ORGANIZATION_STATE)") === false, 'organization: comma before an empty state');
}
