<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The report charts' x-axis (assets/js/pg_report_view.js,
 * pgReportView.axisTicks): the names under a line chart stand at even
 * steps from the first point, never a step that jumps (15, 17, 19, 22).
 * Runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The ticks the script gives for each [count, most] pair; null when Node
// cannot run.
function report_view_axis_test_node($cases)
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

	$script = "var window = {};\nvar document = {readyState: 'loading', addEventListener: function () {}};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_report_view.js') . "\n"
		. 'var cases = ' . json_encode($cases) . ";\n"
		. 'console.log(JSON.stringify(cases.map(function (one) { return window.pgReportView.axisTicks(one[0], one[1]); })));';

	$file = @tempnam(sys_get_temp_dir(), 'pgaxis');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_report_view_axis_ticks_stand_at_even_steps()
{
	$result = report_view_axis_test_node(array(
		array(14, 7),  // the shipping report's two weeks at 1440 px
		array(12, 7),  // the VAT report's year
		array(12, 2),  // the same year on a phone
		array(31, 7),  // a month of days
		array(7, 7),   // every point has room
		array(5, 7),   // fewer points than room
		array(1, 7),   // a lone point
		array(2, 2),
		array(0, 7),
	));

	if ($result === null) {
		return;
	}

	pg_assert_same(array(0, 3, 6, 9, 12), $result[0], 'two weeks: every third day from the first');
	pg_assert_same(array(0, 2, 4, 6, 8, 10), $result[1], 'a year: every second month');
	pg_assert_same(array(0, 11), $result[2], 'two names: the first and the last are one step');
	pg_assert_same(array(0, 5, 10, 15, 20, 25, 30), $result[3], 'a month: every fifth day');
	pg_assert_same(array(0, 1, 2, 3, 4, 5, 6), $result[4], 'room for all: every point');
	pg_assert_same(array(0, 1, 2, 3, 4), $result[5], 'fewer points than room: every point');
	pg_assert_same(array(0), $result[6], 'a lone point: its name');
	pg_assert_same(array(0, 1), $result[7], 'two points: both');
	pg_assert_same(array(), $result[8], 'no point: no name');

	foreach ($result as $n => $ticks) {
		$steps = array();

		for ($i = 1; $i < count($ticks); $i++) {
			$steps[] = $ticks[$i] - $ticks[$i - 1];
		}

		pg_assert_true(count(array_unique($steps)) <= 1, 'case ' . $n . ': one step between all names');
	}
}
