<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Remind me later on an e-mail conversation
 * (includes/workspace/mail_remind.php): the times each choice names in the
 * person's zone, the conditions that set a conversation aside and bring it
 * back unread, the pane's offer, the schema step and the wiring. No
 * database.
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

function workspace_mail_remind_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_workspace_mail_remind_time_in_the_persons_zone()
{
	// Wednesday 2026-11-18 22:30 UTC: already Thursday 01:30 in Istanbul.
	$now = gmmktime(22, 30, 0, 11, 18, 2026);

	pg_assert_same(array(true, $now + 3600), array_values(array_intersect_key(ws_mail_remind_time('hour', '', 'Europe/Istanbul', $now), array('ok' => 1, 'at' => 1))), 'in an hour');

	pg_assert_same(gmmktime(6, 0, 0, 11, 20, 2026), ws_mail_remind_time('morning', '', 'Europe/Istanbul', $now)['at'], 'tomorrow is Friday in Istanbul: 09:00 there');
	pg_assert_same(gmmktime(9, 0, 0, 11, 19, 2026), ws_mail_remind_time('morning', '', 'UTC', $now)['at'], 'tomorrow is Thursday in UTC');
	pg_assert_same(gmmktime(6, 0, 0, 11, 23, 2026), ws_mail_remind_time('week', '', 'Europe/Istanbul', $now)['at'], 'next week: Monday 09:00');

	$monday = gmmktime(12, 0, 0, 11, 23, 2026);
	pg_assert_same(gmmktime(9, 0, 0, 11, 30, 2026), ws_mail_remind_time('week', '', 'UTC', $monday)['at'], 'on a Monday, the Monday after');

	$sunday = gmmktime(12, 0, 0, 11, 22, 2026);
	pg_assert_same(gmmktime(9, 0, 0, 11, 23, 2026), ws_mail_remind_time('week', '', '', $sunday)['at'], 'on a Sunday, the next day; no zone reads as UTC');

	pg_assert_same(gmmktime(6, 30, 0, 11, 20, 2026), ws_mail_remind_time('at', '2026-11-20T09:30', 'Europe/Istanbul', $now)['at'], 'a chosen time in the zone');
}

function test_workspace_mail_remind_short_time()
{
	$now = gmmktime(12, 0, 0, 11, 15, 2026);
	$at = gmmktime(6, 0, 0, 11, 16, 2026);

	pg_assert_same('16 Nov 09:00', ws_mail_remind_short_time($at, $now, 'Europe/Istanbul', false, false), 'day, month and hour in the zone; no year, no zone');
	pg_assert_same('Nov 16 9:00 AM', ws_mail_remind_short_time($at, $now, 'Europe/Istanbul', true, true), 'month first, twelve hours');
	pg_assert_same('5 Jan 2027 09:00', ws_mail_remind_short_time(gmmktime(9, 0, 0, 1, 5, 2027), $now, 'UTC', false, false), 'the year when it is not this year');
	pg_assert_same('1 Jan 2027 01:30', ws_mail_remind_short_time(gmmktime(22, 30, 0, 12, 31, 2026), $now, 'Europe/Istanbul', false, false), 'the year as the zone reads it');
}

function test_workspace_mail_remind_time_refuses()
{
	$now = gmmktime(12, 0, 0, 11, 14, 2026);

	pg_assert_same('choice', ws_mail_remind_time('soon', '', 'UTC', $now)['error'], 'an unknown choice');
	pg_assert_same('missing', ws_mail_remind_time('at', '', 'UTC', $now)['error'], 'no time typed');
	pg_assert_same('past', ws_mail_remind_time('at', '2026-11-14T11:00', 'UTC', $now)['error'], 'in the past');
	pg_assert_same('too_far', ws_mail_remind_time('at', '2027-02-14T12:00', 'UTC', $now)['error'], 'past sixty days');
	pg_assert_same('format', ws_mail_remind_time('at', '2027-03-28T02:30', 'Europe/Berlin', $now)['error'], 'an hour the zone skips');

	foreach (array('choice', 'missing', 'format', 'past', 'too_far') as $error) {
		pg_assert_true(ws_mail_remind_error_text($error) !== '', $error . ' is told');
	}
}

