<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Aras Kargo label driver (includes/shipping/aras.php), every
 * operation called through pg_label_call(). Aras is never called: a
 * stand-in transport answers with the SOAP answers of tests/fixtures/aras/,
 * built in the shape of the service's own published description (the WSDL
 * of arascargoservice.asmx and its IrsaliyeData schema), and keeps the
 * requests it was sent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function carrier_label_aras_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/aras/' . $name);
}

function carrier_label_aras_test_envelope($inner)
{
	return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body>' . $inner . '</soap:Body></soap:Envelope>';
}

function carrier_label_aras_test_account($changes = array())
{
	return array_merge(array(
		'username'       => 'magaza_ws',
		'password'       => 'Ws-Parola-2026',
		'customer_code'  => '1234567',
		'mode'           => 'live',
		'sender_address' => '',
		'label_format'   => 'pdf',
	), $changes);
}

function carrier_label_aras_test_shipment($changes = array())
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
		'items'        => array(array('title' => 'Mug', 'quantity' => 2), array('title' => 'Plate & Bowl <set>', 'quantity' => 1)),
		'total'        => 8990,
		'currency'     => 'TRY',
		'service'      => '',
		'return'       => false,
	), $changes);
}

// Answers each call with the next answer queued for its operation (read
// from the SOAPAction header): operation => list of array(status, body) or
// a fixture file name for a 200 answer. A call with nothing queued times
// out after it was sent.
function carrier_label_aras_test_stub($answers)
{
	$GLOBALS['carrier_label_aras_test_sent'] = array();

	pg_label_aras_transport(function ($method, $url, $headers, $body) use (&$answers) {
		$operation = '';

		foreach ($headers as $header) {
			if (preg_match('#^SOAPAction: "http://tempuri\.org/([A-Za-z]+)"$#', $header, $match)) {
				$operation = $match[1];
			}
		}

		$GLOBALS['carrier_label_aras_test_sent'][] = array('operation' => $operation, 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body);

		if (empty($answers[$operation])) {
			return array('status' => 0, 'body' => '', 'error' => 'Operation timed out', 'sent' => true);
		}

		$answer = array_shift($answers[$operation]);

		if (is_string($answer)) {
			$answer = array(200, carrier_label_aras_test_fixture($answer));
		}

		return array('status' => $answer[0], 'body' => $answer[1], 'error' => '', 'sent' => true);
	});
}

function carrier_label_aras_test_sent()
{
	return array_map(function ($sent) {
		return $sent['operation'];
	}, $GLOBALS['carrier_label_aras_test_sent']);
}

function carrier_label_aras_test_done()
{
	pg_label_aras_transport(null, true);
}

// A request's operation element as the service reads it: its name, its
// namespace, the names of its children in order and their values.
function carrier_label_aras_test_request($index)
{
	$dom = new DOMDocument();
	$dom->loadXML($GLOBALS['carrier_label_aras_test_sent'][$index]['body']);
	$xp = new DOMXPath($dom);
	$node = $xp->query('/*[local-name()="Envelope"]/*[local-name()="Body"]/*')->item(0);

	return array('name' => $node->localName, 'namespace' => $node->namespaceURI, 'values' => pg_label_aras_xml_array($node));
}

function carrier_label_aras_test_order_names($index)
{
	$dom = new DOMDocument();
	$dom->loadXML($GLOBALS['carrier_label_aras_test_sent'][$index]['body']);
	$xp = new DOMXPath($dom);
	$names = array();

	foreach ($xp->query('//*[local-name()="Order"]/*') as $child) {
		$names[] = $child->localName;
	}

	return $names;
}

// The GetCargoInfo answer with one waybill row of the columns given (none:
// an empty data set).
function carrier_label_aras_test_cargo_info($columns)
{
	$row = '';

	foreach ($columns as $name => $value) {
		$row .= '<' . $name . '>' . htmlspecialchars($value, ENT_XML1) . '</' . $name . '>';
	}

	$row = ($columns === array()) ? '' : '<IrsData diffgr:id="IrsData1" msdata:rowOrder="0">' . $row . '</IrsData>';

	return preg_replace('#<IrsData diffgr:id="IrsData1".*</IrsData>#s', $row, carrier_label_aras_test_fixture('cargo_info.xml'));
}

