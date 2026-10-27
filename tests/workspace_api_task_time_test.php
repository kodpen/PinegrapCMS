<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for time spent on tasks over the Workspace API: the caller's own
 * time across tasks between two days (GET /workspace/time: the range and its
 * bounds, the page position, a task the caller no longer sees, the running
 * timer) and changing one entry (the check the panel can share, the fields
 * left alone, the refusals, the rehearsal).
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

function ws_api_task_time_test_route($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function ws_api_task_time_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

function ws_api_task_time_test_viewer($role = 3)
{
	return array('id' => 7, 'member' => true, 'role' => $role);
}

// A ws_task_time row: 90 minutes written by hand by person 7.
function ws_api_task_time_test_entry($overrides = array())
{
	return $overrides + array(
		'id' => 311, 'task_id' => 42, 'user_id' => 7, 'started_at' => 0, 'minutes' => 90,
		'worked_on' => '2026-10-24', 'note' => 'Layout', 'billable' => 1, 'invoice_id' => 0, 'created_at' => 1793000000,
	);
}

// ─── The range ──────────────────────────────────────────────────────────

function test_ws_task_time_range_defaults_to_this_week()
{
	$range = ws_task_time_range_check('', '', '2026-10-29');

	pg_assert_true($range['ok']);
	pg_assert_same('2026-10-26', $range['from'], 'the Monday of the last day');
	pg_assert_same('2026-10-29', $range['to'], 'today');

	$range = ws_task_time_range_check('', '2026-10-25', '2026-10-29');
	pg_assert_same('2026-10-19', $range['from'], 'the Monday of the week of to');
}

function test_ws_task_time_range_bounds()
{
	pg_assert_true(ws_task_time_range_check('2026-08-01', '2026-10-31', '2026-10-29')['ok'], '92 days');

	$range = ws_task_time_range_check('2026-07-31', '2026-10-31', '2026-10-29');
	pg_assert_false($range['ok'], '93 days');
	pg_assert_same('to', $range['field']);

	$range = ws_task_time_range_check('2026-10-26', '2026-10-20', '2026-10-29');
	pg_assert_false($range['ok'], 'to before from');
	pg_assert_same('to', $range['field']);

	pg_assert_true(ws_task_time_range_check('2026-10-26', '2026-10-26', '2026-10-29')['ok'], 'one day');
}

function test_ws_task_time_range_refuses_an_unreadable_day()
{
	$range = ws_task_time_range_check('2026-02-30', '', '2026-10-29');
	pg_assert_false($range['ok']);
	pg_assert_same('from', $range['field']);

	$range = ws_task_time_range_check('', 'next week', '2026-10-29');
	pg_assert_false($range['ok']);
	pg_assert_same('to', $range['field']);
}

// The page position is the day and the id, newest day first.
function test_ws_task_time_position_round_trip()
{
	pg_assert_same(20261024, ws_task_time_position_pack('2026-10-24'));
	pg_assert_same('2026-10-24', ws_task_time_position_unpack(20261024));
	pg_assert_same(null, ws_task_time_position_unpack(20261332), 'no such day');
	pg_assert_same(null, ws_task_time_position_unpack(0));
}

// Only the caller's rows, the running timer among them, newest day first.
function test_ws_task_time_range_where_reads_only_the_callers_rows()
{
	$where = ws_task_time_range_where(7, '2026-10-20', '2026-10-26', null);

	pg_assert_contains("user_id = '7'", $where);
	pg_assert_contains("worked_on >= '2026-10-20'", $where);
	pg_assert_contains("worked_on <= '2026-10-26'", $where);
	pg_assert_false(strpos($where, 'minutes >') !== false, 'a running timer (minutes 0) is listed');

	$after = ws_task_time_range_where(7, '2026-10-20', '2026-10-26', array('day' => '2026-10-24', 'id' => 311));
	pg_assert_contains("(worked_on < '2026-10-24' OR (worked_on = '2026-10-24' AND id < '311'))", $after, 'the next page');
	pg_assert_contains('ORDER BY worked_on DESC, id DESC', ws_task_time_test_range_source(), 'newest day first');
}

function ws_task_time_test_range_source()
{
	return ws_api_task_time_test_source('ws_task_time_range');
}

// A task the caller can no longer see keeps its id, with no title.
function test_ws_task_time_task_ref_hides_a_task_out_of_reach()
{
	pg_assert_same(array('id' => 42, 'title' => 'Spring catalogue', 'visible' => true), ws_task_time_task_ref(42, array('id' => 42, 'title' => 'Spring catalogue')));
	pg_assert_same(array('id' => 43, 'title' => '', 'visible' => false), ws_task_time_task_ref(43, null));
}

// The panel's My time and the API ask the same question about a task.
function test_ws_task_time_mine_and_range_share_the_visibility()
{
	pg_assert_contains('ws_task_time_seen_task(', ws_api_task_time_test_source('ws_task_time_mine'));
	pg_assert_contains('ws_task_time_seen_task(', ws_task_time_test_range_source());
	pg_assert_contains('ws_can_see_task(', ws_api_task_time_test_source('ws_task_time_seen_task'));
}

// A row of the range is the per-task row plus its task; a running timer is
// running with 0 minutes.
function test_ws_api_task_time_range_row()
{
	$row = ws_api_task_time_range_present(ws_api_task_time_test_entry(array('minutes' => 0, 'started_at' => 1793000000)), ws_task_time_task_ref(43, null));

	pg_assert_true($row['running']);
	pg_assert_same(0, $row['minutes']);
	pg_assert_same(array('id' => 43, 'title' => '', 'visible' => false), $row['task']);
	pg_assert_same(array_keys(ws_api_task_time_schema()), array_slice(array_keys($row), 0, count(ws_api_task_time_schema())), 'the per-task fields first');
}

function test_ws_api_time_list_route()
{
	$route = ws_api_task_time_test_route('workspace.time.list');

	pg_assert_true(is_array($route), 'declared');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/workspace/time', $route['path']);
	pg_assert_same('tasks:read', $route['scope']);
	pg_assert_contains('total_minutes', ws_api_task_time_test_source('ws_api_time_list'), 'the total of the range');
}

// ─── Changing an entry ──────────────────────────────────────────────────

// Only the fields sent change.
function test_ws_task_time_update_check_partial()
{
	$checked = ws_task_time_update_check(ws_api_task_time_test_viewer(), ws_api_task_time_test_entry(), array('minutes' => 45), '2026-10-27');

	pg_assert_true($checked['ok']);
	pg_assert_same(array('minutes' => 45, 'worked_on' => '2026-10-24', 'note' => 'Layout', 'billable' => 1), $checked['data']);

	$checked = ws_task_time_update_check(ws_api_task_time_test_viewer(), ws_api_task_time_test_entry(), array('note' => "  New\n words ", 'billable' => false, 'worked_on' => '2026-10-27'), '2026-10-27');
	pg_assert_same(array('minutes' => 90, 'worked_on' => '2026-10-27', 'note' => 'New words', 'billable' => 0), $checked['data']);
}

function test_ws_task_time_update_check_values()
{
	$viewer = ws_api_task_time_test_viewer();
	$entry = ws_api_task_time_test_entry();

	pg_assert_same('minutes', ws_task_time_update_check($viewer, $entry, array('minutes' => 0), '2026-10-27')['field']);
	pg_assert_same('minutes', ws_task_time_update_check($viewer, $entry, array('minutes' => WS_TASK_TIME_MAX + 1), '2026-10-27')['field']);
	pg_assert_same('worked_on', ws_task_time_update_check($viewer, $entry, array('worked_on' => '2026-10-28'), '2026-10-27')['field'], 'a day to come');
	pg_assert_same('worked_on', ws_task_time_update_check($viewer, $entry, array('worked_on' => 'soon'), '2026-10-27')['field']);
	pg_assert_same('worked_on', ws_task_time_update_check($viewer, $entry, array('worked_on' => ''), '2026-10-27')['field'], 'a day cannot be taken away');
}

// Whoever may delete it: one's own entry, any for staff.
function test_ws_task_time_update_check_who()
{
	$theirs = ws_api_task_time_test_entry(array('user_id' => 8));
	$checked = ws_task_time_update_check(ws_api_task_time_test_viewer(3), $theirs, array('minutes' => 30), '2026-10-27');

	pg_assert_false($checked['ok']);
	pg_assert_same(403, $checked['status']);
	pg_assert_true(ws_task_time_update_check(ws_api_task_time_test_viewer(2), $theirs, array('minutes' => 30), '2026-10-27')['ok'], 'staff');
}

// An entry on an invoice draft and a running timer are refused with 422.
function test_ws_task_time_update_check_invoice_and_timer()
{
	$viewer = ws_api_task_time_test_viewer(1);

	$checked = ws_task_time_update_check($viewer, ws_api_task_time_test_entry(array('invoice_id' => 55)), array('minutes' => 30), '2026-10-27');
	pg_assert_false($checked['ok'], 'on an invoice draft');
	pg_assert_same(422, $checked['status']);
	pg_assert_same('entry_id', $checked['field']);

	$checked = ws_task_time_update_check($viewer, ws_api_task_time_test_entry(array('minutes' => 0, 'started_at' => 1793000000)), array('note' => 'x'), '2026-10-27');
	pg_assert_false($checked['ok'], 'a running timer');
	pg_assert_same(422, $checked['status']);
	pg_assert_same('entry_id', $checked['field']);
}

function test_ws_api_task_time_update_route_rehearses()
{
	$route = ws_api_task_time_test_route('workspace.tasks.time.update');

	pg_assert_true(is_array($route), 'declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/tasks/{id}/time/{entry_id}/update', $route['path'], 'beside the POST that deletes');
	pg_assert_same('tasks:write', $route['scope']);
	pg_assert_true(!empty($route['dry_run']));

	$source = ws_api_task_time_test_source('ws_api_task_time_update');
	pg_assert_true(strpos($source, 'ws_task_time_update_check(') < strpos($source, 'api_dry_run_stop('), 'checked before the rehearsal stops');
	pg_assert_true(strpos($source, 'api_dry_run_stop(') < strpos($source, 'ws_task_time_update('), 'written after it');
	pg_assert_contains('ws_task_time_update_check(', ws_api_task_time_test_source('ws_task_time_update'), 'the write meets the same check');
}
