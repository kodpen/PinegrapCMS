<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Repricing view of a marketplace account
 * (includes/fn/marketplace_repricing.php): a listing's buybox state, the
 * four figure cards' counts, the filter behind each card, the log's reason
 * names, the "Try first" line and the rank's words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/marketplace_repricing.php');

// One listing as mp_repricing_listings() hands it to the pure functions.
function marketplace_repricing_test_listing($changes = array())
{
	return array_merge(array(
		'product_id' => 1, 'title' => 'Mug', 'code' => 'MUG-1',
		'price' => 10000, 'floor' => 8000, 'rounding' => 'none', 'buybox_ours' => 0,
	), $changes);
}

// The rank and other sellers come out of buybox_ours; rank 1 is winning,
// over 1 losing, no read neither.
function test_marketplace_repricing_state_rank()
{
	$state = mp_repricing_state(marketplace_repricing_test_listing(array('buybox_ours' => 1)));
	pg_assert_same(1, $state['rank'], 'ours alone');
	pg_assert_true($state['winning'], 'rank 1 wins');
	pg_assert_false($state['losing'], 'rank 1 does not lose');
	pg_assert_false($state['others'], 'no other seller');

	$state = mp_repricing_state(marketplace_repricing_test_listing(array('buybox_ours' => 131)));
	pg_assert_same(3, $state['rank'], 'third');
	pg_assert_true($state['losing'], 'rank 3 loses');
	pg_assert_true($state['others'], 'others sell it');

	$state = mp_repricing_state(marketplace_repricing_test_listing());
	pg_assert_same(0, $state['rank'], 'never read');
	pg_assert_false($state['winning'] || $state['losing'], 'a listing never read neither wins nor loses');
}

// At the floor: the price is the lowest the floor lets the rounding reach,
// or under it (a typed own price may be). No floor is "No cost".
function test_marketplace_repricing_state_floor()
{
	pg_assert_true(mp_repricing_state(marketplace_repricing_test_listing(array('price' => 8000)))['at_floor'], 'price equals the floor');
	pg_assert_true(mp_repricing_state(marketplace_repricing_test_listing(array('price' => 7000)))['at_floor'], 'under the floor');
	pg_assert_false(mp_repricing_state(marketplace_repricing_test_listing(array('price' => 8001)))['at_floor'], 'a minor unit over it, no rounding');

	// Floor 80.10 with ,90: the lowest price the rules may send is 80.90.
	pg_assert_true(mp_repricing_state(marketplace_repricing_test_listing(array('floor' => 8010, 'price' => 8090, 'rounding' => '90')))['at_floor'], 'held at the floor by its ending');
	pg_assert_false(mp_repricing_state(marketplace_repricing_test_listing(array('floor' => 8010, 'price' => 8190, 'rounding' => '90')))['at_floor'], 'the next ending up is over it');

	$state = mp_repricing_state(marketplace_repricing_test_listing(array('floor' => null)));
	pg_assert_true($state['no_cost'], 'no floor is no cost');
	pg_assert_false($state['at_floor'], 'no floor is never at the floor');
}

// The cards count overlapping groups; all counts every listing.
function test_marketplace_repricing_counts()
{
	$listings = array(
		marketplace_repricing_test_listing(array('product_id' => 1, 'buybox_ours' => 1)),
		marketplace_repricing_test_listing(array('product_id' => 2, 'buybox_ours' => 130, 'price' => 8000)),
		marketplace_repricing_test_listing(array('product_id' => 3, 'floor' => null)),
		marketplace_repricing_test_listing(array('product_id' => 4)),
	);

	pg_assert_same(array('all' => 4, 'winning' => 1, 'losing' => 1, 'floor' => 1, 'no_cost' => 1),
		mp_repricing_counts($listings), 'counts');

	pg_assert_same(array(2), array_column(mp_repricing_filter($listings, 'losing', ''), 'product_id'), 'losing only');
	pg_assert_same(array(2), array_column(mp_repricing_filter($listings, 'floor', ''), 'product_id'), 'at the floor');
	pg_assert_same(array(1, 2, 3, 4), array_column(mp_repricing_filter($listings, 'all', ''), 'product_id'), 'all');
	pg_assert_same(array(1, 2, 3, 4), array_column(mp_repricing_filter($listings, 'bogus', ''), 'product_id'), 'an unknown card is all');
}

// The search box finds a listing by its title or code, without case.
function test_marketplace_repricing_filter_search()
{
	$listings = array(
		marketplace_repricing_test_listing(array('product_id' => 1, 'title' => 'Blue Mug', 'code' => 'BM-1')),
		marketplace_repricing_test_listing(array('product_id' => 2, 'title' => 'Plate', 'code' => 'PL-2')),
	);

	pg_assert_same(array(1), array_column(mp_repricing_filter($listings, 'all', 'blue'), 'product_id'), 'by title');
	pg_assert_same(array(2), array_column(mp_repricing_filter($listings, 'all', 'pl-2'), 'product_id'), 'by code');
	pg_assert_same(array(), mp_repricing_filter($listings, 'all', 'cup'), 'nothing found');
}

// Every reason the robot writes has a name; an unknown code is shown as itself.
function test_marketplace_repricing_reasons()
{
	foreach (array(MP_REPRICING_REASON_LOST, MP_REPRICING_REASON_FLOOR, MP_REPRICING_REASON_RAISED, MP_REPRICING_REASON_BACK) as $code) {
		pg_assert_true(strlen($code) <= 20, $code . ' fits marketplace_price_log.reason');
		pg_assert_true(mp_repricing_reason_label($code) !== $code, $code . ' has a name');
	}

	pg_assert_same('odd_code', mp_repricing_reason_label('odd_code'), 'unknown code');
}

// The line shows while the account's robot only writes the log: Try first
// on, or no Pricing row yet (the tab starts with Try first on).
function test_marketplace_repricing_try_first()
{
	pg_assert_true(mp_repricing_try_first(array()), 'no rule row');
	pg_assert_true(mp_repricing_try_first(array(array('scope' => MP_PRICING_SCOPE_ACCOUNT, 'robot' => MP_PRICING_ROBOT_TRY))), 'try first on');
	pg_assert_false(mp_repricing_try_first(array(array('scope' => MP_PRICING_SCOPE_ACCOUNT, 'robot' => MP_PRICING_ROBOT_ON))), 'sending');
	pg_assert_true(mp_repricing_try_first(array(array('scope' => MP_PRICING_SCOPE_BRAND, 'robot' => 0))), 'a brand rule alone: the account row is the default');
}

// The rank in words: ours, a place, or not read yet.
function test_marketplace_repricing_rank_text()
{
	pg_assert_same(lang('Ours'), mp_repricing_rank_text(1), 'ours');
	pg_assert_same(lang(array('string' => 'Rank {var:1}', 'vars' => array(3))), mp_repricing_rank_text(3), 'third');
	pg_assert_same(lang('Not read yet'), mp_repricing_rank_text(0), 'no read');
}
