<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the İleti Merkezi SMS driver (includes/sms/iletimerkezi.php): the
 * send and sender list requests, the answer codes, when a send may be tried
 * again or is uncertain, and the sender list. İleti Merkezi is not called.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/sms/iletimerkezi.php');

function sms_iletimerkezi_test_app()
{
	return array(
		'user'            => 'a1b2c3d4e5f6',
		'password'        => '0f1e2d3c4b5a69788796a5b4c3d2e1f0',
		'password_stored' => true,
		'sender'          => 'PINEGRAP',
		'enabled'         => true,
	);
}

// Turkish mobile numbers go in twelve digits; others are not passed on.
function test_sms_iletimerkezi_number()
{
	pg_assert_same('905321234567', pg_sms_iletimerkezi_number('905321234567'));
	pg_assert_same('', pg_sms_iletimerkezi_number('902121234567'));
	pg_assert_same('', pg_sms_iletimerkezi_number('4915112345678'));
	pg_assert_same('', pg_sms_iletimerkezi_number('90532123456'));
}

// The send request: the panel's key and hash as they are, the order with the
// sender, the informational İYS flag and the recipient under message.
function test_sms_iletimerkezi_send_request()
{
	$request = pg_sms_iletimerkezi_send_request(sms_iletimerkezi_test_app(), '905321234567', 'Siparişiniz alındı: 3/5 ₺', false);

	pg_assert_same('https://api.iletimerkezi.com/v1/send-sms/json', $request['url']);
	pg_assert_true(in_array('Content-Type: application/json', $request['headers'], true));

	pg_assert_same(
		'{"request":{"authentication":{"key":"a1b2c3d4e5f6","hash":"0f1e2d3c4b5a69788796a5b4c3d2e1f0"},"order":{"sender":"PINEGRAP","iys":"0","message":{"text":"Siparişiniz alındı: 3/5 ₺","receipents":{"number":["905321234567"]}}}}}',
		$request['body']);

	$commercial = json_decode(pg_sms_iletimerkezi_send_request(sms_iletimerkezi_test_app(), '905321234567', 'Kampanya', true)['body'], true);
	pg_assert_same('1', $commercial['request']['order']['iys']);
	pg_assert_same('BIREYSEL', $commercial['request']['order']['iysList']);
}

// The sender list request carries the authentication block alone.
function test_sms_iletimerkezi_senders_request()
{
	$request = pg_sms_iletimerkezi_senders_request(sms_iletimerkezi_test_app());

	pg_assert_same('https://api.iletimerkezi.com/v1/get-sender/json', $request['url']);
	pg_assert_same('{"request":{"authentication":{"key":"a1b2c3d4e5f6","hash":"0f1e2d3c4b5a69788796a5b4c3d2e1f0"}}}', $request['body']);
}

// Success: code 200 with an order id, which may come as a long number.
function test_sms_iletimerkezi_send_success()
{
	$result = pg_sms_iletimerkezi_send_parse(200, '{"response":{"status":{"code":200,"message":"İşlem başarılı"},"order":{"id":"312891245"}}}');
	pg_assert_true($result['ok']);
	pg_assert_same('312891245', $result['id']);
	pg_assert_same('200', $result['code']);

	$long = pg_sms_iletimerkezi_send_parse(200, '{"response":{"status":{"code":"200","message":"OK"},"order":{"id":31289124512345678901234}}}');
	pg_assert_same('31289124512345678901234', $long['id']);
}

// 200 without an order id: it may have been taken, so it is not sent again.
function test_sms_iletimerkezi_send_success_without_id()
{
	$result = pg_sms_iletimerkezi_send_parse(200, '{"response":{"status":{"code":200,"message":"OK"}}}');
	pg_assert_false($result['ok']);
	pg_assert_false($result['retry']);
	pg_assert_true($result['uncertain']);
}

// A refused key or hash, with İleti Merkezi's JSON or bare in front of it.
function test_sms_iletimerkezi_send_auth_refused()
{
	foreach (array(
		array(401, '{"response":{"status":{"code":401,"message":"Üyelik bilgileri hatalı"}}}'),
		array(200, '{"response":{"status":{"code":"401","message":"Üyelik bilgileri hatalı"}}}'),
		array(401, ''),
		array(403, '<html>Forbidden</html>'),
	) as $answer) {
		$result = pg_sms_iletimerkezi_send_parse($answer[0], $answer[1]);
		pg_assert_false($result['ok'], $answer[1]);
		pg_assert_same('401', $result['code'], $answer[1]);
		pg_assert_false($result['retry'], $answer[1]);
		pg_assert_false($result['uncertain'], $answer[1]);
		pg_assert_contains('API', $result['error'], $answer[1]);
	}
}

// A sender not approved is a refusal that took nothing.
function test_sms_iletimerkezi_send_sender_refused()
{
	$result = pg_sms_iletimerkezi_send_parse(450, '{"response":{"status":{"code":450,"message":"Gönderilen başlık kullanıma uygun değil"}}}');
	pg_assert_same('450', $result['code']);
	pg_assert_false($result['retry']);
	pg_assert_false($result['uncertain']);
	pg_assert_contains('sender name', $result['error']);
}

