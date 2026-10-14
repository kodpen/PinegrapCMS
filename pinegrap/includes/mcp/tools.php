<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The MCP server's tools: the external API's endpoints, offered to an AI
 * application.
 *
 * Every route in includes/api/schema.php (and what the modules add) is one
 * tool. Its name comes from the route id, its description from the route's
 * summary and description, its input schema from the declared parameters, so
 * a new endpoint is a new tool without a line here. A tool runs the route in
 * this request, through the same checks integration.php applies - the
 * permission, the parameters, the handler - and the handler's answer becomes
 * the tool's result.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_MCP_ENTRY')) {
	exit;
}

// Whether a route is offered as a tool at all, whoever asks.
//
// Signing in, push registration and the API's own description are about the
// connection rather than the site's data, and a connection made through MCP
// has its own sign-in. The *_post routes are second addresses of a DELETE or
// PATCH for servers that drop those verbs; a tool call never travels as that
// verb, so the original route is the only one needed. A route with no scope
// is public and is no tool either, nor one that says 'mcp' => false: an
// address an app polls to stay in step with the screen, not an action.
function pg_mcp_route_is_offered($route) {

	$id = isset($route['id']) ? (string)$route['id'] : '';

	if (($id === '') || !empty($route['public']) || !isset($route['scope']) || ($route['scope'] === '')) {

		return false;

	}

	if (isset($route['mcp']) && ($route['mcp'] === false)) {

		return false;

	}

	foreach (array('auth.', 'push.', 'docs.') as $prefix) {

		if (strpos($id, $prefix) === 0) {

			return false;

		}

	}

	return (substr($id, -5) !== '_post');

}

// The tool's name: the route id with its dots turned into underscores.
// MCP itself allows a dot, but the model APIs most clients pass tools on to
// accept only letters, digits, underscores and hyphens.
function pg_mcp_tool_name($route_id) {

	return substr((string)preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$route_id), 0, 64);

}

// One parameter as JSON Schema: the OpenAPI document's translation of the
// declared type, its description, and null allowed where the parameter
// clears a field.
function pg_mcp_param_schema($param) {

	$schema = api_openapi_type($param);

	if (isset($param['description']) && ($param['description'] !== '')) {

		$schema['description'] = isset($schema['description'])
			? $param['description'] . ' ' . $schema['description']
			: $param['description'];

	}

	if (!empty($param['nullable']) && isset($schema['type']) && is_string($schema['type'])) {

		$schema['type'] = array($schema['type'], 'null');

	}

	return $schema;

}

// The tool for a route.
//
// The hints tell the application how careful to be (MCP ToolAnnotations): a
// GET only reads; a creation only adds; anything else that writes may change
// or remove what is there. Writes that can be rehearsed take dry_run, the
// API's X-Dry-Run: the call is checked all the way to the write and the
// answer says what would have been done.
function pg_mcp_tool_from_route($route) {

	$properties = array();

	$required = array();

	foreach ((isset($route['params']) ? $route['params'] : array()) as $param) {

		$properties[$param['name']] = pg_mcp_param_schema($param);

		if (!empty($param['required'])) {

			$required[] = $param['name'];

		}

	}

	$read_only = ($route['method'] === 'GET');

	if (!$read_only && !empty($route['dry_run']) && !isset($properties['dry_run'])) {

		$properties['dry_run'] = array(
			'type'        => 'boolean',
			'description' => 'Check the call without writing anything: the answer says what would have been done.'
		);

	}

	$input_schema = array(
		'type'       => 'object',
		'properties' => (object)$properties
	);

	if (!empty($required)) {

		$input_schema['required'] = $required;

	}

	$segments = explode('.', (string)$route['id']);

	$last = end($segments);

	$description = isset($route['summary']) ? (string)$route['summary'] : '';

	if (isset($route['description']) && ($route['description'] !== '')) {

		$description .= (($description !== '') ? '. ' : '') . $route['description'];

	}

	return array(
		'name'        => pg_mcp_tool_name($route['id']),
		'title'       => isset($route['summary']) ? (string)$route['summary'] : (string)$route['id'],
		'description' => $description,
		'inputSchema' => $input_schema,
		'annotations' => array(
			'title'           => isset($route['summary']) ? (string)$route['summary'] : (string)$route['id'],
			'readOnlyHint'    => $read_only,
			'destructiveHint' => !$read_only && ($last !== 'create'),
			'idempotentHint'  => $read_only,
			'openWorldHint'   => false
		)
	);

}

