<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// POST /batch: several reads of the API in one request.
//
// Each entry is a GET of an API route, run inside this request the way the MCP
// server runs a handler: the verb, path, input and route are set through the
// router's setters, and api_send_capture() makes the handler's answer come back
// as an ApiAnswerCaptured instead of being sent. Every entry goes through its
// own scope check, its own validation and its own handler (with its row rule
// and its 404), so an entry can never read what the same GET would refuse, and
// one entry's error never fails the others.

if (!defined('PG_API_ENTRY')) {
	exit;
}

// The most entries one batch takes.
function api_batch_limit() {

	return 20;

}

// The keys an entry may carry.
function api_batch_entry_keys() {

	return array('id', 'path', 'query', 'if_none_match');

}

// One entry of the body, checked and normalised without answering.
//
// Gives array('id' => string|null, 'path' => '/products/12', 'query' =>
// array(name => string), 'if_none_match' => string), or array('error' =>
// array('field' => key, 'problem' => word)) where the word is one of
// not_object, unknown_key, id, path, query, if_none_match. A query string
// written into the path (/products?limit=5) is read as query, and the query
// object, when both name a key, wins.
function api_batch_entry_check($entry) {

	if (!is_array($entry) || (!empty($entry) && array_values($entry) === $entry)) {

		return array('error' => array('field' => '', 'problem' => 'not_object'));

	}

	foreach (array_keys($entry) as $key) {

		if (!in_array((string)$key, api_batch_entry_keys(), true)) {

			return array('error' => array('field' => (string)$key, 'problem' => 'unknown_key'));

		}

	}

	$id = null;

	if (isset($entry['id'])) {

		if (!is_string($entry['id']) && !is_int($entry['id'])) {

			return array('error' => array('field' => 'id', 'problem' => 'id'));

		}

		$id = (string)$entry['id'];

		if (mb_strlen($id) > 100) {

			return array('error' => array('field' => 'id', 'problem' => 'id'));

		}

	}

	$raw_path = isset($entry['path']) ? $entry['path'] : null;

	if (!is_string($raw_path) || (trim($raw_path) === '') || (strlen($raw_path) > 2000) || (substr(trim($raw_path), 0, 1) !== '/')) {

		return array('error' => array('field' => 'path', 'problem' => 'path'));

	}

	$raw_path = trim($raw_path);

	$query = array();

	$mark = strpos($raw_path, '?');

	if ($mark !== false) {

		parse_str(substr($raw_path, $mark + 1), $query);

		$raw_path = substr($raw_path, 0, $mark);

	}

	// The router's own normalising (api_request_path()): one leading slash,
	// no trailing one, no doubled separators.
	$path = '/' . trim((string)preg_replace('#/+#', '/', $raw_path), '/');

	if (isset($entry['query'])) {

		$sent = $entry['query'];

		if (is_string($sent)) {

			$parsed = array();

			parse_str(ltrim($sent, '?'), $parsed);

			$sent = $parsed;

		} else if (!is_array($sent) || (!empty($sent) && array_values($sent) === $sent)) {

			return array('error' => array('field' => 'query', 'problem' => 'query'));

		}

		$query = array_merge($query, $sent);

	}

	// A query value is text, as in an address; true and false as words.
	$values = array();

	foreach ($query as $name => $value) {

		if ($value === null) {

			continue;

		}

		if (is_bool($value)) {

			$value = $value ? 'true' : 'false';

		}

		if (!is_scalar($value)) {

			return array('error' => array('field' => 'query', 'problem' => 'query'));

		}

		$values[(string)$name] = (string)$value;

	}

	// The router never reads path from the input (api_request_input()).
	unset($values['path']);

	$if_none_match = '';

	if (isset($entry['if_none_match'])) {

		if (!is_string($entry['if_none_match']) || (strlen($entry['if_none_match']) > 1000)) {

			return array('error' => array('field' => 'if_none_match', 'problem' => 'if_none_match'));

		}

		$if_none_match = trim($entry['if_none_match']);

	}

	return array('id' => $id, 'path' => $path, 'query' => $values, 'if_none_match' => $if_none_match);

}

