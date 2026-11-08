<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Runs what the route table documents about each parameter through the
 * router's own validator (api_validate_input(), api_validate_value()) and
 * prints the refusals as JSON: the declared default, every value of an enum,
 * and one request per route built from its declared types and bounds with
 * every parameter filled in.
 *
 * Not a test file: tests/api_route_examples_test.php runs it as a process of
 * its own, because the validator needs the real includes/api/response.php
 * and other test files declare stand-ins for some of its functions.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/time.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/router.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');

// Thrown by the send hook in place of the exit an answer ends with, so a
// refusal comes back as its error body.
class ApiRouteExampleRefused extends Exception
{
	public $body;

	public function __construct($status, $body)
	{
		$this->body = $body;
		parent::__construct('HTTP ' . (int) $status);
	}
}

api_send_hook(function ($status, $body) {
	throw new ApiRouteExampleRefused($status, $body);
});

// null when $call went through, else "code field: message" of the refusal.
function api_route_examples_refusal($call)
{
	try {
		$call();
	} catch (ApiRouteExampleRefused $refused) {
		$error = isset($refused->body['error']) ? $refused->body['error'] : array();

		return (isset($error['code']) ? $error['code'] : '?') . ' '
			. (isset($error['field']) ? $error['field'] : '') . ': '
			. (isset($error['message']) ? $error['message'] : '');
	}

	return null;
}

// One value a client would send for the parameter, read only from its
// declaration: the smallest accepted number, the first enum value, one item.
function api_route_examples_value($param)
{
	$type = isset($param['type']) ? $param['type'] : 'string';

	switch ($type) {
		case 'int':
		case 'money':
			return isset($param['min']) ? (int) $param['min'] : (isset($param['max']) ? min(1, (int) $param['max']) : 1);
		case 'decimal':
			return isset($param['min']) ? $param['min'] : (isset($param['max']) ? min(1, $param['max']) : 1);
		case 'bool':
			return true;
		case 'enum':
			return isset($param['values'][0]) ? $param['values'][0] : '';
		case 'datetime':
			return '2026-11-07';
		case 'object':
			return array('key' => 'value');
		case 'list':
			$of = isset($param['of']) ? $param['of'] : null;

			if ($of === 'integer') {
				return array(1);
			}

			if ($of === 'string') {
				return array('a');
			}

			return array(array());
		default:
			return 'a';
	}
}

$result = array('routes' => 0, 'defaults' => array(), 'enums' => array(), 'requests' => array(), 'harness' => array());

foreach (array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes()) as $route) {
	$result['routes']++;

	$input = array();
	$path_values = array();
	$body_keys = array();

	foreach ($route['params'] as $param) {
		$label = $route['id'] . ' ' . $param['name'];

		if (array_key_exists('default', $param) && ($param['default'] !== null)) {
			$problem = api_route_examples_refusal(function () use ($param) {
				api_validate_value($param, $param['default']);
			});

			if ($problem !== null) {
				$result['defaults'][] = $label . ' default ' . var_export($param['default'], true) . ': ' . $problem;
			}
		}

		if (isset($param['type']) && ($param['type'] === 'enum')) {
			if (empty($param['values']) || !is_array($param['values'])) {
				$result['enums'][] = $label . ': enum without values';
			} else {
				foreach ($param['values'] as $value) {
					$problem = api_route_examples_refusal(function () use ($param, $value) {
						api_validate_value($param, $value);
					});

					if ($problem !== null) {
						$result['enums'][] = $label . ' ' . var_export($value, true) . ': ' . $problem;
					}
				}
			}
		}

		$where = isset($param['in']) ? $param['in'] : 'query';
		$value = api_route_examples_value($param);

		if ($where === 'path') {
			$path_values[$param['name']] = (string) $value;
			continue;
		}

		$input[$param['name']] = $value;

		if ($where === 'body') {
			$body_keys[] = $param['name'];
		}
	}

	$route['path_values'] = $path_values;
	api_request_body_keys($body_keys);

	$problem = api_route_examples_refusal(function () use ($route, $input) {
		api_validate_input($route, $input);
	});

	if ($problem !== null) {
		$result['requests'][] = $route['id'] . ' ' . $problem;
	}
}

// The harness itself: a value the validator must refuse comes back refused,
// so empty lists above mean the routes passed, not that nothing was caught.
api_request_body_keys(array());

$limit = array('params' => array(array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 250)));

$result['harness']['too_big'] = api_route_examples_refusal(function () use ($limit) {
	api_validate_input($limit, array('limit' => 500));
});
$result['harness']['inside'] = api_route_examples_refusal(function () use ($limit) {
	api_validate_input($limit, array('limit' => 50));
});

echo "\n" . '@@RESULT@@' . json_encode($result);
