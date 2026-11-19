<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Calls every GET route of a running site's external API and compares each
 * answer with the site's own OpenAPI document: the status, whether the body
 * is JSON, required fields, types and formats, null only where a field is
 * declared nullable, and fields the document does not declare. Lists are read
 * first; a route that reads one record takes the first row of its list (the
 * list at the path before it, or the one api_answer_check_parents() names),
 * each path parameter filled from the row's field of its name. A refusal the
 * route's own description names for that record (a 404, a 409) tries the
 * next rows, up to API_ANSWER_CHECK_TRIES, and is a skip when none answers.
 *
 * Read only: it sends GET requests and nothing else. It leaves out routes
 * marked 'moves_money' (read from the route rows of this checkout), routes
 * that answer with a file, and routes with a required query parameter it
 * cannot guess. It waits when X-RateLimit-Remaining runs out and on a 429.
 *
 * It needs a running site with a database, so it is not a CI check.
 *
 * Usage:  php tools/check_api_answers.php [--only=PATH_PREFIX]
 *         php tools/check_api_answers.php --help
 *         --only=/users reads only the routes whose path starts with it (the
 *         lists their records need are read as well).
 * Exit:   0 every answer keeps the document; 1 a difference was found, or the
 *         document could not be read; 2 usage or missing settings.
 *
 * Settings come from the environment, as tools/ws_client.php reads them:
 * PG_WS_SITE (the address ending in /integration.php), PG_WS_KEY,
 * PG_WS_SECRET. They are never taken as an argument and never printed. The
 * key should hold every read scope; a route it lacks the scope for is
 * reported as skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

require_once(__DIR__ . '/ws_client.php');

/**
 * The GET routes of an OpenAPI document, routes with fewer path parameters
 * first (a record waits for the list its id comes from), then by path.
 *
 * @param array<string,mixed> $document
 * @return array<int,array{method: string, path: string, id: string, description: string, binary: bool, schema: array|null, codes: int[], query: string[], required_query: string[]}>
 */
function api_answer_check_routes($document)
{
	$routes = array();
	$paths = isset($document['paths']) && is_array($document['paths']) ? $document['paths'] : array();

	foreach ($paths as $path => $operations) {
		if (!is_array($operations) || !isset($operations['get']) || !is_array($operations['get'])) {
			continue;
		}

		$operation = $operations['get'];
		$responses = isset($operation['responses']) && is_array($operation['responses']) ? $operation['responses'] : array();
		$codes = array();
		$schema = null;
		$binary = false;

		foreach ($responses as $code => $response) {
			$code = (int) $code;

			if ($code < 200 || $code > 299 || $code === 204 || $code === 206) {
				continue;
			}

			$codes[] = $code;
			$content = isset($response['content']) && is_array($response['content']) ? $response['content'] : array();

			if (isset($content['application/octet-stream'])) {
				$binary = true;
			}

			if ($schema === null && isset($content['application/json']['schema']) && is_array($content['application/json']['schema'])) {
				$schema = $content['application/json']['schema'];
			}
		}

		$query = array();
		$required = array();

		foreach (isset($operation['parameters']) && is_array($operation['parameters']) ? $operation['parameters'] : array() as $parameter) {
			if (!is_array($parameter) || (isset($parameter['in']) ? $parameter['in'] : '') !== 'query') {
				continue;
			}

			$query[] = (string) $parameter['name'];

			if (!empty($parameter['required'])) {
				$required[] = (string) $parameter['name'];
			}
		}

		$routes[] = array(
			'method'         => 'GET',
			'path'           => (string) $path,
			'id'             => isset($operation['operationId']) ? (string) $operation['operationId'] : '',
			'description'    => isset($operation['description']) ? (string) $operation['description'] : '',
			'binary'         => $binary,
			'schema'         => $schema,
			'codes'          => $codes ?: array(200),
			'query'          => $query,
			'required_query' => $required,
		);
	}

	usort($routes, function ($a, $b) {
		$depth = substr_count($a['path'], '{') - substr_count($b['path'], '{');

		return $depth !== 0 ? $depth : strcmp($a['path'], $b['path']);
	});

	return $routes;
}

