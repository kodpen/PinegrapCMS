<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the refused calls report (includes/fn/api_refusals.php and
 * GET /reports/api-refusals): ids folded out of paths by the route they
 * reached, rows added up by route, code and application, the guide section
 * of each code, the one read's shape and the answer's totals.
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

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/api_refusals.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/response.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/system.php');

function api_refusals_test_routes()
{
	return array(
		array('id' => 'orders.get', 'method' => 'GET', 'path' => '/orders/{id}',
			'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int'))),
		array('id' => 'orders.list', 'method' => 'GET', 'path' => '/orders', 'params' => array()),
		array('id' => 'pages.update', 'method' => 'POST', 'also_accepts' => array('PATCH'), 'path' => '/pages/{id}',
			'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int'))),
		array('id' => 'files.get', 'method' => 'GET', 'path' => '/files/{name}',
			'params' => array(array('name' => 'name', 'in' => 'path', 'type' => 'string')))
	);
}

function api_refusals_test_row($method, $path, $code, $app, $status, $count, $last, $name = '')
{
	return array('method' => $method, 'path' => $path, 'error_code' => $code, 'app_id' => (string)$app,
		'status_code' => (string)$status, 'refusals' => (string)$count, 'last_at' => (string)$last, 'app_name' => $name);
}

// A path is folded to the pattern of the route it reached; an int id takes
// digits only, a method the route also accepts counts, and an address no
// route takes loses its numbers.
function test_api_refusals_fold_path()
{
	$patterns = pg_api_refusals_patterns(api_refusals_test_routes());

	pg_assert_same(array('orders.get', '/orders/{id}'), pg_api_refusals_fold_path('GET', '/orders/12', $patterns), 'int id');
	pg_assert_same(array('orders.list', '/orders'), pg_api_refusals_fold_path('get', '/orders', $patterns), 'no id, method case');
	pg_assert_same(array('pages.update', '/pages/{id}'), pg_api_refusals_fold_path('PATCH', '/pages/4', $patterns), 'also accepted method');
	pg_assert_same(array('files.get', '/files/{name}'), pg_api_refusals_fold_path('GET', '/files/logo.png', $patterns), 'string placeholder');
	pg_assert_same(array('', '/orders/abc'), pg_api_refusals_fold_path('GET', '/orders/abc', $patterns), 'an int placeholder takes no word');
	pg_assert_same(array('', '/orders/{id}'), pg_api_refusals_fold_path('DELETE', '/orders/12', $patterns), 'wrong method keeps no route');
	pg_assert_same(array('', '/nope/{id}/x/{id}'), pg_api_refusals_fold_path('GET', '/nope/12/x/7', $patterns), 'unknown address');
	pg_assert_same(array('', '/v2'), pg_api_refusals_fold_path('GET', '/v2', $patterns), 'a number inside a word stays');
}

// Rows of one route, code and application are added up whatever the id;
// the busiest comes first; each code carries its guide section.
function test_api_refusals_fold_rows()
{
	$rows = array(
		api_refusals_test_row('GET', '/orders/12', 'not_found', 3, 404, 2, 100, 'Shop sync'),
		api_refusals_test_row('GET', '/orders/13', 'not_found', 3, 404, 3, 250, 'Shop sync'),
		api_refusals_test_row('GET', '/orders/13', 'not_found', 4, 404, 1, 90, 'Other'),
		api_refusals_test_row('POST', '/pages/4', 'validation_failed', 3, 422, 4, 80, 'Shop sync'),
		api_refusals_test_row('GET', '/orders', 'unauthorized', 0, 401, 1, 300, null)
	);

	$folded = pg_api_refusals_fold($rows, api_refusals_test_routes(), array('validation_failed' => 'start#calls'));

	pg_assert_same(4, count($folded), 'four groups');

	pg_assert_same('orders.get', $folded[0]['route'], 'busiest first');
	pg_assert_same(5, $folded[0]['refusals'], 'ids added up');
	pg_assert_same(250, $folded[0]['last_at'], 'latest kept');
	pg_assert_same(3, $folded[0]['app_id'], 'per application');
	pg_assert_same('', $folded[0]['guide'], 'a code without a hint');

	pg_assert_same('validation_failed', $folded[1]['error_code'], 'second');
	pg_assert_same('start#calls', $folded[1]['guide'], 'guide by code');

	pg_assert_same(0, $folded[2]['app_id'], 'ties broken by the latest');
	pg_assert_same('', $folded[2]['app_name'], 'no name for an unknown key');
	pg_assert_same(4, $folded[3]['app_id'], 'another application is its own row');
}

// The one read takes refused rows in the window, grouped, busiest first,
// capped; and every code in the hints registry has a guide.
function test_api_refusals_sql_and_guides()
{
	$sql = pg_api_refusals_sql(1700000000);

	pg_assert_contains('api_request_log.timestamp >= 1700000000', $sql, 'window');
	pg_assert_contains('status_code >= 400', $sql, 'refused only');
	pg_assert_contains('GROUP BY api_request_log.method, api_request_log.path, api_request_log.error_code, api_request_log.app_id', $sql, 'grouped');
	pg_assert_contains('LIMIT ' . pg_api_refusals_read_max(), $sql, 'capped');

	$guides = pg_api_refusals_guides();

	pg_assert_same('designer#page', $guides['design_node_unknown'], 'from the hints registry');
}

// The answer: totals over every row, data cut at limit, nulls for what a row
// does not have.
function test_api_refusals_answer()
{
	$rows = pg_api_refusals_fold(array(
		api_refusals_test_row('GET', '/orders/12', 'not_found', 3, 404, 5, 1700000000, 'Shop sync'),
		api_refusals_test_row('GET', '/x', '', 0, 404, 2, 1700000100),
		api_refusals_test_row('POST', '/pages/4', 'validation_failed', 9, 422, 1, 1700000200)
	), api_refusals_test_routes(), array('validation_failed' => 'start#calls'));

	$answer = api_refusals_answer($rows, 30, 2, 1700086400);

	pg_assert_same(8, $answer['total'], 'every refusal');
	pg_assert_same(3, $answer['rows'], 'every row');
	pg_assert_same(2, count($answer['data']), 'cut at limit');
	pg_assert_same('2023-10-16T22:13:20Z', $answer['from'], 'from');
	pg_assert_same(30, $answer['days'], 'days');

	pg_assert_same('orders.get', $answer['data'][0]['route'], 'route');
	pg_assert_same(3, $answer['data'][0]['application_id'], 'application');
	pg_assert_same('Shop sync', $answer['data'][0]['application_name'], 'name');
	pg_assert_same(null, $answer['data'][0]['guide'], 'no guide');

	pg_assert_same(null, $answer['data'][1]['route'], 'no route');
	pg_assert_same(null, $answer['data'][1]['error_code'], 'no code');
	pg_assert_same(null, $answer['data'][1]['application_id'], 'unknown key');

	$schema = api_refusals_report_schema();

	pg_assert_same(array_keys($schema['data'][0]), array_keys($answer['data'][0]), 'declared fields');
	pg_assert_same(array_keys($schema), array_keys($answer), 'declared top level');
}

// The route is declared once and read with system:read.
function test_api_refusals_route_declared()
{
	$found = null;

	foreach (api_schema() as $route) {
		if ($route['id'] === 'reports.api_refusals') {
			$found = $route;
		}
	}

	pg_assert_true(is_array($found), 'route exists');
	pg_assert_same('system:read', $found['scope'], 'scope');
	pg_assert_same('/reports/api-refusals', $found['path'], 'path');
	pg_assert_true(function_exists($found['handler']), 'handler');
}
