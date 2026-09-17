<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Telling an outside system that something happened here.
//
// Without this an integration has to ask "anything new?" on a timer, and the
// interval is a straight trade between how stale an order may be and how much
// pointless traffic the site carries. A webhook turns that into one call at the
// moment it matters.
//
// Nothing is delivered inside the request that caused it. An order that has just
// been paid for must not wait on somebody else's server - if their endpoint is
// slow, the customer watches a spinner; if it hangs, the checkout hangs. The
// event goes into a queue and the cron sends it.

if (!defined('PG_INIT_LOADED')) {
	exit;
}

require_once(dirname(__FILE__) . '/http.php');

// The events an application may subscribe to. A name that is not in here cannot
// be stored, so a subscription always means something.
function api_webhook_events() {

	return array(
		'order.created'        => 'A new order was placed',
		'order.status_changed' => 'An order moved to another status',
		'order.cancelled'      => 'An order was cancelled',
		'product.created'      => 'A new product was added',
		'product.updated'      => 'A product was changed',
		'inventory.changed'    => 'A product stock level changed',
		'customer.created'     => 'A new customer record was made',
		'page.updated'         => 'A page was changed',
		'product_group.updated' => 'A product group was changed',
		'file.created'         => 'A file was uploaded'
	);

}

function api_webhook_event_is_valid($event) {

	$events = api_webhook_events();

	return isset($events[$event]);

}

