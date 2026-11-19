<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Runs If-Match through the real includes/api/response.php: a GET handler is
 * run inside an update to read the record's ETag (api_current_etag()), and
 * api_if_match_check() refuses a changed record with the 412 envelope. Prints
 * the outcomes as JSON after @@RESULT@@.
 *
 * Not a test file: tests/api_if_match_test.php runs it as a process of its
 * own, because other test files declare stand-ins for some of response.php's
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/preconditions.php');

// Thrown by the send hook in place of the exit an answer ends with.
class ApiIfMatchAnswered extends Exception
{
	public $status;
	public $body;

	public function __construct($status, $body)
	{
		$this->status = (int) $status;
		$this->body = $body;
		parent::__construct('HTTP ' . (int) $status);
	}
}

api_send_hook(function ($status, $body) {
	throw new ApiIfMatchAnswered($status, $body);
});

// The record the GET handler reads: thing 7.
$GLOBALS['api_if_match_run_name'] = 'Pen';

function api_if_match_run_get($params)
{
	if ((int) $params['id'] !== 7) {
		api_fail_not_found('thing');
	}

	api_ok(array('id' => 7, 'name' => $GLOBALS['api_if_match_run_name']));
}

$routes = array(
	array('id' => 'things.get', 'method' => 'GET', 'path' => '/things/{id}', 'handler' => 'api_if_match_run_get', 'scope' => '',
		'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int', 'required' => true))),
	array('id' => 'things.update', 'method' => 'POST', 'also_accepts' => array('PATCH'), 'path' => '/things/{id}', 'scope' => '',
		'handler' => 'api_if_match_run_get', 'path_values' => array('id' => '7'),
		'params' => array(array('name' => 'id', 'in' => 'path', 'type' => 'int', 'required' => true))),
);

// What api_if_match_check() did: 'write', or the status and error code.
function api_if_match_run_check($route, $routes, $header)
{
	api_header('If-Match', $header);

	try {
		api_if_match_check($route, $routes);
	} catch (ApiIfMatchAnswered $answer) {
		$error = isset($answer->body['error']) ? $answer->body['error'] : array();

		return array(
			'status'     => $answer->status,
			'code'       => isset($error['code']) ? $error['code'] : '',
			'message'    => isset($error['message']) && ($error['message'] !== ''),
			'request_id' => isset($error['request_id']) && ($error['request_id'] !== ''),
		);
	}

	return 'write';
}

api_request_method('POST');
api_request_input(array('name' => 'Pencil'));
api_current_route($routes[1]);

$result = array();

$expected = api_etag_of_json(api_json_encode(array('id' => 7, 'name' => 'Pen')));

$result['tag_read'] = (api_current_etag($routes[0], array('id' => '7')) === $expected);
$result['tag_missing'] = api_current_etag($routes[0], array('id' => '8'));
$result['method_restored'] = api_request_method();
$result['input_restored'] = api_request_input();
$result['route_restored'] = api_current_route()['id'];
$result['capture_off'] = api_send_capture();

$result['absent'] = api_if_match_run_check($routes[1], $routes, '');
$result['matching'] = api_if_match_run_check($routes[1], $routes, $expected);
$result['star'] = api_if_match_run_check($routes[1], $routes, '*');

$GLOBALS['api_if_match_run_name'] = 'Pen (changed)';

$result['changed'] = api_if_match_run_check($routes[1], $routes, $expected);

$missing = $routes[1];
$missing['path_values'] = array('id' => '8');
$result['missing'] = api_if_match_run_check($missing, $routes, '*');

echo '@@RESULT@@' . json_encode($result);
