<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// An application managing its own event subscriptions.
//
// The integrator registers the address, so they do not have to ask an operator
// to paste it into a screen - and, more to the point, they can re-register it
// themselves when their endpoint moves.
//
// An application only ever sees and changes its own subscriptions. The stored
// signing secret is returned exactly once, when the subscription is made, for
// the same reason an application's own secret is: the receiver needs it to
// verify what arrives, and nothing else does.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(dirname(dirname(__FILE__)) . '/outbound/webhooks.php');

function api_webhook_present($row) {

	// What the queue still holds for this subscription, and why the last
	// attempt did not get through. last_failure alone is a timestamp; the
	// reason lives on the queue row, and an integrator whose receiver stopped
	// answering has no other way to read it than through this list.
	//
	// The dispatcher deletes a row the moment it is delivered, so the newest
	// row with an attempt on it is the newest failure still on file - a
	// subscription that is delivering cleanly has none, and reports null.
	// pending counts rows that are waiting or scheduled for a retry; a row
	// that has used up its attempts has next_attempt_at = 0 and is not pending.
	$id = (int)$row['id'];

	$queue = api_row("SELECT
			(SELECT COUNT(*) FROM api_webhook_queue
				WHERE webhook_id = '" . $id . "' AND next_attempt_at > 0) AS pending,
			(SELECT last_error FROM api_webhook_queue
				WHERE webhook_id = '" . $id . "' AND attempts > 0 ORDER BY id DESC LIMIT 1) AS last_error,
			(SELECT last_status_code FROM api_webhook_queue
				WHERE webhook_id = '" . $id . "' AND attempts > 0 ORDER BY id DESC LIMIT 1) AS last_status_code");

	$attempted = ($queue !== null && $queue['last_error'] !== null);

	return array(
		'id'         => $id,
		'url'        => $row['url'],
		'events'     => json_decode((string)$row['events'], true),
		'status'     => $row['status'],
		'last_success' => api_time($row['last_success']),
		'last_failure' => api_time($row['last_failure']),
		'pending'          => ($queue !== null) ? (int)$queue['pending'] : 0,
		'last_status_code' => $attempted ? (int)$queue['last_status_code'] : null,
		'last_error'       => $attempted ? (string)$queue['last_error'] : null,
		'channel_group_id' => (isset($row['channel_group_id']) && (int)$row['channel_group_id'] > 0) ? (int)$row['channel_group_id'] : null,
		'created_at'   => api_time($row['created_timestamp'])
	);

}

// The columns a subscription is read with. channel_group_id only once the
// 2026.4.10 upgrade has added it; before that every subscription is a
// whole-site one and reports null.
function api_webhook_columns() {

	return "id, url, events, status, last_success, last_failure, created_timestamp"
		. (api_webhook_channel_groups_ready() ? ", channel_group_id" : "");

}

// The group of workspace channels a subscription is narrowed to, checked
// against the events it will listen to. 0 - sent as 0, null or an empty
// value - is no group: the whole site, as every subscription was before.
//
// The workspace says whether the group is one the application's owner sees
// and whether every event is one of its own (ws_webhook_group_refused()). A
// site with the workspace switched off has no group to name.
function api_webhook_clean_channel_group($group_id, $events) {

	$group_id = (int)$group_id;

	if ($group_id <= 0) {

		return 0;

	}

	if (!api_webhook_channel_groups_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));

	}

	$app = api_current_app();

	$refused = function_exists('ws_webhook_group_refused')
		? ws_webhook_group_refused((array)($app['owner'] ?? array()), $group_id, $events)
		: array('error' => lang('That group could not be found.'), 'field' => 'channel_group_id');

	if ($refused !== null) {

		api_fail_validation($refused['error'], $refused['field']);

	}

	return $group_id;

}

function api_webhooks_list($params) {

	$app = api_current_app();

	$rows = api_rows("SELECT " . api_webhook_columns() . "
		FROM api_webhooks
		WHERE app_id = '" . (int)$app['id'] . "'
		ORDER BY id ASC");

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_webhook_present($row);

	}

	api_ok(array('data' => $out, 'events' => array_keys(api_webhook_events())));

}

