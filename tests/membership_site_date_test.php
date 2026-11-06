<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A membership is tested against the site's own date. get_file.php never runs
 * init.php, so PHP's zone is the server's until initialize_timezone() sets the
 * site's: the active-member flag that check_view_access() reads for a
 * membership folder must be worked out after that call, or a site east of the
 * server lets an expired member in after midnight and a site west of it shuts
 * a member out on the evening of the expiry day. The recurring payment job
 * ends a membership with yesterday's date; a day before is a calendar day, so
 * on the 25-hour day of a clock change it is never today.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_get_file_member_flag_uses_the_site_zone()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');
	$start = strpos($source, 'function initialize_user()');
	pg_assert_true($start !== false, 'initialize_user() is in get_file.php');

	$body = substr($source, $start);
	$flag = strpos($body, "define('USER_MEMBER', true);");
	$zone = strpos($body, 'initialize_timezone();');

	pg_assert_true($flag !== false, 'the active-member flag is set in initialize_user()');
	pg_assert_true(($zone !== false) && ($zone < $flag), 'the site zone is set before the flag is worked out');
}

function test_recurring_job_previous_date_is_a_calendar_day()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/recurring_payment_job.php');

	pg_assert_false(strpos($source, 'time() - 86400') !== false, 'yesterday is not 24 hours ago');
	pg_assert_true(strpos($source, "\$previous_date = date('Y-m-d', strtotime('-1 day', strtotime(\$current_date)));") !== false, 'yesterday is the calendar day before today');

	// The 25-hour day of the autumn change: 24 hours before 23:30 is 00:30 on
	// the same day, the calendar day before is the 26th.
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');
	$now = mktime(23, 30, 0, 10, 27, 2024);
	$current_date = date('Y-m-d', $now);
	pg_assert_same('2024-10-27', date('Y-m-d', $now - 86400), '24 hours back stays on the same day');
	pg_assert_same('2024-10-26', date('Y-m-d', strtotime('-1 day', strtotime($current_date))), 'the calendar day before');
	date_default_timezone_set($zone);
}
