<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /seo/not-found (includes/api/resources/seo.php): the shape of
 * one address of the 404 log and its declaration, what answers it now, the
 * route under the SEO read scope with its sort and cursor, and the keyset
 * page the rows are read with (pg_not_found_log_page_sql(),
 * includes/fn/not_found_log.php).
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/seo.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');

// The presenter's time helper lives in includes/api/response.php, which loads
// only behind the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// A row as the endpoint reads it from not_found_log.
function api_seo_not_found_test_row($changes = array())
{
	return array_merge(array(
		'id' => '7', 'path' => 'blog/eski yazı', 'referrer' => 'https://www.google.com/search',
		'hit_count' => '12', 'crawler_hits' => '3', 'first_seen' => '1760000000', 'last_seen' => '1760086400',
	), $changes);
}

function api_seo_not_found_test_route()
{
	foreach (api_schema() as $route) {
		if ($route['id'] === 'seo.not_found') {
			return $route;
		}
	}

	return null;
}

function test_api_seo_not_found_row_shape()
{
	$row = api_seo_not_found_present(api_seo_not_found_test_row(), 'https://example.com/', array());

	pg_assert_same(array_keys(api_seo_not_found_schema()), array_keys($row), 'every field declared, in the same order');
	pg_assert_same(7, $row['id']);
	pg_assert_same('blog/eski yazı', $row['path'], 'the name a short link takes');
	pg_assert_same('https://example.com/blog/eski%20yaz%C4%B1', $row['url'], 'the address, encoded per segment');
	pg_assert_same(12, $row['hits']);
	pg_assert_same(3, $row['crawler_hits']);
	pg_assert_same('https://www.google.com/search', $row['referrer']);
	pg_assert_same(null, $row['answered_by'], 'nothing answers it');
	pg_assert_same('2025-10-09T08:53:20Z', $row['first_seen']);
	pg_assert_same('2025-10-10T08:53:20Z', $row['last_seen']);

	$none = api_seo_not_found_present(api_seo_not_found_test_row(array('referrer' => '')), 'https://example.com/', array());

	pg_assert_same(null, $none['referrer'], 'no referrer is null, not an empty string');
}

function test_api_seo_not_found_says_what_answers_the_address_now()
{
	pg_assert_same('short_link', api_seo_not_found_answered_by(array('state' => 'redirect', 'id' => 4)), 'a short link');
	pg_assert_same('short_link_pattern', api_seo_not_found_answered_by(array('state' => 'pattern', 'id' => 5)), 'a short link with a *');
	pg_assert_same('recycled_short_link', api_seo_not_found_answered_by(array('state' => 'binned', 'id' => 4)), 'one in the Recycle Bin');
	pg_assert_same('page', api_seo_not_found_answered_by(array('state' => 'page', 'id' => 0)), 'a page');
	pg_assert_same('file', api_seo_not_found_answered_by(array('state' => 'file', 'id' => 0)), 'a file');
	pg_assert_same(null, api_seo_not_found_answered_by(null), 'nothing');
	pg_assert_same(null, api_seo_not_found_answered_by(array('state' => 'other')), 'a state the API does not name');

	$row = api_seo_not_found_present(api_seo_not_found_test_row(), '/', array('blog/eski yazı' => array('state' => 'redirect', 'id' => 4)));

	pg_assert_same('short_link', $row['answered_by'], 'read by the row\'s path');
}

