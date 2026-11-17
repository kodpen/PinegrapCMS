<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Conventions section and the heading outline of the API
 * reference (includes/api/console_view.php), printed by the panel's
 * api_docs.php and the console at integration.php/docs.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/console_view.php');

// Every topic the reference opens with is there, once, with a title and text.
function test_api_console_conventions_topics()
{
	$items = api_console_conventions('https://example.test/pinegrap/integration.php');

	$ids = array();

	foreach ($items as $item) {
		pg_assert_true(isset($item['id'], $item['title'], $item['text']), 'an item has id, title and text');
		pg_assert_true(trim($item['title']) !== '' && trim($item['text']) !== '', $item['id'] . ' has words');
		$ids[] = $item['id'];
	}

	pg_assert_same(array('credentials', 'errors', 'paging', 'idempotency', 'dry-run', 'etag',
		'rate-limit', 'post-twins', 'formats', 'language'), $ids, 'the topics, in reading order');
}

// Each topic says what the code answers today and names the headers it reads.
function test_api_console_conventions_match_the_code()
{
	$text = array();

	foreach (api_console_conventions('https://example.test/pinegrap/integration.php') as $item) {
		$text[$item['id']] = $item['text'] . ' ' . (isset($item['example']) ? $item['example'] : '');
	}

	pg_assert_contains('X-Pinegrap-Key', $text['credentials']);
	pg_assert_contains('Bearer', $text['credentials']);
	pg_assert_contains('https://example.test/pinegrap/integration.php/meta', $text['credentials']);
	pg_assert_contains('X-Request-Id', $text['errors']);
	pg_assert_contains('request_id', $text['errors']);
	pg_assert_contains('next_cursor', $text['paging']);
	pg_assert_contains('Idempotent-Replay', $text['idempotency']);
	pg_assert_contains('idempotency_key_reused', $text['idempotency']);
	pg_assert_contains('64', $text['idempotency']);
	pg_assert_contains('dry_run_unsupported', $text['dry-run']);
	pg_assert_contains('If-None-Match', $text['etag']);
	pg_assert_contains('X-RateLimit-Remaining', $text['rate-limit']);
	pg_assert_contains('Retry-After', $text['rate-limit']);
	pg_assert_contains('X-HTTP-Method-Override', $text['post-twins']);
	pg_assert_contains('/delete', $text['post-twins']);
	pg_assert_contains('fields=', $text['formats']);
	pg_assert_contains('GET /meta', $text['language']);

	// Rules a later change brings are not promised before they exist.
	$all = implode(' ', $text);

	foreach (array('precondition_failed', 'Accept-Language', 'error.errors', 'Location', 'HEAD') as $later) {
		pg_assert_true(strpos($all, $later) === false, $later . ' is not promised');
	}
}

// The section is one h2 with an h3 per topic, ids to link to, the English line
// first, and every value escaped.
function test_api_console_conventions_html()
{
	$html = api_console_conventions_html(array(
		array('id' => 'one', 'title' => 'A <b>', 'text' => 'Text & more', 'example' => '{"a": "<x>"}'),
		array('id' => 'two', 'title' => 'B', 'text' => 'Plain')
	));

	pg_assert_contains('<section class="doc-conv card mb-5" id="conventions"', $html);
	pg_assert_same(1, substr_count($html, '<h2'), 'one section heading');
	pg_assert_same(2, substr_count($html, '<h3'), 'a heading per topic');
	pg_assert_contains('id="conv_one"', $html);
	pg_assert_contains('id="conv_two"', $html);
	pg_assert_contains('A &lt;b&gt;', $html);
	pg_assert_contains('Text &amp; more', $html);
	pg_assert_contains('{&quot;a&quot;: &quot;&lt;x&gt;&quot;}', $html);
	pg_assert_same(1, substr_count($html, 'doc-curl'), 'an example only where one is given');
	pg_assert_true(strpos($html, 'English') !== false && strpos($html, 'English') < strpos($html, '<h3'),
		'the line about the English reference comes before the topics');
}

// Groups are h2 and endpoints h3 on both pages, and the index opens with the
// Conventions link.
function test_api_console_endpoint_headings()
{
	$parts = api_console_endpoints_html();

	pg_assert_true(strpos($parts['nav'], 'href="#conventions"') === 0 || strpos($parts['nav'], '#conventions') < strpos($parts['nav'], '#ep_'),
		'the index opens with Conventions');
	pg_assert_same(0, substr_count($parts['panels'], '<div class="doc-group-h"'), 'no group heading is a div');
	pg_assert_same(0, substr_count($parts['panels'], '<div class="doc-ep-head"'), 'no endpoint heading is a div');
	pg_assert_same($parts['groups'] + 2, substr_count($parts['panels'], '<h2 class="doc-group-h"'),
		'a group heading per group, Webhook events and Errors');
	pg_assert_same($parts['count'] + 2, substr_count($parts['panels'], '<h3 class="doc-ep-head"'),
		'an endpoint heading per endpoint, the event block and the error block');
}

// The source of an API file, for the figures that live only as literals there.
function api_console_conventions_test_source($file)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	pg_assert_true($source !== '', $file . ' is read');

	return $source;
}

