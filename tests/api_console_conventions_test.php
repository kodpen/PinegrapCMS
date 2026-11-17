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