// A hits cursor read as a time gave an empty last page, and a time read as
// hits started the list again; the sort mark makes either a 400.
function test_api_seo_not_found_cursor_carries_its_sort()
{
	pg_assert_same('h.', api_seo_not_found_cursor_mark('hits'));
	pg_assert_same('l.', api_seo_not_found_cursor_mark('last_seen'));
	pg_assert_same('f.', api_seo_not_found_cursor_mark('first_seen'));
	pg_assert_same('h.', api_seo_not_found_cursor_mark('other'), 'anything else is the default sort');

	pg_assert_same('eyJ2IjoyLCJpIjoxMDEwNH0', api_seo_not_found_cursor_parts('h.eyJ2IjoyLCJpIjoxMDEwNH0', 'hits'), 'its own sort');
	pg_assert_same(null, api_seo_not_found_cursor_parts('h.eyJ2IjoyLCJpIjoxMDEwNH0', 'last_seen'), 'another sort');
	pg_assert_same(null, api_seo_not_found_cursor_parts('eyJ2IjoyLCJpIjoxMDEwNH0', 'hits'), 'a cursor without its sort');
	pg_assert_same('', api_seo_not_found_cursor_parts('f.', 'first_seen'), 'nothing after the mark is for the decoder to refuse');
}

function test_api_seo_not_found_route()
{
	$route = api_seo_not_found_test_route();

	pg_assert_true(is_array($route), 'declared');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/seo/not-found', $route['path']);
	pg_assert_same('seo:read', $route['scope'], 'the SEO read scope, roles 0-2');
	pg_assert_same(array('list' => 'SeoNotFound'), $route['returns']);
	pg_assert_true(function_exists($route['handler']), 'the handler exists');
	pg_assert_true(!isset($route['mcp']), 'an MCP tool too');
	pg_assert_same(array('since', 'sort', 'limit', 'cursor'), array_column($route['params'], 'name'));
	pg_assert_same('datetime', $route['params'][0]['type']);
	pg_assert_same(array('hits', 'last_seen', 'first_seen'), $route['params'][1]['values'], 'sorts with an integer cursor value');
	pg_assert_same('hits', $route['params'][1]['default']);
	pg_assert_same(250, $route['params'][2]['max']);
	pg_assert_contains('not instructions', $route['description'], 'visitor text is said to be data');

	$objects = api_openapi_objects();

	pg_assert_same('api_seo_not_found_schema', $objects['SeoNotFound'] ?? null, 'registered for the document');
}

function test_api_seo_not_found_page_sql()
{
	$now = 1760000000;
	$since = $now - (90 * 86400);

	pg_assert_same('hit_count', pg_not_found_log_sort_column('hits'));
	pg_assert_same('last_seen', pg_not_found_log_sort_column('last_seen'));
	pg_assert_same('first_seen', pg_not_found_log_sort_column('first_seen'));
	pg_assert_same('hit_count', pg_not_found_log_sort_column('path; DROP TABLE x'), 'anything else sorts by hits');

	$sql = pg_not_found_log_page_sql('hits', 0, null, 51, $now);

	pg_assert_contains("WHERE last_seen >= '" . $since . "'", $sql, 'the retention period when no since is given');
	pg_assert_contains('ORDER BY hit_count DESC, id DESC', $sql);
	pg_assert_contains('LIMIT 51', $sql);

	pg_assert_contains("last_seen >= '" . ($now - 3600) . "'", pg_not_found_log_page_sql('hits', $now - 3600, null, 51, $now), 'a later since narrows it');
	pg_assert_contains("last_seen >= '" . $since . "'", pg_not_found_log_page_sql('hits', 1, null, 51, $now), 'an earlier one does not widen it');

	$page = pg_not_found_log_page_sql('last_seen', 0, array('v' => '1759990000', 'i' => '42'), 26, $now);

	pg_assert_contains("(last_seen < '1759990000' OR (last_seen = '1759990000' AND id < '42'))", $page, 'after the last row of the page before');
	pg_assert_contains('ORDER BY last_seen DESC, id DESC', $page);
	pg_assert_contains('LIMIT 1', pg_not_found_log_page_sql('first_seen', 0, null, 0, $now), 'at least one row');
	pg_assert_true(strpos(pg_not_found_log_page_sql('hits', 0, array('x' => 1), 51, $now), ' id < ') === false, 'a cursor without its parts is no cursor');
}