/**
 * Why a route is not called, '' when it is.
 *
 * @param array{method: string, path: string, binary: bool, required_query: string[]} $route
 * @param array<string,bool> $money "METHOD /path" of the routes marked moves_money
 * @return string
 */
function api_answer_check_skip($route, $money)
{
	if (!empty($money[$route['method'] . ' ' . $route['path']])) {
		return 'moves money';
	}

	if ($route['binary']) {
		return 'binary answer';
	}

	if (!empty($route['required_query'])) {
		return 'needs the query parameter ' . implode(', ', $route['required_query']);
	}

	return '';
}

/**
 * The first id of a listing's data, null when the answer is no listing or
 * the page is empty.
 *
 * @param mixed $answer the decoded body
 * @return int|string|null
 */
function api_answer_check_first_id($answer)
{
	if (!is_array($answer) || !isset($answer['data'][0]) || !is_array($answer['data'][0])) {
		return null;
	}

	$id = isset($answer['data'][0]['id']) ? $answer['data'][0]['id'] : null;

	return (is_int($id) || (is_string($id) && $id !== '')) ? $id : null;
}

/**
 * A route's path with each {parameter} replaced by the first id of the list
 * read at the path before it (or named by api_answer_check_parents()); null
 * when that list was not read or was empty.
 *
 * @param string $path
 * @param array<string,int|string|null> $first first id by resolved list path
 * @return string|null
 */
function api_answer_check_fill_path($path, $first)
{
	return api_answer_check_fill($path, array(), function ($list) use ($first) {
		return isset($first[$list]) ? array('data' => array(array('id' => $first[$list]))) : null;
	});
}

/**
 * How many records of its lists a route is tried with, at most, when each
 * answers a refusal its own description names for that record.
 */
if (!defined('API_ANSWER_CHECK_TRIES')) {
	define('API_ANSWER_CHECK_TRIES', 5);
}

/**
 * Where the records of a path parameter come from when no list route sits
 * at the path before it: the list (or record) to read, its parameters filled
 * as far as they share the route's path and from first rows beyond, and
 * where its rows are in the answer (see api_answer_check_rows()).
 *
 * @return array<string,array{list: string, rows: string}> by the path before the parameter
 */
function api_answer_check_parents()
{
	$messages = array('list' => '/workspace/channels/{id}/messages', 'rows' => 'data');

	return array(
		// A canvas is the message that carries it.
		'/workspace/messages'          => $messages,
		'/workspace/canvases'          => $messages,
		'/workspace/memory'            => array('list' => '/workspace/channels/{id}/memory', 'rows' => 'data'),
		'/workspace/mail/items'        => array('list' => '/workspace/mail/conversations/{id}', 'rows' => 'mails'),
		'/workspace/flow-runs'         => array('list' => '/workspace/flows/{id}/runs', 'rows' => 'data'),
		'/workspace/flows/{id}/boxes'  => array('list' => '/workspace/flows/{id}', 'rows' => 'draft.nodes'),
		'/design/proposals'            => array('list' => '/design/pages/{id}/proposals', 'rows' => 'data'),
		'/store/states'                => array('list' => '/store/countries', 'rows' => 'data[].states'),
		'/translations/pages'          => array('list' => '/pages', 'rows' => 'data'),
	);
}

/**
 * The rows of a decoded answer at a dotted path; "name[]" takes every item
 * of that list and reads the rest of the path in each, one after another.
 *
 * @param mixed $answer
 * @param string $spec such as 'data', 'draft.nodes', 'data[].states'
 * @return array<int,array<string,mixed>>
 */
function api_answer_check_rows($answer, $spec)
{
	$values = array($answer);

	foreach (explode('.', $spec) as $part) {
		$each = substr($part, -2) === '[]';
		$name = $each ? substr($part, 0, -2) : $part;
		$next = array();

		foreach ($values as $value) {
			if (!is_array($value) || !isset($value[$name]) || !is_array($value[$name])) {
				continue;
			}

			if ($each) {
				foreach ($value[$name] as $item) {
					$next[] = $item;
				}
			} else {
				$next[] = $value[$name];
			}
		}

		$values = $next;
	}

	$rows = array();

	foreach ($values as $list) {
		foreach (is_array($list) ? $list : array() as $row) {
			if (is_array($row)) {
				$rows[] = $row;
			}
		}
	}

	return $rows;
}

