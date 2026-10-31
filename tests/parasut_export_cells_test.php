<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Orders > Export for Paraşüt Selected writes the text a customer
 * or staff member typed (name, e-mail, address, city, state, identity number,
 * product name) as explicit text cells. PHPExcel's default binder stores a
 * string that starts with '=' as a formula and '#N/A' as an error, so a
 * checkout name like '=HYPERLINK(...)' would run when the workbook is opened.
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

// Every typed text column goes through setCellValueExplicit(..., TYPE_STRING).
function test_parasut_export_typed_text_is_text_cell()
{
	$block = parasut_export_cells_block();
	$cells = array(
		"'A'. \$i_obj_2 ,\$customer_billing_name",
		"'D' . \$i_obj_2,\$billing_email_address",
		"'F' . \$i_obj_2,\$billing_combined_address",
		"'G' . \$i_obj_2,\$billing_city",
		"'H' . \$i_obj_2,\$billing_state",
		"'I' . \$i_obj_2,\$billing_phone_number",
		"'J' . \$i_obj_2,\$billing_fax_number",
		"'M' . \$i_obj_2,\$identification_number",
		"'A'. \$i_obj_1 ,\$customer_billing_name",
		"'B' . \$i_obj_1, \$organization_name . ' - ' . \$order_number",
		"'L' . \$i_obj_1,\$product_name",
	);
	foreach ($cells as $cell) {
		pg_assert_true(
			strpos($block, '->setCellValueExplicit(' . $cell . ',PHPExcel_Cell_DataType::TYPE_STRING)') !== false,
			'text cell: ' . $cell
		);
		pg_assert_true(strpos($block, '->setCellValue(' . $cell) === false, 'no bound cell: ' . $cell);
	}
}

// No variable reaches a plain setCellValue() except the dates and numbers.
function test_parasut_export_bound_cells_are_dates_and_numbers()
{
	$block = parasut_export_cells_block();
	preg_match_all('/->setCellValue\(\s*\'([A-Z])\'\s*\.\s*\$i_obj_(\d)\s*,\s*([^\n]*)/', $block, $matches, PREG_SET_ORDER);
	pg_assert_true(count($matches) > 0, 'bound cells found');
	$allowed = array('C', 'E', 'F', 'H', 'K', 'L', 'O', 'P', 'Q', 'R', 'S');
	foreach ($matches as $match) {
		$column = $match[1];
		$value = $match[3];
		pg_assert_true(in_array($column, $allowed, true), 'bound column ' . $column . ' is a fixed or numeric column');
		if ($column === 'L') {
			pg_assert_true(strpos($value, 'lang(') === 0, 'bound L holds only a translated fee label');
		}
		if ($column === 'F' && $match[2] === '2') {
			pg_assert_true(false, 'contacts F (address) is a text cell');
		}
	}
}
