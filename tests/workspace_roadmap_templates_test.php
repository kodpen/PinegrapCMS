<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the team's roadmap templates
 * (includes/workspace/roadmap_templates.php): the days a template keeps,
 * a plan made from one, who changes or deletes it, "Move the following steps
 * too" in the roadmap script, and the guards that keep a template apart from
 * the plans.
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

// A template keeps each step's day counted from the earliest step's day, no
// note, customer or done mark.
function test_workspace_roadmap_template_keeps_days_from_the_first_step()
{
	$plan = array(
		'title' => ' Onboarding ', 'note' => 'Call Ayşe', 'contact_id' => 9, 'all_day' => 1,
		'starts_at' => strtotime('2026-11-02 00:00:00'), 'ends_at' => strtotime('2026-11-05 00:00:00'),
	);
	$steps = array(
		array('title' => 'Kick-off', 'day' => '2026-11-03', 'done_at' => 5, 'task_id' => 4),
		array('title' => 'Training', 'day' => '2026-11-10'),
		array('title' => '   ', 'day' => '2026-11-11'),
		array('title' => 'Ask for feedback', 'day' => '0000-00-00'),
		array('title' => 'Prepare', 'day' => '2026-11-02'),
	);
	$made = ws_roadmap_template_from_plan($plan, $steps);

	pg_assert_same(array('title' => 'Onboarding', 'all_day' => 1, 'start_minute' => 0, 'length_minutes' => 3 * 1440), $made['template']);
	pg_assert_same(array(
		array('title' => 'Kick-off', 'day_offset' => 1, 'has_day' => 1),
		array('title' => 'Training', 'day_offset' => 8, 'has_day' => 1),
		array('title' => 'Ask for feedback', 'day_offset' => 0, 'has_day' => 0),
		array('title' => 'Prepare', 'day_offset' => 0, 'has_day' => 1),
	), $made['steps'], 'counted from the earliest day; a step with no title is dropped, one with no day keeps none');
	pg_assert_false(isset($made['template']['note']), 'no note');
	pg_assert_false(isset($made['template']['contact_id']), 'no customer');

	$timed = ws_roadmap_template_from_plan(array('title' => 'Call', 'all_day' => 0,
		'starts_at' => strtotime('2026-11-20 14:30:00'), 'ends_at' => strtotime('2026-11-20 15:15:00')), array());
	pg_assert_same(870, $timed['template']['start_minute'], 'a timed plan keeps its time of day');
	pg_assert_same(45, $timed['template']['length_minutes']);
	pg_assert_same(array(), $timed['steps']);
}

// A plan made from a template starts on the day given, the first step on it.
function test_workspace_roadmap_template_shift_puts_the_first_step_on_the_day()
{
	$template = array('title' => 'Onboarding', 'all_day' => 1, 'start_minute' => 0, 'length_minutes' => 3 * 1440);
	$steps = array(
		array('title' => 'Prepare', 'day_offset' => 0, 'has_day' => 1),
		array('title' => 'Training', 'day_offset' => 8, 'has_day' => 1),
		array('title' => 'Ask', 'day_offset' => 0, 'has_day' => 0),
	);
	$made = ws_roadmap_template_shift($template, $steps, '2027-03-25');

	pg_assert_same(strtotime('2027-03-25 00:00:00'), $made['plan']['starts_at']);
	pg_assert_same(strtotime('2027-03-28 00:00:00'), $made['plan']['ends_at'], 'three days, across the clock change');
	pg_assert_same('', $made['plan']['note']);
	pg_assert_same(array(
		array('title' => 'Prepare', 'day' => '2027-03-25'),
		array('title' => 'Training', 'day' => '2027-04-02'),
		array('title' => 'Ask', 'day' => ''),
	), $made['steps']);

	$shown = ws_personal_plan_present(array('id' => 0, 'remind_at' => 0) + $made['plan']);
	pg_assert_same('2027-03-27', $shown['end'], 'the drawer shows the last day');

	$timed = ws_roadmap_template_shift(array('title' => 'Call', 'all_day' => 0, 'start_minute' => 870, 'length_minutes' => 45), array(), '2026-12-01');
	pg_assert_same(strtotime('2026-12-01 14:30:00'), $timed['plan']['starts_at']);
	pg_assert_same(strtotime('2026-12-01 15:15:00'), $timed['plan']['ends_at']);

	// Back and forth: a plan kept as a template and used again keeps its shape.
	$again = ws_roadmap_template_from_plan($made['plan'] + array('title' => 'Onboarding'), $made['steps']);
	pg_assert_same($template, $again['template']);
}

