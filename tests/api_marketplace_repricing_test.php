<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /marketplaces/{account}/repricing and its update route
 * (includes/api/resources/marketplace_repricing.php): a listing as both
 * answer it, a never-read and a winning listing, the floor, the last change,
 * and which own price and strategy an update leaves.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/marketplace_repricing.php');

// The presenter's helpers live in includes/api/response.php, which loads only
// behind the API's own entry. Stand-ins of the same shape.
if (!function_exists('api_money')) {
	function api_money($minor_units)
	{
		return (int) $minor_units;
	}
}

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		$unix = (int) $unix;

		return ($unix <= 0) ? null : gmdate('Y-m-d\TH:i:s\Z', $unix);
	}
}

// One listing as mp_repricing_listings() hands it over.
function api_marketplace_repricing_test_listing($changes = array())
{
	return array_merge(array(
		'product_id' => 7, 'title' => 'Mug', 'code' => 'MUG-1', 'price' => 12990, 'by' => 'rule',
		'own_price' => 0, 'floor' => 9000, 'strategy' => 0, 'buybox_price' => 0, 'buybox_ours' => 0,
		'buybox_at' => 0, 'change' => null,
	), $changes);
}

// Never read: no rank, no buybox price, no read time, no change.
function test_api_marketplace_repricing_present_not_read()
{
	$out = api_marketplace_repricing_present(api_marketplace_repricing_test_listing());

	pg_assert_same(7, $out['product_id'], 'product id');
	pg_assert_same(12990, $out['price'], 'price');
	pg_assert_same('rule', $out['price_from'], 'set by the rules');
	pg_assert_same(null, $out['own_price'], 'no own price');
	pg_assert_same('fixed', $out['strategy'], 'fixed');
	pg_assert_same(9000, $out['floor'], 'floor');
	pg_assert_false($out['at_floor'], 'above the floor');
	pg_assert_same(null, $out['buybox_rank'], 'no rank');
	pg_assert_false($out['winning'], 'not winning');
	pg_assert_same(null, $out['buybox_price'], 'no buybox price');
	pg_assert_same(null, $out['buybox_read_at'], 'no read time');
	pg_assert_same(null, $out['last_change'], 'no change');
}

// Rank and other sellers come out of buybox_ours (low seven bits, bit 128).
function test_api_marketplace_repricing_present_buybox()
{
	$out = api_marketplace_repricing_present(api_marketplace_repricing_test_listing(array(
		'buybox_ours' => 1 | 128, 'buybox_price' => 12500, 'buybox_at' => 1700000000, 'strategy' => 1,
	)));

	pg_assert_same(1, $out['buybox_rank'], 'rank 1');
	pg_assert_true($out['winning'], 'winning');
	pg_assert_true($out['other_sellers'], 'others sell it');
	pg_assert_same(12500, $out['buybox_price'], 'buybox price');
	pg_assert_same('2023-11-14T22:13:20Z', $out['buybox_read_at'], 'read time');
	pg_assert_same('win', $out['strategy'], 'win');

	$losing = api_marketplace_repricing_present(api_marketplace_repricing_test_listing(array('buybox_ours' => 3, 'strategy' => 2)));
	pg_assert_same(3, $losing['buybox_rank'], 'rank 3');
	pg_assert_false($losing['winning'], 'losing');
	pg_assert_false($losing['other_sellers'], 'bit not set');
	pg_assert_same('grow', $losing['strategy'], 'grow');
}

// The floor: none is null; a price at it (or an own price under it) is at the floor.
function test_api_marketplace_repricing_present_floor_and_own_price()
{
	$none = api_marketplace_repricing_present(api_marketplace_repricing_test_listing(array('floor' => null)));
	pg_assert_same(null, $none['floor'], 'no floor');
	pg_assert_false($none['at_floor'], 'no floor is not at the floor');

	$own = api_marketplace_repricing_present(api_marketplace_repricing_test_listing(array(
		'price' => 8000, 'by' => 'own', 'own_price' => 8000,
	)));
	pg_assert_same(8000, $own['own_price'], 'own price');
	pg_assert_same('own', $own['price_from'], 'set by the own price');
	pg_assert_true($own['at_floor'], 'an own price under the floor');

	$unknown = api_marketplace_repricing_present(api_marketplace_repricing_test_listing(array('strategy' => 9)));
	pg_assert_same('fixed', $unknown['strategy'], 'an unknown number is fixed');
}

// The last change carries both prices, the reason, sent and when.
function test_api_marketplace_repricing_present_last_change()
{
	$out = api_marketplace_repricing_present(api_marketplace_repricing_test_listing(array(
		'change' => array('old_price' => 12990, 'new_price' => 12490, 'reason' => 'lost', 'sent' => false, 'at' => 1700000000),
	)));

	pg_assert_same(array(
		'old_price' => 12990, 'new_price' => 12490, 'reason' => 'lost', 'sent' => false, 'at' => '2023-11-14T22:13:20Z',
	), $out['last_change'], 'last change');
}

// The presenter's keys are the declared ones, in the same order.
function test_api_marketplace_repricing_present_matches_schema()
{
	pg_assert_same(array_keys(api_marketplace_repricing_schema()),
		array_keys(api_marketplace_repricing_present(api_marketplace_repricing_test_listing())), 'fields');

	$change = api_marketplace_repricing_present(api_marketplace_repricing_test_listing(array(
		'change' => array('old_price' => 1, 'new_price' => 2, 'reason' => 'raised', 'sent' => true, 'at' => 1),
	)))['last_change'];
	pg_assert_same(array_keys(api_marketplace_repricing_change_schema()), array_keys($change), 'change fields');
}

// Only what was sent changes; an empty own price clears it.
function test_api_marketplace_repricing_change_keeps_what_was_not_sent()
{
	$current = array('own_price' => 15000, 'strategy' => 1);

	$strategy = api_marketplace_repricing_change(array('strategy' => 'grow'), $current, array(0, 1, 2));
	pg_assert_same('', $strategy['error'], 'grow on Trendyol');
	pg_assert_same(2, $strategy['strategy'], 'grow stored as 2');
	pg_assert_same(15000, $strategy['own_price'], 'own price kept');

	$price = api_marketplace_repricing_change(array('own_price' => 13990), $current, array(0, 1, 2));
	pg_assert_same(13990, $price['own_price'], 'own price set');
	pg_assert_same(1, $price['strategy'], 'strategy kept');

	$cleared = api_marketplace_repricing_change(array('own_price' => ''), $current, array(0, 1, 2));
	pg_assert_same(0, $cleared['own_price'], 'cleared: by the rules');
}

// A strategy the marketplace lacks and an empty body are refused.
function test_api_marketplace_repricing_change_refusals()
{
	$current = array('own_price' => 0, 'strategy' => 0);

	$n11 = api_marketplace_repricing_change(array('strategy' => 'win'), $current, array(0));
	pg_assert_same('strategy', $n11['field'], 'win on n11 refused');
	pg_assert_true($n11['error'] !== '', 'with a reason');

	$fixed = api_marketplace_repricing_change(array('strategy' => 'fixed'), $current, array(0));
	pg_assert_same('', $fixed['error'], 'fixed everywhere');

	$empty = api_marketplace_repricing_change(array('account' => 1, 'product' => 7), $current, array(0, 1, 2));
	pg_assert_true($empty['error'] !== '', 'nothing to change');
}
