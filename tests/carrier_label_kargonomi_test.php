<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Kargonomi label driver (includes/shipping/kargonomi.php),
 * every operation called through pg_label_call(). Kargonomi has no test
 * mode and is never called: a stand-in transport answers with the answers
 * of tests/fixtures/kargonomi/, built in the shape of its public reference
 * (www.kargonomi.com.tr/help/api-dokumantasyonu/kargonomi-api/), and keeps
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

function carrier_label_kargonomi_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/kargonomi/' . $name);
}

function carrier_label_kargonomi_test_account($changes = array())
{
	return array_merge(array(
		'api_token'    => 'kg-token-0htoc1aNkVJhE6mD',
		'warehouse_id' => '12707',
		'provider'     => '',
	), $changes);
}

function carrier_label_kargonomi_test_shipment($changes = array())
{
	return array_merge(array(
		'order_number' => '10042',
		'recipient'    => array(
			'name'       => 'Ayşe Yılmaz',
			'email'      => 'ayse@example.com',
			'phone'      => '+90 (532) 123 45 67',
			'address_1'  => 'Kızılay Mah. Atatürk Blv. No:5',
			'address_2'  => 'Daire 3',
			'city'       => 'Çankaya',
			'state'      => 'Ankara',
			'state_code' => 'TR-06',
			'zip'        => '06420',
			'country'    => 'TR',
		),
		'parcel'       => array('length' => 30, 'width' => 20, 'height' => 10.5, 'weight' => 1.25),
		'items'        => array(array('title' => 'Mug', 'quantity' => 2), array('title' => 'Plate', 'quantity' => 1)),
		'total'        => 8990,
		'currency'     => 'TRY',
		'service'      => '',
		'return'       => false,
	), $changes);
}

// Answers each request with the next answer queued for it: "METHOD path"
// (the path under the API base) => list of array(status, body) or a
// fixture file name for a 200 answer. A request with nothing queued times
// out after it was sent.
function carrier_label_kargonomi_test_stub($answers)
{
	// A test run on its own (a filter) has not loaded the driver yet.
	pg_label_load('kargonomi');
	$GLOBALS['carrier_label_kargonomi_test_sent'] = array();

	pg_label_kargonomi_transport(function ($method, $url, $headers, $body) use (&$answers) {
		$key = $method . ' ' . substr($url, strlen('https://app.kargonomi.com.tr/api/v1'));
		$GLOBALS['carrier_label_kargonomi_test_sent'][] = array('request' => $key, 'headers' => $headers, 'body' => ($body === null) ? null : json_decode($body, true));

		if (empty($answers[$key])) {
			return array('status' => 0, 'body' => '', 'error' => 'Operation timed out', 'sent' => true);
		}

		$answer = array_shift($answers[$key]);

		if (is_string($answer)) {
			$answer = array(200, carrier_label_kargonomi_test_fixture($answer));
		}

		return array('status' => $answer[0], 'body' => $answer[1], 'error' => '', 'sent' => true);
	});
}

function carrier_label_kargonomi_test_sent()
{
	return array_map(function ($sent) {
		return $sent['request'];
	}, $GLOBALS['carrier_label_kargonomi_test_sent']);
}

function carrier_label_kargonomi_test_done()
{
	pg_label_kargonomi_transport(null, true);
}

// The answers of a create up to the confirmation.
function carrier_label_kargonomi_test_places_and_draft()
{
	return array(
		'GET /states'                        => array('states.json'),
		'GET /cities/6'                      => array('cities_6.json'),
		'POST /shipments'                    => array(array(201, carrier_label_kargonomi_test_fixture('shipment_draft.json'))),
		'GET /shipment-price-comparison/8'   => array('price_comparison.json'),
	);
}

function test_carrier_label_kargonomi_is_a_builtin_driver()
{
	pg_assert_true(pg_label_driver('kargonomi') !== null);
	pg_assert_same(array('carriers' => array('surat', 'aras', 'ptt'), 'formats' => array('pdf')), pg_label_info('kargonomi'));

	foreach (array('test', 'create', 'label', 'cancel', 'track') as $operation) {
		pg_assert_true(pg_label_can('kargonomi', $operation), $operation);
	}

	pg_assert_false(pg_label_can('kargonomi', 'pickup'));

	$fields = array();

	foreach (pg_label_fields('kargonomi') as $field) {
		$fields[$field['name']] = $field['type'] . (empty($field['required']) ? '' : ' required');
	}

	pg_assert_same(array(
		'api_token'    => 'secret required',
		'warehouse_id' => 'text required',
		'provider'     => 'text',
	), $fields);

	$help = pg_label_fields('kargonomi')[0]['help'];
	pg_assert_contains('no test mode', $help);
	pg_assert_contains('real, charged label', $help);
}

