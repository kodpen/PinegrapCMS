<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the SMS layer (includes/fn/sms.php) and the Netgsm driver
 * (includes/sms/netgsm.php): reading a mobile number, the send and sender
 * list requests, Netgsm's answers, the sender name check, what pg_sms_send()
 * and pg_sms_enqueue() refuse before any account is read, and what the queue
 * does with a message after each kind of answer. Netgsm is not called and no
 * database is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/sms/netgsm.php');

function sms_test_app()
{
	return array(
		'user'            => '8501234567',
		'password'        => 'p:ss',
		'password_stored' => true,
		'sender'          => 'PINEGRAP',
		'enabled'         => true,
	);
}

// A settings stand-in that records what would be marked.
class sms_test_liveform
{
	public $errors = array();
	public $notices = array();

	public function mark_error($field, $message = '')
	{
		$this->errors[$field] = $message;
	}

	public function add_notice($notice)
	{
		$this->notices[] = $notice;
	}

	public function check_form_errors()
	{
		return (bool) $this->errors;
	}
}

// Turkish mobile numbers in the ways people write them, all to one form.
function test_sms_number_reads_turkish_forms()
{
	foreach (array('0532 123 45 67', '5321234567', '+90 (532) 123-45-67', '905321234567', '0090 532 123 45 67', '0532.123.45.67', "0532\xC2\xA0123\xC2\xA04567") as $typed) {
		pg_assert_same('905321234567', pg_sms_number($typed), $typed);
	}
}

// Other countries need their code; national forms are read as Turkish only.
function test_sms_number_international_needs_its_code()
{
	pg_assert_same('4915112345678', pg_sms_number('+49 151 12345678'));
	pg_assert_same('447700900123', pg_sms_number('0044 7700 900123'));
	pg_assert_same('', pg_sms_number('2025550123'));
	pg_assert_same('', pg_sms_number('02121234567'));
}

// Under +90 only a mobile number is one; the trunk zero kept after the
// country code is dropped.
function test_sms_number_turkish_international_forms()
{
	pg_assert_same('905321234567', pg_sms_number('+90 0532 123 45 67'));
	pg_assert_same('905321234567', pg_sms_number('0090 0532 123 45 67'));
	pg_assert_same('', pg_sms_number('+90 212 123 45 67'));
	pg_assert_same('', pg_sms_number('+90 532 123 45 6'));
}

function test_sms_number_refuses_what_is_no_number()
{
	foreach (array('', 'abc', '0532 12a 45 67', '+0532123456', '+12', '+1234567890123456', null, array('5321234567')) as $typed) {
		pg_assert_same('', pg_sms_number($typed), var_export($typed, true));
	}
}

function test_sms_netgsm_number_takes_turkish_mobiles_only()
{
	pg_assert_same('5321234567', pg_sms_netgsm_number('905321234567'));
	pg_assert_same('', pg_sms_netgsm_number('902121234567'));
	pg_assert_same('', pg_sms_netgsm_number('4915112345678'));
	pg_assert_same('', pg_sms_netgsm_number('5321234567'));
}

// "TR" for anything outside ASCII, which the default alphabet would garble.
function test_sms_netgsm_encoding()
{
	pg_assert_same('', pg_sms_netgsm_encoding('Order 1172 shipped, 47.14 TL'));

	foreach (array('Siparişiniz kargoda', 'Ödeme alındı', 'İade', 'çay', 'Ödeme Üzüm', '₺47,14', 'kâr', "\xE2\x80\x9Ctamam\xE2\x80\x9D") as $text) {
		pg_assert_same('TR', pg_sms_netgsm_encoding($text), $text);
	}
}

// One message to one number, informational, Basic auth, JSON.
function test_sms_netgsm_send_request_shape()
{
	$request = pg_sms_netgsm_send_request(sms_test_app(), '5321234567', 'Order 1172 shipped', false);

	pg_assert_same('https://api.netgsm.com.tr/sms/rest/v2/send', $request['url']);
	pg_assert_contains('Authorization: Basic ' . base64_encode('8501234567:p:ss'), $request['headers']);
	pg_assert_contains('Content-Type: application/json', $request['headers']);
	pg_assert_same(array(
		'msgheader' => 'PINEGRAP',
		'messages'  => array(array('msg' => 'Order 1172 shipped', 'no' => '5321234567')),
		'iysfilter' => '0',
	), json_decode($request['body'], true));
}

function test_sms_netgsm_send_request_turkish_and_commercial()
{
	$body = json_decode(pg_sms_netgsm_send_request(sms_test_app(), '5321234567', 'Kargonuz yolda, teşekkürler', true)['body'], true);

	pg_assert_same('TR', $body['encoding']);
	pg_assert_same('11', $body['iysfilter']);
	pg_assert_same('Kargonuz yolda, teşekkürler', $body['messages'][0]['msg']);
}

