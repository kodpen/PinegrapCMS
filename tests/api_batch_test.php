<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for POST /batch (includes/api/resources/batch.php): the entry check,
 * which routes may be entries, finding an entry's route, the runs through the
 * real response.php, and the page cache left alone by a batch.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/router.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/batch.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_cache.php');

// A full entry is read as sent; id as text.
function test_api_batch_entry_full()
{
	$checked = api_batch_entry_check(array('id' => 'a', 'path' => '/products/12', 'query' => array('fields' => 'id,name'), 'if_none_match' => ' W/"abc" '));

	pg_assert_same(array('id' => 'a', 'path' => '/products/12', 'query' => array('fields' => 'id,name'), 'if_none_match' => 'W/"abc"'), $checked);

	$number = api_batch_entry_check(array('id' => 4, 'path' => '/meta'));

	pg_assert_same('4', $number['id'], 'a number id is echoed as text');
	pg_assert_same(array(), $number['query'], 'no query');
	pg_assert_same('', $number['if_none_match'], 'no tag');
}

// The path is normalised as the router does, and a query in it is read.
function test_api_batch_entry_path_and_query()
{
	$checked = api_batch_entry_check(array('path' => '//orders//?status=new&limit=5', 'query' => array('limit' => 10, 'total' => true, 'skip' => null)));

	pg_assert_same('/orders', $checked['path']);
	pg_assert_same(array('status' => 'new', 'limit' => '10', 'total' => 'true'), $checked['query'], 'the query object wins; null left out; true as a word');
	pg_assert_same(null, $checked['id']);

	$string = api_batch_entry_check(array('path' => '/orders', 'query' => '?status=paid&path=/x'));

	pg_assert_same(array('status' => 'paid'), $string['query'], 'a query string, path dropped');
}

// What the check refuses, and under which field.
function test_api_batch_entry_refusals()
{
	$cases = array(
		array('not an entry', '', 'not_object'),
		array(array('/meta'), '', 'not_object'),
		array(array('path' => '/meta', 'method' => 'POST'), 'method', 'unknown_key'),
		array(array('path' => '/meta', 'id' => array('x')), 'id', 'id'),
		array(array('path' => '/meta', 'id' => str_repeat('x', 101)), 'id', 'id'),
		array(array('id' => 'a'), 'path', 'path'),
		array(array('path' => 'meta'), 'path', 'path'),
		array(array('path' => '   '), 'path', 'path'),
		array(array('path' => 7), 'path', 'path'),
		array(array('path' => '/meta', 'query' => array('a', 'b')), 'query', 'query'),
		array(array('path' => '/meta', 'query' => array('a' => array('b' => 1))), 'query', 'query'),
		array(array('path' => '/meta', 'query' => 5), 'query', 'query'),
		array(array('path' => '/meta', 'if_none_match' => 5), 'if_none_match', 'if_none_match'),
	);

	foreach ($cases as $number => $case) {
		$checked = api_batch_entry_check($case[0]);

		pg_assert_same(array('error' => array('field' => $case[1], 'problem' => $case[2])), $checked, 'case ' . $number);
	}
}

// Only credentialed JSON reads may be entries.
function test_api_batch_route_allowed()
{
	pg_assert_true(api_batch_route_allowed(array('id' => 'products.get', 'method' => 'GET', 'scope' => 'products:read')), 'a read');
	pg_assert_true(api_batch_route_allowed(array('id' => 'auth.me', 'method' => 'GET', 'scope' => '')), 'a read with no scope');
	pg_assert_false(api_batch_route_allowed(array('id' => 'products.update', 'method' => 'POST', 'scope' => 'products:write')), 'a write');
	pg_assert_false(api_batch_route_allowed(array('id' => 'x.file', 'method' => 'GET', 'scope' => 'files:read', 'binary' => true)), 'a file');
	pg_assert_false(api_batch_route_allowed(array('id' => 'x.public', 'method' => 'GET', 'scope' => '', 'public' => true)), 'a public route');

	foreach (array('batch', 'openapi', 'docs', 'docs.endpoints', 'discovery') as $id) {
		pg_assert_false(api_batch_route_allowed(array('id' => $id, 'method' => 'GET', 'scope' => '')), $id);
	}
}

