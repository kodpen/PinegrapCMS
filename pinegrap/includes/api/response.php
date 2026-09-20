<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The response contract: one JSON shape, real HTTP status codes, one exit path.
//
// The previous surface answered every outcome with 200 and an English string in
// a "message" field, so a client could not tell "no such product" from "your key
// is wrong" without parsing prose, and no HTTP client, retry policy or generated
// SDK could work with it at all. Everything here exists to make the status line
// carry the meaning and the body carry a stable machine-readable code.

if (!defined('PG_API_ENTRY')) {
	exit;
}

// Codes are stable identifiers and stay English: they are compared in client
// code and must never change with the panel's language. The human sentence
// beside each one is translated.
function api_status_text($code) {

	$texts = array(
		200 => 'OK',
		201 => 'Created',
		204 => 'No Content',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		409 => 'Conflict',
		410 => 'Gone',
		413 => 'Payload Too Large',
		422 => 'Unprocessable Entity',
		429 => 'Too Many Requests',
		500 => 'Internal Server Error',
		503 => 'Service Unavailable'
	);

	return isset($texts[$code]) ? $texts[$code] : 'Internal Server Error';

}

// The single exit. Everything that answers a request comes through here, which
// is what guarantees the log line is written exactly once and that no handler
// can return without one.
function api_send($status_code, $body, $error_code = '') {

	// Anything the shared code printed on the way here - a notice out of
	// functions.php, a stray warning - is in the buffer and would sit in front
	// of the JSON and make it unparseable. That is precisely how the previous
	// surface broke: an undefined variable in a logging call put a PHP warning
	// ahead of the body on every write. Keep the buffer for the log when
	// debugging is on, drop it either way.
	$stray = '';

	if (ob_get_level() > 0) {

		$stray = (string)ob_get_clean();

	}

	if ($stray !== '' && defined('DEBUG') && DEBUG) {

		$body['_debug_output'] = $stray;

	}

	if (!headers_sent()) {

		header('Content-Type: application/json; charset=utf-8');

		header('X-Request-Id: ' . api_request_id());

		// An integration reads its own data; a cache or a shared proxy holding
		// on to it is a leak, not a saving.
		header('Cache-Control: no-store');

		header('X-Content-Type-Options: nosniff');

		foreach (api_extra_headers() as $name => $value) {

			header($name . ': ' . $value);

		}

		// PHP's own status line, so this works under CGI, FastCGI and mod_php
		// alike without depending on SAPI-specific behaviour.
		http_response_code($status_code);

	}

	// Recorded before the body goes out, so a client that retries the instant it
	// receives the answer already finds it on file.
	if (function_exists('api_idempotency_finish')) {

		api_idempotency_finish($status_code, $body);

	}

	api_log_request($status_code, $error_code);

	if ($status_code !== 204) {

		echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	}

	exit();

}

// The one exit for an HTML answer. The console at /docs is the only route that
// answers with a document rather than JSON; it goes through the same buffer
// reset and writes the same log row as api_send(), so a stray warning cannot
// land in front of the markup and the request is counted like any other.
//
// The page carries an inline script and stylesheet of its own and loads the
// panel's bundled Bootstrap files from this site; the policy allows exactly
// that and nothing from anywhere else. The frame and referrer headers are
// there because a key is typed into this page: it must not be embeddable in
// another site's frame, and its address must not travel with a link click.
function api_send_html($status_code, $html) {

	if (ob_get_level() > 0) {

		ob_end_clean();

	}

	if (!headers_sent()) {

		header('Content-Type: text/html; charset=utf-8');

		header('X-Request-Id: ' . api_request_id());

		header('Cache-Control: no-store');

		header('X-Content-Type-Options: nosniff');

		header('X-Frame-Options: DENY');

		header('Referrer-Policy: no-referrer');

		header("Content-Security-Policy: default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");

		foreach (api_extra_headers() as $name => $value) {

			header($name . ': ' . $value);

		}

		http_response_code($status_code);

	}

	api_log_request($status_code, '');

	echo $html;

	exit();

}

// Headers collected during the request (rate limit counters, Retry-After,
// Location) and flushed by api_send(). Kept in one place so a handler can add
// one without reaching for header() and losing it to the buffer reset.
function api_extra_headers($name = null, $value = null) {

	static $headers = array();

	if ($name === null) {

		return $headers;

	}

	$headers[$name] = $value;

	return $headers;

}

// A per-request identifier, echoed in the X-Request-Id header and stored on the
// log row. It is what turns "the API failed this morning" into one row.
function api_request_id() {

	static $id = '';

	if ($id === '') {

		if (function_exists('random_bytes')) {

			$id = bin2hex(random_bytes(16));

		} else {

			$id = md5(uniqid((string)mt_rand(), true));

		}

	}

	return $id;

}

function api_ok($data, $status_code = 200) {

	api_send($status_code, $data);

}

// A page of a collection. next_cursor is null on the last page, which is what a
// client loops on; total is absent unless it was asked for, because COUNT(*)
// over a large catalogue on every poll costs more than it tells.
function api_ok_list($rows, $limit, $next_cursor = null, $total = null) {

	$page = array(
		'limit'       => (int)$limit,
		'has_more'    => ($next_cursor !== null),
		'next_cursor' => $next_cursor
	);

	if ($total !== null) {

		$page['total'] = (int)$total;

	}

	api_send(200, array('data' => array_values($rows), 'page' => $page));

}

