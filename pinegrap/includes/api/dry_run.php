<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Asking what would happen, without it happening.
//
// Someone writing an integration has to find out whether their body is the
// shape this API wants, and today the only way is to send it for real. On a
// live catalogue that means a test product to delete afterwards, an order that
// was never placed, a price written to the shop for a second - and the ones
// that cannot be undone are exactly the ones worth checking first.
//
// X-Dry-Run: true runs everything up to the first write - authentication, the
// permission, the shape of the body, the fields the endpoint insists on, and
// whether the row it is about exists - and then answers instead of writing. So
// a refusal is a real refusal: the same 422 with the same field, produced by
// the same code that would have refused the real call. What it cannot tell you
// is what only the write itself would reveal, a unique key another request took
// in the meantime.
//
// A header, or ?dry_run=true in the query string, and never a body field: it is
// true of every write, and a body field would have to be added to every route,
// kept out of every INSERT, and remembered by whoever writes the next endpoint.
// The query string is read here, once, for every route alike, and it is there
// for the callers that cannot set a header - a webhook tester, a no-code tool,
// a link pasted into a terminal. Ignoring it, as an unknown query value is
// ignored, would hand exactly those callers the write they asked not to make.

if (!defined('PG_API_ENTRY')) {
	exit;
}

// Whether this request asked for a dry run, in the header or in the query
// string.
//
// Read once. The strictness is on purpose: 'X-Dry-Run: maybe' is far more
// likely a mistake in the caller than a request to write, and the caller would
// only learn otherwise from the row that appeared. Either one asking is
// enough: a caller who sent both and meant them differently learns it from an
// answer that says dry_run, not from a write it did not want.
function api_dry_run_requested() {

	static $requested = null;

	if ($requested !== null) {

		return $requested;

	}

	$header = api_dry_run_value(api_header('X-Dry-Run'));

	if ($header === null) {

		api_fail(400, 'invalid_dry_run', lang('X-Dry-Run must be true or false.'));

	}

	$query = api_dry_run_value(api_dry_run_query());

	if ($query === null) {

		api_fail(400, 'invalid_dry_run', lang('dry_run must be true or false.'), 'dry_run');

	}

	$requested = $header || $query;

	return $requested;

}

// What one way of asking says: true, false - also when it was not sent at
// all - or null for a value that is neither.
function api_dry_run_value($raw) {

	$value = strtolower(trim((string)$raw));

	if (in_array($value, array('1', 'true', 'yes', 'on'), true)) {

		return true;

	}

	if (in_array($value, array('', '0', 'false', 'no', 'off'), true)) {

		return false;

	}

	return null;

}

// dry_run as the query string carried it, '' when it did not.
//
// Taken from the request's input rather than from $_GET, so a call the MCP
// server runs in-process reads what that call was given (it hands dry_run over
// as the header) and not the query string of the request to mcp.php. A body
// field of that name is not this: the endpoint refuses it as a field it does
// not know, before anything is written.
function api_dry_run_query() {

	$input = api_request_input();

	if (!array_key_exists('dry_run', $input) || in_array('dry_run', api_request_body_keys(), true)) {

		return '';

	}

	// ?dry_run[]=true is neither true nor false.
	return is_scalar($input['dry_run']) ? (string)$input['dry_run'] : 'invalid';

}

// Called by a write handler at the line where it is about to write, and never
// before the checks: everything above the call is what a dry run verifies, and
// everything below it is what a dry run skips. A handler that calls this too
// early promises a validation it did not do.
//
// $would is the verb in the past participle the caller can branch on - created,
// updated, deleted, cancelled - and $resource names what it is about. $detail
// carries anything the handler already worked out and the caller would want to
// see: the identifier it would have written to, the number of rows a batch
// would have touched.
function api_dry_run_stop($would, $resource, $detail = array()) {

	if (!api_dry_run_requested()) {

		return;

	}

	$body = array(
		'dry_run'  => true,
		'would'    => (string)$would,
		'resource' => (string)$resource
	);

	if (!empty($detail)) {

		$body['detail'] = $detail;

	}

	// 200 and not 201: nothing was created, and a client that branches on the
	// status must not be told otherwise.
	api_send(200, $body);

}
