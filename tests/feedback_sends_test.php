<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the satisfaction surveys on their way out
 * (includes/fn/feedback_sends.php): which events queue a survey, the
 * filters, the sending hours, the queued row, the record's verdict before a
 * send, the consent rule of service and commercial surveys, the reminder,
 * the periodic round, the conversation closed event and the wiring of the
 * announcements, the cancels and the general job.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function feedback_sends_test_survey($overrides = array())
{
	return array_merge(array(
		'id' => 7, 'kind' => PG_FEEDBACK_CSAT, 'touchpoint' => 'booking', 'filter' => array('service' => 0), 'delay_minutes' => 120,
		'channel' => 'email', 'purpose' => 0, 'enabled' => 1, 'archived_at' => 0,
	), $overrides);
}

function test_feedback_sends_event_record()
{
	pg_assert_same(array('touchpoint' => 'booking', 'type' => 'booking', 'id' => 4), pg_feedback_event_record('booking.completed', array('id' => 4)));
	pg_assert_same(array('touchpoint' => 'ticket', 'type' => 'ticket', 'id' => 9), pg_feedback_event_record('ticket.checked_in', array('id' => 9, 'calendar_event_id' => 3)));
	pg_assert_same(array('touchpoint' => 'course', 'type' => 'course', 'id' => 2), pg_feedback_event_record('course.completed', array('id' => 2, 'user_id' => 5)));
	pg_assert_same(array('touchpoint' => 'form', 'type' => 'form', 'id' => 31), pg_feedback_event_record('form.submitted', array('id' => 31, 'form_id' => 8)));
	pg_assert_same(array('touchpoint' => 'delivery', 'type' => 'order', 'id' => 12), pg_feedback_event_record('order.delivered', array('id' => 12, 'ship_to_id' => 3)));
	pg_assert_same(array('touchpoint' => 'mail', 'type' => 'conversation', 'id' => 310), pg_feedback_event_record('workspace.mail.closed', array('conversation_id' => 310)));
	pg_assert_same(array('touchpoint' => 'queue', 'type' => 'queue', 'id' => 58), pg_feedback_event_record('queue.served', array('id' => 58, 'place_id' => 3)));
	pg_assert_same(null, pg_feedback_event_record('queue.no_show', array('id' => 58)), 'a number not here asks nothing');
	pg_assert_same(null, pg_feedback_event_record('booking.confirmed', array('id' => 4)), 'a confirmed booking asks nothing');
	pg_assert_same(null, pg_feedback_event_record('booking.completed', array()), 'no record, no survey');

	foreach (array_keys(feedback_sends_test_touchpoints()) as $touchpoint) {
		pg_assert_true(in_array($touchpoint, array('booking', 'ticket', 'course', 'form', 'delivery', 'mail', 'queue', 'periodic'), true), $touchpoint . ' is followed');
	}
}

// The starting points of the Surveys tab, each followed by an event or by
// the periodic round.
function feedback_sends_test_touchpoints()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_surveys.php');

	return pg_feedback_touchpoints();
}

function test_feedback_sends_filter_takes()
{
	pg_assert_true(pg_feedback_filter_takes('booking', array('service' => 0), 5), '0 takes every service');
	pg_assert_true(pg_feedback_filter_takes('booking', array('service' => 5), 5));
	pg_assert_false(pg_feedback_filter_takes('booking', array('service' => 5), 6));
	pg_assert_false(pg_feedback_filter_takes('mail', array('box' => 2), 1), 'another mailbox');
	pg_assert_true(pg_feedback_filter_takes('delivery', array(), 0), 'a delivery has no filter');
	pg_assert_true(pg_feedback_filter_takes('queue', array('place' => 0), 4), '0 takes every queue place');
	pg_assert_false(pg_feedback_filter_takes('queue', array('place' => 3), 4), 'another queue place');
	pg_assert_false(pg_feedback_filter_takes('form', array('form' => 8, 'field' => 0, 'value' => ''), 9));
}

