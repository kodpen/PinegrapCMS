<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipping report on the shared view
 * (includes/fn/report_view.php): its figures equal the Grand Total row, its
 * line counts the day columns, and the screen hands both to the drawing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Products, Before, In this period (the days summed), After, Total.
function test_shipping_report_view_figures()
{
	$dates = array('2026-11-10', '2026-11-11', '2026-11-12');
	$totals = array('before' => 4, '2026-11-10' => 2, '2026-11-11' => 0, '2026-11-12' => 1250, 'after' => 3, 'total' => 1259);
	$figures = pg_report_view_shipping_figures($totals, $dates, 7);

	pg_assert_same(array('Products', 'Before', 'In this period', 'After', 'Total'), array_map(function ($figure) {
		return $figure['label'];
	}, $figures));
	pg_assert_same(pg_format_number(7, 0), $figures[0]['text']);
	pg_assert_same(pg_format_number(4, 0), $figures[1]['text']);
	pg_assert_same(pg_format_number(1252, 0), $figures[2]['text'], 'the days summed');
	pg_assert_same(pg_format_number(3, 0), $figures[3]['text']);
	pg_assert_same(pg_format_number(1259, 0), $figures[4]['text'], 'the Grand Total cell');

	// No data: the screen has no totals; every figure reads 0.
	$empty = pg_report_view_shipping_figures(array(), $dates, 0);
	pg_assert_same(pg_format_number(0, 0), $empty[4]['text']);
}

// One point a day in the period's order; none for one day or no units.
function test_shipping_report_view_chart()
{
	$dates = array('2026-11-10', '2026-11-11', '2026-11-12');
	$names = array('Tue 10 Nov', 'Wed 11 Nov', 'Thu 12 Nov');
	$totals = array('before' => 9, '2026-11-10' => 2, '2026-11-12' => 1, 'after' => 9, 'total' => 21);
	$chart = pg_report_view_shipping_chart($totals, $dates, $names);

	pg_assert_same('line', $chart['kind']);
	pg_assert_same('Units by ship date', $chart['label']);
	pg_assert_same(array(2, 0, 1), $chart['values'], 'the day columns, a missing day 0');
	pg_assert_same('Units', $chart['series'], 'the legend names the line');
	pg_assert_same($names, $chart['names'], 'each point names its day');
	pg_assert_same(array(pg_format_number(2, 0), pg_format_number(0, 0), pg_format_number(1, 0)), $chart['notes'], 'the figure each tip shows');
	pg_assert_same('Tue 10 Nov: 2 units', $chart['tips'][0]);
	pg_assert_same('Thu 12 Nov: 1 unit', $chart['tips'][2]);

	pg_assert_same(null, pg_report_view_shipping_chart($totals, array('2026-11-10'), array('Tue 10 Nov')), 'one day');
	pg_assert_same(null, pg_report_view_shipping_chart(array('before' => 5, 'total' => 5), $dates, $names), 'nothing in the period');
	pg_assert_same(null, pg_report_view_shipping_chart(array(), $dates, $names), 'no data');
}

// The screen prints the view over its table, without the dashboard's sheet
// and bundle (no map here); its query, period and lists are untouched.
function test_shipping_report_screen_draws_the_view()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_shipping_report.php');

	pg_assert_contains("'figures' => pg_report_view_shipping_figures(\$report_view_totals, \$dates, count(\$products)),", $source);
	pg_assert_contains("'chart' => pg_report_view_shipping_chart(\$report_view_totals, \$dates, \$report_view_names),", $source);
	pg_assert_contains('<section class="pg-report-view" id="pg_report_view"', $source);
	pg_assert_contains("JSON_HEX_TAG", $source, 'the data cannot close its script');
	pg_assert_contains("pg_js_bundle_tags('assets/js/pg_report_view.min.js')", $source);
	pg_assert_false(strpos($source, 'pg_dash.min.js') !== false, 'not the dashboard bundle');
	pg_assert_false(strpos($source, 'pg_dashboard_v2_style_url') !== false, 'nor the dashboard sheet');
	pg_assert_true(strpos($source, 'id="pg_report_view"') < strpos($source, '<section class="pg-card pg-report-frame"'), 'the view sits over the table');
	pg_assert_contains("AND (orders.status IN ('complete', 'exported'))", $source, 'the same orders');
	pg_assert_contains('list($start_date, $end_date) = pg_shipping_report_period(', $source, 'the same period rule');
}