function test_carrier_label_kargonomi_test_reads_the_balance()
{
	carrier_label_kargonomi_test_stub(array('GET /user/credit' => array(
		'credit.json',
		array(401, '{"message":"Unauthenticated. kg-token-0htoc1aNkVJhE6mD"}'),
	)));
	$answer = pg_label_call('kargonomi', 'test', array(carrier_label_kargonomi_test_account()));
	$sent = $GLOBALS['carrier_label_kargonomi_test_sent'][0];
	$refused = pg_label_call('kargonomi', 'test', array(carrier_label_kargonomi_test_account()));
	carrier_label_kargonomi_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_contains('balance 122.80', implode(' ', $answer['log']));
	pg_assert_contains('Authorization: Bearer kg-token-0htoc1aNkVJhE6mD', $sent['headers']);
	pg_assert_contains('Accept: application/json', $sent['headers']);

	pg_assert_false($refused['success']);
	pg_assert_false($refused['unclear']);
	pg_assert_contains('Kargonomi refused the request. (Unauthenticated. ****)', $refused['error']);
	pg_assert_false(strpos(json_encode($refused), 'kg-token-0htoc1aNkVJhE6mD'), 'the token is masked');
}

function test_carrier_label_kargonomi_create_buys_the_cheapest_and_reads_the_label()
{
	carrier_label_kargonomi_test_stub(carrier_label_kargonomi_test_places_and_draft() + array(
		'POST /confirm-shipping-price'        => array('confirm.json'),
		'GET /shipments/8'                    => array('shipment_creating.json', 'shipment_created.json'),
		'GET /shipments/8/barcode?format=pdf' => array('barcode.json'),
	));
	$answer = pg_label_call('kargonomi', 'create', array(carrier_label_kargonomi_test_shipment(), carrier_label_kargonomi_test_account()));
	$sent = $GLOBALS['carrier_label_kargonomi_test_sent'];
	carrier_label_kargonomi_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array(
		'GET /states', 'GET /cities/6', 'POST /shipments', 'GET /shipment-price-comparison/8', 'POST /confirm-shipping-price',
		'GET /shipments/8', 'GET /shipments/8', 'GET /shipments/8/barcode?format=pdf',
	), carrier_label_kargonomi_test_sent(), 'the shipment is read again until the carrier\'s number comes');

	pg_assert_same(array('shipment' => array(
		'warehouse_id'   => 12707,
		'buyer_name'     => 'Ayşe Yılmaz',
		'buyer_email'    => 'ayse@example.com',
		'buyer_phone'    => '5321234567',
		'buyer_address'  => 'Kızılay Mah. Atatürk Blv. No:5 Daire 3',
		'buyer_state_id' => 6,
		'buyer_city_id'  => 662,
		'packages'       => array(array('content' => '2 x Mug, 1 x Plate', 'desi' => 2.1)),
	)), $sent[2]['body'], 'the warehouse sends; desi is the larger of volume and weight');
	pg_assert_same(array('shipment_id' => 8, 'shipping_provider_id' => -1), $sent[4]['body'], 'no carrier asked: the cheapest');

	pg_assert_same('8', $answer['reference']);
	pg_assert_same('7344501034697', $answer['tracking_number']);
	pg_assert_same('aras', $answer['carrier']);
	pg_assert_same('8000123456789', $answer['barcode']);
	pg_assert_same('created', $answer['status']);
	pg_assert_same('pdf', $answer['label_format']);
	pg_assert_same(0, strpos($answer['label'], '%PDF-'));
	pg_assert_contains('confirmed with the cheapest carrier', implode(' ', $answer['log']));
}

