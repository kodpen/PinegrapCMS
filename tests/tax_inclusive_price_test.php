<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the price a buyer pays for one unit (includes/fn/ecommerce.php
 * get_tax_inclusive_price()) and for the marketplace price built on it
 * (includes/api/outbound/connectors/base.php mp_product_price()): the shop
 * keeps prices without tax and the checkout adds the tax on top, so what a
 * marketplace is told as the sale price has to carry the same tax, by the
 * same formula, or the product sells cheaper over there by the tax.
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

// 79.95 at the zone's 18 per cent: 7995 + round(1439.1) = 9434, the amount
// the checkout collects for the same product (update_order_item_taxes()).
function test_tax_inclusive_price_adds_the_zone_rate_to_a_taxable_product()
{
	pg_assert_same(9434, get_tax_inclusive_price(7995, 1, null, 18.0, true));
}

function test_tax_inclusive_price_uses_the_products_own_rate_before_the_zones()
{
	pg_assert_same(12000, get_tax_inclusive_price(10000, 1, 20.0, 18.0, true));
	pg_assert_same(12000, get_tax_inclusive_price(10000, 1, '20.000', 18.0, true));
}

function test_tax_inclusive_price_zero_rated_product_is_sent_as_it_is()
{
	pg_assert_same(10000, get_tax_inclusive_price(10000, 1, 0.0, 18.0, true));
	pg_assert_same(10000, get_tax_inclusive_price(10000, 1, '0.000', 18.0, true));
}

function test_tax_inclusive_price_is_the_price_itself_when_the_shop_does_not_tax()
{
	pg_assert_same(7995, get_tax_inclusive_price(7995, 1, null, 18.0, false));
	pg_assert_same(7995, get_tax_inclusive_price(7995, 1, 20.0, 18.0, false));
}

function test_tax_inclusive_price_is_the_price_itself_for_an_untaxed_product()
{
	pg_assert_same(7995, get_tax_inclusive_price(7995, 0, null, 18.0, true));
	pg_assert_same(7995, get_tax_inclusive_price(7995, '0', 20.0, 18.0, true));
}

// The shop's country in no tax zone: the checkout charges no tax there
// whatever rate the product carries, and neither does the marketplace price.
function test_tax_inclusive_price_is_the_price_itself_outside_a_tax_zone()
{
	pg_assert_same(7995, get_tax_inclusive_price(7995, 1, null, false, true));
	pg_assert_same(10000, get_tax_inclusive_price(10000, 1, 20.0, false, true));
}

// Rounded, never truncated: 1 kuruş at 8.5 per cent is round(0.085) = 0,
// and 999 at 18 per cent is round(179.82) = 180, not 179.
function test_tax_inclusive_price_rounds_the_tax_like_the_checkout()
{
	pg_assert_same(1, get_tax_inclusive_price(1, 1, 8.5, 18.0, true));
	pg_assert_same(1179, get_tax_inclusive_price(999, 1, null, 18.0, true));
	pg_assert_same(0, get_tax_inclusive_price(0, 1, null, 18.0, true));
}

function test_tax_inclusive_price_answers_an_integer_in_minor_units()
{
	pg_assert_same(9434, get_tax_inclusive_price('7995', '1', null, '18.000', true));
}

// The test runner has no shop configuration, so ECOMMERCE_TAX is not defined
// and no zone can be resolved: the marketplace price is the shop price, which
// is also what a shop with tax switched off sends.
function test_tax_inclusive_price_marketplace_price_without_tax_is_the_shop_price()
{
	pg_assert_same(7995, mp_product_price(array('price' => '7995', 'taxable' => '1', 'tax_rate' => null)));
	pg_assert_same(79.95, mp_price_major(mp_product_price(array('price' => '7995', 'taxable' => '1', 'tax_rate' => '20.000'))));
}
