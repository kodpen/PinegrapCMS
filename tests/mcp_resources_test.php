<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the MCP server's resources and prompts that need no database
 * (includes/mcp/resources.php, includes/mcp/prompts.php): how a resource URI
 * is matched against the fixed resources and the URI templates, which
 * resources and prompts a caller is offered (the routes behind them as
 * tools/list filters them), the errors a read that names nothing gets in each
 * protocol generation, the contents a read answers with, the arguments a
 * prompt takes and the messages it is built from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/dry_run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/resources.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/prompts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// The routes a caller holding these scopes may use, by tool name, from the
// Workspace's real route table, as pg_mcp_routes() builds them in a request.
function mcp_resources_test_routes($scopes, $schema = null)
{
	return pg_mcp_routes_for(($schema === null) ? ws_api_routes() : $schema, $scopes);
}

// The Workspace's route table with one route changed.
function mcp_resources_test_schema_with($route_id, $changes)
{
	$schema = array();

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $route_id) {
			$route = array_merge($route, $changes);
		}

		$schema[] = $route;
	}

	return $schema;
}

function test_mcp_resources_capabilities_are_advertised()
{
	$capabilities = pg_mcp_capabilities();

	pg_assert_same(array('listChanged' => false), $capabilities['tools']);
	pg_assert_same(array('listChanged' => false), $capabilities['resources'], 'resources, without subscriptions or list notifications');
	pg_assert_same(array('listChanged' => false), $capabilities['prompts']);
}

function test_mcp_resources_parse_fixed_and_templates()
{
	pg_assert_same(array('kind' => 'my_day', 'arguments' => array()), pg_mcp_resource_parse('pinegrap://workspace/my-day'));
	pg_assert_same(array('kind' => 'channel_context', 'arguments' => array('id' => '5')), pg_mcp_resource_parse('pinegrap://workspace/channels/5/context'));
	pg_assert_same(array('kind' => 'record_refs', 'arguments' => array('type' => 'order', 'id' => '1045')), pg_mcp_resource_parse('pinegrap://workspace/refs/order/1045'));
	pg_assert_same(array('kind' => 'record_refs', 'arguments' => array('type' => 'user_account', 'id' => '7')), pg_mcp_resource_parse('pinegrap://workspace/refs/user_account/7'), 'an underscore in the type');
	pg_assert_same('123456789012345678', pg_mcp_resource_parse('pinegrap://workspace/channels/123456789012345678/context')['arguments']['id'], 'eighteen digits');

	// RFC 3986 6.2.2.1: the scheme and the host are case-insensitive, the
	// path is not.
	pg_assert_same('my_day', pg_mcp_resource_parse('PINEGRAP://Workspace/my-day')['kind'], 'scheme and host in capitals');
	pg_assert_same(null, pg_mcp_resource_parse('pinegrap://workspace/MY-DAY'), 'the path is matched as written');
}