// One number the code spells, found by $pattern in $file.
function api_console_conventions_test_number($file, $pattern)
{
	$found = preg_match($pattern, api_console_conventions_test_source($file), $match);
	pg_assert_same(1, $found, $pattern . ' is found in ' . $file);

	return $found ? (int) $match[1] : -1;
}

// Every figure the section states is the one the code holds, so the text
// cannot drift from the API: a change to a figure fails here until the
// sentence says the new one.
function test_api_console_conventions_figures_follow_the_code()
{
	$text = array();

	foreach (api_console_conventions('https://example.test/pinegrap/integration.php') as $item) {
		$text[$item['id']] = $item['text'];
	}

	// Idempotency-Key: its length cap, the pending retry's wait, the day a record lives.
	$max = api_console_conventions_test_number('includes/api/idempotency.php', '/strlen\(\$key\) > (\d+)/');
	pg_assert_contains('up to ' . $max . ' characters', $text['idempotency']);

	$retry = api_console_conventions_test_number('includes/api/idempotency.php', "/api_extra_headers\('Retry-After', '(\d+)'\);\s*api_fail\(409, 'idempotency_in_progress'/");
	pg_assert_contains('Retry-After: ' . $retry, $text['idempotency']);

	$kept = api_console_conventions_test_number('includes/api/maintenance.php', "/DELETE FROM api_idempotency WHERE created_timestamp < '\" \. \(time\(\) - (\d+)\)/");
	pg_assert_same(86400, $kept, 'a stored answer is purged after a day');
	pg_assert_contains('for about a day', $text['idempotency']);

	// Rate limit: the default a new application gets, from the insert and the column.
	$insert = api_console_conventions_test_number('includes/api/keys.php', "/'active',\s*'(\d+)',\s*'\" \. \\\$kind/");
	$column = api_console_conventions_test_number('includes/migrations/2026.4.4.php', '/rate_limit_per_min\s+SMALLINT UNSIGNED NOT NULL DEFAULT (\d+)/');
	pg_assert_same($column, $insert, 'a new application and the column default agree');
	pg_assert_contains('(' . $insert . ' by default)', $text['rate-limit']);

	// The statuses whose error carries docs, as api_fail() lists them.
	$found = preg_match('/in_array\(\$status_code, array\(([\d, ]+)\), true\)\s*&& function_exists\(\'api_openapi_base_url\'\)/',
		api_console_conventions_test_source('includes/api/response.php'), $match);
	pg_assert_same(1, $found, 'the docs statuses are found in api_fail()');
	pg_assert_contains('(' . ($found ? $match[1] : '') . ') also carries docs', $text['errors']);

	// Each error code named, with the status the catalogue gives it.
	$catalogue = api_error_catalogue();

	foreach (array('idempotency' => array('idempotency_key_reused' => 409, 'idempotency_in_progress' => 409),
		'dry-run' => array('dry_run_unsupported' => 400),
		'rate-limit' => array('rate_limited' => 429)) as $id => $codes) {
		foreach ($codes as $code => $status) {
			pg_assert_true(isset($catalogue[$code]) && ((int) $catalogue[$code][0] === $status), $code . ' is ' . $status . ' in the catalogue');
			pg_assert_contains($status . ' ' . $code, $text[$id]);
		}
	}

	// Each header named is one the code reads or sends, in the file that does.
	foreach (array(
		'credentials' => array('X-Pinegrap-Key' => 'includes/api/auth.php', 'X-Pinegrap-Secret' => 'includes/api/auth.php'),
		'errors'      => array('X-Request-Id' => 'includes/api/response.php'),
		'idempotency' => array('Idempotency-Key' => 'includes/api/idempotency.php', 'Idempotent-Replay' => 'includes/api/idempotency.php'),
		'dry-run'     => array('X-Dry-Run' => 'includes/api/dry_run.php'),
		'etag'        => array('ETag' => 'includes/api/response.php', 'If-None-Match' => 'includes/api/response.php', 'Cache-Control: no-store' => 'includes/api/response.php'),
		'rate-limit'  => array('X-RateLimit-Limit' => 'includes/api/ratelimit.php', 'X-RateLimit-Remaining' => 'includes/api/ratelimit.php', 'X-RateLimit-Reset' => 'includes/api/ratelimit.php', 'Retry-After' => 'includes/api/ratelimit.php'),
		'post-twins'  => array('X-HTTP-Method-Override' => 'includes/api/router.php')
	) as $id => $headers) {
		foreach ($headers as $header => $file) {
			pg_assert_contains($header, $text[$id]);
			$name = explode(':', $header)[0];
			pg_assert_true(strpos(api_console_conventions_test_source($file), "'" . $name) !== false, $name . ' is in ' . $file);
		}
	}

	// The fields the envelopes are described with are the ones they are built with.
	$response = api_console_conventions_test_source('includes/api/response.php');

	foreach (array('code', 'message', 'request_id', 'field', 'docs', 'limit', 'has_more', 'next_cursor') as $key) {
		pg_assert_true(strpos($response, "'" . $key . "'") !== false, $key . ' is built in response.php');
	}

	$dry = api_console_conventions_test_source('includes/api/dry_run.php');

	foreach (array('dry_run', 'would', 'resource', 'detail') as $key) {
		pg_assert_true(strpos($dry, "'" . $key . "'") !== false, $key . ' is built in dry_run.php');
		pg_assert_contains($key, $text['dry-run']);
	}
}
