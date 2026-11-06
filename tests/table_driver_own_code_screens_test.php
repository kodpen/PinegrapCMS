<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The screens that had their own DataTables code are off it: the product
 * group form's products table holds form fields, so it is a plain sortable
 * table that keeps every row in the form; the File Manager's injected file
 * table is a plain table; the link picker is a printed list of the table
 * driver.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_product_group_products_table_is_plain()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');

	pg_assert_contains('class="table table-hover mb-0" data-pg-sort>', $src, 'a plain table sorted by its headings');
	pg_assert_false((bool) preg_match('/class="chart[ "]/', $src), 'not a table.chart');
	pg_assert_false(strpos($src, 'DataTable') !== false, 'no DataTables call');
	pg_assert_contains('$table.children("tbody").append($rows);', $src, 'loaded rows join the table');
	pg_assert_contains('$productsTable.on("change", "#select_all"', $src, 'Select All works without DataTables');
	pg_assert_contains('.prop("indeterminate", (ticked > 0) && (ticked < $boxes.length))', $src, 'a part-ticked list shows the mixed state');
}

function test_explorer_file_table_is_plain()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/explorer.php');

	pg_assert_contains("\$output_table_classes = 'table table-hover text-nowrap';", $src);
	pg_assert_false(strpos($src, 'datatable-') !== false, 'no DataTables flags');
}

function test_link_picker_is_a_printed_driver_list()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/editor_select_page_or_file.php');

	pg_assert_contains("pg_list_table_html(\$list['source'], \$list['page'], lang('Browse Items'))", $src);
	pg_assert_false((bool) preg_match('/class="chart[ "]/', $src), 'not a table.chart');

	foreach (array('page', 'file', 'short_link') as $type) {
		pg_assert_contains("\$list_name = 'editor_select_page_or_file_" . $type . "';", $src);
		pg_assert_true(in_array('editor_select_page_or_file_' . $type, pg_list_table_printed_lists(), true), $type . ' keeps a heading\'s sort');
	}
}
