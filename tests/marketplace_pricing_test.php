<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel price of a marketplace account
 * (includes/fn/marketplace_pricing.php): which rule a product takes, the
 * rounding endings, the profit floor from cost, VAT, commission, fee,
 * shipping and margin, and the price the rules give against the site's own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/marketplace_pricing.php');

// A rule row as the table keeps it.
function marketplace_pricing_test_rule($scope, $scope_id, $scope_key, $percent, $fixed = 0, $rounding = 'none')
{
	return array(
		'scope' => $scope, 'scope_id' => $scope_id, 'scope_key' => $scope_key,
		'percent' => $percent, 'fixed' => $fixed, 'rounding' => $rounding,
	);
}

// The deepest category wins over a shallower one and over the brand; the
// brand wins over the account's own row; no row at all is no rule.
function test_marketplace_pricing_rule_order()
{
	$account = marketplace_pricing_test_rule(MP_PRICING_SCOPE_ACCOUNT, 0, '', 500);
	$brand = marketplace_pricing_test_rule(MP_PRICING_SCOPE_BRAND, 0, 'acme', 1000);
	$top = marketplace_pricing_test_rule(MP_PRICING_SCOPE_CATEGORY, 3, '', 1500);
	$deep = marketplace_pricing_test_rule(MP_PRICING_SCOPE_CATEGORY, 7, '', 2000);

	$rules = array($account, $brand, $top, $deep);

	// The product sits in 7 (under 3) and in 9 (under 3 too).
	$chains = array(array(7, 3), array(9, 3));

	pg_assert_same(2000, mp_pricing_pick_rule($rules, $chains, 'Acme')['percent'], 'deepest category');
	pg_assert_same(1500, mp_pricing_pick_rule(array($account, $brand, $top), $chains, 'Acme')['percent'], 'parent category');
	pg_assert_same(1000, mp_pricing_pick_rule(array($account, $brand), $chains, ' ACME ')['percent'], 'brand, any case');
	pg_assert_same(500, mp_pricing_pick_rule(array($account, $brand), $chains, 'Other')['percent'], 'account');
	pg_assert_same(500, mp_pricing_pick_rule(array($account, $brand), array(), '')['percent'], 'no brand, no category');
	pg_assert_same(null, mp_pricing_pick_rule(array($brand, $top), array(array(5)), 'Other'), 'nothing matches');
	pg_assert_same(null, mp_pricing_pick_rule(array(), $chains, 'Acme'), 'no rules');
}

// A brand rule matches the way its column compares: leading and trailing
// spaces dropped, any case, on both the stored brand and the product's;
// inner spaces and other letters still differ.
function test_marketplace_pricing_brand_match_trims_and_ignores_case()
{
	$rule = marketplace_pricing_test_rule(MP_PRICING_SCOPE_BRAND, 0, '  Acme Tools ', 700);
	$account = marketplace_pricing_test_rule(MP_PRICING_SCOPE_ACCOUNT, 0, '', 100);

	foreach (array('Acme Tools', 'acme tools', ' ACME TOOLS  ', "\tAcme Tools\n") as $brand) {
		pg_assert_same(700, mp_pricing_pick_rule(array($account, $rule), array(), $brand)['percent'], $brand);
	}

	pg_assert_same(100, mp_pricing_pick_rule(array($account, $rule), array(), 'Acme  Tools')['percent'], 'inner spaces');
	pg_assert_same(100, mp_pricing_pick_rule(array($account, $rule), array(), 'Acme Tool')['percent'], 'another brand');
	pg_assert_same(100, mp_pricing_pick_rule(array($account, $rule), array(), '')['percent'], 'no brand');
	pg_assert_same('ğüşöç', mp_pricing_brand_key(' ĞÜŞÖÇ '), 'Turkish letters lower-cased');
}

// Two categories at the same depth: the lower id, so the pick never depends
// on the order the rows were read in.
function test_marketplace_pricing_rule_tie_takes_the_lower_category()
{
	$a = marketplace_pricing_test_rule(MP_PRICING_SCOPE_CATEGORY, 12, '', 100);
	$b = marketplace_pricing_test_rule(MP_PRICING_SCOPE_CATEGORY, 4, '', 200);

	pg_assert_same(200, mp_pricing_pick_rule(array($a, $b), array(array(12), array(4)), '')['percent']);
	pg_assert_same(200, mp_pricing_pick_rule(array($b, $a), array(array(4), array(12)), '')['percent']);
}

