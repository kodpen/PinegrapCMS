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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');

// Whether a route is offered as a tool at all, whoever asks.
//
// Signing in, push registration and the API's own description are about the
// connection rather than the site's data, and a connection made through MCP
// has its own sign-in. The *_post routes are second addresses of a DELETE or
// PATCH for servers that drop those verbs; a tool call never travels as that
// verb, so the original route is the only one needed. A route with no scope
// is public and is no tool either, nor one that says 'mcp' => false: an
// address an app polls to stay in step with the screen, not an action. A
// route that needs credentials but no scope is a tool only when it says
// 'mcp' => true: the guide (guide.read), which every caller may read.
function pg_mcp_route_is_offered($route) {

	$id = isset($route['id']) ? (string)$route['id'] : '';

	if (($id === '') || !empty($route['public']) || !isset($route['scope'])) {

		return false;

	}

	if (($route['scope'] === '') && (!isset($route['mcp']) || ($route['mcp'] !== true))) {

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

// What a model is told on top of the route's own description, for the
// tools where it goes wrong without it: the form writes are addressed by
// field names it cannot guess, and a rich-text answer keeps only the HTML
// the field's html_allowed lists. A tool of an area the guide has a topic
// for ends with "Guide: <topic>." (pg_mcp_tool_guide_line()). Empty for every
// other route. The REST document keeps the route's description alone.
function pg_mcp_tool_guidance($route_id) {

	$guidance = pg_mcp_tool_guidance_own($route_id);

	$guide = pg_mcp_tool_guide_line($route_id);

	return trim($guidance . (($guidance !== '' && $guide !== '') ? ' ' : '') . $guide);

}

// "Guide: <topic>." for a route whose area has a listed topic, or ''. The
// caller holds the tool it is described for, so the topic is listed for them
// whenever its file is there and its module is on.
function pg_mcp_tool_guide_line($route_id) {

	static $registry = null;

	static $modules = null;

	if ($registry === null) {

		$registry = pg_agent_guide_registry();

		$modules = pg_agent_guide_modules_on();

	}

	$topic = pg_agent_guide_topic_of_route($registry, pg_agent_guide_listed($registry, array((string)$route_id), $modules), (string)$route_id);

	return ($topic !== '') ? 'Guide: ' . $topic . '.' : '';

}

// The lines a few tools carry for themselves, beside the guide's.
function pg_mcp_tool_guidance_own($route_id) {

	$fields = 'Read the form\'s fields first with the tool forms_fields (or the resource pinegrap://forms/{id}/fields): an answer is addressed by the field name, the answer to a choice field is one of its option values, and a field with rich_text true takes HTML - only the tags, attributes, style properties, address schemes and image hosts its html_allowed lists are kept (anything else is removed or unwrapped, and class is never kept), up to its max_length characters.';

	switch ($route_id) {

		case 'forms.submission_create':
		case 'forms.submission_update':

			return $fields . ' Send it with dry_run true first to see the answer without writing anything.';

		case 'forms.get':

			return 'To write a record on this form, read forms_fields for what each field takes.';

	}

	return '';

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

		// An enum lists every value it takes, so a nullable one lists null
		// as well, or the client's validator refuses the clear.
		if (isset($schema['enum'])) {

			$schema['enum'][] = null;

		}

	}

	return $schema;

}

// The tool for a route.
//
// The hints tell the application how careful to be (MCP ToolAnnotations): a
// GET only reads; a creation only adds, and so does a proposal (a change
// card the person applies or sets aside); anything else that writes may
// change or remove what is there. Writes that can be rehearsed take dry_run,
// the API's X-Dry-Run: the call is checked all the way to the write and the
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

	$guidance = pg_mcp_tool_guidance((string)$route['id']);

	if ($guidance !== '') {

		$description .= (($description !== '') ? ' ' : '') . $guidance;

	}

	return array(
		'name'        => pg_mcp_tool_name($route['id']),
		'title'       => isset($route['summary']) ? (string)$route['summary'] : (string)$route['id'],
		'description' => $description,
		'inputSchema' => $input_schema,
		'annotations' => array(
			'title'           => isset($route['summary']) ? (string)$route['summary'] : (string)$route['id'],
			'readOnlyHint'    => $read_only,
			'destructiveHint' => !$read_only && !in_array($last, array('create', 'propose'), true),
			'idempotentHint'  => $read_only,
			'openWorldHint'   => false
		)
	);

}

