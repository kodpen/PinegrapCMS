<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the task opened to read in My Tasks' drawer
 * (assets/js/workspace_task_read.js, called from taskDrawer.render() of
 * assets/js/workspace.js): the date picker's month, the day arithmetic its
 * arrow keys use, the words of a value row left empty, and that every value
 * is saved through the drawer's own action. The Node parts are skipped where
 * Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of workspace_task_read.js, '' when it is missing.
function workspace_task_read_test_source()
{
	$file = PG_FUNCTIONS_DIR . '/assets/js/workspace_task_read.js';

	return is_file($file) ? str_replace("\r\n", "\n", (string) file_get_contents($file)) : '';
}

// One "function name(" of the file up to its closing brace at its indent.
function workspace_task_read_test_function($name)
{
	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', workspace_task_read_test_source(), $match) ? $match[0] : '';
}

// Runs a script with Node and answers what it printed as JSON; null without Node.
function workspace_task_read_test_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgtaskread');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

function test_workspace_task_read_month_days()
{
	$iso = workspace_task_read_test_function('isoDay');
	$month = workspace_task_read_test_function('monthDays');

	pg_assert_true($iso !== '', 'isoDay() is in workspace_task_read.js');
	pg_assert_true($month !== '', 'monthDays() is in workspace_task_read.js');

	$result = workspace_task_read_test_node($iso . $month . '
var nov = monthDays(2026, 10);
var feb = monthDays(2027, 1);
console.log(JSON.stringify({
	count: nov.length,
	first: nov[0].iso,
	firstIn: nov[0].inMonth,
	one: nov[6].iso,
	oneIn: nov[6].inMonth,
	last: nov[41].iso,
	feb: feb[0].iso,
	febEnd: feb[27].iso
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(42, $result['count'], 'six weeks are drawn');
	pg_assert_same('2026-10-26', $result['first'], 'the month opens on the Monday before its first day');
	pg_assert_false($result['firstIn'], 'a day of the month before is marked');
	pg_assert_same('2026-11-01', $result['one'], '1 November 2026 is a Sunday, the last of the first row');
	pg_assert_true($result['oneIn']);
	pg_assert_same('2026-12-06', $result['last']);
	pg_assert_same('2027-02-01', $result['feb'], 'a month starting on a Monday starts the grid');
	pg_assert_same('2027-02-28', $result['febEnd']);
}

function test_workspace_task_read_shift_day()
{
	$iso = workspace_task_read_test_function('isoDay');
	$shift = workspace_task_read_test_function('shiftDay');

	pg_assert_true($shift !== '', 'shiftDay() is in workspace_task_read.js');

	$result = workspace_task_read_test_node($iso . $shift . '
console.log(JSON.stringify({
	next: shiftDay("2026-11-30", 1),
	week: shiftDay("2026-12-29", 7),
	back: shiftDay("2026-03-01", -1),
	leap: shiftDay("2028-02-28", 1),
	dst: shiftDay("2026-10-24", 7)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same('2026-12-01', $result['next']);
	pg_assert_same('2027-01-05', $result['week'], 'a week on crosses the year');
	pg_assert_same('2026-02-28', $result['back']);
	pg_assert_same('2028-02-29', $result['leap']);
	pg_assert_same('2026-10-31', $result['dst'], 'a clock change does not move the day');
}

function test_workspace_task_read_saves_through_the_drawer_action()
{
	$save = workspace_task_read_test_function('saveValue');

	pg_assert_true($save !== '', 'saveValue() is in workspace_task_read.js');
	pg_assert_contains("h.api('ws_task_save', data)", $save, 'a value row saves with the action the form saved with');
	pg_assert_contains('drawer.values()', $save, 'every field is sent as it stands, so one change keeps the rest');
	pg_assert_contains('result.needs_confirm', $save, 'a clash is asked about, as the form asked');
	pg_assert_contains("h.api('ws_task_get'", $save, 'the page is drawn again from the stored task');
	pg_assert_contains('why: { key: key', $save, 'a refusal is said under its row');
}

function test_workspace_task_read_wired_into_the_drawer()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains('window.PGWsTaskRead.page(self, task, readHelpers())', $source, 'an existing task opens as a page');
	pg_assert_contains('window.PGWsTaskRead.newForm(self, body, readHelpers())', $source, 'New task keeps a short form');
	pg_assert_contains("window.PGWsTaskRead = { page: page, newForm: newForm };", workspace_task_read_test_source());

	$bundles = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');

	pg_assert_contains("'assets/js/workspace_task_read.js',", $bundles, 'the part is in the Workspace bundle');
	pg_assert_true(strpos($bundles, "'assets/js/workspace_task_read.js',") < strpos($bundles, "'assets/js/workspace.js',"), 'and before workspace.js, which calls it');
}

function test_workspace_task_read_keys_stay_in_the_picker()
{
	$keys = workspace_task_read_test_function('pickerKeys');

	pg_assert_true($keys !== '', 'pickerKeys() is in workspace_task_read.js');
	pg_assert_contains("event.key === 'Escape'", $keys);
	pg_assert_contains('event.stopPropagation()', $keys, 'Esc closes the picker, not the drawer');
	pg_assert_contains("'ArrowDown'", $keys, 'the arrows move in a menu');
}
