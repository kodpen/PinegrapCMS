<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Shippo label driver (includes/shipping/shippo.php), every
 * operation called through pg_label_call(). Shippo is never called: a
 * stand-in transport answers with the answers of tests/fixtures/shippo/,
 * built in the shape of its public reference (docs.goshippo.com), and keeps
 * the requests it was sent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function carrier_label_shippo_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/shippo/' . $name);
}

function carrier_label_shippo_test_account($changes = array())
{
	return array_merge(array(
		'api_token'       => 'shippo_test_5f1a9c0b7e2d4c3b8a6f',
		'label_file_type' => 'PDF_A6',
		'service'         => '',
		'sender_name'     => 'Example Shop',
		'sender_company'  => '',
		'sender_street1'  => 'Friedrichstraße 10',
		'sender_street2'  => '',
		'sender_city'     => 'Berlin',
		'sender_state'    => '',
		'sender_zip'      => '10117',
		'sender_country'  => 'de',
		'sender_phone'    => '+49 30 1234567',
		'sender_email'    => 'shop@example.com',
	), $changes);
}

// Answers each request with the next answer queued for it: "METHOD path"
// (the path without the API base; a label address as host and path, no
// query) => list of array(status, body) or a fixture file name for a 200
// answer. A request with nothing queued times out after it was sent.
function carrier_label_shippo_test_stub($answers)
{
	$GLOBALS['carrier_label_shippo_test_sent'] = array();

	pg_label_shippo_transport(function ($method, $url, $headers, $body) use (&$answers) {
		$path = (strncmp($url, 'https://api.goshippo.com', 24) === 0)
			? substr($url, 24)
			: parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);
		$key = $method . ' ' . $path;
		$GLOBALS['carrier_label_shippo_test_sent'][] = array('request' => $key, 'headers' => $headers, 'body' => ($body === null) ? null : json_decode($body, true));

		if (empty($answers[$key])) {
			return array('status' => 0, 'body' => '', 'error' => 'Operation timed out', 'sent' => true);
		}

		$answer = array_shift($answers[$key]);

		if (is_string($answer)) {
			$answer = array(200, carrier_label_shippo_test_fixture($answer));
		}

		return array('status' => $answer[0], 'body' => $answer[1], 'error' => '', 'sent' => true);
	});
}

function carrier_label_shippo_test_sent()
{
	return array_map(function ($sent) {
		return $sent['request'];
	}, $GLOBALS['carrier_label_shippo_test_sent']);
}

function carrier_label_shippo_test_done()
{
	pg_label_shippo_transport(null, true);
}

function carrier_label_shippo_test_shipment()
{
	return array(
		'order_number' => '10042',
		'recipient'    => array(
			'name'       => 'Jane Doe',
			'email'      => 'jane@example.com',
			'phone'      => '+44 20 7946 0000',
			'address_1'  => '10 Downing Street',
			'address_2'  => 'Flat 2',
			'city'       => 'London',
			'state'      => '',
			'state_code' => '',
			'zip'        => 'SW1A 2AA',
			'country'    => 'gb',
		),
		'parcel'       => array('length' => 30, 'width' => 20, 'height' => 10.5, 'weight' => 1.25),
		'items'        => array(array('title' => 'Mug', 'quantity' => 2)),
		'total'        => 4990,
		'currency'     => 'EUR',
	);
}

function test_carrier_label_shippo_is_a_builtin_driver()
{
	pg_assert_true(pg_label_driver('shippo') !== null);
	pg_assert_same(array('carriers' => array('ups', 'fedex', 'usps', 'dhl', 'deutschepost', 'dpd', 'royalmail'), 'formats' => array('pdf', 'zpl')), pg_label_info('shippo'));

	foreach (array('test', 'create', 'label', 'cancel', 'track') as $operation) {
		pg_assert_true(pg_label_can('shippo', $operation), $operation);
	}

	pg_assert_false(pg_label_can('shippo', 'pickup'));

	$fields = array();

	foreach (pg_label_fields('shippo') as $field) {
		$fields[$field['name']] = $field['type'] . (empty($field['required']) ? '' : ' required');
	}

	pg_assert_same(array(
		'api_token'       => 'secret required',
		'label_file_type' => 'select',
		'service'         => 'text',
		'sender_name'     => 'text required',
		'sender_company'  => 'text',
		'sender_street1'  => 'text required',
		'sender_street2'  => 'text',
		'sender_city'     => 'text required',
		'sender_state'    => 'text',
		'sender_zip'      => 'text required',
		'sender_country'  => 'text required',
		'sender_phone'    => 'text',
		'sender_email'    => 'text',
		'customs_contents'       => 'select',
		'customs_incoterm'       => 'select',
		'customs_non_delivery'   => 'select',
		'customs_hs_code'        => 'text',
		'customs_origin_country' => 'select',
		'customs_signer'         => 'text',
	), $fields);
}

