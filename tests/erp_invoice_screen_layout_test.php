<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the layout of the invoice create and draft screens and the new
 * quote and new purchase order screens: the shared
 * editor (includes/erp/invoice_form.php) lays them out as a form column, a
 * status column with the totals and a sticky save bar, splitting only from
 * 2xl because the lines table needs the width.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_invoice_screen_layout_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_erp_invoice_editor_columns()
{
	$source = erp_invoice_screen_layout_test_source('includes/erp/invoice_form.php');

	pg_assert_contains("\$columns = !empty(\$options['columns']);", $source, 'the option');
	pg_assert_contains('<div class="col-12 2xl:col-9 order-1">', $source, 'form column');
	pg_assert_contains('<div class="col-12 2xl:col-3 order-2">', $source, 'status column');
	pg_assert_contains('<div class="position-sticky my-7" style="top:3.3rem">', $source, 'the sticky box inside the status column');
	pg_assert_contains('<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">', $source, 'sticky save bar column');
	pg_assert_true(strpos($source, 'class="col-12 lg:col-8') === false, 'no lg split: the lines table scrolled sideways in it');
}

function test_erp_invoice_screens_use_the_columns()
{
	foreach (array('add_erp_manual_invoice.php', 'edit_erp_invoice_draft.php', 'add_erp_quote.php', 'add_erp_purchase_order.php') as $file) {
		$source = erp_invoice_screen_layout_test_source($file);

		pg_assert_contains("'columns' => true,", $source, $file . ': the columns');
		pg_assert_contains("'save_bar' =>", $source, $file . ': the save bar in the row');
		pg_assert_true(strpos($source, 'position-sticky mb-7" style="bottom:.5rem;"') === false, $file . ': the nav itself is no longer the sticky part');
	}

	// The new quote's templates card leads the form column.
	pg_assert_contains('\'lead\' => $output_templates,', erp_invoice_screen_layout_test_source('add_erp_quote.php'), 'the templates above the quote');
	pg_assert_contains('<div class="col-12 2xl:col-9 order-1">\' . ((string) ($options[\'lead\'] ?? \'\'))', erp_invoice_screen_layout_test_source('includes/erp/invoice_form.php'), 'lead at the top of the form column');

	// The quote edit and purchase order edit screens keep their own layout.
	foreach (array('edit_erp_quote.php', 'edit_erp_purchase_order.php') as $file) {
		pg_assert_true(strpos(erp_invoice_screen_layout_test_source($file), "'columns' => true") === false, $file . ': not laid out in columns');
	}
}