// The arguments of a call, sorted into what the route reads from its path and
// what it reads from the query and the body. Answers array('path' => name =>
// value, 'input' => name => value, 'body_keys' => list, 'dry_run' => bool).
//
// Values go on as they came: the route's own validation types and checks them,
// exactly as it would a request. A path value is turned into text, which is
// what a path is made of. An argument the route does not declare is passed on
// as a body key, so the route refuses it by name rather than the call
// succeeding without it.
function pg_mcp_arguments($route, $arguments) {

	$split = array('path' => array(), 'input' => array(), 'body_keys' => array(), 'dry_run' => false);

	if (!is_array($arguments)) {

		return $split;

	}

	$where = array();

	foreach ((isset($route['params']) ? $route['params'] : array()) as $param) {

		$where[$param['name']] = isset($param['in']) ? $param['in'] : 'query';

	}

	foreach ($arguments as $name => $value) {

		$name = (string)$name;

		if (($name === 'dry_run') && !isset($where['dry_run'])) {

			$split['dry_run'] = ($value === true) || ($value === 1) || ($value === '1') || ($value === 'true');

			continue;

		}

		$in = isset($where[$name]) ? $where[$name] : 'body';

		if ($in === 'path') {

			$split['path'][$name] = is_scalar($value) ? (string)$value : '';

			continue;

		}

		// The API reads query values as text, the way a URL carries them.
		if (($in === 'query') && is_bool($value)) {

			$value = $value ? 'true' : 'false';

		}

		$split['input'][$name] = $value;

		if ($in === 'body') {

			$split['body_keys'][] = $name;

		}

	}

	return $split;

}

// The route's path with its placeholders filled in, for the request log.
function pg_mcp_route_path($route, $path_values) {

	return (string)preg_replace_callback('#\{([a-z_]+)\}#i', function ($found) use ($path_values) {

		return isset($path_values[$found[1]]) ? rawurlencode((string)$path_values[$found[1]]) : $found[0];

	}, (string)$route['path']);

}

/* ---------------------------------------------------------------------------
   Within a request: the caller's tools, and running one
   --------------------------------------------------------------------------- */

// The routes this caller may use, by tool name.
function pg_mcp_routes() {

	static $routes = null;

	if ($routes !== null) {

		return $routes;

	}

	$routes = array();

	$scopes = api_current_scopes();

	foreach (api_schema() as $route) {

		if (!pg_mcp_route_is_offered($route) || !api_has_scope($scopes, $route['scope'])) {

			continue;

		}

		$name = pg_mcp_tool_name($route['id']);

		// The first route of a name keeps it, as the router lets the first
		// matching route answer.
		if (!isset($routes[$name])) {

			$routes[$name] = $route;

		}

	}

	return $routes;

}

function pg_mcp_tools_list() {

	$tools = array();

	foreach (pg_mcp_routes() as $route) {

		$tools[] = pg_mcp_tool_from_route($route);

	}

	return $tools;

}

// tools/call. The route runs as api_run() would run it; whatever it answers
// with, success or refusal, comes back through api_send() into the hook below
// and leaves as the tool's result. A refusal is a result with isError set, as
// MCP asks for errors the model should see and could act on - a missing
// field, a record that is not there - rather than a protocol error.
function pg_mcp_tool_call($id, $params, $modern) {

	$name = (isset($params['name']) && is_string($params['name'])) ? $params['name'] : '';

	$routes = pg_mcp_routes();

	if (!isset($routes[$name])) {

		pg_mcp_error($id, -32602, 'Unknown tool: ' . mb_substr($name, 0, 100), null, 200);

	}

	$route = $routes[$name];

	$arguments = (isset($params['arguments']) && is_array($params['arguments'])) ? $params['arguments'] : array();

	$split = pg_mcp_arguments($route, $arguments);

	$route['path_values'] = $split['path'];

	api_request_method($route['method']);

	api_request_path('/mcp' . pg_mcp_route_path($route, $split['path']));

	api_request_input($split['input']);

	api_request_body_keys($split['body_keys']);

	api_header('X-Dry-Run', $split['dry_run'] ? 'true' : '');

	api_send_hook(function ($status_code, $body) use ($id, $modern) {

		pg_mcp_tool_result($id, $status_code, $body, $modern);

	});

	api_current_route($route);

	// Only routes the caller's scopes cover are listed, but a name can be
	// sent without being listed.
	api_require_scope($route['scope']);

	if ($split['dry_run'] && empty($route['dry_run'])) {

		api_fail(400, 'dry_run_unsupported', lang('This endpoint cannot rehearse a call. Send it without X-Dry-Run.'));

	}

	$values = api_validate_input($route, $split['input']);

	call_user_func($route['handler'], $values);

	api_fail_server();

}

// The handler's answer as a CallToolResult. The JSON goes as text, which
// every client shows to its model, and as structuredContent for the clients
// that read it.
function pg_mcp_tool_result($id, $status_code, $body, $modern) {

	$result = array(
		'content' => array(
			array('type' => 'text', 'text' => (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
		),
		'isError' => ($status_code < 200) || ($status_code >= 300)
	);

	if (is_array($body) && !empty($body) && (array_keys($body) !== range(0, count($body) - 1))) {

		$result['structuredContent'] = $body;

	}

	pg_mcp_result($id, $result, $modern);

}
