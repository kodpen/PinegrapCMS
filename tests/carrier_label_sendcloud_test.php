<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sendcloud label driver (includes/shipping/sendcloud.php),
 * every operation called through pg_label_call(). Sendcloud is never
 * called: a stand-in transport answers with the answers of
 * tests/fixtures/sendcloud/, built in the shape of its public reference
 * (sendcloud.dev, API v3), and keeps the requests it was sent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function carrier_label_sendcloud_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/sendcloud/' . $name);
}

function carrier_label_sendcloud_test_account($changes = array())
{
	return array_merge(array(
		'public_key'     => 'pk_4b1f0c9e7a2d43b8',
		'secret_key'     => 'sk_9d3e6a1c5f7b4e20',
		'mode'           => 'live',
		'sender_address' => '',
		'service'        => '',
		'label_format'   => 'pdf',
	), $changes);
}

// Answers each request with the next answer queued for it: "METHOD path"
// (the path under the API base) => list of array(status, body) or a
// fixture file name for a 200 answer. A request with nothing queued times
// out after it was sent.
function carrier_label_sendcloud_test_stub($answers)
{
	$GLOBALS['carrier_label_sendcloud_test_sent'] = array();

	pg_label_sendcloud_transport(function ($method, $url, $headers, $body) use (&$answers) {
		$key = $method . ' ' . substr($url, strlen('https://panel.sendcloud.sc/api/v3'));
		$GLOBALS['carrier_label_sendcloud_test_sent'][] = array('request' => $key, 'headers' => $headers, 'body' => ($body === null) ? null : json_decode($body, true));

		if (empty($answers[$key])) {
			return array('status' => 0, 'body' => '', 'error' => 'Operation timed out', 'sent' => true);
		}

		$answer = array_shift($answers[$key]);

		if (is_string($answer)) {
			$answer = array(200, carrier_label_sendcloud_test_fixture($answer));
		}

		return array('status' => $answer[0], 'body' => $answer[1], 'error' => '', 'sent' => true);
	});
}

function carrier_label_sendcloud_test_sent()
{
	return array_map(function ($sent) {
		return $sent['request'];
	}, $GLOBALS['carrier_label_sendcloud_test_sent']);
}

function carrier_label_sendcloud_test_done()
{
	pg_label_sendcloud_transport(null, true);
}

function carrier_label_sendcloud_test_shipment($changes = array())
{
	return array_merge(array(
		'order_number' => '10042',
		'recipient'    => array(
			'name'       => 'Jana Müller',
			'email'      => 'jana@example.com',
			'phone'      => '+49 30 1234567',
			'address_1'  => 'Friedrichstraße 10',
			'address_2'  => 'Hinterhaus',
			'city'       => 'Berlin',
			'state'      => 'Berlin',
			'state_code' => '',
			'zip'        => '10117',
			'country'    => 'de',
		),
		'parcel'       => array('length' => 30, 'width' => 20, 'height' => 10.5, 'weight' => 1.25),
		'items'        => array(array('title' => 'Mug', 'quantity' => 2)),
		'total'        => 4990,
		'currency'     => 'eur',
	), $changes);
}

function test_carrier_label_sendcloud_is_a_builtin_driver()
{
	pg_assert_true(pg_label_driver('sendcloud') !== null);
	pg_assert_same(array('carriers' => array('ups', 'fedex', 'dhl', 'deutschepost', 'dpd', 'royalmail'), 'formats' => array('pdf', 'zpl')), pg_label_info('sendcloud'));

	foreach (array('test', 'create', 'label', 'cancel', 'track') as $operation) {
		pg_assert_true(pg_label_can('sendcloud', $operation), $operation);
	}

	pg_assert_false(pg_label_can('sendcloud', 'pickup'));

	$fields = array();

	foreach (pg_label_fields('sendcloud') as $field) {
		$fields[$field['name']] = $field['type'] . (empty($field['required']) ? '' : ' required');
	}

	pg_assert_same(array(
		'public_key'     => 'secret required',
		'secret_key'     => 'secret required',
		'mode'           => 'select',
		'sender_address' => 'text',
		'service'        => 'text',
		'label_format'   => 'select',
		'customs_contents'       => 'select',
		'customs_incoterm'       => 'select',
		'customs_non_delivery'   => 'select',
		'customs_hs_code'        => 'text',
		'customs_origin_country' => 'select',
		'customs_signer'         => 'text',
	), $fields, 'the shared customs block after the driver\'s own fields');
}