// 503 is İleti Merkezi's "temporarily out of service": tried again later, as
// is a bare 429 or 503 in front of it. A 502 or 504 may come after it took
// the message.
function test_sms_iletimerkezi_send_try_later()
{
	$coded = pg_sms_iletimerkezi_send_parse(503, '{"response":{"status":{"code":503,"message":"Sunucu geçici olarak servis dışı"}}}');
	pg_assert_true($coded['retry']);
	pg_assert_false($coded['uncertain']);

	foreach (array(429, 503) as $status) {
		$bare = pg_sms_iletimerkezi_send_parse($status, 'Too busy');
		pg_assert_true($bare['retry'], (string) $status);
		pg_assert_false($bare['uncertain'], (string) $status);
	}

	foreach (array(502, 504, 500) as $status) {
		$gateway = pg_sms_iletimerkezi_send_parse($status, '<html>Bad gateway</html>');
		pg_assert_false($gateway['retry'], (string) $status);
		pg_assert_true($gateway['uncertain'], (string) $status);
	}
}

// Other codes are refusals that are not tried again, unknown ones keep
// İleti Merkezi's message.
function test_sms_iletimerkezi_send_other_codes()
{
	foreach (array('400', '402', '451', '452', '453', '454', '466', '468') as $code) {
		$result = pg_sms_iletimerkezi_send_parse(200, '{"response":{"status":{"code":' . $code . ',"message":"x"}}}');
		pg_assert_same($code, $result['code'], $code);
		pg_assert_false($result['retry'], $code);
		pg_assert_false($result['uncertain'], $code);
		pg_assert_true($result['error'] !== '', $code);
	}

	$unknown = pg_sms_iletimerkezi_send_parse(200, '{"response":{"status":{"code":499,"message":"<b>Yeni</b>  hata"}}}');
	pg_assert_contains('499', $unknown['error']);
	pg_assert_contains('Yeni hata', $unknown['error']);
}

// An answer that is not İleti Merkezi's JSON: a success status may come after
// it took the message; a plain 4xx took nothing.
function test_sms_iletimerkezi_send_malformed()
{
	foreach (array('', 'OK', '{"response":{}}', '{"status":{"code":200}}', '[1,2]') as $body) {
		$result = pg_sms_iletimerkezi_send_parse(200, $body);
		pg_assert_false($result['ok'], $body);
		pg_assert_same('', $result['code'], $body);
		pg_assert_false($result['retry'], $body);
		pg_assert_true($result['uncertain'], $body);
	}

	$not_found = pg_sms_iletimerkezi_send_parse(404, '<html>Not found</html>');
	pg_assert_false($not_found['retry']);
	pg_assert_false($not_found['uncertain']);
}

// Nothing came back: retried only when the request never left.
function test_sms_iletimerkezi_send_no_answer()
{
	$never = pg_sms_iletimerkezi_send_parse(0, '', 'Could not resolve host', true);
	pg_assert_true($never['retry']);
	pg_assert_false($never['uncertain']);

	$timeout = pg_sms_iletimerkezi_send_parse(0, '', 'Operation timed out', false);
	pg_assert_false($timeout['retry']);
	pg_assert_true($timeout['uncertain']);
}

// The sender list: an array, a single name, an empty list.
function test_sms_iletimerkezi_senders_parse()
{
	$list = pg_sms_iletimerkezi_senders_parse(200, '{"response":{"status":{"code":200,"message":"İşlem başarılı"},"senders":{"sender":["eMarka Test","PINEGRAP","PINEGRAP"," "]}}}');
	pg_assert_true($list['ok']);
	pg_assert_same(array('eMarka Test', 'PINEGRAP'), $list['senders']);

	$single = pg_sms_iletimerkezi_senders_parse(200, '{"response":{"status":{"code":200},"senders":{"sender":"KODPEN"}}}');
	pg_assert_same(array('KODPEN'), $single['senders']);

	$empty = pg_sms_iletimerkezi_senders_parse(200, '{"response":{"status":{"code":200},"senders":{"sender":[]}}}');
	pg_assert_true($empty['ok']);
	pg_assert_same(array(), $empty['senders']);
}

// Refused apart from could not ask.
function test_sms_iletimerkezi_senders_refused_or_not_asked()
{
	$refused = pg_sms_iletimerkezi_senders_parse(401, '{"response":{"status":{"code":401,"message":"Üyelik bilgileri hatalı"}}}');
	pg_assert_false($refused['ok']);
	pg_assert_true($refused['refused']);
	pg_assert_same('401', $refused['code']);

	foreach (array(array(503, '{"response":{"status":{"code":503}}}', ''), array(0, '', 'timeout'), array(200, 'garbage', '')) as $answer) {
		$not_asked = pg_sms_iletimerkezi_senders_parse($answer[0], $answer[1], $answer[2]);
		pg_assert_false($not_asked['ok'], $answer[1]);
		pg_assert_false($not_asked['refused'], $answer[1]);
	}
}
