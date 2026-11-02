<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the daily summary Claude writes for the Assistant card
 * (includes/workspace/claude.php) that need no database: when it is due,
 * how its request is told from the others, the setting as posted, and what
 * the routine is told about it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_workspace_claude_summary_due()
{
	pg_assert_true(ws_claude_summary_due(9, 4, 9, false), 'the hour came');
	pg_assert_true(ws_claude_summary_due(9, 4, 15, false), 'the hour passed and none was asked: a site whose job ran late still gets it');
	pg_assert_false(ws_claude_summary_due(9, 4, 8, false), 'before the hour');
	pg_assert_false(ws_claude_summary_due(9, 4, 10, true), 'already asked today');
	pg_assert_false(ws_claude_summary_due(WS_CLAUDE_SUMMARY_OFF, 4, 23, false), 'off');
	pg_assert_false(ws_claude_summary_due(0, 0, 12, false), 'no channel');
}

function test_workspace_claude_is_summary_request()
{
	$row = array('channel_id' => 4, 'message_id' => 0, 'note_id' => 0, 'task_id' => 0, 'note_text' => WS_CLAUDE_SUMMARY_MARK);

	pg_assert_true(ws_claude_is_summary_request($row));
	pg_assert_false(ws_claude_is_summary_request(array('message_id' => 7) + $row), 'somebody asked in a message');
	pg_assert_false(ws_claude_is_summary_request(array('note_id' => 2) + $row), 'asked in a note');
	pg_assert_false(ws_claude_is_summary_request(array('task_id' => 2) + $row), 'a run on a task');
	pg_assert_false(ws_claude_is_summary_request(array('page_id' => 2) + $row), 'asked about a page');
	pg_assert_false(ws_claude_is_summary_request(array('note_text' => 'daily summary please') + $row), 'words, not the mark');
	pg_assert_false(ws_claude_is_summary_request(array('channel_id' => 0) + $row), 'no channel');
}

function test_workspace_claude_summary_input()
{
	$channels = array(4 => 'general', 9 => 'shop');

	pg_assert_same(array('ok' => true, 'hour' => 8, 'channel_id' => 9), ws_claude_summary_input('8', '9', $channels));
	pg_assert_same(array('ok' => true, 'hour' => 0, 'channel_id' => 4), ws_claude_summary_input('0', 4, $channels), 'midnight is an hour');
	pg_assert_same(array('ok' => false, 'hour' => 8, 'channel_id' => 0), ws_claude_summary_input('8', '12', $channels), 'a channel Claude may not write in');
	pg_assert_same(array('ok' => true, 'hour' => WS_CLAUDE_SUMMARY_OFF, 'channel_id' => 9), ws_claude_summary_input('', '9', $channels), 'off keeps the channel');
	pg_assert_same(WS_CLAUDE_SUMMARY_OFF, ws_claude_summary_input('24', '9', $channels)['hour'], 'no such hour: off');
	pg_assert_same(WS_CLAUDE_SUMMARY_OFF, ws_claude_summary_input('9am', '9', $channels)['hour'], 'not a number: off');
}

function test_workspace_claude_summary_in_the_instructions()
{
	$prompt = ws_claude_routine_prompt('https://shop.example/pinegrap/integration.php');

	pg_assert_contains('"summary": true', $prompt);
	pg_assert_contains('nobody mentioned', $prompt);
	pg_assert_contains('exactly three lines', ws_claude_summary_instruction());
}
