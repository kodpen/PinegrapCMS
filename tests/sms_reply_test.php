<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for answering an SMS conversation from the Workspace: the length
 * counter (GSM-7 against UCS-2 and the parts a text makes), the 7 days after
 * the customer's last SMS, the providers that may answer, and the wiring of
 * the queue, the actions and the drawer. No database and no provider.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_reply.php');

function test_sms_reply_parts()
{
	pg_assert_same(array('encoding' => 'gsm', 'units' => 0, 'parts' => 0, 'per_part' => 160, 'left' => 160), pg_sms_parts(''), 'empty');
	pg_assert_same(array('encoding' => 'gsm', 'units' => 160, 'parts' => 1, 'per_part' => 160, 'left' => 0), pg_sms_parts(str_repeat('a', 160)), 'one full part');
	pg_assert_same(array('encoding' => 'gsm', 'units' => 161, 'parts' => 2, 'per_part' => 153, 'left' => 145), pg_sms_parts(str_repeat('a', 161)), 'two parts of 153');
	pg_assert_same(array('encoding' => 'gsm', 'units' => 306, 'parts' => 2, 'per_part' => 153, 'left' => 0), pg_sms_parts(str_repeat('a', 306)), 'two full parts');
	pg_assert_same(3, pg_sms_parts(str_repeat('a', 307))['parts'], 'a third part');

	// The extension table's characters take two places.
	pg_assert_same(array('encoding' => 'gsm', 'units' => 7, 'parts' => 1, 'per_part' => 160, 'left' => 153), pg_sms_parts('€{}a'), 'extension characters count twice');
	pg_assert_same('ucs2', pg_sms_parts('Çağrı yok')['encoding'], 'ğ and ı are not GSM-7');
	pg_assert_same('gsm', pg_sms_parts("Ödeme ÜÖÄ äöü ñ é à Ç @£\$¥")['encoding'], 'the default table\'s letters');

	pg_assert_same(array('encoding' => 'ucs2', 'units' => 70, 'parts' => 1, 'per_part' => 70, 'left' => 0), pg_sms_parts(str_repeat('ş', 70)), 'one UCS-2 part');
	pg_assert_same(array('encoding' => 'ucs2', 'units' => 71, 'parts' => 2, 'per_part' => 67, 'left' => 63), pg_sms_parts(str_repeat('ş', 71)), 'two parts of 67');
	pg_assert_same(2, pg_sms_parts("\u{1F600}")['units'], 'an emoji takes two UTF-16 units');
	pg_assert_same('ucs2', pg_sms_parts("a\u{1F600}")['encoding'], 'an emoji needs UCS-2');

	// Counted as it is sent: pg_sms_text() trims and folds line breaks.
	pg_assert_same(3, pg_sms_parts("  a\r\nb ")['units'], 'counted after pg_sms_text()');
}

function test_sms_reply_window()
{
	pg_assert_same(604800, pg_sms_reply_window_left(1000, 1000), 'just written');
	pg_assert_same(3600, pg_sms_reply_window_left(1000, 1000 + 604800 - 3600), 'an hour left');
	pg_assert_same(0, pg_sms_reply_window_left(1000, 1000 + 604800), 'closed at 7 days');
	pg_assert_same(0, pg_sms_reply_window_left(0, 1000), 'the customer never wrote');
	pg_assert_contains('7', pg_sms_reply_window_text(0), 'closed');
	pg_assert_same(lang(array('string' => 'An SMS answer can be sent for {var:1} days {var:2} h more.', 'vars' => array(6, 5))), pg_sms_reply_window_text(6 * 86400 + 5 * 3600 + 20), 'days and hours');
	pg_assert_same(lang(array('string' => 'An SMS answer can be sent for {var:1} h {var:2} min more.', 'vars' => array(5, 30))), pg_sms_reply_window_text(5 * 3600 + 30 * 60), 'hours and minutes');
}

function test_sms_reply_kind_and_providers()
{
	$kinds = pg_sms_kinds();

	pg_assert_true(array_key_exists('reply', $kinds), 'reply is a kind');
	pg_assert_same(false, $kinds['reply'], 'transactional: no promotion, no consent asked');

	pg_assert_true(pg_sms_reply_provider_answers('twilio'), 'Twilio receives');
	pg_assert_true(pg_sms_reply_provider_answers('netgsm'), 'Netgsm receives');
	pg_assert_true(pg_sms_reply_provider_answers('iletimerkezi'), 'İleti Merkezi receives');
	pg_assert_true(!pg_sms_reply_provider_answers(''), 'no provider');
	pg_assert_true(!pg_sms_reply_provider_answers('vonage'), 'a provider without incoming SMS offers nothing');
}

