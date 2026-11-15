<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel settings' agents section
 * (includes/workspace/channel_agents_panel.php) that need no database: the
 * row it draws, the agents it offers to add, and its texts.
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

function test_workspace_channel_agent_panel_row()
{
	$agent = ws_agent_shape(array('id' => '7', 'kind' => 'app', 'handle' => 'ci', 'name' => 'CI', 'status' => 'paused',
		'door_url' => 'https://hooks.example.com/x', 'door_secret_sealed' => 'a:b', 'key_sealed' => 'c:d'));
	$row = ws_channel_agent_panel_row(ws_channel_agent_shape(array('wake_rule' => 'prefix', 'wake_prefix' => '[ci]', 'tier' => '2', 'loop_guard' => '0'), $agent));

	pg_assert_same(array(
		'agent_id'    => 7,
		'kind'        => 'app',
		'handle'      => 'ci',
		'name'        => 'CI',
		'role'        => '',
		'avatar'      => '',
		'active'      => false,
		'state'       => 'paused',
		'waiting'     => 0,
		'wake_rule'   => 'prefix',
		'wake_prefix' => '[ci]',
		'wake_words'  => lang(array('string' => 'wakes on {var:1}', 'vars' => '[ci]')),
		'tier'        => 2,
		'tier_words'  => lang('makes changes that can be undone'),
		'loop_guard'  => false,
		'seen'        => '',
		'not_seen'    => false,
		'state_words' => lang('Not knocked yet'),
	), $row, 'no door, address or secret reaches the screen');
}

function test_workspace_channel_agent_panel_row_role_picture_state()
{
	$now = 1800000000;
	$agent = ws_agent_shape(array('id' => '4', 'kind' => 'app', 'handle' => 'mimar', 'name' => 'Mimar', 'status' => 'active', 'role' => 'architect',
		'door_kind' => 'poll', 'seen_at' => $now - 30, 'avatar' => 'https://example.com/m.png'));
	$row = ws_channel_agent_panel_row(ws_channel_agent_shape(array('wake_rule' => 'mention', 'tier' => '9', 'loop_guard' => '1'), $agent), $now);

	pg_assert_same(ws_agent_role_label('architect'), $row['role'], 'the role\'s label');
	pg_assert_same('https://example.com/m.png', $row['avatar'], 'its own picture');
	pg_assert_same('on', $row['state'], 'seen half a minute ago');
	pg_assert_same(lang('wakes when mentioned'), $row['wake_words']);
	pg_assert_same(lang('asks before any change'), $row['tier_words'], 'a tier out of range reads as 1');

	$late = ws_channel_agent_panel_row(ws_channel_agent_shape(array(), ws_agent_shape(array('id' => '5', 'kind' => 'app', 'status' => 'active', 'door_kind' => 'poll'))), $now);

	pg_assert_same('late', $late['state'], 'never seen');
	pg_assert_same(lang('Never seen'), $late['state_words'], 'the pill says what its time is');
	pg_assert_same(lang('Last seen a moment ago'), $row['state_words'], 'seen half a minute ago');

	$model = ws_channel_agent_panel_row(ws_channel_agent_shape(array(), ws_agent_shape(array('id' => '6', 'kind' => 'model', 'status' => 'active'))), $now);

	pg_assert_same(lang('Always ready'), $model['state_words']);

	$counts = array(ws_agent_request_value($agent) => array('queued' => 2, 'working' => 0, 'answered' => 0, 'failed' => 0));
	$waiting = ws_channel_agent_panel_row(ws_channel_agent_shape(array(), $agent), $now, $counts);

	pg_assert_same('waiting', $waiting['state'], 'requests queued for it');
	pg_assert_same(2, $waiting['waiting']);
}

function test_workspace_channel_agent_panel_state()
{
	pg_assert_same('paused', ws_channel_agent_panel_state(false, 'on'), 'paused first of all');
	pg_assert_same('late', ws_channel_agent_panel_state(true, 'late'));
	pg_assert_same('ready', ws_channel_agent_panel_state(true, 'ready'));
	pg_assert_same('off', ws_channel_agent_panel_state(true, 'odd'), 'an unknown state reads as off');
	pg_assert_same('working', ws_channel_agent_panel_state(true, 'late', 'working'), 'the card\'s working before the last seen');
	pg_assert_same('waiting', ws_channel_agent_panel_state(true, 'on', 'waiting'));
	pg_assert_same('paused', ws_channel_agent_panel_state(false, 'on', 'working'), 'paused first of all');
	pg_assert_same('on', ws_channel_agent_panel_state(true, 'on', 'idle'), 'an idle agent keeps its last seen');
}

