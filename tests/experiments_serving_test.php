<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for serving a page experiment's variant: the control key, the pick
 * between the bucket and a new draw, the body page per served state, the
 * page cache's key per variant, the experiment part of a stored copy, the
 * router's route for a visitor and a crawler, and the beacon's variant.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_experiment_variants()
{
	return array(
		'a' => array('id' => 11, 'draft_page_id' => 0, 'variant_key' => 'a', 'weight' => 50),
		'b' => array('id' => 12, 'draft_page_id' => 40, 'variant_key' => 'b', 'weight' => 50),
	);
}

// The live page is the variant without a draft.
function test_experiment_control_key_and_weights()
{
	pg_assert_same('a', pg_experiment_control_key(pg_test_experiment_variants()));
	pg_assert_same('', pg_experiment_control_key(array('b' => array('draft_page_id' => 4))), 'every variant has a draft');
	pg_assert_same(array('a' => 50, 'b' => 50), pg_experiment_weights(pg_test_experiment_variants()));
	pg_assert_same(array('a' => 0), pg_experiment_weights(array('a' => array('weight' => -5))), 'no negative weight');
}

// A kept bucket wins while its variant has a weight; otherwise a new draw.
function test_experiment_pick_bucket_or_draw()
{
	$weights = array('a' => 50, 'b' => 50);

	pg_assert_same(array('key' => 'b', 'drawn' => false), pg_experiment_pick($weights, 'b', 0.0), 'kept bucket');
	pg_assert_same(array('key' => 'a', 'drawn' => true), pg_experiment_pick($weights, '', 0.1), 'no bucket draws');
	pg_assert_same(array('key' => 'b', 'drawn' => true), pg_experiment_pick($weights, 'z', 0.9), 'a key the experiment lacks draws');
	pg_assert_same(array('key' => 'a', 'drawn' => true), pg_experiment_pick(array('a' => 50, 'b' => 0), 'b', 0.9), 'a variant set to no weight draws');
	pg_assert_same(array('key' => '', 'drawn' => false), pg_experiment_pick(array('a' => 0), '', 0.5), 'nothing to draw');
}

// The body is the draft's only for the served page and a variant with a draft.
function test_experiment_body_page_per_served_state()
{
	$served = array('page_id' => 7, 'draft_page_id' => 40);

	pg_assert_same(40, pg_experiment_body_page_for(7, $served), 'the variant draft');
	pg_assert_same(8, pg_experiment_body_page_for(8, $served), 'another page drawn in the request');
	pg_assert_same(7, pg_experiment_body_page_for(7, array('page_id' => 7, 'draft_page_id' => 0)), 'the control');
	pg_assert_same(7, pg_experiment_body_page_for(7, null), 'no experiment served (excluded or none)');
}

// One copy per variant; the control keeps the address's own key.
function test_experiment_cache_variant_key()
{
	$key = sha1('address');
	$b = pg_page_cache_variant_key($key, 3, 'b');

	pg_assert_true(preg_match('/^[0-9a-f]{40}$/', $b) === 1, 'a key');
	pg_assert_true($b !== $key, 'not the address key');
	pg_assert_true($b !== pg_page_cache_variant_key($key, 3, 'c'), 'per variant');
	pg_assert_true($b !== pg_page_cache_variant_key($key, 4, 'b'), 'per experiment');
	pg_assert_same($b, pg_page_cache_variant_key($key, 3, 'b'), 'stable');

	$served = array('experiment_id' => 3, 'key' => 'b', 'control_key' => 'a', 'weights' => array('a' => 50, 'b' => 50));
	$store = pg_page_cache_experiment_store($key, $served, false);
	pg_assert_same($b, $store['key'], 'the variant copy');
	pg_assert_same(array('id' => 3, 'control' => 'a', 'weights' => array('a' => 50, 'b' => 50)), $store['exp']);

	$served['key'] = 'a';
	pg_assert_same($key, pg_page_cache_experiment_store($key, $served, false)['key'], 'the control copy');

	pg_assert_same(array('key' => $key, 'exp' => null), pg_page_cache_experiment_store($key, null, false), 'no experiment');
	pg_assert_same(null, pg_page_cache_experiment_store($key, null, true), 'a running experiment the request did not join');

	$served['key'] = 'z';
	pg_assert_same(null, pg_page_cache_experiment_store($key, $served, false), 'a key outside the weights');
}

