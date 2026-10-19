<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the SMS layer (includes/fn/sms.php) and the Netgsm driver
 * (includes/sms/netgsm.php): reading a mobile number, the send and sender
 * list requests, Netgsm's answers, the sender name check and what
 * pg_sms_send() refuses before any account is read. Netgsm is not called.
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

// "TR" only for the Turkish letters the default alphabet lacks; Ö, Ü and ü
// are in it.
function test_sms_netgsm_encoding()
{
	pg_assert_same('', pg_sms_netgsm_encoding('Order 1172 shipped'));
	pg_assert_same('', pg_sms_netgsm_encoding('Ödeme Üzüm'));

	foreach (array('Siparişiniz kargoda', 'Ödeme alındı', 'İade', 'çay', 'ağ', 'ŞUBAT', 'ĞÇ') as $text) {
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

function test_sms_netgsm_send_parse_transport_and_unreadable()
{
	$down = pg_sms_netgsm_send_parse(0, '', 'Connection timed out');
	pg_assert_false($down['ok']);
	pg_assert_true($down['retry']);
	pg_assert_true(strpos($down['error'], 'Connection timed out') !== false);

	$html = pg_sms_netgsm_send_parse(502, '<html>Bad Gateway</html>');
	pg_assert_false($html['ok']);
	pg_assert_true($html['retry']);

	$odd = pg_sms_netgsm_send_parse(200, 'OK');
	pg_assert_false($odd['ok']);
	pg_assert_false($odd['retry']);
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
		pg_assert_false($result['retry'], (string) $status);
		pg_assert_same(pg_sms_netgsm_error_text('30'), $result['error'], (string) $status);
	}
}

function test_sms_netgsm_senders_parse_refused_credentials()
{
	$result = pg_sms_netgsm_senders_parse(200, '{"code":"30","error":"Kullanici bilgisi bulunamadi"}');

	pg_assert_false($result['ok']);
	pg_assert_same('30', $result['code']);
	pg_assert_false($result['retry']);
}

function test_sms_sender_match_keeps_the_approved_spelling()
{
	$approved = array('PineGrap', 'KODPEN');

	pg_assert_same('PineGrap', pg_sms_sender_match('PineGrap', $approved));
	pg_assert_same('KODPEN', pg_sms_sender_match('kodpen', $approved));
	pg_assert_same('', pg_sms_sender_match('OTHER', $approved));
	pg_assert_same('', pg_sms_sender_match('PINE', array()));
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

// Refusals that come before any account is read or any call is made: each
// with its own sentence, so a lookup moved ahead of them would show here as
// "SMS is not set up.".
function test_sms_send_refuses_before_reading_the_account()
{
	foreach (array(
		array('905321234567', 'Hello', 'newsletter', 'Unknown kind of SMS message.'),
		array('905321234567', 'Hello', 'marketing', 'Marketing messages are not sent by SMS: they need the recipient\'s consent recorded in İYS.'),
		array('not a number', 'Hello', 'order', 'This is not a mobile number an SMS can be sent to.'),
		array('905321234567', '   ', 'order', 'The message is empty.'),
	) as $case) {
		$result = pg_sms_send($case[0], $case[1], $case[2]);

		pg_assert_false($result['ok'], $case[2]);
		pg_assert_false($result['retry'], $case[2]);
		pg_assert_same(lang($case[3]), $result['error'], $case[2]);
	}
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
