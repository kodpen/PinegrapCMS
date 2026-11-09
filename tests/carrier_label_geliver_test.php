<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Geliver label driver (includes/shipping/geliver.php), every
 * operation called through pg_label_call(). Geliver is never called: a
 * stand-in transport answers with the answers of tests/fixtures/geliver/,
 * built in the shape of Geliver's public API reference (docs.geliver.io),
 * and keeps the requests it was sent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The driver file is loaded here, not by the first test that calls
// pg_label_call(): a test run on its own (a filter) must find its functions.
pg_label_load('geliver');

function carrier_label_geliver_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/geliver/' . $name . '.json');
}

function carrier_label_geliver_test_account($changes = array())
{
	return array_merge(array(
		'api_token'      => 'gl-test-token-123456',
		'mode'           => 'live',
		'sender_address' => '',
		'service'        => '',
	), $changes);
}

function carrier_label_geliver_test_pdf()
{
	return "%PDF-1.4\n1 0 obj << >> endobj\n%%EOF";
}

// Answers each request with the next answer queued for it: "METHOD path"
// (the path without the API base) => list of array(status, body) or a
// fixture name for a 200 answer. A request with nothing queued is not reached.
function carrier_label_geliver_test_stub($answers)
{
	$GLOBALS['carrier_label_geliver_test_sent'] = array();

	pg_label_geliver_transport(function ($method, $url, $headers, $body) use (&$answers) {
		$path = (strncmp($url, 'https://api.geliver.io/api/v1', 29) === 0) ? substr($url, 29) : $url;
		$key = $method . ' ' . $path;
		$GLOBALS['carrier_label_geliver_test_sent'][] = array('request' => $key, 'headers' => $headers, 'body' => ($body === null) ? null : json_decode($body, true));

		if (empty($answers[$key])) {
			return array('status' => 0, 'body' => '', 'error' => 'Operation timed out', 'sent' => ($method !== 'GET'));
		}

		$answer = array_shift($answers[$key]);

		if (is_string($answer)) {
			$answer = array(200, carrier_label_geliver_test_fixture($answer));
		}

		return array('status' => $answer[0], 'body' => $answer[1], 'error' => '', 'sent' => true);
	});
}

function carrier_label_geliver_test_sent()
{
	return array_map(function ($sent) {
		return $sent['request'];
	}, $GLOBALS['carrier_label_geliver_test_sent']);
}

function carrier_label_geliver_test_shipment()
{
	return array(
		'order_number' => '10042',
		'recipient'    => array(
			'name'       => 'Ayşe Yılmaz',
			'email'      => 'ayse@example.com',
			'phone'      => '+905051234567',
			'address_1'  => 'Celal Bayar Üniversitesi Teknokenti Daire 102',
			'address_2'  => '',
			'city'       => 'Yunusemre',
			'state'      => 'Manisa',
			'state_code' => 'TR-45',
			'zip'        => '45000',
			'country'    => 'tr',
		),
		'parcel'       => array('length' => 30, 'width' => 20, 'height' => 10.5, 'weight' => 1.25),
		'items'        => array(array('title' => 'Mug', 'quantity' => 2), array('title' => '', 'quantity' => 1)),
		'total'        => 15000,
		'currency'     => 'TRY',
	);
}

function carrier_label_geliver_test_done()
{
	pg_label_geliver_transport(null, true);
}

function test_carrier_label_geliver_is_a_builtin_driver()
{
	pg_assert_true(pg_label_driver('geliver') !== null);
	$info = pg_label_info('geliver');
	pg_assert_same(array('yurtici', 'surat', 'aras', 'mng', 'ptt'), $info['carriers']);
	pg_assert_same(array('pdf'), $info['formats']);
	pg_assert_same('Yurtiçi Kargo', $info['services']['YURTICI_STANDART']);
	pg_assert_false(isset($info['services']['GELIVER_STANDART']), 'the test service is not offered');

	foreach (array('test', 'create', 'label', 'cancel', 'track', 'rate') as $operation) {
		pg_assert_true(pg_label_can('geliver', $operation), $operation);
	}

	pg_assert_false(pg_label_can('geliver', 'pickup'));

	$fields = array();

	foreach (pg_label_fields('geliver') as $field) {
		$fields[$field['name']] = $field['type'];
	}

	pg_assert_same(array('api_token' => 'secret', 'mode' => 'select', 'sender_address' => 'text', 'service' => 'text'), $fields);
}

