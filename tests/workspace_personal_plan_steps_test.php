<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the roadmap of a person's own plan
 * (includes/workspace/personal_plan_steps.php): the steps the drawer sends,
 * how a step follows its task, the progress, and that every read starts
 * from the plan's gate.
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

function test_workspace_personal_plan_steps_clean_keeps_the_order_and_drops_empty_rows()
{
	$clean = ws_personal_plan_steps_clean(array(
		array('id' => '4', 'title' => ' Draft ', 'day' => '2026-11-20', 'done' => 1),
		array('id' => 0, 'title' => '   ', 'day' => ''),
		array('title' => 'Send', 'day' => '', 'done' => '0'),
		'not a step',
	));

	pg_assert_same(2, count($clean['steps']), 'a row with no title is dropped');
	pg_assert_same(array('id' => 4, 'title' => 'Draft', 'day' => '2026-11-20', 'done' => true), $clean['steps'][0]);
	pg_assert_same(array('id' => 0, 'title' => 'Send', 'day' => '', 'done' => false), $clean['steps'][1]);

	$json = ws_personal_plan_steps_clean('[{"title":"From JSON","day":"2026-12-01"}]');
	pg_assert_same('From JSON', $json['steps'][0]['title'], 'a JSON list is read too');
}

function test_workspace_personal_plan_steps_clean_refuses()
{
	pg_assert_true(isset(ws_personal_plan_steps_clean(array(array('title' => 'x', 'day' => '2026-02-30')))['error']), 'no such day');
	pg_assert_true(isset(ws_personal_plan_steps_clean(array(array('title' => 'x', 'day' => '20.11.2026')))['error']), 'not Y-m-d');

	$many = array();

	for ($i = 0; $i <= WS_PERSONAL_PLAN_STEP_MAX; $i++) {
		$many[] = array('title' => 'Step ' . $i);
	}

	pg_assert_true(isset(ws_personal_plan_steps_clean($many)['error']), 'more steps than a roadmap keeps');
	pg_assert_same(array(), ws_personal_plan_steps_clean(null)['steps'], 'nothing sent: no steps');
}

function test_workspace_personal_plan_step_follows_its_task()
{
	$rows = array(
		array('id' => 1, 'title' => 'Own', 'day' => '0000-00-00', 'done_at' => 1700000000, 'task_id' => 0),
		array('id' => 2, 'title' => 'Tied, done', 'day' => '2026-11-20', 'done_at' => 0, 'task_id' => 30),
		array('id' => 3, 'title' => 'Tied, open', 'day' => '2026-11-21', 'done_at' => 1700000000, 'task_id' => 31),
		array('id' => 4, 'title' => 'Task deleted', 'day' => '2026-11-22', 'done_at' => 0, 'task_id' => 32),
		array('id' => 5, 'title' => 'Task hidden', 'day' => '2026-11-23', 'done_at' => 0, 'task_id' => 33),
	);
	$tasks = array(
		30 => array('status' => 'done', 'number' => 'T-30', 'visible' => true),
		31 => array('status' => 'in_progress', 'number' => 'T-31', 'visible' => true),
		33 => array('status' => 'done', 'number' => 'T-33', 'visible' => false),
	);
	$steps = ws_personal_plan_steps_state($rows, $tasks);

	pg_assert_true($steps[0]['done'], 'an untied step keeps its own mark');
	pg_assert_same('', $steps[0]['day'], 'no day');
	pg_assert_true($steps[1]['done'], 'done while its task is');
	pg_assert_true($steps[1]['follows']);
	pg_assert_same('T-30', $steps[1]['task_number']);
	pg_assert_false($steps[2]['done'], 'an open task: not done, whatever the old mark');
	pg_assert_false($steps[3]['follows'], 'a deleted task leaves the step its own mark');
	pg_assert_true($steps[4]['done'], 'a task the reader may not see still moves the step');
	pg_assert_same(0, $steps[4]['task_id'], 'but its id is not given');
	pg_assert_same('', $steps[4]['task_number'], 'nor its number');
	pg_assert_same('', $steps[4]['task_status'], 'nor its status');

	pg_assert_same(array('total' => 5, 'done' => 3, 'percent' => 60), ws_personal_plan_progress($steps));
	pg_assert_same(array('total' => 0, 'done' => 0, 'percent' => 0), ws_personal_plan_progress(array()), 'no steps');
}

// Every read of the steps starts from a plan the gate let through, and only
// the owner changes them.
function test_workspace_personal_plan_steps_are_read_through_the_gate()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/personal_plan_steps.php');

	foreach (array('ws_personal_plan_roadmap', 'ws_personal_plan_steps_save', 'ws_personal_plan_step_task') as $function) {
		$start = strpos($module, 'function ' . $function . '(');
		$body = substr($module, $start, 400);
		pg_assert_same(1, preg_match('/\$plan = ws_personal_plan\(/', $body), $function . ' reads the plan through the gate first');
	}

	pg_assert_contains('ws_personal_plan_can_see((int) $viewer[\'id\'], $plan, $team)', $module, 'the calendar progress asks the gate');
	pg_assert_same(2, substr_count($module, 'ws_personal_plan_can_edit($user_id, $plan)'), 'saving and tying are the owner\'s');
	pg_assert_contains('ws_can_see_task($viewer, $task)', $module, 'a step is tied only to a task its owner may see');
}

// The plan drawer on a phone: the hidden posted date and time inputs keep
// their 1 px (.pg-input after them in the same layer gave them full width
// and scrolled the drawer body sideways), a step's open calendar takes its
// own row, and the step's date box says what it is.
function test_workspace_personal_plan_drawer_fits_a_phone()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_my_calendar_roadmap.js');

	pg_assert_same(1, preg_match('/\.pg-field-native\.pg-field-native \{[^}]*width: 1px;/', $css), 'the hidden field beats .pg-input');
	pg_assert_true(strpos($css, '.pg-field-native.pg-field-native {') < strpos($css, "\n.pg-input {"), 'checked against the .pg-input rule it must beat');
	pg_assert_same(1, preg_match('/\.ws-roadmap-step > \.pg-date-inline \{[^}]*grid-column: 1 \/ -1;/', $css), 'the calendar takes a row of its own');
	pg_assert_same(1, preg_match('/\.ws-roadmap-tools \{[^}]*grid-row: 1;/', $css), 'the step tools stay on its first row');
	pg_assert_same(1, preg_match('/\.ws-plan-who \.pg-seg-item \{[^}]*white-space: normal;/', $css), 'Who can see breaks a long label');
	pg_assert_contains("day.placeholder = s('step_day');", $js, 'the step date box says what it is');
	pg_assert_contains("'step_day'         => lang('Day of the step')", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/personal_plan_steps.php'));
}
