<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Twilio SMS driver (includes/sms/twilio.php): the send and
 * phone number list requests, the error codes, when a send may be tried again
 * or is uncertain, and the sender list. Twilio is not called.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/sms/twilio.php');

function sms_twilio_test_app($sender = '+14155550100')
{
	return array(
		'user'            => 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
		'password'        => 'test_auth_token',
		'password_stored' => true,
		'sender'          => $sender,
		'enabled'         => true,
	);
}

// Every country's number goes in E.164 with its plus.
function test_sms_twilio_number()
{
	pg_assert_same('+905321234567', pg_sms_twilio_number('905321234567'));
	pg_assert_same('+4915112345678', pg_sms_twilio_number('4915112345678'));
	pg_assert_same('', pg_sms_twilio_number(''));
	pg_assert_same('', pg_sms_twilio_number('+4915112345678'));
	pg_assert_same('', pg_sms_twilio_number('0532123456'));
}

// A sender is a phone number of the account in E.164 or a Messaging Service
// SID.
function test_sms_twilio_sender_kind()
{
	pg_assert_same('number', pg_sms_twilio_sender_kind('+14155550100'));
	pg_assert_same('service', pg_sms_twilio_sender_kind('MG0123456789abcdef0123456789abcdef'));
	pg_assert_same('', pg_sms_twilio_sender_kind('14155550100'));
	pg_assert_same('', pg_sms_twilio_sender_kind('MG0123'));
	pg_assert_same('', pg_sms_twilio_sender_kind('PINEGRAP'));
}

// The send request: Basic auth with the Account SID and Auth Token, a form
// body with To, From and Body.
function test_sms_twilio_send_request()
{
	$request = pg_sms_twilio_send_request(sms_twilio_test_app(), '+4915112345678', 'Order #12 ready: 3/5 € & more');

	pg_assert_same('https://api.twilio.com/2010-04-01/Accounts/ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX/Messages.json', $request['url']);
	pg_assert_true(in_array('Authorization: Basic ' . base64_encode('ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX:test_auth_token'), $request['headers'], true));
	pg_assert_true(in_array('Content-Type: application/x-www-form-urlencoded', $request['headers'], true));
	pg_assert_same('To=%2B4915112345678&From=%2B14155550100&Body=Order%20%2312%20ready%3A%203%2F5%20%E2%82%AC%20%26%20more', $request['body']);

	parse_str($request['body'], $fields);
	pg_assert_same('Order #12 ready: 3/5 € & more', $fields['Body']);
}

// A Messaging Service SID goes as MessagingServiceSid instead of From.
function test_sms_twilio_send_request_messaging_service()
{
	$request = pg_sms_twilio_send_request(sms_twilio_test_app('MG0123456789abcdef0123456789abcdef'), '+4915112345678', 'Hi');

	parse_str($request['body'], $fields);
	pg_assert_same('MG0123456789abcdef0123456789abcdef', $fields['MessagingServiceSid']);
	pg_assert_false(isset($fields['From']));
}

// The phone number list is a GET with the same sign-in and no body.
function test_sms_twilio_senders_request()
{
	$request = pg_sms_twilio_senders_request(sms_twilio_test_app());

	pg_assert_same('https://api.twilio.com/2010-04-01/Accounts/ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX/IncomingPhoneNumbers.json?PageSize=1000', $request['url']);
	pg_assert_false(isset($request['body']));
	pg_assert_true(in_array('Authorization: Basic ' . base64_encode('ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX:test_auth_token'), $request['headers'], true));
}

// 201 with a Message SID, status queued or sent, is a success.
function test_sms_twilio_send_success()
{
	foreach (array('queued', 'accepted', 'sent') as $status) {
		$result = pg_sms_twilio_send_parse(201, '{"sid":"SM0123456789abcdef0123456789abcdef","status":"' . $status . '","error_code":null}');
		pg_assert_true($result['ok'], $status);
		pg_assert_same('SM0123456789abcdef0123456789abcdef', $result['id'], $status);
		pg_assert_same(34, strlen($result['id']), 'sms_outbox.provider_id is VARCHAR(64)');
	}
}

// 2xx without a Message SID: it may have been taken, so it is not sent again.
function test_sms_twilio_send_success_without_sid()
{
	$result = pg_sms_twilio_send_parse(201, '{"status":"queued"}');
	pg_assert_false($result['ok']);
	pg_assert_false($result['retry']);
	pg_assert_true($result['uncertain']);
}

// A message Twilio took and marked failed at once is a refusal.
function test_sms_twilio_send_failed_at_once()
{
	$result = pg_sms_twilio_send_parse(201, '{"sid":"SM0123456789abcdef0123456789abcdef","status":"failed","error_code":30003}');
	pg_assert_false($result['ok']);
	pg_assert_same('30003', $result['code']);
	pg_assert_false($result['retry']);
	pg_assert_false($result['uncertain']);
}

// 20003: the Account SID or Auth Token refused, coded or bare.
function test_sms_twilio_send_auth_refused()
{
	foreach (array(
		array(401, '{"code":20003,"message":"Authenticate","more_info":"https://www.twilio.com/docs/errors/20003","status":401}'),
		array(401, ''),
		array(403, '<html>Forbidden</html>'),
	) as $answer) {
		$result = pg_sms_twilio_send_parse($answer[0], $answer[1]);
		pg_assert_false($result['ok'], $answer[1]);
		pg_assert_same('20003', $result['code'], $answer[1]);
		pg_assert_false($result['retry'], $answer[1]);
		pg_assert_false($result['uncertain'], $answer[1]);
		pg_assert_contains('Auth Token', $result['error'], $answer[1]);
	}
}

