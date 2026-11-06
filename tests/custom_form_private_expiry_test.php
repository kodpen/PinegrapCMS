<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * pg_cf_private_expiry_date(): the private access expiry a custom form grants
 * is counted in calendar days, so a clock change inside the period does not
 * end it a day early.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/custom_form.php');

function test_cf_private_expiry_counts_calendar_days()
{
	pg_assert_same('2026-11-12', pg_cf_private_expiry_date('2026-11-05', 7), 'plain week');
	pg_assert_same('2027-01-04', pg_cf_private_expiry_date('2026-12-30', 5), 'across the year');
	pg_assert_same('2028-03-01', pg_cf_private_expiry_date('2028-02-28', 2), 'leap day');
}

function test_cf_private_expiry_across_clock_changes()
{
	$zone = date_default_timezone_get();
	date_default_timezone_set('Europe/Berlin');

	// Autumn: 2026-10-25 has 25 hours; N * 86400 seconds stopped on the day before.
	$autumn = pg_cf_private_expiry_date('2026-10-20', 10);
	// Spring: 2027-03-28 has 23 hours.
	$spring = pg_cf_private_expiry_date('2027-03-25', 5);

	date_default_timezone_set($zone);

	pg_assert_same('2026-10-30', $autumn, 'autumn clock change');
	pg_assert_same('2027-03-30', $spring, 'spring clock change');
}

function test_cf_private_expiry_rejects_bad_input()
{
	pg_assert_same('', pg_cf_private_expiry_date('not a date', 3), 'bad date');
	pg_assert_same('', pg_cf_private_expiry_date('2026-11-05', 0), 'no days');
}