// Put an event on the queue for every webhook that asked for it.
//
// Called from wherever the thing actually happens - the checkout, the order
// screen, the API's own writes - and deliberately cheap: a lookup and an insert
// per subscriber, no network.
function api_webhook_enqueue($event, $payload) {

	if (!defined('DB_CONNECTED') || !api_webhook_event_is_valid($event)) {

		return 0;

	}

	// A site with no webhooks is the normal case, and it should cost one indexed
	// read and nothing else.
	$hooks = db_items("SELECT id, events FROM api_webhooks WHERE status = 'active'");

	if (!is_array($hooks) || empty($hooks)) {

		return 0;

	}

	$body = json_encode(array(
		'event'      => $event,
		'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
		'data'       => $payload
	), JSON_UNESCAPED_UNICODE);

	$queued = 0;

	foreach ($hooks as $hook) {

		$events = json_decode((string)$hook['events'], true);

		if (!is_array($events) || !in_array($event, $events, true)) {

			continue;

		}

		db("INSERT INTO api_webhook_queue
			(webhook_id, event, payload, attempts, next_attempt_at, created_timestamp)
			VALUES (
				'" . (int)$hook['id'] . "',
				'" . escape($event) . "',
				'" . escape($body) . "',
				'0',
				UNIX_TIMESTAMP(),
				UNIX_TIMESTAMP()
			)");

		$queued++;

	}

	return $queued;

}

// The signature a receiver checks.
//
// The timestamp is signed with the body rather than sent beside it, so a
// captured call cannot be replayed tomorrow with the clock moved on: the
// receiver refuses anything whose timestamp is not within a few minutes, and the
// timestamp cannot be edited without breaking the signature.
function api_webhook_signature($secret, $timestamp, $body) {

	return hash_hmac('sha256', $timestamp . '.' . $body, $secret);

}

// How long to wait before trying again. Six attempts over about a day and a
// half: long enough to ride out a receiver's maintenance window, short enough
// that a dead address stops being retried.
function api_webhook_backoff($attempts) {

	$schedule = array(60, 300, 1800, 7200, 21600, 86400);

	$index = (int)$attempts - 1;

	if ($index < 0) { $index = 0; }

	if ($index >= count($schedule)) {

		return 0;

	}

	return $schedule[$index];

}

function api_webhook_max_attempts() {

	return 6;

}

// Send one queued row. Answers true when it is done with - delivered, or given
// up on - and false when it is waiting for another attempt.
function api_webhook_deliver($row) {

	$hook = db_item("SELECT id, app_id, url, secret, status FROM api_webhooks
		WHERE id = '" . (int)$row['webhook_id'] . "' LIMIT 1");

	if (!$hook || $hook['status'] === 'disabled') {

		// The subscription is gone or switched off; the queued event has nowhere
		// to go and is dropped rather than retried forever.
		db("DELETE FROM api_webhook_queue WHERE id = '" . (int)$row['id'] . "'");

		return true;

	}

	$body = (string)$row['payload'];

	$timestamp = (string)time();

	$headers = array(
		'Content-Type: application/json',
		'X-Pinegrap-Event: ' . $row['event'],
		'X-Pinegrap-Delivery: ' . (int)$row['id'],
		'X-Pinegrap-Signature: t=' . $timestamp . ',v1=' . api_webhook_signature($hook['secret'], $timestamp, $body)
	);

	$outcome = api_http_post($hook['url'], $body, $headers, 10);

	$attempts = (int)$row['attempts'] + 1;

	if ($outcome['ok']) {

		db("DELETE FROM api_webhook_queue WHERE id = '" . (int)$row['id'] . "'");

		db("UPDATE api_webhooks SET status = 'active', last_success = UNIX_TIMESTAMP()
			WHERE id = '" . (int)$hook['id'] . "'");

		return true;

	}

	$error = ($outcome['error'] !== '') ? $outcome['error'] : ('HTTP ' . $outcome['status']);

	$retry_in = api_http_should_retry($outcome) ? api_webhook_backoff($attempts) : 0;

	$exhausted = ($retry_in === 0 || $attempts >= api_webhook_max_attempts());

	db("UPDATE api_webhook_queue SET
			attempts = '" . $attempts . "',
			last_status_code = '" . (int)$outcome['status'] . "',
			last_error = '" . escape(substr($error, 0, 500)) . "',
			next_attempt_at = '" . ($exhausted ? 0 : (time() + $retry_in)) . "'
		WHERE id = '" . (int)$row['id'] . "'");

	db("UPDATE api_webhooks SET last_failure = UNIX_TIMESTAMP()
		WHERE id = '" . (int)$hook['id'] . "'");

	if ($exhausted) {

		// The subscription is marked, not deleted: the operator has to see that
		// their address stopped answering, and silently dropping events is how
		// an integration is discovered to be broken a week later.
		db("UPDATE api_webhooks SET status = 'failing' WHERE id = '" . (int)$hook['id'] . "'");

		log_activity(lang(array(
			'string' => 'A webhook delivery to {var:1} was given up after {var:2} attempts: {var:3}',
			'vars'   => array($hook['url'], $attempts, $error)
		)), 'SYSTEM');

		return true;

	}

	return false;

}

// The cron's pass. Bounded by wall clock rather than row count: a run that
// starts is expected to end, and a hundred slow receivers would otherwise hold
// the job open past the next tick.
function api_webhook_dispatch($seconds = 20) {

	if (!defined('DB_CONNECTED')) {

		return array('sent' => 0, 'failed' => 0);

	}

	$deadline = microtime(true) + $seconds;

	$sent = 0;

	$failed = 0;

	while (microtime(true) < $deadline) {

		$row = db_item("SELECT id, webhook_id, event, payload, attempts
			FROM api_webhook_queue
			WHERE next_attempt_at > 0 AND next_attempt_at <= UNIX_TIMESTAMP()
			  AND attempts < '" . api_webhook_max_attempts() . "'
			ORDER BY next_attempt_at ASC, id ASC
			LIMIT 1");

		if (!$row) {

			break;

		}

		// Claimed before it is sent, so a second cron overlapping this one does
		// not pick up the same row and deliver it twice.
		db("UPDATE api_webhook_queue SET next_attempt_at = UNIX_TIMESTAMP() + 120
			WHERE id = '" . (int)$row['id'] . "'");

		if (api_webhook_deliver($row)) {

			$sent++;

		} else {

			$failed++;

		}

	}

	// Rows that ran out of attempts are kept for a week so the panel can show
	// what happened, then swept.
	db("DELETE FROM api_webhook_queue
		WHERE next_attempt_at = 0 AND created_timestamp < '" . (time() - 604800) . "'");

	return array('sent' => $sent, 'failed' => $failed);

}