function test_carrier_label_sendcloud_test_reads_the_sender_address()
{
	carrier_label_sendcloud_test_stub(array('GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json', 'sender_addresses.json', 'sender_addresses.json')));
	$answer = pg_label_call('sendcloud', 'test', array(carrier_label_sendcloud_test_account(array('mode' => 'test'))));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'][0];
	$chosen = pg_label_call('sendcloud', 'test', array(carrier_label_sendcloud_test_account(array('sender_address' => '193'))));
	$missing = pg_label_call('sendcloud', 'test', array(carrier_label_sendcloud_test_account(array('sender_address' => '7'))));
	carrier_label_sendcloud_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('Example Shop, Eindhoven NL', $answer['account'], 'the first address when none is named');
	pg_assert_contains('test mode', implode(' ', $answer['log']));
	pg_assert_contains('Authorization: Basic ' . base64_encode('pk_4b1f0c9e7a2d43b8:sk_9d3e6a1c5f7b4e20'), $sent['headers']);
	pg_assert_same('Example Shop UK, London GB', $chosen['account']);
	pg_assert_false($missing['success']);
	pg_assert_contains('sender address', $missing['error']);
}

function test_carrier_label_sendcloud_refused_keys_are_masked()
{
	carrier_label_sendcloud_test_stub(array('GET /addresses/sender-addresses?page_size=100' => array(array(401, '{"errors": [{"status": "401", "code": "authentication_failed", "detail": "Invalid key pk_4b1f0c9e7a2d43b8."}]}'))));
	$answer = pg_label_call('sendcloud', 'test', array(carrier_label_sendcloud_test_account()));
	carrier_label_sendcloud_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_false(strpos(json_encode($answer), 'pk_4b1f0c9e7a2d43b8'), 'the key is masked');
	pg_assert_contains('Sendcloud refused the request.', $answer['error']);
	pg_assert_contains('****', $answer['error']);
}

function test_carrier_label_sendcloud_create_announces_the_cheapest_option()
{
	carrier_label_sendcloud_test_stub(array(
		'GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json'),
		'POST /shipping-options'                         => array('shipping_options.json'),
		'POST /shipments/announce'                       => array('shipment.json'),
	));
	$answer = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(), carrier_label_sendcloud_test_account()));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	carrier_label_sendcloud_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('GET /addresses/sender-addresses?page_size=100', 'POST /shipping-options', 'POST /shipments/announce'), carrier_label_sendcloud_test_sent());
	pg_assert_same('f3a1c2d4-5b6e-4f70-8a9b-0c1d2e3f4a5b', $answer['reference']);
	pg_assert_same('05212345678901', $answer['tracking_number']);
	pg_assert_same('dpd', $answer['carrier']);
	pg_assert_same('https://tracking.eu-central-1-0.sendcloud.sc/forward?carrier=dpd&code=05212345678901&destination=DE&lang=en-us', $answer['tracking_url']);
	pg_assert_same('created', $answer['status']);
	pg_assert_same('pdf', $answer['label_format']);
	pg_assert_same(0, strpos($answer['label'], '%PDF-'), 'the label file of the answer');
	pg_assert_false(strpos(json_encode($answer['raw']), 'JVBERi0'), 'the base64 label is not logged');

	$parcel = array('dimensions' => array('length' => '30.00', 'width' => '20.00', 'height' => '10.50', 'unit' => 'cm'), 'weight' => array('value' => '1.250', 'unit' => 'kg'));
	pg_assert_same(array(
		'from_address'     => array('country_code' => 'NL', 'postal_code' => '5611 EM'),
		'to_address'       => array('country_code' => 'DE', 'postal_code' => '10117'),
		'parcels'          => array($parcel),
		'calculate_quotes' => true,
	), $sent[1]['body']);

	pg_assert_same(array(
		'label_details'     => array('mime_type' => 'application/pdf', 'dpi' => 72),
		'to_address'        => array(
			'name'           => 'Jana Müller',
			'address_line_1' => 'Friedrichstraße 10',
			'address_line_2' => 'Hinterhaus',
			'postal_code'    => '10117',
			'city'           => 'Berlin',
			'country_code'   => 'DE',
			'phone_number'   => '+49 30 1234567',
			'email'          => 'jana@example.com',
		),
		'from_address'      => array('sender_address_id' => 192),
		'ship_with'         => array('type' => 'shipping_option_code', 'properties' => array('shipping_option_code' => 'dpd:home')),
		'parcels'           => array($parcel),
		'order_number'      => '10042',
		'total_order_price' => array('currency' => 'EUR', 'value' => '49.90'),
	), $sent[2]['body'], 'the letter, a service point, export documents and an unpriced option are passed over');
}

