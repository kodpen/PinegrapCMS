<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The MCP server's resources: context an AI application reads before it
 * acts, at pinegrap:// addresses.
 *
 * Each resource is one GET route of the external API. A read runs that route
 * the way tools/call runs it (pg_mcp_route_run()), so the scope, the OAuth
 * ceiling, the person's rights, channel membership and 'mcp' => false hold
 * exactly as they hold for the tool: a resource is listed and read only when
 * its route is one of the caller's tools (pg_mcp_routes()), and a read
 * answers what the tool would, or names the same refusal.
 *
 *   pinegrap://workspace/my-day                  GET /workspace/my-day
 *   pinegrap://workspace/channels/{id}/context   GET /workspace/channels/{id}/context
 *   pinegrap://workspace/channels/{id}/customer  GET /workspace/channels/{id}/customer
 *   pinegrap://workspace/refs/{type}/{id}        GET /workspace/refs?type=&id=
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_MCP_ENTRY')) {
	exit;
}

// The resources, by key: a fixed uri or a uri_template, the route that
// answers a read, and the texts a client shows and its model reads (English,
// like the tools' descriptions). A template's variables are parameters of
// its route of the same name, in the path or the query alike.
function pg_mcp_resource_kinds() {

	return array(

		'my_day' => array(
			'uri'         => 'pinegrap://workspace/my-day',
			'route'       => 'workspace.my_day.get',
			'name'        => 'workspace_my_day',
			'title'       => 'My day',
			'description' => 'What the person who connected has on their plate today: open tasks due today or overdue, the running timer, today\'s plan items, approvals and read receipts waiting for their answer, changes proposed at their request, and replies and mentions written to them lately.'
		),

		'channel_context' => array(
			'uri_template' => 'pinegrap://workspace/channels/{id}/context',
			'route'        => 'workspace.channels.context',
			'name'         => 'workspace_channel_context',
			'title'        => 'Channel context',
			'description'  => 'What a Workspace channel or discussion is about, in one read: its summary, latest decisions and notes, latest messages (oldest first) and open tasks. id is the channel\'s id; only channels the person may read.'
		),

		'channel_customer' => array(
			'uri_template' => 'pinegrap://workspace/channels/{id}/customer',
			'route'        => 'workspace.channels.customer',
			'name'         => 'workspace_channel_customer',
			'title'        => 'Channel customer',
			'description'  => 'Who a Workspace channel\'s customer is - the contact, user or current account chosen for the channel - and where they stand: how many orders they placed and the latest five, each current account tied to them with its balance, their open sales quotes and the latest forms their contact submitted. A section is null where the person holds no right to it or the key not the scope for it, or where it holds nothing; customer is null for a channel about nobody. id is the channel\'s id; only channels the person may read.'
		),

		'record_refs' => array(
			'uri_template' => 'pinegrap://workspace/refs/{type}/{id}',
			'route'        => 'workspace.refs.get',
			'name'         => 'workspace_record_refs',
			'title'        => 'Where a record was talked about',
			'description'  => 'The messages and tasks that point at one record, in the channels the person may read. type is order, product, product_group, offer, cart_quote, contact, user_account, erp_account, invoice, waybill, receipt, edoc, erp_quote, form, calendar_event, file or page; id is the record\'s id.'
		)

	);

}

// What a template variable takes: an id is a positive whole number written
// without leading zeros, a type a lowercase word. A value must be written
// out exactly so: nothing is percent-decoded, so %35 is not 5 and %2F is no
// slash, and one uri always names one record.
function pg_mcp_resource_variable_pattern($name) {

	return ($name === 'type') ? '[a-z][a-z_]{0,31}' : '[1-9][0-9]{0,17}';

}

// A uri with its scheme and host in lowercase (RFC 3986 6.2.2.1: both are
// case-insensitive; the path is not), or null for one without both or with a
// user or a port.
function pg_mcp_resource_normalise($uri) {

	if (preg_match('#^([A-Za-z][A-Za-z0-9+.\-]*)://([A-Za-z0-9.\-]+)(/.*)?\z#s', $uri, $found) !== 1) {

		return null;

	}

	return strtolower($found[1]) . '://' . strtolower($found[2]) . (isset($found[3]) ? $found[3] : '');

}

