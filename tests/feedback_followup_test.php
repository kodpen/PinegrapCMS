<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what follows a satisfaction survey's answer
 * (includes/fn/feedback_followup.php): the feedback.received and
 * feedback.detractor payloads, when a write starts the follow-up of a low
 * score, the record the task is tagged with, the task and the channel line,
 * the 48-hour escalation, the outcome, and where the events are named.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhook_payloads.php');

function feedback_followup_test_row($overrides = array())
{
	return array_merge(array(
		'id' => 41, 'survey_id' => 7, 'contact_id' => 884, 'record_type' => 'booking', 'record_id' => 12, 'subject_id' => 3,
		'staff_user_id' => 5, 'channel' => 'email', 'status' => PG_FEEDBACK_ANSWERED, 'score' => 2, 'band' => PG_FEEDBACK_BAND_LOW,
		'comment' => 'Waited <@user:1> too long', 'answered_at' => 1800000000, 'owner_user_id' => 0, 'task_id' => 0, 'escalated_at' => 0, 'outcome' => 0,
	), $overrides);
}

function feedback_followup_test_survey($overrides = array())
{
	return array_merge(array('id' => 7, 'name' => 'After a booking', 'kind' => PG_FEEDBACK_CSAT, 'touchpoint' => 'booking',
		'fallback_user_id' => 9, 'escalation_user_id' => 11, 'ws_channel_id' => 22), $overrides);
}

function test_feedback_followup_received_payload()
{
	$event = pg_feedback_event_payload(feedback_followup_test_row(), feedback_followup_test_survey(), true);

	pg_assert_same(41, $event['id']);
	pg_assert_same('csat', $event['kind']);
	pg_assert_same('low', $event['band']);
	pg_assert_same(2, $event['score']);
	pg_assert_same(884, $event['contact_id']);
	pg_assert_same('2027-01-15T08:00:00Z', $event['answered_at'], 'UTC, ISO 8601');
	pg_assert_same(true, $event['first']);
	pg_assert_same('Waited <@user:1> too long', $event['comment'], 'the words as given');

	$none = pg_feedback_event_payload(feedback_followup_test_row(array('contact_id' => 0, 'comment' => null, 'score' => 0, 'band' => PG_FEEDBACK_BAND_LOW)), feedback_followup_test_survey(array('kind' => PG_FEEDBACK_NPS)), false);
	pg_assert_same(null, $none['contact_id'], 'no contact is null');
	pg_assert_same('', $none['comment'], 'no comment is empty');
	pg_assert_same(0, $none['score'], '0 is an NPS answer');
	pg_assert_same(false, $none['first']);

	$detractor = pg_feedback_detractor_payload($event, 5, 300);
	pg_assert_false(array_key_exists('first', $detractor), 'a detractor event is once per answer');
	pg_assert_same(5, $detractor['owner_id']);
	pg_assert_same(300, $detractor['task_id']);
}

function test_feedback_followup_due_once_per_answer()
{
	$sent = array('status' => PG_FEEDBACK_SENT, 'band' => 0, 'owner_user_id' => 0, 'task_id' => 0);

	pg_assert_true(pg_feedback_followup_due($sent, PG_FEEDBACK_BAND_LOW), 'a first low answer');
	pg_assert_false(pg_feedback_followup_due($sent, PG_FEEDBACK_BAND_MIDDLE), 'a middle score');
	pg_assert_false(pg_feedback_followup_due(array('status' => PG_FEEDBACK_ANSWERED, 'band' => PG_FEEDBACK_BAND_LOW, 'owner_user_id' => 0, 'task_id' => 0), PG_FEEDBACK_BAND_LOW), 'the comment added to a low score');
	pg_assert_true(pg_feedback_followup_due(array('status' => PG_FEEDBACK_ANSWERED, 'band' => PG_FEEDBACK_BAND_HIGH, 'owner_user_id' => 0, 'task_id' => 0), PG_FEEDBACK_BAND_LOW), 'changed to a low score');
	pg_assert_false(pg_feedback_followup_due(array('status' => PG_FEEDBACK_ANSWERED, 'band' => PG_FEEDBACK_BAND_HIGH, 'owner_user_id' => 5, 'task_id' => 300), PG_FEEDBACK_BAND_LOW), 'low, high, then low again: one task');
}

function test_feedback_followup_ref()
{
	pg_assert_same(array(array('type' => 'contact', 'id' => 884), array('type' => 'booking', 'id' => 12)), pg_feedback_followup_refs(feedback_followup_test_row()));
	pg_assert_same(array(array('type' => 'calendar_event', 'id' => 3)), pg_feedback_followup_refs(feedback_followup_test_row(array('contact_id' => 0, 'record_type' => 'ticket'))), 'a ticket by its event');
	pg_assert_same(array(array('type' => 'form', 'id' => 12)), pg_feedback_followup_refs(feedback_followup_test_row(array('contact_id' => 0, 'record_type' => 'form'))));
	pg_assert_same(array(), pg_feedback_followup_refs(feedback_followup_test_row(array('contact_id' => 0, 'record_type' => 'conversation'))), 'nothing to tag');
}