function test_carrier_label_sendcloud_test_mode_ships_an_unstamped_letter()
{
	carrier_label_sendcloud_test_stub(array(
		'GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json'),
		'POST /shipments/announce'                       => array('shipment.json'),
	));
	$answer = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(array('service' => 'postnl:standard')), carrier_label_sendcloud_test_account(array('mode' => 'test'))));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	carrier_label_sendcloud_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('sendcloud:letter', $sent[1]['body']['ship_with']['properties']['shipping_option_code'], 'no service is taken in test mode');
	pg_assert_contains('(test)', implode(' ', $answer['log']));
}

function test_carrier_label_sendcloud_create_takes_the_asked_option_and_zpl()
{
	carrier_label_sendcloud_test_stub(array(
		'GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json', 'sender_addresses.json'),
		'POST /shipments/announce'                       => array('shipment_get.json', 'shipment_get.json'),
		'GET /parcels/383707309/documents/label'        => array('label.zpl'),
	));
	$account = carrier_label_sendcloud_test_account(array('service' => 'postnl:standard', 'label_format' => 'zpl', 'sender_address' => '192'));
	$answer = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(array('service' => 'dpd:home')), $account));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	$account_option = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(), $account));
	$second = $GLOBALS['carrier_label_sendcloud_test_sent'];
	carrier_label_sendcloud_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('dpd:home', $sent[1]['body']['ship_with']['properties']['shipping_option_code'], 'the shipment\'s service wins');
	pg_assert_same(array('mime_type' => 'application/zpl', 'dpi' => 203), $sent[1]['body']['label_details']);
	pg_assert_same('GET /parcels/383707309/documents/label', $sent[2]['request'], 'no label file in the answer: fetched');
	pg_assert_contains('Accept: application/zpl', $sent[2]['headers']);
	pg_assert_same('zpl', $answer['label_format']);
	pg_assert_contains('^XA', $answer['label']);

	pg_assert_true($account_option['success'], 'a failed download does not fail create');
	pg_assert_same('postnl:standard', $second[4]['body']['ship_with']['properties']['shipping_option_code'], 'else the account\'s');
	pg_assert_same('', $account_option['label']);
	pg_assert_contains('could not be read', implode(' ', $account_option['log']));
}

function test_carrier_label_sendcloud_refusals_before_a_call()
{
	carrier_label_sendcloud_test_stub(array());
	$return = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(array('return' => true)), carrier_label_sendcloud_test_account(array('mode' => 'test'))));
	$nowhere = carrier_label_sendcloud_test_shipment();
	$nowhere['recipient']['country'] = '';
	$no_country = pg_label_call('sendcloud', 'create', array($nowhere, carrier_label_sendcloud_test_account()));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	carrier_label_sendcloud_test_done();

	pg_assert_false($return['success']);
	pg_assert_false($return['unclear']);
	pg_assert_contains('live mode', $return['error'], 'a return is never bought in test mode');
	pg_assert_false($no_country['success']);
	pg_assert_same(array(), $sent);

	pg_assert_same('', pg_label_sendcloud_route_error('nl', 'de'));
	pg_assert_same('', pg_label_sendcloud_route_error('GB', 'GB'));
	pg_assert_same('', pg_label_sendcloud_route_error('FR', 'FR'));
	pg_assert_contains('customs', pg_label_sendcloud_route_error('NL', 'GB'));
	pg_assert_contains('customs', pg_label_sendcloud_route_error('GB', 'FR'));
	pg_assert_contains('customs', pg_label_sendcloud_route_error('DE', 'CH'));
	pg_assert_contains('customs', pg_label_sendcloud_route_error('DE', 'US'));
	pg_assert_contains('does not ship from TR', pg_label_sendcloud_route_error('TR', 'TR'));

	pg_assert_same('', pg_label_sendcloud_route_error('DE', 'NL', true));
	pg_assert_same('', pg_label_sendcloud_route_error('PL', 'NL', true), 'a return from any EU country to the shop');
	pg_assert_contains('A return from CH to DE needs a customs declaration', pg_label_sendcloud_route_error('CH', 'DE', true));
	pg_assert_contains('A return from FR to GB needs a customs declaration', pg_label_sendcloud_route_error('FR', 'GB', true));
	pg_assert_contains('does not ship from TR', pg_label_sendcloud_route_error('DE', 'TR', true), 'the shop\'s country is the one checked');
}