function test_carrier_label_kargonomi_create_takes_the_asked_carrier_and_reads_places_once()
{
	carrier_label_kargonomi_test_stub(array(
		'GET /states'                         => array('states.json'),
		'GET /cities/6'                       => array('cities_6.json'),
		'POST /shipments'                     => array('shipment_draft.json', 'shipment_draft.json'),
		'GET /shipment-price-comparison/8'    => array('price_comparison.json', 'price_comparison.json'),
		'POST /confirm-shipping-price'        => array('shipment_created.json', 'shipment_created.json'),
		'GET /shipments/8/barcode?format=pdf' => array('barcode.json', 'barcode.json'),
	));
	$account = carrier_label_kargonomi_test_account(array('provider' => 'Surat'));
	$first = pg_label_call('kargonomi', 'create', array(carrier_label_kargonomi_test_shipment(), $account));
	$second = pg_label_call('kargonomi', 'create', array(carrier_label_kargonomi_test_shipment(array('service' => 'ptt')), $account));
	$sent = $GLOBALS['carrier_label_kargonomi_test_sent'];
	carrier_label_kargonomi_test_done();

	pg_assert_true($first['success'], $first['error']);
	pg_assert_true($second['success'], $second['error']);
	pg_assert_same(array('shipment_id' => 8, 'shipping_provider_id' => 5), $sent[4]['body'], 'the account\'s carrier');
	pg_assert_same(array('shipment_id' => 8, 'shipping_provider_id' => 7), $sent[8]['body'], 'the shipment\'s service wins');
	pg_assert_same(array(
		'GET /states', 'GET /cities/6', 'POST /shipments', 'GET /shipment-price-comparison/8', 'POST /confirm-shipping-price', 'GET /shipments/8/barcode?format=pdf',
		'POST /shipments', 'GET /shipment-price-comparison/8', 'POST /confirm-shipping-price', 'GET /shipments/8/barcode?format=pdf',
	), carrier_label_kargonomi_test_sent(), 'the numbers came with the confirmation; places are read once a request');
	pg_assert_contains('confirmed with surat', implode(' ', $first['log']));
}

function test_carrier_label_kargonomi_refusals_before_a_call()
{
	carrier_label_kargonomi_test_stub(array());
	$cases = array();
	$cases['return'] = carrier_label_kargonomi_test_shipment(array('return' => true));

	foreach (array('country' => 'DE', 'name' => 'Ayşe', 'address_1' => 'Kızılay', 'phone' => '123', 'city' => '') as $field => $value) {
		$shipment = carrier_label_kargonomi_test_shipment();
		$shipment['recipient'][$field] = $value;

		if ($field === 'address_1') {
			$shipment['recipient']['address_2'] = '';
		}

		$cases[$field] = $shipment;
	}

	$answers = array();

	foreach ($cases as $name => $shipment) {
		$answers[$name] = pg_label_call('kargonomi', 'create', array($shipment, carrier_label_kargonomi_test_account()));
	}

	$sent = $GLOBALS['carrier_label_kargonomi_test_sent'];
	carrier_label_kargonomi_test_done();

	pg_assert_contains('return labels', $answers['return']['error']);
	pg_assert_contains('within Turkey only', $answers['country']['error']);

	foreach (array('name', 'address_1', 'phone', 'city') as $name) {
		pg_assert_contains('name and surname, an address of at least ten characters', $answers[$name]['error'], $name);
	}

	foreach ($answers as $name => $answer) {
		pg_assert_false($answer['success'], $name);
		pg_assert_false($answer['unclear'], $name);
	}

	pg_assert_same(array(), $sent, 'nothing reaches Kargonomi');
}

function test_carrier_label_kargonomi_unknown_places_open_no_draft()
{
	$far = carrier_label_kargonomi_test_shipment();
	$far['recipient']['state'] = 'Hakkari';
	$lost = carrier_label_kargonomi_test_shipment();
	$lost['recipient']['city'] = 'Polatlı';

	carrier_label_kargonomi_test_stub(array('GET /states' => array('states.json'), 'GET /cities/6' => array('cities_6.json')));
	$province = pg_label_call('kargonomi', 'create', array($far, carrier_label_kargonomi_test_account()));
	$district = pg_label_call('kargonomi', 'create', array($lost, carrier_label_kargonomi_test_account()));
	$sent = carrier_label_kargonomi_test_sent();
	carrier_label_kargonomi_test_done();

	pg_assert_false($province['success']);
	pg_assert_contains('does not know the province Hakkari', $province['error']);
	pg_assert_false($district['success']);
	pg_assert_contains('does not know the district Polatlı of Ankara', $district['error']);
	pg_assert_same(array('GET /states', 'GET /cities/6'), $sent, 'no draft is opened');
}

