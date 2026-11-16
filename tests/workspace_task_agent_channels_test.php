<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent channel filter over the task lists
 * (includes/workspace/tasks.php, ws_tasks_agent_rule()): My Tasks, the
 * Planning Board, the Work Calendar and GET /workspace/tasks leave an agent
 * channel's tasks out unless asked for or named; a task assigned to the
 * reader stays in "Mine".
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

// Two agent channels (5, 7) and a discussion (11) under 5, as
// ws_tasks_agent_channels() gives them.
function workspace_task_agent_channels_fixture()
{
	return array(
		5  => array('id' => 5, 'name' => 'dev', 'agent_channel' => 1, 'parent_id' => 0),
		7  => array('id' => 7, 'name' => 'tests', 'agent_channel' => 1, 'parent_id' => 0),
		11 => array('id' => 11, 'name' => 'dev', 'agent_channel' => 1, 'parent_id' => 5),
	);
}

// Which of the tasks' channels a rule keeps: 0 (no channel), 3 (a team
// channel), the agent channels and the discussion.
function workspace_task_agent_kept($rule)
{
	return array_values(array_filter(array(0, 3, 5, 7, 11), function ($id) use ($rule) {
		return ws_tasks_agent_keeps($rule, $id);
	}));
}

function test_workspace_task_agents_left_out_by_default()
{
	$rule = ws_tasks_agent_rule(workspace_task_agent_channels_fixture(), '');

	pg_assert_same(array(0, 3), workspace_task_agent_kept($rule), 'agent channels and their discussions are left out');
	pg_assert_same('t.channel_id NOT IN (5,7,11)', ws_tasks_agent_sql($rule), 'as SQL');
}

function test_workspace_task_agents_include_and_only()
{
	$agents = workspace_task_agent_channels_fixture();

	pg_assert_same(null, ws_tasks_agent_rule($agents, 'include'), 'include filters nothing');
	pg_assert_same(array(0, 3, 5, 7, 11), workspace_task_agent_kept(ws_tasks_agent_rule($agents, 'include')), 'include keeps all');

	$only = ws_tasks_agent_rule($agents, 'only');
	pg_assert_same(array(5, 7, 11), workspace_task_agent_kept($only), 'only keeps the agent channels alone');
	pg_assert_same('channel_id IN (5,7,11)', ws_tasks_agent_sql($only, 'channel_id'), 'only as SQL');
}

function test_workspace_task_agents_named_channel_is_kept()
{
	$agents = workspace_task_agent_channels_fixture();

	pg_assert_same(array(0, 3, 5), workspace_task_agent_kept(ws_tasks_agent_rule($agents, '', 5)), 'a named agent channel is kept');
	pg_assert_same(array(3, 5, 7, 11), workspace_task_agent_kept(ws_tasks_agent_rule($agents, 'only', 3)), 'a named team channel is kept under only');
}

// W3: the reader's own tasks keep a task assigned to them whatever its
// channel; the toggle still narrows them to the agent channels.
function test_workspace_task_agents_mine_keeps_own_tasks()
{
	$agents = workspace_task_agent_channels_fixture();

	pg_assert_same(null, ws_tasks_agent_rule($agents, '', 0, true), 'mine filters nothing');
	pg_assert_same('', ws_tasks_agent_sql(ws_tasks_agent_rule($agents, '', 0, true)), 'no SQL for mine');
	pg_assert_same(array(5, 7, 11), workspace_task_agent_kept(ws_tasks_agent_rule($agents, 'only', 0, true)), 'mine, only: the agent channels alone');
}

// Before the upgrade there is no agent channel, so nothing is left out and
// "only" shows nothing.
function test_workspace_task_agents_none_known()
{
	pg_assert_same(null, ws_tasks_agent_rule(array(), ''), 'nothing to leave out');
	pg_assert_same('', ws_tasks_agent_sql(null), 'no SQL');
	pg_assert_same('0 = 1', ws_tasks_agent_sql(ws_tasks_agent_rule(array(), 'only')), 'only with none shows none');
}

// GET /workspace/tasks names the parameter, as GET /workspace/decisions.
function test_workspace_task_agents_api_parameter()
{
	$found = null;

	foreach (ws_api_routes() as $route) {
		if (($route['id'] ?? '') === 'workspace.tasks.list') {
			foreach ($route['params'] as $param) {
				if ($param['name'] === 'agent_channels') {
					$found = $param;
				}
			}
		}
	}

	pg_assert_true(is_array($found), 'agent_channels is a parameter');
	pg_assert_same(array('include', 'only'), $found['values'], 'its values');
}

// W5: the dashboard's figures over other people's tasks leave agent
// channels' tasks out by the same rule: the Team card's open and late counts
// and what each one works on, and the Pipeline card's tasks opened by the
// reader (theirs to do still count).
function test_workspace_task_agents_dashboard_figures_use_the_rule()
{
	$team = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/team.php');
	$pipeline = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/pipeline.php');

	pg_assert_same(1, substr_count($team, 'ws_tasks_agent_out_sql()'), 'the Team card reads the rule once');
	pg_assert_same(3, substr_count($team, '$agent_and'), 'the Team card sets it and applies it to both its task queries');
	pg_assert_true(strpos($team, "ws_board(\$rights, date('Y-m-d', \$now), 1, 0, false, 0, '')") !== false, 'the Team card\'s day load is the Planning Board\'s');
	pg_assert_true(strpos($pipeline, "ws_tasks_agent_out_sql('ws_tasks.channel_id')") !== false, 'the Pipeline card leaves out what the reader opened in an agent channel');
}
