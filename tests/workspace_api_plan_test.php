<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the planning board over the Workspace API (GET /workspace/plan):
 * a cell carries the board's task entries with their flags (the due day, a
 * projected copy of a repeating task, a moved copy, a copy waiting for an
 * overdue one) and the plan items the grid placed on the day, and a row
 * carries the count of the person's undated tasks. The board leaves agent
 * channels' tasks out unless asked, and a single task (GET
 * /workspace/tasks/{id}) carries its links in the drawer's shape.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// A task entry as ws_plan_grid() writes it into a cell.
function ws_api_plan_test_task($overrides = array())
{
	return $overrides + array(
		'id' => 42, 'title' => 'Layout', 'minutes' => 120, 'status' => 'todo', 'priority' => 'normal',
		'due' => false, 'new' => false, 'estimated' => true, 'due_date' => '2026-10-27', 'progress' => null,
		'upcoming' => false, 'repeat' => '', 'moved_from' => '', 'waits_for' => '', 'series_latest' => false,
	);
}

// A ws_board() row: person 7, one Tuesday with a due task, a projected
// weekly copy that was moved from Monday and waits for the overdue copy, and
// a meeting; three undated tasks.
function ws_api_plan_test_row()
{
	return array(
		'person'      => array('id' => 7, 'name' => 'Ayşe'),
		'capacity'    => array('day_minutes' => 480),
		'unscheduled' => 3,
		'departments' => array(),
		'time'        => null,
		'cells'       => array(array(
			'date' => '2026-10-27', 'workday' => true, 'holiday' => false, 'leave' => false,
			'capacity' => 480, 'minutes' => 240, 'load' => 50,
			'tasks' => array(
				ws_api_plan_test_task(array('due' => true)),
				ws_api_plan_test_task(array('id' => 57, 'minutes' => 60, 'upcoming' => true, 'repeat' => 'Every week',
					'moved_from' => '2026-10-26', 'waits_for' => '#57')),
			),
			'events' => array(
				array('id' => 19, 'kind' => 'meeting', 'title' => 'Weekly review', 'time' => '10:00', 'minutes' => 60, 'meeting_url' => ''),
			),
		)),
	);
}

function test_ws_api_plan_cell_carries_the_board_entries()
{
	$row = ws_api_plan_present(ws_api_plan_test_row());
	$day = $row['days'][0];

	pg_assert_same(array(42, 57), $day['tasks'], 'tasks keeps its id list');
	pg_assert_same(array(
		array('id' => 42, 'minutes' => 120, 'due' => true, 'upcoming' => false, 'repeat' => '', 'moved_from' => '', 'waits_for' => ''),
		array('id' => 57, 'minutes' => 60, 'due' => false, 'upcoming' => true, 'repeat' => 'Every week', 'moved_from' => '2026-10-26', 'waits_for' => '#57'),
	), $day['task_items'], 'the task entries with their flags');
	pg_assert_same(array(
		array('id' => 19, 'kind' => 'meeting', 'title' => 'Weekly review', 'time' => '10:00', 'minutes' => 60, 'meeting_url' => ''),
	), $day['events'], 'the plan items of the day');
	pg_assert_same(240, $day['minutes']);
	pg_assert_same(50, $day['load']);
}

function test_ws_api_plan_row_carries_the_undated_count()
{
	$row = ws_api_plan_present(ws_api_plan_test_row());

	pg_assert_same(7, $row['user_id']);
	pg_assert_same('Ayşe', $row['name']);
	pg_assert_same(3, $row['unscheduled']);
}

// The answer is what ws_board() drew, not a second reading of the tasks.
function test_ws_api_plan_get_presents_the_board()
{
	$function = new ReflectionFunction('ws_api_plan_get');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	pg_assert_contains('ws_board(', $source);
	pg_assert_contains('ws_api_plan_present(', $source);
}

// Every key the presenter answers is declared in the schema object.
function test_ws_api_plan_schema_declares_the_new_keys()
{
	$schema = ws_api_plan_row_schema();
	$row = ws_api_plan_present(ws_api_plan_test_row());

	pg_assert_same(array_keys($schema), array_keys($row), 'row keys');
	pg_assert_same(array_keys($schema['days'][0]), array_keys($row['days'][0]), 'day keys');
	pg_assert_same(array_keys($schema['days'][0]['task_items'][0]), array_keys($row['days'][0]['task_items'][0]), 'task item keys');
	pg_assert_same(array_keys($schema['days'][0]['events'][0]), array_keys($row['days'][0]['events'][0]), 'event keys');
}

function ws_api_plan_test_route($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function ws_api_plan_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// The board leaves agent channels' tasks out as the panel's board does, takes
// include or only, and answers how many agent channels the owner may read.
function test_ws_api_plan_get_takes_the_agent_channels_mode()
{
	$route = ws_api_plan_test_route('workspace.plan.get');
	$param = null;

	foreach ($route['params'] as $candidate) {
		if ($candidate['name'] === 'agent_channels') {
			$param = $candidate;
		}
	}

	pg_assert_true($param !== null, 'the route takes agent_channels');
	pg_assert_same(array('include', 'only'), $param['values']);
	pg_assert_same('integer', $route['returns']['agent_channels'], 'the count is declared beside list');

	$source = ws_api_plan_test_source('ws_api_plan_get');

	pg_assert_contains("ws_agent_channel_mode(\$params['agent_channels'] ?? '')", $source);
	pg_assert_contains("'agent_channels' =>", $source);
}

// A single task carries its links in the drawer's shape; the list keeps the
// bare ids.
function test_ws_api_task_get_carries_the_drawer_links()
{
	pg_assert_same('WorkspaceTaskDetail', ws_api_plan_test_route('workspace.tasks.get')['returns']);
	pg_assert_same(array('list' => 'WorkspaceTask'), ws_api_plan_test_route('workspace.tasks.list')['returns']);

	$detail = ws_api_task_detail_schema();

	pg_assert_same(array_merge(array_keys(ws_api_task_schema()), array('links')), array_keys($detail));
	pg_assert_same('WorkspaceTaskLinks?', $detail['links'], 'null without the links table');

	$objects = ws_openapi_objects();

	pg_assert_same('ws_api_task_links_schema', $objects['WorkspaceTaskLinks']);
	pg_assert_same('ws_api_task_link_schema', $objects['WorkspaceTaskLink']);

	// Every key the drawer's function answers is declared, and no other.
	$source = ws_api_plan_test_source('ws_task_links_detail');
	preg_match_all("/'([a-z_]+)'\\s*=>/", $source, $found);
	$declared = array_merge(array_keys(ws_api_task_links_schema()), array_keys(ws_api_task_link_schema()));
	$answered = array_unique($found[1]);

	sort($declared);
	sort($answered);
	pg_assert_same($declared, array_values($answered));

	pg_assert_contains('ws_task_links_detail(', ws_api_plan_test_source('ws_api_tasks_get'));
}
