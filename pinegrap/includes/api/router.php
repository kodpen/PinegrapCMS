<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Reading the request, matching it to a route, and checking its input.
//
// Routing is on the path and the HTTP verb rather than an action parameter, so
// the same resource reads and writes through one address and the verb says which.
// The path arrives through PATH_INFO, which every server hands to PHP without a
// rewrite rule - the software is installed on IIS as often as on Apache, and an
// API that only works where mod_rewrite is available is an API that half the
// installations cannot use.

if (!defined('PG_API_ENTRY')) {
	exit;
}

function api_request_method() {

	static $method = '';

	if ($method !== '') {

		return $method;

	}

	$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';

	// Some managed hosts and corporate proxies drop PATCH and DELETE outright.
	// The override header is the usual way round it and is honoured only on a
	// POST, so it can never widen what a GET is allowed to do.
	if ($method === 'POST') {

		$override = strtoupper(trim(api_header('X-HTTP-Method-Override')));

		if (in_array($override, array('PATCH', 'PUT', 'DELETE'), true)) {

			$method = $override;

		}

	}

	return $method;

}

// The part after the entry point: /products/12 out of
// .../integration.php/products/12. The query fallback is for the rare server
// that will not populate PATH_INFO at all.
function api_request_path() {

	static $path = null;

	if ($path !== null) {

		return $path;

	}

	$path = isset($_SERVER['PATH_INFO']) ? (string)$_SERVER['PATH_INFO'] : '';

	// IIS does not always set PATH_INFO, and when it does not it puts the WHOLE
	// original URL into ORIG_PATH_INFO - script name included. Read raw, a call
	// to the bare entry point therefore arrives as the route
	// "/pinegrap/integration.php", which is answered "no such endpoint" and
	// logged under that path instead of being recognised as an empty one.
	// The script's own name comes off first.
	if ($path === '' && isset($_SERVER['ORIG_PATH_INFO'])) {

		$original = (string)$_SERVER['ORIG_PATH_INFO'];

		$script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';

		if ($script !== '' && strpos($original, $script) === 0) {

			$original = substr($original, strlen($script));

		}

		$path = $original;

	}

	if ($path === '' && isset($_GET['path'])) {

		$path = (string)$_GET['path'];

	}

	// One leading slash, no trailing slash, no doubled separators, so
	// /products/, //products and /products all reach the same route.
	$path = '/' . trim(preg_replace('#/+#', '/', $path), '/');

	return $path;

}

// A request header by name, case-insensitively, whichever way the server exposes
// it. Authorization is the awkward one: several server configurations strip it
// from the CGI environment and leave it only in apache_request_headers(), and an
// API that reads it in one place only fails on those hosts with no clue why.
function api_header($name) {

	static $headers = null;

	if ($headers === null) {

		$headers = array();

		if (function_exists('apache_request_headers')) {

			$apache = apache_request_headers();

			if (is_array($apache)) {

				foreach ($apache as $key => $value) {

					$headers[strtolower($key)] = $value;

				}

			}

		}

		foreach ($_SERVER as $key => $value) {

			if (strpos($key, 'HTTP_') === 0) {

				$headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;

			}

		}

		// Written by a common rewrite rule on hosts that strip the header.
		if (!isset($headers['authorization']) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {

			$headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];

		}

	}

	$name = strtolower($name);

	return isset($headers[$name]) ? trim((string)$headers[$name]) : '';

}

