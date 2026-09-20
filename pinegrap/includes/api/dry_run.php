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
// Deliberately a header and not a parameter: it is true of every write, and a
// body field would have to be added to every route, kept out of every INSERT,
// and remembered by whoever writes the next endpoint.

if (!defined('PG_API_ENTRY')) {
	exit;
}

// Whether this request asked for a dry run.
//
// Read once. The strictness is on purpose: 'X-Dry-Run: maybe' is far more
// likely a mistake in the caller than a request to write, and the caller would
// only learn otherwise from the row that appeared.
function api_dry_run_requested() {

	static $requested = null;

	if ($requested !== null) {

		return $requested;

	}

	$raw = trim((string)api_header('X-Dry-Run'));

	if ($raw === '') {

		$requested = false;

		return $requested;

	}

	$value = strtolower($raw);

	if (in_array($value, array('1', 'true', 'yes', 'on'), true)) {

		$requested = true;

		return $requested;

	}

	if (in_array($value, array('0', 'false', 'no', 'off'), true)) {

		$requested = false;

		return $requested;

	}

	api_fail(400, 'invalid_dry_run', lang('X-Dry-Run must be true or false.'));

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
