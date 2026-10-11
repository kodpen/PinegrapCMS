<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the workspace's channel templates
 * (includes/workspace/templates.php): the check of a template's tasks and
 * notes (ws_template_body_clean()), the dates counted from the day a template
 * is applied, the checklist taken out of a task's description and put back,
 * and the built-in templates passing their own check.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only; the palette (groups.php) is what the built-in
// templates are normalised with.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/groups.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');

// A body as the template screen sends it is kept field by field, unknown
// keys dropped and numbers held in their ranges.
function test_workspace_templates_body_clean_keeps_a_valid_body()
{
	$result = ws_template_body_clean(json_encode(array(
		'tasks' => array(
			array('title' => '  Kickoff   meeting ', 'priority' => 'high', 'due_in_days' => '2', 'start_in_days' => 0, 'estimate_minutes' => 60,
				'assign' => 'creator', 'checklist' => array('Agenda', '- [ ] Notes', ''), 'depends_on' => array(), 'colour' => 'red'),
			array('title' => 'Offer', 'priority' => 'whenever', 'due_in_days' => 9999, 'estimate_minutes' => -5,
				'assign' => 'user:12', 'depends_on' => array(0, 0, 1, 5, -1)),
		),
		'notes' => array(
			array('title' => 'Meeting notes', 'body' => "::: Agenda\r\n| Topic |\n| --- |"),
			array('title' => '', 'body' => '   '),
		),
	)));

	pg_assert_true($result['ok']);
	pg_assert_same(2, count($result['body']['tasks']));

	$first = $result['body']['tasks'][0];
	pg_assert_same('Kickoff meeting', $first['title'], 'spaces collapsed');
	pg_assert_same(2, $first['due_in_days'], 'numeric string');
	pg_assert_same(0, $first['start_in_days']);
	pg_assert_same(array('Agenda', 'Notes'), $first['checklist'], 'empty item dropped, checklist mark taken off');
	pg_assert_false(array_key_exists('colour', $first), 'unknown key dropped');

	$second = $result['body']['tasks'][1];
	pg_assert_same('normal', $second['priority'], 'unknown priority');
	pg_assert_same(3650, $second['due_in_days'], 'days capped');
	pg_assert_same(null, $second['start_in_days'], 'no start');
	pg_assert_same(0, $second['estimate_minutes'], 'negative estimate');
	pg_assert_same('user:12', $second['assign']);
	pg_assert_same(array(0), $second['depends_on'], 'only earlier tasks, once each');

	pg_assert_same(1, count($result['body']['notes']), 'empty note dropped');
	pg_assert_same("::: Agenda\n| Topic |\n| --- |", $result['body']['notes'][0]['body'], 'line ends made LF');
}

// What cannot be kept is refused with a reason, not fixed silently.
function test_workspace_templates_body_clean_refuses()
{
	pg_assert_false(ws_template_body_clean('{not json')['ok'], 'broken JSON');
	pg_assert_false(ws_template_body_clean(array('tasks' => array(array('title' => '   '))))['ok'], 'task without a title');
	pg_assert_false(ws_template_body_clean(array('tasks' => array(array('title' => 'A', 'start_in_days' => 5, 'due_in_days' => 2))))['ok'], 'starts after it is due');
	pg_assert_false(ws_template_body_clean(array('tasks' => array_fill(0, WS_TEMPLATE_TASKS_MAX + 1, array('title' => 'A'))))['ok'], 'too many tasks');
	pg_assert_false(ws_template_body_clean(array('notes' => array_fill(0, WS_TEMPLATE_NOTES_MAX + 1, array('title' => 'A'))))['ok'], 'too many notes');

	$long = array('notes' => array_fill(0, WS_TEMPLATE_NOTES_MAX, array('title' => 'A', 'body' => str_repeat('ş', 8000))));
	pg_assert_false(ws_template_body_clean($long)['ok'], 'larger than the column');

	$empty = ws_template_body_clean('');
	pg_assert_true($empty['ok'], 'an empty body is an empty template');
	pg_assert_same(array('tasks' => array(), 'notes' => array()), $empty['body']);
}

