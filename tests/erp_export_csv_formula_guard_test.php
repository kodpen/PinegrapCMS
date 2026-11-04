<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the formula guard of the generic CSV exports
 * (includes/erp/export.php): text cells of the accounts, invoices and
 * receipts files that start with a formula trigger get an apostrophe, amount
 * cells (a cancellation's negative amount) stay numbers, and the accounts
 * import reads a guarded cell back as it was entered.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/settlement.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/export.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/import.php');

function erp_export_csv_guard_profile()
{
	return array('format' => 'csv', 'decimal' => '.', 'date' => 'Y-m-d', 'currency_map' => array());
}

function test_erp_export_accounts_csv_guards_text_cells()
{
	$rows = erp_export_rows_csv_accounts(array(array(
		'title' => '=1+1',
		'kind' => 'customer',
		'is_person' => 0,
		'tax_number' => '123',
		'tax_office' => '+Office',
		'email' => '@mail',
		'phone' => '+90 532',
		'address' => "\tStreet",
		'district' => '-D',
		'city' => '=C',
		'state' => '@S',
		'postcode' => '34000',
		'country_code' => 'tr',
		'currency' => 'TRY',
		'status' => 'active',
		'notes' => '=HYPERLINK("x")',
		'payment_days' => 0,
		'overdue_notify_days' => 0,
		'overdue_notify_customer' => 1,
		'balance' => -1500,
		'id' => 7,
	)), erp_export_csv_guard_profile());

	$row = $rows[0];
	pg_assert_same("'=1+1", $row[0], 'title');
	pg_assert_same("'+Office", $row[4], 'tax office');
	pg_assert_same("'@mail", $row[5], 'email');
	pg_assert_same("'+90 532", $row[6], 'phone');
	pg_assert_same("'\tStreet", $row[7], 'address');
	pg_assert_same("'-D", $row[8], 'district');
	pg_assert_same("'=C", $row[9], 'city');
	pg_assert_same("'@S", $row[10], 'state');
	pg_assert_same("'=HYPERLINK(\"x\")", $row[15], 'notes');
	pg_assert_same('-15.00', $row[19], 'a negative balance stays a number');
}

function test_erp_export_invoices_csv_guards_text_cells()
{
	$rows = erp_export_rows_csv_invoices(array(array(
		'full_number' => 'A1',
		'issue_date' => '2026-10-31',
		'due_date' => '2026-11-30',
		'direction' => 'sales',
		'doc_type' => 'invoice',
		'status' => 'issued',
		'party_snapshot' => '',
		'buyer_snapshot' => json_encode(array('title' => '=Buyer', 'tax_number' => '1', 'tax_office' => '@Office', 'country_code' => 'TR')),
		'account_title' => '=Buyer',
		'account_tax_number' => '1',
		'account_tax_office' => '@Office',
		'account_country_code' => 'TR',
		'currency' => 'TRY',
		'exchange_rate' => 1,
		'subtotal' => 100,
		'tax_total' => 0,
		'grand_total' => 100,
		'grand_total_base' => 100,
		'paid_total' => 0,
		'order_number' => '',
		'id' => 3,
		'lines' => array(array('line_no' => 1, 'description' => '-Line', 'quantity' => 1, 'unit_code' => 'C62', 'unit_price' => 100, 'discount_amount' => 0, 'tax_rate' => 0, 'tax_total' => 0, 'line_total' => 100)),
	)), erp_export_csv_guard_profile());

	$row = $rows[0];
	pg_assert_same("'=Buyer", $row[6], 'buyer title');
	pg_assert_same("'@Office", $row[8], 'tax office');
	pg_assert_same("'-Line", $row[13], 'line description');
}

function test_erp_export_receipts_csv_guards_text_but_not_amounts()
{
	$rows = erp_export_rows_csv_receipts(array(array(
		'doc_date' => '2026-10-31',
		'doc_type' => 'cancel',
		'account_title' => '=Acc',
		'account_tax_number' => '1',
		'till_name' => '+Till',
		'payment_method' => 'cash',
		'amount' => 10000,
		'currency' => 'TRY',
		'exchange_rate' => 1,
		'amount_base' => 10000,
		'description' => '@desc',
		'id' => 2,
		'doc_id' => 1,
	)), erp_export_csv_guard_profile());

	$row = $rows[0];
	pg_assert_same("'=Acc", $row[2], 'account title');
	pg_assert_same("'+Till", $row[4], 'till name');
	pg_assert_same("'@desc", $row[10], 'description');
	pg_assert_same('-100.00', $row[6], 'the cancellation amount stays negative');
	pg_assert_same('-100.00', $row[9], 'the base amount stays negative');
}

function test_erp_import_read_unguards_cells()
{
	$path = tempnam(sys_get_temp_dir(), 'pgcsv');
	file_put_contents($path, "Title;Phone\n'=1+1;'+90 532\n'plain;x\n");
	$read = erp_import_read($path, ';');
	@unlink($path);

	pg_assert_same(array('=1+1', '+90 532'), $read['rows'][0]['cells'], 'guarded cells are read back as entered');
	pg_assert_same("'plain", $read['rows'][1]['cells'][0], 'an apostrophe before plain text stays');
}
