<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a volunteer leaving a shift's waitlist is never counted as a
 * late cancel in the programme's report (includes/fn/volunteer_signup.php,
 * includes/fn/volunteer_report.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_volunteer_waitlist_leave_is_no_late_cancel()
{
	$source = pg_test_function_source('pg_volunteer_self_act');
	pg_assert_contains("UPDATE volunteer_signups SET cancelled_at = 0 WHERE id = ? AND state = 'cancelled'", $source, 'leaving the waitlist clears the cancel stamp');
	pg_assert_contains("(\$own['state'] === 'waiting')", $source, 'only a waiting row loses its stamp, a signed place given back keeps it');

	$shifts = array(5 => array('start' => '2026-11-20 09:00:00', 'roles' => array('r1' => 1)));
	$row = array('shift_id' => 5, 'volunteer_id' => 7, 'name' => 'Ada', 'role' => 'r1', 'state' => 'cancelled', 'outcome' => '', 'cancelled' => '', 'day' => '', 'minutes' => 0, 'approved' => false);
	pg_assert_same(0, pg_volunteer_report_build($shifts, array($row), 24, '2026-11-16', '2026-11-22')['total']['late'], 'a cancelled row with no stamp is no late cancel');
}