function test_carrier_label_geliver_test_reads_the_sender_address()
{
	carrier_label_geliver_test_stub(array('GET /addresses?isRecipientAddress=false&limit=1&page=1' => array('addresses_sender')));

	$answer = pg_label_call('geliver', 'test', array(carrier_label_geliver_test_account()));
	$sent = $GLOBALS['carrier_label_geliver_test_sent'];
	carrier_label_geliver_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('Ana adres', $answer['account']);
	pg_assert_contains('Authorization: Bearer gl-test-token-123456', $sent[0]['headers']);
}

// A service that echoes the token back cannot put it into the notice or the log.
function test_carrier_label_geliver_refused_token_is_masked()
{
	carrier_label_geliver_test_stub(array('GET /addresses/abc-1' => array(array(401, carrier_label_geliver_test_fixture('error_token')))));

	$answer = pg_label_call('geliver', 'test', array(carrier_label_geliver_test_account(array('sender_address' => 'abc-1'))));
	carrier_label_geliver_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('E1055', $answer['error']);
	pg_assert_false(strpos(json_encode($answer), 'gl-test-token-123456'), 'the token is masked');
	pg_assert_contains('****', $answer['error']);
}

function test_carrier_label_geliver_create_accepts_the_cheapest_offer()
{
	carrier_label_geliver_test_stub(array(
		'POST /shipments'    => array('shipment_offers'),
		'POST /transactions' => array('transaction_accepted'),
		'GET https://labels3.geliver.io/labels/4aaaf0b4-d071-4edc-a941-cea2f6a78453.pdf' => array(array(200, carrier_label_geliver_test_pdf())),
	));

	$answer = pg_label_call('geliver', 'create', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account()));
	$sent = $GLOBALS['carrier_label_geliver_test_sent'];
	carrier_label_geliver_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('4aaaf0b4-d071-4edc-a941-cea2f6a78453', $answer['reference']);
	pg_assert_same('64124002705', $answer['tracking_number'], 'the barcode until the carrier gives its number');
	pg_assert_same('64124002705', $answer['barcode']);
	pg_assert_same('ptt', $answer['carrier']);
	pg_assert_same('created', $answer['status']);
	pg_assert_same('pdf', $answer['label_format']);
	pg_assert_same(carrier_label_geliver_test_pdf(), $answer['label']);
	pg_assert_same(array('source' => 'carrier', 'carrier' => 'ptt'), pg_label_shipment_options($answer));

	pg_assert_same(array('offerID' => '16bc98bb-3d1f-4bc1-8acd-14d2422301e6'), $sent[1]['body']);

	$body = $sent[0]['body'];
	pg_assert_same(false, $body['test']);
	pg_assert_same('45', $body['recipientAddress']['cityCode']);
	pg_assert_same('TR', $body['recipientAddress']['countryCode']);
	pg_assert_same('Yunusemre', $body['recipientAddress']['districtName']);
	pg_assert_false(isset($body['recipientAddress']['address2']), 'empty fields are left out');
	pg_assert_same(array(array('title' => 'Mug', 'quantity' => 2)), $body['items']);
	pg_assert_same(array('10.5', '1.25'), array($body['height'], $body['weight']));
	pg_assert_same('TL', $body['order']['totalAmountCurrency']);
	pg_assert_same(150, (int) $body['order']['totalAmount']);
	pg_assert_false(isset($body['senderAddressID']));

	pg_assert_false(strpos(json_encode($sent[2]['headers']), 'Bearer'), 'the label file is fetched without the token');
}