function test_carrier_label_shippo_test_lists_the_carrier_accounts()
{
	carrier_label_shippo_test_stub(array('GET /carrier_accounts?results=50' => array('carrier_accounts.json')));
	$answer = pg_label_call('shippo', 'test', array(carrier_label_shippo_test_account()));
	$sent = $GLOBALS['carrier_label_shippo_test_sent'][0];
	carrier_label_shippo_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('usps, dhl_express', $answer['account'], 'active accounts only');
	pg_assert_contains('test mode', implode(' ', $answer['log']));
	pg_assert_contains('Authorization: ShippoToken shippo_test_5f1a9c0b7e2d4c3b8a6f', $sent['headers']);
	pg_assert_contains('SHIPPO-API-VERSION: 2018-02-08', $sent['headers']);
}

function test_carrier_label_shippo_refused_token_is_masked()
{
	carrier_label_shippo_test_stub(array('GET /carrier_accounts?results=50' => array(array(401, '{"detail": "Token shippo_test_5f1a9c0b7e2d4c3b8a6f does not exist."}'))));
	$answer = pg_label_call('shippo', 'test', array(carrier_label_shippo_test_account()));
	carrier_label_shippo_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_false(strpos(json_encode($answer), 'shippo_test_5f1a9c0b7e2d4c3b8a6f'), 'the token is masked');
	pg_assert_contains('****', $answer['error']);
}

function test_carrier_label_shippo_create_buys_the_cheapest_rate()
{
	carrier_label_shippo_test_stub(array(
		'POST /shipments/'                                => array('shipment.json'),
		'POST /transactions/'                             => array('transaction.json'),
		'GET shippo-delivery-east.s3.amazonaws.com/915d94940ea54c3a80cbfa328722f5a1.pdf' => array('label.pdf'),
	));
	$answer = pg_label_call('shippo', 'create', array(carrier_label_shippo_test_shipment(), carrier_label_shippo_test_account()));
	$sent = $GLOBALS['carrier_label_shippo_test_sent'];
	carrier_label_shippo_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('915d94940ea54c3a80cbfa328722f5a1', $answer['reference']);
	pg_assert_same('01234567890123', $answer['tracking_number']);
	pg_assert_same('dpd', $answer['carrier'], 'DPD DE');
	pg_assert_same('https://tracking.dpd.de/parcelstatus?query=01234567890123&locale=en_DE', $answer['tracking_url']);
	pg_assert_same('created', $answer['status']);
	pg_assert_same('pdf', $answer['label_format']);
	pg_assert_same(0, strpos($answer['label'], '%PDF-'));

	$shipment = $sent[0]['body'];
	pg_assert_same(array('name' => 'Example Shop', 'street1' => 'Friedrichstraße 10', 'city' => 'Berlin', 'zip' => '10117', 'country' => 'DE', 'phone' => '+49 30 1234567', 'email' => 'shop@example.com'), $shipment['address_from']);
	pg_assert_same('GB', $shipment['address_to']['country']);
	pg_assert_same('Flat 2', $shipment['address_to']['street2']);
	pg_assert_same(array('length' => '30', 'width' => '20', 'height' => '10.5', 'distance_unit' => 'cm', 'weight' => '1.25', 'mass_unit' => 'kg'), $shipment['parcels'][0]);
	pg_assert_same(array('reference_1' => '10042'), $shipment['extra']);
	pg_assert_false($shipment['async']);

	pg_assert_same(array('rate' => 'a7e5b9c39c3b4b9a8e0a4b5b7c5d6e1f', 'label_file_type' => 'PDF_A6', 'async' => false, 'metadata' => 'Order 10042'), $sent[1]['body']);
	pg_assert_false(strpos(implode("\n", $sent[2]['headers']), 'ShippoToken'), 'the label address is fetched without the token');
}