function api_webhooks_create($params) {

	$app = api_current_app();

	// The address is checked before it is stored, not at delivery time: telling
	// the integrator now that their address is unreachable, or inside this
	// network, is worth more than a queue that quietly fails later.
	$check = api_http_check_url($params['url']);

	if (!$check['ok']) {

		api_fail_validation($check['error'], 'url');

	}

	$clean = api_webhook_clean_events($params['events']);

	$group_id = api_webhook_clean_channel_group(isset($params['channel_group_id']) ? $params['channel_group_id'] : 0, $clean);

	if ((int)api_value("SELECT COUNT(*) FROM api_webhooks WHERE app_id = '" . (int)$app['id'] . "'") >= 10) {

		api_fail(409, 'too_many_webhooks', lang('An application may hold ten subscriptions.'));

	}

	// Above: the address answers, every event named exists, the group (if
	// one is named) is there for those events, and the application is under
	// its ten subscriptions. Below: the row, and the one and only time its
	// secret is readable.
	api_dry_run_stop('created', 'webhook', array(
		'url'              => $params['url'],
		'events'           => $clean,
		'channel_group_id' => ($group_id > 0) ? $group_id : null
	));

	$secret = api_generate_secret();

	$narrowed = ($group_id > 0);

	api_exec("INSERT INTO api_webhooks (app_id, url, events, secret, status, created_timestamp"
		. ($narrowed ? ", channel_group_id" : "") . ")
		VALUES (
			'" . (int)$app['id'] . "',
			'" . escape($params['url']) . "',
			'" . escape(json_encode($clean)) . "',
			'" . escape($secret) . "',
			'active',
			UNIX_TIMESTAMP()"
			. ($narrowed ? ", '" . $group_id . "'" : "") . "
		)");

	$id = api_insert_id();

	$row = api_row("SELECT " . api_webhook_columns() . "
		FROM api_webhooks WHERE id = '" . $id . "' LIMIT 1");

	$out = api_webhook_present($row);

	// Once, here, and never again from a read.
	$out['secret'] = $secret;

	$out['signature_header'] = 'X-Pinegrap-Signature: t=<unix>,v1=HMAC_SHA256(t + "." + body, secret)';

	api_ok($out, 201);

}

function api_webhooks_delete($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$row = api_row("SELECT id FROM api_webhooks
		WHERE id = '" . $id . "' AND app_id = '" . (int)$app['id'] . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Webhook'));

	}

	api_dry_run_stop('deleted', 'webhook', array('id' => $id));

	api_exec("DELETE FROM api_webhooks WHERE id = '" . $id . "'");

	api_exec("DELETE FROM api_webhook_queue WHERE webhook_id = '" . $id . "'");

	api_send(204, array());

}

// One of this application's subscriptions, or a 404. A subscription that
// belongs to another application is answered exactly like one that does not
// exist, so an id cannot be used to learn what other integrations listen to.
function api_webhook_owned($id) {

	$app = api_current_app();

	$row = api_row("SELECT " . api_webhook_columns() . "
		FROM api_webhooks
		WHERE id = '" . (int)$id . "' AND app_id = '" . (int)$app['id'] . "'
		LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Webhook'));

	}

	return $row;

}

// The event list a create or an update was sent, checked and de-duplicated.
function api_webhook_clean_events($events) {

	if (!is_array($events)) {

		$events = array($events);

	}

	$clean = array();

	foreach ($events as $event) {

		$event = trim((string)$event);

		if (!api_webhook_event_is_valid($event)) {

			api_fail_validation(lang(array('string' => 'Unknown event: {var:1}', 'vars' => $event)), 'events');

		}

		if (!in_array($event, $clean, true)) { $clean[] = $event; }

	}

	if (empty($clean)) {

		api_fail_validation(lang('At least one event is required.'), 'events');

	}

	return $clean;

}

function api_webhooks_get($params) {

	api_ok(api_webhook_present(api_webhook_owned($params['id'])));

}

