<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a customer's SMS to the site (includes/fn/sms_inbox.php): which
 * providers receive, stop words, Twilio's signature and message, the SMS
 * box's columns, the Channel field and the card's badge, the consent
 * source, and the places it is wired.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_inbox.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_sms_inbox_providers()
{
	$providers = array_keys(pg_sms_providers());
	$ways = array_keys(pg_sms_incoming_ways());
	sort($providers);
	sort($ways);
	pg_assert_same($providers, $ways, 'every provider says how it receives');
	pg_assert_true(pg_sms_incoming_supported('twilio'));
	pg_assert_same('poll', pg_sms_incoming_ways()['netgsm'], 'Netgsm is asked');
	pg_assert_same('poll', pg_sms_incoming_ways()['iletimerkezi'], 'İleti Merkezi is asked');
	pg_assert_false(pg_sms_incoming_supported('nobody'));
}

function test_sms_inbox_stop_words()
{
	foreach (array('STOP', 'stop', ' Stop. ', 'İptal', 'iptal', 'IPTAL', 'ret', 'DUR!', 'Stop all', 'unsubscribe') as $text) {
		pg_assert_true(pg_sms_is_stop_word($text), $text);
	}

	foreach (array('', 'stop sending me this', 'dur bakalım', 'retur', 'Siparişim nerede?') as $text) {
		pg_assert_false(pg_sms_is_stop_word($text), $text);
	}
}

function test_sms_inbox_twilio_signature()
{
	// Twilio's rule: the address, then each field's name and value with the
	// names sorted, signed with HMAC-SHA1 and the auth token, in Base64.
	$url = 'https://mycompany.com/myapp.php?foo=1&bar=2';
	$fields = array('To' => '+18005551212', 'CallSid' => 'CA1234567890ABCDE', 'From' => '+12349013030', 'Digits' => '1234', 'Caller' => '+12349013030');
	$expected = base64_encode(hash_hmac('sha1', $url . 'CallSidCA1234567890ABCDECaller+12349013030Digits1234From+12349013030To+18005551212', '12345', true));

	pg_assert_same($expected, pg_sms_twilio_signature($url, $fields, '12345'));
	pg_assert_true(pg_sms_twilio_signed($url, $fields, $expected, '12345'));
	pg_assert_false(pg_sms_twilio_signed($url, array('Digits' => '1235') + $fields, $expected, '12345'), 'a changed field');
	pg_assert_false(pg_sms_twilio_signed('http://mycompany.com/myapp.php?foo=1&bar=2', $fields, $expected, '12345'), 'another address');
	pg_assert_false(pg_sms_twilio_signed($url, $fields, '', '12345'), 'no signature');
	pg_assert_false(pg_sms_twilio_signed($url, $fields, pg_sms_twilio_signature($url, $fields, ''), ''), 'no token');
}

function test_sms_inbox_twilio_message()
{
	$message = pg_sms_twilio_message(array('MessageSid' => 'SM0123456789abcdef0123456789abcdef', 'From' => '+905321112233', 'Body' => ' Siparişim nerede? ', 'NumMedia' => '0'));

	pg_assert_same(array('wamid' => 'SM0123456789abcdef0123456789abcdef', 'from' => '905321112233', 'text' => 'Siparişim nerede?', 'kind' => 'text', 'name' => '', 'at' => 0), $message);
	pg_assert_same('image', pg_sms_twilio_message(array('MessageSid' => 'SM0123456789', 'From' => '+905321112233', 'NumMedia' => '1'))['kind']);
	pg_assert_same(null, pg_sms_twilio_message(array('MessageSid' => 'x', 'From' => '+905321112233')), 'no message id');
	pg_assert_same(null, pg_sms_twilio_message(array('MessageSid' => 'SM0123456789', 'From' => 'Kodpen')), 'a sender name is no number');
	pg_assert_same(null, pg_sms_twilio_message('nothing'));
}

