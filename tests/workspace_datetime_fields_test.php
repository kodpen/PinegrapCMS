<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace's date and time fields on the panel's one
 * date-and-time control (assets/js/pg_fields.js): every native date, time
 * and datetime-local input assets/js/workspace.js builds carries its hook and
 * stays the ISO store its post reads, the fields are drawn once they are in
 * the page, a value a script writes is shown, and the control's files reach
 * every workspace screen: printed before the workspace script, or, on a
 * record's screen, fetched before it by the lazy loader (ws_record_loader()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!function_exists('ws_record_loader')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');
}

function workspace_datetime_source($path)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_workspace_date_inputs_carry_their_hook()
{
	$src = workspace_datetime_source('assets/js/workspace.js');

	pg_assert_same(0, preg_match_all("/\\.type = '(date|time|datetime-local)'/", $src), 'no date, time or datetime-local input is made without pgDateField()');
	pg_assert_same(12, preg_match_all("/pgDateField\\(\\w+(\\.\\w+)?, '(date|time)'\\);/", $src), 'the twelve date and time inputs of the task, reminder, poll, hours, message, event and timeline forms go through pgDateField()');
	pg_assert_same(1, preg_match("/var DATE_HOOKS = \\{ date: 'data-pg-date', time: 'data-pg-time', 'datetime-local': 'data-pg-datetime' \\};/", $src), 'each native type has its hook');
	pg_assert_true(strpos($src, "pgDateField(input, (meta.kind === 'date') ? 'date' : 'datetime-local');") !== false, 'a record field of a date or a date and time kind (saValueInput) keeps its native store');
	pg_assert_true(strpos($src, "input.value = value ? String(value).replace(' ', 'T').slice(0, 16) : '';") !== false, 'a date and time value still goes in as Y-m-d\TH:i');
}

function test_workspace_date_fields_are_drawn_and_redrawn()
{
	$src = workspace_datetime_source('assets/js/workspace.js');

	pg_assert_same(1, preg_match('/function start\(\) \{\s*shareKit\(\);\s*watchDateFields\(\);/', $src), 'the watcher starts with the script');
	pg_assert_true(strpos($src, "}).observe(document.body, { childList: true, subtree: true });") !== false, 'fields added anywhere in the page are drawn');
	pg_assert_same(1, preg_match("/fromInput\\.value = '';\\s*toInput\\.value = '';\\s*dateShown\\(fromInput\\);\\s*dateShown\\(toInput\\);/", $src), 'the timeline\'s cleared dates are shown empty');

	$rec = workspace_datetime_source('assets/js/workspace_recurrence.js');
	pg_assert_same(1, preg_match('/start\.value = target;\s*if \(window\.pgFields && window\.pgFields\.refresh\) \{\s*window\.pgFields\.refresh\(start\);/', $rec), 'the start date the repeat box moves is shown');
}

function test_workspace_record_loader_fetches_the_date_control_first()
{
	$out = ws_record_loader(array('/a/workspace.js?v=1'), '<script src="/a/pg_fields.js" data-pg-remove-label="Remove"></script>');

	pg_assert_true(strpos($out, '<template id="ws-fields-assets"><script src="/a/pg_fields.js" data-pg-remove-label="Remove"></script></template>') !== false, 'the control\'s tags wait in a template');
	pg_assert_true(strpos($out, 'document.getElementById("ws-fields-assets")') !== false && strpos($out, '!window.pgFields') !== false, 'they are fetched only when the screen has no control');
	pg_assert_true(strpos($out, 'node.setAttribute(attr.name, attr.value);') !== false, 'a tag keeps its data attributes (the calendar\'s settings, the labels)');

	pg_assert_same(false, strpos(ws_record_loader(array('/a/workspace.js?v=1')), '<template'), 'no template without the control\'s tags');
}

function test_workspace_screens_print_the_date_control()
{
	$src = workspace_datetime_source('includes/workspace/screen.php');

	pg_assert_true(strpos($src, "ws_record_loader(pg_js_bundle_urls('assets/js/workspace.min.js'), get_date_picker_format() . pg_fields_script_tags())") !== false, 'a record\'s screen hands the control to the loader without using up pg_fields_script()');
	$fields = strpos($src, "\$fields = pg_fields_script();");
	$bundle = strpos($src, "\$output .= pg_js_bundle_tags('assets/js/workspace.min.js')");
	pg_assert_true($fields !== false && $bundle !== false && $fields < $bundle, 'the other workspace screens print the control before the workspace script');

	$tags = pg_fields_script_tags();
	pg_assert_true(strpos($tags, '/assets/js/pg_fields.js') !== false && $tags === pg_fields_script_tags(), 'pg_fields_script_tags() gives the tags on every call');
}

// The modules of the workspace bundle build their date and time inputs with
// the dateField helper workspace.js lends them (its pgDateField()).
function test_workspace_module_date_inputs_carry_their_hook()
{
	$counts = array(
		'workspace_approvals.js' => 2,
		'workspace_customer.js' => 2,
		'workspace_recurrence.js' => 1,
		'workspace_task_time.js' => 2,
		'workspace_task_approval.js' => 1,
	);

	foreach ($counts as $file => $count) {
		$src = workspace_datetime_source('assets/js/' . $file);

		pg_assert_same(0, preg_match_all("/\\.type = '(date|time|datetime-local)'/", $src), $file . ': no date or time input is made without the helper');
		pg_assert_same($count, preg_match_all("/\\.dateField\\([^;]*?, '(date|time)'\\)/", $src), $file . ': its date and time inputs go through the helper');
	}

	pg_assert_same(0, preg_match_all("/input\\('date'/", workspace_datetime_source('assets/js/workspace_task_time.js') . workspace_datetime_source('assets/js/workspace_task_approval.js')), 'the small input() builders make no native date');

	$ws = workspace_datetime_source('assets/js/workspace.js');
	pg_assert_same(3, substr_count($ws, 'dateField: pgDateField'), 'the part, feature and repeat helpers lend pgDateField()');
}

// The repeat's end date: its box hides with it and is where the focus goes.
function test_workspace_recurrence_end_date_box()
{
	$rec = workspace_datetime_source('assets/js/workspace_recurrence.js');

	pg_assert_true(strpos($rec, "untilBox.classList.toggle('d-none', ends.value !== 'on');") !== false, 'the end date is hidden with the box drawn after it');
	pg_assert_true(strpos($rec, 'window.pgFields.focus(until);') !== false, 'the focus goes to the box people type in, not the hidden store');
}
