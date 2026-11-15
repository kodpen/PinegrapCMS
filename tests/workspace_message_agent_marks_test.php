<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an agent's marks in a message (messageAgentMarks() of
 * workspace.js, run under node): the agent's own name without the
 * "(application)" suffix, its role badge and its state dot, read from the
 * channel's member row of the agent (ws_agent_card_brief()). A person's, a
 * guest's or a plain application's message, an agent of another channel and
 * a channel without the brief get nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs messageAgentMarks() over the cases, each array(message, channel);
// false when node is missing, null when the function is not found.
function ws_message_agent_marks_test_run($cases)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/^    function messageAgentMarks\(message, channel\) \{.*?^    \}$/ms', $source, $match)) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwsmam');

	if ($file === false) {
		return false;
	}

	file_put_contents($file, $match[0] . "\n"
		. 'var cases = ' . json_encode($cases) . ';' . "\n"
		. 'console.log(JSON.stringify(cases.map(function (c) { return messageAgentMarks(c[0], c[1]); })));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function ws_message_agent_marks_test_message($sender_kind, $sender)
{
	return array('id' => 50, 'kind' => 'message', 'sender_kind' => $sender_kind, 'sender' => $sender, 'deleted' => false);
}

function ws_message_agent_marks_test_row($state, $role = 'Coordinator')
{
	return array('id' => 4, 'app_id' => 9, 'handle' => 'prmerge', 'name' => 'PR Merge', 'role' => $role,
		'role_key' => 'merger', 'state' => $state, 'state_words' => 'Words ' . $state, 'waiting' => 0, 'usage_high' => false);
}

function test_ws_message_agent_marks()
{
	$m = 'ws_message_agent_marks_test_message';
	$agent = array('app_id' => 9, 'name' => 'PR Merge app (application)', 'app' => true, 'agent' => true);
	$channel = function ($state, $role = 'Merger') {
		return array('id' => 25, 'agents' => array(ws_message_agent_marks_test_row($state, $role)));
	};

	$results = ws_message_agent_marks_test_run(array(
		array($m('app', $agent), $channel('working')),
		array($m('app', $agent), $channel('waiting')),
		array($m('app', $agent), $channel('late')),
		array($m('app', $agent), $channel('paused')),
		array($m('app', $agent), $channel('idle', '')),
		// A person, a guest, a plain application, Claude.
		array($m('user', array('id' => 3, 'name' => 'Ayşe')), $channel('working')),
		array($m('guest', array('id' => 0, 'name' => 'Guest')), $channel('working')),
		array($m('app', array('app_id' => 9, 'app' => true, 'agent' => false)), $channel('working')),
		array($m('app', array('app_id' => 9, 'app' => true, 'agent' => true, 'claude' => true)), $channel('working')),
		// An agent of another channel; a member row without the brief.
		array($m('app', array('app_id' => 12, 'app' => true, 'agent' => true)), $channel('working')),
		array($m('app', $agent), array('id' => 25, 'agents' => array(array('id' => 4, 'app_id' => 9, 'name' => 'PR Merge', 'role' => 'Merger')))),
		array($m('app', $agent), null),
	));

	// node is not installed: nothing to run.
	if ($results === false) {
		return;
	}

	pg_assert_true(is_array($results), 'messageAgentMarks() is there and runs');

	if (!is_array($results)) {
		return;
	}

	pg_assert_same(array('name' => 'PR Merge', 'role' => 'Merger', 'dot' => 'online', 'words' => 'Words working'), $results[0], 'working: green dot');
	pg_assert_same('away', $results[1]['dot'], 'waiting: amber dot');
	pg_assert_same('busy', $results[2]['dot'], 'not seen: the alarm dot');
	pg_assert_same('offline', $results[3]['dot'], 'paused: grey dot');
	pg_assert_same(array('name' => 'PR Merge', 'role' => '', 'dot' => '', 'words' => ''), $results[4], 'ready and no role: the name alone');

	for ($i = 5; $i <= 9; $i++) {
		pg_assert_same(null, $results[$i], 'no marks for case ' . $i);
	}

	pg_assert_same(array('name' => 'PR Merge', 'role' => 'Merger', 'dot' => '', 'words' => ''), $results[10], 'no brief: name and role, no dot');
	pg_assert_same(null, $results[11], 'no channel: nothing');
}
