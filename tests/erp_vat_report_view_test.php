<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP tax report on the shared view
 * (includes/fn/report_view.php): its figures are the cards it had, its
 * chart reads the By month table or the kinds of document, and the screen
 * hands both to the drawing with its tables and export untouched.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_vat_view_money($kurus)
{
	return number_format($kurus / 100, 2, '.', '') . ' TL';
}

function pg_test_vat_view_names()
{
	return array(
		'calculated' => 'Calculated VAT',
		'deductible' => 'Deductible VAT',
		'kinds' => array('sales' => 'Sales invoices', 'sales_return' => 'Sales returns', 'purchase' => 'Purchase invoices', 'purchase_return' => 'Returns to suppliers', 'expense' => 'Expenses'),
		'months' => array('2026-09' => 'September 2026', '2026-10' => 'October 2026'),
	);
}

// Calculated, deductible, difference and, with withholding, the withheld.
function test_erp_vat_report_view_figures()
{
	$totals = array('calculated' => 120000, 'deductible' => 150050, 'deductible_purchases' => 100000, 'deductible_expenses' => 50050, 'difference' => -30050, 'withheld_sales' => 4000, 'withheld_purchases' => 700);
	$names = pg_test_vat_view_names();
	$figures = pg_report_view_vat_figures($totals, false, $names, 'pg_test_vat_view_money');

	pg_assert_same(array('Calculated VAT', 'Deductible VAT', 'Difference'), array_column($figures, 'label'));
	pg_assert_same(array('1200.00 TL', '1500.50 TL', '-300.50 TL'), array_column($figures, 'text'), 'the totals the tables print');
	pg_assert_same('Sales less sales returns', $figures[0]['note']);
	pg_assert_same('Purchases 1000.00 TL · expenses 500.50 TL', $figures[1]['note']);
	pg_assert_same('Deductible exceeds calculated', $figures[2]['note']);
	pg_assert_same('bad', $figures[2]['tone'], 'a carried-over difference is marked');

	$with = pg_report_view_vat_figures($totals, true, $names, 'pg_test_vat_view_money');
	pg_assert_same('Withholding', $with[3]['label']);
	pg_assert_same('40.00 TL', $with[3]['text']);
	pg_assert_same('On sales; on purchases 7.00 TL', $with[3]['note']);

	$even = pg_report_view_vat_figures(array('difference' => 0), false, $names, 'pg_test_vat_view_money');
	pg_assert_same('Calculated and deductible are equal', $even[2]['note']);
	pg_assert_same('', $even[2]['tone']);
}

// Two months or more: calculated as a line, deductible dashed beside it.
function test_erp_vat_report_view_chart_by_month()
{
	$report = array('months' => array(
		'2026-09' => array('calculated' => 10000, 'deductible_purchases' => 4000, 'deductible_expenses' => 1000, 'difference' => 5000),
		'2026-10' => array('calculated' => -2000, 'deductible_purchases' => 3000, 'deductible_expenses' => 0, 'difference' => -5000),
	));
	$chart = pg_report_view_vat_chart($report, pg_test_vat_view_names(), 'pg_test_vat_view_money');

	pg_assert_same('line', $chart['kind']);
	pg_assert_same('Calculated VAT by month', $chart['label']);
	pg_assert_same('Deductible VAT', $chart['compare_label']);
	pg_assert_same(array(100.0, 0.0), $chart['values'], 'a month below zero is drawn at zero');
	pg_assert_same(array(50.0, 30.0), $chart['compare'], 'purchases and expenses');
	pg_assert_same('October 2026: Calculated VAT -20.00 TL · Deductible VAT 30.00 TL · Difference -50.00 TL', $chart['tips'][1], 'the tip says the real figures');
	pg_assert_same(array('September 2026', 'October 2026'), $chart['names'], 'each point names its month');
	pg_assert_same(array('100.00 TL', '-20.00 TL'), $chart['notes'], 'the real figures of the line');
	pg_assert_same(array('50.00 TL', '30.00 TL'), $chart['compare_notes'], 'the real figures of the dashed line');
	pg_assert_same(array('label' => 'Difference', 'text' => '-50.00 TL'), $chart['extras'][1], 'the tip adds the difference');

	$quiet = array('months' => array('2026-09' => array('calculated' => 0, 'deductible_purchases' => 0, 'deductible_expenses' => 0, 'difference' => 0), '2026-10' => array('calculated' => 0, 'deductible_purchases' => 0, 'deductible_expenses' => 0, 'difference' => 0)));
	pg_assert_same(null, pg_report_view_vat_chart($quiet, pg_test_vat_view_names(), 'pg_test_vat_view_money'), 'nothing to draw');
}

