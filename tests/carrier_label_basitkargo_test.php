<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Basit Kargo label driver (includes/shipping/basitkargo.php),
 * every operation called through pg_label_call(). Basit Kargo has no test
 * mode and is never called: a stand-in transport answers with the answers of
 * tests/fixtures/basitkargo/, built in the shape of its public reference
 * (basitkargo.com/api), and keeps the requests it was sent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function carrier_label_basitkargo_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/basitkargo/' . $name);
}

function carrier_label_basitkargo_test_account($changes = array())
{
	return array_merge(array(
		'api_token'  => 'bk-token-9876543210',
		'handler'    => 'ECONOMIC',
		'address_id' => '',
		'brand_id'   => '',
	), $changes);
}

// Answers each request with the next answer queued for it: "METHOD path"
// (the path without the API base) => list of array(status, body[, headers])
// or a fixture file name for a 200 answer. A request with nothing queued
// times out after it was sent.
function carrier_label_basitkargo_test_stub($answers)
{
	$GLOBALS['carrier_label_basitkargo_test_sent'] = array();

	pg_label_basitkargo_transport(function ($method, $url, $headers, $body) use (&$answers) {
		$path = (strncmp($url, 'https://basitkargo.com/api', 26) === 0) ? substr($url, 26) : $url;
		$key = $method . ' ' . $path;
		$GLOBALS['carrier_label_basitkargo_test_sent'][] = array('request' => $key, 'headers' => $headers, 'body' => ($body === null) ? null : json_decode($body, true));

		if (empty($answers[$key])) {
			return array('status' => 0, 'body' => '', 'error' => 'Operation timed out', 'sent' => true, 'headers' => '');
		}

		$answer = array_shift($answers[$key]);

		if (is_string($answer)) {
			$answer = array(200, carrier_label_basitkargo_test_fixture($answer));
		}

		return array('status' => $answer[0], 'body' => $answer[1], 'error' => '', 'sent' => true, 'headers' => isset($answer[2]) ? $answer[2] : '');
	});
}

function carrier_label_basitkargo_test_sent()
{
	return array_map(function ($sent) {
		return $sent['request'];
	}, $GLOBALS['carrier_label_basitkargo_test_sent']);
}

function carrier_label_basitkargo_test_done()
{
	pg_label_basitkargo_transport(null, true);
}

function carrier_label_basitkargo_test_shipment()
{
	return array(
		'order_number' => '10042',
		'recipient'    => array(
			'name'       => 'Ayşe Yılmaz',
			'email'      => 'ayse@example.com',
			'phone'      => '+905051234567',
			'address_1'  => 'Kıbrıs Şehitleri Cad. No: 12',
			'address_2'  => 'Daire 4',
			'city'       => 'Konak',
			'state'      => 'İzmir',
			'state_code' => 'TR-35',
			'zip'        => '35220',
			'country'    => 'TR',
		),
		'parcel'       => array('length' => 30, 'width' => 20, 'height' => 10.5, 'weight' => 1.25),
		'items'        => array(array('title' => 'Mug', 'quantity' => 2), array('title' => '', 'quantity' => 1)),
		'total'        => 34990,
		'currency'     => 'TRY',
	);
}

function test_carrier_label_basitkargo_is_a_builtin_driver()
{
	pg_assert_true(pg_label_driver('basitkargo') !== null);
	pg_assert_same(array('carriers' => array('yurtici', 'surat', 'aras', 'mng', 'ptt'), 'formats' => array('svg')), pg_label_info('basitkargo'));

	foreach (array('test', 'create', 'label', 'cancel', 'track') as $operation) {
		pg_assert_true(pg_label_can('basitkargo', $operation), $operation);
	}

	pg_assert_false(pg_label_can('basitkargo', 'pickup'));

	$fields = array();

	foreach (pg_label_fields('basitkargo') as $field) {
		$fields[$field['name']] = $field['type'];
	}

	pg_assert_same(array('api_token' => 'secret', 'handler' => 'text', 'address_id' => 'text', 'brand_id' => 'text'), $fields);
}

function test_carrier_label_basitkargo_test_reads_the_firm_status()
{
	carrier_label_basitkargo_test_stub(array('GET /firm/status' => array('firm_status.json')));

	$answer = pg_label_call('basitkargo', 'test', array(carrier_label_basitkargo_test_account()));
	$sent = $GLOBALS['carrier_label_basitkargo_test_sent'];
	carrier_label_basitkargo_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same('Kurumsal (Onaylandı)', $answer['account']);
	pg_assert_contains('Authorization: Bearer bk-token-9876543210', $sent[0]['headers']);
}

