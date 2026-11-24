<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a volunteer programme's report (includes/fn/volunteer_report.php):
 * the period, late cancels, the sums by week, role and volunteer, and the
 * workbook's sheets.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_report.php');

function test_volunteer_report_period()
{
	pg_assert_same(array('weeks' => 4, 'from' => '2026-11-02', 'to' => '2026-11-29'), pg_volunteer_report_period('4', '2026-11-24'), 'whole weeks ending with this one');
	pg_assert_same(12, pg_volunteer_report_period(12, '2026-11-24')['weeks']);
	pg_assert_same(4, pg_volunteer_report_period('7', '2026-11-24')['weeks'], 'an unknown length is the first');
	pg_assert_same(4, pg_volunteer_report_period(array(52), '2026-11-24')['weeks']);
}

function test_volunteer_late_cancel()
{
	pg_assert_false(pg_volunteer_late_cancel('2026-11-19 09:00:00', '2026-11-20 09:00:00', 24), 'exactly the cut-off is on time');
	pg_assert_true(pg_volunteer_late_cancel('2026-11-19 09:01:00', '2026-11-20 09:00:00', 24));
	pg_assert_true(pg_volunteer_late_cancel('2026-11-20 10:00:00', '2026-11-20 09:00:00', 24), 'after the start');
	pg_assert_false(pg_volunteer_late_cancel('', '2026-11-20 09:00:00', 24), 'no cancel');
	pg_assert_true(pg_volunteer_late_cancel('2026-11-19 10:00:00', '2026-11-20 09:00:00', null), 'the default cut-off');
}

function _volunteer_report_test_build()
{
	$shifts = array(
		1 => array('start' => '2026-11-10 09:00:00', 'roles' => array('r1' => 2, 'r2' => 1)),
		2 => array('start' => '2026-11-17 09:00:00', 'roles' => array('r1' => 1)),
	);
	$row = function ($values) {
		return $values + array('shift_id' => 1, 'volunteer_id' => 7, 'name' => 'Ada', 'role' => 'r1', 'state' => 'signed', 'outcome' => '', 'cancelled' => '', 'day' => '', 'minutes' => 0, 'approved' => false);
	};
	$signups = array(
		$row(array('outcome' => 'came', 'minutes' => 240, 'approved' => true)),
		$row(array('volunteer_id' => 8, 'name' => 'Bora', 'outcome' => 'no_show')),
		$row(array('volunteer_id' => 9, 'name' => 'Can', 'role' => 'r1', 'outcome' => 'came', 'minutes' => 120)),
		$row(array('shift_id' => 2, 'volunteer_id' => 8, 'name' => 'Bora', 'state' => 'cancelled', 'cancelled' => '2026-11-17 08:00:00')),
		$row(array('shift_id' => 2, 'volunteer_id' => 9, 'name' => 'Can', 'state' => 'cancelled', 'cancelled' => '2026-11-10 08:00:00')),
		$row(array('shift_id' => null, 'role' => '', 'day' => '2026-11-18', 'minutes' => 90, 'approved' => true)),
		$row(array('shift_id' => null, 'role' => '', 'day' => '2026-10-01', 'minutes' => 600, 'approved' => true)),
		$row(array('shift_id' => 99, 'minutes' => 600, 'approved' => true)),
	);

	return pg_volunteer_report_build($shifts, $signups, 24, '2026-11-09', '2026-11-22');
}

function test_volunteer_report_build_by_week()
{
	$report = _volunteer_report_test_build();

	pg_assert_same(array('2026-11-09', '2026-11-16'), array_keys($report['weeks']));
	pg_assert_same(array('needed' => 3, 'filled' => 2, 'no_show' => 1, 'late' => 0, 'minutes' => 240), $report['weeks']['2026-11-09'], 'three signed for two r1 places fill two; unapproved hours do not count');
	pg_assert_same(array('needed' => 1, 'filled' => 0, 'no_show' => 0, 'late' => 1, 'minutes' => 90), $report['weeks']['2026-11-16'], 'one late cancel, one in time; hours outside a shift on their day');
	pg_assert_same(array('needed' => 4, 'filled' => 2, 'no_show' => 1, 'late' => 1, 'minutes' => 330), $report['total'], 'a day outside the period and an unknown shift are left out');
}

function test_volunteer_report_build_by_role_and_volunteer()
{
	$report = _volunteer_report_test_build();

	pg_assert_same(array('r1' => 240, '' => 90), $report['roles']);
	pg_assert_same(array(7, 8, 9), array_keys($report['volunteers']), 'most hours first, then by name');
	pg_assert_same(array('name' => 'Ada', 'minutes' => 330, 'came' => 1, 'no_show' => 0, 'late' => 0), $report['volunteers'][7]);
	pg_assert_same(array('name' => 'Bora', 'minutes' => 0, 'came' => 0, 'no_show' => 1, 'late' => 1), $report['volunteers'][8]);
}

function test_volunteer_report_sheets()
{
	$report = _volunteer_report_test_build();
	$sheets = pg_volunteer_report_sheets($report, array('active' => 5, 'left' => 1), array('from' => '2026-11-09', 'to' => '2026-11-22'), array('r1' => 'Kitchen', '' => 'Outside a shift'));

	pg_assert_same(4, count($sheets));
	pg_assert_same(array('t' => 'n', 'v' => '5.5'), $sheets[0]['rows'][7][1], 'approved hours as a number');
	pg_assert_same(array('t' => 's', 'v' => 'Kitchen'), $sheets[2]['rows'][2][0], 'a role by its name');
	pg_assert_same(array('t' => 's', 'v' => '=1+1'), pg_volunteer_report_sheets(array('weeks' => array(), 'roles' => array(), 'volunteers' => array(array('name' => '=1+1', 'minutes' => 0, 'came' => 0, 'no_show' => 0, 'late' => 0)), 'total' => $report['total']), array('active' => 0, 'left' => 0), array('from' => '', 'to' => ''), array())[3]['rows'][2][0], 'a name is text, never a formula');
	pg_assert_true(strpos(json_encode($sheets), 'document') === false, 'no document in the export');
}