// Whether a GET route may be an entry: not a token route or another route
// that needs no credentials, not a file (its bytes are no JSON body), and not
// the descriptions of the API, which are documents rather than reads of the
// site.
function api_batch_route_allowed($route) {

	if (!is_array($route) || !isset($route['method']) || ($route['method'] !== 'GET')) {

		return false;

	}

	if (!empty($route['public']) || !empty($route['binary'])) {

		return false;

	}

	$id = isset($route['id']) ? (string)$route['id'] : '';

	if (in_array($id, array('batch', 'openapi', 'docs', 'docs.endpoints', 'discovery'), true)) {

		return false;

	}

	return true;

}

// The route an entry's path reads, as the router finds it for a GET: the first
// route whose pattern matches and that takes GET, with its path_values. Gives
// array('route' => route), array('refused' => 'not_batchable') when the path
// is a route that may not be an entry (a write, a file, a token route), or
// array('refused' => 'unknown') when no route has that path at all.
function api_batch_route_find($path, $routes) {

	$known = false;

	foreach ($routes as $route) {

		if (!preg_match(api_route_pattern($route), $path, $matches)) {

			continue;

		}

		$known = true;

		$accepted = array($route['method']);

		if (isset($route['also_accepts'])) {

			$accepted = array_merge($accepted, (array)$route['also_accepts']);

		}

		if (!in_array('GET', $accepted, true)) {

			continue;

		}

		if (!api_batch_route_allowed($route)) {

			return array('refused' => 'not_batchable');

		}

		$names = array();

		preg_match_all('#\{([a-z_]+)\}#i', $route['path'], $names);

		$route['path_values'] = array();

		foreach ($names[1] as $index => $name) {

			$route['path_values'][$name] = isset($matches[$index + 1]) ? urldecode($matches[$index + 1]) : '';

		}

		return array('route' => $route);

	}

	return array('refused' => $known ? 'not_batchable' : 'unknown');

}

// The refusal of an entry that did not pass api_batch_entry_check().
function api_batch_entry_refusal($index, $error) {

	$field = 'requests[' . (int)$index . ']' . (($error['field'] !== '') ? '.' . $error['field'] : '');

	switch ($error['problem']) {

		case 'not_object':

			$message = lang('An entry must be an object of id, path, query and if_none_match.');

			break;

		case 'unknown_key':

			$message = lang(array('string' => 'An entry takes id, path, query and if_none_match, not {var:1}.', 'vars' => $error['field']));

			break;

		case 'id':

			$message = lang('id must be text of at most 100 characters.');

			break;

		case 'path':

			$message = lang('path is required: the address of a read, starting with /, such as /products/12.');

			break;

		case 'query':

			$message = lang('query must be an object of single values, or a query string.');

			break;

		default:

			$message = lang('if_none_match must be the ETag of an earlier answer.');

	}

	api_fail(422, 'validation_failed', $message, $field);

}