function test_carrier_label_kargonomi_refused_draft_is_plain()
{
	carrier_label_kargonomi_test_stub(array(
		'GET /states'     => array('states.json'),
		'GET /cities/6'   => array('cities_6.json'),
		'POST /shipments' => array(array(422, carrier_label_kargonomi_test_fixture('error_422.json'))),
	));
	$answer = pg_label_call('kargonomi', 'create', array(carrier_label_kargonomi_test_shipment(), carrier_label_kargonomi_test_account()));
	$sent = carrier_label_kargonomi_test_sent();
	carrier_label_kargonomi_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('Kargonomi refused the request. (Alıcının telefon numarası 10 karakter olmalıdır.)', $answer['error']);
	pg_assert_same(array('GET /states', 'GET /cities/6', 'POST /shipments'), $sent);
}

function test_carrier_label_kargonomi_no_offer_discards_the_draft()
{
	carrier_label_kargonomi_test_stub(carrier_label_kargonomi_test_places_and_draft() + array(
		'DELETE /shipments/8' => array(array(204, '')),
	));
	$answer = pg_label_call('kargonomi', 'create', array(carrier_label_kargonomi_test_shipment(array('service' => 'hepsijet')), carrier_label_kargonomi_test_account()));
	$sent = carrier_label_kargonomi_test_sent();
	carrier_label_kargonomi_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('no offer of hepsijet', $answer['error'], 'out of HepsiJet\'s area is no price');
	pg_assert_same('DELETE /shipments/8', end($sent), 'nothing is bought');
	pg_assert_contains('draft 8 deleted', implode(' ', $answer['log']));
}

function test_carrier_label_kargonomi_confirmation_failures()
{
	$account = carrier_label_kargonomi_test_account();
	$answers = carrier_label_kargonomi_test_places_and_draft();

	foreach (array_keys($answers) as $key) {
		$answers[$key] = array_merge($answers[$key], $answers[$key], $answers[$key]);
	}

	carrier_label_kargonomi_test_stub($answers + array(
		'POST /confirm-shipping-price' => array(
			array(422, '{"message": "Bakiyeniz yetersiz."}'),
			array(502, '<html>Bad gateway</html>'),
		),
		'DELETE /shipments/8'          => array(array(204, '')),
	));
	$balance = pg_label_call('kargonomi', 'create', array(carrier_label_kargonomi_test_shipment(), $account));
	$gateway = pg_label_call('kargonomi', 'create', array(carrier_label_kargonomi_test_shipment(), $account));
	$lost = pg_label_call('kargonomi', 'create', array(carrier_label_kargonomi_test_shipment(), $account));
	$deletes = count(array_keys(carrier_label_kargonomi_test_sent(), 'DELETE /shipments/8'));
	carrier_label_kargonomi_test_done();

	pg_assert_false($balance['success']);
	pg_assert_false($balance['unclear'], 'a refused confirmation bought nothing');
	pg_assert_contains('(Bakiyeniz yetersiz.)', $balance['error']);
	pg_assert_true($gateway['unclear'], 'a server error after the confirmation went out');
	pg_assert_true($lost['unclear'], 'no answer after the confirmation went out');
	pg_assert_same(1, $deletes, 'only the refused draft is deleted');
}

function test_carrier_label_kargonomi_carrier_order_failed_or_late()
{
	carrier_label_kargonomi_test_stub(carrier_label_kargonomi_test_places_and_draft() + array(
		'POST /confirm-shipping-price' => array('confirm.json'),
		'GET /shipments/8'             => array('shipment_failed.json'),
	));
	$failed = pg_label_call('kargonomi', 'create', array(carrier_label_kargonomi_test_shipment(), carrier_label_kargonomi_test_account()));
	carrier_label_kargonomi_test_done();

	pg_assert_false($failed['success']);
	pg_assert_false($failed['unclear']);
	pg_assert_contains('could not make the carrier\'s order for this shipment. (Kargo Sipariş Oluşturulamadı)', $failed['error']);

	$creating = array_fill(0, 5, 'shipment_creating.json');
	carrier_label_kargonomi_test_stub(carrier_label_kargonomi_test_places_and_draft() + array(
		'POST /confirm-shipping-price' => array('confirm.json'),
		'GET /shipments/8'             => $creating,
	));
	$late = pg_label_call('kargonomi', 'create', array(carrier_label_kargonomi_test_shipment(), carrier_label_kargonomi_test_account()));
	$reads = count(array_keys(carrier_label_kargonomi_test_sent(), 'GET /shipments/8'));
	carrier_label_kargonomi_test_done();

	pg_assert_false($late['success']);
	pg_assert_true($late['unclear'], 'bought, the carrier\'s number not given yet');
	pg_assert_contains('carrier\'s number of shipment 8 yet', $late['error']);
	pg_assert_same(5, $reads, 'five reads, then it stops');
}