// Every GET route of the real schema that is a token route, a file or the
// batch itself is refused; every other GET may be an entry.
function test_api_batch_real_routes()
{
	$batch = null;

	foreach (array_merge(api_schema(), erp_api_routes(), translate_api_routes(), ws_api_routes()) as $route) {
		if ($route['id'] === 'batch') {
			$batch = $route;
		}

		if (!empty($route['binary']) || !empty($route['public']) || ($route['method'] !== 'GET')) {
			pg_assert_false(api_batch_route_allowed($route), $route['id']);
		}
	}

	pg_assert_true(is_array($batch), 'the batch route is declared');
	pg_assert_same(false, $batch['mcp'], 'no MCP tool');
	pg_assert_same('', $batch['scope'], 'no scope of its own');
	pg_assert_true(empty($batch['public']), 'needs a caller');
}

// Finding the route: the first GET, its path values, a write and an unknown
// address told apart.
function test_api_batch_route_find()
{
	$routes = array(
		array('id' => 'things.update', 'method' => 'POST', 'path' => '/things/{id}', 'scope' => 'w', 'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int'))),
		array('id' => 'things.get', 'method' => 'GET', 'path' => '/things/{id}', 'scope' => 'r', 'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int'))),
		array('id' => 'things.archive', 'method' => 'POST', 'path' => '/things/{id}/archive', 'scope' => 'w', 'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int'))),
		array('id' => 'things.file', 'method' => 'GET', 'binary' => true, 'path' => '/things/{id}/file', 'scope' => 'r', 'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int'))),
		array('id' => 'tags.get', 'method' => 'GET', 'path' => '/tags/{slug}', 'scope' => 'r', 'params' => array(array('name' => 'slug', 'in' => 'path', 'type' => 'string'))),
	);

	$found = api_batch_route_find('/things/7', $routes);

	pg_assert_same('things.get', isset($found['route']) ? $found['route']['id'] : null, 'the GET of the address');
	pg_assert_same(array('id' => '7'), $found['route']['path_values']);

	$slug = api_batch_route_find('/tags/a%20b', $routes);

	pg_assert_same(array('slug' => 'a b'), $slug['route']['path_values'], 'decoded as the router does');

	pg_assert_same(array('refused' => 'not_batchable'), api_batch_route_find('/things/7/archive', $routes), 'a write');
	pg_assert_same(array('refused' => 'not_batchable'), api_batch_route_find('/things/7/file', $routes), 'a file');
	pg_assert_same(array('refused' => 'unknown'), api_batch_route_find('/things/x', $routes), 'no route');
	pg_assert_same(array('refused' => 'unknown'), api_batch_route_find('/nowhere', $routes), 'no route');
}

// The runner's outcomes, from the helper process.
function api_batch_test_run_result()
{
	static $result = null;

	if ($result !== null) {
		return $result;
	}

	$output = '';

	if (function_exists('shell_exec')) {
		$command = escapeshellarg(PHP_BINARY) . ' '
			. escapeshellarg(__DIR__ . '/helpers/api_batch_run.php') . ' 2>&1';

		$output = (string) shell_exec($command);
	}

	$marker = strrpos($output, '@@RESULT@@');
	$decoded = ($marker === false) ? null : json_decode(substr($output, $marker + 10), true);

	$result = is_array($decoded) ? $decoded : array('error' => substr($output, 0, 2000));

	return $result;
}