function test_mcp_resources_parse_refuses_what_names_nothing()
{
	$refused = array(
		// Bad ids.
		'pinegrap://workspace/channels/0/context'                    => 'id 0',
		'pinegrap://workspace/channels/05/context'                   => 'a leading zero',
		'pinegrap://workspace/channels/-1/context'                   => 'a negative id',
		'pinegrap://workspace/channels/abc/context'                  => 'letters',
		'pinegrap://workspace/channels/1.5/context'                  => 'a fraction',
		'pinegrap://workspace/channels/1e3/context'                  => 'an exponent',
		'pinegrap://workspace/channels//context'                     => 'no id',
		'pinegrap://workspace/channels/{id}/context'                 => 'the template itself',
		'pinegrap://workspace/channels/1234567890123456789/context'  => 'more digits than an id has',
		'pinegrap://workspace/refs/order/0'                          => 'record id 0',
		'pinegrap://workspace/refs/Order/5'                          => 'a type in capitals',
		'pinegrap://workspace/refs/order1/5'                         => 'a digit in the type',
		'pinegrap://workspace/refs/_order/5'                         => 'a type starting with an underscore',
		'pinegrap://workspace/refs//5'                               => 'no type',
		// Extra or missing path parts.
		'pinegrap://workspace/my-day/'                               => 'a trailing slash',
		'pinegrap://workspace/my-day/today'                          => 'a part after a fixed resource',
		'pinegrap://workspace/channels/5/context/extra'              => 'a part after a template',
		'pinegrap://workspace/channels/5'                            => 'a part missing',
		'pinegrap://workspace/channels/5/context/../../my-day'       => 'dot segments',
		'pinegrap://workspace/refs/order'                            => 'no record id',
		'pinegrap://workspace//my-day'                               => 'an empty segment',
		'pinegrap://workspace'                                       => 'no path',
		'pinegrap://workspace/'                                      => 'an empty path',
		// Encoded characters, a query, a fragment, white space.
		'pinegrap://workspace/channels/%35/context'                  => 'an encoded digit is not decoded',
		'pinegrap://workspace/channels/5%2Fcontext'                  => 'an encoded slash',
		'pinegrap://workspace/my%2Dday'                              => 'an encoded hyphen',
		'pinegrap://workspace/channels/5/context?limit=100'          => 'a query',
		'pinegrap://workspace/my-day#top'                            => 'a fragment',
		"pinegrap://workspace/my-day\n"                              => 'a trailing newline',
		' pinegrap://workspace/my-day'                               => 'a leading space',
		'pinegrap://workspace/channels/5 /context'                   => 'a space in the id',
		// Other schemes and places.
		'https://workspace/my-day'                                   => 'https',
		'file:///workspace/my-day'                                   => 'file',
		'pinegrap:workspace/my-day'                                  => 'no authority',
		'pinegrap:///workspace/my-day'                               => 'an empty authority',
		'pinegrap://store/my-day'                                    => 'another host',
		'pinegrap://user@workspace/my-day'                           => 'user information',
		'pinegrap://workspace:80/my-day'                             => 'a port',
		'workspace/my-day'                                           => 'no scheme',
		''                                                           => 'empty',
	);

	foreach ($refused as $uri => $why) {
		pg_assert_same(null, pg_mcp_resource_parse((string) $uri), $why);
	}

	pg_assert_same(null, pg_mcp_resource_parse('pinegrap://workspace/channels/' . str_repeat('1', 600) . '/context'), 'too long');

	foreach (array(null, 5, true, array('pinegrap://workspace/my-day')) as $value) {
		pg_assert_same(null, pg_mcp_resource_parse($value), 'not a string: ' . gettype($value));
	}
}

function test_mcp_resources_uri_is_built_from_its_template()
{
	pg_assert_same('pinegrap://workspace/my-day', pg_mcp_resource_uri('my_day', array()));
	pg_assert_same('pinegrap://workspace/channels/7/context', pg_mcp_resource_uri('channel_context', array('id' => '7')));
	pg_assert_same('pinegrap://workspace/channels/7/context', pg_mcp_resource_uri('channel_context', array('id' => 7)), 'a number');
	pg_assert_same('pinegrap://workspace/refs/invoice/3', pg_mcp_resource_uri('record_refs', array('type' => 'invoice', 'id' => '3')));
	pg_assert_same('', pg_mcp_resource_uri('channel_context', array('id' => '0')), 'a value its variable does not take');
	pg_assert_same('', pg_mcp_resource_uri('channel_context', array('id' => '7/../8')), 'a value with a slash');
	pg_assert_same('', pg_mcp_resource_uri('channel_context', array()), 'a value missing');
	pg_assert_same('', pg_mcp_resource_uri('nothing', array()), 'an unknown kind');

	foreach (array('pinegrap://workspace/my-day', 'pinegrap://workspace/channels/42/context', 'pinegrap://workspace/refs/erp_quote/9') as $uri) {
		$found = pg_mcp_resource_parse($uri);
		pg_assert_same($uri, pg_mcp_resource_uri($found['kind'], $found['arguments']), 'parsed and built back: ' . $uri);
	}
}

