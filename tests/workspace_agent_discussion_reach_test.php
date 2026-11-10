<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agents a discussion reaches through the channel it is about
 * (ws_agent_reach_rows(), includes/workspace/agent_queue.php): the merge of
 * the discussion's own rows with the channel's, that only an ask by name
 * wakes an agent there, and that the wake, the answer, the retry, the
 * picker, the unread line and the model assistant's door read the same rows.
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

/**
 * The source of one function of a Workspace file, up to the next one.
 */
function ws_reach_test_source($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 10);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function ws_reach_test_row($id, $rule = 'mention', $tier = 1)
{
	return array('id' => $id, 'kind' => 'app', 'handle' => 'agent' . $id, 'app_id' => 10 + $id, 'wake_rule' => $rule, 'wake_prefix' => ($rule === 'prefix') ? 'go' : '', 'tier' => $tier, 'loop_guard' => 0);
}

function test_workspace_agent_reach_merge()
{
	$parent = array('id' => 4, 'kind' => 'public', 'archived_at' => 0);
	$channel_rows = array(ws_reach_test_row(1, 'all', 3), ws_reach_test_row(2, 'prefix', 2), ws_reach_test_row(3, 'replies'));
	$own = array(ws_reach_test_row(2, 'replies', 2));

	// Agent 2 has a row of the discussion's own, agent 3 one that is not
	// allowed: neither takes the channel's.
	$rows = ws_agent_reach_merge($own, array(2, 3), $parent, $channel_rows);
	pg_assert_same(array(1, 2), array_map('intval', array_column($rows, 'id')), 'the own rows decide alone, an own row not allowed too');
	pg_assert_same('replies', $rows[1]['wake_rule'], 'an own row is kept as it is');
	pg_assert_same(2, (int) $rows[1]['tier'], 'with its own tier');

	pg_assert_same('mention', $rows[0]['wake_rule'], 'the channel\'s rule does not pass');
	pg_assert_same('', $rows[0]['wake_prefix'], 'nor its prefix');
	pg_assert_same(1, (int) $rows[0]['tier'], 'tier 1 in the discussion');
	pg_assert_same(1, (int) $rows[0]['loop_guard'], 'the loop guard on');
	pg_assert_same(4, (int) $rows[0]['reach_from'], 'where the row came from');

	pg_assert_same($own, ws_agent_reach_merge($own, array(2), null, $channel_rows), 'no channel: nothing from it');
	pg_assert_same($own, ws_agent_reach_merge($own, array(2), array('id' => 4, 'archived_at' => 1700000000), $channel_rows), 'an archived channel: nothing from it');
	pg_assert_same($channel_rows, ws_agent_reach_merge($channel_rows, array(1, 2, 3), null, array()), 'a channel\'s list is unchanged');
}

function test_workspace_agent_reach_wakes_by_name_only()
{
	$parent = array('id' => 4, 'kind' => 'public', 'archived_at' => 0);
	$rows = ws_agent_reach_merge(array(), array(), $parent, array(ws_reach_test_row(1, 'all', 3)));

	pg_assert_true(!ws_agent_wake_matches($rows[0], 'A message for everybody', true), 'the channel\'s "all" does not wake it in a discussion, nor an answer to it');
	pg_assert_true(ws_agent_wake_matches($rows[0], 'Ask @agent1 please', false), 'its handle wakes it');
	pg_assert_true(ws_agent_wake_matches($rows[0], '<@app:11> look', false), 'its tag wakes it');
}

function test_workspace_agent_reach_used_everywhere()
{
	$uses = array(
		array('agent_queue.php', 'ws_agent_after_send'),
		array('api_agent_requests.php', 'ws_api_agent_request_place'),
		array('agent_activity.php', 'ws_agent_activity_retry'),
		array('app_handles.php', 'ws_channel_app_picker'),
		array('messages.php', 'ws_message_tell_apps'),
	);

	foreach ($uses as $use) {
		pg_assert_contains('ws_agent_reach_rows(', ws_reach_test_source($use[0], $use[1]), $use[1] . ' reads the rows that reach the channel');
	}

	$model = ws_reach_test_source('model_agents.php', 'ws_model_channel_allowed');
	pg_assert_contains('ws_agent_reach_row(', $model, 'the model assistant\'s door takes the channel\'s row in a discussion');

	$tier = ws_reach_test_source('agent_tiers.php', 'ws_channel_agent_tier_of');
	pg_assert_true($tier !== '', 'the tier function is there');
	pg_assert_true(strpos($tier, 'ws_agent_reach') === false && strpos($tier, 'ws_thread_parent_channel') === false, 'the tier never reads the channel a discussion is about');
}