function test_carrier_label_aras_is_a_builtin_driver()
{
	pg_assert_true(pg_label_driver('aras') !== null);
	pg_assert_same(array('carriers' => array('aras'), 'formats' => array('pdf', 'zpl')), pg_label_info('aras'));

	foreach (array('test', 'create', 'label', 'cancel', 'track') as $operation) {
		pg_assert_true(pg_label_can('aras', $operation), $operation);
	}

	pg_assert_false(pg_label_can('aras', 'pickup'));

	$fields = array();

	foreach (pg_label_fields('aras') as $field) {
		$fields[$field['name']] = $field['type'] . (empty($field['required']) ? '' : ' required');
	}

	pg_assert_same(array(
		'username'       => 'text required',
		'password'       => 'secret required',
		'customer_code'  => 'text',
		'mode'           => 'select',
		'sender_address' => 'text',
		'label_format'   => 'select',
	), $fields);
}

function test_carrier_label_aras_test_lists_the_sender_addresses()
{
	$empty = carrier_label_aras_test_envelope('<GetAddressListResponse xmlns="http://tempuri.org/"><GetAddressListResult /></GetAddressListResponse>');
	carrier_label_aras_test_stub(array('GetAddressList' => array('addresses.xml', 'addresses.xml', 'addresses.xml', array(200, $empty))));
	$answer = pg_label_call('aras', 'test', array(carrier_label_aras_test_account(array('mode' => 'test'))));
	$sent = $GLOBALS['carrier_label_aras_test_sent'][0];
	$request = carrier_label_aras_test_request(0);
	$chosen = pg_label_call('aras', 'test', array(carrier_label_aras_test_account(array('sender_address' => '3302'))));
	$live = $GLOBALS['carrier_label_aras_test_sent'][1];
	$missing = pg_label_call('aras', 'test', array(carrier_label_aras_test_account(array('sender_address' => '9999'))));
	$none = pg_label_call('aras', 'test', array(carrier_label_aras_test_account()));
	carrier_label_aras_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('KADIKÖY', $answer['account'], 'the first address when none is named');
	pg_assert_contains('in test mode', implode(' ', $answer['log']));
	pg_assert_contains('3301, 3302', implode(' ', $answer['log']));
	pg_assert_same('POST', $sent['method']);
	pg_assert_same('https://customerservicestest.araskargo.com.tr/arascargoservice/arascargoservice.asmx', $sent['url']);
	pg_assert_same('https://customerws.araskargo.com.tr/arascargoservice.asmx', $live['url']);
	pg_assert_contains('SOAPAction: "http://tempuri.org/GetAddressList"', $sent['headers']);
	pg_assert_contains('Content-Type: text/xml; charset=utf-8', $sent['headers']);
	pg_assert_same('GetAddressList', $request['name']);
	pg_assert_same('http://tempuri.org/', $request['namespace']);
	pg_assert_same(array('userName' => array('magaza_ws'), 'password' => array('Ws-Parola-2026')), $request['values']);

	pg_assert_same('ÇANKAYA', $chosen['account']);
	pg_assert_false($missing['success']);
	pg_assert_contains('no sender address of this ID', $missing['error']);
	pg_assert_false($none['success']);
	pg_assert_contains('listed no sender address', $none['error']);
}

function test_carrier_label_aras_fault_is_a_plain_refusal_with_the_password_masked()
{
	$echo = carrier_label_aras_test_envelope('<soap:Fault><faultcode>soap:Server</faultcode><faultstring>Login failed: magaza_ws / Ws-Parola-2026</faultstring></soap:Fault>');
	carrier_label_aras_test_stub(array(
		'GetAddressList' => array(array(500, $echo)),
		'SetOrder'       => array(array(500, carrier_label_aras_test_fixture('fault.xml'))),
	));
	$answer = pg_label_call('aras', 'test', array(carrier_label_aras_test_account()));
	$create = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	carrier_label_aras_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('Aras Kargo refused the request.', $answer['error']);
	pg_assert_contains('****', $answer['error']);
	pg_assert_false(strpos(json_encode($answer), 'Ws-Parola-2026'), 'the password is masked');

	pg_assert_false($create['success']);
	pg_assert_false($create['unclear'], 'a SOAP fault is the service\'s own refusal');
	pg_assert_contains('Kullanıcı adı veya şifre hatalı.', $create['error']);
	pg_assert_same(array('GetAddressList', 'SetOrder'), carrier_label_aras_test_sent(), 'no label is asked for after the refusal');
}