function test_carrier_label_geliver_create_takes_the_chosen_service_and_waits_for_offers()
{
	carrier_label_geliver_test_stub(array(
		'POST /shipments'    => array('shipment_offers_pending'),
		'GET /shipments/4aaaf0b4-d071-4edc-a941-cea2f6a78453' => array('shipment_offers'),
		'POST /transactions' => array('transaction_accepted'),
		'GET https://labels3.geliver.io/labels/4aaaf0b4-d071-4edc-a941-cea2f6a78453.pdf' => array(array(500, '')),
	));

	$shipment = carrier_label_geliver_test_shipment();
	$shipment['service'] = 'ptt_standart';

	$answer = pg_label_call('geliver', 'create', array($shipment, carrier_label_geliver_test_account(array('service' => 'YURTICI_STANDART', 'sender_address' => 'snd-1'))));
	$sent = $GLOBALS['carrier_label_geliver_test_sent'];
	carrier_label_geliver_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('offerID' => '1f26245c-db36-4bce-ac57-6f443847ec4f'), $sent[2]['body']);
	pg_assert_same('snd-1', $sent[0]['body']['senderAddressID']);
	pg_assert_same('', $answer['label'], 'the label is fetched again later');
	pg_assert_contains('The Geliver label could not be read: HTTP 500.', implode(' ', $answer['log']));
}

// The offers of a shipment with no cheapest one named (a test shipment's
// only offer comes so): $drop list ids are left out.
function carrier_label_geliver_test_offers_without_cheapest($drop = array())
{
	$offers = json_decode(carrier_label_geliver_test_fixture('shipment_offers'), true);
	$offers['data']['offers']['cheapest'] = null;
	$offers['data']['offers']['list'] = array_values(array_filter($offers['data']['offers']['list'], function ($offer) use ($drop) {
		return !in_array($offer['id'], $drop, true);
	}));

	return json_encode($offers);
}

// Geliver may leave "cheapest" empty although it made offers; the lowest
// priced offer of the list is taken then.
function test_carrier_label_geliver_offer_without_cheapest_takes_the_lowest_of_the_list()
{
	$offers = array(
		'cheapest' => null,
		'list'     => array(
			array('id' => 'o-1', 'providerServiceCode' => 'PTT_STANDART', 'totalAmount' => '1.5', 'amount' => '1.25'),
			array('id' => 'o-2', 'providerServiceCode' => 'ARAS_STANDART', 'totalAmount' => '0.9', 'amount' => '0.75'),
			array('id' => 'o-3', 'providerServiceCode' => 'MNG_STANDART'),
			array('id' => '', 'providerServiceCode' => 'SURAT_STANDART', 'totalAmount' => '0.1'),
		),
	);

	pg_assert_same('o-2', pg_label_geliver_offer($offers, '')['id']);

	$offers['list'][1]['totalAmount'] = 'x';
	pg_assert_same('o-2', pg_label_geliver_offer($offers, '')['id'], 'the price without VAT when no total is given');

	unset($offers['list'][1]['amount']);
	pg_assert_same('o-1',pg_label_geliver_offer($offers, '')['id'], 'an offer without a price is never bought');

	pg_assert_same(null, pg_label_geliver_offer(array('cheapest' => null, 'list' => array(array('id' => 'o-3'))), ''));
	pg_assert_same('o-9', pg_label_geliver_offer(array('cheapest' => array('id' => 'o-9'), 'list' => $offers['list']), '')['id'], 'Geliver\'s cheapest first');
}

