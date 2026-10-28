<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/bundles.php: the contents a form
 * hands over, which products may be components, the stock and the price a
 * bundle's components give it, and the discount typed as a percentage.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Ids and quantities become whole numbers; the bundle itself, empty rows and
// quantities below one are left out; a product named twice adds up.
function test_bundle_items_normalize()
{
	$items = array(
		array('product_id' => '7', 'quantity' => '2'),
		array('product_id' => 9, 'quantity' => 0),
		array('product_id' => 5, 'quantity' => 1),
		array('product_id' => 7, 'quantity' => 1),
		array('product_id' => 0, 'quantity' => 3),
		array('quantity' => 3),
		'junk',
	);

	pg_assert_same(
		array(array('product_id' => 7, 'quantity' => 3)),
		pg_bundle_items_normalize($items, 5));

	pg_assert_same(
		array(array('product_id' => 4, 'quantity' => 1)),
		pg_bundle_items_normalize(array(array('product_id' => 4))),
		'a missing quantity is one');

	pg_assert_same(
		array(array('product_id' => 4, 'quantity' => PG_BUNDLE_MAX_QUANTITY)),
		pg_bundle_items_normalize(array(array('product_id' => 4, 'quantity' => 100000))),
		'the quantity is capped');
}

// No more than PG_BUNDLE_MAX_ITEMS components, the first ones kept.
function test_bundle_items_normalize_caps_the_rows()
{
	$items = array();

	for ($id = 1; $id <= PG_BUNDLE_MAX_ITEMS + 5; $id++) {
		$items[] = array('product_id' => $id, 'quantity' => 1);
	}

	$list = pg_bundle_items_normalize($items);

	pg_assert_same(PG_BUNDLE_MAX_ITEMS, count($list));
	pg_assert_same(1, $list[0]['product_id']);
}

// A bundle, a donation, a gift card and a recurring product are refused.
function test_bundle_component_problem()
{
	pg_assert_same('', pg_bundle_component_problem(array('bundle' => '0', 'selection_type' => 'quantity', 'gift_card' => '0', 'recurring' => '0')));
	pg_assert_true(pg_bundle_component_problem(array('bundle' => '1')) !== '', 'no nesting');
	pg_assert_true(pg_bundle_component_problem(array('selection_type' => 'donation')) !== '', 'donation');
	pg_assert_true(pg_bundle_component_problem(array('gift_card' => '1')) !== '', 'gift card');
	pg_assert_true(pg_bundle_component_problem(array('recurring' => '1')) !== '', 'recurring');
}

// The least of floor(stock / quantity) over the tracked components.
function test_bundle_stock_least_component()
{
	$components = array(
		array('quantity' => 2, 'inventory' => '1', 'inventory_quantity' => '9', 'backorder' => '0'),
		array('quantity' => 1, 'inventory' => '1', 'inventory_quantity' => '6', 'backorder' => '0'),
		array('quantity' => 5, 'inventory' => '0', 'inventory_quantity' => '0', 'backorder' => '0'),
	);

	pg_assert_same(
		array('inventory' => 1, 'inventory_quantity' => 4, 'backorder_allowed' => false),
		pg_bundle_stock($components));
}

// Negative stock allows none; a component short of one bundle allows none.
function test_bundle_stock_short_and_negative()
{
	$short = array(array('quantity' => 3, 'inventory' => 1, 'inventory_quantity' => 2, 'backorder' => 0));
	$negative = array(array('quantity' => 1, 'inventory' => 1, 'inventory_quantity' => -4, 'backorder' => 0));

	pg_assert_same(0, pg_bundle_stock($short)['inventory_quantity']);
	pg_assert_same(0, pg_bundle_stock($negative)['inventory_quantity']);
}

// No tracked component: the bundle tracks no stock. No contents: tracked
// with none, so an empty bundle cannot be sold.
function test_bundle_stock_untracked_and_empty()
{
	$untracked = array(array('quantity' => 1, 'inventory' => '0', 'inventory_quantity' => '0', 'backorder' => '0'));

	pg_assert_same(0, pg_bundle_stock($untracked)['inventory']);
	pg_assert_same(array('inventory' => 1, 'inventory_quantity' => 0, 'backorder_allowed' => false), pg_bundle_stock(array()));
}

// Back orders only when every tracked component takes them.
function test_bundle_stock_backorder()
{
	$all = array(
		array('quantity' => 1, 'inventory' => 1, 'inventory_quantity' => 0, 'backorder' => 1),
		array('quantity' => 1, 'inventory' => 0, 'inventory_quantity' => 0, 'backorder' => 0),
	);
	$one_refuses = array(
		array('quantity' => 1, 'inventory' => 1, 'inventory_quantity' => 0, 'backorder' => 1),
		array('quantity' => 1, 'inventory' => 1, 'inventory_quantity' => 3, 'backorder' => 0),
	);

	pg_assert_true(pg_bundle_stock($all)['backorder_allowed'], 'an untracked component does not refuse');
	pg_assert_true(!pg_bundle_stock($one_refuses)['backorder_allowed']);
}

