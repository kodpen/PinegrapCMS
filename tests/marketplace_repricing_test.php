<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Repricing view of a marketplace account
 * (includes/fn/marketplace_repricing.php): a listing's buybox state, the
 * four figure cards' counts, the filter behind each card, the log's reason
 * names, the "Try first" line and the rank on a product's row.
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
		'price' => 10000, 'floor' => 8000, 'buybox_ours' => 0,
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

// At the floor: the price going out is not above the floor (a typed own
// price under it counts too). No floor is "No cost".
function test_marketplace_repricing_state_floor()
{
	pg_assert_true(mp_repricing_state(marketplace_repricing_test_listing(array('price' => 8000)))['at_floor'], 'price equals the floor');
	pg_assert_true(mp_repricing_state(marketplace_repricing_test_listing(array('price' => 7000)))['at_floor'], 'an own price under the floor');
	pg_assert_false(mp_repricing_state(marketplace_repricing_test_listing(array('price' => 8001)))['at_floor'], 'a minor unit over it');
	pg_assert_false(mp_repricing_state(marketplace_repricing_test_listing(array('floor' => 8010, 'price' => 8090)))['at_floor'], 'held over the floor by a rounding ending');

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

	pg_assert_same(array(2), mp_repricing_card_ids($listings, 'losing'), 'losing only');
	pg_assert_same(array(2), mp_repricing_card_ids($listings, 'floor'), 'at the floor');
	pg_assert_same(array(3), mp_repricing_card_ids($listings, 'no_cost'), 'no cost');
	pg_assert_same(array(1, 2, 3, 4), mp_repricing_card_ids($listings, 'all'), 'all');
}

// Every reason the robot writes has a name; an unknown code is shown as itself.
function test_marketplace_repricing_reasons()
{
	pg_assert_same(array('lost', 'floor', 'raised', 'back'), array_keys(mp_repricing_reasons()), 'the four strings the robot writes');
	pg_assert_same(lang('Buybox lost'), mp_repricing_reason_label('lost'), 'lost');
	pg_assert_same(lang('Floor reached'), mp_repricing_reason_label('floor'), 'floor');
	pg_assert_same(lang('Raised while winning'), mp_repricing_reason_label('raised'), 'raised');
	pg_assert_same(lang('Stepped back'), mp_repricing_reason_label('back'), 'back');
	pg_assert_same('gray', mp_repricing_reason_tone('odd_code'), 'unknown code in grey');

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

// The rank as the product's row shows it: Winning in green, the number
// without an ordinal ending, Not read yet in grey.
function test_marketplace_repricing_rank_pill()
{
	pg_assert_contains('pg-tone-teal', mp_repricing_rank_html(1), 'winning is green');
	pg_assert_contains(h(lang('Winning')), mp_repricing_rank_html(1), 'winning');
	pg_assert_contains(h(lang(array('string' => 'Buybox rank: {var:1}', 'vars' => array(3)))), mp_repricing_rank_html(3), 'rank 3');
	pg_assert_contains('pg-tone-gray', mp_repricing_rank_html(0), 'not read is grey');
	pg_assert_contains(h(lang('Not read yet')), mp_repricing_rank_html(0), 'not read');
}

// The filters behind the segment row: the five product views and the log;
// anything else is All.
function test_marketplace_repricing_view_key()
{
	pg_assert_same('losing', mp_repricing_view_key('losing'), 'a card');
	pg_assert_same('log', mp_repricing_view_key('log'), 'the price log');
	pg_assert_same('all', mp_repricing_view_key('bogus'), 'unknown');
	pg_assert_same('all', mp_repricing_view_key(array('x')), 'not a string');
}

// A card's SQL condition on the listing: winning and losing by the stored
// rank, the floor and no cost by the ids worked out in PHP.
function test_marketplace_repricing_view_condition()
{
	pg_assert_same('(COALESCE(state.buybox_ours, 0) & 127) = 1', mp_repricing_view_condition('winning', array()), 'winning');
	pg_assert_same('(COALESCE(state.buybox_ours, 0) & 127) > 1', mp_repricing_view_condition('losing', array()), 'losing');
	pg_assert_same('map.product_id IN (3, 7)', mp_repricing_view_condition('floor', array(3, '7')), 'ids');
	pg_assert_same('1 = 0', mp_repricing_view_condition('no_cost', array()), 'no ids: nothing');
	pg_assert_same('1 = 1', mp_repricing_view_condition('all', array()), 'all');
}
