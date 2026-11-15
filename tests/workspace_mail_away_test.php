<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "I am away" (mail_away.php): the range, the text and the
 * stand-in a person keeps under My e-mail settings, when the automatic
 * answer is due, who a conversation goes to, the schema step and the wiring
 * into the ingest, the holder change and the drawer. The writes need a
 * database and are checked in their source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_away_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_workspace_mail_away_input()
{
	$ok = ws_mail_away_input(array('start' => '2026-11-20', 'end' => '2026-11-27', 'text' => " Back on Monday.\n", 'stand_in' => '7'), '2026-11-15');
	pg_assert_true($ok['ok'], 'a whole range is kept');
	pg_assert_same(array('start' => '2026-11-20', 'end' => '2026-11-27', 'text' => 'Back on Monday.', 'stand_in' => 7), $ok['away'], 'cleaned');

	$one = ws_mail_away_input(array('start' => '2026-11-15', 'end' => '', 'text' => 'Out today.'), '2026-11-15');
	pg_assert_same('2026-11-15', $one['away']['end'], 'no last day: one day');
	pg_assert_same(0, $one['away']['stand_in'], 'no stand-in');

	$clear = ws_mail_away_input(array('start' => '', 'end' => '2026-11-27', 'text' => 'x'), '2026-11-15');
	pg_assert_true($clear['ok'] && ($clear['away'] === null), 'no first day: switched off');

	pg_assert_same('start', ws_mail_away_input(array('start' => '2026-02-30', 'text' => 'x'), '2026-01-01')['field'], 'no such day');
	pg_assert_same('end', ws_mail_away_input(array('start' => '2026-11-20', 'end' => '2026-11-19', 'text' => 'x'), '2026-11-15')['field'], 'the last day before the first');
	pg_assert_same('end', ws_mail_away_input(array('start' => '2026-11-01', 'end' => '2026-11-14', 'text' => 'x'), '2026-11-15')['field'], 'a range already over');
	pg_assert_same('end', ws_mail_away_input(array('start' => '2026-11-15', 'end' => '2028-01-01', 'text' => 'x'), '2026-11-15')['field'], 'longer than a year');
	pg_assert_same('text', ws_mail_away_input(array('start' => '2026-11-20', 'text' => "  \n"), '2026-11-15')['field'], 'a text is needed');
	pg_assert_same('text', ws_mail_away_input(array('start' => '2026-11-20', 'text' => str_repeat('a', WS_MAIL_AUTO_REPLY_MAX + 1)), '2026-11-15')['field'], 'as long as a box\'s automatic answer at most');
	pg_assert_same('stand_in', ws_mail_away_input(array('start' => '2026-11-20', 'text' => 'x', 'stand_in' => 'abc'), '2026-11-15')['field'], 'a stand-in is a person');
	pg_assert_same('text', ws_mail_away_input(array('start' => '2026-11-20', 'text' => array('x')), '2026-11-15')['field'], 'never an array');
}

function test_workspace_mail_away_decode()
{
	pg_assert_same(null, ws_mail_away_decode(null), 'none kept');
	pg_assert_same(null, ws_mail_away_decode('not json'), 'broken');
	pg_assert_same(null, ws_mail_away_decode('{"start":"2026-11-20","end":"2026-11-19","text":"x"}'), 'a broken range');
	pg_assert_same(null, ws_mail_away_decode('{"start":"2026-11-20","end":"2026-11-21","text":""}'), 'no text');
	pg_assert_same(array('start' => '2026-11-20', 'end' => '2026-11-21', 'text' => 'x', 'stand_in' => 4, 'plan_id' => 9),
		ws_mail_away_decode('{"start":"2026-11-20","end":"2026-11-21","text":"x","stand_in":"4","plan_id":9}'), 'read back');
}

function test_workspace_mail_away_on()
{
	$away = array('start' => '2026-11-20', 'end' => '2026-11-22', 'text' => 'x', 'stand_in' => 0, 'plan_id' => 0);
	pg_assert_false(ws_mail_away_on($away, '2026-11-19'), 'the day before');
	pg_assert_true(ws_mail_away_on($away, '2026-11-20'), 'the first day');
	pg_assert_true(ws_mail_away_on($away, '2026-11-22'), 'the last day');
	pg_assert_false(ws_mail_away_on($away, '2026-11-23'), 'the day after');
	pg_assert_false(ws_mail_away_on(null, '2026-11-20'), 'not away');
}

function test_workspace_mail_away_today_in_the_persons_zone()
{
	// 2026-11-19 22:30 UTC is already the 20th in Istanbul (UTC+3).
	$now = gmmktime(22, 30, 0, 11, 19, 2026);
	pg_assert_same('2026-11-20', ws_mail_away_today('Europe/Istanbul', $now), 'their zone');
	pg_assert_same('2026-11-19', ws_mail_away_today('UTC', $now), 'UTC');
	pg_assert_same('2026-11-19', ws_mail_away_today('No/Such_Zone', $now), 'an unknown zone is UTC');
	pg_assert_same(gmmktime(21, 0, 0, 11, 19, 2026), ws_mail_away_day_start('2026-11-20', 'Europe/Istanbul'), 'the first day starts at their midnight');
}