function test_sms_netgsm_senders_request_shape()
{
	$request = pg_sms_netgsm_senders_request(sms_test_app());

	pg_assert_same('https://api.netgsm.com.tr/sms/rest/v2/msgheader', $request['url']);
	pg_assert_contains('Authorization: Basic ' . base64_encode('8501234567:p:ss'), $request['headers']);
	pg_assert_false(in_array('Content-Type: application/json', $request['headers'], true));
	pg_assert_false(isset($request['body']));
}

// The job id survives as a JSON number longer than an integer holds.
function test_sms_netgsm_send_parse_big_numeric_job_id()
{
	pg_assert_same('17377215342605050417149344', pg_sms_netgsm_send_parse(200, '{"code":"00","jobid":17377215342605050417149344}')['id']);
}

function test_sms_netgsm_send_parse_success()
{
	$result = pg_sms_netgsm_send_parse(200, '{"code":"00","jobid":"17377215342605050417149344","description":"queued"}');

	pg_assert_true($result['ok']);
	pg_assert_same('17377215342605050417149344', $result['id']);
	pg_assert_same('00', $result['code']);
	pg_assert_same('', $result['error']);
	pg_assert_false($result['retry']);
}

// Netgsm sends a refusal as a JSON body with a 4xx status: the code is read.
function test_sms_netgsm_send_parse_refusal_in_4xx()
{
	$result = pg_sms_netgsm_send_parse(406, '{"code":"40","description":"invalid header"}');

	pg_assert_false($result['ok']);
	pg_assert_same('40', $result['code']);
	pg_assert_same(lang('The sender name is not defined on this Netgsm account.'), $result['error']);
	pg_assert_false($result['retry']);
}

function test_sms_netgsm_send_parse_retries_outages_and_limits()
{
	foreach (array('80', '85', '100', '101') as $code) {
		$result = pg_sms_netgsm_send_parse(200, '{"code":"' . $code . '"}');

		pg_assert_false($result['ok'], $code);
		pg_assert_true($result['retry'], $code);
	}

	foreach (array('20', '30', '40', '50', '70') as $code) {
		pg_assert_false(pg_sms_netgsm_send_parse(200, '{"code":"' . $code . '"}')['retry'], $code);
	}
}

// A message is sent again only when it cannot have reached Netgsm: a
// timeout or a 500/502/504 may come after Netgsm took it.
function test_sms_netgsm_send_parse_transport_and_unreadable()
{
	$timeout = pg_sms_netgsm_send_parse(0, '', 'Operation timed out', false);
	pg_assert_false($timeout['ok']);
	pg_assert_false($timeout['retry']);
	pg_assert_true(strpos($timeout['error'], 'Operation timed out') !== false);

	$unresolved = pg_sms_netgsm_send_parse(0, '', 'Could not resolve host', true);
	pg_assert_true($unresolved['retry']);

	foreach (array(500, 502, 504) as $status) {
		$page = pg_sms_netgsm_send_parse($status, '<html>Bad Gateway</html>');
		pg_assert_false($page['ok'], (string) $status);
		pg_assert_false($page['retry'], (string) $status);
	}

	foreach (array(429, 503) as $status) {
		pg_assert_true(pg_sms_netgsm_send_parse($status, 'Busy')['retry'], (string) $status);
	}

	$odd = pg_sms_netgsm_send_parse(200, 'OK');
	pg_assert_false($odd['ok']);
	pg_assert_false($odd['retry']);
}

function test_sms_http_never_sent()
{
	// cURL could not start (no cURL at all is reported so), a proxy or host
	// name not resolved, no connection.
	foreach (array(2, 5, 6, 7) as $errno) {
		pg_assert_true(pg_sms_http_never_sent($errno), (string) $errno);
	}

	// The TLS handshake and certificate checks fail before the request is
	// written: a missing CA bundle, a broken TLS proxy.
	foreach (array(35, 58, 59, 60, 66, 77, 83, 90, 91) as $errno) {
		pg_assert_true(pg_sms_http_never_sent($errno), 'TLS ' . $errno);
	}

	// Timeout, send error, receive error: the request may have arrived.
	foreach (array(0, 28, 52, 55, 56) as $errno) {
		pg_assert_false(pg_sms_http_never_sent($errno), (string) $errno);
	}

	// Not a byte of the request went out: a timeout before the connection
	// was made. Once the request was written, a timeout is uncertain.
	pg_assert_true(pg_sms_http_never_sent(28, 0, 0), 'timeout, nothing written');
	pg_assert_false(pg_sms_http_never_sent(28, 0, 250), 'timeout after the request');
	pg_assert_false(pg_sms_http_never_sent(56, 0, 250), 'receive error after the request');
	pg_assert_false(pg_sms_http_never_sent(0, 0, 0), 'no transport error');

	// A proxy that refused the tunnel let nothing through, whatever the
	// error number; one that opened it did not stop what followed.
	pg_assert_true(pg_sms_http_never_sent(56, 403), 'tunnel refused');
	pg_assert_true(pg_sms_http_never_sent(97, 407), 'proxy wants a sign-in');
	pg_assert_false(pg_sms_http_never_sent(56, 200), 'tunnel open');
	pg_assert_false(pg_sms_http_never_sent(28, 200), 'timeout through the tunnel');
	pg_assert_false(pg_sms_http_never_sent(0, 403), 'no transport error');
}