function test_carrier_label_aras_create_registers_and_reads_the_label()
{
	carrier_label_aras_test_stub(array('SetOrder' => array('set_order.xml'), 'GetArasBarcode' => array('barcode.xml')));
	$answer = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	$sent = $GLOBALS['carrier_label_aras_test_sent'];
	$request = carrier_label_aras_test_request(0);
	$names = carrier_label_aras_test_order_names(0);
	$barcode = carrier_label_aras_test_request(1);
	carrier_label_aras_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('SetOrder', 'GetArasBarcode'), carrier_label_aras_test_sent());
	pg_assert_same('https://customerws.araskargo.com.tr/arascargoservice.asmx', $sent[0]['url']);

	$order = $request['values']['orderInfo'][0]['Order'][0];
	$code = $order['IntegrationCode'][0];
	pg_assert_true(preg_match('/^10042\d{6}$/', $code) === 1, 'the order number and six random digits: ' . $code);
	pg_assert_same($code, $answer['reference']);
	pg_assert_same(array('magaza_ws'), $request['values']['userName']);
	pg_assert_same(array('Ws-Parola-2026'), $request['values']['password']);
	pg_assert_same(array('Username' => array('magaza_ws'), 'Password' => array('Ws-Parola-2026'), 'integrationCode' => array($code)), $barcode['values']);

	pg_assert_same(array(
		'UserName', 'Password', 'TradingWaybillNumber', 'InvoiceNumber', 'ReceiverName', 'ReceiverAddress', 'ReceiverPhone1',
		'ReceiverCityName', 'ReceiverTownName', 'VolumetricWeight', 'Weight', 'PieceCount', 'IntegrationCode', 'Description',
		'PayorTypeCode', 'IsWorldWide', 'IsCod', 'PieceDetails',
	), $names, 'the WSDL\'s sequence, empty elements left out');

	$expected = array(
		'UserName'             => 'magaza_ws',
		'Password'             => 'Ws-Parola-2026',
		'TradingWaybillNumber' => '10042',
		'InvoiceNumber'        => '10042',
		'ReceiverName'         => 'Ayşe Yılmaz',
		'ReceiverAddress'      => 'Kızılay Mah. Atatürk Blv. No:5 Daire 3',
		'ReceiverPhone1'       => '05321234567',
		'ReceiverCityName'     => 'Ankara',
		'ReceiverTownName'     => 'Çankaya',
		'VolumetricWeight'     => '3',
		'Weight'               => '2',
		'PieceCount'           => '1',
		'Description'          => '2 x Mug, 1 x Plate & Bowl <set>',
		'PayorTypeCode'        => '1',
		'IsWorldWide'          => '0',
		'IsCod'                => '0',
	);

	foreach ($expected as $name => $value) {
		pg_assert_same(array($value), $order[$name], $name);
	}

	pg_assert_same(array(
		'VolumetricWeight' => array('3'),
		'Weight'           => array('2'),
		'BarcodeNumber'    => array($code),
		'Description'      => array('2 x Mug, 1 x Plate & Bowl <set>'),
	), $order['PieceDetails'][0]['PieceDetail'][0]);

	pg_assert_same('3401234567890', $answer['tracking_number']);
	pg_assert_same('aras', $answer['carrier']);
	pg_assert_same('', $answer['tracking_url'], 'the row takes the Aras page of the number');
	pg_assert_same('created', $answer['status']);
	pg_assert_same('90100424839201', $answer['barcode']);
	pg_assert_same('pdf', $answer['label_format']);
	pg_assert_same(0, strpos($answer['label'], '%PDF-'), 'the base64 PDF of the answer');
	pg_assert_false(strpos(json_encode($answer['raw']), 'JVBERi0'), 'the label is not logged');
	pg_assert_contains('registered', implode(' ', $answer['log']));
	pg_assert_false(strpos(implode(' ', $answer['log']), '(test)'));
}