// The resource a uri names: array('kind' => key, 'arguments' => variable =>
// value as text), or null when it names none. The whole uri must match a
// fixed resource or a template: no query, no fragment, no part before or
// after.
function pg_mcp_resource_parse($uri) {

	if (!is_string($uri) || ($uri === '') || (strlen($uri) > 512)) {

		return null;

	}

	$uri = pg_mcp_resource_normalise($uri);

	if ($uri === null) {

		return null;

	}

	foreach (pg_mcp_resource_kinds() as $key => $kind) {

		if (isset($kind['uri'])) {

			if ($uri === $kind['uri']) {

				return array('kind' => $key, 'arguments' => array());

			}

			continue;

		}

		$names = array();

		$pattern = preg_replace_callback('#\\\\\{([a-z_]+)\\\\\}#', function ($found) use (&$names) {

			$names[] = $found[1];

			return '(' . pg_mcp_resource_variable_pattern($found[1]) . ')';

		}, preg_quote($kind['uri_template'], '#'));

		if (preg_match('#^' . $pattern . '\z#', $uri, $values) === 1) {

			$arguments = array();

			foreach ($names as $index => $name) {

				$arguments[$name] = $values[$index + 1];

			}

			return array('kind' => $key, 'arguments' => $arguments);

		}

	}

	return null;

}

// The uri of a resource with its variables filled in, or '' when a value is
// missing or is not one its variable takes.
function pg_mcp_resource_uri($key, $values) {

	$kinds = pg_mcp_resource_kinds();

	if (!isset($kinds[$key])) {

		return '';

	}

	if (isset($kinds[$key]['uri'])) {

		return $kinds[$key]['uri'];

	}

	$valid = true;

	$uri = preg_replace_callback('#\{([a-z_]+)\}#', function ($found) use ($values, &$valid) {

		$value = (isset($values[$found[1]]) && is_scalar($values[$found[1]])) ? (string)$values[$found[1]] : '';

		if (preg_match('#^' . pg_mcp_resource_variable_pattern($found[1]) . '\z#', $value) !== 1) {

			$valid = false;

		}

		return $value;

	}, $kinds[$key]['uri_template']);

	return $valid ? (string)$uri : '';

}

// The route that answers a kind of resource for this caller, or null when it
// is not one of their tools: not offered over MCP, or outside their scopes.
// $routes is pg_mcp_routes(), by tool name.
function pg_mcp_resource_route($key, $routes) {

	$kinds = pg_mcp_resource_kinds();

	if (!isset($kinds[$key])) {

		return null;

	}

	$name = pg_mcp_tool_name($kinds[$key]['route']);

	return (isset($routes[$name]['id']) && ($routes[$name]['id'] === $kinds[$key]['route'])) ? $routes[$name] : null;

}

// One entry of resources/list or resources/templates/list.
function pg_mcp_resource_entry($kind) {

	$entry = isset($kind['uri']) ? array('uri' => $kind['uri']) : array('uriTemplate' => $kind['uri_template']);

	return $entry + array(
		'name'        => $kind['name'],
		'title'       => $kind['title'],
		'description' => $kind['description'],
		'mimeType'    => 'application/json'
	);

}

// resources/list: the fixed resources the caller may read.
function pg_mcp_resources_list($routes) {

	$list = array();

	foreach (pg_mcp_resource_kinds() as $key => $kind) {

		if (isset($kind['uri']) && (pg_mcp_resource_route($key, $routes) !== null)) {

			$list[] = pg_mcp_resource_entry($kind);

		}

	}

	return $list;

}

// resources/templates/list: the templates the caller may read through.
function pg_mcp_resource_templates_list($routes) {

	$list = array();

	foreach (pg_mcp_resource_kinds() as $key => $kind) {

		if (isset($kind['uri_template']) && (pg_mcp_resource_route($key, $routes) !== null)) {

			$list[] = pg_mcp_resource_entry($kind);

		}

	}

	return $list;

}