// The arguments of a call, sorted into what the route reads from its path and
// what it reads from the query and the body. Answers array('path' => name =>
// value, 'input' => name => value, 'body_keys' => list, 'dry_run' => true,
// false, or null for a value that is neither).
//
// Values go on as they came: the route's own validation types and checks them,
// exactly as it would a request. A path value is turned into text, which is
// what a path is made of. An argument the route does not declare is passed on
// as a body key, so the route refuses it by name rather than the call
// succeeding without it.
//
// dry_run is read by the same rule as X-Dry-Run (api_dry_run_value()): the
// schema says boolean, but a client that sends "yes" or "True" means a
// rehearsal, and reading that as false would make the write it asked not to.
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

			$split['dry_run'] = (is_scalar($value) || ($value === null)) ? api_dry_run_value($value) : null;

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

// The routes of $schema a caller holding $scopes may use, by tool name. The
// resources and prompts (resources.php, prompts.php) are offered from the
// same list, so a route that is no tool for the caller is no resource either.
function pg_mcp_routes_for($schema, $scopes) {

	$routes = array();

	foreach ($schema as $route) {

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

// tools/list: a tool for each of the caller's routes (pg_mcp_routes_for()).
function pg_mcp_tools_list($routes) {

	$tools = array();

	foreach ($routes as $route) {

		$tools[] = pg_mcp_tool_from_route($route);

	}

	return $tools;

}

/* ---------------------------------------------------------------------------
   Within a request: the caller's tools, and running one
   --------------------------------------------------------------------------- */

// The routes this caller may use, by tool name.
function pg_mcp_routes() {

	static $routes = null;

	if ($routes === null) {

		$routes = pg_mcp_routes_for(api_schema(), api_current_scopes());

	}

	return $routes;

}

// tools/call. On the compact address the four tools that stand for the rest
// are answered by pg_mcp_compact_call(); any other name is a route's tool, on
// both addresses. A refusal is a result with isError set, as MCP asks for errors
// the model should see and could act on - a missing field, a record that is
// not there - rather than a protocol error.
function pg_mcp_tool_call($id, $params, $modern) {

	$name = (isset($params['name']) && is_string($params['name'])) ? $params['name'] : '';

	$arguments = (isset($params['arguments']) && is_array($params['arguments'])) ? $params['arguments'] : array();

	if (pg_mcp_is_compact() && in_array($name, pg_mcp_compact_tool_names(), true)) {

		pg_mcp_compact_call($id, $name, $arguments, $modern);

	}

	$routes = pg_mcp_routes();

	if (!isset($routes[$name])) {

		pg_mcp_error($id, -32602, 'Unknown tool: ' . mb_substr($name, 0, 100), null, 200);

	}

	pg_mcp_route_run($routes[$name], $arguments, function ($status_code, $body) use ($id, $modern) {

		pg_mcp_tool_result($id, $status_code, $body, $modern);

	});

}

// Runs one of the caller's routes (pg_mcp_routes()) as api_run() would run
// it, for a tool call, a resource read or a prompt that embeds a resource.
// Whatever the route answers with, success or refusal, comes back through
// api_send() into the hook and is handed to $answer($status_code, $body),
// which writes the JSON-RPC answer and leaves; this function never returns.
function pg_mcp_route_run($route, $arguments, $answer) {

	$split = pg_mcp_arguments($route, $arguments);

	$route['path_values'] = $split['path'];

	api_request_method($route['method']);

	api_request_path('/mcp' . pg_mcp_route_path($route, $split['path']));

	api_request_input($split['input']);

	api_request_body_keys($split['body_keys']);

	api_header('X-Dry-Run', $split['dry_run'] ? 'true' : '');

	api_send_hook(function ($status_code, $body) use ($answer, $route, $split) {

		// A write that went through makes every page the page cache keeps
		// old once the request ends. Every tool call is a POST, so init.php
		// cannot tell, and most routes announce nothing (pg_announce()).
		if (function_exists('pg_page_cache_mcp_dirties') && pg_page_cache_mcp_dirties($route['method'], $split['dry_run'], $status_code)) {

			pg_page_cache_mark_dirty();

		}

		call_user_func($answer, $status_code, $body);

	});

	api_current_route($route);

	// pg_mcp_routes() holds only the routes the caller's scopes cover; the
	// check is the router's own and stays, so no caller of this function
	// can run a route past it.
	api_require_scope($route['scope']);

	if ($split['dry_run'] === null) {

		api_fail(400, 'invalid_dry_run', lang('dry_run must be true or false.'), 'dry_run');

	}

	if ($split['dry_run'] && empty($route['dry_run'])) {

		api_fail(400, 'dry_run_unsupported', lang('This endpoint cannot rehearse a call. Send it without X-Dry-Run.'));

	}

	$values = api_validate_input($route, $split['input']);

	call_user_func($route['handler'], $values);

	api_fail_server();

}

// The handler's answer as a CallToolResult: array('result' => ...) for
// pg_mcp_answer(). The JSON goes as text (pg_mcp_json_encode(), a broken byte
// written as U+FFFD), which every client shows to its model, and as
// structuredContent for the clients that read it. A body that cannot be JSON
// at all is an internal error, as the REST answer turns it into a 500, never
// a result whose text is empty.
function pg_mcp_tool_answer($status_code, $body) {

	$text = pg_mcp_json_encode($body);

	if ($text === null) {

		return array('error' => array('code' => -32603, 'message' => 'Internal error'));

	}

	$result = array(
		'content' => array(
			array('type' => 'text', 'text' => $text)
		),
		'isError' => ($status_code < 200) || ($status_code >= 300)
	);

	if (is_array($body) && !empty($body) && (array_keys($body) !== range(0, count($body) - 1))) {

		$result['structuredContent'] = $body;

	}

	return array('result' => $result);

}

function pg_mcp_tool_result($id, $status_code, $body, $modern) {

	pg_mcp_answer($id, pg_mcp_tool_answer($status_code, $body), $modern);

}

/* ---------------------------------------------------------------------------
   The compact list (mcp_compact.php)
   --------------------------------------------------------------------------- */

// Whether this request came to the compact list's address. Its tools/list
// names guide_read, the four tools below and the tools that move money; every
// other tool of the caller is found with tools_find, read with
// tools_describe and run through tools_read or tools_write. A tool called by
// its own name runs as on the full address.
function pg_mcp_is_compact() {

	return defined('PG_MCP_COMPACT') && PG_MCP_COMPACT;

}

// How many names tools_find answers and tools_describe takes at most.
function pg_mcp_compact_limits() {

	return array('find' => 20, 'describe' => 5);

}

// The four tools of the compact list that stand for the rest.
function pg_mcp_compact_tool_names() {

	return array('tools_find', 'tools_describe', 'tools_read', 'tools_write');

}

// Whether a route is run only by its own name: a route that moves money or
// makes a cost. The client's question before such a call then names the
// action itself rather than tools_write.
function pg_mcp_route_moves_money($route) {

	return !empty($route['moves_money']);

}

// A tool's area: the first part of its route id (products, design,
// workspace, erp...).
function pg_mcp_tool_area($route_id) {

	$segments = explode('.', (string)$route_id);

	return $segments[0];

}

// The areas of the caller's tools, sorted.
function pg_mcp_compact_areas($routes) {

	$areas = array();

	foreach ($routes as $route) {

		$areas[pg_mcp_tool_area($route['id'])] = true;

	}

	$areas = array_keys($areas);

	sort($areas);

	return $areas;

}

// The first sentence of a tool's description, at most 200 characters: what
// tools_find answers for each name.
function pg_mcp_tool_first_sentence($description) {

	$description = trim((string)$description);

	$end = strpos($description, '. ');

	if ($end !== false) {

		$description = substr($description, 0, $end + 1);

	}

	return (mb_strlen($description) > 200) ? rtrim(mb_substr($description, 0, 199)) . '…' : $description;

}

// tools/list on the compact address for a caller whose tools are $routes.
function pg_mcp_compact_tools_list($routes) {

	$tools = array();

	if (isset($routes['guide_read'])) {

		$tools[] = pg_mcp_tool_from_route($routes['guide_read']);

	}

	$tools = array_merge($tools, pg_mcp_compact_meta_tools(pg_mcp_compact_areas($routes)));

	foreach ($routes as $route) {

		if (pg_mcp_route_moves_money($route)) {

			$tools[] = pg_mcp_tool_from_route($route);

		}

	}

	return $tools;

}

// The four tools that stand for the rest, with the caller's areas as the
// values tools_find's area takes.
function pg_mcp_compact_meta_tools($areas) {

	$limits = pg_mcp_compact_limits();

	$name_property = array('type' => 'string', 'description' => 'The tool\'s name, as tools_find answers it.');

	$arguments_property = array('type' => 'object', 'description' => 'The tool\'s own arguments, as tools_describe gives its input schema.');

	$read = array('readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false);

	$tools = array(
		array(
			'name'        => 'tools_find',
			'title'       => 'Find tools',
			'description' => 'Find the site\'s tools by words, an area or both: answers at most ' . $limits['find'] . ' tool names, best match first, with the first sentence of each, and more true when there are others. Then read the ones you need with tools_describe.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'words' => array('type' => 'string', 'maxLength' => 200, 'description' => 'Words looked for in a tool\'s name and description, such as "product price" or "page content".'),
					'area'  => array('type' => 'string', 'enum' => array_values($areas), 'description' => 'Only the tools of this area.')
				)
			),
			'annotations' => array('title' => 'Find tools') + $read
		),
		array(
			'name'        => 'tools_describe',
			'title'       => 'Describe tools',
			'description' => 'Answers the description and input schema of at most ' . $limits['describe'] . ' tools, and run_with: tools_read for a tool that only reads, tools_write for one that writes, or own_name for one that moves money and is called by its own name.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'names' => array('type' => 'array', 'items' => array('type' => 'string'), 'minItems' => 1, 'maxItems' => $limits['describe'], 'description' => 'Tool names, as tools_find answers them.')
				),
				'required'   => array('names')
			),
			'annotations' => array('title' => 'Describe tools') + $read
		),
		array(
			'name'        => 'tools_read',
			'title'       => 'Run a reading tool',
			'description' => 'Runs a tool that only reads, with its arguments, under that tool\'s own permission and checks; the answer is the tool\'s. A tool that writes is refused here: run it with tools_write.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array('name' => $name_property, 'arguments' => $arguments_property),
				'required'   => array('name')
			),
			'annotations' => array('title' => 'Run a reading tool') + $read
		),
		array(
			'name'        => 'tools_write',
			'title'       => 'Run a writing tool',
			'description' => 'Runs a tool that creates, changes, sends or deletes something, with its arguments, under that tool\'s own permission and checks. Tell the person what it will do and wait for their yes first. A tool that only reads is refused here (use tools_read), and so is one that moves money: call that one by its own name.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'name'      => $name_property,
					'arguments' => $arguments_property,
					'dry_run'   => array('type' => 'boolean', 'description' => 'Check the call without writing anything, where the tool accepts dry_run: the answer says what would have been done.')
				),
				'required'   => array('name')
			),
			'annotations' => array(
				'title'           => 'Run a writing tool',
				'readOnlyHint'    => false,
				'destructiveHint' => true,
				'idempotentHint'  => false,
				'openWorldHint'   => false
			)
		)
	);

	return $tools;

}