function test_carrier_label_shippo_create_takes_the_asked_service_and_zpl()
{
	carrier_label_shippo_test_stub(array(
		'POST /shipments/'                                => array('shipment.json'),
		'POST /transactions/'                             => array('transaction_zpl.json'),
		'GET shippo-delivery-east.s3.amazonaws.com/915d94940ea54c3a80cbfa328722f5a1.zpl' => array('label.zpl'),
	));
	$shipment = carrier_label_shippo_test_shipment();
	$shipment['return'] = true;
	$answer = pg_label_call('shippo', 'create', array($shipment, carrier_label_shippo_test_account(array('service' => 'DHL_Express_Worldwide', 'label_file_type' => 'ZPLII'))));
	$sent = $GLOBALS['carrier_label_shippo_test_sent'];
	carrier_label_shippo_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('zpl', $answer['label_format']);
	pg_assert_contains('^XA', $answer['label']);
	pg_assert_same(true, $sent[0]['body']['extra']['is_return'], 'a return label');
	pg_assert_same('545ab0a1a6ea4c9f9adb2512a57d6d8b', $sent[1]['body']['rate'], 'the asked service level');
	pg_assert_same('ZPLII', $sent[1]['body']['label_file_type']);
}

function test_carrier_label_shippo_refusals_are_plain()
{
	// No rate: nothing is bought.
	carrier_label_shippo_test_stub(array('POST /shipments/' => array('shipment_no_rates.json')));
	$none = pg_label_call('shippo', 'create', array(carrier_label_shippo_test_shipment(), carrier_label_shippo_test_account()));
	$sent = carrier_label_shippo_test_sent();
	carrier_label_shippo_test_done();

	pg_assert_false($none['success']);
	pg_assert_false($none['unclear']);
	pg_assert_contains('Destination postal code is not valid.', $none['error']);
	pg_assert_same(array('POST /shipments/'), $sent);

	// The carrier refused the label: Shippo charges nothing for it.
	carrier_label_shippo_test_stub(array('POST /shipments/' => array('shipment.json'), 'POST /transactions/' => array('transaction_error.json')));
	$error = pg_label_call('shippo', 'create', array(carrier_label_shippo_test_shipment(), carrier_label_shippo_test_account()));
	carrier_label_shippo_test_done();

	pg_assert_false($error['success']);
	pg_assert_false($error['unclear']);
	pg_assert_contains('postal code does not match', $error['error']);

	// A refused request names the field.
	carrier_label_shippo_test_stub(array('POST /shipments/' => array(array(400, '{"address_to": {"zip": ["This field is required."]}}'))));
	$bad = pg_label_call('shippo', 'create', array(carrier_label_shippo_test_shipment(), carrier_label_shippo_test_account()));
	carrier_label_shippo_test_done();

	pg_assert_false($bad['success']);
	pg_assert_false($bad['unclear'], 'a shipment costs nothing');
	pg_assert_contains('address_to.zip: This field is required.', $bad['error']);

	// No country: refused before a call.
	carrier_label_shippo_test_stub(array());
	$shipment = carrier_label_shippo_test_shipment();
	$shipment['recipient']['country'] = '';
	$nowhere = pg_label_call('shippo', 'create', array($shipment, carrier_label_shippo_test_account()));
	pg_assert_same(array(), carrier_label_shippo_test_sent());
	carrier_label_shippo_test_done();
	pg_assert_false($nowhere['success']);
}

function test_carrier_label_shippo_unclear_answers()
{
	// The purchase went out and no answer came.
	carrier_label_shippo_test_stub(array('POST /shipments/' => array('shipment.json')));
	$lost = pg_label_call('shippo', 'create', array(carrier_label_shippo_test_shipment(), carrier_label_shippo_test_account()));
	carrier_label_shippo_test_done();

	pg_assert_false($lost['success']);
	pg_assert_true($lost['unclear']);

	// A server error on the purchase.
	carrier_label_shippo_test_stub(array('POST /shipments/' => array('shipment.json'), 'POST /transactions/' => array(array(502, '<html>Bad Gateway</html>'))));
	$down = pg_label_call('shippo', 'create', array(carrier_label_shippo_test_shipment(), carrier_label_shippo_test_account()));
	carrier_label_shippo_test_done();

	pg_assert_false($down['success']);
	pg_assert_true($down['unclear']);

	// Still queued after asking again: it may yet be bought.
	carrier_label_shippo_test_stub(array(
		'POST /shipments/'                                 => array('shipment.json'),
		'POST /transactions/'                              => array('transaction_queued.json'),
		'GET /transactions/915d94940ea54c3a80cbfa328722f5a1' => array('transaction_queued.json', 'transaction_queued.json', 'transaction_queued.json'),
	));
	$queued = pg_label_call('shippo', 'create', array(carrier_label_shippo_test_shipment(), carrier_label_shippo_test_account()));
	$waits = pg_label_shippo_waits();
	carrier_label_shippo_test_done();

	pg_assert_false($queued['success']);
	pg_assert_true($queued['unclear']);
	pg_assert_same(array(2, 2, 2), $waits, 'waits recorded, not slept');

	// Queued, then made.
	carrier_label_shippo_test_stub(array(
		'POST /shipments/'                                 => array('shipment.json'),
		'POST /transactions/'                              => array('transaction_queued.json'),
		'GET /transactions/915d94940ea54c3a80cbfa328722f5a1' => array('transaction.json'),
		'GET shippo-delivery-east.s3.amazonaws.com/915d94940ea54c3a80cbfa328722f5a1.pdf' => array(array(403, 'AccessDenied')),
	));
	$made = pg_label_call('shippo', 'create', array(carrier_label_shippo_test_shipment(), carrier_label_shippo_test_account()));
	carrier_label_shippo_test_done();

	pg_assert_true($made['success'], $made['error']);
	pg_assert_same('', $made['label'], 'a label that cannot be read does not fail the purchase');
}