function test_carrier_label_aras_create_zpl_in_test_mode_from_a_sender_address()
{
	carrier_label_aras_test_stub(array('SetOrder' => array('set_order.xml'), 'GetArasBarcode' => array('barcode.xml')));
	$account = carrier_label_aras_test_account(array('mode' => 'test', 'label_format' => 'zpl', 'sender_address' => '3302'));
	$answer = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), $account));
	$sent = $GLOBALS['carrier_label_aras_test_sent'];
	$names = carrier_label_aras_test_order_names(0);
	$order = carrier_label_aras_test_request(0)['values']['orderInfo'][0]['Order'][0];
	carrier_label_aras_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('https://customerservicestest.araskargo.com.tr/arascargoservice/arascargoservice.asmx', $sent[0]['url']);
	pg_assert_same('zpl', $answer['label_format']);
	pg_assert_contains('^XA', $answer['label']);
	pg_assert_contains('3401234567890', $answer['label']);
	pg_assert_same('SenderAccountAddressId', end($names), 'the last element of the sequence');
	pg_assert_same(array('3302'), $order['SenderAccountAddressId']);
	pg_assert_contains('(test)', implode(' ', $answer['log']));
}

function test_carrier_label_aras_refusals_before_a_call()
{
	carrier_label_aras_test_stub(array());
	$return = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(array('return' => true)), carrier_label_aras_test_account()));

	$abroad = carrier_label_aras_test_shipment();
	$abroad['recipient']['country'] = 'DE';
	$abroad = pg_label_call('aras', 'create', array($abroad, carrier_label_aras_test_account()));

	$no_phone = carrier_label_aras_test_shipment();
	$no_phone['recipient']['phone'] = '123';
	$no_phone = pg_label_call('aras', 'create', array($no_phone, carrier_label_aras_test_account()));

	$no_district = carrier_label_aras_test_shipment();
	$no_district['recipient']['city'] = '';
	$no_district = pg_label_call('aras', 'create', array($no_district, carrier_label_aras_test_account()));
	$sent = $GLOBALS['carrier_label_aras_test_sent'];
	carrier_label_aras_test_done();

	pg_assert_contains('return labels', $return['error']);
	pg_assert_contains('Turkey only', $abroad['error']);
	pg_assert_contains('phone number', $no_phone['error']);
	pg_assert_contains('district', $no_district['error']);

	foreach (array($return, $abroad, $no_phone, $no_district) as $answer) {
		pg_assert_false($answer['success']);
		pg_assert_false($answer['unclear']);
	}

	pg_assert_same(array(), $sent, 'nothing reaches Aras');

	$home = carrier_label_aras_test_shipment();
	$home['recipient']['country'] = '';
	pg_assert_same('', pg_label_aras_shipment_error($home), 'no country is a Turkish address');
}

function test_carrier_label_aras_refused_registration_is_plain()
{
	carrier_label_aras_test_stub(array('SetOrder' => array('set_order_refused.xml')));
	$answer = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	carrier_label_aras_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('Aras Kargo refused the request. (Alıcı telefon bilgisi hatalı.)', $answer['error']);
	pg_assert_same(array('SetOrder'), carrier_label_aras_test_sent(), 'no label is asked for');
}

