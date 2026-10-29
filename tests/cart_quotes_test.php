<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure rules of cart quotes (includes/fn/cart_quotes.php):
 * the shape and the kept parts of a link's token, where a quote stands, the
 * lines a form or the API may keep, and what the link puts into the cart
 * against the catalogue as it is now (gone, disabled, donation, stock,
 * minimum and maximum).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function cart_quotes_test_product(array $over = array())
{
	return array_merge(array(
		'enabled' => '1', 'selection_type' => 'quantity', 'inventory' => '0', 'inventory_quantity' => '0',
		'backorder' => '0', 'minimum_quantity' => '0', 'maximum_quantity' => '0',
	), $over);
}

function test_cart_quote_token_shape_is_a_new_short_link_token()
{
	pg_assert_true(pg_cart_quote_token_shape(pg_short_link_new_token()), 'a new token');
	pg_assert_true(!pg_cart_quote_token_shape(substr(pg_short_link_new_token(), 0, 42)), 'one character short');
	pg_assert_true(!pg_cart_quote_token_shape(str_repeat('a', 42) . '='), 'a padding character');
	pg_assert_true(!pg_cart_quote_token_shape(array(str_repeat('a', 43))), 'a list');
	pg_assert_true(!pg_cart_quote_token_shape(''), 'nothing');
}

function test_cart_quote_token_parts_keep_a_hash_and_a_short_hint()
{
	$token = str_repeat('Ab-_', 10) . 'xyz';
	$parts = pg_cart_quote_token_parts($token);

	pg_assert_same(hash('sha256', $token), $parts['hash']);
	pg_assert_same(64, strlen($parts['hash']));
	pg_assert_same('Ab-_Ab', $parts['hint']);
	pg_assert_true(strlen($parts['hint']) <= 8, 'fits token_hint VARCHAR(8)');
}

function test_cart_quote_state_expires_an_open_quote_only()
{
	$now = 1760000000;

	pg_assert_same('open', pg_cart_quote_state(array('status' => 'open', 'valid_until' => 0), $now), 'no end');
	pg_assert_same('open', pg_cart_quote_state(array('status' => 'open', 'valid_until' => $now), $now), 'the last second');
	pg_assert_same('expired', pg_cart_quote_state(array('status' => 'open', 'valid_until' => $now - 1), $now), 'past its end');
	pg_assert_same('ordered', pg_cart_quote_state(array('status' => 'ordered', 'valid_until' => $now - 1), $now), 'ordered stays ordered');
	pg_assert_same('cancelled', pg_cart_quote_state(array('status' => 'cancelled', 'valid_until' => 0), $now), 'cancelled');
	pg_assert_same('expired', pg_cart_quote_state(array('status' => 'expired', 'valid_until' => 0), $now), 'stored expired');
	pg_assert_same('cancelled', pg_cart_quote_state(array('status' => 'odd', 'valid_until' => 0), $now), 'an unknown status opens nothing');
}

function test_cart_quote_lines_clean_adds_up_a_repeated_product()
{
	$result = pg_cart_quote_lines_clean(array(
		array('product_id' => '7', 'quantity' => '2'),
		array('product_id' => 9, 'quantity' => 1),
		array('product_id' => 7, 'quantity' => 3),
	));

	pg_assert_same('', $result['problem']);
	pg_assert_same(array(array('product_id' => 7, 'quantity' => 5), array('product_id' => 9, 'quantity' => 1)), $result['lines']);
}

function test_cart_quote_lines_clean_refuses_what_is_no_line()
{
	pg_assert_same('empty', pg_cart_quote_lines_clean(array())['problem'], 'no lines');
	pg_assert_same('empty', pg_cart_quote_lines_clean('7')['problem'], 'not a list');
	pg_assert_same('quantity', pg_cart_quote_lines_clean(array(array('product_id' => 7, 'quantity' => 0)))['problem'], 'zero');
	pg_assert_same('quantity', pg_cart_quote_lines_clean(array(array('product_id' => 7, 'quantity' => '-2')))['problem'], 'negative');
	pg_assert_same('quantity', pg_cart_quote_lines_clean(array(array('product_id' => 7, 'quantity' => '1.5')))['problem'], 'a fraction');
	pg_assert_same('quantity', pg_cart_quote_lines_clean(array(array('product_id' => 0, 'quantity' => 1)))['problem'], 'no product');
	pg_assert_same('quantity', pg_cart_quote_lines_clean(array(array('product_id' => array(7), 'quantity' => 1)))['problem'], 'a product list');
	pg_assert_same('quantity', pg_cart_quote_lines_clean(array('7'))['problem'], 'a row that is not a row');
}

