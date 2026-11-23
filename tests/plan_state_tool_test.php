<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for tools/plan_state.php: the plan's name, the steps of a PR title
 * and of a plan file, a PR's state, the state of each step, and what
 * --tasks writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/plan_state.php');

// A path, a name with .md or a bare name are the same plan; a path
// separator alone or odd characters are none.
function test_plan_state_plan_name()
{
	pg_assert_same('plan-state-channel', plan_state_plan_name('docs/memory/plans/plan-state-channel.md'));
	pg_assert_same('plan-state-channel', plan_state_plan_name('plan-state-channel.md'));
	pg_assert_same('plan-state-channel', plan_state_plan_name('plan-state-channel'));
	pg_assert_same('', plan_state_plan_name(''));
	pg_assert_same('', plan_state_plan_name('a b'));
}

// The steps between the plan tag and the dash; another plan's PR has none.
function test_plan_state_title_steps()
{
	$plan = 'plan-state-channel';
	pg_assert_same(array('PS2'), plan_state_title_steps('[plan:plan-state-channel] PS2 — plan_state.php', $plan));
	pg_assert_same(array('PS2'), plan_state_title_steps('[plan:plan-state-channel.md] PS2 - tool', $plan));
	pg_assert_same(array('PS1', 'PS2'), plan_state_title_steps('[plan:plan-state-channel] PS1, PS2 — both', $plan));
	pg_assert_same(array('PS1', 'PS2'), plan_state_title_steps('[plan:plan-state-channel] PS1 and PS2 — both', $plan));
	pg_assert_same(array('PS1', 'PS2', 'PS3'), plan_state_title_steps('[plan:plan-state-channel] PS1-PS3 — all', $plan));
	pg_assert_same(array('A4b2'), plan_state_title_steps('[plan:plan-state-channel] A4b2 — sub step', $plan));
	pg_assert_same(array('PS2'), plan_state_title_steps('[plan:plan-state-channel] PS2 — fixes PS3 text', $plan), 'the topic is not read');
	pg_assert_same(array(), plan_state_title_steps('[plan:plan-state] PS2 — other plan', $plan));
	pg_assert_same(array(), plan_state_title_steps('[iş] PS2 — no plan', $plan));
}

// Step lines of a plan file, in its order, each once.
function test_plan_state_file_steps()
{
	$text = "## What changes?\n- PS1 (S, `.github/`): one\n- **PS2** (S): two\n- PS3: three\n- PS1 (again)\n- A word line\n- AH1 census of the code\n";
	pg_assert_same(array('PS1', 'PS2', 'PS3'), plan_state_file_steps($text));
}

// draft held, open ready, merged done, closed unmerged nothing.
function test_plan_state_pr_state()
{
	pg_assert_same('held', plan_state_pr_state(array('state' => 'open', 'draft' => true)));
	pg_assert_same('ready', plan_state_pr_state(array('state' => 'open', 'draft' => false)));
	pg_assert_same('done', plan_state_pr_state(array('state' => 'closed', 'merged_at' => '2026-11-23T10:00:00Z')));
	pg_assert_same('', plan_state_pr_state(array('state' => 'closed', 'merged_at' => null)));
}

// done wins over ready and held; the newest PR of the same state wins;
// plan file steps come first, a step with no PR is todo.
function test_plan_state_steps()
{
	$prs = array(
		array('number' => 10, 'title' => '[plan:p] S1 — a', 'state' => 'closed', 'merged_at' => '2026-11-20', 'html_url' => 'u10'),
		array('number' => 12, 'title' => '[plan:p] S1 — again', 'state' => 'open', 'draft' => true, 'html_url' => 'u12'),
		array('number' => 13, 'title' => '[plan:p] S2 — b', 'state' => 'open', 'draft' => true, 'html_url' => 'u13'),
		array('number' => 14, 'title' => '[plan:p] S2 — b2', 'state' => 'open', 'draft' => false, 'html_url' => 'u14'),
		array('number' => 15, 'title' => '[plan:p] S9 — extra', 'state' => 'open', 'draft' => true, 'html_url' => 'u15'),
		array('number' => 16, 'title' => '[plan:p] S3 — dropped', 'state' => 'closed', 'merged_at' => null, 'html_url' => 'u16'),
		array('number' => 17, 'title' => '[plan:q] S3 — other plan', 'state' => 'open', 'draft' => false, 'html_url' => 'u17'),
	);
	$steps = plan_state_steps($prs, 'p', array('S1', 'S2', 'S3'));
	pg_assert_same(array('S1', 'S2', 'S3', 'S9'), array_keys($steps));
	pg_assert_same(array('step' => 'S1', 'state' => 'done', 'number' => 10, 'url' => 'u10'), $steps['S1']);
	pg_assert_same('ready', $steps['S2']['state']);
	pg_assert_same(14, $steps['S2']['number']);
	pg_assert_same('todo', $steps['S3']['state']);
	pg_assert_same('held', $steps['S9']['state']);
	pg_assert_same('S1 done #10 u10', plan_state_line($steps['S1']));
	pg_assert_same('S3 todo', plan_state_line($steps['S3']));
}

// The task statuses of the states.
function test_plan_state_task_status()
{
	pg_assert_same('todo', plan_state_task_status('todo'));
	pg_assert_same('doing', plan_state_task_status('held'));
	pg_assert_same('waiting', plan_state_task_status('ready'));
	pg_assert_same('done', plan_state_task_status('done'));
}

// Missing tasks are created, existing ones change only in what differs; a
// step with no PR never changes a task, a cancelled task changes only when
// done; of two tasks with one title the lower id is used.
function test_plan_state_task_changes()
{
	$steps = array(
		'S1' => array('step' => 'S1', 'state' => 'done', 'number' => 10, 'url' => 'u10'),
		'S2' => array('step' => 'S2', 'state' => 'ready', 'number' => 14, 'url' => 'u14'),
		'S3' => array('step' => 'S3', 'state' => 'todo', 'number' => 0, 'url' => ''),
		'S4' => array('step' => 'S4', 'state' => 'held', 'number' => 20, 'url' => 'u20'),
		'S5' => array('step' => 'S5', 'state' => 'todo', 'number' => 0, 'url' => ''),
		'S6' => array('step' => 'S6', 'state' => 'held', 'number' => 21, 'url' => 'u21'),
	);
	$tasks = array(
		array('id' => 7, 'title' => 'p S1', 'status' => 'done', 'description' => 'u10'),
		array('id' => 9, 'title' => 'p S2', 'status' => 'doing', 'description' => 'u14'),
		array('id' => 5, 'title' => 'p S2', 'status' => 'todo', 'description' => ''),
		array('id' => 11, 'title' => 'p S3', 'status' => 'doing', 'description' => 'note'),
		array('id' => 12, 'title' => 'p S6', 'status' => 'cancelled', 'description' => ''),
		array('id' => 13, 'title' => 'other S4', 'status' => 'doing', 'description' => 'u20'),
	);
	$changes = plan_state_task_changes($steps, $tasks, 'p');
	pg_assert_same(array(
		array('action' => 'update', 'step' => 'S2', 'task_id' => 5, 'fields' => array('status' => 'waiting', 'description' => 'u14')),
		array('action' => 'create', 'step' => 'S4', 'task_id' => 0, 'fields' => array('title' => 'p S4', 'status' => 'doing', 'description' => 'u20')),
		array('action' => 'create', 'step' => 'S5', 'task_id' => 0, 'fields' => array('title' => 'p S5', 'status' => 'todo')),
	), $changes);
}