// A service that echoes the token back cannot put it into the notice or the log.
function test_carrier_label_basitkargo_refused_token_is_masked()
{
	carrier_label_basitkargo_test_stub(array('GET /firm/status' => array(array(401, '{"message":"Geçersiz token: bk-token-9876543210"}'))));

	$answer = pg_label_call('basitkargo', 'test', array(carrier_label_basitkargo_test_account()));
	carrier_label_basitkargo_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('Geçersiz token', $answer['error']);
	pg_assert_false(strpos(json_encode($answer), 'bk-token-9876543210'), 'the token is masked');
	pg_assert_contains('****', $answer['error']);
}

function test_carrier_label_basitkargo_create_opens_the_order_and_reads_the_svg()
{
	carrier_label_basitkargo_test_stub(array(
		'POST /v2/order/barcode'   => array('order_barcode.json'),
		'GET /label/svg/888-6AR-OUP' => array('label.svg'),
	));

	$answer = pg_label_call('basitkargo', 'create', array(carrier_label_basitkargo_test_shipment(), carrier_label_basitkargo_test_account(array('address_id' => 'abc-123'))));
	$sent = $GLOBALS['carrier_label_basitkargo_test_sent'];
	carrier_label_basitkargo_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('POST /v2/order/barcode', 'GET /label/svg/888-6AR-OUP'), carrier_label_basitkargo_test_sent(), 'two calls');
	pg_assert_same('888-6AR-OUP', $answer['reference']);
	pg_assert_same('SRT7700112233', $answer['tracking_number'], 'the carrier\'s number');
	pg_assert_same('BK4815162342', $answer['barcode']);
	pg_assert_same('surat', $answer['carrier']);
	pg_assert_same('created', $answer['status']);
	pg_assert_same('svg', $answer['label_format']);
	pg_assert_same(carrier_label_basitkargo_test_fixture('label.svg'), $answer['label']);

	$body = $sent[0]['body'];
	pg_assert_same('OUTGOING', $body['type']);
	pg_assert_same('ECONOMIC', $body['handlerCode']);
	pg_assert_same('10042', $body['content']['code']);
	pg_assert_same(array(array('name' => 'Mug', 'quantity' => 2)), $body['content']['items']);
	pg_assert_same(array(array('height' => 10.5, 'width' => 20, 'depth' => 30, 'weight' => 1.25)), $body['content']['packages']);
	pg_assert_same(array('name' => 'Ayşe Yılmaz', 'phone' => '+905051234567', 'email' => 'ayse@example.com', 'city' => 'İzmir', 'town' => 'Konak', 'address' => 'Kıbrıs Şehitleri Cad. No: 12 Daire 4'), $body['client']);
	pg_assert_same(349.9, $body['orderTotal']);
	pg_assert_same('abc-123', $body['addressId']);
	pg_assert_false(isset($body['brandId']));

	$keys = preg_grep('/^Idempotency-Key: [0-9a-f-]{36}$/', $sent[0]['headers']);
	pg_assert_same(1, count($keys), 'the create carries an idempotency key');
}

function test_carrier_label_basitkargo_return_is_incoming_with_the_chosen_carrier()
{
	carrier_label_basitkargo_test_stub(array(
		'POST /v2/order/barcode'     => array('order_barcode.json'),
		'GET /label/svg/888-6AR-OUP' => array(array(200, '<html>not a label</html>')),
	));

	$shipment = carrier_label_basitkargo_test_shipment();
	$shipment['return'] = true;
	$shipment['service'] = 'self_aras';
	$answer = pg_label_call('basitkargo', 'create', array($shipment, carrier_label_basitkargo_test_account()));
	$sent = $GLOBALS['carrier_label_basitkargo_test_sent'];
	carrier_label_basitkargo_test_done();

	pg_assert_true($answer['success'], 'the order is bought; label() fetches the label again');
	pg_assert_same('', $answer['label']);
	pg_assert_same('INCOMING', $sent[0]['body']['type']);
	pg_assert_same('SELF_ARAS', $sent[0]['body']['handlerCode']);
}

function test_carrier_label_basitkargo_refusal_is_plain()
{
	carrier_label_basitkargo_test_stub(array('POST /v2/order/barcode' => array(array(400, 'Geçerli bir telefon numarası giriniz.'))));

	$answer = pg_label_call('basitkargo', 'create', array(carrier_label_basitkargo_test_shipment(), carrier_label_basitkargo_test_account()));
	carrier_label_basitkargo_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('Geçerli bir telefon numarası giriniz.', $answer['error']);
}