function test_sms_reply_card()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

	$conversation = array('id' => 3, 'message_id' => 40, 'last_in_at' => time() - 600);
	$item = array('id' => 8, 'message_id' => 40, 'conversation' => $conversation, 'transport' => 'sms');
	$row = array('id' => 40);

	pg_assert_same(3, ws_mail_phone_reply_offer($row, $item, true, 'sms'), 'on an SMS card, for a writer');
	pg_assert_same(0, ws_mail_phone_reply_offer($row, $item, true), 'no WhatsApp answer on an SMS card');
	pg_assert_same(0, ws_mail_phone_reply_offer($row, array('transport' => 'whatsapp') + $item, true, 'sms'), 'no SMS answer on a WhatsApp card');
	pg_assert_same(0, ws_mail_phone_reply_offer($row, $item, false, 'sms'), 'not for a reader');
	pg_assert_true(ws_mail_phone_window($conversation, time(), 'sms') > 600000, '7 days less ten minutes');
	pg_assert_true(ws_mail_phone_window($conversation, time()) < 86400, 'WhatsApp\'s window as before');

	pg_assert_same(array('text' => "Merhaba,\nyarın."), ws_mail_phone_sms_text("  Merhaba,\r\nyarın. "), 'as it is sent');
	pg_assert_same(array('error' => 'empty'), ws_mail_phone_sms_text(" \n "), 'empty');
	pg_assert_same(array('error' => 'sms_too_long'), ws_mail_phone_sms_text(str_repeat('ş', PG_SMS_ANSWER_MAX + 1)), 'too long');
	pg_assert_same(array('text' => str_repeat('a', PG_SMS_ANSWER_MAX)), ws_mail_phone_sms_text(str_repeat('a', PG_SMS_ANSWER_MAX)), 'at the limit');
	pg_assert_same(pg_sms_max_length(), PG_SMS_ANSWER_MAX, 'the layer\'s own limit');

	foreach (array('not_sms', 'sms_off', 'sms_receive', 'sms_window', 'sms_too_long', 'sms_number') as $error) {
		pg_assert_true(ws_mail_phone_reply_error_text($error) !== lang('The answer could not be sent.'), 'a sentence for ' . $error);
	}

	$out = array('direction' => 'out', 'send_state' => 'sent', 'transport' => 'sms');
	pg_assert_contains(lang('Sent by SMS'), ws_mail_send_badge_html($out), 'sent by SMS');
	pg_assert_contains(lang('Not sent'), ws_mail_send_badge_html(array('send_state' => 'failed') + $out), 'not sent');

	pg_assert_same(lang('Answer to a customer'), pg_sms_queue_list_kinds()['reply'], 'the Sends label');
}

function test_sms_reply_wiring()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	$sms = $read('includes/fn/sms.php');
	pg_assert_contains("pg_sms_reply_outcome(\$id, (string) \$row['reference'], \$outcome['status'], (string) \$outcome['last_error']);", $sms, 'an answer learns how it went');
	pg_assert_contains("kind, recipient, body, reference FROM sms_outbox", $sms, 'the reference is read with the row');

	$phone = $read('includes/workspace/mail_phone.php');
	pg_assert_contains("pg_sms_enqueue_reply((string) \$conversation['customer_phone'], \$text, array(", $phone, 'an answer leaves through sms_outbox only');
	pg_assert_contains("\$queued = pg_sms_enqueue('+' . (string) \$number, \$text, 'reply', array(", $read('includes/fn/sms_reply.php'), 'as the kind reply');

	pg_assert_contains("case 'ws_sms_reply':", $read('includes/workspace/actions.php'), 'the action');
	pg_assert_contains("'ws_sms_reply_form', 'ws_sms_reply',", $read('includes/panel/actions.php'), 'registered, with the token');
	pg_assert_contains("'sms_reply'       => (", $read('includes/workspace/messages.php'), 'the payload offers it');
	pg_assert_contains("&& ws_mail_phone_sms_offered())", $read('includes/workspace/messages.php'), 'only with a provider that receives');

	$script = $read('assets/js/workspace_mail.js');
	pg_assert_contains("whatsappForm(message, view, h, true);", $script, 'the same drawer');
	pg_assert_contains('function smsParts(text) {', $script, 'the counter\'s twin');
	pg_assert_contains("h.api(sms ? 'ws_sms_reply' : 'ws_whatsapp_reply'", $script, 'sent by its own action');
}