/**
 * The value a row gives a path parameter: the row's field of the
 * parameter's name ({no} reads revision_no), else its id; null when it has
 * neither.
 *
 * @param array<string,mixed> $row
 * @param string $name the parameter without its braces
 * @return int|string|null
 */
function api_answer_check_param_value($row, $name)
{
	$aliases = array('no' => 'revision_no');
	$fields = array($name);

	if (isset($aliases[$name])) {
		$fields[] = $aliases[$name];
	}

	$fields[] = 'id';

	foreach ($fields as $field) {
		$value = isset($row[$field]) ? $row[$field] : null;

		if (is_int($value) || (is_string($value) && $value !== '')) {
			return $value;
		}
	}

	return null;
}

/**
 * A route's path with each {parameter} filled from a row of its list: the
 * list at the path before it, or the one api_answer_check_parents() names.
 * $tuple holds the row to take for each parameter in order (0 when left
 * out); null when a list cannot be read or has no such row.
 *
 * @param string $path
 * @param int[] $tuple
 * @param callable $fetch function (string $filled_path, string $template): mixed, the decoded answer or null
 * @param array{0: string, 1: string}|null $seed a path template and its filled path whose shared start is reused
 * @return string|null
 */
function api_answer_check_fill($path, $tuple, $fetch, $seed = null)
{
	$parents = api_answer_check_parents();
	$seed_template = $seed === null ? array() : explode('/', trim($seed[0], '/'));
	$seed_filled = $seed === null ? array() : explode('/', trim($seed[1], '/'));
	$aligned = true;
	$done = '';
	$template = '';
	$k = 0;

	foreach (explode('/', trim($path, '/')) as $i => $segment) {
		$filled = $segment;
		$aligned = $aligned && isset($seed_template[$i], $seed_filled[$i]) && $seed_template[$i] === $segment;

		if ($aligned) {
			$filled = $seed_filled[$i];
		} elseif ($segment !== '' && $segment[0] === '{') {
			if (isset($parents[$template])) {
				$list_template = $parents[$template]['list'];
				$list = api_answer_check_fill($list_template, array(), $fetch, array($template, $done));
				$spec = $parents[$template]['rows'];
			} else {
				$list_template = $template;
				$list = $done;
				$spec = 'data';
			}

			if ($list === null) {
				return null;
			}

			$rows = api_answer_check_rows(call_user_func($fetch, $list, $list_template), $spec);
			$index = isset($tuple[$k]) ? (int) $tuple[$k] : 0;
			$value = isset($rows[$index]) ? api_answer_check_param_value($rows[$index], substr($segment, 1, -1)) : null;

			if ($value === null) {
				return null;
			}

			$filled = rawurlencode((string) $value);
		}

		if ($segment !== '' && $segment[0] === '{') {
			$k++;
		}

		$done .= '/' . $filled;
		$template .= '/' . $segment;
	}

	return $done;
}

/**
 * The row of each list to try a route with, in order: the first rows
 * first, then by the sum of the rows, the outer list moving before the
 * inner one.
 *
 * @param int $depth the route's number of path parameters
 * @param int $bound rows of each list
 * @return array<int,int[]>
 */
function api_answer_check_tuples($depth, $bound)
{
	$tuples = array(array());

	for ($level = 0; $level < $depth; $level++) {
		$next = array();

		foreach ($tuples as $tuple) {
			for ($row = 0; $row < $bound; $row++) {
				$next[] = array_merge($tuple, array($row));
			}
		}

		$tuples = $next;
	}

	usort($tuples, function ($a, $b) {
		$sum = array_sum($a) - array_sum($b);

		if ($sum !== 0) {
			return $sum;
		}

		// The same sum: the tuple whose outer list moved further comes first.
		foreach ($a as $i => $row) {
			if ($row !== $b[$i]) {
				return $b[$i] - $row;
			}
		}

		return 0;
	});

	return $tuples;
}

