<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Kargonomi's side of the carrier webhook layer
 * (includes/shipping/kargonomi.php): the shipment.updated message of
 * Kargonomi's reference ({meta, shipment}, the shipment in the shape GET
 * /shipments/{id} answers) read into a track() answer, the
 * X-Webhook-Signature check with a stored secret, and registration and
 * removal through the stand-in transport of
 * tests/carrier_label_kargonomi_test.php. Kargonomi is never called.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_tracking.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/carrier_webhook.php');
require_once(__DIR__ . '/carrier_label_kargonomi_test.php');

function carrier_webhook_kargonomi_test_body($changes = array())
{
	$message = json_decode(carrier_label_kargonomi_test_fixture('webhook_shipment_updated.json'), true);

	foreach ($changes as $path => $value) {
		$at = &$message;

		foreach (explode('.', $path) as $name) {
			$at = &$at[$name];
		}

		$at = $value;
		unset($at);
	}

	return json_encode($message);
}

function carrier_webhook_kargonomi_test_events($body)
{
	pg_label_load('kargonomi');

	return pg_label_kargonomi_webhook_read($body);
}

function test_carrier_webhook_kargonomi_reads_a_status_change()
{
	$events = carrier_webhook_kargonomi_test_events(carrier_webhook_kargonomi_test_body());

	pg_assert_true(is_array($events), 'a readable message');
	pg_assert_same(1, count($events));
	pg_assert_same('8', $events[0]['reference'], 'the shipment id is the label reference');
	pg_assert_false($events[0]['is_return'], 'Kargonomi makes outgoing shipments only');

	$answer = pg_label_answer('track', $events[0]['answer']);
	pg_assert_true($answer['success'], (string) $answer['error']);
	pg_assert_same('in_transit', $answer['status']);
	pg_assert_same(gmmktime(13, 20, 0, 10, 27, 2026), $answer['status_at'], 'handed to the carrier');
	pg_assert_same('7344501034697', $answer['tracking_number']);
	pg_assert_same('aras', $answer['carrier']);
	pg_assert_same('8', $answer['reference']);
}

function test_carrier_webhook_kargonomi_reads_a_delivery()
{
	$events = carrier_webhook_kargonomi_test_events(carrier_webhook_kargonomi_test_body(array(
		'shipment.status'                                   => 'webservice_shipment_delivered',
		'shipment.status_label'                             => 'Teslim Edildi',
		'shipment.shipping_provider_customer_delivery_date' => '2026-10-29T10:45:00.000000Z',
	)));

	pg_assert_same('delivered', $events[0]['answer']['status']);
	pg_assert_same(gmmktime(10, 45, 0, 10, 29, 2026), $events[0]['answer']['status_at'], 'the customer delivery date');
	pg_assert_same(array('Kargonomi: Teslim Edildi.'), $events[0]['answer']['log']);
}

function test_carrier_webhook_kargonomi_track_shares_the_answer()
{
	carrier_label_kargonomi_test_stub(array('GET /shipments/8' => array('shipment_started.json')));
	$tracked = pg_label_kargonomi_track('8', carrier_label_kargonomi_test_account());
	carrier_label_kargonomi_test_done();

	$events = carrier_webhook_kargonomi_test_events(carrier_webhook_kargonomi_test_body());

	foreach (array('success', 'reference', 'status', 'status_at', 'tracking_number', 'carrier', 'log') as $name) {
		pg_assert_same($tracked[$name], $events[0]['answer'][$name], $name);
	}
}

function test_carrier_webhook_kargonomi_ignores_other_messages()
{
	// Another event, a shipment id that cannot be one and an unknown status
	// write nothing; a body that is not a message is refused.
	pg_assert_same(array(), carrier_webhook_kargonomi_test_events(carrier_webhook_kargonomi_test_body(array('meta.webhook.event_type' => 'shipment.created'))));
	pg_assert_same(array(), carrier_webhook_kargonomi_test_events(carrier_webhook_kargonomi_test_body(array('shipment.id' => '../user'))));
	$unknown = carrier_webhook_kargonomi_test_events(carrier_webhook_kargonomi_test_body(array('shipment.status' => 'on_the_moon')));
	pg_assert_false($unknown[0]['answer']['success'], 'no status');
	pg_assert_false(carrier_webhook_kargonomi_test_events('not json'));
	pg_assert_false(carrier_webhook_kargonomi_test_events('{"meta":{}}'));
	pg_assert_false(carrier_webhook_kargonomi_test_events('{"shipment":"8"}'));
}

function test_carrier_webhook_kargonomi_message_moves_the_parcel()
{
	$events = carrier_webhook_kargonomi_test_events(carrier_webhook_kargonomi_test_body());
	$event = $events[0];
	$event['answer'] = pg_label_answer('track', $event['answer']);
	$row = array(
		'id'           => 7,
		'order_id'     => 42,
		'order_number' => '10042',
		'order_status' => 'shipped',
		'ship_to_id'   => 51,
		'driver'       => 'kargonomi',
		'reference'    => '8',
		'state'        => 'active',
		'is_return'    => 0,
		'number'       => '7344501034697',
		'carrier'      => 'aras',
		'tracking_url' => '',
		'status'       => 'created',
		'status_at'    => gmmktime(7, 10, 0, 10, 27, 2026),
		'delivered_at' => 0,
	);

	pg_assert_same('apply', pg_carrier_webhook_decide($row, $event, gmmktime(13, 30, 0, 10, 27, 2026))['action']);
}

