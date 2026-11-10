<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an agent's scheduled wakes (includes/workspace/agent_wake.php)
 * and the agent's brief (agent_queue.php) that need no database: the /wake
 * line, the schedule it writes and how it moves on, the wake's channel and
 * state, the brief's parts, and the wiring into the commands, the API and
 * the general job.
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

function test_workspace_agent_wake_interval()
{
	pg_assert_same(30, ws_agent_wake_interval('30m'));
	pg_assert_same(45, ws_agent_wake_interval('45min'));
	pg_assert_same(20, ws_agent_wake_interval('20dk'));
	pg_assert_same(120, ws_agent_wake_interval('2h'));
	pg_assert_same(180, ws_agent_wake_interval('3SA'), 'any case');
	pg_assert_same(30, ws_agent_wake_interval('30minutes'), 'a number and its unit joined');
	pg_assert_same(0, ws_agent_wake_interval('30'), 'no unit');
	pg_assert_same(0, ws_agent_wake_interval('m'));
	pg_assert_same(0, ws_agent_wake_interval('2d'), 'days are no unit');
}

function test_workspace_agent_wake_parse()
{
	$now = strtotime('2026-11-08 18:00:00');

	$every = ws_agent_wake_parse('@ci every 30m "look at the queue"', $now);
	pg_assert_true($every['ok']);
	pg_assert_same('ci', $every['handle']);
	pg_assert_same('every', $every['action']);
	pg_assert_same(30, $every['every']);
	pg_assert_same('look at the queue', $every['note'], 'quotes taken off');

	$words = ws_agent_wake_parse('@CI her 2 saat kuyruğa bak', $now);
	pg_assert_same(array('ci', 120, 'kuyruğa bak'), array($words['handle'], $words['every'], $words['note']), 'Turkish words, the unit apart');

	$tag = ws_agent_wake_parse('<@app:7> off', $now);
	pg_assert_same(array(7, 'off'), array($tag['app_id'], $tag['action']), 'the application\'s tag names it too');

	$show = ws_agent_wake_parse('@ci', $now);
	pg_assert_true($show['ok'] && ($show['action'] === 'show'), 'the agent alone shows the schedule');

	$tonight = ws_agent_wake_parse('@ci at 21:00 nightly check', $now);
	pg_assert_same(strtotime('2026-11-08 21:00:00'), $tonight['at']);
	pg_assert_same('nightly check', $tonight['note']);

	$gone = ws_agent_wake_parse('@ci at 09:00', $now);
	pg_assert_same(strtotime('2026-11-09 09:00:00'), $gone['at'], 'a time gone today is tomorrow');

	$day = ws_agent_wake_parse('@ci at tomorrow 07:30', $now);
	pg_assert_same(strtotime('2026-11-09 07:30:00'), $day['at'], 'a day and a time');

	foreach (array('ci every 30m', '@ci every 0m', '@ci every 25h', '@ci every soon', '@ci at noon', '@ci at today 08:00', '@ci later', '') as $bad) {
		$parsed = ws_agent_wake_parse($bad, $now);
		pg_assert_true(!$parsed['ok'] && ($parsed['error'] !== ''), 'refused: ' . $bad);
	}

	pg_assert_true(!ws_agent_wake_parse('@ci every 30m ' . str_repeat('x', 251), $now)['ok'], 'a note over 250 characters');
}

function test_workspace_agent_wake_values_and_after()
{
	$now = 1000000;

	pg_assert_same(array('wake_every' => 30, 'wake_next_at' => $now + 1800, 'wake_note' => 'n'), ws_agent_wake_values(array('action' => 'every', 'every' => 30, 'note' => 'n'), $now));
	pg_assert_same(array('wake_every' => 0, 'wake_next_at' => $now + 50, 'wake_note' => ''), ws_agent_wake_values(array('action' => 'at', 'at' => $now + 50, 'note' => ''), $now));
	pg_assert_same(array('wake_every' => 0, 'wake_next_at' => 0, 'wake_note' => ''), ws_agent_wake_values(array('action' => 'off', 'note' => 'kept?'), $now), 'off clears the note too');

	$due = array('wake_every' => 30, 'wake_next_at' => $now - 10, 'wake_note' => 'n');
	pg_assert_same($now - 10 + 1800, ws_agent_wake_after($due, $now)['wake_next_at'], 'moves on from the time it was due');
	pg_assert_same($now + 1800, ws_agent_wake_after(array('wake_next_at' => $now - 7200) + $due, $now)['wake_next_at'], 'a missed hour makes one wake, not several');
	pg_assert_same(array('wake_every' => 0, 'wake_next_at' => 0, 'wake_note' => ''), ws_agent_wake_after(array('wake_every' => 0, 'wake_next_at' => $now, 'wake_note' => 'once'), $now), 'a one-time wake is over');
}