function test_carrier_label_sendcloud_parcel_outside_the_eu_is_refused_after_the_sender()
{
	$shipment = carrier_label_sendcloud_test_shipment();
	$shipment['recipient']['country'] = 'CH';
	carrier_label_sendcloud_test_stub(array('GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json')));
	$answer = pg_label_call('sendcloud', 'create', array($shipment, carrier_label_sendcloud_test_account()));
	carrier_label_sendcloud_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('A parcel from NL to CH needs a customs declaration', $answer['error']);
	pg_assert_same(array('GET /addresses/sender-addresses?page_size=100'), carrier_label_sendcloud_test_sent(), 'nothing is priced or announced');
}

function test_carrier_label_sendcloud_failures_and_unclear_answers()
{
	$account = carrier_label_sendcloud_test_account(array('service' => 'dpd:home'));
	$senders = array('sender_addresses.json', 'sender_addresses.json', 'sender_addresses.json', 'sender_addresses.json', 'sender_addresses.json');

	carrier_label_sendcloud_test_stub(array(
		'GET /addresses/sender-addresses?page_size=100' => $senders,
		'POST /shipments/announce'                       => array(
			'shipment_failed.json',
			array(400, '{"errors": [{"detail": "This field is required.", "status": "400", "source": {"pointer": "/to_address/name"}, "code": "required"}]}'),
			array(502, '<html>Bad gateway</html>'),
		),
	));
	$failed = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(), $account));
	$invalid = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(), $account));
	$server = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(), $account));
	$lost = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(), $account));
	carrier_label_sendcloud_test_done();

	pg_assert_false($failed['success']);
	pg_assert_false($failed['unclear'], 'a failed announcement is not charged');
	pg_assert_contains('Sendcloud could not make the label. (Service error: An error occurred while connecting to the carrier.)', $failed['error']);

	pg_assert_false($invalid['unclear']);
	pg_assert_contains('(to_address.name: This field is required.)', $invalid['error']);

	pg_assert_true($server['unclear'], 'a server error after the announcement went out');
	pg_assert_true($lost['unclear'], 'no answer after the announcement went out');
	pg_assert_same('The carrier label service could not be reached.', $lost['error']);

	carrier_label_sendcloud_test_stub(array(
		'GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json'),
		'POST /shipping-options'                         => array(array(200, '{"data": [], "message": "We couldn\'t find any shipping options for this request."}')),
	));
	$none = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(), carrier_label_sendcloud_test_account()));
	carrier_label_sendcloud_test_done();

	pg_assert_false($none['success']);
	pg_assert_false($none['unclear']);
	pg_assert_contains('Sendcloud found no shipping option for this shipment. (We couldn', $none['error']);
}

function test_carrier_label_sendcloud_label_reads_the_parcel_document()
{
	$id = 'f3a1c2d4-5b6e-4f70-8a9b-0c1d2e3f4a5b';
	carrier_label_sendcloud_test_stub(array(
		'GET /shipments/' . $id                            => array('shipment_get.json', 'shipment_cancelled.json'),
		'GET /parcels/383707309/documents/label?paper_size=a6' => array('label.pdf'),
	));
	$answer = pg_label_call('sendcloud', 'label', array($id, 'pdf', carrier_label_sendcloud_test_account()));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	$cancelled = pg_label_call('sendcloud', 'label', array($id, 'pdf', carrier_label_sendcloud_test_account()));
	$bad = pg_label_call('sendcloud', 'label', array('../user', 'pdf', carrier_label_sendcloud_test_account()));
	carrier_label_sendcloud_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(0, strpos($answer['label'], '%PDF-'));
	pg_assert_contains('Accept: application/pdf', $sent[1]['headers']);
	pg_assert_false($cancelled['success']);
	pg_assert_contains('cancelled', $cancelled['error']);
	pg_assert_false($bad['success']);
}

function test_carrier_label_sendcloud_cancel()
{
	$id = 'f3a1c2d4-5b6e-4f70-8a9b-0c1d2e3f4a5b';
	carrier_label_sendcloud_test_stub(array(
		'POST /shipments/' . $id . '/cancel' => array(
			array(202, '{"data": {"status": "queued", "message": "Shipment cancellation has been queued"}}'),
			array(409, '{"errors": [{"status": "409", "code": "invalid", "detail": "This shipment is already being cancelled."}]}'),
		),
	));
	$queued = pg_label_call('sendcloud', 'cancel', array($id, carrier_label_sendcloud_test_account()));
	$refused = pg_label_call('sendcloud', 'cancel', array($id, carrier_label_sendcloud_test_account()));
	$lost = pg_label_call('sendcloud', 'cancel', array($id, carrier_label_sendcloud_test_account()));
	carrier_label_sendcloud_test_done();

	pg_assert_true($queued['success'], $queued['error']);
	pg_assert_contains('queued', implode(' ', $queued['log']));
	pg_assert_false($refused['success']);
	pg_assert_false($refused['unclear']);
	pg_assert_contains('already being cancelled', $refused['error']);
	pg_assert_true($lost['unclear']);
}