// "00" without a job id is no message taken on.
function test_sms_netgsm_send_parse_success_without_job_id()
{
	$result = pg_sms_netgsm_send_parse(200, '{"code":"00"}');

	pg_assert_false($result['ok']);
	pg_assert_same('', $result['id']);
}

// An unknown code keeps Netgsm's own words, cut and without markup.
function test_sms_netgsm_unknown_code_keeps_description()
{
	$result = pg_sms_netgsm_send_parse(200, '{"code":"99","description":"<b>Something</b>   new"}');

	pg_assert_false($result['ok']);
	pg_assert_true(strpos($result['error'], '99') !== false);
	pg_assert_true(strpos($result['error'], 'Something new') !== false);
	pg_assert_true(strpos($result['error'], '<b>') === false);
}

// The list has come back under both names, and as a single string.
function test_sms_netgsm_senders_parse_shapes()
{
	pg_assert_same(array('PINEGRAP', 'KODPEN'), pg_sms_netgsm_senders_parse(200, '{"code":"00","msgheaders":["PINEGRAP","KODPEN","PINEGRAP"," "]}')['senders']);
	pg_assert_same(array('PINEGRAP'), pg_sms_netgsm_senders_parse(200, '{"code":"00","msgheader":["PINEGRAP"]}')['senders']);
	pg_assert_same(array('8501234567'), pg_sms_netgsm_senders_parse(200, '{"code":"00","msgheader":"8501234567"}')['senders']);

	$empty = pg_sms_netgsm_senders_parse(200, '{"code":"00"}');
	pg_assert_true($empty['ok']);
	pg_assert_same(array(), $empty['senders']);
}

// A refused sign-in without a JSON body reads as code 30.
function test_sms_netgsm_bare_401_is_refused_credentials()
{
	foreach (array(401, 403) as $status) {
		$result = pg_sms_netgsm_senders_parse($status, 'Unauthorized');

		pg_assert_same('30', $result['code'], (string) $status);
		pg_assert_true($result['refused'], (string) $status);
		pg_assert_same(pg_sms_netgsm_error_text('30'), $result['error'], (string) $status);
	}
}

function test_sms_netgsm_senders_parse_refused_credentials()
{
	$result = pg_sms_netgsm_senders_parse(200, '{"code":"30","error":"Kullanici bilgisi bulunamadi"}');

	pg_assert_false($result['ok']);
	pg_assert_same('30', $result['code']);
	pg_assert_true($result['refused']);
}

// When Netgsm could not be asked, the account is not refused but saved
// unchecked: no answer, a gateway page, a limit or a system error.
function test_sms_netgsm_senders_parse_could_not_ask()
{
	foreach (array(
		array(0, '', 'Operation timed out'),
		array(502, '<html>Bad Gateway</html>', ''),
		array(200, '{"code":"100"}', ''),
		array(200, '{"code":"85"}', ''),
	) as $case) {
		$result = pg_sms_netgsm_senders_parse($case[0], $case[1], $case[2]);

		pg_assert_false($result['ok'], $case[1]);
		pg_assert_false($result['refused'], $case[1]);
		pg_assert_true($result['error'] !== '', $case[1]);
	}

	pg_assert_true(pg_sms_netgsm_senders_parse(406, '{"code":"40"}')['refused']);
}

function test_sms_sender_match_keeps_the_approved_spelling()
{
	$approved = array('PineGrap', 'KODPEN');

	pg_assert_same('PineGrap', pg_sms_sender_match('PineGrap', $approved));
	pg_assert_same('KODPEN', pg_sms_sender_match('kodpen', $approved));
	pg_assert_same('', pg_sms_sender_match('OTHER', $approved));
	pg_assert_same('', pg_sms_sender_match('PINE', array()));

	// The account's own number passes, listed or not.
	pg_assert_same('8501234567', pg_sms_sender_match('8501234567', array('KODPEN'), '8501234567'));
	pg_assert_same('', pg_sms_sender_match('', array(), ''));
}

