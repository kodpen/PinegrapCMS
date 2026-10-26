<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a task's due time and e-mail reminder in the Workspace API:
 * the create and update routes take due_time and remind_minutes as the task
 * drawer does, a null clears them, and a task answers with both.
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

// The parameters of a route, by name.
function ws_api_task_reminder_test_params($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			$params = array();

			foreach ($route['params'] as $param) {
				$params[$param['name']] = $param;
			}

			return $params;
		}
	}

	return array();
}

// Both writes take the two fields, and null clears either.
function test_ws_api_task_routes_take_due_time_and_reminder()
{
	foreach (array('workspace.tasks.create', 'workspace.tasks.update') as $id) {
		$params = ws_api_task_reminder_test_params($id);

		pg_assert_true(isset($params['due_time']), $id . ': due_time');
		pg_assert_true(isset($params['remind_minutes']), $id . ': remind_minutes');
		pg_assert_true(!empty($params['due_time']['nullable']), $id . ': due_time clears with null');
		pg_assert_true(!empty($params['remind_minutes']['nullable']), $id . ': remind_minutes clears with null');
		pg_assert_same('int', $params['remind_minutes']['type'], $id . ': remind_minutes is a number');
	}
}

// The route's limit is the drawer's: a value the route lets through is
// one the reminder takes.
function test_ws_api_task_reminder_limit_matches_the_drawer()
{
	$params = ws_api_task_reminder_test_params('workspace.tasks.update');

	pg_assert_same(WS_TASK_REMIND_MAX, $params['remind_minutes']['max'], 'max');
	pg_assert_same(0, $params['remind_minutes']['min'], 'min');
	pg_assert_true(strpos($params['due_time']['description'], WS_TASK_REMIND_DAY_TIME) !== false, 'the day time named in the description');
}

// The body reaches ws_task_validate() with both fields, an empty value
// (the router's clear) included.
function test_ws_api_task_input_keeps_due_time_and_reminder()
{
	$data = ws_api_task_input(array('title' => 'Call back', 'due_date' => '2026-11-02', 'due_time' => '14:30', 'remind_minutes' => 60));

	pg_assert_same('14:30', $data['due_time'], 'due_time');
	pg_assert_same(60, $data['remind_minutes'], 'remind_minutes');

	$cleared = ws_api_task_input(array('due_time' => '', 'remind_minutes' => ''));

	pg_assert_same('', $cleared['due_time'], 'due_time cleared');
	pg_assert_same('', $cleared['remind_minutes'], 'remind_minutes cleared');

	pg_assert_false(array_key_exists('due_time', ws_api_task_input(array('title' => 'x'))), 'left out, left alone');
}

// What the drawer's checks make of the cleared values: no time, no reminder.
function test_ws_api_task_cleared_values_mean_none()
{
	pg_assert_same(null, ws_task_time_or_null(''), 'no time');
	pg_assert_same(null, ws_task_remind_minutes_or_null(''), 'no reminder');
	pg_assert_same('14:30:00', ws_task_time_or_null('14:30'), 'a time');
	pg_assert_same(0, ws_task_remind_minutes_or_null(0), 'at the due time');
}

// A task answers with both.
function test_ws_api_task_schema_has_due_time_and_reminder()
{
	$schema = ws_api_task_schema();

	pg_assert_same('string?', $schema['due_time'], 'due_time');
	pg_assert_same('integer?', $schema['remind_minutes'], 'remind_minutes');
}
