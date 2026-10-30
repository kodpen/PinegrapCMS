<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Sendcloud's side of the carrier webhook layer
 * (includes/shipping/sendcloud.php): the parcel_status_changed message of
 * Sendcloud's reference (sendcloud.dev, webhooks v3: {action, timestamp,
 * carrier_status_change_timestamp, parcel}) read into a track() answer, the
 * Sendcloud-Signature check (HMAC-SHA256 of the raw body with the secret
 * key) through the layer's verify hook, and registration and removal on the
 * API integration through the stand-in transport of
 * tests/carrier_label_sendcloud_test.php. Sendcloud is never called.
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
require_once(__DIR__ . '/carrier_label_sendcloud_test.php');

function carrier_webhook_sendcloud_test_body($changes = array())
{
	$message = json_decode(carrier_label_sendcloud_test_fixture('webhook_parcel_status_changed.json'), true);

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

function carrier_webhook_sendcloud_test_events($body)
{
	pg_label_load('sendcloud');

	return pg_label_sendcloud_webhook_read($body);
}

function test_carrier_webhook_sendcloud_reads_the_status_message()
{
	$events = carrier_webhook_sendcloud_test_events(carrier_webhook_sendcloud_test_body());

	pg_assert_true(is_array($events), 'a readable message');
	pg_assert_same(1, count($events));
	pg_assert_same('f3a1c2d4-5b6e-4f70-8a9b-0c1d2e3f4a5b', $events[0]['reference'], 'the shipment id is the label reference');
	pg_assert_false($events[0]['is_return']);

	$answer = pg_label_answer('track', $events[0]['answer']);
	pg_assert_true($answer['success'], (string) $answer['error']);
	pg_assert_same('out_for_delivery', $answer['status']);
	pg_assert_same(gmmktime(8, 12, 0, 10, 30, 2026), $answer['status_at'], 'the carrier\'s time');
	pg_assert_same('05212345678901', $answer['tracking_number']);
	pg_assert_same('f3a1c2d4-5b6e-4f70-8a9b-0c1d2e3f4a5b', $answer['reference']);
	pg_assert_true(strncmp($answer['tracking_url'], 'https://tracking.', 17) === 0);

	// Without the carrier's time, Sendcloud's own (in milliseconds).
	$own = carrier_webhook_sendcloud_test_events(carrier_webhook_sendcloud_test_body(array(
		'carrier_status_change_timestamp' => null,
		'timestamp'                       => 1793351520000,
		'parcel.status.id'                => 11,
	)));
	pg_assert_same('delivered', $own[0]['answer']['status']);
	pg_assert_same(gmmktime(9, 12, 0, 10, 30, 2026), $own[0]['answer']['status_at']);

	$statuses = array(1000 => 'created', 1 => 'created', 22 => 'in_transit', 3 => 'in_transit', 5 => 'in_transit', 8 => 'in_transit', 12 => 'in_transit', 93 => 'delivered', 80 => 'exception');

	foreach ($statuses as $id => $status) {
		$read = carrier_webhook_sendcloud_test_events(carrier_webhook_sendcloud_test_body(array('parcel.status.id' => $id)));
		pg_assert_same($status, $read[0]['answer']['status'], 'status ' . $id);
	}
}

function test_carrier_webhook_sendcloud_ignores_other_messages()
{
	// Another action, a return parcel (its label is r-<return>-<parcel>
	// and a return records nothing), a shipment id that cannot be one and
	// a status with no meaning here carry nothing that is written; a body
	// that is not a message is refused.
	pg_assert_same(array(), carrier_webhook_sendcloud_test_events(carrier_webhook_sendcloud_test_body(array('action' => 'integration_updated'))));
	pg_assert_same(array(), carrier_webhook_sendcloud_test_events(carrier_webhook_sendcloud_test_body(array('parcel.is_return' => true))));
	pg_assert_same(array(), carrier_webhook_sendcloud_test_events(carrier_webhook_sendcloud_test_body(array('parcel.shipment_uuid' => '../shipments'))));
	pg_assert_same(array(), carrier_webhook_sendcloud_test_events(carrier_webhook_sendcloud_test_body(array('parcel.shipment_uuid' => null))));
	$cancelled = carrier_webhook_sendcloud_test_events(carrier_webhook_sendcloud_test_body(array('parcel.status.id' => 2000)));
	pg_assert_false($cancelled[0]['answer']['success'], 'no status');
	pg_assert_false(carrier_webhook_sendcloud_test_events('not json'));
	pg_assert_false(carrier_webhook_sendcloud_test_events('{"parcel":{}}'));
}

function test_carrier_webhook_sendcloud_checks_the_signature()
{
	pg_label_load('sendcloud');
	$account = carrier_label_sendcloud_test_account();
	$body = carrier_webhook_sendcloud_test_body();
	$good = hash_hmac('sha256', $body, $account['secret_key']);

	pg_assert_true(pg_label_sendcloud_webhook_verify($body, array('sendcloud-signature' => $good), $account));
	pg_assert_true(pg_label_sendcloud_webhook_verify($body, array('sendcloud-signature' => strtoupper($good)), $account), 'hex in any case');
	pg_assert_false(pg_label_sendcloud_webhook_verify($body . ' ', array('sendcloud-signature' => $good), $account), 'another body');
	pg_assert_false(pg_label_sendcloud_webhook_verify($body, array('sendcloud-signature' => hash_hmac('sha256', $body, 'sk_other')), $account), 'another key');
	pg_assert_false(pg_label_sendcloud_webhook_verify($body, array(), $account), 'missing');
	pg_assert_false(pg_label_sendcloud_webhook_verify($body, array('sendcloud-signature' => ''), $account), 'empty');
	pg_assert_false(pg_label_sendcloud_webhook_verify($body, array('sendcloud-signature' => $good), carrier_label_sendcloud_test_account(array('secret_key' => ''))), 'no key');

	// The layer: headers as PHP gives them, the hook called when a driver
	// has one, none for a driver without.
	$headers = pg_carrier_webhook_headers(array('HTTP_SENDCLOUD_SIGNATURE' => $good, 'CONTENT_TYPE' => 'application/json', 'REQUEST_METHOD' => 'POST'));
	pg_assert_same(array('sendcloud-signature' => $good, 'content-type' => 'application/json'), $headers);
	pg_assert_true(pg_carrier_webhook_verified('sendcloud', $body, $headers, $account));
	pg_assert_false(pg_carrier_webhook_verified('sendcloud', $body, array(), $account));
	pg_assert_true(pg_carrier_webhook_verified('shippo', $body, array(), array()), 'Shippo signs nothing');
}

function test_carrier_webhook_sendcloud_message_moves_the_parcel()
{
	$events = carrier_webhook_sendcloud_test_events(carrier_webhook_sendcloud_test_body());
	$event = $events[0];
	$event['answer'] = pg_label_answer('track', $event['answer']);
	$row = array(
		'id'           => 7,
		'order_id'     => 42,
		'order_number' => '10042',
		'order_status' => 'shipped',
		'ship_to_id'   => 51,
		'driver'       => 'sendcloud',
		'reference'    => 'f3a1c2d4-5b6e-4f70-8a9b-0c1d2e3f4a5b',
		'state'        => 'active',
		'is_return'    => 0,
		'number'       => '05212345678901',
		'carrier'      => 'dpd',
		'tracking_url' => '',
		'status'       => 'in_transit',
		'status_at'    => gmmktime(17, 40, 0, 10, 28, 2026),
		'delivered_at' => 0,
	);

	pg_assert_same('apply', pg_carrier_webhook_decide($row, $event, gmmktime(8, 15, 0, 10, 30, 2026))['action']);
	pg_assert_same('ignored', pg_carrier_webhook_decide(array_merge($row, array('is_return' => 1, 'reference' => 'r-1-2')), $event, gmmktime(8, 15, 0, 10, 30, 2026))['action'], 'a return label');
}

function test_carrier_webhook_sendcloud_registers_and_removes()
{
	pg_label_load('sendcloud');
	$url = 'https://shop.example/carrier_webhook.php?driver=sendcloud&key=k9k9k9k9';
	$ours = '{"data":{"id":4013,"type":"api","webhook_active":true,"webhook_url":"' . str_replace('/', '\\/', $url) . '"}}';
	$theirs = json_decode(carrier_label_sendcloud_test_fixture('integrations.json'), true);
	$theirs['data'][1]['webhook_active'] = true;
	$theirs['data'][1]['webhook_url'] = 'https://erp.example/sendcloud';
	$two = json_decode(carrier_label_sendcloud_test_fixture('integrations.json'), true);
	$two['data'][0]['type'] = 'api';

	carrier_label_sendcloud_test_stub(array(
		'GET /integrations'        => array('integrations.json', array(200, json_encode($theirs)), array(200, json_encode($two))),
		'PATCH /integrations/4013' => array(array(204, ''), array(204, '')),
		'GET /integrations/4013'   => array(array(200, $ours), array(404, '{"errors":[{"status":404,"code":"not_found","detail":"The Integration could not be found"}]}'), array(200, json_encode(array('data' => $theirs['data'][1])))),
	));
	$registered = pg_label_sendcloud_webhook_register($url, carrier_label_sendcloud_test_account());
	$taken = pg_label_sendcloud_webhook_register($url, carrier_label_sendcloud_test_account());
	$unclear = pg_label_sendcloud_webhook_register($url, carrier_label_sendcloud_test_account());
	$removed = pg_label_sendcloud_webhook_remove('4013', carrier_label_sendcloud_test_account());
	$gone = pg_label_sendcloud_webhook_remove('4013', carrier_label_sendcloud_test_account());
	$left = pg_label_sendcloud_webhook_remove('4013', carrier_label_sendcloud_test_account());
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	$requests = carrier_label_sendcloud_test_sent();
	carrier_label_sendcloud_test_done();

	pg_assert_true($registered['success'], $registered['error']);
	pg_assert_same('4013', $registered['id'], 'the API integration, not the shop');
	pg_assert_same(array('webhook_active' => true, 'webhook_url' => $url), $sent[1]['body']);
	pg_assert_false($taken['success'], 'another address is never overwritten');
	pg_assert_true(strpos($taken['error'], 'another address') !== false, $taken['error']);
	pg_assert_false($unclear['success'], 'two API integrations: which one the keys belong to is unknown');
	pg_assert_true($removed['success'], $removed['error']);
	pg_assert_same(array('webhook_active' => false, 'webhook_url' => null), $sent[5]['body']);
	pg_assert_true($gone['success'], 'a 404 counts as removed');
	pg_assert_true($left['success'], 'another address is left alone');
	pg_assert_same(array(
		'GET /integrations', 'PATCH /integrations/4013', 'GET /integrations', 'GET /integrations',
		'GET /integrations/4013', 'PATCH /integrations/4013', 'GET /integrations/4013', 'GET /integrations/4013',
	), $requests);
	pg_assert_false(pg_label_sendcloud_webhook_remove('../shipments', carrier_label_sendcloud_test_account())['success']);
}