function test_sms_sender_valid()
{
	pg_assert_true(pg_sms_sender_valid('PINEGRAP'));
	pg_assert_true(pg_sms_sender_valid('ŞİRKET AŞ'));
	pg_assert_true(pg_sms_sender_valid('8501234567'));
	pg_assert_false(pg_sms_sender_valid(''));
	pg_assert_false(pg_sms_sender_valid('ABCDEFGHIJKL'));
	pg_assert_false(pg_sms_sender_valid("PINE\nGRAP"));
}

function test_sms_text_rules()
{
	pg_assert_same("Line 1\nLine 2 tab", pg_sms_text("  Line 1\r\nLine 2\ttab\x07  "));
	pg_assert_same("a\nb", pg_sms_text("a\rb"));
	pg_assert_same('', pg_sms_text("\xC3\x28"), 'not UTF-8');
	pg_assert_same('', pg_sms_text(array('x')));
	pg_assert_same('Sipariş ₺47,14', pg_sms_text('Sipariş ₺47,14'));
}

// Refusals that come before any account is read or any call is made: each
// with its own sentence, so a lookup moved ahead of them would show here as
// "SMS is not set up.".
function test_sms_send_refuses_before_reading_the_account()
{
	foreach (array(
		array('905321234567', 'Hello', 'newsletter', 'Unknown kind of SMS message.'),
		array('905321234567', 'Hello', 'marketing', 'Marketing messages are not sent by SMS: they need the recipient\'s consent recorded in İYS.'),
		array('not a number', 'Hello', 'order', 'This is not a mobile number an SMS can be sent to.'),
		array('905321234567', "  \x07 ", 'order', 'The message is empty.'),
	) as $case) {
		$result = pg_sms_send($case[0], $case[1], $case[2]);

		pg_assert_false($result['ok'], $case[2]);
		pg_assert_false($result['retry'], $case[2]);
		pg_assert_same(lang($case[3]), $result['error'], $case[2]);
	}

	$long = pg_sms_send('905321234567', str_repeat('ş', pg_sms_max_length() + 1), 'order');
	pg_assert_same(lang(array('string' => 'The message is longer than {var:1} characters.', 'vars' => array(pg_sms_max_length()))), $long['error']);
	pg_assert_false($long['retry']);
}

function test_sms_kinds_only_marketing_is_commercial()
{
	foreach (pg_sms_kinds() as $kind => $commercial) {
		pg_assert_same($kind === 'marketing', $commercial, $kind);
	}
}

// Every listed provider has its driver file and both functions.
function test_sms_every_provider_has_a_driver()
{
	foreach (pg_sms_providers() as $key => $definition) {
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/includes/sms/' . $definition['file']), $key . ': ' . $definition['file']);
		pg_assert_true(pg_sms_driver($key), $key);
		pg_assert_same(0, strpos($definition['portal'], 'https://'), $key);
		pg_assert_true(strlen(pg_sms_app_key($key)) <= 40, $key . ': connect_apps.provider is VARCHAR(40)');
	}

	pg_assert_false(pg_sms_driver('nonexistent'));
}

// A pane without the SMS card, or without the named provider's fields,
// leaves the account alone.
function test_sms_settings_posted_provider()
{
	pg_assert_same(null, pg_sms_settings_posted_provider(array('organization_name' => 'Kodpen')));
	pg_assert_same(null, pg_sms_settings_posted_provider(array('sms_provider' => 'netgsm')));
	pg_assert_same(null, pg_sms_settings_posted_provider(array('sms_provider' => 'other', 'sms_other_user' => 'x')));
	pg_assert_same(null, pg_sms_settings_posted_provider(array('sms_provider' => array('netgsm'), 'sms_netgsm_user' => 'x')));
	pg_assert_same('netgsm', pg_sms_settings_posted_provider(array('sms_provider' => 'netgsm', 'sms_netgsm_user' => '')));
}

function sms_test_none()
{
	return array('user' => '', 'password' => '', 'password_stored' => false, 'sender' => '', 'enabled' => false);
}

function sms_test_post($fields)
{
	return $fields + array(
		'sms_provider'        => 'netgsm',
		'sms_netgsm_user'     => '8501234567',
		'sms_netgsm_password' => '',
		'sms_netgsm_sender'   => 'PINEGRAP',
	);
}

// Nothing set up and nothing typed: nothing written, nothing asked.
function test_sms_settings_decide_untouched_card()
{
	$decision = pg_sms_settings_decide(sms_test_post(array('sms_netgsm_user' => '', 'sms_netgsm_sender' => '')), 'netgsm', sms_test_none(), '');

	pg_assert_same('none', $decision['action']);
	pg_assert_false($decision['ask']);
	pg_assert_same(array(), $decision['marks']);
}