function test_carrier_label_basitkargo_abroad_is_refused_before_a_call()
{
	carrier_label_basitkargo_test_stub(array());

	$shipment = carrier_label_basitkargo_test_shipment();
	$shipment['recipient']['country'] = 'DE';
	$answer = pg_label_call('basitkargo', 'create', array($shipment, carrier_label_basitkargo_test_account()));
	$sent = carrier_label_basitkargo_test_sent();
	carrier_label_basitkargo_test_done();

	pg_assert_false($answer['success']);
	pg_assert_same(array(), $sent);
}

// A timeout after the request went out is asked once more with the same
// key; a second silence or an order still in progress (409) is unclear.
function test_carrier_label_basitkargo_unclear_answers()
{
	carrier_label_basitkargo_test_stub(array());

	$answer = pg_label_call('basitkargo', 'create', array(carrier_label_basitkargo_test_shipment(), carrier_label_basitkargo_test_account()));
	$sent = $GLOBALS['carrier_label_basitkargo_test_sent'];
	carrier_label_basitkargo_test_done();

	pg_assert_false($answer['success']);
	pg_assert_true($answer['unclear']);
	pg_assert_same(2, count($sent));
	pg_assert_same(array_values(preg_grep('/^Idempotency-Key/', $sent[0]['headers'])), array_values(preg_grep('/^Idempotency-Key/', $sent[1]['headers'])), 'the same key');

	carrier_label_basitkargo_test_stub(array('POST /v2/order/barcode' => array(array(409, ''))));
	$answer = pg_label_call('basitkargo', 'create', array(carrier_label_basitkargo_test_shipment(), carrier_label_basitkargo_test_account()));
	carrier_label_basitkargo_test_done();
	pg_assert_true($answer['unclear'], '409');

	carrier_label_basitkargo_test_stub(array('POST /v2/order/barcode' => array(array(502, '<html>Bad gateway</html>'))));
	$answer = pg_label_call('basitkargo', 'create', array(carrier_label_basitkargo_test_shipment(), carrier_label_basitkargo_test_account()));
	carrier_label_basitkargo_test_done();
	pg_assert_true($answer['unclear'], '5xx');
}

function test_carrier_label_basitkargo_429_waits_for_retry_after_once()
{
	carrier_label_basitkargo_test_stub(array(
		'POST /v2/order/barcode'     => array(array(429, 'Çok fazla istek', "HTTP/1.1 429 Too Many Requests\r\nRetry-After: 7\r\n"), 'order_barcode.json'),
		'GET /label/svg/888-6AR-OUP' => array('label.svg'),
	));

	$answer = pg_label_call('basitkargo', 'create', array(carrier_label_basitkargo_test_shipment(), carrier_label_basitkargo_test_account()));
	$sent = $GLOBALS['carrier_label_basitkargo_test_sent'];
	$waits = pg_label_basitkargo_waits();
	carrier_label_basitkargo_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(3, count($sent), 'three calls');
	pg_assert_same(array(7), $waits, 'waited as asked');
	pg_assert_same(array_values(preg_grep('/^Idempotency-Key/', $sent[0]['headers'])), array_values(preg_grep('/^Idempotency-Key/', $sent[1]['headers'])), 'the same key');

	// A long wait is not taken in the request; a second 429 is not retried.
	carrier_label_basitkargo_test_stub(array('GET /v2/order/888-6AR-OUP' => array(array(429, '', "Retry-After: 120\r\n"))));
	$answer = pg_label_call('basitkargo', 'track', array('888-6AR-OUP', carrier_label_basitkargo_test_account()));
	$sent = carrier_label_basitkargo_test_sent();
	carrier_label_basitkargo_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('120', $answer['error']);
	pg_assert_same(1, count($sent));
}

function test_carrier_label_basitkargo_retry_after_and_window()
{
	pg_assert_same(7, pg_label_basitkargo_retry_after("HTTP/1.1 429\r\nretry-after: 7\r\n", 1000));
	pg_assert_same(30, pg_label_basitkargo_retry_after("Retry-After: " . gmdate('D, d M Y H:i:s', 1030) . " GMT\r\n", 1000));
	pg_assert_same(0, pg_label_basitkargo_retry_after('', 1000));

	$times = array_fill(0, 600, 100.0);
	pg_assert_same(0.0, pg_label_basitkargo_window_wait(array_slice($times, 0, 599), 101.0), 'under the limit');
	pg_assert_same(59.0, pg_label_basitkargo_window_wait($times, 101.0), 'the 600th call in the minute waits');
	pg_assert_same(0.0, pg_label_basitkargo_window_wait($times, 160.5), 'a minute later');
}

