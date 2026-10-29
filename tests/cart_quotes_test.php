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

function test_cart_quote_until_is_the_end_of_the_day_named()
{
	pg_assert_same(0, pg_cart_quote_until(''), 'no end');
	pg_assert_same(mktime(23, 59, 59, 11, 30, 2026), pg_cart_quote_until('2026-11-30'), 'the whole day');
	pg_assert_same(null, pg_cart_quote_until('2026-02-30'), 'no such day');
	pg_assert_same(null, pg_cart_quote_until('30/11/2026'), 'not Y-m-d');
	pg_assert_same(null, pg_cart_quote_until('0000-00-00'), 'the empty date');
}

// The clean lines of two products and the catalogue rows of both, as
// pg_cart_quote_create_check() hands them over.
function cart_quotes_test_create_refusal(array $changes = array())
{
	$given = array_merge(array(
		'contact_id'    => 0,
		'contact_found' => false,
		'lines'         => array(array('product_id' => 7, 'quantity' => 2), array('product_id' => 8, 'quantity' => 1)),
		'products'      => array(7 => array('enabled' => '1', 'selection_type' => 'quantity'), 8 => array('enabled' => '1', 'selection_type' => 'checkbox')),
		'valid_until'   => 0,
		'now'           => 1760000000,
	), $changes);

	return pg_cart_quote_create_refusal($given['contact_id'], $given['contact_found'], pg_cart_quote_lines_clean($given['lines']),
		$given['products'], $given['valid_until'], $given['now']);
}

// The checks the new cart quote screen and POST /cart-quotes share: the
// contact named is there, the last day is not over, the lines are lines, and
// every product is one the screen's product box offers.
function test_cart_quote_create_refusal_names_the_field()
{
	$now = 1760000000;

	pg_assert_same(null, cart_quotes_test_create_refusal(), 'nobody named, no end, two products');
	pg_assert_same(null, cart_quotes_test_create_refusal(array('contact_id' => 5, 'contact_found' => true, 'valid_until' => $now)), 'a contact that is there, the link\'s last second');

	$refused = function (array $changes) {
		$refusal = cart_quotes_test_create_refusal($changes);

		return is_array($refusal) ? array($refusal['code'], $refusal['field']) : null;
	};

	pg_assert_same(array('contact_missing', 'contact_id'), $refused(array('contact_id' => 5)), 'a contact that is gone');
	pg_assert_same(array('valid_until_past', 'valid_until'), $refused(array('valid_until' => $now - 1)), 'a last day already over');
	pg_assert_same(array('lines_invalid', 'lines'), $refused(array('lines' => array())), 'no line');
	pg_assert_same(array('lines_invalid', 'lines'), $refused(array('lines' => array(array('product_id' => 7, 'quantity' => 0)))), 'no quantity');
	pg_assert_same(array('lines_invalid', 'lines'), $refused(array('lines' => array(array('product_id' => 7, 'quantity' => 1.5)))), 'not a whole number');

	$many = array();

	for ($id = 1; $id <= PG_CART_QUOTE_MAX_LINES + 1; $id++) {
		$many[] = array('product_id' => $id, 'quantity' => 1);
	}

	pg_assert_same(array('lines_too_many', 'lines'), $refused(array('lines' => $many)), 'more products than a quote holds');
	pg_assert_same(array('contact_missing', 'contact_id'), $refused(array('contact_id' => 5, 'valid_until' => $now - 1, 'lines' => array())), 'the contact first');
}

