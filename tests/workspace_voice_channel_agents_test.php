<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which answers are read aloud (speaksAnswer() of workspace.js,
 * run under node): an assistant's answer to the viewer's own message, and a
 * registry agent's of the channel that answers the viewer's own message or
 * names them; never a person's, a guest's, an application's that is no
 * agent, nor an agent's of another channel. The channel's agents carry the
 * application they write as (ws_channel_member_agent()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_agents_panel.php');

// Runs speaksAnswer() over the cases, each array(message, parent, channel);
// false when node is missing, null when the function is not found.
function ws_voice_channel_agents_test_run($cases)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/^    function speaksAnswer\(message, parent, channel\) \{.*?^    \}$/ms', $source, $match)) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwsspk');

	if ($file === false) {
		return false;
	}

	file_put_contents($file, $match[0] . "\n"
		. 'var cases = ' . json_encode($cases) . ';' . "\n"
		. 'console.log(JSON.stringify(cases.map(function (c) { return speaksAnswer(c[0], c[1], c[2]); })));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

// A message of $sender_kind with $sender, answering $parent_id.
function ws_voice_channel_agents_test_message($sender_kind, $sender, $parent_id = 0, $extra = array())
{
	return $extra + array(
		'id'          => 50,
		'kind'        => 'message',
		'sender_kind' => $sender_kind,
		'sender'      => $sender,
		'parent_id'   => $parent_id,
		'mentions_me' => false,
		'deleted'     => false,
	);
}

function test_ws_voice_channel_agents_decision()
{
	$channel = array('id' => 25, 'agents' => array(array('id' => 4, 'app_id' => 9, 'handle' => 'prmerge')));
	$mine = array('id' => 10, 'mine' => true);
	$theirs = array('id' => 11, 'mine' => false);
	$agent = array('app_id' => 9, 'app' => true, 'agent' => true);
	$other_agent = array('app_id' => 12, 'app' => true, 'agent' => true);
	$app = array('app_id' => 9, 'app' => true, 'agent' => false);
	$m = 'ws_voice_channel_agents_test_message';

	$cases = array(
		'claude to my message'          => array($m('app', array('claude' => true), 10), $mine, $channel),
		'claude to theirs'              => array($m('app', array('claude' => true), 11), $theirs, $channel),
		'claude naming me, no answer'   => array($m('app', array('claude' => true), 0, array('mentions_me' => true)), null, $channel),
		'agent to my message'           => array($m('app', $agent, 10), $mine, $channel),
		'agent naming me'               => array($m('app', $agent, 0, array('mentions_me' => true)), null, $channel),
		'agent to theirs naming me'     => array($m('app', $agent, 11, array('mentions_me' => true)), $theirs, $channel),
		'agent to theirs'               => array($m('app', $agent, 11), $theirs, $channel),
		'agent, plain line'             => array($m('app', $agent, 0), null, $channel),
		'agent of another channel'      => array($m('app', $other_agent, 10, array('mentions_me' => true)), $mine, $channel),
		'agent, channel lists none'     => array($m('app', $agent, 10), $mine, array('id' => 25)),
		'app that is no agent'          => array($m('app', $app, 10, array('mentions_me' => true)), $mine, $channel),
		'person to my message'          => array($m('user', array('id' => 7)), $mine, $channel),
		'guest to my message'           => array($m('guest', array('id' => 0), 10), $mine, $channel),
		'deleted agent answer'          => array($m('app', $agent, 10, array('deleted' => true)), $mine, $channel),
		'parent not the one answered'   => array($m('app', $agent, 99), $mine, $channel),
	);
	$expected = array(true, false, false, true, true, true, false, false, false, false, false, false, false, false, false);

	$result = ws_voice_channel_agents_test_run(array_values($cases));

	if ($result === false) {
		return;
	}

	pg_assert_true(is_array($result), 'speaksAnswer() is in workspace.js and runs');

	foreach (array_keys($cases) as $index => $label) {
		pg_assert_same($expected[$index], $result[$index], $label);
	}
}

// The channel's agents carry the application they write as.
function test_ws_voice_channel_agents_member_app_id()
{
	$row = array('agent' => array('id' => 4, 'app_id' => 9, 'handle' => 'prmerge', 'name' => 'PR Merge', 'kind' => 'app', 'door_kind' => 'poll', 'seen_at' => 0), 'wake_rule' => 'mention');

	pg_assert_same(9, ws_channel_member_agent($row, time())['app_id']);
}

// The switch is offered where the channel has agents, not only where an
// assistant is set up, in the channel menu and in Channel info.
function test_ws_voice_channel_agents_switch_offered()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_same(2, substr_count($screen, "window.PGWsVoice.speakSupported() && (BOOT.claude || BOOT.ai || (BOOT.mcp || []).length || (channel.agents || []).length)"));
	pg_assert_contains('!speaksAnswer(message, parent, this.channel)', $screen);
}