// Each entry answers on its own, in the order asked.
function test_api_batch_answers_each_entry()
{
	$result = api_batch_test_run_result();

	pg_assert_false(isset($result['error']), 'the helper answered: ' . (isset($result['error']) ? $result['error'] : ''));

	$by_id = array();
	$order = array();

	foreach ($result['answers'] as $index => $answer) {
		$order[] = $answer['id'];
		$by_id[($answer['id'] === null) ? 'none' . $index : $answer['id']] = $answer;
	}

	pg_assert_same(array('one', 'held', 'gone', 'limited', 'trimmed', 'list', 'bad-limit', 'scope', 'unknown', 'write', 'file', 'self', null, 'extra', null, 'broken', 'silent', '12'), $order, 'in the order asked');

	pg_assert_same(200, $by_id['one']['status']);
	pg_assert_same($result['tag'], $by_id['one']['etag'], 'the tag a GET of its own sends');
	pg_assert_same(array('id' => 7, 'name' => 'Pen', 'colour' => 'blue'), $by_id['one']['body']);

	pg_assert_same(304, $by_id['held']['status'], 'a matching if_none_match');
	pg_assert_same(null, $by_id['held']['body'], 'no body on a 304');
	pg_assert_same($result['tag'], $by_id['held']['etag']);

	pg_assert_same(array(404, 'not_found'), array($by_id['gone']['status'], $by_id['gone']['code']), 'the route\'s own 404');
	pg_assert_same(array(429, 'rate_limited'), array($by_id['limited']['status'], $by_id['limited']['code']), 'the rate limit refuses one entry');
	pg_assert_same(array(403, 'insufficient_scope'), array($by_id['scope']['status'], $by_id['scope']['code']), 'the route\'s own scope');
	pg_assert_same(array(422, 'validation_failed', 'limit'), array($by_id['bad-limit']['status'], $by_id['bad-limit']['code'], $by_id['bad-limit']['field']), 'the route\'s own validation');
	pg_assert_same(array(404, 'unknown_endpoint'), array($by_id['unknown']['status'], $by_id['unknown']['code']));

	foreach (array('write', 'file', 'self') as $id) {
		pg_assert_same(array(400, 'not_batchable'), array($by_id[$id]['status'], $by_id[$id]['code']), $id);
	}

	pg_assert_same(array(422, 'requests[12].path'), array($by_id['none12']['status'], $by_id['none12']['field']), 'a path without /');
	pg_assert_same(array(422, 'requests[13].method'), array($by_id['extra']['status'], $by_id['extra']['field']), 'an unknown key');
	pg_assert_same(array(422, 'requests[14]'), array($by_id['none14']['status'], $by_id['none14']['field']), 'not an object');
	pg_assert_same(array(500, 'server_error'), array($by_id['broken']['status'], $by_id['broken']['code']), 'a fault fails its own entry');
	pg_assert_same(array(500, 'server_error'), array($by_id['silent']['status'], $by_id['silent']['code']), 'a handler that answered nothing');

	pg_assert_same(200, $by_id['12']['status'], 'a doubled slash reaches the route');
}

// fields= trims an entry's answer, and the tag is of the trimmed answer.
function test_api_batch_fields()
{
	$result = api_batch_test_run_result();

	$trimmed = $result['answers'][4];

	pg_assert_same(array('id' => 7, 'name' => 'Pen'), $trimmed['body']);
	pg_assert_true($trimmed['etag'] !== $result['tag'], 'the tag of the trimmed answer');

	$list = $result['answers'][5];

	pg_assert_same(array(array('id' => 1, 'colour' => 'red'), array('id' => 2, 'colour' => 'green')), $list['body']['data'], 'a listing row by row');
	pg_assert_same(2, $list['body']['page']['limit'], 'the query in the path');
}

// Every entry is counted, and the request is left as the batch had it.
function test_api_batch_restores_the_request()
{
	$result = api_batch_test_run_result();

	pg_assert_same(range(0, 17), $result['hits'], 'every entry counted');
	pg_assert_same('POST', $result['method']);
	pg_assert_same('/batch', $result['path']);
	pg_assert_same(array('requests' => array()), $result['input']);
	pg_assert_same(array('requests'), $result['body_keys']);
	pg_assert_same('batch', $result['route']);
	pg_assert_same(false, $result['capture'], 'capture switched off');
	pg_assert_same('', $result['if_none_match']);
	pg_assert_same(false, $result['fields_left'], 'no ?fields= left behind');
	pg_assert_same(array('X-RateLimit-Limit' => '100', 'X-RateLimit-Remaining' => '83'), $result['headers'], 'an entry\'s headers dropped, the latest count kept');
}

// A batch is a read: it leaves the page cache alone; other API writes do not.
function test_api_batch_page_cache()
{
	pg_assert_true(pg_page_cache_api_path_reads('/batch'));
	pg_assert_true(pg_page_cache_api_path_reads('/batch/'));
	pg_assert_true(pg_page_cache_api_path_reads('//Batch'));
	pg_assert_true(pg_page_cache_api_path_reads('/pinegrap/integration.php/batch'), 'IIS ORIG_PATH_INFO');
	pg_assert_false(pg_page_cache_api_path_reads('/products/batch'));
	pg_assert_false(pg_page_cache_api_path_reads('/products'));
	pg_assert_false(pg_page_cache_api_path_reads(''));

	pg_assert_false(pg_page_cache_request_dirties('POST', 'integration.php', false, true, '/batch'), 'a batch');
	pg_assert_true(pg_page_cache_request_dirties('POST', 'integration.php', false, true, '/products'), 'an API write');
	pg_assert_true(pg_page_cache_request_dirties('POST', 'integration.php', false, true), 'no path given');
}
