<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cart in its market's currency (includes/fn/market_cart.php):
 * the cart's state, a line's price, an amount and a threshold at the rate,
 * an offer row and ladder, a member's price, the order row a visitor's
 * market asks for, and the wiring into the cart's writers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A cart in no market, or in a currency without a rate, is worked in base.
function test_market_cart_state()
{
	pg_assert_same(null, pg_market_cart_state_for(0, 'EUR', 0.03, 'none', 'TRY'), 'no market');
	pg_assert_same(null, pg_market_cart_state_for(2, 'EUR', 0, 'none', 'TRY'), 'no rate');
	pg_assert_same(null, pg_market_cart_state_for(2, 'EUR', 'x', 'none', 'TRY'), 'a rate that is not a number');

	$state = pg_market_cart_state_for(2, 'eur', '0.03', 'end99', 'TRY');
	pg_assert_same(array('market_id' => 2, 'code' => 'EUR', 'rate' => 0.03, 'rounding' => 'end99'), $state);

	// A market in the base currency rounds at rate 1, whatever the row says.
	$base = pg_market_cart_state_for(3, 'TRY', 0, 'whole', 'TRY');
	pg_assert_same(array('market_id' => 3, 'code' => '', 'rate' => 1.0, 'rounding' => 'whole'), $base);
	pg_assert_same(3, pg_market_cart_state_for(3, '', 0, 'bogus', 'TRY')['market_id'], 'no code is base');
	pg_assert_same('none', pg_market_cart_state_for(3, '', 0, 'bogus', 'TRY')['rounding'], 'an unknown rule is none');
}

// A product's price is converted and rounded by the market's rule; any
// other amount only at the rate, half away from zero; back to base divides.
function test_market_cart_conversions()
{
	$eur = pg_market_cart_state_for(2, 'EUR', 0.03, 'end99', 'TRY');
	$cent = pg_market_cart_state_for(2, 'EUR', 0.03, 'none', 'TRY');

	// 1,000.00 TRY at 0.03 = 30.00 EUR, then up to 30.99.
	pg_assert_same(3099, pg_market_cart_price_for($eur, 100000));
	pg_assert_same(3000, pg_market_cart_price_for($cent, 100000));
	pg_assert_same(100000, pg_market_cart_price_for(null, '100000'), 'no market: as it is');

	// A shipping rate of 45.50 TRY = 1.365 EUR -> 1.37, never by the rule.
	pg_assert_same(137, pg_market_cart_amount_for($eur, 4550));
	pg_assert_same(-137, pg_market_cart_amount_for($eur, -4550), 'half away from zero both ways');
	pg_assert_same(4550, pg_market_cart_amount_for(null, 4550));
	pg_assert_same(0, pg_market_cart_amount_for($eur, ''), 'nothing is 0');

	// 30.99 EUR is 1,033.00 TRY for a base band.
	pg_assert_same(103300, pg_market_cart_base_for($eur, 3099));
	pg_assert_same(3099, pg_market_cart_base_for(null, 3099));

	// A base market keeps the figure and rounds a price only.
	$base = pg_market_cart_state_for(3, '', 0, 'whole', 'TRY');
	pg_assert_same(1300, pg_market_cart_price_for($base, 1234));
	pg_assert_same(1234, pg_market_cart_amount_for($base, 1234));
}

// An offer row: list prices as a line, amounts and thresholds at the rate;
// a key missing or null (no product joined) stays.
function test_market_cart_offer_row()
{
	$eur = pg_market_cart_state_for(2, 'EUR', 0.03, 'end99', 'TRY');
	$row = array(
		'id' => '7',
		'product_price' => '100000',
		'add_product_price' => null,
		'discount_order_amount' => '5000',
		'discount_product_percentage' => '10',
		'required_subtotal' => '200000',
		'upsell_trigger_subtotal' => '1000',
		'price' => '3099',
	);
	$converted = pg_market_cart_offer_row_for($eur, $row);

	pg_assert_same(3099, $converted['product_price']);
	pg_assert_same(null, $converted['add_product_price'], 'no product joined');
	pg_assert_same(150, $converted['discount_order_amount']);
	pg_assert_same('10', $converted['discount_product_percentage'], 'a percentage stays');
	pg_assert_same(6000, $converted['required_subtotal']);
	pg_assert_same(30, $converted['upsell_trigger_subtotal']);
	pg_assert_same('3099', $converted['price'], 'a line price is the cart\'s already');
	pg_assert_same('7', $converted['id']);

	pg_assert_same($row, pg_market_cart_offer_row_for(null, $row), 'no market: as it is');
	pg_assert_same(null, pg_market_cart_offer_row_for($eur, null), 'no row');
}

