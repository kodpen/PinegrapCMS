<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The arrival dates, ship date adjustments, verified shipping addresses,
 * order reports and duplicate products lists on the list table driver, and
 * the shipping report as a plain report table.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/list_table.php';

function test_commerce_lists_td10b_printed()
{
	$lists = array(
		'view_arrival_dates' => 'view_arrival_dates.php',
		'view_ship_date_adjustments' => 'view_ship_date_adjustments.php',
		'view_verified_shipping_addresses' => 'view_verified_shipping_addresses.php',
		'view_order_reports' => 'view_order_reports.php',
		'duplicate_products' => 'duplicate_products.php',
	);

	foreach ($lists as $list => $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(in_array($list, pg_list_table_printed_lists(), true), $list . ' keeps a heading sort');
		pg_assert_false(strpos($source, '<table class="chart') !== false, $file . ': no DataTables table left');
		pg_assert_contains("'list' => '" . $list . "'", $source, $file . ': printed under its pg_list_state() name');
		pg_assert_contains("pg_list_table_search_box('" . $list . "')", $source, $file . ': toolbar search box');
		pg_assert_contains("pg_js_bundle_tags('assets/js/pg_list_table.min.js')", $source, $file . ': driver loaded');
	}
}

function test_commerce_lists_td10b_duplicate_products_selection()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/duplicate_products.php');

	// The POST handler still reads products[]; the driver fills it.
	pg_assert_contains("'ids_field' => 'products[]'", $source);
	pg_assert_contains("\$_POST['products']", $source);
	pg_assert_false(strpos($source, 'id="select_all"') !== false, 'the old select-all box is gone');
	pg_assert_contains('array_intersect_key($cells, $columns)', $source, 'a row has only the columns its filter shows');
}

function test_commerce_lists_td10b_shipping_report_plain()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_shipping_report.php');

	pg_assert_false(strpos($source, '<table class="chart') !== false, 'not a DataTable');
	pg_assert_contains('<table class="shipping_report pg-report-table">', $source);
	pg_assert_contains('<tfoot>', $source, 'the Grand Total stays the foot');
}