// One month: the tax of each kind of document, only those with tax.
function test_erp_vat_report_view_chart_by_kind()
{
	$report = array(
		'months' => array('2026-10' => array()),
		'kinds' => array('sales' => array('vat' => 18000), 'sales_return' => array('vat' => 0), 'purchase' => array('vat' => 9000), 'purchase_return' => array('vat' => 500)),
		'totals' => array('deductible_expenses' => 1200),
	);
	$chart = pg_report_view_vat_chart($report, pg_test_vat_view_names(), 'pg_test_vat_view_money');

	pg_assert_same('bars', $chart['kind']);
	pg_assert_same('Tax by document type', $chart['label']);
	pg_assert_same(array('Sales invoices', 'Purchase invoices', 'Returns to suppliers', 'Expenses'), array_column($chart['rows'], 'text'));
	pg_assert_same(array(180.0, 90.0, 5.0, 12.0), array_column($chart['rows'], 'value'));
	pg_assert_same('12.00 TL', $chart['rows'][3]['note'], 'the expenses deductible tax only');

	pg_assert_same(null, pg_report_view_vat_chart(array('months' => array(), 'kinds' => array(), 'totals' => array()), pg_test_vat_view_names(), 'pg_test_vat_view_money'), 'an empty period');
}

// The screen draws the view where the cards stood; the period form, the
// statement, the tables and the export stay.
function test_erp_vat_report_screen_draws_the_view()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_vat_report.php');

	pg_assert_contains("'figures' => pg_report_view_vat_figures(\$totals, \$has_withholding, \$report_view_names, \$report_view_money),", $source);
	pg_assert_contains("'chart' => pg_report_view_vat_chart(\$report, \$report_view_names, \$report_view_money),", $source);
	pg_assert_contains('<section class="pg-report-view" id="pg_report_view"', $source);
	pg_assert_contains("pg_js_bundle_tags('assets/js/pg_report_view.min.js')", $source);
	pg_assert_false(strpos($source, 'pg_dash.min.js') !== false, 'no map here, so not the dashboard bundle');
	pg_assert_false(strpos($source, 'pg_dashboard_v2_style_url') !== false, 'nor the dashboard sheet');
	pg_assert_contains('JSON_HEX_TAG', $source);
	pg_assert_same(0, substr_count($source, '$difference_hint'), 'the cards are gone');
	pg_assert_true(strpos($source, 'id="erp_vat_period"') < strpos($source, 'id="pg_report_view"'), 'the period form stays on top');
	pg_assert_contains("\$report = erp_vat_report(\$period['from'], \$period['to']);", $source, 'the same figures');
	pg_assert_contains("if ((\$_GET['export'] ?? '') === 'xlsx') {", $source, 'the export stays');
	pg_assert_contains('id="erp_vat_months"', $source, 'the By month table stays');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_report_view.js');
	pg_assert_contains('var compare = (chart.compare && chart.compare.length) ? chart.compare : null;', $js, 'a dashed second line');
	pg_assert_contains('legend(chart.compare_label || "", true)', $js, 'named in the legend');
	pg_assert_contains('el("dd", "pg-report-fig-note"', $js, 'a figure keeps its note');
}