function test_feedback_sends_hours()
{
	$zone = 'Europe/Istanbul';
	$at = function ($utc) {
		return (int) (new DateTime($utc, new DateTimeZone('UTC')))->getTimestamp();
	};

	pg_assert_same($at('2026-11-20 06:00:00'), pg_feedback_send_time($at('2026-11-20 05:00:00'), $zone), '08:00 waits for 09:00');
	pg_assert_same($at('2026-11-20 06:00:00'), pg_feedback_send_time($at('2026-11-20 06:00:00'), $zone), '09:00 goes');
	pg_assert_same($at('2026-11-20 16:59:00'), pg_feedback_send_time($at('2026-11-20 16:59:00'), $zone), '19:59 goes');
	pg_assert_same($at('2026-11-21 06:00:00'), pg_feedback_send_time($at('2026-11-20 17:00:00'), $zone), '20:00 waits for the next morning');
	pg_assert_same($at('2026-11-21 06:00:00'), pg_feedback_send_time($at('2026-11-20 22:30:00'), $zone), '01:30 waits for that morning');
}

function test_feedback_sends_queue_row()
{
	$now = (int) (new DateTime('2026-11-20 08:00:00', new DateTimeZone('UTC')))->getTimestamp();
	$facts = array('email' => ' Ayse@Example.com', 'phone' => '905321112233', 'contact_id' => 44, 'subject_id' => 3, 'staff_user_id' => 12);
	$row = pg_feedback_queue_row(feedback_sends_test_survey(), array('type' => 'booking', 'id' => 418), $facts, $now, 'Europe/Istanbul');

	pg_assert_same('Ayse@Example.com', $row['recipient']);
	pg_assert_same(pg_feedback_person_hash('ayse@example.com'), $row['person_hash'], 'the hash of the folded address');
	pg_assert_same(array(7, 44, 'booking', 418, 3, 12, 'email', PG_FEEDBACK_QUEUED, $now), array($row['survey_id'], $row['contact_id'], $row['record_type'], $row['record_id'], $row['subject_id'], $row['staff_user_id'], $row['channel'], $row['status'], $row['created_at']));
	pg_assert_same($now + 7200, $row['due_at'], 'two hours later, 13:00 site time');

	$late = pg_feedback_queue_row(feedback_sends_test_survey(), array('type' => 'booking', 'id' => 418), $facts, $now + 8 * 3600, 'Europe/Istanbul');
	pg_assert_same($now + 22 * 3600, $late['due_at'], 'a delay ending at 21:00 goes at 09:00 the next day');

	$sms = pg_feedback_queue_row(feedback_sends_test_survey(array('channel' => 'sms')), array('type' => 'booking', 'id' => 418), $facts, $now, 'Europe/Istanbul');
	pg_assert_same('905321112233', $sms['recipient']);
	pg_assert_same(pg_feedback_person_hash('905321112233'), $sms['person_hash']);

	pg_assert_same(null, pg_feedback_queue_row(feedback_sends_test_survey(), array('type' => 'booking', 'id' => 1), array('email' => 'not an address'), $now, 'UTC'), 'no address, no row');
	pg_assert_same(null, pg_feedback_queue_row(feedback_sends_test_survey(array('channel' => 'whatsapp')), array('type' => 'booking', 'id' => 1), array('email' => 'a@example.com', 'phone' => ''), $now, 'UTC'), 'no number, no row');
}