/**
 * Whether an answer is a refusal the route's own description names for the
 * record it was called with ("404 for a task that has never repeated",
 * "409 not_a_collection"), so another record may answer: the status and
 * its code, '' when it is not. Only a route with a path parameter has
 * another record to try.
 *
 * @param array{path: string, description?: string} $route
 * @param array{status: int, body: string} $answer
 * @return string
 */
function api_answer_check_named_refusal($route, $answer)
{
	$status = (int) $answer['status'];
	$description = isset($route['description']) ? (string) $route['description'] : '';

	if ($status < 400 || $status > 499 || $status === 403 || $status === 429
		|| strpos($route['path'], '{') === false
		|| !preg_match('/\b' . $status . '\b/', $description)) {
		return '';
	}

	$json = json_decode($answer['body'], true);
	$code = isset($json['error']['code']) && is_scalar($json['error']['code']) ? ' ' . $json['error']['code'] : '';

	return 'status ' . $status . $code;
}

/**
 * The SKIP reason of a route every record tried refused as it describes.
 *
 * @param string[] $refusals "path status code" of each try
 * @return string
 */
function api_answer_check_refusal_skip($refusals)
{
	return 'every record tried (' . count($refusals) . ') was refused as the route describes: ' . implode('; ', $refusals);
}

/**
 * A schema with its $ref followed.
 *
 * @param mixed $schema
 * @param array<string,mixed> $document
 * @return array<string,mixed>|null
 */
function api_answer_check_resolve($schema, $document)
{
	for ($hops = 0; is_array($schema) && isset($schema['$ref']) && $hops < 20; $hops++) {
		$name = substr((string) $schema['$ref'], strlen('#/components/schemas/'));
		$schema = isset($document['components']['schemas'][$name]) ? $document['components']['schemas'][$name] : null;
	}

	return is_array($schema) ? $schema : null;
}

/**
 * The JSON name of a decoded value's kind.
 *
 * @param mixed $value
 * @return string
 */
function api_answer_check_kind($value)
{
	if ($value === null) {
		return 'null';
	}

	if (is_bool($value)) {
		return 'boolean';
	}

	if (is_int($value)) {
		return 'integer';
	}

	if (is_float($value)) {
		return 'number';
	}

	if (is_string($value)) {
		return 'string';
	}

	return ($value === array() || array_keys($value) === range(0, count($value) - 1)) ? 'array' : 'object';
}

/**
 * The differences between a decoded value and its schema, one line each;
 * a difference found in several rows of a list is told once.
 *
 * @param mixed $value
 * @param array<string,mixed> $schema
 * @param array<string,mixed> $document
 * @param string $where the field's path, '' for the whole answer
 * @return string[]
 */
function api_answer_check_value($value, $schema, $document, $where)
{
	$out = array();
	api_answer_check_walk($value, $schema, $document, $where, $out);

	return array_keys($out);
}

/**
 * @param mixed $value
 * @param mixed $schema
 * @param array<string,mixed> $document
 * @param string $where
 * @param array<string,bool> $out
 * @return void
 */