function test_sms_inbox_box_and_field()
{
	$values = ws_mail_phone_sms_box_values('twilio', '+1 415 555 0100', 7);

	pg_assert_same(array('SMS +1 415 555 0100', '+1 415 555 0100', 7, 1), array($values['name'], $values['address'], $values['channel_id'], $values['enabled']));
	pg_assert_same(array('app' => 'sms', 'provider' => 'twilio'), json_decode($values['settings'], true));
	pg_assert_same(0, ws_mail_phone_sms_box_values('twilio', '', 0)['enabled'], 'no channel: kept, switched off');

	$html = ws_mail_phone_channel_html(array(3 => array('id' => 3, 'name' => 'destek')), 3, 'sms');

	pg_assert_contains('name="sms_channel"', $html);
	pg_assert_contains('SMS', $html, 'its help speaks of SMS');
	pg_assert_contains('name="whatsapp_channel"', ws_mail_phone_channel_html(array(), 0), 'the WhatsApp card keeps its field');

	pg_assert_same('/sms_webhook.php?provider=twilio', substr(pg_sms_webhook_url('twilio'), -strlen('/sms_webhook.php?provider=twilio')));
	pg_assert_true(in_array('message', pg_consent_log_sources(), true), 'a stop word is a consent source');
	pg_assert_contains('An SMS from', ws_mail_customer_line_text('Ayşe', 4, 'Merhaba', '', 'sms'));
}

// Where the pieces are joined: the settings, the webhook, the ingest by
// transport, the card's badge and the firewall.
function test_sms_inbox_wiring()
{
	$read = function ($file) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	};

	pg_assert_contains('pg_sms_webhook_receive();', $read('sms_webhook.php'));
	pg_assert_contains('pg_sms_inbox_job();', $read('job.php'), 'the general job asks the polled inboxes');
	pg_assert_contains("!empty(\$message['stop']) || pg_sms_is_stop_word(", $read('includes/fn/sms_inbox.php'), 'a provider\'s own unsubscribe stops too');
	pg_assert_contains("\$sms_channel_post = pg_sms_channel_check(\$_POST, USER_ID, \$liveform);", $read('includes/settings/contact.save.php'));
	pg_assert_contains('pg_sms_channel_save($sms_channel_post, USER_ID);', $read('includes/settings/contact.save.php'));
	pg_assert_contains('pg_sms_webhook_field($key)', $read('includes/fn/sms.php'), 'the address in its provider\'s block');
	pg_assert_same(1, substr_count($read('includes/fn/sms.php'), 'pg_sms_channel_field('), 'one Channel field below the blocks, so one sms_channel is posted');
	pg_assert_same('', pg_sms_webhook_field('netgsm'), 'a polled provider has no address');
	pg_assert_contains("hash('sha256', \$transport . ':' . (string) \$message['wamid'])", $read('includes/workspace/mail_phone.php'), 'an SMS id and a wamid never meet');
	pg_assert_contains("bi-chat-dots", $read('includes/workspace/mail.php'), 'an SMS card has its own badge');
	pg_assert_true(in_array('sms_webhook.php', waf_country_api_scripts(), true), 'called from the provider\'s servers');

	// A stop word sent again by the provider is not refused twice.
	pg_assert_true((bool) preg_match("~ws_mail_phone_ingest\\(.*?\\(\\\$out\\['status'\\] !== 'duplicate'\\) && \\(!empty\\(\\\$message\\['stop'\\]\\) \\|\\| pg_sms_is_stop_word~s", $read('includes/fn/sms_inbox.php')), 'the refusal follows the ingest and skips a duplicate');
}

// The polled inboxes: when an ask is due and how far it reaches.
function test_sms_inbox_window()
{
	pg_assert_same(array('due' => true, 'from' => 10000 - PG_SMS_INBOX_REACH, 'to' => 10000), pg_sms_inbox_window(0, 10000), 'never asked');
	pg_assert_false(pg_sms_inbox_window(10000 - PG_SMS_INBOX_EVERY + 1, 10000)['due'], 'asked a moment ago');
	pg_assert_true(pg_sms_inbox_window(10000 - PG_SMS_INBOX_EVERY, 10000)['due']);
}

