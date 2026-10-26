<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Workspace follow-ups (includes/workspace/followups.php) that need
 * no database: a follow-up is a task tagged with the record it is about, on a
 * channel or for a person, due on a day and reminded by the task reminder;
 * ws_followup_task_data() turns what a caller asks for into the fields
 * ws_task_create() takes, and refuses what would never remind anybody.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// 26.10.2026 10:00 in the site's zone: after the day's 09:00 reminder time.
function workspace_followup_test_now()
{
	return mktime(10, 0, 0, 10, 26, 2026);
}

// A follow-up as the customer card asks for it.
function workspace_followup_test_input($extra = array())
{
	return array_merge(array(
		'title'      => 'Follow up: Demir Ltd',
		'refs'       => array(array('type' => 'contact', 'id' => 12)),
		'channel_id' => 40,
		'assignees'  => array(7),
		'due_date'   => '2026-11-02',
	), $extra);
}

// The fields ws_task_create() takes, tagged with the record and reminded at
// the due time unless told otherwise.
function test_ws_followup_task_data_shape()
{
	$check = ws_followup_task_data(7, workspace_followup_test_input(), workspace_followup_test_now());

	pg_assert_true($check['ok'], 'a follow-up with a record, a day and a person');
	pg_assert_same('Follow up: Demir Ltd', $check['data']['title'], 'the title as given');
	pg_assert_same('', $check['data']['description'], 'no note');
	pg_assert_same(array('<#contact:12>'), $check['data']['refs'], 'tagged with the record it is about');
	pg_assert_same(40, $check['data']['channel_id'], 'on the channel');
	pg_assert_same(array(7), $check['data']['assignees'], 'for the person');
	pg_assert_same('todo', $check['data']['status'], 'still to do');
	pg_assert_same('normal', $check['data']['priority'], 'of normal priority');
	pg_assert_same('2026-11-02', $check['data']['due_date'], 'due on the day');
	pg_assert_same(null, $check['data']['due_time'], 'no time: the day\'s reminder time');
	pg_assert_same(0, $check['data']['remind_minutes'], 'reminded at the due time');
}

// A follow-up is about a record: one is needed, and only # tags count.
function test_ws_followup_task_data_needs_a_record()
{
	$none = ws_followup_task_data(7, workspace_followup_test_input(array('refs' => array())), workspace_followup_test_now());

	pg_assert_false($none['ok'], 'no record');
	pg_assert_same('refs', $none['field'], 'the record is the field');

	$person = ws_followup_task_data(7, workspace_followup_test_input(array('refs' => array('<@user:9>'))), workspace_followup_test_now());

	pg_assert_false($person['ok'], 'a person is no record to follow up');

	$tokens = ws_followup_task_data(7, workspace_followup_test_input(array('refs' => array('<#erp_quote:5>', array('type' => 'erp_account', 'id' => 3), '<#erp_quote:5>'))), workspace_followup_test_now());

	pg_assert_true($tokens['ok'], 'tags as text or as type and id');
	pg_assert_same(array('<#erp_quote:5>', '<#erp_account:3>'), $tokens['data']['refs'], 'each record once, in the order given');
}

// A follow-up is on a day, written as the task drawer takes one.
function test_ws_followup_task_data_needs_a_day()
{
	$none = ws_followup_task_data(7, workspace_followup_test_input(array('due_date' => '')), workspace_followup_test_now());

	pg_assert_false($none['ok'], 'no day');
	pg_assert_same('due_date', $none['field'], 'the day is the field');

	$words = ws_followup_task_data(7, workspace_followup_test_input(array('due_date' => 'next week')), workspace_followup_test_now());

	pg_assert_false($words['ok'], 'words are no day');
	pg_assert_same(lang('Write the date as day.month.year.'), $words['error'], 'the task drawer\'s sentence');

	$dotted = ws_followup_task_data(7, workspace_followup_test_input(array('due_date' => '2.11.2026')), workspace_followup_test_now());

	pg_assert_true($dotted['ok'], 'day.month.year');
	pg_assert_same('2026-11-02', $dotted['data']['due_date'], 'kept as Y-m-d');

	$time = ws_followup_task_data(7, workspace_followup_test_input(array('due_time' => '25:00')), workspace_followup_test_now());

	pg_assert_false($time['ok'], 'no such time');
	pg_assert_same('due_time', $time['field'], 'the time is the field');

	$timed = ws_followup_task_data(7, workspace_followup_test_input(array('due_time' => '9:30')), workspace_followup_test_now());

	pg_assert_same('09:30:00', $timed['data']['due_time'], 'a time as the task keeps it');
}