// Switching on a new account asks the provider about it.
function test_sms_settings_decide_switch_on_asks()
{
	$decision = pg_sms_settings_decide(sms_test_post(array('sms_enabled' => '1', 'sms_netgsm_password' => 'secret')), 'netgsm', sms_test_none(), '');

	pg_assert_same('save', $decision['action']);
	pg_assert_true($decision['ask']);
	pg_assert_same('secret', $decision['password_effective']);
	pg_assert_same(array(), $decision['marks']);
}

// An account left as it was is not written or asked about again, so an
// unrelated save neither calls the provider nor re-seals the password.
function test_sms_settings_decide_unchanged_account_is_left_alone()
{
	$decision = pg_sms_settings_decide(sms_test_post(array('sms_enabled' => '1')), 'netgsm', sms_test_app(), '');

	pg_assert_same('none', $decision['action']);
	pg_assert_false($decision['ask']);
}

// Neither is one this site can no longer read: the card's warning says so,
// the other Communication settings still save.
function test_sms_settings_decide_unreadable_password_does_not_block_other_saves()
{
	$before = array('password' => '') + sms_test_app();

	$decision = pg_sms_settings_decide(sms_test_post(array('sms_enabled' => '1')), 'netgsm', $before, '');

	pg_assert_same('none', $decision['action']);
	pg_assert_same(array(), $decision['marks']);
}

// Changing such an account without a new password names the cause.
function test_sms_settings_decide_unreadable_password_on_change()
{
	$before = array('password' => '') + sms_test_app();

	$decision = pg_sms_settings_decide(sms_test_post(array('sms_enabled' => '1', 'sms_netgsm_sender' => 'KODPEN')), 'netgsm', $before, '');

	pg_assert_same(
		lang('SMS is switched on but cannot send: the stored password cannot be read with this site\'s encryption key. Enter it again.'),
		$decision['marks']['sms_netgsm_password']);
	pg_assert_false($decision['ask']);
}

// A changed sender is checked with the stored password.
function test_sms_settings_decide_changed_sender_uses_stored_password()
{
	$decision = pg_sms_settings_decide(sms_test_post(array('sms_enabled' => '1', 'sms_netgsm_sender' => 'KODPEN')), 'netgsm', sms_test_app(), '');

	pg_assert_same('save', $decision['action']);
	pg_assert_true($decision['ask']);
	pg_assert_same('p:ss', $decision['password_effective']);
	pg_assert_same('', $decision['password'], 'an empty box keeps the stored password');
}

function test_sms_settings_decide_required_fields()
{
	$decision = pg_sms_settings_decide(sms_test_post(array('sms_enabled' => '1', 'sms_netgsm_user' => '', 'sms_netgsm_sender' => 'TOO LONG NAME')), 'netgsm', sms_test_none(), '');

	pg_assert_same(array('sms_netgsm_user', 'sms_netgsm_password', 'sms_netgsm_sender'), array_keys($decision['marks']));
	pg_assert_same(lang('A sender name has at most 11 characters.'), $decision['marks']['sms_netgsm_sender']);
	pg_assert_false($decision['ask']);
}

// Switching off writes the switch alone, also where nothing can be sealed.
function test_sms_settings_decide_switch_off()
{
	pg_assert_same('disable', pg_sms_settings_decide(sms_test_post(array()), 'netgsm', sms_test_app(), '')['action']);
	pg_assert_same('disable', pg_sms_settings_decide(sms_test_post(array()), 'netgsm', sms_test_app(), 'No key.')['action']);

	$changed = pg_sms_settings_decide(sms_test_post(array('sms_netgsm_sender' => 'KODPEN')), 'netgsm', sms_test_app(), 'No key.');
	pg_assert_same('disable', $changed['action']);
	pg_assert_same(array(), $changed['marks']);
	pg_assert_same(1, count($changed['notices']));
}

// Off and changed: kept, not asked.
function test_sms_settings_decide_off_account_is_kept_unchecked()
{
	$decision = pg_sms_settings_decide(sms_test_post(array('sms_netgsm_password' => 'new')), 'netgsm', array('enabled' => false) + sms_test_app(), '');

	pg_assert_same('save', $decision['action']);
	pg_assert_false($decision['enabled']);
	pg_assert_false($decision['ask']);
}

// Off with the user code emptied removes the stored account.
function test_sms_settings_decide_forget()
{
	pg_assert_same('forget', pg_sms_settings_decide(sms_test_post(array('sms_netgsm_user' => '')), 'netgsm', sms_test_app(), '')['action']);
	pg_assert_same('forget', pg_sms_settings_decide(sms_test_post(array('sms_netgsm_user' => '')), 'netgsm', sms_test_app(), 'No key.')['action']);
}