function test_carrier_label_sendcloud_track_reads_the_latest_event()
{
	$id = 'f3a1c2d4-5b6e-4f70-8a9b-0c1d2e3f4a5b';
	carrier_label_sendcloud_test_stub(array(
		'GET /shipments/' . $id                    => array('shipment_get.json'),
		'GET /parcels/tracking/05212345678901' => array('tracking.json'),
	));
	$answer = pg_label_call('sendcloud', 'track', array($id, carrier_label_sendcloud_test_account()));
	carrier_label_sendcloud_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('out_for_delivery', $answer['status']);
	pg_assert_same(strtotime('2026-10-29T08:12:00Z'), $answer['status_at']);
	pg_assert_same('05212345678901', $answer['tracking_number']);

	pg_assert_same('created', pg_label_sendcloud_status(array('phase' => 'ready_to_send')));
	pg_assert_same('in_transit', pg_label_sendcloud_status(array('phase' => 'sorted')));
	pg_assert_same('delivered', pg_label_sendcloud_status(array('phase' => 'delivered')));
	pg_assert_same('delivered', pg_label_sendcloud_status(array('phase' => 'delivery_forced')));
	pg_assert_same('returned', pg_label_sendcloud_status(array('phase' => 'none', 'exception' => 'returned_sender')));
	pg_assert_same('in_transit', pg_label_sendcloud_status(array('phase' => 'none', 'exception' => 'sorting_delay')));
	pg_assert_same('exception', pg_label_sendcloud_status(array('phase' => 'none', 'exception' => 'address_error')));
	pg_assert_same('', pg_label_sendcloud_status(array('phase' => 'unknown', 'exception' => 'unknown')));
}

function test_carrier_label_sendcloud_carrier_of_a_code()
{
	pg_assert_same('dpd', pg_label_sendcloud_carrier('dpd'));
	pg_assert_same('dpd', pg_label_sendcloud_carrier('DPD_AT'));
	pg_assert_same('dhl', pg_label_sendcloud_carrier('dhl_de'));
	pg_assert_same('dhl', pg_label_sendcloud_carrier('dhl_express'));
	pg_assert_same('deutschepost', pg_label_sendcloud_carrier('deutsche_post'));
	pg_assert_same('royalmail', pg_label_sendcloud_carrier('royal_mail'));
	pg_assert_same('ups', pg_label_sendcloud_carrier('ups'));
	pg_assert_same('', pg_label_sendcloud_carrier('dhl'), 'DHL Parcel Benelux is not DHL Germany');
	pg_assert_same('', pg_label_sendcloud_carrier('postnl'));
	pg_assert_same('', pg_label_sendcloud_carrier('colissimo'));
}

