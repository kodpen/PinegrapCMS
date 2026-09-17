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

if (!defined('PG_API_ENTRY')) {
	exit;
}

require_once(dirname(dirname(__FILE__)) . '/outbound/webhooks.php');

function api_webhook_present($row) {

	return array(
		'id'         => (int)$row['id'],
		'url'        => $row['url'],
		'events'     => json_decode((string)$row['events'], true),
		'status'     => $row['status'],
		'last_success' => api_time($row['last_success']),
		'last_failure' => api_time($row['last_failure']),
		'created_at'   => api_time($row['created_timestamp'])
	);

}

function api_webhooks_list($params) {

	$app = api_current_app();

	$rows = api_rows("SELECT id, url, events, status, last_success, last_failure, created_timestamp
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

	$events = $params['events'];

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

	if ((int)api_value("SELECT COUNT(*) FROM api_webhooks WHERE app_id = '" . (int)$app['id'] . "'") >= 10) {

		api_fail(409, 'too_many_webhooks', lang('An application may hold ten subscriptions.'));

	}

	$secret = api_generate_secret();

	api_exec("INSERT INTO api_webhooks (app_id, url, events, secret, status, created_timestamp)
		VALUES (
			'" . (int)$app['id'] . "',
			'" . escape($params['url']) . "',
			'" . escape(json_encode($clean)) . "',
			'" . escape($secret) . "',
			'active',
			UNIX_TIMESTAMP()
		)");

	$id = api_insert_id();

	$row = api_row("SELECT id, url, events, status, last_success, last_failure, created_timestamp
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

	api_exec("DELETE FROM api_webhooks WHERE id = '" . $id . "'");

	api_exec("DELETE FROM api_webhook_queue WHERE webhook_id = '" . $id . "'");

	api_send(204, array());

}