// The offers of the test shipment that failed on dev: complete after about
// 18 seconds, one offer, no cheapest named.
function test_carrier_label_geliver_create_waits_for_late_offers_without_cheapest()
{
	carrier_label_geliver_test_stub(array(
		'POST /shipments'    => array('shipment_offers_pending'),
		'GET /shipments/4aaaf0b4-d071-4edc-a941-cea2f6a78453' => array_merge(
			array_fill(0, 8, 'shipment_offers_pending'),
			array(array(200, carrier_label_geliver_test_offers_without_cheapest(array('1f26245c-db36-4bce-ac57-6f443847ec4f'))))
		),
		'POST /transactions' => array('transaction_accepted'),
	));

	$answer = pg_label_call('geliver', 'create', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account(array('mode' => 'test'))));
	$sent = $GLOBALS['carrier_label_geliver_test_sent'];
	carrier_label_geliver_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(11, count($sent) - 1, 'one open, nine reads, the purchase (and the label file)');
	pg_assert_same(array('offerID' => '16bc98bb-3d1f-4bc1-8acd-14d2422301e6'), $sent[10]['body']);
}

// Geliver has answered 99 percent with the only offer requested already in.
function test_carrier_label_geliver_offers_complete_by_count()
{
	pg_assert_true(pg_label_geliver_offers_complete(array('percentageCompleted' => 100)));
	pg_assert_true(pg_label_geliver_offers_complete(array('percentageCompleted' => 99, 'totalOffersRequested' => 1, 'totalOffersCompleted' => 1)));
	pg_assert_false(pg_label_geliver_offers_complete(array('percentageCompleted' => 50, 'totalOffersRequested' => 2, 'totalOffersCompleted' => 1)));
	pg_assert_false(pg_label_geliver_offers_complete(array('percentageCompleted' => 0, 'totalOffersRequested' => 0, 'totalOffersCompleted' => 0)));
	pg_assert_false(pg_label_geliver_offers_complete(null));

	$offers = json_decode(carrier_label_geliver_test_offers_without_cheapest(array('1f26245c-db36-4bce-ac57-6f443847ec4f')), true);
	$offers['data']['offers'] = array_merge($offers['data']['offers'], array('percentageCompleted' => 99, 'totalOffersRequested' => 1, 'totalOffersCompleted' => 1));

	carrier_label_geliver_test_stub(array(
		'POST /shipments'    => array(array(200, json_encode($offers))),
		'POST /transactions' => array('transaction_accepted'),
	));

	$answer = pg_label_call('geliver', 'create', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account(array('mode' => 'test'))));
	$sent = carrier_label_geliver_test_sent();
	carrier_label_geliver_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('POST /shipments', 'POST /transactions'), array_slice($sent, 0, 2), 'no read again');
}

// Offers that never arrive end the wait after PG_LABEL_GELIVER_OFFER_READS
// reads and buy nothing.
function test_carrier_label_geliver_create_stops_waiting_for_offers()
{
	carrier_label_geliver_test_stub(array(
		'POST /shipments' => array('shipment_offers_pending'),
		'GET /shipments/4aaaf0b4-d071-4edc-a941-cea2f6a78453' => array_fill(0, 40, 'shipment_offers_pending'),
	));

	$answer = pg_label_call('geliver', 'create', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account()));
	$sent = carrier_label_geliver_test_sent();
	carrier_label_geliver_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_same(1 + PG_LABEL_GELIVER_OFFER_READS, count($sent));
	pg_assert_false(in_array('POST /transactions', $sent, true));
}

function test_carrier_label_geliver_create_without_the_chosen_offer_buys_nothing()
{
	carrier_label_geliver_test_stub(array('POST /shipments' => array('shipment_offers')));

	$answer = pg_label_call('geliver', 'create', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account(array('service' => 'ARAS_STANDART'))));
	$sent = carrier_label_geliver_test_sent();
	carrier_label_geliver_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_same(array('POST /shipments'), $sent);
}

// In test mode the shipment is free and only Geliver's test service offers.
function test_carrier_label_geliver_create_in_test_mode_ignores_the_service()
{
	carrier_label_geliver_test_stub(array(
		'POST /shipments'    => array('shipment_offers'),
		'POST /transactions' => array('transaction_accepted'),
	));

	pg_label_call('geliver', 'create', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account(array('mode' => 'test', 'service' => 'ARAS_STANDART'))));
	$sent = $GLOBALS['carrier_label_geliver_test_sent'];
	carrier_label_geliver_test_done();

	pg_assert_same(true, $sent[0]['body']['test']);
	pg_assert_same(array('offerID' => '16bc98bb-3d1f-4bc1-8acd-14d2422301e6'), $sent[1]['body']);
}

