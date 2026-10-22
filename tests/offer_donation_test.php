<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for donation lines in the offer engine (includes/fn/ecommerce.php):
 * a donation is the amount the visitor typed, not a product, so "the cheapest
 * item is free" does not pick it, no product discount takes it, it is not a
 * piece toward a "cart quantity" condition, and taking an offer's discount
 * off the cart does not reset its amount.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A cart line as pg_offer_cheapest_item_id() reads it.
function offer_donation_test_line($id, $price, $changes = array())
{
	return array_merge(array(
		'id' => (string) $id, 'price' => (string) $price, 'added_by_offer' => '0', 'product_selection_type' => 'checkbox',
	), $changes);
}

function test_offer_donation_cheapest_line_skips_the_donation()
{
	// Y x2 at 10,00 and a donation of 5,00: the donation is the cheapest row,
	// the cheapest item is Y.
	$lines = array(
		offer_donation_test_line(11, 1000),
		offer_donation_test_line(12, 500, array('product_selection_type' => 'donation')),
	);
	pg_assert_same(11, pg_offer_cheapest_line_id($lines), 'the donation is not the cheapest item');
}

function test_offer_donation_cheapest_line_rules()
{
	$lines = array(
		offer_donation_test_line(21, 700),
		offer_donation_test_line(22, 300, array('added_by_offer' => '1')),
		offer_donation_test_line(23, 0),
		offer_donation_test_line(24, 700),
	);
	pg_assert_same(21, pg_offer_cheapest_line_id($lines), 'a gift and a free line are skipped; a tie goes to the lowest id');
	pg_assert_same(21, pg_offer_cheapest_line_id(array_reverse($lines)), 'the order the rows come in does not move the tie');
	pg_assert_same(0, pg_offer_cheapest_line_id(array(offer_donation_test_line(31, 500, array('product_selection_type' => 'donation')))), 'a cart of donations only has no cheapest item');
	pg_assert_same(0, pg_offer_cheapest_line_id(array()), 'an empty cart');
}

function test_offer_donation_line_is_not_a_product_target()
{
	$donation = offer_donation_test_line(41, 500, array('product_id' => '9', 'product_selection_type' => 'donation'));
	$product = offer_donation_test_line(42, 1000, array('product_id' => '9'));
	$action = array('discount_product_target' => 'product', 'discount_product_product_id' => '9', 'discount_product_group_id' => '0');

	pg_assert_true(pg_offer_action_targets_item($action, $product), 'the named product is discounted');
	pg_assert_true(!pg_offer_action_targets_item($action, $donation), 'a donation is never discounted, even when its product is named');
}

function test_offer_donation_restore_keeps_the_typed_amount()
{
	// Taking a discount off puts back the list price (or the member's price);
	// a donation's product price is 0, so its line keeps its own amount.
	pg_assert_same(null, pg_offer_restored_line_price(array('product_id' => '9', 'product_price' => '0', 'product_selection_type' => 'donation'), array()), 'the donation keeps what the visitor typed');
	pg_assert_same('1000', pg_offer_restored_line_price(array('product_id' => '7', 'product_price' => '1000', 'product_selection_type' => 'checkbox'), array()), 'a product gets its list price back');
	pg_assert_same(800, pg_offer_restored_line_price(array('product_id' => '7', 'product_price' => '1000', 'product_selection_type' => 'checkbox'), array(7 => array('price' => 800))), 'a member gets the agreed price back');
}

function test_offer_donation_is_not_counted_toward_cart_quantity()
{
	// "Buy 3, cheapest free" with X 20,00, Y 10,00 and a 0,01 donation: the
	// donation must not be the third piece that frees Y.
	$lines = array(
		array('quantity' => '1', 'product_selection_type' => 'checkbox'),
		array('quantity' => '1', 'product_selection_type' => 'checkbox'),
		array('quantity' => '1', 'product_selection_type' => 'donation'),
	);
	pg_assert_same(2, pg_offer_counted_quantity($lines), 'a donation line is not a piece');
	pg_assert_same(5, pg_offer_counted_quantity(array(
		array('quantity' => '3', 'product_selection_type' => 'checkbox'),
		array('quantity' => '2', 'product_selection_type' => null),
	)), 'units of a line count, and a line whose product was deleted still counts');
	pg_assert_same(0, pg_offer_counted_quantity(array()), 'an empty cart');
}
