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
require_once(dirname(dirname(__FILE__)) . '/outbound/webhook_payloads.php');

function api_webhook_present($row) {

	// What the queue still holds for this subscription, and why the last
	// attempt did not get through. last_failure alone is a timestamp; the
	// reason lives on the queue row, and an integrator whose receiver stopped
	// answering has no other way to read it than through this list.
	//
	// The dispatcher deletes a row the moment it is delivered, so the newest
	// row with an attempt on it is the newest failure still on file - a
	// subscription that is delivering cleanly has none, and reports null.
	// pending counts rows that are waiting or scheduled for a retry, and the
	// ones held for a failing subscription (attempts 0, next_attempt_at 0,
	// api_webhook_queue_hold()) until it is retried; a row that has used up
	// its attempts has next_attempt_at = 0 and is not pending.
	$id = (int)$row['id'];

	$queue = api_row("SELECT
			(SELECT COUNT(*) FROM api_webhook_queue
				WHERE webhook_id = '" . $id . "' AND (next_attempt_at > 0 OR attempts = 0)) AS pending,
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

// Every event that can be subscribed to, with what it carries: for an
// application's own event chooser. GET /webhooks keeps its list of names.
function api_webhooks_events($params) {

	api_ok(array('data' => api_webhook_event_rows(api_webhook_events(), api_webhook_event_modules(), api_webhook_event_payloads())));

}

// The module of each event a module adds, by name. The flows and the incoming
// mail merged on their own with the Workspace off are still the Workspace's
// events.
function api_webhook_event_modules() {

	api_modules_load();

	$core = api_webhook_core_events();

	$names = array('flows' => 'workspace', 'mail' => 'workspace');

	$out = array();

	foreach (api_modules() as $key => $module) {

		$declare = $module['prefix'] . 'webhook_events';

		if (!$module['enabled'] || !function_exists($declare)) {

			continue;

		}

		foreach ((array)call_user_func($declare) as $name => $description) {

			if (!isset($core[$name]) && !isset($out[$name])) {

				$out[$name] = isset($names[$key]) ? $names[$key] : (string)$key;

			}

		}

	}

	return $out;

}

function api_webhooks_create($params) {

	$app = api_current_app();

	// The checks a subscription is made with, the same for the panel's "New
	// subscription" form (api_webhook_create_check()).
	$checked = api_webhook_create_check($params['url'], $params['events'], api_webhook_held_count($app['id']));

	if ($checked['refusal'] !== null) {

		api_webhook_fail($checked['refusal']);

	}

	$clean = $checked['events'];

	// A group of workspace channels is named only through the API; the panel
	// form makes whole-site subscriptions.
	$group_id = api_webhook_clean_channel_group(isset($params['channel_group_id']) ? $params['channel_group_id'] : 0, $clean);

	// Above: the address answers, every event named exists, the application is
	// under its subscription limit, and the group (if one is named) is there
	// for those events. Below: the row, and the one and only time its secret
	// is readable.
	api_dry_run_stop('created', 'webhook', array(
		'url'              => $checked['url'],
		'events'           => $clean,
		'channel_group_id' => ($group_id > 0) ? $group_id : null
	));

	$made = api_webhook_store($app['id'], $checked['url'], $clean, $group_id);

	if ($made === null) {

		api_fail_server();

	}

	$row = api_row("SELECT " . api_webhook_columns() . "
		FROM api_webhooks WHERE id = '" . (int)$made['id'] . "' LIMIT 1");

	$out = api_webhook_present($row);

	// Once, here, and never again from a read.
	$out['secret'] = $made['secret'];

	$out['signature_header'] = 'X-Pinegrap-Signature: t=<unix>,v1=HMAC_SHA256(t + "." + body, secret)';

	api_ok($out, 201);

}

// What the body of POST /webhooks may hold, as its route row declares it
// (max_length of url, max_items of events). The panel form is held to the
// same, so it cannot store what the route refuses; a test keeps the two equal.
function api_webhook_create_bounds() {

	return array('url' => 500, 'events' => 20);

}

// How many subscriptions an application holds, every status counted.
function api_webhook_held_count($app_id) {

	return (int)db_value("SELECT COUNT(*) FROM api_webhooks WHERE app_id = '" . (int)$app_id . "'");

}

// One refusal: the status and code the route answers, the field it names
// (null for none) and the message.
function api_webhook_refusal($status, $code, $field, $message) {

	return array('status' => (int)$status, 'code' => (string)$code, 'field' => $field, 'message' => (string)$message);

}

// The route's answer to a refusal of api_webhook_create_check() or
// api_webhook_test_refusal().
function api_webhook_fail($refusal) {

	api_fail($refusal['status'], $refusal['code'], $refusal['message'], $refusal['field']);

}

// The checks a new subscription is made with, for both callers: POST
// /webhooks and the panel's "New subscription" form (api_settings.php). In
// the route's order: the address (present, within its length, then tried by
// $check_url, api_http_check_url() unless a test passes another), the events
// (within their count, each in the catalogue, at least one) and the
// application's limit ($held: the subscriptions it holds now).
//
// Answers refusal (null when every check passed), the trimmed url and the
// events de-duplicated.
function api_webhook_create_check($url, $events, $held, $check_url = 'api_http_check_url') {

	$bounds = api_webhook_create_bounds();

	$url = trim(is_scalar($url) ? (string)$url : '');

	$out = array('refusal' => null, 'url' => $url, 'events' => array());

	if ($url === '') {

		$out['refusal'] = api_webhook_refusal(422, 'validation_failed', 'url', lang(array('string' => '{var:1} is required.', 'vars' => 'url')));

		return $out;

	}

	if (mb_strlen($url) > $bounds['url']) {

		$out['refusal'] = api_webhook_refusal(422, 'validation_failed', 'url', lang(array(
			'string' => '{var:1} is longer than the {var:2} characters accepted.',
			'vars'   => array('url', $bounds['url'])
		)));

		return $out;

	}

	if (!is_array($events)) {

		$events = ($events === null || $events === '') ? array() : array($events);

	}

	if (count($events) > $bounds['events']) {

		$out['refusal'] = api_webhook_refusal(422, 'validation_failed', 'events', lang(array(
			'string' => '{var:1} holds more than the {var:2} items accepted in one request.',
			'vars'   => array('events', $bounds['events'])
		)));

		return $out;

	}

	// The address is checked before it is stored, not at delivery time: telling
	// the integrator now that their address is unreachable, or inside this
	// network, is worth more than a queue that quietly fails later.
	$check = call_user_func($check_url, $url);

	if (empty($check['ok'])) {

		$out['refusal'] = api_webhook_refusal(422, 'validation_failed', 'url', lang($check['error']));

		return $out;

	}

	$cleaned = api_webhook_events_refusal($events);

	if ($cleaned['message'] !== '') {

		$out['refusal'] = api_webhook_refusal(422, 'validation_failed', 'events', $cleaned['message']);

		return $out;

	}

	$out['events'] = $cleaned['events'];

	if (api_webhook_subscription_limit_reached($held)) {

		$out['refusal'] = api_webhook_refusal(409, 'too_many_webhooks', null, lang(array(
			'string' => 'An application may hold {var:1} subscriptions.',
			'vars'   => api_webhook_subscription_limit()
		)));

	}

	return $out;

}

// The row of a new subscription, active, with its signing secret: answers
// its id and the plain secret (readable only now), or null when the INSERT
// failed. Called after api_webhook_create_check() passed.
function api_webhook_store($app_id, $url, $events, $group_id = 0) {

	$secret = api_generate_secret();

	$narrowed = ((int)$group_id > 0);

	$done = db("INSERT INTO api_webhooks (app_id, url, events, secret, status, created_timestamp"
		. ($narrowed ? ", channel_group_id" : "") . ")
		VALUES (
			'" . (int)$app_id . "',
			'" . escape($url) . "',
			'" . escape(json_encode(array_values($events))) . "',
			'" . escape($secret) . "',
			'active',
			UNIX_TIMESTAMP()"
			. ($narrowed ? ", '" . (int)$group_id . "'" : "") . "
		)");

	$id = $done ? (int)mysqli_insert_id(db::$con) : 0;

	return ($id > 0) ? array('id' => $id, 'secret' => $secret) : null;

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

	$cleaned = api_webhook_events_refusal($events);

	if ($cleaned['message'] !== '') {

		api_fail_validation($cleaned['message'], 'events');

	}

	return $cleaned['events'];

}

// The same check, answered rather than sent: events (the list cleaned) and
// message ('' when every name is in the catalogue and there is at least one).
function api_webhook_events_refusal($events) {

	if (!is_array($events)) {

		$events = array($events);

	}

	$clean = array();

	foreach ($events as $event) {

		$event = trim(is_scalar($event) ? (string)$event : '');

		if (!api_webhook_event_is_valid($event)) {

			return array('events' => array(), 'message' => lang(array('string' => 'Unknown event: {var:1}', 'vars' => $event)));

		}

		if (!in_array($event, $clean, true)) { $clean[] = $event; }

	}

	if (empty($clean)) {

		return array('events' => array(), 'message' => lang('At least one event is required.'));

	}

	return array('events' => $clean, 'message' => '');

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

			api_fail_validation(lang($check['error']), 'url');

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
		'vars'   => array(pg_ops_alert_mask_url($row['url']), $app['name'], $app['api_key'])
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

	// With event, that event's sample, one of the subscription's own events:
	// queued for this subscription alone and never announced, so no flow,
	// channel or other subscriber hears it.
	$event = isset($params['event']) ? trim((string)$params['event']) : '';

	$payloads = api_webhook_event_payloads();

	$refusal = api_webhook_test_refusal($row, $event, $payloads);

	if ($refusal !== null) {

		api_webhook_fail($refusal);

	}

	$sample = ($event !== '') ? $payloads[$event]['sample'] : null;

	api_dry_run_stop('queued', 'webhook_test', array('id' => $id, 'event' => ($sample !== null) ? $event : 'webhook.test'));

	$queued = api_webhook_test_queue($id, $event, $sample);

	if ($queued === null) {

		api_fail_server();

	}

	api_ok(array(
		'webhook_id'  => $id,
		'delivery_id' => $queued['id'],
		'event'       => $queued['event'],
		'queued'      => true
	));

}

// What refuses a test delivery to a subscription, or null: for POST
// /webhooks/{id}/test and the panel's "Send a sample". $row is the
// subscription (status, events as stored); $event '' asks for webhook.test.
function api_webhook_test_refusal($row, $event, $payloads) {

	// The dispatcher drops whatever is queued for a switched-off subscription,
	// so queueing a test for one would report a delivery that is never
	// made.
	if ($row['status'] === 'disabled') {

		return api_webhook_refusal(409, 'webhook_disabled', null, lang('Deliveries to this subscription are switched off. Set its status to active first.'));

	}

	if ((string)$event === '') {

		return null;

	}

	$refused = api_webhook_sample_refusal($event, (array)json_decode((string)$row['events'], true), $payloads);

	if ($refused === 'not_listened') {

		return api_webhook_refusal(422, 'validation_failed', 'event', lang(array('string' => 'This subscription does not listen to {var:1}.', 'vars' => $event)));

	}

	if ($refused === 'no_sample') {

		return api_webhook_refusal(422, 'validation_failed', 'event', lang(array('string' => 'There is no sample of {var:1} yet. Send the test without an event.', 'vars' => $event)));

	}

	return null;

}

// A test delivery on the queue for one subscription: the event's sample
// under its own name when $sample is given, else webhook.test. Sent by the
// scheduled task like any other event, signed the same way, so the receiver
// sees exactly what a real event looks like. Answers the delivery id and the
// event's name, or null when the INSERT failed.
function api_webhook_test_queue($id, $event, $sample) {

	$id = (int)$id;

	$created_at = gmdate('Y-m-d\TH:i:s\Z');

	$name = ($sample !== null) ? (string)$event : 'webhook.test';

	$body = ($sample !== null)
		? api_webhook_sample_body($event, $sample, $created_at)
		: json_encode(array(
			'event'      => 'webhook.test',
			'created_at' => $created_at,
			'data'       => array('webhook_id' => $id)
		), JSON_UNESCAPED_UNICODE);

	$done = db("INSERT INTO api_webhook_queue
		(webhook_id, event, payload, attempts, next_attempt_at, created_timestamp)
		VALUES (
			'" . $id . "',
			'" . escape($name) . "',
			'" . escape($body) . "',
			'0',
			UNIX_TIMESTAMP(),
			UNIX_TIMESTAMP()
		)");

	$delivery = $done ? (int)mysqli_insert_id(db::$con) : 0;

	return ($delivery > 0) ? array('id' => $delivery, 'event' => $name) : null;

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
		'vars'   => array(pg_ops_alert_mask_url($row['url']), $app['name'], $app['api_key'])
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
		'created_at'       => 'datetime?',
		'secret'           => 'string',
		'signature_header' => 'string',
		'requeued'         => 'integer'
	);

}

// One row of GET /webhooks/events (api_webhook_event_rows()), declared for the
// OpenAPI document. Each field is name, type, nullable and description; the
// sample is null for an event whose payload is not written yet.
function api_webhook_event_schema() {

	return array(
		'name'        => 'string',
		'description' => 'string',
		'module'      => 'string',
		'fields'      => 'object[]',
		'sample'      => 'object?'
	);

}