// Moving the address, changing what is listened to, or switching deliveries
// off and on again - the three things an integrator otherwise had to do by
// deleting the subscription and making a new one, which also threw away the
// signing secret the receiver was configured with.
//
// 'failing' is not something a caller sets. It is what the dispatcher writes
// when an address stops answering, and sending active is how the integrator
// says it answers again; what is still waiting is sent by /retry, not here,
// because a new address should not receive events that were meant for the old
// one unless somebody asks for that.
function api_webhooks_update($params) {

	$app = api_current_app();

	$row = api_webhook_owned($params['id']);

	$id = (int)$row['id'];

	$set = array();

	if (array_key_exists('url', $params)) {

		$check = api_http_check_url($params['url']);

		if (!$check['ok']) {

			api_fail_validation($check['error'], 'url');

		}

		$set[] = "url = '" . escape($params['url']) . "'";

	}

	$events = null;

	if (array_key_exists('events', $params)) {

		$events = api_webhook_clean_events($params['events']);

		$set[] = "events = '" . escape(json_encode($events)) . "'";

	}

	// The group and the events are checked together, whichever of them
	// changes: a narrowed subscription may listen only to workspace events.
	// Switching one back on (active) asks again whether its group is still
	// there - a group taken away switches its subscriptions off, and turning
	// one on would otherwise leave it hearing nothing without a word.
	$group_sent = array_key_exists('channel_group_id', $params);

	$group_id = $group_sent ? (int)$params['channel_group_id'] : (isset($row['channel_group_id']) ? (int)$row['channel_group_id'] : 0);

	if ($group_sent || ($group_id > 0 && ($events !== null || (array_key_exists('status', $params) && $params['status'] === 'active')))) {

		$listened = ($events !== null) ? $events : json_decode((string)$row['events'], true);

		$group_id = api_webhook_clean_channel_group($group_id, is_array($listened) ? $listened : array());

	}

	if ($group_sent) {

		if (!api_webhook_channel_groups_ready()) {

			api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));

		}

		$set[] = "channel_group_id = '" . $group_id . "'";

	}

	if (array_key_exists('status', $params)) {

		$set[] = "status = '" . escape($params['status']) . "'";

	}

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'webhook', array('id' => $id, 'fields' => count($set)));

	api_exec("UPDATE api_webhooks SET " . implode(', ', $set) . " WHERE id = '" . $id . "' LIMIT 1");

	log_activity(lang(array(
		'string' => 'API event subscription ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($row['url'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(api_webhook_present(api_webhook_owned($id)));

}

// Sending again what is waiting. The same thing the Retry button on the
// application's Events tab does: the subscription goes back to active and
// every queued row gets its whole ladder of attempts back. A row that ran out
// of attempts is invisible to the dispatcher, so leaving the count where it
// was would let it try once more and give up.
function api_webhooks_retry($params) {

	$row = api_webhook_owned($params['id']);

	$id = (int)$row['id'];

	$waiting = (int)api_value("SELECT COUNT(*) FROM api_webhook_queue WHERE webhook_id = '" . $id . "'");

	api_dry_run_stop('retried', 'webhook', array('id' => $id, 'requeued' => $waiting));

	api_exec("UPDATE api_webhooks SET status = 'active' WHERE id = '" . $id . "' LIMIT 1");

	api_exec("UPDATE api_webhook_queue
		SET attempts = '0', next_attempt_at = UNIX_TIMESTAMP(), last_error = ''
		WHERE webhook_id = '" . $id . "'");

	$out = api_webhook_present(api_webhook_owned($id));

	$out['requeued'] = $waiting;

	api_ok($out);

}

// A delivery the receiver can be tested against without waiting for an order.
//
// It goes on the queue for this one subscription and is sent by the scheduled
// task like any other event, signed the same way, so what the receiver sees is
// exactly what a real event will look like. It is not sent from this request:
// a slow receiver would hold the call open, and a delivery made here would
// skip the retry ladder the real ones go through.
function api_webhooks_test($params) {

	$row = api_webhook_owned($params['id']);

	$id = (int)$row['id'];

	// The dispatcher drops whatever is queued for a switched-off subscription,
	// so queueing a test for one would report a delivery that is never
	// made.
	if ($row['status'] === 'disabled') {

		api_fail(409, 'webhook_disabled', lang('Deliveries to this subscription are switched off. Set its status to active first.'));

	}

	api_dry_run_stop('queued', 'webhook_test', array('id' => $id));

	$body = json_encode(array(
		'event'      => 'webhook.test',
		'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
		'data'       => array('webhook_id' => $id)
	), JSON_UNESCAPED_UNICODE);

	api_exec("INSERT INTO api_webhook_queue
		(webhook_id, event, payload, attempts, next_attempt_at, created_timestamp)
		VALUES (
			'" . $id . "',
			'webhook.test',
			'" . escape($body) . "',
			'0',
			UNIX_TIMESTAMP(),
			UNIX_TIMESTAMP()
		)");

	api_ok(array(
		'webhook_id'  => $id,
		'delivery_id' => (int)api_insert_id(),
		'event'       => 'webhook.test',
		'queued'      => true
	));

}

// A new signing secret, returned once, here. The old one stops verifying the
// moment this answers: deliveries are signed when they are sent, not when they
// are queued, so anything still waiting goes out under the new secret too.
function api_webhooks_rotate_secret($params) {

	$app = api_current_app();

	$row = api_webhook_owned($params['id']);

	$id = (int)$row['id'];

	api_dry_run_stop('rotated', 'webhook_secret', array('id' => $id));

	$secret = api_generate_secret();

	api_exec("UPDATE api_webhooks SET secret = '" . escape($secret) . "' WHERE id = '" . $id . "' LIMIT 1");

	log_activity(lang(array(
		'string' => 'The signing secret of API event subscription ({var:1}) was renewed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($row['url'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	$out = api_webhook_present(api_webhook_owned($id));

	$out['secret'] = $secret;

	$out['signature_header'] = 'X-Pinegrap-Signature: t=<unix>,v1=HMAC_SHA256(t + "." + body, secret)';

	api_ok($out);

}

// What api_webhook_present() returns, declared for the OpenAPI document. The
// secret is in the answer to the calls that create a subscription or renew its
// secret and nowhere else, which is why it is optional here; requeued is only
// in the answer to /retry.
function api_webhook_schema() {

	return array(
		'id'               => 'integer',
		'url'              => 'string',
		'events'           => 'string[]',
		'status'           => 'string',
		'last_success'     => 'string?',
		'last_failure'     => 'string?',
		'pending'          => 'integer',
		'last_status_code' => 'integer?',
		'last_error'       => 'string?',
		'channel_group_id' => 'integer?',
		'created_at'       => 'string?',
		'secret'           => 'string',
		'signature_header' => 'string',
		'requeued'         => 'integer'
	);

}
