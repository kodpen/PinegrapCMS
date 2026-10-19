<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /store/feeds (includes/api/resources/store.php): the feed
 * object, its declaration and the route.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// The presenter's time helper lives in includes/api/response.php, which loads
// only behind the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// A feed that is on and was built, without an address the test could open
// (no site key in the test runner).
function test_api_store_feeds_present()
{
	$entry = array_merge(pg_product_feed_format_defaults(), array(
		'enabled'  => 1,
		'built_at' => 1792400377,
		'count'    => 13,
		'bytes'    => 7990,
		'skipped'  => array('donation' => 3, 'not_in_catalog' => 9),
	));

	$feed = api_store_feed_present('openai_jsonl', $entry, 'ok');

	pg_assert_same('openai_jsonl', $feed['format']);
	pg_assert_same(true, $feed['enabled']);
	pg_assert_same(null, $feed['url'], 'no address yet');
	pg_assert_same('application/jsonl; charset=utf-8', $feed['content_type']);
	pg_assert_same('ok', $feed['state']);
	pg_assert_same('2026-10-19T08:59:37Z', $feed['built_at']);
	pg_assert_same(13, $feed['products']);
	pg_assert_same(7990, $feed['bytes']);
	pg_assert_same(null, $feed['error']);
	pg_assert_same(array(array('reason' => 'donation', 'count' => 3), array('reason' => 'not_in_catalog', 'count' => 9)), $feed['skipped']);

	pg_assert_same(array_keys(api_store_feed_schema()), array_keys($feed), 'every field declared, in order');
}

// A feed that is off, never built, and one whose last build failed.
function test_api_store_feeds_present_off_and_failing()
{
	$off = api_store_feed_present('google_tsv', pg_product_feed_format_defaults(), 'off');

	pg_assert_same(false, $off['enabled']);
	pg_assert_same(null, $off['built_at']);
	pg_assert_same(array(), $off['skipped']);

	$failing = api_store_feed_present('openai_csv', array_merge(pg_product_feed_format_defaults(), array('enabled' => 1, 'error' => 'no_catalog')), 'failing');

	pg_assert_same('no_catalog', $failing['error']);
	pg_assert_same('failing', $failing['state']);
}

// The route reads with products:read and answers with the declared object.
function test_api_store_feeds_route()
{
	$route = null;

	foreach (api_schema() as $candidate) {
		if ($candidate['id'] === 'store.feeds') {
			$route = $candidate;
		}
	}

	pg_assert_true(is_array($route), 'declared');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/store/feeds', $route['path']);
	pg_assert_same('products:read', $route['scope']);
	pg_assert_same(array('list' => 'StoreFeed'), $route['returns']);
	pg_assert_true(function_exists($route['handler']), 'handler exists');
}