// Each resource is read through its route: the route is a GET with a scope,
// every template variable is one of its parameters, and every parameter it
// requires comes from the URI, so a read can always be made.
function test_mcp_resources_templates_match_their_routes()
{
	$by_id = array();

	foreach (ws_api_routes() as $route) {
		$by_id[$route['id']] = $route;
	}

	foreach (pg_mcp_resource_kinds() as $key => $kind) {
		pg_assert_true(isset($by_id[$kind['route']]), $key . ': its route exists');

		if (!isset($by_id[$kind['route']])) {
			continue;
		}

		$route = $by_id[$kind['route']];
		$params = array();

		foreach ($route['params'] as $param) {
			$params[$param['name']] = $param;
		}

		pg_assert_same('GET', $route['method'], $key . ': read by a GET');
		pg_assert_true($route['scope'] !== '', $key . ': its route has a scope');

		preg_match_all('#\{([a-z_]+)\}#', isset($kind['uri_template']) ? $kind['uri_template'] : $kind['uri'], $found);

		foreach ($found[1] as $variable) {
			pg_assert_true(isset($params[$variable]), $key . ': {' . $variable . '} is a parameter of ' . $route['id']);
		}

		foreach ($params as $name => $param) {
			if (!empty($param['required'])) {
				pg_assert_true(in_array($name, $found[1], true), $key . ': the required ' . $name . ' comes from the URI');
			}
		}
	}
}

function test_mcp_resources_list_follows_the_routes_offered()
{
	$routes = mcp_resources_test_routes(array('workspace:read'));

	$resources = pg_mcp_resources_list($routes);

	pg_assert_same(1, count($resources), 'one fixed resource');
	pg_assert_same('pinegrap://workspace/my-day', $resources[0]['uri']);
	pg_assert_same('application/json', $resources[0]['mimeType']);
	pg_assert_true($resources[0]['name'] !== '' && $resources[0]['description'] !== '', 'named and described');

	$templates = pg_mcp_resource_templates_list($routes);

	pg_assert_same(array('pinegrap://workspace/channels/{id}/context', 'pinegrap://workspace/refs/{type}/{id}'), array_column($templates, 'uriTemplate'));
	pg_assert_same(array('application/json', 'application/json'), array_column($templates, 'mimeType'));

	foreach (array('my_day', 'channel_context', 'record_refs') as $key) {
		pg_assert_true(pg_mcp_resource_route($key, $routes) !== null, $key . ' is read through its route');
	}

	// Without the scope, nothing.
	foreach (array(array(), array('tasks:read'), array('orders:read')) as $scopes) {
		$none = mcp_resources_test_routes($scopes);
		pg_assert_same(array(), pg_mcp_resources_list($none), 'no fixed resource without workspace:read');
		pg_assert_same(array(), pg_mcp_resource_templates_list($none), 'no template without workspace:read');
		pg_assert_same(null, pg_mcp_resource_route('channel_context', $none), 'and no read');
	}

	pg_assert_same(null, pg_mcp_resource_route('nothing', $routes), 'an unknown kind');
}

function test_mcp_resources_left_out_with_their_route()
{
	// A route that is no tool is no resource either.
	$routes = mcp_resources_test_routes(array('workspace:read'), mcp_resources_test_schema_with('workspace.channels.context', array('mcp' => false)));

	pg_assert_same(array('pinegrap://workspace/refs/{type}/{id}'), array_column(pg_mcp_resource_templates_list($routes), 'uriTemplate'));
	pg_assert_same(null, pg_mcp_resource_route('channel_context', $routes));

	$routes = mcp_resources_test_routes(array('workspace:read'), mcp_resources_test_schema_with('workspace.my_day.get', array('mcp' => false)));

	pg_assert_same(array(), pg_mcp_resources_list($routes));

	// A route under another scope follows that scope.
	$routes = mcp_resources_test_routes(array('workspace:read'), mcp_resources_test_schema_with('workspace.refs.get', array('scope' => 'orders:read')));

	pg_assert_same(null, pg_mcp_resource_route('record_refs', $routes));

	// The tool name must be held by the resource's own route.
	$routes = mcp_resources_test_routes(array('workspace:read'));
	$routes[pg_mcp_tool_name('workspace.my_day.get')]['id'] = 'workspace.something_else';

	pg_assert_same(null, pg_mcp_resource_route('my_day', $routes), 'another route under the name');
}

