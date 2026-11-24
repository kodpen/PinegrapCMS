<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipping rate checks: the cart's Checkout button re-checks a
 * completed recipient's method with check_shipping_method() and prices it
 * with update_shipping_cost_for_ship_to(); the classic Shipping Method screen
 * starts every listed method from no zone rates; the destination's zones and
 * a product's zones match a state by its id, not by a code another country
 * may share. These paths need a database, so their wiring is pinned here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _shipping_rate_recheck_block()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/shopping_cart.php');
	$start = strpos($src, '/* begin: update completed ship tos');
	$end = strpos($src, '/* end: update completed ship tos */');
	pg_assert_true(($start !== false) && ($end !== false) && ($end > $start), 'the re-check block is found');
	return substr($src, $start, $end - $start);
}

// The Checkout button re-checks the chosen method as the shipping method
// screen does: address type, zone still served, forced method, cut-off.
function test_shipping_cart_recheck_uses_check_shipping_method()
{
	$block = _shipping_rate_recheck_block();
	pg_assert_contains('check_shipping_method(array(', $block, 'the shared method check');
	pg_assert_contains("'address_1' => \$address_1", $block, 'the address type is checked');
	pg_assert_true(strpos($block, 'FROM shipping_methods') === false, 'no method query of its own');
}

// The cost is the one update_shipping_cost_for_ship_to() works out from the
// stored zone, never a zone looked up again beside it.
function test_shipping_cart_recheck_prices_with_stored_zone()
{
	$block = _shipping_rate_recheck_block();
	pg_assert_contains('UPDATE ship_tos SET zone_id = ? WHERE id = ?', $block, 'the zone the method serves now is stored');
	pg_assert_contains('update_shipping_cost_for_ship_to($ship_to_id);', $block, 'the shared pricing');
	pg_assert_true(strpos($block, 'get_valid_zones_for_destination(') === false, 'no destination-only zone lookup');
	pg_assert_true(strpos($block, '$zone_base_rate') === false, 'no zone rate carried between recipients');
}

// A recipient with no active line does not inherit the previous recipient's
// destination answer.
function test_shipping_cart_recheck_resets_destination_answer()
{
	$block = _shipping_rate_recheck_block();
	$reset = strpos($block, '$order_items_are_valid_for_destination = true;');
	$loop = strpos($block, 'foreach ($order_items as $order_item)');
	pg_assert_true(($reset !== false) && ($loop !== false) && ($reset < $loop), 'set before each recipient\'s lines are read');
}

// Every method listed on the classic Shipping Method screen starts from no
// zone rates, as shipping_method.php charges a forced method.
function test_shipping_method_screen_resets_zone_rates_per_method()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/get_shipping_method.php');
	$parts = explode('foreach ($shipping_methods as $shipping_method_id) {', $src);
	$listing = 0;
	foreach ($parts as $i => $part) {
		if (($i == 0) || (strpos($part, '$shipping_cost += $zone_base_rate;') === false)) {
			continue;
		}
		$listing++;
		$body = substr($part, 0, strpos($part, '$shipping_cost += $zone_base_rate;'));
		foreach (array('$zone_base_rate', '$zone_primary_weight_rate', '$zone_secondary_weight_rate', '$zone_item_rate') as $name) {
			$reset = strpos($body, $name . ' = 0;');
			$zone_loop = strpos($body, 'foreach ($zones as $zone_id)');
			pg_assert_true(($reset !== false) && ($zone_loop !== false) && ($reset < $zone_loop), $name . ' reset in listing ' . $listing);
		}
	}
	pg_assert_same(2, $listing, 'both listings of the screen');
}

function _shipping_rate_function_source($name)
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	$start = strpos($src, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' is found');
	$end = strpos($src, "\nfunction ", $start + 10);
	return substr($src, $start, $end - $start);
}

// A zone matches the recipient's state by that state's id: Western
// Australia (WA) is not Washington (WA).
function test_shipping_destination_zones_match_state_by_id()
{
	$src = _shipping_rate_function_source('get_valid_zones_for_destination');
	pg_assert_contains('WHERE state_id = ?', $src, 'the state by id');
	pg_assert_true(strpos($src, 'WHERE states.code = ?') === false, 'never the code alone');
}

function test_shipping_product_destination_matches_state_by_id()
{
	$src = _shipping_rate_function_source('validate_product_for_destination');
	pg_assert_contains('WHERE (zone_id = ?) AND (state_id = ?)', $src, 'the state by id');
	pg_assert_true(strpos($src, 'AND (states.code = ?)') === false, 'never the code alone');
}