// Runs one entry and answers array('status', 'etag', 'body'). $count_hit is
// called first (the rate limit; it may refuse with 429), $require_scope with
// the route's scope. Everything the entry sets is put back afterwards.
function api_batch_run_entry($index, $entry, $routes, $count_hit, $require_scope) {

	$saved = array(
		'method'        => api_request_method(),
		'path'          => api_request_path(),
		'input'         => api_request_input(),
		'body_keys'     => api_request_body_keys(),
		'route'         => api_current_route(),
		'headers'       => api_extra_headers(),
		'if_none_match' => api_header('If-None-Match'),
		'fields_set'    => array_key_exists('fields', $_GET),
		'fields'        => isset($_GET['fields']) ? $_GET['fields'] : null
	);

	$status = 500;

	$body = null;

	$etag = null;

	api_send_capture(true);

	try {

		try {

			call_user_func($count_hit, $index);

			$checked = api_batch_entry_check($entry);

			if (isset($checked['error'])) {

				api_batch_entry_refusal($index, $checked['error']);

			}

			$found = api_batch_route_find($checked['path'], $routes);

			if (isset($found['refused']) && ($found['refused'] === 'unknown')) {

				api_fail(404, 'unknown_endpoint', lang('No such endpoint.'), 'requests[' . (int)$index . '].path');

			}

			if (isset($found['refused'])) {

				api_fail(400, 'not_batchable', lang('That address cannot be an entry of a batch: an entry is a read that answers JSON. Send writes, files and sign-in calls on their own.'), 'requests[' . (int)$index . '].path');

			}

			$route = $found['route'];

			api_request_method('GET');

			api_request_path($checked['path']);

			api_request_input($checked['query']);

			api_request_body_keys(array());

			api_current_route($route);

			api_header('If-None-Match', $checked['if_none_match']);

			// api_response_fields() reads ?fields= of the request itself.
			if (isset($checked['query']['fields'])) {

				$_GET['fields'] = $checked['query']['fields'];

			} else {

				unset($_GET['fields']);

			}

			if ($route['scope'] !== '') {

				call_user_func($require_scope, $route['scope']);

			}

			$values = api_validate_input($route, $checked['query']);

			call_user_func($route['handler'], $values);

			api_fail_server();

		} catch (ApiAnswerCaptured $answer) {

			$status = $answer->status;

			$body = $answer->body;

			// The same trimming and tag a GET of its own gets in api_send().
			if ($status === 200) {

				$body = api_response_fields($body);

				$json = api_json_encode($body);

				if ($json === null) {

					$status = 500;

					$body = api_batch_server_error();

				} else {

					$etag = api_etag_of_json($json);

					if (api_etag_matches($etag)) {

						$status = 304;

						$body = null;

					}

				}

			}

		} catch (Exception $fault) {

			api_batch_fault($fault);

			$body = api_batch_server_error();

		} catch (Error $fault) {

			api_batch_fault($fault);

			$body = api_batch_server_error();

		}

	} finally {

		api_send_capture(false);

		api_request_method($saved['method']);

		api_request_path($saved['path']);

		api_request_input($saved['input']);

		api_request_body_keys($saved['body_keys']);

		if ($saved['route'] !== null) {

			api_current_route($saved['route']);

		}

		api_header('If-None-Match', $saved['if_none_match']);

		if ($saved['fields_set']) {

			$_GET['fields'] = $saved['fields'];

		} else {

			unset($_GET['fields']);

		}

		// An entry's own headers (Allow, Retry-After, Location) are not the
		// batch's; the rate limit's count is, and stays at its latest.
		$headers = $saved['headers'];

		foreach (api_extra_headers() as $name => $value) {

			if (strpos($name, 'X-RateLimit-') === 0) {

				$headers[$name] = $value;

			}

		}

		api_extra_headers($headers);

	}

	return array('status' => (int)$status, 'etag' => $etag, 'body' => $body);

}

// The envelope of an entry whose handler failed.
function api_batch_server_error() {

	return array('error' => array(
		'code'       => 'server_error',
		'message'    => lang('A system error occurred.'),
		'request_id' => api_request_id()
	));

}

// A fault inside one entry, written to the error log as the fault guard
// (includes/api/fault.php) would write one that ended the request.
function api_batch_fault($fault) {

	if (function_exists('pg_error_write') && function_exists('pg_error_entry')) {

		pg_error_write(pg_error_entry(
			'error',
			get_class($fault),
			'API batch entry: ' . $fault->getMessage(),
			$fault->getFile(),
			$fault->getLine(),
			array()));

	}

}

// The answers of every entry, in the order asked: array of array('id',
// 'status', 'etag', 'body').
function api_batch_answers($entries, $routes, $count_hit, $require_scope) {

	$answers = array();

	foreach (array_values($entries) as $index => $entry) {

		$answer = api_batch_run_entry($index, $entry, $routes, $count_hit, $require_scope);

		$id = (is_array($entry) && isset($entry['id']) && (is_string($entry['id']) || is_int($entry['id']))) ? (string)$entry['id'] : null;

		$answers[] = array('id' => $id) + $answer;

	}

	return $answers;

}

// Counts an entry against the caller's rate limit. The call itself was
// counted by the router, and stands for the first entry, so a batch of n
// entries costs n.
function api_batch_count_hit($index) {

	if ((int)$index > 0) {

		api_rate_limit_check(api_current_app());

	}

}

function api_batch_run($params) {

	$entries = isset($params['requests']) ? (array)$params['requests'] : array();

	if (empty($entries)) {

		api_fail(422, 'validation_failed', lang('requests must hold at least one entry.'), 'requests');

	}

	api_ok(array('responses' => api_batch_answers($entries, api_all_routes(), 'api_batch_count_hit', 'api_require_scope')));

}