function test_carrier_label_sendcloud_return_announces_from_the_customer()
{
	carrier_label_sendcloud_test_stub(array(
		'GET /addresses/sender-addresses?page_size=100'      => array('sender_addresses.json'),
		'POST /shipping-options'                              => array('shipping_options_return.json'),
		'POST /returns/announce-synchronously'               => array(array(201, '{"return_id": 12345, "parcel_id": 67880, "multi_collo_ids": []}')),
		'GET /returns/12345'                                  => array('return_pending.json', 'return.json'),
		'GET /parcels/67880/documents/label?paper_size=a6'   => array('label.pdf'),
	));
	$shipment = carrier_label_sendcloud_test_shipment(array('return' => true));
	$shipment['recipient']['state_code'] = 'DE-BE';
	$answer = pg_label_call('sendcloud', 'create', array($shipment, carrier_label_sendcloud_test_account(array('service' => 'postnl:standard'))));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	$waits = pg_label_sendcloud_waits();
	carrier_label_sendcloud_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array(
		'GET /addresses/sender-addresses?page_size=100',
		'POST /shipping-options',
		'POST /returns/announce-synchronously',
		'GET /returns/12345',
		'GET /returns/12345',
		'GET /parcels/67880/documents/label?paper_size=a6',
	), carrier_label_sendcloud_test_sent());
	pg_assert_same(array(2), $waits, 'asked again once while the number was missing');
	pg_assert_same('r-12345-67880', $answer['reference']);
	pg_assert_same('05212345679999', $answer['tracking_number']);
	pg_assert_same('dpd', $answer['carrier']);
	pg_assert_same('https://tracking.eu-central-1-0.sendcloud.sc/forward?carrier=dpd&code=05212345679999&destination=NL&lang=en-us', $answer['tracking_url']);
	pg_assert_same('created', $answer['status']);
	pg_assert_same('pdf', $answer['label_format']);
	pg_assert_same(0, strpos($answer['label'], '%PDF-'));
	pg_assert_contains('dpd:return/return', implode(' ', $answer['log']));

	pg_assert_same(array(
		'from_address'     => array('country_code' => 'DE', 'postal_code' => '10117'),
		'to_address'       => array('country_code' => 'NL', 'postal_code' => '5611 EM'),
		'parcels'          => array(array('dimensions' => array('length' => '30.00', 'width' => '20.00', 'height' => '10.50', 'unit' => 'cm'), 'weight' => array('value' => '1.250', 'unit' => 'kg'))),
		'functionalities'  => array('returns' => true),
		'calculate_quotes' => true,
	), $sent[1]['body'], 'priced from the customer to the shop');

	pg_assert_same(array(
		'from_address'    => array(
			'name'                => 'Jana Müller',
			'address_line_1'      => 'Friedrichstraße 10',
			'address_line_2'      => 'Hinterhaus',
			'postal_code'         => '10117',
			'city'                => 'Berlin',
			'country_code'        => 'DE',
			'state_province_code' => 'DE-BE',
			'phone_number'        => '+49 30 1234567',
			'email'               => 'jana@example.com',
		),
		'to_address'      => array(
			'name'           => 'Marie Doe',
			'company_name'   => 'Example Shop',
			'address_line_1' => 'Stadhuisplein',
			'house_number'   => '10',
			'postal_code'    => '5611 EM',
			'city'           => 'Eindhoven',
			'country_code'   => 'NL',
			'phone_number'   => '+31626262626',
			'email'          => 'shop@example.com',
		),
		'ship_with'       => array('type' => 'shipping_option_code', 'shipping_option_code' => 'dpd:return/return'),
		'weight'          => array('value' => 1.25, 'unit' => 'kg'),
		'dimensions'      => array('length' => 30, 'width' => 20, 'height' => 10.5, 'unit' => 'cm'),
		'delivery_option' => 'drop_off_point',
		'order_number'    => '10042',
	), $sent[2]['body'], 'the outgoing option, a service point and a non-return option are passed over; no customs');
}

function test_carrier_label_sendcloud_return_refusals()
{
	$account = carrier_label_sendcloud_test_account();
	$abroad = carrier_label_sendcloud_test_shipment(array('return' => true));
	$abroad['recipient']['country'] = 'CH';

	carrier_label_sendcloud_test_stub(array('GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json')));
	$customs = pg_label_call('sendcloud', 'create', array($abroad, $account));
	$sent = carrier_label_sendcloud_test_sent();
	carrier_label_sendcloud_test_done();

	pg_assert_false($customs['success']);
	pg_assert_false($customs['unclear']);
	pg_assert_contains('A return from CH to NL needs a customs declaration', $customs['error']);
	pg_assert_same(array('GET /addresses/sender-addresses?page_size=100'), $sent, 'nothing is priced or announced');

	carrier_label_sendcloud_test_stub(array(
		'GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json', 'sender_addresses.json', 'sender_addresses.json', 'sender_addresses.json'),
		'POST /shipping-options'                         => array('shipping_options.json'),
		'POST /returns/announce-synchronously'          => array(
			array(400, '{"error": {"code": "invalid_postal_code", "request": "api/v3/returns/announce-synchronously", "message": "The postal code you provided is invalid."}}'),
			array(502, '<html>Bad gateway</html>'),
		),
	));
	$none = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_shipment(array('return' => true)), $account));
	$asked = carrier_label_sendcloud_test_shipment(array('return' => true, 'service' => 'dpd:return/return'));
	$invalid = pg_label_call('sendcloud', 'create', array($asked, $account));
	$server = pg_label_call('sendcloud', 'create', array($asked, $account));
	$lost = pg_label_call('sendcloud', 'create', array($asked, $account));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	carrier_label_sendcloud_test_done();

	pg_assert_false($none['success']);
	pg_assert_false($none['unclear']);
	pg_assert_contains('Sendcloud found no return option for this shipment.', $none['error'], 'outgoing options are never taken for a return');
	pg_assert_same('dpd:return/return', $sent[3]['body']['ship_with']['shipping_option_code'], 'the shipment\'s service is taken without pricing');
	pg_assert_false($invalid['unclear']);
	pg_assert_contains('(The postal code you provided is invalid.)', $invalid['error']);
	pg_assert_true($server['unclear'], 'a server error after the return was announced');
	pg_assert_true($lost['unclear'], 'no answer after the return was announced');
}

