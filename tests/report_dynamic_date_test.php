<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_report_dynamic_value() in includes/fn/forms.php: a 'Day(s)
 * Ago' filter on a date field compares with a whole calendar date, so the
 * Order and Visitor reports keep the boundary day; other dynamic values and
 * field types keep get_dynamic_value()'s result.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_report_dynamic_date_days_ago_on_date_field_is_a_bare_date()
{
	$now = mktime(18, 0, 0, 10, 30, 2026);

	pg_assert_same('2026-10-29', pg_report_dynamic_value('days ago', '1', 'date', $now));
	pg_assert_same('2026-10-23', pg_report_dynamic_value('days ago', '7', 'date', $now));
	pg_assert_same('2026-10-30', pg_report_dynamic_value('days ago', '0', 'date', $now));
}

function test_report_dynamic_date_days_ago_crosses_month_and_year()
{
	$now = mktime(0, 30, 0, 1, 2, 2027);

	pg_assert_same('2026-12-31', pg_report_dynamic_value('days ago', '2', 'date', $now));
	pg_assert_same('2026-11-03', pg_report_dynamic_value('days ago', 60, 'date', $now));
}

function test_report_dynamic_date_days_ago_matches_the_field_side_format()
{
	// The report's field side is FROM_UNIXTIME(..., '%Y-%m-%d'): the operand
	// must compare equal to that string for an order on the boundary day.
	$now = mktime(18, 0, 0, 10, 30, 2026);
	$order_day = date('Y-m-d', mktime(9, 12, 0, 10, 29, 2026));

	pg_assert_true($order_day === pg_report_dynamic_value('days ago', '1', 'date', $now));
	pg_assert_true($order_day >= pg_report_dynamic_value('days ago', '1', 'date', $now));
}

function test_report_dynamic_date_other_cases_keep_get_dynamic_value()
{
	pg_assert_same(date('Y-m-d'), pg_report_dynamic_value('current date', '', 'date'));
	pg_assert_same(get_dynamic_value('viewer', ''), pg_report_dynamic_value('viewer', '', ''));
	pg_assert_true((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', pg_report_dynamic_value('days ago', '1', 'date and time')));
	pg_assert_true((bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', pg_report_dynamic_value('days ago', '1', '')));
}