function test_feedback_followup_task_input()
{
	$input = pg_feedback_followup_input(feedback_followup_test_row(), feedback_followup_test_survey(), 5, 22, 1800000000);

	pg_assert_same(array(5), $input['assignees']);
	pg_assert_same(22, $input['channel_id']);
	pg_assert_same('2027-01-17', $input['due_date'], 'due in two days');
	pg_assert_same(2, count($input['refs']));
	pg_assert_contains('> Waited ‹@user:1› too long', $input['description'], 'the words quoted as data, never a mention');
	pg_assert_contains('After a booking', $input['title']);

	$check = ws_followup_task_data(5, $input, 1800000000);
	pg_assert_true($check['ok'], 'a follow-up the Workspace takes');
	pg_assert_same(null, pg_feedback_followup_input(feedback_followup_test_row(array('contact_id' => 0, 'record_type' => 'conversation')), feedback_followup_test_survey(), 5, 0, 1800000000), 'no record, no task');
}

function test_feedback_followup_found_by_its_tags()
{
	$where = pg_feedback_followup_where(array(array('type' => 'contact', 'id' => 884), array('type' => 'booking', 'id' => 12), array('type' => 'calendar_event', 'id' => 3), array('type' => 'page', 'id' => 4)));

	pg_assert_same(array("(r.contact_id = '884')", "((r.record_type = 'booking') AND (r.record_id = '12'))", "((r.record_type = 'ticket') AND (r.subject_id = '3'))"), $where);
	pg_assert_same(array(), pg_feedback_followup_where(array(array('type' => 'page', 'id' => 4))), 'a task with no such tag reads nothing');
	pg_assert_same(null, pg_feedback_followup_part(300, array()), 'and has no survey part');
}

function test_feedback_followup_line()
{
	$line = pg_feedback_followup_line(feedback_followup_test_row(), feedback_followup_test_survey(array('name' => 'Visit <b>')), 300);

	pg_assert_contains('<#task:300>', $line);
	pg_assert_contains('<#contact:884>', $line);
	pg_assert_contains('Visit ‹b›', $line, 'a survey name never makes a tag');
	pg_assert_false(strpos($line, 'Waited') !== false, 'the comment stays out of the channel');
	pg_assert_false(strpos(pg_feedback_followup_line(feedback_followup_test_row(array('contact_id' => 0)), feedback_followup_test_survey(), 0), '<#') !== false, 'no task, no contact: no tag');
}

function test_feedback_followup_escalation_due()
{
	$row = feedback_followup_test_row(array('owner_user_id' => 5, 'task_id' => 300));
	$now = 1800000000;
	$opened = $now - 48 * 3600;

	pg_assert_true(pg_feedback_escalation_due($row, 'todo', $opened, 11, $now), '48 hours open');
	pg_assert_false(pg_feedback_escalation_due($row, 'todo', $opened + 60, 11, $now), 'not yet');
	pg_assert_false(pg_feedback_escalation_due($row, 'done', $opened, 11, $now), 'closed');
	pg_assert_false(pg_feedback_escalation_due($row, 'cancelled', $opened, 11, $now), 'cancelled');
	pg_assert_true(pg_feedback_escalation_due($row, 'waiting', $opened, 11, $now), 'waiting is open');
	pg_assert_false(pg_feedback_escalation_due($row, 'todo', $opened, 0, $now), 'no escalation user');
	pg_assert_false(pg_feedback_escalation_due(array('escalated_at' => $now) + $row, 'todo', $opened, 11, $now), 'once');
	pg_assert_false(pg_feedback_escalation_due(array('task_id' => 0) + $row, 'todo', $opened, 11, $now), 'no task');
}

function test_feedback_followup_outcomes()
{
	pg_assert_same(array(1, 2, 3), array_keys(pg_feedback_outcomes()), 'called, solved, not reached');
	pg_assert_same(2, pg_feedback_outcome_valid('2'));
	pg_assert_same(0, pg_feedback_outcome_valid('4'));
	pg_assert_same(0, pg_feedback_outcome_valid(array(1)));
	pg_assert_same(0, pg_feedback_outcome_valid('1.5'));
}

function test_feedback_followup_events_are_named()
{
	foreach (array('feedback.received', 'feedback.detractor') as $event) {
		pg_assert_true(api_webhook_event_is_valid($event), $event . ' in the webhook catalogue');
		pg_assert_true(in_array($event, pg_event_workspace_events(), true), $event . ' kept for flows');
		pg_assert_true(isset(ws_flow_event_outputs()[$event]), $event . ' gives its values to the start box');
		pg_assert_true(isset(api_webhook_event_payloads()[$event]['sample']), $event . ' has its fields and sample');
		pg_assert_same(array('contact', 884), ws_events_record_of($event, array('id' => 41, 'contact_id' => 884)), $event . ' is about the contact');
		pg_assert_same(array('', 0), ws_events_record_of($event, array('id' => 41, 'contact_id' => null)), $event . ' without a contact');
	}
}

function test_feedback_followup_wiring()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/feedback.php');
	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/feedback_sends.php');
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$tasks = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/tasks.php');

	pg_assert_same(2, substr_count($page, 'pg_feedback_answered('), 'both writes of the page are followed');
	pg_assert_contains('pg_feedback_escalation_run(', $job, 'the general job escalates');
	pg_assert_contains('pg_feedback_outcome_set(', $actions, 'closing a task keeps its outcome');
	pg_assert_contains('pg_feedback_followup_part(', $tasks, 'the task drawer shows the answer');
}