// 21608: a trial account sends only to verified numbers.
function test_sms_twilio_send_unverified_number()
{
	$result = pg_sms_twilio_send_parse(400, '{"code":21608,"message":"The number +4915112345678 is unverified. Trial accounts cannot send messages to unverified numbers.","status":400}');
	pg_assert_false($result['ok']);
	pg_assert_same('21608', $result['code']);
	pg_assert_false($result['retry']);
	pg_assert_false($result['uncertain']);
	pg_assert_contains('verified', $result['error']);
}

// 20429 and a bare 429 or 503 are tried again later; a 500, 502 or 504 may
// come after Twilio took the message.
function test_sms_twilio_send_try_later()
{
	$coded = pg_sms_twilio_send_parse(429, '{"code":20429,"message":"Too Many Requests","status":429}');
	pg_assert_same('20429', $coded['code']);
	pg_assert_true($coded['retry']);
	pg_assert_false($coded['uncertain']);

	foreach (array(429, 503) as $status) {
		$bare = pg_sms_twilio_send_parse($status, 'Too busy');
		pg_assert_true($bare['retry'], (string) $status);
		pg_assert_false($bare['uncertain'], (string) $status);
	}

	foreach (array(500, 502, 504) as $status) {
		$gateway = pg_sms_twilio_send_parse($status, '<html>Bad gateway</html>');
		pg_assert_false($gateway['retry'], (string) $status);
		pg_assert_true($gateway['uncertain'], (string) $status);
	}
}

// Nothing back: retried only when the request never left.
function test_sms_twilio_send_transport()
{
	$never = pg_sms_twilio_send_parse(0, '', 'Could not resolve host', true);
	pg_assert_true($never['retry']);
	pg_assert_false($never['uncertain']);

	$after = pg_sms_twilio_send_parse(0, '', 'Operation timed out', false);
	pg_assert_false($after['retry']);
	pg_assert_true($after['uncertain']);
}

// An unreadable answer: a 2xx may have been taken, a plain 4xx took nothing.
function test_sms_twilio_send_malformed()
{
	$ok_status = pg_sms_twilio_send_parse(200, 'not json');
	pg_assert_false($ok_status['ok']);
	pg_assert_same('', $ok_status['code']);
	pg_assert_false($ok_status['retry']);
	pg_assert_true($ok_status['uncertain']);

	$client = pg_sms_twilio_send_parse(400, '<html>Bad request</html>');
	pg_assert_false($client['retry']);
	pg_assert_false($client['uncertain']);
}

// An unknown code keeps Twilio's message, stripped and cut.
function test_sms_twilio_unknown_code_keeps_message()
{
	$result = pg_sms_twilio_send_parse(400, '{"code":21999,"message":"<b>Something</b>   odd","status":400}');
	pg_assert_same('21999', $result['code']);
	pg_assert_contains('21999', $result['error']);
	pg_assert_contains('Something odd', $result['error']);
}

// The account's phone numbers that can send SMS.
function test_sms_twilio_senders_parse()
{
	$result = pg_sms_twilio_senders_parse(200, '{"incoming_phone_numbers":['
		. '{"phone_number":"+14155550100","capabilities":{"sms":true,"voice":true}},'
		. '{"phone_number":"+14155550101","capabilities":{"sms":false,"voice":true}},'
		. '{"phone_number":"+447700900123"},'
		. '{"phone_number":"+14155550100","capabilities":{"sms":true}}'
		. '],"next_page_uri":null}');

	pg_assert_true($result['ok']);
	pg_assert_same(array('+14155550100', '+447700900123'), $result['senders']);

	$empty = pg_sms_twilio_senders_parse(200, '{"incoming_phone_numbers":[]}');
	pg_assert_true($empty['ok']);
	pg_assert_same(array(), $empty['senders']);
}

// Refused (Twilio answered no) apart from could not ask.
function test_sms_twilio_senders_parse_refused_and_could_not_ask()
{
	$refused = pg_sms_twilio_senders_parse(401, '{"code":20003,"message":"Authenticate","status":401}');
	pg_assert_false($refused['ok']);
	pg_assert_true($refused['refused']);
	pg_assert_same('20003', $refused['code']);

	$missing = pg_sms_twilio_senders_parse(404, '{"code":20404,"message":"The requested resource was not found","status":404}');
	pg_assert_true($missing['refused']);

	foreach (array(
		array(0, '', 'Could not resolve host'),
		array(429, '{"code":20429,"message":"Too Many Requests","status":429}', ''),
		array(503, '', ''),
		array(200, 'not json', ''),
	) as $answer) {
		$result = pg_sms_twilio_senders_parse($answer[0], $answer[1], $answer[2]);
		pg_assert_false($result['ok'], $answer[1]);
		pg_assert_false($result['refused'], $answer[1]);
	}
}

// A Messaging Service SID is taken as the sender without a list.
function test_sms_twilio_senders_take_a_messaging_service()
{
	$result = pg_sms_twilio_senders_with_service(sms_twilio_test_app('MG0123456789abcdef0123456789abcdef'), array('ok' => true, 'senders' => array('+14155550100'), 'code' => '', 'error' => '', 'refused' => false));
	pg_assert_same(array('+14155550100', 'MG0123456789abcdef0123456789abcdef'), $result['senders']);

	$number = pg_sms_twilio_senders_with_service(sms_twilio_test_app(), array('ok' => true, 'senders' => array(), 'code' => '', 'error' => '', 'refused' => false));
	pg_assert_same(array(), $number['senders']);
}