// A ladder's rungs and a member's agreed prices are converted at the rate.
function test_market_cart_tiers_and_member_prices()
{
	$eur = pg_market_cart_state_for(2, 'EUR', 0.03, 'end99', 'TRY');
	$tiers = array(array('min' => 250000, 'percent' => 10), array('min' => 100000, 'percent' => 5));

	pg_assert_same(array(array('min' => 7500, 'percent' => 10), array('min' => 3000, 'percent' => 5)), pg_market_cart_tiers_for($eur, $tiers));
	pg_assert_same($tiers, pg_market_cart_tiers_for(null, $tiers));

	$member = array(12 => array('price' => 4550, 'source' => 'account'));
	pg_assert_same(array(12 => array('price' => 137, 'source' => 'account')), pg_market_cart_member_prices_for($eur, $member));
	pg_assert_same($member, pg_market_cart_member_prices_for(null, $member));
}

// The order row a visitor's market asks for: the market, the currency
// ('' for base) and base units for one unit.
function test_market_cart_target()
{
	pg_assert_same(array('market_id' => 2, 'currency' => 'EUR', 'rate' => '33.33333333'), pg_market_cart_target(2, 'EUR', '0.03', 'TRY'));
	pg_assert_same(array('market_id' => 3, 'currency' => '', 'rate' => '0.00000000'), pg_market_cart_target(3, 'TRY', 1, 'TRY'), 'a market in base');
	pg_assert_same(array('market_id' => 0, 'currency' => '', 'rate' => '0.00000000'), pg_market_cart_target(0, 'EUR', '0.03', 'TRY'), 'no market');
	pg_assert_same(0, pg_market_cart_target(2, 'EUR', 0, 'TRY')['market_id'], 'no rate: no market');
}

// The cart's writers go through the module.
function test_market_cart_wiring()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$ecommerce = file_get_contents($root . 'includes/fn/ecommerce.php');

	pg_assert_contains("\$price = pg_market_cart_price(\$product['price'], \$product['id']);", $ecommerce, 'a new line is priced in the market');
	pg_assert_contains(": pg_market_cart_price(\$order_items[\$key]['product_price'], \$order_items[\$key]['product_id']);", $ecommerce, 'the prices refresh in the market');
	pg_assert_contains("return pg_market_cart_tiers_for(pg_market_cart(), \$tiers);", $ecommerce, 'the ladder is in the cart currency');
	pg_assert_same(3, substr_count($ecommerce, 'pg_market_cart_member_prices(pg_erp_member_prices())'), 'member prices at the rate');

	foreach (array('shipping.php' => 2, 'shipping_method.php' => 1, 'get_shipping_method.php' => 2) as $file => $count) {
		pg_assert_same($count, substr_count(file_get_contents($root . $file), '$shipping_cost = pg_market_cart_amount($shipping_cost);'), $file . ': every copy of the shipping formula');
	}

	foreach (array('express_order.php' => 2, 'order_form.php' => 1, 'shopping_cart.php' => 2, 'cart_action.php' => 2) as $file => $count) {
		pg_assert_same($count, substr_count(file_get_contents($root . $file), 'pg_market_cart_typed_minor('), $file . ': a typed donation');
	}

	foreach (array('get_shopping_cart.php', 'get_express_order.php', 'get_shipping_method.php') as $file) {
		pg_assert_contains('pg_market_cart_display_begin()', file_get_contents($root . $file), $file . ' prints in the cart currency');
	}

	pg_assert_same(2, substr_count(file_get_contents($root . 'includes/fn/designer.php'), 'pg_market_cart_drawn(function ()'), 'both cart widgets');
	pg_assert_contains('pg_market_cart_seal(', file_get_contents($root . 'submit_order.php'), 'the order is placed at its rate');
}