// The experiment part of a copy survives the round trip and is cleaned.
function test_experiment_cache_meta()
{
	$exp = array('id' => 3, 'control' => 'a', 'weights' => array('a' => 50, 'b' => 50));
	$back = pg_page_cache_unpack(pg_page_cache_pack(array('created' => 5, 'headers' => array(), 'exp' => $exp), '<p>x</p>'));

	pg_assert_same($exp, $back['meta']['exp']);
	pg_assert_same(null, pg_page_cache_unpack(pg_page_cache_pack(array('created' => 5, 'headers' => array()), 'x'))['meta']['exp'], 'none');
	pg_assert_same(null, pg_page_cache_unpack("{\"v\":1,\"created\":5,\"headers\":[]}\nx")['meta']['exp'], 'an older copy');

	pg_assert_same(null, pg_page_cache_experiment_meta(array('id' => 0, 'control' => 'a', 'weights' => array('a' => 1, 'b' => 1))), 'no id');
	pg_assert_same(null, pg_page_cache_experiment_meta(array('id' => 3, 'control' => 'c', 'weights' => array('a' => 1, 'b' => 1))), 'control outside the weights');
	pg_assert_same(null, pg_page_cache_experiment_meta(array('id' => 3, 'control' => 'a', 'weights' => array('a' => 1))), 'one variant');
	pg_assert_same(array('id' => 3, 'control' => 'a', 'weights' => array('a' => 1, 'b' => 0)),
		pg_page_cache_experiment_meta(array('id' => '3', 'control' => 'a', 'weights' => array('a' => 1, 'b' => -2, 'X!' => 5))), 'cleaned');
}

// The router answers a visitor from their variant's copy and a crawler from the live page's.
function test_experiment_cache_route()
{
	$exp = array('id' => 3, 'control' => 'a', 'weights' => array('a' => 50, 'b' => 50));

	pg_assert_same(array('key' => 'b', 'drawn' => false, 'variant' => true),
		pg_page_cache_experiment_route($exp, array('pg_exp' => '3:b'), false, 0.0), 'kept bucket b');
	pg_assert_same(array('key' => 'a', 'drawn' => false, 'variant' => false),
		pg_page_cache_experiment_route($exp, array('pg_exp' => '1:b.3:a'), false, 0.9), 'kept bucket a is the live copy');
	pg_assert_same(array('key' => 'b', 'drawn' => true, 'variant' => true),
		pg_page_cache_experiment_route($exp, array(), false, 0.9), 'a new visitor draws');
	pg_assert_same(array('key' => '', 'drawn' => false, 'variant' => false),
		pg_page_cache_experiment_route($exp, array('pg_exp' => '3:b'), true, 0.9), 'a crawler never joins');
}

// An experiment page is never kept by a shared cache.
function test_experiment_cache_headers_private()
{
	pg_assert_same('Cache-Control: private, max-age=0', pg_page_cache_headers(120, true)[0]);
	pg_assert_true(strpos(pg_page_cache_headers(120)[0], 's-maxage=') !== false, 'other pages unchanged');
}

// The beacon carries the variant; only a variant of the running experiment counts.
function test_experiment_beacon_variant()
{
	$payload = pg_page_cache_beacon_payload(array('page_id' => 7, 'exp_id' => 3, 'variant_id' => 12));
	$read = pg_page_cache_beacon_read($payload);

	pg_assert_same(3, $read['exp_id']);
	pg_assert_same(12, $read['variant_id']);
	pg_assert_same(0, pg_page_cache_beacon_read('{"page_id":7}')['exp_id'], 'an older copy names none');

	$running = array('id' => 3, 'variants' => pg_test_experiment_variants());

	pg_assert_same(array(3, 12), pg_experiment_beacon_view($running, 3, 12));
	pg_assert_same(null, pg_experiment_beacon_view($running, 3, 99), 'a variant of another experiment');
	pg_assert_same(null, pg_experiment_beacon_view($running, 4, 12), 'another experiment');
	pg_assert_same(null, pg_experiment_beacon_view(null, 3, 12), 'the experiment ended');
}

// The hourly row's key is one per day, hour, experiment and variant.
function test_experiment_hourly_key()
{
	$key = pg_experiment_hourly_key('2026-11-03', 9, 3, 12);

	pg_assert_same(sha1('2026-11-03|9|3|12'), $key);
	pg_assert_true($key !== pg_experiment_hourly_key('2026-11-03', 10, 3, 12), 'per hour');
	pg_assert_true($key !== pg_experiment_hourly_key('2026-11-03', 9, 3, 11), 'per variant');
}