function test_carrier_webhook_kargonomi_checks_the_signature()
{
	pg_label_load('kargonomi');
	$raw = carrier_webhook_kargonomi_test_body();
	$secret = 'whsec-3f9a1c7e5b2d4086';
	$account = carrier_label_kargonomi_test_account(array('webhook_secret' => $secret));
	$hex = hash_hmac('sha256', $raw, $secret);

	pg_assert_true(pg_label_kargonomi_webhook_verify($raw, array('x-webhook-signature' => $hex), $account), 'hex');
	pg_assert_true(pg_label_kargonomi_webhook_verify($raw, array('x-webhook-signature' => strtoupper($hex)), $account), 'any case');
	pg_assert_true(pg_label_kargonomi_webhook_verify($raw, array('x-webhook-signature' => 'sha256=' . $hex), $account), 'with the algorithm');
	pg_assert_true(pg_label_kargonomi_webhook_verify($raw, array('x-webhook-signature' => base64_encode(hash_hmac('sha256', $raw, $secret, true))), $account), 'base64');
	pg_assert_false(pg_label_kargonomi_webhook_verify($raw, array('x-webhook-signature' => hash_hmac('sha256', $raw, 'another')), $account), 'another secret');
	pg_assert_false(pg_label_kargonomi_webhook_verify($raw . ' ', array('x-webhook-signature' => $hex), $account), 'a changed body');
	pg_assert_false(pg_label_kargonomi_webhook_verify($raw, array(), $account), 'no signature with a secret');

	// No secret known: the key in the address is the credential.
	pg_assert_true(pg_label_kargonomi_webhook_verify($raw, array(), carrier_label_kargonomi_test_account()));
}

function test_carrier_webhook_kargonomi_reads_the_register_answer()
{
	pg_label_load('kargonomi');
	$created = json_decode(carrier_label_kargonomi_test_fixture('webhook_created.json'), true);

	pg_assert_same(array('id' => '14', 'secret' => ''), pg_label_kargonomi_webhook_created($created));
	pg_assert_same(array('id' => '14', 'secret' => 'whsec-3f9a1c7e5b2d4086'), pg_label_kargonomi_webhook_created(array('webhook' => array('id' => 14, 'secret_key' => 'whsec-3f9a1c7e5b2d4086'))));
	pg_assert_same(array('id' => '15', 'secret' => ''), pg_label_kargonomi_webhook_created(array('id' => 15)));
	pg_assert_same(array('id' => '', 'secret' => ''), pg_label_kargonomi_webhook_created(array('message' => 'ok')));
}

function test_carrier_webhook_kargonomi_registers_and_removes()
{
	carrier_label_kargonomi_test_stub(array(
		'POST /webhooks'      => array(array(201, carrier_label_kargonomi_test_fixture('webhook_created.json')), 'error_422.json'),
		'DELETE /webhooks/14' => array(array(200, '{"message":"Webhook silindi."}'), array(404, '{"message":"Not found."}'), array(500, '')),
	));
	$url = 'https://shop.example/carrier_webhook.php?driver=kargonomi&key=k9k9k9k9';
	$account = carrier_label_kargonomi_test_account();

	$registered = pg_label_kargonomi_webhook_register($url, $account);
	pg_assert_same(array('success' => true, 'id' => '14', 'error' => ''), $registered);
	$sent = $GLOBALS['carrier_label_kargonomi_test_sent'][0]['body'];
	pg_assert_same('shipment.updated', $sent['event_type']);
	pg_assert_same($url, $sent['url']);
	pg_assert_true($sent['is_active']);
	pg_assert_true(is_string($sent['name']) && ($sent['name'] !== ''), 'a name');

	$refused = pg_label_kargonomi_webhook_register($url, $account);
	pg_assert_false($refused['success']);
	pg_assert_same('', $refused['id']);

	pg_assert_true(pg_label_kargonomi_webhook_remove('14', $account)['success'], 'removed');
	pg_assert_true(pg_label_kargonomi_webhook_remove('14', $account)['success'], 'a 404 counts as removed');
	pg_assert_false(pg_label_kargonomi_webhook_remove('14', $account)['success'], 'a server error');
	pg_assert_false(pg_label_kargonomi_webhook_remove('../user', $account)['success'], 'not an id');
	pg_assert_same(array('POST /webhooks', 'POST /webhooks', 'DELETE /webhooks/14', 'DELETE /webhooks/14', 'DELETE /webhooks/14'), carrier_label_kargonomi_test_sent());
	carrier_label_kargonomi_test_done();
}