function test_carrier_label_shippo_label_reads_the_transaction()
{
	carrier_label_shippo_test_stub(array(
		'GET /transactions/915d94940ea54c3a80cbfa328722f5a1' => array('transaction.json', 'transaction.json', 'transaction_refunded.json'),
		'GET shippo-delivery-east.s3.amazonaws.com/915d94940ea54c3a80cbfa328722f5a1.pdf' => array('label.pdf'),
	));
	$pdf = pg_label_call('shippo', 'label', array('915d94940ea54c3a80cbfa328722f5a1', 'pdf', carrier_label_shippo_test_account()));
	$zpl = pg_label_call('shippo', 'label', array('915d94940ea54c3a80cbfa328722f5a1', 'zpl', carrier_label_shippo_test_account()));
	$refunded = pg_label_call('shippo', 'label', array('915d94940ea54c3a80cbfa328722f5a1', 'pdf', carrier_label_shippo_test_account()));
	$bad = pg_label_call('shippo', 'label', array('../carrier_accounts', 'pdf', carrier_label_shippo_test_account()));
	$sent = carrier_label_shippo_test_sent();
	carrier_label_shippo_test_done();

	pg_assert_true($pdf['success'], $pdf['error']);
	pg_assert_same('pdf', $pdf['label_format']);
	pg_assert_false($zpl['success'], 'the label was made as a PDF');
	pg_assert_false($refunded['success']);
	pg_assert_false($bad['success']);
	pg_assert_same(4, count($sent), 'nothing sent for a reference that is no id');
}

function test_carrier_label_shippo_label_address_off_shippo_hosts_is_not_fetched()
{
	$transaction = json_decode(carrier_label_shippo_test_fixture('transaction.json'), true);
	$transaction['label_url'] = 'https://evil.example/label.pdf';

	carrier_label_shippo_test_stub(array('GET /transactions/915d94940ea54c3a80cbfa328722f5a1' => array(array(200, json_encode($transaction)))));
	$answer = pg_label_call('shippo', 'label', array('915d94940ea54c3a80cbfa328722f5a1', 'pdf', carrier_label_shippo_test_account()));
	$sent = carrier_label_shippo_test_sent();
	carrier_label_shippo_test_done();

	pg_assert_false($answer['success']);
	pg_assert_same(array('GET /transactions/915d94940ea54c3a80cbfa328722f5a1'), $sent);

	foreach (array('https://shippo-delivery.s3.amazonaws.com/a.pdf', 'https://shippo-delivery-east.s3.amazonaws.com/a.pdf', 'https://deliver.goshippo.com/a.pdf') as $url) {
		pg_assert_true(pg_label_shippo_label_host($url), $url);
	}

	foreach (array('http://shippo-delivery.s3.amazonaws.com/a.pdf', 'https://evil.s3.amazonaws.com/a.pdf', 'https://goshippo.com.evil.example/a.pdf', 'https://shippo-delivery.s3.amazonaws.com.evil.example/a.pdf') as $url) {
		pg_assert_false(pg_label_shippo_label_host($url), $url);
	}
}