// Each ending goes to the nearest price that ends in it, a tie upwards.
function test_marketplace_pricing_rounding_endings()
{
	pg_assert_same(12345, mp_pricing_round(12345, 'none'));
	pg_assert_same(12300, mp_pricing_round(12345, 'whole'));
	pg_assert_same(12400, mp_pricing_round(12350, 'whole'));
	pg_assert_same(12390, mp_pricing_round(12345, '90'));
	pg_assert_same(12290, mp_pricing_round(12330, '90'));
	pg_assert_same(12390, mp_pricing_round(12340, '90'), 'a tie goes up');
	pg_assert_same(12299, mp_pricing_round(12340, '99'));
	pg_assert_same(12399, mp_pricing_round(12350, '99'));
	pg_assert_same(90, mp_pricing_round(10, '90'), 'never under zero');
	pg_assert_same(12345, mp_pricing_round(12345, 'unknown'), 'an unknown ending is none');
}

// Rounding up gives the first price at or over the amount with the ending.
function test_marketplace_pricing_round_up()
{
	pg_assert_same(12390, mp_pricing_round_up(12301, '90'));
	pg_assert_same(12290, mp_pricing_round_up(12290, '90'));
	pg_assert_same(12399, mp_pricing_round_up(12301, '99'));
	pg_assert_same(12400, mp_pricing_round_up(12301, 'whole'));
	pg_assert_same(12301, mp_pricing_round_up(12301, 'none'));
}

// The site price with VAT times (1 + percent) plus the fixed part, rounded.
function test_marketplace_pricing_rule_price()
{
	$rule = marketplace_pricing_test_rule(MP_PRICING_SCOPE_ACCOUNT, 0, '', 1250, 500, '90');

	// 100.00 + 12.5% = 112.50, + 5.00 = 117.50, nearest ,90: 117.90.
	pg_assert_same(11790, mp_pricing_rule_price(10000, $rule));

	// A cut: -10% of 99.99 is 9.999, rounded half away from zero to 10.00.
	pg_assert_same(8999, mp_pricing_rule_price(9999, marketplace_pricing_test_rule(0, 0, '', -1000)));

	// Never under zero.
	pg_assert_same(0, mp_pricing_rule_price(1000, marketplace_pricing_test_rule(0, 0, '', 0, -5000)));
}

// The floor with the listing's commission: 20% VAT, 15% commission on the
// price, a 3.00 fixed fee, 2.00 shipping, 50.00 cost, 10% margin.
function test_marketplace_pricing_floor_with_a_listing_commission()
{
	$floor = mp_pricing_floor(array(
		'cost' => 5000, 'vat_rate' => 20, 'commission_percent' => 15, 'commission_fixed' => 300,
		'fee_percent' => 0, 'fee_fixed' => 0, 'shipping' => 200, 'margin' => 1000,
	));

	pg_assert_true(is_int($floor), 'an integer');
	pg_assert_true(marketplace_pricing_test_floor_holds($floor, 20, 15, 300, 0, 0, 200, 5000, 1000), 'the floor keeps the margin');
	pg_assert_false(marketplace_pricing_test_floor_holds($floor - 2, 20, 15, 300, 0, 0, 200, 5000, 1000, 0), 'two minor units less do not');
}

// Without a listing commission the account's rate stands in (percent and
// fixed), and a payment fee raises the floor.
function test_marketplace_pricing_floor_without_a_listing_commission()
{
	$input = array(
		'cost' => 5000, 'vat_rate' => 20, 'commission_percent' => 0, 'commission_fixed' => 0,
		'fee_percent' => 0, 'fee_fixed' => 0, 'shipping' => 0, 'margin' => 1000,
	);

	$bare = mp_pricing_floor($input);

	// 50.00 at 10% of the net: the net is 55.56 (5556 x 0.9 = 5000.4), with
	// VAT 66.67.
	pg_assert_same(6667, $bare);

	$rates = mp_pricing_floor_rates(null, array('percent' => 12.5, 'fixed' => 150));
	pg_assert_same(12.5, $rates['commission_percent']);
	pg_assert_same(150, $rates['commission_fixed']);

	$with_rate = mp_pricing_floor(array_merge($input, $rates));
	pg_assert_true($with_rate > $bare, 'the account rate raises the floor');
	pg_assert_true(marketplace_pricing_test_floor_holds($with_rate, 20, 12.5, 150, 0, 0, 0, 5000, 1000));
	pg_assert_false(marketplace_pricing_test_floor_holds($with_rate - 2, 20, 12.5, 150, 0, 0, 0, 5000, 1000, 0));

	// The listing's own rate wins over the account's; a listing rate of 0 is
	// no rate.
	pg_assert_same(18.0, mp_pricing_floor_rates(18, array('percent' => 12.5, 'fixed' => 150))['commission_percent']);
	pg_assert_same(0, mp_pricing_floor_rates(18, array('percent' => 12.5, 'fixed' => 150))['commission_fixed']);
	pg_assert_same(12.5, mp_pricing_floor_rates(0, array('percent' => 12.5, 'fixed' => 150))['commission_percent']);
	pg_assert_same(0.0, mp_pricing_floor_rates(null, null)['commission_percent']);

	$with_fee = mp_pricing_floor(array_merge($input, array('fee_percent' => 2, 'fee_fixed' => 100)));
	pg_assert_true(marketplace_pricing_test_floor_holds($with_fee, 20, 0, 0, 2, 100, 0, 5000, 1000));
	pg_assert_false(marketplace_pricing_test_floor_holds($with_fee - 2, 20, 0, 0, 2, 100, 0, 5000, 1000, 0));
}

