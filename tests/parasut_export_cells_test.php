<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Orders > Export for Paraşüt Selected writes the text a customer
 * or staff member typed (name, e-mail, address, city, state, identity number,
 * product name) as text. pg_xlsx_write_layout() writes every string as an
 * inline text cell and never a formula (parasut_sheet_xlsx_test.php); the
 * export hands it those values as strings, so a checkout name like
 * '=HYPERLINK(...)' or a number-like identity number stays as typed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function parasut_export_cells_block()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_orders.php');
	$start = strpos($source, "case 'export_orders_for_parasut':");
	pg_assert_true($start !== false, 'export_orders_for_parasut case exists');
	$end = strpos($source, "case 'remove_card_data':", $start);
	pg_assert_true($end !== false, 'next case found');
	return substr($source, $start, $end - $start);
}

// Every typed text column is handed to the writer as a string.
function test_parasut_export_typed_text_is_text_cell()
{
	$block = parasut_export_cells_block();
	$cells = array(
		"'A' => (string) \$customer_billing_name,",
		"'D' => (string) \$billing_email_address,",
		"'F' => (string) \$billing_combined_address,",
		"'G' => (string) \$billing_city,",
		"'H' => (string) \$billing_state,",
		"'I' => (string) \$billing_phone_number,",
		"'J' => (string) \$billing_fax_number,",
		"'M' => (string) \$identification_number,",
		"'B' => \$organization_name . ' - ' . \$order_number,",
		"'L' => (string) \$product_name,",
	);
	foreach ($cells as $cell) {
		pg_assert_true(strpos($block, $cell) !== false, 'text cell: ' . $cell);
	}
	pg_assert_same(2, substr_count($block, "'A' => (string) \$customer_billing_name,"), 'the name on both sheets');
}

// The sheets are written by the shared writer under Paraşüt's heads, with
// no template file.
function test_parasut_export_uses_the_shared_writer()
{
	$block = parasut_export_cells_block();
	pg_assert_same(1, substr_count($block, 'pg_xlsx_write_layout($invoice_layout, '), 'the invoice workbook');
	pg_assert_same(1, substr_count($block, "pg_xlsx_write_layout(pg_parasut_sheet_layout('contacts'), "), 'the contacts workbook');
	pg_assert_true(strpos($block, "pg_parasut_sheet_layout('invoices')") !== false, 'invoice sheet head');
	pg_assert_true(strpos($block, "pg_parasut_sheet_layout('contacts')") !== false, 'contacts sheet head');
	pg_assert_true(strpos($block, "array(2 => 'd.mm.yyyy', 5 => 'd.mm.yyyy', 15 => '#,##0.00', 16 => '#,##0.00', 17 => '#,##', 18 => '#,##0.00')") !== false, 'the column formats');
}
