<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the volunteer messages
 * (includes/fn/volunteer_messages.php): the sending hours, the channels a
 * call or reminder tries, the day-before reminder's moment, the document
 * notice bits, the link rebuilt from its issue, the SMS text, the drawer's
 * call hint, and the service kinds of SMS and WhatsApp.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_messages.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_page_view.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php');

function test_volunteer_send_hour()
{
	pg_assert_true(pg_volunteer_send_hour('2026-12-07 09:00:00'), 'from 09:00');
	pg_assert_true(pg_volunteer_send_hour('2026-12-07 19:59:59'));
	pg_assert_false(pg_volunteer_send_hour('2026-12-07 20:00:00'), 'never from 20:00');
	pg_assert_false(pg_volunteer_send_hour('2026-12-07 08:59:00'));
	pg_assert_false(pg_volunteer_send_hour('2026-12-07 02:30'), 'never at night');
	pg_assert_false(pg_volunteer_send_hour('soon'));
}

function test_volunteer_message_channels()
{
	$all = array('email', 'sms', 'whatsapp');
	pg_assert_same(array('whatsapp', 'email', 'sms'), pg_volunteer_message_channels('whatsapp', $all, true, true), 'the own choice first, then the others');
	pg_assert_same(array('email', 'sms', 'whatsapp'), pg_volunteer_message_channels('email', $all, true, true));
	pg_assert_same(array('email'), pg_volunteer_message_channels('sms', $all, false, true), 'no phone: e-mail only');
	pg_assert_same(array('sms', 'whatsapp'), pg_volunteer_message_channels('email', $all, true, false), 'no e-mail address: the phone');
	pg_assert_same(array('sms'), pg_volunteer_message_channels('whatsapp', array('sms'), true, true), 'a choice the programme does not offer is passed over');
	pg_assert_same(array(), pg_volunteer_message_channels('email', array(), true, true), 'no channel: no call');
	pg_assert_same(array(), pg_volunteer_message_channels('email', array('email'), true, false));
	pg_assert_same(array('email'), pg_volunteer_reminder_allowed(array()), 'a reminder is no call: e-mail when the programme set none');
	pg_assert_same(array('sms'), pg_volunteer_reminder_allowed(array('sms')));
}

function test_volunteer_reminder_due()
{
	$now = '2026-12-07 10:00:00';
	pg_assert_true(pg_volunteer_reminder_due(array('start' => '2026-12-08 07:00:00', 'reminded_at' => 0), $now), 'tomorrow early');
	pg_assert_true(pg_volunteer_reminder_due(array('start' => '2026-12-08 23:00:00', 'reminded_at' => 0), $now), 'tomorrow late');
	pg_assert_true(pg_volunteer_reminder_due(array('start' => '2026-12-07 18:00:00', 'reminded_at' => 0), $now), 'a shift added for today');
	pg_assert_false(pg_volunteer_reminder_due(array('start' => '2026-12-09 07:00:00', 'reminded_at' => 0), $now), 'the day after tomorrow: not yet');
	pg_assert_false(pg_volunteer_reminder_due(array('start' => '2026-12-08 07:00:00', 'reminded_at' => 1765000000), $now), 'once');
	pg_assert_false(pg_volunteer_reminder_due(array('start' => '2026-12-07 09:00:00', 'reminded_at' => 0), $now), 'started');
	pg_assert_false(pg_volunteer_reminder_due(array('start' => '', 'reminded_at' => 0), $now));
	pg_assert_true(pg_volunteer_reminder_due(array('start' => '2027-01-01 08:00:00', 'reminded_at' => 0), '2026-12-31 12:00:00'), 'across the year');
}

function test_volunteer_notice_bits()
{
	pg_assert_same(1, pg_volunteer_notice_bit(30));
	pg_assert_same(2, pg_volunteer_notice_bit(7));
	pg_assert_same(0, pg_volunteer_notice_bit(0));
	pg_assert_same(array(), pg_volunteer_notices_sent(0));
	pg_assert_same(array(30), pg_volunteer_notices_sent(1));
	pg_assert_same(array(30, 7), pg_volunteer_notices_sent(3));

	// Fed back into the notice rule: 30 then 7, each once.
	pg_assert_same(30, pg_volunteer_document_notice('2027-01-06', '2026-12-07', pg_volunteer_notices_sent(0)));
	pg_assert_same(0, pg_volunteer_document_notice('2027-01-06', '2026-12-08', pg_volunteer_notices_sent(1)));
	pg_assert_same(7, pg_volunteer_document_notice('2026-12-14', '2026-12-07', pg_volunteer_notices_sent(1)));
	pg_assert_same(0, pg_volunteer_document_notice('2026-12-14', '2026-12-08', pg_volunteer_notices_sent(3)));
}