function test_carrier_label_geliver_create_refused_for_balance()
{
	carrier_label_geliver_test_stub(array(
		'POST /shipments'    => array('shipment_offers'),
		'POST /transactions' => array(array(400, carrier_label_geliver_test_fixture('error_balance'))),
	));

	$answer = pg_label_call('geliver', 'create', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account()));
	carrier_label_geliver_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear'], 'Geliver said no');
	pg_assert_contains('E1066', $answer['error']);
}

// The purchase went out and no answer came back: it may have been bought.
function test_carrier_label_geliver_create_without_answer_is_unclear()
{
	foreach (array(array(0, ''), array(502, '<html>Bad gateway</html>')) as $reply) {
		$queue = array('POST /shipments' => array('shipment_offers'));

		if ($reply[0] !== 0) {
			$queue['POST /transactions'] = array($reply);
		}

		carrier_label_geliver_test_stub($queue);

		$answer = pg_label_call('geliver', 'create', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account()));
		carrier_label_geliver_test_done();

		pg_assert_false($answer['success'], 'HTTP ' . $reply[0]);
		pg_assert_true($answer['unclear'], 'HTTP ' . $reply[0]);
	}
}

// A purchase answered without a tracking number or barcode cannot be recorded.
function test_carrier_label_geliver_create_without_tracking_number_is_unclear()
{
	$accepted = json_decode(carrier_label_geliver_test_fixture('transaction_accepted'), true);
	unset($accepted['data']['shipment']['barcode']);

	carrier_label_geliver_test_stub(array(
		'POST /shipments'    => array('shipment_offers'),
		'POST /transactions' => array(array(200, json_encode($accepted))),
	));

	$answer = pg_label_call('geliver', 'create', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account()));
	carrier_label_geliver_test_done();

	pg_assert_false($answer['success']);
	pg_assert_true($answer['unclear']);
}

function test_carrier_label_geliver_label_downloads_the_pdf()
{
	carrier_label_geliver_test_stub(array(
		'GET /shipments/4aaaf0b4-d071-4edc-a941-cea2f6a78453' => array('shipment_delivered'),
		'GET https://labels3.geliver.io/labels/4aaaf0b4-d071-4edc-a941-cea2f6a78453.pdf' => array(array(200, carrier_label_geliver_test_pdf())),
	));

	$answer = pg_label_call('geliver', 'label', array('4aaaf0b4-d071-4edc-a941-cea2f6a78453', 'pdf', carrier_label_geliver_test_account()));
	carrier_label_geliver_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(carrier_label_geliver_test_pdf(), $answer['label']);
	pg_assert_same('64124002705', $answer['barcode']);

	pg_assert_false(pg_label_call('geliver', 'label', array('4aaaf0b4-d071-4edc-a941-cea2f6a78453', 'zpl', carrier_label_geliver_test_account()))['success']);
	pg_assert_false(pg_label_call('geliver', 'label', array('../etc', 'pdf', carrier_label_geliver_test_account()))['success']);
}

// A label address off Geliver's hosts is never fetched.
function test_carrier_label_geliver_label_only_from_geliver_hosts()
{
	$shipment = json_decode(carrier_label_geliver_test_fixture('shipment_delivered'), true);
	$shipment['data']['labelURL'] = 'https://evil.example/geliver.io/label.pdf';

	carrier_label_geliver_test_stub(array('GET /shipments/s-1' => array(array(200, json_encode($shipment)))));

	$answer = pg_label_call('geliver', 'label', array('s-1', 'pdf', carrier_label_geliver_test_account()));
	$sent = carrier_label_geliver_test_sent();
	carrier_label_geliver_test_done();

	pg_assert_false($answer['success']);
	pg_assert_same(array('GET /shipments/s-1'), $sent);
}