function test_mcp_resources_errors()
{
	$uri = 'pinegrap://workspace/channels/5/context';

	pg_assert_same(array('code' => -32602, 'message' => 'Resource not found', 'data' => array('uri' => $uri)), pg_mcp_resource_not_found($uri, true), 'the current generation: invalid params');
	pg_assert_same(array('code' => -32002, 'message' => 'Resource not found', 'data' => array('uri' => $uri)), pg_mcp_resource_not_found($uri, false), 'earlier generations: -32002');
	pg_assert_same(512, mb_strlen(pg_mcp_resource_not_found(str_repeat('x', 900), true)['data']['uri']), 'a long uri is cut in the answer');

	$api_error = array('code' => 'not_found', 'message' => 'Channel not found.', 'request_id' => 'abc');

	foreach (array(400, 403, 404, 410, 422) as $status) {
		$refusal = pg_mcp_resource_refusal($uri, $status, array('error' => $api_error), false);

		pg_assert_same(-32002, $refusal['code'], $status . ' is a resource not found');
		pg_assert_same('Resource not found', $refusal['message']);
		pg_assert_same(array('uri' => $uri, 'error' => $api_error), $refusal['data'], 'the API error the tool would show');
		pg_assert_same(-32602, pg_mcp_resource_refusal($uri, $status, array('error' => $api_error), true)['code'], $status . ' in the current generation');
	}

	$internal = pg_mcp_resource_refusal($uri, 500, array('error' => array('code' => 'server_error', 'message' => 'A system error occurred.')), true);

	pg_assert_same(-32603, $internal['code'], 'a failure of the route is an internal error');
	pg_assert_same('Internal error', $internal['message']);
	pg_assert_same('server_error', $internal['data']['error']['code']);

	pg_assert_same(array('uri' => $uri), pg_mcp_resource_refusal($uri, 404, null, true)['data'], 'no error object, only the uri');
	pg_assert_same(array('uri' => $uri), pg_mcp_resource_refusal($uri, 404, array('error' => 'text'), true)['data'], 'an error that is not an object is left out');
}

function test_mcp_resources_contents()
{
	$contents = pg_mcp_resource_contents('PINEGRAP://workspace/my-day', array('tasks' => array(), 'name' => 'Çağla/Ünal'));

	pg_assert_same(array('contents'), array_keys($contents));
	pg_assert_same(1, count($contents['contents']), 'one text item');
	pg_assert_same('PINEGRAP://workspace/my-day', $contents['contents'][0]['uri'], 'the uri as the client sent it');
	pg_assert_same('application/json', $contents['contents'][0]['mimeType']);
	pg_assert_same('{"tasks":[],"name":"Çağla/Ünal"}', $contents['contents'][0]['text'], 'the route\'s JSON body');
}

function test_mcp_resources_cache_hints()
{
	pg_assert_same(array('x' => 1, 'ttlMs' => 0, 'cacheScope' => 'private'), pg_mcp_cache_hints(array('x' => 1), 0, true), 'a read is stale at once and the caller\'s own');
	pg_assert_same(array('x' => 1, 'ttlMs' => 300000, 'cacheScope' => 'private'), pg_mcp_cache_hints(array('x' => 1), 300000, true));
	pg_assert_same(array('x' => 1), pg_mcp_cache_hints(array('x' => 1), 300000, false), 'earlier generations have no cache hints');
}

// The current generation names a read's uri in Mcp-Name, as it names a tool
// or a prompt; a value outside plain ASCII comes Base64-encoded.
function test_mcp_resources_read_names_its_uri_in_the_header()
{
	$uri = 'pinegrap://workspace/my-day';
	$headers = array('method' => 'resources/read', 'name' => $uri, 'version' => '2026-07-28');

	pg_assert_same('', pg_mcp_header_mismatch('resources/read', array('uri' => $uri), $headers), 'the uri as Mcp-Name');
	pg_assert_true(pg_mcp_header_mismatch('resources/read', array('uri' => $uri), array('name' => '') + $headers) !== '', 'Mcp-Name missing');
	pg_assert_true(pg_mcp_header_mismatch('resources/read', array('uri' => 'pinegrap://workspace/refs/order/1'), $headers) !== '', 'another uri');
	pg_assert_same('', pg_mcp_header_mismatch('resources/read', array('uri' => $uri), array('name' => '=?base64?' . base64_encode($uri) . '?=') + $headers), 'Base64-encoded');
	pg_assert_true(pg_mcp_header_mismatch('resources/read', array('uri' => $uri), array('name' => '=?base64?***?=') + $headers) !== '', 'not Base64');
	pg_assert_same('', pg_mcp_header_mismatch('prompts/get', array('name' => 'weekly_report'), array('method' => 'prompts/get', 'name' => '=?base64?' . base64_encode('weekly_report') . '?=', 'version' => '2026-07-28')), 'a prompt name encoded');
	pg_assert_same('', pg_mcp_header_mismatch('resources/list', array(), array('method' => 'resources/list', 'name' => '', 'version' => '2026-07-28')), 'a list names nothing');

	pg_assert_same('a', pg_mcp_header_name_decode('a'));
	pg_assert_same('Hello, 世界', pg_mcp_header_name_decode('=?base64?SGVsbG8sIOS4lueVjA==?='));
	pg_assert_same(null, pg_mcp_header_name_decode('=?base64?not base64?='));
	pg_assert_same('=?BASE64?eA==?=', pg_mcp_header_name_decode('=?BASE64?eA==?='), 'the markers are lowercase');
}

