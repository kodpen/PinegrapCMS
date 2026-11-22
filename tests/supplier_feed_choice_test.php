<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/supplier_feed_choice.php: which feed a product
 * carried by several feeds follows, the price at a new rate from the kept buy
 * price, and the product card naming the feed in use.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_sync.php');

function test_supplier_feed_choice_stock_and_lowest_buy()
{
	$a = array('id' => 1, 'stock' => 5, 'buy' => 1200);
	$b = array('id' => 2, 'stock' => 3, 'buy' => 1000);
	$c = array('id' => 3, 'stock' => 0, 'buy' => 500);

	pg_assert_same(2, pg_supplier_feed_pick_item(array($a, $b, $c), 1), 'with stock, the lowest buy price wins; the cheaper one with no stock does not');
	pg_assert_same(1, pg_supplier_feed_pick_item(array($a, array('id' => 2, 'stock' => 0, 'buy' => 1000)), 2), 'the followed feed ran out: the one with stock takes over');
	pg_assert_same(3, pg_supplier_feed_pick_item(array(array('id' => 3, 'stock' => 0, 'buy' => 500)), 0), 'one feed alone is followed, even with no stock');
}

function test_supplier_feed_choice_ties_and_no_stock()
{
	pg_assert_same(2, pg_supplier_feed_pick_item(array(
		array('id' => 1, 'stock' => 5, 'buy' => 1000),
		array('id' => 2, 'stock' => 5, 'buy' => 1000),
	), 2), 'a tie keeps the one followed now');
	pg_assert_same(1, pg_supplier_feed_pick_item(array(
		array('id' => 2, 'stock' => 5, 'buy' => 1000),
		array('id' => 1, 'stock' => 5, 'buy' => 1000),
	), 0), 'a tie with none followed: the oldest item');
	pg_assert_same(2, pg_supplier_feed_pick_item(array(
		array('id' => 1, 'stock' => 0, 'buy' => 900),
		array('id' => 2, 'stock' => 0, 'buy' => 1000),
	), 2), 'no stock anywhere: the one followed stays, so its feed closes the product');
	pg_assert_same(1, pg_supplier_feed_pick_item(array(
		array('id' => 2, 'stock' => 4, 'buy' => null),
		array('id' => 1, 'stock' => 4, 'buy' => 2000),
	), 2), 'a feed with no buy price comes after one with a price');
	pg_assert_same(0, pg_supplier_feed_pick_item(array(), 0), 'no candidate');
}

function test_supplier_feed_choice_reprice_at_new_rate()
{
	$rules = array('margin' => 50, 'fixed' => 0, 'rounding' => 'none', 'floor_margin' => 0);

	// 10,00 USD bought, 50 %: 15,00 USD; at 30 then 32 per dollar.
	pg_assert_same(48000, pg_supplier_feed_reprice(1000, $rules, 30.0, 32.0, 45000), 'the price follows the new rate');
	pg_assert_same(null, pg_supplier_feed_reprice(1000, $rules, 30.0, 30.0, 45000), 'the same price: nothing to write');
	pg_assert_same(null, pg_supplier_feed_reprice(0, $rules, 30.0, 32.0, 45000), 'no buy price kept');
	pg_assert_same(null, pg_supplier_feed_reprice(1000, $rules, 30.0, 0.0, 45000), 'no rate now');

	$whole = array('margin' => 50, 'rounding' => '99', 'floor_margin' => 0);
	pg_assert_same(48099, pg_supplier_feed_reprice(1000, $whole, 30.0, 32.05, 45099), 'the rounding ending is kept');
}

function test_supplier_feed_choice_reprice_rule_by_old_price()
{
	$rules = array(
		'margin' => 50,
		'specific' => array(array('category' => 'Mutfak', 'margin' => 20)),
	);

	// The kitchen rule gave 12,00 at a rate of 1; at 2 it gives 24,00.
	pg_assert_same(2400, pg_supplier_feed_reprice(1000, $rules, 1.0, 2.0, 1200), 'the rule that gave the old price is kept');
	pg_assert_same(3000, pg_supplier_feed_reprice(1000, $rules, 1.0, 2.0, 1500), 'the feed\'s own rule gave it');
	pg_assert_same(null, pg_supplier_feed_reprice(1000, $rules, 1.0, 2.0, 1399), 'a price no rule gave: left to the next pull');

	$same = array('margin' => 50, 'specific' => array(array('brand' => 'Acme', 'fixed' => 0)));
	pg_assert_same(3000, pg_supplier_feed_reprice(1000, $same, 1.0, 2.0, 1399), 'rules that agree need no old price');
	pg_assert_same(2, count(pg_supplier_feed_rule_candidates($same)), 'the feed\'s own and each specific rule');
}

function test_supplier_feed_choice_product_card()
{
	$one = pg_supplier_feed_product_card_html(array(
		array('feed_id' => 2, 'name' => 'Acme', 'buy_price' => 1000, 'currency' => 'USD', 'seen_at' => 0, 'follow_price' => 1, 'active' => 1),
	));
	pg_assert_false(strpos($one, 'data-pg-sf-followed') !== false, 'one feed: no pill');

	$two = pg_supplier_feed_product_card_html(array(
		array('feed_id' => 2, 'name' => 'Acme', 'buy_price' => 1000, 'currency' => 'USD', 'seen_at' => 0, 'follow_price' => 1, 'active' => 1),
		array('feed_id' => 3, 'name' => 'Beta', 'buy_price' => 1100, 'currency' => 'USD', 'seen_at' => 0, 'follow_price' => 1, 'active' => 0),
	));
	pg_assert_same(1, substr_count($two, 'data-pg-sf-followed'), 'the feed in use wears the pill');
	pg_assert_contains('Acme</a> <span class="pg-pill pg-tone-', $two, 'beside its name');
	pg_assert_contains('lowest buy price', $two, 'the card says how the feed is chosen');
}