function test_carrier_label_geliver_cancel()
{
	carrier_label_geliver_test_stub(array('DELETE /shipments/s-1' => array('shipment_canceled')));
	$answer = pg_label_call('geliver', 'cancel', array('s-1', carrier_label_geliver_test_account()));
	pg_assert_true($answer['success'], $answer['error']);

	carrier_label_geliver_test_stub(array('DELETE /shipments/s-1' => array(array(404, '{"result":false,"code":"E1051","message":"Gönderi bulunamadı."}'))));
	$answer = pg_label_call('geliver', 'cancel', array('s-1', carrier_label_geliver_test_account()));
	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('E1051', $answer['error']);

	carrier_label_geliver_test_stub(array());
	$answer = pg_label_call('geliver', 'cancel', array('s-1', carrier_label_geliver_test_account()));
	carrier_label_geliver_test_done();

	pg_assert_true($answer['unclear'], 'the cancellation went out without an answer');
}

function test_carrier_label_geliver_track_maps_statuses()
{
	carrier_label_geliver_test_stub(array('GET /shipments/s-1' => array('shipment_delivered')));
	$answer = pg_label_call('geliver', 'track', array('s-1', carrier_label_geliver_test_account()));
	carrier_label_geliver_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('delivered', $answer['status']);
	pg_assert_same(gmmktime(14, 29, 45, 4, 23, 2025), $answer['status_at']);
	pg_assert_same('KP123456789TR', $answer['tracking_number']);
	pg_assert_same('ptt', $answer['carrier']);
	pg_assert_same('https://gonderitakip.ptt.gov.tr/Track/summary?id=KP123456789TR', $answer['tracking_url']);

	$cases = array(
		array(array('trackingStatusCode' => 'PRE_TRANSIT', 'trackingSubStatusCode' => 'pickup_scheduled'), '', 'created'),
		array(array('trackingStatusCode' => 'TRANSIT', 'trackingSubStatusCode' => 'package_departed'), '', 'in_transit'),
		array(array('trackingStatusCode' => 'TRANSIT', 'trackingSubStatusCode' => 'out_for_delivery'), '', 'out_for_delivery'),
		array(array('trackingStatusCode' => 'RETURNED', 'trackingSubStatusCode' => 'return_to_sender'), '', 'returned'),
		array(array('trackingStatusCode' => 'FAILURE', 'trackingSubStatusCode' => 'package_lost'), '', 'exception'),
		array(array('trackingStatusCode' => 'CANCELED', 'trackingSubStatusCode' => 'package_canceled'), '', 'exception'),
		array(null, 'SHIPPED', 'in_transit'),
		array(null, 'TRACKING_CODE_CREATED', 'created'),
		array(array('trackingStatusCode' => 'UNKNOWN', 'trackingSubStatusCode' => 'other'), '', ''),
	);

	foreach ($cases as $case) {
		pg_assert_same($case[2], pg_label_geliver_status(array('trackingStatus' => $case[0], 'statusCode' => $case[1])), json_encode($case));
	}
}

function test_carrier_label_geliver_track_without_status_or_answer()
{
	carrier_label_geliver_test_stub(array('GET /shipments/s-1' => array(array(200, '{"result":true,"data":{"id":"s-1","statusCode":"","trackingStatus":{"trackingStatusCode":"UNKNOWN"}}}'))));
	$answer = pg_label_call('geliver', 'track', array('s-1', carrier_label_geliver_test_account()));
	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);

	// A read that was not answered changed nothing.
	carrier_label_geliver_test_stub(array());
	$answer = pg_label_call('geliver', 'track', array('s-1', carrier_label_geliver_test_account()));
	carrier_label_geliver_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('Operation timed out', implode(' ', $answer['log']));
}

