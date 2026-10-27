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

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
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

// The key as it is stored, for this caller.
//
// Keys are chosen by the client and stored per application. Every device signed
// in through a device application shares that application's id, so its key is
// kept under a name of its own: two people's phones that happened to pick the
// same key must never be answered with each other's stored response. Hashed
// rather than prefixed so that the stored value still fits the column whatever
// length the client chose.
function api_idempotency_scoped_key($app, $key) {

	if (($key === '') || !is_array($app) || empty($app['device']['id'])) {

		return $key;

	}

	return hash('sha256', 'device:' . (int)$app['device']['id'] . ':' . $key);

}

// How long a key reserved by a request that has not answered yet is believed,
// in seconds. A request killed hard (an out-of-memory abort, a recycled worker)
// never gives its key back; once this has passed, a retry takes the key over
// and runs. Sized past the longest a write should run.
function api_idempotency_pending_ttl() {

	return 300;

}

// What to do with a write that carries a key, given the row on file for that
// key (null when there is none), the fingerprint of this request and the time:
//
//   run          nothing on file: reserve the key and carry the write out
//   replay       the same request was carried out: answer with its record
//   reused       the key was used for a different request: refuse with 409
//   in_progress  the same request is being carried out right now by another
//                attempt: refuse with 409, the retry gets the record later
//   stale        a reservation whose request never answered: take it over
//
// A reservation is a row with status_code 0; no answer is ever recorded with 0.
function api_idempotency_decision($row, $body_hash, $now) {

	if (!is_array($row)) {

		return 'run';

	}

	if ((string)$row['request_hash'] !== (string)$body_hash) {

		return 'reused';

	}

	if ((int)$row['status_code'] === 0) {

		return (((int)$row['created_timestamp'] + api_idempotency_pending_ttl()) < (int)$now) ? 'stale' : 'in_progress';

	}

	return 'replay';

}

// Whether this request holds the reservation of its key: 'none' before one is
// taken, 'held' while the write runs, 'closed' once its answer was recorded or
// the reservation was given back.
function api_idempotency_reservation($set = null) {

	static $state = 'none';

	if ($set !== null) {

		$state = (string)$set;

	}

	return $state;

}