// The caller's real address. The firewall already resolves this through any
// trusted proxy or CDN; reading REMOTE_ADDR directly behind Cloudflare reports
// the edge server, which makes an address allow list meaningless.
function api_client_ip() {

	static $ip = null;

	if ($ip === null) {

		$ip = function_exists('waf_client_ip')
			? waf_client_ip()
			: (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

	}

	return $ip;

}

// Everything the caller sent, merged into one array: the JSON body when there is
// one, the form fields otherwise, with the query string underneath.
//
// Cookies are deliberately not part of this. They were left out of the previous
// surface for the same reason and it is worth restating: a browser attaches
// cookies to a cross-site request without the user's involvement, so a parameter
// that can arrive in a cookie is a parameter an attacker can set.
// The keys the request body carried, as opposed to the query string.
//
// Only the body is checked for parameters the endpoint does not know, below. A
// query string collects things that have nothing to do with the endpoint - a
// cache buster, an analytics tag, a proxy's own marker - and refusing those
// would break callers for no gain. A body key nobody reads is a different
// matter: it is a field the integration believes it is writing.
function api_request_body_keys($keys = null) {

	static $stored = array();

	if ($keys !== null) {

		$stored = $keys;

	}

	return $stored;

}

function api_request_input() {

	static $input = null;

	if ($input !== null) {

		return $input;

	}

	$input = $_GET;

	unset($input['path']);

	$raw = (string)@file_get_contents('php://input');

	if ($raw !== '') {

		$content_type = strtolower(api_header('Content-Type'));

		if (strpos($content_type, 'json') !== false) {

			$decoded = json_decode($raw, true);

			if (!is_array($decoded)) {

				api_fail(400, 'invalid_json', lang('The request body is not valid JSON.'));

			}

			api_request_body_keys(array_keys($decoded));

			$input = array_merge($input, $decoded);

		} elseif (!empty($_POST)) {

			api_request_body_keys(array_keys($_POST));

			$input = array_merge($input, $_POST);

		} else {

			// A body with no content type, or one PHP did not parse itself.
			$parsed = array();

			parse_str($raw, $parsed);

			api_request_body_keys(array_keys($parsed));

			$input = array_merge($input, $parsed);

		}

	} elseif (!empty($_POST)) {

		api_request_body_keys(array_keys($_POST));

		$input = array_merge($input, $_POST);

	}

	return $input;

}

// All routes: the ones that need credentials and the ones that do not.
function api_all_routes() {

	return array_merge(api_open_routes(), api_schema());

}

// The regular expression a route's path matches, with each placeholder narrowed
// to what its parameter is declared to be.
//
// A placeholder that matched anything would swallow its neighbours: with {id} as
// ([^/]+), POST /products/inventory - the bulk stock endpoint - is also a match
// for POST /products/{id}, and whichever route happens to be declared first
// wins. Reading the declared type turns {id} into a run of digits, so a path can
// only reach the handler it was written for.
function api_route_pattern($route) {

	$types = array();

	foreach ($route['params'] as $param) {

		if (isset($param['in']) && $param['in'] === 'path') {

			$types[$param['name']] = isset($param['type']) ? $param['type'] : 'string';

		}

	}

	// preg_quote escapes the braces, so a placeholder reads as \{name\} here.
	$quoted = preg_quote($route['path'], '#');

	$body = preg_replace_callback('#\\\{([a-z_]+)\\\}#i', function ($found) use ($types) {

		$type = isset($types[$found[1]]) ? $types[$found[1]] : 'string';

		return ($type === 'int') ? '([0-9]+)' : '([^/]+)';

	}, $quoted);

	return '#^' . $body . '$#i';

}

// Finds the route for this request. A path that exists under another verb is
// answered 405 with an Allow header rather than 404, because "you used the wrong
// method" and "there is no such thing" send a developer to very different places.
function api_route_match($method, $path) {

	$allowed = array();

	foreach (api_all_routes() as $route) {

		if (!preg_match(api_route_pattern($route), $path, $matches)) {

			continue;

		}

		// A route names the method it is documented under, and may accept others.
		// The updates accept PATCH beside POST: PATCH is the more precise verb,
		// but a default IIS install answers it with a 405 out of its WebDAV
		// handler before PHP is reached, so an API that only spoke PATCH would
		// have two endpoints that simply do not exist on a large share of the
		// installations. Removing WebDAV is a server-level change that cannot be
		// made from the web root - the sections it lives in are locked, and
		// writing them into web.config takes the whole site down with a 500.
		$accepted = array($route['method']);

		if (isset($route['also_accepts'])) {

			$accepted = array_merge($accepted, $route['also_accepts']);

		}

		if (!in_array($method, $accepted, true)) {

			$allowed = array_merge($allowed, $accepted);

			continue;

		}

		// Path placeholders, in the order they appear, paired with their names.
		$names = array();

		preg_match_all('#\{([a-z_]+)\}#i', $route['path'], $names);

		$path_values = array();

		foreach ($names[1] as $index => $name) {

			$path_values[$name] = isset($matches[$index + 1]) ? urldecode($matches[$index + 1]) : '';

		}

		$route['path_values'] = $path_values;

		return $route;

	}

	if (!empty($allowed)) {

		api_extra_headers('Allow', implode(', ', array_unique($allowed)));

		api_fail(405, 'method_not_allowed', lang(array(
			'string' => 'This address does not accept {var:1} requests.',
			'vars'   => $method
		)));

	}

	return null;

}

/* ---------------------------------------------------------------------------
   Input validation
   ---------------------------------------------------------------------------
   Every parameter is checked against its declaration in the schema before a
   handler sees it, so a handler works with values of the right type and never
   has to guess what arrived. A value that is wrong is refused with the name of
   the field, rather than being cast into something plausible - the old surface
   turned a price of "cheap" into zero and reported success.
   --------------------------------------------------------------------------- */

function api_validate_input($route, $input) {

	$values = array();

	$path_values = isset($route['path_values']) ? $route['path_values'] : array();

	foreach ($route['params'] as $param) {

		$name = $param['name'];

		$where = isset($param['in']) ? $param['in'] : 'query';

		if ($where === 'path') {

			$raw     = isset($path_values[$name]) ? $path_values[$name] : null;
			$present = ($raw !== null && $raw !== '');

		} else if ($where === 'body') {

			// A nullable body parameter accepts JSON null as the clear as well as
			// an empty string. null is the idiomatic way to empty a nullable
			// field and would otherwise be read as "field not sent".
			if (!empty($param['nullable']) && array_key_exists($name, $input) && $input[$name] === null) {

				$values[$name] = '';

				continue;

			}


			// In a body an empty string is a value, not an absence: it is how a
			// caller clears a field. Dropping it made a column impossible to
			// empty through the API - the write returned 200 and changed
			// nothing, which is worse than refusing.
			//
			// Nothing is let through untyped by this: a body parameter that is
			// not a string refuses an empty string in api_validate_value(), so
			// {"price": ""} is a 422 rather than a silent zero.
			$present = array_key_exists($name, $input) && $input[$name] !== null;
			$raw     = $present ? $input[$name] : null;

		} else {

			// A query string cannot express absence any other way: ?search=
			// with nothing after it is the same as not asking.
			$present = array_key_exists($name, $input) && $input[$name] !== null && $input[$name] !== '';
			$raw     = $present ? $input[$name] : null;

		}

		if (!$present) {

			if (!empty($param['required'])) {

				api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => $name)), $name);

			}

			if (array_key_exists('default', $param)) {

				$values[$name] = $param['default'];

			}

			continue;

		}

		// A nullable parameter takes an empty value as an instruction to clear
		// the field. The type check would refuse it - an empty string is not a
		// number - so it is answered before the type is looked at, and the
		// handler decides what clearing means for its column.
		if ($raw === '' && !empty($param['nullable'])) {

			$values[$name] = '';

			continue;

		}

		$values[$name] = api_validate_value($param, $raw);

	}

	// A field the endpoint does not take is refused rather than dropped.
	//
	// Silently ignoring it is how an integration sends "stock" for a year,
	// gets 200 every time and never writes a single quantity. The name is
	// reported back so the mistake is obvious from the response alone.
	$accepted = array('api_key', 'api_secret');

	foreach ($route['params'] as $param) {

		if ((isset($param['in']) ? $param['in'] : 'query') === 'body') {

			$accepted[] = $param['name'];

		}

	}

	foreach (api_request_body_keys() as $sent) {

		if (in_array($sent, $accepted, true)) {

			continue;

		}

		$shown = mb_substr((string)$sent, 0, 60);

		api_fail_validation(lang(array(
			'string' => 'This endpoint does not take a field called {var:1}.',
			'vars'   => $shown
		)), $shown);

	}

	return $values;

}

