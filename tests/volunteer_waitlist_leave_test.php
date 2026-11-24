<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that leaving a volunteer shift's waitlist is no late cancel: the
 * cancel of a waiting row is written without a cancel time, so the
 * programme's Report never counts it, while a signed place given back is
 * stamped as before (includes/fn/volunteer_signup.php,
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_signup.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_report.php');

function test_volunteer_cancel_stamped_only_for_a_signed_place()
{
	pg_assert_same(true, pg_volunteer_cancel_stamped('signed'), 'a signed place given back is stamped');
	pg_assert_same(false, pg_volunteer_cancel_stamped('waiting'), 'leaving the waitlist is not');
	pg_assert_same(false, pg_volunteer_cancel_stamped(''), 'an unknown state is not');
}

function test_volunteer_report_skips_a_waitlist_leave()
{
	$shifts = array(7 => array('start' => '2026-12-10 09:00:00', 'roles' => array('r1' => 1)));
	$row = array('shift_id' => 7, 'volunteer_id' => 3, 'name' => 'Ada', 'role' => 'r1', 'state' => 'cancelled', 'outcome' => '', 'day' => '', 'minutes' => 0, 'approved' => false);

	// The row a waitlist leave two hours before the start now writes.
	$left = pg_volunteer_report_build($shifts, array($row + array('cancelled' => '')), 24, '2026-12-07', '2026-12-13');
	pg_assert_same(0, (int) $left['total']['late'], 'a waitlist leave is no late cancel');

	$late = pg_volunteer_report_build($shifts, array($row + array('cancelled' => '2026-12-10 07:00:00')), 24, '2026-12-07', '2026-12-13');
	pg_assert_same(1, (int) $late['total']['late'], 'a stamped cancel past the cut-off still counts');
}

function test_volunteer_self_cancel_writes_the_stamp_by_state()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_signup.php');

	pg_assert_true(strpos($source, "cancelled_at = UNIX_TIMESTAMP(), updated_at = UNIX_TIMESTAMP() WHERE id = ? AND state = ?") === false, 'the own cancel does not stamp every state');
	pg_assert_true(strpos($source, "pg_volunteer_cancel_stamped(\$own['state'])") !== false, 'the own cancel asks whether to stamp');
}
