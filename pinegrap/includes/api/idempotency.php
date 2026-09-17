<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Safe retries for writes.
//
// A marketplace whose connection drops after the request arrived but before the
// answer came back has no way to know whether the stock was adjusted, so it
// sends the same call again. Applied twice, "decrease by one" is wrong stock and
// nothing in the data says so afterwards. The client sends an Idempotency-Key,
// the first call stores its answer under that key, and the retry is answered
// from the store instead of being applied a second time.
//
// The same key sent with a different body is the other mistake - a client
// reusing one key for everything - and is refused rather than answered with the
// wrong cached response.

if (!defined('PG_API_ENTRY')) {
	exit;
}

// Returns the key when the request carries one, '' otherwise.
function api_idempotency_key() {

	$key = api_header('Idempotency-Key');

	$key = trim((string)$key);

	if ($key === '') {

		return '';

	}

	if (strlen($key) > 64) {

		api_fail(400, 'invalid_idempotency_key', lang('Idempotency-Key must be 64 characters or fewer.'));

	}

	return $key;

}

// Called before a write runs. Answers the stored response and exits when this
// exact request has already been carried out; returns quietly when it has not.
function api_idempotency_replay($app_id, $key, $body_hash) {

	if ($key === '') {

		return;

	}

	$row = api_row("SELECT request_hash, status_code, response
		FROM api_idempotency
		WHERE app_id = '" . (int)$app_id . "' AND idem_key = '" . escape($key) . "'
		LIMIT 1");

	if ($row === null) {

		return;

	}

	if ($row['request_hash'] !== $body_hash) {

		api_fail(409, 'idempotency_key_reused', lang('This Idempotency-Key was already used for a different request.'));

	}

	$stored = json_decode((string)$row['response'], true);

	if (!is_array($stored)) {

		// The stored answer is unreadable, which should not happen. Refusing is
		// safer than applying the write again behind the client's back.
		api_fail_server();

	}

	api_extra_headers('Idempotent-Replay', 'true');

	api_send((int)$row['status_code'], $stored);

}

// What this request is running under, set once the replay check has passed.
// api_send() reads it on the way out, which is why a handler never has to
// remember to record its own answer.
function api_idempotency_context($app_id = null, $key = null, $body_hash = null) {

	static $context = array('app_id' => 0, 'key' => '', 'hash' => '');

	if ($app_id !== null) {

		$context = array('app_id' => (int)$app_id, 'key' => (string)$key, 'hash' => (string)$body_hash);

	}

	return $context;

}

// Called by api_send() for any answer that is not an error. An error is not
// recorded on purpose: a call that failed has not been carried out, so the
// retry that follows should be allowed to actually run.
function api_idempotency_finish($status_code, $response) {

	if ($status_code >= 400) {

		return;

	}

	$context = api_idempotency_context();

	if ($context['key'] === '') {

		return;

	}

	api_idempotency_store($context['app_id'], $context['key'], $context['hash'], $status_code, $response);

}

// Called by api_idempotency_finish() once a write has succeeded, so the answer
// is on record before the client could possibly retry.
function api_idempotency_store($app_id, $key, $body_hash, $status_code, $response) {

	if ($key === '') {

		return;

	}

	// INSERT IGNORE rather than a check and an insert: two retries arriving at
	// the same moment would both find nothing and both insert, and the unique
	// key on (application, key) is what settles that race.
	api_exec("INSERT IGNORE INTO api_idempotency
		(app_id, idem_key, request_hash, status_code, response, created_timestamp)
		VALUES (
			'" . (int)$app_id . "',
			'" . escape($key) . "',
			'" . escape($body_hash) . "',
			'" . (int)$status_code . "',
			'" . escape(json_encode($response, JSON_UNESCAPED_UNICODE)) . "',
			UNIX_TIMESTAMP()
		)");

}

// The fingerprint a replay is compared against: the method, the path and the
// parsed input, so the same key with a different payload is caught.
function api_idempotency_hash($input) {

	ksort($input);

	return hash('sha256', api_request_method() . ' ' . api_request_path() . ' ' . json_encode($input));

}