function api_validate_value($param, $raw) {

	$name = $param['name'];

	$type = isset($param['type']) ? $param['type'] : 'string';

	switch ($type) {

		case 'int':

			if (!is_numeric($raw) || (string)(int)$raw !== (string)trim((string)$raw)) {

				api_fail_validation(lang(array('string' => '{var:1} must be a whole number.', 'vars' => $name)), $name);

			}

			$value = (int)$raw;

			if (isset($param['min']) && $value < $param['min']) {

				api_fail_validation(lang(array('string' => '{var:1} is below the smallest accepted value.', 'vars' => $name)), $name);

			}

			if (isset($param['max']) && $value > $param['max']) {

				api_fail_validation(lang(array('string' => '{var:1} is above the largest accepted value.', 'vars' => $name)), $name);

			}

			return $value;

		case 'money':

			// Minor units only. A decimal here means the caller is thinking in
			// lira while the field counts kurus, and quietly rounding it would
			// change a price by a hundredfold in the wrong direction.
			if (!is_numeric($raw) || strpos((string)$raw, '.') !== false || strpos((string)$raw, ',') !== false) {

				api_fail_validation(lang(array(
					'string' => '{var:1} must be a whole number of minor units, with no decimal separator.',
					'vars'   => $name
				)), $name);

			}

			return (int)$raw;

		case 'decimal':

			if (!is_numeric($raw)) {

				api_fail_validation(lang(array('string' => '{var:1} must be a number.', 'vars' => $name)), $name);

			}

			$value = (float)$raw;

			// The bounds were declared in the schema and were being ignored
			// here, so a tax rate of 150 reached the write and was silently
			// clamped instead of refused.
			if (isset($param['min']) && $value < $param['min']) {

				api_fail_validation(lang(array('string' => '{var:1} is below the smallest accepted value.', 'vars' => $name)), $name);

			}

			if (isset($param['max']) && $value > $param['max']) {

				api_fail_validation(lang(array('string' => '{var:1} is above the largest accepted value.', 'vars' => $name)), $name);

			}

			return $value;

		case 'bool':

			// JSON carries true and false as booleans, and (string)false is the
			// empty string, which matches neither list below. Without this a
			// client could turn a switch on and never off: every {"field": false}
			// came back 422 while {"field": true} was accepted.
			if (is_bool($raw)) {

				return $raw;

			}

			$normalised = strtolower(trim((string)$raw));

			if (in_array($normalised, array('1', 'true', 'yes', 'on', 'enabled'), true)) {

				return true;

			}

			if (in_array($normalised, array('0', 'false', 'no', 'off', 'disabled'), true)) {

				return false;

			}

			api_fail_validation(lang(array('string' => '{var:1} must be true or false.', 'vars' => $name)), $name);

			return false;

		case 'enum':

			$normalised = trim((string)$raw);

			if (!in_array($normalised, $param['values'], true)) {

				api_fail_validation(lang(array(
					'string' => '{var:1} must be one of: {var:2}',
					'vars'   => array($name, implode(', ', $param['values']))
				)), $name);

			}

			return $normalised;

		case 'datetime':

			$parsed = api_time_parse($raw);

			if ($parsed === null) {

				api_fail_validation(lang(array(
					'string' => '{var:1} must be a date, in ISO-8601 or YYYY-MM-DD form.',
					'vars'   => $name
				)), $name);

			}

			return $parsed;

		case 'list':

			if (!is_array($raw)) {

				api_fail_validation(lang(array('string' => '{var:1} must be a list.', 'vars' => $name)), $name);

			}

			if (isset($param['max_items']) && count($raw) > $param['max_items']) {

				api_fail_validation(lang(array(
					'string' => '{var:1} holds more than the {var:2} items accepted in one request.',
					'vars'   => array($name, $param['max_items'])
				)), $name);

			}

			return $raw;

		default:

			$value = trim((string)$raw);

			if (isset($param['max_length']) && mb_strlen($value) > $param['max_length']) {

				api_fail_validation(lang(array(
					'string' => '{var:1} is longer than the {var:2} characters accepted.',
					'vars'   => array($name, $param['max_length'])
				)), $name);

			}

			return $value;

	}

}
