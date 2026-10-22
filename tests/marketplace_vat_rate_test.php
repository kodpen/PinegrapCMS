<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the VAT rate a marketplace listing declares
 * (includes/api/outbound/connectors/base.php mp_applied_vat_rate(), and the
 * Trendyol and n11 drivers' mp_trendyol_vat_rate() / mp_n11_vat_rate()).
 *
 * The declared rate is the rate actually inside the price that is sent
 * (mp_product_price()): Trendyol and n11 work the VAT back out of the sale
 * price, so a rate the price does not carry turns part of the seller's net
 * into VAT. On a shop with tax switched off the stored price is final and the
 * product's own rate, or the zone's, is declared as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/n11.php');

// A listing item as mp_listing_items() builds it, with the rate applied by a
// shop in the given tax state.
function marketplace_vat_rate_test_item($taxable, $product_rate, $zone_rate, $tax_enabled)
{
	return array(
		'taxable'  => (bool) $taxable,
		'tax_rate' => $product_rate,
		'vat_rate' => mp_applied_vat_rate($taxable, $product_rate, $zone_rate, $tax_enabled)
	);
}

// Tax on, but the shop's country is in no tax zone: the checkout adds
// nothing, so 100.00 goes out as 100.00 and its VAT is 0, not the product's
// own 20 per cent (which would leave the seller 83.33).
function test_marketplace_vat_rate_is_zero_outside_a_tax_zone()
{
	pg_assert_same(0.0, mp_applied_vat_rate(1, 20.0, false, true));
	pg_assert_same(10000, get_tax_inclusive_price(10000, 1, 20.0, false, true));

	$item = marketplace_vat_rate_test_item(1, 20.0, false, true);

	pg_assert_same(0, mp_trendyol_vat_rate($item));
	pg_assert_same(0, mp_n11_vat_rate($item));
}

// The drivers declare the rate the item carries, whatever its own rate says.
function test_marketplace_vat_rate_drivers_follow_the_applied_rate()
{
	$item = array('taxable' => true, 'tax_rate' => 20.0, 'vat_rate' => 0.0);

	pg_assert_same(0, mp_trendyol_vat_rate($item));
	pg_assert_same(0, mp_n11_vat_rate($item));
}

// Tax off: the stored price is the final price, VAT inside, so nothing is
// applied here (null) and the product's own rate is declared, also for a
// product whose Taxable switch is off.
function test_marketplace_vat_rate_tax_off_keeps_the_products_rate()
{
	pg_assert_same(null, mp_applied_vat_rate('0', 20.0, 18.0, false));
	pg_assert_same(null, mp_applied_vat_rate(1, 20.0, 18.0, false));

	$item = marketplace_vat_rate_test_item('0', 20.0, 18.0, false);

	pg_assert_same(20, mp_trendyol_vat_rate($item));
	pg_assert_same(20, mp_n11_vat_rate($item));

	// An item built without the key (by hand) resolves the same way.
	pg_assert_same(20, mp_trendyol_vat_rate(array('taxable' => false, 'tax_rate' => 20.0)));
	pg_assert_same(20, mp_n11_vat_rate(array('taxable' => false, 'tax_rate' => 20.0)));
}

// Tax on and the product not taxable: its price carries no tax.
function test_marketplace_vat_rate_tax_on_untaxed_product_is_zero()
{
	pg_assert_same(0.0, mp_applied_vat_rate('0', 20.0, 18.0, true));

	$item = marketplace_vat_rate_test_item('0', 20.0, 18.0, true);

	pg_assert_same(0, mp_trendyol_vat_rate($item));
	pg_assert_same(0, mp_n11_vat_rate($item));
}

// Tax on, zone 18, no rate of its own: 18 is inside the price and is
// declared as the nearest of their rates below it.
function test_marketplace_vat_rate_zone_rate_rounds_down()
{
	pg_assert_same(18.0, mp_applied_vat_rate(1, null, 18.0, true));
	pg_assert_same(20.0, mp_applied_vat_rate(1, '20.000', 18.0, true));

	$item = marketplace_vat_rate_test_item(1, null, 18.0, true);

	pg_assert_same(10, mp_trendyol_vat_rate($item));
	pg_assert_same(10, mp_n11_vat_rate($item));
}

// The declared rate is the one the price was built with: for every case the
// price equals the stored price plus that rate (nothing when none applies).
function test_marketplace_vat_rate_agrees_with_the_price()
{
	$cases = array(
		array(1, 20.0, false, true),
		array(1, null, 18.0, true),
		array(1, '8.500', 18.0, true),
		array(1, 0.0, 18.0, true),
		array('0', 20.0, 18.0, true),
		array(1, 20.0, 18.0, false),
		array('0', null, false, false)
	);

	foreach ($cases as $index => $case) {
		$rate = mp_applied_vat_rate($case[0], $case[1], $case[2], $case[3]);
		$expected = 7995 + (int) round((($rate === null) ? 0.0 : $rate) / 100 * 7995);

		pg_assert_same($expected, get_tax_inclusive_price(7995, $case[0], $case[1], $case[2], $case[3]), 'case ' . $index);
	}
}

// The test runner has no shop configuration, so ECOMMERCE_TAX is not
// defined: a row is read as a tax-off shop's.
function test_marketplace_vat_rate_product_without_tax_configuration_is_null()
{
	pg_assert_same(null, mp_product_vat_rate(array('price' => '7995', 'taxable' => '1', 'tax_rate' => '20.000')));
}