// On a channel nobody needs to be on it (the person who opened it is
// reminded); without one it is the opener's own.
function test_ws_followup_task_data_people()
{
	$channel = ws_followup_task_data(7, workspace_followup_test_input(array('assignees' => array())), workspace_followup_test_now());

	pg_assert_same(array(), $channel['data']['assignees'], 'on a channel, nobody named');

	$own = ws_followup_task_data(7, workspace_followup_test_input(array('channel_id' => 0, 'assignees' => array())), workspace_followup_test_now());

	pg_assert_same(0, $own['data']['channel_id'], 'no channel');
	pg_assert_same(array(7), $own['data']['assignees'], 'the opener\'s own');

	$named = ws_followup_task_data(7, workspace_followup_test_input(array('channel_id' => 0, 'assignees' => array('9', 9, 0, -3, 11))), workspace_followup_test_now());

	pg_assert_same(array(9, 11), $named['data']['assignees'], 'the people named, each once');
}

// The reminder: at the due time unless told otherwise, none when asked for
// none, and never one that would go in the past.
function test_ws_followup_task_data_reminder()
{
	$early = ws_followup_task_data(7, workspace_followup_test_input(array('remind_minutes' => 1440)), workspace_followup_test_now());

	pg_assert_same(1440, $early['data']['remind_minutes'], 'a day before');

	$silent = ws_followup_task_data(7, workspace_followup_test_input(array('remind_minutes' => null)), workspace_followup_test_now());

	pg_assert_true($silent['ok'], 'no reminder asked for');
	pg_assert_same(null, $silent['data']['remind_minutes'], 'none');

	$far = ws_followup_task_data(7, workspace_followup_test_input(array('remind_minutes' => 86401)), workspace_followup_test_now());

	pg_assert_false($far['ok'], 'more than 60 days before');
	pg_assert_same('remind_minutes', $far['field'], 'the reminder is the field');

	$today = ws_followup_task_data(7, workspace_followup_test_input(array('due_date' => '2026-10-26')), workspace_followup_test_now());

	pg_assert_false($today['ok'], 'today at 09:00 has passed at 10:00');
	pg_assert_same('due_date', $today['field'], 'the day is the field');

	$later = ws_followup_task_data(7, workspace_followup_test_input(array('due_date' => '2026-10-26', 'due_time' => '15:00')), workspace_followup_test_now());

	pg_assert_true($later['ok'], 'today at 15:00 is still ahead');

	$before = ws_followup_task_data(7, workspace_followup_test_input(array('due_date' => '2026-10-27', 'remind_minutes' => 1440)), workspace_followup_test_now());

	pg_assert_true($before['ok'], 'a day before tomorrow 09:00 has passed, the task is not due yet: it goes at once');

	$overdue = ws_followup_task_data(7, workspace_followup_test_input(array('due_date' => '2026-10-20', 'remind_minutes' => null)), workspace_followup_test_now());

	pg_assert_true($overdue['ok'], 'a day gone by, with no reminder: already overdue, as a job may open it');
}

// The body of a function with its comment lines left out.
function workspace_followup_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// A follow-up is a task the person makes, with every check of one made by
// hand; a channel they can no longer write in leaves it theirs alone, and a
// job that runs again is handed back the open one.
function test_ws_followup_task_open_goes_through_the_task_checks()
{
	$source = workspace_followup_test_source('ws_followup_task_open');

	pg_assert_contains("empty(\$viewer['member'])", $source, 'members of the team only');
	pg_assert_contains('ws_followup_task_data(', $source, 'laid out and checked first');
	pg_assert_contains('ws_can_post_channel($viewer, ws_channel(', $source, 'a channel the person may write in');
	pg_assert_contains('ws_followup_task_find(', $source, 'one open follow-up when asked');
	pg_assert_contains('ws_task_create($viewer, $data)', $source, 'made as a task of that person');
	pg_assert_false(strpos($source, 'INSERT') !== false, 'never written around ws_task_create()');
}