// Only the maker changes a template; once the maker's account is gone a
// manager (roles 0 to 2) may delete it, nobody changes it.
function test_workspace_roadmap_template_rights()
{
	$template = array('id' => 3, 'user_id' => 7);

	pg_assert_true(ws_roadmap_template_can_change(7, $template, true), 'the maker');
	pg_assert_false(ws_roadmap_template_can_change(8, $template, true), 'another member');
	pg_assert_false(ws_roadmap_template_can_change(7, $template, false), 'a maker off the team');
	pg_assert_false(ws_roadmap_template_can_change(0, array('id' => 3, 'user_id' => 0), true), 'nobody');

	pg_assert_true(ws_roadmap_template_can_delete(7, 3, $template, true, false), 'the maker deletes');
	pg_assert_false(ws_roadmap_template_can_delete(8, 0, $template, true, false), 'an administrator does not while the maker is there');
	pg_assert_true(ws_roadmap_template_can_delete(8, 2, $template, true, true), 'a manager once the maker is gone');
	pg_assert_true(ws_roadmap_template_can_delete(8, 1, $template, true, true), 'a designer is above a manager');
	pg_assert_false(ws_roadmap_template_can_delete(8, 3, $template, true, true), 'a plain member never');
	pg_assert_false(ws_roadmap_template_can_delete(8, 2, $template, false, true), 'a manager off the team never');
	pg_assert_false(ws_roadmap_template_can_change(8, $template, true), 'nobody changes a gone maker\'s template');
}

// "Move the following steps too" in workspace_my_calendar_roadmap.js.
function test_workspace_roadmap_move_following_steps()
{
	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$script = "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_my_calendar_roadmap.js') . "\n"
		. "var steps = [{ title: 'a', day: '2026-11-02' }, { title: 'b', day: '2026-11-05' }, { title: 'c', day: '' }, { title: 'd', day: '2026-11-30' }];\n"
		. "console.log(JSON.stringify({\n"
		. "  later: window.PGWsRoadmap.shiftFollowing(steps, 1, '2026-11-05', '2026-11-08').map(function (s) { return s.day; }),\n"
		. "  back: window.PGWsRoadmap.shiftFollowing(steps, 0, '2026-11-02', '2026-10-31').map(function (s) { return s.day; }),\n"
		. "  last: window.PGWsRoadmap.shiftFollowing(steps, 3, '2026-11-30', '2026-12-02').map(function (s) { return s.day; }),\n"
		. "  same: steps.map(function (s) { return s.day; })\n"
		. "}));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgroadmap');

	if ($file === false) {
		return;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	$result = json_decode(implode("\n", $out), true);

	pg_assert_same(array('2026-11-02', '2026-11-08', '', '2026-12-03'), $result['later'], 'the later steps move by the same days; a step with no day keeps none');
	pg_assert_same(array('2026-10-31', '2026-11-03', '', '2026-11-28'), $result['back'], 'back across a month');
	pg_assert_same(array('2026-11-02', '2026-11-05', '', '2026-12-02'), $result['last'], 'the last step moves alone');
	pg_assert_same(array('2026-11-02', '2026-11-05', '', '2026-11-30'), $result['same'], 'the list given is not changed');
}

// A template is not a plan: the module reads the plans only through the
// gate's functions, and the draft it fills has no note and no customer.
function test_workspace_roadmap_templates_stay_apart_from_plans()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/roadmap_templates.php');

	pg_assert_same(0, preg_match('/ws_personal_plan(s|_steps|_people)\b[^(]/', $module), 'no plan table named');
	pg_assert_contains('$plan = ws_personal_plan($user_id, (int) $plan_id);', $module, 'saving reads the plan through the gate');
	pg_assert_contains('ws_personal_plan_can_edit($user_id, $plan)', $module, 'only the owner keeps a plan as a template');
	pg_assert_same(0, preg_match('/contact_id|_people_ids|visibility/', $module), 'no customer or people kept');

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
	pg_assert_same(0, preg_match('/ws_roadmap_template/', $api), 'not in the API');
}
