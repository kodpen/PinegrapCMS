<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the invoice direction of an export run (includes/erp/export.php):
 * only a profile that lists directions takes the one posted; the Paraşüt
 * sales invoice template always writes sales invoices, and a profile with no
 * direction (accounts, receipts) keeps the default.
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

function test_erp_export_direction_follows_the_profile()
{
	$csv = array('id' => 'csv_invoices', 'directions' => array('sales', 'purchase'));
	$parasut = array('id' => 'parasut_invoices', 'directions' => array('sales'));
	$accounts = array('id' => 'csv_accounts');

	pg_assert_same('purchase', erp_export_direction($csv, 'purchase'), 'the generic CSV writes purchases');
	pg_assert_same('sales', erp_export_direction($csv, 'sales'), 'the generic CSV writes sales');
	pg_assert_same('sales', erp_export_direction($csv, ''), 'nothing posted is sales');
	pg_assert_same('sales', erp_export_direction($parasut, 'purchase'), 'the Paraşüt sales template never writes purchases');
	pg_assert_same('sales', erp_export_direction($accounts, 'purchase'), 'a profile without directions keeps sales');
	pg_assert_same('sales', erp_export_direction(null, 'purchase'), 'no profile');
}

function test_erp_export_direction_refusal()
{
	$csv = array('id' => 'csv_invoices', 'directions' => array('sales', 'purchase'));
	$parasut = array('id' => 'parasut_invoices', 'directions' => array('sales'));
	$accounts = array('id' => 'csv_accounts');

	pg_assert_same(false, erp_export_direction_refused($csv, 'purchase'), 'CSV takes purchases');
	pg_assert_same(true, erp_export_direction_refused($parasut, 'purchase'), 'Paraşüt sales refuses purchases');
	pg_assert_same(false, erp_export_direction_refused($parasut, 'sales'), 'Paraşüt sales takes sales');
	pg_assert_same(false, erp_export_direction_refused($parasut, ''), 'a disabled selector posts nothing');
	pg_assert_same(false, erp_export_direction_refused($accounts, 'purchase'), 'a profile without directions ignores it');
}

function test_erp_export_filters_take_the_profile_direction()
{
	$filters = erp_export_filters(array('direction' => 'purchase'), array('id' => 'parasut_invoices', 'directions' => array('sales')));
	pg_assert_same('sales', $filters['direction'], 'Paraşüt sales filters on sales');
	$filters = erp_export_filters(array('direction' => 'purchase'), array('id' => 'csv_invoices', 'directions' => array('sales', 'purchase')));
	pg_assert_same('purchase', $filters['direction'], 'CSV filters on purchases');
}