// Switching on where nothing can be sealed is refused on the switch.
function test_sms_settings_decide_cannot_keep_an_account()
{
	$decision = pg_sms_settings_decide(sms_test_post(array('sms_enabled' => '1', 'sms_netgsm_password' => 'secret')), 'netgsm', sms_test_none(), 'No key.');

	pg_assert_same(array('sms_enabled' => 'No key.'), $decision['marks']);
	pg_assert_false($decision['ask']);
}

// The test number: read only when its button was pressed, an empty one is a
// plain save (Enter presses the button), a wrong one is marked.
function test_sms_settings_decide_test_number()
{
	$typed = pg_sms_settings_decide(sms_test_post(array('sms_test' => '1', 'sms_test_to' => '0532 123 45 67')), 'netgsm', sms_test_app(), '');
	pg_assert_same('905321234567', $typed['test_to']);

	$empty = pg_sms_settings_decide(sms_test_post(array('sms_test' => '1', 'sms_test_to' => '')), 'netgsm', sms_test_app(), '');
	pg_assert_same('', $empty['test_to']);
	pg_assert_same(array(), $empty['marks']);

	$not_pressed = pg_sms_settings_decide(sms_test_post(array('sms_test_to' => '0532 123 45 67')), 'netgsm', sms_test_app(), '');
	pg_assert_same('', $not_pressed['test_to']);

	$wrong = pg_sms_settings_decide(sms_test_post(array('sms_test' => '1', 'sms_test_to' => '0212 123 45 67')), 'netgsm', sms_test_app(), '');
	pg_assert_true(isset($wrong['marks']['sms_test_to']));
}

// A pane without the SMS card marks nothing.
function test_sms_settings_check_without_the_card()
{
	$liveform = new sms_test_liveform();

	pg_assert_same(null, pg_sms_settings_check(array('organization_name' => 'Kodpen'), $liveform));
	pg_assert_same(array(), $liveform->errors);
}

// A send result as pg_sms_send() returns it.
function sms_test_send($ok, $retry = false, $uncertain = false, $error = 'Netgsm said no.')
{
	return array(
		'ok'        => $ok,
		'id'        => $ok ? '17377215342605050417149344' : '',
		'code'      => $ok ? '00' : '',
		'error'     => $ok ? '' : $error,
		'retry'     => $retry,
		'uncertain' => $uncertain,
	);
}

// A claimed row as pg_sms_queue_run() reads it.
function sms_test_queued_row($attempts = 0, $expires_at = 0)
{
	return array('attempts' => $attempts, 'send_after' => 1000, 'expires_at' => $expires_at);
}

// The check both pg_sms_send() and pg_sms_enqueue() start with hands back the
// number and the text in the form they are sent in.
function test_sms_check_gives_the_sent_form()
{
	$check = pg_sms_check('0532 123 45 67', "  Siparişiniz\r\nalındı\t ", 'order');

	pg_assert_true($check['ok']);
	pg_assert_same('905321234567', $check['number']);
	pg_assert_same("Siparişiniz\nalındı", $check['text']);
	pg_assert_same('', $check['error']);

	$refused = pg_sms_check('905321234567', 'Hello', 'marketing');
	pg_assert_false($refused['ok']);
	pg_assert_same('', $refused['number']);
}

// A failure made by the layer itself never claims the provider may have the
// message.
function test_sms_failure_is_never_uncertain()
{
	$failure = pg_sms_failure('x', true);

	pg_assert_false($failure['ok']);
	pg_assert_true($failure['retry']);
	pg_assert_false($failure['uncertain']);
}

// Uncertain is what may have reached Netgsm without a success coming back:
// a timeout, a success status or a server error without Netgsm's JSON, and
// "00" without a job id. A refusal, a limit and a request that reached
// nobody are not.
function test_sms_netgsm_send_parse_uncertain()
{
	pg_assert_true(pg_sms_netgsm_send_parse(0, '', 'Operation timed out', false)['uncertain'], 'timeout');
	pg_assert_false(pg_sms_netgsm_send_parse(0, '', 'Could not resolve host', true)['uncertain'], 'unresolved');

	foreach (array(500, 502, 504) as $status) {
		pg_assert_true(pg_sms_netgsm_send_parse($status, '<html>Bad Gateway</html>')['uncertain'], (string) $status);
	}

	foreach (array(429, 503) as $status) {
		pg_assert_false(pg_sms_netgsm_send_parse($status, 'Busy')['uncertain'], (string) $status);
	}

	pg_assert_true(pg_sms_netgsm_send_parse(200, 'OK')['uncertain'], 'unreadable 200');
	pg_assert_false(pg_sms_netgsm_send_parse(404, '<html>Not Found</html>')['uncertain'], 'unreadable 404');
	pg_assert_false(pg_sms_netgsm_send_parse(401, '')['uncertain'], 'bare 401');
	pg_assert_true(pg_sms_netgsm_send_parse(200, '{"code":"00"}')['uncertain'], 'no job id');

	foreach (array('20', '30', '40', '50', '70', '80', '85', '100', '101') as $code) {
		pg_assert_false(pg_sms_netgsm_send_parse(200, '{"code":"' . $code . '"}')['uncertain'], $code);
	}

	pg_assert_false(pg_sms_netgsm_send_parse(406, '{"code":"40"}')['uncertain'], '406 with a code');
	pg_assert_false(pg_sms_netgsm_send_parse(200, '{"code":"00","jobid":"1"}')['uncertain'], 'success');
}