// A fixed price is the bundle's own; a sum adds the components and takes the
// discount off, rounded to the nearest minor unit.
function test_bundle_price()
{
	$components = array(
		array('price' => '1999', 'quantity' => '2'),
		array('price' => 501, 'quantity' => 1),
	);

	pg_assert_same(3000, pg_bundle_price('fixed', '3000', $components, 1000));
	pg_assert_same(4499, pg_bundle_price('sum', 0, $components, 0));
	pg_assert_same(4049, pg_bundle_price('sum', 0, $components, 1000), '4049.1 rounds down');
	pg_assert_same(3937, pg_bundle_price('sum', 0, $components, 1250), '3936.625 rounds up');
	pg_assert_same(0, pg_bundle_price('sum', 0, $components, 20000), 'the discount stops at 100%');
	pg_assert_same(0, pg_bundle_price('sum', 500, array(), 0), 'no contents, no price');
}

// A percentage typed with a dot or a comma; anything else is no discount.
function test_bundle_discount_bp_from_input()
{
	pg_assert_same(1250, pg_bundle_discount_bp_from_input('12.5'));
	pg_assert_same(1250, pg_bundle_discount_bp_from_input(' 12,5 '));
	pg_assert_same(10000, pg_bundle_discount_bp_from_input('100'));
	pg_assert_same(0, pg_bundle_discount_bp_from_input(''));
	pg_assert_same(0, pg_bundle_discount_bp_from_input('abc'));
	pg_assert_same(0, pg_bundle_discount_bp_from_input('-5'));
	pg_assert_same(0, pg_bundle_discount_bp_from_input('101'));
}

// A plain product's lines take its stock in order; a product that is not
// tracked, or takes back orders, is not limited.
function test_bundle_cart_allowance_plain_lines()
{
	$stock = array(
		7 => array('tracked' => true, 'quantity' => 5, 'backorder' => false),
		8 => array('tracked' => false, 'quantity' => 0, 'backorder' => false),
		9 => array('tracked' => true, 'quantity' => 0, 'backorder' => true),
	);
	$lines = array(
		array('id' => 1, 'product_id' => 7, 'quantity' => 3),
		array('id' => 2, 'product_id' => 7, 'quantity' => 3),
		array('id' => 3, 'product_id' => 7, 'quantity' => 1),
		array('id' => 4, 'product_id' => 8, 'quantity' => 4),
		array('id' => 5, 'product_id' => 9, 'quantity' => 2),
	);

	pg_assert_same(array(
		1 => array('allowed' => 3, 'unavailable' => false),
		2 => array('allowed' => 2, 'unavailable' => false),
		3 => array('allowed' => 0, 'unavailable' => false),
		4 => array('allowed' => 4, 'unavailable' => false),
		5 => array('allowed' => 2, 'unavailable' => false),
	), pg_bundle_cart_allowance($lines, $stock));
}

// A component bought alone and inside a bundle is counted once.
function test_bundle_cart_allowance_shared_component()
{
	$stock = array(
		7 => array('tracked' => true, 'quantity' => 5, 'backorder' => false),
		8 => array('tracked' => true, 'quantity' => 10, 'backorder' => false),
	);
	$bundle = array(array('product_id' => 7, 'quantity' => 2), array('product_id' => 8, 'quantity' => 1));
	$lines = array(
		array('id' => 1, 'product_id' => 7, 'quantity' => 2),
		array('id' => 2, 'product_id' => 50, 'quantity' => 3, 'components' => $bundle),
		array('id' => 3, 'product_id' => 8, 'quantity' => 10),
	);

	$answer = pg_bundle_cart_allowance($lines, $stock);

	pg_assert_same(2, $answer[1]['allowed']);
	pg_assert_same(1, $answer[2]['allowed'], '3 of 7 left after the plain line: one bundle of two');
	pg_assert_same(false, $answer[2]['unavailable']);
	pg_assert_same(9, $answer[3]['allowed'], 'the bundle took one of ten');
}

// A bundle its components cannot make, or with no contents, is unavailable.
function test_bundle_cart_allowance_unavailable_bundle()
{
	$stock = array(7 => array('tracked' => true, 'quantity' => 1, 'backorder' => false));
	$lines = array(
		array('id' => 1, 'product_id' => 50, 'quantity' => 1, 'components' => array(array('product_id' => 7, 'quantity' => 2))),
		array('id' => 2, 'product_id' => 51, 'quantity' => 1, 'components' => array()),
		array('id' => 3, 'product_id' => 52, 'quantity' => 2, 'components' => array(array('product_id' => 99, 'quantity' => 1))),
	);

	$answer = pg_bundle_cart_allowance($lines, $stock);

	pg_assert_same(array('allowed' => 0, 'unavailable' => true), $answer[1]);
	pg_assert_same(array('allowed' => 0, 'unavailable' => true), $answer[2], 'no contents');
	pg_assert_same(array('allowed' => 2, 'unavailable' => false), $answer[3], 'an untracked component sets no limit');
}