function test_carrier_label_kargonomi_label_prints_again()
{
	$pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

	carrier_label_kargonomi_test_stub(array('GET /shipments/8/barcode?format=pdf' => array(
		'barcode.json',
		array(200, base64_encode($pdf)),
		array(200, $pdf),
		array(200, '{"data": {"barcode": null}}'),
		array(404, '{"message": "Gönderi bulunamadı."}'),
	)));
	$account = carrier_label_kargonomi_test_account();
	$wrapped = pg_label_call('kargonomi', 'label', array('8', 'pdf', $account));
	$bare = pg_label_call('kargonomi', 'label', array('8', 'pdf', $account));
	$raw = pg_label_call('kargonomi', 'label', array('8', 'pdf', $account));
	$none = pg_label_call('kargonomi', 'label', array('8', 'pdf', $account));
	$missing = pg_label_call('kargonomi', 'label', array('8', 'pdf', $account));
	$count = count($GLOBALS['carrier_label_kargonomi_test_sent']);
	$zpl = pg_label_call('kargonomi', 'label', array('8', 'zpl', $account));
	$bad = pg_label_call('kargonomi', 'label', array('8/barcode', 'pdf', $account));
	$after = count($GLOBALS['carrier_label_kargonomi_test_sent']);
	carrier_label_kargonomi_test_done();

	foreach (array($wrapped, $bare, $raw) as $answer) {
		pg_assert_true($answer['success'], $answer['error']);
		pg_assert_same($pdf, $answer['label']);
		pg_assert_same('pdf', $answer['label_format']);
	}

	pg_assert_false($none['success']);
	pg_assert_contains('no label for this shipment yet', $none['error']);
	pg_assert_false($missing['success']);
	pg_assert_contains('Gönderi bulunamadı.', $missing['error']);
	pg_assert_false($zpl['success']);
	pg_assert_contains('PDF only', $zpl['error']);
	pg_assert_false($bad['success']);
	pg_assert_same($count, $after, 'neither is asked of Kargonomi');
}

function test_carrier_label_kargonomi_cancel()
{
	carrier_label_kargonomi_test_stub(array('POST /shipments/cancel' => array(
		'cancel.json',
		array(422, '{"message": "Kargoya verilmiş gönderi iptal edilemez."}'),
	)));
	$account = carrier_label_kargonomi_test_account();
	$taken = pg_label_call('kargonomi', 'cancel', array('8', $account));
	$body = $GLOBALS['carrier_label_kargonomi_test_sent'][0]['body'];
	$refused = pg_label_call('kargonomi', 'cancel', array('8', $account));
	$lost = pg_label_call('kargonomi', 'cancel', array('8', $account));
	carrier_label_kargonomi_test_done();

	pg_assert_true($taken['success'], $taken['error']);
	pg_assert_same(array('shipment_id' => 8), $body);
	pg_assert_contains('36 saat içinde', implode(' ', $taken['log']));
	pg_assert_false($refused['success']);
	pg_assert_false($refused['unclear']);
	pg_assert_contains('Kargoya verilmiş gönderi iptal edilemez.', $refused['error']);
	pg_assert_true($lost['unclear'], 'no answer after the cancellation went out');
}

function test_carrier_label_kargonomi_track_reads_the_shipment()
{
	carrier_label_kargonomi_test_stub(array('GET /shipments/8' => array(
		'shipment_delivered.json',
		'shipment_started.json',
		'shipment_creating.json',
		array(200, str_replace('webservice_shipment_started', 'some_new_status', carrier_label_kargonomi_test_fixture('shipment_started.json'))),
	)));
	$account = carrier_label_kargonomi_test_account();
	$delivered = pg_label_call('kargonomi', 'track', array('8', $account));
	$moving = pg_label_call('kargonomi', 'track', array('8', $account));
	$creating = pg_label_call('kargonomi', 'track', array('8', $account));
	$unknown = pg_label_call('kargonomi', 'track', array('8', $account));
	carrier_label_kargonomi_test_done();

	pg_assert_true($delivered['success'], $delivered['error']);
	pg_assert_same('delivered', $delivered['status']);
	pg_assert_same(gmmktime(11, 35, 0, 10, 28, 2026), $delivered['status_at'], 'the customer\'s delivery date');
	pg_assert_same('7344501034697', $delivered['tracking_number']);
	pg_assert_same('aras', $delivered['carrier']);
	pg_assert_contains('Kargo Teslim Edildi', implode(' ', $delivered['log']));

	pg_assert_same('in_transit', $moving['status']);
	pg_assert_same(gmmktime(13, 20, 0, 10, 27, 2026), $moving['status_at'], 'handed to the carrier');
	pg_assert_same('created', $creating['status']);
	pg_assert_same(gmmktime(7, 10, 20, 10, 27, 2026), $creating['status_at']);
	pg_assert_same('', $creating['tracking_number']);

	pg_assert_false($unknown['success']);
	pg_assert_false($unknown['unclear']);
	pg_assert_contains('no status for this shipment yet', $unknown['error']);
}

