<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A fault that escapes a handler: an uncaught Throwable, or a fatal error
// (memory, time limit) that only a shutdown function still sees.
//
// Without this the site's own handler (pg_error_uncaught(), includes/fn/
// errors.php) answered the external API with a plain-text "An unexpected error
// occurred." as text/html, no code and no request id, so a client could not
// parse it and an operator could not find its line. Here such a request
// answers the envelope like any other failure: 500, code server_error, the
// request id in the body and in X-Request-Id. PHP's own message never goes to
// the client; it is in the error log, on a line that carries the same request
// id (pg_error_request_id()).
//
// The MCP endpoint is guarded the same way. A fault inside a tool, a resource
// read or a prompt comes back through the route's answer hook as the REST
// envelope in an isError result; a fault anywhere else answers a JSON-RPC
// internal error (-32603) whose data holds the request id.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The error_get_last() entry of an error that ended the request. A warning
// or notice that happened to be the last one is not a fault.
function api_fault_is_fatal($error) {

	if (!is_array($error) || !isset($error['type'])) {

		return false;

	}

	$fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

	return (((int)$error['type'] & $fatal) !== 0);

}

// Whether the shutdown function should answer: a fatal error ended the request
// and nothing has gone out yet. Once headers are sent the client already holds
// a status and part of a body, and a second answer would only corrupt it.
function api_fault_can_answer($error, $headers_sent) {

	return api_fault_is_fatal($error) && !$headers_sent;

}

// The REST answer to a fault. The sentence is the generic one, never the
// Throwable's or PHP's message, which can name files, queries and values.
function api_fault_body($request_id, $message) {

	return array('error' => array(
		'code'       => 'server_error',
		'message'    => (string)$message,
		'request_id' => (string)$request_id
	));

}

// The MCP answer to a fault outside a route: the request's own id is not known
// any more, so it is null, as JSON-RPC allows when the id cannot be told.
function api_fault_mcp_body($request_id) {

	return array(
		'jsonrpc' => '2.0',
		'id'      => null,
		'error'   => array(
			'code'    => -32603,
			'message' => 'Internal error',
			'data'    => array('request_id' => (string)$request_id)
		)
	);

}

// The generic sentence, in English when lang() is what failed.
function api_fault_message() {

	try {

		return (string)lang('A system error occurred.');

	} catch (Throwable $error) {

		return 'A system error occurred.';

	}

}

// Runs the endpoint ($run: api_run or pg_mcp_run) so that a fault answers as
// described above. api_send() and pg_mcp_send() exit, so a request that
// answers normally never comes back here.
function api_fault_guard($run) {

	register_shutdown_function('api_fault_shutdown');

	// On a fatal error with display_errors on, PHP prints its message and
	// flushes every output buffer before any shutdown function runs, so the
	// client would get PHP's text and no envelope. The error log still gets
	// every line (pg_error_record()); DEBUG keeps the display, whose output an
	// answer carries in _debug_output.
	if (!(defined('DEBUG') && DEBUG)) {

		@ini_set('display_errors', '0');

	}

	try {

		call_user_func($run);

	} catch (Throwable $thrown) {

		api_fault_throwable($thrown);

	}

}

// An uncaught Throwable: recorded as pg_error_uncaught() records it, then
// answered through the usual exit, so the log row, the idempotency record and
// the MCP hook all see a 500 like any other. Should that exit itself throw,
// the answer is written directly.
function api_fault_throwable($thrown) {

	api_fault_record($thrown);

	if (!api_fault_answering(true)) {

		try {

			if (defined('PG_MCP_ENTRY') && (api_send_hook() === null)) {

				pg_mcp_send(500, api_fault_mcp_body(api_request_id()));

			}

			api_fail(500, 'server_error', api_fault_message());

		} catch (Throwable $again) {

			api_fault_record($again);

		}

	}

	api_fault_write();

	exit(255);

}

// Shutdown: a fatal error that never became a Throwable. No exit here, which
// would stop the shutdown functions registered after this one.
function api_fault_shutdown() {

	if (!api_fault_can_answer(error_get_last(), headers_sent())) {

		return;

	}

	api_fault_write();

}

// Whether the fault is already being answered. A call with true marks it and
// returns what it was before.
function api_fault_answering($mark = false) {

	static $answering = false;

	$was = $answering;

	if ($mark) {

		$answering = true;

	}

	return $was;

}

// Writes the fault's answer without api_send(): what the shared code or PHP
// printed is dropped, the status and headers set when they still can be, then
// the request is logged. Written once per request.
function api_fault_write() {

	static $written = false;

	if ($written) {

		return;

	}

	$written = true;

	while (ob_get_level() > 0) {

		ob_end_clean();

	}

	$request_id = api_request_id();

	$body = defined('PG_MCP_ENTRY') ? api_fault_mcp_body($request_id) : api_fault_body($request_id, api_fault_message());

	// Once headers are out the client holds a status and part of a body; a
	// JSON text after it would only corrupt that. The request is still logged.
	if (!headers_sent()) {

		http_response_code(500);

		header('Content-Type: application/json; charset=utf-8');

		header('X-Request-Id: ' . $request_id);

		header('Cache-Control: no-store');

		echo (string)pg_json_encode_answer($body);

	}

	try {

		if (function_exists('api_idempotency_finish')) {

			api_idempotency_finish(500, $body);

		}

		api_log_request(500, 'server_error');

	} catch (Throwable $error) {

		api_fault_record($error);

	}

}

// One error log line for a Throwable, under the request's id.
function api_fault_record($thrown) {

	if (function_exists('pg_error_record_throwable')) {

		pg_error_record_throwable($thrown);

	}

}

// The error log line of a failed query (includes/api/db.php). Outside DEBUG
// the client gets only the generic sentence, so without this line the
// request id in its answer led to nothing that said what went wrong.
// $detail is what the database said: an operator's text, never a client's.
function api_fault_query_entry($detail) {

	$detail = trim((string)$detail);

	return pg_error_entry(
		'error',
		'Database',
		($detail === '') ? 'API: a query failed.' : 'API: a query failed: ' . $detail,
		'',
		0,
		array());

}

// Writes that line, under the request's id.
function api_fault_query_record($detail) {

	if (function_exists('pg_error_write') && function_exists('pg_error_entry')) {

		pg_error_write(api_fault_query_entry($detail));

	}

}
