<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Basit Kargo's side of the carrier webhook layer
 * (includes/shipping/basitkargo.php): the two messages of its reference
 * (basitkargo.com/api, Webhook), a status change ({id, orderNumber,
 * orderId, barcode, status, handler, handlerShipmentCode}, no time) and a
 * movement (the GET /v2/order/{id} shape, traces included), read into a
 * track() answer. The address is added in Basit Kargo's panel by hand.
 * Basit Kargo is never called.
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

function carrier_webhook_basitkargo_test_body($fixture, $changes = array())
{
	$message = json_decode((string) file_get_contents(__DIR__ . '/fixtures/basitkargo/' . $fixture), true);

	foreach ($changes as $name => $value) {
		if ($value === null) {
			unset($message[$name]);
		} else {
			$message[$name] = $value;
		}
	}

	return json_encode($message);
}

function carrier_webhook_basitkargo_test_events($body)
{
	pg_label_load('basitkargo');

	return pg_label_basitkargo_webhook_read($body);
}

function test_carrier_webhook_basitkargo_reads_a_status_change()
{
	$events = carrier_webhook_basitkargo_test_events(carrier_webhook_basitkargo_test_body('webhook_status.json'));

	pg_assert_true(is_array($events), 'a readable message');
	pg_assert_same(1, count($events));
	pg_assert_same('888-6AR-OUP', $events[0]['reference'], 'the order id is the label reference');
	pg_assert_false($events[0]['is_return']);

	$answer = pg_label_answer('track', $events[0]['answer']);
	pg_assert_true($answer['success'], (string) $answer['error']);
	pg_assert_same('in_transit', $answer['status']);
	pg_assert_same(0, $answer['status_at'], 'a status change carries no time: the receiver uses now');
	pg_assert_same('ARS5566778899', $answer['tracking_number']);
	pg_assert_same('aras', $answer['carrier'], 'from the top-level handler');

	// No time: the tracking decision dates it at the moment it arrived.
	$row = array('status' => 'created', 'status_at' => 1700000000, 'number' => 'ARS5566778899', 'carrier' => 'aras', 'tracking_url' => '', 'delivered_at' => 0, 'is_return' => 0);
	$decision = pg_shipment_tracking_decide($row, $answer, 1800000000);
	pg_assert_true($decision['ok']);
	pg_assert_same(1800000000, $decision['at']);

	$barcode = carrier_webhook_basitkargo_test_events(carrier_webhook_basitkargo_test_body('webhook_status.json', array('handlerShipmentCode' => null)));
	pg_assert_same('BK4815162342', $barcode[0]['answer']['tracking_number'], 'Basit Kargo\'s barcode before the carrier gives a number');
}

function test_carrier_webhook_basitkargo_reads_a_movement_newest_first()
{
	$events = carrier_webhook_basitkargo_test_events(carrier_webhook_basitkargo_test_body('webhook_movement.json'));

	pg_assert_same(1, count($events));
	pg_assert_same('888-6AR-OUP', $events[0]['reference']);
	pg_assert_false($events[0]['is_return'], 'OUTGOING');

	$answer = $events[0]['answer'];
	pg_assert_same('delivered', $answer['status']);
	pg_assert_same(strtotime('2026-10-31T18:10:10'), $answer['status_at'], 'the latest trace, listed first');
	pg_assert_same(array('Basit Kargo: Teslim Edildi - Çankaya Şube.'), $answer['log']);
	pg_assert_same('aras', $answer['carrier']);

	$incoming = carrier_webhook_basitkargo_test_events(carrier_webhook_basitkargo_test_body('webhook_movement.json', array('type' => 'INCOMING')));
	pg_assert_true($incoming[0]['is_return'], 'an INCOMING order is a return');
}

function test_carrier_webhook_basitkargo_unreadable_and_odd_bodies()
{
	foreach (array('', 'not json', '[]', '"text"', '{"orderNumber":"10042","status":"SHIPPED"}') as $body) {
		pg_assert_false(carrier_webhook_basitkargo_test_events($body), $body);
	}

	pg_assert_same(array(), carrier_webhook_basitkargo_test_events(carrier_webhook_basitkargo_test_body('webhook_status.json', array('id' => '../firm/balance'))), 'an id of another shape names no label');

	$odd = carrier_webhook_basitkargo_test_events(carrier_webhook_basitkargo_test_body('webhook_status.json', array('status' => 'ODD')));
	pg_assert_false(pg_label_answer('track', $odd[0]['answer'])['success'], 'an unknown status writes nothing');
}

function test_carrier_webhook_basitkargo_is_added_by_hand()
{
	pg_label_load('basitkargo');

	pg_assert_true(pg_carrier_webhook_manual('basitkargo') !== false, 'by hand');
	pg_assert_same('', pg_carrier_webhook_function('basitkargo', 'register'), 'Basit Kargo has no registration API');
	pg_assert_same('', pg_carrier_webhook_function('basitkargo', 'verify'), 'its messages are not signed: the key in the address is the credential');
	pg_assert_same(array('Durum Değişikliği', 'Kargo Hareketi'), pg_carrier_webhook_manual('basitkargo')['events']);
}
