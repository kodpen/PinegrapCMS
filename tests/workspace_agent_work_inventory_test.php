<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the general readers that keep agent channels apart beside the
 * task lists: the Work Calendar's Conversations layer
 * (ws_calendar_channels_kept()), the scheduled task counts
 * (ws_scheduled_metric_channel_sql(), ws_task_time_week_narrow()) and the
 * dashboard's team figure (ws_tasks_agent_count_sql()).
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calendar.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/task_time.php');

// Agent channels 5 and 7 and a discussion (11) under 5, as
// ws_tasks_agent_channels() gives them.
function workspace_agent_inventory_agents()
{
	return array(
		5  => array('id' => 5, 'name' => 'dev', 'agent_channel' => 1, 'parent_id' => 0),
		7  => array('id' => 7, 'name' => 'tests', 'agent_channel' => 1, 'parent_id' => 0),
		11 => array('id' => 11, 'name' => 'dev', 'agent_channel' => 1, 'parent_id' => 5),
	);
}

// The channels a reader reads: two team channels (3, 4) and agent channel 5.
function workspace_agent_inventory_readable()
{
	return array(
		3 => array('id' => 3, 'name' => 'general', 'kind' => 'public'),
		4 => array('id' => 4, 'name' => 'sales', 'kind' => 'private'),
		5 => array('id' => 5, 'name' => 'dev', 'kind' => 'public'),
	);
}

function test_workspace_agent_inventory_calendar_talk_leaves_agents_out()
{
	$rule = ws_tasks_agent_rule(workspace_agent_inventory_agents(), '');

	pg_assert_same(array(3, 4), array_keys(ws_calendar_channels_kept(workspace_agent_inventory_readable(), $rule)), 'the Conversations layer leaves agent channels out by default');
}

function test_workspace_agent_inventory_calendar_talk_only_and_include()
{
	$agents = workspace_agent_inventory_agents();
	$readable = workspace_agent_inventory_readable();

	pg_assert_same(array(5), array_keys(ws_calendar_channels_kept($readable, ws_tasks_agent_rule($agents, 'only'))), 'the toggle shows the agent channels alone');
	pg_assert_same(array(3, 4, 5), array_keys(ws_calendar_channels_kept($readable, ws_tasks_agent_rule($agents, 'include'))), 'include keeps all');
	pg_assert_same(array(3, 4, 5), array_keys(ws_calendar_channels_kept($readable, null)), 'no rule keeps all');
	pg_assert_same(array(3, 4, 5), array_keys(ws_calendar_channels_kept($readable, ws_tasks_agent_rule(array(), ''))), 'before the upgrade nothing is left out');
}

function test_workspace_agent_inventory_scheduled_count_condition()
{
	$agents = workspace_agent_inventory_agents();
	$task = array('scope' => 'channel');

	pg_assert_same(' AND channel_id NOT IN (5,7,11)', ws_scheduled_metric_channel_sql($task, 0, $agents), 'a site-wide task count leaves agent channels out');
	pg_assert_same(" AND channel_id = '5'", ws_scheduled_metric_channel_sql($task, 5, $agents), 'a named agent channel is counted');
	pg_assert_same(" AND channel_id = '3'", ws_scheduled_metric_channel_sql($task, 3, $agents), 'a named team channel is counted');
	pg_assert_same('', ws_scheduled_metric_channel_sql($task, 0, array()), 'nothing left out before the upgrade');
	pg_assert_same('', ws_scheduled_metric_channel_sql(array('scope' => ''), 0, $agents), 'a count that is no task count is unchanged');
}

function test_workspace_agent_inventory_week_minutes_narrow()
{
	pg_assert_same(array('join' => '', 'where' => ''), ws_task_time_week_narrow(0, ''), 'no condition: every minute');
	pg_assert_same(array('join' => " INNER JOIN ws_tasks t ON t.id = tt.task_id AND t.channel_id = '5'", 'where' => ''), ws_task_time_week_narrow(5, 't.channel_id NOT IN (5)'), 'a named channel wins');

	$out = ws_task_time_week_narrow(0, ws_tasks_agent_sql(ws_tasks_agent_rule(workspace_agent_inventory_agents(), ''), 't.channel_id'));
	pg_assert_same(' LEFT JOIN ws_tasks t ON t.id = tt.task_id', $out['join'], 'joined loosely');
	pg_assert_same(' AND (t.id IS NULL OR t.channel_id NOT IN (5,7,11))', $out['where'], 'agent channels left out, minutes of a gone task kept');
}

function test_workspace_agent_inventory_team_count_sql()
{
	pg_assert_same('COUNT(*)', ws_tasks_agent_count_sql(''), 'no agent channel: a plain count');
	pg_assert_same('COALESCE(SUM(CASE WHEN channel_id NOT IN (5,7,11) THEN 1 ELSE 0 END), 0)', ws_tasks_agent_count_sql(ws_tasks_agent_out_sql('channel_id', workspace_agent_inventory_agents())), 'the team figure leaves agent channels out');
}