function test_carrier_label_sendcloud_return_label_and_cancel()
{
	carrier_label_sendcloud_test_stub(array(
		'GET /parcels/67880/documents/label' => array('label.zpl'),
		'PATCH /returns/12345/cancel'        => array(
			array(202, '{"message": "Cancellation requested successfully"}'),
			array(409, '{"errors": [{"field": "returns", "code": 409, "message": "Return is not cancellable."}]}'),
		),
	));
	$label = pg_label_call('sendcloud', 'label', array('r-12345-67880', 'zpl', carrier_label_sendcloud_test_account()));
	$requested = pg_label_call('sendcloud', 'cancel', array('r-12345-67880', carrier_label_sendcloud_test_account()));
	$refused = pg_label_call('sendcloud', 'cancel', array('r-12345-67880', carrier_label_sendcloud_test_account()));
	$broken = pg_label_call('sendcloud', 'cancel', array('r-12345', carrier_label_sendcloud_test_account()));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	carrier_label_sendcloud_test_done();

	pg_assert_true($label['success'], $label['error']);
	pg_assert_same('r-12345-67880', $label['reference']);
	pg_assert_contains('^XA', $label['label']);
	pg_assert_same(array('GET /parcels/67880/documents/label', 'PATCH /returns/12345/cancel', 'PATCH /returns/12345/cancel'), carrier_label_sendcloud_test_sent(), 'the return\'s parcel, no shipment read');
	pg_assert_same(null, $sent[1]['body']);

	pg_assert_true($requested['success'], $requested['error']);
	pg_assert_contains('Cancellation requested successfully', implode(' ', $requested['log']));
	pg_assert_false($refused['success']);
	pg_assert_false($refused['unclear']);
	pg_assert_contains('Return is not cancellable.', $refused['error']);
	pg_assert_false($broken['success'], 'a return reference without its parcel');
}

function carrier_label_sendcloud_test_customs_shipment()
{
	$shipment = carrier_label_sendcloud_test_shipment(array('order_number' => '10077'));
	$shipment['recipient'] = array(
		'name'       => 'John Doe',
		'email'      => 'john@example.com',
		'phone'      => '+12129976661',
		'address_1'  => '123 West 43rd St',
		'address_2'  => '',
		'city'       => 'New York',
		'state'      => 'New York',
		'state_code' => 'US-NY',
		'zip'        => '10036',
		'country'    => 'US',
	);
	$shipment['goods'] = array(
		array('name' => 'Ceramic Mug', 'quantity' => 2, 'price' => 1250, 'weight' => 0.4, 'hs_code' => '6912.00.10', 'origin_country' => 'TR', 'sku' => 'MUG-01'),
		array('name' => 'Linen Napkin', 'quantity' => 1, 'price' => 499, 'weight' => 0, 'hs_code' => '', 'origin_country' => '', 'sku' => ''),
	);

	return $shipment;
}

function carrier_label_sendcloud_test_customs_account($changes = array())
{
	return carrier_label_sendcloud_test_account(array_merge(array(
		'customs_contents'       => 'merchandise',
		'customs_incoterm'       => 'DDU',
		'customs_non_delivery'   => 'return',
		'customs_hs_code'        => '6302.51',
		'customs_origin_country' => 'PT',
		'customs_signer'         => '',
	), $changes));
}

function test_carrier_label_sendcloud_customs_route_carries_parcel_items()
{
	carrier_label_sendcloud_test_stub(array(
		'GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json'),
		'POST /shipping-options'                         => array('shipping_options_customs.json'),
		'POST /shipments/announce'                       => array('shipment.json'),
	));
	$answer = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_customs_shipment(), carrier_label_sendcloud_test_customs_account()));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	carrier_label_sendcloud_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('GET /addresses/sender-addresses?page_size=100', 'POST /shipping-options', 'POST /shipments/announce'), carrier_label_sendcloud_test_sent());

	$body = $sent[2]['body'];
	pg_assert_same('postnl:worldwide', $body['ship_with']['properties']['shipping_option_code'], 'export documents allowed; the DDP option passed over for DDU');
	pg_assert_same(array(
		array(
			'description'    => 'Ceramic Mug',
			'quantity'       => 2,
			'weight'         => array('value' => 0.4, 'unit' => 'kg'),
			'price'          => array('value' => '12.50', 'currency' => 'EUR'),
			'hs_code'        => '69120010',
			'origin_country' => 'TR',
			'sku'            => 'MUG-01',
		),
		array(
			'description'    => 'Linen Napkin',
			'quantity'       => 1,
			'weight'         => array('value' => 0.45, 'unit' => 'kg'),
			'price'          => array('value' => '4.99', 'currency' => 'EUR'),
			'hs_code'        => '630251',
			'origin_country' => 'PT',
		),
	), $body['parcels'][0]['parcel_items'], 'product values first, shop defaults second; no SKU key when there is none');
	pg_assert_same(array('invoice_number' => '10077', 'export_reason' => 'commercial_goods'), $body['customs_information']);
	pg_assert_same('US-NY', $body['to_address']['state_province_code']);

	$gift = pg_label_sendcloud_customs(carrier_label_sendcloud_test_customs_shipment(), carrier_label_sendcloud_test_customs_account(array('customs_contents' => 'gift')), 'NL');
	pg_assert_same('gift', $gift['information']['export_reason']);
	pg_assert_same('ups:express_saver/incoterm=ddp', pg_label_sendcloud_option_pick(json_decode(carrier_label_sendcloud_test_fixture('shipping_options_customs.json'), true)['data'], false, 'DDP'));
}

