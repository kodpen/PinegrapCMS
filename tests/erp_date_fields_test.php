<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the ERP screens' native date inputs are drawn on the one
 * date-and-time control: every <input type="date"> carries data-pg-date (it
 * stays the posted ISO store) and every such screen prints the site's date
 * order next to the field script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_date_fields_test_screens()
{
	return array(
		'erp_accountant.php', 'erp_aging.php', 'erp_audit.php', 'erp_cashflow.php', 'erp_cheque.php',
		'erp_expenses.php', 'erp_inbox.php', 'erp_profit.php', 'erp_reconciliation.php', 'erp_vat_report.php',
		'edit_erp_invoice.php',
	);
}

function test_erp_date_fields_native_inputs_are_hooked()
{
	foreach (erp_date_fields_test_screens() as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		preg_match_all('/<input type="date"[^>]*>/', $source, $inputs);

		pg_assert_true(count($inputs[0]) > 0, $file . ' has a native date input');

		foreach ($inputs[0] as $input) {
			pg_assert_true(strpos($input, ' data-pg-date ') !== false, $file . ': ' . substr($input, 0, 90) . ' is hooked');
		}
	}
}

function test_erp_date_fields_screens_print_the_date_order_with_the_script()
{
	foreach (erp_date_fields_test_screens() as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(strpos($source, 'get_date_picker_format() . pg_fields_script()') !== false, $file . ' prints the date order with the field script');
		pg_assert_same(1, substr_count($source, 'pg_fields_script()'), $file . ' prints the field script once');
	}
}
