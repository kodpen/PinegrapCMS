<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for erp_profit_period_title() (includes/erp/profit.php): the period
 * of the expenses card heading as the period picker names it, not as the
 * accountant pack's file name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/profit.php');

function _erp_profit_period_title_constants()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}
	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}
}

function test_erp_profit_period_title_month()
{
	pg_assert_same(get_month_name_from_number('10') . ' 2026', erp_profit_period_title('2026-10-01', '2026-10-31'), 'a whole month: its name and year');
	pg_assert_same(get_month_name_from_number('02') . ' 2028', erp_profit_period_title('2028-02-01', '2028-02-29'), 'a leap February is a whole month');
}

function test_erp_profit_period_title_year()
{
	pg_assert_same(lang(array('string' => 'Year {var:1}', 'vars' => '2026')), erp_profit_period_title('2026-01-01', '2026-12-31'), 'a whole year, as the picker says it');
}

function test_erp_profit_period_title_dates()
{
	_erp_profit_period_title_constants();

	$title = erp_profit_period_title('2026-03-05', '2026-04-20');

	pg_assert_same(prepare_form_data_for_output('2026-03-05', 'date', false) . ' – ' . prepare_form_data_for_output('2026-04-20', 'date', false), $title, 'two dates in the site format');
	pg_assert_false(strpos($title, '_') !== false, 'never the file-name form');
	pg_assert_false(strpos(erp_profit_period_title('2026-10-01', '2026-10-30'), '2026-10') !== false, 'a month short of its end is two dates');
}