function test_carrier_label_basitkargo_label_and_track()
{
	carrier_label_basitkargo_test_stub(array(
		'GET /label/svg/888-6AR-OUP' => array('label.svg'),
		'GET /v2/order/888-6AR-OUP'  => array('order_shipped.json', 'order_lost.json'),
	));

	$label = pg_label_call('basitkargo', 'label', array('888-6AR-OUP', 'svg', carrier_label_basitkargo_test_account()));
	$track = pg_label_call('basitkargo', 'track', array('888-6AR-OUP', carrier_label_basitkargo_test_account()));
	$lost = pg_label_call('basitkargo', 'track', array('888-6AR-OUP', carrier_label_basitkargo_test_account()));
	$pdf = pg_label_call('basitkargo', 'label', array('888-6AR-OUP', 'pdf', carrier_label_basitkargo_test_account()));
	$bad = pg_label_call('basitkargo', 'label', array('../firm/balance', 'svg', carrier_label_basitkargo_test_account()));
	$sent = carrier_label_basitkargo_test_sent();
	carrier_label_basitkargo_test_done();

	pg_assert_true($label['success'], $label['error']);
	pg_assert_same('svg', $label['label_format']);

	pg_assert_true($track['success'], $track['error']);
	pg_assert_same('out_for_delivery', $track['status']);
	pg_assert_same(strtotime('2026-10-31T08:40:10'), $track['status_at']);
	pg_assert_same('SRT7700112233', $track['tracking_number']);
	pg_assert_same('surat', $track['carrier'], 'an own-contract code names the carrier too');

	pg_assert_true($lost['success']);
	pg_assert_same('exception', $lost['status']);

	pg_assert_false($pdf['success'], 'SVG only');
	pg_assert_false($bad['success']);
	pg_assert_same(3, count($sent), 'nothing sent for a PDF or a reference of another shape');

	foreach (array('NEW' => 'created', 'READY_TO_SHIP' => 'created', 'SHIPPED' => 'in_transit', 'DELAYED' => 'in_transit',
		'DELIVERED' => 'delivered', 'RETURNING' => 'returned', 'RETURNED' => 'returned', 'NEEDS_SUPPORT' => 'exception', 'ODD' => '') as $code => $status) {
		pg_assert_same($status, pg_label_basitkargo_status($code), $code);
	}
}

function test_carrier_label_basitkargo_cancel_takes_the_barcode_back()
{
	carrier_label_basitkargo_test_stub(array(
		'GET /v2/order/888-6AR-OUP'             => array('order_shipped.json'),
		'DELETE /order/barcode/BK4815162342'    => array(array(200, '')),
		'DELETE /order/888-6AR-OUP'             => array(array(200, '')),
	));

	$answer = pg_label_call('basitkargo', 'cancel', array('888-6AR-OUP', carrier_label_basitkargo_test_account()));
	$sent = carrier_label_basitkargo_test_sent();
	carrier_label_basitkargo_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('GET /v2/order/888-6AR-OUP', 'DELETE /order/barcode/BK4815162342', 'DELETE /order/888-6AR-OUP'), $sent);

	// Collected already: refused, and the order is not deleted.
	carrier_label_basitkargo_test_stub(array(
		'GET /v2/order/888-6AR-OUP'          => array('order_shipped.json'),
		'DELETE /order/barcode/BK4815162342' => array(array(400, '{"message":"Kargo firması teslim aldı."}')),
	));

	$answer = pg_label_call('basitkargo', 'cancel', array('888-6AR-OUP', carrier_label_basitkargo_test_account()));
	$sent = carrier_label_basitkargo_test_sent();
	carrier_label_basitkargo_test_done();

	pg_assert_false($answer['success']);
	pg_assert_false($answer['unclear']);
	pg_assert_contains('teslim aldı', $answer['error']);
	pg_assert_same(2, count($sent));

	// An order without a barcode is only deleted.
	carrier_label_basitkargo_test_stub(array(
		'GET /v2/order/888-6AR-OUP'    => array('order_new.json'),
		'DELETE /order/888-6AR-OUP'    => array(array(200, '')),
	));

	$answer = pg_label_call('basitkargo', 'cancel', array('888-6AR-OUP', carrier_label_basitkargo_test_account()));
	$sent = carrier_label_basitkargo_test_sent();
	carrier_label_basitkargo_test_done();

	pg_assert_true($answer['success'], $answer['error']);
	pg_assert_same(array('GET /v2/order/888-6AR-OUP', 'DELETE /order/888-6AR-OUP'), $sent);
}