function test_carrier_label_aras_unclear_registrations()
{
	carrier_label_aras_test_stub(array('SetOrder' => array(
		array(502, '<html>Bad gateway</html>'),
		array(200, carrier_label_aras_test_envelope('<SetOrderResponse xmlns="http://tempuri.org/"><SetOrderResult /></SetOrderResponse>')),
		array(404, '<html>Not found</html>'),
	)));
	$gateway = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	$silent = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	$not_found = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	$lost = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	carrier_label_aras_test_done();

	pg_assert_true($gateway['unclear'], 'a server error that is not a SOAP answer');
	pg_assert_true($silent['unclear'], 'an answer without a result');
	pg_assert_false($not_found['unclear'], 'turned away before anything was done');
	pg_assert_true($lost['unclear'], 'no answer after the request went out');
	pg_assert_same('The carrier label service could not be reached.', $lost['error']);
	pg_assert_contains('Integration code 10042', implode(' ', $lost['log']), 'the code to look for at Aras');

	foreach (array($gateway, $silent, $not_found, $lost) as $answer) {
		pg_assert_false($answer['success']);
	}
}

function test_carrier_label_aras_registration_without_a_label_is_cancelled()
{
	carrier_label_aras_test_stub(array(
		'SetOrder'       => array('set_order.xml', 'set_order.xml'),
		'GetArasBarcode' => array('barcode_refused.xml', 'barcode_refused.xml'),
		'CancelDispatch' => array('cancel.xml'),
	));
	$cancelled = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	$first = carrier_label_aras_test_request(0)['values']['orderInfo'][0]['Order'][0]['IntegrationCode'][0];
	$cancel = carrier_label_aras_test_request(2);
	$stuck = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	$second = carrier_label_aras_test_request(3)['values']['orderInfo'][0]['Order'][0]['IntegrationCode'][0];
	$sent = carrier_label_aras_test_sent();
	carrier_label_aras_test_done();

	pg_assert_same(array('SetOrder', 'GetArasBarcode', 'CancelDispatch', 'SetOrder', 'GetArasBarcode', 'CancelDispatch'), $sent);
	pg_assert_same(array('userName' => array('magaza_ws'), 'password' => array('Ws-Parola-2026'), 'integrationCode' => array($first)), $cancel['values']);

	pg_assert_false($cancelled['success']);
	pg_assert_false($cancelled['unclear'], 'taken back at Aras: it can be made again');
	pg_assert_contains('so it was cancelled there', $cancelled['error']);
	pg_assert_contains('(Bu entegrasyon koduna ait barkod bulunamadı.)', $cancelled['error']);

	pg_assert_false($stuck['success']);
	pg_assert_true($stuck['unclear'], 'registered, no label, not cancelled');
	pg_assert_contains('Look for integration code ' . $second, $stuck['error']);
}

function test_carrier_label_aras_number_without_a_readable_label()
{
	$barcode = preg_replace('#<ZebraPdf>.*</ZebraPdf>#s', '', carrier_label_aras_test_fixture('barcode.xml'));
	carrier_label_aras_test_stub(array('SetOrder' => array('set_order.xml'), 'GetArasBarcode' => array(array(200, $barcode))));
	$answer = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	carrier_label_aras_test_done();

	pg_assert_true($answer['success'], 'the shipment and its number stand');
	pg_assert_same('3401234567890', $answer['tracking_number']);
	pg_assert_same('', $answer['label']);
	pg_assert_contains('could not be read as PDF', implode(' ', $answer['log']));
}

function test_carrier_label_aras_barcode_stands_in_for_a_missing_tracking_number()
{
	$barcode = str_replace('<TrackingNumber>3401234567890</TrackingNumber>', '<TrackingNumber />', carrier_label_aras_test_fixture('barcode.xml'));
	carrier_label_aras_test_stub(array('SetOrder' => array('set_order.xml'), 'GetArasBarcode' => array(array(200, $barcode))));
	$answer = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	carrier_label_aras_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('90100424839201', $answer['tracking_number'], 'the barcode the branch scans');
}

