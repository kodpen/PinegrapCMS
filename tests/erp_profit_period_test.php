<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the report period (includes/erp/profit.php): erp_profit_period()
 * keeps dates inside the reportable years, erp_profit_months() counts the
 * months without ever running past year 9999, and
 * erp_profit_period_choices() always lists the period shown, selected.
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

function test_erp_profit_period_refuses_years_out_of_bounds()
{
	$today = '2026-11-02';

	foreach (array(
		array('range' => 'year', 'year' => '9999'),
		array('range' => 'year', 'year' => '1969'),
		array('range' => 'month', 'month' => '9999-12'),
		array('range' => 'quarter', 'quarter' => '9999-Q4'),
		array('range' => 'dates', 'from' => '9998-01-01', 'to' => '9999-12-31'),
		array('range' => 'dates', 'from' => '2026-01-01', 'to' => '2101-01-01'),
	) as $input) {
		$period = erp_profit_period($input, $today);

		pg_assert_same('2026-11-01', $period['from'], 'out of bounds falls back to this month: ' . json_encode($input));
		pg_assert_same('2026-11-30', $period['to'], 'to the end of this month');
		pg_assert_same('month', $period['range'], 'as a month');
		pg_assert_true($period['error'] !== '', 'with an error');
	}
}

function test_erp_profit_period_keeps_years_in_bounds()
{
	$period = erp_profit_period(array('range' => 'year', 'year' => '2100'), '2026-11-02');
	pg_assert_same(array('2100-01-01', '2100-12-31', ''), array($period['from'], $period['to'], $period['error']), 'the last year is allowed');

	$period = erp_profit_period(array('range' => 'month', 'month' => '1970-01'), '2026-11-02');
	pg_assert_same(array('1970-01-01', '1970-01-31', ''), array($period['from'], $period['to'], $period['error']), 'the first year is allowed');

	$period = erp_profit_period(array('range' => 'quarter', 'quarter' => '2023-Q1'), '2026-11-02');
	pg_assert_same(array('2023-01-01', '2023-03-31', 'quarter'), array($period['from'], $period['to'], $period['range']), 'an old quarter');

	$period = erp_profit_period(array(), '2026-11-02');
	pg_assert_same(array('2026-11-01', '2026-11-30', ''), array($period['from'], $period['to'], $period['error']), 'nothing sent is this month, silently');
}

// Two dates given the other way round are swapped and then capped at two
// years like any other pair, with the same notice.
function test_erp_profit_period_caps_reversed_dates_at_two_years()
{
	$today = '2026-11-02';
	$capped = lang('The report covers at most two years at a time; the end date was brought forward.');

	$forward = erp_profit_period(array('range' => 'dates', 'from' => '2020-01-01', 'to' => '2030-12-31'), $today);
	$reversed = erp_profit_period(array('range' => 'dates', 'from' => '2030-12-31', 'to' => '2020-01-01'), $today);
	pg_assert_same(array('2020-01-01', '2021-12-31', 'dates', $capped), array($forward['from'], $forward['to'], $forward['range'], $forward['error']), 'a long pair is capped');
	pg_assert_same($forward, $reversed, 'the same pair reversed is capped the same way');

	$period = erp_profit_period(array('range' => 'dates', 'from' => '2100-12-31', 'to' => '1970-01-01'), $today);
	pg_assert_same(array('1970-01-01', '1971-12-31', $capped), array($period['from'], $period['to'], $period['error']), 'the widest reversed pair is capped too');

	$period = erp_profit_period(array('range' => 'dates', 'from' => '2026-02-01', 'to' => '2026-01-05'), $today);
	pg_assert_same(array('2026-01-05', '2026-02-01', ''), array($period['from'], $period['to'], $period['error']), 'a short reversed pair is only swapped, silently');
}

function test_erp_profit_months_counts_without_string_compare()
{
	pg_assert_same(array('2026-11', '2026-12', '2027-01'), erp_profit_months('2026-11-15', '2027-01-03'), 'across a year end');
	pg_assert_same(array('2026-02'), erp_profit_months('2026-02-01', '2026-02-28'), 'one month');
	pg_assert_same(array(), erp_profit_months('2026-03-01', '2026-02-01'), 'nothing when reversed');
	pg_assert_same(array('9999-11', '9999-12'), erp_profit_months('9999-11-01', '9999-12-31'), 'stops at year 9999');
	pg_assert_same(array(), erp_profit_months('', '2026-01-01'), 'nothing for a broken date');
}

function test_erp_profit_period_choices_list_the_shown_period()
{
	$today = '2026-11-02';

	$choices = erp_profit_period_choices(erp_profit_period(array('range' => 'month', 'month' => '2026-10'), $today), $today);
	pg_assert_same(24, count($choices['month']), 'a recent month adds nothing');
	pg_assert_same('2026-11', array_keys($choices['month'])[0], 'newest first');
	pg_assert_true($choices['month']['2026-10'], 'the shown month is selected');
	pg_assert_same(1, count(array_filter($choices['month'])), 'only one selected');

	$choices = erp_profit_period_choices(erp_profit_period(array('range' => 'month', 'month' => '2024-05'), $today), $today);
	pg_assert_same(25, count($choices['month']), 'an old month is added');
	pg_assert_true($choices['month']['2024-05'], 'and selected');
	pg_assert_same('2024-05', array_key_last($choices['month']), 'at its place, oldest last');

	$choices = erp_profit_period_choices(erp_profit_period(array('range' => 'quarter', 'quarter' => '2023-Q1'), $today), $today);
	pg_assert_same('2026-Q4', array_keys($choices['quarter'])[0], 'this quarter first');
	pg_assert_true(isset($choices['quarter']['2025-Q1']) && !isset($choices['quarter']['2024-Q4']), 'eight quarters back');
	pg_assert_true($choices['quarter']['2023-Q1'], 'an old quarter is added and selected');

	$choices = erp_profit_period_choices(erp_profit_period(array('range' => 'year', 'year' => '2019'), $today), $today);
	pg_assert_true($choices['year']['2019'], 'an old year is added and selected');
	pg_assert_same(7, count($choices['year']), 'beside the six years');

	$choices = erp_profit_period_choices(erp_profit_period(array('range' => 'dates', 'from' => '2026-01-05', 'to' => '2026-02-01'), $today), $today);
	pg_assert_same(0, count(array_filter($choices['month'])) + count(array_filter($choices['quarter'])) + count(array_filter($choices['year'])), 'two dates select nothing in the lists');
}

// A sale move of no cost was written while the product had no cost: the
// report reads it as unknown (today's average, else "no cost known"), never
// as a known cost of nothing.
function test_erp_profit_report_reads_a_zero_cost_move_as_unknown()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/profit.php');

	pg_assert_contains('if (!empty($moves[$key])) {', $source, 'a move counts only with a cost');
	pg_assert_contains("!empty(\$moves['sale:' . (int) \$parent['id']])", $source, 'a return reads its sale the same way');
	pg_assert_false(strpos($source, 'if (isset($moves[$key])) {') !== false, 'no isset() test left');
}