function test_carrier_label_shippo_cancel_asks_for_a_refund()
{
	carrier_label_shippo_test_stub(array('POST /refunds/' => array('refund.json')));
	$answer = pg_label_call('shippo', 'cancel', array('915d94940ea54c3a80cbfa328722f5a1', carrier_label_shippo_test_account()));
	$sent = $GLOBALS['carrier_label_shippo_test_sent'];
	carrier_label_shippo_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('transaction' => '915d94940ea54c3a80cbfa328722f5a1', 'async' => false), $sent[0]['body']);
	pg_assert_contains('QUEUED', implode(' ', $answer['log']));

	// Refused (used, or refunded already).
	carrier_label_shippo_test_stub(array('POST /refunds/' => array(array(400, '{"transaction": ["Transaction has already been refunded."]}'))));
	$refused = pg_label_call('shippo', 'cancel', array('915d94940ea54c3a80cbfa328722f5a1', carrier_label_shippo_test_account()));
	carrier_label_shippo_test_done();

	pg_assert_false($refused['success']);
	pg_assert_false($refused['unclear']);
	pg_assert_contains('already been refunded', $refused['error']);

	// An ERROR refund.
	carrier_label_shippo_test_stub(array('POST /refunds/' => array(array(201, '{"object_id": "b1c2", "status": "ERROR", "transaction": "915d94940ea54c3a80cbfa328722f5a1"}'))));
	$error = pg_label_call('shippo', 'cancel', array('915d94940ea54c3a80cbfa328722f5a1', carrier_label_shippo_test_account()));
	carrier_label_shippo_test_done();

	pg_assert_false($error['success']);

	// No answer: it may have been refunded.
	carrier_label_shippo_test_stub(array());
	$lost = pg_label_call('shippo', 'cancel', array('915d94940ea54c3a80cbfa328722f5a1', carrier_label_shippo_test_account()));
	carrier_label_shippo_test_done();

	pg_assert_false($lost['success']);
	pg_assert_true($lost['unclear']);
}

function test_carrier_label_shippo_track_reads_the_transaction_status()
{
	carrier_label_shippo_test_stub(array('GET /transactions/915d94940ea54c3a80cbfa328722f5a1' => array('transaction_transit.json', 'transaction.json')));
	$transit = pg_label_call('shippo', 'track', array('915d94940ea54c3a80cbfa328722f5a1', carrier_label_shippo_test_account()));
	$unknown = pg_label_call('shippo', 'track', array('915d94940ea54c3a80cbfa328722f5a1', carrier_label_shippo_test_account()));
	carrier_label_shippo_test_done();

	pg_assert_true($transit['success'], $transit['error']);
	pg_assert_same('in_transit', $transit['status']);
	pg_assert_same('01234567890123', $transit['tracking_number']);
	pg_assert_false($unknown['success'], 'UNKNOWN is no status');

	pg_assert_same('created', pg_label_shippo_status('PRE_TRANSIT'));
	pg_assert_same('delivered', pg_label_shippo_status(array('status' => 'DELIVERED')));
	pg_assert_same('out_for_delivery', pg_label_shippo_status(array('status' => 'TRANSIT', 'substatus' => array('code' => 'out_for_delivery'))));
	pg_assert_same('returned', pg_label_shippo_status('RETURNED'));
	pg_assert_same('exception', pg_label_shippo_status('FAILURE'));
	pg_assert_same('', pg_label_shippo_status('UNKNOWN'));
}

function test_carrier_label_shippo_carrier_of_a_provider()
{
	$providers = array(
		'USPS' => 'usps', 'UPS' => 'ups', 'FedEx' => 'fedex', 'DHL Express' => 'dhl', 'DHL Germany' => 'dhl',
		'Deutsche Post' => 'deutschepost', 'DPD UK' => 'dpd', 'dpd_de' => 'dpd', 'Royal Mail' => 'royalmail',
		'Evri UK' => '', 'Colissimo' => '', '' => '',
	);

	foreach ($providers as $provider => $carrier) {
		pg_assert_same($carrier, pg_label_shippo_carrier($provider), $provider);
	}
}

// Order lines as pg_shipment_label_shipment() gives them for customs: the
// unit price in minor units, the unit weight in kg.
function carrier_label_shippo_test_goods()
{
	return array(
		array('name' => 'Kaffeebecher „Berlin“', 'quantity' => 2, 'price' => 1249.5, 'weight' => 0.4, 'hs_code' => '6912.00', 'origin_country' => 'PT'),
		array('name' => 'Untersetzer', 'quantity' => 1, 'price' => 1, 'weight' => 0, 'hs_code' => '', 'origin_country' => ''),
	);
}