// districtName is the recipient's district when one was entered, else the city.
function test_carrier_label_geliver_district_name_is_the_district_else_the_city()
{
	pg_label_load('geliver');
	$shipment = carrier_label_geliver_test_shipment();
	$body = pg_label_geliver_shipment_body($shipment, carrier_label_geliver_test_account());
	pg_assert_same('Yunusemre', $body['recipientAddress']['districtName'], 'no district key');

	$shipment['recipient']['district'] = 'Şehzadeler';
	$body = pg_label_geliver_shipment_body($shipment, carrier_label_geliver_test_account());
	pg_assert_same('Şehzadeler', $body['recipientAddress']['districtName']);
	pg_assert_same('Manisa', $body['recipientAddress']['cityName']);
}

// The price list is national and by parcel only; a service listed twice (the
// account's own contract and Geliver's) is quoted at the lower price.
function test_carrier_label_geliver_rate_reads_the_price_list()
{
	carrier_label_geliver_test_stub(array(
		'GET /priceList?paramType=parcel&length=30&width=20&height=10.5&weight=1.25' => array('price_list'),
	));

	$answer = pg_label_call('geliver', 'rate', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account()));
	carrier_label_geliver_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('GET /priceList?paramType=parcel&length=30&width=20&height=10.5&weight=1.25'), carrier_label_geliver_test_sent());
	pg_assert_same(7, count($answer['quotes']));
	pg_assert_same(array('service' => 'SENDEO_STANDART', 'carrier' => '', 'amount' => 8988, 'currency' => 'TRY'), $answer['quotes'][0]);

	$by_service = array();

	foreach ($answer['quotes'] as $quote) {
		$by_service[$quote['service']] = $quote;
	}

	pg_assert_same(array('service' => 'YURTICI_STANDART', 'carrier' => 'yurtici', 'amount' => 13140, 'currency' => 'TRY'), $by_service['YURTICI_STANDART']);
	pg_assert_same(9900, $by_service['SURAT_STANDART']['amount']);
	pg_assert_same('surat', $by_service['SURAT_STANDART']['carrier']);
}

function test_carrier_label_geliver_rate_quotes_skip_offers_without_a_price()
{
	$quotes = pg_label_geliver_rate_quotes(array('priceList' => array(
		array('desi' => '1', 'offers' => array(
			array('providerServiceCode' => 'PTT_STANDART', 'providerCode' => 'PTT', 'totalAmount' => '50.005', 'currency' => 'TL'),
			array('providerServiceCode' => 'MNG_STANDART', 'totalAmount' => '', 'currency' => 'TL'),
			array('providerServiceCode' => 'ARAS_STANDART', 'totalAmount' => '-3', 'currency' => 'TL'),
			array('totalAmount' => '10', 'currency' => 'TL'),
			'nothing',
		)),
		'nothing',
	)));

	pg_assert_same(array(array('service' => 'PTT_STANDART', 'carrier' => 'ptt', 'amount' => 5001, 'currency' => 'TRY')), $quotes);
	pg_assert_same(array(), pg_label_geliver_rate_quotes(array('data' => array())));
}

function test_carrier_label_geliver_rate_refused_or_empty()
{
	carrier_label_geliver_test_stub(array(
		'GET /priceList?paramType=parcel&length=30&width=20&height=10.5&weight=1.25' => array(
			array(401, carrier_label_geliver_test_fixture('error_token')),
			array(200, '{"result":true,"additionalMessage":"Success","priceList":[]}'),
		),
	));

	$refused = pg_label_call('geliver', 'rate', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account()));
	$empty = pg_label_call('geliver', 'rate', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account()));
	$unreached = pg_label_call('geliver', 'rate', array(carrier_label_geliver_test_shipment(), carrier_label_geliver_test_account()));
	carrier_label_geliver_test_done();

	foreach (array('refused' => $refused, 'empty' => $empty, 'unreached' => $unreached) as $case => $answer) {
		pg_assert_false($answer['success'], $case);
		pg_assert_false($answer['unclear'], $case . ': a price list changes nothing');
		pg_assert_same(array(), $answer['quotes'], $case);
	}

	pg_assert_true(strpos(json_encode($refused['raw']), 'gl-test-token-123456') === false, 'token masked');
}