// The words of a tools_find query, lowercased, each at least two characters;
// a plural's s is dropped so that "products" finds "product".
function pg_mcp_find_words($words) {

	$found = array();

	foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string)$words), -1, PREG_SPLIT_NO_EMPTY) as $word) {

		if (mb_strlen($word) > 3 && substr($word, -1) === 's' && substr($word, -2) !== 'ss') {

			$word = substr($word, 0, -1);

		}

		if (mb_strlen($word) >= 2) {

			$found[$word] = true;

		}

	}

	return array_keys($found);

}

// tools_find for a caller whose tools are $routes: the tools of $area (all
// areas when it is ''), ranked by how many of the words their name, summary
// or description holds (the name counting most), then by name. With no words
// every tool of the area is a match, by name. Answers array('tools' =>
// list of name and description, 'more' => bool).
function pg_mcp_compact_find($routes, $words, $area) {

	$words = pg_mcp_find_words($words);

	$ranked = array();

	foreach ($routes as $name => $route) {

		if (($area !== '') && (pg_mcp_tool_area($route['id']) !== $area)) {

			continue;

		}

		$tool_name = mb_strtolower(str_replace('_', ' ', (string)$name));

		$summary = mb_strtolower(isset($route['summary']) ? (string)$route['summary'] : '');

		$description = mb_strtolower(isset($route['description']) ? (string)$route['description'] : '');

		$matched = 0;

		$score = 0;

		foreach ($words as $word) {

			$weight = (strpos($tool_name, $word) !== false) ? 3 : ((strpos($summary, $word) !== false) ? 2 : ((strpos($description, $word) !== false) ? 1 : 0));

			if ($weight > 0) {

				$matched++;

				$score += $weight;

			}

		}

		if (!empty($words) && ($matched === 0)) {

			continue;

		}

		$ranked[] = array($matched, $score, (string)$name, $route);

	}

	usort($ranked, function ($a, $b) {

		if ($a[0] !== $b[0]) {

			return $b[0] - $a[0];

		}

		if ($a[1] !== $b[1]) {

			return $b[1] - $a[1];

		}

		return strcmp($a[2], $b[2]);

	});

	$limits = pg_mcp_compact_limits();

	$tools = array();

	foreach (array_slice($ranked, 0, $limits['find']) as $row) {

		$tool = pg_mcp_tool_from_route($row[3]);

		$tools[] = array('name' => $tool['name'], 'description' => pg_mcp_tool_first_sentence($tool['description']));

	}

	return array('tools' => $tools, 'more' => count($ranked) > $limits['find']);

}