/* ---------------------------------------------------------------------------
   Prompts
   --------------------------------------------------------------------------- */

function test_mcp_prompts_list_follows_the_resources_offered()
{
	$routes = mcp_resources_test_routes(array('workspace:read'));
	$prompts = pg_mcp_prompts_list($routes);

	pg_assert_same(array('channel_summary', 'weekly_report'), array_column($prompts, 'name'));

	foreach ($prompts as $prompt) {
		pg_assert_true(($prompt['title'] !== '') && ($prompt['description'] !== ''), $prompt['name'] . ': titled and described');
	}

	pg_assert_same(array(array('name' => 'channel_id', 'description' => 'The id of the channel or discussion to summarise.', 'required' => true)), $prompts[0]['arguments']);
	pg_assert_same(false, $prompts[1]['arguments'][0]['required'], 'weekly_report: channel_id is optional');

	pg_assert_same(array(), pg_mcp_prompts_list(mcp_resources_test_routes(array())), 'nothing to read, no prompt');

	$no_context = mcp_resources_test_routes(array('workspace:read'), mcp_resources_test_schema_with('workspace.channels.context', array('mcp' => false)));

	pg_assert_same(array('weekly_report'), array_column(pg_mcp_prompts_list($no_context), 'name'), 'channel_summary needs the channel context');

	$no_day = mcp_resources_test_routes(array('workspace:read'), mcp_resources_test_schema_with('workspace.my_day.get', array('mcp' => false)));

	pg_assert_same(array('channel_summary'), array_column(pg_mcp_prompts_list($no_day), 'name'), 'weekly_report needs my day');

	pg_assert_true(pg_mcp_prompt_is_offered('weekly_report', $routes));
	pg_assert_false(pg_mcp_prompt_is_offered('nothing', $routes), 'an unknown prompt');
	pg_assert_false(pg_mcp_prompt_is_offered('', $routes));
	pg_assert_false(pg_mcp_prompt_is_offered('channel_summary', $no_context));
}

function test_mcp_prompts_arguments_are_checked()
{
	pg_assert_same(array('values' => array('channel_id' => '12'), 'error' => ''), pg_mcp_prompt_arguments('channel_summary', array('channel_id' => '12')));
	pg_assert_same(array('values' => array('channel_id' => '12'), 'error' => ''), pg_mcp_prompt_arguments('channel_summary', array('channel_id' => 12)), 'a number for a client that sends one');

	pg_assert_same('Missing required argument: channel_id', pg_mcp_prompt_arguments('channel_summary', array())['error']);
	pg_assert_same('Missing required argument: channel_id', pg_mcp_prompt_arguments('channel_summary', null)['error'], 'no arguments at all');
	pg_assert_same('Missing required argument: channel_id', pg_mcp_prompt_arguments('channel_summary', array('channel_id' => ''))['error'], 'empty is missing');
	pg_assert_same('Missing required argument: channel_id', pg_mcp_prompt_arguments('channel_summary', array('channel_id' => null))['error'], 'null is missing');

	foreach (array('abc', '0', '-3', '1.5', ' 12', '12 ', '012', '12/context', array('12'), true, 1.5, 0, -4) as $value) {
		pg_assert_same('Invalid argument: channel_id must be a positive whole number.', pg_mcp_prompt_arguments('channel_summary', array('channel_id' => $value))['error'], var_export($value, true));
	}

	pg_assert_same('Unknown argument: channel', pg_mcp_prompt_arguments('channel_summary', array('channel_id' => '12', 'channel' => '3'))['error'], 'an argument the prompt does not take');
	pg_assert_same('Unknown argument: 0', pg_mcp_prompt_arguments('channel_summary', array('12'))['error'], 'a list instead of an object');
	pg_assert_same('Invalid params: arguments must be an object.', pg_mcp_prompt_arguments('channel_summary', 'channel_id=12')['error']);

	pg_assert_same(array('values' => array(), 'error' => ''), pg_mcp_prompt_arguments('weekly_report', null), 'weekly_report takes no argument');
	pg_assert_same(array('values' => array(), 'error' => ''), pg_mcp_prompt_arguments('weekly_report', array()), 'an empty object');
	pg_assert_same(array('values' => array(), 'error' => ''), pg_mcp_prompt_arguments('weekly_report', array('channel_id' => '')), 'an empty optional one is left out');
	pg_assert_same(array('values' => array('channel_id' => '9'), 'error' => ''), pg_mcp_prompt_arguments('weekly_report', array('channel_id' => '9')));
	pg_assert_same('Invalid argument: channel_id must be a positive whole number.', pg_mcp_prompt_arguments('weekly_report', array('channel_id' => 'x'))['error']);

	pg_assert_same('Unknown prompt: nothing', pg_mcp_prompt_arguments('nothing', array())['error']);
}

