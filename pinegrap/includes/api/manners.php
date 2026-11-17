<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The HTTP manners an HTTP client library expects of the external API:
//
// - HEAD is answered as the GET of the same address, headers only, so a client
//   can ask whether a record exists or has changed (ETag) without its body.
// - A 201 names the new record's address in Location, where a GET route reads
//   it.
// - An answer whose error.message is a translated sentence says its language
//   in Content-Language.
// - Where an answer may be read cross-origin, Access-Control-Expose-Headers
//   lets the page's script read those headers.
// - A 401 for missing credentials carries the Basic challenge under the same
//   rule as a 401 for wrong ones.
//
// Pure helpers here; the router, api_send() and api_authenticate() call them.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The method a request is routed under: HEAD is the GET of the same address.
function api_route_method($method) {

	$method = strtoupper((string)$method);

	return ($method === 'HEAD') ? 'GET' : $method;

}

// Whether this request is a HEAD of the REST API, whose answer has no body.
// The MCP endpoint answers its own HEAD (pg_mcp_run()).
function api_request_is_head() {

	return !defined('PG_MCP_ENTRY')
		&& isset($_SERVER['REQUEST_METHOD'])
		&& (strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'HEAD');

}

// The Allow header's methods: every method an address accepts, HEAD beside GET.
function api_allow_methods($accepted) {

	$methods = array();

	foreach ((array)$accepted as $method) {

		$methods[] = (string)$method;

		if ($method === 'GET') {

			$methods[] = 'HEAD';

		}

	}

	return array_values(array_unique($methods));

}

// The address a 201 names: the request's path and the new record's id, when a
// GET route ($patterns, api_route_pattern() of each GET route) reads that path.
// '' when the answer has no id or no route reads it: POST /products answers
// /products/12, POST /auth/sessions has no record to point at.
function api_location_path($request_path, $body, $patterns) {

	if (!is_array($body) || !isset($body['id']) || !(is_int($body['id']) || (is_string($body['id']) && ($body['id'] !== '')))) {

		return '';

	}

	$id = (string)$body['id'];

	if (strpos($id, '/') !== false) {

		return '';

	}

	$path = rtrim((string)$request_path, '/') . '/' . rawurlencode($id);

	foreach ((array)$patterns as $pattern) {

		if (preg_match($pattern, $path) === 1) {

			return $path;

		}

	}

	return '';

}

// The Content-Language of an answer: the texts' language when the body carries
// a sentence in error.message, '' otherwise (a record's fields are data, not
// the answer's language).
function api_answer_language($body, $language) {

	if (!is_array($body) || !isset($body['error']) || !is_array($body['error'])
		|| !isset($body['error']['message']) || !is_string($body['error']['message'])
		|| ($body['error']['message'] === '')) {

		return '';

	}

	return (string)$language;

}

// The headers a cross-origin script may read beside the ones every browser
// shows: the request id, the manners above, the cache tag and the rate limit.
function api_exposed_headers() {

	return array(
		'X-Request-Id',
		'Content-Language',
		'Location',
		'ETag',
		'Retry-After',
		'Idempotent-Replay',
		'X-RateLimit-Limit',
		'X-RateLimit-Remaining',
		'X-RateLimit-Reset'
	);

}

// The extra headers with Access-Control-Expose-Headers added where an answer
// is open to other origins and does not name its own list.
function api_with_exposed_headers($headers) {

	$headers = (array)$headers;

	if (!isset($headers['Access-Control-Allow-Origin']) || isset($headers['Access-Control-Expose-Headers'])) {

		return $headers;

	}

	$headers['Access-Control-Expose-Headers'] = implode(', ', api_exposed_headers());

	return $headers;

}

// Whether a 401 carries the Basic challenge. A browser answers that challenge
// with its own login dialog, and a script that made the call through fetch()
// would never see the answer until the dialog is dismissed, so a request that
// says it is script-made (X-Requested-With: XMLHttpRequest) goes without it.
function api_basic_challenge_wanted($requested_with) {

	return strtolower(trim((string)$requested_with)) !== 'xmlhttprequest';

}
