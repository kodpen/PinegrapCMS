<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the month table on the designer canvas: a list whose parent is
 * a table row is drawn as that row's cells, the table's full-width cells
 * span its real column count (_sdTableColumnCount(), cut out of
 * assets/js/style_designer.src.js and run under Node, skipped on a machine
 * without Node), a week draws seven sample days and weekday names, and a
 * condition chip in a day cell is left out instead of cut.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function calendar_month_canvas_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function, up to the next function at the same indentation.
function calendar_month_canvas_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, $name . '() is in the editor');
	$to = strpos($source, "\n    function ", $from + 10);

	return substr($source, $from, $to === false ? null : $to - $from);
}

// Runs a JavaScript expression over the cut function; null without Node.
function calendar_month_canvas_test_run($expr)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = calendar_month_canvas_test_function(calendar_month_canvas_test_source(), '_sdTableColumnCount') . "\n"
		. 'console.log(JSON.stringify(' . $expr . '));';
	$file = tempnam(sys_get_temp_dir(), 'pgcalmonth');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function test_calendar_month_canvas_column_count()
{
	$got = calendar_month_canvas_test_run('['
		// Weekday heads, the weeks' band, a week of seven days.
		. '_sdTableColumnCount([[1,1,1,1,1,1,1],[99],[1,1,1,1,1,1,1]]),'
		// Only full-width cells: no column of their own.
		. '_sdTableColumnCount([[99],[99]]),'
		// A cell spanning two counts as two; the widest row wins.
		. '_sdTableColumnCount([[2,1],[1,1,1,1]]),'
		. '_sdTableColumnCount([]),'
		. '_sdTableColumnCount([["3", 0, null]])'
		. ']');

	if ($got !== null) {
		pg_assert_same(array(7, 0, 4, 0, 3), $got);
	}
}

function test_calendar_month_canvas_row_list_is_drawn_as_cells()
{
	$source = calendar_month_canvas_test_source();
	$build = calendar_month_canvas_test_function($source, 'buildNodeEl');
	pg_assert_contains("=== 'tr' && _sdListIsSourced(node)", $build, 'a list in a table row has its own builder');
	pg_assert_contains('return buildRowListAreaEl(node);', $build);

	$row = calendar_month_canvas_test_function($source, 'buildRowListAreaEl');
	pg_assert_contains('_sdListGhostEls(node)', $row, 'the sample rows follow as cells');
	pg_assert_contains('_sdListSample = outer;', $row, 'a sample week puts the outer sample row back');
	pg_assert_contains('_sdListTableSpans(table)', $row, 'the bands span the real columns');
	pg_assert_true(strpos($row, "createElement('div')") !== false && strpos($row, 'el.insertBefore(band') !== false, 'the band sits inside the first cell');

	pg_assert_contains("'table:has(> thead > tr > .sd-list-td, > tbody > tr > .sd-list-td) { table-layout:fixed; width:100%; }'", $source, 'the columns fit the canvas width');
	pg_assert_contains("'.sd-list-td .sd-chip-row { display:none !important; }'", $source, 'a chip in a day cell is left out, not cut');
	pg_assert_contains("'tr.sd-ghost::after { content:none; }'", $source, 'a sample row adds no column');
}

function test_calendar_month_canvas_samples_fill_a_week()
{
	$source = calendar_month_canvas_test_source();
	$from = strpos($source, "        'calendar.weekdays': [\n            { __weekday_name");
	pg_assert_true($from !== false, 'the weekdays have sample rows');
	$block = substr($source, $from, strpos($source, "        ],\n", strpos($source, "'calendar.days': [", $from)) - $from);

	foreach (array('Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') as $day) {
		pg_assert_contains("__weekday_name: _sdT('" . $day . "')", $block, $day . ' is a sample head');
	}
	foreach (array('6', '7', '8', '9', '10', '11') as $n) {
		pg_assert_contains("__day_number: '" . $n . "'", $block, 'day ' . $n . ' is a sample day');
	}
}
