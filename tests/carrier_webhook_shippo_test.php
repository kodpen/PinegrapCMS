<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Shippo's side of the carrier webhook layer
 * (includes/shipping/shippo.php): the track_updated message of Shippo's
 * reference (docs.goshippo.com, webhooks guide, API version 2018-02-08:
 * {event, test, data: <a tracking object>}) and the older transaction
 * message, read into a track() answer; registration and removal through the
 * stand-in transport of tests/carrier_label_shippo_test.php. Shippo is
 * never called.
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
require_once(__DIR__ . '/carrier_label_shippo_test.php');

function carrier_webhook_shippo_test_body($changes = array(), $message = null)
{
	if ($message === null) {
		$message = json_decode(carrier_label_shippo_test_fixture('webhook_track_updated.json'), true);
	}

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

function carrier_webhook_shippo_test_events($body)
{
	pg_label_load('shippo');

	return pg_label_shippo_webhook_read($body);
}

function test_carrier_webhook_shippo_reads_the_tracking_message()
{
	$events = carrier_webhook_shippo_test_events(carrier_webhook_shippo_test_body());

	pg_assert_true(is_array($events), 'a readable message');
	pg_assert_same(1, count($events));
	pg_assert_same('915d94940ea54c3a80cbfa328722f5a1', $events[0]['reference'], 'the transaction is the label reference');
	pg_assert_false($events[0]['is_return'], 'the label row decides');

	$answer = pg_label_answer('track', $events[0]['answer']);
	pg_assert_true($answer['success'], (string) $answer['error']);
	pg_assert_same('out_for_delivery', $answer['status']);
	pg_assert_same(gmmktime(14, 2, 0, 10, 30, 2026), $answer['status_at']);
	pg_assert_same('01234567890123', $answer['tracking_number']);
	pg_assert_same('915d94940ea54c3a80cbfa328722f5a1', $answer['reference']);

	$delivered = carrier_webhook_shippo_test_events(carrier_webhook_shippo_test_body(array(
		'event'                          => 'TRACK_UPDATED',
		'data.tracking_status.status'    => 'DELIVERED',
		'data.tracking_status.substatus' => null,
	)));
	pg_assert_same('delivered', $delivered[0]['answer']['status'], 'the event name in any case');
}

function test_carrier_webhook_shippo_reads_the_older_transaction_message()
{
	$transaction = json_decode(carrier_label_shippo_test_fixture('transaction_transit.json'), true);
	$events = carrier_webhook_shippo_test_events(json_encode(array('event' => 'track_updated', 'test' => true, 'data' => $transaction)));

	pg_assert_same('915d94940ea54c3a80cbfa328722f5a1', $events[0]['reference'], "the transaction's own object_id");
	pg_assert_same('in_transit', $events[0]['answer']['status']);
	pg_assert_same('https://tracking.dpd.de/parcelstatus?query=01234567890123&locale=en_DE', $events[0]['answer']['tracking_url']);
}

function test_carrier_webhook_shippo_ignores_other_messages()
{
	// Another event, Shippo's own test message (transaction null), a
	// reference that cannot be an id and an UNKNOWN status carry nothing
	// that is written; a body that is not a message is refused.
	pg_assert_same(array(), carrier_webhook_shippo_test_events(carrier_webhook_shippo_test_body(array('event' => 'transaction_created'))));
	pg_assert_same(array(), carrier_webhook_shippo_test_events(carrier_webhook_shippo_test_body(array('data.transaction' => null))));
	pg_assert_same(array(), carrier_webhook_shippo_test_events(carrier_webhook_shippo_test_body(array('data.transaction' => '../refunds'))));
	$unknown = carrier_webhook_shippo_test_events(carrier_webhook_shippo_test_body(array('data.tracking_status.status' => 'UNKNOWN')));
	pg_assert_false($unknown[0]['answer']['success'], 'no status');
	pg_assert_false(carrier_webhook_shippo_test_events('not json'));
	pg_assert_false(carrier_webhook_shippo_test_events('{"data":{}}'));
}

function test_carrier_webhook_shippo_message_moves_the_parcel()
{
	$events = carrier_webhook_shippo_test_events(carrier_webhook_shippo_test_body());
	$event = $events[0];
	$event['answer'] = pg_label_answer('track', $event['answer']);
	$row = array(
		'id'           => 7,
		'order_id'     => 42,
		'order_number' => '10042',
		'order_status' => 'shipped',
		'ship_to_id'   => 51,
		'driver'       => 'shippo',
		'reference'    => '915d94940ea54c3a80cbfa328722f5a1',
		'state'        => 'active',
		'is_return'    => 0,
		'number'       => '01234567890123',
		'carrier'      => 'dpd',
		'tracking_url' => '',
		'status'       => 'in_transit',
		'status_at'    => gmmktime(9, 0, 0, 10, 30, 2026),
		'delivered_at' => 0,
	);

	pg_assert_same('apply', pg_carrier_webhook_decide($row, $event, gmmktime(14, 5, 0, 10, 30, 2026))['action']);
	pg_assert_same('ignored', pg_carrier_webhook_decide(array_merge($row, array('is_return' => 1)), $event, gmmktime(14, 5, 0, 10, 30, 2026))['action'], 'a return label');
}

function test_carrier_webhook_shippo_registers_and_removes()
{
	pg_label_load('shippo');
	carrier_label_shippo_test_stub(array(
		'POST /webhooks' => array(
			array(201, '{"object_id":"7a1b2c3d4e5f60718293a4b5c6d7e8f9","event":"track_updated","url":"https://shop.example/carrier_webhook.php?driver=shippo&key=k9k9k9k9","active":true,"is_test":true}'),
			array(400, '{"detail":"Webhooks are not available for this account."}'),
		),
		'DELETE /webhooks/7a1b2c3d4e5f60718293a4b5c6d7e8f9' => array(array(204, ''), array(404, '{"detail":"Not found."}'), array(500, '')),
	));
	$url = 'https://shop.example/carrier_webhook.php?driver=shippo&key=k9k9k9k9';
	$registered = pg_label_shippo_webhook_register($url, carrier_label_shippo_test_account());
	$removed = pg_label_shippo_webhook_remove('7a1b2c3d4e5f60718293a4b5c6d7e8f9', carrier_label_shippo_test_account());
	$gone = pg_label_shippo_webhook_remove('7a1b2c3d4e5f60718293a4b5c6d7e8f9', carrier_label_shippo_test_account());
	$failed = pg_label_shippo_webhook_remove('7a1b2c3d4e5f60718293a4b5c6d7e8f9', carrier_label_shippo_test_account());
	$refused = pg_label_shippo_webhook_register($url, carrier_label_shippo_test_account(array('api_token' => 'shippo_live_0a1b2c3d4e5f')));
	$long = pg_label_shippo_webhook_register('https://shop.example/' . str_repeat('a', 180) . '/carrier_webhook.php?key=k', carrier_label_shippo_test_account());
	$sent = $GLOBALS['carrier_label_shippo_test_sent'];
	carrier_label_shippo_test_done();

	pg_assert_true($registered['success'], $registered['error']);
	pg_assert_same('7a1b2c3d4e5f60718293a4b5c6d7e8f9', $registered['id']);
	pg_assert_same(array('event' => 'track_updated', 'url' => $url, 'active' => true, 'is_test' => true), $sent[0]['body']);
	pg_assert_true($removed['success'], 'an empty 2xx');
	pg_assert_true($gone['success'], 'a 404 counts as removed');
	pg_assert_false($failed['success']);
	pg_assert_false($refused['success']);
	pg_assert_false($sent[4]['body']['is_test'], 'a live token registers a live webhook');
	pg_assert_true(strpos($refused['error'], 'Webhooks are not available') !== false);
	pg_assert_false($long['success'], 'Shippo takes under 200 characters');
	pg_assert_same(5, count($sent), 'the long address is never sent');
	pg_assert_false(pg_label_shippo_webhook_remove('../shipments', carrier_label_shippo_test_account())['success']);
}