function api_answer_check_walk($value, $schema, $document, $where, &$out)
{
	$schema = api_answer_check_resolve($schema, $document);

	if ($schema === null) {
		return;
	}

	$label = ($where === '' ? '(answer)' : $where) . ': ';

	if ($value === null) {
		if (empty($schema['nullable'])) {
			$out[$label . 'null, not declared nullable'] = true;
		}

		return;
	}

	if (isset($schema['allOf']) && is_array($schema['allOf'])) {
		foreach ($schema['allOf'] as $part) {
			api_answer_check_walk($value, $part, $document, $where, $out);
		}

		return;
	}

	foreach (array('anyOf', 'oneOf') as $word) {
		if (!isset($schema[$word]) || !is_array($schema[$word])) {
			continue;
		}

		// The branch the value comes closest to tells what is wrong.
		$best = null;

		foreach ($schema[$word] as $part) {
			$found = array();
			api_answer_check_walk($value, $part, $document, $where, $found);

			if ($best === null || count($found) < count($best)) {
				$best = $found;
			}
		}

		$out += (array) $best;

		return;
	}

	$type = isset($schema['type']) ? (string) $schema['type'] : (isset($schema['properties']) ? 'object' : '');
	$kind = api_answer_check_kind($value);

	if ($type === 'object') {
		if ($kind !== 'object' && $value !== array()) {
			$out[$label . $kind . ', declared object'] = true;
			return;
		}

		$properties = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : null;
		$prefix = $where === '' ? '' : $where . '.';

		foreach (isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : array() as $name) {
			if (!array_key_exists($name, $value)) {
				$out[$prefix . $name . ': required, missing'] = true;
			}
		}

		foreach ((array) $properties as $name => $field) {
			if (array_key_exists($name, $value)) {
				api_answer_check_walk($value[$name], $field, $document, $prefix . $name, $out);
			}
		}

		$extra = isset($schema['additionalProperties']) ? $schema['additionalProperties'] : null;

		foreach ($value as $name => $field) {
			if ($properties !== null && array_key_exists($name, $properties)) {
				continue;
			}

			if (is_array($extra)) {
				api_answer_check_walk($field, $extra, $document, $prefix . $name, $out);
			} elseif ($properties !== null && $extra !== true) {
				$out[$prefix . $name . ': not declared'] = true;
			}
		}

		return;
	}

	if ($type === 'array') {
		if ($kind !== 'array') {
			$out[$label . $kind . ', declared array'] = true;
			return;
		}

		foreach ($value as $item) {
			api_answer_check_walk($item, isset($schema['items']) ? $schema['items'] : null, $document, $where . '[]', $out);
		}

		return;
	}

	$fits = array(
		'string'  => $kind === 'string',
		'integer' => $kind === 'integer',
		'number'  => $kind === 'integer' || $kind === 'number',
		'boolean' => $kind === 'boolean',
	);

	if (isset($fits[$type]) && !$fits[$type]) {
		$out[$label . $kind . ', declared ' . $type] = true;
		return;
	}

	$shown = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

	if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
		$out[$label . $shown . ' is not one of ' . implode(', ', array_map('strval', array_filter($schema['enum'], 'is_scalar')))] = true;
		return;
	}

	$format = isset($schema['format']) ? (string) $schema['format'] : '';
	$patterns = array(
		'date-time' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/',
		'date'      => '/^\d{4}-\d{2}-\d{2}$/',
	);

	if ($kind === 'string' && isset($patterns[$format]) && !preg_match($patterns[$format], $value)) {
		$out[$label . $shown . ' is not a ' . $format] = true;
	}
}

/**
 * The value of a header in a raw header block (the last one sent), '' when
 * none.
 *
 * @param string $headers
 * @param string $name
 * @return string
 */
function api_answer_check_header($headers, $name)
{
	$found = '';

	if (preg_match_all('/^' . preg_quote($name, '/') . ':\s*(.*?)\s*$/mi', (string) $headers, $m)) {
		$found = (string) end($m[1]);
	}

	return $found;
}

/**
 * The differences between one answer and its route in the document.
 *
 * @param array{schema: array|null, codes: int[]} $route
 * @param array{status: int, headers: string, body: string, error: string} $answer
 * @param array<string,mixed> $document
 * @return string[]
 */
function api_answer_check_answer($route, $answer, $document)
{
	if ($answer['error'] !== '') {
		return array('network: ' . $answer['error']);
	}

	$out = array();
	$json = json_decode($answer['body'], true);
	$is_json = json_last_error() === JSON_ERROR_NONE && is_array($json);

	if (!in_array($answer['status'], $route['codes'], true)) {
		$line = 'status ' . $answer['status'];

		if ($is_json && isset($json['error']['code']) && is_scalar($json['error']['code'])) {
			$line .= ' ' . $json['error']['code'];

			if (isset($json['error']['request_id']) && is_scalar($json['error']['request_id'])) {
				$line .= ' (request_id ' . $json['error']['request_id'] . ')';
			}
		}

		$out[] = $line . ', declared ' . implode(' or ', $route['codes']);
	}

	if (!$is_json) {
		$type = api_answer_check_header($answer['headers'], 'Content-Type');
		$type = $type === '' ? 'no Content-Type' : trim(explode(';', $type)[0]);
		$start = trim((string) preg_replace('/\s+/', ' ', substr($answer['body'], 0, 200)));
		$out[] = 'not JSON (' . $type . '): ' . (strlen($start) > 80 ? substr($start, 0, 80) . '...' : $start);

		return $out;
	}

	if ($out === array() && $route['schema'] !== null) {
		$out = api_answer_check_value($json, $route['schema'], $document, '');
	}

	return $out;
}

