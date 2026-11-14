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
	pg_assert_false(pg_sms_incoming_supported('netgsm'), 'a poll comes later');
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
	pg_assert_contains("\$sms_channel_post = pg_sms_channel_check(\$_POST, USER_ID, \$liveform);", $read('includes/settings/contact.save.php'));
	pg_assert_contains('pg_sms_channel_save($sms_channel_post, USER_ID);', $read('includes/settings/contact.save.php'));
	pg_assert_contains('pg_sms_channel_field($key,', $read('includes/fn/sms.php'));
	pg_assert_contains("hash('sha256', \$transport . ':' . (string) \$message['wamid'])", $read('includes/workspace/mail_phone.php'), 'an SMS id and a wamid never meet');
	pg_assert_contains("bi-chat-dots", $read('includes/workspace/mail.php'), 'an SMS card has its own badge');
	pg_assert_true(in_array('sms_webhook.php', waf_country_api_scripts(), true), 'called from the provider\'s servers');
}
