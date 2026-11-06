<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that an ERP account CSV import in update mode leaves a stored value
 * alone where the file's cell is empty. A mapped column with an empty cell
 * counted as supplied, so the row's fallback took the stored value's place:
 * the default type and country, the base currency, the Active status, and a
 * Taxpayer guessed from the tax number.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/accounts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/import.php');

/**
 * A stored account as erp_account() returns it.
 *
 * @return array
 */
function erp_import_update_empty_cell_existing()
{
	return array(
		'id' => 7, 'kind' => 'supplier', 'title' => 'Acme GmbH', 'is_person' => 0, 'tax_number' => '',
		'tax_office' => '', 'email' => 'acme@example.com', 'phone' => '+49 30 1234', 'address' => 'Hauptstr. 1',
		'district' => '', 'city' => 'Berlin', 'state' => '', 'country_code' => 'DE', 'postcode' => '10115',
		'currency' => 'EUR', 'contact_id' => 0, 'status' => 'passive', 'notes' => 'Kept', 'payment_days' => 30,
		'overdue_notify_days' => 5, 'balance_fc' => 0,
	);
}

/**
 * The import's field list with the lengths it trims to; erp_import_fields()
 * reads the tax number width from the schema.
 *
 * @return array
 */
function erp_import_update_empty_cell_fields()
{
	$fields = array();
	foreach (array_keys(erp_import_field_synonyms()) as $field) {
		$fields[$field] = array('length' => 255);
	}
	return $fields;
}

function test_erp_import_update_keeps_stored_values_behind_empty_cells()
{
	// Name, E-mail, Phone filled; Type, Taxpayer, Country, Currency and
	// Status mapped but empty on this row.
	$mapping = array(0 => 'title', 1 => 'email', 2 => 'phone', 3 => 'kind', 4 => 'is_person', 5 => 'country_code', 6 => 'currency', 7 => 'status');
	$cells = array('Acme GmbH', 'acme@example.com', '+49 30 9999', '', '', '', '', '');
	$defaults = array('kind' => 'customer', 'country_code' => 'US', 'currency' => 'USD');

	$account = erp_import_normalize_row($cells, $mapping, $defaults, erp_import_update_empty_cell_fields());
	$data = erp_import_merge(erp_import_update_empty_cell_existing(), $account);

	pg_assert_same('+49 30 9999', $data['phone'], 'a filled cell is written');
	pg_assert_same('supplier', $data['kind'], 'an empty Type cell keeps the stored type');
	pg_assert_same(false, $data['is_person'], 'an empty Taxpayer cell keeps the stored company');
	pg_assert_same('DE', $data['country_code'], 'an empty Country cell keeps the stored country');
	pg_assert_same('EUR', $data['currency'], 'an empty Currency cell keeps the stored currency');
	pg_assert_same('passive', $data['status'], 'an empty Status cell keeps the stored status');
}

function test_erp_import_update_writes_filled_coded_cells()
{
	$mapping = array(0 => 'title', 1 => 'kind', 2 => 'is_person', 3 => 'status');
	$cells = array('Acme GmbH', 'customer', 'person', 'active');
	$defaults = array('kind' => 'customer', 'country_code' => 'US', 'currency' => 'USD');

	$data = erp_import_merge(erp_import_update_empty_cell_existing(), erp_import_normalize_row($cells, $mapping, $defaults, erp_import_update_empty_cell_fields()));

	pg_assert_same('customer', $data['kind']);
	pg_assert_same(true, $data['is_person']);
	pg_assert_same('active', $data['status']);
	pg_assert_same('DE', $data['country_code'], 'an unmapped Country keeps the stored country');
}