function test_workspace_agent_wake_channel_and_state()
{
	pg_assert_same(4, ws_agent_wake_channel(array(4)));
	pg_assert_same(4, ws_agent_wake_channel(array('4', 4)));
	pg_assert_same(0, ws_agent_wake_channel(array(4, 5)), 'several channels: none');
	pg_assert_same(0, ws_agent_wake_channel(array()));

	$row = array('agent' => 'agent:3', 'message_id' => 0, 'ai_state' => ws_agent_wake_state('every', 'look', 1700000000));
	pg_assert_same(array('kind' => 'every', 'note' => 'look', 'due' => 1700000000), ws_agent_wake_of($row));
	pg_assert_same(null, ws_agent_wake_of(array('message_id' => 9) + $row), 'a message\'s request is no wake');
	pg_assert_same(null, ws_agent_wake_of(array('agent' => 'model:3') + $row), 'only app agents are woken');
	pg_assert_same(null, ws_agent_wake_of(array('ai_state' => '') + $row));
	pg_assert_same(null, ws_agent_wake_of(array('agent' => 'app:3') + $row), 'an application\'s proposal is no wake');
}

function test_workspace_agent_wake_waiting_where()
{
	$where = ws_agent_wake_waiting_where('agent:3');
	pg_assert_contains("agent = 'agent:3'", $where);
	pg_assert_contains('message_id = 0', $where, 'only a schedule\'s wake holds the next one');
	pg_assert_contains("status IN ('queued', 'sent')", $where, 'a claimed wake does not');
}

function test_workspace_agent_brief_parts()
{
	$guard = implode("\n", ws_agent_loop_guard_lines());
	pg_assert_true(strpos($guard, 'no_answer') !== false, 'the loop guard says how to stay quiet');
	pg_assert_true(strpos($guard, '/workspace/agent-wake') !== false, 'and how to check in instead');

	$tools = implode("\n", ws_agent_brief_tool_lines('https://site.example/mcp.php', array('workspace:write', 'workspace:read', ''), 12));
	pg_assert_true(strpos($tools, 'https://site.example/mcp.php') !== false);
	pg_assert_true(strpos($tools, 'workspace:read, workspace:write.') !== false, 'scopes sorted, empty ones left out');
	pg_assert_true(strpos($tools, '/workspace/agent-requests/12/answer') !== false);

	$channels = array(array('id' => 4, 'name' => "ops\nroom"), array('id' => 5, 'name' => 'ci'));
	$one = implode("\n", ws_agent_brief_wake_lines(array('kind' => 'every'), array($channels[0]), 4));
	pg_assert_true(strpos($one, '#ops room (id 4)') !== false, 'the channel on one line');

	$many = implode("\n", ws_agent_brief_wake_lines(array('kind' => 'at'), $channels, 0));
	pg_assert_true(strpos($many, 'one-time') !== false);
	pg_assert_true((strpos($many, '(id 5)') !== false) && (strpos($many, 'workspace.messages.create') !== false), 'several channels: it writes where it needs');

	pg_assert_true(strpos(implode("\n", ws_agent_brief_wake_lines(array('kind' => 'every'), array(), 0)), 'no_answer') !== false, 'no channel: it closes the request');
}

// A function's source without its comment lines.
function workspace_agent_wake_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

function test_workspace_agent_wake_wiring()
{
	pg_assert_same('wake', ws_command_names()['uyandır'] ?? '', '/uyandır is /wake');
	pg_assert_same('wake', ws_command_names()['wake'] ?? '');
	pg_assert_true(strpos(workspace_agent_wake_test_source('ws_command_run'), 'ws_command_wake(') !== false, 'the command runs');
	pg_assert_true(in_array('wake', array_column(ws_commands(null), 'key'), true), 'listed for /help and the picker');

	$run = workspace_agent_wake_test_source('ws_agent_wakes_run');
	pg_assert_true(strpos($run, 'UPDATE ws_agents') < strpos($run, 'ws_agent_wake_request('), 'the schedule moves on before the request, so overlapping ticks make one');
	pg_assert_true(strpos($run, 'mysqli_affected_rows') !== false, 'checked by affected rows');

	$request = workspace_agent_wake_test_source('ws_agent_wake_request');
	pg_assert_true(strpos($request, 'ws_agent_brake(') < strpos($request, 'INSERT INTO ws_ai_requests'), 'the hourly brake holds for wakes');
	pg_assert_true(strpos($request, 'ws_agent_knock(') !== false, 'a push door is knocked');

	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php'), 'ws_agent_wakes_run()') !== false, 'the general job makes the wakes');

	$set = workspace_agent_wake_test_source('ws_api_agent_wake_set');
	pg_assert_true(strpos($set, 'api_dry_run_stop(') < strpos($set, 'ws_agent_wake_save('), 'rehearsed before it is written');

	pg_assert_true(array_key_exists('wake', ws_api_claude_request_schema()), 'a request says what woke it');
}