// A parcel from Germany to Great Britain carries the declaration of
// tests/fixtures/shippo/customs_declaration.json with the shipment: the
// product's values first, the shop defaults for the line with none, total
// value and net weight per line.
function test_carrier_label_shippo_create_declares_customs()
{
	carrier_label_shippo_test_stub(array(
		'POST /shipments/'    => array('shipment.json'),
		'POST /transactions/' => array('transaction.json'),
		'GET shippo-delivery-east.s3.amazonaws.com/915d94940ea54c3a80cbfa328722f5a1.pdf' => array('label.pdf'),
	));
	$shipment = carrier_label_shippo_test_shipment();
	$shipment['goods'] = carrier_label_shippo_test_goods();
	$account = carrier_label_shippo_test_account(array(
		'customs_contents'       => 'merchandise',
		'customs_incoterm'       => 'DDU',
		'customs_non_delivery'   => 'return',
		'customs_hs_code'        => '4823.90',
		'customs_origin_country' => 'DE',
		'customs_signer'         => '',
	));
	$answer = pg_label_call('shippo', 'create', array($shipment, $account));
	$sent = $GLOBALS['carrier_label_shippo_test_sent'];
	carrier_label_shippo_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(json_decode(carrier_label_shippo_test_fixture('customs_declaration.json'), true), $sent[0]['body']['customs_declaration']);

	// A return is declared as returned goods; the shop's choices are mapped.
	$shipment['return'] = true;
	$customs = pg_label_shippo_customs($shipment, array_merge($account, array('customs_incoterm' => 'DAP', 'customs_non_delivery' => 'abandon', 'customs_signer' => 'Jane Roe')));

	pg_assert_same('RETURN_MERCHANDISE', $customs['declaration']['contents_type']);
	pg_assert_same('DAP', $customs['declaration']['incoterm']);
	pg_assert_same('ABANDON', $customs['declaration']['non_delivery_option']);
	pg_assert_same('Jane Roe', $customs['declaration']['certify_signer']);
}

// A line with no HS code anywhere refuses the label before Shippo is asked.
function test_carrier_label_shippo_create_refuses_a_line_without_hs_code()
{
	carrier_label_shippo_test_stub(array());
	$shipment = carrier_label_shippo_test_shipment();
	$shipment['goods'] = carrier_label_shippo_test_goods();
	$answer = pg_label_call('shippo', 'create', array($shipment, carrier_label_shippo_test_account(array('customs_origin_country' => 'DE'))));
	$sent = carrier_label_shippo_test_sent();
	carrier_label_shippo_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false(!empty($answer['unclear']), 'nothing was bought');
	pg_assert_contains('Untersetzer', $answer['error']);
	pg_assert_same(array(), $sent, 'no request');
}

// A domestic or EU route, or a shop that has filled nothing yet, sends the
// shipment as before: no declaration.
function test_carrier_label_shippo_routes_without_customs_are_unchanged()
{
	$filled = carrier_label_shippo_test_account(array('customs_hs_code' => '6912', 'customs_origin_country' => 'DE'));
	$shipment = carrier_label_shippo_test_shipment();
	$shipment['goods'] = carrier_label_shippo_test_goods();

	foreach (array('DE' => 'domestic', 'FR' => 'within the EU') as $country => $route) {
		$shipment['recipient']['country'] = $country;
		pg_assert_same(array('declaration' => null, 'error' => ''), pg_label_shippo_customs($shipment, $filled), $route);
	}

	$shipment['recipient']['country'] = 'GB';
	$plain = carrier_label_shippo_test_goods();
	$plain[0]['hs_code'] = '';
	$plain[0]['origin_country'] = '';
	$shipment['goods'] = $plain;
	pg_assert_same(array('declaration' => null, 'error' => ''), pg_label_shippo_customs($shipment, carrier_label_shippo_test_account()), 'nothing filled yet');

	carrier_label_shippo_test_stub(array(
		'POST /shipments/'    => array('shipment.json'),
		'POST /transactions/' => array('transaction.json'),
		'GET shippo-delivery-east.s3.amazonaws.com/915d94940ea54c3a80cbfa328722f5a1.pdf' => array('label.pdf'),
	));
	$shipment['recipient']['country'] = 'DE';
	$shipment['goods'] = carrier_label_shippo_test_goods();
	pg_label_call('shippo', 'create', array($shipment, $filled));
	$sent = $GLOBALS['carrier_label_shippo_test_sent'];
	carrier_label_shippo_test_done();

	pg_assert_false(array_key_exists('customs_declaration', $sent[0]['body']), 'a domestic shipment carries none');
}