function test_volunteer_live_token()
{
	$key = str_repeat('k', 32);
	$token = pg_volunteer_link_token(5, 1765000000, $key);
	$row = array('id' => 5, 'link_issued_at' => 1765000000, 'link_hash' => pg_volunteer_link_token_hash($token));
	pg_assert_same($token, pg_volunteer_live_token($row, $key), 'the link still in use');
	pg_assert_same('', pg_volunteer_live_token(array('link_hash' => null) + $row, $key), 'a withdrawn link');
	pg_assert_same('', pg_volunteer_live_token(array('link_issued_at' => 1765000001) + $row, $key), 'a newer issue than the hash');
	pg_assert_same('', pg_volunteer_live_token(array('link_issued_at' => 0) + $row, $key), 'never sent');
}

function test_volunteer_sms_text()
{
	$message = array('subject' => 'Food bank: a shift needs you', 'body' => 'x');
	pg_assert_same('Food bank: a shift needs you: Mon 09:00–12:00 · Depot https://x/v', pg_volunteer_sms_text($message, 'Mon 09:00–12:00 · Depot', 'https://x/v'));
	pg_assert_same('Food bank: a shift needs you: Mon', pg_volunteer_sms_text($message, 'Mon', ''));
}

function test_volunteer_call_hint()
{
	pg_assert_same(lang('Calls are off: choose a channel under Set-up, Calls and reminders.'), pg_volunteer_call_hint(3, false, true));
	pg_assert_same(lang('Nobody to ask: every role is full, the shift is not within the call days, or everyone who fits was asked.'), pg_volunteer_call_hint(0, true, true));
	pg_assert_same(lang(array('string' => 'Calls go out between 09:00 and 20:00: the {var:1} volunteers who fit are asked then without a press.', 'vars' => array(2))), pg_volunteer_call_hint(2, true, false));
	pg_assert_same(lang(array('string' => 'Asks {var:1} volunteers who fit, each by the channel they chose.', 'vars' => array(2))), pg_volunteer_call_hint(2, true, true));
}

function test_volunteer_message_kinds_are_service()
{
	$kinds = pg_sms_kinds();
	pg_assert_same(false, $kinds['volunteer'], 'a service message: no marketing consent asked');
	pg_assert_true(isset(pg_sms_queue_list_kinds()['volunteer']), 'the Sends list names the kind');

	$events = pg_whatsapp_message_events();

	foreach (array('volunteer_call', 'volunteer_reminder') as $event) {
		pg_assert_same('volunteer', $events[$event]['kind'], $event);
		pg_assert_true(!empty($events[$event]['service']), $event . ' refuses a marketing template');

		foreach ($events[$event]['fields'] as $field) {
			pg_assert_true(isset(pg_whatsapp_message_field_labels()[$field]), $event . ' ' . $field . ' is labelled');
		}
	}
}

function test_volunteer_calls_form()
{
	$form = array('action' => '/volunteer.php', 'hidden' => '');
	$c = pg_volunteer_page_secure_classes();
	pg_assert_same('', pg_volunteer_calls_form_html($form, $c, array('id' => 4, 'calls' => '1', 'call_channel' => 'email'), array()), 'no calls, no switch');

	$html = pg_volunteer_calls_form_html($form, $c, array('id' => 4, 'calls' => '0', 'call_channel' => 'sms'), array('email', 'sms'));
	pg_assert_true(strpos($html, 'name="action" value="calls"') !== false);
	pg_assert_true(strpos($html, ' checked') === false, 'off stays off');
	pg_assert_true(strpos($html, '<option value="sms" selected>') !== false, 'the channel chosen');

	$html = pg_volunteer_calls_form_html($form, $c, array('id' => 4, 'calls' => '1', 'call_channel' => 'email'), array('whatsapp'));
	pg_assert_true(strpos($html, '<select') === false && strpos($html, 'name="channel" value="whatsapp"') !== false, 'one channel: no list');
	pg_assert_true(strpos($html, ' checked') !== false);
	pg_assert_same(array(lang('Your shift calls choice was saved.'), true), pg_volunteer_answer_text('calls_saved'));
}