function test_workspace_mail_away_reply_due()
{
	$away = array('start' => '2026-11-20', 'end' => '2026-11-22', 'text' => 'Away.', 'stand_in' => 0, 'plan_id' => 0);
	pg_assert_true(ws_mail_away_reply_due($away, '2026-11-21', 'none', 0), 'in the range, not answered yet');
	pg_assert_true(ws_mail_away_reply_due($away, '2026-11-21', 'pass', 0), 'a verified sender');
	pg_assert_false(ws_mail_away_reply_due($away, '2026-11-21', 'fail', 0), 'never to a forged sender');
	pg_assert_false(ws_mail_away_reply_due($away, '2026-11-21', 'none', 1), 'once per sender');
	pg_assert_false(ws_mail_away_reply_due($away, '2026-11-23', 'none', 0), 'out of the range');
	pg_assert_false(ws_mail_away_reply_due(null, '2026-11-21', 'none', 0), 'not away');
}

function test_workspace_mail_away_hand_to()
{
	$away = array('start' => '2026-11-20', 'end' => '2026-11-22', 'text' => 'x', 'stand_in' => 8, 'plan_id' => 0);
	pg_assert_same(8, ws_mail_away_hand_to($away, '2026-11-21', 5, true), 'away: the stand-in');
	pg_assert_same(5, ws_mail_away_hand_to($away, '2026-11-21', 5, false), 'a stand-in who may not answer there: the person');
	pg_assert_same(5, ws_mail_away_hand_to($away, '2026-11-24', 5, true), 'back: the person');
	pg_assert_same(5, ws_mail_away_hand_to(array_merge($away, array('stand_in' => 0)), '2026-11-21', 5, true), 'no stand-in: the person');
	pg_assert_same(8, ws_mail_away_hand_to(array_merge($away, array('stand_in' => 8)), '2026-11-21', 8, true), 'never to themselves');
	pg_assert_same(5, ws_mail_away_hand_to(null, '2026-11-21', 5, true), 'not away');
}

function test_workspace_mail_away_step()
{
	$source = workspace_mail_away_test_source('includes/migrations/2026.11.0.php');
	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_ws_mail_away();"), 'called once');

	$start = (int) strpos($source, 'function upgrade_2026_11_0_ws_mail_away()');
	pg_assert_true($start > 0, 'the step is there');
	$body = substr($source, $start, (int) strpos($source, "\n}\n", $start) - $start);
	pg_assert_contains("install_add_column('ws_profiles', 'mail_away', \"TEXT NULL\");", $body, 'the column, idempotent');
	pg_assert_true(strpos($body, 'db(') === false, 'only the runner helpers');
}

function test_workspace_mail_away_wiring()
{
	$ingest = workspace_mail_away_test_source('includes/workspace/mail.php');
	pg_assert_contains("} elseif (!\$opened && function_exists('ws_mail_away_reply')) {\n        ws_mail_away_reply(\$box, \$item_id, \$conversation);", $ingest, 'an ongoing conversation, after the box\'s own answer');

	$status = workspace_mail_away_test_source('includes/workspace/mail_status.php');
	pg_assert_contains('ws_mail_auto_reply_send($box, $item, $conversation, $text,', $status, 'the box\'s answer shares the sending');
	pg_assert_contains('ws_mail_away_stand_in(', $status, 'a conversation given by hand or the API goes to the stand-in');

	$away = workspace_mail_away_test_source('includes/workspace/mail_away.php');
	pg_assert_contains("pg_schema_has('ws_profiles', 'mail_away')", $away, 'probed before it is read');
	pg_assert_contains("'visibility' => 'team'", $away, 'a plan the whole team sees');
	pg_assert_contains('ws_personal_plan_save(', $away, 'through the plans\' own module');
	pg_assert_contains("AND direction = 'out' AND user_id = 0", $away, 'once per sender, from the automatic answers');
	pg_assert_true(strpos($away, 'ws_personal_plans') === false || strpos($away, 'FROM ws_personal_plans') === false, 'never reads the plan table itself');

	$actions = workspace_mail_away_test_source('includes/workspace/actions.php');
	pg_assert_contains("'away' => \$away_ready ? ws_mail_away_offer(", $actions, 'the drawer reads the range');
	pg_assert_contains('ws_mail_away_save($viewer, $request', $actions, 'and keeps it');

	$script = workspace_mail_away_test_source('assets/js/workspace_mail.js');
	pg_assert_contains("t('mail_away_title')", $script, 'the drawer\'s section');
	pg_assert_contains('away_stand_in', $script, 'the stand-in is sent');
}