function test_cart_quote_lines_clean_caps_lines_and_quantities()
{
	$many = array();

	for ($i = 1; $i <= PG_CART_QUOTE_MAX_LINES + 1; $i++) {
		$many[] = array('product_id' => $i, 'quantity' => 1);
	}

	pg_assert_same('many', pg_cart_quote_lines_clean($many)['problem'], 'one line too many');
	pg_assert_same('', pg_cart_quote_lines_clean(array_slice($many, 0, PG_CART_QUOTE_MAX_LINES))['problem'], 'the most lines');

	$big = pg_cart_quote_lines_clean(array(array('product_id' => 3, 'quantity' => PG_CART_QUOTE_MAX_QUANTITY), array('product_id' => 3, 'quantity' => 5)));

	pg_assert_same(PG_CART_QUOTE_MAX_QUANTITY, $big['lines'][0]['quantity'], 'a sum stops at the largest quantity');
}

function test_cart_quote_plan_leaves_out_what_cannot_be_ordered()
{
	$plan = pg_cart_quote_plan(
		array(
			array('product_id' => 1, 'quantity' => 2),
			array('product_id' => 2, 'quantity' => 1),
			array('product_id' => 3, 'quantity' => 1),
			array('product_id' => 4, 'quantity' => 1),
			array('product_id' => 5, 'quantity' => 1),
		),
		array(
			1 => cart_quotes_test_product(),
			2 => cart_quotes_test_product(array('enabled' => '0')),
			3 => cart_quotes_test_product(array('selection_type' => 'donation')),
			4 => cart_quotes_test_product(array('inventory' => '1', 'inventory_quantity' => '0')),
		)
	);

	pg_assert_same(array(array('product_id' => 1, 'quantity' => 2)), $plan['add']);
	pg_assert_same(array(
		array('product_id' => 2, 'reason' => 'unavailable'),
		array('product_id' => 3, 'reason' => 'unavailable'),
		array('product_id' => 4, 'reason' => 'stock'),
		array('product_id' => 5, 'reason' => 'missing'),
	), $plan['skipped']);
}

function test_cart_quote_plan_brings_quantities_within_the_rules()
{
	$plan = pg_cart_quote_plan(
		array(
			array('product_id' => 1, 'quantity' => 1),
			array('product_id' => 2, 'quantity' => 9),
			array('product_id' => 3, 'quantity' => 9),
			array('product_id' => 4, 'quantity' => 9),
			array('product_id' => 5, 'quantity' => 1),
		),
		array(
			1 => cart_quotes_test_product(array('minimum_quantity' => '3')),
			2 => cart_quotes_test_product(array('maximum_quantity' => '4')),
			3 => cart_quotes_test_product(array('inventory' => '1', 'inventory_quantity' => '5')),
			4 => cart_quotes_test_product(array('inventory' => '1', 'inventory_quantity' => '0', 'backorder' => '1')),
			5 => cart_quotes_test_product(array('inventory' => '1', 'inventory_quantity' => '2', 'minimum_quantity' => '3')),
		)
	);

	pg_assert_same(array(
		array('product_id' => 1, 'quantity' => 3),
		array('product_id' => 2, 'quantity' => 4),
		array('product_id' => 3, 'quantity' => 5),
		array('product_id' => 4, 'quantity' => 9),
	), $plan['add'], 'minimum, maximum, stock, backorder');
	pg_assert_same(array(array('product_id' => 5, 'reason' => 'stock')), $plan['skipped'], 'less stock than the minimum');
}