// Called before a write runs. Answers the stored response and exits when this
// exact request has already been carried out, refuses a key used for another
// request or a retry that arrived while the first attempt is still running,
// and otherwise reserves the key for this request and returns.
//
// The reservation is what makes the key work for retries that overlap. Read
// alone, the record only exists once the first attempt has finished, so a
// client that timed out and sent the call again while the first one was still
// running found nothing on file and applied the write a second time. The
// reservation row is inserted before the write; the unique key on
// (application, key) lets exactly one of several simultaneous attempts in.
function api_idempotency_replay($app_id, $key, $body_hash) {

	if ($key === '') {

		return;

	}

	// A few rounds at most: an insert can lose to an attempt that reserved the
	// key a moment earlier, and a stale reservation can be taken over by
	// somebody else first. Every round reads the row again.
	for ($round = 0; $round < 3; $round++) {

		$row = api_row("SELECT request_hash, status_code, response, created_timestamp
			FROM api_idempotency
			WHERE app_id = '" . (int)$app_id . "' AND idem_key = '" . escape($key) . "'
			LIMIT 1");

		$decision = api_idempotency_decision($row, $body_hash, time());

		if ($decision === 'reused') {

			api_fail(409, 'idempotency_key_reused', lang('This Idempotency-Key was already used for a different request.'));

		}

		if ($decision === 'in_progress') {

			api_extra_headers('Retry-After', '1');

			api_fail(409, 'idempotency_in_progress', lang('A request with this Idempotency-Key is still being carried out. Send it again in a moment to receive its answer.'));

		}

		if ($decision === 'replay') {

			$stored = json_decode((string)$row['response'], true);

			if (!is_array($stored)) {

				// The stored answer is unreadable, which should not happen.
				// Refusing is safer than applying the write again behind the
				// client's back.
				api_fail_server();

			}

			api_extra_headers('Idempotent-Replay', 'true');

			$context = api_idempotency_context();

			api_send((int)$row['status_code'], api_idempotency_restored($stored, $context['restore']));

		}

		if ($decision === 'run') {

			$taken = api_exec("INSERT IGNORE INTO api_idempotency
				(app_id, idem_key, request_hash, status_code, response, created_timestamp)
				VALUES (
					'" . (int)$app_id . "',
					'" . escape($key) . "',
					'" . escape($body_hash) . "',
					'0',
					NULL,
					UNIX_TIMESTAMP()
				)");

		} else {

			// stale: taken over only while it is still the row that was read,
			// so of two retries arriving together one gets it.
			$taken = api_exec("UPDATE api_idempotency
				SET created_timestamp = UNIX_TIMESTAMP()
				WHERE app_id = '" . (int)$app_id . "' AND idem_key = '" . escape($key) . "'
					AND status_code = '0' AND created_timestamp = '" . (int)$row['created_timestamp'] . "'
				LIMIT 1");

		}

		if ($taken === 1) {

			api_idempotency_reservation('held');

			// A request that ends without answering through api_send() - a
			// fatal error in the handler - gives the key back, so the retry
			// that follows runs rather than waiting out the reservation.
			register_shutdown_function('api_idempotency_release');

			return;

		}

	}

	api_extra_headers('Retry-After', '1');

	api_fail(409, 'idempotency_in_progress', lang('A request with this Idempotency-Key is still being carried out. Send it again in a moment to receive its answer.'));

}

// What this request is running under, set before the replay check. api_send()
// reads it on the way out, which is why a handler never has to remember to
// record its own answer. $redact is the route's 'idempotency_redact' list
// (api_idempotency_redacted()), $restore its 'idempotency_restore' function
// (api_idempotency_restored()).
function api_idempotency_context($app_id = null, $key = null, $body_hash = null, $redact = array(), $restore = '') {

	static $context = array('app_id' => 0, 'key' => '', 'hash' => '', 'redact' => array(), 'restore' => '');

	if ($app_id !== null) {

		$context = array('app_id' => (int)$app_id, 'key' => (string)$key, 'hash' => (string)$body_hash, 'redact' => array_values((array)$redact), 'restore' => is_string($restore) ? $restore : '');

	}

	return $context;

}

// The answer as it is recorded under its key: each top-level field the route
// names in 'idempotency_redact' is null. A secret answered once, such as a
// share link's address, would otherwise sit readable in api_idempotency for
// the day the row lives (api_maintenance_purge()), for anyone who can read
// the database and without the site's key. A retry answered from the record
// gets that field again only where the route's 'idempotency_restore'
// function reads it back (api_idempotency_restored()), null otherwise.
function api_idempotency_redacted($response, $keys) {

	if (!is_array($response)) {

		return $response;

	}

	foreach ((array)$keys as $name) {

		if (is_string($name) && array_key_exists($name, $response)) {

			$response[$name] = null;

		}

	}

	return $response;

}

// The recorded answer as a replay sends it. A route that keeps a field out of
// the record ('idempotency_redact') may name a function
// ('idempotency_restore') that reads it back from where it is kept sealed,
// such as a share link's address from its row, so the retry gets the first
// answer, which the Idempotency-Key header promises. The function is handed
// the record and returns it; an answer that is not an object leaves the
// record as it is. It runs before the handler, so it makes the route's own
// checks again.
function api_idempotency_restored($stored, $restore) {

	if (!is_string($restore) || ($restore === '') || !function_exists($restore)) {

		return $stored;

	}

	$restored = call_user_func($restore, $stored);

	return is_array($restored) ? $restored : $stored;

}

// Called by api_send() for every answer. Only a request that holds the
// reservation of its key acts here: a success records its answer on the
// reservation, an error gives the key back. An error is not recorded on
// purpose: a call that failed has not been carried out, so the retry that
// follows should be allowed to actually run.
function api_idempotency_finish($status_code, $response) {

	if (api_idempotency_reservation() !== 'held') {

		return;

	}

	// Closed before the statement runs: a failing statement answers through
	// api_send() as well, which must not come back here.
	api_idempotency_reservation('closed');

	$context = api_idempotency_context();

	if ($context['key'] === '') {

		return;

	}

	if ($status_code >= 400) {

		api_idempotency_forget($context['app_id'], $context['key']);

		return;

	}

	api_idempotency_store($context['app_id'], $context['key'], $context['hash'], $status_code, api_idempotency_redacted($response, $context['redact']));

}

// Called by api_idempotency_finish() once a write has succeeded, so the answer
// is on record before the client could possibly retry. Written onto the
// reservation this request inserted.
function api_idempotency_store($app_id, $key, $body_hash, $status_code, $response) {

	if ($key === '') {

		return;

	}

	// Encoded the way the answer itself was (api_json_encode()), so a stored
	// record is never an empty string the replay cannot read back. An answer
	// that cannot be encoded was already turned into a 500 by api_send(); the
	// key is given back rather than kept with nothing to replay.
	$json = api_json_encode($response);

	if ($json === null) {

		api_idempotency_forget($app_id, $key);

		return;

	}

	api_exec("UPDATE api_idempotency SET
			status_code = '" . (int)$status_code . "',
			response = '" . escape($json) . "'
		WHERE app_id = '" . (int)$app_id . "' AND idem_key = '" . escape($key) . "'
			AND request_hash = '" . escape($body_hash) . "' AND status_code = '0'
		LIMIT 1");

}

// Gives a reservation back. Only a row still waiting for its answer is removed.
function api_idempotency_forget($app_id, $key) {

	api_exec("DELETE FROM api_idempotency
		WHERE app_id = '" . (int)$app_id . "' AND idem_key = '" . escape($key) . "'
			AND status_code = '0'
		LIMIT 1");

}

// Shutdown: a request that took the reservation and never answered through
// api_send() gives it back.
function api_idempotency_release() {

	if (api_idempotency_reservation() !== 'held') {

		return;

	}

	api_idempotency_reservation('closed');

	$context = api_idempotency_context();

	if (($context['key'] === '') || !isset(db::$con) || !db::$con) {

		return;

	}

	db_q("DELETE FROM api_idempotency
		WHERE app_id = ? AND idem_key = ?
			AND status_code = '0'
		LIMIT 1", array((string)(int)$context['app_id'], (string)$context['key']));

}

// The input a write's fingerprint is taken over. A file sent as a form part
// ('form_file' on the route) is not among the parsed fields, so without it two
// uploads of different files under the same name and text read as one request
// and the second is answered with the first one's message. The part's name,
// size and a SHA-256 of its bytes stand in for it, the way content_base64 is
// part of a JSON body. A route run by the MCP endpoint has no form of its own.
function api_idempotency_input($route, $params) {

	$params = api_idempotency_secret_input($route, $params, defined('ENCRYPTION_KEY') ? (string)ENCRYPTION_KEY : '');

	$field = isset($route['form_file']) ? (string)$route['form_file'] : '';

	if (($field === '') || (api_send_hook() !== null) || !isset($_FILES[$field]) || !is_array($_FILES[$field])) {

		return $params;

	}

	$part = $_FILES[$field];

	$temporary = (isset($part['tmp_name']) && is_string($part['tmp_name'])) ? $part['tmp_name'] : '';

	$params[$field] = array(
		'name'   => (isset($part['name']) && is_string($part['name'])) ? $part['name'] : '',
		'size'   => (isset($part['size']) && !is_array($part['size'])) ? (int)$part['size'] : 0,
		'sha256' => (($temporary !== '') && is_uploaded_file($temporary)) ? (string)@hash_file('sha256', $temporary) : ''
	);

	return $params;

}

// A field the route marks 'secret' (a password) enters the fingerprint as an
// HMAC keyed with the site's key instead of as sent. The fingerprint is kept
// beside the key for a day, and a plain SHA-256 over a short password and a
// few fields with a handful of possible values is a fast offline guess, next
// to the slow password_hash() the password itself is kept by. Two different
// values still give two fingerprints, so the key sent again with another
// password is still refused as reused.
function api_idempotency_secret_input($route, $params, $key) {

	$declared = (isset($route['params']) && is_array($route['params'])) ? $route['params'] : array();

	foreach ($declared as $param) {

		if (empty($param['secret']) || !isset($param['name']) || !array_key_exists($param['name'], $params)) {

			continue;

		}

		$value = $params[$param['name']];

		$params[$param['name']] = 'hmac:' . hash_hmac('sha256', is_string($value) ? $value : api_idempotency_input_text(array($value)), (string)$key);

	}

	return $params;

}

// The fingerprint a replay is compared against: the method, the path and the
// parsed input, so the same key with a different payload is caught.
function api_idempotency_hash($input) {

	ksort($input);

	return hash('sha256', api_request_method() . ' ' . api_request_path() . ' ' . api_idempotency_input_text($input));

}

// The parsed input as the text its fingerprint is taken over. JSON, as it has
// always been, so a key recorded before keeps matching its retry. A value that
// is not valid UTF-8 (a form field from a client encoding Windows-1254) made
// json_encode() return false, and every such body hashed as "METHOD PATH " -
// the same key sent with another body was answered from the first one's record
// instead of 409. That input is written with serialize(), which keeps every
// byte; substituting the broken bytes would make two different bodies alike.
function api_idempotency_input_text($input) {

	$json = json_encode($input);

	return is_string($json) ? $json : 'php:' . serialize($input);

}