// pg_sms_enqueue() refuses what pg_sms_send() would, with the same sentence,
// before it looks at the queue or the account.
function test_sms_enqueue_refuses_before_the_queue()
{
	$result = pg_sms_enqueue('905321234567', 'Hello', 'newsletter');

	pg_assert_false($result['ok']);
	pg_assert_same(0, $result['row']);
	pg_assert_false($result['duplicate']);
	pg_assert_same(lang('Unknown kind of SMS message.'), $result['error']);

	pg_assert_same(lang('This is not a mobile number an SMS can be sent to.'), pg_sms_enqueue('0212 123 45 67', 'Hello', 'order')['error']);

	// Without a database the queue is not there, and that is said before the
	// account is looked at.
	pg_assert_same(lang('The SMS queue is not available yet; run the upgrade.'), pg_sms_enqueue('0532 123 45 67', 'Hello', 'order')['error']);
}

// The row written: the checked number and text, now as the earliest time, a
// trimmed reference or none.
function test_sms_queue_row_columns()
{
	$check = pg_sms_check('0532 123 45 67', 'Kargonuz yola çıktı', 'shipping');

	$row = pg_sms_queue_row($check, 'shipping', array('reference' => ' order:438:shipped '), 5000);

	pg_assert_same(5000, $row['created_at']);
	pg_assert_same(5000, $row['send_after']);
	pg_assert_same(0, $row['expires_at']);
	pg_assert_same('queued', $row['status']);
	pg_assert_same('shipping', $row['kind']);
	pg_assert_same('905321234567', $row['recipient']);
	pg_assert_same('Kargonuz yola çıktı', $row['body']);
	pg_assert_same('order:438:shipped', $row['reference']);

	pg_assert_same(null, pg_sms_queue_row($check, 'shipping', array(), 5000)['reference']);
	pg_assert_same(null, pg_sms_queue_row($check, 'shipping', array('reference' => '  '), 5000)['reference']);
	pg_assert_same(null, pg_sms_queue_row($check, 'shipping', 'not options', 5000)['reference']);

	// A time in the past is now; a later one is kept.
	pg_assert_same(5000, pg_sms_queue_row($check, 'shipping', array('send_after' => 10), 5000)['send_after']);
	pg_assert_same(9000, pg_sms_queue_row($check, 'shipping', array('send_after' => 9000), 5000)['send_after']);
	pg_assert_same(5600, pg_sms_queue_row($check, 'shipping', array('expires_at' => 5600), 5000)['expires_at']);
}

// A reference that is not a plain name, and an expiry that leaves no time to
// send, are refused rather than dropped.
function test_sms_queue_row_refusals()
{
	$check = pg_sms_check('0532 123 45 67', 'Kod: 123456', 'otp');

	foreach (array('order 438', "order:\n438", 'sipariş:438', str_repeat('a', 65), "x'y") as $reference) {
		$row = pg_sms_queue_row($check, 'otp', array('reference' => $reference), 5000);

		pg_assert_true(isset($row['error']), json_encode($reference));
		pg_assert_false(isset($row['recipient']), json_encode($reference));
	}

	pg_assert_same('order:438:received', pg_sms_queue_row($check, 'otp', array('reference' => 'order:438:received'), 5000)['reference']);
	pg_assert_same(str_repeat('a', 64), pg_sms_queue_row($check, 'otp', array('reference' => str_repeat('a', 64)), 5000)['reference']);

	pg_assert_same(lang('The message would expire before it could be sent.'), pg_sms_queue_row($check, 'otp', array('expires_at' => 4000), 5000)['error']);
	pg_assert_same(lang('The message would expire before it could be sent.'), pg_sms_queue_row($check, 'otp', array('expires_at' => 5000), 5000)['error']);
	pg_assert_true(isset(pg_sms_queue_row($check, 'otp', array('send_after' => 7000, 'expires_at' => 6000), 5000)['error']), 'expires before its own start');
}