function test_feedback_sends_record_verdict()
{
	$now = 2000000000;

	pg_assert_same('cancel', pg_feedback_record_verdict('booking', array(), array(), $now, $now), 'a record gone');
	pg_assert_same('send', pg_feedback_record_verdict('booking', array('state' => 'done'), array(), $now, $now));
	pg_assert_same('cancel', pg_feedback_record_verdict('booking', array('state' => 'cancelled'), array(), $now, $now), 'a cancelled booking');
	pg_assert_same('cancel', pg_feedback_record_verdict('ticket', array('state' => 'void'), array(), $now, $now), 'a void ticket');
	pg_assert_same('send', pg_feedback_record_verdict('ticket', array('state' => 'used'), array(), $now, $now));
	pg_assert_same('cancel', pg_feedback_record_verdict('order', array('state' => 'cancelled'), array(), $now, $now), 'a cancelled order');
	pg_assert_same('cancel', pg_feedback_record_verdict('order', array('state' => 'shipped', 'review_requested' => true), array(), $now, $now), 'its review request went in the last 30 days');
	pg_assert_same('send', pg_feedback_record_verdict('order', array('state' => 'shipped', 'review_requested' => false), array(), $now, $now));
	pg_assert_same('send', pg_feedback_record_verdict('queue', array('state' => 'served', 'cleared_at' => 0), array(), $now, $now), 'a served number');
	pg_assert_same('cancel', pg_feedback_record_verdict('queue', array('state' => 'served', 'cleared_at' => $now), array(), $now, $now), 'its personal fields cleared');
	pg_assert_same('cancel', pg_feedback_record_verdict('queue', array('state' => 'left'), array(), $now, $now), 'a number no longer served');
	pg_assert_same('cancel', pg_feedback_record_verdict('conversation', array('state' => 'open'), array(), $now, $now), 'a conversation opened again');
	pg_assert_same('send', pg_feedback_record_verdict('conversation', array('state' => 'closed'), array(), $now, $now));
	pg_assert_same('send', pg_feedback_record_verdict('course', array('email' => 'a@example.com'), array(), $now, $now));

	$filter = array('form' => 8, 'field' => 21, 'value' => 'Solved');
	pg_assert_same('send', pg_feedback_record_verdict('form', array('value' => 'x'), array('form' => 8, 'field' => 0, 'value' => ''), $now, $now), 'a form without a field goes after its days');
	pg_assert_same('send', pg_feedback_record_verdict('form', array('value' => 'Solved'), $filter, $now, $now), 'the field took the value');
	pg_assert_same('wait', pg_feedback_record_verdict('form', array('value' => 'Open'), $filter, $now - 89 * 86400, $now), 'not yet');
	pg_assert_same('cancel', pg_feedback_record_verdict('form', array('value' => 'Open'), $filter, $now - 90 * 86400, $now), 'never within 90 days');
}

function test_feedback_sends_consent_rule()
{
	pg_assert_same('', pg_feedback_consent_refusal(0, 'email', array()), 'a service survey asks no consent');
	pg_assert_same('blocked', pg_feedback_consent_refusal(0, 'email', array('blocked' => true)), 'a bounced address, service survey');
	pg_assert_same('blocked', pg_feedback_consent_refusal(1, 'email', array('blocked' => true, 'opted_in' => true)), 'a bounced address, commercial survey');
	pg_assert_same('consent', pg_feedback_consent_refusal(1, 'email', array('opted_in' => false)), 'commercial needs marketing consent');
	pg_assert_same('iys', pg_feedback_consent_refusal(1, 'email', array('opted_in' => true, 'iys_refused' => true)), 'an İYS refusal');
	pg_assert_same('', pg_feedback_consent_refusal(1, 'email', array('opted_in' => true, 'iys_refused' => false)));
	pg_assert_same('consent', pg_feedback_consent_refusal(1, 'sms', array('number_refusal' => 'refused')), 'a number without permission');
	pg_assert_same('', pg_feedback_consent_refusal(0, 'whatsapp', array('number_refusal' => 'refused')), 'a service survey by WhatsApp');
	pg_assert_same('', pg_feedback_consent_refusal(1, 'whatsapp', array('number_refusal' => '')));
}

