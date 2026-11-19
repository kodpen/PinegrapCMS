<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agents a discussion's people panel lists
 * (ws_channel_member_agent_rows(), includes/workspace/channel_agents_panel.php):
 * its own rows, the channel's agents asked there by name, the agent it
 * follows, and that the member list, the agent card and the API read the
 * same rows.
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

function ws_discussion_agents_test_row($id, $rule = 'all', $tier = 3, $kind = 'app', $status = 'active', $allowed = true)
{
	return array(
		'agent'       => array('id' => $id, 'kind' => $kind, 'handle' => 'agent' . $id, 'name' => 'Agent ' . $id, 'status' => $status, 'app_id' => 10 + $id),
		'allowed'     => $allowed,
		'wake_rule'   => $rule,
		'wake_prefix' => ($rule === 'prefix') ? 'go' : '',
		'tier'        => $tier,
		'loop_guard'  => false,
		'added_by'    => 1,
		'added_at'    => 1700000000,
	);
}

function ws_discussion_agents_test_ids($rows)
{
	return array_map(function ($row) {
		return (int) $row['agent']['id'];
	}, $rows);
}

function test_workspace_discussion_agents_channel_unchanged()
{
	$own = array(ws_discussion_agents_test_row(1), ws_discussion_agents_test_row(2, 'mention', 1, 'app', 'paused'));
	$rows = ws_channel_member_agent_rows($own, null, array());

	pg_assert_same(array(1, 2), ws_discussion_agents_test_ids($rows), 'a channel lists its own rows, a paused agent too');
	pg_assert_same('all', $rows[0]['wake_rule'], 'with their own rule');
	pg_assert_same(0, $rows[0]['reach_from'], 'and no channel they come from');
	pg_assert_true(!$rows[0]['following'], 'nothing followed in a channel');
}

function test_workspace_discussion_agents_from_the_channel()
{
	$parent = array('id' => 4, 'kind' => 'public', 'archived_at' => 0);
	$channel_rows = array(
		ws_discussion_agents_test_row(1, 'all', 3),
		ws_discussion_agents_test_row(2, 'prefix', 2),
		ws_discussion_agents_test_row(3, 'all', 1, 'app', 'paused'),
		ws_discussion_agents_test_row(5, 'all', 1, 'app', 'active', false),
		ws_discussion_agents_test_row(6, 'replies', 2, 'model'),
	);
	$own = array(ws_discussion_agents_test_row(2, 'replies', 2));
	$rows = ws_channel_member_agent_rows($own, $parent, $channel_rows, '');

	pg_assert_same(array(2, 1, 6), ws_discussion_agents_test_ids($rows), 'its own rows first, then the channel\'s active and allowed agents with no row of its own');
	pg_assert_same('replies', $rows[0]['wake_rule'], 'an own row keeps its rule');
	pg_assert_same(0, $rows[0]['reach_from'], 'an own row comes from nowhere');
	pg_assert_same(2, (int) $rows[0]['tier'], 'and keeps its tier');

	pg_assert_same(4, $rows[1]['reach_from'], 'a channel\'s row says where it comes from');
	pg_assert_same('mention', $rows[1]['wake_rule'], 'and answers when named');
	pg_assert_same('', $rows[1]['wake_prefix'], 'with no prefix');
	pg_assert_same(1, (int) $rows[1]['tier'], 'tier 1 in the discussion');
	pg_assert_true(!empty($rows[1]['loop_guard']), 'the loop guard on');

	pg_assert_same($own, array_map(function ($row) {
		unset($row['reach_from'], $row['following']);
		return $row;
	}, ws_channel_member_agent_rows($own, array('id' => 4, 'archived_at' => 1700000000), $channel_rows, '')), 'an archived channel gives none');
	pg_assert_same(array(2), ws_discussion_agents_test_ids(ws_channel_member_agent_rows($own, null, $channel_rows, '')), 'a missing channel gives none');
}

function test_workspace_discussion_agents_following()
{
	$parent = array('id' => 4, 'kind' => 'public', 'archived_at' => 0);
	$channel_rows = array(ws_discussion_agents_test_row(1), ws_discussion_agents_test_row(6, 'mention', 1, 'model'));
	$own = array(ws_discussion_agents_test_row(3, 'mention', 1, 'app', 'paused'));

	$rows = ws_channel_member_agent_rows($own, $parent, $channel_rows, 'model:6');
	pg_assert_same(array(false, false, true), array_column($rows, 'following'), 'the model agent it follows is marked');

	$rows = ws_channel_member_agent_rows($own, $parent, $channel_rows, 'agent:1');
	pg_assert_same(array(false, true, false), array_column($rows, 'following'), 'a channel\'s agent it follows is marked');

	$rows = ws_channel_member_agent_rows($own, $parent, $channel_rows, 'agent:3');
	pg_assert_same(array(false, false, false), array_column($rows, 'following'), 'a paused agent reaches nothing and is not marked');

	$rows = ws_channel_member_agent_rows($own, $parent, $channel_rows, 'claude');
	pg_assert_same(array(false, false, false), array_column($rows, 'following'), 'Claude is not a registry row');
}

function test_workspace_discussion_agents_one_list()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channel_agents_panel.php');
	$start = strpos($source, 'function ws_channel_member_agents(');
	pg_assert_true($start !== false, 'the member list is there');
	pg_assert_contains('ws_channel_member_agent_list(', substr($source, $start, 600), 'the member list (Channel info, the people panel, the API\'s agent_count) reads the shared rows');

	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/agent_card.php');
	$start = strpos($card, 'function ws_agent_card(');
	pg_assert_contains('ws_channel_member_agent_list(', substr($card, $start, 600), 'the agent card reads the same rows');
}