function test_sms_inbox_netgsm()
{
	pg_sms_driver('netgsm');
	$app = array('user' => '8508000000', 'password' => 'secret', 'sender' => 'KODPEN');
	$request = pg_sms_netgsm_inbox_request($app, mktime(10, 0, 0, 11, 12, 2026), mktime(10, 0, 0, 11, 14, 2026));

	pg_assert_same('https://api.netgsm.com.tr/sms/rest/v2/inbox?startdate=12112026&stopdate=14112026', $request['url'], 'dates always sent, ddMMyyyy');
	pg_assert_true(in_array('Authorization: Basic ' . base64_encode('8508000000:secret'), $request['headers'], true));

	$read = pg_sms_netgsm_inbox_parse(200, '{"code":"00","description":"","messages":[{"message":"Siparişim nerede?","sender":"905321112233","receiver":"8508000000","datetime":"14.11.2026 09:30:00"},{"message":"x","sender":"Kodpen","receiver":"8508000000","datetime":""}]}');

	pg_assert_true($read['ok']);
	pg_assert_same(1, count($read['messages']), 'a sender that is no number is left out');
	pg_assert_same(array('905321112233', 'Siparişim nerede?'), array($read['messages'][0]['from'], $read['messages'][0]['text']));
	pg_assert_same($read['messages'][0]['wamid'], pg_sms_netgsm_inbox_parse(200, '{"code":"00","messages":[{"message":"Siparişim nerede?","sender":"905321112233","datetime":"14.11.2026 09:30:00"}]}')['messages'][0]['wamid'], 'the same message is the same id on the next ask');
	pg_assert_same(array('ok' => true, 'messages' => array(), 'error' => ''), pg_sms_netgsm_inbox_parse(200, '{"code":"40","description":"no messages"}'), '40 is an empty inbox');
	pg_assert_false(pg_sms_netgsm_inbox_parse(200, '{"code":"30"}')['ok'], 'wrong credentials');
}

function test_sms_inbox_iletimerkezi()
{
	pg_sms_driver('iletimerkezi');
	$app = array('user' => 'key', 'password' => 'hash', 'sender' => 'KODPEN');
	$request = pg_sms_iletimerkezi_inbox_request($app, mktime(0, 0, 0, 11, 12, 2026), mktime(23, 59, 59, 11, 13, 2026), 2);
	$body = json_decode($request['body'], true);

	pg_assert_same('https://api.iletimerkezi.com/v1/get-inbox/json', $request['url']);
	pg_assert_same(array('page' => 2, 'rowCount' => 1000, 'filter' => array('start' => '2026-11-12 00:00:00', 'end' => '2026-11-13 23:59:59')), $body['request']['inbox']);
	pg_assert_same(array('key' => 'key', 'hash' => 'hash'), $body['request']['authentication']);

	$answer = '{"response":{"status":{"code":200,"message":"İşlem başarılı"},"inbox":{"count":2,"messages":['
		. '{"id":25,"shortCode":"7889","from":"+905354101234","keyword":"EMARKA","command":null,"action":"RECORD_ONLY","message":"EMARKA Merhaba","price":0.8,"receivedAt":"2026-11-13 20:20:20"},'
		. '{"id":26,"shortCode":"7889","from":"+905354101234","keyword":"EMARKA","command":"IPTAL","action":"UNSUBSCRIBE","message":"EMARKA IPTAL","price":0.8,"receivedAt":"2026-11-13 20:21:00"}]}}}';
	$read = pg_sms_iletimerkezi_inbox_parse(200, $answer, '', 1);

	pg_assert_true($read['ok']);
	pg_assert_false($read['more'], 'one page holds both');
	pg_assert_same(array('iletimerkezi-25', 'iletimerkezi-26'), array_column($read['messages'], 'wamid'));
	pg_assert_same(array(false, true), array_column($read['messages'], 'stop'), 'İleti Merkezi\'s own unsubscribe is a stop');
	pg_assert_true(pg_sms_iletimerkezi_inbox_parse(200, str_replace('"count":2', '"count":2500', $answer), '', 1)['more'], 'more pages follow');
	pg_assert_false(pg_sms_iletimerkezi_inbox_parse(200, '{"response":{"status":{"code":458,"message":"Tarih aralığı hatalı."}}}')['ok'], 'a bad range');
}