// The error for a uri that names nothing the caller may read: -32602
// (invalid params) in the current generation, -32002 in the earlier ones,
// which clients of both still accept. $error is the API's error object when
// the route refused the read: what the tool would have shown, no more.
function pg_mcp_resource_not_found($uri, $modern, $error = null) {

	$data = array('uri' => mb_substr((string)$uri, 0, 512));

	if (is_array($error)) {

		$data['error'] = $error;

	}

	return array('code' => $modern ? -32602 : -32002, 'message' => 'Resource not found', 'data' => $data);

}

// The error for a read the route refused. A refusal of the caller's request -
// a record that is not there or not theirs, a value the route does not take -
// is a resource not found, whatever its status; data.error is the route's
// error object as the tool shows it, its code included (a 403 stays
// forbidden), so a read tells what the tool tells, no more and no less. A
// failure of the route itself is an internal error.
function pg_mcp_resource_refusal($uri, $status_code, $body, $modern) {

	$error = (is_array($body) && isset($body['error']) && is_array($body['error'])) ? $body['error'] : null;

	if (($status_code >= 400) && ($status_code < 500)) {

		return pg_mcp_resource_not_found($uri, $modern, $error);

	}

	$data = array('uri' => mb_substr((string)$uri, 0, 512));

	if ($error !== null) {

		$data['error'] = $error;

	}

	return array('code' => -32603, 'message' => 'Internal error', 'data' => $data);

}

// A read's result: one text item, the route's JSON body (pg_mcp_json_encode(),
// a broken byte written as U+FFFD), under the uri as the client sent it. null
// when the body cannot be JSON at all, so it is never passed off as an empty
// resource.
function pg_mcp_resource_contents($uri, $body) {

	$text = pg_mcp_json_encode($body);

	if ($text === null) {

		return null;

	}

	return array('contents' => array(array(
		'uri'      => (string)$uri,
		'mimeType' => 'application/json',
		'text'     => $text
	)));

}

// What resources/read does before any route runs, for a caller whose tools
// are $routes (pg_mcp_routes()): array('error' => ...) for params without a
// uri, or a uri that names nothing this caller may read (pg_mcp_answer()
// writes it), otherwise array('route', 'arguments', 'uri') - the route to
// run, its parameters from the uri's variables, and the uri as sent.
function pg_mcp_resource_target($params, $routes, $modern) {

	if (!isset($params['uri']) || !is_string($params['uri'])) {

		return array('error' => array('code' => -32602, 'message' => 'Invalid params: uri must be a string.'));

	}

	$uri = $params['uri'];

	$found = pg_mcp_resource_parse($uri);

	$route = ($found !== null) ? pg_mcp_resource_route($found['kind'], $routes) : null;

	if ($route === null) {

		return array('error' => pg_mcp_resource_not_found($uri, $modern));

	}

	return array('route' => $route, 'arguments' => $found['arguments'], 'uri' => $uri);

}

// What resources/read answers once the route has: array('result' => the
// contents) on a 2xx, array('error' => ...) for a refusal or a failure
// (pg_mcp_resource_refusal()), a 2xx body that cannot be JSON included, as
// the REST answer turns it into a 500. A read is never cached for longer than
// the answer itself: what it holds changes with every message.
function pg_mcp_resource_answer($uri, $status_code, $body, $modern) {

	if (($status_code >= 200) && ($status_code < 300)) {

		$contents = pg_mcp_resource_contents($uri, $body);

		if ($contents === null) {

			return array('error' => pg_mcp_resource_refusal($uri, 500, null, $modern));

		}

		return array('result' => pg_mcp_cache_hints($contents, 0, $modern));

	}

	return array('error' => pg_mcp_resource_refusal($uri, $status_code, $body, $modern));

}

/* ---------------------------------------------------------------------------
   Within a request
   --------------------------------------------------------------------------- */

// resources/read.
function pg_mcp_resource_read($id, $params, $modern) {

	$target = pg_mcp_resource_target($params, pg_mcp_routes(), $modern);

	if (isset($target['error'])) {

		pg_mcp_answer($id, $target, $modern);

	}

	$uri = $target['uri'];

	pg_mcp_route_run($target['route'], $target['arguments'], function ($status_code, $body) use ($id, $uri, $modern) {

		pg_mcp_answer($id, pg_mcp_resource_answer($uri, $status_code, $body, $modern), $modern);

	});

}
