<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of tools/check_api_answers.php: the comparison of
 * an answer with the OpenAPI document (status, JSON, required fields, types,
 * null, undeclared fields), the routes it leaves out, and the walk from a
 * list's first id to one record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/check_api_answers.php');

// A small document in the shape api_openapi_build() writes.
function api_answer_check_test_document()
{
	$json = array('application/json' => array('schema' => null));

	$list = function ($object) {
		return array('type' => 'object', 'properties' => array(
			'data' => array('type' => 'array', 'items' => array('$ref' => '#/components/schemas/' . $object)),
			'page' => array('$ref' => '#/components/schemas/Paging'),
		));
	};

	$answer = function ($schema) use ($json) {
		$json['application/json']['schema'] = $schema;
		return array('200' => array('description' => 'Success', 'content' => $json), '404' => array('description' => 'No such record.'));
	};

	return array(
		'openapi' => '3.0.3',
		'paths' => array(
			'/users/{id}' => array('get' => array('operationId' => 'users_read', 'responses' => $answer(array('$ref' => '#/components/schemas/User')))),
			'/users' => array('get' => array('operationId' => 'users_list', 'responses' => $answer($list('User')))),
			'/orders/{id}/items/{item_id}' => array('get' => array('operationId' => 'orders_items_read', 'responses' => $answer(array('$ref' => '#/components/schemas/User')))),
			'/orders' => array(
				'get' => array('operationId' => 'orders_list', 'responses' => $answer($list('User'))),
				'post' => array('operationId' => 'orders_create', 'responses' => $answer(array('$ref' => '#/components/schemas/User'))),
			),
			'/orders/{id}/items' => array('get' => array('operationId' => 'orders_items_list', 'responses' => $answer($list('User')))),
			'/files/{id}/download' => array('get' => array('operationId' => 'files_download', 'responses' => array(
				'200' => array('description' => 'The file itself', 'content' => array('application/octet-stream' => array('schema' => array('type' => 'string', 'format' => 'binary')))),
			))),
			'/gift-cards' => array('get' => array('operationId' => 'gift_cards_list', 'responses' => $answer($list('User')))),
			'/search' => array('get' => array(
				'operationId' => 'search_list',
				'parameters' => array(array('name' => 'q', 'in' => 'query', 'required' => true, 'schema' => array('type' => 'string'))),
				'responses' => $answer($list('User')),
			)),
		),
		'components' => array('schemas' => array(
			'Paging' => array('type' => 'object', 'properties' => array(
				'limit' => array('type' => 'integer'),
				'has_more' => array('type' => 'boolean'),
				'next_cursor' => array('type' => 'string', 'nullable' => true),
			)),
			'User' => array('type' => 'object', 'required' => array('id', 'username'), 'properties' => array(
				'id' => array('type' => 'integer'),
				'username' => array('type' => 'string'),
				'phone' => array('type' => 'string', 'nullable' => true),
				'created_at' => array('type' => 'string', 'format' => 'date-time'),
				'birthday' => array('type' => 'string', 'format' => 'date'),
				'role' => array('type' => 'string', 'enum' => array('admin', 'user')),
				'device' => array('nullable' => true, 'allOf' => array(array('$ref' => '#/components/schemas/Device'))),
				'issues' => array('type' => 'array', 'items' => array('type' => 'object', 'properties' => array(
					'code' => array('type' => 'string'),
					'points' => array('type' => 'number'),
				))),
				'extra' => array('type' => 'object'),
			)),
			'Device' => array('type' => 'object', 'properties' => array('name' => array('type' => 'string'))),
		)),
	);
}

function api_answer_check_test_user()
{
	return array(
		'id' => 5,
		'username' => 'ada',
		'phone' => null,
		'created_at' => '2026-11-17T10:00:00+03:00',
		'birthday' => '1990-04-01',
		'role' => 'admin',
		'device' => null,
		'issues' => array(array('code' => 'title', 'points' => 1.5)),
		'extra' => array('anything' => true),
	);
}

function api_answer_check_test_compare($user)
{
	$document = api_answer_check_test_document();

	return api_answer_check_value($user, array('$ref' => '#/components/schemas/User'), $document, '');
}