// Taken by the provider: sent, with its id.
function test_sms_queue_outcome_sent()
{
	$outcome = pg_sms_queue_outcome(sms_test_queued_row(2), sms_test_send(true), 7000);

	pg_assert_same('sent', $outcome['status']);
	pg_assert_same(3, $outcome['attempts']);
	pg_assert_same(7000, $outcome['sent_at']);
	pg_assert_same('', $outcome['last_error']);
	pg_assert_same('17377215342605050417149344', $outcome['provider_id']);
}

// Refused: failed at once, whatever attempts are left.
function test_sms_queue_outcome_refused_is_not_tried_again()
{
	$outcome = pg_sms_queue_outcome(sms_test_queued_row(), sms_test_send(false, false, false, 'The sender name is not defined.'), 7000);

	pg_assert_same('failed', $outcome['status']);
	pg_assert_same(1, $outcome['attempts']);
	pg_assert_same('The sender name is not defined.', $outcome['last_error']);
	pg_assert_same(0, $outcome['sent_at']);
}

// The provider may have it: never queued again by itself, even when the
// answer would otherwise allow it.
function test_sms_queue_outcome_uncertain_is_not_tried_again()
{
	$outcome = pg_sms_queue_outcome(sms_test_queued_row(), sms_test_send(false, false, true, 'Operation timed out'), 7000);

	pg_assert_same('uncertain', $outcome['status']);
	pg_assert_same(1, $outcome['attempts']);
	pg_assert_same('Operation timed out', $outcome['last_error']);

	pg_assert_same('uncertain', pg_sms_queue_outcome(sms_test_queued_row(), sms_test_send(false, true, true), 7000)['status']);
}

// Only a message that never reached the provider is queued again, on the mail
// queue's ladder, until the last attempt.
function test_sms_queue_outcome_retry_ladder()
{
	$waits = array();

	for ($attempts = 0; $attempts < pg_mail_max_attempts() - 1; $attempts++) {
		$outcome = pg_sms_queue_outcome(sms_test_queued_row($attempts), sms_test_send(false, true), 7000);

		pg_assert_same('queued', $outcome['status'], 'attempt ' . ($attempts + 1));
		pg_assert_same($attempts + 1, $outcome['attempts']);
		pg_assert_same('Netgsm said no.', $outcome['last_error']);

		$waits[] = $outcome['send_after'] - 7000;
	}

	pg_assert_same(array(60, 300, 1800, 7200, 21600), $waits);

	$last = pg_sms_queue_outcome(sms_test_queued_row(pg_mail_max_attempts() - 1), sms_test_send(false, true), 7000);

	pg_assert_same('failed', $last['status']);
	pg_assert_same(pg_mail_max_attempts(), $last['attempts']);
	pg_assert_same(1000, $last['send_after'], 'a failed row keeps its time');
}

// A message that would expire before its next attempt is not queued again.
function test_sms_queue_outcome_retry_before_expiry()
{
	$kept = pg_sms_queue_outcome(sms_test_queued_row(0, 7061), sms_test_send(false, true), 7000);

	pg_assert_same('queued', $kept['status']);
	pg_assert_same(7060, $kept['send_after']);

	$dropped = pg_sms_queue_outcome(sms_test_queued_row(0, 7060), sms_test_send(false, true, false, 'Netgsm limits how often it is asked.'), 7000);

	pg_assert_same('failed', $dropped['status']);
	pg_assert_same(lang(array(
		'string' => '{var:1} Not tried again: the message expires before the next attempt.',
		'vars'   => array('Netgsm limits how often it is asked.'))), $dropped['last_error']);
}

// Expired before its send: failed without an attempt.
function test_sms_queue_outcome_expired()
{
	$outcome = pg_sms_queue_outcome(sms_test_queued_row(1, 6000), null, 7000);

	pg_assert_same('failed', $outcome['status']);
	pg_assert_same(1, $outcome['attempts']);
	pg_assert_same(lang('The message expired before it could be sent.'), $outcome['last_error']);
	pg_assert_same('', $outcome['provider_id']);
}

// A driver that names no uncertainty is read as certain.
function test_sms_queue_outcome_without_the_uncertain_key()
{
	$send = sms_test_send(false, true);
	unset($send['uncertain']);

	pg_assert_same('queued', pg_sms_queue_outcome(sms_test_queued_row(), $send, 7000)['status']);
}

// Without a database the queue is not there: the job's pass and the screen's
// tab both stay away.
function test_sms_queue_without_the_table()
{
	pg_assert_false(pg_sms_queue_ready());
	pg_assert_false(pg_sms_queue_shown());
	pg_assert_same(array('sent' => 0, 'failed' => 0), pg_sms_queue_run());
}
