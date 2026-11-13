<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's agents in a message's "Who has seen it" drawer:
 * the three states ws_message_seen_agents_sort() gives (seen, woken but not
 * taken yet, not woken), the row's words, and seenByParts() in
 * assets/js/workspace.js, which keeps "Everyone in the channel has seen it"
 * for when no woken agent waits.
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

// The channel's agents as ws_message_seen_agents_of() gives them.
function workspace_message_seen_agents_test_agents()
{
	return array(
		array('key' => 'claude', 'keys' => array('claude'), 'app_id' => 2, 'name' => 'Claude', 'handle' => '', 'tone' => 3, 'assistant' => true),
		array('key' => 'agent-7', 'keys' => array('agent:7', 'model:7'), 'app_id' => 70, 'name' => 'Mimar', 'handle' => 'mimar', 'tone' => 7, 'assistant' => false),
		array('key' => 'agent-8', 'keys' => array('agent:8', 'model:8'), 'app_id' => 80, 'name' => 'Merge', 'handle' => 'prmerge', 'tone' => 8, 'assistant' => false),
		array('key' => 'agent-9', 'keys' => array('agent:9', 'model:9'), 'app_id' => 0, 'name' => 'Writer', 'handle' => 'writer', 'tone' => 9, 'assistant' => true),
	);
}

function workspace_message_seen_agents_test_request($agent, $status, $claimed = 0, $answered = 0, $created = 100)
{
	return array('agent' => $agent, 'status' => $status, 'created_at' => $created, 'claimed_at' => $claimed, 'answered_at' => $answered);
}

function workspace_message_seen_agents_test_keys($rows)
{
	return array_map(function ($row) { return $row['key'] . ':' . $row['state'] . ':' . $row['at']; }, $rows);
}

function test_workspace_message_seen_agents_three_states()
{
	$requests = array(
		workspace_message_seen_agents_test_request('claude', 'answered', 110, 120),
		workspace_message_seen_agents_test_request('agent:7', 'queued'),
		workspace_message_seen_agents_test_request('model:9', 'running', 130),
	);
	$out = ws_message_seen_agents_sort(workspace_message_seen_agents_test_agents(), $requests, array());

	pg_assert_same(array('claude:answered:120', 'agent-9:took:130'), workspace_message_seen_agents_test_keys($out['seen']), 'answered and taken are seen');
	pg_assert_same(array('agent-7:waiting:100'), workspace_message_seen_agents_test_keys($out['waiting']), 'a queued request waits');
	pg_assert_same(array('agent-8:quiet:0'), workspace_message_seen_agents_test_keys($out['quiet']), 'no request: not woken, never seen');
}

function test_workspace_message_seen_agents_newest_request_and_marks()
{
	$requests = array(
		workspace_message_seen_agents_test_request('agent:7', 'answered', 101, 102, 100),
		workspace_message_seen_agents_test_request('agent:7', 'sent', 0, 0, 200),
		workspace_message_seen_agents_test_request('claude', 'failed', 150, 0),
		workspace_message_seen_agents_test_request('agent:8', 'cancelled'),
	);
	$out = ws_message_seen_agents_sort(workspace_message_seen_agents_test_agents(), $requests, array(80 => array('state' => 'reacted', 'at' => 140)));

	pg_assert_same(array('claude:took:150', 'agent-8:reacted:140'), workspace_message_seen_agents_test_keys($out['seen']), 'failed after it was taken, and a reaction, count as seen');
	pg_assert_same(array('agent-7:waiting:200'), workspace_message_seen_agents_test_keys($out['waiting']), 'the newest request decides');

	// An answer under the message outranks a request still waiting.
	$out = ws_message_seen_agents_sort(workspace_message_seen_agents_test_agents(), array(workspace_message_seen_agents_test_request('agent:7', 'queued')),
		array(70 => array('state' => 'answered', 'at' => 160)));

	pg_assert_same(array('agent-7:answered:160'), workspace_message_seen_agents_test_keys($out['seen']));
	pg_assert_same(array(), $out['waiting']);
}

function test_workspace_message_seen_agents_writer_left_out()
{
	$out = ws_message_seen_agents_sort(workspace_message_seen_agents_test_agents(), array(), array(), 70);
	$keys = array_map(function ($row) { return $row['key']; }, $out['quiet']);

	pg_assert_same(array('claude', 'agent-8', 'agent-9'), $keys, 'the agent that wrote the message is not listed');
}

function test_workspace_message_seen_agent_row_words()
{
	$now = 1700000000;
	$row = ws_message_seen_agent_row(array('key' => 'agent-7', 'name' => 'Mimar', 'handle' => 'mimar', 'tone' => 7, 'assistant' => false, 'state' => 'took', 'at' => $now - 5 * 60), $now);

	pg_assert_same(lang(array('string' => 'took it {var:1}', 'vars' => ws_seen_ago_words($now - 5 * 60, $now))), $row['line']);
	pg_assert_same('took', $row['state']);

	$row = ws_message_seen_agent_row(array('key' => 'claude', 'name' => 'Claude', 'handle' => '', 'tone' => 3, 'assistant' => true, 'state' => 'waiting', 'at' => $now), $now);

	pg_assert_same(lang('woken, not taken yet'), $row['line']);
	pg_assert_true($row['assistant']);
}

// seenByParts() cut out of workspace.js and run with Node; null without it.
function workspace_message_seen_agents_test_parts($cases)
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/\n    function seenByParts\(data\) \{\n.*?\n    \}\n/s', $js, $match) || !function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgseen');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $match[0] . "\nprocess.stdout.write(JSON.stringify(" . json_encode($cases) . ".map(function (c) { var p = seenByParts(c); return [p.alone, p.everyone]; })));\n");
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function test_workspace_message_seen_agents_drawer_parts()
{
	pg_assert_contains('function seenByParts(data)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'));

	$person = array('id' => 1, 'name' => 'Ayşe');
	$agent = array('key' => 'agent-7', 'name' => 'Mimar');
	$results = workspace_message_seen_agents_test_parts(array(
		array('seen' => array($person)),
		array('seen' => array($person), 'agents_waiting' => array($agent)),
		array('agents_quiet' => array($agent)),
		array('agents_seen' => array($agent)),
	));

	if ($results === null) {
		return;
	}

	pg_assert_same(array(false, true), $results[0], 'every person has seen it: everyone');
	pg_assert_same(array(false, false), $results[1], 'a woken agent waits: not everyone');
	pg_assert_same(array(true, true), $results[2], 'only an agent not woken: the one line');
	pg_assert_same(array(false, true), $results[3], 'an agent that saw it is not alone');
}

function test_workspace_message_seen_agents_texts()
{
	$texts = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	foreach (array('seen_by_quiet', 'seen_by_agents_help') as $key) {
		pg_assert_contains("'" . $key . "'", $texts, $key . ' has its text');
	}
}
