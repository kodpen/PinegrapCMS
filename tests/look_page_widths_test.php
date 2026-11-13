<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's page widths in backend.src.css: the measures
 * are tokens with the decided values, an add or edit screen and a list take
 * them through their body class, and the first two screens carry it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_page_widths_test_source($path)
{
	$file = dirname(__DIR__) . '/pinegrap/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

function look_page_widths_test_block()
{
	$css = look_page_widths_test_source('assets/css/backend.src.css');
	$start = strpos($css, 'Look A: page widths.');

	return $start === false ? '' : substr($css, $start);
}

function test_page_width_tokens_hold_the_decided_measures()
{
	$block = look_page_widths_test_block();

	foreach (array('--pg-measure-edit: 1120px;', '--pg-measure-form: 760px;', '--pg-measure-status: 336px;', '--pg-measure-gap: 24px;', '--pg-measure-list-min: 1700px;', '--pg-measure-details: 20rem;') as $token) {
		pg_assert_contains($token, $block, $token);
	}
}

function test_page_width_rules_read_the_tokens()
{
	$block = look_page_widths_test_block();

	pg_assert_contains('body.pg-measure-edit #content {', $block, 'an edit screen holds its content box');
	pg_assert_contains('grid-template-columns: minmax(0, var(--pg-measure-form)) minmax(0, var(--pg-measure-status));', $block, 'form and status columns from the tokens');
	pg_assert_contains('.pg-edit-columns > .order-3 {', $block, 'the save bar spans both columns');
	pg_assert_contains('min-width: min(100%, max(var(--pg-list-held, 0px), var(--pg-measure-list-min) + 2rem));', $block, 'a list is at least the list measure (or its held width) or the window');
	pg_assert_contains('body.pg-measure-list #content:has(.pg-list-table-details:not([hidden])) {', $block, 'an open details panel widens the list');
	pg_assert_contains('min-width: min(100%, max(var(--pg-list-held, 0px), var(--pg-measure-list-min) + 2rem) + var(--pg-measure-details));', $block, 'the panel is added to the closed width, up to the window');
	pg_assert_contains('flex: 0 0 var(--pg-measure-details);', look_page_widths_test_source('assets/css/backend.src.css'), 'the panel takes its width from the token');
	pg_assert_contains('body.pg-measure-list .pg-list-table-grid > thead > tr > th:first-child {', $block, 'the name column takes the slack');
}

function test_first_screens_take_the_measures()
{
	$builder = look_page_widths_test_source('product_builder.php');

	pg_assert_contains("'extra classes' => 'products pg-measure-edit',", $builder, 'the product screen is an edit screen');
	pg_assert_contains('<div class="row pg-edit-columns">', $builder, 'its column row');
	pg_assert_contains("'extra classes'=>'products pg-measure-list',", look_page_widths_test_source('view_products.php'), 'the products list is a list');
}

function test_lists_off_the_table_driver_take_the_list_measure()
{
	$screens = array('calendars', 'view_menu_items', 'view_sessions', 'view_performance_log', 'view_accessibility', 'backups', 'translations', 'marketplace_products', 'erp_purchase_orders', 'erp_quotes', 'erp_expenses', 'erp_expense_categories', 'erp_cheques', 'erp_bank_statements', 'erp_stock_counts', 'erp_invoice_recurrences', 'erp_audit');

	foreach ($screens as $screen) {
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-list\\b/", look_page_widths_test_source($screen . '.php')), $screen . ' is a list');
	}
}

function test_reports_take_the_report_measure()
{
	$screens = array('erp_dashboard', 'erp_aging', 'erp_cashflow', 'erp_profit', 'search_console', 'search_audits');

	foreach ($screens as $screen) {
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-report\\b/", look_page_widths_test_source($screen . '.php')), $screen . ' is a report');
	}
	pg_assert_true(substr_count(look_page_widths_test_source('erp_cashflow.php'), 'pg-measure-report') === 2, 'the cash flow and the cash forecast are reports');
}