// How a tool is run on the compact address: tools_read, tools_write or by its
// own name.
function pg_mcp_compact_run_with($route) {

	if (pg_mcp_route_moves_money($route)) {

		return 'own_name';

	}

	return ($route['method'] === 'GET') ? 'tools_read' : 'tools_write';

}

// tools_describe: each named tool's description and input schema, and the
// names that are none of the caller's tools.
function pg_mcp_compact_describe($routes, $names) {

	$tools = array();

	$unknown = array();

	foreach ($names as $name) {

		if (!isset($routes[$name])) {

			$unknown[] = $name;

			continue;

		}

		$tool = pg_mcp_tool_from_route($routes[$name]);

		$tools[] = array(
			'name'        => $tool['name'],
			'description' => $tool['description'],
			'inputSchema' => $tool['inputSchema'],
			'run_with'    => pg_mcp_compact_run_with($routes[$name])
		);

	}

	return array('tools' => $tools, 'unknown' => $unknown);

}

// What a call of one of the four tools comes to, without running anything:
// array('answer' => body) for tools_find and tools_describe, array('route' =>
// route, 'arguments' => arguments) for tools_read and tools_write - the route
// then runs through pg_mcp_route_run() with its own scope, validation, rate
// limit and log - or array('refuse' => array(status, code, message, field)).
function pg_mcp_compact_target($routes, $tool, $arguments) {

	$refuse = function ($status, $code, $message, $field = null) {

		return array('refuse' => array($status, $code, $message, $field));

	};

	$limits = pg_mcp_compact_limits();

	if ($tool === 'tools_find') {

		$words = isset($arguments['words']) ? $arguments['words'] : '';

		$area = isset($arguments['area']) ? $arguments['area'] : '';

		if (!is_string($words) || (mb_strlen($words) > 200)) {

			return $refuse(422, 'validation_failed', lang('words must be text of at most 200 characters.'), 'words');

		}

		if (!is_string($area) || (($area !== '') && !in_array($area, pg_mcp_compact_areas($routes), true))) {

			return $refuse(422, 'validation_failed', lang('area must be one of the areas tools_find lists.'), 'area');

		}

		if ((trim($words) === '') && ($area === '')) {

			return $refuse(422, 'validation_failed', lang('Give words, an area or both.'), 'words');

		}

		return array('answer' => pg_mcp_compact_find($routes, $words, $area));

	}

	if ($tool === 'tools_describe') {

		$names = isset($arguments['names']) ? $arguments['names'] : null;

		if (is_string($names)) {

			$names = array($names);

		}

		$valid = is_array($names) && !empty($names) && (count($names) <= $limits['describe']);

		foreach ((is_array($names) ? $names : array()) as $name) {

			$valid = $valid && is_string($name);

		}

		if (!$valid) {

			return $refuse(422, 'validation_failed', sprintf(lang('names must list one to %d tool names.'), $limits['describe']), 'names');

		}

		return array('answer' => pg_mcp_compact_describe($routes, array_values(array_unique($names))));

	}

	$name = (isset($arguments['name']) && is_string($arguments['name'])) ? $arguments['name'] : '';

	if ($name === '') {

		return $refuse(422, 'validation_failed', lang('name is required: the tool\'s name, as tools_find answers it.'), 'name');

	}

	if (!isset($routes[$name])) {

		return $refuse(404, 'not_found', lang('There is no tool of that name. Find one with tools_find.'), 'name');

	}

	$route = $routes[$name];

	$call = isset($arguments['arguments']) ? $arguments['arguments'] : array();

	if (!is_array($call)) {

		return $refuse(422, 'validation_failed', lang('arguments must be an object of the tool\'s own arguments.'), 'arguments');

	}

	if ($tool === 'tools_read') {

		if ($route['method'] !== 'GET') {

			return $refuse(400, 'tool_writes', lang('This tool writes: run it with tools_write.'), 'name');

		}

		return array('route' => $route, 'arguments' => $call);

	}

	if ($route['method'] === 'GET') {

		return $refuse(400, 'tool_reads', lang('This tool only reads: run it with tools_read.'), 'name');

	}

	if (pg_mcp_route_moves_money($route)) {

		return $refuse(400, 'tool_moves_money', lang('This tool moves money: call it by its own name, after the person\'s yes.'), 'name');

	}

	if (array_key_exists('dry_run', $arguments)) {

		$call['dry_run'] = $arguments['dry_run'];

	}

	return array('route' => $route, 'arguments' => $call);

}

// tools/call of one of the four tools on the compact address. A refusal is
// the API's error object in a result with isError, written as api_fail()
// writes any refusal of a route, and logged as one.
function pg_mcp_compact_call($id, $tool, $arguments, $modern) {

	$writer = function ($status_code, $body) use ($id, $modern) {

		pg_mcp_tool_result($id, $status_code, $body, $modern);

	};

	$target = pg_mcp_compact_target(pg_mcp_routes(), $tool, $arguments);

	if (isset($target['refuse'])) {

		api_send_hook($writer);

		list($status, $code, $message, $field) = $target['refuse'];

		api_fail($status, $code, $message, $field);

	}

	if (isset($target['answer'])) {

		call_user_func($writer, 200, $target['answer']);

	}

	pg_mcp_route_run($target['route'], $target['arguments'], $writer);

}