function test_carrier_label_sendcloud_customs_route_with_an_empty_block_is_refused()
{
	// A product's own HS code does not open the route: only the shop's block does.
	$account = carrier_label_sendcloud_test_customs_account(array('customs_hs_code' => '', 'customs_origin_country' => '', 'customs_signer' => ''));
	carrier_label_sendcloud_test_stub(array('GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json')));
	$answer = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_customs_shipment(), $account));
	carrier_label_sendcloud_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('A parcel from NL to US needs a customs declaration, which is not sent to Sendcloud yet.', $answer['error']);
	pg_assert_same(array('GET /addresses/sender-addresses?page_size=100'), carrier_label_sendcloud_test_sent());
	pg_assert_same('', pg_label_sendcloud_route_error('NL', 'US', false, true));
	pg_assert_contains('does not ship from TR', pg_label_sendcloud_route_error('TR', 'US', false, true), 'the sender countries still apply');
	pg_assert_contains('A return from CH to DE needs a customs declaration', pg_label_sendcloud_route_error('CH', 'DE', true));
}

function test_carrier_label_sendcloud_customs_line_without_hs_code_is_refused()
{
	$account = carrier_label_sendcloud_test_customs_account(array('customs_hs_code' => ''));
	carrier_label_sendcloud_test_stub(array('GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json')));
	$answer = pg_label_call('sendcloud', 'create', array(carrier_label_sendcloud_test_customs_shipment(), $account));
	carrier_label_sendcloud_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('Linen Napkin has no HS code', $answer['error']);
	pg_assert_same(array('GET /addresses/sender-addresses?page_size=100'), carrier_label_sendcloud_test_sent(), 'nothing is priced or announced');

	$long = carrier_label_sendcloud_test_customs_shipment();
	$long['goods'][0]['hs_code'] = '6912.0010.9900.1';
	$refused = pg_label_sendcloud_customs($long, carrier_label_sendcloud_test_customs_account(), 'NL');
	pg_assert_contains('at most 12 digits', $refused['error']);

	$no_currency = carrier_label_sendcloud_test_customs_shipment();
	$no_currency['currency'] = '';
	pg_assert_contains('no currency', pg_label_sendcloud_customs($no_currency, carrier_label_sendcloud_test_customs_account(), 'NL')['error']);
}

function test_carrier_label_sendcloud_eu_route_with_the_block_filled_is_unchanged()
{
	$shipment = carrier_label_sendcloud_test_customs_shipment();
	$shipment['recipient'] = carrier_label_sendcloud_test_shipment()['recipient'];
	carrier_label_sendcloud_test_stub(array(
		'GET /addresses/sender-addresses?page_size=100' => array('sender_addresses.json'),
		'POST /shipping-options'                         => array('shipping_options.json'),
		'POST /shipments/announce'                       => array('shipment.json'),
	));
	$answer = pg_label_call('sendcloud', 'create', array($shipment, carrier_label_sendcloud_test_customs_account()));
	$sent = $GLOBALS['carrier_label_sendcloud_test_sent'];
	carrier_label_sendcloud_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('dpd:home', $sent[2]['body']['ship_with']['properties']['shipping_option_code'], 'export documents still passed over');
	pg_assert_false(isset($sent[2]['body']['customs_information']));
	pg_assert_false(isset($sent[2]['body']['parcels'][0]['parcel_items']));
	pg_assert_same(array('items' => null, 'information' => null, 'error' => ''), pg_label_sendcloud_customs($shipment, carrier_label_sendcloud_test_customs_account(), 'NL'));
}