// A product the screen's box would not offer is refused before anything is
// kept: one not in the catalogue (gone or in the Recycle Bin), switched off,
// or a donation, whose amount is the visitor's to give.
function test_cart_quote_create_refusal_holds_the_products_to_the_catalogue()
{
	$unavailable = function (array $products) {
		$refusal = cart_quotes_test_create_refusal(array('products' => $products));

		return is_array($refusal) ? array($refusal['code'], $refusal['field']) : null;
	};

	$on = array('enabled' => '1', 'selection_type' => 'quantity');

	pg_assert_same(array('product_unavailable', 'lines'), $unavailable(array(7 => $on)), 'not in the catalogue');
	pg_assert_same(array('product_unavailable', 'lines'), $unavailable(array(7 => $on, 8 => array('enabled' => '0', 'selection_type' => 'quantity'))), 'switched off');
	pg_assert_same(array('product_unavailable', 'lines'), $unavailable(array(7 => $on, 8 => array('enabled' => '1', 'selection_type' => 'donation'))), 'a donation');
	pg_assert_contains('8', cart_quotes_test_create_refusal(array('products' => array(7 => $on)))['error'], 'the product is named');
}

// Cancel and a new link need a quote that is there and open now: one past
// its last day is no longer open, though nothing wrote expired.
function test_cart_quote_open_refusal()
{
	$now = 1760000000;
	$code = function ($quote, $action = 'cancel') use ($now) {
		$refusal = pg_cart_quote_open_refusal($quote, $now, $action);

		return is_array($refusal) ? $refusal['code'] : null;
	};

	pg_assert_same(null, $code(array('status' => 'open', 'valid_until' => 0)), 'open, no end');
	pg_assert_same(null, $code(array('status' => 'open', 'valid_until' => $now)), 'open, its last second');
	pg_assert_same('not_found', $code(null), 'not there');
	pg_assert_same('not_open', $code(array('status' => 'open', 'valid_until' => $now - 1)), 'past its last day');
	pg_assert_same('not_open', $code(array('status' => 'ordered', 'valid_until' => 0)), 'ordered');
	pg_assert_same('not_open', $code(array('status' => 'cancelled', 'valid_until' => 0)), 'cancelled');
	pg_assert_same('not_open', $code(array('status' => 'expired', 'valid_until' => 0), 'link'), 'stored expired');

	pg_assert_same(lang('Only an open cart quote can be cancelled.'), pg_cart_quote_open_refusal(array('status' => 'ordered', 'valid_until' => 0), $now, 'cancel')['error'], 'the list\'s sentence for cancel');
	pg_assert_same(lang('Only an open cart quote can be given a new link.'), pg_cart_quote_open_refusal(array('status' => 'ordered', 'valid_until' => 0), $now, 'link')['error'], 'and for a new link');
}

// What a cart_quote.* event carries: the quote as it stands now, its lines
// as ids and quantities, never the link.
function test_cart_quote_event_payload()
{
	$quote = array(
		'id' => '12', 'token_hash' => str_repeat('a', 64), 'token_hint' => 'Ab3dE9', 'contact_id' => '41', 'valid_until' => '1759999999',
		'status' => 'open', 'order_id' => '9001', 'created_by' => '3', 'created_at' => '1750000000', 'updated_at' => '1750000000',
	);
	$lines = array(array('product_id' => '7', 'quantity' => '2'), array('product_id' => '8', 'quantity' => '1'));
	$payload = pg_cart_quote_event_payload($quote, $lines, 1760000000);

	pg_assert_same(array(
		'id'          => 12,
		'status'      => 'open',
		'state'       => 'expired',
		'contact_id'  => 41,
		'order_id'    => 9001,
		'valid_until' => gmdate('Y-m-d\TH:i:s\Z', 1759999999),
		'items'       => array(array('product_id' => 7, 'quantity' => 2), array('product_id' => 8, 'quantity' => 1)),
	), $payload, 'the quote, its state now and its lines');

	$open = pg_cart_quote_event_payload(array_merge($quote, array('valid_until' => '0', 'contact_id' => '0', 'order_id' => '0')), array(), 1760000000);

	pg_assert_same(null, $open['valid_until'], 'no end');
	pg_assert_same('open', $open['state']);
	pg_assert_same(0, $open['contact_id'], 'made for nobody named');
	pg_assert_same(array(), $open['items']);
	pg_assert_false(strpos(json_encode($payload), 'Ab3dE9') !== false || strpos(json_encode($payload), str_repeat('a', 64)) !== false, 'neither the token\'s hint nor its hash');
}