function test_mcp_prompts_channel_summary_messages()
{
	$routes = mcp_resources_test_routes(array('workspace:read'));
	$result = pg_mcp_prompt_result('channel_summary', array('channel_id' => '12'), 'Acme Store', $routes, null);

	pg_assert_same(array('description', 'messages'), array_keys($result));
	pg_assert_same(1, count($result['messages']), 'text only when the context is not embedded');
	pg_assert_same('user', $result['messages'][0]['role']);
	pg_assert_same('text', $result['messages'][0]['content']['type']);

	$text = $result['messages'][0]['content']['text'];

	pg_assert_contains('pinegrap://workspace/channels/12/context', $text, 'names the resource');
	pg_assert_contains('workspace_channels_context', $text, 'and the tool that reads the same');
	pg_assert_contains('Acme Store', $text, 'the site');

	foreach (pg_ai_prompt_rules('assistant', '- ') as $line) {
		pg_assert_contains($line, $text, 'the rules every prompt carries');
	}

	$item = array('uri' => 'pinegrap://workspace/channels/12/context', 'mimeType' => 'application/json', 'text' => '{"channel":{"id":12}}');
	$embedded = pg_mcp_prompt_result('channel_summary', array('channel_id' => '12'), 'Acme Store', $routes, $item);

	pg_assert_same(2, count($embedded['messages']), 'the context as a second message');
	pg_assert_same('user', $embedded['messages'][1]['role']);
	pg_assert_same(array('type' => 'resource', 'resource' => $item), $embedded['messages'][1]['content'], 'an embedded resource');
	pg_assert_contains('attached', $embedded['messages'][0]['content']['text']);
}

function test_mcp_prompts_weekly_report_messages()
{
	$routes = mcp_resources_test_routes(array('workspace:read'));

	$plain = pg_mcp_prompt_result('weekly_report', array(), 'Acme Store', $routes, null);
	$text = $plain['messages'][0]['content']['text'];

	pg_assert_same(1, count($plain['messages']));
	pg_assert_contains('pinegrap://workspace/my-day', $text);
	pg_assert_contains('workspace_my_day_get', $text);
	pg_assert_false(strpos($text, 'pinegrap://workspace/channels/') !== false, 'no channel without channel_id');

	foreach (pg_ai_prompt_rules('assistant', '- ') as $line) {
		pg_assert_contains($line, $text, 'the rules every prompt carries');
	}

	$channel = pg_mcp_prompt_result('weekly_report', array('channel_id' => '9'), 'Acme Store', $routes, null)['messages'][0]['content']['text'];

	pg_assert_contains('pinegrap://workspace/channels/9/context', $channel, 'the channel as well');
	pg_assert_contains('pinegrap://workspace/my-day', $channel);

	// Only what the caller may read is named.
	$no_context = mcp_resources_test_routes(array('workspace:read'), mcp_resources_test_schema_with('workspace.channels.context', array('mcp' => false)));
	$without = pg_mcp_prompt_result('weekly_report', array('channel_id' => '9'), 'Acme Store', $no_context, null)['messages'][0]['content']['text'];

	pg_assert_false(strpos($without, 'channels/9') !== false, 'a channel context the caller cannot read is not named');
	pg_assert_false(strpos($without, 'workspace_channels_context') !== false, 'nor its tool');
}