// No cost, or deductions that take the margin whatever the price: no floor.
function test_marketplace_pricing_floor_without_cost()
{
	$input = array('cost' => 0, 'vat_rate' => 20, 'commission_percent' => 15, 'margin' => 1000);

	pg_assert_same(null, mp_pricing_floor($input), 'no cost');
	pg_assert_same(null, mp_pricing_floor(array('cost' => null, 'vat_rate' => 20)), 'cost unknown');
	pg_assert_same(null, mp_pricing_floor(array('cost' => 5000, 'vat_rate' => 0, 'commission_percent' => 95, 'margin' => 1000)), 'cannot be reached');

	// The final floor: the higher of the two, a typed floor only raises it.
	pg_assert_same(null, mp_pricing_floor_final(null, 0));
	pg_assert_same(7000, mp_pricing_floor_final(null, 7000), 'typed alone');
	pg_assert_same(8000, mp_pricing_floor_final(8000, 7000), 'typed lower');
	pg_assert_same(9000, mp_pricing_floor_final(8000, 9000), 'typed higher');
}

// No rule: the site price as it is, floor or not. A rule under the floor
// moves to the next ending over it.
function test_marketplace_pricing_channel_price()
{
	pg_assert_same(10000, mp_pricing_channel_price(10000, null, 15000), 'no rule, the site price');

	$cut = marketplace_pricing_test_rule(MP_PRICING_SCOPE_ACCOUNT, 0, '', -2000, 0, '99');

	// 100.00 - 20% = 80.00, nearest ,99: 79.99; the floor 85.10 lifts it to 85.99.
	pg_assert_same(7999, mp_pricing_channel_price(10000, $cut, null));
	pg_assert_same(8599, mp_pricing_channel_price(10000, $cut, 8510));
	pg_assert_same(7999, mp_pricing_channel_price(10000, $cut, 7000), 'over the floor stays');

	// An empty rule changes nothing.
	pg_assert_same(10000, mp_pricing_channel_price(10000, marketplace_pricing_test_rule(0, 0, '', 0), null));
}

// The site price is an input only: the module writes nothing and the
// product row keeps its own price beside the channel price.
function test_marketplace_pricing_site_price_unchanged()
{
	$product = array('price' => 10000);
	$rule = marketplace_pricing_test_rule(MP_PRICING_SCOPE_ACCOUNT, 0, '', 1000);

	pg_assert_same(11000, mp_pricing_channel_price(10000, $rule, null));
	pg_assert_same(10000, $product['price']);
}

// The rounding endings the rules can name, and the reader of a stored one.
function test_marketplace_pricing_rounding_names()
{
	pg_assert_same(array('none', 'whole', '90', '99'), mp_pricing_roundings());
	pg_assert_same('99', mp_pricing_rounding(' 99 '));
	pg_assert_same('none', mp_pricing_rounding('95'));
}

// Whether a price keeps the margin, worked out the long way with floats: an
// independent check of mp_pricing_floor(). The module rounds each part to a
// minor unit, so the floor is allowed one; two minor units under it is
// checked with none.
function marketplace_pricing_test_floor_holds($price, $vat, $commission, $commission_fixed, $fee, $fee_fixed, $shipping, $cost, $margin, $tolerance = 1)
{
	$net = $price * 100 / (100 + $vat);
	$profit = $net - $price * ($commission + $fee) / 100 - $commission_fixed - $fee_fixed - $shipping - $cost;

	return $profit + $tolerance >= $net * $margin / 10000;
}
