<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The screens that had their own DataTables code are off it: the product
 * group form's products table holds form fields, so it is a plain sortable
 * table that keeps every row in the form; the File Manager's injected file
 * table is a plain table.
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
