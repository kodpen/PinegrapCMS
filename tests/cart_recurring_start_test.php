<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the cart widget's recurring rows (includes/fn/widgets_cart.php):
 * which rows are charged today and which are a later charge, by the rule
 * submit_order() charges with (pg_order_line_charged_today()), and when a
 * stored schedule is written again from the product's defaults.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _pcrs_row($start_date, $start_days = 0, $recurring = 1, $editable = 0)
{
	return array(
		'recurring'                               => $recurring,
		'recurring_start_date'                    => $start_date,
		'recurring_start_days'                    => $start_days,
		'recurring_payment_period'                => 'Monthly',
		'recurring_schedule_editable_by_customer' => $editable,
	);
}

function test_cart_recurring_start_in_the_past_is_charged_today()
{
	$today = '2026-11-09';
	pg_assert_false(_pg_cart_row_charged_later(_pcrs_row('2026-11-08'), $today, false), 'start date yesterday: today');
	pg_assert_false(_pg_cart_row_charged_later(_pcrs_row('2026-11-09'), $today, false), 'start date today: today');
	pg_assert_true(_pg_cart_row_charged_later(_pcrs_row('2026-11-10'), $today, false), 'start date tomorrow: later');
	pg_assert_false(_pg_cart_row_charged_later(_pcrs_row(''), $today, false), 'no date, starts at once: today');
	pg_assert_true(_pg_cart_row_charged_later(_pcrs_row('0000-00-00', 30), $today, false), 'no date, starts in 30 days: later');
	pg_assert_true(_pg_cart_row_charged_later(_pcrs_row('2026-11-08'), $today, true), 'ClearCommerce bills recurring rows itself');
	pg_assert_false(_pg_cart_row_charged_later(_pcrs_row('2026-11-20', 0, 0), $today, true), 'a plain row is always today');
}

function test_cart_recurring_stale_start_is_written_again()
{
	$today = '2026-11-09';
	$blank = _pcrs_row('');
	$blank['recurring_payment_period'] = '';
	pg_assert_true(_pg_cart_recurring_needs_defaults($blank, $today), 'no schedule yet');
	pg_assert_true(_pg_cart_recurring_needs_defaults(_pcrs_row('2026-11-08'), $today), 'product schedule with a past start date');
	pg_assert_false(_pg_cart_recurring_needs_defaults(_pcrs_row('2026-11-09'), $today), 'start date today stays');
	pg_assert_false(_pg_cart_recurring_needs_defaults(_pcrs_row('2026-12-01'), $today), 'future start date stays');
	pg_assert_false(_pg_cart_recurring_needs_defaults(_pcrs_row('2026-11-08', 0, 1, 1), $today), 'the customer\'s own schedule is not overwritten');
	pg_assert_false(_pg_cart_recurring_needs_defaults(_pcrs_row('', 0, 0), $today), 'not a recurring row');
}