function test_feedback_sends_reminder_and_period()
{
	$now = 2000000000;
	$sent = array('status' => PG_FEEDBACK_SENT, 'sent_at' => $now - 3 * 86400, 'reminded_at' => 0, 'answered_at' => 0);

	pg_assert_true(pg_feedback_reminder_due($sent, $now), 'three days after');
	pg_assert_false(pg_feedback_reminder_due(array('sent_at' => $now - 2 * 86400) + $sent, $now), 'two days after');
	pg_assert_false(pg_feedback_reminder_due(array('reminded_at' => $now - 3600) + $sent, $now), 'one reminder only');
	pg_assert_false(pg_feedback_reminder_due(array('answered_at' => $now - 3600) + $sent, $now), 'answered');
	pg_assert_false(pg_feedback_reminder_due(array('status' => PG_FEEDBACK_ANSWERED) + $sent, $now), 'answered status');
	pg_assert_false(pg_feedback_reminder_due(array('sent_at' => $now - 30 * 86400) + $sent, $now), 'its link no longer holds');

	$last = (int) (new DateTime('2026-05-20 10:00:00', new DateTimeZone('UTC')))->getTimestamp();
	pg_assert_true(pg_feedback_periodic_due(0, 6, $last), 'never asked');
	pg_assert_false(pg_feedback_periodic_due($last, 6, (int) (new DateTime('2026-11-20 09:59:00', new DateTimeZone('UTC')))->getTimestamp()), 'not six months yet');
	pg_assert_true(pg_feedback_periodic_due($last, 6, (int) (new DateTime('2026-11-20 10:00:00', new DateTimeZone('UTC')))->getTimestamp()), 'six months on');
}

function test_feedback_sends_mail_closed_event()
{
	$conversation = array('id' => 310, 'channel_id' => 22, 'message_id' => 90300, 'box_id' => 1, 'assignee_id' => 12, 'status' => 'waiting');

	pg_assert_same(
		array('conversation_id' => 310, 'channel_id' => 22, 'message_id' => 90300, 'box_id' => 1, 'assignee_id' => 12, 'closed_by' => 5),
		ws_mail_conversation_closed_event($conversation, array('status' => 'closed', 'closed_at' => 1), 5));
	pg_assert_same(0, ws_mail_conversation_closed_event($conversation, array('status' => 'closed', 'assignee_id' => 0), 5)['assignee_id'], 'let go while closing');
	pg_assert_same(null, ws_mail_conversation_closed_event(array('status' => 'closed') + $conversation, array('status' => 'closed'), 5), 'already closed');
	pg_assert_same(null, ws_mail_conversation_closed_event($conversation, array('assignee_id' => 5), 5), 'only given to somebody');
	pg_assert_same(null, ws_mail_conversation_closed_event($conversation, array('status' => 'open'), 5));
}

function test_feedback_sends_wiring()
{
	pg_assert_contains('pg_feedback_heard($event, (array) $data);', pg_test_function_source('pg_announce'), 'every announcement is heard');
	pg_assert_contains("pg_feedback_heard('form.submitted', \$form_submitted_event);", (string) file_get_contents(PG_FUNCTIONS_DIR . '/custom_form.php'), 'a visitor\'s form, queued without pg_announce()');
	pg_assert_contains("pg_announce('workspace.mail.closed', \$closed);", pg_test_function_source('ws_mail_conversation_set'));
	pg_assert_contains("pg_feedback_cancel_record('conversation'", pg_test_function_source('ws_mail_conversation_set'), 'opened again');
	pg_assert_contains("pg_feedback_cancel_record('order', \$order_id);", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php'), 'a cancelled order');
	pg_assert_contains("pg_feedback_cancel_record('order', \$return['order_id']);", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/returns.php'), 'a refunded order');
	pg_assert_contains('pg_feedback_job_run(time());', (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php'), 'the general job runs the pass');

	foreach (array('booking.completed', 'ticket.checked_in') as $event) {
		pg_assert_true(in_array($event, pg_event_workspace_events(), true), $event . ' reaches flows');
		pg_assert_true(api_webhook_event_is_valid($event), $event . ' is in the catalogue');
		pg_assert_true(isset(ws_flow_event_outputs()[$event]), $event . ' gives its values to a flow');
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
	pg_assert_true(isset(ws_webhook_events()['workspace.mail.closed']), 'workspace.mail.closed is in the catalogue');
	pg_assert_same(array('booking', 41), ws_events_record_of('booking.completed', array('id' => 41)));
	pg_assert_same(array('calendar_event', 3), ws_events_record_of('ticket.checked_in', array('id' => 9, 'calendar_event_id' => 3)));
}
