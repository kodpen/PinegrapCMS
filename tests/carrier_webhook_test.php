<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the carrier status webhook (includes/fn/carrier_webhook.php,
 * carrier_webhook.php) and Geliver's side of it (includes/shipping/geliver.php):
 * the TRACK_UPDATED message of Geliver's reference (docs.geliver.io, "Test
 * esnasında iletilecek webhook içeriği", kept as
 * tests/fixtures/geliver/webhook_track_updated.json) read into a track()
 * answer, the key in the address, what a message changes on a parcel through
 * the tracking write-back, unknown shipments logged once, and registering and
 * removing the webhook at Geliver through a stand-in transport. Nothing here
 * reaches Geliver or the database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_tracking.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/carrier_webhook.php');

function carrier_webhook_test_body($changes = array())
{
	$message = json_decode((string) file_get_contents(__DIR__ . '/fixtures/geliver/webhook_track_updated.json'), true);

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

function carrier_webhook_test_events($body)
{
	pg_label_load('geliver');

	return pg_label_geliver_webhook_read($body);
}

function carrier_webhook_test_row($changes = array())
{
	return array_merge(array(
		'id'           => 7,
		'order_id'     => 42,
		'order_number' => '10042',
		'order_status' => 'shipped',
		'ship_to_id'   => 51,
		'driver'       => 'geliver',
		'reference'    => '186af322-c9ad-407c-b798-64d464816cf4',
		'state'        => 'active',
		'is_return'    => 0,
		'number'       => '123123123',
		'carrier'      => '',
		'tracking_url' => '',
		'status'       => 'in_transit',
		'status_at'    => 1700000000,
		'delivered_at' => 0,
	), $changes);
}

function carrier_webhook_test_account()
{
	return array('api_token' => 'gl-test-token-123456', 'mode' => 'test', 'sender_address' => '', 'service' => '');
}

function test_carrier_webhook_geliver_reads_the_reference_example()
{
	$events = carrier_webhook_test_events(carrier_webhook_test_body());

	pg_assert_true(is_array($events), 'a readable message');
	pg_assert_same(1, count($events));

	$event = $events[0];
	pg_assert_same('186af322-c9ad-407c-b798-64d464816cf4', $event['reference'], "Geliver's shipment id");
	pg_assert_false($event['is_return']);

	$answer = pg_label_answer('track', $event['answer']);
	pg_assert_true($answer['success'], (string) $answer['error']);
	pg_assert_same('delivered', $answer['status']);
	// statusDate is null in the example: the tracking status's own time.
	pg_assert_same(gmmktime(16, 49, 33, 5, 12, 2026), $answer['status_at']);
	pg_assert_same('http://xxx.com/123123123', $answer['tracking_url']);
	pg_assert_same('', $answer['carrier'], 'no provider code in the example');
}

function test_carrier_webhook_geliver_statuses_and_shapes()
{
	$events = carrier_webhook_test_events(carrier_webhook_test_body(array(
		'data.trackingStatus.trackingStatusCode'    => 'TRANSIT',
		'data.trackingStatus.trackingSubStatusCode' => 'out_for_delivery',
		'data.trackingStatus.statusDate'            => '2026-05-12T10:00:00Z',
		'data.providerCode'                         => 'ARAS',
		'data.isReturn'                             => true,
	)));

	pg_assert_same('out_for_delivery', $events[0]['answer']['status']);
	pg_assert_same(gmmktime(10, 0, 0, 5, 12, 2026), $events[0]['answer']['status_at'], 'statusDate first');
	pg_assert_same('aras', $events[0]['answer']['carrier']);
	pg_assert_true($events[0]['is_return'], "Geliver's own return flag");

	// Another event type, a message without a shipment id and one Geliver
	// gives no status for carry nothing; a body that is not JSON is refused.
	pg_assert_same(array(), carrier_webhook_test_events(carrier_webhook_test_body(array('event' => 'TRANSACTION_CREATED'))));
	pg_assert_same(array(), carrier_webhook_test_events(carrier_webhook_test_body(array('data.id' => ''))));
	pg_assert_same(array(), carrier_webhook_test_events(carrier_webhook_test_body(array('data.id' => '../shipments'))));
	$unknown = carrier_webhook_test_events(carrier_webhook_test_body(array('data.trackingStatus' => null, 'data.statusCode' => '')));
	pg_assert_false($unknown[0]['answer']['success'], 'no status');
	pg_assert_false(carrier_webhook_test_events('not json'));
	pg_assert_false(carrier_webhook_test_events('[1,2]'));
}

function test_carrier_webhook_key_check()
{
	pg_assert_true(pg_carrier_webhook_key_matches('a1b2c3d4e5f60718293a4b5c6d7e8f90', 'a1b2c3d4e5f60718293a4b5c6d7e8f90'));
	pg_assert_false(pg_carrier_webhook_key_matches('a1b2c3d4e5f60718293a4b5c6d7e8f90', 'a1b2c3d4e5f60718293a4b5c6d7e8f9'));
	pg_assert_false(pg_carrier_webhook_key_matches('', ''), 'no key stored refuses every message');
	pg_assert_false(pg_carrier_webhook_key_matches('a1b2c3d4e5f60718293a4b5c6d7e8f90', null));
	pg_assert_false(pg_carrier_webhook_key_matches('a1b2c3d4e5f60718293a4b5c6d7e8f90', array('a1b2c3d4e5f60718293a4b5c6d7e8f90')));

	pg_assert_same(32, strlen(pg_carrier_webhook_new_key()));
	pg_assert_true(pg_carrier_webhook_new_key() !== pg_carrier_webhook_new_key());

	// No site address in a test run: the address is relative.
	pg_assert_same('/carrier_webhook.php?driver=geliver&key=k1', pg_carrier_webhook_url('geliver', 'k1'));
}

function test_carrier_webhook_decide_maps_to_the_tracking_decision()
{
	$events = carrier_webhook_test_events(carrier_webhook_test_body(array(
		'data.trackingStatus.trackingStatusCode'    => 'TRANSIT',
		'data.trackingStatus.trackingSubStatusCode' => 'out_for_delivery',
	)));
	$event = $events[0];
	$at = gmmktime(16, 49, 33, 5, 12, 2026);

	$decided = pg_carrier_webhook_decide(carrier_webhook_test_row(), $event, $at + 60);
	pg_assert_same('apply', $decided['action']);
	pg_assert_same('out_for_delivery', $decided['decision']['status']);
	pg_assert_same($at, $decided['decision']['at']);
	pg_assert_same(array('tracking_url' => 'http://xxx.com/123123123'), $decided['decision']['options']);
	pg_assert_false($decided['decision']['delivered']);

	// The same status again: the tracking page is all that is left to write.
	$same = pg_carrier_webhook_decide(carrier_webhook_test_row(array('status' => 'out_for_delivery', 'status_at' => $at, 'tracking_url' => 'http://xxx.com/123123123')), $event, $at + 60);
	pg_assert_same('ignored', $same['action'], 'a repeated message writes nothing');

	// Out of order: a message older than what the row holds is dropped.
	$older = pg_carrier_webhook_decide(carrier_webhook_test_row(array('status_at' => $at + 3600)), $event, $at + 7200);
	pg_assert_same('ignored', $older['action']);
	pg_assert_same('stale', $older['reason']);

	// A failure answer writes nothing.
	$failed = $event;
	$failed['answer'] = array('success' => false, 'error' => 'no status');
	pg_assert_same('ignored', pg_carrier_webhook_decide(carrier_webhook_test_row(), $failed, $at)['action']);
}

function test_carrier_webhook_delivered_once()
{
	$events = carrier_webhook_test_events(carrier_webhook_test_body());
	$at = gmmktime(16, 49, 33, 5, 12, 2026);

	$first = pg_carrier_webhook_decide(carrier_webhook_test_row(), $events[0], $at + 60);
	pg_assert_same('apply', $first['action']);
	pg_assert_true($first['decision']['delivered'], 'the arrival');

	// The same message again, once the parcel arrived: no second delivery.
	foreach (array(array('delivered_at' => $at), array('status' => 'delivered'), array('status' => 'returned')) as $changes) {
		$again = pg_carrier_webhook_decide(carrier_webhook_test_row($changes), $events[0], $at + 120);
		pg_assert_same('ignored', $again['action'], json_encode($changes));
		pg_assert_same('closed', $again['reason']);
	}
}

function test_carrier_webhook_return_label_never_delivered()
{
	$events = carrier_webhook_test_events(carrier_webhook_test_body());

	$label = pg_carrier_webhook_decide(carrier_webhook_test_row(array('is_return' => 1, 'number' => null)), $events[0], 1800000000);
	pg_assert_same('ignored', $label['action']);
	pg_assert_same('return', $label['reason']);

	$flagged = $events[0];
	$flagged['is_return'] = true;
	$message = pg_carrier_webhook_decide(carrier_webhook_test_row(), $flagged, 1800000000);
	pg_assert_same('ignored', $message['action'], "Geliver's return flag");
	pg_assert_same('return', $message['reason']);
}

function test_carrier_webhook_unknown_and_closed_labels()
{
	$events = carrier_webhook_test_events(carrier_webhook_test_body());

	$unknown = pg_carrier_webhook_decide(null, $events[0], 1800000000);
	pg_assert_same('unknown', $unknown['action']);

	foreach (array(
		array('state' => 'cancelled'),
		array('number' => null),
		array('order_status' => 'cancelled'),
	) as $changes) {
		pg_assert_same('ignored', pg_carrier_webhook_decide(carrier_webhook_test_row($changes), $events[0], 1800000000)['action'], json_encode($changes));
	}

	// An unknown shipment is logged once; the list keeps the last 100.
	$first = pg_carrier_webhook_unknown_note(array(), 'geliver', 'abc');
	pg_assert_true($first['log']);
	$again = pg_carrier_webhook_unknown_note($first['seen'], 'geliver', 'abc');
	pg_assert_false($again['log']);
	pg_assert_true(pg_carrier_webhook_unknown_note($first['seen'], 'shippo', 'abc')['log'], 'per driver');

	$seen = array();

	for ($i = 0; $i < 150; $i++) {
		$seen = pg_carrier_webhook_unknown_note($seen, 'geliver', 'r' . $i)['seen'];
	}

	pg_assert_same(100, count($seen));
	pg_assert_true(pg_carrier_webhook_unknown_note($seen, 'geliver', 'r0')['log'], 'the oldest fell out');
	pg_assert_false(pg_carrier_webhook_unknown_note($seen, 'geliver', 'r149')['log']);
}

function test_carrier_webhook_geliver_registers_and_removes()
{
	pg_label_load('geliver');
	$sent = array();
	$answers = array(
		array(200, '{"result":true,"additionalMessage":"Success","data":{"id":"10a26f76-e668-494b-bafa-372e5bf65128","type":"TRACK_UPDATED","url":"https://shop.example/carrier_webhook.php?driver=geliver&key=k9k9k9k9","isActive":true}}'),
		array(200, '{"result":true,"additionalMessage":"Success","data":{"id":"00000000-0000-0000-0000-000000000000","isActive":false}}'),
		array(400, '{"result":false,"code":"E1001","message":"invalid url https://shop.example/carrier_webhook.php?driver=geliver&key=k9k9k9k9"}'),
		array(404, '{"result":false,"message":"not found"}'),
	);

	pg_label_geliver_transport(function ($method, $url, $headers, $body) use (&$sent, &$answers) {
		$sent[] = array($method, $url, $body);
		$answer = array_shift($answers);

		return array('status' => $answer[0], 'body' => $answer[1], 'error' => '', 'sent' => true);
	});

	$url = 'https://shop.example/carrier_webhook.php?driver=geliver&key=k9k9k9k9';
	$registered = pg_label_geliver_webhook_register($url, carrier_webhook_test_account());
	$removed = pg_label_geliver_webhook_remove('10a26f76-e668-494b-bafa-372e5bf65128', carrier_webhook_test_account());
	$refused = pg_label_geliver_webhook_register($url, carrier_webhook_test_account());
	$gone = pg_label_geliver_webhook_remove('10a26f76-e668-494b-bafa-372e5bf65128', carrier_webhook_test_account());

	pg_label_geliver_transport(null, true);

	pg_assert_true($registered['success']);
	pg_assert_same('10a26f76-e668-494b-bafa-372e5bf65128', $registered['id']);
	pg_assert_same(array('POST', 'https://api.geliver.io/api/v1/webhook'), array($sent[0][0], $sent[0][1]));
	pg_assert_same(array('type' => 'TRACK_UPDATED', 'url' => $url), json_decode($sent[0][2], true));

	pg_assert_true($removed['success']);
	pg_assert_same(array('DELETE', 'https://api.geliver.io/api/v1/webhook/10a26f76-e668-494b-bafa-372e5bf65128'), array($sent[1][0], $sent[1][1]));

	pg_assert_false($refused['success']);
	pg_assert_true(strpos($refused['error'], 'E1001') !== false, $refused['error']);

	pg_assert_true($gone['success'], 'a webhook Geliver no longer has counts as removed');

	// An id that is not one never reaches the address.
	pg_assert_false(pg_label_geliver_webhook_remove('../shipments', carrier_webhook_test_account())['success']);
}

function test_carrier_webhook_masks_the_key_in_messages()
{
	pg_assert_same('invalid url https://shop.example/carrier_webhook.php?driver=geliver&key=****', pg_carrier_webhook_mask('invalid url https://shop.example/carrier_webhook.php?driver=geliver&key=k9k9k9k9', 'k9k9k9k9'));
	pg_assert_same('text', pg_carrier_webhook_mask('text', ''));
}

function test_carrier_webhook_writes_only_through_the_tracking_write_back()
{
	$receive = pg_test_function_source('pg_carrier_webhook_receive');
	pg_assert_contains('pg_carrier_webhook_decide(', $receive);
	pg_assert_contains('pg_shipment_tracking_apply(', $receive);

	foreach (array('pg_shipment_record(', 'pg_shipment_delivered(', 'shipping_tracking_numbers SET', 'UPDATE shipping_tracking_numbers') as $write) {
		pg_assert_false(strpos($receive, $write) !== false, $write);
	}

	pg_assert_contains('pg_shipment_tracking_decide(', pg_test_function_source('pg_carrier_webhook_decide'));
	pg_assert_contains('hash_equals(', pg_test_function_source('pg_carrier_webhook_key_matches'));
	pg_assert_contains('pg_carrier_webhook_sync(', pg_test_function_source('pg_label_settings_save'));

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	pg_assert_contains("'payment_callback.php', 'carrier_webhook.php'", $waf, 'in the firewall\'s API list');

	$entry = (string) file_get_contents(PG_FUNCTIONS_DIR . '/carrier_webhook.php');
	pg_assert_contains("define('PG_NO_SESSION', true);", $entry);
	pg_assert_contains('pg_carrier_webhook_receive();', $entry);
}
