<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Runs POST /batch's entries through the real includes/api/response.php and
 * router.php (api_batch_answers()): each entry's handler answers into
 * api_send_capture(), and the request is left as it was. Prints the outcomes
 * as JSON after @@RESULT@@.
 *
 * Not a test file: tests/api_batch_test.php runs it as a process of its own,
 * because other test files declare stand-ins for some of response.php's
 * functions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('PG_TEST_RUNNER', true);
define('PG_API_ENTRY', true);

$repository = dirname(dirname(__DIR__));

require_once($repository . '/pinegrap/functions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/response.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/log.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/manners.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/router.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/batch.php');

// The things a route reads: thing 7 exists, thing 8 does not.
function api_batch_run_thing($params)
{
	if ((int) $params['id'] !== 7) {
		api_fail_not_found('thing');
	}

	api_extra_headers('X-Thing-Header', 'leaked');

	api_ok(array('id' => 7, 'name' => 'Pen', 'colour' => 'blue'));
}

function api_batch_run_list($params)
{
	api_ok_list(array(array('id' => 1, 'name' => 'A', 'colour' => 'red'), array('id' => 2, 'name' => 'B', 'colour' => 'green')), (int) $params['limit']);
}

function api_batch_run_secret($params)
{
	api_ok(array('id' => 1, 'secret' => true));
}

function api_batch_run_broken($params)
{
	throw new RuntimeException('broken on purpose');
}

function api_batch_run_silent($params)
{
	// Answers nothing: the router's 500.
}

$routes = array(
	array('id' => 'things.list', 'method' => 'GET', 'path' => '/things', 'scope' => 'things:read', 'handler' => 'api_batch_run_list',
		'params' => array(array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250, 'default' => 50))),
	array('id' => 'things.get', 'method' => 'GET', 'path' => '/things/{id}', 'scope' => 'things:read', 'handler' => 'api_batch_run_thing',
		'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int', 'required' => true))),
	array('id' => 'things.update', 'method' => 'POST', 'also_accepts' => array('PATCH'), 'path' => '/things/{id}', 'scope' => 'things:write', 'handler' => 'api_batch_run_thing',
		'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int', 'required' => true))),
	array('id' => 'things.archive', 'method' => 'POST', 'path' => '/things/{id}/archive', 'scope' => 'things:read', 'handler' => 'api_batch_run_thing',
		'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int', 'required' => true))),
	array('id' => 'things.file', 'method' => 'GET', 'path' => '/things/{id}/file', 'scope' => 'things:read', 'binary' => true, 'handler' => 'api_batch_run_thing',
		'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int', 'required' => true))),
	array('id' => 'secrets.get', 'method' => 'GET', 'path' => '/secrets', 'scope' => 'secrets:read', 'handler' => 'api_batch_run_secret', 'params' => array()),
	array('id' => 'broken', 'method' => 'GET', 'path' => '/broken', 'scope' => 'things:read', 'handler' => 'api_batch_run_broken', 'params' => array()),
	array('id' => 'silent', 'method' => 'GET', 'path' => '/silent', 'scope' => 'things:read', 'handler' => 'api_batch_run_silent', 'params' => array()),
	array('id' => 'batch', 'method' => 'POST', 'path' => '/batch', 'scope' => '', 'handler' => 'api_batch_run', 'params' => array()),
);

$hits = array();

$count_hit = function ($index) use (&$hits) {
	$hits[] = $index;

	// The fourth entry finds the limit reached.
	if ($index === 3) {
		api_extra_headers('Retry-After', '30');
		api_extra_headers('X-RateLimit-Remaining', '0');
		api_fail(429, 'rate_limited', 'Rate limit reached.');
	}

	api_extra_headers('X-RateLimit-Remaining', (string) (100 - $index));
};

$require_scope = function ($scope) {
	if ($scope !== 'things:read') {
		api_fail(403, 'insufficient_scope', 'No permission.');
	}
};

// The batch's own request, as the router left it.
api_request_method('POST');
api_request_path('/batch');
api_request_input(array('requests' => array()));
api_request_body_keys(array('requests'));
api_current_route($routes[8]);
api_extra_headers('X-RateLimit-Limit', '100');

$tag = api_etag_of_json(api_json_encode(array('id' => 7, 'name' => 'Pen', 'colour' => 'blue')));

$entries = array(
	array('id' => 'one', 'path' => '/things/7'),
	array('id' => 'held', 'path' => '/things/7', 'if_none_match' => $tag),
	array('id' => 'gone', 'path' => '/things/8'),
	array('id' => 'limited', 'path' => '/things/7'),
	array('id' => 'trimmed', 'path' => '/things/7', 'query' => array('fields' => 'name')),
	array('id' => 'list', 'path' => '/things?limit=2', 'query' => array('fields' => 'colour')),
	array('id' => 'bad-limit', 'path' => '/things', 'query' => array('limit' => 'many')),
	array('id' => 'scope', 'path' => '/secrets'),
	array('id' => 'unknown', 'path' => '/nowhere'),
	array('id' => 'write', 'path' => '/things/7/archive'),
	array('id' => 'file', 'path' => '/things/7/file'),
	array('id' => 'self', 'path' => '/batch'),
	array('path' => 'things/7'),
	array('id' => 'extra', 'path' => '/things/7', 'method' => 'POST'),
	'not an entry',
	array('id' => 'broken', 'path' => '/broken'),
	array('id' => 'silent', 'path' => '/silent'),
	array('id' => 12, 'path' => '//things//7/'),
);

$answers = api_batch_answers($entries, $routes, $count_hit, $require_scope);

$result = array('answers' => array());

foreach ($answers as $answer) {
	$result['answers'][] = array(
		'id'     => $answer['id'],
		'status' => $answer['status'],
		'etag'   => $answer['etag'],
		'code'   => isset($answer['body']['error']['code']) ? $answer['body']['error']['code'] : null,
		'field'  => isset($answer['body']['error']['field']) ? $answer['body']['error']['field'] : null,
		'body'   => isset($answer['body']['error']) ? null : $answer['body'],
	);
}

$result['tag'] = $tag;
$result['hits'] = $hits;
$result['method'] = api_request_method();
$result['path'] = api_request_path();
$result['input'] = api_request_input();
$result['body_keys'] = api_request_body_keys();
$result['route'] = api_current_route()['id'];
$result['capture'] = api_send_capture();
$result['if_none_match'] = api_header('If-None-Match');
$result['fields_left'] = array_key_exists('fields', $_GET);
$result['headers'] = api_extra_headers();

echo '@@RESULT@@' . json_encode($result);
