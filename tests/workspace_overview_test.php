<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workspace overview (includes/workspace/home.php, drawHome()
 * and its cards in assets/js/workspace.js): the greeting by the hour, the
 * line under it, today's calendar rows; the cards in their drawn order, a
 * task ticked off from it, a meeting's link, the waiting rows of the
 * approval and read-receipt files.
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

// The body of one method of the channel screen in workspace.js, or ''.
function workspace_overview_test_method($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/\n        ' . preg_quote($name, '/') . ': function \([^)]*\) \{\n(.*?)\n        \},\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_overview_greets_by_the_hour()
{
	$cases = array(
		0 => 'Good night', 4 => 'Good night', 5 => 'Good morning', 11 => 'Good morning',
		12 => 'Good afternoon', 17 => 'Good afternoon', 18 => 'Good evening', 22 => 'Good evening', 23 => 'Good night',
	);

	foreach ($cases as $hour => $words) {
		pg_assert_same($words, ws_home_greeting($hour), $hour . ':00');
	}
}

function test_workspace_overview_lead_says_the_counts_or_that_all_is_read()
{
	pg_assert_same('Everything is read and nothing is due today.', ws_home_lead(array()), 'nothing new, nothing due');
	pg_assert_same(
		'12 new messages · 1 mention · 2 approval requests · 1 read receipt asked of you · 3 tasks due today or overdue',
		ws_home_lead(array('messages' => 12, 'mentions' => 1, 'approvals' => 2, 'acks' => 1, 'due' => 3))
	);
	pg_assert_same('1 new message · 1 task due today or overdue', ws_home_lead(array('messages' => 1, 'mentions' => -2, 'due' => 1)), 'a count below one says nothing');
}

function test_workspace_overview_calendar_rows_by_the_hour()
{
	$start = strtotime('2026-10-31 00:00:00');
	$end = strtotime('2026-10-31 23:59:59');
	$at = function ($time) {
		return strtotime('2026-10-31 ' . $time . ':00');
	};

	$rows = ws_home_calendar_rows(array(
		array('id' => 3, 'title' => 'Shoot', 'starts_at' => $at('20:00'), 'ends_at' => $at('21:00')),
		array('id' => 2, 'title' => 'Weekly', 'starts_at' => $at('17:00'), 'ends_at' => $at('19:00'), 'meeting_url' => 'https://meet.example.com/w'),
		array('id' => 1, 'title' => 'Call', 'starts_at' => $at('09:30'), 'ends_at' => $at('10:00')),
		array('id' => 4, 'title' => 'Holiday', 'all_day' => 1, 'starts_at' => $start, 'ends_at' => $end),
		array('id' => 5, 'title' => 'Overnight', 'starts_at' => strtotime('2026-10-30 22:00:00'), 'ends_at' => $at('08:00'), 'layer' => 'Home'),
	), $start, $end, $at('17:30'));

	pg_assert_same(array('Holiday', 'Overnight', 'Call', 'Weekly', 'Shoot'), array_column($rows, 'title'), 'all-day items first, then by the hour');
	pg_assert_same('', $rows[0]['time'], 'an all-day item has no hour');
	pg_assert_false($rows[0]['now'] || $rows[0]['past'], 'an all-day item is neither going on nor over');
	pg_assert_same(array('00:00', '08:00', 'Home'), array($rows[1]['time'], $rows[1]['until'], $rows[1]['layer']), 'begun yesterday: from midnight, with its calendar');
	pg_assert_true($rows[2]['past'] && !$rows[2]['now'], 'the morning call is over');
	pg_assert_true($rows[3]['now'] && !$rows[3]['past'], 'the meeting is going on');
	pg_assert_same('https://meet.example.com/w', $rows[3]['meeting_url']);
	pg_assert_true(!$rows[4]['now'] && !$rows[4]['past'], 'the shoot is still to come');
	pg_assert_false(array_key_exists('at', $rows[0]), 'the sort key is not sent');
}

function test_workspace_overview_calendar_shows_at_most_eight()
{
	$start = strtotime('2026-10-31 00:00:00');
	$items = array();

	for ($i = 1; $i <= 10; $i++) {
		$items[] = array('id' => $i, 'title' => 'Item ' . $i, 'starts_at' => $start + $i * 3600, 'ends_at' => $start + $i * 3600 + 1800);
	}

	$rows = ws_home_calendar_rows($items, $start, $start + 86399, $start);

	pg_assert_same(8, count($rows));
	pg_assert_same('Item 1', $rows[0]['title'], 'the earliest stay');
}

// The greeting only here, then the cards as drawn: waiting, due, calendar;
// the channels' news and the latest decisions; joining and what this is.
function test_workspace_overview_cards_in_their_order()
{
	$draw = workspace_overview_test_method('drawHome');
	$marks = array("t('home_greeting', data.greeting", 'data.lead', "t('home_new_channel')", 'self.homeWaiting(', 'self.homeDue(', 'self.homeCalendar(', 'self.homeDigest(', "t('home_decisions')", 'data.join.length', "'card ws-home-about'");
	$last = -1;

	pg_assert_true($draw !== '', 'drawHome() is found');

	foreach ($marks as $mark) {
		$at = strpos($draw, $mark);

		pg_assert_true($at !== false, $mark . ' is drawn');
		pg_assert_true($at > $last, $mark . ' comes after the part before it');
		$last = (int) $at;
	}

	pg_assert_true(strpos(workspace_overview_test_method('showHome'), 'data.date') === false, 'the header holds the name alone; the day stands over the greeting');
}

function test_workspace_overview_cards_act_from_the_page()
{
	$due = workspace_overview_test_method('homeDue');
	$calendar = workspace_overview_test_method('homeCalendar');
	$waiting = workspace_overview_test_method('homeWaiting');

	pg_assert_contains("api('ws_task_status', { task_id: task.id, status: 'done' })", $due, 'a task is ticked off from the overview');
	pg_assert_contains("taskDrawer.open(task.id", $due, 'the rest of the row opens it');
	pg_assert_contains('/^https?:/i.test(item.meeting_url)', $calendar, 'a meeting link is followed only when it is a web address');
	pg_assert_contains("call.rel = 'noopener noreferrer';", $calendar);
	pg_assert_contains('feature.homeRows(data, self, helpers, card.body)', $waiting, 'approvals and read receipts draw their own rows');

	foreach (array('workspace_approvals.js', 'workspace_acks.js') as $file) {
		pg_assert_contains('homeRows: homeRows', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file), $file . ' gives the overview its rows');
	}
}

function test_workspace_overview_texts_are_given()
{
	$texts = ws_home_js_strings();

	foreach (array('home_greeting', 'home_waiting', 'home_waiting_empty', 'home_due', 'home_calendar', 'home_calendar_more', 'home_calendar_empty', 'home_all_day', 'home_now', 'home_digest', 'home_digest_empty', 'home_digest_new') as $key) {
		pg_assert_true(isset($texts[$key]), $key . ' is given');
	}

	pg_assert_true(isset(ws_approvals_js_strings()['apv_home_asks']), 'an approval row says what is asked');
	pg_assert_true(isset(ws_acks_js_strings()['ack_home_asks']), 'a read-receipt row says what is asked');
}