// The only way to fail. $code is the stable machine identifier, $message the
// translated sentence, $field the offending input when there is one.
function api_fail($status_code, $code, $message, $field = null) {

	$error = array(
		'code'       => $code,
		'message'    => $message,
		'request_id' => api_request_id()
	);

	if ($field !== null) {

		$error['field'] = $field;

	}

	// Where to read about it, on the failures that are about how the call was
	// made rather than about the record it asked for. A 404 for product 91 is
	// answered by the catalogue, not by the documentation, and a link on it
	// would be noise on the one error integrations see most.
	if (in_array($status_code, array(400, 401, 403, 405, 422, 429), true)
		&& function_exists('api_openapi_base_url')) {

		$error['docs'] = api_openapi_base_url() . '/docs';

	}

	api_send($status_code, array('error' => $error), $code);

}

// Failures common enough to be worth naming, so that every handler reports the
// same code for the same situation.

function api_fail_unauthorized($message = '') {

	// The reason is deliberately not narrowed. "No such key" and "wrong secret"
	// answered differently is a way to enumerate valid keys.
	if ($message === '') {

		$message = lang('Invalid credentials.');

	}

	// The challenge header makes a browser put its own login dialog in front
	// of the answer, and a script that made the request through fetch() never
	// sees the response until the dialog is dismissed - the console at /docs
	// would hang on a mistyped secret. A request that identifies itself as
	// script-made is therefore answered without it; a command-line client or a
	// generated SDK never sends that header and keeps the challenge.
	if (strtolower(trim(api_header('X-Requested-With'))) !== 'xmlhttprequest') {

		api_extra_headers('WWW-Authenticate', 'Basic realm="Pinegrap API"');

	}

	api_fail(401, 'unauthorized', $message);

}

function api_fail_scope($needed) {

	api_fail(403, 'insufficient_scope', lang(array(
		'string' => 'This application does not hold the {var:1} permission.',
		'vars'   => $needed
	)));

}

function api_fail_not_found($what = '') {

	if ($what === '') {

		$what = lang('Resource');

	}

	api_fail(404, 'not_found', lang(array('string' => '{var:1} not found.', 'vars' => $what)));

}

function api_fail_validation($message, $field = null) {

	api_fail(422, 'validation_failed', $message, $field);

}

function api_fail_server($message = '') {

	if ($message === '') {

		$message = lang('A system error occurred.');

	}

	api_fail(500, 'server_error', $message);

}

/* ---------------------------------------------------------------------------
   Cursor pagination
   ---------------------------------------------------------------------------
   Offset paging over a table that is being written to skips and repeats rows:
   insert one product while a sync is on page three and page four starts one row
   late. The cursor is the sort key of the last row handed out - a timestamp and
   an id, the id breaking ties between rows written in the same second - so the
   next page resumes exactly where the previous one stopped no matter what has
   been inserted in between.
   --------------------------------------------------------------------------- */

function api_cursor_encode($sort_value, $id) {

	$packed = json_encode(array('v' => (int)$sort_value, 'i' => (int)$id));

	return rtrim(strtr(base64_encode($packed), '+/', '-_'), '=');

}

// Returns array('v' => int, 'i' => int) or null when the cursor is missing or
// unreadable. A damaged cursor is a client mistake, so the caller answers 400
// rather than silently starting from the beginning and re-sending every row.
function api_cursor_decode($cursor) {

	$cursor = trim((string)$cursor);

	if ($cursor === '') {

		return null;

	}

	$padded = strtr($cursor, '-_', '+/');

	$remainder = strlen($padded) % 4;

	if ($remainder > 0) {

		$padded .= str_repeat('=', 4 - $remainder);

	}

	$decoded = base64_decode($padded, true);

	if ($decoded === false) {

		return null;

	}

	$parsed = json_decode($decoded, true);

	if (!is_array($parsed) || !isset($parsed['v']) || !isset($parsed['i'])) {

		return null;

	}

	return array('v' => (int)$parsed['v'], 'i' => (int)$parsed['i']);

}

/* ---------------------------------------------------------------------------
   Value formatting
   --------------------------------------------------------------------------- */

// Money crosses the wire as an integer count of minor units, never a decimal
// string and never a float. The previous surface accepted lira on the way in
// and returned lira on one endpoint, kurus on another; one of those had to be
// wrong in any given client.
function api_money($minor_units) {

	return (int)$minor_units;

}

// Timestamps cross the wire as ISO-8601 UTC. The unix integer is kept beside it
// because the store works in unix internally and a client that already has that
// habit should not have to parse a string back.
function api_time($unix) {

	$unix = (int)$unix;

	if ($unix <= 0) {

		return null;

	}

	return gmdate('Y-m-d\TH:i:s\Z', $unix);

}

// Accepts ISO-8601, 'YYYY-MM-DD', or a bare unix integer, and returns a unix
// timestamp or null. Used for every date filter so they all behave alike.
function api_time_parse($value) {

	$value = trim((string)$value);

	if ($value === '') {

		return null;

	}

	if (ctype_digit($value)) {

		return (int)$value;

	}

	$parsed = strtotime($value);

	return ($parsed === false) ? null : (int)$parsed;

}