function test_carrier_label_aras_label_prints_again()
{
	carrier_label_aras_test_stub(array('GetArasBarcode' => array('barcode.xml', 'barcode.xml', 'barcode_refused.xml')));
	$pdf = pg_label_call('aras', 'label', array('10042483920', 'pdf', carrier_label_aras_test_account()));
	$zpl = pg_label_call('aras', 'label', array('10042483920', 'zpl', carrier_label_aras_test_account()));
	$refused = pg_label_call('aras', 'label', array('10042483920', 'pdf', carrier_label_aras_test_account()));
	$count = count($GLOBALS['carrier_label_aras_test_sent']);
	$svg = pg_label_call('aras', 'label', array('10042483920', 'svg', carrier_label_aras_test_account()));
	$bad = pg_label_call('aras', 'label', array('../user', 'pdf', carrier_label_aras_test_account()));
	$after = count($GLOBALS['carrier_label_aras_test_sent']);
	carrier_label_aras_test_done();

	pg_assert_true($pdf['success'], $pdf['error']);
	pg_assert_same(0, strpos($pdf['label'], '%PDF-'));
	pg_assert_same('90100424839201', $pdf['barcode']);
	pg_assert_true($zpl['success'], $zpl['error']);
	pg_assert_same('zpl', $zpl['label_format']);
	pg_assert_contains('^XA', $zpl['label']);
	pg_assert_false($refused['success']);
	pg_assert_contains('gave no label for this shipment. (Bu entegrasyon koduna ait barkod bulunamadı.)', $refused['error']);
	pg_assert_false($svg['success']);
	pg_assert_contains('PDF or ZPL', $svg['error']);
	pg_assert_false($bad['success']);
	pg_assert_same($count, $after, 'neither is asked of Aras');
}

function test_carrier_label_aras_cancel()
{
	carrier_label_aras_test_stub(array('CancelDispatch' => array('cancel.xml', 'cancel_refused.xml', array(500, carrier_label_aras_test_fixture('fault.xml')))));
	$done = pg_label_call('aras', 'cancel', array('10042483920', carrier_label_aras_test_account()));
	$request = carrier_label_aras_test_request(0);
	$refused = pg_label_call('aras', 'cancel', array('10042483920', carrier_label_aras_test_account()));
	$fault = pg_label_call('aras', 'cancel', array('10042483920', carrier_label_aras_test_account()));
	$lost = pg_label_call('aras', 'cancel', array('10042483920', carrier_label_aras_test_account()));
	carrier_label_aras_test_done();

	pg_assert_true($done['success'], $done['error']);
	pg_assert_contains('cancelled', implode(' ', $done['log']));
	pg_assert_same(array('userName' => array('magaza_ws'), 'password' => array('Ws-Parola-2026'), 'integrationCode' => array('10042483920')), $request['values']);
	pg_assert_false($refused['success']);
	pg_assert_false($refused['unclear']);
	pg_assert_contains('Şubede işlem gören gönderi iptal edilemez.', $refused['error']);
	pg_assert_false($fault['success']);
	pg_assert_false($fault['unclear']);
	pg_assert_true($lost['unclear'], 'no answer after the cancellation went out');
}

function test_carrier_label_aras_track_reads_the_waybill()
{
	carrier_label_aras_test_stub(array('GetCargoInfo' => array('cargo_info.xml')));
	$answer = pg_label_call('aras', 'track', array('10042483920', carrier_label_aras_test_account()));
	$request = carrier_label_aras_test_request(0);
	carrier_label_aras_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('username' => array('magaza_ws'), 'password' => array('Ws-Parola-2026'), 'customerCode' => array('1234567'), 'integrationCode' => array('10042483920')), $request['values']);
	pg_assert_same('delivered', $answer['status']);
	pg_assert_same(gmmktime(11, 35, 0, 10, 28, 2026), $answer['status_at'], '28.10.2026 14:35 in Turkey');
	pg_assert_same('3401234567890', $answer['tracking_number']);
	pg_assert_same('aras', $answer['carrier']);
	pg_assert_same('https://kargotakip.araskargo.com.tr/mainpage.aspx?code=3401234567890', $answer['tracking_url']);
	pg_assert_contains('TESLİM EDİLDİ 6', implode(' ', $answer['log']));
}