function test_workspace_mail_remind_conditions()
{
	pg_assert_same('', ws_mail_remind_hidden_sql(7, false), 'nothing before the table is there');
	pg_assert_same('', ws_mail_remind_woken_sql(7, false));
	pg_assert_same('', ws_mail_remind_hidden_sql(0, true), 'nobody: nothing');

	$hidden = ws_mail_remind_hidden_sql(7, true);
	pg_assert_contains("NOT EXISTS (SELECT 1 FROM ws_mail_reminders mr WHERE mr.conversation_id = ws_mail_conversations.id AND mr.user_id = '7'", $hidden);
	pg_assert_contains("mr.kind = 'later' AND mr.woken_at = 0)", $hidden, 'only the person\'s own, still waiting');

	$woken = ws_mail_remind_woken_sql(7, true);
	pg_assert_contains("EXISTS (SELECT 1 FROM ws_mail_reminders mr", $woken);
	pg_assert_contains("mr.kind = 'later' AND mr.woken_at > 0)", $woken, 'back and not read since');

	pg_assert_same("channel_id IN (3) AND message_id > 0", ws_mail_list_where(array('view' => 'all', 'status' => 'all', 'holder' => 'all', 'overdue' => false), 7, array(3)),
		'the list is unchanged without the table');
}

function test_workspace_mail_remind_tool()
{
	pg_assert_same(null, ws_mail_remind_tool(false, true, null), 'not offered outside the team');
	pg_assert_same(null, ws_mail_remind_tool(true, false, null), 'nor before the table is there');
	pg_assert_same(array('on' => false, 'at' => 0, 'short' => '', 'text' => ''), ws_mail_remind_tool(true, true, null), 'offered, none set');

	$set = ws_mail_remind_tool(true, true, array('due_at' => gmmktime(9, 0, 0, 11, 20, 2026)));
	pg_assert_true($set['on'], 'one set');
	pg_assert_same(gmmktime(9, 0, 0, 11, 20, 2026), $set['at']);
	pg_assert_true($set['text'] !== '', 'says when');
	pg_assert_true(($set['short'] !== '') && (mb_strlen($set['short']) < mb_strlen($set['text'])), 'the button\'s label is the short time');

	foreach (ws_mail_remind_js_strings() as $key => $text) {
		pg_assert_true($text !== '', $key . ' has words');
	}
}

function test_workspace_mail_remind_step()
{
	$source = workspace_mail_remind_test_source('includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_ws_mail_reminders();       // 11.0.212\n"), 'called once under 11.0.212');

	$step = substr($source, (int) strpos($source, 'function upgrade_2026_11_0_ws_mail_reminders'));
	pg_assert_contains("install_create_table('ws_mail_reminders', \"CREATE TABLE ws_mail_reminders (", $step);

	foreach (array('conversation_id INT UNSIGNED', 'user_id         INT UNSIGNED', 'kind            VARCHAR(10)', 'item_id         INT UNSIGNED',
		'due_at          INT UNSIGNED', 'woken_at        INT UNSIGNED', 'created_at      INT UNSIGNED',
		'UNIQUE KEY uniq_person (conversation_id, user_id, kind)', 'KEY idx_due (woken_at, due_at)') as $part) {
		pg_assert_contains($part, $step, 'the shape the owner approved: ' . $part);
	}

	pg_assert_true(strpos($step, 'db(') === false, 'only the runner helpers');
	pg_assert_contains("'ws_mail_reminders',", workspace_mail_remind_test_source('install/index.php'), 'listed for a reinstall');
}

function test_workspace_mail_remind_wiring()
{
	pg_assert_contains("ws_mail_remind_new_mail(\$conversation_id, \$now);", workspace_mail_remind_test_source('includes/workspace/mail.php'), 'a new mail brings it back');

	$reminders = workspace_mail_remind_test_source('includes/workspace/reminders.php');
	pg_assert_contains('ws_mail_reminders_waiting()', $reminders, 'the job\'s first look');
	pg_assert_contains('ws_mail_reminders_run($limit);', $reminders, 'the general job wakes it');
	pg_assert_contains("/includes/workspace/mail_remind.php'", workspace_mail_remind_test_source('job.php'), 'loaded before the first look');

	pg_assert_contains("case 'mail_remind':", workspace_mail_remind_test_source('includes/workspace/notify.php'), 'the inbox line is described');
	pg_assert_true(in_array('mail_remind', pg_notification_mail_kinds(), true), 'the bell shows it with the Workspace off');
	pg_assert_true(in_array('ws_mail_remind', ws_mail_actions_without_workspace(), true), 'the action works with the Workspace off');

	pg_assert_contains('ws_mail_remind_hidden_sql(', workspace_mail_remind_test_source('includes/workspace/mail_list.php'), 'the list leaves it out');
	pg_assert_contains('ws_mail_remind_woken_sql(', workspace_mail_remind_test_source('includes/workspace/mail_reads.php'), 'unread when back');
	pg_assert_contains('ws_mail_remind_read($user_id, $conversation_id);', workspace_mail_remind_test_source('includes/workspace/mail_reads.php'), 'read: done');
	pg_assert_contains("'remind'     => function_exists('ws_mail_remind_tool')", workspace_mail_remind_test_source('includes/workspace/mail_pane.php'), 'offered on the pane');
	pg_assert_contains("h.api('ws_mail_remind'", workspace_mail_remind_test_source('assets/js/workspace_mail.js'), 'the screen asks for it');
}