function test_carrier_label_kargonomi_pure_helpers()
{
	pg_label_load('kargonomi');

	pg_assert_same('in_transit', pg_label_kargonomi_status('webservice_shipment_started'));
	pg_assert_same('returned', pg_label_kargonomi_status('webservice_shipment_returning'));
	pg_assert_same('exception', pg_label_kargonomi_status('webservice_shipment_not_delivered'));
	pg_assert_same('exception', pg_label_kargonomi_status('webservice_shipment_missing'));
	pg_assert_same('exception', pg_label_kargonomi_status('request_for_cancellation'));
	pg_assert_same('created', pg_label_kargonomi_status('READY'));
	pg_assert_same('', pg_label_kargonomi_status('unknown'));

	pg_assert_same('5321234567', pg_label_kargonomi_phone('+90 532 123 45 67'));
	pg_assert_same('5321234567', pg_label_kargonomi_phone('0532 123 4567'));
	pg_assert_same('2125551122', pg_label_kargonomi_phone('0090 212 555 11 22'));
	pg_assert_same('', pg_label_kargonomi_phone('0123 456 7890'), 'no number starts with zero after the trunk');
	pg_assert_same('', pg_label_kargonomi_phone('12345'));

	$states = json_decode(carrier_label_kargonomi_test_fixture('states.json'), true);
	pg_assert_same('34', pg_label_kargonomi_place_id($states, 'istanbul'));
	pg_assert_same('35', pg_label_kargonomi_place_id($states, ' İzmir '));
	pg_assert_same('6', pg_label_kargonomi_place_id(array(array('id' => 6, 'title' => 'Ankara')), 'ANKARA'), 'a bare list, a title');
	pg_assert_same('', pg_label_kargonomi_place_id($states, ''));
	pg_assert_same('', pg_label_kargonomi_place_id(array(array('id' => 'x', 'name' => 'ANKARA')), 'Ankara'));

	$offers = json_decode(carrier_label_kargonomi_test_fixture('price_comparison.json'), true);
	pg_assert_same('-1', pg_label_kargonomi_offer($offers, ''));
	pg_assert_same('4', pg_label_kargonomi_offer($offers, 'aras'));
	pg_assert_same('', pg_label_kargonomi_offer($offers, 'hepsijet'));
	pg_assert_same('', pg_label_kargonomi_offer($offers, 'otomatik'));
	pg_assert_same('', pg_label_kargonomi_offer(array('shipping_provider_with_price' => array(array('id' => 6, 'slug' => 'hepsijet', 'price' => 'Hizmet Dışı Bölge'))), ''), 'no carrier priced it');

	pg_assert_same('', pg_label_kargonomi_number_of(array('shipping_webservice_barcode' => 'JRJ3PA_1')), 'Kargonomi\'s package barcode is no parcel number');
	pg_assert_same('8000123456789', pg_label_kargonomi_number_of(array('shipping_webservice_tracking_code' => null, 'shipping_webservice_barcode' => '8000123456789')));
	pg_assert_same('aras', pg_label_kargonomi_carrier(array('shipping_provider_slug' => 'Aras')));
	pg_assert_same('', pg_label_kargonomi_carrier(array('shipping_provider_slug' => 'kolaygelsin')));

	pg_assert_same(array('Doğrulama hatası.', 'Telefon 10 karakter olmalıdır.', 'Adres en az 10 karakter olmalıdır.'), pg_label_kargonomi_error_texts(array(
		'message' => 'Doğrulama hatası.',
		'errors'  => array('shipment.buyer_phone' => array('Telefon 10 karakter olmalıdır.'), 'shipment.buyer_address' => array('Adres en az 10 karakter olmalıdır.')),
	)));
	pg_assert_same('', pg_label_kargonomi_document('not a label', array()));
}