function test_api_answer_check_a_true_answer_has_no_difference()
{
	pg_assert_same(array(), api_answer_check_test_compare(api_answer_check_test_user()), 'a record that keeps the document');

	$user = api_answer_check_test_user();
	$user['device'] = array('name' => 'Phone');
	$user['phone'] = '555';
	$user['issues'] = array();
	pg_assert_same(array(), api_answer_check_test_compare($user), 'a nullable reference filled, an empty list');
}

function test_api_answer_check_a_missing_required_field()
{
	$user = api_answer_check_test_user();
	unset($user['username']);

	pg_assert_same(array('username: required, missing'), api_answer_check_test_compare($user), 'a required field left out');
}

function test_api_answer_check_a_wrong_type()
{
	$user = api_answer_check_test_user();
	$user['id'] = '5';
	$user['issues'][0]['points'] = 'many';

	pg_assert_same(array(
		'id: string, declared integer',
		'issues[].points: string, declared number',
	), api_answer_check_test_compare($user), 'a number as text, in a record and in a list item');

	$user = api_answer_check_test_user();
	$user['created_at'] = '2026-11-17 10:00:00';
	$user['birthday'] = '01.04.1990';
	$user['role'] = 'owner';

	pg_assert_same(array(
		'created_at: "2026-11-17 10:00:00" is not a date-time',
		'birthday: "01.04.1990" is not a date',
		'role: "owner" is not one of admin, user',
	), api_answer_check_test_compare($user), 'formats and enums');
}

function test_api_answer_check_a_null_not_declared_nullable()
{
	$user = api_answer_check_test_user();
	$user['username'] = null;
	$user['created_at'] = null;

	pg_assert_same(array(
		'username: null, not declared nullable',
		'created_at: null, not declared nullable',
	), api_answer_check_test_compare($user), 'null where the document promises a value');
}

// #10090: the SEO issue rows carried fields the document did not name.
function test_api_answer_check_an_undeclared_field()
{
	$user = api_answer_check_test_user();
	$user['issues'][] = array('code' => 'meta', 'points' => 2, 'source' => 'page', 'label' => 'Meta', 'weight' => 3, 'earned' => 1);
	$user['issues'][] = array('code' => 'h1', 'points' => 0, 'source' => 'page');
	$user['token'] = 'x';

	pg_assert_same(array(
		'issues[].source: not declared',
		'issues[].label: not declared',
		'issues[].weight: not declared',
		'issues[].earned: not declared',
		'token: not declared',
	), api_answer_check_test_compare($user), 'each undeclared field once, however many rows carry it');
}

// #10088 and #10089: the route answered 500, or the site's HTML error page.
function test_api_answer_check_a_failed_or_non_json_answer()
{
	$document = api_answer_check_test_document();
	$route = null;

	foreach (api_answer_check_routes($document) as $one) {
		if ($one['path'] === '/users/{id}') {
			$route = $one;
		}
	}

	$json = "HTTP/1.1 500\r\nContent-Type: application/json\r\n\r\n";

	pg_assert_same(
		array('status 500 server_error (request_id r-1), declared 200'),
		api_answer_check_answer($route, array('status' => 500, 'headers' => $json, 'body' => '{"error":{"code":"server_error","request_id":"r-1"}}', 'error' => ''), $document),
		'a 500 envelope names its code and request id'
	);

	pg_assert_same(
		array('status 500, declared 200', 'not JSON (text/html): <br /> <b>Fatal error</b>'),
		api_answer_check_answer($route, array('status' => 500, 'headers' => "HTTP/1.1 500\r\nContent-Type: text/html\r\n\r\n", 'body' => "<br />\n<b>Fatal error</b>", 'error' => ''), $document),
		'an HTML error page'
	);

	pg_assert_same(
		array('not JSON (application/json): {"id": 5,'),
		api_answer_check_answer($route, array('status' => 200, 'headers' => $json, 'body' => '{"id": 5,', 'error' => ''), $document),
		'a cut answer'
	);

	pg_assert_same(
		array(),
		api_answer_check_answer($route, array('status' => 200, 'headers' => $json, 'body' => json_encode(api_answer_check_test_user()), 'error' => ''), $document),
		'a true answer'
	);

	pg_assert_same(
		array('network: timed out'),
		api_answer_check_answer($route, array('status' => 0, 'headers' => '', 'body' => '', 'error' => 'timed out'), $document),
		'no answer at all'
	);
}