function test_carrier_label_aras_track_statuses()
{
	$waybill = array('IRSALIYE_NUMARA' => '2026102700', 'IRSALIYE_TARIH' => '2026-10-27T00:00:00+03:00', 'KARGO_TAKIP_NO' => '3401234567890');

	carrier_label_aras_test_stub(array('GetCargoInfo' => array(
		array(200, carrier_label_aras_test_cargo_info($waybill + array('DURUM_KODU' => '2', 'DURUMU' => 'YOLDA'))),
		array(200, carrier_label_aras_test_cargo_info($waybill + array('DURUM_KODU' => '5', 'DURUMU' => 'DAĞITIMDA'))),
		array(200, carrier_label_aras_test_cargo_info($waybill + array('DURUMU' => 'TESLİMAT ŞUBESİNDE', 'IADE_SEBEBI' => 'ALICI ADRESTE BULUNAMADI'))),
		array(200, carrier_label_aras_test_cargo_info(array())),
	)));
	$account = carrier_label_aras_test_account();
	$moving = pg_label_call('aras', 'track', array('10042483920', $account));
	$out = pg_label_call('aras', 'track', array('10042483920', $account));
	$back = pg_label_call('aras', 'track', array('10042483920', $account));
	$nothing = pg_label_call('aras', 'track', array('10042483920', $account));
	$count = count($GLOBALS['carrier_label_aras_test_sent']);
	$no_code = pg_label_call('aras', 'track', array('10042483920', carrier_label_aras_test_account(array('customer_code' => ''))));
	$after = count($GLOBALS['carrier_label_aras_test_sent']);
	carrier_label_aras_test_done();

	pg_assert_same('in_transit', $moving['status']);
	pg_assert_same(gmmktime(21, 0, 0, 10, 26, 2026), $moving['status_at'], 'the waybill date');
	pg_assert_same('out_for_delivery', $out['status']);
	pg_assert_same(0, $out['status_at'], 'the row dates no departure: the job takes the moment it learnt of it');
	pg_assert_same('returned', $back['status']);
	pg_assert_same(0, $back['status_at']);
	pg_assert_false($nothing['success']);
	pg_assert_false($nothing['unclear']);
	pg_assert_contains('no status for this shipment yet', $nothing['error']);
	pg_assert_false($no_code['success']);
	pg_assert_contains('customer code', $no_code['error']);
	pg_assert_same($count, $after, 'without the customer code Aras is not asked');

	$row = function ($columns) {
		return array_map(function ($value) {
			return array($value);
		}, $columns);
	};

	pg_assert_same('delivered', pg_label_aras_status($row(array('DURUMU' => 'Teslim Edildi'))));
	pg_assert_same('delivered', pg_label_aras_status($row(array('TESLIM_TARIHI' => '28.10.2026', 'TESLIM_ALAN' => 'KAPICI'))));
	pg_assert_same('in_transit', pg_label_aras_status($row(array('TESLIM_TARIHI' => '28.10.2026', 'KARGO_TAKIP_NO' => '3401234567890'))), 'a date without who took it');
	pg_assert_same('in_transit', pg_label_aras_status($row(array('DURUMU' => 'TESLİM EDİLEMEDİ', 'KARGO_TAKIP_NO' => '3401234567890'))));
	pg_assert_same('in_transit', pg_label_aras_status($row(array('DURUMU' => 'TESLİMAT ŞUBESİNDE', 'IRSALIYE_NUMARA' => '2026102700'))));
	pg_assert_same('out_for_delivery', pg_label_aras_status($row(array('DURUMU' => 'Dağıtıma Çıktı'))));
	pg_assert_same('returned', pg_label_aras_status($row(array('DURUMU' => 'İADE SÜRECİNDE', 'TESLIM_TARIHI' => '28.10.2026', 'TESLIM_ALAN' => 'ÖRNEK MAĞAZA'))));
	pg_assert_same('in_transit', pg_label_aras_status($row(array('IADE_SEBEBI' => '-', 'KARGO_TAKIP_NO' => '3401234567890'))), 'a return reason without words');
	pg_assert_same('in_transit', pg_label_aras_status($row(array('IADE_SEBEBI' => 'Yok', 'KARGO_TAKIP_NO' => '3401234567890'))), 'a word for none');
	pg_assert_same('returned', pg_label_aras_status($row(array('IADE_SEBEBI' => 'ALICI KABUL ETMEDİ', 'DURUMU' => 'TESLİM EDİLDİ', 'TESLIM_ALAN' => 'ÖRNEK MAĞAZA'))), 'delivered back to the sender is a return');
	pg_assert_same('', pg_label_aras_status($row(array('KARGO_TAKIP_NO' => '0'))));
	pg_assert_same('', pg_label_aras_status(null));
}