// The assignment rules a task may carry; anything else becomes none.
function test_workspace_templates_assign_rules()
{
	foreach (array('creator', 'department_lead', 'none', 'department:3', 'user:42') as $rule) {
		pg_assert_true(ws_template_assign_valid($rule), $rule);
	}

	foreach (array('', 'admin', 'user:0', 'user:abc', 'department:', 'user:1;DROP', 'creator ') as $rule) {
		pg_assert_false(ws_template_assign_valid($rule), var_export($rule, true));
	}

	$result = ws_template_body_clean(array('tasks' => array(array('title' => 'A', 'assign' => 'boss'))));
	pg_assert_same('none', $result['body']['tasks'][0]['assign']);
}

// Dates are calendar days from the day the template is applied, across a
// month end, a leap day and the change of the clocks alike.
function test_workspace_templates_relative_dates()
{
	pg_assert_same('2026-10-09', ws_template_relative_date('2026-10-09', 0));
	pg_assert_same('2026-10-11', ws_template_relative_date('2026-10-09', 2), 'a weekend counts');
	pg_assert_same('2026-11-02', ws_template_relative_date('2026-10-09', 24), 'month end');
	pg_assert_same('2028-03-01', ws_template_relative_date('2028-02-28', 2), 'leap day');
	pg_assert_same('2026-10-26', ws_template_relative_date('2026-10-24', 2), 'clocks go back on 25 October in Europe');
	pg_assert_same(null, ws_template_relative_date('2026-10-09', null), 'no days, no date');

	pg_assert_same(7, ws_template_days_until('2026-10-09', '2026-10-16'));
	pg_assert_same(0, ws_template_days_until('2026-10-09', '2026-10-01'), 'a day past is 0');
	pg_assert_same(2, ws_template_days_until('2026-10-24', '2026-10-26'), 'across the change of the clocks');
	pg_assert_same(null, ws_template_days_until('2026-10-09', ''));
	pg_assert_same(null, ws_template_days_until('2026-10-09', '0000-00-00'));
}

// A task's checklist comes out of its description for a template and goes
// back in as "- [ ]" lines; a fenced block is left alone.
function test_workspace_templates_checklist_round_trip()
{
	$description = "Call them first.\n\n- [ ] Agenda\n- [x] Room\n```\n- [ ] not an item\n```";
	$split = ws_template_split_checklist($description);

	pg_assert_same(array('Agenda', 'Room'), $split['items']);
	pg_assert_same("Call them first.\n\n```\n- [ ] not an item\n```", $split['text']);

	pg_assert_same("Call them first.\n\n- [ ] Agenda\n- [ ] Room", ws_template_task_description('Call them first.', array('Agenda', 'Room')));
	pg_assert_same("- [ ] Agenda", ws_template_task_description('', array('Agenda')));
	pg_assert_same('Only text', ws_template_task_description('Only text', array()));
}

// The built-in templates pass the check every saved template passes, and
// their order of tasks points only backwards.
function test_workspace_templates_builtin_are_clean()
{
	$builtin = ws_templates_builtin();

	pg_assert_same(array('builtin:new_customer', 'builtin:website_delivery', 'builtin:month_end'), array_keys($builtin));

	foreach ($builtin as $id => $template) {
		pg_assert_true($template['builtin'], $id);
		pg_assert_true(trim($template['name']) !== '', $id . ' name');
		pg_assert_true(count($template['body']['tasks']) > 0, $id . ' tasks');

		$check = ws_template_body_clean(json_encode($template['body']));
		pg_assert_true($check['ok'], $id . ' passes the check');
		pg_assert_same($template['body'], $check['body'], $id . ' is already clean');
	}

	pg_assert_same(5, count($builtin['builtin:new_customer']['body']['tasks']));
	pg_assert_same(array(0, 1), $builtin['builtin:month_end']['body']['tasks'][2]['depends_on']);
}