function test_api_answer_check_skips_money_binary_and_required_parameters()
{
	$routes = api_answer_check_routes(api_answer_check_test_document());
	$money = array('GET /gift-cards' => true);
	$why = array();

	foreach ($routes as $route) {
		$why[$route['path']] = api_answer_check_skip($route, $money);
	}

	pg_assert_same('moves money', $why['/gift-cards'], 'a route marked moves_money');
	pg_assert_same('binary answer', $why['/files/{id}/download'], 'a file');
	pg_assert_same('needs the query parameter q', $why['/search'], 'a required query parameter the tool cannot guess');
	pg_assert_same('', $why['/users'], 'a list');
	pg_assert_same('', $why['/users/{id}'], 'a record');
}

function test_api_answer_check_reads_lists_first_then_records()
{
	$paths = array();

	foreach (api_answer_check_routes(api_answer_check_test_document()) as $route) {
		$paths[] = $route['method'] . ' ' . $route['path'];
	}

	// GET only; fewer path parameters first, so a record waits for the list
	// its id comes from.
	pg_assert_same(array(
		'GET /gift-cards',
		'GET /orders',
		'GET /search',
		'GET /users',
		'GET /files/{id}/download',
		'GET /orders/{id}/items',
		'GET /users/{id}',
		'GET /orders/{id}/items/{item_id}',
	), $paths, 'lists before records');
}

function test_api_answer_check_walks_from_a_list_to_a_record()
{
	pg_assert_same(7, api_answer_check_first_id(array('data' => array(array('id' => 7), array('id' => 8)), 'page' => array())), 'the first id of a page');
	pg_assert_same('ab-1', api_answer_check_first_id(array('data' => array(array('id' => 'ab-1')))), 'a text id');
	pg_assert_same(null, api_answer_check_first_id(array('data' => array(), 'page' => array())), 'an empty page');
	pg_assert_same(null, api_answer_check_first_id(array('id' => 3)), 'a record is no list');

	$first = array('/users' => 5, '/orders' => 9, '/orders/9/items' => 'a b');

	pg_assert_same('/users/5', api_answer_check_fill_path('/users/{id}', $first), 'one parameter');
	pg_assert_same('/orders/9/items', api_answer_check_fill_path('/orders/{id}/items', $first), 'a nested list');
	pg_assert_same('/orders/9/items/a%20b', api_answer_check_fill_path('/orders/{id}/items/{item_id}', $first), 'two levels, encoded');
	pg_assert_same(null, api_answer_check_fill_path('/files/{id}/download', $first), 'no list read for it');
	pg_assert_same('/users', api_answer_check_fill_path('/users', $first), 'a path without parameters');

	$first['/files'] = null;
	pg_assert_same(null, api_answer_check_fill_path('/files/{id}/download', $first), 'the list was empty');
}

function test_api_answer_check_keeps_under_the_rate_limit()
{
	pg_assert_same(0, api_answer_check_wait("X-RateLimit-Remaining: 40\r\nX-RateLimit-Reset: 1060\r\n", 200, 1000), 'room left');
	pg_assert_same(61, api_answer_check_wait("X-RateLimit-Remaining: 1\r\nX-RateLimit-Reset: 1060\r\n", 200, 1000), 'the last call of the window waits for the next one');
	pg_assert_same(30, api_answer_check_wait("Retry-After: 30\r\n", 429, 1000), 'a refused call waits as told');
	pg_assert_same(60, api_answer_check_wait('', 429, 1000), 'a refused call with no hint waits a minute');
	pg_assert_same(0, api_answer_check_wait('', 200, 1000), 'no rate headers');
}

function test_api_answer_check_a_refused_key_is_no_difference()
{
	pg_assert_same("the key does not hold this route's scope", api_answer_check_refused(array('status' => 403, 'body' => '{"error":{"code":"insufficient_scope"}}')), 'a scope the key lacks');
	pg_assert_same('refused for this key: forbidden', api_answer_check_refused(array('status' => 403, 'body' => '{"error":{"code":"forbidden"}}')), 'a route for another kind of application');
	pg_assert_same('', api_answer_check_refused(array('status' => 403, 'body' => '<html>')), 'an HTML 403 is a difference');
	pg_assert_same('', api_answer_check_refused(array('status' => 500, 'body' => '{"error":{"code":"server_error"}}')), 'a 500 is a difference');
}