/**
 * Why an answer is a refusal of the key rather than a fault of the route,
 * '' when it is not: a 403 envelope (a scope the key does not hold, a route
 * kept for another kind of application) says nothing about the document.
 *
 * @param array{status: int, body: string} $answer
 * @return string
 */
function api_answer_check_refused($answer)
{
	if ($answer['status'] !== 403) {
		return '';
	}

	$json = json_decode($answer['body'], true);

	if (!isset($json['error']['code']) || !is_string($json['error']['code'])) {
		return '';
	}

	return $json['error']['code'] === 'insufficient_scope'
		? "the key does not hold this route's scope"
		: 'refused for this key: ' . $json['error']['code'];
}

/**
 * Seconds to wait before the next call: a 429 waits Retry-After (a minute
 * when it says nothing); the last call of a rate window waits for the next
 * window.
 *
 * @param string $headers
 * @param int $status
 * @param int $now
 * @return int
 */
function api_answer_check_wait($headers, $status, $now)
{
	if ($status === 429) {
		$after = api_answer_check_header($headers, 'Retry-After');

		return ctype_digit($after) ? (int) $after : 60;
	}

	$remaining = api_answer_check_header($headers, 'X-RateLimit-Remaining');
	$reset = api_answer_check_header($headers, 'X-RateLimit-Reset');

	if (!ctype_digit($remaining) || (int) $remaining > 1) {
		return 0;
	}

	return ctype_digit($reset) ? max(0, (int) $reset - $now) + 1 : 60;
}

/**
 * "METHOD /path" of every route row marked moves_money in this checkout,
 * core and modules, switched on or not.
 *
 * @return array<string,bool>
 */
function api_answer_check_money_routes()
{
	if (!defined('PG_API_PANEL')) {
		define('PG_API_PANEL', true);
	}

	require_once(dirname(__DIR__) . '/pinegrap/functions.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');

	$money = array();

	foreach (array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes()) as $route) {
		if (!empty($route['moves_money'])) {
			$money[$route['method'] . ' ' . $route['path']] = true;
		}
	}

	return $money;
}

/**
 * One GET with the key, waiting as the rate limit asks; a 429 is tried once
 * more.
 *
 * @param string $url
 * @param array{site: string, key: string, secret: string} $settings
 * @return array{status: int, headers: string, body: string, error: string}
 */
function api_answer_check_get($url, $settings)
{
	for ($try = 0; ; $try++) {
		$answer = ws_client_request('GET', $url, $settings);
		$wait = api_answer_check_wait($answer['headers'], $answer['status'], time());

		if ($wait > 0) {
			fwrite(STDERR, 'Rate limit: waiting ' . $wait . " s\n");
			sleep(min($wait, 120));
		}

		if ($answer['status'] !== 429 || $try >= 1) {
			return $answer;
		}
	}
}

/**
 * @param string[] $argv without the script name
 * @return int exit code
 */