function test_workspace_channel_agent_tier_and_wake_words()
{
	pg_assert_same(lang('asks before any change'), ws_channel_agent_tier_words(1));
	pg_assert_same(lang('makes changes that can be undone'), ws_channel_agent_tier_words('2'));
	pg_assert_same(lang('makes every change by itself'), ws_channel_agent_tier_words(3));
	pg_assert_same(lang('asks before any change'), ws_channel_agent_tier_words(0), 'out of range counts as 1');

	pg_assert_same(lang('wakes when mentioned'), ws_channel_agent_wake_words('mention'));
	pg_assert_same(lang('wakes on every message'), ws_channel_agent_wake_words('all'));
	pg_assert_same(lang(array('string' => 'wakes on {var:1}', 'vars' => '[ci]')), ws_channel_agent_wake_words('prefix', '[ci]'));
	pg_assert_same(lang('wakes on replies to it'), ws_channel_agent_wake_words('replies'));
	pg_assert_same(lang('wakes when mentioned'), ws_channel_agent_wake_words('odd'), 'an unknown rule reads as a mention');
}

function test_workspace_channel_agent_avatar_url()
{
	pg_assert_same('https://example.com/a.png', ws_channel_agent_avatar_url(' https://example.com/a.png '));
	pg_assert_same('/files/agent.png', ws_channel_agent_avatar_url('/files/agent.png'));
	pg_assert_same('', ws_channel_agent_avatar_url('http://example.com/a.png'), 'no plain http');
	pg_assert_same('', ws_channel_agent_avatar_url('//evil.example/a.png'), 'no protocol-relative address');
	pg_assert_same('', ws_channel_agent_avatar_url('javascript:alert(1)'));
	pg_assert_same('', ws_channel_agent_avatar_url('/a.png" onerror="x'));
	pg_assert_same('', ws_channel_agent_avatar_url(''));
}

function test_workspace_channel_agents_addable()
{
	$agents = array(
		ws_agent_shape(array('id' => 1, 'kind' => 'claude', 'handle' => 'claude', 'name' => 'Claude', 'status' => 'active', 'door_url' => 'https://example.com/fire')),
		ws_agent_shape(array('id' => 2, 'kind' => 'model', 'handle' => 'chatgpt', 'name' => 'ChatGPT', 'status' => 'active', 'key_sealed' => 'a:b')),
		ws_agent_shape(array('id' => 3, 'kind' => 'app', 'handle' => 'ci', 'name' => '', 'status' => 'paused')),
	);
	$out = ws_channel_agents_addable($agents, array('2'));

	pg_assert_same(array(3), array_column($out, 'agent_id'), 'the agents already in the channel and Claude, which is no channel agent, are left out');
	pg_assert_same(array('agent_id', 'kind', 'handle', 'name', 'status', 'active'), array_keys($out[0]), 'only what the list shows');
	pg_assert_same('paused', $out[0]['status']);
	pg_assert_same(false, $out[0]['active']);
	pg_assert_same(array(), ws_channel_agents_addable(array(), array()));
}

function test_workspace_channel_agents_actions_and_texts()
{
	pg_assert_same(array('ws_channel_agents_list', 'ws_channel_agents_set', 'ws_channel_agents_remove', 'ws_agent_card'), ws_channel_agents_actions());

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_channel_agents.js');
	$strings = ws_channel_agents_js_strings();

	preg_match_all("/t\\('(cha_[a-z0-9_]+)'\\)/", $source, $used);

	foreach (array_unique($used[1]) as $key) {
		pg_assert_true(isset($strings[$key]), 'the script\'s text ' . $key . ' is in ws_channel_agents_js_strings()');
	}

	foreach (ws_agent_wake_rules() as $rule) {
		pg_assert_true(isset($strings['cha_wake_' . $rule]), 'every wake rule has its label: ' . $rule);
	}
}
