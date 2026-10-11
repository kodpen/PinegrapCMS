<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the toolbar helpers in includes/fn/output.php: the list filter
 * (pg_filter_select() - the selected option, the values carried along from
 * the address, escaping) and the period navigator (pg_period_unit(),
 * pg_period_nav()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_output_filter_select_marks_the_current_option()
{
	$html = pg_filter_select('filter', array('' => 'All', 'overdue' => 'Overdue', 'due_week' => 'Due this week'), 'overdue', array('label' => 'Due date'));

	pg_assert_contains('<option value="overdue" selected>Overdue</option>', $html, 'current option');
	pg_assert_contains('<option value="">All</option>', $html, 'empty value stays unselected');
	pg_assert_contains('name="filter"', $html, 'select name');
	pg_assert_contains('onchange="this.form.submit()"', $html, 'submits on change');
	pg_assert_contains('aria-label="Due date"', $html, 'accessible name');
	pg_assert_true(strpos($html, '<form method="get" class="pg-toolbar-filter disable_shortcut">') === 0, 'a GET form of its own');
	pg_assert_same(1, substr_count($html, ' selected'), 'one option selected');
}

function test_output_filter_select_carries_only_the_kept_values()
{
	$saved = $_GET;
	$_GET = array('search' => 'ACME "Ltd"', 'state' => 'open', 'page' => '3', 'empty' => '', 'list' => array('a'));

	$html = pg_filter_select('state', array('' => 'All', 'open' => 'Open'), 'open', array('keep' => array('search', 'state', 'empty', 'list')));

	$_GET = $saved;

	pg_assert_contains('<input type="hidden" name="search" value="ACME &quot;Ltd&quot;">', $html, 'kept value, escaped');
	pg_assert_false(strpos($html, 'type="hidden" name="state"') !== false, 'the filter itself is not carried');
	pg_assert_false(strpos($html, 'name="page"') !== false, 'values not named are dropped');
	pg_assert_false(strpos($html, 'name="empty"') !== false, 'empty values are dropped');
	pg_assert_false(strpos($html, 'name="list"') !== false, 'arrays are dropped');
}

function test_output_filter_select_escapes_labels_and_draws_the_icon()
{
	$html = pg_filter_select('tab', array('a<b' => 'Fish & <chips> (3)'), 'a<b', array('icon' => 'funnel', 'label' => 'Status', 'action' => 'erp_inbox.php?x=1&y=2'));

	pg_assert_contains('<option value="a&lt;b" selected>Fish &amp; &lt;chips&gt; (3)</option>', $html, 'value and label escaped');
	pg_assert_contains('<i class="bi bi-funnel" aria-hidden="true"></i>', $html, 'icon');
	pg_assert_contains('for="pg_filter_tab"', $html, 'icon label points at the select');
	pg_assert_contains('action="erp_inbox.php?x=1&amp;y=2"', $html, 'action escaped');
}

function output_test_range($start, $stop)
{
	return array(
		'start_month' => date('m', strtotime($start)), 'start_day' => date('d', strtotime($start)), 'start_year' => date('Y', strtotime($start)),
		'stop_month' => date('m', strtotime($stop)), 'stop_day' => date('d', strtotime($stop)), 'stop_year' => date('Y', strtotime($stop)),
	);
}

function test_output_period_unit_reads_the_range()
{
	pg_assert_same('day', pg_period_unit(output_test_range('2026-10-10', '2026-10-10')), 'one day');
	pg_assert_same('week', pg_period_unit(output_test_range('2026-10-04', '2026-10-10')), 'Sunday to Saturday');
	pg_assert_same('month', pg_period_unit(output_test_range('2026-10-05', '2026-10-11')), 'seven days not from a Sunday');
	pg_assert_same('week', pg_period_unit(output_test_range('2026-10-25', '2026-10-31')), 'a week across a daylight saving change');
	pg_assert_same('month', pg_period_unit(output_test_range('2026-10-01', '2026-10-31')), 'calendar month');
	pg_assert_same('year', pg_period_unit(output_test_range('2026-01-01', '2026-12-31')), 'calendar year');
	pg_assert_same('month', pg_period_unit(output_test_range('2025-01-01', '2026-12-31')), 'two years step by month');
	pg_assert_same('month', pg_period_unit(output_test_range('2026-09-09', '2026-10-10')), 'any other range');
}

function test_output_period_nav_steps_by_the_unit()
{
	$day = function ($date) { return output_test_range($date, $date); };
	$periods = array(
		'day'   => array($day('2026-10-09'), $day('2026-10-10'), $day('2026-10-11')),
		'week'  => array(output_test_range('2026-09-27', '2026-10-03'), output_test_range('2026-10-04', '2026-10-10'), output_test_range('2026-10-11', '2026-10-17')),
		'month' => array(output_test_range('2026-09-01', '2026-09-30'), output_test_range('2026-10-01', '2026-10-31'), output_test_range('2026-11-01', '2026-11-30')),
		'year'  => array(output_test_range('2025-01-01', '2025-12-31'), output_test_range('2026-01-01', '2026-12-31'), output_test_range('2027-01-01', '2027-12-31')),
	);

	$html = pg_period_nav('view_orders.php', $day('2026-10-10'), $periods, 'Oct 10 &amp; on', ' d-none');

	pg_assert_contains('href="view_orders.php?start_month=10&amp;start_day=09&amp;start_year=2026&amp;stop_month=10&amp;stop_day=09&amp;stop_year=2026"', $html, 'previous day');
	pg_assert_contains('href="view_orders.php?start_month=10&amp;start_day=11&amp;start_year=2026&amp;stop_month=10&amp;stop_day=11&amp;stop_year=2026"', $html, 'next day');
	pg_assert_contains('href="view_orders.php?start_month=01&amp;start_day=01&amp;start_year=2026&amp;stop_month=12&amp;stop_day=31&amp;stop_year=2026"', $html, 'this year in the menu');
	pg_assert_same(1, substr_count($html, 'dropdown-item link-body-emphasis active'), 'one unit marked');
	pg_assert_contains('Oct 10 &amp; on', $html, 'range text as given');
	pg_assert_contains('class="d-inline-block d-none"', $html, 'extra class');
}
