<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's agents among its members: an agent's row in Channel
 * info (ws_channel_member_agent()), the short last-seen words, a person's
 * last seen sent only to a manager, and the head's faces and the info
 * panel's two lists in assets/js/workspace.js.
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

// A channel-agent row as ws_channel_agent_shape() gives it.
function workspace_channel_members_test_row($agent, $wake_rule = 'mention', $wake_prefix = '')
{
	return array(
		'agent'       => $agent + array('id' => 7, 'handle' => 'mimar', 'name' => 'Mimar', 'status' => 'active', 'door_kind' => 'poll', 'seen_at' => 0, 'door_last_knock' => 0, 'door_error' => ''),
		'wake_rule'   => $wake_rule,
		'wake_prefix' => $wake_prefix,
	);
}

function test_workspace_seen_ago_words()
{
	$now = 1700000000;

	pg_assert_same(lang('Not seen yet'), ws_seen_ago_words(0, $now));
	pg_assert_same(lang('a moment ago'), ws_seen_ago_words($now - 40, $now));
	pg_assert_same(lang(array('string' => '{var:1} min ago', 'vars' => 12)), ws_seen_ago_words($now - 12 * 60 - 5, $now));
	pg_assert_same(lang(array('string' => '{var:1} h ago', 'vars' => 3)), ws_seen_ago_words($now - 3 * 3600, $now));
	pg_assert_same(date('d.m.Y H:i', $now - 2 * 86400), ws_seen_ago_words($now - 2 * 86400, $now), 'older: the date');
}

function test_workspace_person_seen_words()
{
	$now = time();

	pg_assert_same(lang('Online'), ws_person_seen_words($now - 30, $now), 'a fresh heartbeat reads online');
	pg_assert_same(lang(array('string' => '{var:1} min ago', 'vars' => 12)), ws_person_seen_words($now - 12 * 60, $now));
	pg_assert_same(lang('Not seen yet'), ws_person_seen_words(0, $now));
	pg_assert_same(array(), ws_channel_member_seen(false, array(1, 2), $now), 'only a manager is sent the time');
	pg_assert_same(array(), ws_channel_member_seen(true, array(), $now));
}

function test_workspace_channel_member_agent_poll()
{
	$now = 1700000000;
	$fresh = ws_channel_member_agent(workspace_channel_members_test_row(array('kind' => 'app', 'seen_at' => $now - 40)), $now);

	pg_assert_same(7, $fresh['id']);
	pg_assert_same('mimar', $fresh['handle']);
	pg_assert_same(false, $fresh['assistant']);
	pg_assert_same(lang('a moment ago'), $fresh['seen']);
	pg_assert_same('on', $fresh['seen_state'], 'seen lately: the teal dot');
	pg_assert_same(lang('wakes when mentioned'), $fresh['wake']);

	$late = ws_channel_member_agent(workspace_channel_members_test_row(array('kind' => 'app', 'seen_at' => $now - 600)), $now);
	pg_assert_same('late', $late['seen_state'], 'ten minutes unseen: amber');

	$never = ws_channel_member_agent(workspace_channel_members_test_row(array('kind' => 'app')), $now);
	pg_assert_same('late', $never['seen_state'], 'never seen: amber');
	pg_assert_same(lang('Not seen yet'), $never['seen']);
}

function test_workspace_channel_member_agent_push_and_model()
{
	$now = 1700000000;
	$push = ws_channel_member_agent(workspace_channel_members_test_row(array('kind' => 'app', 'door_kind' => 'push', 'door_last_knock' => $now - 120), 'all'), $now);

	pg_assert_same(lang(array('string' => 'Last knock {var:1}', 'vars' => lang(array('string' => '{var:1} min ago', 'vars' => 2)))), $push['seen'], 'a push agent: its last knock');
	pg_assert_same('off', $push['seen_state']);
	pg_assert_same(lang('wakes on every message'), $push['wake']);

	$failing = ws_channel_member_agent(workspace_channel_members_test_row(array('kind' => 'app', 'door_kind' => 'push', 'door_error' => 'HTTP 500')), $now);
	pg_assert_same(lang('Not knocked yet'), $failing['seen']);
	pg_assert_same('late', $failing['seen_state'], 'a door that fails: amber');

	$model = ws_channel_member_agent(workspace_channel_members_test_row(array('kind' => 'model', 'status' => 'paused'), 'prefix', '[ci]'), $now);
	pg_assert_same(true, $model['assistant'], 'a model agent is an assistant');
	pg_assert_same(false, $model['active']);
	pg_assert_same(lang('Always ready'), $model['seen']);
	pg_assert_same('ready', $model['seen_state']);
	pg_assert_same(lang(array('string' => 'wakes on {var:1}', 'vars' => '[ci]')), $model['wake']);
}

function test_workspace_channel_detail_sends_agents_and_seen()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channels.php');

	pg_assert_contains("\$brief['agents'] = ws_channel_member_agents(\$channel, time());", $source, 'every reader gets the agents');
	pg_assert_contains("ws_channel_member_seen(\$brief['can_manage'], \$member_ids, time())", $source, 'the people\'s time only for a manager');
}

function test_workspace_channel_members_js()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains('function memberFaces(node, channel)', $source);
	pg_assert_contains("node.appendChild(el('span', 'small', channel.members.length + agents.length));", $source, 'one count for people and agents');
	pg_assert_true(substr_count($source, 'memberFaces(') >= 3, 'the head draws and redraws the faces');
	pg_assert_contains("'avatar avatar-subtle ws-agent-face '", $source, 'an agent\'s face is its own shape');
	pg_assert_contains("channelForm(channel, { agents: true }", $source, 'Manage opens Agents in this channel');
	pg_assert_contains("mount(body, channel, { reveal: !!defaults.agents })", $source);

	$texts = ws_channel_agents_js_strings();

	foreach (array('cm_people', 'cm_agents', 'cm_agent', 'cm_assistant', 'cm_manage', 'cm_manage_label', 'cm_ready') as $key) {
		pg_assert_true(isset($texts[$key]), $key . ' is given');
	}
}