function api_answer_check_main($argv)
{
	list($options) = ws_client_options($argv);

	if (isset($options['help'])) {
		echo "Usage: php tools/check_api_answers.php [--only=PATH_PREFIX]\n"
			. "Settings: PG_WS_SITE, PG_WS_KEY, PG_WS_SECRET in the environment.\n";
		return 0;
	}

	$settings = ws_client_settings($options, getenv(), false);

	if ($settings['missing'] !== array()) {
		fwrite(STDERR, 'Missing settings: ' . implode(', ', $settings['missing']) . "\n");
		return 2;
	}

	$only = isset($options['only']) && is_string($options['only']) ? $options['only'] : '';

	$answer = api_answer_check_get($settings['site'] . '/openapi.json', $settings);
	$document = json_decode($answer['body'], true);

	if ($answer['status'] !== 200 || !is_array($document) || !isset($document['paths'])) {
		fwrite(STDERR, 'The OpenAPI document could not be read: ' . ws_client_failure($answer) . "\n");
		return 1;
	}

	$money = api_answer_check_money_routes();
	$routes = api_answer_check_routes($document);
	$count = array('ok' => 0, 'diff' => 0, 'skip' => 0);

	// Answers read so far by filled path (null when not 200), and the
	// templates read with a short page.
	$read = array();
	$paged = array();

	foreach ($routes as $route) {
		$paged[$route['path']] = in_array('limit', $route['query'], true);
	}

	// A short page is enough to see the shape and the first rows.
	$call = function ($url_path, $template) use ($settings, $paged) {
		return api_answer_check_get($settings['site'] . $url_path . (!empty($paged[$template]) ? '?limit=5' : ''), $settings);
	};

	// A list a record needs and no route read yet is read when needed.
	$fetch = function ($url_path, $template) use (&$read, $call) {
		if (!array_key_exists($url_path, $read)) {
			$answer = $call($url_path, $template);
			$read[$url_path] = $answer['status'] === 200 ? json_decode($answer['body'], true) : null;
		}

		return $read[$url_path];
	};

	foreach ($routes as $route) {
		$path = $route['path'];
		$is_list = substr($path, -1) !== '}';

		// A list another route's record needs is read even outside --only.
		if ($only !== '' && strpos($path, $only) !== 0 && !($is_list && strpos($only, $path) === 0)) {
			continue;
		}

		$why = api_answer_check_skip($route, $money);
		$url_path = null;
		$answer = null;
		$refusals = array();

		if ($why === '') {
			foreach (api_answer_check_tuples(substr_count($path, '{'), API_ANSWER_CHECK_TRIES) as $tuple) {
				$candidate = api_answer_check_fill($path, $tuple, $fetch);

				if ($candidate === null) {
					continue;
				}

				$url_path = $candidate;
				$answer = $call($url_path, $path);
				$read[$url_path] = $answer['status'] === 200 ? json_decode($answer['body'], true) : null;
				$named = api_answer_check_named_refusal($route, $answer);

				if ($named === '') {
					break;
				}

				// A refusal the route names for this one record: the next row.
				$refusals[] = $url_path . ' ' . $named;
				$answer = null;

				if (count($refusals) >= API_ANSWER_CHECK_TRIES) {
					break;
				}
			}

			if ($answer === null) {
				$why = $refusals !== array() ? api_answer_check_refusal_skip($refusals) : 'no record to read: its list is empty or was not read';
			}
		}

		if ($why !== '') {
			echo 'SKIP ' . $route['method'] . ' ' . $path . ' - ' . $why . "\n";
			$count['skip']++;
			continue;
		}

		$refused = api_answer_check_refused($answer);

		if ($refused !== '') {
			echo 'SKIP ' . $route['method'] . ' ' . $path . ' - ' . $refused . "\n";
			$count['skip']++;
			continue;
		}

		$found = api_answer_check_answer($route, $answer, $document);
		$shown = $route['method'] . ' ' . $path . ($url_path !== $path ? ' (' . $url_path . ')' : '');

		if ($found === array()) {
			echo 'OK   ' . $shown . "\n";
			$count['ok']++;
			continue;
		}

		echo 'DIFF ' . $shown . "\n";

		foreach ($found as $line) {
			echo '     - ' . $line . "\n";
		}

		$count['diff']++;
	}

	echo "\n" . $count['ok'] . ' OK, ' . $count['diff'] . ' with differences, ' . $count['skip'] . " skipped.\n";

	return $count['diff'] > 0 ? 1 : 0;
}

if (!defined('PG_TEST_RUNNER')) {
	exit(api_answer_check_main(array_slice($argv, 1)));
}