function test_carrier_label_aras_helpers()
{
	pg_assert_same('05321234567', pg_label_aras_phone('+90 (532) 123 45 67'));
	pg_assert_same('05321234567', pg_label_aras_phone('0532 123 4567'));
	pg_assert_same('05321234567', pg_label_aras_phone('5321234567'));
	pg_assert_same('02125551122', pg_label_aras_phone('0090 212 555 11 22'));
	pg_assert_same('123', pg_label_aras_phone('123'));

	pg_assert_same('10042483920', pg_label_aras_integration_code('10042', 483920));
	pg_assert_same('10042000007', pg_label_aras_integration_code('10042', 7));
	pg_assert_same('0077000005', pg_label_aras_integration_code('A-77', 5), 'at least ten digits');
	pg_assert_same('6789012345000001', pg_label_aras_integration_code('123456789012345', 1), 'the last ten digits of a long number');
	pg_assert_same('10042483920', pg_label_aras_code(' 10042483920 '));
	pg_assert_same('', pg_label_aras_code('10042/483920'));

	pg_assert_same(gmmktime(11, 35, 0, 10, 28, 2026), pg_label_aras_time('28.10.2026', '14:35'));
	pg_assert_same(gmmktime(6, 5, 30, 10, 28, 2026), pg_label_aras_time('28/10/2026 09:05:30'));
	pg_assert_same(gmmktime(21, 0, 0, 10, 26, 2026), pg_label_aras_time('2026-10-27T00:00:00+03:00'));
	pg_assert_same(gmmktime(8, 0, 0, 10, 27, 2026), pg_label_aras_time('2026-10-27T08:00:00Z'));
	pg_assert_same(gmmktime(21, 0, 0, 10, 26, 2026), pg_label_aras_time('2026-10-27'), 'midnight in Turkey');
	pg_assert_same(0, pg_label_aras_time('31.02.2026'));
	pg_assert_same(0, pg_label_aras_time(''));
	pg_assert_same(0, pg_label_aras_time('01.01.0001'));

	pg_assert_same('TESLIM EDILDI', pg_label_aras_fold('Teslim Edildi'));
	pg_assert_same('DAGITIMDA', pg_label_aras_fold('DAĞITIMDA'));
	pg_assert_same('IADE SURECINDE', pg_label_aras_fold(" İade \n Sürecinde "));

	pg_assert_same('<a>x &amp; y</a><b>&lt;z&gt;</b>', pg_label_aras_xml_elements(array('a' => "x & y\x01", 'b' => '<z>', 'c' => '')), 'escaped, control characters and empty elements dropped');
	pg_assert_same(array('envelope' => false, 'fault' => '', 'result' => null), pg_label_aras_parse('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>', 'SetOrder'), 'a document type is not read');
	pg_assert_same(array('envelope' => false, 'fault' => '', 'result' => null), pg_label_aras_parse('not xml', 'SetOrder'));
}

// A long Turkish result message is cut on a character, not a byte: a broken
// last letter would make htmlspecialchars() print the whole notice empty.
function test_carrier_label_aras_long_refusal_is_cut_on_a_character()
{
	$refused = str_replace('Alıcı telefon bilgisi hatalı.', 'A' . str_repeat('ş', 200), carrier_label_aras_test_fixture('set_order_refused.xml'));
	carrier_label_aras_test_stub(array('SetOrder' => array(array(200, $refused))));
	$answer = pg_label_call('aras', 'create', array(carrier_label_aras_test_shipment(), carrier_label_aras_test_account()));
	carrier_label_aras_test_done();

	pg_assert_false($answer['success']);
	pg_assert_true(mb_check_encoding($answer['error'], 'UTF-8'), 'the error is valid UTF-8');
	pg_assert_contains('A' . str_repeat('ş', 149), htmlspecialchars($answer['error'], ENT_QUOTES, 'UTF-8'));
}