// Finding an open follow-up again reads the statuses a task is open in.
function test_ws_followup_task_find_reads_open_tasks()
{
	$source = workspace_followup_test_source('ws_followup_task_find');

	pg_assert_true((bool) preg_match("~t\\.status IN \\(([^)]*)\\)~", $source, $match), 'by status');

	$listed = array_map(function ($status) {
		return trim($status, " '");
	}, explode(',', $match[1] ?? ''));
	$open = array_values(array_filter(array_keys(ws_task_statuses()), 'ws_task_is_open'));

	sort($listed);
	sort($open);

	pg_assert_same($open, $listed, 'the statuses ws_task_is_open() calls open');
	pg_assert_contains("r.source_type = 'task'", $source, 'by the task\'s tag of the record');
}

// "Remind me about this customer": the channel's customer tagged, for the
// person asking, on the channel; an empty title names the channel, never the
// customer a reader without the right sees only by kind and number.
function test_ws_customer_followup_open()
{
	$source = workspace_followup_test_source('ws_customer_followup_open');

	pg_assert_contains('ws_channel_customer_of($channel)', $source, 'the record the channel is about');
	pg_assert_contains("'assignees'  => array((int) \$viewer['id'])", $source, 'for the person asking');
	pg_assert_contains("'channel_id' => (int) \$channel['id']", $source, 'on the channel');
	pg_assert_contains("'Follow up: {var:1}', 'vars' => array((string) \$channel['name'])", $source, 'an empty title names the channel');
	pg_assert_false(strpos($source, "'remind_minutes'") !== false, 'reminded at the due time');
}

// The panel action asks for a channel the person may write in, and is a row
// of the panel action table.
function test_ws_channel_customer_followup_action()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($source, "case 'ws_channel_customer_followup':");

	pg_assert_true($start !== false, 'the Workspace actions answer ws_channel_customer_followup');

	if ($start !== false) {
		$case = substr($source, $start, 500);

		pg_assert_contains("ws_action_channel(\$viewer, \$request, 'post')", $case, 'a channel the person may write in');
		pg_assert_contains('ws_customer_followup_open($viewer, $channel, $request)', $case, 'the follow-up for that person');
	}

	if (!defined('PG_API_PANEL')) {
		define('PG_API_PANEL', true);
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

	$actions = pg_panel_actions();

	pg_assert_true(isset($actions['ws_channel_customer_followup']), 'a row of the panel action table');
	pg_assert_same('pg_panel_ws', $actions['ws_channel_customer_followup']['handler'] ?? '', 'handled by the Workspace');
}

// The card offers "Remind me" only where the reader may write, asks the
// action, and every word it shows is in the screen's strings.
function test_ws_customer_card_remind_me()
{
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_customer.js');

	pg_assert_contains("'ws_channel_customer_followup'", $card, 'the card asks the panel action');
	pg_assert_contains('channel.can_post && !channel.archived', $card, 'only where the reader may write');

	$strings = ws_customer_js_strings();

	foreach (array('cust_remind', 'cust_remind_help', 'cust_remind_what', 'cust_remind_default', 'cust_remind_day', 'cust_remind_time', 'cust_remind_save', 'cust_remind_note', 'cust_remind_done') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key);
		pg_assert_contains("'" . $key . "'", $card, $key . ' is shown');
	}

	pg_assert_contains('{1}', $strings['cust_remind_default'], 'the channel\'s name filled in by the card');
	pg_assert_contains('{2}', $strings['cust_remind_done'], 'the task and its day filled in by the card');
	pg_assert_contains(WS_TASK_REMIND_DAY_TIME, $strings['cust_remind_note'], 'the day\'s reminder time named');
}
